<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('polls')) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$pdo      = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$now      = date('Y-m-d H:i:s');
$pollId   = inputInt('get', 'id');
$archiv   = isset($_GET['archiv']);

// ── IP hash helper ──────────────────────────────────────────────────────────
function pollIpHash(int $pollId): string {
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . '|poll_' . $pollId);
}

// ── Vote POST handler ───────────────────────────────────────────────────────
$voteError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pollId !== null && isset($_POST['vote'])) {
    verifyCsrf();

    if (honeypotTriggered()) {
        // Fake success for bots
        header('Location: ' . BASE_URL . '/polls/index.php?id=' . $pollId . '&voted=1');
        exit;
    }

    rateLimit('poll_vote', 5, 60);

    $optionId = inputInt('post', 'option_id');

    // Validate poll is active
    $stmt = $pdo->prepare(
        "SELECT * FROM cms_polls WHERE id = ? AND status = 'active'
         AND (start_date IS NULL OR start_date <= ?) AND (end_date IS NULL OR end_date > ?)"
    );
    $stmt->execute([$pollId, $now, $now]);
    $votePoll = $stmt->fetch();

    if (!$votePoll) {
        $voteError = 'closed';
    } elseif ($optionId === null) {
        $voteError = 'no_option';
    } else {
        // Validate option belongs to poll
        $stmt = $pdo->prepare("SELECT id FROM cms_poll_options WHERE id = ? AND poll_id = ?");
        $stmt->execute([$optionId, $pollId]);
        if (!$stmt->fetch()) {
            $voteError = 'invalid_option';
        } else {
            $ipHash = pollIpHash($pollId);
            try {
                $pdo->prepare(
                    "INSERT INTO cms_poll_votes (poll_id, option_id, ip_hash) VALUES (?, ?, ?)"
                )->execute([$pollId, $optionId, $ipHash]);
                header('Location: ' . BASE_URL . '/polls/index.php?id=' . $pollId . '&voted=1');
                exit;
            } catch (\PDOException $e) {
                // Duplicate key = already voted
                $voteError = 'already_voted';
            }
        }
    }
}

// ── Load data based on view ─────────────────────────────────────────────────

$pageTitle = 'Ankety';
$poll = null;
$options = [];
$hasVoted = false;
$voted = isset($_GET['voted']);
$polls = [];
$totalPages = 1;
$page = 1;

if ($pollId !== null) {
    // Single poll view
    $stmt = $pdo->prepare("SELECT * FROM cms_polls WHERE id = ?");
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch();

    if (!$poll) {
        header('Location: ' . BASE_URL . '/polls/index.php');
        exit;
    }

    $pageTitle = h($poll['question']) . ' – Ankety';

    // Load options with vote counts
    $stmt = $pdo->prepare(
        "SELECT o.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE option_id = o.id) AS vote_count
         FROM cms_poll_options o WHERE o.poll_id = ? ORDER BY o.sort_order, o.id"
    );
    $stmt->execute([$pollId]);
    $options = $stmt->fetchAll();

    // Check if user already voted
    $ipHash = pollIpHash($pollId);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = ? AND ip_hash = ?");
    $stmt->execute([$pollId, $ipHash]);
    $hasVoted = (int)$stmt->fetchColumn() > 0;

    // Is poll currently active?
    $isActive = $poll['status'] === 'active'
        && ($poll['start_date'] === null || $poll['start_date'] <= $now)
        && ($poll['end_date'] === null || $poll['end_date'] > $now);

} else {
    // List view (active or archive)
    $perPage = 10;
    $page = max(1, (int)($_GET['strana'] ?? 1));

    if ($archiv) {
        $pageTitle = 'Archiv anket';
        $countSql = "SELECT COUNT(*) FROM cms_polls WHERE status = 'closed' OR (end_date IS NOT NULL AND end_date <= :now1)";
        $listSql  = "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
                     FROM cms_polls p WHERE p.status = 'closed' OR (p.end_date IS NOT NULL AND p.end_date <= :now1)
                     ORDER BY p.created_at DESC LIMIT :lim OFFSET :off";
        $countBind = [':now1' => $now];
    } else {
        $countSql = "SELECT COUNT(*) FROM cms_polls WHERE status = 'active'
                     AND (start_date IS NULL OR start_date <= :now1) AND (end_date IS NULL OR end_date > :now2)";
        $listSql  = "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
                     FROM cms_polls p WHERE p.status = 'active'
                     AND (p.start_date IS NULL OR p.start_date <= :now1) AND (p.end_date IS NULL OR p.end_date > :now2)
                     ORDER BY p.created_at DESC LIMIT :lim OFFSET :off";
        $countBind = [':now1' => $now, ':now2' => $now];
    }

    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($countBind);
    $total = (int)$stmtCount->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmtList = $pdo->prepare($listSql);
    foreach ($countBind as $k => $v) {
        $stmtList->bindValue($k, $v);
    }
    $stmtList->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmtList->bindValue(':off', $offset,  PDO::PARAM_INT);
    $stmtList->execute();
    $polls = $stmtList->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta(['title' => $pageTitle . ' – ' . $siteName, 'url' => BASE_URL . '/polls/index.php']) ?>
  <title><?= h($pageTitle) ?> – <?= h($siteName) ?></title>
  <style>
    .poll-card { border: 1px solid #ddd; border-radius: 6px; padding: 1rem 1.2rem; margin-bottom: 1rem; }
    .poll-card h3 { margin: 0 0 .3rem; }
    .poll-meta { font-size: .85rem; color: #666; margin-bottom: .5rem; }

    .poll-results-bar-track { background: #e8e8e8; border-radius: 4px; overflow: hidden; margin: .2rem 0 .5rem; }
    .poll-results-bar-fill  { background: #005fcc; height: 1.2rem; min-width: 0; transition: width .3s; }
    .poll-results-item { margin-bottom: .75rem; }
    .poll-results-label { display: flex; justify-content: space-between; margin-bottom: .15rem; }

    .poll-vote-form fieldset { border: none; padding: 0; margin: 0; }
    .poll-vote-form legend { font-weight: bold; font-size: 1.1rem; margin-bottom: .5rem; }
    .poll-vote-form .option-choice { margin-bottom: .4rem; }
    .poll-vote-form .option-choice label { cursor: pointer; }

    .pager { margin-top: 1rem; }
    .pager a, .pager span { margin-right: .5rem; }

    .vote-success { background: #e6f4ea; border: 1px solid #2e7d32; color: #2e7d32; padding: .6rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
    .vote-error   { background: #fdecea; border: 1px solid #c62828; color: #c62828; padding: .6rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
  </style>
</head>
<body>
<?= adminBar() ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<header>
  <h1><?= h($siteName) ?></h1>
  <?= siteNav('polls') ?>
</header>

<main id="obsah">
  <div id="a11y-live" role="status" aria-live="polite" aria-atomic="true" class="sr-only"></div>

<?php if ($poll !== null): ?>
  <!-- ── Single poll view ── -->
  <h2><?= h($poll['question']) ?></h2>
  <?php if ($poll['description']): ?>
    <p><?= h($poll['description']) ?></p>
  <?php endif; ?>

  <?php if ($voted): ?>
    <p class="vote-success" role="status">Váš hlas byl zaznamenán. Děkujeme!</p>
  <?php endif; ?>

  <?php if ($voteError): ?>
    <p class="vote-error" role="alert">
      <?php
        $errorMessages = [
            'too_many'       => 'Příliš mnoho pokusů, zkuste to prosím později.',
            'closed'         => 'Tato anketa již není aktivní.',
            'no_option'      => 'Vyberte prosím jednu z možností.',
            'invalid_option' => 'Neplatná možnost.',
            'already_voted'  => 'Z této IP adresy již bylo hlasováno.',
        ];
        echo h($errorMessages[$voteError] ?? 'Nastala chyba při hlasování.');
      ?>
    </p>
  <?php endif; ?>

  <?php
    $showForm = isset($isActive) && $isActive && !$hasVoted && !$voted;
    $totalVotes = 0;
    foreach ($options as $o) $totalVotes += (int)$o['vote_count'];
  ?>

  <?php if ($showForm): ?>
    <form method="post" action="<?= h(BASE_URL) ?>/polls/index.php?id=<?= (int)$poll['id'] ?>"
          class="poll-vote-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <?= honeypotField() ?>
      <fieldset>
        <legend><?= h($poll['question']) ?></legend>
        <?php foreach ($options as $i => $o): ?>
          <div class="option-choice">
            <input type="radio" id="opt_<?= (int)$o['id'] ?>" name="option_id"
                   value="<?= (int)$o['id'] ?>" <?= $i === 0 ? 'aria-required="true"' : '' ?>
                   required>
            <label for="opt_<?= (int)$o['id'] ?>"><?= h($o['option_text']) ?></label>
          </div>
        <?php endforeach; ?>
      </fieldset>
      <button type="submit" name="vote" value="1" style="margin-top:.75rem" class="btn">Hlasovat</button>
    </form>
  <?php else: ?>
    <!-- Results -->
    <section aria-label="Výsledky ankety">
      <h3>Výsledky</h3>
      <div role="list" aria-label="Možnosti a hlasy">
        <?php foreach ($options as $o):
          $vc  = (int)$o['vote_count'];
          $pct = $totalVotes > 0 ? round($vc / $totalVotes * 100, 1) : 0;
        ?>
          <div role="listitem" class="poll-results-item">
            <div class="poll-results-label">
              <span><?= h($o['option_text']) ?></span>
              <span><?= $pct ?>&nbsp;% (<?= $vc ?>&nbsp;hlasů)</span>
            </div>
            <div class="poll-results-bar-track" aria-hidden="true">
              <div class="poll-results-bar-fill" style="width:<?= $pct ?>%<?= $pct > 0 ? ';min-width:2px' : '' ?>"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p><strong>Celkem hlasů: <?= $totalVotes ?></strong></p>
    </section>

    <?php if (!isset($isActive) || !$isActive): ?>
      <p style="color:#666"><em>Tato anketa je uzavřena.</em></p>
    <?php elseif ($hasVoted || $voted): ?>
      <p style="color:#666"><em>Již jste hlasoval/a.</em></p>
    <?php endif; ?>
  <?php endif; ?>

  <p style="margin-top:1.5rem"><a href="<?= h(BASE_URL) ?>/polls/index.php"><span aria-hidden="true">←</span> Zpět na přehled anket</a></p>

<?php else: ?>
  <!-- ── List view ── -->
  <h2><?= $archiv ? 'Archiv anket' : 'Ankety' ?></h2>

  <nav aria-label="Filtr anket" style="margin-bottom:1rem">
    <?php if ($archiv): ?>
      <a href="<?= h(BASE_URL) ?>/polls/index.php">Aktivní ankety</a>
      <span aria-current="page"><strong>Archiv</strong></span>
    <?php else: ?>
      <span aria-current="page"><strong>Aktivní ankety</strong></span>
      <a href="<?= h(BASE_URL) ?>/polls/index.php?archiv=1">Archiv</a>
    <?php endif; ?>
  </nav>

  <?php if (empty($polls)): ?>
    <p><?= $archiv ? 'Žádné uzavřené ankety.' : 'Žádné aktivní ankety.' ?></p>
  <?php else: ?>
    <?php foreach ($polls as $p):
      $pVotes = (int)$p['vote_count'];
      $pIpHash = pollIpHash((int)$p['id']);
      $stmtVoted = $pdo->prepare("SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = ? AND ip_hash = ?");
      $stmtVoted->execute([(int)$p['id'], $pIpHash]);
      $pHasVoted = (int)$stmtVoted->fetchColumn() > 0;
    ?>
      <article class="poll-card">
        <h3><a href="<?= h(BASE_URL) ?>/polls/index.php?id=<?= (int)$p['id'] ?>"><?= h($p['question']) ?></a></h3>
        <p class="poll-meta">
          <?= $pVotes ?> hlasů
          · Vytvořeno <?= formatCzechDate($p['created_at']) ?>
          <?php if ($p['end_date']): ?>
            · Konec: <?= formatCzechDate($p['end_date']) ?>
          <?php endif; ?>
        </p>
        <?php if ($p['description']): ?>
          <p><?= h($p['description']) ?></p>
        <?php endif; ?>
        <p>
          <?php if ($pHasVoted || $archiv): ?>
            <a href="<?= h(BASE_URL) ?>/polls/index.php?id=<?= (int)$p['id'] ?>">Zobrazit výsledky <span aria-hidden="true">→</span></a>
          <?php else: ?>
            <a href="<?= h(BASE_URL) ?>/polls/index.php?id=<?= (int)$p['id'] ?>">Hlasovat <span aria-hidden="true">→</span></a>
          <?php endif; ?>
        </p>
      </article>
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
      <nav class="pager" aria-label="Stránkování">
        <?php $base = $archiv ? 'index.php?archiv=1&' : 'index.php?'; ?>
        <?php if ($page > 1): ?>
          <a href="<?= h($base) ?>strana=<?= $page - 1 ?>" rel="prev"><span aria-hidden="true">‹</span> Předchozí</a>
        <?php endif; ?>
        <span aria-current="page"><?= $page ?>&nbsp;/&nbsp;<?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
          <a href="<?= h($base) ?>strana=<?= $page + 1 ?>" rel="next">Další <span aria-hidden="true">›</span></a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

<?php endif; ?>

</main>

<script>
(function () {
  var msg = document.querySelector('.vote-success');
  var live = document.getElementById('a11y-live');
  if (msg && live) live.textContent = msg.textContent;
})();
</script>

<?= siteFooter() ?>
</body>
</html>
