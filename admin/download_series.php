<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu sérií ke stažení nemáte potřebné oprávnění.');
requireModuleEnabled('downloads');

$pdo = db_connect();
$message = trim((string)($_GET['msg'] ?? ''));
$deleteError = trim((string)($_GET['delete_error'] ?? ''));
$deleteErrorSeriesId = inputInt('get', 'delete_error_id');
$deleteErrorMessage = match ($deleteError) {
    'confirm_required' => 'Sérii ke stažení nejde smazat bez potvrzení kontroly dopadu. U pole Potvrzení smazání je konkrétní nápověda.',
    'invalid' => 'Sérii ke stažení nejde smazat, protože už není dostupná.',
    default => '',
};
$error = '';
$fieldErrors = [];
$fieldErrorMessages = [];
$editId = inputInt('get', 'edit');
$formState = [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'description' => '',
    'is_active' => 1,
    'sort_order' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? 'save'));

    if ($action === 'delete') {
        $deleteId = inputInt('post', 'series_id');
        if ($deleteId === null) {
            header('Location: ' . BASE_URL . '/admin/download_series.php?delete_error=invalid');
            exit;
        }

        $confirmFieldName = 'confirm_download_series_delete_' . $deleteId;
        $confirmedSeriesDelete = isset($_POST[$confirmFieldName])
            && (string)$_POST[$confirmFieldName] === '1';
        if (!$confirmedSeriesDelete) {
            header('Location: ' . BASE_URL . '/admin/download_series.php?delete_error=confirm_required&delete_error_id=' . $deleteId);
            exit;
        }

        $seriesStmt = $pdo->prepare('SELECT id FROM cms_download_series WHERE id = ? LIMIT 1');
        $seriesStmt->execute([$deleteId]);
        if (!$seriesStmt->fetch()) {
            header('Location: ' . BASE_URL . '/admin/download_series.php?delete_error=invalid&delete_error_id=' . $deleteId);
            exit;
        }

        $seriesImpactStmt = $pdo->prepare(
            'SELECT COUNT(*) AS download_count,
                    SUM(CASE WHEN is_current_version = 1 THEN 1 ELSE 0 END) AS current_count
             FROM cms_downloads
             WHERE download_series_id = ? AND deleted_at IS NULL'
        );
        $seriesImpactStmt->execute([$deleteId]);
        $seriesImpact = $seriesImpactStmt->fetch() ?: [];
        $seriesDownloadCount = (int)($seriesImpact['download_count'] ?? 0);
        $seriesCurrentCount = (int)($seriesImpact['current_count'] ?? 0);

        $pdo->prepare("UPDATE cms_downloads SET download_series_id = NULL, is_current_version = 0 WHERE download_series_id = ?")->execute([$deleteId]);
        $pdo->prepare("DELETE FROM cms_download_series WHERE id = ?")->execute([$deleteId]);
        logAction('download_series_delete', "id={$deleteId};download_count={$seriesDownloadCount};current_count={$seriesCurrentCount}");
        header('Location: ' . BASE_URL . '/admin/download_series.php?msg=deleted');
        exit;
    }

    $seriesId = inputInt('post', 'series_id');
    $formState = [
        'id' => $seriesId ?? 0,
        'title' => trim((string)($_POST['title'] ?? '')),
        'slug' => trim((string)($_POST['slug'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'sort_order' => max(0, (int)($_POST['sort_order'] ?? 0)),
    ];
    $editId = $seriesId;

    if ($formState['title'] === '') {
        $error = 'Sérii ke stažení nejde uložit bez názvu. U pole Název série je konkrétní nápověda.';
        $fieldErrors[] = 'title';
        $fieldErrorMessages['title'] = 'Doplňte krátký název série, například Instalační balíčky.';
    } else {
        $submittedSlug = downloadSeriesSlug($formState['slug'] !== '' ? $formState['slug'] : $formState['title']);
        if ($submittedSlug === '') {
            $error = 'Slug veřejné série ke stažení není možné vytvořit. U pole Slug série je konkrétní nápověda.';
            $fieldErrors[] = 'slug';
            $fieldErrorMessages['slug'] = 'Použijte alespoň jedno písmeno nebo číslo. Vhodný slug může vypadat třeba instalacni-balicky.';
        } else {
            $uniqueSlug = uniqueDownloadSeriesSlug($pdo, $submittedSlug, $seriesId);
            if ($formState['slug'] !== '' && $uniqueSlug !== $submittedSlug) {
                $error = 'Slug veřejné série ke stažení už používá jiná série. U pole Slug série je konkrétní nápověda.';
                $fieldErrors[] = 'slug';
                $fieldErrorMessages['slug'] = 'Zadejte jiný unikátní slug, nebo pole nechte prázdné a CMS ho vytvoří z názvu.';
            } elseif ($seriesId !== null) {
                $existingStmt = $pdo->prepare("SELECT * FROM cms_download_series WHERE id = ?");
                $existingStmt->execute([$seriesId]);
                $existingSeries = $existingStmt->fetch() ?: null;
                if (!$existingSeries) {
                    $error = 'Upravovaná série neexistuje.';
                } else {
                    $pdo->prepare(
                        "UPDATE cms_download_series
                         SET title = ?, slug = ?, description = ?, is_active = ?, sort_order = ?, updated_at = NOW()
                         WHERE id = ?"
                    )->execute([
                        $formState['title'],
                        $uniqueSlug,
                        $formState['description'],
                        (int)$formState['is_active'],
                        (int)$formState['sort_order'],
                        $seriesId,
                    ]);
                    if ((int)$formState['is_active'] === 1 && downloadSeriesPath($existingSeries) !== downloadSeriesPath(['id' => $seriesId, 'slug' => $uniqueSlug])) {
                        upsertPathRedirect(
                            $pdo,
                            downloadSeriesPath($existingSeries),
                            downloadSeriesPath(['id' => $seriesId, 'slug' => $uniqueSlug])
                        );
                    }
                    logAction('download_series_edit', "id={$seriesId} title={$formState['title']} slug={$uniqueSlug}");
                    header('Location: ' . BASE_URL . '/admin/download_series.php?msg=saved');
                    exit;
                }
            } else {
                $sortOrder = (int)$formState['sort_order'];
                if ($sortOrder <= 0) {
                    $sortOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM cms_download_series")->fetchColumn();
                }
                $pdo->prepare(
                    "INSERT INTO cms_download_series (title, slug, description, is_active, sort_order)
                     VALUES (?, ?, ?, ?, ?)"
                )->execute([
                    $formState['title'],
                    $uniqueSlug,
                    $formState['description'],
                    (int)$formState['is_active'],
                    $sortOrder,
                ]);
                logAction('download_series_add', "title={$formState['title']} slug={$uniqueSlug}");
                header('Location: ' . BASE_URL . '/admin/download_series.php?msg=saved');
                exit;
            }
        }
    }
}

if ($editId !== null && $error === '') {
    $editStmt = $pdo->prepare("SELECT * FROM cms_download_series WHERE id = ?");
    $editStmt->execute([$editId]);
    $editSeries = $editStmt->fetch() ?: null;
    if ($editSeries) {
        $formState = [
            'id' => (int)$editSeries['id'],
            'title' => (string)$editSeries['title'],
            'slug' => (string)$editSeries['slug'],
            'description' => (string)($editSeries['description'] ?? ''),
            'is_active' => (int)$editSeries['is_active'],
            'sort_order' => (int)$editSeries['sort_order'],
        ];
    } else {
        $editId = null;
    }
}

$seriesRows = $pdo->query(
    "SELECT s.id, s.title, s.slug, s.description, s.is_active, s.sort_order,
            COUNT(d.id) AS download_count,
            SUM(CASE WHEN d.is_current_version = 1 THEN 1 ELSE 0 END) AS current_count
     FROM cms_download_series s
     LEFT JOIN cms_downloads d ON d.download_series_id = s.id AND d.deleted_at IS NULL
     GROUP BY s.id, s.title, s.slug, s.description, s.is_active, s.sort_order
     ORDER BY s.sort_order, s.title"
)->fetchAll();

adminHeader('Ke stažení – série a verze');
?>
<?php if ($message === 'saved'): ?><p class="success" role="status">Série byla uložena.</p><?php endif; ?>
<?php if ($message === 'deleted'): ?><p class="success" role="status">Série byla smazána.</p><?php endif; ?>
<?php if ($error !== ''): ?><p id="download-series-error" class="error" role="alert" aria-atomic="true"><?= h($error) ?></p><?php endif; ?>
<?php if ($deleteErrorMessage !== ''): ?><p id="download-series-delete-error" class="error" role="alert" aria-atomic="true"><?= h($deleteErrorMessage) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <a href="downloads.php"><span aria-hidden="true">←</span> Zpět na soubory a položky</a>
  <a href="<?= h(BASE_URL . '/downloads/index.php') ?>" target="_blank" rel="noopener noreferrer">Zobrazit katalog na webu<?= newWindowLinkSrOnlySuffix() ?></a>
</p>

<p class="admin-description">Série propojí více vydání stejného dokumentu, aplikace nebo balíčku. Veřejně se zobrazí jen aktivní série s alespoň jednou publikovanou položkou.</p>

<form method="post" class="form-card" novalidate<?= $error !== '' ? ' aria-describedby="download-series-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="series_id" value="<?= (int)$formState['id'] ?>">
  <fieldset>
    <legend><?= (int)$formState['id'] > 0 ? 'Upravit sérii' : 'Přidat sérii' ?></legend>
    <label for="series-title">Název série <span aria-hidden="true">*</span></label>
    <input type="text" id="series-title" name="title" required aria-required="true" maxlength="255" value="<?= h((string)$formState['title']) ?>"
           <?= adminFieldAttributes('title', $fieldErrors, [], ['series-title-help']) ?>>
    <small id="series-title-help" class="field-help">Použijte krátký název, který správci i návštěvníci poznají v katalogu ke stažení.</small>
    <?php adminRenderFieldError('title', $fieldErrors, [], $fieldErrorMessages['title'] ?? ''); ?>

    <label for="series-slug">Slug série</label>
    <input type="text" id="series-slug" name="slug" maxlength="150" pattern="[a-z0-9\-]+" value="<?= h((string)$formState['slug']) ?>"
           <?= adminFieldAttributes('slug', $fieldErrors, [], ['series-slug-help']) ?>>
    <small id="series-slug-help" class="field-help">Volitelné. Veřejná adresa bude mít tvar <code>/downloads/serie/slug-serie</code>.</small>
    <?php adminRenderFieldError('slug', $fieldErrors, [], $fieldErrorMessages['slug'] ?? ''); ?>

    <label for="series-description">Popis série</label>
    <textarea id="series-description" name="description" rows="4" aria-describedby="series-description-help"><?= h((string)$formState['description']) ?></textarea>
    <small id="series-description-help" class="field-help">Zobrazí se na veřejné stránce série nad seznamem verzí.</small>

    <label for="series-sort">Pořadí</label>
    <input type="number" id="series-sort" name="sort_order" min="0" value="<?= (int)$formState['sort_order'] ?>" aria-describedby="series-sort-help">
    <small id="series-sort-help" class="field-help">Nižší číslo se ve správě i veřejných seznamech řadí dříve.</small>

    <label class="admin-checkbox-label">
      <input type="checkbox" name="is_active" value="1"<?= (int)$formState['is_active'] === 1 ? ' checked' : '' ?>>
      Zobrazovat sérii na webu
    </label>
    <button type="submit" class="btn admin-action-row"><?= (int)$formState['id'] > 0 ? 'Uložit sérii' : 'Přidat sérii' ?></button>
    <?php if ((int)$formState['id'] > 0): ?>
      <a href="download_series.php" class="btn">Zrušit úpravy</a>
    <?php endif; ?>
  </fieldset>
</form>

<h2>Existující série</h2>
<?php if ($seriesRows === []): ?>
  <p>Zatím tu nejsou žádné série ke stažení.</p>
<?php else: ?>
  <table>
    <caption>Série a verze ke stažení</caption>
    <thead>
      <tr>
        <th scope="col">Série</th>
        <th scope="col">Slug</th>
        <th scope="col">Položky</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($seriesRows as $seriesRow): ?>
        <?php
          $seriesId = (int)$seriesRow['id'];
          $seriesDeleteConfirmField = 'confirm_download_series_delete_' . $seriesId;
          $seriesDeleteConfirmId = 'confirm-download-series-delete-' . $seriesId;
          $seriesDeleteReviewId = 'download-series-delete-review-' . $seriesId;
          $seriesDeleteFieldErrorId = 'confirm-download-series-delete-' . $seriesId . '-error';
          $seriesDeleteHasError = $deleteError === 'confirm_required' && $deleteErrorSeriesId === $seriesId;
          $seriesDeleteErrorFields = $seriesDeleteHasError ? [$seriesDeleteConfirmField] : [];
          ?>
        <tr>
          <td>
            <strong><?= h((string)$seriesRow['title']) ?></strong>
            <?php if (trim((string)($seriesRow['description'] ?? '')) !== ''): ?>
              <br><small class="field-help"><?= h(mb_strimwidth(normalizePlainText((string)$seriesRow['description']), 0, 120, '…', 'UTF-8')) ?></small>
            <?php endif; ?>
          </td>
          <td><code><?= h((string)$seriesRow['slug']) ?></code></td>
          <td>
            <?= (int)$seriesRow['download_count'] ?>
            <?php if ((int)$seriesRow['current_count'] > 0): ?>
              <br><small class="table-meta">Aktuální verze označena</small>
            <?php endif; ?>
          </td>
          <td><?= (int)$seriesRow['is_active'] === 1 ? 'Aktivní' : 'Skrytá' ?></td>
          <td class="actions">
            <?php if ((int)$seriesRow['is_active'] === 1 && (int)$seriesRow['download_count'] > 0): ?>
              <a class="btn" href="<?= h(downloadSeriesPath($seriesRow)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
            <?php endif; ?>
            <a class="btn" href="download_series.php?edit=<?= $seriesId ?>">Upravit</a>
            <form method="post" class="admin-inline-form" novalidate<?= $seriesDeleteHasError ? ' aria-describedby="download-series-delete-error"' : '' ?>>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="series_id" value="<?= $seriesId ?>">
              <fieldset class="admin-inline-fieldset">
                <legend class="sr-only">Smazání série <?= h((string)$seriesRow['title']) ?></legend>
                <p id="<?= h($seriesDeleteReviewId) ?>" class="field-help field-help--flush">
                  Smazání odebere sérii z <?= (int)$seriesRow['download_count'] ?> položek ke stažení a zruší označení aktuální verze u <?= (int)$seriesRow['current_count'] ?> položek. Položky zůstanou zachované.
                </p>
                <label for="<?= h($seriesDeleteConfirmId) ?>" class="admin-checkbox-label">
                  <input
                    type="checkbox"
                    id="<?= h($seriesDeleteConfirmId) ?>"
                    name="<?= h($seriesDeleteConfirmField) ?>"
                    value="1"
                    required
                    aria-required="true"<?= adminFieldAttributes($seriesDeleteConfirmField, $seriesDeleteErrorFields, [], [$seriesDeleteReviewId], $seriesDeleteFieldErrorId) ?>>
                  Potvrzuji smazání této série.
                </label>
                <?php adminRenderFieldError($seriesDeleteConfirmField, $seriesDeleteErrorFields, [], 'Před smazáním série potvrďte, že jste zkontrolovali navázané položky a aktuální verzi.', $seriesDeleteFieldErrorId); ?>
                <button type="submit" class="btn btn-danger" data-confirm="Smazat sérii? Položky ke stažení zůstanou zachované, smaže se jen jejich zařazení do této série.">Smazat</button>
              </fieldset>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
