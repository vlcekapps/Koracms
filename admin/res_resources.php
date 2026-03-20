<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();

$resources = $pdo->query(
    "SELECT r.id, r.name, r.slot_mode, r.capacity, r.is_active,
            c.name AS category_name
     FROM cms_res_resources r
     LEFT JOIN cms_res_categories c ON c.id = r.category_id
     ORDER BY r.name"
)->fetchAll();

$slotModeLabels = [
    'slots'    => 'Předdefinované sloty',
    'range'    => 'Časový rozsah',
    'duration' => 'Pevná délka',
];

adminHeader('Rezervace – zdroje');
?>
<p>
  <a href="res_resource_form.php" class="btn">+ Přidat zdroj</a>
  <a href="res_categories.php" class="btn" style="margin-left:.5rem">Kategorie</a>
</p>

<?php if (empty($resources)): ?>
  <p>Žádné zdroje.</p>
<?php else: ?>
  <table>
    <caption>Rezervační zdroje</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Kategorie</th>
        <th scope="col">Režim slotů</th>
        <th scope="col">Kapacita</th>
        <th scope="col">Aktivní</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($resources as $r): ?>
      <tr>
        <td><?= h($r['name']) ?></td>
        <td><?= h($r['category_name'] ?: '-') ?></td>
        <td><?= h($slotModeLabels[$r['slot_mode']] ?? $r['slot_mode']) ?></td>
        <td><?= (int)$r['capacity'] ?></td>
        <td><?= $r['is_active'] ? 'Ano' : 'Ne' ?></td>
        <td class="actions">
          <a href="res_resource_form.php?id=<?= (int)$r['id'] ?>" class="btn">Upravit</a>
          <form action="res_resource_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat zdroj? Budoucí rezervace budou zrušeny.')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
