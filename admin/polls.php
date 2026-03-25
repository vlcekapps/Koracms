<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu anket nemáte potřebné oprávnění.');

$pdo = db_connect();
$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$allowedStatusFilters = ['all', 'active', 'scheduled', 'closed'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$whereParts = [];
$params = [];

if ($q !== '') {
    $whereParts[] = '(p.question LIKE ? OR p.description LIKE ? OR p.slug LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$stmt = $pdo->prepare(
    "SELECT p.*,
            (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
     FROM cms_polls p
     {$whereSql}
     ORDER BY COALESCE(p.start_date, p.created_at) DESC, p.id DESC"
);
$stmt->execute($params);
$polls = array_map(
    static fn(array $poll): array => hydratePollPresentation($poll),
    $stmt->fetchAll()
);

if ($statusFilter !== 'all') {
    $polls = array_values(array_filter(
        $polls,
        static fn(array $poll): bool => (string)($poll['state'] ?? '') === $statusFilter
    ));
}

adminHeader('Ankety');
?>
<p><a href="polls_form.php" class="btn">+ Nová anketa</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q" class="visually-hidden">Hledat v anketách</label>
    <input type="search" id="q" name="q" placeholder="Hledat v anketách..."
           value="<?= h($q) ?>" style="width:320px">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="active"<?= $statusFilter === 'active' ? ' selected' : '' ?>>Aktivní</option>
      <option value="scheduled"<?= $statusFilter === 'scheduled' ? ' selected' : '' ?>>Naplánované</option>
      <option value="closed"<?= $statusFilter === 'closed' ? ' selected' : '' ?>>Uzavřené</option>
    </select>
  </div>
  <button type="submit" class="btn">Filtrovat</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="polls.php" class="btn">Zrušit</a>
  <?php endif; ?>
</form>

<?php if (empty($polls)): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all'): ?>
      Pro zvolený filtr tu teď nejsou žádné ankety.
    <?php else: ?>
      Zatím tu nejsou žádné ankety. <a href="polls_form.php">Přidat první anketu</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <table>
    <caption>Ankety</caption>
    <thead>
      <tr>
        <th scope="col">Otázka</th>
        <th scope="col">Stav</th>
        <th scope="col">Hlasy</th>
        <th scope="col">Začátek</th>
        <th scope="col">Konec</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($polls as $poll): ?>
      <?php
        $state = (string)($poll['state'] ?? 'active');
        $stateStyle = match ($state) {
            'closed' => 'color:#666',
            'scheduled' => 'color:#0b5fa5',
            default => 'color:#0a6a2f',
        };
      ?>
      <tr<?= $state === 'scheduled' ? ' style="background:#eef6ff"' : '' ?>>
        <td>
          <strong><?= h((string)$poll['question']) ?></strong><br>
          <small style="color:#555">/polls/<?= h((string)$poll['slug']) ?></small>
          <?php if ($poll['excerpt'] !== ''): ?>
            <br><small style="color:#555"><?= h((string)$poll['excerpt']) ?></small>
          <?php endif; ?>
        </td>
        <td><strong style="<?= $stateStyle ?>"><?= h((string)$poll['state_label']) ?></strong></td>
        <td><?= (int)($poll['vote_count'] ?? 0) ?></td>
        <td><?= !empty($poll['start_date']) ? h(formatCzechDate((string)$poll['start_date'])) : '–' ?></td>
        <td><?= !empty($poll['end_date']) ? h(formatCzechDate((string)$poll['end_date'])) : '–' ?></td>
        <td class="actions">
          <a href="polls_form.php?id=<?= (int)$poll['id'] ?>" class="btn">Upravit</a>
          <?php if ($state !== 'scheduled'): ?>
            <a href="<?= h(pollPublicPath($poll)) ?>" target="_blank" rel="noopener noreferrer">Veřejná stránka</a>
          <?php endif; ?>
          <form action="polls_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$poll['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat anketu včetně všech hlasů?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>



<?php adminFooter(); ?>
