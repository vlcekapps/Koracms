<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo            = db_connect();
$id             = inputInt('post', 'id');
$title          = trim($_POST['title']            ?? '');
$perex          = trim($_POST['perex']            ?? '');
$content        = trim($_POST['content']          ?? '');
$catId          = inputInt('post', 'category_id');
$tagIds         = array_map('intval', (array)($_POST['tags'] ?? []));
$publishAt      = trim($_POST['publish_at']       ?? '');
$metaTitle      = trim($_POST['meta_title']       ?? '');
$metaDescription= trim($_POST['meta_description'] ?? '');
$commentsEnabled = isset($_POST['comments_enabled']) ? 1 : 0;

if ($title === '' || $content === '') {
    header('Location: blog_form.php' . ($id ? "?id={$id}" : ''));
    exit;
}

// Plánované publikování – validace a formátování
$publishAtSql = null;
if ($publishAt !== '') {
    $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $publishAt);
    if ($dt) $publishAtSql = $dt->format('Y-m-d H:i:s');
}

// ── Obrázek ──────────────────────────────────────────────────────────────
$imageFile = null; // null = nezměněno

if (!empty($_FILES['image']['name'])) {
    $tmp  = $_FILES['image']['tmp_name'];
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmp);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    if (isset($allowed[$mime])) {
        $ext      = $allowed[$mime];
        $filename = uniqid('img_', true) . '.' . $ext;
        $dir      = __DIR__ . '/../uploads/articles/';
        $thumbDir = $dir . 'thumbs/';

        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

        if (move_uploaded_file($tmp, $dir . $filename)) {
            gallery_make_thumb($dir . $filename, $thumbDir . $filename, 400);
            $imageFile = $filename;

            // Smazat starý obrázek pokud existuje
            if ($id !== null) {
                $old = $pdo->prepare("SELECT image_file FROM cms_articles WHERE id = ?");
                $old->execute([$id]);
                $oldFile = $old->fetchColumn();
                if ($oldFile) {
                    @unlink($dir . $oldFile);
                    @unlink($thumbDir . $oldFile);
                }
            }
        }
    }
} elseif (isset($_POST['image_delete']) && $_POST['image_delete'] === '1' && $id !== null) {
    // Smazat obrázek bez náhrady
    $old = $pdo->prepare("SELECT image_file FROM cms_articles WHERE id = ?");
    $old->execute([$id]);
    $oldFile = $old->fetchColumn();
    if ($oldFile) {
        $dir = __DIR__ . '/../uploads/articles/';
        @unlink($dir . $oldFile);
        @unlink($dir . 'thumbs/' . $oldFile);
    }
    $imageFile = '';
}

// ── Uložení článku ───────────────────────────────────────────────────────
if ($id !== null) {
    // Zajisti preview_token (pro starší záznamy bez tokenu)
    $existing = $pdo->prepare("SELECT preview_token FROM cms_articles WHERE id = ?");
    $existing->execute([$id]);
    $existingToken = (string)($existing->fetchColumn() ?? '');
    if ($existingToken === '') {
        $existingToken = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE cms_articles SET preview_token=? WHERE id=?")->execute([$existingToken, $id]);
    }

    $setClauses = "title=?, perex=?, content=?, category_id=?, comments_enabled=?, publish_at=?,
                   meta_title=?, meta_description=?, author_id=COALESCE(author_id,?), updated_at=NOW()";
    $params     = [$title, $perex, $content, $catId, $commentsEnabled, $publishAtSql, $metaTitle, $metaDescription, currentUserId()];
    if ($imageFile !== null) {
        $setClauses .= ", image_file=?";
        $params[]    = $imageFile;
    }
    $params[] = $id;
    $pdo->prepare("UPDATE cms_articles SET {$setClauses} WHERE id=?")->execute($params);
    logAction('article_edit', "id={$id} title={$title}");
} else {
    $previewToken = bin2hex(random_bytes(16));
    $authorId     = currentUserId();
    $status       = isSuperAdmin() ? 'published' : 'pending';
    $pdo->prepare(
        "INSERT INTO cms_articles
         (title, perex, content, category_id, comments_enabled, image_file, publish_at,
          meta_title, meta_description, preview_token, author_id, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([$title, $perex, $content, $catId, $commentsEnabled, $imageFile ?? '', $publishAtSql,
                $metaTitle, $metaDescription, $previewToken, $authorId, $status]);
    $id = (int)$pdo->lastInsertId();
    logAction('article_add', "id={$id} title={$title} status={$status}");
}

// ── Tagy ─────────────────────────────────────────────────────────────────
try {
    $pdo->prepare("DELETE FROM cms_article_tags WHERE article_id = ?")->execute([$id]);
    if (!empty($tagIds)) {
        $ins = $pdo->prepare("INSERT IGNORE INTO cms_article_tags (article_id, tag_id) VALUES (?,?)");
        foreach ($tagIds as $tid) {
            $ins->execute([$id, $tid]);
        }
    }
} catch (\PDOException $e) {}

header('Location: ' . BASE_URL . '/admin/blog.php');
exit;
