<?php

require_once __DIR__ . '/layout.php';
requireCapability('appmarket_manage', 'Přístup odepřen. Pro správu Appmarketu nemáte potřebné oprávnění.');
requireModuleEnabled('appmarket');
requireReadOnlyHttpMethod();

$pdo = db_connect();
$appId = inputInt('get', 'app_id');
$id = inputInt('get', 'id');
$app = $appId !== null ? appmarketFindApp($pdo, $appId) : null;
$existing = $id !== null ? appmarketFindRelease($pdo, $id) : null;
if ($app === null || ($id !== null && ($existing === null
    || (int)$existing['app_id'] !== $appId || $existing['metadata_source'] !== 'manual'
    || $existing['status'] === 'withdrawn'))) {
    http_response_code(404);
    exit('Aplikace nebo upravitelné vydání nebyly nalezeny.');
}
$latestStmt = $pdo->prepare('SELECT * FROM cms_appmarket_releases WHERE app_id = ? ORDER BY version_code DESC, id DESC LIMIT 1');
if ($existing !== null && $existing['status'] === 'published') {
    requireSuperAdmin();
}
$latestStmt->execute([$appId]);
$latest = $latestStmt->fetch(PDO::FETCH_ASSOC);
$source = $existing ?? (is_array($latest) ? $latest : []);
$form = [
    'version_name' => (string)($source['version_name'] ?? ''),
    'platform' => (string)($source['platform'] ?? 'cross_platform'),
    'system_requirements' => (string)($source['system_requirements'] ?? ''),
    'release_notes' => $existing !== null ? (string)($existing['release_notes'] ?? '') : '',
    'status' => $existing !== null ? (string)$existing['status'] : 'draft',
    'release_channel' => (string)($source['release_channel'] ?? 'stable'),
    'revision' => $existing !== null ? appmarketCatalogRevision($existing) : '',
];
$flash = is_array($_SESSION['appmarket_catalog_flash'] ?? null) ? $_SESSION['appmarket_catalog_flash'] : [];
unset($_SESSION['appmarket_catalog_flash']);
$errors = [];
if (($flash['app_id'] ?? null) === $appId && ($flash['id'] ?? null) === $id) {
    $form = array_merge($form, is_array($flash['form'] ?? null) ? $flash['form'] : []);
    $errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
}
$heading = $existing !== null ? 'Upravit vydání' : ($latest ? 'Aktualizovat verzi' : 'První vydání softwaru');
$needsFile = $existing === null || !is_file(appmarketReleaseFilePath($existing));
adminHeader($heading);
?>
<p><a href="appmarket.php?app_id=<?= (int)$appId ?>"><span aria-hidden="true">←</span> Zpět na <?= h((string)$app['name']) ?></a></p>
<p class="admin-description">
  <?= $existing !== null && !$needsFile
      ? 'Upravujete toto vydání. Bez nového uploadu zůstane dosavadní soubor zachovaný.'
      : ($existing !== null
          ? 'U tohoto vydání chybí soubor, například po importu. Před uložením jej nahrajte.'
          : 'Vznikne nové samostatné vydání. Starší verze i jejich odkazy zůstanou v historii.') ?>
  Podpisy, certifikáty, licence a bezpečnost distribuovaného softwaru ověřuje jeho správce.
  CMS balíčky nespouští ani nerozbaluje a neprovádí antivirovou kontrolu. Android SDK není potřeba.
</p>
<?php if ($errors !== []): ?>
<div class="error" role="alert" id="appmarket-release-errors" aria-atomic="true">
  <p><strong>Vydání se nepodařilo uložit.</strong> Zadané texty zůstaly zachované. Soubor je po chybě potřeba vybrat znovu.</p>
  <ul>
  <?php foreach ($errors as $field => $message): ?>
    <li><?php if ($field !== 'form'): ?><a href="#<?= h((string)$field) ?>"><?= h((string)$message) ?></a><?php else: ?><?= h((string)$message) ?><?php endif; ?></li>
  <?php endforeach; ?>
  </ul>
  <?php if (isset($errors['form']) && $id !== null): ?>
    <p><a href="appmarket_release_form.php?app_id=<?= (int)$appId ?>&amp;id=<?= (int)$id ?>">Znovu načíst aktuálně uložené vydání</a> (zahodí neuložené změny)</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<form method="post" action="appmarket_release_save.php" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="app_id" value="<?= (int)$appId ?>">
  <?php if ($id !== null): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
  <input type="hidden" name="revision" value="<?= h((string)$form['revision']) ?>">
  <fieldset>
    <legend>Verze a požadavky na systém</legend>
    <label for="version_name">Číslo verze</label>
    <input type="text" id="version_name" name="version_name" maxlength="100" required value="<?= h((string)$form['version_name']) ?>" aria-describedby="version-name-help<?= isset($errors['version_name']) ? ' version-name-error' : '' ?>"<?= isset($errors['version_name']) ? ' aria-invalid="true"' : '' ?>>
    <p id="version-name-help" class="form-help">Například 1.2.0 nebo 2026.09-beta. Číslo zadáváte sami; interní pořadí vydání spravuje CMS.</p>
    <?php if (isset($errors['version_name'])): ?><p class="error" id="version-name-error"><?= h((string)$errors['version_name']) ?></p><?php endif; ?>

    <label for="platform">Platforma</label>
    <select id="platform" name="platform" required<?= isset($errors['platform']) ? ' aria-invalid="true" aria-describedby="platform-error"' : '' ?>>
      <option value="">Vyberte platformu</option>
      <?php foreach (appmarketPlatformDefinitions() as $value => $label): ?>
        <option value="<?= h($value) ?>"<?= $form['platform'] === $value ? ' selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (isset($errors['platform'])): ?><p class="error" id="platform-error"><?= h((string)$errors['platform']) ?></p><?php endif; ?>

    <label for="system_requirements">Požadavky na systém, volitelné</label>
    <textarea id="system_requirements" name="system_requirements" rows="4" maxlength="5000" aria-describedby="requirements-help<?= isset($errors['system_requirements']) ? ' requirements-error' : '' ?>"<?= isset($errors['system_requirements']) ? ' aria-invalid="true"' : '' ?>><?= h((string)$form['system_requirements']) ?></textarea>
    <p class="form-help" id="requirements-help">Verze systému, architektura procesoru, paměť nebo potřebné knihovny. Zobrazí se čtenářům jako text.</p>
    <?php if (isset($errors['system_requirements'])): ?><p class="error" id="requirements-error"><?= h((string)$errors['system_requirements']) ?></p><?php endif; ?>
  </fieldset>
  <fieldset>
    <legend>Soubor a seznam změn</legend>
    <label for="release_file"><?= $needsFile ? 'Soubor vydání' : 'Nahradit soubor, volitelné' ?></label>
    <input type="file" id="release_file" name="release_file" accept="<?= h(implode(',', array_map(static fn (string $extension): string => '.' . $extension, appmarketSoftwareExtensions()))) ?>"<?= $needsFile ? ' required' : '' ?> aria-describedby="release-file-help<?= isset($errors['release_file']) ? ' release-file-error' : '' ?>"<?= isset($errors['release_file']) ? ' aria-invalid="true"' : '' ?>>
    <p class="form-help" id="release-file-help">Limit <?= h(koraUploadMaxSizeLabel()) ?>; skutečný upload může omezit i PHP nebo hosting.
      Povolené formáty: <?= h(implode(', ', appmarketSoftwareExtensions())) ?>.
      <?php if ($existing !== null && !$needsFile): ?>Dosavadní soubor: <?= h((string)$existing['file_original_name']) ?> (<?= h(formatFileSize((int)$existing['file_size'])) ?>).<?php endif; ?>
    </p>
    <?php if (isset($errors['release_file'])): ?><p class="error" id="release-file-error"><?= h((string)$errors['release_file']) ?></p><?php endif; ?>
    <label for="release_notes">Changelog vydání, volitelné</label>
    <textarea id="release_notes" name="release_notes" rows="10" maxlength="<?= appmarketReleaseNotesMaxLength() ?>" aria-describedby="release-notes-help<?= isset($errors['release_notes']) ? ' release-notes-error' : '' ?>"<?= isset($errors['release_notes']) ? ' aria-invalid="true"' : '' ?>><?= h((string)$form['release_notes']) ?></textarea>
    <p class="form-help" id="release-notes-help">Co tato verze mění. Podporovaný je bezpečný Markdown, nikoli spouštění HTML nebo skriptů.</p>
    <?php if (isset($errors['release_notes'])): ?><p class="error" id="release-notes-error"><?= h((string)$errors['release_notes']) ?></p><?php endif; ?>
  </fieldset>
  <fieldset>
    <legend>Zveřejnění</legend>
    <label for="release_channel">Kanál vydání</label>
    <select id="release_channel" name="release_channel"<?= isset($errors['release_channel']) ? ' aria-invalid="true" aria-describedby="release-channel-error"' : '' ?>>
      <?php foreach (appmarketReleaseChannelDefinitions() as $value => $label): ?>
        <option value="<?= h($value) ?>"<?= $form['release_channel'] === $value ? ' selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (isset($errors['release_channel'])): ?><p class="error" id="release-channel-error"><?= h((string)$errors['release_channel']) ?></p><?php endif; ?>
    <label for="status">Stav vydání</label>
    <select id="status" name="status" aria-describedby="status-help<?= isset($errors['status']) ? ' status-error' : '' ?>"<?= isset($errors['status']) ? ' aria-invalid="true"' : '' ?>>
      <option value="draft"<?= $form['status'] === 'draft' ? ' selected' : '' ?>>Koncept</option>
      <?php if (isSuperAdmin()): ?><option value="published"<?= $form['status'] === 'published' ? ' selected' : '' ?>>Zveřejnit</option><?php endif; ?>
    </select>
    <p class="form-help" id="status-help">Zveřejnění zpřístupní aplikaci a toto vydání v katalogu a smí je provést jen superadmin. Obecná vydání se neposílají do starého Android aktualizačního API.</p>
    <?php if (isset($errors['status'])): ?><p class="error" id="status-error"><?= h((string)$errors['status']) ?></p><?php endif; ?>
  </fieldset>
  <p class="admin-actions"><button type="submit"><?= $id !== null ? 'Uložit změny vydání' : 'Uložit nové vydání' ?></button></p>
</form>
<?php adminFooter(); ?>
