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
];
$formError = $_SESSION['newsletter_form_error'] ?? '';
unset($_SESSION['newsletter_form_state'], $_SESSION['newsletter_form_error']);

adminHeader('Nová rozesílka');
?>

<?php if ($formError !== ''): ?>
  <p class="error" role="alert" id="newsletter-form-error"><?= h($formError) ?></p>
<?php endif; ?>

<p><a href="newsletter.php">&larr; Zpět na newsletter</a></p>

<div class="button-row" style="justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem">
  <div>
    <p style="margin:.2rem 0 .45rem">Rozesílka se odešle všem potvrzeným odběratelům newsletteru.</p>
    <p style="margin:.2rem 0 0">
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
  <form method="post" action="newsletter_send.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <fieldset>
      <legend>Obsah e-mailu</legend>

      <label for="subject">Předmět <span aria-hidden="true">*</span></label>
      <input type="text" id="subject" name="subject" required aria-required="true" maxlength="255"
             value="<?= h((string)$formState['subject']) ?>">

      <label for="body">Text e-mailu <span aria-hidden="true">*</span></label>
      <textarea id="body" name="body" rows="15" required aria-required="true"><?= h((string)$formState['body']) ?></textarea>

      <p style="margin-top:.5rem;font-size:.95rem;color:#444">
        Do každého e-mailu se automaticky přidá odkaz pro odhlášení z odběru.
      </p>

      <div class="button-row" style="margin-top:1.5rem">
        <button type="submit" class="btn"
                onclick="return confirm('Opravdu odeslat newsletter <?= $confirmedCount ?> potvrzeným odběratelům?')">
          Odeslat rozesílku
        </button>
        <a href="newsletter.php" class="btn">Zrušit</a>
      </div>
    </fieldset>
  </form>

  <?php if ($useWysiwyg): ?>
  <link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
  <script nonce="<?= cspNonce() ?>">
  (function () {
      const ta = document.getElementById('body');
      const wrapper = document.createElement('div');
      wrapper.style.cssText = 'background:#fff;border:1px solid #ccc;margin-top:.2rem;min-height:250px';
      ta.parentNode.insertBefore(wrapper, ta);
      ta.style.display = 'none';
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
