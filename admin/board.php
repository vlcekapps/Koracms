<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();

$items = $pdo->query(
    "SELECT b.id, b.title, b.category_id, c.name AS category_name,
            b.posted_date, b.removal_date, b.original_name, b.file_size,
            b.is_published, b.created_at,
            COALESCE(b.status,'published') AS status,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
     FROM cms_board b
     LEFT JOIN cms_board_categories c ON c.id = b.category_id
     LEFT JOIN cms_users u ON u.id = b.author_id
     ORDER BY b.posted_date DESC, b.sort_order, b.title"
)->fetchAll();

adminHeader('Úřední deska');
?>
<p>
  <a href="board_form.php" class="btn">+ Přidat dokument</a>
  <a href="board_cats.php" style="margin-left:1rem">Správa kategorií</a>
</p>

<?php if (empty($items)): ?>
  <p>Žádné dokumenty na úřední desce.</p>
<?php else: ?>
  <table>
    <caption>Dokumenty úřední desky</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Kategorie</th>
        <th scope="col">Vyvěšeno</th>
        <th scope="col">Sejmuto</th>
        <th scope="col">Soubor</th>
        <th scope="col">Autor</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $d): ?>
      <tr>
        <td><?= h($d['title']) ?></td>
        <td><?= $d['category_name'] ? h($d['category_name']) : '<em>–</em>' ?></td>
        <td><?= h($d['posted_date']) ?></td>
        <td><?= $d['removal_date'] ? h($d['removal_date']) : '<em>bez omezení</em>' ?></td>
        <td>
          <?php if ($d['original_name'] !== ''): ?>
            <?= h($d['original_name']) ?>
            <?php if ($d['file_size'] > 0): ?>
              <small>(<?= h(formatFileSize($d['file_size'])) ?>)</small>
            <?php endif; ?>
          <?php else: ?>
            –
          <?php endif; ?>
        </td>
        <td><?= $d['author_name'] ? h($d['author_name']) : '<em>–</em>' ?></td>
        <td>
          <?php if ($d['status'] === 'pending'): ?>
            <strong style="color:#c60">⏳ Čeká na schválení</strong>
          <?php elseif (!$d['is_published']): ?>
            <strong>Skryto</strong>
          <?php elseif ($d['removal_date'] && $d['removal_date'] < date('Y-m-d')): ?>
            <em>Archivováno</em>
          <?php else: ?>
            Publikováno
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="board_form.php?id=<?= (int)$d['id'] ?>" class="btn">Upravit</a>
          <?php if ($d['status'] === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="board">
              <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/board.php">
              <button type="submit" class="btn" style="background:#060;color:#fff">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="board_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat dokument?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
