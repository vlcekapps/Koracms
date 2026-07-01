<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

requireLogin(BASE_URL . '/admin/login.php');
$requestMethod = requireJsonHttpMethods(['GET', 'HEAD']);

sendAdminJsonHeaders();
if ($requestMethod === 'HEAD') {
    exit;
}

$pdo = db_connect();
$query = trim((string)($_GET['q'] ?? ''));
$limit = filter_var($_GET['limit'] ?? 20, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
$results = array_map(
    static function (array $item): array {
        return [
            'type' => (string)($item['type'] ?? ''),
            'key' => (string)($item['key'] ?? ''),
            'label' => (string)($item['label'] ?? ''),
            'description' => (string)($item['description'] ?? ''),
            'url' => (string)($item['url'] ?? ''),
            'module' => (string)($item['module'] ?? ''),
            'badge' => (string)($item['badge'] ?? ''),
            'pinned' => !empty($item['pinned']),
            'pin_available' => !empty($item['pin_available']),
        ];
    },
    adminCommandSearch($pdo, $query, $limit !== false ? (int)$limit : 20)
);

sendJsonResponse([
    'query' => $query,
    'results' => $results,
    'csrf_token' => csrfToken(),
]);
