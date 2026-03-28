<?php
require_once __DIR__ . '/../db.php';

function adminHeader(string $pageTitle): void
{
    $siteName = h(getSetting('site_name', 'Kora CMS'));
    $baseUrl = BASE_URL;
    $pdo = db_connect();

    $canManageComments = currentUserHasCapability('comments_manage');
    $canManageMessages = currentUserHasCapability('messages_manage');
    $canManageNewsletter = currentUserHasCapability('newsletter_manage');

    $pendingComments = $canManageComments ? pendingCommentCount() : 0;
    $unreadContactMessages = ($canManageMessages && isModuleEnabled('contact')) ? unreadContactCount() : 0;
    $unreadChatMessages = ($canManageMessages && isModuleEnabled('chat')) ? unreadChatCount() : 0;
    $newsletterCounts = ($canManageNewsletter && isModuleEnabled('newsletter'))
        ? newsletterSubscriberCounts($pdo)
        : ['confirmed' => 0, 'pending' => 0];
    $pendingNewsletterSubscribers = $newsletterCounts['pending'];
    $pendingReviewItems = canAccessReviewQueue() ? pendingReviewSummary($pdo) : [];
    $pendingReviewTotal = array_sum(array_column($pendingReviewItems, 'count'));
    $pendingCommentsLabel = $pendingComments === 1
        ? 'čekající komentář'
        : ($pendingComments < 5 ? 'čekající komentáře' : 'čekajících komentářů');

    $renderItem = static function (array $item): string {
        $style = isset($item['style']) ? ' style="' . $item['style'] . '"' : '';
        return '    <li><a href="' . $item['url'] . '"' . $style . '>' . $item['label'] . '</a></li>' . "\n";
    };

    $renderNestedItem = static function (array $item): string {
        $style = $item['style'] ?? 'padding-left:.95rem;font-size:.85rem;color:#ddd';
        return '          <li><a href="' . $item['url'] . '" style="' . $style . '">' . $item['label'] . '</a></li>' . "\n";
    };

    $renderNavSection = static function (string $id, string $heading, array $items) use ($renderItem, $renderNestedItem): string {
        if ($items === []) {
            return '';
        }

        $html = '  <section class="nav-section" aria-labelledby="' . $id . '">' . "\n"
            . '    <h3 id="' . $id . '">' . $heading . '</h3>' . "\n"
            . '    <ul>' . "\n";

        foreach ($items as $item) {
            if (($item['type'] ?? 'link') !== 'details') {
                $html .= $renderItem($item);
                continue;
            }

            $summaryStyle = $item['summary_style'] ?? 'cursor:pointer;color:#ddd;font-size:.9rem;padding:.45rem .35rem;border-radius:4px;list-style:none;user-select:none';
            $html .= '      <li>' . "\n"
                . '        <details>' . "\n"
                . '          <summary style="' . $summaryStyle . '">' . $item['label'] . '</summary>' . "\n"
                . '          <ul class="nav-list--nested">' . "\n";

            foreach ($item['items'] as $childItem) {
                $html .= $renderNestedItem($childItem);
            }

            $html .= '          </ul>' . "\n"
                . '        </details>' . "\n"
                . '      </li>' . "\n";
        }

        $html .= '    </ul>' . "\n"
            . '  </section>' . "\n";

        return $html;
    };

    $topItems = [
        ['url' => $baseUrl . '/admin/index.php', 'label' => 'Přehled'],
        ['url' => $baseUrl . '/admin/profile.php', 'label' => 'Můj profil'],
    ];
    if (canAccessReviewQueue()) {
        $topItems[] = [
            'url' => $baseUrl . '/admin/review_queue.php',
            'label' => 'Ke schválení'
                . ($pendingReviewTotal > 0
                    ? ' <span class="badge" aria-label="' . $pendingReviewTotal . ' čekajících položek">' . $pendingReviewTotal . '</span>'
                    : ''),
        ];
    }

    $contentItems = [];
    if (isModuleEnabled('blog') && (currentUserHasCapability('blog_manage_own') || currentUserHasCapability('blog_taxonomies_manage'))) {
        $blogItems = [];
        if (currentUserHasCapability('blog_taxonomies_manage')) {
            $blogItems[] = ['url' => $baseUrl . '/admin/blogs.php', 'label' => 'Správa blogů'];
        }
        if (currentUserHasCapability('blog_manage_own')) {
            $blogItems[] = ['url' => $baseUrl . '/admin/blog.php', 'label' => 'Články'];
        }
        if (currentUserHasCapability('blog_taxonomies_manage')) {
            $blogItems[] = ['url' => $baseUrl . '/admin/blog_cats.php', 'label' => 'Kategorie'];
            $blogItems[] = ['url' => $baseUrl . '/admin/blog_tags.php', 'label' => 'Štítky'];
        }
        if ($blogItems !== []) {
            $contentItems[] = [
                'type' => 'details',
                'label' => 'Blog',
                'label_plain' => 'Blog',
                'items' => $blogItems,
            ];
        }
    }
    if (isModuleEnabled('news') && currentUserHasCapability('news_manage_own')) {
        $contentItems[] = ['url' => $baseUrl . '/admin/news.php', 'label' => 'Novinky'];
    }
    if (currentUserHasCapability('content_manage_shared')) {
        $contentItems[] = ['url' => $baseUrl . '/admin/pages.php', 'label' => 'Stránky'];
        $contentItems[] = ['url' => $baseUrl . '/admin/page_positions.php', 'label' => 'Pozice stránek'];
        if (isModuleEnabled('events')) {
            $contentItems[] = ['url' => $baseUrl . '/admin/events.php', 'label' => 'Události'];
        }
        if (isModuleEnabled('gallery')) {
            $contentItems[] = ['url' => $baseUrl . '/admin/gallery_albums.php', 'label' => 'Galerie'];
        }
        if (isModuleEnabled('podcast')) {
            $contentItems[] = ['url' => $baseUrl . '/admin/podcast_shows.php', 'label' => 'Podcasty'];
        }
        if (isModuleEnabled('places')) {
            $contentItems[] = ['url' => $baseUrl . '/admin/places.php', 'label' => 'Zajímavá místa'];
        }
        if (isModuleEnabled('downloads')) {
            $contentItems[] = [
                'type' => 'details',
                'label' => 'Ke stažení',
                'label_plain' => 'Ke stažení',
                'items' => [
                    ['url' => $baseUrl . '/admin/downloads.php', 'label' => 'Soubory a položky'],
                    ['url' => $baseUrl . '/admin/dl_cats.php', 'label' => 'Kategorie'],
                ],
            ];
        }
        if (isModuleEnabled('faq')) {
            $contentItems[] = [
                'type' => 'details',
                'label' => 'Znalostní báze',
                'label_plain' => 'Znalostní báze',
                'items' => [
                    ['url' => $baseUrl . '/admin/faq.php', 'label' => 'Otázky'],
                    ['url' => $baseUrl . '/admin/faq_cats.php', 'label' => 'Kategorie'],
                ],
            ];
        }
        if (isModuleEnabled('forms')) {
            $contentItems[] = [
                'type' => 'details',
                'label' => 'Formuláře',
                'label_plain' => 'Formuláře',
                'items' => [
                    ['url' => $baseUrl . '/admin/forms.php', 'label' => 'Přehled formulářů'],
                ],
            ];
        }
        if (isModuleEnabled('board')) {
            $contentItems[] = [
                'type' => 'details',
                'label' => 'Vývěska a oznámení',
                'label_plain' => 'Vývěska a oznámení',
                'items' => [
                    ['url' => $baseUrl . '/admin/board.php', 'label' => 'Dokumenty a oznámení'],
                    ['url' => $baseUrl . '/admin/board_cats.php', 'label' => 'Kategorie'],
                ],
            ];
        }
        if (isModuleEnabled('food')) {
            $contentItems[] = ['url' => $baseUrl . '/admin/food.php', 'label' => 'Jídelní lístek'];
        }
        if (isModuleEnabled('polls')) {
            $contentItems[] = ['url' => $baseUrl . '/admin/polls.php', 'label' => 'Ankety'];
        }
    }

    $communicationItems = [];
    if (isModuleEnabled('blog') && $canManageComments) {
        $communicationItems[] = [
            'url' => $baseUrl . '/admin/comments.php',
            'label' => 'Komentáře'
                . ($pendingComments > 0
                    ? ' <span class="badge" aria-label="' . $pendingComments . ' ' . $pendingCommentsLabel . '">' . $pendingComments . '</span>'
                    : ''),
        ];
    }
    if ($canManageMessages && isModuleEnabled('contact')) {
        $communicationItems[] = [
            'url' => $baseUrl . '/admin/contact.php',
            'label' => 'Kontakt'
                . ($unreadContactMessages > 0
                    ? ' <span class="badge" aria-label="' . $unreadContactMessages . ' nových kontaktních zpráv">' . $unreadContactMessages . '</span>'
                    : ''),
        ];
    }
    if ($canManageMessages && isModuleEnabled('chat')) {
        $communicationItems[] = [
            'url' => $baseUrl . '/admin/chat.php',
            'label' => 'Chat'
                . ($unreadChatMessages > 0
                    ? ' <span class="badge" aria-label="' . $unreadChatMessages . ' nových chat zpráv">' . $unreadChatMessages . '</span>'
                    : ''),
        ];
    }
    if ($canManageNewsletter && isModuleEnabled('newsletter')) {
        $communicationItems[] = [
            'url' => $baseUrl . '/admin/newsletter.php',
            'label' => 'Newsletter'
                . ($pendingNewsletterSubscribers > 0
                    ? ' <span class="badge" aria-label="' . $pendingNewsletterSubscribers . ' odběratelů čeká na potvrzení">' . $pendingNewsletterSubscribers . '</span>'
                    : ''),
        ];
    }

    $reservationItems = [];
    if (isModuleEnabled('reservations') && currentUserHasCapability('bookings_manage')) {
        $reservationItems[] = ['url' => $baseUrl . '/admin/res_bookings.php', 'label' => 'Rezervace'];
        $reservationItems[] = ['url' => $baseUrl . '/admin/res_resources.php', 'label' => 'Zdroje rezervací'];
        $reservationItems[] = ['url' => $baseUrl . '/admin/res_categories.php', 'label' => 'Kategorie zdrojů rezervací'];
        $reservationItems[] = ['url' => $baseUrl . '/admin/res_locations.php', 'label' => 'Lokality rezervací'];
    }

    $settingsItems = [];
    if (currentUserHasCapability('settings_manage')) {
        $settingsItems[] = ['url' => $baseUrl . '/admin/settings.php', 'label' => 'Obecná nastavení'];
        $settingsItems[] = ['url' => $baseUrl . '/admin/settings_modules.php', 'label' => 'Správa modulů'];
        $settingsItems[] = ['url' => $baseUrl . '/admin/settings_display.php', 'label' => 'Pozice modulů'];
    }
    if (isSuperAdmin()) {
        $settingsItems[] = ['url' => $baseUrl . '/admin/themes.php', 'label' => 'Vzhled a šablony'];
    }
    if (isModuleEnabled('statistics') && currentUserHasCapability('statistics_view')) {
        $settingsItems[] = ['url' => $baseUrl . '/admin/statistics.php', 'label' => 'Statistiky'];
    }
    if (currentUserHasCapability('users_manage')) {
        $settingsItems[] = ['url' => $baseUrl . '/admin/users.php', 'label' => 'Uživatelé a role'];
    }
    if (currentUserHasCapability('import_export_manage')) {
        $settingsItems[] = ['url' => $baseUrl . '/admin/import.php', 'label' => 'Export a import'];
        $settingsItems[] = ['url' => $baseUrl . '/admin/wp_import.php', 'label' => 'Import z WordPressu'];
        $settingsItems[] = ['url' => $baseUrl . '/admin/estranky_import.php', 'label' => 'Import z eStránek'];
        $settingsItems[] = ['url' => $baseUrl . '/admin/estranky_download_photos.php', 'label' => 'Stažení fotek z eStránek'];
        $settingsItems[] = ['url' => $baseUrl . '/admin/integrity.php', 'label' => 'Kontrola integrity'];
        $settingsItems[] = ['url' => $baseUrl . '/admin/backup.php', 'label' => 'Záloha databáze'];
    }
    if (currentUserHasCapability('settings_manage')) {
        $settingsItems[] = ['url' => $baseUrl . '/admin/redirects.php', 'label' => 'Přesměrování'];
    }

    $bottomItems = [
        ['url' => $baseUrl . '/index.php', 'label' => '<span aria-hidden="true">←</span> Web'],
        ['url' => $baseUrl . '/admin/logout.php', 'label' => 'Odhlásit se'],
    ];

    echo '<!DOCTYPE html>' . "\n"
       . '<html lang="cs">' . "\n"
       . '<head>' . "\n"
       . '  <meta charset="utf-8">' . "\n"
       . '  <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
       . '  <title>' . $pageTitle . ' – ' . $siteName . ' Admin</title>' . "\n"
       . '  <style nonce="' . cspNonce() . '">' . "\n"
       . '    *, *::before, *::after { box-sizing: border-box; }' . "\n"
       . '    body { font-family: system-ui, sans-serif; margin: 0; display: flex; min-height: 100vh; }' . "\n"
       . '    nav { background: #222; color: #fff; width: 230px; flex-shrink: 0; padding: 1rem; }' . "\n"
       . '    nav h2 { font-size: 1rem; margin: 0 0 .25rem; color: #ccc; }' . "\n"
       . '    nav h3 { font-size:.78rem; letter-spacing:.04em; text-transform:uppercase; margin:.9rem 0 .35rem; color:#9ca3af; }' . "\n"
       . '    nav ul { list-style: none; margin: 0; padding: 0; }' . "\n"
       . '    nav li { margin: 0; }' . "\n"
       . '    nav section + section { margin-top:.45rem; }' . "\n"
       . '    nav a, nav summary { display: block; min-height: 2.25rem; line-height: 1.35; border-radius: 4px; }' . "\n"
       . '    nav a { color: #ddd; text-decoration: none; font-size: .9rem; padding: .45rem .35rem; }' . "\n"
       . '    nav summary { color: #bbb; font-size: .85rem; }' . "\n"
       . '    nav .nav-list--nested { margin:.2rem 0 0; padding:0; list-style:none; }' . "\n"
       . '    nav a:hover, nav a:focus, nav summary:hover, nav summary:focus { background: rgba(255,255,255,.08); color: #fff; text-decoration: none; }' . "\n"
       . '    main { flex: 1; padding: 1.5rem 2rem; }' . "\n"
       . '    h1 { margin-top: 0; }' . "\n"
       . '    table { border-collapse: collapse; width: 100%; }' . "\n"
       . '    th, td { border: 1px solid #ccc; padding: .4rem .6rem; text-align: left; }' . "\n"
       . '    th { background: #f0f0f0; }' . "\n"
       . '    .btn { padding: .45rem .9rem; cursor: pointer; min-height: 2rem; border: 1px solid #c6d0db; border-radius: .55rem; background: #f8fafc; color: #102a43; }' . "\n"
       . '    .btn-danger { background: #b42318; color: #fff; border: 1px solid #8f1d14; }' . "\n"
       . '    .btn-success { background: #1b5e20; color: #fff; border: 1px solid #154d1a; }' . "\n"
       . '    .button-row { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; }' . "\n"
       . '    .error { color: #b42318; }' . "\n"
       . '    .success { color: #1b5e20; }' . "\n"
       . '    label { display: block; margin-top: 1rem; font-weight: bold; }' . "\n"
       . '    input[type=text], input[type=email], input[type=password], input[type=number], textarea, select {' . "\n"
       . '      width: 100%; padding: .35rem; margin-top: .2rem; }' . "\n"
       . '    textarea { min-height: 200px; }' . "\n"
       . '    .actions form { display: inline; }' . "\n"
       . '    .badge { display:inline-block; min-width:1.4rem; padding:.1rem .45rem; border-radius:999px; background:#b42318; color:#fff; font-size:.75rem; text-align:center; }' . "\n"
       . '    .status-badge { display:inline-flex; align-items:center; gap:.35rem; padding:.2rem .55rem; border-radius:999px; border:1px solid transparent; font-size:.82rem; font-weight:700; line-height:1.25; }' . "\n"
       . '    .status-badge--pending { background:#fff4d6; border-color:#d7b46a; color:#7a4300; }' . "\n"
       . '    .status-badge--published { background:#e8f5ec; border-color:#9cc7a4; color:#1f5f2b; }' . "\n"
       . '    .status-badge--hidden { background:#f2f4f7; border-color:#d0d5dd; color:#344054; }' . "\n"
       . '    .status-badge--scheduled { background:#eaf2ff; border-color:#9bb8e8; color:#1f4f99; }' . "\n"
       . '    .status-badge--current { background:#e4f7ea; border-color:#8fcca2; color:#166534; }' . "\n"
       . '    .status-badge--danger { background:#fdecea; border-color:#f0a39c; color:#8f1d14; }' . "\n"
       . '    .status-badge--neutral { background:#f8fafc; border-color:#cbd5e1; color:#334155; }' . "\n"
       . '    .status-stack { display:grid; gap:.3rem; }' . "\n"
       . '    .table-row--pending { background:#fffaf0; }' . "\n"
       . '    .table-meta { display:block; margin-top:.2rem; color:#475467; font-size:.85rem; line-height:1.4; }' . "\n"
       . '    .field-help { display:block; margin:.35rem 0 0; color:#555; font-size:.92rem; line-height:1.45; font-weight:normal; }' . "\n"
       . '    .field-help code { font-size:.95em; }' . "\n"
       . '    .sr-only, .visually-hidden { position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0; }' . "\n"
       . '    :focus-visible { outline: 3px solid #005fcc; outline-offset: 2px; }' . "\n"
       . '    nav a:focus-visible { outline-color: #7ecfff; }' . "\n"
       . '    .skip-link { position:absolute;left:-999px;top:auto;width:1px;height:1px;overflow:hidden;z-index:999; }' . "\n"
       . '    .skip-link:focus { position:fixed;top:0;left:0;width:auto;height:auto;padding:.75rem 1.5rem;background:#005fcc;color:#fff;font-size:1rem;text-decoration:none;z-index:9999; }' . "\n"
       . '  </style>' . "\n"
       . '</head>' . "\n"
       . '<body>' . "\n"
       . '<a href="#obsah" class="skip-link">Přeskočit na obsah</a>' . "\n"
       . '<nav aria-label="Administrace">' . "\n"
       . '  <h2>' . $siteName . '</h2>' . "\n";

    $userName = h(currentUserDisplayName());
    if ($userName !== '') {
        echo '  <p style="font-size:.8rem;color:#bbb;margin:0 0 .75rem"><span aria-hidden="true">&#9786;</span> ' . $userName . '</p>' . "\n";
    }

    echo '  <ul>' . "\n";
    foreach ($topItems as $item) {
        echo $renderItem($item);
    }
    echo '  </ul>' . "\n";

    echo $renderNavSection('nav-content', 'Obsah webu', $contentItems);
    echo $renderNavSection('nav-communication', 'Komunikace', $communicationItems);
    echo $renderNavSection('nav-reservations', 'Rezervace', $reservationItems);
    echo $renderNavSection('nav-settings', 'Nastavení a správa', $settingsItems);

    echo '  <ul style="margin-top:.8rem">' . "\n";
    foreach ($bottomItems as $item) {
        echo $renderItem($item);
    }
    echo '  </ul>' . "\n"
       . '</nav>' . "\n"
       . '<main id="obsah">' . "\n"
       . '  <div id="a11y-live" role="status" aria-live="polite" aria-atomic="true" class="sr-only"></div>' . "\n"
       . '  <h1>' . $pageTitle . '</h1>' . "\n";
}

function adminFooter(): void
{
    $version = KORA_VERSION;
    $nonce = cspNonce();
    echo '<script nonce="' . $nonce . '">document.addEventListener("DOMContentLoaded",function(){'
       . 'var l=document.getElementById("a11y-live");if(!l)return;'
       . 'var m=document.querySelector(\'[role="status"]:not(#a11y-live),[role="alert"]\');'
       . 'if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);m.removeAttribute("role");}'
       . '});</script>'
       . '<script nonce="' . $nonce . '">document.addEventListener("click",function(e){'
       . 'var b=e.target.closest("[data-confirm]");'
       . 'if(b&&!confirm(b.dataset.confirm)){e.preventDefault();e.stopPropagation();}'
       . '});</script>'
       . '</main>'
       . '<footer style="text-align:center;padding:.5rem;font-size:.75rem;color:#666">'
       . 'Kora CMS ' . $version
       . '</footer>'
       . '</body></html>';
}
