<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo     = db_connect();
$success = false;
$error   = '';

$editId = inputInt('get', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name      = trim($_POST['name'] ?? '');
    $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
    $updateId  = inputInt('post', 'update_id');

    if ($name === '') {
        $error = 'Název kategorie je povinný.';
    } elseif ($updateId !== null) {
        $pdo->prepare("UPDATE cms_res_categories SET name = ?, sort_order = ? WHERE id = ?")->execute([$name, $sortOrder, $updateId]);
        logAction('res_cat_edit', "id={$updateId}, name=" . mb_substr($name, 0, 80));
        $success = true;
        $editId  = null;
    } else {
        $pdo->prepare("INSERT INTO cms_res_categories (name, sort_order) VALUES (?, ?)")->execute([$name, $sortOrder]);
        logAction('res_cat_add', "name=" . mb_substr($name, 0, 80));
        $success = true;
    }
}

$categories = $pdo->query("SELECT id, name, sort_order FROM cms_res_categories ORDER BY sort_order, name")->fetchAll();

adminHeader('Rezervace - kategorie');
?>
<?php if ($success): ?><p class="success" role="status">Kategorie uložena.</p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

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
      <button type="submit">Přidat kategorii</button>
    </div>
  </fieldset>
</form>

<h2>Existující kategorie</h2>
<?php if (empty($categories)): ?>
  <p>Žádné kategorie.</p>
<?php else: ?>
  <table>
    <caption>Kategorie zdrojů</caption>
    <thead><tr><th scope="col">Název</th><th scope="col">Pořadí</th><th scope="col">Akce</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $cat): ?>
      <tr>
        <?php if ($editId === (int)$cat['id']): ?>
          <td colspan="2">
            <form method="post" style="display:flex;gap:.4rem;align-items:center" novalidate>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id"  value="<?= (int)$cat['id'] ?>">
              <label for="name-<?= (int)$cat['id'] ?>" class="sr-only">Název kategorie</label>
              <input type="text" id="name-<?= (int)$cat['id'] ?>" name="name" required aria-required="true" maxlength="255"
                     value="<?= h($cat['name']) ?>" style="width:auto">
              <label for="sort-<?= (int)$cat['id'] ?>" class="sr-only">Pořadí</label>
              <input type="number" id="sort-<?= (int)$cat['id'] ?>" name="sort_order" min="0" style="width:5rem"
                     value="<?= (int)$cat['sort_order'] ?>">
              <button type="submit" class="btn">Uložit</button>
              <a href="res_categories.php">Zrušit</a>
            </form>
          </td>
        <?php else: ?>
          <td><?= h($cat['name']) ?></td>
          <td><?= (int)$cat['sort_order'] ?></td>
        <?php endif; ?>
        <td class="actions">
          <?php if ($editId !== (int)$cat['id']): ?>
            <a href="res_categories.php?edit=<?= (int)$cat['id'] ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="res_cat_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat kategorii? Zdroje bez kategorie zůstanou zachovány.')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<p><a href="res_resources.php"><span aria-hidden="true">&larr;</span> Zpět na zdroje</a></p>
<?php adminFooter(); ?>
