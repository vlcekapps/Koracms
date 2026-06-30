<?php

require_once __DIR__ . '/../db.php';

sendAdminJsonHeaders();

/**
 * @param array<string,mixed> $payload
 */
function contentLockJsonResponse(array $payload, int $statusCode = 200): void
{
    sendJsonResponse($payload, $statusCode);
}

requireJsonHttpMethods(['POST'], ['ok' => false]);

if (!isLoggedIn()) {
    contentLockJsonResponse(['ok' => false], 401);
}

verifyCsrf(false);

$entityType = trim((string)($_POST['entity_type'] ?? ''));
$entityId = inputInt('post', 'entity_id');

if ($entityType === '' || $entityId === null || $entityId <= 0) {
    contentLockJsonResponse(['ok' => false, 'csrf_token' => csrfToken()], 400);
}

$result = refreshContentLock($entityType, $entityId);
contentLockJsonResponse(['ok' => $result, 'csrf_token' => csrfToken()]);
