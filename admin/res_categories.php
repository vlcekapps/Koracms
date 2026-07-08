<?php
require_once __DIR__ . '/layout.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu kategorií rezervací nemáte potřebné oprávnění.');
requireModuleEnabled('reservations');

$pdo = db_connect();
$success = false;
$error = '';
$fieldErrors = [];
$fieldErrorMessages = [];
$editId = inputInt('get', 'edit');
$q = trim($_GET['q'] ?? '');
$formState = [
    'name' => '',
    'sort_order' => '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
    $updateId = inputInt('post', 'update_id');
    $formState = [
        'name' => $name,
        'sort_order' => (string)$sortOrder,
    ];
    $editId = $updateId;

    if ($name === '') {
        $error = 'Kategorii zdrojů rezervací nejde uložit bez názvu. U pole Název je konkrétní nápověda.';
        $fieldErrors[] = 'name';
        $fieldErrorMessages['name'] = 'Doplňte krátký název kategorie, například Konzultace.';
    } elseif ($updateId !== null) {
        $pdo->prepare("UPDATE cms_res_categories SET name = ?, sort_order = ? WHERE id = ?")->execute([$name, $sortOrder, $updateId]);
        logAction('res_cat_edit', "id={$updateId}, name=" . mb_substr($name, 0, 80));
        $success = true;
        $editId = null;
    } else {
        $pdo->prepare("INSERT INTO cms_res_categories (name, sort_order) VALUES (?, ?)")->execute([$name, $sortOrder]);
        logAction('res_cat_add', "name=" . mb_substr($name, 0, 80));
        $success = true;
        $formState = [
            'name' => '',
            'sort_order' => '0',
        ];
    }
}

$stmt = $pdo->prepare(
    "SELECT c.id, c.name, c.sort_order, COUNT(r.id) AS resource_count
     FROM cms_res_categories c
     LEFT JOIN cms_res_resources r ON r.category_id = c.id
     " . ($q !== '' ? "WHERE c.name LIKE ?" : '') . "
     GROUP BY c.id, c.name, c.sort_order
     ORDER BY c.sort_order, c.name"
);
$stmt->execute($q !== '' ? ['%' . $q . '%'] : []);
$categories = $stmt->fetchAll();

$deleteConfirmError = trim((string)($_GET['delete_error'] ?? '')) === 'confirm_required';
$deleteErrorId = inputInt('get', 'delete_error_id');
if ($deleteConfirmError) {
    $error = 'Kategorii zdrojů rezervací nejde smazat bez potvrzení kontroly dopadu. U pole Potvrzení smazání je konkrétní nápověda.';
}
$successMessage = $success ? 'Kategorie uložena.' : '';
if (trim((string)($_GET['deleted'] ?? '')) === '1') {
    $successMessage = 'Kategorie byla smazána.';
}
$createFormHasError = $error !== '' && $editId === null && !$deleteConfirmError;

adminHeader('Kategorie zdrojů rezervací');
?>
<?php if ($successMessage !== ''): ?><p class="success" role="status"><?= h($successMessage) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p id="res-category-form-error" class="error" role="alert" aria-atomic="true"><?= h($error) ?></p><?php endif; ?>

<form method="get" class="button-row button-row--baseline admin-stack-sm">
  <div>
    <label for="q">Hledat</label>
    <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Název kategorie" class="admin-search-input">
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== ''): ?>
    <a href="res_categories.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<form method="post" novalidate<?= $createFormHasError ? ' aria-describedby="res-category-form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset>
    <legend>Nová kategorie</legend>
    <div class="button-row button-row--baseline">
      <div>
        <label for="name">Název <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" required aria-required="true" maxlength="255"
               value="<?= h($editId === null ? $formState['name'] : '') ?>"
               <?= adminFieldAttributes('name', $editId === null ? $fieldErrors : [], [], ['res-category-name-help']) ?>>
        <small id="res-category-name-help" class="field-help">Zobrazuje se ve filtrech a v editoru rezervačních zdrojů.</small>
        <?php adminRenderFieldError('name', $editId === null ? $fieldErrors : [], [], $fieldErrorMessages['name'] ?? ''); ?>
      </div>
      <div>
        <label for="sort_order">Pořadí</label>
        <input type="number" id="sort_order" name="sort_order" min="0" class="admin-input-xs" value="<?= h($editId === null ? $formState['sort_order'] : '0') ?>">
      </div>
      <button type="submit" class="btn">Přidat kategorii</button>
    </div>
  </fieldset>
</form>

<h2>Existující kategorie</h2>
<?php if ($categories === []): ?>
  <p><?= $q !== '' ? 'Pro zvolený filtr tu teď nejsou žádné kategorie zdrojů rezervací.' : 'Zatím tu nejsou žádné kategorie zdrojů rezervací.' ?></p>
<?php else: ?>
  <table>
    <caption>Kategorie zdrojů rezervací</caption>
    <thead><tr><th scope="col">Název</th><th scope="col">Pořadí</th><th scope="col">Zdroje</th><th scope="col">Akce</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $category): ?>
      <?php
        $categoryId = (int)$category['id'];
        $deleteConfirmField = 'confirm_res_category_delete_' . $categoryId;
        $deleteConfirmId = 'confirm-res-category-delete-' . $categoryId;
        $deleteReviewId = 'res-category-delete-review-' . $categoryId;
        $deleteFieldErrorId = 'confirm-res-category-delete-' . $categoryId . '-error';
        $deleteHasError = $deleteConfirmError && $deleteErrorId === $categoryId;
        $deleteErrorFields = $deleteHasError ? [$deleteConfirmField] : [];
        ?>
      <tr>
        <?php if ($editId === $categoryId): ?>
          <?php $editCategoryHasError = $error !== '' && $editId === $categoryId && !$deleteConfirmError; ?>
          <td colspan="3">
            <form method="post" novalidate<?= $editCategoryHasError ? ' aria-describedby="res-category-form-error"' : '' ?>>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= $categoryId ?>">
              <fieldset class="admin-filter-fieldset button-row button-row--baseline">
                <legend class="sr-only">Upravit kategorii <?= h((string)$category['name']) ?></legend>
                <label for="name-<?= $categoryId ?>" class="sr-only">Název kategorie</label>
                <input type="text" id="name-<?= $categoryId ?>" name="name" required aria-required="true" maxlength="255"
                       value="<?= h($editCategoryHasError ? $formState['name'] : (string)$category['name']) ?>" class="admin-input-auto"
                       <?= adminFieldAttributes('name', $editCategoryHasError ? $fieldErrors : [], [], ['res-category-name-help-' . $categoryId], 'name-error-' . $categoryId) ?>>
                <small id="res-category-name-help-<?= $categoryId ?>" class="field-help">Doplňte krátký název kategorie zdrojů rezervací.</small>
                <?php adminRenderFieldError('name', $editCategoryHasError ? $fieldErrors : [], [], $fieldErrorMessages['name'] ?? '', 'name-error-' . $categoryId); ?>
                <label for="sort-<?= $categoryId ?>" class="sr-only">Pořadí</label>
                <input type="number" id="sort-<?= $categoryId ?>" name="sort_order" min="0" class="admin-input-xs"
                       value="<?= h($editCategoryHasError ? $formState['sort_order'] : (string)$category['sort_order']) ?>">
                <button type="submit" class="btn">Uložit</button>
                <a href="res_categories.php<?= $q !== '' ? '?q=' . rawurlencode($q) : '' ?>">Zrušit</a>
              </fieldset>
            </form>
          </td>
        <?php else: ?>
          <td><?= h((string)$category['name']) ?></td>
          <td><?= (int)$category['sort_order'] ?></td>
          <td><?= (int)$category['resource_count'] ?></td>
        <?php endif; ?>
        <td class="actions">
          <?php if ($editId !== $categoryId): ?>
            <a href="res_categories.php?edit=<?= $categoryId ?><?= $q !== '' ? '&amp;q=' . rawurlencode($q) : '' ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="res_cat_delete.php" method="post"
                class="admin-inline-form"
                novalidate<?= $deleteHasError ? ' aria-describedby="res-category-form-error"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= $categoryId ?>">
            <fieldset class="admin-inline-fieldset">
              <legend class="sr-only">Smazání kategorie rezervací <?= h((string)$category['name']) ?></legend>
              <p id="<?= h($deleteReviewId) ?>" class="field-help field-help--flush">
                Smazání odebere kategorii z <?= (int)$category['resource_count'] ?> rezervačních zdrojů. Zdroje i existující rezervace zůstanou zachované bez této kategorie.
              </p>
              <label for="<?= h($deleteConfirmId) ?>" class="admin-checkbox-label">
                <input
                  type="checkbox"
                  id="<?= h($deleteConfirmId) ?>"
                  name="<?= h($deleteConfirmField) ?>"
                  value="1"
                  required
                  aria-required="true"<?= adminFieldAttributes($deleteConfirmField, $deleteErrorFields, [], [$deleteReviewId], $deleteFieldErrorId) ?>>
                Potvrzuji smazání této kategorie rezervací.
              </label>
              <?php adminRenderFieldError($deleteConfirmField, $deleteErrorFields, [], 'Před smazáním kategorie potvrďte, že jste zkontrolovali dopad na rezervační zdroje.', $deleteFieldErrorId); ?>
              <button type="submit" class="btn btn-danger"
                      data-confirm="Smazat kategorii? Zdroje bez kategorie zůstanou zachovány.">Smazat</button>
            </fieldset>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<p><a href="res_resources.php"><span aria-hidden="true">&larr;</span> Zpět na zdroje rezervací</a></p>
<?php adminFooter(); ?>
