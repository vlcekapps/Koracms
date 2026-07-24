<?php

require_once __DIR__ . '/layout.php';
requireSuperAdmin();
requireModuleEnabled('appmarket');

$pdo = db_connect();
$appId = inputInt('get', 'app_id') ?? inputInt('post', 'app_id');
$app = $appId !== null ? appmarketFindApp($pdo, $appId) : null;
if ($app === null) {
    header('Location: appmarket.php');
    exit;
}

$error = '';
$notice = trim((string)($_SESSION['appmarket_certificate_notice'] ?? ''));
unset($_SESSION['appmarket_certificate_notice']);
$fingerprintInput = '';
$notesInput = '';
$activeInput = false;
$fingerprintError = '';
$redirectAfterSuccess = static function (string $message) use ($app): void {
    $_SESSION['appmarket_certificate_notice'] = $message;
    header('Location: ' . BASE_URL . '/admin/appmarket_certificates.php?app_id=' . (int)$app['id']);
    exit;
};
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'add') {
        $fingerprintInput = trim((string)($_POST['fingerprint'] ?? ''));
        $notesInput = trim((string)($_POST['notes'] ?? ''));
        $activeInput = isset($_POST['is_active']);
        $fingerprint = appmarketNormalizeCertificateFingerprint($fingerprintInput);
        if ($fingerprint === '') {
            $error = 'SHA-256 otisk musí mít 64 hexadecimálních znaků.';
            $fingerprintError = $error;
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO cms_appmarket_certificates
                     (app_id, fingerprint_sha256, is_active, notes, created_by_user_id)
                     VALUES (?,?,?, ?, ?)"
                )->execute([
                    (int)$app['id'],
                    $fingerprint,
                    $activeInput ? 1 : 0,
                    $notesInput,
                    currentUserId(),
                ]);
                $redirectAfterSuccess('Podpisový certifikát byl uložen.');
            } catch (PDOException $e) {
                $error = 'Tento certifikát už je u aplikace uložený.';
                $fingerprintError = $error;
            }
        }
    } elseif ($action === 'toggle') {
        $certificateId = inputInt('post', 'certificate_id');
        if ($certificateId === null) {
            $error = 'Certifikát nebyl nalezen.';
        } else {
            $certificateStmt = $pdo->prepare(
                'SELECT is_active FROM cms_appmarket_certificates WHERE id = ? AND app_id = ? LIMIT 1'
            );
            $certificateStmt->execute([$certificateId, (int)$app['id']]);
            $certificateIsActive = $certificateStmt->fetchColumn();
            if ($certificateIsActive === false) {
                $error = 'Certifikát nebyl nalezen.';
            } elseif ((int)$certificateIsActive === 1 && !isset($_POST['confirm_deactivate'])) {
                $error = 'Před zneplatněním certifikátu potvrďte tuto nevratnou bezpečnostní změnu.';
            } elseif ((int)$certificateIsActive === 1) {
                try {
                    $pdo->beginTransaction();
                    $withdrawStmt = $pdo->prepare(
                        "UPDATE cms_appmarket_releases
                         SET status = 'withdrawn'
                         WHERE certificate_id = ? AND app_id = ? AND status = 'published'"
                    );
                    $withdrawStmt->execute([$certificateId, (int)$app['id']]);
                    $withdrawnCount = $withdrawStmt->rowCount();
                    $pdo->prepare(
                        'UPDATE cms_appmarket_certificates SET is_active = 0 WHERE id = ? AND app_id = ?'
                    )->execute([$certificateId, (int)$app['id']]);
                    $pdo->prepare(
                        "UPDATE cms_appmarket_apps a
                         SET status = 'draft'
                         WHERE a.id = ?
                           AND a.status = 'published'
                           AND NOT EXISTS (
                             SELECT 1 FROM cms_appmarket_releases r
                             WHERE r.app_id = a.id AND r.status = 'published'
                           )"
                    )->execute([(int)$app['id']]);
                    $pdo->commit();
                    $redirectAfterSuccess(
                        'Certifikát byl zneplatněn a '
                        . $withdrawnCount . ' zveřejněných vydání bylo staženo z veřejné nabídky.'
                    );
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    koraLog('error', 'appmarket certificate revocation failed', [
                        'app_id' => (int)$app['id'],
                        'certificate_id' => $certificateId,
                        'exception' => $e,
                    ]);
                    $error = 'Certifikát se nepodařilo bezpečně zneplatnit.';
                }
            } else {
                $pdo->prepare(
                    'UPDATE cms_appmarket_certificates SET is_active = 1 WHERE id = ? AND app_id = ?'
                )->execute([$certificateId, (int)$app['id']]);
                $redirectAfterSuccess('Certifikát byl schválen pro budoucí zveřejňování.');
            }
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT c.*,
            (SELECT COUNT(*) FROM cms_appmarket_releases r WHERE r.certificate_id = c.id) AS release_count,
            (SELECT COUNT(*) FROM cms_appmarket_releases r
             WHERE r.certificate_id = c.id AND r.status = 'published') AS published_release_count
     FROM cms_appmarket_certificates c
     WHERE c.app_id = ?
     ORDER BY c.is_active DESC, c.created_at DESC, c.id DESC"
);
$stmt->execute([(int)$app['id']]);
$certificates = $stmt->fetchAll();

adminHeader('Podpisové certifikáty');
?>
<p><a href="appmarket.php?app_id=<?= (int)$app['id'] ?>"><span aria-hidden="true">←</span> Zpět na aplikaci <?= h((string)$app['name']) ?></a></p>
<p class="admin-description">Schválený certifikát je podmínkou zveřejnění. Soukromý podpisový klíč se do Kora CMS nikdy nenahrává.</p>

<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($notice !== ''): ?><p class="success" role="status"><?= h($notice) ?></p><?php endif; ?>

<section class="admin-section" aria-labelledby="appmarket-certificates-heading">
  <h2 id="appmarket-certificates-heading">Certifikáty aplikace</h2>
  <?php if ($certificates === []): ?>
    <p>Žádný certifikát zatím není uložený. Při prvním nahrání APK se zjištěný certifikát založí jako neschválený.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <caption>Podpisové certifikáty aplikace <?= h((string)$app['name']) ?></caption>
        <thead><tr><th scope="col">SHA-256</th><th scope="col">Stav</th><th scope="col">Vydání</th><th scope="col">Akce</th></tr></thead>
        <tbody>
          <?php foreach ($certificates as $certificate): ?>
            <tr>
              <td><code><?= h((string)$certificate['fingerprint_sha256']) ?></code></td>
              <td><?= (int)$certificate['is_active'] === 1 ? 'Schválený' : 'Neschválený' ?></td>
              <td><?= (int)$certificate['release_count'] ?></td>
              <td>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                  <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
                  <input type="hidden" name="certificate_id" value="<?= (int)$certificate['id'] ?>">
                  <input type="hidden" name="action" value="toggle">
                  <?php if ((int)$certificate['is_active'] === 1): ?>
                    <?php $confirmId = 'confirm-certificate-deactivate-' . (int)$certificate['id']; ?>
                    <label class="admin-checkbox-label" for="<?= h($confirmId) ?>">
                      <input type="checkbox" id="<?= h($confirmId) ?>" name="confirm_deactivate" value="1">
                      Potvrzuji zneplatnění a stažení
                      <?= (int)$certificate['published_release_count'] ?> zveřejněných vydání
                    </label>
                  <?php endif; ?>
                  <button type="submit">
                    <?= (int)$certificate['is_active'] === 1 ? 'Zneplatnit certifikát' : 'Schválit certifikát' ?>
                    <?= h(substr((string)$certificate['fingerprint_sha256'], 0, 12)) ?>…
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="admin-section" aria-labelledby="appmarket-certificate-add-heading">
  <h2 id="appmarket-certificate-add-heading">Přidat známý certifikát</h2>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
    <input type="hidden" name="action" value="add">
    <fieldset>
      <legend>Otisk a stav</legend>
      <label for="fingerprint">SHA-256 otisk <span aria-hidden="true">*</span></label>
      <input type="text" id="fingerprint" name="fingerprint" required aria-required="true" maxlength="95"
             value="<?= h($fingerprintInput) ?>"
             aria-describedby="appmarket-fingerprint-help<?= $fingerprintError !== '' ? ' appmarket-fingerprint-error' : '' ?>"
             <?= $fingerprintError !== '' ? 'aria-invalid="true"' : '' ?>>
      <small id="appmarket-fingerprint-help" class="field-help">Lze vložit souvisle i s dvojtečkami. Před schválením porovnejte otisk s důvěryhodným výstupem <code>apksigner</code>.</small>
      <?php if ($fingerprintError !== ''): ?>
        <small id="appmarket-fingerprint-error" class="field-help field-error"><?= h($fingerprintError) ?></small>
      <?php endif; ?>

      <label for="notes">Interní poznámka</label>
      <textarea id="notes" name="notes" rows="4"><?= h($notesInput) ?></textarea>

      <label class="admin-checkbox-label" for="certificate-active">
        <input type="checkbox" id="certificate-active" name="is_active" value="1"<?= $activeInput ? ' checked' : '' ?>>
        Ihned schválit pro zveřejňování
      </label>
    </fieldset>
    <button type="submit">Uložit certifikát</button>
  </form>
</section>

<?php adminFooter(); ?>
