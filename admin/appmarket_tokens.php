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
$nameInput = '';
$expiresAtInput = '';
$publicKeyInput = '';
$nameError = '';
$expiresAtError = '';
$publicKeyError = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'create') {
        $nameInput = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 150);
        $expiresAtInput = trim((string)($_POST['expires_at'] ?? ''));
        $publicKeyInput = trim((string)($_POST['attestation_public_key'] ?? ''));
        $expiresAt = appmarketNormalizeDateTime($expiresAtInput);
        $publicKey = appmarketNormalizeAttestationPublicKey($publicKeyInput);
        $keyFingerprint = appmarketAttestationKeyFingerprint($publicKey);
        if ($nameInput === '') {
            $error = 'Doplňte název tokenu, například MiniRec production publisher.';
            $nameError = $error;
        } elseif ($expiresAtInput !== '' && $expiresAt === null) {
            $error = 'Zadejte platné datum a čas expirace.';
            $expiresAtError = $error;
        } elseif (!appmarketAttestationOpenSslAvailable()) {
            $error = 'Server nemá PHP rozšíření OpenSSL potřebné k ověření publisher podpisů.';
            $publicKeyError = $error;
        } elseif ($publicKey === '' || $keyFingerprint === '') {
            $error = 'Vložte platný veřejný RSA klíč o velikosti alespoň 3072 bitů.';
            $publicKeyError = $error;
        } else {
            $token = appmarketGeneratePublishToken();
            $pdo->prepare(
                "INSERT INTO cms_appmarket_publish_tokens
                 (app_id, name, token_prefix, token_hash, scopes, attestation_algorithm,
                  attestation_public_key, attestation_key_fingerprint, expires_at, created_by_user_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                (int)$app['id'],
                $nameInput,
                $token['prefix'],
                $token['hash'],
                appmarketTokenScopesValue('release:create'),
                appmarketAttestationAlgorithm(),
                $publicKey,
                $keyFingerprint,
                $expiresAt,
                currentUserId(),
            ]);
            $_SESSION['appmarket_new_token'] = $token['token'];
            header('Location: appmarket_tokens.php?app_id=' . (int)$app['id']);
            exit;
        }
    } elseif ($action === 'revoke') {
        $tokenId = inputInt('post', 'token_id');
        if ($tokenId !== null && isset($_POST['confirm_revoke'])) {
            $pdo->prepare(
                'UPDATE cms_appmarket_publish_tokens SET revoked_at = NOW() WHERE id = ? AND app_id = ? AND revoked_at IS NULL'
            )->execute([$tokenId, (int)$app['id']]);
            header('Location: appmarket_tokens.php?app_id=' . (int)$app['id']);
            exit;
        }
        $error = 'Před odvoláním tokenu potvrďte, že jej už publisher ani CI nemají používat.';
    }
}

$newToken = trim((string)($_SESSION['appmarket_new_token'] ?? ''));
unset($_SESSION['appmarket_new_token']);
$stmt = $pdo->prepare(
    "SELECT *
     FROM cms_appmarket_publish_tokens
     WHERE app_id = ?
     ORDER BY revoked_at IS NULL DESC, created_at DESC, id DESC"
);
$stmt->execute([(int)$app['id']]);
$tokens = $stmt->fetchAll();
$legacyTokenExists = false;
foreach ($tokens as $existingToken) {
    if (appmarketNormalizeSha256((string)($existingToken['attestation_key_fingerprint'] ?? '')) === '') {
        $legacyTokenExists = true;
        break;
    }
}

adminHeader('Publikační tokeny');
?>
<p><a href="appmarket.php?app_id=<?= (int)$app['id'] ?>"><span aria-hidden="true">←</span> Zpět na aplikaci <?= h((string)$app['name']) ?></a></p>
<p class="admin-description">Token smí pouze nahrát nové vydání jako koncept. Je svázaný s veřejným publisher klíčem; odpovídající privátní klíč zůstává pouze na důvěryhodném lokálním počítači.</p>
<?php if ($legacyTokenExists): ?>
  <p class="warning" role="status">
    Starší token bez ověřovacího klíče už nové vydání nepřijme. Vygenerujte publisher klíč,
    vytvořte nový token a starý token potom odvolejte.
  </p>
<?php endif; ?>

<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($newToken !== ''): ?>
  <section class="admin-section" aria-labelledby="appmarket-new-token-heading">
    <h2 id="appmarket-new-token-heading">Nový token, zobrazí se jen jednou</h2>
    <p class="success" role="status">Token byl vytvořen. Uložte jej do lokálního správce tajemství nebo proměnné prostředí.</p>
    <p><code class="break-long-token"><?= h($newToken) ?></code></p>
  </section>
<?php endif; ?>

<section class="admin-section" aria-labelledby="appmarket-token-create-heading">
  <h2 id="appmarket-token-create-heading">Vytvořit token</h2>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
    <input type="hidden" name="action" value="create">
    <fieldset>
      <legend>Identifikace, ověřovací klíč a platnost</legend>
      <label for="token-name">Název <span aria-hidden="true">*</span></label>
      <input type="text" id="token-name" name="name" required aria-required="true" maxlength="150"
             value="<?= h($nameInput) ?>"
             aria-describedby="appmarket-token-name-help<?= $nameError !== '' ? ' appmarket-token-name-error' : '' ?>"
             <?= $nameError !== '' ? 'aria-invalid="true"' : '' ?>>
      <small id="appmarket-token-name-help" class="field-help">Použijte název aplikace, zařízení nebo CI prostředí, abyste později poznali účel tokenu.</small>
      <?php if ($nameError !== ''): ?>
        <small id="appmarket-token-name-error" class="field-help field-error"><?= h($nameError) ?></small>
      <?php endif; ?>

      <label for="token-attestation-public-key">Veřejný publisher klíč <span aria-hidden="true">*</span></label>
      <textarea id="token-attestation-public-key" name="attestation_public_key" rows="8" required
                aria-required="true" maxlength="<?= appmarketAttestationPublicKeyMaxBytes() ?>"
                aria-describedby="appmarket-token-public-key-help<?= $publicKeyError !== '' ? ' appmarket-token-public-key-error' : '' ?>"
                <?= $publicKeyError !== '' ? 'aria-invalid="true"' : '' ?>><?= h($publicKeyInput) ?></textarea>
      <small id="appmarket-token-public-key-help" class="field-help">
        Vygenerujte samostatný klíč příkazem
        <code>php tools/appmarket-attest.php generate publisher-private.pem publisher-public.pem</code>
        mimo repozitář aplikace a vložte obsah souboru <code>publisher-public.pem</code>.
        Privátní klíč ani Android podpisový klíč do CMS nikdy nevkládejte.
      </small>
      <?php if ($publicKeyError !== ''): ?>
        <small id="appmarket-token-public-key-error" class="field-help field-error"><?= h($publicKeyError) ?></small>
      <?php endif; ?>

      <label for="token-expires-at">Expirace</label>
      <input type="datetime-local" id="token-expires-at" name="expires_at" value="<?= h($expiresAtInput) ?>"
             aria-describedby="appmarket-token-expiry-help<?= $expiresAtError !== '' ? ' appmarket-token-expiry-error' : '' ?>"
             <?= $expiresAtError !== '' ? 'aria-invalid="true"' : '' ?>>
      <small id="appmarket-token-expiry-help" class="field-help">Doporučená je omezená platnost. Prázdná hodnota znamená token bez automatické expirace.</small>
      <?php if ($expiresAtError !== ''): ?>
        <small id="appmarket-token-expiry-error" class="field-help field-error"><?= h($expiresAtError) ?></small>
      <?php endif; ?>
    </fieldset>
    <button type="submit">Vytvořit token</button>
  </form>
</section>

<section class="admin-section" aria-labelledby="appmarket-tokens-heading">
  <h2 id="appmarket-tokens-heading">Existující tokeny</h2>
  <?php if ($tokens === []): ?>
    <p>Zatím nebyl vytvořen žádný publikační token.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <caption>Publikační tokeny aplikace <?= h((string)$app['name']) ?></caption>
        <thead><tr><th scope="col">Název</th><th scope="col">Prefix</th><th scope="col">Ověřovací klíč</th><th scope="col">Platnost</th><th scope="col">Poslední použití</th><th scope="col">Akce</th></tr></thead>
        <tbody>
          <?php foreach ($tokens as $token): ?>
            <?php
              $revoked = trim((string)($token['revoked_at'] ?? '')) !== '';
              $expired = !$revoked
                  && trim((string)($token['expires_at'] ?? '')) !== ''
                  && strtotime((string)$token['expires_at']) <= time();
              ?>
            <tr>
              <td><?= h((string)$token['name']) ?></td>
              <td><code><?= h((string)$token['token_prefix']) ?>…</code></td>
              <td>
                <?php if (appmarketNormalizeSha256((string)($token['attestation_key_fingerprint'] ?? '')) !== ''): ?>
                  <span>RSA-SHA256</span><br>
                  <code class="break-long-token"><?= h((string)$token['attestation_key_fingerprint']) ?></code>
                <?php else: ?>
                  <span>Chybí, token nelze použít pro nový upload</span>
                <?php endif; ?>
              </td>
              <td><?= $revoked ? 'Odvolaný' : ($expired ? 'Expirovaný' : (trim((string)$token['expires_at']) !== '' ? formatCzechDate((string)$token['expires_at']) : 'Bez expirace')) ?></td>
              <td><?= trim((string)$token['last_used_at']) !== '' ? formatCzechDate((string)$token['last_used_at']) : 'Dosud nepoužit' ?></td>
              <td>
                <?php if (!$revoked): ?>
                  <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
                    <input type="hidden" name="token_id" value="<?= (int)$token['id'] ?>">
                    <input type="hidden" name="action" value="revoke">
                    <?php $confirmId = 'confirm-token-revoke-' . (int)$token['id']; ?>
                    <label class="admin-checkbox-label" for="<?= h($confirmId) ?>">
                      <input type="checkbox" id="<?= h($confirmId) ?>" name="confirm_revoke" value="1">
                      Potvrzuji odvolání tokenu
                    </label>
                    <button type="submit">Odvolat token <?= h((string)$token['name']) ?></button>
                  </form>
                <?php else: ?>
                  <span>Bez akce</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php adminFooter(); ?>
