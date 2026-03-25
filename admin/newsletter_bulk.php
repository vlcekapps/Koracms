<?php
require_once __DIR__ . '/../db.php';
requireCapability('newsletter_manage', 'Přístup odepřen. Pro správu odběratelů newsletteru nemáte potřebné oprávnění.');
verifyCsrf();

$redirect = internalRedirectTarget(trim($_POST['redirect'] ?? ''), BASE_URL . '/admin/newsletter.php');
$action = trim($_POST['action'] ?? '');
$allowedActions = ['confirm', 'resend', 'delete'];
$ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), static fn(int $id): bool => $id > 0)));

if ($ids === [] || !in_array($action, $allowedActions, true)) {
    header('Location: ' . $redirect);
    exit;
}

$pdo = db_connect();
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    "SELECT id, email, confirmed
     FROM cms_subscribers
     WHERE id IN ({$placeholders})"
);
$stmt->execute($ids);
$subscribers = $stmt->fetchAll();

if ($subscribers === []) {
    header('Location: ' . $redirect);
    exit;
}

$appendParams = static function (string $target, array $params): string {
    return appendUrlQuery($target, $params);
};

if ($action === 'confirm') {
    $pendingIds = [];
    foreach ($subscribers as $subscriber) {
        if ((int)$subscriber['confirmed'] === 0) {
            $pendingIds[] = (int)$subscriber['id'];
        }
    }

    if ($pendingIds === []) {
        header('Location: ' . $appendParams($redirect, ['ok' => 'bulk_no_change']));
        exit;
    }

    $confirmPlaceholders = implode(',', array_fill(0, count($pendingIds), '?'));
    $pdo->prepare(
        "UPDATE cms_subscribers
         SET confirmed = 1
         WHERE id IN ({$confirmPlaceholders})"
    )->execute($pendingIds);

    logAction('newsletter_subscriber_bulk_confirm', 'count=' . count($pendingIds));
    header('Location: ' . $appendParams($redirect, ['ok' => 'bulk_confirmed', 'count' => (string)count($pendingIds)]));
    exit;
}

if ($action === 'resend') {
    $pendingSubscribers = [];
    foreach ($subscribers as $subscriber) {
        if ((int)$subscriber['confirmed'] === 0) {
            $pendingSubscribers[] = $subscriber;
        }
    }

    if ($pendingSubscribers === []) {
        header('Location: ' . $appendParams($redirect, ['ok' => 'bulk_no_change']));
        exit;
    }

    $sentCount = 0;
    $failedCount = 0;
    $updateTokenStmt = $pdo->prepare(
        "UPDATE cms_subscribers
         SET token = ?
         WHERE id = ?"
    );

    foreach ($pendingSubscribers as $subscriber) {
        $token = bin2hex(random_bytes(32));
        $updateTokenStmt->execute([$token, (int)$subscriber['id']]);
        $mailSent = sendNewsletterSubscriptionConfirmation((string)$subscriber['email'], $token);
        if ($mailSent) {
            $sentCount++;
        } else {
            $failedCount++;
        }
    }

    logAction(
        'newsletter_subscriber_bulk_resend',
        'count=' . count($pendingSubscribers) . ';sent=' . $sentCount . ';failed=' . $failedCount
    );

    $target = $redirect;
    if ($sentCount > 0) {
        $target = $appendParams($target, ['ok' => 'bulk_resent', 'count' => (string)$sentCount]);
    }
    if ($failedCount > 0) {
        $target = $appendParams($target, ['error' => 'bulk_resend_failed', 'failed' => (string)$failedCount]);
    }
    if ($sentCount === 0 && $failedCount === 0) {
        $target = $appendParams($target, ['ok' => 'bulk_no_change']);
    }

    header('Location: ' . $target);
    exit;
}

$deleteIds = array_map(static fn(array $subscriber): int => (int)$subscriber['id'], $subscribers);
$deletePlaceholders = implode(',', array_fill(0, count($deleteIds), '?'));
$pdo->prepare("DELETE FROM cms_subscribers WHERE id IN ({$deletePlaceholders})")->execute($deleteIds);
logAction('newsletter_subscriber_bulk_delete', 'count=' . count($deleteIds));
header('Location: ' . $appendParams($redirect, ['ok' => 'bulk_deleted', 'count' => (string)count($deleteIds)]));
exit;
