<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// No application bootstrap: these fixtures must never send mail or notifications.
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/reservation_booking_validation.php';

function rcReservationMysqlConnect(): PDO
{
    global $server, $user, $pass, $database;
    $pdo = new PDO("mysql:host={$server};dbname={$database};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 8');
    $pdo->exec('SET SESSION lock_wait_timeout = 8');
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    return $pdo;
}

/**
 * @param resource $channel
 * @param array<string,mixed> $message
 */
function rcReservationMysqlSend($channel, array $message): void
{
    $line = json_encode($message, JSON_THROW_ON_ERROR) . "\n";
    if (fwrite($channel, $line) !== strlen($line)) {
        throw new RuntimeException('Incomplete worker signal.');
    }
}

/**
 * @param resource $channel
 * @return array<string,mixed>
 */
function rcReservationMysqlReceive($channel, float $deadline): array
{
    stream_set_blocking($channel, false);
    $buffer = '';
    while (microtime(true) < $deadline) {
        $read = [$channel];
        $write = $except = null;
        $readable = stream_select($read, $write, $except, 0, 100000);
        if ($readable === false) {
            throw new RuntimeException('Worker signal polling failed.');
        }
        if ($readable > 0) {
            $chunk = fread($channel, 4096);
            if ($chunk === false || ($chunk === '' && feof($channel))) {
                throw new RuntimeException('Worker disconnected before its result.');
            }
            $buffer .= $chunk;
            if (strlen($buffer) > 8192) {
                throw new RuntimeException('Oversized worker signal.');
            }
            if (str_contains($buffer, "\n")) {
                $message = json_decode(trim($buffer), true, 16, JSON_THROW_ON_ERROR);
                if (!is_array($message)) {
                    throw new RuntimeException('Invalid worker signal.');
                }
                return $message;
            }
        }
    }
    throw new RuntimeException('Timed out waiting for worker signal.');
}

/** @param resource $channel */
function rcReservationMysqlAssertWaiting($channel): void
{
    $read = [$channel];
    $write = $except = null;
    if (stream_select($read, $write, $except, 0, 0) !== 0) {
        throw new RuntimeException('Worker returned or disconnected before parent commit.');
    }
}

/** @param list<string> $arguments */
function rcReservationMysqlWorker(array $arguments): int
{
    $pdo = null;
    $channel = null;
    try {
        if (count($arguments) !== 6
            || !ctype_digit($arguments[2])
            || reservationBookingDate($arguments[3]) === null
            || preg_match('/^rc-reservation-mysql-[a-f0-9]{24}$/D', $arguments[4]) !== 1
            || preg_match('/^127\.0\.0\.1:[0-9]+$/D', $arguments[5]) !== 1) {
            throw new RuntimeException('Invalid isolated worker arguments.');
        }
        $channel = stream_socket_client('tcp://' . $arguments[5], $errorCode, $errorText, 5);
        if ($channel === false) {
            throw new RuntimeException('Cannot connect worker signal channel.');
        }
        stream_set_timeout($channel, 5);
        $pdo = rcReservationMysqlConnect();
        $resourceId = (int)$arguments[2];
        $owner = $pdo->prepare('SELECT slug FROM cms_res_resources WHERE id = ?');
        $owner->execute([$resourceId]);
        if ($owner->fetchColumn() !== $arguments[4]) {
            throw new RuntimeException('Worker resource is not its isolated fixture.');
        }
        $pdo->beginTransaction();
        // Establish an empty REPEATABLE READ snapshot before the parent inserts.
        $initialCount = (int)$pdo->query('SELECT COUNT(*) FROM cms_res_bookings WHERE resource_id = ' . $resourceId)->fetchColumn();
        if ($initialCount !== 0) {
            throw new RuntimeException('Worker snapshot must start with an empty booking day.');
        }
        rcReservationMysqlSend($channel, [
            'type' => 'ready',
            'slug' => $arguments[4],
            'initial_count' => $initialCount,
            'connection_id' => (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn(),
        ]);
        $started = microtime(true);
        $validation = reservationValidateBookingInsert($pdo, $resourceId, $arguments[3], '09:00', '10:00', 1, true, true);
        $pdo->rollBack();
        rcReservationMysqlSend($channel, [
            'type' => 'result',
            'error' => $validation['error'],
            'wait_ms' => (int)round((microtime(true) - $started) * 1000),
        ]);
        return $validation['error'] === 'capacity' ? 0 : 1;
    } catch (Throwable $exception) {
        if (is_resource($channel)) {
            rcReservationMysqlSend($channel, ['type' => 'failure', 'message' => $exception->getMessage()]);
        }
        fwrite(STDERR, $exception->getMessage() . "\n");
        return 1;
    } finally {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_resource($channel)) {
            fclose($channel);
        }
    }
}

if (($argv[1] ?? '') === '--worker') {
    exit(rcReservationMysqlWorker($argv));
}

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$pdo = null;
$resourceId = 0;
$listener = $channel = $process = $workerLog = null;
$failure = null;
$slug = 'rc-reservation-mysql-' . bin2hex(random_bytes(12));
$fixtureDay = (new DateTimeImmutable('today'))->modify('+7 days');
$fixtureDate = $fixtureDay->format('Y-m-d');

try {
    $pdo = rcReservationMysqlConnect();
    $engines = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN
        ('cms_res_resources', 'cms_res_hours', 'cms_res_slots', 'cms_res_blocked', 'cms_res_bookings')")->fetchAll();
    $check(count($engines) === 5, 'All five reservation tables must exist.');
    foreach ($engines as $table) {
        $check(strcasecmp((string)$table['ENGINE'], 'InnoDB') === 0, $table['TABLE_NAME'] . ' must use InnoDB.');
    }
    // Unique rows only; do not nest the global suite lock while module-ready runs.
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO cms_res_resources
        (name, slug, capacity, slot_mode, min_advance_hours, max_advance_days, max_concurrent,
         allow_guests, reminders_enabled, calendar_invite_enabled, is_active)
        VALUES (?, ?, 1, 'slots', 0, 30, 1, 1, 0, 0, 1)")->execute([$slug, $slug]);
    $resourceId = (int)$pdo->lastInsertId();
    $dayOfWeek = (int)$fixtureDay->format('N') - 1;
    $pdo->prepare("INSERT INTO cms_res_hours (resource_id, day_of_week, open_time, close_time, is_closed)
        VALUES (?, ?, '09:00:00', '10:00:00', 0)")->execute([$resourceId, $dayOfWeek]);
    $pdo->prepare("INSERT INTO cms_res_slots (resource_id, day_of_week, start_time, end_time, max_bookings)
        VALUES (?, ?, '09:00:00', '10:00:00', 1)")->execute([$resourceId, $dayOfWeek]);
    $pdo->commit();

    $listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorText);
    $check(is_resource($listener), 'Cannot open loopback worker signal listener.');
    $address = stream_socket_get_name($listener, false);
    $check(is_string($address), 'Cannot determine loopback listener address.');
    $workerLog = tmpfile();
    $check(is_resource($workerLog), 'Cannot open temporary worker diagnostic stream.');

    $pdo->beginTransaction();
    $count = $pdo->prepare('SELECT COUNT(*) FROM cms_res_bookings WHERE resource_id = ?');
    $count->execute([$resourceId]);
    $check((int)$count->fetchColumn() === 0, 'Fixture must start without any bookings.');
    $validation = reservationValidateBookingInsert($pdo, $resourceId, $fixtureDate, '09:00', '10:00', 1, false);
    $check($validation['error'] === '' && $validation['limit'] === 1, 'Parent must validate the offered slot with capacity one.');

    $process = proc_open([PHP_BINARY, __FILE__, '--worker', (string)$resourceId, $fixtureDate, $slug, $address], [
        0 => ['pipe', 'r'], 1 => $workerLog, 2 => $workerLog,
    ], $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
    $check(is_resource($process), 'Cannot launch independent PHP worker.');
    fclose($pipes[0]);
    $channel = stream_socket_accept($listener, 5);
    $check(is_resource($channel), 'Worker did not connect within five seconds.');
    $ready = rcReservationMysqlReceive($channel, microtime(true) + 5);
    $check(($ready['type'] ?? '') === 'ready' && ($ready['slug'] ?? '') === $slug, 'Worker did not acknowledge its own fixture.');
    $check(($ready['initial_count'] ?? null) === 0, 'Worker must establish an empty snapshot before waiting.');
    $workerConnectionId = (int)($ready['connection_id'] ?? 0);
    $check($workerConnectionId > 0 && $workerConnectionId !== (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn(), 'Worker must use a separate MySQL connection.');

    $inspection = $pdo->prepare('SELECT INFO FROM information_schema.PROCESSLIST WHERE ID = ?');
    $deadline = microtime(true) + 4;
    $waitingSince = null;
    $observedWait = false;
    while (microtime(true) < $deadline) {
        rcReservationMysqlAssertWaiting($channel);
        $inspection->execute([$workerConnectionId]);
        $query = (string)$inspection->fetchColumn();
        if (str_contains($query, 'cms_res_resources') && str_contains(strtoupper($query), 'FOR UPDATE')) {
            $waitingSince ??= microtime(true);
            if (microtime(true) - $waitingSince >= 0.25) {
                $observedWait = true;
                break;
            }
        } else {
            $waitingSince = null;
        }
        usleep(20000);
    }
    $check($observedWait, 'Worker must be observed waiting on the resource FOR UPDATE before the first booking exists.');
    $pdo->prepare("INSERT INTO cms_res_bookings
        (resource_id, guest_name, booking_date, start_time, end_time, party_size, status)
        VALUES (?, ?, ?, ?, ?, 1, 'confirmed')")->execute([
            $resourceId, $slug, $fixtureDate, $validation['start_time'], $validation['end_time'],
        ]);
    rcReservationMysqlAssertWaiting($channel);
    $pdo->commit();

    $result = rcReservationMysqlReceive($channel, microtime(true) + 5);
    $check(($result['type'] ?? '') === 'result' && ($result['error'] ?? '') === 'capacity', 'After commit the worker must reject capacity, not allow a second booking: ' . json_encode($result));
    $check((int)($result['wait_ms'] ?? 0) >= 250, 'Worker did not spend the observed interval inside the production validator.');
    $count->execute([$resourceId]);
    $check((int)$count->fetchColumn() === 1, 'Exactly the parent booking must remain.');
    $deadline = microtime(true) + 3;
    do {
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        usleep(20000);
    } while (microtime(true) < $deadline);
    $check(!$status['running'] && $status['exitcode'] === 0, 'Worker must exit successfully within three seconds.');
} catch (Throwable $exception) {
    $failure = $exception->getMessage();
} finally {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
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
    if ($pdo instanceof PDO && $resourceId > 0) {
        try {
            $pdo->beginTransaction();
            $owner = $pdo->prepare('SELECT slug FROM cms_res_resources WHERE id = ? FOR UPDATE');
            $owner->execute([$resourceId]);
            $currentSlug = $owner->fetchColumn();
            $check($currentSlug === false || $currentSlug === $slug, 'Cleanup refused a resource not owned by this test.');
            if ($currentSlug === $slug) {
                foreach (['cms_res_bookings', 'cms_res_slots', 'cms_res_hours'] as $table) {
                    $pdo->prepare('DELETE FROM ' . $table . ' WHERE resource_id = ?')->execute([$resourceId]);
                }
                $pdo->prepare('DELETE FROM cms_res_resources WHERE id = ? AND slug = ?')->execute([$resourceId, $slug]);
            }
            $pdo->commit();
            foreach (['cms_res_bookings', 'cms_res_slots', 'cms_res_hours', 'cms_res_resources'] as $table) {
                $key = $table === 'cms_res_resources' ? 'id' : 'resource_id';
                $remaining = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $key . ' = ?');
                $remaining->execute([$resourceId]);
                $check((int)$remaining->fetchColumn() === 0, 'Fixture cleanup left rows in ' . $table . '.');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
    fwrite(STDERR, 'FAIL: ' . $failure . "\n");
    exit(1);
}
echo 'OK: ' . $checks . " reservation MySQL checks; real InnoDB resource-lock wait; worker=capacity after commit; fixtures cleaned; no application mail.\n";
