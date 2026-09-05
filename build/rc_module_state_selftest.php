<?php

declare(strict_types=1);

// Isolated production-function tests: no config, application DB, SMTP or cron
// endpoint execution. PDO translates MySQL-only syntax for in-memory SQLite.
date_default_timezone_set('Europe/Prague');
$rcChecks = 0;
$rcMailSucceeds = false;
$rcNewsletterMail = [];
$rcBoardMail = [];
$rcReminderMail = [];
$rcReminderFailures = [];
$rcClock = date('Y-m-d H:i:s');

function rcModuleSame(mixed $actual, mixed $expected, string $label): void
{
    global $rcChecks;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
    $rcChecks++;
}

/** @param list<string> $names */
function rcModuleLoadFunctions(string $relativePath, array $names): void
{
    $source = file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    if (!is_string($source)) {
        throw new RuntimeException('Cannot load ' . $relativePath);
    }
    $tokens = token_get_all($source);
    $loaded = [];
    foreach ($tokens as $index => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }
        $nameIndex = $index + 1;
        while (isset($tokens[$nameIndex]) && is_array($tokens[$nameIndex]) && $tokens[$nameIndex][0] === T_WHITESPACE) {
            $nameIndex++;
        }
        $nameToken = $tokens[$nameIndex] ?? null;
        if (!is_array($nameToken) || $nameToken[0] !== T_STRING || !in_array($nameToken[1], $names, true)) {
            continue;
        }
        $code = '';
        $depth = 0;
        $started = false;
        for ($cursor = $index, $count = count($tokens); $cursor < $count; $cursor++) {
            $part = $tokens[$cursor];
            $code .= is_array($part) ? $part[1] : $part;
            if ($part === '{' || (is_array($part) && in_array($part[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
                $started = true;
            } elseif ($part === '}') {
                $depth--;
                if ($started && $depth === 0) {
                    eval($code);
                    $loaded[] = $nameToken[1];
                    break;
                }
            }
        }
    }
    sort($loaded);
    sort($names);
    rcModuleSame($loaded, $names, 'Loaded production functions from ' . $relativePath);
}

class RcModuleStatePdo extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->sqliteCreateFunction('NOW', static fn (): string => $GLOBALS['rcClock'], 0);
        $this->sqliteCreateFunction('CURDATE', static fn (): string => substr($GLOBALS['rcClock'], 0, 10), 0);
        $this->sqliteCreateFunction('TIMESTAMP', static fn (string $day, string $time): string => $day . ' ' . $time, 2);
        $this->sqliteCreateFunction('GREATEST', static fn (int $first, int $second): int => max($first, $second), 2);
        $this->sqliteCreateFunction('TRUNCATE', static fn (mixed $value, int $precision): int => (int)$value, 2);
        $this->sqliteCreateFunction('TIMESTAMPDIFF', static function (string $unit, string $start, string $end): int {
            $timezone = new DateTimeZone('UTC');
            return (new DateTimeImmutable($end, $timezone))->getTimestamp()
                - (new DateTimeImmutable($start, $timezone))->getTimestamp();
        }, 3);
    }

    private function translate(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', trim($sql)) ?? '';
        if (str_contains($sql, 'FROM INFORMATION_SCHEMA.COLUMNS')) {
            return 'SELECT name AS COLUMN_NAME FROM pragma_table_info(?)';
        }
        $sql = str_replace(' FOR UPDATE', '', $sql);
        $sql = str_replace('ON DUPLICATE KEY UPDATE email = cms_subscribers.email', 'ON CONFLICT(email) DO NOTHING', $sql);
        return str_replace('TIMESTAMPDIFF(SECOND,', "TIMESTAMPDIFF('SECOND',", $sql);
    }

    /** @param array<int,mixed> $options */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare($this->translate($query), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return $fetchMode === null
            ? parent::query($this->translate($query))
            : parent::query($this->translate($query), $fetchMode, ...$fetchModeArgs);
    }
}

function isModuleEnabled(string $module): bool
{
    return true;
}

/** @param array<string,mixed> $context */
function koraLog(string $level, string $message, array $context = []): void
{
}

function logAction(string $action, string $detail): void
{
}

function sendNewsletterSubscriptionConfirmation(string $email, string $token): bool
{
    $GLOBALS['rcNewsletterMail'][] = [$email, $token];
    return $GLOBALS['rcMailSucceeds'];
}

/** @param array<string,mixed> $document */
function sendBoardItemNotification(string $email, string $token, array $document): bool
{
    $GLOBALS['rcBoardMail'][] = [$email, (int)$document['id']];
    return true;
}

function boardSlug(string $value): string
{
    return trim($value);
}

/** @param array<string,mixed> $document */
function boardPublicPath(array $document): string
{
    return '/board/' . (string)$document['slug'];
}

/** @return array<string,mixed>|null */
function reservationBookingForNotification(PDO $pdo, int $bookingId): ?array
{
    $stmt = $pdo->prepare('SELECT b.*, r.reminders_enabled, r.reminder_hours_before
        FROM cms_res_bookings b JOIN cms_res_resources r ON r.id = b.resource_id WHERE b.id = ?');
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/** @param array<string,mixed> $booking */
function reservationReminderSubject(array $booking): string
{
    return 'Reminder';
}

/** @param array<string,mixed> $booking */
function reservationReminderBody(array $booking): string
{
    return 'Reminder';
}

/** @param array<string,mixed> $booking */
function reservationSendMail(array $booking, string $subject, string $body, string $event, bool $attachments): bool
{
    $GLOBALS['rcReminderMail'][] = (int)$booking['id'];
    return !in_array((int)$booking['id'], $GLOBALS['rcReminderFailures'], true);
}

function reservationRecordBookingEvent(PDO $pdo, int $bookingId, string $event, string $description): void
{
    $pdo->prepare('INSERT INTO cms_res_booking_events (booking_id, event_type, description) VALUES (?, ?, ?)')
        ->execute([$bookingId, $event, $description]);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfToken(): string
{
    return 'test-csrf';
}

function honeypotField(): string
{
    return '';
}

/** @param array<string,mixed> $poll
 * @param array<string,string> $query
 */
function pollPublicPath(array $poll, array $query = []): string
{
    return '/polls/test';
}

/** @param array<string,mixed> $poll
 * @param array{voted:bool,showForm:bool,resultsVisible:bool} $state
 */
function rcModuleRenderPoll(array $poll, bool $hasVoted, array $state): DOMXPath
{
    $isEmbedded = true;
    $options = [['id' => 1, 'option_text' => 'First', 'vote_count' => 1], ['id' => 2, 'option_text' => 'Second', 'vote_count' => 2]];
    $voterCount = 3;
    $totalVotes = 3;
    $voted = $state['voted'];
    $showForm = $state['showForm'];
    $resultsVisible = $state['resultsVisible'];
    $isActive = $poll['state'] === 'active';
    $voteErrorMessage = '';
    ob_start();
    try {
        require dirname(__DIR__) . '/themes/default/views/modules/polls-index.php';
        $html = (string)ob_get_contents();
    } finally {
        ob_end_clean();
    }
    return rcModuleParseHtml($html);
}

function rcModuleParseHtml(string $html): DOMXPath
{
    $document = new DOMDocument();
    $previousErrors = libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
    }
    return new DOMXPath($document);
}

function rcModuleRecipeHistoryFeedback(string $error): DOMXPath
{
    $source = (string)file_get_contents(dirname(__DIR__) . '/admin/recipe_history.php');
    $start = strpos($source, '<?php if ($message !==');
    $end = strpos($source, '<?php if ($contentLockWarning !==');
    if ($start === false || $end === false || $end <= $start) {
        throw new RuntimeException('Cannot locate the production recipe-history feedback template.');
    }
    $message = '';
    ob_start();
    try {
        eval('?>' . substr($source, $start, $end - $start));
        $html = (string)ob_get_contents();
    } finally {
        ob_end_clean();
    }
    return rcModuleParseHtml($html);
}

function rcModuleSeedRecipe(PDO $pdo, int $id, string $status): void
{
    $pdo->prepare('INSERT INTO cms_recipes (id, title, status) VALUES (?, ?, ?)')->execute([$id, 'Recipe ' . $id, $status]);
    $pdo->prepare('INSERT INTO cms_recipe_ingredient_groups (id, recipe_id) VALUES (?, ?)')->execute([$id, $id]);
    $pdo->prepare('INSERT INTO cms_recipe_ingredients (id, recipe_id, group_id, name) VALUES (?, ?, ?, ?)')
        ->execute([$id, $id, $id, 'Flour']);
    $pdo->prepare('INSERT INTO cms_recipe_steps (id, recipe_id, instruction) VALUES (?, ?, ?)')
        ->execute([$id, $id, 'Mix']);
}

try {
    rcModuleLoadFunctions('cron.php', ['cronMissingColumns', 'cronProcessReservationReminders', 'cronProcessScheduledBoardPublications']);
    rcModuleLoadFunctions('polls/index.php', ['pollDetailInteractionState']);
    rcModuleLoadFunctions('subscribe.php', ['newsletterRequestSubscription']);
    rcModuleLoadFunctions('admin/recipe_content.php', ['recipeContentDeletePart']);
    rcModuleLoadFunctions('admin/recipe_history.php', ['recipeHistoryRestoreSnapshot']);
    rcModuleLoadFunctions('lib/presentation.php', [
        'pollVoteModeOptions', 'pollVoteMode', 'pollResultsVisibilityOptions', 'pollResultsVisibility',
        'pollAllowsMultipleChoices', 'pollConfiguredMaxChoices', 'pollResultsAreVisible',
        'pollResultPercentage', 'pollVoteSelectionLabel', 'reservationReminderIsDue',
        'boardIsPubliclyReachable', 'boardPublicationEventLabels', 'recordBoardPublicationEvent',
        'boardAttachmentChecksum', 'notifyBoardSubscribers',
    ]);
    require dirname(__DIR__) . '/lib/recipes.php';
    $pdo = new RcModuleStatePdo();

    foreach ([
        'cron.php' => '$totalPublished += cronProcessScheduledBoardPublications($pdo);',
        'polls/index.php' => '$interactionState = pollDetailInteractionState($poll, $hasVoted, $voted);',
        'subscribe.php' => '$state = newsletterRequestSubscription($pdo, $email);',
        'admin/recipe_content.php' => 'recipeContentDeletePart($pdo, $recipeId, $part, $partId, currentUserId())',
        'admin/recipe_history.php' => '$error = recipeHistoryRestoreSnapshot($pdo, $recipeId, $snapshot, currentUserId());',
    ] as $path => $call) {
        rcModuleSame(
            str_contains((string)file_get_contents(dirname(__DIR__) . '/' . $path), $call),
            true,
            'Endpoint uses its tested state transition: ' . $path
        );
    }

    foreach (['after_vote', 'always', 'closed', 'hidden'] as $visibility) {
        foreach (['active', 'closed'] as $pollState) {
            foreach ([false, true] as $hasVoted) {
                foreach ([false, true] as $requestedSuccess) {
                    $poll = ['id' => 1, 'question' => 'Test poll', 'state' => $pollState, 'results_visibility' => $visibility];
                    $state = pollDetailInteractionState($poll, $hasVoted, $requestedSuccess);
                    $expectedVisible = match ($visibility) {
                        'always' => true,
                        'hidden' => false,
                        'closed' => $pollState === 'closed',
                        default => $hasVoted || $pollState === 'closed',
                    };
                    rcModuleSame($state['voted'], $hasVoted && $requestedSuccess, 'Only a verified voter receives success feedback');
                    rcModuleSame($state['resultsVisible'], $expectedVisible, 'Query success flags do not grant result access');
                    $dom = rcModuleRenderPoll($poll, $hasVoted, $state);
                    rcModuleSame((int)$dom->evaluate('count(//form)'), $pollState === 'active' && !$hasVoted ? 1 : 0, 'Voting form availability');
                    rcModuleSame((int)$dom->evaluate('count(//*[@id="poll-results-title"])'), $expectedVisible ? 1 : 0, 'Results render independently of voting form');
                    rcModuleSame((int)$dom->evaluate('count(//*[@id="poll-vote-success-message-1"])'), $state['voted'] ? 1 : 0, 'No forged vote success banner');
                    foreach ($dom->query('//*[@aria-labelledby or @aria-describedby]') ?: [] as $element) {
                        if (!$element instanceof DOMElement) {
                            continue;
                        }
                        foreach (['aria-labelledby', 'aria-describedby'] as $attribute) {
                            foreach (preg_split('/\s+/', trim($element->getAttribute($attribute))) ?: [] as $reference) {
                                if ($reference !== '') {
                                    rcModuleSame((int)$dom->evaluate('count(//*[@id="' . $reference . '"])'), 1, 'Accessible poll reference ' . $reference);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    $pdo->exec('CREATE TABLE cms_subscribers (id INTEGER PRIMARY KEY, email TEXT UNIQUE, token TEXT UNIQUE, confirmed INT DEFAULT 0)');
    rcModuleSame(newsletterRequestSubscription($pdo, 'reader@example.test'), 'mail_error', 'First failed confirmation is reported');
    $originalSubscriber = $pdo->query('SELECT * FROM cms_subscribers')->fetch();
    $rcMailSucceeds = true;
    rcModuleSame(newsletterRequestSubscription($pdo, 'reader@example.test'), 'ok', 'Pending subscriber can retry');
    rcModuleSame($pdo->query('SELECT * FROM cms_subscribers')->fetch(), $originalSubscriber, 'Retry preserves token and pending state');
    rcModuleSame($GLOBALS['rcNewsletterMail'][0], $GLOBALS['rcNewsletterMail'][1], 'Retry sends the same valid confirmation link');
    $pdo->exec('UPDATE cms_subscribers SET confirmed = 1');
    rcModuleSame(newsletterRequestSubscription($pdo, 'reader@example.test'), 'ok', 'Confirmed subscriber response remains generic');
    rcModuleSame(count($rcNewsletterMail), 2, 'Confirmed subscriber receives no new confirmation');
    rcModuleSame((int)$pdo->query('SELECT confirmed FROM cms_subscribers')->fetchColumn(), 1, 'Retry never unconfirms a subscriber');

    $pdo->exec('CREATE TABLE cms_res_resources (id INTEGER PRIMARY KEY, reminders_enabled INT, reminder_hours_before INT,
        reminder_message TEXT, calendar_invite_enabled INT)');
    $pdo->exec('CREATE TABLE cms_res_bookings (id INTEGER PRIMARY KEY, resource_id INT, status TEXT DEFAULT "confirmed",
        booking_date TEXT, start_time TEXT, calendar_token TEXT, reminder_sent_at TEXT, reminder_last_error TEXT,
        updated_at TEXT)');
    $pdo->exec('CREATE TABLE cms_res_booking_events (id INTEGER PRIMARY KEY, booking_id INT, event_type TEXT, description TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('INSERT INTO cms_res_resources (id, reminders_enabled, reminder_hours_before) VALUES (1,1,1),(2,1,48),(3,1,0),(4,1,NULL)');
    $insertBooking = $pdo->prepare('INSERT INTO cms_res_bookings (id, resource_id, booking_date, start_time) VALUES (?, ?, ?, ?)');
    $now = new DateTimeImmutable($rcClock);
    $notDue = $now->modify('+2 hours');
    for ($id = 1; $id <= 100; $id++) {
        $insertBooking->execute([$id, 1, $notDue->format('Y-m-d'), $notDue->format('H:i:s')]);
    }
    foreach ([[101,2,24], [102,3,1], [103,4,24], [104,3,-1], [105,3,1], [106,3,1], [107,3,1]] as [$id, $resource, $hours]) {
        $start = $now->modify(($hours >= 0 ? '+' : '') . $hours . ' hours');
        $insertBooking->execute([$id, $resource, $start->format('Y-m-d'), $start->format('H:i:s')]);
    }
    $pdo->exec("UPDATE cms_res_bookings SET reminder_last_error = 'failed' WHERE id = 105");
    $pdo->exec("UPDATE cms_res_bookings SET reminder_sent_at = '2026-01-01' WHERE id = 106");
    $pdo->exec("UPDATE cms_res_bookings SET status = 'cancelled' WHERE id = 107");
    rcModuleSame(cronProcessReservationReminders($pdo), ['sent' => 3, 'failed' => 0], 'Due bookings are not starved by 100 earlier not-due bookings');
    rcModuleSame($rcReminderMail, [102, 101, 103], 'Due selection respects default and minimum lead times and state exclusions');
    rcModuleSame(cronProcessReservationReminders($pdo), ['sent' => 0, 'failed' => 0], 'Sent reminders are not repeated');
    $failedStart = $now->modify('+1 hour');
    $insertBooking->execute([108, 3, $failedStart->format('Y-m-d'), $failedStart->format('H:i:s')]);
    $rcReminderFailures = [108];
    rcModuleSame(cronProcessReservationReminders($pdo), ['sent' => 0, 'failed' => 1], 'Failed reminder is recorded');
    rcModuleSame(cronProcessReservationReminders($pdo), ['sent' => 0, 'failed' => 0], 'Failed reminders do not retry without intervention');

    $pdo->exec('UPDATE cms_res_bookings SET reminder_sent_at = CURRENT_TIMESTAMP');
    $insertResource = $pdo->prepare('INSERT INTO cms_res_resources (id, reminders_enabled, reminder_hours_before) VALUES (?, 1, ?)');
    $matrixBookingId = 1000;
    foreach ([null, 0, -5, '', 'invalid', '1.9', '-1.9', '24hours', '1e2', 2147483647] as $offset => $legacyHours) {
        $resourceId = 200 + $offset;
        $insertResource->execute([$resourceId, $legacyHours]);
        foreach ([-1, 0, 3599, 3600, 3601, 86399, 86400, 86401, 359999, 360000, 360001] as $seconds) {
            $start = $now->modify(($seconds >= 0 ? '+' : '') . $seconds . ' seconds');
            $insertBooking->execute([$matrixBookingId++, $resourceId, $start->format('Y-m-d'), $start->format('H:i:s')]);
        }
    }
    $legacyBookings = $pdo->query('SELECT b.*, r.reminders_enabled, r.reminder_hours_before
        FROM cms_res_bookings b JOIN cms_res_resources r ON r.id = b.resource_id
        WHERE b.id >= 1000 ORDER BY b.booking_date, b.start_time, b.id')->fetchAll();
    $expectedLegacyIds = [];
    foreach ($legacyBookings as $legacyBooking) {
        if (reservationReminderIsDue($legacyBooking, $now)) {
            $expectedLegacyIds[] = (int)$legacyBooking['id'];
        }
    }
    $rcReminderMail = [];
    rcModuleSame(count($expectedLegacyIds) < 100, true, 'Legacy normalization matrix fits one due batch');
    rcModuleSame(
        cronProcessReservationReminders($pdo),
        ['sent' => count($expectedLegacyIds), 'failed' => 0],
        'SQL due predicate matches the production PHP predicate for malformed and boundary values'
    );
    rcModuleSame($rcReminderMail, $expectedLegacyIds, 'SQL and PHP select exactly the same legacy-hour bookings');

    $pdo->exec('UPDATE cms_res_bookings SET reminder_sent_at = CURRENT_TIMESTAMP');
    $fractionalStart = $now->modify('+90 minutes');
    for ($id = 2000; $id < 2100; $id++) {
        $insertBooking->execute([$id, 205, $fractionalStart->format('Y-m-d'), $fractionalStart->format('H:i:s')]);
    }
    $laterDue = $now->modify('+24 hours');
    $insertBooking->execute([2100, 2, $laterDue->format('Y-m-d'), $laterDue->format('H:i:s')]);
    $rcReminderMail = [];
    rcModuleSame(
        cronProcessReservationReminders($pdo),
        ['sent' => 1, 'failed' => 0],
        '100 fractional legacy lead times cannot starve a later due reminder'
    );
    rcModuleSame($rcReminderMail, [2100], 'Only the later genuinely due booking is sent');

    $pdo->exec("CREATE TABLE cms_board (id INTEGER PRIMARY KEY, slug TEXT, title TEXT DEFAULT 'Board', category_id INT DEFAULT 1,
        status TEXT DEFAULT 'published', is_published INT DEFAULT 0, deleted_at TEXT, posted_date TEXT,
        publish_at TEXT, unpublish_at TEXT, created_at TEXT, filename TEXT DEFAULT '', original_name TEXT DEFAULT '', file_size INT DEFAULT 0)");
    $pdo->exec('CREATE TABLE cms_board_publication_events (id INTEGER PRIMARY KEY, board_id INT, event_type TEXT, event_date TEXT,
        actor_user_id INT, public_path TEXT, attachment_name TEXT, attachment_size INT, attachment_checksum TEXT)');
    $pdo->exec('CREATE TABLE cms_board_subscribers (id INTEGER PRIMARY KEY, email TEXT, token TEXT, confirmed INT, all_categories INT)');
    $pdo->exec('CREATE TABLE cms_board_subscriber_categories (subscriber_id INT, category_id INT)');
    $pdo->exec("INSERT INTO cms_board_subscribers VALUES (1,'all@example.test','all',1,1), (2,'category@example.test','category',1,0),
        (3,'pending@example.test','pending',0,1), (4,'other@example.test','other',1,0)");
    $pdo->exec('INSERT INTO cms_board_subscriber_categories VALUES (2,1),(4,2)');
    $insertBoard = $pdo->prepare('INSERT INTO cms_board (id, slug, posted_date, publish_at, created_at) VALUES (?, ?, ?, ?, ?)');
    $dueTime = $now->modify('-1 hour')->format('Y-m-d H:i:s');
    for ($id = 1; $id <= 9; $id++) {
        $insertBoard->execute([$id, 'item-' . $id, $now->format('Y-m-d'), $dueTime, $dueTime]);
    }
    $pdo->exec('UPDATE cms_board SET is_published = 1 WHERE id = 2');
    $pdo->prepare('UPDATE cms_board SET publish_at = ? WHERE id = 3')->execute([$now->modify('+1 hour')->format('Y-m-d H:i:s')]);
    $pdo->exec("UPDATE cms_board SET deleted_at = '2026-01-01' WHERE id = 4");
    $pdo->exec("UPDATE cms_board SET status = 'draft' WHERE id = 5");
    $pdo->prepare('UPDATE cms_board SET posted_date = ? WHERE id = 6')->execute([$now->modify('+1 day')->format('Y-m-d')]);
    $pdo->prepare('UPDATE cms_board SET unpublish_at = ? WHERE id = 7')->execute([$dueTime]);
    $pdo->exec('UPDATE cms_board SET publish_at = NULL WHERE id = 8');
    $pdo->exec("UPDATE cms_board SET slug = '' WHERE id = 9");
    rcModuleSame(cronProcessScheduledBoardPublications($pdo), 2, 'Scheduled board publishes both checkbox states, excluding ineligible rows');
    rcModuleSame($pdo->query('SELECT board_id FROM cms_board_publication_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN), [1,2], 'Scheduled publications record their audit events');
    rcModuleSame(count($rcBoardMail), 4, 'Scheduled publications notify only confirmed matching subscribers');
    rcModuleSame($rcBoardMail, [
        ['all@example.test', 1], ['category@example.test', 1],
        ['all@example.test', 2], ['category@example.test', 2],
    ], 'Board notification recipients preserve category opt-ins');
    rcModuleSame($pdo->query('SELECT event_type, actor_user_id, public_path FROM cms_board_publication_events ORDER BY id')->fetchAll(), [
        ['event_type' => 'published', 'actor_user_id' => null, 'public_path' => '/board/item-1'],
        ['event_type' => 'published', 'actor_user_id' => null, 'public_path' => '/board/item-2'],
    ], 'Scheduled event metadata identifies automated publication and public path');
    rcModuleSame($pdo->query('SELECT id FROM cms_board WHERE is_published = 1 AND publish_at IS NULL ORDER BY id')->fetchAll(PDO::FETCH_COLUMN), [1,2], 'Only claimed schedule transitions change state');
    rcModuleSame(cronProcessScheduledBoardPublications($pdo), 0, 'Completed board schedules are not republished');
    rcModuleSame(count($rcBoardMail), 4, 'Repeated cron sends no duplicate board notices');

    // Reproduce the real board_save branches: a public save retains publish_at,
    // records a published event and notifies subscribers immediately.
    foreach ([10 => $rcClock, 11 => $dueTime] as $id => $savedPublishAt) {
        $insertBoard->execute([$id, 'item-' . $id, $now->format('Y-m-d'), $savedPublishAt, $rcClock]);
        $pdo->prepare('UPDATE cms_board SET is_published = 1 WHERE id = ?')->execute([$id]);
        $savedDocument = $pdo->query('SELECT * FROM cms_board WHERE id = ' . $id)->fetch();
        rcModuleSame(boardIsPubliclyReachable($savedDocument), true, 'Past-date board save is immediately public');
        recordBoardPublicationEvent($pdo, $savedDocument, 'published');
        notifyBoardSubscribers($pdo, $savedDocument);
    }
    $insertEvent = $pdo->prepare('INSERT INTO cms_board_publication_events (board_id, event_type, event_date) VALUES (?, ?, ?)');
    $insertBoard->execute([12, 'item-12', $now->format('Y-m-d'), $dueTime, $dueTime]);
    $insertEvent->execute([12, 'published', $now->modify('-2 hours')->format('Y-m-d H:i:s')]);
    $insertEvent->execute([12, 'url_changed', $rcClock]);
    $newPublicationIds = [12];
    foreach (['url_changed', 'attachment_changed', 'removed', 'hidden', 'deleted', 'restored'] as $offset => $eventType) {
        $id = 13 + $offset;
        $newPublicationIds[] = $id;
        $insertBoard->execute([$id, 'item-' . $id, $now->format('Y-m-d'), $dueTime, $dueTime]);
        $insertEvent->execute([$id, $eventType, $rcClock]);
    }
    $mailBeforeDeduplication = count($rcBoardMail);
    $eventsBeforeDeduplication = (int)$pdo->query('SELECT COUNT(*) FROM cms_board_publication_events')->fetchColumn();
    rcModuleSame(
        cronProcessScheduledBoardPublications($pdo),
        count($newPublicationIds),
        'Only new publication transitions count, not already announced public saves'
    );
    rcModuleSame($pdo->query('SELECT id, publish_at, created_at FROM cms_board WHERE id IN (10,11) ORDER BY id')->fetchAll(), [
        ['id' => 10, 'publish_at' => null, 'created_at' => $rcClock],
        ['id' => 11, 'publish_at' => null, 'created_at' => $rcClock],
    ], 'Already announced schedules are cleared without rewriting the actual publication date');
    rcModuleSame(
        (int)$pdo->query("SELECT COUNT(*) FROM cms_board_publication_events WHERE board_id IN (10,11) AND event_type = 'published'")->fetchColumn(),
        2,
        'Events exactly at or after publish_at both suppress duplicate publication events'
    );
    rcModuleSame(
        (int)$pdo->query('SELECT COUNT(*) FROM cms_board_publication_events')->fetchColumn(),
        $eventsBeforeDeduplication + count($newPublicationIds),
        'Older publications and other event types never suppress new events'
    );
    $newMail = array_slice($rcBoardMail, $mailBeforeDeduplication);
    rcModuleSame(
        array_column($newMail, 1),
        array_merge(...array_map(static fn (int $id): array => [$id, $id], $newPublicationIds)),
        'Only genuinely new publications send category notifications, once per matching subscriber'
    );
    rcModuleSame(
        (int)$pdo->query("SELECT COUNT(*) FROM cms_board_publication_events WHERE board_id = 12 AND event_type = 'published'")->fetchColumn(),
        2,
        'A later schedule publishes again after its older published event'
    );
    rcModuleSame(cronProcessScheduledBoardPublications($pdo), 0, 'Deduplicated and newly published schedules are all consumed');
    rcModuleSame(count($rcBoardMail), $mailBeforeDeduplication + count($newMail), 'Later cron runs do not resend either class of publication');

    $pdo->exec('CREATE TABLE cms_recipes (id INTEGER PRIMARY KEY, title TEXT, status TEXT, deleted_at TEXT)');
    $pdo->exec('CREATE TABLE cms_recipe_ingredient_groups (id INTEGER PRIMARY KEY, recipe_id INT, title TEXT DEFAULT "", sort_order INT DEFAULT 0)');
    $pdo->exec('CREATE TABLE cms_recipe_ingredients (id INTEGER PRIMARY KEY, recipe_id INT, group_id INT, name TEXT,
        amount TEXT DEFAULT "", quantity_min TEXT, quantity_max TEXT, unit TEXT DEFAULT "", note TEXT DEFAULT "",
        is_optional INT DEFAULT 0, sort_order INT DEFAULT 0)');
    $pdo->exec('CREATE TABLE cms_recipe_steps (id INTEGER PRIMARY KEY, recipe_id INT, title TEXT DEFAULT "", instruction TEXT,
        media_id INT, image_alt_text TEXT DEFAULT "", sort_order INT DEFAULT 0)');
    $pdo->exec('CREATE TABLE cms_media (id INTEGER PRIMARY KEY, filename TEXT, folder TEXT, original_name TEXT, alt_text TEXT, mime_type TEXT, visibility TEXT)');
    $pdo->exec('CREATE TABLE cms_recipe_structure_snapshots (id INTEGER PRIMARY KEY, recipe_id INT, action_label TEXT, snapshot_json TEXT,
        ingredient_count INT, step_count INT, user_id INT)');
    foreach (['ingredient', 'step', 'group'] as $offset => $part) {
        $id = $offset + 1;
        rcModuleSeedRecipe($pdo, $id, 'published');
        $before = recipeStructureSnapshot($pdo, $id);
        $blocked = false;
        try {
            recipeContentDeletePart($pdo, $id, $part, $id, null);
        } catch (DomainException) {
            $blocked = true;
        }
        rcModuleSame($blocked, true, 'Published recipe rejects deletion of its last ' . $part);
        rcModuleSame(recipeStructureSnapshot($pdo, $id), $before, 'Rejected ' . $part . ' deletion rolls back all structure changes');
        $pdo->prepare("UPDATE cms_recipes SET status = 'draft' WHERE id = ?")->execute([$id]);
        rcModuleSame(recipeContentDeletePart($pdo, $id, $part, $id, null), true, 'Draft recipe permits deleting its last ' . $part);
        rcModuleSame(recipeHasPublishableStructure($pdo, $id), false, 'Draft can be incomplete');
    }
    rcModuleSame((int)$pdo->query('SELECT COUNT(*) FROM cms_recipe_structure_snapshots')->fetchColumn(), 3, 'Only successful mutations persist snapshots');
    rcModuleSeedRecipe($pdo, 4, 'published');
    $pdo->exec("INSERT INTO cms_recipe_ingredients (id, recipe_id, group_id, name) VALUES (40,4,4,'Sugar')");
    rcModuleSame(recipeContentDeletePart($pdo, 4, 'ingredient', 40, null), true, 'Published recipe may delete a non-final ingredient');
    rcModuleSame(recipeHasPublishableStructure($pdo, 4), true, 'Successful published edit preserves complete structure');
    rcModuleSame(recipeContentDeletePart($pdo, 4, 'ingredient', 3, null), false, 'Cross-recipe deletion is rejected');
    rcModuleSame($pdo->inTransaction(), false, 'Deletion helper leaves no transaction open');
    $beforeFailure = recipeStructureSnapshot($pdo, 4);
    $snapshotsBeforeFailure = (int)$pdo->query('SELECT COUNT(*) FROM cms_recipe_structure_snapshots')->fetchColumn();
    $pdo->exec("CREATE TRIGGER rc_reject_step_delete BEFORE DELETE ON cms_recipe_steps BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
    $databaseFailureCaught = false;
    try {
        recipeContentDeletePart($pdo, 4, 'step', 4, null);
    } catch (PDOException) {
        $databaseFailureCaught = true;
    }
    rcModuleSame($databaseFailureCaught, true, 'Database failures are not converted to successful deletion');
    rcModuleSame(recipeStructureSnapshot($pdo, 4), $beforeFailure, 'Database failure rolls back destructive edits');
    rcModuleSame(
        (int)$pdo->query('SELECT COUNT(*) FROM cms_recipe_structure_snapshots')->fetchColumn(),
        $snapshotsBeforeFailure,
        'Database failure also rolls back the snapshot'
    );
    rcModuleSame($pdo->inTransaction(), false, 'Database failure leaves no open transaction');

    $pdo->exec('DROP TRIGGER rc_reject_step_delete');
    rcModuleSeedRecipe($pdo, 5, 'published');
    $emptySnapshot = ['version' => 1, 'groups' => [], 'steps' => []];
    $beforeRestore = recipeStructureSnapshot($pdo, 5);
    $publishedRestoreBlocked = false;
    try {
        recipeRestoreStructure($pdo, 5, $emptySnapshot);
    } catch (DomainException) {
        $publishedRestoreBlocked = true;
    }
    rcModuleSame($publishedRestoreBlocked, true, 'RC39: restoring empty history must not leave a published recipe incomplete');
    rcModuleSame(recipeStructureSnapshot($pdo, 5), $beforeRestore, 'Rejected empty history preserves published content');
    rcModuleSame($pdo->inTransaction(), false, 'Rejected standalone restore closes its own transaction');

    $validSnapshot = [
        'version' => 1,
        'groups' => [['title' => 'Restored ingredients', 'ingredients' => [['name' => 'Restored flour', 'quantity_min' => '125', 'unit' => 'g']]]],
        'steps' => [['title' => 'Restored step', 'instruction' => 'Mix thoroughly.']],
    ];
    $invalidPublishedSnapshots = [
        ['version' => 1, 'groups' => [], 'steps' => $validSnapshot['steps']],
        ['version' => 1, 'groups' => $validSnapshot['groups'], 'steps' => []],
        ['version' => 1, 'groups' => [['ingredients' => [['name' => " \t\n"]]]], 'steps' => $validSnapshot['steps']],
        ['version' => 1, 'groups' => $validSnapshot['groups'], 'steps' => [['instruction' => " \t\n"]]],
        ['version' => 1, 'groups' => [null, [], ['ingredients' => ['invalid', null, ['name' => []]]]], 'steps' => $validSnapshot['steps']],
        ['version' => 1, 'groups' => $validSnapshot['groups'], 'steps' => [null, 'invalid', ['instruction' => []]]],
    ];
    $pdo->exec("CREATE TRIGGER rc_restore_must_validate_first BEFORE DELETE ON cms_recipe_steps BEGIN SELECT RAISE(ABORT, 'restore deleted before validating'); END");
    $snapshotsBeforeRestore = (int)$pdo->query('SELECT COUNT(*) FROM cms_recipe_structure_snapshots')->fetchColumn();
    foreach ($invalidPublishedSnapshots as $invalidSnapshot) {
        $restoreError = recipeHistoryRestoreSnapshot($pdo, 5, $invalidSnapshot, null);
        rcModuleSame(str_contains($restoreError, 'koncept'), true, 'Invalid published history returns actionable validation feedback before deleting');
        rcModuleSame(recipeStructureSnapshot($pdo, 5), $beforeRestore, 'Invalid historical entries cannot erase public recipe structure');
        rcModuleSame(
            (int)$pdo->query('SELECT COUNT(*) FROM cms_recipe_structure_snapshots')->fetchColumn(),
            $snapshotsBeforeRestore,
            'Rejected history restore leaves no spurious backup snapshot'
        );
        rcModuleSame($pdo->inTransaction(), false, 'History validation failure closes the transaction');
    }
    $feedbackError = recipeHistoryRestoreSnapshot($pdo, 5, $emptySnapshot, null);
    $feedbackDom = rcModuleRecipeHistoryFeedback($feedbackError);
    rcModuleSame(
        (int)$feedbackDom->evaluate('count(//*[@id="recipe-history-error" and @role="alert"])'),
        1,
        'Restore rejection is rendered through the existing accessible alert'
    );
    rcModuleSame(
        (string)$feedbackDom->evaluate('string(//*[@id="recipe-history-error"])'),
        $feedbackError,
        'Accessible alert preserves the corrective instruction'
    );
    rcModuleSame((int)$feedbackDom->evaluate('count(//*[@role="status"])'), 0, 'Rejected restore renders no success message');
    $escapedFeedback = rcModuleRecipeHistoryFeedback('<script>invalid</script>');
    rcModuleSame((int)$escapedFeedback->evaluate('count(//script)'), 0, 'History validation feedback remains HTML-escaped');
    $pdo->exec('DROP TRIGGER rc_restore_must_validate_first');

    $pdo->beginTransaction();
    $nestedRejected = false;
    try {
        recipeRestoreStructure($pdo, 5, $emptySnapshot);
    } catch (DomainException) {
        $nestedRejected = true;
    }
    rcModuleSame($nestedRejected, true, 'Direct callers cannot bypass published validation inside an existing transaction');
    rcModuleSame($pdo->inTransaction(), true, 'Library validation preserves ownership of the caller transaction');
    rcModuleSame(recipeStructureSnapshot($pdo, 5), $beforeRestore, 'Nested validation changes no recipe data');
    $pdo->rollBack();

    rcModuleSame(recipeHistoryRestoreSnapshot($pdo, 5, $validSnapshot, 17), '', 'Complete history restores successfully into a published recipe');
    rcModuleSame(recipeHasPublishableStructure($pdo, 5), true, 'Restored public recipe remains complete');
    rcModuleSame($pdo->query('SELECT status FROM cms_recipes WHERE id = 5')->fetchColumn(), 'published', 'Valid restoration preserves publication status');
    $savedBackup = $pdo->query('SELECT snapshot_json, user_id FROM cms_recipe_structure_snapshots WHERE recipe_id = 5 ORDER BY id DESC LIMIT 1')->fetch();
    rcModuleSame(recipeDecodeStructureSnapshot((string)$savedBackup['snapshot_json']), $beforeRestore, 'Successful history restore retains the original structure in history');
    rcModuleSame((int)$savedBackup['user_id'], 17, 'Backup records the restoring actor');
    $afterValidRestore = recipeStructureSnapshot($pdo, 5);
    rcModuleSame($afterValidRestore['groups'][0]['ingredients'][0]['name'], 'Restored flour', 'Valid named ingredient is restored');
    rcModuleSame($afterValidRestore['steps'][0]['instruction'], 'Mix thoroughly.', 'Valid instruction is restored');
    $pdo->beginTransaction();
    rcModuleSame(recipeRestoreStructure($pdo, 5, $beforeRestore), true, 'Complete direct restore supports caller-owned transactions');
    rcModuleSame($pdo->inTransaction(), true, 'Complete library restore does not commit the caller transaction');
    $pdo->rollBack();
    rcModuleSame(recipeStructureSnapshot($pdo, 5), $afterValidRestore, 'Caller rollback restores the prior public content');

    $mixedSnapshot = $validSnapshot;
    $mixedSnapshot['groups'][0]['ingredients'][] = ['name' => []];
    $mixedSnapshot['steps'][] = ['instruction' => []];
    rcModuleSame(recipeHistoryRestoreSnapshot($pdo, 5, $mixedSnapshot, null), '', 'Valid historical content survives unrelated malformed entries');
    rcModuleSame(recipeStructureSnapshot($pdo, 5), $afterValidRestore, 'Preflight and insertion use the same named-ingredient and instruction rules');

    foreach (['draft', 'pending'] as $offset => $status) {
        $id = 6 + $offset;
        rcModuleSeedRecipe($pdo, $id, $status);
        rcModuleSame(recipeHistoryRestoreSnapshot($pdo, $id, $emptySnapshot, null), '', 'Incomplete history remains restorable for ' . $status);
        rcModuleSame(recipeHasPublishableStructure($pdo, $id), false, 'Unpublished workflow allows an incomplete ' . $status . ' recipe');
        rcModuleSame(
            $pdo->query('SELECT status FROM cms_recipes WHERE id = ' . $id)->fetchColumn(),
            $status,
            'History restore does not publish or change the ' . $status . ' workflow'
        );
    }
    rcModuleSame(
        recipeRestoreStructure($pdo, 5, ['version' => 2, 'groups' => [], 'steps' => []]),
        false,
        'Unsupported snapshot format is rejected without mutating a recipe'
    );
    rcModuleSame(recipeRestoreStructure($pdo, 99999, $validSnapshot), false, 'Missing recipe cannot acquire orphaned restored structure');
    rcModuleSame($pdo->inTransaction(), false, 'Missing-target restore closes its owned transaction');

    $backupsBeforeRestoreFailure = (int)$pdo->query('SELECT COUNT(*) FROM cms_recipe_structure_snapshots')->fetchColumn();
    $pdo->exec("CREATE TRIGGER rc_restore_insert_failure BEFORE INSERT ON cms_recipe_steps BEGIN SELECT RAISE(ABORT, 'simulated restore failure'); END");
    $restoreDatabaseFailure = false;
    try {
        recipeHistoryRestoreSnapshot($pdo, 5, $validSnapshot, null);
    } catch (PDOException) {
        $restoreDatabaseFailure = true;
    }
    rcModuleSame($restoreDatabaseFailure, true, 'Restore SQL failures are not reported as validation or success');
    rcModuleSame(recipeStructureSnapshot($pdo, 5), $afterValidRestore, 'Failed restore rolls back replaced groups, ingredients and steps');
    rcModuleSame(
        (int)$pdo->query('SELECT COUNT(*) FROM cms_recipe_structure_snapshots')->fetchColumn(),
        $backupsBeforeRestoreFailure,
        'Failed restore rolls back its backup together with all content writes'
    );
    rcModuleSame($pdo->inTransaction(), false, 'Restore SQL failure leaves no transaction open');
    $pdo->exec('DROP TRIGGER rc_restore_insert_failure');

    echo 'RC module state self-test OK (' . $rcChecks . " checks; isolated SQLite, captured mail, production functions/templates)\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'RC module state self-test failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
