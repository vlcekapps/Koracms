<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$id          = inputInt('post', 'id');
$title       = trim($_POST['title']       ?? '');
$slug        = trim($_POST['slug']        ?? '');
$content     = $_POST['content']          ?? '';
$isPublished = isset($_POST['is_published']) ? 1 : 0;
$showInNav   = isset($_POST['show_in_nav'])  ? 1 : 0;
$navOrder    = max(1, (int)($_POST['nav_order'] ?? 1));

if ($title === '' || $slug === '') {
    header('Location: ' . BASE_URL . '/admin/page_form.php' . ($id ? '?id=' . $id : ''));
    exit;
}

// Sanitize slug
$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
$slug = trim($slug, '-');
if ($slug === '') $slug = slugify($title);

$pdo = db_connect();

if ($id !== null) {
    $pdo->prepare(
        "UPDATE cms_pages
         SET title=?, slug=?, content=?, is_published=?, show_in_nav=?, nav_order=?
         WHERE id=?"
    )->execute([$title, $slug, $content, $isPublished, $showInNav, $navOrder, $id]);
} else {
    $status      = isSuperAdmin() ? 'published' : 'pending';
    $isPublished = isSuperAdmin() ? $isPublished : 0;
    $pdo->prepare(
        "INSERT INTO cms_pages (title, slug, content, is_published, show_in_nav, nav_order, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    )->execute([$title, $slug, $content, $isPublished, $showInNav, $navOrder, $status]);
}

header('Location: ' . BASE_URL . '/admin/pages.php');
exit;
