<?php

declare(strict_types=1);

namespace KoraRcFoodMediaTest;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Only schema introspection is adapted; production usage queries execute unchanged. */
final class FixtureDatabase extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'INFORMATION_SCHEMA.TABLES')) {
            $query = "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?";
        } elseif (str_contains($query, 'INFORMATION_SCHEMA.COLUMNS')) {
            $query = 'SELECT COUNT(*) FROM pragma_table_info(?) WHERE name = ?';
        }
        return parent::prepare($query, $options);
    }
}

final class Redirect extends RuntimeException
{
    public function __construct(public string $target)
    {
        parent::__construct('Fixture redirect');
    }
}

function same(mixed $actual, mixed $expected, string $label): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    $GLOBALS['foodMediaChecks']++;
}

function fixturePath(string $relative): string
{
    if (str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, ':')) {
        throw new RuntimeException('Invalid fixture path');
    }
    return $GLOBALS['foodMediaRoot'] . '/' . str_replace('\\', '/', $relative);
}

function ensureDirectory(string $path, int $permissions = 0755): bool
{
    $normalized = str_replace('\\', '/', $path);
    if (!str_starts_with($normalized, $GLOBALS['foodMediaRoot'] . '/')) {
        throw new RuntimeException('Refusing a directory outside the fixture root');
    }
    return is_dir($path) || mkdir($path, $permissions, true);
}

function writeFixture(string $path, string $bytes): void
{
    ensureDirectory(dirname($path));
    if (file_put_contents($path, $bytes) !== strlen($bytes)) {
        throw new RuntimeException('Cannot write fixture');
    }
}

/** @return array<string,string> */
function fileSnapshot(): array
{
    $result = [];
    foreach ($GLOBALS['foodMediaFiles'] as $path) {
        clearstatcache(true, $path);
        if (is_file($path)) {
            $result[$path] = (string)hash_file('sha256', $path);
        }
    }
    return $result;
}

/** @return array<string,list<array<string,mixed>>> */
function dataSnapshot(PDO $pdo): array
{
    $result = [];
    foreach (['cms_media', 'cms_food_cards', 'cms_food_items'] as $table) {
        $result[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll();
    }
    return $result;
}

/** A new namespace resets request-local scanner caches without bootstrapping the app. */
function requestNamespace(): string
{
    static $request = 0;
    $namespace = 'KoraRcFoodMediaRequest' . ++$request;
    $prefix = 'namespace ' . $namespace . '; use \\PDO; use \\PDOException; use \\RuntimeException; use \\Throwable;';
    eval($prefix . '
        function db_connect(): PDO { return $GLOBALS["foodMediaDb"]; }
        function isModuleEnabled(string $module): bool { return (bool)$GLOBALS["foodMediaModuleEnabled"]; }
        function koraStoragePath(string $relative): string { return \\KoraRcFoodMediaTest\\fixturePath("private/" . $relative); }
        function koraEnsureDirectory(string $path, int $permissions = 0755): bool { return \\KoraRcFoodMediaTest\\ensureDirectory($path, $permissions); }
        function koraLog(string $level, string $message, array $context = []): void { $GLOBALS["foodMediaLogs"][] = [$level, $message, $context]; }
        function inputInt(string $source, string $name): ?int {
            $value = filter_var(($source === "get" ? $_GET : $_POST)[$name] ?? "", FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
            return $value === false ? null : (int)$value;
        }
        function logAction(string $action, string $detail = ""): void { $GLOBALS["foodMediaActions"][] = [$action, $detail]; }
        function mediaAdminRedirectWithFlash(string $target): void { throw new \\KoraRcFoodMediaTest\\Redirect($target); }
    ');
    $source = (string)file_get_contents(dirname(__DIR__) . '/lib/media_library.php');
    $path = fixturePath('lib/media_library_' . $request . '.php');
    writeFixture($path, '<?php ' . $prefix . substr($source, 5));
    require $path;
    return $namespace;
}

/** @param array<string,mixed> $fields */
function runMediaAction(PDO $pdo, array $fields): void
{
    $namespace = requestNamespace();
    $source = (string)file_get_contents(dirname(__DIR__) . '/admin/media.php');
    $action = (string)$fields['action'];
    $nextAction = ['delete' => 'bulk', 'update_meta' => 'replace', 'bulk' => null][$action];
    $start = strpos($source, "    if (\$action === '{$action}') {");
    $end = $nextAction !== null
        ? strpos($source, "    if (\$action === '{$nextAction}') {", $start === false ? 0 : $start)
        : strpos($source, "\n}\n\n\$flash = mediaFlashPull();", $start === false ? 0 : $start);
    if ($start === false || $end === false) {
        throw new RuntimeException('Cannot locate the actual media POST branch: ' . $action);
    }
    $_POST = $fields;
    $_SESSION = [];
    $GLOBALS['foodMediaActions'] = [];
    $GLOBALS['foodMediaLogs'] = [];
    $target = '/fixture/admin/media.php';
    $mediaDeleteConfirmErrorMessage = 'Fixture confirmation required';
    $mediaDeleteFilesystemErrorMessage = 'Fixture filesystem failure';
    try {
        eval('namespace ' . $namespace . '; ' . substr($source, $start, $end - $start));
        throw new RuntimeException('The actual POST branch did not redirect');
    } catch (Redirect $response) {
        same($response->target, $target, 'Actual branch redirects to its supplied internal target');
    }
}

function removeFixtureTree(string $root): void
{
    $resolved = realpath($root);
    if ($resolved === false || str_replace('\\', '/', $resolved) !== $GLOBALS['foodMediaRoot']) {
        throw new RuntimeException('Refusing cleanup outside the resolved fixture root');
    }
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($resolved, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($resolved);
}

$GLOBALS['foodMediaChecks'] = 0;
$GLOBALS['foodMediaRoot'] = str_replace('\\', '/', sys_get_temp_dir()) . '/kora_rc_food_media_' . bin2hex(random_bytes(12));
$GLOBALS['foodMediaFiles'] = [];
$GLOBALS['foodMediaModuleEnabled'] = false;
$exitCode = 0;
mkdir($GLOBALS['foodMediaRoot'], 0700);

try {
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('This offline self-test requires pdo_sqlite');
    }
    define('BASE_URL', '/fixture');
    $pdo = new FixtureDatabase('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => getenv('KORA_RC_STRINGIFY_FETCHES') === '1',
    ]);
    $GLOBALS['foodMediaDb'] = $pdo;
    $pdo->sqliteCreateFunction('CONCAT', static fn (mixed ...$values): string => implode('', $values));
    $pdo->exec('CREATE TABLE cms_media (id INTEGER PRIMARY KEY, filename TEXT, original_name TEXT, mime_type TEXT, visibility TEXT)');
    $pdo->exec('CREATE TABLE cms_food_cards (id INTEGER PRIMARY KEY, title TEXT, description TEXT, content TEXT, status TEXT, is_published INT, deleted_at TEXT)');
    $pdo->exec('CREATE TABLE cms_food_items (id INTEGER PRIMARY KEY, card_id INT, title TEXT, media_id INT, is_available INT)');
    $pdo->exec('CREATE TABLE cms_appmarket_apps (id INTEGER PRIMARY KEY, name TEXT, icon_media_id INT)');

    $states = ['public' => ['published', 1, null], 'hidden' => ['published', 0, null], 'deleted' => ['published', 1, '2026-10-05 12:00:00']];
    foreach ($states as $index => $state) {
        $cardId = count($GLOBALS['foodMediaFiles']) + 10;
        $itemId = $cardId + 100;
        $mediaId = $cardId + 200;
        $filename = 'food-' . $mediaId . '.png';
        $pdo->prepare('INSERT INTO cms_food_cards VALUES (?,?,?,?,?,?,?)')->execute([$cardId, $index, '', '', ...$state]);
        $pdo->prepare('INSERT INTO cms_food_items VALUES (?,?,?,?,?)')->execute([$itemId, $cardId, 'Food item ' . $index, $mediaId, 0]);
        $pdo->prepare('INSERT INTO cms_media VALUES (?,?,?,?,?)')->execute([$mediaId, $filename, $filename, 'image/png', 'public']);
        foreach (['uploads/media/' . $filename, 'uploads/media/thumbs/' . $filename,
            'uploads/media/' . str_replace('.png', '.webp', $filename), 'uploads/media/thumbs/' . str_replace('.png', '.webp', $filename)] as $relative) {
            $path = fixturePath($relative);
            writeFixture($path, 'fixture bytes ' . $relative);
            $GLOBALS['foodMediaFiles'][] = $path;
        }
        $namespace = requestNamespace();
        $find = $namespace . '\\mediaFindUsages';
        $has = $namespace . '\\mediaHasUsage';
        $media = $pdo->query('SELECT * FROM cms_media WHERE id = ' . $mediaId)->fetch();
        $usages = $find($media, 50);
        same(count($usages), 1, $index . ' menu has exactly one structural usage');
        same($usages[0]['title'], 'Food item ' . $index, 'Usage identifies the actual item');
        same($usages[0]['admin_url'], '/fixture/admin/food_items.php?card=' . $cardId . '&edit_item=' . $itemId, 'Usage links to the owning item editor');
        same($has($media), true, 'Actual scanner protects even unavailable items');
        same(($namespace . '\\isModuleEnabled')('food'), false, 'Food is disabled in the scanner and controller fixture');
        same($find(['id' => $mediaId + 1000, 'filename' => 'unrelated.png']), [], 'Unrelated media IDs are not falsely matched');
        same($GLOBALS['foodMediaLogs'] ?? [], [], 'Scanner succeeds without fail-closed exception fallback');

        $beforeData = dataSnapshot($pdo);
        $beforeFiles = fileSnapshot();
        foreach ([
            ['action' => 'delete', 'media_id' => $mediaId, 'confirm_media_delete_' . $mediaId => '1'],
            ['action' => 'bulk', 'bulk_action' => 'delete_unused', 'media_ids' => [$mediaId]],
            ['action' => 'update_meta', 'media_id' => $mediaId, 'visibility' => 'private'],
            ['action' => 'bulk', 'bulk_action' => 'make_private', 'media_ids' => [$mediaId]],
        ] as $fields) {
            runMediaAction($pdo, $fields);
            same(dataSnapshot($pdo), $beforeData, $index . ' rejected action preserves all fixture metadata');
            same(fileSnapshot(), $beforeFiles, $index . ' rejected action preserves originals and all derivatives');
            same(isset($_SESSION['media_library_flash']['error']), true, 'Rejected action explains its failure');
            same($GLOBALS['foodMediaLogs'], [], 'Rejection is not a filesystem error');
            if ($fields['action'] !== 'bulk') {
                same($GLOBALS['foodMediaActions'], [], 'Rejected individual action does not log a successful mutation');
            }
        }

        $pdo->prepare('UPDATE cms_food_items SET media_id = NULL WHERE id = ?')->execute([$itemId]);
        $namespace = requestNamespace();
        $has = $namespace . '\\mediaHasUsage';
        same($has($media), false, 'A fresh request sees detachment');
        $delete = $index === 'hidden'
            ? ['action' => 'bulk', 'bulk_action' => 'delete_unused', 'media_ids' => [$mediaId]]
            : ['action' => 'delete', 'media_id' => $mediaId, 'confirm_media_delete_' . $mediaId => '1'];
        $remainingFiles = array_diff_key($beforeFiles, array_flip(array_filter(
            $GLOBALS['foodMediaFiles'],
            static fn (string $path): bool => str_contains($path, 'food-' . $mediaId . '.')
        )));
        runMediaAction($pdo, $delete);
        same($pdo->query('SELECT COUNT(*) FROM cms_media WHERE id = ' . $mediaId)->fetchColumn(), getenv('KORA_RC_STRINGIFY_FETCHES') === '1' ? '0' : 0, 'Detached media metadata is deleted');
        same(fileSnapshot(), $remainingFiles, 'Detached deletion removes only its own complete file set');
        same(isset($_SESSION['media_library_flash']['success']), true, 'Detached deletion reports success');
        same(isset($_SESSION['media_library_flash']['error']), false, 'Detached deletion has no false usage error');
        same($GLOBALS['foodMediaLogs'], [], 'Detached deletion succeeds without filesystem failures');
    }

    $pdo->exec("INSERT INTO cms_appmarket_apps VALUES (1, 'Existing structural usage', 999)");
    $namespace = requestNamespace();
    $find = $namespace . '\\mediaFindUsages';
    same($find(['id' => 999, 'filename' => 'existing.png'])[0]['admin_url'], '/fixture/admin/appmarket_form.php?id=1', 'Existing structural registry entries still work');
    echo 'RC Food media usage: ' . $GLOBALS['foodMediaChecks'] . " offline SQLite/controller checks passed.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n");
    $exitCode = 1;
} finally {
    removeFixtureTree($GLOBALS['foodMediaRoot']);
}
exit($exitCode);
