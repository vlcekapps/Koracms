<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo    = db_connect();
$id     = inputInt('get', 'id');
$showId = inputInt('get', 'show_id');
$ep     = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_podcasts WHERE id = ?");
    $stmt->execute([$id]);
    $ep = $stmt->fetch();
    if (!$ep) { header('Location: podcast_shows.php'); exit; }
    if ($showId === null) $showId = (int)$ep['show_id'];
}

// Potřebujeme znát show
if ($showId === null) { header('Location: podcast_shows.php'); exit; }
$showStmt = $pdo->prepare("SELECT id, title FROM cms_podcast_shows WHERE id = ?");
$showStmt->execute([$showId]);
$show = $showStmt->fetch();
if (!$show) { header('Location: podcast_shows.php'); exit; }

$useWysiwyg   = getSetting('content_editor', 'html') === 'wysiwyg';
$publishInput = !empty($ep['publish_at']) ? date('Y-m-d\TH:i', strtotime($ep['publish_at'])) : '';

adminHeader($id ? 'Upravit epizodu' : 'Nová epizoda');
?>

<p><a href="podcast.php?show_id=<?= (int)$showId ?>"><span aria-hidden="true">←</span> <?= h($show['title']) ?></a></p>

<form method="post" action="podcast_save.php" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="show_id" value="<?= (int)$showId ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Epizoda</legend>

    <label for="title">Název epizody <span aria-hidden="true">*</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           value="<?= h($ep['title'] ?? '') ?>">

    <label for="episode_num">Číslo epizody <small>(nepovinné)</small></label>
  <input type="number" id="episode_num" name="episode_num" min="1" style="width:8rem"
         value="<?= $ep['episode_num'] ? (int)$ep['episode_num'] : '' ?>">

  <label for="duration">Délka <small>(např. 42:30)</small></label>
  <input type="text" id="duration" name="duration" maxlength="20" style="width:8rem"
         value="<?= h($ep['duration'] ?? '') ?>">

  <label for="audio_file">
    Audio soubor (MP3/OGG)
    <?php if (!empty($ep['audio_file'])): ?>
      <small>(aktuální: <?= h($ep['audio_file']) ?>)</small>
    <?php endif; ?>
  </label>
  <input type="file" id="audio_file" name="audio_file" accept="audio/mpeg,audio/ogg,audio/wav">

  <label for="audio_url">nebo externí URL <small>(YouTube, Soundcloud, přímý odkaz…)</small></label>
  <input type="url" id="audio_url" name="audio_url" maxlength="500"
         value="<?= h($ep['audio_url'] ?? '') ?>">

  <label for="description">Popis epizody</label>
  <textarea id="description" name="description" rows="8"><?= h($ep['description'] ?? '') ?></textarea>

  <label for="publish_at">Plánované zveřejnění <small>(prázdné = ihned)</small></label>
  <input type="datetime-local" id="publish_at" name="publish_at" style="width:auto"
         value="<?= h($publishInput) ?>">

    <div style="margin-top:1.5rem">
      <button type="submit" class="btn"><?= $id ? 'Uložit' : 'Přidat epizodu' ?></button>
      <a href="podcast.php?show_id=<?= (int)$showId ?>" style="margin-left:1rem">Zrušit</a>
    </div>
  </fieldset>
</form>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
(function () {
    const ta = document.getElementById('description');
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'background:#fff;border:1px solid #ccc;margin-top:.2rem;min-height:150px';
    ta.parentNode.insertBefore(wrapper, ta);
    ta.style.display = 'none';
    const quill = new Quill(wrapper, { theme: 'snow' });
    quill.root.innerHTML = ta.value;
    ta.closest('form').addEventListener('submit', () => { ta.value = quill.root.innerHTML; });
})();
</script>
<?php endif; ?>

<?php adminFooter(); ?>
