<?php
$articleLink = static fn(array $article): string => articlePublicPath($article);
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
$filterLink = static function (array $params = []) use ($blog, $activeAuthor): string {
    $query = [];
    if (!empty($activeAuthor['author_slug'])) {
        $query['autor'] = (string)$activeAuthor['author_slug'];
    }
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $query[$key] = $value;
    }

    $base = blogIndexPath($blog);
    return $query === [] ? $base : $base . '?' . http_build_query($query);
};
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="blog-title">
    <div class="section-heading">
      <div>
        <h1 id="blog-title" class="section-title section-title--hero"><?= h($pageHeading) ?></h1>
        <?php if (!empty($blog['description'])): ?>
          <p class="section-subtitle"><?= h((string)$blog['description']) ?></p>
        <?php endif; ?>
      </div>
      <?php if (!empty($showAuthorsIndexLink)): ?>
        <a class="section-link" href="<?= authorIndexPath() ?>">Autoři <span aria-hidden="true">→</span></a>
      <?php endif; ?>
    </div>

    <?php if (!empty($publicBlogs) && count($publicBlogs) > 1): ?>
      <nav aria-label="Další blogy webu" class="form-stack">
        <ul class="chip-list">
          <?php foreach ($publicBlogs as $publicBlog): ?>
            <?php $isCurrentBlog = (int)($publicBlog['id'] ?? 0) === (int)($blog['id'] ?? 0); ?>
            <li>
              <?php if ($isCurrentBlog): ?>
                <span class="pill"><?= h((string)$publicBlog['name']) ?></span>
              <?php else: ?>
                <a class="chip-link" href="<?= h(blogIndexPath($publicBlog)) ?>"><?= h((string)$publicBlog['name']) ?></a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
    <?php endif; ?>

    <?php if (!empty($categories)): ?>
      <nav aria-label="Kategorie blogu" class="form-stack">
        <ul class="chip-list">
          <li><a class="chip-link" href="<?= h($filterLink()) ?>"<?= ($katId === null && $tagSlug === '') ? ' aria-current="page"' : '' ?>>Vše</a></li>
          <?php foreach ($categories as $category): ?>
            <li>
              <a class="chip-link" href="<?= h($filterLink(['kat' => (int)$category['id']])) ?>"<?= $katId === (int)$category['id'] ? ' aria-current="page"' : '' ?>>
                <?= h($category['name']) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
    <?php endif; ?>

    <?php if (!empty($allTags)): ?>
      <nav aria-label="Tagy blogu" class="form-stack">
        <ul class="chip-list">
          <?php foreach ($allTags as $tag): ?>
            <li>
              <a class="chip-link" href="<?= h($filterLink(['tag' => $tag['slug']])) ?>"<?= $tagSlug === $tag['slug'] ? ' aria-current="page"' : '' ?>>
                #<?= h($tag['name']) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
    <?php endif; ?>

    <?php if (!empty($activeAuthor)): ?>
      <nav aria-label="Aktivní autor blogu" class="form-stack">
        <ul class="chip-list">
          <li><a class="chip-link" href="<?= authorIndexPath() ?>">Všichni autoři</a></li>
          <li><a class="chip-link" href="<?= h(blogIndexPath($blog)) ?>">Všechny články</a></li>
          <li><span class="pill">Autor: <?= h($activeAuthor['author_display_name']) ?></span></li>
        </ul>
      </nav>
    <?php endif; ?>

    <?php if (empty($articles)): ?>
      <p class="empty-state">
        <?php if (!empty($activeAuthor)): ?>
          Autor zatím nemá žádné veřejně publikované články.
        <?php else: ?>
          Zatím tu nejsou žádné články.
        <?php endif; ?>
      </p>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($articles as $article): ?>
          <article class="card">
            <?php if (!empty($article['image_file'])): ?>
              <a class="card__media" href="<?= h($articleLink($article)) ?>">
                <img src="<?= BASE_URL ?>/uploads/articles/thumbs/<?= rawurlencode($article['image_file']) ?>"
                     alt="<?= h($article['title']) ?>" loading="lazy">
              </a>
            <?php endif; ?>
            <div class="card__body">
              <h2 class="card__title">
                <a href="<?= h($articleLink($article)) ?>"><?= h($article['title']) ?></a>
              </h2>
              <p class="meta-row meta-row--tight">
                <time datetime="<?= h(str_replace(' ', 'T', $article['created_at'])) ?>"><?= formatCzechDate($article['created_at']) ?></time>
                <span><?= h(articleReadingMeta(($article['perex'] ?? '') . ($article['content'] ?? ''), (int)($article['view_count'] ?? 0))) ?></span>
                <?php if (!empty($article['author_name'])): ?>
                  <?= $renderAuthorName($article) ?>
                <?php endif; ?>
              </p>
              <?php if (!empty($article['perex'])): ?>
                <p><?= h($article['perex']) ?></p>
              <?php endif; ?>
              <p>
                <a class="section-link" href="<?= h($articleLink($article)) ?>">Číst článek <span aria-hidden="true">→</span></a>
                <?php if (isset($_SESSION['cms_user_id'])): ?>
                  · <a href="<?= BASE_URL ?>/admin/blog_form.php?id=<?= (int)$article['id'] ?>">Upravit</a>
                <?php endif; ?>
              </p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?= renderPager($page, $pages, $paginBase, 'Stránkování blogu', 'Novější', 'Starší') ?>
    <?php endif; ?>
  </section>
</div>
