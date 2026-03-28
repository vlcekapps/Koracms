<?php
// UI komponenty – patička, cookie banner, SEO, admin bar, a11y, favicon, údržba, log – extrahováno z db.php

function siteFooter(): string
{
    trackPageView();

    $year     = date('Y');
    $siteName = h(getSetting('site_name', 'Kora CMS'));
    $b        = BASE_URL;
    $links    = '';

    $socials = [
        'social_facebook'  => ['Facebook',  'https://www.facebook.com/'],
        'social_youtube'   => ['YouTube',   'https://www.youtube.com/'],
        'social_instagram' => ['Instagram', 'https://www.instagram.com/'],
        'social_twitter'   => ['X/Twitter', 'https://x.com/'],
    ];
    foreach ($socials as $key => [$label, $prefix]) {
        $url = getSetting($key, '');
        if ($url !== '') {
            $links .= '<a href="' . h($url) . '" rel="noopener noreferrer" target="_blank">' . $label . '</a> ';
        }
    }
    $links .= '<a href="' . $b . '/feed.php">RSS</a>';

    $version = KORA_VERSION;

    return "<footer>\n"
         . "  <p>&copy; {$year} {$siteName}</p>\n"
         . "  <p>{$links}</p>\n"
         . "  <p><a href=\"{$b}/search.php\">Vyhledávání</a>"
         . (isModuleEnabled('newsletter') ? " · <a href=\"{$b}/subscribe.php\">Odběr novinek</a>" : '')
         . "</p>\n"
         . (isModuleEnabled('reservations')
             ? "  <p>"
               . (isLoggedIn()
                   ? (isPublicUser()
                       ? "<a href=\"{$b}/reservations/my.php\">Moje rezervace</a> · <a href=\"{$b}/public_profile.php\">Můj profil</a> · <a href=\"{$b}/public_logout.php\">Odhlásit se</a>"
                       : '')
                   : "<a href=\"{$b}/public_login.php\">Přihlášení</a> · <a href=\"{$b}/register.php\">Registrace</a>")
               . "</p>\n"
             : '')
         . "  <p><small><a href=\"https://koracms.pvlcek.cz\" rel=\"noopener noreferrer\" target=\"_blank\">Kora CMS {$version}</a></small></p>\n"
         . (getSetting('visitor_counter_enabled', '0') === '1'
             ? (function () {
                   $vs = getVisitorStats();
                    $f  = fn(int $n) => number_format($n, 0, ',', "\u{00a0}");
                    $items = [
                        'Online' => $f($vs['online']),
                        'Dnes' => $f($vs['today']),
                        'Měsíc' => $f($vs['month']),
                        'Celkem' => $f($vs['total']),
                    ];

                    $html = "  <div class=\"visitor-counter-block\" aria-labelledby=\"visitor-counter-heading\">\n"
                          . "    <p id=\"visitor-counter-heading\" class=\"sr-only\">Statistiky návštěvnosti</p>\n"
                          . "    <ul class=\"visitor-counter\">\n";

                    foreach ($items as $label => $value) {
                        $html .= "      <li class=\"visitor-counter__item\">"
                              . "<span class=\"visitor-counter__label\">" . h($label) . ":</span> "
                              . "<strong class=\"visitor-counter__value\">{$value}</strong>"
                              . "</li>\n";
                    }

                    return $html
                         . "    </ul>\n"
                         . "  </div>\n";
               })()
             : '')
         . "</footer>\n"
         . cookieBanner();
}

/**
 * Vrátí HTML cookie lišty – zobrazí se jen při první návštěvě.
 * Volbu uloží do cookie cms_cookie (1=přijato, 0=odmítnuto) na 365 dní.
 */
function cookieBanner(): string
{
    if (getSetting('cookie_consent_enabled', '0') !== '1') return '';
    $text = h(getSetting('cookie_consent_text',
        'Tento web používá soubory cookies ke zlepšení vašeho zážitku z prohlížení.'));
    $nonce = cspNonce();
    return '<div id="cookie-banner" role="dialog" aria-labelledby="cookie-heading" aria-modal="true"'
         . ' style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:9000;'
         . 'background:#222;color:#fff;padding:1rem 1.5rem;'
         . 'box-shadow:0 -2px 8px rgba(0,0,0,.4)">' . "\n"
         . '  <p id="cookie-heading" style="margin:0 0 .75rem">' . "\n"
         . '    <strong>Soubory cookies</strong> &ndash; ' . $text . "\n"
         . '  </p>' . "\n"
         . '  <div style="display:flex;gap:.75rem;flex-wrap:wrap">' . "\n"
         . '    <button id="cookie-accept" type="button"'
         . ' style="padding:.4rem 1rem;background:#4caf50;border:none;color:#fff;'
         . 'cursor:pointer;border-radius:3px;font-size:1rem">Přijmout</button>' . "\n"
         . '    <button id="cookie-decline" type="button"'
         . ' style="padding:.4rem 1rem;background:#777;border:none;color:#fff;'
         . 'cursor:pointer;border-radius:3px;font-size:1rem">Odmítnout</button>' . "\n"
         . '  </div>' . "\n"
         . '</div>' . "\n"
         . '<script nonce="' . $nonce . '">' . "\n"
         . '(function(){' . "\n"
         . '  function getCk(n){var v=\'; \'+document.cookie,p=v.split(\'; \'+n+\'=\');if(p.length===2)return p.pop().split(\';\').shift();}' . "\n"
         . '  function setCk(n,v,d){var e=new Date();e.setTime(e.getTime()+(d*864e5));document.cookie=n+\'=\'+v+\';expires=\'+e.toUTCString()+\';path=/;SameSite=Lax\';}' . "\n"
         . '  var b=document.getElementById(\'cookie-banner\');' . "\n"
         . '  var ac=document.getElementById(\'cookie-accept\');' . "\n"
         . '  var dc=document.getElementById(\'cookie-decline\');' . "\n"
         . '  if(!getCk(\'cms_cookie\')){b.style.display=\'block\';setTimeout(function(){ac.focus();},50);}' . "\n"
         . '  function hide(v){setCk(\'cms_cookie\',v,365);b.style.display=\'none\';}' . "\n"
         . '  ac.addEventListener(\'click\',function(){hide(\'1\');});' . "\n"
         . '  dc.addEventListener(\'click\',function(){hide(\'0\');});' . "\n"
         . '  b.addEventListener(\'keydown\',function(e){' . "\n"
         . '    if(e.key!==\'Tab\')return;' . "\n"
         . '    var els=b.querySelectorAll(\'button\');' . "\n"
         . '    if(e.shiftKey&&document.activeElement===els[0]){e.preventDefault();els[els.length-1].focus();}' . "\n"
         . '    else if(!e.shiftKey&&document.activeElement===els[els.length-1]){e.preventDefault();els[0].focus();}' . "\n"
         . '  });' . "\n"
         . '})();' . "\n"
         . '</script>' . "\n";
}

/**
 * Vrátí HTML meta tagů pro SEO a Open Graph.
 *
 * @param array{title?:string,description?:string,image?:string,url?:string,type?:string} $meta
 */
function seoMeta(array $meta = []): string
{
    $siteName = h(getSetting('site_name', 'Kora CMS'));
    $b        = BASE_URL;
    $title = isset($meta['title'])       ? h($meta['title'])       : $siteName;
    $desc  = isset($meta['description']) ? h($meta['description']) : h(getSetting('site_description', ''));
    $image = $meta['image'] ?? '';
    $url   = isset($meta['url'])         ? h($meta['url'])         : '';
    $type  = isset($meta['type'])        ? h($meta['type'])        : 'website';

    if ($image === '') {
        $def = getSetting('og_image_default', '');
        if ($def !== '') $image = h($b . '/uploads/' . $def);
    } else {
        $image = h($image);
    }

    $out  = "  <meta name=\"description\" content=\"{$desc}\">\n";
    $out .= "  <meta property=\"og:type\" content=\"{$type}\">\n";
    $out .= "  <meta property=\"og:title\" content=\"{$title}\">\n";
    $out .= "  <meta property=\"og:site_name\" content=\"{$siteName}\">\n";
    if ($desc  !== '') $out .= "  <meta property=\"og:description\" content=\"{$desc}\">\n";
    if ($image !== '') $out .= "  <meta property=\"og:image\" content=\"{$image}\">\n";
    if ($url   !== '') $out .= "  <meta property=\"og:url\" content=\"{$url}\">\n";
    return $out;
}

/**
 * Vrátí HTML administrátorské lišty (viditelné jen přihlášeným uživatelům).
 *
 * @param string $editUrl URL tlačítka "Upravit" – prázdné = pouze odkaz na admin
 */
function adminBar(string $editUrl = ''): string
{
    if (!isLoggedIn()) return '';
    $b   = BASE_URL;
    $out = '<div id="admin-bar" role="navigation" aria-label="Administrace webu"'
         . ' style="position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#222;'
         . 'color:#fff;display:flex;align-items:center;gap:.5rem;padding:.45rem .75rem;'
         . 'font-size:.85rem">'
         . '<a href="' . $b . '/admin/index.php" style="color:#ddd;text-decoration:none;display:inline-flex;'
         . 'align-items:center;min-height:2rem;padding:.35rem .6rem;border-radius:4px"><span aria-hidden="true">&#9881;</span> Admin</a>';
    if ($editUrl !== '') {
        $out .= ' <a href="' . h($editUrl) . '" style="color:#ffd700;text-decoration:none;display:inline-flex;'
              . 'align-items:center;min-height:2rem;padding:.35rem .6rem;border-radius:4px">&#9998; Upravit</a>';
    }
    $out .= '<span style="margin-left:auto">'
          . '<a href="' . $b . '/admin/logout.php" style="color:#ddd;text-decoration:none;display:inline-flex;'
          . 'align-items:center;min-height:2rem;padding:.35rem .6rem;border-radius:4px">Odhlásit se</a>'
          . '</span>';
    $out .= '</div>';
    return $out;
}

/**
 * Vrátí sdílené a11y styly pro skip link, screen-reader text a focus ring.
 */
function publicA11yStyleTag(): string
{
    $nonce = cspNonce();
    return "<style nonce=\"{$nonce}\">\n"
         . "  .skip-link { position:absolute; left:-9999px; top:auto; }\n"
         . "  .skip-link:focus { left:1rem; top:1rem; z-index:9999; background:#fff; color:#000;"
         . " padding:.5rem 1rem; border:2px solid #000; text-decoration:none; }\n"
         . "  .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden;"
         . " clip:rect(0,0,0,0); white-space:nowrap; border:0; }\n"
         . "  :focus-visible { outline:3px solid #005fcc; outline-offset:2px; }\n"
         . "</style>\n";
}

/**
 * Vrátí HTML tagu <link rel="icon"> pro favicon (pokud je nastaven).
 */
function faviconTag(): string
{
    $favicon = getSetting('site_favicon', '');
    if ($favicon === '') return '';
    $url = h(BASE_URL . '/uploads/site/' . $favicon);
    return "  <link rel=\"icon\" href=\"{$url}\">\n"
         . "  <link rel=\"apple-touch-icon\" href=\"{$url}\">\n";
}

/**
 * Pokud je zapnut režim údržby, zobrazí stránku údržby a ukončí skript.
 * Přihlášení administrátoři nejsou omezeni.
 */
function checkMaintenanceMode(): void
{
    if (getSetting('maintenance_mode', '0') !== '1') return;
    if (isLoggedIn()) return;
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if (str_ends_with($script, DIRECTORY_SEPARATOR . 'maintenance.php')) return;
    include dirname(__DIR__) . '/maintenance.php';
    exit;
}

/**
 * Zapíše záznam do audit logu (cms_log).
 */
function logAction(string $action, string $detail = ''): void
{
    try {
        db_connect()->prepare("INSERT INTO cms_log (action, detail) VALUES (?, ?)")
            ->execute([$action, $detail]);
    } catch (\PDOException $e) {
        // Tabulka ještě neexistuje
    }
}

/**
 * Vrátí HTML pro bulk action bar – obalující form, checkboxy a tlačítka.
 * Použití: echo bulkFormOpen('news', 'news.php');
 */
function bulkFormOpen(string $module, string $redirectPage): string
{
    $redirect = BASE_URL . '/admin/' . $redirectPage;
    return '<form method="post" action="' . BASE_URL . '/admin/bulk.php" id="bulk-form">'
         . '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">'
         . '<input type="hidden" name="module" value="' . h($module) . '">'
         . '<input type="hidden" name="redirect" value="' . h($redirect) . '">';
}

function bulkFormClose(): string
{
    return '</form>';
}

/**
 * Vrátí HTML pro bulk action bar s tlačítky (smazat, publikovat, skrýt).
 * Umístí se nad tabulku.
 */
function bulkActionBar(bool $showPublish = true): string
{
    $out = '<div class="bulk-bar" style="margin-bottom:.5rem;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap" hidden>';
    $out .= '<span class="bulk-bar__count" aria-live="polite"></span>';
    $out .= '<select name="action" aria-label="Hromadná akce" required>';
    $out .= '<option value="">-- Vyberte akci --</option>';
    $out .= '<option value="delete">Smazat vybrané</option>';
    if ($showPublish) {
        $out .= '<option value="publish">Publikovat vybrané</option>';
        $out .= '<option value="hide">Skrýt vybrané</option>';
    }
    $out .= '</select>';
    $out .= '<button type="submit" class="btn" onclick="return confirm(\'Opravdu provést hromadnou akci?\')">Provést</button>';
    $out .= '</div>';
    return $out;
}

/**
 * Vrátí JS pro select-all checkbox a zobrazení bulk baru.
 * Volat jednou na konci admin stránky (v adminFooter).
 */
function bulkCheckboxJs(): string
{
    $nonce = cspNonce();
    return '<script nonce="' . $nonce . '">'
        . '(function(){'
        . 'var f=document.getElementById("bulk-form");if(!f)return;'
        . 'var bar=f.querySelector(".bulk-bar"),cnt=bar?bar.querySelector(".bulk-bar__count"):null;'
        . 'var sa=f.querySelector(".bulk-select-all"),cbs=f.querySelectorAll(".bulk-checkbox");'
        . 'function u(){var c=f.querySelectorAll(".bulk-checkbox:checked").length;'
        . 'if(bar)bar.hidden=c===0;if(cnt)cnt.textContent="Vybráno: "+c;'
        . 'if(sa)sa.checked=c===cbs.length&&c>0;}'
        . 'if(sa)sa.addEventListener("change",function(){cbs.forEach(function(cb){cb.checked=sa.checked;});u();});'
        . 'cbs.forEach(function(cb){cb.addEventListener("change",u);});'
        . '})();'
        . '</script>';
}
