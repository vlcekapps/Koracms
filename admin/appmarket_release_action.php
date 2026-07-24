<?php

require_once __DIR__ . '/layout.php';
requireSuperAdmin();
requireModuleEnabled('appmarket');
requireHttpMethods(['POST']);
verifyCsrf();

$pdo = db_connect();
$releaseId = inputInt('post', 'release_id');
$action = trim((string)($_POST['action'] ?? ''));
$release = $releaseId !== null ? appmarketFindRelease($pdo, $releaseId) : null;
if ($release === null) {
    http_response_code(404);
    exit('Vydání nebylo nalezeno.');
}

$returnUrl = 'appmarket.php?app_id=' . (int)$release['app_id'];
if (in_array($action, ['publish', 'withdraw', 'delete'], true)
    && trim((string)($_POST['confirm_action'] ?? '')) !== $action
) {
    $_SESSION['appmarket_notice_error'] = match ($action) {
        'publish' => 'Před zveřejněním projděte kontrolní obrazovku a potvrďte bezpečnostní souhrn.',
        'withdraw' => 'Před stažením vydání potvrďte odstranění z veřejného katalogu a update API.',
        default => 'Před smazáním potvrďte trvalé odstranění konceptu a jeho APK.',
    };
    header('Location: ' . ($action === 'publish'
        ? 'appmarket_release_review.php?release_id=' . (int)$release['id']
        : $returnUrl));
    exit;
}

if ($action === 'publish') {
    $result = appmarketPublishRelease($pdo, (int)$release['id'], currentUserId() ?? 0);
    if (!$result['ok']) {
        $_SESSION['appmarket_notice_error'] = implode(' ', $result['errors']);
        header('Location: appmarket_release_review.php?release_id=' . (int)$release['id']);
        exit;
    } else {
        $_SESSION['appmarket_notice'] = 'Vydání bylo zveřejněno a je dostupné v katalogu i update API.';
    }
} elseif ($action === 'withdraw' && (string)$release['status'] === 'published') {
    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            "UPDATE cms_appmarket_releases SET status = 'withdrawn' WHERE id = ? AND status = 'published'"
        )->execute([(int)$release['id']]);
        $pdo->prepare(
            "UPDATE cms_appmarket_apps a
             SET status = 'draft'
             WHERE a.id = ?
               AND a.status = 'published'
               AND NOT EXISTS (
                 SELECT 1 FROM cms_appmarket_releases r
                 WHERE r.app_id = a.id AND r.status = 'published'
               )"
        )->execute([(int)$release['app_id']]);
        $pdo->commit();
        $_SESSION['appmarket_notice'] = 'Vydání bylo staženo z veřejné nabídky.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        koraLog('error', 'appmarket release withdrawal failed', [
            'app_id' => (int)$release['app_id'],
            'release_id' => (int)$release['id'],
            'exception' => $e,
        ]);
        $_SESSION['appmarket_notice_error'] = 'Vydání se nepodařilo bezpečně stáhnout.';
    }
} elseif ($action === 'delete' && (string)$release['status'] === 'draft') {
    $storageName = (string)$release['apk_storage_name'];
    $pdo->prepare("DELETE FROM cms_appmarket_releases WHERE id = ? AND status = 'draft'")
        ->execute([(int)$release['id']]);
    appmarketDeletePrivateApkIfUnused($pdo, $storageName);
    $_SESSION['appmarket_notice'] = 'Koncept vydání byl odstraněn.';
} else {
    $_SESSION['appmarket_notice_error'] = 'Tuto akci nelze pro aktuální stav vydání provést.';
}

header('Location: ' . $returnUrl);
exit;
