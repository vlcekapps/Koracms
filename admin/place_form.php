<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$id  = inputInt('get', 'id');
$pl  = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_places WHERE id = ?");
    $stmt->execute([$id]);
    $pl = $stmt->fetch();
    if (!$pl) { header('Location: places.php'); exit; }
}

// Existující kategorie jako návrhy
$cats = $pdo->query(
    "SELECT DISTINCT category FROM cms_places WHERE category != '' ORDER BY category"
)->fetchAll(\PDO::FETCH_COLUMN);

adminHeader($id ? 'Upravit místo' : 'Nové místo');
?>

<form method="post" action="place_save.php" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <label for="name">Název <span aria-hidden="true">*</span></label>
  <input type="text" id="name" name="name" required maxlength="255"
         value="<?= h($pl['name'] ?? '') ?>">

  <label for="category">Kategorie <small>(nepovinná)</small></label>
  <input type="text" id="category" name="category" maxlength="100"
         list="cats-list" value="<?= h($pl['category'] ?? '') ?>">
  <datalist id="cats-list">
    <?php foreach ($cats as $c): ?>
      <option value="<?= h($c) ?>">
    <?php endforeach; ?>
  </datalist>

  <label for="url">URL odkaz <small>(nepovinný)</small></label>
  <input type="url" id="url" name="url" maxlength="500"
         value="<?= h($pl['url'] ?? '') ?>">

  <label for="description">Krátký popis <small>(nepovinný)</small></label>
  <textarea id="description" name="description" rows="3"><?= h($pl['description'] ?? '') ?></textarea>

  <label for="sort_order">Pořadí</label>
  <input type="number" id="sort_order" name="sort_order" min="0" style="width:8rem"
         value="<?= (int)($pl['sort_order'] ?? 0) ?>">

  <label style="font-weight:normal;margin-top:1rem">
    <input type="checkbox" name="is_published" value="1"
           <?= ($pl['is_published'] ?? 1) ? 'checked' : '' ?>>
    Zobrazit na webu
  </label>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id ? 'Uložit' : 'Přidat místo' ?></button>
    <a href="places.php" style="margin-left:1rem">Zrušit</a>
  </div>
</form>

<?php adminFooter(); ?>
