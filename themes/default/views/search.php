<div class="search-shell">
  <section class="surface surface--narrow" aria-labelledby="search-title">
    <h1 id="search-title" class="section-title section-title--hero">Vyhledávání</h1>
    <p class="section-subtitle">Najděte články, novinky, stránky a další obsah napříč celým webem.</p>

    <form method="get" role="search" class="form-stack">
      <div class="search-form-row">
        <div class="field">
          <label for="q">Hledat na webu</label>
          <input type="search" id="q" name="q" class="form-control" required minlength="2"
                 value="<?= h($q) ?>" aria-label="Hledaný výraz">
        </div>
        <button type="submit" class="button-primary">Hledat</button>
      </div>
    </form>
  </section>

  <?php if ($q !== ''): ?>
    <section class="surface" aria-labelledby="search-results-title">
      <div class="section-heading">
        <div>
          <h2 id="search-results-title" class="section-title">Výsledky pro „<?= h($q) ?>“</h2>
        </div>
      </div>

      <?php if (empty($results)): ?>
        <p class="empty-state">Žádné výsledky pro <strong><?= h($q) ?></strong>.</p>
      <?php else: ?>
        <p class="section-subtitle">Nalezeno <?= $resultCountLabel ?> pro hledaný výraz <strong><?= h($q) ?></strong>.</p>
        <div class="result-list">
          <?php foreach ($results as $result): ?>
            <article class="result-item">
              <p class="meta-row meta-row--tight">
                <span class="pill"><?= h(typeLabel($result['type'])) ?></span>
              </p>
              <h3 class="result-item__title">
                <a href="<?= h(resultUrl($result)) ?>"><?= h(mb_substr($result['title'], 0, 120)) ?></a>
              </h3>
              <?php if (!empty($result['perex'])): ?>
                <p class="result-item__excerpt"><?= h(mb_substr(strip_tags($result['perex']), 0, 200)) ?></p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
