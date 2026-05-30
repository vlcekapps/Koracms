<?php

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex');

$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($requestMethod, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    echo "Method not allowed\n";
    exit;
}
if ($requestMethod === 'HEAD') {
    exit;
}

echo "User-agent: *\n";
echo "Disallow: " . BASE_URL . "/admin/\n";
echo "Disallow: " . BASE_URL . "/uploads/forms/\n";
echo "Disallow: " . BASE_URL . "/uploads/backups/\n";
echo "Disallow: " . BASE_URL . "/uploads/tmp/\n";
echo "Disallow: " . BASE_URL . "/migrate.php\n";
echo "Disallow: " . BASE_URL . "/install.php\n";
echo "\n";
echo "Sitemap: " . siteUrl('/sitemap.xml') . "\n";
