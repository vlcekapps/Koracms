<?php
$eventDescription = trim((string)($event['description'] ?? ''));
$eventProgram = trim((string)($event['program_note'] ?? ''));
$eventLocation = trim((string)($event['location'] ?? ''));
$eventExcerpt = trim((string)($event['excerpt_plain'] ?? ''));
$backUrl = (string)($backUrl ?? (BASE_URL . '/events/index.php'));
?>
<div class="page-stack page-stack--detail">
  <article class="surface surface--hero">
    <div class="article-shell">
      <div class="article-shell__content">
        <p class="section-kicker"><?= h((string)($event['event_kind_label'] ?? 'Akce')) ?></p>
        <h1 class="section-title section-title--hero"><?= h((string)($event['title'] ?? '')) ?></h1>

        <p class="meta-row">
          <span class="pill"><?= h((string)($event['event_status_label'] ?? 'Připravujeme')) ?></span>
          <time datetime="<?= h(str_replace(' ', 'T', (string)($event['event_date'] ?? ''))) ?>">
            <?= formatCzechDate((string)($event['event_date'] ?? '')) ?>
          </time>
          <?php if (!empty($event['event_end'])): ?>
            <span>do <?= h(formatCzechDate((string)$event['event_end'])) ?></span>
          <?php endif; ?>
          <?php if ($eventLocation !== ''): ?>
            <span><?= h($eventLocation) ?></span>
          <?php endif; ?>
        </p>

        <?php if ($eventExcerpt !== ''): ?>
          <p class="section-subtitle"><?= h($eventExcerpt) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </article>

  <?php if ((string)($event['image_url'] ?? '') !== ''): ?>
    <section class="surface board-detail__hero" aria-labelledby="event-preview-title">
      <div class="article-shell">
        <h2 id="event-preview-title" class="sr-only">Obrázek události</h2>
        <img class="article-cover" src="<?= h((string)$event['image_url']) ?>" alt="">
      </div>
    </section>
  <?php endif; ?>

  <section class="surface" aria-labelledby="event-about-title">
    <div class="article-shell article-shell--sidebar">
      <div class="article-shell__content">
        <?php if ($eventDescription !== ''): ?>
          <section aria-labelledby="event-about-title">
            <h2 id="event-about-title" class="section-title section-title--compact">O události</h2>
            <div class="prose">
              <?= renderContent($eventDescription) ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if ($eventProgram !== ''): ?>
          <section class="surface" style="margin-top:1.5rem" aria-labelledby="event-program-title">
            <h2 id="event-program-title" class="section-title section-title--compact">Program a doplňující informace</h2>
            <div class="prose">
              <?= renderContent($eventProgram) ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if ($eventDescription === '' && $eventProgram === ''): ?>
          <p class="empty-state">Další podrobnosti k této události zatím nejsou doplněné.</p>
        <?php endif; ?>
      </div>

      <aside class="article-shell__aside">
        <section class="info-card" aria-labelledby="event-info-title">
          <h2 id="event-info-title" class="section-title section-title--compact">Praktické informace</h2>
          <dl class="info-list">
            <div>
              <dt>Začátek</dt>
              <dd><time datetime="<?= h(str_replace(' ', 'T', (string)($event['event_date'] ?? ''))) ?>"><?= formatCzechDate((string)($event['event_date'] ?? '')) ?></time></dd>
            </div>
            <?php if (!empty($event['event_end'])): ?>
              <div>
                <dt>Konec</dt>
                <dd><time datetime="<?= h(str_replace(' ', 'T', (string)$event['event_end'])) ?>"><?= formatCzechDate((string)$event['event_end']) ?></time></dd>
              </div>
            <?php endif; ?>
            <?php if ($eventLocation !== ''): ?>
              <div><dt>Místo</dt><dd><?= h($eventLocation) ?></dd></div>
            <?php endif; ?>
            <?php if ((string)($event['organizer_name'] ?? '') !== ''): ?>
              <div><dt>Pořadatel</dt><dd><?= h((string)$event['organizer_name']) ?></dd></div>
            <?php endif; ?>
            <?php if ((string)($event['organizer_email'] ?? '') !== ''): ?>
              <div><dt>E-mail pořadatele</dt><dd><a href="mailto:<?= h((string)$event['organizer_email']) ?>"><?= h((string)$event['organizer_email']) ?></a></dd></div>
            <?php endif; ?>
            <?php if (!empty($event['price_note'])): ?>
              <div><dt>Cena / vstupné</dt><dd><?= h((string)$event['price_note']) ?></dd></div>
            <?php endif; ?>
            <?php if (!empty($event['accessibility_note'])): ?>
              <div><dt>Přístupnost</dt><dd><?= nl2br(h((string)$event['accessibility_note'])) ?></dd></div>
            <?php endif; ?>
          </dl>

          <div class="stack-actions" style="margin-top:1rem">
            <?php if (!empty($event['has_registration_url'])): ?>
              <a class="btn" href="<?= h((string)$event['registration_url']) ?>" target="_blank" rel="noopener noreferrer">Registrovat se</a>
            <?php endif; ?>
            <a class="btn btn-secondary" href="<?= h(eventIcsPath($event)) ?>">Přidat do kalendáře</a>
          </div>
        </section>
      </aside>
    </div>
  </section>

  <section class="surface">
    <div class="article-shell">
      <div class="article-actions">
        <a class="button-secondary" href="<?= h($backUrl) ?>"><span aria-hidden="true">&larr;</span> Zpět na události</a>
        <button type="button" class="button-secondary js-copy-link"
                data-url="<?= h(eventPublicUrl($event)) ?>"
                aria-label="Kopírovat odkaz na událost">Kopírovat odkaz</button>
      </div>
    </div>
  </section>
</div>
