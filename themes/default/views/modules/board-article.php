<?php
$boardLabel = $boardLabel ?? boardModulePublicLabel();
$documentCategory = trim((string)($document['category_name'] ?? ''));
$documentPostedDate = (string)($document['posted_date'] ?? '');
$documentRemovalDate = (string)($document['removal_date'] ?? '');
$documentIsArchived = $documentRemovalDate !== '' && $documentRemovalDate < date('Y-m-d');
$leadText = normalizePlainText((string)($document['excerpt'] ?? ''));
?>
<div class="article-layout">
  <article class="surface" aria-labelledby="dokument-nadpis">
    <p class="section-kicker"><?= h((string)($document['board_type_label'] ?? 'Položka')) ?></p>
    <header class="section-heading">
      <div>
        <h1 id="dokument-nadpis" class="section-title section-title--hero"><?= h((string)$document['title']) ?></h1>
        <p class="meta-row">
          <?php if ((int)($document['is_pinned'] ?? 0) === 1): ?>
            <span class="pill">Důležité</span>
          <?php endif; ?>
          <?php if ($documentCategory !== ''): ?>
            <span class="pill"><?= h($documentCategory) ?></span>
          <?php endif; ?>
          <time datetime="<?= h($documentPostedDate) ?>"><?= formatCzechDate($documentPostedDate) ?></time>
          <?php if ($documentIsArchived): ?>
            <span>Archivní položka</span>
          <?php endif; ?>
        </p>
      </div>
    </header>

    <?php if ($leadText !== ''): ?>
      <p class="article-shell__lead"><?= h($leadText) ?></p>
    <?php endif; ?>

    <?php if ((string)($document['image_url'] ?? '') !== ''): ?>
      <div class="board-detail__hero">
        <img class="board-detail__image" src="<?= h((string)$document['image_url']) ?>" alt="" loading="lazy">
      </div>
    <?php endif; ?>

    <div class="split-grid">
      <section class="card" aria-labelledby="dokument-prehled">
        <div class="card__body">
          <h2 id="dokument-prehled" class="card__title">Přehled</h2>
          <p><strong>Typ:</strong> <?= h((string)($document['board_type_label'] ?? 'Položka')) ?></p>
          <p><strong>Vyvěšeno:</strong> <time datetime="<?= h($documentPostedDate) ?>"><?= formatCzechDate($documentPostedDate) ?></time></p>
          <?php if ($documentRemovalDate !== ''): ?>
            <p><strong>Sejmuto:</strong> <time datetime="<?= h($documentRemovalDate) ?>"><?= formatCzechDate($documentRemovalDate) ?></time></p>
          <?php endif; ?>
          <?php if ($documentCategory !== ''): ?>
            <p><strong>Kategorie:</strong> <?= h($documentCategory) ?></p>
          <?php endif; ?>
          <?php if ((string)($document['original_name'] ?? '') !== ''): ?>
            <p><strong>Příloha:</strong> <?= h((string)$document['original_name']) ?>
              <?php if ((int)($document['file_size'] ?? 0) > 0): ?>
                (<?= h(formatFileSize((int)$document['file_size'])) ?>)
              <?php endif; ?>
            </p>
          <?php endif; ?>
        </div>
      </section>

      <?php if (!empty($document['has_contact'])): ?>
        <section class="card" aria-labelledby="dokument-kontakt">
          <div class="card__body">
            <h2 id="dokument-kontakt" class="card__title">Kontakt</h2>
            <ul class="board-contact-list" role="list">
              <?php if ((string)$document['contact_name'] !== ''): ?>
                <li><strong>Osoba:</strong> <?= h((string)$document['contact_name']) ?></li>
              <?php endif; ?>
              <?php if ((string)$document['contact_phone'] !== ''): ?>
                <li><strong>Telefon:</strong> <a href="tel:<?= h(preg_replace('/\s+/', '', (string)$document['contact_phone'])) ?>"><?= h((string)$document['contact_phone']) ?></a></li>
              <?php endif; ?>
              <?php if ((string)$document['contact_email'] !== ''): ?>
                <li><strong>E-mail:</strong> <a href="mailto:<?= h((string)$document['contact_email']) ?>"><?= h((string)$document['contact_email']) ?></a></li>
              <?php endif; ?>
            </ul>
          </div>
        </section>
      <?php endif; ?>
    </div>

    <?php if ((string)($document['description'] ?? '') !== ''): ?>
      <div class="prose article-shell__content">
        <?= renderContent((string)$document['description']) ?>
      </div>
    <?php endif; ?>

    <div class="article-actions">
      <a class="button-secondary" href="<?= BASE_URL ?>/board/index.php"><span aria-hidden="true">&larr;</span> <?= h(boardModuleBackLabel()) ?></a>
      <?php if ((string)($document['filename'] ?? '') !== ''): ?>
        <a class="button-primary" href="<?= moduleFileUrl('board', (int)$document['id']) ?>" download="<?= h((string)$document['original_name']) ?>">Stáhnout přílohu</a>
      <?php endif; ?>
    </div>
  </article>
</div>
