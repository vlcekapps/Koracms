<?php
// UI komponenty – patička, cookie banner, SEO, admin bar, a11y, favicon, údržba, log – extrahováno z db.php

function siteFooter(): string
{
    trackPageView();

    $year     = date('Y');
    $siteName = h(getSetting('site_name', 'Kora CMS'));
    $b        = BASE_URL;
    $publicRegistrationEnabled = publicRegistrationEnabled();

    $version = KORA_VERSION;

    $footerWidgets = renderZone('footer', 'footer-widgets');

    return "<footer>\n"
         . $footerWidgets
         . "  <p>&copy; {$year} {$siteName}</p>\n"
         . "  <p><a href=\"{$b}/feed.php\">RSS</a></p>\n"
         . (isModuleEnabled('reservations') && ($publicRegistrationEnabled || (isLoggedIn() && isPublicUser()))
             ? "  <p>"
               . (isLoggedIn()
                   ? (isPublicUser()
                       ? "<a href=\"{$b}/reservations/my.php\">Moje rezervace</a> · <a href=\"{$b}/public_profile.php\">Můj profil</a> · <a href=\"{$b}/public_logout.php\">Odhlásit se</a>"
                       : '')
                   : "<a href=\"{$b}/public_login.php\">Přihlášení</a> · <a href=\"{$b}/register.php\">Registrace</a>")
               . "</p>\n"
             : '')
         . "  <p><small><a href=\"https://github.com/vlcekapps/Koracms\" rel=\"noopener noreferrer\" target=\"_blank\">Kora CMS {$version}</a></small></p>\n"
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
         . '  ac.addEventListener(\'click\',function(){hide(\'1\');if(window._koraGa4Id){var s=document.createElement(\'script\');s.async=true;s.src=\'https://www.googletagmanager.com/gtag/js?id=\'+window._koraGa4Id;document.head.appendChild(s);window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag(\'js\',new Date());gtag(\'config\',window._koraGa4Id);}});' . "\n"
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
        $userId = function_exists('currentUserId') ? currentUserId() : null;
        db_connect()->prepare("INSERT INTO cms_log (action, detail, user_id) VALUES (?, ?, ?)")
            ->execute([$action, $detail, $userId]);
    } catch (\PDOException $e) {
        // Tabulka ještě neexistuje
    }
}

/**
 * Vrátí kompletní bulk akce fieldset + form (sjednocený vzor).
 * Form se zavře hned za fieldsetem – checkboxy v tabulce mají form="bulk-form".
 *
 * @param string $module   Identifikátor modulu pro bulk.php
 * @param string $redirect URL pro redirect po akci
 * @param string $legend   Popisek fieldset legendy
 * @param string $itemLabel Jednotné číslo položky pro status text (např. "článek", "album")
 * @param bool   $showPublish Zobrazit tlačítka publikovat/skrýt
 */
function bulkActions(string $module, string $redirect, string $legend, string $itemLabel = 'položka', bool $showPublish = true): string
{
    $out = '<form method="post" action="' . BASE_URL . '/admin/bulk.php" id="bulk-form">'
         . '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">'
         . '<input type="hidden" name="module" value="' . h($module) . '">'
         . '<input type="hidden" name="redirect" value="' . h($redirect) . '">'
         . '<fieldset style="margin:0 0 .85rem;border:1px solid #d6d6d6;border-radius:10px;padding:.85rem 1rem">'
         . '<legend>' . h($legend) . '</legend>'
         . '<p id="bulk-status" data-selection-status="bulk" class="field-help" aria-live="polite" style="margin-top:0">Zatím není vybraná žádná ' . h($itemLabel) . '.</p>'
         . '<div class="button-row">'
         . '<button type="submit" name="action" value="delete" class="btn btn-danger bulk-action-btn" disabled data-confirm="Smazat vybrané?">Smazat vybrané</button>';
    if ($showPublish) {
        $out .= '<button type="submit" name="action" value="publish" class="btn bulk-action-btn" disabled>Publikovat vybrané</button>'
              . '<button type="submit" name="action" value="hide" class="btn bulk-action-btn" disabled>Skrýt vybrané</button>';
    }
    $out .= '</div></fieldset></form>';
    return $out;
}

// Zachováme zpětnou kompatibilitu pro soubory, které ještě používají starý vzor
function bulkFormOpen(string $module, string $redirectPage): string
{
    return bulkActions($module, BASE_URL . '/admin/' . $redirectPage, 'Hromadné akce', 'položka', true);
}
function bulkFormClose(): string { return ''; }
function bulkActionBar(bool $showPublish = true): string { return ''; }

/**
 * Vrátí JS pro select-all a enable/disable bulk tlačítek.
 * Checkboxy mají form="bulk-form", select-all má id="check-all".
 */
function bulkCheckboxJs(): string
{
    $nonce = cspNonce();
    return '<script nonce="' . $nonce . '">'
        . '(function(){'
        . 'var form=document.getElementById("bulk-form");if(!form)return;'
        . 'var checkAllNodes=Array.from(document.querySelectorAll("[data-check-all=\'bulk-form\']"));'
        . 'if(checkAllNodes.length===0){var legacyCheckAll=document.getElementById("check-all");if(legacyCheckAll)checkAllNodes=[legacyCheckAll];}'
        . 'var cbs=document.querySelectorAll("input[name=\'ids[]\']");'
        . 'var statusEl=form.querySelector("[data-selection-status]")||document.getElementById("bulk-status");'
        . 'var buttons=form.querySelectorAll(".bulk-action-btn");'
        . 'function u(){'
        . 'var c=document.querySelectorAll("input[name=\'ids[]\']:checked").length;'
        . 'buttons.forEach(function(b){b.disabled=c===0;});'
        . 'if(statusEl){'
        . 'if(c===0)statusEl.textContent="Zatím není vybraná žádná položka.";'
        . 'else statusEl.textContent="Vybráno: "+c;'
        . '}'
        . 'checkAllNodes.forEach(function(checkAll){checkAll.checked=c===cbs.length&&c>0;});'
        . '}'
        . 'checkAllNodes.forEach(function(checkAll){checkAll.addEventListener("change",function(){cbs.forEach(function(cb){cb.checked=checkAll.checked;});u();});});'
        . 'cbs.forEach(function(cb){cb.addEventListener("change",u);});'
        . 'form.addEventListener("submit",function(e){'
        . 'var btn=e.submitter||document.activeElement;'
        . 'if(btn&&btn.dataset.confirm&&!confirm(btn.dataset.confirm)){e.preventDefault();}'
        . '});'
        . '})();'
        . '</script>';
}

/**
 * Vrátí JS pro drag & drop řazení seznamu s AJAX uložením.
 * Seznam musí mít data-sortable="module" a položky data-sort-id="X".
 * Tlačítka Nahoru/Dolů zůstávají jako WCAG fallback.
 */
function sortableJs(): string
{
    $nonce = cspNonce();
    $endpoint = BASE_URL . '/admin/reorder_ajax.php';
    $csrf = csrfToken();
    return '<script nonce="' . $nonce . '">'
        . '(function(){'
        . 'document.querySelectorAll("[data-sortable]").forEach(function(list){'
        . 'var module=list.dataset.sortable;'
        . 'var items=function(){return Array.from(list.querySelectorAll("[data-sort-id]"));};'
        . 'var dragged=null;'
        . 'var live=document.getElementById("a11y-live");'
        // Drag events
        . 'items().forEach(function(el){el.setAttribute("draggable","true");});'
        . 'list.addEventListener("dragstart",function(e){'
        . 'var t=e.target.closest("[data-sort-id]");if(!t)return;'
        . 'dragged=t;t.style.opacity="0.4";'
        . 'e.dataTransfer.effectAllowed="move";'
        . '});'
        . 'list.addEventListener("dragover",function(e){'
        . 'e.preventDefault();e.dataTransfer.dropEffect="move";'
        . 'var t=e.target.closest("[data-sort-id]");'
        . 'if(t&&t!==dragged){var r=t.getBoundingClientRect();'
        . 'var mid=r.top+r.height/2;'
        . 'if(e.clientY<mid)list.insertBefore(dragged,t);else list.insertBefore(dragged,t.nextSibling);'
        . '}'
        . '});'
        . 'list.addEventListener("dragend",function(){'
        . 'if(dragged)dragged.style.opacity="";dragged=null;save();'
        . '});'
        // Save function
        . 'function save(){'
        . 'var order=items().map(function(el){return el.dataset.sortId;});'
        . 'var fd=new FormData();fd.append("csrf_token",' . json_encode($csrf) . ');'
        . 'fd.append("module",module);'
        . 'order.forEach(function(id){fd.append("order[]",id);});'
        . 'fetch(' . json_encode($endpoint) . ',{method:"POST",body:fd,credentials:"same-origin"})'
        . '.then(function(r){return r.json();})'
        . '.then(function(d){'
        . 'var msg=d&&d.ok?"Pořadí uloženo.":"Uložení pořadí selhalo.";'
        . 'if(!d||!d.ok)alert(msg);'
        . 'if(live)live.textContent=msg;'
        . '});'
        . '}'
        // Move function (shared by keyboard and buttons)
        . 'function moveItem(el,dir){'
        . 'if(dir==="up"&&el.previousElementSibling)list.insertBefore(el,el.previousElementSibling);'
        . 'else if(dir==="down"&&el.nextElementSibling)list.insertBefore(el.nextElementSibling,el);'
        . 'else return;'
        . 'save();el.focus();'
        . 'if(live){var pos=items().indexOf(el)+1;live.textContent="Přesunuto na pozici "+pos+".";}'
        . '}'
        // Visible ↑/↓ buttons
        . 'items().forEach(function(el){'
        . 'var wrap=document.createElement("span");'
        . 'wrap.style.cssText="display:inline-flex;gap:2px;margin-left:6px;vertical-align:middle";'
        . 'var up=document.createElement("button");up.type="button";up.textContent="↑";'
        . 'up.title="Posunout nahoru";up.setAttribute("aria-label","Posunout nahoru");'
        . 'up.style.cssText="padding:1px 6px;cursor:pointer;font-size:.85rem;line-height:1";'
        . 'up.addEventListener("click",function(){moveItem(el,"up");});'
        . 'var dn=document.createElement("button");dn.type="button";dn.textContent="↓";'
        . 'dn.title="Posunout dolů";dn.setAttribute("aria-label","Posunout dolů");'
        . 'dn.style.cssText="padding:1px 6px;cursor:pointer;font-size:.85rem;line-height:1";'
        . 'dn.addEventListener("click",function(){moveItem(el,"down");});'
        . 'wrap.appendChild(up);wrap.appendChild(dn);'
        . 'el.appendChild(wrap);'
        . '});'
        // Keyboard: Ctrl+Arrow
        . 'list.addEventListener("keydown",function(e){'
        . 'if(!e.ctrlKey||!["ArrowUp","ArrowDown"].includes(e.key))return;'
        . 'var t=e.target.closest("[data-sort-id]");if(!t)return;'
        . 'e.preventDefault();'
        . 'moveItem(t,e.key==="ArrowUp"?"up":"down");'
        . '});'
        . '});'
        . '})();'
        . '</script>';
}

/**
 * Vrátí relativní čas v češtině (např. "před 5 minutami", "včera").
 */
function relativeTime(?string $datetime): string
{
    if ($datetime === null || trim($datetime) === '') {
        return '–';
    }
    try {
        $then = new \DateTimeImmutable($datetime);
    } catch (\Exception $e) {
        return '–';
    }
    $now  = new \DateTimeImmutable('now');
    $diff = $now->getTimestamp() - $then->getTimestamp();

    if ($diff < 0) {
        return 'právě teď';
    }

    if ($diff < 60) {
        return 'právě teď';
    }

    $minutes = (int)floor($diff / 60);
    if ($minutes < 60) {
        if ($minutes === 1) {
            return 'před 1 minutou';
        }
        if ($minutes >= 2 && $minutes <= 4) {
            return "před {$minutes} minutami";
        }
        return "před {$minutes} minutami";
    }

    $hours = (int)floor($diff / 3600);
    if ($hours < 24) {
        if ($hours === 1) {
            return 'před 1 hodinou';
        }
        if ($hours >= 2 && $hours <= 4) {
            return "před {$hours} hodinami";
        }
        return "před {$hours} hodinami";
    }

    $days = (int)floor($diff / 86400);
    if ($days === 1) {
        return 'včera';
    }
    if ($days >= 2 && $days <= 4) {
        return "před {$days} dny";
    }
    if ($days < 7) {
        return "před {$days} dny";
    }
    if ($days < 30) {
        $weeks = (int)floor($days / 7);
        if ($weeks === 1) {
            return 'před 1 týdnem';
        }
        return "před {$weeks} týdny";
    }

    $months = (int)floor($days / 30);
    if ($months < 12) {
        if ($months === 1) {
            return 'před 1 měsícem';
        }
        if ($months >= 2 && $months <= 4) {
            return "před {$months} měsíci";
        }
        return "před {$months} měsíci";
    }

    $years = (int)floor($days / 365);
    if ($years === 1) {
        return 'před 1 rokem';
    }
    if ($years >= 2 && $years <= 4) {
        return "před {$years} roky";
    }
    return "před {$years} lety";
}

// ──────────────────────── Content locking ────────────────────────────────

/**
 * Pokusí se získat zámek obsahu. Pokud zámek drží jiný uživatel
 * a ještě nevypršel, vrátí informace o něm. Jinak zámek získá/obnoví a vrátí null.
 *
 * @return array|null  null = zámek získán; pole ['locked_by' => string, 'locked_at' => string] = zamčeno jiným uživatelem
 */
function acquireContentLock(string $entityType, int $entityId): ?array
{
    $userId = currentUserId();
    if ($userId === null) {
        return null;
    }

    $pdo = db_connect();
    $expiresAt = date('Y-m-d H:i:s', time() + 900); // 15 minut

    // Zkontrolujeme existující zámek
    try {
        $stmt = $pdo->prepare(
            "SELECT cl.user_id, cl.locked_at, cl.expires_at,
                    COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS user_name
             FROM cms_content_locks cl
             LEFT JOIN cms_users u ON u.id = cl.user_id
             WHERE cl.entity_type = ? AND cl.entity_id = ?"
        );
        $stmt->execute([$entityType, $entityId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $lockUserId = (int)$existing['user_id'];
            $lockExpired = strtotime((string)$existing['expires_at']) <= time();

            if ($lockUserId !== $userId && !$lockExpired) {
                // Zamčeno jiným uživatelem a ještě nevypršelo
                return [
                    'locked_by' => (string)($existing['user_name'] ?? '–'),
                    'locked_at' => (string)$existing['locked_at'],
                ];
            }

            // Zámek pro stejného uživatele nebo expirovaný – aktualizujeme
            $pdo->prepare(
                "UPDATE cms_content_locks SET user_id = ?, locked_at = NOW(), expires_at = ? WHERE entity_type = ? AND entity_id = ?"
            )->execute([$userId, $expiresAt, $entityType, $entityId]);

            return null;
        }

        // Žádný zámek – vytvoříme nový (atomicky přes ON DUPLICATE KEY UPDATE pro případ race condition)
        $pdo->prepare(
            "INSERT INTO cms_content_locks (entity_type, entity_id, user_id, locked_at, expires_at)
             VALUES (?, ?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), locked_at = NOW(), expires_at = VALUES(expires_at)"
        )->execute([$entityType, $entityId, $userId, $expiresAt]);

        return null;
    } catch (\PDOException $e) {
        error_log('acquireContentLock: ' . $e->getMessage());
        return null;
    }
}

/**
 * Uvolní zámek obsahu pro aktuálního uživatele.
 */
function releaseContentLock(string $entityType, int $entityId): void
{
    $userId = currentUserId();
    if ($userId === null) {
        return;
    }

    try {
        db_connect()->prepare(
            "DELETE FROM cms_content_locks WHERE entity_type = ? AND entity_id = ? AND user_id = ?"
        )->execute([$entityType, $entityId, $userId]);
    } catch (\PDOException $e) {
        error_log('releaseContentLock: ' . $e->getMessage());
    }
}

/**
 * Obnoví expiraci zámku pro aktuálního uživatele. Vrací true, pokud byl zámek obnoven.
 */
function refreshContentLock(string $entityType, int $entityId): bool
{
    $userId = currentUserId();
    if ($userId === null) {
        return false;
    }

    $expiresAt = date('Y-m-d H:i:s', time() + 900);
    try {
        $stmt = db_connect()->prepare(
            "UPDATE cms_content_locks SET expires_at = ? WHERE entity_type = ? AND entity_id = ? AND user_id = ?"
        );
        $stmt->execute([$expiresAt, $entityType, $entityId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (\PDOException $e) {
        error_log('refreshContentLock: ' . $e->getMessage());
        return false;
    }
}

/**
 * Validuje datetime-local vstup (formát Y-m-d\TH:i).
 * Vrátí SQL datetime string (Y-m-d H:i:s) nebo null při chybě.
 */
function validateDateTimeLocal(string $input): ?string
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    $dt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $input);
    if ($dt === false || $dt->format('Y-m-d\TH:i') !== $input) {
        return null;
    }
    return $dt->format('Y-m-d H:i:s');
}
