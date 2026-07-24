<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu receptů nemáte potřebné oprávnění.');
requireModuleEnabled('recipes');
verifyCsrf();

$sourceId = inputInt('post', 'id');
if ($sourceId === null || (string)($_POST['confirm_action'] ?? '') !== '1') {
    header('Location: ' . appendUrlQuery(BASE_URL . '/admin/recipes.php', ['action_error' => 'confirm']));
    exit;
}

$pdo = db_connect();
$newId = recipeDuplicate($pdo, $sourceId, currentUserId());
if ($newId === null) {
    header('Location: ' . BASE_URL . '/admin/recipes.php');
    exit;
}

logAction('recipe_clone', "source_id={$sourceId} new_id={$newId}");
header('Location: ' . appendUrlQuery(
    BASE_URL . '/admin/recipe_form.php',
    ['id' => $newId, 'msg' => 'duplicated']
));
exit;
