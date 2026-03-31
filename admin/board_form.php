<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu úřední desky nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$document = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_board WHERE id = ?");
    $stmt->execute([$id]);
    $document = $stmt->fetch() ?: null;
    if (!$document) {
        header('Location: board.php');
        exit;
    }
}

$document = $document ?: [
    'title' => '',
    'slug' => '',
    'board_type' => 'document',
    'excerpt' => '',
    'description' => '',
    'category_id' => null,
    'posted_date' => date('Y-m-d'),
    'removal_date' => '',
    'image_file' => '',
    'contact_name' => '',
    'contact_phone' => '',
    'contact_email' => '',
    'filename' => '',
    'original_name' => '',
    'file_size' => 0,
    'is_pinned' => 0,
    'is_published' => 1,
    'status' => 'published',
];

$categories = $pdo->query("SELECT id, name FROM cms_board_categories ORDER BY sort_order, name")->fetchAll();
$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$publicLabel = boardModulePublicLabel();
$currentBoardType = normalizeBoardType((string)($document['board_type'] ?? 'document'));
$boardTypeHelpMap = [];
foreach (boardTypeDefinitions() as $typeKey => $typeMeta) {
    $boardTypeHelpMap[$typeKey] = (string)($typeMeta['help'] ?? '');
}
$err = trim($_GET['err'] ?? '');
$formError = match ($err) {
    'required' => 'Vyplňte prosím všechna povinná pole (nadpis a datum vyvěšení).',
    'dates' => 'Datum sejmutí nesmí být dříve než datum vyvěšení.',
    'slug' => 'Slug položky je povinný a musí být unikátní.',
    'contact_email' => 'Kontaktní e-mail nemá platný formát.',
    'image' => 'Obrázek se nepodařilo nahrát. Použijte JPEG, PNG, GIF nebo WebP.',
    'file' => 'Přílohu se nepodařilo nahrát nebo má nepovolený formát.',
    default => '',
};

adminHeader($id ? 'Upravit položku sekce ' . $publicLabel : 'Nová položka sekce ' . $publicLabel);
?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error"><?= h($formError) ?></p>
<?php endif; ?>

<?php if ($id): ?>
  <p><a href="revisions.php?type=board&amp;id=<?= (int)$id ?>">Historie revizí</a></p>
<?php endif; ?>

<p style="margin-top:0;color:#555">
  Na veřejném webu se modul aktuálně zobrazuje jako <strong><?= h($publicLabel) ?></strong>.
</p>
<p style="margin-top:0;font-size:.9rem">
  Vyplňte potřebné údaje k položce a zvolte, jestli se má zveřejnit na webu. Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<p><a href="board.php"><span aria-hidden="true">←</span> Zpět na přehled sekce <?= h($publicLabel) ?></a></p>

<form method="post" action="board_save.php" enctype="multipart/form-data" novalidate<?= $formError !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Položka sekce <?= h($publicLabel) ?></legend>

    <label for="title">Nadpis <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           value="<?= h((string)$document['title']) ?>">

    <label for="slug">Slug veřejné stránky <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255" pattern="[a-z0-9\-]+"
           aria-describedby="board-slug-help"
           value="<?= h((string)$document['slug']) ?>">
    <small id="board-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Použijte malá písmena, číslice a pomlčky.</small>

    <label for="board_type">Typ oznámení</label>
    <select id="board_type" name="board_type" aria-describedby="board-type-help">
      <?php foreach (boardTypeDefinitions() as $typeKey => $typeMeta): ?>
        <option value="<?= h($typeKey) ?>"<?= $currentBoardType === $typeKey ? ' selected' : '' ?>>
          <?= h($typeMeta['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <small id="board-type-help" class="field-help"><?= h($boardTypeHelpMap[$currentBoardType] ?? '') ?></small>

    <label for="category_id">Kategorie</label>
    <select id="category_id" name="category_id">
      <option value="">- bez kategorie -</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int)$category['id'] ?>"<?= ((int)($document['category_id'] ?? 0) === (int)$category['id']) ? ' selected' : '' ?>>
          <?= h((string)$category['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="excerpt">Krátký text / perex</label>
    <textarea id="excerpt" name="excerpt" rows="3" aria-describedby="board-excerpt-help"><?= h((string)($document['excerpt'] ?? '')) ?></textarea>
    <small id="board-excerpt-help" class="field-help">Zobrazí se ve výpisu a na homepage. Hodí se pro parte, ztráty a nálezy i krátká oznámení.</small>

    <label for="description">Detailní popis</label>
    <?php if ($useWysiwyg): ?>
      <div id="editor-description" style="min-height:180px"><?= (string)($document['description'] ?? '') ?></div>
      <input type="hidden" id="description" name="description" aria-describedby="board-description-help" value="<?= h((string)($document['description'] ?? '')) ?>">
      <small id="board-description-help" class="field-help">Vyplňte, když chcete na detailu doplnit delší text.</small>
    <?php else: ?>
      <textarea id="description" name="description" rows="6" aria-describedby="board-description-help"><?= h((string)($document['description'] ?? '')) ?></textarea>
      <small id="board-description-help" class="field-help">Vyplňte, když chcete na detailu doplnit delší text. <?= adminHtmlSnippetSupportMarkup() ?></small>
      <?php renderAdminContentReferencePicker('description'); ?>
    <?php endif; ?>
  </fieldset>

  <fieldset>
    <legend>Termín a zvýraznění</legend>

    <label for="posted_date">Datum vyvěšení <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="date" id="posted_date" name="posted_date" required aria-required="true"
           aria-describedby="board-posted-date-help"
           value="<?= h((string)$document['posted_date']) ?>">
    <small id="board-posted-date-help" class="field-help">Položka se na veřejném webu zobrazí od tohoto dne. Budoucí datum vytvoří naplánovanou položku.</small>

    <label for="removal_date">Datum sejmutí</label>
    <input type="date" id="removal_date" name="removal_date" aria-describedby="board-removal-date-help"
           value="<?= h((string)($document['removal_date'] ?? '')) ?>">
    <small id="board-removal-date-help" class="field-help">Nechte prázdné, pokud má položka zůstat bez data stažení.</small>

    <label style="font-weight:normal;margin-top:1rem">
      <input type="checkbox" name="is_pinned" value="1" aria-describedby="board-pinned-help"<?= (int)($document['is_pinned'] ?? 0) === 1 ? ' checked' : '' ?>>
      Připnout mezi důležité položky
    </label>
    <small id="board-pinned-help" class="field-help">Připnuté položky se zobrazují výš ve výpisu a mohou lépe fungovat i v homepage bloku.</small>
  </fieldset>

  <fieldset>
    <legend>Obrázek a kontakt</legend>

    <label for="board_image">Obrázek oznámení</label>
    <?php if (!empty($document['image_file'])): ?>
      <div style="margin:.5rem 0">
        <img src="<?= h(boardImageUrl($document)) ?>" alt="Náhled obrázku"
             style="display:block;max-width:280px;width:100%;border-radius:12px;border:1px solid #d0d7de">
      </div>
    <?php endif; ?>
    <input type="file" id="board_image" name="board_image" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
           aria-describedby="board-image-help<?= !empty($document['image_file']) ? ' board-image-current' : '' ?>">
    <small id="board-image-help" class="field-help">Hodí se pro parte, ztracené zvíře, plakát nebo ilustrační fotku oznámení.</small>
    <?php if (!empty($document['image_file'])): ?>
      <small id="board-image-current" class="field-help">Aktuální obrázek je nahraný. Nahrajte nový, pokud ho chcete nahradit.</small>
    <?php endif; ?>
    <?php if (!empty($document['image_file'])): ?>
      <label for="board_image_delete" style="font-weight:normal;margin-top:.35rem">
        <input type="checkbox" id="board_image_delete" name="board_image_delete" value="1">
        Smazat aktuální obrázek
      </label>
    <?php endif; ?>

    <label for="contact_name">Kontaktní osoba</label>
    <input type="text" id="contact_name" name="contact_name" maxlength="255"
           value="<?= h((string)($document['contact_name'] ?? '')) ?>">

    <label for="contact_phone">Telefon</label>
    <input type="text" id="contact_phone" name="contact_phone" maxlength="100"
           value="<?= h((string)($document['contact_phone'] ?? '')) ?>">

    <label for="contact_email">E-mail</label>
    <input type="email" id="contact_email" name="contact_email" maxlength="255"
           value="<?= h((string)($document['contact_email'] ?? '')) ?>">
  </fieldset>

  <fieldset>
    <legend>Příloha a zveřejnění</legend>

    <label for="file">Soubor přílohy</label>
    <input type="file" id="file" name="file"
           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.zip,.txt"
           aria-describedby="board-file-help<?= !empty($document['original_name']) ? ' board-file-current' : '' ?>">
    <small id="board-file-help" class="field-help">Můžete nahrát běžný dokument nebo archiv, například PDF, DOCX, XLSX, PPTX, ODT nebo ZIP.</small>
    <?php if (!empty($document['original_name'])): ?>
      <small id="board-file-current" class="field-help">Aktuální příloha: <strong><?= h((string)$document['original_name']) ?></strong><?php if ((int)$document['file_size'] > 0): ?> (<?= h(formatFileSize((int)$document['file_size'])) ?>)<?php endif; ?>. Nahrajte nový soubor, pokud ji chcete nahradit.</small>
    <?php endif; ?>

    <label style="font-weight:normal;margin-top:1rem">
      <input type="checkbox" name="is_published" value="1" aria-describedby="board-published-help"
             <?= (int)($document['is_published'] ?? 1) === 1 ? 'checked' : '' ?>>
      Zveřejnit na webu
    </label>
    <small id="board-published-help" class="field-help" style="margin-top:.2rem">Když volbu vypnete, položka zůstane uložená jen v administraci.</small>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id ? 'Uložit změny' : 'Přidat položku sekce' ?></button>
    <a href="board.php" style="margin-left:1rem">Zrušit</a>
    <?php if (($document['status'] ?? 'published') === 'published'
        && (int)($document['is_published'] ?? 1) === 1
        && !empty($document['slug'])
        && (string)($document['posted_date'] ?? '') !== ''
        && (string)$document['posted_date'] <= date('Y-m-d')): ?>
      <a href="<?= h(boardPublicPath($document)) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
    <?php endif; ?>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
(function () {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    const boardTypeInput = document.getElementById('board_type');
    const boardTypeHelp = document.getElementById('board-type-help');
    const boardTypeHelpMap = <?= json_encode($boardTypeHelpMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let slugManual = <?= !empty($document['slug']) ? 'true' : 'false' ?>;

    const slugify = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    slugInput?.addEventListener('input', function () {
        slugManual = this.value.trim() !== '';
    });

    titleInput?.addEventListener('input', function () {
        if (slugManual || !slugInput) {
            return;
        }
        slugInput.value = slugify(this.value);
    });

    const syncBoardTypeHelp = function () {
        if (!boardTypeInput || !boardTypeHelp) {
            return;
        }
        boardTypeHelp.textContent = boardTypeHelpMap[boardTypeInput.value] || '';
    };

    boardTypeInput?.addEventListener('change', syncBoardTypeHelp);
    syncBoardTypeHelp();
})();
</script>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script nonce="<?= cspNonce() ?>">
(function () {
    const textarea = document.getElementById('description');
    const wrapper = document.getElementById('editor-description');
    const quill = new Quill(wrapper, {
        theme: 'snow',
        modules: {
            toolbar: [[{'header': [2, 3, false]}], 'bold', 'italic', 'underline', {'list': 'ordered'}, {'list': 'bullet'}, 'link', 'clean']
        }
    });
    quill.root.innerHTML = textarea.value;
    textarea.closest('form')?.addEventListener('submit', function () {
        textarea.value = quill.root.innerHTML;
    });
})();
</script>
<?php endif; ?>

<?php adminFooter(); ?>
