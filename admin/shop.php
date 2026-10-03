<?php
require_once __DIR__ . '/../db.php';
$requestMethod = requireHttpMethods(['GET', 'HEAD']);
requireSuperAdmin();
requireModuleEnabled('shop');
require_once __DIR__ . '/../lib/shop.php';
require_once __DIR__ . '/layout.php';
shopSafeHeaders();
if ($requestMethod === 'HEAD') {
    exit;
}

$pdo = db_connect();
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$visibility = is_string($_GET['visibility'] ?? null) ? $_GET['visibility'] : '';
if (!in_array($visibility, ['', 'active', 'inactive'], true)) {
    $visibility = '';
}
$categoryId = inputInt('get', 'category_id');
$categories = $pdo->query('SELECT id, name, is_active FROM cms_shop_categories ORDER BY sort_order, name, id')->fetchAll();
$conditions = [];
$params = [];
if ($q !== '') {
    $conditions[] = '(p.title LIKE ? OR p.slug LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($visibility !== '') {
    $conditions[] = 'p.is_active = ?';
    $params[] = $visibility === 'active' ? 1 : 0;
}
if ($categoryId !== null) {
    $conditions[] = 'p.category_id = ?';
    $params[] = $categoryId;
}
$where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
$paging = paginate($pdo, 'SELECT COUNT(*) FROM cms_shop_products p' . $where, $params, 25);
$stmt = $pdo->prepare('SELECT p.id, p.title, p.slug, p.price_cents, p.tax_class, p.is_active,
    p.file_original_name, p.updated_at, c.name AS category_name, c.is_active AS category_active
    FROM cms_shop_products p LEFT JOIN cms_shop_categories c ON c.id = p.category_id' . $where . '
    ORDER BY p.updated_at DESC, p.id DESC LIMIT ' . $paging['perPage'] . ' OFFSET ' . $paging['offset']);
$stmt->execute($params);
$products = $stmt->fetchAll();
$flash = $_SESSION['shop_admin_catalog_flash'] ?? null;
unset($_SESSION['shop_admin_catalog_flash']);
$pagerUrl = BASE_URL . '/admin/shop.php?' . http_build_query([
    'q' => $q, 'visibility' => $visibility, 'category_id' => $categoryId,
]) . '&';

adminHeader('Digitální obchod: produkty');
?>
<p class="button-row">
  <a class="btn" href="shop_product.php">Nový produkt</a>
  <a href="shop_categories.php">Kategorie</a>
  <a href="shop_settings.php">Nastavení obchodu</a>
  <a href="shop_orders.php">Objednávky</a>
</p>
<?php if (is_string($flash) && $flash !== ''): ?>
  <p class="success" role="status"><?= h($flash) ?></p>
<?php endif; ?>
<?php if (!shopReady()): ?>
  <p class="admin-warning-box">Prodej není připravený. Zkontrolujte identitu, právní texty, platební metody a pravidla zemí v nastavení obchodu.</p>
<?php endif; ?>
<form method="get" action="shop.php" class="admin-filter-form">
  <fieldset class="admin-fieldset-card">
    <legend>Filtr produktů</legend>
    <div class="admin-form-grid admin-form-grid--end">
      <div class="admin-form-grid__cell">
        <label for="shop-q">Název nebo slug</label>
        <input type="search" id="shop-q" name="q" value="<?= h($q) ?>">
      </div>
      <div class="admin-form-grid__cell">
        <label for="shop-visibility">Zveřejnění</label>
        <select id="shop-visibility" name="visibility">
          <option value="">Všechny produkty</option>
          <option value="active"<?= $visibility === 'active' ? ' selected' : '' ?>>Aktivní</option>
          <option value="inactive"<?= $visibility === 'inactive' ? ' selected' : '' ?>>Neaktivní</option>
        </select>
      </div>
      <div class="admin-form-grid__cell">
        <label for="shop-category">Kategorie</label>
        <select id="shop-category" name="category_id">
          <option value="">Všechny kategorie</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= (int)$category['id'] ?>"<?= $categoryId === (int)$category['id'] ? ' selected' : '' ?>><?= h((string)$category['name']) ?><?= (int)$category['is_active'] === 1 ? '' : ' (neaktivní)' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn" type="submit">Použít filtr</button>
      <a href="shop.php">Zrušit filtr</a>
    </div>
  </fieldset>
</form>
<p role="status">Nalezeno produktů: <?= $paging['total'] ?>. Strana <?= $paging['page'] ?> z <?= $paging['totalPages'] ?>.</p>
<div class="table-responsive" tabindex="0" role="region" aria-labelledby="shop-products-caption">
  <table>
    <caption id="shop-products-caption">Produkty digitálního obchodu podle zvoleného filtru</caption>
    <thead><tr><th scope="col">Produkt</th><th scope="col">Kategorie</th><th scope="col">Cena včetně daně</th><th scope="col">Daňová třída</th><th scope="col">Stav</th><th scope="col">Soubor</th><th scope="col">Akce</th></tr></thead>
    <tbody>
      <?php foreach ($products as $product): ?>
        <tr>
          <th scope="row"><?= h((string)$product['title']) ?><small class="table-meta"><?= h((string)$product['slug']) ?></small></th>
          <td><?= h((string)($product['category_name'] ?? 'Bez kategorie')) ?><?= $product['category_name'] !== null && (int)$product['category_active'] !== 1 ? ' (neaktivní)' : '' ?></td>
          <td><?= h(shopMoney((int)$product['price_cents'])) ?></td>
          <td><?= $product['tax_class'] === 'publication' ? 'Publikace' : 'Obecná' ?></td>
          <td><?= (int)$product['is_active'] === 1 ? 'Aktivní' : 'Neaktivní' ?></td>
          <td class="table-cell--detail"><?= h((string)($product['file_original_name'] ?: 'Nenahrán')) ?></td>
          <td><a href="shop_product.php?id=<?= (int)$product['id'] ?>">Upravit<span class="sr-only"> <?= h((string)$product['title']) ?></span></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($products === []): ?><tr><td colspan="7">Zvolenému filtru neodpovídá žádný produkt.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?= renderPager($paging['page'], $paging['totalPages'], $pagerUrl, 'Stránkování produktů') ?>
<?php if (currentUserHasCapability('shop_manage')): ?>
<section class="admin-section-card" aria-labelledby="catalog-transfer">
  <h2 id="catalog-transfer">Přenos katalogu (JSON)</h2>
  <p>Použijte společný export/import CMS. Z obchodu se přenáší pouze konfigurace, kategorie a textová/cenová metadata produktů. Soubory, objednávky, zákazníci, doklady, bankovní pohyby a tajné tokeny se nepřenášejí. Import vypne připravenost prodeje; nové produkty, platební metody a pravidla zemí zůstanou neaktivní do kontroly.</p>
  <p class="button-row"><a href="export.php">Kontrola JSON exportu CMS</a><a href="import.php">Import JSON katalogu přes CMS</a></p>
  <p class="field-help">Společný export obsahuje i další obsah CMS. Zkontrolujte jeho citlivost a uchovávejte jej pouze v oprávněném úložišti. Při přenosu obchodu zvlášť bezpečně zálohujte privátní soubory a klíč; soubory nelze obnovit z katalogových metadat.</p>
</section>
<?php endif; ?>
<?php adminFooter(); ?>
