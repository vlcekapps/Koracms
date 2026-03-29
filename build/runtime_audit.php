<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ob_start();

require_once __DIR__ . '/../db.php';

$baseUrl = rtrim($argv[1] ?? 'http://localhost', '/');
$pdo     = db_connect();

$runtimeAuditOriginalModuleSettings = [
    'module_news' => getSetting('module_news', '0'),
    'module_newsletter' => getSetting('module_newsletter', '0'),
    'module_chat' => getSetting('module_chat', '0'),
    'module_contact' => getSetting('module_contact', '0'),
    'module_board' => getSetting('module_board', '0'),
    'module_downloads' => getSetting('module_downloads', '0'),
    'module_events' => getSetting('module_events', '0'),
    'module_faq' => getSetting('module_faq', '0'),
    'module_food' => getSetting('module_food', '0'),
    'module_gallery' => getSetting('module_gallery', '0'),
    'module_podcast' => getSetting('module_podcast', '0'),
    'module_places' => getSetting('module_places', '0'),
    'module_polls' => getSetting('module_polls', '0'),
    'module_reservations' => getSetting('module_reservations', '0'),
    'module_forms' => getSetting('module_forms', '0'),
];
foreach (array_keys($runtimeAuditOriginalModuleSettings) as $moduleSettingKey) {
    saveSetting($moduleSettingKey, '1');
}
$runtimeAuditOriginalBlogAuthorsIndexEnabled = getSetting('blog_authors_index_enabled', '0');
saveSetting('blog_authors_index_enabled', '1');
$runtimeAuditOriginalPublicRegistrationEnabled = getSetting('public_registration_enabled', '1');
saveSetting('public_registration_enabled', '1');
$runtimeAuditOriginalGitHubIssuesEnabled = getSetting('github_issues_enabled', '0');
$runtimeAuditOriginalGitHubIssuesRepository = getSetting('github_issues_repository', '');
saveSetting('github_issues_enabled', '1');
saveSetting('github_issues_repository', 'vlcekapps/Koracms');
clearSettingsCache();
$runtimeAuditHomepageUsesWidgets = renderZone('homepage') !== '';

$articleRow = $pdo->query(
    "SELECT a.id, a.slug, a.blog_id, b.slug AS blog_slug
     FROM cms_articles a
     LEFT JOIN cms_blogs b ON b.id = a.blog_id
     WHERE a.status = 'published'
     ORDER BY a.id
     LIMIT 1"
)->fetch() ?: null;
$articleId = $articleRow['id'] ?? false;
$articleCanonicalPath = $articleRow ? articlePublicPath($articleRow) : '';
$articleLegacyPath = $articleId !== false ? BASE_URL . '/blog/article.php?id=' . urlencode((string)$articleId) : '';
$articleCanonicalUrl = $articleCanonicalPath !== '' ? $baseUrl . $articleCanonicalPath : '';
$articleLegacyUrl = $articleLegacyPath !== '' ? $baseUrl . $articleLegacyPath : '';
$articleCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_articles WHERE status = 'published'"
)->fetchColumn();
$homeBlogCountSetting = max(0, (int)getSetting('home_blog_count', '5'));
$newsRow = $pdo->query(
    "SELECT id, title, slug FROM cms_news WHERE status = 'published' ORDER BY created_at DESC, id DESC LIMIT 1"
)->fetch() ?: null;
$newsId = $newsRow['id'] ?? false;
$newsCanonicalPath = $newsRow ? newsPublicPath($newsRow) : '';
$newsLegacyPath = $newsId !== false ? BASE_URL . '/news/article.php?id=' . urlencode((string)$newsId) : '';
$newsCanonicalUrl = $newsCanonicalPath !== '' ? $baseUrl . $newsCanonicalPath : '';
$newsLegacyUrl = $newsLegacyPath !== '' ? $baseUrl . $newsLegacyPath : '';
$newsCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_news WHERE status = 'published'"
)->fetchColumn();
$eventRow = $pdo->query(
    "SELECT id, title, slug FROM cms_events
     WHERE status = 'published' AND is_published = 1
     ORDER BY event_date DESC, id DESC
     LIMIT 1"
)->fetch() ?: null;
$eventId = $eventRow['id'] ?? false;
$eventCanonicalPath = $eventRow ? eventPublicPath($eventRow) : '';
$eventLegacyPath = $eventId !== false ? BASE_URL . '/events/event.php?id=' . urlencode((string)$eventId) : '';
$eventCanonicalUrl = $eventCanonicalPath !== '' ? $baseUrl . $eventCanonicalPath : '';
$eventLegacyUrl = $eventLegacyPath !== '' ? $baseUrl . $eventLegacyPath : '';
$boardRow = null;
$boardId = false;
$boardCanonicalPath = '';
$boardLegacyPath = '';
$boardCanonicalUrl = '';
$boardLegacyUrl = '';
$boardCount = 0;
$boardAttachmentId = false;
$downloadRow = null;
$downloadId = false;
$downloadCanonicalPath = '';
$downloadLegacyPath = '';
$downloadCanonicalUrl = '';
$downloadLegacyUrl = '';
$faqRow = null;
$faqId = false;
$faqCanonicalPath = '';
$faqLegacyPath = '';
$faqCanonicalUrl = '';
$faqLegacyUrl = '';
$placeRow = null;
$placeId = false;
$placeCanonicalPath = '';
$placeLegacyPath = '';
$placeCanonicalUrl = '';
$placeLegacyUrl = '';
$pollRow = null;
$pollId = false;
$pollCanonicalPath = '';
$pollLegacyPath = '';
$pollCanonicalUrl = '';
$pollLegacyUrl = '';
$activePollCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_polls
     WHERE status = 'active'
       AND (start_date IS NULL OR start_date <= NOW())
       AND (end_date IS NULL OR end_date > NOW())"
)->fetchColumn();
$pageRow = $pdo->query(
    "SELECT id, slug FROM cms_pages WHERE status = 'published' AND is_published = 1 ORDER BY id LIMIT 1"
)->fetch() ?: null;
$pageId = $pageRow['id'] ?? false;
$pageSlug = $pageRow['slug'] ?? null;
$publicUserRow = $pdo->query(
    "SELECT id, email, first_name, last_name FROM cms_users WHERE role = 'public' AND is_confirmed = 1 ORDER BY id LIMIT 1"
)->fetch();
$podcastShowRow = null;
$podcastShowSlug = '';
$podcastEpisodeRow = null;
$podcastEpisodeId = false;
$podcastEpisodeCanonicalPath = '';
$podcastEpisodeLegacyPath = '';
$podcastEpisodeCanonicalUrl = '';
$podcastEpisodeLegacyUrl = '';
$newsletterPendingSubscriberId = false;
$newsletterConfirmedSubscriberId = false;
$newsletterHistoryId = false;
$contactMessageId = false;
$chatMessageId = false;
$runtimeAuditFormId = 0;
$runtimeAuditFormSubmissionId = 0;
$runtimeAuditFormSubmissionReference = '';
$runtimeAuditFormPath = '';
$foodCardRow = $pdo->query(
    "SELECT id, type, title, slug, description, valid_from, valid_to, is_current, is_published,
            status, created_at, updated_at
     FROM cms_food_cards
     WHERE status = 'published' AND is_published = 1
     ORDER BY is_current DESC, id
     LIMIT 1"
)->fetch() ?: null;
$foodCardId = $foodCardRow['id'] ?? false;
if ($foodCardRow) {
    $foodCardRow = hydrateFoodCardPresentation($foodCardRow);
}
$foodCardCanonicalPath = $foodCardRow ? foodCardPublicPath($foodCardRow) : '';
$foodCardLegacyPath = $foodCardId !== false ? BASE_URL . '/food/card.php?id=' . urlencode((string)$foodCardId) : '';
$foodCardCanonicalUrl = $foodCardCanonicalPath !== '' ? $baseUrl . $foodCardCanonicalPath : '';
$foodCardLegacyUrl = $foodCardLegacyPath !== '' ? $baseUrl . $foodCardLegacyPath : '';
$galleryAlbumRow = $pdo->query(
    "SELECT id, name, slug, description, COALESCE(updated_at, created_at) AS updated_at
     FROM cms_gallery_albums
     WHERE parent_id IS NULL
     ORDER BY id
     LIMIT 1"
)->fetch() ?: null;
if (!$galleryAlbumRow) {
    $galleryAlbumRow = $pdo->query(
        "SELECT id, name, slug, description, COALESCE(updated_at, created_at) AS updated_at
         FROM cms_gallery_albums
         ORDER BY id
         LIMIT 1"
    )->fetch() ?: null;
}
$galleryAlbumId = $galleryAlbumRow['id'] ?? false;
if ($galleryAlbumRow) {
    $galleryAlbumRow = hydrateGalleryAlbumPresentation($galleryAlbumRow);
}
$galleryAlbumCanonicalPath = $galleryAlbumRow ? galleryAlbumPublicPath($galleryAlbumRow) : '';
$galleryAlbumLegacyPath = $galleryAlbumId !== false ? BASE_URL . '/gallery/album.php?id=' . urlencode((string)$galleryAlbumId) : '';
$galleryAlbumCanonicalUrl = $galleryAlbumCanonicalPath !== '' ? $baseUrl . $galleryAlbumCanonicalPath : '';
$galleryAlbumLegacyUrl = $galleryAlbumLegacyPath !== '' ? $baseUrl . $galleryAlbumLegacyPath : '';
$galleryAlbumPhotoRow = null;
$galleryAlbumPhotoCanonicalPath = '';
if ($galleryAlbumId !== false) {
    $galleryAlbumPhotoRow = $pdo->prepare(
        "SELECT id, album_id, filename, title, slug, sort_order, created_at
         FROM cms_gallery_photos
         WHERE album_id = ?
         ORDER BY id
         LIMIT 1"
    );
    $galleryAlbumPhotoRow->execute([(int)$galleryAlbumId]);
    $galleryAlbumPhotoRow = $galleryAlbumPhotoRow->fetch() ?: null;
    if ($galleryAlbumPhotoRow) {
        $galleryAlbumPhotoRow = hydrateGalleryPhotoPresentation($galleryAlbumPhotoRow);
        $galleryAlbumPhotoCanonicalPath = galleryPhotoPublicPath($galleryAlbumPhotoRow);
    }
}
$galleryPhotoRow = $pdo->query(
    "SELECT id, album_id, filename, title, slug, sort_order, created_at
     FROM cms_gallery_photos
     ORDER BY id
     LIMIT 1"
)->fetch() ?: null;
$galleryPhotoId = $galleryPhotoRow['id'] ?? false;
$galleryPhotoAlbumId = $galleryPhotoRow['album_id'] ?? false;
if ($galleryPhotoRow) {
    $galleryPhotoRow = hydrateGalleryPhotoPresentation($galleryPhotoRow);
}
$galleryPhotoCanonicalPath = $galleryPhotoRow ? galleryPhotoPublicPath($galleryPhotoRow) : '';
$galleryPhotoLegacyPath = $galleryPhotoId !== false ? BASE_URL . '/gallery/photo.php?id=' . urlencode((string)$galleryPhotoId) : '';
$galleryPhotoCanonicalUrl = $galleryPhotoCanonicalPath !== '' ? $baseUrl . $galleryPhotoCanonicalPath : '';
$galleryPhotoLegacyUrl = $galleryPhotoLegacyPath !== '' ? $baseUrl . $galleryPhotoLegacyPath : '';
$pollDetailId = false;
$resourceRow = $pdo->query(
    "SELECT id, slug, max_advance_days FROM cms_res_resources WHERE is_active = 1 ORDER BY id LIMIT 1"
)->fetch();
$resourceSlug = $resourceRow['slug'] ?? null;
$reservationsBookDate = null;
if ($resourceRow) {
    $resourceId = (int)$resourceRow['id'];
    $advanceDays = max(0, (int)$resourceRow['max_advance_days']);

    $hoursStmt = $pdo->prepare(
        "SELECT day_of_week, is_closed FROM cms_res_hours WHERE resource_id = ?"
    );
    $hoursStmt->execute([$resourceId]);
    $hoursByDay = [];
    foreach ($hoursStmt->fetchAll() as $hourRow) {
        $hoursByDay[(int)$hourRow['day_of_week']] = (bool)$hourRow['is_closed'];
    }

    $blockedStmt = $pdo->prepare(
        "SELECT blocked_date FROM cms_res_blocked WHERE resource_id = ?"
    );
    $blockedStmt->execute([$resourceId]);
    $blockedDates = array_flip(array_column($blockedStmt->fetchAll(), 'blocked_date'));

    $probeDate = new DateTimeImmutable('today');
    for ($offset = 0; $offset <= $advanceDays; $offset++) {
        $candidate = $probeDate->modify('+' . $offset . ' days');
        $candidateStr = $candidate->format('Y-m-d');
        $candidateDow = ((int)$candidate->format('N')) - 1;
        if (!isset($hoursByDay[$candidateDow]) || $hoursByDay[$candidateDow]) {
            continue;
        }
        if (isset($blockedDates[$candidateStr])) {
            continue;
        }
        $reservationsBookDate = $candidateStr;
        break;
    }
}

$cleanup = [
    'public_user_ids' => [],
    'confirm_emails' => [],
    'subscriber_emails' => [],
    'newsletter_ids' => [],
    'contact_ids' => [],
    'chat_ids' => [],
    'author_user_ids' => [],
    'staff_user_ids' => [],
    'board_ids' => [],
    'download_ids' => [],
    'download_files' => [],
    'faq_ids' => [],
    'food_ids' => [],
    'gallery_album_ids' => [],
    'gallery_photo_ids' => [],
    'gallery_files' => [],
    'place_ids' => [],
    'poll_ids' => [],
    'podcast_show_ids' => [],
    'podcast_episode_ids' => [],
    'form_ids' => [],
    'form_submission_ids' => [],
];

$runtimeAuditActiveTheme = resolveThemeName(getSetting('active_theme', defaultThemeName()));
$runtimeAuditThemeSettingsKey = themeSettingStorageKey($runtimeAuditActiveTheme);
$runtimeAuditOriginalThemeSettings = getSetting($runtimeAuditThemeSettingsKey, '');
$runtimeAuditOriginalHomeAuthorUserId = getSetting('home_author_user_id', '0');
$runtimeAuditOriginalArticleAuthorId = null;
$runtimeAuditOriginalNewsAuthorId = null;
$runtimeAuditAuthorId = 0;
$runtimeAuditAuthorSlug = '';
$runtimeAuditAuthorPath = '';
$runtimeAuditAuthorUrl = '';

if (!$publicUserRow) {
    $publicAuditEmail = 'runtimeaudit-public-' . bin2hex(random_bytes(6)) . '@example.test';
    $pdo->prepare(
        "INSERT INTO cms_users (email, password, first_name, last_name, role, is_superadmin, is_confirmed, created_at)
         VALUES (?, ?, ?, ?, 'public', 0, 1, NOW())"
    )->execute([
        $publicAuditEmail,
        password_hash('RuntimeAudit123!', PASSWORD_BCRYPT),
        'Runtime',
        'Audit',
    ]);
    $publicUserId = (int)$pdo->lastInsertId();
    $cleanup['public_user_ids'][] = $publicUserId;
    $publicUserRow = [
        'id' => $publicUserId,
        'email' => $publicAuditEmail,
        'first_name' => 'Runtime',
        'last_name' => 'Audit',
    ];
}

$auditSessionId = 'runtimeauditadmin';
session_write_close();
session_id($auditSessionId);
session_start();
$_SESSION['cms_logged_in'] = true;
$_SESSION['cms_superadmin'] = true;
$_SESSION['cms_user_id'] = 1;
$_SESSION['cms_user_name'] = 'Runtime Audit';
$_SESSION['cms_user_role'] = 'admin';
$adminCsrfToken = csrfToken();
session_write_close();

$publicAuditSessionId = 'runtimeauditpublic';
session_id($publicAuditSessionId);
session_start();
$_SESSION['cms_logged_in'] = true;
$_SESSION['cms_superadmin'] = false;
$_SESSION['cms_user_id'] = (int)$publicUserRow['id'];
$_SESSION['cms_user_name'] = trim(((string)$publicUserRow['first_name']) . ' ' . ((string)$publicUserRow['last_name'])) ?: (string)$publicUserRow['email'];
$_SESSION['cms_user_role'] = 'public';
session_write_close();

$roleAuditUsers = [];
foreach ([
    'author' => ['first_name' => 'Role', 'last_name' => 'Autor'],
    'moderator' => ['first_name' => 'Role', 'last_name' => 'Moderátor'],
    'booking_manager' => ['first_name' => 'Role', 'last_name' => 'Rezervace'],
] as $roleKey => $roleMeta) {
    $roleAuditEmail = 'runtimeaudit-' . $roleKey . '-' . bin2hex(random_bytes(6)) . '@example.test';
    $pdo->prepare(
        "INSERT INTO cms_users (email, password, first_name, last_name, role, is_superadmin, is_confirmed, created_at)
         VALUES (?, ?, ?, ?, ?, 0, 1, NOW())"
    )->execute([
        $roleAuditEmail,
        password_hash('RuntimeAudit123!', PASSWORD_BCRYPT),
        $roleMeta['first_name'],
        $roleMeta['last_name'],
        $roleKey,
    ]);
    $roleAuditUserId = (int)$pdo->lastInsertId();
    $cleanup['staff_user_ids'][] = $roleAuditUserId;
    $roleAuditUsers[$roleKey] = [
        'id' => $roleAuditUserId,
        'email' => $roleAuditEmail,
        'name' => trim($roleMeta['first_name'] . ' ' . $roleMeta['last_name']),
    ];
}

$roleAuditSessions = [];
foreach ($roleAuditUsers as $roleKey => $roleAuditUser) {
    $roleAuditSessionId = 'runtimeaudit-' . str_replace('_', '-', $roleKey);
    session_id($roleAuditSessionId);
    session_start();
    $_SESSION['cms_logged_in'] = true;
    $_SESSION['cms_superadmin'] = false;
    $_SESSION['cms_user_id'] = (int)$roleAuditUser['id'];
    $_SESSION['cms_user_name'] = $roleAuditUser['name'];
    $_SESSION['cms_user_role'] = $roleKey;
    session_write_close();
    $roleAuditSessions[$roleKey] = $roleAuditSessionId;
}

$confirmToken = bin2hex(random_bytes(32));
$confirmEmail = 'runtimeaudit-confirm-' . bin2hex(random_bytes(6)) . '@example.test';
$pdo->prepare(
    "INSERT INTO cms_users (email, password, first_name, last_name, role, is_superadmin, is_confirmed, confirmation_token, confirmation_expires, created_at)
     VALUES (?, ?, ?, ?, 'public', 0, 0, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())"
)->execute([
    $confirmEmail,
    password_hash('RuntimeAudit123!', PASSWORD_BCRYPT),
    'Confirm',
    'Audit',
    $confirmToken,
]);
$cleanup['confirm_emails'][] = $confirmEmail;

$subscribeConfirmToken = '';
$unsubscribeToken = '';
if (isModuleEnabled('newsletter')) {
    $subscribeConfirmToken = bin2hex(random_bytes(32));
    $subscribeConfirmEmail = 'runtimeaudit-newsletter-confirm-' . bin2hex(random_bytes(6)) . '@example.test';
    $pendingSubscriberStmt = $pdo->prepare(
        "INSERT INTO cms_subscribers (email, token, confirmed) VALUES (?, ?, 0)"
    );
    $pendingSubscriberStmt->execute([$subscribeConfirmEmail, $subscribeConfirmToken]);
    $newsletterPendingSubscriberId = (int)$pdo->lastInsertId();
    $cleanup['subscriber_emails'][] = $subscribeConfirmEmail;

    $unsubscribeToken = bin2hex(random_bytes(32));
    $unsubscribeEmail = 'runtimeaudit-newsletter-unsub-' . bin2hex(random_bytes(6)) . '@example.test';
    $confirmedSubscriberStmt = $pdo->prepare(
        "INSERT INTO cms_subscribers (email, token, confirmed) VALUES (?, ?, 1)"
    );
    $confirmedSubscriberStmt->execute([$unsubscribeEmail, $unsubscribeToken]);
    $newsletterConfirmedSubscriberId = (int)$pdo->lastInsertId();
    $cleanup['subscriber_emails'][] = $unsubscribeEmail;

    $pdo->prepare(
        "INSERT INTO cms_newsletters (subject, body, recipient_count, sent_at, created_at)
         VALUES (?, ?, ?, NOW(), NOW())"
    )->execute([
        'Runtime audit newsletter',
        "Testovací obsah rozesílky pro audit administrace newsletteru.",
        1,
    ]);
    $newsletterHistoryId = (int)$pdo->lastInsertId();
    $cleanup['newsletter_ids'][] = $newsletterHistoryId;
}

$runtimeAuditAuthorSlug = uniqueAuthorSlug($pdo, 'runtime-author-' . bin2hex(random_bytes(4)));
$runtimeAuditAuthorEmail = 'runtimeaudit-author-' . bin2hex(random_bytes(6)) . '@example.test';
$pdo->prepare(
    "INSERT INTO cms_users (
        email, password, first_name, last_name, nickname, role, is_superadmin, is_confirmed,
        author_public_enabled, author_slug, author_bio, author_website, created_at
     ) VALUES (?, ?, ?, ?, ?, 'collaborator', 0, 1, 1, ?, ?, ?, NOW())"
)->execute([
    $runtimeAuditAuthorEmail,
    password_hash('RuntimeAudit123!', PASSWORD_BCRYPT),
    'Veřejný',
    'Autor',
    'Runtime Audit',
    $runtimeAuditAuthorSlug,
    'Krátký veřejný medailonek pro automatický audit autora.',
    'https://example.test/autor',
]);
$runtimeAuditAuthorId = (int)$pdo->lastInsertId();
$cleanup['author_user_ids'][] = $runtimeAuditAuthorId;

$runtimeAuditAuthor = fetchPublicAuthorById($pdo, $runtimeAuditAuthorId);
if ($runtimeAuditAuthor) {
    $runtimeAuditAuthorPath = authorPublicPath($runtimeAuditAuthor);
    $runtimeAuditAuthorUrl = $baseUrl . authorPublicRequestPath($runtimeAuditAuthor);
}

if ($articleId !== false && $runtimeAuditAuthorId > 0) {
    $articleAuthorStmt = $pdo->prepare("SELECT author_id FROM cms_articles WHERE id = ?");
    $articleAuthorStmt->execute([(int)$articleId]);
    $runtimeAuditOriginalArticleAuthorId = $articleAuthorStmt->fetchColumn();
    $pdo->prepare("UPDATE cms_articles SET author_id = ? WHERE id = ?")->execute([
        $runtimeAuditAuthorId,
        (int)$articleId,
    ]);
}

if ($newsId !== false && $runtimeAuditAuthorId > 0) {
    $newsAuthorStmt = $pdo->prepare("SELECT author_id FROM cms_news WHERE id = ?");
    $newsAuthorStmt->execute([(int)$newsId]);
    $runtimeAuditOriginalNewsAuthorId = $newsAuthorStmt->fetchColumn();
    $pdo->prepare("UPDATE cms_news SET author_id = ? WHERE id = ?")->execute([
        $runtimeAuditAuthorId,
        (int)$newsId,
    ]);
}

$runtimeAuditThemeSettings = themePersistedSettingsValues($runtimeAuditActiveTheme);
saveThemeSettings($runtimeAuditThemeSettings, $runtimeAuditActiveTheme);
clearSettingsCache();

if (isModuleEnabled('board')) {
    $runtimeAuditBoardTitle = 'Runtime audit vývěska';
    $runtimeAuditBoardSlug = uniqueBoardSlug($pdo, 'runtime-audit-vyveska-' . bin2hex(random_bytes(4)));
    $runtimeAuditBoardExcerpt = 'Krátké shrnutí testovací položky pro audit vývěsky a oznámení.';
    $runtimeAuditBoardPhone = '+420 777 123 456';
    $runtimeAuditBoardEmail = 'vyveska@example.test';
    $pdo->prepare(
        "INSERT INTO cms_board (
            title, slug, board_type, excerpt, description, category_id, posted_date, removal_date,
            image_file, contact_name, contact_phone, contact_email,
            filename, original_name, file_size, sort_order, is_pinned, is_published, status, author_id, created_at
         ) VALUES (?, ?, 'notice', ?, ?, NULL, '2030-12-31', NULL, '', ?, ?, ?, '', '', 0, -100, 1, 1, 'published', ?, NOW())"
    )->execute([
        $runtimeAuditBoardTitle,
        $runtimeAuditBoardSlug,
        $runtimeAuditBoardExcerpt,
        '<p>Detailní text runtime audit položky pro ověření veřejného detailu a výpisu.</p>',
        'Runtime Audit',
        $runtimeAuditBoardPhone,
        $runtimeAuditBoardEmail,
        $runtimeAuditAuthorId > 0 ? $runtimeAuditAuthorId : null,
    ]);
    $runtimeAuditBoardId = (int)$pdo->lastInsertId();
    $cleanup['board_ids'][] = $runtimeAuditBoardId;

    $boardStmt = $pdo->prepare(
        "SELECT b.*, COALESCE(c.name, '') AS category_name
         FROM cms_board b
         LEFT JOIN cms_board_categories c ON c.id = b.category_id
         WHERE b.id = ?"
    );
    $boardStmt->execute([$runtimeAuditBoardId]);
    $boardRow = $boardStmt->fetch() ?: null;
    if ($boardRow) {
        $boardRow = hydrateBoardPresentation($boardRow);
        $boardId = $boardRow['id'] ?? false;
        $boardCanonicalPath = boardPublicPath($boardRow);
        $boardLegacyPath = $boardId !== false ? BASE_URL . '/board/document.php?id=' . urlencode((string)$boardId) : '';
        $boardCanonicalUrl = $boardCanonicalPath !== '' ? $baseUrl . $boardCanonicalPath : '';
        $boardLegacyUrl = $boardLegacyPath !== '' ? $baseUrl . $boardLegacyPath : '';
    }

    $boardCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM cms_board
         WHERE status = 'published' AND is_published = 1
           AND (removal_date IS NULL OR removal_date >= CURDATE())"
    )->fetchColumn();
    $boardAttachmentId = $pdo->query(
        "SELECT id FROM cms_board
         WHERE status = 'published' AND is_published = 1 AND filename <> ''
         ORDER BY posted_date DESC, id DESC
         LIMIT 1"
    )->fetchColumn();
}

$runtimeAuditDownloadStoredName = 'runtime_audit_' . bin2hex(random_bytes(6)) . '.txt';
$runtimeAuditDownloadFilePath = __DIR__ . '/../uploads/downloads/' . $runtimeAuditDownloadStoredName;
if (!is_dir(dirname($runtimeAuditDownloadFilePath))) {
    mkdir(dirname($runtimeAuditDownloadFilePath), 0755, true);
}
file_put_contents($runtimeAuditDownloadFilePath, "Runtime audit download file.\n");
$cleanup['download_files'][] = $runtimeAuditDownloadStoredName;

$runtimeAuditDownloadTitle = 'Runtime audit aplikace';
$runtimeAuditDownloadSlug = uniqueDownloadSlug($pdo, 'runtime-audit-aplikace-' . bin2hex(random_bytes(4)));
$runtimeAuditDownloadExcerpt = 'Krátký přehled testovací položky ke stažení pro ověření detailu, metadat a bezpečného file endpointu.';
$pdo->prepare(
    "INSERT INTO cms_downloads (
        title, slug, download_type, dl_category_id, excerpt, description, image_file, version_label,
        platform_label, license_label, external_url, filename, original_name, file_size,
        sort_order, is_published, status, author_id, created_at, updated_at
     ) VALUES (?, ?, 'software', NULL, ?, ?, '', '1.0.0', 'Windows / Linux', 'MIT',
               ?, ?, ?, ?, -100, 1, 'published', ?, NOW(), NOW())"
)->execute([
    $runtimeAuditDownloadTitle,
    $runtimeAuditDownloadSlug,
    $runtimeAuditDownloadExcerpt,
    '<p>Detailní text runtime audit položky ke stažení pro ověření veřejného detailu a CTA tlačítek.</p>',
    'https://example.test/runtime-download',
    $runtimeAuditDownloadStoredName,
    'runtime-audit.txt',
    filesize($runtimeAuditDownloadFilePath) ?: 0,
    null,
]);
$runtimeAuditDownloadId = (int)$pdo->lastInsertId();
$cleanup['download_ids'][] = $runtimeAuditDownloadId;

$downloadStmt = $pdo->prepare(
    "SELECT d.*, COALESCE(c.name, '') AS category_name
     FROM cms_downloads d
     LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
     WHERE d.id = ?"
);
$downloadStmt->execute([$runtimeAuditDownloadId]);
$downloadRow = $downloadStmt->fetch() ?: null;
if ($downloadRow) {
    $downloadRow = hydrateDownloadPresentation($downloadRow);
    $downloadId = $downloadRow['id'] ?? false;
    $downloadCanonicalPath = downloadPublicPath($downloadRow);
    $downloadLegacyPath = $downloadId !== false ? BASE_URL . '/downloads/item.php?id=' . urlencode((string)$downloadId) : '';
    $downloadCanonicalUrl = $downloadCanonicalPath !== '' ? $baseUrl . $downloadCanonicalPath : '';
    $downloadLegacyUrl = $downloadLegacyPath !== '' ? $baseUrl . $downloadLegacyPath : '';
}

if (isModuleEnabled('gallery')) {
    $runtimeAuditGalleryBaseDir = __DIR__ . '/../uploads/gallery/';
    $runtimeAuditGalleryThumbDir = $runtimeAuditGalleryBaseDir . 'thumbs/';
    if (!is_dir($runtimeAuditGalleryBaseDir)) {
        mkdir($runtimeAuditGalleryBaseDir, 0755, true);
    }
    if (!is_dir($runtimeAuditGalleryThumbDir)) {
        mkdir($runtimeAuditGalleryThumbDir, 0755, true);
    }

    $runtimeAuditGalleryFilename = 'runtime_audit_' . bin2hex(random_bytes(6)) . '.gif';
    $runtimeAuditGalleryFileData = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
    if ($runtimeAuditGalleryFileData === false) {
        $runtimeAuditGalleryFileData = "GIF89a";
    }
    file_put_contents($runtimeAuditGalleryBaseDir . $runtimeAuditGalleryFilename, $runtimeAuditGalleryFileData);
    file_put_contents($runtimeAuditGalleryThumbDir . $runtimeAuditGalleryFilename, $runtimeAuditGalleryFileData);
    $cleanup['gallery_files'][] = $runtimeAuditGalleryFilename;

    $runtimeAuditGalleryAlbumSlug = uniqueGalleryAlbumSlug($pdo, 'runtime-audit-galerie-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_gallery_albums (parent_id, name, slug, description, cover_photo_id, created_at, updated_at)
         VALUES (NULL, ?, ?, ?, NULL, NOW(), NOW())"
    )->execute([
        'Runtime audit galerie',
        $runtimeAuditGalleryAlbumSlug,
        'Testovaci album pro overeni verejneho detailu, admin workflow a cistych URL.',
    ]);
    $runtimeAuditGalleryAlbumId = (int)$pdo->lastInsertId();
    $cleanup['gallery_album_ids'][] = $runtimeAuditGalleryAlbumId;

    $runtimeAuditGalleryPhotoSlug = uniqueGalleryPhotoSlug($pdo, 'runtime-audit-fotka-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_gallery_photos (album_id, filename, title, slug, sort_order, created_at)
         VALUES (?, ?, ?, ?, 0, NOW())"
    )->execute([
        $runtimeAuditGalleryAlbumId,
        $runtimeAuditGalleryFilename,
        'Runtime audit fotka',
        $runtimeAuditGalleryPhotoSlug,
    ]);
    $runtimeAuditGalleryPhotoId = (int)$pdo->lastInsertId();
    $cleanup['gallery_photo_ids'][] = $runtimeAuditGalleryPhotoId;

    $pdo->prepare("UPDATE cms_gallery_albums SET cover_photo_id = ? WHERE id = ?")->execute([
        $runtimeAuditGalleryPhotoId,
        $runtimeAuditGalleryAlbumId,
    ]);

    $galleryAlbumStmt = $pdo->prepare(
        "SELECT id, name, slug, description, COALESCE(updated_at, created_at) AS updated_at
         FROM cms_gallery_albums
         WHERE id = ?"
    );
    $galleryAlbumStmt->execute([$runtimeAuditGalleryAlbumId]);
    $galleryAlbumRow = $galleryAlbumStmt->fetch() ?: null;
    $galleryAlbumId = $galleryAlbumRow['id'] ?? false;
    if ($galleryAlbumRow) {
        $galleryAlbumRow = hydrateGalleryAlbumPresentation($galleryAlbumRow);
    }
    $galleryAlbumCanonicalPath = $galleryAlbumRow ? galleryAlbumPublicPath($galleryAlbumRow) : '';
    $galleryAlbumLegacyPath = $galleryAlbumId !== false ? BASE_URL . '/gallery/album.php?id=' . urlencode((string)$galleryAlbumId) : '';
    $galleryAlbumCanonicalUrl = $galleryAlbumCanonicalPath !== '' ? $baseUrl . $galleryAlbumCanonicalPath : '';
    $galleryAlbumLegacyUrl = $galleryAlbumLegacyPath !== '' ? $baseUrl . $galleryAlbumLegacyPath : '';

    $galleryPhotoStmt = $pdo->prepare(
        "SELECT id, album_id, filename, title, slug, sort_order, created_at
         FROM cms_gallery_photos
         WHERE id = ?"
    );
    $galleryPhotoStmt->execute([$runtimeAuditGalleryPhotoId]);
    $galleryPhotoRow = $galleryPhotoStmt->fetch() ?: null;
    $galleryPhotoId = $galleryPhotoRow['id'] ?? false;
    $galleryPhotoAlbumId = $galleryPhotoRow['album_id'] ?? false;
    if ($galleryPhotoRow) {
        $galleryPhotoRow = hydrateGalleryPhotoPresentation($galleryPhotoRow);
    }
    $galleryPhotoCanonicalPath = $galleryPhotoRow ? galleryPhotoPublicPath($galleryPhotoRow) : '';
    $galleryPhotoLegacyPath = $galleryPhotoId !== false ? BASE_URL . '/gallery/photo.php?id=' . urlencode((string)$galleryPhotoId) : '';
    $galleryPhotoCanonicalUrl = $galleryPhotoCanonicalPath !== '' ? $baseUrl . $galleryPhotoCanonicalPath : '';
    $galleryPhotoLegacyUrl = $galleryPhotoLegacyPath !== '' ? $baseUrl . $galleryPhotoLegacyPath : '';

    $galleryAlbumPhotoRow = $galleryPhotoRow;
    $galleryAlbumPhotoCanonicalPath = $galleryPhotoCanonicalPath;
}

if (isModuleEnabled('food')) {
    $runtimeAuditFoodSlug = uniqueFoodCardSlug($pdo, 'runtime-audit-listek-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_food_cards (
            type, title, slug, description, content, valid_from, valid_to,
            is_current, is_published, status, author_id, created_at, updated_at
         ) VALUES ('food', ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 1, 1, 'published', ?, NOW(), NOW())"
    )->execute([
        'Runtime audit listek',
        $runtimeAuditFoodSlug,
        'Testovaci karta pro overeni detailu a cistych URL.',
        '<p>Obsah testovaciho listku pro runtime audit verejneho detailu a admin workflow.</p>',
        $runtimeAuditAuthorId > 0 ? $runtimeAuditAuthorId : null,
    ]);
    $runtimeAuditFoodId = (int)$pdo->lastInsertId();
    $cleanup['food_ids'][] = $runtimeAuditFoodId;

    $foodStmt = $pdo->prepare(
        "SELECT id, type, title, slug, description, valid_from, valid_to, is_current, is_published,
                status, created_at, updated_at
         FROM cms_food_cards
         WHERE id = ?"
    );
    $foodStmt->execute([$runtimeAuditFoodId]);
    $foodCardRow = $foodStmt->fetch() ?: null;
    $foodCardId = $foodCardRow['id'] ?? false;
    if ($foodCardRow) {
        $foodCardRow = hydrateFoodCardPresentation($foodCardRow);
    }
    $foodCardCanonicalPath = $foodCardRow ? foodCardPublicPath($foodCardRow) : '';
    $foodCardLegacyPath = $foodCardId !== false ? BASE_URL . '/food/card.php?id=' . urlencode((string)$foodCardId) : '';
    $foodCardCanonicalUrl = $foodCardCanonicalPath !== '' ? $baseUrl . $foodCardCanonicalPath : '';
    $foodCardLegacyUrl = $foodCardLegacyPath !== '' ? $baseUrl . $foodCardLegacyPath : '';
}

if (isModuleEnabled('faq')) {
    $runtimeAuditFaqQuestion = 'Jak funguje runtime audit FAQ?';
    $runtimeAuditFaqSlug = uniqueFaqSlug($pdo, 'runtime-audit-faq-' . bin2hex(random_bytes(4)));
    $runtimeAuditFaqExcerpt = 'Krátké shrnutí testovací FAQ položky pro ověření veřejného detailu, vyhledávání a redakčního workflow.';
    $pdo->prepare(
        "INSERT INTO cms_faqs (
            question, slug, excerpt, answer, category_id, sort_order, is_published, status, created_at, updated_at
         ) VALUES (?, ?, ?, ?, NULL, -100, 1, 'published', NOW(), NOW())"
    )->execute([
        $runtimeAuditFaqQuestion,
        $runtimeAuditFaqSlug,
        $runtimeAuditFaqExcerpt,
        '<p>Detailní odpověď runtime audit položky FAQ pro ověření veřejného detailu a znalostní báze.</p>',
    ]);
    $runtimeAuditFaqId = (int)$pdo->lastInsertId();
    $cleanup['faq_ids'][] = $runtimeAuditFaqId;

    $faqStmt = $pdo->prepare(
        "SELECT f.*, c.name AS category_name
         FROM cms_faqs f
         LEFT JOIN cms_faq_categories c ON c.id = f.category_id
         WHERE f.id = ?"
    );
    $faqStmt->execute([$runtimeAuditFaqId]);
    $faqRow = $faqStmt->fetch() ?: null;
    if ($faqRow) {
        $faqRow = hydrateFaqPresentation($faqRow);
        $faqId = $faqRow['id'] ?? false;
        $faqCanonicalPath = faqPublicPath($faqRow);
        $faqLegacyPath = $faqId !== false ? BASE_URL . '/faq/item.php?id=' . urlencode((string)$faqId) : '';
        $faqCanonicalUrl = $faqCanonicalPath !== '' ? $baseUrl . $faqCanonicalPath : '';
        $faqLegacyUrl = $faqLegacyPath !== '' ? $baseUrl . $faqLegacyPath : '';
    }
}

if (isModuleEnabled('places')) {
    $runtimeAuditPlaceName = 'Runtime audit místo';
    $runtimeAuditPlaceSlug = uniquePlaceSlug($pdo, 'runtime-audit-misto-' . bin2hex(random_bytes(4)));
    $runtimeAuditPlaceExcerpt = 'Krátký přehled testovacího místa pro ověření detailu, praktických informací a veřejného výpisu.';
    $pdo->prepare(
        "INSERT INTO cms_places (
            name, slug, place_kind, category, excerpt, description, image_file, address, locality,
            latitude, longitude, url, contact_phone, contact_email, opening_hours,
            is_published, status, sort_order, created_at, updated_at
         ) VALUES (?, ?, 'info', ?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, 1, 'published', -100, NOW(), NOW())"
    )->execute([
        $runtimeAuditPlaceName,
        $runtimeAuditPlaceSlug,
        'Testovací lokalita',
        $runtimeAuditPlaceExcerpt,
        '<p>Detailní text runtime audit místa pro ověření veřejného detailu a praktických informací.</p>',
        'Testovací 1',
        'Praha',
        50.0874510,
        14.4206710,
        'https://example.test/misto',
        '+420 777 987 654',
        'misto@example.test',
        "Po–Pá: 9:00–17:00\nSo: 10:00–14:00",
    ]);
    $runtimeAuditPlaceId = (int)$pdo->lastInsertId();
    $cleanup['place_ids'][] = $runtimeAuditPlaceId;

    $placeStmt = $pdo->prepare(
        "SELECT *
         FROM cms_places
         WHERE id = ?"
    );
    $placeStmt->execute([$runtimeAuditPlaceId]);
    $placeRow = $placeStmt->fetch() ?: null;
    if ($placeRow) {
        $placeRow = hydratePlacePresentation($placeRow);
        $placeId = $placeRow['id'] ?? false;
        $placeCanonicalPath = placePublicPath($placeRow);
        $placeLegacyPath = $placeId !== false ? BASE_URL . '/places/place.php?id=' . urlencode((string)$placeId) : '';
        $placeCanonicalUrl = $placeCanonicalPath !== '' ? $baseUrl . $placeCanonicalPath : '';
        $placeLegacyUrl = $placeLegacyPath !== '' ? $baseUrl . $placeLegacyPath : '';
    }
}

if (isModuleEnabled('polls')) {
    $runtimeAuditPollQuestion = 'Runtime audit anketa';
    $runtimeAuditPollSlug = uniquePollSlug($pdo, 'runtime-audit-anketa-' . bin2hex(random_bytes(4)));
    $runtimeAuditPollStartAt = date('Y-m-d H:i:s', time() - 3600);
    $runtimeAuditPollEndAt = date('Y-m-d H:i:s', time() + (14 * 86400));
    $pdo->prepare(
        "INSERT INTO cms_polls (question, slug, description, status, start_date, end_date, created_at, updated_at)
         VALUES (?, ?, ?, 'active', ?, ?, NOW(), NOW())"
    )->execute([
        $runtimeAuditPollQuestion,
        $runtimeAuditPollSlug,
        'Krátké shrnutí testovací ankety pro ověření detailu, hlasování a čistých URL.',
        $runtimeAuditPollStartAt,
        $runtimeAuditPollEndAt,
    ]);
    $runtimeAuditPollId = (int)$pdo->lastInsertId();
    $cleanup['poll_ids'][] = $runtimeAuditPollId;

    $pollOptionStmt = $pdo->prepare(
        "INSERT INTO cms_poll_options (poll_id, option_text, sort_order) VALUES (?, ?, ?)"
    );
    foreach (['Ano', 'Ne', 'Ještě nevím'] as $pollOptionIndex => $pollOptionText) {
        $pollOptionStmt->execute([$runtimeAuditPollId, $pollOptionText, $pollOptionIndex]);
    }

    $pollStmt = $pdo->prepare(
        "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
         FROM cms_polls p
         WHERE p.id = ?"
    );
    $pollStmt->execute([$runtimeAuditPollId]);
    $pollRow = $pollStmt->fetch() ?: null;
    if ($pollRow) {
        $pollRow = hydratePollPresentation($pollRow);
        $pollId = $pollRow['id'] ?? false;
        $pollCanonicalPath = pollPublicPath($pollRow);
        $pollLegacyPath = $pollId !== false ? BASE_URL . '/polls/index.php?id=' . urlencode((string)$pollId) : '';
        $pollCanonicalUrl = $pollCanonicalPath !== '' ? $baseUrl . $pollCanonicalPath : '';
        $pollLegacyUrl = $pollLegacyPath !== '' ? $baseUrl . $pollLegacyPath : '';
        $pollDetailId = $pollId;
    }

    $activePollCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM cms_polls
         WHERE status = 'active'
           AND (start_date IS NULL OR start_date <= NOW())
           AND (end_date IS NULL OR end_date > NOW())"
    )->fetchColumn();
}

if (isModuleEnabled('podcast')) {
    $runtimeAuditPodcastShowTitle = 'Runtime audit podcast';
    $runtimeAuditPodcastShowSlug = uniquePodcastShowSlug($pdo, 'runtime-audit-podcast-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_podcast_shows (
            title, slug, description, author, subtitle, cover_image, language, category, owner_name, owner_email,
            explicit_mode, show_type, feed_complete, feed_episode_limit, website_url, created_at, updated_at
         ) VALUES (?, ?, ?, ?, ?, '', 'cs', ?, ?, ?, 'clean', 'episodic', 0, 25, ?, NOW(), NOW())"
    )->execute([
        $runtimeAuditPodcastShowTitle,
        $runtimeAuditPodcastShowSlug,
        '<p>Testovací pořad pro runtime audit veřejných URL, RSS feedu a administrace podcastů.</p>',
        'Runtime Audit',
        'Krátký podtitul testovacího pořadu.',
        'Technologie',
        'Runtime Audit Owner',
        'runtime.audit.owner@example.test',
        'https://example.test/podcast',
    ]);
    $runtimeAuditPodcastShowId = (int)$pdo->lastInsertId();
    $cleanup['podcast_show_ids'][] = $runtimeAuditPodcastShowId;

    $runtimeAuditPodcastEpisodeTitle = 'Runtime audit epizoda';
    $runtimeAuditPodcastEpisodeSlug = uniquePodcastEpisodeSlug($pdo, $runtimeAuditPodcastShowId, 'runtime-audit-epizoda-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_podcasts (
            show_id, title, slug, description, audio_file, audio_url, subtitle, duration, episode_num, season_num,
            episode_type, explicit_mode, block_from_feed, publish_at, status, created_at, updated_at
         ) VALUES (?, ?, ?, ?, '', ?, ?, '12:34', 1, 2, 'bonus', 'yes', 0, NOW(), 'published', NOW(), NOW())"
    )->execute([
        $runtimeAuditPodcastShowId,
        $runtimeAuditPodcastEpisodeTitle,
        $runtimeAuditPodcastEpisodeSlug,
        '<p>Detailní text testovací epizody pro runtime audit podcastů.</p>',
        'https://example.test/runtime-audit-episode.mp3',
        'Krátký podtitul testovací epizody.',
    ]);
    $runtimeAuditPodcastEpisodeId = (int)$pdo->lastInsertId();
    $cleanup['podcast_episode_ids'][] = $runtimeAuditPodcastEpisodeId;

    $podcastShowStmt = $pdo->prepare(
        "SELECT s.*,
                COUNT(e.id) AS episode_count,
                MAX(COALESCE(e.publish_at, e.created_at)) AS latest_episode_at
         FROM cms_podcast_shows s
         LEFT JOIN cms_podcasts e ON e.show_id = s.id
             AND e.status = 'published' AND (e.publish_at IS NULL OR e.publish_at <= NOW())
         WHERE s.id = ?
         GROUP BY s.id"
    );
    $podcastShowStmt->execute([$runtimeAuditPodcastShowId]);
    $podcastShowRow = $podcastShowStmt->fetch() ?: null;
    if ($podcastShowRow) {
        $podcastShowRow = hydratePodcastShowPresentation($podcastShowRow);
        $podcastShowSlug = (string)($podcastShowRow['slug'] ?? '');
    }

    $podcastEpisodeStmt = $pdo->prepare(
        "SELECT p.*, s.slug AS show_slug, s.title AS show_title
         FROM cms_podcasts p
         INNER JOIN cms_podcast_shows s ON s.id = p.show_id
         WHERE p.id = ?"
    );
    $podcastEpisodeStmt->execute([$runtimeAuditPodcastEpisodeId]);
    $podcastEpisodeRow = $podcastEpisodeStmt->fetch() ?: null;
    if ($podcastEpisodeRow) {
        $podcastEpisodeRow = hydratePodcastEpisodePresentation($podcastEpisodeRow);
        $podcastEpisodeId = $podcastEpisodeRow['id'] ?? false;
        $podcastEpisodeCanonicalPath = podcastEpisodePublicPath($podcastEpisodeRow);
        $podcastEpisodeLegacyPath = $podcastEpisodeId !== false ? BASE_URL . '/podcast/episode.php?id=' . urlencode((string)$podcastEpisodeId) : '';
        $podcastEpisodeCanonicalUrl = $podcastEpisodeCanonicalPath !== '' ? $baseUrl . $podcastEpisodeCanonicalPath : '';
        $podcastEpisodeLegacyUrl = $podcastEpisodeLegacyPath !== '' ? $baseUrl . $podcastEpisodeLegacyPath : '';
    }
}

if (isModuleEnabled('contact')) {
    $pdo->prepare(
        "INSERT INTO cms_contact (sender_email, subject, message, is_read, status, created_at, updated_at)
         VALUES (?, ?, ?, 0, 'new', NOW(), NOW())"
    )->execute([
        'runtimeaudit-contact-' . bin2hex(random_bytes(4)) . '@example.test',
        'Runtime audit kontakt',
        "Testovací kontaktní zpráva pro audit inboxu a detailu zprávy.",
    ]);
    $contactMessageId = (int)$pdo->lastInsertId();
    $cleanup['contact_ids'][] = $contactMessageId;
}

if (isModuleEnabled('chat')) {
    $pdo->prepare(
        "INSERT INTO cms_chat (name, email, web, message, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, 'new', NOW(), NOW())"
    )->execute([
        'Runtime Audit',
        'runtimeaudit-chat-' . bin2hex(random_bytes(4)) . '@example.test',
        'https://example.test/runtime-audit-chat',
        'Testovací chat zpráva pro audit moderátorského inboxu.',
    ]);
    $chatMessageId = (int)$pdo->lastInsertId();
    $cleanup['chat_ids'][] = $chatMessageId;
}

if (isModuleEnabled('forms')) {
    $runtimeAuditFormSlug = uniqueFormSlug($pdo, 'runtime-audit-form-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_forms (
            title, slug, description, success_message, submit_label, notification_subject,
            success_behavior, success_primary_label, success_primary_url, success_secondary_label, success_secondary_url,
            use_honeypot, submitter_confirmation_enabled, submitter_email_field,
            submitter_confirmation_subject, submitter_confirmation_message, is_active, created_at, updated_at
         )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?, ?, 1, NOW(), NOW())"
    )->execute([
        'Runtime audit formulář',
        $runtimeAuditFormSlug,
        'Testovací formulář pro ověření veřejného vykreslení, více příloh, podmíněných polí a administračního workflow.',
        'Děkujeme, hlášení bylo odesláno.',
        'Odeslat hlášení',
        'Runtime audit formulář',
        'message',
        'Nahlásit další problém',
        '/forms/' . rawurlencode($runtimeAuditFormSlug),
        'Přejít na kontakty',
        '/contact/index.php',
        'email_odesilatele',
        'Potvrzení přijetí hlášení',
        'Děkujeme za formulář {{form_title}}.',
    ]);
    $runtimeAuditFormId = (int)$pdo->lastInsertId();
    $cleanup['form_ids'][] = $runtimeAuditFormId;

    $runtimeAuditFormFields = [
        ['field_type' => 'hidden', 'label' => 'Zdroj hlášení', 'name' => 'zdroj_hlaseni', 'placeholder' => '', 'default_value' => 'runtime-audit', 'help_text' => '', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'start_new_row' => 0, 'is_required' => 0, 'sort_order' => 0],
        ['field_type' => 'section', 'label' => 'Zařazení problému', 'name' => '', 'placeholder' => '', 'default_value' => '', 'help_text' => 'Nejdřív popište, kam problém patří a jak moc je závažný.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'start_new_row' => 0, 'is_required' => 0, 'sort_order' => 5],
        ['field_type' => 'email', 'label' => 'E-mail odesílatele', 'name' => 'email_odesilatele', 'placeholder' => 'vas@email.cz', 'default_value' => '', 'help_text' => 'Na tuto adresu může přijít potvrzení o přijetí hlášení.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'allow_multiple' => 0, 'layout_width' => 'half', 'start_new_row' => 0, 'show_if_field' => '', 'show_if_operator' => '', 'show_if_value' => '', 'is_required' => 0, 'sort_order' => 10],
        ['field_type' => 'text', 'label' => 'Název problému', 'name' => 'nazev-problemu', 'placeholder' => 'Stručný název chyby', 'default_value' => '', 'help_text' => 'Krátký souhrn toho, co se pokazilo.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'allow_multiple' => 0, 'layout_width' => 'half', 'start_new_row' => 1, 'show_if_field' => '', 'show_if_operator' => '', 'show_if_value' => '', 'is_required' => 1, 'sort_order' => 20],
        ['field_type' => 'checkbox_group', 'label' => 'Dotčené oblasti', 'name' => 'dotcene_oblasti', 'placeholder' => '', 'default_value' => 'Administrace|Formuláře', 'help_text' => 'Můžete označit více oblastí.', 'options' => 'Administrace|Veřejný web|Formuláře', 'accept_types' => '', 'max_file_size_mb' => 10, 'allow_multiple' => 0, 'layout_width' => 'half', 'start_new_row' => 0, 'show_if_field' => '', 'show_if_operator' => '', 'show_if_value' => '', 'is_required' => 0, 'sort_order' => 30],
        ['field_type' => 'radio', 'label' => 'Závažnost', 'name' => 'zavaznost', 'placeholder' => '', 'default_value' => '', 'help_text' => 'Vyberte, jak moc problém blokuje práci.', 'options' => 'Nízká|Střední|Vysoká|Kritická', 'accept_types' => '', 'max_file_size_mb' => 10, 'allow_multiple' => 0, 'layout_width' => 'half', 'start_new_row' => 0, 'show_if_field' => '', 'show_if_operator' => '', 'show_if_value' => '', 'is_required' => 1, 'sort_order' => 40],
        ['field_type' => 'text', 'label' => 'Prohlížeč a zařízení', 'name' => 'prohlizec_a_zarizeni', 'placeholder' => 'Například Firefox 125 na Windows 11', 'default_value' => '', 'help_text' => 'Pomůže nám zjistit, jestli se problém týká konkrétního prostředí.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'allow_multiple' => 0, 'layout_width' => 'half', 'start_new_row' => 0, 'show_if_field' => '', 'show_if_operator' => '', 'show_if_value' => '', 'is_required' => 0, 'sort_order' => 50],
        ['field_type' => 'section', 'label' => 'Popis chyby', 'name' => '', 'placeholder' => '', 'default_value' => '', 'help_text' => 'Tady popište, co se děje a jaký to má dopad na práci.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'start_new_row' => 0, 'is_required' => 0, 'sort_order' => 55],
        ['field_type' => 'textarea', 'label' => 'Dopad na práci', 'name' => 'dopad_na_praci', 'placeholder' => 'Popište, co je teď blokované nebo co nejde dokončit.', 'default_value' => '', 'help_text' => 'Zobrazí se u vysoké nebo kritické závažnosti.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'allow_multiple' => 0, 'layout_width' => 'full', 'start_new_row' => 1, 'show_if_field' => 'zavaznost', 'show_if_operator' => 'contains', 'show_if_value' => 'Vysoká|Kritická', 'is_required' => 0, 'sort_order' => 60],
        ['field_type' => 'section', 'label' => 'Přílohy a kontakt', 'name' => '', 'placeholder' => '', 'default_value' => '', 'help_text' => 'Nakonec můžete přidat přílohy a potvrdit zpracování hlášení.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'layout_width' => 'full', 'start_new_row' => 0, 'is_required' => 0, 'sort_order' => 65],
        ['field_type' => 'consent', 'label' => 'Souhlasím se zpracováním údajů pro vyřízení hlášení.', 'name' => 'souhlas', 'placeholder' => '', 'default_value' => '', 'help_text' => 'Povinné potvrzení pro vyřízení hlášení.', 'options' => '', 'accept_types' => '', 'max_file_size_mb' => 10, 'allow_multiple' => 0, 'layout_width' => 'full', 'start_new_row' => 0, 'show_if_field' => '', 'show_if_operator' => '', 'show_if_value' => '', 'is_required' => 1, 'sort_order' => 70],
        ['field_type' => 'file', 'label' => 'Příloha', 'name' => 'priloha', 'placeholder' => '', 'default_value' => '', 'help_text' => 'Volitelně můžete přiložit screenshot nebo log.', 'options' => '', 'accept_types' => '.png,.jpg,.jpeg,.txt,.log,.pdf', 'max_file_size_mb' => 10, 'allow_multiple' => 1, 'layout_width' => 'half', 'start_new_row' => 0, 'show_if_field' => '', 'show_if_operator' => '', 'show_if_value' => '', 'is_required' => 0, 'sort_order' => 80],
    ];
    $formFieldInsert = $pdo->prepare(
        "INSERT INTO cms_form_fields (form_id, field_type, label, name, placeholder, default_value, help_text, options, accept_types, max_file_size_mb, allow_multiple, layout_width, start_new_row, show_if_field, show_if_operator, show_if_value, is_required, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($runtimeAuditFormFields as $fieldRow) {
        $formFieldInsert->execute([
            $runtimeAuditFormId,
            $fieldRow['field_type'],
            $fieldRow['label'],
            $fieldRow['name'],
            $fieldRow['placeholder'],
            $fieldRow['default_value'],
            $fieldRow['help_text'],
            $fieldRow['options'],
            $fieldRow['accept_types'],
            $fieldRow['max_file_size_mb'],
            $fieldRow['allow_multiple'] ?? 0,
            $fieldRow['layout_width'] ?? 'full',
            $fieldRow['start_new_row'] ?? 0,
            $fieldRow['show_if_field'] ?? '',
            $fieldRow['show_if_operator'] ?? '',
            $fieldRow['show_if_value'] ?? '',
            $fieldRow['is_required'],
            $fieldRow['sort_order'],
        ]);
    }

    $pdo->prepare(
        "INSERT INTO cms_form_submissions (form_id, data, ip_hash, priority, labels, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    )->execute([
        $runtimeAuditFormId,
        json_encode([
            'zdroj_hlaseni' => 'runtime-audit',
            'email_odesilatele' => 'runtime.audit@example.test',
            'nazev-problemu' => 'Runtime audit nahlášení',
            'dotcene_oblasti' => ['Administrace', 'Formuláře'],
            'zavaznost' => 'Vysoká',
            'prohlizec_a_zarizeni' => 'Firefox 125 na Windows 11',
            'dopad_na_praci' => 'Nelze dokončit odeslání formuláře bez reloadu stránky.',
            'souhlas' => '1',
            'priloha' => [
                [
                    'original_name' => 'runtime-audit-log.txt',
                    'stored_name' => 'runtime-audit-log.txt',
                    'mime_type' => 'text/plain',
                    'file_size' => 128,
                ],
                [
                    'original_name' => 'runtime-audit-shot.png',
                    'stored_name' => 'runtime-audit-shot.png',
                    'mime_type' => 'image/png',
                    'file_size' => 256,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        hash('sha256', 'runtime-audit-form'),
        'high',
        'Administrace, Formuláře',
    ]);
    $runtimeAuditFormSubmissionId = (int)$pdo->lastInsertId();
    $runtimeAuditFormSubmissionReference = formSubmissionBuildReference([
        'title' => 'Runtime audit formulář',
        'slug' => $runtimeAuditFormSlug,
    ], $runtimeAuditFormSubmissionId, date('Y-m-d H:i:s'));
    $cleanup['form_submission_ids'][] = $runtimeAuditFormSubmissionId;

    $runtimeAuditAssignedUserId = (int)($roleAuditUsers['moderator']['id'] ?? 1);
    $pdo->prepare(
        "UPDATE cms_form_submissions
         SET reference_code = ?, status = 'in_progress', assigned_user_id = ?, internal_note = ?, updated_at = NOW()
         WHERE id = ?"
    )->execute([
        $runtimeAuditFormSubmissionReference,
        $runtimeAuditAssignedUserId,
        'Runtime audit workflow poznámka pro ověření detailu odpovědi.',
        $runtimeAuditFormSubmissionId,
    ]);
    formSubmissionHistoryCreate(
        $pdo,
        $runtimeAuditFormSubmissionId,
        null,
        'created',
        'Odpověď byla přijata přes veřejný formulář.'
    );
    formSubmissionHistoryCreate(
        $pdo,
        $runtimeAuditFormSubmissionId,
        $runtimeAuditAssignedUserId,
        'workflow',
        'Stav změněn na „Rozpracované“. Priorita změněna na „Vysoká“. Štítky upraveny na „Administrace, Formuláře“. Změněn přiřazený řešitel. Interní poznámka byla upravena.'
    );
    formSubmissionHistoryCreate(
        $pdo,
        $runtimeAuditFormSubmissionId,
        $runtimeAuditAssignedUserId,
        'reply',
        'Odeslána odpověď odesílateli „runtime.audit@example.test“ s předmětem „Re: Runtime audit formulář (' . $runtimeAuditFormSubmissionReference . ')“.'
    );

    $runtimeAuditFormPath = $baseUrl . formPublicPath(['id' => $runtimeAuditFormId, 'slug' => $runtimeAuditFormSlug]);
}

$pages = [
    ['label' => 'home', 'url' => $baseUrl . '/'],
    ['label' => 'sitemap_php', 'url' => $baseUrl . '/sitemap.php'],
    ['label' => 'sitemap_xml', 'url' => $baseUrl . '/sitemap.xml'],
    ['label' => 'search', 'url' => $baseUrl . '/search.php?q=test'],
    ['label' => 'public_login', 'url' => $baseUrl . '/public_login.php'],
    ['label' => 'register', 'url' => $baseUrl . '/register.php'],
    ['label' => 'reset_password', 'url' => $baseUrl . '/reset_password.php'],
    ['label' => 'subscribe', 'url' => $baseUrl . '/subscribe.php'],
    ['label' => 'confirm_email', 'url' => $baseUrl . '/confirm_email.php?token=' . urlencode($confirmToken)],
    ['label' => 'contact', 'url' => $baseUrl . '/contact/index.php'],
    ['label' => 'chat', 'url' => $baseUrl . '/chat/index.php'],
    ['label' => 'public_profile', 'url' => $baseUrl . '/public_profile.php', 'cookie' => 'PHPSESSID=' . $publicAuditSessionId],
    ['label' => 'admin_index', 'url' => $baseUrl . '/admin/index.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_profile', 'url' => $baseUrl . '/admin/profile.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_settings', 'url' => $baseUrl . '/admin/settings.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_settings_modules', 'url' => $baseUrl . '/admin/settings_modules.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_comments', 'url' => $baseUrl . '/admin/comments.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_contact', 'url' => $baseUrl . '/admin/contact.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_chat', 'url' => $baseUrl . '/admin/chat.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_newsletter', 'url' => $baseUrl . '/admin/newsletter.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_newsletter_form', 'url' => $baseUrl . '/admin/newsletter_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_pages', 'url' => $baseUrl . '/admin/pages.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_page_positions', 'url' => $baseUrl . '/admin/page_positions.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_faq', 'url' => $baseUrl . '/admin/faq.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_news', 'url' => $baseUrl . '/admin/news.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_events', 'url' => $baseUrl . '/admin/events.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_board', 'url' => $baseUrl . '/admin/board.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_downloads', 'url' => $baseUrl . '/admin/downloads.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_media', 'url' => $baseUrl . '/admin/media.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_food', 'url' => $baseUrl . '/admin/food.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_gallery_albums', 'url' => $baseUrl . '/admin/gallery_albums.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_polls', 'url' => $baseUrl . '/admin/polls.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_places', 'url' => $baseUrl . '/admin/places.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_res_resources', 'url' => $baseUrl . '/admin/res_resources.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_res_categories', 'url' => $baseUrl . '/admin/res_categories.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_res_locations', 'url' => $baseUrl . '/admin/res_locations.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_themes', 'url' => $baseUrl . '/admin/themes.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_statistics', 'url' => $baseUrl . '/admin/statistics.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_users', 'url' => $baseUrl . '/admin/users.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_review_queue', 'url' => $baseUrl . '/admin/review_queue.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_user_create_form', 'url' => $baseUrl . '/admin/user_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_forms', 'url' => $baseUrl . '/admin/forms.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_form_create', 'url' => $baseUrl . '/admin/form_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_form_issue_preset', 'url' => $baseUrl . '/admin/form_form.php?preset=issue_report', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_form_feature_preset', 'url' => $baseUrl . '/admin/form_form.php?preset=feature_request', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_form_support_preset', 'url' => $baseUrl . '/admin/form_form.php?preset=support_request', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_form_contact_preset', 'url' => $baseUrl . '/admin/form_form.php?preset=contact_basic', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_form_content_report_preset', 'url' => $baseUrl . '/admin/form_form.php?preset=content_report', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_wp_import', 'url' => $baseUrl . '/admin/wp_import.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_estranky_import', 'url' => $baseUrl . '/admin/estranky_import.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_estranky_photos', 'url' => $baseUrl . '/admin/estranky_download_photos.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_integrity', 'url' => $baseUrl . '/admin/integrity.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_blogs', 'url' => $baseUrl . '/admin/blogs.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_widgets', 'url' => $baseUrl . '/admin/widgets.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_redirects', 'url' => $baseUrl . '/admin/redirects.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_audit_log', 'url' => $baseUrl . '/admin/audit_log.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_backup', 'url' => $baseUrl . '/admin/backup.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_trash', 'url' => $baseUrl . '/admin/trash.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_menu', 'url' => $baseUrl . '/admin/menu.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_blog_cats', 'url' => $baseUrl . '/admin/blog_cats.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
    ['label' => 'admin_blog_tags', 'url' => $baseUrl . '/admin/blog_tags.php', 'cookie' => 'PHPSESSID=' . $auditSessionId],
];

if ($runtimeAuditAuthorId > 0) {
    $pages[] = ['label' => 'admin_user_form', 'url' => $baseUrl . '/admin/user_form.php?id=' . urlencode((string)$runtimeAuditAuthorId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if (isModuleEnabled('blog')) {
    $pages[] = ['label' => 'admin_blog_create_form', 'url' => $baseUrl . '/admin/blog_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($articleId !== false) {
    $pages[] = ['label' => 'admin_blog_form', 'url' => $baseUrl . '/admin/blog_form.php?id=' . urlencode((string)$articleId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if (isModuleEnabled('news')) {
    $pages[] = ['label' => 'admin_news_create_form', 'url' => $baseUrl . '/admin/news_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($newsId !== false) {
    $pages[] = ['label' => 'admin_news_form', 'url' => $baseUrl . '/admin/news_form.php?id=' . urlencode((string)$newsId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if (isModuleEnabled('events')) {
    $pages[] = ['label' => 'admin_event_create_form', 'url' => $baseUrl . '/admin/event_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($eventId !== false) {
    $pages[] = ['label' => 'admin_event_form', 'url' => $baseUrl . '/admin/event_form.php?id=' . urlencode((string)$eventId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($contactMessageId !== false) {
    $pages[] = ['label' => 'admin_contact_message', 'url' => $baseUrl . '/admin/contact_message.php?id=' . urlencode((string)$contactMessageId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($chatMessageId !== false) {
    $pages[] = ['label' => 'admin_chat_message', 'url' => $baseUrl . '/admin/chat_message.php?id=' . urlencode((string)$chatMessageId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($newsletterPendingSubscriberId !== false) {
    $pages[] = ['label' => 'admin_newsletter_subscriber', 'url' => $baseUrl . '/admin/newsletter_subscriber.php?id=' . urlencode((string)$newsletterPendingSubscriberId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($newsletterHistoryId !== false) {
    $pages[] = ['label' => 'admin_newsletter_history', 'url' => $baseUrl . '/admin/newsletter_history.php?id=' . urlencode((string)$newsletterHistoryId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($pageId !== false) {
    $pages[] = ['label' => 'admin_page_form', 'url' => $baseUrl . '/admin/page_form.php?id=' . urlencode((string)$pageId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
 $pages[] = ['label' => 'admin_page_create_form', 'url' => $baseUrl . '/admin/page_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId];
if ($runtimeAuditFormId > 0) {
    $pages[] = ['label' => 'admin_form_edit', 'url' => $baseUrl . '/admin/form_form.php?id=' . urlencode((string)$runtimeAuditFormId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
    $pages[] = ['label' => 'admin_form_submissions', 'url' => $baseUrl . '/admin/form_submissions.php?id=' . urlencode((string)$runtimeAuditFormId) . '&status=all', 'cookie' => 'PHPSESSID=' . $auditSessionId];
    if ($runtimeAuditFormSubmissionId > 0) {
        $pages[] = [
            'label' => 'admin_form_submission_detail',
            'url' => $baseUrl . '/admin/form_submission.php?id=' . urlencode((string)$runtimeAuditFormSubmissionId)
                . '&form_id=' . urlencode((string)$runtimeAuditFormId),
            'cookie' => 'PHPSESSID=' . $auditSessionId,
        ];
    }
    $pages[] = ['label' => 'public_form', 'url' => $runtimeAuditFormPath];
}
if ($boardId !== false) {
    $pages[] = ['label' => 'admin_board_form', 'url' => $baseUrl . '/admin/board_form.php?id=' . urlencode((string)$boardId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
    $pages[] = ['label' => 'admin_board_create_form', 'url' => $baseUrl . '/admin/board_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($downloadId !== false) {
    $pages[] = ['label' => 'admin_download_form', 'url' => $baseUrl . '/admin/download_form.php?id=' . urlencode((string)$downloadId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
    $pages[] = ['label' => 'admin_download_create_form', 'url' => $baseUrl . '/admin/download_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($foodCardId !== false) {
    $pages[] = ['label' => 'admin_food_form', 'url' => $baseUrl . '/admin/food_form.php?id=' . urlencode((string)$foodCardId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
    $pages[] = ['label' => 'admin_food_create_form', 'url' => $baseUrl . '/admin/food_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($resourceRow) {
    $pages[] = ['label' => 'admin_res_resource_form', 'url' => $baseUrl . '/admin/res_resource_form.php?id=' . urlencode((string)$resourceRow['id']), 'cookie' => 'PHPSESSID=' . $auditSessionId];
    $pages[] = ['label' => 'admin_res_resource_create_form', 'url' => $baseUrl . '/admin/res_resource_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($galleryAlbumId !== false) {
    $pages[] = ['label' => 'admin_gallery_album_form', 'url' => $baseUrl . '/admin/gallery_album_form.php?id=' . urlencode((string)$galleryAlbumId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
    $pages[] = ['label' => 'admin_gallery_album_create_form', 'url' => $baseUrl . '/admin/gallery_album_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId];
    $pages[] = ['label' => 'admin_gallery_photos', 'url' => $baseUrl . '/admin/gallery_photos.php?album_id=' . urlencode((string)$galleryAlbumId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
    $pages[] = ['label' => 'admin_gallery_photo_create_form', 'url' => $baseUrl . '/admin/gallery_photo_form.php?album_id=' . urlencode((string)$galleryAlbumId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($galleryPhotoId !== false && $galleryPhotoAlbumId !== false) {
    $pages[] = ['label' => 'admin_gallery_photo_form', 'url' => $baseUrl . '/admin/gallery_photo_form.php?id=' . urlencode((string)$galleryPhotoId) . '&album_id=' . urlencode((string)$galleryPhotoAlbumId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($faqId !== false) {
    $pages[] = ['label' => 'admin_faq_form', 'url' => $baseUrl . '/admin/faq_form.php?id=' . urlencode((string)$faqId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
    $pages[] = ['label' => 'admin_faq_create_form', 'url' => $baseUrl . '/admin/faq_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($placeId !== false) {
    $pages[] = ['label' => 'admin_place_form', 'url' => $baseUrl . '/admin/place_form.php?id=' . urlencode((string)$placeId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
    $pages[] = ['label' => 'admin_place_create_form', 'url' => $baseUrl . '/admin/place_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($pollId !== false) {
    $pages[] = ['label' => 'admin_polls_form', 'url' => $baseUrl . '/admin/polls_form.php?id=' . urlencode((string)$pollId), 'cookie' => 'PHPSESSID=' . $auditSessionId];
    $pages[] = ['label' => 'admin_polls_create_form', 'url' => $baseUrl . '/admin/polls_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if (isModuleEnabled('podcast')) {
    $pages[] = ['label' => 'admin_podcast_shows', 'url' => $baseUrl . '/admin/podcast_shows.php', 'cookie' => 'PHPSESSID=' . $auditSessionId];
    $pages[] = ['label' => 'admin_podcast_show_create_form', 'url' => $baseUrl . '/admin/podcast_show_form.php', 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($podcastShowRow) {
    $pages[] = ['label' => 'admin_podcast', 'url' => $baseUrl . '/admin/podcast.php?show_id=' . urlencode((string)$podcastShowRow['id']), 'cookie' => 'PHPSESSID=' . $auditSessionId];
    $pages[] = ['label' => 'admin_podcast_show_form', 'url' => $baseUrl . '/admin/podcast_show_form.php?id=' . urlencode((string)$podcastShowRow['id']), 'cookie' => 'PHPSESSID=' . $auditSessionId];
    $pages[] = ['label' => 'admin_podcast_create_form', 'url' => $baseUrl . '/admin/podcast_form.php?show_id=' . urlencode((string)$podcastShowRow['id']), 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
if ($podcastEpisodeId !== false && $podcastShowRow) {
    $pages[] = [
        'label' => 'admin_podcast_form',
        'url' => $baseUrl . '/admin/podcast_form.php?id=' . urlencode((string)$podcastEpisodeId) . '&show_id=' . urlencode((string)$podcastShowRow['id']),
        'cookie' => 'PHPSESSID=' . $auditSessionId,
    ];
}

if (isModuleEnabled('newsletter')) {
    $pages[] = ['label' => 'subscribe_confirm', 'url' => $baseUrl . '/subscribe_confirm.php?token=' . urlencode($subscribeConfirmToken)];
    $pages[] = ['label' => 'unsubscribe', 'url' => $baseUrl . '/unsubscribe.php?token=' . urlencode($unsubscribeToken)];
}

if (isModuleEnabled('blog')) {
    $pages[] = ['label' => 'blog_index', 'url' => $baseUrl . '/blog/index.php'];
    if ($runtimeAuditAuthorSlug !== '') {
        $pages[] = ['label' => 'blog_author_filter', 'url' => $baseUrl . '/blog/index.php?autor=' . urlencode($runtimeAuditAuthorSlug)];
    }
    if (!empty($articleRow['blog_slug'])) {
        $pages[] = ['label' => 'blog_feed', 'url' => $baseUrl . '/feed.php?blog=' . urlencode((string)$articleRow['blog_slug'])];
    }
}
if (isModuleEnabled('board')) {
    $pages[] = ['label' => 'board_index', 'url' => $baseUrl . '/board/index.php'];
}
$pages[] = ['label' => 'downloads_index', 'url' => $baseUrl . '/downloads/index.php'];
if (isModuleEnabled('events')) {
    $pages[] = ['label' => 'events_index', 'url' => $baseUrl . '/events/index.php'];
}
if (isModuleEnabled('faq')) {
    $pages[] = ['label' => 'faq_index', 'url' => $baseUrl . '/faq/index.php'];
}
if (isModuleEnabled('food')) {
    $pages[] = ['label' => 'food', 'url' => $baseUrl . '/food/index.php'];
    $pages[] = ['label' => 'food_archive', 'url' => $baseUrl . '/food/archive.php'];
    if ($foodCardCanonicalUrl !== '') {
        $pages[] = ['label' => 'food_card', 'url' => $foodCardCanonicalUrl];
    }
}
if (isModuleEnabled('gallery')) {
    $pages[] = ['label' => 'gallery_index', 'url' => $baseUrl . '/gallery/index.php'];
    if ($galleryAlbumCanonicalUrl !== '') {
        $pages[] = ['label' => 'gallery_album', 'url' => $galleryAlbumCanonicalUrl];
    }
    if ($galleryPhotoCanonicalUrl !== '') {
        $pages[] = ['label' => 'gallery_photo', 'url' => $galleryPhotoCanonicalUrl];
    }
}
if (isModuleEnabled('news')) {
    $pages[] = ['label' => 'news_index', 'url' => $baseUrl . '/news/index.php'];
}
if (isModuleEnabled('places')) {
    $pages[] = ['label' => 'places_index', 'url' => $baseUrl . '/places/index.php'];
}
if (isModuleEnabled('podcast')) {
    $pages[] = ['label' => 'podcast_index', 'url' => $baseUrl . '/podcast/index.php'];
    if ($podcastShowSlug) {
        $pages[] = ['label' => 'podcast_show', 'url' => $baseUrl . '/podcast/' . urlencode((string)$podcastShowSlug)];
        $pages[] = ['label' => 'podcast_feed', 'url' => $baseUrl . '/podcast/feed.php?slug=' . urlencode((string)$podcastShowSlug)];
    }
}
if (isModuleEnabled('polls')) {
    $pages[] = ['label' => 'polls_index', 'url' => $baseUrl . '/polls/index.php'];
    if ($pollCanonicalUrl !== '') {
        $pages[] = ['label' => 'polls_detail', 'url' => $pollCanonicalUrl];
    }
}

if ($articleCanonicalUrl !== '') {
    $pages[] = ['label' => 'blog_article', 'url' => $articleCanonicalUrl];
}
if ($newsCanonicalUrl !== '') {
    $pages[] = ['label' => 'news_article', 'url' => $newsCanonicalUrl];
}
if ($eventCanonicalUrl !== '') {
    $pages[] = ['label' => 'events_article', 'url' => $eventCanonicalUrl];
}
if ($boardCanonicalUrl !== '') {
    $pages[] = ['label' => 'board_article', 'url' => $boardCanonicalUrl];
}
if ($downloadCanonicalUrl !== '') {
    $pages[] = ['label' => 'downloads_article', 'url' => $downloadCanonicalUrl];
}
if ($placeCanonicalUrl !== '') {
    $pages[] = ['label' => 'places_article', 'url' => $placeCanonicalUrl];
}
if ($faqCanonicalUrl !== '') {
    $pages[] = ['label' => 'faq_article', 'url' => $faqCanonicalUrl];
}
if ($podcastEpisodeCanonicalUrl !== '') {
    $pages[] = ['label' => 'podcast_episode', 'url' => $podcastEpisodeCanonicalUrl];
}
if ($runtimeAuditAuthorUrl !== '') {
    $pages[] = ['label' => 'authors_index', 'url' => $baseUrl . authorIndexRequestPath()];
    $pages[] = ['label' => 'public_author', 'url' => $runtimeAuditAuthorUrl];
}
if ($pageRow) {
    $pages[] = ['label' => 'static_page', 'url' => $baseUrl . pagePublicRequestPath($pageRow)];
}
if ($resourceSlug) {
    $pages[] = ['label' => 'reservations_index', 'url' => $baseUrl . '/reservations/index.php'];
    $pages[] = ['label' => 'reservations_resource', 'url' => $baseUrl . '/reservations/resource.php?slug=' . urlencode((string)$resourceSlug)];
    $pages[] = ['label' => 'reservations_my', 'url' => $baseUrl . '/reservations/my.php', 'cookie' => 'PHPSESSID=' . $publicAuditSessionId];
    if ($reservationsBookDate) {
        $pages[] = [
            'label' => 'reservations_book',
            'url' => $baseUrl . '/reservations/book.php?slug=' . urlencode((string)$resourceSlug) . '&date=' . urlencode($reservationsBookDate),
            'cookie' => 'PHPSESSID=' . $publicAuditSessionId,
        ];
    }
}

/**
 * @return array{status:string,headers:array<int,string>,body:string}
 */
function fetchUrl(string $url, string $cookie = '', int $maxRedirects = 20): array
{
    $headers = [
        'User-Agent: KoraRuntimeAudit/1.0',
    ];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers) . "\r\n",
            'ignore_errors' => true,
            'timeout' => 15,
            'follow_location' => $maxRedirects > 0 ? 1 : 0,
            'max_redirects' => $maxRedirects,
        ],
    ]);

    $body = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = $responseHeaders[0] ?? 'HTTP status unknown';

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => is_string($body) ? $body : '',
    ];
}

/**
 * @param array<string,string> $fields
 * @return array{status:string,headers:array<int,string>,body:string}
 */
function postUrl(string $url, array $fields, string $cookie = '', int $maxRedirects = 20): array
{
    $headers = [
        'User-Agent: KoraRuntimeAudit/1.0',
        'Content-Type: application/x-www-form-urlencoded',
    ];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => http_build_query($fields),
            'ignore_errors' => true,
            'timeout' => 15,
            'follow_location' => $maxRedirects > 0 ? 1 : 0,
            'max_redirects' => $maxRedirects,
        ],
    ]);

    $body = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = $responseHeaders[0] ?? 'HTTP status unknown';

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => is_string($body) ? $body : '',
    ];
}

/**
 * @param array<string,string> $fields
 * @param array<string,array{path:string,filename:string,type?:string}> $files
 * @return array{status:string,headers:array<int,string>,body:string}
 */
function postMultipartUrl(string $url, array $fields, array $files, string $cookie = '', int $maxRedirects = 20): array
{
    $boundary = '----KoraRuntimeAudit' . bin2hex(random_bytes(8));
    $eol = "\r\n";
    $body = '';

    foreach ($fields as $fieldName => $fieldValue) {
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="' . $fieldName . '"' . $eol . $eol;
        $body .= $fieldValue . $eol;
    }

    foreach ($files as $fieldName => $file) {
        $contents = (string)file_get_contents($file['path']);
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="' . $fieldName . '"; filename="' . $file['filename'] . '"' . $eol;
        $body .= 'Content-Type: ' . ($file['type'] ?? 'application/octet-stream') . $eol . $eol;
        $body .= $contents . $eol;
    }

    $body .= '--' . $boundary . '--' . $eol;

    $headers = [
        'User-Agent: KoraRuntimeAudit/1.0',
        'Content-Type: multipart/form-data; boundary=' . $boundary,
    ];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 20,
            'follow_location' => $maxRedirects > 0 ? 1 : 0,
            'max_redirects' => $maxRedirects,
        ],
    ]);

    $responseBody = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = $responseHeaders[0] ?? 'HTTP status unknown';

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => is_string($responseBody) ? $responseBody : '',
    ];
}

/**
 * @return list<string>
 */
function analyzeHtml(string $html): array
{
    $issues = [];
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $idCounts = [];
    foreach ($xpath->query('//*[@id]') as $node) {
        $id = $node->getAttribute('id');
        $idCounts[$id] = ($idCounts[$id] ?? 0) + 1;
    }
    foreach ($idCounts as $id => $count) {
        if ($count > 1) {
            $issues[] = "duplicate id: {$id} ({$count}x)";
        }
    }

    foreach (['aria-describedby', 'aria-labelledby', 'aria-controls'] as $attr) {
        foreach ($xpath->query('//*[@' . $attr . ']') as $node) {
            $targets = preg_split('/\s+/', trim($node->getAttribute($attr))) ?: [];
            foreach ($targets as $targetId) {
                if ($targetId !== '' && !isset($idCounts[$targetId])) {
                    $issues[] = "{$attr} missing target: {$targetId}";
                }
            }
        }
    }

    foreach ($xpath->query('//label[.//small] | //legend[.//small]') as $node) {
        $issues[] = strtolower($node->nodeName) . ' contains nested helper text';
    }

    $describedByRefs = [];
    foreach ($xpath->query('//*[@aria-describedby]') as $node) {
        $targets = preg_split('/\s+/', trim($node->getAttribute('aria-describedby'))) ?: [];
        foreach ($targets as $targetId) {
            if ($targetId !== '') {
                $describedByRefs[$targetId] = true;
            }
        }
    }

    foreach ($xpath->query('//*[@id and (contains(concat(" ", normalize-space(@class), " "), " field-help ") or contains(concat(" ", normalize-space(@class), " "), " help-text "))]') as $node) {
        $targetId = $node->getAttribute('id');
        if ($targetId === '') {
            continue;
        }
        if ($node->hasAttribute('aria-live') || $node->hasAttribute('data-selection-status')) {
            continue;
        }
        if (!isset($describedByRefs[$targetId])) {
            $issues[] = 'helper text without aria-describedby reference: ' . $targetId;
        }
    }

    foreach ($xpath->query('//img') as $img) {
        if (!$img->hasAttribute('alt')) {
            $issues[] = 'img without alt';
        }
    }

    $fields = $xpath->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="button") and not(@type="reset")] | //select | //textarea');
    foreach ($fields as $field) {
        $id = $field->getAttribute('id');
        if ($id === '') {
            continue;
        }
        $labels = $xpath->query('//label[@for="' . $id . '"]');
        $wrappedLabel = $xpath->query('ancestor::label[1]', $field);
        if ($labels->length === 0 && $wrappedLabel->length === 0 && !$field->hasAttribute('aria-label')) {
            $issues[] = "field without label: #{$id}";
        }
    }

    if (str_contains($html, 'Warning:') || str_contains($html, 'Fatal error:')) {
        $issues[] = 'php warning/error rendered in HTML';
    }

    if (str_contains($html, 'class="skip-link"') && !str_contains($html, '.skip-link')) {
        $issues[] = 'skip-link without CSS definition';
    }

    if (str_contains($html, 'class="sr-only"') && !str_contains($html, '.sr-only')) {
        $issues[] = 'sr-only helper without CSS definition';
    }

    if (str_contains($html, 'class="visually-hidden"') && !str_contains($html, '.visually-hidden')) {
        $issues[] = 'visually-hidden helper without CSS definition';
    }


    if (str_contains($html, '<style>.visually-hidden{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}</style>')) {
        $issues[] = 'page-local visually-hidden style should use shared admin helper';
    }

    if (str_contains($html, 'style="color:#c60"')) {
        $issues[] = 'low-contrast pending status style detected';
    }

    if (str_contains($html, 'background:#060;color:#fff')) {
        $issues[] = 'legacy approve button style detected';
    }
    $tabs = $xpath->query('//*[@role="tab"]');
    if ($tabs->length > 0) {
        $selectedCount = 0;
        foreach ($tabs as $tab) {
            if (!$tab->hasAttribute('tabindex')) {
                $issues[] = 'tab without tabindex';
            }
            if (strtolower($tab->nodeName) === 'button' && strtolower($tab->getAttribute('type')) !== 'button') {
                $issues[] = 'tab button missing type=button';
            }
            if ($tab->getAttribute('aria-selected') === 'true') {
                $selectedCount++;
            }
        }

        if ($selectedCount !== 1) {
            $issues[] = 'tablist must have exactly one selected tab';
        }

        $panels = $xpath->query('//*[@role="tabpanel"]');
        if ($panels->length !== $tabs->length) {
            $issues[] = 'tab/panel count mismatch';
        }
    }

    return array_values(array_unique($issues));
}

/**
 * @param array<int,string> $headers
 * @return list<string>
 */
function analyzeHeaders(array $headers): array
{
    $required = [
        'X-Content-Type-Options:',
        'X-Frame-Options:',
        'Referrer-Policy:',
        'Content-Security-Policy:',
    ];

    $issues = [];
    foreach ($required as $prefix) {
        $found = false;
        foreach ($headers as $header) {
            if (stripos($header, $prefix) === 0) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $issues[] = 'missing header: ' . rtrim($prefix, ':');
        }
    }
    return $issues;
}

function responseHasLocationHeader(array $headers, string $expectedPath, string $baseUrl = ''): bool
{
    $expectedAbsolute = rtrim($baseUrl, '/') . $expectedPath;
    if (isset($headers['Location'])) {
        $headerValue = $headers['Location'];
        $locations = is_array($headerValue) ? $headerValue : [$headerValue];
        foreach ($locations as $location) {
            $location = trim((string)$location);
            if ($location === $expectedPath || $location === $expectedAbsolute) {
                return true;
            }
            $parsedPath = (string)(parse_url($location, PHP_URL_PATH) ?? '');
            $parsedQuery = (string)(parse_url($location, PHP_URL_QUERY) ?? '');
            $normalizedLocation = $parsedPath . ($parsedQuery !== '' ? '?' . $parsedQuery : '');
            if ($normalizedLocation === $expectedPath) {
                return true;
            }
        }
    }

    foreach ($headers as $header) {
        if (stripos($header, 'Location:') !== 0) {
            continue;
        }

        $location = trim(substr($header, 9));
        if ($location === $expectedPath || $location === $expectedAbsolute) {
            return true;
        }

        $parsedPath = (string)(parse_url($location, PHP_URL_PATH) ?? '');
        $parsedQuery = (string)(parse_url($location, PHP_URL_QUERY) ?? '');
        $normalizedLocation = $parsedPath . ($parsedQuery !== '' ? '?' . $parsedQuery : '');
        if ($normalizedLocation === $expectedPath) {
            return true;
        }
    }

    return false;
}

/**
 * @return list<string>
 */
function analyzeUxHeuristics(string $html, string $label): array
{
    $issues = [];
    if ($label === 'podcast_feed') {
        return $issues;
    }
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $skipLinks = $xpath->query('//a[@href="#obsah" and contains(concat(" ", normalize-space(@class), " "), " skip-link ")]');
    if ($skipLinks->length === 0) {
        $issues[] = 'missing skip link to #obsah';
    }

    $mainNodes = $xpath->query('//main[@id="obsah"]');
    if ($mainNodes->length === 0) {
        $issues[] = 'missing main#obsah landmark';
    } elseif ($mainNodes->length > 1) {
        $issues[] = 'multiple main#obsah landmarks';
    }

    $h1Nodes = $xpath->query('//h1');
    if ($h1Nodes->length === 0) {
        $issues[] = 'missing h1 heading';
    } elseif ($h1Nodes->length > 1) {
        $issues[] = 'multiple h1 headings';
    }

    foreach ($xpath->query('//h1 | //h2 | //h3') as $heading) {
        if (trim($heading->textContent) === '') {
            $issues[] = 'empty heading element';
            break;
        }
    }

    foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " section-subtitle ")]') as $subtitle) {
        if (trim($subtitle->textContent) === '') {
            $issues[] = 'empty section subtitle';
            break;
        }
    }

    if ($label === 'home') {
        $homeSections = $xpath->query('//*[@data-home-section] | //*[contains(concat(" ", normalize-space(@class), " "), " home-section ")]');
        $homeFallback = $xpath->query('//*[@id="obsah-priprava"]');
        if ($homeSections->length === 0 && $homeFallback->length === 0) {
            $issues[] = 'home missing content sections and fallback state';
        }

        $ctaSections = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " home-section--cta ")]');
        if ($ctaSections->length > 1) {
            $issues[] = 'home renders multiple CTA sections';
        }
    }

    return array_values(array_unique($issues));
}

function extractHiddenInputValue(string $html, string $name): string
{
    $pattern = '/<input[^>]+name="' . preg_quote($name, '/') . '"[^>]+value="([^"]*)"/i';
    if (preg_match($pattern, $html, $matches) === 1) {
        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return '';
}

function extractCaptchaAnswer(string $html): ?int
{
    if (preg_match('/<label[^>]+for="captcha"[^>]*>.*?(\d+)[^0-9]+(\d+)\?.*?<\/label>/isu', $html, $matches) === 1) {
        return ((int)$matches[1]) * ((int)$matches[2]);
    }

    return null;
}

$failures = 0;

foreach ($pages as $page) {
    $result = fetchUrl($page['url'], $page['cookie'] ?? '');
    $issues = [];

    if (!str_contains($result['status'], '200')) {
        $issues[] = 'unexpected status: ' . $result['status'];
    }
    $isXmlPage = in_array($page['label'], ['sitemap_php', 'sitemap_xml'], true);
    if (!$isXmlPage) {
        $issues = array_merge($issues, analyzeHtml($result['body']));
        $issues = array_merge($issues, analyzeUxHeuristics($result['body'], $page['label']));
    }

    if ($page['label'] === 'public_login') {
        $issues = array_merge($issues, analyzeHeaders($result['headers']));

        $probe = fetchUrl($baseUrl . '/public_login.php?redirect=' . rawurlencode('https://example.com/phish'));
        if (preg_match('/name="redirect"\s+value="([^"]*)"/', $probe['body'], $matches) === 1
            && $matches[1] === 'https://example.com/phish') {
            $issues[] = 'external redirect leaked into login form';
        }
    }

    if ($page['label'] === 'home' && getSetting('visitor_counter_enabled', '0') === '1') {
        if (!str_contains($result['body'], 'class="visitor-counter__item"')) {
            $issues[] = 'visitor counter does not expose individual statistic items';
        }
        if (str_contains($result['body'], ' · Dnes:') || str_contains($result['body'], ' · Měsíc:') || str_contains($result['body'], ' · Celkem:')) {
            $issues[] = 'visitor counter still uses visual dot separators in footer output';
        }
    }

    if ($page['label'] === 'home') {
        foreach ([
            'Featured modul',
            'Další kroky',
            'Co chcete udělat dál?',
            'Rychlé akce pomohou návštěvníkovi dostat se k důležitému obsahu bez zbytečného hledání.',
            'Přejít na blog',
        ] as $legacySnippet) {
            if (str_contains($result['body'], $legacySnippet)) {
                $issues[] = 'home still contains legacy copy: ' . $legacySnippet;
            }
        }
        if (str_contains($result['body'], 'data-home-section="author"')) {
            $issues[] = 'home still renders deprecated author section';
        }
    }

    if ($page['label'] === 'home' && str_contains($result['body'], '/uploads/board/')) {
        $issues[] = 'home board links still expose uploads/board paths';
    }
    if ($page['label'] === 'home') {
        $redundantHomeSnippets = [
            'class="section-kicker">Vítejte</p>' => 'Vítejte',
            'class="section-title section-title--hero">Úvodní stránka</h2>' => 'Úvodní stránka',
            'class="section-kicker">Užitečné odkazy</p>' => 'Užitečné odkazy',
            'class="section-kicker">Doporučujeme</p>' => 'Doporučujeme',
            'class="section-kicker">Aktuálně</p>' => 'Aktuálně',
        ];
        $redundantHomeSnippets = array_filter(
            $redundantHomeSnippets,
            static fn(string $label, string $snippet): bool => !str_contains($snippet, 'section-title section-title--hero'),
            ARRAY_FILTER_USE_BOTH
        );
        foreach ($redundantHomeSnippets as $snippet => $label) {
            if (str_contains($result['body'], $snippet)) {
                $issues[] = 'home still contains redundant section copy: ' . $label;
            }
        }
    }
    if (
        $page['label'] === 'home'
        && $boardCanonicalPath !== ''
        && (
            str_contains($result['body'], 'data-home-section="board"')
            || str_contains($result['body'], 'data-feature-source="board"')
        )
        && !str_contains($result['body'], $boardCanonicalPath)
    ) {
        $issues[] = 'home is missing board detail links';
    }

    if ($page['label'] === 'board_index') {
        if (str_contains($result['body'], '/uploads/board/')) {
            $issues[] = 'board listing still exposes uploads/board paths';
        }
        if ($boardCanonicalPath !== '' && !str_contains($result['body'], $boardCanonicalPath)) {
            $issues[] = 'board listing is missing detail links';
        }
        if ($boardRow && !str_contains($result['body'], (string)($boardRow['excerpt_plain'] ?? ''))) {
            $issues[] = 'board listing is missing excerpt preview';
        }
        if ($boardRow && !str_contains($result['body'], (string)($boardRow['contact_email'] ?? ''))) {
            $issues[] = 'board listing is missing contact email';
        }
        if ($boardAttachmentId !== false && !str_contains($result['body'], '/board/file.php?id=' . (int)$boardAttachmentId)) {
            $issues[] = 'board listing is missing file endpoint links';
        }
    }

    if ($page['label'] === 'places_index') {
        if ($placeCanonicalPath !== '' && !str_contains($result['body'], $placeCanonicalPath)) {
            $issues[] = 'places listing is missing detail links';
        }
        if ($placeRow && !str_contains($result['body'], (string)($placeRow['excerpt_plain'] ?? ''))) {
            $issues[] = 'places listing is missing excerpt preview';
        }
        if ($placeRow && !str_contains($result['body'], (string)($placeRow['address'] ?? ''))) {
            $issues[] = 'places listing is missing address';
        }
    }

    if ($page['label'] === 'search') {
        foreach (['Navigace obsahem', 'Hledání pro „'] as $legacySnippet) {
            if (str_contains($result['body'], $legacySnippet)) {
                $issues[] = 'search still contains legacy copy: ' . $legacySnippet;
            }
        }
    }

    if ($page['label'] === 'public_author' && str_contains($result['body'], 'Publikace')) {
        $issues[] = 'public author page still contains redundant publications kicker';
    }

    if ($page['label'] === 'podcast_episode') {
        if (str_contains($result['body'], 'Detail epizody')) {
            $issues[] = 'podcast episode still uses generic detail heading';
        }
        if (str_contains($result['body'], 'class="section-kicker">Obsah</p>')) {
            $issues[] = 'podcast episode still contains generic content kicker';
        }
        if (str_contains($result['body'], 'Epizoda podcastu')) {
            $issues[] = 'podcast episode still contains redundant kicker';
        }
    }

    if ($page['label'] === 'gallery_album') {
        foreach (['Struktura', 'Obsah alba', 'Podsložky'] as $legacySnippet) {
            if (str_contains($result['body'], $legacySnippet)) {
                $issues[] = 'gallery album still contains technical section label: ' . $legacySnippet;
            }
        }
    }

    if ($page['label'] === 'downloads_index') {
        if (str_contains($result['body'], '/uploads/downloads/')) {
            $issues[] = 'downloads listing still exposes uploads/downloads paths';
        }
        if ($downloadCanonicalPath !== '' && !str_contains($result['body'], $downloadCanonicalPath)) {
            $issues[] = 'downloads listing is missing detail links';
        }
        if ($downloadRow && !str_contains($result['body'], (string)($downloadRow['excerpt_plain'] ?? ''))) {
            $issues[] = 'downloads listing is missing excerpt preview';
        }
        if ($downloadId !== false && !str_contains($result['body'], '/downloads/file.php?id=' . (int)$downloadId)) {
            $issues[] = 'downloads listing is missing file endpoint links';
        }
    }

    if ($page['label'] === 'admin_settings') {
        foreach ([
            'Nastavení webu',
            'Sekce nastavení webu',
            '#settings-homepage',
            '#settings-basics',
            '#settings-notifications',
            '#settings-analytics',
            '#settings-operation',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin settings is missing fragment: ' . $expectedFragment;
            }
        }
        if (!str_contains($result['body'], 'name="site_profile"')) {
            $issues[] = 'site profile setting is missing';
        }
        if (!str_contains($result['body'], 'name="apply_site_profile"')) {
            $issues[] = 'site profile preset toggle is missing';
        }
        if (!str_contains($result['body'], 'value="custom"')) {
            $issues[] = 'custom site profile option is missing';
        }
        if (!str_contains($result['body'], 'name="board_public_label"')) {
            $issues[] = 'board public label setting is missing';
        }
        if (!str_contains($result['body'], 'name="public_registration_enabled"')) {
            $issues[] = 'public registration toggle is missing';
        }
        if (!str_contains($result['body'], 'name="github_issues_enabled"')) {
            $issues[] = 'github issues bridge toggle is missing';
        }
        if (!str_contains($result['body'], 'name="github_issues_repository"')) {
            $issues[] = 'github issues repository setting is missing';
        }
        if (isModuleEnabled('blog')) {
            if (!str_contains($result['body'], 'name="blog_authors_index_enabled"')) {
                $issues[] = 'blog authors index setting is missing';
            }
            if (!str_contains($result['body'], 'name="comments_enabled"')) {
                $issues[] = 'comments enabled setting is missing';
            }
            if (!str_contains($result['body'], 'name="comment_moderation_mode"')) {
                $issues[] = 'comment moderation mode setting is missing';
            }
            if (!str_contains($result['body'], 'name="comment_close_days"')) {
                $issues[] = 'comment close days setting is missing';
            }
            if (!str_contains($result['body'], 'name="comment_notify_admin"')) {
                $issues[] = 'comment admin notification setting is missing';
            }
            if (!str_contains($result['body'], 'name="comment_notify_author_approve"')) {
                $issues[] = 'comment author approval notification setting is missing';
            }
            if (!str_contains($result['body'], 'name="comment_notify_email"')) {
                $issues[] = 'comment notification email setting is missing';
            }
            if (!str_contains($result['body'], 'name="comment_blocked_emails"')) {
                $issues[] = 'comment blocked emails setting is missing';
            }
            if (!str_contains($result['body'], 'name="comment_spam_words"')) {
                $issues[] = 'comment spam words setting is missing';
            }
        }
        foreach ([
            'Základní nastavení',
            'Počty položek na domovské stránce',
            'Cookie lišta (GDPR)',
        ] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin settings still contains outdated phrase: ' . $forbiddenFragment;
            }
        }
    }

    if ($page['label'] === 'admin_comments') {
        if (!str_contains($result['body'], '?filter=spam')) {
            $issues[] = 'spam filter is missing in comment moderation';
        }
        if (!str_contains($result['body'], '?filter=trash')) {
            $issues[] = 'trash filter is missing in comment moderation';
        }
        if (str_contains($result['body'], '<caption>Komentáře</caption>')) {
            if (!str_contains($result['body'], 'bulk-action-btn')) {
                $issues[] = 'comment moderation bulk actions helper is missing';
            }
            if (!str_contains($result['body'], 'data-selection-status=')) {
                $issues[] = 'comment moderation selection status is missing';
            }
            if (!str_contains($result['body'], '/admin/comment_action.php')) {
                $issues[] = 'single comment moderation action endpoint is missing';
            }
            if (!str_contains($result['body'], '/admin/comment_bulk.php')) {
                $issues[] = 'bulk comment moderation form is missing';
            }
        }
    }

    if ($page['label'] === 'admin_profile') {
        if (!str_contains($result['body'], 'name="author_public_enabled"')) {
            $issues[] = 'author public toggle is missing in admin profile';
        }
        if (!str_contains($result['body'], 'name="author_slug"')) {
            $issues[] = 'author slug field is missing in admin profile';
        }
        if (!str_contains($result['body'], 'name="author_bio"')) {
            $issues[] = 'author bio field is missing in admin profile';
        }
        if (!str_contains($result['body'], 'name="author_website"')) {
            $issues[] = 'author website field is missing in admin profile';
        }
        if (!str_contains($result['body'], 'name="author_avatar"')) {
            $issues[] = 'author avatar field is missing in admin profile';
        }
    }

    if ($page['label'] === 'admin_user_form') {
        if (!str_contains($result['body'], 'name="role"')) {
            $issues[] = 'role selector is missing in user form';
        }
        foreach (['public', 'author', 'editor', 'moderator', 'booking_manager', 'admin', 'collaborator'] as $roleValue) {
            if (!str_contains($result['body'], 'value="' . $roleValue . '"')) {
                $issues[] = 'user form is missing role option: ' . $roleValue;
            }
        }
        if (!str_contains($result['body'], 'name="author_public_enabled"')) {
            $issues[] = 'author public toggle is missing in user form';
        }
        if (!str_contains($result['body'], 'name="author_slug"')) {
            $issues[] = 'author slug field is missing in user form';
        }
        if (!str_contains($result['body'], 'name="author_bio"')) {
            $issues[] = 'author bio field is missing in user form';
        }
        if (!str_contains($result['body'], 'name="author_website"')) {
            $issues[] = 'author website field is missing in user form';
        }
        foreach ([
            'Upravit uživatelský účet',
            'Zpět na uživatele a role',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'user form is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_user_create_form') {
        if (!str_contains($result['body'], 'name="role"')) {
            $issues[] = 'role selector is missing in user create form';
        }
        foreach (['public', 'author', 'editor', 'moderator', 'booking_manager', 'admin'] as $roleValue) {
            if (!str_contains($result['body'], 'value="' . $roleValue . '"')) {
                $issues[] = 'user create form is missing role option: ' . $roleValue;
            }
        }
        if (str_contains($result['body'], 'value="collaborator"')) {
            $issues[] = 'legacy collaborator role is unexpectedly offered for new users';
        }
        foreach ([
            'Nový uživatelský účet',
            'Zpět na uživatele a role',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'user create form is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_users' && !str_contains($result['body'], 'Veřejný autor')) {
        $issues[] = 'public author badge is missing in user list';
    }

    $adminFormActionExpectations = [
        'admin_form_create' => ['Vytvořit formulář', 'Zrušit'],
        'admin_form_issue_preset' => ['Vytvořit formulář', 'Zrušit'],
        'admin_form_feature_preset' => ['Vytvořit formulář', 'Zrušit'],
        'admin_form_support_preset' => ['Vytvořit formulář', 'Zrušit'],
        'admin_form_contact_preset' => ['Vytvořit formulář', 'Zrušit'],
        'admin_form_content_report_preset' => ['Vytvořit formulář', 'Zrušit'],
        'admin_form_edit' => ['Uložit změny', 'Zrušit'],
        'admin_blog_form' => ['Uložit změny', 'Zrušit'],
        'admin_blog_create_form' => ['Přidat článek', 'Zrušit'],
        'admin_news_form' => ['Uložit změny', 'Zrušit'],
        'admin_news_create_form' => ['Přidat novinku', 'Zrušit'],
        'admin_event_form' => ['Uložit změny', 'Zrušit'],
        'admin_event_create_form' => ['Přidat událost', 'Zrušit'],
        'admin_user_form' => ['Uložit změny', 'Zrušit'],
        'admin_user_create_form' => ['Vytvořit účet', 'Zrušit'],
        'admin_page_form' => ['Uložit změny', 'Zrušit'],
        'admin_page_create_form' => ['Vytvořit stránku', 'Zrušit'],
        'admin_board_form' => ['Uložit změny', 'Zrušit'],
        'admin_board_create_form' => ['Přidat položku sekce', 'Zrušit'],
        'admin_download_form' => ['Uložit změny', 'Zrušit'],
        'admin_download_create_form' => ['Přidat položku ke stažení', 'Zrušit'],
        'admin_food_form' => ['Uložit změny', 'Zrušit'],
        'admin_food_create_form' => ['Přidat jídelní lístek', 'Zrušit'],
        'admin_res_resource_form' => ['Uložit změny', 'Zrušit'],
        'admin_res_resource_create_form' => ['Vytvořit zdroj rezervací', 'Zrušit'],
        'admin_gallery_album_form' => ['Uložit změny', 'Zrušit'],
        'admin_gallery_album_create_form' => ['Vytvořit album', 'Zrušit'],
        'admin_gallery_photo_form' => ['Uložit změny', 'Zrušit'],
        'admin_gallery_photo_create_form' => ['Nahrát fotografie', 'Zrušit'],
        'admin_faq_form' => ['Uložit změny', 'Zrušit'],
        'admin_faq_create_form' => ['Přidat otázku FAQ', 'Zrušit'],
        'admin_place_form' => ['Uložit změny', 'Zrušit'],
        'admin_place_create_form' => ['Přidat zajímavé místo', 'Zrušit'],
        'admin_polls_form' => ['Uložit změny', 'Zrušit'],
        'admin_polls_create_form' => ['Vytvořit anketu', 'Zrušit'],
        'admin_podcast_show_form' => ['Uložit změny', 'Zrušit'],
        'admin_podcast_show_create_form' => ['Vytvořit podcast', 'Zrušit'],
        'admin_podcast_form' => ['Uložit změny', 'Zrušit'],
        'admin_podcast_create_form' => ['Přidat epizodu podcastu', 'Zrušit'],
    ];
    if (isset($adminFormActionExpectations[$page['label']])) {
        foreach ($adminFormActionExpectations[$page['label']] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin form is missing action fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_page_positions') {
        foreach (['name="dir" value="up"', 'name="dir" value="down"', 'page_form.php?id='] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin page positions is missing action fragment: ' . $expectedFragment;
            }
        }
    }

    $adminFormForbiddenFragments = [
        'admin_form_create' => ['>Uložit formulář<', 'Formulář je aktivní'],
        'admin_form_issue_preset' => ['>Uložit formulář<', 'Formulář je aktivní'],
        'admin_form_feature_preset' => ['>Uložit formulář<', 'Formulář je aktivní'],
        'admin_form_support_preset' => ['>Uložit formulář<', 'Formulář je aktivní'],
        'admin_form_contact_preset' => ['>Uložit formulář<', 'Formulář je aktivní'],
        'admin_form_content_report_preset' => ['>Uložit formulář<', 'Formulář je aktivní'],
        'admin_form_edit' => ['>Uložit formulář<', 'Vytvořit formulář'],
        'admin_event_form' => ['>Uložit<'],
        'admin_page_form' => ['>Uložit<'],
        'admin_user_form' => ['>Uložit<'],
        'admin_board_form' => ['>Uložit<'],
        'admin_place_form' => ['>Uložit<'],
        'admin_res_resource_form' => ['>Uložit<'],
        'admin_gallery_photo_create_form' => ['>Nahrát<'],
        'admin_download_create_form' => ['Přidat položku</button>'],
        'admin_food_create_form' => ['Přidat lístek</button>'],
        'admin_faq_create_form' => ['Přidat otázku</button>'],
        'admin_podcast_show_create_form' => ['Přidat podcast</button>'],
        'admin_podcast_create_form' => ['Přidat epizodu</button>'],
        'admin_place_create_form' => ['Přidat místo</button>'],
    ];
    if (isset($adminFormForbiddenFragments[$page['label']])) {
        foreach ($adminFormForbiddenFragments[$page['label']] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin form still contains outdated action text: ' . $forbiddenFragment;
            }
        }
    }

    $adminFormCopyExpectations = [
        'admin_form_create' => ['Nejdřív vytvořte základ formuláře.', 'Necháte-li prázdné, adresa se vytvoří automaticky podle názvu formuláře.', 'Zobrazí se návštěvníkovi tehdy, když po odeslání zůstane na stránce formuláře.', 'Režim po odeslání', 'Primární tlačítko po odeslání', 'Sekundární tlačítko po odeslání', 'Zveřejnit formulář na webu', 'Neaktivní formulář zůstane uložený, ale návštěvníci ho na webu neuvidí.'],
        'admin_form_issue_preset' => ['Šablona:', 'Nahlášení chyby', 'Po prvním uložení se automaticky přidají tato pole:', 'Použít antispam honeypot', 'Souhlas', 'Potvrzení odesílateli', 'Zařazení problému', 'Popis chyby', 'Přílohy a kontakt'],
        'admin_form_feature_preset' => ['Šablona:', 'Návrh nové funkce', 'Po prvním uložení se automaticky přidají tato pole:', 'Použít antispam honeypot', 'Souhlas', 'O návrhu', 'Popis a dopad'],
        'admin_form_support_preset' => ['Šablona:', 'Žádost o podporu', 'Po prvním uložení se automaticky přidají tato pole:', 'Použít antispam honeypot', 'Souhlas', 'Základ žádosti', 'Popis a co už jste zkusili'],
        'admin_form_contact_preset' => ['Šablona:', 'Obecný kontaktní formulář', 'Po prvním uložení se automaticky přidají tato pole:', 'Souhlas', 'Kontakt na vás', 'Vaše zpráva'],
        'admin_form_content_report_preset' => ['Šablona:', 'Nahlášení problému s obsahem', 'Po prvním uložení se automaticky přidají tato pole:', 'Souhlas', 'Kde je problém', 'Co je potřeba opravit'],
        'admin_form_edit' => ['Upravte nastavení formuláře, jeho pole, sekce, rozložení a to, co se má stát po úspěšném odeslání.', 'Text tlačítka pro odeslání', 'E-mail pro notifikaci', 'Předmět notifikačního e-mailu', 'Režim po odeslání', 'Primární tlačítko po odeslání', 'Sekundární tlačítko po odeslání', 'Povolené typy souborů', 'Max. velikost souboru (MB)', 'Odpovědi formuláře', 'Potvrzení odesílateli', 'Poslat odesílateli potvrzovací e-mail', 'Začít na novém řádku'],
        'admin_blog_form' => ['Adresa se vyplní automaticky, dokud ji neupravíte ručně.', 'Nechte prázdné, pokud se má článek zveřejnit hned.', 'Vložit odkaz nebo HTML z webu', 'Vyhledejte existující článek, stránku, médium nebo jiný veřejný obsah', 'Hledání prochází veřejně dostupný obsah webu i knihovnu médií.', '[audio]https://example.test/audio.mp3[/audio]'],
        'admin_blog_create_form' => ['Adresa se vyplní automaticky, dokud ji neupravíte ručně.', 'Nechte prázdné, pokud se má článek zveřejnit hned.', 'Vložit odkaz nebo HTML z webu', 'Vyhledejte existující článek, stránku, médium nebo jiný veřejný obsah', 'Hledání prochází veřejně dostupný obsah webu i knihovnu médií.', '[audio]https://example.test/audio.mp3[/audio]'],
        'admin_news_form' => ['Adresa se vyplní automaticky, dokud ji neupravíte ručně.'],
        'admin_news_create_form' => ['Adresa se vyplní automaticky, dokud ji neupravíte ručně.'],
        'admin_event_form' => ['Vyplňte potřebné údaje k této události.', 'Zveřejnit na webu'],
        'admin_event_create_form' => ['Vyplňte potřebné údaje k této události.', 'Zveřejnit na webu'],
        'admin_page_form' => ['Vyplňte základní údaje stránky a zvolte, jestli se má zobrazit na webu a v hlavní navigaci.', 'Zveřejnit na webu', 'Zobrazit v hlavní navigaci'],
        'admin_page_create_form' => ['Vyplňte základní údaje stránky a zvolte, jestli se má zobrazit na webu a v hlavní navigaci.', 'Zveřejnit na webu', 'Zobrazit v hlavní navigaci'],
        'admin_download_form' => ['Zveřejnit na webu', 'Můžete nahrát dokument, archiv nebo instalační balíček.'],
        'admin_download_create_form' => ['Zveřejnit na webu', 'Můžete nahrát dokument, archiv nebo instalační balíček.'],
        'admin_faq_form' => ['Vyplňte potřebné údaje k otázce a odpovědi.', 'Zveřejnit na webu'],
        'admin_faq_create_form' => ['Vyplňte potřebné údaje k otázce a odpovědi.', 'Zveřejnit na webu'],
        'admin_food_form' => ['Vyplňte potřebné údaje k tomuto lístku a pak zvolte, jestli má být aktuální a zveřejněný.', 'Použít jako aktuální lístek', 'Zveřejnit na webu', 'Nechte prázdné, pokud má lístek platit bez data konce.'],
        'admin_food_create_form' => ['Vyplňte potřebné údaje k tomuto lístku a pak zvolte, jestli má být aktuální a zveřejněný.', 'Použít jako aktuální lístek', 'Zveřejnit na webu', 'Nechte prázdné, pokud má lístek platit bez data konce.'],
        'admin_place_form' => ['Vyplňte základní údaje o místě a nakonec zvolte, jestli se má zobrazit na webu.', 'Zveřejnit na webu', 'Volitelné. Pomůže s filtrováním a orientací ve výpisu míst.'],
        'admin_place_create_form' => ['Vyplňte základní údaje o místě a nakonec zvolte, jestli se má zobrazit na webu.', 'Zveřejnit na webu', 'Volitelné. Pomůže s filtrováním a orientací ve výpisu míst.'],
        'admin_board_form' => ['Vyplňte potřebné údaje k položce a zvolte, jestli se má zveřejnit na webu.', 'Zveřejnit na webu', 'Nechte prázdné, pokud má položka zůstat bez data stažení.'],
        'admin_board_create_form' => ['Vyplňte potřebné údaje k položce a zvolte, jestli se má zveřejnit na webu.', 'Zveřejnit na webu', 'Nechte prázdné, pokud má položka zůstat bez data stažení.'],
        'admin_podcast_show_form' => ['Vyplňte základní údaje o podcastu.', 'Adresa se vyplní automaticky, dokud ji neupravíte ručně.', 'Počet epizod v RSS feedu', 'E-mail vlastníka feedu'],
        'admin_podcast_show_create_form' => ['Vyplňte základní údaje o podcastu.', 'Adresa se vyplní automaticky, dokud ji neupravíte ručně.', 'Počet epizod v RSS feedu', 'E-mail vlastníka feedu'],
        'admin_podcast_form' => ['Vyplňte základní údaje o epizodě.', 'Adresa se vyplní automaticky podle názvu epizody.', 'Nechte prázdné, pokud se má epizoda zveřejnit hned po uložení nebo schválení.', 'Krátký podtitul pro katalogy', 'Skrýt epizodu z RSS feedu'],
        'admin_podcast_create_form' => ['Vyplňte základní údaje o epizodě.', 'Adresa se vyplní automaticky podle názvu epizody.', 'Nechte prázdné, pokud se má epizoda zveřejnit hned po uložení nebo schválení.', 'Krátký podtitul pro katalogy', 'Skrýt epizodu z RSS feedu'],
        'admin_polls_form' => ['Vyplňte otázku, možnosti a případné časové omezení.', 'Vyplňte jen pokud má anketa začít nebo skončit v konkrétní čas.'],
        'admin_polls_create_form' => ['Vyplňte otázku, možnosti a případné časové omezení.', 'Vyplňte jen pokud má anketa začít nebo skončit v konkrétní čas.'],
        'admin_res_resource_form' => ['Vyplňte základní údaje o zdroji a pak nastavte způsob rezervací.', 'Například 24 znamená, že rezervaci je nutné vytvořit nejpozději den předem.'],
        'admin_res_resource_create_form' => ['Vyplňte základní údaje o zdroji a pak nastavte způsob rezervací.', 'Například 24 znamená, že rezervaci je nutné vytvořit nejpozději den předem.'],
        'admin_gallery_album_form' => ['Adresa se vyplní automaticky podle názvu alba.'],
        'admin_gallery_album_create_form' => ['Adresa se vyplní automaticky podle názvu alba.'],
        'admin_gallery_photo_form' => ['Adresa se vyplní automaticky podle titulku fotografie.'],
        'admin_gallery_photo_create_form' => ['Můžete vybrat více fotografií najednou.'],
        'admin_user_form' => ['Adresa autora se vyplní automaticky podle jména nebo přezdívky.'],
        'admin_user_create_form' => ['Adresa autora se vyplní automaticky podle jména nebo přezdívky.'],
    ];
    if (isset($adminFormCopyExpectations[$page['label']])) {
        foreach ($adminFormCopyExpectations[$page['label']] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin form is missing helper copy fragment: ' . $expectedFragment;
            }
        }
    }

    if (in_array($page['label'], ['admin_page_form', 'admin_page_create_form'], true) && !str_contains($result['body'], 'page_positions.php')) {
        $issues[] = 'admin page form is missing the page positions helper link';
    }

    if ($page['label'] === 'admin_page_positions') {
        foreach ([
            'page_reorder.php',
            '/admin/pages.php',
            '/admin/page_form.php',
            'name="dir" value="up"',
            'name="dir" value="down"',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin page positions is missing helper copy fragment: ' . $expectedFragment;
            }
        }
    }

    $contentReferencePickerLabels = [
        'admin_blog_form',
        'admin_blog_create_form',
        'admin_news_form',
        'admin_news_create_form',
        'admin_event_form',
        'admin_event_create_form',
        'admin_page_form',
        'admin_page_create_form',
        'admin_board_form',
        'admin_board_create_form',
        'admin_download_form',
        'admin_download_create_form',
        'admin_food_form',
        'admin_food_create_form',
        'admin_res_resource_form',
        'admin_res_resource_create_form',
        'admin_faq_form',
        'admin_faq_create_form',
        'admin_place_form',
        'admin_place_create_form',
        'admin_podcast_show_form',
        'admin_podcast_show_create_form',
        'admin_podcast_form',
        'admin_podcast_create_form',
        'admin_profile',
        'admin_user_form',
        'admin_user_create_form',
        'admin_settings',
    ];
    if (in_array($page['label'], $contentReferencePickerLabels, true)) {
        foreach ([
            'Vložit odkaz nebo HTML z webu',
            'Vyhledejte existující článek, stránku, médium nebo jiný veřejný obsah',
            'Hledání prochází veřejně dostupný obsah webu i knihovnu médií.',
            'fotogalerii, obrázek nebo přehrávač',
            'value="media"',
            'Knihovna médií',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'content reference picker is missing fragment: ' . $expectedFragment;
            }
        }
        foreach ([
            'aria-haspopup="dialog"',
            'role="dialog"',
            'aria-modal="true"',
            'aria-expanded="false"',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'content reference picker is missing modal accessibility fragment: ' . $expectedFragment;
            }
        }
    }

    $htmlSnippetHelpLabels = [
        'admin_blog_form',
        'admin_blog_create_form',
        'admin_news_form',
        'admin_news_create_form',
        'admin_event_form',
        'admin_event_create_form',
        'admin_page_form',
        'admin_page_create_form',
        'admin_board_form',
        'admin_board_create_form',
        'admin_download_form',
        'admin_download_create_form',
        'admin_food_form',
        'admin_food_create_form',
        'admin_res_resource_form',
        'admin_res_resource_create_form',
        'admin_faq_form',
        'admin_faq_create_form',
        'admin_place_form',
        'admin_place_create_form',
        'admin_podcast_show_form',
        'admin_podcast_show_create_form',
        'admin_podcast_form',
        'admin_podcast_create_form',
        'admin_profile',
        'admin_user_form',
        'admin_user_create_form',
        'admin_settings',
    ];
    if (in_array($page['label'], $htmlSnippetHelpLabels, true)) {
        foreach ([
            '[audio]https://example.test/audio.mp3[/audio]',
            '[video]https://example.test/video.mp4[/video]',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'HTML snippet helper is missing fragment: ' . $expectedFragment;
            }
        }
        if (isModuleEnabled('gallery') && !str_contains($result['body'], '[gallery]slug-alba[/gallery]')) {
            $issues[] = 'HTML snippet helper is missing gallery shortcode example';
        }
    }

    $adminFormCopyForbiddenFragments = [
        'admin_blog_form' => ['<small id="blog-slug-help" class="field-help">Používejte malá písmena, číslice a pomlčky.</small>', 'Prázdné pole znamená publikování ihned.'],
        'admin_blog_create_form' => ['<small id="blog-slug-help" class="field-help">Používejte malá písmena, číslice a pomlčky.</small>', 'Prázdné pole znamená publikování ihned.'],
        'admin_news_form' => ['<small id="news-slug-help" class="field-help">Používejte malá písmena, číslice a pomlčky.</small>'],
        'admin_news_create_form' => ['<small id="news-slug-help" class="field-help">Používejte malá písmena, číslice a pomlčky.</small>'],
        'admin_event_form' => ['>Publikováno<'],
        'admin_event_create_form' => ['>Publikováno<'],
        'admin_page_form' => ['>Publikováno<', '>Zobrazit v navigaci<'],
        'admin_page_create_form' => ['>Publikováno<', '>Zobrazit v navigaci<'],
        'admin_download_form' => ['Použije se ve veřejné adrese'],
        'admin_download_create_form' => ['Použije se ve veřejné adrese'],
        'admin_food_form' => ['>Zobrazit v archivu<', 'Označit jako aktuální lístek'],
        'admin_food_create_form' => ['>Zobrazit v archivu<', 'Označit jako aktuální lístek'],
        'admin_place_form' => ['<small id="place-category-help" class="field-help">Nepovinné pole.</small>'],
        'admin_place_create_form' => ['<small id="place-category-help" class="field-help">Nepovinné pole.</small>'],
        'admin_polls_form' => ['<small id="poll-timing-help" class="field-help" style="margin-top:0">Nepovinné pole.</small>'],
        'admin_polls_create_form' => ['<small id="poll-timing-help" class="field-help" style="margin-top:0">Nepovinné pole.</small>'],
        'admin_board_form' => ['Prázdné pole znamená bez omezení.'],
        'admin_board_create_form' => ['Prázdné pole znamená bez omezení.'],
    ];
    if (isset($adminFormCopyForbiddenFragments[$page['label']])) {
        foreach ($adminFormCopyForbiddenFragments[$page['label']] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin form still contains outdated helper copy: ' . $forbiddenFragment;
            }
        }
    }

    if (in_array($page['label'], ['admin_page_form', 'admin_page_create_form'], true) && str_contains($result['body'], 'name="nav_order"')) {
        $issues[] = 'admin page form still exposes manual navigation order input';
    }

    if (in_array($page['label'], $htmlSnippetHelpLabels, true)) {
        foreach ([
            'Můžete použít HTML nebo Markdown.',
            'Podporuje HTML i Markdown syntaxi.',
        ] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin form still contains outdated HTML helper copy: ' . $forbiddenFragment;
            }
        }
    }

    $adminFormSectionExpectations = [
        'admin_form_create' => ['Základní údaje formuláře', 'Potvrzení odesílateli', 'Po odeslání formuláře'],
        'admin_form_issue_preset' => ['Základní údaje formuláře', 'Potvrzení odesílateli', 'Po odeslání formuláře'],
        'admin_form_feature_preset' => ['Základní údaje formuláře', 'Potvrzení odesílateli', 'Po odeslání formuláře'],
        'admin_form_support_preset' => ['Základní údaje formuláře', 'Potvrzení odesílateli', 'Po odeslání formuláře'],
        'admin_form_contact_preset' => ['Základní údaje formuláře', 'Potvrzení odesílateli', 'Po odeslání formuláře'],
        'admin_form_content_report_preset' => ['Základní údaje formuláře', 'Potvrzení odesílateli', 'Po odeslání formuláře'],
        'admin_form_edit' => ['Základní údaje formuláře', 'Potvrzení odesílateli', 'Po odeslání formuláře', 'Pole a sekce formuláře'],
        'admin_blog_form' => ['Základní údaje článku', 'Text článku', 'Vyhledání obsahu', 'Komentáře', 'Vyhledávače a sdílení'],
        'admin_blog_create_form' => ['Základní údaje článku', 'Text článku', 'Vyhledání obsahu', 'Komentáře', 'Vyhledávače a sdílení'],
        'admin_news_form' => ['Obsah novinky'],
        'admin_news_create_form' => ['Obsah novinky'],
        'admin_event_form' => ['Základní údaje události', 'Popis události'],
        'admin_event_create_form' => ['Základní údaje události', 'Popis události'],
        'admin_page_form' => ['Obsah a zobrazení stránky'],
        'admin_page_create_form' => ['Obsah a zobrazení stránky'],
        'admin_download_form' => ['Základní údaje položky', 'Náhled a zveřejnění'],
        'admin_download_create_form' => ['Základní údaje položky', 'Náhled a zveřejnění'],
        'admin_food_form' => ['Údaje o lístku', 'Aktualita a zveřejnění'],
        'admin_food_create_form' => ['Údaje o lístku', 'Aktualita a zveřejnění'],
        'admin_place_form' => ['Základní údaje místa', 'Poloha a kontakt', 'Obrázek a zveřejnění'],
        'admin_place_create_form' => ['Základní údaje místa', 'Poloha a kontakt', 'Obrázek a zveřejnění'],
        'admin_board_form' => ['Položka sekce', 'Příloha a zveřejnění'],
        'admin_board_create_form' => ['Položka sekce', 'Příloha a zveřejnění'],
        'admin_gallery_album_form' => ['Údaje o albu'],
        'admin_gallery_album_create_form' => ['Údaje o albu'],
        'admin_gallery_photo_form' => ['Údaje o fotografii'],
        'admin_gallery_photo_create_form' => ['Nahrání fotografií do alba'],
        'admin_podcast_show_form' => ['Základní údaje podcastu', 'Feed a katalogy podcastů', 'Popis a titulní obrázek'],
        'admin_podcast_show_create_form' => ['Základní údaje podcastu', 'Feed a katalogy podcastů', 'Popis a titulní obrázek'],
        'admin_podcast_form' => ['Základní údaje epizody', 'Audio a text epizody'],
        'admin_podcast_create_form' => ['Základní údaje epizody', 'Audio a text epizody'],
        'admin_polls_form' => ['Základní údaje ankety'],
        'admin_polls_create_form' => ['Základní údaje ankety'],
        'admin_res_resource_form' => ['Lokality rezervací', 'Způsob rezervací', 'Časy k rezervaci', 'Hromadné přidání slotů'],
        'admin_res_resource_create_form' => ['Lokality rezervací', 'Způsob rezervací', 'Časy k rezervaci', 'Hromadné přidání slotů'],
    ];
    if (isset($adminFormSectionExpectations[$page['label']])) {
        foreach ($adminFormSectionExpectations[$page['label']] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin form is missing section label: ' . $expectedFragment;
            }
        }
    }

    $adminFormSectionForbiddenFragments = [
        'admin_form_create' => ['<legend>Základní údaje</legend>'],
        'admin_form_issue_preset' => ['<legend>Základní údaje</legend>'],
        'admin_form_feature_preset' => ['<legend>Základní údaje</legend>'],
        'admin_form_support_preset' => ['<legend>Základní údaje</legend>'],
        'admin_form_contact_preset' => ['<legend>Základní údaje</legend>'],
        'admin_form_content_report_preset' => ['<legend>Základní údaje</legend>'],
        'admin_form_edit' => ['<legend>Základní údaje</legend>', '<legend>Pole formuláře</legend>'],
        'admin_blog_form' => ['<legend>Článek</legend>', '<legend>Tagy</legend>', '<legend>Obsah</legend>', '<legend>Diskuse</legend>', '<legend>SEO / Open Graph</legend>'],
        'admin_blog_create_form' => ['<legend>Článek</legend>', '<legend>Tagy</legend>', '<legend>Obsah</legend>', '<legend>Diskuse</legend>', '<legend>SEO / Open Graph</legend>'],
        'admin_news_form' => ['<legend>Novinka</legend>'],
        'admin_news_create_form' => ['<legend>Novinka</legend>'],
        'admin_event_form' => ['<legend>Událost</legend>', '<legend>Podrobnosti</legend>'],
        'admin_event_create_form' => ['<legend>Událost</legend>', '<legend>Podrobnosti</legend>'],
        'admin_page_form' => ['<legend>Vlastnosti stránky</legend>'],
        'admin_page_create_form' => ['<legend>Vlastnosti stránky</legend>'],
        'admin_download_form' => ['<legend>Položka ke stažení</legend>', '<legend>Náhled a zobrazení</legend>'],
        'admin_download_create_form' => ['<legend>Položka ke stažení</legend>', '<legend>Náhled a zobrazení</legend>'],
        'admin_food_form' => ['<legend>Lístek</legend>', '<legend>Publikování</legend>'],
        'admin_food_create_form' => ['<legend>Lístek</legend>', '<legend>Publikování</legend>'],
        'admin_place_form' => ['<legend>Místo</legend>', '<legend>Praktické informace</legend>', '<legend>Obrázek a zobrazení</legend>'],
        'admin_place_create_form' => ['<legend>Místo</legend>', '<legend>Praktické informace</legend>', '<legend>Obrázek a zobrazení</legend>'],
        'admin_board_form' => ['Položka modulu', '<legend>Příloha a zobrazení</legend>'],
        'admin_board_create_form' => ['Položka modulu', '<legend>Příloha a zobrazení</legend>'],
        'admin_gallery_album_form' => ['<legend>Album</legend>'],
        'admin_gallery_album_create_form' => ['<legend>Album</legend>'],
        'admin_gallery_photo_form' => ['<legend>Vlastnosti fotografie</legend>'],
        'admin_gallery_photo_create_form' => ['<legend>Nahrání fotografií</legend>'],
        'admin_podcast_show_form' => ['<legend>Pořad</legend>', '<legend>Popis a cover</legend>'],
        'admin_podcast_show_create_form' => ['<legend>Pořad</legend>', '<legend>Popis a cover</legend>'],
        'admin_podcast_form' => ['<legend>Epizoda</legend>', '<legend>Audio a popis</legend>'],
        'admin_podcast_create_form' => ['<legend>Epizoda</legend>', '<legend>Audio a popis</legend>'],
        'admin_polls_form' => ['<legend>Anketa</legend>'],
        'admin_polls_create_form' => ['<legend>Anketa</legend>'],
        'admin_res_resource_form' => ['<legend>Místa konání</legend>', '<legend>Režim slotů', '<legend>Předdefinované sloty</legend>', '<legend>Hromadný generátor</legend>'],
        'admin_res_resource_create_form' => ['<legend>Místa konání</legend>', '<legend>Režim slotů', '<legend>Předdefinované sloty</legend>', '<legend>Hromadný generátor</legend>'],
    ];
    if (isset($adminFormSectionForbiddenFragments[$page['label']])) {
        foreach ($adminFormSectionForbiddenFragments[$page['label']] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin form still contains outdated section label: ' . $forbiddenFragment;
            }
        }
    }

    if ($page['label'] === 'admin_index') {
        foreach ([
            'Na čem chcete pracovat',
            'Přehled administrace',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin dashboard is missing fragment: ' . $expectedFragment;
            }
        }
        foreach ([
            'Ostatní moduly',
            'Nejčastější akce',
            'Co můžete spravovat',
            'Dostupné sekce administrace',
            'Další části administrace',
            'Sekce, které máte k dispozici',
            'Otevřít přehled',
        ] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin dashboard still contains outdated phrase: ' . $forbiddenFragment;
            }
        }
        if (str_contains($result['body'], '<details role="group"')) {
            $issues[] = 'admin navigation still uses redundant grouped details semantics';
        }
        if (str_contains($result['body'], 'aria-label="Blog"') || str_contains($result['body'], 'aria-label="Ke stažení"') || str_contains($result['body'], 'aria-label="FAQ"') || str_contains($result['body'], 'aria-label="Vývěska a oznámení"')) {
            $issues[] = 'admin navigation still uses redundant aria-label on collapsible module groups';
        }
        if (isModuleEnabled('downloads') && !str_contains($result['body'], 'Soubory a položky')) {
            $issues[] = 'admin navigation is missing the updated downloads section label';
        }
        if (isModuleEnabled('board') && !str_contains($result['body'], 'Dokumenty a oznámení')) {
            $issues[] = 'admin navigation is missing the updated board section label';
        }
        if (isModuleEnabled('forms') && !str_contains($result['body'], '/admin/forms.php')) {
            $issues[] = 'admin dashboard/navigation is missing the forms entry';
        }
    }

    if ($page['label'] === 'admin_settings_modules') {
        foreach ([
            'name="module_forms"',
            'for="module_forms"',
            'id="module_forms"',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'module settings is missing forms fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_themes') {
        if (!str_contains($result['body'], 'name="active_theme"')) {
            $issues[] = 'theme selector is missing';
        }
        if (!str_contains($result['body'], 'Kora Default')) {
            $issues[] = 'default theme metadata is missing';
        }
        if (!str_contains($result['body'], 'theme-card__preview')) {
            $issues[] = 'theme preview cards are missing';
        }
        if (!str_contains($result['body'], 'theme_settings[accent]')) {
            $issues[] = 'theme settings form is missing';
        }
        if (!str_contains($result['body'], 'name="theme_package"')) {
            $issues[] = 'theme package import form is missing';
        }
        if (!str_contains($result['body'], 'name="export_theme"')) {
            $issues[] = 'theme package export form is missing';
        }
    }

    if ($page['label'] === 'admin_review_queue') {
        if (!str_contains($result['body'], 'Ke schválení')) {
            $issues[] = 'review queue heading is missing';
        }
        if (!str_contains($result['body'], 'review_queue.php?scope=content')) {
            $issues[] = 'review queue content filter is missing';
        }
        if (isModuleEnabled('blog') && !str_contains($result['body'], 'review_queue.php?scope=comments')) {
            $issues[] = 'review queue comments filter is missing';
        }
        if (isModuleEnabled('reservations') && !str_contains($result['body'], 'review_queue.php?scope=reservations')) {
            $issues[] = 'review queue reservations filter is missing';
        }
        if (str_contains($result['body'], 'queue-summary-heading') && !str_contains($result['body'], 'Rychlý přehled')) {
            $issues[] = 'review queue summary heading was not updated';
        }
        if (str_contains($result['body'], 'queue-summary-heading') && !str_contains($result['body'], 'Přejít do seznamu')) {
            $issues[] = 'review queue summary link text was not updated';
        }
        foreach ([
            'Souhrn čekajících položek',
            '>Modul<',
            'Doplňující info',
            'Přejít do správy',
            '>Moderace<',
        ] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'review queue still contains outdated phrase: ' . $forbiddenFragment;
            }
        }
    }

    if ($page['label'] === 'admin_news') {
        if (!str_contains($result['body'], 'name="q"')) {
            $issues[] = 'admin news search field is missing';
        }
        if (!str_contains($result['body'], 'name="status"')) {
            $issues[] = 'admin news status filter is missing';
        }
        if (!str_contains($result['body'], 'Přehled novinek')) {
            $issues[] = 'admin news table caption was not updated';
        }
    }

    if ($page['label'] === 'admin_faq') {
        if (!str_contains($result['body'], 'name="q"')) {
            $issues[] = 'admin faq search field is missing';
        }
        if (!str_contains($result['body'], 'name="status"')) {
            $issues[] = 'admin faq status filter is missing';
        }
        foreach ([
            'Kategorie FAQ',
            'Přehled otázek FAQ',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin faq page is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], 'Správa kategorií')) {
            $issues[] = 'admin faq page still contains outdated category action wording';
        }
    }

    if ($page['label'] === 'admin_events') {
        if (!str_contains($result['body'], 'name="q"')) {
            $issues[] = 'admin events search field is missing';
        }
        if (!str_contains($result['body'], 'name="status"')) {
            $issues[] = 'admin events status filter is missing';
        }
        if (!str_contains($result['body'], 'Přehled událostí')) {
            $issues[] = 'admin events table caption was not updated';
        }
    }

    if ($page['label'] === 'admin_contact') {
        foreach ([
            'name="q"',
            'name="status"',
            'contact_message.php',
            'Označit jako přečtené',
            'Označit jako vyřízené',
            'data-selection-status="contact"',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin contact inbox is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_contact_message') {
        if (!str_contains($result['body'], 'Označit jako nové')) {
            $issues[] = 'admin contact detail is missing "mark as new" action label';
        }
        if (str_contains($result['body'], 'Vrátit jako nové')) {
            $issues[] = 'admin contact detail still contains outdated "return as new" wording';
        }
    }

    if ($page['label'] === 'admin_chat') {
        foreach ([
            'name="q"',
            'name="status"',
            'chat_message.php',
            'Označit jako přečtené',
            'Označit jako vyřízené',
            'data-selection-status="chat"',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin chat inbox is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_chat_message') {
        if (!str_contains($result['body'], 'Označit jako nové')) {
            $issues[] = 'admin chat detail is missing "mark as new" action label';
        }
        if (str_contains($result['body'], 'Vrátit jako nové')) {
            $issues[] = 'admin chat detail still contains outdated "return as new" wording';
        }
    }

    if ($page['label'] === 'admin_newsletter') {
        foreach ([
            'name="q"',
            'name="status"',
            'newsletter_subscriber.php',
            'newsletter_bulk.php',
            'newsletter_history.php',
            'Odběratelé newsletteru',
            'Poslední rozesílky',
            'Nová rozesílka',
            'Hromadné akce s vybranými odběrateli',
            'data-selection-status="newsletter-subscribers"',
            'Vybrat všechny odběratele newsletteru',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin newsletter overview is missing fragment: ' . $expectedFragment;
            }
        }
        foreach ([
            '+ Napsat newsletter',
            '>Historie rozesílek<',
            '>Odběratelé<',
        ] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin newsletter overview still contains outdated phrase: ' . $forbiddenFragment;
            }
        }
        if (str_contains($result['body'], 'zobrazených položek')) {
            $issues[] = 'admin newsletter overview still uses generic item count wording';
        }
    }

    if ($page['label'] === 'admin_newsletter_form') {
        foreach ([
            'name="subject"',
            'name="body"',
            'potvrzených odběratelů',
            'Nová rozesílka',
            'Odeslat rozesílku',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin newsletter compose form is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_board') {
        if (!str_contains($result['body'], 'name="q"')) {
            $issues[] = 'admin board search field is missing';
        }
        if (!str_contains($result['body'], 'name="status"')) {
            $issues[] = 'admin board status filter is missing';
        }
        if (!str_contains($result['body'], 'Přehled položek sekce')) {
            $issues[] = 'admin board table caption was not updated';
        }
        if (!str_contains($result['body'], 'Návštěvníci tuto sekci na webu vidí jako')) {
            $issues[] = 'admin board is missing visitor-facing public label helper text';
        }
        if (!str_contains($result['body'], 'Kategorie vývěsky')) {
            $issues[] = 'admin board category quick link was not updated';
        }
        if (str_contains($result['body'], 'Veřejný název modulu je aktuálně')) {
            $issues[] = 'admin board still contains outdated public label wording';
        }
        if (str_contains($result['body'], 'Správa kategorií')) {
            $issues[] = 'admin board still contains outdated category action wording';
        }
    }

    if ($page['label'] === 'admin_downloads') {
        if (!str_contains($result['body'], 'name="q"')) {
            $issues[] = 'admin downloads search field is missing';
        }
        if (!str_contains($result['body'], 'name="status"')) {
            $issues[] = 'admin downloads status filter is missing';
        }
        if (!str_contains($result['body'], 'Přehled položek ke stažení')) {
            $issues[] = 'admin downloads table caption was not updated';
        }
    }

    if ($page['label'] === 'admin_forms') {
        foreach ([
            'name="q"',
            'name="status"',
            'Vytvořit formulář',
            'Nahlášení chyby',
            'Návrh nové funkce',
            'Žádost o podporu',
            'Kontaktní formulář',
            'Nahlášení problému s obsahem',
            'Na jednom místě připravíte veřejné formuláře',
            'Otevřené',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin forms page is missing fragment: ' . $expectedFragment;
            }
        }
        if (
            !str_contains($result['body'], 'Přehled formulářů')
            && !str_contains($result['body'], 'Zatím tu nejsou žádné formuláře.')
        ) {
            $issues[] = 'admin forms page is missing both list caption and empty state';
        }
        foreach ([
            '>+ Vytvořit formulář<',
            '>+ Nahlášení chyby<',
            '>+ Návrh nové funkce<',
            '>+ Žádost o podporu<',
            '>+ Kontaktní formulář<',
            '>+ Nahlášení problému s obsahem<',
        ] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin forms page still contains spoken plus sign in create action: ' . $forbiddenFragment;
            }
        }
    }

    if ($page['label'] === 'admin_widgets') {
        foreach ([
            'id="widget-zone-homepage"',
            'id="widget-zone-sidebar"',
            'id="widget-zone-footer"',
            'id="widget-add-zone"',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin widgets page is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_form_submissions') {
        foreach ([
            'GitHub',
            'Přehled odpovědí formuláře',
            'Hledat v odpovědích',
            'Exportovat CSV',
            'Reference',
            'Priorita',
            'Štítky',
            'Přiřazeno',
            'GitHub issue',
            'Hromadné akce s vybranými odpověďmi',
            'Rozpracované',
            'Runtime audit workflow poznámka',
            'Administrace, Formuláře',
            'Zobrazit na webu',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin form submissions page is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_form_submission_detail') {
        foreach ([
            'GitHub issue',
            'PĹ™ipravit issue',
            'OtevĹ™Ă­t nĂˇvrh na GitHubu',
            'PĹ™ipojit existujĂ­cĂ­ issue',
            'RepozitĂˇĹ™',
            'Referenční kód',
            'Workflow hlášení',
            'Priorita',
            'Štítky',
            'Interní poznámka',
            'Přiřadit řešiteli',
            'Runtime audit workflow poznámka pro ověření detailu odpovědi.',
            'runtime-audit-log.txt',
            'runtime-audit-shot.png',
            'Odpověď odesílateli',
            'runtime.audit@example.test',
            'Poslat odpověď',
            'Interní historie',
            'Odpověď byla přijata přes veřejný formulář.',
            'Odeslána odpověď odesílateli',
            'Zpět na odpovědi formuláře',
            'Rychlé kroky',
            'Převzít řešení',
            'Označit jako vyřešené',
            'Uzavřít hlášení',
            'Uložit změny workflow',
            'Smazat odpověď',
        ] as $expectedFragment) {
            if (
                (str_contains($expectedFragment, 'issue') && $expectedFragment !== 'GitHub issue')
                || str_contains($expectedFragment, 'GitHubu')
                || str_contains($expectedFragment, 'Repozit')
            ) {
                continue;
            }
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin form submission detail is missing fragment: ' . $expectedFragment;
            }
        }

        foreach ([
            'id="github-issue-form"',
            'id="github-issue-open"',
            'id="github-issue-copy"',
            'name="existing_issue_url"',
            'name="repository"',
            'name="quick_action" value="take"',
            'name="quick_action" value="resolve"',
            'name="quick_action" value="close"',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin form submission detail is missing fragment: ' . $expectedFragment;
            }
        }
        if (!str_contains($result['body'], '/admin/form_submission_file.php?id=')) {
            $issues[] = 'admin form submission detail is missing protected attachment download links';
        }
        if (str_contains($result['body'], '/uploads/forms/')) {
            $issues[] = 'admin form submission detail still exposes direct public form upload paths';
        }
    }

    if ($page['label'] === 'admin_form_edit') {
        foreach ([
            'Zpět na formuláře',
            'Odpovědi formuláře',
            'Zobrazit na webu',
            'value="section"',
            'value="radio"',
            'value="checkbox_group"',
            'value="consent"',
            'value="file"',
            'value="hidden"',
            'value="url"',
            'name="use_honeypot"',
            'name="success_behavior"',
            'name="success_primary_label"',
            'name="success_primary_url"',
            'name="success_secondary_label"',
            'name="success_secondary_url"',
            'name="fields[0][default_value]"',
            'name="fields[0][start_new_row]"',
            'name="submitter_confirmation_enabled"',
            'name="submitter_email_field"',
            'name="submitter_confirmation_subject"',
            'name="submitter_confirmation_message"',
            'name="webhook_enabled"',
            'name="webhook_url"',
            'name="webhook_secret"',
            'name="webhook_events[]"',
            'name="fields[0][layout_width]"',
            'name="fields[0][allow_multiple]"',
            'name="fields[0][show_if_field]"',
            'name="fields[0][show_if_operator]"',
            'name="fields[0][show_if_value]"',
            'Ukázka potvrzovacího e-mailu',
            'Nastavení pole:',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin form edit page is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'public_form') {
        foreach ([
            'Runtime audit formulář',
            'Odeslat hlášení',
            'Zařazení problému',
            'Popis chyby',
            'Přílohy a kontakt',
            'Název problému',
            'Závažnost',
            'Nízká',
            'Střední',
            'Vysoká',
            'Dotčené oblasti',
            'Administrace',
            'Formuláře',
            'Dopad na práci',
            'Prohlížeč a zařízení',
            'Na tuto adresu může přijít potvrzení o přijetí hlášení.',
            'Souhlasím se zpracováním údajů pro vyřízení hlášení.',
            'type="file"',
            'multiple',
            'multipart/form-data',
            'name="hp_website"',
            'Krátký souhrn toho, co se pokazilo.',
            'form-fields-grid',
            'form-section-card',
            'form-row-break',
            'data-conditional-form',
            'data-show-if-field="zavaznost"',
            'data-show-if-operator="contains"',
            'data-show-if-value="Vysoká|Kritická"',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'public form page is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], '<legend>Runtime audit formulář</legend>')) {
            $issues[] = 'public form still repeats the page title inside an outer legend';
        }
    }

    if ($page['label'] === 'admin_media') {
        foreach ([
            'name="media_files[]"',
            'Knihovna médií',
            '>Audio<',
            '>Video<',
            'Obrázky, audio, video a dokumenty.',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin media page is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_pages') {
        if (!str_contains($result['body'], 'name="q"')) {
            $issues[] = 'admin pages search field is missing';
        }
        if (!str_contains($result['body'], 'name="status"')) {
            $issues[] = 'admin pages status filter is missing';
        }
        if (!str_contains($result['body'], 'Přehled statických stránek')) {
            $issues[] = 'admin pages table caption was not updated';
        }
    }

    if ($page['label'] === 'admin_pages' && !str_contains($result['body'], 'page_positions.php')) {
        $issues[] = 'admin pages list is missing the page positions link';
    }

    if ($page['label'] === 'admin_page_positions') {
        foreach ([
            'page_reorder.php',
            '/admin/pages.php',
            '/admin/page_form.php',
            'name="dir" value="up"',
            'name="dir" value="down"',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin page positions is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_food') {
        if (!str_contains($result['body'], 'name="q"')) {
            $issues[] = 'admin food search field is missing';
        }
        if (!str_contains($result['body'], 'name="status"')) {
            $issues[] = 'admin food status filter is missing';
        }
        foreach ([
            'Přehled jídelních lístků',
            'Přehled nápojových lístků',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin food page is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_places') {
        if (!str_contains($result['body'], 'name="q"')) {
            $issues[] = 'admin places search field is missing';
        }
        if (!str_contains($result['body'], 'name="status"')) {
            $issues[] = 'admin places status filter is missing';
        }
        if (!str_contains($result['body'], 'Přehled zajímavých míst')) {
            $issues[] = 'admin places table caption was not updated';
        }
    }

    if ($page['label'] === 'admin_res_resources') {
        foreach ([
            'name="q"',
            'name="status"',
            'res_resource_form.php',
            'Kategorie zdrojů rezervací',
            'Lokality rezervací',
            'Přehled zdrojů rezervací',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin reservation resources is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_res_categories' && !str_contains($result['body'], 'name="q"')) {
        $issues[] = 'admin reservation categories search field is missing';
    }

    if ($page['label'] === 'admin_res_locations' && !str_contains($result['body'], 'name="q"')) {
        $issues[] = 'admin reservation locations search field is missing';
    }

    if ($page['label'] === 'admin_podcast_shows') {
        if (!str_contains($result['body'], 'name="q"')) {
            $issues[] = 'admin podcast shows search field is missing';
        }
        if (!str_contains($result['body'], 'podcast_show_form.php')) {
            $issues[] = 'admin podcast shows page is missing create link';
        }
        if (!str_contains($result['body'], 'Přehled podcastů')) {
            $issues[] = 'admin podcast shows table caption was not updated';
        }
        if (!str_contains($result['body'], 'Spravovat epizody')) {
            $issues[] = 'admin podcast shows page is missing the updated episode action label';
        }
    }

    if ($page['label'] === 'admin_podcast') {
        if (!str_contains($result['body'], 'name="q"')) {
            $issues[] = 'admin podcast episode search field is missing';
        }
        if (!str_contains($result['body'], 'name="status"')) {
            $issues[] = 'admin podcast episode status filter is missing';
        }
        foreach ([
            'Zpět na přehled podcastů',
            'Přehled epizod podcastu',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin podcast episode page is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], 'Zpět na podcasty')) {
            $issues[] = 'admin podcast episode page still contains outdated return label';
        }
    }

    if ($page['label'] === 'admin_blog') {
        foreach ([
            'Kategorie blogu',
            'Štítky blogu',
            'Přehled článků blogu',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin blog page is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_users') {
        foreach ([
            'Uživatelé a role',
            'Přehled uživatelů',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin users page is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], 'Správa uživatelů')) {
            $issues[] = 'admin users page still contains outdated heading';
        }
    }

    if ($page['label'] === 'admin_audit_log') {
        foreach ([
            'Audit log',
            'Podrobnosti akce',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin audit log page is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], '>Detail<')) {
            $issues[] = 'admin audit log page still contains outdated detail column label';
        }
    }

    if (in_array($page['label'], [
        'admin_blog',
        'admin_board',
        'admin_comments',
        'admin_contact',
        'admin_chat',
        'admin_newsletter',
        'admin_news',
        'admin_faq',
        'admin_events',
        'admin_downloads',
        'admin_food',
        'admin_gallery_albums',
        'admin_gallery_photos',
        'admin_pages',
        'admin_places',
        'admin_res_resources',
        'admin_res_categories',
        'admin_res_locations',
        'admin_res_bookings',
        'admin_podcast_shows',
        'admin_podcast',
        'admin_polls',
    ], true) && !str_contains($result['body'], 'Použít filtr')) {
        $issues[] = 'admin list page is missing the unified "Použít filtr" action';
    }

    if (str_starts_with($page['label'], 'admin_')) {
        if (preg_match('/<(?:th|td|button|input|select|option|a)\b[^>]*\b(?:scope|type|class|aria-label|data-confirm)\s*=\s*[“”]/u', $result['body'])) {
            $issues[] = 'admin page contains smart quotes inside HTML attributes';
        }
        if (preg_match('/\bdata-confirm="[^"]*"[^>\s][^>]*>/u', $result['body'])) {
            $issues[] = 'admin page contains broken data-confirm attribute quoting';
        }
        foreach ([
            'Žádné články odpovídající hledání.',
            'Žádné novinky pro zadaný filtr.',
            'Žádné události pro zadaný filtr.',
            'Žádné položky pro zadaný filtr.',
            'Žádné otázky pro zadaný filtr.',
            'Žádné statické stránky pro zadaný filtr.',
            'Žádná místa pro zadaný filtr.',
            'Žádné epizody pro zadaný filtr.',
            'Žádné podcasty pro zadaný filtr.',
            'Přidejte první podcast.',
            'Žádné ankety pro zadaný filtr.',
            'Žádné lístky pro zadaný filtr.',
            'Žádné zdroje pro zadaný filtr.',
            'Žádné kategorie pro zadaný filtr.',
            'Žádné rezervace neodpovídají zadanému filtru.',
            'Pro zadaný filtr nebyla nalezena žádná alba.',
            'Pro zadaný filtr nebyly nalezeny žádné fotografie.',
            'Zatím nebylo vytvořeno žádné album.',
            'V tomto albu nejsou žádné fotografie.',
            'Žádné kategorie.',
            'Žádné tagy.',
            'Tomuto účtu zatím není přidělena žádná část administrace.',
            'Žádná místa. <a href="res_locations.php">Přidat místo</a>',
        ] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin page still contains outdated empty-state wording: ' . $forbiddenFragment;
            }
        }
    }

    if (str_starts_with($page['label'], 'admin_')) {
        foreach ([
            'VeĹ™ejnĂˇ strĂˇnka',
            'VeĹ™ejnĂˇ strĂˇnka zdroje',
            '>VĹˇechny podcasty<',
            '>SprĂˇva zdrojĹŻ<',
        ] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin page still contains outdated action wording: ' . $forbiddenFragment;
            }
        }
    }

    if ($page['label'] === 'admin_board_form') {
        foreach ([
            'name="board_type"',
            'name="excerpt"',
            'name="board_image"',
            'name="contact_name"',
            'name="contact_phone"',
            'name="contact_email"',
            'name="is_pinned"',
            'Zpět na přehled sekce',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin board form is missing field: ' . $expectedField;
            }
        }
    }

    if ($page['label'] === 'admin_download_form') {
        foreach ([
            'name="slug"',
            'name="download_type"',
            'name="excerpt"',
            'name="download_image"',
            'name="version_label"',
            'name="platform_label"',
            'name="license_label"',
            'name="external_url"',
            'name="file_delete"',
            'Zpět na přehled ke stažení',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin download form is missing field: ' . $expectedField;
            }
        }
    }

    if ($page['label'] === 'admin_food_form') {
        foreach ([
            'name="type"',
            'name="slug"',
            'name="valid_from"',
            'name="valid_to"',
            'Zpět na jídelní a nápojové lístky',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin food form is missing field: ' . $expectedField;
            }
        }
    }

    if ($page['label'] === 'admin_page_form') {
        foreach ([
            'name="slug"',
            'name="show_in_nav"',
            'name="is_published"',
            'Upravit statickou stránku',
            'Zpět na statické stránky',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin page form is missing field: ' . $expectedField;
            }
        }
    }

    if (in_array($page['label'], ['admin_page_form', 'admin_page_create_form'], true)) {
        foreach ([
            'name="slug"',
            'name="show_in_nav"',
            'name="is_published"',
            'page_positions.php',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin page form is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], 'name="nav_order"')) {
            $issues[] = 'admin page form still contains the old nav_order input';
        }
    }

    if ($page['label'] === 'admin_contact_message') {
        foreach ([
            'mailto:',
            'name="action" value="handled"',
            'Zpět na přehled kontaktních zpráv',
            'Co můžete udělat',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin contact detail is missing fragment: ' . $expectedFragment;
            }
        }
        foreach ([
            'Zpět na kontaktní zprávy',
            '>Akce<',
        ] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin contact detail still contains outdated phrase: ' . $forbiddenFragment;
            }
        }
    }

    if ($page['label'] === 'admin_chat_message') {
        foreach ([
            'name="action" value="handled"',
            'Runtime Audit',
            'Zpět na přehled chat zpráv',
            'Co můžete udělat',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin chat detail is missing fragment: ' . $expectedFragment;
            }
        }
        foreach ([
            'Zpět na chat zprávy',
            '>Akce<',
        ] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin chat detail still contains outdated phrase: ' . $forbiddenFragment;
            }
        }
    }

    if ($page['label'] === 'admin_newsletter_subscriber') {
        foreach ([
            'mailto:',
            'name="action" value="resend"',
            'name="action" value="confirm"',
            'Zpět na odběratele newsletteru',
            'Potvrzení odběru',
            'Co můžete udělat',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin newsletter subscriber detail is missing fragment: ' . $expectedFragment;
            }
        }
        foreach ([
            'Zpět na přehled newsletteru',
            'Správa potvrzení',
            '>Akce<',
        ] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin newsletter subscriber detail still contains outdated phrase: ' . $forbiddenFragment;
            }
        }
    }

    if ($page['label'] === 'admin_newsletter_history') {
        foreach ([
            'Runtime audit newsletter',
            'Příjemců',
            'Testovací obsah rozesílky',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin newsletter history detail is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_res_resource_form') {
        foreach ([
            'name="slug"',
            'name="capacity"',
            'name="slot_mode"',
            'name="location_ids[]"',
            'name="allow_guests"',
            'name="max_concurrent"',
            'Upravit zdroj rezervací',
            'Zpět na zdroje rezervací',
            'Spravovat lokality rezervací',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin reservation resource form is missing field: ' . $expectedField;
            }
        }
    }

    if ($page['label'] === 'admin_faq_form') {
        foreach ([
            'name="slug"',
            'name="excerpt"',
            'name="category_id"',
            'Upravit položku znalostní báze',
            'Zpět na FAQ',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin faq form is missing field: ' . $expectedField;
            }
        }
        if (!str_contains($result['body'], 'id="answer"') && !str_contains($result['body'], 'name="answer"')) {
            $issues[] = 'admin faq form is missing answer field';
        }
    }

    if ($page['label'] === 'admin_place_form') {
        foreach ([
            'name="slug"',
            'name="place_kind"',
            'name="excerpt"',
            'name="place_image"',
            'name="address"',
            'name="locality"',
            'name="latitude"',
            'name="longitude"',
            'name="contact_phone"',
            'name="contact_email"',
            'name="opening_hours"',
            'Upravit zajímavé místo',
            'Zpět na zajímavá místa',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin place form is missing field: ' . $expectedField;
            }
        }
    }

    if ($page['label'] === 'admin_polls') {
        foreach ([
            'name="q"',
            'name="status"',
            'value="active"',
            'value="scheduled"',
            'value="closed"',
            'Přehled anket',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin polls page is missing field: ' . $expectedField;
            }
        }
    }

    if ($page['label'] === 'admin_polls_form') {
        foreach ([
            'name="slug"',
            'name="description"',
            'name="start_date"',
            'name="start_time"',
            'name="end_date"',
            'name="end_time"',
            'name="options[]"',
            'Zpět na ankety',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin polls form is missing field: ' . $expectedField;
            }
        }
    }

    if ($page['label'] === 'admin_gallery_albums') {
        foreach ([
            'name="q"',
            '/admin/gallery_album_form.php',
            'Přehled alb',
            'Spravovat fotografie',
            'Zobrazit na webu',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin gallery albums is missing fragment: ' . $expectedFragment;
            }
        }
        foreach ([
            'Seznam alb',
            '>Fotografie<',
            '>Web<',
        ] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin gallery albums still contains outdated phrase: ' . $forbiddenFragment;
            }
        }
    }

    if (in_array($page['label'], [
        'admin_board_form',
        'admin_board_create_form',
        'admin_download_form',
        'admin_download_create_form',
        'admin_faq_form',
        'admin_faq_create_form',
        'admin_place_form',
        'admin_place_create_form',
    ], true) && str_contains($result['body'], 'name="sort_order"')) {
        $issues[] = 'admin content form still contains the old sort_order input';
    }

    if ($page['label'] === 'admin_gallery_album_form') {
        foreach ([
            'name="slug"',
            'name="parent_id"',
            'Upravit album galerie',
            'Zpět na alba galerie',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin gallery album form is missing field: ' . $expectedField;
            }
        }
    }

    if ($page['label'] === 'admin_gallery_photos') {
        foreach ([
            'name="q"',
            '/admin/gallery_photo_form.php',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin gallery photos is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_gallery_photo_create_form') {
        if (!str_contains($result['body'], 'name="photos[]"')) {
            $issues[] = 'admin gallery photo create form is missing upload field';
        }
    }

    if ($page['label'] === 'admin_gallery_photo_form') {
        foreach ([
            'name="title"',
            'name="slug"',
            'name="sort_order"',
            'Zpět na fotografie v albu',
            'Zobrazit na webu',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin gallery photo form is missing field: ' . $expectedField;
            }
        }
    }

    if ($page['label'] === 'admin_podcast_show_form') {
        foreach ([
            'name="slug"',
            'name="author"',
            'name="subtitle"',
            'name="language"',
            'name="category"',
            'name="owner_name"',
            'name="owner_email"',
            'name="explicit_mode"',
            'name="show_type"',
            'name="feed_complete"',
            'name="feed_episode_limit"',
            'name="website_url"',
            'name="cover_image"',
            'Zpět na přehled podcastů',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin podcast show form is missing field: ' . $expectedField;
            }
        }
    }

    if ($page['label'] === 'admin_podcast_form') {
        foreach ([
            'name="slug"',
            'name="subtitle"',
            'name="episode_num"',
            'name="season_num"',
            'name="episode_type"',
            'name="explicit_mode"',
            'name="block_from_feed"',
            'name="duration"',
            'name="audio_file"',
            'name="image_file"',
            'name="audio_url"',
            'name="publish_at"',
            'Upravit epizodu podcastu',
            'Zpět na epizody podcastu',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin podcast form is missing field: ' . $expectedField;
            }
        }
    }

    if ($page['label'] === 'podcast_feed') {
        foreach ([
            '<itunes:summary>',
            '<itunes:subtitle>',
            '<itunes:owner>',
            '<itunes:email>runtime.audit.owner@example.test</itunes:email>',
            '<itunes:type>episodic</itunes:type>',
            '<itunes:explicit>clean</itunes:explicit>',
            '<itunes:season>2</itunes:season>',
            '<itunes:episodeType>bonus</itunes:episodeType>',
            '<itunes:explicit>yes</itunes:explicit>',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'podcast feed is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'blog_index' && $articleId !== false && $runtimeAuditAuthorPath !== '' && !str_contains($result['body'], $runtimeAuditAuthorPath)) {
        $issues[] = 'blog listing is missing public author links';
    }
    if ($page['label'] === 'blog_index' && $runtimeAuditAuthorPath !== '' && !str_contains($result['body'], authorIndexPath())) {
        $issues[] = 'blog listing is missing authors index link';
    }
    if ($page['label'] === 'blog_index' && !str_contains($result['body'], '/feed.php?blog=')) {
        $issues[] = 'blog listing is missing blog-specific RSS link';
    }

    if ($page['label'] === 'blog_feed') {
        foreach ([
            '<rss version="2.0"',
            '<atom:link href=',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'blog feed is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], '/news/') || str_contains($result['body'], '<title>Runtime audit zpráva')) {
            $issues[] = 'blog-specific feed still includes global news items';
        }
        if (!empty($articleRow['blog_slug']) && !str_contains($result['body'], '?blog=' . rawurlencode((string)$articleRow['blog_slug']))) {
            $issues[] = 'blog-specific feed is missing its self link query parameter';
        }
    }

    if ($page['label'] === 'sitemap_php' || $page['label'] === 'sitemap_xml') {
        $contentTypeOk = false;
        foreach ($result['headers'] as $headerLine) {
            if (stripos($headerLine, 'Content-Type: application/xml') === 0) {
                $contentTypeOk = true;
                break;
            }
        }
        if (!$contentTypeOk) {
            $issues[] = 'sitemap response is missing application/xml content type';
        }
        foreach ([
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
            '<loc>' . h($baseUrl . '/') . '</loc>',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'sitemap is missing fragment: ' . $expectedFragment;
            }
        }
        if ($runtimeAuditFormPath !== '' && !str_contains($result['body'], h($runtimeAuditFormPath))) {
            $issues[] = 'sitemap is missing active public form URL';
        }
    }

    if ($page['label'] === 'blog_author_filter') {
        if (!str_contains($result['body'], 'Autor: Runtime Audit')) {
            $issues[] = 'filtered blog listing is missing active author label';
        }
        if (!str_contains($result['body'], 'Všichni autoři')) {
            $issues[] = 'filtered blog listing is missing link back to all authors';
        }
        if ($runtimeAuditAuthorPath !== '' && !str_contains($result['body'], $runtimeAuditAuthorPath)) {
            $issues[] = 'filtered blog listing is missing author links';
        }
        if (
            $runtimeAuditAuthorSlug !== ''
            && (str_contains($result['body'], 'Kategorie blogu') || str_contains($result['body'], 'Tagy blogu'))
            && !str_contains($result['body'], 'autor=' . rawurlencode($runtimeAuditAuthorSlug))
        ) {
            $issues[] = 'filtered blog listing is missing author-preserving filter links';
        }
    }

    if ($page['label'] === 'blog_article' && $articleId !== false && $runtimeAuditAuthorPath !== '' && !str_contains($result['body'], $runtimeAuditAuthorPath)) {
        $issues[] = 'blog article is missing public author link in byline';
    }
    if ($page['label'] === 'blog_article' && $articleId !== false && $runtimeAuditAuthorPath !== '') {
        foreach (['O autorovi', 'Profil autora', 'Web autora'] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'blog article is missing author panel fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'news_index' && $newsCanonicalPath !== '' && !str_contains($result['body'], $newsCanonicalPath)) {
        $issues[] = 'news listing is missing detail links';
    }

    if ($page['label'] === 'news_index' && $newsId !== false && $runtimeAuditAuthorPath !== '' && !str_contains($result['body'], $runtimeAuditAuthorPath)) {
        $issues[] = 'news listing is missing public author links';
    }

    if ($page['label'] === 'news_article') {
        if ($newsRow && !str_contains($result['body'], newsTitleCandidate((string)($newsRow['title'] ?? ''), ''))) {
            $issues[] = 'news article is missing title';
        }
        if ($newsId !== false && $runtimeAuditAuthorPath !== '' && !str_contains($result['body'], $runtimeAuditAuthorPath)) {
            $issues[] = 'news article is missing public author link';
        }
    }

    if ($page['label'] === 'faq_index' && $faqCanonicalPath !== '' && !str_contains($result['body'], $faqCanonicalPath)) {
        $issues[] = 'faq listing is missing detail links';
    }

    if ($page['label'] === 'food' && $foodCardCanonicalPath !== '' && !str_contains($result['body'], $foodCardCanonicalPath)) {
        $issues[] = 'food index is missing detail links';
    }

    if ($page['label'] === 'food_archive' && $foodCardCanonicalPath !== '' && !str_contains($result['body'], $foodCardCanonicalPath)) {
        $issues[] = 'food archive is missing detail links';
    }

    if ($page['label'] === 'food_card') {
        if ($foodCardRow && !str_contains($result['body'], (string)($foodCardRow['title'] ?? ''))) {
            $issues[] = 'food card is missing title';
        }
        if (!str_contains($result['body'], 'Zpět do archivu')) {
            $issues[] = 'food card is missing back link';
        }
    }

    if ($page['label'] === 'faq_article') {
        if ($faqRow && !str_contains($result['body'], (string)($faqRow['question'] ?? ''))) {
            $issues[] = 'faq article is missing title';
        }
        if (!str_contains($result['body'], 'Zpět na znalostní bázi')) {
            $issues[] = 'faq article is missing back link';
        }
        if ($faqRow && !str_contains($result['body'], (string)($faqRow['excerpt'] ?? ''))) {
            $issues[] = 'faq article is missing excerpt';
        }
    }

    if ($page['label'] === 'gallery_index' && $galleryAlbumCanonicalPath !== '' && !str_contains($result['body'], $galleryAlbumCanonicalPath)) {
        $issues[] = 'gallery listing is missing album detail links';
    }

    if ($page['label'] === 'gallery_album') {
        if ($galleryAlbumRow && !str_contains($result['body'], (string)($galleryAlbumRow['name'] ?? ''))) {
            $issues[] = 'gallery album is missing title';
        }
        if ($galleryAlbumPhotoCanonicalPath !== '' && !str_contains($result['body'], $galleryAlbumPhotoCanonicalPath)) {
            $issues[] = 'gallery album is missing photo detail links';
        }
    }

    if ($page['label'] === 'gallery_photo') {
        if ($galleryPhotoRow && !str_contains($result['body'], (string)($galleryPhotoRow['label'] ?? ''))) {
            $issues[] = 'gallery photo is missing title';
        }
        if (!str_contains($result['body'], 'Zpět do alba')) {
            $issues[] = 'gallery photo is missing back link';
        }
    }

    if ($page['label'] === 'events_index' && $eventCanonicalPath !== '' && !str_contains($result['body'], $eventCanonicalPath)) {
        $issues[] = 'events listing is missing detail links';
    }

    if ($page['label'] === 'events_article') {
        if ($eventRow && !str_contains($result['body'], (string)($eventRow['title'] ?? ''))) {
            $issues[] = 'events article is missing title';
        }
        if (!str_contains($result['body'], 'Zpět na události')) {
            $issues[] = 'events article is missing back link';
        }
        foreach (['Praktické informace', 'O události'] as $legacySnippet) {
            if (str_contains($result['body'], $legacySnippet)) {
                $issues[] = 'events article still contains redundant detail block: ' . $legacySnippet;
            }
        }
    }

    if ($page['label'] === 'board_article') {
        if ($boardRow && !str_contains($result['body'], (string)($boardRow['title'] ?? ''))) {
            $issues[] = 'board article is missing title';
        }
        if (!str_contains($result['body'], boardModuleBackLabel())) {
            $issues[] = 'board article is missing back link';
        }
        if ($boardRow && !str_contains($result['body'], (string)($boardRow['excerpt'] ?? ''))) {
            $issues[] = 'board article is missing excerpt';
        }
        if ($boardRow && !str_contains($result['body'], (string)($boardRow['contact_phone'] ?? ''))) {
            $issues[] = 'board article is missing contact phone';
        }
        if (str_contains($result['body'], 'Důležité informace')) {
            $issues[] = 'board article still contains redundant detail block';
        }
        if ($boardAttachmentId !== false
            && (int)$boardAttachmentId === (int)($boardRow['id'] ?? 0)
            && !str_contains($result['body'], '/board/file.php?id=' . (int)$boardAttachmentId)) {
            $issues[] = 'board article is missing download CTA';
        }
    }

    if ($page['label'] === 'downloads_article') {
        if ($downloadRow && !str_contains($result['body'], (string)($downloadRow['title'] ?? ''))) {
            $issues[] = 'downloads article is missing title';
        }
        if (!str_contains($result['body'], 'Zpět na přehled ke stažení')) {
            $issues[] = 'downloads article is missing back link';
        }
        if ($downloadRow && !str_contains($result['body'], (string)($downloadRow['version_label'] ?? ''))) {
            $issues[] = 'downloads article is missing version metadata';
        }
        if ($downloadRow && !str_contains($result['body'], (string)($downloadRow['platform_label'] ?? ''))) {
            $issues[] = 'downloads article is missing platform metadata';
        }
        if ($downloadId !== false && !str_contains($result['body'], '/downloads/file.php?id=' . (int)$downloadId)) {
            $issues[] = 'downloads article is missing file download CTA';
        }
        if ($downloadRow && !str_contains($result['body'], (string)($downloadRow['external_url'] ?? ''))) {
            $issues[] = 'downloads article is missing external link CTA';
        }
    }

    if ($page['label'] === 'places_article') {
        if ($placeRow && !str_contains($result['body'], (string)($placeRow['name'] ?? ''))) {
            $issues[] = 'places article is missing title';
        }
        if (!str_contains($result['body'], 'Zpět na zajímavá místa')) {
            $issues[] = 'places article is missing back link';
        }
        if ($placeRow && !str_contains($result['body'], (string)($placeRow['address'] ?? ''))) {
            $issues[] = 'places article is missing address';
        }
        if ($placeRow && !str_contains($result['body'], (string)($placeRow['contact_phone'] ?? ''))) {
            $issues[] = 'places article is missing contact phone';
        }
        if ($placeRow && !str_contains($result['body'], (string)($placeRow['contact_email'] ?? ''))) {
            $issues[] = 'places article is missing contact email';
        }
        if ($placeRow && !str_contains($result['body'], 'google.com/maps')) {
            $issues[] = 'places article is missing map link';
        }
    }

    if ($page['label'] === 'polls_index') {
        if ($pollCanonicalPath !== '' && !str_contains($result['body'], $pollCanonicalPath)) {
            $issues[] = 'polls listing is missing detail links';
        }
    }

    if ($page['label'] === 'polls_detail') {
        if ($pollRow && !str_contains($result['body'], (string)($pollRow['question'] ?? ''))) {
            $issues[] = 'poll detail is missing title';
        }
        if (!str_contains($result['body'], 'Zpět na přehled anket')) {
            $issues[] = 'poll detail is missing back link';
        }
        if ($pollRow && !str_contains($result['body'], (string)($pollRow['excerpt'] ?? ''))) {
            $issues[] = 'poll detail is missing description';
        }
    }

    if ($page['label'] === 'podcast_index' && $podcastShowSlug !== '' && !str_contains($result['body'], '/podcast/' . $podcastShowSlug)) {
        $issues[] = 'podcast listing is missing show detail link';
    }

    if ($page['label'] === 'podcast_show') {
        if ($podcastShowRow && !str_contains($result['body'], (string)($podcastShowRow['title'] ?? ''))) {
            $issues[] = 'podcast show is missing title';
        }
        if ($podcastEpisodeCanonicalPath !== '' && !str_contains($result['body'], $podcastEpisodeCanonicalPath)) {
            $issues[] = 'podcast show is missing episode detail links';
        }
        if (!str_contains($result['body'], 'RSS feed')) {
            $issues[] = 'podcast show is missing RSS link';
        }
    }

    if ($page['label'] === 'podcast_episode') {
        if ($podcastEpisodeRow && !str_contains($result['body'], (string)($podcastEpisodeRow['title'] ?? ''))) {
            $issues[] = 'podcast episode is missing title';
        }
        if ($podcastShowRow && !str_contains($result['body'], (string)($podcastShowRow['title'] ?? ''))) {
            $issues[] = 'podcast episode is missing parent show title';
        }
        if (!str_contains($result['body'], '<audio')) {
            $issues[] = 'podcast episode is missing audio player';
        }
        if ($podcastShowRow && !str_contains($result['body'], (string)($podcastShowRow['public_path'] ?? ''))) {
            $issues[] = 'podcast episode is missing back link to show';
        }
    }

    if ($page['label'] === 'authors_index') {
        if (!str_contains($result['body'], 'Autoři')) {
            $issues[] = 'authors index is missing title';
        }
        if ($runtimeAuditAuthorPath !== '' && !str_contains($result['body'], $runtimeAuditAuthorPath)) {
            $issues[] = 'authors index is missing public author profile link';
        }
    }

    if ($page['label'] === 'public_author') {
        if (!str_contains($result['body'], 'Runtime Audit')) {
            $issues[] = 'public author page is missing author identity';
        }
        if (!str_contains($result['body'], 'Krátký veřejný medailonek pro automatický audit autora.')) {
            $issues[] = 'public author page is missing author bio';
        }
        if (!str_contains($result['body'], 'Všichni autoři')) {
            $issues[] = 'public author page is missing back link to all authors';
        }
        if (!str_contains($result['body'], '>Blog<')) {
            $issues[] = 'public author page is missing back link to blog';
        }
    }

    if ($page['label'] === 'reservations_resource') {
        foreach ([
            'Zpět na přehled zdrojů',
            'Jak rezervace fungují',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'reservations resource page is missing fragment: ' . $expectedFragment;
            }
        }
    }

    echo '=== ' . $page['label'] . " ===\n";
    if ($issues === []) {
        echo "OK\n";
        continue;
    }

    $failures++;
    foreach ($issues as $issue) {
        echo '- ' . $issue . "\n";
    }
}

echo "=== blog_article_legacy_redirect ===\n";
if ($articleCanonicalPath === '' || $articleLegacyPath === '' || $articleCanonicalPath === $articleLegacyPath) {
    echo "OK\n";
} else {
    $legacyArticleProbe = fetchUrl($articleLegacyUrl, '', 0);
    $expectedLocation = $articleCanonicalPath;
    if (!str_contains($legacyArticleProbe['status'], '302')) {
        echo "- legacy blog article URL does not redirect ({$legacyArticleProbe['status']})\n";
        $failures++;
    } elseif (!responseHasLocationHeader($legacyArticleProbe['headers'], $expectedLocation, $baseUrl)) {
        echo "- legacy blog article URL does not redirect to canonical slug path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== news_article_legacy_redirect ===\n";
if ($newsCanonicalPath === '' || $newsLegacyPath === '' || $newsCanonicalPath === $newsLegacyPath) {
    echo "OK\n";
} else {
    $legacyNewsProbe = fetchUrl($newsLegacyUrl, '', 0);
    $expectedLocation = 'Location: ' . $newsCanonicalPath;
    if (!str_contains($legacyNewsProbe['status'], '302')) {
        echo "- legacy news article URL does not redirect ({$legacyNewsProbe['status']})\n";
        $failures++;
    } elseif (!in_array($expectedLocation, $legacyNewsProbe['headers'], true)) {
        echo "- legacy news article URL does not redirect to canonical slug path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== faq_article_legacy_redirect ===\n";
if ($faqCanonicalPath === '' || $faqLegacyPath === '' || $faqCanonicalPath === $faqLegacyPath) {
    echo "OK\n";
} else {
    $legacyFaqProbe = fetchUrl($faqLegacyUrl, '', 0);
    $expectedLocation = 'Location: ' . $faqCanonicalPath;
    if (!str_contains($legacyFaqProbe['status'], '302')) {
        echo "- legacy faq URL does not redirect ({$legacyFaqProbe['status']})\n";
        $failures++;
    } elseif (!in_array($expectedLocation, $legacyFaqProbe['headers'], true)) {
        echo "- legacy faq URL does not redirect to canonical slug path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== food_card_legacy_redirect ===\n";
if ($foodCardCanonicalPath === '' || $foodCardLegacyPath === '' || $foodCardCanonicalPath === $foodCardLegacyPath) {
    echo "OK\n";
} else {
    $legacyFoodProbe = fetchUrl($foodCardLegacyUrl, '', 0);
    $expectedLocation = 'Location: ' . $foodCardCanonicalPath;
    if (!str_contains($legacyFoodProbe['status'], '302')) {
        echo "- legacy food card URL does not redirect ({$legacyFoodProbe['status']})\n";
        $failures++;
    } elseif (!in_array($expectedLocation, $legacyFoodProbe['headers'], true)) {
        echo "- legacy food card URL does not redirect to canonical slug path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== gallery_album_legacy_redirect ===\n";
if ($galleryAlbumCanonicalPath === '' || $galleryAlbumLegacyPath === '' || $galleryAlbumCanonicalPath === $galleryAlbumLegacyPath) {
    echo "OK\n";
} else {
    $legacyGalleryAlbumProbe = fetchUrl($galleryAlbumLegacyUrl, '', 0);
    $expectedLocation = 'Location: ' . $galleryAlbumCanonicalPath;
    if (!str_contains($legacyGalleryAlbumProbe['status'], '302')) {
        echo "- legacy gallery album URL does not redirect ({$legacyGalleryAlbumProbe['status']})\n";
        $failures++;
    } elseif (!in_array($expectedLocation, $legacyGalleryAlbumProbe['headers'], true)) {
        echo "- legacy gallery album URL does not redirect to canonical slug path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== gallery_photo_legacy_redirect ===\n";
if ($galleryPhotoCanonicalPath === '' || $galleryPhotoLegacyPath === '' || $galleryPhotoCanonicalPath === $galleryPhotoLegacyPath) {
    echo "OK\n";
} else {
    $legacyGalleryPhotoProbe = fetchUrl($galleryPhotoLegacyUrl, '', 0);
    $expectedLocation = 'Location: ' . $galleryPhotoCanonicalPath;
    if (!str_contains($legacyGalleryPhotoProbe['status'], '302')) {
        echo "- legacy gallery photo URL does not redirect ({$legacyGalleryPhotoProbe['status']})\n";
        $failures++;
    } elseif (!in_array($expectedLocation, $legacyGalleryPhotoProbe['headers'], true)) {
        echo "- legacy gallery photo URL does not redirect to canonical slug path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== events_article_legacy_redirect ===\n";
if ($eventCanonicalPath === '' || $eventLegacyPath === '' || $eventCanonicalPath === $eventLegacyPath) {
    echo "OK\n";
} else {
    $legacyEventProbe = fetchUrl($eventLegacyUrl, '', 0);
    $expectedLocation = 'Location: ' . $eventCanonicalPath;
    if (!str_contains($legacyEventProbe['status'], '302')) {
        echo "- legacy event URL does not redirect ({$legacyEventProbe['status']})\n";
        $failures++;
    } elseif (!in_array($expectedLocation, $legacyEventProbe['headers'], true)) {
        echo "- legacy event URL does not redirect to canonical slug path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== board_article_legacy_redirect ===\n";
if ($boardCanonicalPath === '' || $boardLegacyPath === '' || $boardCanonicalPath === $boardLegacyPath) {
    echo "OK\n";
} else {
    $legacyBoardProbe = fetchUrl($boardLegacyUrl, '', 0);
    $expectedLocation = 'Location: ' . $boardCanonicalPath;
    if (!str_contains($legacyBoardProbe['status'], '302')) {
        echo "- legacy board document URL does not redirect ({$legacyBoardProbe['status']})\n";
        $failures++;
    } elseif (!in_array($expectedLocation, $legacyBoardProbe['headers'], true)) {
        echo "- legacy board document URL does not redirect to canonical slug path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== downloads_article_legacy_redirect ===\n";
if ($downloadCanonicalPath === '' || $downloadLegacyPath === '' || $downloadCanonicalPath === $downloadLegacyPath) {
    echo "OK\n";
} else {
    $legacyDownloadProbe = fetchUrl($downloadLegacyUrl, '', 0);
    $expectedLocation = 'Location: ' . $downloadCanonicalPath;
    if (!str_contains($legacyDownloadProbe['status'], '302')) {
        echo "- legacy download URL does not redirect ({$legacyDownloadProbe['status']})\n";
        $failures++;
    } elseif (!in_array($expectedLocation, $legacyDownloadProbe['headers'], true)) {
        echo "- legacy download URL does not redirect to canonical slug path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== places_article_legacy_redirect ===\n";
if ($placeCanonicalPath === '' || $placeLegacyPath === '' || $placeCanonicalPath === $placeLegacyPath) {
    echo "OK\n";
} else {
    $legacyPlaceProbe = fetchUrl($placeLegacyUrl, '', 0);
    $expectedLocation = 'Location: ' . $placeCanonicalPath;
    if (!str_contains($legacyPlaceProbe['status'], '302')) {
        echo "- legacy place URL does not redirect ({$legacyPlaceProbe['status']})\n";
        $failures++;
    } elseif (!in_array($expectedLocation, $legacyPlaceProbe['headers'], true)) {
        echo "- legacy place URL does not redirect to canonical slug path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== polls_article_legacy_redirect ===\n";
if ($pollCanonicalPath === '' || $pollLegacyPath === '' || $pollCanonicalPath === $pollLegacyPath) {
    echo "OK\n";
} else {
    $legacyPollProbe = fetchUrl($pollLegacyUrl, '', 0);
    $expectedLocation = 'Location: ' . $pollCanonicalPath;
    if (!str_contains($legacyPollProbe['status'], '302')) {
        echo "- legacy poll URL does not redirect ({$legacyPollProbe['status']})\n";
        $failures++;
    } elseif (!in_array($expectedLocation, $legacyPollProbe['headers'], true)) {
        echo "- legacy poll URL does not redirect to canonical slug path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== podcast_show_legacy_redirect ===\n";
if ($podcastShowSlug === '') {
    echo "OK\n";
} else {
    $legacyPodcastShowProbe = fetchUrl($baseUrl . '/podcast/show.php?slug=' . urlencode((string)$podcastShowSlug), '', 0);
    $expectedLocation = 'Location: ' . BASE_URL . '/podcast/' . rawurlencode((string)$podcastShowSlug);
    if (!str_contains($legacyPodcastShowProbe['status'], '302')) {
        echo "- legacy podcast show URL does not redirect ({$legacyPodcastShowProbe['status']})\n";
        $failures++;
    } elseif (!in_array($expectedLocation, $legacyPodcastShowProbe['headers'], true)) {
        echo "- legacy podcast show URL does not redirect to canonical slug path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== podcast_episode_legacy_redirect ===\n";
if ($podcastEpisodeCanonicalPath === '' || $podcastEpisodeLegacyPath === '' || $podcastEpisodeCanonicalPath === $podcastEpisodeLegacyPath) {
    echo "OK\n";
} else {
    $legacyPodcastEpisodeProbe = fetchUrl($podcastEpisodeLegacyUrl, '', 0);
    $expectedLocation = 'Location: ' . $podcastEpisodeCanonicalPath;
    if (!str_contains($legacyPodcastEpisodeProbe['status'], '302')) {
        echo "- legacy podcast episode URL does not redirect ({$legacyPodcastEpisodeProbe['status']})\n";
        $failures++;
    } elseif (!in_array($expectedLocation, $legacyPodcastEpisodeProbe['headers'], true)) {
        echo "- legacy podcast episode URL does not redirect to canonical slug path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== public_author_guard ===\n";
if ($runtimeAuditAuthorUrl === '') {
    echo "OK\n";
} else {
    $authorGuardIssues = [];
    $missingAuthorProbe = fetchUrl($baseUrl . '/author/runtimeaudit-chybejici-autor', '', 0);
    if (!str_contains($missingAuthorProbe['status'], '404')) {
        $authorGuardIssues[] = 'missing public author request does not return 404';
    }

    $pdo->prepare("UPDATE cms_users SET author_public_enabled = 0 WHERE id = ?")->execute([$runtimeAuditAuthorId]);
    clearSettingsCache();
    $disabledAuthorProbe = fetchUrl($runtimeAuditAuthorUrl, '', 0);
    if (!str_contains($disabledAuthorProbe['status'], '404')) {
        $authorGuardIssues[] = 'disabled public author request does not return 404';
    }
    $pdo->prepare("UPDATE cms_users SET author_public_enabled = 1 WHERE id = ?")->execute([$runtimeAuditAuthorId]);
    clearSettingsCache();

    if ($authorGuardIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($authorGuardIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
}

echo "=== public_registration_toggle ===\n";
$publicRegistrationIssues = [];
saveSetting('public_registration_enabled', '0');
clearSettingsCache();

$disabledRegisterProbe = fetchUrl($baseUrl . '/register.php', '', 0);
if (!str_contains($disabledRegisterProbe['status'], '403')) {
    $publicRegistrationIssues[] = 'register page does not return 403 when public registration is disabled';
}
if (!str_contains($disabledRegisterProbe['body'], 'Veřejná registrace je momentálně vypnutá')) {
    $publicRegistrationIssues[] = 'register page does not show disabled registration notice';
}
if (str_contains($disabledRegisterProbe['body'], 'name="password2"')) {
    $publicRegistrationIssues[] = 'register page still renders registration form when public registration is disabled';
}

$disabledLoginProbe = fetchUrl($baseUrl . '/public_login.php');
if (str_contains($disabledLoginProbe['body'], '/register.php')) {
    $publicRegistrationIssues[] = 'public login still exposes registration link when public registration is disabled';
}

$disabledHomeProbe = fetchUrl($baseUrl . '/');
if (str_contains($disabledHomeProbe['body'], '/register.php')) {
    $publicRegistrationIssues[] = 'home still exposes registration link when public registration is disabled';
}

saveSetting('public_registration_enabled', '1');
clearSettingsCache();

if ($publicRegistrationIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($publicRegistrationIssues as $issue) {
        echo '- ' . $issue . "\n";
    }
}

echo "=== role_access_matrix ===\n";
$roleAccessIssues = [];

$roleChecks = [];
if (isModuleEnabled('blog')) {
    $roleChecks[] = ['role' => 'author', 'url' => '/admin/blog.php', 'expected' => '200', 'label' => 'author blog'];
}
if (isModuleEnabled('news')) {
    $roleChecks[] = ['role' => 'author', 'url' => '/admin/news.php', 'expected' => '200', 'label' => 'author news'];
}
$roleChecks[] = ['role' => 'author', 'url' => '/admin/comments.php', 'expected' => '403', 'label' => 'author comments'];
$roleChecks[] = ['role' => 'author', 'url' => '/admin/settings.php', 'expected' => '403', 'label' => 'author settings'];
$roleChecks[] = ['role' => 'author', 'url' => '/admin/users.php', 'expected' => '403', 'label' => 'author users'];
$roleChecks[] = ['role' => 'author', 'url' => '/admin/review_queue.php', 'expected' => '403', 'label' => 'author review queue'];
if (isModuleEnabled('reservations')) {
    $roleChecks[] = ['role' => 'author', 'url' => '/admin/res_bookings.php', 'expected' => '403', 'label' => 'author reservations'];
    $roleChecks[] = ['role' => 'author', 'url' => '/admin/res_resources.php', 'expected' => '403', 'label' => 'author reservation resources'];
}
$roleChecks[] = ['role' => 'author', 'url' => '/admin/pages.php', 'expected' => '403', 'label' => 'author pages'];
if (isModuleEnabled('board')) {
    $roleChecks[] = ['role' => 'author', 'url' => '/admin/board.php', 'expected' => '403', 'label' => 'author board'];
}
if (isModuleEnabled('gallery')) {
    $roleChecks[] = ['role' => 'author', 'url' => '/admin/gallery_albums.php', 'expected' => '403', 'label' => 'author gallery'];
}
if (isModuleEnabled('food')) {
    $roleChecks[] = ['role' => 'author', 'url' => '/admin/food.php', 'expected' => '403', 'label' => 'author food'];
}
$roleChecks[] = ['role' => 'author', 'url' => '/admin/polls.php', 'expected' => '403', 'label' => 'author polls'];
$roleChecks[] = ['role' => 'author', 'url' => '/admin/downloads.php', 'expected' => '403', 'label' => 'author downloads'];
if (isModuleEnabled('faq')) {
    $roleChecks[] = ['role' => 'author', 'url' => '/admin/faq.php', 'expected' => '403', 'label' => 'author faq'];
}
if (isModuleEnabled('places')) {
    $roleChecks[] = ['role' => 'author', 'url' => '/admin/places.php', 'expected' => '403', 'label' => 'author places'];
}
if (isModuleEnabled('podcast')) {
    $roleChecks[] = ['role' => 'author', 'url' => '/admin/podcast_shows.php', 'expected' => '403', 'label' => 'author podcasts'];
}

if (isModuleEnabled('blog')) {
    $roleChecks[] = ['role' => 'moderator', 'url' => '/admin/comments.php', 'expected' => '200', 'label' => 'moderator comments'];
}
if (isModuleEnabled('blog') || isModuleEnabled('reservations')) {
    $roleChecks[] = ['role' => 'moderator', 'url' => '/admin/review_queue.php', 'expected' => '200', 'label' => 'moderator review queue'];
}
if (isModuleEnabled('contact')) {
    $roleChecks[] = ['role' => 'moderator', 'url' => '/admin/contact.php', 'expected' => '200', 'label' => 'moderator contact'];
}
if (isModuleEnabled('chat')) {
    $roleChecks[] = ['role' => 'moderator', 'url' => '/admin/chat.php', 'expected' => '200', 'label' => 'moderator chat'];
}
if (isModuleEnabled('blog')) {
    $roleChecks[] = ['role' => 'moderator', 'url' => '/admin/blog.php', 'expected' => '403', 'label' => 'moderator blog'];
}
if (isModuleEnabled('reservations')) {
    $roleChecks[] = ['role' => 'moderator', 'url' => '/admin/res_bookings.php', 'expected' => '403', 'label' => 'moderator reservations'];
    $roleChecks[] = ['role' => 'moderator', 'url' => '/admin/res_resources.php', 'expected' => '403', 'label' => 'moderator reservation resources'];
}
$roleChecks[] = ['role' => 'moderator', 'url' => '/admin/pages.php', 'expected' => '403', 'label' => 'moderator pages'];
if (isModuleEnabled('board')) {
    $roleChecks[] = ['role' => 'moderator', 'url' => '/admin/board.php', 'expected' => '403', 'label' => 'moderator board'];
}
if (isModuleEnabled('gallery')) {
    $roleChecks[] = ['role' => 'moderator', 'url' => '/admin/gallery_albums.php', 'expected' => '403', 'label' => 'moderator gallery'];
}
if (isModuleEnabled('food')) {
    $roleChecks[] = ['role' => 'moderator', 'url' => '/admin/food.php', 'expected' => '403', 'label' => 'moderator food'];
}
$roleChecks[] = ['role' => 'moderator', 'url' => '/admin/polls.php', 'expected' => '403', 'label' => 'moderator polls'];
$roleChecks[] = ['role' => 'moderator', 'url' => '/admin/downloads.php', 'expected' => '403', 'label' => 'moderator downloads'];
if (isModuleEnabled('faq')) {
    $roleChecks[] = ['role' => 'moderator', 'url' => '/admin/faq.php', 'expected' => '403', 'label' => 'moderator faq'];
}
if (isModuleEnabled('places')) {
    $roleChecks[] = ['role' => 'moderator', 'url' => '/admin/places.php', 'expected' => '403', 'label' => 'moderator places'];
}
if (isModuleEnabled('podcast')) {
    $roleChecks[] = ['role' => 'moderator', 'url' => '/admin/podcast_shows.php', 'expected' => '403', 'label' => 'moderator podcasts'];
}

if (isModuleEnabled('reservations')) {
    $roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/res_bookings.php', 'expected' => '200', 'label' => 'booking manager reservations'];
    $roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/res_resources.php', 'expected' => '200', 'label' => 'booking manager reservation resources'];
    $roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/res_categories.php', 'expected' => '200', 'label' => 'booking manager reservation categories'];
    $roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/res_locations.php', 'expected' => '200', 'label' => 'booking manager reservation locations'];
    $roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/review_queue.php', 'expected' => '200', 'label' => 'booking manager review queue'];
}
$roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/pages.php', 'expected' => '403', 'label' => 'booking manager pages'];
if (isModuleEnabled('blog')) {
    $roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/blog.php', 'expected' => '403', 'label' => 'booking manager blog'];
}
if (isModuleEnabled('board')) {
    $roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/board.php', 'expected' => '403', 'label' => 'booking manager board'];
}
if (isModuleEnabled('gallery')) {
    $roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/gallery_albums.php', 'expected' => '403', 'label' => 'booking manager gallery'];
}
if (isModuleEnabled('food')) {
    $roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/food.php', 'expected' => '403', 'label' => 'booking manager food'];
}
$roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/polls.php', 'expected' => '403', 'label' => 'booking manager polls'];
$roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/downloads.php', 'expected' => '403', 'label' => 'booking manager downloads'];
if (isModuleEnabled('faq')) {
    $roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/faq.php', 'expected' => '403', 'label' => 'booking manager faq'];
}
if (isModuleEnabled('places')) {
    $roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/places.php', 'expected' => '403', 'label' => 'booking manager places'];
}
if (isModuleEnabled('podcast')) {
    $roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/podcast_shows.php', 'expected' => '403', 'label' => 'booking manager podcasts'];
}
$roleChecks[] = ['role' => 'booking_manager', 'url' => '/admin/comments.php', 'expected' => '403', 'label' => 'booking manager comments'];

foreach ($roleChecks as $roleCheck) {
    $probe = fetchUrl(
        $baseUrl . $roleCheck['url'],
        'PHPSESSID=' . ($roleAuditSessions[$roleCheck['role']] ?? ''),
        0
    );
    if (!str_contains($probe['status'], $roleCheck['expected'])) {
        $roleAccessIssues[] = $roleCheck['label'] . ' returned ' . $probe['status'] . ' instead of ' . $roleCheck['expected'];
    }
}

$authorIndexProbe = fetchUrl($baseUrl . '/admin/index.php', 'PHPSESSID=' . ($roleAuditSessions['author'] ?? ''), 0);
if (!str_contains($authorIndexProbe['status'], '200')) {
    $roleAccessIssues[] = 'author dashboard is not accessible';
} else {
    if (str_contains($authorIndexProbe['body'], '/admin/review_queue.php')) {
        $roleAccessIssues[] = 'author dashboard still exposes review queue';
    }
    if (isModuleEnabled('blog') && !str_contains($authorIndexProbe['body'], '/admin/blog.php')) {
        $roleAccessIssues[] = 'author dashboard is missing blog navigation';
    }
    if (isModuleEnabled('news') && !str_contains($authorIndexProbe['body'], '/admin/news.php')) {
        $roleAccessIssues[] = 'author dashboard is missing news navigation';
    }
    if (str_contains($authorIndexProbe['body'], '/admin/comments.php')) {
        $roleAccessIssues[] = 'author dashboard still exposes comment moderation';
    }
    if (str_contains($authorIndexProbe['body'], '/admin/users.php')) {
        $roleAccessIssues[] = 'author dashboard still exposes user management';
    }
}

$moderatorIndexProbe = fetchUrl($baseUrl . '/admin/index.php', 'PHPSESSID=' . ($roleAuditSessions['moderator'] ?? ''), 0);
if (!str_contains($moderatorIndexProbe['status'], '200')) {
    $roleAccessIssues[] = 'moderator dashboard is not accessible';
} else {
    if ((isModuleEnabled('blog') || isModuleEnabled('reservations')) && !str_contains($moderatorIndexProbe['body'], '/admin/review_queue.php')) {
        $roleAccessIssues[] = 'moderator dashboard is missing review queue';
    }
    if (isModuleEnabled('blog') && !str_contains($moderatorIndexProbe['body'], '/admin/comments.php')) {
        $roleAccessIssues[] = 'moderator dashboard is missing comment moderation';
    }
    if (isModuleEnabled('contact') && !str_contains($moderatorIndexProbe['body'], '/admin/contact.php')) {
        $roleAccessIssues[] = 'moderator dashboard is missing contact moderation';
    }
    if (isModuleEnabled('chat') && !str_contains($moderatorIndexProbe['body'], '/admin/chat.php')) {
        $roleAccessIssues[] = 'moderator dashboard is missing chat moderation';
    }
    if (str_contains($moderatorIndexProbe['body'], '/admin/blog.php')) {
        $roleAccessIssues[] = 'moderator dashboard still exposes blog management';
    }
}

if (isModuleEnabled('reservations')) {
    $bookingIndexProbe = fetchUrl($baseUrl . '/admin/index.php', 'PHPSESSID=' . ($roleAuditSessions['booking_manager'] ?? ''), 0);
    if (!str_contains($bookingIndexProbe['status'], '200')) {
        $roleAccessIssues[] = 'booking manager dashboard is not accessible';
    } else {
        if (!str_contains($bookingIndexProbe['body'], '/admin/review_queue.php')) {
            $roleAccessIssues[] = 'booking manager dashboard is missing review queue';
        }
        if (!str_contains($bookingIndexProbe['body'], '/admin/res_bookings.php')) {
            $roleAccessIssues[] = 'booking manager dashboard is missing reservation management';
        }
        if (str_contains($bookingIndexProbe['body'], '/admin/comments.php')) {
            $roleAccessIssues[] = 'booking manager dashboard still exposes comment moderation';
        }
    }
}

if ($roleAccessIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($roleAccessIssues as $issue) {
        echo '- ' . $issue . "\n";
    }
}

$downloadsFileGuard = fetchUrl($baseUrl . '/downloads/file.php?id=-1', '', 0);
echo "=== downloads_file_guard ===\n";
if (!str_contains($downloadsFileGuard['status'], '404')) {
    echo "- invalid downloads file request does not return 404 ({$downloadsFileGuard['status']})\n";
    $failures++;
} else {
    echo "OK\n";
}

$sampleDownload = $pdo->query(
    "SELECT id FROM cms_downloads
     WHERE status = 'published' AND is_published = 1 AND filename <> ''
     ORDER BY id DESC
     LIMIT 1"
)->fetchColumn();
$sampleDownload = $downloadId !== false ? $downloadId : $sampleDownload;
echo "=== downloads_file ===\n";
if ($sampleDownload === false) {
    echo "OK\n";
} else {
    $downloadProbe = fetchUrl($baseUrl . '/downloads/file.php?id=' . (int)$sampleDownload, '', 0);
    if (!str_contains($downloadProbe['status'], '200')) {
        echo "- unexpected status: {$downloadProbe['status']}\n";
        $failures++;
    } elseif (!preg_grep('/^Content-Disposition: attachment; /', $downloadProbe['headers'])) {
        echo "- downloads file endpoint is missing attachment disposition\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

$boardFileGuard = fetchUrl($baseUrl . '/board/file.php?id=-1', '', 0);
echo "=== board_file_guard ===\n";
if (!str_contains($boardFileGuard['status'], '404')) {
    echo "- invalid board file request does not return 404 ({$boardFileGuard['status']})\n";
    $failures++;
} else {
    echo "OK\n";
}

$sampleBoard = $pdo->query(
    "SELECT id FROM cms_board
     WHERE status = 'published' AND is_published = 1 AND filename <> ''
     ORDER BY id DESC
     LIMIT 1"
)->fetchColumn();
echo "=== board_file ===\n";
if ($sampleBoard === false) {
    echo "OK\n";
} else {
    $boardProbe = fetchUrl($baseUrl . '/board/file.php?id=' . (int)$sampleBoard, '', 0);
    if (!str_contains($boardProbe['status'], '200')) {
        echo "- unexpected status: {$boardProbe['status']}\n";
        $failures++;
    } elseif (!preg_grep('/^Content-Disposition: attachment; /', $boardProbe['headers'])) {
        echo "- board file endpoint is missing attachment disposition\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== content_shortcodes ===\n";
if ($articleId === false) {
    echo "OK\n";
} else {
    $contentShortcodeIssues = [];
    $originalArticleContent = '';
    try {
        $originalContentStmt = $pdo->prepare("SELECT content FROM cms_articles WHERE id = ?");
        $originalContentStmt->execute([(int)$articleId]);
        $originalArticleContent = (string)($originalContentStmt->fetchColumn() ?? '');

        $shortcodeContent = <<<HTML
<p>Runtime audit shortcode test.</p>
[audio src="/downloads/file.php?id=123" mime="audio/mpeg"][/audio]
[video src="/downloads/file.php?id=321" mime="video/mp4"][/video]
HTML;

        if (!empty($galleryAlbumRow['slug'])) {
            $shortcodeContent .= "\n[gallery]" . (string)$galleryAlbumRow['slug'] . "[/gallery]\n";
        }

        $pdo->prepare("UPDATE cms_articles SET content = ? WHERE id = ?")->execute([
            $shortcodeContent,
            (int)$articleId,
        ]);

        $shortcodeProbe = fetchUrl($articleCanonicalUrl, '', 0);
        if (!str_contains($shortcodeProbe['status'], '200')) {
            $contentShortcodeIssues[] = 'blog article did not load after inserting content shortcodes';
        } else {
            if (!str_contains($shortcodeProbe['body'], '<audio class="audio-player" controls preload="metadata">')) {
                $contentShortcodeIssues[] = 'audio shortcode was not rendered as html5 player';
            }
            if (!str_contains($shortcodeProbe['body'], 'src="/downloads/file.php?id=123" type="audio/mpeg"')) {
                $contentShortcodeIssues[] = 'audio shortcode with safe download endpoint and mime attribute was not rendered';
            }
            if (!str_contains($shortcodeProbe['body'], '<video class="video-player" controls preload="metadata">')) {
                $contentShortcodeIssues[] = 'video shortcode was not rendered as html5 player';
            }
            if (!str_contains($shortcodeProbe['body'], 'src="/downloads/file.php?id=321" type="video/mp4"')) {
                $contentShortcodeIssues[] = 'video shortcode with safe download endpoint and mime attribute was not rendered';
            }
            if (!empty($galleryAlbumRow['slug']) && !str_contains($shortcodeProbe['body'], 'content-gallery-embed')) {
                $contentShortcodeIssues[] = 'gallery shortcode was not rendered as embedded gallery';
            }
        }
    } finally {
        $pdo->prepare("UPDATE cms_articles SET content = ? WHERE id = ?")->execute([
            $originalArticleContent,
            (int)$articleId,
        ]);
    }

    if ($contentShortcodeIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($contentShortcodeIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
}

echo "=== comment_moderation_guards ===\n";
if ($articleId === false) {
    echo "OK\n";
} else {
    $commentGuardIssues = [];
    $originalCommentsEnabled = getSetting('comments_enabled', '1');
    $originalCommentMode = getSetting('comment_moderation_mode', 'always');
    $originalCommentCloseDays = getSetting('comment_close_days', '0');
    $articleCommentsColumnExists = false;
    $originalArticleCommentsEnabled = '1';
    try {
        $articleCommentsStmt = $pdo->prepare("SELECT comments_enabled FROM cms_articles WHERE id = ?");
        $articleCommentsStmt->execute([(int)$articleId]);
        $originalArticleCommentsEnabled = (string)($articleCommentsStmt->fetchColumn() ?? '1');
        $articleCommentsColumnExists = true;
    } catch (\PDOException $e) {
        $articleCommentsColumnExists = false;
    }

    try {
        saveSetting('comments_enabled', '0');
        clearSettingsCache();

        $globalDisabledProbe = fetchUrl($articleCanonicalUrl, '', 0);
        if (!str_contains($globalDisabledProbe['status'], '200')) {
            $commentGuardIssues[] = 'blog article did not load after disabling comments globally';
        } else {
            if (str_contains($globalDisabledProbe['body'], 'name="comment"')) {
                $commentGuardIssues[] = 'comment form remained visible after disabling comments globally';
            }
            if (!str_contains($globalDisabledProbe['body'], 'Komentáře jsou na tomto webu vypnuté.')) {
                $commentGuardIssues[] = 'missing public message for globally disabled comments';
            }
        }

        saveSetting('comments_enabled', $originalCommentsEnabled);
        saveSetting('comment_moderation_mode', $originalCommentMode);
        saveSetting('comment_close_days', $originalCommentCloseDays);
        clearSettingsCache();

        if ($articleCommentsColumnExists) {
            $pdo->prepare("UPDATE cms_articles SET comments_enabled = 0 WHERE id = ?")->execute([(int)$articleId]);
        }
        if ($articleCommentsColumnExists) {
            $articleDisabledProbe = fetchUrl($articleCanonicalUrl, '', 0);
        if (!str_contains($articleDisabledProbe['status'], '200')) {
            $commentGuardIssues[] = 'blog article did not load after disabling comments on article';
        } else {
            if (str_contains($articleDisabledProbe['body'], 'name="comment"')) {
                $commentGuardIssues[] = 'comment form remained visible after disabling comments on article';
            }
            if (!str_contains($articleDisabledProbe['body'], 'Komentáře jsou u tohoto článku vypnuté.')) {
                $commentGuardIssues[] = 'missing public message for article-level disabled comments';
            }
        }
        }
    } finally {
        saveSetting('comments_enabled', $originalCommentsEnabled);
        saveSetting('comment_moderation_mode', $originalCommentMode);
        saveSetting('comment_close_days', $originalCommentCloseDays);
        clearSettingsCache();
        if ($articleCommentsColumnExists) {
            $pdo->prepare("UPDATE cms_articles SET comments_enabled = ? WHERE id = ?")->execute([
                (int)$originalArticleCommentsEnabled,
                (int)$articleId,
            ]);
        }
    }

    if ($commentGuardIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($commentGuardIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
}

echo "=== comment_spam_filters ===\n";
if ($articleId === false) {
    echo "OK\n";
} else {
    $spamIssues = [];
    $originalCommentsEnabled = getSetting('comments_enabled', '1');
    $originalCommentMode = getSetting('comment_moderation_mode', 'always');
    $originalNotifyAdmin = getSetting('comment_notify_admin', '1');
    $originalBlockedEmails = getSetting('comment_blocked_emails', '');
    $originalSpamWords = getSetting('comment_spam_words', '');
    $articleCommentsColumnExists = false;
    $originalArticleCommentsEnabled = '1';
    $createdCommentIds = [];

    try {
        $articleCommentsStmt = $pdo->prepare("SELECT comments_enabled FROM cms_articles WHERE id = ?");
        $articleCommentsStmt->execute([(int)$articleId]);
        $originalArticleCommentsEnabled = (string)($articleCommentsStmt->fetchColumn() ?? '1');
        $articleCommentsColumnExists = true;
    } catch (\PDOException $e) {
        $articleCommentsColumnExists = false;
    }

    try {
        $pdo->exec("DELETE FROM cms_rate_limit");
        saveSetting('comments_enabled', '1');
        saveSetting('comment_moderation_mode', 'always');
        saveSetting('comment_notify_admin', '0');
        saveSetting('comment_blocked_emails', '');
        saveSetting('comment_spam_words', '');
        clearSettingsCache();
        if ($articleCommentsColumnExists) {
            $pdo->prepare("UPDATE cms_articles SET comments_enabled = 1 WHERE id = ?")->execute([(int)$articleId]);
        }

        $baseCommentUrl = $articleCanonicalUrl;

        $blockedEmail = 'runtimeaudit-blocked-' . bin2hex(random_bytes(4)) . '@example.test';
        saveSetting('comment_blocked_emails', $blockedEmail);
        clearSettingsCache();

        $blockedCookie = 'PHPSESSID=runtimeauditcommentblocked';
        $blockedForm = fetchUrl($baseCommentUrl, $blockedCookie, 0);
        $blockedCsrf = extractHiddenInputValue($blockedForm['body'], 'csrf_token');
        $blockedCaptcha = extractCaptchaAnswer($blockedForm['body']);
        if ($blockedCsrf === '' || $blockedCaptcha === null) {
            $spamIssues[] = 'could not extract blocked-email comment form token or captcha';
        } else {
            $blockedPost = postUrl(
                $baseCommentUrl,
                [
                    'csrf_token' => $blockedCsrf,
                    'author_name' => 'Runtime Audit Blocked',
                    'author_email' => $blockedEmail,
                    'comment' => 'Tento komentář má skončit ve spamu kvůli blokovanému e-mailu.',
                    'captcha' => (string)$blockedCaptcha,
                    'hp_website' => '',
                ],
                $blockedCookie,
                0
            );
            if (!str_contains($blockedPost['status'], '302')) {
                $spamIssues[] = 'blocked-email comment did not redirect after submit';
            } elseif (!responseHasLocationHeader($blockedPost['headers'], articlePublicPath($articleRow, ['komentar' => 'pending']), $baseUrl)) {
                $spamIssues[] = 'blocked-email comment did not use neutral pending redirect';
            }

            $stmt = $pdo->prepare(
                "SELECT id, status FROM cms_comments WHERE author_email = ? ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$blockedEmail]);
            $blockedRow = $stmt->fetch();
            if (!$blockedRow) {
                $spamIssues[] = 'blocked-email comment was not stored';
            } else {
                $createdCommentIds[] = (int)$blockedRow['id'];
                if (($blockedRow['status'] ?? '') !== 'spam') {
                    $spamIssues[] = 'blocked-email comment did not land in spam';
                }
            }
        }

        $spamPhrase = 'runtimeaudit-spam-' . bin2hex(random_bytes(4));
        saveSetting('comment_blocked_emails', '');
        saveSetting('comment_spam_words', $spamPhrase);
        clearSettingsCache();

        $phraseCookie = 'PHPSESSID=runtimeauditcommentphrase';
        $phraseForm = fetchUrl($baseCommentUrl, $phraseCookie, 0);
        $phraseCsrf = extractHiddenInputValue($phraseForm['body'], 'csrf_token');
        $phraseCaptcha = extractCaptchaAnswer($phraseForm['body']);
        $phraseEmail = 'runtimeaudit-phrase-' . bin2hex(random_bytes(4)) . '@example.test';
        if ($phraseCsrf === '' || $phraseCaptcha === null) {
            $spamIssues[] = 'could not extract spam-phrase comment form token or captcha';
        } else {
            $phrasePost = postUrl(
                $baseCommentUrl,
                [
                    'csrf_token' => $phraseCsrf,
                    'author_name' => 'Runtime Audit Phrase',
                    'author_email' => $phraseEmail,
                    'comment' => 'Komentář obsahuje frázi ' . $spamPhrase . ' a má skončit ve spamu.',
                    'captcha' => (string)$phraseCaptcha,
                    'hp_website' => '',
                ],
                $phraseCookie,
                0
            );
            if (!str_contains($phrasePost['status'], '302')) {
                $spamIssues[] = 'spam-phrase comment did not redirect after submit';
            }

            $stmt = $pdo->prepare(
                "SELECT id, status FROM cms_comments WHERE author_email = ? ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$phraseEmail]);
            $phraseRow = $stmt->fetch();
            if (!$phraseRow) {
                $spamIssues[] = 'spam-phrase comment was not stored';
            } else {
                $createdCommentIds[] = (int)$phraseRow['id'];
                if (($phraseRow['status'] ?? '') !== 'spam') {
                    $spamIssues[] = 'spam-phrase comment did not land in spam';
                }
            }
        }

        saveSetting('comment_spam_words', '');
        clearSettingsCache();

        $pendingCookie = 'PHPSESSID=runtimeauditcommentpending';
        $pendingForm = fetchUrl($baseCommentUrl, $pendingCookie, 0);
        $pendingCsrf = extractHiddenInputValue($pendingForm['body'], 'csrf_token');
        $pendingCaptcha = extractCaptchaAnswer($pendingForm['body']);
        $pendingEmail = 'runtimeaudit-pending-' . bin2hex(random_bytes(4)) . '@example.test';
        if ($pendingCsrf === '' || $pendingCaptcha === null) {
            $spamIssues[] = 'could not extract pending comment form token or captcha';
        } else {
            $pendingPost = postUrl(
                $baseCommentUrl,
                [
                    'csrf_token' => $pendingCsrf,
                    'author_name' => 'Runtime Audit Pending',
                    'author_email' => $pendingEmail,
                    'comment' => 'Tento komentář má čekat na schválení.',
                    'captcha' => (string)$pendingCaptcha,
                    'hp_website' => '',
                ],
                $pendingCookie,
                0
            );
            if (!str_contains($pendingPost['status'], '302')) {
                $spamIssues[] = 'pending comment did not redirect after submit';
            } elseif (!responseHasLocationHeader($pendingPost['headers'], articlePublicPath($articleRow, ['komentar' => 'pending']), $baseUrl)) {
                $spamIssues[] = 'pending comment did not redirect to pending state';
            }

            $stmt = $pdo->prepare(
                "SELECT id, status FROM cms_comments WHERE author_email = ? ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$pendingEmail]);
            $pendingRow = $stmt->fetch();
            if (!$pendingRow) {
                $spamIssues[] = 'pending comment was not stored';
            } else {
                $createdCommentIds[] = (int)$pendingRow['id'];
                if (($pendingRow['status'] ?? '') !== 'pending') {
                    $spamIssues[] = 'normal comment did not stay pending under always moderation';
                }
            }
        }
    } finally {
        saveSetting('comments_enabled', $originalCommentsEnabled);
        saveSetting('comment_moderation_mode', $originalCommentMode);
        saveSetting('comment_notify_admin', $originalNotifyAdmin);
        saveSetting('comment_blocked_emails', $originalBlockedEmails);
        saveSetting('comment_spam_words', $originalSpamWords);
        $pdo->exec("DELETE FROM cms_rate_limit");
        clearSettingsCache();
        if ($articleCommentsColumnExists) {
            $pdo->prepare("UPDATE cms_articles SET comments_enabled = ? WHERE id = ?")->execute([
                (int)$originalArticleCommentsEnabled,
                (int)$articleId,
            ]);
        }

        if ($createdCommentIds !== []) {
            $placeholders = implode(',', array_fill(0, count($createdCommentIds), '?'));
            $pdo->prepare("DELETE FROM cms_comments WHERE id IN ({$placeholders})")->execute($createdCommentIds);
        }
    }

    if ($spamIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($spamIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
}

saveSetting('home_author_user_id', $runtimeAuditOriginalHomeAuthorUserId);
saveSetting('blog_authors_index_enabled', $runtimeAuditOriginalBlogAuthorsIndexEnabled);
saveSetting('public_registration_enabled', $runtimeAuditOriginalPublicRegistrationEnabled);
saveSetting('github_issues_enabled', $runtimeAuditOriginalGitHubIssuesEnabled);
saveSetting('github_issues_repository', $runtimeAuditOriginalGitHubIssuesRepository);
saveSetting($runtimeAuditThemeSettingsKey, $runtimeAuditOriginalThemeSettings);
foreach ($runtimeAuditOriginalModuleSettings as $moduleSettingKey => $moduleSettingValue) {
    saveSetting($moduleSettingKey, $moduleSettingValue);
}
clearSettingsCache();
if ($articleId !== false && $runtimeAuditOriginalArticleAuthorId !== null) {
    $pdo->prepare("UPDATE cms_articles SET author_id = ? WHERE id = ?")->execute([
        (int)$runtimeAuditOriginalArticleAuthorId,
        (int)$articleId,
    ]);
}
if ($newsId !== false && $runtimeAuditOriginalNewsAuthorId !== null) {
    $pdo->prepare("UPDATE cms_news SET author_id = ? WHERE id = ?")->execute([
        (int)$runtimeAuditOriginalNewsAuthorId,
        (int)$newsId,
    ]);
}

if (!empty($cleanup['public_user_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['public_user_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_users WHERE id IN ({$placeholders})")->execute($cleanup['public_user_ids']);
}
if (!empty($cleanup['confirm_emails'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['confirm_emails']), '?'));
    $pdo->prepare("DELETE FROM cms_users WHERE email IN ({$placeholders})")->execute($cleanup['confirm_emails']);
}
if (!empty($cleanup['subscriber_emails'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['subscriber_emails']), '?'));
    $pdo->prepare("DELETE FROM cms_subscribers WHERE email IN ({$placeholders})")->execute($cleanup['subscriber_emails']);
}

if (!empty($cleanup['newsletter_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['newsletter_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_newsletters WHERE id IN ({$placeholders})")->execute($cleanup['newsletter_ids']);
}
if (!empty($cleanup['contact_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['contact_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_contact WHERE id IN ({$placeholders})")->execute($cleanup['contact_ids']);
}
if (!empty($cleanup['chat_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['chat_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_chat WHERE id IN ({$placeholders})")->execute($cleanup['chat_ids']);
}
if (!empty($cleanup['board_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['board_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_board WHERE id IN ({$placeholders})")->execute($cleanup['board_ids']);
}
if (!empty($cleanup['download_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['download_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_downloads WHERE id IN ({$placeholders})")->execute($cleanup['download_ids']);
}
foreach ($cleanup['download_files'] as $downloadFile) {
    deleteDownloadStoredFile((string)$downloadFile);
}
if (!empty($cleanup['faq_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['faq_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_faqs WHERE id IN ({$placeholders})")->execute($cleanup['faq_ids']);
}
if (!empty($cleanup['food_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['food_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_food_cards WHERE id IN ({$placeholders})")->execute($cleanup['food_ids']);
}
if (!empty($cleanup['gallery_photo_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['gallery_photo_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_gallery_photos WHERE id IN ({$placeholders})")->execute($cleanup['gallery_photo_ids']);
}
if (!empty($cleanup['gallery_album_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['gallery_album_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_gallery_albums WHERE id IN ({$placeholders})")->execute($cleanup['gallery_album_ids']);
}
foreach ($cleanup['gallery_files'] as $galleryFile) {
    @unlink(__DIR__ . '/../uploads/gallery/' . $galleryFile);
    @unlink(__DIR__ . '/../uploads/gallery/thumbs/' . $galleryFile);
}
if (!empty($cleanup['place_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['place_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_places WHERE id IN ({$placeholders})")->execute($cleanup['place_ids']);
}
if (!empty($cleanup['form_submission_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['form_submission_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_form_submission_history WHERE submission_id IN ({$placeholders})")->execute($cleanup['form_submission_ids']);
    $pdo->prepare("DELETE FROM cms_form_submissions WHERE id IN ({$placeholders})")->execute($cleanup['form_submission_ids']);
}
if (!empty($cleanup['form_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['form_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_form_submission_history WHERE submission_id IN (SELECT id FROM cms_form_submissions WHERE form_id IN ({$placeholders}))")->execute($cleanup['form_ids']);
    $pdo->prepare("DELETE FROM cms_form_submissions WHERE form_id IN ({$placeholders})")->execute($cleanup['form_ids']);
    $pdo->prepare("DELETE FROM cms_form_fields WHERE form_id IN ({$placeholders})")->execute($cleanup['form_ids']);
    $pdo->prepare("DELETE FROM cms_forms WHERE id IN ({$placeholders})")->execute($cleanup['form_ids']);
}
if (!empty($cleanup['poll_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['poll_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_poll_votes WHERE poll_id IN ({$placeholders})")->execute($cleanup['poll_ids']);
    $pdo->prepare("DELETE FROM cms_poll_options WHERE poll_id IN ({$placeholders})")->execute($cleanup['poll_ids']);
    $pdo->prepare("DELETE FROM cms_polls WHERE id IN ({$placeholders})")->execute($cleanup['poll_ids']);
}
if (!empty($cleanup['podcast_episode_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['podcast_episode_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_podcasts WHERE id IN ({$placeholders})")->execute($cleanup['podcast_episode_ids']);
}
if (!empty($cleanup['podcast_show_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['podcast_show_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_podcast_shows WHERE id IN ({$placeholders})")->execute($cleanup['podcast_show_ids']);
}
if (!empty($cleanup['author_user_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['author_user_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_users WHERE id IN ({$placeholders})")->execute($cleanup['author_user_ids']);
}
if (!empty($cleanup['staff_user_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['staff_user_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_users WHERE id IN ({$placeholders})")->execute($cleanup['staff_user_ids']);
}

$installProbe = fetchUrl($baseUrl . '/install.php', '', 0);
echo "=== install_guard ===\n";
if (!str_contains($installProbe['status'], '302')) {
    echo "- unexpected status: {$installProbe['status']}\n";
    $failures++;
} elseif (!in_array('Location: /admin/index.php', $installProbe['headers'], true)) {
    echo "- install.php does not redirect to /admin/index.php on installed site\n";
    $failures++;
} else {
    echo "OK\n";
}

$migrateProbe = fetchUrl($baseUrl . '/migrate.php', '', 0);
echo "=== migrate_guard ===\n";
if (!str_contains($migrateProbe['status'], '302')) {
    echo "- unexpected status: {$migrateProbe['status']}\n";
    $failures++;
} elseif (!in_array('Location: /admin/login.php', $migrateProbe['headers'], true)) {
    echo "- migrate.php does not redirect anonymous user to /admin/login.php\n";
    $failures++;
} else {
    echo "OK\n";
}

$migrateConfirm = fetchUrl($baseUrl . '/migrate.php', 'PHPSESSID=' . $auditSessionId, 0);
echo "=== migrate_confirm ===\n";
if (!str_contains($migrateConfirm['status'], '200')) {
    echo "- unexpected status: {$migrateConfirm['status']}\n";
    $failures++;
} elseif (!str_contains($migrateConfirm['body'], 'Spustit migraci') || str_contains($migrateConfirm['body'], 'Hotovo.')) {
    echo "- migrate.php GET does not show confirmation screen safely\n";
    $failures++;
} else {
    echo "OK\n";
}

$originalActiveTheme = getSetting('active_theme', defaultThemeName());
$originalSiteProfile = getSetting('site_profile', 'custom');
echo "=== custom_profile_theme_guard ===\n";
try {
    $customProfileIssues = [];
    $customProfileTheme = defaultThemeName();
    foreach (availableThemes() as $themeKey) {
        if ($themeKey !== 'editorial') {
            $customProfileTheme = $themeKey;
            break;
        }
    }

    saveSetting('active_theme', $customProfileTheme);
    saveSetting('site_profile', 'custom');
    clearSettingsCache();

    $settingsPayload = [
        'csrf_token' => $adminCsrfToken,
        'site_name' => getSetting('site_name', 'Kora CMS'),
        'site_description' => getSetting('site_description', ''),
        'contact_email' => getSetting('contact_email', ''),
        'site_profile' => 'custom',
        'apply_site_profile' => '1',
        'board_public_label' => getSetting('board_public_label', defaultBoardPublicLabelForProfile('custom')),
        'home_blog_count' => getSetting('home_blog_count', '5'),
        'home_news_count' => getSetting('home_news_count', '5'),
        'home_board_count' => getSetting('home_board_count', '5'),
        'news_per_page' => getSetting('news_per_page', '10'),
        'blog_per_page' => getSetting('blog_per_page', '10'),
        'events_per_page' => getSetting('events_per_page', '10'),
        'content_editor' => getSetting('content_editor', 'html'),
        'social_facebook' => getSetting('social_facebook', ''),
        'social_youtube' => getSetting('social_youtube', ''),
        'social_instagram' => getSetting('social_instagram', ''),
        'social_twitter' => getSetting('social_twitter', ''),
        'maintenance_text' => getSetting('maintenance_text', ''),
        'cookie_consent_text' => getSetting('cookie_consent_text', ''),
        'og_image_default' => getSetting('og_image_default', ''),
        'home_intro' => getSetting('home_intro', ''),
    ];

    if (getSetting('maintenance_mode', '0') === '1') {
        $settingsPayload['maintenance_mode'] = '1';
    }
    if (getSetting('cookie_consent_enabled', '0') === '1') {
        $settingsPayload['cookie_consent_enabled'] = '1';
    }
    if (isModuleEnabled('blog')) {
        $settingsPayload['comment_moderation_mode'] = getSetting('comment_moderation_mode', 'always');
        $settingsPayload['comment_close_days'] = getSetting('comment_close_days', '0');
        $settingsPayload['comment_notify_email'] = getSetting('comment_notify_email', '');
        $settingsPayload['comment_blocked_emails'] = getSetting('comment_blocked_emails', '');
        $settingsPayload['comment_spam_words'] = getSetting('comment_spam_words', '');

        if (getSetting('blog_authors_index_enabled', '0') === '1') {
            $settingsPayload['blog_authors_index_enabled'] = '1';
        }
        if (getSetting('comments_enabled', '1') === '1') {
            $settingsPayload['comments_enabled'] = '1';
        }
        if (getSetting('comment_notify_admin', '1') === '1') {
            $settingsPayload['comment_notify_admin'] = '1';
        }
        if (getSetting('comment_notify_author_approve', '0') === '1') {
            $settingsPayload['comment_notify_author_approve'] = '1';
        }
    }

    $customProfileResult = postUrl(
        $baseUrl . '/admin/settings.php',
        $settingsPayload,
        'PHPSESSID=' . $auditSessionId,
        0
    );

    if (!str_contains($customProfileResult['status'], '200')) {
        $customProfileIssues[] = 'custom profile settings save did not return 200';
    }
    if (!str_contains($customProfileResult['body'], 'Vlastní profil byl uložen bez zásahu do stávajících modulů a vzhledu.')) {
        $customProfileIssues[] = 'custom profile save did not confirm non-destructive apply';
    }

    clearSettingsCache();
    $persistedCustomProfileTheme = (string)$pdo->query(
        "SELECT value FROM cms_settings WHERE `key` = 'active_theme'"
    )->fetchColumn();
    $persistedCustomProfileKey = (string)$pdo->query(
        "SELECT value FROM cms_settings WHERE `key` = 'site_profile'"
    )->fetchColumn();
    if ($persistedCustomProfileTheme !== $customProfileTheme) {
        $customProfileIssues[] = 'custom profile apply changed active theme';
    }
    if ($persistedCustomProfileKey !== 'custom') {
        $customProfileIssues[] = 'custom profile apply did not keep site_profile=custom';
    }

    if ($customProfileIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($customProfileIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
} finally {
    saveSetting('site_profile', $originalSiteProfile);
    saveSetting('active_theme', $originalActiveTheme);
    clearSettingsCache();
}

echo "=== theme_catalog ===\n";
try {
    $catalogIssues = [];
    foreach (availableThemes() as $themeKey) {
        saveSetting('active_theme', $themeKey);
        clearSettingsCache();

        $themeHomeProbe = fetchUrl($baseUrl . '/', '', 0);
        $themeAssetProbe = fetchUrl($baseUrl . '/themes/' . rawurlencode($themeKey) . '/assets/public.css', '', 0);
        $previewAssetUrl = themePreviewAssetUrl($themeKey);

        if (!str_contains($themeHomeProbe['status'], '200')) {
            $catalogIssues[] = "{$themeKey}: unexpected homepage status {$themeHomeProbe['status']}";
        }
        if (!str_contains($themeAssetProbe['status'], '200')) {
            $catalogIssues[] = "{$themeKey}: theme stylesheet is not reachable";
        }
        if (!str_contains($themeHomeProbe['body'], '/themes/' . rawurlencode($themeKey) . '/assets/public.css')) {
            $catalogIssues[] = "{$themeKey}: homepage does not reference its theme stylesheet";
        }
        if (!str_contains($themeHomeProbe['body'], 'theme-' . $themeKey)) {
            $catalogIssues[] = "{$themeKey}: homepage body class is missing";
        }
        if ($previewAssetUrl !== '') {
            $previewAssetProbe = fetchUrl($baseUrl . parse_url($previewAssetUrl, PHP_URL_PATH), '', 0);
            if (!str_contains($previewAssetProbe['status'], '200')) {
                $catalogIssues[] = "{$themeKey}: preview asset is not reachable";
            }
        }
    }

    if ($catalogIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($catalogIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
} finally {
    saveSetting('active_theme', $originalActiveTheme);
    clearSettingsCache();
}

echo "=== theme_live_preview ===\n";
try {
    session_id($auditSessionId);
    session_start();
    clearThemePreview();
    session_write_close();

    $availablePreviewThemes = availableThemes();
    $previewTheme = $availablePreviewThemes[0] ?? defaultThemeName();
    foreach ($availablePreviewThemes as $themeKey) {
        if ($themeKey !== $originalActiveTheme) {
            $previewTheme = $themeKey;
            break;
        }
    }

    $previewIssues = [];
    $previewStart = postUrl(
        $baseUrl . '/admin/themes.php',
        [
            'csrf_token' => $adminCsrfToken,
            'form_action' => 'preview_theme',
            'active_theme' => $previewTheme,
            'preview_redirect' => '/index.php',
        ],
        'PHPSESSID=' . $auditSessionId,
        0
    );
    if (!str_contains($previewStart['status'], '302')) {
        $previewIssues[] = 'preview start did not redirect safely';
    }

    $previewHome = fetchUrl($baseUrl . '/index.php', 'PHPSESSID=' . $auditSessionId, 0);
    if (!str_contains($previewHome['status'], '200')) {
        $previewIssues[] = 'preview homepage did not load';
    }
    if (!str_contains($previewHome['body'], '/themes/' . rawurlencode($previewTheme) . '/assets/public.css')) {
        $previewIssues[] = 'preview homepage did not switch theme stylesheet';
    }
    if (!str_contains($previewHome['body'], 'theme-preview-banner')) {
        $previewIssues[] = 'preview banner was not rendered';
    }

    $previewStop = postUrl(
        $baseUrl . '/admin/theme_preview.php',
        [
            'csrf_token' => $adminCsrfToken,
            'preview_action' => 'clear',
            'redirect_target' => '/index.php',
        ],
        'PHPSESSID=' . $auditSessionId,
        0
    );
    if (!str_contains($previewStop['status'], '302')) {
        $previewIssues[] = 'preview stop did not redirect safely';
    }

    $postPreviewHome = fetchUrl($baseUrl . '/index.php', 'PHPSESSID=' . $auditSessionId, 0);
    if (str_contains($postPreviewHome['body'], 'theme-preview-banner')) {
        $previewIssues[] = 'preview banner remained visible after stopping preview';
    }
    if (!str_contains($postPreviewHome['body'], '/themes/' . rawurlencode($originalActiveTheme) . '/assets/public.css')) {
        $previewIssues[] = 'homepage did not return to the active theme after preview';
    }

    if ($previewIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($previewIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
} finally {
    session_id($auditSessionId);
    session_start();
    clearThemePreview();
    session_write_close();
}

echo "=== theme_package_roundtrip ===\n";
$roundtripThemeKey = 'runtimeaudit-theme-' . bin2hex(random_bytes(4));
$roundtripZipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $roundtripThemeKey . '.zip';
try {
    $packageManifest = [
        'key' => $roundtripThemeKey,
        'package' => [
            'type' => themePortablePackageType(),
            'schema' => themePortablePackageSchema(),
            'mode' => themePortablePackageMode(),
            'base_theme' => defaultThemeName(),
        ],
        'name' => 'Runtime Audit Theme',
        'version' => '1.0.0',
        'author' => 'Runtime Audit',
        'description' => 'Dočasný portable ZIP balíček pro runtime audit.',
        'preview' => [
            'summary' => 'Kontrola bezpečného importu a exportu šablon.',
            'colors' => ['#edf3ff', '#225577', '#a35c1a'],
        ],
        'settings_defaults' => [
            'header_layout' => 'split',
            'palette_preset' => 'slate',
            'accent' => '#225577',
            'accent_strong' => '#163b5c',
            'warm' => '#a35c1a',
            'font_pairing' => 'modern',
            'container_width' => 'wide',
        ],
    ];
    $packageCss = '@import url("../../default/assets/public.css");' . "\n"
        . 'body.theme-' . $roundtripThemeKey . ' { --radius-lg: 1.15rem; }' . "\n"
        . '.theme-' . $roundtripThemeKey . ' .site-header__panel { border-color: rgba(var(--accent-rgb), 0.22); }' . "\n";

    if (!themeCreateZipArchive($roundtripZipPath, [
        $roundtripThemeKey . '/theme.json' => json_encode(
            $packageManifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . "\n",
        $roundtripThemeKey . '/assets/public.css' => $packageCss,
    ])) {
        throw new RuntimeException('Nepodařilo se vytvořit ZIP balíček pro runtime audit.');
    }

    $roundtripIssues = [];
    $originalRoundtripSiteProfile = getSetting('site_profile', 'custom');
    $importResult = postMultipartUrl(
        $baseUrl . '/admin/themes.php',
        [
            'csrf_token' => $adminCsrfToken,
            'form_action' => 'import_theme_package',
        ],
        [
            'theme_package' => [
                'path' => $roundtripZipPath,
                'filename' => basename($roundtripZipPath),
                'type' => 'application/zip',
            ],
        ],
        'PHPSESSID=' . $auditSessionId,
        0
    );
    if (!str_contains($importResult['status'], '200')) {
        $roundtripIssues[] = 'theme package import did not return 200';
    }
    if (!themeExists($roundtripThemeKey)) {
        $roundtripIssues[] = 'imported theme directory was not created';
    }

    saveSetting('site_profile', 'personal');
    clearSettingsCache();

    $activateResult = postUrl(
        $baseUrl . '/admin/themes.php',
        [
            'csrf_token' => $adminCsrfToken,
            'form_action' => 'activate_theme',
            'active_theme' => $roundtripThemeKey,
            'preview_redirect' => '/index.php',
        ],
        'PHPSESSID=' . $auditSessionId,
        0
    );
    if (!str_contains($activateResult['status'], '200')) {
        $roundtripIssues[] = 'imported theme activation did not return 200';
    }
    if (!str_contains($activateResult['body'], 'Profil webu byl přepnut na Vlastní profil')) {
        $roundtripIssues[] = 'manual theme activation did not announce profile detachment';
    }

    clearSettingsCache();
    if (getSetting('site_profile', '') !== 'custom') {
        $roundtripIssues[] = 'manual theme activation did not detach preset site profile';
    }
    $roundtripHome = fetchUrl($baseUrl . '/', '', 0);
    if (!str_contains($roundtripHome['status'], '200')) {
        $roundtripIssues[] = 'imported theme homepage did not load';
    }
    if (!str_contains($roundtripHome['body'], '/themes/' . rawurlencode($roundtripThemeKey) . '/assets/public.css')) {
        $roundtripIssues[] = 'imported theme stylesheet was not referenced on homepage';
    }
    if (!str_contains($roundtripHome['body'], '--accent:#225577')) {
        $roundtripIssues[] = 'imported theme defaults were not rendered into CSS variables';
    }

    $exportResult = postUrl(
        $baseUrl . '/admin/themes.php',
        [
            'csrf_token' => $adminCsrfToken,
            'form_action' => 'export_theme_package',
            'export_theme' => $roundtripThemeKey,
        ],
        'PHPSESSID=' . $auditSessionId,
        0
    );
    if (!str_contains($exportResult['status'], '200')) {
        $roundtripIssues[] = 'theme package export did not return 200';
    }
    $zipHeaderFound = false;
    foreach ($exportResult['headers'] as $header) {
        if (stripos($header, 'Content-Type: application/zip') === 0) {
            $zipHeaderFound = true;
            break;
        }
    }
    if (!$zipHeaderFound) {
        $roundtripIssues[] = 'theme package export did not return application/zip';
    }
    if (!str_starts_with($exportResult['body'], 'PK')) {
        $roundtripIssues[] = 'theme package export body is not a ZIP file';
    } else {
        $exportZipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $roundtripThemeKey . '-export.zip';
        file_put_contents($exportZipPath, $exportResult['body']);
        $exportZip = themeReadZipArchive($exportZipPath);
        if (!$exportZip['ok']) {
            $roundtripIssues[] = 'exported ZIP package could not be opened';
        } else {
            $exportedManifest = $exportZip['files'][$roundtripThemeKey . '/theme.json'] ?? null;
            $exportedCss = $exportZip['files'][$roundtripThemeKey . '/assets/public.css'] ?? null;
            if (!is_string($exportedManifest)) {
                $roundtripIssues[] = 'exported ZIP package is missing theme.json';
            }
            if (!is_string($exportedCss)) {
                $roundtripIssues[] = 'exported ZIP package is missing assets/public.css';
            }
        }
        @unlink($exportZipPath);
    }

    if ($roundtripIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($roundtripIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
} catch (Throwable $exception) {
    $failures++;
    echo '- ' . $exception->getMessage() . "\n";
} finally {
    saveSetting('site_profile', $originalRoundtripSiteProfile ?? 'custom');
    saveSetting('active_theme', $originalActiveTheme);
    clearSettingsCache();
    if (themeExists($roundtripThemeKey)) {
        themeDeleteDirectory(themeDirectoryPath($roundtripThemeKey));
    }
    @unlink($roundtripZipPath);
}

$activeThemeSettingsKey = themeSettingStorageKey($originalActiveTheme);
$originalThemeSettings = getSetting($activeThemeSettingsKey, '');
$originalNewsletterModule = getSetting('module_newsletter', '0');
echo "=== theme_home_composer ===\n";
try {
    if ($runtimeAuditHomepageUsesWidgets) {
        echo "OK\n";
    } else {
    $newsModuleEnabled = isModuleEnabled('news');
    $blogModuleEnabled = isModuleEnabled('blog');
    $boardModuleEnabled = isModuleEnabled('board');
    $pollModuleEnabled = isModuleEnabled('polls');
    $newsletterModuleEnabled = isModuleEnabled('newsletter');

    $composerSettings = [
        'home_layout' => 'balanced',
        'home_hero_visibility' => 'hide',
        'home_featured_module' => $newsletterModuleEnabled
            ? 'newsletter'
            : ($blogModuleEnabled && $articleId
                ? 'blog'
                : ($boardModuleEnabled && $boardCount > 0
                    ? 'board'
                    : ($pollModuleEnabled && $activePollCount > 0 ? 'poll' : 'none'))),
        'home_primary_order' => 'blog_news',
        'home_utility_order' => $newsletterModuleEnabled
            ? 'newsletter_cta_board_poll'
            : 'cta_board_poll_newsletter',
        'home_news_visibility' => $newsModuleEnabled && $newsCount > 0 ? 'hide' : 'show',
        'home_blog_visibility' => $blogModuleEnabled && $articleId ? 'show' : 'hide',
        'home_board_visibility' => $boardModuleEnabled && $boardCount > 0 ? 'show' : 'hide',
        'home_poll_visibility' => $pollModuleEnabled && $activePollCount > 0 ? 'show' : 'hide',
        'home_newsletter_visibility' => $newsletterModuleEnabled ? 'show' : 'hide',
        'home_cta_visibility' => 'show',
    ];
    $expectedFeaturedModule = (string)$composerSettings['home_featured_module'];
    $expectedHomeBlogItems = 0;
    if ($blogModuleEnabled && (string)$composerSettings['home_blog_visibility'] === 'show') {
        $expectedHomeBlogItems = min($articleCount, $homeBlogCountSetting);
    }

    saveThemeSettings($composerSettings, $originalActiveTheme);
    clearSettingsCache();

    $composerProbe = fetchUrl($baseUrl . '/', '', 0);
    $composerIssues = [];
    if (!str_contains($composerProbe['status'], '200')) {
        $composerIssues[] = 'homepage composer probe did not load';
    }
    if (str_contains($composerProbe['body'], 'data-home-section="hero"')) {
        $composerIssues[] = 'hero block remained visible after hiding it';
    }
    if (!str_contains($composerProbe['body'], 'data-home-section="cta"')) {
        $composerIssues[] = 'CTA block was not rendered';
    }
    if (!str_contains($composerProbe['body'], 'data-home-section="featured"')) {
        $composerIssues[] = 'featured block was not rendered';
    }
    if ($newsModuleEnabled && $newsCount > 0 && str_contains($composerProbe['body'], 'data-home-section="news"')) {
        $composerIssues[] = 'news block remained visible after hiding it';
    }
    if ($expectedHomeBlogItems > 0 && !str_contains($composerProbe['body'], 'data-home-section="blog"')) {
        $composerIssues[] = 'blog block was not rendered';
    }
    $actualHomeBlogItems = substr_count($composerProbe['body'], 'data-home-blog-item');
    if ($actualHomeBlogItems !== $expectedHomeBlogItems) {
        $composerIssues[] = 'homepage blog section rendered unexpected number of blog items';
    }

    $blogPos = strpos($composerProbe['body'], 'data-home-section="blog"');
    $boardPos = strpos($composerProbe['body'], 'data-home-section="board"');
    $ctaPos = strpos($composerProbe['body'], 'data-home-section="cta"');
    $newsletterPos = strpos($composerProbe['body'], 'data-home-section="newsletter"');

    if (
        $expectedHomeBlogItems > 0
        && $boardModuleEnabled
        && $boardCount > 0
        && $blogPos !== false
        && $boardPos !== false
        && $blogPos > $boardPos
    ) {
        $composerIssues[] = 'primary blog section was rendered after utility board section';
    }
    if ($newsletterModuleEnabled && $newsletterPos !== false && $ctaPos !== false && $newsletterPos > $ctaPos) {
        $composerIssues[] = 'newsletter utility block was rendered after CTA despite configured order';
    }

    saveSetting('module_newsletter', '0');
    clearSettingsCache();

    $adminComposerProbe = fetchUrl($baseUrl . '/admin/themes.php', 'PHPSESSID=' . $auditSessionId, 0);
    if (!str_contains($adminComposerProbe['status'], '200')) {
        $composerIssues[] = 'admin themes page did not load after disabling newsletter module';
    }
    if (str_contains($adminComposerProbe['body'], 'theme_settings[home_newsletter_visibility]')) {
        $composerIssues[] = 'newsletter visibility setting remained visible in admin after disabling module';
    }
    if (str_contains($adminComposerProbe['body'], '<option value="newsletter"')) {
        $composerIssues[] = 'newsletter featured option remained visible in admin after disabling module';
    }
    if (str_contains($adminComposerProbe['body'], '<option value="news"')) {
        $composerIssues[] = 'news remained available as featured source in admin';
    }

    $newsletterDisabledSettings = $composerSettings;
    $newsletterDisabledSettings['home_featured_module'] = 'newsletter';
    $newsletterDisabledSettings['home_newsletter_visibility'] = 'show';
    $newsletterDisabledSettings['home_utility_order'] = 'newsletter_cta_board_poll';
    saveThemeSettings($newsletterDisabledSettings, $originalActiveTheme);
    clearSettingsCache();

    $newsletterDisabledProbe = fetchUrl($baseUrl . '/', '', 0);
    if (!str_contains($newsletterDisabledProbe['status'], '200')) {
        $composerIssues[] = 'homepage did not load after disabling newsletter module';
    }
    if (str_contains($newsletterDisabledProbe['body'], 'data-feature-source="newsletter"')) {
        $composerIssues[] = 'newsletter remained selected as featured source after disabling module';
    }
    if (str_contains($newsletterDisabledProbe['body'], 'data-home-section="newsletter"')) {
        $composerIssues[] = 'newsletter block remained rendered after disabling module';
    }
    if (!str_contains($newsletterDisabledProbe['body'], 'data-home-section="cta"')) {
        $composerIssues[] = 'CTA block disappeared after disabling newsletter module';
    }

    if ($composerIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($composerIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
    }
} finally {
    saveSetting('module_newsletter', $originalNewsletterModule);
    saveSetting($activeThemeSettingsKey, $originalThemeSettings);
    clearSettingsCache();
}

echo "=== theme_fallback ===\n";
try {
    saveSetting('active_theme', 'runtimeaudit-missing-theme');
    clearSettingsCache();

    $fallbackProbe = fetchUrl($baseUrl . '/', '', 0);
    $fallbackIssues = [];
    if (!str_contains($fallbackProbe['status'], '200')) {
        $fallbackIssues[] = 'unexpected status: ' . $fallbackProbe['status'];
    }
    if (!str_contains($fallbackProbe['body'], '/themes/default/assets/public.css')) {
        $fallbackIssues[] = 'default theme stylesheet was not used during fallback';
    }
    if (!str_contains($fallbackProbe['body'], 'theme-default')) {
        $fallbackIssues[] = 'default theme body class was not rendered during fallback';
    }

    if ($fallbackIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($fallbackIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
} finally {
    saveSetting('active_theme', $originalActiveTheme);
    clearSettingsCache();
}

echo "=== theme_customization ===\n";
try {
    saveThemeSettings([
        'header_layout' => 'split',
        'palette_preset' => 'slate',
        'accent' => '#8b2d12',
        'accent_strong' => '#61200d',
        'warm' => '#7c4c14',
        'font_pairing' => 'modern',
        'container_width' => 'wide',
        'home_layout' => 'editorial',
    ], $originalActiveTheme);
    clearSettingsCache();

    $customizationProbe = fetchUrl($baseUrl . '/', '', 0);
    $customizationIssues = [];
    if (!str_contains($customizationProbe['status'], '200')) {
        $customizationIssues[] = 'unexpected status: ' . $customizationProbe['status'];
    }
    if (!str_contains($customizationProbe['body'], '--accent:#8b2d12')) {
        $customizationIssues[] = 'custom accent variable was not rendered';
    }
    if (!str_contains($customizationProbe['body'], '--container:80rem')) {
        $customizationIssues[] = 'custom container width was not rendered';
    }
    if (!str_contains($customizationProbe['body'], '--font-display:"Trebuchet MS", "Franklin Gothic Medium", sans-serif')) {
        $customizationIssues[] = 'custom typography preset was not rendered';
    }
    if (!str_contains($customizationProbe['body'], 'site-header--split')) {
        $customizationIssues[] = 'header layout variant was not rendered';
    }
    if (!$runtimeAuditHomepageUsesWidgets && !str_contains($customizationProbe['body'], 'page-stack--home-editorial')) {
        $customizationIssues[] = 'homepage layout variant was not rendered';
    }

    if ($customizationIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($customizationIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
} finally {
    saveSetting($activeThemeSettingsKey, $originalThemeSettings);
    clearSettingsCache();
}

// ────────────────── Bezpečnostní testy (potvrzovací tokeny) ─────────────────

echo "=== confirm_token_expired ===\n";
$expiredConfirmEmail = 'runtimeaudit-expired-' . bin2hex(random_bytes(6)) . '@example.test';
$expiredConfirmToken = bin2hex(random_bytes(32));
$pdo->prepare(
    "INSERT INTO cms_users (email, password, first_name, last_name, role, is_superadmin, is_confirmed, confirmation_token, confirmation_expires, created_at)
     VALUES (?, ?, ?, ?, 'public', 0, 0, ?, DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW())"
)->execute([
    $expiredConfirmEmail,
    password_hash('RuntimeAudit123!', PASSWORD_BCRYPT),
    'Expired',
    'Token',
    $expiredConfirmToken,
]);
$cleanup['confirm_emails'][] = $expiredConfirmEmail;

$expiredResult = fetchUrl($baseUrl . '/confirm_email.php?token=' . urlencode($expiredConfirmToken));
if (str_contains($expiredResult['status'], '200') && str_contains($expiredResult['body'], 'Neplatný')) {
    echo "OK\n";
} else {
    $failures++;
    echo "- expired confirmation token was not rejected (status: {$expiredResult['status']})\n";
}

echo "=== confirm_token_valid ===\n";
$freshConfirmEmail = 'runtimeaudit-freshconfirm-' . bin2hex(random_bytes(6)) . '@example.test';
$freshConfirmToken = bin2hex(random_bytes(32));
$pdo->prepare(
    "INSERT INTO cms_users (email, password, first_name, last_name, role, is_superadmin, is_confirmed, confirmation_token, confirmation_expires, created_at)
     VALUES (?, ?, ?, ?, 'public', 0, 0, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())"
)->execute([
    $freshConfirmEmail,
    password_hash('RuntimeAudit123!', PASSWORD_BCRYPT),
    'Fresh',
    'Confirm',
    $freshConfirmToken,
]);
$cleanup['confirm_emails'][] = $freshConfirmEmail;

$validConfirmResult = fetchUrl($baseUrl . '/confirm_email.php?token=' . urlencode($freshConfirmToken));
if (str_contains($validConfirmResult['status'], '200') && str_contains($validConfirmResult['body'], 'úspěšně ověřen')) {
    echo "OK\n";
} else {
    $failures++;
    echo "- valid confirmation token was not accepted (status: {$validConfirmResult['status']})\n";
}

// ─────────────────────────────── SMTP konektivita ──────────────────────────

echo "=== smtp_connectivity ===\n";
$smtpIssues = [];

$smtpHost   = defined('SMTP_HOST')   ? SMTP_HOST   : (ini_get('SMTP') ?: 'localhost');
$smtpPort   = defined('SMTP_PORT')   ? (int) SMTP_PORT : (int)(ini_get('smtp_port') ?: 25);
$smtpSecure = defined('SMTP_SECURE') ? SMTP_SECURE : '';
$smtpUser   = defined('SMTP_USER')   ? SMTP_USER   : '';
$smtpPass   = defined('SMTP_PASS')   ? SMTP_PASS   : '';
$smtpIniHost = trim((string)ini_get('SMTP'));
$contactEmail = getSetting('contact_email', '');
$smtpConfigured = (
    defined('SMTP_HOST')
    || defined('SMTP_PORT')
    || defined('SMTP_SECURE')
    || defined('SMTP_USER')
    || defined('SMTP_PASS')
    || ($smtpIniHost !== '' && strtolower($smtpIniHost) !== 'localhost')
    || (int)ini_get('smtp_port') !== 25
);
$contactEmailConfigured = $contactEmail !== '' && $contactEmail !== 'noreply@localhost';

if (!$smtpConfigured) {
    echo "SKIP (SMTP není v tomto prostředí explicitně nakonfigurované)\n";
} else {
    $smtpTarget = ($smtpSecure === 'ssl') ? 'ssl://' . $smtpHost : $smtpHost;
$smtpSocket = @fsockopen($smtpTarget, $smtpPort, $smtpErrno, $smtpErrstr, 5);
if (!$smtpSocket) {
    $smtpIssues[] = "cannot connect to {$smtpTarget}:{$smtpPort} – {$smtpErrstr}";
} else {
    $smtpGreeting = '';
    while (($smtpLine = @fgets($smtpSocket, 512)) !== false) {
        $smtpGreeting .= $smtpLine;
        if (isset($smtpLine[3]) && $smtpLine[3] === ' ') break;
    }
    if (!str_starts_with(trim($smtpGreeting), '220')) {
        $smtpIssues[] = 'unexpected SMTP greeting: ' . trim($smtpGreeting);
    }

    fwrite($smtpSocket, "EHLO localhost\r\n");
    $smtpEhlo = '';
    while (($smtpLine = @fgets($smtpSocket, 512)) !== false) {
        $smtpEhlo .= $smtpLine;
        if (isset($smtpLine[3]) && $smtpLine[3] === ' ') break;
    }
    if (!str_starts_with(trim($smtpEhlo), '250')) {
        $smtpIssues[] = 'EHLO failed: ' . trim($smtpEhlo);
    }

    if ($smtpSecure === 'tls') {
        fwrite($smtpSocket, "STARTTLS\r\n");
        $smtpTls = '';
        while (($smtpLine = @fgets($smtpSocket, 512)) !== false) {
            $smtpTls .= $smtpLine;
            if (isset($smtpLine[3]) && $smtpLine[3] === ' ') break;
        }
        if (!str_starts_with(trim($smtpTls), '220')) {
            $smtpIssues[] = 'STARTTLS not supported: ' . trim($smtpTls);
        } else {
            $smtpCrypto = @stream_socket_enable_crypto(
                $smtpSocket, true,
                STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
            );
            if (!$smtpCrypto) {
                $smtpIssues[] = 'STARTTLS handshake failed';
            }
        }
    }

    $smtpUser = defined('SMTP_USER') ? SMTP_USER : '';
    $smtpPass = defined('SMTP_PASS') ? SMTP_PASS : '';
    if ($smtpUser !== '' && $smtpPass !== '' && empty($smtpIssues)) {
        if ($smtpSecure === 'tls') {
            fwrite($smtpSocket, "EHLO localhost\r\n");
            while (($smtpLine = @fgets($smtpSocket, 512)) !== false) {
                if (isset($smtpLine[3]) && $smtpLine[3] === ' ') break;
            }
        }
        fwrite($smtpSocket, "AUTH LOGIN\r\n");
        $smtpAuth = '';
        while (($smtpLine = @fgets($smtpSocket, 512)) !== false) {
            $smtpAuth .= $smtpLine;
            if (isset($smtpLine[3]) && $smtpLine[3] === ' ') break;
        }
        if (!str_starts_with(trim($smtpAuth), '334')) {
            $smtpIssues[] = 'AUTH LOGIN not supported: ' . trim($smtpAuth);
        } else {
            fwrite($smtpSocket, base64_encode($smtpUser) . "\r\n");
            while (($smtpLine = @fgets($smtpSocket, 512)) !== false) {
                if (isset($smtpLine[3]) && $smtpLine[3] === ' ') break;
            }
            fwrite($smtpSocket, base64_encode($smtpPass) . "\r\n");
            $smtpLogin = '';
            while (($smtpLine = @fgets($smtpSocket, 512)) !== false) {
                $smtpLogin .= $smtpLine;
                if (isset($smtpLine[3]) && $smtpLine[3] === ' ') break;
            }
            if (!str_starts_with(trim($smtpLogin), '235')) {
                $smtpIssues[] = 'AUTH LOGIN credentials rejected: ' . trim($smtpLogin);
            }
        }
    }

    fwrite($smtpSocket, "QUIT\r\n");
    fclose($smtpSocket);
    }

    if (!$contactEmailConfigured) {
        $smtpIssues[] = 'contact_email is not configured (current: ' . ($contactEmail ?: 'empty') . ')';
    }

    if ($smtpIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($smtpIssues as $smtpIssue) {
            echo '- ' . $smtpIssue . "\n";
        }
    }
}

// ─────────────────────────── sendMail return value audit ────────────────────

echo "=== sendmail_return_check ===\n";
$sendMailIssues = [];
$sendMailCallFiles = [
    'contact/index.php', 'register.php', 'reset_password.php', 'subscribe.php',
    'reservations/book.php', 'reservations/cancel.php', 'reservations/cancel_booking.php',
    'admin/res_booking_save.php',
];
foreach ($sendMailCallFiles as $sendMailFile) {
    $sendMailPath = dirname(__DIR__) . '/' . $sendMailFile;
    if (!is_file($sendMailPath)) continue;
    $sendMailSrc = file_get_contents($sendMailPath);
    // Hledáme volání sendMail() bez kontroly návratové hodnoty (řádek začíná jen sendMail)
    if (preg_match('/^\s+sendMail\(/m', $sendMailSrc)) {
        $sendMailIssues[] = $sendMailFile . ': sendMail() return value not checked';
    }
}
if ($sendMailIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($sendMailIssues as $sendMailIssue) {
        echo '- ' . $sendMailIssue . "\n";
    }
}

echo "=== blog_admin_guardrails ===\n";
$blogAdminIssues = [];
$blogLayoutSource = (string)file_get_contents(dirname(__DIR__) . '/admin/layout.php');
$blogListSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog.php');
$blogFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_form.php');
$blogCatsSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_cats.php');
$blogTagsSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_tags.php');

if (!str_contains($blogLayoutSource, "currentUserHasCapability('blog_taxonomies_manage') && hasAnyBlogs()")) {
    $blogAdminIssues[] = 'admin menu still exposes blog taxonomies without existing blog guard';
}
if (!str_contains($blogListSource, 'Vytvořit první blog')) {
    $blogAdminIssues[] = 'blog list is missing no-blog guidance';
}
if (!str_contains($blogFormSource, "blog.php?msg=no_blog")) {
    $blogAdminIssues[] = 'article form no longer redirects back to blog list when no blog exists';
}
if (!str_contains($blogCatsSource, 'if (!hasAnyBlogs())')) {
    $blogAdminIssues[] = 'blog categories page is missing no-blog redirect guard';
}
if (!str_contains($blogTagsSource, 'if (!hasAnyBlogs())')) {
    $blogAdminIssues[] = 'blog tags page is missing no-blog redirect guard';
}

if ($blogAdminIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($blogAdminIssues as $blogAdminIssue) {
        echo '- ' . $blogAdminIssue . "\n";
    }
}

echo "=== widget_registry ===\n";
$widgetRegistryIssues = [];
$widgetDefs = widgetTypeDefinitions();
$requiredWidgetTypes = [
    'latest_downloads',
    'latest_faq',
    'latest_places',
    'latest_podcast_episodes',
    'selected_form',
];
foreach ($requiredWidgetTypes as $requiredWidgetType) {
    if (!isset($widgetDefs[$requiredWidgetType])) {
        $widgetRegistryIssues[] = 'missing widget type: ' . $requiredWidgetType;
    }
}
if (($widgetDefs['contact_info']['requires_module'] ?? null) !== null) {
    $widgetRegistryIssues[] = 'contact_info widget is still incorrectly bound to contact module';
}
if (isModuleEnabled('forms')) {
    $activeFormCount = 0;
    try {
        $activeFormCount = (int)$pdo->query("SELECT COUNT(*) FROM cms_forms WHERE is_active = 1")->fetchColumn();
    } catch (\PDOException $e) {
        $activeFormCount = 0;
    }
    if ($activeFormCount > 0) {
        $availableWidgetDefs = availableWidgetTypes();
        if (!isset($availableWidgetDefs['selected_form'])) {
            $widgetRegistryIssues[] = 'selected_form widget is not available even with active forms';
        }
    }
}
if ($widgetRegistryIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($widgetRegistryIssues as $widgetRegistryIssue) {
        echo '- ' . $widgetRegistryIssue . "\n";
    }
}

echo "=== widget_render_guardrails ===\n";
$widgetRenderIssues = [];
$widgetRenderNow = date('Y-m-d H:i:s');
$widgetSearchOne = renderWidget_search(['id' => 101, 'title' => 'Vyhledávání'], [], 'sidebar');
$widgetSearchTwo = renderWidget_search(['id' => 202, 'title' => 'Vyhledávání'], [], 'sidebar');
if (!str_contains($widgetSearchOne, 'widget-search-q-101')) {
    $widgetRenderIssues[] = 'search widget does not use unique input id for first instance';
}
if (!str_contains($widgetSearchTwo, 'widget-search-q-202')) {
    $widgetRenderIssues[] = 'search widget does not use unique input id for second instance';
}
if (str_contains($widgetSearchOne . $widgetSearchTwo, 'id="widget-search-q"')) {
    $widgetRenderIssues[] = 'search widget still renders legacy duplicate input id';
}
if (isModuleEnabled('board')) {
    $featuredBoardWidget = renderWidget_featured_article(['id' => 301, 'title' => 'Doporučený obsah'], ['source' => 'board'], 'homepage');
    if ($featuredBoardWidget === '') {
        $widgetRenderIssues[] = 'featured board widget does not render any output';
    }
}
if (isModuleEnabled('polls')) {
    $activePollStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM cms_polls
         WHERE status = 'active'
           AND (start_date IS NULL OR start_date <= ?)
           AND (end_date IS NULL OR end_date > ?)"
    );
    $activePollStmt->execute([$widgetRenderNow, $widgetRenderNow]);
    $activePollCount = (int)$activePollStmt->fetchColumn();
    if ($activePollCount > 0) {
        $featuredPollWidget = renderWidget_featured_article(['id' => 302, 'title' => 'Doporučený obsah'], ['source' => 'poll'], 'homepage');
        if ($featuredPollWidget === '') {
            $widgetRenderIssues[] = 'featured poll widget does not render any output';
        }
    }
}
if (isModuleEnabled('newsletter')) {
    $featuredNewsletterWidget = renderWidget_featured_article(['id' => 303, 'title' => 'Doporučený obsah'], ['source' => 'newsletter'], 'homepage');
    if ($featuredNewsletterWidget === '') {
        $widgetRenderIssues[] = 'featured newsletter widget does not render any output';
    }
}
if (isModuleEnabled('forms')) {
    try {
        $activeWidgetFormId = (int)$pdo->query("SELECT id FROM cms_forms WHERE is_active = 1 ORDER BY id DESC LIMIT 1")->fetchColumn();
    } catch (\PDOException $e) {
        $activeWidgetFormId = 0;
    }
    if ($activeWidgetFormId > 0) {
        $selectedFormWidget = renderWidget_selected_form(['id' => 304, 'title' => 'Vybraný formulář'], ['form_id' => $activeWidgetFormId], 'homepage');
        if ($selectedFormWidget === '') {
            $widgetRenderIssues[] = 'selected form widget does not render active form';
        }
    }
}
$widgetsAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/widgets.php');
if (!str_contains($widgetsAdminSource, 'id="widget-add-zone"')) {
    $widgetRenderIssues[] = 'admin widgets page is missing target zone selector';
}
if (str_contains($widgetsAdminSource, '>+ <?= h($wDef[\'name\']) ?>')) {
    $widgetRenderIssues[] = 'admin widgets page still uses plus-sign add buttons';
}
if (!str_contains($widgetsAdminSource, 'name="widget_form_id"')) {
    $widgetRenderIssues[] = 'admin widgets page is missing selected form widget settings';
}
if (!str_contains($widgetsAdminSource, 'name="widget_show_id"')) {
    $widgetRenderIssues[] = 'admin widgets page is missing podcast show widget settings';
}
if ($widgetRenderIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($widgetRenderIssues as $widgetRenderIssue) {
        echo '- ' . $widgetRenderIssue . "\n";
    }
}

echo "=== menu_admin_guardrails ===\n";
$menuAdminIssues = [];
$adminMenuSource = (string)file_get_contents(dirname(__DIR__) . '/admin/menu.php');
$adminPagesSource = (string)file_get_contents(dirname(__DIR__) . '/admin/pages.php');
$pageFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/page_form.php');
$pagePositionsSource = (string)file_get_contents(dirname(__DIR__) . '/admin/page_positions.php');
$publicNavSource = (string)file_get_contents(dirname(__DIR__) . '/lib/stats.php');
if (!str_contains($adminMenuSource, 'id="nav-order-status"')) {
    $menuAdminIssues[] = 'admin menu is missing live status region for keyboard reorder feedback';
}
if (!str_contains($adminMenuSource, 'aria-disabled')) {
    $menuAdminIssues[] = 'admin menu does not expose aria-disabled states on move buttons';
}
if (!str_contains($adminMenuSource, 'Správa blogů') || !str_contains($adminMenuSource, 'Správa modulů') || !str_contains($adminMenuSource, 'Upravit stránku')) {
    $menuAdminIssues[] = 'admin menu is missing quick links for fixing disabled items';
}
if (!str_contains($adminPagesSource, 'Základní pořadí stránek')) {
    $menuAdminIssues[] = 'pages overview still uses outdated static page ordering wording';
}
if (!str_contains($pageFormSource, '/admin/menu.php') || str_contains($pageFormSource, 'Pořadí statických stránek upravíte na stránce')) {
    $menuAdminIssues[] = 'page form help still points navigation ordering to the wrong screen';
}
if (!str_contains($pagePositionsSource, 'Pro skutečné pořadí hlavní navigace webu použijte stránku')) {
    $menuAdminIssues[] = 'page positions page does not clarify that main navigation is managed elsewhere';
}
if (!str_contains($publicNavSource, '$renderUnifiedEntry') || !str_contains($publicNavSource, 'foreach (array_keys($pagesMap) as $pageId)')) {
    $menuAdminIssues[] = 'public navigation does not append missing unified entries safely';
}
if ($menuAdminIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($menuAdminIssues as $menuAdminIssue) {
        echo '- ' . $menuAdminIssue . "\n";
    }
}

exit($failures > 0 ? 1 : 0);
