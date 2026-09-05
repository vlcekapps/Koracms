<?php

declare(strict_types=1);

namespace KoraLegacyAuthSelfTest;

use PDO;
use RuntimeException;
use Throwable;

// Execute production login/session/2FA code against PDO SQLite memory only.
// No application bootstrap, real credentials, session files, HTTP or live schema changes.
define('BASE_URL', '');

final class Redirect extends RuntimeException
{
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $GLOBALS['legacyChecks']++;
}

function source(string $path): string
{
    $contents = file_get_contents(dirname(__DIR__) . '/' . $path);
    if (!is_string($contents)) {
        throw new RuntimeException('Cannot read ' . $path);
    }
    return $contents;
}

function evaluate(string $code): void
{
    eval('namespace ' . __NAMESPACE__ . '; use \\PDO; use \\PDOException; use \\InvalidArgumentException; ' . $code);
}

function loadFunction(string $path, string $name): void
{
    if (preg_match('/^function ' . preg_quote($name, '/') . '\\b.*?^\\}/ms', source($path), $match) !== 1) {
        throw new RuntimeException('Cannot extract ' . $name);
    }
    evaluate($match[0]);
}

function runHandler(string $path): string
{
    $code = preg_replace('/^require_once [^\r\n]+;\R/m', '', source($path));
    ob_start();
    try {
        evaluate('?>' . $code);
        return '';
    } catch (Redirect $response) {
        return $response->getMessage();
    } finally {
        ob_end_clean();
    }
}

function db_connect(): PDO
{
    return $GLOBALS['legacyDb'];
}

function header(string $value): void
{
    if (str_starts_with($value, 'Location: ')) {
        throw new Redirect(substr($value, 10));
    }
}

function session_regenerate_id(bool $deleteOldSession = false): bool
{
    check($deleteOldSession, 'Authentication must retire the previous session ID.');
    return true;
}

function sleep(int $seconds): int
{
    return 0;
}

function rateLimit(mixed ...$args): void
{
}

function rateLimitSubject(mixed ...$args): void
{
}

function verifyCsrf(): void
{
    check(($_POST['csrf_token'] ?? '') === 'fixture-csrf', 'Fixture must submit CSRF.');
}

function csrfToken(): string
{
    return 'fixture-csrf';
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function adminLoginStylesheetTag(): string
{
    return '';
}

function internalRedirectTarget(string $target, string $default = ''): string
{
    return str_starts_with($target, '/') && !str_starts_with($target, '//') ? $target : $default;
}

/** @return null */
function adminRouteCapability()
{
    return null;
}

/** @return null */
function adminRouteModuleRequirement()
{
    return null;
}

function getSetting(string $key, string $default = ''): string
{
    return $default;
}

function checkMaintenanceMode(): void
{
}

function publicRegistrationEnabled(): bool
{
    return false;
}

/** @param array<string,mixed> $args */
function renderPublicPage(array $args): void
{
}

/** @param array<string,mixed> $row */
function fixture(array $row): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $columns = ['id INTEGER PRIMARY KEY', 'email TEXT', 'password TEXT', 'first_name TEXT',
        'last_name TEXT', 'nickname TEXT', 'is_superadmin INTEGER', 'totp_secret TEXT'];
    if (array_key_exists('role', $row)) {
        $columns[] = 'role TEXT';
    }
    if (array_key_exists('is_confirmed', $row)) {
        $columns[] = 'is_confirmed INTEGER';
    }
    $pdo->exec('CREATE TABLE cms_users (' . implode(', ', $columns) . ')');
    $pdo->prepare('INSERT INTO cms_users (' . implode(', ', array_keys($row)) . ') VALUES ('
        . implode(', ', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
    $GLOBALS['legacyDb'] = $pdo;
    $_SESSION = [];
    $_GET = [];
    $_POST = ['email' => 'legacy@example.test', 'heslo' => 'fixture-password', 'password' => 'fixture-password',
        'csrf_token' => 'fixture-csrf', 'redirect' => '/migrate.php'];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/migrate.php';
    return $pdo;
}

$completion = new class () {
    public bool $completed = false;
};
register_shutdown_function(static function () use ($completion): void {
    if (!$completion->completed) {
        fwrite(STDERR, "Legacy auth regression did not complete all checks.\n");
        exit(1);
    }
});

try {
    $GLOBALS['legacyChecks'] = 0;
    foreach (['roleDefinitions', 'normalizeUserRole', 'isLoggedIn', 'isSuperAdmin', 'currentUserRole',
        'isPublicUser', 'loginUser', 'adminLoginRedirectTarget', 'requireLogin', 'requireSuperAdmin'] as $name) {
        loadFunction('auth.php', $name);
    }
    evaluate(substr(source('lib/session_security.php'), 5));
    foreach (['base32Decode', 'totpCalculate', 'totpVerify'] as $name) {
        loadFunction('lib/totp.php', $name);
    }
    set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
        throw new \ErrorException($message, 0, $level, $file, $line);
    });

    $base = ['id' => 8, 'email' => 'legacy@example.test', 'password' => password_hash('fixture-password', PASSWORD_BCRYPT, ['cost' => 4]),
        'first_name' => '', 'last_name' => '', 'nickname' => '', 'is_superadmin' => 1, 'totp_secret' => ''];
    foreach ([true, false] as $hasRole) {
        foreach ([0, 1] as $superadmin) {
            $expectedRole = $superadmin === 1 ? 'admin' : 'collaborator';
            $row = array_replace($base, ['is_superadmin' => $superadmin]);
            if ($hasRole) {
                $row['role'] = $expectedRole;
            }
            $pdo = fixture($row);
            $loaded = $pdo->query('SELECT * FROM cms_users')->fetch();
            check(!array_key_exists('is_confirmed', $loaded), 'Actual PDO row must omit the legacy column.');
            check(runHandler('admin/login.php') === '/migrate.php', 'Legacy password login must reach its redirect.');
            check(isLoggedIn(), 'Legacy password login must establish authentication.');
            refreshAuthenticatedSession();
            check(isLoggedIn() && currentUserRole() === $expectedRole, 'Next request must retain the normalized legacy role.');
            if ($superadmin === 1) {
                requireSuperAdmin();
                check(true, 'Production migration authorization accepts legacy superadmin.');
            } else {
                check(!isSuperAdmin(), 'Historical staff must not become superadmin.');
            }
            $fingerprint = $_SESSION['cms_auth_fingerprint'];
            // Only this in-memory fixture changes schema, never the application database.
            $pdo->exec('ALTER TABLE cms_users ADD COLUMN is_confirmed INTEGER NOT NULL DEFAULT 1');
            if (!$hasRole) {
                $pdo->exec("ALTER TABLE cms_users ADD COLUMN role TEXT NOT NULL DEFAULT 'collaborator'");
                $pdo->exec("UPDATE cms_users SET role = 'admin' WHERE is_superadmin = 1");
            }
            refreshAuthenticatedSession();
            check(isLoggedIn() && $_SESSION['cms_auth_fingerprint'] === $fingerprint, 'Migration defaults must not strand a valid session.');
            $pdo->exec('UPDATE cms_users SET is_confirmed = 0');
            refreshAuthenticatedSession();
            check(!isLoggedIn(), 'Explicit revocation after migration must invalidate the session.');
        }
    }

    $secret = 'JBSWY3DPEHPK3PXP';
    foreach ([true, false] as $hasRole) {
        foreach ([false, true] as $migrateBeforeCode) {
            foreach ([false, true] as $revoke) {
                $row = array_replace($base, ['totp_secret' => $secret]);
                if ($hasRole) {
                    $row['role'] = 'admin';
                }
                $pdo = fixture($row);
                check(runHandler('admin/login.php') === '/admin/login_2fa.php', 'Legacy TOTP must require its second factor.');
                check(!isLoggedIn() && !empty($_SESSION['2fa_pending_fingerprint']), 'Password step alone must not authenticate.');
                // The schema upgrade may happen between password and TOTP verification.
                if ($migrateBeforeCode || $revoke) {
                    $pdo->exec('ALTER TABLE cms_users ADD COLUMN is_confirmed INTEGER NOT NULL DEFAULT 1');
                    if (!$hasRole) {
                        $pdo->exec("ALTER TABLE cms_users ADD COLUMN role TEXT NOT NULL DEFAULT 'admin'");
                    }
                }
                if ($revoke) {
                    $pdo->exec('UPDATE cms_users SET is_confirmed = 0');
                }
                $_POST = ['csrf_token' => 'fixture-csrf', 'totp_code' => totpCalculate($secret)];
                $redirect = runHandler('admin/login_2fa.php');
                check(isLoggedIn() === !$revoke, 'TOTP must honor both legacy normalization and explicit revocation.');
                check(!isset($_SESSION['2fa_pending_user_id']), 'Finished or revoked TOTP must clear pending state.');
                if (!$revoke) {
                    check($redirect === '/migrate.php', 'Successful legacy TOTP must retain the redirect.');
                    refreshAuthenticatedSession();
                    check(isLoggedIn() && currentUserRole() === 'admin', 'Legacy TOTP authentication survives the next request.');
                }
            }
        }
    }

    foreach ([['role' => 'public'], ['role' => 'unknown'], ['role' => null], ['role' => ''],
        ['role' => 'admin', 'is_confirmed' => 0], ['role' => 'admin', 'is_confirmed' => null]] as $blocked) {
        fixture(array_replace($base, $blocked));
        runHandler('admin/login.php');
        check(!isLoggedIn() && !isset($_SESSION['2fa_pending_user_id']), 'Invalid legacy account must not enter authentication.');
    }
    fixture($base + ['role' => 'admin']);
    $_POST['heslo'] = 'incorrect-password';
    runHandler('admin/login.php');
    check(!isLoggedIn(), 'Legacy compatibility must still verify the password.');

    foreach ([[], ['is_confirmed' => 0], ['is_confirmed' => null], ['is_confirmed' => 1]] as $confirmation) {
        fixture(array_replace($base, ['role' => 'public', 'is_superadmin' => 0], $confirmation));
        runHandler('public_login.php');
        check(isLoggedIn() === (($confirmation['is_confirmed'] ?? null) === 1), 'Public login must require explicit confirmation.');
    }
    $completion->completed = true;
    echo 'RC legacy auth PDO: ' . $GLOBALS['legacyChecks'] . " checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
