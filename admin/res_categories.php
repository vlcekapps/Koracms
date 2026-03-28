<?php
require_once __DIR__ . '/layout.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu kategorií rezervací nemáte potřebné oprávnění.');

$pdo = db_connect();
$success = false;
$error = '';
$editId = inputInt('get', 'edit');
$q = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
    $updateId = inputInt('post', 'update_id');

    if ($name === '') {
        $error = 'Název kategorie je povinný.';
    } elseif ($updateId !== null) {
        $pdo->prepare("UPDATE cms_res_categories SET name = ?, sort_order = ? WHERE id = ?")->execute([$name, $sortOrder, $updateId]);
        logAction('res_cat_edit', "id={$updateId}, name=" . mb_substr($name, 0, 80));
        $success = true;
        $editId = null;
    } else {
        $pdo->prepare("INSERT INTO cms_res_categories (name, sort_order) VALUES (?, ?)")->execute([$name, $sortOrder]);
        logAction('res_cat_add', "name=" . mb_substr($name, 0, 80));
        $success = true;
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

adminHeader('Kategorie zdrojů rezervací');
?>
<?php if ($success): ?><p class="success" role="status">Kategorie uložena.</p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q">Hledat</label>
    <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Název kategorie">
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== ''): ?>
    <a href="res_categories.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset>
    <legend>Nová kategorie</legend>
    <div style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label for="name">Název <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" required aria-required="true" maxlength="255">
      </div>
      <div>
        <label for="sort_order">Pořadí</label>
        <input type="number" id="sort_order" name="sort_order" min="0" style="width:5rem" value="0">
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
      <tr>
        <?php if ($editId === (int)$category['id']): ?>
          <td colspan="3">
            <form method="post" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap" novalidate>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= (int)$category['id'] ?>">
              <label for="name-<?= (int)$category['id'] ?>" class="sr-only">Název kategorie</label>
              <input type="text" id="name-<?= (int)$category['id'] ?>" name="name" required aria-required="true" maxlength="255"
                     value="<?= h((string)$category['name']) ?>" style="width:auto">
              <label for="sort-<?= (int)$category['id'] ?>" class="sr-only">Pořadí</label>
              <input type="number" id="sort-<?= (int)$category['id'] ?>" name="sort_order" min="0" style="width:5rem"
                     value="<?= (int)$category['sort_order'] ?>">
              <button type="submit" class="btn">Uložit</button>
              <a href="res_categories.php<?= $q !== '' ? '?q=' . rawurlencode($q) : '' ?>">Zrušit</a>
            </form>
          </td>
        <?php else: ?>
          <td><?= h((string)$category['name']) ?></td>
          <td><?= (int)$category['sort_order'] ?></td>
          <td><?= (int)$category['resource_count'] ?></td>
        <?php endif; ?>
        <td class="actions">
          <?php if ($editId !== (int)$category['id']): ?>
            <a href="res_categories.php?edit=<?= (int)$category['id'] ?><?= $q !== '' ? '&amp;q=' . rawurlencode($q) : '' ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="res_cat_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Smazat kategorii? Zdroje bez kategorie zůstanou zachovány.">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<p><a href="res_resources.php"><span aria-hidden="true">&larr;</span> Zpět na zdroje rezervací</a></p>
<?php adminFooter(); ?>
