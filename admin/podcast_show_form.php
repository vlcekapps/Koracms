<?php
require_once __DIR__ . '/layout.php';
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
  Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<form method="post" action="podcast_show_save.php" enctype="multipart/form-data" novalidate<?= $formError !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id !== null): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Pořad</legend>

    <label for="title">Název pořadu <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           value="<?= h((string)$show['title']) ?>">

    <label for="slug">Slug veřejné stránky <span aria-hidden="true">*</span>
      <small>(pouze malá písmena, číslice a pomlčky)</small>
    </label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="100" pattern="[a-z0-9\-]+"
           value="<?= h((string)$show['slug']) ?>">

    <label for="author">Autor / vydavatel</label>
    <input type="text" id="author" name="author" maxlength="255"
           value="<?= h((string)$show['author']) ?>">

    <label for="language">Jazyk <small>(kód dle IETF, např. cs, en)</small></label>
    <input type="text" id="language" name="language" maxlength="10" style="width:8rem"
           value="<?= h((string)$show['language']) ?>">

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
    <legend>Popis a cover</legend>

    <label for="description">Popis pořadu</label>
    <?php if ($useWysiwyg): ?>
      <div id="description_editor" class="quill-editor" style="min-height:14rem"></div>
      <textarea id="description" name="description" rows="8" class="visually-hidden"><?= h((string)$show['description']) ?></textarea>
      <small style="color:#666">HTML textarea je přístupnější varianta; WYSIWYG je jen volitelný vizuální režim.</small>
    <?php else: ?>
      <textarea id="description" name="description" rows="8"><?= h((string)$show['description']) ?></textarea>
      <small style="color:#666">Podporuje HTML i Markdown syntaxi.</small>
    <?php endif; ?>

    <label for="cover_image">Cover obrázek</label>
    <?php if ((string)$show['cover_url'] !== ''): ?>
      <div style="margin:.75rem 0">
        <img src="<?= h((string)$show['cover_url']) ?>" alt="" style="display:block;max-width:18rem;width:100%;border-radius:1rem;border:1px solid #d6d6d6">
      </div>
      <small>Aktuální cover je už nahraný. Nahrajte nový pro nahrazení.</small>
    <?php endif; ?>
    <input type="file" id="cover_image" name="cover_image" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,image/*">
    <?php if ((string)$show['cover_image'] !== ''): ?>
      <label for="cover_image_delete" style="font-weight:normal;margin-top:.5rem">
        <input type="checkbox" id="cover_image_delete" name="cover_image_delete" value="1">
        Odebrat stávající cover obrázek
      </label>
    <?php endif; ?>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id !== null ? 'Uložit změny' : 'Přidat podcast' ?></button>
    <?php if ($id !== null && (string)$show['slug'] !== ''): ?>
      <a href="<?= h((string)$show['public_path']) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Veřejná stránka</a>
      <a href="<?= h(BASE_URL . '/podcast/feed.php?slug=' . rawurlencode((string)$show['slug'])) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">RSS feed</a>
    <?php endif; ?>
    <a href="podcast_shows.php" style="margin-left:1rem">Zrušit</a>
  </div>
</form>

<style>.visually-hidden{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}</style>

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
