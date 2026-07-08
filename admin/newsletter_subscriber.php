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
$deleteConfirmError = $error === 'subscriber_delete_confirm_required';
$deleteConfirmField = 'confirm_newsletter_subscriber_delete_' . (int)$subscriber['id'];
$deleteConfirmId = 'confirm-newsletter-subscriber-delete-' . (int)$subscriber['id'];
$deleteReviewId = 'newsletter-subscriber-delete-review-' . (int)$subscriber['id'];
$deleteFieldErrorId = 'confirm-newsletter-subscriber-delete-' . (int)$subscriber['id'] . '-error';
$deleteErrorFields = $deleteConfirmError ? [$deleteConfirmField] : [];

adminHeader('Detail odběratele');
?>

<?php if (isset($successMessages[$ok])): ?>
  <p class="success" role="status"><?= h($successMessages[$ok]) ?></p>
<?php endif; ?>
<?php if (isset($errorMessages[$error]) || $deleteConfirmError): ?>
  <p id="newsletter-subscriber-error" class="error" role="alert" aria-atomic="true">
    <?= h($deleteConfirmError ? 'Odběratele newsletteru nejde smazat bez potvrzení kontroly dopadu. U pole Potvrzení smazání je konkrétní nápověda.' : $errorMessages[$error]) ?>
  </p>
<?php endif; ?>

<p><a href="<?= h($redirect) ?>">&larr; Zpět na odběratele newsletteru</a></p>

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
      <th scope="row">Potvrzení odběru</th>
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

<h2>Co můžete udělat</h2>
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
          data-confirm="Opravdu znovu poslat potvrzovací e-mail tomuto odběrateli?">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$subscriber['id'] ?>">
      <input type="hidden" name="action" value="resend">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" class="btn">Znovu poslat potvrzení</button>
    </form>
  <?php endif; ?>

  <form method="post" action="<?= BASE_URL ?>/admin/newsletter_subscriber_action.php"
        class="admin-inline-form"
        novalidate<?= $deleteConfirmError ? ' aria-describedby="newsletter-subscriber-error"' : '' ?>
        data-confirm="Smazat tohoto odběratele?">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$subscriber['id'] ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
    <fieldset class="admin-inline-fieldset">
      <legend class="sr-only">Smazání odběratele <?= h((string)$subscriber['email']) ?></legend>
      <p id="<?= h($deleteReviewId) ?>" class="field-help field-help--flush">
        Smazání odstraní e-mail z aktivních odběrů newsletteru. Historie už odeslaných rozesílek zůstane zachovaná.
      </p>
      <label for="<?= h($deleteConfirmId) ?>" class="admin-checkbox-label">
        <input
          type="checkbox"
          id="<?= h($deleteConfirmId) ?>"
          name="<?= h($deleteConfirmField) ?>"
          value="1"
          required
          aria-required="true"<?= adminFieldAttributes($deleteConfirmField, $deleteErrorFields, [], [$deleteReviewId], $deleteFieldErrorId) ?>>
        Potvrzuji smazání tohoto odběratele.
      </label>
      <?php adminRenderFieldError($deleteConfirmField, $deleteErrorFields, [], 'Před smazáním odběratele potvrďte, že jste zkontrolovali jeho e-mail a dopad na další rozesílky.', $deleteFieldErrorId); ?>
      <button type="submit" class="btn btn-danger">Smazat odběratele</button>
    </fieldset>
  </form>
</div>

<?php adminFooter(); ?>
