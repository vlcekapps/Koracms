<?php

require_once __DIR__ . '/layout.php';
requireCapability('appmarket_manage', 'Přístup odepřen. Pro správu Appmarketu nemáte potřebné oprávnění.');
requireModuleEnabled('appmarket');

$pdo = db_connect();
$id = inputInt('get', 'id');
$app = [
    'id' => null,
    'name' => '',
    'slug' => '',
    'package_id' => '',
    'short_description' => '',
    'description' => '',
    'icon_media_id' => null,
    'website_url' => '',
    'support_url' => '',
    'privacy_url' => '',
    'license_label' => '',
    'status' => 'draft',
    'is_featured' => 0,
    'sort_order' => 0,
];
$hasReleases = false;

if ($id !== null) {
    $existing = appmarketFindApp($pdo, $id);
    if ($existing === null) {
        header('Location: appmarket.php');
        exit;
    }
    $app = array_merge($app, $existing);
    $releaseCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_appmarket_releases WHERE app_id = ?');
    $releaseCountStmt->execute([$id]);
    $hasReleases = (int)$releaseCountStmt->fetchColumn() > 0;
}

$flash = is_array($_SESSION['appmarket_form_flash'] ?? null) ? $_SESSION['appmarket_form_flash'] : [];
unset($_SESSION['appmarket_form_flash']);
if (isset($flash['form']) && is_array($flash['form'])) {
    $app = array_merge($app, $flash['form']);
}
$errors = isset($flash['errors']) && is_array($flash['errors']) ? $flash['errors'] : [];
$fieldErrors = isset($flash['field_errors']) && is_array($flash['field_errors'])
    ? array_values(array_unique(array_map('strval', $flash['field_errors'])))
    : [];
$fieldErrorMap = [];
foreach ($fieldErrors as $fieldName) {
    $fieldErrorMap[$fieldName] = [$fieldName];
}

$mediaRows = $pdo->query(
    "SELECT id, original_name, alt_text, mime_type
     FROM cms_media
     WHERE visibility = 'public'
       AND mime_type LIKE 'image/%'
     ORDER BY created_at DESC, id DESC
     LIMIT 300"
)->fetchAll();
$selectedScreenshots = [];
if ($id !== null) {
    $screenshotStmt = $pdo->prepare(
        'SELECT media_id FROM cms_appmarket_screenshots WHERE app_id = ? ORDER BY sort_order, id'
    );
    $screenshotStmt->execute([$id]);
    $selectedScreenshots = array_map('intval', $screenshotStmt->fetchAll(PDO::FETCH_COLUMN));
}
if (isset($flash['screenshots']) && is_array($flash['screenshots'])) {
    $selectedScreenshots = array_values(array_unique(array_map('intval', $flash['screenshots'])));
}

$fieldMessage = static function (string $fieldName) use ($errors): string {
    foreach ($errors as $error) {
        if (is_array($error) && (string)($error['field'] ?? '') === $fieldName) {
            return (string)($error['message'] ?? '');
        }
    }
    return '';
};

adminHeader($id !== null ? 'Upravit aplikaci' : 'Nová aplikace');
?>
<p><a href="appmarket.php"><span aria-hidden="true">←</span> Zpět na Appmarket</a></p>

<?php if ($errors !== []): ?>
  <div class="error" role="alert" id="appmarket-form-errors" aria-atomic="true">
    <p><strong>Aplikaci se nepodařilo uložit.</strong></p>
    <ul>
      <?php foreach ($errors as $error): ?>
        <li><?= h(is_array($error) ? (string)($error['message'] ?? '') : (string)$error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="appmarket_save.php" novalidate<?= $errors !== [] ? ' aria-describedby="appmarket-form-errors"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id !== null): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Základní údaje aplikace</legend>

    <label for="name">Název <span aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" required aria-required="true" maxlength="255"
           value="<?= h((string)$app['name']) ?>"<?= adminFieldAttributes('name', $fieldErrors, $fieldErrorMap) ?>>
    <?php adminRenderFieldError('name', $fieldErrors, $fieldErrorMap, $fieldMessage('name')); ?>

    <label for="slug">Slug veřejné stránky, volitelné</label>
    <input type="text" id="slug" name="slug" maxlength="150"
           pattern="[a-z0-9\-]+" value="<?= h((string)$app['slug']) ?>"
           <?= adminFieldAttributes('slug', $fieldErrors, $fieldErrorMap, ['appmarket-slug-help']) ?>>
    <small id="appmarket-slug-help" class="field-help">Prázdný slug se vytvoří z názvu. Použijte malá písmena, číslice a pomlčky.</small>
    <?php adminRenderFieldError('slug', $fieldErrors, $fieldErrorMap, $fieldMessage('slug')); ?>

    <label for="package_id">Android applicationId <span aria-hidden="true">*</span></label>
    <input type="text" id="package_id" name="package_id" required aria-required="true" maxlength="255"
           value="<?= h((string)$app['package_id']) ?>"<?= $hasReleases ? ' readonly' : '' ?>
           <?= adminFieldAttributes('package_id', $fieldErrors, $fieldErrorMap, ['appmarket-package-help']) ?>>
    <small id="appmarket-package-help" class="field-help">
      Například <code>cz.vlcekapps.minirec</code>. Všechna vydání se proti této hodnotě ověřují.
      <?php if ($hasReleases): ?>Po nahrání prvního vydání už identitu aplikace nelze změnit.<?php endif; ?>
    </small>
    <?php adminRenderFieldError('package_id', $fieldErrors, $fieldErrorMap, $fieldMessage('package_id')); ?>

    <label for="short_description">Krátký popis <span aria-hidden="true">*</span></label>
    <textarea id="short_description" name="short_description" rows="3" maxlength="500" required aria-required="true"
              <?= adminFieldAttributes('short_description', $fieldErrors, $fieldErrorMap, ['appmarket-short-description-help']) ?>><?= h((string)$app['short_description']) ?></textarea>
    <small id="appmarket-short-description-help" class="field-help">Stručné vysvětlení účelu aplikace pro katalog a výsledky hledání.</small>
    <?php adminRenderFieldError('short_description', $fieldErrors, $fieldErrorMap, $fieldMessage('short_description')); ?>

    <label for="description">Podrobný popis</label>
    <textarea id="description" name="description" rows="12" aria-describedby="appmarket-description-help"><?= h((string)$app['description']) ?></textarea>
    <small id="appmarket-description-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small>
  </fieldset>

  <fieldset>
    <legend>Odkazy a licence</legend>

    <label for="website_url">Web aplikace</label>
    <input type="url" id="website_url" name="website_url" maxlength="500"
           value="<?= h((string)$app['website_url']) ?>" placeholder="https://example.cz"
           <?= adminFieldAttributes('website_url', $fieldErrors, $fieldErrorMap) ?>>
    <?php adminRenderFieldError('website_url', $fieldErrors, $fieldErrorMap, $fieldMessage('website_url')); ?>

    <label for="support_url">Podpora</label>
    <input type="url" id="support_url" name="support_url" maxlength="500"
           value="<?= h((string)$app['support_url']) ?>" placeholder="https://example.cz/podpora"
           <?= adminFieldAttributes('support_url', $fieldErrors, $fieldErrorMap) ?>>
    <?php adminRenderFieldError('support_url', $fieldErrors, $fieldErrorMap, $fieldMessage('support_url')); ?>

    <label for="privacy_url">Ochrana soukromí</label>
    <input type="url" id="privacy_url" name="privacy_url" maxlength="500"
           value="<?= h((string)$app['privacy_url']) ?>" placeholder="https://example.cz/soukromi"
           <?= adminFieldAttributes('privacy_url', $fieldErrors, $fieldErrorMap) ?>>
    <?php adminRenderFieldError('privacy_url', $fieldErrors, $fieldErrorMap, $fieldMessage('privacy_url')); ?>

    <label for="license_label">Licence aplikace</label>
    <input type="text" id="license_label" name="license_label" maxlength="100"
           value="<?= h((string)$app['license_label']) ?>" placeholder="například Proprietární nebo GPL-3.0">
  </fieldset>

  <fieldset>
    <legend>Obrázky z knihovny médií</legend>

    <label for="icon_media_id">Ikona aplikace</label>
    <select id="icon_media_id" name="icon_media_id"
            <?= adminFieldAttributes('icon_media_id', $fieldErrors, $fieldErrorMap, ['appmarket-icon-help']) ?>>
      <option value="">Bez ikony</option>
      <?php foreach ($mediaRows as $media): ?>
        <option value="<?= (int)$media['id'] ?>"<?= (int)($app['icon_media_id'] ?? 0) === (int)$media['id'] ? ' selected' : '' ?>>
          <?= h((string)$media['original_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <small id="appmarket-icon-help" class="field-help">Nabízejí se jen veřejné obrázky. Alt text se převezme z knihovny médií.</small>
    <?php adminRenderFieldError('icon_media_id', $fieldErrors, $fieldErrorMap, $fieldMessage('icon_media_id')); ?>

    <fieldset<?= adminFieldAttributes('screenshots', $fieldErrors, $fieldErrorMap, ['appmarket-screenshots-help']) ?>>
      <legend>Snímky obrazovky, nejvýše 12</legend>
      <p id="appmarket-screenshots-help" class="field-help">Pořadí odpovídá pořadí médií v seznamu. Vybrat lze jen snímky s výstižným alt textem doplněným v knihovně médií.</p>
      <?php if ($mediaRows === []): ?>
        <p>V knihovně médií zatím není žádný veřejný obrázek.</p>
      <?php else: ?>
        <?php foreach ($mediaRows as $media): ?>
          <?php $mediaId = (int)$media['id']; ?>
          <label class="admin-checkbox-label" for="appmarket-screenshot-<?= $mediaId ?>">
            <input type="checkbox" id="appmarket-screenshot-<?= $mediaId ?>" name="screenshot_ids[]"
                   value="<?= $mediaId ?>"<?= in_array($mediaId, $selectedScreenshots, true) ? ' checked' : '' ?>>
            <?= h((string)$media['original_name']) ?>
            <?php if (trim((string)$media['alt_text']) === ''): ?>
              <span>(chybí alt text)</span>
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>
    </fieldset>
    <?php adminRenderFieldError('screenshots', $fieldErrors, $fieldErrorMap, $fieldMessage('screenshots')); ?>
  </fieldset>

  <fieldset>
    <legend>Zobrazení</legend>

    <label for="sort_order">Pořadí</label>
    <input type="number" id="sort_order" name="sort_order" min="0" max="100000"
           value="<?= (int)$app['sort_order'] ?>">

    <label class="admin-checkbox-label" for="is_featured">
      <input type="checkbox" id="is_featured" name="is_featured" value="1"<?= (int)$app['is_featured'] === 1 ? ' checked' : '' ?>>
      Doporučená aplikace
    </label>

    <?php if ($id !== null && isSuperAdmin()): ?>
      <label for="status">Stav aplikace</label>
      <select id="status" name="status"
              <?= adminFieldAttributes('status', $fieldErrors, $fieldErrorMap, ['appmarket-status-help']) ?>>
        <?php foreach (appmarketAppStatusDefinitions() as $statusKey => $statusLabel): ?>
          <option value="<?= h($statusKey) ?>"<?= (string)$app['status'] === $statusKey ? ' selected' : '' ?>><?= h($statusLabel) ?></option>
        <?php endforeach; ?>
      </select>
      <small id="appmarket-status-help" class="field-help">Zveřejnit lze jen aplikaci, která má alespoň jedno zveřejněné vydání.</small>
      <?php adminRenderFieldError('status', $fieldErrors, $fieldErrorMap, $fieldMessage('status')); ?>
    <?php endif; ?>
  </fieldset>

  <button type="submit">Uložit aplikaci</button>
</form>

<?php adminFooter(); ?>
