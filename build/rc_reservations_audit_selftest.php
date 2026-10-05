<?php

declare(strict_types=1);

namespace KoraReservationsAuditTest;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Execute production handlers and views without application bootstrap, network or mail.
// Reservation tables come from install.php, retaining its NOT NULL and unique constraints.
define('BASE_URL', '');
date_default_timezone_set('Europe/Prague');
$checks = 0;

final class Response extends RuntimeException
{
}

function same(mixed $actual, mixed $expected, string $label): void
{
    $GLOBALS['checks']++;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': ' . var_export($actual, true));
    }
}

function source(string $path): string
{
    $code = file_get_contents(dirname(__DIR__) . '/' . $path);
    if ($code === false) {
        throw new RuntimeException('Missing production source: ' . $path);
    }
    return $code;
}

function evaluate(string $code, array $variables = []): mixed
{
    extract($variables, EXTR_SKIP);
    return eval('namespace ' . __NAMESPACE__ . '; use \\PDO; use \\DateTime; use \\DateTimeImmutable; use \\Throwable; use \\LogicException; ' . $code);
}

function loadFunction(string $path, string $name): void
{
    if (preg_match('/^function ' . preg_quote($name, '/') . '\b.*?^\}/ms', source($path), $match) !== 1) {
        throw new RuntimeException('Missing production function: ' . $name);
    }
    evaluate($match[0]);
}

function runFile(string $path, array $variables = []): string
{
    $code = preg_replace('/^require_once [^\r\n]+;\R/m', '', source($path));
    // Intercept redirects, but do not turn a successful header/exit into a save failure.
    $code = preg_replace_callback('/catch\s*\((?:\\\\)?Throwable\s+(\$\w+)\)\s*\{/', static function (array $match): string {
        return $match[0] . ' if (' . $match[1] . ' instanceof \\KoraReservationsAuditTest\\Response) { throw ' . $match[1] . '; }';
    }, $code);
    ob_start();
    try {
        evaluate('?>' . $code, $variables);
        return (string)ob_get_contents();
    } catch (Response $response) {
        return $response->getMessage();
    } finally {
        ob_end_clean();
    }
}

function header(string $value): void
{
    if (str_starts_with($value, 'Location: ')) {
        throw new Response(substr($value, 10));
    }
}
function db_connect(): PDO
{
    return $GLOBALS['fixtureDb'];
}
function requireCapability(string $capability, string $message = ''): void
{
    if (!$GLOBALS['authorized']) {
        throw new Response('forbidden');
    }
}
function requireModuleEnabled(string $module): void
{
    if (!isModuleEnabled($module)) {
        throw new Response('module-disabled');
    }
}
function isModuleEnabled(string $module): bool
{
    return $GLOBALS['moduleEnabled'];
}
function verifyCsrf(): void
{
    if (($_POST['csrf_token'] ?? '') !== csrfToken()) {
        throw new Response('csrf-rejected');
    }
}
function csrfToken(): string
{
    return 'isolated-csrf';
}
function currentUserId(): int
{
    return 7;
}
function requirePublicLogin(string $redirect = ''): void
{
}
function internalRedirectTarget(string $url, string $fallback): string
{
    return str_starts_with($url, '/') && !str_starts_with($url, '//') ? $url : $fallback;
}
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function adminHeader(string $title): void
{
}
function adminFooter(): void
{
}
function autoCompleteBookings(): void
{
    // Automatic lifecycle processing is outside this handler regression's scope.
}
function checkMaintenanceMode(): void
{
}
function getSetting(string $key, string $fallback = ''): string
{
    return $fallback;
}
function honeypotTriggered(): bool
{
    return false;
}
function honeypotField(): string
{
    return '';
}
function rateLimit(string $key, int $limit, int $seconds): void
{
}
function cspNonce(): string
{
    return 'isolated-nonce';
}
function formatCzechDate(string $date): string
{
    return $date;
}
function siteUrl(string $path): string
{
    return 'https://example.test' . $path;
}
function reservationResourceSlug(string $value): string
{
    return $value;
}
function reservationResourcePublicPath(array $resource): string
{
    return '/reservations/resource.php?slug=' . rawurlencode((string)$resource['resource_slug']);
}
function newWindowLinkSrOnlySuffix(): string
{
    return ' (new window)';
}
function logAction(string $action, string $detail): void
{
    $GLOBALS['effects'][] = ['log', $action, $detail];
}
function koraLog(string $level, string $message, array $context = []): void
{
    $GLOBALS['failures'][] = [$level, $message];
}
function reservationBookingForNotification(PDO $pdo, int $bookingId): ?array
{
    $stmt = $pdo->prepare('SELECT b.*, r.name AS resource_name, r.calendar_invite_enabled,
        u.email AS user_email FROM cms_res_bookings b
        JOIN cms_res_resources r ON r.id = b.resource_id
        LEFT JOIN cms_users u ON u.id = b.user_id WHERE b.id = ?');
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}
function reservationSendMail(array $booking, string $subject, string $body, string $notification, bool $includeCalendar = true): bool
{
    $GLOBALS['effects'][] = ['mail', (int)$booking['id'], $notification];
    return true;
}
function renderPublicPage(array $page): void
{
    $GLOBALS['renderedPage'] = $page;
    echo runFile('themes/default/views/' . $page['view'] . '.php', $page['view_data']);
}

final class FixturePdo extends PDO
{
    public array $lockingReads = [];

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => getenv('KORA_RC_STRINGIFY_FETCHES') === '1',
        ]);
        $this->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $this->sqliteCreateFunction('CONCAT', static fn (...$parts): ?string => in_array(null, $parts, true) ? null : implode('', $parts));
        $this->sqliteCreateFunction('CONCAT_WS', static fn ($separator, ...$parts): string => implode($separator, array_filter($parts, static fn ($part): bool => $part !== null)));
    }

    public function beginTransaction(): bool
    {
        $this->lockingReads = [];
        return parent::beginTransaction();
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'FOR UPDATE')) {
            same($this->inTransaction(), true, 'locking read requires a transaction');
            if ($this->lockingReads === []) {
                same(str_contains($query, 'FROM cms_res_resources WHERE id = ?'), true, 'resource lock remains first');
            }
            $this->lockingReads[] = $query;
            $query = str_replace(' FOR UPDATE', '', $query);
        }
        if (str_contains($query, 'INSERT INTO cms_res_bookings')) {
            same($this->inTransaction(), true, 'handler INSERT retains the validation transaction');
            same(count($this->lockingReads), 5, 'handler INSERT retains all five locking reads');
        }
        return parent::prepare($query, $options);
    }
}

function canonicalSchema(string $table): string
{
    if (preg_match('/CREATE TABLE IF NOT EXISTS ' . preg_quote($table, '/') . ' \((.*?)\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4/s', source('install.php'), $match) !== 1) {
        throw new RuntimeException('Canonical table not found: ' . $table);
    }
    $body = preg_replace('/\bINT\s+NOT NULL AUTO_INCREMENT PRIMARY KEY/', 'INTEGER PRIMARY KEY AUTOINCREMENT', $match[1]);
    $body = preg_replace('/\bENUM\([^)]*\)|\bVARCHAR\(\d+\)|\bDATETIME\b|\bDATE\b|\bTIME\b/', 'TEXT', $body);
    $body = preg_replace('/\b(?:INT|TINYINT|BIGINT)(?:\(\d+\))?(?!\w)/', 'INTEGER', $body);
    $body = preg_replace('/ ON UPDATE CURRENT_TIMESTAMP/', '', $body);
    $body = preg_replace('/^\s*INDEX[^\r\n]*\R?/m', '', $body);
    $body = preg_replace('/UNIQUE KEY \w+\s*\(/', 'UNIQUE (', $body);
    return 'CREATE TABLE ' . $table . ' (' . rtrim($body, " \t\r\n,") . ')';
}

function fixture(): FixturePdo
{
    $pdo = new FixturePdo();
    foreach (['cms_res_resources', 'cms_res_hours', 'cms_res_slots', 'cms_res_blocked', 'cms_res_locations',
        'cms_res_resource_locations', 'cms_res_bookings', 'cms_res_booking_events'] as $table) {
        $pdo->exec(canonicalSchema($table));
    }
    $pdo->exec("CREATE TABLE cms_users (id INTEGER PRIMARY KEY, email TEXT, first_name TEXT, last_name TEXT, phone TEXT, nickname TEXT);
        INSERT INTO cms_users VALUES (7, 'user@example.test', 'Fixture', 'User', '', 'fixture');
        INSERT INTO cms_res_resources (id, name, slug, capacity, slot_mode, max_concurrent, min_advance_hours, allow_guests)
        VALUES (1, 'Room', 'room-1', 5, 'range', 1, 0, 1), (2, 'Other room', 'room-2', 5, 'range', 1, 0, 1);");
    for ($day = 0; $day < 7; $day++) {
        $pdo->prepare("INSERT INTO cms_res_hours (resource_id, day_of_week, open_time, close_time) VALUES (1, ?, '09:00:00', '11:00:00')")->execute([$day]);
    }
    $GLOBALS['fixtureDb'] = $pdo;
    $GLOBALS['authorized'] = true;
    $GLOBALS['moduleEnabled'] = true;
    $GLOBALS['effects'] = [];
    $GLOBALS['failures'] = [];
    $GLOBALS['renderedPage'] = null;
    $_SESSION = ['cms_user_id' => 7];
    $_GET = [];
    $_POST = ['csrf_token' => csrfToken()];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    return $pdo;
}

function tomorrow(): string
{
    return (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
}

function booking(PDO $pdo, array $overrides = []): int
{
    $values = array_replace([
        'resource_id' => 1, 'user_id' => 7, 'guest_name' => 'Host', 'guest_email' => 'host@example.test',
        'booking_date' => tomorrow(), 'start_time' => '09:00:00', 'end_time' => '10:00:00',
        'status' => 'confirmed', 'confirmation_token' => str_repeat('a', 32), 'calendar_token' => bin2hex(random_bytes(16)),
    ], $overrides);
    $pdo->exec('INSERT INTO cms_res_bookings (' . implode(', ', array_keys($values)) . ') VALUES ('
        . implode(', ', array_map(static fn ($value): string => $value === null ? 'NULL' : $pdo->quote((string)$value), array_values($values))) . ')');
    return (int)$pdo->lastInsertId();
}

function snapshot(PDO $pdo): array
{
    $result = [];
    foreach (['cms_res_resources', 'cms_res_hours', 'cms_res_slots', 'cms_res_blocked', 'cms_res_bookings', 'cms_res_booking_events'] as $table) {
        $result[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll();
    }
    $result['cms_res_resource_locations'] = $pdo->query('SELECT * FROM cms_res_resource_locations ORDER BY resource_id, location_id')->fetchAll();
    return $result;
}

function dom(string $html): \DOMXPath
{
    $doc = new \DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new \DOMXPath($doc);
    foreach ($xpath->query('//*[@aria-describedby or @aria-labelledby]') as $node) {
        foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
            foreach (preg_split('/\s+/', trim($node->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY) as $id) {
                same($xpath->query('//*[@id="' . $id . '"]')->length, 1, 'ARIA target exists uniquely: ' . $id);
            }
        }
    }
    return $xpath;
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('pdo_sqlite is required; no configured database fallback is allowed.');
}
evaluate(substr(source('lib/reservation_booking_validation.php'), strlen('<?php')));
foreach (['inputInt', 'currentUserContactDefaults'] as $name) {
    loadFunction($name === 'inputInt' ? 'db.php' : 'auth.php', $name);
}
foreach (['appendUrlQuery', 'reservationCalendarToken', 'reservationBookingEventLabels', 'reservationRecordBookingEvent',
    'reservationBookingContactEmail', 'reservationStatusMailBody', 'reservationBookingStatusLabels'] as $name) {
    loadFunction('lib/presentation.php', $name);
}
foreach (['adminFieldHasError', 'adminFieldErrorId', 'adminFieldAttributes', 'adminRenderFieldError'] as $name) {
    loadFunction('admin/layout.php', $name);
}

// Prove the fixture rejects the original NULL writes rather than relaxing the schema.
$pdo = fixture();
$bookingId = booking($pdo);
$pdo->exec("INSERT INTO cms_res_blocked (id, resource_id, blocked_date) VALUES (1, 1, '2030-01-01')");
foreach (['cms_res_bookings' => ['guest_name', 'guest_email', 'guest_phone'], 'cms_res_blocked' => ['reason']] as $table => $columns) {
    $metadata = array_column($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(), null, 'name');
    foreach ($columns as $column) {
        same((int)$metadata[$column]['notnull'], 1, 'canonical NOT NULL: ' . $table . '.' . $column);
        same(preg_match('/\b' . $column . '\s+VARCHAR\(\d+\)\s+NOT NULL/', source('migrate.php')), 1, 'migration retains NOT NULL: ' . $column);
        try {
            $pdo->exec('UPDATE ' . $table . ' SET ' . $column . ' = NULL');
            same(true, false, 'NULL must violate the canonical schema: ' . $column);
        } catch (PDOException) {
            same(true, true, 'canonical constraint rejects NULL: ' . $column);
        }
    }
}

foreach ([
    ['mode' => 'user', 'user_id' => '7'],
    ['mode' => 'guest', 'guest_name' => 'Host'],
    ['mode' => 'guest', 'guest_name' => 'Host', 'guest_email' => 'guest@example.test'],
    ['mode' => 'guest', 'guest_name' => 'Host', 'guest_phone' => '123'],
    ['mode' => 'guest', 'guest_name' => '0', 'guest_email' => 'guest@example.test', 'guest_phone' => '0'],
] as $customer) {
    $pdo = fixture();
    $_POST += $customer + ['resource_id' => '1', 'booking_date' => tomorrow(), 'start_time' => '09:00', 'end_time' => '09:30', 'party_size' => '1'];
    $response = runFile('admin/res_booking_add.php');
    same(str_starts_with($response, 'res_booking_detail.php?id='), true, 'manual creation succeeds: ' . $customer['mode']);
    same($GLOBALS['failures'], [], 'manual creation has no caught SQL failure');
    same((int)$pdo->query('SELECT COUNT(*) FROM cms_res_bookings')->fetchColumn(), 1, 'manual creation writes exactly one booking');
    $row = $pdo->query('SELECT * FROM cms_res_bookings')->fetch();
    foreach (['guest_name', 'guest_email', 'guest_phone'] as $column) {
        same($row[$column], $customer[$column] ?? '', 'manual contact remains a string: ' . $column);
    }
    same($row['user_id'] === null ? null : (int)$row['user_id'], $customer['mode'] === 'user' ? 7 : null, 'nullable guest user_id is retained');
    same($row['notes'], null, 'optional notes remain nullable');
    same($row['status'], 'confirmed', 'manual booking is confirmed');
    same((int)$pdo->query("SELECT COUNT(*) FROM cms_res_booking_events WHERE event_type = 'created'")->fetchColumn(), 1, 'manual creation records actual history');
    same($pdo->inTransaction(), false, 'manual creation commits');
}

function resourcePost(): array
{
    $hours = [];
    for ($day = 0; $day < 7; $day++) {
        $hours[$day] = ['open_time' => '09:00', 'close_time' => '11:00'];
    }
    return ['csrf_token' => csrfToken(), 'id' => '1', 'name' => 'Edited room', 'slug' => 'room-1', 'slot_mode' => 'range',
        'capacity' => '5', 'max_concurrent' => '1', 'hours' => $hours, 'allow_guests' => '1', 'reminder_hours_before' => '24',
        'blocked_dates' => [tomorrow()], 'blocked_reasons' => [''], 'blocked_ids' => ['0']];
}
foreach ([false, true] as $existing) {
    $pdo = fixture();
    $pdo->prepare('INSERT INTO cms_res_blocked (id, resource_id, blocked_date, reason) VALUES (2, 2, ?, ?)')->execute([tomorrow(), 'Other resource']);
    $_POST = resourcePost();
    if ($existing) {
        $pdo->prepare('INSERT INTO cms_res_blocked (id, resource_id, blocked_date, reason) VALUES (1, 1, ?, ?)')->execute([tomorrow(), 'Previous reason']);
        $_POST['blocked_ids'] = ['1'];
    }
    same(runFile('admin/res_resource_save.php'), '/admin/res_resources.php', 'blank block reason saves through actual handler');
    same($pdo->query('SELECT reason FROM cms_res_blocked WHERE resource_id = 1')->fetchColumn(), '', 'block reason is an empty string');
    same($pdo->query('SELECT reason FROM cms_res_blocked WHERE resource_id = 2')->fetchColumn(), 'Other resource', 'other resource block is untouched');
    same($pdo->query('SELECT name FROM cms_res_resources WHERE id = 1')->fetchColumn(), 'Edited room', 'resource update commits with blank reason');
    same((int)$pdo->query('SELECT COUNT(*) FROM cms_res_hours WHERE resource_id = 1')->fetchColumn(), 7, 'all availability rules persist');
    same($pdo->inTransaction(), false, 'resource save commits');
}
$pdo = fixture();
$before = snapshot($pdo);
$pdo->exec("CREATE TRIGGER fail_block BEFORE INSERT ON cms_res_blocked BEGIN SELECT RAISE(ABORT, 'isolated failure'); END");
$_POST = resourcePost();
same(runFile('admin/res_resource_save.php'), '/admin/res_resource_form.php?id=1&err=save', 'real block failure takes save-error path');
same(snapshot($pdo), $before, 'late resource-save failure rolls back resource and schedule changes');
same($GLOBALS['effects'], [], 'failed resource save writes no audit log');
same($pdo->inTransaction(), false, 'failed resource save releases transaction');

// Render and submit the public range form using real validation and actual INSERT SQL.
foreach (['09:00' => '09:30', '09:15' => '09:45'] as $open => $boundary) {
    $pdo = fixture();
    $close = $open === '09:00' ? '11:00' : '11:15';
    $pdo->prepare('UPDATE cms_res_hours SET open_time = ?, close_time = ?')->execute([$open . ':00', $close . ':00']);
    booking($pdo, ['start_time' => $boundary . ':00', 'end_time' => $close . ':00']);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['slug' => 'room-1', 'date' => tomorrow()];
    $_POST = [];
    $xpath = dom(runFile('reservations/book.php'));
    same($xpath->query('//select[@name="start_time"]/option[@value="' . $open . '"]')->length, 1, 'free range start is offered');
    same($xpath->query('//select[@name="start_time"]/option[@value="' . $boundary . '"]')->length, 0, 'occupied boundary is not offered as a start');
    same($xpath->query('//select[@name="start_time"]/option[@value="' . $close . '"]')->length, 0, 'closing boundary is not a start');
    same($xpath->query('//select[@name="end_time"]/option[@value="' . $boundary . '"]')->length, 1, 'adjacent booking start remains a valid end');
    same($xpath->query('//select[@name="end_time"]/option[@value="' . $close . '"]')->length, 1, 'closing boundary remains an end');
    same($xpath->query('//label[@for="end_time"]')->length, 1, 'range end has a label');
    same($xpath->query('//fieldset/legend[@id="reservation-time-legend"]')->length, 1, 'range controls retain their legend');

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['csrf_token' => csrfToken(), 'start_time' => $open, 'end_time' => $close, 'party_size' => '1', 'notes' => 'Preserved note'];
    $before = snapshot($pdo);
    $xpath = dom(runFile('reservations/book.php'));
    same(snapshot($pdo), $before, 'overlapping range is rejected without partial writes');
    same($GLOBALS['effects'], [], 'overlapping range sends no mail or log');
    same($xpath->query('//*[@role="alert"]')->length, 1, 'overlap rejection has an alert');
    same($xpath->query('//select[@name="end_time" and @aria-invalid="true"]')->length, 1, 'overlap error is attached to the end control');
    same($xpath->query('//select[@name="end_time"]/option[@value="' . $boundary . '"]')->length, 1, 'adjacent end survives error regeneration');
    same($xpath->query('//select[@name="end_time"]/option[@value="' . $close . '" and @selected]')->length, 1, 'submitted end selection is preserved');
    same($xpath->query('//textarea[@name="notes"]')->item(0)->textContent, 'Preserved note', 'range rejection preserves typed notes');
    same($pdo->inTransaction(), false, 'overlap rejection rolls back');

    $_POST['end_time'] = $boundary;
    same(runFile('reservations/book.php'), '/reservations/my.php?msg=ok', 'adjacent public range commits');
    same((int)$pdo->query('SELECT COUNT(*) FROM cms_res_bookings')->fetchColumn(), 2, 'only the adjacent booking is inserted');
    $row = $pdo->query('SELECT * FROM cms_res_bookings ORDER BY id DESC LIMIT 1')->fetch();
    same($row['start_time'], $open . ':00', 'adjacent start persists');
    same($row['end_time'], $boundary . ':00', 'adjacent end persists');
    same(count($pdo->lockingReads), 5, 'public creation preserves original lock protocol');
    same($pdo->inTransaction(), false, 'adjacent public creation commits');
}
$pdo = fixture();
booking($pdo, ['end_time' => '11:00:00']);
$_GET = ['slug' => 'room-1', 'date' => tomorrow()];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$xpath = dom(runFile('reservations/book.php'));
same($GLOBALS['renderedPage']['view_data']['slotsEmpty'], true, 'fully occupied range has no artificial closing-time start');
same($xpath->query('//form')->length, 0, 'fully occupied range does not offer an unusable form');

// No-show follows the detail UI's existing previous-date rule and named consent.
foreach (['confirmed', 'completed'] as $status) {
    foreach (['today', 'tomorrow'] as $day) {
        $pdo = fixture();
        $date = (new \DateTimeImmutable($day))->format('Y-m-d');
        $bookingId = booking($pdo, ['booking_date' => $date, 'status' => $status]);
        $before = snapshot($pdo);
        $_POST += ['booking_id' => (string)$bookingId, 'action' => 'no_show', 'confirm_reservation_status_no_show' => '1', 'admin_note' => 'Do not save'];
        $response = runFile('admin/res_booking_save.php');
        same(str_contains($response, 'error=no_show_not_available'), true, 'premature no-show is rejected: ' . $status . '/' . $day);
        same(snapshot($pdo), $before, 'premature no-show preserves status, note, tokens and history');
        same($GLOBALS['effects'], [], 'premature no-show emits no log or mail');
        parse_str((string)parse_url($response, PHP_URL_QUERY), $_GET);
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $xpath = dom(runFile('admin/res_booking_detail.php'));
        same($xpath->query('//*[@role="alert"]')->length, 1, 'temporal rejection renders an accessible alert');
        same($xpath->query('//*[@id="reservation-status-error"]')->length, 1, 'temporal rejection uses an existing message target');
        same($xpath->query('//input[@name="confirm_reservation_status_no_show"]')->length, 0, 'premature no-show remains unavailable in the detail');
        if ($day === 'tomorrow' && $status === 'confirmed') {
            $pdo->beginTransaction();
            same(reservationValidateBookingInsert($pdo, 1, $date, '09:00', '10:00', 1)['error'], 'capacity', 'rejected no-show does not free future capacity');
            $pdo->rollBack();
        }
    }
}
foreach (['confirmed', 'completed'] as $status) {
    $pdo = fixture();
    $bookingId = booking($pdo, ['booking_date' => (new \DateTimeImmutable('yesterday'))->format('Y-m-d'), 'status' => $status]);
    $_POST += ['booking_id' => (string)$bookingId, 'action' => 'no_show', 'confirm_reservation_status_cancel' => '1'];
    $before = snapshot($pdo);
    $response = runFile('admin/res_booking_save.php');
    same(str_contains($response, 'error=status_confirm_required'), true, 'wrong confirmation cannot authorize no-show');
    same(snapshot($pdo), $before, 'unconfirmed no-show preserves all data');
    same($GLOBALS['effects'], [], 'unconfirmed no-show emits no log or mail');
    $submitted = $_POST;
    parse_str((string)parse_url($response, PHP_URL_QUERY), $_GET);
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $xpath = dom(runFile('admin/res_booking_detail.php'));
    same($xpath->query('//*[@role="alert" and @aria-atomic="true"]')->length, 1, 'no-show consent error renders an atomic alert');
    same($xpath->query('//input[@name="confirm_reservation_status_no_show" and @aria-invalid="true" and not(@checked)]')->length, 1, 'no-show consent error marks an unchecked confirmation');
    same($xpath->query('//*[@id="reservation-status-confirm-no-show-error"]')->length, 1, 'no-show consent error has field-level text');
    $_POST = $submitted;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['confirm_reservation_status_no_show'] = '1';
    $_POST['admin_note'] = 'Checked absence';
    same(str_contains(runFile('admin/res_booking_save.php'), 'ok=1'), true, 'confirmed previous-day no-show succeeds');
    same($pdo->query('SELECT status FROM cms_res_bookings')->fetchColumn(), 'no_show', 'valid no-show status persists');
    same($pdo->query('SELECT admin_note FROM cms_res_bookings')->fetchColumn(), 'Checked absence', 'valid no-show note persists');
    same((int)$pdo->query("SELECT COUNT(*) FROM cms_res_booking_events WHERE event_type = 'no_show'")->fetchColumn(), 1, 'valid no-show records one event');
    same(count($GLOBALS['effects']), 2, 'valid no-show emits one audit log and one intercepted mail');
    same(str_contains(runFile('admin/res_booking_save.php'), 'error=status_conflict'), true, 'no-show replay is rejected by the original status guard');
    same(count($GLOBALS['effects']), 2, 'no-show replay emits no duplicate side effects');
}

// Follow the real list link to detail; never synthesize hidden or inherited consent.
$pdo = fixture();
$bookingId = booking($pdo, ['status' => 'pending']);
booking($pdo, ['status' => 'pending', 'start_time' => '10:00:00', 'end_time' => '11:00:00']);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = [];
$_GET = ['resource_id' => '1', 'status' => 'pending'];
$before = snapshot($pdo);
$xpath = dom(runFile('admin/res_bookings.php'));
same(snapshot($pdo), $before, 'list navigation does not change reservation data');
same($xpath->query('//form[@action="res_booking_save.php"]')->length, 0, 'list has no broken consent-free status POST');
$links = $xpath->query('//td[@class="actions"]/a');
same($links->length, 2, 'each pending row offers a review link');
foreach ($links as $link) {
    same($link->getElementsByTagName('span')->item(0)->getAttribute('class'), 'sr-only', 'review link includes a screen-reader booking identity');
    same(str_contains($link->textContent, '#'), true, 'review link names its booking');
    parse_str((string)parse_url($link->getAttribute('href'), PHP_URL_QUERY), $params);
    same(str_contains($params['redirect'], 'status=pending'), true, 'review navigation retains the status filter');
    same(str_contains($params['redirect'], 'resource_id=1'), true, 'review navigation retains the resource filter');
    $_GET = $params;
    $detail = dom(runFile('admin/res_booking_detail.php'));
    foreach (['approve', 'reject'] as $action) {
        same($detail->query('//input[@type="checkbox" and @name="confirm_reservation_status_' . $action . '" and @required and not(@checked)]')->length, 1, 'review offers fresh named confirmation: ' . $action);
        same($detail->query('//label[@for="confirm-reservation-status-' . $action . '"]')->length, 1, 'confirmation is labeled: ' . $action);
    }
    same($detail->query('//input[@type="hidden" and starts-with(@name, "confirm_")]')->length, 0, 'detail does not bypass consent with a hidden field');
}
same(snapshot($pdo), $before, 'following review links changes no booking data');
foreach (['approve' => 'confirmed', 'reject' => 'rejected'] as $action => $status) {
    $pdo = fixture();
    $bookingId = booking($pdo, ['status' => 'pending']);
    $_POST += ['booking_id' => (string)$bookingId, 'action' => $action];
    $before = snapshot($pdo);
    $response = runFile('admin/res_booking_save.php');
    same(str_contains($response, 'error=status_confirm_required'), true, 'direct status POST still needs consent: ' . $action);
    same(snapshot($pdo), $before, 'unconfirmed status action preserves state and history: ' . $action);
    same($GLOBALS['effects'], [], 'unconfirmed status action sends no mail: ' . $action);
    $submitted = $_POST;
    parse_str((string)parse_url($response, PHP_URL_QUERY), $_GET);
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $xpath = dom(runFile('admin/res_booking_detail.php'));
    same($xpath->query('//*[@role="alert" and @aria-atomic="true"]')->length, 1, 'review consent error renders an atomic alert: ' . $action);
    same($xpath->query('//input[@name="confirm_reservation_status_' . $action . '" and @aria-invalid="true" and not(@checked)]')->length, 1, 'review consent error marks unchecked confirmation: ' . $action);
    same($xpath->query('//*[@id="reservation-status-confirm-' . $action . '-error"]')->length, 1, 'review consent error has field-level text: ' . $action);
    $_POST = $submitted;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['confirm_reservation_status_' . $action] = '1';
    same(str_contains(runFile('admin/res_booking_save.php'), 'ok=1'), true, 'confirmed review action succeeds: ' . $action);
    same($pdo->query('SELECT status FROM cms_res_bookings')->fetchColumn(), $status, 'confirmed review action persists: ' . $action);
    same((int)$pdo->query('SELECT COUNT(*) FROM cms_res_booking_events')->fetchColumn(), 1, 'confirmed review action records one event: ' . $action);
}

$pdo = fixture();
$bookingId = booking($pdo, ['status' => 'pending']);
$_POST += ['booking_id' => (string)$bookingId, 'action' => 'approve', 'confirm_reservation_status_approve' => '1'];
$before = snapshot($pdo);
$_POST['csrf_token'] = 'wrong';
same(runFile('admin/res_booking_save.php'), 'csrf-rejected', 'status handler retains CSRF guard');
same(snapshot($pdo), $before, 'CSRF rejection writes nothing');
$_POST['csrf_token'] = csrfToken();
$GLOBALS['authorized'] = false;
same(runFile('admin/res_booking_save.php'), 'forbidden', 'status handler retains capability guard');
same(snapshot($pdo), $before, 'unauthorized status POST writes nothing');
$GLOBALS['authorized'] = true;
$GLOBALS['moduleEnabled'] = false;
same(runFile('admin/res_booking_save.php'), 'module-disabled', 'status handler retains module guard');
same(snapshot($pdo), $before, 'disabled-module status POST writes nothing');

echo 'OK: ' . $checks . ' reservation audit checks; canonical isolated SQLite schema; no application DB/HTTP/mail.' . PHP_EOL;
