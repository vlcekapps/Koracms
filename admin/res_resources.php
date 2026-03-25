<?php
require_once __DIR__ . '/layout.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu zdrojů rezervací nemáte potřebné oprávnění.');

$pdo = db_connect();
$q = trim($_GET['q'] ?? '');
$statusFilter = in_array($_GET['status'] ?? '', ['all', 'active', 'inactive'], true)
    ? (string)$_GET['status']
    : 'all';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(r.name LIKE ? OR r.slug LIKE ? OR r.description LIKE ? OR c.name LIKE ? OR l.name LIKE ? OR l.address LIKE ?)';
    for ($i = 0; $i < 6; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($statusFilter === 'active') {
    $where[] = 'r.is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $where[] = 'r.is_active = 0';
}

$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT r.id, r.name, r.slug, r.slot_mode, r.capacity, r.is_active, r.description,
            c.name AS category_name,
            GROUP_CONCAT(DISTINCT l.name ORDER BY l.name SEPARATOR ', ') AS location_names,
            COUNT(DISTINCT CASE WHEN b.status = 'pending' THEN b.id END) AS pending_count,
            COUNT(DISTINCT CASE WHEN b.status IN ('pending', 'confirmed') THEN b.id END) AS open_count
     FROM cms_res_resources r
     LEFT JOIN cms_res_categories c ON c.id = r.category_id
     LEFT JOIN cms_res_resource_locations rl ON rl.resource_id = r.id
     LEFT JOIN cms_res_locations l ON l.id = rl.location_id
     LEFT JOIN cms_res_bookings b ON b.resource_id = r.id
     {$whereSql}
     GROUP BY r.id, r.name, r.slug, r.slot_mode, r.capacity, r.is_active, r.description, c.name
     ORDER BY r.is_active DESC, r.name"
);
$stmt->execute($params);
$resources = $stmt->fetchAll();

$slotModeLabels = [
    'slots' => 'Předdefinované sloty',
    'range' => 'Časový rozsah',
    'duration' => 'Pevná délka',
];

adminHeader('Zdroje rezervací');
?>
<p>
  <a href="res_resource_form.php" class="btn">+ Přidat zdroj</a>
  <a href="res_categories.php" class="btn" style="margin-left:.5rem">Kategorie zdrojů rezervací</a>
  <a href="res_locations.php" class="btn" style="margin-left:.5rem">Lokality rezervací</a>
</p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q">Hledat</label>
    <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Název, slug, kategorie nebo místo">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status" style="width:auto">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="active"<?= $statusFilter === 'active' ? ' selected' : '' ?>>Aktivní</option>
      <option value="inactive"<?= $statusFilter === 'inactive' ? ' selected' : '' ?>>Neaktivní</option>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="res_resources.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if ($resources === []): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all'): ?>
      Pro zvolený filtr tu teď nejsou žádné zdroje rezervací.
    <?php else: ?>
      Zatím tu nejsou žádné zdroje rezervací. <a href="res_resource_form.php">Přidat první zdroj</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <table>
    <caption>Přehled zdrojů rezervací</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Režim</th>
        <th scope="col">Kapacita</th>
        <th scope="col">Otevřené rezervace</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($resources as $resource): ?>
      <?php $publicPath = reservationResourcePublicPath($resource); ?>
      <tr>
        <td>
          <strong><?= h((string)$resource['name']) ?></strong>
          <br><small><?= h((string)$resource['slug']) ?></small>
          <?php if (!empty($resource['category_name'])): ?>
            <br><small>Kategorie: <?= h((string)$resource['category_name']) ?></small>
          <?php endif; ?>
          <?php if (!empty($resource['location_names'])): ?>
            <br><small>Místa: <?= h((string)$resource['location_names']) ?></small>
          <?php endif; ?>
          <?php if (!empty($resource['description'])): ?>
            <br><small style="color:#555"><?= h(mb_strimwidth((string)$resource['description'], 0, 110, '…', 'UTF-8')) ?></small>
          <?php endif; ?>
        </td>
        <td><?= h((string)($slotModeLabels[$resource['slot_mode']] ?? $resource['slot_mode'])) ?></td>
        <td><?= (int)$resource['capacity'] ?></td>
        <td>
          Aktivní: <strong><?= (int)$resource['open_count'] ?></strong>
          <br><small>Čekající: <?= (int)$resource['pending_count'] ?></small>
        </td>
        <td><?= (int)$resource['is_active'] === 1 ? 'Aktivní' : 'Neaktivní' ?></td>
        <td class="actions">
          <a href="res_resource_form.php?id=<?= (int)$resource['id'] ?>" class="btn">Upravit</a>
          <?php if ((int)$resource['is_active'] === 1): ?>
            <a href="<?= h($publicPath) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
          <?php endif; ?>
          <form action="res_resource_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$resource['id'] ?>">
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
