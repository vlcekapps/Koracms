<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu statických stránek nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$requestedBlogId = inputInt('get', 'blog_id');
$redirect = internalRedirectTarget(trim($_GET['redirect'] ?? ''), BASE_URL . '/admin/pages.php');

if ($id !== null) {
    $stmt = $pdo->prepare('SELECT * FROM cms_pages WHERE id = ?');
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
        'blog_id' => null,
        'blog_nav_order' => 0,
        'is_published' => 0,
        'show_in_nav' => 0,
        'status' => 'published',
        'unpublish_at' => null,
        'admin_note' => '',
    ];
    if ($requestedBlogId !== null && getBlogById($requestedBlogId)) {
        $page['blog_id'] = $requestedBlogId;
    }
}

$availableBlogs = getAllBlogs();
$availableBlogsById = [];
foreach ($availableBlogs as $availableBlog) {
    $availableBlogsById[(int)$availableBlog['id']] = $availableBlog;
}

$selectedBlogId = !empty($page['blog_id']) ? (int)$page['blog_id'] : null;
if ($selectedBlogId !== null && !isset($availableBlogsById[$selectedBlogId])) {
    $selectedBlogId = null;
    $page['blog_id'] = null;
}
$selectedBlog = $selectedBlogId !== null ? $availableBlogsById[$selectedBlogId] : null;
$isBlogPage = $selectedBlog !== null;

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$pageTitle = $id ? 'Upravit statickou stránku' : 'Nová statická stránka';
$err = trim($_GET['err'] ?? '');
$publicPath = ((int)($page['is_published'] ?? 0) === 1 && trim((string)($page['slug'] ?? '')) !== '') ? pagePublicPath($page) : '';
$fieldErrorMap = [
    'required' => ['title'],
    'slug' => ['slug'],
    'blog' => ['blog_id'],
    'unpublish_at' => ['unpublish_at'],
];
$fieldErrorMessages = [
    'title' => 'Název stránky je povinný.',
    'slug' => 'Slug stránky je už obsazený. Zvolte prosím jiný.',
    'blog' => 'Vybraný blog už neexistuje nebo pro tuto stránku není dostupný.',
    'unpublish_at' => 'Plánované zrušení publikace má neplatný formát data a času.',
];

adminHeader($pageTitle);
?>

<?php if ($id): ?>
  <p>
    <a href="revisions.php?type=page&amp;id=<?= (int)$id ?>">Historie revizí</a>
    ·
    <form action="convert_content.php" method="post" style="display:inline">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="direction" value="page_to_article">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <button type="submit" class="btn"
              data-confirm="Převést stránku na článek blogu? Stránka bude smazána a nahrazena článkem.">Převést na článek</button>
    </form>
  </p>
<?php endif; ?>

<?php if ($err === 'required'): ?>
  <p role="alert" class="error" id="form-error">Název stránky je povinný.</p>
<?php elseif ($err === 'slug'): ?>
  <p role="alert" class="error" id="form-error">Slug stránky je už obsazený. Zvolte prosím jiný.</p>
<?php elseif ($err === 'blog'): ?>
  <p role="alert" class="error" id="form-error">Vybraný blog už neexistuje nebo pro tuto stránku není dostupný.</p>
<?php elseif ($err === 'unpublish_at'): ?>
  <p role="alert" class="error" id="form-error">Plánované zrušení publikace má neplatný formát data a času.</p>
<?php endif; ?>

<p class="button-row button-row--start">
  <a href="<?= h($redirect) ?>"><span aria-hidden="true">←</span> Zpět na statické stránky</a>
  <a href="<?= BASE_URL ?>/admin/menu.php">Navigace webu</a>
  <?php if ($selectedBlog): ?>
    <a href="<?= BASE_URL ?>/admin/blog_pages.php?blog_id=<?= (int)$selectedBlog['id'] ?>">Pořadí stránek blogu</a>
  <?php endif; ?>
</p>
<p style="margin-top:0;font-size:.9rem">Vyplňte základní údaje stránky. Můžete ji ponechat jako globální statickou stránku, nebo ji přiřadit ke konkrétnímu blogu.</p>

<form method="post" action="<?= BASE_URL ?>/admin/page_save.php" novalidate<?= $err !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
  <?php if ($id !== null): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Obsah a zobrazení stránky</legend>

    <label for="blog_id">Patří k blogu</label>
    <select id="blog_id" name="blog_id"
            <?= adminFieldAttributes('blog_id', $err, $fieldErrorMap, ['page-blog-help', 'page-blog-order-help']) ?>>
      <option value="">Ne – globální statická stránka</option>
      <?php foreach ($availableBlogs as $availableBlog): ?>
        <option value="<?= (int)$availableBlog['id'] ?>"<?= $selectedBlogId === (int)$availableBlog['id'] ? ' selected' : '' ?>>
          <?= h((string)$availableBlog['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <small id="page-blog-help" class="field-help">Blogová stránka se zobrazí jen uvnitř příslušného blogu, má vlastní pořadí a nepatří do hlavní navigace webu.</small>
    <small id="page-blog-order-help" class="field-help">
      <span id="page-blog-order-text"<?= $selectedBlog ? ' hidden' : '' ?>>Po přiřazení blogu se tu objeví odkaz na správu pořadí blogových stránek.</span>
      <span id="page-blog-order-link-wrapper"<?= $selectedBlog ? '' : ' hidden' ?>>
        Pořadí této stránky uvnitř blogu upravíte na stránce <a id="page-blog-order-link" href="<?= $selectedBlog ? BASE_URL . '/admin/blog_pages.php?blog_id=' . (int)$selectedBlog['id'] : '#' ?>">Pořadí stránek blogu</a>.
      </span>
    </small>
    <?php adminRenderFieldError('blog_id', $err, $fieldErrorMap, $fieldErrorMessages['blog']); ?>

    <label for="title">Název <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="title" name="title" required aria-required="true"
           <?= adminFieldAttributes('title', $err, $fieldErrorMap) ?>
           value="<?= h((string)$page['title']) ?>">
    <?php adminRenderFieldError('title', $err, $fieldErrorMap, $fieldErrorMessages['title']); ?>

    <label for="slug">Slug (URL) <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true"
           <?= adminFieldAttributes('slug', $err, $fieldErrorMap, ['page-slug-help']) ?>
           pattern="[a-z0-9\-]+" title="Pouze malá písmena, číslice a pomlčky"
           value="<?= h((string)$page['slug']) ?>">
    <small id="page-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Použijte malá písmena, číslice a pomlčky.</small>
    <?php adminRenderFieldError('slug', $err, $fieldErrorMap, $fieldErrorMessages['slug']); ?>

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
      <input type="checkbox" id="show_in_nav" name="show_in_nav" value="1" aria-describedby="page-nav-help"<?= !$isBlogPage && !empty($page['show_in_nav']) ? ' checked' : '' ?><?= $isBlogPage ? ' disabled aria-disabled="true"' : '' ?>>
      Zobrazit v hlavní navigaci
    </label>
    <small id="page-nav-help" class="field-help">
      <?php if ($isBlogPage): ?>
        Blogové stránky se v hlavní navigaci webu nezobrazují. Pro tento obsah se použije navigace uvnitř blogu.
      <?php else: ?>
        Použije se jen u zveřejněné stránky. Skutečné pořadí v hlavní navigaci upravíte na stránce <a href="<?= BASE_URL ?>/admin/menu.php">Navigace webu</a>.
      <?php endif; ?>
    </small>

    <label for="unpublish_at">Plánované zrušení publikace</label>
    <input type="datetime-local" id="unpublish_at" name="unpublish_at"
           <?= adminFieldAttributes('unpublish_at', $err, $fieldErrorMap, ['unpublish-at-help']) ?>
           style="width:auto" value="<?= h(!empty($page['unpublish_at']) ? date('Y-m-d\TH:i', strtotime((string)$page['unpublish_at'])) : '') ?>">
    <small id="unpublish-at-help" class="field-help">Volitelné. Obsah se v zadaný čas automaticky skryje z veřejného webu.</small>
    <?php adminRenderFieldError('unpublish_at', $err, $fieldErrorMap, $fieldErrorMessages['unpublish_at']); ?>
  </fieldset>

  <fieldset style="margin-top:1rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Interní poznámka</legend>
    <label for="admin_note" class="visually-hidden">Interní poznámka</label>
    <textarea id="admin_note" name="admin_note" rows="2" aria-describedby="admin-note-help"
              style="min-height:0"><?= h((string)($page['admin_note'] ?? '')) ?></textarea>
    <small id="admin-note-help" class="field-help">Viditelná jen v administraci. Na veřejném webu se nezobrazuje.</small>
  </fieldset>

  <fieldset>
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
<script nonce="<?= cspNonce() ?>">
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

<script nonce="<?= cspNonce() ?>">
(function () {
    var titleInput = document.getElementById('title');
    var slugInput = document.getElementById('slug');
    var blogSelect = document.getElementById('blog_id');
    var showInNavCheckbox = document.getElementById('show_in_nav');
    var pageNavHelp = document.getElementById('page-nav-help');
    var pageBlogOrderLink = document.getElementById('page-blog-order-link');
    var pageBlogOrderText = document.getElementById('page-blog-order-text');
    var pageBlogOrderLinkWrapper = document.getElementById('page-blog-order-link-wrapper');
    var slugManuallyEdited = <?= $id !== null ? 'true' : 'false' ?>;
    var blogPagesBase = <?= json_encode(BASE_URL . '/admin/blog_pages.php?blog_id=') ?>;

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

    function updateBlogPageUi() {
        var selectedBlogId = blogSelect ? blogSelect.value.trim() : '';
        var isBlogPage = selectedBlogId !== '';

        if (showInNavCheckbox) {
            showInNavCheckbox.disabled = isBlogPage;
            showInNavCheckbox.setAttribute('aria-disabled', isBlogPage ? 'true' : 'false');
            if (isBlogPage) {
                showInNavCheckbox.checked = false;
            }
        }

        if (pageNavHelp) {
            pageNavHelp.innerHTML = isBlogPage
                ? 'Blogové stránky se v hlavní navigaci webu nezobrazují. Pro tento obsah se použije navigace uvnitř blogu.'
                : 'Použije se jen u zveřejněné stránky. Skutečné pořadí v hlavní navigaci upravíte na stránce <a href="<?= BASE_URL ?>/admin/menu.php">Navigace webu</a>.';
        }

        if (pageBlogOrderLink) {
            if (isBlogPage) {
                pageBlogOrderLink.href = blogPagesBase + encodeURIComponent(selectedBlogId);
                if (pageBlogOrderLinkWrapper) {
                    pageBlogOrderLinkWrapper.hidden = false;
                }
                if (pageBlogOrderText) {
                    pageBlogOrderText.hidden = true;
                }
            } else {
                pageBlogOrderLink.href = '#';
                if (pageBlogOrderLinkWrapper) {
                    pageBlogOrderLinkWrapper.hidden = true;
                }
                if (pageBlogOrderText) {
                    pageBlogOrderText.hidden = false;
                }
            }
        }
    }

    if (blogSelect) {
        blogSelect.addEventListener('change', updateBlogPageUi);
        updateBlogPageUi();
    }
})();
</script>

<?php adminFooter(); ?>
