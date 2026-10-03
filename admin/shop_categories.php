<?php
require_once __DIR__ . '/../db.php';
$requestMethod = requireHttpMethods(['GET', 'HEAD', 'POST']);
requireSuperAdmin();
requireModuleEnabled('shop');
require_once __DIR__ . '/../lib/shop.php';
require_once __DIR__ . '/layout.php';
shopSafeHeaders();

$pdo = db_connect();
$categoryId = inputInt('get', 'id');
$values = ['name' => '', 'slug' => '', 'description' => '', 'sort_order' => '0', 'is_active' => '0'];
if ($categoryId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM cms_shop_categories WHERE id = ?');
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();
    if (!$category) {
        http_response_code(404);
        if ($requestMethod === 'HEAD') {
            exit;
        }
        adminHeader('Kategorie nenalezena');
        echo '<p class="error">Požadovaná kategorie neexistuje.</p><p><a href="shop_categories.php">Zpět na kategorie</a></p>';
        adminFooter();
        exit;
    }
    foreach ($values as $field => $default) {
        $values[$field] = (string)($category[$field] ?? $default);
    }
}
if ($requestMethod === 'HEAD') {
    exit;
}
$scopeKey = $categoryId === null ? 'new' : (string)$categoryId;
$formUrl = BASE_URL . '/admin/shop_categories.php' . ($categoryId !== null ? '?id=' . $categoryId : '');
$errors = [];
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach ($values as $field => $default) {
        $values[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    }
    $values['is_active'] = ($_POST['is_active'] ?? '') === '1' ? '1' : '0';
    if ($values['name'] === '' || mb_strlen($values['name']) > 190) {
        $errors['name'] = 'Doplňte název kategorie, nejvýše 190 znaků.';
    }
    $values['slug'] = shopSlug($values['slug'] !== '' ? $values['slug'] : $values['name']);
    if ($values['slug'] === '' || strlen($values['slug']) > 150) {
        $errors['slug'] = 'Doplňte platný jedinečný slug, nejvýše 150 znaků.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM cms_shop_categories WHERE slug = ? AND id <> ?');
        $stmt->execute([$values['slug'], $categoryId ?? 0]);
        if ($stmt->fetchColumn() !== false) {
            $errors['slug'] = 'Tento slug už používá jiná kategorie.';
        }
    }
    $sortOrder = filter_var($values['sort_order'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
    if ($sortOrder === false) {
        $errors['sort_order'] = 'Pořadí musí být celé nezáporné číslo.';
    }
    if (mb_strlen($values['description']) > 50000 || strlen($values['description']) > 65535) {
        $errors['description'] = 'Popis může mít nejvýše 50 000 znaků a 65 535 bajtů v UTF-8.';
    }
    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            if ($categoryId !== null) {
                $stmt = $pdo->prepare('SELECT id FROM cms_shop_categories WHERE id = ? FOR UPDATE');
                $stmt->execute([$categoryId]);
                if ($stmt->fetchColumn() === false) {
                    throw new DomainException('Kategorie již neexistuje.');
                }
                $pdo->prepare('UPDATE cms_shop_categories SET name = ?, slug = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?')
                    ->execute([$values['name'], $values['slug'], $values['description'], $sortOrder, (int)$values['is_active'], $categoryId]);
            } else {
                $pdo->prepare('INSERT INTO cms_shop_categories (name, slug, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$values['name'], $values['slug'], $values['description'], $sortOrder, (int)$values['is_active']]);
            }
            $pdo->commit();
            unset($_SESSION['shop_admin_category_flash'][$scopeKey]);
            $_SESSION['shop_admin_category_message'] = 'Kategorie byla uložena.';
            header('Location: ' . internalRedirectTarget(BASE_URL . '/admin/shop_categories.php'), true, 303);
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof PDOException && (string)$exception->getCode() === '23000') {
                $errors['slug'] = 'Slug musí být jedinečný. Zvolte jiný a akci opakujte.';
            } else {
                $message = 'Kategorii se nepodařilo uložit. Údaje byly zachovány.';
            }
        }
    }
    $_SESSION['shop_admin_category_flash'][$scopeKey] = ['values' => $values, 'errors' => $errors, 'message' => $message];
    header('Location: ' . internalRedirectTarget($formUrl), true, 303);
    exit;
}
$flash = $_SESSION['shop_admin_category_flash'][$scopeKey] ?? null;
unset($_SESSION['shop_admin_category_flash'][$scopeKey]);
if (is_array($flash)) {
    $values = array_replace($values, $flash['values'] ?? []);
    $errors = $flash['errors'] ?? [];
    $message = (string)($flash['message'] ?? '');
}
$success = $_SESSION['shop_admin_category_message'] ?? '';
unset($_SESSION['shop_admin_category_message']);
$fieldErrors = array_keys($errors);
$categories = $pdo->query('SELECT c.id, c.name, c.slug, c.is_active, c.sort_order,
    (SELECT COUNT(*) FROM cms_shop_products p WHERE p.category_id = c.id) AS product_count
    FROM cms_shop_categories c ORDER BY c.sort_order, c.name, c.id')->fetchAll();
$renderError = static function (string $field) use ($errors, $fieldErrors): void {
    adminRenderFieldError($field, $fieldErrors, [], $errors[$field] ?? '');
};
adminHeader('Kategorie digitálního obchodu');
?>
<p class="button-row"><a href="shop.php">Produkty</a><a href="shop_settings.php">Nastavení</a><a href="shop_orders.php">Objednávky</a></p>
<?php if (is_string($success) && $success !== ''): ?><p class="success" role="status"><?= h($success) ?></p><?php endif; ?>
<?php if ($errors !== [] || $message !== ''): ?>
  <div class="error" role="alert"><p><?= h($message ?: 'Kategorie nebyla uložena. Opravte označená pole.') ?></p>
    <?php if ($errors !== []): ?><ul><?php foreach ($errors as $field => $error): ?><li><a href="#<?= h($field) ?>"><?= h($error) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
<?php endif; ?>
<form action="<?= h($formUrl) ?>" method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset class="admin-fieldset-card">
    <legend><?= $categoryId !== null ? 'Upravit kategorii' : 'Nová kategorie' ?></legend>
    <label for="name">Název (povinné)</label>
    <input type="text" id="name" name="name" maxlength="190" required value="<?= h($values['name']) ?>"<?= adminFieldAttributes('name', $fieldErrors) ?>>
    <?php $renderError('name'); ?>
    <label for="slug">Slug</label>
    <input type="text" id="slug" name="slug" maxlength="150" value="<?= h($values['slug']) ?>"<?= adminFieldAttributes('slug', $fieldErrors, [], ['category-slug-help']) ?>>
    <small class="field-help" id="category-slug-help">Jedinečný v kategoriích obchodu. Prázdný slug se odvodí z názvu.</small>
    <?php $renderError('slug'); ?>
    <label for="description">Popis (prostý text)</label>
    <textarea id="description" name="description" rows="4" maxlength="50000"<?= adminFieldAttributes('description', $fieldErrors) ?>><?= h($values['description']) ?></textarea>
    <?php $renderError('description'); ?>
    <label for="sort_order">Pořadí</label>
    <input type="number" id="sort_order" name="sort_order" min="0" max="2147483647" required value="<?= h($values['sort_order']) ?>"<?= adminFieldAttributes('sort_order', $fieldErrors) ?>>
    <?php $renderError('sort_order'); ?>
    <label for="is_active"><input type="checkbox" id="is_active" name="is_active" value="1"<?= $values['is_active'] === '1' ? ' checked' : '' ?> aria-describedby="category-active-help"> Aktivní kategorie</label>
    <small class="field-help" id="category-active-help">Kategorie se nemažou: deaktivace uchová vazby produktů i historii objednávek. Zkontrolujte také zveřejnění přiřazených produktů.</small>
    <p class="button-row"><button class="btn" type="submit">Uložit kategorii</button><?php if ($categoryId !== null): ?><a href="shop_categories.php">Zrušit úpravu / nová kategorie</a><?php endif; ?></p>
  </fieldset>
</form>
<div class="table-responsive" tabindex="0" role="region" aria-labelledby="shop-categories-caption">
  <table>
    <caption id="shop-categories-caption">Kategorie a počet přiřazených produktů</caption>
    <thead><tr><th scope="col">Název</th><th scope="col">Slug</th><th scope="col">Pořadí</th><th scope="col">Stav</th><th scope="col">Produkty</th><th scope="col">Akce</th></tr></thead>
    <tbody>
      <?php foreach ($categories as $category): ?>
        <tr><th scope="row"><?= h((string)$category['name']) ?></th><td><?= h((string)$category['slug']) ?></td><td><?= (int)$category['sort_order'] ?></td><td><?= (int)$category['is_active'] === 1 ? 'Aktivní' : 'Neaktivní' ?></td><td><?= (int)$category['product_count'] ?></td><td><a href="shop_categories.php?id=<?= (int)$category['id'] ?>">Upravit<span class="sr-only"> <?= h((string)$category['name']) ?></span></a></td></tr>
      <?php endforeach; ?>
      <?php if ($categories === []): ?><tr><td colspan="6">Zatím nejsou vytvořeny žádné kategorie.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php adminFooter(); ?>
