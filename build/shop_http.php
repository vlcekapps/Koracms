<?php

declare(strict_types=1);

require_once __DIR__ . '/http_test_helpers.php';
require_once __DIR__ . '/shop_mysql_selftest.php';

// The integration runner supplies this helper; standalone static analysis does not.
if (!function_exists('httpIntegrationStatusCode')) {
    /** @param array{status:string,headers:array<int,string>,body:string} $response */
    function httpIntegrationStatusCode(array $response): int
    {
        return preg_match('/\s(\d{3})\s/', $response['status'], $matches) === 1 ? (int)$matches[1] : 0;
    }
}

/** @param array{status:string,headers:array<int,string>,body:string} $response */
function shopHttpPrivateHeaders(array $response): bool
{
    $headers = strtolower(implode("\n", $response['headers']));
    return str_contains($headers, 'cache-control:') && str_contains($headers, 'no-store')
        && str_contains($headers, 'x-robots-tag: noindex') && str_contains($headers, 'referrer-policy: no-referrer')
        && str_contains($headers, 'x-content-type-options: nosniff');
}

/** Extract only the bounded Flate/CID text format produced by our invoice writer. */
function shopHttpPdfText(string $pdf): string
{
    if (!str_starts_with($pdf, '%PDF-') || strlen($pdf) > 8 * 1024 * 1024) {
        return '';
    }
    preg_match_all('/<<[^>]*\/Length\s+([0-9]+)[^>]*>>\s*stream\r?\n/s', $pdf, $matches, PREG_OFFSET_CAPTURE);
    $streams = [];
    $characters = [];
    foreach ($matches[0] as $index => [$header, $offset]) {
        $length = (int)$matches[1][$index][0];
        if ($length > 4 * 1024 * 1024 || !str_contains($header, '/FlateDecode')) {
            continue;
        }
        $compressed = substr($pdf, $offset + strlen($header), $length);
        set_error_handler(static fn (): bool => true);
        try {
            $decoded = gzuncompress($compressed, 4 * 1024 * 1024);
        } finally {
            restore_error_handler();
        }
        if (!is_string($decoded)) {
            continue;
        }
        $streams[] = $decoded;
        if (str_contains($decoded, 'beginbfchar')) {
            preg_match_all('/<([0-9A-F]{4})>\s*<([0-9A-F]{4,8})>/', $decoded, $mapping, PREG_SET_ORDER);
            foreach ($mapping as $entry) {
                $bytes = hex2bin($entry[2]);
                if (is_string($bytes)) {
                    $characters[$entry[1]] = mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE');
                }
            }
        }
    }
    $text = '';
    foreach ($streams as $stream) {
        preg_match_all('/<([0-9A-F]+)>\s*Tj/', $stream, $runs);
        foreach ($runs[1] as $run) {
            foreach (str_split($run, 4) as $cid) {
                $text .= $characters[$cid] ?? '?';
            }
            $text .= "\n";
        }
    }
    return $text;
}

/** @return list<string> */
function shopHttpFormIssues(string $html, bool $confirmations = true): array
{
    $issues = [];
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try {
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $xpath = new DOMXPath($doc);
    $ids = [];
    foreach ($xpath->query('//*[@id]') ?: [] as $element) {
        if ($element instanceof DOMElement) {
            $id = $element->getAttribute('id');
            $ids[$id] = ($ids[$id] ?? 0) + 1;
        }
    }
    foreach ($xpath->query('//*[@aria-describedby or @aria-labelledby]') ?: [] as $element) {
        if (!$element instanceof DOMElement) {
            continue;
        }
        foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
            foreach (preg_split('/\s+/', trim($element->getAttribute($attribute))) ?: [] as $id) {
                if ($id !== '' && ($ids[$id] ?? 0) !== 1) {
                    $issues[] = 'Shop form has a missing or duplicate ARIA target: ' . $id;
                }
            }
        }
    }
    foreach ($confirmations ? ['confirm_terms', 'confirm_digital'] : [] as $name) {
        $inputs = $xpath->query('//input[@name="' . $name . '"]');
        $input = $inputs !== false ? $inputs->item(0) : null;
        if (!$input instanceof DOMElement || $inputs->length !== 1
            || $input->getAttribute('type') !== 'checkbox' || $input->hasAttribute('checked')) {
            $issues[] = 'Shop confirmation must exist exactly once and remain unchecked: ' . $name;
            continue;
        }
        $id = $input->getAttribute('id');
        $labelled = false;
        foreach ($xpath->query('//label[@for]') ?: [] as $label) {
            if ($label instanceof DOMElement && $id !== '' && $label->getAttribute('for') === $id) {
                $labelled = true;
            }
        }
        if (!$labelled || ($xpath->query('//fieldset/legend')->length ?? 0) === 0) {
            $issues[] = 'Shop confirmation lacks a label or fieldset legend: ' . $name;
        }
    }
    $skip = $xpath->query('//a[contains(@class,"skip-link")]');
    if ($skip === false || $skip->length === 0) {
        $issues[] = 'Shop checkout is missing its public skip link';
    } else {
        $link = $skip->item(0);
        $target = $link instanceof DOMElement ? $link->getAttribute('href') : '';
        if (!str_starts_with($target, '#') || ($ids[substr($target, 1)] ?? 0) !== 1) {
            $issues[] = 'Shop checkout skip link has no unique target';
        }
    }
    return $issues;
}

/** @return array<string,mixed> */
function shopHttpSessionData(string $id): array
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_id($id);
    session_start();
    $data = $_SESSION;
    session_write_close();
    return $data;
}

/**
 * @param array{id:string,cookie:string,csrf:string} $session
 * @param array{status:string,headers:array<int,string>,body:string} $page
 * @param array<string,string> $data
 * @return array<string,string>
 */
function shopHttpCheckoutFields(array $session, array $page, array $data = []): array
{
    foreach (['csrf_token', 'checkout_nonce', 'action', 'quote_signature'] as $name) {
        $value = extractHiddenInputValue($page['body'], $name);
        if ($value !== '') {
            $data[$name] = $value;
        }
    }
    $data['captcha'] = (string)(shopHttpSessionData($session['id'])['captcha_answer'] ?? '');
    return $data;
}

/**
 * Run only under the coordinated integration runner. No schema installation,
 * SMTP calls, bank API calls, global cron execution, or broad cleanup.
 * @param array{cookie:string,csrf:string} $adminSession
 * @return list<string>
 */
function shopHttpChecks(PDO $pdo, string $baseUrl, array $adminSession): array
{
    require_once dirname(__DIR__) . '/lib/shop.php';
    require_once dirname(__DIR__) . '/lib/shop_invoice.php';
    $fixture = new ShopTestFixture($pdo);
    $issues = [];
    $sessions = [];
    $originalSessionId = session_id();
    $hadActiveSession = session_status() === PHP_SESSION_ACTIVE;
    $originalSession = $_SESSION ?? [];
    $url = rtrim($baseUrl, '/') . BASE_URL . '/shop/';
    $check = static function (bool $condition, string $message) use (&$issues): void {
        if (!$condition) {
            $issues[] = $message;
        }
    };
    $newSession = static function (array $data) use (&$sessions): array {
        $session = koraPrimeTestSession($data, 'shop-http-' . bin2hex(random_bytes(12)));
        $sessions[] = $session['id'];
        return $session;
    };
    try {
        $fixture->setup(false, rtrim($baseUrl, '/'));
        $country = $fixture->countries[0];
        $cart = [$fixture->productIds[0] => 1];
        $guest = $newSession(['shop_cart' => $cart]);
        $initial = fetchUrl($url . 'checkout.php', $guest['cookie'], 0);
        $check(httpIntegrationStatusCode($initial) === 200 && shopHttpPrivateHeaders($initial), 'Shop checkout must render with private/noindex headers');
        $issues = array_merge($issues, shopHttpFormIssues($initial['body'], false));
        $details = array_map(static fn (mixed $value): string => (string)$value, $fixture->customer($country));
        unset($details['confirm_terms'], $details['confirm_digital']);
        $details['payment_method_id'] = (string)$fixture->methodId;
        $fields = shopHttpCheckoutFields($guest, $initial, $details);
        // postUrl refreshes CSRF by design; raw POST is required for the forged-token test.
        $forged = postRawUrl(
            $url . 'checkout.php',
            http_build_query(array_replace($fields, ['csrf_token' => 'forged'])),
            'application/x-www-form-urlencoded',
            $guest['cookie'],
            0
        );
        $check(httpIntegrationStatusCode($forged) === 403 && $fixture->orders() === [], 'Public shop rejects forged CSRF without creating an order');
        $forgedCart = postRawUrl(
            $url . 'cart.php',
            http_build_query(['csrf_token' => 'forged', 'action' => 'clear']),
            'application/x-www-form-urlencoded',
            $guest['cookie'],
            0
        );
        $check(
            httpIntegrationStatusCode($forgedCart) === 403 && (shopHttpSessionData($guest['id'])['shop_cart'] ?? []) === $cart,
            'Public cart rejects forged CSRF without changing contents'
        );

        $reviewResponse = postUrl($url . 'checkout.php', $fields, $guest['cookie'], 0);
        $reviewUrl = $url . 'checkout.php?review=' . rawurlencode($fields['checkout_nonce']);
        $check(httpIntegrationStatusCode($reviewResponse) === 303 && $fixture->orders() === [], 'Review does not yet create a binding order');
        $review = fetchUrl($reviewUrl, $guest['cookie'], 0);
        $issues = array_merge($issues, shopHttpFormIssues($review['body']));
        $check(
            str_contains($review['body'], 'Objednávka zavazující k platbě')
            && str_contains($review['body'], $fixture->prefix . ' terms') && str_contains($review['body'], '121,00 Kč'),
            'Final review shows binding payment button, exact price and legal snapshot'
        );
        $invalidFields = shopHttpCheckoutFields($guest, $review, ['confirm_terms' => '1', 'confirm_digital' => '1']);
        $invalidCaptcha = postUrl($url . 'checkout.php', array_replace($invalidFields, ['captcha' => '999']), $guest['cookie'], 0);
        $check(httpIntegrationStatusCode($invalidCaptcha) === 303 && $fixture->orders() === [], 'Wrong final public CAPTCHA cannot create an order');
        $errors = fetchUrl($reviewUrl, $guest['cookie'], 0);
        $check(
            str_contains($errors['body'], publicCaptchaErrorMessage()) && str_contains($errors['body'], $fixture->prefix),
            'Final CAPTCHA error explains recovery and preserves customer input'
        );
        $issues = array_merge($issues, shopHttpFormIssues($errors['body']));
        $noConsent = shopHttpCheckoutFields($guest, $errors);
        $consentResponse = postUrl($url . 'checkout.php', $noConsent, $guest['cookie'], 0);
        $check(httpIntegrationStatusCode($consentResponse) === 303 && $fixture->orders() === [], 'Previous posted consent is not reused on another checkout attempt');
        $consentForm = fetchUrl($reviewUrl, $guest['cookie'], 0);
        $issues = array_merge($issues, shopHttpFormIssues($consentForm['body']));

        $staleFields = shopHttpCheckoutFields($guest, $consentForm, ['confirm_terms' => '1', 'confirm_digital' => '1']);
        $pdo->prepare('UPDATE cms_shop_products SET price_cents=12101 WHERE id=?')->execute([$fixture->productIds[0]]);
        $stale = postUrl($url . 'checkout.php', $staleFields, $guest['cookie'], 0);
        $check(httpIntegrationStatusCode($stale) === 303 && $fixture->orders() === [], 'Price changed after review requires a new explicit confirmation');
        $pdo->prepare('UPDATE cms_shop_products SET price_cents=12100 WHERE id=?')->execute([$fixture->productIds[0]]);

        // Signed-in customers are subject to the same one-use CAPTCHA.
        $signed = $newSession(['cms_user_id' => $fixture->accountIds[0], 'shop_cart' => $cart]);
        $signedPage = fetchUrl($url . 'checkout.php', $signed['cookie'], 0);
        $signedFields = shopHttpCheckoutFields($signed, $signedPage, $details);
        postUrl($url . 'checkout.php', $signedFields, $signed['cookie'], 0);
        $signedReview = fetchUrl($url . 'checkout.php?review=' . rawurlencode($signedFields['checkout_nonce']), $signed['cookie'], 0);
        $signedPlace = shopHttpCheckoutFields($signed, $signedReview, ['confirm_terms' => '1', 'confirm_digital' => '1']);
        $signedResponse = postUrl($url . 'checkout.php', array_replace($signedPlace, ['captcha' => '999']), $signed['cookie'], 0);
        $check(httpIntegrationStatusCode($signedResponse) === 303 && $fixture->orders() === [], 'Signed-in buyer cannot bypass CAPTCHA');

        $fresh = fetchUrl($url . 'checkout.php', $guest['cookie'], 0);
        $fields = shopHttpCheckoutFields($guest, $fresh, $details);
        postUrl($url . 'checkout.php', $fields, $guest['cookie'], 0);
        $reviewUrl = $url . 'checkout.php?review=' . rawurlencode($fields['checkout_nonce']);
        $freshReview = fetchUrl($reviewUrl, $guest['cookie'], 0);
        $issues = array_merge($issues, shopHttpFormIssues($freshReview['body']));
        $fields = shopHttpCheckoutFields($guest, $freshReview, ['confirm_terms' => '1', 'confirm_digital' => '1']);
        // Real sendMail() explicitly short-circuits .test recipients before SMTP.
        $success = postUrl($url . 'checkout.php', $fields, $guest['cookie'], 0);
        $orders = $fixture->orders();
        $check(httpIntegrationStatusCode($success) === 303 && count($orders) === 1, 'Fresh signed quote and explicit consents create exactly one guest order');
        if (count($orders) !== 1) {
            return $issues;
        }
        $order = $orders[0];
        $repeat = postUrl($url . 'checkout.php', $fields, $guest['cookie'], 0);
        $check(httpIntegrationStatusCode($repeat) === 303 && count($fixture->orders()) === 1
            && responseLocationHeaderValue($repeat['headers']) === responseLocationHeaderValue($success['headers']), 'Replaying the committed checkout returns the same order without another acceptance');
        $id = (int)$order['id'];
        $token = shopToken($order);
        $items = shopOrderItems($pdo, $id);
        $item = (int)$items[0]['id'];
        $tokenQuery = 'token=' . rawurlencode($token);
        $check($order['user_id'] === null && $order['status'] === 'accepted', 'Guest email never attaches an account; foreign order remains on tax hold');
        $earlyInvoice = fetchUrl($url . 'invoice.php?' . $tokenQuery . '&kind=proforma', $guest['cookie'], 0);
        $check(httpIntegrationStatusCode($earlyInvoice) === 404, 'Foreign held order exposes no premature payment instruction');
        $check((shopHttpSessionData($guest['id'])['shop_cart'] ?? null) === [], 'Accepted order clears its own cart');
        $page = fetchUrl($url . 'order.php?' . $tokenQuery, $guest['cookie'], 0);
        $check(httpIntegrationStatusCode($page) === 200 && shopHttpPrivateHeaders($page), 'Valid bearer order is private and accessible');
        $check(!str_contains($page['body'], 'google-analytics.com') && !str_contains($page['body'], 'googletagmanager.com'), 'Bearer page excludes analytics');
        $unpaid = fetchUrl($url . 'download.php?' . $tokenQuery . '&item=' . $item, $guest['cookie'], 0);
        $check(httpIntegrationStatusCode($unpaid) === 403 && shopHttpPrivateHeaders($unpaid), 'No unpaid public download');
        foreach (['order.php', 'invoice.php', 'download.php', 'withdraw.php'] as $endpoint) {
            $anonymous = fetchUrl($url . $endpoint . '?id=' . $id . '&item=' . $item, $guest['cookie'], 0);
            $wrongOwner = fetchUrl($url . $endpoint . '?id=' . $id . '&item=' . $item, $signed['cookie'], 0);
            $badToken = fetchUrl($url . $endpoint . '?token=' . bin2hex(random_bytes(32)) . '&item=' . $item, $guest['cookie'], 0);
            $check(httpIntegrationStatusCode($anonymous) === 404 && httpIntegrationStatusCode($wrongOwner) === 404
                && httpIntegrationStatusCode($badToken) === 404, 'Guest id, matching-email account and unknown token cannot authorize ' . $endpoint);
        }
        // An actual server-owned fixture checks positive and negative account authorization.
        $_SESSION['cms_user_id'] = $fixture->accountIds[0];
        $owned = $fixture->createOrder($country);
        unset($_SESSION['cms_user_id']);
        $other = $newSession(['cms_user_id' => $fixture->accountIds[1]]);
        $ownerPage = fetchUrl($url . 'order.php?id=' . $owned['id'], $signed['cookie'], 0);
        $otherPage = fetchUrl($url . 'order.php?id=' . $owned['id'], $other['cookie'], 0);
        $adminPage = fetchUrl($url . 'order.php?id=' . $owned['id'], $adminSession['cookie'], 0);
        $check(httpIntegrationStatusCode($ownerPage) === 200 && httpIntegrationStatusCode($otherPage) === 404
            && httpIntegrationStatusCode($adminPage) === 404, 'Only the owning account, not another account or admin, has public id access');
        $withdrawUrl = $url . 'withdraw.php?id=' . $owned['id'];
        $withdrawReview = fetchUrl($withdrawUrl, $signed['cookie'], 0);
        $issues = array_merge($issues, shopHttpFormIssues($withdrawReview['body'], false));
        $check(httpIntegrationStatusCode($withdrawReview) === 200 && shopHttpPrivateHeaders($withdrawReview)
            && str_contains($withdrawReview['body'], 'Potvrdit odstoupení')
            && str_contains($ownerPage['body'], 'Odstoupit od smlouvy'), 'Owner gets a prominent, private withdrawal review without retyping identity');
        $badWithdrawal = postRawUrl(
            $withdrawUrl,
            http_build_query(['csrf_token' => 'invalid', 'confirm_withdrawal' => '1']),
            'application/x-www-form-urlencoded',
            $signed['cookie'],
            0
        );
        $check(httpIntegrationStatusCode($badWithdrawal) === 403 && $fixture->order((int)$owned['id'])['status'] === 'accepted', 'Withdrawal rejects bad CSRF without cancelling');
        $withdrawReview = fetchUrl($withdrawUrl, $signed['cookie'], 0);
        $unconfirmed = postUrl($withdrawUrl, ['csrf_token' => extractHiddenInputValue($withdrawReview['body'], 'csrf_token')], $signed['cookie'], 0);
        $withdrawReview = fetchUrl($withdrawUrl, $signed['cookie'], 0);
        $issues = array_merge($issues, shopHttpFormIssues($withdrawReview['body'], false));
        $check(httpIntegrationStatusCode($unconfirmed) === 303 && $fixture->order((int)$owned['id'])['status'] === 'accepted'
            && str_contains($withdrawReview['body'], 'Chybí potvrzení odstoupení.'), 'Missing withdrawal confirmation returns an accessible error without state change');
        $confirmedWithdrawal = postUrl($withdrawUrl, ['csrf_token' => extractHiddenInputValue($withdrawReview['body'], 'csrf_token'),
            'confirm_withdrawal' => '1'], $signed['cookie'], 0);
        $withdrawn = $fixture->order((int)$owned['id']);
        $check(httpIntegrationStatusCode($confirmedWithdrawal) === 303 && $withdrawn['status'] === 'cancelled'
            && $withdrawn['mail_sent_status'] === 'cancelled' && $withdrawn['fulfilled_at'] === null
            && shopWithdrawalText($pdo, $withdrawn) !== null, 'Confirmed owner withdrawal stops delivery and records a permanent acknowledgement');
        $closedReview = fetchUrl($withdrawUrl, $signed['cookie'], 0);
        $check(!str_contains($closedReview['body'], 'name="confirm_withdrawal"'), 'Closed order does not offer a new withdrawal form');
        shopVerifyTax($pdo, $id, $fixture->evidence($country));
        $proforma = shopInvoice($pdo, $fixture->order($id), 'proforma');
        $checkProforma = static function (string $phase) use ($pdo, $fixture, $id, $url, $baseUrl, $guest, $adminSession, $proforma, $check): void {
            foreach (['html', 'text', 'pdf'] as $format) {
                $public = fetchUrl($url . 'invoice.php?token=' . rawurlencode(shopToken($fixture->order($id))) . '&kind=proforma&format=' . $format, $guest['cookie'], 0);
                $admin = fetchUrl(rtrim($baseUrl, '/') . BASE_URL . '/admin/shop_invoice.php?id=' . $proforma['id'] . '&format=' . $format, $adminSession['cookie'], 0);
                foreach (['public' => $public, 'admin' => $admin] as $surface => $response) {
                    $text = $format === 'pdf' ? shopHttpPdfText($response['body']) : $response['body'];
                    $check(httpIntegrationStatusCode($response) === 200 && shopHttpPrivateHeaders($response)
                        && str_contains($text, 'Neplaťte znovu.') && !str_contains($text, 'K úhradě:')
                        && !str_contains($text, 'QR platba (SPAYD):'), 'Old ' . $surface . ' proforma ' . $format . ' has no live payment offer after ' . $phase);
                    $check($format === 'pdf' ? !str_contains($response['body'], '/Subtype /Image') && !str_contains($response['body'], '/S /Figure')
                        : !str_contains($response['body'], 'qr kód k platbě'), 'Old ' . $surface . ' proforma ' . $format . ' has no payment QR after ' . $phase);
                }
            }
            $qr = fetchUrl($url . 'invoice.php?token=' . rawurlencode(shopToken($fixture->order($id))) . '&kind=proforma&format=qr', $guest['cookie'], 0);
            $check(httpIntegrationStatusCode($qr) === 404, 'Proforma QR endpoint refuses obsolete payment instructions after ' . $phase);
            $check(shopInvoice($pdo, $fixture->order($id), 'proforma') === $proforma, 'Rendering an old proforma never rewrites its immutable DB snapshot after ' . $phase);
        };
        shopRecordPayment($pdo, $id, $fixture->prefix . '-http-paid');
        $paid = fetchUrl($url . 'download.php?' . $tokenQuery . '&item=' . $item, $guest['cookie'], 0);
        $check(httpIntegrationStatusCode($paid) === 403, 'Payment alone does not bypass delivery gate');
        $sent = shopDispatchOrder($pdo, $id, static fn (string $recipient, string $subject, string $body, array $options): bool => true);
        $check($sent, 'Injected HTTP-fixture dispatch succeeds without SMTP');
        $deliveredWithdrawal = fetchUrl($url . 'withdraw.php?' . $tokenQuery, $guest['cookie'], 0);
        $check(httpIntegrationStatusCode($deliveredWithdrawal) === 200 && shopHttpPrivateHeaders($deliveredWithdrawal)
            && !str_contains($deliveredWithdrawal['body'], 'name="confirm_withdrawal"')
            && str_contains($deliveredWithdrawal['body'], 'Práva z vad'), 'Delivered digital content explains warranty rights instead of offering ordinary withdrawal');
        $checkProforma('fulfilled');
        $download = fetchUrl($url . 'download.php?' . $tokenQuery . '&item=' . $item, $guest['cookie'], 0);
        $path = shopPrivatePath((string)$items[0]['file_storage_name']);
        $check(httpIntegrationStatusCode($download) === 200 && shopHttpPrivateHeaders($download)
            && $download['body'] === $fixture->files[$path], 'Fulfilled token downloads exactly its immutable private bytes');
        $foreignItem = shopOrderItems($pdo, (int)$owned['id'])[0];
        $crossItem = fetchUrl($url . 'download.php?' . $tokenQuery . '&item=' . $foreignItem['id'], $guest['cookie'], 0);
        $check(httpIntegrationStatusCode($crossItem) === 404, 'Token cannot download an item from another order');
        $replacement = $fixture->createFile(2);
        $pdo->prepare('UPDATE cms_shop_products SET file_storage_name=?,file_original_name=?,file_size=?,file_sha256=?,price_cents=1,title=? WHERE id=?')
            ->execute([$replacement['file_storage_name'], 'new-name.pdf', $replacement['file_size'], $replacement['file_sha256'], 'Edited product', $fixture->productIds[0]]);
        $unchanged = fetchUrl($url . 'download.php?' . $tokenQuery . '&item=' . $item, $guest['cookie'], 0);
        $check($unchanged['body'] === $download['body'], 'Catalogue edits do not change the purchased download');
        $originalBytes = $fixture->files[$path];
        try {
            if (file_put_contents($path, str_repeat('x', strlen($originalBytes))) !== strlen($originalBytes)) {
                throw new RuntimeException('Cannot prepare same-size corrupted private fixture');
            }
            clearstatcache(true, $path);
            $corrupt = fetchUrl($url . 'download.php?' . $tokenQuery . '&item=' . $item, $guest['cookie'], 0);
            $check(httpIntegrationStatusCode($corrupt) === 404, 'Same-size tampered purchased bytes are never released');
        } finally {
            if (file_put_contents($path, $originalBytes) !== strlen($originalBytes)) {
                throw new RuntimeException('Cannot restore owned private fixture bytes');
            }
            clearstatcache(true, $path);
        }
        $pdo->prepare('UPDATE cms_shop_orders SET token_expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?')->execute([$id]);
        $expired = fetchUrl($url . 'download.php?' . $tokenQuery . '&item=' . $item, $guest['cookie'], 0);
        $check(httpIntegrationStatusCode($expired) === 404, 'Expired token cannot download a fulfilled order');
        $pdo->prepare('UPDATE cms_shop_orders SET token_expires_at=DATE_ADD(NOW(),INTERVAL 1 DAY) WHERE id=?')->execute([$id]);
        shopChangeOrder($pdo, $id, 'refund', 'HTTP fixture refund completed');
        $checkProforma('refunded');
        $revoked = fetchUrl($url . 'download.php?' . $tokenQuery . '&item=' . $item, $guest['cookie'], 0);
        $check(httpIntegrationStatusCode($revoked) === 403, 'Refund revokes public download');
    } catch (Throwable $exception) {
        $issues[] = 'Shop HTTP checks failed: ' . $exception->getMessage();
    } finally {
        try {
            $fixture->cleanup();
        } catch (Throwable $exception) {
            $issues[] = 'Shop HTTP fixture cleanup failed: ' . $exception->getMessage();
        }
        foreach ($sessions as $sessionId) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id($sessionId);
            session_start();
            $_SESSION = [];
            session_destroy();
        }
        session_id($originalSessionId);
        if ($hadActiveSession && $originalSessionId !== '') {
            session_start();
        }
        $_SESSION = $originalSession;
    }
    return $issues;
}
