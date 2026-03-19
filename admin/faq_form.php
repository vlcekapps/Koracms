<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$id  = inputInt('get', 'id');
$faq = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_faqs WHERE id = ?");
    $stmt->execute([$id]);
    $faq = $stmt->fetch();
    if (!$faq) { header('Location: faq.php'); exit; }
}

$categories = $pdo->query("SELECT id, name FROM cms_faq_categories ORDER BY sort_order, name")->fetchAll();
$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';

adminHeader($id ? 'Upravit otázku' : 'Nová otázka');

$err = trim($_GET['err'] ?? '');
?>

<?php if ($err === 'required'): ?>
  <p role="alert" class="error" id="form-error">Vyplňte prosím otázku a odpověď.</p>
<?php endif; ?>

<p style="margin-top:0;font-size:.9rem">
  Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<form method="post" action="faq_save.php" novalidate
      <?= $err ? 'aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Otázka a odpověď</legend>

    <label for="question">Otázka <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="question" name="question" required aria-required="true" maxlength="500"
           value="<?= h($faq['question'] ?? '') ?>">

    <label for="answer">Odpověď <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
  <textarea id="answer" name="answer" rows="8" required aria-required="true"><?= h($faq['answer'] ?? '') ?></textarea>

  <label for="category_id">Kategorie</label>
  <select id="category_id" name="category_id">
    <option value="">– bez kategorie –</option>
    <?php foreach ($categories as $cat): ?>
      <option value="<?= (int)$cat['id'] ?>" <?= ($faq['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
        <?= h($cat['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="sort_order">Pořadí</label>
  <input type="number" id="sort_order" name="sort_order" min="0" style="width:8rem"
         value="<?= (int)($faq['sort_order'] ?? 0) ?>">

  <label style="font-weight:normal;margin-top:1rem">
    <input type="checkbox" name="is_published" value="1"
           <?= ($faq['is_published'] ?? 1) ? 'checked' : '' ?>>
    Publikováno
  </label>

    <div style="margin-top:1.5rem">
      <button type="submit" class="btn"><?= $id ? 'Uložit' : 'Přidat otázku' ?></button>
      <a href="faq.php" style="margin-left:1rem">Zrušit</a>
    </div>
  </fieldset>
</form>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
(function () {
    const ta = document.getElementById('answer');
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'background:#fff;border:1px solid #ccc;margin-top:.2rem;min-height:200px';
    ta.parentNode.insertBefore(wrapper, ta);
    ta.style.display = 'none';
    const quill = new Quill(wrapper, { theme: 'snow' });
    quill.root.innerHTML = ta.value;
    ta.closest('form').addEventListener('submit', () => { ta.value = quill.root.innerHTML; });
})();
</script>
<?php endif; ?>

<?php adminFooter(); ?>
