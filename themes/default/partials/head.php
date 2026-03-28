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
<?php $cookieConsentEnabled = getSetting('cookie_consent_enabled', '0') === '1'; ?>
<?php if (!$cookieConsentEnabled): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= h($ga4Id) ?>"></script>
  <script nonce="<?= cspNonce() ?>">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= h($ga4Id) ?>');</script>
<?php else: ?>
  <script nonce="<?= cspNonce() ?>">
  window._koraGa4Id='<?= h($ga4Id) ?>';
  (function(){
    function getCk(n){var v='; '+document.cookie,p=v.split('; '+n+'=');if(p.length===2)return p.pop().split(';').shift();}
    if(getCk('cms_cookie')==='1'){
      var s=document.createElement('script');s.async=true;
      s.src='https://www.googletagmanager.com/gtag/js?id=<?= h($ga4Id) ?>';
      document.head.appendChild(s);
      window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
      gtag('js',new Date());gtag('config','<?= h($ga4Id) ?>');
    }
  })();
  </script>
<?php endif; ?>
<?php endif; ?>
<?php $customHead = getSetting('custom_head_code', ''); if ($customHead !== ''): ?>
<?= $customHead ?>
<?php endif; ?>
