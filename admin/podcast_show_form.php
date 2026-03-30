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
    'subtitle' => '',
    'cover_image' => '',
    'language' => 'cs',
    'category' => '',
    'owner_name' => '',
    'owner_email' => '',
    'explicit_mode' => 'no',
    'show_type' => 'episodic',
    'feed_complete' => 0,
    'feed_episode_limit' => 100,
    'website_url' => '',
    'is_published' => 1,
    'status' => 'published',
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
$backUrl = internalRedirectTarget((string)($_GET['redirect'] ?? ''), BASE_URL . '/admin/podcast_shows.php');
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
    'owner_email' => 'E-mail vlastníka feedu musí mít platný formát.',
    'feed_limit' => 'Počet epizod v RSS feedu musí být číslo od 1 do 1000.',
    'cover' => 'Cover musí být čtvercový JPG nebo PNG v rozmezí 1024×1024 až 3000×3000 px.',
    default => '',
};
$coverHelpIds = (string)$show['cover_url'] !== ''
    ? 'podcast-show-cover-current podcast-show-cover-help'
    : 'podcast-show-cover-help';

adminHeader($id !== null ? 'Upravit podcast' : 'Nový podcast');
?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error"><?= h($formError) ?></p>
<?php endif; ?>

<p style="margin-top:0;font-size:.9rem">
  Vyplňte základní údaje o podcastu. Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<p><a href="<?= h($backUrl) ?>"><span aria-hidden="true">&larr;</span> Zpět na přehled podcastů</a></p>

<form method="post" action="podcast_show_save.php" enctype="multipart/form-data" novalidate<?= $formError !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="redirect" value="<?= h($backUrl) ?>">
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

    <label for="subtitle">Krátký podtitul pro katalogy</label>
    <input type="text" id="subtitle" name="subtitle" maxlength="255" aria-describedby="podcast-show-subtitle-help"
           value="<?= h((string)$show['subtitle']) ?>">
    <small id="podcast-show-subtitle-help" class="field-help">Volitelné. Hodí se pro Apple Podcasts a další aplikace jako krátké shrnutí pořadu.</small>

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
    <legend>Feed a katalogy podcastů</legend>

    <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-start">
      <div style="flex:1 1 12rem">
        <label for="feed_episode_limit">Počet epizod v RSS feedu</label>
        <input type="number" id="feed_episode_limit" name="feed_episode_limit" min="1" max="1000"
               aria-describedby="podcast-show-feed-limit-help"
               value="<?= (int)$show['feed_episode_limit'] ?>">
        <small id="podcast-show-feed-limit-help" class="field-help">Běžné podcast hostingy omezují feed na poslední epizody. Zadejte počet od 1 do 1000.</small>
      </div>

      <div style="flex:1 1 12rem">
        <label for="show_type">Typ pořadu</label>
        <select id="show_type" name="show_type" aria-describedby="podcast-show-type-help">
          <option value="episodic"<?= (string)$show['show_type'] === 'episodic' ? ' selected' : '' ?>>Epizodický</option>
          <option value="serial"<?= (string)$show['show_type'] === 'serial' ? ' selected' : '' ?>>Seriálový</option>
        </select>
        <small id="podcast-show-type-help" class="field-help">Apple Podcasts používá typ pořadu pro lepší řazení a zobrazování epizod.</small>
      </div>

      <div style="flex:1 1 12rem">
        <label for="explicit_mode">Explicitní obsah</label>
        <select id="explicit_mode" name="explicit_mode">
          <option value="no"<?= (string)$show['explicit_mode'] === 'no' ? ' selected' : '' ?>>Ne</option>
          <option value="clean"<?= (string)$show['explicit_mode'] === 'clean' ? ' selected' : '' ?>>Clean</option>
          <option value="yes"<?= (string)$show['explicit_mode'] === 'yes' ? ' selected' : '' ?>>Ano</option>
        </select>
      </div>
    </div>

    <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-start">
      <div style="flex:1 1 18rem">
        <label for="owner_name">Vlastník feedu</label>
        <input type="text" id="owner_name" name="owner_name" maxlength="255"
               value="<?= h((string)$show['owner_name']) ?>">
      </div>

      <div style="flex:1 1 18rem">
        <label for="owner_email">E-mail vlastníka feedu</label>
        <input type="email" id="owner_email" name="owner_email" maxlength="255" aria-describedby="podcast-show-owner-email-help"
               value="<?= h((string)$show['owner_email']) ?>">
        <small id="podcast-show-owner-email-help" class="field-help">Používá se v RSS feedu jako kontakt pro podcast katalogy.</small>
      </div>
    </div>

    <label for="feed_complete" style="font-weight:normal;margin-top:.5rem">
      <input type="checkbox" id="feed_complete" name="feed_complete" value="1"<?= !empty($show['feed_complete']) ? ' checked' : '' ?>>
      Označit feed jako dokončený
    </label>

    <label for="is_published" style="font-weight:normal;margin-top:.5rem">
      <input type="checkbox" id="is_published" name="is_published" value="1"<?= !empty($show['is_published']) ? ' checked' : '' ?>>
      Zobrazit pořad veřejně na webu
    </label>
    <small class="field-help">Když volbu vypnete, pořad zůstane v administraci, ale nebude veřejně dostupný ani se neobjeví ve vyhledávání, sitemapě a indexu podcastů.</small>

    <?php if ((string)$show['status'] === 'pending'): ?>
      <p class="field-help" style="margin-top:.75rem">Tento pořad aktuálně čeká na schválení.</p>
    <?php endif; ?>
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
        <img src="<?= h((string)$show['cover_url']) ?>" alt="Náhled obalu podcastu" style="display:block;max-width:18rem;width:100%;border-radius:1rem;border:1px solid #d6d6d6">
      </div>
      <small id="podcast-show-cover-current" class="field-help">Aktuální titulní obrázek je nahraný. Nahrajte nový, pokud ho chcete nahradit.</small>
    <?php endif; ?>
    <input type="file" id="cover_image" name="cover_image" accept=".jpg,.jpeg,.png,image/jpeg,image/png"
           aria-describedby="<?= h($coverHelpIds) ?>">
    <small id="podcast-show-cover-help" class="field-help">Volitelné, ale doporučené. Pro podcastové aplikace a Apple Podcasts nahrajte čtvercový JPG nebo PNG v rozmezí 1024×1024 až 3000×3000 px.</small>
    <?php if ((string)$show['cover_image'] !== ''): ?>
      <label for="cover_image_delete" style="font-weight:normal;margin-top:.5rem">
        <input type="checkbox" id="cover_image_delete" name="cover_image_delete" value="1">
        Odebrat stávající cover obrázek
      </label>
    <?php endif; ?>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id !== null ? 'Uložit změny' : 'Vytvořit podcast' ?></button>
    <a href="<?= h($backUrl) ?>" style="margin-left:1rem">Zrušit</a>
    <?php if ($id !== null && (string)$show['slug'] !== ''): ?>
      <?php if (!empty($show['is_public'])): ?>
        <a href="<?= h((string)$show['public_path']) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
      <?php endif; ?>
      <a href="<?= h(BASE_URL . '/podcast/feed.php?slug=' . rawurlencode((string)$show['slug'])) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">RSS feed</a>
      <a href="<?= h(BASE_URL . '/admin/revisions.php?type=podcast_show&id=' . (int)$show['id']) ?>" style="margin-left:1rem">Historie změn</a>
    <?php endif; ?>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
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
  <script nonce="<?= cspNonce() ?>">
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
