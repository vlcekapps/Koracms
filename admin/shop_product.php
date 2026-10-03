<?php
require_once __DIR__ . '/../db.php';
$requestMethod = requireHttpMethods(['GET', 'HEAD', 'POST']);
requireSuperAdmin();
requireModuleEnabled('shop');
require_once __DIR__ . '/../lib/shop.php';
require_once __DIR__ . '/layout.php';
shopSafeHeaders();

$pdo = db_connect();
$productId = inputInt('get', 'id');
$product = null;
if ($productId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM cms_shop_products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch() ?: null;
    if ($product === null) {
        http_response_code(404);
        if ($requestMethod === 'HEAD') {
            exit;
        }
        adminHeader('Produkt nenalezen');
        echo '<p class="error">Požadovaný produkt neexistuje.</p><p><a href="shop.php">Zpět na produkty</a></p>';
        adminFooter();
        exit;
    }
}
if ($requestMethod === 'HEAD') {
    exit;
}
$scopeKey = $productId === null ? 'new' : (string)$productId;
$formUrl = BASE_URL . '/admin/shop_product.php' . ($productId !== null ? '?id=' . $productId : '');
$values = [
    'title' => '', 'slug' => '', 'description' => '', 'requirements' => '',
    'license_text' => '', 'update_policy' => '', 'price' => '', 'tax_class' => 'general',
    'category_id' => '0', 'is_active' => '0',
];
if ($product !== null) {
    foreach ($values as $field => $default) {
        if ($field !== 'price') {
            $values[$field] = (string)($product[$field] ?? $default);
        }
    }
    $cents = (int)$product['price_cents'];
    $values['price'] = intdiv($cents, 100) . ',' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
}
$pendingUpload = $_SESSION['shop_admin_product_upload'][$scopeKey] ?? null;
if (!is_array($pendingUpload) || (int)($pendingUpload['expires_at'] ?? 0) < time()) {
    unset($_SESSION['shop_admin_product_upload'][$scopeKey]);
    $pendingUpload = null;
}
$fileMetadata = is_array($pendingUpload['metadata'] ?? null) ? $pendingUpload['metadata'] : ($product ?? []);
$hasReplacementFile = is_array($pendingUpload['metadata'] ?? null);
$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach ($values as $field => $default) {
        $values[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    }
    $values['is_active'] = ($_POST['is_active'] ?? '') === '1' ? '1' : '0';
    if ($values['title'] === '' || mb_strlen($values['title']) > 190) {
        $errors['title'] = 'Doplňte název produktu, nejvýše 190 znaků.';
    }
    $values['slug'] = shopSlug($values['slug'] !== '' ? $values['slug'] : $values['title']);
    if ($values['slug'] === '' || strlen($values['slug']) > 150) {
        $errors['slug'] = 'Doplňte platný slug, nejvýše 150 znaků.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM cms_shop_products WHERE slug = ? AND id <> ?');
        $stmt->execute([$values['slug'], $productId ?? 0]);
        if ($stmt->fetchColumn() !== false) {
            $errors['slug'] = 'Tento slug už používá jiný produkt.';
        }
    }
    $priceCents = shopAmountCents($values['price']);
    if ($priceCents === null || $priceCents <= 0 || $priceCents > 10000000000) {
        $errors['price'] = 'Zadejte kladnou cenu v CZK s nejvýše dvěma desetinnými místy.';
    }
    if (!in_array($values['tax_class'], ['general', 'publication'], true)) {
        $errors['tax_class'] = 'Vyberte obecnou třídu nebo publikaci.';
    }
    $selectedCategory = filter_var($values['category_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($selectedCategory === false) {
        $errors['category_id'] = 'Vyberte existující kategorii nebo volbu Bez kategorie.';
    } elseif ($selectedCategory === 0 && $values['is_active'] === '1') {
        $errors['category_id'] = 'Pro veřejný produkt vyberte existující aktivní kategorii.';
    } elseif ($selectedCategory > 0) {
        $stmt = $pdo->prepare('SELECT is_active FROM cms_shop_categories WHERE id = ?');
        $stmt->execute([$selectedCategory]);
        $categoryActive = $stmt->fetchColumn();
        if ($categoryActive === false || ($values['is_active'] === '1' && (int)$categoryActive !== 1)) {
            $errors['category_id'] = 'Pro aktivní produkt vyberte aktivní kategorii; kategorie musí existovat.';
        }
    }
    foreach (['description', 'requirements', 'license_text', 'update_policy'] as $field) {
        if (mb_strlen($values[$field]) > 50000 || strlen($values[$field]) > 65535) {
            $errors[$field] = 'Text může mít nejvýše 50 000 znaků a 65 535 bajtů v UTF-8.';
        } elseif ($values['is_active'] === '1' && $values[$field] === '') {
            $errors[$field] = 'Pro zveřejnění produktu je tento text povinný.';
        }
    }
    $upload = $_FILES['product_file'] ?? null;
    if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $fileMetadata = shopStoreUpload($upload);
            $hasReplacementFile = true;
            $_SESSION['shop_admin_product_upload'][$scopeKey] = [
                'metadata' => $fileMetadata, 'expires_at' => time() + 3600,
            ];
        } catch (DomainException $exception) {
            $errors['product_file'] = $exception->getMessage();
        } catch (Throwable $exception) {
            $errors['product_file'] = 'Soubor se nepodařilo bezpečně uložit. Zkontrolujte privátní úložiště a opakujte nahrání.';
        }
    }
    if ($values['is_active'] === '1' && (empty($fileMetadata['file_storage_name']) || empty($fileMetadata['file_sha256']))) {
        $errors['product_file'] = $errors['product_file'] ?? 'Pro zveřejnění nahrajte soubor digitálního produktu.';
    }
    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            if ($productId !== null) {
                $stmt = $pdo->prepare('SELECT * FROM cms_shop_products WHERE id = ? FOR UPDATE');
                $stmt->execute([$productId]);
                $lockedProduct = $stmt->fetch();
                if (!$lockedProduct) {
                    throw new DomainException('Produkt už neexistuje. Obnovte seznam produktů.');
                }
                // A stale editor without a replacement must preserve the current file snapshot.
                if (!$hasReplacementFile) {
                    $fileMetadata = $lockedProduct;
                }
            }
            $data = [
                (int)$selectedCategory, $values['title'], $values['slug'], $values['description'],
                $values['requirements'], $values['license_text'], $values['update_policy'],
                $priceCents, $values['tax_class'], $fileMetadata['file_storage_name'] ?? '',
                $fileMetadata['file_original_name'] ?? '', (int)($fileMetadata['file_size'] ?? 0),
                $fileMetadata['file_sha256'] ?? '', (int)$values['is_active'],
            ];
            if ($productId !== null) {
                $data[] = $productId;
                $pdo->prepare('UPDATE cms_shop_products SET category_id = ?, title = ?, slug = ?, description = ?,
                    requirements = ?, license_text = ?, update_policy = ?, price_cents = ?, tax_class = ?,
                    file_storage_name = ?, file_original_name = ?, file_size = ?, file_sha256 = ?, is_active = ?,
                    updated_at = NOW() WHERE id = ?')->execute($data);
            } else {
                $pdo->prepare('INSERT INTO cms_shop_products (category_id, title, slug, description, requirements,
                    license_text, update_policy, price_cents, tax_class, file_storage_name, file_original_name,
                    file_size, file_sha256, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())')->execute($data);
            }
            $pdo->commit();
            unset($_SESSION['shop_admin_product_upload'][$scopeKey], $_SESSION['shop_admin_product_flash'][$scopeKey]);
            $_SESSION['shop_admin_catalog_flash'] = 'Produkt byl uložen. Soubory již přijatých objednávek zůstávají beze změny.';
            header('Location: ' . internalRedirectTarget(BASE_URL . '/admin/shop.php'), true, 303);
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof PDOException && (string)$exception->getCode() === '23000') {
                $errors['slug'] = 'Slug není jedinečný. Zvolte jiný a uložení opakujte.';
            } else {
                $message = 'Produkt se nepodařilo uložit. Údaje byly zachovány; zkuste uložení znovu.';
            }
        }
    }
    // Only ordinary editor data is restored; confirmations never belong to a draft.
    $_SESSION['shop_admin_product_flash'][$scopeKey] = ['values' => $values, 'errors' => $errors, 'message' => $message];
    header('Location: ' . internalRedirectTarget($formUrl), true, 303);
    exit;
}
$flash = $_SESSION['shop_admin_product_flash'][$scopeKey] ?? null;
unset($_SESSION['shop_admin_product_flash'][$scopeKey]);
if (is_array($flash)) {
    $values = array_replace($values, $flash['values'] ?? []);
    $errors = $flash['errors'] ?? [];
    $message = (string)($flash['message'] ?? '');
}
$categories = $pdo->query('SELECT id, name, is_active FROM cms_shop_categories ORDER BY sort_order, name, id')->fetchAll();
$fieldErrors = array_keys($errors);
$renderError = static function (string $field) use ($errors, $fieldErrors): void {
    adminRenderFieldError($field, $fieldErrors, [], $errors[$field] ?? '');
};
adminHeader($productId !== null ? 'Upravit digitální produkt' : 'Nový digitální produkt');
?>
<p><a href="shop.php">Zpět na produkty</a> · <a href="shop_categories.php">Spravovat kategorie</a></p>
<?php if ($errors !== [] || $message !== ''): ?>
  <div class="error" role="alert" id="shop-product-errors">
    <p><?= h($message !== '' ? $message : 'Produkt nebyl uložen. Opravte označená pole.') ?></p>
    <?php if ($errors !== []): ?><ul><?php foreach ($errors as $field => $error): ?><li><a href="#<?= h($field) ?>"><?= h($error) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
<?php endif; ?>
<form method="post" action="<?= h($formUrl) ?>" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset class="admin-fieldset-card">
    <legend>Produkt a zveřejnění</legend>
    <label for="title">Název produktu (povinné)</label>
    <input type="text" id="title" name="title" required maxlength="190" value="<?= h($values['title']) ?>"<?= adminFieldAttributes('title', $fieldErrors) ?>>
    <?php $renderError('title'); ?>
    <label for="slug">Slug</label>
    <input type="text" id="slug" name="slug" maxlength="150" value="<?= h($values['slug']) ?>"<?= adminFieldAttributes('slug', $fieldErrors, [], ['slug-help']) ?>>
    <small class="field-help" id="slug-help">Jedinečná část adresy. Prázdný slug se odvodí z názvu.</small>
    <?php $renderError('slug'); ?>
    <label for="category_id">Kategorie</label>
    <select id="category_id" name="category_id"<?= adminFieldAttributes('category_id', $fieldErrors) ?>>
      <option value="0">Bez kategorie</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int)$category['id'] ?>"<?= $values['category_id'] === (string)$category['id'] ? ' selected' : '' ?>><?= h((string)$category['name']) ?><?= (int)$category['is_active'] === 1 ? '' : ' (neaktivní)' ?></option>
      <?php endforeach; ?>
    </select>
    <?php $renderError('category_id'); ?>
    <label for="price">Konečná cena v CZK včetně daně (povinné)</label>
    <input type="text" id="price" name="price" inputmode="decimal" required value="<?= h($values['price']) ?>"<?= adminFieldAttributes('price', $fieldErrors, [], ['price-help']) ?>>
    <small class="field-help" id="price-help">Například 249,00. Částky se ukládají v haléřích bez výpočtů s desetinným float.</small>
    <?php $renderError('price'); ?>
    <label for="tax_class">Daňová třída (povinné)</label>
    <select id="tax_class" name="tax_class" required<?= adminFieldAttributes('tax_class', $fieldErrors) ?>>
      <option value="general"<?= $values['tax_class'] === 'general' ? ' selected' : '' ?>>Obecná</option>
      <option value="publication"<?= $values['tax_class'] === 'publication' ? ' selected' : '' ?>>Publikace</option>
    </select>
    <?php $renderError('tax_class'); ?>
    <label for="is_active" class="admin-check-row"><input type="checkbox" id="is_active" name="is_active" value="1"<?= $values['is_active'] === '1' ? ' checked' : '' ?> aria-describedby="active-help"> Aktivní produkt ve veřejném katalogu</label>
    <small class="field-help" id="active-help">Zveřejnění vyžaduje popis, požadavky, licenci, pravidla aktualizací a soubor. Prodej navíc podléhá připravenosti obchodu a aktivnímu pravidlu země.</small>
  </fieldset>
  <fieldset class="admin-fieldset-card">
    <legend>Informace pro kupujícího</legend>
    <p id="product-text-help">Pro aktivní produkt jsou všechny tyto texty povinné. Pište prostý text, nikoli HTML.</p>
    <?php foreach (['description' => 'Popis produktu', 'requirements' => 'Technické požadavky a kompatibilita', 'license_text' => 'Licence a povolené použití', 'update_policy' => 'Aktualizace a podpora'] as $field => $label): ?>
      <label for="<?= h($field) ?>"><?= h($label) ?></label>
      <textarea id="<?= h($field) ?>" name="<?= h($field) ?>" rows="6" maxlength="50000"<?= adminFieldAttributes($field, $fieldErrors, [], ['product-text-help']) ?>><?= h($values[$field]) ?></textarea>
      <?php $renderError($field); ?>
    <?php endforeach; ?>
  </fieldset>
  <fieldset class="admin-fieldset-card">
    <legend>Digitální soubor</legend>
    <p id="product-file-help">Nové nahrání vytvoří neměnný soubor v privátním úložišti. Existující objednávky nadále používají svůj původní snapshot. Bez nového nahrání se soubor produktu nemění.</p>
    <?php if (!empty($fileMetadata['file_original_name'])): ?>
      <p><?= $pendingUpload !== null ? 'Nahraný soubor čekající na uložení produktu' : 'Aktuální soubor' ?>: <strong><?= h((string)$fileMetadata['file_original_name']) ?></strong>, <?= (int)$fileMetadata['file_size'] ?> bajtů.</p>
      <p class="admin-code-break">SHA-256: <code><?= h((string)$fileMetadata['file_sha256']) ?></code></p>
    <?php endif; ?>
    <label for="product_file">Nahrát nový soubor produktu</label>
    <input type="file" id="product_file" name="product_file"<?= adminFieldAttributes('product_file', $fieldErrors, [], ['product-file-help']) ?>>
    <?php $renderError('product_file'); ?>
  </fieldset>
  <p class="button-row"><button type="submit" class="btn" data-submit-once="Ukládám…">Uložit produkt</button><a href="shop.php">Zrušit</a></p>
</form>
<?php adminFooter(); ?>
