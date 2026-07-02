<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

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

$saveSubscriberCategories = static function (PDO $pdo, int $subscriberId, array $categoryIds): void {
    $pdo->prepare("DELETE FROM cms_board_subscriber_categories WHERE subscriber_id = ?")->execute([$subscriberId]);
    if ($categoryIds === []) {
        return;
    }
    $stmt = $pdo->prepare(
        "INSERT INTO cms_board_subscriber_categories (subscriber_id, category_id)
         VALUES (?, ?)"
    );
    foreach ($categoryIds as $categoryId) {
        $stmt->execute([$subscriberId, $categoryId]);
    }
};

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
            $errors[] = 'Chybná odpověď na ověřovací otázku.';
            $errorFields[] = 'captcha';
        }

        if ($errors === []) {
            rateLimitSubject('board_subscribe_email', $email, 3, 3600);
            $token = bin2hex(random_bytes(32));
            $allCategories = $selectedCategoryIds === [] ? 1 : 0;

            try {
                $existingStmt = $pdo->prepare(
                    "SELECT id, confirmed
                     FROM cms_board_subscribers
                     WHERE email = ?
                     LIMIT 1"
                );
                $existingStmt->execute([$email]);
                $existing = $existingStmt->fetch() ?: null;

                if ($existing && (int)$existing['confirmed'] === 1) {
                    $state = 'ok';
                } elseif ($existing) {
                    $subscriberId = (int)$existing['id'];
                    $pdo->prepare(
                        "UPDATE cms_board_subscribers
                         SET token = ?, confirmed = 0, all_categories = ?, created_at = NOW(), confirmed_at = NULL
                         WHERE id = ?"
                    )->execute([$token, $allCategories, $subscriberId]);
                    $saveSubscriberCategories($pdo, $subscriberId, $selectedCategoryIds);
                    $state = sendBoardSubscriptionConfirmation($email, $token) ? 'ok' : 'mail_error';
                } else {
                    $pdo->prepare(
                        "INSERT INTO cms_board_subscribers (email, token, confirmed, all_categories)
                         VALUES (?, ?, 0, ?)"
                    )->execute([$email, $token, $allCategories]);
                    $subscriberId = (int)$pdo->lastInsertId();
                    $saveSubscriberCategories($pdo, $subscriberId, $selectedCategoryIds);
                    $state = sendBoardSubscriptionConfirmation($email, $token) ? 'ok' : 'mail_error';
                }
            } catch (\PDOException $e) {
                koraLog('warning', 'board subscribe failed', ['exception' => $e]);
                $state = 'ok';
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
