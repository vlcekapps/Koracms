<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/presentation.php';
require_once dirname(__DIR__) . '/lib/recipes.php';

function rcRecipeMysqlConnect(): PDO
{
    $dbHost = (string)$GLOBALS['server'];
    $dbName = (string)$GLOBALS['database'];
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", (string)$GLOBALS['user'], (string)$GLOBALS['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 8');
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    return $pdo;
}

/** @param resource $channel */
function rcRecipeMysqlSignal($channel, ?array $message = null): array
{
    if ($message !== null) {
        $line = json_encode($message, JSON_THROW_ON_ERROR) . "\n";
        if (fwrite($channel, $line) !== strlen($line)) {
            throw new RuntimeException('Incomplete recipe worker signal.');
        }
        return $message;
    }
    stream_set_timeout($channel, 5);
    $line = fgets($channel, 8192);
    if ($line === false || !str_ends_with($line, "\n")) {
        throw new RuntimeException('Recipe worker did not return a complete signal.');
    }
    $decoded = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid recipe worker signal.');
    }
    return $decoded;
}

if (($argv[1] ?? '') === '--worker') {
    $workerPdo = null;
    try {
        if (count($argv) !== 6 || !ctype_digit($argv[2])
            || preg_match('/^rc-recipe-mysql-[a-f0-9]{24}$/D', $argv[3]) !== 1
            || preg_match('/^127\.0\.0\.1:[0-9]+$/D', $argv[4]) !== 1
            || !in_array($argv[5], ['restore', 'incomplete', 'valid'], true)) {
            throw new RuntimeException('Invalid recipe worker arguments.');
        }
        $channel = stream_socket_client('tcp://' . $argv[4], $errorCode, $errorText, 5);
        if ($channel === false) {
            throw new RuntimeException('Cannot open recipe worker channel.');
        }
        $workerPdo = rcRecipeMysqlConnect();
        $recipeId = (int)$argv[2];
        $owner = $workerPdo->prepare('SELECT slug FROM cms_recipes WHERE id = ?');
        $owner->execute([$recipeId]);
        if ($owner->fetchColumn() !== $argv[3]) {
            throw new RuntimeException('Worker refused a foreign recipe.');
        }
        $workerPdo->beginTransaction();
        $oldSnapshot = $workerPdo->prepare('SELECT COUNT(*) FROM cms_recipe_ingredients WHERE recipe_id = ?');
        $oldSnapshot->execute([$recipeId]);
        if ((int)$oldSnapshot->fetchColumn() !== 1) {
            throw new RuntimeException('Recipe worker needs an initially complete snapshot.');
        }
        rcRecipeMysqlSignal($channel, ['type' => 'ready', 'id' => (int)$workerPdo->query('SELECT CONNECTION_ID()')->fetchColumn()]);
        if ($argv[5] === 'restore') {
            $accepted = recipeApplyLifecycleAction($workerPdo, $recipeId, 'purge');
        } else {
            $recipe = recipeLockForWrite($workerPdo, $recipeId);
            $accepted = $recipe !== null && recipeHasPublishableStructure($workerPdo, $recipeId, true);
            if ($accepted) {
                $workerPdo->prepare("UPDATE cms_recipes SET status = 'published' WHERE id = ?")->execute([$recipeId]);
            }
        }
        if ($accepted) {
            $workerPdo->commit();
        } else {
            $workerPdo->rollBack();
        }
        rcRecipeMysqlSignal($channel, ['type' => 'result', 'accepted' => $accepted]);
    } catch (Throwable $exception) {
        if ($workerPdo instanceof PDO && $workerPdo->inTransaction()) {
            $workerPdo->rollBack();
        }
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    }
    exit(0);
}

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$pdo = rcRecipeMysqlConnect();
$engines = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('cms_recipes','cms_recipe_categories','cms_recipe_ingredients','cms_recipe_ingredient_groups','cms_recipe_steps',
        'cms_recipe_structure_snapshots','cms_revisions','cms_redirects','cms_content_locks')")->fetchAll();
$check(count($engines) === 9, 'All recipe write and cleanup tables must exist.');
foreach ($engines as $table) {
    $check(strcasecmp((string)$table['ENGINE'], 'InnoDB') === 0, 'Recipe tables must support transactions.');
}

foreach (['restore', 'incomplete', 'valid'] as $scenario) {
    $slug = 'rc-recipe-mysql-' . bin2hex(random_bytes(12));
    $recipeId = $categoryId = 0;
    $listener = $channel = $process = $workerLog = null;
    $failure = null;
    try {
        $pdo->prepare('INSERT INTO cms_recipe_categories (name,slug) VALUES (?,?)')->execute([$slug, $slug]);
        $categoryId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO cms_recipes (category_id,title,slug,status) VALUES (?,?,?,'draft')")->execute([$categoryId, $slug, $slug]);
        $recipeId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO cms_recipe_ingredient_groups (recipe_id,title) VALUES (?,?)')->execute([$recipeId, 'Ingredients']);
        $groupId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO cms_recipe_ingredients (recipe_id,group_id,name) VALUES (?,?,?)')->execute([$recipeId, $groupId, 'Flour']);
        $pdo->prepare('INSERT INTO cms_recipe_steps (recipe_id,instruction) VALUES (?,?)')->execute([$recipeId, 'Mix']);
        if ($scenario === 'restore') {
            $pdo->prepare('UPDATE cms_recipes SET deleted_at = NOW() WHERE id = ?')->execute([$recipeId]);
        }
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorText);
        $check(is_resource($listener), 'Cannot open recipe listener.');
        $address = stream_socket_get_name($listener, false);
        $check(is_string($address), 'Recipe listener needs a loopback address.');
        $workerLog = tmpfile();
        $check(is_resource($workerLog), 'Cannot open recipe worker diagnostics.');
        $pdo->beginTransaction();
        $check(recipeLockForWrite($pdo, $recipeId, true) !== null, 'Parent owns recipe row lock.');
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', (string)$recipeId, $slug, $address, $scenario], [
            0 => ['pipe', 'r'], 1 => $workerLog, 2 => $workerLog,
        ], $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
        $check(is_resource($process), 'Cannot launch recipe worker.');
        fclose($pipes[0]);
        $channel = stream_socket_accept($listener, 5);
        $check(is_resource($channel), 'Recipe worker did not connect.');
        $ready = rcRecipeMysqlSignal($channel);
        $workerId = (int)($ready['id'] ?? 0);
        $check(($ready['type'] ?? '') === 'ready' && $workerId > 0
            && $workerId !== (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn(), 'Worker uses an independent MySQL connection.');
        $inspection = $pdo->prepare('SELECT INFO FROM information_schema.PROCESSLIST WHERE ID = ?');
        $deadline = microtime(true) + 4;
        $waitingSince = null;
        $observedWait = false;
        while (microtime(true) < $deadline) {
            $read = [$channel];
            $write = $except = null;
            $check(stream_select($read, $write, $except, 0, 0) === 0, 'Recipe worker cannot finish before parent commit.');
            $inspection->execute([$workerId]);
            $query = (string)$inspection->fetchColumn();
            if (str_contains($query, 'cms_recipes') && str_contains(strtoupper($query), 'FOR UPDATE')) {
                $waitingSince ??= microtime(true);
                if (microtime(true) - $waitingSince >= 0.1) {
                    $observedWait = true;
                    break;
                }
            } else {
                $waitingSince = null;
            }
            usleep(20000);
        }
        $check($observedWait, 'Must observe a real recipe row-lock wait.');
        if ($scenario === 'restore') {
            $pdo->prepare('UPDATE cms_recipes SET deleted_at = NULL WHERE id = ?')->execute([$recipeId]);
        } elseif ($scenario === 'incomplete') {
            $pdo->prepare('DELETE FROM cms_recipe_ingredients WHERE recipe_id = ?')->execute([$recipeId]);
        }
        $pdo->commit();
        $result = rcRecipeMysqlSignal($channel);
        $check(($result['type'] ?? '') === 'result' && ($result['accepted'] ?? null) === ($scenario === 'valid'), 'Worker uses current state, not its old snapshot.');
        $state = $pdo->prepare('SELECT status, deleted_at FROM cms_recipes WHERE id = ?');
        $state->execute([$recipeId]);
        $recipe = $state->fetch();
        $check(is_array($recipe) && $recipe['deleted_at'] === null && $recipe['status'] === ($scenario === 'valid' ? 'published' : 'draft'), 'No restored deletion or incomplete publication.');
        $deadline = microtime(true) + 3;
        do {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $check(!$status['running'] && $status['exitcode'] === 0, 'Recipe worker exits successfully.');
    } catch (Throwable $exception) {
        $failure = $exception->getMessage();
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_resource($process)) {
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process);
            }
            proc_close($process);
        }
        foreach ([$channel, $listener] as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if ($recipeId > 0) {
            $owner = $pdo->prepare('SELECT slug FROM cms_recipes WHERE id = ?');
            $owner->execute([$recipeId]);
            $check($owner->fetchColumn() === $slug, 'Cleanup refuses a foreign recipe.');
            foreach (['cms_recipe_steps', 'cms_recipe_ingredients', 'cms_recipe_ingredient_groups'] as $table) {
                $pdo->prepare('DELETE FROM ' . $table . ' WHERE recipe_id = ?')->execute([$recipeId]);
            }
            $pdo->prepare('DELETE FROM cms_recipes WHERE id = ? AND slug = ?')->execute([$recipeId, $slug]);
        }
        if ($categoryId > 0) {
            $pdo->prepare('DELETE FROM cms_recipe_categories WHERE id = ? AND slug = ?')->execute([$categoryId, $slug]);
        }
        if (is_resource($workerLog)) {
            if ($failure !== null) {
                rewind($workerLog);
                $failure .= "\nWorker: " . stream_get_contents($workerLog, 8192);
            }
            fclose($workerLog);
        }
    }
    if ($failure !== null) {
        fwrite(STDERR, 'FAIL: ' . $scenario . ': ' . $failure . "\n");
        exit(1);
    }
}
echo 'OK: ' . $checks . " recipe MySQL checks; three real row-lock races; fixtures cleaned; no application mail.\n";
