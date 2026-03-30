<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('board')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = inputInt('get', 'id');
$slug = boardSlug(trim($_GET['slug'] ?? ''));
if ($id === null && $slug === '') {
    header('Location: ' . BASE_URL . '/board/index.php');
    exit;
}

$pdo = db_connect();

if ($slug !== '') {
    $stmt = $pdo->prepare(
        "SELECT b.*, COALESCE(c.name, '') AS category_name
         FROM cms_board b
         LEFT JOIN cms_board_categories c ON c.id = b.category_id
         WHERE b.slug = ? AND " . boardPublicVisibilitySql('b') . "
         LIMIT 1"
    );
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->prepare(
        "SELECT b.*, COALESCE(c.name, '') AS category_name
         FROM cms_board b
         LEFT JOIN cms_board_categories c ON c.id = b.category_id
         WHERE b.id = ? AND " . boardPublicVisibilitySql('b') . "
         LIMIT 1"
    );
    $stmt->execute([$id]);
}

$document = $stmt->fetch() ?: null;
if (!$document) {
    http_response_code(404);
    $siteName = getSetting('site_name', 'Kora CMS');
    $missingPath = $slug !== ''
        ? BASE_URL . '/board/' . rawurlencode($slug)
        : BASE_URL . '/board/document.php' . ($id !== null ? '?id=' . urlencode((string)$id) : '');

    renderPublicPage([
        'title' => 'Položka nenalezena - ' . $siteName,
        'meta' => [
            'title' => 'Položka nenalezena - ' . $siteName,
            'url' => $missingPath,
        ],
        'view' => 'not-found',
        'body_class' => 'page-board-not-found',
    ]);
    exit;
}

$document = hydrateBoardPresentation($document);

if ($slug === '' && !empty($document['slug'])) {
    header('Location: ' . boardPublicPath($document));
    exit;
}

if (!isset($_SESSION['cms_user_id'])) {
    trackPageView('board', (int)$document['id']);
}

$siteName = getSetting('site_name', 'Kora CMS');
$boardLabel = boardModulePublicLabel();
$metaDescription = boardExcerpt($document, 180);
if ($metaDescription === '') {
    $metaDescription = 'Detail položky modulu ' . $boardLabel . '.';
}

renderPublicPage([
    'title' => (string)$document['title'] . ' - ' . $siteName,
    'meta' => [
        'title' => (string)$document['title'] . ' - ' . $siteName,
        'description' => $metaDescription,
        'url' => boardPublicUrl($document),
        'type' => 'article',
    ],
    'view' => 'modules/board-article',
    'view_data' => [
        'boardLabel' => $boardLabel,
        'document' => $document,
    ],
    'current_nav' => 'board',
    'body_class' => 'page-board-article',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/board_form.php?id=' . (int)$document['id'],
]);
