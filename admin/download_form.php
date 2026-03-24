<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu souborů ke stažení nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$download = [
    'id' => null,
    'title' => '',
    'slug' => '',
    'download_type' => 'document',
    'dl_category_id' => null,
    'excerpt' => '',
    'description' => '',
    'image_file' => '',
    'version_label' => '',
    'platform_label' => '',
    'license_label' => '',
    'external_url' => '',
    'filename' => '',
    'original_name' => '',
    'file_size' => 0,
    'sort_order' => 0,
    'is_published' => 1,
    'status' => 'published',
];

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_downloads WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        header('Location: downloads.php');
        exit;
    }
    $download = array_merge($download, $existing);
}

$download = hydrateDownloadPresentation($download);
$categories = $pdo->query("SELECT id, name FROM cms_dl_categories ORDER BY name")->fetchAll();
$downloadTypes = downloadTypeDefinitions();
$editorMode = getSetting('content_editor', 'html');
$err = trim((string)($_GET['err'] ?? ''));
$errorMessage = match ($err) {
    'required' => 'Název položky je povinný.',
    'slug' => 'Slug položky musí obsahovat alespoň jedno písmeno nebo číslo.',
    'slug_taken' => 'Tento slug už používá jiná položka ke stažení.',
    'source' => 'Položka musí mít alespoň nahraný soubor nebo externí odkaz.',
    'url' => 'Externí odkaz musí být platná adresa začínající na http:// nebo https://.',
    'image' => 'Náhledový obrázek se nepodařilo uložit.',
    'file' => 'Soubor se nepodařilo uložit nebo má nepovolený formát.',
    default => '',
};

adminHeader($id ? 'Upravit položku ke stažení' : 'Nová položka ke stažení');
?>

<?php if ($errorMessage !== ''): ?>
  <p class="error" role="alert"><?= h($errorMessage) ?></p>
<?php endif; ?>

<form method="post" action="download_save.php" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id !== null): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Položka ke stažení</legend>

    <label for="title">Název <span aria-hidden="true">*</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           value="<?= h((string)$download['title']) ?>">

    <label for="slug">Slug <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255"
           pattern="[a-z0-9\-]+"
           value="<?= h((string)$download['slug']) ?>">
    <small style="color:#666">Použije se ve veřejné adrese, například <code>/downloads/moje-aplikace</code>.</small>

    <label for="download_type">Typ položky</label>
    <select id="download_type" name="download_type">
      <?php foreach ($downloadTypes as $typeKey => $typeMeta): ?>
        <option value="<?= h($typeKey) ?>"<?= (string)$download['download_type'] === $typeKey ? ' selected' : '' ?>>
          <?= h((string)$typeMeta['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="dl_category_id">Kategorie</label>
    <select id="dl_category_id" name="dl_category_id">
      <option value="">– bez kategorie –</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int)$category['id'] ?>"
          <?= (int)($download['dl_category_id'] ?? 0) === (int)$category['id'] ? ' selected' : '' ?>>
          <?= h((string)$category['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="version_label">Verze</label>
    <input type="text" id="version_label" name="version_label" maxlength="100"
           placeholder="např. 2.4.1"
           value="<?= h((string)$download['version_label']) ?>">

    <label for="platform_label">Platforma / cílové prostředí</label>
    <input type="text" id="platform_label" name="platform_label" maxlength="100"
           placeholder="např. Windows, Android, PDF, Web"
           value="<?= h((string)$download['platform_label']) ?>">

    <label for="license_label">Licence</label>
    <input type="text" id="license_label" name="license_label" maxlength="100"
           placeholder="např. MIT, GPL, freeware"
           value="<?= h((string)$download['license_label']) ?>">

    <label for="excerpt">Krátký perex</label>
    <textarea id="excerpt" name="excerpt" rows="3"><?= h((string)$download['excerpt']) ?></textarea>

    <label for="description">Popis</label>
    <?php if ($editorMode === 'wysiwyg'): ?>
      <div id="description_editor" class="quill-editor" style="min-height:16rem"></div>
      <textarea id="description" name="description" rows="10" class="visually-hidden"><?= h((string)$download['description']) ?></textarea>
      <small style="color:#666">HTML textarea je přístupnější varianta; WYSIWYG je jen volitelný vizuální režim.</small>
    <?php else: ?>
      <textarea id="description" name="description" rows="10"><?= h((string)$download['description']) ?></textarea>
    <?php endif; ?>
  </fieldset>

  <fieldset>
    <legend>Zdroje a odkazy</legend>

    <label for="file">
      Soubor
      <?php if ((string)$download['original_name'] !== ''): ?>
        <small>(aktuální: <strong><?= h((string)$download['original_name']) ?></strong>
          <?php if ((int)$download['file_size'] > 0): ?>(<?= h(formatFileSize((int)$download['file_size'])) ?>)<?php endif; ?>
          – nahrajte nový pro nahrazení)</small>
      <?php endif; ?>
    </label>
    <input type="file" id="file" name="file"
           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.zip,.7z,.tar,.gz,.bz2,.txt,.exe,.msi,.apk,.jar,.dmg,.pkg,.deb,.rpm,.appimage">
    <small style="color:#666">Povolené formáty zahrnují dokumenty, archivy a běžné softwarové balíčky.</small>
    <?php if ((string)$download['filename'] !== ''): ?>
      <label style="font-weight:normal;margin-top:.75rem">
        <input type="checkbox" name="file_delete" value="1">
        Odebrat stávající soubor a ponechat jen detail / externí odkaz
      </label>
    <?php endif; ?>

    <label for="external_url">Externí odkaz</label>
    <input type="url" id="external_url" name="external_url" maxlength="255"
           placeholder="https://example.com/download"
           value="<?= h((string)$download['external_url']) ?>">
    <small style="color:#666">Hodí se například pro GitHub Releases, App Store nebo produktovou stránku.</small>
  </fieldset>

  <fieldset>
    <legend>Náhled a zobrazení</legend>

    <label for="download_image">Náhledový obrázek</label>
    <input type="file" id="download_image" name="download_image" accept=".jpg,.jpeg,.png,.gif,.webp,.svg">
    <?php if ((string)$download['image_url'] !== ''): ?>
      <div style="margin:.75rem 0">
        <img src="<?= h((string)$download['image_url']) ?>" alt="" style="max-width:16rem;height:auto;border:1px solid #d6d6d6;border-radius:.75rem">
      </div>
      <label style="font-weight:normal">
        <input type="checkbox" name="download_image_delete" value="1">
        Odebrat stávající náhledový obrázek
      </label>
    <?php endif; ?>

    <label for="sort_order">Pořadí</label>
    <input type="number" id="sort_order" name="sort_order" min="0" style="width:8rem"
           value="<?= (int)($download['sort_order'] ?? 0) ?>">

    <label style="font-weight:normal;margin-top:1rem">
      <input type="checkbox" name="is_published" value="1"
             <?= (int)($download['is_published'] ?? 1) === 1 ? 'checked' : '' ?>>
      Zobrazit na webu
    </label>

    <?php if ($id !== null && (string)$download['slug'] !== '' && (string)$download['status'] === 'published' && (int)($download['is_published'] ?? 0) === 1): ?>
      <p style="margin-top:1rem">
        <a href="<?= h(downloadPublicPath($download)) ?>" target="_blank" rel="noopener noreferrer">Otevřít veřejnou stránku položky</a>
      </p>
    <?php endif; ?>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id !== null ? 'Uložit změny' : 'Přidat položku' ?></button>
    <a href="downloads.php" style="margin-left:1rem">Zrušit</a>
  </div>
</form>

<style>.visually-hidden{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}</style>

<script>
(() => {
  const titleField = document.getElementById('title');
  const slugField = document.getElementById('slug');
  if (titleField && slugField) {
    const slugify = (value) => value
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');

    let slugTouched = slugField.value.trim() !== '';
    slugField.addEventListener('input', () => {
      slugTouched = slugField.value.trim() !== '';
    });
    titleField.addEventListener('input', () => {
      if (!slugTouched) {
        slugField.value = slugify(titleField.value);
      }
    });
  }
})();
</script>

<?php if ($editorMode === 'wysiwyg'): ?>
  <link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
  <script>
  (() => {
    const descriptionField = document.getElementById('description');
    const host = document.getElementById('description_editor');
    if (!descriptionField || !host || typeof Quill === 'undefined') {
      return;
    }

    const toolbar = [
      [{ header: [1, 2, 3, false] }],
      ['bold', 'italic', 'underline', 'link'],
      [{ list: 'ordered' }, { list: 'bullet' }],
      ['blockquote', 'code-block'],
      ['clean']
    ];

    const quill = new Quill(host, {
      theme: 'snow',
      modules: { toolbar }
    });

    quill.root.innerHTML = descriptionField.value;
    quill.on('text-change', () => {
      descriptionField.value = quill.root.innerHTML;
    });
  })();
  </script>
<?php endif; ?>

<?php adminFooter(); ?>
