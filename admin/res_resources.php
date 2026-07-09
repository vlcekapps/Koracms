<?php
require_once __DIR__ . '/layout.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu zdrojů rezervací nemáte potřebné oprávnění.');
requireModuleEnabled('reservations');

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
            COUNT(DISTINCT l.id) AS location_count,
            COUNT(DISTINCT CASE WHEN b.status = 'pending' THEN b.id END) AS pending_count,
            COUNT(DISTINCT CASE WHEN b.status IN ('pending', 'confirmed') THEN b.id END) AS open_count,
            (SELECT COUNT(*) FROM cms_res_bookings b2 WHERE b2.resource_id = r.id AND b2.status != 'cancelled' AND b2.booking_date >= CURDATE()) AS future_cancel_count,
            (SELECT COUNT(*) FROM cms_res_hours h WHERE h.resource_id = r.id) AS hours_count,
            (SELECT COUNT(*) FROM cms_res_slots s WHERE s.resource_id = r.id) AS slot_count,
            (SELECT COUNT(*) FROM cms_res_blocked bl WHERE bl.resource_id = r.id) AS blocked_count
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

$deleteConfirmError = trim((string)($_GET['delete_error'] ?? '')) === 'confirm_required';
$deleteErrorId = inputInt('get', 'delete_error_id');
$errorMessage = $deleteConfirmError
    ? 'Zdroj rezervací nejde smazat bez potvrzení kontroly dopadu. U pole Potvrzení smazání je konkrétní nápověda.'
    : '';
$successMessage = trim((string)($_GET['deleted'] ?? '')) === '1'
    ? 'Zdroj rezervací byl smazán.'
    : '';

adminHeader('Zdroje rezervací');
?>
<?php if ($successMessage !== ''): ?><p class="success" role="status"><?= h($successMessage) ?></p><?php endif; ?>
<?php if ($errorMessage !== ''): ?><p id="res-resource-form-error" class="error" role="alert" aria-atomic="true"><?= h($errorMessage) ?></p><?php endif; ?>

<p class="button-row button-row--start admin-stack-sm">
  <a href="res_resource_form.php" class="btn">+ Přidat zdroj</a>
  <a href="res_categories.php" class="btn">Kategorie zdrojů rezervací</a>
  <a href="res_locations.php" class="btn">Lokality rezervací</a>
</p>

<form method="get" class="button-row button-row--baseline admin-stack-sm">
  <div>
    <label for="q">Hledat</label>
    <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Název, slug, kategorie nebo místo">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status" class="admin-input-auto">
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
  <div class="table-responsive">
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
      <?php
        $resourceId = (int)$resource['id'];
        $publicPath = reservationResourcePublicPath($resource);
        $deleteConfirmField = 'confirm_res_resource_delete_' . $resourceId;
        $deleteConfirmId = 'confirm-res-resource-delete-' . $resourceId;
        $deleteReviewId = 'res-resource-delete-review-' . $resourceId;
        $deleteFieldErrorId = 'confirm-res-resource-delete-' . $resourceId . '-error';
        $deleteHasError = $deleteConfirmError && $deleteErrorId === $resourceId;
        $deleteErrorFields = $deleteHasError ? [$deleteConfirmField] : [];
        ?>
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
            <br><small class="table-meta"><?= h(mb_strimwidth((string)$resource['description'], 0, 110, '…', 'UTF-8')) ?></small>
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
          <a href="res_resource_form.php?id=<?= $resourceId ?>" class="btn">Upravit</a>
          <?php if ((int)$resource['is_active'] === 1): ?>
            <a href="<?= h($publicPath) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
          <?php endif; ?>
          <form action="res_resource_delete.php" method="post"
                class="admin-inline-form"
                novalidate<?= $deleteHasError ? ' aria-describedby="res-resource-form-error"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= $resourceId ?>">
            <fieldset class="admin-inline-fieldset">
              <legend class="sr-only">Smazání rezervačního zdroje <?= h((string)$resource['name']) ?></legend>
              <p id="<?= h($deleteReviewId) ?>" class="field-help field-help--flush">
                Smazání zruší budoucí nezrušené rezervace tohoto zdroje a odstraní jeho dostupnost. Počty:
                budoucí nezrušené rezervace <?= (int)$resource['future_cancel_count'] ?>,
                vazby na místa <?= (int)$resource['location_count'] ?>,
                pravidla otevírací doby <?= (int)$resource['hours_count'] ?>,
                sloty <?= (int)$resource['slot_count'] ?>,
                blokované dny <?= (int)$resource['blocked_count'] ?>.
                Historické rezervace zůstanou v přehledu, ale po odstranění zdroje už nepovedou na veřejnou stránku zdroje.
              </p>
              <label for="<?= h($deleteConfirmId) ?>" class="admin-checkbox-label">
                <input
                  type="checkbox"
                  id="<?= h($deleteConfirmId) ?>"
                  name="<?= h($deleteConfirmField) ?>"
                  value="1"
                  required
                  aria-required="true"<?= adminFieldAttributes($deleteConfirmField, $deleteErrorFields, [], [$deleteReviewId], $deleteFieldErrorId) ?>>
                Potvrzuji smazání tohoto rezervačního zdroje.
              </label>
              <?php adminRenderFieldError($deleteConfirmField, $deleteErrorFields, [], 'Před smazáním zdroje potvrďte, že jste zkontrolovali budoucí rezervace, dostupnost a veřejnou stránku zdroje.', $deleteFieldErrorId); ?>
              <button type="submit" class="btn btn-danger"
                      data-confirm="Smazat zdroj? Budoucí nezrušené rezervace budou zrušeny a dostupnost zdroje odstraněna.">Smazat</button>
            </fieldset>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

<?php adminFooter(); ?>
