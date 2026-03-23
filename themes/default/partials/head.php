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
