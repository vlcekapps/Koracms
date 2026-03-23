<?php
require_once __DIR__ . '/../db.php';

function adminHeader(string $pageTitle): void
{
    $siteName = h(getSetting('site_name', 'Kora CMS'));
    $b = BASE_URL;

    echo '<!DOCTYPE html>' . "\n"
       . '<html lang="cs">' . "\n"
       . '<head>' . "\n"
       . '  <meta charset="utf-8">' . "\n"
       . '  <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
       . '  <title>' . $pageTitle . ' – ' . $siteName . ' Admin</title>' . "\n"
       . '  <style>' . "\n"
       . '    *, *::before, *::after { box-sizing: border-box; }' . "\n"
       . '    body { font-family: system-ui, sans-serif; margin: 0; display: flex; min-height: 100vh; }' . "\n"
       . '    nav { background: #222; color: #fff; width: 210px; flex-shrink: 0; padding: 1rem; }' . "\n"
       . '    nav h2 { font-size: 1rem; margin: 0 0 .25rem; color: #ccc; }' . "\n"
       . '    nav ul { list-style: none; margin: 0; padding: 0; }' . "\n"
       . '    nav li { margin: 0; }' . "\n"
       . '    nav a, nav summary { display: block; min-height: 2.25rem; line-height: 1.35; border-radius: 4px; }' . "\n"
       . '    nav a { color: #ddd; text-decoration: none; font-size: .9rem; padding: .45rem .35rem; }' . "\n"
       . '    nav summary { color: #bbb; font-size: .85rem; }' . "\n"
       . '    nav a:hover, nav a:focus, nav summary:hover, nav summary:focus { background: rgba(255,255,255,.08); color: #fff; text-decoration: none; }' . "\n"
       . '    main { flex: 1; padding: 1.5rem 2rem; }' . "\n"
       . '    h1 { margin-top: 0; }' . "\n"
       . '    table { border-collapse: collapse; width: 100%; }' . "\n"
       . '    th, td { border: 1px solid #ccc; padding: .4rem .6rem; text-align: left; }' . "\n"
       . '    th { background: #f0f0f0; }' . "\n"
       . '    .btn { padding: .45rem .9rem; cursor: pointer; min-height: 2rem; }' . "\n"
       . '    .btn-danger { background: #c00; color: #fff; border: none; }' . "\n"
       . '    .error { color: #c00; }' . "\n"
       . '    .success { color: #060; }' . "\n"
       . '    label { display: block; margin-top: 1rem; font-weight: bold; }' . "\n"
       . '    input[type=text], input[type=email], input[type=password], input[type=number], textarea, select {'
       . '      width: 100%; padding: .35rem; margin-top: .2rem; }' . "\n"
       . '    textarea { min-height: 200px; }' . "\n"
       . '    .actions form { display: inline; }' . "\n"
       . '    .sr-only { position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0; }' . "\n"
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

    $topItems = [
        ['url' => $b . '/admin/index.php',   'label' => 'Přehled'],
        ['url' => $b . '/admin/profile.php', 'label' => 'Můj profil'],
    ];

    $moduleItems = [
        ['url' => $b . '/admin/news.php',          'label' => 'Novinky',          'module' => 'news'],
        ['url' => $b . '/admin/chat.php',          'label' => 'Chat',             'module' => 'chat'],
        ['url' => $b . '/admin/contact.php',       'label' => 'Kontakt',          'module' => 'contact'],
        ['url' => $b . '/admin/gallery_albums.php','label' => 'Galerie',          'module' => 'gallery'],
        ['url' => $b . '/admin/events.php',        'label' => 'Události',         'module' => 'events'],
        ['url' => $b . '/admin/polls.php',         'label' => 'Ankety',           'module' => 'polls'],
        ['url' => $b . '/admin/podcast.php',       'label' => 'Podcasty',         'module' => 'podcast'],
        ['url' => $b . '/admin/places.php',        'label' => 'Zajímavá místa',   'module' => 'places'],
        ['url' => $b . '/admin/newsletter.php',    'label' => 'Newsletter',       'module' => 'newsletter'],
        ['url' => $b . '/admin/food.php',          'label' => 'Jídelní lístek',   'module' => 'food'],
        ['url' => $b . '/admin/pages.php',         'label' => 'Stránky'],
        ['url' => $b . '/admin/import.php',        'label' => 'Export / Import'],
    ];

    $bottomItems = [];
    $bottomItems[] = ['url' => $b . '/index.php',        'label' => '<span aria-hidden="true">←</span> Web'];
    $bottomItems[] = ['url' => $b . '/admin/logout.php', 'label' => 'Odhlásit se'];

    $renderItem = function(array $item): string {
        $style = isset($item['style']) ? ' style="' . $item['style'] . '"' : '';
        return '    <li><a href="' . $item['url'] . '"' . $style . '>' . $item['label'] . '</a></li>' . "\n";
    };

    echo '  <ul>' . "\n";
    foreach ($topItems as $item) { echo $renderItem($item); }
    echo '  </ul>' . "\n"
       . '  <details style="margin:.4rem 0">' . "\n"
       . '    <summary style="cursor:pointer;color:#bbb;font-size:.85rem;padding:.45rem .35rem;border-radius:4px;list-style:none">'
       . '<span aria-hidden="true">&#9776;</span> Moduly</summary>' . "\n"
       . '    <ul style="margin:.3rem 0 0;padding:0;list-style:none">' . "\n";
    if (isModuleEnabled('blog')) {
    echo '      <li>' . "\n"
       . '        <details role="group" aria-label="Blog">' . "\n"
       . '          <summary style="cursor:pointer;color:#ddd;font-size:.9rem;padding:.45rem .35rem;border-radius:4px;list-style:none;user-select:none">Blog</summary>' . "\n"
       . '          <ul style="margin:.2rem 0 0;padding:0;list-style:none">' . "\n"
       . '            <li><a href="' . $b . '/admin/blog.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd">Články</a></li>' . "\n"
       . '            <li><a href="' . $b . '/admin/blog_cats.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd"><span aria-hidden="true">↳</span> Kategorie</a></li>' . "\n"
       . '            <li><a href="' . $b . '/admin/blog_tags.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd"><span aria-hidden="true">↳</span> Tagy</a></li>' . "\n"
       . '            <li><a href="' . $b . '/admin/comments.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd"><span aria-hidden="true">↳</span> Komentáře</a></li>' . "\n"
       . '          </ul>' . "\n"
       . '        </details>' . "\n"
       . '      </li>' . "\n";
    }
    if (isModuleEnabled('downloads')) {
    echo '      <li>' . "\n"
       . '        <details role="group" aria-label="Ke stažení">' . "\n"
       . '          <summary style="cursor:pointer;color:#ddd;font-size:.9rem;padding:.45rem .35rem;border-radius:4px;list-style:none;user-select:none">Ke stažení</summary>' . "\n"
       . '          <ul style="margin:.2rem 0 0;padding:0;list-style:none">' . "\n"
       . '            <li><a href="' . $b . '/admin/downloads.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd">Soubory</a></li>' . "\n"
       . '            <li><a href="' . $b . '/admin/dl_cats.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd"><span aria-hidden="true">↳</span> Kategorie</a></li>' . "\n"
       . '          </ul>' . "\n"
       . '        </details>' . "\n"
       . '      </li>' . "\n";
    }
    if (isModuleEnabled('faq')) {
    echo '      <li>' . "\n"
       . '        <details role="group" aria-label="FAQ">' . "\n"
       . '          <summary style="cursor:pointer;color:#ddd;font-size:.9rem;padding:.45rem .35rem;border-radius:4px;list-style:none;user-select:none">FAQ</summary>' . "\n"
       . '          <ul style="margin:.2rem 0 0;padding:0;list-style:none">' . "\n"
       . '            <li><a href="' . $b . '/admin/faq.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd">Otázky</a></li>' . "\n"
       . '            <li><a href="' . $b . '/admin/faq_cats.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd"><span aria-hidden="true">↳</span> Kategorie</a></li>' . "\n"
       . '          </ul>' . "\n"
       . '        </details>' . "\n"
       . '      </li>' . "\n";
    }
    if (isModuleEnabled('board')) {
    echo '      <li>' . "\n"
       . '        <details role="group" aria-label="Úřední deska">' . "\n"
       . '          <summary style="cursor:pointer;color:#ddd;font-size:.9rem;padding:.45rem .35rem;border-radius:4px;list-style:none;user-select:none">Úřední deska</summary>' . "\n"
       . '          <ul style="margin:.2rem 0 0;padding:0;list-style:none">' . "\n"
       . '            <li><a href="' . $b . '/admin/board.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd">Příspěvky</a></li>' . "\n"
       . '            <li><a href="' . $b . '/admin/board_cats.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd"><span aria-hidden="true">↳</span> Kategorie</a></li>' . "\n"
       . '          </ul>' . "\n"
       . '        </details>' . "\n"
       . '      </li>' . "\n";
    }
    if (isModuleEnabled('reservations')) {
    echo '      <li>' . "\n"
       . '        <details role="group" aria-label="Rezervace">' . "\n"
       . '          <summary style="cursor:pointer;color:#ddd;font-size:.9rem;padding:.45rem .35rem;border-radius:4px;list-style:none;user-select:none">Rezervace</summary>' . "\n"
       . '          <ul style="margin:.2rem 0 0;padding:0;list-style:none">' . "\n"
       . '            <li><a href="' . $b . '/admin/res_resources.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd">Zdroje</a></li>' . "\n"
       . '            <li><a href="' . $b . '/admin/res_categories.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd"><span aria-hidden="true">↳</span> Kategorie</a></li>' . "\n"
       . '            <li><a href="' . $b . '/admin/res_bookings.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd"><span aria-hidden="true">↳</span> Rezervace</a></li>' . "\n"
       . '            <li><a href="' . $b . '/admin/res_locations.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd"><span aria-hidden="true">↳</span> Místa</a></li>' . "\n"
       . '          </ul>' . "\n"
       . '        </details>' . "\n"
       . '      </li>' . "\n";
    }
    echo '      <li role="group" aria-label="Ostatní moduly"><ul style="margin:0;padding:0;list-style:none">' . "\n";
    foreach ($moduleItems as $item) {
        if (isset($item['module']) && !isModuleEnabled($item['module'])) continue;
        echo $renderItem($item);
    }
    echo '      </ul></li>' . "\n";
    echo '    </ul>' . "\n"
       . '  </details>' . "\n"
       . '  <details style="margin:.4rem 0">' . "\n"
       . '    <summary style="cursor:pointer;color:#bbb;font-size:.85rem;padding:.45rem .35rem;border-radius:4px;list-style:none">'
       . '<span aria-hidden="true">&#9881;</span> Nastavení</summary>' . "\n"
       . '    <ul style="margin:.3rem 0 0;padding:0;list-style:none">' . "\n"
       . '      <li><a href="' . $b . '/admin/settings.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd">Základní nastavení</a></li>' . "\n"
       . '      <li><a href="' . $b . '/admin/settings_modules.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd">Moduly</a></li>' . "\n"
       . '      <li><a href="' . $b . '/admin/settings_display.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd">Nastavení zobrazení</a></li>' . "\n";
    if (isModuleEnabled('statistics')) {
    echo '      <li><a href="' . $b . '/admin/statistics.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd">Statistiky</a></li>' . "\n";
    }
    if (isSuperAdmin()) {
    echo '      <li><a href="' . $b . '/admin/users.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd">Správa uživatelů</a></li>' . "\n";
    }
    echo '    </ul>' . "\n"
       . '  </details>' . "\n"
       . '  <ul style="margin-top:.4rem">' . "\n";
    foreach ($bottomItems as $item) { echo $renderItem($item); }
    echo '  </ul>' . "\n"
       . '</nav>' . "\n"
       . '<main id="obsah">' . "\n"
       . '  <div id="a11y-live" role="status" aria-live="polite" aria-atomic="true" class="sr-only"></div>' . "\n"
       . '  <h1>' . $pageTitle . '</h1>' . "\n";
}

function adminFooter(): void
{
    $version = KORA_VERSION;
    echo '<script>document.addEventListener("DOMContentLoaded",function(){'
       . 'var l=document.getElementById("a11y-live");if(!l)return;'
       . 'var m=document.querySelector(\'[role="status"]:not(#a11y-live),[role="alert"]\');'
       . 'if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);m.removeAttribute("role");}'
       . '});</script>'
       . '</main>'
       . '<footer style="text-align:center;padding:.5rem;font-size:.75rem;color:#666">'
       . 'Kora CMS ' . $version
       . '</footer>'
       . '</body></html>';
}
