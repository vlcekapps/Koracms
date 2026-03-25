<?php
$themeKey = $themeManifest['key'] ?? null;

$readThemeSelect = static function (string $settingKey, string $fallback) use ($themeKey): string {
    $value = themeSettingValue($settingKey, $themeKey);
    return $value !== '' ? $value : $fallback;
};

$homeLayout = $readThemeSelect('home_layout', 'balanced');
$heroVisibility = $readThemeSelect('home_hero_visibility', 'show');
$featuredPreference = $readThemeSelect('home_featured_module', 'auto');
$primaryOrder = $readThemeSelect('home_primary_order', 'news_blog');
$utilityOrder = $readThemeSelect('home_utility_order', 'board_poll_newsletter_cta');
$newsVisibility = $readThemeSelect('home_news_visibility', 'show');
$blogVisibility = $readThemeSelect('home_blog_visibility', 'show');
$boardVisibility = $readThemeSelect('home_board_visibility', 'show');
$pollVisibility = $readThemeSelect('home_poll_visibility', 'show');
$newsletterVisibility = $readThemeSelect('home_newsletter_visibility', 'show');
$authorVisibility = $readThemeSelect('home_author_visibility', 'hide');
$ctaVisibility = $readThemeSelect('home_cta_visibility', 'hide');
$articleLink = static fn(array $article): string => articlePublicPath($article);
$newsLink = static fn(array $item): string => newsPublicPath($item);
$renderAuthorName = static function (array $entry): string {
    if (empty($entry['author_name'])) {
        return '';
    }

    $label = h((string)$entry['author_name']);
    if (!empty($entry['author_public_path'])) {
        return '<a href="' . h((string)$entry['author_public_path']) . '">' . $label . '</a>';
    }

    return '<span>' . $label . '</span>';
};

$newsAvailable = !empty($latestNews);
$blogAvailable = !empty($latestArticles);
$boardAvailable = !empty($latestBoard);
$pollAvailable = !empty($homePoll);
$newsletterAvailable = isModuleEnabled('newsletter');
$contactAvailable = isModuleEnabled('contact');

$resolveFeaturedModule = static function (string $preference) use (
    $newsAvailable,
    $blogAvailable,
    $boardAvailable,
    $pollAvailable,
    $newsletterAvailable
): string {
    $available = [
        'blog' => $blogAvailable,
        'news' => $newsAvailable,
        'board' => $boardAvailable,
        'poll' => $pollAvailable,
        'newsletter' => $newsletterAvailable,
    ];

    if ($preference === 'none') {
        return '';
    }

    if ($preference !== 'auto' && !empty($available[$preference])) {
        return $preference;
    }

    foreach (['blog', 'news', 'board', 'poll', 'newsletter'] as $candidate) {
        if (!empty($available[$candidate])) {
            return $candidate;
        }
    }

    return '';
};

$featuredModule = $resolveFeaturedModule($featuredPreference);

$newsItems = $latestNews;
$blogItems = $latestArticles;
$boardItems = $latestBoard;
$featuredNewsItem = null;
$featuredArticle = null;
$featuredBoardItem = null;

if ($featuredModule === 'news' && !empty($newsItems)) {
    $featuredNewsItem = array_shift($newsItems);
}
if ($featuredModule === 'blog' && !empty($blogItems)) {
    $featuredArticle = array_shift($blogItems);
}
if ($featuredModule === 'board' && !empty($boardItems)) {
    $featuredBoardItem = array_shift($boardItems);
}

$showHero = $heroVisibility === 'show' && $homeIntro !== '';
$showNews = $newsVisibility === 'show' && !empty($newsItems);
$showBlog = $blogVisibility === 'show' && !empty($blogItems);
$showBoard = $boardVisibility === 'show' && !empty($boardItems);
$showPoll = $pollVisibility === 'show' && $pollAvailable && $featuredModule !== 'poll';
$showNewsletter = $newsletterVisibility === 'show' && $newsletterAvailable && $featuredModule !== 'newsletter';
$showAuthor = $authorVisibility === 'show' && !empty($homeAuthor);

$ctaActions = [
    [
        'label' => 'Hledat na webu',
        'url' => BASE_URL . '/search.php',
        'class' => 'button-primary',
    ],
];
if ($contactAvailable) {
    $ctaActions[] = [
        'label' => 'Napsat zprávu',
        'url' => BASE_URL . '/contact/index.php',
        'class' => 'button-secondary',
    ];
}
if ($newsletterAvailable) {
    $ctaActions[] = [
        'label' => 'Odebírat novinky',
        'url' => BASE_URL . '/subscribe.php',
        'class' => 'button-secondary',
    ];
}
if (isPublicUser()) {
    $ctaActions[] = [
        'label' => 'Můj profil',
        'url' => BASE_URL . '/public_profile.php',
        'class' => 'button-secondary',
    ];
} elseif (!isLoggedIn()) {
    $ctaActions[] = [
        'label' => 'Přihlásit se',
        'url' => BASE_URL . '/public_login.php',
        'class' => 'button-secondary',
    ];
}

$showCta = $ctaVisibility === 'show' && !empty($ctaActions);

$heroStats = [];
if (!empty($latestArticles)) {
    $heroStats[] = [
        'value' => count($latestArticles),
        'label' => 'článků v přehledu',
    ];
}
if (!empty($latestNews)) {
    $heroStats[] = [
        'value' => count($latestNews),
        'label' => 'novinek na úvodní stránce',
    ];
}
if (!empty($latestBoard)) {
    $heroStats[] = [
        'value' => count($latestBoard),
        'label' => 'dokumentů na desce',
    ];
}
if ($pollAvailable) {
    $heroStats[] = [
        'value' => (int)$homePoll['vote_count'],
        'label' => 'hlasů v aktuální anketě',
    ];
}

$renderIntroSection = static function () use ($showHero, $homeIntro, $heroStats, $homeLayout): string {
    if (!$showHero) {
        return '';
    }

    $sectionClasses = ['surface', 'surface--hero', 'home-section', 'home-section--hero'];
    if ($homeLayout === 'editorial') {
        $sectionClasses[] = 'surface--accent';
        $sectionClasses[] = 'home-hero--editorial';
    } elseif ($homeLayout === 'compact') {
        $sectionClasses[] = 'home-hero--compact';
    } else {
        $sectionClasses[] = 'home-hero--balanced';
    }

    ob_start();
    ?>
    <section class="<?= h(implode(' ', $sectionClasses)) ?>" data-home-section="hero" aria-labelledby="uvod-nadpis">
      <div class="home-hero__meta">
        <div>
          <p class="section-kicker">Vítejte</p>
          <h2 id="uvod-nadpis" class="section-title section-title--hero">Úvodní stránka</h2>
          <div class="prose">
            <?= renderContent($homeIntro) ?>
          </div>
        </div>

        <?php if ($homeLayout === 'editorial' && !empty($heroStats)): ?>
          <ul class="hero-stats" aria-label="Rychlý přehled obsahu">
            <?php foreach ($heroStats as $stat): ?>
              <li class="hero-stats__item">
                <strong class="hero-stats__value"><?= h((string)$stat['value']) ?></strong>
                <span class="hero-stats__label"><?= h($stat['label']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </section>
    <?php

    return (string)ob_get_clean();
};

$renderAuthorSection = static function () use ($showAuthor, $homeAuthor): string {
    if (!$showAuthor) {
        return '';
    }

    $sectionTitle = currentSiteProfileKey() === 'personal' ? 'O mně' : 'O autorovi';
    $ctaLabel = currentSiteProfileKey() === 'personal' ? 'Celý profil' : 'Profil autora';

    ob_start();
    ?>
    <section class="surface surface--accent home-section home-section--author" data-home-section="author" aria-labelledby="autor-nadpis">
      <div class="author-panel author-panel--home">
        <div class="author-panel__media">
          <?php if (!empty($homeAuthor['author_avatar_url'])): ?>
            <img
              class="author-avatar"
              src="<?= h((string)$homeAuthor['author_avatar_url']) ?>"
              alt="Profilová fotografie autora <?= h((string)$homeAuthor['author_display_name']) ?>"
              loading="lazy">
          <?php else: ?>
            <div class="author-avatar author-avatar--placeholder" aria-hidden="true">
              <?= h(mb_strtoupper(mb_substr((string)$homeAuthor['author_display_name'], 0, 1))) ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="author-panel__content">
          <p class="section-kicker">Autor</p>
          <h2 id="autor-nadpis" class="section-title"><?= h($sectionTitle) ?></h2>
          <p class="author-panel__name"><?= h((string)$homeAuthor['author_display_name']) ?></p>
          <?php if (!empty($homeAuthor['author_bio'])): ?>
            <div class="prose author-panel__bio">
              <?= renderContent((string)$homeAuthor['author_bio']) ?>
            </div>
          <?php endif; ?>
          <div class="button-row button-row--start">
            <a class="button-primary" href="<?= h((string)$homeAuthor['author_public_path']) ?>"><?= h($ctaLabel) ?></a>
            <?php if (!empty($homeAuthor['author_website_url'])): ?>
              <a class="button-secondary" href="<?= h((string)$homeAuthor['author_website_url']) ?>" target="_blank" rel="noopener noreferrer">Web autora</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>
    <?php

    return (string)ob_get_clean();
};

$renderNewsSection = static function (array $items, bool $compactCards = false) use (
    $newsLink,
    $renderAuthorName
): string {
    if ($items === []) {
        return '';
    }

    $gridClass = 'card-grid card-grid--compact';
    if ($compactCards) {
        $gridClass .= ' home-grid--dense';
    }

    ob_start();
    ?>
    <section class="surface home-section home-section--news" data-home-section="news" aria-labelledby="novinky-nadpis">
      <div class="section-heading">
        <div>
          <p class="section-kicker">Přehled</p>
          <h2 id="novinky-nadpis" class="section-title">Novinky</h2>
        </div>
        <a class="section-link" href="<?= BASE_URL ?>/news/index.php">Všechny novinky <span aria-hidden="true">→</span></a>
      </div>
      <div class="<?= h($gridClass) ?>">
        <?php foreach ($items as $item): ?>
          <article class="card">
            <div class="card__body">
              <p class="meta-row meta-row--tight">
                <time datetime="<?= h(str_replace(' ', 'T', (string)$item['created_at'])) ?>"><?= formatCzechDate((string)$item['created_at']) ?></time>
                <?php if (!empty($item['author_name'])): ?>
                  <?= $renderAuthorName($item) ?>
                <?php endif; ?>
              </p>
              <h3 class="card__title">
                <a href="<?= h($newsLink($item)) ?>"><?= h((string)$item['title']) ?></a>
              </h3>
              <?php if (!empty($item['excerpt'])): ?>
                <p><?= h((string)$item['excerpt']) ?></p>
              <?php endif; ?>
              <p><a class="section-link" href="<?= h($newsLink($item)) ?>">Číst dále <span aria-hidden="true">→</span></a></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php

    return (string)ob_get_clean();
};

$renderBlogSection = static function (array $items, bool $featureLead = false, bool $compactCards = false) use (
    $articleLink,
    $renderAuthorName
): string {
    if ($items === []) {
        return '';
    }

    $gridClass = 'card-grid';
    if ($compactCards) {
        $gridClass .= ' card-grid--compact home-grid--dense';
    }

    ob_start();
    ?>
    <section class="surface home-section home-section--blog<?= $featureLead ? ' home-section--featured' : '' ?>" data-home-section="blog" aria-labelledby="blog-nadpis">
      <div class="section-heading">
        <div>
          <p class="section-kicker">Obsah</p>
          <h2 id="blog-nadpis" class="section-title">Blog</h2>
        </div>
        <a class="section-link" href="<?= BASE_URL ?>/blog/index.php">Všechny články <span aria-hidden="true">→</span></a>
      </div>

      <?php if ($featureLead && count($items) > 1): ?>
        <?php
          $leadArticle = $items[0];
          $secondaryArticles = array_slice($items, 1);
        ?>
        <div class="home-featured">
          <article class="card card--feature home-featured__lead">
            <?php if (!empty($leadArticle['image_file'])): ?>
              <a class="card__media" href="<?= h($articleLink($leadArticle)) ?>">
                <img src="<?= BASE_URL ?>/uploads/articles/thumbs/<?= rawurlencode($leadArticle['image_file']) ?>"
                     alt="<?= h($leadArticle['title']) ?>" loading="lazy">
              </a>
            <?php endif; ?>
            <div class="card__body">
              <p class="section-kicker">Doporučený článek</p>
              <p class="meta-row meta-row--tight">
                <?php if (!empty($leadArticle['category'])): ?>
                  <span class="pill"><?= h($leadArticle['category']) ?></span>
                <?php endif; ?>
                <span><?= readingTime(($leadArticle['perex'] ?? '') . ($leadArticle['content'] ?? '')) ?> min čtení</span>
                <?php if (!empty($leadArticle['author_name'])): ?>
                  <?= $renderAuthorName($leadArticle) ?>
                <?php endif; ?>
              </p>
              <h3 class="card__title card__title--feature">
                <a href="<?= h($articleLink($leadArticle)) ?>"><?= h($leadArticle['title']) ?></a>
              </h3>
              <?php if (!empty($leadArticle['perex'])): ?>
                <p><?= h($leadArticle['perex']) ?></p>
              <?php endif; ?>
              <p>
                <a class="section-link" href="<?= h($articleLink($leadArticle)) ?>">Číst dále <span aria-hidden="true">→</span></a>
                <?php if (isset($_SESSION['cms_user_id'])): ?>
                  · <a href="<?= BASE_URL ?>/admin/blog_form.php?id=<?= (int)$leadArticle['id'] ?>">Upravit</a>
                <?php endif; ?>
              </p>
            </div>
          </article>

          <div class="home-featured__side <?= h($gridClass) ?>">
            <?php foreach ($secondaryArticles as $article): ?>
              <article class="card">
                <?php if (!empty($article['image_file'])): ?>
                  <a class="card__media" href="<?= h($articleLink($article)) ?>">
                    <img src="<?= BASE_URL ?>/uploads/articles/thumbs/<?= rawurlencode($article['image_file']) ?>"
                         alt="<?= h($article['title']) ?>" loading="lazy">
                  </a>
                <?php endif; ?>
                <div class="card__body">
                  <p class="meta-row meta-row--tight">
                    <?php if (!empty($article['category'])): ?>
                      <span class="pill"><?= h($article['category']) ?></span>
                    <?php endif; ?>
                    <span><?= readingTime(($article['perex'] ?? '') . ($article['content'] ?? '')) ?> min čtení</span>
                    <?php if (!empty($article['author_name'])): ?>
                      <?= $renderAuthorName($article) ?>
                    <?php endif; ?>
                  </p>
                  <h3 class="card__title">
                    <a href="<?= h($articleLink($article)) ?>"><?= h($article['title']) ?></a>
                  </h3>
                  <?php if (!empty($article['perex'])): ?>
                    <p><?= h($article['perex']) ?></p>
                  <?php endif; ?>
                  <p>
                    <a class="section-link" href="<?= h($articleLink($article)) ?>">Číst dále <span aria-hidden="true">→</span></a>
                    <?php if (isset($_SESSION['cms_user_id'])): ?>
                      · <a href="<?= BASE_URL ?>/admin/blog_form.php?id=<?= (int)$article['id'] ?>">Upravit</a>
                    <?php endif; ?>
                  </p>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="<?= h($gridClass) ?>">
          <?php foreach ($items as $article): ?>
            <article class="card">
              <?php if (!empty($article['image_file'])): ?>
                <a class="card__media" href="<?= h($articleLink($article)) ?>">
                  <img src="<?= BASE_URL ?>/uploads/articles/thumbs/<?= rawurlencode($article['image_file']) ?>"
                       alt="<?= h($article['title']) ?>" loading="lazy">
                </a>
              <?php endif; ?>
              <div class="card__body">
                <p class="meta-row meta-row--tight">
                  <?php if (!empty($article['category'])): ?>
                    <span class="pill"><?= h($article['category']) ?></span>
                  <?php endif; ?>
                  <span><?= readingTime(($article['perex'] ?? '') . ($article['content'] ?? '')) ?> min čtení</span>
                  <?php if (!empty($article['author_name'])): ?>
                    <?= $renderAuthorName($article) ?>
                  <?php endif; ?>
                </p>
                <h3 class="card__title">
                  <a href="<?= h($articleLink($article)) ?>"><?= h($article['title']) ?></a>
                </h3>
                <?php if (!empty($article['perex'])): ?>
                  <p><?= h($article['perex']) ?></p>
                <?php endif; ?>
                <p>
                  <a class="section-link" href="<?= h($articleLink($article)) ?>">Číst dále <span aria-hidden="true">→</span></a>
                  <?php if (isset($_SESSION['cms_user_id'])): ?>
                    · <a href="<?= BASE_URL ?>/admin/blog_form.php?id=<?= (int)$article['id'] ?>">Upravit</a>
                  <?php endif; ?>
                </p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <?php

    return (string)ob_get_clean();
};

$renderBoardSection = static function (array $items): string {
    if ($items === []) {
        return '';
    }

    $boardLabel = boardModulePublicLabel();
    ob_start();
    ?>
    <section class="surface home-section home-section--board" data-home-section="board" aria-labelledby="deska-nadpis">
      <div class="section-heading">
        <div>
          <p class="section-kicker"><?= h(boardModuleSectionKicker()) ?></p>
          <h2 id="deska-nadpis" class="section-title"><?= h($boardLabel) ?></h2>
        </div>
        <a class="section-link" href="<?= BASE_URL ?>/board/index.php"><?= h(boardModuleAllItemsLabel()) ?> <span aria-hidden="true">→</span></a>
      </div>
      <ul class="link-list">
        <?php foreach ($items as $boardItem): ?>
          <li class="link-list__item board-item">
            <?php if (!empty($boardItem['image_url'])): ?>
              <a class="board-item__media" href="<?= h(boardPublicPath($boardItem)) ?>" aria-hidden="true" tabindex="-1">
                <img class="board-item__image" src="<?= h((string)$boardItem['image_url']) ?>" alt="" loading="lazy">
              </a>
            <?php endif; ?>
            <div class="board-item__content">
              <a class="link-list__title" href="<?= h(boardPublicPath($boardItem)) ?>">
                <?= h((string)$boardItem['title']) ?>
              </a>
              <p class="meta-row meta-row--tight board-item__flags">
                <span class="pill"><?= h((string)$boardItem['board_type_label']) ?></span>
                <?php if ((int)($boardItem['is_pinned'] ?? 0) === 1): ?>
                  <span class="pill">Důležité</span>
                <?php endif; ?>
                <?php if (!empty($boardItem['category_name'])): ?>
                  <span class="pill"><?= h((string)$boardItem['category_name']) ?></span>
                <?php endif; ?>
                <span>Vyvěšeno <time datetime="<?= h((string)$boardItem['posted_date']) ?>"><?= formatCzechDate((string)$boardItem['posted_date']) ?></time></span>
                <?php if ((int)($boardItem['file_size'] ?? 0) > 0): ?>
                  <span><?= h(formatFileSize((int)$boardItem['file_size'])) ?></span>
                <?php endif; ?>
              </p>
              <?php if (!empty($boardItem['excerpt_plain'])): ?>
                <p class="board-item__summary"><?= h((string)$boardItem['excerpt_plain']) ?></p>
              <?php endif; ?>
              <?php if (!empty($boardItem['has_contact'])): ?>
                <p class="board-item__contact">
                  <strong>Kontakt:</strong>
                  <?php if (!empty($boardItem['contact_name'])): ?>
                    <span><?= h((string)$boardItem['contact_name']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($boardItem['contact_phone'])): ?>
                    <span><a href="tel:<?= h((string)preg_replace('/\s+/', '', (string)$boardItem['contact_phone'])) ?>"><?= h((string)$boardItem['contact_phone']) ?></a></span>
                  <?php endif; ?>
                  <?php if (!empty($boardItem['contact_email'])): ?>
                    <span><a href="mailto:<?= h((string)$boardItem['contact_email']) ?>"><?= h((string)$boardItem['contact_email']) ?></a></span>
                  <?php endif; ?>
                </p>
              <?php endif; ?>
              <div class="button-row button-row--start">
                <a class="section-link" href="<?= h(boardPublicPath($boardItem)) ?>">Zobrazit detail <span aria-hidden="true">→</span></a>
                <?php if ((string)($boardItem['filename'] ?? '') !== ''): ?>
                  <a class="section-link" href="<?= moduleFileUrl('board', (int)$boardItem['id']) ?>" download="<?= h((string)$boardItem['original_name']) ?>">Stáhnout přílohu <span aria-hidden="true">→</span></a>
                <?php endif; ?>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php

    return (string)ob_get_clean();
};

$renderPollSection = static function () use ($showPoll, $homePoll): string {
    if (!$showPoll) {
        return '';
    }

    ob_start();
    ?>
    <section class="surface surface--accent home-section home-section--poll" data-home-section="poll" aria-labelledby="anketa-nadpis">
      <p class="section-kicker">Zapojte se</p>
      <h2 id="anketa-nadpis" class="section-title">Anketa</h2>
      <p><strong><?= h($homePoll['question']) ?></strong></p>
      <p class="meta-row"><span><?= (int)$homePoll['vote_count'] ?> hlasů</span></p>
      <p><a class="section-link" href="<?= h((string)$homePoll['public_path']) ?>">Hlasovat <span aria-hidden="true">→</span></a></p>
    </section>
    <?php

    return (string)ob_get_clean();
};

$renderNewsletterSection = static function () use ($showNewsletter, $contactAvailable): string {
    if (!$showNewsletter) {
        return '';
    }

    ob_start();
    ?>
    <section class="surface surface--accent home-section home-section--newsletter" data-home-section="newsletter" aria-labelledby="newsletter-nadpis">
      <div class="section-heading">
        <div>
          <h2 id="newsletter-nadpis" class="section-title">Odběr novinek</h2>
          <p class="section-subtitle">Zájemci mohou dostávat nové články, aktuality a pozvánky přímo e-mailem.</p>
        </div>
      </div>
      <div class="button-row button-row--start">
        <a class="button-primary" href="<?= BASE_URL ?>/subscribe.php">Přihlásit odběr</a>
        <?php if ($contactAvailable): ?>
          <a class="button-secondary" href="<?= BASE_URL ?>/contact/index.php">Napsat zprávu</a>
        <?php endif; ?>
      </div>
    </section>
    <?php

    return (string)ob_get_clean();
};

$renderCtaSection = static function () use ($showCta, $ctaActions): string {
    if (!$showCta) {
        return '';
    }

    ob_start();
    ?>
    <section class="surface home-section home-section--cta" data-home-section="cta" aria-labelledby="cta-nadpis">
      <div class="section-heading">
        <div>
          <p class="section-kicker">Užitečné odkazy</p>
          <h2 id="cta-nadpis" class="section-title">Rychlý přístup</h2>
          <p class="section-subtitle">Vyberte si, kam chcete pokračovat.</p>
        </div>
      </div>
      <div class="button-row button-row--start">
        <?php foreach ($ctaActions as $action): ?>
          <a class="<?= h($action['class']) ?>" href="<?= h($action['url']) ?>"><?= h($action['label']) ?></a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php

    return (string)ob_get_clean();
};

$renderFeaturedSection = static function () use (
    $featuredModule,
    $featuredNewsItem,
    $featuredArticle,
    $featuredBoardItem,
    $pollAvailable,
    $homePoll,
    $newsletterAvailable,
    $contactAvailable,
    $articleLink,
    $newsLink,
    $renderAuthorName
): string {
    if ($featuredModule === '') {
        return '';
    }

    ob_start();
    switch ($featuredModule) {
        case 'blog':
            if (!$featuredArticle) {
                break;
            }
            ?>
            <section class="surface surface--accent home-section home-section--featured-module" data-home-section="featured" data-feature-source="blog" aria-labelledby="featured-nadpis">
              <div class="section-heading">
                <div>
                  <p class="section-kicker">Doporučujeme</p>
                  <h2 id="featured-nadpis" class="section-title">Doporučený článek</h2>
                </div>
                <a class="section-link" href="<?= BASE_URL ?>/blog/index.php">Přejít na blog <span aria-hidden="true">→</span></a>
              </div>
              <article class="card card--feature">
                <?php if (!empty($featuredArticle['image_file'])): ?>
                  <a class="card__media" href="<?= h($articleLink($featuredArticle)) ?>">
                    <img src="<?= BASE_URL ?>/uploads/articles/thumbs/<?= rawurlencode($featuredArticle['image_file']) ?>"
                         alt="<?= h($featuredArticle['title']) ?>" loading="lazy">
                  </a>
                <?php endif; ?>
                <div class="card__body">
                  <p class="meta-row meta-row--tight">
                    <?php if (!empty($featuredArticle['category'])): ?>
                      <span class="pill"><?= h($featuredArticle['category']) ?></span>
                    <?php endif; ?>
                    <span><?= readingTime(($featuredArticle['perex'] ?? '') . ($featuredArticle['content'] ?? '')) ?> min čtení</span>
                    <?php if (!empty($featuredArticle['author_name'])): ?>
                      <?= $renderAuthorName($featuredArticle) ?>
                    <?php endif; ?>
                  </p>
                  <h3 class="card__title card__title--feature">
                    <a href="<?= h($articleLink($featuredArticle)) ?>"><?= h($featuredArticle['title']) ?></a>
                  </h3>
                  <?php if (!empty($featuredArticle['perex'])): ?>
                    <p><?= h($featuredArticle['perex']) ?></p>
                  <?php endif; ?>
                  <p><a class="section-link" href="<?= h($articleLink($featuredArticle)) ?>">Číst článek <span aria-hidden="true">→</span></a></p>
                </div>
              </article>
            </section>
            <?php
            break;

        case 'news':
            if (!$featuredNewsItem) {
                break;
            }
            ?>
            <section class="surface surface--accent home-section home-section--featured-module" data-home-section="featured" data-feature-source="news" aria-labelledby="featured-nadpis">
              <div class="section-heading">
                <div>
                  <p class="section-kicker">Aktuálně</p>
                  <h2 id="featured-nadpis" class="section-title">Zvýrazněná novinka</h2>
                </div>
                <a class="section-link" href="<?= BASE_URL ?>/news/index.php">Přejít na novinky <span aria-hidden="true">→</span></a>
              </div>
              <article class="card card--highlighted">
                <div class="card__body">
                  <p class="meta-row meta-row--tight">
                    <time datetime="<?= h(str_replace(' ', 'T', (string)$featuredNewsItem['created_at'])) ?>"><?= formatCzechDate((string)$featuredNewsItem['created_at']) ?></time>
                    <?php if (!empty($featuredNewsItem['author_name'])): ?>
                      <?= $renderAuthorName($featuredNewsItem) ?>
                    <?php endif; ?>
                  </p>
                  <h3 class="card__title card__title--feature">
                    <a href="<?= h($newsLink($featuredNewsItem)) ?>"><?= h((string)$featuredNewsItem['title']) ?></a>
                  </h3>
                  <?php if (!empty($featuredNewsItem['excerpt'])): ?>
                    <p><?= h((string)$featuredNewsItem['excerpt']) ?></p>
                  <?php endif; ?>
                  <p><a class="section-link" href="<?= h($newsLink($featuredNewsItem)) ?>">Číst novinku <span aria-hidden="true">→</span></a></p>
                </div>
              </article>
            </section>
            <?php
            break;

        case 'board':
            if (!$featuredBoardItem) {
                break;
            }
            $featuredBoardPath = boardPublicPath($featuredBoardItem);
            $featuredBoardSummary = (string)($featuredBoardItem['excerpt_plain'] ?? '');
            ?>
            <section class="surface surface--accent home-section home-section--featured-module" data-home-section="featured" data-feature-source="board" aria-labelledby="featured-nadpis">
              <div class="section-heading">
                <div>
                  <p class="section-kicker"><?= h(boardModuleSectionKicker()) ?></p>
                  <h2 id="featured-nadpis" class="section-title">Zvýrazněná položka</h2>
                </div>
                <a class="section-link" href="<?= BASE_URL ?>/board/index.php"><?= h(boardModuleAllItemsLabel()) ?> <span aria-hidden="true">→</span></a>
              </div>
              <article class="card card--highlighted">
                <?php if (!empty($featuredBoardItem['image_url'])): ?>
                  <a class="card__media" href="<?= h($featuredBoardPath) ?>">
                    <img src="<?= h((string)$featuredBoardItem['image_url']) ?>" alt="" loading="lazy">
                  </a>
                <?php endif; ?>
                <div class="card__body">
                  <p class="meta-row meta-row--tight">
                    <span class="pill"><?= h((string)$featuredBoardItem['board_type_label']) ?></span>
                    <?php if ((int)($featuredBoardItem['is_pinned'] ?? 0) === 1): ?>
                      <span class="pill">Důležité</span>
                    <?php endif; ?>
                    <?php if (!empty($featuredBoardItem['category_name'])): ?>
                      <span class="pill"><?= h((string)$featuredBoardItem['category_name']) ?></span>
                    <?php endif; ?>
                    <time datetime="<?= h((string)$featuredBoardItem['posted_date']) ?>"><?= formatCzechDate((string)$featuredBoardItem['posted_date']) ?></time>
                    <?php if ((int)($featuredBoardItem['file_size'] ?? 0) > 0): ?>
                      <span><?= h(formatFileSize((int)$featuredBoardItem['file_size'])) ?></span>
                    <?php endif; ?>
                  </p>
                  <h3 class="card__title">
                    <a href="<?= h($featuredBoardPath) ?>"><?= h($featuredBoardItem['title']) ?></a>
                  </h3>
                  <?php if ($featuredBoardSummary !== ''): ?>
                    <p><?= h($featuredBoardSummary) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($featuredBoardItem['has_contact'])): ?>
                    <p class="board-item__contact">
                      <strong>Kontakt:</strong>
                      <?php if (!empty($featuredBoardItem['contact_name'])): ?>
                        <span><?= h((string)$featuredBoardItem['contact_name']) ?></span>
                      <?php endif; ?>
                      <?php if (!empty($featuredBoardItem['contact_phone'])): ?>
                        <span><a href="tel:<?= h((string)preg_replace('/\s+/', '', (string)$featuredBoardItem['contact_phone'])) ?>"><?= h((string)$featuredBoardItem['contact_phone']) ?></a></span>
                      <?php endif; ?>
                      <?php if (!empty($featuredBoardItem['contact_email'])): ?>
                        <span><a href="mailto:<?= h((string)$featuredBoardItem['contact_email']) ?>"><?= h((string)$featuredBoardItem['contact_email']) ?></a></span>
                      <?php endif; ?>
                    </p>
                  <?php endif; ?>
                  <div class="button-row button-row--start">
                    <a class="section-link" href="<?= h($featuredBoardPath) ?>">Zobrazit detail <span aria-hidden="true">→</span></a>
                    <?php if ((string)($featuredBoardItem['filename'] ?? '') !== ''): ?>
                      <a class="section-link" href="<?= moduleFileUrl('board', (int)$featuredBoardItem['id']) ?>" download="<?= h((string)$featuredBoardItem['original_name']) ?>">Stáhnout přílohu <span aria-hidden="true">→</span></a>
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            </section>
            <?php
            break;

        case 'poll':
            if (!$pollAvailable) {
                break;
            }
            ?>
            <section class="surface surface--accent home-section home-section--featured-module" data-home-section="featured" data-feature-source="poll" aria-labelledby="featured-nadpis">
              <p class="section-kicker">Zapojte se</p>
              <h2 id="featured-nadpis" class="section-title">Aktuální anketa</h2>
              <p><strong><?= h($homePoll['question']) ?></strong></p>
              <p class="meta-row"><span><?= (int)$homePoll['vote_count'] ?> hlasů</span></p>
              <div class="button-row button-row--start">
                <a class="button-primary" href="<?= h((string)$homePoll['public_path']) ?>">Hlasovat</a>
                <a class="button-secondary" href="<?= BASE_URL ?>/polls/index.php">Všechny ankety</a>
              </div>
            </section>
            <?php
            break;

        case 'newsletter':
            if (!$newsletterAvailable) {
                break;
            }
            ?>
            <section class="surface surface--accent home-section home-section--featured-module" data-home-section="featured" data-feature-source="newsletter" aria-labelledby="featured-nadpis">
              <h2 id="featured-nadpis" class="section-title">Zůstaňte v kontaktu</h2>
              <p class="section-subtitle">Přihlaste se k odběru a dostávejte nové články, aktuality a pozvánky přímo e-mailem.</p>
              <div class="button-row button-row--start">
                <a class="button-primary" href="<?= BASE_URL ?>/subscribe.php">Přihlásit odběr</a>
                <?php if ($contactAvailable): ?>
                  <a class="button-secondary" href="<?= BASE_URL ?>/contact/index.php">Napsat zprávu</a>
                <?php endif; ?>
              </div>
            </section>
            <?php
            break;
    }

    return (string)ob_get_clean();
};

$introHtml = $renderIntroSection();
$featuredHtml = $renderFeaturedSection();
$authorHtml = $renderAuthorSection();
$newsHtml = $showNews ? $renderNewsSection($newsItems, $homeLayout === 'compact') : '';
$blogHtml = $showBlog ? $renderBlogSection($blogItems, $homeLayout === 'editorial' && $featuredModule !== 'blog', $homeLayout === 'compact') : '';
$boardHtml = $renderBoardSection($showBoard ? $boardItems : []);
$pollHtml = $renderPollSection();
$newsletterHtml = $renderNewsletterSection();
$ctaHtml = $renderCtaSection();

$primarySections = [
    'news' => $newsHtml,
    'blog' => $blogHtml,
];
$primaryOrderKeys = $primaryOrder === 'blog_news'
    ? ['blog', 'news']
    : ['news', 'blog'];
$orderedPrimaryHtml = [];
foreach ($primaryOrderKeys as $sectionKey) {
    if (($primarySections[$sectionKey] ?? '') !== '') {
        $orderedPrimaryHtml[] = $primarySections[$sectionKey];
    }
}

$utilitySections = [
    'board' => $boardHtml,
    'poll' => $pollHtml,
    'newsletter' => $newsletterHtml,
    'cta' => $ctaHtml,
];
$utilityOrderKeys = match ($utilityOrder) {
    'newsletter_cta_board_poll' => ['newsletter', 'cta', 'board', 'poll'],
    'cta_board_poll_newsletter' => ['cta', 'board', 'poll', 'newsletter'],
    default => ['board', 'poll', 'newsletter', 'cta'],
};
$orderedUtilityHtml = [];
foreach ($utilityOrderKeys as $sectionKey) {
    if (($utilitySections[$sectionKey] ?? '') !== '') {
        $orderedUtilityHtml[] = $utilitySections[$sectionKey];
    }
}

$utilityHtml = '';
if ($orderedUtilityHtml !== []) {
    ob_start();
    ?>
    <div class="stack-sections stack-sections--tight home-utility-stack">
      <?php foreach ($orderedUtilityHtml as $sectionHtml): ?>
        <?= $sectionHtml ?>
      <?php endforeach; ?>
    </div>
    <?php
    $utilityHtml = (string)ob_get_clean();
}

$hasAnyContent = $introHtml !== ''
    || $featuredHtml !== ''
    || $authorHtml !== ''
    || $orderedPrimaryHtml !== []
    || $utilityHtml !== '';

$pageStackClasses = ['page-stack', 'page-stack--home', 'page-stack--home-' . $homeLayout];
?>
<div class="<?= h(implode(' ', $pageStackClasses)) ?>">
  <?= $introHtml ?>
  <?= $featuredHtml ?>
  <?= $authorHtml ?>

  <?php if ($homeLayout === 'editorial'): ?>
    <?php if ($orderedPrimaryHtml !== []): ?>
      <?php $editorialLead = array_shift($orderedPrimaryHtml); ?>
      <?= $editorialLead ?>
    <?php endif; ?>

    <?php if ($orderedPrimaryHtml !== [] || $utilityHtml !== ''): ?>
      <div class="split-grid">
        <?php foreach ($orderedPrimaryHtml as $sectionHtml): ?>
          <?= $sectionHtml ?>
        <?php endforeach; ?>
        <?= $utilityHtml ?>
      </div>
    <?php endif; ?>
  <?php elseif ($homeLayout === 'compact'): ?>
    <?php if ($orderedPrimaryHtml !== []): ?>
      <div class="split-grid">
        <?php foreach ($orderedPrimaryHtml as $sectionHtml): ?>
          <?= $sectionHtml ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?= $utilityHtml ?>
  <?php else: ?>
    <?php foreach ($orderedPrimaryHtml as $sectionHtml): ?>
      <?= $sectionHtml ?>
    <?php endforeach; ?>
    <?= $utilityHtml ?>
  <?php endif; ?>

  <?php if (!$hasAnyContent): ?>
    <section class="surface empty-state" aria-labelledby="obsah-priprava">
      <h2 id="obsah-priprava" class="section-title">Obsah se připravuje</h2>
      <p>Úvodní stránka je připravena pro první obsahové bloky a modulové sekce.</p>
    </section>
  <?php endif; ?>
</div>
