<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('chat')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('chat', 5, 120);

    if (honeypotTriggered()) {
        header('Location: ' . BASE_URL . '/chat/index.php');
        exit;
    }

    verifyCsrf();

    if (!captchaVerify($_POST['captcha'] ?? '')) {
        $errors[] = 'Nesprávná odpověď na ověřovací příklad.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $web = trim($_POST['web'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '') {
        $errors[] = 'Jméno je povinný údaj.';
    }
    if ($message === '') {
        $errors[] = 'Zpráva je povinný údaj.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Neplatná e-mailová adresa.';
    }
    if ($web !== '' && !filter_var($web, FILTER_VALIDATE_URL)) {
        $web = '';
    }

    if (empty($errors)) {
        try {
            $pdo->prepare(
                "INSERT INTO cms_chat (name, email, web, message, status)
                 VALUES (?, ?, ?, ?, 'new')"
            )->execute([$name, $email, $web, $message]);

            notifyChatMessage($name, $message);

            header('Location: ' . BASE_URL . '/chat/index.php');
            exit;
        } catch (\PDOException $e) {
            error_log('chat INSERT failed: ' . $e->getMessage());
            $errors[] = 'Zprávu se nepodařilo uložit. Zkuste to prosím později.';
        }
    }
}

$captchaExpr = captchaGenerate();
$messages = $pdo->query(
    "SELECT name, email, web, message, created_at FROM cms_chat ORDER BY created_at DESC LIMIT 50"
)->fetchAll();

renderPublicPage([
    'title' => 'Chat – ' . $siteName,
    'meta' => [
        'title' => 'Chat – ' . $siteName,
        'url' => BASE_URL . '/chat/index.php',
    ],
    'view' => 'modules/chat',
    'view_data' => [
        'messages' => $messages,
        'errors' => $errors,
        'captchaExpr' => $captchaExpr,
        'formData' => [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'web' => trim($_POST['web'] ?? ''),
            'message' => trim($_POST['message'] ?? ''),
        ],
    ],
    'current_nav' => 'chat',
    'body_class' => 'page-chat',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/chat.php',
]);
