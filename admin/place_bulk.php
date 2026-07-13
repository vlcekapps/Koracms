<?php

require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu míst nemáte potřebné oprávnění.');
requireModuleEnabled('places');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/places.php');
    exit;
}

verifyCsrf();

$ids = [];
$hasInvalidId = false;
foreach ((array)($_POST['ids'] ?? []) as $rawId) {
    $rawId = trim((string)$rawId);
    if ($rawId === '' || !ctype_digit($rawId) || (int)$rawId <= 0) {
        $hasInvalidId = true;
        continue;
    }
    $ids[] = (int)$rawId;
}
$ids = array_values(array_unique($ids));
$action = trim((string)($_POST['action'] ?? ''));
$redirect = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), BASE_URL . '/admin/places.php');

/**
 * @param array<string,mixed> $context
 */
$setFlash = static function (string $type, string $message, array $context = []): void {
    $_SESSION['place_bulk_flash'] = array_merge([
        'type' => $type,
        'message' => $message,
    ], $context);
};

if ($action === 'delete') {
    $deleteFlashContext = ['selected_ids' => $ids];
    if ($ids === [] || $hasInvalidId) {
        $setFlash(
            'error',
            'Vyberte znovu místa, která chcete přesunout do Koše.',
            array_merge($deleteFlashContext, ['code' => 'place_bulk_delete_selection_required'])
        );
        header('Location: ' . $redirect);
        exit;
    }
    if (($_POST['confirm_place_bulk_delete'] ?? '') !== '1') {
        $setFlash(
            'error',
            'Hromadný přesun míst do Koše nejde provést bez potvrzení kontroly dopadu.',
            array_merge($deleteFlashContext, ['code' => 'place_bulk_delete_confirm_required'])
        );
        header('Location: ' . $redirect);
        exit;
    }

    $pdo = db_connect();
    try {
        $pdo->beginTransaction();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $placeStmt = $pdo->prepare(
            "SELECT id
             FROM cms_places
             WHERE id IN ({$placeholders}) AND deleted_at IS NULL
             FOR UPDATE"
        );
        $placeStmt->execute($ids);
        $allowedIds = array_map('intval', $placeStmt->fetchAll(PDO::FETCH_COLUMN));
        sort($allowedIds);
        $requestedIds = $ids;
        sort($requestedIds);
        if ($allowedIds !== $requestedIds) {
            $pdo->rollBack();
            $setFlash(
                'error',
                'Vybraný seznam míst se změnil nebo obsahuje nedostupnou položku. Zkontrolujte výběr a potvrďte akci znovu.',
                array_merge($deleteFlashContext, ['code' => 'place_bulk_delete_selection_invalid'])
            );
            header('Location: ' . $redirect);
            exit;
        }

        $updateStmt = $pdo->prepare(
            "UPDATE cms_places SET deleted_at = NOW()
             WHERE id IN ({$placeholders}) AND deleted_at IS NULL"
        );
        $updateStmt->execute($ids);
        if ($updateStmt->rowCount() !== count($ids)) {
            throw new RuntimeException('Počet přesunutých míst neodpovídá potvrzenému výběru.');
        }

        logAction('place_bulk_delete', 'ids=' . implode(',', $ids) . ' soft=true');
        $pdo->commit();
        $message = count($ids) === 1
            ? 'Vybrané místo bylo přesunuto do Koše. Lze ho obnovit ve správě Koše.'
            : 'Vybraná místa (' . count($ids) . ') byla přesunuta do Koše. Lze je obnovit ve správě Koše.';
        $setFlash('success', $message, ['code' => 'place_bulk_delete_success']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        koraLog('warning', 'admin place bulk soft delete failed', [
            'operation' => 'place_bulk_soft_delete',
            'place_count' => count($ids),
            'exception' => $e,
        ]);
        $setFlash(
            'error',
            'Místa se nepodařilo přesunout do Koše. Data zůstala beze změny; zkontrolujte výběr a zkuste akci znovu.',
            array_merge($deleteFlashContext, ['code' => 'place_bulk_delete_failed'])
        );
    }

    header('Location: ' . $redirect);
    exit;
}

if (!in_array($action, ['publish', 'hide'], true) || $ids === [] || $hasInvalidId) {
    header('Location: ' . $redirect);
    exit;
}

$pdo = db_connect();
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$status = $action === 'publish' ? 'published' : 'pending';
$pdo->prepare(
    "UPDATE cms_places SET status = ?
     WHERE id IN ({$placeholders}) AND deleted_at IS NULL"
)->execute(array_merge([$status], $ids));
logAction('place_bulk_' . $action, 'ids=' . implode(',', $ids));

header('Location: ' . $redirect);
exit;
