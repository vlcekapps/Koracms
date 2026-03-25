<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu jídelních lístků nemáte potřebné oprávnění.');

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
    $whereParts[] = '(c.title LIKE ? OR c.slug LIKE ? OR c.description LIKE ? OR c.content LIKE ?)';
    for ($i = 0; $i < 4; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($statusFilter === 'pending') {
    $whereParts[] = "COALESCE(c.status,'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $whereParts[] = "COALESCE(c.status,'published') = 'published' AND c.is_published = 1";
} elseif ($statusFilter === 'hidden') {
    $whereParts[] = "COALESCE(c.status,'published') = 'published' AND c.is_published = 0";
}

$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$stmt = $pdo->prepare(
    "SELECT c.id, c.type, c.title, c.slug, c.description, c.valid_from, c.valid_to, c.is_current,
            c.is_published, COALESCE(c.status,'published') AS status, c.created_at, c.updated_at,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
     FROM cms_food_cards c
     LEFT JOIN cms_users u ON u.id = c.author_id
     {$whereSql}
     ORDER BY c.type, c.is_current DESC, COALESCE(c.valid_from, c.created_at) DESC, c.id DESC"
);
$stmt->execute($params);
$items = array_map(
    static fn(array $item): array => hydrateFoodCardPresentation($item),
    $stmt->fetchAll()
);

$canApprove = currentUserHasCapability('content_approve_shared');

adminHeader('Jídelní a nápojový lístek');
?>
<p>
  <a href="food_form.php?type=food" class="btn">+ Nový jídelní lístek</a>
  <a href="food_form.php?type=beverage" class="btn" style="margin-left:.5rem">+ Nový nápojový lístek</a>
</p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q" class="visually-hidden">Hledat v lístcích</label>
    <input type="search" id="q" name="q" placeholder="Hledat v lístcích…"
           value="<?= h($q) ?>" style="width:300px">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Publikováno</option>
      <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Čekající</option>
      <option value="hidden"<?= $statusFilter === 'hidden' ? ' selected' : '' ?>>Skryté</option>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="food.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($items)): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all'): ?>
      Pro zvolený filtr tu teď nejsou žádné jídelní ani nápojové lístky.
    <?php else: ?>
      Zatím tu nejsou žádné jídelní ani nápojové lístky.
      <a href="food_form.php?type=food">Přidat první jídelní lístek</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <?php
  $groups = ['food' => [], 'beverage' => []];
  foreach ($items as $item) {
      $groups[$item['type']][] = $item;
  }
  $labels = ['food' => 'Jídelní lístky', 'beverage' => 'Nápojové lístky'];
  foreach ($groups as $type => $rows):
      if (empty($rows)) {
          continue;
      }
  ?>
  <h2 style="margin-top:2rem"><?= h($labels[$type]) ?></h2>
  <table>
    <caption>Jídelní a nápojové lístky</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Platnost</th>
        <th scope="col">Autor</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $card): ?>
      <tr<?= $card['status'] === 'pending' ? ' class="table-row--pending"' : '' ?>>
        <td>
          <?php if ($card['is_current']): ?>
            <strong>★ <?= h((string)$card['title']) ?></strong>
          <?php else: ?>
            <?= h((string)$card['title']) ?>
          <?php endif; ?>
          <br><small style="color:#555">/food/card/<?= h((string)$card['slug']) ?></small>
          <?php if (!empty($card['description'])): ?>
            <br><small style="color:#555"><?= h((string)$card['description']) ?></small>
          <?php endif; ?>
        </td>
        <td><?= h((string)($card['validity_label'] !== '' ? $card['validity_label'] : 'Bez omezení')) ?></td>
        <td><?= h((string)($card['author_name'] ?: '–')) ?></td>
        <td>
          <?php if ($card['status'] === 'pending'): ?>
            <strong class="status-badge status-badge--pending">Čeká na schválení</strong>
          <?php elseif ((int)$card['is_published'] === 1): ?>
            <?= $card['is_current'] ? '<strong style="color:#060">Aktuální</strong>' : 'Publikováno' ?>
          <?php else: ?>
            <strong>Skryto</strong>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="food_form.php?id=<?= (int)$card['id'] ?>" class="btn">Upravit</a>
          <?php if ($card['is_publicly_visible']): ?>
            <a href="<?= h((string)$card['public_path']) ?>" target="_blank" rel="noopener noreferrer">Veřejná stránka</a>
          <?php endif; ?>
          <?php if ($card['status'] === 'pending' && $canApprove): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="food">
              <input type="hidden" name="id" value="<?= (int)$card['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/food.php">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="food_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$card['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat lístek &quot;<?= h(addslashes((string)$card['title'])) ?>&quot;?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endforeach; ?>
<?php endif; ?>



<?php adminFooter(); ?>
