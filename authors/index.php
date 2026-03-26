<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

$pdo = db_connect();
$authors = fetchPublicAuthors($pdo);
$siteName = getSetting('site_name', 'Kora CMS');
$blogEnabled = isModuleEnabled('blog');

renderPublicPage([
    'title' => 'Autoři – ' . $siteName,
    'meta' => [
        'title' => 'Autoři – ' . $siteName,
        'description' => 'Veřejné profily autorů a jejich publikovaných článků.',
        'url' => authorIndexUrl(),
        'type' => 'website',
    ],
    'view' => 'account/authors',
    'view_data' => [
        'authors' => $authors,
        'blogEnabled' => $blogEnabled,
    ],
    'current_nav' => $blogEnabled ? 'blog' : '',
    'body_class' => 'page-authors',
    'page_kind' => 'listing',
]);
