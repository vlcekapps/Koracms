<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu anket nemáte potřebné oprávnění.');

$pdo = db_connect();
$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$allowedStatusFilters = ['all', 'active', 'scheduled', 'closed'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$perPage = 25;
$whereParts = [];
$params = [];

if ($q !== '') {
    $whereParts[] = '(p.question LIKE ? OR p.description LIKE ? OR p.slug LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$now = date('Y-m-d H:i:s');
if ($statusFilter === 'active') {
    $whereParts[] = pollPublicVisibilitySql('p', 'active');
} elseif ($statusFilter === 'scheduled') {
    $whereParts[] = "COALESCE(p.status, 'active') = 'active' AND p.start_date IS NOT NULL AND p.start_date > ?";
    $params[] = $now;
} elseif ($statusFilter === 'closed') {
    $whereParts[] = pollPublicVisibilitySql('p', 'archive');
}

$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';
$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM cms_polls p {$whereSql}",
    $params,
    $perPage
);
['totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pagination;

$stmt = $pdo->prepare(
    "SELECT p.*,
            (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
     FROM cms_polls p
     {$whereSql}
     ORDER BY COALESCE(p.start_date, p.created_at) DESC, p.id DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$polls = array_map(
    static fn(array $poll): array => hydratePollPresentation($poll),
    $stmt->fetchAll()
);

$listQuery = array_filter([
    'q' => $q !== '' ? $q : null,
    'status' => $statusFilter !== 'all' ? $statusFilter : null,
    'strana' => $page > 1 ? $page : null,
], static fn($value): bool => $value !== null && $value !== '');
$currentListUrl = BASE_URL . '/admin/polls.php' . ($listQuery !== [] ? '?' . http_build_query($listQuery) : '');
$pagerQuery = array_filter([
    'q' => $q !== '' ? $q : null,
    'status' => $statusFilter !== 'all' ? $statusFilter : null,
], static fn($value): bool => $value !== null && $value !== '');
$pagerBaseUrl = '?' . ($pagerQuery !== [] ? http_build_query($pagerQuery) . '&' : '');

adminHeader('Ankety');
?>
<p><a href="polls_form.php?redirect=<?= urlencode($currentListUrl) ?>" class="btn">+ Nová anketa</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q" class="visually-hidden">Hledat v anketách</label>
    <input type="search" id="q" name="q" placeholder="Hledat v anketách..." value="<?= h($q) ?>" style="width:320px">
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
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="polls.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($polls)): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all'): ?>
      Pro zvolený filtr tu teď nejsou žádné ankety.
    <?php else: ?>
      Zatím tu nejsou žádné ankety. <a href="polls_form.php?redirect=<?= urlencode($currentListUrl) ?>">Přidat první anketu</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <?= bulkActions('polls', $currentListUrl, 'Hromadné akce s anketami', 'anketa', false) ?>
  <table>
    <caption>Přehled anket</caption>
    <thead>
      <tr>
        <th scope="col"><input type="checkbox" id="check-all" aria-label="Vybrat vše"></th>
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
        <td><input type="checkbox" name="ids[]" value="<?= (int)$poll['id'] ?>" form="bulk-form" aria-label="Vybrat <?= h((string)$poll['question']) ?>"></td>
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
          <a href="polls_form.php?id=<?= (int)$poll['id'] ?>&amp;redirect=<?= urlencode($currentListUrl) ?>" class="btn">Upravit</a>
          <?php if ($state !== 'scheduled'): ?>
            <a href="<?= h(pollPublicPath($poll)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
          <?php endif; ?>
          <form action="polls_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$poll['id'] ?>">
            <input type="hidden" name="redirect" value="<?= h($currentListUrl) ?>">
            <button type="submit" class="btn btn-danger" data-confirm="Smazat anketu včetně všech hlasů?">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= renderPager($page, $pages, $pagerBaseUrl, 'Stránkování anket', 'Starší stránka', 'Novější stránka') ?>
  <?= bulkCheckboxJs() ?>
<?php endif; ?>

<?php adminFooter(); ?>
