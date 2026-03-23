<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/theme.php';

define('KORA_VERSION', trim(file_get_contents(__DIR__ . '/VERSION')));

function db_connect(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        global $server, $user, $pass, $database;
        $dsn = "mysql:host={$server};dbname={$database};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function inputInt(string $source, string $key): ?int
{
    $arr = ($source === 'get') ? $_GET : $_POST;
    $val = filter_var($arr[$key] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return ($val !== false) ? (int)$val : null;
}

// ──────────────────────────────── Nastavení (cms_settings) ────────────────────

function getSettings(): array
{
    global $_CMS_SETTINGS;
    if (!isset($_CMS_SETTINGS)) {
        try {
            $rows = db_connect()->query("SELECT `key`, value FROM cms_settings")->fetchAll();
            $_CMS_SETTINGS = array_column($rows, 'value', 'key');
        } catch (\PDOException $e) {
            $_CMS_SETTINGS = [];
        }
    }
    return $_CMS_SETTINGS;
}

function clearSettingsCache(): void
{
    global $_CMS_SETTINGS;
    unset($_CMS_SETTINGS);
}

function getSetting(string $key, string $default = ''): string
{
    return getSettings()[$key] ?? $default;
}

function saveSetting(string $key, string $value): void
{
    db_connect()
        ->prepare("INSERT INTO cms_settings (`key`, value) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE value = VALUES(value)")
        ->execute([$key, $value]);
    clearSettingsCache();
}

function isModuleEnabled(string $module): bool
{
    return getSetting('module_' . $module, '0') === '1';
}

// ────────────────────────────── Pomocné funkce ────────────────────────────────

/** Formátuje datum česky: 18. března 2026, 14:30 */
function formatCzechDate(string $datetime): string
{
    static $months = [
        '', 'ledna', 'února', 'března', 'dubna', 'května', 'června',
        'července', 'srpna', 'září', 'října', 'listopadu', 'prosince',
    ];
    try { $dt = new \DateTime($datetime); } catch (\Exception $e) { return h($datetime); }
    return $dt->format('j') . '. ' . $months[(int)$dt->format('n')]
         . ' ' . $dt->format('Y, H:i');
}

/**
 * Odhadne dobu čtení textu v minutách (průměr 200 slov/min pro češtinu).
 */
function readingTime(string $text): int
{
    $plain = strip_tags($text);
    $words = preg_match_all('/\S+/u', $plain);
    return max(1, (int)round($words / 200));
}

// ─────────────────────────────── Statické stránky ────────────────────────

/**
 * Převede text na URL slug (podporuje českou diakritiku).
 */
function slugify(string $text): string
{
    $map = [
        'á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n',
        'ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z',
        'Á'=>'a','Č'=>'c','Ď'=>'d','É'=>'e','Ě'=>'e','Í'=>'i','Ň'=>'n',
        'Ó'=>'o','Ř'=>'r','Š'=>'s','Ť'=>'t','Ú'=>'u','Ů'=>'u','Ý'=>'y','Ž'=>'z',
    ];
    $text = strtr($text, $map);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

// ─────────────────────────────── Galerie ──────────────────────────────────

/**
 * Sestaví drobečkový trail od kořene po dané album.
 * Vrací pole [ ['id'=>…, 'name'=>…], … ] od nejstaršího k aktuálnímu.
 */
function gallery_breadcrumb(int $albumId): array
{
    $pdo   = db_connect();
    $trail = [];
    $id    = $albumId;
    $seen  = [];
    while ($id !== null && !in_array($id, $seen, true)) {
        $seen[] = $id;
        $stmt   = $pdo->prepare("SELECT id, name, parent_id FROM cms_gallery_albums WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) break;
        array_unshift($trail, ['id' => (int)$row['id'], 'name' => $row['name']]);
        $id = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
    }
    return $trail;
}

/**
 * Vrátí URL náhledové miniatury alba.
 * Priorita: cover_photo_id → první fotka v albu → první podalbum (rekurze max. 4×).
 */
function gallery_cover_url(int $albumId, int $depth = 0): string
{
    if ($depth > 4) return '';
    $pdo  = db_connect();
    $base = BASE_URL . '/uploads/gallery/thumbs/';

    $stmt = $pdo->prepare("SELECT cover_photo_id FROM cms_gallery_albums WHERE id = ?");
    $stmt->execute([$albumId]);
    $album = $stmt->fetch();
    if ($album && $album['cover_photo_id']) {
        $s = $pdo->prepare("SELECT filename FROM cms_gallery_photos WHERE id = ?");
        $s->execute([$album['cover_photo_id']]);
        $p = $s->fetch();
        if ($p) return $base . rawurlencode($p['filename']);
    }

    $stmt = $pdo->prepare(
        "SELECT filename FROM cms_gallery_photos WHERE album_id = ? ORDER BY sort_order, id LIMIT 1"
    );
    $stmt->execute([$albumId]);
    $photo = $stmt->fetch();
    if ($photo) return $base . rawurlencode($photo['filename']);

    $stmt = $pdo->prepare(
        "SELECT id FROM cms_gallery_albums WHERE parent_id = ? ORDER BY name LIMIT 1"
    );
    $stmt->execute([$albumId]);
    $sub = $stmt->fetch();
    if ($sub) return gallery_cover_url((int)$sub['id'], $depth + 1);

    return '';
}

/**
 * Vytvoří miniaturu obrázku (max. $maxDim px na delší straně).
 * Vrátí true při úspěchu, false při selhání.
 */
function gallery_make_thumb(string $src, string $dst, int $maxDim = 300): bool
{
    $info = @getimagesize($src);
    if (!$info) return false;
    [$w, $h, $type] = $info;

    $image = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => @imagecreatefrompng($src),
        IMAGETYPE_GIF  => @imagecreatefromgif($src),
        IMAGETYPE_WEBP => @imagecreatefromwebp($src),
        default        => false,
    };
    if (!$image) return false;

    if ($w <= $maxDim && $h <= $maxDim) {
        $newW = $w;
        $newH = $h;
    } elseif ($w >= $h) {
        $newW = $maxDim;
        $newH = (int)round($h * $maxDim / $w);
    } else {
        $newH = $maxDim;
        $newW = (int)round($w * $maxDim / $h);
    }

    $thumb = imagecreatetruecolor($newW, $newH);
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }
    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);

    $ok = match ($type) {
        IMAGETYPE_JPEG => imagejpeg($thumb, $dst, 85),
        IMAGETYPE_PNG  => imagepng($thumb, $dst, 6),
        IMAGETYPE_GIF  => imagegif($thumb, $dst),
        IMAGETYPE_WEBP => imagewebp($thumb, $dst, 85),
        default        => false,
    };
    imagedestroy($image);
    imagedestroy($thumb);
    return (bool)$ok;
}

/**
 * Zpracuje obsah přes Parsedown (Markdown + HTML).
 * Markdown syntaxe se převede na HTML, existující HTML projde beze změny.
 */
function renderContent(string $text): string
{
    static $parsedown = null;
    if ($parsedown === null) {
        require_once __DIR__ . '/lib/Parsedown.php';
        $parsedown = new Parsedown();
        $parsedown->setSafeMode(false);
    }
    return $parsedown->text($text);
}

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 0) . ' kB';
    return $bytes . ' B';
}

/**
 * Vrátí HTML patičky s ikonami sociálních sítí a odkazem na RSS.
 * Automaticky přidá cookie lištu (pokud je povolena).
 */
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
                   return "  <p class=\"visitor-counter\" role=\"status\" aria-label=\"Statistiky návštěvnosti\">"
                        . "Online: <strong>{$f($vs['online'])}</strong>"
                        . " · Dnes: <strong>{$f($vs['today'])}</strong>"
                        . " · Měsíc: <strong>{$f($vs['month'])}</strong>"
                        . " · Celkem: <strong>{$f($vs['total'])}</strong>"
                        . "</p>\n";
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
    return <<<HTML
<div id="cookie-banner" role="dialog" aria-labelledby="cookie-heading" aria-modal="true"
     style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:9000;
            background:#222;color:#fff;padding:1rem 1.5rem;
            box-shadow:0 -2px 8px rgba(0,0,0,.4)">
  <p id="cookie-heading" style="margin:0 0 .75rem">
    <strong>Soubory cookies</strong> &ndash; {$text}
  </p>
  <div style="display:flex;gap:.75rem;flex-wrap:wrap">
    <button id="cookie-accept" type="button"
            style="padding:.4rem 1rem;background:#4caf50;border:none;color:#fff;
                   cursor:pointer;border-radius:3px;font-size:1rem">Přijmout</button>
    <button id="cookie-decline" type="button"
            style="padding:.4rem 1rem;background:#777;border:none;color:#fff;
                   cursor:pointer;border-radius:3px;font-size:1rem">Odmítnout</button>
  </div>
</div>
<script>
(function(){
  function getCk(n){var v='; '+document.cookie,p=v.split('; '+n+'=');if(p.length===2)return p.pop().split(';').shift();}
  function setCk(n,v,d){var e=new Date();e.setTime(e.getTime()+(d*864e5));document.cookie=n+'='+v+';expires='+e.toUTCString()+';path=/;SameSite=Lax';}
  var b=document.getElementById('cookie-banner');
  var ac=document.getElementById('cookie-accept');
  var dc=document.getElementById('cookie-decline');
  if(!getCk('cms_cookie')){b.style.display='block';setTimeout(function(){ac.focus();},50);}
  function hide(v){setCk('cms_cookie',v,365);b.style.display='none';}
  ac.addEventListener('click',function(){hide('1');});
  dc.addEventListener('click',function(){hide('0');});
  b.addEventListener('keydown',function(e){
    if(e.key!=='Tab')return;
    var els=b.querySelectorAll('button');
    if(e.shiftKey&&document.activeElement===els[0]){e.preventDefault();els[els.length-1].focus();}
    else if(!e.shiftKey&&document.activeElement===els[els.length-1]){e.preventDefault();els[0].focus();}
  });
})();
</script>
HTML;
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
    return "<style>\n"
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
    include __DIR__ . '/maintenance.php';
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
 * Vrátí absolutní URL včetně schématu a domény – pro použití v e-mailech.
 */
function siteUrl(string $path = ''): string
{
    $base = BASE_URL;
    if ($base === '' || !str_starts_with($base, 'http')) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = $scheme . '://' . $host . $base;
    }
    return $base . $path;
}

/**
 * Odešle e-mail v UTF-8. Vrátí true při úspěchu.
 */
function sendMail(string $to, string $subject, string $body): bool
{
    $from        = getSetting('contact_email', 'noreply@localhost');
    $safeSubject = preg_replace('/[\r\n]/', '', $subject);
    $safeFrom    = preg_replace('/[\r\n]/', '', $from);

    $smtpHost = ini_get('SMTP') ?: 'localhost';
    $smtpPort = (int)(ini_get('smtp_port') ?: 25);

    $smtp = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 5);
    if (!$smtp) {
        error_log("sendMail FAILED: connect {$smtpHost}:{$smtpPort} – {$errstr}");
        return false;
    }

    // Čtení SMTP odpovědi (včetně víceřádkových)
    $read = function () use ($smtp): string {
        $response = '';
        while (($line = fgets($smtp, 512)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $response;
    };

    $read(); // 220 greeting
    fwrite($smtp, "EHLO localhost\r\n");
    $read(); // 250 capabilities
    fwrite($smtp, "MAIL FROM:<{$safeFrom}>\r\n");
    $read();
    fwrite($smtp, "RCPT TO:<{$to}>\r\n");
    $read();
    fwrite($smtp, "DATA\r\n");
    $read(); // 354 go ahead

    $msg = "From: {$safeFrom}\r\n"
         . "To: {$to}\r\n"
         . "Subject: {$safeSubject}\r\n"
         . "Reply-To: {$safeFrom}\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n"
         . "MIME-Version: 1.0\r\n"
         . "\r\n"
         . str_replace("\n.", "\n..", $body) . "\r\n.\r\n";

    fwrite($smtp, $msg);
    $dataResp = $read();
    fwrite($smtp, "QUIT\r\n");
    fclose($smtp);

    $ok = str_starts_with(trim($dataResp), '250');
    if (!$ok) {
        error_log("sendMail FAILED: SMTP said: {$dataResp}");
    }
    return $ok;
}

// ─────────────────────────────── Statistiky ──────────────────────────────────

/**
 * Zaznamená zobrazení stránky (jedno volání za request).
 * Přeskočí adminy a známé boty. Kontroluje visitor_tracking_enabled.
 */
function trackPageView(string $pageType = 'other', ?int $refId = null): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    if (getSetting('visitor_tracking_enabled', '0') !== '1') return;
    if (isset($_SESSION['cms_user_id'])) return; // admin/spolupracovník

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|wget|curl/i', $ua)) return;

    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ipHash  = hash('sha256', $ip . '|' . date('Y-m-d'));
    $pageUrl = mb_substr(($_SERVER['REQUEST_URI'] ?? '/'), 0, 500);
    $ref     = mb_substr(($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);

    try {
        db_connect()->prepare(
            "INSERT INTO cms_page_views (page_url, page_type, page_ref_id, ip_hash, user_agent, referrer)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $pageUrl,
            $pageType,
            $refId,
            $ipHash,
            mb_substr($ua, 0, 500),
            $ref,
        ]);
    } catch (\PDOException $e) {
        // Tabulka nemusí existovat
    }
}

/**
 * Počet unikátních návštěvníků online (za posledních 5 minut).
 */
function getOnlineCount(): int
{
    try {
        return (int)db_connect()->query(
            "SELECT COUNT(DISTINCT ip_hash) FROM cms_page_views
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        )->fetchColumn();
    } catch (\PDOException $e) {
        return 0;
    }
}

/**
 * Vrátí statistiky návštěvnosti: online, today, month, total.
 */
function getVisitorStats(): array
{
    $stats = ['online' => 0, 'today' => 0, 'month' => 0, 'total' => 0];
    try {
        $pdo = db_connect();

        // Online (posledních 5 min)
        $stats['online'] = (int)$pdo->query(
            "SELECT COUNT(DISTINCT ip_hash) FROM cms_page_views
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        )->fetchColumn();

        // Dnes (unikátní IP)
        $stats['today'] = (int)$pdo->query(
            "SELECT COUNT(DISTINCT ip_hash) FROM cms_page_views
             WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();

        // Měsíc = agregáty z cms_stats_daily + dnešní live data
        $monthAgg = (int)$pdo->query(
            "SELECT COALESCE(SUM(unique_visitors), 0) FROM cms_stats_daily
             WHERE stat_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
               AND stat_date < CURDATE()"
        )->fetchColumn();
        $stats['month'] = $monthAgg + $stats['today'];

        // Celkem = agregáty + dnešní live data
        $totalAgg = (int)$pdo->query(
            "SELECT COALESCE(SUM(unique_visitors), 0) FROM cms_stats_daily
             WHERE stat_date < CURDATE()"
        )->fetchColumn();
        $stats['total'] = $totalAgg + $stats['today'];

    } catch (\PDOException $e) {
        // Tabulky nemusí existovat
    }
    return $stats;
}

/**
 * Líná agregace denních statistik + mazání starých raw dat (GDPR).
 * Volá se při návštěvě admin statistik.
 */
function statsCleanup(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $pdo = db_connect();

        // Agregace: dny starší než včerejšek, které ještě nejsou v cms_stats_daily
        $pdo->exec(
            "INSERT IGNORE INTO cms_stats_daily (stat_date, total_views, unique_visitors)
             SELECT DATE(created_at),
                    COUNT(*),
                    COUNT(DISTINCT ip_hash)
             FROM cms_page_views
             WHERE DATE(created_at) < CURDATE()
             GROUP BY DATE(created_at)"
        );

        // Mazání raw dat starších než retence
        $days = max(1, (int)getSetting('stats_retention_days', '90'));
        $pdo->prepare(
            "DELETE FROM cms_page_views WHERE created_at < DATE_SUB(CURDATE(), INTERVAL ? DAY)"
        )->execute([$days]);

    } catch (\PDOException $e) {
        // Tabulky nemusí existovat
    }
}

/** Automatické dokončení proběhlých rezervací (lazy update) */
function autoCompleteBookings(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if (!isModuleEnabled('reservations')) return;
    try {
        $pdo = db_connect();
        // confirmed → completed
        $pdo->exec(
            "UPDATE cms_res_bookings SET status = 'completed', updated_at = NOW()
             WHERE status = 'confirmed'
               AND (booking_date < CURDATE() OR (booking_date = CURDATE() AND end_time < CURTIME()))"
        );
        // pending → cancelled (termín vypršel bez schválení)
        $pdo->exec(
            "UPDATE cms_res_bookings
             SET status = 'cancelled', updated_at = NOW(), cancelled_at = NOW(),
                 admin_note = CASE
                     WHEN COALESCE(admin_note, '') = '' THEN 'Automaticky zrušeno – termín vypršel bez schválení'
                     ELSE CONCAT(admin_note, '\nAutomaticky zrušeno – termín vypršel bez schválení')
                 END
             WHERE status = 'pending'
               AND (booking_date < CURDATE() OR (booking_date = CURDATE() AND end_time < CURTIME()))"
        );
    } catch (\PDOException $e) {
        // Tabulka nemusí existovat
    }
}

/** Výchozí pořadí modulů v navigaci */
function navModuleDefaults(): array
{
    return [
        'blog'      => ['/blog/index.php',       'Blog'],
        'news'      => ['/news/index.php',        'Novinky'],
        'events'    => ['/events/index.php',      'Akce'],
        'podcast'   => ['/podcast/index.php',     'Podcasty'],
        'gallery'   => ['/gallery/index.php',     'Galerie'],
        'places'    => ['/places/index.php',      'Zajímavá místa'],
        'downloads' => ['/downloads/index.php',   'Ke stažení'],
        'food'      => ['/food/index.php',        'Jídelní lístek'],
        'chat'      => ['/chat/index.php',        'Chat'],
        'polls'     => ['/polls/index.php',       'Ankety'],
        'faq'       => ['/faq/index.php',         'FAQ'],
        'board'     => ['/board/index.php',       'Úřední deska'],
        'reservations' => ['/reservations/index.php', 'Rezervace'],
        'contact'   => ['/contact/index.php',     'Kontakt'],
    ];
}

/** Vrátí aktuální pořadí klíčů modulů dle nastavení (nebo výchozí) */
function navModuleOrder(): array
{
    $defaults = array_keys(navModuleDefaults());
    $saved    = getSetting('nav_module_order', '');
    if ($saved === '') return $defaults;

    $order = array_filter(explode(',', $saved), fn($k) => isset(navModuleDefaults()[$k]));
    $order = array_values($order);
    // Přidej nové moduly, které v uloženém pořadí chybí
    foreach ($defaults as $k) {
        if (!in_array($k, $order, true)) $order[] = $k;
    }
    return $order;
}

/** Navigace webu – zobrazí jen povolené moduly v nastavitelném pořadí */
function siteNav(string $current = ''): string
{
    $b   = BASE_URL;
    $cur = function(string $p) use ($current) {
        return $current === $p ? ' aria-current="page"' : '';
    };
    $li  = function(string $href, string $label, string $page) use ($b, $cur) {
        return '<li><a href="' . $b . $href . '"' . $cur($page) . '>' . $label . '</a></li>' . "\n";
    };

    $nav  = '<nav aria-label="Hlavní navigace"><ul>' . "\n";
    $nav .= $li('/index.php', 'Domů', 'home');

    // Statické stránky zobrazované v navigaci (za Domů, před moduly)
    try {
        $pages = db_connect()->query(
            "SELECT title, slug FROM cms_pages
             WHERE show_in_nav = 1 AND is_published = 1
             ORDER BY nav_order, title"
        )->fetchAll();
        foreach ($pages as $p) {
            $nav .= '<li><a href="' . $b . '/page.php?slug=' . rawurlencode($p['slug']) . '"'
                  . ($current === 'page:' . $p['slug'] ? ' aria-current="page"' : '')
                  . '>' . h($p['title']) . '</a></li>' . "\n";
        }
    } catch (\PDOException $e) {
        // Tabulka cms_pages ještě neexistuje
    }

    $moduleMap = navModuleDefaults();
    foreach (navModuleOrder() as $key) {
        if (isModuleEnabled($key) && isset($moduleMap[$key])) {
            [$href, $label] = $moduleMap[$key];
            $nav .= $li($href, $label, $key);
        }
    }

    if (isLoggedIn()) $nav .= $li('/admin/index.php', 'Administrace', 'admin');

    $nav .= '</ul></nav>';
    return $nav;
}
