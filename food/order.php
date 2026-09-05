<?php

require_once __DIR__ . '/../db.php';
checkMaintenanceMode();
requireHttpMethods(['GET', 'POST']);

if (!isModuleEnabled('food')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$rawSlug = $_GET['slug'] ?? $_POST['slug'] ?? '';
$slug = foodCardSlug(is_string($rawSlug) ? trim($rawSlug) : '');
if ($slug === '') {
    header('Location: ' . BASE_URL . '/food/index.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT * FROM cms_food_cards
     WHERE slug = ? AND " . foodCardPublicVisibilitySql() . "
     LIMIT 1"
);
$stmt->execute([$slug]);
$cardRow = $stmt->fetch() ?: null;
if (!$cardRow) {
    renderPublicNotFoundPage([
        'title' => 'Lístek nebyl nalezen',
        'meta' => [
            'url' => BASE_URL . '/food/order.php?slug=' . rawurlencode($slug),
        ],
        'view_data' => [
            'title' => 'Lístek nebyl nalezen',
            'message' => 'Objednávkovou poptávku pro tento lístek nelze odeslat.',
        ],
        'current_nav' => 'food',
        'body_class' => 'page-food-order page-not-found',
        'page_kind' => 'utility',
    ]);
}

$card = hydrateFoodCardPresentation($cardRow);
$card['sections'] = foodLoadCardSections($pdo, (int)$card['id']);
$card = hydrateFoodCardPresentation($card);
$selectableItems = foodOrderSelectableItems($card['sections']);
$selectableChoices = foodOrderSelectableChoices($card['sections']);
$choicesByKey = [];
foreach ($selectableChoices as $selectableChoice) {
    $choicesByKey[(string)$selectableChoice['key']] = $selectableChoice;
}
$fulfillmentModes = normalizeFoodOrderFulfillmentModes($card['order_fulfillment_modes'] ?? '');
$defaultFulfillmentType = count($fulfillmentModes) === 1 ? $fulfillmentModes[0] : '';

$errors = [];
$fieldErrors = [];
$success = false;
$referenceCode = '';
$contactDefaults = currentUserContactDefaults($pdo);
$isPostRequest = $_SERVER['REQUEST_METHOD'] === 'POST';
$formData = [
    'customer_name' => $isPostRequest ? trim((string)($_POST['customer_name'] ?? '')) : $contactDefaults['name'],
    'customer_email' => $isPostRequest ? trim((string)($_POST['customer_email'] ?? '')) : $contactDefaults['email'],
    'customer_phone' => $isPostRequest ? trim((string)($_POST['customer_phone'] ?? '')) : $contactDefaults['phone'],
    'fulfillment_type' => $isPostRequest
        ? normalizeFoodOrderFulfillmentType((string)($_POST['fulfillment_type'] ?? ''))
        : $defaultFulfillmentType,
    'requested_at' => trim((string)($_POST['requested_at'] ?? '')),
    'customer_address' => trim((string)($_POST['customer_address'] ?? '')),
    'customer_note' => trim((string)($_POST['customer_note'] ?? '')),
    'quantities' => [],
];

if ($isPostRequest) {
    rateLimit('food_order', 5, 300);

    if (honeypotTriggered()) {
        $success = true;
    } else {
        verifyCsrf();

        if (!foodCardCanAcceptOrders($card)) {
            $errors[] = 'Tento lístek teď nepřijímá objednávkové poptávky.';
        }

        if ($formData['customer_name'] === '') {
            $errors[] = 'Zadejte své jméno.';
            $fieldErrors['customer_name'] = 'Zadejte své jméno.';
        }
        if ($formData['customer_email'] === '' || !filter_var($formData['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Zadejte platnou e-mailovou adresu.';
            $fieldErrors['customer_email'] = 'Zadejte platnou e-mailovou adresu.';
        }
        if ($formData['customer_phone'] === '') {
            $errors[] = 'Zadejte telefon pro upřesnění poptávky.';
            $fieldErrors['customer_phone'] = 'Zadejte telefon pro upřesnění poptávky.';
        }
        if ($fulfillmentModes === []) {
            $formData['fulfillment_type'] = '';
        } elseif (!in_array($formData['fulfillment_type'], $fulfillmentModes, true)) {
            $errors[] = 'Vyberte dostupný způsob převzetí.';
            $fieldErrors['fulfillment_type'] = 'Vyberte jednu z nabízených možností převzetí.';
        }
        if ($formData['fulfillment_type'] === 'delivery') {
            if ($formData['customer_address'] === '') {
                $errors[] = 'Pro doručení zadejte adresu.';
                $fieldErrors['customer_address'] = 'Doplňte ulici, číslo, obec a PSČ pro doručení.';
            } elseif (mb_strlen($formData['customer_address']) > 1000) {
                $errors[] = 'Adresa doručení je příliš dlouhá.';
                $fieldErrors['customer_address'] = 'Zkraťte adresu nejvýše na 1000 znaků.';
            }
        } else {
            $formData['customer_address'] = '';
        }
        if (mb_strlen($formData['customer_note']) > 3000) {
            $errors[] = 'Poznámka je příliš dlouhá.';
            $fieldErrors['customer_note'] = 'Zkraťte poznámku nejvýše na 3000 znaků.';
        }
        $requestedAt = normalizeFoodOrderRequestedAt($formData['requested_at']);
        if ((int)($card['order_requested_at_enabled'] ?? 0) === 1) {
            if ($requestedAt === null || $requestedAt === false) {
                $errors[] = 'Zadejte platný požadovaný termín.';
                $fieldErrors['requested_at'] = 'Vyberte platné datum a čas.';
            } elseif (!foodOrderRequestedAtIsFuture($requestedAt)) {
                $errors[] = 'Požadovaný termín musí být v budoucnosti.';
                $fieldErrors['requested_at'] = 'Vyberte budoucí datum a čas.';
            }
        } else {
            $requestedAt = null;
        }
        if (!captchaVerify((string)($_POST['captcha'] ?? ''))) {
            $captchaError = publicCaptchaErrorMessage();
            $errors[] = $captchaError;
            $fieldErrors['captcha'] = $captchaError;
        }

        $quantityValidation = foodValidateOrderQuantities($choicesByKey, $_POST['qty'] ?? []);
        $quantities = $quantityValidation['quantities'];
        $formData['quantities'] = $quantityValidation['values'];
        foreach ($quantityValidation['errors'] as $quantityField => $quantityError) {
            $fieldErrors[$quantityField] = $quantityError;
            $errors[] = $quantityError;
        }

        if ($quantities === [] && $quantityValidation['errors'] === []) {
            $errors[] = 'Vyberte alespoň jednu dostupnou položku a zadejte množství.';
            $fieldErrors['items'] = 'Vyberte alespoň jednu dostupnou položku a zadejte množství.';
        }

        $snapshot = foodBuildOrderSnapshot($choicesByKey, $quantities);
        if ($snapshot['items'] === [] && $quantities !== []) {
            $errors[] = 'Vybrané položky už nejsou dostupné.';
            $fieldErrors['items'] = 'Vybrané položky už nejsou dostupné.';
        }

        if ($errors === []) {
            $referenceCode = uniqueFoodOrderReferenceCode($pdo);
            try {
                $pdo->beginTransaction();
                $pdo->prepare(
                    "INSERT INTO cms_food_orders
                     (card_id, card_title, reference_code, customer_name, customer_email, customer_phone,
                      fulfillment_type, requested_at, customer_address, customer_note,
                      status, total_amount, price_currency, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?, NOW(), NOW())"
                )->execute([
                    (int)$card['id'],
                    (string)$card['title'],
                    $referenceCode,
                    $formData['customer_name'],
                    $formData['customer_email'],
                    $formData['customer_phone'],
                    $formData['fulfillment_type'],
                    $requestedAt,
                    $formData['fulfillment_type'] === 'delivery' ? $formData['customer_address'] : '',
                    $formData['customer_note'],
                    $snapshot['total'],
                    $snapshot['currency'],
                ]);
                $orderId = (int)$pdo->lastInsertId();
                $insertItem = $pdo->prepare(
                    "INSERT INTO cms_food_order_items
                     (order_id, item_id, variant_id, item_title, variant_label, portion_label,
                      quantity, unit_price_amount, price_currency, price_note, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                foreach ($snapshot['items'] as $snapshotItem) {
                    $insertItem->execute([
                        $orderId,
                        (int)$snapshotItem['item_id'],
                        $snapshotItem['variant_id'],
                        (string)$snapshotItem['item_title'],
                        (string)$snapshotItem['variant_label'],
                        (string)$snapshotItem['portion_label'],
                        (int)$snapshotItem['quantity'],
                        $snapshotItem['unit_price_amount'],
                        (string)$snapshotItem['price_currency'],
                        (string)$snapshotItem['price_note'],
                        (int)$snapshotItem['sort_order'],
                    ]);
                }
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                koraLog('warning', 'food order insert failed', ['exception' => $e]);
                $errors[] = 'Poptávku se nepodařilo uložit. Zkuste to prosím později.';
            }

            if ($errors === []) {
                $recipient = foodCardOrderRecipient($card);
                if ($recipient !== '') {
                    $lines = [
                        'Nová nezávazná objednávková poptávka.',
                        '',
                        'Referenční kód: ' . $referenceCode,
                        'Lístek: ' . (string)$card['title'],
                        'Jméno: ' . $formData['customer_name'],
                        'E-mail: ' . $formData['customer_email'],
                        'Telefon: ' . $formData['customer_phone'],
                    ];
                    $fulfillmentLabel = foodOrderFulfillmentLabel($formData['fulfillment_type']);
                    if ($fulfillmentLabel !== '') {
                        $lines[] = 'Způsob převzetí: ' . $fulfillmentLabel;
                    }
                    if ($requestedAt !== null && $requestedAt !== false) {
                        $lines[] = 'Požadovaný termín: ' . formatCzechDateTime($requestedAt);
                    }
                    if ($formData['fulfillment_type'] === 'delivery' && $formData['customer_address'] !== '') {
                        $lines[] = 'Adresa doručení: ' . $formData['customer_address'];
                    }
                    $lines[] = '';
                    $lines[] = 'Položky:';
                    foreach ($snapshot['items'] as $snapshotItem) {
                        $priceLabel = foodPriceLabel(
                            $snapshotItem['unit_price_amount'] !== null ? (string)$snapshotItem['unit_price_amount'] : null,
                            (string)$snapshotItem['price_currency'],
                            (string)$snapshotItem['price_note']
                        );
                        $variantLabel = trim((string)$snapshotItem['variant_label']);
                        $portionLabel = trim((string)$snapshotItem['portion_label']);
                        $selectionLabel = (string)$snapshotItem['item_title'];
                        if ($variantLabel !== '') {
                            $selectionLabel .= ' – ' . $variantLabel;
                        }
                        if ($portionLabel !== '') {
                            $selectionLabel .= ' (' . $portionLabel . ')';
                        }
                        $lines[] = '- ' . (int)$snapshotItem['quantity'] . '× ' . $selectionLabel
                            . ($priceLabel !== '' ? ' (' . $priceLabel . ')' : '');
                    }
                    if ($snapshot['total'] !== null) {
                        $lines[] = '';
                        $lines[] = 'Orientační součet: ' . foodPriceLabel($snapshot['total'], $snapshot['currency']);
                    }
                    if ($formData['customer_note'] !== '') {
                        $lines[] = '';
                        $lines[] = 'Poznámka:';
                        $lines[] = $formData['customer_note'];
                    }
                    if (!sendMail($recipient, 'Poptávka z jídelního lístku: ' . $referenceCode . ' – ' . $siteName, implode("\n", $lines), [
                        'reply_to' => $formData['customer_email'],
                    ])) {
                        $errors[] = 'Poptávka byla uložena, ale e-mailové oznámení se nepodařilo odeslat.';
                    }
                }
                $success = true;
            }
        }
    }
}

$captchaExpr = captchaGenerate();

renderPublicPage([
    'title' => 'Poptávka z lístku – ' . (string)$card['title'] . ' – ' . $siteName,
    'meta' => [
        'title' => 'Poptávka z lístku – ' . (string)$card['title'] . ' – ' . $siteName,
        'url' => BASE_URL . '/food/order.php?slug=' . rawurlencode((string)$card['slug']),
        'robots' => 'noindex, nofollow',
    ],
    'view' => 'modules/food-order',
    'view_data' => [
        'card' => $card,
        'selectableItems' => $selectableItems,
        'fulfillmentModes' => $fulfillmentModes,
        'success' => $success,
        'errors' => $errors,
        'fieldErrors' => $fieldErrors,
        'referenceCode' => $referenceCode,
        'formData' => $formData,
        'captchaExpr' => $captchaExpr,
    ],
    'current_nav' => 'food',
    'body_class' => 'page-food-order',
    'page_kind' => 'form',
    'admin_edit_url' => BASE_URL . '/admin/food_orders.php',
]);
