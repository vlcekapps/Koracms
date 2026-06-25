<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

if (!isModuleEnabled('statistics')) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$pdo = db_connect();
statsCleanup();

// ── Filtr: výchozí posledních 30 dní ─────────────────────────────────────────
$dateFrom = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo   = $_GET['to']   ?? date('Y-m-d');

// Validace formátu
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = date('Y-m-d', strtotime('-30 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo   = date('Y-m-d');
}

$fmt = fn (int $n) => number_format($n, 0, ',', "\u{00a0}");

function statisticsLogSectionError(string $section, \Throwable $e): void
{
    koraLog('warning', 'admin statistics section query failed', [
        'section' => $section,
        'exception' => $e,
    ]);
}

// ── Návštěvnost ─────────────────────────────────────────────────────────────
$vs = getVisitorStats();

// Denní data pro graf
$dailyData = [];
$maxViews  = 1;
try {
    // Kombinace: starší dny z agregátů, dnes z raw dat
    $stmtAgg = $pdo->prepare(
        "SELECT stat_date AS d, total_views AS views, unique_visitors AS uv
         FROM cms_stats_daily
         WHERE stat_date >= ? AND stat_date <= ? AND stat_date < CURDATE()
         ORDER BY stat_date"
    );
    $stmtAgg->execute([$dateFrom, $dateTo]);
    foreach ($stmtAgg->fetchAll() as $r) {
        $dailyData[$r['d']] = ['views' => (int)$r['views'], 'uv' => (int)$r['uv']];
    }

    // Dnešní den z raw dat (pokud v rozsahu)
    if ($dateTo >= date('Y-m-d')) {
        $today = date('Y-m-d');
        $todayViews = (int)$pdo->query(
            "SELECT COUNT(*) FROM cms_page_views WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();
        $todayUv = (int)$pdo->query(
            "SELECT COUNT(DISTINCT ip_hash) FROM cms_page_views WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();
        $dailyData[$today] = ['views' => $todayViews, 'uv' => $todayUv];
    }
} catch (\PDOException $e) {
    statisticsLogSectionError('daily_visits', $e);
}

// Doplnit chybějící dny a spočítat maximum
$chartDays = [];
$totalViews = 0;
$totalUv    = 0;
$current = new DateTime($dateFrom);
$end     = new DateTime($dateTo);
while ($current <= $end) {
    $d = $current->format('Y-m-d');
    $v = $dailyData[$d]['views'] ?? 0;
    $u = $dailyData[$d]['uv']    ?? 0;
    $chartDays[] = ['date' => $d, 'label' => $current->format('j.n.'), 'views' => $v, 'uv' => $u];
    $totalViews += $v;
    $totalUv    += $u;
    if ($v > $maxViews) {
        $maxViews = $v;
    }
    $current->modify('+1 day');
}
$avgPerDay = count($chartDays) > 0 ? round($totalViews / count($chartDays), 1) : 0;

// ── Nejčtenější články ──────────────────────────────────────────────────────
$topArticles = [];
if (isModuleEnabled('blog')) {
    try {
        $topArticles = $pdo->query(
            "SELECT id, title, view_count FROM cms_articles
             WHERE status = 'published' AND view_count > 0
             ORDER BY view_count DESC LIMIT 20"
        )->fetchAll();
    } catch (\PDOException $e) {
        statisticsLogSectionError('top_articles', $e);
    }
}

// ── Rezervace ───────────────────────────────────────────────────────────────
$resMonthly   = [];
$resStatus    = [];
$resTopRes    = [];
if (isModuleEnabled('reservations')) {
    try {
        $resMonthly = $pdo->query(
            "SELECT DATE_FORMAT(booking_date, '%Y-%m') AS m, COUNT(*) AS cnt
             FROM cms_res_bookings
             WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY m ORDER BY m"
        )->fetchAll();
    } catch (\PDOException $e) {
        statisticsLogSectionError('reservations_monthly', $e);
    }

    try {
        $resStatus = $pdo->query(
            "SELECT status, COUNT(*) AS cnt FROM cms_res_bookings GROUP BY status ORDER BY cnt DESC"
        )->fetchAll();
    } catch (\PDOException $e) {
        statisticsLogSectionError('reservations_status', $e);
    }

    try {
        $resTopRes = $pdo->query(
            "SELECT r.name, COUNT(b.id) AS cnt
             FROM cms_res_bookings b
             JOIN cms_res_resources r ON r.id = b.resource_id
             GROUP BY b.resource_id ORDER BY cnt DESC LIMIT 10"
        )->fetchAll();
    } catch (\PDOException $e) {
        statisticsLogSectionError('reservations_top_resources', $e);
    }
}

// ── Newsletter ──────────────────────────────────────────────────────────────
$nlConfirmed   = 0;
$nlUnconfirmed = 0;
$nlMonthly     = [];
if (isModuleEnabled('newsletter')) {
    try {
        $nlConfirmed   = (int)$pdo->query("SELECT COUNT(*) FROM cms_subscribers WHERE confirmed = 1")->fetchColumn();
        $nlUnconfirmed = (int)$pdo->query("SELECT COUNT(*) FROM cms_subscribers WHERE confirmed = 0")->fetchColumn();
        $nlMonthly     = $pdo->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS cnt
             FROM cms_subscribers WHERE confirmed = 1
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY m ORDER BY m"
        )->fetchAll();
    } catch (\PDOException $e) {
        statisticsLogSectionError('newsletter', $e);
    }
}

// ── Komentáře ───────────────────────────────────────────────────────────────
$commentStats    = [];
$commentApproved = 0;
$commentPending  = 0;
if (isModuleEnabled('blog')) {
    try {
        $commentApproved = (int)$pdo->query("SELECT COUNT(*) FROM cms_comments WHERE status = 'approved'")->fetchColumn();
        $commentPending  = (int)$pdo->query("SELECT COUNT(*) FROM cms_comments WHERE status = 'pending'")->fetchColumn();
        $commentStats    = $pdo->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS cnt
             FROM cms_comments
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY m ORDER BY m"
        )->fetchAll();
    } catch (\PDOException $e) {
        $commentApproved = (int)$pdo->query("SELECT COUNT(*) FROM cms_comments WHERE is_approved = 1")->fetchColumn();
        $commentPending  = (int)$pdo->query("SELECT COUNT(*) FROM cms_comments WHERE is_approved = 0")->fetchColumn();
        $commentStats    = $pdo->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS cnt
             FROM cms_comments
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY m ORDER BY m"
        )->fetchAll();
    }
}

// ── Kontakt ─────────────────────────────────────────────────────────────────
$contactStats = [];
if (isModuleEnabled('contact')) {
    try {
        $contactStats = $pdo->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS cnt
             FROM cms_contact
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY m ORDER BY m"
        )->fetchAll();
    } catch (\PDOException $e) {
        statisticsLogSectionError('contact', $e);
    }
}

// ── Česky pojmenované měsíce ────────────────────────────────────────────────
$czMonths = [
    '01' => 'leden', '02' => 'únor', '03' => 'březen', '04' => 'duben',
    '05' => 'květen', '06' => 'červen', '07' => 'červenec', '08' => 'srpen',
    '09' => 'září', '10' => 'říjen', '11' => 'listopad', '12' => 'prosinec',
];
$fmtMonth = function (string $ym) use ($czMonths): string {
    $parts = explode('-', $ym);
    return ($czMonths[$parts[1]] ?? $parts[1]) . ' ' . $parts[0];
};

// ── Stavové popisy rezervací ────────────────────────────────────────────────
$statusLabels = [
    'pending'   => 'Čekající',
    'confirmed' => 'Potvrzené',
    'completed' => 'Dokončené',
    'cancelled' => 'Zrušené',
    'rejected'  => 'Zamítnuté',
    'no_show'   => 'Nedostavení se',
];

adminHeader('Statistiky');
?>

<form method="get" class="admin-filter-form">
  <fieldset class="admin-filter-fieldset admin-form-grid admin-form-grid--end">
    <legend class="sr-only">Období pro statistiky</legend>
    <div class="admin-form-grid__cell">
      <label for="from" class="admin-compact-label">Od</label>
      <input type="date" id="from" name="from" value="<?= h($dateFrom) ?>"
             class="admin-input-auto">
    </div>
    <div class="admin-form-grid__cell">
      <label for="to" class="admin-compact-label">Do</label>
      <input type="date" id="to" name="to" value="<?= h($dateTo) ?>"
             class="admin-input-auto">
    </div>
    <button type="submit" class="btn">Zobrazit</button>
  </fieldset>
</form>

<!-- ── 1. Návštěvnost ──────────────────────────────────────────────────────── -->
<section aria-labelledby="sec-visitors">
  <h2 id="sec-visitors">Návštěvnost</h2>

  <div class="admin-summary-grid admin-stack-sm" role="list" aria-labelledby="sec-visitors">
    <div class="admin-summary-card" role="listitem">
      <div class="admin-summary-card__value"><?= $fmt($vs['online']) ?></div>
      <div class="admin-summary-card__heading">Online</div>
    </div>
    <div class="admin-summary-card" role="listitem">
      <div class="admin-summary-card__value"><?= $fmt($vs['today']) ?></div>
      <div class="admin-summary-card__heading">Dnes</div>
    </div>
    <div class="admin-summary-card" role="listitem">
      <div class="admin-summary-card__value"><?= $fmt($vs['month']) ?></div>
      <div class="admin-summary-card__heading">Tento měsíc</div>
    </div>
    <div class="admin-summary-card" role="listitem">
      <div class="admin-summary-card__value"><?= $fmt($vs['total']) ?></div>
      <div class="admin-summary-card__heading">Celkem</div>
    </div>
  </div>

  <p>Zobrazené období: <strong><?= h($dateFrom) ?></strong> – <strong><?= h($dateTo) ?></strong>
    · Celkem zobrazení: <strong><?= $fmt($totalViews) ?></strong>
    · Unikátní návštěvníci: <strong><?= $fmt($totalUv) ?></strong>
    · Průměr/den: <strong><?= $avgPerDay ?></strong>
  </p>

  <?php if (!empty($chartDays)): ?>
  <figure class="admin-stat-chart">
    <figcaption>Denní návštěvnost</figcaption>
    <ol class="admin-stat-bars">
      <?php foreach ($chartDays as $dayIndex => $d):
          $dayLabelId = 'statistics-day-label-' . $dayIndex;
          $dayValueId = 'statistics-day-value-' . $dayIndex;
          ?>
        <li class="admin-stat-bar">
          <span id="<?= h($dayLabelId) ?>" class="admin-stat-bar__label"><?= h($d['label']) ?></span>
          <progress
            class="admin-stat-progress"
            value="<?= (int)$d['views'] ?>"
            max="<?= (int)$maxViews ?>"
            aria-labelledby="<?= h($dayLabelId . ' ' . $dayValueId) ?>"
          ><?= (int)$d['views'] ?></progress>
          <span id="<?= h($dayValueId) ?>" class="admin-stat-bar__value">
            <?= $fmt((int)$d['views']) ?> zobrazení, <?= $fmt((int)$d['uv']) ?> unikátních
          </span>
        </li>
      <?php endforeach; ?>
    </ol>
    <table class="sr-only">
      <caption>Denní návštěvnost za období <?= h($dateFrom) ?> – <?= h($dateTo) ?></caption>
      <thead><tr><th scope="col">Den</th><th scope="col">Zobrazení</th><th scope="col">Unikátní</th></tr></thead>
      <tbody>
      <?php foreach ($chartDays as $d): ?>
        <tr><td><?= h($d['label']) ?></td><td><?= $d['views'] ?></td><td><?= $d['uv'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </figure>
  <?php endif; ?>
</section>

<!-- ── 2. Nejčtenější články ────────────────────────────────────────────────── -->
<?php if (isModuleEnabled('blog') && !empty($topArticles)): ?>
<section aria-labelledby="sec-articles">
  <h2 id="sec-articles">Nejčtenější články</h2>
  <table>
    <thead>
      <tr><th scope="col">#</th><th scope="col">Článek</th><th scope="col">Zobrazení</th></tr>
    </thead>
    <tbody>
    <?php foreach ($topArticles as $i => $a): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><a href="blog_form.php?id=<?= (int)$a['id'] ?>"><?= h($a['title']) ?></a></td>
        <td><?= $fmt((int)$a['view_count']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<!-- ── 3. Rezervace ────────────────────────────────────────────────────────── -->
<?php if (isModuleEnabled('reservations') && (!empty($resMonthly) || !empty($resStatus) || !empty($resTopRes))): ?>
<section aria-labelledby="sec-reservations">
  <h2 id="sec-reservations">Rezervace</h2>

  <?php if (!empty($resStatus)): ?>
  <h3>Stav rezervací</h3>
  <table>
    <thead><tr><th scope="col">Stav</th><th scope="col">Počet</th></tr></thead>
    <tbody>
    <?php foreach ($resStatus as $r): ?>
      <tr><td><?= h($statusLabels[$r['status']] ?? $r['status']) ?></td><td><?= $fmt((int)$r['cnt']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if (!empty($resMonthly)):
      $resMax = max(array_column($resMonthly, 'cnt'));
      if ($resMax < 1) {
          $resMax = 1;
      }
      ?>
  <h3>Rezervace za posledních 6 měsíců</h3>
  <figure class="admin-stat-chart admin-stat-chart--compact">
    <figcaption>Měsíční rezervace</figcaption>
    <ol class="admin-stat-bars">
      <?php foreach ($resMonthly as $reservationIndex => $r):
          $reservationLabelId = 'statistics-reservation-label-' . $reservationIndex;
          $reservationValueId = 'statistics-reservation-value-' . $reservationIndex;
          ?>
        <li class="admin-stat-bar">
          <span id="<?= h($reservationLabelId) ?>" class="admin-stat-bar__label"><?= h($fmtMonth((string)$r['m'])) ?></span>
          <progress
            class="admin-stat-progress"
            value="<?= (int)$r['cnt'] ?>"
            max="<?= (int)$resMax ?>"
            aria-labelledby="<?= h($reservationLabelId . ' ' . $reservationValueId) ?>"
          ><?= (int)$r['cnt'] ?></progress>
          <span id="<?= h($reservationValueId) ?>" class="admin-stat-bar__value"><?= $fmt((int)$r['cnt']) ?><span class="sr-only"> rezervací</span></span>
        </li>
      <?php endforeach; ?>
    </ol>
    <table class="sr-only">
      <caption>Rezervace za posledních 6 měsíců</caption>
      <thead><tr><th scope="col">Měsíc</th><th scope="col">Počet</th></tr></thead>
      <tbody>
      <?php foreach ($resMonthly as $r): ?>
        <tr><td><?= h($fmtMonth($r['m'])) ?></td><td><?= $r['cnt'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </figure>
  <?php endif; ?>

  <?php if (!empty($resTopRes)): ?>
  <h3>Nejžádanější zdroje</h3>
  <table>
    <thead><tr><th scope="col">Zdroj</th><th scope="col">Rezervací</th></tr></thead>
    <tbody>
    <?php foreach ($resTopRes as $r): ?>
      <tr><td><?= h($r['name']) ?></td><td><?= $fmt((int)$r['cnt']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>
<?php endif; ?>

<!-- ── 4. Newsletter ───────────────────────────────────────────────────────── -->
<?php if (isModuleEnabled('newsletter') && ($nlConfirmed > 0 || $nlUnconfirmed > 0)): ?>
<section aria-labelledby="sec-newsletter">
  <h2 id="sec-newsletter">Newsletter</h2>
  <p>Potvrzení odběratelé: <strong><?= $fmt($nlConfirmed) ?></strong>
     · Nepotvrzení: <strong><?= $fmt($nlUnconfirmed) ?></strong></p>

  <?php if (!empty($nlMonthly)):
      $nlMax = max(array_column($nlMonthly, 'cnt'));
      if ($nlMax < 1) {
          $nlMax = 1;
      }
      ?>
  <h3>Noví odběratelé za posledních 6 měsíců</h3>
  <figure class="admin-stat-chart admin-stat-chart--compact">
    <figcaption>Noví odběratelé newsletteru</figcaption>
    <ol class="admin-stat-bars">
      <?php foreach ($nlMonthly as $newsletterIndex => $r):
          $newsletterLabelId = 'statistics-newsletter-label-' . $newsletterIndex;
          $newsletterValueId = 'statistics-newsletter-value-' . $newsletterIndex;
          ?>
        <li class="admin-stat-bar">
          <span id="<?= h($newsletterLabelId) ?>" class="admin-stat-bar__label"><?= h($fmtMonth((string)$r['m'])) ?></span>
          <progress
            class="admin-stat-progress"
            value="<?= (int)$r['cnt'] ?>"
            max="<?= (int)$nlMax ?>"
            aria-labelledby="<?= h($newsletterLabelId . ' ' . $newsletterValueId) ?>"
          ><?= (int)$r['cnt'] ?></progress>
          <span id="<?= h($newsletterValueId) ?>" class="admin-stat-bar__value"><?= $fmt((int)$r['cnt']) ?><span class="sr-only"> nových odběratelů</span></span>
        </li>
      <?php endforeach; ?>
    </ol>
    <table class="sr-only">
      <caption>Noví odběratelé za posledních 6 měsíců</caption>
      <thead><tr><th scope="col">Měsíc</th><th scope="col">Počet</th></tr></thead>
      <tbody>
      <?php foreach ($nlMonthly as $r): ?>
        <tr><td><?= h($fmtMonth($r['m'])) ?></td><td><?= $r['cnt'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </figure>
  <?php endif; ?>
</section>
<?php endif; ?>

<!-- ── 5. Komentáře ────────────────────────────────────────────────────────── -->
<?php if (isModuleEnabled('blog') && ($commentApproved > 0 || $commentPending > 0)): ?>
<section aria-labelledby="sec-comments">
  <h2 id="sec-comments">Komentáře</h2>
  <p>Schválené: <strong><?= $fmt($commentApproved) ?></strong>
     · Čekající: <strong><?= $fmt($commentPending) ?></strong></p>

  <?php if (!empty($commentStats)):
      $cmMax = max(array_column($commentStats, 'cnt'));
      if ($cmMax < 1) {
          $cmMax = 1;
      }
      ?>
  <h3>Komentáře za posledních 6 měsíců</h3>
  <figure class="admin-stat-chart admin-stat-chart--compact">
    <figcaption>Aktivita komentářů</figcaption>
    <ol class="admin-stat-bars">
      <?php foreach ($commentStats as $commentIndex => $r):
          $commentLabelId = 'statistics-comment-label-' . $commentIndex;
          $commentValueId = 'statistics-comment-value-' . $commentIndex;
          ?>
        <li class="admin-stat-bar">
          <span id="<?= h($commentLabelId) ?>" class="admin-stat-bar__label"><?= h($fmtMonth((string)$r['m'])) ?></span>
          <progress
            class="admin-stat-progress"
            value="<?= (int)$r['cnt'] ?>"
            max="<?= (int)$cmMax ?>"
            aria-labelledby="<?= h($commentLabelId . ' ' . $commentValueId) ?>"
          ><?= (int)$r['cnt'] ?></progress>
          <span id="<?= h($commentValueId) ?>" class="admin-stat-bar__value"><?= $fmt((int)$r['cnt']) ?><span class="sr-only"> komentářů</span></span>
        </li>
      <?php endforeach; ?>
    </ol>
    <table class="sr-only">
      <caption>Komentáře za posledních 6 měsíců</caption>
      <thead><tr><th scope="col">Měsíc</th><th scope="col">Počet</th></tr></thead>
      <tbody>
      <?php foreach ($commentStats as $r): ?>
        <tr><td><?= h($fmtMonth($r['m'])) ?></td><td><?= $r['cnt'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </figure>
  <?php endif; ?>
</section>
<?php endif; ?>

<!-- ── 6. Kontaktní zprávy ─────────────────────────────────────────────────── -->
<?php if (isModuleEnabled('contact') && !empty($contactStats)):
    $ctMax = max(array_column($contactStats, 'cnt'));
    if ($ctMax < 1) {
        $ctMax = 1;
    }
    ?>
<section aria-labelledby="sec-contact">
  <h2 id="sec-contact">Kontaktní zprávy</h2>
  <h3>Zprávy za posledních 6 měsíců</h3>
  <figure class="admin-stat-chart admin-stat-chart--compact">
    <figcaption>Kontaktní zprávy</figcaption>
    <ol class="admin-stat-bars">
      <?php foreach ($contactStats as $contactIndex => $r):
          $contactLabelId = 'statistics-contact-label-' . $contactIndex;
          $contactValueId = 'statistics-contact-value-' . $contactIndex;
          ?>
        <li class="admin-stat-bar">
          <span id="<?= h($contactLabelId) ?>" class="admin-stat-bar__label"><?= h($fmtMonth((string)$r['m'])) ?></span>
          <progress
            class="admin-stat-progress"
            value="<?= (int)$r['cnt'] ?>"
            max="<?= (int)$ctMax ?>"
            aria-labelledby="<?= h($contactLabelId . ' ' . $contactValueId) ?>"
          ><?= (int)$r['cnt'] ?></progress>
          <span id="<?= h($contactValueId) ?>" class="admin-stat-bar__value"><?= $fmt((int)$r['cnt']) ?><span class="sr-only"> kontaktních zpráv</span></span>
        </li>
      <?php endforeach; ?>
    </ol>
    <table class="sr-only">
      <caption>Kontaktní zprávy za posledních 6 měsíců</caption>
      <thead><tr><th scope="col">Měsíc</th><th scope="col">Počet</th></tr></thead>
      <tbody>
      <?php foreach ($contactStats as $r): ?>
        <tr><td><?= h($fmtMonth($r['m'])) ?></td><td><?= $r['cnt'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </figure>
</section>
<?php endif; ?>

<?php adminFooter(); ?>
