<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu kategorií ke stažení nemáte potřebné oprávnění.');
requireModuleEnabled('downloads');

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
    ];
    $editId = $updateId;

    if ($formState['name'] === '') {
        $error = 'Název kategorie je povinný.';
    } elseif (mb_strlen($formState['meta_title'], 'UTF-8') > 160) {
        $error = 'Meta title může mít nejvýše 160 znaků.';
    } else {
        $submittedSlug = downloadCategorySlug($formState['slug'] !== '' ? $formState['slug'] : $formState['name']);
        if ($submittedSlug === '') {
            $error = 'Slug kategorie musí obsahovat alespoň jedno písmeno nebo číslo.';
        } else {
            $uniqueSlug = uniqueDownloadCategorySlug($pdo, $submittedSlug, $updateId);
            if ($formState['slug'] !== '' && $uniqueSlug !== $submittedSlug) {
                $error = 'Tento slug už používá jiná kategorie ke stažení.';
            } elseif ($updateId !== null) {
                $existingStmt = $pdo->prepare("SELECT * FROM cms_dl_categories WHERE id = ?");
                $existingStmt->execute([$updateId]);
                $existingCategory = $existingStmt->fetch() ?: null;
                if (!$existingCategory) {
                    $error = 'Upravovaná kategorie neexistuje.';
                } else {
                    $pdo->prepare(
                        "UPDATE cms_dl_categories
                         SET name = ?, slug = ?, description = ?, meta_title = ?, meta_description = ?, updated_at = NOW()
                         WHERE id = ?"
                    )->execute([
                        $formState['name'],
                        $uniqueSlug,
                        $formState['description'],
                        $formState['meta_title'],
                        $formState['meta_description'],
                        $updateId,
                    ]);

                    $updatedCategoryForRedirect = ['id' => $updateId, 'slug' => $uniqueSlug];
                    if (downloadCategoryPath($existingCategory) !== downloadCategoryPath($updatedCategoryForRedirect)) {
                        upsertPathRedirect(
                            $pdo,
                            downloadCategoryPath($existingCategory),
                            downloadCategoryPath($updatedCategoryForRedirect)
                        );
                    }
                    logAction('download_cat_edit', "id={$updateId} name={$formState['name']} slug={$uniqueSlug}");
                    $success = true;
                    $editId = null;
                }
            } else {
                $pdo->prepare(
                    "INSERT INTO cms_dl_categories (name, slug, description, meta_title, meta_description)
                     VALUES (?, ?, ?, ?, ?)"
                )->execute([
                    $formState['name'],
                    $uniqueSlug,
                    $formState['description'],
                    $formState['meta_title'],
                    $formState['meta_description'],
                ]);
                logAction('download_cat_add', "name={$formState['name']} slug={$uniqueSlug}");
                $success = true;
                $formState = [
                    'name' => '',
                    'slug' => '',
                    'description' => '',
                    'meta_title' => '',
                    'meta_description' => '',
                ];
            }
        }
    }
}

$categories = $pdo->query(
    "SELECT c.id, c.name, c.slug, c.description, c.meta_title, c.meta_description, c.updated_at,
            COUNT(d.id) AS download_count
     FROM cms_dl_categories c
     LEFT JOIN cms_downloads d ON d.dl_category_id = c.id AND d.deleted_at IS NULL
     GROUP BY c.id, c.name, c.slug, c.description, c.meta_title, c.meta_description, c.updated_at
     ORDER BY c.name"
)->fetchAll();

adminHeader('Ke stažení – kategorie');
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
        <input type="text" id="name" name="name" class="admin-input-auto" required aria-required="true" maxlength="255"
               value="<?= h($editId === null ? $formState['name'] : '') ?>" aria-describedby="download-category-name-help">
        <small id="download-category-name-help" class="field-help">Zobrazuje se ve filtrech, na landing stránce i u položek ke stažení.</small>
      </div>
      <div class="form-group">
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" class="admin-input-auto" maxlength="150" pattern="[a-z0-9\-]+"
               value="<?= h($editId === null ? $formState['slug'] : '') ?>" aria-describedby="download-category-slug-help">
        <small id="download-category-slug-help" class="field-help">Volitelné. Veřejná adresa bude mít tvar <code>/downloads/kategorie/slug-kategorie</code>.</small>
      </div>
    </div>

    <label for="description">Popis</label>
    <textarea id="description" name="description" rows="4" aria-describedby="download-category-description-help"><?= h($editId === null ? $formState['description'] : '') ?></textarea>
    <small id="download-category-description-help" class="field-help">Zobrazí se na veřejné stránce kategorie nad výpisem položek.</small>

    <div class="form-grid">
      <div class="form-group">
        <label for="meta_title">Meta title</label>
        <input type="text" id="meta_title" name="meta_title" class="admin-input-auto" maxlength="160"
               value="<?= h($editId === null ? $formState['meta_title'] : '') ?>">
      </div>
      <div class="form-group">
        <label for="meta_description">Meta description</label>
        <textarea id="meta_description" name="meta_description" rows="3"><?= h($editId === null ? $formState['meta_description'] : '') ?></textarea>
      </div>
    </div>

    <div class="button-row button-row--baseline">
      <button type="submit" class="btn admin-action-row">Přidat kategorii</button>
    </div>
  </fieldset>
</form>

<h2>Existující kategorie</h2>
<?php if (empty($categories)): ?>
  <p>Zatím tu nejsou žádné kategorie.</p>
<?php else: ?>
  <table>
    <caption>Kategorie ke stažení</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Slug</th>
        <th scope="col">Položky</th>
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
                    <input type="text" id="name-<?= (int)$category['id'] ?>" name="name" class="admin-input-auto" required aria-required="true" maxlength="255"
                           value="<?= h($formState['name'] !== '' ? $formState['name'] : (string)$category['name']) ?>">
                  </div>
                  <div class="form-group">
                    <label for="slug-<?= (int)$category['id'] ?>">Slug</label>
                    <input type="text" id="slug-<?= (int)$category['id'] ?>" name="slug" class="admin-input-auto" maxlength="150" pattern="[a-z0-9\-]+"
                           value="<?= h($formState['slug'] !== '' ? $formState['slug'] : (string)$category['slug']) ?>">
                  </div>
                </div>
                <label for="description-<?= (int)$category['id'] ?>">Popis</label>
                <textarea id="description-<?= (int)$category['id'] ?>" name="description" rows="4"><?= h($formState['description'] !== '' ? $formState['description'] : (string)($category['description'] ?? '')) ?></textarea>
                <div class="form-grid">
                  <div class="form-group">
                    <label for="meta-title-<?= (int)$category['id'] ?>">Meta title</label>
                    <input type="text" id="meta-title-<?= (int)$category['id'] ?>" name="meta_title" class="admin-input-auto" maxlength="160"
                           value="<?= h($formState['meta_title'] !== '' ? $formState['meta_title'] : (string)($category['meta_title'] ?? '')) ?>">
                  </div>
                  <div class="form-group">
                    <label for="meta-description-<?= (int)$category['id'] ?>">Meta description</label>
                    <textarea id="meta-description-<?= (int)$category['id'] ?>" name="meta_description" rows="3"><?= h($formState['meta_description'] !== '' ? $formState['meta_description'] : (string)($category['meta_description'] ?? '')) ?></textarea>
                  </div>
                </div>
                <div class="button-row button-row--baseline">
                  <button type="submit" class="btn">Uložit</button>
                  <a href="dl_cats.php" class="btn">Zrušit</a>
                </div>
              </fieldset>
            </form>
          </td>
        <?php else: ?>
          <td>
            <strong><?= h((string)$category['name']) ?></strong>
            <?php if (trim((string)($category['description'] ?? '')) !== ''): ?>
              <br><small class="field-help"><?= h(mb_strimwidth(normalizePlainText((string)$category['description']), 0, 120, '…', 'UTF-8')) ?></small>
            <?php endif; ?>
          </td>
          <td><code><?= h((string)$category['slug']) ?></code></td>
          <td><?= (int)$category['download_count'] ?></td>
          <td><a href="<?= h(downloadCategoryPath($category)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a></td>
        <?php endif; ?>
        <td class="actions">
          <?php if ($editId !== (int)$category['id']): ?>
            <a href="dl_cats.php?edit=<?= (int)$category['id'] ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="dl_cat_delete.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm="<?= h('Smazat kategorii? Soubory bez kategorie se zobrazí v sekci „Ostatní“.') ?>">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<p><a href="downloads.php"><span aria-hidden="true">←</span> Zpět na soubory a položky</a></p>
<?php adminFooter(); ?>
