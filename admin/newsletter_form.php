<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo            = db_connect();
$confirmedCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_subscribers WHERE confirmed = 1"
)->fetchColumn();

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';

adminHeader('Nový newsletter');
?>

<p>Bude odesláno <strong><?= $confirmedCount ?></strong> potvrzeným odběratelům.</p>

<?php if ($confirmedCount === 0): ?>
  <p class="error">Nejsou žádní potvrzení odběratelé. Newsletter nelze odeslat.</p>
<?php else: ?>

<form method="post" action="newsletter_send.php" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <label for="subject">Předmět <span aria-hidden="true">*</span></label>
  <input type="text" id="subject" name="subject" required maxlength="255">

  <label for="body">Text emailu <span aria-hidden="true">*</span></label>
  <textarea id="body" name="body" rows="15" required></textarea>

  <p style="margin-top:.5rem;font-size:.9rem;color:#555">
    V textu emailu se automaticky přidá odkaz pro odhlášení z odběru.
  </p>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"
            onclick="return confirm('Opravdu odeslat newsletter <?= $confirmedCount ?> odběratelům?')">
      Odeslat newsletter
    </button>
    <a href="newsletter.php" style="margin-left:1rem">Zrušit</a>
  </div>
</form>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
(function () {
    const ta = document.getElementById('body');
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'background:#fff;border:1px solid #ccc;margin-top:.2rem;min-height:250px';
    ta.parentNode.insertBefore(wrapper, ta);
    ta.style.display = 'none';
    const quill = new Quill(wrapper, { theme: 'snow' });
    ta.closest('form').addEventListener('submit', () => { ta.value = quill.root.innerHTML; });
})();
</script>
<?php endif; ?>

<?php endif; ?>

<?php adminFooter(); ?>
