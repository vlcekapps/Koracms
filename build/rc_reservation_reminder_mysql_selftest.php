<?php

declare(strict_types=1);

namespace RcReservationReminderMysqlSelftest;

use DateTimeImmutable;
use DateTimeInterface;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Run: php build/rc_reservation_reminder_mysql_selftest.php [--offline]
 *
 * This is a real, multi-process MySQL/InnoDB test, not an SMTP integration test.
 * Only config.php is required; neither the application nor cron is bootstrapped.
 * Token-extracted production functions run unchanged in this private namespace:
 * cronProcessReservationReminders, cronMissingColumns, the notification booking
 * loader, due predicate, subject/body and booking-event writer/labels.
 * Mocks: isModuleEnabled enables reservations; reservationSendMail captures calls
 * and returns true/false, optionally waiting on a TCP process barrier. No SMTP,
 * sendMail, mail(), application logging or notifications are loaded or called.
 * Empty fixture confirmation tokens avoid the body helper's siteUrl dependency.
 *
 * The worker PDO adapter adds ONLY fixed, ownership-validated resource/booking
 * IDs and a random resource slug to the candidate SELECT, before ORDER BY/LIMIT.
 * Other SQL, FOR UPDATE, transactions, marker writes and event inserts are real.
 * A second barrier after the first worker's actual marker UPDATE proves that its
 * locks also survive the SMTP return and remain held until the marker commits.
 * An extra nonlocking read immediately after production's resource-routing read
 * records its already established REPEATABLE READ snapshot; it changes no locks.
 * Unknown worker SQL and out-of-scope statement parameters fail closed.
 * Parent cancellation/due changes are fixture SQL, not mocked cancellation code.
 * UTC and lock timeouts affect these connections only; no DDL/global lock/settings.
 * Cleanup deletes only owned booking IDs/events and the validated resource ID.
 * --offline checks extraction/scoping without opening a database connection.
 */
require_once dirname(__DIR__) . '/config.php';

date_default_timezone_set('UTC');
$checks = 0;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $GLOBALS['checks']++;
}

/** @param list<string> $names @return array<string,string> */
function loadFunctions(string $relativePath, array $names): array
{
    $source = file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    check(is_string($source), 'Cannot read production source: ' . $relativePath);
    $tokens = token_get_all($source);
    $functions = [];
    foreach ($tokens as $index => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }
        $nameIndex = $index + 1;
        while (isset($tokens[$nameIndex]) && is_array($tokens[$nameIndex])
            && in_array($tokens[$nameIndex][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $nameIndex++;
        }
        $name = $tokens[$nameIndex] ?? null;
        if (!is_array($name) || $name[0] !== T_STRING || !in_array($name[1], $names, true)) {
            continue;
        }
        $code = '';
        $depth = 0;
        $started = false;
        for ($cursor = $index, $count = count($tokens); $cursor < $count; $cursor++) {
            $part = $tokens[$cursor];
            $code .= is_array($part) ? $part[1] : $part;
            if ($part === '{' || (is_array($part)
                && in_array($part[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
                $started = true;
            } elseif ($part === '}' && --$depth === 0 && $started) {
                check(!isset($functions[$name[1]]), 'Duplicate extracted function: ' . $name[1]);
                $functions[$name[1]] = $code;
                break;
            }
        }
    }
    $loaded = array_keys($functions);
    sort($loaded);
    sort($names);
    check($loaded === $names, 'Missing/incomplete production functions in ' . $relativePath);
    eval('namespace ' . __NAMESPACE__ . ';'
        . 'use \\PDO; use \\DateTimeImmutable; use \\DateTimeInterface;'
        . implode("\n", $functions));
    return $functions;
}

function isModuleEnabled(string $module): bool
{
    check($module === 'reservations', 'Unexpected module requested.');
    return true;
}

/** @return array{0:string,1:string,2:string,3:array<int,mixed>} */
function connectionArguments(): array
{
    $dbHost = (string)$GLOBALS['server'];
    $dbName = (string)$GLOBALS['database'];
    return ["mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        (string)$GLOBALS['user'], (string)$GLOBALS['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]];
}

function configureConnection(PDO $pdo): void
{
    check($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql', 'Real MySQL PDO is required.');
    $pdo->exec("SET SESSION time_zone = '+00:00'");
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 12');
    $pdo->exec('SET SESSION lock_wait_timeout = 12');
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
}

function connect(): PDO
{
    $pdo = new PDO(...connectionArguments());
    configureConnection($pdo);
    return $pdo;
}

function validScope(int $resourceId, int $bookingId, string $slug): bool
{
    return $resourceId > 0 && $bookingId > 0
        && preg_match('/^rc-res-reminder-[a-f0-9]{24}$/D', $slug) === 1;
}

function assertOwner(PDO $pdo, int $resourceId, int $bookingId, string $slug): void
{
    check(validScope($resourceId, $bookingId, $slug), 'Invalid fixture scope.');
    $statement = $pdo->prepare('SELECT r.slug, b.guest_name FROM cms_res_resources r
        JOIN cms_res_bookings b ON b.resource_id = r.id WHERE r.id = ? AND b.id = ?');
    $statement->execute([$resourceId, $bookingId]);
    $row = $statement->fetch();
    check(
        is_array($row) && $row['slug'] === $slug && $row['guest_name'] === $slug,
        'Worker refused a resource/booking not owned by this test.'
    );
}

final class Channel
{
    /** @var resource */
    public $stream;
    private string $buffer = '';
    private string $token;

    /** @param resource $stream */
    public function __construct($stream, string $token)
    {
        check(is_resource($stream), 'Missing TCP process barrier.');
        $this->stream = $stream;
        $this->token = $token;
        stream_set_blocking($stream, false);
    }

    /** @param array<string,mixed> $message */
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
            check($written !== false && $written > 0, 'Worker barrier disconnected during write.');
            $offset += $written;
        }
        check($offset === strlen($line), 'Incomplete worker barrier message.');
    }

    /** @return array<string,mixed> */
    public function receive(float $seconds = 6): array
    {
        $deadline = microtime(true) + $seconds;
        do {
            $newline = strpos($this->buffer, "\n");
            if ($newline !== false) {
                $line = substr($this->buffer, 0, $newline);
                $this->buffer = substr($this->buffer, $newline + 1);
                $message = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
                check(
                    is_array($message) && ($message['token'] ?? '') === $this->token,
                    'Invalid or unauthenticated worker barrier message.'
                );
                return $message;
            }
            $read = [$this->stream];
            $write = $except = null;
            $readable = stream_select($read, $write, $except, 0, 100000);
            check($readable !== false, 'Worker barrier polling failed.');
            if ($readable > 0) {
                $chunk = fread($this->stream, 4096);
                check(
                    $chunk !== false && !($chunk === '' && feof($this->stream)),
                    'Worker disconnected before its expected message.'
                );
                $this->buffer .= $chunk;
                check(strlen($this->buffer) <= 65536, 'Oversized worker barrier message.');
            }
        } while (microtime(true) < $deadline);
        throw new RuntimeException('Timed out waiting for worker barrier message.');
    }

    public function assertQuiet(): void
    {
        $read = [$this->stream];
        $write = $except = null;
        check(
            $this->buffer === '' && stream_select($read, $write, $except, 0, 0) === 0,
            'Blocked worker sent mail, returned or disconnected before lock release.'
        );
    }
}

function normalizeSql(string $sql): string
{
    return preg_replace('/\s+/', ' ', trim($sql)) ?? '';
}

final class FixturePdo extends PDO
{
    public int $resourceId;
    public int $bookingId;
    public int $connectionId;
    public string $slug;
    public string $mode;
    public Channel $channel;
    public array $mail = [];
    public array $snapshots = [];
    public int $candidateSelections = 0;
    public int $resourceLockReads = 0;
    public int $bookingLockReads = 0;
    private bool $armed = false;

    public function __construct(int $resourceId, int $bookingId, string $slug, string $mode, Channel $channel)
    {
        parent::__construct(...connectionArguments());
        configureConnection($this);
        assertOwner($this, $resourceId, $bookingId, $slug);
        $this->connectionId = (int)parent::query('SELECT CONNECTION_ID()')->fetchColumn();
        $this->resourceId = $resourceId;
        $this->bookingId = $bookingId;
        $this->slug = $slug;
        $this->mode = $mode;
        $this->channel = $channel;
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [FixtureStatement::class, [$this]]);
        $this->armed = true;
    }

    public static function scopedCandidateSql(string $sql, int $resourceId, int $bookingId, string $slug): string
    {
        check(validScope($resourceId, $bookingId, $slug), 'Candidate filter needs a fixed valid scope.');
        $normalized = normalizeSql($sql);
        check(
            preg_match('/^(SELECT b\.id FROM cms_res_bookings b JOIN cms_res_resources r ON r\.id = b\.resource_id WHERE) (.+) (ORDER BY b\.booking_date, b\.start_time, b\.id LIMIT 100)$/i', $normalized, $parts) === 1
            && !str_contains($normalized, ';'),
            'Candidate SELECT shape changed; refusing any unscoped cron execution.'
        );
        // Wrap the entire production predicate so a future OR cannot escape scope.
        return $parts[1] . " b.resource_id = {$resourceId} AND b.id = {$bookingId} AND r.slug = '{$slug}' AND ("
            . $parts[2] . ') ' . $parts[3];
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (!$this->armed) {
            return parent::prepare($query, $options);
        }
        $sql = normalizeSql($query);
        $role = '';
        if (preg_match('/^SELECT b\.id\b/i', $sql) === 1) {
            $query = self::scopedCandidateSql($query, $this->resourceId, $this->bookingId, $this->slug);
            $role = 'candidates';
            $this->candidateSelections++;
        } elseif ($sql === 'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?') {
            $role = 'schema';
        } elseif ($sql === 'SELECT resource_id FROM cms_res_bookings WHERE id = ?') {
            $role = 'routing';
        } elseif (preg_match('/^SELECT id FROM cms_res_resources WHERE id = \?(?: FOR UPDATE)?$/', $sql) === 1) {
            $role = 'resource';
        } elseif (str_starts_with($sql, 'SELECT b.*, r.name AS resource_name,')
            && preg_match('/ WHERE b\.id = \?(?: FOR UPDATE)?$/', $sql) === 1) {
            $role = 'booking';
        } elseif ($sql === "UPDATE cms_res_bookings SET reminder_sent_at = NOW(), reminder_last_error = '', updated_at = NOW() WHERE id = ?") {
            $role = 'sent';
        } elseif ($sql === 'UPDATE cms_res_bookings SET reminder_last_error = ?, updated_at = NOW() WHERE id = ?') {
            $role = 'failed';
        } elseif ($sql === 'INSERT INTO cms_res_booking_events (booking_id, event_type, description, actor_user_id, metadata_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())') {
            $role = 'event';
        }
        check($role !== '', 'Unknown worker SQL refused: ' . $sql);
        $statement = parent::prepare($query, $options);
        check($statement instanceof FixtureStatement, 'Missing scoped PDO statement adapter.');
        $statement->role = $role;
        return $statement;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        throw new RuntimeException('Unscoped worker query() is forbidden; use audited prepare().');
    }

    public function exec(string $statement): int|false
    {
        check(!$this->armed, 'Unscoped worker exec() is forbidden; use audited prepare().');
        return parent::exec($statement);
    }

    /** @param list<mixed> $ids */
    public function candidatesObserved(array $ids): void
    {
        $ids = array_map('intval', $ids);
        check($ids === [] || $ids === [$this->bookingId], 'Cron selected an unowned candidate.');
        $this->channel->send(['type' => 'candidates', 'ids' => $ids]);
    }

    public function routingObserved(): void
    {
        check($this->inTransaction(), 'Production routing must run inside its candidate transaction.');
        $statement = parent::prepare('SELECT status, reminder_sent_at, reminder_last_error
            FROM cms_res_bookings WHERE id = ? AND resource_id = ?');
        check($statement instanceof FixtureStatement, 'Missing snapshot statement adapter.');
        $statement->role = 'snapshot';
        $statement->execute([$this->bookingId, $this->resourceId]);
        $snapshot = $statement->fetch();
        check(is_array($snapshot), 'Fixture missing from the routing snapshot.');
        $this->snapshots[] = $snapshot;
        $this->channel->send(['type' => 'snapshot', 'row' => $snapshot, 'in_transaction' => true]);
    }

    public function markerObserved(string $role): void
    {
        if (!str_starts_with($this->mode, 'hold-')) {
            return;
        }
        check($this->inTransaction(), 'The actual marker UPDATE must still hold the candidate transaction.');
        $this->channel->send(['type' => 'marker', 'role' => $role, 'in_transaction' => true]);
        $release = $this->channel->receive(12);
        check(($release['type'] ?? '') === 'release_marker', 'Invalid marker barrier release.');
    }
}

final class FixtureStatement extends PDOStatement
{
    public string $role = '';
    private FixturePdo $owner;

    protected function __construct(FixturePdo $owner)
    {
        $this->owner = $owner;
    }

    public function execute(?array $params = null): bool
    {
        $values = $params === null ? [] : array_values($params);
        $role = $this->role;
        $valid = false;
        if ($role === 'candidates') {
            $valid = $values === [];
        } elseif ($role === 'schema') {
            $valid = count($values) === 1 && in_array(
                $values[0],
                ['cms_res_bookings', 'cms_res_resources', 'cms_res_booking_events'],
                true
            );
        } elseif ($role === 'resource') {
            $valid = count($values) === 1 && (int)$values[0] === $this->owner->resourceId;
        } elseif (in_array($role, ['routing', 'booking', 'sent'], true)) {
            $valid = count($values) === 1 && (int)$values[0] === $this->owner->bookingId;
        } elseif ($role === 'failed') {
            $valid = count($values) === 2 && (int)$values[1] === $this->owner->bookingId;
        } elseif ($role === 'event') {
            $valid = count($values) === 5 && (int)$values[0] === $this->owner->bookingId
                && in_array($values[1], ['reminder_sent', 'reminder_failed'], true);
        } elseif ($role === 'snapshot') {
            $valid = $values === [$this->owner->bookingId, $this->owner->resourceId];
        }
        check($valid, 'Unowned or unsupported worker statement parameters: ' . $role);
        $result = parent::execute($params);
        if ($role === 'routing') {
            $this->owner->routingObserved();
        }
        if (in_array($role, ['sent', 'failed'], true)) {
            $this->owner->markerObserved($role);
        }
        if (str_ends_with(normalizeSql($this->queryString), ' FOR UPDATE')) {
            if ($role === 'resource') {
                $this->owner->resourceLockReads++;
            } elseif ($role === 'booking') {
                $this->owner->bookingLockReads++;
            }
        }
        return $result;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $rows = parent::fetchAll($mode, ...$args);
        if ($this->role === 'candidates') {
            check($mode === PDO::FETCH_COLUMN, 'Unexpected candidate fetch mode.');
            $this->owner->candidatesObserved($rows);
        }
        return $rows;
    }
}

/** @param array<string,mixed> $booking */
function reservationSendMail(array $booking, string $subject, string $body, string $notification, bool $includeCalendar = true): bool
{
    $pdo = $GLOBALS['rcReminderWorkerPdo'] ?? null;
    check(
        $pdo instanceof FixturePdo && (int)($booking['id'] ?? 0) === $pdo->bookingId
        && (int)($booking['resource_id'] ?? 0) === $pdo->resourceId
        && ($booking['resource_slug'] ?? '') === $pdo->slug && ($booking['guest_name'] ?? '') === $pdo->slug,
        'Mail capture refused an unowned booking.'
    );
    check($pdo->inTransaction(), 'SMTP capture must occur while production holds its transaction.');
    check(
        $notification === 'reservation_reminder' && $includeCalendar && $subject !== '' && $body !== '',
        'Unexpected mail call contract.'
    );
    $success = !in_array($pdo->mode, ['hold-failure', 'failure'], true);
    $mail = ['booking_id' => $pdo->bookingId, 'success' => $success, 'in_transaction' => true,
        'notification' => $notification, 'subject' => $subject, 'body' => $body];
    $pdo->mail[] = $mail;
    $pdo->channel->send(['type' => 'mail', 'mail' => $mail]);
    if (str_starts_with($pdo->mode, 'hold-')) {
        $release = $pdo->channel->receive(12);
        check(($release['type'] ?? '') === 'release_mail', 'Invalid SMTP barrier release.');
    }
    return $success;
}

/** @param list<string> $arguments */
function worker(array $arguments): int
{
    $pdo = null;
    $channel = null;
    try {
        check(count($arguments) === 8 && ctype_digit($arguments[2]) && ctype_digit($arguments[3])
            && (string)(int)$arguments[2] === $arguments[2] && (string)(int)$arguments[3] === $arguments[3]
            && validScope((int)$arguments[2], (int)$arguments[3], $arguments[4])
            && in_array($arguments[5], ['hold-success', 'success', 'hold-failure', 'failure'], true)
            && preg_match('/^127\.0\.0\.1:[0-9]+$/D', $arguments[6]) === 1
            && preg_match('/^[a-f0-9]{32}$/D', $arguments[7]) === 1, 'Invalid isolated worker arguments.');
        $stream = stream_socket_client('tcp://' . $arguments[6], $errorCode, $errorText, 5);
        $channel = new Channel($stream, $arguments[7]);
        $pdo = new FixturePdo((int)$arguments[2], (int)$arguments[3], $arguments[4], $arguments[5], $channel);
        $GLOBALS['rcReminderWorkerPdo'] = $pdo;
        $channel->send(['type' => 'ready', 'slug' => $pdo->slug, 'booking_id' => $pdo->bookingId,
            'mode' => $pdo->mode, 'connection_id' => $pdo->connectionId]);
        $started = microtime(true);
        $result = cronProcessReservationReminders($pdo);
        check(!$pdo->inTransaction(), 'Cron left a candidate transaction open.');
        check($pdo->candidateSelections === 1, 'Cron must execute one isolated candidate SELECT.');
        $channel->send(['type' => 'result', 'result' => $result, 'mail' => $pdo->mail,
            'resource_lock_reads' => $pdo->resourceLockReads, 'booking_lock_reads' => $pdo->bookingLockReads,
            'elapsed_ms' => (int)round((microtime(true) - $started) * 1000)]);
        return 0;
    } catch (Throwable $exception) {
        if ($channel instanceof Channel) {
            try {
                $channel->send(['type' => 'failure', 'message' => $exception->getMessage()]);
            } catch (Throwable $ignored) {
            }
        }
        fwrite(STDERR, $exception->getMessage() . "\n");
        return 1;
    } finally {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($channel instanceof Channel && is_resource($channel->stream)) {
            fclose($channel->stream);
        }
    }
}

/** @param array<string,mixed> $worker @return array<string,mixed> */
function expect(array $worker, string $type): array
{
    $message = $worker['channel']->receive();
    check(($message['type'] ?? '') === $type, 'Expected ' . $type . ': ' . json_encode($message));
    return $message;
}

/** @param array<int,array<string,mixed>> $workers */
function startWorker(array &$workers, int $resourceId, int $bookingId, string $slug, string $mode): int
{
    $index = count($workers);
    $workers[$index] = ['process' => null, 'listener' => null, 'channel' => null, 'log' => null, 'exitcode' => null];
    $entry = & $workers[$index];
    $entry['listener'] = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorText);
    check(is_resource($entry['listener']), 'Cannot open loopback listener.');
    $address = stream_socket_get_name($entry['listener'], false);
    check(is_string($address), 'Cannot determine loopback address.');
    $token = bin2hex(random_bytes(16));
    $entry['log'] = tmpfile();
    check(is_resource($entry['log']), 'Cannot open worker diagnostic stream.');
    $entry['process'] = proc_open(
        [PHP_BINARY, __FILE__, '--worker', (string)$resourceId, (string)$bookingId,
        $slug, $mode, $address, $token],
        [0 => ['pipe', 'r'], 1 => $entry['log'], 2 => $entry['log']],
        $pipes,
        dirname(__DIR__),
        null,
        ['bypass_shell' => true]
    );
    check(is_resource($entry['process']), 'Cannot start independent PHP cron worker.');
    fclose($pipes[0]);
    $stream = stream_socket_accept($entry['listener'], 6);
    $entry['channel'] = new Channel($stream, $token);
    $ready = expect($entry, 'ready');
    check(
        ($ready['slug'] ?? '') === $slug && ($ready['booking_id'] ?? 0) === $bookingId
        && ($ready['mode'] ?? '') === $mode && (int)($ready['connection_id'] ?? 0) > 0,
        'Worker did not validate and acknowledge its own fixture.'
    );
    $entry['connection_id'] = (int)$ready['connection_id'];
    return $index;
}

/** @param array<string,mixed> $worker */
function expectCandidateSnapshot(array $worker, int $bookingId): void
{
    check((expect($worker, 'candidates')['ids'] ?? null) === [$bookingId], 'Both workers must select the same due booking.');
    $snapshot = expect($worker, 'snapshot');
    $row = $snapshot['row'] ?? [];
    check(
        ($snapshot['in_transaction'] ?? false) && ($row['status'] ?? '') === 'confirmed'
        && array_key_exists('reminder_sent_at', $row) && $row['reminder_sent_at'] === null
        && ($row['reminder_last_error'] ?? null) === '',
        'Worker must establish an old confirmed/unsent/error-free REPEATABLE READ snapshot before locking.'
    );
}

/** @param array<string,mixed> $worker */
function observeResourceWait(PDO $pdo, array $worker, int $blockerId): string
{
    check($worker['connection_id'] !== $blockerId, 'Lock waiter and holder must be separate MySQL connections.');
    // PROCESSLIST and INNODB_TRX are not one snapshot; observe the known blocked SQL only.
    $inspection = $pdo->prepare('SELECT INFO FROM information_schema.PROCESSLIST WHERE ID = ?');
    $deadline = microtime(true) + 6;
    $waitingSince = null;
    $lastEvidence = [];
    do {
        $worker['channel']->assertQuiet();
        $inspection->execute([$worker['connection_id']]);
        $sql = normalizeSql((string)$inspection->fetchColumn());
        $waiting = str_contains($sql, 'cms_res_resources') && str_ends_with(strtoupper($sql), ' FOR UPDATE');
        $lastEvidence = ['sql' => $sql];
        if ($waiting) {
            $waitingSince = $waitingSince ?? microtime(true);
            if (microtime(true) - $waitingSince >= 0.25) {
                $worker['channel']->assertQuiet();
                return 'stable PROCESSLIST resource FOR UPDATE';
            }
        } else {
            $waitingSince = null;
        }
        usleep(20000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('No observed InnoDB/resource FOR UPDATE wait before releasing the holder: '
        . json_encode($lastEvidence, JSON_THROW_ON_ERROR));
}

/** @param array<string,mixed> $worker @param array{sent:int,failed:int} $expected */
function expectResult(array $worker, array $expected, int $mailCount, bool $locked): array
{
    $result = expect($worker, 'result');
    check(
        ($result['result'] ?? null) === $expected && count($result['mail'] ?? []) === $mailCount,
        'Unexpected cron counts/mail attempts: ' . json_encode($result)
    );
    if ($locked) {
        check(
            ($result['resource_lock_reads'] ?? 0) === 1 && ($result['booking_lock_reads'] ?? 0) === 1,
            'Production must execute both real resource and notification-booking FOR UPDATE reads.'
        );
    }
    return $result;
}

/** @param array<string,mixed> $worker */
function finishWorker(array &$worker): void
{
    $deadline = microtime(true) + 5;
    do {
        $status = proc_get_status($worker['process']);
        if (!$status['running']) {
            $worker['exitcode'] = $status['exitcode'];
            break;
        }
        usleep(20000);
    } while (microtime(true) < $deadline);
    check(!$status['running'], 'Cron worker did not exit within five seconds.');
    $closed = proc_close($worker['process']);
    $worker['process'] = null;
    check($worker['exitcode'] === 0 || ($worker['exitcode'] === -1 && $closed === 0), 'Cron worker exited unsuccessfully.');
}

function insertBooking(PDO $pdo, int $resourceId, string $slug, array &$bookingIds): int
{
    $owner = $pdo->prepare('SELECT slug FROM cms_res_resources WHERE id = ?');
    $owner->execute([$resourceId]);
    check($owner->fetchColumn() === $slug, 'Cannot insert a fixture on an unowned resource.');
    $start = new DateTimeImmutable((string)$pdo->query('SELECT DATE_ADD(NOW(), INTERVAL 2 HOUR)')->fetchColumn());
    $end = $start->modify('+1 hour');
    $pdo->prepare("INSERT INTO cms_res_bookings
        (resource_id, guest_name, guest_email, booking_date, start_time, end_time, party_size,
         status, confirmation_token, calendar_token, reminder_sent_at, reminder_last_error)
        VALUES (?, ?, 'reminder-selftest@example.test', ?, ?, ?, 1, 'confirmed', '', NULL, NULL, '')")
        ->execute([$resourceId, $slug, $start->format('Y-m-d'), $start->format('H:i:s'), $end->format('H:i:s')]);
    $bookingId = (int)$pdo->lastInsertId();
    $bookingIds[] = $bookingId;
    assertOwner($pdo, $resourceId, $bookingId, $slug);
    return $bookingId;
}

/** @return array<string,mixed> */
function bookingState(PDO $pdo, int $resourceId, int $bookingId, string $slug): array
{
    assertOwner($pdo, $resourceId, $bookingId, $slug);
    $statement = $pdo->prepare('SELECT status, reminder_sent_at, reminder_last_error FROM cms_res_bookings WHERE id = ? AND resource_id = ?');
    $statement->execute([$bookingId, $resourceId]);
    return $statement->fetch();
}

/** @return list<string> */
function events(PDO $pdo, int $bookingId): array
{
    $statement = $pdo->prepare('SELECT event_type FROM cms_res_booking_events WHERE booking_id = ? ORDER BY id');
    $statement->execute([$bookingId]);
    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

/** @param array<int,array<string,mixed>> $workers @param list<string> $waitEvidence */
function concurrentMail(PDO $pdo, array &$workers, int $resourceId, int $bookingId, string $slug, bool $success, array &$waitEvidence): void
{
    $mode = $success ? 'success' : 'failure';
    $first = startWorker($workers, $resourceId, $bookingId, $slug, 'hold-' . $mode);
    expectCandidateSnapshot($workers[$first], $bookingId);
    $mail = expect($workers[$first], 'mail')['mail'] ?? [];
    check(($mail['booking_id'] ?? 0) === $bookingId && ($mail['success'] ?? null) === $success
        && ($mail['in_transaction'] ?? false), 'First worker must hold its transaction inside captured SMTP.');
    $before = bookingState($pdo, $resourceId, $bookingId, $slug);
    check($before['reminder_sent_at'] === null && $before['reminder_last_error'] === ''
        && events($pdo, $bookingId) === [], 'SMTP barrier must precede marker/event writes.');
    $second = startWorker($workers, $resourceId, $bookingId, $slug, $mode);
    expectCandidateSnapshot($workers[$second], $bookingId);
    $waitEvidence[] = observeResourceWait($pdo, $workers[$second], $workers[$first]['connection_id']);
    $workers[$first]['channel']->send(['type' => 'release_mail']);
    $marker = expect($workers[$first], 'marker');
    check(
        ($marker['role'] ?? '') === ($success ? 'sent' : 'failed') && ($marker['in_transaction'] ?? false),
        'The first worker must pause after its real marker UPDATE, before commit.'
    );
    $waitEvidence[] = observeResourceWait($pdo, $workers[$second], $workers[$first]['connection_id']);
    check(
        bookingState($pdo, $resourceId, $bookingId, $slug) === $before && events($pdo, $bookingId) === [],
        'Marker changes must remain uncommitted while the second worker waits.'
    );
    $workers[$first]['channel']->send(['type' => 'release_marker']);
    expectResult($workers[$first], ['sent' => $success ? 1 : 0, 'failed' => $success ? 0 : 1], 1, true);
    expectResult($workers[$second], ['sent' => 0, 'failed' => 0], 0, true);
    finishWorker($workers[$first]);
    finishWorker($workers[$second]);
    $state = bookingState($pdo, $resourceId, $bookingId, $slug);
    check(
        $state['status'] === 'confirmed' && ($success
        ? $state['reminder_sent_at'] !== null && $state['reminder_last_error'] === ''
        : $state['reminder_sent_at'] === null && trim((string)$state['reminder_last_error']) !== ''),
        'Committed reminder marker must match the normal mail return value.'
    );
    check(
        events($pdo, $bookingId) === [$success ? 'reminder_sent' : 'reminder_failed'],
        'Exactly one actual reminder event must be committed.'
    );
    $after = startWorker($workers, $resourceId, $bookingId, $slug, 'success');
    check((expect($workers[$after], 'candidates')['ids'] ?? null) === [], 'Marked reminders must be excluded from a fresh cron pass.');
    expectResult($workers[$after], ['sent' => 0, 'failed' => 0], 0, false);
    finishWorker($workers[$after]);
    check(
        bookingState($pdo, $resourceId, $bookingId, $slug) === $state
        && events($pdo, $bookingId) === [$success ? 'reminder_sent' : 'reminder_failed'],
        'A later cron pass must not retry or modify sent/failed reminders.'
    );
}

/** @param array<int,array<string,mixed>> $workers @param list<string> $waitEvidence */
function changedBeforeLock(PDO $pdo, array &$workers, int $resourceId, int $bookingId, string $slug, bool $cancel, array &$waitEvidence): void
{
    assertOwner($pdo, $resourceId, $bookingId, $slug);
    $pdo->beginTransaction();
    $resource = $pdo->prepare('SELECT slug FROM cms_res_resources WHERE id = ? FOR UPDATE');
    $resource->execute([$resourceId]);
    check($resource->fetchColumn() === $slug, 'Parent refused an unowned resource lock.');
    $booking = $pdo->prepare('SELECT guest_name FROM cms_res_bookings WHERE id = ? AND resource_id = ? FOR UPDATE');
    $booking->execute([$bookingId, $resourceId]);
    check($booking->fetchColumn() === $slug, 'Parent refused an unowned booking lock.');
    $index = startWorker($workers, $resourceId, $bookingId, $slug, 'success');
    expectCandidateSnapshot($workers[$index], $bookingId);
    $waitEvidence[] = observeResourceWait($pdo, $workers[$index], (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn());
    if ($cancel) {
        $pdo->prepare("UPDATE cms_res_bookings SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW()
            WHERE id = ? AND resource_id = ? AND guest_name = ?")->execute([$bookingId, $resourceId, $slug]);
    } else {
        // The +2h booking ceases to be due when the lead time becomes one hour.
        $pdo->prepare('UPDATE cms_res_resources SET reminder_hours_before = 1 WHERE id = ? AND slug = ?')
            ->execute([$resourceId, $slug]);
    }
    $workers[$index]['channel']->assertQuiet();
    $pdo->commit();
    expectResult($workers[$index], ['sent' => 0, 'failed' => 0], 0, true);
    finishWorker($workers[$index]);
    $state = bookingState($pdo, $resourceId, $bookingId, $slug);
    check(
        $state['status'] === ($cancel ? 'cancelled' : 'confirmed') && $state['reminder_sent_at'] === null
        && $state['reminder_last_error'] === '' && events($pdo, $bookingId) === [],
        'Current cancellation/due state must suppress all mail and reminder markers/events despite the old snapshot.'
    );
}

/** @param list<int> $bookingIds */
function cleanup(PDO $pdo, int $resourceId, string $slug, array $bookingIds): void
{
    $pdo->beginTransaction();
    try {
        $owner = $pdo->prepare('SELECT slug FROM cms_res_resources WHERE id = ? FOR UPDATE');
        $owner->execute([$resourceId]);
        $currentSlug = $owner->fetchColumn();
        check($currentSlug === false || $currentSlug === $slug, 'Cleanup refused a resource not owned by this test.');
        if ($currentSlug === $slug) {
            foreach ($bookingIds as $bookingId) {
                $booking = $pdo->prepare('SELECT resource_id, guest_name FROM cms_res_bookings WHERE id = ? FOR UPDATE');
                $booking->execute([$bookingId]);
                $row = $booking->fetch();
                check(
                    $row === false || ((int)$row['resource_id'] === $resourceId && $row['guest_name'] === $slug),
                    'Cleanup refused an unowned booking ID.'
                );
                $pdo->prepare('DELETE FROM cms_res_booking_events WHERE booking_id = ?')->execute([$bookingId]);
                $pdo->prepare('DELETE FROM cms_res_bookings WHERE id = ? AND resource_id = ? AND guest_name = ?')
                    ->execute([$bookingId, $resourceId, $slug]);
            }
            $remaining = $pdo->prepare('SELECT COUNT(*) FROM cms_res_bookings WHERE resource_id = ?');
            $remaining->execute([$resourceId]);
            check((int)$remaining->fetchColumn() === 0, 'Cleanup refused to remove a resource with untracked bookings.');
            $pdo->prepare('DELETE FROM cms_res_resources WHERE id = ? AND slug = ?')->execute([$resourceId, $slug]);
        }
        $pdo->commit();
        foreach ($bookingIds as $bookingId) {
            foreach (['cms_res_bookings' => 'id', 'cms_res_booking_events' => 'booking_id'] as $table => $key) {
                $remaining = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $key . ' = ?');
                $remaining->execute([$bookingId]);
                check((int)$remaining->fetchColumn() === 0, 'Cleanup left owned rows in ' . $table . '.');
            }
        }
        $remaining = $pdo->prepare('SELECT COUNT(*) FROM cms_res_resources WHERE id = ?');
        $remaining->execute([$resourceId]);
        check((int)$remaining->fetchColumn() === 0, 'Cleanup left the owned resource.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function offlineChecks(string $cronCode): void
{
    $candidate = null;
    foreach (token_get_all('<?php ' . $cronCode) as $token) {
        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $literal = eval('return ' . $token[1] . ';');
            if (is_string($literal) && str_starts_with(normalizeSql($literal), 'SELECT b.id ')) {
                check($candidate === null, 'Ambiguous candidate SELECT in production function.');
                $candidate = $literal;
            }
        }
    }
    check(is_string($candidate), 'Cannot locate production candidate SELECT.');
    $slug = 'rc-res-reminder-' . str_repeat('a', 24);
    $scoped = FixturePdo::scopedCandidateSql($candidate, 123, 456, $slug);
    $filter = "b.resource_id = 123 AND b.id = 456 AND r.slug = '{$slug}' AND ";
    $restored = str_replace($filter . '(', '', normalizeSql($scoped));
    $restored = preg_replace('/\) (ORDER BY b\.booking_date, b\.start_time, b\.id LIMIT 100)$/', ' $1', $restored);
    check(
        $restored === normalizeSql($candidate),
        'Candidate isolation must preserve all production due predicates, order and limit.'
    );
    check(strpos($scoped, $filter) < strpos($scoped, 'ORDER BY'), 'Scope must be applied before candidate LIMIT.');
    $withOr = str_replace("WHERE b.status", "WHERE 1 = 1 OR b.status", $candidate);
    check(
        str_contains(FixturePdo::scopedCandidateSql($withOr, 123, 456, $slug), $filter . '(1 = 1 OR b.status'),
        'An OR in the production predicate must not escape the fixed fixture scope.'
    );
    foreach ([$candidate . '; SELECT b.id FROM cms_res_bookings b', 'SELECT b.id FROM cms_res_bookings b'] as $unsafe) {
        $rejected = false;
        try {
            FixturePdo::scopedCandidateSql($unsafe, 123, 456, $slug);
        } catch (RuntimeException $exception) {
            $rejected = true;
        }
        check($rejected, 'Changed/unsafe candidate SQL must fail closed.');
    }
    foreach ([[0, 456, $slug], [123, 0, $slug], [123, 456, $slug . "' OR 1=1 --"]] as $scope) {
        $rejected = false;
        try {
            FixturePdo::scopedCandidateSql($candidate, ...$scope);
        } catch (RuntimeException $exception) {
            $rejected = true;
        }
        check($rejected, 'Invalid IDs/slugs must not enter the candidate SQL.');
    }
    check(
        function_exists(__NAMESPACE__ . '\\cronProcessReservationReminders')
        && function_exists(__NAMESPACE__ . '\\reservationBookingForNotification')
        && !function_exists('cronProcessReservationReminders') && !function_exists('reservationSendMail'),
        'Production functions and mail mock must remain namespace-isolated.'
    );
}

$pdo = null;
$resourceId = 0;
$bookingIds = [];
$workers = [];
$waitEvidence = [];
$failure = null;
$slug = 'rc-res-reminder-' . bin2hex(random_bytes(12));

try {
    check(count($argv) === 1 || ($argv[1] ?? '') === '--worker'
        || (count($argv) === 2 && $argv[1] === '--offline'), 'Usage: php ' . basename(__FILE__) . ' [--offline]');
    $cronFunctions = loadFunctions('cron.php', ['cronMissingColumns', 'cronProcessReservationReminders']);
    loadFunctions('lib/presentation.php', ['reservationBookingForNotification', 'reservationReminderIsDue',
        'reservationReminderSubject', 'reservationReminderBody', 'reservationRecordBookingEvent', 'reservationBookingEventLabels']);
    offlineChecks($cronFunctions['cronProcessReservationReminders']);
    if (($argv[1] ?? '') === '--worker') {
        exit(worker($argv));
    }
    if (($argv[1] ?? '') === '--offline') {
        echo 'OK: ' . $checks . " offline reminder checks; production functions extracted; candidate scope verified; no DB/SMTP.\n";
        exit(0);
    }
    $pdo = connect();
    $tables = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN
        ('cms_res_resources', 'cms_res_bookings', 'cms_res_booking_events')")->fetchAll();
    check(count($tables) === 3, 'All three reminder fixture tables must exist.');
    foreach ($tables as $table) {
        check(strcasecmp((string)$table['ENGINE'], 'InnoDB') === 0, $table['TABLE_NAME'] . ' must use InnoDB.');
    }
    $pdo->prepare("INSERT INTO cms_res_resources
        (name, slug, capacity, slot_mode, min_advance_hours, cancellation_hours, allow_guests,
         reminders_enabled, reminder_hours_before, calendar_invite_enabled, is_active)
        VALUES (?, ?, 1, 'range', 0, 0, 1, 1, 24, 0, 1)")->execute([$slug, $slug]);
    $resourceId = (int)$pdo->lastInsertId();
    $bookingId = insertBooking($pdo, $resourceId, $slug, $bookingIds);
    $booking = reservationBookingForNotification($pdo, $bookingId);
    check(is_array($booking) && reservationReminderIsDue($booking), 'Fixture must be genuinely due under the production predicate.');
    concurrentMail($pdo, $workers, $resourceId, $bookingId, $slug, true, $waitEvidence);
    concurrentMail($pdo, $workers, $resourceId, insertBooking($pdo, $resourceId, $slug, $bookingIds), $slug, false, $waitEvidence);
    changedBeforeLock($pdo, $workers, $resourceId, insertBooking($pdo, $resourceId, $slug, $bookingIds), $slug, true, $waitEvidence);
    changedBeforeLock($pdo, $workers, $resourceId, insertBooking($pdo, $resourceId, $slug, $bookingIds), $slug, false, $waitEvidence);
} catch (Throwable $exception) {
    $failure = $exception->getMessage();
} finally {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($workers as &$entry) {
        if (is_resource($entry['process'])) {
            $status = proc_get_status($entry['process']);
            if ($status['running']) {
                proc_terminate($entry['process']);
            }
            proc_close($entry['process']);
        }
        if ($entry['channel'] instanceof Channel && is_resource($entry['channel']->stream)) {
            fclose($entry['channel']->stream);
        }
        if (is_resource($entry['listener'])) {
            fclose($entry['listener']);
        }
        if (is_resource($entry['log'])) {
            if ($failure !== null) {
                rewind($entry['log']);
                $failure .= "\nWorker: " . stream_get_contents($entry['log'], 8192);
            }
            fclose($entry['log']);
        }
    }
    unset($entry);
    if ($pdo instanceof PDO && $resourceId > 0) {
        try {
            cleanup($pdo, $resourceId, $slug, $bookingIds);
        } catch (Throwable $exception) {
            $failure = ($failure !== null ? $failure . '; ' : '') . 'Cleanup: ' . $exception->getMessage();
        }
    }
}

if ($failure !== null) {
    fwrite(STDERR, 'FAIL: ' . $failure . "\n");
    exit(1);
}
echo 'OK: ' . $checks . " reminder MySQL checks; two workers send once; mail=false is marked without retry;"
    . " cancellation/due rechecked against old RR snapshots; waits=" . implode(', ', array_unique($waitEvidence))
    . "; owned fixtures cleaned; no outgoing mail.\n";
