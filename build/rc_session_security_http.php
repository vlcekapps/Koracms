<?php

declare(strict_types=1);

/** @return list<string> */
function rcSessionSecurityHttpChecks(PDO $pdo, string $baseUrl): array
{
    $issues = [];
    $ids = [];
    $secret = totpGenerateSecret();
    $create = $pdo->prepare("INSERT INTO cms_users
        (email, password, first_name, last_name, role, is_superadmin, is_confirmed, totp_secret)
        VALUES (?, ?, 'RC', 'Session', 'admin', 1, 1, ?)");
    $protectedUrl = $baseUrl . BASE_URL . '/admin/index.php';
    try {
        foreach (['unchanged', 'role', 'password', 'confirmation', 'deleted', 'missing_binding'] as $change) {
            $create->execute(['rc-session-' . bin2hex(random_bytes(6)) . '@example.test',
                password_hash('RC-session-test-123!', PASSWORD_DEFAULT), '']);
            $id = (int)$pdo->lastInsertId();
            $ids[] = $id;
            $data = ['cms_logged_in' => true, 'cms_user_id' => $id,
                'cms_superadmin' => true, 'cms_user_role' => 'admin'];
            if ($change === 'missing_binding') {
                $data['cms_auth_fingerprint'] = '';
            }
            $session = koraPrimeTestSession($data);
            if ($change === 'role') {
                $pdo->prepare("UPDATE cms_users SET role = 'public', is_superadmin = 0 WHERE id = ?")->execute([$id]);
            } elseif ($change === 'password') {
                $pdo->prepare('UPDATE cms_users SET password = ? WHERE id = ?')
                    ->execute([password_hash('Changed-session-test-123!', PASSWORD_DEFAULT), $id]);
            } elseif ($change === 'confirmation') {
                $pdo->prepare('UPDATE cms_users SET is_confirmed = 0 WHERE id = ?')->execute([$id]);
            } elseif ($change === 'deleted') {
                $pdo->prepare('DELETE FROM cms_users WHERE id = ?')->execute([$id]);
            }
            $response = fetchUrl($protectedUrl, $session['cookie'], 0);
            $expected = $change === 'unchanged' ? 200 : 302;
            if (httpIntegrationStatusCode($response) !== $expected
                || ($expected === 302 && !str_contains(responseLocationHeaderValue($response['headers']), '/admin/login.php'))) {
                $issues[] = 'Session ' . $change . ' did not enforce current account credentials.';
            }
        }

        foreach (['valid', 'expired', 'password_changed', 'role_changed', 'deleted'] as $change) {
            $create->execute(['rc-totp-' . bin2hex(random_bytes(6)) . '@example.test',
                password_hash('RC-totp-test-123!', PASSWORD_DEFAULT), $secret]);
            $id = (int)$pdo->lastInsertId();
            $ids[] = $id;
            $session = koraPrimeTestSession([
                '2fa_pending_user_id' => $id,
                '2fa_pending_redirect' => BASE_URL . '/admin/index.php',
                '2fa_pending_issued_at' => time() - ($change === 'expired' ? 601 : 1),
            ]);
            if ($change === 'password_changed') {
                $pdo->prepare('UPDATE cms_users SET password = ? WHERE id = ?')
                    ->execute([password_hash('Changed-totp-test-123!', PASSWORD_DEFAULT), $id]);
            } elseif ($change === 'role_changed') {
                $pdo->prepare("UPDATE cms_users SET role = 'public', is_superadmin = 0 WHERE id = ?")->execute([$id]);
            } elseif ($change === 'deleted') {
                $pdo->prepare('DELETE FROM cms_users WHERE id = ?')->execute([$id]);
            }
            $url = $baseUrl . BASE_URL . '/admin/login_2fa.php';
            $response = postUrl($url, ['csrf_token' => $session['csrf'], 'totp_code' => totpCalculate($secret)], $session['cookie'], 0);
            $location = responseLocationHeaderValue($response['headers']);
            if (httpIntegrationStatusCode($response) !== 302
                || !str_contains($location, $change === 'valid' ? '/admin/index.php' : '/admin/login.php')) {
                $issues[] = 'Pending 2FA ' . $change . ' did not enforce expiry/current credentials.';
            }
            $cookie = responseMergeCookies($response['headers'], $session['cookie']);
            $after = fetchUrl($protectedUrl, $cookie, 0);
            if (httpIntegrationStatusCode($after) !== ($change === 'valid' ? 200 : 302)) {
                $issues[] = 'Pending 2FA ' . $change . ' left an incorrect authenticated state.';
            }
        }
    } finally {
        foreach ($ids as $id) {
            $pdo->prepare('DELETE FROM cms_users WHERE id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM cms_rate_limit WHERE id = ?')
                ->execute([rateLimitKey('login_2fa_user', 'subject:' . $id)]);
        }
    }
    return $issues;
}
