<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu jídelních lístků nemáte potřebné oprávnění.');

$pdo = db_connect();
$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$typeFilter = trim($_GET['type'] ?? 'all');
$scopeFilter = trim($_GET['scope'] ?? 'all');
$allowedStatusFilters = ['all', 'pending', 'published', 'hidden'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}
$allowedTypeFilters = ['all', 'food', 'beverage'];
if (!in_array($typeFilter, $allowedTypeFilters, true)) {
    $typeFilter = 'all';
}
$allowedScopeFilters = ['all', 'current', 'upcoming', 'archive'];
if (!in_array($scopeFilter, $allowedScopeFilters, true)) {
    $scopeFilter = 'all';
}

$whereParts = ['c.deleted_at IS NULL'];
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
if ($typeFilter !== 'all') {
    $whereParts[] = 'c.type = ?';
    $params[] = $typeFilter;
}
if ($scopeFilter !== 'all') {
    $whereParts[] = '(' . foodCardScopeVisibilitySql($scopeFilter, 'c') . ')';
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);
$stateOrderSql = "CASE
    WHEN c.valid_from IS NOT NULL AND c.valid_from > CURDATE() THEN 1
    WHEN c.valid_to IS NOT NULL AND c.valid_to < CURDATE() THEN 2
    ELSE 0
  END";

$stmt = $pdo->prepare(
    "SELECT c.id, c.type, c.title, c.slug, c.description, c.valid_from, c.valid_to, c.is_current,
            c.is_published, COALESCE(c.status,'published') AS status, c.created_at, c.updated_at,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
     FROM cms_food_cards c
     LEFT JOIN cms_users u ON u.id = c.author_id
     {$whereSql}
     ORDER BY c.type,
              {$stateOrderSql},
              c.is_current DESC,
              CASE WHEN c.valid_from IS NOT NULL AND c.valid_from > CURDATE() THEN c.valid_from END ASC,
              CASE WHEN c.valid_to IS NOT NULL AND c.valid_to < CURDATE() THEN c.valid_to END DESC,
              COALESCE(c.valid_from, c.created_at) DESC,
              c.id DESC"
);
$stmt->execute($params);
$items = array_map(
    static fn (array $item): array => hydrateFoodCardPresentation($item),
    $stmt->fetchAll()
);

$canApprove = currentUserHasCapability('content_approve_shared');

adminHeader('Jídelní a nápojový lístek');
?>
<p class="button-row button-row--start admin-stack-sm">
  <a href="food_form.php?type=food" class="btn">+ Nový jídelní lístek</a>
  <a href="food_form.php?type=beverage" class="btn">+ Nový nápojový lístek</a>
</p>

<form method="get" class="button-row button-row--baseline admin-stack-sm">
  <div>
    <label for="q" class="visually-hidden">Hledat v lístcích</label>
    <input type="search" id="q" name="q" placeholder="Hledat v lístcích…"
           value="<?= h($q) ?>" class="admin-search-input">
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
  <div>
    <label for="type">Typ</label>
    <select id="type" name="type">
      <option value="all"<?= $typeFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="food"<?= $typeFilter === 'food' ? ' selected' : '' ?>>Jídelní lístky</option>
      <option value="beverage"<?= $typeFilter === 'beverage' ? ' selected' : '' ?>>Nápojové lístky</option>
    </select>
  </div>
  <div>
    <label for="scope">Platnost</label>
    <select id="scope" name="scope">
      <option value="all"<?= $scopeFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="current"<?= $scopeFilter === 'current' ? ' selected' : '' ?>>Platí nyní</option>
      <option value="upcoming"<?= $scopeFilter === 'upcoming' ? ' selected' : '' ?>>Připravujeme</option>
      <option value="archive"<?= $scopeFilter === 'archive' ? ' selected' : '' ?>>Archivní</option>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all' || $typeFilter !== 'all' || $scopeFilter !== 'all'): ?>
    <a href="food.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($items)): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all' || $typeFilter !== 'all' || $scopeFilter !== 'all'): ?>
      Pro zvolený filtr tu teď nejsou žádné jídelní ani nápojové lístky.
    <?php else: ?>
      Zatím tu nejsou žádné jídelní ani nápojové lístky.
      <a href="food_form.php?type=food">Přidat první jídelní lístek</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <?= bulkActions('food', BASE_URL . '/admin/food.php', 'Hromadné akce s jídelníčky', 'položka') ?>
  <?php
  $groups = [];
    foreach ($items as $item) {
        $groups[$item['type']][] = $item;
    }
    $labels = ['food' => 'Jídelní lístky', 'beverage' => 'Nápojové lístky'];
    $captions = ['food' => 'Přehled jídelních lístků', 'beverage' => 'Přehled nápojových lístků'];
    foreach (['food', 'beverage'] as $type):
        if (!isset($groups[$type])) {
            continue;
        }
        $rows = $groups[$type];
        ?>
  <h2 class="admin-section-heading"><?= h($labels[$type]) ?></h2>
  <table>
    <caption><?= h($captions[$type]) ?></caption>
    <thead>
      <tr>
        <th scope="col"><label for="check-all-<?= h($type) ?>" class="sr-only">Vybrat vše</label><input type="checkbox" id="check-all-<?= h($type) ?>" data-check-all="bulk-form"></th>
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
        <td><label for="food-card-select-<?= h($type) ?>-<?= (int)$card['id'] ?>" class="sr-only">Vybrat <?= h((string)$card['title']) ?></label><input type="checkbox" id="food-card-select-<?= h($type) ?>-<?= (int)$card['id'] ?>" name="ids[]" value="<?= (int)$card['id'] ?>" form="bulk-form"></td>
        <td>
          <?php if ($card['is_current']): ?>
            <strong>★ <?= h((string)$card['title']) ?></strong>
          <?php else: ?>
            <?= h((string)$card['title']) ?>
          <?php endif; ?>
          <br><small class="table-meta">/food/card/<?= h((string)$card['slug']) ?></small>
          <?php if (!empty($card['description'])): ?>
            <br><small class="table-meta"><?= h((string)$card['description']) ?></small>
          <?php endif; ?>
        </td>
        <td>
          <strong><?= h((string)($card['state_label'] ?? 'Platí nyní')) ?></strong>
          <br><small class="table-meta"><?= h((string)($card['validity_label'] !== '' ? $card['validity_label'] : 'Bez omezení')) ?></small>
        </td>
        <td><?= h((string)($card['author_name'] ?: '–')) ?></td>
        <td>
          <?php if ($card['status'] === 'pending'): ?>
            <strong class="status-badge status-badge--pending">Čeká na schválení</strong>
          <?php elseif ((int)$card['is_published'] === 1): ?>
            <?= $card['is_current'] ? '<strong class="status-badge status-badge--current">Aktuální</strong>' : 'Publikováno' ?>
          <?php else: ?>
            <strong>Skryto</strong>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="food_form.php?id=<?= (int)$card['id'] ?>" class="btn">Upravit</a>
          <a href="revisions.php?type=food&amp;id=<?= (int)$card['id'] ?>">Historie revizí</a>
          <?php if ($card['is_publicly_visible']): ?>
            <a href="<?= h((string)$card['public_path']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= h(newWindowLinkLabel('Zobrazit na webu')) ?>">Zobrazit na webu</a>
          <?php endif; ?>
          <?php if ($card['status'] === 'pending' && $canApprove): ?>
            <form action="approve.php" method="post">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="food">
              <input type="hidden" name="id" value="<?= (int)$card['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/food.php">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="food_delete.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$card['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Smazat lístek &quot;<?= h(addslashes((string)$card['title'])) ?>&quot;?">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endforeach; ?>
  <?= bulkCheckboxJs() ?>
<?php endif; ?>

<?php adminFooter(); ?>
