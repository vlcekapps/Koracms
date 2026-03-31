<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu podcastů nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$showId = inputInt('get', 'show_id');
$episode = [
    'id' => null,
    'show_id' => $showId,
    'show_slug' => '',
    'show_title' => '',
    'title' => '',
    'slug' => '',
    'description' => '',
    'audio_file' => '',
    'image_file' => '',
    'audio_url' => '',
    'subtitle' => '',
    'duration' => '',
    'episode_num' => null,
    'season_num' => null,
    'episode_type' => 'full',
    'explicit_mode' => 'inherit',
    'block_from_feed' => 0,
    'publish_at' => null,
    'created_at' => null,
    'status' => 'published',
];

if ($id !== null) {
    $stmt = $pdo->prepare(
        "SELECT p.*, s.slug AS show_slug, s.title AS show_title, s.cover_image AS show_cover_image
         FROM cms_podcasts p
         INNER JOIN cms_podcast_shows s ON s.id = p.show_id
         WHERE p.id = ?"
    );
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        header('Location: podcast_shows.php');
        exit;
    }
    $episode = array_merge($episode, $existing);
    if ($showId === null) {
        $showId = (int)$episode['show_id'];
    }
}

if ($showId === null) {
    header('Location: podcast_shows.php');
    exit;
}

$showStmt = $pdo->prepare("SELECT * FROM cms_podcast_shows WHERE id = ?");
$showStmt->execute([$showId]);
$show = $showStmt->fetch() ?: null;
if (!$show) {
    header('Location: podcast_shows.php');
    exit;
}
$show = hydratePodcastShowPresentation($show);
$backUrl = internalRedirectTarget((string)($_GET['redirect'] ?? ''), BASE_URL . '/admin/podcast.php?show_id=' . (int)$showId);
$episode['show_id'] = (int)$show['id'];
$episode['show_slug'] = (string)$show['slug'];
$episode['show_title'] = (string)$show['title'];
$episode['show_cover_image'] = (string)($show['cover_image'] ?? '');
$episode = hydratePodcastEpisodePresentation($episode);

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$publishInput = !empty($episode['publish_at']) ? date('Y-m-d\TH:i', strtotime((string)$episode['publish_at'])) : '';
$err = trim((string)($_GET['err'] ?? ''));
$formError = match ($err) {
    'required' => 'Název epizody je povinný.',
    'slug' => 'Slug epizody musí obsahovat alespoň jedno písmeno nebo číslo.',
    'slug_taken' => 'Tento slug už v rámci pořadu používá jiná epizoda.',
    'url' => 'Externí audio odkaz musí mít platný formát.',
    'audio' => 'Audio soubor se nepodařilo uložit.',
    'image' => 'Obrázek epizody musí být čtvercový JPG nebo PNG v rozmezí 1024×1024 až 3000×3000 px.',
    'publish_at' => 'Plánované zveřejnění má neplatný formát data a času.',
    default => '',
};
$imageHelpIds = (string)$episode['image_url'] !== ''
    ? 'podcast-episode-image-current podcast-episode-image-help'
    : 'podcast-episode-image-help';

adminHeader($id !== null ? 'Upravit epizodu podcastu' : 'Nová epizoda podcastu');
?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error"><?= h($formError) ?></p>
<?php endif; ?>

<p><a href="<?= h($backUrl) ?>"><span aria-hidden="true">&larr;</span> Zpět na epizody podcastu</a></p>

<p style="margin-top:0;font-size:.9rem">
  Vyplňte základní údaje o epizodě. Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<form method="post" action="podcast_save.php" enctype="multipart/form-data" novalidate<?= $formError !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="show_id" value="<?= (int)$showId ?>">
  <input type="hidden" name="redirect" value="<?= h($backUrl) ?>">
  <?php if ($id !== null): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Základní údaje epizody</legend>

    <label for="title">Název epizody <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           value="<?= h((string)$episode['title']) ?>">

    <label for="slug">Slug veřejné stránky <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255" pattern="[a-z0-9\-]+"
           aria-describedby="podcast-episode-slug-help"
           value="<?= h((string)$episode['slug']) ?>">
    <small id="podcast-episode-slug-help" class="field-help">Adresa se vyplní automaticky podle názvu epizody. V rámci pořadu musí zůstat jedinečná.</small>

    <label for="subtitle">Krátký podtitul pro katalogy</label>
    <input type="text" id="subtitle" name="subtitle" maxlength="255" aria-describedby="podcast-episode-subtitle-help"
           value="<?= h((string)$episode['subtitle']) ?>">
    <small id="podcast-episode-subtitle-help" class="field-help">Volitelné. Hodí se pro Apple Podcasts a další aplikace jako krátký doplněk názvu epizody.</small>

    <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:1 1 12rem">
        <label for="episode_num">Číslo epizody</label>
        <input type="number" id="episode_num" name="episode_num" min="1" style="width:100%"
               value="<?= !empty($episode['episode_num']) ? (int)$episode['episode_num'] : '' ?>">
      </div>
      <div style="flex:1 1 12rem">
        <label for="season_num">Číslo série</label>
        <input type="number" id="season_num" name="season_num" min="1" style="width:100%"
               value="<?= !empty($episode['season_num']) ? (int)$episode['season_num'] : '' ?>">
      </div>
      <div style="flex:1 1 12rem">
        <label for="duration">Délka</label>
        <input type="text" id="duration" name="duration" maxlength="20" aria-describedby="podcast-episode-duration-help"
               value="<?= h((string)$episode['duration']) ?>">
        <small id="podcast-episode-duration-help" class="field-help">Například <code>42:30</code>.</small>
      </div>
      <div style="flex:1 1 16rem">
        <label for="publish_at">Plánované zveřejnění</label>
        <input type="datetime-local" id="publish_at" name="publish_at" style="width:100%" aria-describedby="podcast-episode-publish-help"
               value="<?= h($publishInput) ?>">
      </div>
    </div>
    <small id="podcast-episode-publish-help" class="field-help">Nechte prázdné, pokud se má epizoda zveřejnit hned po uložení nebo schválení.</small>

    <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-start">
      <div style="flex:1 1 12rem">
        <label for="episode_type">Typ epizody</label>
        <select id="episode_type" name="episode_type">
          <option value="full"<?= (string)$episode['episode_type'] === 'full' ? ' selected' : '' ?>>Plná epizoda</option>
          <option value="trailer"<?= (string)$episode['episode_type'] === 'trailer' ? ' selected' : '' ?>>Trailer</option>
          <option value="bonus"<?= (string)$episode['episode_type'] === 'bonus' ? ' selected' : '' ?>>Bonus</option>
        </select>
      </div>

      <div style="flex:1 1 12rem">
        <label for="explicit_mode">Explicitní obsah</label>
        <select id="explicit_mode" name="explicit_mode">
          <option value="inherit"<?= (string)$episode['explicit_mode'] === 'inherit' ? ' selected' : '' ?>>Převzít z pořadu</option>
          <option value="no"<?= (string)$episode['explicit_mode'] === 'no' ? ' selected' : '' ?>>Ne</option>
          <option value="clean"<?= (string)$episode['explicit_mode'] === 'clean' ? ' selected' : '' ?>>Clean</option>
          <option value="yes"<?= (string)$episode['explicit_mode'] === 'yes' ? ' selected' : '' ?>>Ano</option>
        </select>
      </div>
    </div>

    <label for="block_from_feed" style="font-weight:normal;margin-top:.5rem">
      <input type="checkbox" id="block_from_feed" name="block_from_feed" value="1"<?= !empty($episode['block_from_feed']) ? ' checked' : '' ?>>
      Skrýt epizodu z RSS feedu
    </label>
  </fieldset>

  <fieldset>
    <legend>Audio a text epizody</legend>

    <label for="audio_file">Audio soubor</label>
    <input type="file" id="audio_file" name="audio_file" accept=".mp3,.ogg,.wav,.m4a,.aac,audio/*"
           aria-describedby="podcast-episode-audio-help<?= (string)$episode['audio_file'] !== '' ? ' podcast-episode-audio-current' : '' ?>">
    <small id="podcast-episode-audio-help" class="field-help">Můžete nahrát běžný zvukový soubor, například MP3, OGG, WAV, M4A nebo AAC.</small>
    <?php if ((string)$episode['audio_file'] !== ''): ?>
      <small id="podcast-episode-audio-current" class="field-help">Aktuální soubor je nahraný. Nahrajte nový, pokud ho chcete nahradit.</small>
    <?php endif; ?>
    <?php if ((string)$episode['audio_file'] !== ''): ?>
      <label for="audio_file_delete" style="font-weight:normal;margin-top:.5rem">
        <input type="checkbox" id="audio_file_delete" name="audio_file_delete" value="1">
        Odebrat stávající audio soubor
      </label>
    <?php endif; ?>

    <label for="audio_url">Externí audio odkaz</label>
    <input type="url" id="audio_url" name="audio_url" maxlength="500" aria-describedby="podcast-episode-audio-url-help"
           placeholder="https://example.com/episode.mp3"
           value="<?= h((string)$episode['audio_url']) ?>">
    <small id="podcast-episode-audio-url-help" class="field-help">Hodí se pro externí hosting nebo přímý odkaz na audio soubor.</small>

    <label for="image_file">Obrázek epizody</label>
    <?php if ((string)$episode['image_url'] !== ''): ?>
      <div style="margin:.75rem 0">
        <img src="<?= h((string)$episode['image_url']) ?>" alt="Náhled obrázku epizody" style="display:block;max-width:18rem;width:100%;border-radius:1rem;border:1px solid #d6d6d6">
      </div>
      <small id="podcast-episode-image-current" class="field-help">Aktuální obrázek epizody je nahraný. Nahrajte nový, pokud ho chcete nahradit.</small>
    <?php endif; ?>
    <input type="file" id="image_file" name="image_file" accept=".jpg,.jpeg,.png,image/jpeg,image/png"
           aria-describedby="<?= h($imageHelpIds) ?>">
    <small id="podcast-episode-image-help" class="field-help">Volitelné. Pokud chcete pro epizodu vlastní artwork, nahrajte čtvercový JPG nebo PNG v rozmezí 1024×1024 až 3000×3000 px.</small>
    <?php if ((string)$episode['image_file'] !== ''): ?>
      <label for="image_file_delete" style="font-weight:normal;margin-top:.5rem">
        <input type="checkbox" id="image_file_delete" name="image_file_delete" value="1">
        Odebrat stávající obrázek epizody
      </label>
    <?php endif; ?>

    <label for="description">Popis epizody</label>
    <?php if ($useWysiwyg): ?>
      <div id="description_editor" class="quill-editor" style="min-height:16rem"></div>
      <textarea id="description" name="description" rows="10" class="visually-hidden" aria-describedby="podcast-episode-description-help"><?= h((string)$episode['description']) ?></textarea>
      <small id="podcast-episode-description-help" class="field-help">HTML textarea je přístupnější varianta; WYSIWYG je jen volitelný vizuální režim.</small>
    <?php else: ?>
      <textarea id="description" name="description" rows="10" aria-describedby="podcast-episode-description-help"><?= h((string)$episode['description']) ?></textarea>
      <small id="podcast-episode-description-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small>
      <?php renderAdminContentReferencePicker('description'); ?>
    <?php endif; ?>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id !== null ? 'Uložit změny' : 'Přidat epizodu podcastu' ?></button>
    <a href="<?= h($backUrl) ?>" style="margin-left:1rem">Zrušit</a>
    <?php if ($id !== null && (string)$episode['status'] === 'published' && empty($episode['is_scheduled']) && !empty($show['is_public'])): ?>
      <a href="<?= h((string)$episode['public_path']) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
    <?php endif; ?>
    <?php if ($id !== null): ?>
      <a href="<?= h(BASE_URL . '/admin/revisions.php?type=podcast_episode&id=' . (int)$episode['id']) ?>" style="margin-left:1rem">Historie změn</a>
    <?php endif; ?>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
(function () {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    let slugManual = <?= $id !== null && !empty($episode['slug']) ? 'true' : 'false' ?>;

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
