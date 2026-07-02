<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu položek jídelních lístků nemáte potřebné oprávnění.');
requireModuleEnabled('food');

$pdo = db_connect();
$cardId = inputInt('get', 'card') ?? inputInt('post', 'card_id');
if ($cardId === null) {
    header('Location: ' . BASE_URL . '/admin/food.php');
    exit;
}

$cardStmt = $pdo->prepare("SELECT * FROM cms_food_cards WHERE id = ? AND deleted_at IS NULL");
$cardStmt->execute([$cardId]);
$card = $cardStmt->fetch() ?: null;
if (!$card) {
    header('Location: ' . BASE_URL . '/admin/food.php');
    exit;
}
$card = hydrateFoodCardPresentation($card);

$message = trim((string)($_GET['msg'] ?? ''));
$error = '';
$fieldErrors = [];
$editSectionId = inputInt('get', 'edit_section');
$editItemId = inputInt('get', 'edit_item');
$sectionState = [
    'id' => 0,
    'title' => '',
    'description' => '',
    'serving_date' => '',
    'serving_time_from' => '',
    'serving_time_to' => '',
    'serving_note' => '',
    'sort_order' => '0',
];
$itemState = [
    'id' => 0,
    'section_id' => '',
    'title' => '',
    'description' => '',
    'price_amount' => '',
    'price_currency' => 'CZK',
    'price_note' => '',
    'portion_label' => '',
    'energy_kj' => '',
    'energy_kcal' => '',
    'protein_g' => '',
    'carbs_g' => '',
    'fat_g' => '',
    'salt_g' => '',
    'media_id' => '',
    'image_alt_text' => '',
    'allergens' => [],
    'dietary_flags' => [],
    'is_available' => '1',
    'sort_order' => '0',
];

$redirectToItems = static function (string $messageKey = '') use ($cardId): void {
    $query = ['card' => $cardId];
    if ($messageKey !== '') {
        $query['msg'] = $messageKey;
    }
    header('Location: ' . BASE_URL . '/admin/food_items.php?' . http_build_query($query));
    exit;
};

$sectionBelongsToCard = static function (?int $sectionId) use ($pdo, $cardId): bool {
    if ($sectionId === null) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT id FROM cms_food_sections WHERE id = ? AND card_id = ?");
    $stmt->execute([$sectionId, $cardId]);

    return (bool)$stmt->fetch();
};

$itemBelongsToCard = static function (?int $itemId) use ($pdo, $cardId): bool {
    if ($itemId === null) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT id FROM cms_food_items WHERE id = ? AND card_id = ?");
    $stmt->execute([$itemId, $cardId]);

    return (bool)$stmt->fetch();
};

$loadItemForCard = static function (?int $itemId) use ($pdo, $cardId): ?array {
    if ($itemId === null) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM cms_food_items WHERE id = ? AND card_id = ?");
    $stmt->execute([$itemId, $cardId]);
    $item = $stmt->fetch() ?: null;

    return is_array($item) ? $item : null;
};

$normalizeSectionOrders = static function () use ($pdo, $cardId): void {
    $stmt = $pdo->prepare("SELECT id FROM cms_food_sections WHERE card_id = ? ORDER BY sort_order, id");
    $stmt->execute([$cardId]);
    $update = $pdo->prepare("UPDATE cms_food_sections SET sort_order = ? WHERE id = ? AND card_id = ?");
    $order = 10;
    foreach ($stmt->fetchAll() as $row) {
        $update->execute([$order, (int)$row['id'], $cardId]);
        $order += 10;
    }
};

$normalizeItemOrders = static function (int $sectionId) use ($pdo, $cardId): void {
    $stmt = $pdo->prepare("SELECT id FROM cms_food_items WHERE card_id = ? AND section_id = ? ORDER BY sort_order, id");
    $stmt->execute([$cardId, $sectionId]);
    $update = $pdo->prepare("UPDATE cms_food_items SET sort_order = ? WHERE id = ? AND card_id = ? AND section_id = ?");
    $order = 10;
    foreach ($stmt->fetchAll() as $row) {
        $update->execute([$order, (int)$row['id'], $cardId, $sectionId]);
        $order += 10;
    }
};

$moveOrderedRow = static function (string $table, int $rowId, string $direction, ?int $sectionId = null) use ($pdo, $cardId): bool {
    if (!in_array($table, ['cms_food_sections', 'cms_food_items'], true) || !in_array($direction, ['up', 'down'], true)) {
        return false;
    }
    $where = 'card_id = ?';
    $params = [$cardId];
    if ($table === 'cms_food_items') {
        if ($sectionId === null) {
            return false;
        }
        $where .= ' AND section_id = ?';
        $params[] = $sectionId;
    }

    $stmt = $pdo->prepare("SELECT id, sort_order FROM {$table} WHERE {$where} ORDER BY sort_order, id");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $currentIndex = null;
    foreach ($rows as $index => $row) {
        if ((int)$row['id'] === $rowId) {
            $currentIndex = $index;
            break;
        }
    }
    if ($currentIndex === null) {
        return false;
    }
    $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
    if (!isset($rows[$targetIndex])) {
        return false;
    }

    $first = $rows[$currentIndex];
    $second = $rows[$targetIndex];
    $update = $pdo->prepare("UPDATE {$table} SET sort_order = ? WHERE id = ?");
    $update->execute([(int)$second['sort_order'], (int)$first['id']]);
    $update->execute([(int)$first['sort_order'], (int)$second['id']]);

    return true;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'delete_section') {
        $deleteSectionId = inputInt('post', 'section_id');
        if ($sectionBelongsToCard($deleteSectionId)) {
            $pdo->prepare("DELETE FROM cms_food_items WHERE section_id = ? AND card_id = ?")->execute([$deleteSectionId, $cardId]);
            $pdo->prepare("DELETE FROM cms_food_sections WHERE id = ? AND card_id = ?")->execute([$deleteSectionId, $cardId]);
            logAction('food_section_delete', "card={$cardId} section={$deleteSectionId}");
        }
        $redirectToItems('deleted');
    }

    if ($action === 'delete_item') {
        $deleteItemId = inputInt('post', 'item_id');
        if ($itemBelongsToCard($deleteItemId)) {
            $pdo->prepare("DELETE FROM cms_food_items WHERE id = ? AND card_id = ?")->execute([$deleteItemId, $cardId]);
            logAction('food_item_delete', "card={$cardId} item={$deleteItemId}");
        }
        $redirectToItems('deleted');
    }

    if ($action === 'move_section') {
        $sectionId = inputInt('post', 'section_id');
        $direction = trim((string)($_POST['direction'] ?? ''));
        if ($sectionBelongsToCard($sectionId)) {
            $normalizeSectionOrders();
            $moveOrderedRow('cms_food_sections', (int)$sectionId, $direction);
            logAction('food_section_move', "card={$cardId} section={$sectionId} direction={$direction}");
        }
        $redirectToItems('moved');
    }

    if ($action === 'move_item') {
        $itemId = inputInt('post', 'item_id');
        $item = $loadItemForCard($itemId);
        $direction = trim((string)($_POST['direction'] ?? ''));
        if ($item) {
            $sectionId = (int)$item['section_id'];
            $normalizeItemOrders($sectionId);
            $moveOrderedRow('cms_food_items', (int)$item['id'], $direction, $sectionId);
            logAction('food_item_move', "card={$cardId} item={$itemId} direction={$direction}");
        }
        $redirectToItems('moved');
    }

    if ($action === 'duplicate_item') {
        $itemId = inputInt('post', 'item_id');
        $item = $loadItemForCard($itemId);
        if ($item) {
            $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM cms_food_items WHERE card_id = ? AND section_id = ?");
            $maxStmt->execute([$cardId, (int)$item['section_id']]);
            $sortOrder = (int)$maxStmt->fetchColumn();
            $pdo->prepare(
                "INSERT INTO cms_food_items
                 (card_id, section_id, title, description, price_amount, price_currency, price_note,
                  portion_label, energy_kj, energy_kcal, protein_g, carbs_g, fat_g, salt_g,
                  media_id, image_alt_text, allergens, dietary_flags, is_available, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $cardId,
                (int)$item['section_id'],
                'Kopie: ' . (string)$item['title'],
                (string)($item['description'] ?? ''),
                $item['price_amount'],
                (string)$item['price_currency'],
                (string)$item['price_note'],
                (string)($item['portion_label'] ?? ''),
                $item['energy_kj'] !== null ? (int)$item['energy_kj'] : null,
                $item['energy_kcal'] !== null ? (int)$item['energy_kcal'] : null,
                $item['protein_g'],
                $item['carbs_g'],
                $item['fat_g'],
                $item['salt_g'],
                $item['media_id'] !== null ? (int)$item['media_id'] : null,
                (string)($item['image_alt_text'] ?? ''),
                (string)$item['allergens'],
                (string)$item['dietary_flags'],
                (int)$item['is_available'],
                $sortOrder,
            ]);
            logAction('food_item_duplicate', "card={$cardId} item={$itemId}");
        }
        $redirectToItems('duplicated');
    }

    if ($action === 'bulk_availability') {
        $rawItemIds = array_map('intval', (array)($_POST['item_ids'] ?? []));
        $itemIds = array_values(array_unique(array_filter($rawItemIds, static fn (int $id): bool => $id > 0)));
        $availability = trim((string)($_POST['bulk_availability'] ?? ''));
        if ($itemIds !== [] && in_array($availability, ['available', 'unavailable'], true)) {
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $params = array_merge([(int)($availability === 'available' ? 1 : 0), $cardId], $itemIds);
            $pdo->prepare(
                "UPDATE cms_food_items
                 SET is_available = ?, updated_at = NOW()
                 WHERE card_id = ? AND id IN ({$placeholders})"
            )->execute($params);
            logAction('food_item_bulk_availability', "card={$cardId} count=" . count($itemIds) . " state={$availability}");
        }
        $redirectToItems('bulk');
    }

    if ($action === 'save_section') {
        $sectionId = inputInt('post', 'section_id');
        $servingDateInput = trim((string)($_POST['serving_date'] ?? ''));
        $servingTimeFromInput = trim((string)($_POST['serving_time_from'] ?? ''));
        $servingTimeToInput = trim((string)($_POST['serving_time_to'] ?? ''));
        $servingDate = normalizeFoodServingDate($servingDateInput);
        $servingTimeFrom = normalizeFoodServingTime($servingTimeFromInput);
        $servingTimeTo = normalizeFoodServingTime($servingTimeToInput);
        $sectionState = [
            'id' => $sectionId ?? 0,
            'title' => trim((string)($_POST['title'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'serving_date' => $servingDateInput,
            'serving_time_from' => $servingTimeFromInput,
            'serving_time_to' => $servingTimeToInput,
            'serving_note' => mb_substr(trim((string)($_POST['serving_note'] ?? '')), 0, 255),
            'sort_order' => (string)max(0, (int)($_POST['sort_order'] ?? 0)),
        ];
        $editSectionId = $sectionId;

        if ($sectionState['title'] === '') {
            $error = 'Název sekce je povinný.';
            $fieldErrors[] = 'section_title';
        } elseif ($servingDateInput !== '' && $servingDate === '') {
            $error = 'Datum podávání musí být skutečné datum ve formátu RRRR-MM-DD.';
            $fieldErrors[] = 'section_serving_date';
        } elseif ($servingTimeFromInput !== '' && $servingTimeFrom === '') {
            $error = 'Čas začátku podávání musí být ve formátu HH:MM.';
            $fieldErrors[] = 'section_serving_time_from';
        } elseif ($servingTimeToInput !== '' && $servingTimeTo === '') {
            $error = 'Čas konce podávání musí být ve formátu HH:MM.';
            $fieldErrors[] = 'section_serving_time_to';
        } elseif ($servingTimeFrom !== '' && $servingTimeTo !== '' && $servingTimeTo < $servingTimeFrom) {
            $error = 'Čas konce podávání nesmí být dříve než čas začátku.';
            $fieldErrors[] = 'section_serving_time_from';
            $fieldErrors[] = 'section_serving_time_to';
        } elseif ($sectionId !== null && !$sectionBelongsToCard($sectionId)) {
            $error = 'Upravovaná sekce nepatří k tomuto lístku.';
        } elseif ($sectionId !== null) {
            $pdo->prepare(
                "UPDATE cms_food_sections
                 SET title = ?, description = ?, serving_date = ?, serving_time_from = ?, serving_time_to = ?,
                     serving_note = ?, sort_order = ?, updated_at = NOW()
                 WHERE id = ? AND card_id = ?"
            )->execute([
                $sectionState['title'],
                $sectionState['description'],
                $servingDate !== '' ? $servingDate : null,
                $servingTimeFrom !== '' ? $servingTimeFrom : null,
                $servingTimeTo !== '' ? $servingTimeTo : null,
                $sectionState['serving_note'],
                (int)$sectionState['sort_order'],
                $sectionId,
                $cardId,
            ]);
            logAction('food_section_edit', "card={$cardId} section={$sectionId}");
            $redirectToItems('saved');
        } else {
            $sortOrder = (int)$sectionState['sort_order'];
            if ($sortOrder <= 0) {
                $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM cms_food_sections WHERE card_id = ?");
                $maxStmt->execute([$cardId]);
                $sortOrder = (int)$maxStmt->fetchColumn();
            }
            $pdo->prepare(
                "INSERT INTO cms_food_sections
                 (card_id, title, description, serving_date, serving_time_from, serving_time_to, serving_note, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $cardId,
                $sectionState['title'],
                $sectionState['description'],
                $servingDate !== '' ? $servingDate : null,
                $servingTimeFrom !== '' ? $servingTimeFrom : null,
                $servingTimeTo !== '' ? $servingTimeTo : null,
                $sectionState['serving_note'],
                $sortOrder,
            ]);
            logAction('food_section_add', "card={$cardId}");
            $redirectToItems('saved');
        }
    }

    if ($action === 'save_item') {
        $itemId = inputInt('post', 'item_id');
        $sectionId = inputInt('post', 'section_id');
        $priceAmount = normalizeFoodPriceInput((string)($_POST['price_amount'] ?? ''));
        $energyKj = normalizeFoodNutritionIntegerInput((string)($_POST['energy_kj'] ?? ''));
        $energyKcal = normalizeFoodNutritionIntegerInput((string)($_POST['energy_kcal'] ?? ''));
        $proteinG = normalizeFoodNutritionDecimalInput((string)($_POST['protein_g'] ?? ''));
        $carbsG = normalizeFoodNutritionDecimalInput((string)($_POST['carbs_g'] ?? ''));
        $fatG = normalizeFoodNutritionDecimalInput((string)($_POST['fat_g'] ?? ''));
        $saltG = normalizeFoodNutritionDecimalInput((string)($_POST['salt_g'] ?? ''));
        $mediaId = inputInt('post', 'media_id') ?? 0;
        $media = $mediaId > 0 ? mediaGetById($mediaId) : null;
        $mediaIsValid = $mediaId <= 0 || (is_array($media) && mediaIsPublic($media) && mediaCanPreviewImage($media));
        $allergens = normalizeFoodAllergenList($_POST['allergens'] ?? []);
        $dietaryFlags = normalizeFoodDietaryFlags($_POST['dietary_flags'] ?? []);
        $itemState = [
            'id' => $itemId ?? 0,
            'section_id' => $sectionId !== null ? (string)$sectionId : '',
            'title' => trim((string)($_POST['title'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'price_amount' => $priceAmount !== false && $priceAmount !== null ? $priceAmount : trim((string)($_POST['price_amount'] ?? '')),
            'price_currency' => normalizeFoodCurrency((string)($_POST['price_currency'] ?? 'CZK')),
            'price_note' => trim((string)($_POST['price_note'] ?? '')),
            'portion_label' => mb_substr(trim((string)($_POST['portion_label'] ?? '')), 0, 80),
            'energy_kj' => $energyKj !== false && $energyKj !== null ? (string)$energyKj : trim((string)($_POST['energy_kj'] ?? '')),
            'energy_kcal' => $energyKcal !== false && $energyKcal !== null ? (string)$energyKcal : trim((string)($_POST['energy_kcal'] ?? '')),
            'protein_g' => $proteinG !== false && $proteinG !== null ? $proteinG : trim((string)($_POST['protein_g'] ?? '')),
            'carbs_g' => $carbsG !== false && $carbsG !== null ? $carbsG : trim((string)($_POST['carbs_g'] ?? '')),
            'fat_g' => $fatG !== false && $fatG !== null ? $fatG : trim((string)($_POST['fat_g'] ?? '')),
            'salt_g' => $saltG !== false && $saltG !== null ? $saltG : trim((string)($_POST['salt_g'] ?? '')),
            'media_id' => $mediaId > 0 ? (string)$mediaId : '',
            'image_alt_text' => mb_substr(trim((string)($_POST['image_alt_text'] ?? '')), 0, 255),
            'allergens' => $allergens,
            'dietary_flags' => $dietaryFlags,
            'is_available' => isset($_POST['is_available']) ? '1' : '0',
            'sort_order' => (string)max(0, (int)($_POST['sort_order'] ?? 0)),
        ];
        $editItemId = $itemId;

        if ($itemState['title'] === '') {
            $error = 'Název položky je povinný.';
            $fieldErrors[] = 'item_title';
        } elseif (!$sectionBelongsToCard($sectionId)) {
            $error = 'Vyberte platnou sekci tohoto lístku.';
            $fieldErrors[] = 'item_section_id';
        } elseif ($priceAmount === false) {
            $error = 'Cena musí být číslo s nejvýše dvěma desetinnými místy.';
            $fieldErrors[] = 'item_price_amount';
        } elseif ($energyKj === false) {
            $error = 'Energie v kJ musí být celé nezáporné číslo.';
            $fieldErrors[] = 'item_energy_kj';
        } elseif ($energyKcal === false) {
            $error = 'Energie v kcal musí být celé nezáporné číslo.';
            $fieldErrors[] = 'item_energy_kcal';
        } elseif ($proteinG === false) {
            $error = 'Bílkoviny musí být číslo s nejvýše dvěma desetinnými místy.';
            $fieldErrors[] = 'item_protein_g';
        } elseif ($carbsG === false) {
            $error = 'Sacharidy musí být číslo s nejvýše dvěma desetinnými místy.';
            $fieldErrors[] = 'item_carbs_g';
        } elseif ($fatG === false) {
            $error = 'Tuky musí být číslo s nejvýše dvěma desetinnými místy.';
            $fieldErrors[] = 'item_fat_g';
        } elseif ($saltG === false) {
            $error = 'Sůl musí být číslo s nejvýše dvěma desetinnými místy.';
            $fieldErrors[] = 'item_salt_g';
        } elseif (!$mediaIsValid) {
            $error = 'Vyberte platný veřejný obrázek z knihovny médií, nebo ponechte obrázek prázdný.';
            $fieldErrors[] = 'item_media_id';
        } elseif ($itemId !== null && !$itemBelongsToCard($itemId)) {
            $error = 'Upravovaná položka nepatří k tomuto lístku.';
        } elseif ($itemId !== null) {
            $pdo->prepare(
                "UPDATE cms_food_items
                 SET section_id = ?, title = ?, description = ?, price_amount = ?, price_currency = ?,
                     price_note = ?, portion_label = ?, energy_kj = ?, energy_kcal = ?, protein_g = ?, carbs_g = ?, fat_g = ?, salt_g = ?,
                     media_id = ?, image_alt_text = ?, allergens = ?, dietary_flags = ?, is_available = ?, sort_order = ?, updated_at = NOW()
                 WHERE id = ? AND card_id = ?"
            )->execute([
                $sectionId,
                $itemState['title'],
                $itemState['description'],
                $priceAmount,
                $itemState['price_currency'],
                $itemState['price_note'],
                $itemState['portion_label'],
                $energyKj,
                $energyKcal,
                $proteinG,
                $carbsG,
                $fatG,
                $saltG,
                $mediaId > 0 ? $mediaId : null,
                $itemState['image_alt_text'],
                implode(',', $allergens),
                implode(',', $dietaryFlags),
                (int)$itemState['is_available'],
                (int)$itemState['sort_order'],
                $itemId,
                $cardId,
            ]);
            logAction('food_item_edit', "card={$cardId} item={$itemId}");
            $redirectToItems('saved');
        } else {
            $sortOrder = (int)$itemState['sort_order'];
            if ($sortOrder <= 0) {
                $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM cms_food_items WHERE section_id = ? AND card_id = ?");
                $maxStmt->execute([$sectionId, $cardId]);
                $sortOrder = (int)$maxStmt->fetchColumn();
            }
            $pdo->prepare(
                "INSERT INTO cms_food_items
                 (card_id, section_id, title, description, price_amount, price_currency, price_note,
                  portion_label, energy_kj, energy_kcal, protein_g, carbs_g, fat_g, salt_g,
                  media_id, image_alt_text, allergens, dietary_flags, is_available, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $cardId,
                $sectionId,
                $itemState['title'],
                $itemState['description'],
                $priceAmount,
                $itemState['price_currency'],
                $itemState['price_note'],
                $itemState['portion_label'],
                $energyKj,
                $energyKcal,
                $proteinG,
                $carbsG,
                $fatG,
                $saltG,
                $mediaId > 0 ? $mediaId : null,
                $itemState['image_alt_text'],
                implode(',', $allergens),
                implode(',', $dietaryFlags),
                (int)$itemState['is_available'],
                $sortOrder,
            ]);
            logAction('food_item_add', "card={$cardId} section={$sectionId}");
            $redirectToItems('saved');
        }
    }
}

if ($editSectionId !== null && $error === '') {
    $editSectionStmt = $pdo->prepare(
        "SELECT id, title, description, serving_date, serving_time_from, serving_time_to, serving_note, sort_order
         FROM cms_food_sections
         WHERE id = ? AND card_id = ?"
    );
    $editSectionStmt->execute([$editSectionId, $cardId]);
    $editSection = $editSectionStmt->fetch() ?: null;
    if ($editSection) {
        $sectionState = [
            'id' => (int)$editSection['id'],
            'title' => (string)$editSection['title'],
            'description' => (string)($editSection['description'] ?? ''),
            'serving_date' => normalizeFoodServingDate((string)($editSection['serving_date'] ?? '')),
            'serving_time_from' => normalizeFoodServingTime(substr((string)($editSection['serving_time_from'] ?? ''), 0, 5)),
            'serving_time_to' => normalizeFoodServingTime(substr((string)($editSection['serving_time_to'] ?? ''), 0, 5)),
            'serving_note' => (string)($editSection['serving_note'] ?? ''),
            'sort_order' => (string)(int)$editSection['sort_order'],
        ];
    } else {
        $editSectionId = null;
    }
}

$sections = foodLoadCardSections($pdo, $cardId);

if ($editItemId !== null && $error === '') {
    $editItemStmt = $pdo->prepare("SELECT * FROM cms_food_items WHERE id = ? AND card_id = ?");
    $editItemStmt->execute([$editItemId, $cardId]);
    $editItem = $editItemStmt->fetch() ?: null;
    if ($editItem) {
        $editItem = hydrateFoodItemPresentation($editItem);
        $itemState = [
            'id' => (int)$editItem['id'],
            'section_id' => (string)(int)$editItem['section_id'],
            'title' => (string)$editItem['title'],
            'description' => (string)($editItem['description'] ?? ''),
            'price_amount' => $editItem['price_amount'] !== null ? (string)$editItem['price_amount'] : '',
            'price_currency' => (string)$editItem['price_currency'],
            'price_note' => (string)$editItem['price_note'],
            'portion_label' => (string)$editItem['portion_label'],
            'energy_kj' => $editItem['energy_kj'] !== null ? (string)$editItem['energy_kj'] : '',
            'energy_kcal' => $editItem['energy_kcal'] !== null ? (string)$editItem['energy_kcal'] : '',
            'protein_g' => $editItem['protein_g'] !== null ? (string)$editItem['protein_g'] : '',
            'carbs_g' => $editItem['carbs_g'] !== null ? (string)$editItem['carbs_g'] : '',
            'fat_g' => $editItem['fat_g'] !== null ? (string)$editItem['fat_g'] : '',
            'salt_g' => $editItem['salt_g'] !== null ? (string)$editItem['salt_g'] : '',
            'media_id' => (int)$editItem['media_id'] > 0 ? (string)(int)$editItem['media_id'] : '',
            'image_alt_text' => (string)$editItem['image_alt_text'],
            'allergens' => $editItem['allergen_values'],
            'dietary_flags' => $editItem['dietary_flag_values'],
            'is_available' => (string)(int)$editItem['is_available'],
            'sort_order' => (string)(int)$editItem['sort_order'],
        ];
    } else {
        $editItemId = null;
    }
}

$allergenDefinitions = foodAllergenDefinitions();
$dietaryFlagDefinitions = foodDietaryFlagDefinitions();
$selectedMediaId = (int)($itemState['media_id'] !== '' ? $itemState['media_id'] : 0);
$mediaOptionsStmt = $pdo->prepare(
    "SELECT id, original_name, filename, alt_text
     FROM cms_media
     WHERE visibility = 'public' AND mime_type LIKE 'image/%' AND mime_type <> 'image/svg+xml'
     ORDER BY (id = ?) DESC, created_at DESC, id DESC
     LIMIT 201"
);
$mediaOptionsStmt->execute([$selectedMediaId]);
$mediaOptions = $mediaOptionsStmt->fetchAll();

adminHeader('Položky lístku: ' . (string)$card['title']);
?>

<?php if ($message === 'saved'): ?><p class="success" role="status">Položky lístku byly uloženy.</p><?php endif; ?>
<?php if ($message === 'deleted'): ?><p class="success" role="status">Položka nebo sekce byla smazána.</p><?php endif; ?>
<?php if ($message === 'moved'): ?><p class="success" role="status">Pořadí bylo upraveno.</p><?php endif; ?>
<?php if ($message === 'duplicated'): ?><p class="success" role="status">Položka byla zkopírována.</p><?php endif; ?>
<?php if ($message === 'bulk'): ?><p class="success" role="status">Dostupnost vybraných položek byla upravena.</p><?php endif; ?>
<?php if ($error !== ''): ?><p id="food-items-error" class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <a href="food.php"><span aria-hidden="true">&larr;</span> Zpět na lístky</a>
  <a href="food_form.php?id=<?= (int)$cardId ?>">Upravit údaje lístku</a>
  <?php if (!empty($card['is_publicly_visible'])): ?>
    <a href="<?= h((string)$card['public_path']) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
  <?php endif; ?>
</p>

<p class="admin-description">
  Strukturované položky se na webu zobrazí před volným HTML obsahem lístku. Když žádné položky nezadáte, veřejný web dál použije původní obsah lístku.
</p>

<section class="form-card" aria-labelledby="food-section-form-title">
  <h2 id="food-section-form-title"><?= (int)$sectionState['id'] > 0 ? 'Upravit sekci' : 'Přidat sekci' ?></h2>
  <form method="post" novalidate<?= $error !== '' && array_intersect($fieldErrors, ['section_title', 'section_serving_date', 'section_serving_time_from', 'section_serving_time_to']) !== [] ? ' aria-describedby="food-items-error"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="save_section">
    <input type="hidden" name="card_id" value="<?= (int)$cardId ?>">
    <input type="hidden" name="section_id" value="<?= (int)$sectionState['id'] ?>">
    <fieldset>
      <legend>Sekce lístku</legend>
      <label for="section-title">Název sekce <span aria-hidden="true">*</span></label>
      <input type="text" id="section-title" name="title" required aria-required="true" maxlength="255"
             value="<?= h((string)$sectionState['title']) ?>"
             <?= adminFieldAttributes('section_title', $fieldErrors, [], ['section-title-help']) ?>>
      <small id="section-title-help" class="field-help">Například Polévky, Hlavní jídla, Dezerty nebo Teplé nápoje.</small>
      <?php adminRenderFieldError('section_title', $fieldErrors, [], $error); ?>

      <label for="section-description">Popis sekce</label>
      <textarea id="section-description" name="description" rows="3"><?= h((string)$sectionState['description']) ?></textarea>

      <fieldset class="admin-fieldset-card">
        <legend>Denní nabídka</legend>
        <p id="section-serving-help" class="field-help field-help--flush">Volitelné. Pokud sekce platí jen pro konkrétní den nebo čas, vyplňte datum podávání a případně časové rozmezí.</p>
        <div class="form-grid">
          <div class="form-group">
            <label for="section-serving-date">Datum podávání</label>
            <input type="date" id="section-serving-date" name="serving_date"
                   value="<?= h((string)$sectionState['serving_date']) ?>"
                   <?= adminFieldAttributes('section_serving_date', $fieldErrors, [], ['section-serving-help']) ?>>
            <?php adminRenderFieldError('section_serving_date', $fieldErrors, [], $error); ?>
          </div>
          <div class="form-group">
            <label for="section-serving-time-from">Podávání od</label>
            <input type="time" id="section-serving-time-from" name="serving_time_from"
                   value="<?= h((string)$sectionState['serving_time_from']) ?>"
                   <?= adminFieldAttributes('section_serving_time_from', $fieldErrors, [], ['section-serving-help']) ?>>
            <?php adminRenderFieldError('section_serving_time_from', $fieldErrors, [], $error); ?>
          </div>
          <div class="form-group">
            <label for="section-serving-time-to">Podávání do</label>
            <input type="time" id="section-serving-time-to" name="serving_time_to"
                   value="<?= h((string)$sectionState['serving_time_to']) ?>"
                   <?= adminFieldAttributes('section_serving_time_to', $fieldErrors, [], ['section-serving-help']) ?>>
            <?php adminRenderFieldError('section_serving_time_to', $fieldErrors, [], $error); ?>
          </div>
        </div>
        <label for="section-serving-note">Poznámka k podávání</label>
        <input type="text" id="section-serving-note" name="serving_note" maxlength="255"
               value="<?= h((string)$sectionState['serving_note']) ?>"
               aria-describedby="section-serving-help">
      </fieldset>

      <label for="section-sort">Pořadí</label>
      <input type="number" id="section-sort" name="sort_order" min="0" class="admin-input-auto" value="<?= h((string)$sectionState['sort_order']) ?>" aria-describedby="section-sort-help">
      <small id="section-sort-help" class="field-help">Nižší číslo se zobrazí dříve. Prázdné nebo nula zařadí novou sekci na konec.</small>

      <div class="button-row button-row--start admin-action-row">
        <button type="submit" class="btn"><?= (int)$sectionState['id'] > 0 ? 'Uložit sekci' : 'Přidat sekci' ?></button>
        <?php if ((int)$sectionState['id'] > 0): ?><a class="btn" href="food_items.php?card=<?= (int)$cardId ?>">Zrušit úpravu</a><?php endif; ?>
      </div>
    </fieldset>
  </form>
</section>

<section class="form-card" aria-labelledby="food-item-form-title">
  <h2 id="food-item-form-title"><?= (int)$itemState['id'] > 0 ? 'Upravit položku' : 'Přidat položku' ?></h2>
  <?php if ($sections === []): ?>
    <p class="field-help field-help--flush">Nejprve přidejte alespoň jednu sekci lístku.</p>
  <?php else: ?>
    <form method="post" novalidate<?= $error !== '' && array_intersect($fieldErrors, ['item_title', 'item_section_id', 'item_price_amount', 'item_energy_kj', 'item_energy_kcal', 'item_protein_g', 'item_carbs_g', 'item_fat_g', 'item_salt_g', 'item_media_id']) !== [] ? ' aria-describedby="food-items-error"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="action" value="save_item">
      <input type="hidden" name="card_id" value="<?= (int)$cardId ?>">
      <input type="hidden" name="item_id" value="<?= (int)$itemState['id'] ?>">
      <fieldset>
        <legend>Položka lístku</legend>

        <label for="item-section">Sekce <span aria-hidden="true">*</span></label>
        <select id="item-section" name="section_id" class="admin-input-auto"
                <?= adminFieldAttributes('item_section_id', $fieldErrors, []) ?>>
          <?php foreach ($sections as $section): ?>
            <option value="<?= (int)$section['id'] ?>"<?= (string)$itemState['section_id'] === (string)(int)$section['id'] ? ' selected' : '' ?>>
              <?= h((string)$section['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php adminRenderFieldError('item_section_id', $fieldErrors, [], $error); ?>

        <label for="item-title">Název položky <span aria-hidden="true">*</span></label>
        <input type="text" id="item-title" name="title" required aria-required="true" maxlength="255"
               value="<?= h((string)$itemState['title']) ?>"
               <?= adminFieldAttributes('item_title', $fieldErrors, []) ?>>
        <?php adminRenderFieldError('item_title', $fieldErrors, [], $error); ?>

        <label for="item-description">Popis položky</label>
        <textarea id="item-description" name="description" rows="3" aria-describedby="item-description-help"><?= h((string)$itemState['description']) ?></textarea>
        <small id="item-description-help" class="field-help">Volitelně doplňte složení, porci nebo krátkou poznámku.</small>

        <fieldset class="admin-fieldset-card">
          <legend>Obrázek položky</legend>
          <p id="item-media-help" class="field-help field-help--flush">Volitelné. Použijte veřejný obrázek z knihovny médií; soukromá média a SVG se u položek nezobrazují.</p>
          <label for="item-media-id">Obrázek z knihovny médií</label>
          <select id="item-media-id" name="media_id" class="admin-input-wide" <?= adminFieldAttributes('item_media_id', $fieldErrors, [], ['item-media-help']) ?>>
            <option value="">Bez obrázku</option>
            <?php foreach ($mediaOptions as $mediaOption): ?>
              <?php
              $mediaLabel = trim((string)($mediaOption['original_name'] ?? ''));
                if ($mediaLabel === '') {
                    $mediaLabel = (string)($mediaOption['filename'] ?? '');
                }
                ?>
              <option value="<?= (int)$mediaOption['id'] ?>"<?= (string)$itemState['media_id'] === (string)(int)$mediaOption['id'] ? ' selected' : '' ?>>
                <?= h($mediaLabel) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php adminRenderFieldError('item_media_id', $fieldErrors, [], $error); ?>

          <label for="item-image-alt">Alternativní text obrázku pro tuto položku</label>
          <input type="text" id="item-image-alt" name="image_alt_text" maxlength="255" value="<?= h((string)$itemState['image_alt_text']) ?>" aria-describedby="item-image-alt-help">
          <small id="item-image-alt-help" class="field-help">Když ho necháte prázdný, použije se alt text z knihovny médií a případně název položky.</small>
        </fieldset>

        <div class="form-grid">
          <div class="form-group">
            <label for="item-price">Cena</label>
            <input type="text" id="item-price" name="price_amount" inputmode="decimal" maxlength="20"
                   value="<?= h((string)$itemState['price_amount']) ?>"
                   <?= adminFieldAttributes('item_price_amount', $fieldErrors, [], ['item-price-help']) ?>>
            <small id="item-price-help" class="field-help">Volitelné. Použijte například 129 nebo 129,90.</small>
            <?php adminRenderFieldError('item_price_amount', $fieldErrors, [], $error); ?>
          </div>
          <div class="form-group">
            <label for="item-currency">Měna</label>
            <input type="text" id="item-currency" name="price_currency" maxlength="3" class="admin-input-auto" value="<?= h((string)$itemState['price_currency']) ?>">
          </div>
          <div class="form-group">
            <label for="item-price-note">Poznámka k ceně</label>
            <input type="text" id="item-price-note" name="price_note" maxlength="255" value="<?= h((string)$itemState['price_note']) ?>" placeholder="např. za 100 g">
          </div>
        </div>

        <fieldset class="admin-fieldset-card">
          <legend>Výživové údaje</legend>
          <p id="item-nutrition-help" class="field-help field-help--flush">Volitelné hodnoty pro návštěvníky. Nevyplněná pole se na veřejném webu nezobrazí.</p>
          <label for="item-portion-label">Porce</label>
          <input type="text" id="item-portion-label" name="portion_label" maxlength="80"
                 value="<?= h((string)$itemState['portion_label']) ?>"
                 aria-describedby="item-nutrition-help">
          <div class="form-grid">
            <div class="form-group">
              <label for="item-energy-kj">Energie v kJ</label>
              <input type="number" id="item-energy-kj" name="energy_kj" min="0" step="1"
                     value="<?= h((string)$itemState['energy_kj']) ?>"
                     <?= adminFieldAttributes('item_energy_kj', $fieldErrors, [], ['item-nutrition-help']) ?>>
              <?php adminRenderFieldError('item_energy_kj', $fieldErrors, [], $error); ?>
            </div>
            <div class="form-group">
              <label for="item-energy-kcal">Energie v kcal</label>
              <input type="number" id="item-energy-kcal" name="energy_kcal" min="0" step="1"
                     value="<?= h((string)$itemState['energy_kcal']) ?>"
                     <?= adminFieldAttributes('item_energy_kcal', $fieldErrors, [], ['item-nutrition-help']) ?>>
              <?php adminRenderFieldError('item_energy_kcal', $fieldErrors, [], $error); ?>
            </div>
            <div class="form-group">
              <label for="item-protein-g">Bílkoviny v g</label>
              <input type="text" id="item-protein-g" name="protein_g" inputmode="decimal"
                     value="<?= h((string)$itemState['protein_g']) ?>"
                     <?= adminFieldAttributes('item_protein_g', $fieldErrors, [], ['item-nutrition-help']) ?>>
              <?php adminRenderFieldError('item_protein_g', $fieldErrors, [], $error); ?>
            </div>
            <div class="form-group">
              <label for="item-carbs-g">Sacharidy v g</label>
              <input type="text" id="item-carbs-g" name="carbs_g" inputmode="decimal"
                     value="<?= h((string)$itemState['carbs_g']) ?>"
                     <?= adminFieldAttributes('item_carbs_g', $fieldErrors, [], ['item-nutrition-help']) ?>>
              <?php adminRenderFieldError('item_carbs_g', $fieldErrors, [], $error); ?>
            </div>
            <div class="form-group">
              <label for="item-fat-g">Tuky v g</label>
              <input type="text" id="item-fat-g" name="fat_g" inputmode="decimal"
                     value="<?= h((string)$itemState['fat_g']) ?>"
                     <?= adminFieldAttributes('item_fat_g', $fieldErrors, [], ['item-nutrition-help']) ?>>
              <?php adminRenderFieldError('item_fat_g', $fieldErrors, [], $error); ?>
            </div>
            <div class="form-group">
              <label for="item-salt-g">Sůl v g</label>
              <input type="text" id="item-salt-g" name="salt_g" inputmode="decimal"
                     value="<?= h((string)$itemState['salt_g']) ?>"
                     <?= adminFieldAttributes('item_salt_g', $fieldErrors, [], ['item-nutrition-help']) ?>>
              <?php adminRenderFieldError('item_salt_g', $fieldErrors, [], $error); ?>
            </div>
          </div>
        </fieldset>

        <fieldset class="admin-fieldset-card">
          <legend>Alergeny</legend>
          <p class="field-help field-help--flush">Vyberte čísla alergenů, která se u položky zobrazí čtenářům textově.</p>
          <div class="checkbox-grid">
            <?php foreach ($allergenDefinitions as $allergenNumber => $allergenLabel): ?>
              <label class="admin-checkbox-label">
                <input type="checkbox" name="allergens[]" value="<?= (int)$allergenNumber ?>"<?= in_array((int)$allergenNumber, $itemState['allergens'], true) ? ' checked' : '' ?>>
                <?= (int)$allergenNumber ?> - <?= h($allergenLabel) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="admin-fieldset-card">
          <legend>Dietní štítky</legend>
          <div class="checkbox-grid">
            <?php foreach ($dietaryFlagDefinitions as $flagKey => $flagLabel): ?>
              <label class="admin-checkbox-label">
                <input type="checkbox" name="dietary_flags[]" value="<?= h($flagKey) ?>"<?= in_array($flagKey, $itemState['dietary_flags'], true) ? ' checked' : '' ?>>
                <?= h($flagLabel) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <label class="admin-checkbox-label">
          <input type="checkbox" name="is_available" value="1"<?= $itemState['is_available'] === '1' ? ' checked' : '' ?>>
          Položka je dostupná
        </label>

        <label for="item-sort">Pořadí</label>
        <input type="number" id="item-sort" name="sort_order" min="0" class="admin-input-auto" value="<?= h((string)$itemState['sort_order']) ?>">

        <div class="button-row button-row--start admin-action-row">
          <button type="submit" class="btn"><?= (int)$itemState['id'] > 0 ? 'Uložit položku' : 'Přidat položku' ?></button>
          <?php if ((int)$itemState['id'] > 0): ?><a class="btn" href="food_items.php?card=<?= (int)$cardId ?>">Zrušit úpravu</a><?php endif; ?>
        </div>
      </fieldset>
    </form>
  <?php endif; ?>
</section>

<h2>Strukturovaný obsah lístku</h2>
<?php if ($sections === []): ?>
  <p>Zatím tu nejsou žádné strukturované sekce ani položky.</p>
<?php else: ?>
  <?php foreach ($sections as $section): ?>
    <section class="admin-section-card" aria-labelledby="food-section-<?= (int)$section['id'] ?>">
      <div class="section-heading">
        <div>
          <h3 id="food-section-<?= (int)$section['id'] ?>"><?= h((string)$section['title']) ?></h3>
          <?php if (trim((string)($section['description'] ?? '')) !== ''): ?>
            <p class="field-help field-help--flush"><?= h((string)$section['description']) ?></p>
          <?php endif; ?>
          <?php if (trim((string)($section['serving_label'] ?? '')) !== ''): ?>
            <p class="field-help field-help--flush">
              <strong><?= !empty($section['is_today']) ? 'Dnešní nabídka: ' : 'Podávání: ' ?></strong><?= h((string)$section['serving_label']) ?>
            </p>
          <?php endif; ?>
        </div>
        <div class="actions">
          <a class="btn" href="food_items.php?card=<?= (int)$cardId ?>&amp;edit_section=<?= (int)$section['id'] ?>">Upravit sekci</a>
          <form method="post" class="admin-inline-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="action" value="move_section">
            <input type="hidden" name="card_id" value="<?= (int)$cardId ?>">
            <input type="hidden" name="section_id" value="<?= (int)$section['id'] ?>">
            <input type="hidden" name="direction" value="up">
            <button type="submit" class="btn">Posunout sekci nahoru</button>
          </form>
          <form method="post" class="admin-inline-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="action" value="move_section">
            <input type="hidden" name="card_id" value="<?= (int)$cardId ?>">
            <input type="hidden" name="section_id" value="<?= (int)$section['id'] ?>">
            <input type="hidden" name="direction" value="down">
            <button type="submit" class="btn">Posunout sekci dolů</button>
          </form>
          <form method="post" class="admin-inline-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="action" value="delete_section">
            <input type="hidden" name="card_id" value="<?= (int)$cardId ?>">
            <input type="hidden" name="section_id" value="<?= (int)$section['id'] ?>">
            <button type="submit" class="btn btn-danger" data-confirm="Smazat sekci včetně všech položek?">Smazat sekci</button>
          </form>
        </div>
      </div>

      <?php if (empty($section['items'])): ?>
        <p class="field-help">Tato sekce zatím nemá žádné položky.</p>
      <?php else: ?>
        <form id="food-bulk-form-<?= (int)$section['id'] ?>" method="post" class="admin-inline-form admin-action-row" aria-labelledby="food-bulk-title-<?= (int)$section['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="action" value="bulk_availability">
          <input type="hidden" name="card_id" value="<?= (int)$cardId ?>">
          <strong id="food-bulk-title-<?= (int)$section['id'] ?>">Hromadná dostupnost položek v této sekci</strong>
          <label for="food-bulk-availability-<?= (int)$section['id'] ?>" class="sr-only">Nový stav dostupnosti</label>
          <select id="food-bulk-availability-<?= (int)$section['id'] ?>" name="bulk_availability">
            <option value="available">Označit jako dostupné</option>
            <option value="unavailable">Označit jako nedostupné</option>
          </select>
          <button type="submit" class="btn">Použít na vybrané položky</button>
        </form>
        <table>
          <caption>Položky sekce <?= h((string)$section['title']) ?></caption>
          <thead>
            <tr>
              <th scope="col">Výběr</th>
              <th scope="col">Položka</th>
              <th scope="col">Obrázek</th>
              <th scope="col">Cena</th>
              <th scope="col">Alergeny a štítky</th>
              <th scope="col">Stav</th>
              <th scope="col">Akce</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($section['items'] as $item): ?>
              <tr>
                <td>
                  <label class="sr-only" for="food-item-select-<?= (int)$item['id'] ?>">Vybrat položku <?= h((string)$item['title']) ?></label>
                  <input id="food-item-select-<?= (int)$item['id'] ?>" form="food-bulk-form-<?= (int)$section['id'] ?>" type="checkbox" name="item_ids[]" value="<?= (int)$item['id'] ?>">
                </td>
                <td>
                  <strong><?= h((string)$item['title']) ?></strong>
                  <?php if (trim((string)($item['description'] ?? '')) !== ''): ?>
                    <br><small class="table-meta"><?= h((string)$item['description']) ?></small>
                  <?php endif; ?>
                  <?php if (!empty($item['nutrition_labels'])): ?>
                    <br><small class="table-meta">Výživové údaje:
                      <?= h(implode(', ', array_map(
                          static fn (array $label): string => (string)$label['label'] . ': ' . (string)$label['value'],
                          $item['nutrition_labels']
                      ))) ?>
                    </small>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ((string)($item['image_thumb_url'] ?? '') !== ''): ?>
                    <img src="<?= h((string)$item['image_thumb_url']) ?>" alt="<?= h((string)$item['image_alt']) ?>" class="admin-thumb" loading="lazy">
                  <?php else: ?>
                    <span class="table-meta">Bez obrázku</span>
                  <?php endif; ?>
                </td>
                <td><?= h((string)($item['price_label'] !== '' ? $item['price_label'] : 'Bez ceny')) ?></td>
                <td>
                  <?php if (!empty($item['allergen_labels'])): ?>
                    <small class="table-meta">Alergeny: <?= h(implode(', ', $item['allergen_labels'])) ?></small><br>
                  <?php endif; ?>
                  <?php if (!empty($item['dietary_flag_labels'])): ?>
                    <small class="table-meta">Štítky: <?= h(implode(', ', $item['dietary_flag_labels'])) ?></small>
                  <?php endif; ?>
                </td>
                <td><?= (int)$item['is_available'] === 1 ? 'Dostupná' : 'Nedostupná' ?></td>
                <td class="actions">
                  <a class="btn" href="food_items.php?card=<?= (int)$cardId ?>&amp;edit_item=<?= (int)$item['id'] ?>">Upravit</a>
                  <form method="post" class="admin-inline-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="action" value="move_item">
                    <input type="hidden" name="card_id" value="<?= (int)$cardId ?>">
                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                    <input type="hidden" name="direction" value="up">
                    <button type="submit" class="btn">Nahoru</button>
                  </form>
                  <form method="post" class="admin-inline-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="action" value="move_item">
                    <input type="hidden" name="card_id" value="<?= (int)$cardId ?>">
                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                    <input type="hidden" name="direction" value="down">
                    <button type="submit" class="btn">Dolů</button>
                  </form>
                  <form method="post" class="admin-inline-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="action" value="duplicate_item">
                    <input type="hidden" name="card_id" value="<?= (int)$cardId ?>">
                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                    <button type="submit" class="btn">Kopírovat</button>
                  </form>
                  <form method="post" class="admin-inline-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="card_id" value="<?= (int)$cardId ?>">
                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                    <button type="submit" class="btn btn-danger" data-confirm="Smazat položku?">Smazat</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php adminFooter(); ?>
