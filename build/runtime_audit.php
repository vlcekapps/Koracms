<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ob_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../cron.php';
require_once __DIR__ . '/http_test_helpers.php';

$baseUrlInput = $argv[1] ?? getenv('KORA_TEST_BASE_URL');
if (!is_string($baseUrlInput) || $baseUrlInput === '') {
    $baseUrlInput = 'http://localhost';
}
$baseUrl = rtrim($baseUrlInput, '/');
$pdo     = db_connect();
$cronSource = (string) file_get_contents(__DIR__ . '/../cron.php');
$adminBackupSource = (string) file_get_contents(__DIR__ . '/../admin/backup.php');
$backupHelperSource = (string) file_get_contents(__DIR__ . '/../lib/backup.php');
$uiSource = (string) file_get_contents(__DIR__ . '/../lib/ui.php');
$revisionsSource = (string) file_get_contents(__DIR__ . '/../lib/revisions.php');
$widgetsSource = (string) file_get_contents(__DIR__ . '/../lib/widgets.php');
$paginationSource = (string) file_get_contents(__DIR__ . '/../lib/pagination.php');
$presentationSource = (string) file_get_contents(__DIR__ . '/../lib/presentation.php');
$themeSource = (string) file_get_contents(__DIR__ . '/../lib/theme.php');
$mediaLibrarySource = (string) file_get_contents(__DIR__ . '/../lib/media_library.php');
$webhooksSource = (string) file_get_contents(__DIR__ . '/../lib/webhooks.php');
$mailSource = (string) file_get_contents(__DIR__ . '/../lib/mail.php');
$commentsSource = (string) file_get_contents(__DIR__ . '/../lib/comments.php');
$authSource = (string) file_get_contents(__DIR__ . '/../auth.php');
$dbSource = (string) file_get_contents(__DIR__ . '/../db.php');
$errorPageCssSource = is_file(__DIR__ . '/../assets/error.css') ? (string) file_get_contents(__DIR__ . '/../assets/error.css') : '';
$publicCoreCssSource = is_file(__DIR__ . '/../themes/default/assets/public-core.css') ? (string) file_get_contents(__DIR__ . '/../themes/default/assets/public-core.css') : '';
$adminLayoutStylesheetSource = is_file(__DIR__ . '/../admin/assets/layout.css') ? (string) file_get_contents(__DIR__ . '/../admin/assets/layout.css') : '';
$cspReportSource = (string) file_get_contents(__DIR__ . '/../csp-report.php');
$htaccessSource = (string) file_get_contents(__DIR__ . '/../.htaccess');
$robotsSource = (string) file_get_contents(__DIR__ . '/../robots.php');
$healthSource = (string) file_get_contents(__DIR__ . '/../health.php');
$sitemapSource = (string) file_get_contents(__DIR__ . '/../sitemap.php');
$searchSource = (string) file_get_contents(__DIR__ . '/../search.php');
$blogIndexSource = (string) file_get_contents(__DIR__ . '/../blog/index.php');
$blogArticleSource = (string) file_get_contents(__DIR__ . '/../blog/article.php');
$boardIndexSource = (string) file_get_contents(__DIR__ . '/../board/index.php');
$contactIndexSource = (string) file_get_contents(__DIR__ . '/../contact/index.php');
$chatIndexSource = (string) file_get_contents(__DIR__ . '/../chat/index.php');
$formsIndexSource = (string) file_get_contents(__DIR__ . '/../forms/index.php');
$subscribeConfirmSource = (string) file_get_contents(__DIR__ . '/../subscribe_confirm.php');
$unsubscribeSource = (string) file_get_contents(__DIR__ . '/../unsubscribe.php');
$passwordResetSource = (string) file_get_contents(__DIR__ . '/../reset_password.php');
$reservationsBookSource = (string) file_get_contents(__DIR__ . '/../reservations/book.php');
$reservationsCancelSource = (string) file_get_contents(__DIR__ . '/../reservations/cancel.php');
$reservationsCancelBookingSource = (string) file_get_contents(__DIR__ . '/../reservations/cancel_booking.php');
$feedSource = (string) file_get_contents(__DIR__ . '/../feed.php');
$podcastFeedSource = (string) file_get_contents(__DIR__ . '/../podcast/feed.php');
$eventIcsSource = (string) file_get_contents(__DIR__ . '/../events/ics.php');
$composerSource = is_file(__DIR__ . '/../composer.json') ? (string) file_get_contents(__DIR__ . '/../composer.json') : '';
$phpstanConfigSource = is_file(__DIR__ . '/../phpstan.neon.dist') ? (string) file_get_contents(__DIR__ . '/../phpstan.neon.dist') : '';
$phpstanBootstrapSource = is_file(__DIR__ . '/phpstan_bootstrap.php') ? (string) file_get_contents(__DIR__ . '/phpstan_bootstrap.php') : '';
$ciWorkflowSource = is_file(__DIR__ . '/../.github/workflows/ci.yml') ? (string) file_get_contents(__DIR__ . '/../.github/workflows/ci.yml') : '';
$fullCiWorkflowSource = is_file(__DIR__ . '/../.github/workflows/full-ci.yml') ? (string) file_get_contents(__DIR__ . '/../.github/workflows/full-ci.yml') : '';
$runtimeAuditSelfSource = (string) file_get_contents(__FILE__);
$httpIntegrationBuildSource = is_file(__DIR__ . '/http_integration.php') ? (string) file_get_contents(__DIR__ . '/http_integration.php') : '';
$httpServerRouterSource = is_file(__DIR__ . '/http_server_router.php') ? (string) file_get_contents(__DIR__ . '/http_server_router.php') : '';
$repositoryGuardrailsAuditSource = is_file(__DIR__ . '/repository_guardrails_audit.php') ? (string) file_get_contents(__DIR__ . '/repository_guardrails_audit.php') : '';
$configSampleAuditSource = is_file(__DIR__ . '/config_sample_audit.php') ? (string) file_get_contents(__DIR__ . '/config_sample_audit.php') : '';
$versionMetadataAuditSource = is_file(__DIR__ . '/version_metadata_audit.php') ? (string) file_get_contents(__DIR__ . '/version_metadata_audit.php') : '';
$schemaParityAuditSource = is_file(__DIR__ . '/schema_parity_audit.php') ? (string) file_get_contents(__DIR__ . '/schema_parity_audit.php') : '';
$redirectGuardrailsAuditSource = is_file(__DIR__ . '/redirect_guardrails_audit.php') ? (string) file_get_contents(__DIR__ . '/redirect_guardrails_audit.php') : '';
$workflowAuditSource = is_file(__DIR__ . '/workflow_audit.php') ? (string) file_get_contents(__DIR__ . '/workflow_audit.php') : '';
$workflowAuditSelftestSource = is_file(__DIR__ . '/workflow_audit_selftest.php') ? (string) file_get_contents(__DIR__ . '/workflow_audit_selftest.php') : '';
$themeViewAuditSource = is_file(__DIR__ . '/theme_view_audit.php') ? (string) file_get_contents(__DIR__ . '/theme_view_audit.php') : '';
$themeViewAuditSelftestSource = is_file(__DIR__ . '/theme_view_audit_selftest.php') ? (string) file_get_contents(__DIR__ . '/theme_view_audit_selftest.php') : '';
$sourceEncodingAuditSource = is_file(__DIR__ . '/source_encoding_audit.php') ? (string) file_get_contents(__DIR__ . '/source_encoding_audit.php') : '';
$mojibakeAuditSource = is_file(__DIR__ . '/mojibake_audit.php') ? (string) file_get_contents(__DIR__ . '/mojibake_audit.php') : '';
$whitespaceAuditSource = is_file(__DIR__ . '/whitespace_audit.php') ? (string) file_get_contents(__DIR__ . '/whitespace_audit.php') : '';
$releaseScriptSource = is_file(__DIR__ . '/release.ps1') ? (string) file_get_contents(__DIR__ . '/release.ps1') : '';
$releasePackageAuditSource = is_file(__DIR__ . '/release_package_audit.php') ? (string) file_get_contents(__DIR__ . '/release_package_audit.php') : '';
$releaseSmokeSource = is_file(__DIR__ . '/release_smoke.php') ? (string) file_get_contents(__DIR__ . '/release_smoke.php') : '';
$gitattributesSource = is_file(__DIR__ . '/../.gitattributes') ? (string) file_get_contents(__DIR__ . '/../.gitattributes') : '';
$gitignoreSource = is_file(__DIR__ . '/../.gitignore') ? (string) file_get_contents(__DIR__ . '/../.gitignore') : '';
$homeSource = (string) file_get_contents(__DIR__ . '/../index.php');
$fileDownloadHelperSource = (string) file_get_contents(__DIR__ . '/../lib/filedownloads.php');
$readOnlyMediaFileSource = (string) file_get_contents(__DIR__ . '/../media/file.php');
$readOnlyMediaPreviewSource = (string) file_get_contents(__DIR__ . '/../media/preview.php');
$readOnlyMediaThumbSource = (string) file_get_contents(__DIR__ . '/../media/thumb.php');
$readOnlyDownloadsFileSource = (string) file_get_contents(__DIR__ . '/../downloads/file.php');
$readOnlyBoardFileSource = (string) file_get_contents(__DIR__ . '/../board/file.php');
$readOnlyGalleryImageSource = (string) file_get_contents(__DIR__ . '/../gallery/image.php');
$readOnlyPlacesImageSource = (string) file_get_contents(__DIR__ . '/../places/image.php');
$readOnlyPodcastAudioSource = (string) file_get_contents(__DIR__ . '/../podcast/audio.php');
$readOnlyPodcastImageSource = (string) file_get_contents(__DIR__ . '/../podcast/image.php');
$readOnlyPodcastCoverSource = (string) file_get_contents(__DIR__ . '/../podcast/cover.php');
$adminExportSource = (string) file_get_contents(__DIR__ . '/../admin/export.php');
$adminBulkSource = (string) file_get_contents(__DIR__ . '/../admin/bulk.php');
$adminFormSubmissionFileSource = (string) file_get_contents(__DIR__ . '/../admin/form_submission_file.php');
$adminFormSubmissionsSource = (string) file_get_contents(__DIR__ . '/../admin/form_submissions.php');
$adminFormsSource = (string) file_get_contents(__DIR__ . '/../admin/forms.php');
$adminIndexSource = (string) file_get_contents(__DIR__ . '/../admin/index.php');
$adminStatisticsSource = (string) file_get_contents(__DIR__ . '/../admin/statistics.php');
$adminContentReferenceSearchSource = (string) file_get_contents(__DIR__ . '/../admin/content_reference_search.php');
$adminContentLockRefreshSource = (string) file_get_contents(__DIR__ . '/../admin/content_lock_refresh.php');
$adminReorderAjaxSource = (string) file_get_contents(__DIR__ . '/../admin/reorder_ajax.php');
$adminGalleryExportZipSource = (string) file_get_contents(__DIR__ . '/../admin/gallery_export_zip.php');
$adminThemesSource = (string) file_get_contents(__DIR__ . '/../admin/themes.php');
$adminResBookingSaveSource = (string) file_get_contents(__DIR__ . '/../admin/res_booking_save.php');

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
    'module_statistics' => getSetting('module_statistics', '0'),
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
$runtimeAuditPublicVisitorStatsWidgetEnabled = isModuleEnabled('statistics')
    && getSetting('visitor_tracking_enabled', '0') === '1'
    && (int)$pdo->query(
        "SELECT COUNT(*) FROM cms_widgets
         WHERE widget_type = 'visitor_stats'
           AND is_active = 1
           AND zone IN ('homepage', 'footer')"
    )->fetchColumn() > 0;

$articleRow = $pdo->query(
    "SELECT a.id, a.title, a.slug, a.blog_id, a.meta_title, a.meta_description, a.perex, a.content, a.image_file,
            b.slug AS blog_slug, b.logo_file AS blog_logo_file
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
    "SELECT id, title, slug FROM cms_news WHERE " . newsPublicVisibilitySql() . " ORDER BY created_at DESC, id DESC LIMIT 1"
)->fetch() ?: null;
$newsId = $newsRow['id'] ?? false;
$newsCanonicalPath = $newsRow ? newsPublicPath($newsRow) : '';
$newsLegacyPath = $newsId !== false ? BASE_URL . '/news/article.php?id=' . urlencode((string)$newsId) : '';
$newsCanonicalUrl = $newsCanonicalPath !== '' ? $baseUrl . $newsCanonicalPath : '';
$newsLegacyUrl = $newsLegacyPath !== '' ? $baseUrl . $newsLegacyPath : '';
$newsCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_news WHERE " . newsPublicVisibilitySql()
)->fetchColumn();
$newsLegacySlugPath = '';
$newsLegacySlugUrl = '';
$runtimeAuditNewsSearchTerm = '';
$runtimeAuditNewsExpiredTitle = '';
$runtimeAuditNewsDeletedTitle = '';
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
$eventIcsPath = '';
$eventIcsUrl = '';
$eventOngoingRow = null;
$eventOngoingPath = '';
$eventOngoingUrl = '';
$eventFutureTitle = '';
$eventOngoingTitle = '';
$eventLegacySlugPath = '';
$eventLegacySlugUrl = '';
$boardRow = null;
$boardId = false;
$boardCanonicalPath = '';
$boardLegacyPath = '';
$boardCanonicalUrl = '';
$boardLegacyUrl = '';
$boardCount = 0;
$boardAttachmentId = false;
$boardFutureTitle = '';
$boardFuturePath = '';
$boardFutureUrl = '';
$boardPrivateAttachmentId = false;
$boardPrivateAttachmentPath = '';
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
$placeLegacySlugPath = '';
$placeLegacySlugUrl = '';
$placeHiddenId = false;
$placeHiddenTitle = '';
$placeHiddenImagePath = '';
$placeVisibleImagePath = '';
$pollRow = null;
$pollId = false;
$pollCanonicalPath = '';
$pollLegacyPath = '';
$pollCanonicalUrl = '';
$pollLegacyUrl = '';
$pollLegacySlugPath = '';
$pollLegacySlugUrl = '';
$runtimeAuditPollSearchTerm = '';
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
$podcastShowLegacySlugPath = '';
$podcastShowLegacySlugUrl = '';
$podcastEpisodeRow = null;
$podcastEpisodeId = false;
$podcastEpisodeCanonicalPath = '';
$podcastEpisodeLegacyPath = '';
$podcastEpisodeCanonicalUrl = '';
$podcastEpisodeLegacyUrl = '';
$podcastEpisodeLegacySlugPath = '';
$podcastEpisodeLegacySlugUrl = '';
$podcastVisibleCoverUrl = '';
$podcastVisibleEpisodeImageUrl = '';
$podcastVisibleEpisodeAudioUrl = '';
$podcastHiddenShowTitle = '';
$podcastHiddenShowPath = '';
$podcastHiddenShowUrl = '';
$podcastHiddenFeedUrl = '';
$podcastHiddenShowCoverUrl = '';
$podcastHiddenEpisodeImageUrl = '';
$podcastHiddenEpisodeAudioUrl = '';
$newsletterPendingSubscriberId = false;
$newsletterConfirmedSubscriberId = false;
$newsletterHistoryId = false;
$contactMessageId = false;
$chatMessageId = false;
$chatApprovedMessageText = '';
$chatHiddenMessageText = '';
$chatPendingMessageText = '';
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
$runtimeAuditFoodFutureTitle = '';
$runtimeAuditFoodArchiveTitle = '';
$galleryAlbumRow = $pdo->query(
    "SELECT id, name, slug, description, COALESCE(updated_at, created_at) AS updated_at
     FROM cms_gallery_albums
     WHERE parent_id IS NULL
       AND " . galleryAlbumPublicVisibilitySql() . "
     ORDER BY id
     LIMIT 1"
)->fetch() ?: null;
if (!$galleryAlbumRow) {
    $galleryAlbumRow = $pdo->query(
        "SELECT id, name, slug, description, COALESCE(updated_at, created_at) AS updated_at
         FROM cms_gallery_albums
         WHERE " . galleryAlbumPublicVisibilitySql() . "
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
           AND " . galleryPhotoPublicVisibilitySql() . "
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
    "SELECT p.id, p.album_id, p.filename, p.title, p.slug, p.sort_order, p.created_at
     FROM cms_gallery_photos p
     INNER JOIN cms_gallery_albums a ON a.id = p.album_id
     WHERE " . galleryPhotoPublicVisibilitySql('p', 'a') . "
     ORDER BY p.id
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
$galleryHiddenAlbumPath = '';
$galleryHiddenAlbumUrl = '';
$galleryHiddenAlbumTitle = '';
$galleryHiddenPhotoPath = '';
$galleryHiddenPhotoUrl = '';
$galleryHiddenPhotoTitle = '';
$galleryPhotoMediaCanonicalUrl = '';
$runtimeAuditGalleryFilename = '';
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
    'news_ids' => [],
    'contact_ids' => [],
    'chat_ids' => [],
    'author_user_ids' => [],
    'staff_user_ids' => [],
    'board_ids' => [],
    'board_files' => [],
    'download_ids' => [],
    'download_files' => [],
    'event_ids' => [],
    'faq_ids' => [],
    'food_ids' => [],
    'gallery_album_ids' => [],
    'gallery_photo_ids' => [],
    'gallery_files' => [],
    'place_ids' => [],
    'place_files' => [],
    'poll_ids' => [],
    'podcast_show_ids' => [],
    'podcast_episode_ids' => [],
    'podcast_files' => [],
    'form_ids' => [],
    'form_submission_ids' => [],
    'redirect_paths' => [],
    'audit_log_ids' => [],
];

$runtimeAuditActiveTheme = resolveThemeName(getSetting('active_theme', defaultThemeName()));
$runtimeAuditThemeSettingsKey = themeSettingStorageKey($runtimeAuditActiveTheme);
$runtimeAuditOriginalThemeSettings = getSetting($runtimeAuditThemeSettingsKey, '');
$runtimeAuditOriginalHomeAuthorUserId = getSetting('home_author_user_id', '0');
$runtimeAuditOriginalArticleAuthorId = null;
$runtimeAuditOriginalNewsAuthorId = null;
$runtimeAuditExistingNewsIdForAuthorRestore = false;
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

$runtimeAuditSessionSuffix = bin2hex(random_bytes(6));
$auditSessionId = 'runtimeaudit-admin-' . $runtimeAuditSessionSuffix;
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

$pdo->prepare(
    "INSERT INTO cms_log (action, detail, user_id, created_at)
     VALUES ('runtime_audit', 'Dočasný záznam pro ověření tabulky audit logu.', 1, NOW())"
)->execute();
$cleanup['audit_log_ids'][] = (int)$pdo->lastInsertId();

$publicAuditSessionId = 'runtimeaudit-public-' . $runtimeAuditSessionSuffix;
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
    $roleAuditSessionId = 'runtimeaudit-' . str_replace('_', '-', $roleKey) . '-' . $runtimeAuditSessionSuffix;
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
$runtimeAuditDownloadSeriesKey = 'runtime-audit-aplikace';
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
    $runtimeAuditExistingNewsIdForAuthorRestore = (int)$newsId;
    $newsAuthorStmt = $pdo->prepare("SELECT author_id FROM cms_news WHERE id = ?");
    $newsAuthorStmt->execute([(int)$newsId]);
    $runtimeAuditOriginalNewsAuthorId = $newsAuthorStmt->fetchColumn();
    $pdo->prepare("UPDATE cms_news SET author_id = ? WHERE id = ?")->execute([
        $runtimeAuditAuthorId,
        (int)$newsId,
    ]);
}

if (isModuleEnabled('news')) {
    $runtimeAuditNewsSuffix = bin2hex(random_bytes(4));
    $runtimeAuditNewsTitle = 'Runtime audit novinka ' . $runtimeAuditNewsSuffix;
    $runtimeAuditNewsSlug = uniqueNewsSlug($pdo, 'runtime-audit-novinka-' . bin2hex(random_bytes(4)));
    $runtimeAuditNewsOldSlug = uniqueNewsSlug($pdo, 'runtime-audit-novinka-stary-' . bin2hex(random_bytes(4)));
    $runtimeAuditNewsSearchTerm = 'runtime audit';
    $runtimeAuditNewsExpiredTitle = 'Runtime audit expirovaná novinka ' . $runtimeAuditNewsSuffix;
    $runtimeAuditNewsDeletedTitle = 'Runtime audit skrytá novinka ' . $runtimeAuditNewsSuffix;

    $pdo->prepare(
        "INSERT INTO cms_news (
            title, slug, content, author_id, status, unpublish_at, admin_note, meta_title, meta_description, created_at
         ) VALUES (?, ?, ?, ?, 'published', NULL, ?, ?, ?, NOW())"
    )->execute([
        $runtimeAuditNewsTitle,
        $runtimeAuditNewsSlug,
        '<p>Testovací novinka pro audit veřejného výpisu, detailu, hledání a strukturovaných dat.</p>',
        $runtimeAuditAuthorId > 0 ? $runtimeAuditAuthorId : null,
        'Runtime audit interní poznámka k novince.',
        'Runtime audit meta titulek novinky',
        'Runtime audit meta popis novinky pro kontrolu editoru a detailu.',
    ]);
    $runtimeAuditNewsId = (int)$pdo->lastInsertId();
    $cleanup['news_ids'][] = $runtimeAuditNewsId;

    $pdo->prepare(
        "INSERT INTO cms_news (
            title, slug, content, status, unpublish_at, created_at
         ) VALUES (?, ?, ?, 'published', ?, NOW())"
    )->execute([
        $runtimeAuditNewsExpiredTitle,
        uniqueNewsSlug($pdo, 'runtime-audit-expirovana-' . bin2hex(random_bytes(4))),
        '<p>Tato novinka už nemá být veřejně vidět.</p>',
        (new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
    ]);
    $cleanup['news_ids'][] = (int)$pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO cms_news (
            title, slug, content, status, deleted_at, created_at
         ) VALUES (?, ?, ?, 'published', NOW(), NOW())"
    )->execute([
        $runtimeAuditNewsDeletedTitle,
        uniqueNewsSlug($pdo, 'runtime-audit-smazana-' . bin2hex(random_bytes(4))),
        '<p>Tato novinka je soft-deleted a nesmí být veřejně dohledatelná.</p>',
    ]);
    $cleanup['news_ids'][] = (int)$pdo->lastInsertId();

    $runtimeAuditNewsStmt = $pdo->prepare(
        "SELECT n.id, n.title, n.slug, n.content, n.meta_title, n.meta_description, n.created_at, n.updated_at,
                COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name,
                u.author_public_enabled, u.author_slug, u.role AS author_role
         FROM cms_news n
         LEFT JOIN cms_users u ON u.id = n.author_id
         WHERE n.id = ?"
    );
    $runtimeAuditNewsStmt->execute([$runtimeAuditNewsId]);
    $newsRow = $runtimeAuditNewsStmt->fetch() ?: null;
    if ($newsRow) {
        $newsRow = hydrateNewsPresentation($newsRow);
        $newsId = $newsRow['id'] ?? false;
        $newsCanonicalPath = newsPublicPath($newsRow);
        $newsLegacyPath = $newsId !== false ? BASE_URL . '/news/article.php?id=' . urlencode((string)$newsId) : '';
        $newsCanonicalUrl = $newsCanonicalPath !== '' ? $baseUrl . $newsCanonicalPath : '';
        $newsLegacyUrl = $newsLegacyPath !== '' ? $baseUrl . $newsLegacyPath : '';
        $newsLegacySlugPath = BASE_URL . '/news/' . rawurlencode($runtimeAuditNewsOldSlug);
        $newsLegacySlugUrl = $baseUrl . $newsLegacySlugPath;
        upsertPathRedirect($pdo, $newsLegacySlugPath, $newsCanonicalPath);
        $cleanup['redirect_paths'][] = $newsLegacySlugPath;
    }

    $newsCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM cms_news WHERE " . newsPublicVisibilitySql()
    )->fetchColumn();
}

$runtimeAuditThemeSettings = themePersistedSettingsValues($runtimeAuditActiveTheme);
saveThemeSettings($runtimeAuditThemeSettings, $runtimeAuditActiveTheme);
clearSettingsCache();

if (isModuleEnabled('events')) {
    $runtimeAuditEventTitle = 'Runtime audit konference';
    $runtimeAuditEventSlug = uniqueEventSlug($pdo, 'runtime-audit-konference-' . bin2hex(random_bytes(4)));
    $runtimeAuditEventOldSlug = uniqueEventSlug($pdo, 'runtime-audit-konference-stary-' . bin2hex(random_bytes(4)));
    $runtimeAuditEventDate = (new DateTimeImmutable('+10 days 18:00'))->format('Y-m-d H:i:s');
    $runtimeAuditEventEnd = (new DateTimeImmutable('+10 days 20:30'))->format('Y-m-d H:i:s');
    $runtimeAuditEventTitle = 'Runtime audit konference';
    $runtimeAuditEventFutureExcerpt = 'Krátké shrnutí připravované akce pro audit filtrů, detailu a kalendářového exportu.';
    $runtimeAuditEventOngoingSlug = uniqueEventSlug($pdo, 'runtime-audit-ziva-dilna-' . bin2hex(random_bytes(4)));
    $runtimeAuditEventOngoingDate = (new DateTimeImmutable('-1 day 10:00'))->format('Y-m-d H:i:s');
    $runtimeAuditEventOngoingEnd = (new DateTimeImmutable('+1 day 14:00'))->format('Y-m-d H:i:s');
    $runtimeAuditEventOngoingTitle = 'Runtime audit živá dílna';

    $pdo->prepare(
        "INSERT INTO cms_events (
            title, slug, event_kind, excerpt, description, program_note, location,
            organizer_name, organizer_email, registration_url, price_note, accessibility_note,
            image_file, event_date, event_end, is_published, status, admin_note, unpublish_at, created_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?, 1, 'published', ?, NULL, NOW())"
    )->execute([
        $runtimeAuditEventTitle,
        $runtimeAuditEventSlug,
        'lecture',
        $runtimeAuditEventFutureExcerpt,
        '<p>Detailní text runtime audit konference pro ověření veřejného detailu události a filtrů ve výpisu.</p>',
        '<p>18:00 Otevření sálu<br>18:30 Hlavní program<br>20:00 Diskuse</p>',
        'Praha, Klubovna',
        'Runtime Audit Team',
        'events@example.test',
        'https://example.test/runtime-audit-registrace',
        'Vstup zdarma po registraci',
        "Bezbariérový vstup z ulice.\nMožnost doprovodu se služebním psem.",
        $runtimeAuditEventDate,
        $runtimeAuditEventEnd,
        'Runtime audit poznámka k plánované akci.',
    ]);
    $runtimeAuditEventId = (int)$pdo->lastInsertId();
    $cleanup['event_ids'][] = $runtimeAuditEventId;

    $eventStmt = $pdo->prepare("SELECT * FROM cms_events WHERE id = ?");
    $eventStmt->execute([$runtimeAuditEventId]);
    $eventRow = $eventStmt->fetch() ?: null;
    if ($eventRow) {
        $eventRow = hydrateEventPresentation($eventRow);
        $eventId = $eventRow['id'] ?? false;
        $eventCanonicalPath = eventPublicPath($eventRow);
        $eventLegacyPath = $eventId !== false ? BASE_URL . '/events/event.php?id=' . urlencode((string)$eventId) : '';
        $eventCanonicalUrl = $eventCanonicalPath !== '' ? $baseUrl . $eventCanonicalPath : '';
        $eventLegacyUrl = $eventLegacyPath !== '' ? $baseUrl . $eventLegacyPath : '';
        $eventIcsPath = eventIcsPath($eventRow);
        $eventIcsUrl = $baseUrl . $eventIcsPath;
        $eventFutureTitle = (string)($eventRow['title'] ?? '');
        $eventLegacySlugPath = BASE_URL . '/events/' . rawurlencode($runtimeAuditEventOldSlug);
        $eventLegacySlugUrl = $baseUrl . $eventLegacySlugPath;
        upsertPathRedirect($pdo, $eventLegacySlugPath, $eventCanonicalPath);
        $cleanup['redirect_paths'][] = $eventLegacySlugPath;
    }

    $pdo->prepare(
        "INSERT INTO cms_events (
            title, slug, event_kind, excerpt, description, program_note, location,
            organizer_name, organizer_email, registration_url, price_note, accessibility_note,
            image_file, event_date, event_end, is_published, status, admin_note, unpublish_at, created_at
         ) VALUES (?, ?, ?, ?, ?, '', ?, '', '', '', 'Pouze pro zvané hosty', '', '', ?, ?, 1, 'published', ?, NULL, NOW())"
    )->execute([
        $runtimeAuditEventOngoingTitle,
        $runtimeAuditEventOngoingSlug,
        'workshop',
        'Probíhající akce pro audit sekce právě probíhá.',
        '<p>Tato akce právě probíhá a musí se ve veřejném výpisu objevit v samostatném přehledu.</p>',
        'Brno, učebna 2',
        $runtimeAuditEventOngoingDate,
        $runtimeAuditEventOngoingEnd,
        'Runtime audit poznámka k probíhající akci.',
    ]);
    $runtimeAuditOngoingEventId = (int)$pdo->lastInsertId();
    $cleanup['event_ids'][] = $runtimeAuditOngoingEventId;

    $eventOngoingStmt = $pdo->prepare("SELECT * FROM cms_events WHERE id = ?");
    $eventOngoingStmt->execute([$runtimeAuditOngoingEventId]);
    $eventOngoingRow = $eventOngoingStmt->fetch() ?: null;
    if ($eventOngoingRow) {
        $eventOngoingRow = hydrateEventPresentation($eventOngoingRow);
        $eventOngoingPath = eventPublicPath($eventOngoingRow);
        $eventOngoingUrl = $baseUrl . $eventOngoingPath;
        $eventOngoingTitle = (string)($eventOngoingRow['title'] ?? $runtimeAuditEventOngoingTitle);
    }
}

if (isModuleEnabled('board')) {
    $runtimeAuditBoardTitle = 'Runtime audit vývěska';
    $runtimeAuditBoardSlug = uniqueBoardSlug($pdo, 'runtime-audit-vyveska-' . bin2hex(random_bytes(4)));
    $runtimeAuditBoardExcerpt = 'Krátké shrnutí testovací položky pro audit vývěsky a oznámení.';
    $runtimeAuditBoardPhone = '+420 777 123 456';
    $runtimeAuditBoardEmail = 'vyveska@example.test';
    $runtimeAuditBoardStorageDir = __DIR__ . '/../uploads/board';
    if (!is_dir($runtimeAuditBoardStorageDir)) {
        mkdir($runtimeAuditBoardStorageDir, 0755, true);
    }

    $runtimeAuditBoardStoredName = 'runtime_audit_board_' . bin2hex(random_bytes(6)) . '.txt';
    file_put_contents($runtimeAuditBoardStorageDir . '/' . $runtimeAuditBoardStoredName, "Runtime audit board attachment.\n");
    $cleanup['board_files'][] = $runtimeAuditBoardStoredName;

    $pdo->prepare(
        "INSERT INTO cms_board (
            title, slug, board_type, excerpt, description, category_id, posted_date, removal_date,
            image_file, contact_name, contact_phone, contact_email,
            filename, original_name, file_size, sort_order, is_pinned, is_published, status, author_id, created_at
         ) VALUES (?, ?, 'notice', ?, ?, NULL, DATE_SUB(CURDATE(), INTERVAL 1 DAY), NULL, '', ?, ?, ?, ?, 'runtime-audit-board.txt', ?, -100, 1, 1, 'published', ?, NOW())"
    )->execute([
        $runtimeAuditBoardTitle,
        $runtimeAuditBoardSlug,
        $runtimeAuditBoardExcerpt,
        '<p>Detailní text runtime audit položky pro ověření veřejného detailu a výpisu.</p>',
        'Runtime Audit',
        $runtimeAuditBoardPhone,
        $runtimeAuditBoardEmail,
        $runtimeAuditBoardStoredName,
        filesize($runtimeAuditBoardStorageDir . '/' . $runtimeAuditBoardStoredName) ?: 0,
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

    $boardFutureTitle = 'Runtime audit budoucí položka';
    $boardFutureSlug = uniqueBoardSlug($pdo, 'runtime-audit-budouci-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_board (
            title, slug, board_type, excerpt, description, category_id, posted_date, removal_date,
            image_file, contact_name, contact_phone, contact_email,
            filename, original_name, file_size, sort_order, is_pinned, is_published, status, author_id, created_at
         ) VALUES (?, ?, 'notice', ?, ?, NULL, DATE_ADD(CURDATE(), INTERVAL 14 DAY), NULL, '', '', '', '', '', '', 0, -90, 0, 1, 'published', ?, NOW())"
    )->execute([
        $boardFutureTitle,
        $boardFutureSlug,
        'Budoucí položka vývěsky pro audit plánovaného zveřejnění.',
        '<p>Tato položka se nesmí veřejně zobrazit dříve, než nastane datum vyvěšení.</p>',
        $runtimeAuditAuthorId > 0 ? $runtimeAuditAuthorId : null,
    ]);
    $runtimeAuditFutureBoardId = (int)$pdo->lastInsertId();
    $cleanup['board_ids'][] = $runtimeAuditFutureBoardId;
    $boardFuturePath = boardPublicPath(['id' => $runtimeAuditFutureBoardId, 'slug' => $boardFutureSlug]);
    $boardFutureUrl = $baseUrl . $boardFuturePath;

    $runtimeAuditPrivateBoardStoredName = 'runtime_audit_board_private_' . bin2hex(random_bytes(6)) . '.txt';
    file_put_contents($runtimeAuditBoardStorageDir . '/' . $runtimeAuditPrivateBoardStoredName, "Runtime audit private board attachment.\n");
    $cleanup['board_files'][] = $runtimeAuditPrivateBoardStoredName;

    $runtimeAuditPrivateBoardSlug = uniqueBoardSlug($pdo, 'runtime-audit-soukroma-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_board (
            title, slug, board_type, excerpt, description, category_id, posted_date, removal_date,
            image_file, contact_name, contact_phone, contact_email,
            filename, original_name, file_size, sort_order, is_pinned, is_published, status, author_id, created_at
         ) VALUES (?, ?, 'document', ?, ?, NULL, CURDATE(), NULL, '', '', '', '', ?, 'runtime-audit-private.txt', ?, -80, 0, 0, 'published', ?, NOW())"
    )->execute([
        'Runtime audit neveřejná příloha',
        $runtimeAuditPrivateBoardSlug,
        'Neveřejná příloha pro audit přístupových práv vývěsky.',
        '<p>Soubor této položky smí stáhnout jen správce obsahu nebo schvalovatel.</p>',
        $runtimeAuditPrivateBoardStoredName,
        filesize($runtimeAuditBoardStorageDir . '/' . $runtimeAuditPrivateBoardStoredName) ?: 0,
        $runtimeAuditAuthorId > 0 ? $runtimeAuditAuthorId : null,
    ]);
    $boardPrivateAttachmentId = (int)$pdo->lastInsertId();
    $cleanup['board_ids'][] = $boardPrivateAttachmentId;
    $boardPrivateAttachmentPath = '/board/file.php?id=' . $boardPrivateAttachmentId;

    $boardCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM cms_board
         WHERE " . boardPublicVisibilitySql() . "
           AND (removal_date IS NULL OR removal_date >= CURDATE())"
    )->fetchColumn();
    $boardAttachmentId = $pdo->query(
        "SELECT id FROM cms_board
         WHERE " . boardPublicVisibilitySql() . "
           AND (removal_date IS NULL OR removal_date >= CURDATE())
           AND filename <> ''
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
$runtimeAuditDownloadHiddenStoredName = 'runtime_audit_hidden_' . bin2hex(random_bytes(6)) . '.txt';
$runtimeAuditDownloadHiddenFilePath = __DIR__ . '/../uploads/downloads/' . $runtimeAuditDownloadHiddenStoredName;
file_put_contents($runtimeAuditDownloadHiddenFilePath, "Runtime audit hidden download file.\n");
$cleanup['download_files'][] = $runtimeAuditDownloadHiddenStoredName;

$runtimeAuditDownloadTitle = 'Runtime audit aplikace';
$runtimeAuditDownloadSlug = uniqueDownloadSlug($pdo, 'runtime-audit-aplikace-' . bin2hex(random_bytes(4)));
$runtimeAuditDownloadExcerpt = 'Krátký přehled testovací položky ke stažení pro ověření detailu, metadat a bezpečného file endpointu.';
$pdo->prepare(
    "INSERT INTO cms_downloads (
        title, slug, download_type, dl_category_id, excerpt, description, image_file, version_label,
        platform_label, license_label, project_url, release_date, requirements, checksum_sha256, series_key,
        external_url, filename, original_name, file_size, download_count, is_featured,
        sort_order, is_published, status, author_id, created_at, updated_at
     ) VALUES (?, ?, 'software', NULL, ?, ?, '', '1.0.0', 'Windows / Linux', 'MIT',
               ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, 5, 1, -100, 1, 'published', ?, NOW(), NOW())"
)->execute([
    $runtimeAuditDownloadTitle,
    $runtimeAuditDownloadSlug,
    $runtimeAuditDownloadExcerpt,
    '<p>Detailní text runtime audit položky ke stažení pro ověření veřejného detailu a CTA tlačítek.</p>',
    'https://example.test/runtime-project',
    'Windows 11 nebo novější, 4 GB RAM.',
    str_repeat('a', 64),
    $runtimeAuditDownloadSeriesKey,
    'https://example.test/runtime-download',
    $runtimeAuditDownloadStoredName,
    'runtime-audit.txt',
    filesize($runtimeAuditDownloadFilePath) ?: 0,
    null,
]);
$runtimeAuditDownloadId = (int)$pdo->lastInsertId();
$cleanup['download_ids'][] = $runtimeAuditDownloadId;

$runtimeAuditDownloadSiblingSlug = uniqueDownloadSlug($pdo, 'runtime-audit-aplikace-older-' . bin2hex(random_bytes(4)));
$pdo->prepare(
    "INSERT INTO cms_downloads (
        title, slug, download_type, dl_category_id, excerpt, description, image_file, version_label,
        platform_label, license_label, project_url, release_date, requirements, checksum_sha256, series_key,
        external_url, filename, original_name, file_size, download_count, is_featured,
        sort_order, is_published, status, author_id, created_at, updated_at
     ) VALUES (?, ?, 'software', NULL, ?, ?, '', '0.9.0', 'Windows / Linux', 'MIT',
               ?, DATE_SUB(CURDATE(), INTERVAL 30 DAY), ?, ?, ?, '', ?, ?, ?, 2, 0, -90, 1, 'published', ?, NOW(), NOW())"
)->execute([
    'Runtime audit aplikace 0.9.0',
    $runtimeAuditDownloadSiblingSlug,
    'Starší testovací verze ke stažení.',
    '<p>Starší verze runtime audit aplikace pro ověření seznamu dalších verzí.</p>',
    'https://example.test/runtime-project',
    'Windows 10 nebo novější.',
    str_repeat('b', 64),
    $runtimeAuditDownloadSeriesKey,
    $runtimeAuditDownloadStoredName,
    'runtime-audit.txt',
    filesize($runtimeAuditDownloadFilePath) ?: 0,
    null,
]);
$cleanup['download_ids'][] = (int)$pdo->lastInsertId();

$runtimeAuditHiddenDownloadSlug = uniqueDownloadSlug($pdo, 'runtime-audit-hidden-download-' . bin2hex(random_bytes(4)));
$pdo->prepare(
    "INSERT INTO cms_downloads (
        title, slug, download_type, dl_category_id, excerpt, description, image_file, version_label,
        platform_label, license_label, project_url, release_date, requirements, checksum_sha256, series_key,
        external_url, filename, original_name, file_size, download_count, is_featured,
        sort_order, is_published, status, author_id, created_at, updated_at
     ) VALUES (?, ?, 'document', NULL, ?, ?, '', '', '', '',
               '', NULL, '', '', '', '', ?, ?, ?, 0, 0, -80, 0, 'published', ?, NOW(), NOW())"
)->execute([
    'Runtime audit skrytý download',
    $runtimeAuditHiddenDownloadSlug,
    'Skrytý soubor ke stažení pro ověření file guardu.',
    '<p>Tato položka nesmí být veřejně stažitelná.</p>',
    $runtimeAuditDownloadHiddenStoredName,
    'runtime-audit-hidden.txt',
    filesize($runtimeAuditDownloadHiddenFilePath) ?: 0,
    null,
]);
$runtimeAuditHiddenDownloadId = (int)$pdo->lastInsertId();
$cleanup['download_ids'][] = $runtimeAuditHiddenDownloadId;

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

    $runtimeAuditGalleryRelatedPhotoSlug = uniqueGalleryPhotoSlug($pdo, 'runtime-audit-fotka-related-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_gallery_photos (album_id, filename, title, slug, sort_order, created_at)
         VALUES (?, ?, ?, ?, 1, NOW())"
    )->execute([
        $runtimeAuditGalleryAlbumId,
        $runtimeAuditGalleryFilename,
        'Runtime audit doplňková fotka',
        $runtimeAuditGalleryRelatedPhotoSlug,
    ]);
    $cleanup['gallery_photo_ids'][] = (int)$pdo->lastInsertId();

    $pdo->prepare("UPDATE cms_gallery_albums SET cover_photo_id = ? WHERE id = ?")->execute([
        $runtimeAuditGalleryPhotoId,
        $runtimeAuditGalleryAlbumId,
    ]);

    $galleryHiddenAlbumTitle = 'Runtime audit skryté album';
    $runtimeAuditHiddenGalleryAlbumSlug = uniqueGalleryAlbumSlug($pdo, 'runtime-audit-skryte-album-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_gallery_albums (parent_id, name, slug, description, cover_photo_id, is_published, created_at, updated_at)
         VALUES (NULL, ?, ?, ?, NULL, 0, NOW(), NOW())"
    )->execute([
        $galleryHiddenAlbumTitle,
        $runtimeAuditHiddenGalleryAlbumSlug,
        'Skryté album jen pro ověření veřejné viditelnosti galerie.',
    ]);
    $runtimeAuditHiddenGalleryAlbumId = (int)$pdo->lastInsertId();
    $cleanup['gallery_album_ids'][] = $runtimeAuditHiddenGalleryAlbumId;
    $galleryHiddenAlbumPath = BASE_URL . '/gallery/' . rawurlencode($runtimeAuditHiddenGalleryAlbumSlug);
    $galleryHiddenAlbumUrl = $baseUrl . $galleryHiddenAlbumPath;

    $galleryHiddenPhotoTitle = 'Runtime audit skrytá fotka';
    $runtimeAuditHiddenGalleryPhotoSlug = uniqueGalleryPhotoSlug($pdo, 'runtime-audit-skryta-fotka-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_gallery_photos (album_id, filename, title, slug, sort_order, is_published, created_at)
         VALUES (?, ?, ?, ?, 0, 0, NOW())"
    )->execute([
        $runtimeAuditHiddenGalleryAlbumId,
        $runtimeAuditGalleryFilename,
        $galleryHiddenPhotoTitle,
        $runtimeAuditHiddenGalleryPhotoSlug,
    ]);
    $runtimeAuditHiddenGalleryPhotoId = (int)$pdo->lastInsertId();
    $cleanup['gallery_photo_ids'][] = $runtimeAuditHiddenGalleryPhotoId;
    $galleryHiddenPhotoPath = BASE_URL . '/gallery/foto/' . rawurlencode($runtimeAuditHiddenGalleryPhotoSlug);
    $galleryHiddenPhotoUrl = $baseUrl . $galleryHiddenPhotoPath;

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
    $galleryPhotoMediaCanonicalUrl = $galleryPhotoRow ? ($baseUrl . galleryPhotoMediaRequestPath($galleryPhotoRow, 'thumb')) : '';

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

    $runtimeAuditFoodFutureTitle = 'Runtime audit budoucí lístek';
    $runtimeAuditFoodFutureSlug = uniqueFoodCardSlug($pdo, 'runtime-audit-budouci-listek-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_food_cards (
            type, title, slug, description, content, valid_from, valid_to,
            is_current, is_published, status, author_id, created_at, updated_at
         ) VALUES ('food', ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 0, 1, 'published', ?, NOW(), NOW())"
    )->execute([
        $runtimeAuditFoodFutureTitle,
        $runtimeAuditFoodFutureSlug,
        'Budoucí testovací karta pro ověření scope filtrů jídelních lístků.',
        '<p>Budoucí obsah pro ověření scope upcoming.</p>',
        $runtimeAuditAuthorId > 0 ? $runtimeAuditAuthorId : null,
    ]);
    $cleanup['food_ids'][] = (int)$pdo->lastInsertId();

    $runtimeAuditFoodArchiveTitle = 'Runtime audit archivní lístek';
    $runtimeAuditFoodArchiveSlug = uniqueFoodCardSlug($pdo, 'runtime-audit-archivni-listek-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_food_cards (
            type, title, slug, description, content, valid_from, valid_to,
            is_current, is_published, status, author_id, created_at, updated_at
         ) VALUES ('beverage', ?, ?, ?, ?, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), 0, 1, 'published', ?, NOW(), NOW())"
    )->execute([
        $runtimeAuditFoodArchiveTitle,
        $runtimeAuditFoodArchiveSlug,
        'Archivní testovací karta pro ověření scope archivních lístků.',
        '<p>Archivní obsah pro ověření scope archive.</p>',
        $runtimeAuditAuthorId > 0 ? $runtimeAuditAuthorId : null,
    ]);
    $cleanup['food_ids'][] = (int)$pdo->lastInsertId();
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

if (false && isModuleEnabled('places')) {
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

if (isModuleEnabled('places')) {
    $runtimeAuditPlaceName = 'Runtime audit místo';
    $runtimeAuditPlaceSlug = uniquePlaceSlug($pdo, 'runtime-audit-misto-' . bin2hex(random_bytes(4)));
    $runtimeAuditPlaceLegacySlug = uniquePlaceSlug($pdo, 'runtime-audit-misto-stary-' . bin2hex(random_bytes(3)));
    $runtimeAuditPlaceExcerpt = 'Krátký přehled testovacího místa pro ověření detailu, praktických informací a veřejného adresáře.';
    $runtimeAuditPlaceImage = 'runtime-audit-place-' . bin2hex(random_bytes(4)) . '.png';
    $runtimeAuditHiddenPlaceImage = 'runtime-audit-place-hidden-' . bin2hex(random_bytes(4)) . '.png';
    $runtimeAuditPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aC8AAAAASUVORK5CYII=');

    if (is_string($runtimeAuditPng)) {
        file_put_contents(__DIR__ . '/../uploads/places/' . $runtimeAuditPlaceImage, $runtimeAuditPng);
        file_put_contents(__DIR__ . '/../uploads/places/' . $runtimeAuditHiddenPlaceImage, $runtimeAuditPng);
        $cleanup['place_files'][] = $runtimeAuditPlaceImage;
        $cleanup['place_files'][] = $runtimeAuditHiddenPlaceImage;
    }

    $pdo->prepare(
        "INSERT INTO cms_places (
            name, slug, place_kind, category, excerpt, description, image_file, address, locality,
            latitude, longitude, url, contact_phone, contact_email, opening_hours, meta_title, meta_description,
            is_published, status, sort_order, created_at, updated_at
         ) VALUES (?, ?, 'info', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'published', -100, NOW(), NOW())"
    )->execute([
        $runtimeAuditPlaceName,
        $runtimeAuditPlaceSlug,
        'Testovací lokalita',
        $runtimeAuditPlaceExcerpt,
        '<p>Detailní text runtime audit místa pro ověření veřejného detailu, filtrace a SEO výstupu.</p>',
        $runtimeAuditPlaceImage,
        'Testovací 1',
        'Praha',
        50.0874510,
        14.4206710,
        'https://example.test/misto',
        '+420 777 987 654',
        'misto@example.test',
        "Po–Pá: 9:00–17:00\nSo: 10:00–14:00",
        'Runtime audit meta titulek místa',
        'Runtime audit meta popis místa pro ověření veřejného detailu.',
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
        $placeVisibleImagePath = placeImageRequestPath($placeRow);
        if ($placeCanonicalPath !== '') {
            $placeLegacySlugPath = BASE_URL . '/places/' . rawurlencode($runtimeAuditPlaceLegacySlug);
            $placeLegacySlugUrl = $baseUrl . $placeLegacySlugPath;
            upsertPathRedirect($pdo, $placeLegacySlugPath, $placeCanonicalPath);
            $cleanup['redirect_paths'][] = $placeLegacySlugPath;
        }
    }

    $placeHiddenTitle = 'Runtime audit skryté místo';
    $runtimeAuditHiddenPlaceSlug = uniquePlaceSlug($pdo, 'runtime-audit-skryte-misto-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_places (
            name, slug, place_kind, category, excerpt, description, image_file, address, locality,
            latitude, longitude, url, contact_phone, contact_email, opening_hours, meta_title, meta_description,
            is_published, status, sort_order, created_at, updated_at
         ) VALUES (?, ?, 'service', ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, 0, 'published', 999, NOW(), NOW())"
    )->execute([
        $placeHiddenTitle,
        $runtimeAuditHiddenPlaceSlug,
        'Skrytá lokalita',
        'Tato položka nesmí být veřejně vidět.',
        '<p>Skrytý detail místa pro ověření ochrany obrázku.</p>',
        $runtimeAuditHiddenPlaceImage,
        'Skrytá 1',
        'Brno',
        'https://example.test/skryte-misto',
        '+420 777 000 111',
        'hidden-place@example.test',
        'Jen interně',
        '',
        '',
    ]);
    $placeHiddenId = (int)$pdo->lastInsertId();
    $cleanup['place_ids'][] = $placeHiddenId;
    $placeHiddenImagePath = BASE_URL . '/places/image.php?id=' . $placeHiddenId;
}

if (isModuleEnabled('polls')) {
    $runtimeAuditPollQuestion = 'Runtime audit anketa';
    $runtimeAuditPollSlug = uniquePollSlug($pdo, 'runtime-audit-anketa-' . bin2hex(random_bytes(4)));
    $runtimeAuditPollSearchTerm = 'Runtime audit';
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
    $pdo->prepare("UPDATE cms_polls SET description = ? WHERE id = ?")->execute([
        'Krátké shrnutí testovací ankety pro ověření detailu, hlasování a čistých URL.',
        $runtimeAuditPollId,
    ]);
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
        $pollLegacySlugPath = BASE_URL . '/polls/runtime-audit-stary-slug-' . bin2hex(random_bytes(4));
        upsertPathRedirect($pdo, $pollLegacySlugPath, $pollCanonicalPath);
        $cleanup['redirect_paths'][] = $pollLegacySlugPath;
        $pollLegacySlugUrl = $baseUrl . $pollLegacySlugPath;
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
    $runtimeAuditPodcastBaseDir = __DIR__ . '/../uploads/podcasts/';
    $runtimeAuditPodcastCoverDir = $runtimeAuditPodcastBaseDir . 'covers/';
    $runtimeAuditPodcastImageDir = $runtimeAuditPodcastBaseDir . 'images/';
    if (!is_dir($runtimeAuditPodcastBaseDir)) {
        mkdir($runtimeAuditPodcastBaseDir, 0755, true);
    }
    if (!is_dir($runtimeAuditPodcastCoverDir)) {
        mkdir($runtimeAuditPodcastCoverDir, 0755, true);
    }
    if (!is_dir($runtimeAuditPodcastImageDir)) {
        mkdir($runtimeAuditPodcastImageDir, 0755, true);
    }
    $runtimeAuditPodcastPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aC8AAAAASUVORK5CYII=');
    if ($runtimeAuditPodcastPng === false) {
        $runtimeAuditPodcastPng = '';
    }
    $runtimeAuditPodcastAudio = "ID3RuntimeAuditPodcast";
    $runtimeAuditPodcastCover = 'runtime-audit-podcast-cover-' . bin2hex(random_bytes(4)) . '.png';
    $runtimeAuditPodcastEpisodeImage = 'runtime-audit-podcast-episode-' . bin2hex(random_bytes(4)) . '.png';
    $runtimeAuditPodcastAudioFile = 'runtime-audit-podcast-audio-' . bin2hex(random_bytes(4)) . '.mp3';
    $runtimeAuditHiddenPodcastCover = 'runtime-audit-podcast-hidden-cover-' . bin2hex(random_bytes(4)) . '.png';
    $runtimeAuditHiddenPodcastEpisodeImage = 'runtime-audit-podcast-hidden-episode-' . bin2hex(random_bytes(4)) . '.png';
    $runtimeAuditHiddenPodcastAudioFile = 'runtime-audit-podcast-hidden-audio-' . bin2hex(random_bytes(4)) . '.mp3';
    if ($runtimeAuditPodcastPng !== '') {
        file_put_contents($runtimeAuditPodcastCoverDir . $runtimeAuditPodcastCover, $runtimeAuditPodcastPng);
        file_put_contents($runtimeAuditPodcastImageDir . $runtimeAuditPodcastEpisodeImage, $runtimeAuditPodcastPng);
        file_put_contents($runtimeAuditPodcastCoverDir . $runtimeAuditHiddenPodcastCover, $runtimeAuditPodcastPng);
        file_put_contents($runtimeAuditPodcastImageDir . $runtimeAuditHiddenPodcastEpisodeImage, $runtimeAuditPodcastPng);
        $cleanup['podcast_files'][] = 'covers/' . $runtimeAuditPodcastCover;
        $cleanup['podcast_files'][] = 'images/' . $runtimeAuditPodcastEpisodeImage;
        $cleanup['podcast_files'][] = 'covers/' . $runtimeAuditHiddenPodcastCover;
        $cleanup['podcast_files'][] = 'images/' . $runtimeAuditHiddenPodcastEpisodeImage;
    }
    file_put_contents($runtimeAuditPodcastBaseDir . $runtimeAuditPodcastAudioFile, $runtimeAuditPodcastAudio);
    file_put_contents($runtimeAuditPodcastBaseDir . $runtimeAuditHiddenPodcastAudioFile, $runtimeAuditPodcastAudio);
    $cleanup['podcast_files'][] = $runtimeAuditPodcastAudioFile;
    $cleanup['podcast_files'][] = $runtimeAuditHiddenPodcastAudioFile;
    $runtimeAuditPodcastShowTitle = 'Runtime audit podcast';
    $runtimeAuditPodcastShowSlug = uniquePodcastShowSlug($pdo, 'runtime-audit-podcast-' . bin2hex(random_bytes(4)));
    $runtimeAuditPodcastShowOldSlug = uniquePodcastShowSlug($pdo, 'runtime-audit-podcast-old-' . bin2hex(random_bytes(3)));
    $pdo->prepare(
        "INSERT INTO cms_podcast_shows (
            title, slug, description, author, subtitle, cover_image, language, category, owner_name, owner_email,
            explicit_mode, show_type, feed_complete, feed_episode_limit, website_url, status, is_published, created_at, updated_at
         ) VALUES (?, ?, ?, ?, ?, ?, 'cs', ?, ?, ?, 'clean', 'episodic', 0, 25, ?, 'published', 1, NOW(), NOW())"
    )->execute([
        $runtimeAuditPodcastShowTitle,
        $runtimeAuditPodcastShowSlug,
        '<p>Testovací pořad pro runtime audit veřejných URL, RSS feedu a administrace podcastů.</p>',
        'Runtime Audit',
        'Krátký podtitul testovacího pořadu.',
        $runtimeAuditPodcastCover,
        'Technologie',
        'Runtime Audit Owner',
        'runtime.audit.owner@example.test',
        'https://example.test/podcast',
    ]);
    $runtimeAuditPodcastShowId = (int)$pdo->lastInsertId();
    $cleanup['podcast_show_ids'][] = $runtimeAuditPodcastShowId;

    $runtimeAuditPodcastEpisodeTitle = 'Runtime audit epizoda';
    $runtimeAuditPodcastEpisodeSlug = uniquePodcastEpisodeSlug($pdo, $runtimeAuditPodcastShowId, 'runtime-audit-epizoda-' . bin2hex(random_bytes(4)));
    $runtimeAuditPodcastEpisodeOldSlug = uniquePodcastEpisodeSlug($pdo, $runtimeAuditPodcastShowId, 'runtime-audit-epizoda-old-' . bin2hex(random_bytes(3)));
    $pdo->prepare(
        "INSERT INTO cms_podcasts (
            show_id, title, slug, description, audio_file, image_file, audio_url, subtitle, duration, episode_num, season_num,
            episode_type, explicit_mode, block_from_feed, publish_at, status, created_at, updated_at
         ) VALUES (?, ?, ?, ?, ?, ?, '', ?, '12:34', 1, 2, 'bonus', 'yes', 0, NOW(), 'published', NOW(), NOW())"
    )->execute([
        $runtimeAuditPodcastShowId,
        $runtimeAuditPodcastEpisodeTitle,
        $runtimeAuditPodcastEpisodeSlug,
        '<p>Detailní text testovací epizody pro runtime audit podcastů.</p>',
        $runtimeAuditPodcastAudioFile,
        $runtimeAuditPodcastEpisodeImage,
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
             AND " . podcastEpisodePublicVisibilitySql('e', 's') . "
         WHERE s.id = ?
         GROUP BY s.id"
    );
    $podcastShowStmt->execute([$runtimeAuditPodcastShowId]);
    $podcastShowRow = $podcastShowStmt->fetch() ?: null;
    if ($podcastShowRow) {
        $podcastShowRow = hydratePodcastShowPresentation($podcastShowRow);
        $podcastShowSlug = (string)($podcastShowRow['slug'] ?? '');
        $podcastVisibleCoverUrl = (string)($podcastShowRow['cover_url'] ?? '');
        if ($podcastShowSlug !== '') {
            $podcastShowLegacySlugPath = BASE_URL . '/podcast/' . rawurlencode($runtimeAuditPodcastShowOldSlug);
            $podcastShowLegacySlugUrl = $baseUrl . $podcastShowLegacySlugPath;
            upsertPathRedirect($pdo, $podcastShowLegacySlugPath, (string)$podcastShowRow['public_path']);
            $cleanup['redirect_paths'][] = $podcastShowLegacySlugPath;
        }
    }

    $podcastEpisodeStmt = $pdo->prepare(
        "SELECT p.*, s.slug AS show_slug, s.title AS show_title
         FROM cms_podcasts p
         INNER JOIN cms_podcast_shows s ON s.id = p.show_id
         WHERE p.id = ?
           AND " . podcastEpisodePublicVisibilitySql('p', 's')
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
        $podcastVisibleEpisodeImageUrl = (string)($podcastEpisodeRow['image_url'] ?? '');
        $podcastVisibleEpisodeAudioUrl = (string)($podcastEpisodeRow['audio_src'] ?? '');
        if ($podcastEpisodeCanonicalPath !== '') {
            $podcastEpisodeLegacySlugPath = BASE_URL . '/podcast/' . rawurlencode($podcastShowSlug) . '/' . rawurlencode($runtimeAuditPodcastEpisodeOldSlug);
            $podcastEpisodeLegacySlugUrl = $baseUrl . $podcastEpisodeLegacySlugPath;
            upsertPathRedirect($pdo, $podcastEpisodeLegacySlugPath, $podcastEpisodeCanonicalPath);
            $cleanup['redirect_paths'][] = $podcastEpisodeLegacySlugPath;
        }
    }

    $podcastHiddenShowTitle = 'Runtime audit skryty podcast';
    $runtimeAuditHiddenPodcastShowSlug = uniquePodcastShowSlug($pdo, 'runtime-audit-hidden-podcast-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_podcast_shows (
            title, slug, description, author, subtitle, cover_image, language, category, owner_name, owner_email,
            explicit_mode, show_type, feed_complete, feed_episode_limit, website_url, status, is_published, created_at, updated_at
         ) VALUES (?, ?, ?, ?, ?, ?, 'cs', ?, ?, ?, 'clean', 'episodic', 0, 10, ?, 'published', 0, NOW(), NOW())"
    )->execute([
        $podcastHiddenShowTitle,
        $runtimeAuditHiddenPodcastShowSlug,
        '<p>Skryty porad jen pro overeni verejne viditelnosti podcastu.</p>',
        'Runtime Audit',
        'Skryty podtitul.',
        $runtimeAuditHiddenPodcastCover,
        'Test',
        'Runtime Audit Owner',
        'runtime.audit.owner@example.test',
        'https://example.test/podcast-hidden',
    ]);
    $runtimeAuditHiddenPodcastShowId = (int)$pdo->lastInsertId();
    $cleanup['podcast_show_ids'][] = $runtimeAuditHiddenPodcastShowId;
    $podcastHiddenShowPath = BASE_URL . '/podcast/' . rawurlencode($runtimeAuditHiddenPodcastShowSlug);
    $podcastHiddenShowUrl = $baseUrl . BASE_URL . '/podcast/' . rawurlencode($runtimeAuditHiddenPodcastShowSlug);
    $podcastHiddenFeedUrl = $baseUrl . '/podcast/feed.php?slug=' . urlencode($runtimeAuditHiddenPodcastShowSlug);
    $podcastHiddenShowCoverUrl = $baseUrl . BASE_URL . '/podcast/cover.php?id=' . urlencode((string)$runtimeAuditHiddenPodcastShowId);

    $runtimeAuditHiddenPodcastEpisodeSlug = uniquePodcastEpisodeSlug($pdo, $runtimeAuditHiddenPodcastShowId, 'runtime-audit-hidden-epizoda-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_podcasts (
            show_id, title, slug, description, audio_file, image_file, audio_url, subtitle, duration, episode_num, season_num,
            episode_type, explicit_mode, block_from_feed, publish_at, status, created_at, updated_at
         ) VALUES (?, ?, ?, ?, ?, ?, '', ?, '01:23', 1, 1, 'full', 'inherit', 0, NOW(), 'published', NOW(), NOW())"
    )->execute([
        $runtimeAuditHiddenPodcastShowId,
        'Runtime audit skryta epizoda',
        $runtimeAuditHiddenPodcastEpisodeSlug,
        '<p>Skryta epizoda pro overeni asset endpointu.</p>',
        $runtimeAuditHiddenPodcastAudioFile,
        $runtimeAuditHiddenPodcastEpisodeImage,
        'Skryta epizoda.',
    ]);
    $runtimeAuditHiddenPodcastEpisodeId = (int)$pdo->lastInsertId();
    $cleanup['podcast_episode_ids'][] = $runtimeAuditHiddenPodcastEpisodeId;
    $podcastHiddenEpisodeImageUrl = $baseUrl . BASE_URL . '/podcast/image.php?id=' . urlencode((string)$runtimeAuditHiddenPodcastEpisodeId);
    $podcastHiddenEpisodeAudioUrl = $baseUrl . BASE_URL . '/podcast/audio.php?id=' . urlencode((string)$runtimeAuditHiddenPodcastEpisodeId);
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
    $chatPendingMessageText = 'Testovací chat zpráva čekající na schválení pro runtime audit.';
    $chatApprovedMessageText = 'Schválená chat zpráva pro veřejný runtime audit.';
    $chatHiddenMessageText = 'Skrytá chat zpráva pro runtime audit.';
    $pdo->prepare(
        "INSERT INTO cms_chat (name, email, web, message, status, public_visibility, created_at, updated_at)
         VALUES (?, ?, ?, ?, 'new', 'pending', NOW(), NOW())"
    )->execute([
        'Runtime Audit',
        'runtimeaudit-chat-' . bin2hex(random_bytes(4)) . '@example.test',
        'https://example.test/runtime-audit-chat',
        $chatPendingMessageText,
    ]);
    $chatMessageId = (int)$pdo->lastInsertId();
    $cleanup['chat_ids'][] = $chatMessageId;
    chatHistoryCreate($pdo, $chatMessageId, null, 'submitted', 'Zpráva byla přijata a čeká na schválení.');

    $pdo->prepare(
        "INSERT INTO cms_chat (
            name, email, web, message, status, public_visibility, approved_at, created_at, updated_at
         )
         VALUES (?, ?, ?, ?, 'read', 'approved', NOW(), NOW(), NOW())"
    )->execute([
        'Runtime Audit Approved',
        'runtimeaudit-approved-' . bin2hex(random_bytes(4)) . '@example.test',
        'https://example.test/runtime-audit-approved',
        $chatApprovedMessageText,
    ]);
    $approvedChatId = (int)$pdo->lastInsertId();
    $cleanup['chat_ids'][] = $approvedChatId;
    chatHistoryCreate($pdo, $approvedChatId, null, 'visibility', 'Zpráva byla schválena pro veřejný chat.');

    $pdo->prepare(
        "INSERT INTO cms_chat (name, email, web, message, status, public_visibility, created_at, updated_at)
         VALUES (?, ?, ?, ?, 'handled', 'hidden', NOW(), NOW())"
    )->execute([
        'Runtime Audit Hidden',
        'runtimeaudit-hidden-' . bin2hex(random_bytes(4)) . '@example.test',
        'https://example.test/runtime-audit-hidden',
        $chatHiddenMessageText,
    ]);
    $hiddenChatId = (int)$pdo->lastInsertId();
    $cleanup['chat_ids'][] = $hiddenChatId;
    chatHistoryCreate($pdo, $hiddenChatId, null, 'visibility', 'Zpráva byla skryta z veřejného chatu.');
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
if (!empty($articleRow['blog_id'])) {
    $pages[] = ['label' => 'admin_blog_pages', 'url' => $baseUrl . '/admin/blog_pages.php?blog_id=' . urlencode((string)$articleRow['blog_id']), 'cookie' => 'PHPSESSID=' . $auditSessionId];
}
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
    if ($eventOngoingTitle !== '') {
        $pages[] = ['label' => 'events_index_ongoing', 'url' => $baseUrl . '/events/index.php?scope=ongoing'];
    }
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
    if ($runtimeAuditNewsSearchTerm !== '') {
        $pages[] = ['label' => 'news_index_search', 'url' => $baseUrl . '/news/index.php?q=' . urlencode($runtimeAuditNewsSearchTerm)];
    }
}
if (isModuleEnabled('places')) {
    $pages[] = ['label' => 'places_index', 'url' => $baseUrl . '/places/index.php'];
    if ($placeRow) {
        $pages[] = ['label' => 'places_index_filtered', 'url' => $baseUrl . '/places/index.php?q=' . urlencode('audit') . '&kind=info&category=' . urlencode((string)($placeRow['category'] ?? '')) . '&locality=' . urlencode((string)($placeRow['locality'] ?? ''))];
    }
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
    if ($runtimeAuditPollSearchTerm !== '') {
        $pages[] = ['label' => 'polls_index_search', 'url' => $baseUrl . '/polls/index.php?q=' . urlencode($runtimeAuditPollSearchTerm)];
    }
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
if ($eventIcsUrl !== '') {
    $pages[] = ['label' => 'events_ics', 'url' => $eventIcsUrl];
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

// HTTP helpery sdílíme s build/http_integration.php přes build/http_test_helpers.php,
// aby audit a integrační scénáře používaly stejnou logiku cookies, redirectů a CSRF.

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

    $hasLinkedPublicStylesheet = str_contains($html, '/assets/public.css')
        || str_contains($html, '/assets/public-core.css')
        || str_contains($html, '/admin/assets/layout.css');
    if (str_contains($html, 'class="skip-link"') && !str_contains($html, '.skip-link') && !$hasLinkedPublicStylesheet) {
        $issues[] = 'skip-link without CSS definition';
    }

    if (str_contains($html, 'class="sr-only"') && !str_contains($html, '.sr-only') && !$hasLinkedPublicStylesheet) {
        $issues[] = 'sr-only helper without CSS definition';
    }

    if (str_contains($html, 'class="visually-hidden"') && !str_contains($html, '.visually-hidden') && !$hasLinkedPublicStylesheet) {
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

if (!function_exists('responseHasLocationHeader')) {
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

if (!function_exists('extractHiddenInputValue')) {
    function extractHiddenInputValue(string $html, string $name): string
    {
        $pattern = '/<input[^>]+name="' . preg_quote($name, '/') . '"[^>]+value="([^"]*)"/i';
        if (preg_match($pattern, $html, $matches) === 1) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }
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
    if ($page['label'] === 'register') {
        saveSetting('public_registration_enabled', '1');
        clearSettingsCache();
    }

    $result = fetchUrl($page['url'], $page['cookie'] ?? '');
    $issues = [];

    if (!str_contains($result['status'], '200')) {
        $issues[] = 'unexpected status: ' . $result['status'];
    }
    $isStructuredResponse = in_array($page['label'], ['sitemap_php', 'sitemap_xml', 'events_ics'], true);
    if (!$isStructuredResponse) {
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

    if ($page['label'] === 'home' && $runtimeAuditPublicVisitorStatsWidgetEnabled) {
        if (preg_match('/class="[^"]*visitor-counter__item/u', $result['body']) !== 1) {
            $issues[] = 'visitor counter does not expose individual statistic items';
        }
        if (preg_match('/<section[^>]*(?:aria-label|aria-labelledby)="[^"]*"[^>]*>.*?<h[23][^>]*>.*?<\/h[23]>.*?<ul class="visitor-counter/isu', $result['body']) !== 1) {
            $issues[] = 'visitor stats widget is missing a visible heading';
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
            static fn (string $label, string $snippet): bool => !str_contains($snippet, 'section-title section-title--hero'),
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
        if (!str_contains($result['body'], 'Zobrazit detail')) {
            $issues[] = 'board listing is missing detail links';
        }
        foreach ([
            'name="q"',
            'name="kat"',
            'name="month"',
            'name="scope"',
            'Filtrovat položky vývěsky',
            '<h2 id="board-scope-heading" class="sr-only">Rozsah výpisu vývěsky</h2>',
            '<nav class="tab-nav" aria-labelledby="board-scope-heading">',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'board listing is missing filter fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], 'class="tab-nav" aria-label="Rozsah výpisu vývěsky"')) {
            $issues[] = 'board listing still uses aria-label-only scope navigation';
        }
        if ($boardFutureTitle !== '' && str_contains($result['body'], $boardFutureTitle)) {
            $issues[] = 'board listing still exposes future-dated item';
        }
    }

    if (false && $page['label'] === 'places_index') {
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

    if ($page['label'] === 'places_index') {
        foreach ([
            'name="q"',
            'name="kind"',
            'name="category"',
            'name="locality"',
            'Filtrovat adresář míst',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'places listing is missing fragment: ' . $expectedFragment;
            }
        }
        if ($placeCanonicalPath !== '' && !str_contains($result['body'], $placeCanonicalPath)) {
            $issues[] = 'places listing is missing detail links';
        }
        if ($placeRow && !str_contains($result['body'], (string)($placeRow['excerpt_plain'] ?? ''))) {
            $issues[] = 'places listing is missing excerpt preview';
        }
        if ($placeRow && !str_contains($result['body'], (string)($placeRow['address'] ?? ''))) {
            $issues[] = 'places listing is missing address';
        }
        if (str_contains($result['body'], '/uploads/places/')) {
            $issues[] = 'places listing still exposes uploads/places paths';
        }
        if ($placeHiddenTitle !== '' && str_contains($result['body'], $placeHiddenTitle)) {
            $issues[] = 'places listing still exposes hidden place';
        }
    }

    if ($page['label'] === 'places_index_filtered') {
        foreach ([
            'value="audit"',
            'value="info"',
            'value="Testovací lokalita"',
            'value="Praha"',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'filtered places listing is missing fragment: ' . $expectedFragment;
            }
        }
        if ($placeCanonicalPath !== '' && !str_contains($result['body'], $placeCanonicalPath)) {
            $issues[] = 'filtered places listing is missing detail links';
        }
    }

    if ($page['label'] === 'search') {
        foreach (['Navigace obsahem', 'Hledání pro „'] as $legacySnippet) {
            if (str_contains($result['body'], $legacySnippet)) {
                $issues[] = 'search still contains legacy copy: ' . $legacySnippet;
            }
        }
        foreach ([
            '<h2 id="search-form-title" class="sr-only">Hledání na webu</h2>',
            '<form method="get" role="search" class="form-stack" aria-labelledby="search-form-title">',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'global search form is missing heading-backed search landmark fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], 'aria-label="Hledaný výraz"')) {
            $issues[] = 'global search input still contains redundant aria-label instead of relying on its visible label';
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
        foreach (['name="q"', 'name="kat"', 'name="typ"', 'name="platform"', 'name="source"', 'name="featured"', 'Filtrovat položky ke stažení'] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'downloads listing is missing filter fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_settings') {
        foreach ([
            'Nastavení webu',
            'Sekce nastavení webu',
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
        if (str_contains($result['body'], 'name="home_intro"')) {
            $issues[] = 'admin settings still renders the legacy home_intro field instead of widget-only homepage intro';
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
        'admin_polls_form' => ['<legend>Anketa</legend>', '<legend>Časové omezení</legend>', '<legend>Vyhledávače a sdílení</legend>'],
        'admin_polls_create_form' => ['<legend>Anketa</legend>', '<legend>Časové omezení</legend>', '<legend>Vyhledávače a sdílení</legend>'],
        'admin_podcast_show_form' => ['Uložit změny', 'Zrušit'],
        'admin_podcast_show_create_form' => ['Vytvořit podcast', 'Zrušit'],
        'admin_podcast_form' => ['Uložit změny', 'Zrušit'],
        'admin_podcast_create_form' => ['Přidat epizodu podcastu', 'Zrušit'],
    ];
    $adminFormActionExpectations['admin_polls_form'] = ['<legend>Anketa</legend>', '<legend>Časové omezení</legend>', '<legend>Vyhledávače a sdílení</legend>'];
    $adminFormActionExpectations['admin_polls_create_form'] = ['<legend>Anketa</legend>', '<legend>Časové omezení</legend>', '<legend>Vyhledávače a sdílení</legend>'];
    if (isset($adminFormActionExpectations[$page['label']])) {
        foreach ($adminFormActionExpectations[$page['label']] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin form is missing action fragment: ' . $expectedFragment;
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
        'admin_form_issue_preset' => ['Šablona:', 'Nahlášení chyby', 'Po prvním uložení se automaticky přidají tato pole:', 'Použít antispam honeypot', 'Souhlas', 'Potvrzení odesílateli', 'Zařazení problému', 'Popis chyby', 'Přílohy a kontakt', 'value="email_pro_odpoved" selected'],
        'admin_form_feature_preset' => ['Šablona:', 'Návrh nové funkce', 'Po prvním uložení se automaticky přidají tato pole:', 'Použít antispam honeypot', 'Souhlas', 'O návrhu', 'Popis a dopad'],
        'admin_form_support_preset' => ['Šablona:', 'Žádost o podporu', 'Po prvním uložení se automaticky přidají tato pole:', 'Použít antispam honeypot', 'Souhlas', 'Základ žádosti', 'Popis a co už jste zkusili'],
        'admin_form_contact_preset' => ['Šablona:', 'Obecný kontaktní formulář', 'Po prvním uložení se automaticky přidají tato pole:', 'Souhlas', 'Kontakt na vás', 'Vaše zpráva'],
        'admin_form_content_report_preset' => ['Šablona:', 'Nahlášení problému s obsahem', 'Po prvním uložení se automaticky přidají tato pole:', 'Souhlas', 'Kde je problém', 'Co je potřeba opravit'],
        'admin_form_edit' => ['Upravte nastavení formuláře, jeho pole, sekce, rozložení a to, co se má stát po úspěšném odeslání.', 'Text tlačítka pro odeslání', 'E-mail pro notifikaci', 'Předmět notifikačního e-mailu', 'Režim po odeslání', 'Primární tlačítko po odeslání', 'Sekundární tlačítko po odeslání', 'Povolené typy souborů', 'Max. velikost souboru (MB)', 'Odpovědi formuláře', 'Potvrzení odesílateli', 'Poslat odesílateli potvrzovací e-mail', 'Začít na novém řádku'],
        'admin_blog_form' => ['Adresa se vyplní automaticky, dokud ji neupravíte ručně.', 'Nechte prázdné, pokud se má článek zveřejnit hned.', 'Vložit odkaz nebo HTML z webu', 'Vyhledejte existující článek, stránku, formulář, anketu, médium nebo jiný veřejný obsah', 'Hledání prochází veřejně dostupný obsah webu, formuláře, ankety i knihovnu médií.', '[audio]https://example.test/audio.mp3[/audio]'],
        'admin_blog_create_form' => ['Adresa se vyplní automaticky, dokud ji neupravíte ručně.', 'Nechte prázdné, pokud se má článek zveřejnit hned.', 'Vložit odkaz nebo HTML z webu', 'Vyhledejte existující článek, stránku, formulář, anketu, médium nebo jiný veřejný obsah', 'Hledání prochází veřejně dostupný obsah webu, formuláře, ankety i knihovnu médií.', '[audio]https://example.test/audio.mp3[/audio]'],
        'admin_news_form' => ['Adresa se vyplní automaticky, dokud ji neupravíte ručně.'],
        'admin_news_create_form' => ['Adresa se vyplní automaticky, dokud ji neupravíte ručně.'],
        'admin_event_form' => ['Vyplňte potřebné údaje k této události.', 'Krátké shrnutí', 'Registrační odkaz', 'Plánované zrušení publikace', 'Zveřejnit na webu'],
        'admin_event_create_form' => ['Vyplňte potřebné údaje k této události.', 'Krátké shrnutí', 'Registrační odkaz', 'Plánované zrušení publikace', 'Zveřejnit na webu'],
        'admin_page_form' => ['Vyplňte základní údaje stránky. Můžete ji ponechat jako globální statickou stránku, nebo ji přiřadit ke konkrétnímu blogu.', 'Patří k blogu', 'Zveřejnit na webu', 'Zobrazit v hlavní navigaci', 'Pořadí stránek blogu'],
        'admin_page_create_form' => ['Vyplňte základní údaje stránky. Můžete ji ponechat jako globální statickou stránku, nebo ji přiřadit ke konkrétnímu blogu.', 'Patří k blogu', 'Zveřejnit na webu', 'Zobrazit v hlavní navigaci', 'Pořadí stránek blogu'],
        'admin_download_form' => ['Zveřejnit na webu', 'Můžete nahrát dokument, archiv nebo instalační balíček.'],
        'admin_download_create_form' => ['Zveřejnit na webu', 'Můžete nahrát dokument, archiv nebo instalační balíček.'],
        'admin_faq_form' => ['Vyplňte potřebné údaje k otázce a odpovědi.', 'Zveřejnit na webu'],
        'admin_faq_create_form' => ['Vyplňte potřebné údaje k otázce a odpovědi.', 'Zveřejnit na webu'],
        'admin_food_form' => ['Vyplňte potřebné údaje k tomuto lístku a pak zvolte, jestli má být aktuální a zveřejněný.', 'Použít jako aktuální lístek', 'Zveřejnit na webu', 'Nechte prázdné, pokud má lístek platit bez data konce.'],
        'admin_food_create_form' => ['Vyplňte potřebné údaje k tomuto lístku a pak zvolte, jestli má být aktuální a zveřejněný.', 'Použít jako aktuální lístek', 'Zveřejnit na webu', 'Nechte prázdné, pokud má lístek platit bez data konce.'],
        'admin_place_form' => ['Vyplňte základní údaje o místě a nakonec zvolte, jestli se má zobrazit na webu.', 'Zveřejnit na webu', 'Volitelné. Pomůže s filtrováním a orientací ve veřejném adresáři míst.'],
        'admin_place_create_form' => ['Vyplňte základní údaje o místě a nakonec zvolte, jestli se má zobrazit na webu.', 'Zveřejnit na webu', 'Volitelné. Pomůže s filtrováním a orientací ve veřejném adresáři míst.'],
        'admin_board_form' => ['Vyplňte potřebné údaje k položce a zvolte, jestli se má zveřejnit na webu.', 'Zveřejnit na webu', 'Nechte prázdné, pokud má položka zůstat bez data stažení.'],
        'admin_board_create_form' => ['Vyplňte potřebné údaje k položce a zvolte, jestli se má zveřejnit na webu.', 'Zveřejnit na webu', 'Nechte prázdné, pokud má položka zůstat bez data stažení.'],
        'admin_podcast_show_form' => ['Vyplňte základní údaje o podcastu.', 'Adresa se vyplní automaticky, dokud ji neupravíte ručně.', 'Počet epizod v RSS feedu', 'E-mail vlastníka feedu'],
        'admin_podcast_show_create_form' => ['Vyplňte základní údaje o podcastu.', 'Adresa se vyplní automaticky, dokud ji neupravíte ručně.', 'Počet epizod v RSS feedu', 'E-mail vlastníka feedu'],
        'admin_podcast_form' => ['Vyplňte základní údaje o epizodě.', 'Adresa se vyplní automaticky podle názvu epizody.', 'Nechte prázdné, pokud se má epizoda zveřejnit hned po uložení nebo schválení.', 'Krátký podtitul pro katalogy', 'Skrýt epizodu z RSS feedu'],
        'admin_podcast_create_form' => ['Vyplňte základní údaje o epizodě.', 'Adresa se vyplní automaticky podle názvu epizody.', 'Nechte prázdné, pokud se má epizoda zveřejnit hned po uložení nebo schválení.', 'Krátký podtitul pro katalogy', 'Skrýt epizodu z RSS feedu'],
        'admin_polls_form' => ['<legend>Anketa</legend>', '<legend>?asov? omezen?</legend>', '<legend>Vyhled?va?e a sd?len?</legend>'],
        'admin_polls_create_form' => ['<legend>Anketa</legend>', '<legend>?asov? omezen?</legend>', '<legend>Vyhled?va?e a sd?len?</legend>'],
        'admin_res_resource_form' => ['Vyplňte základní údaje o zdroji a pak nastavte způsob rezervací.', 'Například 24 znamená, že rezervaci je nutné vytvořit nejpozději den předem.'],
        'admin_res_resource_create_form' => ['Vyplňte základní údaje o zdroji a pak nastavte způsob rezervací.', 'Například 24 znamená, že rezervaci je nutné vytvořit nejpozději den předem.'],
        'admin_gallery_album_form' => ['Adresa se vyplní automaticky podle názvu alba.'],
        'admin_gallery_album_create_form' => ['Adresa se vyplní automaticky podle názvu alba.'],
        'admin_gallery_photo_form' => ['Adresa se vyplní automaticky podle titulku fotografie.'],
        'admin_gallery_photo_create_form' => ['Můžete vybrat více fotografií najednou.'],
        'admin_user_form' => ['Adresa autora se vyplní automaticky podle jména nebo přezdívky.'],
        'admin_user_create_form' => ['Adresa autora se vyplní automaticky podle jména nebo přezdívky.'],
    ];
    $adminFormCopyExpectations['admin_polls_form'] = ['<legend>Anketa</legend>', '<legend>Časové omezení</legend>', '<legend>Vyhledávače a sdílení</legend>'];
    $adminFormCopyExpectations['admin_polls_create_form'] = ['<legend>Anketa</legend>', '<legend>Časové omezení</legend>', '<legend>Vyhledávače a sdílení</legend>'];
    if (isset($adminFormCopyExpectations[$page['label']])) {
        foreach ($adminFormCopyExpectations[$page['label']] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin form is missing helper copy fragment: ' . $expectedFragment;
            }
        }
    }

    if (in_array($page['label'], ['admin_page_form', 'admin_page_create_form'], true) && !str_contains($result['body'], '/admin/menu.php')) {
        $issues[] = 'admin page form is missing the navigation management helper link';
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
    ];
    if (in_array($page['label'], $contentReferencePickerLabels, true)) {
        foreach ([
            'Vložit odkaz nebo HTML z webu',
            'Vyhledejte existující článek, stránku, formulář, anketu, médium nebo jiný veřejný obsah',
            'Hledání prochází veřejně dostupný obsah webu, formuláře, ankety i knihovnu médií.',
            'fotogalerii, obrázek, přehrávač nebo obsahový snippet',
            'value="media"',
            'Knihovna médií',
            'value="forms"',
            'Formuláře',
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
    ];
    if (in_array($page['label'], $htmlSnippetHelpLabels, true)) {
        $expectedSnippetFragments = [
            '[audio]https://example.test/audio.mp3[/audio]',
            '[video]https://example.test/video.mp4[/video]',
        ];
        if (isModuleEnabled('forms')) {
            $expectedSnippetFragments[] = '[form]slug-formulare[/form]';
        }
        if (isModuleEnabled('polls')) {
            $expectedSnippetFragments[] = '[poll]slug-ankety[/poll]';
        }
        if (isModuleEnabled('downloads')) {
            $expectedSnippetFragments[] = '[download]slug-polozky[/download]';
        }
        if (isModuleEnabled('podcast')) {
            $expectedSnippetFragments[] = '[podcast]slug-poradu[/podcast]';
            $expectedSnippetFragments[] = '[podcast_episode]slug-poradu/slug-epizody[/podcast_episode]';
        }
        if (isModuleEnabled('places')) {
            $expectedSnippetFragments[] = '[place]slug-mista[/place]';
        }
        if (isModuleEnabled('events')) {
            $expectedSnippetFragments[] = '[event]slug-udalosti[/event]';
        }
        if (isModuleEnabled('board')) {
            $expectedSnippetFragments[] = '[board]slug-oznameni[/board]';
        }
        foreach ($expectedSnippetFragments as $expectedFragment) {
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
        'admin_download_form' => ['Použije se ve veřejné adrese'],
        'admin_download_create_form' => ['Použije se ve veřejné adrese'],
        'admin_food_form' => ['>Zobrazit v archivu<', 'Označit jako aktuální lístek'],
        'admin_food_create_form' => ['>Zobrazit v archivu<', 'Označit jako aktuální lístek'],
        'admin_place_form' => ['<small id="place-category-help" class="field-help">Nepovinné pole.</small>'],
        'admin_place_create_form' => ['<small id="place-category-help" class="field-help">Nepovinné pole.</small>'],
        'admin_polls_form' => ['<legend>Anketa</legend>', '<legend>?asov? omezen?</legend>', '<legend>Vyhled?va?e a sd?len?</legend>'],
        'admin_polls_create_form' => ['<legend>Anketa</legend>', '<legend>?asov? omezen?</legend>', '<legend>Vyhled?va?e a sd?len?</legend>'],
        'admin_board_form' => ['Prázdné pole znamená bez omezení.'],
        'admin_board_create_form' => ['Prázdné pole znamená bez omezení.'],
    ];
    $adminFormCopyForbiddenFragments['admin_polls_form'] = ['<legend>?asov? omezen?</legend>', '<legend>Vyhled?va?e a sd?len?</legend>'];
    $adminFormCopyForbiddenFragments['admin_polls_create_form'] = ['<legend>?asov? omezen?</legend>', '<legend>Vyhled?va?e a sd?len?</legend>'];
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
        'admin_news_form' => ['Novinka', 'Zveřejnění', 'Vyhledávače a sdílení'],
        'admin_news_create_form' => ['Novinka', 'Zveřejnění', 'Vyhledávače a sdílení'],
        'admin_event_form' => ['Základní údaje události', 'Termín konání', 'Obsah události', 'Pořadatel, registrace a dostupnost', 'Obrázek a zveřejnění', 'Interní poznámka'],
        'admin_event_create_form' => ['Základní údaje události', 'Termín konání', 'Obsah události', 'Pořadatel, registrace a dostupnost', 'Obrázek a zveřejnění', 'Interní poznámka'],
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
        'admin_polls_form' => ['<legend>Anketa</legend>', '<legend>?asov? omezen?</legend>', '<legend>Vyhled?va?e a sd?len?</legend>'],
        'admin_polls_create_form' => ['<legend>Anketa</legend>', '<legend>?asov? omezen?</legend>', '<legend>Vyhled?va?e a sd?len?</legend>'],
        'admin_res_resource_form' => ['Lokality rezervací', 'Způsob rezervací', 'Časy k rezervaci', 'Hromadné přidání slotů'],
        'admin_res_resource_create_form' => ['Lokality rezervací', 'Způsob rezervací', 'Časy k rezervaci', 'Hromadné přidání slotů'],
    ];
    $adminFormSectionExpectations['admin_polls_form'] = ['<legend>Anketa</legend>', '<legend>Časové omezení</legend>', '<legend>Vyhledávače a sdílení</legend>'];
    $adminFormSectionExpectations['admin_polls_create_form'] = ['<legend>Anketa</legend>', '<legend>Časové omezení</legend>', '<legend>Vyhledávače a sdílení</legend>'];
    if (isset($adminFormSectionExpectations[$page['label']])) {
        foreach ($adminFormSectionExpectations[$page['label']] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin form is missing section label: ' . $expectedFragment;
            }
        }
    }

    if (in_array($page['label'], ['admin_download_form', 'admin_download_create_form'], true)) {
        foreach (['Zdroje a odkazy', 'Praktické informace a kompatibilita'] as $expectedSection) {
            if (!str_contains($result['body'], $expectedSection)) {
                $issues[] = 'admin download form is missing section label: ' . $expectedSection;
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
        'admin_event_form' => ['<legend>Událost</legend>', '<legend>Podrobnosti</legend>', '<legend>Popis události</legend>'],
        'admin_event_create_form' => ['<legend>Událost</legend>', '<legend>Podrobnosti</legend>', '<legend>Popis události</legend>'],
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
        'admin_polls_form' => ['<legend>Anketa</legend>', '<legend>?asov? omezen?</legend>', '<legend>Vyhled?va?e a sd?len?</legend>'],
        'admin_polls_create_form' => ['<legend>Anketa</legend>', '<legend>?asov? omezen?</legend>', '<legend>Vyhled?va?e a sd?len?</legend>'],
        'admin_res_resource_form' => ['<legend>Místa konání</legend>', '<legend>Režim slotů', '<legend>Předdefinované sloty</legend>', '<legend>Hromadný generátor</legend>'],
        'admin_res_resource_create_form' => ['<legend>Místa konání</legend>', '<legend>Režim slotů', '<legend>Předdefinované sloty</legend>', '<legend>Hromadný generátor</legend>'],
    ];
    $adminFormSectionForbiddenFragments['admin_polls_form'] = ['<legend>?asov? omezen?</legend>', '<legend>Vyhled?va?e a sd?len?</legend>'];
    $adminFormSectionForbiddenFragments['admin_polls_create_form'] = ['<legend>?asov? omezen?</legend>', '<legend>Vyhled?va?e a sd?len?</legend>'];
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
        if (!str_contains($result['body'], 'name="kat"')) {
            $issues[] = 'admin faq category filter is missing';
        }
        if (!str_contains($result['body'], 'name="status"')) {
            $issues[] = 'admin faq status filter is missing';
        }
        foreach ([
            'Kategorie FAQ',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin faq page is missing fragment: ' . $expectedFragment;
            }
        }
        if (
            !str_contains($result['body'], 'Přehled otázek FAQ')
            && !str_contains($result['body'], 'Zatím tu nejsou žádné otázky.')
            && !str_contains($result['body'], 'Pro zvolený filtr tu teď nejsou žádné otázky.')
        ) {
            $issues[] = 'admin faq page is missing both list caption and empty state';
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
            'name="visibility"',
            'chat_message.php',
            'name="action" value="approve"',
            'name="action" value="hide"',
            'Označit jako přečtené',
            'Označit jako vyřízené',
            'data-selection-status="chat"',
            'Hromadné akce s vybranými zprávami',
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

    if ($page['label'] === 'chat') {
        foreach ([
            $chatApprovedMessageText,
            'name="q"',
            'name="razeni"',
            'name="email"',
            'name="message"',
        ] as $expectedFragment) {
            if ($expectedFragment !== '' && !str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'public chat is missing fragment: ' . $expectedFragment;
            }
        }
        foreach ([
            $chatPendingMessageText,
            $chatHiddenMessageText,
            'mailto:',
            'name="web"',
            'runtime-audit-chat',
        ] as $forbiddenFragment) {
            if ($forbiddenFragment !== '' && str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'public chat still exposes forbidden fragment: ' . $forbiddenFragment;
            }
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
        if (
            !str_contains($result['body'], 'Přehled položek sekce')
            && !str_contains($result['body'], 'Zatím tu nejsou žádné položky této sekce.')
            && !str_contains($result['body'], 'Pro zvolený filtr tu teď nejsou žádné položky.')
        ) {
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

    if ($page['label'] === 'admin_downloads') {
        foreach (['name="kat"', 'name="typ"', 'name="source"', 'name="platform"', 'name="featured"'] as $expectedFilter) {
            if (!str_contains($result['body'], $expectedFilter)) {
                $issues[] = 'admin downloads is missing filter: ' . $expectedFilter;
            }
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
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin forms page is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], 'Přehled formulářů') && !str_contains($result['body'], 'Otevřené')) {
            $issues[] = 'admin forms list is missing open submissions column';
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
            'Připravit issue',
            'Otevřít návrh na GitHubu',
            'Připojit existující issue',
            'Repozitář',
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
            'Viditelnost nových souborů',
            'SVG už knihovna z bezpečnostních důvodů nepřijímá.',
            'Veřejná i soukromá',
            'Filtry knihovny médií',
            'Použitá média nelze smazat ani přepnout do soukromého režimu',
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
        if (
            !str_contains($result['body'], 'Přehled statických stránek')
            && !str_contains($result['body'], 'Zatím tu nejsou žádné statické stránky.')
            && !str_contains($result['body'], 'Pro zvolený filtr tu teď nejsou žádné statické stránky.')
        ) {
            $issues[] = 'admin pages table caption was not updated';
        }
    }

    if ($page['label'] === 'admin_pages' && !str_contains($result['body'], '/admin/menu.php')) {
        $issues[] = 'admin pages list is missing the unified navigation link';
    }

    if ($page['label'] === 'admin_food') {
        if (!str_contains($result['body'], 'name="q"')) {
            $issues[] = 'admin food search field is missing';
        }
        if (!str_contains($result['body'], 'name="status"')) {
            $issues[] = 'admin food status filter is missing';
        }
        if (!str_contains($result['body'], 'name="type"')) {
            $issues[] = 'admin food type filter is missing';
        }
        if (!str_contains($result['body'], 'name="scope"')) {
            $issues[] = 'admin food scope filter is missing';
        }
        foreach ([
            'Přehled jídelních lístků',
            'Přehled nápojových lístků',
            'Historie revizí',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin food page is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if (false && $page['label'] === 'admin_places') {
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
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin reservation resources is missing fragment: ' . $expectedFragment;
            }
        }
        if (
            !str_contains($result['body'], 'Přehled zdrojů rezervací')
            && !str_contains($result['body'], 'Zatím tu nejsou žádné zdroje rezervací.')
            && !str_contains($result['body'], 'Pro zvolený filtr tu teď nejsou žádné zdroje rezervací.')
        ) {
            $issues[] = 'admin reservation resources is missing both list caption and empty state';
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
        if (!str_contains($result['body'], 'name="status"')) {
            $issues[] = 'admin podcast shows status filter is missing';
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
            'Veřejná stránka',
            'Veřejná stránka zdroje',
            '>Všechny podcasty<',
            '>Správa zdrojů<',
        ] as $forbiddenFragment) {
            if (str_contains($result['body'], $forbiddenFragment)) {
                $issues[] = 'admin page still contains outdated action wording: ' . $forbiddenFragment;
            }
        }
    }

    if ($page['label'] === 'admin_places') {
        foreach ([
            'name="q"',
            'name="status"',
            'name="kind"',
            'name="category"',
            'name="locality"',
            'Přehled zajímavých míst',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin places page is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'admin_blog_pages') {
        foreach ([
            'Pořadí stránek blogu',
            'Tady určujete pořadí statických stránek a externích odkazů blogu',
            'Uložit pořadí',
            'Nová stránka blogu',
            'Přidat externí odkaz blogu',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin blog pages page is missing fragment: ' . $expectedFragment;
            }
        }
        if (!str_contains($result['body'], 'id="blog-page-order-status"') || !str_contains($result['body'], 'data-sort-id=')) {
            $issues[] = 'admin blog pages page is missing accessible reorder controls';
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
            'id="board-type-help"',
            'revisions.php?type=board&amp;id=',
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

    if ($page['label'] === 'admin_download_form') {
        foreach ([
            'name="project_url"',
            'name="release_date"',
            'name="requirements"',
            'name="checksum_sha256"',
            'name="series_key"',
            'name="is_featured"',
            'revisions.php?type=download&amp;id=',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin download form is missing extended field: ' . $expectedField;
            }
        }
    }

    if ($page['label'] === 'admin_event_form') {
        foreach ([
            'name="slug"',
            'name="event_kind"',
            'name="excerpt"',
            'name="program_note"',
            'name="organizer_name"',
            'name="organizer_email"',
            'name="registration_url"',
            'name="price_note"',
            'name="accessibility_note"',
            'name="event_image"',
            'name="unpublish_at"',
            'revisions.php?type=event&amp;id=',
            'Zpět na přehled událostí',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin event form is missing field: ' . $expectedField;
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
            'Historie revizí',
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
            '/admin/menu.php',
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
            'name="public_visibility"',
            'name="internal_note"',
            '/admin/chat_update.php',
            '/admin/chat_reply.php',
            'Runtime Audit',
            'Zpět na přehled chat zpráv',
            'Rychlé kroky',
            'Workflow zprávy',
            'Odpověď odesílateli',
            'Historie zprávy',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'admin chat detail is missing fragment: ' . $expectedFragment;
            }
        }
        foreach ([
            'Zpět na chat zprávy',
            '>Akce<',
            'Vrátit jako nové',
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

    if (in_array($page['label'], ['admin_faq_form', 'admin_faq_create_form'], true)) {
        foreach ([
            'name="slug"',
            'name="excerpt"',
            'name="category_id"',
            'name="meta_title"',
            'name="meta_description"',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin faq form is missing field: ' . $expectedField;
            }
        }
        if (!str_contains($result['body'], 'id="answer"') && !str_contains($result['body'], 'name="answer"')) {
            $issues[] = 'admin faq form is missing answer field';
        }
    }

    if (in_array($page['label'], ['admin_news_form', 'admin_news_create_form'], true)) {
        foreach ([
            'name="slug"',
            'name="unpublish_at"',
            'name="admin_note"',
            'name="meta_title"',
            'name="meta_description"',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin news form is missing field: ' . $expectedField;
            }
        }
        if (!str_contains($result['body'], 'id="content"') && !str_contains($result['body'], 'name="content"')) {
            $issues[] = 'admin news form is missing content field';
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
            'name="meta_title"',
            'name="meta_description"',
            'Historie revizí',
            'Upravit zajímavé místo',
            'Zpět na zajímavá místa',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin place form is missing field: ' . $expectedField;
            }
        }
    }

    if (false && $page['label'] === 'admin_place_form') {
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
        $issues = array_values(array_filter(
            $issues,
            static fn (string $issue): bool => !preg_match('/^admin polls page is missing field: P/u', $issue)
        ));
        if (!str_contains($result['body'], 'Přehled anket')) {
            $issues[] = 'admin polls page is missing field: Přehled anket';
        }
    }

    if ($page['label'] === 'admin_polls') {
        $issues = array_values(array_filter(
            $issues,
            static fn (string $issue): bool =>
                !in_array($issue, [
                    'admin polls page is missing field: Přehled anket',
                ], true)
        ));
        if (!str_contains($result['body'], 'Přehled anket')) {
            $issues[] = 'admin polls page is missing field: Přehled anket';
        }
    }

    if ($page['label'] === 'admin_polls') {
        $issues = array_values(array_filter(
            $issues,
            static fn (string $issue): bool => !preg_match('/^admin polls page is missing field: P/u', $issue)
        ));
        if (!str_contains($result['body'], 'name="status"') || !str_contains($result['body'], 'name="q"')) {
            $issues[] = 'admin polls page is missing filter controls';
        }
    }

    if (in_array($page['label'], ['admin_polls_form', 'admin_polls_create_form'], true)) {
        foreach ([
            'name="slug"',
            'name="description"',
            'name="start_date"',
            'name="start_time"',
            'name="end_date"',
            'name="end_time"',
            'name="options[]"',
            'name="meta_title"',
            'name="meta_description"',
            'name="redirect"',
            'Zpět na ankety',
        ] as $expectedField) {
            if (!str_contains($result['body'], $expectedField)) {
                $issues[] = 'admin polls form is missing field: ' . $expectedField;
            }
        }
        $issues = array_values(array_filter(
            $issues,
            static fn (string $issue): bool => !preg_match('/^admin polls form is missing field: Z/u', $issue)
        ));
        if (!str_contains($result['body'], 'Zpět na ankety')) {
            $issues[] = 'admin polls form is missing field: Zpět na ankety';
        }
    }
    if (in_array($page['label'], ['admin_polls_form', 'admin_polls_create_form'], true)) {
        $issues = array_values(array_filter(
            $issues,
            static fn (string $issue): bool =>
                !in_array($issue, [
                    'admin polls form is missing field: Zpět na ankety',
                ], true)
        ));
        if (!str_contains($result['body'], 'Zpět na ankety')) {
            $issues[] = 'admin polls form is missing field: Zpět na ankety';
        }
    }

    if (in_array($page['label'], ['admin_polls_form', 'admin_polls_create_form'], true)) {
        $issues = array_values(array_filter(
            $issues,
            static fn (string $issue): bool => !preg_match('/^admin polls form is missing field: Z/u', $issue)
        ));
        if (!str_contains($result['body'], 'name="redirect"')) {
            $issues[] = 'admin polls form is missing return redirect field';
        }
    }

    if ($page['label'] === 'admin_polls_form' && !str_contains($result['body'], 'revisions.php?type=poll&amp;id=')) {
        $issues[] = 'admin polls form is missing revisions link';
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
            'name="is_published"',
            'revisions.php?type=podcast_show&amp;id=',
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
            'revisions.php?type=podcast_episode&amp;id=',
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
            '<managingEditor>runtime.audit.owner@example.test (Runtime Audit Owner)</managingEditor>',
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
        if (str_contains($result['body'], 'length="0"')) {
            $issues[] = 'podcast feed still contains zero-length enclosure';
        }
    }

    if ($page['label'] === 'blog_index' && $articleId !== false && $runtimeAuditAuthorPath !== '' && !str_contains($result['body'], $runtimeAuditAuthorPath)) {
        $issues[] = 'blog listing is missing public author links';
    }
    if ($page['label'] === 'blog_index' && $articleId !== false && $runtimeAuditAuthorPath !== '' && !str_contains($result['body'], authorIndexPath())) {
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
    if ($page['label'] === 'blog_article' && $articleId !== false && $articleRow) {
        $expectedArticleMetaTitle = trim((string)($articleRow['meta_title'] ?? ''));
        if ($expectedArticleMetaTitle === '') {
            $expectedArticleMetaTitle = trim((string)($articleRow['title'] ?? ''));
        }
        $expectedArticleMetaDescription = trim((string)($articleRow['meta_description'] ?? ''));
        if ($expectedArticleMetaDescription === '') {
            $expectedArticleMetaDescription = trim((string)($articleRow['perex'] ?? ''));
        }
        if ($expectedArticleMetaDescription === '') {
            $expectedArticleMetaDescription = articleExcerpt((string)($articleRow['content'] ?? ''), 220);
        }
        if ($expectedArticleMetaTitle !== '' && !str_contains($result['body'], '<meta property="og:title" content="' . h($expectedArticleMetaTitle) . '">')) {
            $issues[] = 'blog article social title is not the standalone article title';
        }
        if ($expectedArticleMetaDescription !== '' && !str_contains($result['body'], '<meta property="og:description" content="' . h($expectedArticleMetaDescription) . '">')) {
            $issues[] = 'blog article social description does not fall back to article text';
        }
        if (!str_contains($result['body'], '<meta name="twitter:title" content="' . h($expectedArticleMetaTitle) . '">')) {
            $issues[] = 'blog article is missing twitter title metadata';
        }
        $expectedArticleMetaImage = trim((string)($articleRow['image_file'] ?? ''));
        if ($expectedArticleMetaImage !== '') {
            $expectedArticleMetaImage = '/uploads/articles/' . rawurlencode($expectedArticleMetaImage);
        } else {
            $expectedArticleMetaImage = trim((string)($articleRow['blog_logo_file'] ?? ''));
            if ($expectedArticleMetaImage !== '') {
                $expectedArticleMetaImage = '/uploads/blogs/' . rawurlencode($expectedArticleMetaImage);
            }
        }
        if ($expectedArticleMetaImage !== '' && !str_contains($result['body'], '<meta property="og:image" content="' . h(siteUrl($expectedArticleMetaImage)) . '">')) {
            $issues[] = 'blog article social image does not fall back to the blog logo';
        }
    }
    if ($page['label'] === 'blog_article' && $articleId !== false && $runtimeAuditAuthorPath !== '') {
        foreach (['O autorovi', 'Profil autora', 'Web autora'] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'blog article is missing author panel fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'public_profile') {
        foreach ([
            'id="profile-links-heading"',
            'aria-labelledby="profile-links-heading"',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'public profile is missing heading-backed account navigation fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], '<nav aria-label="')) {
            $issues[] = 'public profile still uses aria-label-only account navigation';
        }
    }

    if ($page['label'] === 'news_index' && $newsCanonicalPath !== '' && !str_contains($result['body'], $newsCanonicalPath)) {
        $issues[] = 'news listing is missing detail links';
    }

    if ($page['label'] === 'news_index' && $newsId !== false && $runtimeAuditAuthorPath !== '' && !str_contains($result['body'], $runtimeAuditAuthorPath)) {
        $issues[] = 'news listing is missing public author links';
    }

    if (in_array($page['label'], ['news_index', 'news_index_search'], true)) {
        foreach ([
            'name="q"',
            'Hledat v novinkách',
            '<h2 id="news-search-heading" class="sr-only">Hledání v novinkách</h2>',
            '<form method="get" class="stack stack--tight" role="search" aria-labelledby="news-search-heading">',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'news listing is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], 'role="search" aria-label="Hledání v novinkách"')) {
            $issues[] = 'news listing still uses aria-label-only search landmark';
        }
        if ($runtimeAuditNewsExpiredTitle !== '' && str_contains($result['body'], $runtimeAuditNewsExpiredTitle)) {
            $issues[] = 'news listing still exposes expired news';
        }
        if ($runtimeAuditNewsDeletedTitle !== '' && str_contains($result['body'], $runtimeAuditNewsDeletedTitle)) {
            $issues[] = 'news listing still exposes deleted news';
        }
    }

    if ($page['label'] === 'news_index_search') {
        if ($newsCanonicalPath !== '' && !str_contains($result['body'], $newsCanonicalPath)) {
            $issues[] = 'filtered news listing is missing detail links';
        }
        if (!str_contains($result['body'], 'value="' . h($runtimeAuditNewsSearchTerm) . '"')
            && !str_contains($result['body'], 'value="' . $runtimeAuditNewsSearchTerm . '"')) {
            $issues[] = 'filtered news listing does not preserve search query';
        }
    }

    if ($page['label'] === 'news_article') {
        if ($newsRow && !str_contains($result['body'], newsTitleCandidate((string)($newsRow['title'] ?? ''), ''))) {
            $issues[] = 'news article is missing title';
        }
        if ($newsId !== false && $runtimeAuditAuthorPath !== '' && !str_contains($result['body'], $runtimeAuditAuthorPath)) {
            $issues[] = 'news article is missing public author link';
        }
        if (!str_contains($result['body'], 'application/ld+json')) {
            $issues[] = 'news article is missing structured data';
        }
    }

    if ($page['label'] === 'faq_index' && $faqCanonicalPath !== '' && !str_contains($result['body'], $faqCanonicalPath)) {
        $issues[] = 'faq listing is missing detail links';
    }
    if ($page['label'] === 'faq_index') {
        foreach ([
            'name="q"',
            'name="kat"',
            'tab-nav',
            '<h2 id="faq-display-mode-heading" class="sr-only">Zobrazení znalostní báze</h2>',
            '<nav class="tab-nav" aria-labelledby="faq-display-mode-heading">',
            'application/ld+json',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'faq listing is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], 'class="tab-nav" aria-label="Zobrazení znalostní báze"')) {
            $issues[] = 'faq listing still uses aria-label-only display navigation';
        }
    }

    if ($page['label'] === 'food' && $foodCardCanonicalPath !== '' && !str_contains($result['body'], $foodCardCanonicalPath)) {
        $issues[] = 'food index is missing detail links';
    }
    if ($page['label'] === 'food' && $runtimeAuditFoodFutureTitle !== '' && str_contains($result['body'], $runtimeAuditFoodFutureTitle)) {
        $issues[] = 'food index incorrectly shows upcoming food fixture';
    }

    if ($page['label'] === 'food_archive' && $foodCardCanonicalPath !== '' && !str_contains($result['body'], $foodCardCanonicalPath)) {
        $issues[] = 'food archive is missing detail links';
    }

    if ($page['label'] === 'food_archive') {
        foreach ([
            'name="q"',
            'name="typ"',
            'Platí nyní',
            'Připravujeme',
            'Archivní',
            'Všechny lístky',
            '<h2 id="food-archive-scope-heading" class="sr-only">Rozsah výpisu lístků</h2>',
            '<nav class="tab-nav" aria-labelledby="food-archive-scope-heading">',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'food archive is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], 'class="tab-nav" aria-label="Rozsah výpisu lístků"')) {
            $issues[] = 'food archive still uses aria-label-only scope navigation';
        }
        if ($runtimeAuditFoodFutureTitle !== '' && !str_contains($result['body'], $runtimeAuditFoodFutureTitle)) {
            $issues[] = 'food archive is missing upcoming food fixture';
        }
    }

    if ($page['label'] === 'food_card') {
        if ($foodCardRow && !str_contains($result['body'], (string)($foodCardRow['title'] ?? ''))) {
            $issues[] = 'food card is missing title';
        }
        if (!str_contains($result['body'], 'Vytisknout')) {
            $issues[] = 'food card is missing print action';
        }
        if (!str_contains($result['body'], 'Zpět na aktuální lístek') && !str_contains($result['body'], 'Zpět do archivu')) {
            $issues[] = 'food card is missing back navigation';
        }
        foreach ([
            '<h2 id="food-card-breadcrumb-heading" class="sr-only">Drobečková navigace</h2>',
            '<nav aria-labelledby="food-card-breadcrumb-heading">',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'food card is missing heading-backed breadcrumb fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], '<nav aria-label="Drobečková navigace"')) {
            $issues[] = 'food card still uses aria-label-only breadcrumb navigation';
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
        if (!str_contains($result['body'], 'application/ld+json')) {
            $issues[] = 'faq article is missing structured data';
        }
    }

    if ($page['label'] === 'gallery_index' && $galleryAlbumCanonicalPath !== '' && !str_contains($result['body'], $galleryAlbumCanonicalPath)) {
        $issues[] = 'gallery listing is missing album detail links';
    }
    if ($page['label'] === 'gallery_index') {
        foreach ([
            'name="q"',
            'Hledat v galerii',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'gallery listing is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], '/uploads/gallery/')) {
            $issues[] = 'gallery listing still exposes uploads/gallery paths';
        }
    }

    if ($page['label'] === 'gallery_album') {
        if ($galleryAlbumRow && !str_contains($result['body'], (string)($galleryAlbumRow['name'] ?? ''))) {
            $issues[] = 'gallery album is missing title';
        }
        if ($galleryAlbumPhotoCanonicalPath !== '' && !str_contains($result['body'], $galleryAlbumPhotoCanonicalPath)) {
            $issues[] = 'gallery album is missing photo detail links';
        }
        foreach ([
            'name="q"',
            'Hledat v albu',
            '<h2 id="gallery-album-breadcrumb-heading" class="sr-only">Drobečková navigace</h2>',
            '<nav aria-labelledby="gallery-album-breadcrumb-heading">',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'gallery album is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], '<nav aria-label="Drobečková navigace"')) {
            $issues[] = 'gallery album still uses aria-label-only breadcrumb navigation';
        }
        if (str_contains($result['body'], '/uploads/gallery/')) {
            $issues[] = 'gallery album still exposes uploads/gallery paths';
        }
    }

    if ($page['label'] === 'gallery_photo') {
        if ($galleryPhotoRow && !str_contains($result['body'], (string)($galleryPhotoRow['label'] ?? ''))) {
            $issues[] = 'gallery photo is missing title';
        }
        if (!str_contains($result['body'], 'Zpět do alba')) {
            $issues[] = 'gallery photo is missing back link';
        }
        foreach ([
            'Kopírovat odkaz',
            'Další fotografie v albu',
            '<h2 id="gallery-photo-breadcrumb-heading" class="sr-only">Drobečková navigace</h2>',
            '<nav aria-labelledby="gallery-photo-breadcrumb-heading">',
            'id="gallery-photo-nav-heading"',
            'aria-labelledby="gallery-photo-nav-heading"',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'gallery photo is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], '<nav aria-label="Drobečková navigace"')) {
            $issues[] = 'gallery photo still uses aria-label-only breadcrumb navigation';
        }
        if (str_contains($result['body'], 'aria-label="Navigace mezi fotografiemi"')) {
            $issues[] = 'gallery photo still uses aria-label-only photo navigation';
        }
        if (str_contains($result['body'], '/uploads/gallery/')) {
            $issues[] = 'gallery photo still exposes uploads/gallery paths';
        }
    }

    if ($page['label'] === 'events_index') {
        if ($eventCanonicalPath !== '' && !str_contains($result['body'], $eventCanonicalPath)) {
            $issues[] = 'events listing is missing detail links';
        }
        foreach ([
            'Filtrovat akce',
            'Hledat v akcích',
            'Typ akce',
            'Období',
            'Přidat do kalendáře',
            'Připravujeme',
            '<h2 id="events-scope-heading" class="sr-only">Rozsah výpisu akcí</h2>',
            '<nav class="tab-nav" aria-labelledby="events-scope-heading">',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'events listing is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], 'class="tab-nav" aria-label="Rozsah výpisu akcí"')) {
            $issues[] = 'events listing still uses aria-label-only scope navigation';
        }
        if ($eventFutureTitle !== '' && !str_contains($result['body'], $eventFutureTitle)) {
            $issues[] = 'events listing is missing the runtime audit future event';
        }
    }

    if ($page['label'] === 'events_index_ongoing') {
        foreach ([
            'Právě probíhající akce',
            'Právě probíhá',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'ongoing events listing is missing fragment: ' . $expectedFragment;
            }
        }
        if ($eventOngoingTitle !== '' && !str_contains($result['body'], $eventOngoingTitle)) {
            $issues[] = 'ongoing events listing is missing the runtime audit ongoing event';
        }
    }

    if ($page['label'] === 'events_article') {
        if ($eventRow && !str_contains($result['body'], (string)($eventRow['title'] ?? ''))) {
            $issues[] = 'events article is missing title';
        }
        foreach ([
            'Zpět na události',
            'O události',
            'Program a doplňující informace',
            'Praktické informace',
            'Registrovat se',
            'Přidat do kalendáře',
            'application/ld+json',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'events article is missing fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'events_ics') {
        $contentTypeOk = false;
        foreach ($result['headers'] as $headerLine) {
            if (stripos($headerLine, 'Content-Type: text/calendar') === 0) {
                $contentTypeOk = true;
                break;
            }
        }
        if (!$contentTypeOk) {
            $issues[] = 'event ICS response is missing text/calendar content type';
        }
        foreach ([
            'BEGIN:VCALENDAR',
            'BEGIN:VEVENT',
            'SUMMARY:' . $eventFutureTitle,
            'END:VCALENDAR',
        ] as $expectedFragment) {
            if ($expectedFragment !== 'SUMMARY:' && !str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'event ICS response is missing fragment: ' . $expectedFragment;
            } elseif ($expectedFragment === 'SUMMARY:' . $eventFutureTitle && $eventFutureTitle !== '' && !str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'event ICS response is missing summary title';
            }
        }
    }

    if ($page['label'] === 'board_article') {
        if ($boardRow && !str_contains($result['body'], (string)($boardRow['title'] ?? ''))) {
            $issues[] = 'board article is missing title';
        }
        if (
            !str_contains($result['body'], boardModuleBackLabel())
            && !str_contains($result['body'], BASE_URL . '/board/index.php')
        ) {
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

    if ($page['label'] === 'downloads_article') {
        foreach (['Praktické informace', 'Stažení', 'Další verze ke stažení', 'Domovská stránka projektu'] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'downloads article is missing extended fragment: ' . $expectedFragment;
            }
        }
    }

    if ($page['label'] === 'places_article') {
        if ($placeRow && !str_contains($result['body'], (string)($placeRow['name'] ?? ''))) {
            $issues[] = 'places article is missing title';
        }
        foreach ([
            'Zpět na zajímavá místa',
            'Praktické informace',
            'Kontakt',
            'Kopírovat odkaz',
            'application/ld+json',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'places article is missing fragment: ' . $expectedFragment;
            }
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
        if (str_contains($result['body'], '/uploads/places/')) {
            $issues[] = 'places article still exposes uploads/places paths';
        }
    }

    if (false && $page['label'] === 'places_article') {
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

    if (in_array($page['label'], ['polls_index', 'polls_index_search'], true)) {
        if ($pollCanonicalPath !== '' && !str_contains($result['body'], $pollCanonicalPath)) {
            $issues[] = 'polls listing is missing detail links';
        }
        foreach ([
            'name="q"',
            'Aktivní ankety',
            'Archiv',
            '<h2 id="poll-search-heading" class="sr-only">Hledání v anketách</h2>',
            '<form method="get" class="listing-filters" role="search" aria-labelledby="poll-search-heading">',
            '<h2 id="polls-filter-heading" class="sr-only">Filtr anket</h2>',
            '<nav aria-labelledby="polls-filter-heading">',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'polls listing is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], '<nav aria-label="Filtr anket"')) {
            $issues[] = 'polls listing still uses aria-label-only filter navigation';
        }
        if (str_contains($result['body'], 'aria-label="Hledání v anketách"')) {
            $issues[] = 'polls listing still uses aria-label-only search landmark';
        }
    }

    if ($page['label'] === 'polls_index_search') {
        if ($pollCanonicalPath !== '' && !str_contains($result['body'], $pollCanonicalPath)) {
            $issues[] = 'filtered polls listing is missing detail links';
        }
        if ($runtimeAuditPollSearchTerm !== '' && !str_contains($result['body'], 'value="' . $runtimeAuditPollSearchTerm . '"')) {
            $issues[] = 'filtered polls listing does not preserve search query';
        }
    }

    if ($page['label'] === 'polls_detail') {
        if ($pollRow && !str_contains($result['body'], (string)($pollRow['question'] ?? ''))) {
            $issues[] = 'poll detail is missing title';
        }
        if (!str_contains($result['body'], 'Zpět na přehled anket')) {
            $issues[] = 'poll detail is missing back link';
        }
        if (!str_contains($result['body'], 'Zpět na přehled anket')) {
            $issues[] = 'poll detail is missing clean back link';
        } else {
            $issues = array_values(array_filter(
                $issues,
                static fn (string $issue): bool => $issue !== 'poll detail is missing back link'
            ));
        }
        if ($pollRow && !str_contains($result['body'], (string)($pollRow['excerpt'] ?? ''))) {
            $issues[] = 'poll detail is missing description';
        }
    }

    if ($page['label'] === 'polls_detail') {
        if (str_contains($result['body'], 'href="' . BASE_URL . '/polls/index.php')) {
            $issues = array_values(array_filter(
                $issues,
                static fn (string $issue): bool =>
                    $issue !== 'poll detail is missing back link'
                    && $issue !== 'poll detail is missing clean back link'
            ));
        } else {
            $issues[] = 'poll detail is missing list back href';
        }
    }

    if ($page['label'] === 'podcast_index' && $podcastShowSlug !== '' && !str_contains($result['body'], '/podcast/' . $podcastShowSlug)) {
        $issues[] = 'podcast listing is missing show detail link';
    }
    if ($page['label'] === 'podcast_index' && $podcastHiddenShowTitle !== '' && str_contains($result['body'], $podcastHiddenShowTitle)) {
        $issues[] = 'podcast listing still exposes hidden show';
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
        if (!str_contains($result['body'], 'application/ld+json')) {
            $issues[] = 'podcast show is missing structured data';
        }
        if (str_contains($result['body'], '/uploads/podcasts/')) {
            $issues[] = 'podcast show still exposes direct uploads paths';
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
        if (!str_contains($result['body'], 'application/ld+json')) {
            $issues[] = 'podcast episode is missing structured data';
        }
        foreach ([
            '<h2 id="podcast-episode-breadcrumb-heading" class="sr-only">Drobečková navigace</h2>',
            '<nav aria-labelledby="podcast-episode-breadcrumb-heading">',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'podcast episode is missing heading-backed breadcrumb fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], '<nav aria-label="Drobečková navigace"')) {
            $issues[] = 'podcast episode still uses aria-label-only breadcrumb navigation';
        }
        if (str_contains($result['body'], '/uploads/podcasts/')) {
            $issues[] = 'podcast episode still exposes direct uploads paths';
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
        if (!str_contains($result['body'], 'Zpět na blog')) {
            $issues[] = 'public author page is missing back link to blog';
        }
    }

    if ($page['label'] === 'reservations_resource') {
        foreach ([
            'Zpět na přehled zdrojů',
            'Jak rezervace fungují',
            'id="reservation-calendar-nav-heading"',
            'aria-labelledby="reservation-calendar-nav-heading"',
        ] as $expectedFragment) {
            if (!str_contains($result['body'], $expectedFragment)) {
                $issues[] = 'reservations resource page is missing fragment: ' . $expectedFragment;
            }
        }
        if (str_contains($result['body'], 'class="calendar-nav" aria-label="Navigace v kalendáři"')) {
            $issues[] = 'reservations resource still uses aria-label-only calendar navigation';
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

echo "=== news_article_slug_redirect ===\n";
if ($newsLegacySlugPath === '' || $newsCanonicalPath === '' || $newsLegacySlugPath === $newsCanonicalPath) {
    echo "OK\n";
} else {
    $legacyNewsSlugProbe = fetchUrl($newsLegacySlugUrl, '', 0);
    if (!str_contains($legacyNewsSlugProbe['status'], '301') && !str_contains($legacyNewsSlugProbe['status'], '302')) {
        echo "- legacy news slug URL does not redirect ({$legacyNewsSlugProbe['status']})\n";
        $failures++;
    } elseif (!responseHasLocationHeader($legacyNewsSlugProbe['headers'], $newsCanonicalPath, $baseUrl)) {
        echo "- legacy news slug URL does not redirect to canonical slug path\n";
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

echo "=== gallery_visibility_guards ===\n";
$galleryVisibilityIssues = [];
if ($galleryHiddenAlbumUrl !== '') {
    $hiddenAlbumProbe = fetchUrl($galleryHiddenAlbumUrl, '', 0);
    if (!preg_match('/\s404\s/', $hiddenAlbumProbe['status'])) {
        $galleryVisibilityIssues[] = 'hidden gallery album does not return 404';
    }
}
if ($galleryHiddenPhotoUrl !== '') {
    $hiddenPhotoProbe = fetchUrl($galleryHiddenPhotoUrl, '', 0);
    if (!preg_match('/\s404\s/', $hiddenPhotoProbe['status'])) {
        $galleryVisibilityIssues[] = 'hidden gallery photo does not return 404';
    }
}
if ($galleryPhotoMediaCanonicalUrl !== '') {
    $galleryMediaProbe = fetchUrl($galleryPhotoMediaCanonicalUrl, '', 0);
    if (!str_contains($galleryMediaProbe['status'], '200')) {
        $galleryVisibilityIssues[] = 'gallery image endpoint does not serve public thumbnails';
    } else {
        $hasImageContentType = false;
        foreach ($galleryMediaProbe['headers'] as $galleryMediaHeader) {
            if (stripos((string)$galleryMediaHeader, 'Content-Type: image/') === 0) {
                $hasImageContentType = true;
                break;
            }
        }
        if (!$hasImageContentType) {
            $galleryVisibilityIssues[] = 'gallery image endpoint is missing image content type';
        }
    }
}
if ($runtimeAuditGalleryFilename !== '') {
    $galleryDirectUploadProbe = fetchUrl($baseUrl . '/uploads/gallery/' . rawurlencode($runtimeAuditGalleryFilename), '', 0);
    if (!preg_match('/\s40[34]\s/', $galleryDirectUploadProbe['status'])) {
        $galleryVisibilityIssues[] = 'direct uploads/gallery path is still publicly reachable';
    }
}
$gallerySearchProbe = fetchUrl($baseUrl . '/gallery/index.php?q=' . urlencode('Runtime audit skryté'), '', 0);
if (!str_contains($gallerySearchProbe['status'], '200')) {
    $galleryVisibilityIssues[] = 'gallery search page did not load for visibility audit';
} else {
    if ($galleryHiddenAlbumTitle !== '' && str_contains($gallerySearchProbe['body'], $galleryHiddenAlbumTitle)) {
        $galleryVisibilityIssues[] = 'gallery search still exposes hidden album';
    }
    if ($galleryHiddenPhotoTitle !== '' && str_contains($gallerySearchProbe['body'], $galleryHiddenPhotoTitle)) {
        $galleryVisibilityIssues[] = 'gallery search still exposes hidden photo';
    }
}
if ($galleryVisibilityIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($galleryVisibilityIssues as $galleryVisibilityIssue) {
        echo '- ' . $galleryVisibilityIssue . "\n";
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

echo "=== events_article_slug_redirect ===\n";
if ($eventLegacySlugPath === '' || $eventCanonicalPath === '' || $eventLegacySlugPath === $eventCanonicalPath) {
    echo "OK\n";
} else {
    $legacyEventSlugProbe = fetchUrl($eventLegacySlugUrl, '', 0);
    if (!str_contains($legacyEventSlugProbe['status'], '301') && !str_contains($legacyEventSlugProbe['status'], '302')) {
        echo "- legacy event slug URL does not redirect ({$legacyEventSlugProbe['status']})\n";
        $failures++;
    } elseif (!responseHasLocationHeader($legacyEventSlugProbe['headers'], $eventCanonicalPath, $baseUrl)) {
        echo "- legacy event slug URL does not redirect to canonical slug path\n";
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

echo "=== places_article_slug_redirect ===\n";
if ($placeLegacySlugPath === '' || $placeCanonicalPath === '' || $placeLegacySlugPath === $placeCanonicalPath) {
    echo "OK\n";
} else {
    $legacyPlaceSlugProbe = fetchUrl($placeLegacySlugUrl, '', 0);
    if (!str_contains($legacyPlaceSlugProbe['status'], '301') && !str_contains($legacyPlaceSlugProbe['status'], '302')) {
        echo "- legacy place slug URL does not redirect ({$legacyPlaceSlugProbe['status']})\n";
        $failures++;
    } elseif (!responseHasLocationHeader($legacyPlaceSlugProbe['headers'], $placeCanonicalPath, $baseUrl)) {
        echo "- legacy place slug URL does not redirect to canonical slug path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== places_image_visibility_guards ===\n";
$placesImageIssues = [];
if ($placeVisibleImagePath !== '') {
    $placeVisibleImageProbe = fetchUrl($baseUrl . $placeVisibleImagePath, '', 0);
    if (!str_contains($placeVisibleImageProbe['status'], '200')) {
        $placesImageIssues[] = 'places image endpoint does not serve public place images';
    } else {
        $hasImageContentType = false;
        foreach ($placeVisibleImageProbe['headers'] as $placeVisibleImageHeader) {
            if (stripos((string)$placeVisibleImageHeader, 'Content-Type: image/') === 0) {
                $hasImageContentType = true;
                break;
            }
        }
        if (!$hasImageContentType) {
            $placesImageIssues[] = 'places image endpoint is missing image content type';
        }
    }
}
if ($placeHiddenImagePath !== '') {
    $placeHiddenImageProbe = fetchUrl($baseUrl . $placeHiddenImagePath, '', 0);
    if (!preg_match('/\s404\s/', $placeHiddenImageProbe['status'])) {
        $placesImageIssues[] = 'hidden place image remains publicly reachable';
    }
}
$placeDirectUploadProbe = fetchUrl($baseUrl . '/uploads/places/' . rawurlencode((string)($cleanup['place_files'][0] ?? 'runtime-audit-place.png')), '', 0);
if (!preg_match('/\s40[34]\s/', $placeDirectUploadProbe['status'])) {
    $placesImageIssues[] = 'direct uploads/places path is still publicly reachable';
}
if ($placesImageIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($placesImageIssues as $placesImageIssue) {
        echo '- ' . $placesImageIssue . "\n";
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

echo "=== polls_slug_legacy_redirect ===\n";
if ($pollCanonicalPath === '' || $pollLegacySlugPath === '' || $pollLegacySlugUrl === '') {
    echo "OK\n";
} else {
    $legacyPollSlugProbe = fetchUrl($pollLegacySlugUrl, '', 0);
    $expectedLocation = 'Location: ' . $pollCanonicalPath;
    if (!str_contains($legacyPollSlugProbe['status'], '301') && !str_contains($legacyPollSlugProbe['status'], '302')) {
        echo "- legacy poll slug URL does not redirect ({$legacyPollSlugProbe['status']})\n";
        $failures++;
    } elseif (!in_array($expectedLocation, $legacyPollSlugProbe['headers'], true)) {
        echo "- legacy poll slug URL does not redirect to canonical slug path\n";
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

echo "=== podcast_show_slug_redirect ===\n";
if ($podcastShowLegacySlugPath === '' || $podcastShowSlug === '') {
    echo "OK\n";
} else {
    $legacyPodcastShowSlugProbe = fetchUrl($podcastShowLegacySlugUrl, '', 0);
    if (!str_contains($legacyPodcastShowSlugProbe['status'], '301') && !str_contains($legacyPodcastShowSlugProbe['status'], '302')) {
        echo "- legacy podcast show slug URL does not redirect ({$legacyPodcastShowSlugProbe['status']})\n";
        $failures++;
    } elseif (!responseHasLocationHeader($legacyPodcastShowSlugProbe['headers'], BASE_URL . '/podcast/' . rawurlencode((string)$podcastShowSlug), $baseUrl)) {
        echo "- legacy podcast show slug URL does not redirect to canonical path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== podcast_episode_slug_redirect ===\n";
if ($podcastEpisodeLegacySlugPath === '' || $podcastEpisodeCanonicalPath === '') {
    echo "OK\n";
} else {
    $legacyPodcastEpisodeSlugProbe = fetchUrl($podcastEpisodeLegacySlugUrl, '', 0);
    if (!str_contains($legacyPodcastEpisodeSlugProbe['status'], '301') && !str_contains($legacyPodcastEpisodeSlugProbe['status'], '302')) {
        echo "- legacy podcast episode slug URL does not redirect ({$legacyPodcastEpisodeSlugProbe['status']})\n";
        $failures++;
    } elseif (!responseHasLocationHeader($legacyPodcastEpisodeSlugProbe['headers'], $podcastEpisodeCanonicalPath, $baseUrl)) {
        echo "- legacy podcast episode slug URL does not redirect to canonical path\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== podcast_visibility_guards ===\n";
$podcastVisibilityIssues = [];
if ($podcastVisibleCoverUrl !== '') {
    $podcastVisibleCoverProbe = fetchUrl(str_starts_with($podcastVisibleCoverUrl, 'http') ? $podcastVisibleCoverUrl : $baseUrl . $podcastVisibleCoverUrl, '', 0);
    if (!str_contains($podcastVisibleCoverProbe['status'], '200')) {
        $podcastVisibilityIssues[] = 'podcast cover endpoint does not serve public show cover';
    }
}
if ($podcastVisibleEpisodeImageUrl !== '') {
    $podcastVisibleImageProbe = fetchUrl(str_starts_with($podcastVisibleEpisodeImageUrl, 'http') ? $podcastVisibleEpisodeImageUrl : $baseUrl . $podcastVisibleEpisodeImageUrl, '', 0);
    if (!str_contains($podcastVisibleImageProbe['status'], '200')) {
        $podcastVisibilityIssues[] = 'podcast image endpoint does not serve public episode image';
    }
}
if ($podcastVisibleEpisodeAudioUrl !== '') {
    $podcastVisibleAudioProbe = fetchUrl(str_starts_with($podcastVisibleEpisodeAudioUrl, 'http') ? $podcastVisibleEpisodeAudioUrl : $baseUrl . $podcastVisibleEpisodeAudioUrl, '', 0);
    if (!str_contains($podcastVisibleAudioProbe['status'], '200')) {
        $podcastVisibilityIssues[] = 'podcast audio endpoint does not serve public episode audio';
    }
}
if ($podcastHiddenShowUrl !== '') {
    $podcastHiddenShowProbe = fetchUrl($podcastHiddenShowUrl, '', 0);
    if (!preg_match('/\s404\s/', $podcastHiddenShowProbe['status'])) {
        $podcastVisibilityIssues[] = 'hidden podcast show remains publicly reachable';
    }
}
if ($podcastHiddenFeedUrl !== '') {
    $podcastHiddenFeedProbe = fetchUrl($podcastHiddenFeedUrl, '', 0);
    if (!preg_match('/\s404\s/', $podcastHiddenFeedProbe['status'])) {
        $podcastVisibilityIssues[] = 'hidden podcast feed remains publicly reachable';
    }
}
if ($podcastHiddenShowCoverUrl !== '') {
    $podcastHiddenCoverProbe = fetchUrl($podcastHiddenShowCoverUrl, '', 0);
    if (!preg_match('/\s404\s/', $podcastHiddenCoverProbe['status'])) {
        $podcastVisibilityIssues[] = 'hidden podcast cover remains publicly reachable';
    }
}
if ($podcastHiddenEpisodeImageUrl !== '') {
    $podcastHiddenImageProbe = fetchUrl($podcastHiddenEpisodeImageUrl, '', 0);
    if (!preg_match('/\s404\s/', $podcastHiddenImageProbe['status'])) {
        $podcastVisibilityIssues[] = 'hidden podcast episode image remains publicly reachable';
    }
}
if ($podcastHiddenEpisodeAudioUrl !== '') {
    $podcastHiddenAudioProbe = fetchUrl($podcastHiddenEpisodeAudioUrl, '', 0);
    if (!preg_match('/\s404\s/', $podcastHiddenAudioProbe['status'])) {
        $podcastVisibilityIssues[] = 'hidden podcast episode audio remains publicly reachable';
    }
}
$podcastDirectUploadProbe = fetchUrl($baseUrl . '/uploads/podcasts/' . rawurlencode((string)($runtimeAuditPodcastAudioFile ?? 'runtime-audit-podcast-audio.mp3')), '', 0);
if (!preg_match('/\s40[34]\s/', $podcastDirectUploadProbe['status'])) {
    $podcastVisibilityIssues[] = 'direct uploads/podcasts path is still publicly reachable';
}
$podcastSearchProbe = fetchUrl($baseUrl . '/search.php?q=' . urlencode($podcastHiddenShowTitle), '', 0);
if (!str_contains($podcastSearchProbe['status'], '200')) {
    $podcastVisibilityIssues[] = 'search page did not load for podcast visibility audit';
} elseif ($podcastHiddenShowPath !== '' && str_contains($podcastSearchProbe['body'], $podcastHiddenShowPath)) {
    $podcastVisibilityIssues[] = 'search still exposes hidden podcast show';
}
if ($podcastVisibilityIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($podcastVisibilityIssues as $podcastVisibilityIssue) {
        echo '- ' . $podcastVisibilityIssue . "\n";
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

echo "=== downloads_private_file_guard ===\n";
if (($runtimeAuditHiddenDownloadId ?? 0) <= 0) {
    echo "OK\n";
} else {
    $privateDownloadProbe = fetchUrl($baseUrl . '/downloads/file.php?id=' . (int)$runtimeAuditHiddenDownloadId, '', 0);
    if (!preg_match('/\s404\s/', $privateDownloadProbe['status'])) {
        echo "- private download file is publicly accessible ({$privateDownloadProbe['status']})\n";
        $failures++;
    } else {
        echo "OK\n";
    }
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

echo "=== board_file ===\n";
if ($boardAttachmentId === false) {
    echo "OK\n";
} else {
    $boardProbe = fetchUrl($baseUrl . '/board/file.php?id=' . (int)$boardAttachmentId, '', 0);
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

echo "=== board_future_visibility ===\n";
if ($boardFutureUrl === '') {
    echo "OK\n";
} else {
    $futureBoardProbe = fetchUrl($boardFutureUrl, '', 0);
    if (!preg_match('/\s404\s/', $futureBoardProbe['status'])) {
        echo "- future-dated board item is publicly visible ({$futureBoardProbe['status']})\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== board_private_attachment_access ===\n";
if ($boardPrivateAttachmentId === false || $boardPrivateAttachmentPath === '') {
    echo "OK\n";
} else {
    $privateAttachmentIssues = [];
    $privateAnonProbe = fetchUrl($baseUrl . $boardPrivateAttachmentPath, '', 0);
    if (!preg_match('/\s404\s/', $privateAnonProbe['status'])) {
        $privateAttachmentIssues[] = 'anonymous visitor can access private board attachment';
    }

    $privateAuthorProbe = fetchUrl($baseUrl . $boardPrivateAttachmentPath, 'PHPSESSID=' . $roleAuditSessions['author'], 0);
    if (!preg_match('/\s404\s/', $privateAuthorProbe['status'])) {
        $privateAttachmentIssues[] = 'author can access private board attachment';
    }

    $privateModeratorProbe = fetchUrl($baseUrl . $boardPrivateAttachmentPath, 'PHPSESSID=' . $roleAuditSessions['moderator'], 0);
    if (!preg_match('/\s404\s/', $privateModeratorProbe['status'])) {
        $privateAttachmentIssues[] = 'moderator can access private board attachment';
    }

    $privateBookingManagerProbe = fetchUrl($baseUrl . $boardPrivateAttachmentPath, 'PHPSESSID=' . $roleAuditSessions['booking_manager'], 0);
    if (!preg_match('/\s404\s/', $privateBookingManagerProbe['status'])) {
        $privateAttachmentIssues[] = 'booking manager can access private board attachment';
    }

    $privateAdminProbe = fetchUrl($baseUrl . $boardPrivateAttachmentPath, 'PHPSESSID=' . $auditSessionId, 0);
    if (!str_contains($privateAdminProbe['status'], '200')) {
        $privateAttachmentIssues[] = 'admin cannot access private board attachment';
    }

    if ($privateAttachmentIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($privateAttachmentIssues as $privateAttachmentIssue) {
            echo '- ' . $privateAttachmentIssue . "\n";
        }
    }
}

echo "=== content_shortcodes ===\n";
if ($articleId === false) {
    echo "OK\n";
} else {
    $contentShortcodeIssues = [];
    $runtimeAuditPdfUrl = '/uploads/runtime-audit.pdf';
    $originalArticleContent = '';
    try {
        $originalContentStmt = $pdo->prepare("SELECT content FROM cms_articles WHERE id = ?");
        $originalContentStmt->execute([(int)$articleId]);
        $originalArticleContent = (string)($originalContentStmt->fetchColumn() ?? '');

        $shortcodeContent = <<<HTML
<p>Runtime audit shortcode test.</p>
[audio src="/downloads/file.php?id=123" mime="audio/mpeg"][/audio]
[video src="/downloads/file.php?id=321" mime="video/mp4"][/video]
[video]https://www.youtube.com/watch?v=yIdGMYUmfgg&t=26s[/video]
[pdf src="{$runtimeAuditPdfUrl}" title="Runtime audit PDF" mime="application/pdf"][/pdf]
[code]echo "Ahoj z code shortcodu";
$soubor = "/uploads/runtime-audit.pdf";[/code]
HTML;

        if (!empty($galleryAlbumRow['slug'])) {
            $shortcodeContent .= "\n[gallery]" . (string)$galleryAlbumRow['slug'] . "[/gallery]\n";
        }
        if ($runtimeAuditFormSlug !== '') {
            $shortcodeContent .= "\n[form]" . $runtimeAuditFormSlug . "[/form]\n";
        }
        if (!empty($pollRow['slug'])) {
            $shortcodeContent .= "\n[poll]" . (string)$pollRow['slug'] . "[/poll]\n";
        }
        if (!empty($downloadRow['slug'])) {
            $shortcodeContent .= "\n[download]" . (string)$downloadRow['slug'] . "[/download]\n";
        }
        if (!empty($podcastShowRow['slug'])) {
            $shortcodeContent .= "\n[podcast]" . (string)$podcastShowRow['slug'] . "[/podcast]\n";
        }
        if (!empty($podcastShowRow['slug']) && !empty($podcastEpisodeRow['slug'])) {
            $shortcodeContent .= "\n[podcast_episode]" . (string)$podcastShowRow['slug'] . '/' . (string)$podcastEpisodeRow['slug'] . "[/podcast_episode]\n";
        }
        if (!empty($placeRow['slug'])) {
            $shortcodeContent .= "\n[place]" . (string)$placeRow['slug'] . "[/place]\n";
        }
        if (!empty($eventRow['slug'])) {
            $shortcodeContent .= "\n[event]" . (string)$eventRow['slug'] . "[/event]\n";
        }
        if (!empty($boardRow['slug'])) {
            $shortcodeContent .= "\n[board]" . (string)$boardRow['slug'] . "[/board]\n";
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
            if (!str_contains($shortcodeProbe['body'], 'https://www.youtube-nocookie.com/embed/yIdGMYUmfgg?start=26')) {
                $contentShortcodeIssues[] = 'youtube video shortcode was not rendered as privacy-friendly iframe';
            }
            if (!str_contains($shortcodeProbe['body'], 'aria-labelledby="content-video-heading-')) {
                $contentShortcodeIssues[] = 'youtube video shortcode is missing heading-backed section label';
            }
            if (!str_contains($shortcodeProbe['body'], 'Otevřít video samostatně')) {
                $contentShortcodeIssues[] = 'youtube video shortcode is missing standalone fallback link';
            }
            if (!str_contains($shortcodeProbe['body'], 'content-embed-card--pdf')) {
                $contentShortcodeIssues[] = 'pdf shortcode was not rendered as embedded pdf card';
            }
            if (!str_contains($shortcodeProbe['body'], 'aria-labelledby="content-pdf-heading-')) {
                $contentShortcodeIssues[] = 'pdf shortcode is missing heading-backed section label';
            }
            if (!str_contains($shortcodeProbe['body'], 'title="PDF dokument: Runtime audit PDF"')) {
                $contentShortcodeIssues[] = 'pdf shortcode is missing accessible iframe title';
            }
            if (!str_contains($shortcodeProbe['body'], 'Otevřít PDF samostatně')) {
                $contentShortcodeIssues[] = 'pdf shortcode is missing fallback open action';
            }
            if (!str_contains($shortcodeProbe['body'], 'content-code-block')) {
                $contentShortcodeIssues[] = 'code shortcode was not rendered as copyable code block';
            }
            if (!str_contains($shortcodeProbe['body'], 'aria-labelledby="content-code-heading-')) {
                $contentShortcodeIssues[] = 'code shortcode is missing heading-backed section label';
            }
            if (!str_contains($shortcodeProbe['body'], 'class="button-secondary content-code-block__copy js-copy-content"')) {
                $contentShortcodeIssues[] = 'code shortcode is missing copy button';
            }
            if (!str_contains($shortcodeProbe['body'], 'Kopírovat do schránky')) {
                $contentShortcodeIssues[] = 'code shortcode is missing copy button label';
            }
            if (!str_contains($shortcodeProbe['body'], 'echo &quot;Ahoj z code shortcodu&quot;;')) {
                $contentShortcodeIssues[] = 'code shortcode did not preserve escaped code content';
            }
            if (!empty($galleryAlbumRow['slug']) && !str_contains($shortcodeProbe['body'], 'content-gallery-embed')) {
                $contentShortcodeIssues[] = 'gallery shortcode was not rendered as embedded gallery';
            }
            if (!empty($galleryAlbumRow['slug']) && !str_contains($shortcodeProbe['body'], 'aria-labelledby="content-gallery-heading-')) {
                $contentShortcodeIssues[] = 'gallery shortcode is missing heading-backed section label';
            }
            if ($runtimeAuditFormSlug !== '' && !str_contains($shortcodeProbe['body'], 'content-embed-frame--form')) {
                $contentShortcodeIssues[] = 'form shortcode was not rendered as interactive embed';
            }
            if ($runtimeAuditFormSlug !== '' && !str_contains($shortcodeProbe['body'], 'aria-labelledby="content-interactive-heading-')) {
                $contentShortcodeIssues[] = 'interactive shortcode is missing heading-backed section label';
            }
            if ($runtimeAuditFormPath !== '' && !str_contains($shortcodeProbe['body'], $runtimeAuditFormPath . '?embed=1')) {
                $contentShortcodeIssues[] = 'form shortcode is missing embedded form iframe target';
            }
            if (!empty($pollRow['slug']) && !str_contains($shortcodeProbe['body'], 'content-embed-frame--poll')) {
                $contentShortcodeIssues[] = 'poll shortcode was not rendered as interactive embed';
            }
            if (!empty($pollRow['slug']) && !str_contains($shortcodeProbe['body'], (string)$pollRow['question'])) {
                $contentShortcodeIssues[] = 'poll shortcode is missing poll question';
            }
            if (!empty($downloadRow['slug']) && !str_contains($shortcodeProbe['body'], 'content-embed-card--download')) {
                $contentShortcodeIssues[] = 'download shortcode was not rendered as teaser card';
            }
            if (!empty($downloadRow['slug']) && !str_contains($shortcodeProbe['body'], 'aria-labelledby="content-card-heading-')) {
                $contentShortcodeIssues[] = 'content card shortcode is missing heading-backed section label';
            }
            if (!empty($downloadRow['slug']) && !str_contains($shortcodeProbe['body'], (string)$downloadRow['title'])) {
                $contentShortcodeIssues[] = 'download shortcode is missing download title';
            }
            if (!empty($podcastShowRow['slug']) && !str_contains($shortcodeProbe['body'], 'content-embed-card--podcast')) {
                $contentShortcodeIssues[] = 'podcast shortcode was not rendered as teaser card';
            }
            if (!empty($podcastShowRow['slug']) && !str_contains($shortcodeProbe['body'], (string)$podcastShowRow['title'])) {
                $contentShortcodeIssues[] = 'podcast shortcode is missing show title';
            }
            if (!empty($podcastShowRow['slug']) && !empty($podcastEpisodeRow['slug']) && !str_contains($shortcodeProbe['body'], 'content-embed-card--podcast-episode')) {
                $contentShortcodeIssues[] = 'podcast episode shortcode was not rendered as teaser card';
            }
            if (!empty($podcastShowRow['slug']) && !empty($podcastEpisodeRow['slug']) && !str_contains($shortcodeProbe['body'], (string)$podcastEpisodeRow['title'])) {
                $contentShortcodeIssues[] = 'podcast episode shortcode is missing episode title';
            }
            if (!empty($placeRow['slug']) && !str_contains($shortcodeProbe['body'], 'content-embed-card--place')) {
                $contentShortcodeIssues[] = 'place shortcode was not rendered as teaser card';
            }
            if (!empty($placeRow['slug']) && !str_contains($shortcodeProbe['body'], (string)$placeRow['name'])) {
                $contentShortcodeIssues[] = 'place shortcode is missing place title';
            }
            if (!empty($eventRow['slug']) && !str_contains($shortcodeProbe['body'], 'content-embed-card--event')) {
                $contentShortcodeIssues[] = 'event shortcode was not rendered as teaser card';
            }
            if (!empty($eventRow['slug']) && !str_contains($shortcodeProbe['body'], (string)$eventRow['title'])) {
                $contentShortcodeIssues[] = 'event shortcode is missing event title';
            }
            if (!empty($boardRow['slug']) && !str_contains($shortcodeProbe['body'], 'content-embed-card--board')) {
                $contentShortcodeIssues[] = 'board shortcode was not rendered as teaser card';
            }
            if (!empty($boardRow['slug']) && !str_contains($shortcodeProbe['body'], (string)$boardRow['title'])) {
                $contentShortcodeIssues[] = 'board shortcode is missing board title';
            }
            if (preg_match('/<section[^>]+(?:content-code-block|content-embed-card|content-gallery-embed)[^>]+aria-label=/i', $shortcodeProbe['body']) === 1) {
                $contentShortcodeIssues[] = 'content shortcodes still render aria-label-only sections';
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

echo "=== content_snippet_guardrails ===\n";
$contentSnippetIssues = [];
$contentLibrarySource = (string)file_get_contents(dirname(__DIR__) . '/lib/content.php');
$contentPickerSource = (string)file_get_contents(dirname(__DIR__) . '/admin/content_reference_picker.php');
$contentPickerCssPath = dirname(__DIR__) . '/admin/assets/content-reference-picker.css';
$contentPickerCssSource = is_file($contentPickerCssPath) ? (string)file_get_contents($contentPickerCssPath) : '';
$contentSearchSource = (string)file_get_contents(dirname(__DIR__) . '/admin/content_reference_search.php');
$contentSitemapSource = (string)file_get_contents(dirname(__DIR__) . '/sitemap.php');
$contentHttpIntegrationSource = is_file(dirname(__DIR__) . '/build/http_integration.php')
    ? (string)file_get_contents(dirname(__DIR__) . '/build/http_integration.php')
    : '';

foreach ([
    '[form',
    '[poll',
    '[download',
    '[pdf',
    '[code',
    '[podcast(?:',
    '[podcast_episode',
    '[place',
    '[event',
    '[board',
    'contentYouTubeVideoId',
    'youtube-nocookie.com/embed/',
] as $shortcodeFragment) {
    if (!str_contains($contentLibrarySource, $shortcodeFragment)) {
        $contentSnippetIssues[] = 'content parser is missing shortcode registration: ' . $shortcodeFragment;
    }
}

foreach ([
    "\$types['forms'] = 'Formuláře';",
    '[form]slug-formulare[/form]',
    '[podcast_episode]slug-poradu/slug-epizody[/podcast_episode]',
    'let isSearching = false;',
    'adminContentReferencePickerStylesheetTag()',
    "searchButton.setAttribute('aria-disabled', pending ? 'true' : 'false');",
    'if (!dialog.hidden && !dialog.contains(document.activeElement)) {',
    'searchButton.focus();',
    'content-reference-picker-dialog__description',
    'content-reference-picker-fieldset',
    'content-reference-picker-status',
    "document.body.classList.add('admin-modal-open')",
    "document.body.classList.remove('admin-modal-open')",
] as $pickerFragment) {
    if (!str_contains($contentPickerSource, $pickerFragment)) {
        $contentSnippetIssues[] = 'content picker is missing snippet helper fragment: ' . $pickerFragment;
    }
}
foreach ([
    'searchButton.disabled = true;',
    'searchButton.disabled = false;',
    'style="display:none"',
    'style="margin-top:.35rem"',
    'style="margin:0;border:1px solid #ccc;padding:.5rem 1rem"',
    'style="margin:.85rem 0 0;color:#555;font-size:.92rem;line-height:1.45"',
    '.style.display',
    'document.body.style.overflow',
    '<style nonce=',
] as $pickerForbiddenFragment) {
    if (str_contains($contentPickerSource, $pickerForbiddenFragment)) {
        $contentSnippetIssues[] = 'content picker still uses forbidden focus/style fragment: ' . $pickerForbiddenFragment;
    }
}
foreach ([
    'function adminContentReferencePickerStylesheetTag(): string',
    '/admin/assets/content-reference-picker.css',
] as $pickerStylesheetHelperFragment) {
    if (!str_contains($uiSource, $pickerStylesheetHelperFragment)) {
        $contentSnippetIssues[] = 'shared UI helper is missing content picker stylesheet fragment: ' . $pickerStylesheetHelperFragment;
    }
}
if (str_contains($uiSource, 'function adminContentReferencePickerStyleTag(): string')) {
    $contentSnippetIssues[] = 'shared UI helper still exposes inline content picker style tag';
}
foreach ([
    '.content-reference-picker-overlay',
    '.content-reference-picker-dialog',
    '.content-reference-picker-result--with-thumb',
    '@media (max-width: 720px)',
] as $pickerCssFragment) {
    if (!str_contains($contentPickerCssSource, $pickerCssFragment)) {
        $contentSnippetIssues[] = 'content picker stylesheet is missing fragment: ' . $pickerCssFragment;
    }
}

foreach ([
    'Vložit formulář',
    'Vložit anketu',
    'Vložit blok ke stažení',
    'Vložit PDF náhled',
    'Vložit podcast',
    'Vložit epizodu podcastu',
    'Vložit místo',
    'Vložit událost',
    'Vložit oznámení',
    "'form' => 'Formulář'",
    "'forms',",
] as $searchFragment) {
    if (!str_contains($contentSearchSource, $searchFragment)) {
        $contentSnippetIssues[] = 'content reference search is missing snippet action fragment: ' . $searchFragment;
    }
}

if (!str_contains($contentSearchSource, 'Vložit fotogalerii')) {
    $contentSnippetIssues[] = 'content reference search is missing gallery album insert action fragment';
}
if (!str_contains($contentSearchSource, 'function contentReferencePdfShortcode(string $url, string $title = \'\', string $mimeType = \'\', int $mediaId = 0): string')) {
    $contentSnippetIssues[] = 'content reference search is missing pdf shortcode helper';
}
if (!str_contains($contentLibrarySource, 'function renderContentPdfShortcode(string $url, string $title = \'\', string $preferredMimeType = \'\', int $mediaId = 0): ?string')) {
    $contentSnippetIssues[] = 'content renderer is missing pdf shortcode helper';
}
if (!str_contains($contentLibrarySource, 'function contentResolvePdfMedia(int $mediaId, string $url): ?array')) {
    $contentSnippetIssues[] = 'content renderer is missing pdf media resolver helper';
}
if (!str_contains($contentLibrarySource, 'function renderContentCodeShortcode(string $body): ?string')) {
    $contentSnippetIssues[] = 'content renderer is missing code shortcode helper';
}
if (!str_contains($contentLibrarySource, 'content-embed-card content-embed-card--pdf')) {
    $contentSnippetIssues[] = 'content renderer is missing pdf embed card markup';
}
if (!str_contains($contentLibrarySource, 'content-code-block__copy js-copy-content')) {
    $contentSnippetIssues[] = 'content renderer is missing copyable code block markup';
}
if (!str_contains($contentLibrarySource, 'function contentSectionHeadingId(string $prefix, string $label): string')
    || !str_contains($contentLibrarySource, 'function contentSectionHiddenHeading(string $headingId, string $label): string')) {
    $contentSnippetIssues[] = 'content renderer is missing shared heading-backed section helpers';
}
foreach ([
    'content-code-heading',
    'content-video-heading',
    'content-pdf-heading',
    'content-gallery-heading',
    'content-card-heading',
    'content-interactive-heading',
] as $contentHeadingPrefix) {
    if (!str_contains($contentLibrarySource, $contentHeadingPrefix)) {
        $contentSnippetIssues[] = 'content renderer is missing heading prefix: ' . $contentHeadingPrefix;
    }
}
if (str_contains($contentLibrarySource, 'aria-label=')) {
    $contentSnippetIssues[] = 'content renderer still contains aria-label-only section markup';
}
if (!str_contains($contentLibrarySource, '/\\[code(?:\\s+([^\\]]*))?\\](.*?)\\[\\/code\\]/is')) {
    $contentSnippetIssues[] = 'content renderer is missing code shortcode parser';
}
if (!str_contains($contentLibrarySource, "\$attributes['media_id'] ?? \$attributes['media'] ?? ''")) {
    $contentSnippetIssues[] = 'content renderer is missing media_id-aware pdf shortcode parsing';
}
if (!str_contains($contentLibrarySource, 'mediaPreviewUrl($media)')) {
    $contentSnippetIssues[] = 'content renderer is missing media preview URL integration for PDF';
}
if (!str_contains($baseLayoutSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/layouts/base.php'), '.js-copy-content')) {
    $contentSnippetIssues[] = 'default layout is missing code copy button handler';
}
if (!str_contains($baseLayoutSource, 'Obsah byl zkopírován do schránky.')) {
    $contentSnippetIssues[] = 'default layout is missing copyable code live region feedback';
}
if (str_contains($contentPickerSource, '<figcaption>')) {
    $contentSnippetIssues[] = 'content reference picker still injects figcaption into inserted image HTML';
}
if (str_contains($contentPickerSource, "const altText = typeof item.media_alt === 'string' && item.media_alt.trim() !== ''\n                ? item.media_alt.trim()\n                : item.title;")) {
    $contentSnippetIssues[] = 'content reference picker still falls back to image title for alt text';
}
if (!str_contains($contentSearchSource, "SELECT id, name AS title, slug, description, COALESCE(updated_at, created_at) AS created_at, 'gallery_album' AS type")) {
    $contentSnippetIssues[] = 'content reference search gallery album query is not selecting real description field';
}
if (str_contains($contentSearchSource, "SELECT id, name AS title, slug, excerpt, COALESCE(updated_at, created_at) AS created_at, 'gallery_album' AS type")) {
    $contentSnippetIssues[] = 'content reference search gallery album query still references non-existent excerpt column';
}
if (str_contains($contentSearchSource, 'p.caption') || str_contains($contentSearchSource, 'p.alt_text')) {
    $contentSnippetIssues[] = 'content reference search gallery photo query still references non-existent caption/alt_text columns';
}
if (!str_contains($contentSearchSource, 'p.title LIKE ? OR p.slug LIKE ? OR a.name LIKE ?')) {
    $contentSnippetIssues[] = 'content reference search gallery photo query is not using real gallery photo fields';
}
if (!str_contains($contentSitemapSource, "galleryPhotoPublicVisibilitySql('p', 'a') . \"\n             ORDER BY p.created_at DESC, p.id DESC")) {
    $contentSnippetIssues[] = 'sitemap gallery photo query must qualify created_at/id through the photo alias';
}
if (!str_contains($contentHttpIntegrationSource, "httpIntegrationPrintResult('content_reference_gallery_http'")) {
    $contentSnippetIssues[] = 'build/http_integration.php is missing gallery content picker coverage';
}
if (!str_contains($contentHttpIntegrationSource, "httpIntegrationPrintResult('content_reference_pdf_http'")) {
    $contentSnippetIssues[] = 'build/http_integration.php is missing pdf content picker coverage';
}
if (!str_contains($contentHttpIntegrationSource, '/media/preview.php?id=')) {
    $contentSnippetIssues[] = 'build/http_integration.php is missing media preview endpoint coverage for PDF';
}

if ($contentSnippetIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($contentSnippetIssues as $contentSnippetIssue) {
        echo '- ' . $contentSnippetIssue . "\n";
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

echo "=== chat_runtime ===\n";
if (!isModuleEnabled('chat')) {
    echo "SKIP (modul chat není aktivní)\n";
} else {
    $chatIssues = [];
    $publicChatUrl = $baseUrl . '/chat/index.php';

    $pendingChatCookie = 'PHPSESSID=runtimeauditchatpending';
    $pendingChatForm = fetchUrl($publicChatUrl, $pendingChatCookie, 0);
    $pendingChatCsrf = extractHiddenInputValue($pendingChatForm['body'], 'csrf_token');
    $pendingChatCaptcha = extractCaptchaAnswer($pendingChatForm['body']);
    $pendingChatMessage = 'Runtime audit moderovaná chat zpráva ' . bin2hex(random_bytes(4));

    if ($pendingChatCsrf === '' || $pendingChatCaptcha === null) {
        $chatIssues[] = 'could not extract chat form token or captcha';
    } else {
        $pendingChatPost = postUrl(
            $publicChatUrl,
            [
                'csrf_token' => $pendingChatCsrf,
                'name' => 'Runtime Audit Chat',
                'email' => '',
                'message' => $pendingChatMessage,
                'captcha' => (string)$pendingChatCaptcha,
                'hp_website' => '',
            ],
            $pendingChatCookie,
            0
        );
        if (!str_contains($pendingChatPost['status'], '302')) {
            $chatIssues[] = 'chat submit did not redirect after submit';
        } elseif (!responseHasLocationHeader($pendingChatPost['headers'], '/chat/index.php?ok=pending', $baseUrl)) {
            $chatIssues[] = 'chat submit did not redirect to pending state';
        }

        $pendingChatStmt = $pdo->prepare(
            "SELECT id, status, public_visibility
             FROM cms_chat
             WHERE message = ?
             ORDER BY id DESC
             LIMIT 1"
        );
        $pendingChatStmt->execute([$pendingChatMessage]);
        $pendingChatRow = $pendingChatStmt->fetch();
        if (!$pendingChatRow) {
            $chatIssues[] = 'submitted chat message was not stored';
        } else {
            $pendingChatId = (int)$pendingChatRow['id'];
            $cleanup['chat_ids'][] = $pendingChatId;
            if (($pendingChatRow['status'] ?? '') !== 'new') {
                $chatIssues[] = 'submitted chat message is not marked as new';
            }
            if (($pendingChatRow['public_visibility'] ?? '') !== 'pending') {
                $chatIssues[] = 'submitted chat message is not waiting for approval';
            }

            $publicChatAfterSubmit = fetchUrl($publicChatUrl, '', 0);
            if (str_contains($publicChatAfterSubmit['body'], $pendingChatMessage)) {
                $chatIssues[] = 'pending chat message leaked into public chat';
            }

            $noEmailDetail = fetchUrl(
                $baseUrl . '/admin/chat_message.php?id=' . urlencode((string)$pendingChatId),
                'PHPSESSID=' . $auditSessionId,
                0
            );
            if (!str_contains($noEmailDetail['status'], '200')) {
                $chatIssues[] = 'admin detail for no-email chat message is not accessible';
            } else {
                if (!str_contains($noEmailDetail['body'], 'Tato zpráva neobsahuje e-mailovou adresu')) {
                    $chatIssues[] = 'chat detail without e-mail is missing explanatory reply notice';
                }
                if (str_contains($noEmailDetail['body'], '/admin/chat_reply.php')) {
                    $chatIssues[] = 'chat detail without e-mail still renders reply form';
                }
            }
        }
    }

    $urlChatCookie = 'PHPSESSID=runtimeauditchaturl';
    $urlChatForm = fetchUrl($publicChatUrl, $urlChatCookie, 0);
    $urlChatCsrf = extractHiddenInputValue($urlChatForm['body'], 'csrf_token');
    $urlChatCaptcha = extractCaptchaAnswer($urlChatForm['body']);
    $urlSpamMessage = 'Tato zpráva obsahuje odkaz https://example.test/runtime-audit-chat-spam a má být odmítnuta.';
    if ($urlChatCsrf === '' || $urlChatCaptcha === null) {
        $chatIssues[] = 'could not extract chat form token or captcha for URL rejection test';
    } else {
        $urlChatPost = postUrl(
            $publicChatUrl,
            [
                'csrf_token' => $urlChatCsrf,
                'name' => 'Runtime Audit URL',
                'email' => 'runtimeaudit-chat-' . bin2hex(random_bytes(4)) . '@example.test',
                'message' => $urlSpamMessage,
                'captcha' => (string)$urlChatCaptcha,
                'hp_website' => '',
            ],
            $urlChatCookie,
            0
        );
        if (!str_contains($urlChatPost['status'], '200')) {
            $chatIssues[] = 'chat URL rejection did not stay on the form page';
        }
        if (!str_contains($urlChatPost['body'], 'Do textu zprávy nevkládejte webové adresy ani odkazy.')) {
            $chatIssues[] = 'chat URL rejection is missing validation message';
        }
        $urlSpamCountStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_chat WHERE message = ?");
        $urlSpamCountStmt->execute([$urlSpamMessage]);
        if ((int)$urlSpamCountStmt->fetchColumn() !== 0) {
            $chatIssues[] = 'chat message containing a URL was still stored';
        }
    }

    $chatFilteredProbe = fetchUrl(
        $publicChatUrl . '?q=' . urlencode('Schválená chat zpráva') . '&razeni=oldest',
        '',
        0
    );
    if (!str_contains($chatFilteredProbe['status'], '200')) {
        $chatIssues[] = 'filtered public chat is not accessible';
    } else {
        if (!str_contains($chatFilteredProbe['body'], $chatApprovedMessageText)) {
            $chatIssues[] = 'filtered public chat is missing the approved message';
        }
        if (str_contains($chatFilteredProbe['body'], $chatHiddenMessageText)) {
            $chatIssues[] = 'filtered public chat still exposes hidden messages';
        }
        if (!str_contains($chatFilteredProbe['body'], 'value="oldest" selected')) {
            $chatIssues[] = 'public chat sort switch does not preserve oldest ordering';
        }
    }

    if ($chatIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($chatIssues as $issue) {
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
if ($runtimeAuditExistingNewsIdForAuthorRestore !== false && $runtimeAuditOriginalNewsAuthorId !== null) {
    $pdo->prepare("UPDATE cms_news SET author_id = ? WHERE id = ?")->execute([
        (int)$runtimeAuditOriginalNewsAuthorId,
        (int)$runtimeAuditExistingNewsIdForAuthorRestore,
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
if (!empty($cleanup['news_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['news_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_news WHERE id IN ({$placeholders})")->execute($cleanup['news_ids']);
}
if (!empty($cleanup['contact_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['contact_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_contact WHERE id IN ({$placeholders})")->execute($cleanup['contact_ids']);
}
if (!empty($cleanup['chat_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['chat_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_chat_history WHERE chat_id IN ({$placeholders})")->execute($cleanup['chat_ids']);
    $pdo->prepare("DELETE FROM cms_chat WHERE id IN ({$placeholders})")->execute($cleanup['chat_ids']);
}
if (!empty($cleanup['board_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['board_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_board WHERE id IN ({$placeholders})")->execute($cleanup['board_ids']);
}
foreach ($cleanup['board_files'] as $boardFile) {
    @unlink(__DIR__ . '/../uploads/board/' . basename((string)$boardFile));
}
if (!empty($cleanup['download_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['download_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_downloads WHERE id IN ({$placeholders})")->execute($cleanup['download_ids']);
}
foreach ($cleanup['download_files'] as $downloadFile) {
    deleteDownloadStoredFile((string)$downloadFile);
}
if (!empty($cleanup['event_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['event_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_events WHERE id IN ({$placeholders})")->execute($cleanup['event_ids']);
}
if (!empty($cleanup['redirect_paths'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['redirect_paths']), '?'));
    $pdo->prepare("DELETE FROM cms_redirects WHERE old_path IN ({$placeholders})")->execute($cleanup['redirect_paths']);
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
foreach ($cleanup['place_files'] as $placeFile) {
    @unlink(__DIR__ . '/../uploads/places/' . basename((string)$placeFile));
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
if (!empty($cleanup['audit_log_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['audit_log_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_log WHERE id IN ({$placeholders})")->execute($cleanup['audit_log_ids']);
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
foreach ($cleanup['podcast_files'] as $podcastFile) {
    @unlink(__DIR__ . '/../uploads/podcasts/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$podcastFile));
}
if (!empty($cleanup['author_user_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['author_user_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_users WHERE id IN ({$placeholders})")->execute($cleanup['author_user_ids']);
}
if (!empty($cleanup['staff_user_ids'])) {
    $placeholders = implode(',', array_fill(0, count($cleanup['staff_user_ids']), '?'));
    $pdo->prepare("DELETE FROM cms_users WHERE id IN ({$placeholders})")->execute($cleanup['staff_user_ids']);
}

echo "=== cron_runtime ===\n";
try {
    $cronIssues = [];
    $cronRateLimitId = 'runtime_audit_' . bin2hex(random_bytes(8));
    $originalChatRetentionDays = getSetting('chat_retention_days', '0');
    $cronChatId = 0;
    $cronBackupFile = cronBackupDirectory() . 'kora_backup_' . date('Y-m-d') . '.sql';
    $cronCspLogDirectory = cronLogDirectory();
    $oldCspReportFile = $cronCspLogDirectory . 'csp_reports-runtime-audit-old.jsonl';
    $recentCspReportFile = $cronCspLogDirectory . 'csp_reports-runtime-audit-recent.jsonl';
    $legacyBackupFile = __DIR__ . '/../uploads/backups/kora_backup_' . date('Y-m-d') . '.sql';
    $storageRoot = str_replace('\\', '/', rtrim(koraStorageDirectory(), '\\/'));
    $webRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: dirname(__DIR__));

    if ($storageRoot === $webRoot || str_starts_with($storageRoot . '/', rtrim($webRoot, '/') . '/')) {
        $cronIssues[] = 'private storage directory still points inside the public webroot';
    }

    $pdo->prepare(
        "INSERT INTO cms_rate_limit (id, attempts, window_start)
         VALUES (?, 1, DATE_SUB(NOW(), INTERVAL 2 HOUR))
         ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), window_start = VALUES(window_start)"
    )->execute([$cronRateLimitId]);

    saveSetting('chat_retention_days', '1');
    clearSettingsCache();
    $GLOBALS['_CMS_SETTINGS']['chat_retention_days'] = '1';
    $pdo->prepare(
        "INSERT INTO cms_chat (name, email, web, message, status, public_visibility, created_at, updated_at)
         VALUES (?, '', '', ?, 'handled', 'hidden', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY))"
    )->execute([
        'Runtime Audit Cron',
        'Runtime audit stará chat zpráva pro cron cleanup.',
    ]);
    $cronChatId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO cms_chat_history (chat_id, actor_user_id, event_type, message, created_at)
         VALUES (?, NULL, 'workflow', ?, DATE_SUB(NOW(), INTERVAL 10 DAY))"
    )->execute([
        $cronChatId,
        'Zpráva byla označena jako vyřízená.',
    ]);
    if (koraEnsureDirectory($cronCspLogDirectory)) {
        file_put_contents($oldCspReportFile, "{}\n");
        file_put_contents($recentCspReportFile, "{}\n");
        touch($oldCspReportFile, time() - (31 * 86400));
        touch($recentCspReportFile, time());
    }

    $cronLog = runKoraCron($pdo);
    $cronLastRunStmt = $pdo->prepare("SELECT value FROM cms_settings WHERE `key` = 'cron_last_run_at' LIMIT 1");
    $cronLastRunStmt->execute();
    $cronLastRunAt = (string)($cronLastRunStmt->fetchColumn() ?: '');
    $rateLimitCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_rate_limit WHERE id = ?");
    $rateLimitCheckStmt->execute([$cronRateLimitId]);
    $remainingRateLimit = (int)$rateLimitCheckStmt->fetchColumn();
    $cronChatCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_chat WHERE id = ?");
    $cronChatCheckStmt->execute([$cronChatId]);
    $remainingCronChat = (int)$cronChatCheckStmt->fetchColumn();
    $cronChatHistoryCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_chat_history WHERE chat_id = ?");
    $cronChatHistoryCheckStmt->execute([$cronChatId]);
    $remainingCronChatHistory = (int)$cronChatHistoryCheckStmt->fetchColumn();

    if ($remainingRateLimit !== 0) {
        $cronIssues[] = 'cron did not clean expired rate-limit records';
        $pdo->prepare("DELETE FROM cms_rate_limit WHERE id = ?")->execute([$cronRateLimitId]);
    }
    if ($remainingCronChat !== 0 || $remainingCronChatHistory !== 0) {
        $cronIssues[] = 'cron did not clean old handled chat messages according to chat_retention_days';
    }
    $cronLastRunTimestamp = strtotime($cronLastRunAt);
    if ($cronLastRunAt === '' || $cronLastRunTimestamp === false || $cronLastRunTimestamp < time() - 300) {
        $cronIssues[] = 'cron did not persist a fresh cron_last_run_at health timestamp';
    }
    if (is_file($oldCspReportFile)) {
        $cronIssues[] = 'cron did not clean old CSP report JSONL logs';
    }
    if (!is_file($recentCspReportFile)) {
        $cronIssues[] = 'cron removed a recent CSP report JSONL log';
    }
    if (!is_file($cronBackupFile)) {
        $cronIssues[] = 'cron did not keep SQL backups in private storage';
    }
    if (is_file($legacyBackupFile)) {
        $cronIssues[] = 'cron still writes SQL backups into public uploads/backups';
    }
    foreach ($cronLog as $cronLine) {
        if (str_starts_with($cronLine, 'Chyba')) {
            $cronIssues[] = 'cron reported an error: ' . $cronLine;
        }
    }

    if ($cronIssues === []) {
        echo "OK\n";
    } else {
        $failures++;
        foreach ($cronIssues as $issue) {
            echo '- ' . $issue . "\n";
        }
    }
    saveSetting('chat_retention_days', $originalChatRetentionDays);
    clearSettingsCache();
    $GLOBALS['_CMS_SETTINGS']['chat_retention_days'] = $originalChatRetentionDays;
    if ($cronChatId > 0) {
        $pdo->prepare("DELETE FROM cms_chat_history WHERE chat_id = ?")->execute([$cronChatId]);
        $pdo->prepare("DELETE FROM cms_chat WHERE id = ?")->execute([$cronChatId]);
    }
    @unlink($oldCspReportFile);
    @unlink($recentCspReportFile);
} catch (\Throwable $e) {
    saveSetting('chat_retention_days', $originalChatRetentionDays ?? '0');
    clearSettingsCache();
    $GLOBALS['_CMS_SETTINGS']['chat_retention_days'] = $originalChatRetentionDays ?? '0';
    if (!empty($cronChatId)) {
        $pdo->prepare("DELETE FROM cms_chat_history WHERE chat_id = ?")->execute([$cronChatId]);
        $pdo->prepare("DELETE FROM cms_chat WHERE id = ?")->execute([$cronChatId]);
    }
    if (!empty($oldCspReportFile)) {
        @unlink($oldCspReportFile);
    }
    if (!empty($recentCspReportFile)) {
        @unlink($recentCspReportFile);
    }
    $failures++;
    echo '- cron runtime check failed: ' . $e->getMessage() . "\n";
}

echo "=== cron_guardrails ===\n";
$cronGuardIssues = [];
$cronGuardChecks = [
    'cronMissingColumns helper' => str_contains($cronSource, 'function cronMissingColumns(PDO $pdo, string $tableName, array $requiredColumns): array'),
    'unpublish skip message' => str_contains($cronSource, 'Přeskočeno zrušení publikace pro {$tableName}: chybí sloupce'),
    'publish skip message' => str_contains($cronSource, 'Přeskočeno plánované publikování pro {$tableName}: chybí sloupce'),
    'publish required columns guard' => str_contains($cronSource, "cronMissingColumns(\$pdo, \$tableName, ['publish_at', 'status', 'created_at'])"),
    'unpublish required columns guard' => str_contains($cronSource, "\$requiredColumns = \$config['has_published']"),
    'cron backup table allowlist' => str_contains($cronSource, 'koraBackupTableNames($pdo)') && str_contains($cronSource, 'koraSqlWriteTableDump($pdo, $tableName'),
    'manual backup table allowlist' => str_contains($adminBackupSource, 'koraBackupTableNames($pdo)') && str_contains($adminBackupSource, 'koraSqlWriteTableDump($pdo, $table'),
    'backup helper validates identifiers' => str_contains($backupHelperSource, 'function koraSqlIdentifierAllowed') && str_contains($backupHelperSource, '/\A[A-Za-z0-9_]+\z/'),
    'backup helper centralizes table dump' => str_contains($backupHelperSource, 'function koraSqlWriteTableDump') && str_contains($backupHelperSource, 'PDO::FETCH_ASSOC') && str_contains($backupHelperSource, 'function koraSqlCreateTableStatement'),
    'CSP report log retention' => str_contains($cronSource, 'function cronLogDirectory(): string')
        && str_contains($cronSource, "cronDeleteOldFiles(cronLogDirectory(), ['csp_reports-*.jsonl'], 30 * 86400)"),
];
foreach ($cronGuardChecks as $label => $ok) {
    if (!$ok) {
        $cronGuardIssues[] = $label;
    }
}
if ($cronGuardIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($cronGuardIssues as $issue) {
        echo '- ' . $issue . "\n";
    }
}

echo "=== foundation_guardrails ===\n";
$foundationIssues = [];
$formatCheckScriptText = '';
$analyseStrictScriptText = '';
$composerPayload = json_decode($composerSource, true);
if (is_array($composerPayload) && isset($composerPayload['scripts']) && is_array($composerPayload['scripts'])) {
    foreach ($composerPayload['scripts'] as $scriptName => $scriptCommand) {
        if (!is_string($scriptName)) {
            continue;
        }
        $scriptText = '';
        if (is_array($scriptCommand)) {
            $scriptText = implode(' ', array_map(
                static fn ($part): string => is_scalar($part) ? (string)$part : '',
                $scriptCommand,
            ));
        } elseif (is_scalar($scriptCommand)) {
            $scriptText = (string)$scriptCommand;
        }
        if ($scriptText === '') {
            continue;
        }
        if (str_starts_with($scriptName, 'format:check')) {
            $formatCheckScriptText .= ' ' . $scriptText;
        }
        if (str_starts_with($scriptName, 'analyse:strict')) {
            $analyseStrictScriptText .= ' ' . $scriptText;
        }
    }
}
$adminPhpPaths = glob(__DIR__ . '/../admin/*.php') ?: [];
$adminPhpFiles = array_map(
    static fn (string $path): string => 'admin/' . basename($path),
    $adminPhpPaths,
);
sort($adminPhpFiles);
preg_match_all('/admin\/[A-Za-z0-9_]+\.php/', $formatCheckScriptText, $formatCheckAdminMatches);
$formatCheckedAdminFiles = array_values(array_unique($formatCheckAdminMatches[0] ?? []));
sort($formatCheckedAdminFiles);
$missingAdminFormatFiles = array_values(array_diff($adminPhpFiles, $formatCheckedAdminFiles));
preg_match_all('/admin\/[A-Za-z0-9_]+\.php/', $analyseStrictScriptText, $phpstanStrictAdminMatches);
$phpstanStrictAdminFiles = array_values(array_unique($phpstanStrictAdminMatches[0] ?? []));
sort($phpstanStrictAdminFiles);
$missingAdminPhpstanFiles = array_values(array_diff($adminPhpFiles, $phpstanStrictAdminFiles));
$foundationChecks = [
    'composer dev tooling exists' => str_contains($composerSource, '"require-dev"')
        && str_contains($composerSource, 'phpstan/phpstan')
        && str_contains($composerSource, 'friendsofphp/php-cs-fixer')
        && str_contains($composerSource, '"ci:basic"')
        && str_contains($composerSource, '"ci:full"')
        && str_contains($composerSource, '"format:check"')
        && str_contains($composerSource, '"format:fix"'),
    'github actions basic CI exists' => str_contains($ciWorkflowSource, 'composer ci:basic')
        && str_contains($ciWorkflowSource, 'shivammathur/setup-php')
        && str_contains($ciWorkflowSource, 'actions/checkout@v6'),
    'github actions CI uses bounded read-only jobs' => str_contains($ciWorkflowSource, 'permissions:')
        && str_contains($ciWorkflowSource, 'contents: read')
        && str_contains($ciWorkflowSource, 'concurrency:')
        && str_contains($ciWorkflowSource, 'cancel-in-progress: true')
        && str_contains($ciWorkflowSource, 'timeout-minutes:')
        && str_contains($fullCiWorkflowSource, 'permissions:')
        && str_contains($fullCiWorkflowSource, 'contents: read')
        && str_contains($fullCiWorkflowSource, 'concurrency:')
        && str_contains($fullCiWorkflowSource, 'cancel-in-progress: true')
        && str_contains($fullCiWorkflowSource, 'timeout-minutes:'),
    'github actions full CI exists' => str_contains($fullCiWorkflowSource, 'workflow_dispatch:')
        && str_contains($fullCiWorkflowSource, 'schedule:')
        && str_contains($fullCiWorkflowSource, 'composer validate --strict')
        && str_contains($fullCiWorkflowSource, 'composer ci:full')
        && str_contains($fullCiWorkflowSource, 'shivammathur/setup-php')
        && str_contains($fullCiWorkflowSource, 'actions/checkout@v6'),
    'github actions full CI prepares runtime environment' => str_contains($fullCiWorkflowSource, 'services:')
        && str_contains($fullCiWorkflowSource, 'mysql:8.0')
        && str_contains($fullCiWorkflowSource, 'MYSQL_DATABASE: koracms_ci')
        && str_contains($fullCiWorkflowSource, 'KORA_TEST_BASE_URL: http://127.0.0.1:8000')
        && str_contains($fullCiWorkflowSource, 'php -S 127.0.0.1:8000 -t . build/http_server_router.php')
        && str_contains($fullCiWorkflowSource, 'install.php')
        && str_contains($fullCiWorkflowSource, 'site_profile=custom')
        && str_contains($fullCiWorkflowSource, 'did not keep site_profile=custom')
        && str_contains($fullCiWorkflowSource, 'SELECT COUNT(*) FROM cms_settings'),
    'github actions workflow audit is wired into basic CI' => str_contains($composerSource, '"test:workflow"')
        && str_contains($composerSource, 'php build/workflow_audit.php')
        && str_contains($composerSource, '"test:workflow-selftest"')
        && str_contains($composerSource, 'php build/workflow_audit_selftest.php')
        && str_contains($composerSource, '"format:check:workflow"')
        && str_contains($composerSource, 'build/workflow_audit.php build/workflow_audit_selftest.php')
        && str_contains($composerSource, '@test:workflow')
        && str_contains($composerSource, '@test:workflow-selftest')
        && str_contains($composerSource, '@format:check:workflow')
        && str_contains($workflowAuditSource, 'Basic CI')
        && str_contains($workflowAuditSource, 'Full CI')
        && str_contains($workflowAuditSource, '$argv[1]')
        && str_contains($workflowAuditSource, 'forbidWorkflowSnippets')
        && str_contains($workflowAuditSource, 'auditWorkflowActionReferences')
        && str_contains($workflowAuditSource, 'pull_request_target:')
        && str_contains($workflowAuditSource, 'contents: write')
        && str_contains($workflowAuditSource, '${{ secrets.')
        && str_contains($workflowAuditSource, 'uses an unpinned action')
        && str_contains($workflowAuditSource, 'composer ci:full')
        && str_contains($workflowAuditSelftestSource, 'assertAuditPasses')
        && str_contains($workflowAuditSelftestSource, 'assertAuditFails')
        && str_contains($workflowAuditSelftestSource, 'actions/checkout@main')
        && str_contains($workflowAuditSelftestSource, 'pull_request_target:')
        && str_contains($workflowAuditSelftestSource, '${{ secrets.CI_TOKEN }}')
        && str_contains($composerSource, 'build/phpstan_bootstrap.php build/workflow_audit.php')
        && str_contains($composerSource, 'build/http_server_router.php')
        && str_contains($phpstanConfigSource, 'build/workflow_audit_selftest.php')
        && str_contains($phpstanConfigSource, 'build/http_server_router.php')
        && str_contains($httpServerRouterSource, "routeToScript('index.php')")
        && str_contains($httpServerRouterSource, '$routeScriptPath = null')
        && str_contains($httpServerRouterSource, 'require $routeScriptPath')
        && !str_contains($httpServerRouterSource, 'require $targetPath')
        && !str_contains($httpServerRouterSource, 'Content-Security-Policy'),
    'runtime and HTTP tests support configurable base URL' => str_contains($runtimeAuditSelfSource, "getenv('KORA_TEST_BASE_URL')")
        && str_contains($httpIntegrationBuildSource, "getenv('KORA_TEST_BASE_URL')"),
    'pagination helper uses heading-backed navigation' => str_contains($paginationSource, 'class="sr-only"')
        && str_contains($paginationSource, 'aria-labelledby="')
        && str_contains($paginationSource, 'pager-heading-')
        && !str_contains($paginationSource, '<nav aria-label='),
    'composer schema validation is wired into local and GitHub basic CI' => str_contains($composerSource, '"test:composer-validate"')
        && str_contains($composerSource, 'composer validate --strict')
        && str_contains($composerSource, '@test:composer-validate')
        && str_contains($ciWorkflowSource, 'Validate Composer config')
        && str_contains($ciWorkflowSource, 'composer validate --strict'),
    'repository guardrails audit is wired into local quality gates' => str_contains($composerSource, '"test:repo-guardrails"')
        && str_contains($composerSource, 'php build/repository_guardrails_audit.php')
        && str_contains($composerSource, '@test:repo-guardrails')
        && str_contains($composerSource, 'build/repository_guardrails_audit.php')
        && str_contains($phpstanConfigSource, 'build/repository_guardrails_audit.php')
        && str_contains($repositoryGuardrailsAuditSource, 'isAllowedDbConnectionVariableFile')
        && str_contains($repositoryGuardrailsAuditSource, 'directlyLoadsDbOrConfig')
        && str_contains($repositoryGuardrailsAuditSource, 'reserved DB connection variable')
        && str_contains($repositoryGuardrailsAuditSource, 'build/unit_test_bootstrap.php')
        && str_contains($repositoryGuardrailsAuditSource, 'config.sample.php')
        && str_contains($repositoryGuardrailsAuditSource, 'db.php'),
    'config sample audit is wired into local quality gates' => str_contains($composerSource, '"test:config-sample"')
        && str_contains($composerSource, 'php build/config_sample_audit.php')
        && str_contains($composerSource, '@test:config-sample')
        && str_contains($composerSource, 'build/config_sample_audit.php')
        && str_contains($phpstanConfigSource, 'build/config_sample_audit.php')
        && str_contains($configSampleAuditSource, 'parseConfigSampleDefines')
        && str_contains($configSampleAuditSource, 'BASE_URL')
        && str_contains($configSampleAuditSource, 'KORA_STORAGE_DIR')
        && str_contains($configSampleAuditSource, 'GITHUB_ISSUES_TOKEN')
        && str_contains($configSampleAuditSource, 'CRON_TOKEN')
        && str_contains($configSampleAuditSource, 'config.sample.php is missing define('),
    'version metadata audit is wired into local quality gates' => str_contains($composerSource, '"test:version-metadata"')
        && str_contains($composerSource, 'php build/version_metadata_audit.php')
        && str_contains($composerSource, '@test:version-metadata')
        && str_contains($composerSource, 'build/version_metadata_audit.php')
        && str_contains($phpstanConfigSource, 'build/version_metadata_audit.php')
        && str_contains($versionMetadataAuditSource, 'versionAuditIsSemver')
        && str_contains($versionMetadataAuditSource, "define('KORA_VERSION'")
        && str_contains($versionMetadataAuditSource, '$packageFileOverrides["VERSION"] = $newVersion')
        && str_contains($versionMetadataAuditSource, 'Release smoke ZIP contains an unexpected VERSION value')
        && str_contains($versionMetadataAuditSource, 'Source archive contains an unexpected VERSION value'),
    'schema parity audit is wired into local quality gates' => str_contains($composerSource, '"test:schema-parity"')
        && str_contains($composerSource, 'php build/schema_parity_audit.php')
        && str_contains($composerSource, '@test:schema-parity')
        && str_contains($composerSource, 'build/schema_parity_audit.php')
        && str_contains($phpstanConfigSource, 'build/schema_parity_audit.php')
        && str_contains($schemaParityAuditSource, 'schemaParityTableContains')
        && str_contains($schemaParityAuditSource, 'cms_pages.blog_id')
        && str_contains($schemaParityAuditSource, 'cms_media.caption')
        && str_contains($schemaParityAuditSource, 'cms_gallery_photos.deleted_at')
        && str_contains($schemaParityAuditSource, 'ORDER BY p.created_at DESC, p.id DESC')
        && str_contains($schemaParityAuditSource, 'articleExcerpt('),
    'redirect guardrails audit is wired into local quality gates' => str_contains($composerSource, '"test:redirect-guardrails"')
        && str_contains($composerSource, 'php build/redirect_guardrails_audit.php')
        && str_contains($composerSource, '@test:redirect-guardrails')
        && str_contains($composerSource, 'build/redirect_guardrails_audit.php')
        && str_contains($phpstanConfigSource, 'build/redirect_guardrails_audit.php')
        && str_contains($redirectGuardrailsAuditSource, 'redirectGuardrailsReadsRequestRedirectTarget')
        && str_contains($redirectGuardrailsAuditSource, 'redirectGuardrailsOutputsRawRequestUriReturnField')
        && str_contains($redirectGuardrailsAuditSource, 'internalRedirectTarget(')
        && str_contains($redirectGuardrailsAuditSource, 'adminLoginRedirectTarget(')
        && str_contains($redirectGuardrailsAuditSource, 'request-derived redirect target must use internalRedirectTarget()'),
    'source encoding audit is wired into local quality gates' => str_contains($composerSource, '"test:encoding"')
        && str_contains($composerSource, 'php build/source_encoding_audit.php')
        && str_contains($composerSource, '@test:encoding')
        && str_contains($composerSource, 'build/source_encoding_audit.php')
        && str_contains($phpstanConfigSource, 'build/source_encoding_audit.php')
        && str_contains($sourceEncodingAuditSource, 'mb_check_encoding($contents, \'UTF-8\')')
        && str_contains($sourceEncodingAuditSource, 'invalid UTF-8 encoding')
        && str_contains($sourceEncodingAuditSource, 'unexpected UTF-8 BOM')
        && str_contains($sourceEncodingAuditSource, "strtolower((string)pathinfo(str_replace('\\\\', '/', \$relativePath), PATHINFO_EXTENSION)) === 'ps1'"),
    'mojibake audit is wired into local quality gates' => str_contains($composerSource, '"test:mojibake"')
        && str_contains($composerSource, 'php build/mojibake_audit.php')
        && str_contains($composerSource, '@test:mojibake')
        && str_contains($composerSource, 'build/mojibake_audit.php')
        && str_contains($phpstanConfigSource, 'build/mojibake_audit.php')
        && str_contains($mojibakeAuditSource, 'suspicious mojibake fragment')
        && str_contains($mojibakeAuditSource, 'isAllowedIntentionalMojibake')
        && str_contains($mojibakeAuditSource, '$legacyBrokenSocialLinksTitle')
        && str_contains($mojibakeAuditSource, "slugify('Ärger')"),
    'whitespace audit is wired into local quality gates' => str_contains($composerSource, '"test:whitespace"')
        && str_contains($composerSource, 'php build/whitespace_audit.php')
        && str_contains($composerSource, '@test:whitespace')
        && str_contains($composerSource, 'build/whitespace_audit.php')
        && str_contains($phpstanConfigSource, 'build/whitespace_audit.php')
        && str_contains($whitespaceAuditSource, 'git')
        && str_contains($whitespaceAuditSource, 'trailing whitespace')
        && str_contains($whitespaceAuditSource, 'missing final newline'),
    'php cs fixer smoke check exists' => str_contains($composerSource, 'php-cs-fixer fix')
        && str_contains($composerSource, '--dry-run')
        && str_contains($composerSource, '--path-mode=intersection')
        && str_contains($composerSource, 'build/lint_php.php build/phpstan_bootstrap.php')
        && str_contains($composerSource, 'admin/layout.php admin/login.php admin/login_2fa.php admin/logout.php admin/profile.php')
        && str_contains($composerSource, 'admin/settings_modules.php admin/settings_save.php admin/theme_preview.php')
        && str_contains($composerSource, 'admin/approve.php admin/bulk.php admin/content_lock_refresh.php admin/nav_reorder.php admin/reorder_ajax.php')
        && str_contains($composerSource, 'admin/page_positions.php admin/widget_add.php admin/widget_delete.php admin/widget_save.php')
        && str_contains($composerSource, 'admin/form_delete.php admin/form_submission_action.php admin/form_submission_bulk.php')
        && str_contains($composerSource, 'admin/form_submission_delete.php admin/form_submission_file.php admin/form_submission_issue.php admin/form_submission_reply.php')
        && str_contains($composerSource, 'auth.php author.php blog_router.php config.sample.php confirm_email.php cron.php csp-report.php feed.php health.php index.php maintenance.php')
        && str_contains($composerSource, 'newsletter_widget_subscribe.php page.php public_login.php public_logout.php public_profile.php')
        && str_contains($composerSource, 'register.php reset_password.php robots.php search.php sitemap.php subscribe.php subscribe_confirm.php unsubscribe.php')
        && str_contains($composerSource, 'authors/index.php blog/index.php blog/article.php blog/page.php')
        && str_contains($composerSource, 'board/index.php board/document.php board/file.php')
        && str_contains($composerSource, 'chat/index.php contact/index.php')
        && str_contains($composerSource, 'downloads/index.php downloads/item.php downloads/file.php')
        && str_contains($composerSource, 'events/index.php events/event.php events/ics.php')
        && str_contains($composerSource, 'faq/index.php faq/item.php')
        && str_contains($composerSource, 'food/index.php food/archive.php food/card.php')
        && str_contains($composerSource, 'forms/index.php')
        && str_contains($composerSource, 'gallery/index.php gallery/album.php gallery/photo.php gallery/image.php')
        && str_contains($composerSource, 'media/file.php media/preview.php media/thumb.php')
        && str_contains($composerSource, 'news/index.php news/article.php')
        && str_contains($composerSource, 'places/index.php places/place.php places/image.php')
        && str_contains($composerSource, 'podcast/index.php podcast/show.php podcast/episode.php podcast/feed.php podcast/image.php podcast/cover.php podcast/audio.php')
        && str_contains($composerSource, 'polls/index.php')
        && str_contains($composerSource, 'reservations/index.php reservations/resource.php reservations/book.php reservations/my.php reservations/cancel.php reservations/cancel_booking.php')
        && str_contains($composerSource, 'lib/backup.php lib/comments.php lib/content.php')
        && str_contains($composerSource, 'lib/definitions.php lib/filedownloads.php lib/gallery.php lib/github.php')
        && str_contains($composerSource, 'lib/mail.php lib/media_library.php lib/messages.php lib/pagination.php lib/presentation.php')
        && str_contains($composerSource, 'lib/revisions.php lib/stats.php lib/theme.php lib/totp.php lib/ui.php lib/uploads.php')
        && str_contains($composerSource, 'lib/webhooks.php lib/widgets.php')
        && str_contains($composerSource, '@format:check'),
    'php cs fixer admin clone/delete smoke check exists' => str_contains($composerSource, '"format:check:admin-delete"')
        && str_contains($composerSource, '"format:fix:admin-delete"')
        && str_contains($composerSource, '@format:check:admin-delete')
        && str_contains($composerSource, 'admin/blog_clone.php admin/blog_delete.php admin/blog_blog_delete.php admin/blog_cat_delete.php admin/blog_tag_delete.php')
        && str_contains($composerSource, 'admin/board_clone.php admin/board_delete.php admin/news_clone.php admin/news_delete.php')
        && str_contains($composerSource, 'admin/page_clone.php admin/page_delete.php admin/event_clone.php admin/event_delete.php')
        && str_contains($composerSource, 'admin/download_delete.php admin/food_delete.php admin/faq_delete.php admin/faq_cat_delete.php')
        && str_contains($composerSource, 'admin/dl_cat_delete.php admin/place_delete.php admin/polls_delete.php admin/podcast_delete.php admin/podcast_show_delete.php')
        && str_contains($composerSource, 'admin/newsletter_subscriber_delete.php admin/user_delete.php admin/res_cat_delete.php admin/res_location_delete.php admin/res_resource_delete.php'),
    'php cs fixer admin messaging smoke check exists' => str_contains($composerSource, '"format:check:admin-messaging"')
        && str_contains($composerSource, '"format:fix:admin-messaging"')
        && str_contains($composerSource, '@format:check:admin-messaging')
        && str_contains($composerSource, 'admin/comment_action.php admin/comment_approve.php admin/comment_bulk.php admin/comment_delete.php')
        && str_contains($composerSource, 'admin/contact_action.php admin/contact_bulk.php admin/contact_delete.php')
        && str_contains($composerSource, 'admin/chat_action.php admin/chat_bulk.php admin/chat_delete.php admin/chat_reply.php admin/chat_update.php')
        && str_contains($composerSource, 'admin/newsletter_bulk.php admin/newsletter_send.php admin/newsletter_subscriber_action.php'),
    'php cs fixer admin moderation smoke check exists' => str_contains($composerSource, '"format:check:admin-moderation"')
        && str_contains($composerSource, '"format:fix:admin-moderation"')
        && str_contains($composerSource, '@format:check:admin-moderation')
        && str_contains($composerSource, 'admin/comments.php admin/contact.php admin/chat.php admin/form_submissions.php'),
    'php cs fixer admin save smoke check exists' => str_contains($composerSource, '"format:check:admin-save"')
        && str_contains($composerSource, '"format:fix:admin-save"')
        && str_contains($composerSource, '@format:check:admin-save')
        && str_contains($composerSource, 'admin/blog_save.php admin/board_save.php admin/download_save.php admin/event_save.php admin/faq_save.php')
        && str_contains($composerSource, 'admin/food_save.php admin/form_save.php admin/gallery_album_save.php admin/gallery_photo_save.php admin/news_save.php')
        && str_contains($composerSource, 'admin/page_save.php admin/place_save.php admin/podcast_save.php admin/podcast_show_save.php')
        && str_contains($composerSource, 'admin/polls_save.php admin/res_booking_save.php admin/res_resource_save.php admin/user_save.php'),
    'php cs fixer admin form smoke check exists' => str_contains($composerSource, '"format:check:admin-form"')
        && str_contains($composerSource, '"format:fix:admin-form"')
        && str_contains($composerSource, '@format:check:admin-form')
        && str_contains($composerSource, 'admin/blog_form.php admin/board_form.php admin/download_form.php admin/event_form.php admin/faq_form.php')
        && str_contains($composerSource, 'admin/food_form.php admin/form_form.php admin/gallery_album_form.php admin/gallery_photo_form.php admin/news_form.php')
        && str_contains($composerSource, 'admin/newsletter_form.php admin/page_form.php admin/place_form.php admin/podcast_form.php admin/podcast_show_form.php')
        && str_contains($composerSource, 'admin/polls_form.php admin/res_resource_form.php admin/user_form.php'),
    'php cs fixer admin taxonomy smoke check exists' => str_contains($composerSource, '"format:check:admin-taxonomy"')
        && str_contains($composerSource, '"format:fix:admin-taxonomy"')
        && str_contains($composerSource, '@format:check:admin-taxonomy')
        && str_contains($composerSource, 'admin/blog_cats.php admin/blog_tags.php admin/blog_members.php admin/blog_pages.php')
        && str_contains($composerSource, 'admin/board_cats.php admin/board_cat_delete.php admin/dl_cats.php admin/faq_cats.php')
        && str_contains($composerSource, 'admin/res_categories.php admin/res_locations.php')
        && str_contains($composerSource, 'admin/page_reorder.php admin/gallery_photo_reorder.php admin/gallery_album_delete.php admin/gallery_photo_delete.php'),
    'php cs fixer admin overview smoke check exists' => str_contains($composerSource, '"format:check:admin-overview"')
        && str_contains($composerSource, '"format:fix:admin-overview"')
        && str_contains($composerSource, '@format:check:admin-overview')
        && str_contains($composerSource, 'admin/index.php admin/audit_log.php admin/backup.php admin/blog.php admin/blogs.php admin/board.php')
        && str_contains($composerSource, 'admin/downloads.php admin/events.php admin/faq.php admin/food.php admin/forms.php')
        && str_contains($composerSource, 'admin/gallery_albums.php admin/gallery_photos.php admin/integrity.php admin/media.php admin/menu.php')
        && str_contains($composerSource, 'admin/news.php admin/newsletter.php admin/pages.php admin/places.php admin/podcast.php')
        && str_contains($composerSource, 'admin/podcast_shows.php admin/polls.php admin/redirects.php admin/res_bookings.php admin/res_resources.php')
        && str_contains($composerSource, 'admin/review_queue.php admin/trash.php admin/users.php'),
    'php cs fixer admin utility smoke check exists' => str_contains($composerSource, '"format:check:admin-utility"')
        && str_contains($composerSource, '"format:fix:admin-utility"')
        && str_contains($composerSource, '@format:check:admin-utility')
        && str_contains($composerSource, 'admin/blog_bulk.php admin/blog_content_reference_search.php admin/blog_transfer.php')
        && str_contains($composerSource, 'admin/content_reference_picker.php admin/content_reference_search.php admin/convert_content.php')
        && str_contains($composerSource, 'admin/gallery_export_zip.php admin/contact_message.php admin/chat_message.php admin/form_submission.php')
        && str_contains($composerSource, 'admin/newsletter_history.php admin/newsletter_subscriber.php')
        && str_contains($composerSource, 'admin/res_booking_add.php admin/res_booking_detail.php admin/settings_shared.php'),
    'php cs fixer admin display smoke check exists' => str_contains($composerSource, '"format:check:admin-display"')
        && str_contains($composerSource, '"format:fix:admin-display"')
        && str_contains($composerSource, '@format:check:admin-display')
        && str_contains($composerSource, 'admin/settings_display.php admin/themes.php admin/widgets.php'),
    'php cs fixer admin maintenance smoke check exists' => str_contains($composerSource, '"format:check:admin-maintenance"')
        && str_contains($composerSource, '"format:fix:admin-maintenance"')
        && str_contains($composerSource, '@format:check:admin-maintenance')
        && str_contains($composerSource, 'admin/settings.php admin/statistics.php admin/import.php admin/export.php')
        && str_contains($composerSource, 'admin/estranky_import.php')
        && str_contains($composerSource, 'admin/estranky_download_photos.php admin/wp_import.php admin/revisions.php'),
    'php cs fixer build test smoke check exists' => str_contains($composerSource, '"format:check:build-tests"')
        && str_contains($composerSource, '"format:fix:build-tests"')
        && str_contains($composerSource, '@format:check:build-tests')
        && str_contains($composerSource, 'build/http_server_router.php build/http_test_helpers.php build/release_package_audit.php build/release_smoke.php')
        && str_contains($composerSource, 'build/theme_view_audit.php build/theme_view_audit_selftest.php build/unit_test_bootstrap.php build/unit_tests.php'),
    'phpstan build test smoke check exists' => str_contains($composerSource, '"analyse:strict:build-tests"')
        && str_contains($composerSource, '@analyse:strict:build-tests')
        && str_contains($composerSource, '--level=6 build/http_server_router.php build/http_test_helpers.php build/release_package_audit.php build/release_smoke.php')
        && str_contains($composerSource, 'build/theme_view_audit.php build/theme_view_audit_selftest.php build/unit_test_bootstrap.php build/unit_tests.php'),
    'theme view audit is wired into basic CI' => str_contains($composerSource, '"test:theme-views"')
        && str_contains($composerSource, 'php build/theme_view_audit.php')
        && str_contains($composerSource, '@test:theme-views')
        && str_contains($composerSource, '"test:theme-views-selftest"')
        && str_contains($composerSource, 'php build/theme_view_audit_selftest.php')
        && str_contains($composerSource, '@test:theme-views-selftest'),
    'theme view audit checks static id and aria references' => str_contains($themeViewAuditSource, 'themeViewAuditCheckStaticIdReferences')
        && str_contains($themeViewAuditSource, "['aria-labelledby', 'aria-describedby']")
        && str_contains($themeViewAuditSource, 'duplicate static id')
        && str_contains($themeViewAuditSource, 'missing static ')
        && str_contains($themeViewAuditSelftestSource, 'Duplicate static id guard')
        && str_contains($themeViewAuditSelftestSource, 'Missing static aria target guard'),
    'phpstan covers stable helper batches' => str_contains($composerSource, '"analyse"')
        && str_contains($composerSource, 'phpstan analyse')
        && str_contains($phpstanConfigSource, 'level: 6')
        && str_contains($phpstanConfigSource, 'bootstrapFiles')
        && str_contains($phpstanConfigSource, 'scanFiles')
        && str_contains($phpstanConfigSource, 'admin/content_reference_picker.php')
        && str_contains($phpstanConfigSource, 'admin/layout.php')
        && str_contains($phpstanConfigSource, 'admin/settings_shared.php')
        && str_contains($phpstanConfigSource, 'build/http_server_router.php')
        && str_contains($phpstanConfigSource, 'build/http_test_helpers.php')
        && str_contains($phpstanConfigSource, 'build/phpstan_bootstrap.php')
        && str_contains($phpstanConfigSource, 'build/repository_guardrails_audit.php')
        && str_contains($phpstanConfigSource, 'build/config_sample_audit.php')
        && str_contains($phpstanConfigSource, 'build/version_metadata_audit.php')
        && str_contains($phpstanConfigSource, 'build/schema_parity_audit.php')
        && str_contains($phpstanConfigSource, 'build/redirect_guardrails_audit.php')
        && str_contains($phpstanConfigSource, 'build/release_package_audit.php')
        && str_contains($phpstanConfigSource, 'build/release_smoke.php')
        && str_contains($phpstanConfigSource, 'build/theme_view_audit.php')
        && str_contains($phpstanConfigSource, 'build/theme_view_audit_selftest.php')
        && str_contains($phpstanConfigSource, 'build/source_encoding_audit.php')
        && str_contains($phpstanConfigSource, 'build/mojibake_audit.php')
        && str_contains($phpstanConfigSource, 'build/whitespace_audit.php')
        && str_contains($phpstanConfigSource, 'auth.php')
        && str_contains($phpstanConfigSource, 'db.php')
        && str_contains($phpstanConfigSource, 'lib/content.php')
        && str_contains($phpstanConfigSource, 'lib/filedownloads.php')
        && str_contains($phpstanConfigSource, 'lib/github.php')
        && str_contains($phpstanConfigSource, 'lib/media_library.php')
        && str_contains($phpstanConfigSource, 'lib/pagination.php')
        && str_contains($phpstanConfigSource, 'lib/theme.php')
        && str_contains($phpstanConfigSource, 'lib/totp.php')
        && str_contains($phpstanConfigSource, 'lib/ui.php')
        && str_contains($phpstanConfigSource, 'lib/uploads.php')
        && str_contains($composerSource, '"analyse:strict"')
        && str_contains($composerSource, '"analyse:strict:imports"')
        && str_contains($composerSource, 'admin/import.php admin/export.php admin/wp_import.php admin/estranky_import.php admin/estranky_download_photos.php')
        && str_contains($composerSource, '"@analyse:strict:imports"')
        && str_contains($composerSource, '"analyse:strict:public-authors"')
        && str_contains($composerSource, 'authors/index.php')
        && str_contains($composerSource, '"@analyse:strict:public-authors"')
        && str_contains($composerSource, '"analyse:strict:public-blog"')
        && str_contains($composerSource, 'blog/index.php blog/article.php blog/page.php')
        && str_contains($composerSource, '"@analyse:strict:public-blog"')
        && str_contains($composerSource, '"analyse:strict:public-board"')
        && str_contains($composerSource, 'board/index.php board/document.php board/file.php')
        && str_contains($composerSource, '"@analyse:strict:public-board"')
        && str_contains($composerSource, '"analyse:strict:public-chat"')
        && str_contains($composerSource, 'chat/index.php')
        && str_contains($composerSource, '"@analyse:strict:public-chat"')
        && str_contains($composerSource, '"analyse:strict:public-contact"')
        && str_contains($composerSource, 'contact/index.php')
        && str_contains($composerSource, '"@analyse:strict:public-contact"')
        && str_contains($composerSource, '"analyse:strict:public-downloads"')
        && str_contains($composerSource, 'downloads/index.php downloads/item.php downloads/file.php')
        && str_contains($composerSource, '"@analyse:strict:public-downloads"')
        && str_contains($composerSource, '"analyse:strict:public-events"')
        && str_contains($composerSource, 'events/index.php events/event.php events/ics.php')
        && str_contains($composerSource, '"@analyse:strict:public-events"')
        && str_contains($composerSource, '"analyse:strict:public-faq"')
        && str_contains($composerSource, 'faq/index.php faq/item.php')
        && str_contains($composerSource, '"@analyse:strict:public-faq"')
        && str_contains($composerSource, '"analyse:strict:public-food"')
        && str_contains($composerSource, 'food/index.php food/archive.php food/card.php')
        && str_contains($composerSource, '"@analyse:strict:public-food"')
        && str_contains($composerSource, '"analyse:strict:public-forms"')
        && str_contains($composerSource, 'forms/index.php')
        && str_contains($composerSource, '"@analyse:strict:public-forms"')
        && str_contains($composerSource, '"analyse:strict:public-gallery"')
        && str_contains($composerSource, 'gallery/index.php gallery/album.php gallery/photo.php gallery/image.php')
        && str_contains($composerSource, '"@analyse:strict:public-gallery"')
        && str_contains($composerSource, '"analyse:strict:public-media"')
        && str_contains($composerSource, 'media/file.php media/preview.php media/thumb.php')
        && str_contains($composerSource, '"@analyse:strict:public-media"')
        && str_contains($composerSource, '"analyse:strict:public-news"')
        && str_contains($composerSource, 'news/index.php news/article.php')
        && str_contains($composerSource, '"@analyse:strict:public-news"')
        && str_contains($composerSource, '"analyse:strict:public-places"')
        && str_contains($composerSource, 'places/index.php places/place.php places/image.php')
        && str_contains($composerSource, '"@analyse:strict:public-places"')
        && str_contains($composerSource, '"analyse:strict:public-podcast"')
        && str_contains($composerSource, 'podcast/index.php podcast/show.php podcast/episode.php podcast/feed.php podcast/image.php podcast/cover.php podcast/audio.php')
        && str_contains($composerSource, '"@analyse:strict:public-podcast"')
        && str_contains($composerSource, '"analyse:strict:public-polls"')
        && str_contains($composerSource, 'polls/index.php')
        && str_contains($composerSource, '"@analyse:strict:public-polls"')
        && str_contains($composerSource, '"analyse:strict:public-reservations"')
        && str_contains($composerSource, 'reservations/index.php reservations/resource.php reservations/book.php reservations/my.php reservations/cancel.php reservations/cancel_booking.php')
        && str_contains($composerSource, '"@analyse:strict:public-reservations"')
        && str_contains($composerSource, '--level=6')
        && str_contains($composerSource, 'admin/approve.php admin/audit_log.php admin/backup.php admin/blog.php admin/blog_blog_delete.php admin/blog_bulk.php admin/blog_cats.php admin/blog_cat_delete.php admin/blog_clone.php admin/blog_content_reference_search.php admin/blog_delete.php admin/blog_form.php admin/blog_members.php admin/blog_pages.php admin/blog_save.php admin/blog_tags.php admin/blog_tag_delete.php admin/blog_transfer.php admin/blogs.php admin/board.php admin/board_cats.php admin/board_cat_delete.php admin/board_clone.php admin/board_delete.php admin/board_form.php admin/board_save.php admin/bulk.php admin/chat.php admin/chat_action.php admin/chat_bulk.php admin/chat_delete.php admin/chat_message.php admin/chat_reply.php admin/chat_update.php admin/comments.php admin/comment_action.php admin/comment_approve.php admin/comment_bulk.php admin/comment_delete.php admin/contact.php admin/contact_action.php admin/contact_bulk.php admin/contact_delete.php admin/contact_message.php admin/content_lock_refresh.php admin/content_reference_picker.php admin/content_reference_search.php admin/convert_content.php admin/dl_cats.php admin/dl_cat_delete.php admin/downloads.php admin/download_form.php admin/download_save.php admin/download_delete.php admin/event_form.php admin/event_save.php admin/events.php admin/event_clone.php admin/event_delete.php admin/faq.php admin/faq_cats.php admin/faq_cat_delete.php admin/faq_delete.php admin/faq_form.php admin/faq_save.php admin/food.php admin/food_form.php admin/food_save.php admin/food_delete.php admin/form_delete.php admin/form_form.php admin/form_save.php admin/form_submission.php admin/form_submission_action.php admin/form_submission_bulk.php admin/form_submission_delete.php admin/form_submission_file.php admin/form_submission_issue.php admin/form_submission_reply.php admin/form_submissions.php admin/forms.php admin/gallery_album_delete.php admin/gallery_album_form.php admin/gallery_album_save.php admin/gallery_albums.php admin/gallery_export_zip.php admin/gallery_photo_delete.php admin/gallery_photo_form.php admin/gallery_photo_reorder.php admin/gallery_photo_save.php admin/gallery_photos.php admin/index.php admin/integrity.php admin/layout.php admin/login.php admin/login_2fa.php admin/logout.php admin/media.php admin/menu.php admin/nav_reorder.php admin/news.php admin/news_clone.php admin/news_delete.php admin/news_form.php admin/news_save.php admin/newsletter.php admin/newsletter_bulk.php admin/newsletter_form.php admin/newsletter_history.php admin/newsletter_send.php admin/newsletter_subscriber.php admin/newsletter_subscriber_action.php admin/newsletter_subscriber_delete.php admin/page_clone.php admin/page_delete.php admin/page_form.php admin/page_positions.php admin/page_reorder.php admin/page_save.php admin/pages.php admin/place_delete.php admin/place_form.php admin/place_save.php admin/places.php admin/podcast.php admin/podcast_delete.php admin/podcast_form.php admin/podcast_save.php admin/podcast_show_delete.php admin/podcast_show_form.php admin/podcast_show_save.php admin/podcast_shows.php admin/polls.php admin/polls_form.php admin/polls_save.php admin/polls_delete.php admin/profile.php admin/redirects.php admin/reorder_ajax.php admin/res_booking_add.php admin/res_booking_detail.php admin/res_booking_save.php admin/res_bookings.php admin/res_cat_delete.php admin/res_categories.php admin/res_location_delete.php admin/res_locations.php admin/res_resource_delete.php admin/res_resource_form.php admin/res_resource_save.php admin/res_resources.php admin/review_queue.php admin/revisions.php admin/settings.php admin/settings_display.php admin/settings_modules.php admin/settings_save.php admin/settings_shared.php admin/statistics.php admin/theme_preview.php admin/themes.php admin/trash.php admin/user_delete.php admin/user_form.php admin/user_save.php admin/users.php admin/widget_add.php admin/widget_delete.php admin/widgets.php admin/widget_save.php author.php blog_router.php build/lint_php.php build/phpstan_bootstrap.php build/workflow_audit.php build/repository_guardrails_audit.php build/config_sample_audit.php build/version_metadata_audit.php build/schema_parity_audit.php build/redirect_guardrails_audit.php build/source_encoding_audit.php build/mojibake_audit.php build/whitespace_audit.php auth.php confirm_email.php cron.php csp-report.php db.php feed.php health.php index.php install.php maintenance.php migrate.php newsletter_widget_subscribe.php page.php public_login.php public_logout.php public_profile.php register.php reset_password.php robots.php search.php sitemap.php subscribe.php subscribe_confirm.php unsubscribe.php')
        && str_contains($composerSource, 'csp-report.php')
        && str_contains($composerSource, 'lib/backup.php')
        && str_contains($composerSource, 'lib/comments.php lib/content.php lib/definitions.php')
        && str_contains($composerSource, 'lib/filedownloads.php lib/gallery.php lib/github.php lib/mail.php lib/media_library.php')
        && str_contains($composerSource, 'lib/messages.php lib/pagination.php lib/presentation.php lib/revisions.php lib/stats.php lib/theme.php')
        && str_contains($composerSource, 'lib/totp.php lib/ui.php lib/uploads.php lib/webhooks.php lib/widgets.php')
        && str_contains($phpstanBootstrapSource, "getenv('KORA_PHPSTAN_BASE_URL')")
        && str_contains($phpstanBootstrapSource, "define('KORA_VERSION', '0.0.0')")
        && str_contains($phpstanBootstrapSource, 'function h(?string $s): string')
        && !str_contains($phpstanBootstrapSource, 'require_once'),
    'dev tooling is protected from direct web access' => str_contains($htaccessSource, 'composer\.(json|lock)')
        && str_contains($htaccessSource, 'RewriteRule ^\.github - [F,L]')
        && str_contains($htaccessSource, 'RewriteRule ^vendor/ - [F,L]'),
    'release zip excludes dev tooling and metadata' => str_contains($releaseScriptSource, "'vendor'")
        && str_contains($releaseScriptSource, "'.github'")
        && str_contains($releaseScriptSource, "'.gitattributes'")
        && str_contains($releaseScriptSource, "'composer.json'")
        && str_contains($releaseScriptSource, "'composer.lock'")
        && str_contains($releaseScriptSource, "'phpstan.neon.dist'")
        && str_contains($releaseScriptSource, "'.php-cs-fixer.dist.php'")
        && str_contains($releaseScriptSource, 'Invoke-ReleasePackageAudit -ProjectRoot $projectRoot')
        && str_contains($releaseScriptSource, 'Invoke-ReleaseCi -ProjectRoot $projectRoot -Full:$FullCi')
        && str_contains($releaseScriptSource, '[switch]$FullCi')
        && str_contains($releaseScriptSource, '[switch]$SkipCi')
        && str_contains($releaseScriptSource, '[switch]$DryRun')
        && str_contains($releaseScriptSource, 'function Get-UpdatedChangelogContent')
        && str_contains($releaseScriptSource, '## [Unreleased]`n`n## [$NewVersion]')
        && str_contains($releaseScriptSource, "'ci:basic'")
        && str_contains($releaseScriptSource, "'ci:full'")
        && str_contains($releaseScriptSource, 'New-ReleaseZip')
        && str_contains($releaseScriptSource, '[hashtable]$FileOverrides')
        && str_contains($releaseScriptSource, 'Write-ReleaseChecksum -Path $zipPath')
        && str_contains($releaseScriptSource, 'Get-FileHash -Path $Path -Algorithm SHA256')
        && str_contains($releaseScriptSource, 'koracms-$newVersion.zip.sha256')
        && str_contains($releaseScriptSource, 'if ($DryRun)')
        && str_contains($releaseScriptSource, '$packageFileOverrides["VERSION"] = $newVersion')
        && str_contains($releaseScriptSource, 'New-ReleaseZip -ProjectRoot $projectRoot -OutPath $zipPath -FileOverrides $packageFileOverrides')
        && str_contains($releaseScriptSource, 'VERSION a CHANGELOG.md zůstávají beze změn')
        && str_contains($releaseScriptSource, 'Dry run dokončen')
        && str_contains($releaseScriptSource, 'Nebyl vytvořen commit, tag, push ani GitHub release'),
    'release package audit is wired into basic CI' => str_contains($composerSource, '"test:release-package"')
        && str_contains($composerSource, '@test:release-package')
        && str_contains($releasePackageAuditSource, 'release.ps1')
        && str_contains($releasePackageAuditSource, 'Invoke-ReleasePackageAudit')
        && str_contains($releasePackageAuditSource, 'Invoke-ReleaseCi')
        && str_contains($releasePackageAuditSource, '[switch]$FullCi')
        && str_contains($releasePackageAuditSource, '[switch]$DryRun')
        && str_contains($releasePackageAuditSource, '[hashtable]$FileOverrides')
        && str_contains($releasePackageAuditSource, "'.github'")
        && str_contains($releasePackageAuditSource, 'Write-ReleaseChecksum')
        && str_contains($releasePackageAuditSource, 'Dry run dokončen')
        && str_contains($releasePackageAuditSource, '$packageFileOverrides["VERSION"] = $newVersion')
        && str_contains($releasePackageAuditSource, '$gitignorePath')
        && str_contains($releasePackageAuditSource, '!docs/admin-guide.md')
        && str_contains($releasePackageAuditSource, 'build/release_package_audit.php export-ignore')
        && str_contains($releasePackageAuditSource, 'Release package audit OK'),
    'release dry run smoke is wired into basic CI' => str_contains($composerSource, '"test:release-smoke"')
        && str_contains($composerSource, 'php build/release_smoke.php')
        && str_contains($composerSource, '@test:release-smoke')
        && str_contains($releaseSmokeSource, "DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release.ps1'")
        && str_contains($releaseSmokeSource, 'pwsh')
        && str_contains($releaseSmokeSource, 'powershell')
        && str_contains($releaseSmokeSource, "['git', 'init', '--quiet']")
        && str_contains($releaseSmokeSource, "['git', 'add', '--all']")
        && str_contains($releaseSmokeSource, "['git', 'commit', '--quiet', '-m', 'Release smoke snapshot']")
        && str_contains($releaseSmokeSource, "['git', 'status', '--short', '--untracked-files=all']")
        && str_contains($releaseSmokeSource, "['git', 'tag', '--list']")
        && str_contains($releaseSmokeSource, "['git', 'archive', '--format=zip', '--output', \$sourceArchivePath, 'HEAD']")
        && str_contains($releaseSmokeSource, '-DryRun')
        && str_contains($releaseSmokeSource, '-SkipCi')
        && str_contains($releaseSmokeSource, 'System.IO.Compression.ZipFile')
        && str_contains($releaseSmokeSource, 'ConvertTo-Json')
        && str_contains($releaseSmokeSource, 'docs/admin-guide.md')
        && str_contains($releaseSmokeSource, 'themes/default/theme.json')
        && str_contains($releaseSmokeSource, 'Release smoke ZIP does not keep a fresh Unreleased section')
        && str_contains($releaseSmokeSource, 'Source archive unexpectedly contains build tooling')
        && str_contains($releaseSmokeSource, 'Source archive unexpectedly contains docs content')
        && str_contains($releaseSmokeSource, 'Release smoke OK'),
    'repository attributes protect source archives and line endings' => str_contains($gitattributesSource, '.gitattributes text eol=lf')
        && str_contains($gitattributesSource, '*.php text eol=lf')
        && str_contains($gitattributesSource, '*.json text eol=lf')
        && str_contains($gitattributesSource, '*.md text eol=lf')
        && str_contains($gitattributesSource, '*.neon.dist text eol=lf')
        && str_contains($gitattributesSource, '.github export-ignore')
        && str_contains($gitattributesSource, '.github/** export-ignore')
        && str_contains($gitattributesSource, '.gitignore export-ignore')
        && str_contains($gitattributesSource, '.gitattributes export-ignore')
        && str_contains($gitattributesSource, '.php-cs-fixer.dist.php export-ignore')
        && str_contains($gitattributesSource, 'build export-ignore')
        && str_contains($gitattributesSource, 'build/** export-ignore')
        && str_contains($gitattributesSource, 'build/release_package_audit.php export-ignore')
        && str_contains($gitattributesSource, 'build/release_smoke.php export-ignore')
        && str_contains($gitattributesSource, 'composer.json export-ignore')
        && str_contains($gitattributesSource, 'composer.lock export-ignore')
        && str_contains($gitattributesSource, 'docs/** export-ignore')
        && str_contains($gitattributesSource, 'phpstan.neon.dist export-ignore'),
    'gitignore protects local and generated artifacts' => str_contains($gitignoreSource, 'config.php')
        && str_contains($gitignoreSource, 'aconfig.php')
        && str_contains($gitignoreSource, 'uploads/')
        && str_contains($gitignoreSource, '.integrity_snapshot.json')
        && str_contains($gitignoreSource, 'dist/')
        && str_contains($gitignoreSource, 'vendor/')
        && str_contains($gitignoreSource, '.php-cs-fixer.cache')
        && str_contains($gitignoreSource, '.DS_Store')
        && str_contains($gitignoreSource, 'Thumbs.db')
        && str_contains($gitignoreSource, '.vscode/')
        && str_contains($gitignoreSource, '.idea/')
        && str_contains($gitignoreSource, '.claude/')
        && str_contains($gitignoreSource, '!docs/admin-guide.md'),
    'robots route exists' => str_contains($htaccessSource, 'RewriteRule ^robots\.txt$ robots.php')
        && str_contains($robotsSource, 'Disallow: " . BASE_URL . "/admin/')
        && str_contains($robotsSource, 'Sitemap: " . siteUrl(\'/sitemap.xml\')')
        && str_contains($robotsSource, "in_array(\$requestMethod, ['GET', 'HEAD'], true)")
        && str_contains($robotsSource, "header('Allow: GET, HEAD')"),
    'read-only discovery endpoints enforce HTTP methods' => str_contains($sitemapSource, "in_array(\$requestMethod, ['GET', 'HEAD'], true)")
        && str_contains($sitemapSource, "header('Allow: GET, HEAD')")
        && str_contains($sitemapSource, "if (\$requestMethod === 'HEAD')")
        && str_contains($feedSource, "in_array(\$requestMethod, ['GET', 'HEAD'], true)")
        && str_contains($feedSource, "header('Allow: GET, HEAD')")
        && str_contains($feedSource, "if (\$isHeadRequest)")
        && str_contains($podcastFeedSource, "in_array(\$requestMethod, ['GET', 'HEAD'], true)")
        && str_contains($podcastFeedSource, "header('Allow: GET, HEAD')")
        && str_contains($podcastFeedSource, "if (\$isHeadRequest)")
        && str_contains($eventIcsSource, "in_array(\$requestMethod, ['GET', 'HEAD'], true)")
        && str_contains($eventIcsSource, "header('Allow: GET, HEAD')")
        && str_contains($eventIcsSource, "if (\$isHeadRequest)"),
    'file response helper enforces read-only methods' => str_contains($fileDownloadHelperSource, 'function requireReadOnlyHttpMethod(): bool')
        && str_contains($fileDownloadHelperSource, "in_array(\$requestMethod, ['GET', 'HEAD'], true)")
        && str_contains($fileDownloadHelperSource, "header('Allow: GET, HEAD')")
        && str_contains($fileDownloadHelperSource, "if ((\$_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD')"),
    'public file endpoints enforce HTTP methods' => str_contains($readOnlyMediaFileSource, 'requireReadOnlyHttpMethod();')
        && str_contains($readOnlyMediaPreviewSource, 'requireReadOnlyHttpMethod();')
        && str_contains($readOnlyMediaThumbSource, '$isHeadRequest = requireReadOnlyHttpMethod();')
        && str_contains($readOnlyDownloadsFileSource, 'requireReadOnlyHttpMethod();')
        && str_contains($readOnlyBoardFileSource, 'requireReadOnlyHttpMethod();')
        && str_contains($readOnlyGalleryImageSource, '$isHeadRequest = requireReadOnlyHttpMethod();')
        && str_contains($readOnlyPlacesImageSource, '$isHeadRequest = requireReadOnlyHttpMethod();')
        && str_contains($readOnlyPodcastAudioSource, '$isHeadRequest = requireReadOnlyHttpMethod();')
        && str_contains($readOnlyPodcastImageSource, '$isHeadRequest = requireReadOnlyHttpMethod();')
        && str_contains($readOnlyPodcastCoverSource, '$isHeadRequest = requireReadOnlyHttpMethod();'),
    'admin read-only endpoints enforce HTTP methods' => str_contains($adminExportSource, '$isHeadRequest = requireReadOnlyHttpMethod();')
        && str_contains($adminExportSource, 'if ($isHeadRequest)')
        && str_contains($adminFormSubmissionFileSource, '$isHeadRequest = requireReadOnlyHttpMethod();')
        && str_contains($adminFormSubmissionFileSource, 'if ($isHeadRequest)')
        && str_contains($adminFormSubmissionsSource, '$isCsvExportHeadRequest = requireReadOnlyHttpMethod();')
        && str_contains($adminFormSubmissionsSource, 'if ($isCsvExportHeadRequest)')
        && str_contains($adminContentReferenceSearchSource, '$isHeadRequest = requireReadOnlyHttpMethod();')
        && str_contains($adminContentReferenceSearchSource, 'if ($isHeadRequest)'),
    'seoMeta renders canonical' => str_contains($uiSource, 'function seoCanonicalUrl(string $target): string')
        && str_contains($uiSource, '<link rel="canonical" href="')
        && str_contains($uiSource, 'seoCanonicalUrl((string)($meta[\'url\'] ?? \'\'))'),
    'health endpoint is minimal JSON' => str_contains($healthSource, "header('Content-Type: application/json; charset=UTF-8')")
        && str_contains($healthSource, "header('Cache-Control: no-store, max-age=0')")
        && str_contains($healthSource, "header('Pragma: no-cache')")
        && str_contains($healthSource, "in_array(\$requestMethod, ['GET', 'HEAD'], true)")
        && str_contains($healthSource, "header('Allow: GET, HEAD')")
        && str_contains($healthSource, "if (\$isHeadRequest)")
        && str_contains($healthSource, "db_connect()->query('SELECT 1')")
        && str_contains($healthSource, "'request_id' => koraRequestId()")
        && str_contains($healthSource, "'database' => ['status' => 'fail']")
        && str_contains($healthSource, "'storage' => ['status' => 'fail']"),
    'health endpoint reports cron freshness' => str_contains($healthSource, "'cron' => ['status' => 'unknown']")
        && str_contains($healthSource, "getSetting('cron_last_run_at', '')")
        && str_contains($healthSource, "'last_run' => date(DATE_ATOM, \$cronLastRunTimestamp)"),
    'health endpoint reports latest backup timestamp' => str_contains($healthSource, "'last_backup' => date(DATE_ATOM, \$latestBackupTimestamp)")
        && str_contains($healthSource, "'backup' => ['status' => 'unknown']"),
    'csp report endpoint is a non-cacheable JSON receiver' => str_contains($cspReportSource, "header('Cache-Control: no-store, max-age=0')")
        && str_contains($cspReportSource, "header('Pragma: no-cache')")
        && str_contains($cspReportSource, 'function cspReportJsonResponse')
        && str_contains($cspReportSource, "'request_id' => koraRequestId()")
        && str_contains($cspReportSource, "header('Allow: POST')"),
    'admin POST-only JSON endpoints send safe headers' => str_contains($adminContentLockRefreshSource, "header('Cache-Control: no-store')")
        && str_contains($adminContentLockRefreshSource, "header('X-Content-Type-Options: nosniff')")
        && str_contains($adminContentLockRefreshSource, "header('Allow: POST')")
        && str_contains($adminReorderAjaxSource, "header('Cache-Control: no-store')")
        && str_contains($adminReorderAjaxSource, "header('X-Content-Type-Options: nosniff')")
        && str_contains($adminReorderAjaxSource, "header('Allow: POST')"),
    'admin downloadable exports send safe headers' => str_contains($adminExportSource, "header('Cache-Control: no-store')")
        && str_contains($adminExportSource, "header('X-Content-Type-Options: nosniff')")
        && str_contains($adminFormSubmissionFileSource, "header('Cache-Control: no-store')")
        && str_contains($adminFormSubmissionFileSource, "header('X-Content-Type-Options: nosniff')")
        && str_contains($adminFormSubmissionsSource, "header('Cache-Control: no-store')")
        && str_contains($adminFormSubmissionsSource, "header('X-Content-Type-Options: nosniff')")
        && str_contains($adminBackupSource, "header('Cache-Control: no-store')")
        && str_contains($adminBackupSource, "header('X-Content-Type-Options: nosniff')")
        && substr_count($adminGalleryExportZipSource, "header('Cache-Control: no-store')") >= 2
        && substr_count($adminGalleryExportZipSource, "header('X-Content-Type-Options: nosniff')") >= 2
        && str_contains($adminThemesSource, "header('Cache-Control: no-store')")
        && str_contains($adminThemesSource, "header('X-Content-Type-Options: nosniff')"),
    'request id and structured error logging exist' => str_contains($authSource, 'function koraRequestId')
        && str_contains($authSource, "header('X-Request-ID: ' . \$requestId)")
        && str_contains($authSource, 'function koraLog(')
        && str_contains($authSource, 'JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES')
        && str_contains($dbSource, '$requestId = koraRequestId()')
        && str_contains($dbSource, "koraLog('error', 'Uncaught exception'")
        && str_contains($dbSource, "'file' => basename(\$exceptionFile)")
        && str_contains($dbSource, "'file_hash' => hash('sha256', \$exceptionFile)")
        && !str_contains($dbSource, "'file' => \$e->getFile()"),
    'uncaught exception page uses static error stylesheet' => str_contains($dbSource, '/assets/error.css')
        && str_contains($dbSource, 'class="error-page"')
        && str_contains($dbSource, 'class="error-page__request"')
        && str_contains($dbSource, "header('Cache-Control: no-store, max-age=0')")
        && str_contains($dbSource, "header('Pragma: no-cache')")
        && !str_contains($dbSource, '<style>body{font-family:system-ui')
        && str_contains($errorPageCssSource, '.error-page')
        && str_contains($errorPageCssSource, '.error-page__request')
        && str_contains($errorPageCssSource, '.error-page pre'),
    'anti-spam honeypot uses shared CSS class' => str_contains($authSource, 'class="honeypot-field"')
        && !str_contains($authSource, 'style="position:absolute;left:-9999px')
        && str_contains($publicCoreCssSource, '.honeypot-field')
        && str_contains($adminLayoutStylesheetSource, '.honeypot-field'),
    'public composite pages use structured recoverable error logs' => !str_contains($blogIndexSource, 'error_log(')
        && !str_contains($blogArticleSource, 'error_log(')
        && !str_contains($searchSource, 'error_log(')
        && !str_contains($sitemapSource, 'error_log(')
        && str_contains($blogIndexSource, "koraLog('warning', 'blog index pages query failed'")
        && str_contains($blogArticleSource, "koraLog('warning', 'article tags query failed'")
        && str_contains($searchSource, 'function searchLogSourceError')
        && str_contains($searchSource, "koraLog('warning', 'search source query failed'")
        && str_contains($sitemapSource, 'function sitemapLogSectionError')
        && str_contains($sitemapSource, "koraLog('warning', 'sitemap section query failed'"),
    'public submission and token endpoints use structured recoverable error logs' => !str_contains($boardIndexSource, 'error_log(')
        && !str_contains($contactIndexSource, 'error_log(')
        && !str_contains($chatIndexSource, 'error_log(')
        && !str_contains($formsIndexSource, 'error_log(')
        && !str_contains($readOnlyDownloadsFileSource, 'error_log(')
        && !str_contains($subscribeConfirmSource, 'error_log(')
        && !str_contains($unsubscribeSource, 'error_log(')
        && str_contains($boardIndexSource, "koraLog('warning', 'board archive months query failed'")
        && str_contains($contactIndexSource, "koraLog('warning', 'contact submission insert failed'")
        && str_contains($chatIndexSource, "koraLog('warning', 'chat submission insert failed'")
        && str_contains($formsIndexSource, "koraLog('warning', 'public form submission insert failed'")
        && str_contains($readOnlyDownloadsFileSource, "koraLog('warning', 'download count update failed'")
        && str_contains($subscribeConfirmSource, "koraLog('warning', 'newsletter confirmation failed'")
        && str_contains($unsubscribeSource, "koraLog('warning', 'newsletter unsubscribe failed'"),
    'admin composite pages use structured recoverable error logs' => !str_contains($adminContentReferenceSearchSource, 'error_log(')
        && !str_contains($adminFormsSource, 'error_log(')
        && !str_contains($adminStatisticsSource, 'error_log(')
        && str_contains($adminContentReferenceSearchSource, 'function contentReferenceLogSourceError')
        && str_contains($adminContentReferenceSearchSource, "koraLog('warning', 'content reference search source failed'")
        && str_contains($adminFormsSource, "koraLog('warning', 'admin forms overview query failed'")
        && str_contains($adminStatisticsSource, 'function statisticsLogSectionError')
        && str_contains($adminStatisticsSource, "koraLog('warning', 'admin statistics section query failed'"),
    'shared helper recoverable failures use structured logs' => !str_contains($uiSource, 'error_log(')
        && !str_contains($revisionsSource, 'error_log(')
        && !str_contains($widgetsSource, 'error_log(')
        && !str_contains($mediaLibrarySource, 'error_log(')
        && !str_contains($webhooksSource, 'error_log(')
        && !str_contains($themeSource, 'error_log(')
        && !str_contains($presentationSource, 'error_log(')
        && str_contains($uiSource, 'function contentLockLogError')
        && str_contains($uiSource, "koraLog('warning', 'content lock operation failed'")
        && str_contains($revisionsSource, 'function revisionLogError')
        && str_contains($revisionsSource, "koraLog('warning', 'revision operation failed'")
        && str_contains($widgetsSource, 'function widgetLogError')
        && str_contains($widgetsSource, "koraLog('warning', 'widget operation failed'")
        && str_contains($mediaLibrarySource, "koraLog('warning', 'media usage scan failed'")
        && str_contains($webhooksSource, 'function formWebhookSafeLogContext')
        && str_contains($webhooksSource, "koraLog('warning', 'form webhook dispatch failed'")
        && str_contains($themeSource, 'function themeLogFilesystemCleanupFailure')
        && str_contains($themeSource, "koraLog('warning', 'theme filesystem cleanup failed'")
        && str_contains($presentationSource, 'function presentationLogFileDeleteFailure')
        && str_contains($presentationSource, "koraLog('warning', 'presentation file delete failed'")
        && str_contains($presentationSource, "koraLog('warning', 'blog slug redirect save failed'")
        && str_contains($presentationSource, "koraLog('warning', 'path redirect save failed'")
        && !str_contains($webhooksSource, 'responsePreview')
        && !str_contains($webhooksSource, "' body='"),
    'mail failures avoid raw recipient and SMTP response logs' => !str_contains($mailSource, 'error_log(')
        && str_contains($mailSource, 'function mailLogFailure')
        && str_contains($mailSource, "koraLog('warning', 'mail delivery failed'")
        && str_contains($mailSource, 'function mailEmailDomain')
        && str_contains($mailSource, 'function mailSmtpResponseCode')
        && !str_contains($mailSource, 'SMTP said:')
        && !str_contains($mailSource, 'got: {$resp}')
        && !str_contains($mailSource, 'pro {$recipient}')
        && !str_contains($commentsSource, 'sendMail FAILED')
        && !str_contains($passwordResetSource, 'sendMail FAILED')
        && !str_contains($reservationsBookSource, 'sendMail FAILED')
        && !str_contains($reservationsCancelSource, 'sendMail FAILED')
        && !str_contains($reservationsCancelBookingSource, 'sendMail FAILED')
        && !str_contains($adminResBookingSaveSource, 'sendMail FAILED')
        && str_contains($commentsSource, "mailLogFailure('notification_failed'")
        && str_contains($passwordResetSource, "mailLogFailure('notification_failed'")
        && str_contains($reservationsBookSource, "mailLogFailure('notification_failed'")
        && str_contains($reservationsCancelSource, "mailLogFailure('notification_failed'")
        && str_contains($reservationsCancelBookingSource, "mailLogFailure('notification_failed'")
        && str_contains($adminResBookingSaveSource, "mailLogFailure('notification_failed'"),
    'file operation logs avoid raw filesystem paths' => !str_contains($fileDownloadHelperSource, 'error_log(')
        && !str_contains($adminGalleryExportZipSource, 'error_log(')
        && !str_contains($adminBulkSource, 'error_log(')
        && !str_contains($themeSource, 'error_log(')
        && !str_contains($presentationSource, 'error_log(')
        && str_contains($fileDownloadHelperSource, 'function storedFileLogFailure')
        && str_contains($fileDownloadHelperSource, "koraLog('warning', 'stored file response failed'")
        && str_contains($adminGalleryExportZipSource, 'function galleryExportLogMissingFile')
        && str_contains($adminGalleryExportZipSource, "koraLog('warning', 'gallery export source file missing'")
        && str_contains($adminBulkSource, 'function adminBulkLogFileDeleteFailure')
        && str_contains($adminBulkSource, "koraLog('warning', 'admin bulk operation failed'")
        && str_contains($themeSource, 'path_hash')
        && str_contains($themeSource, "themeLogFilesystemCleanupFailure('delete_file'")
        && str_contains($presentationSource, 'path_hash')
        && str_contains($presentationSource, "presentationLogFileDeleteFailure('podcast_cover'")
        && !str_contains($fileDownloadHelperSource, 'sendStoredFileDownload:')
        && !str_contains($adminGalleryExportZipSource, 'soubor nenalezen:')
        && !str_contains($adminBulkSource, 'bulk board:')
        && !str_contains($adminBulkSource, 'bulk download:')
        && !str_contains($adminBulkSource, 'bulk place:'),
];
foreach ($foundationChecks as $label => $ok) {
    if (!$ok) {
        $foundationIssues[] = $label;
    }
}
if ($adminPhpFiles === []) {
    $foundationIssues[] = 'admin formatter coverage could not scan admin PHP files';
} elseif ($missingAdminFormatFiles !== []) {
    $foundationIssues[] = 'php cs fixer missing admin format coverage: ' . implode(', ', $missingAdminFormatFiles);
}
if ($adminPhpFiles !== [] && $missingAdminPhpstanFiles !== []) {
    $foundationIssues[] = 'phpstan missing admin strict coverage: ' . implode(', ', $missingAdminPhpstanFiles);
}

$robotsProbe = fetchUrl($baseUrl . '/robots.txt', '', 0);
if (!str_contains($robotsProbe['status'], '200')) {
    $foundationIssues[] = 'robots.txt did not return 200';
} elseif (!str_contains($robotsProbe['body'], 'Disallow: ' . BASE_URL . '/admin/') || !str_contains($robotsProbe['body'], 'Sitemap: ')) {
    $foundationIssues[] = 'robots.txt is missing admin disallow or sitemap';
}
$robotsPostProbe = postRawUrl($baseUrl . '/robots.txt', '', 'text/plain', '', 0);
$robotsAllowHeaderFound = false;
foreach ($robotsPostProbe['headers'] as $robotsPostHeader) {
    if (stripos($robotsPostHeader, 'Allow:') === 0 && str_contains($robotsPostHeader, 'GET') && str_contains($robotsPostHeader, 'HEAD')) {
        $robotsAllowHeaderFound = true;
        break;
    }
}
if (!str_contains($robotsPostProbe['status'], '405') || !$robotsAllowHeaderFound) {
    $foundationIssues[] = 'robots.txt did not reject unsupported methods with Allow: GET, HEAD';
}

$sitemapPostProbe = postRawUrl($baseUrl . '/sitemap.xml', '', 'text/plain', '', 0);
$sitemapAllowHeaderFound = false;
foreach ($sitemapPostProbe['headers'] as $sitemapPostHeader) {
    if (stripos($sitemapPostHeader, 'Allow:') === 0 && str_contains($sitemapPostHeader, 'GET') && str_contains($sitemapPostHeader, 'HEAD')) {
        $sitemapAllowHeaderFound = true;
        break;
    }
}
if (!str_contains($sitemapPostProbe['status'], '405') || !$sitemapAllowHeaderFound) {
    $foundationIssues[] = 'sitemap.xml did not reject unsupported methods with Allow: GET, HEAD';
}

$feedPostProbe = postRawUrl($baseUrl . '/feed.php', '', 'text/plain', '', 0);
$feedAllowHeaderFound = false;
foreach ($feedPostProbe['headers'] as $feedPostHeader) {
    if (stripos($feedPostHeader, 'Allow:') === 0 && str_contains($feedPostHeader, 'GET') && str_contains($feedPostHeader, 'HEAD')) {
        $feedAllowHeaderFound = true;
        break;
    }
}
if (!str_contains($feedPostProbe['status'], '405') || !$feedAllowHeaderFound) {
    $foundationIssues[] = 'feed.php did not reject unsupported methods with Allow: GET, HEAD';
}

$podcastFeedPostProbe = postRawUrl($baseUrl . '/podcast/feed.php?slug=__missing__', '', 'text/plain', '', 0);
$podcastFeedAllowHeaderFound = false;
foreach ($podcastFeedPostProbe['headers'] as $podcastFeedPostHeader) {
    if (stripos($podcastFeedPostHeader, 'Allow:') === 0 && str_contains($podcastFeedPostHeader, 'GET') && str_contains($podcastFeedPostHeader, 'HEAD')) {
        $podcastFeedAllowHeaderFound = true;
        break;
    }
}
if (!str_contains($podcastFeedPostProbe['status'], '405') || !$podcastFeedAllowHeaderFound) {
    $foundationIssues[] = 'podcast/feed.php did not reject unsupported methods with Allow: GET, HEAD';
}

$eventIcsPostProbe = postRawUrl($baseUrl . '/events/ics.php?slug=__missing__', '', 'text/plain', '', 0);
$eventIcsAllowHeaderFound = false;
foreach ($eventIcsPostProbe['headers'] as $eventIcsPostHeader) {
    if (stripos($eventIcsPostHeader, 'Allow:') === 0 && str_contains($eventIcsPostHeader, 'GET') && str_contains($eventIcsPostHeader, 'HEAD')) {
        $eventIcsAllowHeaderFound = true;
        break;
    }
}
if (!str_contains($eventIcsPostProbe['status'], '405') || !$eventIcsAllowHeaderFound) {
    $foundationIssues[] = 'events/ics.php did not reject unsupported methods with Allow: GET, HEAD';
}

$fileMethodGuardUrls = [
    '/media/file.php?id=0' => 'media/file.php',
    '/media/preview.php?id=0' => 'media/preview.php',
    '/media/thumb.php?id=0' => 'media/thumb.php',
    '/downloads/file.php?id=0' => 'downloads/file.php',
    '/board/file.php?id=0' => 'board/file.php',
    '/gallery/image.php?id=0' => 'gallery/image.php',
    '/places/image.php?id=0' => 'places/image.php',
    '/podcast/audio.php?id=0' => 'podcast/audio.php',
    '/podcast/image.php?id=0' => 'podcast/image.php',
    '/podcast/cover.php?id=0' => 'podcast/cover.php',
];
foreach ($fileMethodGuardUrls as $fileMethodGuardUrl => $fileMethodGuardLabel) {
    $fileMethodGuardProbe = postRawUrl($baseUrl . $fileMethodGuardUrl, '', 'text/plain', '', 0);
    $fileMethodGuardAllowHeaderFound = false;
    foreach ($fileMethodGuardProbe['headers'] as $fileMethodGuardHeader) {
        if (stripos($fileMethodGuardHeader, 'Allow:') === 0 && str_contains($fileMethodGuardHeader, 'GET') && str_contains($fileMethodGuardHeader, 'HEAD')) {
            $fileMethodGuardAllowHeaderFound = true;
            break;
        }
    }
    if (!str_contains($fileMethodGuardProbe['status'], '405') || !$fileMethodGuardAllowHeaderFound) {
        $foundationIssues[] = $fileMethodGuardLabel . ' did not reject unsupported methods with Allow: GET, HEAD';
    }
}

$adminReadOnlyMethodGuardUrls = [
    '/admin/export.php' => 'admin/export.php',
    '/admin/form_submission_file.php?id=0&field=missing' => 'admin/form_submission_file.php',
    '/admin/form_submissions.php?id=0&export=csv' => 'admin/form_submissions.php CSV export',
    '/admin/content_reference_search.php?q=test&type=all' => 'admin/content_reference_search.php',
];
foreach ($adminReadOnlyMethodGuardUrls as $adminReadOnlyMethodGuardUrl => $adminReadOnlyMethodGuardLabel) {
    $adminReadOnlyMethodGuardProbe = postRawUrl($baseUrl . $adminReadOnlyMethodGuardUrl, '', 'text/plain', 'PHPSESSID=' . $auditSessionId, 0);
    $adminReadOnlyMethodGuardAllowHeaderFound = false;
    foreach ($adminReadOnlyMethodGuardProbe['headers'] as $adminReadOnlyMethodGuardHeader) {
        if (stripos($adminReadOnlyMethodGuardHeader, 'Allow:') === 0 && str_contains($adminReadOnlyMethodGuardHeader, 'GET') && str_contains($adminReadOnlyMethodGuardHeader, 'HEAD')) {
            $adminReadOnlyMethodGuardAllowHeaderFound = true;
            break;
        }
    }
    if (!str_contains($adminReadOnlyMethodGuardProbe['status'], '405') || !$adminReadOnlyMethodGuardAllowHeaderFound) {
        $foundationIssues[] = $adminReadOnlyMethodGuardLabel . ' did not reject unsupported methods with Allow: GET, HEAD';
    }
}

$healthProbe = fetchUrl($baseUrl . '/health.php', '', 0);
if (!str_contains($healthProbe['status'], '200')) {
    $foundationIssues[] = 'health.php did not return 200 on healthy local installation';
} else {
    $healthPayload = json_decode($healthProbe['body'], true);
    if (!is_array($healthPayload) || ($healthPayload['status'] ?? '') !== 'ok' || (($healthPayload['checks']['database']['status'] ?? '') !== 'ok')) {
        $foundationIssues[] = 'health.php did not report healthy database status';
    }
    if (!is_array($healthPayload) || !isset($healthPayload['checks']['cron']) || !is_array($healthPayload['checks']['cron'])) {
        $foundationIssues[] = 'health.php did not include cron freshness status';
    }
    if (!is_array($healthPayload) || !isset($healthPayload['checks']['backup']) || !is_array($healthPayload['checks']['backup']) || (($healthPayload['checks']['backup']['status'] ?? '') !== 'unknown' && empty($healthPayload['checks']['backup']['last_backup']))) {
        $foundationIssues[] = 'health.php did not include latest backup timestamp when a backup exists';
    }
    $healthCacheHeader = '';
    foreach ($healthProbe['headers'] as $healthHeader) {
        if (stripos($healthHeader, 'Cache-Control:') === 0) {
            $healthCacheHeader .= ' ' . $healthHeader;
        }
    }
    if (stripos($healthCacheHeader, 'no-store') === false || stripos($healthCacheHeader, 'max-age=0') === false) {
        $foundationIssues[] = 'health.php did not send no-store monitoring cache headers';
    }
    $healthPostProbe = postRawUrl($baseUrl . '/health.php', '{}', 'application/json', '', 0);
    $healthAllowHeaderFound = false;
    foreach ($healthPostProbe['headers'] as $healthPostHeader) {
        if (stripos($healthPostHeader, 'Allow:') === 0 && str_contains($healthPostHeader, 'GET') && str_contains($healthPostHeader, 'HEAD')) {
            $healthAllowHeaderFound = true;
            break;
        }
    }
    if (!str_contains($healthPostProbe['status'], '405') || !$healthAllowHeaderFound) {
        $foundationIssues[] = 'health.php did not reject unsupported methods with Allow: GET, HEAD';
    }
}

$cspReportGetProbe = fetchUrl($baseUrl . '/csp-report.php', '', 0);
$cspReportAllowHeaderFound = false;
$cspReportCacheHeader = '';
foreach ($cspReportGetProbe['headers'] as $cspReportGetHeader) {
    if (stripos($cspReportGetHeader, 'Allow:') === 0 && str_contains($cspReportGetHeader, 'POST')) {
        $cspReportAllowHeaderFound = true;
    }
    if (stripos($cspReportGetHeader, 'Cache-Control:') === 0) {
        $cspReportCacheHeader .= ' ' . $cspReportGetHeader;
    }
}
if (!str_contains($cspReportGetProbe['status'], '405') || !$cspReportAllowHeaderFound || !str_contains($cspReportGetProbe['body'], 'request_id')) {
    $foundationIssues[] = 'csp-report.php did not reject unsupported methods with a traceable JSON response';
}
if (stripos($cspReportCacheHeader, 'no-store') === false || stripos($cspReportCacheHeader, 'max-age=0') === false) {
    $foundationIssues[] = 'csp-report.php did not send no-store cache headers';
}

if ($foundationIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($foundationIssues as $issue) {
        echo '- ' . $issue . "\n";
    }
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

echo "=== install_schema_guard ===\n";
$installSource = (string) file_get_contents(__DIR__ . '/../install.php');
$installTableContains = static function (string $tableName, string $needle) use ($installSource): bool {
    $marker = 'CREATE TABLE IF NOT EXISTS ' . $tableName;
    $start = strpos($installSource, $marker);
    if ($start === false) {
        return false;
    }
    $end = strpos($installSource, "ENGINE=InnoDB", $start);
    if ($end === false) {
        return false;
    }
    $tableSql = substr($installSource, $start, $end - $start);
    return str_contains($tableSql, $needle);
};
$installSchemaChecks = [
    'cms_articles contains unpublish_at' => $installTableContains('cms_articles', 'unpublish_at'),
    'cms_articles contains admin_note' => $installTableContains('cms_articles', 'admin_note'),
    'cms_news contains unpublish_at' => $installTableContains('cms_news', 'unpublish_at'),
    'cms_news contains deleted_at' => $installTableContains('cms_news', 'deleted_at'),
    'cms_pages contains unpublish_at' => $installTableContains('cms_pages', 'unpublish_at'),
    'cms_pages contains admin_note' => $installTableContains('cms_pages', 'admin_note'),
    'cms_pages contains deleted_at' => $installTableContains('cms_pages', 'deleted_at'),
    'cms_events contains event_kind' => $installTableContains('cms_events', 'event_kind'),
    'cms_events contains excerpt' => $installTableContains('cms_events', 'excerpt'),
    'cms_events contains program_note' => $installTableContains('cms_events', 'program_note'),
    'cms_events contains organizer_name' => $installTableContains('cms_events', 'organizer_name'),
    'cms_events contains organizer_email' => $installTableContains('cms_events', 'organizer_email'),
    'cms_events contains registration_url' => $installTableContains('cms_events', 'registration_url'),
    'cms_events contains price_note' => $installTableContains('cms_events', 'price_note'),
    'cms_events contains accessibility_note' => $installTableContains('cms_events', 'accessibility_note'),
    'cms_events contains image_file' => $installTableContains('cms_events', 'image_file'),
    'cms_events contains unpublish_at' => $installTableContains('cms_events', 'unpublish_at'),
    'cms_events contains publish_at' => $installTableContains('cms_events', 'publish_at'),
    'cms_events contains admin_note' => $installTableContains('cms_events', 'admin_note'),
    'cms_events contains deleted_at' => $installTableContains('cms_events', 'deleted_at'),
    'cms_places contains deleted_at' => $installTableContains('cms_places', 'deleted_at'),
    'cms_news contains meta_title' => $installTableContains('cms_news', 'meta_title'),
    'cms_news contains meta_description' => $installTableContains('cms_news', 'meta_description'),
    'cms_polls contains meta_title' => $installTableContains('cms_polls', 'meta_title'),
    'cms_polls contains meta_description' => $installTableContains('cms_polls', 'meta_description'),
    'cms_media contains caption' => $installTableContains('cms_media', 'caption'),
    'cms_media contains credit' => $installTableContains('cms_media', 'credit'),
    'cms_media contains visibility' => $installTableContains('cms_media', 'visibility'),
    'cms_faq_categories contains parent_id' => $installTableContains('cms_faq_categories', 'parent_id'),
    'cms_faqs contains meta_title' => $installTableContains('cms_faqs', 'meta_title'),
    'cms_faqs contains meta_description' => $installTableContains('cms_faqs', 'meta_description'),
    'cms_faqs contains deleted_at' => $installTableContains('cms_faqs', 'deleted_at'),
    'cms_users contains totp_secret' => $installTableContains('cms_users', 'totp_secret'),
    'cms_users contains passkey_credentials' => $installTableContains('cms_users', 'passkey_credentials'),
];
$installSchemaIssues = [];
foreach ($installSchemaChecks as $label => $present) {
    if (!$present) {
        $installSchemaIssues[] = $label;
    }
}
if ($installSchemaIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($installSchemaIssues as $issue) {
        echo '- ' . $issue . "\n";
    }
}

echo "=== migrate_schema_guard ===\n";
$migrateSource = (string) file_get_contents(__DIR__ . '/../migrate.php');
$migrateSchemaChecks = [
    'cms_news.meta_title' => str_contains($migrateSource, 'cms_news.meta_title'),
    'cms_news.meta_description' => str_contains($migrateSource, 'cms_news.meta_description'),
    'cms_polls.meta_title' => str_contains($migrateSource, 'cms_polls.meta_title'),
    'cms_polls.meta_description' => str_contains($migrateSource, 'cms_polls.meta_description'),
    'cms_media.caption' => str_contains($migrateSource, 'cms_media.caption'),
    'cms_media.credit' => str_contains($migrateSource, 'cms_media.credit'),
    'cms_media.visibility' => str_contains($migrateSource, 'cms_media.visibility'),
    'idx_media_visibility' => str_contains($migrateSource, 'idx_media_visibility'),
    'cms_faq_categories.parent_id' => str_contains($migrateSource, 'cms_faq_categories.parent_id'),
    'cms_faqs.meta_title' => str_contains($migrateSource, 'cms_faqs.meta_title'),
    'cms_faqs.meta_description' => str_contains($migrateSource, 'cms_faqs.meta_description'),
    'cms_events.publish_at' => str_contains($migrateSource, 'cms_events.publish_at'),
    'cms_places.deleted_at' => str_contains($migrateSource, 'cms_places.deleted_at'),
    'migrate reruns schema consistency checks even on matching version' => str_contains($migrateSource, 'kontrola konzistence schématu a chybějících sloupců'),
];
$migrateSchemaIssues = [];
foreach ($migrateSchemaChecks as $label => $present) {
    if (!$present) {
        $migrateSchemaIssues[] = $label;
    }
}
if ($migrateSchemaIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($migrateSchemaIssues as $issue) {
        echo '- ' . $issue . "\n";
    }
}

$migrateProbe = fetchUrl($baseUrl . '/migrate.php', '', 0);
echo "=== migrate_guard ===\n";
if (!str_contains($migrateProbe['status'], '302')) {
    echo "- unexpected status: {$migrateProbe['status']}\n";
    $failures++;
} else {
    $migrateLocation = responseLocationHeaderValue($migrateProbe['headers']);
    $migrateLocationPath = (string)(parse_url($migrateLocation, PHP_URL_PATH) ?? '');
    $migrateLocationQuery = (string)(parse_url($migrateLocation, PHP_URL_QUERY) ?? '');
    parse_str($migrateLocationQuery, $migrateLocationParams);
    if ($migrateLocationPath !== BASE_URL . '/admin/login.php') {
        echo "- migrate.php does not redirect anonymous user to /admin/login.php\n";
        $failures++;
    } elseif (($migrateLocationParams['redirect'] ?? '') !== BASE_URL . '/migrate.php') {
        echo "- migrate.php does not preserve safe redirect back to itself after login\n";
        $failures++;
    } else {
        echo "OK\n";
    }
}

echo "=== standalone_system_pages_guardrails ===\n";
$standalonePageIssues = [];
$maintenanceSource = (string)file_get_contents(__DIR__ . '/../maintenance.php');
$standaloneCssPath = dirname(__DIR__) . '/assets/standalone.css';
$standaloneCssSource = is_file($standaloneCssPath) ? (string)file_get_contents($standaloneCssPath) : '';
if (!str_contains($uiSource, 'function standaloneStylesheetTag(): string')
    || !str_contains($uiSource, '/assets/standalone.css')
    || str_contains($uiSource, 'function publicA11yStyleTag(): string')) {
    $standalonePageIssues[] = 'shared UI helpers are missing standalone stylesheet helper or still expose inline public a11y style tag';
}
foreach ([
    '.standalone-page',
    '.skip-link:focus',
    '.standalone-page--install',
    '.migrate-warning',
    '.migrate-log',
    '.maintenance-box',
] as $standaloneCssFragment) {
    if (!str_contains($standaloneCssSource, $standaloneCssFragment)) {
        $standalonePageIssues[] = 'standalone stylesheet is missing fragment: ' . $standaloneCssFragment;
    }
}
foreach ([
    'install.php' => [$installSource, 'standalone-page--install'],
    'migrate.php' => [$migrateSource, 'standalone-page--migrate'],
    'maintenance.php' => [$maintenanceSource, 'standalone-page--maintenance'],
] as $standalonePageName => [$standalonePageSource, $standaloneBodyClass]) {
    if (!str_contains($standalonePageSource, 'standaloneStylesheetTag()')
        || !str_contains($standalonePageSource, $standaloneBodyClass)) {
        $standalonePageIssues[] = $standalonePageName . ' is missing shared standalone stylesheet or body class';
    }
    if (str_contains($standalonePageSource, '<style') || str_contains($standalonePageSource, 'publicA11yStyleTag()')) {
        $standalonePageIssues[] = $standalonePageName . ' still renders local inline styles';
    }
}
if (!str_contains($maintenanceSource, '<a href="#obsah" class="skip-link">')
    || !str_contains($maintenanceSource, '<main id="obsah" class="maintenance-box">')) {
    $standalonePageIssues[] = 'maintenance.php is missing skip link or main content target';
}
if ($standalonePageIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($standalonePageIssues as $standalonePageIssue) {
        echo '- ' . $standalonePageIssue . "\n";
    }
}

echo "=== admin_login_redirect_guardrails ===\n";
$adminLoginRedirectIssues = [];
$adminAuthSource = (string)file_get_contents(dirname(__DIR__) . '/auth.php');
$adminLoginSource = (string)file_get_contents(dirname(__DIR__) . '/admin/login.php');
$adminLogin2faSource = (string)file_get_contents(dirname(__DIR__) . '/admin/login_2fa.php');
$adminLoginCssPath = dirname(__DIR__) . '/admin/assets/login.css';
$adminLoginCssSource = is_file($adminLoginCssPath) ? (string)file_get_contents($adminLoginCssPath) : '';
$publicLoginSource = (string)file_get_contents(dirname(__DIR__) . '/public_login.php');
$resetPasswordSource = (string)file_get_contents(dirname(__DIR__) . '/reset_password.php');
$adminHttpIntegrationSource = is_file(dirname(__DIR__) . '/build/http_integration.php')
    ? (string)file_get_contents(dirname(__DIR__) . '/build/http_integration.php')
    : '';
foreach ([
    '$requestUri = (string)($_SERVER[\'REQUEST_URI\'] ?? \'\');',
    'adminLoginRedirectTarget($currentTarget, \'\')',
    'redirect=',
] as $authRedirectFragment) {
    if (!str_contains($adminAuthSource, $authRedirectFragment)) {
        $adminLoginRedirectIssues[] = 'auth flow is missing admin redirect fragment: ' . $authRedirectFragment;
    }
}
foreach ([
    "adminLoginRedirectTarget(trim(\$_GET['redirect'] ?? \$_POST['redirect'] ?? ''), BASE_URL . '/admin/index.php')",
    '$cancel2fa = ($_GET[\'cancel_2fa\'] ?? \'\') === \'1\';',
    "\$_SESSION['2fa_pending_redirect'] = \$redirect;",
    "\$_SESSION['2fa_pending_redirect']",
    'name="redirect"',
    "header('Location: ' . \$redirect);",
    'adminLoginStylesheetTag()',
] as $adminLoginFragment) {
    if (!str_contains($adminLoginSource, $adminLoginFragment)) {
        $adminLoginRedirectIssues[] = 'admin login is missing redirect fragment: ' . $adminLoginFragment;
    }
}
foreach ([
    "adminLoginRedirectTarget((string)(\$_SESSION['2fa_pending_redirect'] ?? ''), BASE_URL . '/admin/index.php')",
    'cancel_2fa=1&redirect=',
    "\$_SESSION['2fa_pending_redirect']",
    "header('Location: ' . \$redirect);",
    'class="totp-code-input"',
    'class="login-secondary-action"',
    'adminLoginStylesheetTag()',
] as $adminLogin2faFragment) {
    if (!str_contains($adminLogin2faSource, $adminLogin2faFragment)) {
        $adminLoginRedirectIssues[] = 'admin 2FA is missing login fragment: ' . $adminLogin2faFragment;
    }
}
if (!str_contains($uiSource, 'function adminLoginStylesheetTag(): string')
    || !str_contains($uiSource, "/admin/assets/login.css")
    || str_contains($uiSource, 'function adminLoginStyleTag(): string')) {
    $adminLoginRedirectIssues[] = 'shared UI helper is missing linked admin login stylesheet helper or still exposes inline style tag';
}
foreach ([
    '.totp-code-input',
    'letter-spacing: 0.3rem',
    '.login-secondary-action',
    '.skip-link:focus',
] as $adminLoginCssFragment) {
    if (!str_contains($adminLoginCssSource, $adminLoginCssFragment)) {
        $adminLoginRedirectIssues[] = 'admin login stylesheet is missing fragment: ' . $adminLoginCssFragment;
    }
}
foreach ([
    'admin login' => $adminLoginSource,
    'admin 2FA login' => $adminLogin2faSource,
] as $adminLoginStyleLabel => $adminLoginStyleSource) {
    if (str_contains($adminLoginStyleSource, '<style') || str_contains($adminLoginStyleSource, 'style=')) {
        $adminLoginRedirectIssues[] = $adminLoginStyleLabel . ' still contains local style blocks or inline style attributes';
    }
}
foreach ([
    'style="font-size:1.5rem;text-align:center;letter-spacing:.3rem"',
    'style="margin-top:2rem"',
] as $adminLogin2faInlineStyleFragment) {
    if (str_contains($adminLogin2faSource, $adminLogin2faInlineStyleFragment)) {
        $adminLoginRedirectIssues[] = 'admin 2FA still contains inline style fragment: ' . $adminLogin2faInlineStyleFragment;
    }
}
if ($adminHttpIntegrationSource === '') {
    $adminLoginRedirectIssues[] = 'build/http_integration.php is missing for admin login redirect coverage';
} else {
    foreach ([
        "httpIntegrationPrintResult('admin_login_redirect_http'",
        "httpIntegrationPrintResult('migrate_login_redirect_http'",
    ] as $httpLoginRedirectFragment) {
        if (!str_contains($adminHttpIntegrationSource, $httpLoginRedirectFragment)) {
            $adminLoginRedirectIssues[] = 'http integration is missing admin redirect scenario: ' . $httpLoginRedirectFragment;
        }
    }
}
if ($adminLoginRedirectIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($adminLoginRedirectIssues as $issue) {
        echo '- ' . $issue . "\n";
    }
}

echo "=== auth_rate_limit_guardrails ===\n";
$authRateLimitIssues = [];
foreach ([
    'function rateLimitKey(string $action, string $identifier): string',
    'function rateLimitApply(string $key, int $max, int $window, ?callable $onExceeded = null): void',
    'function rateLimit(string $action, int $max = 10, int $window = 60, ?callable $onExceeded = null): void',
    'function rateLimitSubject(string $action, string $subject, int $max = 10, int $window = 60, ?callable $onExceeded = null): void',
    "rateLimitKey(\$action, \$ip)",
    "rateLimitKey(\$action, 'subject:' . \$normalizedSubject)",
    '$onExceeded();',
] as $authRateLimitFragment) {
    if (!str_contains($adminAuthSource, $authRateLimitFragment)) {
        $authRateLimitIssues[] = 'auth.php is missing rate-limit fragment: ' . $authRateLimitFragment;
    }
}
foreach ([
    'admin/login.php' => [$adminLoginSource, "rateLimit('login', 5, 300)", "rateLimitSubject('login_email', \$inputEmail, 5, 900)"],
    'admin/login_2fa.php' => [$adminLogin2faSource, "rateLimit('login_2fa', 5, 300)", "rateLimitSubject('login_2fa_user', (string)\$userId, 5, 300)"],
    'public_login.php' => [$publicLoginSource, "rateLimit('public_login', 5, 300)", "rateLimitSubject('public_login_email', \$email, 5, 900)"],
    'reset_password.php email' => [$resetPasswordSource, "rateLimit('password_reset', 3, 300)", "rateLimitSubject('password_reset_email', \$email, 3, 900)"],
    'reset_password.php token' => [$resetPasswordSource, "rateLimitSubject('password_reset_token', \$token, 5, 300)", "verifyCsrf()"],
] as $rateLimitSourceLabel => [$rateLimitSource, $ipFragment, $subjectFragment]) {
    if (!str_contains($rateLimitSource, $ipFragment) || !str_contains($rateLimitSource, $subjectFragment)) {
        $authRateLimitIssues[] = $rateLimitSourceLabel . ' is missing combined IP and subject rate limiting';
    }
}
if (!str_contains($cspReportSource, "rateLimit('csp_report', 120, 60") || !str_contains($cspReportSource, 'function cspReportRateLimitExceeded')) {
    $authRateLimitIssues[] = 'csp-report.php is missing dedicated JSON rate limiting';
}
if ($authRateLimitIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($authRateLimitIssues as $issue) {
        echo '- ' . $issue . "\n";
    }
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
        'maintenance_text' => getSetting('maintenance_text', ''),
        'cookie_consent_text' => getSetting('cookie_consent_text', ''),
        'og_image_default' => getSetting('og_image_default', ''),
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
        $baseUrl . '/admin/settings_save.php',
        $settingsPayload,
        'PHPSESSID=' . $auditSessionId,
        0
    );

    if (!str_contains($customProfileResult['status'], '302')) {
        $customProfileIssues[] = 'custom profile settings save did not return PRG redirect';
    }
    if (!responseHasLocationHeader($customProfileResult['headers'], '/admin/settings.php', $baseUrl)) {
        $customProfileIssues[] = 'custom profile settings save did not redirect back to settings page';
    }

    $customProfileFollowup = fetchUrl(
        $baseUrl . '/admin/settings.php',
        'PHPSESSID=' . $auditSessionId,
        0
    );

    if (!str_contains($customProfileFollowup['body'], 'Vlastní profil zůstal bez zásahu do stávajících modulů a vzhledu.')) {
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
$activeThemeSettingDefinitions = themeSettingDefinitions($originalActiveTheme);
$activeThemeSupportsHomepageComposer = isset(
    $activeThemeSettingDefinitions['home_layout'],
    $activeThemeSettingDefinitions['home_featured_module'],
    $activeThemeSettingDefinitions['home_cta_visibility']
);
echo "=== theme_home_composer ===\n";
try {
    if ($runtimeAuditHomepageUsesWidgets || !$activeThemeSupportsHomepageComposer) {
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
    if (
        !$runtimeAuditHomepageUsesWidgets
        && isset($activeThemeSettingDefinitions['home_layout'])
        && !str_contains($customizationProbe['body'], 'page-stack--home-editorial')
    ) {
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

$smtpHost   = defined('SMTP_HOST') ? SMTP_HOST : (ini_get('SMTP') ?: 'localhost');
$smtpPort   = defined('SMTP_PORT') ? (int) SMTP_PORT : (int)(ini_get('smtp_port') ?: 25);
$smtpSecure = defined('SMTP_SECURE') ? SMTP_SECURE : '';
$smtpUser   = defined('SMTP_USER') ? SMTP_USER : '';
$smtpPass   = defined('SMTP_PASS') ? SMTP_PASS : '';
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
    $smtpConnectivitySkipped = false;
    if (!$smtpSocket) {
        $smtpConnectivitySkipped = true;
        echo "SKIP (SMTP server {$smtpTarget}:{$smtpPort} není z tohoto prostředí dosažitelný: {$smtpErrstr})\n";
    } else {
        $smtpGreeting = '';
        while (($smtpLine = @fgets($smtpSocket, 512)) !== false) {
            $smtpGreeting .= $smtpLine;
            if (isset($smtpLine[3]) && $smtpLine[3] === ' ') {
                break;
            }
        }
        if (!str_starts_with(trim($smtpGreeting), '220')) {
            $smtpIssues[] = 'unexpected SMTP greeting: ' . trim($smtpGreeting);
        }

        fwrite($smtpSocket, "EHLO localhost\r\n");
        $smtpEhlo = '';
        while (($smtpLine = @fgets($smtpSocket, 512)) !== false) {
            $smtpEhlo .= $smtpLine;
            if (isset($smtpLine[3]) && $smtpLine[3] === ' ') {
                break;
            }
        }
        if (!str_starts_with(trim($smtpEhlo), '250')) {
            $smtpIssues[] = 'EHLO failed: ' . trim($smtpEhlo);
        }

        if ($smtpSecure === 'tls') {
            fwrite($smtpSocket, "STARTTLS\r\n");
            $smtpTls = '';
            while (($smtpLine = @fgets($smtpSocket, 512)) !== false) {
                $smtpTls .= $smtpLine;
                if (isset($smtpLine[3]) && $smtpLine[3] === ' ') {
                    break;
                }
            }
            if (!str_starts_with(trim($smtpTls), '220')) {
                $smtpIssues[] = 'STARTTLS not supported: ' . trim($smtpTls);
            } else {
                $smtpCrypto = @stream_socket_enable_crypto(
                    $smtpSocket,
                    true,
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
                    if (isset($smtpLine[3]) && $smtpLine[3] === ' ') {
                        break;
                    }
                }
            }
            fwrite($smtpSocket, "AUTH LOGIN\r\n");
            $smtpAuth = '';
            while (($smtpLine = @fgets($smtpSocket, 512)) !== false) {
                $smtpAuth .= $smtpLine;
                if (isset($smtpLine[3]) && $smtpLine[3] === ' ') {
                    break;
                }
            }
            if (!str_starts_with(trim($smtpAuth), '334')) {
                $smtpIssues[] = 'AUTH LOGIN not supported: ' . trim($smtpAuth);
            } else {
                fwrite($smtpSocket, base64_encode($smtpUser) . "\r\n");
                while (($smtpLine = @fgets($smtpSocket, 512)) !== false) {
                    if (isset($smtpLine[3]) && $smtpLine[3] === ' ') {
                        break;
                    }
                }
                fwrite($smtpSocket, base64_encode($smtpPass) . "\r\n");
                $smtpLogin = '';
                while (($smtpLine = @fgets($smtpSocket, 512)) !== false) {
                    $smtpLogin .= $smtpLine;
                    if (isset($smtpLine[3]) && $smtpLine[3] === ' ') {
                        break;
                    }
                }
                if (!str_starts_with(trim($smtpLogin), '235')) {
                    $smtpIssues[] = 'AUTH LOGIN credentials rejected: ' . trim($smtpLogin);
                }
            }
        }

        fwrite($smtpSocket, "QUIT\r\n");
        fclose($smtpSocket);
    }

    if (!$smtpConnectivitySkipped && !$contactEmailConfigured) {
        $smtpIssues[] = 'contact_email is not configured (current: ' . ($contactEmail ?: 'empty') . ')';
    }

    if ($smtpConnectivitySkipped) {
        // Výslovně přeskočeno výše.
    } elseif ($smtpIssues === []) {
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
    if (!is_file($sendMailPath)) {
        continue;
    }
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

echo "=== sendmail_header_guardrails ===\n";
$sendMailHeaderIssues = [];
$mailLibrarySource = (string)file_get_contents(dirname(__DIR__) . '/lib/mail.php');
$contactModuleSource = (string)file_get_contents(dirname(__DIR__) . '/contact/index.php');

if (!str_contains($mailLibrarySource, 'Message-ID:')) {
    $sendMailHeaderIssues[] = 'sendMail is missing Message-ID header';
}
if (!str_contains($mailLibrarySource, 'Date: ')) {
    $sendMailHeaderIssues[] = 'sendMail is missing Date header';
}
if (!str_contains($mailLibrarySource, 'Content-Transfer-Encoding:')) {
    $sendMailHeaderIssues[] = 'sendMail is missing explicit Content-Transfer-Encoding header';
}
if (!str_contains($mailLibrarySource, 'quoted_printable_encode')) {
    $sendMailHeaderIssues[] = 'sendMail body encoding no longer uses quoted-printable fallback logic';
}
if (!str_contains($mailLibrarySource, 'function mailEncodeHeaderValue')) {
    $sendMailHeaderIssues[] = 'sendMail is missing UTF-8 header encoding helper';
}
if (!str_contains($contactModuleSource, "Kontakt: ' . \$subject")) {
    $sendMailHeaderIssues[] = 'contact notifications no longer prefix subject with Kontakt';
}
if (!str_contains($contactModuleSource, "'reply_to' => \$from")) {
    $sendMailHeaderIssues[] = 'contact notifications are missing visitor reply-to';
}

if ($sendMailHeaderIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($sendMailHeaderIssues as $sendMailHeaderIssue) {
        echo '- ' . $sendMailHeaderIssue . "\n";
    }
}

echo "=== content_security_policy_guardrails ===\n";
$contentSecurityPolicyIssues = [];
$authSource = (string)file_get_contents(dirname(__DIR__) . '/auth.php');
if (!str_contains($authSource, "media-src 'self' https: data: blob:;")) {
    $contentSecurityPolicyIssues[] = 'auth CSP is missing media-src allowance for external media embeds';
}
if (!str_contains($authSource, "frame-src 'self' https:;")) {
    $contentSecurityPolicyIssues[] = 'auth CSP is missing frame-src allowance for external iframe embeds';
}
if (!str_contains($authSource, "frame-ancestors 'none'")) {
    $contentSecurityPolicyIssues[] = 'auth CSP no longer protects pages against third-party framing';
}
if (!str_contains($authSource, 'style-src-elem \'self\' \'nonce-{$_CSP_NONCE}\' \'unsafe-inline\'{$_CSP_EXTRA_STYLE};') || !str_contains($authSource, "style-src-attr 'unsafe-inline'")) {
    $contentSecurityPolicyIssues[] = 'auth CSP report policy would collect noisy inline style reports';
}
foreach ([
    'script-src \'self\' \'nonce-{$_CSP_NONCE}\' \'unsafe-inline\'{$_CSP_EXTRA_SCRIPT}',
    'font-src \'self\'{$_CSP_EXTRA_FONT}',
    'https://www.googletagmanager.com',
    'https://cdn.jsdelivr.net',
    'https://cdn.quilljs.com',
    'https://www.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com',
] as $cspTrustedSourceFragment) {
    if (!str_contains($authSource, $cspTrustedSourceFragment)) {
        $contentSecurityPolicyIssues[] = 'auth CSP is missing trusted source fragment: ' . $cspTrustedSourceFragment;
    }
}
if (!str_contains($presentationSource, 'function structuredDataScript(array $data, string $indent = \'\'): string')
    || !str_contains($presentationSource, '<script type="application/ld+json"\' . $nonce')
    || !str_contains($presentationSource, 'JSON_INVALID_UTF8_SUBSTITUTE')) {
    $contentSecurityPolicyIssues[] = 'presentation structured data is missing nonce-backed JSON-LD helper';
}
if (preg_match('/return\s+[\'"]\s*<script type="application\/ld\+json">/', $presentationSource) === 1
    || str_contains($presentationSource, "return '  <script type=\"application/ld+json\">")) {
    $contentSecurityPolicyIssues[] = 'presentation structured data still renders raw non-nonced JSON-LD script';
}
if (substr_count($presentationSource, 'return structuredDataScript(') < 9) {
    $contentSecurityPolicyIssues[] = 'not all structured data helpers use structuredDataScript';
}
if (!str_contains($authSource, 'Content-Security-Policy-Report-Only') || !str_contains($authSource, 'report-uri ' . "' . BASE_URL . '" . '/csp-report.php')) {
    $contentSecurityPolicyIssues[] = 'auth CSP is missing report-only collection endpoint';
}
foreach (['function cspReportBody', 'function cspReportEntry', 'function cspReportIsNoisyAllowedInlineStyle', "rateLimit('csp_report', 120, 60", "koraStoragePath('logs')", "csp_reports-' . date('Y-m-d') . '.jsonl", 'JSON_INVALID_UTF8_SUBSTITUTE'] as $cspReportSnippet) {
    if (!str_contains($cspReportSource, $cspReportSnippet)) {
        $contentSecurityPolicyIssues[] = 'csp-report.php is missing guardrail snippet: ' . $cspReportSnippet;
    }
}
if (str_contains($cspReportSource, "['path' => \$logPath]") || str_contains($cspReportSource, "['path' => \$logDirectory]")) {
    $contentSecurityPolicyIssues[] = 'csp-report.php logs raw filesystem paths on write failures';
}
$publicHeadSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/partials/head.php');
if (!str_contains($publicHeadSource, '<script async nonce="<?= cspNonce() ?>" src="https://www.googletagmanager.com/gtag/js?id=')
    || !str_contains($publicHeadSource, "s.nonce='<?= h(cspNonce()) ?>';")) {
    $contentSecurityPolicyIssues[] = 'public head GA scripts are missing CSP nonce support';
}
if (!str_contains($themeSource, '$nonce = function_exists(\'cspNonce\') ? cspNonce() : \'\';')
    || !str_contains($themeSource, '<style{$nonceAttribute}>:root{')) {
    $contentSecurityPolicyIssues[] = 'theme CSS variables style tag is missing CSP nonce support';
}
if (!str_contains($uiSource, "s.nonce=' . json_encode(\$nonce) . ';")) {
    $contentSecurityPolicyIssues[] = 'cookie banner delayed GA loader is missing CSP nonce support';
}
if (str_contains($uiSource, 'style=') || str_contains($uiSource, '.style') || str_contains($uiSource, 'style.cssText')) {
    $contentSecurityPolicyIssues[] = 'shared UI helpers still contain inline style markup or JS style mutations';
}
if (!str_contains($uiSource, 'role="navigation" aria-labelledby="public-admin-bar-heading"')
    || !str_contains($uiSource, '<h2 id="public-admin-bar-heading" class="sr-only">Administrace webu</h2>')
    || str_contains($uiSource, 'role="navigation" aria-label="Administrace webu"')) {
    $contentSecurityPolicyIssues[] = 'public admin bar is missing heading-backed navigation semantics';
}
foreach ([
    'class="cookie-banner" hidden',
    'b.hidden=false',
    'b.hidden=true',
    'class="public-admin-bar"',
    'public-admin-bar__link--edit',
    'admin-sort-controls',
    'admin-sort-control',
    'classList.add("admin-sort-item--dragging")',
    'classList.remove("admin-sort-item--dragging")',
] as $sharedUiClassFragment) {
    if (!str_contains($uiSource, $sharedUiClassFragment)) {
        $contentSecurityPolicyIssues[] = 'shared UI helper is missing class-based CSP-safe fragment: ' . $sharedUiClassFragment;
    }
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/admin')) as $cspAdminFile) {
    if (!$cspAdminFile instanceof SplFileInfo || !$cspAdminFile->isFile() || $cspAdminFile->getExtension() !== 'php') {
        continue;
    }
    $cspAdminSource = (string)file_get_contents($cspAdminFile->getPathname());
    if (preg_match('/<script\s+(?![^>]*\bnonce=)[^>]*\bsrc="https:\/\/cdn\.(?:jsdelivr|quilljs)\.com/i', $cspAdminSource) === 1) {
        $contentSecurityPolicyIssues[] = 'admin external editor script is missing nonce: ' . $cspAdminFile->getFilename();
    }
}
$contentSecurityPolicyProbe = fetchUrl($baseUrl . '/', '', 0);
$contentSecurityPolicyHeaderFound = false;
$contentSecurityPolicyReportOnlyHeaderFound = false;
if (preg_match('/<style\s+nonce="[^"]+">:root\{--/i', $contentSecurityPolicyProbe['body']) !== 1) {
    $contentSecurityPolicyIssues[] = 'public theme CSS variables are not rendered with a nonce-backed style tag';
}
foreach ($contentSecurityPolicyProbe['headers'] as $securityHeader) {
    $normalizedHeader = strtolower((string)$securityHeader);
    if (str_starts_with($normalizedHeader, 'content-security-policy-report-only:')) {
        $contentSecurityPolicyReportOnlyHeaderFound = true;
        if (!str_contains($normalizedHeader, 'report-uri ' . BASE_URL . '/csp-report.php')) {
            $contentSecurityPolicyIssues[] = 'public CSP report-only header is missing csp-report.php report-uri';
        }
        if (!str_contains($normalizedHeader, "style-src-attr 'unsafe-inline'")) {
            $contentSecurityPolicyIssues[] = 'public CSP report-only header would collect noisy inline style attribute reports';
        }
        continue;
    }
    if (!str_starts_with($normalizedHeader, 'content-security-policy:')) {
        continue;
    }
    $contentSecurityPolicyHeaderFound = true;
    if (!str_contains($normalizedHeader, "media-src 'self' https: data: blob:")) {
        $contentSecurityPolicyIssues[] = 'public response header is missing media-src allowance for external media embeds';
    }
    if (!str_contains($normalizedHeader, "frame-src 'self' https:")) {
        $contentSecurityPolicyIssues[] = 'public response header is missing frame-src allowance for external iframe embeds';
    }
    if (!str_contains($normalizedHeader, "frame-ancestors 'none'")) {
        $contentSecurityPolicyIssues[] = 'public response header no longer protects against third-party framing';
    }
    foreach (['https://www.googletagmanager.com', 'https://cdn.jsdelivr.net', 'https://cdn.quilljs.com'] as $expectedCspSource) {
        if (!str_contains($normalizedHeader, $expectedCspSource)) {
            $contentSecurityPolicyIssues[] = 'public response header is missing trusted script/style source: ' . $expectedCspSource;
        }
    }
    if (!str_contains($normalizedHeader, "connect-src 'self' https://www.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com")) {
        $contentSecurityPolicyIssues[] = 'public response header is missing GA connect-src allowance';
    }
}
if (!$contentSecurityPolicyHeaderFound) {
    $contentSecurityPolicyIssues[] = 'public response is missing Content-Security-Policy header';
}
if (!$contentSecurityPolicyReportOnlyHeaderFound) {
    $contentSecurityPolicyIssues[] = 'public response is missing Content-Security-Policy-Report-Only header';
}

if ($contentSecurityPolicyIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($contentSecurityPolicyIssues as $contentSecurityPolicyIssue) {
        echo '- ' . $contentSecurityPolicyIssue . "\n";
    }
}

echo "=== blog_admin_guardrails ===\n";
$blogAdminIssues = [];
$blogLayoutSource = (string)file_get_contents(dirname(__DIR__) . '/admin/layout.php');
$blogListSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog.php');
$blogFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_form.php');
$blogCatsSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_cats.php');
$blogTagsSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_tags.php');
$blogDeleteSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_blog_delete.php');
$blogBulkSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_bulk.php');
$blogTransferSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_transfer.php');
$blogMembersSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_members.php');
$blogSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_save.php');
$blogsAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blogs.php');
$usersAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/users.php');
$blogExportSource = (string)file_get_contents(dirname(__DIR__) . '/admin/export.php');
$blogImportSource = (string)file_get_contents(dirname(__DIR__) . '/admin/import.php');
$blogWpImportSource = (string)file_get_contents(dirname(__DIR__) . '/admin/wp_import.php');
$blogEstrankyImportSource = (string)file_get_contents(dirname(__DIR__) . '/admin/estranky_import.php');
$blogIndexControllerSource = (string)file_get_contents(dirname(__DIR__) . '/blog/index.php');
$blogArticleControllerSource = (string)file_get_contents(dirname(__DIR__) . '/blog/article.php');
$blogIndexViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/blog-index.php');
$blogFeedSource = (string)file_get_contents(dirname(__DIR__) . '/feed.php');
$blogRouterSource = (string)file_get_contents(dirname(__DIR__) . '/blog_router.php');
$blogWidgetAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/widgets.php');
$blogWidgetSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/widget_save.php');
$blogWidgetLibSource = (string)file_get_contents(dirname(__DIR__) . '/lib/widgets.php');
$blogPresentationSource = (string)file_get_contents(dirname(__DIR__) . '/lib/presentation.php');
$blogInstallSource = (string)file_get_contents(dirname(__DIR__) . '/install.php');
$blogMigrateSource = (string)file_get_contents(dirname(__DIR__) . '/migrate.php');
$dbSource = (string)file_get_contents(dirname(__DIR__) . '/db.php');

if (!str_contains($blogLayoutSource, 'canCurrentUserManageAnyBlogTaxonomies() && hasAnyBlogs()')) {
    $blogAdminIssues[] = 'admin menu still exposes blog taxonomies without existing blog guard';
}
if (!str_contains($blogLayoutSource, 'blog_members.php')) {
    $blogAdminIssues[] = 'admin menu is missing blog team navigation';
}
if (!str_contains($blogListSource, 'Vytvořit první blog')) {
    $blogAdminIssues[] = 'blog list is missing no-blog guidance';
}
if (!str_contains($blogListSource, 'getWritableBlogsForUser()')) {
    $blogAdminIssues[] = 'blog list is missing writable-blog filtering for own-only users';
}
if (!str_contains($blogListSource, "\$message === 'no_blog_access'")) {
    $blogAdminIssues[] = 'blog list is missing no-blog-access feedback';
}
if (!str_contains($blogListSource, 'Tým blogu')) {
    $blogAdminIssues[] = 'blog list is missing blog team quick link';
}
if (!str_contains($blogListSource, 'Přesunout do jiného blogu') || !str_contains($blogListSource, 'value="move"')) {
    $blogAdminIssues[] = 'blog list is missing bulk move action';
}
if (!str_contains($blogListSource, '<?php if ($multiBlog): ?>')) {
    $blogAdminIssues[] = 'blog list is missing multiblog-only guard around bulk move action';
}
if (!str_contains($blogFormSource, "blog.php?msg=no_blog")) {
    $blogAdminIssues[] = 'article form no longer redirects back to blog list when no blog exists';
}
if (!str_contains($blogFormSource, 'const noCategoryLabel =')) {
    $blogAdminIssues[] = 'article form is missing shared no-category label for multiblog category switching';
}
if (!str_contains($blogFormSource, 'categorySelect.options[0].textContent = noCategoryLabel;')) {
    $blogAdminIssues[] = 'article form no longer normalizes initial no-category label from the shared PHP value';
}
if (!str_contains($blogFormSource, "categoryMarkup.push('<option value=\"\">' + noCategoryLabel + '</option>');")) {
    $blogAdminIssues[] = 'article form no longer rebuilds empty category option from the shared PHP label';
}
if (!str_contains($blogFormSource, 'name="category_selection_mode"') || !str_contains($blogFormSource, 'name="tag_selection_mode"')) {
    $blogAdminIssues[] = 'article form is missing taxonomy selection mode tracking for blog changes';
}
if (!str_contains($blogFormSource, 'resolveAutoSelections') || !str_contains($blogFormSource, 'normalizeTaxonomyName')) {
    $blogAdminIssues[] = 'article form is missing automatic taxonomy carry-over when switching blogs';
}
if (!str_contains($blogFormSource, 'blog-taxonomy-transfer-help')) {
    $blogAdminIssues[] = 'article form is missing help text for taxonomy carry-over when changing blogs';
}
if (!str_contains($blogFormSource, 'blog-missing-category-group') || !str_contains($blogFormSource, 'blog-missing-tags-group') || !str_contains($blogFormSource, 'name="missing_category_action"') || !str_contains($blogFormSource, 'name="missing_tags_action"')) {
    $blogAdminIssues[] = 'article form is missing missing-taxonomy controls for single-article blog changes';
}
if (!str_contains($blogFormSource, 'Vytvořit chybějící kategorii v cílovém blogu') || !str_contains($blogFormSource, 'Vytvořit chybějící štítky v cílovém blogu')) {
    $blogAdminIssues[] = 'article form is missing create options for missing target taxonomies';
}
if (!str_contains($blogFormSource, 'can_manage_taxonomies') || !str_contains($blogFormSource, 'updateMissingTaxonomyChoices')) {
    $blogAdminIssues[] = 'article form is missing per-blog taxonomy create gating for missing taxonomies';
}
if (!str_contains($blogFormSource, 'Uložený blog článku:') || !str_contains($blogFormSource, 'Po uložení bude článek přesunut do blogu')) {
    $blogAdminIssues[] = 'article form no longer distinguishes saved blog from target blog after a blog change';
}
if (str_contains($blogFormSource, 'Článek právě patří do blogu')) {
    $blogAdminIssues[] = 'article form still uses misleading copy that says the article already belongs to the selected target blog';
}
if (!str_contains($blogFormSource, 'comments_default')) {
    $blogAdminIssues[] = 'article form is missing per-blog default comments metadata';
}
if (!str_contains($blogFormSource, 'name="is_featured_in_blog"')) {
    $blogAdminIssues[] = 'article form is missing per-blog featured article toggle';
}
if (!str_contains($blogFormSource, 'canCurrentUserManageBlogTaxonomies($currentBlogId)')) {
    $blogAdminIssues[] = 'article form is missing taxonomy management guard for multiblog links';
}
if (str_contains($blogFormSource, '<style') || str_contains($blogFormSource, 'style=') || str_contains($blogFormSource, '.style')) {
    $blogAdminIssues[] = 'article form still contains local style blocks, inline style attributes or JS style mutations';
}
if (str_contains($blogFormSource, 'error_log(')
    || !str_contains($blogFormSource, "koraLog('warning', 'admin article form taxonomy query failed'")) {
    $blogAdminIssues[] = 'article form taxonomy fallback no longer uses structured recoverable logging';
}
foreach ([
    'class="admin-warning-box"',
    'class="admin-inline-form"',
    'class="blog-form-missing-taxonomy"',
    'class="blog-form-fieldset"',
    'class="blog-form-tag-label"',
    'class="button-row blog-form-actions"',
    'blog-form-wysiwyg-wrapper',
    'textarea.hidden = true',
] as $blogFormStyleFragment) {
    if (!str_contains($blogFormSource, $blogFormStyleFragment)) {
        $blogAdminIssues[] = 'article form is missing shared style fragment: ' . $blogFormStyleFragment;
    }
}
if (!str_contains($blogCatsSource, 'if (!hasAnyBlogs())')) {
    $blogAdminIssues[] = 'blog categories page is missing no-blog redirect guard';
}
if (!str_contains($blogCatsSource, 'getTaxonomyManagedBlogsForUser()')) {
    $blogAdminIssues[] = 'blog categories page is missing manageable-blog filtering';
}
if (str_contains($blogCatsSource, 'style=')) {
    $blogAdminIssues[] = 'blog categories page still contains inline style attributes';
}
foreach ([
    'button-row admin-stack-sm',
    'class="admin-select-sm"',
    'button-row button-row--baseline',
    'class="admin-input-auto"',
] as $blogCatsUtilityFragment) {
    if (!str_contains($blogCatsSource, $blogCatsUtilityFragment)) {
        $blogAdminIssues[] = 'blog categories page is missing utility class fragment: ' . $blogCatsUtilityFragment;
    }
}
if (!str_contains($blogTagsSource, 'if (!hasAnyBlogs())')) {
    $blogAdminIssues[] = 'blog tags page is missing no-blog redirect guard';
}
if (!str_contains($blogTagsSource, 'getTaxonomyManagedBlogsForUser()')) {
    $blogAdminIssues[] = 'blog tags page is missing manageable-blog filtering';
}
if (str_contains($blogTagsSource, 'style=')) {
    $blogAdminIssues[] = 'blog tags page still contains inline style attributes';
}
foreach ([
    'button-row admin-stack-sm',
    'class="admin-select-sm"',
    'class="btn admin-action-row"',
    'button-row button-row--baseline',
    'class="admin-input-auto"',
] as $blogTagsUtilityFragment) {
    if (!str_contains($blogTagsSource, $blogTagsUtilityFragment)) {
        $blogAdminIssues[] = 'blog tags page is missing utility class fragment: ' . $blogTagsUtilityFragment;
    }
}
if (!str_contains($blogMembersSource, 'cms_blog_members')) {
    $blogAdminIssues[] = 'blog team management page is missing membership persistence';
}
if (!str_contains($blogMembersSource, 'blogMembershipRoleDefinitions()')) {
    $blogAdminIssues[] = 'blog team management page is missing role definitions';
}
if (!str_contains($blogMembersSource, 'canCurrentUserManageBlogTaxonomies($blogId)')) {
    $blogAdminIssues[] = 'blog team management page is missing capability enforcement';
}
if (!str_contains($blogMembersSource, 'Zakladatel blogu:') || !str_contains($blogMembersSource, 'created_by_user_id')) {
    $blogAdminIssues[] = 'blog team management page is missing creator audit context';
}
if (!str_contains($blogMembersSource, "name=\"action\" value=\"set_creator\"") || !str_contains($blogMembersSource, 'name="creator_user_id"')) {
    $blogAdminIssues[] = 'blog team management page is missing founder backfill form';
}
if (!str_contains($blogMembersSource, "currentUserHasCapability('blog_taxonomies_manage') || currentUserHasCapability('settings_manage')")) {
    $blogAdminIssues[] = 'blog team management page is missing founder backfill global-permission guard';
}
if (!str_contains($blogMembersSource, 'UPDATE cms_blogs') || !str_contains($blogMembersSource, 'created_by_user_id IS NULL')) {
    $blogAdminIssues[] = 'blog team management page is missing founder backfill single-write update';
}
if (!str_contains($blogMembersSource, 'Zakladatel už je u tohoto blogu evidovaný.')) {
    $blogAdminIssues[] = 'blog team management page is missing guard against reassigning existing founders';
}
if (!str_contains($blogMembersSource, 'automaticky nepřidá uživatele do týmu blogu')) {
    $blogAdminIssues[] = 'blog team management page is missing copy that separates founder audit from team membership';
}
if (!str_contains($blogMembersSource, 'Další blogy uživatele')) {
    $blogAdminIssues[] = 'blog team management page is missing cross-blog membership overview';
}
if (!str_contains($blogBulkSource, "\$action === 'move'")) {
    $blogAdminIssues[] = 'blog bulk handler is missing move action branch';
}
if (!str_contains($blogBulkSource, 'loadTransferableBlogArticles($pdo, $ids)')) {
    $blogAdminIssues[] = 'blog bulk handler no longer validates transferable articles before move';
}
if (!str_contains($blogBulkSource, "\$_SESSION['blog_transfer_selection']")) {
    $blogAdminIssues[] = 'blog bulk handler is missing transfer session handoff';
}
if (!str_contains($blogBulkSource, "BASE_URL . '/admin/blog_transfer.php'")) {
    $blogAdminIssues[] = 'blog bulk handler is missing redirect to transfer confirmation step';
}
if (!str_contains($blogTransferSource, 'loadTransferableBlogArticles($pdo, $selectionIds)')) {
    $blogAdminIssues[] = 'blog transfer page is missing transferable-article permission enforcement';
}
if (!str_contains($blogTransferSource, 'category_strategy') || !str_contains($blogTransferSource, 'tag_strategy')) {
    $blogAdminIssues[] = 'blog transfer page is missing category or tag reconciliation controls';
}
if (!str_contains($blogTransferSource, 'map_existing')) {
    $blogAdminIssues[] = 'blog transfer page is missing manual taxonomy mapping strategy';
}
if (!str_contains($blogTransferSource, 'name="category_map[') || !str_contains($blogTransferSource, 'name="tag_map[')) {
    $blogAdminIssues[] = 'blog transfer page is missing per-taxonomy mapping inputs';
}
if (!str_contains($blogTransferSource, 'blogTransferMappingFieldName')) {
    $blogAdminIssues[] = 'blog transfer page is missing stable field identifiers for taxonomy mapping';
}
if (!str_contains($blogTransferSource, 'canCurrentUserManageBlogTaxonomies((int)$targetBlog[\'id\'])')) {
    $blogAdminIssues[] = 'blog transfer page is missing target taxonomy permission guard';
}
if (!str_contains($blogTransferSource, 'targetCategoryMapById') || !str_contains($blogTransferSource, 'targetTagMapById')) {
    $blogAdminIssues[] = 'blog transfer page is missing target-blog taxonomy ownership validation';
}
if (!str_contains($blogTransferSource, 'adminFieldAttributes(') || !str_contains($blogTransferSource, 'adminRenderFieldError(')) {
    $blogAdminIssues[] = 'blog transfer page is missing field-level accessibility errors for transfer mapping';
}
if (!str_contains($blogTransferSource, 'saveRevision($pdo, \'article\'')) {
    $blogAdminIssues[] = 'blog transfer page is missing revision logging for moved articles';
}
if (!str_contains($blogTransferSource, 'array_key_exists($tagKey, $manualTagAssignments)')) {
    $blogAdminIssues[] = 'blog transfer page is missing manual tag remapping application';
}
if (!str_contains($blogTransferSource, 'internalRedirectTarget')) {
    $blogAdminIssues[] = 'blog transfer page is missing validated redirect handling';
}
if (str_contains($blogTransferSource, 'error_log(')
    || !str_contains($blogTransferSource, "koraLog('warning', 'admin article transfer failed'")) {
    $blogAdminIssues[] = 'blog transfer page no longer uses structured recoverable logging';
}
if (!str_contains($blogSaveSource, 'canCurrentUserWriteToBlog($blogId)')) {
    $blogAdminIssues[] = 'article save is missing writable-blog enforcement';
}
if (str_contains($blogSaveSource, 'error_log(')
    || !str_contains($blogSaveSource, "koraLog('warning', 'admin article save failed'")) {
    $blogAdminIssues[] = 'article save no longer uses structured recoverable logging';
}
if (!str_contains($blogSaveSource, 'cms_categories WHERE id = ? AND blog_id = ?')) {
    $blogAdminIssues[] = 'article save no longer validates category within selected blog';
}
if (!str_contains($blogSaveSource, 'cms_tags WHERE blog_id = ? ORDER BY id')) {
    $blogAdminIssues[] = 'article save no longer scopes tags to selected blog';
}
if (!str_contains($blogSaveSource, '$articleIsMovingToAnotherBlog') || !str_contains($blogSaveSource, 'category_selection_mode') || !str_contains($blogSaveSource, 'tag_selection_mode')) {
    $blogAdminIssues[] = 'article save is missing blog-change taxonomy carry-over safeguards';
}
if (!str_contains($blogSaveSource, 'resolveArticleMoveTaxonomyState(') || !str_contains($blogSaveSource, 'loadArticleTagDetails(')) {
    $blogAdminIssues[] = 'article save is missing server-side taxonomy remapping helpers for blog changes';
}
if (!str_contains($blogSaveSource, 'missing_category_action') || !str_contains($blogSaveSource, 'missing_tags_action') || !str_contains($blogSaveSource, '$canCreateTargetTaxonomies')) {
    $blogAdminIssues[] = 'article save is missing missing-taxonomy create controls for single-article blog changes';
}
if (!str_contains($blogSaveSource, 'INSERT INTO cms_categories (name, blog_id) VALUES (?, ?)') || !str_contains($blogSaveSource, 'INSERT INTO cms_tags (name, slug, blog_id) VALUES (?, ?, ?)')) {
    $blogAdminIssues[] = 'article save is missing creation of missing target taxonomies during single-article move';
}
if (!str_contains($blogSaveSource, "err=category_target") && !str_contains($blogSaveSource, "'category_target'")) {
    $blogAdminIssues[] = 'article save is missing a validation error for foreign categories outside the selected blog';
}
if (!str_contains($blogSaveSource, "err=tags_target") && !str_contains($blogSaveSource, "'tags_target'")) {
    $blogAdminIssues[] = 'article save is missing a validation error for foreign tags outside the selected blog';
}
if (!str_contains($blogSaveSource, 'is_featured_in_blog')) {
    $blogAdminIssues[] = 'article save is missing featured-in-blog persistence';
}
if (!str_contains($blogSaveSource, 'WHERE blog_id = ? AND id <> ?')) {
    $blogAdminIssues[] = 'article save no longer resets previous featured article within a blog';
}
if (!str_contains($blogsAdminSource, 'name="meta_title"') || !str_contains($blogsAdminSource, 'name="meta_description"')) {
    $blogAdminIssues[] = 'blog editor is missing per-blog SEO fields';
}
if (!str_contains($blogsAdminSource, 'name="rss_subtitle"') || !str_contains($blogsAdminSource, 'name="feed_item_limit"')) {
    $blogAdminIssues[] = 'blog editor is missing per-blog RSS metadata';
}
if (!str_contains($blogsAdminSource, 'name="comments_default"')) {
    $blogAdminIssues[] = 'blog editor is missing per-blog default comments toggle';
}
if (!str_contains($blogsAdminSource, 'name="logo_alt_text"')) {
    $blogAdminIssues[] = 'blog editor is missing blog logo alt text field';
}
if (!str_contains($blogsAdminSource, 'name="intro_content"')) {
    $blogAdminIssues[] = 'blog editor is missing extended intro content';
}
if (!str_contains($blogsAdminSource, "renderAdminContentReferencePicker('intro_content')") || !str_contains($blogsAdminSource, "renderAdminContentReferencePicker('bd-intro-content')")) {
    $blogAdminIssues[] = 'blog editor is missing content picker support for extended intro content';
}
foreach ([
    '<legend>Základní údaje blogu</legend>',
    '<legend>Obsah a metadata blogu</legend>',
    '<legend>Logo a zobrazení blogu</legend>',
    'aria-describedby="blog-description-help"',
    'aria-describedby="bd-slug-help"',
    'aria-describedby="bd-description-help"',
    'aria-describedby="bd-meta-help"',
    'aria-describedby="bd-feed-help"',
    'class="blog-admin-fieldset"',
    'class="blog-dialog"',
    'class="blog-dialog-overlay"',
    'class="blog-logo-preview"',
    'class="blog-sort-row"',
] as $blogDialogAccessibilityFragment) {
    if (!str_contains($blogsAdminSource, $blogDialogAccessibilityFragment)) {
        $blogAdminIssues[] = 'blog admin forms are missing accessibility fragment: ' . $blogDialogAccessibilityFragment;
    }
}
foreach ([
    "dialogTitle.textContent = 'Upravit blog: ' + (btn.dataset.blogName || 'Blog');",
    'function getDialogFocusableElements()',
    'el.offsetParent !== null',
    "document.body.classList.add('admin-modal-open')",
    "document.body.classList.remove('admin-modal-open')",
] as $blogDialogJsFragment) {
    if (!str_contains($blogsAdminSource, $blogDialogJsFragment)) {
        $blogAdminIssues[] = 'blog edit dialog is missing accessibility JS fragment: ' . $blogDialogJsFragment;
    }
}
if (str_contains($blogsAdminSource, 'style=')) {
    $blogAdminIssues[] = 'blog admin overview still contains inline style attributes';
}
if (str_contains($blogsAdminSource, '.style')) {
    $blogAdminIssues[] = 'blog admin overview still mutates inline styles via JavaScript';
}
if (!str_contains($blogListSource, 'value="article_to_page"') || !str_contains($blogListSource, 'aria-hidden="true"')) {
    $blogAdminIssues[] = 'blog list still exposes the article-to-page arrow to screen readers';
}
if (!str_contains($blogsAdminSource, 'blog.php?blog=') || !str_contains($blogsAdminSource, 'Články blogu')) {
    $blogAdminIssues[] = 'blog overview is missing per-blog article action link';
}
if (!str_contains($blogsAdminSource, 'blog_cats.php?blog_id=') || !str_contains($blogsAdminSource, 'Kategorie blogu')) {
    $blogAdminIssues[] = 'blog overview is missing per-blog category action link';
}
if (!str_contains($blogsAdminSource, 'blog_tags.php?blog_id=') || !str_contains($blogsAdminSource, 'Štítky blogu')) {
    $blogAdminIssues[] = 'blog overview is missing per-blog tag action link';
}
if (
    preg_match('/Články blogu.*Kategorie blogu.*Štítky blogu.*blog_pages\.php\?blog_id=/s', $blogsAdminSource) !== 1
) {
    $blogAdminIssues[] = 'blog overview actions are missing article/category/tag links before blog pages';
}
if (!str_contains($blogsAdminSource, 'member_count')) {
    $blogAdminIssues[] = 'blog overview is missing assigned team counts';
}
if (!str_contains($blogsAdminSource, 'created_by_user_id') || !str_contains($blogsAdminSource, 'creator_label')) {
    $blogAdminIssues[] = 'blog overview is missing creator audit visibility';
}
if (!str_contains($blogsAdminSource, 'Doplníte na stránce Tým blogu.')) {
    $blogAdminIssues[] = 'blog overview is missing founder backfill hint for legacy blogs';
}
if (!str_contains($blogsAdminSource, 'saveBlogSlugRedirect')) {
    $blogAdminIssues[] = 'blog editor no longer stores legacy slug redirects';
}
if (!str_contains($blogsAdminSource, 'INSERT INTO cms_blog_members') || !str_contains($blogsAdminSource, "'manager'")) {
    $blogAdminIssues[] = 'blog create flow no longer assigns the creator as blog manager';
}
if (!str_contains($blogsAdminSource, 'created_by_user_id) VALUES') || !str_contains($blogsAdminSource, '$creatorUserId > 0 ? $creatorUserId : null')) {
    $blogAdminIssues[] = 'blog create flow is missing creator audit persistence';
}
if (!str_contains($blogsAdminSource, 'beginTransaction()')) {
    $blogAdminIssues[] = 'blog create flow is missing transactional persistence';
}
if (!str_contains($usersAdminSource, 'Bez přiřazení k blogům')) {
    $blogAdminIssues[] = 'users overview is missing blog assignment visibility';
}
if (!str_contains($dbSource, 'function resetAutoIncrementIfEmpty')) {
    $blogAdminIssues[] = 'database helpers are missing auto-increment reset helper for empty blog tables';
}
if (!str_contains($blogDeleteSource, "resetAutoIncrementIfEmpty(\$pdo, 'cms_blogs')")) {
    $blogAdminIssues[] = 'last blog deletion no longer resets blog auto-increment counter';
}
if (!str_contains($blogPresentationSource, 'function getBlogByLegacySlug')) {
    $blogAdminIssues[] = 'blog helpers are missing legacy slug lookup';
}
if (!str_contains($blogPresentationSource, 'function getWritableBlogsForUser')) {
    $blogAdminIssues[] = 'blog helpers are missing writable-blog membership resolution';
}
if (!str_contains($blogPresentationSource, 'function getBlogsWithExplicitMembers')) {
    $blogAdminIssues[] = 'blog helpers are missing explicit-membership blog map';
}
if (!str_contains($blogPresentationSource, 'function blogHasExplicitMembers')) {
    $blogAdminIssues[] = 'blog helpers are missing explicit-membership detection';
}
if (!str_contains($blogPresentationSource, '!isset($explicitMembershipBlogs[$blogId])')) {
    $blogAdminIssues[] = 'writable blog helper no longer keeps blogs without explicit teams writable';
}
if (!str_contains($blogPresentationSource, 'if (!blogHasExplicitMembers($blogId)) {')) {
    $blogAdminIssues[] = 'blog permission helpers are missing per-blog explicit-team fallback';
}
if (!str_contains($blogPresentationSource, 'function getTaxonomyManagedBlogsForUser')) {
    $blogAdminIssues[] = 'blog helpers are missing taxonomy-managed blog resolution';
}
if (!str_contains($blogPresentationSource, 'function loadTransferableBlogArticles')) {
    $blogAdminIssues[] = 'blog helpers are missing transferable article loader for cross-blog move';
}
if (!str_contains($blogPresentationSource, 'function normalizeBlogTaxonomyName') || !str_contains($blogPresentationSource, 'function blogCategoryLookupByNormalizedName') || !str_contains($blogPresentationSource, 'function blogTagLookupMaps') || !str_contains($blogPresentationSource, 'function loadArticleTagDetails')) {
    $blogAdminIssues[] = 'blog helpers are missing shared taxonomy carry-over helpers for article moves';
}
if (!str_contains($blogPresentationSource, 'function saveBlogSlugRedirect')) {
    $blogAdminIssues[] = 'blog helpers are missing slug redirect persistence';
}
if (str_contains($blogPresentationSource, "(string)(\$currentBlog['slug'] ?? '') === \$oldSlug")) {
    $blogAdminIssues[] = 'blog slug redirect helper still skips persisting the old slug before the update happens';
}
if (!str_contains($blogPresentationSource, 'function blogLogoAltText')) {
    $blogAdminIssues[] = 'blog helpers are missing configurable blog logo alt text helper';
}
if (!str_contains($blogIndexControllerSource, "trim((string)(\$_GET['q'] ?? ''))")) {
    $blogAdminIssues[] = 'public blog index is missing in-blog search';
}
if (!str_contains($blogIndexControllerSource, "trim((string)(\$_GET['archiv'] ?? ''))")) {
    $blogAdminIssues[] = 'public blog index is missing archive filter support';
}
if (!str_contains($blogIndexControllerSource, 'is_featured_in_blog = 1')) {
    $blogAdminIssues[] = 'public blog index is missing featured article selection';
}
if (!str_contains($blogIndexControllerSource, 'extra_head_html')) {
    $blogAdminIssues[] = 'public blog index is missing RSS discovery link injection';
}
if (!str_contains($blogIndexViewSource, 'blog-search-q')) {
    $blogAdminIssues[] = 'blog index view is missing blog-local search form';
}
if (!str_contains($blogIndexViewSource, 'Archiv blogu')) {
    $blogAdminIssues[] = 'blog index view is missing archive navigation';
}
foreach ([
    'blog-blogs-heading' => 'Další blogy webu',
    'blog-search-heading' => 'Hledání v blogu',
    'blog-categories-heading' => 'Kategorie blogu',
    'blog-tags-heading' => 'Štítky blogu',
    'blog-archives-heading' => 'Archiv blogu',
] as $blogHeadingId => $blogHeadingLabel) {
    if (!str_contains($blogIndexViewSource, 'id="' . $blogHeadingId . '"')) {
        $blogAdminIssues[] = 'blog index view is missing visible heading id for ' . $blogHeadingLabel;
    }
    if (!str_contains($blogIndexViewSource, $blogHeadingLabel)) {
        $blogAdminIssues[] = 'blog index view is missing visible heading text for ' . $blogHeadingLabel;
    }
    if (!str_contains($blogIndexViewSource, 'aria-labelledby="' . $blogHeadingId . '"')) {
        $blogAdminIssues[] = 'blog index view is missing aria-labelledby for ' . $blogHeadingLabel;
    }
}
if (!str_contains($blogIndexViewSource, 'Doporučený článek')) {
    $blogAdminIssues[] = 'blog index view is missing featured article hero';
}
if (!str_contains($blogIndexViewSource, '$blogLogoAlt = blogLogoAltText($blog);') || !str_contains($blogIndexViewSource, 'alt="<?= h($blogLogoAlt) ?>"')) {
    $blogAdminIssues[] = 'blog index view is missing configurable blog logo alt text support';
}
if (!str_contains($blogIndexViewSource, "renderContent((string)\$blog['intro_content'])")) {
    $blogAdminIssues[] = 'blog index view is missing extended intro content rendering';
}
if (!str_contains($blogIndexViewSource, 'blog-secondary-tools')) {
    $blogAdminIssues[] = 'blog index view is missing secondary blog navigation wrapper';
}
$blogSearchHeadingPos = strpos($blogIndexViewSource, 'blog-search-heading');
$blogPagerPos = strpos($blogIndexViewSource, 'renderPager(');
$blogEmptyStatePos = strpos($blogIndexViewSource, 'empty-state');
if (
    $blogSearchHeadingPos === false
    || $blogPagerPos === false
    || $blogEmptyStatePos === false
    || $blogSearchHeadingPos < $blogPagerPos
    || $blogSearchHeadingPos < $blogEmptyStatePos
) {
    $blogAdminIssues[] = 'blog index secondary navigation is not rendered after article content';
}
if (!str_contains($blogFeedSource, 'getBlogByLegacySlug($feedBlogSlug)')) {
    $blogAdminIssues[] = 'RSS feed is missing legacy blog slug redirects';
}
if (!str_contains($blogFeedSource, 'feed_item_limit')) {
    $blogAdminIssues[] = 'RSS feed is missing per-blog item limit';
}
if (!str_contains($blogFeedSource, 'rss_subtitle')) {
    $blogAdminIssues[] = 'RSS feed is missing per-blog subtitle support';
}
if (!str_contains($blogRouterSource, 'getBlogByLegacySlug($blogSlug)')) {
    $blogAdminIssues[] = 'blog router is missing legacy slug redirect lookup';
}
if (!str_contains($blogExportSource, "'blog_members'") || !str_contains($blogExportSource, "'blog_slug_redirects'")) {
    $blogAdminIssues[] = 'export is missing blog membership and slug redirect datasets';
}
if (!str_contains($blogExportSource, 'created_by_user_id')) {
    $blogAdminIssues[] = 'export is missing blog creator audit field';
}
if (!str_contains($blogExportSource, 'logo_alt_text')) {
    $blogAdminIssues[] = 'export is missing blog logo alt text field';
}
if (!str_contains($blogImportSource, 'cms_blog_members') || !str_contains($blogImportSource, 'cms_blog_slug_redirects')) {
    $blogAdminIssues[] = 'import is missing blog membership or slug redirect restore';
}
if (!str_contains($blogImportSource, 'created_by_user_id')) {
    $blogAdminIssues[] = 'import is missing blog creator audit restore';
}
if (!str_contains($blogImportSource, 'logo_alt_text')) {
    $blogAdminIssues[] = 'import is missing blog logo alt text restore';
}
if (!str_contains($blogWidgetAdminSource, 'value="-1">Aktuální blog (na blogových stránkách)')) {
    $blogAdminIssues[] = 'widget editor is missing current-blog context option for latest articles';
}
if (!str_contains($blogImportSource, "INSERT IGNORE INTO cms_newsletters\n                         (id, subject, body, recipient_count, sent_at, created_at)\n                         VALUES (?,?,?,?,?,?)")
    || str_contains($blogImportSource, "INSERT IGNORE INTO cms_newsletters\n                         (id, subject, body, recipient_count, sent_at, created_at)\n                         VALUES (?,?,?,?,?,?,?)")) {
    $blogAdminIssues[] = 'import newsletter restore has mismatched column/value placeholders';
}
if (!str_contains($blogWidgetSaveSource, '$rawBlogId === -1 ? -1')) {
    $blogAdminIssues[] = 'widget save is missing current-blog context persistence';
}
if (!str_contains($blogWidgetLibSource, "\$rawBlogId === -1")) {
    $blogAdminIssues[] = 'latest articles widget is missing current-blog rendering context';
}
if (!str_contains($blogInstallSource, 'cms_blog_members') || !str_contains($blogInstallSource, 'cms_blog_slug_redirects')) {
    $blogAdminIssues[] = 'fresh install is missing multiblog membership tables';
}
if (!str_contains($blogInstallSource, 'intro_content TEXT')) {
    $blogAdminIssues[] = 'fresh install is missing extended blog intro field';
}
if (!str_contains($blogInstallSource, 'logo_alt_text VARCHAR(255) NOT NULL DEFAULT')) {
    $blogAdminIssues[] = 'fresh install is missing blog logo alt text field';
}
if (!str_contains($blogInstallSource, 'created_by_user_id INT NULL DEFAULT NULL')) {
    $blogAdminIssues[] = 'fresh install is missing blog creator audit field';
}
if (!str_contains($blogInstallSource, 'is_featured_in_blog TINYINT(1) NOT NULL DEFAULT 0')) {
    $blogAdminIssues[] = 'fresh install is missing per-blog featured article field';
}
if (!str_contains($blogMigrateSource, 'cms_blogs.intro_content')) {
    $blogAdminIssues[] = 'migrations are missing extended blog intro field';
}
if (!str_contains($blogMigrateSource, 'cms_blogs.logo_alt_text')) {
    $blogAdminIssues[] = 'migrations are missing blog logo alt text field';
}
if (!str_contains($blogMigrateSource, 'cms_blogs.created_by_user_id')) {
    $blogAdminIssues[] = 'migrations are missing blog creator audit field';
}
if (!str_contains($blogMigrateSource, 'cms_articles.is_featured_in_blog')) {
    $blogAdminIssues[] = 'migrations are missing per-blog featured article field';
}
if (!str_contains($blogWpImportSource, 'created_by_user_id') || !str_contains($blogWpImportSource, 'cms_blog_members')) {
    $blogAdminIssues[] = 'wordpress import is missing creator audit persistence for new blogs';
}
if (!str_contains($blogEstrankyImportSource, 'created_by_user_id') || !str_contains($blogEstrankyImportSource, 'cms_blog_members')) {
    $blogAdminIssues[] = 'estranky import is missing creator audit persistence for new blogs';
}
if (!function_exists('roleDefinitions')) {
    $blogAdminIssues[] = 'auth helpers are missing roleDefinitions()';
} else {
    $blogRoleDefinitions = roleDefinitions();
    foreach (['editor', 'admin', 'collaborator'] as $blogCreatorRole) {
        if (!in_array('blog_taxonomies_manage', $blogRoleDefinitions[$blogCreatorRole]['capabilities'] ?? [], true)) {
            $blogAdminIssues[] = 'role no longer allows creating blogs: ' . $blogCreatorRole;
        }
    }
    if (in_array('blog_taxonomies_manage', $blogRoleDefinitions['author']['capabilities'] ?? [], true)) {
        $blogAdminIssues[] = 'author role unexpectedly allows creating blogs';
    }
}

if ($blogAdminIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($blogAdminIssues as $blogAdminIssue) {
        echo '- ' . $blogAdminIssue . "\n";
    }
}

echo "=== blog_public_guardrails ===\n";
$blogPublicIssues = [];
$blogArticleViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/blog-article.php');
$blogFeedProbe = fetchUrl($baseUrl . '/feed.php?blog=__neexistujici__', '', 0);
if (!preg_match('/\s404\s/', $blogFeedProbe['status'])) {
    $blogPublicIssues[] = 'per-blog RSS feed does not return 404 for unknown blog slug';
}
$blogRouterProbe = fetchUrl($baseUrl . '/__neexistujici_blog__/', '', 0);
if (!preg_match('/\s404\s/', $blogRouterProbe['status'])) {
    $blogPublicIssues[] = 'blog router does not return 404 for unknown blog slug';
}
if (!str_contains($blogArticleControllerSource, 'blogLogoUrl($articleBlog)')) {
    $blogPublicIssues[] = 'blog article social image is missing blog logo fallback';
}
if (!str_contains($blogArticleControllerSource, "'image_alt' => \$articleSeoTitle") || !str_contains($blogArticleControllerSource, "'published_time' =>")) {
    $blogPublicIssues[] = 'blog article social metadata is missing image alt or article timestamps';
}
if (!str_contains($authSource, 'function isSocialPreviewCrawler') || !str_contains($authSource, 'facebookexternalhit') || !str_contains($authSource, '$_SESSION = [];')) {
    $blogPublicIssues[] = 'auth bootstrap is missing cookie-free social preview crawler handling';
}
if (
    !str_contains($authSource, 'session_cache_limiter')
    || !str_contains($authSource, 'header_register_callback')
    || !str_contains($authSource, "header_remove('Set-Cookie')")
    || !str_contains($authSource, 'public, max-age=300, s-maxage=300')
) {
    $blogPublicIssues[] = 'auth bootstrap is missing crawler-friendly cache headers';
}
if (!str_contains($homeSource, "'url' => BASE_URL . '/'")) {
    $blogPublicIssues[] = 'homepage social metadata should canonicalize og:url to the site root, not index.php';
}
if (!str_contains($blogArticleViewSource, 'id="blog-article-tags-heading"')
    || !str_contains($blogArticleViewSource, 'aria-labelledby="blog-article-tags-heading"')) {
    $blogPublicIssues[] = 'blog article tags navigation is missing heading-backed aria-labelledby semantics';
}
if (str_contains($blogArticleViewSource, 'aria-label="Tagy článku"')) {
    $blogPublicIssues[] = 'blog article tags navigation still uses aria-label-only semantics';
}
if (
    !str_contains($htaccessSource, 'KORA_SOCIAL_CRAWLER')
    || !str_contains($htaccessSource, 'SetEnvIfNoCase User-Agent')
    || !str_contains($htaccessSource, 'Header always unset Cache-Control env=KORA_SOCIAL_CRAWLER')
    || !str_contains($htaccessSource, 'Header always set Cache-Control "public, max-age=300, s-maxage=300" env=KORA_SOCIAL_CRAWLER')
    || !str_contains($htaccessSource, 'Header always unset Pragma env=KORA_SOCIAL_CRAWLER')
    || !str_contains($htaccessSource, 'Header always unset Expires env=KORA_SOCIAL_CRAWLER')
) {
    $blogPublicIssues[] = '.htaccess is missing Apache-level social crawler cache override';
}
foreach (['og:image:secure_url', 'og:image:type', 'og:image:width', 'og:image:height', 'og:image:alt', 'og:updated_time'] as $socialMetaFragment) {
    if (!str_contains($uiSource, $socialMetaFragment)) {
        $blogPublicIssues[] = 'SEO meta renderer is missing ' . $socialMetaFragment;
    }
}
if (!str_contains($uiSource, "\$url = isset(\$meta['url']) ? seoCanonicalUrl")) {
    $blogPublicIssues[] = 'SEO meta renderer does not normalize og:url to an absolute URL';
}
$socialPreviewProbePath = $articleCanonicalPath !== '' ? $articleCanonicalPath : '/';
$socialPreviewProbe = fetchUrl($baseUrl . $socialPreviewProbePath, '', 0, 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)');
foreach ($socialPreviewProbe['headers'] as $socialPreviewHeader) {
    if (stripos($socialPreviewHeader, 'Set-Cookie:') === 0) {
        $blogPublicIssues[] = 'social preview crawler receives a session cookie';
        break;
    }
}
foreach ($socialPreviewProbe['headers'] as $socialPreviewHeader) {
    if (stripos($socialPreviewHeader, 'Cache-Control:') === 0 && stripos($socialPreviewHeader, 'no-store') !== false) {
        $blogPublicIssues[] = 'social preview crawler receives no-store cache headers';
        break;
    }
}
if (str_contains($socialPreviewProbe['body'], '<meta property="og:url" content="/')) {
    $blogPublicIssues[] = 'social preview response contains a relative og:url';
}
if ($blogPublicIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($blogPublicIssues as $blogPublicIssue) {
        echo '- ' . $blogPublicIssue . "\n";
    }
}

echo "=== faq_source_guardrails ===\n";
$faqSourceIssues = [];
$faqSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/faq_save.php');
$faqFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/faq_form.php');
$faqIndexControllerSource = (string)file_get_contents(dirname(__DIR__) . '/faq/index.php');
$faqIndexViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/faq-index.php');
$faqArticleViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/faq-article.php');
$faqItemSource = (string)file_get_contents(dirname(__DIR__) . '/faq/item.php');
if (!str_contains($faqSaveSource, 'upsertPathRedirect')) {
    $faqSourceIssues[] = 'faq save is missing slug redirect persistence';
}
if (!str_contains($faqSaveSource, 'faqRevisionSnapshot')) {
    $faqSourceIssues[] = 'faq save is missing expanded revision snapshot';
}
if (!str_contains($faqFormSource, 'name="meta_title"') || !str_contains($faqFormSource, 'name="meta_description"')) {
    $faqSourceIssues[] = 'faq form is missing SEO fields';
}
if (!str_contains($faqIndexControllerSource, "trim((string)(\$_GET['q'] ?? ''))")) {
    $faqSourceIssues[] = 'public faq index is missing search support';
}
if (!str_contains($faqIndexControllerSource, 'paginateArray(')) {
    $faqSourceIssues[] = 'public faq index is missing pagination support';
}
if (!str_contains($faqIndexControllerSource, 'faqStructuredData(')) {
    $faqSourceIssues[] = 'public faq index is missing FAQPage structured data';
}
if (!str_contains($faqIndexViewSource, '$displayModeLinks') || !str_contains($faqIndexViewSource, 'tab-nav')) {
    $faqSourceIssues[] = 'public faq template is missing inline display toggle';
}
if (!str_contains($faqIndexViewSource, 'id="faq-index-breadcrumb-heading"')
    || !str_contains($faqIndexViewSource, 'aria-labelledby="faq-index-breadcrumb-heading"')) {
    $faqSourceIssues[] = 'public faq index breadcrumbs are missing heading-backed aria-labelledby semantics';
}
if (str_contains($faqIndexViewSource, 'aria-label="Drobečková navigace"')) {
    $faqSourceIssues[] = 'public faq index still uses aria-label-only breadcrumb navigation';
}
if (!str_contains($faqArticleViewSource, 'id="faq-breadcrumb-heading"')
    || !str_contains($faqArticleViewSource, 'aria-labelledby="faq-breadcrumb-heading"')) {
    $faqSourceIssues[] = 'public faq article breadcrumbs are missing heading-backed aria-labelledby semantics';
}
if (str_contains($faqArticleViewSource, 'aria-label="Drobečková navigace"')) {
    $faqSourceIssues[] = 'public faq article still uses aria-label-only breadcrumb navigation';
}
foreach ([
    'faq-category-nav-heading',
    'faq-current-categories-heading',
] as $faqNavHeadingId) {
    if (!str_contains($faqIndexViewSource, 'id="' . $faqNavHeadingId . '"')
        || !str_contains($faqIndexViewSource, 'aria-labelledby="' . $faqNavHeadingId . '"')) {
        $faqSourceIssues[] = 'public faq navigation is missing heading-backed semantics for ' . $faqNavHeadingId;
    }
}
foreach ([
    'aria-label="Kategorie znalostní báze"',
    'aria-label="Kategorie v aktuálním výběru"',
] as $legacyFaqNavFragment) {
    if (str_contains($faqIndexViewSource, $legacyFaqNavFragment)) {
        $faqSourceIssues[] = 'public faq index still uses aria-label-only navigation: ' . $legacyFaqNavFragment;
    }
}
if (!str_contains($faqIndexViewSource, 'listing-shell__pager')) {
    $faqSourceIssues[] = 'public faq template is missing pager output';
}
if (!str_contains($faqItemSource, 'faqStructuredData(')) {
    $faqSourceIssues[] = 'faq detail is missing FAQ structured data';
}
if (!str_contains($faqItemSource, '$relatedFaqs')) {
    $faqSourceIssues[] = 'faq detail is missing related questions support';
}
if ($faqSourceIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($faqSourceIssues as $faqSourceIssue) {
        echo '- ' . $faqSourceIssue . "\n";
    }
}

echo "=== board_source_guardrails ===\n";
$boardSourceIssues = [];
$boardSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/board_save.php');
$boardFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/board_form.php');
$boardIndexControllerSource = (string)file_get_contents(dirname(__DIR__) . '/board/index.php');
$boardIndexViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/board-index.php');
$boardFileSource = (string)file_get_contents(dirname(__DIR__) . '/board/file.php');
$boardDocumentSource = (string)file_get_contents(dirname(__DIR__) . '/board/document.php');
if (!str_contains($boardSaveSource, "saveRevision(\$pdo, 'board'")) {
    $boardSourceIssues[] = 'board save is missing revision persistence';
}
if (!str_contains($boardSaveSource, 'upsertPathRedirect')) {
    $boardSourceIssues[] = 'board save is missing slug redirect persistence';
}
if (!str_contains($boardFormSource, 'revisions.php?type=board')) {
    $boardSourceIssues[] = 'board form is missing revisions link';
}
if (!str_contains($boardFormSource, 'board-type-help')) {
    $boardSourceIssues[] = 'board form is missing board type help';
}
if (!str_contains($boardIndexControllerSource, "trim((string)(\$_GET['q'] ?? ''))")) {
    $boardSourceIssues[] = 'public board index is missing search support';
}
if (!str_contains($boardIndexControllerSource, "trim((string)(\$_GET['month'] ?? ''))")) {
    $boardSourceIssues[] = 'public board index is missing month filter support';
}
if (!str_contains($boardIndexControllerSource, 'renderPager(')) {
    $boardSourceIssues[] = 'public board index is missing pagination support';
}
if (!str_contains($boardIndexViewSource, 'board-item__summary')) {
    $boardSourceIssues[] = 'public board template is missing excerpt preview';
}
if (!str_contains($boardIndexViewSource, 'board-item__contact')) {
    $boardSourceIssues[] = 'public board template is missing contact block';
}
if (!str_contains($boardIndexViewSource, "moduleFileUrl('board'")) {
    $boardSourceIssues[] = 'public board template is missing file endpoint links';
}
if (!str_contains($boardIndexViewSource, 'Filtrovat položky vývěsky')) {
    $boardSourceIssues[] = 'public board template is missing filter form';
}
if (!str_contains($boardIndexViewSource, 'listing-shell__pager')) {
    $boardSourceIssues[] = 'public board template is missing pager output';
}
if (!str_contains($boardFileSource, 'currentUserHasCapability(\'content_manage_shared\')')) {
    $boardSourceIssues[] = 'board file endpoint is missing tightened private access rule';
}
if (!str_contains($boardFileSource, '(string)($document[\'posted_date\'] ?? \'\') > date(\'Y-m-d\')')) {
    $boardSourceIssues[] = 'board file endpoint is missing future-date access guard';
}
if (!str_contains($boardDocumentSource, 'boardPublicVisibilitySql(\'b\')')) {
    $boardSourceIssues[] = 'board detail is missing public visibility guard';
}

if ($boardSourceIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($boardSourceIssues as $boardSourceIssue) {
        echo '- ' . $boardSourceIssue . "\n";
    }
}

echo "=== gallery_source_guardrails ===\n";
$gallerySourceIssues = [];
$galleryAlbumSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/gallery_album_save.php');
$galleryPhotoSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/gallery_photo_save.php');
$galleryAlbumFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/gallery_album_form.php');
$galleryPhotoFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/gallery_photo_form.php');
$galleryIndexControllerSource = (string)file_get_contents(dirname(__DIR__) . '/gallery/index.php');
$galleryAlbumControllerSource = (string)file_get_contents(dirname(__DIR__) . '/gallery/album.php');
$galleryPhotoControllerSource = (string)file_get_contents(dirname(__DIR__) . '/gallery/photo.php');
$galleryPhotoViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/gallery-photo.php');
$galleryImageSource = (string)file_get_contents(dirname(__DIR__) . '/gallery/image.php');
$gallerySearchSource = (string)file_get_contents(dirname(__DIR__) . '/search.php');
$galleryWidgetSource = (string)file_get_contents(dirname(__DIR__) . '/lib/widgets.php');
$htaccessSource = (string)file_get_contents(dirname(__DIR__) . '/.htaccess');
if (!str_contains($galleryAlbumSaveSource, "saveRevision(\$pdo, 'gallery_album'")) {
    $gallerySourceIssues[] = 'gallery album save is missing revision persistence';
}
if (!str_contains($galleryAlbumSaveSource, 'upsertPathRedirect')) {
    $gallerySourceIssues[] = 'gallery album save is missing slug redirect persistence';
}
if (!str_contains($galleryPhotoSaveSource, "saveRevision(\$pdo, 'gallery_photo'")) {
    $gallerySourceIssues[] = 'gallery photo save is missing revision persistence';
}
if (!str_contains($galleryPhotoSaveSource, 'upsertPathRedirect')) {
    $gallerySourceIssues[] = 'gallery photo save is missing slug redirect persistence';
}
if (!str_contains($galleryAlbumFormSource, 'revisions.php?type=gallery_album')) {
    $gallerySourceIssues[] = 'gallery album form is missing revisions link';
}
if (!str_contains($galleryPhotoFormSource, 'revisions.php?type=gallery_photo')) {
    $gallerySourceIssues[] = 'gallery photo form is missing revisions link';
}
if (!str_contains($galleryPhotoFormSource, 'name="is_published"')) {
    $gallerySourceIssues[] = 'gallery photo form is missing visibility toggle';
}
if (!str_contains($galleryIndexControllerSource, "trim((string)(\$_GET['q'] ?? ''))")) {
    $gallerySourceIssues[] = 'gallery index is missing public search support';
}
if (!str_contains($galleryIndexControllerSource, 'paginate(')) {
    $gallerySourceIssues[] = 'gallery index is missing pagination support';
}
if (!str_contains($galleryAlbumControllerSource, "galleryAlbumPublicVisibilitySql('a')")) {
    $gallerySourceIssues[] = 'gallery album controller is missing public visibility guard';
}
if (!str_contains($galleryAlbumControllerSource, 'renderPager(')) {
    $gallerySourceIssues[] = 'gallery album controller is missing pager support';
}
if (!str_contains($galleryPhotoControllerSource, "galleryPhotoPublicVisibilitySql('p', 'a')")) {
    $gallerySourceIssues[] = 'gallery photo controller is missing public visibility guard';
}
if (!str_contains($galleryPhotoControllerSource, '$relatedPhotos')) {
    $gallerySourceIssues[] = 'gallery photo controller is missing related photos support';
}
if (!str_contains($galleryPhotoViewSource, 'data-copy-gallery-link')) {
    $gallerySourceIssues[] = 'gallery photo view is missing copy-link action';
}
if (!str_contains($galleryImageSource, "currentUserHasCapability('content_manage_shared')")) {
    $gallerySourceIssues[] = 'gallery image endpoint is missing private visibility guard';
}
if (!str_contains($gallerySearchSource, "galleryPhotoPublicVisibilitySql('p', 'a')")) {
    $gallerySourceIssues[] = 'search no longer protects gallery photo visibility';
}
if (str_contains($galleryWidgetSource, '/uploads/gallery/thumbs/')) {
    $gallerySourceIssues[] = 'gallery widget still exposes direct uploads paths';
}
if (!str_contains($htaccessSource, 'RewriteRule ^uploads/gallery/ - [F,L,NC]')) {
    $gallerySourceIssues[] = 'htaccess is missing gallery uploads deny rule';
}
if ($gallerySourceIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($gallerySourceIssues as $gallerySourceIssue) {
        echo '- ' . $gallerySourceIssue . "\n";
    }
}

echo "=== places_source_guardrails ===\n";
$placesSourceIssues = [];
$placeSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/place_save.php');
$placeFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/place_form.php');
$placeIndexControllerSource = (string)file_get_contents(dirname(__DIR__) . '/places/index.php');
$placeDetailControllerSource = (string)file_get_contents(dirname(__DIR__) . '/places/place.php');
$placeIndexViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/places-index.php');
$placeImageSource = (string)file_get_contents(dirname(__DIR__) . '/places/image.php');
$placeSearchSource = (string)file_get_contents(dirname(__DIR__) . '/search.php');
$placeWidgetSource = (string)file_get_contents(dirname(__DIR__) . '/lib/widgets.php');
$placeHtaccessSource = (string)file_get_contents(dirname(__DIR__) . '/.htaccess');
if (!str_contains($placeSaveSource, "saveRevision(\$pdo, 'place'")) {
    $placesSourceIssues[] = 'place save is missing revision persistence';
}
if (!str_contains($placeSaveSource, 'upsertPathRedirect')) {
    $placesSourceIssues[] = 'place save is missing slug redirect persistence';
}
if (!str_contains($placeSaveSource, 'internalRedirectTarget(')) {
    $placesSourceIssues[] = 'place save is missing validated redirect handling';
}
if (!str_contains($placeFormSource, 'revisions.php?type=place')) {
    $placesSourceIssues[] = 'place form is missing revisions link';
}
if (!str_contains($placeFormSource, 'name="meta_title"') || !str_contains($placeFormSource, 'name="meta_description"')) {
    $placesSourceIssues[] = 'place form is missing SEO fields';
}
if (!str_contains($placeIndexControllerSource, "trim((string)(\$_GET['q'] ?? ''))")) {
    $placesSourceIssues[] = 'places index is missing public search support';
}
if (!str_contains($placeIndexControllerSource, "placePublicVisibilitySql('p')")) {
    $placesSourceIssues[] = 'places index is missing public visibility guard';
}
if (!str_contains($placeIndexControllerSource, 'renderPager(')) {
    $placesSourceIssues[] = 'places index is missing pagination support';
}
if (!str_contains($placeDetailControllerSource, 'placeStructuredData(')) {
    $placesSourceIssues[] = 'place detail is missing structured data';
}
if (!str_contains($placeDetailControllerSource, 'placePublicVisibilitySql()')) {
    $placesSourceIssues[] = 'place detail is missing public visibility guard';
}
if (!str_contains($placeIndexViewSource, 'Filtrovat adresář míst')) {
    $placesSourceIssues[] = 'places view is missing filter form';
}
if (!str_contains($placeIndexViewSource, 'listing-shell__pager')) {
    $placesSourceIssues[] = 'places view is missing pager output';
}
if (!str_contains($placeImageSource, "currentUserHasCapability('content_manage_shared')")) {
    $placesSourceIssues[] = 'places image endpoint is missing private visibility guard';
}
if (!str_contains($placeSearchSource, 'placePublicVisibilitySql()')) {
    $placesSourceIssues[] = 'search no longer protects place visibility';
}
if (!str_contains($placeWidgetSource, 'placePublicVisibilitySql()')) {
    $placesSourceIssues[] = 'places widget no longer protects public visibility';
}
if (!str_contains($placeHtaccessSource, 'RewriteRule ^uploads/places/ - [F,L,NC]')) {
    $placesSourceIssues[] = 'htaccess is missing places uploads deny rule';
}
if ($placesSourceIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($placesSourceIssues as $placesSourceIssue) {
        echo '- ' . $placesSourceIssue . "\n";
    }
}

echo "=== polls_source_guardrails ===\n";
$pollSourceIssues = [];
$pollSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/polls_save.php');
$pollFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/polls_form.php');
$pollListSource = (string)file_get_contents(dirname(__DIR__) . '/admin/polls.php');
$pollDeleteSource = (string)file_get_contents(dirname(__DIR__) . '/admin/polls_delete.php');
$pollIndexControllerSource = (string)file_get_contents(dirname(__DIR__) . '/polls/index.php');
$pollIndexViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/polls-index.php');
$pollPublicCssSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/assets/public.css');
$pollHomeSource = (string)file_get_contents(dirname(__DIR__) . '/index.php');
$pollContentSource = (string)file_get_contents(dirname(__DIR__) . '/lib/content.php');
$pollSearchSource = (string)file_get_contents(dirname(__DIR__) . '/search.php');
$pollSitemapSource = (string)file_get_contents(dirname(__DIR__) . '/sitemap.php');
$pollWidgetSource = (string)file_get_contents(dirname(__DIR__) . '/lib/widgets.php');
$pollExportSource = (string)file_get_contents(dirname(__DIR__) . '/admin/export.php');
$pollImportSource = (string)file_get_contents(dirname(__DIR__) . '/admin/import.php');
if (!str_contains($pollSaveSource, 'saveRevision(') || !str_contains($pollSaveSource, "'poll'")) {
    $pollSourceIssues[] = 'poll save is missing revision persistence';
}
if (!str_contains($pollSaveSource, 'upsertPathRedirect')) {
    $pollSourceIssues[] = 'poll save is missing slug redirect persistence';
}
if (!str_contains($pollSaveSource, 'internalRedirectTarget(')) {
    $pollSourceIssues[] = 'poll save is missing validated redirect handling';
}
if (str_contains($pollSaveSource, 'error_log(')
    || !str_contains($pollSaveSource, "koraLog('warning', 'admin poll save failed'")) {
    $pollSourceIssues[] = 'poll save no longer uses structured recoverable logging';
}
if (!str_contains($pollDeleteSource, 'internalRedirectTarget(')) {
    $pollSourceIssues[] = 'poll delete is missing validated redirect handling';
}
if (!str_contains($pollFormSource, 'revisions.php?type=poll')) {
    $pollSourceIssues[] = 'poll form is missing revisions link';
}
if (!str_contains($pollFormSource, 'name="meta_title"') || !str_contains($pollFormSource, 'name="meta_description"')) {
    $pollSourceIssues[] = 'poll form is missing SEO fields';
}
if (str_contains($pollFormSource, 'onclick="removeOption(this)"') || str_contains($pollFormSource, 'onclick="addOption()"')) {
    $pollSourceIssues[] = 'poll form still uses inline option editor onclick handlers';
}
foreach ([
    'data-poll-option-add',
    'data-poll-option-remove',
    "addButton.addEventListener('click', addOption)",
    "list.addEventListener('click'",
] as $pollOptionEditorFragment) {
    if (!str_contains($pollFormSource, $pollOptionEditorFragment)) {
        $pollSourceIssues[] = 'poll form is missing option editor fragment: ' . $pollOptionEditorFragment;
    }
}
if (!str_contains($pollListSource, 'paginate(')) {
    $pollSourceIssues[] = 'poll admin list is missing pagination support';
}
if (!str_contains($pollListSource, 'name="status"') || !str_contains($pollListSource, 'name="q"')) {
    $pollSourceIssues[] = 'poll admin list is missing filter controls';
}
if (!str_contains($pollIndexControllerSource, 'pollPublicVisibilitySql(')) {
    $pollSourceIssues[] = 'poll index is missing shared visibility helper';
}
if (!str_contains($pollIndexControllerSource, "trim((string)(\$_GET['q'] ?? ''))")) {
    $pollSourceIssues[] = 'poll index is missing public search support';
}
if (!str_contains($pollIndexControllerSource, 'paginate(')) {
    $pollSourceIssues[] = 'poll index is missing pagination support';
}
if (!str_contains($pollIndexViewSource, 'name="q"')) {
    $pollSourceIssues[] = 'poll public view is missing search field';
}
if (!str_contains($pollIndexViewSource, 'renderPager(')) {
    $pollSourceIssues[] = 'poll public view is missing pager output';
}
if (str_contains($pollIndexViewSource, 'poll-result__fill') || str_contains($pollIndexViewSource, 'style="width:')) {
    $pollSourceIssues[] = 'poll public results still use inline-width result bars';
}
if (!str_contains($pollIndexViewSource, '<progress')
    || !str_contains($pollIndexViewSource, 'class="poll-result__track"')
    || !str_contains($pollIndexViewSource, 'aria-label="Podíl hlasů pro možnost')) {
    $pollSourceIssues[] = 'poll public results are missing semantic progress bars';
}
foreach ([
    '.poll-result__track::-webkit-progress-value',
    '.poll-result__track::-moz-progress-bar',
] as $pollProgressCssFragment) {
    if (!str_contains($pollPublicCssSource, $pollProgressCssFragment)) {
        $pollSourceIssues[] = 'poll public CSS is missing progress styling fragment: ' . $pollProgressCssFragment;
    }
}
if (!str_contains($pollSearchSource, 'pollPublicVisibilitySql()')) {
    $pollSourceIssues[] = 'search no longer protects poll visibility';
}
if (!str_contains($pollSitemapSource, 'pollPublicVisibilitySql()')) {
    $pollSourceIssues[] = 'sitemap no longer protects poll visibility';
}
if (!str_contains($pollWidgetSource, "pollPublicVisibilitySql('', 'active')")) {
    $pollSourceIssues[] = 'poll widget is missing shared visibility helper';
}
if (!str_contains($pollHomeSource, "pollPublicVisibilitySql('p', 'active')")) {
    $pollSourceIssues[] = 'homepage poll highlight is missing shared visibility helper';
}
if (!str_contains($pollContentSource, 'pollPublicVisibilitySql(')) {
    $pollSourceIssues[] = 'poll shortcode embed is missing shared visibility helper';
}
foreach (['meta_title', 'meta_description'] as $pollFieldFragment) {
    if (!str_contains($pollExportSource, $pollFieldFragment)) {
        $pollSourceIssues[] = 'poll export is missing field fragment: ' . $pollFieldFragment;
    }
    if (!str_contains($pollImportSource, $pollFieldFragment)) {
        $pollSourceIssues[] = 'poll import is missing field fragment: ' . $pollFieldFragment;
    }
}
if ($pollSourceIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($pollSourceIssues as $pollSourceIssue) {
        echo '- ' . $pollSourceIssue . "\n";
    }
}
echo "=== media_library_guardrails ===\n";
$mediaLibraryIssues = [];
$mediaAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/media.php');
$mediaHelperSource = (string)file_get_contents(dirname(__DIR__) . '/lib/media_library.php');
$uploadHelperSource = (string)file_get_contents(dirname(__DIR__) . '/lib/uploads.php');
$publicFormsSource = (string)file_get_contents(dirname(__DIR__) . '/forms/index.php');
$presentationUploadSource = (string)file_get_contents(dirname(__DIR__) . '/lib/presentation.php');
$mediaSearchSource = (string)file_get_contents(dirname(__DIR__) . '/admin/content_reference_search.php');
$mediaExportSource = (string)file_get_contents(dirname(__DIR__) . '/admin/export.php');
$mediaImportSource = (string)file_get_contents(dirname(__DIR__) . '/admin/import.php');
$boardSaveSourceForUploads = (string)file_get_contents(dirname(__DIR__) . '/admin/board_save.php');
$downloadSaveSourceForUploads = (string)file_get_contents(dirname(__DIR__) . '/admin/download_save.php');
$galleryPhotoSaveSourceForUploads = (string)file_get_contents(dirname(__DIR__) . '/admin/gallery_photo_save.php');
$wpImportSourceForUploads = (string)file_get_contents(dirname(__DIR__) . '/admin/wp_import.php');
$estrankyImportSourceForUploads = (string)file_get_contents(dirname(__DIR__) . '/admin/estranky_import.php');
$estrankyPhotoDownloadSourceForUploads = (string)file_get_contents(dirname(__DIR__) . '/admin/estranky_download_photos.php');
$themeUploadSourceForUploads = (string)file_get_contents(dirname(__DIR__) . '/lib/theme.php');
$mediaHtaccessSource = (string)file_get_contents(dirname(__DIR__) . '/.htaccess');
$mediaFileEndpointSource = (string)file_get_contents(dirname(__DIR__) . '/media/file.php');
$mediaPreviewEndpointSource = (string)file_get_contents(dirname(__DIR__) . '/media/preview.php');
$mediaThumbEndpointSource = (string)file_get_contents(dirname(__DIR__) . '/media/thumb.php');
$mediaHttpIntegrationSource = is_file(dirname(__DIR__) . '/build/http_integration.php')
    ? (string)file_get_contents(dirname(__DIR__) . '/build/http_integration.php')
    : '';
if (!str_contains($mediaAdminSource, 'internalRedirectTarget(')) {
    $mediaLibraryIssues[] = 'media admin is missing validated redirect handling';
}
foreach ([
    'function koraInspectUploadedFile(array $file, array $options = []): array',
    'function koraStoreInspectedUpload(array $upload, string $directory, string $filename, array $options = []): array',
    'is_uploaded_file($tmpPath)',
    'move_uploaded_file(',
] as $uploadHelperFragment) {
    if (!str_contains($uploadHelperSource, $uploadHelperFragment)) {
        $mediaLibraryIssues[] = 'shared upload helper is missing fragment: ' . $uploadHelperFragment;
    }
}
if (!str_contains($mediaHelperSource, 'koraInspectUploadedFile(') || !str_contains($mediaHelperSource, 'koraStoreInspectedUpload(')) {
    $mediaLibraryIssues[] = 'media library upload path is not using shared upload helpers';
}
if (!str_contains($publicFormsSource, 'koraInspectUploadedFile(') || !str_contains($publicFormsSource, 'koraStoreInspectedUpload(')) {
    $mediaLibraryIssues[] = 'public form upload path is not using shared upload helpers';
}
if (!str_contains($presentationUploadSource, 'function storePresentationUploadedFile(') || !str_contains($presentationUploadSource, 'koraInspectUploadedFile(')) {
    $mediaLibraryIssues[] = 'presentation upload path is not using shared upload helpers';
}
foreach ([
    'admin/board_save.php' => [$boardSaveSourceForUploads, 'uploadBoardStoredFile('],
    'admin/download_save.php' => [$downloadSaveSourceForUploads, 'uploadDownloadStoredFile('],
    'admin/gallery_photo_save.php' => [$galleryPhotoSaveSourceForUploads, 'uploadGalleryPhotoImage('],
    'admin/wp_import.php' => [$wpImportSourceForUploads, 'koraStoreInspectedUpload('],
    'admin/estranky_import.php' => [$estrankyImportSourceForUploads, 'koraInspectUploadedFile('],
    'admin/estranky_download_photos.php' => [$estrankyPhotoDownloadSourceForUploads, 'koraInspectUploadedFile('],
    'lib/theme.php' => [$themeUploadSourceForUploads, 'koraInspectUploadedFile('],
] as $uploadSourceLabel => [$uploadSource, $expectedHelper]) {
    if (!str_contains($uploadSource, $expectedHelper)) {
        $mediaLibraryIssues[] = $uploadSourceLabel . ' is missing shared upload helper: ' . $expectedHelper;
    }
    if (str_contains($uploadSource, 'is_uploaded_file(') || str_contains($uploadSource, 'move_uploaded_file(') || str_contains($uploadSource, 'new finfo(FILEINFO_MIME_TYPE)') || str_contains($uploadSource, 'new \\finfo(FILEINFO_MIME_TYPE)')) {
        $mediaLibraryIssues[] = $uploadSourceLabel . ' still performs direct upload storage or MIME detection';
    }
}
foreach ([
    'lib/media_library.php' => $mediaHelperSource,
    'forms/index.php' => $publicFormsSource,
    'lib/presentation.php' => $presentationUploadSource,
] as $uploadSourceLabel => $uploadSource) {
    if (str_contains($uploadSource, 'new finfo(FILEINFO_MIME_TYPE)') || str_contains($uploadSource, 'new \\finfo(FILEINFO_MIME_TYPE)')) {
        $mediaLibraryIssues[] = $uploadSourceLabel . ' still performs direct MIME detection outside shared upload helper';
    }
}
if (!str_contains($mediaAdminSource, 'name="bulk_action"')) {
    $mediaLibraryIssues[] = 'media admin is missing bulk action selector';
}
if (!str_contains($mediaAdminSource, 'make_public') || !str_contains($mediaAdminSource, 'make_private') || !str_contains($mediaAdminSource, 'delete_unused')) {
    $mediaLibraryIssues[] = 'media admin is missing expected bulk actions';
}
if (!str_contains($mediaAdminSource, 'name="caption"') || !str_contains($mediaAdminSource, 'name="credit"') || !str_contains($mediaAdminSource, 'name="visibility"')) {
    $mediaLibraryIssues[] = 'media admin is missing metadata fields for caption, credit or visibility';
}
if (!str_contains($mediaAdminSource, 'navigator.clipboard.writeText')) {
    $mediaLibraryIssues[] = 'media admin is missing clipboard copy helper';
}
if (!str_contains($mediaAdminSource, 'window.prompt(')) {
    $mediaLibraryIssues[] = 'media admin is missing clipboard fallback dialog';
}
if (str_contains($mediaAdminSource, 'image/svg+xml,image/svg')) {
    $mediaLibraryIssues[] = 'media admin upload accept still allows SVG';
}
if (str_contains($mediaAdminSource, '<style') || str_contains($mediaAdminSource, 'style=') || str_contains($mediaAdminSource, '.style')) {
    $mediaLibraryIssues[] = 'media admin still contains local style blocks, inline style markup or JS style mutations';
}
foreach ([
    'class="media-upload-form"',
    'class="media-filter-grid"',
    'class="media-grid"',
    'class="media-card"',
    'class="media-card__image"',
    'class="media-edit-section"',
    'class="media-info-list"',
    'class="media-usage-list"',
] as $mediaAdminClassFragment) {
    if (!str_contains($mediaAdminSource, $mediaAdminClassFragment)) {
        $mediaLibraryIssues[] = 'media admin is missing shared CSS class fragment: ' . $mediaAdminClassFragment;
    }
}
if (!str_contains($mediaHelperSource, 'SVG soubory už knihovna médií nepřijímá')) {
    $mediaLibraryIssues[] = 'media helper is missing explicit SVG upload rejection';
}
if (!str_contains($mediaHelperSource, "return BASE_URL . '/media/file.php?id='")) {
    $mediaLibraryIssues[] = 'media helper is missing canonical protected file URL helper';
}
if (!str_contains($mediaHelperSource, 'function mediaPreviewUrl(array $media): string')) {
    $mediaLibraryIssues[] = 'media helper is missing canonical pdf preview URL helper';
}
if (!str_contains($mediaHelperSource, 'function mediaGetPublicPdfByUrl(string $url): ?array')) {
    $mediaLibraryIssues[] = 'media helper is missing public pdf resolution helper for legacy snippets';
}
if (!str_contains($mediaHelperSource, "return BASE_URL . '/media/thumb.php?id='")) {
    $mediaLibraryIssues[] = 'media helper is missing canonical protected thumb URL helper';
}
if (!str_contains($mediaSearchSource, "visibility = 'public'")) {
    $mediaLibraryIssues[] = 'content reference search no longer filters media to public visibility';
}
if (!str_contains($mediaSearchSource, 'mediaFileUrl($row)')) {
    $mediaLibraryIssues[] = 'content reference search is missing canonical media file URL helper';
}
if (!str_contains($mediaSearchSource, 'mediaThumbUrl($row)')) {
    $mediaLibraryIssues[] = 'content reference search is missing canonical media thumb URL helper';
}
if (!str_contains($mediaExportSource, "'media'")) {
    $mediaLibraryIssues[] = 'export is missing media table payload';
}
foreach (['caption', 'credit', 'visibility'] as $mediaFieldFragment) {
    if (!str_contains($mediaExportSource, $mediaFieldFragment)) {
        $mediaLibraryIssues[] = 'export is missing media field fragment: ' . $mediaFieldFragment;
    }
    if (!str_contains($mediaImportSource, $mediaFieldFragment)) {
        $mediaLibraryIssues[] = 'import is missing media field fragment: ' . $mediaFieldFragment;
    }
}
if (!str_contains($mediaHtaccessSource, 'RewriteRule ^uploads/media/.+\.svg$ - [F,L,NC]')) {
    $mediaLibraryIssues[] = 'htaccess is missing SVG deny rule for uploads/media';
}
if (!str_contains($mediaFileEndpointSource, 'mediaStaffCanAccessPrivate()')) {
    $mediaLibraryIssues[] = 'media file endpoint is missing private access guard';
}
foreach ([
    'mediaCanPreviewPdf($media)',
    'sendStoredFileInline(',
    'X-Frame-Options: SAMEORIGIN',
    "frame-ancestors 'self'",
] as $mediaPreviewFragment) {
    if (!str_contains($mediaPreviewEndpointSource, $mediaPreviewFragment)) {
        $mediaLibraryIssues[] = 'media preview endpoint is missing fragment: ' . $mediaPreviewFragment;
    }
}
if (!str_contains($mediaThumbEndpointSource, 'mediaStaffCanAccessPrivate()')) {
    $mediaLibraryIssues[] = 'media thumb endpoint is missing private access guard';
}
if (!str_contains($mediaHtaccessSource, 'SetEnvIfNoCase Request_URI "(^|/)media/preview\.php$" KORA_MEDIA_PREVIEW=1')) {
    $mediaLibraryIssues[] = 'htaccess is missing media preview SAMEORIGIN exception';
}
if (!str_contains($mediaHtaccessSource, 'Header always set X-Frame-Options "SAMEORIGIN" env=KORA_MEDIA_PREVIEW')) {
    $mediaLibraryIssues[] = 'htaccess is missing preview SAMEORIGIN X-Frame-Options header';
}
if ($mediaHttpIntegrationSource === '') {
    $mediaLibraryIssues[] = 'build/http_integration.php is missing for media admin coverage';
} else {
    foreach ([
        "httpIntegrationPrintResult('media_admin_http'",
        '/admin/media.php',
        "'action' => 'upload'",
        "'action' => 'update_meta'",
        "'action' => 'replace'",
        "'action' => 'delete'",
        "'action' => 'bulk'",
        "'bulk_action' => 'make_private'",
        "'bulk_action' => 'delete_unused'",
        'Použité médium nelze přepnout do soukromého režimu',
        'Použité médium nelze smazat',
        '/media/preview.php?id=',
        'content-disposition: inline;',
        'x-frame-options: sameorigin',
    ] as $mediaIntegrationFragment) {
        if (!str_contains($mediaHttpIntegrationSource, $mediaIntegrationFragment)) {
            $mediaLibraryIssues[] = 'media http integration is missing fragment: ' . $mediaIntegrationFragment;
        }
    }
}
if ($mediaLibraryIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($mediaLibraryIssues as $mediaLibraryIssue) {
        echo '- ' . $mediaLibraryIssue . "\n";
    }
}

echo "=== public_upload_svg_guardrails ===\n";
$publicUploadSvgIssues = [];
$sharedPresentationSource = (string)file_get_contents(dirname(__DIR__) . '/lib/presentation.php');
$blogsAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blogs.php');
$boardFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/board_form.php');
$downloadFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/download_form.php');
$eventFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/event_form.php');
$placeFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/place_form.php');
$profileAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/profile.php');
$userFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/user_form.php');
$settingsAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/settings.php');
$settingsSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/settings_save.php');

foreach ([
    'function uploadDownloadImage',
    'function uploadBlogLogo',
    'function uploadPlaceImage',
    'function uploadEventImage',
    'function uploadBoardImage',
    'function storeUploadedAuthorAvatar',
] as $uploadHelperName) {
    $helperPosition = strpos($sharedPresentationSource, $uploadHelperName);
    if ($helperPosition === false) {
        $publicUploadSvgIssues[] = 'presentation helper is missing expected upload helper: ' . $uploadHelperName;
        continue;
    }

    $helperSlice = substr($sharedPresentationSource, $helperPosition, 1400);
    if (str_contains($helperSlice, 'image/svg+xml')) {
        $publicUploadSvgIssues[] = 'upload helper still allows SVG: ' . $uploadHelperName;
    }
}

foreach ([
    ['source' => $blogsAdminSource, 'fragment' => 'image/svg+xml', 'issue' => 'blog admin still allows SVG logos'],
    ['source' => $blogsAdminSource, 'fragment' => 'WebP a SVG', 'issue' => 'blog admin still advertises SVG logos'],
    ['source' => $boardFormSource, 'fragment' => '.svg', 'issue' => 'board form still allows SVG images'],
    ['source' => $boardFormSource, 'fragment' => 'nebo SVG', 'issue' => 'board form still advertises SVG images'],
    ['source' => $downloadFormSource, 'fragment' => '.svg', 'issue' => 'download form still allows SVG preview images'],
    ['source' => $eventFormSource, 'fragment' => '.svg', 'issue' => 'event form still allows SVG images'],
    ['source' => $placeFormSource, 'fragment' => '.svg', 'issue' => 'place form still allows SVG images'],
    ['source' => $placeFormSource, 'fragment' => 'nebo SVG', 'issue' => 'place form still advertises SVG images'],
    ['source' => $profileAdminSource, 'fragment' => '.svg', 'issue' => 'profile form still allows SVG avatars'],
    ['source' => $profileAdminSource, 'fragment' => 'nebo SVG', 'issue' => 'profile form still advertises SVG avatars'],
    ['source' => $userFormSource, 'fragment' => '.svg', 'issue' => 'user form still allows SVG avatars'],
    ['source' => $userFormSource, 'fragment' => 'nebo SVG', 'issue' => 'user form still advertises SVG avatars'],
    ['source' => $settingsAdminSource, 'fragment' => 'image/svg+xml', 'issue' => 'settings admin still allows SVG site assets'],
    ['source' => $settingsAdminSource, 'fragment' => '.svg', 'issue' => 'settings admin still advertises SVG site assets'],
    ['source' => $settingsAdminSource, 'fragment' => 'Povolené formáty: ICO, PNG nebo SVG', 'issue' => 'settings admin still advertises SVG favicons'],
    ['source' => $settingsAdminSource, 'fragment' => 'Povolené formáty: JPEG, PNG, WebP nebo SVG', 'issue' => 'settings admin still advertises SVG logos'],
] as $svgGuard) {
    if (str_contains((string)$svgGuard['source'], (string)$svgGuard['fragment'])) {
        $publicUploadSvgIssues[] = (string)$svgGuard['issue'];
    }
}

foreach ([
    '$siteFaviconMaxBytes = 256 * 1024',
    '$siteLogoMaxBytes = 2 * 1024 * 1024',
    'Favicon může mít nejvýše 256 KB.',
    'Logo může mít nejvýše 2 MB.',
] as $settingsUploadFragment) {
    if (!str_contains($settingsSaveSource, $settingsUploadFragment) && !str_contains($settingsAdminSource, $settingsUploadFragment)) {
        $publicUploadSvgIssues[] = 'settings admin is missing upload size validation fragment: ' . $settingsUploadFragment;
    }
}

if ($publicUploadSvgIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($publicUploadSvgIssues as $publicUploadSvgIssue) {
        echo '- ' . $publicUploadSvgIssue . "\n";
    }
}

echo "=== settings_prg_guardrails ===\n";
$settingsPrgIssues = [];
$settingsSharedSource = (string)file_get_contents(dirname(__DIR__) . '/admin/settings_shared.php');
$httpIntegrationSource = is_file(dirname(__DIR__) . '/build/http_integration.php')
    ? (string)file_get_contents(dirname(__DIR__) . '/build/http_integration.php')
    : '';
$readmeSource = (string)file_get_contents(dirname(__DIR__) . '/README.md');
foreach ([
    "require_once __DIR__ . '/settings_shared.php';",
    '$flash = settingsFlashPull();',
    'action="settings_save.php"',
    "adminFieldAttributes('site_name'",
    "adminFieldAttributes('contact_email'",
    "adminFieldAttributes('board_public_label'",
    "adminFieldAttributes('github_issues_repository'",
    "adminFieldAttributes('site_favicon'",
    "adminFieldAttributes('site_logo'",
] as $settingsViewFragment) {
    if (!str_contains($settingsAdminSource, $settingsViewFragment)) {
        $settingsPrgIssues[] = 'settings page is missing PRG/view fragment: ' . $settingsViewFragment;
    }
}
if (str_contains($settingsAdminSource, '<style') || str_contains($settingsAdminSource, 'style=') || str_contains($settingsAdminSource, '.style')) {
    $settingsPrgIssues[] = 'settings page still contains local style blocks, inline style markup or JS style mutations';
}
foreach ([
    'class="button-row settings-nav"',
    'class="settings-profile-card"',
    'class="settings-code-textarea"',
    'class="settings-current-logo"',
    'class="btn settings-submit"',
] as $settingsStyleFragment) {
    if (!str_contains($settingsAdminSource, $settingsStyleFragment)) {
        $settingsPrgIssues[] = 'settings page is missing CSS class fragment: ' . $settingsStyleFragment;
    }
}
foreach ([
    'function settingsFlashPull(): array',
    'function settingsFlashSet(array $flash): void',
    'function settingsDefaultFormState(): array',
] as $settingsSharedFragment) {
    if (!str_contains($settingsSharedSource, $settingsSharedFragment)) {
        $settingsPrgIssues[] = 'settings shared helper is missing fragment: ' . $settingsSharedFragment;
    }
}
foreach ([
    '$pdo->beginTransaction();',
    'settingsFlashSet([',
    "'field_errors' => array_values(array_unique(\$fieldErrors))",
    "'success' => \$successMessage",
    "header('Location: ' . BASE_URL . '/admin/settings.php');",
    '$movedFiles = [];',
    '$generatedWebpFiles = [];',
    'koraInspectUploadedFile(',
    'koraStoreInspectedUpload(',
] as $settingsSaveFragment) {
    if (!str_contains($settingsSaveSource, $settingsSaveFragment)) {
        $settingsPrgIssues[] = 'settings save handler is missing PRG/atomic fragment: ' . $settingsSaveFragment;
    }
}
if (str_contains($settingsSaveSource, 'is_uploaded_file(') || str_contains($settingsSaveSource, 'move_uploaded_file(') || str_contains($settingsSaveSource, 'new finfo(FILEINFO_MIME_TYPE)')) {
    $settingsPrgIssues[] = 'settings save handler still performs direct upload storage or MIME detection';
}
foreach ([
    'settings-social',
    'social_facebook',
    'social_youtube',
    'social_instagram',
    'social_twitter',
] as $legacySocialFragment) {
    if (str_contains($settingsAdminSource, $legacySocialFragment)) {
        $settingsPrgIssues[] = 'settings page still contains legacy social links field: ' . $legacySocialFragment;
    }
    if (str_contains($settingsSharedSource, $legacySocialFragment)) {
        $settingsPrgIssues[] = 'settings shared state still contains legacy social links field: ' . $legacySocialFragment;
    }
    if (str_contains($settingsSaveSource, $legacySocialFragment)) {
        $settingsPrgIssues[] = 'settings save handler still persists legacy social links field: ' . $legacySocialFragment;
    }
}
if ($httpIntegrationSource === '') {
    $settingsPrgIssues[] = 'build/http_integration.php is missing';
} else {
    foreach ([
        "require_once __DIR__ . '/http_test_helpers.php';",
        'function httpIntegrationRestoreSettings(array $settings): void',
        '/admin/settings_save.php',
        '/reservations/book.php',
        '/admin/blog_save.php',
        '/admin/blog_transfer.php',
        '/admin/media.php',
        'social_links_widget_http',
        'httpIntegrationRestoreSettings($originalSettings);',
        'editace článku přes blog_save',
        'editace článku s ruční volbou',
        'editace článku s vytvořením taxonomií',
        'nepovolené vytvoření taxonomií v editoru článku',
        'uložený blog jako samostatnou informaci',
        'podvržená cizí kategorie v editoru článku',
        'podvržené cizí štítky v editoru článku',
        ] as $integrationFragment) {
        if (!str_contains($httpIntegrationSource, $integrationFragment)) {
            $settingsPrgIssues[] = 'http integration script is missing scenario fragment: ' . $integrationFragment;
        }
    }
}
if (!str_contains($readmeSource, 'php build/http_integration.php')) {
    $settingsPrgIssues[] = 'README is missing http integration verification command';
}
if ($settingsPrgIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($settingsPrgIssues as $settingsPrgIssue) {
        echo '- ' . $settingsPrgIssue . "\n";
    }
}

echo "=== settings_modules_prg_guardrails ===\n";
$settingsModulesPrgIssues = [];
$settingsModulesSource = (string)file_get_contents(dirname(__DIR__) . '/admin/settings_modules.php');
foreach ([
    '$_SESSION[\'settings_modules_flash\']',
    'clearSettingsCache();',
    "header('Location: ' . BASE_URL . '/admin/settings_modules.php');",
    '$successMessage = trim((string)($flash[\'success\'] ?? \'\'))',
] as $settingsModulesFragment) {
    if (!str_contains($settingsModulesSource, $settingsModulesFragment)) {
        $settingsModulesPrgIssues[] = 'settings modules screen is missing PRG fragment: ' . $settingsModulesFragment;
    }
}
if ($httpIntegrationSource === '') {
    $settingsModulesPrgIssues[] = 'build/http_integration.php is missing for module settings coverage';
} else {
    foreach ([
        "httpIntegrationPrintResult('settings_modules_http'",
        'Nastavení modulů bylo uloženo.',
        "saveSetting('module_forms', '0');",
        "httpIntegrationCheckboxIsChecked(\$settingsModulesSuccessPage['body'], 'module_forms')",
        'success flash zpráva správy modulů po druhém načtení nezmizela',
    ] as $settingsModulesIntegrationFragment) {
        if (!str_contains($httpIntegrationSource, $settingsModulesIntegrationFragment)) {
            $settingsModulesPrgIssues[] = 'settings modules integration is missing fragment: ' . $settingsModulesIntegrationFragment;
        }
    }
}
if ($settingsModulesPrgIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($settingsModulesPrgIssues as $settingsModulesPrgIssue) {
        echo '- ' . $settingsModulesPrgIssue . "\n";
    }
}

echo "=== public_forms_http_guardrails ===\n";
$publicFormsHttpIssues = [];
$formsControllerSource = (string)file_get_contents(dirname(__DIR__) . '/forms/index.php');
$formsViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/forms-show.php');
$formsHelperSource = (string)file_get_contents(dirname(__DIR__) . '/lib/presentation.php');
if ($httpIntegrationSource === '') {
    $publicFormsHttpIssues[] = 'build/http_integration.php is missing for public forms coverage';
} else {
    foreach ([
        "httpIntegrationPrintResult('public_form_submit_http'",
        'formPublicPath([',
        'Chybná odpověď na ověřovací otázku.',
        'Vybraný typ souboru není v tomto poli povolený.',
        'httpIntegrationExtractCaptchaAnswer(',
        'httpIntegrationListStoredFormUploads(',
        "'attachment' => [",
        'httpIntegrationFetchLatestFormSubmissionByFormId(',
    ] as $publicFormsIntegrationFragment) {
        if (!str_contains($httpIntegrationSource, $publicFormsIntegrationFragment)) {
            $publicFormsHttpIssues[] = 'public forms http integration is missing fragment: ' . $publicFormsIntegrationFragment;
        }
    }
}
foreach ([
    'verifyCsrf();',
    'captchaVerify($_POST[\'captcha\'] ?? \'\')',
    'storePublicFormUploads(',
    'formDeleteUploadedFile(',
    '$fieldErrors = [];',
    '$addFieldError = static function',
] as $formsControllerFragment) {
    if (!str_contains($formsControllerSource, $formsControllerFragment)) {
        $publicFormsHttpIssues[] = 'forms controller is missing fragment: ' . $formsControllerFragment;
    }
}
if (!str_contains($formsHelperSource, 'function formDeleteUploadedFilesFromSubmissionData(')) {
    $publicFormsHttpIssues[] = 'forms helper is missing uploaded-file cleanup helper';
}
foreach ([
    'id="form-errors"',
    'role="alert"',
    'type="file"',
    'name="captcha"',
    'aria-invalid="true"',
    'field-error',
    'captcha-error',
] as $formsViewFragment) {
    if (!str_contains($formsViewSource, $formsViewFragment)) {
        $publicFormsHttpIssues[] = 'forms view is missing fragment: ' . $formsViewFragment;
    }
}
if ($publicFormsHttpIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($publicFormsHttpIssues as $publicFormsHttpIssue) {
        echo '- ' . $publicFormsHttpIssue . "\n";
    }
}

echo "=== editorial_validation_guardrails ===\n";
$editorialValidationIssues = [];
$pageSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/page_save.php');
$pageFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/page_form.php');
$foodSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/food_save.php');
$foodFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/food_form.php');
$blogSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_save.php');
$blogFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_form.php');
$podcastEpisodeSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/podcast_save.php');
$podcastEpisodeFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/podcast_form.php');
$pollSaveValidationSource = (string)file_get_contents(dirname(__DIR__) . '/admin/polls_save.php');
$pollFormValidationSource = (string)file_get_contents(dirname(__DIR__) . '/admin/polls_form.php');
$boardSaveValidationSource = (string)file_get_contents(dirname(__DIR__) . '/admin/board_save.php');
$boardFormValidationSource = (string)file_get_contents(dirname(__DIR__) . '/admin/board_form.php');
$reservationSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/res_resource_save.php');
$reservationFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/res_resource_form.php');
$reservationBookingSource = (string)file_get_contents(dirname(__DIR__) . '/reservations/book.php');
if (!str_contains($pageSaveSource, 'validateDateTimeLocal($unpublishAt)')) {
    $editorialValidationIssues[] = 'page save is missing datetime parsing for unpublish_at';
}
if (!str_contains($pageSaveSource, "'err' => 'unpublish_at'")) {
    $editorialValidationIssues[] = 'page save is missing invalid unpublish_at rejection';
}
if (!str_contains($pageSaveSource, "'unpublish_at' => \$unpublishAtSql") || !str_contains($pageSaveSource, "'admin_note' => \$adminNote")) {
    $editorialValidationIssues[] = 'page save revision snapshot is missing unpublish_at or admin_note';
}
if (!str_contains($pageFormSource, "\$err === 'unpublish_at'")) {
    $editorialValidationIssues[] = 'page form is missing invalid unpublish_at error feedback';
}
if (!str_contains($foodSaveSource, "DateTime::createFromFormat('Y-m-d'")) {
    $editorialValidationIssues[] = 'food save is missing strict date parsing for validity fields';
}
foreach (["'err' => 'valid_from'", "'err' => 'valid_to'", "'err' => 'valid_range'"] as $foodErrorFragment) {
    if (!str_contains($foodSaveSource, $foodErrorFragment)) {
        $editorialValidationIssues[] = 'food save is missing validation redirect: ' . $foodErrorFragment;
    }
}
foreach (["'valid_from' =>", "'valid_to' =>", "'valid_range' =>"] as $foodFormFragment) {
    if (!str_contains($foodFormSource, $foodFormFragment)) {
        $editorialValidationIssues[] = 'food form is missing validation message branch: ' . $foodFormFragment;
    }
}
if (!str_contains($blogSaveSource, 'validateDateTimeLocal($publishAt)') || !str_contains($blogSaveSource, 'validateDateTimeLocal($unpublishAt)')) {
    $editorialValidationIssues[] = 'blog save is missing strict publish/unpublish datetime parsing';
}
foreach (["'publish_at'", "'unpublish_at'", "'publish_range'"] as $blogErrorFragment) {
    if (!str_contains($blogSaveSource, $blogErrorFragment)) {
        $editorialValidationIssues[] = 'blog save is missing validation branch: ' . $blogErrorFragment;
    }
}
if (!str_contains($blogSaveSource, "'publish_at' => \$publishAtSql") || !str_contains($blogSaveSource, "'unpublish_at' => \$unpublishAtSql")) {
    $editorialValidationIssues[] = 'blog save revision snapshot is missing publish_at or unpublish_at';
}
foreach (["\$err === 'publish_at'", "\$err === 'unpublish_at'", "\$err === 'publish_range'"] as $blogFormFragment) {
    if (!str_contains($blogFormSource, $blogFormFragment)) {
        $editorialValidationIssues[] = 'blog form is missing validation message branch: ' . $blogFormFragment;
    }
}
if (!str_contains($blogSaveSource, 'uploadArticleImage(') || str_contains($blogSaveSource, 'new finfo(FILEINFO_MIME_TYPE)') || str_contains($blogSaveSource, 'move_uploaded_file(')) {
    $editorialValidationIssues[] = 'blog save article image upload is not using shared upload helpers';
}
if (!str_contains($blogFormSource, "'image_upload' => ['image']") || !str_contains($blogFormSource, 'blog-image-error')) {
    $editorialValidationIssues[] = 'blog form is missing article image upload error feedback';
}
if (!str_contains($podcastEpisodeSaveSource, 'validateDateTimeLocal($publishAtInput)') || !str_contains($podcastEpisodeSaveSource, "redirectWithError('publish_at')")) {
    $editorialValidationIssues[] = 'podcast episode save is missing strict publish_at validation';
}
if (!str_contains($podcastEpisodeFormSource, "'publish_at' =>")) {
    $editorialValidationIssues[] = 'podcast episode form is missing publish_at validation message';
}
if (!str_contains($pollSaveValidationSource, "DateTime::createFromFormat('Y-m-d'") || !str_contains($pollSaveValidationSource, "DateTime::createFromFormat('H:i'")) {
    $editorialValidationIssues[] = 'poll save is missing strict start/end date and time parsing';
}
if (!str_contains($pollSaveValidationSource, "redirectToForm(\$id, 'dates'")) {
    $editorialValidationIssues[] = 'poll save is missing invalid date/time rejection';
}
if (!str_contains($pollFormValidationSource, "'dates' =>")) {
    $editorialValidationIssues[] = 'poll form is missing invalid date/time feedback';
}
if (!str_contains($boardSaveValidationSource, "DateTimeImmutable::createFromFormat('!Y-m-d'")) {
    $editorialValidationIssues[] = 'board save is missing strict board date parsing';
}
foreach (["'posted_date'", "'removal_date'", "'dates'"] as $boardErrorFragment) {
    if (!str_contains($boardSaveValidationSource, $boardErrorFragment)) {
        $editorialValidationIssues[] = 'board save is missing validation branch: ' . $boardErrorFragment;
    }
}
foreach (["'posted_date' =>", "'removal_date' =>", "'posted_date_invalid' =>", "\$err === 'removal_date'"] as $boardFormFragment) {
    if (!str_contains($boardFormValidationSource, $boardFormFragment)) {
        $editorialValidationIssues[] = 'board form is missing validation message branch: ' . $boardFormFragment;
    }
}
if (!str_contains($reservationSaveSource, '$pdo->beginTransaction()') || !str_contains($reservationSaveSource, '$pdo->rollBack()')) {
    $editorialValidationIssues[] = 'reservation resource save is missing transactional protection';
}
if (!str_contains($reservationSaveSource, "DateTime::createFromFormat('H:i'")) {
    $editorialValidationIssues[] = 'reservation resource save is missing strict time parsing';
}
if (!str_contains($reservationSaveSource, "DateTime::createFromFormat('Y-m-d'")) {
    $editorialValidationIssues[] = 'reservation resource save is missing strict blocked-date parsing';
}
if (!str_contains($reservationBookingSource, "DateTime::createFromFormat('!Y-m-d', \$dateStr)")) {
    $editorialValidationIssues[] = 'reservation booking is missing strict booking date parsing';
}
if (!str_contains($reservationBookingSource, 'DateTime::getLastErrors()')) {
    $editorialValidationIssues[] = 'reservation booking is missing invalid calendar date rejection';
}
foreach ([
    "redirectToForm(\$id, 'name')",
    "redirectToForm(\$id, 'slug')",
    "redirectToForm(\$id, 'hours')",
    "redirectToForm(\$id, 'slots')",
    "redirectToForm(\$id, 'blocked_date')",
    "redirectToForm(\$id, 'save')",
] as $reservationRedirectFragment) {
    if (!str_contains($reservationSaveSource, $reservationRedirectFragment)) {
        $editorialValidationIssues[] = 'reservation resource save is missing validation redirect: ' . $reservationRedirectFragment;
    }
}
foreach (["\$err === 'hours'", "\$err === 'slots'", "\$err === 'blocked_date'", "\$err === 'save'"] as $reservationFormFragment) {
    if (!str_contains($reservationFormSource, $reservationFormFragment)) {
        $editorialValidationIssues[] = 'reservation resource form is missing error feedback branch: ' . $reservationFormFragment;
    }
}
if ($editorialValidationIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($editorialValidationIssues as $editorialValidationIssue) {
        echo '- ' . $editorialValidationIssue . "\n";
    }
}

echo "=== utf8_copy_guardrails ===\n";
$utf8CopyIssues = [];
$runtimeAuditSource = (string)file_get_contents(__FILE__);
$migrateSource = (string)file_get_contents(dirname(__DIR__) . '/migrate.php');
$blogFormSourceForUtf8 = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_form.php');
foreach ([
    'Windows 11 nebo novější, 4 GB RAM.',
    'Otevřít návrh na GitHubu',
    'Připojit existující issue',
    'Repozitář',
    'Veřejná stránka',
    'Veřejná stránka zdroje',
    '>Správa zdrojů<',
] as $expectedUtf8Fragment) {
    if (!str_contains($runtimeAuditSource, $expectedUtf8Fragment)) {
        $utf8CopyIssues[] = 'runtime audit is missing expected UTF-8 fragment: ' . $expectedUtf8Fragment;
    }
}
$legacyFoodSlugMojibakeProbe = hex2bin('c3a2c59be2809420536c756779206ac482c2ad64656c6ec482c2ad6368206cc482c2ad73746bc4b9c5bb20c3a2e282ace2809c2043485942413a');
if (is_string($legacyFoodSlugMojibakeProbe) && str_contains($migrateSource, $legacyFoodSlugMojibakeProbe)) {
    $utf8CopyIssues[] = 'migrate.php still contains mojibake in food slug migration log';
}
$legacyCategoryOptionMojibakeProbe = hex2bin('c3a2e282ace2809c2062657a206b617465676f72696520c3a2e282ace2809c');
if (is_string($legacyCategoryOptionMojibakeProbe) && str_contains($blogFormSourceForUtf8, $legacyCategoryOptionMojibakeProbe)) {
    $utf8CopyIssues[] = 'blog form still contains mojibake in legacy category option copy';
}
foreach ([
    'mb_check_encoding($json, \'UTF-8\')' => 'admin import is missing explicit UTF-8 validation',
    'str_starts_with($json, "\\xEF\\xBB\\xBF")' => 'admin import is missing UTF-8 BOM handling',
    'JSON_THROW_ON_ERROR' => 'admin import is missing strict JSON decode error handling',
] as $importUtf8Fragment => $importUtf8Issue) {
    if (!str_contains($blogImportSource, $importUtf8Fragment)) {
        $utf8CopyIssues[] = $importUtf8Issue;
    }
}
if (!str_contains($blogExportSource, 'JSON_UNESCAPED_UNICODE')) {
    $utf8CopyIssues[] = 'admin export is missing JSON_UNESCAPED_UNICODE for UTF-8-safe JSON output';
}
if ($utf8CopyIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($utf8CopyIssues as $utf8CopyIssue) {
        echo '- ' . $utf8CopyIssue . "\n";
    }
}

echo "=== admin_field_error_guardrails ===\n";
$adminFieldErrorIssues = [];
$adminLayoutSource = (string)file_get_contents(dirname(__DIR__) . '/admin/layout.php');
$adminLayoutCssSource = (string)file_get_contents(dirname(__DIR__) . '/admin/assets/layout.css');
$pageFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/page_form.php');
$blogFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_form.php');
$newsFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/news_form.php');
$faqFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/faq_form.php');
$foodFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/food_form.php');
$pollFormValidationSource = (string)file_get_contents(dirname(__DIR__) . '/admin/polls_form.php');
$pollsOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/polls.php');
$formBuilderSource = (string)file_get_contents(dirname(__DIR__) . '/admin/form_form.php');
$formSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/form_save.php');
$presentationHelpersSource = (string)file_get_contents(dirname(__DIR__) . '/lib/presentation.php');
$boardCatsSource = (string)file_get_contents(dirname(__DIR__) . '/admin/board_cats.php');
$boardFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/board_form.php');
$boardOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/board.php');
$downloadCatsSource = (string)file_get_contents(dirname(__DIR__) . '/admin/dl_cats.php');
$eventFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/event_form.php');
$faqCatsSource = (string)file_get_contents(dirname(__DIR__) . '/admin/faq_cats.php');
$faqOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/faq.php');
$formsOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/forms.php');
$formSubmissionDetailSource = (string)file_get_contents(dirname(__DIR__) . '/admin/form_submission.php');
$formSubmissionsOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/form_submissions.php');
$galleryAlbumsOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/gallery_albums.php');
$galleryPhotosOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/gallery_photos.php');
$importAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/import.php');
$wpImportSource = (string)file_get_contents(dirname(__DIR__) . '/admin/wp_import.php');
$estrankyImportSource = (string)file_get_contents(dirname(__DIR__) . '/admin/estranky_import.php');
$estrankyPhotoDownloadSource = (string)file_get_contents(dirname(__DIR__) . '/admin/estranky_download_photos.php');
$placeFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/place_form.php');
$podcastEpisodeFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/podcast_form.php');
$podcastShowFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/podcast_show_form.php');
$reservationCategoriesSource = (string)file_get_contents(dirname(__DIR__) . '/admin/res_categories.php');
$reservationFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/res_resource_form.php');
$reservationBookingAddFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/res_booking_add.php');
$reservationBookingDetailSource = (string)file_get_contents(dirname(__DIR__) . '/admin/res_booking_detail.php');
$reservationBookingsOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/res_bookings.php');
$reservationLocationsSource = (string)file_get_contents(dirname(__DIR__) . '/admin/res_locations.php');
$reservationResourcesSource = (string)file_get_contents(dirname(__DIR__) . '/admin/res_resources.php');
$revisionsAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/revisions.php');
$reviewQueueSource = (string)file_get_contents(dirname(__DIR__) . '/admin/review_queue.php');
$redirectsAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/redirects.php');
$galleryAlbumFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/gallery_album_form.php');
$galleryPhotoFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/gallery_photo_form.php');
$userFormValidationSource = (string)file_get_contents(dirname(__DIR__) . '/admin/user_form.php');
$userSaveValidationSource = (string)file_get_contents(dirname(__DIR__) . '/admin/user_save.php');
$profileFormValidationSource = (string)file_get_contents(dirname(__DIR__) . '/admin/profile.php');
$auditLogSource = (string)file_get_contents(dirname(__DIR__) . '/admin/audit_log.php');
$backupAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/backup.php');
$integrityAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/integrity.php');
$blogsManagementSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blogs.php');
$blogOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog.php');
$blogTransferAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_transfer.php');
$chatOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/chat.php');
$chatMessageDetailSource = (string)file_get_contents(dirname(__DIR__) . '/admin/chat_message.php');
$commentsOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/comments.php');
$contactOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/contact.php');
$contactMessageDetailSource = (string)file_get_contents(dirname(__DIR__) . '/admin/contact_message.php');
$downloadsOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/downloads.php');
$eventsOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/events.php');
$foodOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/food.php');
$newsOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/news.php');
$newsletterOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/newsletter.php');
$newsletterHistorySource = (string)file_get_contents(dirname(__DIR__) . '/admin/newsletter_history.php');
$pagesOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/pages.php');
$placesOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/places.php');
$podcastEpisodesOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/podcast.php');
$podcastShowsOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/podcast_shows.php');
$menuOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/menu.php');
$settingsDisplaySource = (string)file_get_contents(dirname(__DIR__) . '/admin/settings_display.php');
$trashAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/trash.php');
$usersOverviewSource = (string)file_get_contents(dirname(__DIR__) . '/admin/users.php');
$newsletterFormValidationSource = (string)file_get_contents(dirname(__DIR__) . '/admin/newsletter_form.php');
$newsletterSendValidationSource = (string)file_get_contents(dirname(__DIR__) . '/admin/newsletter_send.php');
foreach ([
    'function adminFieldHasError(',
    'function adminFieldAttributes(',
    'function adminRenderFieldError(',
] as $adminLayoutFragment) {
    if (!str_contains($adminLayoutSource, $adminLayoutFragment)) {
        $adminFieldErrorIssues[] = 'admin layout is missing field-level error helper fragment: ' . $adminLayoutFragment;
    }
}
foreach ([
    '.field-error {',
    'input[aria-invalid="true"]',
] as $adminLayoutFragment) {
    if (!str_contains($adminLayoutCssSource, $adminLayoutFragment)) {
        $adminFieldErrorIssues[] = 'admin layout is missing field-level error helper fragment: ' . $adminLayoutFragment;
    }
}
if (!str_contains($adminLayoutSource, '/admin/assets/layout.css')
    || str_contains($adminLayoutSource, '<style nonce')
    || str_contains($adminLayoutSource, '<style>')) {
    $adminFieldErrorIssues[] = 'admin layout must load shared static layout.css instead of rendering an inline style block';
}
if (!str_contains($adminLayoutCssSource, ':root {') || !str_contains($adminLayoutCssSource, '.skip-link:focus')) {
    $adminFieldErrorIssues[] = 'admin layout stylesheet is missing core admin CSS fragments';
}
if (str_contains($adminLayoutSource, "info.setAttribute(\\'aria-live\\',\\'polite\\');")) {
    $adminFieldErrorIssues[] = 'admin content word count still announces every input as a live region';
}
if (!str_contains($adminLayoutSource, "info.setAttribute(\\'data-editor-count\\',\\'content\\');")) {
    $adminFieldErrorIssues[] = 'admin content word count is missing the non-live editor count marker';
}
if (!str_contains($adminLayoutSource, '<nav aria-labelledby="admin-nav-heading">')
    || !str_contains($adminLayoutSource, '<h2 id="admin-nav-heading">Administrace</h2>')
    || !str_contains($adminLayoutSource, 'class="admin-nav-site"')) {
    $adminFieldErrorIssues[] = 'admin layout navigation is missing heading-backed landmark semantics';
}
if (str_contains($adminLayoutSource, 'removeAttribute("role")')
    || str_contains($adminLayoutSource, "removeAttribute('role')")) {
    $adminFieldErrorIssues[] = 'admin layout still removes role from server-rendered status or alert messages';
}
if (str_contains($adminLayoutSource, '[role="status"]:not(#a11y-live),[role="alert"]')) {
    $adminFieldErrorIssues[] = 'admin layout still re-announces server-rendered status or alert messages through the client live region';
}
foreach ([
    '.button-row--start',
    '.button-row--between',
    '.btn-muted',
    '.admin-input-xs',
    '.admin-warning-box',
    '.admin-select-sm',
    '.admin-select-md',
    '.admin-select-lg',
    '.admin-inline-label',
    '.admin-checkbox-label',
    '.admin-inline-form',
    '.admin-copy--flush',
    '.admin-description',
    '.admin-description--flush',
    '.admin-description--muted',
    '.admin-stack-sm',
    '.admin-stack-md',
    '.admin-stack-lg',
    '.admin-section-spaced',
    '.admin-section-spaced--balanced',
    '.admin-input-sm',
    '.admin-input-compact',
    '.admin-input-auto',
    '.admin-modal-open',
    '.honeypot-field',
    '.blog-admin-fieldset',
    '.blog-admin-fieldset--flush',
    '.blog-admin-control-auto',
    '.blog-admin-check-row',
    '.blog-admin-form-actions',
    '.blog-admin-form-actions--dialog',
    '.blog-sort-row',
    '.blog-button--compact',
    '.blog-inline-form',
    '.blog-dialog-overlay',
    '.blog-dialog',
    '.blog-dialog__header',
    '.blog-dialog__title',
    '.blog-dialog__description',
    '.blog-logo-current',
    '.blog-logo-preview',
    '.blog-dialog-label-spaced',
    '.widgets-intro',
    '.widget-panel',
    '.widget-add-zone',
    '.widget-add-actions',
    '.widget-inline-form',
    '.widget-button--compact',
    '.widget-empty',
    '.widget-help--flush',
    '.widget-sort-list',
    '.widget-sort-item',
    '.widget-sort-item--inactive',
    '.widget-sort-item--dragging',
    '.widget-sort-item__body',
    '.widget-sort-item__meta',
    '.widget-sort-item__tools',
    '.widget-sort-item__warning',
    '.widget-sort-item__actions',
    '.widget-dialog-overlay',
    '.widget-dialog',
    '.widget-dialog__header',
    '.widget-dialog__title',
    '.widget-dialog__description',
    '.widget-dialog-fieldset',
    '.widget-dialog-fieldset--dynamic',
    '.widget-dialog-fieldset--nested',
    '.widget-dialog-field',
    '.widget-dialog-field--compact',
    '.widget-dialog-checkbox',
    '.widget-dialog-number-input',
    '.widget-dialog-actions',
    '.settings-nav',
    '.settings-checkbox-row',
    '.settings-checkbox-row--compact',
    '.settings-note',
    '.settings-muted',
    '.settings-profile-card',
    '.settings-profile-description',
    '.settings-fieldset-spaced',
    '.settings-form-row',
    '.settings-input-short',
    '.settings-code-textarea',
    '.settings-current-favicon',
    '.settings-current-logo',
    '.settings-submit',
    '.res-bookings-toolbar',
    '.res-bookings-filter',
    '.res-bookings-filter-row',
    '.res-bookings-filter-select',
    '.res-bookings-hidden-note',
    '.res-bookings-pager-list',
    '.res-booking-status--pending',
    '.res-booking-status--confirmed',
    '.res-booking-status--cancelled',
    '.res-booking-status--rejected',
    '.res-booking-status--completed',
    '.res-booking-status--no_show',
    '.res-booking-required-note',
    '.res-booking-mode-row',
    '.res-booking-fieldset',
    '.res-booking-fieldset--spaced',
    '.res-booking-time-row',
    '.res-booking-input-auto',
    '.res-booking-input-stacked',
    '.res-booking-party-size',
    '.res-booking-note',
    '.res-booking-actions',
    '.res-booking-form',
    '.res-booking-textarea--reject',
    '.res-booking-textarea--compact',
    '.res-booking-action-row',
    '.res-booking-complete-button',
    '.res-booking-pending-note',
    '.media-upload-form',
    '.media-upload-submit',
    '.media-filter-form',
    '.media-bulk-form',
    '.media-filter-grid',
    '.media-filter-actions',
    '.media-result-summary',
    '.media-bulk-actions',
    '.media-bulk-label',
    '.media-bulk-select',
    '.media-grid',
    '.media-card',
    '.media-card__header',
    '.media-card__checkbox',
    '.media-card__link',
    '.media-card__image',
    '.media-card__placeholder',
    '.media-card__body',
    '.media-card__title',
    '.media-edit-section',
    '.media-edit-title',
    '.media-edit-grid',
    '.media-alt-warning',
    '.media-caption-textarea',
    '.media-edit-actions',
    '.media-replace-form',
    '.media-replace-submit',
    '.media-info-list',
    '.media-info-list__value--break',
    '.media-info-list__value--url',
    '.media-usage-fieldset',
    '.media-empty-usage',
    '.media-usage-list',
    '.blog-form-missing-taxonomy',
    '.blog-form-fieldset',
    '.blog-form-fieldset--seo',
    '.blog-form-tag-label',
    '.blog-form-checkbox-row',
    '.blog-form-actions',
    '.blog-form-preview-note',
    '.blog-form-wysiwyg-wrapper',
    '.res-resource-intro',
    '.res-resource-location-fieldset',
    '.res-resource-fieldset',
    '.res-resource-inline-grid',
    '.res-resource-slot-row',
    '.res-resource-blocked-row',
    '.res-resource-actions',
    '.admin-textarea-compact',
    '.admin-rich-editor-sm',
    '.admin-rich-editor-base',
    '.admin-rich-editor-md',
    '.admin-rich-editor-lg',
    '.admin-rich-editor-tall',
    '.admin-fieldset-card',
    '.admin-fieldset-spaced',
    '.admin-field-row',
    '.admin-filter-form',
    '.admin-filter-fieldset',
    '.admin-compact-label',
    '.admin-form-grid',
    '.admin-form-grid--start',
    '.admin-form-grid--end',
    '.admin-form-grid__cell',
    '.admin-form-grid__cell--wide',
    '.admin-form-grid__cell--date',
    '.admin-date-row',
    '.admin-action-row',
    '.admin-preview-block',
    '.admin-image-preview',
    '.admin-image-preview--large',
    '.admin-image-preview--medium',
    '.admin-image-preview--wide',
    '.admin-image-preview--podcast',
    '.admin-avatar-preview',
    '.admin-inline-edit-form',
    '.admin-section-heading',
    '.admin-sort-list',
    '.admin-sort-item',
    '.admin-sort-item--muted',
    '.admin-sort-item--dragging',
    '.admin-sort-item__body',
    '.admin-sort-controls',
    '.admin-sort-control',
    '.admin-order-list',
    '.admin-order-item',
    '.admin-order-item__label',
    '.admin-order-item__label--muted',
    '.admin-summary-grid',
    '.admin-summary-card',
    '.admin-summary-card__heading',
    '.admin-summary-card__value',
    '.admin-summary-card__footer',
    '.admin-stat-chart',
    '.admin-stat-chart--compact',
    '.admin-stat-bars',
    '.admin-stat-bar',
    '.admin-stat-bar__label',
    '.admin-stat-bar__value',
    '.admin-stat-progress',
    '.admin-option-row',
    '.admin-option-row__input',
    '.admin-result-list',
    '.admin-result-item',
    '.admin-result-row',
    '.admin-progress',
    '.admin-progress--lg',
    '.admin-panel',
    '.admin-panel--success',
    '.admin-panel--warning',
    '.admin-panel--info',
    '.admin-panel--danger',
    '.admin-panel--spaced',
    '.admin-panel__heading',
    '.admin-panel__list',
    '.admin-panel__footer',
    '.admin-section-block',
    '.admin-section-block__heading',
    '.theme-catalog',
    '.theme-card',
    '.theme-card--selected',
    '.theme-card__heading',
    '.theme-card__title',
    '.theme-card__preview',
    '.theme-card__placeholder',
    '.theme-card__swatches',
    '.theme-card__swatch',
    '.theme-card__meta',
    '.theme-card__summary',
    '.theme-card__description',
    '.theme-card__status',
    '.theme-card__hint',
    '.theme-setting-row',
    '.theme-setting-color-row',
    '.theme-package-grid',
    '.theme-package-card',
    '.integrity-panel',
    '.integrity-panel--success',
    '.integrity-panel--warning',
    '.integrity-panel--danger',
    '.integrity-panel--info',
    '.integrity-panel--spaced',
    '.integrity-actions',
    '.integrity-result',
    '.integrity-copy--flush',
    '.integrity-copy--footer',
    '.integrity-copy--top',
    '.integrity-heading--flush',
    '.integrity-heading--danger',
    '.integrity-heading--modified',
    '.integrity-heading--muted',
    '.form-submission-grid',
    '.form-submission-field',
    '.form-submission-field--top',
    '.form-submission-actions',
    '.form-submission-actions--compact',
    '.form-submission-help--spaced',
    '.form-submission-help--top',
    '.form-submission-control',
    '.form-submission-control--sm',
    '.form-submission-control--md',
    '.form-submission-control--lg',
    '.form-submission-history',
    '.form-submission-history__item',
    '.form-submission-delete-form',
    '.admin-check-row',
    '.admin-check-row--separated',
    '.admin-profile-totp-panel',
    '.admin-totp-qr',
    '.admin-totp-code-input',
    '.admin-code-break',
    '.admin-inline-meta',
    '.admin-input-wide',
    '.field-help--flush',
    '.field-help--indented',
    '.table-note',
    '.table-row--scheduled',
    '.table-cell--detail',
    '.table-cell--prewrap',
    '.revision-diff-cell',
    '.revision-diff',
    '.revision-diff--details',
    '.revision-diff__delete',
    '.revision-diff__insert',
    '.admin-thumb',
    '.inline-badge',
    '.inline-badge--standalone',
    '.inline-badge--warning',
    '.inline-badge--info',
    '.table-list-compact',
    '.text-pending',
    '.admin-nav-site',
    '.admin-nav-user',
    '.admin-nav-bottom',
    '.autosave-banner',
    '.editor-count',
    '.admin-rich-editor-frame',
    '.admin-rich-editor-xl',
    '.seo-preview',
    '.admin-footer',
] as $adminLayoutUtilityFragment) {
    if (!str_contains($adminLayoutCssSource, $adminLayoutUtilityFragment)) {
        $adminFieldErrorIssues[] = 'admin layout is missing shared utility class: ' . $adminLayoutUtilityFragment;
    }
}
foreach ([
    '$item[\'style\']',
    'summary_style',
    '<summary style="',
    'banner.style.cssText',
    '<button type="button" style=',
    'info.style.cssText',
    'box.style.cssText',
    '<div style="font-size:1.1rem;color:var(--admin-link)',
    '<footer style=',
] as $adminLayoutInlineStyleFragment) {
    if (str_contains($adminLayoutSource, $adminLayoutInlineStyleFragment)) {
        $adminFieldErrorIssues[] = 'admin layout still contains generated inline style fragment: ' . $adminLayoutInlineStyleFragment;
    }
}
if (str_contains($uiSource, '<fieldset style=') || str_contains($uiSource, 'aria-live="polite" style=')) {
    $adminFieldErrorIssues[] = 'shared bulkActions helper still contains inline style attributes';
}
if (!str_contains($uiSource, '<fieldset class="admin-fieldset-card">') || !str_contains($uiSource, 'field-help field-help--flush')) {
    $adminFieldErrorIssues[] = 'shared bulkActions helper is missing shared admin fieldset/status utility classes';
}
if (str_contains($adminThemesSource, '<style nonce="<?= cspNonce() ?>">') || preg_match('/<[^>]+\sstyle=/i', $adminThemesSource) === 1) {
    $adminFieldErrorIssues[] = 'admin themes page still contains local style blocks or inline style attributes';
}
foreach ([
    'class="theme-catalog"',
    'class="theme-card__swatches"',
    '<svg class="theme-card__swatch"',
    'class="admin-section-block"',
    'class="theme-package-grid"',
    'class="theme-package-card"',
] as $themeAdminUtilityFragment) {
    if (!str_contains($adminThemesSource, $themeAdminUtilityFragment)) {
        $adminFieldErrorIssues[] = 'admin themes page is missing utility class fragment: ' . $themeAdminUtilityFragment;
    }
}
if (str_contains($adminThemesSource, 'background:<?= h($previewColor) ?>')) {
    $adminFieldErrorIssues[] = 'admin themes color preview still relies on inline background styles';
}
if (str_contains($newsletterOverviewSource, 'style=')) {
    $adminFieldErrorIssues[] = 'admin newsletter overview still contains inline style attributes';
}
if (preg_match('/<[^>]+\sstyle=/i', $adminStatisticsSource) === 1) {
    $adminFieldErrorIssues[] = 'admin statistics page still contains inline style attributes';
}
foreach ([
    'class="admin-filter-form"',
    'class="admin-summary-grid',
    'class="admin-stat-chart',
    '<progress',
    'class="admin-stat-progress"',
] as $adminStatisticsUtilityFragment) {
    if (!str_contains($adminStatisticsSource, $adminStatisticsUtilityFragment)) {
        $adminFieldErrorIssues[] = 'admin statistics page is missing utility fragment: ' . $adminStatisticsUtilityFragment;
    }
}
foreach ([
    'height:<?=',
    'display:flex;align-items:flex-end',
    'background:#005fcc',
    'background:#2e7d32',
    'background:#1565c0',
    'background:#6a1b9a',
    'background:#e65100',
] as $adminStatisticsLegacyChartFragment) {
    if (str_contains($adminStatisticsSource, $adminStatisticsLegacyChartFragment)) {
        $adminFieldErrorIssues[] = 'admin statistics page still contains legacy inline chart fragment: ' . $adminStatisticsLegacyChartFragment;
    }
}
if (preg_match('/<[^>]+\sstyle=/i', $adminIndexSource) === 1) {
    $adminFieldErrorIssues[] = 'admin dashboard still contains inline style attributes';
}
foreach ([
    'class="admin-panel admin-panel--danger"',
    'class="admin-section-spaced',
    'class="admin-summary-grid',
    'class="admin-summary-card__footer"',
    'class="admin-stat-chart admin-stat-chart--compact"',
    '<progress',
    'class="admin-stat-progress"',
    'class="table-cell--detail"',
] as $adminIndexUtilityFragment) {
    if (!str_contains($adminIndexSource, $adminIndexUtilityFragment)) {
        $adminFieldErrorIssues[] = 'admin dashboard is missing utility fragment: ' . $adminIndexUtilityFragment;
    }
}
foreach ([
    'background:#fff0f0',
    'background:#e3f2fd',
    'background:<?= h($statItem[\'background\']) ?>',
    'display:flex;align-items:flex-end',
    'height:<?=',
    'style="margin-top:1.5rem"',
    'style="max-width:400px',
] as $adminIndexLegacyStyleFragment) {
    if (str_contains($adminIndexSource, $adminIndexLegacyStyleFragment)) {
        $adminFieldErrorIssues[] = 'admin dashboard still contains legacy inline style fragment: ' . $adminIndexLegacyStyleFragment;
    }
}
if (!str_contains($adminIndexSource, 'role="list" aria-labelledby="stats-heading-new"')
    || str_contains($adminIndexSource, 'role="list" aria-label="Souhrn návštěvnosti"')) {
    $adminFieldErrorIssues[] = 'admin dashboard visitor summary is missing heading-backed list semantics';
}
if (!str_contains($adminStatisticsSource, 'role="list" aria-labelledby="sec-visitors"')
    || str_contains($adminStatisticsSource, 'role="list" aria-label="Souhrn návštěvnosti"')) {
    $adminFieldErrorIssues[] = 'admin statistics visitor summary is missing heading-backed list semantics';
}
if (!str_contains($pollFormValidationSource, '<h2 id="poll-results-heading"')
    || !str_contains($pollFormValidationSource, 'class="admin-result-list" aria-labelledby="poll-results-heading"')
    || str_contains($pollFormValidationSource, 'class="admin-result-list" aria-label="Výsledky ankety"')) {
    $adminFieldErrorIssues[] = 'admin poll results list is missing heading-backed list semantics';
}
if (str_contains($auditLogSource, 'style=')) {
    $adminFieldErrorIssues[] = 'admin audit log still contains inline style attributes';
}
if (str_contains($backupAdminSource, 'style=')) {
    $adminFieldErrorIssues[] = 'admin backup page still contains inline style attributes';
}
if (!str_contains($backupAdminSource, 'class="admin-description"')) {
    $adminFieldErrorIssues[] = 'admin backup page is missing shared description styling';
}
if (str_contains($integrityAdminSource, '<style') || str_contains($integrityAdminSource, 'style=')) {
    $adminFieldErrorIssues[] = 'admin integrity page still contains local style blocks or inline style attributes';
}
if (str_contains($blogsManagementSource, '<style') || preg_match('/<[^>]+\sstyle=/i', $blogsManagementSource) === 1 || str_contains($blogsManagementSource, '.style')) {
    $adminFieldErrorIssues[] = 'admin blogs management page still contains local style blocks, inline style attributes or JS style mutations';
}
foreach ([
    'class="blog-admin-fieldset"',
    'class="blog-admin-control-auto"',
    'class="blog-dialog"',
    'class="blog-logo-preview"',
    'class="blog-dialog-label-spaced"',
    "document.body.classList.add('admin-modal-open')",
] as $blogsManagementUtilityFragment) {
    if (!str_contains($blogsManagementSource, $blogsManagementUtilityFragment)) {
        $adminFieldErrorIssues[] = 'admin blogs management page is missing utility class fragment: ' . $blogsManagementUtilityFragment;
    }
}
foreach ([
    'class="integrity-panel integrity-panel--success"',
    'class="integrity-panel integrity-panel--warning"',
    'class="integrity-panel integrity-panel--danger"',
    'class="button-row integrity-actions"',
    'class="admin-inline-form"',
    'class="integrity-result"',
    'class="integrity-panel integrity-panel--info integrity-panel--spaced"',
] as $integrityAdminUtilityFragment) {
    if (!str_contains($integrityAdminSource, $integrityAdminUtilityFragment)) {
        $adminFieldErrorIssues[] = 'admin integrity page is missing utility class fragment: ' . $integrityAdminUtilityFragment;
    }
}
foreach ([
    'class="admin-description"',
    'button-row admin-stack-sm',
    'class="admin-input-sm"',
    'class="admin-select-sm"',
    'class="admin-input-auto"',
    'class="table-cell--detail"',
] as $auditLogUtilityFragment) {
    if (!str_contains($auditLogSource, $auditLogUtilityFragment)) {
        $adminFieldErrorIssues[] = 'admin audit log is missing utility class fragment: ' . $auditLogUtilityFragment;
    }
}
if (str_contains($blogOverviewSource, 'style=')) {
    $adminFieldErrorIssues[] = 'admin blog overview still contains inline style attributes';
}
foreach ([
    'admin-search-input',
    'admin-select-md',
    'admin-select-sm',
    'admin-fieldset-card',
    'field-help field-help--flush',
    'class="btn btn-muted"',
    'class="table-note"',
] as $blogOverviewUtilityFragment) {
    if (!str_contains($blogOverviewSource, $blogOverviewUtilityFragment)) {
        $adminFieldErrorIssues[] = 'admin blog overview is missing utility class fragment: ' . $blogOverviewUtilityFragment;
    }
}
foreach ([
    'admin-search-input',
    'admin-fieldset-card',
    'field-help field-help--flush',
    'class="text-pending"',
    'class="table-note"',
] as $newsletterUtilityFragment) {
    if (!str_contains($newsletterOverviewSource, $newsletterUtilityFragment)) {
        $adminFieldErrorIssues[] = 'admin newsletter overview is missing utility class fragment: ' . $newsletterUtilityFragment;
    }
}
foreach ([
    'admin menu overview' => [
        'source' => $menuOverviewSource,
        'fragments' => [
            'class="admin-description"',
            'field-help field-help--flush',
            'class="admin-sort-list"',
            'class="admin-sort-item',
            'admin-sort-item--muted',
            "classList.add('admin-sort-item--dragging')",
            "classList.remove('admin-sort-item--dragging')",
            'class="admin-sort-item__body"',
            'class="table-meta"',
            'button-row admin-action-row',
        ],
    ],
    'board overview' => [
        'source' => $boardOverviewSource,
        'fragments' => [
            'button-row button-row--start',
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="inline-badge inline-badge--warning"',
            'class="inline-badge inline-badge--info"',
            'class="table-meta"',
        ],
    ],
    'board form' => [
        'source' => $boardFormSource,
        'fragments' => [
            'class="admin-warning-box"',
            'class="admin-description admin-description--flush admin-description--muted"',
            'class="admin-rich-editor-sm"',
            'class="admin-input-auto"',
            'class="admin-field-row"',
            'class="admin-checkbox-label"',
            'class="admin-preview-block"',
            'class="admin-image-preview admin-image-preview--wide"',
            'class="button-row admin-fieldset-spaced"',
            'field-help field-help--flush',
        ],
    ],
    'chat overview' => [
        'source' => $chatOverviewSource,
        'fragments' => [
            'button-row admin-stack-sm',
            'admin-search-input',
            'admin-fieldset-card',
            'field-help field-help--flush',
            'class="table-note"',
        ],
    ],
    'chat message detail' => [
        'source' => $chatMessageDetailSource,
        'fragments' => [
            'class="table-cell--prewrap"',
        ],
    ],
    'blog members' => [
        'source' => $blogMembersSource,
        'fragments' => [
            'class="button-row admin-stack-sm"',
            'class="admin-inline-label"',
            'class="admin-select-lg"',
            'class="table-list-compact"',
            'class="button-row admin-action-row"',
        ],
    ],
    'blog transfer admin' => [
        'source' => $blogTransferAdminSource,
        'fragments' => [
            'class="admin-stack-sm"',
            'class="button-row admin-action-row"',
            'class="admin-fieldset-spaced"',
            'class="admin-field-row"',
        ],
    ],
    'contact overview' => [
        'source' => $contactOverviewSource,
        'fragments' => [
            'button-row admin-stack-sm',
            'admin-search-input',
            'admin-fieldset-card',
            'field-help field-help--flush',
            'class="table-meta"',
            'class="text-pending"',
            'class="table-note"',
        ],
    ],
    'contact message detail' => [
        'source' => $contactMessageDetailSource,
        'fragments' => [
            'class="table-cell--prewrap"',
        ],
    ],
    'comments overview' => [
        'source' => $commentsOverviewSource,
        'fragments' => [
            'button-row admin-stack-sm',
            'admin-search-input',
            'admin-fieldset-card',
            'field-help field-help--flush',
            'class="table-cell--prewrap"',
            'class="table-note"',
        ],
    ],
    'faq overview' => [
        'source' => $faqOverviewSource,
        'fragments' => [
            'button-row button-row--start',
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="table-meta"',
        ],
    ],
    'faq form' => [
        'source' => $faqFormSource,
        'fragments' => [
            'class="admin-description admin-description--flush"',
            'class="admin-textarea-compact"',
            'class="admin-rich-editor-lg"',
            'class="admin-checkbox-label"',
            'class="admin-field-row"',
            'button-row admin-fieldset-spaced',
        ],
    ],
    'forms overview' => [
        'source' => $formsOverviewSource,
        'fragments' => [
            'button-row button-row--baseline admin-stack-sm',
            'class="table-meta"',
            'class="text-pending"',
            '<td class="actions">',
        ],
    ],
    'form submissions overview' => [
        'source' => $formSubmissionsOverviewSource,
        'fragments' => [
            'class="button-row admin-stack-sm"',
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="admin-fieldset-card"',
            'field-help field-help--flush',
            'class="table-meta"',
            'class="table-note"',
        ],
    ],
    'form submission detail' => [
        'source' => $formSubmissionDetailSource,
        'fragments' => [
            'class="admin-inline-form"',
            'class="form-submission-grid"',
            'class="form-submission-control form-submission-control--md"',
            'class="field-help form-submission-help--top"',
            'class="form-submission-history"',
            'class="form-submission-delete-form"',
        ],
    ],
    'news overview' => [
        'source' => $newsOverviewSource,
        'fragments' => [
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="table-meta"',
        ],
    ],
    'news form' => [
        'source' => $newsFormSource,
        'fragments' => [
            'class="admin-warning-box"',
            'class="admin-description admin-description--muted admin-stack-sm"',
            'class="admin-fieldset-card admin-action-row"',
            'class="admin-input-auto"',
            'class="admin-textarea-compact"',
            'class="button-row admin-fieldset-spaced"',
            'field-help field-help--flush',
        ],
    ],
    'downloads overview' => [
        'source' => $downloadsOverviewSource,
        'fragments' => [
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="table-meta"',
        ],
    ],
    'download form' => [
        'source' => $downloadFormSource,
        'fragments' => [
            'class="admin-description admin-description--flush admin-description--muted"',
            'class="quill-editor admin-rich-editor-md"',
            'class="admin-field-row"',
            'class="admin-checkbox-label"',
            'class="admin-preview-block"',
            'class="admin-image-preview"',
            'class="admin-fieldset-card admin-action-row"',
            'class="button-row admin-fieldset-spaced"',
        ],
    ],
    'events overview' => [
        'source' => $eventsOverviewSource,
        'fragments' => [
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="table-meta"',
        ],
    ],
    'event form' => [
        'source' => $eventFormSource,
        'fragments' => [
            'class="admin-warning-box"',
            'class="admin-description admin-description--flush admin-description--muted"',
            'class="admin-fieldset-card admin-action-row"',
            'class="button-row button-row--baseline"',
            'class="admin-input-auto"',
            'class="admin-preview-block"',
            'class="admin-image-preview"',
            'class="admin-checkbox-label',
            'class="admin-textarea-compact"',
            'class="button-row admin-fieldset-spaced"',
            "wrapper.className = 'admin-rich-editor-frame admin-rich-editor-base';",
            'textarea.hidden = true;',
        ],
    ],
    'food overview' => [
        'source' => $foodOverviewSource,
        'fragments' => [
            'button-row button-row--start admin-stack-sm',
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="admin-section-heading"',
            'class="table-meta"',
            'status-badge status-badge--current',
        ],
    ],
    'food form' => [
        'source' => $foodFormSource,
        'fragments' => [
            'class="admin-description admin-description--flush"',
            'class="admin-input-auto"',
            'class="admin-textarea-compact"',
            'class="admin-date-row"',
            'class="admin-fieldset-card admin-fieldset-spaced"',
            'class="admin-checkbox-label"',
            'field-help field-help--indented',
            'class="button-row admin-fieldset-spaced"',
            "wrapper.className = 'admin-rich-editor-frame admin-rich-editor-xl';",
            'textarea.hidden = true;',
        ],
    ],
    'places overview' => [
        'source' => $placesOverviewSource,
        'fragments' => [
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="table-meta"',
        ],
    ],
    'place form' => [
        'source' => $placeFormSource,
        'fragments' => [
            'class="admin-description admin-description--flush"',
            'class="admin-rich-editor-frame admin-rich-editor-lg"',
            'class="admin-form-grid"',
            'class="admin-form-grid__cell"',
            'class="admin-preview-block"',
            'class="admin-image-preview admin-image-preview--large"',
            'class="admin-field-row"',
            'class="admin-checkbox-label"',
            'class="admin-fieldset-card admin-action-row"',
            'class="button-row admin-fieldset-spaced"',
        ],
    ],
    'gallery albums overview' => [
        'source' => $galleryAlbumsOverviewSource,
        'fragments' => [
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="admin-fieldset-card"',
            'field-help field-help--flush',
            'class="table-meta"',
        ],
    ],
    'gallery album form' => [
        'source' => $galleryAlbumFormSource,
        'fragments' => [
            'class="admin-field-row"',
            'class="button-row admin-action-row"',
            'revisions.php?type=gallery_album',
        ],
    ],
    'gallery photos overview' => [
        'source' => $galleryPhotosOverviewSource,
        'fragments' => [
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="admin-thumb"',
            'class="table-meta"',
        ],
    ],
    'gallery photo form' => [
        'source' => $galleryPhotoFormSource,
        'fragments' => [
            'class="admin-stack-sm"',
            'class="admin-image-preview admin-image-preview--medium"',
            'class="admin-field-row"',
            'class="admin-checkbox-label"',
            'class="button-row admin-fieldset-spaced"',
            'class="admin-description admin-description--muted admin-action-row"',
        ],
    ],
    'import page' => [
        'source' => $importAdminSource,
        'fragments' => [
            'class="btn admin-action-row"',
        ],
    ],
    'newsletter history detail' => [
        'source' => $newsletterHistorySource,
        'fragments' => [
            'class="table-cell--prewrap"',
        ],
    ],
    'pages overview' => [
        'source' => $pagesOverviewSource,
        'fragments' => [
            'field-help field-help--flush',
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="admin-input-auto"',
            'class="table-meta"',
            '<td class="actions">',
        ],
    ],
    'page form' => [
        'source' => $pageFormSource,
        'fragments' => [
            'class="admin-warning-box"',
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-inline-form"',
            'class="admin-description admin-description--flush"',
            'class="admin-checkbox-label"',
            'class="admin-field-row"',
            'class="admin-input-auto"',
            'class="admin-fieldset-card admin-action-row"',
            'class="admin-textarea-compact"',
            'class="button-row admin-fieldset-spaced"',
            "wrapper.className = 'admin-rich-editor-frame admin-rich-editor-tall';",
            'ta.hidden = true;',
        ],
    ],
    'newsletter form' => [
        'source' => $newsletterFormValidationSource,
        'fragments' => [
            'button-row button-row--between button-row--top admin-stack-md',
            'class="admin-copy"',
            'class="admin-copy--compact"',
            'class="field-help"',
            'class="button-row admin-action-row"',
            "wrapper.className = 'admin-rich-editor-frame admin-rich-editor-md';",
            'ta.hidden = true;',
        ],
    ],
    'WordPress import' => [
        'source' => $wpImportSource,
        'fragments' => [
            'class="admin-panel admin-panel--success"',
            'class="admin-panel admin-panel--warning"',
            'class="admin-panel admin-panel--warning admin-panel--spaced"',
            'class="admin-panel__heading"',
            'class="admin-panel__list"',
            'class="admin-panel__footer"',
            'class="admin-check-row"',
            'class="admin-check-row admin-check-row--separated"',
            'class="admin-inline-meta"',
            'class="admin-select-md"',
            'class="admin-field-row"',
            'class="button-row admin-action-row"',
        ],
    ],
    'eStranky import' => [
        'source' => $estrankyImportSource,
        'fragments' => [
            'class="admin-panel admin-panel--success"',
            'class="admin-panel admin-panel--warning admin-panel--spaced"',
            'class="admin-panel__heading"',
            'class="admin-panel__list"',
            'class="admin-panel__footer"',
            'class="admin-field-row"',
            'class="admin-select-md"',
            'class="button-row admin-action-row"',
        ],
    ],
    'eStranky photo download' => [
        'source' => $estrankyPhotoDownloadSource,
        'fragments' => [
            'class="admin-panel admin-panel--info"',
            'class="admin-panel admin-panel--success"',
            'class="admin-panel admin-panel--warning admin-panel--spaced"',
            'class="admin-panel__heading"',
            'class="admin-panel__list"',
            'class="admin-panel__footer"',
            'class="admin-progress admin-progress--lg"',
            'class="admin-field-row"',
            'class="admin-input-wide"',
            'class="admin-select-md"',
            'class="button-row admin-action-row"',
        ],
    ],
    'polls overview' => [
        'source' => $pollsOverviewSource,
        'fragments' => [
            'button-row button-row--start',
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="table-row--scheduled"',
            'class="table-meta"',
            'class="status-badge ',
            'status-badge--scheduled',
            '<td class="actions">',
        ],
    ],
    'poll form' => [
        'source' => $pollFormValidationSource,
        'fragments' => [
            'class="admin-description admin-description--flush"',
            'class="admin-fieldset-card admin-action-row"',
            'class="field-help field-help--flush"',
            'class="admin-form-grid admin-form-grid--end"',
            'class="admin-input-auto"',
            'class="option-row admin-option-row"',
            'class="admin-option-row__input"',
            'class="btn admin-action-row"',
            'class="button-row admin-fieldset-spaced"',
            'class="admin-section-heading"',
            'class="admin-result-list"',
            'class="admin-result-item"',
            'class="admin-result-row"',
            'class="admin-progress"',
            '<progress class="admin-progress"',
            "div.className = 'option-row admin-option-row';",
        ],
    ],
    'podcast episodes overview' => [
        'source' => $podcastEpisodesOverviewSource,
        'fragments' => [
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="table-meta"',
            'class="admin-action-row"',
        ],
    ],
    'podcast shows overview' => [
        'source' => $podcastShowsOverviewSource,
        'fragments' => [
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="table-meta"',
            'class="admin-action-row"',
        ],
    ],
    'podcast show form' => [
        'source' => $podcastShowFormSource,
        'fragments' => [
            'class="admin-description admin-description--flush"',
            'class="admin-input-compact"',
            'class="admin-form-grid admin-form-grid--start"',
            'class="admin-form-grid__cell admin-form-grid__cell--wide"',
            'class="admin-checkbox-label"',
            'class="quill-editor admin-rich-editor-md"',
            'class="admin-preview-block"',
            'class="admin-image-preview admin-image-preview--podcast"',
            'class="admin-fieldset-card admin-action-row"',
            'class="button-row admin-fieldset-spaced"',
        ],
    ],
    'podcast episode form' => [
        'source' => $podcastEpisodeFormSource,
        'fragments' => [
            'class="admin-description admin-description--flush"',
            'class="admin-form-grid admin-form-grid--end"',
            'class="admin-form-grid__cell admin-form-grid__cell--date"',
            'class="admin-form-grid admin-form-grid--start"',
            'class="admin-checkbox-label"',
            'class="admin-field-row"',
            'class="admin-preview-block"',
            'class="admin-image-preview admin-image-preview--podcast"',
            'class="quill-editor admin-rich-editor-md"',
            'class="admin-fieldset-card admin-action-row"',
            'class="button-row admin-fieldset-spaced"',
        ],
    ],
    'redirects admin' => [
        'source' => $redirectsAdminSource,
        'fragments' => [
            'class="admin-description"',
            'class="admin-input-auto"',
            'class="btn admin-action-row"',
            'class="admin-inline-edit-form"',
            'internalRedirectTarget($oldPathInput',
            'storedRedirectTarget($newPathInput',
            'http://</code> či <code>https://</code>',
        ],
    ],
    'reservation resources overview' => [
        'source' => $reservationResourcesSource,
        'fragments' => [
            'button-row button-row--start admin-stack-sm',
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-input-auto"',
            'class="table-meta"',
            '<td class="actions">',
        ],
    ],
    'revisions admin' => [
        'source' => $revisionsAdminSource,
        'fragments' => [
            'class="admin-stack-sm"',
            'class="revision-diff-cell"',
            'class="revision-diff revision-diff--details"',
            'class="revision-diff__delete"',
            'class="revision-diff__insert"',
        ],
    ],
    'review queue' => [
        'source' => $reviewQueueSource,
        'fragments' => [
            'class="button-row admin-stack-sm"',
            'class="admin-stack-md"',
            'class="admin-summary-grid"',
            'class="admin-summary-card"',
            'class="admin-summary-card__heading"',
            'class="admin-summary-card__value"',
            'class="admin-copy--flush"',
            'class="admin-fieldset-spaced"',
            'class="admin-inline-form"',
            'class="table-cell--prewrap"',
        ],
    ],
    'module settings' => [
        'source' => $settingsModulesSource,
        'fragments' => [
            'class="admin-checkbox-label"',
            'class="admin-fieldset-spaced"',
            'class="admin-field-row"',
            'class="admin-input-xs"',
            'class="admin-action-row"',
        ],
    ],
    'settings display overview' => [
        'source' => $settingsDisplaySource,
        'fragments' => [
            'class="admin-order-list"',
            'class="admin-order-item"',
            'admin-order-item__label',
            'admin-order-item__label--muted',
        ],
    ],
    'user form' => [
        'source' => $userFormSource,
        'fragments' => [
            'class="admin-fieldset-card admin-fieldset-spaced"',
            'class="field-help field-help--flush"',
            'class="admin-description admin-description--muted admin-field-row"',
            'class="admin-checkbox-label"',
            'class="admin-avatar-preview"',
            'class="admin-field-row"',
            'class="admin-action-row"',
            'class="button-row admin-fieldset-spaced"',
            'authorRoleNote.hidden = !isPublicRole;',
        ],
    ],
    'users overview' => [
        'source' => $usersOverviewSource,
        'fragments' => [
            'inline-badge inline-badge--info inline-badge--standalone',
            'class="table-list-compact"',
            '<td class="actions">',
        ],
    ],
] as $adminInboxLabel => $adminInboxGuardrail) {
    $adminInboxSource = (string)$adminInboxGuardrail['source'];
    if (str_contains($adminInboxSource, 'style=')) {
        $adminFieldErrorIssues[] = 'admin ' . $adminInboxLabel . ' still contains inline style attributes';
    }
    foreach ($adminInboxGuardrail['fragments'] as $adminInboxUtilityFragment) {
        if (!str_contains($adminInboxSource, $adminInboxUtilityFragment)) {
            $adminFieldErrorIssues[] = 'admin ' . $adminInboxLabel . ' is missing utility class fragment: ' . $adminInboxUtilityFragment;
        }
    }
}
if (str_contains($formSubmissionDetailSource, '<style') || str_contains($formSubmissionDetailSource, 'style=')) {
    $adminFieldErrorIssues[] = 'admin form submission detail still contains local style blocks or inline style attributes';
}
if (!str_contains($authSource, 'function storedRedirectTarget')
    || !str_contains($authSource, 'stored redirect skipped unsafe target')
    || !str_contains($authSource, "header('Location: ' . \$redirectTarget")
) {
    $adminFieldErrorIssues[] = 'stored redirect runtime no longer validates cms_redirects targets before sending Location';
}
if (!str_contains($presentationSource, 'internalRedirectTarget($oldPath')
    || !str_contains($presentationSource, 'storedRedirectTarget($newPath')
) {
    $adminFieldErrorIssues[] = 'automatic slug redirect helper no longer shares stored redirect validation';
}
$adminHeadingBackedNavs = [
    'dashboard quick links' => [
        'source' => $adminIndexSource,
        'required' => ['<nav aria-labelledby="task-links-heading" class="button-row">'],
        'forbidden' => ['<nav aria-label="Na čem chcete pracovat"'],
    ],
    'settings sections' => [
        'source' => $settingsAdminSource,
        'required' => ['<nav aria-labelledby="settings-sections-heading" class="button-row settings-nav">', '<h2 id="settings-sections-heading" class="sr-only">Sekce nastavení webu</h2>'],
        'forbidden' => ['<nav aria-label="Sekce nastavení webu"'],
    ],
    'comments filter' => [
        'source' => $commentsOverviewSource,
        'required' => ['<nav aria-labelledby="comments-filter-heading" class="button-row admin-stack-sm">', '<h2 id="comments-filter-heading" class="sr-only">Filtr komentářů</h2>'],
        'forbidden' => ['<nav aria-label="Filtr komentářů"'],
    ],
    'contact filter' => [
        'source' => $contactOverviewSource,
        'required' => ['<nav aria-labelledby="contact-filter-heading" class="button-row admin-stack-sm">', '<h2 id="contact-filter-heading" class="sr-only">Filtr kontaktních zpráv</h2>'],
        'forbidden' => ['<nav aria-label="Filtr kontaktních zpráv"'],
    ],
    'chat status filter' => [
        'source' => $chatOverviewSource,
        'required' => ['<nav aria-labelledby="chat-status-filter-heading" class="button-row admin-stack-sm">', '<h2 id="chat-status-filter-heading" class="sr-only">Stav chat zpráv</h2>'],
        'forbidden' => ['<nav aria-label="Stav chat zpráv"'],
    ],
    'chat visibility filter' => [
        'source' => $chatOverviewSource,
        'required' => ['<nav aria-labelledby="chat-visibility-filter-heading" class="button-row admin-stack-sm">', '<h2 id="chat-visibility-filter-heading" class="sr-only">Veřejná viditelnost chat zpráv</h2>'],
        'forbidden' => ['<nav aria-label="Veřejná viditelnost chat zpráv"'],
    ],
    'form submissions filter' => [
        'source' => $formSubmissionsOverviewSource,
        'required' => ['<nav aria-labelledby="form-submissions-filter-heading" class="button-row admin-stack-sm">', '<h2 id="form-submissions-filter-heading" class="sr-only">Filtr odpovědí formuláře</h2>'],
        'forbidden' => ['<nav aria-label="Filtr odpovědí formuláře"'],
    ],
    'newsletter filter' => [
        'source' => $newsletterOverviewSource,
        'required' => ['<nav aria-labelledby="newsletter-filter-heading" class="button-row admin-stack-sm">', '<h2 id="newsletter-filter-heading" class="sr-only">Filtr odběratelů newsletteru</h2>'],
        'forbidden' => ['<nav aria-label="Filtr odběratelů newsletteru"'],
    ],
    'review queue filter' => [
        'source' => $reviewQueueSource,
        'required' => ['<nav aria-labelledby="review-queue-filter-heading" class="button-row admin-stack-sm">', '<h2 id="review-queue-filter-heading" class="sr-only">Filtr fronty ke schválení</h2>'],
        'forbidden' => ['<nav aria-label="Filtr fronty ke schválení"'],
    ],
    'reservation bookings pager' => [
        'source' => $reservationBookingsOverviewSource,
        'required' => ['<nav aria-labelledby="reservation-bookings-pager-heading">', '<h2 id="reservation-bookings-pager-heading" class="sr-only">Stránkování rezervací</h2>'],
        'forbidden' => ['<nav aria-label="Stránkování rezervací"'],
    ],
];
foreach ($adminHeadingBackedNavs as $adminNavLabel => $adminNavGuardrail) {
    $adminNavSource = (string)$adminNavGuardrail['source'];
    foreach ($adminNavGuardrail['required'] as $adminNavRequiredFragment) {
        if (!str_contains($adminNavSource, $adminNavRequiredFragment)) {
            $adminFieldErrorIssues[] = 'admin ' . $adminNavLabel . ' is missing heading-backed aria-labelledby fragment: ' . $adminNavRequiredFragment;
        }
    }
    foreach ($adminNavGuardrail['forbidden'] as $adminNavForbiddenFragment) {
        if (str_contains($adminNavSource, $adminNavForbiddenFragment)) {
            $adminFieldErrorIssues[] = 'admin ' . $adminNavLabel . ' still contains aria-label-only navigation fragment';
        }
    }
}
if (str_contains($menuOverviewSource, '.style')) {
    $adminFieldErrorIssues[] = 'admin menu overview still mutates inline styles via JavaScript';
}
if (str_contains($eventFormSource, '.style')) {
    $adminFieldErrorIssues[] = 'admin event form still mutates inline styles via JavaScript';
}
if (str_contains($pageFormSource, '.style')) {
    $adminFieldErrorIssues[] = 'admin page form still mutates inline styles via JavaScript';
}
if (str_contains($userFormSource, '.style')) {
    $adminFieldErrorIssues[] = 'admin user form still mutates inline styles via JavaScript';
}
if (str_contains($newsletterFormValidationSource, '.style')) {
    $adminFieldErrorIssues[] = 'admin newsletter form still mutates inline styles via JavaScript';
}
if (str_contains($pollFormValidationSource, '.style')) {
    $adminFieldErrorIssues[] = 'admin poll form still mutates inline styles via JavaScript';
}
foreach ([
    'board categories' => [
        'source' => $boardCatsSource,
        'fragments' => [
            'button-row button-row--baseline',
            'class="admin-input-auto"',
        ],
    ],
    'download categories' => [
        'source' => $downloadCatsSource,
        'fragments' => [
            'class="btn admin-action-row"',
            'button-row button-row--baseline',
            'class="admin-input-auto"',
        ],
    ],
    'faq categories' => [
        'source' => $faqCatsSource,
        'fragments' => [
            'button-row button-row--baseline',
            'class="admin-input-auto"',
        ],
    ],
    'reservation categories' => [
        'source' => $reservationCategoriesSource,
        'fragments' => [
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="admin-input-xs"',
            'class="admin-input-auto"',
        ],
    ],
    'reservation locations' => [
        'source' => $reservationLocationsSource,
        'fragments' => [
            'button-row button-row--baseline admin-stack-sm',
            'class="admin-search-input"',
            'class="admin-input-auto"',
        ],
    ],
] as $adminCategoryLabel => $adminCategoryGuardrail) {
    $adminCategorySource = (string)$adminCategoryGuardrail['source'];
    if (str_contains($adminCategorySource, 'style=')) {
        $adminFieldErrorIssues[] = 'admin ' . $adminCategoryLabel . ' page still contains inline style attributes';
    }
    foreach ($adminCategoryGuardrail['fragments'] as $adminCategoryUtilityFragment) {
        if (!str_contains($adminCategorySource, $adminCategoryUtilityFragment)) {
            $adminFieldErrorIssues[] = 'admin ' . $adminCategoryLabel . ' page is missing utility class fragment: ' . $adminCategoryUtilityFragment;
        }
    }
}
if (str_contains($trashAdminSource, 'style=')) {
    $adminFieldErrorIssues[] = 'admin trash page still contains inline style attributes';
}
foreach ([
    'class="admin-description"',
    '<td class="actions">',
] as $trashAdminUtilityFragment) {
    if (!str_contains($trashAdminSource, $trashAdminUtilityFragment)) {
        $adminFieldErrorIssues[] = 'admin trash page is missing utility class fragment: ' . $trashAdminUtilityFragment;
    }
}
if (!str_contains($adminLayoutSource, 'document.addEventListener("submit"')
    || !str_contains($adminLayoutSource, 'form[data-confirm]')
    || !str_contains($adminLayoutSource, 'b.tagName!=="FORM"')) {
    $adminFieldErrorIssues[] = 'admin layout is missing submit-safe data-confirm handling for forms';
}
foreach ([
    'comments overview' => '/admin/comments.php',
    'chat message detail' => '/admin/chat_message.php',
    'contact overview' => '/admin/contact.php',
    'contact message detail' => '/admin/contact_message.php',
    'gallery photos' => '/admin/gallery_photos.php',
    'newsletter overview' => '/admin/newsletter.php',
    'newsletter subscriber detail' => '/admin/newsletter_subscriber.php',
    'pages overview' => '/admin/pages.php',
    'reservation bookings' => '/admin/res_bookings.php',
    'review queue' => '/admin/review_queue.php',
] as $adminConfirmLabel => $adminConfirmPath) {
    $adminConfirmSource = (string)file_get_contents(dirname(__DIR__) . $adminConfirmPath);
    if (str_contains($adminConfirmSource, 'onsubmit="return confirm(')) {
        $adminFieldErrorIssues[] = $adminConfirmLabel . ' still uses inline onsubmit confirm';
    }
}
if (!str_contains($adminLayoutSource, '[data-submit-once]')
    || !str_contains($adminLayoutSource, 'data-submit-once-clicked')
    || !str_contains($adminLayoutSource, 'e.submitter')) {
    $adminFieldErrorIssues[] = 'admin layout is missing submit-once handling for long-running forms';
}
foreach ([
    'WordPress import' => ['/admin/wp_import.php', 'data-submit-once="Importuji, čekejte prosím…"', 'data-submit-once="Načítám náhled…"'],
    'eStránky import' => ['/admin/estranky_import.php', 'data-submit-once="Importuji, čekejte prosím…"'],
] as $adminSubmitOnceLabel => $adminSubmitOnceSpec) {
    $adminSubmitOncePath = array_shift($adminSubmitOnceSpec);
    $adminSubmitOnceSource = (string)file_get_contents(dirname(__DIR__) . (string)$adminSubmitOncePath);
    if (str_contains($adminSubmitOnceSource, 'onclick="this.disabled=true;this.textContent=')) {
        $adminFieldErrorIssues[] = $adminSubmitOnceLabel . ' still uses inline submit-once onclick';
    }
    foreach ($adminSubmitOnceSpec as $adminSubmitOnceFragment) {
        if (!str_contains($adminSubmitOnceSource, (string)$adminSubmitOnceFragment)) {
            $adminFieldErrorIssues[] = $adminSubmitOnceLabel . ' is missing submit-once fragment: ' . $adminSubmitOnceFragment;
        }
    }
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/admin')) as $adminPhpFile) {
    if (!$adminPhpFile instanceof SplFileInfo || !$adminPhpFile->isFile() || $adminPhpFile->getExtension() !== 'php') {
        continue;
    }
    $adminPhpSource = (string)file_get_contents($adminPhpFile->getPathname());
    if (preg_match('/\son(?:click|change|submit|input)\s*=/i', $adminPhpSource) === 1) {
        $adminFieldErrorIssues[] = 'admin PHP file still contains inline event handler: ' . $adminPhpFile->getFilename();
    }
}
foreach ([
    'reservation booking add form' => [$reservationBookingAddFormSource, 'data-booking-mode-toggle', "radio.addEventListener('change', toggleMode)", 'userFields.hidden = isGuest;', 'guestFields.hidden = !isGuest;'],
    'reservation resource form' => [$reservationFormSource, 'data-add-slot', 'data-remove-slot', 'data-remove-blocked'],
] as $adminEventHandlerLabel => $adminEventHandlerSpec) {
    $adminEventHandlerSource = array_shift($adminEventHandlerSpec);
    foreach ($adminEventHandlerSpec as $adminEventHandlerFragment) {
        if (!str_contains((string)$adminEventHandlerSource, (string)$adminEventHandlerFragment)) {
            $adminFieldErrorIssues[] = $adminEventHandlerLabel . ' is missing event handler fragment: ' . $adminEventHandlerFragment;
        }
    }
}
foreach ([
    'reservation bookings overview' => [
        $reservationBookingsOverviewSource,
        'class="button-row res-bookings-toolbar"',
        'class="res-bookings-filter"',
        'class="res-booking-status--<?= h($statusKey) ?>"',
        'class="res-bookings-pager-list"',
    ],
    'reservation booking add form' => [
        $reservationBookingAddFormSource,
        'class="res-booking-mode-row"',
        'id="user-fields"<?= $mode ===',
        'id="guest-fields"<?= $mode !==',
        'class="res-booking-fieldset res-booking-fieldset--spaced"',
        'class="button-row res-booking-actions"',
    ],
    'reservation booking detail' => [
        $reservationBookingDetailSource,
        'class="res-booking-status--<?= h($statusKey) ?>"',
        'class="button-row button-row--top res-booking-actions"',
        'class="res-booking-fieldset"',
        'res-booking-complete-button',
    ],
    'reservation resource form' => [
        $reservationFormSource,
        'class="res-resource-fieldset"',
        'class="res-resource-inline-grid"',
        'class="slot-row res-resource-slot-row"',
        'class="blocked-row res-resource-blocked-row"',
        'durationField.hidden = mode !==',
        'slotsSection.hidden = mode !==',
    ],
] as $reservationAdminLabel => $reservationAdminFragments) {
    $reservationAdminSource = (string)array_shift($reservationAdminFragments);
    if (str_contains($reservationAdminSource, '<style') || str_contains($reservationAdminSource, 'style=') || str_contains($reservationAdminSource, '.style')) {
        $adminFieldErrorIssues[] = $reservationAdminLabel . ' still contains local style blocks, inline style markup or JS style mutations';
    }
    foreach ($reservationAdminFragments as $reservationAdminFragment) {
        if (!str_contains($reservationAdminSource, (string)$reservationAdminFragment)) {
            $adminFieldErrorIssues[] = $reservationAdminLabel . ' is missing reservation admin style fragment: ' . $reservationAdminFragment;
        }
    }
}
$adminFieldErrorForms = [
    'page form' => [$pageFormSource, "adminFieldAttributes('title'", "adminRenderFieldError('title'", "adminFieldAttributes('unpublish_at'"],
    'blog form' => [$blogFormSource, "adminFieldAttributes('title'", "adminFieldAttributes('content'", "adminFieldAttributes('publish_at'"],
    'news form' => [$newsFormSource, "adminFieldAttributes('title'", "adminFieldAttributes('content'", "adminFieldAttributes('unpublish_at'"],
    'faq form' => [$faqFormSource, "adminFieldAttributes('question'", "adminFieldAttributes('slug'", "adminRenderFieldError('answer'"],
    'food form' => [$foodFormSource, "adminFieldAttributes('title'", "adminFieldAttributes('valid_from'", "adminFieldAttributes('valid_to'"],
    'poll form' => [$pollFormValidationSource, "adminFieldAttributes('question'", "adminFieldAttributes('start_date'", "poll-options-error"],
    'form builder' => [$formBuilderSource, "adminFieldAttributes('title'", "adminFieldAttributes('notification_email'", "adminFieldAttributes('webhook_url'"],
    'board form' => [$boardFormSource, "adminFieldAttributes('posted_date'", "adminFieldAttributes('board_image'", "adminFieldAttributes('file'"],
    'event form' => [$eventFormSource, "adminFieldAttributes('event_date'", "adminFieldAttributes('registration_url'", "adminFieldAttributes('unpublish_at'"],
    'place form' => [$placeFormSource, "adminFieldAttributes('latitude'", "adminFieldAttributes('contact_email'", "adminFieldAttributes('place_image'"],
    'podcast episode form' => [$podcastEpisodeFormSource, "adminFieldAttributes('audio_url'", "adminFieldAttributes('image_file'", "adminFieldAttributes('publish_at'"],
    'podcast show form' => [$podcastShowFormSource, "adminFieldAttributes('website_url'", "adminFieldAttributes('feed_episode_limit'", "adminFieldAttributes('cover_image'"],
    'reservation resource form' => [$reservationFormSource, "adminFieldAttributes('capacity'", 'hours-error', 'blocked-dates-error'],
    'gallery album form' => [$galleryAlbumFormSource, "adminFieldAttributes('name'", "adminFieldAttributes('slug'", "adminFieldAttributes('parent_id'"],
    'gallery photo form' => [$galleryPhotoFormSource, "adminFieldAttributes('slug'", "adminRenderFieldError('slug'"],
    'user form' => [$userFormValidationSource, "adminFieldAttributes('email'", "adminFieldAttributes('author_slug'", "adminFieldAttributes('author_avatar'"],
    'profile form' => [$profileFormValidationSource, "adminFieldAttributes('email'", "adminFieldAttributes('totp_verify'", "adminFieldAttributes('author_slug'"],
    'newsletter form' => [$newsletterFormValidationSource, "adminFieldAttributes('subject'", "adminFieldAttributes('body'", "adminRenderFieldError('body'"],
];
foreach ($adminFieldErrorForms as $formLabel => $formFragments) {
    $formSource = (string)array_shift($formFragments);
    foreach ($formFragments as $requiredFragment) {
        if (!str_contains($formSource, $requiredFragment)) {
            $adminFieldErrorIssues[] = $formLabel . ' is missing field-level error fragment: ' . $requiredFragment;
        }
    }
}
if (!str_contains($formBuilderSource, '$submitterEmailFieldValue') || !str_contains($formBuilderSource, 'count($emailFieldOptions) === 1')) {
    $adminFieldErrorIssues[] = 'form builder is missing resilient fallback selection for submitter confirmation email field';
}
if (!str_contains($formBuilderSource, '$flash = is_array($_SESSION[\'form_flash\'] ?? null) ? $_SESSION[\'form_flash\'] : [];')
    || !str_contains($formBuilderSource, 'unset($_SESSION[\'form_flash\']);')
    || !str_contains($formBuilderSource, '$successMessage = trim((string)($flash[\'success\'] ?? \'\'));')
    || !str_contains($formBuilderSource, '<?php if ($successMessage !== \'\'): ?>')
    || !str_contains($formBuilderSource, '<p class="success" role="status"><?= h($successMessage) ?></p>')) {
    $adminFieldErrorIssues[] = 'form builder is missing success flash handling after save';
}
if (str_contains($formBuilderSource, '<style') || preg_match('/<[^>]+\sstyle=/i', $formBuilderSource) === 1 || str_contains($formBuilderSource, '.style')) {
    $adminFieldErrorIssues[] = 'form builder still contains local style blocks, inline style attributes or JS style mutations';
}
foreach ([
    'class="form-builder-row"',
    'class="form-builder-preview"',
    'class="form-builder-action-grid"',
    'class="form-builder-field-grid"',
    'class="form-builder-condition-grid"',
    'class="form-builder-new-field"',
] as $formBuilderUtilityFragment) {
    if (!str_contains($formBuilderSource, $formBuilderUtilityFragment)) {
        $adminFieldErrorIssues[] = 'form builder is missing shared style fragment: ' . $formBuilderUtilityFragment;
    }
}
foreach ([
    '.form-builder-row',
    '.form-builder-preview',
    '.form-builder-field-grid',
    '.form-builder-condition-grid',
    '.form-builder-new-field',
] as $formBuilderLayoutFragment) {
    if (!str_contains($adminLayoutCssSource, $formBuilderLayoutFragment)) {
        $adminFieldErrorIssues[] = 'admin layout is missing form builder style fragment: ' . $formBuilderLayoutFragment;
    }
}
if (!str_contains($formSaveSource, 'adminAvailableSubmitterEmailFields') || !str_contains($formSaveSource, '$presetSubmitterEmailField') || !str_contains($formSaveSource, '$existingSubmitterEmailField')) {
    $adminFieldErrorIssues[] = 'form save is missing fallback resolution for submitter confirmation email field';
}
if (!str_contains($formSaveSource, 'function formFlashSet(array $flash): void')
    || substr_count($formSaveSource, 'formFlashSet([') < 2
    || substr_count($formSaveSource, "header('Location: ' . BASE_URL . '/admin/form_form.php?id=' .") < 2) {
    $adminFieldErrorIssues[] = 'form save is missing PRG success flash for create and edit flow';
}
if (!str_contains($formSaveSource, 'adminUniquePresetFieldName') || !str_contains($formSaveSource, '$presetFieldName = adminUniquePresetFieldName(')) {
    $adminFieldErrorIssues[] = 'form save is missing exact preset field name preservation for template-backed forms';
}
if (str_contains($profileFormValidationSource, 'style=') || str_contains($profileFormValidationSource, '.style')) {
    $adminFieldErrorIssues[] = 'admin profile form still contains inline style markup or JS style mutations';
}
foreach ([
    'class="admin-fieldset-card admin-fieldset-spaced"',
    'class="field-help field-help--flush"',
    'class="admin-profile-totp-panel"',
    'setup.hidden = !this.checked;',
    'class="admin-totp-qr"',
    'class="admin-code-break"',
    'class="admin-totp-code-input"',
    'class="admin-avatar-preview"',
    'class="admin-checkbox-label"',
] as $profileUtilityFragment) {
    if (!str_contains($profileFormValidationSource, $profileUtilityFragment)) {
        $adminFieldErrorIssues[] = 'admin profile form is missing utility fragment: ' . $profileUtilityFragment;
    }
}
if (
    !str_contains($presentationHelpersSource, 'function formFieldNameVariants(')
    || !str_contains($presentationHelpersSource, 'function formSubmissionValueByFieldName(')
    || !str_contains($presentationHelpersSource, 'function formFieldDefinitionByName(')
    || !str_contains($presentationHelpersSource, "formSubmissionValueByFieldName(\$submissionData, \$name)")
    || !str_contains($presentationHelpersSource, "'{{field:' . \$candidateName . '}}'")
) {
    $adminFieldErrorIssues[] = 'form submission placeholder rendering is missing alias fallback for preset field names';
}
foreach ([
    '$_SESSION[\'form_error_fields\']',
    '$errorFields[] = \'email\'',
    '$errorFields[] = \'author_slug\'',
] as $userSaveFragment) {
    if (!str_contains($userSaveValidationSource, $userSaveFragment)) {
        $adminFieldErrorIssues[] = 'user save is missing field-level error persistence fragment: ' . $userSaveFragment;
    }
}
foreach ([
    '$_SESSION[\'newsletter_form_error_fields\']',
    "adminRenderFieldError('subject'",
] as $newsletterFragment) {
    if (!str_contains($newsletterFormValidationSource . $newsletterSendValidationSource, $newsletterFragment)) {
        $adminFieldErrorIssues[] = 'newsletter compose flow is missing field-level error fragment: ' . $newsletterFragment;
    }
}
$profileTotpCheckPos = strpos($profileFormValidationSource, 'if ($enableTwoFactorRequested)');
$profileUpdateGuardPos = $profileTotpCheckPos === false
    ? false
    : strpos($profileFormValidationSource, 'if (empty($errors)) {', $profileTotpCheckPos);
$profileUpdateExecutePos = strpos($profileFormValidationSource, '$pdo->prepare("UPDATE cms_users SET {$setClauses} WHERE id=?")->execute($params);');
if ($profileTotpCheckPos === false || $profileUpdateGuardPos === false || $profileUpdateExecutePos === false || $profileUpdateGuardPos > $profileUpdateExecutePos) {
    $adminFieldErrorIssues[] = 'profile update is no longer guarded after 2FA validation';
}
if ($adminFieldErrorIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($adminFieldErrorIssues as $adminFieldErrorIssue) {
        echo '- ' . $adminFieldErrorIssue . "\n";
    }
}

echo "=== podcast_source_guardrails ===\n";
$podcastSourceIssues = [];
$podcastShowSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/podcast_show_save.php');
$podcastEpisodeSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/podcast_save.php');
$podcastShowFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/podcast_show_form.php');
$podcastEpisodeFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/podcast_form.php');
$podcastIndexControllerSource = (string)file_get_contents(dirname(__DIR__) . '/podcast/index.php');
$podcastShowControllerSource = (string)file_get_contents(dirname(__DIR__) . '/podcast/show.php');
$podcastEpisodeControllerSource = (string)file_get_contents(dirname(__DIR__) . '/podcast/episode.php');
$podcastIndexViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/podcast-index.php');
$podcastShowViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/podcast-show.php');
$podcastFeedSource = (string)file_get_contents(dirname(__DIR__) . '/podcast/feed.php');
$podcastAudioSource = (string)file_get_contents(dirname(__DIR__) . '/podcast/audio.php');
$podcastCoverSource = (string)file_get_contents(dirname(__DIR__) . '/podcast/cover.php');
$podcastImageSource = (string)file_get_contents(dirname(__DIR__) . '/podcast/image.php');
$podcastSearchSource = (string)file_get_contents(dirname(__DIR__) . '/search.php');
$podcastSitemapSource = (string)file_get_contents(dirname(__DIR__) . '/sitemap.php');
$podcastWidgetSource = (string)file_get_contents(dirname(__DIR__) . '/lib/widgets.php');
$podcastHtaccessSource = (string)file_get_contents(dirname(__DIR__) . '/.htaccess');
if (!str_contains($podcastShowSaveSource, 'saveRevision(') || !str_contains($podcastShowSaveSource, "'podcast_show'")) {
    $podcastSourceIssues[] = 'podcast show save is missing revision persistence';
}
if (!str_contains($podcastShowSaveSource, 'upsertPathRedirect')) {
    $podcastSourceIssues[] = 'podcast show save is missing slug redirect persistence';
}
if (!str_contains($podcastEpisodeSaveSource, 'saveRevision(') || !str_contains($podcastEpisodeSaveSource, "'podcast_episode'")) {
    $podcastSourceIssues[] = 'podcast episode save is missing revision persistence';
}
if (!str_contains($podcastEpisodeSaveSource, 'upsertPathRedirect')) {
    $podcastSourceIssues[] = 'podcast episode save is missing slug redirect persistence';
}
if (!str_contains($podcastShowFormSource, 'name="is_published"')) {
    $podcastSourceIssues[] = 'podcast show form is missing show visibility field';
}
if (!str_contains($podcastShowFormSource, 'revisions.php?type=podcast_show')) {
    $podcastSourceIssues[] = 'podcast show form is missing revisions link';
}
if (!str_contains($podcastEpisodeFormSource, 'revisions.php?type=podcast_episode')) {
    $podcastSourceIssues[] = 'podcast episode form is missing revisions link';
}
if (!str_contains($podcastIndexControllerSource, 'paginate(')) {
    $podcastSourceIssues[] = 'podcast index is missing pagination support';
}
if (!str_contains($podcastIndexControllerSource, "podcastShowPublicVisibilitySql('s')")) {
    $podcastSourceIssues[] = 'podcast index is missing show visibility guard';
}
if (!str_contains($podcastShowControllerSource, 'renderPager(')) {
    $podcastSourceIssues[] = 'podcast show controller is missing pager support';
}
if (!str_contains($podcastIndexViewSource, 'pagerHtml')) {
    $podcastSourceIssues[] = 'podcast index view is missing pager output';
}
if (!str_contains($podcastShowViewSource, 'pagerHtml')) {
    $podcastSourceIssues[] = 'podcast show view is missing pager output';
}
if (!str_contains($podcastShowControllerSource, 'podcastShowStructuredData(')) {
    $podcastSourceIssues[] = 'podcast show controller is missing structured data';
}
if (!str_contains($podcastEpisodeControllerSource, 'podcastEpisodeStructuredData(')) {
    $podcastSourceIssues[] = 'podcast episode controller is missing structured data';
}
if (!str_contains($podcastFeedSource, 'podcastFeedManagingEditor(')) {
    $podcastSourceIssues[] = 'podcast feed is missing managingEditor helper';
}
if (!str_contains($podcastFeedSource, 'podcastEpisodeEnclosureLength(')) {
    $podcastSourceIssues[] = 'podcast feed is missing enclosure length helper';
}
if (!str_contains($podcastAudioSource, "currentUserHasCapability('content_manage_shared')")) {
    $podcastSourceIssues[] = 'podcast audio endpoint is missing private visibility guard';
}
if (!str_contains($podcastCoverSource, "currentUserHasCapability('content_manage_shared')")) {
    $podcastSourceIssues[] = 'podcast cover endpoint is missing private visibility guard';
}
if (!str_contains($podcastImageSource, "currentUserHasCapability('content_manage_shared')")) {
    $podcastSourceIssues[] = 'podcast image endpoint is missing private visibility guard';
}
if (!str_contains($podcastSearchSource, "podcastEpisodePublicVisibilitySql('p', 's')")) {
    $podcastSourceIssues[] = 'search no longer protects podcast episode visibility';
}
if (!str_contains($podcastSearchSource, 'podcastShowPublicVisibilitySql()')) {
    $podcastSourceIssues[] = 'search no longer protects podcast show visibility';
}
if (!str_contains($podcastSitemapSource, "podcastEpisodePublicVisibilitySql('p', 's')")) {
    $podcastSourceIssues[] = 'sitemap no longer protects podcast episode visibility';
}
if (!str_contains($podcastWidgetSource, "podcastEpisodePublicVisibilitySql('p', 's')")) {
    $podcastSourceIssues[] = 'podcast widget no longer protects episode visibility';
}
if (!str_contains($podcastHtaccessSource, 'RewriteRule ^uploads/podcasts/ - [F,L,NC]')) {
    $podcastSourceIssues[] = 'htaccess is missing podcasts uploads deny rule';
}
if ($podcastSourceIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($podcastSourceIssues as $podcastSourceIssue) {
        echo '- ' . $podcastSourceIssue . "\n";
    }
}

echo "=== estranky_photo_guardrails ===\n";
$estrankyPhotoIssues = [];
$estrankyPhotoSource = (string)file_get_contents(dirname(__DIR__) . '/admin/estranky_download_photos.php');
if (!str_contains($estrankyPhotoSource, "'batch_id' => \$batchInfo['id']")) {
    $estrankyPhotoIssues[] = 'eStránky photo downloader no longer stores lightweight batch state';
}
if (!str_contains($estrankyPhotoSource, "'batch_storage' => \$batchInfo['storage']")) {
    $estrankyPhotoIssues[] = 'eStránky photo downloader no longer tracks batch storage backend';
}
if (str_contains($estrankyPhotoSource, "'photos' => \$photoList")) {
    $estrankyPhotoIssues[] = 'eStránky photo downloader stores full photo list in session again';
}
if (!str_contains($estrankyPhotoSource, 'function estrankyFetchRemotePhoto')) {
    $estrankyPhotoIssues[] = 'eStránky photo downloader is missing resilient remote download helper';
}
if (!str_contains($estrankyPhotoSource, 'function_exists(\'curl_init\')')) {
    $estrankyPhotoIssues[] = 'eStránky photo downloader is missing cURL fallback for stricter hosting';
}
if (!str_contains($estrankyPhotoSource, 'function estrankyFallbackPhotoBatchDirectory')) {
    $estrankyPhotoIssues[] = 'eStránky photo downloader is missing uploads/tmp fallback for batch storage';
}
if (str_contains($estrankyPhotoSource, 'error_log(')
    || !str_contains($estrankyPhotoSource, 'function estrankyLogPhotoImportIssue')
    || !str_contains($estrankyPhotoSource, "estrankyLogPhotoImportIssue('estranky photo batch cleanup failed'")
    || !str_contains($estrankyPhotoSource, "estrankyLogPhotoImportIssue('estranky parent album move failed'")
    || !str_contains($estrankyPhotoSource, 'path_hash')) {
    $estrankyPhotoIssues[] = 'eStránky photo downloader no longer uses structured sanitized logging';
}

if ($estrankyPhotoIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($estrankyPhotoIssues as $estrankyPhotoIssue) {
        echo '- ' . $estrankyPhotoIssue . "\n";
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
    'social_links',
    'visitor_stats',
];
foreach ($requiredWidgetTypes as $requiredWidgetType) {
    if (!isset($widgetDefs[$requiredWidgetType])) {
        $widgetRegistryIssues[] = 'missing widget type: ' . $requiredWidgetType;
    }
}
if (($widgetDefs['contact_info']['requires_module'] ?? null) !== null) {
    $widgetRegistryIssues[] = 'contact_info widget is still incorrectly bound to contact module';
}
if (($widgetDefs['intro']['requires_setting'] ?? null) !== null) {
    $widgetRegistryIssues[] = 'intro widget is still incorrectly bound to legacy homepage settings';
}
if (!str_contains($migrateSource, "VALUES ('footer', 'social_links', 'Sociální sítě'")) {
    $widgetRegistryIssues[] = 'widget migration does not insert the correct social links default title';
}
$legacyBrokenSocialLinksTitleProbe = hex2bin('536f6369c482cb876c6ec482c2ad2073c482c2ad74c384e280ba');
if (!str_contains($migrateSource, "WHERE widget_type = 'social_links'") || !is_string($legacyBrokenSocialLinksTitleProbe) || !str_contains($migrateSource, $legacyBrokenSocialLinksTitleProbe)) {
    $widgetRegistryIssues[] = 'widget migration is missing the repair step for previously corrupted social links titles';
}
if (!str_contains($migrateSource, "SELECT id, zone, sort_order, settings") || !str_contains($migrateSource, "WHERE widget_type = 'intro'")) {
    $widgetRegistryIssues[] = 'migration is missing the legacy home_intro to intro widget backfill';
}
if (!str_contains($blogWidgetAdminSource, '$wMetaParts = [];') || !str_contains($blogWidgetAdminSource, "if (\$wTitle !== '' && \$wTitle !== \$wTypeName)")) {
    $widgetRegistryIssues[] = 'widgets admin still repeats the widget type label even when the title already matches it';
}
if (!str_contains($blogWidgetAdminSource, "if (type === 'intro') {") || !str_contains($blogWidgetAdminSource, "document.getElementById('wd-content').value = s.content || s.text || '';")) {
    $widgetRegistryIssues[] = 'widgets admin does not edit intro widget through the shared HTML content field';
}
if (str_contains($blogWidgetAdminSource, 'id="wd-field-text"')) {
    $widgetRegistryIssues[] = 'widgets admin still renders the legacy text-only field for intro widget editing';
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
$widgetIntroHome = renderWidget_intro(['id' => 105, 'title' => 'Úvod'], ['content' => '<p>Audit intro widgetu.</p>'], 'homepage');
$widgetSearchOne = renderWidget_search(['id' => 101, 'title' => 'Vyhledávání'], [], 'sidebar');
$widgetSearchTwo = renderWidget_search(['id' => 202, 'title' => 'Vyhledávání'], [], 'sidebar');
if (!str_contains($widgetIntroHome, '<section class="surface home-section" aria-labelledby="w-105-title">')
    || !str_contains($widgetIntroHome, '<h2 id="w-105-title" class="sr-only">')) {
    $widgetRenderIssues[] = 'homepage intro widget is missing a screen-reader heading with aria-labelledby';
}
if (str_contains($widgetIntroHome, '<section class="surface home-section" aria-label=')) {
    $widgetRenderIssues[] = 'homepage intro widget still renders legacy aria-label-only section markup';
}
if (!str_contains($widgetSearchOne, 'widget-search-q-101')) {
    $widgetRenderIssues[] = 'search widget does not use unique input id for first instance';
}
if (!str_contains($widgetSearchTwo, 'widget-search-q-202')) {
    $widgetRenderIssues[] = 'search widget does not use unique input id for second instance';
}
if (str_contains($widgetSearchOne . $widgetSearchTwo, 'id="widget-search-q"')) {
    $widgetRenderIssues[] = 'search widget still renders legacy duplicate input id';
}
if (!str_contains($widgetSearchOne, 'role="search"')) {
    $widgetRenderIssues[] = 'search widget is missing the search landmark on its form';
}
if (!str_contains($widgetSearchOne, '<fieldset class="widget-form-fieldset">') || !str_contains($widgetSearchOne, 'widget-search-legend-101')) {
    $widgetRenderIssues[] = 'search widget is missing fieldset/legend semantics';
}
if (!str_contains($widgetSearchOne, '<section class="widget-card" aria-labelledby="w-101-title">')
    || !str_contains($widgetSearchOne, '<h3 id="w-101-title" class="widget-card__title">')) {
    $widgetRenderIssues[] = 'sidebar/footer widget cards are missing heading-backed aria-labelledby semantics';
}
if (str_contains($widgetSearchOne, '<section class="widget-card" aria-label=')) {
    $widgetRenderIssues[] = 'search widget still renders the legacy aria-label-only widget card';
}
if (!str_contains($blogWidgetLibSource, "\$settings['content'] ?? (\$settings['text'] ?? '')")) {
    $widgetRenderIssues[] = 'intro widget is missing settings-based HTML content fallback';
}
if (str_contains($blogWidgetLibSource, "getSetting('home_intro', '')")) {
    $widgetRenderIssues[] = 'intro widget still falls back to legacy home_intro setting';
}
if (isModuleEnabled('board')) {
    $widgetBoardSlug = uniqueBoardSlug($pdo, 'runtime-audit-widget-board-' . bin2hex(random_bytes(4)));
    $pdo->prepare(
        "INSERT INTO cms_board (
            title, slug, board_type, excerpt, description, category_id, posted_date, removal_date,
            image_file, contact_name, contact_phone, contact_email,
            filename, original_name, file_size, sort_order, is_pinned, is_published, status, author_id, created_at
         ) VALUES (?, ?, 'notice', ?, ?, NULL, CURDATE(), NULL, '', '', '', '', '', '', 0, -70, 1, 1, 'published', ?, NOW())"
    )->execute([
        'Runtime audit widget vývěska',
        $widgetBoardSlug,
        'Položka vývěsky vytvořená jen pro audit featured widgetu.',
        '<p>Audit featured widgetu vývěsky.</p>',
        $runtimeAuditAuthorId > 0 ? $runtimeAuditAuthorId : null,
    ]);
    $widgetBoardId = (int)$pdo->lastInsertId();
    $cleanup['board_ids'][] = $widgetBoardId;

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
$widgetSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/widget_save.php');
$widgetLibSource = (string)file_get_contents(dirname(__DIR__) . '/lib/widgets.php');
$widgetPublicCssSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/assets/public.css');
$widgetsAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/widgets.php');
$settingsModulesSource = (string)file_get_contents(dirname(__DIR__) . '/admin/settings_modules.php');
$widgetsMigrateSource = (string)file_get_contents(dirname(__DIR__) . '/migrate.php');
$newsletterWidgetSubscribeSource = (string)file_get_contents(dirname(__DIR__) . '/newsletter_widget_subscribe.php');
$uiSource = (string)file_get_contents(dirname(__DIR__) . '/lib/ui.php');
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
if (!str_contains($widgetsAdminSource, 'Aktuální blog (na blogových stránkách)')) {
    $widgetRenderIssues[] = 'admin widgets page is missing current-blog option for latest articles';
}
foreach ([
    '<legend>Základní nastavení widgetu</legend>',
    'id="wd-dynamic-fieldset"',
    '<legend id="wd-dynamic-legend">Doplňující nastavení widgetu</legend>',
    'aria-describedby="wd-blog-help"',
    'aria-describedby="wd-form-help"',
    'aria-describedby="wd-content-help"',
    'class="widget-panel"',
    'class="widget-dialog"',
    'class="widget-dialog-overlay"',
    'class="widget-dialog-fieldset widget-dialog-fieldset--dynamic"',
    'class="widget-sort-list"',
] as $widgetAdminDialogFragment) {
    if (!str_contains($widgetsAdminSource, $widgetAdminDialogFragment)) {
        $widgetRenderIssues[] = 'admin widgets dialog is missing accessibility fragment: ' . $widgetAdminDialogFragment;
    }
}
foreach ([
    'function toggleDialogField(fieldName, visible)',
    "field.hidden = !visible;",
    "field.setAttribute('aria-hidden', visible ? 'false' : 'true');",
    "el.disabled = disabled;",
    'function getDialogFocusableElements()',
    'el.offsetParent !== null',
    "document.body.classList.add('admin-modal-open')",
    "document.body.classList.remove('admin-modal-open')",
    "t.classList.add('widget-sort-item--dragging')",
    "dragged.classList.remove('widget-sort-item--dragging')",
] as $widgetAdminDialogJsFragment) {
    if (!str_contains($widgetsAdminSource, $widgetAdminDialogJsFragment)) {
        $widgetRenderIssues[] = 'admin widgets dialog is missing accessibility JS fragment: ' . $widgetAdminDialogJsFragment;
    }
}
if (str_contains($widgetsAdminSource, '<style') || str_contains($widgetsAdminSource, 'style=')) {
    $widgetRenderIssues[] = 'admin widgets page still contains local style blocks or inline style attributes';
}
if (str_contains($widgetsAdminSource, '.style')) {
    $widgetRenderIssues[] = 'admin widgets page still mutates inline styles via JavaScript';
}
foreach ([
    'widgetSocialLinkDefinitions()',
    'name="widget_<?= h($socialSettingKey) ?>"',
    "type === 'social_links'",
] as $socialWidgetFieldFragment) {
    if (!str_contains($widgetsAdminSource, $socialWidgetFieldFragment)) {
        $widgetRenderIssues[] = 'admin widgets page is missing social widget fragment: ' . $socialWidgetFieldFragment;
    }
}
if (!str_contains($widgetSaveSource, '$rawBlogId === -1 ? -1')) {
    $widgetRenderIssues[] = 'widget save is missing current-blog persistence for latest articles';
}
if (!str_contains($widgetLibSource, "\$rawBlogId === -1")) {
    $widgetRenderIssues[] = 'latest articles widget is missing current-blog render fallback';
}
if (!str_contains($widgetLibSource, 'a.perex, a.content') || !str_contains($widgetLibSource, 'articleReadingMeta(')) {
    $widgetRenderIssues[] = 'latest articles widget is missing reading-time metadata from article content';
}
if (!str_contains($widgetLibSource, 'widget-list__meta') || !str_contains($widgetPublicCssSource, '.widget-list__meta')) {
    $widgetRenderIssues[] = 'latest articles widget is missing styled metadata under sidebar/footer links';
}
foreach (['function widgetHeadingId', 'function widgetCardStart', 'function widgetCardTitle'] as $widgetCardHelperFragment) {
    if (!str_contains($widgetLibSource, $widgetCardHelperFragment)) {
        $widgetRenderIssues[] = 'widget library is missing shared widget card helper: ' . $widgetCardHelperFragment;
    }
}
if (str_contains($widgetLibSource, '<section class="widget-card" aria-label="')) {
    $widgetRenderIssues[] = 'widget-card renderers still contain the legacy aria-label-only section pattern';
}
foreach (['.widget-gallery-grid', '.widget-gallery-grid--compact', '.widget-gallery-grid--wide', '.widget-gallery-grid__image', '.widget-gallery-actions'] as $widgetGalleryCssSelector) {
    if (!str_contains($widgetPublicCssSource, $widgetGalleryCssSelector)) {
        $widgetRenderIssues[] = 'gallery widget CSS is missing selector ' . $widgetGalleryCssSelector;
    }
}
foreach ([
    'style="display:grid;grid-template-columns:repeat(3,1fr);gap:.3rem"',
    'style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.5rem"',
    'style="width:100%;aspect-ratio:1;object-fit:cover',
    'style="margin-top:.5rem"',
    'style="margin-top:.75rem"',
] as $legacyGalleryWidgetInlineStyle) {
    if (str_contains($widgetLibSource, $legacyGalleryWidgetInlineStyle)) {
        $widgetRenderIssues[] = 'gallery widget still contains legacy inline layout style: ' . $legacyGalleryWidgetInlineStyle;
    }
}
if (!str_contains($widgetSaveSource, "case 'social_links':")) {
    $widgetRenderIssues[] = 'widget save handler is missing social links settings persistence';
}
if (!str_contains($widgetSaveSource, "case 'search':")) {
    $widgetRenderIssues[] = 'widget save handler is missing search widget settings persistence';
}
if (!str_contains($widgetLibSource, "'visitor_stats'")) {
    $widgetRenderIssues[] = 'widget registry is missing visitor stats widget type';
}
if (!str_contains($widgetLibSource, 'function renderWidget_visitor_stats')) {
    $widgetRenderIssues[] = 'visitor stats widget renderer is missing';
}
if (!str_contains($widgetLibSource, "'social_links'")) {
    $widgetRenderIssues[] = 'widget registry is missing social links widget type';
}
if (!str_contains($widgetLibSource, 'function widgetSocialLinkDefinitions(): array')) {
    $widgetRenderIssues[] = 'widget library is missing shared social link definitions';
}
if (!str_contains($widgetLibSource, 'function normalizeWidgetExternalUrl(string $value): string')) {
    $widgetRenderIssues[] = 'widget library is missing external URL normalization for social links';
}
if (!str_contains($widgetLibSource, 'function renderWidget_social_links')) {
    $widgetRenderIssues[] = 'social links widget renderer is missing';
}
if (!str_contains($widgetLibSource, 'newsletter_widget_subscribe.php')) {
    $widgetRenderIssues[] = 'newsletter widget renderer is missing inline subscribe form action';
}
if (!str_contains($widgetLibSource, 'Najděte články, novinky, stránky a další obsah napříč celým webem.')) {
    $widgetRenderIssues[] = 'search widget is missing the default cross-site discovery helper text';
}
if (!str_contains($widgetLibSource, '<fieldset class="widget-form-fieldset">') || !str_contains($widgetLibSource, 'widget-search-legend-')) {
    $widgetRenderIssues[] = 'search widget renderer is missing fieldset/legend semantics';
}
if (!str_contains($widgetLibSource, 'name="return_url"') || !str_contains($widgetLibSource, 'name="email"')) {
    $widgetRenderIssues[] = 'newsletter widget is missing required subscribe form fields';
}
if (!str_contains($widgetLibSource, 'honeypotField()')) {
    $widgetRenderIssues[] = 'newsletter widget is missing honeypot protection';
}
if (!str_contains($widgetLibSource, 'newsletter-widget-legend-') || !str_contains($widgetLibSource, 'aria-invalid="true"')) {
    $widgetRenderIssues[] = 'newsletter widget renderer is missing accessible fieldset or invalid-state handling';
}
if (!str_contains($widgetLibSource, 'function widgetInstanceAvailability(array $widget): array')) {
    $widgetRenderIssues[] = 'widget library is missing shared per-widget availability evaluation';
}
if (!str_contains($widgetLibSource, "return widgetInstanceAvailability(\$w)['displayable'];")) {
    $widgetRenderIssues[] = 'public widget zone rendering is not reusing the shared widget availability evaluation';
}
if (!str_contains($widgetsAdminSource, 'widgetInstanceAvailability($w)') || !str_contains($widgetsAdminSource, 'Na webu se teď nezobrazí:')) {
    $widgetRenderIssues[] = 'admin widgets page is missing unavailable-widget notice above the settings action';
}
foreach ([
    'nejsou vyplněné žádné odkazy na sociální sítě',
    'sledování návštěvnosti je vypnuté',
] as $widgetAvailabilityReasonFragment) {
    if (!str_contains($widgetLibSource, $widgetAvailabilityReasonFragment)) {
        $widgetRenderIssues[] = 'widget availability helper is missing reason fragment: ' . $widgetAvailabilityReasonFragment;
    }
}
if (str_contains($settingsModulesSource, 'visitor_counter_enabled')) {
    $widgetRenderIssues[] = 'settings modules page still contains duplicate visitor_counter_enabled toggle';
}
if (!str_contains($settingsModulesSource, 'Statistiky návštěvnosti ve Správě widgetů')) {
    $widgetRenderIssues[] = 'settings modules page is missing widget placement hint for visitor stats';
}
if (!str_contains($widgetLibSource, "getSetting('visitor_tracking_enabled', '0') !== '1'")) {
    $widgetRenderIssues[] = 'visitor stats widget is not gated by visitor_tracking_enabled';
}
if (!str_contains($widgetLibSource, "!isModuleEnabled('statistics')")) {
    $widgetRenderIssues[] = 'visitor stats widget is not gated by statistics module availability';
}
if (str_contains($uiSource, 'visitor-counter-block') || str_contains($uiSource, "getSetting('visitor_counter_enabled', '0')")) {
    $widgetRenderIssues[] = 'site footer still hardcodes public visitor stats output';
}
foreach ([
    "getSetting('social_facebook'",
    "getSetting('social_youtube'",
    "getSetting('social_instagram'",
    "getSetting('social_twitter'",
] as $legacyFooterSocialFragment) {
    if (str_contains($uiSource, $legacyFooterSocialFragment)) {
        $widgetRenderIssues[] = 'site footer still hardcodes legacy social links setting: ' . $legacyFooterSocialFragment;
    }
}
foreach ([
    '/search.php">Vyhledávání</a>',
    '/subscribe.php">Odběr novinek</a>',
] as $legacyFooterDiscoveryFragment) {
    if (str_contains($uiSource, $legacyFooterDiscoveryFragment)) {
        $widgetRenderIssues[] = 'site footer still hardcodes legacy discovery link: ' . $legacyFooterDiscoveryFragment;
    }
}
if (!str_contains($widgetsMigrateSource, "widget_type = 'visitor_stats'")) {
    $widgetRenderIssues[] = 'migrate.php is missing visitor stats widget backfill for upgraded sites';
}
if (!str_contains($widgetsMigrateSource, "widget_type = 'social_links'")) {
    $widgetRenderIssues[] = 'migrate.php is missing social links widget backfill for upgraded sites';
}
if (!str_contains($widgetsMigrateSource, "widget_type = 'search' AND zone = 'footer'")) {
    $widgetRenderIssues[] = 'migrate.php is missing footer search widget backfill for upgraded sites';
}
if (!str_contains($widgetsMigrateSource, "widget_type = 'newsletter' AND zone = 'footer'")) {
    $widgetRenderIssues[] = 'migrate.php is missing footer newsletter widget backfill for upgraded sites';
}
if (!str_contains($newsletterWidgetSubscribeSource, "rateLimit('subscribe_widget', 3, 300)")) {
    $widgetRenderIssues[] = 'newsletter widget subscribe endpoint is missing dedicated rate limiting';
}
if (!str_contains($newsletterWidgetSubscribeSource, 'internalRedirectTarget')) {
    $widgetRenderIssues[] = 'newsletter widget subscribe endpoint is missing safe return_url validation';
}
if (!str_contains($newsletterWidgetSubscribeSource, "'email' => \$email")) {
    $widgetRenderIssues[] = 'newsletter widget subscribe endpoint does not preserve invalid e-mail value for PRG retry';
}
if ($widgetRenderIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($widgetRenderIssues as $widgetRenderIssue) {
        echo '- ' . $widgetRenderIssue . "\n";
    }
}

echo "=== blog_static_pages_guardrails ===\n";
$blogStaticPageIssues = [];
$blogStaticPageControllerSource = (string)file_get_contents(dirname(__DIR__) . '/blog/page.php');
$blogStaticPagesAdminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/blog_pages.php');
$blogStaticHtaccessSource = (string)file_get_contents(dirname(__DIR__) . '/.htaccess');
$blogStaticReadmeSource = (string)file_get_contents(dirname(__DIR__) . '/README.md');
$blogStaticStatsSource = (string)file_get_contents(dirname(__DIR__) . '/lib/stats.php');
$blogStaticPageFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/page_form.php');
$blogStaticPageSaveSource = (string)file_get_contents(dirname(__DIR__) . '/admin/page_save.php');
$blogStaticPagesListSource = (string)file_get_contents(dirname(__DIR__) . '/admin/pages.php');
$blogStaticMenuSource = (string)file_get_contents(dirname(__DIR__) . '/admin/menu.php');
$blogStaticConvertSource = (string)file_get_contents(dirname(__DIR__) . '/admin/convert_content.php');
$blogStaticInstallSource = (string)file_get_contents(dirname(__DIR__) . '/install.php');
$blogStaticMigrateSource = (string)file_get_contents(dirname(__DIR__) . '/migrate.php');
$blogStaticHttpIntegrationSource = (string)file_get_contents(dirname(__DIR__) . '/build/http_integration.php');
if (!str_contains($blogStaticStatsSource, 'site-nav-heading') || !str_contains($blogStaticStatsSource, 'aria-labelledby')) {
    $blogStaticPageIssues[] = 'site navigation is missing a screen-reader heading with aria-labelledby';
}
if (!str_contains($blogStaticStatsSource, '<h2 id="\' . $navHeadingId . \'" class="sr-only">Hlavní navigace</h2>')) {
    $blogStaticPageIssues[] = 'site navigation heading is not rendered as a real heading element for screen readers';
}
if (str_contains($blogStaticStatsSource, 'aria-label="Hlavní navigace"')) {
    $blogStaticPageIssues[] = 'site navigation still contains the legacy aria-label-only implementation';
}
if (!str_contains($blogStaticInstallSource, 'blog_id') || !str_contains($blogStaticInstallSource, 'blog_nav_order') || !str_contains($blogStaticInstallSource, 'idx_pages_blog_nav')) {
    $blogStaticPageIssues[] = 'fresh install is missing blog page schema fields on cms_pages';
}
if (!str_contains($blogStaticInstallSource, 'CREATE TABLE IF NOT EXISTS cms_nav_links') || !str_contains($blogStaticInstallSource, 'idx_nav_links_scope')) {
    $blogStaticPageIssues[] = 'fresh install is missing external navigation links schema';
}
if (!str_contains($blogStaticMigrateSource, 'cms_pages.blog_id') || !str_contains($blogStaticMigrateSource, 'cms_pages.blog_nav_order') || !str_contains($blogStaticMigrateSource, 'idx_pages_blog_nav')) {
    $blogStaticPageIssues[] = 'migration is missing blog page schema upgrade on cms_pages';
}
if (!str_contains($blogStaticMigrateSource, 'cms_nav_links') || !str_contains($blogStaticMigrateSource, 'idx_nav_links_scope')) {
    $blogStaticPageIssues[] = 'migration is missing external navigation links schema';
}
if (!str_contains($blogPresentationSource, 'function pageBlogContext') || !str_contains($blogPresentationSource, '/stranka/') || !str_contains($blogPresentationSource, 'function normalizeBlogPageNavigationOrder') || !str_contains($blogPresentationSource, 'function nextBlogPageNavigationOrder') || !str_contains($blogPresentationSource, 'function navigationLinkAnchorAttributes')) {
    $blogStaticPageIssues[] = 'presentation helpers are missing blog page routing or ordering support';
}
if (!str_contains($blogRouterSource, '$pageSlug = pageSlug') || !str_contains($blogRouterSource, "require __DIR__ . '/blog/page.php'")) {
    $blogStaticPageIssues[] = 'blog router is missing dedicated handling for blog static pages';
}
if (!str_contains($blogStaticHtaccessSource, '/stranka/') || !str_contains($blogStaticHtaccessSource, 'page_slug=$2')) {
    $blogStaticPageIssues[] = '.htaccess is missing blog static page rewrite before article routes';
}
if (!str_contains($blogStaticReadmeSource, '/blog_router.php?blog_slug=$1&page_slug=$2&$args')) {
    $blogStaticPageIssues[] = 'README nginx sample is missing the blog static page route';
}
if (!str_contains($blogIndexControllerSource, 'SELECT id, title, slug, blog_id, blog_nav_order') || !str_contains($blogIndexControllerSource, '$blogPages') || !str_contains($blogIndexControllerSource, 'loadNavigationLinks($pdo, $blogId, true)')) {
    $blogStaticPageIssues[] = 'blog index controller is missing loading of ordered blog pages';
}
if (!str_contains($blogIndexViewSource, 'Stránky blogu') || !str_contains($blogIndexViewSource, 'aria-labelledby="blog-pages-heading"') || !str_contains($blogIndexViewSource, 'navigationLinkAnchorAttributes($blogPage)')) {
    $blogStaticPageIssues[] = 'blog index view is missing the labeled blog page navigation block';
}
if (!str_contains($blogStaticPageControllerSource, "'view' => 'page'") || !str_contains($blogStaticPageControllerSource, 'Zpět na blog') || !str_contains($blogStaticPageControllerSource, "'page-blog-static'")) {
    $blogStaticPageIssues[] = 'blog page controller is missing page rendering or the back-to-blog affordance';
}
if (!str_contains($blogStaticPageFormSource, 'name="blog_id"') || !str_contains($blogStaticPageFormSource, 'Patří k blogu') || !str_contains($blogStaticPageFormSource, 'Pořadí stránek blogu')) {
    $blogStaticPageIssues[] = 'page editor is missing blog assignment controls';
}
if (!str_contains($blogStaticPageSaveSource, '$targetBlogId') || !str_contains($blogStaticPageSaveSource, 'nextBlogPageNavigationOrder') || !str_contains($blogStaticPageSaveSource, 'normalizeBlogPageNavigationOrder')) {
    $blogStaticPageIssues[] = 'page save is missing blog-specific persistence or ordering';
}
if (!str_contains($blogStaticPagesAdminSource, 'blog_nav_order') || !str_contains($blogStaticPagesAdminSource, 'Uložit pořadí') || !str_contains($blogStaticPagesAdminSource, 'blog-page-order-status') || !str_contains($blogStaticPagesAdminSource, 'Přidat externí odkaz blogu')) {
    $blogStaticPageIssues[] = 'blog page ordering admin screen is missing reorder persistence or accessibility helpers';
}
if (str_contains($blogStaticPagesAdminSource, 'style=')) {
    $blogStaticPageIssues[] = 'blog page ordering admin screen still contains inline style attributes';
}
foreach ([
    'class="admin-description"',
    'field-help field-help--flush',
    'class="admin-sort-list"',
    'class="admin-sort-item',
    'class="admin-sort-item__body"',
    'class="table-meta"',
    'class="admin-action-row"',
    'admin-sort-item--dragging',
] as $blogStaticPagesAdminUtilityFragment) {
    if (!str_contains($blogStaticPagesAdminSource, $blogStaticPagesAdminUtilityFragment)) {
        $blogStaticPageIssues[] = 'blog page ordering admin screen is missing utility class fragment: ' . $blogStaticPagesAdminUtilityFragment;
    }
}
if (!str_contains($blogStaticPagesListSource, 'Blogová stránka') || !str_contains($blogStaticPagesListSource, '/admin/blog_pages.php?blog_id=')) {
    $blogStaticPageIssues[] = 'pages overview is missing blog page classification or reorder link';
}
if (!str_contains($blogStaticMenuSource, 'WHERE blog_id IS NULL AND deleted_at IS NULL')) {
    $blogStaticPageIssues[] = 'global navigation management still includes blog pages';
}
if (!str_contains($blogStaticConvertSource, 'blog_nav_order') || !str_contains($blogStaticConvertSource, 'nextBlogPageNavigationOrder') || !str_contains($blogStaticConvertSource, '$targetBlogId = !empty($page[\'blog_id\']) ? (int)$page[\'blog_id\'] : $defaultBlogId')) {
    $blogStaticPageIssues[] = 'content conversion is missing blog-preserving page/article behavior';
}
if (!str_contains($blogExportSource, 'blog_id, blog_nav_order') || !str_contains($blogImportSource, 'blog_id, blog_nav_order') || !str_contains($blogExportSource, 'nav_links') || !str_contains($blogImportSource, 'nav_links')) {
    $blogStaticPageIssues[] = 'export/import is missing blog page fields';
}
if (!str_contains($blogStaticHttpIntegrationSource, "blog_static_pages_http")) {
    $blogStaticPageIssues[] = 'http integration suite is missing blog static page scenarios';
}
if ($blogStaticPageIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($blogStaticPageIssues as $blogStaticPageIssue) {
        echo '- ' . $blogStaticPageIssue . "\n";
    }
}

echo "=== menu_admin_guardrails ===\n";
$menuAdminIssues = [];
$adminMenuSource = (string)file_get_contents(dirname(__DIR__) . '/admin/menu.php');
$adminPagesSource = (string)file_get_contents(dirname(__DIR__) . '/admin/pages.php');
$pageFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/page_form.php');
$pagePositionsSource = (string)file_get_contents(dirname(__DIR__) . '/admin/page_positions.php');
$publicNavSource = (string)file_get_contents(dirname(__DIR__) . '/lib/stats.php');
$adminFormFormSource = (string)file_get_contents(dirname(__DIR__) . '/admin/form_form.php');
if (!str_contains($adminMenuSource, 'id="nav-order-status"')) {
    $menuAdminIssues[] = 'admin menu is missing live status region for keyboard reorder feedback';
}
if (!str_contains($adminMenuSource, 'aria-disabled')) {
    $menuAdminIssues[] = 'admin menu does not expose aria-disabled states on move buttons';
}
if (!str_contains($adminMenuSource, '/admin/blogs.php') || !str_contains($adminMenuSource, '/admin/settings_modules.php') || !str_contains($adminMenuSource, '/admin/page_form.php?id=')) {
    $menuAdminIssues[] = 'admin menu is missing quick links for fixing disabled items';
}
if (!str_contains($adminPagesSource, '/admin/menu.php') || str_contains($adminPagesSource, 'page_positions.php')) {
    $menuAdminIssues[] = 'pages overview still points static page ordering to the wrong screen';
}
if (!str_contains($adminPagesSource, 'value="page_to_article"') || !str_contains($adminPagesSource, 'aria-hidden="true"')) {
    $menuAdminIssues[] = 'pages overview still exposes the page-to-article arrow to screen readers';
}
if (!str_contains($pageFormSource, '/admin/menu.php') || str_contains($pageFormSource, 'page_positions.php')) {
    $menuAdminIssues[] = 'page form help still points navigation ordering to the wrong screen';
}
if (!str_contains($pagePositionsSource, '/admin/menu.php?page_positions=1')) {
    $menuAdminIssues[] = 'page positions compatibility screen does not redirect to unified navigation management';
}
if (!str_contains($publicNavSource, '$renderUnifiedEntry') || !str_contains($publicNavSource, 'foreach (array_keys($pagesMap) as $pageId)')) {
    $menuAdminIssues[] = 'public navigation does not append missing unified entries safely';
}
if (!str_contains($publicNavSource, "\$navHeadingId = 'site-nav-heading';") || !str_contains($publicNavSource, "aria-labelledby=\"' . \$navHeadingId . '\"")) {
    $menuAdminIssues[] = 'public navigation is missing the heading-backed main navigation landmark';
}
if (!str_contains($publicNavSource, '<h2 id="\' . $navHeadingId . \'" class="sr-only">Hlavní navigace</h2>')) {
    $menuAdminIssues[] = 'public navigation still renders the main navigation label as plain text instead of a heading';
}
if (str_contains($publicNavSource, 'aria-label="Hlavní navigace"')) {
    $menuAdminIssues[] = 'public navigation still contains legacy aria-label-only main navigation markup';
}
if (!str_contains($adminMenuSource, 'FROM cms_forms')) {
    $menuAdminIssues[] = 'admin menu no longer includes forms in unified navigation source';
}
if (!str_contains($adminMenuSource, 'Upravit formulář')) {
    $menuAdminIssues[] = 'admin menu is missing form edit action';
}
if (!str_contains($publicNavSource, 'foreach (array_keys($visibleForms) as $formId)')) {
    $menuAdminIssues[] = 'public navigation does not append missing form entries safely';
}
if (!str_contains($publicNavSource, '$visibleBlogEntries, $visibleForms, $visibleLinks, $li, $cur, $current')) {
    $menuAdminIssues[] = 'public navigation unified renderer is missing visible forms or links in closure scope';
}
if (!str_contains($publicNavSource, 'loadPublicNavigationForms()')) {
    $menuAdminIssues[] = 'public navigation is not using the shared public form navigation helper';
}
if (!str_contains($publicNavSource, "current === 'form:'")) {
    $menuAdminIssues[] = 'public navigation is missing current state support for forms';
}
if (!str_contains($publicNavSource, 'function formVisibleInPublicNavigation(')
    || !str_contains($publicNavSource, 'function formPublicNavigationStatusParts(')
    || !str_contains($publicNavSource, 'function loadPublicNavigationForms(')) {
    $menuAdminIssues[] = 'public navigation helper set for forms is incomplete';
}
if (!str_contains($adminFormFormSource, 'name="show_in_nav"')) {
    $menuAdminIssues[] = 'form editor is missing show_in_nav checkbox';
}
if (!str_contains($adminMenuSource, 'formVisibleInPublicNavigation($formRow)')
    || !str_contains($adminMenuSource, "implode(' · ', formPublicNavigationStatusParts(\$formRow))")) {
    $menuAdminIssues[] = 'admin menu is not using shared public form availability logic';
}
if (!str_contains($adminMenuSource, 'Přidat externí odkaz') || !str_contains($adminMenuSource, "key' => 'link:'") || !str_contains($publicNavSource, 'loadNavigationLinks(db_connect(), null, true)')) {
    $menuAdminIssues[] = 'global navigation is missing external link management or rendering';
}
if ($httpIntegrationSource !== '' && !str_contains($httpIntegrationSource, "httpIntegrationPrintResult('forms_nav_http'")) {
    $menuAdminIssues[] = 'http integration is missing forms navigation scenario';
}
if ($menuAdminIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($menuAdminIssues as $menuAdminIssue) {
        echo '- ' . $menuAdminIssue . "\n";
    }
}

echo "=== theme_layout_guardrails ===\n";
$themeLayoutIssues = [];
$themeBaseLayoutSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/layouts/base.php');
$themeHeadPartialSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/partials/head.php');
$themeRuntimeSource = (string)file_get_contents(dirname(__DIR__) . '/lib/theme.php');
$themeCoreCssSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/assets/public-core.css');
$themePublicCssSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/assets/public.css');
$themePageViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/page.php');
$themeBlogIndexViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/blog-index.php');
$themeChatViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/chat.php');
$themeFoodIndexViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/food-index.php');
$themeDownloadsArticleViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/downloads-article.php');
$themeDownloadsIndexViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/downloads-index.php');
$themeEventsArticleViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/events-article.php');
$themeFaqArticleViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/faq-article.php');
$themeFormsShowViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/forms-show.php');
$themePollsIndexViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/polls-index.php');
$themeReservationsBookViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/reservations-book.php');
$themeReservationsResourceViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/reservations-resource.php');
$themeAccountReservationsViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/account/reservations.php');
$themeReservationCancelBookingViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/reservations-cancel-booking.php');
$themeFoodCardViewSource = (string)file_get_contents(dirname(__DIR__) . '/themes/default/views/modules/food-card.php');
if (!str_contains($themeBaseLayoutSource, 'aria-labelledby="page-sidebar-heading"')
    || !str_contains($themeBaseLayoutSource, '<h2 id="page-sidebar-heading" class="sr-only">Postranní panel</h2>')) {
    $themeLayoutIssues[] = 'default theme sidebar is missing heading-backed landmark semantics';
}
if (str_contains($themeBaseLayoutSource, "querySelector('[role=\"status\"]:not(#a11y-live),[role=\"alert\"]')")
    || str_contains($themeBaseLayoutSource, "message.removeAttribute('role');")) {
    $themeLayoutIssues[] = 'default theme layout still mutates server-rendered status roles on page load';
}
if (!str_contains($themeBaseLayoutSource, "closest('[data-confirm]')")
    || !str_contains($themeBaseLayoutSource, 'window.confirm(message)')) {
    $themeLayoutIssues[] = 'default theme layout is missing nonce-backed data-confirm handling';
}
if (!str_contains($themeBaseLayoutSource, "closest('.js-print-page')")
    || !str_contains($themeBaseLayoutSource, 'window.print()')) {
    $themeLayoutIssues[] = 'default theme layout is missing nonce-backed print button handling';
}
if (str_contains($themeBaseLayoutSource, 'style=') || str_contains($themeBaseLayoutSource, '.style') || str_contains($themeBaseLayoutSource, 'style.cssText')) {
    $themeLayoutIssues[] = 'default theme layout still contains inline style markup or JS style mutations';
}
if (!str_contains($themeBaseLayoutSource, 'role="status" aria-labelledby="theme-preview-banner-heading"')
    || !str_contains($themeBaseLayoutSource, '<strong id="theme-preview-banner-heading">Živý náhled:</strong>')
    || str_contains($themeBaseLayoutSource, 'role="status" aria-label="Živý náhled šablony"')) {
    $themeLayoutIssues[] = 'default theme preview banner is missing heading-backed status semantics';
}
if (!str_contains($themeBaseLayoutSource, 'function copyTextFallback(value)')
    || !str_contains($themeBaseLayoutSource, "ta.className = 'clipboard-fallback-control';")
    || !str_contains($themePublicCssSource, '.clipboard-fallback-control')) {
    $themeLayoutIssues[] = 'default theme clipboard fallback is missing class-based styling';
}
if (str_contains($themeHeadPartialSource, 'publicA11yStyleTag()') || str_contains($themeRuntimeSource, 'echo publicA11yStyleTag();')) {
    $themeLayoutIssues[] = 'public theme head still renders shared a11y styles inline instead of using public-core.css';
}
if (!str_contains($themeHeadPartialSource, "themeAssetUrl('assets/public-core.css', defaultThemeName())")
    || !str_contains($themeRuntimeSource, "themeAssetUrl('assets/public-core.css', defaultThemeName())")) {
    $themeLayoutIssues[] = 'public theme head is missing the shared public-core.css stylesheet';
}
foreach ([
    '.skip-link',
    '.sr-only',
    '.honeypot-field',
    '.cookie-banner',
    '.public-admin-bar',
    '.public-admin-bar__link--edit',
] as $themeSharedCssFragment) {
    if (!str_contains($themeCoreCssSource, $themeSharedCssFragment)) {
        $themeLayoutIssues[] = 'shared public core CSS is missing helper fragment: ' . $themeSharedCssFragment;
    }
}
if (!str_contains($themeAccountReservationsViewSource, 'data-confirm="Opravdu chcete zrušit tuto rezervaci?"')) {
    $themeLayoutIssues[] = 'account reservations view is missing data-confirm reservation cancellation';
}
if (!str_contains($themeReservationCancelBookingViewSource, 'data-confirm="Opravdu zrušit rezervaci?"')) {
    $themeLayoutIssues[] = 'reservation cancellation view is missing data-confirm submit guard';
}
if (!str_contains($themeFoodCardViewSource, 'js-print-page')) {
    $themeLayoutIssues[] = 'food card view is missing shared print button hook';
}
$themeHeadingBackedGroups = [
    'food tabs' => [
        'source' => $themeFoodIndexViewSource,
        'required' => ['id="food-tabs-heading"', 'role="tablist" aria-labelledby="food-tabs-heading"'],
        'forbidden' => ['role="tablist" aria-label="Typ lístku"'],
    ],
    'reservation slot picker' => [
        'source' => $themeReservationsBookViewSource,
        'required' => ['<legend id="reservation-time-legend">Výběr času</legend>', 'role="radiogroup" aria-labelledby="reservation-time-legend"'],
        'forbidden' => ['role="radiogroup" aria-label="Dostupné časové sloty"'],
    ],
    'poll results list' => [
        'source' => $themePollsIndexViewSource,
        'required' => ['class="poll-results" role="list" aria-labelledby="poll-results-title"'],
        'forbidden' => ['class="poll-results" role="list" aria-label="Možnosti a výsledky hlasování"'],
    ],
    'chat message list' => [
        'source' => $themeChatViewSource,
        'required' => ['id="chat-messages-heading"', 'class="chat-stream" role="list" aria-labelledby="chat-messages-heading"', 'class="chat-message" role="listitem"'],
        'forbidden' => ['class="chat-stream" aria-label="Zprávy z chatu"'],
    ],
    'reservation calendar table' => [
        'source' => $themeReservationsResourceViewSource,
        'required' => ['class="calendar-table" aria-labelledby="reservation-calendar-caption"', '<caption id="reservation-calendar-caption" class="sr-only">Kalendář rezervací na'],
        'forbidden' => ['class="calendar-table" aria-label="Kalendář rezervací na'],
    ],
];
foreach ($themeHeadingBackedGroups as $themeGroupLabel => $themeGroupGuardrail) {
    $themeGroupSource = (string)$themeGroupGuardrail['source'];
    foreach ($themeGroupGuardrail['required'] as $themeGroupRequiredFragment) {
        if (!str_contains($themeGroupSource, $themeGroupRequiredFragment)) {
            $themeLayoutIssues[] = 'default theme ' . $themeGroupLabel . ' is missing heading-backed group fragment: ' . $themeGroupRequiredFragment;
        }
    }
    foreach ($themeGroupGuardrail['forbidden'] as $themeGroupForbiddenFragment) {
        if (str_contains($themeGroupSource, $themeGroupForbiddenFragment)) {
            $themeLayoutIssues[] = 'default theme ' . $themeGroupLabel . ' still contains aria-label-only group semantics';
        }
    }
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/themes/default/views')) as $themeViewFile) {
    if (!$themeViewFile instanceof SplFileInfo || !$themeViewFile->isFile() || $themeViewFile->getExtension() !== 'php') {
        continue;
    }
    $themeViewSource = (string)file_get_contents($themeViewFile->getPathname());
    if (str_contains($themeViewSource, 'onclick=')) {
        $themeLayoutIssues[] = 'default theme view still contains inline onclick handler: ' . $themeViewFile->getFilename();
    }
}
if (str_contains($themeFormsShowViewSource, '$value = h((string)$rawValue);')
    || !str_contains($themeFormsShowViewSource, '$value = is_array($rawValue) ? \'\' : h((string)$rawValue);')) {
    $themeLayoutIssues[] = 'default theme forms renderer still casts checkbox group values to string';
}
if (preg_match('/\.card-grid\s*\{[^}]*align-items:\s*start/s', $themePublicCssSource) !== 1
    || preg_match('/\.card\s*\{[^}]*height:\s*auto/s', $themePublicCssSource) !== 1) {
    $themeLayoutIssues[] = 'default theme cards can still stretch across grid or section height';
}
if (preg_match('/\.card\s*\{[^}]*height:\s*100%/s', $themePublicCssSource) === 1) {
    $themeLayoutIssues[] = 'default theme base card still uses height: 100%';
}
foreach ([
    '.listing-filters',
    '.page-back-link',
    '.blog-intro-content',
    '.blog-featured-article',
    '.blog-search-input',
    '.chat-filter-form',
    '.chat-filter-search',
    '.stack-sections--spaced',
    '.stack-actions--spaced',
    '.surface--subsection',
    '.filter-option-toggle',
    '.button-row--filters',
] as $themeCssSelector) {
    if (!str_contains($themePublicCssSource, $themeCssSelector)) {
        $themeLayoutIssues[] = 'default theme CSS is missing cleanup selector ' . $themeCssSelector;
    }
}
foreach ([
    $themePageViewSource => ['style="margin-top:1.5rem"'],
    $themeBlogIndexViewSource => [
        'style="display:block;max-width:min(100%,22rem);max-height:8rem;width:auto;height:auto"',
        'style="margin-top:1rem"',
        'style="margin-bottom:1.5rem"',
        'style="margin-bottom:1.25rem"',
        'style="min-width:min(26rem,100%)"',
    ],
    $themeChatViewSource => [
        'style="margin-bottom:1rem"',
        'style="width:min(100%, 20rem)"',
    ],
    $themeDownloadsArticleViewSource => [
        'style="margin-top:1.5rem"',
        'style="margin-top:1rem"',
    ],
    $themeDownloadsIndexViewSource => ['style="font-weight:normal;margin-top:.25rem"'],
    $themeEventsArticleViewSource => [
        'style="margin-top:1.5rem"',
        'style="margin-top:1rem"',
    ],
    $themeFaqArticleViewSource => ['style="margin-top:2rem"'],
    $themeFormsShowViewSource => ['style="margin-top:1rem"'],
    $themePollsIndexViewSource => [
        'style="margin:1rem 0"',
        'style="align-items:flex-end;flex-wrap:wrap"',
    ],
] as $themeViewSource => $legacyInlineStyles) {
    foreach ($legacyInlineStyles as $legacyInlineStyle) {
        if (str_contains((string)$themeViewSource, $legacyInlineStyle)) {
            $themeLayoutIssues[] = 'default theme view still contains legacy inline layout styles: ' . $legacyInlineStyle;
        }
    }
}
if ($themeLayoutIssues === []) {
    echo "OK\n";
} else {
    $failures++;
    foreach ($themeLayoutIssues as $themeLayoutIssue) {
        echo '- ' . $themeLayoutIssue . "\n";
    }
}

echo "=== page_positions_redirect ===\n";
$pagePositionsRedirectProbe = fetchUrl($baseUrl . '/admin/page_positions.php', 'PHPSESSID=' . $auditSessionId, 0);
if (!preg_match('/\s30[12378]\s/', $pagePositionsRedirectProbe['status'])) {
    echo "- admin/page_positions.php does not redirect to unified navigation management\n";
    $failures++;
} elseif (!responseHasLocationHeader($pagePositionsRedirectProbe['headers'], '/admin/menu.php?page_positions=1', $baseUrl)) {
    echo "- admin/page_positions.php does not point to /admin/menu.php?page_positions=1\n";
    $failures++;
} else {
    echo "OK\n";
}

exit($failures > 0 ? 1 : 0);
