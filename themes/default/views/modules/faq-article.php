<?php
$faqCategory = trim((string)($faq['category_name'] ?? ''));
$relatedFaqs = is_array($relatedFaqs ?? null) ? $relatedFaqs : [];
$backUrl = (string)($backUrl ?? (BASE_URL . '/faq/index.php'));
$listingQuery = is_array($listingQuery ?? null) ? $listingQuery : [];
$feedbackSuccess = !empty($feedbackSuccess);
$feedbackError = (string)($feedbackError ?? '');
$feedbackVote = (string)($feedbackVote ?? '');
$feedbackNote = (string)($feedbackNote ?? '');
?>
<div class="article-layout">
  <article class="surface" aria-labelledby="faq-title">
    <?php if (!empty($breadcrumbs)): ?>
      <h2 id="faq-breadcrumb-heading" class="sr-only">Drobečková navigace</h2>
      <nav aria-labelledby="faq-breadcrumb-heading">
        <ol class="breadcrumbs">
          <li><a href="<?= BASE_URL ?>/faq/index.php">Znalostní báze</a></li>
          <?php foreach ($breadcrumbs as $crumb): ?>
            <li><a href="<?= h(faqCategoryPath($crumb)) ?>"><?= h((string)$crumb['name']) ?></a></li>
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
            <a class="pill" href="<?= h(faqCategoryPath(['id' => (int)$faq['category_id'], 'slug' => (string)($faq['category_slug'] ?? '')])) ?>"><?= h($faqCategory) ?></a>
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

    <section class="stack-sections stack-sections--spaced" aria-labelledby="faq-feedback-title">
      <h2 id="faq-feedback-title" class="section-title section-title--compact">Pomohla vám tato odpověď?</h2>

      <?php if ($feedbackSuccess): ?>
        <div class="status-message status-message--success" role="status" aria-atomic="true" aria-labelledby="faq-feedback-success">
          <p id="faq-feedback-success">Děkujeme za zpětnou vazbu. Pomůže nám odpovědi průběžně zlepšovat.</p>
        </div>
      <?php endif; ?>

      <?php if ($feedbackError !== ''): ?>
        <div class="status-message status-message--error" role="alert" aria-atomic="true" aria-labelledby="faq-feedback-error">
          <p id="faq-feedback-error"><?= h($feedbackError) ?></p>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= h(faqPublicPath($faq, $listingQuery)) ?>" class="form-stack" novalidate<?= $feedbackError !== '' ? ' aria-describedby="faq-feedback-error"' : '' ?>>
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <?= honeypotField() ?>
        <fieldset>
          <legend>Ohodnotit odpověď</legend>
          <div class="choice-list">
            <label for="faq-feedback-vote-helpful">
              <input type="radio" id="faq-feedback-vote-helpful" name="vote" value="helpful" required<?= $feedbackVote === 'helpful' ? ' checked' : '' ?>>
              Ano, odpověď mi pomohla
            </label>
            <label for="faq-feedback-vote-not-helpful">
              <input type="radio" id="faq-feedback-vote-not-helpful" name="vote" value="not_helpful" required<?= $feedbackVote === 'not_helpful' ? ' checked' : '' ?>>
              Ne, potřebuji lepší odpověď
            </label>
          </div>
        </fieldset>

        <label for="faq-feedback-note">Co můžeme doplnit?</label>
        <textarea id="faq-feedback-note" name="note" rows="3" maxlength="1000" aria-describedby="faq-feedback-note-help"><?= h($feedbackNote) ?></textarea>
        <small id="faq-feedback-note-help" class="field-help">Volitelné. Nepište sem prosím citlivé údaje; zpráva slouží jen jako redakční poznámka.</small>

        <button type="submit" class="button-primary">Odeslat zpětnou vazbu</button>
      </form>
    </section>

    <div class="article-actions">
      <a class="button-secondary" href="<?= h($backUrl) ?>"><span aria-hidden="true">&larr;</span> Zpět na znalostní bázi</a>
      <button type="button" class="button-secondary js-copy-link"
              data-url="<?= h(faqPublicUrl($faq)) ?>">Kopírovat odkaz<span class="sr-only"> na položku</span></button>
    </div>

    <?php if ($relatedFaqs !== []): ?>
      <section class="stack-sections stack-sections--spaced" aria-labelledby="faq-related-title">
        <h2 id="faq-related-title" class="section-title section-title--compact">Další otázky</h2>
        <ul class="link-list">
          <?php foreach ($relatedFaqs as $relatedFaq): ?>
            <li class="link-list__item">
              <a class="link-list__title" href="<?= h(faqPublicPath($relatedFaq)) ?>"><?= h((string)$relatedFaq['question']) ?></a>
              <?php if (!empty($relatedFaq['category_name'])): ?>
                <p class="meta-row meta-row--tight">
                  <a href="<?= h(faqCategoryPath(['id' => (int)($relatedFaq['category_id'] ?? 0), 'slug' => (string)($relatedFaq['category_slug'] ?? '')])) ?>"><?= h((string)$relatedFaq['category_name']) ?></a>
                </p>
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
