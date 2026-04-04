<?php
require_once __DIR__ . '/../db.php';

function adminFieldHasError(string $fieldName, $errorState, array $fieldErrorMap = []): bool
{
    if (is_array($errorState)) {
        return in_array($fieldName, $errorState, true);
    }

    $errorCode = (string)$errorState;
    if ($errorCode === '' || !isset($fieldErrorMap[$errorCode])) {
        return false;
    }

    return in_array($fieldName, (array)$fieldErrorMap[$errorCode], true);
}

function adminFieldErrorId(string $fieldName, ?string $customId = null): string
{
    return $customId !== null && $customId !== '' ? $customId : $fieldName . '-error';
}

function adminFieldAttributes(
    string $fieldName,
    $errorState,
    array $fieldErrorMap = [],
    array $describedByIds = [],
    ?string $customErrorId = null
): string {
    $hasError = adminFieldHasError($fieldName, $errorState, $fieldErrorMap);
    $ids = [];

    foreach ($describedByIds as $describedById) {
        $describedById = trim((string)$describedById);
        if ($describedById !== '') {
            $ids[] = $describedById;
        }
    }

    if ($hasError) {
        $ids[] = adminFieldErrorId($fieldName, $customErrorId);
    }

    $ids = array_values(array_unique($ids));
    $attributes = $hasError ? ' aria-invalid="true"' : '';
    if ($ids !== []) {
        $attributes .= ' aria-describedby="' . h(implode(' ', $ids)) . '"';
    }

    return $attributes;
}

function adminRenderFieldError(
    string $fieldName,
    $errorState,
    array $fieldErrorMap,
    string $message,
    ?string $customErrorId = null
): void {
    if ($message === '' || !adminFieldHasError($fieldName, $errorState, $fieldErrorMap)) {
        return;
    }

    echo '<small id="' . h(adminFieldErrorId($fieldName, $customErrorId))
        . '" class="field-help field-error">' . h($message) . '</small>';
}

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
    if (isModuleEnabled('blog') && (currentUserHasCapability('blog_manage_own') || canCurrentUserManageAnyBlogTaxonomies())) {
        $blogItems = [];
        if (currentUserHasCapability('blog_taxonomies_manage')) {
            $blogItems[] = ['url' => $baseUrl . '/admin/blogs.php', 'label' => 'Správa blogů'];
        }
        if (canCurrentUserManageAnyBlogTaxonomies()) {
            $blogItems[] = ['url' => $baseUrl . '/admin/blog_members.php', 'label' => 'Týmy blogů'];
        }
        if (currentUserHasCapability('blog_manage_own')) {
            $blogItems[] = ['url' => $baseUrl . '/admin/blog.php', 'label' => 'Články'];
        }
        if (canCurrentUserManageAnyBlogTaxonomies() && hasAnyBlogs()) {
            $blogItems[] = ['url' => $baseUrl . '/admin/blog_cats.php', 'label' => 'Kategorie'];
            $blogItems[] = ['url' => $baseUrl . '/admin/blog_tags.php', 'label' => 'Štítky'];
        }
        if ($blogItems !== []) {
            $contentItems[] = [
                'type' => 'details',
                'label' => 'Blogy',
                'label_plain' => 'Blogy',
                'items' => $blogItems,
            ];
        }
    }
    if (isModuleEnabled('news') && currentUserHasCapability('news_manage_own')) {
        $contentItems[] = ['url' => $baseUrl . '/admin/news.php', 'label' => 'Novinky'];
    }
    if (currentUserHasCapability('content_manage_shared')) {
        $contentItems[] = ['url' => $baseUrl . '/admin/media.php', 'label' => 'Knihovna médií'];
        $contentItems[] = ['url' => $baseUrl . '/admin/pages.php', 'label' => 'Stránky'];
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
            $contentItems[] = ['url' => $baseUrl . '/admin/forms.php', 'label' => 'Formuláře'];
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
        $settingsItems[] = ['url' => $baseUrl . '/admin/menu.php', 'label' => 'Navigace webu'];
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
        $settingsItems[] = ['url' => $baseUrl . '/admin/widgets.php', 'label' => 'Widgety'];
        $settingsItems[] = ['url' => $baseUrl . '/admin/redirects.php', 'label' => 'Přesměrování'];
        $settingsItems[] = ['url' => $baseUrl . '/admin/audit_log.php', 'label' => 'Audit log'];
        $settingsItems[] = ['url' => $baseUrl . '/admin/trash.php', 'label' => 'Koš'];
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
       . '    :root {' . "\n"
       . '      --admin-bg: #ffffff;' . "\n"
       . '      --admin-text: #1a1a2e;' . "\n"
       . '      --admin-text-muted: #555;' . "\n"
       . '      --admin-text-meta: #475467;' . "\n"
       . '      --admin-border: #ccc;' . "\n"
       . '      --admin-surface: #f8fafc;' . "\n"
       . '      --admin-surface-hover: #f0f0f0;' . "\n"
       . '      --admin-link: #005fcc;' . "\n"
       . '      --admin-nav-bg: #222;' . "\n"
       . '      --admin-nav-text: #ddd;' . "\n"
       . '      --admin-nav-text-dim: #ccc;' . "\n"
       . '      --admin-nav-heading: #9ca3af;' . "\n"
       . '      --admin-nav-summary: #bbb;' . "\n"
       . '      --admin-nav-hover: rgba(255,255,255,.08);' . "\n"
       . '      --admin-btn-bg: #f8fafc;' . "\n"
       . '      --admin-btn-text: #102a43;' . "\n"
       . '      --admin-btn-border: #c6d0db;' . "\n"
       . '      --admin-btn-danger-bg: #b42318;' . "\n"
       . '      --admin-btn-danger-border: #8f1d14;' . "\n"
       . '      --admin-btn-success-bg: #1b5e20;' . "\n"
       . '      --admin-btn-success-border: #154d1a;' . "\n"
       . '      --admin-th-bg: #f0f0f0;' . "\n"
       . '      --admin-badge-bg: #b42318;' . "\n"
       . '      --admin-pending-bg: #fff4d6;' . "\n"
       . '      --admin-pending-border: #d7b46a;' . "\n"
       . '      --admin-pending-text: #7a4300;' . "\n"
       . '      --admin-published-bg: #e8f5ec;' . "\n"
       . '      --admin-published-border: #9cc7a4;' . "\n"
       . '      --admin-published-text: #1f5f2b;' . "\n"
       . '      --admin-hidden-bg: #f2f4f7;' . "\n"
       . '      --admin-hidden-border: #d0d5dd;' . "\n"
       . '      --admin-hidden-text: #344054;' . "\n"
       . '      --admin-scheduled-bg: #eaf2ff;' . "\n"
       . '      --admin-scheduled-border: #9bb8e8;' . "\n"
       . '      --admin-scheduled-text: #1f4f99;' . "\n"
       . '      --admin-current-bg: #e4f7ea;' . "\n"
       . '      --admin-current-border: #8fcca2;' . "\n"
       . '      --admin-current-text: #166534;' . "\n"
       . '      --admin-danger-bg: #fdecea;' . "\n"
       . '      --admin-danger-border: #f0a39c;' . "\n"
       . '      --admin-danger-text: #8f1d14;' . "\n"
       . '      --admin-neutral-bg: #f8fafc;' . "\n"
       . '      --admin-neutral-border: #cbd5e1;' . "\n"
       . '      --admin-neutral-text: #334155;' . "\n"
       . '      --admin-pending-row: #fffaf0;' . "\n"
       . '      --admin-error: #b42318;' . "\n"
       . '      --admin-success: #1b5e20;' . "\n"
       . '      --admin-field-help: #555;' . "\n"
       . '      --admin-focus: #005fcc;' . "\n"
       . '      --admin-focus-nav: #7ecfff;' . "\n"
       . '      --admin-invalid-border: #b42318;' . "\n"
       . '      --admin-input-bg: #fff;' . "\n"
       . '      --admin-input-border: #aaa;' . "\n"
       . '      --admin-footer-text: #666;' . "\n"
       . '    }' . "\n"
       . '    @media (prefers-color-scheme: dark) {' . "\n"
       . '      :root {' . "\n"
       . '        --admin-bg: #1a1a2e;' . "\n"
       . '        --admin-text: #e0e0e0;' . "\n"
       . '        --admin-text-muted: #a0a0a0;' . "\n"
       . '        --admin-text-meta: #8899aa;' . "\n"
       . '        --admin-border: #3a3a50;' . "\n"
       . '        --admin-surface: #252540;' . "\n"
       . '        --admin-surface-hover: #2a2a45;' . "\n"
       . '        --admin-link: #4da6ff;' . "\n"
       . '        --admin-nav-bg: #12121f;' . "\n"
       . '        --admin-nav-text: #e0e0e0;' . "\n"
       . '        --admin-nav-text-dim: #b0b0b0;' . "\n"
       . '        --admin-nav-heading: #9090aa;' . "\n"
       . '        --admin-nav-summary: #999;' . "\n"
       . '        --admin-nav-hover: rgba(255,255,255,.1);' . "\n"
       . '        --admin-btn-bg: #252540;' . "\n"
       . '        --admin-btn-text: #e0e0e0;' . "\n"
       . '        --admin-btn-border: #4a4a65;' . "\n"
       . '        --admin-btn-danger-bg: #8f1d14;' . "\n"
       . '        --admin-btn-danger-border: #6b1610;' . "\n"
       . '        --admin-btn-success-bg: #154d1a;' . "\n"
       . '        --admin-btn-success-border: #0f3812;' . "\n"
       . '        --admin-th-bg: #252540;' . "\n"
       . '        --admin-badge-bg: #8f1d14;' . "\n"
       . '        --admin-pending-bg: #3d3220;' . "\n"
       . '        --admin-pending-border: #6b5a30;' . "\n"
       . '        --admin-pending-text: #e8c060;' . "\n"
       . '        --admin-published-bg: #1a3520;' . "\n"
       . '        --admin-published-border: #2a5a35;' . "\n"
       . '        --admin-published-text: #80cc90;' . "\n"
       . '        --admin-hidden-bg: #2a2a40;' . "\n"
       . '        --admin-hidden-border: #4a4a60;' . "\n"
       . '        --admin-hidden-text: #a0a0b8;' . "\n"
       . '        --admin-scheduled-bg: #1a2a45;' . "\n"
       . '        --admin-scheduled-border: #2a4a70;' . "\n"
       . '        --admin-scheduled-text: #80b0e0;' . "\n"
       . '        --admin-current-bg: #1a3520;' . "\n"
       . '        --admin-current-border: #2a5a35;' . "\n"
       . '        --admin-current-text: #80cc90;' . "\n"
       . '        --admin-danger-bg: #3a1a1a;' . "\n"
       . '        --admin-danger-border: #6b2a2a;' . "\n"
       . '        --admin-danger-text: #f0a0a0;' . "\n"
       . '        --admin-neutral-bg: #252540;' . "\n"
       . '        --admin-neutral-border: #4a4a65;' . "\n"
       . '        --admin-neutral-text: #b0b0c8;' . "\n"
       . '        --admin-pending-row: #2d2820;' . "\n"
       . '        --admin-error: #f08080;' . "\n"
       . '        --admin-success: #80cc90;' . "\n"
       . '        --admin-field-help: #a0a0a0;' . "\n"
       . '        --admin-focus: #4da6ff;' . "\n"
       . '        --admin-focus-nav: #7ecfff;' . "\n"
       . '        --admin-invalid-border: #f08080;' . "\n"
       . '        --admin-input-bg: #252540;' . "\n"
       . '        --admin-input-border: #4a4a65;' . "\n"
       . '        --admin-footer-text: #8899aa;' . "\n"
       . '      }' . "\n"
       . '    }' . "\n"
       . '    *, *::before, *::after { box-sizing: border-box; }' . "\n"
       . '    body { font-family: system-ui, sans-serif; margin: 0; display: flex; min-height: 100vh; background: var(--admin-bg); color: var(--admin-text); }' . "\n"
       . '    a { color: var(--admin-link); }' . "\n"
       . '    nav { background: var(--admin-nav-bg); color: var(--admin-nav-text); width: 230px; flex-shrink: 0; padding: 1rem; }' . "\n"
       . '    nav h2 { font-size: 1rem; margin: 0 0 .25rem; color: var(--admin-nav-text-dim); }' . "\n"
       . '    nav h3 { font-size:.78rem; letter-spacing:.04em; text-transform:uppercase; margin:.9rem 0 .35rem; color:var(--admin-nav-heading); }' . "\n"
       . '    nav ul { list-style: none; margin: 0; padding: 0; }' . "\n"
       . '    nav li { margin: 0; }' . "\n"
       . '    nav section + section { margin-top:.45rem; }' . "\n"
       . '    nav a, nav summary { display: block; min-height: 2.25rem; line-height: 1.35; border-radius: 4px; }' . "\n"
       . '    nav a { color: var(--admin-nav-text); text-decoration: none; font-size: .9rem; padding: .45rem .35rem; }' . "\n"
       . '    nav summary { color: var(--admin-nav-summary); font-size: .85rem; }' . "\n"
       . '    nav .nav-list--nested { margin:.2rem 0 0; padding:0; list-style:none; }' . "\n"
       . '    nav a:hover, nav a:focus, nav summary:hover, nav summary:focus { background: var(--admin-nav-hover); color: #fff; text-decoration: none; }' . "\n"
       . '    main { flex: 1; padding: 1.5rem 2rem; }' . "\n"
       . '    h1 { margin-top: 0; }' . "\n"
       . '    table { border-collapse: collapse; width: 100%; }' . "\n"
       . '    th, td { border: 1px solid var(--admin-border); padding: .4rem .6rem; text-align: left; }' . "\n"
       . '    th { background: var(--admin-th-bg); }' . "\n"
       . '    .btn { padding: .45rem .9rem; cursor: pointer; min-height: 2rem; border: 1px solid var(--admin-btn-border); border-radius: .55rem; background: var(--admin-btn-bg); color: var(--admin-btn-text); }' . "\n"
       . '    .btn-danger { background: var(--admin-btn-danger-bg); color: #fff; border: 1px solid var(--admin-btn-danger-border); }' . "\n"
       . '    .btn-success { background: var(--admin-btn-success-bg); color: #fff; border: 1px solid var(--admin-btn-success-border); }' . "\n"
       . '    .button-row { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; }' . "\n"
       . '    .error { color: var(--admin-error); }' . "\n"
       . '    .success { color: var(--admin-success); }' . "\n"
       . '    label { display: block; margin-top: 1rem; font-weight: bold; }' . "\n"
       . '    input[type=text], input[type=email], input[type=password], input[type=number], textarea, select {' . "\n"
       . '      width: 100%; padding: .35rem; margin-top: .2rem; background: var(--admin-input-bg); color: var(--admin-text); border: 1px solid var(--admin-input-border); }' . "\n"
       . '    textarea { min-height: 200px; }' . "\n"
       . '    .actions form { display: inline; }' . "\n"
       . '    .badge { display:inline-block; min-width:1.4rem; padding:.1rem .45rem; border-radius:999px; background:var(--admin-badge-bg); color:#fff; font-size:.75rem; text-align:center; }' . "\n"
       . '    .status-badge { display:inline-flex; align-items:center; gap:.35rem; padding:.2rem .55rem; border-radius:999px; border:1px solid transparent; font-size:.82rem; font-weight:700; line-height:1.25; }' . "\n"
       . '    .status-badge--pending { background:var(--admin-pending-bg); border-color:var(--admin-pending-border); color:var(--admin-pending-text); }' . "\n"
       . '    .status-badge--published { background:var(--admin-published-bg); border-color:var(--admin-published-border); color:var(--admin-published-text); }' . "\n"
       . '    .status-badge--hidden { background:var(--admin-hidden-bg); border-color:var(--admin-hidden-border); color:var(--admin-hidden-text); }' . "\n"
       . '    .status-badge--scheduled { background:var(--admin-scheduled-bg); border-color:var(--admin-scheduled-border); color:var(--admin-scheduled-text); }' . "\n"
       . '    .status-badge--current { background:var(--admin-current-bg); border-color:var(--admin-current-border); color:var(--admin-current-text); }' . "\n"
       . '    .status-badge--danger { background:var(--admin-danger-bg); border-color:var(--admin-danger-border); color:var(--admin-danger-text); }' . "\n"
       . '    .status-badge--neutral { background:var(--admin-neutral-bg); border-color:var(--admin-neutral-border); color:var(--admin-neutral-text); }' . "\n"
       . '    .status-stack { display:grid; gap:.3rem; }' . "\n"
       . '    .table-row--pending { background:var(--admin-pending-row); }' . "\n"
       . '    .table-meta { display:block; margin-top:.2rem; color:var(--admin-text-meta); font-size:.85rem; line-height:1.4; }' . "\n"
       . '    .field-help { display:block; margin:.35rem 0 0; color:var(--admin-field-help); font-size:.92rem; line-height:1.45; font-weight:normal; }' . "\n"
       . '    .field-error { color:var(--admin-error); font-weight:700; }' . "\n"
       . '    .field-help code { font-size:.95em; }' . "\n"
       . '    input[aria-invalid="true"], textarea[aria-invalid="true"], select[aria-invalid="true"] { border:2px solid var(--admin-invalid-border); }' . "\n"
       . '    .sr-only, .visually-hidden { position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0; }' . "\n"
       . '    :focus-visible { outline: 3px solid var(--admin-focus); outline-offset: 2px; }' . "\n"
       . '    nav a:focus-visible { outline-color: var(--admin-focus-nav); }' . "\n"
       . '    .skip-link { position:absolute;left:-999px;top:auto;width:1px;height:1px;overflow:hidden;z-index:999; }' . "\n"
       . '    .skip-link:focus { position:fixed;top:0;left:0;width:auto;height:auto;padding:.75rem 1.5rem;background:var(--admin-focus);color:#fff;font-size:1rem;text-decoration:none;z-index:9999; }' . "\n"
       . '    @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration:0.01ms!important;animation-iteration-count:1!important;transition-duration:0.01ms!important; } }' . "\n"
       . '  </style>' . "\n"
       . '</head>' . "\n"
       . '<body>' . "\n"
       . '<a href="#obsah" class="skip-link">Přeskočit na obsah</a>' . "\n"
       . '<nav aria-label="Administrace">' . "\n"
       . '  <h2>' . $siteName . '</h2>' . "\n";

    $userName = h(currentUserDisplayName());
    if ($userName !== '') {
        echo '  <p style="font-size:.8rem;color:var(--admin-nav-summary);margin:0 0 .75rem"><span aria-hidden="true">&#9786;</span> ' . $userName . '</p>' . "\n";
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
       . '<script nonce="' . $nonce . '">'
       . '(function(){'
       . 'var form=document.querySelector(\'form[method="post"]\');'
       . 'if(!form||!form.querySelector(\'textarea\'))return;'
       . 'var key=\'kora_autosave_\'+location.pathname+(new URLSearchParams(location.search).get(\'id\')||\'_new\');'
       . 'function gather(){'
       . 'var d={};'
       . 'document.querySelectorAll(\'.ql-container\').forEach(function(container){'
       . 'var editor=container.querySelector(\'.ql-editor\');'
       . 'var ta=container.parentNode.querySelector(\'textarea\');'
       . 'if(editor&&ta)ta.value=editor.innerHTML;'
       . '});'
       . 'form.querySelectorAll(\'input[type="text"][name],input[type="datetime-local"][name],textarea[name],select[name]\').forEach(function(el){'
       . 'if(el.name===\'csrf_token\')return;'
       . 'd[el.name]=el.value;'
       . '});'
       . 'form.querySelectorAll(\'input[type="checkbox"][name],input[type="radio"][name]\').forEach(function(el){'
       . 'if(el.name===\'csrf_token\')return;'
       . 'd[\'__chk_\'+el.name+\'_\'+el.value]=el.checked?\'1\':\'0\';'
       . '});'
       . 'd._ts=Date.now();'
       . 'return d;'
       . '}'
       . 'function restore(d){'
       . 'Object.keys(d).forEach(function(k){'
       . 'if(k===\'_ts\')return;'
       . 'if(k.indexOf(\'__chk_\')===0){'
       . 'var parts=k.substring(6);'
       . 'var lastUnderscore=parts.lastIndexOf(\'_\');'
       . 'var name=parts.substring(0,lastUnderscore);'
       . 'var val=parts.substring(lastUnderscore+1);'
       . 'var el=form.querySelector(\'input[name="\'+CSS.escape(name)+\'"][value="\'+CSS.escape(val)+\'"]\');'
       . 'if(el)el.checked=(d[k]===\'1\');'
       . 'return;'
       . '}'
       . 'var el=form.querySelector(\'[name="\'+CSS.escape(k)+\'"]\');'
       . 'if(el)el.value=d[k];'
       . '});'
       . 'document.querySelectorAll(\'.ql-container\').forEach(function(container){'
       . 'var editor=container.querySelector(\'.ql-editor\');'
       . 'var ta=container.parentNode.querySelector(\'textarea\');'
       . 'if(editor&&ta&&ta.value)editor.innerHTML=ta.value;'
       . '});'
       . '}'
       . 'try{'
       . 'var raw=localStorage.getItem(key);'
       . 'if(raw){'
       . 'var saved=JSON.parse(raw);'
       . 'if(saved._ts&&(Date.now()-saved._ts)<86400000){'
       . 'var banner=document.createElement(\'div\');'
       . 'banner.setAttribute(\'role\',\'status\');'
       . 'banner.style.cssText=\'background:var(--admin-pending-bg);border:1px solid var(--admin-pending-border);color:var(--admin-pending-text);padding:.7rem 1rem;border-radius:6px;margin-bottom:1rem;display:flex;align-items:center;gap:.7rem;flex-wrap:wrap\';'
       . 'banner.innerHTML=\'<span>Nalezen neuložený koncept z \'+new Date(saved._ts).toLocaleString(\'cs-CZ\')+\'.</span>\''
       . '+\'<button type="button" style="padding:.3rem .8rem;cursor:pointer">Obnovit</button>\''
       . '+\'<button type="button" style="padding:.3rem .8rem;cursor:pointer">Zahodit</button>\';'
       . 'var btns=banner.querySelectorAll(\'button\');'
       . 'btns[0].addEventListener(\'click\',function(){restore(saved);banner.remove();});'
       . 'btns[1].addEventListener(\'click\',function(){localStorage.removeItem(key);banner.remove();});'
       . 'form.parentNode.insertBefore(banner,form);'
       . '}else{localStorage.removeItem(key);}'
       . '}'
       . '}catch(e){}'
       . 'setInterval(function(){try{localStorage.setItem(key,JSON.stringify(gather()));}catch(e){}},30000);'
       . 'form.addEventListener(\'submit\',function(){try{localStorage.removeItem(key);}catch(e){}});'
       . '})();'
       . '</script>'
       . '<script nonce="' . $nonce . '">'
       . '(function(){'
       . 'var ta=document.querySelector(\'textarea[name="content"]\');'
       . 'if(!ta)return;'
       . 'var info=document.createElement(\'small\');'
       . 'info.style.cssText=\'display:block;margin-top:.3rem;color:var(--admin-text-muted);font-size:.8rem\';'
       . 'info.setAttribute(\'aria-live\',\'polite\');'
       . 'ta.parentNode.insertBefore(info,ta.nextSibling);'
       . 'function update(){'
       . 'var text=ta.value.replace(/<[^>]*>/g,\' \');'
       . 'var words=text.trim()===\'\'?0:(text.trim().match(/\\S+/g)||[]).length;'
       . 'var chars=ta.value.length;'
       . 'var mins=Math.max(1,Math.round(words/200));'
       . 'info.textContent=words+\' slov, \'+chars+\' znaků, ~\'+mins+\' min čtení\';'
       . '}'
       . 'ta.addEventListener(\'input\',update);'
       . 'update();'
       . '})();'
       . '</script>'
       . '<script nonce="' . $nonce . '">'
       . '(function(){'
       . 'var mt=document.getElementById("meta_title");'
       . 'var md=document.getElementById("meta_description");'
       . 'if(!mt||!md)return;'
       . 'var title=document.getElementById("title");'
       . 'var slug=document.getElementById("slug");'
       . 'var box=document.createElement("div");'
       . 'box.style.cssText="margin-top:.8rem;padding:.8rem;background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:8px;font-family:arial,sans-serif;max-width:600px";'
       . 'box.innerHTML=\'<div style="font-size:1.1rem;color:var(--admin-link);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" id="seo-prev-title"></div>\''
       . '+\'<div style="font-size:.8rem;color:var(--admin-success);margin:.2rem 0" id="seo-prev-url"></div>\''
       . '+\'<div style="font-size:.85rem;color:var(--admin-text-muted);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden" id="seo-prev-desc"></div>\''
       . '+\'<small style="color:var(--admin-text-muted);margin-top:.4rem;display:block" id="seo-prev-counts"></small>\';'
       . 'md.parentNode.insertBefore(box,md.nextSibling);'
       . 'var pt=document.getElementById("seo-prev-title");'
       . 'var pu=document.getElementById("seo-prev-url");'
       . 'var pd=document.getElementById("seo-prev-desc");'
       . 'var pc=document.getElementById("seo-prev-counts");'
       . 'function upd(){'
       . 'var t=mt.value||(title?title.value:"")||"";'
       . 'var d=md.value||"";'
       . 'var s=slug?slug.value:"";'
       . 'pt.textContent=t||"(bez titulku)";'
       . 'pu.textContent=location.origin+"/"+s;'
       . 'pd.textContent=d||"(bez popisu)";'
       . 'var tl=t.length,dl=d.length;'
       . 'pc.textContent="Titulek: "+tl+"/60 znak\xc5\xaf"+(tl>60?" \xe2\x9a\xa0":"")+"  \xc2\xb7  Popis: "+dl+"/160 znak\xc5\xaf"+(dl>160?" \xe2\x9a\xa0":"");'
       . '}'
       . 'mt.addEventListener("input",upd);'
       . 'md.addEventListener("input",upd);'
       . 'if(title)title.addEventListener("input",upd);'
       . 'if(slug)slug.addEventListener("input",upd);'
       . 'upd();'
       . '})();'
       . '</script>'
       . '</main>'
       . '<footer style="text-align:center;padding:.5rem;font-size:.75rem;color:var(--admin-footer-text)">'
       . 'Kora CMS ' . $version
       . '</footer>'
       . '</body></html>';
}
