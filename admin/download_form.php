<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
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
    'project_url' => '',
    'release_date' => '',
    'requirements' => '',
    'checksum_sha256' => '',
    'series_key' => '',
    'external_url' => '',
    'filename' => '',
    'original_name' => '',
    'file_size' => 0,
    'download_count' => 0,
    'is_featured' => 0,
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
    'project_url' => 'Domovská stránka projektu musí být platná adresa začínající na http:// nebo https://.',
    'release_date' => 'Datum vydání nemá platný formát.',
    'series' => 'Skupina verzí může obsahovat jen malá písmena, číslice a pomlčky.',
    'checksum' => 'SHA-256 checksum musí obsahovat 64 hexadecimálních znaků.',
    'image' => 'Náhledový obrázek se nepodařilo uložit.',
    'file' => 'Soubor se nepodařilo uložit nebo má nepovolený formát.',
    default => '',
};

adminHeader($id ? 'Upravit položku ke stažení' : 'Nová položka ke stažení');
?>

<?php if ($errorMessage !== ''): ?>
  <p class="error" role="alert"><?= h($errorMessage) ?></p>
<?php endif; ?>

<?php if ($id !== null): ?>
  <p><a href="revisions.php?type=download&amp;id=<?= (int)$id ?>">Historie revizí</a></p>
<?php endif; ?>

<p><a href="downloads.php"><span aria-hidden="true">←</span> Zpět na přehled ke stažení</a></p>
<p style="margin-top:0;color:#555">
  Vytvořte přehlednou kartu ke stažení. Může odkazovat na lokální soubor, externí stránku projektu, nebo na obojí zároveň.
</p>

<form method="post" action="download_save.php" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id !== null): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Základní údaje položky</legend>

    <label for="title">Název <span aria-hidden="true">*</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           value="<?= h((string)$download['title']) ?>">

    <label for="slug">Slug <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255"
           pattern="[a-z0-9\-]+" aria-describedby="download-slug-help"
           value="<?= h((string)$download['slug']) ?>">
    <small id="download-slug-help" class="field-help">Adresa se vyplní automaticky podle názvu položky. Pokud ji upravíte ručně, použijte malá písmena, číslice a pomlčky.</small>

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

    <label for="excerpt">Krátký perex</label>
    <textarea id="excerpt" name="excerpt" rows="3"><?= h((string)$download['excerpt']) ?></textarea>

    <label for="description">Popis položky</label>
    <?php if ($editorMode === 'wysiwyg'): ?>
      <div id="description_editor" class="quill-editor" style="min-height:16rem"></div>
      <textarea id="description" name="description" rows="10" class="visually-hidden" aria-describedby="download-description-help"><?= h((string)$download['description']) ?></textarea>
      <small id="download-description-help" class="field-help">HTML textarea je přístupnější varianta; WYSIWYG je jen volitelný vizuální režim.</small>
    <?php else: ?>
      <textarea id="description" name="description" rows="10" aria-describedby="download-description-help"><?= h((string)$download['description']) ?></textarea>
      <small id="download-description-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small>
      <?php renderAdminContentReferencePicker('description'); ?>
    <?php endif; ?>
  </fieldset>

  <fieldset>
    <legend>Zdroje a odkazy</legend>

    <label for="file">Soubor</label>
    <input type="file" id="file" name="file"
           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.zip,.7z,.tar,.gz,.bz2,.txt,.exe,.msi,.apk,.jar,.dmg,.pkg,.deb,.rpm,.appimage"
           aria-describedby="download-file-help<?= (string)$download['original_name'] !== '' ? ' download-file-current' : '' ?>">
    <small id="download-file-help" class="field-help">Můžete nahrát dokument, archiv nebo instalační balíček. U lokálního souboru se SHA-256 checksum dopočítá automaticky.</small>
    <?php if ((string)$download['original_name'] !== ''): ?>
      <small id="download-file-current" class="field-help">Aktuální soubor: <strong><?= h((string)$download['original_name']) ?></strong><?php if ((int)$download['file_size'] > 0): ?> (<?= h(formatFileSize((int)$download['file_size'])) ?>)<?php endif; ?>. Nahrajte nový, pokud ho chcete nahradit.</small>
    <?php endif; ?>
    <?php if ((string)$download['filename'] !== ''): ?>
      <label style="font-weight:normal;margin-top:.75rem">
        <input type="checkbox" name="file_delete" value="1">
        Odebrat stávající soubor a ponechat jen detail / externí odkaz
      </label>
    <?php endif; ?>

    <label for="external_url">Externí odkaz ke stažení</label>
    <input type="url" id="external_url" name="external_url" maxlength="255" aria-describedby="download-external-url-help"
           placeholder="https://example.com/download"
           value="<?= h((string)$download['external_url']) ?>">
    <small id="download-external-url-help" class="field-help">Hodí se třeba pro GitHub Releases, App Store nebo veřejnou stránku balíčku.</small>

    <label for="project_url">Domovská stránka projektu</label>
    <input type="url" id="project_url" name="project_url" maxlength="255" aria-describedby="download-project-url-help"
           placeholder="https://example.com"
           value="<?= h((string)$download['project_url']) ?>">
    <small id="download-project-url-help" class="field-help">Volitelný odkaz na web projektu, dokumentaci nebo produktovou stránku.</small>
  </fieldset>

  <fieldset>
    <legend>Praktické informace a kompatibilita</legend>

    <label for="version_label">Verze</label>
    <input type="text" id="version_label" name="version_label" maxlength="100"
           placeholder="např. 2.4.1"
           value="<?= h((string)$download['version_label']) ?>">

    <label for="release_date">Datum vydání</label>
    <input type="date" id="release_date" name="release_date"
           value="<?= h((string)$download['release_date']) ?>">

    <label for="platform_label">Platforma / cílové prostředí</label>
    <input type="text" id="platform_label" name="platform_label" maxlength="100"
           placeholder="např. Windows, Android, PDF, Web"
           value="<?= h((string)$download['platform_label']) ?>">

    <label for="license_label">Licence</label>
    <input type="text" id="license_label" name="license_label" maxlength="100"
           placeholder="např. MIT, GPL, freeware"
           value="<?= h((string)$download['license_label']) ?>">

    <label for="checksum_sha256">SHA-256 checksum</label>
    <input type="text" id="checksum_sha256" name="checksum_sha256" maxlength="64" aria-describedby="download-checksum-help"
           placeholder="64 hexadecimálních znaků"
           value="<?= h((string)$download['checksum_sha256']) ?>">
    <small id="download-checksum-help" class="field-help">Pro lokální soubor se vyplní automaticky při nahrání. U externího odkazu ho můžete doplnit ručně.</small>

    <label for="series_key">Skupina verzí</label>
    <input type="text" id="series_key" name="series_key" maxlength="150" aria-describedby="download-series-help"
           placeholder="např. moje-aplikace"
           value="<?= h((string)$download['series_key']) ?>">
    <small id="download-series-help" class="field-help">Pokud více položek patří do jedné řady verzí, použijte stejný klíč. Na detailu se pak zobrazí i další verze.</small>

    <label for="requirements">Požadavky a kompatibilita</label>
    <textarea id="requirements" name="requirements" rows="4" aria-describedby="download-requirements-help"><?= h((string)$download['requirements']) ?></textarea>
    <small id="download-requirements-help" class="field-help">Uveďte třeba požadovaný systém, závislosti, podporované verze nebo instalační poznámky.</small>
  </fieldset>

  <fieldset>
    <legend>Náhled a zveřejnění</legend>

    <label for="download_image">Náhledový obrázek</label>
    <input type="file" id="download_image" name="download_image" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp">
    <?php if ((string)$download['image_url'] !== ''): ?>
      <div style="margin:.75rem 0">
        <img src="<?= h((string)$download['image_url']) ?>" alt="Náhled obrázku" style="max-width:16rem;height:auto;border:1px solid #d6d6d6;border-radius:.75rem">
      </div>
      <label style="font-weight:normal">
        <input type="checkbox" name="download_image_delete" value="1">
        Odebrat stávající náhledový obrázek
      </label>
    <?php endif; ?>

    <label style="font-weight:normal;margin-top:1rem">
      <input type="checkbox" name="is_featured" value="1"<?= (int)($download['is_featured'] ?? 0) === 1 ? ' checked' : '' ?>>
      Doporučená položka
    </label>
    <small class="field-help">Doporučené položky se ve výpisech řadí výš a můžete na ně cílit i filtry.</small>

    <label style="font-weight:normal;margin-top:1rem">
      <input type="checkbox" name="is_published" value="1" aria-describedby="download-published-help"
             <?= (int)($download['is_published'] ?? 1) === 1 ? 'checked' : '' ?>>
      Zveřejnit na webu
    </label>
    <small id="download-published-help" class="field-help" style="margin-top:.2rem">Když volbu vypnete, položka se na veřejném webu nezobrazí.</small>
  </fieldset>

  <fieldset style="margin-top:1rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Stav publikace</legend>
    <label for="article_status">Stav</label>
    <select id="article_status" name="article_status">
      <option value="draft"<?= ($download['status'] ?? '') === 'draft' ? ' selected' : '' ?>>Koncept</option>
      <?php if (currentUserHasCapability('content_approve_shared')): ?>
        <option value="published"<?= ($download['status'] ?? 'published') === 'published' ? ' selected' : '' ?>>Publikováno</option>
      <?php endif; ?>
      <option value="pending"<?= ($download['status'] ?? '') === 'pending' ? ' selected' : '' ?>>Čeká na schválení</option>
    </select>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id !== null ? 'Uložit změny' : 'Přidat položku ke stažení' ?></button>
    <a href="downloads.php" style="margin-left:1rem">Zrušit</a>
    <?php if ($id !== null && (string)$download['slug'] !== '' && (string)$download['status'] === 'published' && (int)($download['is_published'] ?? 0) === 1): ?>
      <a href="<?= h(downloadPublicPath($download)) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
    <?php endif; ?>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
(() => {
  const titleField = document.getElementById('title');
  const slugField = document.getElementById('slug');
  if (!titleField || !slugField) {
    return;
  }

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
})();
</script>

<?php if ($editorMode === 'wysiwyg'): ?>
  <link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
  <script nonce="<?= cspNonce() ?>">
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
