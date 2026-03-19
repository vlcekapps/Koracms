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
       . '    nav li { margin: .4rem 0; }' . "\n"
       . '    nav a { color: #ddd; text-decoration: none; font-size: .9rem; }' . "\n"
       . '    nav a:hover, nav a:focus { color: #fff; text-decoration: underline; }' . "\n"
       . '    main { flex: 1; padding: 1.5rem 2rem; }' . "\n"
       . '    h1 { margin-top: 0; }' . "\n"
       . '    table { border-collapse: collapse; width: 100%; }' . "\n"
       . '    th, td { border: 1px solid #ccc; padding: .4rem .6rem; text-align: left; }' . "\n"
       . '    th { background: #f0f0f0; }' . "\n"
       . '    .btn { padding: .3rem .8rem; cursor: pointer; }' . "\n"
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
       . '  </style>' . "\n"
       . '</head>' . "\n"
       . '<body>' . "\n"
       . '<nav aria-label="Administrace">' . "\n"
       . '  <h2>' . $siteName . '</h2>' . "\n";

    $userName = h(currentUserDisplayName());
    if ($userName !== '') {
        echo '  <p style="font-size:.8rem;color:#aaa;margin:0 0 .75rem"><span aria-hidden="true">&#9786;</span> ' . $userName . '</p>' . "\n";
    }

    $topItems = [
        ['url' => $b . '/admin/index.php',   'label' => 'Přehled'],
        ['url' => $b . '/admin/profile.php', 'label' => 'Můj profil'],
    ];

    $moduleItems = [
        ['url' => $b . '/admin/news.php',          'label' => 'Novinky'],
        ['url' => $b . '/admin/chat.php',          'label' => 'Chat'],
        ['url' => $b . '/admin/contact.php',       'label' => 'Kontakt'],
        ['url' => $b . '/admin/gallery_albums.php','label' => 'Galerie'],
        ['url' => $b . '/admin/events.php',        'label' => 'Události'],
        ['url' => $b . '/admin/podcast.php',         'label' => 'Podcasty'],
        ['url' => $b . '/admin/places.php',        'label' => 'Zajímavá místa'],
        ['url' => $b . '/admin/newsletter.php',    'label' => 'Newsletter'],
        ['url' => $b . '/admin/downloads.php',     'label' => 'Ke stažení'],
        ['url' => $b . '/admin/food.php',          'label' => 'Jídelní lístek'],
        ['url' => $b . '/admin/pages.php',         'label' => 'Stránky'],
        ['url' => $b . '/admin/import.php',        'label' => 'Export / Import'],
    ];

    $bottomItems = [];
    if (isSuperAdmin()) {
        $bottomItems[] = ['url' => $b . '/admin/users.php', 'label' => 'Správa uživatelů'];
    }
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
       . '    <summary style="cursor:pointer;color:#bbb;font-size:.85rem;padding:.3rem 0;list-style:none">'
       . '<span aria-hidden="true">&#9776;</span> Moduly</summary>' . "\n"
       . '    <ul style="margin:.3rem 0 0;padding:0;list-style:none">' . "\n"
       . '      <li>' . "\n"
       . '        <details>' . "\n"
       . '          <summary style="cursor:pointer;color:#ddd;font-size:.9rem;padding:.2rem 0;list-style:none;user-select:none">Blog</summary>' . "\n"
       . '          <ul style="margin:.2rem 0 0;padding:0;list-style:none">' . "\n"
       . '            <li><a href="' . $b . '/admin/blog.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd">Články</a></li>' . "\n"
       . '            <li><a href="' . $b . '/admin/blog_tags.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd"><span aria-hidden="true">↳</span> Tagy</a></li>' . "\n"
       . '            <li><a href="' . $b . '/admin/comments.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd"><span aria-hidden="true">↳</span> Komentáře</a></li>' . "\n"
       . '          </ul>' . "\n"
       . '        </details>' . "\n"
       . '      </li>' . "\n";
    foreach ($moduleItems as $item) { echo $renderItem($item); }
    echo '    </ul>' . "\n"
       . '  </details>' . "\n"
       . '  <details style="margin:.4rem 0">' . "\n"
       . '    <summary style="cursor:pointer;color:#bbb;font-size:.85rem;padding:.3rem 0;list-style:none">'
       . '<span aria-hidden="true">&#9881;</span> Nastavení</summary>' . "\n"
       . '    <ul style="margin:.3rem 0 0;padding:0;list-style:none">' . "\n"
       . '      <li><a href="' . $b . '/admin/settings.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd">Základní nastavení</a></li>' . "\n"
       . '      <li><a href="' . $b . '/admin/settings_modules.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd">Moduly</a></li>' . "\n"
       . '      <li><a href="' . $b . '/admin/settings_display.php" style="padding-left:.75rem;font-size:.85rem;color:#ddd">Nastavení zobrazení</a></li>' . "\n"
       . '    </ul>' . "\n"
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
    echo '<script>document.addEventListener("DOMContentLoaded",function(){'
       . 'var l=document.getElementById("a11y-live");if(!l)return;'
       . 'var m=document.querySelector(\'[role="status"]:not(#a11y-live),[role="alert"]\');'
       . 'if(m){var t=m.textContent.trim();if(t)setTimeout(function(){l.textContent=t;},150);}'
       . '});</script>'
       . '</main></body></html>';
}
