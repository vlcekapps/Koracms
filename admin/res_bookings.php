<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
autoCompleteBookings();

// ── Filtry ──
$filterResource = inputInt('get', 'resource_id');
$filterStatus   = in_array($_GET['status'] ?? '', ['pending','confirmed','cancelled','rejected','completed','no_show'])
                  ? $_GET['status'] : '';
$dateFrom       = trim($_GET['date_from'] ?? '');
$dateTo         = trim($_GET['date_to'] ?? '');

// ── Rychlé schválení / zamítnutí ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $bookingId = inputInt('post', 'booking_id');
    $action    = $_POST['action'] ?? '';

    if ($bookingId !== null && in_array($action, ['approve', 'reject'], true)) {
        $stmtB = $pdo->prepare(
            "SELECT b.*, r.name AS resource_name
             FROM cms_res_bookings b
             LEFT JOIN cms_res_resources r ON r.id = b.resource_id
             WHERE b.id = ?"
        );
        $stmtB->execute([$bookingId]);
        $bk = $stmtB->fetch();

        if ($bk && $bk['status'] === 'pending') {
            $newStatus = ($action === 'approve') ? 'confirmed' : 'rejected';
            $pdo->prepare("UPDATE cms_res_bookings SET status = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newStatus, $bookingId]);
            logAction('booking_' . $action, "id={$bookingId}");

            // Notifikace uživateli
            $email = $bk['guest_email'];
            if (!$email && $bk['user_id']) {
                $stmtU = $pdo->prepare("SELECT email FROM cms_users WHERE id = ?");
                $stmtU->execute([$bk['user_id']]);
                $uRow = $stmtU->fetch();
                if ($uRow) $email = $uRow['email'];
            }
            if ($email) {
                $statusLabel = ($newStatus === 'confirmed') ? 'potvrzena' : 'zamítnuta';
                $subject = 'Rezervace ' . $statusLabel . ' – ' . $bk['resource_name'];
                $body  = "Vaše rezervace byla " . $statusLabel . ".\n\n";
                $body .= "Zdroj: " . $bk['resource_name'] . "\n";
                $body .= "Datum: " . $bk['booking_date'] . "\n";
                $body .= "Čas: " . $bk['start_time'] . ' – ' . $bk['end_time'] . "\n";
                sendMail($email, $subject, $body);
            }
        }
    }

    // Redirect zpět se zachováním filtrů
    $qs = http_build_query(array_filter([
        'resource_id' => $filterResource,
        'status'      => $filterStatus,
        'date_from'   => $dateFrom,
        'date_to'     => $dateTo,
        'strana'      => $_GET['strana'] ?? '',
    ], fn($v) => $v !== '' && $v !== null));
    header('Location: res_bookings.php' . ($qs ? '?' . $qs : ''));
    exit;
}

// ── WHERE podmínky ──
$where  = [];
$params = [];

if ($filterResource !== null) {
    $where[]  = 'b.resource_id = ?';
    $params[] = $filterResource;
}
if ($filterStatus !== '') {
    $where[]  = 'b.status = ?';
    $params[] = $filterStatus;
}
if ($dateFrom !== '') {
    $where[]  = 'b.booking_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[]  = 'b.booking_date <= ?';
    $params[] = $dateTo;
}

// Výchozí pohled: skrýt staré zrušené/zamítnuté (>30 dní)
$defaultFilter = ($filterStatus === '' && $dateFrom === '' && $dateTo === '');
if ($defaultFilter) {
    $where[] = "(b.status IN ('pending','confirmed','completed','no_show') OR (b.status IN ('cancelled','rejected') AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)))";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Stránkování ──
$perPage = 20;
$stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM cms_res_bookings b {$whereSql}");
$stmtCnt->execute($params);
$total  = (int)$stmtCnt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$page   = max(1, min($pages, (int)($_GET['strana'] ?? 1)));
$offset = ($page - 1) * $perPage;

// ── Načtení rezervací ──
$sql = "SELECT b.*, r.name AS resource_name,
               COALESCE(NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS user_name
        FROM cms_res_bookings b
        LEFT JOIN cms_res_resources r ON r.id = b.resource_id
        LEFT JOIN cms_users u ON u.id = b.user_id
        {$whereSql}
        ORDER BY b.booking_date DESC, b.start_time DESC
        LIMIT ? OFFSET ?";
$fetchParams   = $params;
$fetchParams[] = $perPage;
$fetchParams[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($fetchParams);
$bookings = $stmt->fetchAll();

// ── Zdroje pro filtrový dropdown ──
$resources = $pdo->query("SELECT id, name FROM cms_res_resources ORDER BY name")->fetchAll();

// ── Štítky stavů ──
$statusLabels = [
    'pending'   => 'Čeká na schválení',
    'confirmed' => 'Potvrzená',
    'cancelled' => 'Zrušená',
    'rejected'  => 'Zamítnutá',
    'completed' => 'Dokončená',
    'no_show'   => 'Nedostavil se',
];
$statusColors = [
    'pending'   => '#c60',
    'confirmed' => '#060',
    'cancelled' => '#999',
    'rejected'  => '#c00',
    'completed' => '#005fcc',
    'no_show'   => '#8b0000',
];

adminHeader('Rezervace');

// Query string pro stránkování (zachová filtry)
$filterQs = http_build_query(array_filter([
    'resource_id' => $filterResource,
    'status'      => $filterStatus,
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
], fn($v) => $v !== '' && $v !== null));
?>

<p><a href="res_booking_add.php" class="btn">+ Přidat rezervaci</a></p>

<form method="get" aria-labelledby="filter-heading" style="margin-bottom:1rem">
  <fieldset style="border:1px solid #ddd;padding:.5rem 1rem">
    <legend id="filter-heading" style="font-weight:bold">Filtrovat rezervace</legend>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
      <div>
        <label for="resource_id">Zdroj</label>
        <select id="resource_id" name="resource_id" style="width:auto">
          <option value="">– Všechny –</option>
          <?php foreach ($resources as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= $filterResource === (int)$r['id'] ? 'selected' : '' ?>><?= h($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="status">Stav</label>
        <select id="status" name="status" style="width:auto">
          <option value="">– Všechny –</option>
          <?php foreach ($statusLabels as $key => $label): ?>
            <option value="<?= h($key) ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="date_from">Datum od</label>
        <input type="date" id="date_from" name="date_from" value="<?= h($dateFrom) ?>" style="width:auto">
      </div>
      <div>
        <label for="date_to">Datum do</label>
        <input type="date" id="date_to" name="date_to" value="<?= h($dateTo) ?>" style="width:auto">
      </div>
      <div>
        <button type="submit" class="btn">Filtrovat</button>
        <?php if ($filterResource || $filterStatus !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
          <a href="res_bookings.php" class="btn" style="margin-left:.25rem">Zrušit filtr</a>
        <?php endif; ?>
      </div>
    </div>
  </fieldset>
</form>
<?php if ($defaultFilter):
  $hiddenCount = (int)$pdo->query(
      "SELECT COUNT(*) FROM cms_res_bookings WHERE status IN ('cancelled','rejected') AND booking_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
  )->fetchColumn();
  if ($hiddenCount > 0): ?>
  <p style="color:#666;font-size:.85rem;margin-top:-.5rem;margin-bottom:1rem">(<?= $hiddenCount ?> starších zrušených/zamítnutých rezervací je skryto. Pro zobrazení všech použijte filtr.)</p>
<?php endif; endif; ?>

<?php if (empty($bookings)): ?>
  <p>Žádné rezervace<?= ($filterResource || $filterStatus !== '' || $dateFrom !== '' || $dateTo !== '') ? ' odpovídající filtru.' : '.' ?></p>
<?php else: ?>
  <table>
    <caption>Rezervace (celkem <?= $total ?>)</caption>
    <thead>
      <tr>
        <th scope="col">ID</th>
        <th scope="col">Zdroj</th>
        <th scope="col">Uživatel</th>
        <th scope="col">Datum</th>
        <th scope="col">Čas</th>
        <th scope="col">Počet osob</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($bookings as $b): ?>
      <tr>
        <td><?= (int)$b['id'] ?></td>
        <td><?= h($b['resource_name'] ?? '–') ?></td>
        <td><?= h($b['user_name'] ?? $b['guest_name'] ?? '–') ?></td>
        <td><time datetime="<?= h($b['booking_date']) ?>"><?= h($b['booking_date']) ?></time></td>
        <td><?= h($b['start_time']) ?> – <?= h($b['end_time']) ?></td>
        <td><?= (int)$b['party_size'] ?></td>
        <td>
          <strong style="color:<?= $statusColors[$b['status']] ?? '#333' ?>">
            <?= h($statusLabels[$b['status']] ?? $b['status']) ?>
          </strong>
        </td>
        <td class="actions">
          <a href="res_booking_detail.php?id=<?= (int)$b['id'] ?>" class="btn">Detail</a>
          <?php if ($b['status'] === 'pending'): ?>
            <form action="res_bookings.php<?= $filterQs ? '?' . $filterQs : '' ?>" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <button type="submit" class="btn" style="background:#060;color:#fff">Schválit</button>
            </form>
            <form action="res_bookings.php<?= $filterQs ? '?' . $filterQs : '' ?>" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
              <input type="hidden" name="action" value="reject">
              <button type="submit" class="btn btn-danger"
                      onclick="return confirm('Zamítnout rezervaci?')">Zamítnout</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <nav aria-label="Stránkování rezervací">
    <ul style="list-style:none; display:flex; gap:.5rem; padding:0; margin-top:1rem">
      <?php if ($page > 1): ?>
        <li><a href="?<?= $filterQs ? $filterQs . '&amp;' : '' ?>strana=<?= $page - 1 ?>" rel="prev"><span aria-hidden="true">&#8249;</span> Předchozí</a></li>
      <?php endif; ?>
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <li>
          <?php if ($p === $page): ?>
            <span aria-current="page"><strong><?= $p ?></strong></span>
          <?php else: ?>
            <a href="?<?= $filterQs ? $filterQs . '&amp;' : '' ?>strana=<?= $p ?>"><?= $p ?></a>
          <?php endif; ?>
        </li>
      <?php endfor; ?>
      <?php if ($page < $pages): ?>
        <li><a href="?<?= $filterQs ? $filterQs . '&amp;' : '' ?>strana=<?= $page + 1 ?>" rel="next">Další <span aria-hidden="true">&#8250;</span></a></li>
      <?php endif; ?>
    </ul>
  </nav>
  <?php endif; ?>

<?php endif; ?>

<?php adminFooter(); ?>
