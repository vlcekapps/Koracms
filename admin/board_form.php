<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$id  = inputInt('get', 'id');
$d   = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_board WHERE id = ?");
    $stmt->execute([$id]);
    $d = $stmt->fetch();
    if (!$d) { header('Location: board.php'); exit; }
}

$categories = $pdo->query("SELECT id, name FROM cms_board_categories ORDER BY sort_order, name")->fetchAll();
$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';

adminHeader($id ? 'Upravit dokument' : 'Nový dokument úřední desky');
?>

<form method="post" action="board_save.php" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Dokument úřední desky</legend>

    <label for="title">Název <span aria-hidden="true">*</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           value="<?= h($d['title'] ?? '') ?>">

    <label for="category_id">Kategorie</label>
    <select id="category_id" name="category_id">
      <option value="">– bez kategorie –</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>"
          <?= ((int)($d['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
          <?= h($cat['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="description">Popis <small>(nepovinný)</small></label>
    <?php if ($useWysiwyg): ?>
      <div id="editor-description" style="min-height:150px"><?= $d['description'] ?? '' ?></div>
      <input type="hidden" id="description" name="description" value="<?= h($d['description'] ?? '') ?>">
    <?php else: ?>
      <textarea id="description" name="description" rows="4"><?= h($d['description'] ?? '') ?></textarea>
      <small style="color:#666">Podporuje HTML i Markdown syntaxi.</small>
    <?php endif; ?>
  </fieldset>

  <fieldset>
    <legend>Data vyvěšení a sejmutí</legend>

    <label for="posted_date">Datum vyvěšení <span aria-hidden="true">*</span></label>
    <input type="date" id="posted_date" name="posted_date" required aria-required="true"
           value="<?= h($d['posted_date'] ?? date('Y-m-d')) ?>">

    <label for="removal_date">Datum sejmutí <small>(prázdné = bez omezení)</small></label>
    <input type="date" id="removal_date" name="removal_date"
           value="<?= h($d['removal_date'] ?? '') ?>">
  </fieldset>

  <fieldset>
    <legend>Příloha</legend>

    <label for="file">
      Soubor
      <?php if (!empty($d['original_name'])): ?>
        <small>(aktuální: <strong><?= h($d['original_name']) ?></strong>
          <?php if ($d['file_size'] > 0): ?>(<?= h(formatFileSize($d['file_size'])) ?>)<?php endif; ?>
          – nahrajte nový pro nahrazení)</small>
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
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id ? 'Uložit' : 'Přidat dokument' ?></button>
    <a href="board.php" style="margin-left:1rem">Zrušit</a>
  </div>
</form>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
var quillDesc = new Quill('#editor-description', {theme:'snow', modules:{toolbar:[[{'header':[2,3,false]}],'bold','italic','underline',{'list':'ordered'},{'list':'bullet'},'link','clean']}});
document.querySelector('form').addEventListener('submit', function(){
  document.getElementById('description').value = quillDesc.root.innerHTML;
});
</script>
<?php endif; ?>

<?php adminFooter(); ?>
