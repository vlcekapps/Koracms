<?php
$pageKicker = trim((string)($pageKicker ?? 'Stránka'));
$backLinkHref = trim((string)($backLinkHref ?? ''));
$backLinkLabel = trim((string)($backLinkLabel ?? ''));
?>
<article class="surface article-shell">
  <p class="section-kicker"><?= h($pageKicker) ?></p>
  <h1 class="section-title section-title--hero"><?= h((string)$page['title']) ?></h1>
  <div class="prose article-shell__content">
    <?= renderContent((string)$page['content']) ?>
  </div>
  <?php if ($backLinkHref !== '' && $backLinkLabel !== ''): ?>
    <p class="button-row button-row--start" style="margin-top:1.5rem">
      <a class="button-secondary" href="<?= h($backLinkHref) ?>"><?= h($backLinkLabel) ?></a>
    </p>
  <?php endif; ?>
</article>
