<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();

$siteName = getSetting('site_name', 'Kora CMS');
$success  = false;
$error    = false;

$token = trim($_GET['token'] ?? '');

if ($token !== '') {
    $pdo  = db_connect();
    $stmt = $pdo->prepare(
        "SELECT id FROM cms_users WHERE confirmation_token = ? AND is_confirmed = 0"
    );
    $stmt->execute([$token]);
    $userRow = $stmt->fetch();

    if ($userRow) {
        $pdo->prepare(
            "UPDATE cms_users SET is_confirmed = 1, confirmation_token = '' WHERE id = ?"
        )->execute([$userRow['id']]);
        $success = true;
    } else {
        $error = true;
    }
} else {
    $error = true;
}

renderPublicPage([
    'title' => 'Potvrzení e-mailu – ' . $siteName,
    'meta' => [
        'title' => 'Potvrzení e-mailu – ' . $siteName,
    ],
    'view' => 'utility/status',
    'view_data' => [
        'kicker' => 'Ověření účtu',
        'title' => 'Potvrzení e-mailu',
        'variant' => $success ? 'success' : 'error',
        'announceRole' => $success ? 'status' : 'alert',
        'messages' => $success
            ? ['Váš e-mail byl úspěšně ověřen.', 'Nyní se můžete přihlásit.']
            : ['Neplatný nebo již použitý potvrzovací odkaz.'],
        'actions' => $success
            ? [
                ['href' => BASE_URL . '/public_login.php', 'label' => 'Přejít na přihlášení', 'class' => 'button-primary'],
              ]
            : [
                ['href' => BASE_URL . '/register.php', 'label' => 'Zaregistrovat se znovu', 'class' => 'button-primary'],
              ],
    ],
    'body_class' => 'page-status page-confirm-email',
    'page_kind' => 'utility',
]);
