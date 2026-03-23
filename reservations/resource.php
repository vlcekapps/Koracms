<?php
require_once __DIR__ . '/../db.php';
header('Cache-Control: no-cache, no-store, must-revalidate');
checkMaintenanceMode();

if (!isModuleEnabled('reservations')) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: ' . BASE_URL . '/reservations/index.php'); exit;
}

$stmt = $pdo->prepare("SELECT * FROM cms_res_resources WHERE slug = ? AND is_active = 1");
$stmt->execute([$slug]);
$resource = $stmt->fetch();
if (!$resource) {
    header('Location: ' . BASE_URL . '/reservations/index.php'); exit;
}
$resId = (int)$resource['id'];

// Month navigation
$monthParam = $_GET['month'] ?? '';
if (preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $year  = (int)substr($monthParam, 0, 4);
    $month = (int)substr($monthParam, 5, 2);
} else {
    $year  = (int)date('Y');
    $month = (int)date('n');
}
if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
    $year  = (int)date('Y');
    $month = (int)date('n');
}

$firstDay   = new DateTime("{$year}-{$month}-01");
$lastDay    = (clone $firstDay)->modify('last day of this month');
$daysInMonth = (int)$lastDay->format('j');
$today      = new DateTime('today');
$now        = new DateTime();

$maxAdvanceDays = (int)$resource['max_advance_days'];
$maxDate = (clone $today)->modify("+{$maxAdvanceDays} days");

// Load opening hours
$hoursStmt = $pdo->prepare("SELECT * FROM cms_res_hours WHERE resource_id = ? ORDER BY day_of_week");
$hoursStmt->execute([$resId]);
$hoursRows = $hoursStmt->fetchAll();
$hours = [];
foreach ($hoursRows as $hr) {
    $hours[(int)$hr['day_of_week']] = $hr;
}

// Load blocked dates for this month
$blockedStmt = $pdo->prepare(
    "SELECT blocked_date FROM cms_res_blocked WHERE resource_id = ? AND blocked_date BETWEEN ? AND ?"
);
$blockedStmt->execute([$resId, $firstDay->format('Y-m-d'), $lastDay->format('Y-m-d')]);
$blockedDates = array_column($blockedStmt->fetchAll(), 'blocked_date');

// Load bookings for this month
$bookingsStmt = $pdo->prepare(
    "SELECT booking_date, start_time, end_time FROM cms_res_bookings
     WHERE resource_id = ? AND booking_date BETWEEN ? AND ? AND status IN ('pending','confirmed')"
);
$bookingsStmt->execute([$resId, $firstDay->format('Y-m-d'), $lastDay->format('Y-m-d')]);
$bookings = $bookingsStmt->fetchAll();
$bookingsByDate = [];
foreach ($bookings as $bk) {
    $bookingsByDate[$bk['booking_date']][] = $bk;
}

// Load predefined slots if slot_mode = slots
$slotsByDow = [];
if ($resource['slot_mode'] === 'slots') {
    $slotsStmt = $pdo->prepare("SELECT * FROM cms_res_slots WHERE resource_id = ? ORDER BY day_of_week, start_time");
    $slotsStmt->execute([$resId]);
    foreach ($slotsStmt->fetchAll() as $sl) {
        $slotsByDow[(int)$sl['day_of_week']][] = $sl;
    }
}

// Determine day availability
function dayAvailability(string $dateStr, array $resource, array $hours, array $blockedDates,
                         array $bookingsByDate, array $slotsByDow, DateTime $today, DateTime $maxDate): string
{
    $dt  = new DateTime($dateStr);
    $dow = ((int)$dt->format('N')) - 1; // 0=Mon..6=Sun

    if ($dt < $today)                       return 'past';
    if ($dt > $maxDate)                     return 'beyond';
    if (in_array($dateStr, $blockedDates))  return 'blocked';
    if (isset($hours[$dow]) && $hours[$dow]['is_closed']) return 'closed';
    if (!isset($hours[$dow]))               return 'closed';

    $dayBookings = $bookingsByDate[$dateStr] ?? [];

    if ($resource['slot_mode'] === 'slots') {
        $slots = $slotsByDow[$dow] ?? [];
        if (empty($slots)) return 'closed';
        $anyFree = false;
        foreach ($slots as $sl) {
            $booked = 0;
            foreach ($dayBookings as $bk) {
                if ($bk['start_time'] < $sl['end_time'] && $bk['end_time'] > $sl['start_time']) {
                    $booked++;
                }
            }
            if ($booked < (int)$sl['max_bookings']) {
                $anyFree = true;
                break;
            }
        }
        return $anyFree ? 'available' : 'full';
    } else {
        // range / duration: check if ANY time slot is still available
        $maxConcurrent = (int)$resource['max_concurrent'];
        $dayHrs = $hours[$dow] ?? null;
        if (!$dayHrs) return 'closed';

        $openDt  = new DateTime($dateStr . ' ' . substr($dayHrs['open_time'], 0, 5));
        $closeDt = new DateTime($dateStr . ' ' . substr($dayHrs['close_time'], 0, 5));

        if ($resource['slot_mode'] === 'duration') {
            $dur = (int)$resource['slot_duration_min'];
            $cur = clone $openDt;
            while (true) {
                $slotEnd = (clone $cur)->modify("+{$dur} minutes");
                if ($slotEnd > $closeDt) break;
                $overlap = 0;
                $curStr = $cur->format('H:i:s');
                $endStr = $slotEnd->format('H:i:s');
                foreach ($dayBookings as $bk) {
                    if ($bk['start_time'] < $endStr && $bk['end_time'] > $curStr) {
                        $overlap++;
                    }
                }
                if ($overlap < $maxConcurrent) return 'available';
                $cur->modify("+{$dur} minutes");
            }
            return 'full';
        } else {
            // range: at least one 30-min window must be free
            $cur = clone $openDt;
            while ($cur < $closeDt) {
                $windowEnd = (clone $cur)->modify('+30 minutes');
                $curStr = $cur->format('H:i:s');
                $endStr = $windowEnd->format('H:i:s');
                $overlap = 0;
                foreach ($dayBookings as $bk) {
                    if ($bk['start_time'] < $endStr && $bk['end_time'] > $curStr) {
                        $overlap++;
                    }
                }
                if ($overlap < $maxConcurrent) return 'available';
                $cur->modify('+30 minutes');
            }
            return 'full';
        }
    }
}

// Czech day names
$czDays = ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'];
$czDaysFull = ['Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota', 'Neděle'];
$czMonths = [
    '', 'Leden', 'Únor', 'Březen', 'Duben', 'Květen', 'Červen',
    'Červenec', 'Srpen', 'Září', 'Říjen', 'Listopad', 'Prosinec',
];

// Previous/Next month
$prevMonth = (clone $firstDay)->modify('-1 month');
$nextMonth = (clone $firstDay)->modify('+1 month');

// Start day of week (0=Mon)
$startDow = ((int)$firstDay->format('N')) - 1;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => h($resource['name']) . ' – Rezervace – ' . $siteName, 'url' => BASE_URL . '/reservations/resource.php?slug=' . rawurlencode($slug)]) ?>
  <title><?= h($resource['name']) ?> – Rezervace – <?= h($siteName) ?></title>
  <style>
    .skip-link { position: absolute; left: -9999px; }
    .skip-link:focus { left: 1rem; top: 1rem; z-index: 9999;
      background: #fff; padding: .5rem 1rem; border: 2px solid #000; }
    .cal-table { border-collapse: collapse; width: 100%; max-width: 600px; }
    .cal-table th, .cal-table td { border: 1px solid #ddd; padding: .4rem; text-align: center; width: 14.28%; }
    .cal-table thead th { background: #f4f4f4; }
    .cal-day-past { color: #555; background: #ececec; }
    .cal-day-blocked { color: #555; background: #ececec; text-decoration: line-through; }
    .cal-day-closed { color: #555; background: #f0f0f0; }
    .cal-day-beyond { color: #666; background: #f5f5f5; }
    .cal-day-available a { color: #fff; background: #2e7d32; display: block; border-radius: 3px; text-decoration: none; padding: .2rem; }
    .cal-day-available a:hover, .cal-day-available a:focus { background: #1b5e20; outline: 2px solid #000; }
    .cal-day-full { color: #c62828; font-weight: bold; }
    .cal-nav { display: flex; justify-content: space-between; max-width: 600px; margin-bottom: .5rem; }
    .hours-table { border-collapse: collapse; margin-bottom: 1rem; }
    .hours-table th, .hours-table td { border: 1px solid #ddd; padding: .3rem .6rem; text-align: left; }
    .hours-table thead th { background: #f4f4f4; }
    .rules-list { list-style: disc; padding-left: 1.5rem; margin-bottom: 1rem; }
    .rules-list li { margin-bottom: .3rem; }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
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
  <p><a href="<?= h(BASE_URL) ?>/reservations/index.php"><span aria-hidden="true">&larr;</span> Zpět na přehled</a></p>

  <h2><?= h($resource['name']) ?></h2>

  <?php if (($_GET['msg'] ?? '') === 'ok'): ?>
    <p style="background:#e6f4ea;border:1px solid #2e7d32;color:#2e7d32;padding:.6rem 1rem;border-radius:4px" role="status">Rezervace byla úspěšně odeslána. Potvrzení jsme zaslali na váš e-mail.</p>
  <?php endif; ?>

  <?php
  $locStmt = $pdo->prepare(
      "SELECT l.name, l.address FROM cms_res_locations l
       JOIN cms_res_resource_locations rl ON rl.location_id = l.id
       WHERE rl.resource_id = ? ORDER BY l.name"
  );
  $locStmt->execute([$resId]);
  $resLocations = $locStmt->fetchAll();
  if (!empty($resLocations)):
  ?>
    <p><strong><?= count($resLocations) === 1 ? 'Místo' : 'Místa' ?>:</strong>
      <?php foreach ($resLocations as $i => $loc): ?>
        <?= h($loc['name']) ?><?php if ($loc['address'] !== '' && $loc['address'] !== null): ?> <span style="color:#666">(<?= h($loc['address']) ?>)</span><?php endif; ?><?= $i < count($resLocations) - 1 ? ', ' : '' ?>
      <?php endforeach; ?>
    </p>
  <?php endif; ?>

  <?php if ($resource['description'] !== '' && $resource['description'] !== null): ?>
    <section aria-label="Popis">
      <?= renderContent($resource['description']) ?>
    </section>
  <?php endif; ?>

  <!-- Opening hours -->
  <section aria-labelledby="hours-heading">
    <h3 id="hours-heading">Otevírací doba</h3>
    <table class="hours-table" aria-labelledby="hours-heading">
      <thead>
        <tr>
          <th scope="col">Den</th>
          <th scope="col">Otevřeno</th>
        </tr>
      </thead>
      <tbody>
        <?php for ($d = 0; $d < 7; $d++): ?>
          <tr>
            <th scope="row"><?= h($czDaysFull[$d]) ?></th>
            <td>
              <?php if (isset($hours[$d]) && !$hours[$d]['is_closed']): ?>
                <?= h(substr($hours[$d]['open_time'], 0, 5)) ?> – <?= h(substr($hours[$d]['close_time'], 0, 5)) ?>
              <?php else: ?>
                <span style="color:#666">Zavřeno</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </section>

  <!-- Booking rules -->
  <section aria-labelledby="rules-heading">
    <h3 id="rules-heading">Pravidla rezervací</h3>
    <ul class="rules-list">
      <?php if ((int)$resource['min_advance_hours'] > 0): ?>
        <li>Rezervaci je třeba vytvořit nejpozději <?= (int)$resource['min_advance_hours'] ?> hodin před začátkem</li>
      <?php endif; ?>
      <?php if ($maxAdvanceDays > 0): ?>
        <li>Rezervovat lze nejvýše <?= $maxAdvanceDays ?> dní dopředu</li>
      <?php endif; ?>
      <?php if ((int)$resource['cancellation_hours'] > 0): ?>
        <li>Zrušení možné nejpozději <?= (int)$resource['cancellation_hours'] ?> hodin předem</li>
      <?php else: ?>
        <li>Zrušení možné kdykoli před začátkem</li>
      <?php endif; ?>
      <?php if ((int)$resource['capacity'] > 0): ?>
        <li>Kapacita: <?= (int)$resource['capacity'] ?> osob</li>
      <?php endif; ?>
      <?php if ((int)$resource['requires_approval']): ?>
        <li>Rezervace vyžaduje schválení správcem</li>
      <?php endif; ?>
      <?php if (!empty($resource['allow_guests'])): ?>
        <li>Registrace na webu není vyžadována – rezervovat lze i bez účtu jako host</li>
      <?php else: ?>
        <li>Pro rezervaci je nutná <a href="<?= h(BASE_URL) ?>/register.php">registrace na webu</a> a přihlášení</li>
      <?php endif; ?>
    </ul>
  </section>

  <!-- Calendar -->
  <section aria-labelledby="cal-heading">
    <h3 id="cal-heading">Kalendář – <?= h($czMonths[$month]) ?> <?= $year ?></h3>

    <nav class="cal-nav" aria-label="Navigace v kalendáři">
      <a href="<?= h(BASE_URL) ?>/reservations/resource.php?slug=<?= rawurlencode($slug) ?>&amp;month=<?= $prevMonth->format('Y-m') ?>#cal-heading"><span aria-hidden="true">&larr;</span> <?= h($czMonths[(int)$prevMonth->format('n')]) ?></a>
      <a href="<?= h(BASE_URL) ?>/reservations/resource.php?slug=<?= rawurlencode($slug) ?>&amp;month=<?= $nextMonth->format('Y-m') ?>#cal-heading"><?= h($czMonths[(int)$nextMonth->format('n')]) ?> <span aria-hidden="true">&rarr;</span></a>
    </nav>

    <table class="cal-table" aria-label="Kalendář rezervací na <?= h($czMonths[$month]) ?> <?= $year ?>">
      <thead>
        <tr>
          <?php foreach ($czDays as $dayLabel): ?>
            <th scope="col" abbr="<?= h($dayLabel) ?>"><?= h($dayLabel) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <tr>
        <?php
        // Empty cells before first day
        for ($i = 0; $i < $startDow; $i++) {
            echo '<td></td>';
        }
        $cellCount = $startDow;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $status = dayAvailability($dateStr, $resource, $hours, $blockedDates, $bookingsByDate, $slotsByDow, $today, $maxDate);

            switch ($status) {
                case 'past':
                    echo '<td class="cal-day-past">' . $day . ' <span class="sr-only">– minulost</span></td>';
                    break;
                case 'blocked':
                    echo '<td class="cal-day-blocked">' . $day . ' <span class="sr-only">– blokováno</span></td>';
                    break;
                case 'closed':
                    echo '<td class="cal-day-closed">' . $day . ' <span class="sr-only">– zavřeno</span></td>';
                    break;
                case 'beyond':
                    echo '<td class="cal-day-beyond">' . $day . ' <span class="sr-only">– mimo rozsah</span></td>';
                    break;
                case 'available':
                    echo '<td class="cal-day-available"><a href="' . h(BASE_URL) . '/reservations/book.php?slug='
                         . rawurlencode($slug) . '&amp;date=' . $dateStr . '" aria-label="' . $day . ' – volné, rezervovat">' . $day . '</a></td>';
                    break;
                case 'full':
                    echo '<td class="cal-day-full">' . $day . ' <span class="sr-only">– obsazeno</span></td>';
                    break;
            }

            $cellCount++;
            if ($cellCount % 7 === 0 && $day < $daysInMonth) {
                echo '</tr><tr>';
            }
        }

        // Fill remaining cells
        $remaining = $cellCount % 7;
        if ($remaining > 0) {
            for ($i = $remaining; $i < 7; $i++) {
                echo '<td></td>';
            }
        }
        ?>
        </tr>
      </tbody>
    </table>
  </section>
</main>

<?= siteFooter() ?>
<script>document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("a11y-live");if(!l)return;var m=document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);m.removeAttribute("role");}});</script>
</body>
</html>
