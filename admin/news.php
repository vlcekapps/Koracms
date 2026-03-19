<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo   = db_connect();
$items = $pdo->query(
    "SELECT n.id, n.content, n.created_at, COALESCE(n.status,'published') AS status,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
     FROM cms_news n
     LEFT JOIN cms_users u ON u.id = n.author_id
     ORDER BY n.created_at DESC"
)->fetchAll();

adminHeader('Novinky – správa');
?>
<p><a href="news_form.php" class="btn">+ Přidat novinku</a></p>

<?php if (empty($items)): ?>
  <p>Žádné novinky.</p>
<?php else: ?>
  <table>
    <caption>Novinky</caption>
    <thead>
      <tr>
        <th scope="col">Text</th>
        <th scope="col">Autor</th>
        <th scope="col">Datum</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $n): ?>
      <tr<?= $n['status'] === 'pending' ? ' style="background:#fffbe6"' : '' ?>>
        <td><?= h(mb_strimwidth($n['content'], 0, 80, '…')) ?></td>
        <td><?= $n['author_name'] ? h($n['author_name']) : '<em>–</em>' ?></td>
        <td><?= h(formatCzechDate($n['created_at'])) ?></td>
        <td>
          <?php if ($n['status'] === 'pending'): ?>
            <strong style="color:#c60">⏳ Čeká</strong>
          <?php else: ?>
            Publikováno
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="news_form.php?id=<?= (int)$n['id'] ?>">Upravit</a>
          <?php if ($n['status'] === 'pending' && isSuperAdmin()): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="news">
              <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/news.php">
              <button type="submit" class="btn" style="background:#060;color:#fff">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="news_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat novinku?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php adminFooter(); ?>
