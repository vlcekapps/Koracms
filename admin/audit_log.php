<?php
require_once __DIR__ . '/layout.php';
requireCapability('settings_manage', 'Přístup odepřen.');

$pdo = db_connect();

$filterAction = trim($_GET['action'] ?? '');
$filterUser   = trim($_GET['user'] ?? '');
$filterDate   = trim($_GET['date'] ?? '');
$perPage = 50;

$where = [];
$params = [];

if ($filterAction !== '') {
    $where[] = 'l.action LIKE ?';
    $params[] = '%' . $filterAction . '%';
}
if ($filterUser !== '' && ctype_digit($filterUser)) {
    $where[] = 'l.user_id = ?';
    $params[] = (int)$filterUser;
}
if ($filterDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $where[] = 'DATE(l.created_at) = ?';
    $params[] = $filterDate;
}

$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$pag = paginate($pdo, "SELECT COUNT(*) FROM cms_log l {$whereSql}", $params, $perPage);
['totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pag;

$stmt = $pdo->prepare(
    "SELECT l.id, l.action, l.detail, l.user_id, l.created_at,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email, '–') AS user_name
     FROM cms_log l
     LEFT JOIN cms_users u ON u.id = l.user_id
     {$whereSql}
     ORDER BY l.created_at DESC, l.id DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$entries = $stmt->fetchAll();

$users = $pdo->query(
    "SELECT DISTINCT l.user_id, COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS name
     FROM cms_log l LEFT JOIN cms_users u ON u.id = l.user_id WHERE l.user_id IS NOT NULL ORDER BY name"
)->fetchAll();

$filterParams = [];
if ($filterAction !== '') { $filterParams['action'] = $filterAction; }
if ($filterUser !== '') { $filterParams['user'] = $filterUser; }
if ($filterDate !== '') { $filterParams['date'] = $filterDate; }
$baseUrl = BASE_URL . '/admin/audit_log.php';
$paginBase = $baseUrl . ($filterParams !== [] ? '?' . http_build_query($filterParams) . '&' : '?');
$hasFilter = $filterParams !== [];

adminHeader('Audit log');
?>

<p style="font-size:.9rem">Záznam akcí provedených v administraci – přihlášení, úpravy obsahu, změny nastavení a další.</p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
  <label for="action" class="visually-hidden">Akce</label>
  <input type="text" id="action" name="action" placeholder="Hledat akci…"
         value="<?= h($filterAction) ?>" style="width:200px">

  <label for="user" class="visually-hidden">Uživatel</label>
  <select id="user" name="user" style="min-width:150px">
    <option value="">Všichni uživatelé</option>
    <?php foreach ($users as $u): ?>
      <option value="<?= (int)$u['user_id'] ?>"<?= $filterUser === (string)$u['user_id'] ? ' selected' : '' ?>><?= h((string)$u['name']) ?></option>
    <?php endforeach; ?>
  </select>

  <label for="date" class="visually-hidden">Datum</label>
  <input type="date" id="date" name="date" value="<?= h($filterDate) ?>" style="width:auto">

  <button type="submit" class="btn">Filtrovat</button>
  <?php if ($hasFilter): ?>
    <a href="audit_log.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($entries)): ?>
  <p>Žádné záznamy pro zadaný filtr.</p>
<?php else: ?>
  <table>
    <caption>Audit log – záznamy akcí</caption>
    <thead>
      <tr>
        <th scope="col">Datum a čas</th>
        <th scope="col">Uživatel</th>
        <th scope="col">Akce</th>
        <th scope="col">Podrobnosti akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($entries as $entry): ?>
      <tr>
        <td><time datetime="<?= h(str_replace(' ', 'T', (string)$entry['created_at'])) ?>"><?= h((string)$entry['created_at']) ?></time></td>
        <td><?= h((string)$entry['user_name']) ?></td>
        <td><code><?= h((string)$entry['action']) ?></code></td>
        <td style="max-width:400px;word-break:break-word;font-size:.88rem"><?= h(mb_substr((string)$entry['detail'], 0, 200)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?= renderPager($page, $pages, $paginBase, 'Stránkování audit logu', 'Předchozí', 'Další') ?>
<?php endif; ?>

<?php adminFooter(); ?>
