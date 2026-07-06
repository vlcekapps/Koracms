<?php

require_once __DIR__ . '/../db.php';
requireCapability('users_manage', 'Přístup odepřen. Pro mazání uživatelských účtů nemáte potřebné oprávnění.');
verifyCsrf();

$id = inputInt('post', 'id');
$redirectToUsers = static function (array $params = []): void {
    $target = BASE_URL . '/admin/users.php';
    if ($params !== []) {
        $target .= '?' . http_build_query($params);
    }
    header('Location: ' . $target);
    exit;
};

if ($id === null) {
    $redirectToUsers(['delete_error' => 'invalid']);
}

if ($id === currentUserId()) {
    $redirectToUsers(['delete_error' => 'self', 'delete_error_id' => $id]);
}

$pdo = db_connect();
$accountStmt = $pdo->prepare('SELECT id, email, role, is_superadmin FROM cms_users WHERE id = ?');
$accountStmt->execute([$id]);
$account = $accountStmt->fetch();
if (!$account || (int)$account['is_superadmin'] === 1) {
    $redirectToUsers(['delete_error' => 'invalid', 'delete_error_id' => $id]);
}

$confirmFieldName = 'confirm_user_delete_' . $id;
$deleteConfirmed = isset($_POST[$confirmFieldName])
    && (string)$_POST[$confirmFieldName] === '1';
if (!$deleteConfirmed) {
    $redirectToUsers(['delete_error' => 'confirm_required', 'delete_error_id' => $id]);
}

try {
    $pdo->prepare("DELETE FROM cms_admin_shortcuts WHERE user_id = ?")->execute([$id]);
    $deleteStmt = $pdo->prepare("DELETE FROM cms_users WHERE id = ? AND is_superadmin = 0");
    $deleteStmt->execute([$id]);
    if ($deleteStmt->rowCount() > 0) {
        logAction(
            'user_delete',
            'id=' . $id
            . ' role=' . (string)$account['role']
        );
        $redirectToUsers(['deleted' => '1']);
    }
} catch (\PDOException $e) {
    koraLog('warning', 'user delete failed', [
        'user_id' => $id,
        'exception' => $e,
    ]);
    $redirectToUsers(['delete_error' => 'failed', 'delete_error_id' => $id]);
}

$redirectToUsers(['delete_error' => 'invalid', 'delete_error_id' => $id]);
