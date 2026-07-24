<?php
$latestRelease = is_array($latestRelease ?? null) ? $latestRelease : null;
$isLatest = $latestRelease !== null && (int)$latestRelease['id'] === (int)$release['id'];
?>
<div class="page-stack page-stack--detail">
  <article class="surface surface--hero" aria-labelledby="appmarket-release-title">
    <div class="article-shell">
      <p class="section-kicker"><?= $isLatest ? 'Aktuální vydání' : 'Starší vydání' ?></p>
      <h1 id="appmarket-release-title" class="section-title section-title--hero"><?= h((string)$app['name']) ?> <?= h((string)$release['version_name']) ?></h1>
      <p class="meta-row">
        <span>versionCode <?= (int)$release['version_code'] ?></span>
        <?php if ((string)$release['published_at_label'] !== ''): ?><span>Vydáno <?= h((string)$release['published_at_label']) ?></span><?php endif; ?>
        <span><?= h(formatFileSize((int)$release['apk_size'])) ?></span>
        <span><?= h((string)$release['download_count_label']) ?></span>
      </p>
      <div class="button-row button-row--start">
        <a class="btn" href="<?= h(appmarketDownloadPath($app, (int)$release['version_code'])) ?>">Stáhnout APK</a>
        <?php if (!$isLatest && $latestRelease !== null): ?>
          <a class="btn btn-secondary" href="<?= h(appmarketReleasePath($app, (int)$latestRelease['version_code'])) ?>">Přejít na aktuální verzi <?= h((string)$latestRelease['version_name']) ?></a>
        <?php endif; ?>
      </div>
    </div>
  </article>

  <section class="surface" aria-labelledby="appmarket-release-notes-heading">
    <div class="article-shell">
      <h2 id="appmarket-release-notes-heading" class="section-title">Co je nového</h2>
      <?php if (trim((string)$release['release_notes']) !== ''): ?>
        <div class="prose"><?= renderProjectMarkdown((string)$release['release_notes']) ?></div>
      <?php else: ?>
        <p>Pro toto vydání nebyl doplněn seznam změn.</p>
      <?php endif; ?>
    </div>
  </section>

  <section class="surface" aria-labelledby="appmarket-release-security-heading">
    <div class="article-shell">
      <h2 id="appmarket-release-security-heading" class="section-title">Ověření souboru</h2>
      <dl class="info-list">
        <div><dt>ApplicationId</dt><dd><code><?= h((string)$release['package_id_snapshot']) ?></code></dd></div>
        <div><dt>SHA-256 APK</dt><dd><code class="break-long-token"><?= h((string)$release['apk_sha256']) ?></code></dd></div>
        <div><dt>SHA-256 certifikátu</dt><dd><code class="break-long-token"><?= h((string)$release['certificate_fingerprint_sha256']) ?></code></dd></div>
        <?php if ($release['min_sdk'] !== null): ?><div><dt>Minimální Android SDK</dt><dd><?= (int)$release['min_sdk'] ?></dd></div><?php endif; ?>
        <?php if ($release['target_sdk'] !== null): ?><div><dt>Cílové Android SDK</dt><dd><?= (int)$release['target_sdk'] ?></dd></div><?php endif; ?>
      </dl>
    </div>
  </section>

  <section class="surface" aria-labelledby="appmarket-release-back-heading">
    <div class="article-shell">
      <h2 id="appmarket-release-back-heading" class="sr-only">Další navigace</h2>
      <p><a href="<?= h(appmarketAppPath($app)) ?>"><span aria-hidden="true">←</span> Zpět na aplikaci <?= h((string)$app['name']) ?></a></p>
    </div>
  </section>
</div>
