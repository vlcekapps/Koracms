<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

verifyCsrf();

$entityType = trim((string)($_POST['entity_type'] ?? ''));
$entityId = inputInt('post', 'entity_id');

if ($entityType === '' || $entityId === null || $entityId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$result = refreshContentLock($entityType, $entityId);
echo json_encode(['ok' => $result]);
