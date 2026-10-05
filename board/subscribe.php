<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
sendNoStoreNoIndexHeaders();
requireHttpMethods(['GET', 'POST']);

if (!isModuleEnabled('board')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$boardLabel = boardModulePublicLabel();
$state = 'form';
$errors = [];
$errorFields = [];
$postedEmail = trim((string)($_POST['email'] ?? ''));
$postedCategoryIds = is_array($_POST['category_ids'] ?? null) ? $_POST['category_ids'] : [];

$categories = $pdo->query(
    "SELECT id, name
     FROM cms_board_categories
     ORDER BY sort_order, name"
)->fetchAll();
$validCategoryIds = array_map(static fn (array $category): int => (int)$category['id'], $categories);
$selectedCategoryIds = normalizeBoardSubscriberCategoryIds($postedCategoryIds, $validCategoryIds);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('board_subscribe', 3, 300);

    if (honeypotTriggered()) {
        $state = 'ok';
    } else {
        verifyCsrf();
        $email = function_exists('mb_strtolower')
            ? mb_strtolower($postedEmail, 'UTF-8')
            : strtolower($postedEmail);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Zadejte platnou e-mailovou adresu.';
            $errorFields[] = 'email';
        }
        if (!captchaVerify((string)($_POST['captcha'] ?? ''))) {
            $errors[] = publicCaptchaErrorMessage();
            $errorFields[] = 'captcha';
        }

        if ($errors === []) {
            rateLimitSubject('board_subscribe_email', $email, 3, 3600);
            try {
                $subscriber = prepareBoardSubscription($pdo, $email, $selectedCategoryIds);
                if ($subscriber['confirmed'] === 1) {
                    $state = 'ok';
                } else {
                    $state = sendBoardSubscriptionConfirmation($subscriber['email'], $subscriber['token']) ? 'ok' : 'mail_error';
                }
            } catch (\Throwable $e) {
                koraLog('warning', 'board subscribe failed', ['exception' => $e]);
                $state = 'error';
                $errors[] = 'Přihlášení se nepodařilo uložit. Zkuste to prosím později.';
            }
        } else {
            $state = 'error';
        }
    }
}

$captchaExpr = captchaGenerate();

renderPublicPage([
    'title' => 'Odběr vývěsky – ' . $siteName,
    'meta' => [
        'title' => 'Odběr vývěsky – ' . $siteName,
    ],
    'view' => 'modules/board-subscribe',
    'view_data' => [
        'boardLabel' => $boardLabel,
        'state' => $state,
        'errors' => $errors,
        'errorFields' => $errorFields,
        'captchaExpr' => $captchaExpr,
        'postedEmail' => $postedEmail,
        'categories' => $categories,
        'selectedCategoryIds' => $selectedCategoryIds,
    ],
    'current_nav' => 'board',
    'body_class' => 'page-board-subscribe',
    'page_kind' => 'utility',
]);
