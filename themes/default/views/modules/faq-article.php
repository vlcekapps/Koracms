<?php $faqCategory = trim((string)($faq['category_name'] ?? '')); ?>
<div class="article-layout">
  <article class="surface" aria-labelledby="faq-title">
    <?php if (!empty($breadcrumbs)): ?>
      <nav aria-label="Drobečková navigace">
        <ol class="breadcrumbs">
          <li><a href="<?= BASE_URL ?>/faq/index.php">Znalostní báze</a></li>
          <?php foreach ($breadcrumbs as $crumb): ?>
            <li><a href="<?= BASE_URL ?>/faq/index.php?kat=<?= (int)$crumb['id'] ?>"><?= h((string)$crumb['name']) ?></a></li>
          <?php endforeach; ?>
          <li><span aria-current="page"><?= h((string)$faq['question']) ?></span></li>
        </ol>
      </nav>
    <?php endif; ?>

    <header class="section-heading">
      <div>
        <h1 id="faq-title" class="section-title section-title--hero"><?= h((string)$faq['question']) ?></h1>
        <?php if ($faqCategory !== ''): ?>
          <p class="meta-row">
            <a class="pill" href="<?= BASE_URL ?>/faq/index.php?kat=<?= (int)$faq['category_id'] ?>"><?= h($faqCategory) ?></a>
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
      <a class="button-secondary" href="<?= BASE_URL ?>/faq/index.php"><span aria-hidden="true">&larr;</span> Zpět na znalostní bázi</a>
      <button type="button" class="button-secondary js-copy-link"
              data-url="<?= h(faqPublicUrl($faq)) ?>"
              aria-label="Kopírovat odkaz na položku">Kopírovat odkaz</button>
    </div>
  </article>
</div>
