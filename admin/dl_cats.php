<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo     = db_connect();
$success = false;
$error   = '';

$editId = inputInt('get', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name     = trim($_POST['name']      ?? '');
    $updateId = inputInt('post', 'update_id');

    if ($name === '') {
        $error = 'Název kategorie je povinný.';
    } elseif ($updateId !== null) {
        $pdo->prepare("UPDATE cms_dl_categories SET name = ? WHERE id = ?")->execute([$name, $updateId]);
        $success = true;
        $editId  = null;
    } else {
        $pdo->prepare("INSERT INTO cms_dl_categories (name) VALUES (?)")->execute([$name]);
        $success = true;
    }
}

$categories = $pdo->query("SELECT id, name FROM cms_dl_categories ORDER BY name")->fetchAll();

adminHeader('Ke stažení – kategorie');
?>
<?php if ($success): ?><p class="success" role="status">Kategorie uložena.</p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset>
    <legend>Nová kategorie</legend>
    <label for="name">Název <span aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" required aria-required="true" maxlength="255">
    <button type="submit" style="margin-top:.5rem">Přidat kategorii</button>
  </fieldset>
</form>

<h2>Existující kategorie</h2>
<?php if (empty($categories)): ?>
  <p>Žádné kategorie.</p>
<?php else: ?>
  <table>
    <caption>Kategorie ke stažení</caption>
    <thead><tr><th scope="col">Název</th><th scope="col">Akce</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $cat): ?>
      <tr>
        <td>
          <?php if ($editId === (int)$cat['id']): ?>
            <form method="post" style="display:flex;gap:.4rem;align-items:center" novalidate>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id"  value="<?= (int)$cat['id'] ?>">
              <label for="name-<?= (int)$cat['id'] ?>" class="sr-only">Název kategorie</label>
              <input type="text" id="name-<?= (int)$cat['id'] ?>" name="name" required aria-required="true" maxlength="255"
                     value="<?= h($cat['name']) ?>" style="width:auto">
              <button type="submit" class="btn">Uložit</button>
              <a href="dl_cats.php">Zrušit</a>
            </form>
          <?php else: ?>
            <?= h($cat['name']) ?>
          <?php endif; ?>
        </td>
        <td class="actions">
          <?php if ($editId !== (int)$cat['id']): ?>
            <a href="dl_cats.php?edit=<?= (int)$cat['id'] ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="dl_cat_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat kategorii? Soubory bez kategorie se zobrazí v sekci „Ostatní".')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<p><a href="downloads.php"><span aria-hidden="true">←</span> Zpět na soubory</a></p>
<?php adminFooter(); ?>
