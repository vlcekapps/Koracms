<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu statických stránek nemáte potřebné oprávnění.');

$pdo = db_connect();
$q = trim($_GET['q'] ?? '');
$statusFilter = in_array($_GET['status'] ?? '', ['all', 'pending', 'published', 'hidden'], true)
    ? (string)$_GET['status']
    : 'all';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(title LIKE ? OR slug LIKE ? OR content LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($statusFilter === 'pending') {
    $where[] = "COALESCE(status,'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $where[] = "COALESCE(status,'published') = 'published' AND is_published = 1";
} elseif ($statusFilter === 'hidden') {
    $where[] = "COALESCE(status,'published') = 'published' AND is_published = 0";
}

$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT id, title, slug, is_published, show_in_nav, nav_order,
            COALESCE(status,'published') AS status, created_at
     FROM cms_pages
     {$whereSql}
     ORDER BY nav_order, title"
);
$stmt->execute($params);
$pages = $stmt->fetchAll();

$currentRedirect = BASE_URL . '/admin/pages.php';
$queryArgs = array_filter([
    'q' => $q,
    'status' => $statusFilter !== 'all' ? $statusFilter : null,
], static fn($value): bool => $value !== null && $value !== '');
if ($queryArgs !== []) {
    $currentRedirect .= '?' . http_build_query($queryArgs);
}

adminHeader('Statické stránky');
?>

<p><a href="<?= BASE_URL ?>/admin/page_form.php" class="btn">+ Nová stránka</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q">Hledat</label>
    <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Název, slug nebo obsah stránky">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status" style="width:auto">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Publikováno</option>
      <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Čekající</option>
      <option value="hidden"<?= $statusFilter === 'hidden' ? ' selected' : '' ?>>Skryté</option>
    </select>
  </div>
  <button type="submit" class="btn">Filtrovat</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="<?= BASE_URL ?>/admin/pages.php" class="btn">Zrušit</a>
  <?php endif; ?>
</form>

<?php if ($pages === []): ?>
  <p>Žádné statické stránky<?= ($q !== '' || $statusFilter !== 'all') ? ' pro zadaný filtr.' : '.' ?></p>
<?php else: ?>
  <table>
    <caption>Statické stránky</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Stav</th>
        <th scope="col">V navigaci</th>
        <th scope="col">Pořadí</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pages as $page): ?>
        <?php $publicPath = pagePublicPath($page); ?>
        <tr>
          <td>
            <strong><?= h((string)$page['title']) ?></strong>
            <br><small><?= h((string)$page['slug']) ?></small>
            <?php if (!empty($page['created_at'])): ?>
              <br><small style="color:#555">Vytvořeno <?= h((string)$page['created_at']) ?></small>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($page['status'] === 'pending'): ?>
              <strong style="color:#c60">Čeká na schválení</strong>
            <?php elseif ((int)$page['is_published'] === 1): ?>
              Publikováno
            <?php else: ?>
              <strong>Skryto</strong>
            <?php endif; ?>
          </td>
          <td><?= (int)$page['show_in_nav'] === 1 ? 'Ano' : '–' ?></td>
          <td><?= (int)$page['nav_order'] ?></td>
          <td class="actions">
            <a href="<?= BASE_URL ?>/admin/page_form.php?id=<?= (int)$page['id'] ?>&amp;redirect=<?= rawurlencode($currentRedirect) ?>" class="btn">Upravit</a>
            <?php if ($page['status'] === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
              <form action="<?= BASE_URL ?>/admin/approve.php" method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="module" value="pages">
                <input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn" style="background:#060;color:#fff">Schválit</button>
              </form>
            <?php endif; ?>
            <?php if ((int)$page['is_published'] === 1): ?>
              <a href="<?= h($publicPath) ?>" target="_blank" rel="noopener noreferrer">Veřejná stránka</a>
            <?php endif; ?>
            <form method="post" action="<?= BASE_URL ?>/admin/page_delete.php" style="display:inline"
                  onsubmit="return confirm('Smazat tuto stránku?')">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
              <button type="submit" class="btn btn-danger">Smazat</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
