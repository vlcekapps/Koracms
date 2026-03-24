<?php $faqCategory = trim((string)($faq['category_name'] ?? '')); ?>
<div class="article-layout">
  <article class="surface" aria-labelledby="faq-title">
    <p class="section-kicker">FAQ</p>
    <header class="section-heading">
      <div>
        <h1 id="faq-title" class="section-title section-title--hero"><?= h((string)$faq['question']) ?></h1>
        <?php if ($faqCategory !== ''): ?>
          <p class="meta-row">
            <span class="pill"><?= h($faqCategory) ?></span>
          </p>
        <?php endif; ?>
      </div>
    </header>

    <?php if ($faq['excerpt'] !== ''): ?>
      <p class="article-shell__lead"><?= h((string)$faq['excerpt']) ?></p>
    <?php endif; ?>

    <div class="prose article-shell__content">
      <?= renderContent((string)$faq['answer']) ?>
    </div>

    <div class="article-actions">
      <a class="button-secondary" href="<?= BASE_URL ?>/faq/index.php"><span aria-hidden="true">&larr;</span> Zpět na FAQ</a>
    </div>
  </article>
</div>
