<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$id  = inputInt('get', 'id');
$d   = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_downloads WHERE id = ?");
    $stmt->execute([$id]);
    $d = $stmt->fetch();
    if (!$d) { header('Location: downloads.php'); exit; }
}

// Existující kategorie jako návrhy
$cats = $pdo->query(
    "SELECT DISTINCT category FROM cms_downloads WHERE category != '' ORDER BY category"
)->fetchAll(\PDO::FETCH_COLUMN);

adminHeader($id ? 'Upravit soubor' : 'Nový soubor ke stažení');
?>

<form method="post" action="download_save.php" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <label for="title">Název <span aria-hidden="true">*</span></label>
  <input type="text" id="title" name="title" required maxlength="255"
         value="<?= h($d['title'] ?? '') ?>">

  <label for="category">Kategorie <small>(nepovinná – např. Stanovy, Zápisy, Formuláře)</small></label>
  <input type="text" id="category" name="category" maxlength="100"
         list="cats-list" value="<?= h($d['category'] ?? '') ?>">
  <datalist id="cats-list">
    <?php foreach ($cats as $c): ?>
      <option value="<?= h($c) ?>">
    <?php endforeach; ?>
  </datalist>

  <label for="description">Popis <small>(nepovinný)</small></label>
  <textarea id="description" name="description" rows="3"><?= h($d['description'] ?? '') ?></textarea>

  <label for="file">
    Soubor
    <?php if (!empty($d['original_name'])): ?>
      <small>(aktuální: <strong><?= h($d['original_name']) ?></strong>
        <?php if ($d['file_size'] > 0): ?>(<?= h(formatFileSize($d['file_size'])) ?>)<?php endif; ?>
        – nahrajte nový pro nahrazení)</small>
    <?php else: ?>
      <span aria-hidden="true">*</span>
    <?php endif; ?>
  </label>
  <input type="file" id="file" name="file"
         accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.zip,.txt">
  <small style="color:#666">Povolené formáty: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ODT, ODS, ODP, ZIP, TXT</small>

  <label for="sort_order">Pořadí</label>
  <input type="number" id="sort_order" name="sort_order" min="0" style="width:8rem"
         value="<?= (int)($d['sort_order'] ?? 0) ?>">

  <label style="font-weight:normal;margin-top:1rem">
    <input type="checkbox" name="is_published" value="1"
           <?= ($d['is_published'] ?? 1) ? 'checked' : '' ?>>
    Zobrazit na webu
  </label>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id ? 'Uložit' : 'Přidat soubor' ?></button>
    <a href="downloads.php" style="margin-left:1rem">Zrušit</a>
  </div>
</form>

<?php adminFooter(); ?>
