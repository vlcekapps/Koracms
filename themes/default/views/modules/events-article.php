<?php
$eventDescription = normalizePlainText((string)($event['description'] ?? ''));
$eventStart = (string)($event['event_date'] ?? '');
$eventEnd = (string)($event['event_end'] ?? '');
$eventLocation = trim((string)($event['location'] ?? ''));
$eventLead = $eventDescription !== '' ? mb_strimwidth($eventDescription, 0, 260, '...', 'UTF-8') : '';
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

    <?php if ($eventLead !== ''): ?>
      <p class="article-shell__lead"><?= h($eventLead) ?></p>
    <?php endif; ?>

    <?php if ((string)($event['description'] ?? '') !== ''): ?>
      <div class="prose article-shell__content">
        <?= renderContent((string)$event['description']) ?>
      </div>
    <?php else: ?>
      <p class="empty-state">Další podrobnosti k této události zatím nejsou doplněné.</p>
    <?php endif; ?>

    <div class="article-actions">
      <a class="button-secondary" href="<?= BASE_URL ?>/events/index.php"><span aria-hidden="true">&larr;</span> Zpět na události</a>
    </div>
  </article>
</div>
