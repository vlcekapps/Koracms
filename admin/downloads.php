<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$items = db_connect()->query(
    "SELECT d.id, d.title, d.dl_category_id, c.name AS category_name,
            d.filename, d.original_name, d.file_size, d.is_published, d.created_at,
            COALESCE(d.status,'published') AS status,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
     FROM cms_downloads d
     LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
     LEFT JOIN cms_users u ON u.id = d.author_id
     ORDER BY c.name, d.sort_order, d.title"
)->fetchAll();

adminHeader('Ke stažení');
?>
<p><a href="download_form.php" class="btn">+ Přidat soubor</a></p>

<?php if (empty($items)): ?>
  <p>Žádné soubory.</p>
<?php else: ?>
  <table>
    <caption>Soubory ke stažení</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Kategorie</th>
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
        <td>
          <?php if ($d['filename'] !== ''): ?>
            <a href="<?= moduleFileUrl('downloads', (int)$d['id']) ?>"
               target="_blank" rel="noopener" download="<?= h($d['original_name']) ?>"><?= h($d['original_name']) ?></a>
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
          <?php elseif ($d['is_published']): ?>
            Publikováno
          <?php else: ?>
            <strong>Skryto</strong>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="download_form.php?id=<?= (int)$d['id'] ?>" class="btn">Upravit</a>
          <?php if ($d['status'] === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="downloads">
              <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/downloads.php">
              <button type="submit" class="btn" style="background:#060;color:#fff">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="download_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat soubor?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
