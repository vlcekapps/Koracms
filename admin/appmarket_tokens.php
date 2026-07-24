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
$nameError = '';
$expiresAtError = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'create') {
        $nameInput = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 150);
        $expiresAtInput = trim((string)($_POST['expires_at'] ?? ''));
        $expiresAt = appmarketNormalizeDateTime($expiresAtInput);
        if ($nameInput === '') {
            $error = 'Doplňte název tokenu, například MiniRec production publisher.';
            $nameError = $error;
        } elseif ($expiresAtInput !== '' && $expiresAt === null) {
            $error = 'Zadejte platné datum a čas expirace.';
            $expiresAtError = $error;
        } else {
            $token = appmarketGeneratePublishToken();
            $pdo->prepare(
                "INSERT INTO cms_appmarket_publish_tokens
                 (app_id, name, token_prefix, token_hash, scopes, expires_at, created_by_user_id)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([
                (int)$app['id'],
                $nameInput,
                $token['prefix'],
                $token['hash'],
                appmarketTokenScopesValue('release:create'),
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

adminHeader('Publikační tokeny');
?>
<p><a href="appmarket.php?app_id=<?= (int)$app['id'] ?>"><span aria-hidden="true">←</span> Zpět na aplikaci <?= h((string)$app['name']) ?></a></p>
<p class="admin-description">Token smí pouze nahrát nové vydání jako koncept. Nemůže vydání zveřejnit ani získat podpisový klíč.</p>

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
      <legend>Identifikace a platnost</legend>
      <label for="token-name">Název <span aria-hidden="true">*</span></label>
      <input type="text" id="token-name" name="name" required aria-required="true" maxlength="150"
             value="<?= h($nameInput) ?>"
             aria-describedby="appmarket-token-name-help<?= $nameError !== '' ? ' appmarket-token-name-error' : '' ?>"
             <?= $nameError !== '' ? 'aria-invalid="true"' : '' ?>>
      <small id="appmarket-token-name-help" class="field-help">Použijte název aplikace, zařízení nebo CI prostředí, abyste později poznali účel tokenu.</small>
      <?php if ($nameError !== ''): ?>
        <small id="appmarket-token-name-error" class="field-help field-error"><?= h($nameError) ?></small>
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
        <thead><tr><th scope="col">Název</th><th scope="col">Prefix</th><th scope="col">Platnost</th><th scope="col">Poslední použití</th><th scope="col">Akce</th></tr></thead>
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
