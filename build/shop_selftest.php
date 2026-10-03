<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// This bootstrap never connects to the configured database.
require_once __DIR__ . '/unit_test_bootstrap.php';
require_once dirname(__DIR__) . '/lib/shop.php';
require_once dirname(__DIR__) . '/lib/shop_invoice.php';
require_once __DIR__ . '/shop_http.php';

$checks = 0;
$same = static function (mixed $expected, mixed $actual, string $label) use (&$checks): void {
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    $checks++;
};
$rejects = static function (callable $operation, string $label) use ($same): void {
    try {
        $operation();
    } catch (DomainException) {
        $same(true, true, $label);
        return;
    }
    throw new RuntimeException($label . ': expected DomainException');
};

try {
    // Tuples, not numeric-string array keys: PHP coerces keys such as "0".
    foreach ([['0', 0], ['1', 100], ['0.01', 1], ['0,10', 10], ['1.2', 120],
        [' 1234,56 ', 123456], ['999999999.99', 99999999999]] as [$input, $expected]) {
        $same($expected, shopAmountCents($input), 'Exact amount ' . $input);
    }
    foreach (['', '-1', '+1', '01', '.50', '1.', '1.001', '1e2', 'NaN', '1 000',
        '1000000000', "1\n2", '0x10', '1,2.3'] as $input) {
        $same(null, shopAmountCents($input), 'Reject amount ' . json_encode($input));
    }
    $same('0,00 Kč', shopMoney(0), 'Zero money');
    $same('1 234,56 Kč', shopMoney(123456), 'Grouped money');
    $same('-0,01 Kč', shopMoney(-1), 'Negative cent');
    $same(999999999, shopMaximumOrderCents(), 'Order ceiling matches the SPAYD amount boundary');
    foreach ([[12100, 2100, 2100], [11200, 1200, 1200], [10000, 0, 0],
        [1, 2100, 0], [3, 2100, 1], [1, 10000, 1], [1000000000000, 2100, 173553719008]] as [$gross, $rate, $tax]) {
        $same($tax, shopRateTax($gross, $rate), 'Integer inclusive tax ' . $gross . '/' . $rate);
    }
    foreach ([[-1, 0], [1000000000001, 0], [1, -1], [1, 10001]] as [$gross, $rate]) {
        $rejects(static fn (): int => shopRateTax($gross, $rate), 'Reject tax boundary');
    }
    $same(['accepted', 'awaiting_payment', 'paid', 'fulfilled', 'cancelled', 'refunded'], array_keys(shopStates()), 'Only contractual states');
    foreach (['accepted', 'awaiting_payment', 'paid'] as $withdrawableState) {
        $same(true, shopWithdrawalAvailable(['status' => $withdrawableState, 'fulfilled_at' => null]), 'Withdrawal is available before delivery: ' . $withdrawableState);
        $same(false, shopWithdrawalAvailable(['status' => $withdrawableState, 'fulfilled_at' => '2026-01-01 12:00:00']), 'An existing delivery date always prevents withdrawal: ' . $withdrawableState);
    }
    foreach (['fulfilled', 'cancelled', 'refunded', 'unknown', ''] as $closedState) {
        $same(false, shopWithdrawalAvailable(['status' => $closedState]), 'Closed or unknown state cannot begin withdrawal: ' . $closedState);
    }
    $same(false, shopReady(), 'An unconfigured shop fails closed');
    $eligible = ['status' => 'fulfilled', 'paid_at' => '2026-01-01 12:00:00',
        'consent_at' => '2026-01-01 11:00:00', 'tax_verified_at' => '2026-01-01 11:00:00',
        'token_expires_at' => date('Y-m-d H:i:s', time() + 3600)];
    $same(true, shopCanDownload($eligible), 'Eligible fulfilled download');
    foreach ([0, '0'] as $sqlExpired) {
        $same(false, shopCanDownload(array_replace($eligible, ['token_active' => $sqlExpired])), 'SQL expired token overrides a PHP-future timestamp');
    }
    foreach ([1, '1'] as $sqlActive) {
        $same(true, shopCanDownload(array_replace($eligible, ['token_active' => $sqlActive,
            'token_expires_at' => date('Y-m-d H:i:s', time() - 3600)])), 'SQL active token overrides a PHP-past timestamp');
    }
    foreach (['accepted', 'awaiting_payment', 'paid', 'cancelled', 'refunded', 'mail_error', ''] as $state) {
        $same(false, shopCanDownload(array_replace($eligible, ['status' => $state])), 'Deny state ' . $state);
    }
    foreach (['paid_at', 'consent_at', 'tax_verified_at'] as $key) {
        $same(false, shopCanDownload(array_replace($eligible, [$key => null])), 'Deny missing ' . $key);
    }
    foreach ([date('Y-m-d H:i:s', time() - 1), 'invalid', ''] as $expiry) {
        $same(false, shopCanDownload(array_replace($eligible, ['token_expires_at' => $expiry])), 'Deny expiry');
    }
    foreach (['../secret.key', str_repeat('a', 64) . '.php', '/etc/passwd', '', str_repeat('A', 64) . '.bin'] as $name) {
        $same('', shopPrivatePath($name), 'Reject unsafe storage name');
    }
    $snapshot = ['kind' => 'proforma', 'order' => ['order_number' => '2026000001',
        'total_cents' => 123456, 'currency' => 'CZK', 'status' => 'awaiting_payment'],
        'payment' => ['iban' => 'CZ6508000000192000145399', 'account_number' => '19-2000145399/0800']];
    $same('SPD*1.0*ACC:CZ6508000000192000145399*AM:1234.56*CC:CZK*X-VS:2026000001', shopPaymentSpayd($snapshot), 'Exact SPAYD');
    $cent = $snapshot;
    $cent['order']['total_cents'] = 1;
    $same('SPD*1.0*ACC:CZ6508000000192000145399*AM:0.01*CC:CZK*X-VS:2026000001', shopPaymentSpayd($cent), 'SPAYD one cent');
    foreach (['paid', 'fulfilled', 'cancelled', 'refunded'] as $state) {
        $copy = $snapshot;
        $copy['order']['status'] = $state;
        $same('', shopPaymentSpayd($copy), 'No payment QR for ' . $state);
    }
    foreach (['currency' => 'EUR', 'order_number' => '2026*AM:1', 'total_cents' => -1] as $key => $value) {
        $copy = $snapshot;
        $copy['order'][$key] = $value;
        $rejects(static fn (): string => shopPaymentSpayd($copy), 'Reject malformed SPAYD ' . $key);
    }
    $copy = $snapshot;
    $copy['payment']['iban'] = 'CZ6508000000192000145398';
    $rejects(static fn (): string => shopPaymentSpayd($copy), 'Reject invalid IBAN checksum');
    $same(false, shopProductFileValid([]), 'Missing file metadata fails closed without filesystem access');
    $same(false, shopProductFileValid(['file_storage_name' => '../secret.key']), 'Unsafe file metadata fails closed');
    $same(403, httpIntegrationStatusCode(['status' => 'HTTP/1.1 403 Forbidden', 'headers' => [], 'body' => '']), 'HTTP status helper');
    $privateHeaders = ['Cache-Control: no-store, private', 'X-Robots-Tag: noindex, nofollow',
        'Referrer-Policy: no-referrer', 'X-Content-Type-Options: nosniff'];
    $same(true, shopHttpPrivateHeaders(['status' => 'HTTP/1.1 200 OK', 'headers' => $privateHeaders, 'body' => '']), 'Private header assertion');
    $same(false, shopHttpPrivateHeaders(['status' => 'HTTP/1.1 200 OK', 'headers' => [], 'body' => '']), 'Missing privacy headers caught');
    $same('', shopHttpPdfText('<html>Error</html>'), 'PDF assertions reject non-PDF responses');
    $pdfStreams = '';
    foreach (["1 beginbfchar\n<0001> <0041>\nendbfchar\n", 'BT <0001> Tj ET'] as $pdfStream) {
        $compressed = gzcompress($pdfStream);
        if (!is_string($compressed)) {
            throw new RuntimeException('Cannot generate bounded PDF assertion fixture');
        }
        $pdfStreams .= '<< /Length ' . strlen($compressed) . " /Filter /FlateDecode >>\nstream\n" . $compressed . "\nendstream\n";
    }
    $same("A\n", shopHttpPdfText("%PDF-1.7\n" . $pdfStreams), 'PDF assertions decode actual CID text rather than search compressed bytes');
    $accessible = '<a class="skip-link" href="#main">Skip</a><main id="main"><form><fieldset><legend>Consent</legend>'
        . '<label for="terms">Terms</label><input id="terms" type="checkbox" name="confirm_terms" aria-describedby="help">'
        . '<label for="digital">Digital</label><input id="digital" type="checkbox" name="confirm_digital">'
        . '<p id="help">Help</p></fieldset></form></main>';
    $same([], shopHttpFormIssues($accessible), 'Accessible confirmation fixture');
    $same(true, in_array(
        'Shop form has a missing or duplicate ARIA target: missing',
        shopHttpFormIssues(str_replace('aria-describedby="help"', 'aria-describedby="missing"', $accessible)),
        true
    ), 'Dangling ARIA target caught');
    $same(true, in_array(
        'Shop confirmation must exist exactly once and remain unchecked: confirm_terms',
        shopHttpFormIssues(str_replace('name="confirm_terms"', 'name="confirm_terms" checked', $accessible)),
        true
    ), 'Recovered consent caught');
    $same(true, in_array(
        'Shop confirmation lacks a label or fieldset legend: confirm_terms',
        shopHttpFormIssues(str_replace('<label for="terms">Terms</label>', '', $accessible)),
        true
    ), 'Unlabelled confirmation caught');
    $same(true, in_array(
        'Shop checkout skip link has no unique target',
        shopHttpFormIssues(str_replace('href="#main"', 'href="#missing"', $accessible)),
        true
    ), 'Invalid skip target caught');
    echo 'Digital shop unit selftest: ' . $checks . " checks passed.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Digital shop unit selftest failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
