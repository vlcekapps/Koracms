<?php

declare(strict_types=1);

// No config.php, db.php or application database: exercise the production SQL
// against an isolated legacy schema, translating only MySQL date/index syntax.
$projectRoot = dirname(__DIR__);
$checks = 0;

function rateLimitExpirySelfTestSame(mixed $actual, mixed $expected, string $label): void
{
    global $checks;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
    $checks++;
}

function rateLimitExpirySelfTestSql(string $source, string $prefix): string
{
    $matched = preg_match_all('/"(' . preg_quote($prefix, '/') . '\b[^"$]*)"/s', $source, $matches);
    if ($matched !== 1) {
        throw new RuntimeException('Expected exactly one production SQL statement: ' . $prefix);
    }
    $sql = preg_replace('/\s+/', ' ', $matches[1][0]) ?? '';
    return preg_replace_callback(
        '/DATE_(ADD|SUB)\((window_start|NOW\(\)), INTERVAL (\d+) (SECOND|MINUTE|HOUR|DAY)\)/i',
        static fn (array $match): string => "datetime(" . $match[2] . ", '"
            . (strtoupper($match[1]) === 'ADD' ? '+' : '-') . $match[3] . ' ' . strtolower($match[4]) . "')",
        $sql
    ) ?? '';
}

/** @return list<array<string,mixed>> */
function rateLimitExpirySelfTestRows(PDO $pdo): array
{
    return $pdo->query('SELECT id, attempts, window_start, expires_at FROM cms_rate_limit ORDER BY id')
        ->fetchAll(PDO::FETCH_ASSOC);
}

try {
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('The isolated rate-limit expiry self-test requires pdo_sqlite.');
    }
    $migrationSource = file_get_contents($projectRoot . '/migrate.php');
    $cronSource = file_get_contents($projectRoot . '/cron.php');
    if (!is_string($migrationSource) || !is_string($cronSource)) {
        throw new RuntimeException('Cannot read the rate-limit migration or cron source.');
    }
    $addColumnSql = rateLimitExpirySelfTestSql($migrationSource, 'ALTER TABLE cms_rate_limit ADD COLUMN');
    $addIndexSql = rateLimitExpirySelfTestSql($migrationSource, 'ALTER TABLE cms_rate_limit ADD INDEX');
    $backfillSql = rateLimitExpirySelfTestSql($migrationSource, 'UPDATE cms_rate_limit');
    $cleanupSql = rateLimitExpirySelfTestSql($cronSource, 'DELETE FROM cms_rate_limit');
    $addIndexSql = preg_replace(
        '/^ALTER TABLE (\w+) ADD INDEX (\w+) (\([^)]+\))$/',
        'CREATE INDEX $2 ON $1 $3',
        $addIndexSql
    ) ?? '';

    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_STRINGIFY_FETCHES => getenv('KORA_RC_STRINGIFY_FETCHES') === '1',
    ]);
    $now = '2026-09-05 12:00:00';
    $pdo->sqliteCreateFunction('NOW', static function () use (&$now): string {
        return $now;
    }, 0);
    $pdo->exec('CREATE TABLE cms_rate_limit (
        id VARCHAR(64) NOT NULL PRIMARY KEY,
        attempts INT NOT NULL DEFAULT 1,
        window_start DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec("INSERT INTO cms_rate_limit (id, attempts, window_start) VALUES
        ('legacy-recent', 5, '2026-09-05 10:00:00'),
        ('legacy-old', 8, '2026-08-20 10:00:00')");
    $pdo->exec($addColumnSql);
    $pdo->exec($addIndexSql);
    $expiryColumn = $pdo->query("SELECT type, [notnull], dflt_value FROM pragma_table_info('cms_rate_limit')
        WHERE name = 'expires_at'")->fetch(PDO::FETCH_ASSOC);
    if (is_array($expiryColumn) && array_key_exists('notnull', $expiryColumn)) {
        // SQLite/PDO may return this integer as a numeric string on older PHP.
        $expiryColumn['notnull'] = (string)$expiryColumn['notnull'];
    }
    rateLimitExpirySelfTestSame(
        $expiryColumn,
        ['type' => 'DATETIME', 'notnull' => '0', 'dflt_value' => 'NULL'],
        'Upgrade adds a nullable DATETIME column without expiring old rows'
    );
    rateLimitExpirySelfTestSame(
        $pdo->query("SELECT name FROM pragma_index_info('idx_rate_limit_expires_at')")->fetchAll(PDO::FETCH_COLUMN),
        ['expires_at'],
        'Upgrade indexes the expiry deadline'
    );

    $pdo->exec("INSERT INTO cms_rate_limit (id, attempts, window_start, expires_at) VALUES
        ('expired', 9, '2026-09-05 11:58:00', '2026-09-05 11:59:00'),
        ('boundary', 4, '2026-09-05 11:55:00', '2026-09-05 12:00:00'),
        ('long-active', 17, '2026-09-05 10:00:00', '2026-09-05 14:00:00'),
        ('known-custom', 30, '2026-08-20 10:00:00', '2026-09-20 10:00:00')");
    $beforeBackfill = rateLimitExpirySelfTestRows($pdo);
    rateLimitExpirySelfTestSame($pdo->exec($backfillSql), 2, 'Backfill touches only unknown deadlines');
    $afterBackfill = rateLimitExpirySelfTestRows($pdo);
    foreach ($beforeBackfill as $offset => $beforeRow) {
        $afterRow = $afterBackfill[$offset];
        if ($beforeRow['expires_at'] === null) {
            $expectedExpiry = (new DateTimeImmutable((string)$beforeRow['window_start']))
                ->modify('+7 days')->format('Y-m-d H:i:s');
            rateLimitExpirySelfTestSame(
                $afterRow['expires_at'],
                $expectedExpiry,
                'Legacy protection is anchored to window_start: ' . $beforeRow['id']
            );
            $beforeRow['expires_at'] = $expectedExpiry;
        }
        rateLimitExpirySelfTestSame(
            $afterRow,
            $beforeRow,
            'Backfill preserves counts, starts and all known deadlines: ' . $beforeRow['id']
        );
    }

    $now = '2026-09-05 12:01:00';
    rateLimitExpirySelfTestSame($pdo->exec($backfillSql), 0, 'Migration rerun does not extend expiries');
    rateLimitExpirySelfTestSame(
        rateLimitExpirySelfTestRows($pdo),
        $afterBackfill,
        'Migration rerun leaves all rate-limit state unchanged'
    );

    $now = '2026-09-05 12:00:00';
    $pdo->exec("INSERT INTO cms_rate_limit (id, attempts, window_start) VALUES
        ('unknown-after-upgrade', 50, '2026-08-20 10:00:00')");
    $beforeCleanup = rateLimitExpirySelfTestRows($pdo);
    rateLimitExpirySelfTestSame(
        $pdo->exec($cleanupSql),
        3,
        'Cron removes only expired, equality-boundary and expired migrated rows'
    );
    $expectedSurvivors = array_values(array_filter(
        $beforeCleanup,
        static fn (array $row): bool =>
        in_array($row['id'], ['known-custom', 'legacy-recent', 'long-active', 'unknown-after-upgrade'], true)
    ));
    rateLimitExpirySelfTestSame(
        rateLimitExpirySelfTestRows($pdo),
        $expectedSurvivors,
        'Cron preserves long active limits, protected legacy rows and NULL deadlines byte-for-byte'
    );
    rateLimitExpirySelfTestSame($pdo->exec($cleanupSql), 0, 'Repeated cleanup is idempotent');

    $now = '2026-09-05 14:00:00';
    rateLimitExpirySelfTestSame($pdo->exec($cleanupSql), 1, 'A multi-hour row expires at its own exact deadline');
    rateLimitExpirySelfTestSame(
        $pdo->query('SELECT id FROM cms_rate_limit ORDER BY id')->fetchAll(PDO::FETCH_COLUMN),
        ['known-custom', 'legacy-recent', 'unknown-after-upgrade'],
        'Later cleanup still preserves longer and unknown limits'
    );
    echo 'Rate-limit expiry self-test OK (' . $checks . " checks, isolated SQLite; no application DB)\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Rate-limit expiry self-test failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
