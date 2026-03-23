<?php
require_once __DIR__ . '/layout.php';
requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/themes.php');
    exit;
}

verifyCsrf();

$previewAction = trim((string)($_POST['preview_action'] ?? ''));
$redirectTarget = internalRedirectTarget(
    (string)($_POST['redirect_target'] ?? ''),
    BASE_URL . '/admin/themes.php'
);

if ($previewAction === 'clear') {
    clearThemePreview();
    logAction('theme_preview_stop');
}

header('Location: ' . $redirectTarget);
exit;
