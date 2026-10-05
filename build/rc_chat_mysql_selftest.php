<?php

declare(strict_types=1);

namespace RcChatMysqlSelftest;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Run sequentially: php build/rc_chat_mysql_selftest.php [--offline]
 * Requires the installed cms_chat, cms_chat_replies and cms_chat_history, all
 * InnoDB. No DDL, application bootstrap, authentication, cron or SMTP is loaded.
 * Only three unchanged production functions are token-extracted into this
 * namespace; both processes extract their own copy of lib/messages.php.
 *
 * Fixtures: one random rc-chat-mysql-<24 hex> parent per race, NULL topic/actor,
 * .invalid email, and (delete/retention only) one marked reply/history row.
 * All helper SQL/parameters pass a fail-closed ID/prefix-validated PDO adapter.
 * The reply adapter reuses exactly one prestarted transaction at beginTransaction
 * so the production locking read runs against an already stale RR snapshot.
 * No SQL, lock, predicate or commit is mocked in the MySQL mode. After the actual
 * locking read, an extra nonlocking read proves the original snapshot survives.
 * Parent locks, stable exact PROCESSLIST SQL and an authenticated TCP barrier
 * establish the wait. No transaction-information snapshot is used as evidence.
 * Cleanup rechecks the locked parent's prefix before EVERY DELETE; an absent
 * parent permits only zero-child assertions, never unverified orphan deletion.
 * --offline exercises real extracted helpers with a contract-only PDO double;
 * it establishes no MySQL/concurrency evidence and opens no database connection.
 */
require_once dirname(__DIR__) . '/config.php';

const CUTOFF = '2020-02-01 00:00:00';
const OLD_TIME = '2020-01-01 00:00:00';
const NEW_TIME = '2020-03-01 00:00:00';
const REPLY_LOCK = "SELECT id FROM cms_chat WHERE id = ? AND conversation_type = 'public' AND public_visibility = 'approved' FOR UPDATE";
const DELETE_LOCK = 'SELECT id FROM cms_chat WHERE id = ? FOR UPDATE';
const RETENTION_LOCK = "SELECT id FROM cms_chat WHERE id = ? AND status = 'handled' AND updated_at < ? FOR UPDATE";
const SNAPSHOT_SQL = 'SELECT name, message, conversation_type, public_visibility, status, updated_at FROM cms_chat WHERE id = ? AND name = ? AND message = ?';
const OWNER_SQL = 'SELECT name, message FROM cms_chat WHERE id = ?';

$checks = 0;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $GLOBALS['checks']++;
}

function normalizeSql(string $sql): string
{
    return preg_replace('/\s+/', ' ', trim($sql)) ?? '';
}

/** @return array<string,string> */
function loadHelpers(): array
{
    $names = ['chatCreatePublicReply', 'deleteChatMessage', 'chatHistoryCreate'];
    $source = file_get_contents(dirname(__DIR__) . '/lib/messages.php');
    check(is_string($source), 'Cannot read lib/messages.php.');
    $tokens = token_get_all($source, TOKEN_PARSE);
    $functions = [];
    foreach ($tokens as $index => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }
        $cursor = $index + 1;
        while (isset($tokens[$cursor]) && is_array($tokens[$cursor])
            && in_array($tokens[$cursor][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $cursor++;
        }
        $name = $tokens[$cursor] ?? null;
        if (!is_array($name) || $name[0] !== T_STRING || !in_array($name[1], $names, true)) {
            continue;
        }
        $code = '';
        $depth = 0;
        $started = false;
        for ($cursor = $index, $length = count($tokens); $cursor < $length; $cursor++) {
            $part = $tokens[$cursor];
            $code .= is_array($part) ? $part[1] : $part;
            if ($part === '{' || (is_array($part)
                && in_array($part[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
                $started = true;
            } elseif ($part === '}' && $started && --$depth === 0) {
                check(!isset($functions[$name[1]]), 'Duplicate extracted helper.');
                $functions[$name[1]] = $code;
                break;
            }
        }
    }
    $found = array_keys($functions);
    sort($found);
    sort($names);
    check($found === $names, 'Missing/incomplete chat lifecycle helpers.');
    eval('namespace ' . __NAMESPACE__ . '; use \\PDO; use \\RuntimeException; ' . implode("\n", $functions));
    return $functions;
}

function connect(): PDO
{
    $dbHost = (string)$GLOBALS['server'];
    $dbName = (string)$GLOBALS['database'];
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        (string)$GLOBALS['user'],
        (string)$GLOBALS['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );
    $pdo->exec("SET SESSION time_zone = '+00:00'");
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 12');
    $pdo->exec('SET SESSION lock_wait_timeout = 12');
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    return $pdo;
}

function validScope(int $chatId, string $prefix): bool
{
    return $chatId > 0 && preg_match('/^rc-chat-mysql-[a-f0-9]{24}$/D', $prefix) === 1;
}

function assertOwner(PDO $pdo, int $chatId, string $prefix, bool $locking = false): void
{
    check(validScope($chatId, $prefix), 'Invalid chat fixture scope.');
    if ($locking) {
        check($pdo->inTransaction(), 'Ownership lock needs a transaction.');
    }
    $statement = $pdo->prepare(OWNER_SQL . ($locking ? ' FOR UPDATE' : ''));
    $statement->execute([$chatId]);
    $row = $statement->fetch();
    check(
        is_array($row) && $row['name'] === $prefix && $row['message'] === $prefix . ':parent',
        'Refusing a missing/foreign chat fixture.'
    );
}

final class FixturePdo extends PDO
{
    private PDO $connection;
    private int $chatId;
    private string $prefix;
    private bool $reuseBegin = false;
    public ?array $initialSnapshot = null;
    public ?array $afterLockSnapshot = null;
    public array $trace = [];
    public int $lockReads = 0;
    public int $reusedBegins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;

    public function __construct(PDO $connection, int $chatId, string $prefix)
    {
        assertOwner($connection, $chatId, $prefix);
        check($connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql', 'MySQL semantics required.');
        $this->connection = $connection;
        $this->chatId = $chatId;
        $this->prefix = $prefix;
    }

    public static function role(string $sql): string
    {
        $allowed = [
            SNAPSHOT_SQL => 'snapshot', REPLY_LOCK => 'reply-lock',
            DELETE_LOCK => 'delete-lock', RETENTION_LOCK => 'retention-lock',
            "INSERT INTO cms_chat_replies (chat_id, name, email, message, status) VALUES (?, ?, ?, ?, 'pending')" => 'reply',
            'INSERT INTO cms_chat_history (chat_id, actor_user_id, event_type, message) VALUES (?, ?, ?, ?)' => 'history',
            'UPDATE cms_chat SET updated_at = CURRENT_TIMESTAMP WHERE id = ?' => 'touch',
            'DELETE FROM cms_chat_replies WHERE chat_id = ?' => 'delete-replies',
            'DELETE FROM cms_chat_history WHERE chat_id = ?' => 'delete-history',
            'DELETE FROM cms_chat WHERE id = ?' => 'delete-parent',
        ];
        $normalized = normalizeSql($sql);
        check(isset($allowed[$normalized]), 'Unknown/unscoped helper SQL refused: ' . $normalized);
        return $allowed[$normalized];
    }

    public function validate(string $role, ?array $params): void
    {
        check(
            is_array($params) && array_keys($params) === range(0, count($params) - 1),
            'Only explicit positional helper parameters are allowed.'
        );
        $valid = false;
        if ($role === 'snapshot') {
            $valid = $params === [$this->chatId, $this->prefix, $this->prefix . ':parent'];
        } elseif ($role === 'retention-lock') {
            $valid = $params === [$this->chatId, CUTOFF];
        } elseif ($role === 'reply') {
            $valid = $params === [$this->chatId, $this->prefix, $this->prefix . '@example.invalid', $this->prefix . ':worker-reply'];
        } elseif ($role === 'history') {
            $valid = count($params) === 4 && $params[0] === $this->chatId && $params[1] === null
                && $params[2] === 'reply_submitted' && is_string($params[3]) && trim($params[3]) !== '';
        } elseif (in_array($role, ['reply-lock', 'delete-lock', 'touch', 'delete-replies', 'delete-history', 'delete-parent'], true)) {
            $valid = $params === [$this->chatId];
        }
        check($valid, 'Foreign/unsupported helper parameters refused: ' . $role);
        check($this->inTransaction(), 'Helper statements must remain inside a transaction.');
        if (!in_array($role, ['snapshot', 'reply-lock', 'delete-lock', 'retention-lock'], true)) {
            check($this->lockReads === 1, 'Writes must follow the actual parent locking read.');
            assertOwner($this->connection, $this->chatId, $this->prefix, true);
        }
        $this->trace[] = $role;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        check($options === [], 'Custom helper statement options are forbidden.');
        $role = self::role($query);
        $statement = $this->connection->prepare($query);
        check($statement instanceof PDOStatement, 'Cannot prepare helper statement.');
        return new FixtureStatement($this, $statement, $role);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        throw new RuntimeException('Unscoped helper query() is forbidden.');
    }

    public function exec(string $statement): int|false
    {
        throw new RuntimeException('Unscoped helper exec() is forbidden.');
    }

    public function getAttribute(int $attribute): mixed
    {
        check($attribute === PDO::ATTR_DRIVER_NAME, 'Unsupported helper attribute.');
        return $this->connection->getAttribute($attribute);
    }

    public function beginTransaction(): bool
    {
        if ($this->reuseBegin) {
            check($this->inTransaction() && $this->initialSnapshot !== null, 'Lost prestarted snapshot transaction.');
            $this->reuseBegin = false;
            $this->reusedBegins++;
            return true;
        }
        return $this->connection->beginTransaction();
    }

    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }

    public function commit(): bool
    {
        $this->reuseBegin = false;
        $this->commits++;
        return $this->connection->commit();
    }

    public function rollBack(): bool
    {
        $this->reuseBegin = false;
        $this->rollbacks++;
        return $this->connection->rollBack();
    }

    private function snapshot(): array
    {
        $statement = $this->prepare(SNAPSHOT_SQL);
        $statement->execute([$this->chatId, $this->prefix, $this->prefix . ':parent']);
        $row = $statement->fetch();
        check(is_array($row), 'Original parent must remain visible in the old snapshot.');
        return $row;
    }

    public function startSnapshot(bool $reply): void
    {
        check(!$this->inTransaction() && $this->initialSnapshot === null, 'Snapshot can be established only once.');
        $this->connection->beginTransaction();
        $this->initialSnapshot = $this->snapshot();
        $this->reuseBegin = $reply;
    }

    public function executed(string $role): void
    {
        if (in_array($role, ['reply-lock', 'delete-lock', 'retention-lock'], true)) {
            $this->lockReads++;
            check($this->lockReads === 1, 'Expected exactly one production parent lock read.');
            if ($this->initialSnapshot !== null) {
                $this->afterLockSnapshot = $this->snapshot();
                check(
                    $this->afterLockSnapshot === $this->initialSnapshot,
                    'Production lock must not replace the preexisting RR snapshot.'
                );
            }
        }
    }
}

final class FixtureStatement extends PDOStatement
{
    private FixturePdo $owner;
    private PDOStatement $statement;
    private string $role;

    public function __construct(FixturePdo $owner, PDOStatement $statement, string $role)
    {
        $this->owner = $owner;
        $this->statement = $statement;
        $this->role = $role;
    }

    public function execute(?array $params = null): bool
    {
        $this->owner->validate($this->role, $params);
        $result = $this->statement->execute($params);
        $this->owner->executed($this->role);
        return $result;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        throw new RuntimeException('Prebound helper parameters are forbidden.');
    }

    public function bindParam(string|int $param, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        throw new RuntimeException('Prebound helper parameters are forbidden.');
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->statement->fetchColumn($column);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->statement->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    public function rowCount(): int
    {
        return (int)$this->statement->rowCount();
    }
}

final class Channel
{
    /** @var resource */
    private $stream;
    private string $token;
    private string $buffer = '';

    /** @param resource $stream */
    public function __construct($stream, string $token)
    {
        check(is_resource($stream), 'Missing TCP barrier stream.');
        $this->stream = $stream;
        $this->token = $token;
        stream_set_blocking($stream, false);
    }

    public function send(array $message): void
    {
        $message['token'] = $this->token;
        $line = json_encode($message, JSON_THROW_ON_ERROR) . "\n";
        $offset = 0;
        $deadline = microtime(true) + 5;
        while ($offset < strlen($line) && microtime(true) < $deadline) {
            $read = $except = null;
            $write = [$this->stream];
            check(stream_select($read, $write, $except, 0, 100000) !== false, 'Barrier write polling failed.');
            if ($write === []) {
                continue;
            }
            $written = fwrite($this->stream, substr($line, $offset));
            check($written !== false && $written > 0, 'Barrier disconnected during write.');
            $offset += $written;
        }
        check($offset === strlen($line), 'Incomplete TCP barrier message.');
    }

    public function receive(float $seconds = 6): array
    {
        $deadline = microtime(true) + $seconds;
        while (microtime(true) < $deadline) {
            $newline = strpos($this->buffer, "\n");
            if ($newline !== false) {
                $line = substr($this->buffer, 0, $newline);
                $this->buffer = substr($this->buffer, $newline + 1);
                $message = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
                check(is_array($message) && ($message['token'] ?? '') === $this->token, 'Unauthenticated TCP barrier message.');
                return $message;
            }
            $read = [$this->stream];
            $write = $except = null;
            $readable = stream_select($read, $write, $except, 0, 100000);
            check($readable !== false, 'Barrier read polling failed.');
            if ($readable > 0) {
                $chunk = fread($this->stream, 4096);
                check($chunk !== false && !($chunk === '' && feof($this->stream)), 'Worker disconnected before its result.');
                $this->buffer .= $chunk;
                check(strlen($this->buffer) <= 32768, 'Oversized TCP barrier message.');
            }
        }
        throw new RuntimeException('Timed out waiting for TCP barrier message.');
    }

    public function assertQuiet(): void
    {
        $read = [$this->stream];
        $write = $except = null;
        check(
            $this->buffer === '' && stream_select($read, $write, $except, 0, 0) === 0,
            'Worker returned/disconnected before the parent released its lock.'
        );
    }
}

function worker(array $arguments): int
{
    $pdo = null;
    $stream = null;
    try {
        check(count($arguments) === 7 && ctype_digit($arguments[2])
            && (string)(int)$arguments[2] === $arguments[2] && validScope((int)$arguments[2], $arguments[3])
            && preg_match('/^127\.0\.0\.1:[0-9]+$/D', $arguments[4]) === 1
            && in_array($arguments[5], ['hidden-support', 'delete', 'retention'], true)
            && preg_match('/^[a-f0-9]{32}$/D', $arguments[6]) === 1, 'Invalid isolated worker arguments.');
        $stream = stream_socket_client('tcp://' . $arguments[4], $errorCode, $errorText, 5);
        $channel = new Channel($stream, $arguments[6]);
        $connection = connect();
        $connectionId = (int)$connection->query('SELECT CONNECTION_ID()')->fetchColumn();
        $pdo = new FixturePdo($connection, (int)$arguments[2], $arguments[3]);
        $reply = $arguments[5] !== 'retention';
        $pdo->startSnapshot($reply);
        $old = $pdo->initialSnapshot;
        check($old['conversation_type'] === 'public' && $old['public_visibility'] === 'approved'
            && $old['status'] === 'handled' && $old['updated_at'] === OLD_TIME, 'Worker requires the original eligible snapshot.');
        $channel->send(['type' => 'ready', 'connection_id' => $connectionId, 'snapshot' => $old]);
        check(($channel->receive()['type'] ?? '') === 'run', 'Invalid worker start barrier.');
        $started = microtime(true);
        $accepted = $reply
            ? chatCreatePublicReply($pdo, (int)$arguments[2], $arguments[3], $arguments[3] . '@example.invalid', $arguments[3] . ':worker-reply')
            : deleteChatMessage($pdo, (int)$arguments[2], CUTOFF);
        check($pdo->lockReads === 1 && $pdo->afterLockSnapshot === $old, 'Missing actual lock/stale snapshot evidence.');
        check($pdo->reusedBegins === ($reply ? 1 : 0), 'Unexpected transaction restart/reuse.');
        check(
            $pdo->trace === ['snapshot', $reply ? 'reply-lock' : 'retention-lock', 'snapshot'],
            'Rejected lifecycle must not attempt child/history writes or parent deletion.'
        );
        check(!$accepted, 'Current parent state must reject the stale worker.');
        check($pdo->inTransaction() === !$reply, 'Unexpected production transaction ownership.');
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        check($pdo->commits === 1 && $pdo->rollbacks === 0, 'Worker must end exactly its original transaction.');
        $channel->send(['type' => 'result', 'accepted' => $accepted, 'snapshot_after_lock' => $pdo->afterLockSnapshot,
            'lock_reads' => $pdo->lockReads, 'reused_begins' => $pdo->reusedBegins,
            'trace' => $pdo->trace, 'wait_ms' => (int)round((microtime(true) - $started) * 1000)]);
        return 0;
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        return 1;
    } finally {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_resource($stream)) {
            fclose($stream);
        }
    }
}

function childRows(PDO $pdo, int $chatId, string $table): array
{
    check(in_array($table, ['cms_chat_replies', 'cms_chat_history'], true), 'Invalid fixture child table.');
    $statement = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE chat_id = ? ORDER BY id');
    $statement->execute([$chatId]);
    return $statement->fetchAll();
}

function assertNoRows(PDO $pdo, int $chatId): void
{
    foreach (['cms_chat', 'cms_chat_replies', 'cms_chat_history'] as $table) {
        $key = $table === 'cms_chat' ? 'id' : 'chat_id';
        $statement = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $key . ' = ?');
        $statement->execute([$chatId]);
        check((int)$statement->fetchColumn() === 0, 'Fixture/orphan remains in ' . $table . '.');
    }
}

function cleanup(PDO $pdo, int $chatId, string $prefix): void
{
    check(validScope($chatId, $prefix), 'Invalid cleanup scope.');
    $pdo->beginTransaction();
    try {
        $owner = $pdo->prepare(OWNER_SQL . ' FOR UPDATE');
        $owner->execute([$chatId]);
        $row = $owner->fetch();
        if ($row === false) {
            assertNoRows($pdo, $chatId);
        } else {
            check($row['name'] === $prefix && $row['message'] === $prefix . ':parent', 'Cleanup refused a foreign parent.');
            foreach (['cms_chat_replies', 'cms_chat_history', 'cms_chat'] as $table) {
                assertOwner($pdo, $chatId, $prefix, true);
                $sql = $table === 'cms_chat'
                    ? 'DELETE FROM cms_chat WHERE id = ? AND name = ? AND message = ?'
                    : 'DELETE FROM ' . $table . ' WHERE chat_id = ?';
                $pdo->prepare($sql)->execute($table === 'cms_chat' ? [$chatId, $prefix, $prefix . ':parent'] : [$chatId]);
            }
        }
        $pdo->commit();
        assertNoRows($pdo, $chatId);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function observeWait(PDO $pdo, Channel $channel, int $connectionId, int $chatId, bool $retention): void
{
    $sql = $retention ? RETENTION_LOCK : REPLY_LOCK;
    $expected = [];
    foreach ([(string)$chatId, "'" . $chatId . "'"] as $literal) {
        $expanded = preg_replace('/\?/', $literal, $sql, 1);
        $expected[] = str_replace('?', "'" . CUTOFF . "'", $expanded);
    }
    $inspection = $pdo->prepare('SELECT INFO FROM information_schema.PROCESSLIST WHERE ID = ?');
    $deadline = microtime(true) + 5;
    $waitingSince = null;
    while (microtime(true) < $deadline) {
        $channel->assertQuiet();
        check($pdo->inTransaction(), 'Parent lost its owning transaction while observing the wait.');
        $inspection->execute([$connectionId]);
        $actual = normalizeSql((string)$inspection->fetchColumn());
        if (in_array($actual, $expected, true)) {
            $waitingSince ??= microtime(true);
            if (microtime(true) - $waitingSince >= 0.25) {
                return;
            }
        } else {
            $waitingSince = null;
        }
        usleep(20000);
    }
    throw new RuntimeException('Did not observe stable exact parent FOR UPDATE SQL for this worker/fixture.');
}

function race(PDO $pdo, string $scenario): void
{
    $prefix = 'rc-chat-mysql-' . bin2hex(random_bytes(12));
    $chatId = 0;
    $listener = $stream = $process = $workerLog = null;
    $failure = null;
    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO cms_chat (name, email, message, conversation_type, public_visibility, status, created_at, updated_at)
            VALUES (?, ?, ?, 'public', 'approved', 'handled', ?, ?)")->execute([
                $prefix, $prefix . '@example.invalid', $prefix . ':parent', OLD_TIME, OLD_TIME,
            ]);
        $chatId = (int)$pdo->lastInsertId();
        assertOwner($pdo, $chatId, $prefix, true);
        if ($scenario !== 'hidden-support') {
            $pdo->prepare("INSERT INTO cms_chat_replies (chat_id, name, email, message, status)
                VALUES (?, ?, ?, ?, 'pending')")->execute([$chatId, $prefix, $prefix . '@example.invalid', $prefix . ':seed-reply']);
            $pdo->prepare("INSERT INTO cms_chat_history (chat_id, actor_user_id, event_type, message)
                VALUES (?, NULL, 'selftest', ?)")->execute([$chatId, $prefix . ':seed-history']);
        }
        $pdo->commit();
        $beforeReplies = childRows($pdo, $chatId, 'cms_chat_replies');
        $beforeHistory = childRows($pdo, $chatId, 'cms_chat_history');
        check(count($beforeReplies) === ($scenario === 'hidden-support' ? 0 : 1)
            && count($beforeHistory) === count($beforeReplies), 'Unexpected initial child fixture counts.');
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorText);
        check(is_resource($listener), 'Cannot open loopback listener.');
        $address = stream_socket_get_name($listener, false);
        check(is_string($address), 'Missing loopback listener address.');
        $token = bin2hex(random_bytes(16));
        $workerLog = tmpfile();
        check(is_resource($workerLog), 'Cannot open worker diagnostics.');
        $pdo->beginTransaction();
        assertOwner($pdo, $chatId, $prefix, true);
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', (string)$chatId, $prefix, $address, $scenario, $token], [
            0 => ['pipe', 'r'], 1 => $workerLog, 2 => $workerLog,
        ], $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
        check(is_resource($process), 'Cannot launch independent PHP worker.');
        fclose($pipes[0]);
        $stream = stream_socket_accept($listener, 6);
        $channel = new Channel($stream, $token);
        $ready = $channel->receive();
        $workerId = (int)($ready['connection_id'] ?? 0);
        check(($ready['type'] ?? '') === 'ready' && $workerId > 0
            && $workerId !== (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn(), 'Worker needs an independent MySQL connection.');
        $old = $ready['snapshot'] ?? [];
        check(($old['name'] ?? '') === $prefix && ($old['message'] ?? '') === $prefix . ':parent'
            && ($old['conversation_type'] ?? '') === 'public' && ($old['public_visibility'] ?? '') === 'approved'
            && ($old['status'] ?? '') === 'handled' && ($old['updated_at'] ?? '') === OLD_TIME, 'Worker must acknowledge its old eligible snapshot.');
        $channel->send(['type' => 'run']);
        observeWait($pdo, $channel, $workerId, $chatId, $scenario === 'retention');
        if ($scenario === 'hidden-support') {
            $pdo->prepare("UPDATE cms_chat SET conversation_type = 'support', public_visibility = 'hidden', updated_at = ? WHERE id = ? AND name = ?")
                ->execute([NEW_TIME, $chatId, $prefix]);
        } elseif ($scenario === 'delete') {
            $helperPdo = new FixturePdo($pdo, $chatId, $prefix);
            check(deleteChatMessage($helperPdo, $chatId), 'Parent must delete using the actual production helper.');
            check(
                $pdo->inTransaction() && $helperPdo->commits === 0
                && $helperPdo->trace === ['delete-lock', 'delete-replies', 'delete-history', 'delete-parent'],
                'Parent-first helper deletion must stay in the caller-owned transaction.'
            );
        } else {
            $pdo->prepare('UPDATE cms_chat SET updated_at = ? WHERE id = ? AND name = ?')->execute([NEW_TIME, $chatId, $prefix]);
        }
        $channel->assertQuiet();
        $pdo->commit();
        $result = $channel->receive();
        check(($result['type'] ?? '') === 'result' && ($result['accepted'] ?? null) === false
            && ($result['snapshot_after_lock'] ?? null) === $old && ($result['lock_reads'] ?? 0) === 1
            && ($result['reused_begins'] ?? -1) === ($scenario === 'retention' ? 0 : 1)
            && (int)($result['wait_ms'] ?? 0) >= 250, 'Worker must reject current state despite its demonstrably old snapshot.');
        if ($scenario === 'delete') {
            assertNoRows($pdo, $chatId);
        } else {
            assertOwner($pdo, $chatId, $prefix);
            $state = $pdo->prepare(SNAPSHOT_SQL);
            $state->execute([$chatId, $prefix, $prefix . ':parent']);
            $current = $state->fetch();
            check(is_array($current) && $current['updated_at'] === NEW_TIME && $current['status'] === 'handled'
                && $current['conversation_type'] === ($scenario === 'retention' ? 'public' : 'support')
                && $current['public_visibility'] === ($scenario === 'retention' ? 'approved' : 'hidden'), 'Committed current parent must be preserved.');
            check(childRows($pdo, $chatId, 'cms_chat_replies') === $beforeReplies
                && childRows($pdo, $chatId, 'cms_chat_history') === $beforeHistory, 'Rejected worker must leave all child/history data unchanged.');
        }
        $deadline = microtime(true) + 3;
        do {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        check(!$status['running'] && $status['exitcode'] === 0, 'Worker must exit successfully.');
    } catch (Throwable $exception) {
        $failure = $exception->getMessage();
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_resource($process)) {
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process);
            }
            proc_close($process);
        }
        foreach ([$stream, $listener] as $socket) {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
        if ($chatId > 0) {
            try {
                cleanup($pdo, $chatId, $prefix);
            } catch (Throwable $exception) {
                $failure = ($failure !== null ? $failure . '; ' : '') . 'Cleanup: ' . $exception->getMessage();
            }
        }
        if (is_resource($workerLog)) {
            if ($failure !== null) {
                rewind($workerLog);
                $failure .= "\nWorker: " . stream_get_contents($workerLog, 8192);
            }
            fclose($workerLog);
        }
    }
    if ($failure !== null) {
        throw new RuntimeException($scenario . ': ' . $failure);
    }
    echo 'PASS: ' . $scenario . "; stable exact parent FOR UPDATE wait; old snapshot retained; rejected without writes; fixtures cleaned.\n";
}

// Contract double only: deliberately has no driver connection or database state.
final class OfflinePdo extends PDO
{
    public bool $active = false;
    public bool $eligible = true;
    public string $failRole = '';
    public array $row;

    public function __construct(string $prefix)
    {
        $this->row = ['name' => $prefix, 'message' => $prefix . ':parent', 'conversation_type' => 'public',
            'public_visibility' => 'approved', 'status' => 'handled', 'updated_at' => OLD_TIME];
    }

    public function getAttribute(int $attribute): mixed
    {
        return 'mysql';
    }
    public function inTransaction(): bool
    {
        return $this->active;
    }
    public function beginTransaction(): bool
    {
        check(!$this->active, 'Offline transaction must not restart.');
        $this->active = true;
        return true;
    }
    public function commit(): bool
    {
        check($this->active, 'Offline commit requires transaction.');
        $this->active = false;
        return true;
    }
    public function rollBack(): bool
    {
        return $this->commit();
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new OfflineStatement($this, normalizeSql($query));
    }
}

final class OfflineStatement extends PDOStatement
{
    private OfflinePdo $owner;
    private string $sql;
    public function __construct(OfflinePdo $owner, string $sql)
    {
        $this->owner = $owner;
        $this->sql = $sql;
    }
    public function execute(?array $params = null): bool
    {
        if ($this->sql !== OWNER_SQL && $this->sql !== OWNER_SQL . ' FOR UPDATE') {
            $role = FixturePdo::role($this->sql);
            if ($role === $this->owner->failRole) {
                throw new RuntimeException('Injected offline failure: ' . $role);
            }
        }
        return true;
    }
    public function fetchColumn(int $column = 0): mixed
    {
        return $this->owner->eligible ? '7' : false;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->owner->row;
    }
    public function rowCount(): int
    {
        return 1;
    }
}

function expectRejected(callable $operation): void
{
    $rejected = false;
    try {
        $operation();
    } catch (RuntimeException $exception) {
        $rejected = true;
    }
    check($rejected, 'Unsafe/changed contract must fail closed.');
}

function offlineChecks(): void
{
    $prefix = 'rc-chat-mysql-' . str_repeat('a', 24);
    check(!function_exists('chatCreatePublicReply') && !function_exists('deleteChatMessage')
        && !function_exists('db_connect') && !function_exists('sendMail'), 'No application/global helper bootstrap is allowed.');
    foreach (['reply', 'reply-rejected', 'reply-failed', 'delete', 'delete-rejected', 'retention', 'retention-rejected', 'caller-delete', 'snapshot-reply'] as $mode) {
        $connection = new OfflinePdo($prefix);
        $connection->eligible = !str_ends_with($mode, '-rejected');
        $connection->failRole = $mode === 'reply-failed' ? 'history' : '';
        $pdo = new FixturePdo($connection, 7, $prefix);
        $isReply = str_contains($mode, 'reply');
        if ($mode === 'caller-delete') {
            $connection->beginTransaction();
        } elseif ($mode === 'snapshot-reply') {
            $pdo->startSnapshot(true);
            $connection->eligible = false;
        }
        $failed = false;
        $accepted = null;
        try {
            $accepted = $isReply
                ? chatCreatePublicReply($pdo, 7, $prefix, $prefix . '@example.invalid', $prefix . ':worker-reply')
                : deleteChatMessage($pdo, 7, str_starts_with($mode, 'retention') ? CUTOFF : null);
        } catch (RuntimeException $exception) {
            check($exception->getMessage() === 'Injected offline failure: history', 'Unexpected helper contract exception.');
            $failed = true;
        }
        $lockRole = $isReply ? 'reply-lock' : (str_starts_with($mode, 'retention') ? 'retention-lock' : 'delete-lock');
        $expected = [$lockRole];
        if ($mode === 'snapshot-reply') {
            $expected = ['snapshot', 'reply-lock', 'snapshot'];
        } elseif ($mode === 'reply-failed') {
            $expected = ['reply-lock', 'reply', 'history'];
        } elseif ($connection->eligible) {
            $expected = $isReply ? ['reply-lock', 'reply', 'history', 'touch']
                : [$lockRole, 'delete-replies', 'delete-history', 'delete-parent'];
        }
        check($pdo->trace === $expected && $pdo->lockReads === 1, 'Wrong production SQL order/lock contract: ' . $mode);
        check($failed === ($mode === 'reply-failed') && $accepted === ($failed ? null : $connection->eligible), 'Wrong helper result: ' . $mode);
        check($pdo->rollbacks === ($failed ? 1 : 0) && $pdo->commits === (($failed || $mode === 'caller-delete') ? 0 : 1), 'Wrong commit/rollback ownership: ' . $mode);
        check($pdo->inTransaction() === ($mode === 'caller-delete') && $pdo->reusedBegins === ($mode === 'snapshot-reply' ? 1 : 0), 'Wrong transaction/snapshot contract: ' . $mode);
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    $pdo = new FixturePdo(new OfflinePdo($prefix), 7, $prefix);
    foreach (['SELECT * FROM cms_chat', 'DELETE FROM cms_chat', DELETE_LOCK . ' OR 1=1', REPLY_LOCK . '; DELETE FROM cms_chat',
        'UPDATE cms_settings SET value = ?', 'INSERT INTO cms_mail_queue (message) VALUES (?)',
        'SELECT id FROM cms_chat WHERE id = ?', "SELECT id FROM cms_chat WHERE id = ? AND status = 'handled' FOR UPDATE"] as $sql) {
        expectRejected(static function () use ($pdo, $sql): void {
            $pdo->prepare($sql);
        });
    }
    $pdo->beginTransaction();
    foreach ([[8], ['7'], [7, 8], [], null, [':id' => 7]] as $params) {
        expectRejected(static function () use ($pdo, $params): void {
            $pdo->prepare(DELETE_LOCK)->execute($params);
        });
    }
    foreach ([
        ['snapshot', [7, 'foreign', $prefix . ':parent']], ['retention-lock', [7, NEW_TIME]],
        ['reply', [7, 'foreign', 'foreign@example.invalid', 'foreign']], ['history', [8, null, 'reply_submitted', 'foreign']],
        ['history', [7, 1, 'reply_submitted', 'foreign']], ['history', [7, null, 'reply', 'foreign']],
        ['touch', [8]], ['delete-replies', [8]], ['delete-history', [8]], ['delete-parent', [8]],
    ] as [$role, $params]) {
        expectRejected(static function () use ($pdo, $role, $params): void {
            $pdo->validate($role, $params);
        });
    }
    expectRejected(static function () use ($pdo): void {
        $pdo->query('SELECT CONNECTION_ID()');
    });
    expectRejected(static function () use ($pdo): void {
        $pdo->exec('DELETE FROM cms_chat');
    });
    expectRejected(static function () use ($pdo): void {
        $pdo->prepare(DELETE_LOCK)->bindValue(1, 7);
    });
    expectRejected(static function () use ($pdo): void {
        $pdo->validate('touch', [7]);
    });
    expectRejected(static function () use ($prefix): void {
        new FixturePdo(new OfflinePdo('foreign'), 7, $prefix);
    });
    expectRejected(static function () use ($prefix): void {
        new FixturePdo(new OfflinePdo($prefix), 0, $prefix);
    });
    $pdo->rollBack();
}

try {
    check(count($argv) === 1 || (count($argv) === 2 && $argv[1] === '--offline')
        || ($argv[1] ?? '') === '--worker', 'Usage: php build/rc_chat_mysql_selftest.php [--offline]');
    loadHelpers();
    if (($argv[1] ?? '') === '--worker') {
        exit(worker($argv));
    }
    offlineChecks();
    if (($argv[1] ?? '') === '--offline') {
        echo 'OK: ' . $checks . " offline chat contract checks; helper extraction/SQL scope/transaction order; no DB/SMTP; no concurrency claim.\n";
        exit(0);
    }
    $pdo = connect();
    $tables = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('cms_chat', 'cms_chat_replies', 'cms_chat_history')")->fetchAll();
    check(count($tables) === 3, 'All three installed chat fixture tables are required; no skip/baseline.');
    foreach ($tables as $table) {
        check(strcasecmp((string)$table['ENGINE'], 'InnoDB') === 0, $table['TABLE_NAME'] . ' must use InnoDB.');
    }
    foreach (['hidden-support', 'delete', 'retention'] as $scenario) {
        race($pdo, $scenario);
    }
    echo 'OK: ' . $checks . " chat MySQL checks; three real parent-lock races; no rejected reply/history/orphan; refreshed retention preserved; fixtures cleaned; no application mail.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . "\n");
    exit(1);
}
