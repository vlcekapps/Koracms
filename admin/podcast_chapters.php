<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu kapitol podcastu nemáte potřebné oprávnění.');
requireModuleEnabled('podcast');

$pdo = db_connect();
$episodeId = inputInt($_SERVER['REQUEST_METHOD'] === 'POST' ? 'post' : 'get', 'episode_id');
if ($episodeId === null || $episodeId < 1) {
    header('Location: ' . BASE_URL . '/admin/podcast_shows.php');
    exit;
}

$episodeStmt = $pdo->prepare(
    "SELECT p.*, s.title AS show_title, s.slug AS show_slug
     FROM cms_podcasts p
     INNER JOIN cms_podcast_shows s ON s.id = p.show_id
     WHERE p.id = ? AND p.deleted_at IS NULL AND s.deleted_at IS NULL LIMIT 1"
);
$episodeStmt->execute([$episodeId]);
$episode = $episodeStmt->fetch() ?: null;
if ($episode === null) {
    header('Location: ' . BASE_URL . '/admin/podcast_shows.php');
    exit;
}

$error = '';
$errorField = '';
$editChapterId = inputInt('post', 'chapter_id');
$postedStartTime = trim((string)($_POST['start_time'] ?? ''));
$postedTitle = trim((string)($_POST['title'] ?? ''));
$postedUrl = trim((string)($_POST['url'] ?? ''));
$postedImageUrl = trim((string)($_POST['image_url'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? 'save'));
    if ($action === 'delete') {
        if ($editChapterId !== null) {
            $pdo->prepare("DELETE FROM cms_podcast_chapters WHERE id = ? AND episode_id = ?")
                ->execute([$editChapterId, $episodeId]);
            logAction('podcast_chapter_delete', "id={$editChapterId} episode_id={$episodeId}");
        }
        header('Location: ' . BASE_URL . '/admin/podcast_chapters.php?episode_id=' . $episodeId . '&ok=deleted');
        exit;
    }

    $startSeconds = podcastChapterStartSeconds($postedStartTime);
    $url = normalizePodcastChapterUrl($postedUrl);
    $imageUrl = normalizePodcastChapterUrl($postedImageUrl);
    if ($startSeconds === null) {
        $error = 'Zadejte začátek kapitoly jako sekundy nebo čas ve tvaru MM:SS či H:MM:SS.';
        $errorField = 'start_time';
    } elseif ($postedTitle === '') {
        $error = 'Doplňte krátký název kapitoly, například Rozhovor s hostem.';
        $errorField = 'title';
    } elseif ($postedUrl !== '' && $url === '') {
        $error = 'Zadejte veřejnou adresu souvisejícího odkazu jako http/https nebo doménu bez schématu, případně pole nechte prázdné.';
        $errorField = 'url';
    } elseif ($postedImageUrl !== '' && $imageUrl === '') {
        $error = 'Zadejte veřejnou adresu obrázku jako http/https nebo doménu bez schématu, případně pole nechte prázdné.';
        $errorField = 'image_url';
    } else {
        try {
            if ($editChapterId !== null) {
                $pdo->prepare(
                    "UPDATE cms_podcast_chapters
                     SET start_time_seconds = ?, title = ?, url = ?, image_url = ?, updated_at = NOW()
                     WHERE id = ? AND episode_id = ?"
                )->execute([$startSeconds, $postedTitle, $url, $imageUrl, $editChapterId, $episodeId]);
                logAction('podcast_chapter_edit', "id={$editChapterId} episode_id={$episodeId}");
            } else {
                $pdo->prepare(
                    "INSERT INTO cms_podcast_chapters (episode_id, start_time_seconds, title, url, image_url)
                     VALUES (?,?,?,?,?)"
                )->execute([$episodeId, $startSeconds, $postedTitle, $url, $imageUrl]);
                logAction('podcast_chapter_add', 'id=' . (int)$pdo->lastInsertId() . " episode_id={$episodeId}");
            }
            header('Location: ' . BASE_URL . '/admin/podcast_chapters.php?episode_id=' . $episodeId . '&ok=saved');
            exit;
        } catch (PDOException $exception) {
            if ((string)$exception->getCode() === '23000') {
                $error = 'V této epizodě už kapitola se stejným začátkem existuje. Zadejte jiný čas nebo upravte existující kapitolu.';
                $errorField = 'start_time';
            } else {
                throw $exception;
            }
        }
    }
}

$chaptersStmt = $pdo->prepare(
    "SELECT * FROM cms_podcast_chapters WHERE episode_id = ? ORDER BY start_time_seconds ASC, id ASC"
);
$chaptersStmt->execute([$episodeId]);
$chapters = $chaptersStmt->fetchAll();
$episodeEditUrl = BASE_URL . '/admin/podcast_form.php?id=' . $episodeId . '&show_id=' . (int)$episode['show_id'];

adminHeader('Kapitoly epizody: ' . (string)$episode['title']);
?>
<p><a href="<?= h($episodeEditUrl) ?>"><span aria-hidden="true">&larr;</span> Zpět na epizodu</a></p>

<?php if ($error !== ''): ?>
  <p class="error" role="alert" id="form-error" aria-atomic="true"><?= h($error) ?></p>
<?php elseif (isset($_GET['ok'])): ?>
  <p class="success" role="status">Kapitoly byly aktualizovány.</p>
<?php endif; ?>

<section aria-labelledby="podcast-chapter-add-heading">
  <h2 id="podcast-chapter-add-heading">Přidat kapitolu</h2>
  <form id="podcast-chapter-create-form" method="post" novalidate<?= $error !== '' && $editChapterId === null ? ' aria-describedby="form-error"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="episode_id" value="<?= (int)$episodeId ?>">
    <fieldset>
      <legend>Údaje nové kapitoly</legend>
      <label for="start_time">Začátek kapitoly <span aria-hidden="true">*</span></label>
      <input type="text" id="start_time" name="start_time" required aria-required="true"
             placeholder="0:00" value="<?= $editChapterId === null ? h($postedStartTime) : '' ?>"
             aria-describedby="start-time-help<?= $errorField === 'start_time' && $editChapterId === null ? ' start-time-error' : '' ?>"<?= $errorField === 'start_time' && $editChapterId === null ? ' aria-invalid="true"' : '' ?>>
      <small id="start-time-help" class="field-help">Sekundy, MM:SS nebo H:MM:SS. První kapitola obvykle začíná v čase 0:00.</small>
      <?php if ($errorField === 'start_time' && $editChapterId === null): ?><small id="start-time-error" class="error"><?= h($error) ?></small><?php endif; ?>

      <label for="title">Název kapitoly <span aria-hidden="true">*</span></label>
      <input type="text" id="title" name="title" maxlength="255" required aria-required="true"
             value="<?= $editChapterId === null ? h($postedTitle) : '' ?>"<?= $errorField === 'title' && $editChapterId === null ? ' aria-invalid="true" aria-describedby="title-error"' : '' ?>>
      <?php if ($errorField === 'title' && $editChapterId === null): ?><small id="title-error" class="error"><?= h($error) ?></small><?php endif; ?>

      <label for="url">Související odkaz</label>
      <input type="url" id="url" name="url" maxlength="500" value="<?= $editChapterId === null ? h($postedUrl) : '' ?>"<?= $errorField === 'url' && $editChapterId === null ? ' aria-invalid="true" aria-describedby="url-error"' : '' ?>>
      <?php if ($errorField === 'url' && $editChapterId === null): ?><small id="url-error" class="error"><?= h($error) ?></small><?php endif; ?>

      <label for="image_url">Odkaz na obrázek kapitoly</label>
      <input type="url" id="image_url" name="image_url" maxlength="500" value="<?= $editChapterId === null ? h($postedImageUrl) : '' ?>"<?= $errorField === 'image_url' && $editChapterId === null ? ' aria-invalid="true" aria-describedby="image-url-error"' : '' ?>>
      <?php if ($errorField === 'image_url' && $editChapterId === null): ?><small id="image-url-error" class="error"><?= h($error) ?></small><?php endif; ?>
    </fieldset>
    <button type="submit" class="btn">Přidat kapitolu</button>
  </form>
</section>

<section aria-labelledby="podcast-chapter-list-heading">
  <h2 id="podcast-chapter-list-heading">Existující kapitoly</h2>
  <?php if ($chapters === []): ?>
    <p>Zatím nejsou vytvořené žádné kapitoly.</p>
  <?php else: ?>
    <?php foreach ($chapters as $chapter):
        $chapterId = (int)$chapter['id'];
        $isEditedChapterError = $error !== '' && $editChapterId === $chapterId;
        ?>
      <form id="podcast-chapter-form-<?= $chapterId ?>" method="post" class="admin-fieldset-spaced" novalidate<?= $error !== '' && $editChapterId === $chapterId ? ' aria-describedby="form-error"' : '' ?>>
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="episode_id" value="<?= (int)$episodeId ?>">
        <input type="hidden" name="chapter_id" value="<?= $chapterId ?>">
        <fieldset>
          <legend><?= h(podcastChapterTimeLabel($chapter['start_time_seconds'])) ?> – <?= h((string)$chapter['title']) ?></legend>
          <label for="start_time_<?= $chapterId ?>">Začátek kapitoly</label>
          <input type="text" id="start_time_<?= $chapterId ?>" name="start_time" required
                 value="<?= h($editChapterId === $chapterId ? $postedStartTime : podcastChapterTimeLabel($chapter['start_time_seconds'])) ?>" aria-describedby="start-time-help<?= $isEditedChapterError && $errorField === 'start_time' ? ' start-time-error-' . $chapterId : '' ?>"<?= $isEditedChapterError && $errorField === 'start_time' ? ' aria-invalid="true"' : '' ?>>
          <?php if ($isEditedChapterError && $errorField === 'start_time'): ?><small id="start-time-error-<?= $chapterId ?>" class="error"><?= h($error) ?></small><?php endif; ?>
          <label for="title_<?= $chapterId ?>">Název kapitoly</label>
          <input type="text" id="title_<?= $chapterId ?>" name="title" maxlength="255" required value="<?= h($editChapterId === $chapterId ? $postedTitle : (string)$chapter['title']) ?>"<?= $isEditedChapterError && $errorField === 'title' ? ' aria-invalid="true" aria-describedby="title-error-' . $chapterId . '"' : '' ?>>
          <?php if ($isEditedChapterError && $errorField === 'title'): ?><small id="title-error-<?= $chapterId ?>" class="error"><?= h($error) ?></small><?php endif; ?>
          <label for="url_<?= $chapterId ?>">Související odkaz</label>
          <input type="url" id="url_<?= $chapterId ?>" name="url" maxlength="500" value="<?= h($editChapterId === $chapterId ? $postedUrl : (string)$chapter['url']) ?>"<?= $isEditedChapterError && $errorField === 'url' ? ' aria-invalid="true" aria-describedby="url-error-' . $chapterId . '"' : '' ?>>
          <?php if ($isEditedChapterError && $errorField === 'url'): ?><small id="url-error-<?= $chapterId ?>" class="error"><?= h($error) ?></small><?php endif; ?>
          <label for="image_url_<?= $chapterId ?>">Odkaz na obrázek kapitoly</label>
          <input type="url" id="image_url_<?= $chapterId ?>" name="image_url" maxlength="500" value="<?= h($editChapterId === $chapterId ? $postedImageUrl : (string)$chapter['image_url']) ?>"<?= $isEditedChapterError && $errorField === 'image_url' ? ' aria-invalid="true" aria-describedby="image-url-error-' . $chapterId . '"' : '' ?>>
          <?php if ($isEditedChapterError && $errorField === 'image_url'): ?><small id="image-url-error-<?= $chapterId ?>" class="error"><?= h($error) ?></small><?php endif; ?>
        </fieldset>
        <div class="button-row">
          <button type="submit" name="action" value="save" class="btn">Uložit kapitolu</button>
          <button type="submit" name="action" value="delete" class="btn btn-danger" data-confirm="Smazat kapitolu?">Smazat kapitolu</button>
        </div>
      </form>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
<?php adminFooter(); ?>
