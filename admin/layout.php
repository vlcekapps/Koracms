<?php

require_once __DIR__ . '/../db.php';

/**
 * @param string|list<string> $errorState
 * @param array<string, list<string>> $fieldErrorMap
 */
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

/**
 * @param string|list<string> $errorState
 * @param array<string, list<string>> $fieldErrorMap
 * @param list<string> $describedByIds
 */
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

/**
 * @param string|list<string> $errorState
 * @param array<string, list<string>> $fieldErrorMap
 */
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
        return '    <li><a href="' . $item['url'] . '">' . $item['label'] . '</a></li>' . "\n";
    };

    $renderNestedItem = static function (array $item): string {
        return '          <li><a class="nav-link--nested" href="' . $item['url'] . '">' . $item['label'] . '</a></li>' . "\n";
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

            $html .= '      <li>' . "\n"
                . '        <details>' . "\n"
                . '          <summary class="nav-summary">' . $item['label'] . '</summary>' . "\n"
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
       . '    nav .nav-summary { cursor:pointer; color:var(--admin-nav-text); font-size:.9rem; padding:.45rem .35rem; border-radius:4px; list-style:none; user-select:none; }' . "\n"
       . '    nav .nav-list--nested { margin:.2rem 0 0; padding:0; list-style:none; }' . "\n"
       . '    nav .nav-link--nested { padding-left:.95rem; font-size:.85rem; color:var(--admin-nav-text); }' . "\n"
       . '    nav a:hover, nav a:focus, nav summary:hover, nav summary:focus { background: var(--admin-nav-hover); color: #fff; text-decoration: none; }' . "\n"
       . '    main { flex: 1; padding: 1.5rem 2rem; }' . "\n"
       . '    h1 { margin-top: 0; }' . "\n"
       . '    table { border-collapse: collapse; width: 100%; }' . "\n"
       . '    th, td { border: 1px solid var(--admin-border); padding: .4rem .6rem; text-align: left; }' . "\n"
       . '    th { background: var(--admin-th-bg); }' . "\n"
       . '    .btn { padding: .45rem .9rem; cursor: pointer; min-height: 2rem; border: 1px solid var(--admin-btn-border); border-radius: .55rem; background: var(--admin-btn-bg); color: var(--admin-btn-text); }' . "\n"
       . '    .btn-danger { background: var(--admin-btn-danger-bg); color: #fff; border: 1px solid var(--admin-btn-danger-border); }' . "\n"
       . '    .btn-success { background: var(--admin-btn-success-bg); color: #fff; border: 1px solid var(--admin-btn-success-border); }' . "\n"
       . '    .btn-muted { background:#555; color:#fff; border-color:#444; }' . "\n"
       . '    .admin-modal-open { overflow:hidden; }' . "\n"
       . '    .blog-admin-fieldset { margin:0 0 1rem; }' . "\n"
       . '    .blog-admin-fieldset--flush { margin:0; }' . "\n"
       . '    .blog-admin-control-auto { width:auto; }' . "\n"
       . '    .blog-admin-check-row { margin-top:.5rem; }' . "\n"
       . '    .blog-admin-form-actions { margin-top:.75rem; }' . "\n"
       . '    .blog-admin-form-actions--dialog { margin-top:1rem; }' . "\n"
       . '    .blog-sort-row { cursor:grab; }' . "\n"
       . '    .blog-button--compact { font-size:.85rem; }' . "\n"
       . '    .blog-inline-form { display:inline; }' . "\n"
       . '    .blog-dialog-overlay { position:fixed; inset:0; background:rgba(15, 23, 42, .54); z-index:1000; }' . "\n"
       . '    .blog-dialog[hidden], .blog-dialog-overlay[hidden] { display:none!important; }' . "\n"
       . '    .blog-dialog { position:fixed; inset:50% auto auto 50%; transform:translate(-50%, -50%); width:min(30rem, calc(100vw - 2rem)); max-height:calc(100vh - 2rem); overflow:auto; padding:1.2rem; border:1px solid #cbd5e1; border-radius:.9rem; background:#fff; box-shadow:0 28px 60px rgba(15, 23, 42, .28); z-index:1001; }' . "\n"
       . '    .blog-dialog__header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }' . "\n"
       . '    .blog-dialog__title { margin:0; font-size:1.15rem; }' . "\n"
       . '    .blog-dialog__description, .blog-logo-current { margin-top:0; }' . "\n"
       . '    .blog-logo-preview { display:block; margin-top:.4rem; max-width:min(100%, 20rem); max-height:7rem; height:auto; width:auto; }' . "\n"
       . '    .blog-dialog-label-spaced { margin-top:.75rem; }' . "\n"
       . '    .widgets-intro { font-size:.9rem; }' . "\n"
       . '    .widget-panel { margin-bottom:1.5rem; border:1px solid #d6d6d6; border-radius:10px; padding:.85rem 1rem; }' . "\n"
       . '    .widget-add-zone { margin-bottom:.75rem; max-width:18rem; }' . "\n"
       . '    .widget-add-actions, .widget-sort-item__actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }' . "\n"
       . '    .widget-inline-form { display:inline; }' . "\n"
       . '    .widget-button--compact { font-size:.85rem; }' . "\n"
       . '    .widget-empty, .widget-help--flush { margin:0; }' . "\n"
       . '    .widget-sort-list { list-style:none; padding:0; margin:0; }' . "\n"
       . '    .widget-sort-item { display:flex; align-items:flex-start; gap:.75rem; padding:.65rem .5rem; border-bottom:1px solid #eee; flex-wrap:wrap; cursor:grab; }' . "\n"
       . '    .widget-sort-item--inactive { opacity:.5; }' . "\n"
       . '    .widget-sort-item--dragging { opacity:.4; }' . "\n"
       . '    .widget-sort-item__body { min-width:14rem; flex:1 1 16rem; }' . "\n"
       . '    .widget-sort-item__meta { color:#555; }' . "\n"
       . '    .widget-sort-item__tools { display:flex; flex-direction:column; gap:.35rem; align-items:flex-start; }' . "\n"
       . '    .widget-sort-item__warning { margin:0; max-width:26rem; }' . "\n"
       . '    .widget-sort-item__actions { gap:.4rem; }' . "\n"
       . '    .widget-dialog-overlay { position:fixed; inset:0; background:rgba(15, 23, 42, .54); z-index:1000; }' . "\n"
       . '    .widget-dialog[hidden], .widget-dialog-overlay[hidden], .widget-dialog-field[hidden], .widget-dialog-fieldset[hidden] { display:none!important; }' . "\n"
       . '    .widget-dialog { position:fixed; inset:50% auto auto 50%; transform:translate(-50%, -50%); width:min(32rem, calc(100vw - 2rem)); max-height:calc(100vh - 2rem); overflow:auto; padding:1.2rem; border:1px solid #cbd5e1; border-radius:.9rem; background:#fff; box-shadow:0 28px 60px rgba(15, 23, 42, .28); z-index:1001; }' . "\n"
       . '    .widget-dialog__header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }' . "\n"
       . '    .widget-dialog__title { margin:0; font-size:1.15rem; }' . "\n"
       . '    .widget-dialog__description { margin-top:0; }' . "\n"
       . '    .widget-dialog-fieldset { margin:0 0 1rem; border:1px solid #d6d6d6; border-radius:10px; padding:.85rem 1rem; }' . "\n"
       . '    .widget-dialog-fieldset--dynamic, .widget-dialog-fieldset--nested { margin:0; }' . "\n"
       . '    .widget-dialog-field { margin-top:.75rem; }' . "\n"
       . '    .widget-dialog-field--compact { margin-top:.5rem; }' . "\n"
       . '    .widget-dialog-checkbox { font-weight:normal; margin-top:.5rem; }' . "\n"
       . '    .widget-dialog-number-input { width:6rem; }' . "\n"
       . '    .widget-dialog-actions { margin-top:1rem; }' . "\n"
       . '    .settings-nav { margin:1rem 0 1.5rem; }' . "\n"
       . '    .settings-checkbox-row { margin-top:1rem; }' . "\n"
       . '    .settings-checkbox-row--compact { margin-top:.5rem; }' . "\n"
       . '    .settings-note { margin-top:.5rem; font-size:.9rem; color:var(--admin-field-help); }' . "\n"
       . '    .settings-muted { margin-top:.25rem; color:var(--admin-field-help); }' . "\n"
       . '    .settings-profile-card { margin-top:.85rem; padding:.85rem 1rem; border:1px solid var(--admin-border); border-radius:10px; }' . "\n"
       . '    .settings-profile-description { margin:.45rem 0 0 1.8rem; color:var(--admin-text-muted); }' . "\n"
       . '    .settings-fieldset-spaced { margin-top:1rem; }' . "\n"
       . '    .settings-form-row { margin-bottom:.75rem; }' . "\n"
       . '    .settings-input-short { width:20rem; }' . "\n"
       . '    .settings-code-textarea { width:100%; max-width:600px; font-family:monospace; font-size:.85rem; }' . "\n"
       . '    .settings-current-favicon { height:20px; vertical-align:middle; }' . "\n"
       . '    .settings-current-logo { max-height:40px; vertical-align:middle; }' . "\n"
       . '    .settings-submit { margin-top:1rem; }' . "\n"
       . '    .res-bookings-toolbar { margin-bottom:1rem; }' . "\n"
       . '    .res-bookings-filter { margin-bottom:1rem; }' . "\n"
       . '    .res-bookings-filter-row { display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end; }' . "\n"
       . '    .res-bookings-filter-select { width:auto; }' . "\n"
       . '    .res-bookings-hidden-note { color:#666; font-size:.85rem; margin-top:-.5rem; margin-bottom:1rem; }' . "\n"
       . '    .res-bookings-pager-list { list-style:none; display:flex; gap:.5rem; padding:0; margin-top:1rem; flex-wrap:wrap; }' . "\n"
       . '    .res-booking-status--pending { color:#8a4b00; }' . "\n"
       . '    .res-booking-status--confirmed { color:#1b5e20; }' . "\n"
       . '    .res-booking-status--cancelled { color:#666; }' . "\n"
       . '    .res-booking-status--rejected { color:#b71c1c; }' . "\n"
       . '    .res-booking-status--completed { color:#005fcc; }' . "\n"
       . '    .res-booking-status--no_show { color:#6d0000; }' . "\n"
       . '    .res-booking-required-note { margin-top:0; font-size:.9rem; }' . "\n"
       . '    .res-booking-mode-row { display:flex; gap:1.5rem; margin-top:.5rem; flex-wrap:wrap; }' . "\n"
       . '    .res-booking-fieldset { border:1px solid var(--admin-border); border-radius:10px; padding:.85rem 1rem; }' . "\n"
       . '    .res-booking-fieldset--spaced { margin-top:1rem; }' . "\n"
       . '    .res-booking-time-row { display:flex; gap:1rem; flex-wrap:wrap; }' . "\n"
       . '    .res-booking-input-auto { width:auto; }' . "\n"
       . '    .res-booking-input-stacked { width:auto; display:block; margin-top:.2rem; }' . "\n"
       . '    .res-booking-party-size { width:100px; }' . "\n"
       . '    .res-booking-note { min-height:80px; }' . "\n"
       . '    .res-booking-actions { margin-top:1.5rem; }' . "\n"
       . '    .res-booking-actions.button-row--top { margin-top:0; margin-bottom:1rem; align-items:flex-start; }' . "\n"
       . '    .res-booking-form { margin-bottom:1rem; }' . "\n"
       . '    .res-booking-textarea--reject { min-height:80px; }' . "\n"
       . '    .res-booking-textarea--compact { min-height:60px; max-width:400px; }' . "\n"
       . '    .res-booking-action-row { margin-top:.5rem; }' . "\n"
       . '    .res-booking-complete-button { background:#005fcc; color:#fff; }' . "\n"
       . '    .res-booking-pending-note { color:#666; font-style:italic; }' . "\n"
       . '    .media-upload-form { margin-bottom:1.5rem; }' . "\n"
       . '    .media-upload-submit { margin-top:.75rem; }' . "\n"
       . '    .media-filter-form, .media-bulk-form { margin-bottom:1rem; }' . "\n"
       . '    .media-filter-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:.75rem; align-items:end; }' . "\n"
       . '    .media-filter-actions { margin-top:1.4rem; }' . "\n"
       . '    .media-result-summary { margin-bottom:1rem; }' . "\n"
       . '    .media-bulk-actions { margin-bottom:1rem; }' . "\n"
       . '    .media-bulk-label { margin-top:0; }' . "\n"
       . '    .media-bulk-select { max-width:280px; }' . "\n"
       . '    .media-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:1rem; }' . "\n"
       . '    .media-card { border:1px solid #d6d6d6; border-radius:.85rem; background:#fff; overflow:hidden; display:flex; flex-direction:column; }' . "\n"
       . '    .media-card__header { display:flex; align-items:center; justify-content:space-between; padding:.55rem .7rem; border-bottom:1px solid #e5e7eb; background:#f8fafc; }' . "\n"
       . '    .media-card__checkbox { margin:0; font-weight:600; display:flex; align-items:center; gap:.45rem; }' . "\n"
       . '    .media-card__link { display:block; }' . "\n"
       . '    .media-card__image { width:100%; aspect-ratio:1; object-fit:cover; display:block; }' . "\n"
       . '    .media-card__placeholder { aspect-ratio:1; display:flex; align-items:center; justify-content:center; background:#f0f2f5; color:#334155; font-weight:700; }' . "\n"
       . '    .media-card__body { padding:.75rem; display:grid; gap:.45rem; }' . "\n"
       . '    .media-card__title { display:block; word-break:break-word; }' . "\n"
       . '    .media-edit-section { margin-top:2rem; border-top:1px solid #d6d6d6; padding-top:1.5rem; }' . "\n"
       . '    .media-edit-title { margin-top:0; }' . "\n"
       . '    .media-edit-grid { display:grid; grid-template-columns:minmax(260px,1fr) minmax(260px,1fr); gap:1.5rem; }' . "\n"
       . '    .media-alt-warning { color:#b42318; }' . "\n"
       . '    .media-caption-textarea { min-height:7rem; }' . "\n"
       . '    .media-edit-actions { margin-top:1rem; }' . "\n"
       . '    .media-replace-form { margin-top:1rem; }' . "\n"
       . '    .media-replace-submit { margin-top:.8rem; }' . "\n"
       . '    .media-info-list { display:grid; grid-template-columns:auto 1fr; gap:.35rem .75rem; margin:0; }' . "\n"
       . '    .media-info-list dd { margin:0; }' . "\n"
       . '    .media-info-list__value--break { word-break:break-word; }' . "\n"
       . '    .media-info-list__value--url { word-break:break-all; }' . "\n"
       . '    .media-usage-fieldset { margin-top:1rem; }' . "\n"
       . '    .media-empty-usage { margin:0; }' . "\n"
       . '    .media-usage-list { margin:0; padding-left:1.2rem; }' . "\n"
       . '    .blog-form-missing-taxonomy { margin-top:.75rem; }' . "\n"
       . '    .blog-form-fieldset { margin-top:1rem; border:1px solid #ccc; padding:.5rem 1rem; }' . "\n"
       . '    .blog-form-fieldset--seo { margin-top:1.5rem; }' . "\n"
       . '    .blog-form-tag-label { display:inline-block; margin-right:1rem; font-weight:normal; }' . "\n"
       . '    .blog-form-checkbox-row { font-weight:normal; margin-top:.3rem; }' . "\n"
       . '    .blog-form-actions { margin-top:1.5rem; }' . "\n"
       . '    .blog-form-preview-note { color:#666; }' . "\n"
       . '    .blog-form-wysiwyg-wrapper { background:#fff; border:1px solid #ccc; margin-top:.2rem; min-height:300px; }' . "\n"
       . '    .button-row { display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; }' . "\n"
       . '    .button-row--start { justify-content:flex-start; }' . "\n"
       . '    .button-row--between { justify-content:space-between; }' . "\n"
       . '    .button-row--top { align-items:flex-start; }' . "\n"
       . '    .button-row--baseline { align-items:baseline; }' . "\n"
       . '    .admin-stack-sm { margin-bottom:1rem; }' . "\n"
       . '    .admin-stack-md { margin-bottom:1.5rem; }' . "\n"
       . '    .admin-stack-lg { margin-bottom:2rem; }' . "\n"
       . '    .admin-section-spaced { margin-top:1.5rem; }' . "\n"
       . '    .admin-section-spaced--balanced { margin:1.5rem 0; }' . "\n"
       . '    .admin-copy { margin:.2rem 0 .45rem; }' . "\n"
       . '    .admin-copy--compact { margin:.2rem 0 0; }' . "\n"
       . '    .admin-copy--flush { margin:0; }' . "\n"
       . '    .admin-warning-box { background:var(--admin-pending-bg); border:1px solid var(--admin-pending-border); color:var(--admin-pending-text); padding:.75rem 1rem; margin-bottom:1rem; border-radius:4px; }' . "\n"
       . '    .admin-inline-label { display:inline; margin-top:0; }' . "\n"
       . '    .admin-checkbox-label { display:inline; margin-top:0; font-weight:normal; }' . "\n"
       . '    .admin-inline-form { display:inline; }' . "\n"
       . '    .admin-description { font-size:.9rem; }' . "\n"
       . '    .admin-description--flush { margin-top:0; }' . "\n"
       . '    .admin-description--muted { color:var(--admin-field-help); }' . "\n"
       . '    .admin-heading-row h2 { margin-bottom:.5rem; }' . "\n"
       . '    .admin-section-heading { margin-top:2rem; }' . "\n"
       . '    .admin-search-input { width:min(100%,24rem); }' . "\n"
       . '    .admin-input-xs { width:5rem; }' . "\n"
       . '    .admin-input-sm { width:200px; }' . "\n"
       . '    .admin-input-compact { width:8rem; }' . "\n"
       . '    .admin-input-auto { width:auto; }' . "\n"
       . '    .admin-textarea-compact { min-height:0; }' . "\n"
       . '    .admin-rich-editor-sm { min-height:180px; }' . "\n"
       . '    .admin-rich-editor-base { min-height:200px; }' . "\n"
       . '    .admin-rich-editor-md { min-height:16rem; }' . "\n"
       . '    .admin-rich-editor-lg { min-height:220px; }' . "\n"
       . '    .admin-rich-editor-tall { min-height:300px; }' . "\n"
       . '    .admin-select-sm { min-width:150px; }' . "\n"
       . '    .admin-select-md { min-width:180px; }' . "\n"
       . '    .admin-select-lg { min-width:15rem; }' . "\n"
       . '    .admin-fieldset-card { margin:0 0 .85rem; border:1px solid var(--admin-border); border-radius:10px; padding:.85rem 1rem; }' . "\n"
       . '    .admin-fieldset-spaced { margin-top:1.5rem; }' . "\n"
       . '    .admin-field-row { margin-top:.5rem; }' . "\n"
       . '    .admin-filter-form { margin-bottom:1.5rem; }' . "\n"
       . '    .admin-filter-fieldset { border:0; padding:0; margin:0; }' . "\n"
       . '    .admin-compact-label { margin:0; font-size:.85rem; }' . "\n"
       . '    .admin-form-grid { display:flex; gap:1rem; flex-wrap:wrap; }' . "\n"
       . '    .admin-form-grid--start { align-items:flex-start; }' . "\n"
       . '    .admin-form-grid--end { align-items:flex-end; }' . "\n"
       . '    .admin-form-grid__cell { flex:1 1 12rem; }' . "\n"
       . '    .admin-form-grid__cell--wide { flex-basis:18rem; }' . "\n"
       . '    .admin-form-grid__cell--date { flex-basis:16rem; }' . "\n"
       . '    .admin-date-row { display:flex; gap:2rem; flex-wrap:wrap; margin-top:1rem; }' . "\n"
       . '    .admin-action-row { margin-top:1rem; }' . "\n"
       . '    .admin-preview-block { margin:.75rem 0; }' . "\n"
       . '    .admin-image-preview { max-width:16rem; height:auto; border:1px solid var(--admin-border); border-radius:.75rem; }' . "\n"
       . '    .admin-image-preview--large { display:block; max-width:320px; width:100%; }' . "\n"
       . '    .admin-image-preview--medium { display:block; max-width:300px; }' . "\n"
       . '    .admin-image-preview--wide { display:block; max-width:280px; width:100%; }' . "\n"
       . '    .admin-image-preview--podcast { display:block; max-width:18rem; width:100%; border-radius:1rem; }' . "\n"
       . '    .admin-avatar-preview { width:48px; height:48px; object-fit:cover; border:1px solid var(--admin-border); border-radius:999px; vertical-align:middle; }' . "\n"
       . '    .admin-inline-edit-form { display:flex; flex-direction:column; gap:.4rem; }' . "\n"
       . '    .admin-sort-list { list-style:none; padding:0; margin:0; max-width:62rem; }' . "\n"
       . '    .admin-sort-item { display:flex; align-items:center; gap:.75rem; padding:.55rem 0; border-bottom:1px solid var(--admin-border); flex-wrap:wrap; cursor:grab; }' . "\n"
       . '    .admin-sort-item--muted, .admin-sort-item--dragging { opacity:.6; }' . "\n"
       . '    .admin-sort-item__body { min-width:14rem; flex:1 1 18rem; }' . "\n"
       . '    .admin-sort-controls { display:inline-flex; gap:2px; margin-left:6px; vertical-align:middle; }' . "\n"
       . '    .admin-sort-control { padding:1px 6px; cursor:pointer; font-size:.85rem; line-height:1; }' . "\n"
       . '    .admin-order-list { list-style:none; padding:0; margin:0; max-width:30rem; }' . "\n"
       . '    .admin-order-item { display:flex; align-items:center; gap:.5rem; padding:.4rem 0; border-bottom:1px solid var(--admin-border); flex-wrap:wrap; }' . "\n"
       . '    .admin-order-item__label { min-width:10rem; }' . "\n"
       . '    .admin-order-item__label--muted { color:var(--admin-text-muted); }' . "\n"
       . '    .admin-summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; }' . "\n"
       . '    .admin-summary-card { border:1px solid var(--admin-border); border-radius:10px; padding:1rem; background:var(--admin-surface); }' . "\n"
       . '    .admin-summary-card__heading { margin:.1rem 0 .35rem; font-size:1rem; }' . "\n"
       . '    .admin-summary-card__value { margin:.2rem 0 .75rem; font-size:1.8rem; font-weight:700; }' . "\n"
       . '    .admin-summary-card__footer { margin-bottom:0; }' . "\n"
       . '    .admin-stat-chart { margin:0 0 1.5rem; }' . "\n"
       . '    .admin-stat-chart--compact { margin-bottom:1rem; }' . "\n"
       . '    .admin-stat-bars { list-style:none; padding:0; margin:.75rem 0; display:grid; gap:.55rem; max-width:58rem; }' . "\n"
       . '    .admin-stat-bar { display:grid; grid-template-columns:minmax(5rem,12rem) minmax(8rem,1fr) minmax(9rem,auto); gap:.65rem; align-items:center; }' . "\n"
       . '    .admin-stat-bar__label { color:var(--admin-text-meta); font-size:.85rem; }' . "\n"
       . '    .admin-stat-bar__value { font-weight:700; text-align:right; }' . "\n"
       . '    .admin-stat-progress { display:block; width:100%; height:1rem; }' . "\n"
       . '    .admin-option-row { display:flex; gap:.5rem; align-items:center; margin-bottom:.5rem; }' . "\n"
       . '    .admin-option-row__input { flex:1; }' . "\n"
       . '    .admin-result-list { margin-top:.75rem; }' . "\n"
       . '    .admin-result-item { margin-bottom:.75rem; }' . "\n"
       . '    .admin-result-row { display:flex; justify-content:space-between; gap:1rem; margin-bottom:.2rem; }' . "\n"
       . '    .admin-progress { display:block; width:100%; height:1.2rem; }' . "\n"
       . '    .admin-progress--lg { height:1.5rem; }' . "\n"
       . '    .admin-panel { border:1px solid var(--admin-border); border-radius:8px; padding:1rem; margin-bottom:1.5rem; }' . "\n"
       . '    .admin-panel--success { background:var(--admin-published-bg); border-color:var(--admin-published-border); color:var(--admin-published-text); }' . "\n"
       . '    .admin-panel--warning { background:var(--admin-pending-bg); border-color:var(--admin-pending-border); color:var(--admin-pending-text); }' . "\n"
       . '    .admin-panel--info { background:#e3f2fd; border-color:#1565c0; }' . "\n"
       . '    .admin-panel--danger { background:var(--admin-danger-bg); border-color:var(--admin-danger-border); color:var(--admin-danger-text); }' . "\n"
       . '    .admin-panel--spaced { margin-top:2rem; }' . "\n"
       . '    .admin-panel__heading { margin-top:0; }' . "\n"
       . '    .admin-panel__list { margin:0; }' . "\n"
       . '    .admin-panel__footer { margin-bottom:0; }' . "\n"
       . '    .admin-section-block { margin-top:2rem; border-top:1px solid var(--admin-border); padding-top:1.5rem; }' . "\n"
       . '    .admin-section-block__heading { margin-top:0; }' . "\n"
       . '    .theme-catalog, .theme-package-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(18rem,1fr)); gap:1rem; }' . "\n"
       . '    .theme-catalog { margin-top:1rem; }' . "\n"
       . '    .theme-card, .theme-package-card { border:1px solid var(--admin-border); border-radius:12px; background:var(--admin-surface); }' . "\n"
       . '    .theme-card { display:grid; gap:.85rem; height:100%; padding:1rem; }' . "\n"
       . '    .theme-card--selected { border-color:var(--admin-link); box-shadow:0 0 0 3px color-mix(in srgb, var(--admin-link) 16%, transparent); background:var(--admin-input-bg); }' . "\n"
       . '    .theme-card__heading { display:flex; align-items:flex-start; gap:.75rem; }' . "\n"
       . '    .theme-card__heading input[type=radio] { margin-top:.25rem; }' . "\n"
       . '    .theme-card__title { display:inline; margin-top:0; font-weight:700; }' . "\n"
       . '    .theme-card__preview { display:block; overflow:hidden; border:1px solid var(--admin-border); border-radius:10px; background:var(--admin-input-bg); aspect-ratio:16 / 10; }' . "\n"
       . '    .theme-card__preview img { display:block; width:100%; height:100%; object-fit:cover; }' . "\n"
       . '    .theme-card__placeholder { display:grid; place-items:center; width:100%; height:100%; padding:1rem; color:var(--admin-text-meta); text-align:center; background:linear-gradient(135deg, color-mix(in srgb, var(--admin-link) 8%, transparent), color-mix(in srgb, var(--admin-pending-text) 8%, transparent)), var(--admin-input-bg); font-weight:600; }' . "\n"
       . '    .theme-card__swatches { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.75rem; }' . "\n"
       . '    .theme-card__swatch { width:1.35rem; height:1.35rem; flex:0 0 auto; }' . "\n"
       . '    .theme-card__meta { margin:0; color:var(--admin-text-meta); }' . "\n"
       . '    .theme-card__summary, .theme-card__description, .theme-card__status { margin:.6rem 0 0; color:var(--admin-text); }' . "\n"
       . '    .theme-card__status strong { display:inline-block; margin-right:.35rem; }' . "\n"
       . '    .theme-card__hint { margin:.5rem 0 0; color:var(--admin-text-meta); }' . "\n"
       . '    .theme-setting-row { margin-top:1rem; }' . "\n"
       . '    .theme-setting-color-row { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }' . "\n"
       . '    .theme-package-card { padding:1rem; }' . "\n"
       . '    .integrity-panel { border:1px solid var(--admin-border); border-radius:8px; padding:1rem; margin-bottom:1rem; }' . "\n"
       . '    .integrity-panel--success { background:var(--admin-published-bg); border-color:var(--admin-published-border); color:var(--admin-published-text); }' . "\n"
       . '    .integrity-panel--warning { background:var(--admin-pending-bg); border-color:var(--admin-pending-border); color:var(--admin-pending-text); }' . "\n"
       . '    .integrity-panel--danger { background:var(--admin-danger-bg); border-color:var(--admin-danger-border); color:var(--admin-danger-text); }' . "\n"
       . '    .integrity-panel--info { background:var(--admin-neutral-bg); border-color:var(--admin-neutral-border); color:var(--admin-neutral-text); }' . "\n"
       . '    .integrity-panel--spaced { margin-top:2rem; }' . "\n"
       . '    .integrity-actions { margin:1rem 0; }' . "\n"
       . '    .integrity-result { margin-top:1.5rem; }' . "\n"
       . '    .integrity-copy--flush { margin:0; }' . "\n"
       . '    .integrity-copy--footer { margin-bottom:0; }' . "\n"
       . '    .integrity-copy--top { margin-top:1rem; }' . "\n"
       . '    .integrity-heading--flush { margin-top:0; }' . "\n"
       . '    .integrity-heading--danger { color:var(--admin-danger-text); }' . "\n"
       . '    .integrity-heading--modified { color:var(--admin-pending-text); }' . "\n"
       . '    .integrity-heading--muted { color:var(--admin-text-muted); }' . "\n"
       . '    .form-submission-grid { display:grid; grid-template-columns:repeat(2,minmax(14rem,1fr)); gap:1rem; align-items:start; }' . "\n"
       . '    .form-submission-field { margin-bottom:.75rem; }' . "\n"
       . '    .form-submission-field--top { margin-top:1rem; }' . "\n"
       . '    .form-submission-actions { margin-top:1rem; }' . "\n"
       . '    .form-submission-actions--compact { margin-top:.75rem; }' . "\n"
       . '    .form-submission-help--spaced { margin-bottom:.75rem; }' . "\n"
       . '    .form-submission-help--top { margin-top:.75rem; }' . "\n"
       . '    .form-submission-control { width:100%; }' . "\n"
       . '    .form-submission-control--sm { max-width:32rem; }' . "\n"
       . '    .form-submission-control--md { max-width:52rem; }' . "\n"
       . '    .form-submission-control--lg { max-width:60rem; }' . "\n"
       . '    .form-submission-history { padding-left:1.25rem; }' . "\n"
       . '    .form-submission-history__item { margin-bottom:.75rem; }' . "\n"
       . '    .form-submission-delete-form { margin-top:1rem; }' . "\n"
       . '    .admin-check-row { margin:.3rem 0; }' . "\n"
       . '    .admin-check-row--separated { margin:.5rem 0; padding-top:.3rem; border-top:1px solid var(--admin-border); }' . "\n"
       . '    .admin-profile-totp-panel { margin-top:1rem; padding:1rem; background:var(--admin-surface); border:1px solid var(--admin-border); border-radius:8px; }' . "\n"
       . '    .admin-totp-qr { display:block; margin:.5rem 0; }' . "\n"
       . '    .admin-totp-code-input { width:10rem; font-size:1.2rem; text-align:center; letter-spacing:.2rem; }' . "\n"
       . '    .admin-code-break { word-break:break-all; }' . "\n"
       . '    .admin-inline-meta { color:var(--admin-text-meta); font-size:.85rem; }' . "\n"
       . '    .admin-input-wide { max-width:600px; }' . "\n"
       . '    .field-help--flush { margin-top:0; }' . "\n"
       . '    .table-note { margin-top:.75rem; color:var(--admin-field-help); }' . "\n"
       . '    .text-pending { color:var(--admin-pending-text); }' . "\n"
       . '    .error { color: var(--admin-error); }' . "\n"
       . '    .success { color: var(--admin-success); }' . "\n"
       . '    label { display: block; margin-top: 1rem; font-weight: bold; }' . "\n"
       . '    input[type=text], input[type=email], input[type=password], input[type=number], textarea, select {' . "\n"
       . '      width: 100%; padding: .35rem; margin-top: .2rem; background: var(--admin-input-bg); color: var(--admin-text); border: 1px solid var(--admin-input-border); }' . "\n"
       . '    textarea { min-height: 200px; }' . "\n"
       . '    .actions form { display: inline; }' . "\n"
       . '    .table-row--scheduled { background:var(--admin-scheduled-bg); }' . "\n"
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
       . '    .table-cell--detail { max-width:400px; word-break:break-word; font-size:.88rem; }' . "\n"
       . '    .table-cell--prewrap { max-width:36rem; white-space:pre-wrap; }' . "\n"
       . '    .revision-diff-cell { max-width:600px; word-break:break-word; }' . "\n"
       . '    .revision-diff { white-space:pre-wrap; font-size:.88rem; line-height:1.5; }' . "\n"
       . '    .revision-diff--details { margin-top:.5rem; }' . "\n"
       . '    .revision-diff__delete { background:#fdd; text-decoration:line-through; }' . "\n"
       . '    .revision-diff__insert { background:#dfd; text-decoration:none; }' . "\n"
       . '    .admin-thumb { width:80px; height:60px; object-fit:cover; }' . "\n"
       . '    .inline-badge { display:inline-block; margin-left:.4rem; padding:.1rem .45rem; border-radius:999px; font-size:.78rem; font-weight:600; }' . "\n"
       . '    .inline-badge--standalone { margin:.35rem 0 0; }' . "\n"
       . '    .inline-badge--warning { background:#fff1c2; color:#7a4a00; }' . "\n"
       . '    .inline-badge--info { background:#eef4fb; color:#1b4d7a; }' . "\n"
       . '    .table-list-compact { margin:0; padding-left:1rem; }' . "\n"
       . '    .field-help { display:block; margin:.35rem 0 0; color:var(--admin-field-help); font-size:.92rem; line-height:1.45; font-weight:normal; }' . "\n"
       . '    .field-help--indented { margin-left:1.4rem; }' . "\n"
       . '    .field-error { color:var(--admin-error); font-weight:700; }' . "\n"
       . '    .field-help code { font-size:.95em; }' . "\n"
       . '    input[aria-invalid="true"], textarea[aria-invalid="true"], select[aria-invalid="true"] { border:2px solid var(--admin-invalid-border); }' . "\n"
       . '    .sr-only, .visually-hidden { position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0; }' . "\n"
       . '    :focus-visible { outline: 3px solid var(--admin-focus); outline-offset: 2px; }' . "\n"
       . '    nav a:focus-visible { outline-color: var(--admin-focus-nav); }' . "\n"
       . '    .skip-link { position:absolute;left:-999px;top:auto;width:1px;height:1px;overflow:hidden;z-index:999; }' . "\n"
       . '    .skip-link:focus { position:fixed;top:0;left:0;width:auto;height:auto;padding:.75rem 1.5rem;background:var(--admin-focus);color:#fff;font-size:1rem;text-decoration:none;z-index:9999; }' . "\n"
       . '    .admin-nav-user { font-size:.8rem; color:var(--admin-nav-summary); margin:0 0 .75rem; }' . "\n"
       . '    .admin-nav-bottom { margin-top:.8rem; }' . "\n"
       . '    .autosave-banner { background:var(--admin-pending-bg); border:1px solid var(--admin-pending-border); color:var(--admin-pending-text); padding:.7rem 1rem; border-radius:6px; margin-bottom:1rem; display:flex; align-items:center; gap:.7rem; flex-wrap:wrap; }' . "\n"
       . '    .autosave-banner__button { padding:.3rem .8rem; cursor:pointer; }' . "\n"
       . '    .editor-count { display:block; margin-top:.3rem; color:var(--admin-text-muted); font-size:.8rem; }' . "\n"
       . '    .admin-rich-editor-frame { background:var(--admin-input-bg); border:1px solid var(--admin-input-border); margin-top:.2rem; }' . "\n"
       . '    .admin-rich-editor-xl { min-height:350px; }' . "\n"
       . '    .seo-preview { margin-top:.8rem; padding:.8rem; background:var(--admin-surface); border:1px solid var(--admin-border); border-radius:8px; font-family:Arial,sans-serif; max-width:600px; }' . "\n"
       . '    .seo-preview__title { font-size:1.1rem; color:var(--admin-link); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }' . "\n"
       . '    .seo-preview__url { font-size:.8rem; color:var(--admin-success); margin:.2rem 0; }' . "\n"
       . '    .seo-preview__desc { font-size:.85rem; color:var(--admin-text-muted); display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }' . "\n"
       . '    .seo-preview__counts { color:var(--admin-text-muted); margin-top:.4rem; display:block; }' . "\n"
       . '    .admin-footer { text-align:center; padding:.5rem; font-size:.75rem; color:var(--admin-footer-text); }' . "\n"
       . '    @media (max-width: 720px) { .admin-stat-bar { grid-template-columns:1fr; gap:.25rem; } .admin-stat-bar__value { text-align:left; } }' . "\n"
       . '    @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration:0.01ms!important;animation-iteration-count:1!important;transition-duration:0.01ms!important; } }' . "\n"
       . '  </style>' . "\n"
       . '</head>' . "\n"
       . '<body>' . "\n"
       . '<a href="#obsah" class="skip-link">Přeskočit na obsah</a>' . "\n"
       . '<nav aria-label="Administrace">' . "\n"
       . '  <h2>' . $siteName . '</h2>' . "\n";

    $userName = h(currentUserDisplayName());
    if ($userName !== '') {
        echo '  <p class="admin-nav-user"><span aria-hidden="true">&#9786;</span> ' . $userName . '</p>' . "\n";
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

    echo '  <ul class="admin-nav-bottom">' . "\n";
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
       . 'if(b&&b.tagName!=="FORM"&&!confirm(b.dataset.confirm)){e.preventDefault();e.stopPropagation();return;}'
       . 'var once=e.target.closest("[data-submit-once]");'
       . 'if(once){document.querySelectorAll("[data-submit-once-clicked]").forEach(function(el){el.removeAttribute("data-submit-once-clicked");});once.setAttribute("data-submit-once-clicked","1");}'
       . '});document.addEventListener("submit",function(e){'
       . 'var f=e.target.closest("form[data-confirm]");'
       . 'if(f&&!confirm(f.dataset.confirm)){e.preventDefault();e.stopPropagation();return;}'
       . 'var s=e.submitter&&e.submitter.matches&&e.submitter.matches("[data-submit-once]")?e.submitter:e.target.querySelector("[data-submit-once-clicked]");'
       . 'if(s){var t=s.getAttribute("data-submit-once")||s.textContent;setTimeout(function(){s.disabled=true;s.textContent=t;},0);}'
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
       . 'banner.className=\'autosave-banner\';'
       . 'banner.innerHTML=\'<span>Nalezen neuložený koncept z \'+new Date(saved._ts).toLocaleString(\'cs-CZ\')+\'.</span>\''
       . '+\'<button type="button" class="autosave-banner__button">Obnovit</button>\''
       . '+\'<button type="button" class="autosave-banner__button">Zahodit</button>\';'
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
       . 'info.className=\'editor-count\';'
       . 'info.setAttribute(\'data-editor-count\',\'content\');'
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
       . 'box.className="seo-preview";'
       . 'box.innerHTML=\'<div class="seo-preview__title" id="seo-prev-title"></div>\''
       . '+\'<div class="seo-preview__url" id="seo-prev-url"></div>\''
       . '+\'<div class="seo-preview__desc" id="seo-prev-desc"></div>\''
       . '+\'<small class="seo-preview__counts" id="seo-prev-counts"></small>\';'
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
       . '<footer class="admin-footer">'
       . 'Kora CMS ' . $version
       . '</footer>'
       . '</body></html>';
}
