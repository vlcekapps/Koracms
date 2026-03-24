<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

if (!canAccessReviewQueue()) {
    adminForbidden('Přístup odepřen. Fronta ke schválení je dostupná jen rolím se schvalovacím oprávněním.');
}

$pdo = db_connect();
autoCompleteBookings();

$scope = trim($_GET['scope'] ?? 'all');
if (!in_array($scope, ['all', 'content', 'comments', 'reservations'], true)) {
    $scope = 'all';
}

$summaryItems = pendingReviewSummary($pdo);
$summaryByCategory = [
    'content' => 0,
    'comments' => 0,
    'reservations' => 0,
];
foreach ($summaryItems as $summaryItem) {
    $summaryByCategory[$summaryItem['category']] += (int)$summaryItem['count'];
}
$summaryByCategory['all'] = array_sum(array_column($summaryItems, 'count'));

$scopeTabs = [
    ['key' => 'all', 'label' => 'Vše', 'count' => $summaryByCategory['all'], 'visible' => true],
    ['key' => 'content', 'label' => 'Obsah', 'count' => $summaryByCategory['content'], 'visible' => $summaryByCategory['content'] > 0 || currentUserHasCapability('blog_approve') || currentUserHasCapability('news_approve') || currentUserHasCapability('content_approve_shared')],
    ['key' => 'comments', 'label' => 'Komentáře', 'count' => $summaryByCategory['comments'], 'visible' => isModuleEnabled('blog') && currentUserHasCapability('comments_manage')],
    ['key' => 'reservations', 'label' => 'Rezervace', 'count' => $summaryByCategory['reservations'], 'visible' => isModuleEnabled('reservations') && currentUserHasCapability('bookings_manage')],
];

$redirectPath = BASE_URL . '/admin/review_queue.php' . ($scope !== 'all' ? '?scope=' . rawurlencode($scope) : '');

$contentRows = [];
$commentRows = [];
$reservationRows = [];

if (in_array($scope, ['all', 'content'], true)) {
    if (isModuleEnabled('blog') && currentUserHasCapability('blog_approve')) {
        $rows = $pdo->query(
            "SELECT a.id, a.title, a.created_at,
                    COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
             FROM cms_articles a
             LEFT JOIN cms_users u ON u.id = a.author_id
             WHERE a.status = 'pending'
             ORDER BY a.created_at DESC
             LIMIT 20"
        )->fetchAll();
        foreach ($rows as $row) {
            $contentRows[] = [
                'sort_at' => (string)$row['created_at'],
                'module_label' => 'Blog',
                'title' => (string)$row['title'],
                'meta' => trim((string)$row['author_name']) !== '' ? 'Autor: ' . (string)$row['author_name'] : '',
                'date' => (string)$row['created_at'],
                'edit_url' => 'blog_form.php?id=' . (int)$row['id'],
                'manage_url' => 'blog.php',
                'approval_module' => 'articles',
                'id' => (int)$row['id'],
            ];
        }
    }

    if (isModuleEnabled('news') && currentUserHasCapability('news_approve')) {
        $rows = $pdo->query(
            "SELECT n.id, n.title, n.slug, n.content, n.created_at,
                    COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
             FROM cms_news n
             LEFT JOIN cms_users u ON u.id = n.author_id
             WHERE n.status = 'pending'
             ORDER BY n.created_at DESC
             LIMIT 20"
        )->fetchAll();
        foreach ($rows as $row) {
            $contentRows[] = [
                'sort_at' => (string)$row['created_at'],
                'module_label' => 'Novinky',
                'title' => newsTitleCandidate((string)($row['title'] ?? ''), (string)($row['content'] ?? '')),
                'meta' => trim((string)$row['author_name']) !== ''
                    ? 'Autor: ' . (string)$row['author_name']
                    : (newsSlug((string)($row['slug'] ?? '')) !== ''
                        ? 'Slug: ' . newsSlug((string)($row['slug'] ?? ''))
                        : newsExcerpt((string)($row['content'] ?? ''), 80)),
                'date' => (string)$row['created_at'],
                'edit_url' => 'news_form.php?id=' . (int)$row['id'],
                'manage_url' => 'news.php',
                'approval_module' => 'news',
                'id' => (int)$row['id'],
            ];
        }
    }

    if (currentUserHasCapability('content_approve_shared')) {
        $sharedSources = [
            [
                'enabled' => true,
                'module_label' => 'Stránky',
                'approval_module' => 'pages',
                'manage_url' => 'pages.php',
                'edit_builder' => static fn(array $row): string => 'page_form.php?id=' . (int)$row['id'],
                'sql' => "SELECT id, title, slug, created_at
                          FROM cms_pages
                          WHERE status = 'pending'
                          ORDER BY created_at DESC
                          LIMIT 10",
                'row_builder' => static fn(array $row): array => [
                    'sort_at' => (string)$row['created_at'],
                    'title' => (string)$row['title'],
                    'meta' => 'Slug: ' . (string)$row['slug'],
                    'date' => (string)$row['created_at'],
                ],
            ],
            [
                'enabled' => isModuleEnabled('faq'),
                'module_label' => 'FAQ',
                'approval_module' => 'faq',
                'manage_url' => 'faq.php',
                'edit_builder' => static fn(array $row): string => 'faq_form.php?id=' . (int)$row['id'],
                'sql' => "SELECT f.id, f.question, f.slug, f.excerpt, f.answer, f.created_at,
                                 c.name AS category_name
                          FROM cms_faqs f
                          LEFT JOIN cms_faq_categories c ON c.id = f.category_id
                          WHERE f.status = 'pending'
                          ORDER BY f.sort_order, f.id
                          LIMIT 10",
                'row_builder' => static fn(array $row): array => [
                    'sort_at' => (string)$row['created_at'],
                    'title' => (string)$row['question'],
                    'meta' => trim((string)$row['category_name']) !== ''
                        ? 'Kategorie: ' . (string)$row['category_name']
                        : faqExcerpt($row, 80),
                    'date' => (string)$row['created_at'],
                ],
            ],
            [
                'enabled' => isModuleEnabled('board'),
                'module_label' => 'Úřední deska',
                'approval_module' => 'board',
                'manage_url' => 'board.php',
                'edit_builder' => static fn(array $row): string => 'board_form.php?id=' . (int)$row['id'],
                'sql' => "SELECT b.id, b.title, b.posted_date, b.created_at,
                                 COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
                          FROM cms_board b
                          LEFT JOIN cms_users u ON u.id = b.author_id
                          WHERE b.status = 'pending'
                          ORDER BY b.posted_date DESC, b.created_at DESC
                          LIMIT 10",
                'row_builder' => static fn(array $row): array => [
                    'sort_at' => (string)($row['posted_date'] ?: $row['created_at']),
                    'title' => (string)$row['title'],
                    'meta' => trim((string)$row['author_name']) !== '' ? 'Autor: ' . (string)$row['author_name'] : '',
                    'date' => (string)($row['posted_date'] ?: $row['created_at']),
                ],
            ],
            [
                'enabled' => isModuleEnabled('downloads'),
                'module_label' => 'Ke stažení',
                'approval_module' => 'downloads',
                'manage_url' => 'downloads.php',
                'edit_builder' => static fn(array $row): string => 'download_form.php?id=' . (int)$row['id'],
                'sql' => "SELECT d.id, d.title, d.original_name, d.created_at,
                                 COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
                          FROM cms_downloads d
                          LEFT JOIN cms_users u ON u.id = d.author_id
                          WHERE d.status = 'pending'
                          ORDER BY d.created_at DESC
                          LIMIT 10",
                'row_builder' => static fn(array $row): array => [
                    'sort_at' => (string)$row['created_at'],
                    'title' => (string)$row['title'],
                    'meta' => trim((string)$row['original_name']) !== '' ? 'Soubor: ' . (string)$row['original_name'] : ((trim((string)$row['author_name']) !== '') ? 'Autor: ' . (string)$row['author_name'] : ''),
                    'date' => (string)$row['created_at'],
                ],
            ],
            [
                'enabled' => isModuleEnabled('events'),
                'module_label' => 'Události',
                'approval_module' => 'events',
                'manage_url' => 'events.php',
                'edit_builder' => static fn(array $row): string => 'event_form.php?id=' . (int)$row['id'],
                'sql' => "SELECT id, title, location, event_date, created_at
                          FROM cms_events
                          WHERE status = 'pending'
                          ORDER BY event_date DESC, created_at DESC
                          LIMIT 10",
                'row_builder' => static fn(array $row): array => [
                    'sort_at' => (string)($row['event_date'] ?: $row['created_at']),
                    'title' => (string)$row['title'],
                    'meta' => trim((string)$row['location']) !== '' ? 'Místo: ' . (string)$row['location'] : '',
                    'date' => (string)($row['event_date'] ?: $row['created_at']),
                ],
            ],
            [
                'enabled' => isModuleEnabled('places'),
                'module_label' => 'Zajímavá místa',
                'approval_module' => 'places',
                'manage_url' => 'places.php',
                'edit_builder' => static fn(array $row): string => 'place_form.php?id=' . (int)$row['id'],
                'sql' => "SELECT id, name, category, created_at
                          FROM cms_places
                          WHERE status = 'pending'
                          ORDER BY sort_order, name
                          LIMIT 10",
                'row_builder' => static fn(array $row): array => [
                    'sort_at' => (string)$row['created_at'],
                    'title' => (string)$row['name'],
                    'meta' => trim((string)$row['category']) !== '' ? 'Kategorie: ' . (string)$row['category'] : '',
                    'date' => (string)$row['created_at'],
                ],
            ],
            [
                'enabled' => isModuleEnabled('podcast'),
                'module_label' => 'Podcasty',
                'approval_module' => 'podcasts',
                'manage_url' => 'podcast_shows.php',
                'edit_builder' => static fn(array $row): string => 'podcast_form.php?id=' . (int)$row['id'] . '&show_id=' . (int)$row['show_id'],
                'sql' => "SELECT p.id, p.title, p.show_id, p.created_at, s.title AS show_title
                          FROM cms_podcasts p
                          LEFT JOIN cms_podcast_shows s ON s.id = p.show_id
                          WHERE p.status = 'pending'
                          ORDER BY p.created_at DESC
                          LIMIT 10",
                'row_builder' => static fn(array $row): array => [
                    'sort_at' => (string)$row['created_at'],
                    'title' => (string)$row['title'],
                    'meta' => trim((string)$row['show_title']) !== '' ? 'Podcast: ' . (string)$row['show_title'] : '',
                    'date' => (string)$row['created_at'],
                ],
            ],
            [
                'enabled' => isModuleEnabled('food'),
                'module_label' => 'Jídelní lístky',
                'approval_module' => 'food',
                'manage_url' => 'food.php',
                'edit_builder' => static fn(array $row): string => 'food_form.php?id=' . (int)$row['id'],
                'sql' => "SELECT c.id, c.title, c.type, c.valid_from, c.created_at,
                                 COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
                          FROM cms_food_cards c
                          LEFT JOIN cms_users u ON u.id = c.author_id
                          WHERE c.status = 'pending'
                          ORDER BY c.created_at DESC
                          LIMIT 10",
                'row_builder' => static fn(array $row): array => [
                    'sort_at' => (string)($row['valid_from'] ?: $row['created_at']),
                    'title' => (string)$row['title'],
                    'meta' => 'Typ: ' . ((string)$row['type'] === 'beverage' ? 'Nápojový lístek' : 'Jídelní lístek')
                        . (trim((string)$row['author_name']) !== '' ? ' · Autor: ' . (string)$row['author_name'] : ''),
                    'date' => (string)($row['valid_from'] ?: $row['created_at']),
                ],
            ],
        ];

        foreach ($sharedSources as $sharedSource) {
            if (!$sharedSource['enabled']) {
                continue;
            }

            try {
                $rows = $pdo->query($sharedSource['sql'])->fetchAll();
            } catch (\PDOException $e) {
                $rows = [];
            }
            foreach ($rows as $row) {
                $baseRow = $sharedSource['row_builder']($row);
                $contentRows[] = [
                    'sort_at' => $baseRow['sort_at'],
                    'module_label' => $sharedSource['module_label'],
                    'title' => $baseRow['title'],
                    'meta' => $baseRow['meta'],
                    'date' => $baseRow['date'],
                    'edit_url' => $sharedSource['edit_builder']($row),
                    'manage_url' => $sharedSource['manage_url'],
                    'approval_module' => $sharedSource['approval_module'],
                    'id' => (int)$row['id'],
                ];
            }
        }
    }

    usort(
        $contentRows,
        static fn(array $left, array $right): int => strcmp((string)$right['sort_at'], (string)$left['sort_at'])
    );
}

if (in_array($scope, ['all', 'comments'], true) && isModuleEnabled('blog') && currentUserHasCapability('comments_manage')) {
    try {
        $commentRows = $pdo->query(
            "SELECT c.id, c.author_name, c.author_email, c.content, c.created_at,
                    a.title AS article_title, a.id AS article_id, a.slug AS article_slug
             FROM cms_comments c
             LEFT JOIN cms_articles a ON a.id = c.article_id
             WHERE c.status = 'pending'
             ORDER BY c.created_at DESC
             LIMIT 25"
        )->fetchAll();
    } catch (\PDOException $e) {
        $commentRows = $pdo->query(
            "SELECT c.id, c.author_name, c.author_email, c.content, c.created_at,
                    a.title AS article_title, a.id AS article_id, a.slug AS article_slug
             FROM cms_comments c
             LEFT JOIN cms_articles a ON a.id = c.article_id
             WHERE c.is_approved = 0
             ORDER BY c.created_at DESC
             LIMIT 25"
        )->fetchAll();
    }
}

if (in_array($scope, ['all', 'reservations'], true) && isModuleEnabled('reservations') && currentUserHasCapability('bookings_manage')) {
    $reservationRows = $pdo->query(
        "SELECT b.id, b.booking_date, b.start_time, b.end_time, b.party_size, b.status,
                r.name AS resource_name,
                COALESCE(NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email, b.guest_name, b.guest_email) AS customer_name
         FROM cms_res_bookings b
         LEFT JOIN cms_res_resources r ON r.id = b.resource_id
         LEFT JOIN cms_users u ON u.id = b.user_id
         WHERE b.status = 'pending'
         ORDER BY b.booking_date ASC, b.start_time ASC
         LIMIT 25"
    )->fetchAll();
}

adminHeader('Ke schválení');
?>

<p>Jednotné místo pro schvalování obsahu, moderaci komentářů a správu čekajících rezervací.</p>

<nav aria-label="Filtr fronty ke schválení" class="button-row" style="margin-bottom:1rem">
  <?php foreach ($scopeTabs as $scopeTab): ?>
    <?php if (!$scopeTab['visible']): ?>
      <?php continue; ?>
    <?php endif; ?>
    <?php $scopeHref = $scopeTab['key'] === 'all' ? 'review_queue.php' : 'review_queue.php?scope=' . rawurlencode($scopeTab['key']); ?>
    <a href="<?= h($scopeHref) ?>" <?= $scope === $scopeTab['key'] ? 'aria-current="page"' : '' ?>>
      <?= h($scopeTab['label']) ?> (<?= (int)$scopeTab['count'] ?>)
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($summaryItems !== []): ?>
<section aria-labelledby="queue-summary-heading" style="margin-bottom:1.5rem">
  <h2 id="queue-summary-heading">Souhrn čekajících položek</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem">
    <?php foreach ($summaryItems as $summaryItem): ?>
      <section style="border:1px solid #d6d6d6;border-radius:10px;padding:1rem;background:#fff">
        <h3 style="margin:.1rem 0 .35rem;font-size:1rem"><?= h($summaryItem['label']) ?></h3>
        <p style="margin:.2rem 0 .75rem;font-size:1.8rem;font-weight:700"><?= (int)$summaryItem['count'] ?></p>
        <p style="margin:0"><a href="<?= h($summaryItem['url']) ?>">Otevřít <span aria-hidden="true">→</span></a></p>
      </section>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php $hasVisibleRows = false; ?>

<?php if (in_array($scope, ['all', 'content'], true)): ?>
  <?php $hasVisibleRows = $hasVisibleRows || $contentRows !== []; ?>
  <section aria-labelledby="queue-content-heading" style="margin-top:1.5rem">
    <h2 id="queue-content-heading">Obsah čekající na schválení</h2>
    <?php if ($contentRows === []): ?>
      <p>Zatím nic ke schválení.</p>
    <?php else: ?>
      <table>
        <caption>Čekající obsah</caption>
        <thead>
          <tr>
            <th scope="col">Modul</th>
            <th scope="col">Položka</th>
            <th scope="col">Detail</th>
            <th scope="col">Datum</th>
            <th scope="col">Akce</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contentRows as $row): ?>
            <tr>
              <td><?= h($row['module_label']) ?></td>
              <td><?= h($row['title']) ?></td>
              <td><?= $row['meta'] !== '' ? h($row['meta']) : '<em>Bez doplňujících informací</em>' ?></td>
              <td>
                <?php if ($row['date'] !== ''): ?>
                  <time datetime="<?= h(str_replace(' ', 'T', (string)$row['date'])) ?>"><?= formatCzechDate((string)$row['date']) ?></time>
                <?php else: ?>
                  –
                <?php endif; ?>
              </td>
              <td class="actions">
                <a href="<?= h($row['edit_url']) ?>" class="btn">Otevřít</a>
                <form action="approve.php" method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                  <input type="hidden" name="module" value="<?= h($row['approval_module']) ?>">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <input type="hidden" name="redirect" value="<?= h($redirectPath) ?>">
                  <button type="submit" class="btn" style="background:#060;color:#fff">Schválit</button>
                </form>
                <a href="<?= h($row['manage_url']) ?>">Modul</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if (in_array($scope, ['all', 'comments'], true) && isModuleEnabled('blog') && currentUserHasCapability('comments_manage')): ?>
  <?php $hasVisibleRows = $hasVisibleRows || $commentRows !== []; ?>
  <section aria-labelledby="queue-comments-heading" style="margin-top:1.5rem">
    <h2 id="queue-comments-heading">Komentáře čekající na moderaci</h2>
    <?php if ($commentRows === []): ?>
      <p>Zatím nic ke schválení.</p>
    <?php else: ?>
      <table>
        <caption>Čekající komentáře</caption>
        <thead>
          <tr>
            <th scope="col">Autor</th>
            <th scope="col">Článek</th>
            <th scope="col">Komentář</th>
            <th scope="col">Datum</th>
            <th scope="col">Akce</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($commentRows as $commentRow): ?>
            <tr>
              <td>
                <strong><?= h((string)$commentRow['author_name']) ?></strong>
                <?php if (trim((string)$commentRow['author_email']) !== ''): ?>
                  <br><a href="mailto:<?= h((string)$commentRow['author_email']) ?>"><?= h((string)$commentRow['author_email']) ?></a>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($commentRow['article_id'])): ?>
                  <a href="<?= h(articlePublicPath(['id' => (int)$commentRow['article_id'], 'slug' => (string)($commentRow['article_slug'] ?? '')])) ?>" target="_blank" rel="noopener">
                    <?= h((string)($commentRow['article_title'] ?: 'Bez článku')) ?>
                  </a>
                <?php else: ?>
                  <?= h((string)($commentRow['article_title'] ?: 'Bez článku')) ?>
                <?php endif; ?>
              </td>
              <td><div style="max-width:30rem;white-space:pre-wrap"><?= h(mb_strimwidth((string)$commentRow['content'], 0, 180, '…')) ?></div></td>
              <td><time datetime="<?= h(str_replace(' ', 'T', (string)$commentRow['created_at'])) ?>"><?= formatCzechDate((string)$commentRow['created_at']) ?></time></td>
              <td class="actions">
                <?php foreach (['approve' => 'Schválit', 'spam' => 'Spam', 'trash' => 'Koš'] as $actionKey => $actionLabel): ?>
                  <form action="comment_action.php" method="post" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$commentRow['id'] ?>">
                    <input type="hidden" name="filter" value="pending">
                    <input type="hidden" name="action" value="<?= h($actionKey) ?>">
                    <input type="hidden" name="redirect" value="<?= h($redirectPath) ?>">
                    <button type="submit" class="btn<?= $actionKey === 'trash' ? ' btn-danger' : '' ?>"><?= h($actionLabel) ?></button>
                  </form>
                <?php endforeach; ?>
                <a href="comments.php?filter=pending">Moderace</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if (in_array($scope, ['all', 'reservations'], true) && isModuleEnabled('reservations') && currentUserHasCapability('bookings_manage')): ?>
  <?php $hasVisibleRows = $hasVisibleRows || $reservationRows !== []; ?>
  <section aria-labelledby="queue-reservations-heading" style="margin-top:1.5rem">
    <h2 id="queue-reservations-heading">Rezervace čekající na schválení</h2>
    <?php if ($reservationRows === []): ?>
      <p>Zatím nic ke schválení.</p>
    <?php else: ?>
      <table>
        <caption>Čekající rezervace</caption>
        <thead>
          <tr>
            <th scope="col">Zdroj</th>
            <th scope="col">Uživatel</th>
            <th scope="col">Termín</th>
            <th scope="col">Počet osob</th>
            <th scope="col">Akce</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reservationRows as $reservationRow): ?>
            <tr>
              <td><?= h((string)($reservationRow['resource_name'] ?? '–')) ?></td>
              <td><?= h((string)($reservationRow['customer_name'] ?? '–')) ?></td>
              <td>
                <time datetime="<?= h((string)$reservationRow['booking_date']) ?>"><?= h((string)$reservationRow['booking_date']) ?></time>
                <br><small><?= h((string)$reservationRow['start_time']) ?> – <?= h((string)$reservationRow['end_time']) ?></small>
              </td>
              <td><?= (int)$reservationRow['party_size'] ?></td>
              <td class="actions">
                <a href="res_booking_detail.php?id=<?= (int)$reservationRow['id'] ?>" class="btn">Detail</a>
                <form action="res_booking_save.php" method="post" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                  <input type="hidden" name="booking_id" value="<?= (int)$reservationRow['id'] ?>">
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="redirect" value="<?= h($redirectPath) ?>">
                  <button type="submit" class="btn" style="background:#060;color:#fff">Schválit</button>
                </form>
                <form action="res_booking_save.php" method="post" style="display:inline" onsubmit="return confirm('Zamítnout rezervaci?')">
                  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                  <input type="hidden" name="booking_id" value="<?= (int)$reservationRow['id'] ?>">
                  <input type="hidden" name="action" value="reject">
                  <input type="hidden" name="redirect" value="<?= h($redirectPath) ?>">
                  <button type="submit" class="btn btn-danger">Zamítnout</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if (!$hasVisibleRows): ?>
  <p>Aktuálně tu není nic, co by čekalo na vaše schválení.</p>
<?php endif; ?>

<?php adminFooter(); ?>
