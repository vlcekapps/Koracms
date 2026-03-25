<?php
require_once __DIR__ . '/layout.php';
requireCapability('newsletter_manage', 'Přístup odepřen. Pro správu newsletteru nemáte potřebné oprávnění.');

$pdo = db_connect();
$statusFilter = trim($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'pending', 'confirmed'], true)) {
    $statusFilter = 'all';
}
$q = trim($_GET['q'] ?? '');

$subscriberCounts = newsletterSubscriberCounts($pdo);
$totalSubscribers = array_sum($subscriberCounts);
$sentNewsletterCount = (int)$pdo->query("SELECT COUNT(*) FROM cms_newsletters")->fetchColumn();

$subscriberWhere = 'WHERE 1';
$subscriberParams = [];
if ($statusFilter === 'pending') {
    $subscriberWhere .= ' AND s.confirmed = 0';
} elseif ($statusFilter === 'confirmed') {
    $subscriberWhere .= ' AND s.confirmed = 1';
}
if ($q !== '') {
    $subscriberWhere .= ' AND s.email LIKE ?';
    $subscriberParams[] = '%' . $q . '%';
}

$subscriberStmt = $pdo->prepare(
    "SELECT s.id, s.email, s.confirmed, s.created_at
     FROM cms_subscribers s
     {$subscriberWhere}
     ORDER BY s.confirmed ASC, s.created_at DESC"
);
$subscriberStmt->execute($subscriberParams);
$subscribers = $subscriberStmt->fetchAll();

$newsletterWhere = 'WHERE 1';
$newsletterParams = [];
if ($q !== '') {
    $newsletterWhere .= ' AND (n.subject LIKE ? OR n.body LIKE ?)';
    $newsletterParams[] = '%' . $q . '%';
    $newsletterParams[] = '%' . $q . '%';
}

$newsletterStmt = $pdo->prepare(
    "SELECT n.id, n.subject, n.sent_at, n.recipient_count, n.created_at
     FROM cms_newsletters n
     {$newsletterWhere}
     ORDER BY COALESCE(n.sent_at, n.created_at) DESC, n.id DESC
     LIMIT 30"
);
$newsletterStmt->execute($newsletterParams);
$newsletters = $newsletterStmt->fetchAll();

$currentParams = [];
if ($statusFilter !== 'all') {
    $currentParams['status'] = $statusFilter;
}
if ($q !== '') {
    $currentParams['q'] = $q;
}
$currentRedirect = BASE_URL . '/admin/newsletter.php' . ($currentParams !== [] ? '?' . http_build_query($currentParams) : '');

$successMessages = [
    'sent' => 'Newsletter byl odeslán a uložen do historie.',
    'confirmed' => 'Odběratel byl potvrzen.',
    'resent' => 'Potvrzovací e-mail byl znovu odeslán.',
    'deleted' => 'Odběratel byl smazán.',
];
$errorMessages = [
    'resend_failed' => 'Potvrzovací e-mail se nepodařilo odeslat. Zkuste to prosím znovu.',
];
$ok = trim($_GET['ok'] ?? '');
$error = trim($_GET['error'] ?? '');

adminHeader('Newsletter');
?>

<?php if (isset($successMessages[$ok])): ?>
  <p class="success" role="status"><?= h($successMessages[$ok]) ?></p>
<?php endif; ?>
<?php if (isset($errorMessages[$error])): ?>
  <p class="error" role="alert"><?= h($errorMessages[$error]) ?></p>
<?php endif; ?>

<div class="button-row" style="justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem">
  <div>
    <p style="margin:.2rem 0 .45rem">Tady spravujete odběratele newsletteru a kontrolujete historii odeslaných rozesílek.</p>
    <p style="margin:.2rem 0 0">
      <strong><?= $subscriberCounts['confirmed'] ?></strong> potvrzených odběratelů,
      <strong><?= $subscriberCounts['pending'] ?></strong> čeká na potvrzení,
      <strong><?= $sentNewsletterCount ?></strong> rozesílek v historii.
    </p>
  </div>
  <a href="newsletter_form.php" class="btn">+ Nová rozesílka</a>
</div>

<nav aria-label="Filtr odběratelů newsletteru" class="button-row" style="margin-bottom:1rem">
  <a href="?status=all" <?= $statusFilter === 'all' ? 'aria-current="page"' : '' ?>>
    Všichni (<?= $totalSubscribers ?>)
  </a>
  <a href="?status=pending" <?= $statusFilter === 'pending' ? 'aria-current="page"' : '' ?>>
    Čekají na potvrzení (<?= $subscriberCounts['pending'] ?>)
  </a>
  <a href="?status=confirmed" <?= $statusFilter === 'confirmed' ? 'aria-current="page"' : '' ?>>
    Potvrzení (<?= $subscriberCounts['confirmed'] ?>)
  </a>
</nav>

<form method="get" class="button-row" style="margin-bottom:1.5rem">
  <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
  <label for="q" class="sr-only">Hledat v odběratelích a historii newsletterů</label>
  <input type="search" id="q" name="q" placeholder="Hledat podle e-mailu nebo předmětu rozesílky…"
         value="<?= h($q) ?>" style="width:min(100%, 24rem)">
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== ''): ?>
    <a href="?status=<?= h($statusFilter) ?>" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<section aria-labelledby="newsletter-subscribers-heading" style="margin-bottom:2rem">
  <div class="button-row" style="justify-content:space-between;align-items:baseline">
    <h2 id="newsletter-subscribers-heading" style="margin-bottom:.5rem">Odběratelé newsletteru</h2>
    <small><?= count($subscribers) ?> zobrazených položek</small>
  </div>

  <?php if (empty($subscribers)): ?>
    <p><?= $statusFilter === 'all' && $q === '' ? 'Zatím tu nejsou žádní odběratelé.' : 'Pro zvolený filtr teď není k dispozici žádný odběratel.' ?></p>
  <?php else: ?>
    <table>
      <caption class="sr-only">Odběratelé newsletteru</caption>
      <thead>
        <tr>
          <th scope="col">E-mail</th>
          <th scope="col">Stav</th>
          <th scope="col">Přihlášen</th>
          <th scope="col">Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subscribers as $subscriber): ?>
          <?php $isConfirmed = (int)$subscriber['confirmed'] === 1; ?>
          <tr>
            <td><a href="mailto:<?= h((string)$subscriber['email']) ?>"><?= h((string)$subscriber['email']) ?></a></td>
            <td>
              <strong<?= !$isConfirmed ? ' style="color:#9a3412"' : '' ?>>
                <?= h(newsletterSubscriberStatusLabel($isConfirmed)) ?>
              </strong>
            </td>
            <td>
              <time datetime="<?= h(str_replace(' ', 'T', (string)$subscriber['created_at'])) ?>">
                <?= formatCzechDate((string)$subscriber['created_at']) ?>
              </time>
            </td>
            <td class="actions">
              <a class="btn" href="newsletter_subscriber.php?id=<?= (int)$subscriber['id'] ?>&redirect=<?= rawurlencode($currentRedirect) ?>">Zobrazit detail</a>
              <form action="<?= BASE_URL ?>/admin/newsletter_subscriber_action.php" method="post" style="display:inline"
                    onsubmit="return confirm('Smazat tohoto odběratele?')">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$subscriber['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn btn-danger">Smazat</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section aria-labelledby="newsletter-history-heading">
  <div class="button-row" style="justify-content:space-between;align-items:baseline">
    <h2 id="newsletter-history-heading" style="margin-bottom:.5rem">Poslední rozesílky</h2>
    <small><?= count($newsletters) ?> zobrazených položek</small>
  </div>

  <?php if (empty($newsletters)): ?>
    <p>Zatím jste neodeslali žádný newsletter.</p>
  <?php else: ?>
    <table>
      <caption class="sr-only">Historie odeslaných newsletterů</caption>
      <thead>
        <tr>
          <th scope="col">Předmět</th>
          <th scope="col">Odesláno</th>
          <th scope="col">Příjemců</th>
          <th scope="col">Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($newsletters as $newsletter): ?>
          <tr>
            <td><strong><?= h((string)$newsletter['subject']) ?></strong></td>
            <td>
              <?php if (!empty($newsletter['sent_at'])): ?>
                <time datetime="<?= h(str_replace(' ', 'T', (string)$newsletter['sent_at'])) ?>">
                  <?= formatCzechDate((string)$newsletter['sent_at']) ?>
                </time>
              <?php else: ?>
                <em>Neodesláno</em>
              <?php endif; ?>
            </td>
            <td><?= (int)$newsletter['recipient_count'] ?></td>
            <td class="actions">
              <a class="btn" href="newsletter_history.php?id=<?= (int)$newsletter['id'] ?>&redirect=<?= rawurlencode($currentRedirect) ?>">Zobrazit detail</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?php adminFooter(); ?>
