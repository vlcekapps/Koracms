<?php
require_once __DIR__ . '/layout.php';
requireCapability('newsletter_manage', 'Přístup odepřen. Pro správu odběratelů newsletteru nemáte potřebné oprávnění.');

$pdo = db_connect();
$subscriberId = inputInt('get', 'id');
$redirect = internalRedirectTarget(trim($_GET['redirect'] ?? ''), BASE_URL . '/admin/newsletter.php');

if ($subscriberId === null) {
    header('Location: ' . $redirect);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, email, confirmed, token, created_at
     FROM cms_subscribers
     WHERE id = ?"
);
$stmt->execute([$subscriberId]);
$subscriber = $stmt->fetch();

if (!$subscriber) {
    header('Location: ' . $redirect);
    exit;
}

$isConfirmed = (int)$subscriber['confirmed'] === 1;
$selfRedirect = BASE_URL . '/admin/newsletter_subscriber.php?id=' . (int)$subscriber['id'] . '&redirect=' . rawurlencode($redirect);
$successMessages = [
    'confirmed' => 'Odběratel byl potvrzen.',
    'resent' => 'Potvrzovací e-mail byl znovu odeslán.',
];
$errorMessages = [
    'resend_failed' => 'Potvrzovací e-mail se nepodařilo odeslat. Zkuste to prosím znovu.',
];
$ok = trim($_GET['ok'] ?? '');
$error = trim($_GET['error'] ?? '');

adminHeader('Odběratel newsletteru');
?>

<?php if (isset($successMessages[$ok])): ?>
  <p class="success" role="status"><?= h($successMessages[$ok]) ?></p>
<?php endif; ?>
<?php if (isset($errorMessages[$error])): ?>
  <p class="error" role="alert"><?= h($errorMessages[$error]) ?></p>
<?php endif; ?>

<p><a href="<?= h($redirect) ?>">&larr; Zpět na newsletter</a></p>

<table>
  <caption class="sr-only">Detail odběratele newsletteru</caption>
  <tbody>
    <tr>
      <th scope="row">E-mail</th>
      <td><a href="mailto:<?= h((string)$subscriber['email']) ?>"><?= h((string)$subscriber['email']) ?></a></td>
    </tr>
    <tr>
      <th scope="row">Stav</th>
      <td><strong><?= h(newsletterSubscriberStatusLabel($isConfirmed)) ?></strong></td>
    </tr>
    <tr>
      <th scope="row">Přihlášen</th>
      <td>
        <time datetime="<?= h(str_replace(' ', 'T', (string)$subscriber['created_at'])) ?>">
          <?= formatCzechDate((string)$subscriber['created_at']) ?>
        </time>
      </td>
    </tr>
    <tr>
      <th scope="row">Správa potvrzení</th>
      <td>
        <?php if ($isConfirmed): ?>
          Tento odběratel už je potvrzený a bude dostávat další rozesílky.
        <?php else: ?>
          Odběr zatím čeká na potvrzení. Můžete jej potvrdit ručně nebo znovu poslat potvrzovací e-mail.
        <?php endif; ?>
      </td>
    </tr>
  </tbody>
</table>

<h2>Akce</h2>
<div class="button-row">
  <?php if (!$isConfirmed): ?>
    <form method="post" action="<?= BASE_URL ?>/admin/newsletter_subscriber_action.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$subscriber['id'] ?>">
      <input type="hidden" name="action" value="confirm">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" class="btn">Potvrdit odběr</button>
    </form>

    <form method="post" action="<?= BASE_URL ?>/admin/newsletter_subscriber_action.php"
          onsubmit="return confirm('Opravdu znovu poslat potvrzovací e-mail tomuto odběrateli?')">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$subscriber['id'] ?>">
      <input type="hidden" name="action" value="resend">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" class="btn">Znovu poslat potvrzení</button>
    </form>
  <?php endif; ?>

  <form method="post" action="<?= BASE_URL ?>/admin/newsletter_subscriber_action.php"
        onsubmit="return confirm('Smazat tohoto odběratele?')">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$subscriber['id'] ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
    <button type="submit" class="btn btn-danger">Smazat odběratele</button>
  </form>
</div>

<?php adminFooter(); ?>
