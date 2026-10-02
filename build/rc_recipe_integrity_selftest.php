<?php

declare(strict_types=1);

namespace KoraRecipeIntegrityTest;

use PDO;
use RuntimeException;

// Execute real recipe handlers without application bootstrap, mail or persistent data.
define('BASE_URL', '');
$checks = 0;

final class Response extends RuntimeException
{
}

function same(mixed $actual, mixed $expected, string $label): void
{
    $GLOBALS['checks']++;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': ' . var_export($actual, true));
    }
}

function evaluate(string $code): mixed
{
    return eval('namespace ' . __NAMESPACE__ . '; use \\PDO; use \\Throwable; use \\DomainException; use \\DateTimeImmutable; use \\RuntimeException; use \\LogicException; ' . $code);
}

function runHandler(string $path): string
{
    $code = (string)file_get_contents(dirname(__DIR__) . '/' . $path);
    $code = preg_replace('/^require_once [^\r\n]+;\R/m', '', $code);
    $code = preg_replace('/^function recipeContentDeletePart\b.*?^\}\R/ms', '', $code);
    $code = str_replace('catch (Throwable $exception) {', 'catch (Throwable $exception) { if ($exception instanceof \\KoraRecipeIntegrityTest\\Response) { throw $exception; }', $code);
    $code = str_replace("adminHeader('Ingredience", "\$GLOBALS['renderedState'] = ['error' => \$error, 'group' => \$groupState, 'ingredient' => \$ingredientState, 'step' => \$stepState]; adminHeader('Ingredience", $code);
    ob_start();
    try {
        evaluate('?>' . $code);
        return (string)ob_get_contents();
    } catch (Response $response) {
        return $response->getMessage();
    } finally {
        ob_end_clean();
    }
}

function header(string $value): void
{
    if (str_starts_with($value, 'Location: ')) {
        throw new Response(substr($value, 10));
    }
}
function db_connect(): PDO
{
    return $GLOBALS['fixtureDb'];
}
function requireCapability(string $capability, string $message = ''): void
{
}
function requireModuleEnabled(string $module): void
{
}
function currentUserHasCapability(string $capability): bool
{
    return true;
}
function currentUserId(): int
{
    return 7;
}
function verifyCsrf(): void
{
    if (($_POST['csrf_token'] ?? '') !== 'fixture-token') {
        throw new Response('csrf-rejected');
    }
}
function inputInt(string $source, string $name): ?int
{
    $value = filter_var(($source === 'post' ? $_POST : $_GET)[$name] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $value === false ? null : $value;
}
function appendUrlQuery(string $path, array $params): string
{
    return $path . '?' . http_build_query($params);
}
function slugify(string $value): string
{
    return $value;
}
function foodAllergenDefinitions(): array
{
    return [];
}
function logAction(string $action, string $details): void
{
}
function acquireContentLock(string $type, int $id): string
{
    return '';
}
function releaseContentLock(string $type, int $id): void
{
}
function koraLog(string $level, string $message, array $context = []): void
{
}
function storedRedirectTarget(string $path, string $fallback): string
{
    return str_starts_with($path, '/') && !str_starts_with($path, '//') ? $path : $fallback;
}
function upsertPathRedirect(PDO $pdo, string $old, string $new): void
{
}
function adminHeader(string $title): void
{
    throw new Response('render:' . $GLOBALS['renderedState']['error']);
}

final class FixturePdo extends PDO
{
    public mixed $beforeBegin = null;
    public mixed $beforeUnprotectedUpdate = null;

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => getenv('KORA_RC_STRINGIFY_FETCHES') === '1',
        ]);
        $this->sqliteCreateFunction('NOW', static fn (): string => '2026-10-03 12:00:00');
    }
    public function beginTransaction(): bool
    {
        $callback = $this->beforeBegin;
        $this->beforeBegin = null;
        if ($callback !== null) {
            $callback($this);
        }
        return parent::beginTransaction();
    }
    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        if (str_contains($query, 'UPDATE cms_recipes') && !$this->inTransaction() && $this->beforeUnprotectedUpdate !== null) {
            $callback = $this->beforeUnprotectedUpdate;
            $this->beforeUnprotectedUpdate = null;
            $callback($this);
        }
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}

function fixture(): FixturePdo
{
    $pdo = new FixturePdo();
    $pdo->exec("CREATE TABLE cms_recipe_categories (id INTEGER PRIMARY KEY, is_active INTEGER);
        INSERT INTO cms_recipe_categories VALUES (1, 1);
        CREATE TABLE cms_recipes (id INTEGER PRIMARY KEY, category_id INTEGER, author_id INTEGER, title TEXT,
            slug TEXT UNIQUE, summary TEXT, notes TEXT, servings INTEGER, prep_minutes INTEGER, cook_minutes INTEGER,
            difficulty TEXT, dietary_flags TEXT, allergens TEXT, calories_kcal INTEGER, media_id INTEGER,
            image_alt_text TEXT, source_name TEXT, source_url TEXT, meta_title TEXT, meta_description TEXT,
            status TEXT, publish_at TEXT, deleted_at TEXT, updated_at TEXT);
        INSERT INTO cms_recipes (id, category_id, title, slug, summary, status) VALUES (1, 1, 'Recipe', 'recipe', 'Summary', 'draft');
        CREATE TABLE cms_recipe_ingredient_groups (id INTEGER PRIMARY KEY, recipe_id INTEGER, title TEXT, sort_order INTEGER, updated_at TEXT);
        CREATE TABLE cms_recipe_ingredients (id INTEGER PRIMARY KEY, recipe_id INTEGER, group_id INTEGER, name TEXT,
            amount TEXT, quantity_min TEXT, quantity_max TEXT, unit TEXT, note TEXT, is_optional INTEGER, sort_order INTEGER, updated_at TEXT);
        CREATE TABLE cms_recipe_steps (id INTEGER PRIMARY KEY, recipe_id INTEGER, title TEXT, instruction TEXT, media_id INTEGER,
            image_alt_text TEXT, sort_order INTEGER, updated_at TEXT);
        CREATE TABLE cms_media (id INTEGER PRIMARY KEY, mime_type TEXT, visibility TEXT, original_name TEXT, alt_text TEXT, created_at TEXT, filename TEXT, folder TEXT);
        CREATE TABLE cms_recipe_structure_snapshots (id INTEGER PRIMARY KEY, recipe_id INTEGER, action_label TEXT, snapshot_json TEXT,
            ingredient_count INTEGER, step_count INTEGER, user_id INTEGER);
        CREATE TABLE cms_revisions (id INTEGER PRIMARY KEY, entity_type TEXT, entity_id INTEGER, field_name TEXT, old_value TEXT, new_value TEXT, user_id INTEGER, created_at TEXT);
        CREATE TABLE cms_content_locks (id INTEGER PRIMARY KEY, entity_type TEXT, entity_id INTEGER);
        CREATE TABLE cms_redirects (id INTEGER PRIMARY KEY, new_path TEXT);
        INSERT INTO cms_recipe_ingredient_groups VALUES (1, 1, 'Ingredients', 10, NULL);
        INSERT INTO cms_recipe_ingredients (id, recipe_id, group_id, name, sort_order) VALUES (1, 1, 1, 'Flour', 10);
        INSERT INTO cms_recipe_steps (id, recipe_id, instruction, sort_order) VALUES (1, 1, 'Mix', 10);
        INSERT INTO cms_revisions (id,entity_type,entity_id) VALUES (1, 'recipe', 1);
        INSERT INTO cms_content_locks VALUES (1, 'recipe', 1);
        INSERT INTO cms_redirects VALUES (1, '/recipes/recipe');");
    $GLOBALS['fixtureDb'] = $pdo;
    $_GET = [];
    $_SESSION = [];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['csrf_token' => 'fixture-token', 'id' => '1', 'category_id' => '1', 'title' => 'Recipe edited',
        'slug' => 'recipe', 'summary' => 'Preserved summary', 'notes' => 'Preserved notes', 'status' => 'published'];
    return $pdo;
}

function snapshot(PDO $pdo): array
{
    $result = [];
    foreach (['cms_recipes', 'cms_recipe_ingredient_groups', 'cms_recipe_ingredients', 'cms_recipe_steps',
        'cms_recipe_structure_snapshots', 'cms_revisions', 'cms_content_locks', 'cms_redirects'] as $table) {
        $result[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll();
    }
    return $result;
}

try {
    evaluate('?>' . file_get_contents(dirname(__DIR__) . '/lib/recipes.php'));
    $revisionSource = (string)file_get_contents(dirname(__DIR__) . '/lib/revisions.php');
    foreach (['saveRevision', 'revisionLogError'] as $functionName) {
        preg_match('/^function ' . $functionName . '\b.*?^\}/ms', $revisionSource, $functionSource);
        evaluate($functionSource[0]);
    }
    $presentationSource = (string)file_get_contents(dirname(__DIR__) . '/lib/presentation.php');
    preg_match('/^function deleteRedirectsTargetingPath\b.*?^\}/ms', $presentationSource, $redirectFunction);
    evaluate($redirectFunction[0]);
    $contentSource = (string)file_get_contents(dirname(__DIR__) . '/admin/recipe_content.php');
    preg_match('/^function recipeContentDeletePart\b.*?^\}/ms', $contentSource, $deleteFunction);
    evaluate($deleteFunction[0]);
    $scenario = $argv[1] ?? 'all';
    if (in_array($scenario, ['all', 'purge'], true)) {
        $pdo = fixture();
        $pdo->exec("UPDATE cms_recipes SET deleted_at = '2026-10-02' WHERE id = 1");
        $_POST += ['action' => 'purge', 'confirm_action' => '1'];
        $pdo->beforeBegin = static function (PDO $connection): void {
            $connection->exec('UPDATE cms_recipes SET deleted_at = NULL WHERE id = 1');
        };
        runHandler('admin/recipe_action.php');
        same((int)$pdo->query('SELECT COUNT(*) FROM cms_recipes')->fetchColumn(), 1, 'A restored recipe must not be purged from an old snapshot');
        same((int)$pdo->query('SELECT COUNT(*) FROM cms_recipe_ingredients')->fetchColumn(), 1, 'Restored recipe retains ingredients');
        same($pdo->inTransaction(), false, 'Refused purge releases transaction');
        foreach (['delete', 'purge'] as $action) {
            $pdo = fixture();
            $_POST += ['action' => $action];
            $before = snapshot($pdo);
            same(str_contains(runHandler('admin/recipe_action.php'), 'action_error=confirm'), true, 'Critical action requires fresh confirmation');
            same(snapshot($pdo), $before, 'Missing confirmation changes nothing');
        }
        $pdo = fixture();
        same(recipeApplyLifecycleAction($pdo, 1, 'purge'), false, 'Active recipe refuses direct purge');
        same(recipeApplyLifecycleAction($pdo, 1, 'restore'), false, 'Active recipe refuses stale restore');
        same(recipeApplyLifecycleAction($pdo, 999, 'delete'), false, 'Missing recipe is not modified');
        same(recipeApplyLifecycleAction($pdo, 1, 'unknown'), false, 'Unknown action is refused');
        same(recipeApplyLifecycleAction($pdo, 1, 'delete'), true, 'Soft delete succeeds');
        same($pdo->query('SELECT status FROM cms_recipes')->fetchColumn(), 'draft', 'Deleted recipe is draft');
        same(recipeApplyLifecycleAction($pdo, 1, 'restore'), true, 'Restore succeeds');
        $pdo->exec("INSERT INTO cms_redirects VALUES (2, '/recipes/recipe')");
        $pdo->exec("CREATE TRIGGER fail_redirect BEFORE DELETE ON cms_redirects BEGIN SELECT RAISE(ABORT, 'fixture redirect failure'); END");
        $before = snapshot($pdo);
        try {
            recipeApplyLifecycleAction($pdo, 1, 'delete');
            throw new RuntimeException('Strict redirect cleanup failure was swallowed');
        } catch (\PDOException $exception) {
            same(str_contains($exception->getMessage(), 'fixture redirect failure'), true, 'Strict redirect cleanup propagates failure');
        }
        same(snapshot($pdo), $before, 'Failed redirect cleanup leaves the active recipe unchanged');
        deleteRedirectsTargetingPath($pdo, '/recipes/recipe');
        same(snapshot($pdo), $before, 'Default redirect cleanup preserves best-effort compatibility');
        $pdo->exec('DROP TRIGGER fail_redirect');
        same(recipeApplyLifecycleAction($pdo, 1, 'delete'), true, 'Recipe can be deleted again');
        $before = snapshot($pdo);
        $pdo->exec("CREATE TRIGGER fail_purge BEFORE DELETE ON cms_recipes BEGIN SELECT RAISE(ABORT, 'fixture purge failure'); END");
        try {
            recipeApplyLifecycleAction($pdo, 1, 'purge');
            throw new RuntimeException('Purge failure injection did not execute');
        } catch (\PDOException $exception) {
            same(str_contains($exception->getMessage(), 'fixture purge failure'), true, 'Failure follows dependent cleanup');
        }
        same(snapshot($pdo), $before, 'Failed purge restores all children, locks and history');
        $pdo->exec('DROP TRIGGER fail_purge');
        $pdo->beginTransaction();
        same(recipeApplyLifecycleAction($pdo, 1, 'purge'), true, 'Purge supports caller-owned transaction');
        same($pdo->inTransaction(), true, 'Helper does not commit caller transaction');
        $pdo->rollBack();
        same(snapshot($pdo), $before, 'Caller rollback restores the complete recipe');
        same(recipeApplyLifecycleAction($pdo, 1, 'purge'), true, 'Confirmed purge succeeds');
        same(array_filter(snapshot($pdo), static fn (array $rows): bool => $rows !== []), [], 'Purge leaves no related records');
    }
    if (in_array($scenario, ['all', 'publish'], true)) {
        $pdo = fixture();
        $pdo->beforeUnprotectedUpdate = static function (PDO $connection): void {
            $connection->exec('DELETE FROM cms_recipe_ingredients WHERE recipe_id = 1');
        };
        runHandler('admin/recipe_save.php');
        same(recipeHasPublishableStructure($pdo, 1), true, 'Publication check and write must share a transaction');
        same($pdo->query('SELECT status FROM cms_recipes')->fetchColumn(), 'published', 'Complete recipe publishes');
        same($pdo->inTransaction(), false, 'Save commits before redirect');
        $pdo = fixture();
        $pdo->exec("CREATE TRIGGER fail_revision BEFORE INSERT ON cms_revisions WHEN NEW.field_name = 'summary' BEGIN SELECT RAISE(ABORT, 'fixture revision failure'); END");
        $before = snapshot($pdo);
        same(runHandler('admin/recipe_save.php'), '/admin/recipe_form.php?id=1', 'Save failure returns to the same editor');
        same(snapshot($pdo), $before, 'Revision failure must roll back the recipe update');
        same($pdo->inTransaction(), false, 'Failed save releases transaction');
        saveRevision($pdo, 'recipe', 1, ['summary' => 'before'], ['summary' => 'after']);
        same(snapshot($pdo), $before, 'Default revision callers keep best-effort compatibility');
        same($_SESSION['recipe_form_flash']['form']['summary'], 'Preserved summary', 'Save failure retains summary');
        same($_SESSION['recipe_form_flash']['form']['notes'], 'Preserved notes', 'Save failure retains notes');
        same($_SESSION['recipe_form_flash']['field_errors'], ['status'], 'Save failure has a field-level error');
        $pdo = fixture();
        $pdo->exec('DELETE FROM cms_recipe_ingredients');
        $before = snapshot($pdo);
        same(runHandler('admin/recipe_save.php'), '/admin/recipe_form.php?id=1', 'Incomplete recipe returns to editor');
        same(snapshot($pdo), $before, 'Invalid publication changes no data');
        same($_SESSION['recipe_form_flash']['form']['title'], 'Recipe edited', 'Publication error retains title');
    }
    if (in_array($scenario, ['all', 'new'], true)) {
        $pdo = fixture();
        unset($_POST['id']);
        $_POST['slug'] = 'new-recipe';
        $pdo->exec("CREATE TRIGGER fail_initial_group BEFORE INSERT ON cms_recipe_ingredient_groups BEGIN SELECT RAISE(ABORT, 'fixture group failure'); END");
        $before = snapshot($pdo);
        same(runHandler('admin/recipe_save.php'), '/admin/recipe_form.php', 'Failed creation returns to new editor');
        same(snapshot($pdo), $before, 'Failed initial group must not leave a newly created recipe');
        same($_SESSION['recipe_form_flash']['form']['slug'], 'new-recipe', 'Failed creation retains slug');
        $pdo->exec('DROP TRIGGER fail_initial_group');
        same(runHandler('admin/recipe_save.php'), '/admin/recipe_content.php?id=2', 'New recipe proceeds to structure editor');
        same((int)$pdo->query('SELECT COUNT(*) FROM cms_recipe_ingredient_groups WHERE recipe_id=2')->fetchColumn(), 1, 'Initial group exists with new recipe');
        same($pdo->query('SELECT status FROM cms_recipes WHERE id=2')->fetchColumn(), 'draft', 'New recipe cannot bypass draft workflow');
    }
    if (in_array($scenario, ['all', 'structure'], true)) {
        $pdo = fixture();
        $_POST += ['recipe_id' => '1', 'action' => 'save_group', 'group_id' => '1', 'group_title' => 'Updated group'];
        $pdo->exec("CREATE TRIGGER fail_group BEFORE UPDATE ON cms_recipe_ingredient_groups BEGIN SELECT RAISE(ABORT, 'fixture structure failure'); END");
        $before = snapshot($pdo);
        same(str_contains(runHandler('admin/recipe_content.php'), 'Změnu obsahu se nepodařilo uložit.'), true, 'Storage failure is explained without losing input');
        same(snapshot($pdo), $before, 'Failed structure edit must not leave a misleading backup');
        same($GLOBALS['renderedState']['group']['title'], 'Updated group', 'Failed group edit retains title');
        same($pdo->inTransaction(), false, 'Failed structure edit releases transaction');
        $pdo->exec('DROP TRIGGER fail_group');
        same(str_contains(runHandler('admin/recipe_content.php'), 'msg=saved'), true, 'Regular group edit succeeds');
        same($pdo->query('SELECT title FROM cms_recipe_ingredient_groups')->fetchColumn(), 'Updated group', 'Group is updated');
        same((int)$pdo->query('SELECT COUNT(*) FROM cms_recipe_structure_snapshots')->fetchColumn(), 1, 'Successful edit retains one backup');
        $pdo->exec("INSERT INTO cms_recipe_steps (id, recipe_id, instruction, sort_order) VALUES (2, 1, 'Bake', 20)");
        $_POST = ['csrf_token' => 'fixture-token', 'recipe_id' => '1', 'action' => 'move_step', 'step_id' => '1', 'direction' => 'down'];
        $before = snapshot($pdo);
        $pdo->exec("CREATE TRIGGER fail_second_move BEFORE UPDATE ON cms_recipe_steps WHEN OLD.id = 2 BEGIN SELECT RAISE(ABORT, 'fixture second move failure'); END");
        same(str_contains(runHandler('admin/recipe_content.php'), 'Změnu obsahu se nepodařilo uložit.'), true, 'Partial move reports failure');
        same(snapshot($pdo), $before, 'Failed second move rolls back first move and snapshot');
        $pdo->exec('DROP TRIGGER fail_second_move');
        same(str_contains(runHandler('admin/recipe_content.php'), 'msg=moved'), true, 'Both move writes succeed together');
        same((int)$pdo->query('SELECT sort_order FROM cms_recipe_steps WHERE id=1')->fetchColumn(), 20, 'First step moved');
        same((int)$pdo->query('SELECT sort_order FROM cms_recipe_steps WHERE id=2')->fetchColumn(), 10, 'Second step moved');
        $pdo->beginTransaction();
        same(recipeContentDeletePart($pdo, 1, 'step', 2, null), true, 'Part deletion supports editor-owned transaction');
        same($pdo->inTransaction(), true, 'Part deletion keeps caller transaction open');
        $pdo->rollBack();
        same((int)$pdo->query('SELECT COUNT(*) FROM cms_recipe_steps')->fetchColumn(), 2, 'Caller rollback restores deleted step');
    }
    echo 'Recipe integrity self-test OK (' . $checks . ' checks).' . PHP_EOL;
} catch (\Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL . $exception->getTraceAsString() . PHP_EOL);
    exit(1);
}
