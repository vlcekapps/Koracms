<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo     = db_connect();
$success = false;
$error   = '';

$editId = inputInt('get', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name     = trim($_POST['name'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $updateId = inputInt('post', 'update_id');

    if ($name === '') {
        $error = 'Název místa je povinný.';
    } elseif ($updateId !== null) {
        $pdo->prepare("UPDATE cms_res_locations SET name = ?, address = ? WHERE id = ?")
            ->execute([$name, $address, $updateId]);
        logAction('res_location_edit', "id={$updateId}, name=" . mb_substr($name, 0, 80));
        $success = true;
        $editId  = null;
    } else {
        $pdo->prepare("INSERT INTO cms_res_locations (name, address) VALUES (?, ?)")
            ->execute([$name, $address]);
        logAction('res_location_add', "name=" . mb_substr($name, 0, 80));
        $success = true;
    }
}

$locations = $pdo->query("SELECT id, name, address FROM cms_res_locations ORDER BY name")->fetchAll();

adminHeader('Rezervace – místa');
?>
<?php if ($success): ?><p class="success" role="status">Místo uloženo.</p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset>
    <legend>Nové místo</legend>
    <div style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label for="name">Název <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" required aria-required="true" maxlength="255"
               placeholder="např. Dům u Růženky">
      </div>
      <div>
        <label for="address">Adresa</label>
        <input type="text" id="address" name="address" maxlength="500" style="min-width:20rem"
               placeholder="Ulice, číslo domu, PSČ, město">
      </div>
      <button type="submit">Přidat místo</button>
    </div>
  </fieldset>
</form>

<h2>Existující místa</h2>
<?php if (empty($locations)): ?>
  <p>Žádná místa.</p>
<?php else: ?>
  <table>
    <caption>Místa konání</caption>
    <thead><tr><th scope="col">Název</th><th scope="col">Adresa</th><th scope="col">Akce</th></tr></thead>
    <tbody>
    <?php foreach ($locations as $loc): ?>
      <tr>
        <?php if ($editId === (int)$loc['id']): ?>
          <td colspan="2">
            <form method="post" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap" novalidate>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id"  value="<?= (int)$loc['id'] ?>">
              <label for="name-<?= (int)$loc['id'] ?>" class="sr-only">Název místa</label>
              <input type="text" id="name-<?= (int)$loc['id'] ?>" name="name" required aria-required="true" maxlength="255"
                     value="<?= h($loc['name']) ?>" style="width:auto">
              <label for="addr-<?= (int)$loc['id'] ?>" class="sr-only">Adresa místa</label>
              <input type="text" id="addr-<?= (int)$loc['id'] ?>" name="address" maxlength="500"
                     value="<?= h($loc['address']) ?>" style="width:auto;min-width:15rem">
              <button type="submit" class="btn">Uložit</button>
              <a href="res_locations.php">Zrušit</a>
            </form>
          </td>
        <?php else: ?>
          <td><?= h($loc['name']) ?></td>
          <td><?= h($loc['address'] ?: '–') ?></td>
        <?php endif; ?>
        <td class="actions">
          <?php if ($editId !== (int)$loc['id']): ?>
            <a href="res_locations.php?edit=<?= (int)$loc['id'] ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="res_location_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$loc['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat místo? Vazby na zdroje budou odebrány.')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<p><a href="res_resources.php"><span aria-hidden="true">&larr;</span> Zpět na zdroje</a></p>
<?php adminFooter(); ?>
