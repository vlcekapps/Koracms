<?php
require_once __DIR__ . '/layout.php';
requireCapability('newsletter_manage', 'Přístup odepřen. Pro rozesílání newsletteru nemáte potřebné oprávnění.');

$pdo = db_connect();
$subscriberCounts = newsletterSubscriberCounts($pdo);
$confirmedCount = $subscriberCounts['confirmed'];
$pendingCount = $subscriberCounts['pending'];
$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';

$formState = $_SESSION['newsletter_form_state'] ?? [
    'subject' => '',
    'body' => '',
    'confirm_newsletter_send' => '',
];
$formError = $_SESSION['newsletter_form_error'] ?? '';
$formErrorFields = $_SESSION['newsletter_form_error_fields'] ?? [];
unset($_SESSION['newsletter_form_state'], $_SESSION['newsletter_form_error'], $_SESSION['newsletter_form_error_fields']);

$fieldErrorMessages = [
    'subject' => 'Doplňte krátký předmět, který odběratel uvidí v doručené poště.',
    'body' => 'Doplňte text rozesílky. Odkaz pro odhlášení CMS přidá automaticky.',
    'confirm_newsletter_send' => 'Před odesláním potvrďte, že jste zkontrolovali obsah a počet příjemců rozesílky.',
];

adminHeader('Nová rozesílka');
?>

<?php if ($formError !== ''): ?>
  <p class="error" role="alert" id="newsletter-form-error" aria-atomic="true"><?= h($formError) ?></p>
<?php endif; ?>

<p><a href="newsletter.php">&larr; Zpět na newsletter</a></p>

<div class="button-row button-row--between button-row--top admin-stack-md">
  <div>
    <p class="admin-copy">Rozesílka se odešle všem potvrzeným odběratelům newsletteru.</p>
    <p class="admin-copy--compact">
      Odeslání proběhne na <strong><?= $confirmedCount ?></strong> potvrzených odběratelů.
      <?php if ($pendingCount > 0): ?>
        Dalších <strong><?= $pendingCount ?></strong> odběrů zatím čeká na potvrzení.
      <?php endif; ?>
    </p>
  </div>
</div>

<?php if ($confirmedCount === 0): ?>
  <p class="error">Zatím nemáte žádné potvrzené odběratele, takže novou rozesílku teď nelze odeslat.</p>
<?php else: ?>
  <form method="post" action="newsletter_send.php" novalidate<?= $formError !== '' ? ' aria-describedby="newsletter-form-error"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <fieldset>
      <legend>Obsah e-mailu</legend>

      <label for="subject">Předmět <span aria-hidden="true">*</span></label>
      <input type="text" id="subject" name="subject" required aria-required="true" maxlength="255"
             value="<?= h((string)$formState['subject']) ?>"<?= adminFieldAttributes('subject', $formErrorFields) ?>>
      <?php adminRenderFieldError('subject', $formErrorFields, [], $fieldErrorMessages['subject']); ?>

      <label for="body">Text e-mailu <span aria-hidden="true">*</span></label>
      <textarea id="body" name="body" rows="15" required aria-required="true"<?= adminFieldAttributes('body', $formErrorFields) ?>><?= h((string)$formState['body']) ?></textarea>
      <?php adminRenderFieldError('body', $formErrorFields, [], $fieldErrorMessages['body']); ?>

      <p class="field-help">
        Do každého e-mailu se automaticky přidá odkaz pro odhlášení z odběru.
      </p>
    </fieldset>

    <fieldset class="admin-fieldset-card admin-fieldset-spaced" aria-describedby="newsletter-review-help">
      <legend>Kontrola rozesílky</legend>
      <p id="newsletter-review-help" class="field-help field-help--flush">
        Rozesílka se po potvrzení odešle všem potvrzeným odběratelům. Před odesláním zkontrolujte obsah,
        počet příjemců a správnou cílovou skupinu.
      </p>
      <dl class="admin-definition-list admin-definition-list--compact">
        <div>
          <dt>Potvrzených odběratelů</dt>
          <dd><?= $confirmedCount ?></dd>
        </div>
        <div>
          <dt>Čekajících potvrzení</dt>
          <dd><?= $pendingCount ?></dd>
        </div>
      </dl>
      <label for="confirm_newsletter_send" class="admin-checkbox-label">
        <input type="checkbox" id="confirm_newsletter_send" name="confirm_newsletter_send" value="1" required
               <?= !empty($formState['confirm_newsletter_send']) ? 'checked' : '' ?>
               <?= adminFieldAttributes('confirm_newsletter_send', $formErrorFields, [], ['newsletter-review-help'], 'confirm-newsletter-send-error') ?>>
        Potvrzuji, že jsem zkontroloval(a) obsah rozesílky a počet potvrzených příjemců.
      </label>
      <?php adminRenderFieldError('confirm_newsletter_send', $formErrorFields, [], $fieldErrorMessages['confirm_newsletter_send'], 'confirm-newsletter-send-error'); ?>
    </fieldset>

    <div class="button-row admin-action-row">
      <button type="submit" class="btn"
              data-confirm="Opravdu odeslat newsletter <?= $confirmedCount ?> potvrzeným odběratelům?">
        Odeslat rozesílku
      </button>
      <a href="newsletter.php" class="btn">Zrušit</a>
    </div>
  </form>

  <?php if ($useWysiwyg): ?>
  <link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
  <script nonce="<?= cspNonce() ?>" src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
  <script nonce="<?= cspNonce() ?>">
  (function () {
      const ta = document.getElementById('body');
      const wrapper = document.createElement('div');
      wrapper.className = 'admin-rich-editor-frame admin-rich-editor-md';
      ta.parentNode.insertBefore(wrapper, ta);
      ta.hidden = true;
      const quill = new Quill(wrapper, { theme: 'snow' });
      if (ta.value.trim() !== '') {
          quill.root.innerHTML = ta.value;
      }
      ta.closest('form').addEventListener('submit', () => { ta.value = quill.root.innerHTML; });
  })();
  </script>
  <?php endif; ?>
<?php endif; ?>

<?php adminFooter(); ?>
