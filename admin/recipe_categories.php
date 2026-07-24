<?php

require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu kategorií receptů nemáte potřebné oprávnění.');
requireModuleEnabled('recipes');

$pdo = db_connect();
$error = '';
$fieldErrors = [];
$fieldErrorMessages = [];
$editId = inputInt('get', 'edit');
$state = [
    'id' => 0,
    'name' => '',
    'slug' => '',
    'description' => '',
    'meta_title' => '',
    'meta_description' => '',
    'is_active' => 1,
];

$categoryForId = static function (?int $categoryId) use ($pdo): ?array {
    if ($categoryId === null) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM cms_recipe_categories WHERE id = ?');
    $stmt->execute([$categoryId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
};
$redirectToCategories = static function (string $message = ''): void {
    $target = BASE_URL . '/admin/recipe_categories.php';
    if ($message !== '') {
        $target = appendUrlQuery($target, ['msg' => $message]);
    }
    header('Location: ' . $target);
    exit;
};
$moveCategory = static function (int $categoryId, string $direction) use ($pdo): void {
    if (!in_array($direction, ['up', 'down'], true)) {
        return;
    }
    $rows = $pdo->query(
        'SELECT id, sort_order FROM cms_recipe_categories ORDER BY sort_order, name, id'
    )->fetchAll();
    $index = null;
    foreach ($rows as $rowIndex => $row) {
        if ((int)$row['id'] === $categoryId) {
            $index = $rowIndex;
            break;
        }
    }
    if ($index === null) {
        return;
    }
    $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
    if (!isset($rows[$targetIndex])) {
        return;
    }
    $current = $rows[$index];
    $target = $rows[$targetIndex];
    $stmt = $pdo->prepare('UPDATE cms_recipe_categories SET sort_order = ? WHERE id = ?');
    $stmt->execute([(int)$target['sort_order'], (int)$current['id']]);
    $stmt->execute([(int)$current['sort_order'], (int)$target['id']]);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? 'save'));
    $categoryId = inputInt('post', 'category_id');

    if ($action === 'move') {
        $category = $categoryForId($categoryId);
        if ($category !== null) {
            $moveCategory((int)$category['id'], trim((string)($_POST['direction'] ?? '')));
            logAction('recipe_category_move', "id={$category['id']}");
        }
        $redirectToCategories('moved');
    }

    if ($action === 'toggle') {
        $category = $categoryForId($categoryId);
        if ($category !== null) {
            $newActive = (int)$category['is_active'] === 1 ? 0 : 1;
            if ($newActive === 0) {
                $countStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM cms_recipes
                     WHERE category_id = ? AND deleted_at IS NULL AND status = 'published'"
                );
                $countStmt->execute([(int)$category['id']]);
                if ((int)$countStmt->fetchColumn() > 0) {
                    $_SESSION['recipe_category_error'] =
                        'Kategorii nelze deaktivovat, dokud obsahuje publikované recepty. Nejprve recepty přesuňte nebo převeďte na koncept.';
                    $redirectToCategories();
                }
            }
            $pdo->prepare(
                'UPDATE cms_recipe_categories SET is_active = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$newActive, (int)$category['id']]);
            logAction('recipe_category_toggle', "id={$category['id']} active={$newActive}");
        }
        $redirectToCategories('saved');
    }

    $existing = $categoryForId($categoryId);
    $state = [
        'id' => $categoryId ?? 0,
        'name' => mb_substr(trim((string)($_POST['name'] ?? '')), 0, 255),
        'slug' => mb_substr(trim((string)($_POST['slug'] ?? '')), 0, 150),
        'description' => trim((string)($_POST['description'] ?? '')),
        'meta_title' => mb_substr(trim((string)($_POST['meta_title'] ?? '')), 0, 160),
        'meta_description' => mb_substr(trim((string)($_POST['meta_description'] ?? '')), 0, 320),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    if ($state['name'] === '') {
        $error = 'Kategorii nejde uložit bez názvu.';
        $fieldErrors[] = 'name';
        $fieldErrorMessages['name'] = 'Doplňte krátký název, například Polévky.';
    }
    $slug = recipeCategorySlug($state['slug'] !== '' ? $state['slug'] : $state['name']);
    if ($slug === '') {
        $error = 'Slug kategorie není použitelný.';
        $fieldErrors[] = 'slug';
        $fieldErrorMessages['slug'] = 'Použijte alespoň jedno písmeno nebo číslici.';
    } else {
        $uniqueSlug = uniqueRecipeCategorySlug($pdo, $slug, $categoryId);
        if ($state['slug'] !== '' && $uniqueSlug !== $slug) {
            $error = 'Tento slug už používá jiná kategorie receptů.';
            $fieldErrors[] = 'slug';
            $fieldErrorMessages['slug'] = 'Zvolte jiný slug veřejné stránky kategorie.';
        }
        $state['slug'] = $uniqueSlug;
    }
    if ($categoryId !== null && $existing === null) {
        $error = 'Upravovanou kategorii se nepodařilo najít.';
    }
    if ((int)$state['is_active'] === 0 && $existing !== null && (int)$existing['is_active'] === 1) {
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM cms_recipes
             WHERE category_id = ? AND deleted_at IS NULL AND status = 'published'"
        );
        $countStmt->execute([(int)$existing['id']]);
        if ((int)$countStmt->fetchColumn() > 0) {
            $error = 'Kategorii nelze deaktivovat, dokud obsahuje publikované recepty.';
            $fieldErrors[] = 'is_active';
            $fieldErrorMessages['is_active'] = 'Nejprve recepty přesuňte do jiné kategorie nebo je převeďte na koncept.';
        }
    }

    if ($error === '' && $existing !== null) {
        $oldPath = recipeCategoryPublicPath($existing);
        $pdo->prepare(
            'UPDATE cms_recipe_categories
             SET name = ?, slug = ?, description = ?, meta_title = ?, meta_description = ?,
                 is_active = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([
            $state['name'],
            $state['slug'],
            $state['description'],
            $state['meta_title'],
            $state['meta_description'],
            $state['is_active'],
            (int)$existing['id'],
        ]);
        if ((int)$existing['is_active'] === 1 && (int)$state['is_active'] === 1) {
            upsertPathRedirect($pdo, $oldPath, recipeCategoryPublicPath($state));
        } elseif ((int)$state['is_active'] === 0) {
            deleteRedirectsTargetingPath($pdo, $oldPath);
        }
        logAction('recipe_category_edit', "id={$existing['id']} slug={$state['slug']}");
        $redirectToCategories('saved');
    } elseif ($error === '') {
        $maxOrder = (int)$pdo->query(
            'SELECT COALESCE(MAX(sort_order), 0) + 10 FROM cms_recipe_categories'
        )->fetchColumn();
        $pdo->prepare(
            'INSERT INTO cms_recipe_categories
             (name, slug, description, meta_title, meta_description, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $state['name'],
            $state['slug'],
            $state['description'],
            $state['meta_title'],
            $state['meta_description'],
            max(10, $maxOrder),
            $state['is_active'],
        ]);
        logAction('recipe_category_add', 'id=' . (int)$pdo->lastInsertId() . " slug={$state['slug']}");
        $redirectToCategories('saved');
    }
    $editId = $categoryId;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $editId !== null) {
    $existing = $categoryForId($editId);
    if ($existing !== null) {
        $state = array_merge($state, $existing);
    } else {
        $editId = null;
    }
}

$sessionError = trim((string)($_SESSION['recipe_category_error'] ?? ''));
unset($_SESSION['recipe_category_error']);
if ($error === '' && $sessionError !== '') {
    $error = $sessionError;
}
$categories = $pdo->query(
    'SELECT c.*,
            (SELECT COUNT(*) FROM cms_recipes r WHERE r.category_id = c.id AND r.deleted_at IS NULL) AS recipe_count,
            (SELECT COUNT(*) FROM cms_recipes r WHERE r.category_id = c.id AND r.deleted_at IS NULL
                AND r.status = \'published\' AND (r.publish_at IS NULL OR r.publish_at <= NOW())) AS public_count
     FROM cms_recipe_categories c
     ORDER BY c.sort_order, c.name, c.id'
)->fetchAll();
$message = match (trim((string)($_GET['msg'] ?? ''))) {
    'saved' => 'Kategorie receptů byla uložena.',
    'moved' => 'Pořadí kategorií bylo změněno.',
    default => '',
};

adminHeader('Kategorie receptů');
?>
<p><a href="recipes.php"><span aria-hidden="true">←</span> Zpět na recepty</a></p>
<?php if ($message !== ''): ?><p class="success" role="status"><?= h($message) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert" id="recipe-category-error"><?= h($error) ?></p><?php endif; ?>

<form method="post" novalidate<?= $error !== '' ? ' aria-describedby="recipe-category-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="action" value="save">
  <?php if ((int)$state['id'] > 0): ?><input type="hidden" name="category_id" value="<?= (int)$state['id'] ?>"><?php endif; ?>
  <fieldset>
    <legend><?= (int)$state['id'] > 0 ? 'Upravit kategorii' : 'Přidat kategorii' ?></legend>
    <label for="name">Název <span aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" maxlength="255" required aria-required="true"
           value="<?= h((string)$state['name']) ?>" <?= adminFieldAttributes('name', $fieldErrors) ?>>
    <?php adminRenderFieldError('name', $fieldErrors, [], $fieldErrorMessages['name'] ?? ''); ?>
    <label for="slug">Slug, volitelné</label>
    <input type="text" id="slug" name="slug" maxlength="150" pattern="[a-z0-9\-]+"
           value="<?= h((string)$state['slug']) ?>" <?= adminFieldAttributes('slug', $fieldErrors, [], ['recipe-category-slug-help']) ?>>
    <small id="recipe-category-slug-help" class="field-help">Prázdný slug se vytvoří z názvu. Změna slugu aktivní kategorie vytvoří trvalý redirect.</small>
    <?php adminRenderFieldError('slug', $fieldErrors, [], $fieldErrorMessages['slug'] ?? ''); ?>
    <label for="description">Popis kategorie</label>
    <textarea id="description" name="description" rows="5"><?= h((string)$state['description']) ?></textarea>
    <label for="meta_title">Meta title</label>
    <input type="text" id="meta_title" name="meta_title" maxlength="160" value="<?= h((string)$state['meta_title']) ?>">
    <label for="meta_description">Meta description</label>
    <textarea id="meta_description" name="meta_description" rows="3" maxlength="320"><?= h((string)$state['meta_description']) ?></textarea>
    <label class="admin-checkbox-label">
      <input type="checkbox" name="is_active" value="1"<?= (int)$state['is_active'] === 1 ? ' checked' : '' ?>
             <?= adminFieldAttributes('is_active', $fieldErrors, [], ['recipe-category-active-help']) ?>>
      Aktivní veřejná kategorie
    </label>
    <small id="recipe-category-active-help" class="field-help">Kategorii s publikovanými recepty nelze deaktivovat, dokud recepty nepřesunete nebo neskryjete.</small>
    <?php adminRenderFieldError('is_active', $fieldErrors, [], $fieldErrorMessages['is_active'] ?? ''); ?>
    <div class="button-row">
      <button type="submit" class="btn"><?= (int)$state['id'] > 0 ? 'Uložit kategorii' : 'Přidat kategorii' ?></button>
      <?php if ((int)$state['id'] > 0): ?><a href="recipe_categories.php">Zrušit úpravu</a><?php endif; ?>
    </div>
  </fieldset>
</form>

<div class="table-responsive">
  <table>
    <caption>Přehled a pořadí kategorií receptů</caption>
    <thead><tr><th scope="col">Kategorie</th><th scope="col">Recepty</th><th scope="col">Stav</th><th scope="col">Akce</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $category): ?>
      <?php $categoryId = (int)$category['id']; ?>
      <tr>
        <td><strong><?= h((string)$category['name']) ?></strong><br><small class="table-meta">/recipes/kategorie/<?= h((string)$category['slug']) ?></small></td>
        <td><?= (int)$category['recipe_count'] ?> celkem, <?= (int)$category['public_count'] ?> veřejných</td>
        <td><?= (int)$category['is_active'] === 1 ? 'Aktivní' : 'Neaktivní' ?></td>
        <td>
          <a href="recipe_categories.php?edit=<?= $categoryId ?>#name">Upravit</a>
          <?php if ((int)$category['is_active'] === 1): ?>
            <a href="<?= h(recipeCategoryPublicPath($category)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit<?= newWindowLinkSrOnlySuffix() ?></a>
            <?php if ((int)$category['public_count'] > 0): ?>
              <a href="<?= h(recipeCookbookPublicPath((string)$category['slug'])) ?>" target="_blank" rel="noopener noreferrer">EPUB<?= newWindowLinkSrOnlySuffix() ?></a>
            <?php endif; ?>
          <?php endif; ?>
          <form method="post" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="move"><input type="hidden" name="category_id" value="<?= $categoryId ?>">
            <button type="submit" name="direction" value="up" class="btn">Nahoru</button><button type="submit" name="direction" value="down" class="btn">Dolů</button>
          </form>
          <form method="post" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="category_id" value="<?= $categoryId ?>">
            <button type="submit" class="btn"><?= (int)$category['is_active'] === 1 ? 'Deaktivovat' : 'Aktivovat' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php adminFooter(); ?>
