<?php

require_once __DIR__ . '/layout.php';
requireCapability('appmarket_manage', 'Přístup odepřen. Pro správu Appmarketu nemáte potřebné oprávnění.');
requireModuleEnabled('appmarket');
requireHttpMethods(['POST']);
verifyCsrf();

$pdo = db_connect();
$appId = inputInt('post', 'app_id');
$id = inputInt('post', 'id');
if ($appId === null || appmarketFindApp($pdo, $appId) === null) {
    http_response_code(404);
    exit('Aplikace nebyla nalezena.');
}
$form = [];
foreach (['version_name', 'platform', 'system_requirements', 'release_notes', 'status', 'release_channel', 'revision'] as $key) {
    $form[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
}
$form['release_notes'] = appmarketNormalizeReleaseNotes($form['release_notes']);
$existing = $id !== null ? appmarketFindRelease($pdo, $id) : null;
if ($form['status'] === 'published' || ($existing !== null && $existing['status'] === 'published')) {
    requireSuperAdmin();
}
$result = appmarketSaveCatalogRelease(
    $pdo,
    $appId,
    $id,
    $form,
    is_array($_FILES['release_file'] ?? null) ? $_FILES['release_file'] : [],
    currentUserId() ?? 0,
    isSuperAdmin()
);
if (!$result['ok']) {
    $_SESSION['appmarket_catalog_flash'] = ['app_id' => $appId, 'id' => $id, 'form' => $form, 'errors' => $result['errors']];
    header('Location: appmarket_release_form.php?app_id=' . $appId . ($id !== null ? '&id=' . $id : ''));
    exit;
}
$_SESSION['appmarket_notice'] = $id !== null
    ? 'Vydání bylo aktualizováno.' : 'Nové vydání bylo uloženo. Předchozí verze zůstávají v historii.';
header('Location: appmarket.php?app_id=' . $appId);
exit;
