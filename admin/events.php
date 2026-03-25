<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu událostí nemáte potřebné oprávnění.');

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
    $whereParts[] = '(e.title LIKE ? OR e.location LIKE ? OR e.description LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($statusFilter === 'pending') {
    $whereParts[] = "COALESCE(e.status,'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $whereParts[] = "COALESCE(e.status,'published') = 'published' AND e.is_published = 1";
} elseif ($statusFilter === 'hidden') {
    $whereParts[] = "COALESCE(e.status,'published') = 'published' AND e.is_published = 0";
}

$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$stmt = $pdo->prepare(
    "SELECT e.id, e.title, e.slug, e.location, e.event_date, e.event_end, e.is_published,
            COALESCE(e.status,'published') AS status
     FROM cms_events e
     {$whereSql}
     ORDER BY e.event_date DESC, e.id DESC"
);
$stmt->execute($params);
$events = $stmt->fetchAll();

adminHeader('Události');
?>
<p><a href="event_form.php" class="btn">+ Přidat událost</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q" class="visually-hidden">Hledat v událostech</label>
    <input type="search" id="q" name="q" placeholder="Hledat v událostech…"
           value="<?= h($q) ?>" style="width:300px">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Publikované</option>
      <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Čekající</option>
      <option value="hidden"<?= $statusFilter === 'hidden' ? ' selected' : '' ?>>Skryté</option>
    </select>
  </div>
  <button type="submit" class="btn">Filtrovat</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="events.php" class="btn">Zrušit</a>
  <?php endif; ?>
</form>

<?php if (empty($events)): ?>
  <p>Žádné události<?= $q !== '' || $statusFilter !== 'all' ? ' pro zadaný filtr.' : '.' ?></p>
<?php else: ?>
  <table>
    <caption>Události</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Datum konání</th>
        <th scope="col">Místo</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($events as $event): ?>
      <tr>
        <td>
          <strong><?= h((string)$event['title']) ?></strong><br>
          <small style="color:#555">/events/<?= h((string)($event['slug'] ?? '')) ?></small>
        </td>
        <td><time datetime="<?= h(str_replace(' ', 'T', (string)$event['event_date'])) ?>"><?= h(formatCzechDate((string)$event['event_date'])) ?></time></td>
        <td><?= h((string)($event['location'] ?: '–')) ?></td>
        <td>
          <?php if ($event['status'] === 'pending'): ?>
            <strong class="status-badge status-badge--pending"><span aria-hidden="true">⌛</span> Čeká na schválení</strong>
          <?php elseif ((int)$event['is_published'] === 1): ?>
            Publikováno
          <?php else: ?>
            <strong>Skrytá</strong>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="event_form.php?id=<?= (int)$event['id'] ?>" class="btn">Upravit</a>
          <?php if ($event['status'] === 'published' && (int)$event['is_published'] === 1): ?>
            <a href="<?= h(eventPublicPath($event)) ?>" target="_blank" rel="noopener noreferrer">Veřejná stránka</a>
          <?php endif; ?>
          <?php if ($event['status'] === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="events">
              <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/events.php">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="event_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat událost?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>



<?php adminFooter(); ?>
