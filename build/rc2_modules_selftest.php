<?php

declare(strict_types=1);

namespace KoraRc2ModulesTest;

use PDO;
use RuntimeException;

// Execute production helpers, handlers and views against an isolated database.
// No application bootstrap, persistent writes or outgoing mail are used.
define('BASE_URL', '');
date_default_timezone_set('Europe/Prague');
$checks = 0;
$capabilities = [];
$disabledModules = [];
$writableBlogs = [5];

function same(mixed $actual, mixed $expected, string $label): void
{
    $GLOBALS['checks']++;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': ' . var_export($actual, true));
    }
}
function source(string $path): string
{
    return (string)file_get_contents(dirname(__DIR__) . '/' . $path);
}
function evaluate(string $code, array $variables = []): mixed
{
    extract($variables, EXTR_SKIP);
    return eval('namespace ' . __NAMESPACE__ . '; use \PDO; use \DateTime; use \DateTimeImmutable; ' . $code);
}
function loadFunction(string $path, string $name): void
{
    if (preg_match('/^function ' . preg_quote($name, '/') . '\b.*?^\}/ms', source($path), $match) !== 1) {
        throw new RuntimeException('Missing production function ' . $name);
    }
    evaluate($match[0]);
}
final class Response extends RuntimeException
{
}
function runFile(string $path, array $variables = []): string
{
    $code = preg_replace('/^require_once [^\r\n]+;\R/m', '', source($path));
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
function currentUserId(): ?int
{
    return 7;
}
function currentUserHasCapability(string $capability): bool
{
    return in_array($capability, $GLOBALS['capabilities'], true);
}
function isModuleEnabled(string $module): bool
{
    return !in_array($module, $GLOBALS['disabledModules'], true);
}
function canCurrentUserWriteToBlog(int $id): bool
{
    return in_array($id, $GLOBALS['writableBlogs'], true);
}
function requireCapability(string $capability, string $message = ''): void
{
    if (!currentUserHasCapability($capability)) {
        throw new Response('forbidden');
    }
}
function requireModuleEnabled(string $module): void
{
    if (!isModuleEnabled($module)) {
        throw new Response('forbidden');
    }
}
function verifyCsrf(): void
{
    if (($_POST['csrf_token'] ?? '') !== 'test-csrf') {
        throw new Response('csrf-rejected');
    }
}
function db_connect(): PDO
{
    return $GLOBALS['testDb'];
}
function inputInt(string $source, string $name): ?int
{
    $value = filter_var(($source === 'post' ? $_POST : $_GET)[$name] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $value === false ? null : $value;
}
function internalRedirectTarget(string $url, string $fallback): string
{
    return str_starts_with($url, '/') && !str_starts_with($url, '//') ? $url : $fallback;
}
function appendUrlQuery(string $url, array $query): string
{
    return $url . '?' . http_build_query($query);
}
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrfToken(): string
{
    return 'test-csrf';
}
function cspNonce(): string
{
    return 'test-nonce';
}
function honeypotField(): string
{
    return '';
}
function adminHeader(string $title): void
{
}
function adminFooter(): void
{
}
function newWindowLinkSrOnlySuffix(): string
{
    return ' (nové okno)';
}
function pollSlug(string $value): string
{
    return $value;
}
function uniquePollSlug(PDO $pdo, string $value, ?int $id): string
{
    return $value;
}
function hydratePollPresentation(array $poll): array
{
    return $poll + ['excerpt' => ''];
}
function hydrateGalleryPhotoPresentation(array $photo): array
{
    return $photo + ['thumb_url' => '/image', 'public_path' => '/photo', 'label' => 'Photo'];
}
function hydrateGalleryAlbumPresentation(array $album): array
{
    return $album;
}
function normalizeHttpExternalUrl(string $url, bool $relative = false): string
{
    return preg_match('~^https?://~', $url) === 1 ? $url : '';
}
function logAction(string $action, string $details): void
{
    $GLOBALS['issueEffects'][] = ['log', $action, $details];
}
function githubIssueBridgeReady(): bool
{
    return $GLOBALS['issueBridgeReady'];
}
function githubIssueCreate(string $repository, string $title, string $body, array $labels = []): array
{
    $GLOBALS['issueEffects'][] = ['api', $repository, $title, $body, $labels];
    return $GLOBALS['issueApiResult'];
}
function formSubmissionHistoryCreate(PDO $pdo, int $id, ?int $actorId, string $event, string $message): void
{
    $GLOBALS['issueEffects'][] = ['history', $id, $event];
}
function dispatchFormWebhook(array $form, string $event, array $submission, array $fields, array $data, array $extra = []): void
{
    $GLOBALS['issueEffects'][] = ['webhook', $event, $submission['id']];
}
function koraLog(string $level, string $message, array $context = []): void
{
    throw new RuntimeException('Unexpected production failure: ' . $message);
}
function saveRevision(PDO $pdo, string $type, int $id, array $old, array $new): void
{
}
function upsertPathRedirect(PDO $pdo, string $old, string $new): void
{
}
function pollRevisionSnapshot(array $poll, array $options): array
{
    return [];
}
function pollPublicPath(array $poll): string
{
    return '/polls/' . $poll['slug'];
}
function assertAriaReferences(string $html): \DOMXPath
{
    $doc = new \DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new \DOMXPath($doc);
    foreach ($xpath->query('//*[@aria-describedby or @aria-labelledby]') as $element) {
        foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
            foreach (preg_split('/\s+/', trim($element->getAttribute($attribute))) as $id) {
                if ($id !== '') {
                    same($xpath->query('//*[@id="' . $id . '"]')->length, 1, 'Unique existing ARIA reference ' . $id);
                }
            }
        }
    }
    return $xpath;
}

try {
    set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
        throw new \ErrorException($message, 0, $level, $file, $line);
    });
    foreach (['revisionEntityDefinitions', 'canReadEntityRevisions'] as $function) {
        loadFunction('lib/revisions.php', $function);
    }
    foreach (['canManageOwnBlogOnly', 'canManageOwnNewsOnly'] as $function) {
        loadFunction('auth.php', $function);
    }
    foreach (['foodOrderChoiceKey', 'foodValidateOrderQuantities', 'foodBuildOrderSnapshot', 'normalizeFoodCurrency',
        'foodOrderFulfillmentDefinitions', 'normalizeFoodOrderFulfillmentModes', 'foodPriceLabel',
        'foodCardCanAcceptOrders', 'foodOrderSelectableChoices', 'foodOrderSelectableItems', 'foodItemVariantDisplayLabel',
        'normalizeGalleryLicenseUrl', 'pollVoteMode', 'pollResultsVisibility', 'pollVoteModeOptions', 'pollResultsVisibilityOptions'] as $function) {
        loadFunction('lib/presentation.php', $function);
    }
    foreach (['adminEditorFormFields', 'adminEditorFormFlashStore', 'adminEditorFormFlashTake',
        'adminFieldHasError', 'adminFieldErrorId', 'adminFieldAttributes', 'adminRenderFieldError'] as $function) {
        loadFunction('admin/layout.php', $function);
    }

    $entity = ['author_id' => '7', 'blog_id' => '5'];
    foreach (revisionEntityDefinitions() as $type => $definition) {
        $capabilities = ['admin_access'];
        same(canReadEntityRevisions($type, $entity), false, $type . ' rejects unrelated staff');
        $capabilities[] = $definition['capability'];
        same(canReadEntityRevisions($type, $entity), true, $type . ' allows its editor');
        $disabledModules = [$definition['module'] !== '' ? $definition['module'] : 'blog'];
        same(canReadEntityRevisions($type, $entity), false, $type . ' respects disabled module');
        $disabledModules = [];
    }
    same(canReadEntityRevisions('unknown', $entity), false, 'Unknown entity denied');
    $capabilities = ['blog_manage_own', 'news_manage_own'];
    same(canReadEntityRevisions('article', ['author_id' => 8, 'blog_id' => 5]), false, 'Another author article denied');
    same(canReadEntityRevisions('article', ['author_id' => 7, 'blog_id' => 6]), false, 'Removed blog membership denied');
    same(canReadEntityRevisions('news', ['author_id' => 8]), false, 'Another author news denied');
    $capabilities = ['content_manage_shared'];
    $disabledModules = ['blog'];
    same(canReadEntityRevisions('page', ['blog_id' => null]), true, 'Global pages do not require blog');
    $disabledModules = [];

    $choice = ['item_id' => 1, 'item_title' => 'Polévka', 'price_amount' => '10.10', 'price_currency' => 'CZK'];
    $choices = ['item-1' => $choice, 'variant-3' => $choice + ['variant_id' => 3]];
    foreach (['120', '-1', '1.5', '1e1', 'abc', ['2'], true, 1.5] as $invalid) {
        $result = foodValidateOrderQuantities($choices, ['item-1' => $invalid]);
        same(isset($result['errors']['qty-item-1']), true, 'Invalid quantity rejected');
        same($result['quantities'], [], 'Invalid quantity not clamped or coerced');
    }
    $result = foodValidateOrderQuantities($choices, ['item-1' => '99', 'variant-3' => '2']);
    same($result['quantities'], ['item-1' => 99, 'variant-3' => 2], 'Item and variant remain distinct');
    same($result['errors'], [], 'Valid quantities accepted');
    same(foodValidateOrderQuantities($choices, [1 => '2'])['quantities'], ['item-1' => 2], 'Legacy numeric key supported');
    same(isset(foodValidateOrderQuantities($choices, [1 => '2', 'item-1' => '3'])['errors']['items']), true, 'Aliased duplicate rejected');
    same(isset(foodValidateOrderQuantities($choices, ['item-1' => '1', 'variant-99' => '1'])['errors']['items']), true, 'Stale basket is not partially accepted');
    same(foodValidateOrderQuantities($choices, ['item-99' => '0', 'item-1' => ''])['errors'], [], 'Unselected old fields harmless');
    same(isset(foodValidateOrderQuantities($choices, 'invalid')['errors']['items']), true, 'Non-array selection rejected');
    same(foodBuildOrderSnapshot($choices, ['item-1' => 3])['total'], '30.30', 'Complete total');
    $choices['variant-3']['price_amount'] = null;
    same(foodBuildOrderSnapshot($choices, ['item-1' => 3, 'variant-3' => 1])['total'], null, 'Unknown price cannot become zero');
    $choices['variant-3']['price_amount'] = '0.00';
    same(foodBuildOrderSnapshot($choices, ['item-1' => 3, 'variant-3' => 1])['total'], '30.30', 'Explicit zero is a known price');
    $choices['variant-3']['price_currency'] = 'EUR';
    same(foodBuildOrderSnapshot($choices, ['item-1' => 3, 'variant-3' => 1])['total'], null, 'Mixed currencies not added');

    foreach (['contact', 'food-order'] as $view) {
        $html = runFile('themes/default/views/modules/' . $view . '.php', [
            'success' => true, 'errors' => ['Uloženo, oznámení se nepodařilo odeslat.'], 'referenceCode' => 'TEST-123',
        ]);
        same(str_contains($html, 'Uloženo, oznámení se nepodařilo odeslat.'), true, $view . ' shows notification failure');
        same(str_contains($html, 'znovu neodesílejte'), true, $view . ' discourages duplicates');
        same(str_contains($html, '<form'), false, $view . ' saved message is not an editable retry');
        assertAriaReferences($html);
    }
    $item = ['id' => 1, 'title' => 'Polévka', 'price_amount' => '10.10', 'price_currency' => 'CZK'];
    $variantItem = ['id' => 2, 'title' => 'Varianta', 'variants' => [['id' => 3, 'label' => 'Velká', 'price_label' => '']]];
    $html = runFile('themes/default/views/modules/food-order.php', [
        'card' => ['title' => 'Menu', 'slug' => 'menu', 'orders_enabled' => 1, 'is_publicly_visible' => true,
            'sections' => [['items' => [$item, $variantItem]]]],
        'selectableItems' => [$item, $variantItem], 'success' => false,
        'fieldErrors' => ['qty-item-1' => 'Celé číslo.', 'qty-variant-3' => 'Celé číslo.'],
        'formData' => ['quantities' => ['item-1' => '120', 'variant-3' => '1.5']], 'captchaExpr' => '2 + 2',
    ]);
    $xpath = assertAriaReferences($html);
    same($xpath->query('//input[@name="qty[item-1]" and @aria-invalid="true" and @value="120"]')->length, 1, 'Item preserves rejected quantity and field error');
    same($xpath->query('//input[@name="qty[variant-3]" and @aria-invalid="true" and @value="1.5"]')->length, 1, 'Variant preserves rejected quantity and field error');

    $testDb = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_STRINGIFY_FETCHES => getenv('KORA_RC_STRINGIFY_FETCHES') === '1']);
    $testDb->exec('CREATE TABLE cms_polls (id INTEGER PRIMARY KEY, question TEXT, slug TEXT, description TEXT, vote_mode TEXT,
        max_choices INTEGER, results_visibility TEXT, status TEXT, start_date TEXT, end_date TEXT, meta_title TEXT, meta_description TEXT)');
    $testDb->exec("INSERT INTO cms_polls VALUES (1,'Original','original','','single',NULL,'after_vote','active',NULL,NULL,'','')");
    $testDb->exec('CREATE TABLE cms_poll_options (id INTEGER PRIMARY KEY, poll_id INTEGER, option_text TEXT, sort_order INTEGER)');
    $testDb->exec("INSERT INTO cms_poll_options VALUES (10,1,'One',0),(11,1,'Two',1),(20,2,'Foreign',0)");
    $testDb->exec('CREATE TABLE cms_poll_votes (poll_id INTEGER, option_id INTEGER, ip_hash TEXT)');
    $testDb->exec('CREATE TABLE cms_poll_vote_sessions (poll_id INTEGER)');
    $capabilities = ['content_manage_shared'];
    $post = ['csrf_token' => 'test-csrf', 'id' => '1', 'question' => 'Rozepsaná otázka', 'slug' => 'updated',
        'description' => 'Rozepsaný popis', 'options' => ['První', 'Druhá'], 'option_ids' => ['10', '11'],
        'start_date' => '2026-10-02', 'start_time' => '15:42'];
    foreach ([['10', '10'], ['10', '20'], ['10', '999'], ['10', ['11']], ['10', '-1']] as $ids) {
        $_POST = array_replace($post, ['option_ids' => $ids]);
        $_GET = ['id' => '1', 'err' => 'invalid_options'];
        $_SESSION = [];
        $before = $testDb->query('SELECT * FROM cms_poll_options ORDER BY id')->fetchAll();
        same(str_contains(runFile('admin/polls_save.php'), 'err=invalid_options'), true, 'Reject invalid option references');
        same($testDb->query('SELECT * FROM cms_poll_options ORDER BY id')->fetchAll(), $before, 'Rejection changes no options');
        same($testDb->query('SELECT question FROM cms_polls WHERE id=1')->fetchColumn(), 'Original', 'Rejection changes no poll');
        $html = runFile('admin/polls_form.php');
        $xpath = assertAriaReferences($html);
        same($xpath->query('//input[@name="question"]')->item(0)->getAttribute('value'), $post['question'], 'Poll question survives redirect');
        same($xpath->query('//input[@name="start_time"]')->item(0)->getAttribute('value'), '15:42', 'Poll split time survives redirect');
        same(str_contains($html, 'První'), true, 'Poll option text survives redirect');
        same(adminEditorFormFlashTake('poll', 1), [], 'Poll flash consumed once');
    }
    $_POST = array_replace($post, ['id' => '', 'option_ids' => ['0', '0'], 'start_time' => '99:99']);
    $_GET = ['err' => 'dates'];
    $_SESSION = [];
    same(str_contains(runFile('admin/polls_save.php'), 'err=dates'), true, 'Invalid time rejects new poll');
    same((int)$testDb->query('SELECT COUNT(*) FROM cms_polls')->fetchColumn(), 1, 'Invalid new poll was not inserted');
    $xpath = assertAriaReferences(runFile('admin/polls_form.php'));
    same($xpath->query('//input[@name="question"]')->item(0)->getAttribute('value'), $post['question'], 'New poll question survives date error');
    same($xpath->query('//input[@name="start_time" and @aria-invalid="true"]')->item(0)->getAttribute('value'), '99:99', 'New poll date error retains raw value');
    same($xpath->query('//input[@name="options[]"]')->item(0)->getAttribute('value'), 'První', 'New poll option survives date error');
    $_POST = $post;
    same(runFile('admin/polls_save.php'), '/admin/polls.php', 'Valid poll edit succeeds');
    same($testDb->query('SELECT option_text FROM cms_poll_options WHERE id=10')->fetchColumn(), 'První', 'Valid option updated');

    $testDb->exec('CREATE TABLE cms_gallery_albums (id INTEGER PRIMARY KEY, name TEXT)');
    $testDb->exec("INSERT INTO cms_gallery_albums VALUES (1,'Album'),(2,'Other')");
    $testDb->exec('CREATE TABLE cms_gallery_photos (id INTEGER PRIMARY KEY, album_id INTEGER, title TEXT, slug TEXT,
        alt_text TEXT, caption TEXT, description TEXT, credit TEXT, license_label TEXT, license_url TEXT,
        taken_at TEXT, location_label TEXT, sort_order INTEGER, is_published INTEGER)');
    $testDb->exec("INSERT INTO cms_gallery_photos VALUES (1,1,'Original','original','','','','','','',NULL,'',0,1)");
    $_POST = ['csrf_token' => 'test-csrf', 'mode' => 'edit', 'id' => '1', 'album_id' => '1', 'title' => 'Nový titulek',
        'slug' => 'new', 'alt_text' => 'Výstižný alternativní text', 'caption' => 'Nový popisek', 'description' => 'Dlouhý popis',
        'credit' => 'Autor', 'license_url' => 'javascript:bad', 'taken_at' => '2026-09-05'];
    $_SESSION = [];
    same(str_contains(runFile('admin/gallery_photo_save.php'), 'err=license_url'), true, 'Invalid photo license rejected');
    same($testDb->query('SELECT title FROM cms_gallery_photos WHERE id=1')->fetchColumn(), 'Original', 'Invalid metadata leaves DB intact');
    same(adminEditorFormFlashTake('gallery_photo', 1, 2), [], 'Metadata flash does not leak to another album');
    $_GET = ['id' => '1', 'album_id' => '1', 'err' => 'license_url'];
    $html = runFile('admin/gallery_photo_form.php');
    $xpath = assertAriaReferences($html);
    same($xpath->query('//input[@name="alt_text"]')->item(0)->getAttribute('value'), $_POST['alt_text'], 'Photo alt survives redirect');
    same($xpath->query('//textarea[@name="description"]')->item(0)->textContent, $_POST['description'], 'Photo description survives redirect');
    same($xpath->query('//input[@name="is_published" and @checked]')->length, 0, 'Unchecked publication survives redirect');
    same(adminEditorFormFlashTake('gallery_photo', 1, 1), [], 'Metadata flash consumed once');
    foreach (['githubIssueDraftValues', 'githubIssueDraftErrorFields', 'githubIssueDraftStore', 'githubIssueDraftPull',
        'normalizeGitHubRepository', 'formSubmissionHasGitHubIssue'] as $function) {
        loadFunction('lib/github.php', $function);
    }
    $testDb->sqliteCreateFunction('NOW', static fn (): string => '2026-09-05 12:00:00');
    $testDb->exec('CREATE TABLE cms_forms (id INTEGER PRIMARY KEY, title TEXT, slug TEXT)');
    $testDb->exec("INSERT INTO cms_forms VALUES (1, 'Issue test', 'issue-test')");
    $testDb->exec('CREATE TABLE cms_form_submissions (id INTEGER PRIMARY KEY, form_id INTEGER, data TEXT,
        github_issue_repository TEXT, github_issue_number INTEGER, github_issue_url TEXT, updated_at TEXT)');
    $testDb->exec("INSERT INTO cms_form_submissions VALUES (17, 1, '{}', '', NULL, '', NULL)");
    $testDb->exec('CREATE TABLE cms_form_fields (id INTEGER PRIMARY KEY, form_id INTEGER, name TEXT, sort_order INTEGER)');
    $capabilities = ['settings_manage'];
    $issueBridgeReady = true;
    $issueApiResult = ['ok' => true, 'status' => 201, 'repository' => 'owner/repo', 'number' => 123, 'url' => 'https://github.com/owner/repo/issues/123'];
    $post = ['csrf_token' => 'test-csrf', 'id' => '17', 'redirect' => '/admin/form_submission.php?id=17',
        'issue_action' => 'create', 'repository' => 'owner/repo', 'title' => 'Můj návrh', 'body' => "Text\nissue", 'labels' => 'bug, review'];
    $issueEffects = [];
    $before = $testDb->query('SELECT * FROM cms_form_submissions')->fetchAll();
    foreach ([[], ['confirm_form_submission_issue_create_17' => ['1']], ['confirm_form_submission_issue_create_18' => '1']] as $confirmation) {
        $_POST = $post + $confirmation;
        same(str_contains(runFile('admin/form_submission_issue.php'), 'issue=confirm_required'), true, 'Unconfirmed issue rejected before API');
        same($issueEffects, [], 'Rejection has no API, history, log or webhook effect');
        same($testDb->query('SELECT * FROM cms_form_submissions')->fetchAll(), $before, 'Rejected issue leaves data untouched');
        same(githubIssueDraftPull(17)['draft'], githubIssueDraftValues($post), 'Rejected issue preserves all fields');
    }
    $confirmed = $post + ['confirm_form_submission_issue_create_17' => '1'];
    $_POST = array_replace($confirmed, ['csrf_token' => 'wrong']);
    same(runFile('admin/form_submission_issue.php'), 'csrf-rejected', 'CSRF remains mandatory');
    $_POST = $confirmed;
    $capabilities = ['admin_access'];
    same(runFile('admin/form_submission_issue.php'), 'forbidden', 'Confirmation does not bypass capability');
    $capabilities = ['settings_manage'];
    $_POST = array_replace($confirmed, ['issue_action' => 'unexpected']);
    same(str_contains(runFile('admin/form_submission_issue.php'), 'issue=invalid_action'), true, 'Unknown action never falls through to creation');
    $_POST = array_replace($confirmed, ['title' => ' ']);
    same(str_contains(runFile('admin/form_submission_issue.php'), 'issue=invalid'), true, 'Confirmed but invalid draft is rejected');
    same(githubIssueDraftPull(17)['error_fields'], ['github_issue_title'], 'Only invalid title is identified');
    $_POST = $confirmed;
    $issueBridgeReady = false;
    same(str_contains(runFile('admin/form_submission_issue.php'), 'issue=not_ready'), true, 'Disabled bridge rejects even confirmed drafts');
    same(githubIssueDraftPull(17)['draft'], githubIssueDraftValues($post), 'Disabled bridge retains draft');
    same($issueEffects, [], 'All preflight failures remain effect-free');
    $issueBridgeReady = true;
    $issueApiResult = ['ok' => false, 'status' => 0, 'error' => 'Simulated timeout'];
    same(str_contains(runFile('admin/form_submission_issue.php'), 'issue=failed'), true, 'API failure uses PRG');
    same(array_column($issueEffects, 0), ['api'], 'Failed API cannot write history, log or dispatch webhook');
    same($testDb->query('SELECT * FROM cms_form_submissions')->fetchAll(), $before, 'Failed API leaves link unchanged');
    same(githubIssueDraftPull(17)['draft'], githubIssueDraftValues($post), 'Failed API retains draft');
    $issueEffects = [];
    $issueApiResult = ['ok' => true, 'status' => 201, 'repository' => 'owner/repo', 'number' => 123, 'url' => 'https://github.com/owner/repo/issues/123'];
    githubIssueDraftStore(17, githubIssueDraftValues($post));
    same(str_contains(runFile('admin/form_submission_issue.php'), 'issue=created'), true, 'Confirmed creation completes');
    same($issueEffects[0], ['api', 'owner/repo', 'Můj návrh', "Text\nissue", ['bug', 'review']], 'API receives reviewed draft');
    same(array_column($issueEffects, 0), ['api', 'history', 'log', 'webhook'], 'Confirmed effects happen once in order');
    same($testDb->query('SELECT github_issue_url FROM cms_form_submissions WHERE id=17')->fetchColumn(), $issueApiResult['url'], 'Created issue is linked');
    same(githubIssueDraftPull(17), [], 'Success clears old draft');
    $issueEffects = [];
    same(str_contains(runFile('admin/form_submission_issue.php'), 'issue=exists'), true, 'Repeat POST cannot create an already linked issue');
    same($issueEffects, [], 'Repeat POST has no effects');

    echo 'RC2 module regressions OK: ' . $checks . " checks\n";
} catch (\Throwable $error) {
    fwrite(STDERR, $error . PHP_EOL);
    exit(1);
}
