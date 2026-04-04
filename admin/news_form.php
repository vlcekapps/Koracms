<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$id = inputInt('get', 'id');
$item = null;

if ($id !== null) {
    if (canManageOwnNewsOnly()) {
        $stmt = $pdo->prepare("SELECT * FROM cms_news WHERE id = ? AND author_id = ? AND deleted_at IS NULL");
        $stmt->execute([$id, currentUserId()]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM cms_news WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
    }
    $item = $stmt->fetch() ?: null;
    if (!$item) {
        header('Location: news.php');
        exit;
    }
}

$err = trim((string)($_GET['err'] ?? ''));
$formError = match ($err) {
    'required' => 'Titulek a text novinky jsou povinné.',
    'slug' => 'Slug novinky je povinný a musí být unikátní.',
    'unpublish_at' => 'Plánované zrušení publikace musí být ve správném formátu.',
    default => '',
};
$fieldErrorMap = [
    'required' => ['title', 'content'],
    'slug' => ['slug'],
    'unpublish_at' => ['unpublish_at'],
];
$fieldErrorMessages = [
    'title' => 'Titulek novinky je povinný.',
    'content' => 'Text novinky je povinný.',
    'slug' => 'Slug novinky je povinný a musí být unikátní.',
    'unpublish_at' => 'Plánované zrušení publikace musí být ve správném formátu.',
];

$authorName = '';
if ($item && !empty($item['author_id'])) {
    $authorStmt = $pdo->prepare(
        "SELECT first_name, last_name, nickname, email
         FROM cms_users
         WHERE id = ?"
    );
    $authorStmt->execute([(int)$item['author_id']]);
    $authorRow = $authorStmt->fetch() ?: null;
    if ($authorRow) {
        $authorName = trim((string)($authorRow['nickname'] ?? ''));
        if ($authorName === '') {
            $authorName = trim(
                trim((string)($authorRow['first_name'] ?? '')) . ' ' . trim((string)($authorRow['last_name'] ?? ''))
            );
        }
        if ($authorName === '') {
            $authorName = trim((string)($authorRow['email'] ?? ''));
        }
    }
} elseif ($item === null) {
    $authorName = currentUserDisplayName();
}

adminHeader($item ? 'Upravit novinku' : 'Přidat novinku');
?>

<?php if ($item): ?>
  <p><a href="revisions.php?type=news&amp;id=<?= (int)$item['id'] ?>">Historie revizí</a></p>
<?php endif; ?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error"><?= h($formError) ?></p>
<?php endif; ?>

<?php if ($authorName !== ''): ?>
  <p style="color:#555;font-size:.9rem;margin-bottom:1rem">
    Autor: <strong><?= h($authorName) ?></strong>
  </p>
<?php endif; ?>

<form method="post" action="news_save.php" novalidate<?= $formError !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($item): ?>
    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Novinka</legend>

    <label for="title">Titulek <span aria-hidden="true">*</span></label>
    <input
      type="text"
      id="title"
      name="title"
      required
      aria-required="true"
      maxlength="255"
      <?= adminFieldAttributes('title', $err, $fieldErrorMap) ?>
      value="<?= h((string)($item['title'] ?? '')) ?>"
    >
    <?php adminRenderFieldError('title', $err, $fieldErrorMap, $fieldErrorMessages['title']); ?>

    <label for="slug">Slug (URL novinky) <span aria-hidden="true">*</span></label>
    <input
      type="text"
      id="slug"
      name="slug"
      required
      aria-required="true"
      maxlength="255"
      pattern="[a-z0-9\-]+"
      <?= adminFieldAttributes('slug', $err, $fieldErrorMap, ['news-slug-help']) ?>
      value="<?= h((string)($item['slug'] ?? '')) ?>"
    >
    <small id="news-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Používejte malá písmena, číslice a pomlčky.</small>
    <?php adminRenderFieldError('slug', $err, $fieldErrorMap, $fieldErrorMessages['slug']); ?>

    <label for="content">Text novinky <span aria-hidden="true">*</span></label>
    <textarea
      id="content"
      name="content"
      rows="8"
      required
      aria-required="true"
      <?= adminFieldAttributes('content', $err, $fieldErrorMap, ['news-content-help']) ?>
    ><?= h((string)($item['content'] ?? '')) ?></textarea>
    <small id="news-content-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small>
    <?php adminRenderFieldError('content', $err, $fieldErrorMap, $fieldErrorMessages['content']); ?>
    <?php renderAdminContentReferencePicker('content'); ?>
  </fieldset>

  <fieldset style="margin-top:1rem">
    <legend>Zveřejnění</legend>

    <p>
      <small>
        Novinka se po uložení zobrazí pod vlastním odkazem. Datum vytvoření se ukládá automaticky
        <?= $item ? '(původní datum zůstane zachované).' : '(datum a čas přidání).' ?>
      </small>
    </p>

    <label for="article_status">Stav</label>
    <select id="article_status" name="article_status" aria-describedby="news-status-help">
      <option value="draft"<?= ($item['status'] ?? '') === 'draft' ? ' selected' : '' ?>>Koncept</option>
      <?php if (currentUserHasCapability('news_approve')): ?>
        <option value="published"<?= ($item['status'] ?? 'published') === 'published' ? ' selected' : '' ?>>Publikováno</option>
      <?php endif; ?>
      <option value="pending"<?= ($item['status'] ?? '') === 'pending' ? ' selected' : '' ?>>Čeká na schválení</option>
    </select>
    <small id="news-status-help" class="field-help">Koncept je viditelný jen v administraci.</small>

    <label for="publish_at">Plánované publikování</label>
    <input
      type="datetime-local"
      id="publish_at"
      name="publish_at"
      style="width:auto"
      value="<?= h(!empty($item['publish_at']) ? date('Y-m-d\TH:i', strtotime((string)$item['publish_at'])) : '') ?>"
    >
    <small class="field-help">Nechte prázdné, pokud se má novinka zveřejnit hned.</small>

    <label for="unpublish_at">Plánované zrušení publikace</label>
    <input
      type="datetime-local"
      id="unpublish_at"
      name="unpublish_at"
      <?= adminFieldAttributes('unpublish_at', $err, $fieldErrorMap, ['unpublish-at-help']) ?>
      style="width:auto"
      value="<?= h(!empty($item['unpublish_at']) ? date('Y-m-d\TH:i', strtotime((string)$item['unpublish_at'])) : '') ?>"
    >
    <small id="unpublish-at-help" class="field-help">Volitelné. Obsah se v zadaný čas automaticky skryje z veřejného webu.</small>
    <?php adminRenderFieldError('unpublish_at', $err, $fieldErrorMap, $fieldErrorMessages['unpublish_at']); ?>

    <label for="admin_note">Interní poznámka</label>
    <textarea
      id="admin_note"
      name="admin_note"
      rows="2"
      aria-describedby="admin-note-help"
      style="min-height:0"
    ><?= h((string)($item['admin_note'] ?? '')) ?></textarea>
    <small id="admin-note-help" class="field-help">Viditelná jen v administraci. Na veřejném webu se nezobrazuje.</small>
  </fieldset>

  <fieldset style="margin-top:1rem">
    <legend>Vyhledávače a sdílení</legend>

    <label for="meta_title">Meta titulek</label>
    <input
      type="text"
      id="meta_title"
      name="meta_title"
      maxlength="160"
      value="<?= h((string)($item['meta_title'] ?? '')) ?>"
      aria-describedby="meta-title-help"
    >
    <small id="meta-title-help" class="field-help">Volitelné. Pokud pole nevyplníte, použije se titulek novinky.</small>

    <label for="meta_description">Meta popis</label>
    <textarea
      id="meta_description"
      name="meta_description"
      rows="3"
      maxlength="320"
      aria-describedby="meta-description-help"
    ><?= h((string)($item['meta_description'] ?? '')) ?></textarea>
    <small id="meta-description-help" class="field-help">Volitelné. Pokud pole nevyplníte, použije se automatický výtah z textu novinky.</small>
  </fieldset>

  <div style="margin-top:1rem">
    <button type="submit"><?= $item ? 'Uložit změny' : 'Přidat novinku' ?></button>
    <a href="news.php" style="margin-left:1rem">Zrušit</a>
    <?php if ($item && ($item['status'] ?? 'published') === 'published'): ?>
      <a href="<?= h(newsPublicPath($item)) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
    <?php endif; ?>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
(function () {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    let slugManual = <?= $item && !empty($item['slug']) ? 'true' : 'false' ?>;

    const slugify = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    slugInput?.addEventListener('input', function () {
        slugManual = this.value.trim() !== '';
    });

    titleInput?.addEventListener('input', function () {
        if (slugManual || !slugInput) {
            return;
        }
        slugInput.value = slugify(this.value);
    });
})();
</script>

<?php adminFooter(); ?>
