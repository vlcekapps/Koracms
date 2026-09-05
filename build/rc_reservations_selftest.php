<?php

// Standalone: no application bootstrap, network, mail or configured database connection.
require_once __DIR__ . '/../lib/reservation_booking_validation.php';

date_default_timezone_set('Europe/Prague');
$checks = 0;
function rcReservationCheck(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

$resource = [
    'id' => 1, 'is_active' => 1, 'capacity' => 5, 'slot_mode' => 'slots',
    'slot_duration_min' => 60, 'max_concurrent' => 2, 'min_advance_hours' => 1,
    'max_advance_days' => 30, 'allow_guests' => 1, 'cancellation_hours' => 0,
];
$hours = ['open_time' => '09:00:00', 'close_time' => '17:00:00', 'is_closed' => 0];
$slots = [['start_time' => '09:00:00', 'end_time' => '10:00:00', 'max_bookings' => 3]];
$now = new DateTimeImmutable('2030-01-06 12:00:00');
$validate = static function (string $mode, string $start, ?string $end, array $overrides = [], bool $public = true) use ($resource, $hours, $slots, $now): array {
    return reservationValidateBookingTime(
        array_replace($resource, ['slot_mode' => $mode], $overrides),
        $hours,
        $slots,
        false,
        '2030-01-07',
        $start,
        $end,
        1,
        $public,
        $now
    );
};
rcReservationCheck($validate('slots', '09:00', '10:00')['limit'] === 3, 'actual slot uses its own capacity');
rcReservationCheck($validate('slots', '11:00', '12:00')['error'] === 'slot', 'invented slot inside opening hours rejected');
rcReservationCheck($validate('slots', '12:00', '11:00')['error'] !== '', 'reversed slot rejected');
rcReservationCheck($validate('slots', '09:00', '09:00')['error'] !== '', 'zero-length slot rejected');
rcReservationCheck($validate('range', '09:00', '17:00')['error'] === '', 'valid full opening range retained');
rcReservationCheck($validate('range', '09:30', '10:30')['error'] === '', 'valid range retained');
rcReservationCheck($validate('range', '09:17', '10:17')['error'] !== '', 'range outside offered grid rejected');
rcReservationCheck($validate('duration', '10:00', null)['end_time'] === '11:00:00', 'public duration end derived safely');
rcReservationCheck($validate('duration', '10:00', '11:00')['error'] === '', 'valid admin duration retained');
rcReservationCheck($validate('duration', '10:17', null)['error'] === 'duration', 'duration off grid rejected');
rcReservationCheck($validate('duration', '10:00', '12:00')['error'] === 'duration', 'wrong admin duration rejected');
rcReservationCheck($validate('duration', '10:00', null, ['slot_duration_min' => 0])['error'] !== '', 'invalid zero duration fails closed');
foreach (['slots', 'range', 'duration'] as $mode) {
    rcReservationCheck($validate($mode, '02:00', $mode === 'duration' ? null : '03:00')['error'] !== '', $mode . ' before opening rejected');
    rcReservationCheck($validate($mode, '17:00', $mode === 'duration' ? null : '18:00')['error'] !== '', $mode . ' after closing rejected');
    rcReservationCheck($validate($mode, '99:99', '99:99')['error'] !== '', $mode . ' malformed clock rejected without exception');
}
foreach (['2026-02-30', '2030-13-01', '2030-1-07', 'not-a-date'] as $date) {
    rcReservationCheck(reservationBookingDate($date) === null, 'invalid date rejected: ' . $date);
}
rcReservationCheck(reservationBookingDate('2028-02-29') !== null, 'leap date retained');
foreach (['24:00', '12:60', '09:00:01', "09:00\n", '9:00'] as $time) {
    rcReservationCheck(reservationBookingMinutes($time) === null, 'invalid or unoffered time rejected');
}
rcReservationCheck($validate('range', '09:00', '10:00', ['is_active' => 0])['error'] === 'resource', 'inactive resource rejected');
rcReservationCheck($validate('range', '09:00', '10:00', ['min_advance_hours' => 48])['error'] === 'advance', 'public minimum advance enforced');
rcReservationCheck($validate('range', '09:00', '10:00', ['max_advance_days' => 0])['error'] === 'advance', 'public maximum advance enforced');
rcReservationCheck($validate('range', '09:00', '10:00', ['min_advance_hours' => 48], false)['error'] === '', 'admin may override public advance window');
foreach ([null, array_replace($hours, ['is_closed' => 1])] as $closedHours) {
    rcReservationCheck(reservationValidateBookingTime($resource, $closedHours, $slots, false, '2030-01-07', '09:00', '10:00', 1, false)['error'] === 'closed', 'missing or closed hours rejected');
}
rcReservationCheck(reservationValidateBookingTime($resource, $hours, $slots, true, '2030-01-07', '09:00', '10:00', 1, false)['error'] === 'closed', 'blocked day rejected');
rcReservationCheck(reservationValidateBookingTime($resource, $hours, $slots, false, '2030-01-07', '09:00', '10:00', 6, false)['error'] === 'party_size', 'party capacity enforced');
$offsetHours = ['open_time' => '09:15:00', 'close_time' => '17:15:00', 'is_closed' => 0];
rcReservationCheck(reservationValidateBookingTime(array_replace($resource, ['slot_mode' => 'duration']), $offsetHours, [], false, '2030-01-07', '10:15', null, 1, false)['error'] === '', 'duration grid is anchored to opening rather than whole hour');
rcReservationCheck(reservationValidateBookingTime(array_replace($resource, ['slot_mode' => 'range']), ['open_time' => '01:00', 'close_time' => '04:00'], [], false, '2030-03-31', '02:00', '03:00', 1, false)['error'] === 'time', 'DST gap does not normalize into another booking time');

$adjacent = [
    ['start_time' => '09:00:00', 'end_time' => '10:00:00'],
    ['start_time' => '10:00:00', 'end_time' => '11:00:00'],
];
rcReservationCheck(reservationBookingPeakOverlap($adjacent, '09:00:00', '11:00:00') === 1, 'adjacent bookings occupy one concurrent place');
rcReservationCheck(reservationBookingPeakOverlap($adjacent, '11:00:00', '12:00:00') === 0, 'touching endpoint is not an overlap');
$overlapping = array_merge($adjacent, [['start_time' => '09:30:00', 'end_time' => '10:30:00']]);
rcReservationCheck(reservationBookingPeakOverlap($overlapping, '09:00:00', '11:00:00') === 2, 'true peak concurrency counted');
rcReservationCheck(reservationBookingPeakOverlap([['start_time' => '08:00:00', 'end_time' => '18:00:00']], '09:00:00', '11:00:00') === 1, 'covering interval clipped correctly');
rcReservationCheck(reservationBookingPeakOverlap([['start_time' => '09:00:00', 'end_time' => '11:00:00', 'status' => 'cancelled']], '09:00:00', '11:00:00') === 0, 'cancelled bookings release capacity');

$booking = ['status' => 'confirmed', 'booking_date' => '2030-01-07', 'start_time' => '12:00:00', 'cancellation_hours' => 0];
$startTs = strtotime('2030-01-07 12:00:00');
rcReservationCheck(reservationBookingCanBeCancelled($booking, $startTs - 1), 'zero-hour cancellation allowed just before start');
rcReservationCheck(!reservationBookingCanBeCancelled($booking, $startTs), 'zero-hour cancellation forbidden at start');
rcReservationCheck(!reservationBookingCanBeCancelled($booking, $startTs + 7200), 'token cancellation forbidden after start');
$booking['cancellation_hours'] = 2;
rcReservationCheck(reservationBookingCanBeCancelled($booking, $startTs - 7200), 'positive deadline inclusive');
rcReservationCheck(!reservationBookingCanBeCancelled($booking, $startTs - 7199), 'positive deadline expired');
foreach (['cancelled', 'completed', 'rejected', 'no_show'] as $status) {
    rcReservationCheck(!reservationBookingCanBeCancelled(array_replace($booking, ['status' => $status]), $startTs - 86400), 'terminal status cannot be cancelled: ' . $status);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('pdo_sqlite is required for isolated reservation transaction fixtures.');
}

// SQLite executes the production queries in memory. Only MySQL lock syntax is adapted;
// the lock order/transaction contract is checked here, not InnoDB contention behavior.
final class RcReservationFixturePdo extends PDO
{
    /** @var list<string> */
    public array $lockingReads = [];

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
    }

    public function beginTransaction(): bool
    {
        $this->lockingReads = [];
        return parent::beginTransaction();
    }

    /** @param array<int,mixed> $options */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'FOR UPDATE')) {
            rcReservationCheck($this->inTransaction(), 'every locking read is inside the transaction');
            if ($this->lockingReads === []) {
                rcReservationCheck(str_contains($query, 'FROM cms_res_resources WHERE id = ?'), 'resource row is locked before schedule or bookings');
            }
            $this->lockingReads[] = $query;
            $query = str_replace(' FOR UPDATE', '', $query);
        }
        return parent::prepare($query, $options);
    }
}

$pdo = new RcReservationFixturePdo();
$pdo->exec('CREATE TABLE cms_res_resources (id INTEGER PRIMARY KEY, is_active INTEGER, capacity INTEGER, slot_mode TEXT, slot_duration_min INTEGER, max_concurrent INTEGER, min_advance_hours INTEGER, max_advance_days INTEGER, allow_guests INTEGER, cancellation_hours INTEGER)');
$pdo->exec('CREATE TABLE cms_res_hours (id INTEGER PRIMARY KEY, resource_id INTEGER, day_of_week INTEGER, open_time TEXT, close_time TEXT, is_closed INTEGER)');
$pdo->exec('CREATE TABLE cms_res_slots (id INTEGER PRIMARY KEY, resource_id INTEGER, day_of_week INTEGER, start_time TEXT, end_time TEXT, max_bookings INTEGER)');
$pdo->exec('CREATE TABLE cms_res_blocked (id INTEGER PRIMARY KEY, resource_id INTEGER, blocked_date TEXT)');
$pdo->exec('CREATE TABLE cms_res_bookings (id INTEGER PRIMARY KEY AUTOINCREMENT, resource_id INTEGER, user_id INTEGER, booking_date TEXT, start_time TEXT, end_time TEXT, status TEXT, confirmation_token TEXT, calendar_token TEXT, admin_note TEXT, cancelled_at TEXT, updated_at TEXT)');
$pdo->exec("INSERT INTO cms_res_resources VALUES (1, 1, 5, 'range', 60, 2, 0, 30, 1, 0)");
$fixtureDate = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$fixtureDow = (int)(new DateTimeImmutable($fixtureDate))->format('N') - 1;
$pdo->prepare("INSERT INTO cms_res_hours VALUES (1, 1, ?, '09:00:00', '17:00:00', 0)")->execute([$fixtureDow]);
$pdo->prepare("INSERT INTO cms_res_slots VALUES (1, 1, ?, '09:00:00', '10:00:00', 2)")->execute([$fixtureDow]);
$insertFixture = static function (string $start, string $end, string $status = 'confirmed', ?string $date = null) use ($pdo, $fixtureDate): int {
    $pdo->prepare('INSERT INTO cms_res_bookings (resource_id, user_id, booking_date, start_time, end_time, status, confirmation_token) VALUES (1, 7, ?, ?, ?, ?, ?)')
        ->execute([$date ?? $fixtureDate, $start, $end, $status, str_repeat('a', 32)]);
    return (int)$pdo->lastInsertId();
};
$validateInsert = static function (string $start, ?string $end, bool $public = true, bool $guest = false) use ($pdo, $fixtureDate): array {
    return reservationValidateBookingInsert($pdo, 1, $fixtureDate, $start, $end, 1, $public, $guest);
};
try {
    $validateInsert('09:00', '10:00');
    rcReservationCheck(false, 'unlocked validation must throw');
} catch (LogicException) {
    rcReservationCheck(true, 'unlocked validation refused');
}
foreach ([true, false] as $public) {
    $pdo->exec('DELETE FROM cms_res_bookings');
    $insertFixture('09:00:00', '10:00:00');
    $insertFixture('10:00:00', '11:00:00');
    $pdo->beginTransaction();
    $result = $validateInsert('09:00', '11:00', $public);
    rcReservationCheck($result['error'] === '', 'public/admin accepts free peak capacity across adjacent bookings');
    rcReservationCheck($pdo->inTransaction(), 'validation keeps resource lock held for INSERT');
    rcReservationCheck(count($pdo->lockingReads) === 5, 'resource, schedule, blocked dates, slots and bookings use current locking reads');
    $insertFixture($result['start_time'], $result['end_time']);
    $pdo->commit();
    $pdo->beginTransaction();
    rcReservationCheck($validateInsert('09:00', '11:00', $public)['error'] === 'capacity', 'next serialized writer sees committed capacity');
    $pdo->rollBack();
}
$pdo->exec('DELETE FROM cms_res_bookings');
$pdo->exec("UPDATE cms_res_resources SET slot_mode = 'slots', max_concurrent = 1");
foreach ([true, false] as $public) {
    $pdo->beginTransaction();
    $result = $validateInsert('09:00', '10:00', $public);
    rcReservationCheck($result['error'] === '' && $result['limit'] === 2, 'public/admin uses configured actual-slot capacity');
    $insertFixture($result['start_time'], $result['end_time']);
    $pdo->commit();
}
$pdo->beginTransaction();
rcReservationCheck($validateInsert('09:00', '10:00', false)['error'] === 'capacity', 'third admin slot booking rejected');
rcReservationCheck($validateInsert('11:00', '12:00')['error'] === 'slot', 'SQL schedule lookup cannot invent a slot');
$pdo->rollBack();
$pdo->exec('DELETE FROM cms_res_bookings');
$pdo->exec("UPDATE cms_res_resources SET slot_mode = 'duration'");
$pdo->beginTransaction();
rcReservationCheck($validateInsert('10:00', null)['end_time'] === '11:00:00', 'duration normalization runs through production transaction helper');
$insertFixture('10:00:00', '11:00:00');
$pdo->rollBack();
rcReservationCheck((int)$pdo->query('SELECT COUNT(*) FROM cms_res_bookings')->fetchColumn() === 0, 'failed transaction leaves no inserted reservation');
$pdo->prepare('INSERT INTO cms_res_blocked VALUES (1, 1, ?)')->execute([$fixtureDate]);
$pdo->beginTransaction();
rcReservationCheck($validateInsert('10:00', null, false)['error'] === 'closed', 'admin cannot bypass blocked day');
$pdo->rollBack();
$pdo->exec('DELETE FROM cms_res_blocked');
$pdo->exec('UPDATE cms_res_resources SET allow_guests = 0');
$pdo->beginTransaction();
rcReservationCheck($validateInsert('10:00', null, true, true)['error'] === 'guest', 'guest permission rechecked on locked resource');
$pdo->rollBack();
$pdo->exec('UPDATE cms_res_resources SET is_active = 0');
$pdo->beginTransaction();
rcReservationCheck($validateInsert('10:00', null, false)['error'] === 'resource', 'admin cannot book inactive resource');
$pdo->rollBack();
$pdo->exec('UPDATE cms_res_resources SET is_active = 1');

$bookingId = $insertFixture('09:00:00', '10:00:00', 'pending');
$staleBooking = $pdo->query('SELECT * FROM cms_res_bookings WHERE id = ' . $bookingId)->fetch();
rcReservationCheck(reservationCancelBooking($pdo, $staleBooking, 7), 'owner cancellation commits before stale approval');
rcReservationCheck(!reservationCompareAndSetBookingStatus($pdo, $bookingId, 'pending', 'confirmed', 'stale note', 'stale-token'), 'stale approval cannot revive cancellation');
$current = $pdo->query('SELECT * FROM cms_res_bookings WHERE id = ' . $bookingId)->fetch();
rcReservationCheck($current['status'] === 'cancelled' && $current['admin_note'] === null && $current['calendar_token'] === null, 'failed CAS changes neither status nor note/token');
rcReservationCheck(!reservationCancelBooking($pdo, $staleBooking, 7), 'replayed cancellation does not report success');
$bookingId = $insertFixture('09:00:00', '10:00:00', 'pending');
rcReservationCheck(reservationCompareAndSetBookingStatus($pdo, $bookingId, 'pending', 'confirmed', 'approved', 'calendar'), 'valid approval succeeds');
rcReservationCheck(!reservationCompareAndSetBookingStatus($pdo, $bookingId, 'pending', 'confirmed'), 'duplicate approval loses CAS');
$current = $pdo->query('SELECT * FROM cms_res_bookings WHERE id = ' . $bookingId)->fetch();
rcReservationCheck(!reservationCancelBooking($pdo, $current, 8), 'different owner cannot cancel');
rcReservationCheck(!reservationCancelBooking($pdo, $current, null, str_repeat('b', 32)), 'wrong token cannot cancel');
rcReservationCheck(reservationCancelBooking($pdo, $current, null, str_repeat('a', 32)), 'valid token cancellation succeeds before start');
$bookingId = $insertFixture('09:00:00', '10:00:00', 'confirmed', (new DateTimeImmutable('yesterday'))->format('Y-m-d'));
$current = $pdo->query('SELECT * FROM cms_res_bookings WHERE id = ' . $bookingId)->fetch();
rcReservationCheck(!reservationCancelBooking($pdo, $current, null, str_repeat('a', 32)), 'production token mutation rejects past start with zero deadline');
rcReservationCheck($pdo->query('SELECT status FROM cms_res_bookings WHERE id = ' . $bookingId)->fetchColumn() === 'confirmed', 'expired token leaves stored state untouched');
rcReservationCheck(!$pdo->inTransaction(), 'failed cancellation releases transaction');

foreach (['reservations/book.php', 'admin/res_booking_add.php'] as $path) {
    $source = (string)file_get_contents(__DIR__ . '/../' . $path);
    rcReservationCheck(str_contains($source, "require_once __DIR__ . '/../lib/reservation_booking_validation.php';"), $path . ' loads production helper without a bootstrap change');
    rcReservationCheck(strpos($source, 'reservationValidateBookingInsert(') < strpos($source, 'INSERT INTO cms_res_bookings'), $path . ' validates before INSERT');
}
$saveSource = (string)file_get_contents(__DIR__ . '/../admin/res_booking_save.php');
rcReservationCheck(str_contains($saveSource, 'if (!reservationCompareAndSetBookingStatus('), 'admin handler gates side effects on successful CAS');
foreach (['reservations/cancel.php', 'reservations/cancel_booking.php'] as $path) {
    rcReservationCheck(str_contains((string)file_get_contents(__DIR__ . '/../' . $path), 'if (!reservationCancelBooking('), $path . ' gates notifications on committed cancellation');
}

$_SESSION = [];
$draftNote = "<script>alert('audit')</script>\nRozepsaná poznámka";
reservationRememberStatusConflict(41, 'reject', $draftNote);
rcReservationCheck(reservationTakeStatusConflict(42) === null, 'conflict note never appears on another booking');
rcReservationCheck(reservationTakeStatusConflict(41) === ['action' => 'reject', 'admin_note' => $draftNote], 'conflict preserves exact note and action');
rcReservationCheck(reservationTakeStatusConflict(41) === null, 'conflict flash consumed once');

function rcReservationSourceFragment(string $source, string $start, string $end): string
{
    $from = strpos($source, $start);
    $until = $from !== false ? strpos($source, $end, $from) : false;
    if ($from === false || $until === false) {
        throw new RuntimeException('Production conflict view fragment not found.');
    }
    return substr($source, $from, $until - $from);
}
if (!function_exists('h')) {
    function h(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
$detailSource = (string)file_get_contents(__DIR__ . '/../admin/res_booking_detail.php');
$id = 41;
$_GET = ['error' => 'status_conflict', 'action' => 'reject', 'ok' => '1'];
reservationRememberStatusConflict($id, 'reject', $draftNote);
$statusActionErrorMessage = '';
$statusConflictNote = '';
eval(rcReservationSourceFragment($detailSource, '$statusActionErrorCode =', '$statusActionFormErrorAttributes ='));
ob_start();
eval('?>' . rcReservationSourceFragment($detailSource, "<?php if (isset(\$_GET['ok'])", '<p><a href='));
$conflictHtml = (string)ob_get_clean();
$dom = new DOMDocument();
$dom->loadHTML('<?xml encoding="UTF-8">' . $conflictHtml, LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($dom);
rcReservationCheck($xpath->query('//*[@role="alert" and @aria-atomic="true" and @aria-labelledby="reservation-status-error"]')->length === 1, 'conflict is announced by existing accessible alert');
rcReservationCheck($xpath->query('//*[@id="reservation-status-error"]')->length === 1, 'conflict alert references an existing unique message');
rcReservationCheck($xpath->query('//*[@role="status"]')->length === 0, 'conflict cannot simultaneously display success');
$draftTextarea = $xpath->query('//textarea[@id="reservation-conflict-note" and @readonly]')->item(0);
rcReservationCheck($draftTextarea !== null && $draftTextarea->textContent === $draftNote, 'exact escaped draft remains available even when original action disappeared');
rcReservationCheck($xpath->query('//label[@for="reservation-conflict-note"]')->length === 1, 'preserved note has a real label');
rcReservationCheck($xpath->query('//*[@id="reservation-conflict-note-help"]')->length === 1, 'preserved note help exists');
rcReservationCheck($xpath->query('//script')->length === 0, 'preserved note cannot inject markup');
rcReservationCheck(substr_count($saveSource, '$redirectStatusConflict();') === 2, 'both stale initial state and lost CAS produce explicit conflict');
rcReservationCheck(str_contains($detailSource, '$statusActionError && $statusActionErrorAction === $action'), 'state conflict is not mislabeled as missing checkbox confirmation');
echo 'OK: ' . $checks . " reservation checks; isolated SQLite transactions; no application DB/mail.\n";
