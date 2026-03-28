<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu míst nemáte potřebné oprávnění.');

$pdo = db_connect();
$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$allowedStatusFilters = ['all', 'pending', 'published', 'hidden'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$whereParts = [];
$params = [];

if ($q !== '') {
    $whereParts[] = '(p.name LIKE ? OR p.excerpt LIKE ? OR p.description LIKE ? OR p.category LIKE ?
        OR p.locality LIKE ? OR p.address LIKE ? OR p.contact_phone LIKE ? OR p.contact_email LIKE ?)';
    for ($i = 0; $i < 8; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($statusFilter === 'pending') {
    $whereParts[] = "COALESCE(p.status,'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $whereParts[] = "COALESCE(p.status,'published') = 'published' AND p.is_published = 1";
} elseif ($statusFilter === 'hidden') {
    $whereParts[] = "COALESCE(p.status,'published') = 'published' AND p.is_published = 0";
}

$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$stmt = $pdo->prepare(
    "SELECT p.id, p.name, p.slug, p.place_kind, p.category, p.locality, p.url, p.image_file,
            p.is_published, COALESCE(p.status,'published') AS status
     FROM cms_places p
     {$whereSql}
     ORDER BY p.name ASC"
);
$stmt->execute($params);
$places = array_map(
    static fn(array $place): array => hydratePlacePresentation($place),
    $stmt->fetchAll()
);

adminHeader('Zajímavá místa');
?>
<p><a href="place_form.php" class="btn">+ Přidat místo</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q" class="visually-hidden">Hledat v místech</label>
    <input type="search" id="q" name="q" placeholder="Hledat v místech…"
           value="<?= h($q) ?>" style="width:300px">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Publikovaná</option>
      <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Čekající</option>
      <option value="hidden"<?= $statusFilter === 'hidden' ? ' selected' : '' ?>>Skrytá</option>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="places.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($places)): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all'): ?>
      Pro zvolený filtr tu teď nejsou žádná místa.
    <?php else: ?>
      Zatím tu nejsou žádná místa. <a href="place_form.php">Přidat první místo</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <?= bulkFormOpen('places', 'places.php') ?>
  <?= bulkActionBar() ?>
  <?= bulkFormClose() ?>
  <table>
    <caption>Přehled zajímavých míst</caption>
    <thead>
      <tr>
        <th scope="col"><input type="checkbox" class="bulk-select-all" form="bulk-form" aria-label="Vybrat vše"></th>
        <th scope="col">Místo</th>
        <th scope="col">Typ a lokalita</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($places as $place): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int)$place['id'] ?>" class="bulk-checkbox" form="bulk-form" aria-label="Vybrat <?= h((string)$place['name']) ?>"></td>
        <td>
          <strong><?= h((string)$place['name']) ?></strong><br>
          <small style="color:#555">/places/<?= h((string)$place['slug']) ?></small>
          <?php if (!empty($place['category'])): ?>
            <br><small style="color:#555"><?= h((string)$place['category']) ?></small>
          <?php endif; ?>
          <?php if (!empty($place['image_file'])): ?>
            <br><small style="color:#555">Obrázek připojen</small>
          <?php endif; ?>
        </td>
        <td>
          <strong><?= h((string)$place['place_kind_label']) ?></strong>
          <?php if (!empty($place['locality'])): ?>
            <br><small style="color:#555"><?= h((string)$place['locality']) ?></small>
          <?php endif; ?>
          <?php if (!empty($place['url'])): ?>
            <br><a href="<?= h((string)$place['url']) ?>" target="_blank" rel="noopener noreferrer">Externí web</a>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($place['status'] === 'pending'): ?>
            <strong class="status-badge status-badge--pending">Čeká na schválení</strong>
          <?php elseif ((int)$place['is_published'] === 1): ?>
            Publikováno
          <?php else: ?>
            <strong>Skryto</strong>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="place_form.php?id=<?= (int)$place['id'] ?>" class="btn">Upravit</a>
          <?php if ($place['status'] === 'published' && (int)$place['is_published'] === 1): ?>
            <a href="<?= h(placePublicPath($place)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
          <?php endif; ?>
          <?php if ($place['status'] === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="places">
              <input type="hidden" name="id" value="<?= (int)$place['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/places.php">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="place_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$place['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat místo?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= bulkCheckboxJs() ?>
<?php endif; ?>

<?php adminFooter(); ?>
