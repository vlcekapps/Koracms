<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu kategorií FAQ nemáte potřebné oprávnění.');
requireModuleEnabled('faq');

$pdo = db_connect();
$success = false;
$error = '';
$fieldErrors = [];
$fieldErrorMessages = [];
$editId = inputInt('get', 'edit');
$deleteConfirmError = trim((string)($_GET['delete_error'] ?? '')) === 'confirm_required';
$deleteErrorId = inputInt('get', 'delete_error_id');
$formState = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'meta_title' => '',
    'meta_description' => '',
    'sort_order' => '0',
    'parent_id' => '',
];

/**
 * @param array<int, list<array<string, mixed>>> $tree
 */
function renderFaqCategoryOptions(array $tree, int $parentId = 0, int $depth = 0, ?int $excludeId = null, ?int $selectedId = null): string
{
    $out = '';
    foreach ($tree[$parentId] ?? [] as $cat) {
        $cid = (int)$cat['id'];
        if ($cid === $excludeId) {
            continue;
        }

        $prefix = str_repeat('— ', $depth);
        $out .= '<option value="' . $cid . '"' . ($selectedId === $cid ? ' selected' : '') . '>'
            . h($prefix . (string)$cat['name'])
            . '</option>';
        $out .= renderFaqCategoryOptions($tree, $cid, $depth + 1, $excludeId, $selectedId);
    }

    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $updateId = inputInt('post', 'update_id');
    $parentId = inputInt('post', 'parent_id');
    $formState = [
        'name' => trim((string)($_POST['name'] ?? '')),
        'slug' => trim((string)($_POST['slug'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'meta_title' => trim((string)($_POST['meta_title'] ?? '')),
        'meta_description' => trim((string)($_POST['meta_description'] ?? '')),
        'sort_order' => (string)max(0, (int)($_POST['sort_order'] ?? 0)),
        'parent_id' => $parentId !== null ? (string)$parentId : '',
    ];
    $editId = $updateId;

    if ($parentId !== null) {
        $parentStmt = $pdo->prepare("SELECT id FROM cms_faq_categories WHERE id = ?");
        $parentStmt->execute([$parentId]);
        if (!$parentStmt->fetch() || ($updateId !== null && $parentId === $updateId)) {
            $parentId = null;
            $formState['parent_id'] = '';
        }
    }

    if ($formState['name'] === '') {
        $error = 'Kategorii FAQ nejde uložit bez názvu. U pole Název je konkrétní nápověda.';
        $fieldErrors[] = 'name';
        $fieldErrorMessages['name'] = 'Doplňte krátký název kategorie, například Účet a přihlášení.';
    } elseif (mb_strlen($formState['meta_title'], 'UTF-8') > 160) {
        $error = 'Meta title kategorie FAQ je příliš dlouhý. U pole Meta title je konkrétní nápověda.';
        $fieldErrors[] = 'meta_title';
        $fieldErrorMessages['meta_title'] = 'Zkraťte meta title nejvýše na 160 znaků, nebo pole nechte prázdné.';
    } else {
        $submittedSlug = faqCategorySlug($formState['slug'] !== '' ? $formState['slug'] : $formState['name']);
        if ($submittedSlug === '') {
            $error = 'Slug veřejné FAQ kategorie není možné vytvořit. U pole Slug je konkrétní nápověda.';
            $fieldErrors[] = 'slug';
            $fieldErrorMessages['slug'] = 'Použijte alespoň jedno písmeno nebo číslo. Vhodný slug může vypadat třeba ucet-prihlaseni.';
        } else {
            $uniqueSlug = uniqueFaqCategorySlug($pdo, $submittedSlug, $updateId);
            if ($formState['slug'] !== '' && $uniqueSlug !== $submittedSlug) {
                $error = 'Slug veřejné FAQ kategorie už používá jiná kategorie. U pole Slug je konkrétní nápověda.';
                $fieldErrors[] = 'slug';
                $fieldErrorMessages['slug'] = 'Zadejte jiný unikátní slug, nebo pole nechte prázdné a CMS ho vytvoří z názvu.';
            } elseif ($updateId !== null) {
                $existingStmt = $pdo->prepare("SELECT * FROM cms_faq_categories WHERE id = ?");
                $existingStmt->execute([$updateId]);
                $existingCategory = $existingStmt->fetch() ?: null;
                if (!$existingCategory) {
                    $error = 'Upravovaná kategorie neexistuje.';
                } else {
                    $pdo->prepare(
                        "UPDATE cms_faq_categories
                         SET name = ?, slug = ?, description = ?, meta_title = ?, meta_description = ?,
                             sort_order = ?, parent_id = ?, updated_at = NOW()
                         WHERE id = ?"
                    )->execute([
                        $formState['name'],
                        $uniqueSlug,
                        $formState['description'],
                        $formState['meta_title'],
                        $formState['meta_description'],
                        (int)$formState['sort_order'],
                        $parentId,
                        $updateId,
                    ]);

                    $updatedCategory = ['id' => $updateId, 'slug' => $uniqueSlug];
                    if (faqCategoryPath($existingCategory) !== faqCategoryPath($updatedCategory)) {
                        upsertPathRedirect($pdo, faqCategoryPath($existingCategory), faqCategoryPath($updatedCategory));
                    }

                    logAction('faq_cat_edit', "id={$updateId} name={$formState['name']} slug={$uniqueSlug}");
                    $success = true;
                    $editId = null;
                }
            } else {
                $pdo->prepare(
                    "INSERT INTO cms_faq_categories
                     (name, slug, description, meta_title, meta_description, sort_order, parent_id, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                )->execute([
                    $formState['name'],
                    $uniqueSlug,
                    $formState['description'],
                    $formState['meta_title'],
                    $formState['meta_description'],
                    (int)$formState['sort_order'],
                    $parentId,
                ]);

                logAction('faq_cat_add', "name={$formState['name']} slug={$uniqueSlug}");
                $success = true;
                $formState = [
                    'name' => '',
                    'slug' => '',
                    'description' => '',
                    'meta_title' => '',
                    'meta_description' => '',
                    'sort_order' => '0',
                    'parent_id' => '',
                ];
            }
        }
    }
}

$successMessage = $success ? 'Kategorie uložena.' : '';
if (trim((string)($_GET['deleted'] ?? '')) === '1') {
    $successMessage = 'Kategorie FAQ byla smazána.';
}
if ($deleteConfirmError) {
    $error = 'Kategorii FAQ nejde smazat bez potvrzení kontroly dopadu. U pole Potvrzení smazání je konkrétní nápověda.';
}
$createFormHasError = $error !== '' && $editId === null && !$deleteConfirmError;

$categories = $pdo->query(
    "SELECT c.id, c.name, c.slug, c.description, c.meta_title, c.meta_description,
            c.sort_order, c.parent_id, c.updated_at,
            COUNT(DISTINCT f.id) AS faq_count,
            COUNT(DISTINCT child.id) AS child_count
     FROM cms_faq_categories c
     LEFT JOIN cms_faqs f ON f.category_id = c.id AND f.deleted_at IS NULL
     LEFT JOIN cms_faq_categories child ON child.parent_id = c.id
     GROUP BY c.id, c.name, c.slug, c.description, c.meta_title, c.meta_description,
              c.sort_order, c.parent_id, c.updated_at
     ORDER BY c.sort_order, c.name"
)->fetchAll();

$categoryTree = [];
foreach ($categories as $cat) {
    $pid = $cat['parent_id'] !== null ? (int)$cat['parent_id'] : 0;
    $categoryTree[$pid][] = $cat;
}

adminHeader('Kategorie znalostní báze');
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
        <input type="text" id="name" name="name" class="admin-input-auto" required aria-required="true" maxlength="255"
               value="<?= h($editId === null ? $formState['name'] : '') ?>"
               <?= adminFieldAttributes('name', $editId === null ? $fieldErrors : [], [], ['faq-category-name-help']) ?>>
        <small id="faq-category-name-help" class="field-help">Zobrazuje se ve filtrech, na landing stránce i v drobečkové navigaci.</small>
        <?php adminRenderFieldError('name', $editId === null ? $fieldErrors : [], [], $fieldErrorMessages['name'] ?? ''); ?>
      </div>
      <div class="form-group">
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" class="admin-input-auto" maxlength="150" pattern="[a-z0-9\-]+"
               value="<?= h($editId === null ? $formState['slug'] : '') ?>"
               <?= adminFieldAttributes('slug', $editId === null ? $fieldErrors : [], [], ['faq-category-slug-help']) ?>>
        <small id="faq-category-slug-help" class="field-help">Volitelné. Veřejná adresa bude mít tvar <code>/faq/kategorie/slug-kategorie</code>.</small>
        <?php adminRenderFieldError('slug', $editId === null ? $fieldErrors : [], [], $fieldErrorMessages['slug'] ?? ''); ?>
      </div>
      <div class="form-group">
        <label for="parent_id">Nadřazená kategorie</label>
        <select id="parent_id" name="parent_id">
          <option value="">Kořenová kategorie</option>
          <?= renderFaqCategoryOptions($categoryTree, 0, 0, null, $editId === null && $formState['parent_id'] !== '' ? (int)$formState['parent_id'] : null) ?>
        </select>
      </div>
      <div class="form-group">
        <label for="sort_order">Pořadí</label>
        <input type="number" id="sort_order" name="sort_order" class="admin-input-auto" min="0"
               value="<?= h($editId === null ? $formState['sort_order'] : '0') ?>">
      </div>
    </div>

    <label for="description">Popis</label>
    <textarea id="description" name="description" rows="4" aria-describedby="faq-category-description-help"><?= h($editId === null ? $formState['description'] : '') ?></textarea>
    <small id="faq-category-description-help" class="field-help">Zobrazí se na veřejné stránce kategorie nad seznamem otázek.</small>

    <div class="form-grid">
      <div class="form-group">
        <label for="meta_title">Meta title</label>
        <input type="text" id="meta_title" name="meta_title" class="admin-input-auto" maxlength="160"
               value="<?= h($editId === null ? $formState['meta_title'] : '') ?>"
               <?= adminFieldAttributes('meta_title', $editId === null ? $fieldErrors : []) ?>>
        <?php adminRenderFieldError('meta_title', $editId === null ? $fieldErrors : [], [], $fieldErrorMessages['meta_title'] ?? ''); ?>
      </div>
      <div class="form-group">
        <label for="meta_description">Meta description</label>
        <textarea id="meta_description" name="meta_description" rows="3"><?= h($editId === null ? $formState['meta_description'] : '') ?></textarea>
      </div>
    </div>

    <div class="button-row button-row--baseline">
      <button type="submit" class="btn">Přidat kategorii</button>
    </div>
  </fieldset>
</form>

<h2>Přehled kategorií znalostní báze</h2>
<?php if (empty($categories)): ?>
  <p>Zatím tu nejsou žádné kategorie.</p>
<?php else: ?>
  <table>
    <caption>Přehled kategorií znalostní báze</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Slug</th>
        <th scope="col">Otázky</th>
        <th scope="col">Veřejná stránka</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($categories as $category): ?>
      <?php
        $categoryId = (int)$category['id'];
        $deleteConfirmField = 'confirm_faq_category_delete_' . $categoryId;
        $deleteConfirmId = 'confirm-faq-category-delete-' . $categoryId;
        $deleteReviewId = 'faq-category-delete-review-' . $categoryId;
        $deleteFieldErrorId = 'confirm-faq-category-delete-' . $categoryId . '-error';
        $deleteHasError = $deleteConfirmError && $deleteErrorId === $categoryId;
        $deleteErrorFields = $deleteHasError ? [$deleteConfirmField] : [];
        ?>
      <tr>
        <?php if ($editId === $categoryId): ?>
          <td colspan="5">
            <?php $categoryEditHasErrors = $fieldErrors !== [] && !$deleteConfirmError; ?>
            <form method="post" novalidate<?= $categoryEditHasErrors ? ' aria-describedby="form-error"' : '' ?>>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= $categoryId ?>">
              <fieldset>
                <legend>Upravit kategorii <?= h((string)$category['name']) ?></legend>
                <div class="form-grid">
                  <div class="form-group">
                    <label for="name-<?= (int)$category['id'] ?>">Název <span aria-hidden="true">*</span></label>
                    <input type="text" id="name-<?= (int)$category['id'] ?>" name="name" class="admin-input-auto" required aria-required="true" maxlength="255"
                           value="<?= h($formState['name'] !== '' ? $formState['name'] : (string)$category['name']) ?>"
                           <?= adminFieldAttributes('name', $editId === (int)$category['id'] ? $fieldErrors : [], [], [], 'name-error-' . (int)$category['id']) ?>>
                    <?php adminRenderFieldError('name', $editId === (int)$category['id'] ? $fieldErrors : [], [], $fieldErrorMessages['name'] ?? '', 'name-error-' . (int)$category['id']); ?>
                  </div>
                  <div class="form-group">
                    <label for="slug-<?= (int)$category['id'] ?>">Slug</label>
                    <input type="text" id="slug-<?= (int)$category['id'] ?>" name="slug" class="admin-input-auto" maxlength="150" pattern="[a-z0-9\-]+"
                           value="<?= h($formState['slug'] !== '' ? $formState['slug'] : (string)$category['slug']) ?>"
                           <?= adminFieldAttributes('slug', $editId === (int)$category['id'] ? $fieldErrors : [], [], [], 'slug-error-' . (int)$category['id']) ?>>
                    <?php adminRenderFieldError('slug', $editId === (int)$category['id'] ? $fieldErrors : [], [], $fieldErrorMessages['slug'] ?? '', 'slug-error-' . (int)$category['id']); ?>
                  </div>
                  <div class="form-group">
                    <label for="parent-<?= (int)$category['id'] ?>">Nadřazená kategorie</label>
                    <select id="parent-<?= (int)$category['id'] ?>" name="parent_id">
                      <option value="">Kořenová kategorie</option>
                      <?= renderFaqCategoryOptions($categoryTree, 0, 0, (int)$category['id'], $formState['parent_id'] !== '' ? (int)$formState['parent_id'] : ($category['parent_id'] !== null ? (int)$category['parent_id'] : null)) ?>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="sort-<?= (int)$category['id'] ?>">Pořadí</label>
                    <input type="number" id="sort-<?= (int)$category['id'] ?>" name="sort_order" class="admin-input-auto" min="0"
                           value="<?= h($formState['sort_order'] !== '0' ? $formState['sort_order'] : (string)$category['sort_order']) ?>">
                  </div>
                </div>
                <label for="description-<?= (int)$category['id'] ?>">Popis</label>
                <textarea id="description-<?= (int)$category['id'] ?>" name="description" rows="4"><?= h($formState['description'] !== '' ? $formState['description'] : (string)($category['description'] ?? '')) ?></textarea>
                <div class="form-grid">
                  <div class="form-group">
                    <label for="meta-title-<?= (int)$category['id'] ?>">Meta title</label>
                    <input type="text" id="meta-title-<?= (int)$category['id'] ?>" name="meta_title" class="admin-input-auto" maxlength="160"
                           value="<?= h($formState['meta_title'] !== '' ? $formState['meta_title'] : (string)($category['meta_title'] ?? '')) ?>"
                           <?= adminFieldAttributes('meta_title', $editId === (int)$category['id'] ? $fieldErrors : [], [], [], 'meta-title-error-' . (int)$category['id']) ?>>
                    <?php adminRenderFieldError('meta_title', $editId === (int)$category['id'] ? $fieldErrors : [], [], $fieldErrorMessages['meta_title'] ?? '', 'meta-title-error-' . (int)$category['id']); ?>
                  </div>
                  <div class="form-group">
                    <label for="meta-description-<?= (int)$category['id'] ?>">Meta description</label>
                    <textarea id="meta-description-<?= (int)$category['id'] ?>" name="meta_description" rows="3"><?= h($formState['meta_description'] !== '' ? $formState['meta_description'] : (string)($category['meta_description'] ?? '')) ?></textarea>
                  </div>
                </div>
                <div class="button-row button-row--baseline button-row--start">
                  <button type="submit" class="btn">Uložit</button>
                  <a href="faq_cats.php" class="btn">Zrušit</a>
                </div>
              </fieldset>
            </form>
          </td>
        <?php else: ?>
          <td>
            <strong><?= h((string)$category['name']) ?></strong>
            <?php if (trim((string)($category['description'] ?? '')) !== ''): ?>
              <br><small><?= h(mb_strimwidth(normalizePlainText((string)$category['description']), 0, 120, '…', 'UTF-8')) ?></small>
            <?php endif; ?>
          </td>
          <td><code><?= h((string)$category['slug']) ?></code></td>
          <td><?= (int)$category['faq_count'] ?></td>
          <td><a href="<?= h(faqCategoryPath($category)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a></td>
        <?php endif; ?>
        <td class="actions">
          <?php if ($editId !== $categoryId): ?>
            <a href="faq_cats.php?edit=<?= $categoryId ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="faq_cat_delete.php" method="post" class="admin-inline-form" novalidate<?= $deleteHasError ? ' aria-describedby="form-error"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= $categoryId ?>">
            <fieldset class="admin-inline-fieldset">
              <legend class="sr-only">Smazání FAQ kategorie <?= h((string)$category['name']) ?></legend>
              <p id="<?= h($deleteReviewId) ?>" class="field-help field-help--flush">
                Smazání odebere kategorii z <?= (int)$category['faq_count'] ?> otázek a <?= (int)$category['child_count'] ?> podkategorií přesune na kořen. Otázky i podkategorie zůstanou zachované bez této kategorie.
              </p>
              <label for="<?= h($deleteConfirmId) ?>" class="admin-checkbox-label">
                <input
                  type="checkbox"
                  id="<?= h($deleteConfirmId) ?>"
                  name="<?= h($deleteConfirmField) ?>"
                  value="1"
                  required
                  aria-required="true"<?= adminFieldAttributes($deleteConfirmField, $deleteErrorFields, [], [$deleteReviewId], $deleteFieldErrorId) ?>>
                Potvrzuji smazání této FAQ kategorie.
              </label>
              <?php adminRenderFieldError($deleteConfirmField, $deleteErrorFields, [], 'Před smazáním FAQ kategorie potvrďte, že jste zkontrolovali dopad na otázky a podkategorie.', $deleteFieldErrorId); ?>
              <button type="submit" class="btn btn-danger"
                      data-confirm="<?= h('Smazat kategorii? Otázky i podkategorie zůstanou zachované bez této kategorie.') ?>">Smazat</button>
            </fieldset>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<p><a href="faq.php"><span aria-hidden="true">&larr;</span> Zpět na znalostní bázi</a></p>

<?php adminFooter(); ?>
