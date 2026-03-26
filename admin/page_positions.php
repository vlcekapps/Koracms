<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu statických stránek nemáte potřebné oprávnění.');

$pdo = db_connect();
normalizePageNavigationOrder($pdo);

$pages = $pdo->query(
    "SELECT id, title, slug, show_in_nav, is_published, nav_order, COALESCE(status, 'published') AS status
     FROM cms_pages
     ORDER BY nav_order, title, id"
)->fetchAll();

adminHeader('Pozice statických stránek');
?>

<?php if (isset($_GET['nav_saved'])): ?>
  <p class="success" role="status">Pořadí statických stránek bylo uloženo.</p>
<?php endif; ?>

<p class="button-row button-row--start">
  <a href="<?= BASE_URL ?>/admin/pages.php"><span aria-hidden="true">←</span> Zpět na statické stránky</a>
  <a href="<?= BASE_URL ?>/admin/page_form.php" class="btn">+ Nová stránka</a>
</p>

<p>Tlačítky měňte pořadí, v jakém se statické stránky zobrazují návštěvníkům v hlavní navigaci webu. Stránky skryté z navigace nebo nezveřejněné tu zůstávají také, aby měly připravené stabilní pořadí pro případné pozdější zobrazení.</p>

<?php if ($pages === []): ?>
  <p>Zatím tu nejsou žádné statické stránky. <a href="<?= BASE_URL ?>/admin/page_form.php">Přidat první stránku</a>.</p>
<?php else: ?>
  <ol style="list-style:none;padding:0;margin:0;max-width:62rem">
    <?php $total = count($pages); ?>
    <?php foreach ($pages as $index => $page): ?>
      <?php
        $statusParts = [];
        $statusParts[] = (int)$page['show_in_nav'] === 1 ? 'v navigaci' : 'mimo navigaci';
        $statusParts[] = (int)$page['is_published'] === 1 ? 'zveřejněná' : 'nezveřejněná';
        if (($page['status'] ?? 'published') === 'pending') {
            $statusParts[] = 'čeká na schválení';
        }
      ?>
      <li style="display:flex;align-items:center;gap:.75rem;padding:.55rem 0;border-bottom:1px solid #eee;flex-wrap:wrap">
        <div style="min-width:16rem;flex:1 1 18rem">
          <strong><?= h((string)$page['title']) ?></strong>
          <br><small><?= h((string)$page['slug']) ?></small>
          <br><small style="color:#555"><?= h(implode(', ', $statusParts)) ?></small>
        </div>

        <span style="min-width:4rem;color:#555">#<?= (int)$page['nav_order'] ?></span>

        <form method="post" action="<?= BASE_URL ?>/admin/page_reorder.php" style="display:inline;margin:0">
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
          <input type="hidden" name="dir" value="up">
          <button type="submit" class="btn"
                  <?= $index === 0 ? 'disabled aria-disabled="true"' : '' ?>
                  aria-label="Posunout stránku <?= h((string)$page['title']) ?> nahoru">
            <span aria-hidden="true">↑</span> Nahoru
          </button>
        </form>

        <form method="post" action="<?= BASE_URL ?>/admin/page_reorder.php" style="display:inline;margin:0">
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
          <input type="hidden" name="dir" value="down">
          <button type="submit" class="btn"
                  <?= $index === $total - 1 ? 'disabled aria-disabled="true"' : '' ?>
                  aria-label="Posunout stránku <?= h((string)$page['title']) ?> dolů">
            <span aria-hidden="true">↓</span> Dolů
          </button>
        </form>

        <a href="<?= BASE_URL ?>/admin/page_form.php?id=<?= (int)$page['id'] ?>&amp;redirect=<?= rawurlencode(BASE_URL . '/admin/page_positions.php') ?>" class="btn">Upravit</a>
      </li>
    <?php endforeach; ?>
  </ol>
<?php endif; ?>

<?php adminFooter(); ?>
