<?php
/**
 * Koš – přehled smazaného obsahu s možností obnovení nebo trvalého smazání.
 */
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');

$pdo = db_connect();
$success = match ((string)($_GET['ok'] ?? '')) {
    'restored' => 'Položka byla obnovena.',
    'purged' => 'Položka byla trvale smazána.',
    default => '',
};
$purgeConfirmError = trim((string)($_GET['err'] ?? '')) === 'confirm_purge';
$purgeErrorModule = trim((string)($_GET['purge_module'] ?? ''));
$purgeErrorItemId = inputInt('get', 'purge_id');
$error = match ((string)($_GET['err'] ?? '')) {
    'confirm_purge' => 'Před trvalým smazáním potvrďte, že položku už nebude možné obnovit.',
    'invalid_action' => 'Akci se nepodařilo provést. Vyberte položku z koše znovu.',
    default => '',
};

// Akce: obnovit nebo trvale smazat
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim($_POST['action'] ?? '');
    $module = trim($_POST['module'] ?? '');
    $itemId = inputInt('post', 'id');
    $redirectQuery = 'err=invalid_action';

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
        'places'        => ['table' => 'cms_places',         'label' => 'Místo'],
    ];

    if ($itemId !== null && isset($moduleConfig[$module])) {
        $cfg = $moduleConfig[$module];
        if ($action === 'restore') {
            $previousBoardDocument = null;
            if ($module === 'board') {
                $previousStmt = $pdo->prepare("SELECT * FROM cms_board WHERE id = ?");
                $previousStmt->execute([$itemId]);
                $previousBoardDocument = $previousStmt->fetch() ?: null;
            }
            $restoreStmt = $pdo->prepare("UPDATE {$cfg['table']} SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL");
            $restoreStmt->execute([$itemId]);
            if ($restoreStmt->rowCount() !== 1) {
                $redirectQuery = 'err=invalid_action';
            } elseif ($module === 'board' && is_array($previousBoardDocument)) {
                $updatedStmt = $pdo->prepare("SELECT * FROM cms_board WHERE id = ?");
                $updatedStmt->execute([$itemId]);
                $updatedBoardDocument = $updatedStmt->fetch() ?: null;
                if ($updatedBoardDocument) {
                    recordBoardPublicationEvent($pdo, $updatedBoardDocument, 'restored', currentUserId());
                    if (shouldSendBoardPublicationNotice($previousBoardDocument, $updatedBoardDocument)) {
                        $sentNotifications = notifyBoardSubscribers($pdo, $updatedBoardDocument);
                        if ($sentNotifications > 0) {
                            logAction('board_notify', "id={$itemId} sent={$sentNotifications}");
                        }
                    }
                }
            }
            if ($restoreStmt->rowCount() === 1) {
                logAction('trash_restore', "module={$module} id={$itemId}");
                $redirectQuery = 'ok=restored';
            }
        } elseif ($action === 'purge') {
            $confirmedPermanentDelete = isset($_POST['confirm_permanent_delete'])
                && (string)$_POST['confirm_permanent_delete'] === '1';
            if (!$confirmedPermanentDelete) {
                $redirectQuery = http_build_query([
                    'err' => 'confirm_purge',
                    'purge_module' => $module,
                    'purge_id' => $itemId,
                ]);
            } else {
                if ($module === 'places') {
                    $placeImageFile = '';
                    try {
                        $pdo->beginTransaction();
                        $placeStmt = $pdo->prepare(
                            "SELECT id, slug, image_file
                             FROM cms_places
                             WHERE id = ? AND deleted_at IS NOT NULL
                             FOR UPDATE"
                        );
                        $placeStmt->execute([$itemId]);
                        $place = $placeStmt->fetch() ?: null;
                        if (!$place) {
                            $pdo->rollBack();
                            $redirectQuery = 'err=invalid_action';
                        } else {
                            $placeImageFile = trim((string)($place['image_file'] ?? ''));
                            deleteRedirectsTargetingPath($pdo, placePublicPath($place));
                            $pdo->prepare("UPDATE cms_events SET place_id = NULL WHERE place_id = ?")->execute([$itemId]);
                            $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'place' AND entity_id = ?")->execute([$itemId]);
                            $deletePlaceStmt = $pdo->prepare("DELETE FROM cms_places WHERE id = ? AND deleted_at IS NOT NULL");
                            $deletePlaceStmt->execute([$itemId]);
                            if ($deletePlaceStmt->rowCount() !== 1) {
                                throw new RuntimeException('Místo se nepodařilo trvale smazat.');
                            }
                            logAction('trash_purge', "module={$module} id={$itemId}");
                            $pdo->commit();
                            if ($placeImageFile !== '') {
                                deletePlaceImageFile($placeImageFile);
                            }
                            $redirectQuery = 'ok=purged';
                        }
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        koraLog('warning', 'place trash purge failed', [
                            'operation' => 'place_trash_purge',
                            'place_id' => $itemId,
                            'exception' => $e,
                        ]);
                        $redirectQuery = 'err=invalid_action';
                    }
                } elseif ($module === 'articles') {
                    $articleImageFile = '';
                    try {
                        $pdo->beginTransaction();
                        $articleStmt = $pdo->prepare(
                            "SELECT a.id, a.slug, a.blog_id, a.image_file, b.slug AS blog_slug
                             FROM cms_articles a
                             INNER JOIN cms_blogs b ON b.id = a.blog_id
                             WHERE a.id = ? AND a.deleted_at IS NOT NULL
                             FOR UPDATE"
                        );
                        $articleStmt->execute([$itemId]);
                        $articleForPurge = $articleStmt->fetch() ?: null;
                        if (!$articleForPurge) {
                            $pdo->rollBack();
                            $redirectQuery = 'err=invalid_action';
                        } else {
                            $articleImageFile = trim((string)($articleForPurge['image_file'] ?? ''));
                            deleteRedirectsTargetingPath($pdo, articlePublicPath($articleForPurge));
                            $pdo->prepare("DELETE FROM cms_article_tags WHERE article_id = ?")->execute([$itemId]);
                            $pdo->prepare("DELETE FROM cms_article_related WHERE article_id = ? OR related_article_id = ?")->execute([$itemId, $itemId]);
                            $pdo->prepare("DELETE FROM cms_blog_series_items WHERE article_id = ?")->execute([$itemId]);
                            $pdo->prepare("DELETE FROM cms_comments WHERE article_id = ?")->execute([$itemId]);
                            $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'article' AND entity_id = ?")->execute([$itemId]);
                            $deleteArticleStmt = $pdo->prepare("DELETE FROM cms_articles WHERE id = ? AND deleted_at IS NOT NULL");
                            $deleteArticleStmt->execute([$itemId]);
                            if ($deleteArticleStmt->rowCount() !== 1) {
                                throw new RuntimeException('Článek se nepodařilo trvale smazat.');
                            }
                            logAction('trash_purge', "module={$module} id={$itemId}");
                            $pdo->commit();
                            if ($articleImageFile !== '') {
                                deleteArticleImageFile($articleImageFile);
                            }
                            $redirectQuery = 'ok=purged';
                        }
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        koraLog('warning', 'article trash purge failed', [
                            'operation' => 'article_trash_purge',
                            'article_id' => $itemId,
                            'exception' => $e,
                        ]);
                        $redirectQuery = 'err=invalid_action';
                    }
                } elseif ($module === 'polls') {
                    $pdo->prepare("DELETE FROM cms_poll_votes WHERE poll_id = ?")->execute([$itemId]);
                    $pdo->prepare("DELETE FROM cms_poll_options WHERE poll_id = ?")->execute([$itemId]);
                    $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'poll' AND entity_id = ?")->execute([$itemId]);
                } elseif ($module === 'downloads') {
                    $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'download' AND entity_id = ?")->execute([$itemId]);
                } elseif ($module === 'food_cards') {
                    $orderIds = $pdo->prepare("SELECT id FROM cms_food_orders WHERE card_id = ?");
                    $orderIds->execute([$itemId]);
                    $foodOrderIds = array_map('intval', array_column($orderIds->fetchAll(), 'id'));
                    if ($foodOrderIds !== []) {
                        $foodOrderPlaceholders = implode(',', array_fill(0, count($foodOrderIds), '?'));
                        $pdo->prepare("DELETE FROM cms_food_order_items WHERE order_id IN ({$foodOrderPlaceholders})")->execute($foodOrderIds);
                    }
                    $pdo->prepare("DELETE FROM cms_food_orders WHERE card_id = ?")->execute([$itemId]);
                    $pdo->prepare("DELETE FROM cms_food_items WHERE card_id = ?")->execute([$itemId]);
                    $pdo->prepare("DELETE FROM cms_food_sections WHERE card_id = ?")->execute([$itemId]);
                    $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'food' AND entity_id = ?")->execute([$itemId]);
                } elseif ($module === 'podcasts') {
                    $pdo->prepare("DELETE FROM cms_podcast_chapters WHERE episode_id = ?")->execute([$itemId]);
                    $pdo->prepare("DELETE FROM cms_podcast_people WHERE episode_id = ?")->execute([$itemId]);
                    $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'podcast_episode' AND entity_id = ?")->execute([$itemId]);
                } elseif ($module === 'podcast_shows') {
                    $episodeIdsStmt = $pdo->prepare("SELECT id FROM cms_podcasts WHERE show_id = ?");
                    $episodeIdsStmt->execute([$itemId]);
                    $podcastEpisodeIds = array_map('intval', array_column($episodeIdsStmt->fetchAll(), 'id'));
                    if ($podcastEpisodeIds !== []) {
                        $chapterPlaceholders = implode(',', array_fill(0, count($podcastEpisodeIds), '?'));
                        $pdo->prepare("DELETE FROM cms_podcast_chapters WHERE episode_id IN ({$chapterPlaceholders})")->execute($podcastEpisodeIds);
                        foreach ($podcastEpisodeIds as $podcastEpisodeId) {
                            $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'podcast_episode' AND entity_id = ?")->execute([$podcastEpisodeId]);
                        }
                    }
                    $pdo->prepare("DELETE FROM cms_podcast_people WHERE show_id = ?")->execute([$itemId]);
                    $pdo->prepare("DELETE FROM cms_podcast_platform_links WHERE show_id = ?")->execute([$itemId]);
                    $pdo->prepare("DELETE FROM cms_podcasts WHERE show_id = ?")->execute([$itemId]);
                    $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'podcast_show' AND entity_id = ?")->execute([$itemId]);
                } elseif ($module === 'gallery_albums') {
                    $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'gallery_album' AND entity_id = ?")->execute([$itemId]);
                } elseif ($module === 'gallery_photos') {
                    $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'gallery_photo' AND entity_id = ?")->execute([$itemId]);
                } elseif ($module === 'board') {
                    $boardRedirectStmt = $pdo->prepare(
                        "SELECT id, slug
                         FROM cms_board
                         WHERE id = ?
                         LIMIT 1"
                    );
                    $boardRedirectStmt->execute([$itemId]);
                    $boardForRedirectCleanup = $boardRedirectStmt->fetch() ?: null;
                    if ($boardForRedirectCleanup) {
                        deleteRedirectsTargetingPath($pdo, boardPublicPath($boardForRedirectCleanup));
                    }
                    $pdo->prepare("DELETE FROM cms_board_publication_events WHERE board_id = ?")->execute([$itemId]);
                    $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'board' AND entity_id = ?")->execute([$itemId]);
                }
                if (!in_array($module, ['places', 'articles'], true)) {
                    $pdo->prepare("DELETE FROM {$cfg['table']} WHERE id = ? AND deleted_at IS NOT NULL")->execute([$itemId]);
                    logAction('trash_purge', "module={$module} id={$itemId}");
                    $redirectQuery = 'ok=purged';
                }
            }
        }
    }

    header('Location: ' . BASE_URL . '/admin/trash.php?' . $redirectQuery);
    exit;
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
    'places'        => ['table' => 'cms_places',         'label' => 'Místo',                'title_col' => 'name',     'edit_url' => 'place_form.php?id='],
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

usort($trashItems, fn ($a, $b) => $b['deleted_at'] <=> $a['deleted_at']);

adminHeader('Koš');
?>
<?php if ($success !== ''): ?><p class="success" role="status"><?= h($success) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p id="trash-purge-error" class="error" role="alert" aria-atomic="true"><?= h($error) ?></p><?php endif; ?>

<p class="admin-description">Smazané položky lze obnovit nebo trvale odstranit. Položky v koši se nezobrazují na veřejném webu ani v admin přehledech. Trvalé smazání je nevratné a před odesláním vyžaduje samostatné potvrzení u konkrétní položky.</p>

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
      <?php
        $trashItemDomId = 'trash-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string)$item['module'] . '-' . (string)$item['id']);
        $trashItemTitle = trim((string)$item['title']) !== '' ? (string)$item['title'] : ('ID ' . (string)$item['id']);
        $trashItemContext = (string)$item['label'] . ' „' . $trashItemTitle . '“';
        $purgeReviewId = $trashItemDomId . '-purge-review';
        $purgeConfirmField = 'confirm_permanent_delete';
        $purgeConfirmId = $trashItemDomId . '-purge-confirm';
        $purgeConfirmErrorId = $trashItemDomId . '-purge-confirm-error';
        $purgeHasError = $purgeConfirmError
            && $purgeErrorModule === (string)$item['module']
            && $purgeErrorItemId === (int)$item['id'];
        $purgeErrorFields = $purgeHasError ? [$purgeConfirmField] : [];
        ?>
      <tr>
        <td><?= h($item['label']) ?></td>
        <td><?= h($item['title']) ?></td>
        <td><time datetime="<?= h(str_replace(' ', 'T', $item['deleted_at'])) ?>"><?= h($item['deleted_at']) ?></time></td>
        <td class="actions admin-trash-actions">
          <form method="post" class="admin-trash-action-form admin-trash-action-form--restore">
            <fieldset class="admin-filter-fieldset">
              <legend class="sr-only">Obnovit položku <?= h($trashItemContext) ?></legend>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="<?= h($item['module']) ?>">
              <input type="hidden" name="id" value="<?= $item['id'] ?>">
              <input type="hidden" name="action" value="restore">
              <button type="submit" class="btn">Obnovit<span class="sr-only"> položku <?= h($trashItemContext) ?></span></button>
            </fieldset>
          </form>
          <form method="post" class="admin-trash-action-form admin-trash-action-form--purge"
                novalidate<?= $purgeHasError ? ' aria-describedby="trash-purge-error"' : '' ?>
                data-confirm="<?= h('Trvale smazat položku ' . $trashItemContext . '? Tuto akci nelze vrátit zpět.') ?>">
            <fieldset class="admin-filter-fieldset">
              <legend class="sr-only">Trvale smazat položku <?= h($trashItemContext) ?></legend>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="<?= h($item['module']) ?>">
              <input type="hidden" name="id" value="<?= $item['id'] ?>">
              <input type="hidden" name="action" value="purge">
              <p id="<?= h($purgeReviewId) ?>" class="admin-description admin-description--muted admin-copy--compact">Zkontrolujte typ, název a datum smazání v tomto řádku. Trvalé smazání nejde vrátit zpět.</p>
              <label for="<?= h($purgeConfirmId) ?>" class="admin-checkbox-label">
                <input type="checkbox" id="<?= h($purgeConfirmId) ?>" name="<?= h($purgeConfirmField) ?>" value="1" required aria-required="true"<?= adminFieldAttributes($purgeConfirmField, $purgeErrorFields, [], [$purgeReviewId], $purgeConfirmErrorId) ?>>
                Rozumím, že položku nepůjde obnovit.
              </label>
              <?php adminRenderFieldError($purgeConfirmField, $purgeErrorFields, [], 'Před trvalým smazáním potvrďte, že jste zkontrolovali typ, název a datum smazání této položky.', $purgeConfirmErrorId); ?>
              <button type="submit" class="btn btn-danger">Trvale smazat<span class="sr-only"> položku <?= h($trashItemContext) ?></span></button>
            </fieldset>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
