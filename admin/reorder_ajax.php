<?php
/**
 * AJAX endpoint pro drag & drop řazení.
 * POST: csrf_token, module, order[] (pole ID v novém pořadí)
 */
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$csrfToken = trim($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrfToken === '' || !hash_equals(csrfToken(), $csrfToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$module = trim($_POST['module'] ?? '');
$order  = array_values(array_filter(array_map('intval', (array)($_POST['order'] ?? []))));

if ($order === [] || $module === '') {
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = db_connect();

// Podpora přesunu widgetů mezi zónami
$zone = trim($_POST['zone'] ?? '');
if ($module === 'widgets' && $zone !== '') {
    if (!currentUserHasCapability('settings_manage')) {
        http_response_code(403);
        echo json_encode(['ok' => false]);
        exit;
    }
    $stmt = $pdo->prepare("UPDATE cms_widgets SET sort_order = ?, zone = ? WHERE id = ?");
    foreach ($order as $position => $id) {
        $stmt->execute([$position + 1, $zone, $id]);
    }
    logAction('reorder', "module=widgets zone={$zone} order=" . implode(',', $order));
    echo json_encode(['ok' => true]);
    exit;
}

$config = match ($module) {
    'pages' => ['table' => 'cms_pages', 'column' => 'nav_order', 'capability' => 'content_manage_shared'],
    'blogs' => ['table' => 'cms_blogs', 'column' => 'sort_order', 'capability' => 'blog_taxonomies_manage'],
    'form_fields' => ['table' => 'cms_form_fields', 'column' => 'sort_order', 'capability' => 'content_manage_shared'],
    'widgets' => ['table' => 'cms_widgets', 'column' => 'sort_order', 'capability' => 'settings_manage'],
    default => null,
};

if (!$config || !currentUserHasCapability($config['capability'])) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$stmt = $pdo->prepare("UPDATE {$config['table']} SET {$config['column']} = ? WHERE id = ?");
foreach ($order as $position => $id) {
    $stmt->execute([$position + 1, $id]);
}

logAction('reorder', "module={$module} order=" . implode(',', $order));
echo json_encode(['ok' => true]);
