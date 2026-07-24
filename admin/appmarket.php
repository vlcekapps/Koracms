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
                (SELECT COUNT(*) FROM cms_appmarket_releases r WHERE r.app_id = a.id AND r.status = 'published') AS published_release_count,
                (SELECT MAX(r.version_code) FROM cms_appmarket_releases r WHERE r.app_id = a.id AND r.status = 'published') AS latest_version_code
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
  Správa vlastního katalogu Android aplikací, podepsaných produkčních APK a update API.
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
      <p>Zatím není vytvořená žádná aplikace. Začněte jejím názvem, applicationId a popisem.</p>
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
                  <small><code><?= h((string)$app['package_id']) ?></code></small>
                </th>
                <td><?= h(appmarketAppStatusDefinitions()[appmarketNormalizeAppStatus((string)$app['status'])]) ?></td>
                <td>
                  <?= (int)$app['published_release_count'] ?> zveřejněných /
                  <?= (int)$app['release_count'] ?> celkem
                </td>
                <td><a href="appmarket_form.php?id=<?= (int)$app['id'] ?>">Upravit</a></td>
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
      <p><code><?= h((string)$selectedApp['package_id']) ?></code></p>
      <div class="admin-actions">
        <a class="button" href="appmarket_release_form.php?app_id=<?= (int)$selectedApp['id'] ?>">Nahrát vydání</a>
        <a class="button button-secondary" href="appmarket_form.php?id=<?= (int)$selectedApp['id'] ?>">Upravit aplikaci</a>
        <?php if (isSuperAdmin()): ?>
          <a class="button button-secondary" href="appmarket_certificates.php?app_id=<?= (int)$selectedApp['id'] ?>">Podpisové certifikáty</a>
          <a class="button button-secondary" href="appmarket_tokens.php?app_id=<?= (int)$selectedApp['id'] ?>">Publikační tokeny</a>
        <?php endif; ?>
        <?php if ((string)$selectedApp['status'] === 'published'): ?>
          <a class="button button-secondary" href="<?= h(appmarketAppPath($selectedApp)) ?>">Veřejný detail</a>
        <?php endif; ?>
      </div>

      <h3 id="appmarket-releases-heading">Vydání</h3>
      <?php if ($releases === []): ?>
        <p>Pro tuto aplikaci zatím není nahrané žádné vydání.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table aria-labelledby="appmarket-releases-heading">
            <caption>Vydání aplikace <?= h((string)$selectedApp['name']) ?></caption>
            <thead>
              <tr>
                <th scope="col">Verze</th>
                <th scope="col">Stav</th>
                <th scope="col">Podpis</th>
                <th scope="col">Soubor</th>
                <th scope="col">Akce</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($releases as $release): ?>
                <tr>
                  <th scope="row">
                    <?= h((string)$release['version_name']) ?>
                    <small>versionCode <?= (int)$release['version_code'] ?></small>
                  </th>
                  <td><?= h(appmarketReleaseStatusDefinitions()[(string)$release['status']]) ?></td>
                  <td>
                    <?= (int)($release['certificate_is_active'] ?? 0) === 1 ? 'Schválený' : 'Vyžaduje schválení' ?>
                    <small><code><?= h(substr((string)$release['certificate_fingerprint_sha256'], 0, 16)) ?>…</code></small>
                  </td>
                  <td>
                    <?= h(formatFileSize((int)$release['apk_size'])) ?>
                    <small><?= h((string)$release['metadata_source'] === 'apk' ? 'ověřeno Android nástroji' : 'publisher manifest') ?></small>
                  </td>
                  <td>
                    <?php if (isSuperAdmin()): ?>
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
                          <label class="admin-checkbox-label" for="<?= h($confirmDeleteId) ?>">
                            <input type="checkbox" id="<?= h($confirmDeleteId) ?>" name="confirm_action" value="delete">
                            Potvrzuji trvalé smazání konceptu
                          </label>
                          <button type="submit">Smazat koncept verze <?= h((string)$release['version_name']) ?></button>
                        </form>
                      <?php elseif ((string)$release['status'] === 'published'): ?>
                        <form method="post" action="appmarket_release_action.php">
                          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                          <input type="hidden" name="release_id" value="<?= (int)$release['id'] ?>">
                          <input type="hidden" name="action" value="withdraw">
                          <?php $confirmWithdrawId = 'confirm-release-withdraw-' . (int)$release['id']; ?>
                          <label class="admin-checkbox-label" for="<?= h($confirmWithdrawId) ?>">
                            <input type="checkbox" id="<?= h($confirmWithdrawId) ?>" name="confirm_action" value="withdraw">
                            Potvrzuji stažení z veřejné nabídky
                          </label>
                          <button type="submit">Stáhnout vydání verze <?= h((string)$release['version_name']) ?></button>
                        </form>
                      <?php else: ?>
                        <span>Bez akce</span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span>Zveřejňuje superadmin</span>
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

  <section class="admin-section" aria-labelledby="appmarket-foundation-heading">
    <h2 id="appmarket-foundation-heading">Bezpečnost publikačního toku</h2>
    <p>
      APK se ukládají mimo veřejný webroot pod názvem odvozeným ze SHA-256. Publikační token
      smí pouze založit koncept; zveřejnění vyžaduje superadmina a schválený podpisový certifikát.
    </p>
  </section>
<?php endif; ?>

<?php adminFooter(); ?>
