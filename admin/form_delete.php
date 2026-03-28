<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');
verifyCsrf();

$id = inputInt('post', 'id');
$redirect = internalRedirectTarget($_POST['redirect'] ?? '', BASE_URL . '/admin/forms.php');
if ($id !== null) {
    $pdo = db_connect();
    $pdo->prepare("DELETE FROM cms_form_submissions WHERE form_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_form_fields WHERE form_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cms_forms WHERE id = ?")->execute([$id]);
    logAction('form_delete', "id={$id}");
}

header('Location: ' . $redirect);
exit;
