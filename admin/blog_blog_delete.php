<?php
require_once __DIR__ . '/../db.php';
requireCapability('blog_taxonomies_manage', 'Přístup odepřen.');
verifyCsrf();

$id = inputInt('post', 'id');
if ($id !== null) {
    $pdo = db_connect();

    // Najít zbývající blog pro přesun obsahu
    $fallback = $pdo->prepare("SELECT id FROM cms_blogs WHERE id != ? ORDER BY sort_order, id LIMIT 1");
    $fallback->execute([$id]);
    $fallbackId = $fallback->fetchColumn();

    if ($fallbackId) {
        // Přesunout články, kategorie a tagy do zbývajícího blogu
        $pdo->prepare("UPDATE cms_articles SET blog_id = ? WHERE blog_id = ?")->execute([(int)$fallbackId, $id]);
        $pdo->prepare("UPDATE cms_categories SET blog_id = ? WHERE blog_id = ?")->execute([(int)$fallbackId, $id]);
        $pdo->prepare("UPDATE cms_tags SET blog_id = ? WHERE blog_id = ?")->execute([(int)$fallbackId, $id]);
        logAction('blog_delete', "id={$id} content_moved_to={$fallbackId}");
    } else {
        // Poslední blog – smazat veškerý obsah nenávratně
        $articleIds = $pdo->prepare("SELECT id FROM cms_articles WHERE blog_id = ?");
        $articleIds->execute([$id]);
        foreach ($articleIds->fetchAll(PDO::FETCH_COLUMN) as $artId) {
            $pdo->prepare("DELETE FROM cms_article_tags WHERE article_id = ?")->execute([$artId]);
            $pdo->prepare("DELETE FROM cms_comments WHERE article_id = ?")->execute([$artId]);
            $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'article' AND entity_id = ?")->execute([$artId]);
        }
        $pdo->prepare("DELETE FROM cms_articles WHERE blog_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM cms_categories WHERE blog_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM cms_tags WHERE blog_id = ?")->execute([$id]);
        logAction('blog_delete', "id={$id} all_content_deleted");
    }

    $pdo->prepare("DELETE FROM cms_blogs WHERE id = ?")->execute([$id]);
}

header('Location: ' . BASE_URL . '/admin/blogs.php');
exit;
