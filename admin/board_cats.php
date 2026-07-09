<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu kategorií úřední desky nemáte potřebné oprávnění.');
requireModuleEnabled('board');

$pdo = db_connect();
$success = false;
$error = '';
$fieldErrors = [];
$fieldErrorMessages = [];
$deleteConfirmError = trim((string)($_GET['delete_error'] ?? '')) === 'confirm_required';
$deleteErrorId = inputInt('get', 'delete_error_id');

$editId = inputInt('get', 'edit');
$formState = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'meta_title' => '',
    'meta_description' => '',
    'sort_order' => '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $updateId = inputInt('post', 'update_id');
    $formState = [
        'name' => trim((string)($_POST['name'] ?? '')),
        'slug' => trim((string)($_POST['slug'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'meta_title' => trim((string)($_POST['meta_title'] ?? '')),
        'meta_description' => trim((string)($_POST['meta_description'] ?? '')),
        'sort_order' => (string)max(0, (int)($_POST['sort_order'] ?? 0)),
    ];
    $editId = $updateId;

    if ($formState['name'] === '') {
        $error = 'Kategorii vývěsky nejde uložit bez názvu. U pole Název je konkrétní nápověda.';
        $fieldErrors[] = 'name';
        $fieldErrorMessages['name'] = 'Doplňte krátký název kategorie, například Úřední oznámení.';
    } elseif (mb_strlen($formState['meta_title'], 'UTF-8') > 160) {
        $error = 'Meta title kategorie vývěsky je příliš dlouhý. U pole Meta title je konkrétní nápověda.';
        $fieldErrors[] = 'meta_title';
        $fieldErrorMessages['meta_title'] = 'Zkraťte meta title nejvýše na 160 znaků, nebo pole nechte prázdné.';
    } else {
        $submittedSlug = boardCategorySlug($formState['slug'] !== '' ? $formState['slug'] : $formState['name']);
        if ($submittedSlug === '') {
            $error = 'Slug veřejné kategorie vývěsky není možné vytvořit. U pole Slug je konkrétní nápověda.';
            $fieldErrors[] = 'slug';
            $fieldErrorMessages['slug'] = 'Použijte alespoň jedno písmeno nebo číslo. Vhodný slug může vypadat třeba uredni-oznameni.';
        } else {
            $uniqueSlug = uniqueBoardCategorySlug($pdo, $submittedSlug, $updateId);
            if ($formState['slug'] !== '' && $uniqueSlug !== $submittedSlug) {
                $error = 'Slug veřejné kategorie vývěsky už používá jiná kategorie. U pole Slug je konkrétní nápověda.';
                $fieldErrors[] = 'slug';
                $fieldErrorMessages['slug'] = 'Zadejte jiný unikátní slug, nebo pole nechte prázdné a CMS ho vytvoří z názvu.';
            } else {
                $slug = $uniqueSlug;
                if ($updateId !== null) {
                    $existingStmt = $pdo->prepare("SELECT * FROM cms_board_categories WHERE id = ?");
                    $existingStmt->execute([$updateId]);
                    $existingCategory = $existingStmt->fetch() ?: null;
                    if (!$existingCategory) {
                        $error = 'Upravovaná kategorie neexistuje.';
                    } else {
                        $pdo->prepare(
                            "UPDATE cms_board_categories
                             SET name = ?, slug = ?, description = ?, meta_title = ?, meta_description = ?, sort_order = ?
                             WHERE id = ?"
                        )->execute([
                            $formState['name'],
                            $slug,
                            $formState['description'],
                            $formState['meta_title'],
                            $formState['meta_description'],
                            (int)$formState['sort_order'],
                            $updateId,
                        ]);

                        if (boardCategoryPath($existingCategory) !== boardCategoryPath(['id' => $updateId, 'slug' => $slug])) {
                            upsertPathRedirect(
                                $pdo,
                                boardCategoryPath($existingCategory),
                                boardCategoryPath(['id' => $updateId, 'slug' => $slug])
                            );
                        }
                        logAction('board_cat_edit', "id={$updateId} name={$formState['name']} slug={$slug}");
                        $success = true;
                        $editId = null;
                    }
                } else {
                    $pdo->prepare(
                        "INSERT INTO cms_board_categories
                         (name, slug, description, meta_title, meta_description, sort_order)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    )->execute([
                        $formState['name'],
                        $slug,
                        $formState['description'],
                        $formState['meta_title'],
                        $formState['meta_description'],
                        (int)$formState['sort_order'],
                    ]);
                    logAction('board_cat_add', "name={$formState['name']} slug={$slug}");
                    $success = true;
                    $formState = [
                        'name' => '',
                        'slug' => '',
                        'description' => '',
                        'meta_title' => '',
                        'meta_description' => '',
                        'sort_order' => '0',
                    ];
                }
            }
        }
    }
}

if ($deleteConfirmError) {
    $error = 'Kategorii vývěsky nejde smazat bez potvrzení kontroly dopadu. U pole Potvrzení smazání je konkrétní nápověda.';
}
$successMessage = $success ? 'Kategorie uložena.' : '';
if (trim((string)($_GET['deleted'] ?? '')) === '1') {
    $successMessage = 'Kategorie byla smazána.';
}
$createFormHasError = $error !== '' && $editId === null && !$deleteConfirmError;

$categories = $pdo->query(
    "SELECT c.id, c.name, c.slug, c.description, c.meta_title, c.meta_description, c.sort_order, c.updated_at,
            COUNT(DISTINCT b.id) AS board_count,
            COUNT(DISTINCT sc.subscriber_id) AS subscriber_count
     FROM cms_board_categories c
     LEFT JOIN cms_board b ON b.category_id = c.id AND b.deleted_at IS NULL
     LEFT JOIN cms_board_subscriber_categories sc ON sc.category_id = c.id
     GROUP BY c.id, c.name, c.slug, c.description, c.meta_title, c.meta_description, c.sort_order, c.updated_at
     ORDER BY c.sort_order, c.name"
)->fetchAll();

adminHeader('Vývěska a oznámení – kategorie');
?>
<?php if ($successMessage !== ''): ?><p class="success" role="status"><?= h($successMessage) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p id="form-error" class="error" role="alert" aria-atomic="true"><?= h($error) ?></p><?php endif; ?>

<form method="post" novalidate<?= $createFormHasError ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset>
    <legend>Nová kategorie</legend>
    <div class="form-grid">
      <div class="form-group">
        <label for="name">Název <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" required aria-required="true" maxlength="255"
               value="<?= h($editId === null ? $formState['name'] : '') ?>"
               <?= adminFieldAttributes('name', $editId === null ? $fieldErrors : [], [], ['name-help']) ?>>
        <small id="name-help" class="field-help">Zobrazuje se ve filtrech, na landing stránce i v detailu položky.</small>
        <?php adminRenderFieldError('name', $editId === null ? $fieldErrors : [], [], $fieldErrorMessages['name'] ?? ''); ?>
      </div>
      <div class="form-group">
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" maxlength="150"
               value="<?= h($editId === null ? $formState['slug'] : '') ?>"
               <?= adminFieldAttributes('slug', $editId === null ? $fieldErrors : [], [], ['slug-help']) ?>>
        <small id="slug-help" class="field-help">Volitelné. Pokud zůstane prázdný, vytvoří se automaticky z názvu.</small>
        <?php adminRenderFieldError('slug', $editId === null ? $fieldErrors : [], [], $fieldErrorMessages['slug'] ?? ''); ?>
      </div>
      <div class="form-group">
        <label for="sort_order">Pořadí</label>
        <input type="number" id="sort_order" name="sort_order" min="0"
               value="<?= h($editId === null ? $formState['sort_order'] : '0') ?>">
      </div>
    </div>

    <div class="form-group">
      <label for="description">Popis</label>
      <textarea id="description" name="description" rows="4" aria-describedby="description-help"><?= h($editId === null ? $formState['description'] : '') ?></textarea>
      <small id="description-help" class="field-help">Zobrazí se na veřejné stránce kategorie nad výpisem položek. <?= adminHtmlSnippetSupportMarkup() ?></small>
      <?php renderAdminContentReferencePicker('description'); ?>
    </div>

    <div class="form-grid">
      <div class="form-group">
        <label for="meta_title">Meta title</label>
        <input type="text" id="meta_title" name="meta_title" maxlength="160"
               value="<?= h($editId === null ? $formState['meta_title'] : '') ?>"
               <?= adminFieldAttributes('meta_title', $editId === null ? $fieldErrors : []) ?>>
        <?php adminRenderFieldError('meta_title', $editId === null ? $fieldErrors : [], [], $fieldErrorMessages['meta_title'] ?? ''); ?>
      </div>
      <div class="form-group">
        <label for="meta_description">Meta description</label>
        <textarea id="meta_description" name="meta_description" rows="3"><?= h($editId === null ? $formState['meta_description'] : '') ?></textarea>
      </div>
    </div>

    <button type="submit">Přidat kategorii</button>
  </fieldset>
</form>

<h2>Existující kategorie</h2>
<?php if (empty($categories)): ?>
  <p>Zatím tu nejsou žádné kategorie.</p>
<?php else: ?>
  <table>
    <caption>Kategorie úřední desky</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Slug</th>
        <th scope="col">Pořadí</th>
        <th scope="col">Veřejná stránka</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($categories as $category): ?>
      <?php
        $categoryId = (int)$category['id'];
        $deleteConfirmField = 'confirm_board_category_delete_' . $categoryId;
        $deleteConfirmId = 'confirm-board-category-delete-' . $categoryId;
        $deleteReviewId = 'board-category-delete-review-' . $categoryId;
        $deleteFieldErrorId = 'confirm-board-category-delete-' . $categoryId . '-error';
        $deleteHasError = $deleteConfirmError && $deleteErrorId === $categoryId;
        $deleteErrorFields = $deleteHasError ? [$deleteConfirmField] : [];
        ?>
      <tr>
        <?php if ($editId === $categoryId): ?>
          <?php $editCategoryHasError = $error !== '' && $editId === $categoryId && !$deleteConfirmError; ?>
          <td colspan="4">
            <form method="post" novalidate<?= $editCategoryHasError ? ' aria-describedby="form-error"' : '' ?>>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= $categoryId ?>">
              <fieldset>
                <legend>Upravit kategorii <?= h((string)$category['name']) ?></legend>
                <div class="form-grid">
                  <div class="form-group">
                    <label for="name-<?= $categoryId ?>">Název <span aria-hidden="true">*</span></label>
                    <input type="text" id="name-<?= $categoryId ?>" name="name" required aria-required="true" maxlength="255"
                           value="<?= h($editCategoryHasError ? $formState['name'] : (string)$category['name']) ?>"
                           <?= adminFieldAttributes('name', $editCategoryHasError ? $fieldErrors : [], [], ['name-help-' . $categoryId], 'name-error-' . $categoryId) ?>>
                    <small id="name-help-<?= $categoryId ?>" class="field-help">Použijte krátký název, který správci i návštěvníci poznají ve filtrech vývěsky.</small>
                    <?php adminRenderFieldError('name', $editCategoryHasError ? $fieldErrors : [], [], $fieldErrorMessages['name'] ?? '', 'name-error-' . $categoryId); ?>
                  </div>
                  <div class="form-group">
                    <label for="slug-<?= $categoryId ?>">Slug</label>
                    <input type="text" id="slug-<?= $categoryId ?>" name="slug" maxlength="150"
                           value="<?= h($editCategoryHasError ? $formState['slug'] : (string)$category['slug']) ?>"
                           <?= adminFieldAttributes('slug', $editCategoryHasError ? $fieldErrors : [], [], ['slug-help-' . $categoryId], 'slug-error-' . $categoryId) ?>>
                    <small id="slug-help-<?= $categoryId ?>" class="field-help">Volitelné. Když pole necháte prázdné, CMS slug vytvoří z názvu.</small>
                    <?php adminRenderFieldError('slug', $editCategoryHasError ? $fieldErrors : [], [], $fieldErrorMessages['slug'] ?? '', 'slug-error-' . $categoryId); ?>
                  </div>
                  <div class="form-group">
                    <label for="sort-<?= $categoryId ?>">Pořadí</label>
                    <input type="number" id="sort-<?= $categoryId ?>" name="sort_order" min="0"
                           value="<?= h($editCategoryHasError ? $formState['sort_order'] : (string)$category['sort_order']) ?>">
                  </div>
                </div>
                <div class="form-group">
                  <label for="description-<?= $categoryId ?>">Popis</label>
                  <textarea id="description-<?= $categoryId ?>" name="description" rows="4" aria-describedby="description-help-<?= $categoryId ?>"><?= h($editCategoryHasError ? $formState['description'] : (string)($category['description'] ?? '')) ?></textarea>
                  <small id="description-help-<?= $categoryId ?>" class="field-help">Zobrazí se na veřejné stránce kategorie nad výpisem položek. <?= adminHtmlSnippetSupportMarkup() ?></small>
                  <?php renderAdminContentReferencePicker('description-' . $categoryId); ?>
                </div>
                <div class="form-grid">
                  <div class="form-group">
                    <label for="meta-title-<?= $categoryId ?>">Meta title</label>
                    <input type="text" id="meta-title-<?= $categoryId ?>" name="meta_title" maxlength="160"
                           value="<?= h($editCategoryHasError ? $formState['meta_title'] : (string)($category['meta_title'] ?? '')) ?>"
                           <?= adminFieldAttributes('meta_title', $editCategoryHasError ? $fieldErrors : [], [], [], 'meta-title-error-' . $categoryId) ?>>
                    <?php adminRenderFieldError('meta_title', $editCategoryHasError ? $fieldErrors : [], [], $fieldErrorMessages['meta_title'] ?? '', 'meta-title-error-' . $categoryId); ?>
                  </div>
                  <div class="form-group">
                    <label for="meta-description-<?= $categoryId ?>">Meta description</label>
                    <textarea id="meta-description-<?= $categoryId ?>" name="meta_description" rows="3"><?= h($editCategoryHasError ? $formState['meta_description'] : (string)($category['meta_description'] ?? '')) ?></textarea>
                  </div>
                </div>
                <div class="button-row button-row--start">
                  <button type="submit" class="btn">Uložit</button>
                  <a href="board_cats.php" class="btn">Zrušit</a>
                </div>
              </fieldset>
            </form>
          </td>
        <?php else: ?>
          <td>
            <strong><?= h((string)$category['name']) ?></strong>
            <?php if (trim((string)($category['description'] ?? '')) !== ''): ?>
              <br><small><?= h(mb_substr(normalizePlainText((string)$category['description']), 0, 120)) ?></small>
            <?php endif; ?>
          </td>
          <td><code><?= h((string)$category['slug']) ?></code></td>
          <td><?= (int)$category['sort_order'] ?></td>
          <td><a href="<?= h(boardCategoryPath($category)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a></td>
        <?php endif; ?>
        <td class="actions">
          <?php if ($editId !== $categoryId): ?>
            <a href="board_cats.php?edit=<?= $categoryId ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="board_cat_delete.php" method="post"
                class="admin-inline-form"
                novalidate<?= $deleteHasError ? ' aria-describedby="form-error"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= $categoryId ?>">
            <fieldset class="admin-inline-fieldset">
              <legend class="sr-only">Smazání kategorie vývěsky <?= h((string)$category['name']) ?></legend>
              <p id="<?= h($deleteReviewId) ?>" class="field-help field-help--flush">
                Smazání odebere kategorii z <?= (int)$category['board_count'] ?> položek vývěsky a z <?= (int)$category['subscriber_count'] ?> odběrů. Položky i odběratelé zůstanou zachovaní bez této kategorie.
              </p>
              <label for="<?= h($deleteConfirmId) ?>" class="admin-checkbox-label">
                <input
                  type="checkbox"
                  id="<?= h($deleteConfirmId) ?>"
                  name="<?= h($deleteConfirmField) ?>"
                  value="1"
                  required
                  aria-required="true"<?= adminFieldAttributes($deleteConfirmField, $deleteErrorFields, [], [$deleteReviewId], $deleteFieldErrorId) ?>>
                Potvrzuji smazání této kategorie vývěsky.
              </label>
              <?php adminRenderFieldError($deleteConfirmField, $deleteErrorFields, [], 'Před smazáním kategorie potvrďte, že jste zkontrolovali dopad na položky vývěsky a odběry.', $deleteFieldErrorId); ?>
              <button type="submit" class="btn btn-danger"
                      data-confirm="Smazat kategorii? Dokumenty bez kategorie zůstanou na desce.">Smazat</button>
            </fieldset>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<p><a href="board.php"><span aria-hidden="true">&larr;</span> Zpět na dokumenty a oznámení</a></p>
<?php adminFooter(); ?>
