<?php
$placeCategory = trim((string)($place['category'] ?? ''));
$placeLocality = trim((string)($place['locality'] ?? ''));
$backUrl = (string)($backUrl ?? (BASE_URL . '/places/index.php'));
?>
<div class="article-layout">
  <article class="surface" aria-labelledby="place-title">
    <p class="section-kicker"><?= h((string)($place['place_kind_label'] ?? 'Místo')) ?></p>
    <header class="section-heading">
      <div>
        <h1 id="place-title" class="section-title section-title--hero"><?= h((string)$place['name']) ?></h1>
        <p class="meta-row">
          <?php if ($placeCategory !== ''): ?>
            <span class="pill"><?= h($placeCategory) ?></span>
          <?php endif; ?>
          <?php if ($placeLocality !== ''): ?>
            <span><?= h($placeLocality) ?></span>
          <?php endif; ?>
        </p>
      </div>
    </header>

    <?php if (!empty($place['excerpt_plain'])): ?>
      <p class="article-shell__lead"><?= h((string)$place['excerpt_plain']) ?></p>
    <?php endif; ?>

    <?php if ((string)($place['image_url'] ?? '') !== ''): ?>
      <div class="board-detail__hero">
        <img class="board-detail__image" src="<?= h((string)$place['image_url']) ?>" alt="" loading="lazy">
      </div>
    <?php endif; ?>

    <div class="split-grid">
      <section class="card" aria-labelledby="place-info">
        <div class="card__body">
          <h2 id="place-info" class="card__title">Praktické informace</h2>
          <?php if (!empty($place['full_address'])): ?>
            <p><strong>Adresa:</strong> <?= h((string)$place['full_address']) ?></p>
          <?php endif; ?>
          <?php if ((string)($place['url'] ?? '') !== ''): ?>
            <p><strong>Web:</strong> <a href="<?= h((string)$place['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string)$place['url']) ?></a></p>
          <?php endif; ?>
          <?php if (!empty($place['has_coordinates'])): ?>
            <p><strong>GPS:</strong> <?= h((string)$place['latitude']) ?>, <?= h((string)$place['longitude']) ?></p>
            <p><a class="section-link" href="<?= h((string)$place['map_url']) ?>" target="_blank" rel="noopener noreferrer">Otevřít v mapách <span aria-hidden="true">→</span></a></p>
          <?php endif; ?>
          <?php if (!empty($place['opening_hours'])): ?>
            <p><strong>Otevírací doba / poznámky:</strong></p>
            <div class="prose"><?= nl2br(h((string)$place['opening_hours'])) ?></div>
          <?php endif; ?>
        </div>
      </section>

      <?php if (!empty($place['has_contact'])): ?>
        <section class="card" aria-labelledby="place-contact">
          <div class="card__body">
            <h2 id="place-contact" class="card__title">Kontakt</h2>
            <ul class="board-contact-list" role="list">
              <?php if (!empty($place['contact_phone'])): ?>
                <li><strong>Telefon:</strong> <a href="tel:<?= h((string)preg_replace('/\s+/', '', (string)$place['contact_phone'])) ?>"><?= h((string)$place['contact_phone']) ?></a></li>
              <?php endif; ?>
              <?php if (!empty($place['contact_email'])): ?>
                <li><strong>E-mail:</strong> <a href="mailto:<?= h((string)$place['contact_email']) ?>"><?= h((string)$place['contact_email']) ?></a></li>
              <?php endif; ?>
            </ul>
          </div>
        </section>
      <?php endif; ?>
    </div>

    <?php if (!empty($place['description'])): ?>
      <div class="prose article-shell__content">
        <?= renderContent((string)$place['description']) ?>
      </div>
    <?php endif; ?>

    <div class="article-actions">
      <a class="button-secondary" href="<?= h($backUrl) ?>"><span aria-hidden="true">&larr;</span> Zpět na zajímavá místa</a>
      <?php if ((string)($place['url'] ?? '') !== ''): ?>
        <a class="button-primary" href="<?= h((string)$place['url']) ?>" target="_blank" rel="noopener noreferrer">Navštívit web</a>
      <?php endif; ?>
      <button type="button" class="button-secondary js-copy-link"
              data-url="<?= h(placePublicUrl($place)) ?>"
              aria-label="Kopírovat odkaz na místo">Kopírovat odkaz</button>
    </div>
  </article>
</div>
