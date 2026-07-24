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
$publicationIssues = appmarketReleasePublicationIssues($release);
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
    <div><dt>Velikost APK</dt><dd><?= h(formatFileSize((int)$release['apk_size'])) ?></dd></div>
    <div><dt>SHA-256 APK</dt><dd><code class="break-long-token"><?= h((string)$release['apk_sha256']) ?></code></dd></div>
    <div><dt>SHA-256 certifikátu</dt><dd><code class="break-long-token"><?= h((string)$release['certificate_fingerprint_sha256']) ?></code></dd></div>
    <div>
      <dt>Zdroj metadat</dt>
      <dd>
        <?= (string)$release['metadata_source'] === 'apk'
            && !empty($analysis['tool_verified'])
              ? 'Nezávisle ověřeno serverovými Android nástroji'
              : 'Neověřený zdroj, publikace je blokovaná' ?>
      </dd>
    </div>
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
    <form method="post" action="appmarket_release_action.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="release_id" value="<?= (int)$release['id'] ?>">
      <input type="hidden" name="action" value="publish">
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
