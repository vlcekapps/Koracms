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
if ($release['metadata_source'] === 'manual' && in_array($action, ['publish', 'distribution'], true)) {
    $_SESSION['appmarket_notice_error'] = 'Obecné vydání spravujte v editoru verzí, nikoli přes Android distribuční akce.';
    header('Location: ' . $returnUrl);
    exit;
}
if (in_array($action, ['publish', 'distribution', 'withdraw', 'delete'], true)
    && trim((string)($_POST['confirm_action'] ?? '')) !== $action
) {
    $_SESSION['appmarket_notice_error'] = match ($action) {
        'publish' => 'Před zveřejněním projděte kontrolní obrazovku a potvrďte bezpečnostní souhrn.',
        'distribution' => 'Před změnou postupného nasazení potvrďte distribuční zásah.',
        'withdraw' => 'Před stažením vydání potvrďte odstranění z veřejného katalogu a update API.',
        default => 'Před smazáním potvrďte trvalé odstranění konceptu a jeho APK.',
    };
    header('Location: ' . ($action === 'publish'
        ? 'appmarket_release_review.php?release_id=' . (int)$release['id']
        : $returnUrl));
    exit;
}

if ($action === 'publish') {
    $updatePriority = trim((string)($_POST['update_priority'] ?? 'normal'));
    $requiredBelowVersionCode = trim((string)($_POST['required_below_version_code'] ?? ''));
    $releaseChannel = trim((string)($_POST['release_channel'] ?? 'stable'));
    $rolloutPercentage = trim((string)($_POST['rollout_percentage'] ?? '100'));
    $policy = appmarketNormalizeReleasePolicy(
        $updatePriority,
        $requiredBelowVersionCode,
        (int)$release['version_code'],
        $releaseChannel,
        $rolloutPercentage
    );
    $result = $policy['errors'] === []
        ? appmarketPublishRelease(
            $pdo,
            (int)$release['id'],
            currentUserId() ?? 0,
            $policy['priority'],
            $policy['required_below_version_code'],
            $policy['channel'],
            $policy['rollout_percentage']
        )
        : ['ok' => false, 'errors' => $policy['errors']];
    if (!$result['ok']) {
        if ($policy['errors'] !== []) {
            $_SESSION['appmarket_policy_flash'] = [
                'update_priority' => $updatePriority,
                'required_below_version_code' => $requiredBelowVersionCode,
                'release_channel' => $releaseChannel,
                'rollout_percentage' => $rolloutPercentage,
                'priority_error' => $policy['priority_error'],
                'required_below_error' => $policy['required_below_error'],
                'channel_error' => $policy['channel_error'],
                'rollout_error' => $policy['rollout_error'],
                'errors' => $policy['errors'],
            ];
        }
        $_SESSION['appmarket_notice_error'] = implode(' ', $result['errors']);
        header('Location: appmarket_release_review.php?release_id=' . (int)$release['id']);
        exit;
    } else {
        $_SESSION['appmarket_notice'] = 'Vydání bylo zveřejněno a je dostupné v katalogu i update API.';
    }
} elseif ($action === 'distribution' && (string)$release['status'] === 'published') {
    $rolloutPercentage = appmarketNormalizeRolloutPercentage(
        trim((string)($_POST['rollout_percentage'] ?? ''))
    );
    if ($rolloutPercentage === null) {
        $_SESSION['appmarket_notice_error'] = 'Postupné nasazení musí být celé číslo od 0 do 100 procent.';
    } else {
        try {
            $pdo->prepare(
                "UPDATE cms_appmarket_releases
                 SET rollout_percentage = ?
                 WHERE id = ? AND status = 'published'"
            )->execute([$rolloutPercentage, (int)$release['id']]);
            $_SESSION['appmarket_notice'] = $rolloutPercentage === 0
                ? 'Distribuce aktualizace byla pozastavena. Veřejné stažení vydání zůstává dostupné.'
                : 'Postupné nasazení bylo nastaveno na ' . $rolloutPercentage . ' %.';
        } catch (Throwable $e) {
            koraLog('error', 'appmarket release rollout update failed', [
                'release_id' => (int)$release['id'],
                'exception' => $e,
            ]);
            $_SESSION['appmarket_notice_error'] = 'Postupné nasazení se nepodařilo bezpečně změnit.';
        }
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
    try {
        if ($release['metadata_source'] === 'manual') {
            if (!appmarketDeleteCatalogDraft($pdo, (int)$release['id'])) {
                throw new RuntimeException('release is no longer an editable catalog draft');
            }
        } else {
            $storageName = (string)$release['apk_storage_name'];
            $pdo->prepare("DELETE FROM cms_appmarket_releases WHERE id = ? AND status = 'draft'")
                ->execute([(int)$release['id']]);
            appmarketDeletePrivateApkIfUnused($pdo, $storageName);
        }
        $_SESSION['appmarket_notice'] = 'Koncept vydání byl odstraněn.';
    } catch (Throwable $e) {
        koraLog('error', 'appmarket draft deletion failed', ['release_id' => (int)$release['id'], 'exception' => $e]);
        $_SESSION['appmarket_notice_error'] = 'Koncept se nepodařilo odstranit. Obnovte přehled a zkontrolujte jeho aktuální stav.';
    }
} else {
    $_SESSION['appmarket_notice_error'] = 'Tuto akci nelze pro aktuální stav vydání provést.';
}

header('Location: ' . $returnUrl);
exit;
