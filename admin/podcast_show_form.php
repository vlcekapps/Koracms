<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu podcastů nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$show = [
    'id' => null,
    'title' => '',
    'slug' => '',
    'description' => '',
    'author' => '',
    'cover_image' => '',
    'language' => 'cs',
    'category' => '',
    'website_url' => '',
];

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_podcast_shows WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        header('Location: podcast_shows.php');
        exit;
    }
    $show = array_merge($show, $existing);
}

$show = hydratePodcastShowPresentation($show);
$categories = $pdo->query(
    "SELECT DISTINCT category FROM cms_podcast_shows WHERE category <> '' ORDER BY category"
)->fetchAll(\PDO::FETCH_COLUMN);
$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$err = trim((string)($_GET['err'] ?? ''));
$formError = match ($err) {
    'required' => 'Název pořadu je povinný.',
    'slug' => 'Slug pořadu musí obsahovat alespoň jedno písmeno nebo číslo.',
    'slug_taken' => 'Tento slug už používá jiný pořad.',
    'url' => 'Web pořadu musí mít platný formát.',
    'cover' => 'Cover obrázek se nepodařilo uložit.',
    default => '',
};

adminHeader($id !== null ? 'Upravit podcast' : 'Nový podcast');
?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error"><?= h($formError) ?></p>
<?php endif; ?>

<p style="margin-top:0;font-size:.9rem">
  Vyplňte základní údaje o podcastu. Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<p><a href="podcast_shows.php"><span aria-hidden="true">←</span> Zpět na přehled podcastů</a></p>

<form method="post" action="podcast_show_save.php" enctype="multipart/form-data" novalidate<?= $formError !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id !== null): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Základní údaje podcastu</legend>

    <label for="title">Název pořadu <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           value="<?= h((string)$show['title']) ?>">

    <label for="slug">Slug veřejné stránky <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="100" pattern="[a-z0-9\-]+"
           aria-describedby="podcast-show-slug-help"
           value="<?= h((string)$show['slug']) ?>">
    <small id="podcast-show-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Použijte malá písmena, číslice a pomlčky.</small>

    <label for="author">Autor / vydavatel</label>
    <input type="text" id="author" name="author" maxlength="255"
           value="<?= h((string)$show['author']) ?>">

    <label for="language">Jazyk</label>
    <input type="text" id="language" name="language" maxlength="10" style="width:8rem"
           aria-describedby="podcast-show-language-help"
           value="<?= h((string)$show['language']) ?>">
    <small id="podcast-show-language-help" class="field-help">Použijte kód dle IETF, například <code>cs</code> nebo <code>en</code>.</small>

    <label for="category">Kategorie</label>
    <input type="text" id="category" name="category" maxlength="100" list="podcast-categories"
           value="<?= h((string)$show['category']) ?>">
    <datalist id="podcast-categories">
      <?php foreach ($categories as $category): ?>
        <option value="<?= h((string)$category) ?>">
      <?php endforeach; ?>
    </datalist>

    <label for="website_url">Web pořadu</label>
    <input type="url" id="website_url" name="website_url" maxlength="500"
           placeholder="https://example.com/podcast"
           value="<?= h((string)$show['website_url']) ?>">
  </fieldset>

  <fieldset>
    <legend>Popis a titulní obrázek</legend>

    <label for="description">Popis pořadu</label>
    <?php if ($useWysiwyg): ?>
      <div id="description_editor" class="quill-editor" style="min-height:14rem"></div>
      <textarea id="description" name="description" rows="8" class="visually-hidden" aria-describedby="podcast-show-description-help"><?= h((string)$show['description']) ?></textarea>
      <small id="podcast-show-description-help" class="field-help">HTML textarea je přístupnější varianta; WYSIWYG je jen volitelný vizuální režim.</small>
    <?php else: ?>
      <textarea id="description" name="description" rows="8" aria-describedby="podcast-show-description-help"><?= h((string)$show['description']) ?></textarea>
      <small id="podcast-show-description-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small>
      <?php renderAdminContentReferencePicker('description'); ?>
    <?php endif; ?>

    <label for="cover_image">Cover obrázek</label>
    <?php if ((string)$show['cover_url'] !== ''): ?>
      <div style="margin:.75rem 0">
        <img src="<?= h((string)$show['cover_url']) ?>" alt="" style="display:block;max-width:18rem;width:100%;border-radius:1rem;border:1px solid #d6d6d6">
      </div>
      <small id="podcast-show-cover-current" class="field-help">Aktuální titulní obrázek je nahraný. Nahrajte nový, pokud ho chcete nahradit.</small>
    <?php endif; ?>
    <input type="file" id="cover_image" name="cover_image" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,image/*"
           aria-describedby="<?= (string)$show['cover_url'] !== '' ? 'podcast-show-cover-current' : 'podcast-show-cover-help' ?>">
    <?php if ((string)$show['cover_url'] === ''): ?>
      <small id="podcast-show-cover-help" class="field-help">Volitelné. Hodí se pro titulní obrázek pořadu.</small>
    <?php endif; ?>
    <?php if ((string)$show['cover_image'] !== ''): ?>
      <label for="cover_image_delete" style="font-weight:normal;margin-top:.5rem">
        <input type="checkbox" id="cover_image_delete" name="cover_image_delete" value="1">
        Odebrat stávající cover obrázek
      </label>
    <?php endif; ?>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id !== null ? 'Uložit změny' : 'Vytvořit podcast' ?></button>
    <a href="podcast_shows.php" style="margin-left:1rem">Zrušit</a>
    <?php if ($id !== null && (string)$show['slug'] !== ''): ?>
      <a href="<?= h((string)$show['public_path']) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
      <a href="<?= h(BASE_URL . '/podcast/feed.php?slug=' . rawurlencode((string)$show['slug'])) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">RSS feed</a>
    <?php endif; ?>
  </div>
</form>


<script>
(function () {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    let slugManual = <?= $id !== null && !empty($show['slug']) ? 'true' : 'false' ?>;

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
})();
</script>

<?php if ($useWysiwyg): ?>
  <link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
  <script>
  (() => {
    const descriptionField = document.getElementById('description');
    const host = document.getElementById('description_editor');
    if (!descriptionField || !host || typeof Quill === 'undefined') {
      return;
    }

    const quill = new Quill(host, {
      theme: 'snow',
      modules: {
        toolbar: [
          [{ header: [1, 2, 3, false] }],
          ['bold', 'italic', 'underline', 'link'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['blockquote', 'code-block'],
          ['clean']
        ]
      }
    });

    quill.root.innerHTML = descriptionField.value;
    quill.on('text-change', () => {
      descriptionField.value = quill.root.innerHTML;
    });
  })();
  </script>
<?php endif; ?>

<?php adminFooter(); ?>
