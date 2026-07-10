<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu osob podcastu nemáte potřebné oprávnění.');
requireModuleEnabled('podcast');

$pdo = db_connect();
$requestSource = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'post' : 'get';
$showId = inputInt($requestSource, 'show_id');
$episodeId = inputInt($requestSource, 'episode_id');
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

$episode = null;
if ($episodeId !== null) {
    $episodeStmt = $pdo->prepare("SELECT * FROM cms_podcasts WHERE id = ? AND show_id = ? AND deleted_at IS NULL LIMIT 1");
    $episodeStmt->execute([$episodeId, $showId]);
    $episode = $episodeStmt->fetch() ?: null;
    if ($episode === null) {
        header('Location: ' . BASE_URL . '/admin/podcast.php?show_id=' . $showId);
        exit;
    }
}

$scopeWhere = $episodeId === null ? 'show_id = ? AND episode_id IS NULL' : 'show_id = ? AND episode_id = ?';
$scopeParams = $episodeId === null ? [$showId] : [$showId, $episodeId];
$baseQuery = ['show_id' => $showId];
if ($episodeId !== null) {
    $baseQuery['episode_id'] = $episodeId;
}
$baseUrl = BASE_URL . '/admin/podcast_people.php?' . http_build_query($baseQuery);
$personId = inputInt('post', 'person_id');
$error = '';
$errorField = '';
$deleteErrorId = inputInt('get', 'delete_id');
$deleteError = trim((string)($_GET['error'] ?? '')) === 'delete_confirm_required' && $deleteErrorId !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? 'save'));
    if ($action === 'delete') {
        if ($personId !== null) {
            $deletePersonStmt = $pdo->prepare("SELECT id FROM cms_podcast_people WHERE id = ? AND {$scopeWhere} LIMIT 1");
            $deletePersonStmt->execute(array_merge([$personId], $scopeParams));
            if ($deletePersonStmt->fetchColumn()) {
                $confirmationField = 'confirm_podcast_person_delete_' . $personId;
                $confirmed = isset($_POST[$confirmationField]) && (string)$_POST[$confirmationField] === '1';
                if (!$confirmed) {
                    header('Location: ' . appendUrlQuery($baseUrl, [
                        'error' => 'delete_confirm_required',
                        'delete_id' => $personId,
                    ]));
                    exit;
                }

                $deleteSql = "DELETE FROM cms_podcast_people WHERE id = ? AND {$scopeWhere}";
                $pdo->prepare($deleteSql)->execute(array_merge([$personId], $scopeParams));
                logAction('podcast_person_delete', "id={$personId} show_id={$showId}");
            }
        }
        header('Location: ' . appendUrlQuery($baseUrl, ['ok' => 'deleted']));
        exit;
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $role = normalizePodcastPersonRole((string)($_POST['role_key'] ?? 'guest'));
    $group = normalizePodcastPersonGroup((string)($_POST['group_key'] ?? 'cast'));
    $profileUrlInput = trim((string)($_POST['profile_url'] ?? ''));
    $imageUrlInput = trim((string)($_POST['image_url'] ?? ''));
    $profileUrl = normalizePodcastPersonUrl($profileUrlInput);
    $imageUrl = normalizePodcastPersonUrl($imageUrlInput);
    $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));

    if ($name === '') {
        $error = 'Doplňte jméno osoby tak, jak se má zobrazit na webu a v podcastových aplikacích, například Jana Nováková.';
        $errorField = 'name';
    } elseif ($profileUrlInput !== '' && $profileUrl === '') {
        $error = 'Zadejte veřejnou adresu profilu jako http/https nebo doménu bez schématu, případně pole nechte prázdné.';
        $errorField = 'profile_url';
    } elseif ($imageUrlInput !== '' && $imageUrl === '') {
        $error = 'Zadejte veřejnou adresu obrázku jako http/https nebo doménu bez schématu, případně pole nechte prázdné.';
        $errorField = 'image_url';
    } elseif ($personId !== null) {
        $ownershipStmt = $pdo->prepare("SELECT id FROM cms_podcast_people WHERE id = ? AND {$scopeWhere} LIMIT 1");
        $ownershipStmt->execute(array_merge([$personId], $scopeParams));
        if (!$ownershipStmt->fetchColumn()) {
            $error = 'Osobu se nepodařilo v tomto pořadu nebo epizodě najít. Vraťte se k seznamu a vyberte existující osobu.';
        } else {
            $updateSql = "UPDATE cms_podcast_people
                          SET name = ?, role_key = ?, group_key = ?, profile_url = ?, image_url = ?, sort_order = ?, updated_at = NOW()
                          WHERE id = ? AND {$scopeWhere}";
            $pdo->prepare($updateSql)->execute(array_merge([$name, $role, $group, $profileUrl, $imageUrl, $sortOrder, $personId], $scopeParams));
            logAction('podcast_person_edit', "id={$personId} show_id={$showId}");
            header('Location: ' . $baseUrl . '&ok=saved');
            exit;
        }
    } else {
        $pdo->prepare(
            "INSERT INTO cms_podcast_people
             (show_id, episode_id, name, role_key, group_key, profile_url, image_url, sort_order)
             VALUES (?,?,?,?,?,?,?,?)"
        )->execute([$showId, $episodeId, $name, $role, $group, $profileUrl, $imageUrl, $sortOrder]);
        logAction('podcast_person_add', 'id=' . (int)$pdo->lastInsertId() . " show_id={$showId}");
        header('Location: ' . $baseUrl . '&ok=saved');
        exit;
    }
}

$editId = inputInt('get', 'edit');
$person = [
    'id' => null,
    'name' => '',
    'role_key' => $episodeId === null ? 'host' : 'guest',
    'group_key' => 'cast',
    'profile_url' => '',
    'image_url' => '',
    'sort_order' => 0,
];
if ($editId !== null) {
    $editStmt = $pdo->prepare("SELECT * FROM cms_podcast_people WHERE id = ? AND {$scopeWhere} LIMIT 1");
    $editStmt->execute(array_merge([$editId], $scopeParams));
    $existingPerson = $editStmt->fetch() ?: null;
    if ($existingPerson !== null) {
        $person = array_merge($person, $existingPerson);
    }
}
if ($error !== '') {
    $person = array_merge($person, [
        'id' => $personId,
        'name' => (string)($_POST['name'] ?? ''),
        'role_key' => (string)($_POST['role_key'] ?? 'guest'),
        'group_key' => (string)($_POST['group_key'] ?? 'cast'),
        'profile_url' => (string)($_POST['profile_url'] ?? ''),
        'image_url' => (string)($_POST['image_url'] ?? ''),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
    ]);
}

$peopleStmt = $pdo->prepare("SELECT * FROM cms_podcast_people WHERE {$scopeWhere} ORDER BY sort_order ASC, name ASC, id ASC");
$peopleStmt->execute($scopeParams);
$people = $peopleStmt->fetchAll();
$heading = $episodeId === null
    ? 'Tvůrci podcastu: ' . (string)$show['title']
    : 'Hosté a tvůrci epizody: ' . (string)$episode['title'];
$backUrl = $episodeId === null
    ? BASE_URL . '/admin/podcast_show_form.php?id=' . $showId
    : BASE_URL . '/admin/podcast_form.php?id=' . $episodeId . '&show_id=' . $showId;

adminHeader($heading);
?>
<p><a href="<?= h($backUrl) ?>"><span aria-hidden="true">&larr;</span> Zpět na <?= $episodeId === null ? 'podcast' : 'epizodu' ?></a></p>

<?php if ($deleteError): ?>
  <div class="error" role="alert" aria-atomic="true" aria-labelledby="podcast-person-delete-error">
    <p id="podcast-person-delete-error">Osobu nelze odebrat bez potvrzení kontroly jejího jména, role a dopadu na veřejný podcast.</p>
  </div>
<?php elseif ($error !== ''): ?>
  <p class="error" role="alert" id="form-error" aria-atomic="true"><?= h($error) ?></p>
<?php elseif (isset($_GET['ok'])): ?>
  <p class="success" role="status" aria-atomic="true">Seznam osob byl aktualizován.</p>
<?php endif; ?>

<section aria-labelledby="podcast-person-form-heading">
  <h2 id="podcast-person-form-heading"><?= !empty($person['id']) ? 'Upravit osobu' : 'Přidat osobu' ?></h2>
  <form id="podcast-person-form" method="post" novalidate<?= $error !== '' ? ' aria-describedby="form-error"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="show_id" value="<?= (int)$showId ?>">
    <?php if ($episodeId !== null): ?><input type="hidden" name="episode_id" value="<?= (int)$episodeId ?>"><?php endif; ?>
    <?php if (!empty($person['id'])): ?><input type="hidden" name="person_id" value="<?= (int)$person['id'] ?>"><?php endif; ?>
    <fieldset>
      <legend>Údaje osoby</legend>
      <label for="name">Jméno <span aria-hidden="true">*</span></label>
      <input type="text" id="name" name="name" maxlength="255" required aria-required="true" value="<?= h((string)$person['name']) ?>"<?= $errorField === 'name' ? ' aria-invalid="true" aria-describedby="name-error"' : '' ?>>
      <?php if ($errorField === 'name'): ?><small id="name-error" class="error"><?= h($error) ?></small><?php endif; ?>

      <div class="admin-form-grid admin-form-grid--end">
        <div class="admin-form-grid__cell">
          <label for="role_key">Role</label>
          <select id="role_key" name="role_key">
            <?php foreach (podcastPersonRoleOptions() as $roleKey => $roleLabel): ?>
              <option value="<?= h($roleKey) ?>"<?= normalizePodcastPersonRole((string)$person['role_key']) === $roleKey ? ' selected' : '' ?>><?= h($roleLabel) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="admin-form-grid__cell">
          <label for="group_key">Skupina</label>
          <select id="group_key" name="group_key">
            <option value="cast"<?= normalizePodcastPersonGroup((string)$person['group_key']) === 'cast' ? ' selected' : '' ?>>Účinkující</option>
            <option value="crew"<?= normalizePodcastPersonGroup((string)$person['group_key']) === 'crew' ? ' selected' : '' ?>>Tvůrčí tým</option>
          </select>
        </div>
        <div class="admin-form-grid__cell">
          <label for="sort_order">Pořadí</label>
          <input type="number" id="sort_order" name="sort_order" min="0" value="<?= (int)$person['sort_order'] ?>">
        </div>
      </div>

      <label for="profile_url">Veřejný profil osoby</label>
      <input type="url" id="profile_url" name="profile_url" maxlength="500" value="<?= h((string)$person['profile_url']) ?>"<?= $errorField === 'profile_url' ? ' aria-invalid="true" aria-describedby="profile-url-error"' : '' ?>>
      <?php if ($errorField === 'profile_url'): ?><small id="profile-url-error" class="error"><?= h($error) ?></small><?php endif; ?>

      <label for="image_url">Obrázek osoby</label>
      <input type="url" id="image_url" name="image_url" maxlength="500" value="<?= h((string)$person['image_url']) ?>"<?= $errorField === 'image_url' ? ' aria-invalid="true" aria-describedby="image-url-error"' : '' ?>>
      <?php if ($errorField === 'image_url'): ?><small id="image-url-error" class="error"><?= h($error) ?></small><?php endif; ?>
    </fieldset>
    <div class="button-row">
      <button type="submit" class="btn"><?= !empty($person['id']) ? 'Uložit osobu' : 'Přidat osobu' ?></button>
      <?php if (!empty($person['id'])): ?><a href="<?= h($baseUrl) ?>">Zrušit úpravu</a><?php endif; ?>
    </div>
  </form>
</section>

<section aria-labelledby="podcast-people-list-heading">
  <h2 id="podcast-people-list-heading">Seznam osob</h2>
  <?php if ($people === []): ?>
    <p>Zatím zde nejsou uvedeni žádní <?= $episodeId === null ? 'tvůrci pořadu' : 'hosté ani tvůrci epizody' ?>.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <caption><?= h($heading) ?></caption>
        <thead><tr><th scope="col">Jméno</th><th scope="col">Role</th><th scope="col">Skupina</th><th scope="col">Akce</th></tr></thead>
        <tbody>
        <?php foreach ($people as $listedPerson):
            $listedPersonId = (int)$listedPerson['id'];
            $personDeleteConfirmField = 'confirm_podcast_person_delete_' . $listedPersonId;
            $personDeleteReviewId = 'podcast-person-delete-review-' . $listedPersonId;
            $personDeleteFieldErrorId = 'confirm-podcast-person-delete-' . $listedPersonId . '-error';
            $personDeleteHasError = $deleteError && $deleteErrorId === $listedPersonId;
            $personDeleteErrors = $personDeleteHasError ? [$personDeleteConfirmField] : [];
            ?>
          <tr>
            <td><?= h((string)$listedPerson['name']) ?></td>
            <td><?= h(podcastPersonRoleLabel((string)$listedPerson['role_key'])) ?></td>
            <td><?= h(podcastPersonGroupLabel((string)$listedPerson['group_key'])) ?></td>
            <td class="actions">
              <a href="<?= h($baseUrl . '&edit=' . $listedPersonId) ?>" class="btn">Upravit</a>
              <form id="podcast-person-delete-form-<?= $listedPersonId ?>" method="post" novalidate<?= $personDeleteHasError ? ' aria-describedby="podcast-person-delete-error"' : '' ?>>
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="show_id" value="<?= (int)$showId ?>">
                <?php if ($episodeId !== null): ?><input type="hidden" name="episode_id" value="<?= (int)$episodeId ?>"><?php endif; ?>
                <input type="hidden" name="person_id" value="<?= $listedPersonId ?>">
                <input type="hidden" name="action" value="delete">
                <fieldset class="admin-inline-fieldset">
                  <legend>Odebrání osoby <?= h((string)$listedPerson['name']) ?></legend>
                  <p id="<?= h($personDeleteReviewId) ?>" class="field-help field-help--flush">
                    Odebrání trvale odstraní osobu „<?= h((string)$listedPerson['name']) ?>“ z <?= $episodeId === null ? 'veřejného detailu pořadu a RSS metadat' : 'veřejného detailu epizody a jejích RSS metadat' ?>. Podcast, epizoda i externí profil nebo obrázek zůstanou zachované.
                  </p>
                  <label for="confirm-podcast-person-delete-<?= $listedPersonId ?>" class="admin-checkbox-label">
                    <input type="checkbox" id="confirm-podcast-person-delete-<?= $listedPersonId ?>" name="<?= h($personDeleteConfirmField) ?>" value="1" required aria-required="true"<?= adminFieldAttributes($personDeleteConfirmField, $personDeleteErrors, [], [$personDeleteReviewId], $personDeleteFieldErrorId) ?>>
                    Potvrzuji odebrání této osoby.
                  </label>
                  <?php adminRenderFieldError($personDeleteConfirmField, $personDeleteErrors, [], 'Před odebráním potvrďte kontrolu osoby a jejího veřejného dopadu.', $personDeleteFieldErrorId); ?>
                  <button type="submit" class="btn btn-danger" data-confirm="Odebrat osobu?">Odebrat</button>
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
