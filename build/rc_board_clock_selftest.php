<?php

declare(strict_types=1);

namespace KoraBoardClockTest;

use PDO;
use RuntimeException;
use Throwable;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$checks = 0;
$timezone = date_default_timezone_get();
function same(mixed $actual, mixed $expected, string $label): void
{
    $GLOBALS['checks']++;
    if ($actual !== $expected) {
        throw new RuntimeException($label);
    }
}
try {
    $source = (string)file_get_contents(dirname(__DIR__) . '/lib/presentation.php');
    foreach (['slugify', 'boardSlug', 'boardPublicVisibilitySql', 'boardIsPubliclyReachable', 'shouldSendBoardPublicationNotice'] as $name) {
        if (preg_match('/^function ' . $name . '\b.*?^\}/ms', $source, $match) !== 1) {
            throw new RuntimeException('Missing production clock function ' . $name);
        }
        eval('namespace ' . __NAMESPACE__ . '; ' . $match[0]);
    }
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_STRINGIFY_FETCHES => getenv('KORA_RC_STRINGIFY_FETCHES') === '1']);
    $clock = '2026-10-19 00:30:00';
    $pdo->sqliteCreateFunction('NOW', static fn (): string => $clock);
    $pdo->sqliteCreateFunction('CURDATE', static fn (): string => substr($clock, 0, 10));
    $pdo->exec('CREATE TABLE board (slug TEXT, status TEXT, is_published INT, deleted_at TEXT, posted_date TEXT, publish_at TEXT, unpublish_at TEXT)');
    $public = ['slug' => 'owned', 'status' => 'published', 'is_published' => 1, 'deleted_at' => null,
        'posted_date' => '2026-10-19', 'publish_at' => '2026-10-19 00:15:00', 'unpublish_at' => '2026-10-19 01:00:00'];
    foreach (['UTC', 'Pacific/Kiritimati', 'Etc/GMT+12'] as $zone) {
        date_default_timezone_set($zone);
        foreach ([[], ['posted_date' => '2026-10-20'], ['publish_at' => $clock], ['publish_at' => '2026-10-19 00:31:00'],
            ['unpublish_at' => $clock], ['unpublish_at' => '2026-10-18 23:00:00'], ['status' => 'draft'],
            ['is_published' => 0], ['deleted_at' => $clock], ['publish_at' => null, 'unpublish_at' => null]] as $state) {
            $document = array_replace($public, $state);
            $pdo->exec('DELETE FROM board');
            $pdo->prepare('INSERT INTO board VALUES (?,?,?,?,?,?,?)')->execute(array_values($document));
            $expected = (int)$pdo->query('SELECT COUNT(*) FROM board WHERE ' . boardPublicVisibilitySql())->fetchColumn() === 1;
            same(boardIsPubliclyReachable($document, $clock), $expected, 'PHP and SQL visibility disagree under ' . $zone);
        }
        same(boardIsPubliclyReachable($public, $clock), true, 'Database midnight ignores PHP timezone');
        same(shouldSendBoardPublicationNotice(array_replace($public, ['is_published' => 0]), $public, $clock), true, 'First publication uses database clock');
        same(shouldSendBoardPublicationNotice($public, $public, $clock), false, 'Edit cannot resend a publication notice');
        same(boardIsPubliclyReachable(array_replace($public, ['slug' => '']), $clock), false, 'Empty slug remains nonpublic');
        same(boardIsPubliclyReachable($public + ['removal_date' => '2000-01-01'], $clock), true, 'Public archive remains reachable');
    }
    echo 'Board clock parity OK (' . $checks . ' checks)' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Board clock parity FAILED: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    date_default_timezone_set($timezone);
}
