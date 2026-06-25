<?php

/**
 * AJAX endpoint pro drag & drop řazení.
 * POST: csrf_token, module, order[] (pole ID v novém pořadí)
 */
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');

sendAdminJsonHeaders();

/**
 * @param array<string,mixed> $payload
 */
function reorderJsonResponse(array $payload, int $statusCode = 200): void
{
    sendJsonResponse($payload, $statusCode);
}

requireJsonHttpMethods(['POST'], ['ok' => false]);

$csrfToken = trim($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrfToken === '' || !hash_equals(csrfToken(), $csrfToken)) {
    reorderJsonResponse(['ok' => false], 403);
}

$module = trim($_POST['module'] ?? '');
$order  = array_values(array_filter(array_map('intval', (array)($_POST['order'] ?? []))));

if ($order === [] || $module === '') {
    reorderJsonResponse(['ok' => false]);
}

$pdo = db_connect();

// Podpora přesunu widgetů mezi zónami
$zone = trim($_POST['zone'] ?? '');
if ($module === 'widgets' && $zone !== '') {
    if (!currentUserHasCapability('settings_manage')) {
        reorderJsonResponse(['ok' => false], 403);
    }
    $stmt = $pdo->prepare("UPDATE cms_widgets SET sort_order = ?, zone = ? WHERE id = ?");
    foreach ($order as $position => $id) {
        $stmt->execute([$position + 1, $zone, $id]);
    }
    logAction('reorder', "module=widgets zone={$zone} order=" . implode(',', $order));
    reorderJsonResponse(['ok' => true]);
}

$config = match ($module) {
    'pages' => ['table' => 'cms_pages', 'column' => 'nav_order', 'capability' => 'content_manage_shared'],
    'blogs' => ['table' => 'cms_blogs', 'column' => 'sort_order', 'capability' => 'blog_taxonomies_manage'],
    'form_fields' => ['table' => 'cms_form_fields', 'column' => 'sort_order', 'capability' => 'content_manage_shared'],
    'widgets' => ['table' => 'cms_widgets', 'column' => 'sort_order', 'capability' => 'settings_manage'],
    default => null,
};

if (!$config || !currentUserHasCapability($config['capability'])) {
    reorderJsonResponse(['ok' => false], 403);
}

$stmt = $pdo->prepare("UPDATE {$config['table']} SET {$config['column']} = ? WHERE id = ?");
foreach ($order as $position => $id) {
    $stmt->execute([$position + 1, $id]);
}

logAction('reorder', "module={$module} order=" . implode(',', $order));
reorderJsonResponse(['ok' => true]);
