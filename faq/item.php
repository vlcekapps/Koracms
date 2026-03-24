<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('faq')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = inputInt('get', 'id');
$slug = faqSlug(trim($_GET['slug'] ?? ''));
if ($id === null && $slug === '') {
    header('Location: ' . BASE_URL . '/faq/index.php');
    exit;
}

$pdo = db_connect();

if ($slug !== '') {
    $stmt = $pdo->prepare(
        "SELECT f.*, c.name AS category_name
         FROM cms_faqs f
         LEFT JOIN cms_faq_categories c ON c.id = f.category_id
         WHERE f.slug = ? AND COALESCE(f.status,'published') = 'published' AND f.is_published = 1
         LIMIT 1"
    );
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->prepare(
        "SELECT f.*, c.name AS category_name
         FROM cms_faqs f
         LEFT JOIN cms_faq_categories c ON c.id = f.category_id
         WHERE f.id = ? AND COALESCE(f.status,'published') = 'published' AND f.is_published = 1
         LIMIT 1"
    );
    $stmt->execute([$id]);
}

$faq = $stmt->fetch() ?: null;
if (!$faq) {
    http_response_code(404);
    $siteName = getSetting('site_name', 'Kora CMS');
    $missingPath = $slug !== ''
        ? BASE_URL . '/faq/' . rawurlencode($slug)
        : BASE_URL . '/faq/item.php' . ($id !== null ? '?id=' . urlencode((string)$id) : '');

    renderPublicPage([
        'title' => 'FAQ nenalezeno - ' . $siteName,
        'meta' => [
            'title' => 'FAQ nenalezeno - ' . $siteName,
            'url' => $missingPath,
        ],
        'view' => 'not-found',
        'body_class' => 'page-faq-not-found',
    ]);
    exit;
}

$faq = hydrateFaqPresentation($faq);

if ($slug === '' && !empty($faq['slug'])) {
    header('Location: ' . faqPublicPath($faq));
    exit;
}

if (!isset($_SESSION['cms_user_id'])) {
    trackPageView('faq', (int)$faq['id']);
}

$siteName = getSetting('site_name', 'Kora CMS');
$metaDescription = faqExcerpt($faq, 180);
if ($metaDescription === '') {
    $metaDescription = 'Odpověď na otázku ' . (string)$faq['question'];
}

renderPublicPage([
    'title' => (string)$faq['question'] . ' – ' . $siteName,
    'meta' => [
        'title' => (string)$faq['question'] . ' – ' . $siteName,
        'description' => $metaDescription,
        'url' => faqPublicUrl($faq),
        'type' => 'article',
    ],
    'view' => 'modules/faq-article',
    'view_data' => [
        'faq' => $faq,
    ],
    'current_nav' => 'faq',
    'body_class' => 'page-faq-article',
    'page_kind' => 'detail',
    'admin_edit_url' => BASE_URL . '/admin/faq_form.php?id=' . (int)$faq['id'],
]);
