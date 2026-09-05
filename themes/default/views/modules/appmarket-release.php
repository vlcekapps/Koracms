<?php
$latestRelease = is_array($latestRelease ?? null) ? $latestRelease : null;
$isLatest = $latestRelease !== null && (int)$latestRelease['id'] === (int)$release['id'];
$isLegacyRelease = ($release['metadata_source'] ?? 'apk') !== 'manual';
?>
<div class="page-stack page-stack--detail">
  <article class="surface surface--hero" aria-labelledby="appmarket-release-title">
    <div class="article-shell">
      <p class="section-kicker">
        <?= $isLatest ? 'Aktuální' : 'Starší' ?>
        <?= h(mb_strtolower((string)$release['release_channel_label'])) ?> vydání pro <?= h((string)$release['platform_label']) ?>
      </p>
      <h1 id="appmarket-release-title" class="section-title section-title--hero"><?= h((string)$app['name']) ?> <?= h((string)$release['version_name']) ?></h1>
      <p class="meta-row">
        <span><?= h((string)$release['platform_label']) ?></span>
        <?php if ($isLegacyRelease): ?><span>versionCode <?= (int)$release['version_code'] ?></span><?php endif; ?>
        <?php if ((string)$release['published_at_label'] !== ''): ?><span>Vydáno <?= h((string)$release['published_at_label']) ?></span><?php endif; ?>
        <span><?= h(strtoupper((string)$release['file_extension'])) ?></span>
        <span><?= h(formatFileSize((int)$release['file_size'])) ?></span>
        <span><?= h((string)$release['download_count_label']) ?></span>
        <span><?= h((string)$release['release_channel_label']) ?> kanál</span>
        <?php if ($isLegacyRelease): ?><span><?= h((string)$release['update_priority_label']) ?> aktualizace</span><?php endif; ?>
      </p>
      <div class="button-row button-row--start">
        <a class="btn" href="<?= h(appmarketDownloadPath($app, (int)$release['version_code'])) ?>">Stáhnout <?= h(strtoupper((string)$release['file_extension'])) ?> pro <?= h((string)$release['platform_label']) ?></a>
        <?php if (!$isLatest && $latestRelease !== null): ?>
          <a class="btn btn-secondary" href="<?= h(appmarketReleasePath($app, (int)$latestRelease['version_code'])) ?>">Přejít na aktuální verzi <?= h((string)$latestRelease['version_name']) ?> pro <?= h((string)$latestRelease['platform_label']) ?></a>
        <?php endif; ?>
      </div>
    </div>
  </article>

  <section class="surface" aria-labelledby="appmarket-release-requirements-heading">
    <div class="article-shell">
      <h2 id="appmarket-release-requirements-heading" class="section-title">Systémové požadavky</h2>
      <p>Platforma: <?= h((string)$release['platform_label']) ?></p>
      <p class="break-long-token"><?= trim((string)$release['system_requirements']) !== '' ? nl2br(h((string)$release['system_requirements'])) : 'Požadavky nejsou uvedeny. Před instalací ověřte kompatibilitu u autora aplikace.' ?></p>
    </div>
  </section>

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
        <div><dt>Soubor ke stažení</dt><dd class="break-long-token"><?= h(appmarketReleaseDownloadName($app, $release)) ?></dd></div>
        <div><dt>Formát souboru</dt><dd><?= h(strtoupper((string)$release['file_extension'])) ?></dd></div>
        <div><dt>Velikost souboru</dt><dd><?= h(formatFileSize((int)$release['file_size'])) ?></dd></div>
        <div><dt>SHA-256 souboru</dt><dd><code class="break-long-token"><?= h((string)$release['file_sha256']) ?></code></dd></div>
        <div><dt>Distribuční kanál</dt><dd><?= h((string)$release['release_channel_label']) ?></dd></div>
        <?php if ($isLegacyRelease): ?>
          <div><dt>ApplicationId</dt><dd><code class="break-long-token"><?= h((string)$release['package_id_snapshot']) ?></code></dd></div>
          <div><dt>SHA-256 certifikátu</dt><dd><code class="break-long-token"><?= h((string)$release['certificate_fingerprint_sha256']) ?></code></dd></div>
          <div><dt>Postupné nasazení</dt><dd><?= h((string)$release['rollout_label']) ?></dd></div>
          <div><dt>Podporované ABI</dt><dd><?= h((string)$release['supported_abis_label']) ?></dd></div>
          <?php if ($release['min_sdk'] !== null): ?><div><dt>Minimální Android SDK</dt><dd><?= (int)$release['min_sdk'] ?></dd></div><?php endif; ?>
          <?php if ($release['target_sdk'] !== null): ?><div><dt>Cílové Android SDK</dt><dd><?= (int)$release['target_sdk'] ?></dd></div><?php endif; ?>
          <div><dt>Naléhavost aktualizace</dt><dd><?= h((string)$release['update_priority_label']) ?></dd></div>
          <?php if ($release['required_below_version_code'] !== null): ?>
            <div>
              <dt>Povinná aktualizace</dt>
              <dd>Pro instalace s versionCode nižším než <?= (int)$release['required_below_version_code'] ?></dd>
            </div>
          <?php endif; ?>
        <?php endif; ?>
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
