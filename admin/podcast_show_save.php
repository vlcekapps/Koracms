<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo        = db_connect();
$id         = inputInt('post', 'id');
$title      = trim($_POST['title']       ?? '');
$slug       = trim($_POST['slug']        ?? '');
$author     = trim($_POST['author']      ?? '');
$language   = trim($_POST['language']   ?? 'cs');
$category   = trim($_POST['category']   ?? '');
$websiteUrl = trim($_POST['website_url'] ?? '');
$description = trim($_POST['description'] ?? '');

// Slug sanitize – jen malá písmena, číslice, pomlčky
$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));

if ($title === '' || $slug === '') {
    header('Location: podcast_show_form.php' . ($id ? "?id={$id}" : ''));
    exit;
}

// Cover image upload
$coverImage = null;
if (!empty($_FILES['cover_image']['name'])) {
    $tmp   = $_FILES['cover_image']['tmp_name'];
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmp);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (isset($allowed[$mime])) {
        $dir = __DIR__ . '/../uploads/podcasts/covers/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = uniqid('cover_', true) . '.' . $allowed[$mime];
        if (move_uploaded_file($tmp, $dir . $filename)) {
            // Smazat starý obrázek
            if ($id !== null) {
                $old = $pdo->prepare("SELECT cover_image FROM cms_podcast_shows WHERE id = ?");
                $old->execute([$id]);
                $oldFile = $old->fetchColumn();
                if ($oldFile) @unlink($dir . $oldFile);
            }
            $coverImage = $filename;
        }
    }
}

if ($id !== null) {
    $set    = "title=?,slug=?,author=?,language=?,category=?,website_url=?,description=?,updated_at=NOW()";
    $params = [$title, $slug, $author, $language, $category, $websiteUrl, $description];
    if ($coverImage !== null) { $set .= ",cover_image=?"; $params[] = $coverImage; }
    $params[] = $id;
    $pdo->prepare("UPDATE cms_podcast_shows SET {$set} WHERE id=?")->execute($params);
    logAction('podcast_show_edit', "id={$id} slug={$slug}");
} else {
    $pdo->prepare(
        "INSERT INTO cms_podcast_shows (title, slug, author, language, category, website_url, description, cover_image)
         VALUES (?,?,?,?,?,?,?,?)"
    )->execute([$title, $slug, $author, $language, $category, $websiteUrl, $description, $coverImage ?? '']);
    logAction('podcast_show_add', "title={$title} slug={$slug}");
}

header('Location: ' . BASE_URL . '/admin/podcast_shows.php');
exit;
