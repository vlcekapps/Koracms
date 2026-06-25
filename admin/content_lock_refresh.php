<?php

require_once __DIR__ . '/../db.php';

sendAdminJsonHeaders();

/**
 * @param array<string,mixed> $payload
 */
function contentLockJsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(
        $payload + ['request_id' => koraRequestId()],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

requireJsonHttpMethods(['POST'], ['ok' => false]);

if (!isLoggedIn()) {
    contentLockJsonResponse(['ok' => false], 401);
}

verifyCsrf();

$entityType = trim((string)($_POST['entity_type'] ?? ''));
$entityId = inputInt('post', 'entity_id');

if ($entityType === '' || $entityId === null || $entityId <= 0) {
    contentLockJsonResponse(['ok' => false], 400);
}

$result = refreshContentLock($entityType, $entityId);
contentLockJsonResponse(['ok' => $result]);
