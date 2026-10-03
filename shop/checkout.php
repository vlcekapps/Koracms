<?php

require_once __DIR__ . '/../db.php';
if (!isModuleEnabled('shop')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
require_once __DIR__ . '/../lib/shop.php';
require_once __DIR__ . '/../lib/shop_public.php';
shopPublicPrivate();
$requestMethod = requireHttpMethods(['GET', 'HEAD', 'POST']);
checkMaintenanceMode();

$protectionErrors = [];
$action = $requestMethod === 'POST' ? shopPublicString($_POST, 'action') : '';
if ($requestMethod === 'POST') {
    if ($action === 'place' && !captchaVerify(shopPublicString($_POST, 'captcha'))) {
        $protectionErrors['captcha'] = publicCaptchaErrorMessage();
    }
    shopPublicVerifyCsrf();
    if (honeypotTriggered()) {
        $protectionErrors['spam'] = 'Odeslání nebylo přijato. Zkuste formulář vyplnit znovu.';
    }
    rateLimit('shop_checkout', 10, 300, static function (): void {
        header('Retry-After: 300');
        http_response_code(429);
        // The shared limiter exits after this callback returns.
        shopPublicRender('Příliš mnoho pokusů', 'status', [
            'heading' => 'Příliš mnoho pokusů',
            'message' => 'Objednávka nebyla odeslána. Zkuste to prosím za pět minut.',
            'status' => 429,
        ], BASE_URL . '/shop/checkout.php', true);
    });
}

$pdo = db_connect();
$ready = shopReady();
$settings = shopSettings();
$cart = shopPublicCart();
$summary = shopPublicCartSummary($pdo, $cart);
$taxRules = $pdo->query('SELECT country_code, country_name, general_rate_bp, publication_rate_bp, tax_note FROM cms_shop_tax_rules WHERE is_active = 1 ORDER BY country_name, country_code')->fetchAll();
$taxRules = array_values(array_filter(
    $taxRules,
    static fn (array $rule): bool =>
    preg_match('/\A[A-Z]{2}\z/', (string)$rule['country_code']) === 1
    && (int)$rule['general_rate_bp'] >= 0 && (int)$rule['general_rate_bp'] <= 10000
    && (int)$rule['publication_rate_bp'] >= 0 && (int)$rule['publication_rate_bp'] <= 10000
));
$paymentMethods = $pdo->query('SELECT id, name, iban FROM cms_shop_payment_methods WHERE is_active = 1 ORDER BY id')->fetchAll();
$paymentMethods = array_values(array_filter($paymentMethods, static fn (array $method): bool => shopNormalizeIban((string)$method['iban']) !== ''));
$defaults = currentUserContactDefaults($pdo);
$formData = [
    'customer_name' => $defaults['name'], 'email' => $defaults['email'],
    'address' => '', 'city' => '', 'postal_code' => '', 'country_code' => '', 'payment_method_id' => '',
];
$forms = is_array($_SESSION['shop_checkout_forms'] ?? null) ? $_SESSION['shop_checkout_forms'] : [];
$nonce = $requestMethod === 'POST' ? shopPublicString($_POST, 'checkout_nonce') : shopPublicString($_GET, 'review');
$editing = false;
if ($requestMethod !== 'POST' && $nonce === '') {
    $nonce = shopPublicString($_GET, 'edit');
    $editing = $nonce !== '';
}
$state = $forms[$nonce] ?? null;
if (is_array($state) && ($state['stage'] ?? '') === 'committed') {
    // Replaying the same form returns the committed result, never creates another order.
    if (is_string($state['redirect'] ?? null)) {
        shopPublicRedirect($state['redirect']);
    }
    shopPublicError('Objednávka již byla přijata', 'Objednávka ' . (string)($state['order_number'] ?? '') . ' již byla přijata. Novou objednávku neodesílejte; pro přístup kontaktujte prodejce.', 200);
}
if (!is_array($state) || (int)($state['created_at'] ?? 0) < time() - 1800) {
    $state = null;
}
$isReview = !$editing && is_array($state) && ($state['stage'] ?? '') === 'review';
if (is_array($state['formData'] ?? null)) {
    $formData = array_replace($formData, array_intersect_key($state['formData'], $formData));
}
$flash = shopPublicPullFlash('shop_checkout_flash');
if (($isReview || $editing) && ($flash['nonce'] ?? '') !== $nonce) {
    $flash = [];
}
if (is_array($flash['formData'] ?? null)) {
    foreach ($formData as $key => $value) {
        if (is_string($flash['formData'][$key] ?? null)) {
            $formData[$key] = $flash['formData'][$key];
        }
    }
}
$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
$fieldErrors = is_array($flash['fieldErrors'] ?? null) ? $flash['fieldErrors'] : [];

if ($requestMethod === 'POST') {
    $errors = array_values($protectionErrors);
    $fieldErrors = array_intersect_key($protectionErrors, ['captcha' => true]);
    if ($action !== 'place') {
        foreach ($formData as $key => $value) {
            $formData[$key] = shopPublicString($_POST, $key);
        }
    } elseif (is_array($state['formData'] ?? null)) {
        $formData = array_replace($formData, array_intersect_key($state['formData'], $formData));
    }
    $addError = static function (string $key, string $message) use (&$errors, &$fieldErrors): void {
        $fieldErrors[$key] = $message;
        $errors[] = $message;
    };
    if ($state === null || !in_array($action, ['review', 'place'], true)) {
        $errors[] = 'Platnost formuláře vypršela. Zkontrolujte údaje a otevřete novou rekapitulaci.';
    }
    if (!$ready || $paymentMethods === [] || $taxRules === []) {
        $errors[] = 'Prodej není nyní dostupný. Objednávka nebyla vytvořena.';
    }
    if (!$summary['available']) {
        $errors[] = $summary['limitExceeded']
            ? 'Celková cena překračuje limit jedné objednávky ' . shopMoney($summary['maximumTotal']) . '. Snižte počet kusů nebo odeberte položky z košíku.'
            : 'Košík je prázdný nebo obsahuje nedostupnou položku. Nejprve jej upravte.';
    }
    foreach (['customer_name' => ['Jméno a příjmení', 255], 'address' => ['Ulice a číslo domu', 255], 'city' => ['Obec', 150], 'postal_code' => ['PSČ', 30]] as $key => [$label, $maxLength]) {
        if ($formData[$key] === '' || mb_strlen($formData[$key], 'UTF-8') > $maxLength || preg_match('/[\x00-\x1f\x7f]/', $formData[$key])) {
            $addError($key, 'Vyplňte pole „' . $label . '“ (nejvýše ' . $maxLength . ' znaků, bez řídicích znaků).');
        }
    }
    if (strlen($formData['email']) > 255 || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $addError('email', 'Vyplňte úplnou e-mailovou adresu ve tvaru jmeno@example.cz.');
    }
    if (!in_array($formData['country_code'], array_column($taxRules, 'country_code'), true)) {
        $addError('country_code', 'Vyberte zemi skutečné fakturační adresy s aktivním daňovým pravidlem. Do ostatních zemí nyní nelze objednat.');
    }
    $paymentId = shopPublicPositiveInt($formData['payment_method_id']);
    if ($paymentId === null || !in_array($paymentId, array_map(static fn (array $method): int => (int)$method['id'], $paymentMethods), true)) {
        $addError('payment_method_id', 'Vyberte dostupný způsob platby.');
    }
    if ($action === 'place') {
        if (!$isReview || ($state['cart'] ?? []) !== $cart
            || !is_string($state['quote_signature'] ?? null)
            || !hash_equals($state['quote_signature'], shopPublicString($_POST, 'quote_signature'))
        ) {
            $errors[] = 'Košík nebo rekapitulace se změnily. Před objednáním znovu zkontrolujte celkovou cenu.';
            $isReview = false;
        }
        if (shopPublicString($_POST, 'confirm_terms') !== '1') {
            $addError('confirm_terms', 'Je nutný nový souhlas s obchodními podmínkami a elektronickým dokladem.');
        }
        if (shopPublicString($_POST, 'confirm_digital') !== '1') {
            $addError('confirm_digital', 'Je nutný výslovný nový souhlas s digitálním dodáním a potvrzení důsledků zpřístupnění.');
        }
    }
    if ($errors === [] && $action === 'review') {
        try {
            $signature = shopCartQuote($pdo, $cart, $formData['country_code']);
            $forms[$nonce] = ['created_at' => time(), 'stage' => 'review', 'formData' => $formData, 'cart' => $cart, 'quote_signature' => $signature];
            $_SESSION['shop_checkout_forms'] = $forms;
            shopPublicRedirect(BASE_URL . '/shop/checkout.php?review=' . rawurlencode($nonce));
        } catch (DomainException | InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable) {
            $errors[] = 'Rekapitulaci se nepodařilo připravit. Zkuste to později.';
        }
    } elseif ($errors === [] && $action === 'place') {
        $customer = array_intersect_key($formData, array_flip(['customer_name', 'email', 'address', 'city', 'postal_code', 'country_code']));
        $customer['confirm_terms'] = '1';
        $customer['confirm_digital'] = '1';
        $customer['quote_signature'] = $state['quote_signature'];
        try {
            $createdOrder = shopCreateOrder($pdo, $customer, $cart, $paymentId);
        } catch (DomainException | InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage() !== '' ? $exception->getMessage() : 'Objednávku nelze přijmout. Zkontrolujte údaje a košík.';
        } catch (Throwable) {
            $errors[] = 'Objednávku se nepodařilo potvrdit. Před dalším pokusem zkontrolujte e-mail nebo přehled svých objednávek.';
        }
        if (isset($createdOrder)) {
            // Persist a consumed nonce before decrypting a token or calling any transport.
            $forms[$nonce] = ['created_at' => time(), 'stage' => 'committed', 'order_number' => (string)$createdOrder['order_number']];
            $_SESSION['shop_checkout_forms'] = $forms;
            $_SESSION['shop_cart'] = [];
            unset($_SESSION['shop_checkout_flash'], $_SESSION['shop_cart_flash']);
            $accountId = currentUserId();
            try {
                $authorization = $accountId !== null && ($createdOrder['user_id'] ?? null) !== null && (int)$createdOrder['user_id'] === $accountId
                    ? ['id' => (int)$createdOrder['id']] : ['token' => shopToken($createdOrder)];
            } catch (Throwable) {
                session_write_close();
                shopPublicError('Objednávka byla přijata', 'Objednávka ' . (string)$createdOrder['order_number'] . ' byla přijata, ale soukromý odkaz nyní nelze zobrazit. Novou objednávku neodesílejte; kontaktujte prodejce.', 200);
            }
            $target = shopPublicOrderLink('order', $authorization);
            $forms[$nonce]['redirect'] = $target;
            $_SESSION['shop_checkout_forms'] = $forms;
            session_write_close();
            // The order is committed. SMTP failures cannot turn acceptance into a failure.
            try {
                shopDispatchOrder($pdo, (int)$createdOrder['id']);
            } catch (Throwable) {
                koraLog('warning', 'shop order dispatch failed', ['order_id' => (int)$createdOrder['id']]);
            }
            shopPublicRedirect($target);
        }
    }
    // Only ordinary fields survive PRG. Neither confirmation nor CAPTCHA is a draft.
    $_SESSION['shop_checkout_flash'] = compact('formData', 'errors', 'fieldErrors', 'nonce');
    shopPublicRedirect(BASE_URL . '/shop/checkout.php' . ($isReview ? '?review=' . rawurlencode($nonce) : ''));
}

$selectedRule = null;
foreach ($taxRules as $rule) {
    if ($rule['country_code'] === $formData['country_code']) {
        $selectedRule = $rule;
        break;
    }
}
$quoteSignature = '';
if ($isReview) {
    try {
        $quoteSignature = shopCartQuote($pdo, $cart, $formData['country_code']);
        // The signed offer must also equal the exact rows displayed below, not a later read.
        $displayedSignature = $selectedRule !== null && $summary['available']
            ? shopQuoteSignature($summary['items'], $formData['country_code'], $selectedRule) : '';
        if ($quoteSignature === '' || $displayedSignature === '' || ($state['cart'] ?? []) !== $cart
            || !hash_equals((string)$state['quote_signature'], $quoteSignature)
            || !hash_equals($displayedSignature, $quoteSignature)
        ) {
            $errors[] = 'Cena, daňové pravidlo nebo košík se změnily. Otevřete novou rekapitulaci a zkontrolujte aktuální konečnou cenu.';
            $isReview = false;
        }
    } catch (DomainException | InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
        $isReview = false;
    } catch (Throwable) {
        $errors[] = 'Rekapitulaci nelze bezpečně ověřit. Zkuste to později.';
        $isReview = false;
    }
}
if (!$isReview) {
    $nonce = bin2hex(random_bytes(32));
    if ($requestMethod !== 'HEAD') {
        foreach ($forms as $key => $entry) {
            if (!is_array($entry) || (int)($entry['created_at'] ?? 0) < time() - 1800) {
                unset($forms[$key]);
            }
        }
        while (count($forms) >= 8) {
            array_shift($forms);
        }
        $forms[$nonce] = ['created_at' => time(), 'stage' => 'details'];
        $_SESSION['shop_checkout_forms'] = $forms;
    }
}
$previewTax = null;
if ($selectedRule !== null && $summary['available']) {
    $previewTax = 0;
    foreach ($summary['items'] as $item) {
        $rateKey = ($item['tax_class'] ?? '') === 'publication' ? 'publication_rate_bp' : 'general_rate_bp';
        $previewTax += shopRateTax((int)$item['subtotal'], (int)$selectedRule[$rateKey]);
    }
}
$captchaExpr = $requestMethod !== 'HEAD' && $isReview && $ready && $summary['available'] && $paymentMethods !== [] && $taxRules !== [] ? captchaGenerate() : '';
shopPublicRender('Dokončení objednávky', 'checkout', array_merge($summary, [
    'ready' => $ready, 'settings' => $settings, 'taxRules' => $taxRules, 'paymentMethods' => $paymentMethods,
    'formData' => $formData, 'errors' => $errors, 'fieldErrors' => $fieldErrors,
    'captchaExpr' => $captchaExpr, 'previewTax' => $previewTax, 'selectedRule' => $selectedRule,
    'signedIn' => currentUserId() !== null, 'isReview' => $isReview,
    'nonce' => $nonce, 'quoteSignature' => $quoteSignature,
]), BASE_URL . '/shop/checkout.php', true);
