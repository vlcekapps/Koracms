<?php
$faqCategory = trim((string)($faq['category_name'] ?? ''));
$relatedFaqs = is_array($relatedFaqs ?? null) ? $relatedFaqs : [];
$backUrl = (string)($backUrl ?? (BASE_URL . '/faq/index.php'));
?>
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
      <a class="button-secondary" href="<?= h($backUrl) ?>"><span aria-hidden="true">&larr;</span> Zpět na znalostní bázi</a>
      <button type="button" class="button-secondary js-copy-link"
              data-url="<?= h(faqPublicUrl($faq)) ?>"
              aria-label="Kopírovat odkaz na položku">Kopírovat odkaz</button>
    </div>

    <?php if ($relatedFaqs !== []): ?>
      <section class="stack-sections stack-sections--spaced" aria-labelledby="faq-related-title">
        <h2 id="faq-related-title" class="section-title section-title--compact">Další otázky</h2>
        <ul class="link-list">
          <?php foreach ($relatedFaqs as $relatedFaq): ?>
            <li class="link-list__item">
              <a class="link-list__title" href="<?= h(faqPublicPath($relatedFaq)) ?>"><?= h((string)$relatedFaq['question']) ?></a>
              <?php if (!empty($relatedFaq['category_name'])): ?>
                <p class="meta-row meta-row--tight"><span><?= h((string)$relatedFaq['category_name']) ?></span></p>
              <?php endif; ?>
              <?php if (!empty($relatedFaq['excerpt'])): ?>
                <p><?= h((string)$relatedFaq['excerpt']) ?></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </article>
</div>
