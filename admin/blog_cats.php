<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$success = false;
$error   = '';

$editId = inputInt('get', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name    = trim($_POST['name']   ?? '');
    $updateId = inputInt('post', 'update_id');

    if ($name === '') {
        $error = 'Název kategorie je povinný.';
    } elseif ($updateId !== null) {
        $pdo->prepare("UPDATE cms_categories SET name = ? WHERE id = ?")->execute([$name, $updateId]);
        $success = true;
        $editId  = null;
    } else {
        $pdo->prepare("INSERT INTO cms_categories (name) VALUES (?)")->execute([$name]);
        $success = true;
    }
}

$categories = $pdo->query("SELECT id, name FROM cms_categories ORDER BY name")->fetchAll();

adminHeader('Kategorie blogu');
?>
<?php if ($success): ?><p class="success" role="status">Kategorie přidána.</p><?php endif; ?>
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

<h2>Přehled kategorií blogu</h2>
<?php if (empty($categories)): ?>
  <p>Zatím tu nejsou žádné kategorie.</p>
<?php else: ?>
  <?= bulkActions('blog_categories', BASE_URL . '/admin/blog_cats.php', 'Hromadné akce s kategoriemi', 'kategorie', false) ?>
  <table>
    <caption>Přehled kategorií blogu</caption>
    <thead><tr><th scope="col"><input type="checkbox" id="check-all" aria-label="Vybrat vše"></th><th scope="col">Název</th><th scope="col">Akce</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $cat): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int)$cat['id'] ?>" form="bulk-form" aria-label="Vybrat <?= h((string)$cat['name']) ?>"></td>
        <td>
          <?php if ($editId === (int)$cat['id']): ?>
            <form method="post" style="display:flex;gap:.4rem;align-items:center">
              <input type="hidden" name="csrf_token"  value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id"   value="<?= (int)$cat['id'] ?>">
              <input type="text"   name="name" required aria-required="true" maxlength="255"
                     value="<?= h($cat['name']) ?>" style="width:auto">
              <button type="submit" class="btn">Uložit</button>
              <a href="blog_cats.php">Zrušit</a>
            </form>
          <?php else: ?>
            <?= h($cat['name']) ?>
          <?php endif; ?>
        </td>
        <td class="actions">
          <?php if ($editId !== (int)$cat['id']): ?>
            <a href="blog_cats.php?edit=<?= (int)$cat['id'] ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="blog_cat_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Smazat kategorii?">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= bulkCheckboxJs() ?>
<?php endif; ?>
<p><a href="blog.php"><span aria-hidden="true">←</span> Zpět na blog</a></p>
<?php adminFooter(); ?>
