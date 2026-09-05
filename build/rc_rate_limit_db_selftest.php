<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/db.php';

$pdo = db_connect();
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
// A connection-local temporary table shadows the real counters without modifying them.
$pdo->exec('CREATE TEMPORARY TABLE cms_rate_limit (id VARCHAR(64) PRIMARY KEY, attempts INT NOT NULL,
    window_start DATETIME NOT NULL, expires_at DATETIME NULL)');
try {
    $pdo->exec("INSERT INTO cms_rate_limit VALUES ('long', 9, DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 22 HOUR))");
    $check(rateLimitRecordAttempt($pdo, 'short', 60) === 1, 'First attempt must count once.');
    $check((int)$pdo->query("SELECT attempts FROM cms_rate_limit WHERE id = 'long'")->fetchColumn() === 9, 'Short attempt erased a long counter.');
    $check(rateLimitRecordAttempt($pdo, 'short', 60) === 2, 'Repeated attempt must count.');
    $check(rateLimitRecordAttempt($pdo, 'long', 86400) === 10, 'Long window must remain enforced.');
    $pdo->exec("UPDATE cms_rate_limit SET window_start = DATE_SUB(NOW(), INTERVAL 61 SECOND), expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE id = 'short'");
    $check(rateLimitRecordAttempt($pdo, 'short', 60) === 1, 'Expired own window must reset.');
    $check((int)$pdo->query("SELECT expires_at > NOW() FROM cms_rate_limit WHERE id = 'short'")->fetchColumn() === 1, 'Expiry must be refreshed.');
    $pdo->exec('DELETE FROM cms_rate_limit WHERE expires_at <= NOW()');
    $check((int)$pdo->query('SELECT COUNT(*) FROM cms_rate_limit')->fetchColumn() === 2, 'Cleanup removed active windows.');
    $pdo->exec('ALTER TABLE cms_rate_limit DROP COLUMN expires_at');
    $check(rateLimitRecordAttempt($pdo, 'legacy', 60) === 1, 'Pre-migration fallback must count.');
    $check(rateLimitRecordAttempt($pdo, 'legacy', 60) === 2, 'Pre-migration fallback must enforce repeats.');
    $check((int)$pdo->query("SELECT attempts FROM cms_rate_limit WHERE id = 'long'")->fetchColumn() === 10, 'Legacy fallback erased another key.');
} finally {
    $pdo->exec('DROP TEMPORARY TABLE cms_rate_limit');
}
echo 'RC rate limit: ' . $checks . " isolated MySQL checks passed.\n";
