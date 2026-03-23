<?php
$logo = getSetting('site_logo', '');
$headerLayout = themeSettingValue('header_layout', $themeManifest['key'] ?? $themeName);
if ($headerLayout === '') {
    $headerLayout = 'balanced';
}
?>
<header class="site-header site-header--<?= h($headerLayout) ?>">
  <div class="container">
    <div class="site-header__panel">
      <div class="brand">
        <?php if ($logo !== ''): ?>
          <a class="brand__mark" href="<?= BASE_URL ?>/index.php" aria-label="<?= h($siteName) ?>">
            <img src="<?= BASE_URL ?>/uploads/site/<?= h($logo) ?>" alt="" class="brand__logo" loading="lazy">
          </a>
        <?php endif; ?>
        <div class="brand__copy">
          <?php if ($pageKind === 'home'): ?>
            <h1 class="brand__title"><a href="<?= BASE_URL ?>/index.php"><?= h($siteName) ?></a></h1>
          <?php else: ?>
            <p class="brand__title brand__title--compact"><a href="<?= BASE_URL ?>/index.php"><?= h($siteName) ?></a></p>
          <?php endif; ?>
          <?php if ($showSiteDescription && $siteDescription !== ''): ?>
            <p class="brand__tagline"><?= h($siteDescription) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?= siteNav($currentNav) ?>
    </div>
  </div>
</header>
