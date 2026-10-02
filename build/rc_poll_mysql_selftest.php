<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Only isolated fixtures, without application bootstrap, tracking or outgoing mail.
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/presentation.php';
require_once dirname(__DIR__) . '/lib/poll_voting.php';

function rcPollMysqlConnect(): PDO
{
    $dbHost = (string)$GLOBALS['server'];
    $dbName = (string)$GLOBALS['database'];
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", (string)$GLOBALS['user'], (string)$GLOBALS['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 8');
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    return $pdo;
}

/** @param resource $channel */
function rcPollMysqlReceive($channel): array
{
    stream_set_timeout($channel, 5);
    $line = fgets($channel, 8192);
    if ($line === false || !str_ends_with($line, "\n")) {
        throw new RuntimeException('Missing or incomplete worker signal.');
    }
    $message = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($message)) {
        throw new RuntimeException('Invalid worker signal.');
    }
    return $message;
}

/** @param resource $channel */
function rcPollMysqlSend($channel, array $message): void
{
    $line = json_encode($message, JSON_THROW_ON_ERROR) . "\n";
    if (fwrite($channel, $line) !== strlen($line)) {
        throw new RuntimeException('Incomplete worker signal.');
    }
}

if (($argv[1] ?? '') === '--worker') {
    $workerPdo = null;
    $channel = null;
    try {
        if (count($argv) !== 7 || !ctype_digit($argv[2])
            || preg_match('/^rc-poll-mysql-[a-f0-9]{24}$/D', $argv[3]) !== 1
            || preg_match('/^127\.0\.0\.1:[0-9]+$/D', $argv[4]) !== 1
            || !in_array($argv[5], ['closed', 'removed', 'limit', 'mode', 'duplicate', 'protected', 'valid'], true)
            || preg_match('/^[0-9]+(?:,[0-9]+)*$/D', $argv[6]) !== 1) {
            throw new RuntimeException('Invalid worker arguments.');
        }
        $channel = stream_socket_client('tcp://' . $argv[4], $errorCode, $errorText, 5);
        if ($channel === false) {
            throw new RuntimeException('Cannot connect worker signal channel.');
        }
        $workerPdo = rcPollMysqlConnect();
        $pollId = (int)$argv[2];
        $ownerStmt = $workerPdo->prepare('SELECT slug FROM cms_polls WHERE id = ?');
        $ownerStmt->execute([$pollId]);
        if ($ownerStmt->fetchColumn() !== $argv[3]) {
            throw new RuntimeException('Worker refused a poll not owned by this fixture.');
        }
        $workerPdo->beginTransaction();
        // Establish an old snapshot before the parent changes the poll or inserts votes.
        $snapshotStmt = $workerPdo->prepare('SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = ?');
        $snapshotStmt->execute([$pollId]);
        if ((int)$snapshotStmt->fetchColumn() !== 0) {
            throw new RuntimeException('Worker snapshot must start without votes.');
        }
        rcPollMysqlSend($channel, ['type' => 'ready', 'connection_id' => (int)$workerPdo->query('SELECT CONNECTION_ID()')->fetchColumn()]);
        $choices = array_map('intval', explode(',', $argv[6]));
        if ($argv[5] === 'protected') {
            pollLockForWrite($workerPdo, $pollId);
            $result = ['error' => pollOptionHasVotes($workerPdo, $pollId, $choices[0]) ? 'has_votes' : ''];
        } else {
            $result = pollStoreVote($workerPdo, $pollId, null, $choices, hash('sha256', $argv[3]));
        }
        if ($result['error'] === '' && $argv[5] !== 'protected') {
            $workerPdo->commit();
        } else {
            $workerPdo->rollBack();
        }
        rcPollMysqlSend($channel, ['type' => 'result', 'error' => $result['error']]);
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        if ($workerPdo instanceof PDO && $workerPdo->inTransaction()) {
            $workerPdo->rollBack();
        }
        exit(1);
    }
    exit(0);
}

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$pdo = rcPollMysqlConnect();
$engines = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('cms_polls','cms_poll_options','cms_poll_votes','cms_poll_vote_sessions')")->fetchAll();
$check(count($engines) === 4, 'All poll tables must exist.');
foreach ($engines as $table) {
    $check(strcasecmp((string)$table['ENGINE'], 'InnoDB') === 0, $table['TABLE_NAME'] . ' must use InnoDB.');
}

foreach (['closed' => 'closed', 'removed' => 'invalid_option', 'limit' => 'too_many_options', 'mode' => 'no_option',
    'duplicate' => 'already_voted', 'protected' => 'has_votes', 'valid' => ''] as $scenario => $expectedError) {
    $slug = 'rc-poll-mysql-' . bin2hex(random_bytes(12));
    $pollId = 0;
    $listener = $channel = $process = $workerLog = null;
    $failure = null;
    try {
        $pdo->prepare("INSERT INTO cms_polls (question,slug,status,vote_mode,max_choices) VALUES (?,?,'active','multiple',2)")->execute([$slug, $slug]);
        $pollId = (int)$pdo->lastInsertId();
        $optionIds = [];
        for ($index = 0; $index < 3; $index++) {
            $pdo->prepare('INSERT INTO cms_poll_options (poll_id,option_text,sort_order) VALUES (?,?,?)')->execute([$pollId, 'Choice ' . $index, $index]);
            $optionIds[] = (int)$pdo->lastInsertId();
        }
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorText);
        $check(is_resource($listener), 'Cannot open isolated worker listener.');
        $address = stream_socket_get_name($listener, false);
        $check(is_string($address), 'Missing loopback address.');
        $workerLog = tmpfile();
        $check(is_resource($workerLog), 'Cannot open worker diagnostics.');
        $pdo->beginTransaction();
        $check(pollLockForWrite($pdo, $pollId) !== null, 'Parent must own the poll lock.');
        $choices = $scenario === 'limit' ? array_slice($optionIds, 0, 2) : [$optionIds[0]];
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', (string)$pollId, $slug, $address, $scenario, implode(',', $choices)], [
            0 => ['pipe', 'r'], 1 => $workerLog, 2 => $workerLog,
        ], $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
        $check(is_resource($process), 'Cannot launch independent worker.');
        fclose($pipes[0]);
        $channel = stream_socket_accept($listener, 5);
        $check(is_resource($channel), 'Worker did not connect.');
        $ready = rcPollMysqlReceive($channel);
        $workerId = (int)($ready['connection_id'] ?? 0);
        $check(($ready['type'] ?? '') === 'ready' && $workerId > 0
            && $workerId !== (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn(), 'Worker needs its own database connection.');

        $inspection = $pdo->prepare('SELECT INFO FROM information_schema.PROCESSLIST WHERE ID = ?');
        $deadline = microtime(true) + 4;
        $observedWait = false;
        $waitingSince = null;
        while (microtime(true) < $deadline) {
            $read = [$channel];
            $write = $except = null;
            $check(stream_select($read, $write, $except, 0, 0) === 0, 'Worker must not return before the parent commit.');
            $inspection->execute([$workerId]);
            $query = (string)$inspection->fetchColumn();
            if (str_contains($query, 'cms_polls') && str_contains(strtoupper($query), 'FOR UPDATE')) {
                $waitingSince ??= microtime(true);
                if (microtime(true) - $waitingSince >= 0.1) {
                    $observedWait = true;
                    break;
                }
            } else {
                $waitingSince = null;
            }
            usleep(20000);
        }
        $check($observedWait, $scenario . ': must observe a real wait on the poll row lock.');
        if ($scenario === 'closed') {
            $pdo->prepare("UPDATE cms_polls SET status='closed' WHERE id=?")->execute([$pollId]);
        } elseif ($scenario === 'removed') {
            $pdo->prepare('DELETE FROM cms_poll_options WHERE id=? AND poll_id=?')->execute([$optionIds[0], $pollId]);
        } elseif ($scenario === 'limit') {
            $pdo->prepare('UPDATE cms_polls SET max_choices=1 WHERE id=?')->execute([$pollId]);
        } elseif ($scenario === 'mode') {
            $pdo->prepare("UPDATE cms_polls SET vote_mode='single' WHERE id=?")->execute([$pollId]);
        } elseif ($scenario === 'duplicate' || $scenario === 'protected') {
            $result = pollStoreVote($pdo, $pollId, null, [$optionIds[0]], hash('sha256', $slug));
            $check($result['error'] === '', 'Parent vote must succeed.');
        }
        $pdo->commit();
        $result = rcPollMysqlReceive($channel);
        $check(($result['type'] ?? '') === 'result' && ($result['error'] ?? null) === $expectedError, $scenario . ': worker must use current data, not its old snapshot.');
        $votesStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id=?');
        $votesStmt->execute([$pollId]);
        $expectedCount = in_array($scenario, ['duplicate', 'protected', 'valid'], true) ? 1 : 0;
        $check((int)$votesStmt->fetchColumn() === $expectedCount, $scenario . ': no orphan or duplicate vote.');
        $sessionsStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_poll_vote_sessions WHERE poll_id=?');
        $sessionsStmt->execute([$pollId]);
        $check((int)$sessionsStmt->fetchColumn() === $expectedCount, $scenario . ': no partial voter session.');
        $deadline = microtime(true) + 3;
        do {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $check(!$status['running'] && $status['exitcode'] === 0, 'Worker did not exit successfully.');
    } catch (Throwable $e) {
        $failure = $e->getMessage();
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
        foreach ([$channel, $listener] as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if ($pollId > 0) {
            try {
                $pdo->beginTransaction();
                $owner = pollLockForWrite($pdo, $pollId);
                $check($owner !== null && $owner['slug'] === $slug, 'Cleanup refused a foreign poll.');
                foreach (['cms_poll_votes', 'cms_poll_vote_sessions', 'cms_poll_options'] as $table) {
                    $pdo->prepare('DELETE FROM ' . $table . ' WHERE poll_id=?')->execute([$pollId]);
                }
                $pdo->prepare('DELETE FROM cms_polls WHERE id=? AND slug=?')->execute([$pollId, $slug]);
                $pdo->commit();
                $votesStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_polls WHERE id=?');
                $votesStmt->execute([$pollId]);
                $check((int)$votesStmt->fetchColumn() === 0, 'Fixture cleanup failed.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $failure = ($failure ?? '') . '; cleanup: ' . $e->getMessage();
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
        fwrite(STDERR, 'FAIL: ' . $scenario . ': ' . $failure . "\n");
        exit(1);
    }
}
echo 'OK: ' . $checks . " poll MySQL checks; seven real row-lock races; fixtures cleaned; no application mail.\n";
