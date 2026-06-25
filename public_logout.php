<?php

require_once __DIR__ . '/db.php';

$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($requestMethod !== 'GET') {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Allow: GET');
    http_response_code(405);
    echo "Method not allowed\n";
    exit;
}

logout();
header('Location: ' . BASE_URL . '/index.php');
exit;
