<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$id = inputInt('get', 'id');
$item = null;

if ($id !== null) {
    if (canManageOwnNewsOnly()) {
        $stmt = $pdo->prepare("SELECT * FROM cms_news WHERE id = ? AND author_id = ?");
        $stmt->execute([$id, currentUserId()]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM cms_news WHERE id = ?");
        $stmt->execute([$id]);
    }
    $item = $stmt->fetch();
    if (!$item) {
        header('Location: news.php');
        exit;
    }
}

$err = trim($_GET['err'] ?? '');
$formError = match ($err) {
    'required' => 'Titulek a text novinky jsou povinné.',
    'slug' => 'Slug novinky je povinný a musí být unikátní.',
    default => '',
};

$authorName = '';
if ($item && !empty($item['author_id'])) {
    $authorStmt = $pdo->prepare(
        "SELECT first_name, last_name, nickname, email FROM cms_users WHERE id = ?"
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
    <legend>Obsah novinky</legend>

    <label for="title">Titulek <span aria-hidden="true">*</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           value="<?= h((string)($item['title'] ?? '')) ?>">

    <label for="slug">Slug (URL novinky) <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255" pattern="[a-z0-9\-]+"
           aria-describedby="news-slug-help"
           value="<?= h((string)($item['slug'] ?? '')) ?>">
    <small id="news-slug-help" class="field-help">Používejte malá písmena, číslice a pomlčky.</small>

    <label for="content">Text novinky <span aria-hidden="true">*</span></label>
    <textarea id="content" name="content" rows="8" required aria-required="true"><?= h((string)($item['content'] ?? '')) ?></textarea>

    <p>
      <small>
        Novinka se po uložení zobrazí pod vlastním odkazem. Datum vytvoření se ukládá automaticky
        <?= $item ? '(původní datum zůstane zachované).' : '(datum a čas přidání).' ?>
      </small>
    </p>

    <div style="margin-top:1rem">
      <button type="submit"><?= $item ? 'Uložit změny' : 'Přidat novinku' ?></button>
      <a href="news.php" style="margin-left:1rem">Zrušit</a>
      <?php if ($item && ($item['status'] ?? 'published') === 'published'): ?>
        <a href="<?= h(newsPublicPath($item)) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
      <?php endif; ?>
    </div>
  </fieldset>
</form>

<script>
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
