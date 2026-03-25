<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo     = db_connect();
$success = false;
$error   = '';
$editId  = inputInt('get', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name     = trim($_POST['name']      ?? '');
    $updateId = inputInt('post', 'update_id');

    if ($name === '') {
        $error = 'Název tagu je povinný.';
    } else {
        $slug = slugify($name);
        if ($updateId !== null) {
            $pdo->prepare("UPDATE cms_tags SET name = ?, slug = ? WHERE id = ?")
                ->execute([$name, $slug, $updateId]);
            logAction('tag_edit', "id={$updateId} name={$name}");
            $editId = null;
        } else {
            try {
                $pdo->prepare("INSERT INTO cms_tags (name, slug) VALUES (?, ?)")
                    ->execute([$name, $slug]);
                logAction('tag_add', "name={$name}");
            } catch (\PDOException $e) {
                $error = 'Tag s tímto názvem nebo slugem již existuje.';
            }
        }
        if ($error === '') $success = true;
    }
}

$tags = $pdo->query("SELECT id, name, slug FROM cms_tags ORDER BY name")->fetchAll();

adminHeader('Štítky blogu');
?>

<?php if ($success): ?><p class="success" role="status">Tag uložen.</p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset>
    <legend>Nový tag</legend>
    <label for="name">Název <span aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" required aria-required="true" maxlength="100">
    <button type="submit" style="margin-top:.5rem">Přidat tag</button>
  </fieldset>
</form>

<h2>Přehled štítků blogu</h2>
<?php if (empty($tags)): ?>
  <p>Zatím tu nejsou žádné tagy.</p>
<?php else: ?>
  <table>
    <caption>Přehled štítků blogu</caption>
    <thead><tr><th scope="col">Název</th><th scope="col">Slug</th><th scope="col">Akce</th></tr></thead>
    <tbody>
    <?php foreach ($tags as $t): ?>
      <tr>
        <td>
          <?php if ($editId === (int)$t['id']): ?>
            <form method="post" style="display:flex;gap:.4rem;align-items:center">
              <input type="hidden" name="csrf_token"  value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id"   value="<?= (int)$t['id'] ?>">
              <input type="text"   name="name" required aria-required="true" maxlength="100"
                     value="<?= h($t['name']) ?>" style="width:auto">
              <button type="submit" class="btn">Uložit</button>
              <a href="blog_tags.php">Zrušit</a>
            </form>
          <?php else: ?>
            <?= h($t['name']) ?>
          <?php endif; ?>
        </td>
        <td><code><?= h($t['slug']) ?></code></td>
        <td class="actions">
          <?php if ($editId !== (int)$t['id']): ?>
            <a href="blog_tags.php?edit=<?= (int)$t['id'] ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="blog_tag_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id"         value="<?= (int)$t['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat tag?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<p><a href="blog.php"><span aria-hidden="true">←</span> Zpět na blog</a></p>
<?php adminFooter(); ?>
