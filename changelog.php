<?php

require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$changelogPath = __DIR__ . '/CHANGELOG.md';
if (!is_file($changelogPath) || !is_readable($changelogPath)) {
    renderPublicNotFoundPage([
        'title' => 'Changelog není dostupný',
        'view_data' => [
            'title' => 'Changelog není dostupný',
            'message' => 'Seznam změn Kora CMS se teď nepodařilo načíst.',
        ],
        'meta' => [
            'url' => BASE_URL . '/changelog',
        ],
        'body_class' => 'page-not-found',
    ]);
}

$changelogMarkdown = (string)file_get_contents($changelogPath);
$changelogHtml = renderProjectMarkdown($changelogMarkdown);
$siteName = getSetting('site_name', 'Kora CMS');

renderPublicPage([
    'title' => 'Changelog Kora CMS – ' . $siteName,
    'meta' => [
        'title' => 'Changelog Kora CMS – ' . $siteName,
        'description' => 'Veřejný seznam změn redakčního systému Kora CMS včetně připravovaných změn.',
        'url' => BASE_URL . '/changelog',
        'canonical' => BASE_URL . '/changelog',
    ],
    'view' => 'changelog',
    'view_data' => [
        'changelogHtml' => $changelogHtml,
    ],
    'current_nav' => 'changelog',
    'page_kind' => 'utility',
    'body_class' => 'page-changelog',
]);
