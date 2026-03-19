<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$places = db_connect()->query(
    "SELECT id, name, category, url, is_published, sort_order,
            COALESCE(status,'published') AS status
     FROM cms_places ORDER BY sort_order, name"
)->fetchAll();

adminHeader('Zajímavá místa');
?>
<p><a href="place_form.php" class="btn">+ Přidat místo</a></p>

<?php if (empty($places)): ?>
  <p>Žádná místa.</p>
<?php else: ?>
  <table>
    <caption>Zajímavá místa</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Kategorie</th>
        <th scope="col">URL</th>
        <th scope="col">Pořadí</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($places as $p): ?>
      <tr>
        <td><?= h($p['name']) ?></td>
        <td><?= h($p['category'] ?: '–') ?></td>
        <td><?= $p['url'] ? '<a href="' . h($p['url']) . '" target="_blank" rel="noopener">odkaz</a>' : '–' ?></td>
        <td><?= (int)$p['sort_order'] ?></td>
        <td>
          <?php if ($p['status'] === 'pending'): ?>
            <strong style="color:#c60">⏳ Čeká na schválení</strong>
          <?php elseif ($p['is_published']): ?>
            Zobrazeno
          <?php else: ?>
            <strong>Skryto</strong>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="place_form.php?id=<?= (int)$p['id'] ?>" class="btn">Upravit</a>
          <?php if ($p['status'] === 'pending' && isSuperAdmin()): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="places">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/places.php">
              <button type="submit" class="btn" style="background:#060;color:#fff">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="place_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat místo?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
