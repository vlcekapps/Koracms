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

if (in_array($action, ['delete', 'purge'], true) && (string)($_POST['confirm_action'] ?? '') !== '1') {
    header('Location: ' . appendUrlQuery($redirect, ['action_error' => 'confirm']));
    exit;
}

if (!recipeApplyLifecycleAction($pdo, $id, $action)) {
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete') {
    releaseContentLock('recipe', $id);
    logAction('recipe_delete', "id={$id} soft=true");
    header('Location: ' . appendUrlQuery($redirect, ['msg' => 'deleted']));
    exit;
}

if ($action === 'restore') {
    logAction('recipe_restore', "id={$id}");
    header('Location: ' . appendUrlQuery($redirect, ['msg' => 'restored']));
    exit;
}

logAction('recipe_purge', "id={$id}");
header('Location: ' . appendUrlQuery($redirect, ['msg' => 'purged', 'status' => 'trash']));
exit;
