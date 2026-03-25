<?php
require_once __DIR__ . '/layout.php';
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
    'audio_url' => '',
    'duration' => '',
    'episode_num' => null,
    'publish_at' => null,
    'created_at' => null,
    'status' => 'published',
];

if ($id !== null) {
    $stmt = $pdo->prepare(
        "SELECT p.*, s.slug AS show_slug, s.title AS show_title
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
$episode['show_id'] = (int)$show['id'];
$episode['show_slug'] = (string)$show['slug'];
$episode['show_title'] = (string)$show['title'];
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
    default => '',
};

adminHeader($id !== null ? 'Upravit epizodu podcastu' : 'Nová epizoda podcastu');
?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error"><?= h($formError) ?></p>
<?php endif; ?>

<p><a href="podcast.php?show_id=<?= (int)$showId ?>"><span aria-hidden="true">←</span> Zpět na epizody podcastu</a></p>

<p style="margin-top:0;font-size:.9rem">
  Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<form method="post" action="podcast_save.php" enctype="multipart/form-data" novalidate<?= $formError !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="show_id" value="<?= (int)$showId ?>">
  <?php if ($id !== null): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Epizoda</legend>

    <label for="title">Název epizody <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           value="<?= h((string)$episode['title']) ?>">

    <label for="slug">Slug veřejné stránky <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255" pattern="[a-z0-9\-]+"
           aria-describedby="podcast-episode-slug-help"
           value="<?= h((string)$episode['slug']) ?>">
    <small id="podcast-episode-slug-help" class="field-help">Slug musí být unikátní v rámci pořadu.</small>

    <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:1 1 12rem">
        <label for="episode_num">Číslo epizody</label>
        <input type="number" id="episode_num" name="episode_num" min="1" style="width:100%"
               value="<?= !empty($episode['episode_num']) ? (int)$episode['episode_num'] : '' ?>">
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
    <small id="podcast-episode-publish-help" class="field-help">Prázdné datum znamená zveřejnění ihned po schválení nebo uložení.</small>
  </fieldset>

  <fieldset>
    <legend>Audio a popis</legend>

    <label for="audio_file">Audio soubor</label>
    <input type="file" id="audio_file" name="audio_file" accept=".mp3,.ogg,.wav,.m4a,.aac,audio/*"
           aria-describedby="podcast-episode-audio-help<?= (string)$episode['audio_file'] !== '' ? ' podcast-episode-audio-current' : '' ?>">
    <small id="podcast-episode-audio-help" class="field-help">Povolené formáty: MP3, OGG, WAV, M4A a AAC.</small>
    <?php if ((string)$episode['audio_file'] !== ''): ?>
      <small id="podcast-episode-audio-current" class="field-help">Aktuální soubor je už nahraný.</small>
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
    <small id="podcast-episode-audio-url-help" class="field-help">Hodí se pro externí hosting nebo embedovatelný přímý audio soubor.</small>

    <label for="description">Popis epizody</label>
    <?php if ($useWysiwyg): ?>
      <div id="description_editor" class="quill-editor" style="min-height:16rem"></div>
      <textarea id="description" name="description" rows="10" class="visually-hidden" aria-describedby="podcast-episode-description-help"><?= h((string)$episode['description']) ?></textarea>
      <small id="podcast-episode-description-help" class="field-help">HTML textarea je přístupnější varianta; WYSIWYG je jen volitelný vizuální režim.</small>
    <?php else: ?>
      <textarea id="description" name="description" rows="10" aria-describedby="podcast-episode-description-help"><?= h((string)$episode['description']) ?></textarea>
      <small id="podcast-episode-description-help" class="field-help">Podporuje HTML i Markdown syntaxi.</small>
    <?php endif; ?>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id !== null ? 'Uložit změny' : 'Přidat epizodu' ?></button>
    <?php if ($id !== null && (string)$episode['status'] === 'published' && empty($episode['is_scheduled'])): ?>
      <a href="<?= h((string)$episode['public_path']) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
    <?php endif; ?>
    <a href="podcast.php?show_id=<?= (int)$showId ?>" style="margin-left:1rem">Zrušit</a>
  </div>
</form>


<script>
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
