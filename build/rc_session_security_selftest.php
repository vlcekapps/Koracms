<?php

declare(strict_types=1);

require_once __DIR__ . '/unit_test_bootstrap.php';
require_once dirname(__DIR__) . '/lib/session_security.php';

/** @return array<string,mixed> */
function rcSessionSecuritySnapshot(): array
{
    return $_SESSION;
}

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$account = ['id' => 8, 'email' => 'account@example.test', 'password' => 'verified-hash',
    'role' => 'admin', 'is_superadmin' => 1, 'is_confirmed' => 1, 'totp_secret' => 'EXAMPLE'];
$session = ['cms_user_id' => 8, 'cms_auth_fingerprint' => userSessionFingerprint($account)];
$pending = ['2fa_pending_user_id' => 8, '2fa_pending_issued_at' => 1000,
    '2fa_pending_fingerprint' => userSessionFingerprint($account)];
$check(authenticatedAccountMatchesSession($session, $account), 'Unchanged account must remain authenticated.');
$check(pendingTwoFactorSessionIsValid($pending, $account, 1599), '2FA must work within ten minutes.');
$check(!pendingTwoFactorSessionIsValid($pending, $account, 1600), '2FA must expire at ten minutes.');
$check(!pendingTwoFactorSessionIsValid($pending, $account, 999), 'Future-issued 2FA must fail.');
$check(!authenticatedAccountMatchesSession($session, null), 'Deleted account must invalidate authentication.');
$check(!pendingTwoFactorSessionIsValid($pending, null, 1200), 'Deleted account must invalidate 2FA.');
$check(!authenticatedAccountMatchesSession(['cms_user_id' => 8], $account), 'Unbound legacy session must require login.');
$check(!pendingTwoFactorSessionIsValid(['2fa_pending_user_id' => 8], $account, 1200), 'Unbound pending login must fail.');
foreach (['id' => 9, 'email' => 'changed@example.test', 'password' => 'reset-hash', 'role' => 'public',
    'is_superadmin' => 0, 'is_confirmed' => 0, 'totp_secret' => 'CHANGED'] as $field => $value) {
    $changed = array_replace($account, [$field => $value]);
    $check(!authenticatedAccountMatchesSession($session, $changed), 'Session failed to invalidate: ' . $field);
    $check(!pendingTwoFactorSessionIsValid($pending, $changed, 1200), 'Pending login failed to invalidate: ' . $field);
}
$check(authenticatedAccountMatchesSession($session, $account + ['nickname' => 'New name']), 'Display name must not force login.');
$check(userSessionDisplayName($account) === 'account@example.test', 'Display-name fallback failed.');
$check(legacyUserSessionFingerprint('a', 'old') !== legacyUserSessionFingerprint('a', 'new'), 'Legacy password reset must invalidate session.');
$_SESSION = $pending + ['2fa_pending_redirect' => '/admin/index.php', 'unrelated' => 'keep'];
clearPendingTwoFactorSession();
$check(rcSessionSecuritySnapshot() === ['unrelated' => 'keep'], 'Pending cleanup must remove every pending field only.');

$legacyAccount = $account;
unset($legacyAccount['is_confirmed']);
$check(authenticatedAccountMatchesSession($session, $legacyAccount), 'Missing legacy confirmation must use the same session fingerprint.');
$check(pendingTwoFactorSessionIsValid($pending, $legacyAccount, 1200), 'Legacy pending 2FA must accept a missing confirmation column.');
foreach (['admin', 'collaborator', 'author', 'editor', 'moderator', 'booking_manager'] as $role) {
    $staff = array_replace($legacyAccount, ['role' => $role]);
    $check(userSessionAccountIsConfirmed($staff), 'Legacy staff must retain access: ' . $role);
    $check(userSessionFingerprint($staff) === userSessionFingerprint($staff + ['is_confirmed' => 1]), 'Migration default must preserve fingerprint: ' . $role);
}
foreach ([0, '0', null, false] as $confirmation) {
    $unconfirmed = array_replace($account, ['is_confirmed' => $confirmation]);
    $check(!userSessionAccountIsConfirmed($unconfirmed), 'Explicit unconfirmed value must never use legacy fallback.');
    $check(!authenticatedAccountMatchesSession($session, $unconfirmed), 'Explicit unconfirmed account must invalidate session.');
    $check(!pendingTwoFactorSessionIsValid($pending, $unconfirmed, 1200), 'Explicit unconfirmed account must invalidate 2FA.');
}
foreach (['public', 'unknown', '', null] as $role) {
    $blocked = array_replace($legacyAccount, ['role' => $role]);
    $check(!userSessionAccountIsConfirmed($blocked), 'Missing confirmation must not admit public/unknown roles.');
    $check(!authenticatedAccountMatchesSession(['cms_user_id' => 8, 'cms_auth_fingerprint' => userSessionFingerprint($blocked)], $blocked), 'Even a matching fingerprint must not admit an unconfirmed role.');
}
unset($legacyAccount['role']);
$check(authenticatedAccountMatchesSession($session, $legacyAccount), 'Pre-role superadmin session must match migration normalization.');
$check(pendingTwoFactorSessionIsValid($pending, $legacyAccount, 1200), 'Pre-role superadmin must pass pending 2FA validation.');
foreach ([0 => 'collaborator', 1 => 'admin'] as $superadmin => $expectedRole) {
    $staff = array_replace($legacyAccount, ['is_superadmin' => $superadmin]);
    $migrated = $staff + ['role' => $expectedRole, 'is_confirmed' => 1];
    $check(userSessionAccountRole($staff) === $expectedRole, 'Pre-role account must match migration role.');
    $check(userSessionAccountIsConfirmed($staff), 'Pre-role staff account must remain usable.');
    $check(userSessionFingerprint($staff) === userSessionFingerprint($migrated), 'Adding legacy schema defaults must preserve fingerprint.');
}
unset($legacyAccount['is_superadmin']);
$check(!userSessionAccountIsConfirmed($legacyAccount), 'Missing role and staff marker must fail closed.');
$check(userSessionAccountIsConfirmed(array_replace($account, ['role' => 'public'])), 'Explicitly confirmed public login must remain valid.');

echo 'RC session security: ' . $checks . " checks passed.\n";

$process = proc_open(
    [PHP_BINARY, __DIR__ . '/rc_legacy_auth_selftest.php'],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);
if (!is_resource($process)) {
    throw new RuntimeException('Cannot start isolated legacy auth regression.');
}
fclose($pipes[0]);
$output = stream_get_contents($pipes[1]);
$errorOutput = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);
if ($exitCode !== 0) {
    throw new RuntimeException('Legacy auth regression failed: ' . $output . $errorOutput);
}
echo $output;
