<?php

if (!defined('BASE_URL')) {
    http_response_code(404);
    exit;
}

/** @param array<string,mixed> $input */
function shopPublicString(array $input, string $key): string
{
    return is_string($input[$key] ?? null) ? trim($input[$key]) : '';
}

function shopPublicPositiveInt(mixed $value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }
    $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $result === false ? null : (int)$result;
}

/** @return never */
function shopPublicRedirect(string $target): void
{
    header('Location: ' . internalRedirectTarget($target, BASE_URL . '/shop/index.php'), true, 303);
    exit;
}

function shopPublicPrivate(): void
{
    shopSafeHeaders();
    header('Pragma: no-cache');
    header('Expires: 0');

    // The normal theme footer tracks visits; override settings only in this request.
    global $_CMS_SETTINGS;
    $_CMS_SETTINGS = array_replace(getSettings(), [
        'visitor_tracking_enabled' => '0',
        'ga4_measurement_id' => '',
        'custom_head_code' => '',
        'custom_footer_code' => '',
        'cookie_consent_enabled' => '0',
    ]);
    // An additional policy also blocks trackers embedded in configurable widgets/themes.
    header("Content-Security-Policy: default-src 'self'; script-src 'none'; connect-src 'none'; frame-src 'none'; object-src 'none'; img-src 'self' data:; style-src 'self' 'nonce-" . cspNonce() . "'; base-uri 'self'; form-action 'self'", false);
}

/** @param array<string,mixed> $data */
function shopPublicRender(string $title, string $view, array $data, string $canonical, bool $private = false): void
{
    if ($private) {
        // Layout actions and widgets must never receive the capability URL as a return URL.
        $_SERVER['REQUEST_URI'] = $canonical;
    }
    $pageTitle = $title . ' | ' . getSetting('site_name', 'Kora CMS');
    renderPublicPage([
        'title' => $pageTitle,
        'meta' => [
            'title' => $pageTitle,
            'description' => $private ? 'Soukromá stránka digitálního obchodu.' : $title,
            'url' => $canonical,
            'canonical' => $canonical,
        ],
        'view' => 'modules/shop-' . $view,
        'view_data' => $data,
        'current_nav' => 'shop',
        'body_class' => 'page-shop page-shop-' . $view,
        'page_kind' => 'utility',
    ]);
}

/** @return never */
function shopPublicError(string $title, string $message, int $status = 404): void
{
    shopPublicPrivate();
    http_response_code($status);
    shopPublicRender($title, 'status', ['heading' => $title, 'message' => $message, 'status' => $status], BASE_URL . '/shop/index.php', true);
    exit;
}

function shopPublicVerifyCsrf(): void
{
    $submitted = shopPublicString($_POST, 'csrf_token');
    $current = $_SESSION['csrf_token'] ?? '';
    $previous = $_SESSION['csrf_token_prev'] ?? '';
    if ($submitted === '' || !(
        (is_string($current) && $current !== '' && hash_equals($current, $submitted))
        || (is_string($previous) && $previous !== '' && hash_equals($previous, $submitted))
    )) {
        shopPublicError('Akci nelze potvrdit', 'Bezpečnostní ověření vypršelo. Otevřete formulář znovu a akci zopakujte.', 403);
    }
    verifyCsrf();
}

/** @return array<int,int> */
function shopPublicCart(): array
{
    $stored = $_SESSION['shop_cart'] ?? [];
    if (!is_array($stored)) {
        return [];
    }
    $cart = [];
    foreach ($stored as $key => $value) {
        $productId = shopPublicPositiveInt($key);
        $quantity = shopPublicPositiveInt($value);
        if ($productId !== null && $quantity !== null && $quantity <= 100 && count($cart) < 20) {
            $cart[$productId] = $quantity;
        }
    }
    return $cart;
}

/** @return array<string,mixed>|null */
function shopPublicProduct(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare(
        'SELECT p.*, c.name AS category_name FROM cms_shop_products p
         LEFT JOIN cms_shop_categories c ON c.id = p.category_id
         WHERE p.id = ? AND p.is_active = 1
         AND c.is_active = 1 LIMIT 1'
    );
    $statement->execute([$id]);
    $product = $statement->fetch();
    return is_array($product) ? $product : null;
}

/**
 * @param array<int,int> $cart
 * @return array{items:list<array<string,mixed>>,total:int,available:bool,limitExceeded:bool,maximumTotal:int}
 */
function shopPublicCartSummary(PDO $pdo, array $cart): array
{
    $items = [];
    $total = 0;
    $limitExceeded = false;
    $available = $cart !== [];
    foreach ($cart as $id => $quantity) {
        $product = shopPublicProduct($pdo, (int)$id);
        if ($product === null) {
            $available = false;
            $items[] = ['id' => (int)$id, 'title' => 'Nedostupný produkt', 'quantity' => $quantity, 'available' => false];
            continue;
        }
        $price = (int)$product['price_cents'];
        if ($price <= 0 || $price > intdiv(PHP_INT_MAX - $total, $quantity)) {
            $available = false;
            $limitExceeded = $limitExceeded || $price > 0;
            $items[] = ['id' => (int)$id, 'title' => $product['title'], 'quantity' => $quantity, 'available' => false];
            continue;
        }
        $subtotal = $price * $quantity;
        $total += $subtotal;
        $items[] = array_merge($product, ['quantity' => $quantity, 'subtotal' => $subtotal, 'available' => true]);
    }
    $maximumTotal = shopMaximumOrderCents();
    $limitExceeded = $limitExceeded || $total > $maximumTotal;
    return [
        'items' => $items, 'total' => $total, 'available' => $available && !$limitExceeded,
        'limitExceeded' => $limitExceeded, 'maximumTotal' => $maximumTotal,
    ];
}

/** @return array<string,mixed> */
function shopPublicPullFlash(string $key): array
{
    $flash = $_SESSION[$key] ?? [];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        unset($_SESSION[$key]);
    }
    return is_array($flash) ? $flash : [];
}

/** @return array{order:array<string,mixed>,authorization:array<string,string|int>} */
function shopPublicAuthorizedOrder(PDO $pdo): array
{
    if (array_key_exists('token', $_GET)) {
        $token = shopPublicString($_GET, 'token');
        $order = $token !== '' && strlen($token) <= 256 && !preg_match('/[\x00-\x20\x7f]/', $token)
            ? shopOrderByToken($pdo, $token) : null;
        $authorization = ['token' => $token];
    } else {
        $id = shopPublicPositiveInt($_GET['id'] ?? null);
        $accountId = currentUserId();
        $order = $id !== null && $accountId !== null && $accountId > 0 ? shopFindOwnedOrder($pdo, $id) : null;
        if (!is_array($order) || ($order['user_id'] ?? null) === null || (int)$order['user_id'] !== $accountId) {
            $order = null;
        }
        $authorization = ['id' => $id ?? 0];
    }
    if (!is_array($order)) {
        shopPublicError('Objednávka není dostupná', 'Odkaz je neplatný nebo vypršel, případně tato objednávka nepatří k vašemu účtu.');
    }
    return ['order' => $order, 'authorization' => $authorization];
}

/**
 * @param array<string,string|int> $authorization
 * @param array<string,string|int> $parameters
 */
function shopPublicOrderLink(string $endpoint, array $authorization, array $parameters = []): string
{
    return BASE_URL . '/shop/' . $endpoint . '.php?' . http_build_query(array_merge($authorization, $parameters), '', '&', PHP_QUERY_RFC3986);
}

/** @return array<mixed> */
function shopPublicSnapshot(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value)) {
        return [];
    }
    try {
        $decoded = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (JsonException) {
        return [];
    }
}

/**
 * GET reads the existing immutable invoice without ever creating a missing document.
 * @return array<string,mixed>|null
 */
function shopPublicExistingInvoice(PDO $pdo, int $orderId, string $kind): ?array
{
    $statement = $pdo->prepare('SELECT * FROM cms_shop_invoices WHERE order_id = ? AND kind = ? LIMIT 1');
    $statement->execute([$orderId, $kind]);
    $invoice = $statement->fetch();
    return is_array($invoice) ? $invoice : null;
}

/** @return array<string,string> */
function shopPublicInvoiceKinds(): array
{
    return ['proforma' => 'Platební výzva (nedaňový doklad)', 'final' => 'Faktura', 'credit' => 'Opravný doklad'];
}

/**
 * A historical proforma is not a new invitation to pay. Only its rendering copy
 * receives live payment status; financial, seller, legal and item snapshots stay intact.
 * @param array<mixed> $snapshot
 * @param array<string,mixed> $order
 * @return array<mixed>
 */
function shopPublicInvoiceRenderingSnapshot(array $snapshot, array $order): array
{
    if (($snapshot['kind'] ?? '') === 'proforma' && is_array($snapshot['order'] ?? null)) {
        $snapshot['order']['status'] = (string)($order['status'] ?? '');
        $snapshot['order']['paid_at'] = $order['paid_at'] ?? null;
    }
    return $snapshot;
}

/**
 * @param array<string,string> $errors
 * @param list<string> $descriptions
 */
function shopPublicFieldAttributes(array $errors, string $key, array $descriptions = []): string
{
    if (isset($errors[$key])) {
        $descriptions[] = 'shop-' . str_replace('_', '-', $key) . '-error';
    }
    return (isset($errors[$key]) ? ' aria-invalid="true"' : '')
        . ($descriptions !== [] ? ' aria-describedby="' . h(implode(' ', $descriptions)) . '"' : '');
}

/** @param array<string,string> $errors */
function shopPublicFieldError(array $errors, string $key): string
{
    return isset($errors[$key])
        ? '<p class="field-help field-error" id="shop-' . h(str_replace('_', '-', $key)) . '-error">' . h((string)$errors[$key]) . '</p>'
        : '';
}

function shopPublicPathKey(string $path): string
{
    $normalized = rtrim(str_replace('\\', '/', $path), '/');
    return DIRECTORY_SEPARATOR === '\\' ? strtolower($normalized) : $normalized;
}

/**
 * Validate the immutable snapshot, not the current product's file.
 * @param array<string,mixed> $item
 */
function shopPublicVerifiedFile(array $item): ?string
{
    $name = (string)($item['file_storage_name'] ?? '');
    $expectedHash = (string)($item['file_sha256'] ?? '');
    $expectedSize = shopPublicPositiveInt($item['file_size'] ?? null);
    if (preg_match('/\A[a-f0-9]{64}\.bin\z/', $name) !== 1
        || preg_match('/\A[a-f0-9]{64}\z/', $expectedHash) !== 1
        || $name !== $expectedHash . '.bin' || $expectedSize === null
    ) {
        return null;
    }
    $root = realpath(koraStoragePath('shop/files'));
    if ($root === false) {
        return null;
    }
    try {
        $path = shopPrivatePath($name);
    } catch (Throwable) {
        return null;
    }
    if ($path === '') {
        return null;
    }
    $resolved = realpath($path);
    $webroot = realpath(dirname(__DIR__));
    if ($resolved === false || $webroot === false
        || shopPublicPathKey($resolved) !== shopPublicPathKey($root . DIRECTORY_SEPARATOR . $name)
        || shopPublicPathKey($resolved) !== shopPublicPathKey($path)
        || str_starts_with(shopPublicPathKey($resolved), shopPublicPathKey($webroot) . '/')
        || !is_file($resolved) || !is_readable($resolved)
    ) {
        return null;
    }
    for ($part = $path; ; $part = dirname($part)) {
        if (is_link($part)) {
            return null;
        }
        if (dirname($part) === $part) {
            break;
        }
    }
    if (filesize($resolved) !== $expectedSize) {
        return null;
    }
    try {
        return shopProductFileValid($item) ? $resolved : null;
    } catch (Throwable) {
        return null;
    }
}
