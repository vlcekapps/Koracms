<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu receptů nemáte potřebné oprávnění.');
requireModuleEnabled('recipes');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$action = trim((string)($_POST['action'] ?? ''));
$redirect = BASE_URL . '/admin/recipes.php';
if ($id === null || !in_array($action, ['delete', 'restore', 'purge'], true)) {
    header('Location: ' . $redirect);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM cms_recipes WHERE id = ?');
$stmt->execute([$id]);
$recipe = $stmt->fetch();
if (!is_array($recipe)) {
    header('Location: ' . $redirect);
    exit;
}

if (in_array($action, ['delete', 'purge'], true) && (string)($_POST['confirm_action'] ?? '') !== '1') {
    header('Location: ' . appendUrlQuery($redirect, ['action_error' => 'confirm']));
    exit;
}

if ($action === 'delete' && $recipe['deleted_at'] === null) {
    deleteRedirectsTargetingPath($pdo, recipePublicPath($recipe));
    $pdo->prepare("UPDATE cms_recipes SET deleted_at = NOW(), status = 'draft' WHERE id = ?")->execute([$id]);
    releaseContentLock('recipe', $id);
    logAction('recipe_delete', "id={$id} soft=true");
    header('Location: ' . appendUrlQuery($redirect, ['msg' => 'deleted']));
    exit;
}

if ($action === 'restore' && $recipe['deleted_at'] !== null) {
    $pdo->prepare("UPDATE cms_recipes SET deleted_at = NULL, status = 'draft' WHERE id = ?")->execute([$id]);
    logAction('recipe_restore', "id={$id}");
    header('Location: ' . appendUrlQuery($redirect, ['msg' => 'restored']));
    exit;
}

if ($action === 'purge' && $recipe['deleted_at'] !== null) {
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM cms_recipe_steps WHERE recipe_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM cms_recipe_ingredients WHERE recipe_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM cms_recipe_ingredient_groups WHERE recipe_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM cms_recipe_structure_snapshots WHERE recipe_id = ?')->execute([$id]);
        $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'recipe' AND entity_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM cms_content_locks WHERE entity_type = 'recipe' AND entity_id = ?")->execute([$id]);
        deleteRedirectsTargetingPath($pdo, recipePublicPath($recipe));
        $pdo->prepare('DELETE FROM cms_recipes WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    logAction('recipe_purge', "id={$id}");
    header('Location: ' . appendUrlQuery($redirect, ['msg' => 'purged', 'status' => 'trash']));
    exit;
}

header('Location: ' . $redirect);
exit;
