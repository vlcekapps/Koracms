<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo   = db_connect();
$pages = $pdo->query(
    "SELECT id, title, slug, is_published, show_in_nav, nav_order,
            COALESCE(status,'published') AS status
     FROM cms_pages
     ORDER BY nav_order, title"
)->fetchAll();

adminHeader('Statické stránky');
?>

<p><a href="<?= BASE_URL ?>/admin/page_form.php" class="btn">+ Nová stránka</a></p>

<?php if (empty($pages)): ?>
  <p>Zatím žádné statické stránky.</p>
<?php else: ?>
  <table>
    <caption>Statické stránky</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Slug</th>
        <th scope="col">Stav</th>
        <th scope="col">V navigaci</th>
        <th scope="col">Pořadí</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pages as $p): ?>
        <tr>
          <td>
            <a href="<?= BASE_URL ?>/page.php?slug=<?= rawurlencode($p['slug']) ?>"
               target="_blank" rel="noopener"><?= h($p['title']) ?></a>
          </td>
          <td><code><?= h($p['slug']) ?></code></td>
          <td>
            <?php if ($p['status'] === 'pending'): ?>
              <strong style="color:#c60">⏳ Čeká na schválení</strong>
            <?php elseif ($p['is_published']): ?>
              Publikováno
            <?php else: ?>
              <strong>Koncept</strong>
            <?php endif; ?>
          </td>
          <td><?= $p['show_in_nav'] ? 'Ano' : '–' ?></td>
          <td><?= (int)$p['nav_order'] ?></td>
          <td class="actions">
            <a href="<?= BASE_URL ?>/admin/page_form.php?id=<?= (int)$p['id'] ?>" class="btn">Upravit</a>
            <?php if ($p['status'] === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
              <form action="<?= BASE_URL ?>/admin/approve.php" method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="module" value="pages">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/pages.php">
                <button type="submit" class="btn" style="background:#060;color:#fff">Schválit</button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= BASE_URL ?>/admin/page_delete.php"
                  onsubmit="return confirm('Smazat tuto stránku?')">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id"         value="<?= (int)$p['id'] ?>">
              <button type="submit" class="btn btn-danger">Smazat</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
