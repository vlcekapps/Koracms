<?php
require_once __DIR__ . '/../db.php';
header('Cache-Control: no-cache, no-store, must-revalidate');
checkMaintenanceMode();

if (!isModuleEnabled('reservations')) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$currentUrl = BASE_URL . '/reservations/my.php';
requirePublicLogin($currentUrl);

$pdo      = db_connect();
autoCompleteBookings();
$siteName = getSetting('site_name', 'Kora CMS');
$userId   = currentUserId();
$todayStr = date('Y-m-d');
$nowTs    = time();
$msg      = $_GET['msg'] ?? '';

// Status Czech labels
$statusLabels = [
    'pending'   => 'Čeká na schválení',
    'confirmed' => 'Potvrzeno',
    'cancelled' => 'Zrušeno',
    'rejected'  => 'Zamítnuto',
    'completed' => 'Dokončeno',
    'no_show'   => 'Nedostavil se',
];
$statusColors = [
    'pending'   => '#e65100',
    'confirmed' => '#2e7d32',
    'cancelled' => '#666',
    'rejected'  => '#c62828',
    'completed' => '#005fcc',
    'no_show'   => '#8b0000',
];

// Upcoming bookings
$upStmt = $pdo->prepare(
    "SELECT b.*, r.name AS resource_name, r.slug AS resource_slug, r.cancellation_hours
     FROM cms_res_bookings b
     JOIN cms_res_resources r ON r.id = b.resource_id
     WHERE b.user_id = ? AND b.booking_date >= ? AND b.status IN ('pending','confirmed')
     ORDER BY b.booking_date, b.start_time"
);
$upStmt->execute([$userId, $todayStr]);
$upcoming = $upStmt->fetchAll();

// Past bookings
$pastStmt = $pdo->prepare(
    "SELECT b.*, r.name AS resource_name, r.slug AS resource_slug
     FROM cms_res_bookings b
     JOIN cms_res_resources r ON r.id = b.resource_id
     WHERE b.user_id = ? AND b.booking_date < ? AND b.status = 'completed'
     ORDER BY b.booking_date DESC, b.start_time DESC"
);
$pastStmt->execute([$userId, $todayStr]);
$past = $pastStmt->fetchAll();

// Cancelled / Rejected
$cancelledStmt = $pdo->prepare(
    "SELECT b.*, r.name AS resource_name, r.slug AS resource_slug
     FROM cms_res_bookings b
     JOIN cms_res_resources r ON r.id = b.resource_id
     WHERE b.user_id = ? AND b.status IN ('cancelled','rejected','no_show')
     ORDER BY b.booking_date DESC, b.start_time DESC"
);
$cancelledStmt->execute([$userId]);
$cancelled = $cancelledStmt->fetchAll();

function canCancel(array $booking, int $nowTs): bool
{
    $hours     = (int)$booking['cancellation_hours'];
    $bookingTs = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
    return ($bookingTs - $nowTs) >= ($hours * 3600);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => 'Moje rezervace – ' . $siteName]) ?>
  <title>Moje rezervace – <?= h($siteName) ?></title>
  <style>
    .skip-link { position: absolute; left: -9999px; }
    .skip-link:focus { left: 1rem; top: 1rem; z-index: 9999;
      background: #fff; padding: .5rem 1rem; border: 2px solid #000; }
    .res-table-wrap { overflow-x: auto; }
    .res-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
    .res-table th, .res-table td { padding: .45rem .6rem; border: 1px solid #ddd; vertical-align: top; }
    .res-table thead th { background: #f4f4f4; white-space: nowrap; }
    .res-table tbody tr:nth-child(odd) { background: #fafafa; }
    .success-box { background: #e6f4ea; border: 1px solid #2e7d32; color: #2e7d32; padding: .6rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
    .cancel-btn { background: #c62828; color: #fff; border: none; padding: .3rem .8rem; border-radius: 3px; cursor: pointer; font-size: .9rem; }
    .cancel-btn:hover, .cancel-btn:focus { background: #8e0000; outline: 2px solid #000; }
  </style>
</head>
<body>
<?= adminBar() ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('reservations') ?>
</header>

<main id="obsah">
  <div id="a11y-live" role="status" aria-live="polite" aria-atomic="true" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0"></div>
  <h2>Moje rezervace</h2>

  <?php if ($msg === 'ok'): ?>
    <p class="success-box" role="status">Rezervace byla úspěšně vytvořena.</p>
  <?php elseif ($msg === 'cancelled'): ?>
    <p class="success-box" role="status">Rezervace byla zrušena.</p>
  <?php endif; ?>

  <!-- Upcoming -->
  <section aria-labelledby="nadpis-upcoming">
    <h3 id="nadpis-upcoming">Nadcházející rezervace</h3>
    <?php if (empty($upcoming)): ?>
      <p>Žádné nadcházející rezervace.</p>
    <?php else: ?>
      <div class="res-table-wrap">
        <table class="res-table" aria-labelledby="nadpis-upcoming">
          <thead>
            <tr>
              <th scope="col">Prostor</th>
              <th scope="col">Datum</th>
              <th scope="col">Čas</th>
              <th scope="col">Osob</th>
              <th scope="col">Stav</th>
              <th scope="col">Akce</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($upcoming as $bk): ?>
              <tr>
                <td><a href="<?= h(BASE_URL) ?>/reservations/resource.php?slug=<?= rawurlencode($bk['resource_slug']) ?>"><?= h($bk['resource_name']) ?></a></td>
                <td><?= h($bk['booking_date']) ?></td>
                <td><?= h(substr($bk['start_time'], 0, 5)) ?> – <?= h(substr($bk['end_time'], 0, 5)) ?></td>
                <td><?= (int)$bk['party_size'] ?></td>
                <td style="color:<?= $statusColors[$bk['status']] ?? '#000' ?>"><?= h($statusLabels[$bk['status']] ?? $bk['status']) ?></td>
                <td>
                  <?php if (canCancel($bk, $nowTs)): ?>
                    <form method="post" action="<?= h(BASE_URL) ?>/reservations/cancel.php" style="display:inline">
                      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                      <input type="hidden" name="booking_id" value="<?= (int)$bk['id'] ?>">
                      <button type="submit" class="cancel-btn"
                              onclick="return confirm('Opravdu chcete zrušit tuto rezervaci?')">Zrušit</button>
                    </form>
                  <?php else: ?>
                    <span style="color:#999; font-size:.85rem" aria-label="Zrušení již není možné">–</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <!-- Past -->
  <section aria-labelledby="nadpis-past" style="margin-top:1.5rem">
    <h3 id="nadpis-past">Proběhlé rezervace</h3>
    <?php if (empty($past)): ?>
      <p>Žádné proběhlé rezervace.</p>
    <?php else: ?>
      <div class="res-table-wrap">
        <table class="res-table" aria-labelledby="nadpis-past">
          <thead>
            <tr>
              <th scope="col">Prostor</th>
              <th scope="col">Datum</th>
              <th scope="col">Čas</th>
              <th scope="col">Osob</th>
              <th scope="col">Stav</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($past as $bk): ?>
              <tr>
                <td><a href="<?= h(BASE_URL) ?>/reservations/resource.php?slug=<?= rawurlencode($bk['resource_slug']) ?>"><?= h($bk['resource_name']) ?></a></td>
                <td><?= h($bk['booking_date']) ?></td>
                <td><?= h(substr($bk['start_time'], 0, 5)) ?> – <?= h(substr($bk['end_time'], 0, 5)) ?></td>
                <td><?= (int)$bk['party_size'] ?></td>
                <td style="color:<?= $statusColors[$bk['status']] ?? '#000' ?>"><?= h($statusLabels[$bk['status']] ?? $bk['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <!-- Cancelled / Rejected -->
  <section aria-labelledby="nadpis-cancelled" style="margin-top:1.5rem">
    <h3 id="nadpis-cancelled">Zrušené a zamítnuté</h3>
    <?php if (empty($cancelled)): ?>
      <p>Žádné zrušené nebo zamítnuté rezervace.</p>
    <?php else: ?>
      <div class="res-table-wrap">
        <table class="res-table" aria-labelledby="nadpis-cancelled">
          <thead>
            <tr>
              <th scope="col">Prostor</th>
              <th scope="col">Datum</th>
              <th scope="col">Čas</th>
              <th scope="col">Osob</th>
              <th scope="col">Stav</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cancelled as $bk): ?>
              <tr>
                <td><a href="<?= h(BASE_URL) ?>/reservations/resource.php?slug=<?= rawurlencode($bk['resource_slug']) ?>"><?= h($bk['resource_name']) ?></a></td>
                <td><?= h($bk['booking_date']) ?></td>
                <td><?= h(substr($bk['start_time'], 0, 5)) ?> – <?= h(substr($bk['end_time'], 0, 5)) ?></td>
                <td><?= (int)$bk['party_size'] ?></td>
                <td style="color:<?= $statusColors[$bk['status']] ?? '#000' ?>"><?= h($statusLabels[$bk['status']] ?? $bk['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <p style="margin-top:1.5rem"><a href="<?= h(BASE_URL) ?>/reservations/index.php"><span aria-hidden="true">&larr;</span> Nová rezervace</a></p>
</main>

<?= siteFooter() ?>
<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("a11y-live");if(!l)return;var m=document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);m.removeAttribute("role");}});</script>
</body>
</html>
