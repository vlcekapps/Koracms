<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu kategorií úřední desky nemáte potřebné oprávnění.');
requireModuleEnabled('board');

$pdo = db_connect();
$success = false;
$error = '';

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
        $error = 'Název kategorie je povinný.';
    } elseif (mb_strlen($formState['meta_title'], 'UTF-8') > 160) {
        $error = 'Meta title může mít nejvýše 160 znaků.';
    } else {
        $submittedSlug = boardCategorySlug($formState['slug'] !== '' ? $formState['slug'] : $formState['name']);
        if ($submittedSlug === '') {
            $error = 'Slug kategorie musí obsahovat alespoň jedno písmeno nebo číslo.';
        } else {
            $uniqueSlug = uniqueBoardCategorySlug($pdo, $submittedSlug, $updateId);
            if ($formState['slug'] !== '' && $uniqueSlug !== $submittedSlug) {
                $error = 'Tento slug už používá jiná kategorie vývěsky.';
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

$categories = $pdo->query(
    "SELECT id, name, slug, description, meta_title, meta_description, sort_order, updated_at
     FROM cms_board_categories
     ORDER BY sort_order, name"
)->fetchAll();

adminHeader('Vývěska a oznámení – kategorie');
?>
<?php if ($success): ?><p class="success" role="status">Kategorie uložena.</p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset>
    <legend>Nová kategorie</legend>
    <div class="form-grid">
      <div class="form-group">
        <label for="name">Název <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" required aria-required="true" maxlength="255"
               value="<?= h($editId === null ? $formState['name'] : '') ?>"
               aria-describedby="name-help">
        <small id="name-help" class="field-help">Zobrazuje se ve filtrech, na landing stránce i v detailu položky.</small>
      </div>
      <div class="form-group">
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" maxlength="150"
               value="<?= h($editId === null ? $formState['slug'] : '') ?>"
               aria-describedby="slug-help">
        <small id="slug-help" class="field-help">Volitelné. Pokud zůstane prázdný, vytvoří se automaticky z názvu.</small>
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
      <small id="description-help" class="field-help">Zobrazí se na veřejné stránce kategorie nad výpisem položek.</small>
    </div>

    <div class="form-grid">
      <div class="form-group">
        <label for="meta_title">Meta title</label>
        <input type="text" id="meta_title" name="meta_title" maxlength="160"
               value="<?= h($editId === null ? $formState['meta_title'] : '') ?>">
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
      <tr>
        <?php if ($editId === (int)$category['id']): ?>
          <td colspan="4">
            <form method="post" novalidate>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= (int)$category['id'] ?>">
              <fieldset>
                <legend>Upravit kategorii <?= h((string)$category['name']) ?></legend>
                <div class="form-grid">
                  <div class="form-group">
                    <label for="name-<?= (int)$category['id'] ?>">Název <span aria-hidden="true">*</span></label>
                    <input type="text" id="name-<?= (int)$category['id'] ?>" name="name" required aria-required="true" maxlength="255"
                           value="<?= h($formState['name'] !== '' ? $formState['name'] : (string)$category['name']) ?>">
                  </div>
                  <div class="form-group">
                    <label for="slug-<?= (int)$category['id'] ?>">Slug</label>
                    <input type="text" id="slug-<?= (int)$category['id'] ?>" name="slug" maxlength="150"
                           value="<?= h($formState['slug'] !== '' ? $formState['slug'] : (string)$category['slug']) ?>">
                  </div>
                  <div class="form-group">
                    <label for="sort-<?= (int)$category['id'] ?>">Pořadí</label>
                    <input type="number" id="sort-<?= (int)$category['id'] ?>" name="sort_order" min="0"
                           value="<?= h($formState['sort_order'] !== '0' ? $formState['sort_order'] : (string)$category['sort_order']) ?>">
                  </div>
                </div>
                <div class="form-group">
                  <label for="description-<?= (int)$category['id'] ?>">Popis</label>
                  <textarea id="description-<?= (int)$category['id'] ?>" name="description" rows="4"><?= h($formState['description'] !== '' ? $formState['description'] : (string)($category['description'] ?? '')) ?></textarea>
                </div>
                <div class="form-grid">
                  <div class="form-group">
                    <label for="meta-title-<?= (int)$category['id'] ?>">Meta title</label>
                    <input type="text" id="meta-title-<?= (int)$category['id'] ?>" name="meta_title" maxlength="160"
                           value="<?= h($formState['meta_title'] !== '' ? $formState['meta_title'] : (string)($category['meta_title'] ?? '')) ?>">
                  </div>
                  <div class="form-group">
                    <label for="meta-description-<?= (int)$category['id'] ?>">Meta description</label>
                    <textarea id="meta-description-<?= (int)$category['id'] ?>" name="meta_description" rows="3"><?= h($formState['meta_description'] !== '' ? $formState['meta_description'] : (string)($category['meta_description'] ?? '')) ?></textarea>
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
          <?php if ($editId !== (int)$category['id']): ?>
            <a href="board_cats.php?edit=<?= (int)$category['id'] ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="board_cat_delete.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Smazat kategorii? Dokumenty bez kategorie zůstanou na desce.">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<p><a href="board.php"><span aria-hidden="true">&larr;</span> Zpět na dokumenty a oznámení</a></p>
<?php adminFooter(); ?>
