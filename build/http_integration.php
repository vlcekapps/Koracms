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
$createdResourceIds = [];
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

try {
    $originalSettings = [
        'site_name' => httpIntegrationSettingValue($pdo, 'site_name'),
        'site_description' => httpIntegrationSettingValue($pdo, 'site_description'),
        'board_public_label' => httpIntegrationSettingValue($pdo, 'board_public_label'),
        'module_blog' => getSetting('module_blog', '0'),
        'module_reservations' => getSetting('module_reservations', '0'),
    ];

    saveSetting('module_blog', '1');
    saveSetting('module_reservations', '1');
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

    $sourceTagOneSlug = 'http-source-tag-one-' . bin2hex(random_bytes(2));
    $sourceTagTwoSlug = 'http-source-tag-two-' . bin2hex(random_bytes(2));
    $targetTagSlug = 'http-target-tag-' . bin2hex(random_bytes(2));
    $foreignTagSlug = 'http-foreign-tag-' . bin2hex(random_bytes(2));
    foreach ([
        ['name' => 'HTTP Zdrojový štítek A', 'slug' => $sourceTagOneSlug, 'blog_id' => $sourceBlogId],
        ['name' => 'HTTP Zdrojový štítek B', 'slug' => $sourceTagTwoSlug, 'blog_id' => $sourceBlogId],
        ['name' => 'HTTP Cílový štítek', 'slug' => $targetTagSlug, 'blog_id' => $targetBlogId],
        ['name' => 'HTTP Cizí štítek', 'slug' => $foreignTagSlug, 'blog_id' => $foreignBlogId],
    ] as $tagRow) {
        $pdo->prepare("INSERT INTO cms_tags (name, slug, blog_id) VALUES (?, ?, ?)")->execute([$tagRow['name'], $tagRow['slug'], $tagRow['blog_id']]);
        $createdTags[] = (int)$pdo->lastInsertId();
    }
    [$sourceTagOneId, $sourceTagTwoId, $targetTagId, $foreignTagId] = $createdTags;

    foreach ([
        ['title' => 'HTTP Přesunovaný článek', 'slug' => 'http-transfer-article-' . bin2hex(random_bytes(4)), 'author_id' => $authorId],
        ['title' => 'HTTP Cizí mapování', 'slug' => 'http-transfer-article-foreign-' . bin2hex(random_bytes(4)), 'author_id' => $authorId],
        ['title' => 'HTTP Bez taxonomy práv', 'slug' => 'http-transfer-article-author-' . bin2hex(random_bytes(4)), 'author_id' => $authorNoTaxId],
    ] as $articleRow) {
        $pdo->prepare(
            "INSERT INTO cms_articles
                (title, slug, blog_id, perex, content, comments_enabled, category_id, author_id, status)
             VALUES (?, ?, ?, '', '<p>HTTP integration test.</p>', 1, ?, ?, 'published')"
        )->execute([
            $articleRow['title'],
            $articleRow['slug'],
            $sourceBlogId,
            $sourceCategoryId,
            $articleRow['author_id'],
        ]);
        $createdArticles[] = (int)$pdo->lastInsertId();
    }
    [$articleId, $articleForeignAttemptId, $articleNoTaxId] = $createdArticles;

    foreach ([
        [$articleId, $sourceTagOneId],
        [$articleId, $sourceTagTwoId],
        [$articleForeignAttemptId, $sourceTagOneId],
        [$articleNoTaxId, $sourceTagOneId],
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
} finally {
    if (isset($originalSettings) && is_array($originalSettings)) {
        foreach ($originalSettings as $settingKey => $settingValue) {
            saveSetting($settingKey, (string)$settingValue);
        }
        clearSettingsCache();
    }

    foreach ($createdResourceIds as $resourceId) {
        $pdo->prepare("DELETE FROM cms_res_blocked WHERE resource_id = ?")->execute([$resourceId]);
        $pdo->prepare("DELETE FROM cms_res_slots WHERE resource_id = ?")->execute([$resourceId]);
        $pdo->prepare("DELETE FROM cms_res_hours WHERE resource_id = ?")->execute([$resourceId]);
        $pdo->prepare("DELETE FROM cms_res_bookings WHERE resource_id = ?")->execute([$resourceId]);
        $pdo->prepare("DELETE FROM cms_res_resources WHERE id = ?")->execute([$resourceId]);
    }

    foreach ($createdArticles as $articleIdToDelete) {
        $pdo->prepare("DELETE FROM cms_article_tags WHERE article_id = ?")->execute([$articleIdToDelete]);
        $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'article' AND entity_id = ?")->execute([$articleIdToDelete]);
        $pdo->prepare("DELETE FROM cms_articles WHERE id = ?")->execute([$articleIdToDelete]);
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
