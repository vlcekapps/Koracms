<?php
$recipe = is_array($recipe ?? null) ? $recipe : [];
$structure = is_array($structure ?? null) ? $structure : ['groups' => [], 'steps' => []];
$difficultyLabel = (string)($difficultyLabel ?? '');
?>
<article class="article-shell" aria-labelledby="recipe-title">
  <header class="article-shell__header">
    <h2 id="recipe-breadcrumb-title" class="sr-only">Drobečková navigace</h2>
    <nav aria-labelledby="recipe-breadcrumb-title">
      <ol class="breadcrumbs">
        <li><a href="<?= BASE_URL ?>/recipes/index.php">Recepty</a></li>
        <?php if ((int)($recipe['category_is_active'] ?? 0) === 1 && trim((string)($recipe['category_slug'] ?? '')) !== ''): ?>
          <li><a href="<?= h(recipeCategoryPublicPath((string)$recipe['category_slug'])) ?>"><?= h((string)$recipe['category_name']) ?></a></li>
        <?php endif; ?>
        <li><span aria-current="page"><?= h((string)$recipe['title']) ?></span></li>
      </ol>
    </nav>
    <p class="section-kicker">Recept</p>
    <h1 id="recipe-title" class="article-shell__title"><?= h((string)$recipe['title']) ?></h1>
    <p class="article-shell__lead"><?= h((string)$recipe['summary']) ?></p>
  </header>

  <?php if ((string)($recipe['image_url'] ?? '') !== ''): ?>
    <div class="article-hero">
      <img src="<?= h((string)$recipe['image_url']) ?>" alt="<?= h((string)$recipe['image_alt']) ?>">
    </div>
  <?php endif; ?>

  <section class="surface surface--subtle" aria-labelledby="recipe-overview-title">
    <h2 id="recipe-overview-title" class="section-title section-title--compact">Přehled receptu</h2>
    <dl class="meta-list">
      <?php if ((int)($recipe['servings'] ?? 0) > 0): ?><div><dt>Porce</dt><dd><?= (int)$recipe['servings'] ?></dd></div><?php endif; ?>
      <?php if ((int)($recipe['prep_minutes'] ?? 0) > 0): ?><div><dt>Příprava</dt><dd><?= h(recipeDurationLabel((int)$recipe['prep_minutes'])) ?></dd></div><?php endif; ?>
      <?php if ((int)($recipe['cook_minutes'] ?? 0) > 0): ?><div><dt>Tepelná úprava</dt><dd><?= h(recipeDurationLabel((int)$recipe['cook_minutes'])) ?></dd></div><?php endif; ?>
      <?php if (recipeTotalMinutes($recipe) !== null): ?><div><dt>Celkový čas</dt><dd><?= h(recipeDurationLabel(recipeTotalMinutes($recipe))) ?></dd></div><?php endif; ?>
      <?php if ($difficultyLabel !== ''): ?><div><dt>Náročnost</dt><dd><?= h($difficultyLabel) ?></dd></div><?php endif; ?>
      <?php if ((int)($recipe['calories_kcal'] ?? 0) > 0): ?><div><dt>Energie na porci</dt><dd><?= (int)$recipe['calories_kcal'] ?> kcal</dd></div><?php endif; ?>
    </dl>
    <?php if (!empty($recipe['dietary_labels'])): ?>
      <h3>Dietní vlastnosti</h3>
      <ul class="chip-list"><?php foreach ($recipe['dietary_labels'] as $label): ?><li><?= h((string)$label) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if (!empty($recipe['allergen_labels'])): ?>
      <h3>Alergeny</h3>
      <ul><?php foreach ($recipe['allergen_labels'] as $number => $label): ?><li><?= h((string)$number . '. ' . (string)$label) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </section>

  <section aria-labelledby="recipe-ingredients-title">
    <h2 id="recipe-ingredients-title">Ingredience</h2>
    <?php foreach ($structure['groups'] as $group): ?>
      <?php $groupTitle = trim((string)($group['title'] ?? '')); ?>
      <?php if ($groupTitle !== ''): ?><h3><?= h($groupTitle) ?></h3><?php endif; ?>
      <ul class="recipe-ingredient-list">
      <?php foreach (($group['ingredients'] ?? []) as $ingredient): ?>
        <li>
          <?php $amount = trim((string)($ingredient['amount'] ?? '') . ' ' . (string)($ingredient['unit'] ?? '')); ?>
          <?php if ($amount !== ''): ?><strong><?= h($amount) ?></strong> <?php endif; ?>
          <?= h((string)$ingredient['name']) ?>
          <?php if (trim((string)$ingredient['note']) !== ''): ?>, <?= h((string)$ingredient['note']) ?><?php endif; ?>
          <?php if ((int)$ingredient['is_optional'] === 1): ?> <span>(volitelné)</span><?php endif; ?>
        </li>
      <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </section>

  <section aria-labelledby="recipe-steps-title">
    <h2 id="recipe-steps-title">Postup</h2>
    <ol class="recipe-step-list">
    <?php foreach ($structure['steps'] as $step): ?>
      <li>
        <?php if (trim((string)$step['title']) !== ''): ?><h3><?= h((string)$step['title']) ?></h3><?php endif; ?>
        <p><?= nl2br(h((string)$step['instruction'])) ?></p>
        <?php
        $stepMedia = [
            'filename' => $step['media_filename'] ?? '',
            'folder' => $step['media_folder'] ?? 'media',
            'original_name' => $step['media_original_name'] ?? '',
            'alt_text' => $step['media_alt_text'] ?? '',
            'mime_type' => $step['media_mime_type'] ?? '',
            'visibility' => $step['media_visibility'] ?? '',
        ];
        ?>
        <?php if (mediaIsPublic($stepMedia) && mediaCanPreviewImage($stepMedia)): ?>
          <?php
          $stepAlt = trim((string)$step['image_alt_text'])
              ?: (trim((string)$stepMedia['alt_text'])
                  ?: (trim((string)$step['title']) !== ''
                      ? (string)$step['title']
                      : 'Ilustrační obrázek k postupu receptu ' . (string)$recipe['title']));
            ?>
          <img src="<?= h(mediaFileUrl($stepMedia)) ?>" alt="<?= h($stepAlt) ?>" loading="lazy">
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
    </ol>
  </section>

  <?php if (trim((string)($recipe['notes'] ?? '')) !== ''): ?>
    <section aria-labelledby="recipe-notes-title">
      <h2 id="recipe-notes-title">Poznámky a tipy</h2>
      <div class="prose"><?= nl2br(h((string)$recipe['notes'])) ?></div>
    </section>
  <?php endif; ?>

  <?php if (trim((string)($recipe['source_name'] ?? '')) !== '' || trim((string)($recipe['source_url'] ?? '')) !== ''): ?>
    <section aria-labelledby="recipe-source-title">
      <h2 id="recipe-source-title">Zdroj receptu</h2>
      <p>
        <?php if (trim((string)$recipe['source_url']) !== ''): ?>
          <a href="<?= h((string)$recipe['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h(trim((string)$recipe['source_name']) !== '' ? (string)$recipe['source_name'] : 'Původní zdroj') ?><?= newWindowLinkSrOnlySuffix() ?></a>
        <?php else: ?>
          <?= h((string)$recipe['source_name']) ?>
        <?php endif; ?>
      </p>
    </section>
  <?php endif; ?>

  <footer class="article-actions">
    <a class="button-secondary" href="<?= BASE_URL ?>/recipes/index.php"><span aria-hidden="true">←</span> Zpět na recepty</a>
    <button type="button" class="button-secondary js-print-page">Vytisknout recept</button>
  </footer>
</article>
