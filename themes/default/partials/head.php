  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<?= faviconTag() ?>
<?= seoMeta($meta) ?>
  <title><?= h($pageTitle) ?></title>
<?= publicA11yStyleTag() ?>
  <link rel="stylesheet" href="<?= h(themeAssetUrl('assets/public.css', $themeName)) ?>">
<?= themeCssVariablesStyleTag($themeName) ?>
<?php if (!empty($extraHeadHtml)): ?>
<?= $extraHeadHtml ?>
<?php endif; ?>
<?php $ga4Id = getSetting('ga4_measurement_id', ''); if ($ga4Id !== ''): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= h($ga4Id) ?>"></script>
  <script nonce="<?= cspNonce() ?>">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= h($ga4Id) ?>');</script>
<?php endif; ?>
<?php $customHead = getSetting('custom_head_code', ''); if ($customHead !== ''): ?>
<?= $customHead ?>
<?php endif; ?>
