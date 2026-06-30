<?php

require_once __DIR__ . '/db.php';
checkMaintenanceMode();
sendNoStoreNoIndexHeaders();

function newsletterWidgetRedirect(string $target): void
{
    sendNoStoreNoIndexHeaders();
    header('Location: ' . $target);
    exit;
}

$defaultRedirect = BASE_URL . '/subscribe.php';

if (!isModuleEnabled('newsletter')) {
    newsletterWidgetRedirect($defaultRedirect);
}

rateLimit('subscribe_widget', 3, 300);

newsletterWidgetRedirect($defaultRedirect);
