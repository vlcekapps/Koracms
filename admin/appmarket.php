<?php

require_once __DIR__ . '/layout.php';
requireCapability('appmarket_manage', 'Přístup odepřen. Pro správu Appmarketu nemáte potřebné oprávnění.');
requireModuleEnabled('appmarket');

$pdo = db_connect();
$schemaReady = true;
$apps = [];
$selectedApp = null;
$releases = [];
$selectedAppId = inputInt('get', 'app_id');

try {
    $apps = $pdo->query(
        "SELECT a.*,
                (SELECT COUNT(*) FROM cms_appmarket_releases r WHERE r.app_id = a.id) AS release_count,
                (SELECT COUNT(*) FROM cms_appmarket_releases r WHERE r.app_id = a.id AND r.status = 'published') AS published_release_count
         FROM cms_appmarket_apps a
         ORDER BY a.is_featured DESC, a.sort_order, a.name, a.id"
    )->fetchAll();
    if ($selectedAppId !== null) {
        $selectedApp = appmarketFindApp($pdo, $selectedAppId);
        if ($selectedApp !== null) {
            $releaseStmt = $pdo->prepare(
                "SELECT r.*, c.is_active AS certificate_is_active
                 FROM cms_appmarket_releases r
                 LEFT JOIN cms_appmarket_certificates c ON c.id = r.certificate_id
                 WHERE r.app_id = ?
                 ORDER BY r.version_code DESC, r.id DESC"
            );
            $releaseStmt->execute([$selectedAppId]);
            $releases = array_map(
                static fn (array $release): array => appmarketHydrateReleasePresentation($release),
                $releaseStmt->fetchAll()
            );
        }
    }
} catch (PDOException $e) {
    $schemaReady = false;
    koraLog('warning', 'appmarket admin schema is not ready', ['exception' => $e]);
}

$notice = trim((string)($_SESSION['appmarket_notice'] ?? ''));
$noticeError = trim((string)($_SESSION['appmarket_notice_error'] ?? ''));
unset($_SESSION['appmarket_notice'], $_SESSION['appmarket_notice_error']);
if ((string)($_GET['ok'] ?? '') === 'saved') {
    $notice = 'Aplikace byla uložena.';
}

adminHeader('Appmarket');
?>
<p class="admin-description">
  Katalog softwaru pro různé platformy. U aplikace spravujete popis, obrázky a licenci;
  přes Aktualizovat verzi vytvoříte nové vydání s vlastní platformou, souborem a seznamem změn.
</p>

<?php if ($notice !== ''): ?><p class="success" role="status"><?= h($notice) ?></p><?php endif; ?>
<?php if ($noticeError !== ''): ?><p class="error" role="alert"><?= h($noticeError) ?></p><?php endif; ?>

<?php if (!$schemaReady): ?>
  <p class="error" role="alert">
    Databázový základ Appmarketu zatím není připravený. Přihlaste se jako superadmin a spusťte <a href="<?= h(BASE_URL . '/migrate.php') ?>">migraci databáze</a>.
  </p>
<?php else: ?>
  <div class="admin-actions">
    <a class="button" href="appmarket_form.php">Nová aplikace</a>
    <?php if (isSuperAdmin()): ?>
      <a class="button button-secondary" href="<?= h(BASE_URL . '/aplikace') ?>">Veřejný katalog</a>
    <?php endif; ?>
  </div>

  <section class="admin-section" aria-labelledby="appmarket-overview-heading">
    <h2 id="appmarket-overview-heading">Aplikace</h2>
    <?php if ($apps === []): ?>
      <p>Zatím není vytvořená žádná aplikace. Začněte jejím názvem a krátkým popisem, potom přidejte první verzi.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <caption>Aplikace spravované Appmarketem</caption>
          <thead>
            <tr>
              <th scope="col">Aplikace</th>
              <th scope="col">Stav</th>
              <th scope="col">Vydání</th>
              <th scope="col">Akce</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($apps as $app): ?>
              <tr>
                <th scope="row">
                  <a href="appmarket.php?app_id=<?= (int)$app['id'] ?>"><?= h((string)$app['name']) ?></a>
                </th>
                <td><?= h(appmarketAppStatusDefinitions()[appmarketNormalizeAppStatus((string)$app['status'])]) ?></td>
                <td>
                  <?= (int)$app['published_release_count'] ?> zveřejněných /
                  <?= (int)$app['release_count'] ?> celkem
                </td>
                <td>
                  <p><a class="button" href="appmarket_release_form.php?app_id=<?= (int)$app['id'] ?>">Aktualizovat verzi<span class="sr-only"> aplikace <?= h((string)$app['name']) ?></span></a></p>
                  <p><a href="appmarket_form.php?id=<?= (int)$app['id'] ?>">Upravit aplikaci<span class="sr-only"> <?= h((string)$app['name']) ?></span></a></p>
                  <p><a href="appmarket.php?app_id=<?= (int)$app['id'] ?>#appmarket-releases-heading">Historie verzí<span class="sr-only"> aplikace <?= h((string)$app['name']) ?></span></a></p>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php if (is_array($selectedApp)): ?>
    <section class="admin-section" aria-labelledby="appmarket-app-heading">
      <h2 id="appmarket-app-heading"><?= h((string)$selectedApp['name']) ?></h2>
      <div class="admin-actions">
        <a class="button" href="appmarket_release_form.php?app_id=<?= (int)$selectedApp['id'] ?>">Aktualizovat verzi</a>
        <a class="button button-secondary" href="appmarket_form.php?id=<?= (int)$selectedApp['id'] ?>">Upravit aplikaci</a>
        <?php if ((string)$selectedApp['status'] === 'published'): ?>
          <a class="button button-secondary" href="<?= h(appmarketAppPath($selectedApp)) ?>">Veřejný detail</a>
        <?php endif; ?>
      </div>

      <p>Aktualizace vytvoří nové vydání; poslední verze slouží jako předloha. Odkaz Upravit verzi v historii upravuje pouze vybrané ruční vydání.</p>

      <?php if (trim((string)$selectedApp['package_id']) !== ''): ?>
        <details>
          <summary>Pokročilé: kompatibilita s Android update API (volitelné)</summary>
          <p>Android applicationId: <code><?= h((string)$selectedApp['package_id']) ?></code>.</p>
          <p>Původní podepsaná Android vydání používají vlastní kontrolní obrazovku. Jejich zveřejnění vyžaduje superadmina a schválený podpisový certifikát. Ručně spravovaná vydání tyto nástroje nepotřebují.</p>
          <?php if (isSuperAdmin()): ?>
            <div class="admin-actions">
              <a class="button button-secondary" href="appmarket_certificates.php?app_id=<?= (int)$selectedApp['id'] ?>">Podpisové certifikáty</a>
              <a class="button button-secondary" href="appmarket_tokens.php?app_id=<?= (int)$selectedApp['id'] ?>">Publikační tokeny</a>
            </div>
          <?php endif; ?>
        </details>
      <?php endif; ?>

      <h3 id="appmarket-releases-heading" tabindex="-1">Historie verzí</h3>
      <?php if ($releases === []): ?>
        <p>Pro tuto aplikaci zatím není vytvořené žádné vydání. První přidáte přes Aktualizovat verzi.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table aria-labelledby="appmarket-releases-heading">
            <caption>Vydání aplikace <?= h((string)$selectedApp['name']) ?></caption>
            <thead>
              <tr>
                <th scope="col">Verze</th>
                <th scope="col">Stav</th>
                <th scope="col">Platforma a požadavky</th>
                <th scope="col">Soubor</th>
                <th scope="col">Akce</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($releases as $release): ?>
                <?php $isManualRelease = (string)$release['metadata_source'] === 'manual'; ?>
                <tr>
                  <th scope="row">
                    <?= h((string)$release['version_name']) ?>
                    <?php if (!$isManualRelease): ?><small>Android versionCode <?= (int)$release['version_code'] ?></small><?php endif; ?>
                  </th>
                  <td><?= h(appmarketReleaseStatusDefinitions()[(string)$release['status']]) ?></td>
                  <td>
                    <?= h((string)$release['platform_label']) ?>
                    <?php if (trim((string)$release['system_requirements']) !== ''): ?>
                      <small><?= nl2br(h((string)$release['system_requirements'])) ?></small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (trim((string)$release['file_original_name']) !== ''): ?>
                      <?= h((string)$release['file_original_name']) ?>
                      <small><?= h(formatFileSize((int)$release['file_size'])) ?></small>
                    <?php else: ?>
                      <span>Soubor není přiložen</span>
                    <?php endif; ?>
                    <?php if ($isManualRelease): ?>
                      <small>Ručně spravované vydání</small>
                    <?php else: ?>
                      <details>
                        <summary>Původní Android kompatibilita</summary>
                        <p><?= h((string)$release['metadata_source'] === 'apk' ? 'Ověřeno Android nástroji' : 'Ověřeno podepsaným publisherem') ?></p>
                        <p>Podpis: <?= (int)($release['certificate_is_active'] ?? 0) === 1 ? 'Schválený' : 'Vyžaduje schválení' ?>
                          <small><code><?= h(substr((string)$release['certificate_fingerprint_sha256'], 0, 16)) ?>…</code></small>
                        </p>
                        <p><?= h((string)$release['release_channel_label']) ?>
                          <small><?= h((string)$release['rollout_label']) ?></small>
                          <small>ABI: <?= h((string)$release['supported_abis_label']) ?></small>
                        </p>
                        <p><?= h((string)$release['update_priority_label']) ?> aktualizace<?= $release['required_below_version_code'] !== null ? ', povinná pod versionCode ' . (int)$release['required_below_version_code'] : '' ?></p>
                      </details>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($isManualRelease): ?>
                      <?php if ((string)$release['status'] === 'withdrawn'): ?>
                        <span>Stažené vydání nelze upravit</span>
                      <?php elseif ((string)$release['status'] === 'draft' || isSuperAdmin()): ?>
                        <p><a href="appmarket_release_form.php?app_id=<?= (int)$selectedApp['id'] ?>&amp;id=<?= (int)$release['id'] ?>">Upravit verzi <?= h((string)$release['version_name']) ?><span class="sr-only"> pro <?= h((string)$release['platform_label']) ?></span></a></p>
                      <?php else: ?>
                        <span>Zveřejněné vydání spravuje superadmin</span>
                      <?php endif; ?>
                      <?php if (isSuperAdmin() && in_array((string)$release['status'], ['draft', 'published'], true)): ?>
                        <?php
                        $manualAction = (string)$release['status'] === 'draft' ? 'delete' : 'withdraw';
                          $manualActionLabel = $manualAction === 'delete' ? 'Smazat koncept' : 'Stáhnout vydání';
                          $manualConfirmId = 'confirm-manual-release-' . (int)$release['id'];
                          ?>
                        <form method="post" action="appmarket_release_action.php">
                          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                          <input type="hidden" name="release_id" value="<?= (int)$release['id'] ?>">
                          <input type="hidden" name="action" value="<?= h($manualAction) ?>">
                          <fieldset>
                            <legend><?= h($manualActionLabel) ?> verze <?= h((string)$release['version_name']) ?> pro <?= h((string)$release['platform_label']) ?></legend>
                            <label class="admin-checkbox-label" for="<?= h($manualConfirmId) ?>">
                              <input type="checkbox" id="<?= h($manualConfirmId) ?>" name="confirm_action" value="<?= h($manualAction) ?>">
                              <?= $manualAction === 'delete' ? 'Potvrzuji trvalé smazání konceptu a jeho souboru' : 'Potvrzuji stažení vydání z veřejné nabídky' ?>
                            </label>
                          </fieldset>
                          <button type="submit"><?= h($manualActionLabel) ?> verze <?= h((string)$release['version_name']) ?></button>
                        </form>
                      <?php endif; ?>
                    <?php elseif (isSuperAdmin()): ?>
                      <details>
                        <summary>Pokročilé akce Android vydání</summary>
                      <?php if ((string)$release['status'] === 'draft'): ?>
                        <p>
                          <a href="appmarket_release_review.php?release_id=<?= (int)$release['id'] ?>">
                            Zkontrolovat a zveřejnit verzi <?= h((string)$release['version_name']) ?>
                          </a>
                        </p>
                        <form method="post" action="appmarket_release_action.php">
                          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                          <input type="hidden" name="release_id" value="<?= (int)$release['id'] ?>">
                          <input type="hidden" name="action" value="delete">
                          <?php $confirmDeleteId = 'confirm-release-delete-' . (int)$release['id']; ?>
                          <fieldset>
                            <legend>Smazání konceptu verze <?= h((string)$release['version_name']) ?></legend>
                            <label class="admin-checkbox-label" for="<?= h($confirmDeleteId) ?>">
                              <input type="checkbox" id="<?= h($confirmDeleteId) ?>" name="confirm_action" value="delete">
                              Potvrzuji trvalé smazání konceptu
                            </label>
                          </fieldset>
                          <button type="submit">Smazat koncept verze <?= h((string)$release['version_name']) ?></button>
                        </form>
                      <?php elseif ((string)$release['status'] === 'published'): ?>
                        <?php
                          $rolloutId = 'release-rollout-' . (int)$release['id'];
                          $rolloutHelpId = $rolloutId . '-help';
                          $confirmDistributionId = 'confirm-release-distribution-' . (int)$release['id'];
                          ?>
                        <form method="post" action="appmarket_release_action.php">
                          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                          <input type="hidden" name="release_id" value="<?= (int)$release['id'] ?>">
                          <input type="hidden" name="action" value="distribution">
                          <fieldset>
                            <legend>Postupné nasazení verze <?= h((string)$release['version_name']) ?></legend>
                            <label for="<?= h($rolloutId) ?>">Podíl kompatibilních klientů v procentech</label>
                            <input type="number" id="<?= h($rolloutId) ?>" name="rollout_percentage"
                                   min="0" max="100" step="1"
                                   value="<?= (int)$release['rollout_percentage'] ?>"
                                   aria-describedby="<?= h($rolloutHelpId) ?>">
                            <small id="<?= h($rolloutHelpId) ?>" class="field-help">
                              Hodnota 0 pozastaví update API, 100 nabídne aktualizaci všem kohortám.
                            </small>
                          </fieldset>
                          <label class="admin-checkbox-label" for="<?= h($confirmDistributionId) ?>">
                            <input type="checkbox" id="<?= h($confirmDistributionId) ?>"
                                   name="confirm_action" value="distribution">
                            Potvrzuji změnu postupného nasazení
                          </label>
                          <button type="submit">Uložit nasazení</button>
                        </form>
                        <form method="post" action="appmarket_release_action.php">
                          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                          <input type="hidden" name="release_id" value="<?= (int)$release['id'] ?>">
                          <input type="hidden" name="action" value="withdraw">
                          <?php $confirmWithdrawId = 'confirm-release-withdraw-' . (int)$release['id']; ?>
                          <fieldset>
                            <legend>Stažení vydání verze <?= h((string)$release['version_name']) ?></legend>
                            <label class="admin-checkbox-label" for="<?= h($confirmWithdrawId) ?>">
                              <input type="checkbox" id="<?= h($confirmWithdrawId) ?>" name="confirm_action" value="withdraw">
                              Potvrzuji stažení z veřejné nabídky
                            </label>
                          </fieldset>
                          <button type="submit">Stáhnout vydání verze <?= h((string)$release['version_name']) ?></button>
                        </form>
                      <?php else: ?>
                        <span>Bez akce</span>
                      <?php endif; ?>
                      </details>
                    <?php else: ?>
                      <span>Původní Android vydání spravuje superadmin</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

<?php endif; ?>

<?php adminFooter(); ?>
