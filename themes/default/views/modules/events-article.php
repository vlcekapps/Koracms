<?php
$eventDescription = trim((string)($event['description'] ?? ''));
$eventProgram = trim((string)($event['program_note'] ?? ''));
$eventLocation = trim((string)($event['location'] ?? ''));
$eventLocationDisplay = trim((string)($event['location_display'] ?? $eventLocation));
$eventExcerpt = trim((string)($event['excerpt_plain'] ?? ''));
$eventPlace = is_array($event['place'] ?? null) ? $event['place'] : null;
$recurrenceEvents = is_array($recurrenceEvents ?? null) ? $recurrenceEvents : [];
$backUrl = (string)($backUrl ?? (BASE_URL . '/events/index.php'));
?>
<div class="page-stack page-stack--detail">
  <article class="surface surface--hero" aria-labelledby="event-title">
    <div class="article-shell">
      <div class="article-shell__content">
        <p class="section-kicker">
          <?php if ((string)($event['event_type_path'] ?? '') !== ''): ?>
            <a href="<?= h((string)$event['event_type_path']) ?>"><?= h((string)($event['event_kind_label'] ?? 'Akce')) ?></a>
          <?php else: ?>
            <?= h((string)($event['event_kind_label'] ?? 'Akce')) ?>
          <?php endif; ?>
        </p>
        <h1 id="event-title" class="section-title section-title--hero"><?= h((string)($event['title'] ?? '')) ?></h1>

        <p class="meta-row">
          <span class="pill"><?= h((string)($event['event_status_label'] ?? 'Připravujeme')) ?></span>
          <time datetime="<?= h(str_replace(' ', 'T', (string)($event['event_date'] ?? ''))) ?>">
            <?= formatCzechDate((string)($event['event_date'] ?? '')) ?>
          </time>
          <?php if (!empty($event['event_end'])): ?>
            <span>do <?= h(formatCzechDate((string)$event['event_end'])) ?></span>
          <?php endif; ?>
          <?php if ($eventLocationDisplay !== ''): ?>
            <span><?= h($eventLocationDisplay) ?></span>
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
        <img class="article-cover" src="<?= h((string)$event['image_url']) ?>" alt="<?= h((string)($event['title'] ?? '')) ?>">
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
          <section class="surface surface--subsection" aria-labelledby="event-program-title">
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

      <aside class="article-shell__aside" aria-labelledby="event-info-title">
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
            <?php if ($eventLocationDisplay !== ''): ?>
              <div><dt>Místo</dt><dd><?= h($eventLocationDisplay) ?></dd></div>
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

          <div class="stack-actions stack-actions--spaced">
            <?php if (!empty($event['has_registration_url'])): ?>
              <a class="btn" href="<?= h((string)$event['registration_url']) ?>" target="_blank" rel="noopener noreferrer">Registrovat se<?= newWindowLinkSrOnlySuffix() ?></a>
            <?php endif; ?>
            <a class="btn btn-secondary" href="<?= h(eventIcsPath($event)) ?>">Přidat do kalendáře</a>
          </div>
        </section>

        <?php if ($eventPlace !== null): ?>
          <section class="info-card" aria-labelledby="event-place-title">
            <h2 id="event-place-title" class="section-title section-title--compact">Místo konání</h2>
            <p><strong><?= h((string)($eventPlace['name'] ?? '')) ?></strong></p>
            <?php if ((string)($eventPlace['full_address'] ?? '') !== ''): ?>
              <p class="meta-row meta-row--tight"><?= h((string)$eventPlace['full_address']) ?></p>
            <?php endif; ?>
            <div class="stack-actions stack-actions--spaced">
              <a class="btn btn-secondary" href="<?= h((string)($eventPlace['public_path'] ?? '#')) ?>">Detail místa</a>
              <?php if ((string)($eventPlace['map_url'] ?? '') !== ''): ?>
                <a class="btn btn-secondary" href="<?= h((string)$eventPlace['map_url']) ?>" target="_blank" rel="noopener noreferrer">Otevřít mapu<?= newWindowLinkSrOnlySuffix() ?></a>
              <?php endif; ?>
            </div>
          </section>
        <?php endif; ?>
      </aside>
    </div>
  </section>

  <?php if (count($recurrenceEvents) > 1): ?>
    <section class="surface" aria-labelledby="event-recurrence-title">
      <div class="article-shell">
        <h2 id="event-recurrence-title" class="section-title section-title--compact">Další termíny této akce</h2>
        <ol class="link-list">
          <?php foreach ($recurrenceEvents as $recurrenceEvent): ?>
            <li>
              <?php if ((int)$recurrenceEvent['id'] === (int)$event['id']): ?>
                <span aria-current="page">
                  <?= h(formatCzechDate((string)$recurrenceEvent['event_date'])) ?>
                  <span class="sr-only">, aktuální termín</span>
                </span>
              <?php else: ?>
                <a href="<?= h(eventPublicPath($recurrenceEvent)) ?>"><?= h(formatCzechDate((string)$recurrenceEvent['event_date'])) ?></a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </section>
  <?php endif; ?>

  <section class="surface" aria-labelledby="event-actions-title">
    <div class="article-shell">
      <h2 id="event-actions-title" class="sr-only">Další akce k události</h2>
      <div class="article-actions">
        <a class="button-secondary" href="<?= h($backUrl) ?>"><span aria-hidden="true">&larr;</span> Zpět na události</a>
        <button type="button" class="button-secondary js-copy-link"
                data-url="<?= h(eventPublicUrl($event)) ?>">Kopírovat odkaz<span class="sr-only"> na událost</span></button>
      </div>
    </div>
  </section>
</div>
