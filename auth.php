<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'");

function isLoggedIn(): bool
{
    return !empty($_SESSION['cms_logged_in']);
}

function isSuperAdmin(): bool
{
    return !empty($_SESSION['cms_superadmin']);
}

function currentUserId(): ?int
{
    return isset($_SESSION['cms_user_id']) ? (int)$_SESSION['cms_user_id'] : null;
}

function currentUserDisplayName(): string
{
    return $_SESSION['cms_user_name'] ?? '';
}

/**
 * Vrátí pouze bezpečný interní redirect v rámci tohoto webu.
 * Zahazuje externí URL, protocol-relative URL i neplatné cesty.
 */
function internalRedirectTarget(string $target, string $default = ''): string
{
    $target = trim(str_replace(["\r", "\n"], '', $target));
    if ($target === '') {
        return $default;
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $target)) {
        return $default;
    }

    $parts = parse_url($target);
    if ($parts === false) {
        return $default;
    }

    if (isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return $default;
    }

    $path = $parts['path'] ?? '';
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
        return $default;
    }

    if (BASE_URL !== '' && $path !== BASE_URL && !str_starts_with($path, BASE_URL . '/')) {
        return $default;
    }

    $query    = isset($parts['query']) ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    return $path . $query . $fragment;
}

function requireLogin(string $loginUrl = '/admin/login.php'): void
{
    if (!isLoggedIn()) {
        header('Location: ' . $loginUrl);
        exit;
    }

    // Veřejní uživatelé nemají přístup do administrace
    if (isPublicUser() && str_contains($loginUrl, '/admin/')) {
        header('Location: ' . BASE_URL . '/public_profile.php');
        exit;
    }

    // Upgrade staré session (bez user_id) na nový formát s cms_users
    if (!isset($_SESSION['cms_user_id'])) {
        try {
            $u = db_connect()->query(
                "SELECT id, email, first_name, last_name, nickname, is_superadmin, role
                 FROM cms_users WHERE is_superadmin = 1 LIMIT 1"
            )->fetch();
            if ($u) {
                $name = $u['nickname'] !== '' ? $u['nickname']
                      : trim($u['first_name'] . ' ' . $u['last_name']);
                if ($name === '') $name = $u['email'];
                $_SESSION['cms_user_id']    = (int)$u['id'];
                $_SESSION['cms_user_email'] = $u['email'];
                $_SESSION['cms_user_name']  = $name;
                $_SESSION['cms_superadmin'] = (bool)$u['is_superadmin'];
                $_SESSION['cms_user_role']  = $u['role'] ?? 'admin';
            }
        } catch (\PDOException $e) {
            // cms_users ještě neexistuje – ponecháme session beze změny
        }
    }
}

function requireSuperAdmin(): void
{
    requireLogin(BASE_URL . '/admin/login.php');
    if (!isSuperAdmin()) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="cs"><head><meta charset="utf-8"><title>403</title></head>'
           . '<body><p>Přístup odepřen. Tato funkce je vyhrazena hlavnímu administrátorovi.</p>'
           . '<p><a href="' . BASE_URL . '/admin/index.php">Zpět</a></p></body></html>';
        exit;
    }
}

/**
 * Přihlásí uživatele – uloží data do session.
 */
function loginUser(int $id, string $email, bool $superadmin, string $displayName, string $role = 'collaborator'): void
{
    session_regenerate_id(true);
    $_SESSION['cms_logged_in']  = true;
    $_SESSION['cms_user_id']    = $id;
    $_SESSION['cms_user_email'] = $email;
    $_SESSION['cms_superadmin'] = $superadmin;
    $_SESSION['cms_user_name']  = $displayName;
    $_SESSION['cms_user_role']  = $role;
}

function isPublicUser(): bool
{
    return ($_SESSION['cms_user_role'] ?? '') === 'public';
}

function currentUserRole(): string
{
    return $_SESSION['cms_user_role'] ?? 'collaborator';
}

/**
 * Vyžaduje přihlášení veřejného uživatele.
 * Pokud není přihlášen, přesměruje na veřejný login.
 */
function requirePublicLogin(string $redirect = ''): void
{
    if (!isLoggedIn()) {
        $url = BASE_URL . '/public_login.php';
        $safeRedirect = internalRedirectTarget($redirect, '');
        if ($safeRedirect !== '') {
            $url .= '?redirect=' . urlencode($safeRedirect);
        }
        header('Location: ' . $url);
        exit;
    }
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vygeneruje příklad z násobilky (2–9 × 2–9) a uloží správnou odpověď do session.
 * Vrátí text příkladu, např. "3 × 7".
 */
function captchaGenerate(): string
{
    $a = random_int(2, 9);
    $b = random_int(2, 9);
    $_SESSION['captcha_answer'] = $a * $b;
    return "{$a} × {$b}";
}

/**
 * Ověří uživatelovu odpověď na CAPTCHA. Jednorázové – odpověď ze session se po ověření odstraní.
 */
function captchaVerify(string $input): bool
{
    $expected = $_SESSION['captcha_answer'] ?? null;
    unset($_SESSION['captcha_answer']);
    if ($expected === null) return false;
    return (int)trim($input) === (int)$expected;
}

/**
 * Rate limiting – přeruší požadavek s HTTP 429, pokud IP překročí limit.
 * $action  – identifikátor akce (login, comment, contact, chat)
 * $max     – maximální počet pokusů v časovém okně
 * $window  – délka okna v sekundách
 */
function rateLimit(string $action, int $max = 10, int $window = 60): void
{
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = hash('sha256', $ip . '|' . $action);
    try {
        $pdo = db_connect();
        $pdo->prepare("DELETE FROM cms_rate_limit
                    WHERE window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)")
            ->execute([$window]);
        $stmt = $pdo->prepare("SELECT attempts FROM cms_rate_limit WHERE id = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row) {
            if ((int)$row['attempts'] >= $max) {
                http_response_code(429);
                echo '<!DOCTYPE html><html lang="cs"><head><meta charset="utf-8"><title>429</title></head>'
                   . '<body><p>Příliš mnoho pokusů. Zkuste to prosím za chvíli.</p></body></html>';
                exit;
            }
            $pdo->prepare("UPDATE cms_rate_limit SET attempts = attempts + 1 WHERE id = ?")
                ->execute([$key]);
        } else {
            $pdo->prepare("INSERT INTO cms_rate_limit (id, attempts, window_start) VALUES (?,1,NOW())")
                ->execute([$key]);
        }
    } catch (\PDOException $e) {
        error_log('rateLimit: ' . $e->getMessage());
    }
}

/**
 * Vrátí HTML skrytého honeypot pole.
 */
function honeypotField(): string
{
    return '<div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">'
         . '<label for="hp_website">Website</label>'
         . '<input type="text" id="hp_website" name="hp_website" tabindex="-1" autocomplete="off">'
         . '</div>';
}

/**
 * Vrátí true, pokud bot vyplnil honeypot pole (= spam).
 */
function honeypotTriggered(): bool
{
    return ($_POST['hp_website'] ?? '') !== '';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="cs"><head><meta charset="utf-8"><title>403</title></head>'
           . '<body><p>Neplatný bezpečnostní token. Vraťte se zpět a akci opakujte.</p></body></html>';
        exit;
    }
}
