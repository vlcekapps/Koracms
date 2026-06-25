<?php

require_once __DIR__ . '/db.php';

$isHeadRequest = requireReadOnlyHttpMethod();
sendReadOnlyContentHeaders('text/plain; charset=UTF-8', $isHeadRequest, '', 'noindex');

echo "User-agent: *\n";
echo "Disallow: " . BASE_URL . "/admin/\n";
echo "Disallow: " . BASE_URL . "/uploads/forms/\n";
echo "Disallow: " . BASE_URL . "/uploads/backups/\n";
echo "Disallow: " . BASE_URL . "/uploads/tmp/\n";
echo "Disallow: " . BASE_URL . "/migrate.php\n";
echo "Disallow: " . BASE_URL . "/install.php\n";
echo "\n";
echo "Sitemap: " . siteUrl('/sitemap.xml') . "\n";
