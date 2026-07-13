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
    $page = null;
}

$pageFormFlash = $_SESSION['page_form_flash'] ?? null;
unset($_SESSION['page_form_flash']);
$pageFormFlashValues = is_array($pageFormFlash) && is_array($pageFormFlash['values'] ?? null)
    ? $pageFormFlash['values']
    : [];

// Content locking – pokus o získání zámku při editaci existující stránky
$contentLockWarning = null;
if ($page) {
    $contentLockWarning = acquireContentLock('page', $id);
}

if ($page === null) {
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
        'publish_at' => null,
        'unpublish_at' => null,
        'admin_note' => '',
    ];
    if ($requestedBlogId !== null && getBlogById($requestedBlogId)) {
        $page['blog_id'] = $requestedBlogId;
    }
}

if ($pageFormFlashValues !== []) {
    $flashIdRaw = $pageFormFlashValues['id'] ?? null;
    $flashId = $flashIdRaw !== null && $flashIdRaw !== '' ? (int)$flashIdRaw : null;
    if ($flashId === $id) {
        foreach (['title', 'slug', 'content', 'publish_at', 'unpublish_at', 'admin_note'] as $flashKey) {
            if (array_key_exists($flashKey, $pageFormFlashValues)) {
                $page[$flashKey] = (string)$pageFormFlashValues[$flashKey];
            }
        }
        if (array_key_exists('blog_id', $pageFormFlashValues)) {
            $page['blog_id'] = $pageFormFlashValues['blog_id'] !== null && $pageFormFlashValues['blog_id'] !== ''
                ? (int)$pageFormFlashValues['blog_id']
                : null;
        }
        if (array_key_exists('is_published', $pageFormFlashValues)) {
            $page['is_published'] = (int)$pageFormFlashValues['is_published'];
        }
        if (array_key_exists('show_in_nav', $pageFormFlashValues)) {
            $page['show_in_nav'] = (int)$pageFormFlashValues['show_in_nav'];
        }
        if (array_key_exists('article_status', $pageFormFlashValues)
            && in_array((string)$pageFormFlashValues['article_status'], ['draft', 'pending', 'published'], true)) {
            $page['status'] = (string)$pageFormFlashValues['article_status'];
        }
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
$pagePublishAtErrorMessage = 'Znovu vyberte plánované publikování v poli datum a čas, nebo pole nechte prázdné pro okamžité zveřejnění.';
$pageUnpublishAtErrorMessage = 'Znovu vyberte plánované zrušení publikace v poli datum a čas, nebo pole nechte prázdné bez automatického skrytí.';
$pageSlugErrorMessage = 'Slug stránky je už obsazený. Zadejte jiný slug z malých písmen, číslic a pomlček.';
if ($err === 'slug_blog') {
    $pageSlugErrorMessage = 'Tento slug už v tomto blogu používá jiná stránka. Zadejte jiný slug pro tuto blogovou stránku.';
} elseif ($err === 'slug_global') {
    $pageSlugErrorMessage = 'Tento slug už používá jiná globální stránka. Zadejte jiný slug nebo stránku přiřaďte ke konkrétnímu blogu, kde může být slug volný.';
}
$formatDateTimeLocalValue = static function ($value): string {
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }

    $timestamp = strtotime($text);
    return $timestamp === false ? $text : date('Y-m-d\TH:i', $timestamp);
};
$fieldErrorMap = [
    'required' => ['title'],
    'slug' => ['slug'],
    'slug_blog' => ['slug'],
    'slug_global' => ['slug'],
    'blog' => ['blog_id'],
    'publish_at' => ['publish_at'],
    'unpublish_at' => ['unpublish_at'],
];
$fieldErrorMessages = [
    'title' => 'Doplňte název stránky. Použije se v administraci, nadpisu veřejné stránky i navigaci.',
    'slug' => $pageSlugErrorMessage,
    'blog' => 'Vyberte dostupný blog ze seznamu, nebo pole ponechte prázdné pro globální statickou stránku.',
    'publish_at' => $pagePublishAtErrorMessage,
    'unpublish_at' => $pageUnpublishAtErrorMessage,
];

adminHeader($pageTitle);
?>

<?php if (trim((string)($_GET['converted'] ?? '')) === 'article_to_page'): ?>
  <p class="success" role="status" aria-atomic="true">Článek byl převeden na stránku. Původní článek včetně komentářů a vazeb zůstal obnovitelný v Koši.</p>
<?php endif; ?>

<?php if ($contentLockWarning !== null): ?>
  <div role="alert" class="admin-warning-box">
    <strong>Upozornění:</strong>
    Tuto stránku právě upravuje <?= h((string)$contentLockWarning['locked_by']) ?>
    (od <?= h(date('H:i', strtotime((string)$contentLockWarning['locked_at']))) ?>).
    Vaše změny mohou přepsat jejich práci.
  </div>
<?php endif; ?>

<?php if ($id): ?>
  <div class="button-row button-row--baseline admin-stack-sm">
    <a href="revisions.php?type=page&amp;id=<?= (int)$id ?>">Historie revizí</a>
    <form action="convert_content.php" method="post" class="admin-inline-form">
      <fieldset class="admin-inline-fieldset">
        <legend class="sr-only">Kontrola převodu stránky <?= h((string)$page['title']) ?> na článek</legend>
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="direction" value="page_to_article">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="stage" value="review">
        <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
        <button type="submit" class="btn">Převést na článek</button>
      </fieldset>
    </form>
  </div>
<?php endif; ?>

<?php if ($err === 'required'): ?>
  <p role="alert" class="error" id="form-error" aria-atomic="true">Stránku nejde uložit bez názvu. U pole Název je konkrétní nápověda.</p>
<?php elseif (in_array($err, ['slug', 'slug_blog', 'slug_global'], true)): ?>
  <p role="alert" class="error" id="form-error" aria-atomic="true">Slug stránky není možné použít. U pole Slug (URL) je konkrétní nápověda.</p>
<?php elseif ($err === 'blog'): ?>
  <p role="alert" class="error" id="form-error" aria-atomic="true">Vybraný blog není dostupný. U pole Patří k blogu je konkrétní nápověda.</p>
<?php elseif ($err === 'publish_at'): ?>
  <p role="alert" class="error" id="form-error" aria-atomic="true">Plánované publikování nemá platné datum a čas. U pole je konkrétní nápověda.</p>
<?php elseif ($err === 'unpublish_at'): ?>
  <p role="alert" class="error" id="form-error" aria-atomic="true">Plánované zrušení publikace nemá platné datum a čas. U pole je konkrétní nápověda.</p>
<?php endif; ?>

<p class="button-row button-row--start">
  <a href="<?= h($redirect) ?>"><span aria-hidden="true">←</span> Zpět na statické stránky</a>
  <a href="<?= BASE_URL ?>/admin/menu.php">Navigace webu</a>
  <?php if ($selectedBlog): ?>
    <a href="<?= BASE_URL ?>/admin/blog_pages.php?blog_id=<?= (int)$selectedBlog['id'] ?>">Pořadí stránek blogu</a>
  <?php endif; ?>
</p>
<p class="admin-description admin-description--flush">Vyplňte základní údaje stránky. Můžete ji ponechat jako globální statickou stránku, nebo ji přiřadit ke konkrétnímu blogu.</p>

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
           pattern="[a-z0-9\-]+"
           value="<?= h((string)$page['slug']) ?>">
    <small id="page-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Použijte malá písmena, číslice a pomlčky.</small>
    <?php adminRenderFieldError('slug', $err, $fieldErrorMap, $fieldErrorMessages['slug']); ?>

    <label for="content">Obsah</label>
    <textarea id="content" name="content"<?= !$useWysiwyg ? ' aria-describedby="page-content-help"' : '' ?>><?= h((string)$page['content']) ?></textarea>
    <?php if (!$useWysiwyg): ?><small id="page-content-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small><?php endif; ?>
    <?php if (!$useWysiwyg): ?>
      <?php renderAdminContentReferencePicker('content'); ?>
    <?php endif; ?>

    <div class="admin-action-row">
      <label class="admin-checkbox-label">
        <input type="checkbox" name="is_published" value="1" aria-describedby="page-published-help"<?= !empty($page['is_published']) ? ' checked' : '' ?>>
        Zveřejnit na webu
      </label>
    </div>
    <small id="page-published-help" class="field-help">Když volbu vypnete, stránka se na veřejném webu nezobrazí.</small>

    <div class="admin-field-row">
      <label class="admin-checkbox-label">
        <input type="checkbox" id="show_in_nav" name="show_in_nav" value="1" aria-describedby="page-nav-help"<?= !$isBlogPage && !empty($page['show_in_nav']) ? ' checked' : '' ?><?= $isBlogPage ? ' disabled aria-disabled="true"' : '' ?>>
        Zobrazit v hlavní navigaci
      </label>
    </div>
    <small id="page-nav-help" class="field-help">
      <?php if ($isBlogPage): ?>
        Blogové stránky se v hlavní navigaci webu nezobrazují. Pro tento obsah se použije navigace uvnitř blogu.
      <?php else: ?>
        Použije se jen u zveřejněné stránky. Skutečné pořadí v hlavní navigaci upravíte na stránce <a href="<?= BASE_URL ?>/admin/menu.php">Navigace webu</a>.
      <?php endif; ?>
    </small>

    <label for="article_status">Stav</label>
    <select id="article_status" name="article_status" aria-describedby="page-status-help">
      <option value="draft"<?= ($page['status'] ?? '') === 'draft' ? ' selected' : '' ?>>Koncept</option>
      <?php if (currentUserHasCapability('content_approve_shared')): ?>
        <option value="published"<?= ($page['status'] ?? 'published') === 'published' ? ' selected' : '' ?>>Publikováno</option>
      <?php endif; ?>
      <option value="pending"<?= ($page['status'] ?? '') === 'pending' ? ' selected' : '' ?>>Čeká na schválení</option>
    </select>
    <small id="page-status-help" class="field-help">Koncept je viditelný jen v administraci.</small>

    <label for="publish_at">Plánované publikování</label>
    <input type="datetime-local" id="publish_at" name="publish_at"
           <?= adminFieldAttributes('publish_at', $err, $fieldErrorMap, ['publish-at-help']) ?>
           class="admin-input-auto" value="<?= h($formatDateTimeLocalValue($page['publish_at'] ?? '')) ?>">
    <small id="publish-at-help" class="field-help">Nechte prázdné, pokud se má stránka zveřejnit hned.</small>
    <?php adminRenderFieldError('publish_at', $err, $fieldErrorMap, $fieldErrorMessages['publish_at']); ?>

    <label for="unpublish_at">Plánované zrušení publikace</label>
    <input type="datetime-local" id="unpublish_at" name="unpublish_at"
           <?= adminFieldAttributes('unpublish_at', $err, $fieldErrorMap, ['unpublish-at-help']) ?>
           class="admin-input-auto" value="<?= h($formatDateTimeLocalValue($page['unpublish_at'] ?? '')) ?>">
    <small id="unpublish-at-help" class="field-help">Volitelné. Obsah se v zadaný čas automaticky skryje z veřejného webu.</small>
    <?php adminRenderFieldError('unpublish_at', $err, $fieldErrorMap, $fieldErrorMessages['unpublish_at']); ?>
  </fieldset>

  <fieldset class="admin-fieldset-card admin-action-row">
    <legend>Interní poznámka</legend>
    <label for="admin_note" class="visually-hidden">Interní poznámka</label>
    <textarea id="admin_note" name="admin_note" rows="2" aria-describedby="admin-note-help"
              class="admin-textarea-compact"><?= h((string)($page['admin_note'] ?? '')) ?></textarea>
    <small id="admin-note-help" class="field-help">Viditelná jen v administraci. Na veřejném webu se nezobrazuje.</small>
  </fieldset>

  <div class="button-row admin-fieldset-spaced">
    <button type="submit" class="btn"><?= $id !== null ? 'Uložit změny' : 'Vytvořit stránku' ?></button>
    <a href="<?= h($redirect) ?>">Zrušit</a>
    <?php if ($publicPath !== ''): ?>
      <a href="<?= h($publicPath) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
    <?php endif; ?>
    <?php if ($id !== null && !empty($page['preview_token'])): ?>
      <a href="<?= h(pagePreviewPath($page)) ?>" target="_blank" rel="noopener noreferrer">Náhled<?= newWindowLinkSrOnlySuffix() ?></a>
    <?php elseif ($id !== null): ?>
      <small class="field-help field-help--flush">(Uložte pro aktivaci odkazu „Náhled")</small>
    <?php endif; ?>
  </div>
</form>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script nonce="<?= cspNonce() ?>" src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script nonce="<?= cspNonce() ?>">
(function () {
    const ta = document.getElementById('content');
    const wrapper = document.createElement('div');
    wrapper.className = 'admin-rich-editor-frame admin-rich-editor-tall';
    ta.parentNode.insertBefore(wrapper, ta);
    ta.hidden = true;
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

<?php if ($id !== null): ?>
<?php adminRenderContentLockRefreshScript('page', $id); ?>
<?php endif; ?>

<?php adminFooter(); ?>
