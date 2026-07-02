<?php
$boardLabel = $boardLabel ?? boardModulePublicLabel();
$documentCategory = trim((string)($document['category_name'] ?? ''));
$documentPostedDate = (string)($document['posted_date'] ?? '');
$documentRemovalDate = (string)($document['removal_date'] ?? '');
$currentDate = (string)($currentDate ?? '');
$documentIsArchived = $documentRemovalDate !== '' && $currentDate !== '' && $documentRemovalDate < $currentDate;
$backUrl = BASE_URL . '/board/index.php' . ($documentIsArchived ? '?scope=archive' : '');
$leadText = normalizePlainText((string)($document['excerpt'] ?? ''));
$hasAttachment = (string)($document['original_name'] ?? '') !== '';
$hasExtraInfoCard = $documentRemovalDate !== '' || $hasAttachment;
$publicationEvents = is_array($publicationEvents ?? null) ? $publicationEvents : [];
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
            <?php
            $categoryLink = boardCategorySlug((string)($document['category_slug'] ?? '')) !== ''
                ? boardCategoryPath(['id' => (int)($document['category_id'] ?? 0), 'slug' => (string)$document['category_slug']])
                : '';
            ?>
            <?php if ($categoryLink !== ''): ?>
              <a class="pill" href="<?= h($categoryLink) ?>"><?= h($documentCategory) ?></a>
            <?php else: ?>
              <span class="pill"><?= h($documentCategory) ?></span>
            <?php endif; ?>
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
        <img class="board-detail__image" src="<?= h((string)$document['image_url']) ?>" alt="<?= h((string)$document['title']) ?>" loading="lazy">
      </div>
    <?php endif; ?>

    <?php if ($hasExtraInfoCard || !empty($document['has_contact'])): ?>
    <div class="split-grid">
      <?php if ($hasExtraInfoCard): ?>
        <section class="card" aria-labelledby="dokument-detaily">
          <div class="card__body">
            <h2 id="dokument-detaily" class="card__title">Další informace</h2>
            <?php if ($documentRemovalDate !== ''): ?>
              <p><strong>Sejmuto:</strong> <time datetime="<?= h($documentRemovalDate) ?>"><?= formatCzechDate($documentRemovalDate) ?></time></p>
            <?php endif; ?>
            <?php if ($hasAttachment): ?>
              <p><strong>Příloha:</strong> <?= h((string)$document['original_name']) ?>
                <?php if ((int)($document['file_size'] ?? 0) > 0): ?>
                  (<?= h(formatFileSize((int)$document['file_size'])) ?>)
                <?php endif; ?>
              </p>
            <?php endif; ?>
          </div>
        </section>
      <?php endif; ?>
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
    <?php endif; ?>

    <?php if ((string)($document['description'] ?? '') !== ''): ?>
      <div class="prose article-shell__content">
        <?= renderContent((string)$document['description']) ?>
      </div>
    <?php endif; ?>

    <?php if ($publicationEvents !== []): ?>
      <section class="card" aria-labelledby="board-publication-evidence-heading">
        <div class="card__body">
          <h2 id="board-publication-evidence-heading" class="card__title">Evidence zveřejnění</h2>
          <p class="field-help field-help--flush">Tato veřejná evidence zachycuje důležité změny dostupnosti položky a příloh.</p>
          <ol class="timeline-list">
            <?php foreach ($publicationEvents as $event): ?>
              <li>
                <strong><?= h(boardPublicationEventLabel((string)($event['event_type'] ?? ''))) ?></strong>
                <?php if ((string)($event['event_date'] ?? '') !== ''): ?>
                  <span>
                    <time datetime="<?= h(str_replace(' ', 'T', (string)$event['event_date'])) ?>"><?= h(formatCzechDate((string)$event['event_date'])) ?></time>
                  </span>
                <?php endif; ?>
                <?php if ((string)($event['public_path'] ?? '') !== ''): ?>
                  <br><span>Veřejná adresa: <a href="<?= h((string)$event['public_path']) ?>"><?= h((string)$event['public_path']) ?></a></span>
                <?php endif; ?>
                <?php if ((string)($event['attachment_name'] ?? '') !== ''): ?>
                  <br><span>Příloha: <?= h((string)$event['attachment_name']) ?>
                    <?php if ((int)($event['attachment_size'] ?? 0) > 0): ?>
                      (<?= h(formatFileSize((int)$event['attachment_size'])) ?>)
                    <?php endif; ?>
                  </span>
                <?php endif; ?>
                <?php if ((string)($event['attachment_checksum'] ?? '') !== ''): ?>
                  <br><span>SHA-256: <code><?= h((string)$event['attachment_checksum']) ?></code></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>
      </section>
    <?php endif; ?>

    <div class="article-actions">
      <a class="button-secondary" href="<?= h($backUrl) ?>"><span aria-hidden="true">&larr;</span> <?= h(boardModuleBackLabel()) ?></a>
      <?php if ((string)($document['filename'] ?? '') !== ''): ?>
        <a class="button-primary" href="<?= moduleFileUrl('board', (int)$document['id']) ?>" download="<?= h((string)$document['original_name']) ?>">Stáhnout přílohu</a>
      <?php endif; ?>
      <button type="button" class="button-secondary js-copy-link"
              data-url="<?= h(boardPublicUrl($document)) ?>">Kopírovat odkaz<span class="sr-only"> na dokument</span></button>
    </div>
  </article>
</div>
