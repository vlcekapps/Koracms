<?php

/** @param array<string,mixed> $account */
function userSessionAccountRole(array $account): string
{
    if (array_key_exists('role', $account)) {
        return normalizeUserRole(is_string($account['role']) ? $account['role'] : null);
    }

    // Before roles existed, migrate.php maps these staff accounts to admin/collaborator.
    if (!in_array($account['is_superadmin'] ?? null, [0, 1, '0', '1', false, true], true)) {
        return 'public';
    }
    return !empty($account['is_superadmin']) ? 'admin' : 'collaborator';
}

/** @param array<string,mixed> $account */
function userSessionAccountIsConfirmed(array $account): bool
{
    if (array_key_exists('is_confirmed', $account)) {
        return !empty($account['is_confirmed']);
    }

    // A missing legacy column is not the same as an explicitly unconfirmed account.
    return userSessionAccountRole($account) !== 'public';
}

/** @param array<string,mixed> $account */
function userSessionFingerprint(array $account): string
{
    // Bind authentication to the exact credentials checked in the password step.
    return hash('sha256', serialize([
        (int)($account['id'] ?? 0), (string)($account['email'] ?? ''),
        (string)($account['password'] ?? ''), userSessionAccountRole($account),
        (bool)($account['is_superadmin'] ?? false), userSessionAccountIsConfirmed($account),
        (string)($account['totp_secret'] ?? ''),
    ]));
}

function legacyUserSessionFingerprint(string $email, string $passwordHash): string
{
    return hash('sha256', serialize(['legacy-admin', $email, $passwordHash]));
}

/** @param array<string,mixed> $account */
function userSessionDisplayName(array $account): string
{
    $name = trim((string)($account['nickname'] ?? ''));
    if ($name === '') {
        $name = trim((string)($account['first_name'] ?? '') . ' ' . (string)($account['last_name'] ?? ''));
    }
    return $name !== '' ? $name : (string)($account['email'] ?? '');
}

function clearPendingTwoFactorSession(): void
{
    foreach (['user_id', 'email', 'superadmin', 'role', 'name', 'redirect', 'issued_at', 'fingerprint'] as $key) {
        unset($_SESSION['2fa_pending_' . $key]);
    }
}

/**
 * @param array<string,mixed> $session
 * @param array<string,mixed>|null $account
 */
function authenticatedAccountMatchesSession(array $session, ?array $account): bool
{
    $fingerprint = (string)($session['cms_auth_fingerprint'] ?? '');
    return $account !== null && userSessionAccountIsConfirmed($account)
        && (int)($session['cms_user_id'] ?? -1) === (int)$account['id']
        && $fingerprint !== '' && hash_equals(userSessionFingerprint($account), $fingerprint);
}

/**
 * @param array<string,mixed> $session
 * @param array<string,mixed>|null $account
 */
function pendingTwoFactorSessionIsValid(array $session, ?array $account, int $now): bool
{
    $issuedAt = (int)($session['2fa_pending_issued_at'] ?? 0);
    $fingerprint = (string)($session['2fa_pending_fingerprint'] ?? '');
    return $account !== null
        && $issuedAt > 0 && $issuedAt <= $now && $now - $issuedAt < 600
        && (int)($session['2fa_pending_user_id'] ?? 0) === (int)$account['id']
        && userSessionAccountIsConfirmed($account) && !empty($account['totp_secret'])
        && userSessionAccountRole($account) !== 'public'
        && $fingerprint !== '' && hash_equals(userSessionFingerprint($account), $fingerprint);
}

function refreshAuthenticatedSession(): void
{
    if (!isLoggedIn()) {
        return;
    }
    $fingerprint = (string)($_SESSION['cms_auth_fingerprint'] ?? '');
    $accountId = (int)($_SESSION['cms_user_id'] ?? -1);
    try {
        $stmt = db_connect()->prepare('SELECT * FROM cms_users WHERE id = ?');
        $stmt->execute([$accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (authenticatedAccountMatchesSession($_SESSION, $account ?: null)) {
            $_SESSION['cms_user_email'] = (string)$account['email'];
            $_SESSION['cms_user_name'] = userSessionDisplayName($account);
            $_SESSION['cms_user_role'] = userSessionAccountRole($account);
            $_SESSION['cms_superadmin'] = (bool)$account['is_superadmin'];
            return;
        }
    } catch (PDOException $e) {
        // The settings account is valid only before cms_users exists, not on general SQL failures.
        if ((string)$e->getCode() === '42S02' && $accountId === 0 && $fingerprint !== '') {
            $email = getSetting('admin_email', '');
            $passwordHash = getSetting('admin_password', '');
            if ($passwordHash !== '' && hash_equals(legacyUserSessionFingerprint($email, $passwordHash), $fingerprint)) {
                return;
            }
        }
    }
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['auth_session_notice'] = 'Platnost přihlášení skončila nebo se změnilo zabezpečení účtu. Přihlaste se prosím znovu.';
}
