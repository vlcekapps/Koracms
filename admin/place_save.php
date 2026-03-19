<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo         = db_connect();
$id          = inputInt('post', 'id');
$name        = trim($_POST['name']        ?? '');
$category    = trim($_POST['category']    ?? '');
$url         = trim($_POST['url']         ?? '');
$description = trim($_POST['description'] ?? '');
$sortOrder   = max(0, (int)($_POST['sort_order'] ?? 0));
$isPublished = isset($_POST['is_published']) ? 1 : 0;

if ($name === '') {
    header('Location: place_form.php' . ($id ? "?id={$id}" : ''));
    exit;
}

if ($id !== null) {
    $pdo->prepare(
        "UPDATE cms_places SET name=?,category=?,url=?,description=?,sort_order=?,is_published=?
         WHERE id=?"
    )->execute([$name, $category, $url, $description, $sortOrder, $isPublished, $id]);
    logAction('place_edit', "id={$id}");
} else {
    $status      = isSuperAdmin() ? 'published' : 'pending';
    $isPublished = isSuperAdmin() ? $isPublished : 0;
    $pdo->prepare(
        "INSERT INTO cms_places (name,category,url,description,sort_order,is_published,status)
         VALUES (?,?,?,?,?,?,?)"
    )->execute([$name, $category, $url, $description, $sortOrder, $isPublished, $status]);
    logAction('place_add', "name={$name} status={$status}");
}

header('Location: ' . BASE_URL . '/admin/places.php');
exit;
