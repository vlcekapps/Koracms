<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu podcastových platforem nemáte potřebné oprávnění.');
requireModuleEnabled('podcast');

$pdo = db_connect();
$showId = inputInt($_SERVER['REQUEST_METHOD'] === 'POST' ? 'post' : 'get', 'show_id');
if ($showId === null || $showId < 1) {
    header('Location: ' . BASE_URL . '/admin/podcast_shows.php');
    exit;
}
$showStmt = $pdo->prepare("SELECT * FROM cms_podcast_shows WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$showStmt->execute([$showId]);
$show = $showStmt->fetch() ?: null;
if ($show === null) {
    header('Location: ' . BASE_URL . '/admin/podcast_shows.php');
    exit;
}

$baseUrl = BASE_URL . '/admin/podcast_platforms.php?show_id=' . $showId;
$linkId = inputInt('post', 'link_id');
$error = '';
$errorField = '';
$deleteErrorId = inputInt('get', 'delete_id');
$deleteError = trim((string)($_GET['error'] ?? '')) === 'delete_confirm_required' && $deleteErrorId !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? 'save'));
    if ($action === 'delete') {
        if ($linkId !== null) {
            $deleteLinkStmt = $pdo->prepare(
                "SELECT id FROM cms_podcast_platform_links WHERE id = ? AND show_id = ? LIMIT 1"
            );
            $deleteLinkStmt->execute([$linkId, $showId]);
            if ($deleteLinkStmt->fetchColumn()) {
                $confirmationField = 'confirm_podcast_platform_delete_' . $linkId;
                $confirmed = isset($_POST[$confirmationField]) && (string)$_POST[$confirmationField] === '1';
                if (!$confirmed) {
                    header('Location: ' . appendUrlQuery($baseUrl, [
                        'error' => 'delete_confirm_required',
                        'delete_id' => $linkId,
                    ]));
                    exit;
                }

                $pdo->prepare("DELETE FROM cms_podcast_platform_links WHERE id = ? AND show_id = ?")
                    ->execute([$linkId, $showId]);
                logAction('podcast_platform_delete', "id={$linkId} show_id={$showId}");
            }
        }
        header('Location: ' . appendUrlQuery($baseUrl, ['ok' => 'deleted']));
        exit;
    }

    $platformKey = normalizePodcastPlatformKey((string)($_POST['platform_key'] ?? 'other'));
    $label = mb_substr(trim((string)($_POST['label'] ?? '')), 0, 100);
    $urlInput = trim((string)($_POST['url'] ?? ''));
    $url = normalizePodcastPlatformUrl($urlInput);
    $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
    if ($platformKey === 'other' && $label === '') {
        $error = 'Doplňte viditelný název jiné platformy, například Obecní audioarchiv.';
        $errorField = 'label';
    } elseif ($url === '') {
        $error = 'Zadejte veřejnou adresu pořadu na platformě jako http/https nebo doménu bez schématu.';
        $errorField = 'url';
    } else {
        try {
            if ($linkId !== null) {
                $ownershipStmt = $pdo->prepare("SELECT id FROM cms_podcast_platform_links WHERE id = ? AND show_id = ? LIMIT 1");
                $ownershipStmt->execute([$linkId, $showId]);
                if (!$ownershipStmt->fetchColumn()) {
                    $error = 'Odkaz se v tomto podcastu nepodařilo najít. Vraťte se k seznamu a vyberte existující platformu.';
                } else {
                    $pdo->prepare(
                        "UPDATE cms_podcast_platform_links
                         SET platform_key = ?, label = ?, url = ?, sort_order = ?, updated_at = NOW()
                         WHERE id = ? AND show_id = ?"
                    )->execute([$platformKey, $label, $url, $sortOrder, $linkId, $showId]);
                    logAction('podcast_platform_edit', "id={$linkId} show_id={$showId}");
                    header('Location: ' . $baseUrl . '&ok=saved');
                    exit;
                }
            } else {
                $pdo->prepare(
                    "INSERT INTO cms_podcast_platform_links (show_id, platform_key, label, url, sort_order)
                     VALUES (?,?,?,?,?)"
                )->execute([$showId, $platformKey, $label, $url, $sortOrder]);
                logAction('podcast_platform_add', 'id=' . (int)$pdo->lastInsertId() . " show_id={$showId}");
                header('Location: ' . $baseUrl . '&ok=saved');
                exit;
            }
        } catch (PDOException $exception) {
            if ((string)$exception->getCode() === '23000') {
                $error = 'Tato platforma už má u podcastu vlastní odkaz. Upravte existující položku.';
                $errorField = 'platform_key';
            } else {
                throw $exception;
            }
        }
    }
}

$editId = inputInt('get', 'edit');
$link = ['id' => null, 'platform_key' => 'spotify', 'label' => '', 'url' => '', 'sort_order' => 0];
if ($editId !== null) {
    $editStmt = $pdo->prepare("SELECT * FROM cms_podcast_platform_links WHERE id = ? AND show_id = ? LIMIT 1");
    $editStmt->execute([$editId, $showId]);
    $existingLink = $editStmt->fetch() ?: null;
    if ($existingLink !== null) {
        $link = array_merge($link, $existingLink);
    }
}
if ($error !== '') {
    $link = array_merge($link, [
        'id' => $linkId,
        'platform_key' => (string)($_POST['platform_key'] ?? 'other'),
        'label' => (string)($_POST['label'] ?? ''),
        'url' => (string)($_POST['url'] ?? ''),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
    ]);
}

$linksStmt = $pdo->prepare("SELECT * FROM cms_podcast_platform_links WHERE show_id = ? ORDER BY sort_order ASC, id ASC");
$linksStmt->execute([$showId]);
$links = $linksStmt->fetchAll();

adminHeader('Platformy podcastu: ' . (string)$show['title']);
?>
<p><a href="podcast_show_form.php?id=<?= (int)$showId ?>"><span aria-hidden="true">&larr;</span> Zpět na podcast</a></p>
<?php if ($deleteError): ?>
  <div class="error" role="alert" aria-atomic="true" aria-labelledby="podcast-platform-delete-error">
    <p id="podcast-platform-delete-error">Odkaz na platformu nelze odebrat bez potvrzení kontroly platformy, adresy a dopadu na veřejné možnosti poslechu.</p>
  </div>
<?php elseif ($error !== ''): ?>
  <p class="error" role="alert" id="form-error" aria-atomic="true"><?= h($error) ?></p>
<?php elseif (isset($_GET['ok'])): ?>
  <p class="success" role="status" aria-atomic="true">Odkazy na platformy byly aktualizovány.</p>
<?php endif; ?>

<section aria-labelledby="podcast-platform-form-heading">
  <h2 id="podcast-platform-form-heading"><?= !empty($link['id']) ? 'Upravit platformu' : 'Přidat platformu' ?></h2>
  <form id="podcast-platform-form" method="post" novalidate<?= $error !== '' ? ' aria-describedby="form-error"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="show_id" value="<?= (int)$showId ?>">
    <?php if (!empty($link['id'])): ?><input type="hidden" name="link_id" value="<?= (int)$link['id'] ?>"><?php endif; ?>
    <fieldset>
      <legend>Údaje platformy</legend>
      <label for="platform_key">Platforma</label>
      <select id="platform_key" name="platform_key"<?= $errorField === 'platform_key' ? ' aria-invalid="true" aria-describedby="platform-error"' : '' ?>>
        <?php foreach (podcastPlatformOptions() as $platformKey => $platformLabel): ?>
          <option value="<?= h($platformKey) ?>"<?= normalizePodcastPlatformKey((string)$link['platform_key']) === $platformKey ? ' selected' : '' ?>><?= h($platformLabel) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($errorField === 'platform_key'): ?><small id="platform-error" class="error"><?= h($error) ?></small><?php endif; ?>

      <label for="label">Vlastní název</label>
      <input type="text" id="label" name="label" maxlength="100" value="<?= h((string)$link['label']) ?>"<?= $errorField === 'label' ? ' aria-invalid="true" aria-describedby="label-help label-error"' : ' aria-describedby="label-help"' ?>>
      <small id="label-help" class="field-help">Povinný jen pro volbu Jiná platforma; jinak může přepsat výchozí název.</small>
      <?php if ($errorField === 'label'): ?><small id="label-error" class="error"><?= h($error) ?></small><?php endif; ?>

      <label for="url">Odkaz na pořad <span aria-hidden="true">*</span></label>
      <input type="url" id="url" name="url" maxlength="500" required aria-required="true" value="<?= h((string)$link['url']) ?>"<?= $errorField === 'url' ? ' aria-invalid="true" aria-describedby="url-error"' : '' ?>>
      <?php if ($errorField === 'url'): ?><small id="url-error" class="error"><?= h($error) ?></small><?php endif; ?>

      <label for="sort_order">Pořadí</label>
      <input type="number" id="sort_order" name="sort_order" min="0" value="<?= (int)$link['sort_order'] ?>">
    </fieldset>
    <div class="button-row">
      <button type="submit" class="btn"><?= !empty($link['id']) ? 'Uložit platformu' : 'Přidat platformu' ?></button>
      <?php if (!empty($link['id'])): ?><a href="<?= h($baseUrl) ?>">Zrušit úpravu</a><?php endif; ?>
    </div>
  </form>
</section>

<section aria-labelledby="podcast-platform-list-heading">
  <h2 id="podcast-platform-list-heading">Odkazy na platformy</h2>
  <?php if ($links === []): ?>
    <p>Zatím nejsou přidané žádné platformy.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <caption>Platformy podcastu <?= h((string)$show['title']) ?></caption>
        <thead><tr><th scope="col">Platforma</th><th scope="col">Odkaz</th><th scope="col">Akce</th></tr></thead>
        <tbody>
        <?php foreach ($links as $listedLink):
            $listedLinkId = (int)$listedLink['id'];
            $platformDeleteConfirmField = 'confirm_podcast_platform_delete_' . $listedLinkId;
            $platformDeleteReviewId = 'podcast-platform-delete-review-' . $listedLinkId;
            $platformDeleteFieldErrorId = 'confirm-podcast-platform-delete-' . $listedLinkId . '-error';
            $platformDeleteHasError = $deleteError && $deleteErrorId === $listedLinkId;
            $platformDeleteErrors = $platformDeleteHasError ? [$platformDeleteConfirmField] : [];
            ?>
          <tr>
            <td><?= h(podcastPlatformLabel($listedLink)) ?></td>
            <td><a href="<?= h((string)$listedLink['url']) ?>" target="_blank" rel="noopener noreferrer">Otevřít<?= newWindowLinkSrOnlySuffix() ?></a></td>
            <td class="actions">
              <a href="<?= h($baseUrl . '&edit=' . $listedLinkId) ?>" class="btn">Upravit</a>
              <form id="podcast-platform-delete-form-<?= $listedLinkId ?>" method="post" novalidate<?= $platformDeleteHasError ? ' aria-describedby="podcast-platform-delete-error"' : '' ?>>
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="show_id" value="<?= (int)$showId ?>">
                <input type="hidden" name="link_id" value="<?= $listedLinkId ?>">
                <input type="hidden" name="action" value="delete">
                <fieldset class="admin-inline-fieldset">
                  <legend>Odebrání platformy <?= h(podcastPlatformLabel($listedLink)) ?></legend>
                  <p id="<?= h($platformDeleteReviewId) ?>" class="field-help field-help--flush">
                    Odebrání trvale odstraní veřejný odkaz „<?= h(podcastPlatformLabel($listedLink)) ?>“ z detailu podcastu. Účet ani obsah na externí platformě se nezmění.
                  </p>
                  <label for="confirm-podcast-platform-delete-<?= $listedLinkId ?>" class="admin-checkbox-label">
                    <input type="checkbox" id="confirm-podcast-platform-delete-<?= $listedLinkId ?>" name="<?= h($platformDeleteConfirmField) ?>" value="1" required aria-required="true"<?= adminFieldAttributes($platformDeleteConfirmField, $platformDeleteErrors, [], [$platformDeleteReviewId], $platformDeleteFieldErrorId) ?>>
                    Potvrzuji odebrání tohoto odkazu.
                  </label>
                  <?php adminRenderFieldError($platformDeleteConfirmField, $platformDeleteErrors, [], 'Před odebráním potvrďte kontrolu platformy a jejího veřejného dopadu.', $platformDeleteFieldErrorId); ?>
                  <button type="submit" class="btn btn-danger" data-confirm="Odebrat platformu?">Odebrat</button>
                </fieldset>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php adminFooter(); ?>
