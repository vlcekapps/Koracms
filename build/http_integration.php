<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ob_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../admin/settings_shared.php';
require_once __DIR__ . '/http_test_helpers.php';

$baseUrl = rtrim($argv[1] ?? 'http://localhost', '/');
$pdo = db_connect();
$failures = 0;
$createdUsers = [];
$createdBlogs = [];
$createdCategories = [];
$createdTags = [];
$createdArticles = [];
$createdFormIds = [];
$createdFormSubmissionIds = [];
$createdPageIds = [];
$createdGalleryAlbumIds = [];
$createdMediaIds = [];
$createdBoardIds = [];
$createdResourceIds = [];
$createdWidgetIds = [];
$createdTempFiles = [];

/**
 * @param array<int, string> $issues
 */
function httpIntegrationPrintResult(string $label, array $issues, int &$failures): void
{
    echo '=== ' . $label . " ===\n";
    if ($issues === []) {
        echo "OK\n";
        return;
    }

    $failures++;
    foreach ($issues as $issue) {
        echo '- ' . $issue . "\n";
    }
}

function httpIntegrationStatusCode(array $response): int
{
    if (preg_match('/\s(\d{3})\s/', (string)($response['status'] ?? ''), $matches) === 1) {
        return (int)$matches[1];
    }

    return 0;
}

function httpIntegrationSettingValue(PDO $pdo, string $key): string
{
    $stmt = $pdo->prepare("SELECT value FROM cms_settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return is_string($value) ? $value : '';
}

/**
 * @param array<string, string> $settings
 */
function httpIntegrationRestoreSettings(array $settings): void
{
    foreach ($settings as $settingKey => $settingValue) {
        saveSetting($settingKey, (string)$settingValue);
    }

    clearSettingsCache();
}

/**
 * @return array<string, string>
 */
function httpIntegrationSettingsPostFields(array $formState): array
{
    $fields = [
        'site_name' => (string)($formState['site_name'] ?? ''),
        'site_description' => (string)($formState['site_description'] ?? ''),
        'contact_email' => (string)($formState['contact_email'] ?? ''),
        'site_profile' => (string)($formState['site_profile'] ?? currentSiteProfileKey()),
        'board_public_label' => (string)($formState['board_public_label'] ?? ''),
        'github_issues_repository' => (string)($formState['github_issues_repository'] ?? ''),
        'news_per_page' => (string)($formState['news_per_page'] ?? '10'),
        'blog_per_page' => (string)($formState['blog_per_page'] ?? '10'),
        'events_per_page' => (string)($formState['events_per_page'] ?? '10'),
        'chat_retention_days' => (string)($formState['chat_retention_days'] ?? '0'),
        'content_editor' => (string)($formState['content_editor'] ?? 'html'),
        'ga4_measurement_id' => (string)($formState['ga4_measurement_id'] ?? ''),
        'custom_head_code' => (string)($formState['custom_head_code'] ?? ''),
        'custom_footer_code' => (string)($formState['custom_footer_code'] ?? ''),
        'social_facebook' => (string)($formState['social_facebook'] ?? ''),
        'social_youtube' => (string)($formState['social_youtube'] ?? ''),
        'social_instagram' => (string)($formState['social_instagram'] ?? ''),
        'social_twitter' => (string)($formState['social_twitter'] ?? ''),
        'og_image_default' => (string)($formState['og_image_default'] ?? ''),
        'home_intro' => (string)($formState['home_intro'] ?? ''),
        'cookie_consent_text' => (string)($formState['cookie_consent_text'] ?? ''),
        'maintenance_text' => (string)($formState['maintenance_text'] ?? ''),
    ];

    $checkboxFields = [
        'public_registration_enabled',
        'github_issues_enabled',
        'notify_form_submission',
        'notify_pending_content',
        'notify_chat_message',
        'cookie_consent_enabled',
        'maintenance_mode',
        'apply_site_profile',
        'blog_authors_index_enabled',
        'comments_enabled',
        'comment_notify_admin',
        'comment_notify_author_approve',
    ];
    foreach ($checkboxFields as $checkboxField) {
        if (($formState[$checkboxField] ?? '0') === '1') {
            $fields[$checkboxField] = '1';
        }
    }

    if (isModuleEnabled('blog')) {
        $fields['comment_moderation_mode'] = (string)($formState['comment_moderation_mode'] ?? 'always');
        $fields['comment_close_days'] = (string)($formState['comment_close_days'] ?? '0');
        $fields['comment_notify_email'] = (string)($formState['comment_notify_email'] ?? '');
        $fields['comment_blocked_emails'] = (string)($formState['comment_blocked_emails'] ?? '');
        $fields['comment_spam_words'] = (string)($formState['comment_spam_words'] ?? '');
    }

    return $fields;
}

function httpIntegrationFieldHasAriaInvalid(string $html, string $fieldId): bool
{
    return preg_match('/id="' . preg_quote($fieldId, '/') . '"[^>]*aria-invalid="true"/i', $html) === 1;
}

function httpIntegrationCheckboxIsChecked(string $html, string $fieldId): bool
{
    $pattern = '/<input(?=[^>]*id="' . preg_quote($fieldId, '/') . '")(?=[^>]*\bchecked\b)[^>]*>/i';
    return preg_match($pattern, $html) === 1;
}

function httpIntegrationCreateTempFile(string $prefix, string $contents, array &$createdTempFiles): string
{
    $path = tempnam(sys_get_temp_dir(), $prefix);
    if ($path === false) {
        throw new RuntimeException('Nepodařilo se vytvořit dočasný soubor pro HTTP integration test.');
    }

    file_put_contents($path, $contents);
    $createdTempFiles[] = $path;
    return $path;
}

function httpIntegrationCreatePngFixtureFile(string $prefix, array &$createdTempFiles, int $width = 24, int $height = 24): string
{
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
        throw new RuntimeException('GD není k dispozici pro vytvoření PNG fixture v HTTP integration testu.');
    }

    $path = tempnam(sys_get_temp_dir(), $prefix);
    if ($path === false) {
        throw new RuntimeException('Nepodařilo se vytvořit dočasný PNG fixture soubor pro HTTP integration test.');
    }

    $image = imagecreatetruecolor($width, $height);
    if ($image === false) {
        @unlink($path);
        throw new RuntimeException('Nepodařilo se vytvořit GD image pro HTTP integration test.');
    }

    $background = imagecolorallocate($image, 20, 120, 220);
    $accent = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $background);
    imagerectangle($image, 1, 1, $width - 2, $height - 2, $accent);
    imagefilledellipse($image, (int)floor($width / 2), (int)floor($height / 2), max(6, (int)floor($width / 2)), max(6, (int)floor($height / 2)), $accent);

    $written = imagepng($image, $path);
    imagedestroy($image);

    if ($written !== true) {
        @unlink($path);
        throw new RuntimeException('Nepodařilo se zapsat PNG fixture pro HTTP integration test.');
    }

    $createdTempFiles[] = $path;
    return $path;
}

function httpIntegrationCreatePdfFixtureFile(string $prefix, array &$createdTempFiles): string
{
    $path = tempnam(sys_get_temp_dir(), $prefix);
    if ($path === false) {
        throw new RuntimeException('Nepodařilo se vytvořit dočasný PDF fixture soubor pro HTTP integration test.');
    }

    $pdfFixture = <<<PDF
%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >>
endobj
trailer
<< /Root 1 0 R >>
%%EOF
PDF;

    $written = file_put_contents($path, $pdfFixture);
    if ($written === false) {
        @unlink($path);
        throw new RuntimeException('Nepodařilo se zapsat PDF fixture pro HTTP integration test.');
    }

    $createdTempFiles[] = $path;
    return $path;
}

function httpIntegrationFetchMediaById(PDO $pdo, int $mediaId): ?array
{
    if ($mediaId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM cms_media WHERE id = ? LIMIT 1");
    $stmt->execute([$mediaId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function httpIntegrationFetchMediaByOriginalName(PDO $pdo, string $originalName): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM cms_media WHERE original_name = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$originalName]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function httpIntegrationExtractCaptchaAnswer(string $html): string
{
    if (preg_match('/Ověření:\s*kolik je\s*(\d+)\s*[×x*]\s*(\d+)\?/u', $html, $matches) !== 1) {
        return '';
    }

    return (string)(((int)$matches[1]) * ((int)$matches[2]));
}

/**
 * @return array<int, string>
 */
function httpIntegrationListStoredFormUploads(): array
{
    $directory = formUploadDirectory();
    if (!is_dir($directory)) {
        return [];
    }

    $entries = [];
    foreach (scandir($directory) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $directory . $entry;
        if (is_file($path)) {
            $entries[] = $entry;
        }
    }

    sort($entries);
    return $entries;
}

function httpIntegrationFetchLatestFormSubmissionByFormId(PDO $pdo, int $formId): ?array
{
    if ($formId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM cms_form_submissions WHERE form_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$formId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

try {
    $originalSettings = [
        'site_name' => httpIntegrationSettingValue($pdo, 'site_name'),
        'site_description' => httpIntegrationSettingValue($pdo, 'site_description'),
        'contact_email' => httpIntegrationSettingValue($pdo, 'contact_email'),
        'comment_notify_email' => httpIntegrationSettingValue($pdo, 'comment_notify_email'),
        'board_public_label' => httpIntegrationSettingValue($pdo, 'board_public_label'),
        'site_profile' => httpIntegrationSettingValue($pdo, 'site_profile'),
        'active_theme' => httpIntegrationSettingValue($pdo, 'active_theme'),
        'public_registration_enabled' => httpIntegrationSettingValue($pdo, 'public_registration_enabled'),
        'module_statistics' => httpIntegrationSettingValue($pdo, 'module_statistics'),
        'module_blog' => getSetting('module_blog', '0'),
        'module_gallery' => getSetting('module_gallery', '0'),
        'module_reservations' => getSetting('module_reservations', '0'),
        'module_forms' => getSetting('module_forms', '0'),
        'visitor_tracking_enabled' => getSetting('visitor_tracking_enabled', '0'),
        'visitor_counter_enabled' => getSetting('visitor_counter_enabled', '0'),
        'stats_retention_days' => getSetting('stats_retention_days', '90'),
    ];

    saveSetting('module_blog', '1');
    saveSetting('module_gallery', '1');
    saveSetting('module_reservations', '1');
    saveSetting('module_forms', '1');
    clearSettingsCache();

    $settingsIssues = [];
    $adminUserId = (int)($pdo->query("SELECT id FROM cms_users ORDER BY is_superadmin DESC, id ASC LIMIT 1")->fetchColumn() ?: 1);
    $adminSession = koraPrimeTestSession([
        'cms_logged_in' => true,
        'cms_superadmin' => true,
        'cms_user_id' => $adminUserId,
        'cms_user_name' => 'HTTP Integration Admin',
        'cms_user_role' => 'admin',
    ], 'kora-http-settings-admin');

    $baseSettingsState = settingsDefaultFormState();
    $settingsPostFields = httpIntegrationSettingsPostFields($baseSettingsState);

    $settingsSaveUrl = $baseUrl . BASE_URL . '/admin/settings_save.php';
    $settingsPageUrl = $baseUrl . BASE_URL . '/admin/settings.php';

    $validSiteName = 'HTTP Integration Site ' . date('His');
    $validSiteDescription = 'HTTP integration description ' . date('His');
    $validBoardLabel = 'Vývěska test ' . date('His');
    $validFields = $settingsPostFields;
    $validFields['csrf_token'] = $adminSession['csrf'];
    $validFields['site_name'] = $validSiteName;
    $validFields['site_description'] = $validSiteDescription;
    $validFields['board_public_label'] = $validBoardLabel;

    $validResponse = postUrl($settingsSaveUrl, $validFields, $adminSession['cookie'], 0);
    if (httpIntegrationStatusCode($validResponse) !== 302) {
        $settingsIssues[] = 'validní uložení nastavení nevrátilo 302 redirect';
    }
    if (!responseHasLocationHeader($validResponse['headers'], BASE_URL . '/admin/settings.php', $baseUrl)) {
        $settingsIssues[] = 'validní uložení nastavení nemíří zpět na settings.php';
    }
    clearSettingsCache();
    if (httpIntegrationSettingValue($pdo, 'site_name') !== $validSiteName) {
        $settingsIssues[] = 'validní uložení nenastavilo site_name';
    }
    if (httpIntegrationSettingValue($pdo, 'site_description') !== $validSiteDescription) {
        $settingsIssues[] = 'validní uložení nenastavilo site_description';
    }
    if (httpIntegrationSettingValue($pdo, 'board_public_label') !== $validBoardLabel) {
        $settingsIssues[] = 'validní uložení nenastavilo board_public_label';
    }

    $successPage = fetchUrl($settingsPageUrl, $adminSession['cookie'], 0);
    if (!str_contains($successPage['body'], 'Nastavení bylo uloženo.')) {
        $settingsIssues[] = 'po validním uložení se nezobrazila success flash zpráva';
    }
    $successPageSecondRead = fetchUrl($settingsPageUrl, $adminSession['cookie'], 0);
    if (str_contains($successPageSecondRead['body'], 'Nastavení bylo uloženo.')) {
        $settingsIssues[] = 'success flash zpráva po PRG nezmizela po druhém načtení';
    }

    $invalidEmailValue = 'neplatny-email';
    $invalidContactAttempt = 'HTTP Invalid Contact ' . date('His');
    $invalidEmailFields = $settingsPostFields;
    $invalidEmailFields['csrf_token'] = $adminSession['csrf'];
    $invalidEmailFields['site_name'] = $invalidContactAttempt;
    $invalidEmailFields['contact_email'] = $invalidEmailValue;
    $invalidEmailResponse = postUrl($settingsSaveUrl, $invalidEmailFields, $adminSession['cookie'], 0);
    if (httpIntegrationStatusCode($invalidEmailResponse) !== 302) {
        $settingsIssues[] = 'neplatný contact_email nevrátil PRG redirect';
    }
    clearSettingsCache();
    if (httpIntegrationSettingValue($pdo, 'site_name') !== $validSiteName) {
        $settingsIssues[] = 'neplatný contact_email způsobil částečné uložení site_name';
    }
    $invalidEmailPage = fetchUrl($settingsPageUrl, $adminSession['cookie'], 0);
    if (!str_contains($invalidEmailPage['body'], 'Neplatná e-mailová adresa pro kontakt.')) {
        $settingsIssues[] = 'neplatný contact_email nezobrazil chybovou zprávu';
    }
    if (!httpIntegrationFieldHasAriaInvalid($invalidEmailPage['body'], 'contact_email')) {
        $settingsIssues[] = 'neplatný contact_email neoznačil pole aria-invalid';
    }
    if (!str_contains($invalidEmailPage['body'], 'value="' . h($invalidEmailValue) . '"')) {
        $settingsIssues[] = 'neplatný contact_email nezachoval zadanou hodnotu po PRG';
    }

    $svgPath = httpIntegrationCreateTempFile(
        'kora-svg-',
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>',
        $createdTempFiles
    );
    $svgAttemptSiteName = 'HTTP Invalid SVG ' . date('His');
    $invalidSvgFields = $settingsPostFields;
    $invalidSvgFields['csrf_token'] = $adminSession['csrf'];
    $invalidSvgFields['site_name'] = $svgAttemptSiteName;
    $invalidSvgResponse = postMultipartUrl(
        $settingsSaveUrl,
        $invalidSvgFields,
        [
            'site_logo' => [
                'path' => $svgPath,
                'filename' => 'logo.svg',
                'type' => 'image/svg+xml',
            ],
        ],
        $adminSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($invalidSvgResponse) !== 302) {
        $settingsIssues[] = 'SVG upload loga nevrátil PRG redirect';
    }
    clearSettingsCache();
    if (httpIntegrationSettingValue($pdo, 'site_name') !== $validSiteName) {
        $settingsIssues[] = 'neplatný SVG upload způsobil částečné uložení ostatních nastavení';
    }
    $invalidSvgPage = fetchUrl($settingsPageUrl, $adminSession['cookie'], 0);
    if (!str_contains($invalidSvgPage['body'], 'Logo: nepodporovaný formát')) {
        $settingsIssues[] = 'SVG upload loga nezobrazil správnou validační chybu';
    }
    if (!httpIntegrationFieldHasAriaInvalid($invalidSvgPage['body'], 'site_logo')) {
        $settingsIssues[] = 'SVG upload loga neoznačil pole aria-invalid';
    }
    if (!str_contains($invalidSvgPage['body'], 'value="' . h($svgAttemptSiteName) . '"')) {
        $settingsIssues[] = 'SVG upload loga nezachoval ostatní vyplněné hodnoty po PRG';
    }

    $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+XgnQAAAAASUVORK5CYII=', true);
    if (!is_string($tinyPng) || $tinyPng === '') {
        throw new RuntimeException('Nepodařilo se připravit PNG fixture pro HTTP integration test.');
    }
    $oversizedFaviconPath = httpIntegrationCreateTempFile(
        'kora-png-',
        str_repeat($tinyPng, (int)ceil((300 * 1024) / max(1, strlen($tinyPng)))),
        $createdTempFiles
    );
    $oversizedAttemptDescription = 'HTTP Oversized Favicon ' . date('His');
    $oversizedFields = $settingsPostFields;
    $oversizedFields['csrf_token'] = $adminSession['csrf'];
    $oversizedFields['site_description'] = $oversizedAttemptDescription;
    $oversizedResponse = postMultipartUrl(
        $settingsSaveUrl,
        $oversizedFields,
        [
            'site_favicon' => [
                'path' => $oversizedFaviconPath,
                'filename' => 'favicon.png',
                'type' => 'image/png',
            ],
        ],
        $adminSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($oversizedResponse) !== 302) {
        $settingsIssues[] = 'příliš velká favicona nevrátila PRG redirect';
    }
    clearSettingsCache();
    if (httpIntegrationSettingValue($pdo, 'site_description') !== $validSiteDescription) {
        $settingsIssues[] = 'příliš velká favicona způsobila částečné uložení site_description';
    }
    $oversizedPage = fetchUrl($settingsPageUrl, $adminSession['cookie'], 0);
    if (!str_contains($oversizedPage['body'], 'Favicon může mít nejvýše 256 KB.')) {
        $settingsIssues[] = 'příliš velká favicona nezobrazila validační chybu';
    }
    if (!httpIntegrationFieldHasAriaInvalid($oversizedPage['body'], 'site_favicon')) {
        $settingsIssues[] = 'příliš velká favicona neoznačila pole aria-invalid';
    }

    httpIntegrationPrintResult('settings_save_http', $settingsIssues, $failures);
    httpIntegrationRestoreSettings($originalSettings);

    $settingsModulesIssues = [];
    $settingsModulesUrl = $baseUrl . BASE_URL . '/admin/settings_modules.php';
    $settingsModuleKeys = ['blog', 'news', 'chat', 'contact', 'gallery', 'events', 'podcast', 'places', 'newsletter', 'downloads', 'food', 'polls', 'faq', 'board', 'reservations', 'forms', 'statistics'];

    saveSetting('module_forms', '0');
    clearSettingsCache();

    $settingsModulesPage = fetchUrl($settingsModulesUrl, $adminSession['cookie'], 0);
    if (httpIntegrationStatusCode($settingsModulesPage) !== 200) {
        $settingsModulesIssues[] = 'správa modulů nevrátila 200 před uložením';
    }
    $settingsModulesCsrf = extractHiddenInputValue($settingsModulesPage['body'], 'csrf_token');
    if ($settingsModulesCsrf === '') {
        $settingsModulesIssues[] = 'správa modulů nevykreslila csrf_token';
    }
    if (httpIntegrationCheckboxIsChecked($settingsModulesPage['body'], 'module_forms')) {
        $settingsModulesIssues[] = 'test správy modulů nezačínal s vypnutým modulem formulářů';
    }

    $settingsModulesFields = [
        'csrf_token' => $settingsModulesCsrf,
        'stats_retention_days' => httpIntegrationSettingValue($pdo, 'stats_retention_days') !== ''
            ? httpIntegrationSettingValue($pdo, 'stats_retention_days')
            : '90',
    ];
    foreach ($settingsModuleKeys as $moduleKey) {
        $settingValue = getSetting('module_' . $moduleKey, '0');
        if ($moduleKey === 'forms') {
            $settingValue = '1';
        }
        if ($settingValue === '1') {
            $settingsModulesFields['module_' . $moduleKey] = '1';
        }
    }
    if (getSetting('visitor_tracking_enabled', '0') === '1') {
        $settingsModulesFields['visitor_tracking_enabled'] = '1';
    }
    if (getSetting('visitor_counter_enabled', '0') === '1') {
        $settingsModulesFields['visitor_counter_enabled'] = '1';
    }

    $settingsModulesSaveResponse = postUrl($settingsModulesUrl, $settingsModulesFields, $adminSession['cookie'], 0);
    if (httpIntegrationStatusCode($settingsModulesSaveResponse) !== 302) {
        $settingsModulesIssues[] = 'uložení správy modulů nevrátilo 302 redirect';
    }
    if (!responseHasLocationHeader($settingsModulesSaveResponse['headers'], BASE_URL . '/admin/settings_modules.php', $baseUrl)) {
        $settingsModulesIssues[] = 'uložení správy modulů nemíří zpět na settings_modules.php';
    }
    clearSettingsCache();
    if (getSetting('module_forms', '0') !== '1') {
        $settingsModulesIssues[] = 'první uložení správy modulů neuložilo povolení modulu formulářů';
    }

    $settingsModulesSuccessPage = fetchUrl($settingsModulesUrl, $adminSession['cookie'], 0);
    if (!str_contains($settingsModulesSuccessPage['body'], 'Nastavení modulů bylo uloženo.')) {
        $settingsModulesIssues[] = 'správa modulů po uložení nezobrazila success flash zprávu';
    }
    if (!httpIntegrationCheckboxIsChecked($settingsModulesSuccessPage['body'], 'module_forms')) {
        $settingsModulesIssues[] = 'správa modulů po prvním uložení stále nevykreslila povolený modul formulářů jako checked';
    }

    $settingsModulesSecondRead = fetchUrl($settingsModulesUrl, $adminSession['cookie'], 0);
    if (str_contains($settingsModulesSecondRead['body'], 'Nastavení modulů bylo uloženo.')) {
        $settingsModulesIssues[] = 'success flash zpráva správy modulů po druhém načtení nezmizela';
    }

    httpIntegrationPrintResult('settings_modules_http', $settingsModulesIssues, $failures);

    $visitorStatsWidgetIssues = [];
    saveSetting('visitor_tracking_enabled', '1');
    saveSetting('visitor_counter_enabled', '1');
    clearSettingsCache();

    $visitorStatsWidgetTitle = 'HTTP visitor stats ' . bin2hex(random_bytes(4));
    $visitorStatsWidgetSortOrder = (int)$pdo->query(
        "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM cms_widgets WHERE zone = 'footer'"
    )->fetchColumn();
    $insertVisitorStatsWidget = $pdo->prepare(
        "INSERT INTO cms_widgets (zone, widget_type, title, settings, sort_order, is_active)
         VALUES ('footer', 'visitor_stats', ?, '{}', ?, 1)"
    );
    $insertVisitorStatsWidget->execute([$visitorStatsWidgetTitle, $visitorStatsWidgetSortOrder]);
    $visitorStatsWidgetId = (int)$pdo->lastInsertId();
    $createdWidgetIds[] = $visitorStatsWidgetId;

    $publicHomeUrl = rtrim($baseUrl . BASE_URL, '/') . '/';
    $homeWithVisitorStatsWidget = fetchUrl($publicHomeUrl, '', 0);
    if (httpIntegrationStatusCode($homeWithVisitorStatsWidget) !== 200) {
        $visitorStatsWidgetIssues[] = 'homepage s visitor stats widgetem nevrátila 200';
    }
    if (!str_contains($homeWithVisitorStatsWidget['body'], $visitorStatsWidgetTitle)) {
        $visitorStatsWidgetIssues[] = 'visitor stats widget se na homepage nevykreslil s vlastním titulkem';
    }
    if (!str_contains($homeWithVisitorStatsWidget['body'], 'class="visitor-counter__item"')) {
        $visitorStatsWidgetIssues[] = 'visitor stats widget nevykreslil jednotlivé statistické položky';
    }
    if (!str_contains($homeWithVisitorStatsWidget['body'], 'visitor-counter--footer')) {
        $visitorStatsWidgetIssues[] = 'visitor stats widget ve footer zóně nepoužil footer variantu stylů';
    }

    $pdo->prepare("DELETE FROM cms_widgets WHERE id = ?")->execute([$visitorStatsWidgetId]);
    $createdWidgetIds = array_values(array_filter(
        $createdWidgetIds,
        static fn(int $existingWidgetId): bool => $existingWidgetId !== $visitorStatsWidgetId
    ));

    $homeWithoutVisitorStatsWidget = fetchUrl($publicHomeUrl, '', 0);
    if (str_contains($homeWithoutVisitorStatsWidget['body'], $visitorStatsWidgetTitle)) {
        $visitorStatsWidgetIssues[] = 'visitor stats widget zůstal na homepage i po odebrání z footer zóny';
    }

    httpIntegrationPrintResult('visitor_stats_widget_http', $visitorStatsWidgetIssues, $failures);

    $formPresetIssues = [];
    $issuePresetDefinition = formPresetDefinition('issue_report');
    $issuePresetUrl = $baseUrl . BASE_URL . '/admin/form_form.php?preset=issue_report';
    $issuePresetResponse = fetchUrl($issuePresetUrl, $adminSession['cookie'], 0);
    if (httpIntegrationStatusCode($issuePresetResponse) !== 200) {
        $formPresetIssues[] = 'preset formuláře pro nahlášení chyby nevrátil 200';
    }
    if (!str_contains($issuePresetResponse['body'], 'value="email_pro_odpoved" selected')) {
        $formPresetIssues[] = 'preset formuláře pro nahlášení chyby nepředvyplnil pole pro potvrzovací e-mail';
    }
    $issuePresetCsrf = extractHiddenInputValue($issuePresetResponse['body'], 'csrf_token');
    if ($issuePresetCsrf === '') {
        $formPresetIssues[] = 'preset formuláře pro nahlášení chyby nevykreslil csrf_token';
    }

    $issuePresetTitle = 'HTTP preset issue ' . bin2hex(random_bytes(4));
    $issuePresetSlug = uniqueFormSlug($pdo, 'http-preset-issue-' . bin2hex(random_bytes(4)));
    $issuePresetFormValues = (array)($issuePresetDefinition['form'] ?? []);
    $issuePresetCreateResponse = postUrl(
        $baseUrl . BASE_URL . '/admin/form_save.php',
        [
            'csrf_token' => $issuePresetCsrf,
            'preset' => 'issue_report',
            'title' => $issuePresetTitle,
            'slug' => $issuePresetSlug,
            'description' => (string)($issuePresetFormValues['description'] ?? ''),
            'success_message' => (string)($issuePresetFormValues['success_message'] ?? ''),
            'submit_label' => (string)($issuePresetFormValues['submit_label'] ?? ''),
            'notification_email' => '',
            'notification_subject' => (string)($issuePresetFormValues['notification_subject'] ?? ''),
            'redirect_url' => '',
            'success_behavior' => (string)($issuePresetFormValues['success_behavior'] ?? 'message'),
            'success_primary_label' => '',
            'success_primary_url' => '',
            'success_secondary_label' => '',
            'success_secondary_url' => '',
            'use_honeypot' => '1',
            'submitter_confirmation_enabled' => '1',
            'submitter_email_field' => 'email_pro_odpoved',
            'submitter_confirmation_subject' => (string)($issuePresetFormValues['submitter_confirmation_subject'] ?? ''),
            'submitter_confirmation_message' => (string)($issuePresetFormValues['submitter_confirmation_message'] ?? ''),
            'is_active' => '1',
        ],
        $adminSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($issuePresetCreateResponse) !== 302) {
        $formPresetIssues[] = 'uložení presetu formuláře pro nahlášení chyby nevrátilo 302 redirect';
    }

    $issuePresetCreateHeaders = implode("\n", $issuePresetCreateResponse['headers']);
    $issuePresetFormId = 0;
    if (preg_match('~Location:\s*(?:https?://[^/]+)?' . preg_quote(BASE_URL . '/admin/form_form.php?id=', '~') . '(\d+)~i', $issuePresetCreateHeaders, $matches) === 1) {
        $issuePresetFormId = (int)$matches[1];
    } else {
        $formPresetIssues[] = 'uložení presetu formuláře pro nahlášení chyby nemíří zpět na editaci formuláře';
    }

    if ($issuePresetFormId > 0) {
        $createdFormIds[] = $issuePresetFormId;

        $issuePresetFormStmt = $pdo->prepare("SELECT submitter_confirmation_enabled, submitter_email_field FROM cms_forms WHERE id = ? LIMIT 1");
        $issuePresetFormStmt->execute([$issuePresetFormId]);
        $issuePresetFormRow = $issuePresetFormStmt->fetch() ?: [];
        if ((int)($issuePresetFormRow['submitter_confirmation_enabled'] ?? 0) !== 1) {
            $formPresetIssues[] = 'uložený preset formuláře pro nahlášení chyby nezapnul potvrzovací e-mail';
        }
        if ((string)($issuePresetFormRow['submitter_email_field'] ?? '') !== 'email_pro_odpoved') {
            $formPresetIssues[] = 'uložený preset formuláře pro nahlášení chyby neuložil správné pole pro potvrzovací e-mail';
        }

        $issuePresetFieldStmt = $pdo->prepare("SELECT name FROM cms_form_fields WHERE form_id = ? ORDER BY sort_order, id");
        $issuePresetFieldStmt->execute([$issuePresetFormId]);
        $issuePresetFieldNames = array_values(array_map('strval', array_column($issuePresetFieldStmt->fetchAll() ?: [], 'name')));
        if (!in_array('email_pro_odpoved', $issuePresetFieldNames, true) || !in_array('strucny_nazev_problemu', $issuePresetFieldNames, true)) {
            $formPresetIssues[] = 'preset formuláře pro nahlášení chyby neuložil očekávané interní názvy polí';
        }
        if (in_array('email-pro-odpoved', $issuePresetFieldNames, true) || in_array('strucny-nazev-problemu', $issuePresetFieldNames, true)) {
            $formPresetIssues[] = 'preset formuláře pro nahlášení chyby stále slugifikuje interní názvy polí';
        }

        $issuePresetEditUrl = $baseUrl . BASE_URL . '/admin/form_form.php?id=' . $issuePresetFormId;
        $issuePresetEditResponse = fetchUrl($issuePresetEditUrl, $adminSession['cookie'], 0);
        if (httpIntegrationStatusCode($issuePresetEditResponse) !== 200) {
            $formPresetIssues[] = 'editace presetu formuláře pro nahlášení chyby nevrátila 200';
        }
        if (!str_contains($issuePresetEditResponse['body'], 'value="email_pro_odpoved" selected')) {
            $formPresetIssues[] = 'editace presetu formuláře pro nahlášení chyby nezachovala vybrané pole pro potvrzovací e-mail';
        }

        $issuePresetEditCsrf = extractHiddenInputValue($issuePresetEditResponse['body'], 'csrf_token');
        if ($issuePresetEditCsrf === '') {
            $formPresetIssues[] = 'editace presetu formuláře pro nahlášení chyby nevykreslila csrf_token';
        }

        $issuePresetResaveResponse = postUrl(
            $baseUrl . BASE_URL . '/admin/form_save.php',
            [
                'csrf_token' => $issuePresetEditCsrf,
                'id' => (string)$issuePresetFormId,
                'title' => $issuePresetTitle,
                'slug' => $issuePresetSlug,
                'description' => (string)($issuePresetFormValues['description'] ?? ''),
                'success_message' => (string)($issuePresetFormValues['success_message'] ?? ''),
                'submit_label' => (string)($issuePresetFormValues['submit_label'] ?? ''),
                'notification_email' => '',
                'notification_subject' => (string)($issuePresetFormValues['notification_subject'] ?? ''),
                'redirect_url' => '',
                'success_behavior' => (string)($issuePresetFormValues['success_behavior'] ?? 'message'),
                'success_primary_label' => '',
                'success_primary_url' => '',
                'success_secondary_label' => '',
                'success_secondary_url' => '',
                'use_honeypot' => '1',
                'submitter_confirmation_enabled' => '1',
                'submitter_email_field' => '',
                'submitter_confirmation_subject' => (string)($issuePresetFormValues['submitter_confirmation_subject'] ?? ''),
                'submitter_confirmation_message' => (string)($issuePresetFormValues['submitter_confirmation_message'] ?? ''),
                'is_active' => '1',
            ],
            $adminSession['cookie'],
            0
        );
        if (httpIntegrationStatusCode($issuePresetResaveResponse) !== 302) {
            $formPresetIssues[] = 'uložení beze změn u presetu formuláře pro nahlášení chyby nevrátilo 302 redirect';
        }

        $issuePresetResaveHeaders = implode("\n", $issuePresetResaveResponse['headers']);
        if (
            preg_match(
                '~Location:\s*(?:https?://[^/]+)?' . preg_quote(BASE_URL . '/admin/form_form.php?id=' . $issuePresetFormId, '~') . '(?:\s|$)~i',
                $issuePresetResaveHeaders
            ) !== 1
        ) {
            $formPresetIssues[] = 'uložení beze změn u presetu formuláře pro nahlášení chyby nemíří zpět na editaci formuláře';
        }
        if (str_contains($issuePresetResaveHeaders, 'err=submitter_email_field')) {
            $formPresetIssues[] = 'uložení beze změn u presetu formuláře pro nahlášení chyby stále padá na submitter_email_field';
        }

        $issuePresetAfterResaveStmt = $pdo->prepare("SELECT submitter_email_field FROM cms_forms WHERE id = ? LIMIT 1");
        $issuePresetAfterResaveStmt->execute([$issuePresetFormId]);
        if ((string)($issuePresetAfterResaveStmt->fetchColumn() ?: '') !== 'email_pro_odpoved') {
            $formPresetIssues[] = 'uložení beze změn u presetu formuláře pro nahlášení chyby nezachovalo uložené pole pro potvrzovací e-mail';
        }
    }
    httpIntegrationPrintResult('form_issue_preset_http', $formPresetIssues, $failures);

    $galleryPickerIssues = [];
    $galleryAlbumSlug = 'http-picker-gallery-' . bin2hex(random_bytes(4));
    $galleryAlbumTitle = 'HTTP Picker Letecký den ' . date('His');
    $galleryAlbumDescription = 'Dočasné album pro ověření content pickeru galerie.';
    $pdo->prepare(
        "INSERT INTO cms_gallery_albums (name, slug, description, status, is_published)
         VALUES (?, ?, ?, 'published', 1)"
    )->execute([
        $galleryAlbumTitle,
        $galleryAlbumSlug,
        $galleryAlbumDescription,
    ]);
    $galleryAlbumId = (int)$pdo->lastInsertId();
    $createdGalleryAlbumIds[] = $galleryAlbumId;

    $galleryPickerResponse = fetchUrl(
        $baseUrl . BASE_URL . '/admin/content_reference_search.php?q=' . urlencode('letecky') . '&type=gallery',
        $adminSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($galleryPickerResponse) !== 200) {
        $galleryPickerIssues[] = 'gallery content picker search nevrátil 200';
    } else {
        $galleryPickerPayload = json_decode((string)($galleryPickerResponse['body'] ?? ''), true);
        if (!is_array($galleryPickerPayload) || ($galleryPickerPayload['ok'] ?? false) !== true) {
            $galleryPickerIssues[] = 'gallery content picker search nevrátil čitelné JSON s ok=true';
        } else {
            $galleryAlbumFound = false;
            foreach (($galleryPickerPayload['results'] ?? []) as $pickerResult) {
                if (!is_array($pickerResult)) {
                    continue;
                }
                if ((string)($pickerResult['type'] ?? '') !== 'gallery_album') {
                    continue;
                }
                if ((string)($pickerResult['title'] ?? '') !== $galleryAlbumTitle) {
                    continue;
                }
                $galleryAlbumFound = true;
                if ((string)($pickerResult['path'] ?? '') !== galleryAlbumPublicPath(['id' => $galleryAlbumId, 'slug' => $galleryAlbumSlug])) {
                    $galleryPickerIssues[] = 'gallery content picker vrátil album s neočekávanou veřejnou cestou';
                }
                $actions = $pickerResult['insert_actions'] ?? [];
                $hasGalleryAction = false;
                if (is_array($actions)) {
                    foreach ($actions as $action) {
                        if (is_array($action) && (string)($action['label'] ?? '') === 'Vložit fotogalerii') {
                            $hasGalleryAction = true;
                            break;
                        }
                    }
                }
                if (!$hasGalleryAction) {
                    $galleryPickerIssues[] = 'gallery content picker u alba nenabízí akci Vložit fotogalerii';
                }
                break;
            }
            if (!$galleryAlbumFound) {
                $galleryPickerIssues[] = 'gallery content picker nenašel publikované album podle dotazu';
            }
        }
    }

    httpIntegrationPrintResult('content_reference_gallery_http', $galleryPickerIssues, $failures);

    $pdfPickerIssues = [];
    $pdfMediaOriginalName = 'http-picker-pdf-' . bin2hex(random_bytes(4)) . '.pdf';
    $pdfFixturePath = httpIntegrationCreatePdfFixtureFile('kora-picker-pdf-', $createdTempFiles);
    $pdfUploadResponse = postMultipartUrl(
        $baseUrl . BASE_URL . '/admin/media.php',
        [
            'csrf_token' => $adminSession['csrf'],
            'action' => 'upload',
            'upload_visibility' => 'public',
            'return_to' => BASE_URL . '/admin/media.php',
        ],
        [
            'media_files[0]' => [
                'path' => $pdfFixturePath,
                'filename' => $pdfMediaOriginalName,
                'type' => 'application/pdf',
            ],
        ],
        $adminSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($pdfUploadResponse) !== 302) {
        $pdfPickerIssues[] = 'pdf content picker fixture upload nevrátil 302 redirect';
    }

    $pdfMedia = httpIntegrationFetchMediaByOriginalName($pdo, $pdfMediaOriginalName);
    if ($pdfMedia === null) {
        $pdfPickerIssues[] = 'pdf content picker fixture nevytvořil záznam v cms_media';
    } else {
        $createdMediaIds[] = (int)$pdfMedia['id'];
        fetchUrl($baseUrl . BASE_URL . '/admin/media.php', $adminSession['cookie'], 0);
        $pdfSearchQuery = rawurlencode(pathinfo($pdfMediaOriginalName, PATHINFO_FILENAME));
        $pdfPickerResponse = fetchUrl(
            $baseUrl . BASE_URL . '/admin/content_reference_search.php?q=' . $pdfSearchQuery . '&type=media',
            $adminSession['cookie'],
            0
        );
        if (httpIntegrationStatusCode($pdfPickerResponse) !== 200) {
            $pdfPickerIssues[] = 'pdf content picker search nevrátil 200';
        } else {
            $pdfPickerPayload = json_decode((string)($pdfPickerResponse['body'] ?? ''), true);
            if (!is_array($pdfPickerPayload) || ($pdfPickerPayload['ok'] ?? false) !== true) {
                $pdfPickerIssues[] = 'pdf content picker search nevrátil čitelné JSON s ok=true';
            } else {
                $pdfMediaFound = false;
                foreach (($pdfPickerPayload['results'] ?? []) as $pickerResult) {
                    if (!is_array($pickerResult)) {
                        continue;
                    }
                    if ((string)($pickerResult['type'] ?? '') !== 'media_file') {
                        continue;
                    }
                    if ((string)($pickerResult['title'] ?? '') !== $pdfMediaOriginalName) {
                        continue;
                    }
                    $pdfMediaFound = true;
                    if ((string)($pickerResult['path'] ?? '') !== mediaFileUrl($pdfMedia)) {
                        $pdfPickerIssues[] = 'pdf content picker vrátil médium s neočekávanou veřejnou cestou';
                    }
                    $actions = $pickerResult['insert_actions'] ?? [];
                    $hasPdfAction = false;
                    $pdfSnippet = '';
                    if (is_array($actions)) {
                        foreach ($actions as $action) {
                            if (is_array($action) && (string)($action['label'] ?? '') === 'Vložit PDF náhled') {
                                $hasPdfAction = true;
                                $pdfSnippet = (string)($action['snippet'] ?? '');
                                break;
                            }
                        }
                    }
                    if (!$hasPdfAction) {
                        $pdfPickerIssues[] = 'pdf content picker u média nenabízí akci Vložit PDF náhled';
                    } else {
                        if (!str_contains($pdfSnippet, 'media_id="' . (int)$pdfMedia['id'] . '"')) {
                            $pdfPickerIssues[] = 'pdf content picker nevkládá media_id pro same-origin preview endpoint';
                        }

                        $renderedPdfSnippet = renderContent($pdfSnippet);
                        if (!str_contains($renderedPdfSnippet, '/media/preview.php?id=' . (int)$pdfMedia['id'])) {
                            $pdfPickerIssues[] = 'pdf snippet z pickeru nevykresluje iframe přes media preview endpoint';
                        }

                        $legacyPdfSnippet = '[pdf src="' . htmlspecialchars(mediaFileUrl($pdfMedia), ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars((string)mediaOriginalName($pdfMedia), ENT_QUOTES, 'UTF-8') . '" mime="application/pdf"][/pdf]';
                        $legacyRenderedPdfSnippet = renderContent($legacyPdfSnippet);
                        if (!str_contains($legacyRenderedPdfSnippet, '/media/preview.php?id=' . (int)$pdfMedia['id'])) {
                            $pdfPickerIssues[] = 'legacy pdf shortcode se src z uploads/media se zpětně nepřeklápí na media preview endpoint';
                        }

                        $pdfPreviewResponse = fetchUrl(
                            $baseUrl . BASE_URL . '/media/preview.php?id=' . (int)$pdfMedia['id'],
                            '',
                            0
                        );
                        if (httpIntegrationStatusCode($pdfPreviewResponse) !== 200) {
                            $pdfPickerIssues[] = 'media preview endpoint pro public PDF nevrátil 200';
                        } else {
                            $hasPdfContentType = false;
                            $hasInlineDisposition = false;
                            $hasSameOriginFrameHeader = false;
                            $hasSelfFrameAncestors = false;
                            $hasDenyFrameHeader = false;

                            foreach ($pdfPreviewResponse['headers'] as $previewHeader) {
                                $normalizedHeader = strtolower((string)$previewHeader);
                                if (str_starts_with($normalizedHeader, 'content-type: application/pdf')) {
                                    $hasPdfContentType = true;
                                }
                                if (str_starts_with($normalizedHeader, 'content-disposition: inline;')) {
                                    $hasInlineDisposition = true;
                                }
                                if ($normalizedHeader === 'x-frame-options: sameorigin') {
                                    $hasSameOriginFrameHeader = true;
                                }
                                if (str_starts_with($normalizedHeader, 'content-security-policy:') && str_contains($normalizedHeader, "frame-ancestors 'self'")) {
                                    $hasSelfFrameAncestors = true;
                                }
                                if ($normalizedHeader === 'x-frame-options: deny') {
                                    $hasDenyFrameHeader = true;
                                }
                            }

                            if (!$hasPdfContentType) {
                                $pdfPickerIssues[] = 'media preview endpoint pro public PDF nevrátil application/pdf';
                            }
                            if (!$hasInlineDisposition) {
                                $pdfPickerIssues[] = 'media preview endpoint pro public PDF neposílá Content-Disposition inline';
                            }
                            if (!$hasSameOriginFrameHeader) {
                                $pdfPickerIssues[] = 'media preview endpoint pro public PDF neposílá X-Frame-Options SAMEORIGIN';
                            }
                            if (!$hasSelfFrameAncestors) {
                                $pdfPickerIssues[] = 'media preview endpoint pro public PDF neposílá CSP frame-ancestors self';
                            }
                            if ($hasDenyFrameHeader) {
                                $pdfPickerIssues[] = 'media preview endpoint pro public PDF stále vrací X-Frame-Options DENY';
                            }
                            if (!str_starts_with((string)($pdfPreviewResponse['body'] ?? ''), '%PDF-')) {
                                $pdfPickerIssues[] = 'media preview endpoint pro public PDF nevrátil PDF payload';
                            }
                        }
                    }
                    break;
                }
                if (!$pdfMediaFound) {
                    $pdfPickerIssues[] = 'pdf content picker nenašel nahrané PDF médium podle dotazu';
                }
            }
        }
    }

    httpIntegrationPrintResult('content_reference_pdf_http', $pdfPickerIssues, $failures);

    $reservationIssues = [];
    $resourceSlug = 'http-resource-' . bin2hex(random_bytes(4));
    $pdo->prepare(
        "INSERT INTO cms_res_resources
            (name, slug, description, capacity, slot_mode, slot_duration_min,
             min_advance_hours, max_advance_days, cancellation_hours, requires_approval,
             allow_guests, max_concurrent, is_active)
         VALUES (?, ?, ?, 1, 'slots', 60, 1, 30, 24, 0, 1, 1, 1)"
    )->execute([
        'HTTP Resource',
        $resourceSlug,
        'Dočasný zdroj pro HTTP integration test.',
    ]);
    $resourceId = (int)$pdo->lastInsertId();
    $createdResourceIds[] = $resourceId;

    $reservationResponse = fetchUrl(
        $baseUrl . BASE_URL . '/reservations/book.php?slug=' . rawurlencode($resourceSlug) . '&date=2026-02-31',
        '',
        0
    );
    if (httpIntegrationStatusCode($reservationResponse) !== 302) {
        $reservationIssues[] = 'neplatné kalendářní datum rezervace nevrátilo redirect';
    }
    if (!responseHasLocationHeader($reservationResponse['headers'], BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($resourceSlug), $baseUrl)) {
        $reservationIssues[] = 'neplatné kalendářní datum rezervace nemíří zpět na detail zdroje';
    }

    httpIntegrationPrintResult('reservations_http', $reservationIssues, $failures);

    $boardIssues = [];
    $invalidBoardTitle = 'HTTP Invalid Board ' . date('His');
    $invalidBoardResponse = postUrl(
        $baseUrl . BASE_URL . '/admin/board_save.php',
        [
            'csrf_token' => $adminSession['csrf'],
            'title' => $invalidBoardTitle,
            'slug' => '',
            'board_type' => 'notice',
            'excerpt' => '',
            'description' => '',
            'category_id' => '',
            'posted_date' => '2026-02-31',
            'removal_date' => '',
            'contact_name' => '',
            'contact_phone' => '',
            'contact_email' => '',
            'is_published' => '1',
        ],
        $adminSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($invalidBoardResponse) !== 302) {
        $boardIssues[] = 'neplatné datum vývěsky nevrátilo PRG redirect';
    }
    if (!responseHasLocationHeader($invalidBoardResponse['headers'], '/admin/board_form.php?err=posted_date', $baseUrl)) {
        $boardIssues[] = 'neplatné datum vývěsky nemíří zpět na formulář s err=posted_date';
    }
    $boardExistsStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_board WHERE title = ?");
    $boardExistsStmt->execute([$invalidBoardTitle]);
    if ((int)$boardExistsStmt->fetchColumn() !== 0) {
        $boardIssues[] = 'neplatné datum vývěsky vytvořilo záznam v databázi';
    }
    $invalidBoardPage = fetchUrl($baseUrl . BASE_URL . '/admin/board_form.php?err=posted_date', $adminSession['cookie'], 0);
    if (!str_contains($invalidBoardPage['body'], 'Zadejte platné datum vyvěšení.')) {
        $boardIssues[] = 'neplatné datum vývěsky nezobrazilo validační zprávu';
    }
    if (!httpIntegrationFieldHasAriaInvalid($invalidBoardPage['body'], 'posted_date')) {
        $boardIssues[] = 'neplatné datum vývěsky neoznačilo pole posted_date jako aria-invalid';
    }

    httpIntegrationPrintResult('board_save_http', $boardIssues, $failures);

    $blogTransferIssues = [];
    $passwordHash = password_hash('HttpIntegration123!', PASSWORD_BCRYPT);

    $authorEmail = 'http-transfer-author-' . bin2hex(random_bytes(4)) . '@example.test';
    $pdo->prepare(
        "INSERT INTO cms_users (email, password, first_name, last_name, role, is_superadmin, is_confirmed, created_at)
         VALUES (?, ?, 'HTTP', 'Author', 'author', 0, 1, NOW())"
    )->execute([$authorEmail, $passwordHash]);
    $authorId = (int)$pdo->lastInsertId();
    $createdUsers[] = $authorId;

    $authorNoTaxEmail = 'http-transfer-author-plain-' . bin2hex(random_bytes(4)) . '@example.test';
    $pdo->prepare(
        "INSERT INTO cms_users (email, password, first_name, last_name, role, is_superadmin, is_confirmed, created_at)
         VALUES (?, ?, 'HTTP', 'Author Plain', 'author', 0, 1, NOW())"
    )->execute([$authorNoTaxEmail, $passwordHash]);
    $authorNoTaxId = (int)$pdo->lastInsertId();
    $createdUsers[] = $authorNoTaxId;

    $sourceBlogSlug = 'http-source-blog-' . bin2hex(random_bytes(4));
    $targetBlogSlug = 'http-target-blog-' . bin2hex(random_bytes(4));
    $foreignBlogSlug = 'http-foreign-blog-' . bin2hex(random_bytes(4));
    foreach ([
        ['name' => 'HTTP Source Blog', 'slug' => $sourceBlogSlug],
        ['name' => 'HTTP Target Blog', 'slug' => $targetBlogSlug],
        ['name' => 'HTTP Foreign Blog', 'slug' => $foreignBlogSlug],
    ] as $blogRow) {
        $pdo->prepare(
            "INSERT INTO cms_blogs (name, slug, created_by_user_id) VALUES (?, ?, ?)"
        )->execute([$blogRow['name'], $blogRow['slug'], $adminUserId]);
        $createdBlogs[] = (int)$pdo->lastInsertId();
    }
    [$sourceBlogId, $targetBlogId, $foreignBlogId] = $createdBlogs;

    foreach ([
        [$sourceBlogId, $authorId, 'author'],
        [$targetBlogId, $authorId, 'manager'],
        [$sourceBlogId, $authorNoTaxId, 'author'],
        [$targetBlogId, $authorNoTaxId, 'author'],
    ] as $membershipRow) {
        $pdo->prepare(
            "INSERT INTO cms_blog_members (blog_id, user_id, member_role) VALUES (?, ?, ?)"
        )->execute($membershipRow);
    }

    $sourceCategoryName = 'HTTP Zdrojová kategorie';
    $targetCategoryName = 'HTTP Cílová kategorie';
    $pdo->prepare("INSERT INTO cms_categories (name, blog_id) VALUES (?, ?)")->execute([$sourceCategoryName, $sourceBlogId]);
    $sourceCategoryId = (int)$pdo->lastInsertId();
    $createdCategories[] = $sourceCategoryId;
    $pdo->prepare("INSERT INTO cms_categories (name, blog_id) VALUES (?, ?)")->execute([$targetCategoryName, $targetBlogId]);
    $targetCategoryId = (int)$pdo->lastInsertId();
    $createdCategories[] = $targetCategoryId;
    $pdo->prepare("INSERT INTO cms_categories (name, blog_id) VALUES (?, ?)")->execute(['HTTP Cizí kategorie', $foreignBlogId]);
    $foreignCategoryId = (int)$pdo->lastInsertId();
    $createdCategories[] = $foreignCategoryId;
    $sharedCategoryName = 'HTTP Sdílená kategorie';
    $pdo->prepare("INSERT INTO cms_categories (name, blog_id) VALUES (?, ?)")->execute([$sharedCategoryName, $sourceBlogId]);
    $sharedSourceCategoryId = (int)$pdo->lastInsertId();
    $createdCategories[] = $sharedSourceCategoryId;
    $pdo->prepare("INSERT INTO cms_categories (name, blog_id) VALUES (?, ?)")->execute([$sharedCategoryName, $targetBlogId]);
    $sharedTargetCategoryId = (int)$pdo->lastInsertId();
    $createdCategories[] = $sharedTargetCategoryId;
    $createSourceCategoryName = 'HTTP Kategorie k vytvoření';
    $pdo->prepare("INSERT INTO cms_categories (name, blog_id) VALUES (?, ?)")->execute([$createSourceCategoryName, $sourceBlogId]);
    $createSourceCategoryId = (int)$pdo->lastInsertId();
    $createdCategories[] = $createSourceCategoryId;

    $sourceTagOneSlug = 'http-source-tag-one-' . bin2hex(random_bytes(2));
    $sourceTagTwoSlug = 'http-source-tag-two-' . bin2hex(random_bytes(2));
    $targetTagSlug = 'http-target-tag-' . bin2hex(random_bytes(2));
    $foreignTagSlug = 'http-foreign-tag-' . bin2hex(random_bytes(2));
    $sharedTagSlug = 'http-shared-tag-' . bin2hex(random_bytes(2));
    $createSourceTagSlug = 'http-create-tag-' . bin2hex(random_bytes(2));
    foreach ([
        ['name' => 'HTTP Zdrojový štítek A', 'slug' => $sourceTagOneSlug, 'blog_id' => $sourceBlogId],
        ['name' => 'HTTP Zdrojový štítek B', 'slug' => $sourceTagTwoSlug, 'blog_id' => $sourceBlogId],
        ['name' => 'HTTP Cílový štítek', 'slug' => $targetTagSlug, 'blog_id' => $targetBlogId],
        ['name' => 'HTTP Cizí štítek', 'slug' => $foreignTagSlug, 'blog_id' => $foreignBlogId],
        ['name' => 'HTTP Sdílený štítek', 'slug' => $sharedTagSlug, 'blog_id' => $sourceBlogId],
        ['name' => 'HTTP Sdílený štítek', 'slug' => $sharedTagSlug, 'blog_id' => $targetBlogId],
        ['name' => 'HTTP Štítek k vytvoření', 'slug' => $createSourceTagSlug, 'blog_id' => $sourceBlogId],
    ] as $tagRow) {
        $pdo->prepare("INSERT INTO cms_tags (name, slug, blog_id) VALUES (?, ?, ?)")->execute([$tagRow['name'], $tagRow['slug'], $tagRow['blog_id']]);
        $createdTags[] = (int)$pdo->lastInsertId();
    }
    [$sourceTagOneId, $sourceTagTwoId, $targetTagId, $foreignTagId, $sharedSourceTagId, $sharedTargetTagId, $createSourceTagId] = array_slice($createdTags, -7);

    foreach ([
        ['title' => 'HTTP Přesunovaný článek', 'slug' => 'http-transfer-article-' . bin2hex(random_bytes(4)), 'author_id' => $authorId],
        ['title' => 'HTTP Cizí mapování', 'slug' => 'http-transfer-article-foreign-' . bin2hex(random_bytes(4)), 'author_id' => $authorId],
        ['title' => 'HTTP Bez taxonomy práv', 'slug' => 'http-transfer-article-author-' . bin2hex(random_bytes(4)), 'author_id' => $authorNoTaxId],
        ['title' => 'HTTP Edit přes blog_save', 'slug' => 'http-edit-move-article-' . bin2hex(random_bytes(4)), 'author_id' => $authorId],
        ['title' => 'HTTP Edit ruční volba', 'slug' => 'http-edit-manual-article-' . bin2hex(random_bytes(4)), 'author_id' => $authorId],
        ['title' => 'HTTP Edit vytvoření taxonomií', 'slug' => 'http-edit-create-article-' . bin2hex(random_bytes(4)), 'author_id' => $authorId],
    ] as $articleRow) {
        $pdo->prepare(
            "INSERT INTO cms_articles
                (title, slug, blog_id, perex, content, comments_enabled, category_id, author_id, status)
             VALUES (?, ?, ?, '', '<p>HTTP integration test.</p>', 1, ?, ?, 'published')"
        )->execute([
            $articleRow['title'],
            $articleRow['slug'],
            $sourceBlogId,
            $articleRow['title'] === 'HTTP Edit přes blog_save'
                ? $sharedSourceCategoryId
                : ($articleRow['title'] === 'HTTP Edit vytvoření taxonomií' ? $createSourceCategoryId : $sourceCategoryId),
            $articleRow['author_id'],
        ]);
        $createdArticles[] = (int)$pdo->lastInsertId();
    }
    [$articleId, $articleForeignAttemptId, $articleNoTaxId, $articleEditMoveId, $articleManualMoveId, $articleCreateMoveId] = array_slice($createdArticles, -6);

    foreach ([
        [$articleId, $sourceTagOneId],
        [$articleId, $sourceTagTwoId],
        [$articleForeignAttemptId, $sourceTagOneId],
        [$articleNoTaxId, $sourceTagOneId],
        [$articleEditMoveId, $sharedSourceTagId],
        [$articleManualMoveId, $sourceTagOneId],
        [$articleCreateMoveId, $createSourceTagId],
    ] as $articleTagRow) {
        $pdo->prepare("INSERT INTO cms_article_tags (article_id, tag_id) VALUES (?, ?)")->execute($articleTagRow);
    }

    $authorSession = koraPrimeTestSession([
        'cms_logged_in' => true,
        'cms_superadmin' => false,
        'cms_user_id' => $authorId,
        'cms_user_name' => 'HTTP Author',
        'cms_user_role' => 'author',
        'blog_transfer_selection' => [
            'ids' => [$articleId],
            'redirect' => BASE_URL . '/admin/blog.php',
            'created_at' => time(),
        ],
    ], 'kora-http-transfer-author');

    $editAutoFormResponse = fetchUrl(
        $baseUrl . BASE_URL . '/admin/blog_form.php?id=' . $articleEditMoveId . '&blog_id=' . $targetBlogId,
        $authorSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($editAutoFormResponse) !== 200) {
        $blogTransferIssues[] = 'editor článku při změně blogu nevrátil načtení formuláře';
    }
    if (!str_contains($editAutoFormResponse['body'], 'Uložený blog článku:')) {
        $blogTransferIssues[] = 'editor článku nezobrazuje uložený blog jako samostatnou informaci';
    }
    if (!str_contains($editAutoFormResponse['body'], 'Po uložení bude článek přesunut do blogu')) {
        $blogTransferIssues[] = 'editor článku nezobrazuje cílový blog jako stav po uložení';
    }
    if (str_contains($editAutoFormResponse['body'], 'Článek právě patří do blogu')) {
        $blogTransferIssues[] = 'editor článku stále zavádějícím způsobem tvrdí, že článek už patří do vybraného cílového blogu';
    }
    if (!str_contains($editAutoFormResponse['body'], 'value="' . $sharedTargetCategoryId . '" selected')) {
        $blogTransferIssues[] = 'editor článku při změně blogu nepředvyplnil stejně pojmenovanou cílovou kategorii';
    }
    if (!preg_match('/name="tags\[\]"\s+value="' . preg_quote((string)$sharedTargetTagId, '/') . '"[^>]*checked/', $editAutoFormResponse['body'])) {
        $blogTransferIssues[] = 'editor článku při změně blogu nepředvyplnil odpovídající cílový štítek';
    }
    if (!str_contains($editAutoFormResponse['body'], 'id="blog-missing-category-group" style="margin-top:.75rem" hidden') || !str_contains($editAutoFormResponse['body'], 'id="blog-missing-tags-group" style="margin-top:.75rem" hidden')) {
        $blogTransferIssues[] = 'editor článku neschovává sekci chybějících taxonomií, když se cílové taxonomie podařilo automaticky namapovat';
    }

    $editCreateFormResponse = fetchUrl(
        $baseUrl . BASE_URL . '/admin/blog_form.php?id=' . $articleCreateMoveId . '&blog_id=' . $targetBlogId,
        $authorSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($editCreateFormResponse) !== 200) {
        $blogTransferIssues[] = 'editor článku s chybějícími taxonomiemi nevrátil načtení formuláře';
    }
    if (!str_contains($editCreateFormResponse['body'], 'Původní kategorie článku „' . $createSourceCategoryName . '“ v cílovém blogu neexistuje.')) {
        $blogTransferIssues[] = 'editor článku neukazuje chybějící původní kategorii při změně blogu';
    }
    if (!str_contains($editCreateFormResponse['body'], 'Vytvořit chybějící kategorii v cílovém blogu')) {
        $blogTransferIssues[] = 'editor článku nenabízí vytvoření chybějící cílové kategorie pro oprávněného uživatele';
    }
    if (!str_contains($editCreateFormResponse['body'], 'Vytvořit chybějící štítky v cílovém blogu')) {
        $blogTransferIssues[] = 'editor článku nenabízí vytvoření chybějících cílových štítků pro oprávněného uživatele';
    }

    $authorNoTaxSession = koraPrimeTestSession([
        'cms_logged_in' => true,
        'cms_superadmin' => false,
        'cms_user_id' => $authorNoTaxId,
        'cms_user_name' => 'HTTP Author Plain',
        'cms_user_role' => 'author',
        'blog_transfer_selection' => [
            'ids' => [$articleNoTaxId],
            'redirect' => BASE_URL . '/admin/blog.php',
            'created_at' => time(),
        ],
    ], 'kora-http-transfer-author-plain');

    $noTaxEditFormResponse = fetchUrl(
        $baseUrl . BASE_URL . '/admin/blog_form.php?id=' . $articleNoTaxId . '&blog_id=' . $targetBlogId,
        $authorNoTaxSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($noTaxEditFormResponse) !== 200) {
        $blogTransferIssues[] = 'editor článku bez taxonomy práv nevrátil načtení formuláře';
    }
    if (str_contains($noTaxEditFormResponse['body'], 'Vytvořit chybějící kategorii v cílovém blogu') || str_contains($noTaxEditFormResponse['body'], 'Vytvořit chybějící štítky v cílovém blogu')) {
        $blogTransferIssues[] = 'editor článku bez taxonomy práv stále nabízí vytvoření chybějících taxonomií';
    }

    $articleEditMoveSlug = 'http-edit-move-article-save-' . bin2hex(random_bytes(4));
    $articleEditMoveResponse = postUrl($baseUrl . BASE_URL . '/admin/blog_save.php', [
        'csrf_token' => $authorSession['csrf'],
        'id' => (string)$articleEditMoveId,
        'blog_id' => (string)$targetBlogId,
        'title' => 'HTTP Edit přes blog_save',
        'slug' => $articleEditMoveSlug,
        'perex' => '',
        'content' => '<p>HTTP integration test.</p>',
        'category_id' => '',
        'category_selection_mode' => 'auto',
        'tag_selection_mode' => 'auto',
        'redirect' => BASE_URL . '/admin/blog.php?blog=' . $targetBlogId,
        'comments_enabled' => '1',
    ], $authorSession['cookie'], 0);
    if (httpIntegrationStatusCode($articleEditMoveResponse) !== 302) {
        $blogTransferIssues[] = 'editace článku přes blog_save při změně blogu nevrátila redirect';
    }
    $editMovedArticleStmt = $pdo->prepare("SELECT blog_id, category_id FROM cms_articles WHERE id = ?");
    $editMovedArticleStmt->execute([$articleEditMoveId]);
    $editMovedArticle = $editMovedArticleStmt->fetch() ?: [];
    if ((int)($editMovedArticle['blog_id'] ?? 0) !== $targetBlogId) {
        $blogTransferIssues[] = 'editace článku přes blog_save nepřesunula článek do cílového blogu';
    }
    if ((int)($editMovedArticle['category_id'] ?? 0) !== $sharedTargetCategoryId) {
        $blogTransferIssues[] = 'editace článku přes blog_save nenamapovala stejně pojmenovanou kategorii cílového blogu';
    }
    $editMovedTagStmt = $pdo->prepare("SELECT tag_id FROM cms_article_tags WHERE article_id = ? ORDER BY tag_id ASC");
    $editMovedTagStmt->execute([$articleEditMoveId]);
    $editMovedTagIds = array_values(array_map('intval', array_column($editMovedTagStmt->fetchAll() ?: [], 'tag_id')));
    if ($editMovedTagIds !== [$sharedTargetTagId]) {
        $blogTransferIssues[] = 'editace článku přes blog_save nepřenesla odpovídající štítek cílového blogu';
    }

    $manualMoveResponse = postUrl($baseUrl . BASE_URL . '/admin/blog_save.php', [
        'csrf_token' => $authorSession['csrf'],
        'id' => (string)$articleManualMoveId,
        'blog_id' => (string)$targetBlogId,
        'title' => 'HTTP Edit ruční volba',
        'slug' => 'http-edit-manual-article-save-' . bin2hex(random_bytes(4)),
        'perex' => '',
        'content' => '<p>HTTP integration test.</p>',
        'category_id' => (string)$targetCategoryId,
        'tags' => [(string)$targetTagId],
        'category_selection_mode' => 'manual',
        'tag_selection_mode' => 'manual',
        'missing_category_action' => 'drop',
        'missing_tags_action' => 'drop',
        'redirect' => BASE_URL . '/admin/blog.php?blog=' . $targetBlogId,
        'comments_enabled' => '1',
    ], $authorSession['cookie'], 0);
    if (httpIntegrationStatusCode($manualMoveResponse) !== 302) {
        $blogTransferIssues[] = 'editace článku s ruční volbou taxonomií nevrátila redirect';
    }
    $manualMovedArticleStmt = $pdo->prepare("SELECT blog_id, category_id FROM cms_articles WHERE id = ?");
    $manualMovedArticleStmt->execute([$articleManualMoveId]);
    $manualMovedArticle = $manualMovedArticleStmt->fetch() ?: [];
    if ((int)($manualMovedArticle['blog_id'] ?? 0) !== $targetBlogId) {
        $blogTransferIssues[] = 'editace článku s ruční volbou nepřesunula článek do cílového blogu';
    }
    if ((int)($manualMovedArticle['category_id'] ?? 0) !== $targetCategoryId) {
        $blogTransferIssues[] = 'editace článku s ruční volbou nepoužila ručně vybranou cílovou kategorii';
    }
    $manualMovedTagStmt = $pdo->prepare("SELECT tag_id FROM cms_article_tags WHERE article_id = ? ORDER BY tag_id ASC");
    $manualMovedTagStmt->execute([$articleManualMoveId]);
    $manualMovedTagIds = array_values(array_map('intval', array_column($manualMovedTagStmt->fetchAll() ?: [], 'tag_id')));
    if ($manualMovedTagIds !== [$targetTagId]) {
        $blogTransferIssues[] = 'editace článku s ruční volbou nepoužila ručně vybrané cílové štítky';
    }

    $createMoveResponse = postUrl($baseUrl . BASE_URL . '/admin/blog_save.php', [
        'csrf_token' => $authorSession['csrf'],
        'id' => (string)$articleCreateMoveId,
        'blog_id' => (string)$targetBlogId,
        'title' => 'HTTP Edit vytvoření taxonomií',
        'slug' => 'http-edit-create-article-save-' . bin2hex(random_bytes(4)),
        'perex' => '',
        'content' => '<p>HTTP integration test.</p>',
        'category_id' => '',
        'category_selection_mode' => 'auto',
        'tag_selection_mode' => 'auto',
        'missing_category_action' => 'create',
        'missing_tags_action' => 'create',
        'redirect' => BASE_URL . '/admin/blog.php?blog=' . $targetBlogId,
        'comments_enabled' => '1',
    ], $authorSession['cookie'], 0);
    if (httpIntegrationStatusCode($createMoveResponse) !== 302) {
        $blogTransferIssues[] = 'editace článku s vytvořením chybějících taxonomií nevrátila redirect';
    }
    $createdTargetCategoryStmt = $pdo->prepare("SELECT id FROM cms_categories WHERE blog_id = ? AND name = ? ORDER BY id DESC LIMIT 1");
    $createdTargetCategoryStmt->execute([$targetBlogId, $createSourceCategoryName]);
    $createdTargetCategoryId = (int)($createdTargetCategoryStmt->fetchColumn() ?: 0);
    if ($createdTargetCategoryId <= 0) {
        $blogTransferIssues[] = 'editace článku s vytvořením taxonomií nevytvořila chybějící kategorii';
    } elseif (!in_array($createdTargetCategoryId, $createdCategories, true)) {
        $createdCategories[] = $createdTargetCategoryId;
    }
    $createdTargetTagStmt = $pdo->prepare("SELECT id FROM cms_tags WHERE blog_id = ? AND slug = ? ORDER BY id DESC LIMIT 1");
    $createdTargetTagStmt->execute([$targetBlogId, $createSourceTagSlug]);
    $createdTargetTagId = (int)($createdTargetTagStmt->fetchColumn() ?: 0);
    if ($createdTargetTagId <= 0) {
        $blogTransferIssues[] = 'editace článku s vytvořením taxonomií nevytvořila chybějící štítek';
    } elseif (!in_array($createdTargetTagId, $createdTags, true)) {
        $createdTags[] = $createdTargetTagId;
    }
    $createdMoveArticleStmt = $pdo->prepare("SELECT blog_id, category_id FROM cms_articles WHERE id = ?");
    $createdMoveArticleStmt->execute([$articleCreateMoveId]);
    $createdMoveArticle = $createdMoveArticleStmt->fetch() ?: [];
    if ((int)($createdMoveArticle['blog_id'] ?? 0) !== $targetBlogId) {
        $blogTransferIssues[] = 'editace článku s vytvořením taxonomií nepřesunula článek do cílového blogu';
    }
    if ($createdTargetCategoryId > 0 && (int)($createdMoveArticle['category_id'] ?? 0) !== $createdTargetCategoryId) {
        $blogTransferIssues[] = 'editace článku s vytvořením taxonomií nepřiřadila novou kategorii';
    }
    $createdMoveTagStmt = $pdo->prepare("SELECT tag_id FROM cms_article_tags WHERE article_id = ? ORDER BY tag_id ASC");
    $createdMoveTagStmt->execute([$articleCreateMoveId]);
    $createdMoveTagIds = array_values(array_map('intval', array_column($createdMoveTagStmt->fetchAll() ?: [], 'tag_id')));
    if ($createdTargetTagId > 0 && $createdMoveTagIds !== [$createdTargetTagId]) {
        $blogTransferIssues[] = 'editace článku s vytvořením taxonomií nepřiřadila nový štítek';
    }

    $noTaxCreateResponse = postUrl($baseUrl . BASE_URL . '/admin/blog_save.php', [
        'csrf_token' => $authorNoTaxSession['csrf'],
        'id' => (string)$articleNoTaxId,
        'blog_id' => (string)$targetBlogId,
        'title' => 'HTTP Bez taxonomy práv',
        'slug' => 'http-no-taxonomy-create-' . bin2hex(random_bytes(4)),
        'perex' => '',
        'content' => '<p>HTTP integration test.</p>',
        'category_id' => '',
        'category_selection_mode' => 'auto',
        'tag_selection_mode' => 'auto',
        'missing_category_action' => 'create',
        'missing_tags_action' => 'create',
        'redirect' => BASE_URL . '/admin/blog.php?blog=' . $targetBlogId,
        'comments_enabled' => '1',
    ], $authorNoTaxSession['cookie'], 0);
    if (httpIntegrationStatusCode($noTaxCreateResponse) !== 302) {
        $blogTransferIssues[] = 'nepovolené vytvoření taxonomií v editoru článku nevrátilo redirect';
    }
    $noTaxCreateLocationHeader = '';
    foreach (($noTaxCreateResponse['headers'] ?? []) as $headerLine) {
        if (stripos((string)$headerLine, 'Location:') === 0) {
            $noTaxCreateLocationHeader = trim(substr((string)$headerLine, 9));
            break;
        }
    }
    if ($noTaxCreateLocationHeader === '' || !str_contains($noTaxCreateLocationHeader, BASE_URL . '/admin/blog_form.php') || !str_contains($noTaxCreateLocationHeader, 'err=missing_category_action')) {
        $blogTransferIssues[] = 'nepovolené vytvoření taxonomií v editoru článku nemíří zpět na formulář s chybou';
    }
    $noTaxCreateArticleStmt = $pdo->prepare("SELECT blog_id, category_id FROM cms_articles WHERE id = ?");
    $noTaxCreateArticleStmt->execute([$articleNoTaxId]);
    $noTaxCreateArticle = $noTaxCreateArticleStmt->fetch() ?: [];
    if ((int)($noTaxCreateArticle['blog_id'] ?? 0) !== $sourceBlogId) {
        $blogTransferIssues[] = 'nepovolené vytvoření taxonomií v editoru článku přesto změnilo blog článku';
    }
    if ((int)($noTaxCreateArticle['category_id'] ?? 0) !== $sourceCategoryId) {
        $blogTransferIssues[] = 'nepovolené vytvoření taxonomií v editoru článku přesto změnilo kategorii článku';
    }

    $invalidCategoryResponse = postUrl($baseUrl . BASE_URL . '/admin/blog_save.php', [
        'csrf_token' => $authorSession['csrf'],
        'id' => (string)$articleForeignAttemptId,
        'blog_id' => (string)$targetBlogId,
        'title' => 'HTTP Cizí mapování',
        'slug' => 'http-invalid-category-' . bin2hex(random_bytes(4)),
        'perex' => '',
        'content' => '<p>HTTP integration test.</p>',
        'category_id' => (string)$foreignCategoryId,
        'category_selection_mode' => 'manual',
        'tag_selection_mode' => 'manual',
        'redirect' => BASE_URL . '/admin/blog.php?blog=' . $targetBlogId,
        'comments_enabled' => '1',
    ], $authorSession['cookie'], 0);
    if (httpIntegrationStatusCode($invalidCategoryResponse) !== 302) {
        $blogTransferIssues[] = 'podvržená cizí kategorie v editoru článku nevrátila redirect';
    }
    $invalidCategoryLocationHeader = '';
    foreach (($invalidCategoryResponse['headers'] ?? []) as $headerLine) {
        if (stripos((string)$headerLine, 'Location:') === 0) {
            $invalidCategoryLocationHeader = trim(substr((string)$headerLine, 9));
            break;
        }
    }
    if ($invalidCategoryLocationHeader === '' || !str_contains($invalidCategoryLocationHeader, BASE_URL . '/admin/blog_form.php') || !str_contains($invalidCategoryLocationHeader, 'err=category_target')) {
        $blogTransferIssues[] = 'podvržená cizí kategorie v editoru článku nemíří zpět na formulář s validační chybou';
    }
    $invalidCategoryArticleStmt = $pdo->prepare("SELECT blog_id, category_id FROM cms_articles WHERE id = ?");
    $invalidCategoryArticleStmt->execute([$articleForeignAttemptId]);
    $invalidCategoryArticle = $invalidCategoryArticleStmt->fetch() ?: [];
    if ((int)($invalidCategoryArticle['blog_id'] ?? 0) !== $sourceBlogId || (int)($invalidCategoryArticle['category_id'] ?? 0) !== $sourceCategoryId) {
        $blogTransferIssues[] = 'podvržená cizí kategorie v editoru článku přesto změnila článek';
    }

    $invalidTagsResponse = postUrl($baseUrl . BASE_URL . '/admin/blog_save.php', [
        'csrf_token' => $authorSession['csrf'],
        'id' => (string)$articleForeignAttemptId,
        'blog_id' => (string)$targetBlogId,
        'title' => 'HTTP Cizí mapování',
        'slug' => 'http-invalid-tags-' . bin2hex(random_bytes(4)),
        'perex' => '',
        'content' => '<p>HTTP integration test.</p>',
        'category_id' => (string)$targetCategoryId,
        'tags' => [(string)$foreignTagId],
        'category_selection_mode' => 'manual',
        'tag_selection_mode' => 'manual',
        'redirect' => BASE_URL . '/admin/blog.php?blog=' . $targetBlogId,
        'comments_enabled' => '1',
    ], $authorSession['cookie'], 0);
    if (httpIntegrationStatusCode($invalidTagsResponse) !== 302) {
        $blogTransferIssues[] = 'podvržené cizí štítky v editoru článku nevrátily redirect';
    }
    $invalidTagsLocationHeader = '';
    foreach (($invalidTagsResponse['headers'] ?? []) as $headerLine) {
        if (stripos((string)$headerLine, 'Location:') === 0) {
            $invalidTagsLocationHeader = trim(substr((string)$headerLine, 9));
            break;
        }
    }
    if ($invalidTagsLocationHeader === '' || !str_contains($invalidTagsLocationHeader, BASE_URL . '/admin/blog_form.php') || !str_contains($invalidTagsLocationHeader, 'err=tags_target')) {
        $blogTransferIssues[] = 'podvržené cizí štítky v editoru článku nemíří zpět na formulář s validační chybou';
    }
    $invalidTagsArticleStmt = $pdo->prepare("SELECT blog_id, category_id FROM cms_articles WHERE id = ?");
    $invalidTagsArticleStmt->execute([$articleForeignAttemptId]);
    $invalidTagsArticle = $invalidTagsArticleStmt->fetch() ?: [];
    if ((int)($invalidTagsArticle['blog_id'] ?? 0) !== $sourceBlogId || (int)($invalidTagsArticle['category_id'] ?? 0) !== $sourceCategoryId) {
        $blogTransferIssues[] = 'podvržené cizí štítky v editoru článku přesto změnily článek';
    }

    $blogTransferUrl = $baseUrl . BASE_URL . '/admin/blog_transfer.php';
    $positiveTransferResponse = postUrl($blogTransferUrl, [
        'csrf_token' => $authorSession['csrf'],
        'target_blog_id' => (string)$targetBlogId,
        'redirect' => BASE_URL . '/admin/blog.php',
        'category_strategy' => 'map_existing',
        'tag_strategy' => 'map_existing',
        'category_map' => [
            mb_strtolower($sourceCategoryName, 'UTF-8') => (string)$targetCategoryId,
        ],
        'tag_map' => [
            'slug:' . $sourceTagOneSlug => (string)$targetTagId,
            'slug:' . $sourceTagTwoSlug => (string)$targetTagId,
        ],
    ], $authorSession['cookie'], 0);
    if (httpIntegrationStatusCode($positiveTransferResponse) !== 302) {
        $blogTransferIssues[] = 'map_existing přesun článku nevrátil redirect';
    }
    if (!responseHasLocationHeader($positiveTransferResponse['headers'], BASE_URL . '/admin/blog.php', $baseUrl)) {
        $blogTransferIssues[] = 'map_existing přesun článku nemíří zpět na přehled článků';
    }
    $movedArticle = $pdo->prepare("SELECT blog_id, category_id FROM cms_articles WHERE id = ?");
    $movedArticle->execute([$articleId]);
    $movedArticle = $movedArticle->fetch() ?: [];
    if ((int)($movedArticle['blog_id'] ?? 0) !== $targetBlogId) {
        $blogTransferIssues[] = 'map_existing nepřesunul článek do cílového blogu';
    }
    if ((int)($movedArticle['category_id'] ?? 0) !== $targetCategoryId) {
        $blogTransferIssues[] = 'map_existing nenamapoval kategorii na existující cílovou kategorii';
    }
    $mappedTags = $pdo->prepare("SELECT tag_id FROM cms_article_tags WHERE article_id = ? ORDER BY tag_id");
    $mappedTags->execute([$articleId]);
    $mappedTagIds = array_values(array_map('intval', array_column($mappedTags->fetchAll() ?: [], 'tag_id')));
    if ($mappedTagIds !== [$targetTagId]) {
        $blogTransferIssues[] = 'map_existing nevytvořil deduplikovanou vazbu na cílový štítek';
    }

    $noTaxPage = fetchUrl($blogTransferUrl . '?target_blog_id=' . $targetBlogId, $authorNoTaxSession['cookie'], 0);
    if (httpIntegrationStatusCode($noTaxPage) !== 200) {
        $blogTransferIssues[] = 'kontrola skrytí map_existing pro běžného autora nevrátila validační obrazovku';
    }
    if (
        str_contains($noTaxPage['body'], 'name="category_strategy" value="map_existing"')
        || str_contains($noTaxPage['body'], 'name="tag_strategy" value="map_existing"')
    ) {
        $blogTransferIssues[] = 'běžný autor bez taxonomy práv stále vidí volbu map_existing';
    }

    $foreignAttemptSession = koraPrimeTestSession([
        'cms_logged_in' => true,
        'cms_superadmin' => false,
        'cms_user_id' => $authorId,
        'cms_user_name' => 'HTTP Author',
        'cms_user_role' => 'author',
        'blog_transfer_selection' => [
            'ids' => [$articleForeignAttemptId],
            'redirect' => BASE_URL . '/admin/blog.php',
            'created_at' => time(),
        ],
    ], 'kora-http-transfer-author-foreign');
    $foreignAttemptResponse = postUrl($blogTransferUrl, [
        'csrf_token' => $foreignAttemptSession['csrf'],
        'target_blog_id' => (string)$targetBlogId,
        'redirect' => BASE_URL . '/admin/blog.php',
        'category_strategy' => 'map_existing',
        'tag_strategy' => 'map_existing',
        'category_map' => [
            mb_strtolower($sourceCategoryName, 'UTF-8') => (string)$foreignCategoryId,
        ],
        'tag_map' => [
            'slug:' . $sourceTagOneSlug => (string)$foreignTagId,
        ],
    ], $foreignAttemptSession['cookie'], 0);
    if (httpIntegrationStatusCode($foreignAttemptResponse) !== 200) {
        $blogTransferIssues[] = 'podvržená cizí taxonomie nevrátila validační obrazovku';
    }
    if (!str_contains($foreignAttemptResponse['body'], 'Vybraná cílová kategorie nepatří do cílového blogu.')) {
        $blogTransferIssues[] = 'podvržená cizí kategorie nezobrazila validační chybu';
    }
    $unchangedArticle = $pdo->prepare("SELECT blog_id, category_id FROM cms_articles WHERE id = ?");
    $unchangedArticle->execute([$articleForeignAttemptId]);
    $unchangedArticle = $unchangedArticle->fetch() ?: [];
    if ((int)($unchangedArticle['blog_id'] ?? 0) !== $sourceBlogId) {
        $blogTransferIssues[] = 'podvržená cizí taxonomie přesto změnila blog článku';
    }
    if ((int)($unchangedArticle['category_id'] ?? 0) !== $sourceCategoryId) {
        $blogTransferIssues[] = 'podvržená cizí taxonomie přesto změnila kategorii článku';
    }

    httpIntegrationPrintResult('blog_transfer_http', $blogTransferIssues, $failures);

    $blogStaticPagesIssues = [];
    $blogStaticMainSlug = 'http-blog-pages-main-' . bin2hex(random_bytes(4));
    $blogStaticOtherSlug = 'http-blog-pages-other-' . bin2hex(random_bytes(4));
    foreach ([
        ['name' => 'HTTP Blogové stránky', 'slug' => $blogStaticMainSlug],
        ['name' => 'HTTP Jiný blog', 'slug' => $blogStaticOtherSlug],
    ] as $blogRow) {
        $pdo->prepare(
            "INSERT INTO cms_blogs (name, slug, created_by_user_id) VALUES (?, ?, ?)"
        )->execute([$blogRow['name'], $blogRow['slug'], $adminUserId]);
        $createdBlogs[] = (int)$pdo->lastInsertId();
    }
    [$blogStaticMainId, $blogStaticOtherId] = array_slice($createdBlogs, -2);

    $pageSaveUrl = $baseUrl . BASE_URL . '/admin/page_save.php';
    $blogPagesAdminUrl = $baseUrl . BASE_URL . '/admin/blog_pages.php?blog_id=' . $blogStaticMainId;
    $blogStaticIndexUrl = $baseUrl . BASE_URL . '/' . rawurlencode($blogStaticMainSlug) . '/';

    $blogPageOneTitle = 'HTTP Blogová stránka A';
    $blogPageOneSlug = 'http-blog-page-a-' . bin2hex(random_bytes(4));
    $blogPageOneContent = '<p>Obsah první blogové stránky pro HTTP integration test.</p>';
    $blogPageOneResponse = postUrl($pageSaveUrl, [
        'csrf_token' => $adminSession['csrf'],
        'redirect' => BASE_URL . '/admin/pages.php',
        'title' => $blogPageOneTitle,
        'slug' => $blogPageOneSlug,
        'content' => $blogPageOneContent,
        'blog_id' => (string)$blogStaticMainId,
        'is_published' => '1',
        'show_in_nav' => '1',
        'admin_note' => 'HTTP integration test',
    ], $adminSession['cookie'], 0);
    if (httpIntegrationStatusCode($blogPageOneResponse) !== 302) {
        $blogStaticPagesIssues[] = 'uložení první blogové stránky nevrátilo redirect';
    }
    if (!responseHasLocationHeader($blogPageOneResponse['headers'], BASE_URL . '/admin/pages.php', $baseUrl)) {
        $blogStaticPagesIssues[] = 'uložení první blogové stránky nemíří zpět na přehled stránek';
    }
    $blogPageOneStmt = $pdo->prepare("SELECT * FROM cms_pages WHERE slug = ? ORDER BY id DESC LIMIT 1");
    $blogPageOneStmt->execute([$blogPageOneSlug]);
    $blogPageOne = $blogPageOneStmt->fetch() ?: null;
    if (!$blogPageOne) {
        $blogStaticPagesIssues[] = 'první blogová stránka se po uložení nevytvořila';
    } else {
        $createdPageIds[] = (int)$blogPageOne['id'];
        if ((int)($blogPageOne['blog_id'] ?? 0) !== $blogStaticMainId) {
            $blogStaticPagesIssues[] = 'první blogová stránka není přiřazená správnému blogu';
        }
        if ((int)($blogPageOne['show_in_nav'] ?? 1) !== 0) {
            $blogStaticPagesIssues[] = 'první blogová stránka se neměla zobrazit v globální navigaci';
        }
        if ((int)($blogPageOne['blog_nav_order'] ?? 0) !== 1) {
            $blogStaticPagesIssues[] = 'první blogová stránka nemá očekávané pořadí 1';
        }
    }

    $blogPageTwoTitle = 'HTTP Blogová stránka B';
    $blogPageTwoSlug = 'http-blog-page-b-' . bin2hex(random_bytes(4));
    $blogPageTwoContent = '<p>Obsah druhé blogové stránky pro HTTP integration test.</p>';
    $blogPageTwoResponse = postUrl($pageSaveUrl, [
        'csrf_token' => $adminSession['csrf'],
        'redirect' => BASE_URL . '/admin/pages.php',
        'title' => $blogPageTwoTitle,
        'slug' => $blogPageTwoSlug,
        'content' => $blogPageTwoContent,
        'blog_id' => (string)$blogStaticMainId,
        'is_published' => '1',
        'show_in_nav' => '1',
        'admin_note' => 'HTTP integration test',
    ], $adminSession['cookie'], 0);
    if (httpIntegrationStatusCode($blogPageTwoResponse) !== 302) {
        $blogStaticPagesIssues[] = 'uložení druhé blogové stránky nevrátilo redirect';
    }
    $blogPageTwoStmt = $pdo->prepare("SELECT * FROM cms_pages WHERE slug = ? ORDER BY id DESC LIMIT 1");
    $blogPageTwoStmt->execute([$blogPageTwoSlug]);
    $blogPageTwo = $blogPageTwoStmt->fetch() ?: null;
    if (!$blogPageTwo) {
        $blogStaticPagesIssues[] = 'druhá blogová stránka se po uložení nevytvořila';
    } else {
        $createdPageIds[] = (int)$blogPageTwo['id'];
        if ((int)($blogPageTwo['blog_nav_order'] ?? 0) !== 2) {
            $blogStaticPagesIssues[] = 'druhá blogová stránka nemá očekávané pořadí 2';
        }
    }

    $blogPagesAdminResponse = fetchUrl($blogPagesAdminUrl, $adminSession['cookie'], 0);
    if (httpIntegrationStatusCode($blogPagesAdminResponse) !== 200) {
        $blogStaticPagesIssues[] = 'správa pořadí blogových stránek se nenačetla';
    }
    if (!str_contains($blogPagesAdminResponse['body'], 'Pořadí stránek blogu')) {
        $blogStaticPagesIssues[] = 'správa pořadí blogových stránek nemá očekávaný nadpis';
    }
    if ($blogPageOne && $blogPageTwo) {
        $reorderResponse = postUrl(
            $baseUrl . BASE_URL . '/admin/blog_pages.php?blog_id=' . $blogStaticMainId,
            [
                'csrf_token' => $adminSession['csrf'],
                'redirect' => BASE_URL . '/admin/blog_pages.php?blog_id=' . $blogStaticMainId,
                'order' => [(string)$blogPageTwo['id'], (string)$blogPageOne['id']],
            ],
            $adminSession['cookie'],
            0
        );
        if (httpIntegrationStatusCode($reorderResponse) !== 302) {
            $blogStaticPagesIssues[] = 'uložení pořadí blogových stránek nevrátilo redirect';
        }
        if (!responseHasLocationHeader($reorderResponse['headers'], BASE_URL . '/admin/blog_pages.php?blog_id=' . $blogStaticMainId . '&saved=1', $baseUrl)) {
            $blogStaticPagesIssues[] = 'uložení pořadí blogových stránek nemíří zpět na reorder screen';
        }
        $reorderedStmt = $pdo->prepare("SELECT id, blog_nav_order FROM cms_pages WHERE id IN (?, ?) ORDER BY blog_nav_order, id");
        $reorderedStmt->execute([(int)$blogPageOne['id'], (int)$blogPageTwo['id']]);
        $reorderedRows = $reorderedStmt->fetchAll() ?: [];
        if (count($reorderedRows) !== 2
            || (int)$reorderedRows[0]['id'] !== (int)$blogPageTwo['id']
            || (int)$reorderedRows[0]['blog_nav_order'] !== 1
            || (int)$reorderedRows[1]['id'] !== (int)$blogPageOne['id']
            || (int)$reorderedRows[1]['blog_nav_order'] !== 2) {
            $blogStaticPagesIssues[] = 'reorder blogových stránek neuložil očekávané pořadí';
        }
    }

    $blogArticleTitle = 'HTTP Článek v blogu se stránkami';
    $blogArticleSlug = 'http-blog-page-article-' . bin2hex(random_bytes(4));
    $pdo->prepare(
        "INSERT INTO cms_articles (title, slug, blog_id, perex, content, comments_enabled, status)
         VALUES (?, ?, ?, ?, ?, 1, 'published')"
    )->execute([
        $blogArticleTitle,
        $blogArticleSlug,
        $blogStaticMainId,
        'Krátký perex pro blogový index.',
        '<p>Obsah článku pro blogový index.</p>',
    ]);
    $blogArticleId = (int)$pdo->lastInsertId();
    $createdArticles[] = $blogArticleId;

    saveSetting('module_blog', '1');
    clearSettingsCache();

    $blogIndexResponse = fetchUrl($blogStaticIndexUrl, '', 0);
    if (httpIntegrationStatusCode($blogIndexResponse) !== 200) {
        $blogStaticPagesIssues[] = 'veřejný blogový index s navigací stránek se nenačetl';
    }
    if (!str_contains($blogIndexResponse['body'], 'blog-page-links')) {
        $blogStaticPagesIssues[] = 'veřejný blogový index neobsahuje blok Stránky blogu';
    }
    if (!str_contains($blogIndexResponse['body'], $blogPageOneTitle) || !str_contains($blogIndexResponse['body'], $blogPageTwoTitle)) {
        $blogStaticPagesIssues[] = 'veřejný blogový index neobsahuje odkazy na blogové stránky';
    }
    if (!str_contains($blogIndexResponse['body'], $blogArticleTitle)) {
        $blogStaticPagesIssues[] = 'veřejný blogový index nezobrazuje články pod navigací blogových stránek';
    }
    if (str_contains($blogIndexResponse['body'], 'Obsah první blogové stránky pro HTTP integration test.')) {
        $blogStaticPagesIssues[] = 'veřejný blogový index nemá zobrazovat obsah blogové stránky automaticky';
    }
    $pagesHeadingPos = strpos($blogIndexResponse['body'], 'blog-page-links');
    $articleTitlePos = strpos($blogIndexResponse['body'], $blogArticleTitle);
    if ($pagesHeadingPos === false || $articleTitlePos === false || $pagesHeadingPos > $articleTitlePos) {
        $blogStaticPagesIssues[] = 'blok Stránky blogu není na indexu vykreslený nad články';
    }

    $blogPageDetailUrl = $baseUrl . BASE_URL . '/' . rawurlencode($blogStaticMainSlug) . '/stranka/' . rawurlencode($blogPageOneSlug);
    $blogPageDetailResponse = fetchUrl($blogPageDetailUrl, '', 0);
    if (httpIntegrationStatusCode($blogPageDetailResponse) !== 200) {
        $blogStaticPagesIssues[] = 'detail blogové stránky se nenačetl';
    }
    if (!str_contains($blogPageDetailResponse['body'], 'page-blog-static')
        || !str_contains($blogPageDetailResponse['body'], $blogPageOneTitle)
        || !str_contains($blogPageDetailResponse['body'], 'Obsah první blogové stránky pro HTTP integration test.')) {
        $blogStaticPagesIssues[] = 'detail blogové stránky nezobrazuje očekávaný obsah';
    }
    if (!str_contains($blogPageDetailResponse['body'], 'href="' . h(blogIndexPath(['slug' => $blogStaticMainSlug])) . '"')) {
        $blogStaticPagesIssues[] = 'detail blogové stránky nemá odkaz zpět na blog';
    }
    foreach ([
        'Stránky blogu',
        'Hledání v blogu',
        'Archiv blogu',
        'Další blogy webu',
        $blogArticleTitle,
    ] as $forbiddenFragment) {
        if (str_contains($blogPageDetailResponse['body'], $forbiddenFragment)) {
            $blogStaticPagesIssues[] = 'detail blogové stránky stále obsahuje blogový indexový blok: ' . $forbiddenFragment;
        }
    }

    $foreignBlogPageResponse = fetchUrl(
        $baseUrl . BASE_URL . '/' . rawurlencode($blogStaticOtherSlug) . '/stranka/' . rawurlencode($blogPageOneSlug),
        '',
        0
    );
    if (httpIntegrationStatusCode($foreignBlogPageResponse) !== 404) {
        $blogStaticPagesIssues[] = 'blogová stránka je dostupná i pod cizím blogem místo 404';
    }

    $convertArticleTitle = 'HTTP Převod článku na blogovou stránku';
    $convertArticleSlug = 'http-blog-page-convert-' . bin2hex(random_bytes(4));
    $pdo->prepare(
        "INSERT INTO cms_articles (title, slug, blog_id, perex, content, comments_enabled, status)
         VALUES (?, ?, ?, ?, ?, 1, 'published')"
    )->execute([
        $convertArticleTitle,
        $convertArticleSlug,
        $blogStaticMainId,
        'Perex pro převod článku.',
        '<p>Obsah převáděného článku.</p>',
    ]);
    $convertSourceArticleId = (int)$pdo->lastInsertId();
    $createdArticles[] = $convertSourceArticleId;

    $articleToPageResponse = postUrl(
        $baseUrl . BASE_URL . '/admin/convert_content.php',
        [
            'csrf_token' => $adminSession['csrf'],
            'direction' => 'article_to_page',
            'id' => (string)$convertSourceArticleId,
        ],
        $adminSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($articleToPageResponse) !== 302) {
        $blogStaticPagesIssues[] = 'převod článku na blogovou stránku nevrátil redirect';
    }
    $convertedPageStmt = $pdo->prepare("SELECT * FROM cms_pages WHERE title = ? ORDER BY id DESC LIMIT 1");
    $convertedPageStmt->execute([$convertArticleTitle]);
    $convertedPage = $convertedPageStmt->fetch() ?: null;
    if (!$convertedPage) {
        $blogStaticPagesIssues[] = 'převod článku na stránku nevytvořil blogovou stránku';
    } else {
        $createdPageIds[] = (int)$convertedPage['id'];
        if ((int)($convertedPage['blog_id'] ?? 0) !== $blogStaticMainId) {
            $blogStaticPagesIssues[] = 'převod článku na stránku nezachoval blog původního článku';
        }
        if ((int)($convertedPage['blog_nav_order'] ?? 0) !== 3) {
            $blogStaticPagesIssues[] = 'převod článku na stránku nezařadil novou blogovou stránku na konec pořadí';
        }

        $pageToArticleResponse = postUrl(
            $baseUrl . BASE_URL . '/admin/convert_content.php',
            [
                'csrf_token' => $adminSession['csrf'],
                'direction' => 'page_to_article',
                'id' => (string)$convertedPage['id'],
            ],
            $adminSession['cookie'],
            0
        );
        if (httpIntegrationStatusCode($pageToArticleResponse) !== 302) {
            $blogStaticPagesIssues[] = 'převod blogové stránky zpět na článek nevrátil redirect';
        }
        $reconvertedArticleStmt = $pdo->prepare("SELECT * FROM cms_articles WHERE title = ? ORDER BY id DESC LIMIT 1");
        $reconvertedArticleStmt->execute([$convertArticleTitle]);
        $reconvertedArticle = $reconvertedArticleStmt->fetch() ?: null;
        if (!$reconvertedArticle) {
            $blogStaticPagesIssues[] = 'převod blogové stránky zpět na článek nevytvořil článek';
        } else {
            $createdArticles[] = (int)$reconvertedArticle['id'];
            if ((int)($reconvertedArticle['blog_id'] ?? 0) !== $blogStaticMainId) {
                $blogStaticPagesIssues[] = 'převod blogové stránky zpět na článek nezachoval původní blog';
            }
        }
    }

    httpIntegrationPrintResult('blog_static_pages_http', $blogStaticPagesIssues, $failures);

    $blogSlugRedirectIssues = [];
    $blogSlugRedirectOriginalSlug = 'http-blog-slug-main-' . bin2hex(random_bytes(4));
    $blogSlugRedirectOtherSlug = 'http-blog-slug-other-' . bin2hex(random_bytes(4));
    foreach ([
        ['name' => 'HTTP Redirect blog', 'slug' => $blogSlugRedirectOriginalSlug],
        ['name' => 'HTTP Redirect other blog', 'slug' => $blogSlugRedirectOtherSlug],
    ] as $blogSlugRow) {
        $pdo->prepare(
            "INSERT INTO cms_blogs (name, slug, created_by_user_id) VALUES (?, ?, ?)"
        )->execute([$blogSlugRow['name'], $blogSlugRow['slug'], $adminUserId]);
        $createdBlogs[] = (int)$pdo->lastInsertId();
    }
    [$blogSlugRedirectMainId, $blogSlugRedirectOtherId] = array_slice($createdBlogs, -2);

    $blogSlugRedirectPageSlug = 'http-blog-slug-page-' . bin2hex(random_bytes(4));
    $pdo->prepare(
        "INSERT INTO cms_pages (title, slug, content, blog_id, is_published, show_in_nav, blog_nav_order, status)
         VALUES (?, ?, ?, ?, 1, 0, 1, 'published')"
    )->execute([
        'HTTP Redirect page',
        $blogSlugRedirectPageSlug,
        '<p>Obsah testovací blogové stránky.</p>',
        $blogSlugRedirectMainId,
    ]);
    $createdPageIds[] = (int)$pdo->lastInsertId();

    $blogSlugRedirectArticleSlug = 'http-blog-slug-article-' . bin2hex(random_bytes(4));
    $pdo->prepare(
        "INSERT INTO cms_articles (title, slug, blog_id, perex, content, comments_enabled, status)
         VALUES (?, ?, ?, ?, ?, 1, 'published')"
    )->execute([
        'HTTP Redirect article',
        $blogSlugRedirectArticleSlug,
        $blogSlugRedirectMainId,
        'Perex testovacího článku.',
        '<p>Obsah testovacího článku.</p>',
    ]);
    $createdArticles[] = (int)$pdo->lastInsertId();

    $blogSlugRedirectTemporarySlug = $blogSlugRedirectOriginalSlug . '-redirect-' . bin2hex(random_bytes(3));
    if (strlen($blogSlugRedirectTemporarySlug) > 100) {
        $blogSlugRedirectTemporarySlug = substr($blogSlugRedirectTemporarySlug, 0, 100);
    }
    $blogSlugRedirectSnapshotStmt = $pdo->prepare("SELECT old_slug FROM cms_blog_slug_redirects WHERE blog_id = ? ORDER BY id");
    $blogSlugRedirectSnapshotStmt->execute([$blogSlugRedirectMainId]);
    $blogSlugRedirectSnapshot = $blogSlugRedirectSnapshotStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    try {
        saveBlogSlugRedirect($pdo, $blogSlugRedirectMainId, $blogSlugRedirectOriginalSlug);
        $pdo->prepare("UPDATE cms_blogs SET slug = ? WHERE id = ?")->execute([$blogSlugRedirectTemporarySlug, $blogSlugRedirectMainId]);
        clearBlogCache();

        $blogSlugChecks = [
            [
                'label' => 'old_blog_index',
                'url' => $baseUrl . BASE_URL . '/' . rawurlencode($blogSlugRedirectOriginalSlug) . '/',
                'expected_code' => 301,
                'expected_location' => BASE_URL . '/' . rawurlencode($blogSlugRedirectTemporarySlug) . '/',
            ],
            [
                'label' => 'new_blog_index',
                'url' => $baseUrl . BASE_URL . '/' . rawurlencode($blogSlugRedirectTemporarySlug) . '/',
                'expected_code' => 200,
            ],
            [
                'label' => 'old_blog_article',
                'url' => $baseUrl . BASE_URL . '/' . rawurlencode($blogSlugRedirectOriginalSlug) . '/' . rawurlencode($blogSlugRedirectArticleSlug),
                'expected_code' => 301,
                'expected_location' => BASE_URL . '/' . rawurlencode($blogSlugRedirectTemporarySlug) . '/' . rawurlencode($blogSlugRedirectArticleSlug),
            ],
            [
                'label' => 'new_blog_article',
                'url' => $baseUrl . BASE_URL . '/' . rawurlencode($blogSlugRedirectTemporarySlug) . '/' . rawurlencode($blogSlugRedirectArticleSlug),
                'expected_code' => 200,
            ],
            [
                'label' => 'old_blog_page',
                'url' => $baseUrl . BASE_URL . '/' . rawurlencode($blogSlugRedirectOriginalSlug) . '/stranka/' . rawurlencode($blogSlugRedirectPageSlug),
                'expected_code' => 301,
                'expected_location' => BASE_URL . '/' . rawurlencode($blogSlugRedirectTemporarySlug) . '/stranka/' . rawurlencode($blogSlugRedirectPageSlug),
            ],
            [
                'label' => 'new_blog_page',
                'url' => $baseUrl . BASE_URL . '/' . rawurlencode($blogSlugRedirectTemporarySlug) . '/stranka/' . rawurlencode($blogSlugRedirectPageSlug),
                'expected_code' => 200,
            ],
            [
                'label' => 'old_blog_feed',
                'url' => $baseUrl . BASE_URL . '/feed.php?blog=' . rawurlencode($blogSlugRedirectOriginalSlug),
                'expected_code' => 301,
                'expected_location' => BASE_URL . '/feed.php?blog=' . rawurlencode($blogSlugRedirectTemporarySlug),
            ],
            [
                'label' => 'new_blog_feed',
                'url' => $baseUrl . BASE_URL . '/feed.php?blog=' . rawurlencode($blogSlugRedirectTemporarySlug),
                'expected_code' => 200,
            ],
        ];

        foreach ($blogSlugChecks as $blogSlugCheck) {
            $blogSlugResponse = fetchUrl((string)$blogSlugCheck['url'], '', 0);
            if (httpIntegrationStatusCode($blogSlugResponse) !== (int)$blogSlugCheck['expected_code']) {
                $blogSlugRedirectIssues[] = $blogSlugCheck['label'] . ' returned unexpected status ' . ($blogSlugResponse['status'] ?? 'unknown');
                continue;
            }
            if (isset($blogSlugCheck['expected_location'])
                && !responseHasLocationHeader($blogSlugResponse['headers'], (string)$blogSlugCheck['expected_location'], $baseUrl)) {
                $blogSlugRedirectIssues[] = $blogSlugCheck['label'] . ' is missing expected Location header';
            }
        }
    } finally {
        $pdo->prepare("UPDATE cms_blogs SET slug = ? WHERE id = ?")->execute([$blogSlugRedirectOriginalSlug, $blogSlugRedirectMainId]);
        $pdo->prepare("DELETE FROM cms_blog_slug_redirects WHERE blog_id = ?")->execute([$blogSlugRedirectMainId]);
        if ($blogSlugRedirectSnapshot !== []) {
            $restoreBlogSlugRedirectStmt = $pdo->prepare("INSERT INTO cms_blog_slug_redirects (blog_id, old_slug) VALUES (?, ?)");
            foreach ($blogSlugRedirectSnapshot as $snapshotOldSlug) {
                $restoreBlogSlugRedirectStmt->execute([$blogSlugRedirectMainId, (string)$snapshotOldSlug]);
            }
        }
        clearBlogCache();
    }

    httpIntegrationPrintResult('blog_slug_redirect_http', $blogSlugRedirectIssues, $failures);

    $mediaIssues = [];
    $mediaAdminUrl = $baseUrl . BASE_URL . '/admin/media.php';
    $mediaReturnPath = BASE_URL . '/admin/media.php';

    $validMediaOriginalName = 'http-media-upload-' . bin2hex(random_bytes(4)) . '.png';
    $validMediaPath = httpIntegrationCreatePngFixtureFile('kora-media-png-', $createdTempFiles);
    $validMediaUploadResponse = postMultipartUrl(
        $mediaAdminUrl,
        [
            'csrf_token' => $adminSession['csrf'],
            'action' => 'upload',
            'upload_visibility' => 'public',
            'return_to' => $mediaReturnPath,
        ],
        [
            'media_files[0]' => [
                'path' => $validMediaPath,
                'filename' => $validMediaOriginalName,
                'type' => 'image/png',
            ],
        ],
        $adminSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($validMediaUploadResponse) !== 302) {
        $mediaIssues[] = 'validní upload média nevrátil 302 redirect';
    }
    if (!responseHasLocationHeader($validMediaUploadResponse['headers'], $mediaReturnPath, $baseUrl)) {
        $mediaIssues[] = 'validní upload média nemíří zpět na přehled médií';
    }

    $uploadedMedia = httpIntegrationFetchMediaByOriginalName($pdo, $validMediaOriginalName);
    if ($uploadedMedia === null) {
        $mediaIssues[] = 'validní upload média nevytvořil záznam v cms_media';
    } else {
        $createdMediaIds[] = (int)$uploadedMedia['id'];
        if (normalizeMediaVisibility((string)($uploadedMedia['visibility'] ?? 'public')) !== 'public') {
            $mediaIssues[] = 'validní upload média neuložil visibility=public';
        }
        if (!str_starts_with(mediaFileUrl($uploadedMedia), BASE_URL . '/uploads/media/')) {
            $mediaIssues[] = 'validní upload média nepoužívá canonical public URL';
        }
    }

    $validUploadPage = fetchUrl($mediaAdminUrl, $adminSession['cookie'], 0);
    if (!str_contains($validUploadPage['body'], 'Nahráno 1 souborů.')) {
        $mediaIssues[] = 'validní upload média nezobrazil success flash zprávu';
    }

    $invalidSvgOriginalName = 'http-media-svg-' . bin2hex(random_bytes(4)) . '.svg';
    $invalidSvgPath = httpIntegrationCreateTempFile(
        'kora-media-svg-',
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>',
        $createdTempFiles
    );
    $invalidSvgResponse = postMultipartUrl(
        $mediaAdminUrl,
        [
            'csrf_token' => $adminSession['csrf'],
            'action' => 'upload',
            'upload_visibility' => 'public',
            'return_to' => $mediaReturnPath,
        ],
        [
            'media_files[0]' => [
                'path' => $invalidSvgPath,
                'filename' => $invalidSvgOriginalName,
                'type' => 'image/svg+xml',
            ],
        ],
        $adminSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($invalidSvgResponse) !== 302) {
        $mediaIssues[] = 'SVG upload média nevrátil 302 redirect';
    }
    $invalidSvgPage = fetchUrl($mediaAdminUrl, $adminSession['cookie'], 0);
    if (!str_contains($invalidSvgPage['body'], 'SVG soubory už knihovna médií nepřijímá')) {
        $mediaIssues[] = 'SVG upload média nezobrazil validační chybu';
    }
    if (httpIntegrationFetchMediaByOriginalName($pdo, $invalidSvgOriginalName) !== null) {
        $mediaIssues[] = 'SVG upload média přesto vytvořil záznam v cms_media';
    }

    $oversizedMediaOriginalName = 'http-media-oversized-' . bin2hex(random_bytes(4)) . '.bin';
    $oversizedMediaPath = httpIntegrationCreateTempFile(
        'kora-media-big-',
        str_repeat('A', mediaMaxFileSizeBytes() + 2048),
        $createdTempFiles
    );
    $oversizedMediaResponse = postMultipartUrl(
        $mediaAdminUrl,
        [
            'csrf_token' => $adminSession['csrf'],
            'action' => 'upload',
            'upload_visibility' => 'public',
            'return_to' => $mediaReturnPath,
        ],
        [
            'media_files[0]' => [
                'path' => $oversizedMediaPath,
                'filename' => $oversizedMediaOriginalName,
                'type' => 'application/octet-stream',
            ],
        ],
        $adminSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($oversizedMediaResponse) !== 302) {
        $mediaIssues[] = 'oversized upload média nevrátil 302 redirect';
    }
    $oversizedMediaPage = fetchUrl($mediaAdminUrl, $adminSession['cookie'], 0);
    if (!str_contains($oversizedMediaPage['body'], 'Soubor překračuje maximální velikost 10 MB.')) {
        $mediaIssues[] = 'oversized upload média nezobrazil validační chybu';
    }
    if (httpIntegrationFetchMediaByOriginalName($pdo, $oversizedMediaOriginalName) !== null) {
        $mediaIssues[] = 'oversized upload média přesto vytvořil záznam v cms_media';
    }

    if ($uploadedMedia !== null) {
        $uploadedMediaId = (int)$uploadedMedia['id'];
        $uploadedEditPath = BASE_URL . '/admin/media.php?edit=' . $uploadedMediaId;

        $updateMetaResponse = postUrl(
            $mediaAdminUrl,
            [
                'csrf_token' => $adminSession['csrf'],
                'action' => 'update_meta',
                'media_id' => (string)$uploadedMediaId,
                'alt_text' => 'HTTP alt text média',
                'caption' => 'HTTP titulek média',
                'credit' => 'HTTP kredit média',
                'visibility' => 'public',
                'return_to' => $uploadedEditPath,
            ],
            $adminSession['cookie'],
            0
        );
        if (httpIntegrationStatusCode($updateMetaResponse) !== 302) {
            $mediaIssues[] = 'update_meta média nevrátil 302 redirect';
        }
        if (!responseHasLocationHeader($updateMetaResponse['headers'], $uploadedEditPath, $baseUrl)) {
            $mediaIssues[] = 'update_meta média nemíří zpět na detail média';
        }

        $uploadedMediaAfterMeta = httpIntegrationFetchMediaById($pdo, $uploadedMediaId);
        if ($uploadedMediaAfterMeta === null) {
            $mediaIssues[] = 'update_meta média ztratil záznam v cms_media';
        } else {
            if ((string)($uploadedMediaAfterMeta['alt_text'] ?? '') !== 'HTTP alt text média') {
                $mediaIssues[] = 'update_meta média neuložil alt_text';
            }
            if ((string)($uploadedMediaAfterMeta['caption'] ?? '') !== 'HTTP titulek média') {
                $mediaIssues[] = 'update_meta média neuložil caption';
            }
            if ((string)($uploadedMediaAfterMeta['credit'] ?? '') !== 'HTTP kredit média') {
                $mediaIssues[] = 'update_meta média neuložil credit';
            }
        }

        $updateMetaPage = fetchUrl($baseUrl . $uploadedEditPath, $adminSession['cookie'], 0);
        if (!str_contains($updateMetaPage['body'], 'Metadata média byla uložena.')) {
            $mediaIssues[] = 'update_meta média nezobrazil success flash zprávu';
        }
        $updateMetaPageSecondRead = fetchUrl($baseUrl . $uploadedEditPath, $adminSession['cookie'], 0);
        if (str_contains($updateMetaPageSecondRead['body'], 'Metadata média byla uložena.')) {
            $mediaIssues[] = 'update_meta média nezmizel success flash po druhém načtení';
        }

        $replacementMediaOriginalName = 'http-media-replacement-' . bin2hex(random_bytes(4)) . '.png';
        $replacementMediaPath = httpIntegrationCreatePngFixtureFile('kora-media-replace-', $createdTempFiles, 32, 32);
        $replaceResponse = postMultipartUrl(
            $mediaAdminUrl,
            [
                'csrf_token' => $adminSession['csrf'],
                'action' => 'replace',
                'media_id' => (string)$uploadedMediaId,
                'return_to' => $uploadedEditPath,
            ],
            [
                'replacement_file' => [
                    'path' => $replacementMediaPath,
                    'filename' => $replacementMediaOriginalName,
                    'type' => 'image/png',
                ],
            ],
            $adminSession['cookie'],
            0
        );
        if (httpIntegrationStatusCode($replaceResponse) !== 302) {
            $mediaIssues[] = 'replace média stejné MIME rodiny nevrátil 302 redirect';
        }
        if (!responseHasLocationHeader($replaceResponse['headers'], $uploadedEditPath, $baseUrl)) {
            $mediaIssues[] = 'replace média stejné MIME rodiny nemíří zpět na detail média';
        }

        $uploadedMediaAfterReplace = httpIntegrationFetchMediaById($pdo, $uploadedMediaId);
        if ($uploadedMediaAfterReplace === null) {
            $mediaIssues[] = 'replace média stejné MIME rodiny ztratil záznam v cms_media';
        } else {
            if ((string)($uploadedMediaAfterReplace['original_name'] ?? '') !== $replacementMediaOriginalName) {
                $mediaIssues[] = 'replace média stejné MIME rodiny neaktualizoval original_name';
            }
            if ((string)($uploadedMediaAfterReplace['filename'] ?? '') !== (string)($uploadedMedia['filename'] ?? '')) {
                $mediaIssues[] = 'replace média stejné MIME rodiny změnil canonical filename veřejného souboru';
            }
            if ((string)($uploadedMediaAfterReplace['mime_type'] ?? '') !== 'image/png') {
                $mediaIssues[] = 'replace média stejné MIME rodiny změnil mime_type';
            }
            if (!is_file(mediaOriginalPath($uploadedMediaAfterReplace))) {
                $mediaIssues[] = 'replace média stejné MIME rodiny nezachoval fyzický soubor';
            }
        }

        $replacePage = fetchUrl($baseUrl . $uploadedEditPath, $adminSession['cookie'], 0);
        if (!str_contains($replacePage['body'], 'Soubor byl nahrazen bez změny jeho ID.')) {
            $mediaIssues[] = 'replace média stejné MIME rodiny nezobrazil success flash zprávu';
        }

        $invalidReplacePath = httpIntegrationCreateTempFile('kora-media-text-', 'plain text replacement', $createdTempFiles);
        $beforeInvalidReplace = httpIntegrationFetchMediaById($pdo, $uploadedMediaId);
        $invalidReplaceResponse = postMultipartUrl(
            $mediaAdminUrl,
            [
                'csrf_token' => $adminSession['csrf'],
                'action' => 'replace',
                'media_id' => (string)$uploadedMediaId,
                'return_to' => $uploadedEditPath,
            ],
            [
                'replacement_file' => [
                    'path' => $invalidReplacePath,
                    'filename' => 'http-media-replacement-' . bin2hex(random_bytes(4)) . '.txt',
                    'type' => 'text/plain',
                ],
            ],
            $adminSession['cookie'],
            0
        );
        if (httpIntegrationStatusCode($invalidReplaceResponse) !== 302) {
            $mediaIssues[] = 'replace média jiné MIME rodiny nevrátil 302 redirect';
        }
        $invalidReplacePage = fetchUrl($baseUrl . $uploadedEditPath, $adminSession['cookie'], 0);
        if (!str_contains($invalidReplacePage['body'], 'Náhradní soubor musí zůstat ve stejné rodině typu jako původní médium.')) {
            $mediaIssues[] = 'replace média jiné MIME rodiny nezobrazil validační chybu';
        }
        $afterInvalidReplace = httpIntegrationFetchMediaById($pdo, $uploadedMediaId);
        if ($beforeInvalidReplace !== null && $afterInvalidReplace !== null) {
            foreach (['filename', 'mime_type', 'file_size'] as $replaceInvariantField) {
                if ((string)$afterInvalidReplace[$replaceInvariantField] !== (string)$beforeInvalidReplace[$replaceInvariantField]) {
                    $mediaIssues[] = 'replace média jiné MIME rodiny změnil pole ' . $replaceInvariantField;
                }
            }
        }
    }

    $uploadMediaFixture = static function (string $label) use (
        $pdo,
        $mediaAdminUrl,
        $mediaReturnPath,
        $adminSession,
        &$createdTempFiles,
        &$createdMediaIds,
        &$mediaIssues,
        $baseUrl
    ): ?array {
        $originalName = 'http-media-' . $label . '-' . bin2hex(random_bytes(4)) . '.png';
        $fixturePath = httpIntegrationCreatePngFixtureFile('kora-media-fixture-', $createdTempFiles);
        $response = postMultipartUrl(
            $mediaAdminUrl,
            [
                'csrf_token' => $adminSession['csrf'],
                'action' => 'upload',
                'upload_visibility' => 'public',
                'return_to' => $mediaReturnPath,
            ],
            [
                'media_files[0]' => [
                    'path' => $fixturePath,
                    'filename' => $originalName,
                    'type' => 'image/png',
                ],
            ],
            $adminSession['cookie'],
            0
        );
        if (httpIntegrationStatusCode($response) !== 302) {
            $mediaIssues[] = 'fixture upload média (' . $label . ') nevrátil 302 redirect';
            return null;
        }
        if (!responseHasLocationHeader($response['headers'], $mediaReturnPath, $baseUrl)) {
            $mediaIssues[] = 'fixture upload média (' . $label . ') nemíří zpět na přehled médií';
            return null;
        }

        $media = httpIntegrationFetchMediaByOriginalName($pdo, $originalName);
        if ($media === null) {
            $mediaIssues[] = 'fixture upload média (' . $label . ') nevytvořil záznam v cms_media';
            return null;
        }

        $createdMediaIds[] = (int)$media['id'];
        fetchUrl($mediaAdminUrl, $adminSession['cookie'], 0);

        return $media;
    };

    $usedMedia = $uploadMediaFixture('used');
    $bulkUnusedMedia = $uploadMediaFixture('unused');

    if ($usedMedia === null || $bulkUnusedMedia === null) {
        $mediaIssues[] = 'nepodařilo se připravit HTTP fixture pro scénáře used/unused média';
    } else {
        $usedPageSlug = 'http-media-page-' . bin2hex(random_bytes(4));
        $pdo->prepare(
            "INSERT INTO cms_pages (title, slug, content, is_published, show_in_nav, nav_order, status)
             VALUES (?, ?, ?, 1, 0, 0, 'published')"
        )->execute([
            'HTTP media usage page',
            $usedPageSlug,
            '<p><img src="' . h(mediaFileUrl($usedMedia)) . '" alt=""></p>',
        ]);
        $createdPageIds[] = (int)$pdo->lastInsertId();

        $usedMediaEditPath = BASE_URL . '/admin/media.php?edit=' . (int)$usedMedia['id'];
        $usedToPrivateResponse = postUrl(
            $mediaAdminUrl,
            [
                'csrf_token' => $adminSession['csrf'],
                'action' => 'update_meta',
                'media_id' => (string)$usedMedia['id'],
                'alt_text' => '',
                'caption' => '',
                'credit' => '',
                'visibility' => 'private',
                'return_to' => $usedMediaEditPath,
            ],
            $adminSession['cookie'],
            0
        );
        if (httpIntegrationStatusCode($usedToPrivateResponse) !== 302) {
            $mediaIssues[] = 'used -> private nevrátil 302 redirect';
        }
        $usedToPrivatePage = fetchUrl($baseUrl . $usedMediaEditPath, $adminSession['cookie'], 0);
        if (!str_contains($usedToPrivatePage['body'], 'Použité médium nelze přepnout do soukromého režimu, dokud je vložené v obsahu.')) {
            $mediaIssues[] = 'used -> private nezobrazil validační chybu';
        }
        $usedMediaAfterPrivateAttempt = httpIntegrationFetchMediaById($pdo, (int)$usedMedia['id']);
        if ($usedMediaAfterPrivateAttempt === null || normalizeMediaVisibility((string)($usedMediaAfterPrivateAttempt['visibility'] ?? 'public')) !== 'public') {
            $mediaIssues[] = 'used -> private změnil visibility použitého média';
        }

        $usedDeleteResponse = postUrl(
            $mediaAdminUrl,
            [
                'csrf_token' => $adminSession['csrf'],
                'action' => 'delete',
                'media_id' => (string)$usedMedia['id'],
                'return_to' => $mediaReturnPath,
            ],
            $adminSession['cookie'],
            0
        );
        if (httpIntegrationStatusCode($usedDeleteResponse) !== 302) {
            $mediaIssues[] = 'delete použitého média nevrátil 302 redirect';
        }
        $usedDeletePage = fetchUrl($mediaAdminUrl, $adminSession['cookie'], 0);
        if (!str_contains($usedDeletePage['body'], 'Použité médium nelze smazat, dokud je vložené v obsahu.')) {
            $mediaIssues[] = 'delete použitého média nezobrazil validační chybu';
        }
        if (httpIntegrationFetchMediaById($pdo, (int)$usedMedia['id']) === null) {
            $mediaIssues[] = 'delete použitého média přesto smazal záznam';
        }

        $bulkPrivateResponse = postUrl(
            $mediaAdminUrl,
            [
                'csrf_token' => $adminSession['csrf'],
                'action' => 'bulk',
                'bulk_action' => 'make_private',
                'media_ids' => [(string)$usedMedia['id'], (string)$bulkUnusedMedia['id']],
                'return_to' => $mediaReturnPath,
            ],
            $adminSession['cookie'],
            0
        );
        if (httpIntegrationStatusCode($bulkPrivateResponse) !== 302) {
            $mediaIssues[] = 'bulk make_private nevrátil 302 redirect';
        }
        $bulkPrivatePage = fetchUrl($mediaAdminUrl, $adminSession['cookie'], 0);
        if (!str_contains($bulkPrivatePage['body'], 'Soukromě označeno 1 médií.')) {
            $mediaIssues[] = 'bulk make_private nezobrazil success flash zprávu';
        }
        if (!str_contains($bulkPrivatePage['body'], 'U 1 médií byla akce zablokována kvůli použití nebo chybě přesunu.')) {
            $mediaIssues[] = 'bulk make_private nezobrazil blokaci použitého média';
        }
        $usedMediaAfterBulkPrivate = httpIntegrationFetchMediaById($pdo, (int)$usedMedia['id']);
        $bulkUnusedMediaAfterPrivate = httpIntegrationFetchMediaById($pdo, (int)$bulkUnusedMedia['id']);
        if ($usedMediaAfterBulkPrivate === null || normalizeMediaVisibility((string)($usedMediaAfterBulkPrivate['visibility'] ?? 'public')) !== 'public') {
            $mediaIssues[] = 'bulk make_private změnil visibility použitého média';
        }
        if ($bulkUnusedMediaAfterPrivate === null || normalizeMediaVisibility((string)($bulkUnusedMediaAfterPrivate['visibility'] ?? 'public')) !== 'private') {
            $mediaIssues[] = 'bulk make_private nepřepnul nepoužité médium do soukromého režimu';
        } elseif (!str_starts_with(mediaFileUrl($bulkUnusedMediaAfterPrivate), BASE_URL . '/media/file.php?id=' . (int)$bulkUnusedMedia['id'])) {
            $mediaIssues[] = 'bulk make_private nepřepnul canonical URL nepoužitého média na chráněný endpoint';
        }

        $bulkDeleteResponse = postUrl(
            $mediaAdminUrl,
            [
                'csrf_token' => $adminSession['csrf'],
                'action' => 'bulk',
                'bulk_action' => 'delete_unused',
                'media_ids' => [(string)$usedMedia['id'], (string)$bulkUnusedMedia['id']],
                'return_to' => $mediaReturnPath,
            ],
            $adminSession['cookie'],
            0
        );
        if (httpIntegrationStatusCode($bulkDeleteResponse) !== 302) {
            $mediaIssues[] = 'bulk delete_unused nevrátil 302 redirect';
        }
        $bulkDeletePage = fetchUrl($mediaAdminUrl, $adminSession['cookie'], 0);
        if (!str_contains($bulkDeletePage['body'], 'Smazáno 1 nepoužitých médií.')) {
            $mediaIssues[] = 'bulk delete_unused nezobrazil success flash zprávu';
        }
        if (!str_contains($bulkDeletePage['body'], 'U 1 médií byla akce zablokována kvůli použití nebo chybě přesunu.')) {
            $mediaIssues[] = 'bulk delete_unused nezobrazil blokaci použitého média';
        }
        if (httpIntegrationFetchMediaById($pdo, (int)$bulkUnusedMedia['id']) !== null) {
            $mediaIssues[] = 'bulk delete_unused nesmazal nepoužité médium';
        }
        if (httpIntegrationFetchMediaById($pdo, (int)$usedMedia['id']) === null) {
            $mediaIssues[] = 'bulk delete_unused smazal použité médium';
        }
    }

    httpIntegrationPrintResult('media_admin_http', $mediaIssues, $failures);

    $publicFormIssues = [];
    $publicFormSlug = uniqueFormSlug($pdo, 'http-integration-form-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_forms (
            title, slug, description, success_message, submit_label, notification_email, notification_subject,
            redirect_url, success_behavior, success_primary_label, success_primary_url, success_secondary_label,
            success_secondary_url, webhook_enabled, webhook_url, webhook_secret, webhook_events,
            use_honeypot, submitter_confirmation_enabled, submitter_email_field,
            submitter_confirmation_subject, submitter_confirmation_message, show_in_nav, is_active, created_at, updated_at
        )
        VALUES (?, ?, ?, ?, ?, '', '', '', '', '', '', '', '', 0, '', '', '', 1, 0, '', '', '', 0, 1, NOW(), NOW())"
    )->execute([
        'HTTP integration formulář',
        $publicFormSlug,
        'Formulář pro integrační ověření veřejného odeslání s přílohou.',
        'Formulář byl úspěšně odeslán přes HTTP integration.',
        'Odeslat formulář',
    ]);
    $publicFormId = (int)$pdo->lastInsertId();
    $createdFormIds[] = $publicFormId;

    $publicFormFields = [
        [
            'field_type' => 'text',
            'label' => 'Jméno',
            'name' => 'full_name',
            'placeholder' => 'Jan Tester',
            'default_value' => '',
            'help_text' => 'Uveďte své jméno.',
            'options' => '',
            'accept_types' => '',
            'max_file_size_mb' => 1,
            'allow_multiple' => 0,
            'layout_width' => 'half',
            'start_new_row' => 0,
            'show_if_field' => '',
            'show_if_operator' => '',
            'show_if_value' => '',
            'is_required' => 1,
            'sort_order' => 10,
        ],
        [
            'field_type' => 'email',
            'label' => 'E-mail',
            'name' => 'contact_email',
            'placeholder' => 'tester@example.test',
            'default_value' => '',
            'help_text' => 'Na tuto adresu vám můžeme odpovědět.',
            'options' => '',
            'accept_types' => '',
            'max_file_size_mb' => 1,
            'allow_multiple' => 0,
            'layout_width' => 'half',
            'start_new_row' => 0,
            'show_if_field' => '',
            'show_if_operator' => '',
            'show_if_value' => '',
            'is_required' => 1,
            'sort_order' => 20,
        ],
        [
            'field_type' => 'file',
            'label' => 'Příloha',
            'name' => 'attachment',
            'placeholder' => '',
            'default_value' => '',
            'help_text' => 'Nahrajte PNG přílohu do 1 MB.',
            'options' => '',
            'accept_types' => '.png,image/png',
            'max_file_size_mb' => 1,
            'allow_multiple' => 0,
            'layout_width' => 'full',
            'start_new_row' => 1,
            'show_if_field' => '',
            'show_if_operator' => '',
            'show_if_value' => '',
            'is_required' => 1,
            'sort_order' => 30,
        ],
    ];
    $publicFormFieldStmt = $pdo->prepare(
        "INSERT INTO cms_form_fields (
            form_id, field_type, label, name, placeholder, default_value, help_text, options,
            accept_types, max_file_size_mb, allow_multiple, layout_width, start_new_row,
            show_if_field, show_if_operator, show_if_value, is_required, sort_order
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($publicFormFields as $publicFormField) {
        $publicFormFieldStmt->execute([
            $publicFormId,
            $publicFormField['field_type'],
            $publicFormField['label'],
            $publicFormField['name'],
            $publicFormField['placeholder'],
            $publicFormField['default_value'],
            $publicFormField['help_text'],
            $publicFormField['options'],
            $publicFormField['accept_types'],
            $publicFormField['max_file_size_mb'],
            $publicFormField['allow_multiple'],
            $publicFormField['layout_width'],
            $publicFormField['start_new_row'],
            $publicFormField['show_if_field'],
            $publicFormField['show_if_operator'],
            $publicFormField['show_if_value'],
            $publicFormField['is_required'],
            $publicFormField['sort_order'],
        ]);
    }

    $publicFormUrl = $baseUrl . formPublicPath(['id' => $publicFormId, 'slug' => $publicFormSlug]);
    $publicFormSession = koraPrimeTestSession([], 'kora-http-public-form-' . bin2hex(random_bytes(4)));

    $fetchPublicFormState = static function () use ($publicFormUrl, $publicFormSession, &$publicFormIssues): array {
        $response = fetchUrl($publicFormUrl, $publicFormSession['cookie'], 0);
        if (httpIntegrationStatusCode($response) !== 200) {
            $publicFormIssues[] = 'veřejný formulář nevrátil 200 při načtení formuláře';
        }

        $csrfToken = extractHiddenInputValue($response['body'], 'csrf_token');
        if ($csrfToken === '') {
            $publicFormIssues[] = 'veřejný formulář nevykreslil csrf_token';
        }

        $captchaAnswer = httpIntegrationExtractCaptchaAnswer($response['body']);
        if ($captchaAnswer === '') {
            $publicFormIssues[] = 'veřejný formulář nevykreslil parsovatelnou CAPTCHA otázku';
        }

        return [
            'response' => $response,
            'csrf_token' => $csrfToken,
            'captcha_answer' => $captchaAnswer,
        ];
    };

    $basePublicFields = [
        'full_name' => 'HTTP Tester',
        'contact_email' => 'http.tester@example.test',
    ];

    $validPublicFilePath = httpIntegrationCreatePngFixtureFile('kora-form-valid-', $createdTempFiles, 36, 36);
    $invalidPublicFilePath = httpIntegrationCreateTempFile('kora-form-text-', 'plain text attachment', $createdTempFiles);

    $beforeInvalidCaptchaUploads = httpIntegrationListStoredFormUploads();
    $invalidCaptchaState = $fetchPublicFormState();
    $invalidCaptchaResponse = postMultipartUrl(
        $publicFormUrl,
        [
            'csrf_token' => (string)$invalidCaptchaState['csrf_token'],
            'captcha' => '0',
            'full_name' => $basePublicFields['full_name'],
            'contact_email' => $basePublicFields['contact_email'],
        ],
        [
            'attachment' => [
                'path' => $validPublicFilePath,
                'filename' => 'http-form-valid-' . bin2hex(random_bytes(4)) . '.png',
                'type' => 'image/png',
            ],
        ],
        $publicFormSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($invalidCaptchaResponse) !== 200) {
        $publicFormIssues[] = 'submit veřejného formuláře s chybnou CAPTCHou nevrátil 200';
    }
    if (!str_contains($invalidCaptchaResponse['body'], 'Chybná odpověď na ověřovací otázku.')) {
        $publicFormIssues[] = 'submit veřejného formuláře s chybnou CAPTCHou nezobrazil validační chybu';
    }
    if (!httpIntegrationFieldHasAriaInvalid($invalidCaptchaResponse['body'], 'captcha')) {
        $publicFormIssues[] = 'submit veřejného formuláře s chybnou CAPTCHou neoznačil pole captcha jako aria-invalid';
    }
    if (!str_contains($invalidCaptchaResponse['body'], 'id="captcha-error"')) {
        $publicFormIssues[] = 'submit veřejného formuláře s chybnou CAPTCHou nevykreslil lokální chybový text';
    }
    if (!str_contains($invalidCaptchaResponse['body'], 'value="' . h($basePublicFields['full_name']) . '"')) {
        $publicFormIssues[] = 'submit veřejného formuláře s chybnou CAPTCHou nezachoval jméno';
    }
    if (!str_contains($invalidCaptchaResponse['body'], 'value="' . h($basePublicFields['contact_email']) . '"')) {
        $publicFormIssues[] = 'submit veřejného formuláře s chybnou CAPTCHou nezachoval e-mail';
    }
    if (httpIntegrationFetchLatestFormSubmissionByFormId($pdo, $publicFormId) !== null) {
        $publicFormIssues[] = 'submit veřejného formuláře s chybnou CAPTCHou přesto vytvořil submission';
    }
    if (httpIntegrationListStoredFormUploads() !== $beforeInvalidCaptchaUploads) {
        $publicFormIssues[] = 'submit veřejného formuláře s chybnou CAPTCHou po sobě nechal uloženou přílohu';
    }

    $beforeInvalidTypeUploads = httpIntegrationListStoredFormUploads();
    $invalidTypeState = $fetchPublicFormState();
    $invalidTypeResponse = postMultipartUrl(
        $publicFormUrl,
        [
            'csrf_token' => (string)$invalidTypeState['csrf_token'],
            'captcha' => (string)$invalidTypeState['captcha_answer'],
            'full_name' => $basePublicFields['full_name'],
            'contact_email' => $basePublicFields['contact_email'],
        ],
        [
            'attachment' => [
                'path' => $invalidPublicFilePath,
                'filename' => 'http-form-invalid-' . bin2hex(random_bytes(4)) . '.txt',
                'type' => 'text/plain',
            ],
        ],
        $publicFormSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($invalidTypeResponse) !== 200) {
        $publicFormIssues[] = 'submit veřejného formuláře s nepovoleným typem přílohy nevrátil 200';
    }
    if (!str_contains($invalidTypeResponse['body'], 'Pole „Příloha“: Vybraný typ souboru není v tomto poli povolený.')) {
        $publicFormIssues[] = 'submit veřejného formuláře s nepovoleným typem přílohy nezobrazil validační chybu';
    }
    if (!httpIntegrationFieldHasAriaInvalid($invalidTypeResponse['body'], 'field-attachment')) {
        $publicFormIssues[] = 'submit veřejného formuláře s nepovoleným typem přílohy neoznačil pole attachment jako aria-invalid';
    }
    if (!str_contains($invalidTypeResponse['body'], 'id="field-attachment-error"')) {
        $publicFormIssues[] = 'submit veřejného formuláře s nepovoleným typem přílohy nevykreslil lokální chybový text';
    }
    if (httpIntegrationFetchLatestFormSubmissionByFormId($pdo, $publicFormId) !== null) {
        $publicFormIssues[] = 'submit veřejného formuláře s nepovoleným typem přílohy přesto vytvořil submission';
    }
    if (httpIntegrationListStoredFormUploads() !== $beforeInvalidTypeUploads) {
        $publicFormIssues[] = 'submit veřejného formuláře s nepovoleným typem přílohy po sobě nechal uloženou přílohu';
    }

    $validSubmitState = $fetchPublicFormState();
    $validSubmitResponse = postMultipartUrl(
        $publicFormUrl,
        [
            'csrf_token' => (string)$validSubmitState['csrf_token'],
            'captcha' => (string)$validSubmitState['captcha_answer'],
            'full_name' => $basePublicFields['full_name'],
            'contact_email' => $basePublicFields['contact_email'],
        ],
        [
            'attachment' => [
                'path' => $validPublicFilePath,
                'filename' => 'http-form-success-' . bin2hex(random_bytes(4)) . '.png',
                'type' => 'image/png',
            ],
        ],
        $publicFormSession['cookie'],
        0
    );
    if (httpIntegrationStatusCode($validSubmitResponse) !== 200) {
        $publicFormIssues[] = 'validní submit veřejného formuláře nevrátil 200';
    }
    if (!str_contains($validSubmitResponse['body'], 'Formulář byl úspěšně odeslán přes HTTP integration.')) {
        $publicFormIssues[] = 'validní submit veřejného formuláře nezobrazil success stav';
    }
    $validSubmission = httpIntegrationFetchLatestFormSubmissionByFormId($pdo, $publicFormId);
    if ($validSubmission === null) {
        $publicFormIssues[] = 'validní submit veřejného formuláře nevytvořil submission';
    } else {
        $createdFormSubmissionIds[] = (int)$validSubmission['id'];
        $submissionData = json_decode((string)($validSubmission['data'] ?? ''), true);
        if (!is_array($submissionData)) {
            $publicFormIssues[] = 'validní submit veřejného formuláře neuložil čitelná JSON data';
        } else {
            if ((string)($submissionData['full_name'] ?? '') !== $basePublicFields['full_name']) {
                $publicFormIssues[] = 'validní submit veřejného formuláře neuložil full_name';
            }
            if ((string)($submissionData['contact_email'] ?? '') !== $basePublicFields['contact_email']) {
                $publicFormIssues[] = 'validní submit veřejného formuláře neuložil contact_email';
            }

            $attachmentData = $submissionData['attachment'] ?? null;
            if (!is_array($attachmentData) || trim((string)($attachmentData['stored_name'] ?? '')) === '') {
                $publicFormIssues[] = 'validní submit veřejného formuláře neuložil metadata přílohy';
            } else {
                $storedAttachmentPath = formUploadFilePath((string)$attachmentData['stored_name']);
                if ($storedAttachmentPath === '' || !is_file($storedAttachmentPath)) {
                    $publicFormIssues[] = 'validní submit veřejného formuláře neuložil soubor přílohy na disk';
                }
            }
        }
        if (trim((string)($validSubmission['reference_code'] ?? '')) === '') {
            $publicFormIssues[] = 'validní submit veřejného formuláře nevygeneroval reference_code';
        }
    }

    httpIntegrationPrintResult('public_form_submit_http', $publicFormIssues, $failures);
} finally {
    if (isset($originalSettings) && is_array($originalSettings)) {
        httpIntegrationRestoreSettings($originalSettings);
    }

    foreach ($createdWidgetIds as $widgetIdToDelete) {
        $pdo->prepare("DELETE FROM cms_widgets WHERE id = ?")->execute([$widgetIdToDelete]);
    }

    foreach ($createdResourceIds as $resourceId) {
        $pdo->prepare("DELETE FROM cms_res_blocked WHERE resource_id = ?")->execute([$resourceId]);
        $pdo->prepare("DELETE FROM cms_res_slots WHERE resource_id = ?")->execute([$resourceId]);
        $pdo->prepare("DELETE FROM cms_res_hours WHERE resource_id = ?")->execute([$resourceId]);
        $pdo->prepare("DELETE FROM cms_res_bookings WHERE resource_id = ?")->execute([$resourceId]);
        $pdo->prepare("DELETE FROM cms_res_resources WHERE id = ?")->execute([$resourceId]);
    }

    foreach ($createdFormSubmissionIds as $submissionIdToDelete) {
        $submissionStmt = $pdo->prepare("SELECT data FROM cms_form_submissions WHERE id = ? LIMIT 1");
        $submissionStmt->execute([$submissionIdToDelete]);
        $submissionJson = $submissionStmt->fetchColumn();
        $submissionData = json_decode(is_string($submissionJson) ? $submissionJson : '', true);
        if (is_array($submissionData)) {
            formDeleteUploadedFilesFromSubmissionData($submissionData);
        }
        $pdo->prepare("DELETE FROM cms_form_submission_history WHERE submission_id = ?")->execute([$submissionIdToDelete]);
        $pdo->prepare("DELETE FROM cms_form_submissions WHERE id = ?")->execute([$submissionIdToDelete]);
    }
    foreach ($createdFormIds as $formIdToDelete) {
        $submissionRows = $pdo->prepare("SELECT id, data FROM cms_form_submissions WHERE form_id = ?");
        $submissionRows->execute([$formIdToDelete]);
        foreach ($submissionRows->fetchAll() ?: [] as $submissionRow) {
            $submissionData = json_decode((string)($submissionRow['data'] ?? ''), true);
            if (is_array($submissionData)) {
                formDeleteUploadedFilesFromSubmissionData($submissionData);
            }
            $pdo->prepare("DELETE FROM cms_form_submission_history WHERE submission_id = ?")->execute([(int)$submissionRow['id']]);
        }
        $pdo->prepare("DELETE FROM cms_form_submissions WHERE form_id = ?")->execute([$formIdToDelete]);
        $pdo->prepare("DELETE FROM cms_form_fields WHERE form_id = ?")->execute([$formIdToDelete]);
        $pdo->prepare("DELETE FROM cms_forms WHERE id = ?")->execute([$formIdToDelete]);
    }
    foreach ($createdPageIds as $pageIdToDelete) {
        $pdo->prepare("DELETE FROM cms_pages WHERE id = ?")->execute([$pageIdToDelete]);
    }
    foreach ($createdGalleryAlbumIds as $galleryAlbumIdToDelete) {
        $pdo->prepare("DELETE FROM cms_gallery_albums WHERE id = ?")->execute([$galleryAlbumIdToDelete]);
    }
    foreach ($createdArticles as $articleIdToDelete) {
        $pdo->prepare("DELETE FROM cms_article_tags WHERE article_id = ?")->execute([$articleIdToDelete]);
        $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'article' AND entity_id = ?")->execute([$articleIdToDelete]);
        $pdo->prepare("DELETE FROM cms_articles WHERE id = ?")->execute([$articleIdToDelete]);
    }
    foreach ($createdMediaIds as $mediaIdToDelete) {
        $mediaToDelete = httpIntegrationFetchMediaById($pdo, $mediaIdToDelete);
        if ($mediaToDelete !== null) {
            mediaDeletePhysicalFiles($mediaToDelete);
            $pdo->prepare("DELETE FROM cms_media WHERE id = ?")->execute([$mediaIdToDelete]);
        }
    }
    foreach ($createdBoardIds as $boardIdToDelete) {
        $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'board' AND entity_id = ?")->execute([$boardIdToDelete]);
        $pdo->prepare("DELETE FROM cms_board WHERE id = ?")->execute([$boardIdToDelete]);
    }
    foreach ($createdTags as $tagIdToDelete) {
        $pdo->prepare("DELETE FROM cms_tags WHERE id = ?")->execute([$tagIdToDelete]);
    }
    foreach ($createdCategories as $categoryIdToDelete) {
        $pdo->prepare("DELETE FROM cms_categories WHERE id = ?")->execute([$categoryIdToDelete]);
    }
    foreach ($createdBlogs as $blogIdToDelete) {
        $pdo->prepare("DELETE FROM cms_blog_members WHERE blog_id = ?")->execute([$blogIdToDelete]);
        $pdo->prepare("DELETE FROM cms_blogs WHERE id = ?")->execute([$blogIdToDelete]);
    }
    foreach ($createdUsers as $userIdToDelete) {
        $pdo->prepare("DELETE FROM cms_users WHERE id = ?")->execute([$userIdToDelete]);
    }
    foreach ($createdTempFiles as $tempPath) {
        if (is_string($tempPath) && $tempPath !== '' && is_file($tempPath)) {
            @unlink($tempPath);
        }
    }
}

exit($failures === 0 ? 0 : 1);
