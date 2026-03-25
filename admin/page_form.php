<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu statických stránek nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$redirect = internalRedirectTarget(trim($_GET['redirect'] ?? ''), BASE_URL . '/admin/pages.php');

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_pages WHERE id = ?");
    $stmt->execute([$id]);
    $page = $stmt->fetch();
    if (!$page) {
        header('Location: ' . $redirect);
        exit;
    }
} else {
    $page = [
        'id' => null,
        'title' => '',
        'slug' => '',
        'content' => '',
        'is_published' => 0,
        'show_in_nav' => 0,
        'nav_order' => 1,
        'status' => 'published',
    ];
}

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$pageTitle = $id ? 'Upravit statickou stránku' : 'Nová statická stránka';
$err = trim($_GET['err'] ?? '');
$publicPath = ((int)($page['is_published'] ?? 0) === 1 && trim((string)($page['slug'] ?? '')) !== '') ? pagePublicPath($page) : '';

adminHeader($pageTitle);
?>

<?php if ($err === 'required'): ?>
  <p role="alert" class="error" id="form-error">Název stránky je povinný.</p>
<?php elseif ($err === 'slug'): ?>
  <p role="alert" class="error" id="form-error">Slug stránky je už obsazený. Zvolte prosím jiný.</p>
<?php endif; ?>

<p><a href="<?= h($redirect) ?>"><span aria-hidden="true">←</span> Zpět na statické stránky</a></p>
<p style="margin-top:0;font-size:.9rem">Vyplňte základní údaje stránky a zvolte, jestli se má zobrazit na webu a v hlavní navigaci.</p>

<form method="post" action="<?= BASE_URL ?>/admin/page_save.php" novalidate<?= $err !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
  <?php if ($id !== null): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Obsah a zobrazení stránky</legend>

    <label for="title">Název <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="title" name="title" required aria-required="true" value="<?= h((string)$page['title']) ?>">

    <label for="slug">Slug (URL) <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" aria-describedby="page-slug-help"
           pattern="[a-z0-9\-]+" title="Pouze malá písmena, číslice a pomlčky"
           value="<?= h((string)$page['slug']) ?>">
    <small id="page-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Použijte malá písmena, číslice a pomlčky.</small>

    <label for="content">Obsah</label>
    <textarea id="content" name="content"<?= !$useWysiwyg ? ' aria-describedby="page-content-help"' : '' ?>><?= h((string)$page['content']) ?></textarea>
    <?php if (!$useWysiwyg): ?><small id="page-content-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small><?php endif; ?>
    <?php if (!$useWysiwyg): ?>
      <?php renderAdminContentReferencePicker('content'); ?>
    <?php endif; ?>

    <label style="font-weight:normal; margin-top:1rem">
      <input type="checkbox" name="is_published" value="1" aria-describedby="page-published-help"<?= !empty($page['is_published']) ? ' checked' : '' ?>>
      Zveřejnit na webu
    </label>
    <small id="page-published-help" class="field-help">Když volbu vypnete, stránka se na veřejném webu nezobrazí.</small>

    <label style="font-weight:normal; margin-top:.5rem">
      <input type="checkbox" name="show_in_nav" value="1" aria-describedby="page-nav-help"<?= !empty($page['show_in_nav']) ? ' checked' : '' ?>>
      Zobrazit v hlavní navigaci
    </label>
    <small id="page-nav-help" class="field-help">Použije se jen u zveřejněné stránky.</small>

    <label for="nav_order">Pořadí v navigaci</label>
    <input type="number" id="nav_order" name="nav_order" min="1" style="width:8rem" value="<?= (int)$page['nav_order'] ?>">

    <div style="margin-top:1.5rem">
      <button type="submit" class="btn"><?= $id !== null ? 'Uložit změny' : 'Vytvořit stránku' ?></button>
      <a href="<?= h($redirect) ?>" style="margin-left:1rem">Zrušit</a>
      <?php if ($publicPath !== ''): ?>
        <a href="<?= h($publicPath) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu <span aria-hidden="true">↗</span></a>
      <?php endif; ?>
    </div>
  </fieldset>
</form>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
(function () {
    const ta = document.getElementById('content');
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'background:#fff;border:1px solid #ccc;margin-top:.2rem';
    wrapper.style.minHeight = '300px';
    ta.parentNode.insertBefore(wrapper, ta);
    ta.style.display = 'none';
    const quill = new Quill(wrapper, {
        theme: 'snow',
        modules: { toolbar: [
            [{ header: [2, 3, 4, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote', 'code-block'],
            ['link', 'image'],
            ['clean']
        ]}
    });
    quill.root.innerHTML = ta.value;
    ta.closest('form').addEventListener('submit', function () {
        ta.value = quill.root.innerHTML;
    });
})();
</script>
<?php endif; ?>

<script>
(function () {
    var titleInput = document.getElementById('title');
    var slugInput = document.getElementById('slug');
    var slugManuallyEdited = <?= $id !== null ? 'true' : 'false' ?>;

    slugInput.addEventListener('input', function () {
        slugManuallyEdited = true;
    });

    titleInput.addEventListener('input', function () {
        if (slugManuallyEdited) {
            return;
        }

        slugInput.value = this.value
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    });
})();
</script>

<?php adminFooter(); ?>
