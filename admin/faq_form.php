<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu FAQ nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$faq = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_faqs WHERE id = ?");
    $stmt->execute([$id]);
    $faq = $stmt->fetch() ?: null;
    if (!$faq) {
        header('Location: faq.php');
        exit;
    }
}

$faq = $faq ?: [
    'question' => '',
    'slug' => '',
    'excerpt' => '',
    'answer' => '',
    'category_id' => null,
    'is_published' => 1,
    'status' => 'published',
];

$categories = $pdo->query("SELECT id, name FROM cms_faq_categories ORDER BY sort_order, name")->fetchAll();
$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$err = trim($_GET['err'] ?? '');
$formError = match ($err) {
    'required' => 'Vyplňte prosím otázku a odpověď.',
    'slug' => 'Slug FAQ je povinný a musí být unikátní.',
    default => '',
};

adminHeader($id ? 'Upravit otázku FAQ' : 'Nová otázka FAQ');
?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error"><?= h($formError) ?></p>
<?php endif; ?>

<p style="margin-top:0;font-size:.9rem">
  Vyplňte potřebné údaje k otázce a odpovědi. Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<p><a href="faq.php"><span aria-hidden="true">←</span> Zpět na FAQ</a></p>

<form method="post" action="faq_save.php" novalidate<?= $formError !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Otázka a odpověď</legend>

    <label for="question">Otázka <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="question" name="question" required aria-required="true" maxlength="500"
           value="<?= h((string)$faq['question']) ?>">

    <label for="slug">Slug veřejné stránky <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255" pattern="[a-z0-9\-]+"
           aria-describedby="faq-slug-help"
           value="<?= h((string)$faq['slug']) ?>">
    <small id="faq-slug-help" class="field-help">Používejte malá písmena, číslice a pomlčky.</small>

    <label for="excerpt">Krátké shrnutí / perex</label>
    <textarea id="excerpt" name="excerpt" rows="3" aria-describedby="faq-excerpt-help"><?= h((string)($faq['excerpt'] ?? '')) ?></textarea>
    <small id="faq-excerpt-help" class="field-help">Zobrazí se ve výpisu FAQ, ve vyhledávání a jako úvod detailu.</small>

    <label for="answer">Odpověď <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <?php if ($useWysiwyg): ?>
      <div id="editor-answer" style="min-height:220px"><?= (string)($faq['answer'] ?? '') ?></div>
      <input type="hidden" id="answer" name="answer" value="<?= h((string)($faq['answer'] ?? '')) ?>">
    <?php else: ?>
      <textarea id="answer" name="answer" rows="8" required aria-required="true" aria-describedby="faq-answer-help"><?= h((string)($faq['answer'] ?? '')) ?></textarea>
      <small id="faq-answer-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small>
      <?php renderAdminContentReferencePicker('answer'); ?>
    <?php endif; ?>

    <label for="category_id">Kategorie</label>
    <select id="category_id" name="category_id">
      <option value="">– bez kategorie –</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int)$category['id'] ?>"<?= (string)($faq['category_id'] ?? '') === (string)$category['id'] ? ' selected' : '' ?>>
          <?= h((string)$category['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label style="font-weight:normal;margin-top:1rem">
      <input type="checkbox" name="is_published" value="1" aria-describedby="faq-published-help"
             <?= (int)($faq['is_published'] ?? 1) === 1 ? 'checked' : '' ?>>
      Zveřejnit na webu
    </label>
    <small id="faq-published-help" class="field-help" style="margin-top:.2rem">Když volbu vypnete, otázka zůstane uložená jen v administraci.</small>

    <div style="margin-top:1.5rem">
      <button type="submit" class="btn"><?= $id ? 'Uložit změny' : 'Přidat otázku FAQ' ?></button>
      <a href="faq.php" style="margin-left:1rem">Zrušit</a>
      <?php if (($faq['status'] ?? 'published') === 'published' && (int)($faq['is_published'] ?? 1) === 1 && !empty($faq['slug'])): ?>
        <a href="<?= h(faqPublicPath($faq)) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
      <?php endif; ?>
    </div>
  </fieldset>
</form>

<script nonce="<?= cspNonce() ?>">
(function () {
    const questionInput = document.getElementById('question');
    const slugInput = document.getElementById('slug');
    let slugManual = <?= !empty($faq['slug']) ? 'true' : 'false' ?>;

    const slugify = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    slugInput?.addEventListener('input', function () {
        slugManual = this.value.trim() !== '';
    });

    questionInput?.addEventListener('input', function () {
        if (slugManual || !slugInput) {
            return;
        }
        slugInput.value = slugify(this.value);
    });
})();
</script>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script nonce="<?= cspNonce() ?>">
(function () {
    const textarea = document.getElementById('answer');
    const wrapper = document.getElementById('editor-answer');
    const quill = new Quill(wrapper, { theme: 'snow' });
    quill.root.innerHTML = textarea.value;
    textarea.closest('form')?.addEventListener('submit', function () {
        textarea.value = quill.root.innerHTML;
    });
})();
</script>
<?php endif; ?>

<?php adminFooter(); ?>
