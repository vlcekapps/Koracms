<?php
$isEmbedded = !empty($isEmbedded);
$q = trim((string)($q ?? ''));
?>
<div class="<?= $isEmbedded ? 'listing-shell listing-shell--embed' : 'listing-shell' ?>">
  <?php if ($poll !== null): ?>
    <section class="surface<?= $isEmbedded ? ' surface--embed' : '' ?>" aria-labelledby="poll-title">
      <div class="section-heading">
        <div>
          <p class="section-kicker">Anketa</p>
          <h1 id="poll-title" class="section-title <?= $isEmbedded ? 'section-title--compact' : 'section-title--hero' ?>"><?= h((string)$poll['question']) ?></h1>
        </div>
      </div>

      <?php if (!empty($poll['excerpt'])): ?>
        <p class="section-subtitle"><?= h((string)$poll['excerpt']) ?></p>
      <?php endif; ?>

      <?php if ($voted): ?>
        <div class="status-message status-message--success" role="status">
          <p>Váš hlas byl zaznamenán. Děkujeme!</p>
        </div>
      <?php endif; ?>

      <?php if ($voteErrorMessage !== ''): ?>
        <div class="status-message status-message--error" role="alert">
          <p><?= h($voteErrorMessage) ?></p>
        </div>
      <?php endif; ?>

      <?php if ($showForm): ?>
        <form method="post" action="<?= h(pollPublicPath($poll, $isEmbedded ? ['embed' => '1'] : [])) ?>" class="form-stack poll-vote-form" novalidate>
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <?= honeypotField() ?>

          <fieldset class="form-fieldset">
            <legend>Vyberte jednu možnost</legend>

            <div class="stack-list">
              <?php foreach ($options as $index => $option): ?>
                <label class="choice-card" for="poll-option-<?= (int)$option['id'] ?>">
                  <input
                    type="radio"
                    id="poll-option-<?= (int)$option['id'] ?>"
                    name="option_id"
                    value="<?= (int)$option['id'] ?>"
                    <?= $index === 0 ? ' aria-required="true"' : '' ?>
                    required
                  >
                  <span><?= h((string)$option['option_text']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="button-row button-row--start">
              <button type="submit" name="vote" value="1" class="button-primary">Hlasovat</button>
            </div>
          </fieldset>
        </form>
      <?php else: ?>
        <section aria-labelledby="poll-results-title">
          <h2 id="poll-results-title" class="section-title section-title--compact">Výsledky</h2>

          <div class="poll-results" role="list" aria-label="Možnosti a výsledky hlasování">
            <?php foreach ($options as $option): ?>
              <?php $percentage = $totalVotes > 0 ? round(((int)$option['vote_count']) / $totalVotes * 100, 1) : 0; ?>
              <div class="poll-result" role="listitem">
                <div class="poll-result__header">
                  <span><?= h((string)$option['option_text']) ?></span>
                  <span><?= $percentage ?>&nbsp;% (<?= (int)$option['vote_count'] ?>&nbsp;hlasů)</span>
                </div>
                <div class="poll-result__track" aria-hidden="true">
                  <div class="poll-result__fill" style="width:<?= $percentage ?>%"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <p><strong>Celkem hlasů: <?= $totalVotes ?></strong></p>

          <?php if (!$isActive): ?>
            <p class="poll-note">Tato anketa je uzavřena.</p>
          <?php elseif ($hasVoted || $voted): ?>
            <p class="poll-note">U této ankety už jste hlasoval/a.</p>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if (!$isEmbedded): ?>
        <div class="article-actions">
          <a class="button-secondary" href="<?= BASE_URL ?>/polls/index.php"><span aria-hidden="true">←</span> Zpět na přehled anket</a>
        </div>
      <?php endif; ?>
    </section>
  <?php else: ?>
    <?php
      $activeUrl = appendUrlQuery(BASE_URL . '/polls/index.php', array_filter([
          'q' => $q !== '' ? $q : null,
      ]));
      $archiveUrl = appendUrlQuery(BASE_URL . '/polls/index.php', array_filter([
          'archiv' => '1',
          'q' => $q !== '' ? $q : null,
      ]));
      $clearUrl = $archiv ? BASE_URL . '/polls/index.php?archiv=1' : BASE_URL . '/polls/index.php';
      $pagerBaseUrl = BASE_URL . '/polls/index.php?';
      $pagerParams = array_filter([
          'archiv' => $archiv ? '1' : null,
          'q' => $q !== '' ? $q : null,
      ]);
      if ($pagerParams !== []) {
          $pagerBaseUrl .= http_build_query($pagerParams) . '&';
      }
    ?>
    <section class="surface" aria-labelledby="polls-title">
      <div class="section-heading">
        <div>
          <p class="section-kicker">Zapojte se</p>
          <h1 id="polls-title" class="section-title section-title--hero"><?= $archiv ? 'Archiv anket' : 'Ankety' ?></h1>
        </div>
      </div>

      <nav aria-label="Filtr anket">
        <ul class="chip-list">
          <?php if ($archiv): ?>
            <li><a class="chip-link" href="<?= h($activeUrl) ?>">Aktivní ankety</a></li>
            <li><span class="chip-link" aria-current="page">Archiv</span></li>
          <?php else: ?>
            <li><span class="chip-link" aria-current="page">Aktivní ankety</span></li>
            <li><a class="chip-link" href="<?= h($archiveUrl) ?>">Archiv</a></li>
          <?php endif; ?>
        </ul>
      </nav>

      <form method="get" class="listing-filters" aria-label="Hledání v anketách">
        <?php if ($archiv): ?>
          <input type="hidden" name="archiv" value="1">
        <?php endif; ?>
        <div class="button-row button-row--start button-row--filters">
          <div>
            <label for="poll-search">Hledat v anketách</label>
            <input type="search" id="poll-search" name="q" value="<?= h($q) ?>" placeholder="Otázka nebo popis">
          </div>
          <button type="submit" class="button-secondary">Hledat</button>
          <?php if ($q !== ''): ?>
            <a class="button-secondary" href="<?= h($clearUrl) ?>">Zrušit hledání</a>
          <?php endif; ?>
        </div>
      </form>

      <?php if (empty($polls)): ?>
        <p class="empty-state">
          <?php if ($q !== ''): ?>
            Pro zvolený dotaz jsme nenašli žádné ankety.
          <?php elseif ($archiv): ?>
            Žádné uzavřené ankety.
          <?php else: ?>
            Žádné aktivní ankety.
          <?php endif; ?>
        </p>
      <?php else: ?>
        <div class="stack-list">
          <?php foreach ($polls as $listPoll): ?>
            <article class="card poll-card">
              <div class="card__body">
                <h2 class="card__title">
                  <a href="<?= h((string)$listPoll['public_path']) ?>"><?= h((string)$listPoll['question']) ?></a>
                </h2>

                <p class="meta-row meta-row--tight">
                  <span><?= h((string)($listPoll['state_label'] ?? 'Anketa')) ?></span>
                  <span><?= (int)$listPoll['vote_count'] ?> hlasů</span>
                  <time datetime="<?= h(str_replace(' ', 'T', (string)$listPoll['created_at'])) ?>"><?= formatCzechDate((string)$listPoll['created_at']) ?></time>
                  <?php if (!empty($listPoll['end_date'])): ?>
                    <span>Konec: <?= formatCzechDate((string)$listPoll['end_date']) ?></span>
                  <?php endif; ?>
                </p>

                <?php if (!empty($listPoll['excerpt'])): ?>
                  <p><?= h((string)$listPoll['excerpt']) ?></p>
                <?php endif; ?>

                <div class="button-row button-row--start">
                  <?php if (!empty($listPoll['has_voted']) || $archiv || ((string)($listPoll['state'] ?? '') !== 'active')): ?>
                    <a class="button-secondary" href="<?= h((string)$listPoll['public_path']) ?>">Zobrazit výsledky</a>
                  <?php else: ?>
                    <a class="button-secondary" href="<?= h((string)$listPoll['public_path']) ?>">Hlasovat</a>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <?= renderPager($page, $totalPages, $pagerBaseUrl, 'Stránkování anket') ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
