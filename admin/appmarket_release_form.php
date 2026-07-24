<?php

require_once __DIR__ . '/layout.php';
requireCapability('appmarket_manage', 'Přístup odepřen. Pro správu Appmarketu nemáte potřebné oprávnění.');
requireModuleEnabled('appmarket');

$pdo = db_connect();
$appId = inputInt('get', 'app_id');
$app = $appId !== null ? appmarketFindApp($pdo, $appId) : null;
if ($app === null) {
    header('Location: appmarket.php');
    exit;
}

$flash = is_array($_SESSION['appmarket_release_flash'] ?? null) ? $_SESSION['appmarket_release_flash'] : [];
unset($_SESSION['appmarket_release_flash']);
$errors = isset($flash['errors']) && is_array($flash['errors']) ? $flash['errors'] : [];
$releaseNotes = (string)($flash['release_notes'] ?? '');
$releaseNotesError = trim((string)($flash['release_notes_error'] ?? ''));
$apkanalyzerAvailable = appmarketFindAndroidTool('apkanalyzer') !== '';
$apksignerAvailable = appmarketFindAndroidTool('apksigner') !== '';
$attestationAvailable = appmarketAttestationOpenSslAvailable();

adminHeader('Nové vydání aplikace');
?>
<p><a href="appmarket.php"><span aria-hidden="true">←</span> Zpět na Appmarket</a></p>
<p class="admin-description">
  Aplikace: <strong><?= h((string)$app['name']) ?></strong>,
  applicationId: <code><?= h((string)$app['package_id']) ?></code>.
</p>

<?php if ($errors !== []): ?>
  <div class="error" role="alert" id="appmarket-release-errors" aria-atomic="true">
    <p><strong>Vydání se nepodařilo připravit.</strong></p>
    <ul>
      <?php foreach ($errors as $error): ?>
        <li><?= h((string)$error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<section class="admin-section" aria-labelledby="appmarket-analysis-heading">
  <h2 id="appmarket-analysis-heading">Kontrola APK</h2>
  <?php if ($apkanalyzerAvailable && $apksignerAvailable): ?>
    <p class="success" role="status">Server má dostupný <code>apkanalyzer</code> i <code>apksigner</code> a ověří APK přímo.</p>
  <?php elseif ($attestationAvailable): ?>
    <p class="success" role="status">
      Server nemá oba Android nástroje, ale podporuje kryptograficky podepsaná vydání z lokálního
      publisheru. Úplnou Android kontrolu provede váš počítač a doména nezávisle ověří podpis
      manifestu, velikost a SHA-256 APK.
    </p>
  <?php else: ?>
    <p class="error" role="alert">
      Server nemá Android nástroje ani PHP rozšíření OpenSSL. Bez alespoň jednoho bezpečného
      ověřovacího režimu nelze vydání přijmout.
    </p>
  <?php endif; ?>
  <p>Každé vydání vznikne jako koncept. Zveřejnit ho může až superadmin po schválení podpisového certifikátu.</p>
</section>

<form method="post" action="appmarket_release_save.php" enctype="multipart/form-data" novalidate
      <?= $errors !== [] ? 'aria-describedby="appmarket-release-errors"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">

  <fieldset>
    <legend>Produkční balíček</legend>

    <label for="release_file">APK nebo publisher balíček <span aria-hidden="true">*</span></label>
    <input type="file" id="release_file" name="release_file" required aria-required="true"
           accept=".apk,.zip,application/vnd.android.package-archive,application/zip"
           aria-describedby="appmarket-release-file-help<?= $errors !== [] ? ' appmarket-release-file-error' : '' ?>"
           <?= $errors !== [] ? 'aria-invalid="true"' : '' ?>>
    <small id="appmarket-release-file-help" class="field-help">
      Limit je <?= h(koraUploadMaxSizeLabel()) ?> a současně nesmí být nižší PHP limity hostingu.
      Samostatné APK vyžaduje Android nástroje na serveru. Na hostingu bez nich použijte
      <code>tools/appmarket-publish.ps1</code>, případně nahrajte <code>.kora-app-release.zip</code>
      s přesně podepsanými soubory <code>release.json</code> a <code>release.sig</code>.
      Debug a QA APK, duplicitní nebo nižší versionCode a cizí applicationId se odmítnou.
    </small>
    <?php if ($errors !== []): ?>
      <small id="appmarket-release-file-error" class="field-help field-error">Opravte problémy uvedené nad formulářem a soubor vyberte znovu.</small>
    <?php endif; ?>

    <label for="release_notes">Seznam změn</label>
    <textarea id="release_notes" name="release_notes" rows="10" maxlength="<?= appmarketReleaseNotesMaxLength() ?>"
              aria-describedby="appmarket-release-notes-help<?= $releaseNotesError !== '' ? ' appmarket-release-notes-error' : '' ?>"
              <?= $releaseNotesError !== '' ? 'aria-invalid="true"' : '' ?>><?= h($releaseNotes) ?></textarea>
    <small id="appmarket-release-notes-help" class="field-help">Bezpečný Markdown se zobrazí na veřejném detailu vydání a prostý text v update API; vložené HTML se nevykoná.</small>
    <?php if ($releaseNotesError !== ''): ?>
      <small id="appmarket-release-notes-error" class="field-help field-error"><?= h($releaseNotesError) ?></small>
    <?php endif; ?>
  </fieldset>

  <button type="submit">Analyzovat a uložit koncept</button>
</form>

<?php adminFooter(); ?>
