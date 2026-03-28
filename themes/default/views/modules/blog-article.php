<?php
$renderAuthorName = static function (array $article): string {
    if (empty($article['author_name'])) {
        return '';
    }

    $label = h((string)$article['author_name']);
    if (!empty($article['author_public_path'])) {
        return '<a href="' . h((string)$article['author_public_path']) . '">' . $label . '</a>';
    }

    return '<span>' . $label . '</span>';
};

$showAuthorPanel = !empty($article['author_public_path'])
    && (
        !empty($article['author_bio'])
        || !empty($article['author_website_url'])
        || !empty($article['author_avatar_url'])
    );
?>
<div class="article-layout">
  <article class="surface" aria-labelledby="clanek-nadpis">
    <p class="section-kicker">Článek</p>
    <header class="section-heading">
      <div>
        <h1 id="clanek-nadpis" class="section-title section-title--hero"><?= h($article['title']) ?></h1>
        <p class="meta-row">
          <?php if (!empty($article['category'])): ?>
            <a class="pill" href="<?= BASE_URL ?>/blog/index.php?kat=<?= (int)$article['category_id'] ?>"><?= h($article['category']) ?></a>
          <?php endif; ?>
          <time datetime="<?= h(str_replace(' ', 'T', $article['created_at'])) ?>"><?= formatCzechDate($article['created_at']) ?></time>
          <?php if (!empty($article['author_name'])): ?>
            <?= $renderAuthorName($article) ?>
          <?php endif; ?>
          <span><?= h(articleReadingMeta(($article['perex'] ?? '') . ($article['content'] ?? ''), (int)($article['view_count'] ?? 0))) ?></span>
        </p>
      </div>
    </header>

    <?php if (!empty($article['image_file'])): ?>
      <img src="<?= BASE_URL ?>/uploads/articles/<?= rawurlencode($article['image_file']) ?>"
           alt="<?= h($article['title']) ?>" class="article-cover" loading="lazy">
    <?php endif; ?>

    <?php if (!empty($article['perex'])): ?>
      <p class="article-summary"><strong><?= h($article['perex']) ?></strong></p>
    <?php endif; ?>

    <div class="prose article-shell__content">
      <?= renderContent($article['content']) ?>
    </div>

    <?php if (!empty($tags)): ?>
      <nav aria-label="Tagy článku">
        <ul class="chip-list">
          <?php foreach ($tags as $tag): ?>
            <li><a class="chip-link" href="<?= BASE_URL ?>/blog/index.php?tag=<?= rawurlencode($tag['slug']) ?>">#<?= h($tag['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>
    <?php endif; ?>

    <?php if ($showAuthorPanel): ?>
      <section class="surface surface--accent" aria-labelledby="autor-clanku-nadpis">
        <div class="author-panel">
          <div class="author-panel__media">
            <?php if (!empty($article['author_avatar_url'])): ?>
              <img
                class="author-avatar"
                src="<?= h((string)$article['author_avatar_url']) ?>"
                alt="Profilová fotografie autora <?= h((string)$article['author_display_name']) ?>"
                loading="lazy">
            <?php else: ?>
              <div class="author-avatar author-avatar--placeholder" aria-hidden="true">
                <?= h(mb_strtoupper(mb_substr((string)$article['author_display_name'], 0, 1))) ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="author-panel__content">
            <p class="section-kicker">Autor článku</p>
            <h2 id="autor-clanku-nadpis" class="section-title">O autorovi</h2>
            <p class="author-panel__name"><?= h((string)$article['author_display_name']) ?></p>
            <?php if (!empty($article['author_bio'])): ?>
              <div class="prose author-panel__bio">
                <?= renderContent((string)$article['author_bio']) ?>
              </div>
            <?php endif; ?>
            <div class="button-row button-row--start">
              <a class="button-primary" href="<?= h((string)$article['author_public_path']) ?>">Profil autora</a>
              <?php if (!empty($article['author_website_url'])): ?>
                <a class="button-secondary" href="<?= h((string)$article['author_website_url']) ?>" target="_blank" rel="noopener noreferrer">Web autora</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <div class="article-actions">
      <a class="button-secondary" href="<?= BASE_URL ?>/blog/index.php"><span aria-hidden="true">←</span> Zpět na seznam článků</a>
      <button type="button" class="button-secondary js-copy-link"
              data-url="<?= h(articlePublicUrl($article)) ?>"
              aria-label="Kopírovat odkaz na článek">Kopírovat odkaz</button>
    </div>
  </article>

  <section class="surface" aria-labelledby="komentare-nadpis">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Diskuse</p>
        <h2 id="komentare-nadpis" class="section-title">Komentáře<?= count($comments) > 0 ? ' (' . count($comments) . ')' : '' ?></h2>
      </div>
    </div>

    <?php if ($commentResult === 'pending'): ?>
      <div class="status-message status-message--success" role="status">
        <p>Komentář byl přijat a čeká na schválení. Děkujeme!</p>
      </div>
    <?php elseif ($commentResult === 'approved'): ?>
      <div class="status-message status-message--success" role="status">
        <p>Komentář byl zveřejněn. Děkujeme!</p>
      </div>
    <?php endif; ?>

    <?php if (empty($comments)): ?>
      <p class="empty-state">Zatím tu nejsou žádné komentáře.</p>
    <?php else: ?>
      <div class="comments-list">
        <?php foreach ($comments as $comment): ?>
          <article class="comment-card">
            <p class="meta-row meta-row--tight">
              <strong><?= h($comment['author_name']) ?></strong>
              <?php if ($comment['author_email'] !== ''): ?>
                <a href="mailto:<?= h($comment['author_email']) ?>"><?= h($comment['author_email']) ?></a>
              <?php endif; ?>
              <time datetime="<?= h(str_replace(' ', 'T', $comment['created_at'])) ?>"><?= formatCzechDate($comment['created_at']) ?></time>
            </p>
            <p class="comment-text"><?= h($comment['content']) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="surface surface--narrow" aria-labelledby="pridat-komentar-nadpis">
    <p class="section-kicker">Zapojte se</p>
    <h2 id="pridat-komentar-nadpis" class="section-title">Přidat komentář</h2>

    <?php if (!empty($commentErrors)): ?>
      <div id="comment-errors" class="status-message status-message--error" role="alert">
        <ul>
          <?php foreach ($commentErrors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!$commentsState['enabled']): ?>
      <div class="status-message status-message--info" role="status">
        <p><?= h($commentsState['message']) ?></p>
      </div>
    <?php else: ?>
    <form method="post" novalidate class="form-stack"<?php if (!empty($commentErrors)): ?> aria-describedby="comment-errors"<?php endif; ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <?= honeypotField() ?>

      <fieldset class="form-fieldset">
        <legend>Přidat komentář</legend>

        <div class="field">
          <label for="author_name">Jméno <span aria-hidden="true">*</span></label>
          <input type="text" id="author_name" name="author_name" class="form-control" required
                 aria-required="true" maxlength="100" value="<?= h($formData['author_name']) ?>">
        </div>

        <div class="field">
          <label for="author_email">E-mail</label>
          <input type="email" id="author_email" name="author_email" class="form-control" maxlength="255"
                 aria-describedby="comment-author-email-help" autocomplete="email"
                 value="<?= h($formData['author_email']) ?>">
          <small id="comment-author-email-help" class="help-text">Nepovinné pole, nebude zveřejněno.</small>
        </div>

        <div class="field">
          <label for="comment">Komentář <span aria-hidden="true">*</span></label>
          <textarea id="comment" name="comment" class="form-control" required
                    aria-required="true"><?= h($formData['comment']) ?></textarea>
        </div>

        <div class="field">
          <label for="captcha">Ověření: kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
          <input type="text" id="captcha" name="captcha" class="form-control form-control--compact" required
                 aria-required="true" inputmode="numeric" autocomplete="off">
        </div>

        <div class="button-row button-row--start">
          <button type="submit" class="button-primary">Odeslat komentář</button>
        </div>
      </fieldset>
    </form>
    <?php endif; ?>
  </section>
</div>
