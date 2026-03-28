<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
autoCompleteBookings();

$accountLabel = isSuperAdmin() ? 'Superadmin' : userRoleLabel(currentUserRole());
$accountName = trim(currentUserDisplayName()) !== '' ? currentUserDisplayName() : $accountLabel;

$safeCount = static function (PDO $pdo, string $sql, array $params = []): ?int {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (\PDOException $e) {
        return null;
    }
};

$canManageBlog = currentUserHasCapability('blog_manage_own');
$canManageAllBlog = currentUserHasCapability('blog_manage_all');
$canApproveBlog = currentUserHasCapability('blog_approve');
$canManageNews = currentUserHasCapability('news_manage_own');
$canManageAllNews = currentUserHasCapability('news_manage_all');
$canApproveNews = currentUserHasCapability('news_approve');
$canManageSharedContent = currentUserHasCapability('content_manage_shared');
$canApproveSharedContent = currentUserHasCapability('content_approve_shared');
$canManageComments = currentUserHasCapability('comments_manage');
$canManageMessages = currentUserHasCapability('messages_manage');
$canManageBookings = currentUserHasCapability('bookings_manage');
$canManageNewsletter = currentUserHasCapability('newsletter_manage');
$canManageSettings = currentUserHasCapability('settings_manage');
$canManageUsers = currentUserHasCapability('users_manage');
$canViewStatistics = currentUserHasCapability('statistics_view');
$canManageImportExport = currentUserHasCapability('import_export_manage');

$dashboardMode = 'general';
if (
    $canManageBookings
    && !$canManageBlog
    && !$canManageNews
    && !$canManageSharedContent
    && !$canManageMessages
    && !$canManageNewsletter
    && !$canManageSettings
    && !$canManageUsers
) {
    $dashboardMode = 'bookings';
} elseif (
    $canManageMessages
    && !$canManageBlog
    && !$canManageNews
    && !$canManageSharedContent
    && !$canManageBookings
    && !$canManageNewsletter
    && !$canManageSettings
    && !$canManageUsers
) {
    $dashboardMode = 'moderation';
} elseif (
    ($canManageBlog || $canManageNews || $canManageSharedContent)
    && !$canManageMessages
    && !$canManageBookings
    && !$canManageNewsletter
    && !$canManageSettings
    && !$canManageUsers
) {
    $dashboardMode = 'content';
}

$overviewRows = [];

if ($canManageBlog && isModuleEnabled('blog')) {
    $blogScopeSql = $canManageAllBlog ? 'SELECT COUNT(*) FROM cms_articles' : 'SELECT COUNT(*) FROM cms_articles WHERE author_id = ?';
    $blogScopeParams = $canManageAllBlog ? [] : [currentUserId()];
    $blogCount = $safeCount($pdo, $blogScopeSql, $blogScopeParams);
    if ($blogCount !== null) {
        $overviewRows[] = [
            'label' => $canManageAllBlog ? 'Články' : 'Vaše články',
            'count' => $blogCount,
            'url' => 'blog.php',
        ];
    }
}

if ($canManageNews && isModuleEnabled('news')) {
    $newsScopeSql = $canManageAllNews ? 'SELECT COUNT(*) FROM cms_news' : 'SELECT COUNT(*) FROM cms_news WHERE author_id = ?';
    $newsScopeParams = $canManageAllNews ? [] : [currentUserId()];
    $newsCount = $safeCount($pdo, $newsScopeSql, $newsScopeParams);
    if ($newsCount !== null) {
        $overviewRows[] = [
            'label' => $canManageAllNews ? 'Novinky' : 'Vaše novinky',
            'count' => $newsCount,
            'url' => 'news.php',
        ];
    }
}

if ($canManageSharedContent) {
    $sharedOverview = [
        ['module' => null, 'table' => 'cms_pages', 'label' => 'Stránky', 'url' => 'pages.php'],
        ['module' => 'events', 'table' => 'cms_events', 'label' => 'Události', 'url' => 'events.php'],
        ['module' => 'podcast', 'table' => 'cms_podcasts', 'label' => 'Podcasty', 'url' => 'podcast_shows.php'],
        ['module' => 'places', 'table' => 'cms_places', 'label' => 'Místa', 'url' => 'places.php'],
        ['module' => 'downloads', 'table' => 'cms_downloads', 'label' => 'Ke stažení', 'url' => 'downloads.php'],
        ['module' => 'food', 'table' => 'cms_food_cards', 'label' => 'Lístky', 'url' => 'food.php'],
        ['module' => 'faq', 'table' => 'cms_faqs', 'label' => 'FAQ', 'url' => 'faq.php'],
        ['module' => 'board', 'table' => 'cms_board', 'label' => 'Úřední deska', 'url' => 'board.php'],
        ['module' => 'gallery', 'table' => 'cms_gallery_albums', 'label' => 'Galerie', 'url' => 'gallery_albums.php'],
        ['module' => 'polls', 'table' => 'cms_polls', 'label' => 'Ankety', 'url' => 'polls.php'],
    ];

    foreach ($sharedOverview as $item) {
        if (!empty($item['module']) && !isModuleEnabled($item['module'])) {
            continue;
        }
        $itemCount = $safeCount($pdo, "SELECT COUNT(*) FROM {$item['table']}");
        if ($itemCount === null) {
            continue;
        }
        $overviewRows[] = [
            'label' => $item['label'],
            'count' => $itemCount,
            'url' => $item['url'],
        ];
    }
}

if ($canManageComments && isModuleEnabled('blog')) {
    $commentCount = $safeCount($pdo, 'SELECT COUNT(*) FROM cms_comments');
    if ($commentCount !== null) {
        $overviewRows[] = [
            'label' => 'Komentáře',
            'count' => $commentCount,
            'url' => 'comments.php',
        ];
    }
}

if ($canManageMessages) {
    $newContactCount = isModuleEnabled('contact') ? unreadContactCount() : 0;
    $newChatCount = isModuleEnabled('chat') ? unreadChatCount() : 0;
    if (isModuleEnabled('contact')) {
        $contactCount = $safeCount($pdo, 'SELECT COUNT(*) FROM cms_contact');
        if ($contactCount !== null) {
            $overviewRows[] = [
                'label' => 'Kontaktní zprávy',
                'count' => $contactCount,
                'url' => 'contact.php',
            ];
        }
    }
    if (isModuleEnabled('chat')) {
        $chatCount = $safeCount($pdo, 'SELECT COUNT(*) FROM cms_chat');
        if ($chatCount !== null) {
            $overviewRows[] = [
                'label' => 'Chat zprávy',
                'count' => $chatCount,
                'url' => 'chat.php',
            ];
        }
    }
}

if ($canManageBookings && isModuleEnabled('reservations')) {
    $bookingCount = $safeCount($pdo, 'SELECT COUNT(*) FROM cms_res_bookings');
    if ($bookingCount !== null) {
        $overviewRows[] = [
            'label' => 'Rezervace',
            'count' => $bookingCount,
            'url' => 'res_bookings.php',
        ];
    }
}

if ($canManageNewsletter && isModuleEnabled('newsletter')) {
    $newsletterCounts = newsletterSubscriberCounts($pdo);
    $subscriberCount = $newsletterCounts['confirmed'] + $newsletterCounts['pending'];
    if ($subscriberCount >= 0) {
        $overviewRows[] = [
            'label' => 'Odběratelé newsletteru',
            'count' => $subscriberCount,
            'url' => 'newsletter.php',
        ];
    }
}

if ($canManageUsers) {
    $userCount = $safeCount($pdo, 'SELECT COUNT(*) FROM cms_users');
    if ($userCount !== null) {
        $overviewRows[] = [
            'label' => 'Uživatelé',
            'count' => $userCount,
            'url' => 'users.php',
        ];
    }
}

$pendingReviewItems = canAccessReviewQueue() ? pendingReviewSummary($pdo) : [];
$pendingReviewTotal = array_sum(array_column($pendingReviewItems, 'count'));

$contentSummaries = [];
if ($canManageBlog && isModuleEnabled('blog')) {
    $blogTotal = $canManageAllBlog
        ? $safeCount($pdo, 'SELECT COUNT(*) FROM cms_articles')
        : $safeCount($pdo, 'SELECT COUNT(*) FROM cms_articles WHERE author_id = ?', [currentUserId()]);
    $blogPublished = $canManageAllBlog
        ? $safeCount($pdo, "SELECT COUNT(*) FROM cms_articles WHERE status = 'published'")
        : $safeCount($pdo, "SELECT COUNT(*) FROM cms_articles WHERE author_id = ? AND status = 'published'", [currentUserId()]);
    $blogPending = $canManageAllBlog
        ? $safeCount($pdo, "SELECT COUNT(*) FROM cms_articles WHERE status = 'pending'")
        : $safeCount($pdo, "SELECT COUNT(*) FROM cms_articles WHERE author_id = ? AND status = 'pending'", [currentUserId()]);

    if ($blogTotal !== null && $blogPublished !== null && $blogPending !== null) {
        $contentSummaries[] = [
            'heading' => $canManageAllBlog ? 'Blog' : 'Vaše články',
            'items' => [
                'Celkem' => $blogTotal,
                'Publikováno' => $blogPublished,
                'Čeká na schválení' => $blogPending,
            ],
            'url' => 'blog.php',
        ];
    }
}

if ($canManageNews && isModuleEnabled('news')) {
    $newsTotal = $canManageAllNews
        ? $safeCount($pdo, 'SELECT COUNT(*) FROM cms_news')
        : $safeCount($pdo, 'SELECT COUNT(*) FROM cms_news WHERE author_id = ?', [currentUserId()]);
    $newsPublished = $canManageAllNews
        ? $safeCount($pdo, "SELECT COUNT(*) FROM cms_news WHERE status = 'published'")
        : $safeCount($pdo, "SELECT COUNT(*) FROM cms_news WHERE author_id = ? AND status = 'published'", [currentUserId()]);
    $newsPending = $canManageAllNews
        ? $safeCount($pdo, "SELECT COUNT(*) FROM cms_news WHERE status = 'pending'")
        : $safeCount($pdo, "SELECT COUNT(*) FROM cms_news WHERE author_id = ? AND status = 'pending'", [currentUserId()]);

    if ($newsTotal !== null && $newsPublished !== null && $newsPending !== null) {
        $contentSummaries[] = [
            'heading' => $canManageAllNews ? 'Novinky' : 'Vaše novinky',
            'items' => [
                'Celkem' => $newsTotal,
                'Publikováno' => $newsPublished,
                'Čeká na schválení' => $newsPending,
            ],
            'url' => 'news.php',
        ];
    }
}

$pages = [];
if ($canManageSharedContent) {
    try {
        $pages = $pdo->query(
            "SELECT title, slug, is_published
             FROM cms_pages
             ORDER BY nav_order, title"
        )->fetchAll();
    } catch (\PDOException $e) {
        $pages = [];
    }
}

$upcomingEvents = [];
if ($canManageSharedContent && isModuleEnabled('events')) {
    try {
        $upcomingEvents = $pdo->query(
            "SELECT title, event_date, location
             FROM cms_events
             WHERE is_published = 1 AND event_date >= NOW()
             ORDER BY event_date ASC
             LIMIT 5"
        )->fetchAll();
    } catch (\PDOException $e) {
        $upcomingEvents = [];
    }
}

$reservationSummary = null;
if ($canManageBookings && isModuleEnabled('reservations')) {
    $upcoming = $safeCount(
        $pdo,
        "SELECT COUNT(*) FROM cms_res_bookings
         WHERE status IN ('pending','confirmed')
           AND booking_date >= CURDATE()
           AND booking_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
    );
    $pending = $safeCount(
        $pdo,
        "SELECT COUNT(*) FROM cms_res_bookings WHERE status = 'pending'"
    );
    if ($upcoming !== null && $pending !== null) {
        $reservationSummary = [
            'upcoming' => $upcoming,
            'pending' => $pending,
        ];
    }
}

$enabledModules = [];
if ($canManageSettings) {
    foreach ([
        'blog' => 'Blog',
        'news' => 'Novinky',
        'chat' => 'Chat',
        'contact' => 'Kontakt',
        'events' => 'Události',
        'podcast' => 'Podcast',
        'places' => 'Zajímavá místa',
        'food' => 'Jídelní lístek',
        'gallery' => 'Galerie',
        'newsletter' => 'Newsletter',
        'downloads' => 'Ke stažení',
        'polls' => 'Ankety',
        'faq' => 'FAQ',
        'board' => 'Úřední deska',
        'reservations' => 'Rezervace',
        'statistics' => 'Statistiky',
    ] as $moduleKey => $moduleLabel) {
        if (isModuleEnabled($moduleKey)) {
            $enabledModules[] = $moduleLabel;
        }
    }
}

$quickLinks = [];
$dashboardFocusHeading = 'Na čem chcete pracovat';
$dashboardFocusIntro = 'Vyberte si nejbližší krok, který teď chcete na webu udělat.';

$appendQuickLink = static function (array &$target, string $url, string $label): void {
    foreach ($target as $existing) {
        if ($existing['url'] === $url) {
            return;
        }
    }
    $target[] = ['url' => $url, 'label' => $label];
};

if ($dashboardMode === 'content') {
    $dashboardFocusHeading = 'Na čem chcete pracovat';
    $dashboardFocusIntro = 'Začněte tím, co právě potřebujete napsat, upravit nebo schválit.';
    if (canAccessReviewQueue() && $pendingReviewTotal > 0) {
        $appendQuickLink($quickLinks, 'review_queue.php', 'Co čeká na schválení');
    }
    if ($canManageBlog && isModuleEnabled('blog')) {
        $appendQuickLink($quickLinks, 'blog_form.php', 'Nový článek');
        $appendQuickLink($quickLinks, 'blog.php', 'Správa článků');
    }
    if ($canManageNews && isModuleEnabled('news')) {
        $appendQuickLink($quickLinks, 'news.php', 'Správa novinek');
    }
    if ($canManageSharedContent) {
        $appendQuickLink($quickLinks, 'pages.php', 'Stránky webu');
    }
} elseif ($dashboardMode === 'moderation') {
    $dashboardFocusHeading = 'Co chcete vyřídit';
    $dashboardFocusIntro = 'Začněte zprávami a komentáři, které právě čekají na reakci nebo schválení.';
    if ($canManageComments && isModuleEnabled('blog')) {
        $appendQuickLink($quickLinks, 'comments.php?filter=pending', 'Čekající komentáře');
    }
    if (isModuleEnabled('contact')) {
        $appendQuickLink($quickLinks, $newContactCount > 0 ? 'contact.php?status=new' : 'contact.php', $newContactCount > 0 ? 'Nové kontaktní zprávy' : 'Kontaktní zprávy');
    }
    if (isModuleEnabled('chat')) {
        $appendQuickLink($quickLinks, $newChatCount > 0 ? 'chat.php?status=new' : 'chat.php', $newChatCount > 0 ? 'Nové chat zprávy' : 'Chat zprávy');
    }
    if (canAccessReviewQueue() && $pendingReviewTotal > 0) {
        $appendQuickLink($quickLinks, 'review_queue.php', 'Co čeká na schválení');
    }
    if ($canManageNewsletter && isModuleEnabled('newsletter')) {
        $appendQuickLink($quickLinks, 'newsletter.php', 'Newsletter');
    }
} elseif ($dashboardMode === 'bookings') {
    $dashboardFocusHeading = 'Rezervace';
    $dashboardFocusIntro = 'Začněte rezervacemi, které čekají na vyřízení, nebo správou zdrojů a lokalit.';
    $appendQuickLink($quickLinks, 'res_bookings.php?status=pending', 'Čekající rezervace');
    $appendQuickLink($quickLinks, 'res_bookings.php', 'Všechny rezervace');
    $appendQuickLink($quickLinks, 'res_resources.php', 'Zdroje rezervací');
    $appendQuickLink($quickLinks, 'res_locations.php', 'Lokality rezervací');
} else {
    if (canAccessReviewQueue()) {
        $appendQuickLink($quickLinks, 'review_queue.php', $pendingReviewTotal > 0 ? 'Co čeká na schválení' : 'Schvalovací fronta');
    }
    if ($canManageBlog && isModuleEnabled('blog')) {
        $appendQuickLink($quickLinks, 'blog.php', 'Správa článků');
        $appendQuickLink($quickLinks, 'blog_form.php', 'Nový článek');
    }
    if ($canManageMessages && isModuleEnabled('contact')) {
        $appendQuickLink($quickLinks, $newContactCount > 0 ? 'contact.php?status=new' : 'contact.php', $newContactCount > 0 ? 'Nové kontaktní zprávy' : 'Kontaktní zprávy');
    }
    if ($canManageBookings && isModuleEnabled('reservations')) {
        $appendQuickLink($quickLinks, 'res_bookings.php', 'Správa rezervací');
    }
    if ($canManageSettings) {
        $appendQuickLink($quickLinks, 'settings.php', 'Nastavení webu');
    }
    if ($canManageUsers) {
        $appendQuickLink($quickLinks, 'users.php', 'Uživatelé');
    }
    if (isSuperAdmin()) {
        $appendQuickLink($quickLinks, 'themes.php', 'Vzhled a šablony');
    }
}
if (count($quickLinks) > 5) {
    $quickLinks = array_slice($quickLinks, 0, 5);
}

$showOperationalOverview = $dashboardMode === 'general';
$showContentSecondaryBlocks = in_array($dashboardMode, ['general', 'content'], true);
$showReservationSecondaryBlocks = in_array($dashboardMode, ['general', 'bookings'], true);

$visitorStats = null;
$chartData = [];
$maxViews = 1;
if ($showOperationalOverview && $canViewStatistics && isModuleEnabled('statistics') && getSetting('visitor_tracking_enabled', '0') === '1') {
    statsCleanup();
    $visitorStats = getVisitorStats();

    try {
        $rows = $pdo->query(
            "SELECT DATE(created_at) AS d, COUNT(*) AS views
             FROM cms_page_views
             WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY DATE(created_at)
             ORDER BY d"
        )->fetchAll();
        $viewsByDay = [];
        foreach ($rows as $row) {
            $viewsByDay[$row['d']] = (int)$row['views'];
        }

        for ($offset = 6; $offset >= 0; $offset--) {
            $date = date('Y-m-d', strtotime("-{$offset} days"));
            $views = $viewsByDay[$date] ?? 0;
            $chartData[] = [
                'date' => $date,
                'label' => date('j.n.', strtotime($date)),
                'views' => $views,
            ];
            $maxViews = max($maxViews, $views);
        }
    } catch (\PDOException $e) {
        $chartData = [];
    }
}

$scheduledContent = [];
if ($showContentSecondaryBlocks && $canManageBlog && isModuleEnabled('blog')) {
    try {
        $scopeWhere = $canManageAllBlog ? '' : ' AND author_id = ' . (int)currentUserId();
        $scheduledArticles = $pdo->query(
            "SELECT id, title, slug, publish_at
             FROM cms_articles
             WHERE status = 'published' AND publish_at IS NOT NULL AND publish_at > NOW(){$scopeWhere}
             ORDER BY publish_at ASC
             LIMIT 10"
        )->fetchAll();
        foreach ($scheduledArticles as $row) {
            $scheduledContent[] = $row;
        }
    } catch (\PDOException $e) {
        // publish_at může chybět ve starší instalaci
    }
}

adminHeader('Přehled');
?>

<p>
  Jste přihlášen jako <strong><?= h($accountName) ?></strong>.
  Role vašeho účtu: <strong><?= h($accountLabel) ?></strong>.
</p>

<?php if ($pendingReviewItems !== []): ?>
<section style="background:#fffbe6;border:1px solid #d7b600;padding:1rem;margin:1rem 0" aria-labelledby="pending-attention-heading">
  <h2 id="pending-attention-heading" style="margin-top:0">Co potřebuje pozornost</h2>
  <p>Máte <strong><?= (int)$pendingReviewTotal ?></strong> položek, které čekají na schválení nebo vyřízení.</p>
  <ul>
    <?php foreach ($pendingReviewItems as $item): ?>
      <li>
        <?= h($item['label']) ?>: <strong><?= (int)$item['count'] ?></strong>
        <a href="<?= h($item['url']) ?>">zobrazit <span aria-hidden="true">→</span></a>
      </li>
    <?php endforeach; ?>
  </ul>
  <p><a href="review_queue.php">Otevřít frontu <span aria-hidden="true">→</span></a></p>
</section>
<?php endif; ?>

<?php if (!empty($quickLinks)): ?>
<section aria-labelledby="task-links-heading" style="margin-bottom:1.5rem">
  <h2 id="task-links-heading"><?= h($dashboardFocusHeading) ?></h2>
  <p style="margin-top:0;color:#555"><?= h($dashboardFocusIntro) ?></p>
  <nav aria-label="Na čem chcete pracovat" class="button-row">
    <?php foreach ($quickLinks as $link): ?>
      <a href="<?= h($link['url']) ?>" class="btn"><?= h($link['label']) ?></a>
    <?php endforeach; ?>
  </nav>
</section>
<?php endif; ?>

<?php if ($contentSummaries !== []): ?>
<section aria-labelledby="content-summary-heading-new" style="margin:1.5rem 0">
  <h2 id="content-summary-heading-new">Práce s obsahem</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
    <?php foreach ($contentSummaries as $summary): ?>
      <section style="border:1px solid #d6d6d6;border-radius:10px;padding:1rem;background:#fff">
        <h3 style="margin-top:0"><?= h($summary['heading']) ?></h3>
        <ul style="margin:0;padding-left:1.1rem">
          <?php foreach ($summary['items'] as $label => $value): ?>
            <li style="padding:.15rem 0">
              <strong><?= h($label) ?>:</strong> <?= (int)$value ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <p style="margin-bottom:0"><a href="<?= h($summary['url']) ?>">Otevřít sekci <span aria-hidden="true">→</span></a></p>
      </section>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($scheduledContent !== []): ?>
<section aria-labelledby="scheduled-heading" style="margin:1.5rem 0">
  <h2 id="scheduled-heading">Naplánovaný obsah</h2>
  <p style="margin-top:0;color:#555">Články, které se automaticky zveřejní v budoucnu.</p>
  <table>
    <caption class="sr-only">Naplánované publikace</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Publikace</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($scheduledContent as $scheduled): ?>
        <tr>
          <td><?= h((string)$scheduled['title']) ?></td>
          <td>
            <time datetime="<?= h(str_replace(' ', 'T', (string)$scheduled['publish_at'])) ?>">
              <?= formatCzechDate((string)$scheduled['publish_at']) ?>
            </time>
          </td>
          <td>
            <a href="blog_form.php?id=<?= (int)$scheduled['id'] ?>">Upravit <span aria-hidden="true">→</span></a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<?php if ($overviewRows !== []): ?>
<section aria-labelledby="overview-heading-new" style="margin-top:1.5rem">
  <h2 id="overview-heading-new"><?= $showOperationalOverview ? 'Přehled administrace' : 'Další přehledy' ?></h2>
  <?php if ($showOperationalOverview): ?>
    <table>
      <caption>Přehled dostupných sekcí</caption>
      <thead>
        <tr>
          <th scope="col">Sekce</th>
          <th scope="col">Počet</th>
          <th scope="col">Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($overviewRows as $row): ?>
          <tr>
            <td><?= h($row['label']) ?></td>
            <td><?= (int)$row['count'] ?></td>
            <td><a href="<?= h($row['url']) ?>">Otevřít sekci <span aria-hidden="true">→</span></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <ul>
      <?php foreach ($overviewRows as $row): ?>
        <li>
          <a href="<?= h($row['url']) ?>"><?= h($row['label']) ?></a>
          <span style="color:#555"> · <?= (int)$row['count'] ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php else: ?>
  <p>Pro tento účet teď není dostupná žádná část administrace.</p>
<?php endif; ?>

<?php if ($showContentSecondaryBlocks && $pages !== []): ?>
<section aria-labelledby="pages-heading-new" style="margin-top:1.5rem">
  <h2 id="pages-heading-new">Důležité stránky webu</h2>
  <ul>
    <?php foreach ($pages as $page): ?>
      <li>
        <a href="<?= h(pagePublicPath($page)) ?>" target="_blank" rel="noopener">
          <?= h((string)$page['title']) ?>
        </a>
        <?= (int)$page['is_published'] === 1 ? '' : ' <em>(koncept)</em>' ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <p><a href="pages.php">Spravovat stránky <span aria-hidden="true">→</span></a></p>
</section>
<?php endif; ?>

<?php if ($showContentSecondaryBlocks && $upcomingEvents !== []): ?>
<section aria-labelledby="events-heading-new" style="margin-top:1.5rem">
  <h2 id="events-heading-new">Nejbližší události</h2>
  <ul>
    <?php foreach ($upcomingEvents as $eventItem): ?>
      <li>
        <strong><?= h((string)$eventItem['title']) ?></strong>
        –
        <time datetime="<?= h(str_replace(' ', 'T', (string)$eventItem['event_date'])) ?>">
          <?= formatCzechDate((string)$eventItem['event_date']) ?>
        </time>
        <?= trim((string)$eventItem['location']) !== '' ? ' · ' . h((string)$eventItem['location']) : '' ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <p><a href="events.php">Všechny události <span aria-hidden="true">→</span></a></p>
</section>
<?php endif; ?>

<?php if ($showReservationSecondaryBlocks && $reservationSummary !== null): ?>
<section aria-labelledby="reservations-heading-new" style="margin-top:1.5rem">
  <h2 id="reservations-heading-new">Přehled rezervací</h2>
  <ul>
    <li>Nadcházejících rezervací za 7 dnů: <strong><?= (int)$reservationSummary['upcoming'] ?></strong></li>
    <li>Čekajících na schválení: <strong><?= (int)$reservationSummary['pending'] ?></strong></li>
  </ul>
  <p><a href="res_bookings.php">Správa rezervací <span aria-hidden="true">→</span></a></p>
</section>
<?php endif; ?>

<?php if ($visitorStats !== null): ?>
<section aria-labelledby="stats-heading-new" style="margin-top:1.5rem">
  <h2 id="stats-heading-new">Návštěvnost webu</h2>
  <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem" role="list" aria-label="Souhrn návštěvnosti">
    <?php foreach ([
        ['label' => 'Online', 'value' => (int)$visitorStats['online'], 'background' => '#f0f7ff'],
        ['label' => 'Dnes', 'value' => (int)$visitorStats['today'], 'background' => '#f0fff0'],
        ['label' => 'Tento měsíc', 'value' => (int)$visitorStats['month'], 'background' => '#fffbea'],
        ['label' => 'Celkem', 'value' => (int)$visitorStats['total'], 'background' => '#fff2f2'],
    ] as $statItem): ?>
      <div role="listitem" style="background:<?= h($statItem['background']) ?>;padding:.75rem 1rem;border-radius:8px;min-width:120px;text-align:center">
        <div style="font-size:1.5rem;font-weight:700"><?= number_format($statItem['value'], 0, ',', ' ') ?></div>
        <div style="font-size:.9rem;color:#444"><?= h($statItem['label']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($chartData !== []): ?>
  <figure style="margin:0">
    <figcaption class="sr-only">Návštěvnost za posledních 7 dnů</figcaption>
    <div style="display:flex;align-items:flex-end;gap:4px;height:100px" aria-hidden="true">
      <?php foreach ($chartData as $chartItem): ?>
        <div
          style="flex:1;background:#005fcc;min-height:2px;height:<?= (int)round(($chartItem['views'] / $maxViews) * 100) ?>%"
          title="<?= h($chartItem['label']) ?>: <?= (int)$chartItem['views'] ?> zobrazení"></div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:4px" aria-hidden="true">
      <?php foreach ($chartData as $chartItem): ?>
        <span style="flex:1;text-align:center;font-size:.75rem;color:#666"><?= h($chartItem['label']) ?></span>
      <?php endforeach; ?>
    </div>
  </figure>
  <p><a href="statistics.php">Podrobné statistiky <span aria-hidden="true">→</span></a></p>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($showOperationalOverview && $enabledModules !== []): ?>
<section aria-labelledby="modules-heading-new" style="margin-top:1.5rem">
  <h2 id="modules-heading-new">Zapnuté moduly na webu</h2>
  <p><?= h(implode(', ', $enabledModules)) ?></p>
</section>
<?php endif; ?>

<?php adminFooter(); ?>
