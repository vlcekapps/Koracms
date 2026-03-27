<?php
$themePreview = themePreviewData();
$themePreviewManifest = $themePreview !== [] ? themeManifest($themePreview['theme']) : [];
$configuredThemeName = trim(getSetting('active_theme', defaultThemeName()));
if ($configuredThemeName === '') {
    $configuredThemeName = defaultThemeName();
}
$configuredThemeName = themeExists($configuredThemeName) ? $configuredThemeName : defaultThemeName();
$currentRequestUri = internalRedirectTarget((string)($_SERVER['REQUEST_URI'] ?? ''), BASE_URL . '/index.php');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<?= renderThemePartial('head', [
    'pageTitle' => $pageTitle,
    'meta' => $meta,
    'themeName' => $themeName,
    'extraHeadHtml' => $extraHeadHtml,
], $themeName) ?>
</head>
<body class="<?= h($bodyClassAttr) ?>">
<?= adminBar($adminEditUrl) ?>
<?php if ($themePreview !== []): ?>
  <div class="theme-preview-banner" role="status" aria-label="Živý náhled šablony">
    <div class="container theme-preview-banner__inner">
      <p class="theme-preview-banner__text">
        <strong>Živý náhled:</strong>
        <?= h($themePreviewManifest['name'] ?? $themePreview['theme']) ?>
        <?php if ($themePreview['theme'] !== $configuredThemeName): ?>
          · aktivní web stále používá <code><?= h($configuredThemeName) ?></code>
        <?php else: ?>
          · náhled běží nad aktuálně aktivní šablonou
        <?php endif; ?>
      </p>
      <div class="theme-preview-banner__actions">
        <a class="button-secondary" href="<?= BASE_URL ?>/admin/themes.php">Upravit náhled</a>
        <form method="post" action="<?= BASE_URL ?>/admin/theme_preview.php" class="inline-form">
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="preview_action" value="clear">
          <input type="hidden" name="redirect_target" value="<?= h($currentRequestUri) ?>">
          <button type="submit" class="button-secondary">Ukončit náhled</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>
<a href="#obsah" class="skip-link">Přeskočit na obsah</a>
<div id="a11y-live" role="status" aria-live="polite" aria-atomic="true" class="sr-only"></div>
<div class="site-shell">
  <?= renderThemePartial('header', $headerData, $themeName) ?>
  <main id="obsah" class="<?= h($mainClass) ?>">
    <div class="container">
      <?= $contentHtml ?>
    </div>
  </main>
  <?= renderThemePartial('footer', [], $themeName) ?>
</div>
<script nonce="<?= cspNonce() ?>">
document.addEventListener('DOMContentLoaded', function () {
  var liveRegion = document.getElementById('a11y-live');
  if (!liveRegion) return;
  var message = document.querySelector('[role="status"]:not(#a11y-live),[role="alert"]');
  if (!message) return;
  var text = message.textContent.trim();
  if (!text) return;
  setTimeout(function () {
    liveRegion.textContent = text;
  }, 150);
  message.removeAttribute('role');
});
</script>
</body>
</html>
