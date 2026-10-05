<?php

declare(strict_types=1);

namespace KoraRcChatIntegritySelfTest;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

const BASE_URL = '';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$checks = 0;
$context = '';

function same(mixed $actual, mixed $expected, string $label): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($GLOBALS['context'] . ': ' . $label
            . '; expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    $GLOBALS['checks']++;
}

function loadProductionFunctions(string $relativePath, ?array $only = null): void
{
    $source = file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    if (!is_string($source)) {
        throw new RuntimeException('Cannot read production ' . $relativePath);
    }
    // Load declarations only: never bootstrap config, application DB, HTTP or mail.
    $tokens = token_get_all($source, TOKEN_PARSE);
    $declarations = '';
    for ($index = 0, $count = count($tokens); $index < $count; $index++) {
        if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_FUNCTION) {
            continue;
        }
        $name = null;
        for ($next = $index + 1; $next < $count && $tokens[$next] !== '('; $next++) {
            if (is_array($tokens[$next]) && $tokens[$next][0] === T_STRING) {
                $name = $tokens[$next][1];
                break;
            }
        }
        $declaration = '';
        $depth = 0;
        $bodyStarted = false;
        do {
            $token = $tokens[$index];
            $declaration .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token)
                && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $bodyStarted = true;
                $depth++;
            } elseif ($token === '}') {
                $depth--;
            }
            $index++;
        } while ($index < $count && (!$bodyStarted || $depth > 0));
        if (!$bodyStarted || $depth !== 0) {
            throw new RuntimeException('Incomplete production function declaration');
        }
        if ($name !== null && ($only === null || in_array($name, $only, true))) {
            $declarations .= $declaration . "\n";
        }
        $index--;
    }
    eval('namespace ' . __NAMESPACE__ . '; use \\PDO; use \\PDOException; use \\Throwable; '
        . 'use \\RuntimeException; use \\DateTimeImmutable; use \\DateTimeInterface; ' . $declarations);
    foreach ($only ?? ['chatCreateSubmission', 'chatCreatePublicReply', 'deleteChatMessage'] as $name) {
        same(function_exists(__NAMESPACE__ . '\\' . $name), true, 'Production helper exists: ' . $name);
    }
}

final class FixtureStatement extends PDOStatement
{
    public string $productionSql = '';

    protected function __construct(private FixturePdo $fixture)
    {
    }

    public function execute(?array $params = null): bool
    {
        $index = count($this->fixture->events);
        $this->fixture->events[] = [
            'sql' => trim((string)preg_replace('/\s+/', ' ', $this->productionSql)),
            'transaction' => $this->fixture->inTransaction(),
            'completed' => false,
            'affected' => 0,
        ];
        $result = parent::execute($params);
        $this->fixture->events[$index]['completed'] = $result;
        $this->fixture->events[$index]['affected'] = $this->rowCount();
        return $result;
    }
}

final class FixturePdo extends PDO
{
    /** @var list<array{sql:string, transaction:bool, completed:bool, affected:int}> */
    public array $events = [];
    public mixed $beforeBegin = null;

    public function __construct(bool $stringify, public bool $mysqlLockSql)
    {
        parent::__construct('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => $stringify,
        ]);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [FixtureStatement::class, [$this]]);
    }

    public function getAttribute(int $attribute): mixed
    {
        // Exercise MySQL's production SQL branch, not MySQL's locking semantics.
        return $attribute === PDO::ATTR_DRIVER_NAME && $this->mysqlLockSql
            ? 'mysql' : parent::getAttribute($attribute);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $adapted = (string)preg_replace('/\s+FOR\s+UPDATE\s*$/i', '', $query);
        $statement = parent::prepare($adapted, $options);
        if ($statement instanceof FixtureStatement) {
            $statement->productionSql = $query;
        }
        return $statement;
    }

    public function beginTransaction(): bool
    {
        $callback = $this->beforeBegin;
        $this->beforeBegin = null;
        if ($callback !== null) {
            $callback($this);
        }
        return parent::beginTransaction();
    }
}

function fixture(bool $stringify, bool $mysqlLockSql): FixturePdo
{
    $pdo = new FixturePdo($stringify, $mysqlLockSql);
    // Match install.php column nullability/defaults and ENUM domains. Canonical
    // chat tables have no foreign keys; intentional orphans must remain testable.
    $pdo->exec("CREATE TABLE cms_chat (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        topic_id INTEGER NULL DEFAULT NULL,
        topic_label TEXT NOT NULL DEFAULT '' CHECK(length(topic_label) <= 255),
        conversation_type TEXT NOT NULL DEFAULT 'public' CHECK(conversation_type IN ('public','support')),
        reference_code TEXT NOT NULL DEFAULT '' CHECK(length(reference_code) <= 32),
        name TEXT NOT NULL CHECK(length(name) <= 100),
        email TEXT NOT NULL DEFAULT '' CHECK(length(email) <= 255),
        web TEXT NOT NULL DEFAULT '' CHECK(length(web) <= 255),
        message TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'new' CHECK(status IN ('new','read','handled')),
        public_visibility TEXT NOT NULL DEFAULT 'pending' CHECK(public_visibility IN ('pending','approved','hidden')),
        is_pinned INTEGER NOT NULL DEFAULT 0,
        pinned_until TEXT NULL DEFAULT NULL,
        pinned_at TEXT NULL DEFAULT NULL,
        pinned_by_user_id INTEGER NULL DEFAULT NULL,
        approved_at TEXT NULL DEFAULT NULL,
        approved_by_user_id INTEGER NULL DEFAULT NULL,
        internal_note TEXT,
        replied_at TEXT NULL DEFAULT NULL,
        replied_by_user_id INTEGER NULL DEFAULT NULL,
        replied_subject TEXT NOT NULL DEFAULT '' CHECK(length(replied_subject) <= 255),
        replied_to_email TEXT NOT NULL DEFAULT '' CHECK(length(replied_to_email) <= 255),
        replied_body TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE cms_chat_replies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        chat_id INTEGER NOT NULL,
        name TEXT NOT NULL CHECK(length(name) <= 100),
        email TEXT NOT NULL DEFAULT '' CHECK(length(email) <= 255),
        message TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','hidden')),
        approved_at TEXT NULL DEFAULT NULL,
        approved_by_user_id INTEGER NULL DEFAULT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE cms_chat_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        chat_id INTEGER NOT NULL,
        actor_user_id INTEGER NULL DEFAULT NULL,
        event_type TEXT NOT NULL DEFAULT 'workflow' CHECK(length(event_type) <= 50),
        message TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    INSERT INTO cms_chat (id, name, message, status, public_visibility, created_at, updated_at)
        VALUES (1, 'Target', 'Target body', 'handled', 'approved', '2000-01-01 00:00:00', '2000-01-01 00:00:00');
    INSERT INTO cms_chat (id, name, message, conversation_type, public_visibility)
        VALUES (2, 'Foreign', 'Foreign body', 'support', 'hidden');
    INSERT INTO cms_chat_replies (id, chat_id, name, message, status)
        VALUES (1, 1, 'Target reply', 'Target reply body', 'approved'),
               (2, 2, 'Foreign reply', 'Foreign reply body', 'hidden'),
               (3, 999, 'Orphan reply', 'Orphan reply body', 'pending');
    INSERT INTO cms_chat_history (id, chat_id, actor_user_id, event_type, message)
        VALUES (1, 1, 7, 'fixture', 'Target history'),
               (2, 2, 8, 'fixture', 'Foreign history'),
               (3, 999, NULL, 'fixture', 'Orphan history');");
    return $pdo;
}

function snapshot(PDO $pdo): array
{
    $result = [];
    foreach (['cms_chat', 'cms_chat_replies', 'cms_chat_history'] as $table) {
        $result[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll();
    }
    return $result;
}

function withoutChat(array $data, int $chatId): array
{
    foreach ($data as $table => $rows) {
        $key = $table === 'cms_chat' ? 'id' : 'chat_id';
        $data[$table] = array_values(array_filter($rows, static fn (array $row): bool => (int)$row[$key] !== $chatId));
    }
    return $data;
}

function sqlCount(PDO $pdo, string $query, array $params = []): int
{
    $statement = $pdo->prepare($query);
    $statement->execute($params);
    $value = $statement->fetchColumn();
    // PHP 8.0 SQLite may return strings even without ATTR_STRINGIFY_FETCHES.
    same(
        (is_int($value) && $value >= 0)
        || (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) === 1),
        true,
        'SQL count is a nonnegative integer or integer string'
    );
    return (int)$value;
}

function expectFailure(callable $action, string $marker): void
{
    try {
        $action();
    } catch (PDOException $exception) {
        same(str_contains($exception->getMessage(), $marker), true, 'Injected failure propagates: ' . $marker);
        return;
    }
    throw new RuntimeException('Expected PDO failure did not propagate: ' . $marker);
}

function writeEvents(FixturePdo $pdo): array
{
    return array_values(array_filter(
        $pdo->events,
        static fn (array $event): bool => preg_match('/\A(?:INSERT|UPDATE|DELETE)\b/i', $event['sql']) === 1
    ));
}

function assertParentFirst(FixturePdo $pdo, bool $mutates): void
{
    $first = $pdo->events[0] ?? [];
    same(
        preg_match('/\ASELECT\b.*\bFROM cms_chat\b/i', $first['sql'] ?? '') === 1,
        true,
        'Parent is read before any child write'
    );
    same($first['transaction'] ?? false, true, 'Parent read occurs inside transaction');
    same($first['completed'] ?? false, true, 'Parent read executes, not merely prepares');
    same(
        str_ends_with(strtoupper($first['sql'] ?? ''), ' FOR UPDATE'),
        $pdo->mysqlLockSql,
        'MySQL branch requests the parent lock before child access'
    );
    same(writeEvents($pdo) !== [], $mutates, 'Rejected operation performs no writes');
    foreach (writeEvents($pdo) as $event) {
        same($event['transaction'], true, 'Every write shares the parent transaction');
    }
}

function assertCompletedWrite(FixturePdo $pdo, string $prefix): void
{
    $matches = array_values(array_filter(
        $pdo->events,
        static fn (array $event): bool => str_starts_with($event['sql'], $prefix) && $event['completed']
    ));
    same(count($matches), 1, 'Write executed before injected failure: ' . $prefix);
    same($matches[0]['affected'] > 0, true, 'Write changed fixture data: ' . $prefix);
}

function assertTimestamp(string $value, string $label): void
{
    same(preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\z/', $value) === 1, true, $label);
}

function submissionTests(bool $stringify, bool $mysqlLockSql): void
{
    foreach (['public', 'support'] as $type) {
        $pdo = fixture($stringify, $mysqlLockSql);
        $pdo->exec("CREATE TRIGGER fail_history BEFORE INSERT ON cms_chat_history
            BEGIN SELECT RAISE(ABORT, 'fixture submission history failure'); END");
        $before = snapshot($pdo);
        expectFailure(
            static fn () => chatCreateSubmission($pdo, 7, 'Topic', $type, 'Author', 'author@example.test', 'Body'),
            'fixture submission history failure'
        );
        assertCompletedWrite($pdo, 'INSERT INTO cms_chat ');
        same(snapshot($pdo), $before, $type . ' history failure restores parent and every existing row');
        same($pdo->inTransaction(), false, 'Failed submission releases transaction');
    }

    $pdo = fixture($stringify, $mysqlLockSql);
    foreach (['public', 'invalid', ' public ', ' support ', 'support'] as $inputType) {
        $before = snapshot($pdo);
        $private = trim($inputType) === 'support';
        $topicId = $private ? 7 : null;
        $topicLabel = $private ? 'Soukromé dotazy' : '';
        $result = chatCreateSubmission(
            $pdo,
            $topicId,
            $topicLabel,
            $inputType,
            'Žofie Nováková',
            'author@example.test',
            'Zpráva s diakritikou.'
        );
        same(array_keys($result), ['id', 'reference_code'], 'Submission return shape');
        same(is_int($result['id']) && $result['id'] > 0, true, 'Returned id is a positive PHP integer');
        same(is_string($result['reference_code']), true, 'Reference is a PHP string');
        same($private ? preg_match('/\ACHT-[0-9]{8}-[A-Z0-9]{4}\z/', $result['reference_code']) === 1
            : $result['reference_code'] === '', true, 'Only support submissions receive a reference');
        $after = snapshot($pdo);
        same(withoutChat($after, $result['id']), $before, 'Submission preserves all preexisting data');
        $parent = $after['cms_chat'][count($after['cms_chat']) - 1];
        $history = $after['cms_chat_history'][count($after['cms_chat_history']) - 1];
        same((int)$parent['id'], $result['id'], 'Returned id belongs to persisted parent');
        same($parent['topic_id'] === null ? null : (int)$parent['topic_id'], $topicId, 'Nullable topic id persists');
        same($parent['topic_label'], $topicLabel, 'Topic label persists');
        same($parent['conversation_type'], $private ? 'support' : 'public', 'Conversation type is normalized');
        same($parent['reference_code'], $result['reference_code'], 'Returned reference belongs to parent');
        same(
            [$parent['name'], $parent['email'], $parent['message']],
            ['Žofie Nováková', 'author@example.test', 'Zpráva s diakritikou.'],
            'Submission preserves UTF-8 fields'
        );
        same(
            [$parent['status'], $parent['public_visibility'], $parent['web']],
            ['new', $private ? 'hidden' : 'pending', ''],
            'Submission enters the correct private/moderation workflow'
        );
        same(
            [(int)$history['chat_id'], $history['actor_user_id'], $history['event_type'], $history['message']],
            [$result['id'], null, 'submitted', $private ? 'Soukromý dotaz byl přijat do podpůrného inboxu.'
                : 'Zpráva byla přijata a čeká na schválení.'],
            'Submitted history belongs to the new parent'
        );
        same(
            sqlCount($pdo, 'SELECT COUNT(*) FROM cms_chat_history WHERE chat_id = ?', [$result['id']]),
            1,
            'Submission creates exactly one history entry'
        );
        assertTimestamp((string)$parent['created_at'], 'Parent creation timestamp is populated');
        assertTimestamp((string)$parent['updated_at'], 'Parent update timestamp is populated');
        assertTimestamp((string)$history['created_at'], 'History creation timestamp is populated');
        same($pdo->inTransaction(), false, 'Successful submission commits');
        if ($private) {
            same(
                sqlCount($pdo, 'SELECT COUNT(*) FROM cms_chat WHERE reference_code = ?', [$result['reference_code']]),
                1,
                'Private reference does not collide with another parent'
            );
        }
    }
}

function replyTests(bool $stringify, bool $mysqlLockSql): void
{
    foreach (['missing', 'pending', 'hidden', 'support-approved', 'support-hidden'] as $state) {
        $pdo = fixture($stringify, $mysqlLockSql);
        if ($state === 'pending' || $state === 'hidden') {
            $pdo->prepare('UPDATE cms_chat SET public_visibility = ? WHERE id = 1')->execute([$state]);
        } elseif (str_starts_with($state, 'support-')) {
            $pdo->prepare("UPDATE cms_chat SET conversation_type = 'support', public_visibility = ? WHERE id = 1")
                ->execute([substr($state, 8)]);
        }
        $before = snapshot($pdo);
        $pdo->events = [];
        same(
            chatCreatePublicReply($pdo, $state === 'missing' ? 999 : 1, 'Visitor', '', 'Reply'),
            false,
            $state . ' parent rejects public reply'
        );
        assertParentFirst($pdo, false);
        same(snapshot($pdo), $before, $state . ' reply rejection preserves the complete snapshot');
        same($pdo->inTransaction(), false, 'Rejected reply releases transaction');
    }

    // A deterministic state change between an old page read and transaction entry
    // proves live eligibility checks, not real SQLite concurrency or row locking.
    foreach (["UPDATE cms_chat SET public_visibility = 'hidden' WHERE id = 1",
        "UPDATE cms_chat SET conversation_type = 'support' WHERE id = 1",
        'DELETE FROM cms_chat WHERE id = 1'] as $change) {
        $pdo = fixture($stringify, $mysqlLockSql);
        $current = null;
        $pdo->beforeBegin = static function (FixturePdo $connection) use ($change, &$current): void {
            $connection->exec($change);
            $current = snapshot($connection);
        };
        same(
            chatCreatePublicReply($pdo, 1, 'Visitor', '', 'Stale page reply'),
            false,
            'Reply uses current parent state, not an earlier public page snapshot'
        );
        assertParentFirst($pdo, false);
        same(is_array($current), true, 'State-change hook executed before helper transaction');
        same(snapshot($pdo), $current, 'Stale-page rejection neither creates nor cleans orphan rows');
        same($pdo->inTransaction(), false, 'Stale-page rejection releases transaction');
    }

    foreach (['reply', 'history', 'parent'] as $failure) {
        $pdo = fixture($stringify, $mysqlLockSql);
        $trigger = $failure === 'reply' ? 'BEFORE INSERT ON cms_chat_replies'
            : ($failure === 'history' ? 'BEFORE INSERT ON cms_chat_history' : 'BEFORE UPDATE ON cms_chat');
        $marker = 'fixture reply ' . $failure . ' failure';
        $pdo->exec("CREATE TRIGGER fail_reply {$trigger} BEGIN SELECT RAISE(ABORT, '{$marker}'); END");
        $before = snapshot($pdo);
        expectFailure(static fn () => chatCreatePublicReply($pdo, 1, 'Visitor', '', 'Reply'), $marker);
        assertParentFirst($pdo, true);
        if ($failure !== 'reply') {
            assertCompletedWrite($pdo, 'INSERT INTO cms_chat_replies ');
        }
        if ($failure === 'parent') {
            assertCompletedWrite($pdo, 'INSERT INTO cms_chat_history ');
        }
        same(snapshot($pdo), $before, 'Failed reply restores parent timestamp, children, history and unrelated rows');
        same($pdo->inTransaction(), false, 'Failed reply releases transaction');
    }

    $pdo = fixture($stringify, $mysqlLockSql);
    $before = snapshot($pdo);
    $earliest = (string)$pdo->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
    same(
        chatCreatePublicReply($pdo, 1, 'Visitor', 'visitor@example.test', 'Pending reply'),
        true,
        'Approved public parent accepts reply'
    );
    $latest = (string)$pdo->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
    assertParentFirst($pdo, true);
    $after = snapshot($pdo);
    $reply = $after['cms_chat_replies'][count($after['cms_chat_replies']) - 1];
    $history = $after['cms_chat_history'][count($after['cms_chat_history']) - 1];
    same(
        [(int)$reply['chat_id'], $reply['name'], $reply['email'], $reply['message'], $reply['status'],
        $reply['approved_at'], $reply['approved_by_user_id']],
        [1, 'Visitor', 'visitor@example.test', 'Pending reply', 'pending', null, null],
        'Reply remains pending, not approved'
    );
    same(
        [(int)$history['chat_id'], $history['actor_user_id'], $history['event_type'], $history['message']],
        [1, null, 'reply_submitted', 'Veřejná odpověď byla přijata a čeká na schválení.'],
        'Reply history is persisted'
    );
    $updatedAt = $after['cms_chat'][0]['updated_at'];
    same(
        $updatedAt !== $before['cms_chat'][0]['updated_at'] && $updatedAt >= $earliest && $updatedAt <= $latest,
        true,
        'Reply refreshes parent updated_at in its transaction'
    );
    $expected = $before;
    $expected['cms_chat'][0]['updated_at'] = $updatedAt;
    $expected['cms_chat_replies'][] = $reply;
    $expected['cms_chat_history'][] = $history;
    same($after, $expected, 'Successful reply changes only the parent timestamp and one reply/history pair');
    assertTimestamp((string)$reply['created_at'], 'Reply creation timestamp is populated');
    same($reply['updated_at'], $reply['created_at'], 'Reply update timestamp receives its canonical default');
    same(
        sqlCount($pdo, "SELECT COUNT(*) FROM cms_chat_replies WHERE chat_id = 1 AND status = 'pending'"),
        1,
        'Exactly one pending reply is persisted'
    );
    same(
        sqlCount($pdo, "SELECT COUNT(*) FROM cms_chat_history WHERE chat_id = 1 AND event_type = 'reply_submitted'"),
        1,
        'Exactly one submitted reply history is persisted'
    );
    same($pdo->inTransaction(), false, 'Successful reply commits');
    $pdo->events = [];
    same(
        deleteChatMessage($pdo, 1, '2001-01-01 00:00:00'),
        false,
        'A newly submitted reply protects an otherwise handled parent from stale retention'
    );
    assertParentFirst($pdo, false);
    same(snapshot($pdo), $after, 'Retention after actual reply preserves the entire thread');
    same($pdo->inTransaction(), false, 'Retention after actual reply releases transaction');
}

function deletionTests(bool $stringify, bool $mysqlLockSql): void
{
    $cutoff = '2001-01-01 00:00:00';
    foreach ([999, 998, 0, -1] as $id) {
        $pdo = fixture($stringify, $mysqlLockSql);
        $before = snapshot($pdo);
        same(deleteChatMessage($pdo, $id), false, 'Missing parent deletion is a no-op');
        assertParentFirst($pdo, false);
        same(snapshot($pdo), $before, 'Nonexisting deletion preserves orphan and foreign rows');
        same($pdo->inTransaction(), false, 'Nonexisting deletion releases transaction');
    }

    foreach (["UPDATE cms_chat SET updated_at = '2002-01-01 00:00:00' WHERE id = 1",
        "UPDATE cms_chat SET status = 'read' WHERE id = 1",
        "UPDATE cms_chat SET updated_at = '2001-01-01 00:00:00' WHERE id = 1"] as $change) {
        $pdo = fixture($stringify, $mysqlLockSql);
        same(
            sqlCount($pdo, "SELECT COUNT(*) FROM cms_chat WHERE id = 1 AND status = 'handled' AND updated_at < ?", [$cutoff]),
            1,
            'Parent was eligible when retention selected the candidate'
        );
        $pdo->events = [];
        $current = null;
        $pdo->beforeBegin = static function (FixturePdo $connection) use ($change, &$current): void {
            $connection->exec($change);
            $current = snapshot($connection);
        };
        same(deleteChatMessage($pdo, 1, $cutoff), false, 'Retention rechecks current status and strict cutoff');
        assertParentFirst($pdo, false);
        same(is_array($current), true, 'Retention state change executes before transaction');
        same(snapshot($pdo), $current, 'Stale retention selection changes no parent, child or foreign data');
        same($pdo->inTransaction(), false, 'Rejected retention releases transaction');
    }

    foreach ([null, $cutoff] as $retentionCutoff) {
        $pdo = fixture($stringify, $mysqlLockSql);
        $pdo->exec("CREATE TRIGGER fail_final_delete BEFORE DELETE ON cms_chat WHEN OLD.id = 1
            BEGIN SELECT RAISE(ABORT, 'fixture final delete failure'); END");
        $before = snapshot($pdo);
        expectFailure(static fn () => deleteChatMessage($pdo, 1, $retentionCutoff), 'fixture final delete failure');
        assertParentFirst($pdo, true);
        assertCompletedWrite($pdo, 'DELETE FROM cms_chat_replies ');
        assertCompletedWrite($pdo, 'DELETE FROM cms_chat_history ');
        same(snapshot($pdo), $before, 'Final DELETE failure restores partial child cleanup and all parent data');
        same($pdo->inTransaction(), false, 'Failed cleanup releases transaction');
        $pdo->exec('DROP TRIGGER fail_final_delete');
        $pdo->events = [];
        same(deleteChatMessage($pdo, 1, $retentionCutoff), true, 'Eligible cleanup succeeds after rollback');
        assertParentFirst($pdo, true);
        same(snapshot($pdo), withoutChat($before, 1), 'Successful cleanup removes only the selected parent and its children');
        same(sqlCount($pdo, 'SELECT COUNT(*) FROM cms_chat WHERE id = 1'), 0, 'Selected parent is deleted');
        same(sqlCount($pdo, 'SELECT COUNT(*) FROM cms_chat_replies WHERE chat_id = 1'), 0, 'Selected replies are deleted');
        same(sqlCount($pdo, 'SELECT COUNT(*) FROM cms_chat_history WHERE chat_id = 1'), 0, 'Selected history is deleted');
        same($pdo->inTransaction(), false, 'Successful cleanup commits');
        $remaining = snapshot($pdo);
        $pdo->events = [];
        same(deleteChatMessage($pdo, 1, $retentionCutoff), false, 'Repeated cleanup is a safe no-op');
        assertParentFirst($pdo, false);
        same(snapshot($pdo), $remaining, 'Repeated cleanup preserves foreign and orphan rows');
    }

    $pdo = fixture($stringify, $mysqlLockSql);
    $pdo->exec("UPDATE cms_chat SET status = 'read', updated_at = '2002-01-01 00:00:00' WHERE id = 1");
    $before = snapshot($pdo);
    same(deleteChatMessage($pdo, 1), true, 'Explicit deletion without cutoff is not a retention operation');
    assertParentFirst($pdo, true);
    same(snapshot($pdo), withoutChat($before, 1), 'Explicit deletion cleans a read/recent parent only');

    $pdo = fixture($stringify, $mysqlLockSql);
    $before = snapshot($pdo);
    $pdo->beginTransaction();
    same(deleteChatMessage($pdo, 1, $cutoff), true, 'Cleanup supports a caller-owned transaction');
    assertParentFirst($pdo, true);
    same($pdo->inTransaction(), true, 'Cleanup does not commit the caller transaction');
    $pdo->rollBack();
    same(snapshot($pdo), $before, 'Caller rollback restores complete successful cleanup');
}

function replyDraft(): array
{
    return [
        'name' => 'Žofie "<&>',
        'email' => 'visitor+rc@example.test',
        'message' => "Zachovaná odpověď <script>fixture_draft_script()</script>\nDruhý řádek & konec.",
    ];
}

function renderChatMessageView(string $source, bool $threadAvailable): string
{
    $message = [
        'id' => 1,
        'name' => 'fixture-public-author',
        'message' => 'fixture-public-body <script>fixture_parent_script()</script>',
        'created_at' => '2000-01-01 00:00:00',
        'is_pinned' => 1,
        'topic_name' => 'fixture-public-topic',
        'topic_label' => 'fixture-public-topic',
        'topic_slug' => 'fixture-topic',
    ];
    // Mirror the handler contract: unavailable threads receive no public replies.
    $replies = $threadAvailable ? [[
        'name' => 'fixture-approved-reply-author',
        'message' => 'fixture-approved-reply-body',
        'created_at' => '2000-01-02 00:00:00',
    ]] : [];
    $errors = $threadAvailable ? [] : [
        'Na tuto zprávu již nelze odpovědět. Vlákno bylo skryto nebo odstraněno; rozepsaná odpověď zůstala zachovaná.',
    ];
    $formData = replyDraft();
    $successState = '';
    $captchaExpr = '2 × 3';
    $backUrl = '/chat/index.php';
    ob_start();
    try {
        eval('namespace ' . __NAMESPACE__ . '; ?>' . $source);
        return (string)ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

function viewTests(): void
{
    // Extract these production functions only, not db.php/auth.php bootstrap.
    loadProductionFunctions('db.php', ['h']);
    loadProductionFunctions('auth.php', ['csrfToken', 'honeypotField']);
    loadProductionFunctions('lib/presentation.php', ['formatCzechDate']);
    $source = file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/chat-message.php');
    if (!is_string($source)) {
        throw new RuntimeException('Cannot read production chat-message view');
    }
    $hadSession = array_key_exists('_SESSION', $GLOBALS);
    $originalSession = $_SESSION ?? null;
    $_SESSION = ['csrf_token' => 'fixture-chat-csrf'];
    try {
        foreach ([true, false] as $threadAvailable) {
            $GLOBALS['context'] = 'chat-message view / ' . ($threadAvailable ? 'available' : 'unavailable');
            $html = renderChatMessageView($source, $threadAvailable);
            $document = new \DOMDocument();
            same($document->loadHTML('<?xml encoding="UTF-8"><!doctype html><html lang="cs"><body>'
                . $html . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING), true, 'Production view renders parseable HTML');
            $xpath = new \DOMXPath($document);
            same($xpath->query('//script')->length, 0, 'Parent and retained draft text are escaped, not executable');
            foreach (['fixture-public-author', 'fixture-public-body', 'fixture-public-topic',
                'fixture-approved-reply-author', 'fixture-approved-reply-body'] as $publicText) {
                same(str_contains($html, $publicText), $threadAvailable, 'Public content visibility: ' . $publicText);
            }
            same(
                $document->getElementById('chat-message-title')->textContent,
                $threadAvailable ? 'Zpráva od fixture-public-author' : 'Vlákno chatu již není dostupné',
                'Unavailable title does not disclose original author'
            );
            same(
                $xpath->query('//time[@datetime="2000-01-01T00:00:00"]')->length,
                $threadAvailable ? 1 : 0,
                'Original parent timestamp is not rendered for an unavailable thread'
            );
            same(str_contains($html, 'Připnuto'), $threadAvailable, 'Original pinned metadata is not rendered when unavailable');
            if (!$threadAvailable) {
                same(
                    $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " meta-row ")]')->length,
                    0,
                    'Unavailable thread discloses no public metadata rows'
                );
            }
            same($xpath->query('//form')->length, 1, 'Retained reply keeps its form');
            same($xpath->query('//form/fieldset/legend')->length, 1, 'Reply form has a fieldset and legend');
            foreach (replyDraft() as $name => $value) {
                $fields = $xpath->query('//form//*[@name="' . $name . '"]');
                same($fields->length, 1, 'Draft field appears exactly once: ' . $name);
                $field = $fields->item(0);
                same(
                    $field->tagName === 'textarea' ? $field->textContent : $field->getAttribute('value'),
                    $value,
                    'Rendered draft value is preserved: ' . $name
                );
            }
            foreach (['reply-name', 'reply-email', 'reply-message', 'reply-captcha'] as $fieldId) {
                same($xpath->query('//label[@for="' . $fieldId . '"]')->length, 1, 'Form field has a real label: ' . $fieldId);
            }
            $buttons = $xpath->query('//form//button[@type="submit"]');
            same($buttons->length, 1, 'Reply form has one submit button');
            same($buttons->item(0)->hasAttribute('disabled'), !$threadAvailable, 'Unavailable thread cannot resubmit');
            same(
                $xpath->query('//*[@role="alert"]')->length,
                $threadAvailable ? 0 : 1,
                'Unavailable-thread error is announced as an alert'
            );
            same(
                $xpath->query('//form')->item(0)->getAttribute('aria-describedby'),
                $threadAvailable ? '' : 'chat-reply-errors',
                'Failed reply form references its error'
            );
            same(
                $xpath->query('//input[@name="csrf_token"]')->item(0)->getAttribute('value'),
                'fixture-chat-csrf',
                'Production CSRF helper renders the isolated fixture token'
            );
            $ids = [];
            foreach ($xpath->query('//*[@id]') as $element) {
                $id = $element->getAttribute('id');
                same(isset($ids[$id]), false, 'Rendered id is unique: ' . $id);
                $ids[$id] = true;
            }
            foreach ($xpath->query('//*[@aria-describedby or @aria-labelledby]') as $element) {
                foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
                    foreach (preg_split('/\s+/', trim($element->getAttribute($attribute))) ?: [] as $id) {
                        if ($id !== '') {
                            same(isset($ids[$id]), true, 'ARIA reference points to a real element: ' . $id);
                        }
                    }
                }
            }
        }
    } finally {
        if ($hadSession) {
            $_SESSION = $originalSession;
        } else {
            unset($_SESSION);
        }
    }
}

try {
    loadProductionFunctions('lib/messages.php');
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('Required pdo_sqlite driver is unavailable');
    }
    foreach ([false, true] as $stringify) {
        foreach ([false, true] as $mysqlLockSql) {
            $context = ($stringify ? 'stringified' : 'native') . ' PDO / '
                . ($mysqlLockSql ? 'translated MySQL lock SQL' : 'SQLite SQL');
            submissionTests($stringify, $mysqlLockSql);
            replyTests($stringify, $mysqlLockSql);
            deletionTests($stringify, $mysqlLockSql);
        }
    }
    viewTests();
    echo 'Chat integrity self-test OK (' . $checks . ' checks; both PDO fetch/SQL lock branches and production view).' . PHP_EOL;
    echo 'SQLite instrumentation verifies SQL ordering and rollback, not real concurrent row locking.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL . $exception->getTraceAsString() . PHP_EOL);
    exit(1);
}
