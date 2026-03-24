<?php
$eventDescription = normalizePlainText((string)($event['description'] ?? ''));
$eventStart = (string)($event['event_date'] ?? '');
$eventEnd = (string)($event['event_end'] ?? '');
$eventLocation = trim((string)($event['location'] ?? ''));
?>
<div class="article-layout">
  <article class="surface" aria-labelledby="udalost-nadpis">
    <p class="section-kicker">Událost</p>
    <header class="section-heading">
      <div>
        <h1 id="udalost-nadpis" class="section-title section-title--hero"><?= h((string)$event['title']) ?></h1>
        <p class="meta-row">
          <time datetime="<?= h(str_replace(' ', 'T', $eventStart)) ?>"><?= formatCzechDate($eventStart) ?></time>
          <?php if ($eventEnd !== ''): ?>
            <span>do <?= h(formatCzechDate($eventEnd)) ?></span>
          <?php endif; ?>
          <?php if ($eventLocation !== ''): ?>
            <span><?= h($eventLocation) ?></span>
          <?php endif; ?>
        </p>
      </div>
    </header>

    <div class="split-grid">
      <section class="card" aria-labelledby="udalost-prehled">
        <div class="card__body">
          <h2 id="udalost-prehled" class="card__title">Přehled</h2>
          <p><strong>Začátek:</strong> <time datetime="<?= h(str_replace(' ', 'T', $eventStart)) ?>"><?= formatCzechDate($eventStart) ?></time></p>
          <?php if ($eventEnd !== ''): ?>
            <p><strong>Konec:</strong> <time datetime="<?= h(str_replace(' ', 'T', $eventEnd)) ?>"><?= formatCzechDate($eventEnd) ?></time></p>
          <?php endif; ?>
          <?php if ($eventLocation !== ''): ?>
            <p><strong>Místo:</strong> <?= h($eventLocation) ?></p>
          <?php endif; ?>
        </div>
      </section>

      <?php if ($eventDescription !== ''): ?>
        <section class="card" aria-labelledby="udalost-popis">
          <div class="card__body">
            <h2 id="udalost-popis" class="card__title">O události</h2>
            <p><?= h(mb_strimwidth($eventDescription, 0, 260, '...', 'UTF-8')) ?></p>
          </div>
        </section>
      <?php endif; ?>
    </div>

    <?php if ((string)($event['description'] ?? '') !== ''): ?>
      <div class="prose article-shell__content">
        <?= renderContent((string)$event['description']) ?>
      </div>
    <?php endif; ?>

    <div class="article-actions">
      <a class="button-secondary" href="<?= BASE_URL ?>/events/index.php"><span aria-hidden="true">&larr;</span> Zpět na události</a>
    </div>
  </article>
</div>
