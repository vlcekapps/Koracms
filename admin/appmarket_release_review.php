<?php

require_once __DIR__ . '/layout.php';
requireSuperAdmin();
requireModuleEnabled('appmarket');
requireReadOnlyHttpMethod();

$pdo = db_connect();
$releaseId = inputInt('get', 'release_id');
$release = $releaseId !== null ? appmarketFindRelease($pdo, $releaseId) : null;
if ($release === null) {
    http_response_code(404);
    exit('Vydání nebylo nalezeno.');
}

$analysis = json_decode((string)($release['analysis_json'] ?? ''), true);
$analysis = is_array($analysis) ? $analysis : [];
$permissions = json_decode((string)($release['permissions_json'] ?? ''), true);
$permissions = is_array($permissions)
    ? array_values(array_filter(array_map('strval', $permissions), static fn (string $value): bool => $value !== ''))
    : [];
$previousRelease = appmarketPreviousPublishedRelease(
    $pdo,
    (int)$release['app_id'],
    (int)$release['version_code']
);
$permissionDiff = appmarketPermissionDiff($previousRelease, $release);
$policyFlash = is_array($_SESSION['appmarket_policy_flash'] ?? null)
    ? $_SESSION['appmarket_policy_flash']
    : [];
unset($_SESSION['appmarket_policy_flash']);
$selectedPriority = appmarketNormalizeUpdatePriority(
    (string)($policyFlash['update_priority'] ?? $release['update_priority'] ?? 'normal')
);
$selectedRequiredBelow = trim((string)(
    $policyFlash['required_below_version_code']
        ?? $release['required_below_version_code']
        ?? ''
));
$policyErrors = is_array($policyFlash['errors'] ?? null)
    ? array_values(array_map('strval', $policyFlash['errors']))
    : [];
$priorityError = trim((string)($policyFlash['priority_error'] ?? ''));
$requiredBelowError = trim((string)($policyFlash['required_below_error'] ?? ''));
$publicationIssues = appmarketReleasePublicationIssues($pdo, $release);
$noticeError = trim((string)($_SESSION['appmarket_notice_error'] ?? ''));
unset($_SESSION['appmarket_notice_error']);

adminHeader('Kontrola vydání Appmarketu');
?>
<p>
  <a href="appmarket.php?app_id=<?= (int)$release['app_id'] ?>">
    <span aria-hidden="true">←</span> Zpět na aplikaci <?= h((string)$release['app_name']) ?>
  </a>
</p>
<p class="admin-description">
  Před zveřejněním porovnejte identitu balíčku, podpis, kontrolní součet, oprávnění a seznam změn.
</p>

<?php if ($noticeError !== ''): ?><p class="error" role="alert"><?= h($noticeError) ?></p><?php endif; ?>

<section class="admin-section" aria-labelledby="appmarket-release-review-heading">
  <h2 id="appmarket-release-review-heading">
    <?= h((string)$release['app_name']) ?> <?= h((string)$release['version_name']) ?>
  </h2>
  <dl class="info-list">
    <div><dt>Stav</dt><dd><?= h(appmarketReleaseStatusDefinitions()[(string)$release['status']]) ?></dd></div>
    <div><dt>ApplicationId</dt><dd><code><?= h((string)$release['package_id_snapshot']) ?></code></dd></div>
    <div><dt>VersionName</dt><dd><?= h((string)$release['version_name']) ?></dd></div>
    <div><dt>VersionCode</dt><dd><?= (int)$release['version_code'] ?></dd></div>
    <div><dt>Naléhavost aktualizace</dt><dd><?= h((string)$release['update_priority_label']) ?></dd></div>
    <?php if ($release['required_below_version_code'] !== null): ?>
      <div>
        <dt>Povinná pod versionCode</dt>
        <dd><?= (int)$release['required_below_version_code'] ?></dd>
      </div>
    <?php endif; ?>
    <div><dt>Velikost APK</dt><dd><?= h(formatFileSize((int)$release['apk_size'])) ?></dd></div>
    <div><dt>SHA-256 APK</dt><dd><code class="break-long-token"><?= h((string)$release['apk_sha256']) ?></code></dd></div>
    <div><dt>SHA-256 certifikátu</dt><dd><code class="break-long-token"><?= h((string)$release['certificate_fingerprint_sha256']) ?></code></dd></div>
    <div>
      <dt>Zdroj metadat</dt>
      <dd>
        <?php if ((string)$release['metadata_source'] === 'apk' && !empty($analysis['tool_verified'])): ?>
          Nezávisle ověřeno serverovými Android nástroji
        <?php elseif ((string)$release['metadata_source'] === 'publisher_attestation' && !empty($analysis['attestation_verified'])): ?>
          Ověřeno kryptograficky podepsaným lokálním publisherem
        <?php else: ?>
          Neověřený zdroj, publikace je blokovaná
        <?php endif; ?>
      </dd>
    </div>
    <?php if ((string)$release['metadata_source'] === 'publisher_attestation'): ?>
      <div>
        <dt>Publisher klíč</dt>
        <dd><code class="break-long-token"><?= h((string)($analysis['attestation_key_fingerprint'] ?? 'neuveden')) ?></code></dd>
      </div>
    <?php endif; ?>
    <?php if ($release['min_sdk'] !== null): ?><div><dt>Minimální SDK</dt><dd><?= (int)$release['min_sdk'] ?></dd></div><?php endif; ?>
    <?php if ($release['target_sdk'] !== null): ?><div><dt>Cílové SDK</dt><dd><?= (int)$release['target_sdk'] ?></dd></div><?php endif; ?>
  </dl>
</section>

<section class="admin-section" aria-labelledby="appmarket-release-review-notes-heading">
  <h2 id="appmarket-release-review-notes-heading">Seznam změn</h2>
  <?php if (trim((string)$release['release_notes']) === ''): ?>
    <p>Seznam změn nebyl doplněn.</p>
  <?php else: ?>
    <div class="prose"><?= renderProjectMarkdown((string)$release['release_notes']) ?></div>
  <?php endif; ?>
</section>

<section class="admin-section" aria-labelledby="appmarket-release-review-permissions-heading">
  <h2 id="appmarket-release-review-permissions-heading">Oprávnění APK</h2>
  <?php if ($permissions === []): ?>
    <p>APK nedeklaruje žádné oprávnění, nebo je analýza nevrátila.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($permissions as $permission): ?>
        <li><code><?= h($permission) ?></code></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="admin-section" aria-labelledby="appmarket-release-review-delta-heading">
  <h2 id="appmarket-release-review-delta-heading">Změny proti předchozímu vydání</h2>
  <?php if ($previousRelease === null): ?>
    <p>Jde o první veřejné vydání. Oprávnění a SDK proto nemají s čím být porovnány.</p>
  <?php else: ?>
    <p>
      Porovnává se s verzí
      <strong><?= h((string)$previousRelease['version_name']) ?></strong>
      (versionCode <?= (int)$previousRelease['version_code'] ?>).
    </p>
    <?php if ($permissionDiff['added'] === [] && $permissionDiff['removed'] === []): ?>
      <p class="success">Deklarovaná oprávnění se nezměnila.</p>
    <?php else: ?>
      <?php if ($permissionDiff['added'] !== []): ?>
        <h3>Nově přidaná oprávnění</h3>
        <ul>
          <?php foreach ($permissionDiff['added'] as $permission): ?>
            <li><code><?= h($permission) ?></code></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($permissionDiff['removed'] !== []): ?>
        <h3>Odebraná oprávnění</h3>
        <ul>
          <?php foreach ($permissionDiff['removed'] as $permission): ?>
            <li><code><?= h($permission) ?></code></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
    <dl class="info-list">
      <div>
        <dt>Změna minimálního SDK</dt>
        <dd>
          <?= $previousRelease['min_sdk'] !== null ? (int)$previousRelease['min_sdk'] : 'neuvedeno' ?>
          <span aria-hidden="true">→</span>
          <span class="sr-only">na</span>
          <?= $release['min_sdk'] !== null ? (int)$release['min_sdk'] : 'neuvedeno' ?>
        </dd>
      </div>
      <div>
        <dt>Podpisový certifikát</dt>
        <dd>
          <?= hash_equals(
              (string)$previousRelease['certificate_fingerprint_sha256'],
              (string)$release['certificate_fingerprint_sha256']
          ) ? 'Beze změny' : 'Změněn, vyžaduje zvláštní kontrolu' ?>
        </dd>
      </div>
    </dl>
  <?php endif; ?>
</section>

<section class="admin-section" aria-labelledby="appmarket-release-review-result-heading">
  <h2 id="appmarket-release-review-result-heading">Výsledek bezpečnostní kontroly</h2>
  <?php if ($publicationIssues !== []): ?>
    <div class="error" role="alert">
      <p><strong>Vydání zatím nelze zveřejnit.</strong></p>
      <ul>
        <?php foreach ($publicationIssues as $issue): ?><li><?= h($issue) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php elseif ((string)$release['status'] === 'draft'): ?>
    <p class="success" role="status">Kontroly jsou v pořádku. Publikace zpřístupní APK v katalogu i update API.</p>
    <?php if ($policyErrors !== []): ?>
      <div class="error" role="alert" id="appmarket-policy-errors">
        <p><strong>Politiku aktualizace se nepodařilo uložit.</strong></p>
        <ul>
          <?php foreach ($policyErrors as $policyError): ?><li><?= h($policyError) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <form method="post" action="appmarket_release_action.php" novalidate
          <?= $policyErrors !== [] ? 'aria-describedby="appmarket-policy-errors"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="release_id" value="<?= (int)$release['id'] ?>">
      <input type="hidden" name="action" value="publish">
      <fieldset>
        <legend>Politika aktualizace</legend>

        <label for="update_priority">Naléhavost</label>
        <select id="update_priority" name="update_priority"
                aria-describedby="appmarket-update-priority-help<?= $priorityError !== '' ? ' appmarket-update-priority-error' : '' ?>"
                <?= $priorityError !== '' ? 'aria-invalid="true"' : '' ?>>
          <?php foreach (appmarketUpdatePriorityDefinitions() as $priorityKey => $priorityLabel): ?>
            <option value="<?= h($priorityKey) ?>"<?= $selectedPriority === $priorityKey ? ' selected' : '' ?>>
              <?= h($priorityLabel) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small id="appmarket-update-priority-help" class="field-help">
          Naléhavost je informativní údaj pro aplikaci a veřejný detail vydání.
        </small>
        <?php if ($priorityError !== ''): ?>
          <small id="appmarket-update-priority-error" class="field-help field-error"><?= h($priorityError) ?></small>
        <?php endif; ?>

        <label for="required_below_version_code">Povinná aktualizace pod versionCode, volitelné</label>
        <input type="number" id="required_below_version_code" name="required_below_version_code"
               min="1" max="<?= (int)$release['version_code'] ?>"
               value="<?= h($selectedRequiredBelow) ?>"
               aria-describedby="appmarket-required-version-help<?= $requiredBelowError !== '' ? ' appmarket-required-version-error' : '' ?>"
               <?= $requiredBelowError !== '' ? 'aria-invalid="true"' : '' ?>>
        <small id="appmarket-required-version-help" class="field-help">
          Klienti se starším versionCode dostanou v API V2 příznak povinné aktualizace.
          Hodnota rovná nové verzi označí aktualizaci jako povinnou pro všechny starší verze.
        </small>
        <?php if ($requiredBelowError !== ''): ?>
          <small id="appmarket-required-version-error" class="field-help field-error">
            <?= h($requiredBelowError) ?>
          </small>
        <?php endif; ?>
      </fieldset>
      <?php $confirmId = 'confirm-release-publish-' . (int)$release['id']; ?>
      <label class="admin-checkbox-label" for="<?= h($confirmId) ?>">
        <input type="checkbox" id="<?= h($confirmId) ?>" name="confirm_action" value="publish">
        Zkontroloval jsem identitu, podpis, oprávnění i seznam změn a potvrzuji zveřejnění
      </label>
      <button type="submit">Zveřejnit verzi <?= h((string)$release['version_name']) ?></button>
    </form>
  <?php else: ?>
    <p>Vydání už není koncept a nelze jej touto akcí zveřejnit.</p>
  <?php endif; ?>
</section>

<?php adminFooter(); ?>
