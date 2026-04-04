<?php
/**
 * Koš – přehled smazaného obsahu s možností obnovení nebo trvalého smazání.
 */
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');

$pdo = db_connect();
$success = '';

// Akce: obnovit nebo trvale smazat
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim($_POST['action'] ?? '');
    $module = trim($_POST['module'] ?? '');
    $itemId = inputInt('post', 'id');

    $moduleConfig = [
        'articles'       => ['table' => 'cms_articles',       'label' => 'Článek'],
        'news'           => ['table' => 'cms_news',           'label' => 'Novinka'],
        'pages'          => ['table' => 'cms_pages',          'label' => 'Stránka'],
        'events'         => ['table' => 'cms_events',         'label' => 'Událost'],
        'faq'            => ['table' => 'cms_faqs',           'label' => 'Znalostní báze'],
        'board'          => ['table' => 'cms_board',          'label' => 'Úřední deska'],
        'downloads'      => ['table' => 'cms_downloads',      'label' => 'Soubor ke stažení'],
        'food_cards'     => ['table' => 'cms_food_cards',     'label' => 'Jídelní lístek'],
        'podcasts'       => ['table' => 'cms_podcasts',       'label' => 'Podcast – epizoda'],
        'podcast_shows'  => ['table' => 'cms_podcast_shows',  'label' => 'Podcast – pořad'],
        'gallery_albums' => ['table' => 'cms_gallery_albums', 'label' => 'Galerie – album'],
        'gallery_photos' => ['table' => 'cms_gallery_photos', 'label' => 'Galerie – fotografie'],
        'polls'          => ['table' => 'cms_polls',          'label' => 'Anketa'],
    ];

    if ($itemId !== null && isset($moduleConfig[$module])) {
        $cfg = $moduleConfig[$module];
        if ($action === 'restore') {
            $pdo->prepare("UPDATE {$cfg['table']} SET deleted_at = NULL WHERE id = ?")->execute([$itemId]);
            $success = $cfg['label'] . ' obnoven(a).';
            logAction('trash_restore', "module={$module} id={$itemId}");
        } elseif ($action === 'purge') {
            if ($module === 'articles') {
                $pdo->prepare("DELETE FROM cms_article_tags WHERE article_id = ?")->execute([$itemId]);
                $pdo->prepare("DELETE FROM cms_comments WHERE article_id = ?")->execute([$itemId]);
                $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'article' AND entity_id = ?")->execute([$itemId]);
            } elseif ($module === 'polls') {
                $pdo->prepare("DELETE FROM cms_poll_votes WHERE poll_id = ?")->execute([$itemId]);
                $pdo->prepare("DELETE FROM cms_poll_options WHERE poll_id = ?")->execute([$itemId]);
                $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'poll' AND entity_id = ?")->execute([$itemId]);
            } elseif ($module === 'downloads') {
                $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'download' AND entity_id = ?")->execute([$itemId]);
            } elseif ($module === 'podcasts') {
                $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'podcast_episode' AND entity_id = ?")->execute([$itemId]);
            } elseif ($module === 'podcast_shows') {
                $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'podcast_show' AND entity_id = ?")->execute([$itemId]);
            } elseif ($module === 'gallery_albums') {
                $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'gallery_album' AND entity_id = ?")->execute([$itemId]);
            } elseif ($module === 'gallery_photos') {
                $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'gallery_photo' AND entity_id = ?")->execute([$itemId]);
            }
            $pdo->prepare("DELETE FROM {$cfg['table']} WHERE id = ? AND deleted_at IS NOT NULL")->execute([$itemId]);
            $success = $cfg['label'] . ' trvale smazán(a).';
            logAction('trash_purge', "module={$module} id={$itemId}");
        }
    }
}

// Načtení smazaných položek
$trashItems = [];

$modules = [
    'articles'       => ['table' => 'cms_articles',       'label' => 'Článek',              'title_col' => 'title',    'edit_url' => 'blog_form.php?id='],
    'news'           => ['table' => 'cms_news',           'label' => 'Novinka',              'title_col' => 'title',    'edit_url' => 'news_form.php?id='],
    'pages'          => ['table' => 'cms_pages',          'label' => 'Stránka',              'title_col' => 'title',    'edit_url' => 'page_form.php?id='],
    'events'         => ['table' => 'cms_events',         'label' => 'Událost',              'title_col' => 'title',    'edit_url' => 'event_form.php?id='],
    'faq'            => ['table' => 'cms_faqs',           'label' => 'Znalostní báze',       'title_col' => 'question', 'edit_url' => 'faq_form.php?id='],
    'board'          => ['table' => 'cms_board',          'label' => 'Úřední deska',         'title_col' => 'title',    'edit_url' => 'board_form.php?id='],
    'downloads'      => ['table' => 'cms_downloads',      'label' => 'Soubor ke stažení',    'title_col' => 'title',    'edit_url' => 'download_form.php?id='],
    'food_cards'     => ['table' => 'cms_food_cards',     'label' => 'Jídelní lístek',       'title_col' => 'title',    'edit_url' => 'food_form.php?id='],
    'podcasts'       => ['table' => 'cms_podcasts',       'label' => 'Podcast – epizoda',    'title_col' => 'title',    'edit_url' => 'podcast_form.php?id='],
    'podcast_shows'  => ['table' => 'cms_podcast_shows',  'label' => 'Podcast – pořad',      'title_col' => 'title',    'edit_url' => 'podcast_show_form.php?id='],
    'gallery_albums' => ['table' => 'cms_gallery_albums', 'label' => 'Galerie – album',      'title_col' => 'name',     'edit_url' => 'gallery_album_form.php?id='],
    'gallery_photos' => ['table' => 'cms_gallery_photos', 'label' => 'Galerie – fotografie', 'title_col' => 'title',    'edit_url' => 'gallery_photo_form.php?id='],
    'polls'          => ['table' => 'cms_polls',          'label' => 'Anketa',               'title_col' => 'question', 'edit_url' => 'polls_form.php?id='],
];

foreach ($modules as $moduleKey => $cfg) {
    try {
        $stmt = $pdo->query(
            "SELECT id, {$cfg['title_col']} AS title, deleted_at FROM {$cfg['table']} WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC"
        );
        foreach ($stmt->fetchAll() as $row) {
            $trashItems[] = [
                'module'     => $moduleKey,
                'label'      => $cfg['label'],
                'id'         => (int)$row['id'],
                'title'      => (string)$row['title'],
                'deleted_at' => (string)$row['deleted_at'],
                'edit_url'   => $cfg['edit_url'] . (int)$row['id'],
            ];
        }
    } catch (\PDOException $e) {
        // Sloupec deleted_at ještě neexistuje
    }
}

usort($trashItems, fn($a, $b) => $b['deleted_at'] <=> $a['deleted_at']);

adminHeader('Koš');
?>
<?php if ($success !== ''): ?><p class="success" role="status"><?= h($success) ?></p><?php endif; ?>

<p style="font-size:.9rem">Smazané položky lze obnovit nebo trvale odstranit. Položky v koši se nezobrazují na veřejném webu ani v admin přehledech.</p>

<?php if (empty($trashItems)): ?>
  <p>Koš je prázdný.</p>
<?php else: ?>
  <table>
    <caption>Smazané položky</caption>
    <thead>
      <tr>
        <th scope="col">Typ</th>
        <th scope="col">Název</th>
        <th scope="col">Smazáno</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($trashItems as $item): ?>
      <tr>
        <td><?= h($item['label']) ?></td>
        <td><?= h($item['title']) ?></td>
        <td><time datetime="<?= h(str_replace(' ', 'T', $item['deleted_at'])) ?>"><?= h($item['deleted_at']) ?></time></td>
        <td class="actions">
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="module" value="<?= h($item['module']) ?>">
            <input type="hidden" name="id" value="<?= $item['id'] ?>">
            <input type="hidden" name="action" value="restore">
            <button type="submit" class="btn">Obnovit</button>
          </form>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="module" value="<?= h($item['module']) ?>">
            <input type="hidden" name="id" value="<?= $item['id'] ?>">
            <input type="hidden" name="action" value="purge">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Trvale smazat? Tuto akci nelze vrátit zpět.">Trvale smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
