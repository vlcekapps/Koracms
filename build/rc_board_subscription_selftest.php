<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/db.php';

final class RcBoardSubscriptionPdo extends PDO
{
    public string $failSql = '';

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->failSql !== '' && str_starts_with($query, $this->failSql)) {
            throw new PDOException('Injected subscription write failure');
        }
        return parent::prepare($query, $options);
    }
}

$dbHost = (string)$GLOBALS['server'];
$dbName = (string)$GLOBALS['database'];
$pdo = new RcBoardSubscriptionPdo("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", (string)$GLOBALS['user'], (string)$GLOBALS['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($label);
    }
    $checks++;
};
// Connection-local tables isolate all writes from real subscribers and mail.
$pdo->exec('CREATE TEMPORARY TABLE cms_board_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) UNIQUE NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL, confirmed TINYINT NOT NULL DEFAULT 0,
    all_categories TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, confirmed_at DATETIME NULL
) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE cms_board_subscriber_categories (
    subscriber_id INT NOT NULL, category_id INT NOT NULL,
    PRIMARY KEY (subscriber_id, category_id)
) ENGINE=InnoDB');
$snapshot = static function () use ($pdo): array {
    return [
        $pdo->query('SELECT * FROM cms_board_subscribers ORDER BY id')->fetchAll(),
        $pdo->query('SELECT * FROM cms_board_subscriber_categories ORDER BY subscriber_id, category_id')->fetchAll(),
    ];
};
try {
    foreach ([false, true] as $stringify) {
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, $stringify);
        $pdo->exec('DELETE FROM cms_board_subscriber_categories');
        $pdo->exec('DELETE FROM cms_board_subscribers');
        $subscriber = prepareBoardSubscription($pdo, 'board-test@example.test', [3, 8]);
        $check($subscriber['id'] > 0 && $subscriber['confirmed'] === 0, 'New subscription must be pending.');
        $check(preg_match('/^[a-f0-9]{64}$/D', $subscriber['token']) === 1, 'Subscription token must be random hex.');
        $initial = $snapshot();
        $check(count($initial[1]) === 2 && (int)$initial[0][0]['all_categories'] === 0, 'Initial scope must be saved.');
        $repeat = prepareBoardSubscription($pdo, 'board-test@example.test', []);
        $check($repeat === $subscriber, 'Repeat must return the persisted token and state.');
        $check($snapshot() === $initial, 'Repeat must not replace token, dates or category scope.');
        $pdo->prepare('UPDATE cms_board_subscribers SET confirmed = 1, confirmed_at = NOW() WHERE token = ?')
            ->execute([$subscriber['token']]);
        $confirmed = $snapshot();
        $repeat = prepareBoardSubscription($pdo, 'board-test@example.test', [9]);
        $check($repeat['confirmed'] === 1, 'Repeat must not demote a confirmed subscriber.');
        $check($snapshot() === $confirmed, 'Confirmed data and scope must remain unchanged.');

        $pdo->failSql = 'INSERT INTO cms_board_subscriber_categories';
        try {
            prepareBoardSubscription($pdo, 'board-failed@example.test', [5]);
            $check(false, 'Category failure must propagate.');
        } catch (PDOException $e) {
            $check($snapshot() === $confirmed, 'Category failure must roll back the parent insert.');
            $check(!$pdo->inTransaction(), 'Failed creation must close the transaction.');
        } finally {
            $pdo->failSql = '';
        }
        try {
            prepareBoardSubscription($pdo, 'board-partial@example.test', [5, 5]);
            $check(false, 'Second category insert failure must propagate.');
        } catch (PDOException $e) {
            $check($snapshot() === $confirmed, 'Second insert failure must roll back the first link and parent.');
        }
        $pdo->failSql = 'DELETE FROM cms_board_subscribers';
        try {
            removeBoardSubscription($pdo, $subscriber['token']);
            $check(false, 'Final delete failure must propagate.');
        } catch (PDOException $e) {
            $check($snapshot() === $confirmed, 'Final delete failure must restore the category links.');
            $check(!$pdo->inTransaction(), 'Failed removal must close the transaction.');
        } finally {
            $pdo->failSql = '';
        }
        $check(!removeBoardSubscription($pdo, str_repeat('0', 64)), 'Unknown token must not remove a subscription.');
        $check($snapshot() === $confirmed, 'Unknown token must preserve every row.');
        $other = prepareBoardSubscription($pdo, 'board-other@example.test', []);
        $check(removeBoardSubscription($pdo, $subscriber['token']), 'Persisted token must unsubscribe.');
        $remaining = $snapshot();
        $check(count($remaining[0]) === 1 && (int)$remaining[0][0]['id'] === $other['id'], 'Removal must preserve other subscribers.');
        $check($remaining[1] === [], 'Removal must clean the selected category links.');
        $check(!removeBoardSubscription($pdo, $subscriber['token']), 'Repeated removal must be a safe no-op.');
    }
} finally {
    $pdo->exec('DROP TEMPORARY TABLE cms_board_subscriber_categories');
    $pdo->exec('DROP TEMPORARY TABLE cms_board_subscribers');
}
echo 'RC board subscriptions: ' . $checks . " isolated MySQL checks passed.\n";
