<?php
require_once __DIR__ . '/layout.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu rezervací nemáte potřebné oprávnění.');

$pdo = db_connect();
autoCompleteBookings();

$q = trim($_GET['q'] ?? '');
$filterResource = inputInt('get', 'resource_id');
$filterStatus = in_array($_GET['status'] ?? '', ['pending', 'confirmed', 'cancelled', 'rejected', 'completed', 'no_show'], true)
    ? (string)$_GET['status']
    : '';
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(r.name LIKE ? OR b.guest_name LIKE ? OR b.guest_email LIKE ? OR b.guest_phone LIKE ? OR b.notes LIKE ? OR b.admin_note LIKE ? OR CONCAT_WS(' ', u.first_name, u.last_name) LIKE ? OR u.email LIKE ?)";
    for ($i = 0; $i < 8; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($filterResource !== null) {
    $where[] = 'b.resource_id = ?';
    $params[] = $filterResource;
}

if ($filterStatus !== '') {
    $where[] = 'b.status = ?';
    $params[] = $filterStatus;
}

if ($dateFrom !== '') {
    $where[] = 'b.booking_date >= ?';
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'b.booking_date <= ?';
    $params[] = $dateTo;
}

$defaultFilter = ($q === '' && $filterStatus === '' && $dateFrom === '' && $dateTo === '' && $filterResource === null);
if ($defaultFilter) {
    $where[] = "(b.status IN ('pending','confirmed','completed','no_show') OR (b.status IN ('cancelled','rejected') AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)))";
}

$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$perPage = 20;
$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM cms_res_bookings b
     LEFT JOIN cms_res_resources r ON r.id = b.resource_id
     LEFT JOIN cms_users u ON u.id = b.user_id
     {$whereSql}"
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = max(1, min($pages, (int)($_GET['strana'] ?? 1)));
$offset = ($page - 1) * $perPage;

$rowsStmt = $pdo->prepare(
    "SELECT b.*, r.name AS resource_name, r.slug AS resource_slug,
            COALESCE(NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)), ''), u.email) AS user_name
     FROM cms_res_bookings b
     LEFT JOIN cms_res_resources r ON r.id = b.resource_id
     LEFT JOIN cms_users u ON u.id = b.user_id
     {$whereSql}
     ORDER BY b.booking_date DESC, b.start_time DESC, b.id DESC
     LIMIT ? OFFSET ?"
);
$rowsParams = $params;
$rowsParams[] = $perPage;
$rowsParams[] = $offset;
$rowsStmt->execute($rowsParams);
$bookings = $rowsStmt->fetchAll();

$resources = $pdo->query(
    "SELECT id, name, slug FROM cms_res_resources ORDER BY is_active DESC, name"
)->fetchAll();

$statusLabels = reservationBookingStatusLabels();

$filterParams = array_filter([
    'q' => $q,
    'resource_id' => $filterResource,
    'status' => $filterStatus,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
], static fn ($value): bool => $value !== '' && $value !== null);
$paginationParams = $filterParams;
$listRedirect = BASE_URL . '/admin/res_bookings.php';
$currentRedirect = $listRedirect . ($paginationParams !== [] ? '?' . http_build_query($paginationParams + ['strana' => $page]) : ($page > 1 ? '?strana=' . $page : ''));
$filterBaseQuery = $filterParams !== [] ? http_build_query($filterParams) : '';

adminHeader('Rezervace');
?>
<?php if (isset($_GET['ok'])): ?>
  <p class="success" role="status">Rezervace byla úspěšně aktualizována.</p>
<?php endif; ?>
<p class="button-row res-bookings-toolbar">
  <a href="res_booking_add.php" class="btn">+ Přidat rezervaci</a>
  <a href="res_resources.php" class="btn">Zdroje rezervací</a>
</p>

<form method="get" aria-labelledby="filter-heading" class="res-bookings-filter">
  <fieldset class="admin-fieldset-card">
    <legend id="filter-heading">Filtrovat rezervace</legend>
    <div class="res-bookings-filter-row">
      <div>
        <label for="q">Hledat</label>
        <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Jméno, e-mail, zdroj nebo poznámka">
      </div>
      <div>
        <label for="resource_id">Zdroj</label>
        <select id="resource_id" name="resource_id" class="res-bookings-filter-select">
          <option value="">– Všechny –</option>
          <?php foreach ($resources as $resource): ?>
            <option value="<?= (int)$resource['id'] ?>"<?= $filterResource === (int)$resource['id'] ? ' selected' : '' ?>><?= h((string)$resource['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="status">Stav</label>
        <select id="status" name="status" class="res-bookings-filter-select">
          <option value="">– Všechny –</option>
          <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>
            <option value="<?= h($statusKey) ?>"<?= $filterStatus === $statusKey ? ' selected' : '' ?>><?= h($statusLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="date_from">Datum od</label>
        <input type="date" id="date_from" name="date_from" value="<?= h($dateFrom) ?>" class="res-bookings-filter-select">
      </div>
      <div>
        <label for="date_to">Datum do</label>
        <input type="date" id="date_to" name="date_to" value="<?= h($dateTo) ?>" class="res-bookings-filter-select">
      </div>
      <div>
        <button type="submit" class="btn">Použít filtr</button>
        <?php if ($filterParams !== []): ?>
          <a href="res_bookings.php" class="btn">Zrušit filtr</a>
        <?php endif; ?>
      </div>
    </div>
  </fieldset>
</form>

<?php if ($defaultFilter):
    $hiddenCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM cms_res_bookings
         WHERE status IN ('cancelled','rejected') AND booking_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
    )->fetchColumn();
    if ($hiddenCount > 0): ?>
  <p class="res-bookings-hidden-note">
    (<?= $hiddenCount ?> starších zrušených nebo zamítnutých rezervací je skryto. Pro zobrazení všech použijte filtr.)
  </p>
<?php endif; endif; ?>

<?php if ($bookings === []): ?>
  <p><?= $filterParams !== [] ? 'Pro zvolený filtr tu teď nejsou žádné rezervace.' : 'Zatím tu nejsou žádné rezervace.' ?></p>
<?php else: ?>
  <table>
    <caption>Přehled rezervací (celkem <?= $total ?>)</caption>
    <thead>
      <tr>
        <th scope="col">ID</th>
        <th scope="col">Zdroj</th>
        <th scope="col">Zákazník</th>
        <th scope="col">Termín</th>
        <th scope="col">Počet osob</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($bookings as $booking): ?>
      <?php
      $customerLabel = trim((string)($booking['user_name'] ?? ''));
        if ($customerLabel === '') {
            $customerLabel = trim((string)($booking['guest_name'] ?? ''));
        }
        if ($customerLabel === '') {
            $customerLabel = '–';
        }

        $resourcePublicPath = '';
        if (!empty($booking['resource_slug'])) {
            $resourcePublicPath = reservationResourcePublicPath($booking);
        }

        $detailHref = 'res_booking_detail.php?id=' . (int)$booking['id'];
        $detailHref .= '&redirect=' . rawurlencode($currentRedirect);
        $statusKey = preg_replace('/[^a-z0-9_-]/', '', (string)($booking['status'] ?? '')) ?: 'unknown';
        ?>
      <tr>
        <td><?= (int)$booking['id'] ?></td>
        <td>
          <?= h((string)($booking['resource_name'] ?? '–')) ?>
          <?php if ($resourcePublicPath !== ''): ?>
            <br><small><a href="<?= h($resourcePublicPath) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a></small>
          <?php endif; ?>
        </td>
        <td><?= h($customerLabel) ?></td>
        <td>
          <time datetime="<?= h((string)$booking['booking_date']) ?>"><?= h((string)$booking['booking_date']) ?></time>
          <br><small><?= h((string)$booking['start_time']) ?> – <?= h((string)$booking['end_time']) ?></small>
        </td>
        <td><?= (int)$booking['party_size'] ?></td>
        <td>
          <strong class="res-booking-status--<?= h($statusKey) ?>">
            <?= h((string)($statusLabels[$booking['status']] ?? $booking['status'])) ?>
          </strong>
        </td>
        <td class="actions">
          <a href="<?= h($detailHref) ?>" class="btn">Zobrazit detail</a>
          <?php if ($booking['status'] === 'pending'): ?>
            <form action="res_booking_save.php" method="post" class="admin-inline-form">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
            <form action="res_booking_save.php" method="post" class="admin-inline-form" data-confirm="Zamítnout rezervaci?">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
              <button type="submit" class="btn btn-danger">Zamítnout</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <nav aria-labelledby="reservation-bookings-pager-heading">
    <h2 id="reservation-bookings-pager-heading" class="sr-only">Stránkování rezervací</h2>
    <ul class="res-bookings-pager-list">
      <?php if ($page > 1): ?>
        <li><a href="?<?= h($filterBaseQuery !== '' ? $filterBaseQuery . '&amp;' : '') ?>strana=<?= $page - 1 ?>" rel="prev"><span aria-hidden="true">‹</span> Předchozí stránka</a></li>
      <?php endif; ?>
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <li>
          <?php if ($p === $page): ?>
            <span aria-current="page"><strong><?= $p ?></strong></span>
          <?php else: ?>
            <a href="?<?= h($filterBaseQuery !== '' ? $filterBaseQuery . '&amp;' : '') ?>strana=<?= $p ?>"><?= $p ?></a>
          <?php endif; ?>
        </li>
      <?php endfor; ?>
      <?php if ($page < $pages): ?>
        <li><a href="?<?= h($filterBaseQuery !== '' ? $filterBaseQuery . '&amp;' : '') ?>strana=<?= $page + 1 ?>" rel="next">Další stránka <span aria-hidden="true">›</span></a></li>
      <?php endif; ?>
    </ul>
  </nav>
  <?php endif; ?>
<?php endif; ?>

<?php adminFooter(); ?>
