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

function adminRenderContentLockRefreshScript(string $entityType, ?int $entityId): void
{
    if ($entityId === null || $entityId <= 0) {
        return;
    }

    $nonce = cspNonce();
    $csrfTokenJson = json_encode(csrfToken(), JSON_UNESCAPED_SLASHES);
    $entityTypeJson = json_encode($entityType, JSON_UNESCAPED_SLASHES);
    $entityIdJson = json_encode((string)$entityId, JSON_UNESCAPED_SLASHES);
    $endpointJson = json_encode(BASE_URL . '/admin/content_lock_refresh.php', JSON_UNESCAPED_SLASHES);
    if (!is_string($csrfTokenJson) || !is_string($entityTypeJson) || !is_string($entityIdJson) || !is_string($endpointJson)) {
        return;
    }

    echo '<script nonce="' . h($nonce) . '">'
       . '(function(){'
       . 'var csrfToken=' . $csrfTokenJson . ';'
       . 'function syncCsrfToken(token){'
       . 'if(typeof token!=="string"||token==="")return;'
       . 'csrfToken=token;'
       . 'document.querySelectorAll(\'input[name="csrf_token"]\').forEach(function(input){input.value=token;});'
       . '}'
       . 'var lockInterval=setInterval(function(){'
       . 'var fd=new FormData();'
       . 'fd.append("csrf_token",csrfToken);'
       . 'fd.append("entity_type",' . $entityTypeJson . ');'
       . 'fd.append("entity_id",' . $entityIdJson . ');'
       . 'fetch(' . $endpointJson . ',{method:"POST",body:fd,credentials:"same-origin"})'
       . '.then(function(response){return response.ok?response.json():null;})'
       . '.then(function(payload){if(payload&&payload.csrf_token)syncCsrfToken(payload.csrf_token);})'
       . '.catch(function(){});'
       . '},60000);'
       . 'window.addEventListener("beforeunload",function(){clearInterval(lockInterval);});'
       . '})();'
       . '</script>';
}

function adminNavBadge(int $count, string $label): string
{
    if ($count <= 0) {
        return '';
    }

    return ' <span class="badge"><span aria-hidden="true">' . $count . '</span><span class="sr-only">'
        . h($label) . '</span></span>';
}

/**
 * @param list<array<string, mixed>> $items
 * @param array<string, bool> $knownUrls
 */
function adminRegisterNavUrls(array $items, array &$knownUrls): void
{
    foreach ($items as $item) {
        if (($item['type'] ?? 'link') === 'details') {
            $children = $item['items'] ?? [];
            if (is_array($children)) {
                adminRegisterNavUrls($children, $knownUrls);
            }
            continue;
        }

        $url = (string)($item['url'] ?? '');
        if ($url !== '') {
            $knownUrls[$url] = true;
        }
    }
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
        return '    <li><a href="' . h((string)$item['url']) . '">' . $item['label'] . '</a></li>' . "\n";
    };

    $renderNestedItem = static function (array $item): string {
        return '          <li><a class="nav-link--nested" href="' . h((string)$item['url']) . '">' . $item['label'] . '</a></li>' . "\n";
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
                . adminNavBadge($pendingReviewTotal, $pendingReviewTotal . ' čekajících položek'),
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
                    ['url' => $baseUrl . '/admin/download_series.php', 'label' => 'Série a verze'],
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
                . adminNavBadge($pendingComments, $pendingComments . ' ' . $pendingCommentsLabel),
        ];
    }
    if ($canManageMessages && isModuleEnabled('contact')) {
        $communicationItems[] = [
            'url' => $baseUrl . '/admin/contact.php',
            'label' => 'Kontakt'
                . adminNavBadge($unreadContactMessages, $unreadContactMessages . ' nových kontaktních zpráv'),
        ];
    }
    if ($canManageMessages && isModuleEnabled('chat')) {
        $communicationItems[] = [
            'url' => $baseUrl . '/admin/chat.php',
            'label' => 'Chat'
                . adminNavBadge($unreadChatMessages, $unreadChatMessages . ' nových chat zpráv'),
        ];
        $communicationItems[] = [
            'url' => $baseUrl . '/admin/chat_topics.php',
            'label' => 'Témata chatu',
        ];
    }
    if ($canManageNewsletter && isModuleEnabled('newsletter')) {
        $communicationItems[] = [
            'url' => $baseUrl . '/admin/newsletter.php',
            'label' => 'Newsletter'
                . adminNavBadge($pendingNewsletterSubscribers, $pendingNewsletterSubscribers . ' odběratelů čeká na potvrzení'),
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

    $knownNavUrls = [];
    foreach ([$topItems, $contentItems, $communicationItems, $reservationItems, $settingsItems] as $navItems) {
        adminRegisterNavUrls($navItems, $knownNavUrls);
    }

    $moduleFallbackItems = [];
    foreach (array_keys(coreModuleDefinitions()) as $moduleKey) {
        if (!isModuleEnabled((string)$moduleKey) || !currentUserHasCapability(moduleAdminCapability((string)$moduleKey))) {
            continue;
        }

        $adminPath = modulePrimaryAdminPath((string)$moduleKey);
        if ($adminPath === '') {
            continue;
        }

        $url = $baseUrl . $adminPath;
        if (isset($knownNavUrls[$url])) {
            continue;
        }

        $moduleFallbackItems[] = [
            'url' => $url,
            'label' => h(moduleAdminLabel((string)$moduleKey)),
        ];
        $knownNavUrls[$url] = true;
    }

    $bottomItems = [
        ['url' => $baseUrl . '/index.php', 'label' => '<span aria-hidden="true">←</span> Web'],
        ['url' => $baseUrl . '/admin/logout.php', 'label' => 'Odhlásit se'],
    ];
    $adminStylesheetPath = __DIR__ . '/assets/layout.css';
    $adminStylesheetVersion = (string)((int)@filemtime($adminStylesheetPath) ?: KORA_VERSION);
    $adminStylesheetUrl = BASE_URL . '/admin/assets/layout.css?v=' . rawurlencode($adminStylesheetVersion);

    echo '<!DOCTYPE html>' . "\n"
       . '<html lang="cs">' . "\n"
       . '<head>' . "\n"
       . '  <meta charset="utf-8">' . "\n"
       . '  <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
       . '  <title>' . $pageTitle . ' – ' . $siteName . ' Admin</title>' . "\n"
       . '  <link rel="stylesheet" href="' . h($adminStylesheetUrl) . '">' . "\n"
       . '</head>' . "\n"
       . '<body>' . "\n"
       . '<a href="#obsah" class="skip-link">Přeskočit na obsah</a>' . "\n"
       . '<nav aria-labelledby="admin-nav-heading">' . "\n"
       . '  <h2 id="admin-nav-heading">Administrace</h2>' . "\n"
       . '  <p class="admin-nav-site">' . $siteName . '</p>' . "\n";

    $userName = h(currentUserDisplayName());
    if ($userName !== '') {
        echo '  <p class="admin-nav-user"><span aria-hidden="true">&#9786;</span> ' . $userName . '</p>' . "\n";
    }

    echo '  <form action="' . h(BASE_URL . '/admin/command.php') . '" method="get" class="admin-command-nav-search" role="search" aria-labelledby="admin-command-nav-heading">' . "\n"
       . '    <h3 id="admin-command-nav-heading">Hledání v administraci</h3>' . "\n"
       . '    <label for="admin-command-nav-q" class="sr-only">Hledat v administraci</label>' . "\n"
       . '    <input type="search" id="admin-command-nav-q" name="q" placeholder="Hledat v administraci" autocomplete="off">' . "\n"
       . '    <div class="admin-command-nav-actions">' . "\n"
       . '      <button type="submit" class="btn btn-muted">Hledat</button>' . "\n"
       . '      <button type="button" class="btn btn-muted" id="admin-command-open" aria-haspopup="dialog" aria-controls="admin-command-dialog" aria-expanded="false">Paleta <span class="admin-command-shortcut" aria-hidden="true">Ctrl+K</span></button>' . "\n"
       . '    </div>' . "\n"
       . '  </form>' . "\n";

    echo '  <ul>' . "\n";
    foreach ($topItems as $item) {
        echo $renderItem($item);
    }
    echo '  </ul>' . "\n";

    echo $renderNavSection('nav-content', 'Obsah webu', $contentItems);
    echo $renderNavSection('nav-communication', 'Komunikace', $communicationItems);
    echo $renderNavSection('nav-reservations', 'Rezervace', $reservationItems);
    echo $renderNavSection('nav-modules', 'Další moduly', $moduleFallbackItems);
    echo $renderNavSection('nav-settings', 'Nastavení a správa', $settingsItems);

    echo '  <ul class="admin-nav-bottom">' . "\n";
    foreach ($bottomItems as $item) {
        echo $renderItem($item);
    }
    echo '  </ul>' . "\n"
       . '</nav>' . "\n"
       . '<div id="admin-command-overlay" class="admin-command-overlay" hidden></div>' . "\n"
       . '<section id="admin-command-dialog" class="admin-command-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-command-dialog-title" aria-describedby="admin-command-dialog-description" data-search-url="' . h(BASE_URL . '/admin/command_search.php') . '" data-shortcut-url="' . h(BASE_URL . '/admin/shortcut.php') . '" data-csrf-token="' . h(csrfToken()) . '" hidden>' . "\n"
       . '  <div class="admin-command-dialog__header">' . "\n"
       . '    <div>' . "\n"
       . '      <h2 id="admin-command-dialog-title" class="admin-command-dialog__title">Command centrum</h2>' . "\n"
       . '      <p id="admin-command-dialog-description" class="admin-command-dialog__description">Hledejte obrazovky administrace, rychlé akce a obsah k úpravě.</p>' . "\n"
       . '    </div>' . "\n"
       . '    <button type="button" class="btn btn-muted" id="admin-command-close">Zavřít<span class="sr-only"> command centrum</span></button>' . "\n"
       . '  </div>' . "\n"
       . '  <form action="' . h(BASE_URL . '/admin/command.php') . '" method="get" class="admin-command-dialog__search" role="search" aria-labelledby="admin-command-dialog-search-heading">' . "\n"
       . '    <h3 id="admin-command-dialog-search-heading" class="sr-only">Hledat v command centru</h3>' . "\n"
       . '    <label for="admin-command-dialog-q">Hledaný výraz</label>' . "\n"
       . '    <input type="search" id="admin-command-dialog-q" name="q" autocomplete="off" placeholder="Například článek, média nebo nastavení">' . "\n"
       . '    <button type="submit" class="btn">Otevřít výsledky</button>' . "\n"
       . '  </form>' . "\n"
       . '  <p id="admin-command-status" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></p>' . "\n"
       . '  <ul id="admin-command-results" class="admin-command-dialog__results" aria-labelledby="admin-command-dialog-title"></ul>' . "\n"
       . '</section>' . "\n"
       . '<main id="obsah">' . "\n"
       . '  <div id="a11y-live" role="status" aria-live="polite" aria-atomic="true" class="sr-only"></div>' . "\n"
       . '  <h1>' . $pageTitle . '</h1>' . "\n";
}

function adminFooter(): void
{
    $version = KORA_VERSION;
    $nonce = cspNonce();
    echo '<script src="' . h(BASE_URL . '/admin/assets/command.js?v=' . rawurlencode(KORA_VERSION)) . '" nonce="' . $nonce . '"></script>'
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
