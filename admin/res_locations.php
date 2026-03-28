<?php
require_once __DIR__ . '/layout.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu míst rezervací nemáte potřebné oprávnění.');

$pdo = db_connect();
$success = false;
$error = '';
$editId = inputInt('get', 'edit');
$q = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $updateId = inputInt('post', 'update_id');

    if ($name === '') {
        $error = 'Název místa je povinný.';
    } elseif ($updateId !== null) {
        $pdo->prepare("UPDATE cms_res_locations SET name = ?, address = ? WHERE id = ?")->execute([$name, $address, $updateId]);
        logAction('res_location_edit', "id={$updateId}, name=" . mb_substr($name, 0, 80));
        $success = true;
        $editId = null;
    } else {
        $pdo->prepare("INSERT INTO cms_res_locations (name, address) VALUES (?, ?)")->execute([$name, $address]);
        logAction('res_location_add', "name=" . mb_substr($name, 0, 80));
        $success = true;
    }
}

$stmt = $pdo->prepare(
    "SELECT l.id, l.name, l.address, COUNT(rl.resource_id) AS resource_count
     FROM cms_res_locations l
     LEFT JOIN cms_res_resource_locations rl ON rl.location_id = l.id
     " . ($q !== '' ? "WHERE l.name LIKE ? OR l.address LIKE ?" : '') . "
     GROUP BY l.id, l.name, l.address
     ORDER BY l.name"
);
$stmt->execute($q !== '' ? ['%' . $q . '%', '%' . $q . '%'] : []);
$locations = $stmt->fetchAll();

adminHeader('Lokality rezervací');
?>
<?php if ($success): ?><p class="success" role="status">Místo uloženo.</p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q">Hledat</label>
    <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Název nebo adresa">
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== ''): ?>
    <a href="res_locations.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

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
      <button type="submit" class="btn">Přidat místo</button>
    </div>
  </fieldset>
</form>

<h2>Existující místa</h2>
<?php if ($locations === []): ?>
  <p><?= $q !== '' ? 'Pro zvolený filtr tu teď nejsou žádné lokality rezervací.' : 'Zatím tu nejsou žádné lokality rezervací.' ?></p>
<?php else: ?>
  <table>
    <caption>Lokality rezervací</caption>
    <thead><tr><th scope="col">Název</th><th scope="col">Adresa</th><th scope="col">Zdroje</th><th scope="col">Akce</th></tr></thead>
    <tbody>
    <?php foreach ($locations as $location): ?>
      <tr>
        <?php if ($editId === (int)$location['id']): ?>
          <td colspan="3">
            <form method="post" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap" novalidate>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= (int)$location['id'] ?>">
              <label for="name-<?= (int)$location['id'] ?>" class="sr-only">Název místa</label>
              <input type="text" id="name-<?= (int)$location['id'] ?>" name="name" required aria-required="true" maxlength="255"
                     value="<?= h((string)$location['name']) ?>" style="width:auto">
              <label for="addr-<?= (int)$location['id'] ?>" class="sr-only">Adresa místa</label>
              <input type="text" id="addr-<?= (int)$location['id'] ?>" name="address" maxlength="500"
                     value="<?= h((string)$location['address']) ?>" style="width:auto;min-width:15rem">
              <button type="submit" class="btn">Uložit</button>
              <a href="res_locations.php<?= $q !== '' ? '?q=' . rawurlencode($q) : '' ?>">Zrušit</a>
            </form>
          </td>
        <?php else: ?>
          <td><?= h((string)$location['name']) ?></td>
          <td><?= h((string)($location['address'] ?: '–')) ?></td>
          <td><?= (int)$location['resource_count'] ?></td>
        <?php endif; ?>
        <td class="actions">
          <?php if ($editId !== (int)$location['id']): ?>
            <a href="res_locations.php?edit=<?= (int)$location['id'] ?><?= $q !== '' ? '&amp;q=' . rawurlencode($q) : '' ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="res_location_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$location['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Smazat místo? Vazby na zdroje budou odebrány.">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<p><a href="res_resources.php"><span aria-hidden="true">&larr;</span> Zpět na zdroje rezervací</a></p>
<?php adminFooter(); ?>
