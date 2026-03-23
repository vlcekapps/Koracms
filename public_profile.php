<?php
require_once __DIR__ . '/db.php';
checkMaintenanceMode();
requirePublicLogin(BASE_URL . '/public_profile.php');

$pdo      = db_connect();
$userId   = currentUserId();
$siteName = getSetting('site_name', 'Kora CMS');

$profileSuccess  = false;
$passwordSuccess = false;
$profileErrors   = [];
$passwordErrors  = [];

$stmt = $pdo->prepare("SELECT * FROM cms_users WHERE id = ?");
$stmt->execute([$userId]);
$profileRow = $stmt->fetch();

if (!$profileRow) {
    header('Location: ' . BASE_URL . '/public_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = $_POST['action'] ?? 'profile';

    if ($action === 'password') {
        $currentPass = $_POST['current_pass'] ?? '';
        $newPass     = $_POST['new_pass'] ?? '';
        $newPass2    = $_POST['new_pass2'] ?? '';

        if (!password_verify($currentPass, $profileRow['password'])) {
            $passwordErrors[] = 'Současné heslo není správné.';
        }
        if (strlen($newPass) < 8) {
            $passwordErrors[] = 'Nové heslo musí mít alespoň 8 znaků.';
        }
        if ($newPass !== $newPass2) {
            $passwordErrors[] = 'Nová hesla se neshodují.';
        }

        if (empty($passwordErrors)) {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE cms_users SET password = ? WHERE id = ?")
                ->execute([$hash, $userId]);
            $passwordSuccess = true;
        }
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');

        if ($firstName === '') {
            $profileErrors[] = 'Jméno je povinné.';
        }
        if ($lastName === '') {
            $profileErrors[] = 'Příjmení je povinné.';
        }
        if ($phone === '') {
            $profileErrors[] = 'Telefon je povinný.';
        }

        if (empty($profileErrors)) {
            $pdo->prepare(
                "UPDATE cms_users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?"
            )->execute([$firstName, $lastName, $phone, $userId]);

            $displayName = trim($firstName . ' ' . $lastName);
            if ($displayName === '') {
                $displayName = $profileRow['email'];
            }
            $_SESSION['cms_user_name'] = $displayName;

            $profileSuccess = true;
            $profileRow = array_merge($profileRow, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
            ]);
        }
    }
}

renderPublicPage([
    'title' => 'Můj profil – ' . $siteName,
    'meta' => [
        'title' => 'Můj profil – ' . $siteName,
    ],
    'view' => 'account/profile',
    'view_data' => [
        'profileRow' => $profileRow,
        'profileSuccess' => $profileSuccess,
        'passwordSuccess' => $passwordSuccess,
        'profileErrors' => $profileErrors,
        'passwordErrors' => $passwordErrors,
        'showReservationsLink' => isModuleEnabled('reservations'),
    ],
    'body_class' => 'page-account page-public-profile',
    'page_kind' => 'utility',
]);
