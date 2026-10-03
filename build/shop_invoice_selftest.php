<?php

declare(strict_types=1);

// Isolated snapshot fixtures only: deliberately do not load db.php, shop.php or config.php.
require_once __DIR__ . '/../lib/shop_invoice.php';
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function shopInvoiceTest(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
}

function shopInvoiceReject(callable $operation, string $label): void
{
    try {
        $operation();
    } catch (DomainException | LengthException | InvalidArgumentException $e) {
        return;
    }
    throw new RuntimeException('FAIL: expected rejection: ' . $label);
}

/** @param array<string,mixed> $snapshot */
function shopInvoicePdfFallbackTest(array $snapshot, string $unicode, string $label): void
{
    $before = serialize($snapshot);
    $html = shopInvoiceHtml($snapshot);
    $text = shopInvoiceText($snapshot);
    shopInvoiceTest(str_contains($html, htmlspecialchars($unicode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
        && str_contains($text, $unicode), 'Unicode HTML/TXT preserved: ' . $label);
    try {
        shopInvoicePdf($snapshot);
        throw new RuntimeException('FAIL: unsupported shaping must reject PDF: ' . $label);
    } catch (DomainException $exception) {
        shopInvoiceTest(str_contains($exception->getMessage(), 'HTML nebo TXT'), 'explicit DomainException fallback: ' . $label);
    }
    shopInvoiceTest(shopInvoiceHtml($snapshot) === $html && shopInvoiceText($snapshot) === $text
        && serialize($snapshot) === $before, 'failed optional PDF leaves mandatory copies/snapshot unchanged: ' . $label);
}

$destination = dirname(__DIR__) . '/dist/shop-invoice-selftest';
if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
    throw new RuntimeException('Cannot create isolated fixture directory.');
}
$dependencies = dirname(__DIR__) . '/lib/third-party/shop/';
shopInvoiceTest(hash_file('sha256', $dependencies . 'DejaVuSans.ttf') === '7da195a74c55bef988d0d48f9508bd5d849425c1770dba5d7bfc6ce9ed848954', 'unchanged DejaVu 2.37 release font');
shopInvoiceTest(hash_file('sha256', $dependencies . 'qrcodegen.py') === 'd9ac5943cb22fbd8a72e2ad846ecb055a1484ab74d8af8d118071eaeb684affb', 'whitespace-normalized official Nayuki v1.8.0 reference');
foreach (['NOTICE.md','LICENSE-Nayuki.txt','LICENSE-DejaVu.txt'] as $licenseFile) {
    shopInvoiceTest(is_file($dependencies . $licenseFile) && filesize($dependencies . $licenseFile) > 200, 'shipped license ' . $licenseFile);
}
$luminance = static function (string $hex): float {
    $channels = [];
    foreach (str_split($hex, 2) as $byte) {
        $v = hexdec($byte) / 255;
        $channels[] = $v <= 0.04045 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }
    return $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;
};
foreach (['17202a','003b70','59636e'] as $color) {
    shopInvoiceTest(1.05 / ($luminance($color) + 0.05) >= 4.5, 'AA contrast on white ' . $color);
}
$fixture = [
    'kind' => 'proforma', 'invoice_number' => 'PF-2026000001','issued_at' => '2026-10-03 12:34:56',
    'order' => ['order_number' => '2026000001','status' => 'awaiting_payment','customer_name' => 'Žaneta Říhová',
        'email' => 'zaneta@example.invalid','address' => "Příčná 123\nByt číslo 4",'city' => 'České Budějovice','postal_code' => '370 01',
        'country_code' => 'CZ','total_cents' => 36300,'tax_cents' => 6300,'consent_at' => '2026-10-03 12:30:00', 'paid_at' => null],
    'settings' => ['seller_name' => 'Příliš žluťoučký kůň s.r.o.','seller_address' => "Řehořova 42\n130 00 Praha, CZ",'seller_ico' => '12345678',
        'seller_dic' => 'CZ12345678','seller_email' => 'obchod@example.invalid','seller_phone' => '+420 222 123 456',
        'seller_register' => 'Obchodní rejstřík: oddíl C, vložka 12345','vat_mode' => 'vat'],
    'items' => [['title' => 'Elektronická příručka: čeština a přístupnost','quantity' => 3,'unit_price_cents' => 12100,
        'total_cents' => 36300,'tax_rate_bp' => 2100,'tax_cents' => 6300,'file_original_name' => 'Příručka.pdf',
        'product_snapshot' => json_encode(['description' => 'Neměnný popis produktu.','requirements' => 'Čtečka PDF; bez vzdálených fontů.',
            'license_text' => 'Licence pouze pro osobní použití.','update_policy' => 'Aktualizace po dobu jednoho roku.'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]],
    'legal' => ['terms' => "Obchodní podmínky platné při objednávce.\nPráva z vad zůstávají zachována.",
        'privacy' => 'Osobní údaje se zpracovávají pro splnění objednávky.','complaints' => 'Reklamace: napište na obchod@example.invalid.',
        'withdrawal' => 'Souhlas s předčasným dodáním neomezuje práva z vad.','tax_note' => 'Sazba ověřená provozovatelem pro tuto objednávku.',
        'consent' => ['confirm_terms' => 'Souhlasím s podmínkami a elektronickým dokladem.',
            'confirm_digital' => 'Žádám o dodání před uplynutím lhůty a beru na vědomí ztrátu práva na odstoupení až zpřístupněním.']],
    'payment' => ['name' => 'Bankovní převod','iban' => 'CZ6508000000192000145399','account_number' => '19-2000145399/0800'],
];

shopInvoiceTest(shopNormalizeIban('19-2000145399/0800') === 'CZ6508000000192000145399', 'CZ prefix/checksum conversion');
shopInvoiceTest(shopNormalizeIban('2000145399/0800') === 'CZ7908000000002000145399', 'CZ account without prefix');
shopInvoiceTest(shopNormalizeIban(' gb82 west 1234 5698 7654 32 ') === 'GB82WEST12345698765432', 'foreign IBAN whitespace/case');
shopInvoiceTest(shopNormalizeIban('DE89370400440532013000') === 'DE89370400440532013000', 'German IBAN');
foreach (['CZ6608000000192000145399','CZ650800000019200014539','19-2000145398/0800','0/0800','12-34/0000','javascript:alert(1)','GB00WEST12345698765432'] as $bad) {
    shopInvoiceTest(shopNormalizeIban($bad) === '', 'invalid account returns empty validation result ' . $bad);
}
$bad = $fixture;
$bad['payment']['iban'] = 'CZ0008000000192000145399';
shopInvoiceReject(static fn () => shopPaymentSpayd($bad), 'invalid account cannot create payment');
$spd = shopPaymentSpayd($fixture);
shopInvoiceTest($spd === 'SPD*1.0*ACC:CZ6508000000192000145399*AM:363.00*CC:CZK*X-VS:2026000001', 'precise SPAYD');
$bad = $fixture;
$bad['order']['total_cents'] = 999999999;
shopInvoiceTest(str_contains(shopPaymentSpayd($bad), '*AM:9999999.99*'), 'maximum SPAYD amount');
foreach ([['total_cents',1000000000],['total_cents',-1],['total_cents',12.5],['currency','EUR'],['order_number','2026*X-VS:1']] as [$key,$value]) {
    $bad = $fixture;
    $bad['order'][$key] = $value;
    shopInvoiceReject(static fn () => shopPaymentSpayd($bad), 'unsafe SPAYD ' . $key);
}
$bad = $fixture;
$bad['payment']['account_number'] = '2000145399/0800';
shopInvoiceReject(static fn () => shopPaymentSpayd($bad), 'mismatched IBAN/account');
$bad = $fixture;
$bad['items'][0]['tax_cents'] = 1;
shopInvoiceReject(static fn () => shopInvoiceHtml($bad), 'inconsistent totals');
$bad = $fixture;
$bad['items'][0]['product_snapshot'] = '{bad-json';
shopInvoiceReject(static fn () => shopInvoiceText($bad), 'invalid immutable product JSON');
$bad = $fixture;
$bad['legal']['terms'] = "\xC3\x28";
shopInvoiceReject(static fn () => shopInvoiceHtml($bad), 'invalid UTF-8');
$bad = $fixture;
$bad['legal']['terms'] = 'Znak mimo vložené písmo: ' . mb_chr(0x1FAE0, 'UTF-8');
shopInvoiceTest(str_contains(shopInvoiceText($bad), mb_chr(0x1FAE0, 'UTF-8')), 'HTML/text not constrained by PDF font');
shopInvoiceReject(static fn () => shopInvoicePdf($bad), 'unsupported glyph rejects rather than silently corrupting PDF');

$arabic = "\u{0639}\u{0644}\u{064A}";
$font = new ShopInvoiceFont();
foreach (mb_str_split($arabic, 1, 'UTF-8') as $character) {
    shopInvoiceTest($font->character($character)[1] > 0, 'Arabic glyph exists in embedded font before shaping rejection');
}
$unsupportedScripts = [
    'arabic' => $arabic,
    'mixed-arabic-latin' => 'Ali ' . $arabic,
    'arabic-inherited-mark' => "A\u{064B}",
    'arabic-tatweel' => "A\u{0640}",
    'hebrew-bidi' => "\u{05E9}\u{05DC}\u{05D5}\u{05DD}",
    'syriac' => "\u{0710}\u{0712}",
    'devanagari' => "\u{0915}\u{094D}\u{0937}",
    'bengali' => "\u{0995}\u{09CD}\u{09B7}",
    'gurmukhi' => "\u{0A15}\u{0A4D}",
    'gujarati' => "\u{0A95}\u{0ACD}",
    'oriya' => "\u{0B15}\u{0B4D}",
    'tamil' => "\u{0B95}\u{0BCD}",
    'telugu' => "\u{0C15}\u{0C4D}",
    'kannada' => "\u{0C95}\u{0CCD}",
    'malayalam' => "\u{0D15}\u{0D4D}",
    'sinhala' => "\u{0D9A}\u{0DCA}",
    'thai' => "\u{0E01}\u{0E34}",
    'lao' => "\u{0E81}\u{0EB4}",
    'tibetan' => "\u{0F40}\u{0F72}",
    'myanmar' => "\u{1000}\u{1039}",
    'khmer' => "\u{1780}\u{17D2}",
    'joiner' => "A\u{200D}B",
    'non-joiner' => "A\u{200C}B",
    'bidi-override' => "A\u{202E}B\u{202C}",
    'bidi-isolate' => "A\u{2067}B\u{2069}",
];
foreach ($unsupportedScripts as $label => $unicode) {
    $unsupported = $fixture;
    $unsupported['order']['customer_name'] = $unicode;
    shopInvoicePdfFallbackTest($unsupported, $unicode, $label);
    foreach (['html' => shopInvoiceHtml($unsupported), 'txt' => shopInvoiceText($unsupported)] as $extension => $copy) {
        shopInvoiceTest(file_put_contents($destination . '/fallback-' . $label . '.' . $extension, $copy) === strlen($copy), 'Unicode fallback fixture write');
    }
}
foreach (['seller','item','product','legal','invoice-number'] as $location) {
    $unsupported = $fixture;
    if ($location === 'seller') {
        $unsupported['settings']['seller_name'] = $arabic;
    } elseif ($location === 'item') {
        $unsupported['items'][0]['title'] = $arabic;
    } elseif ($location === 'product') {
        $unsupported['items'][0]['product_snapshot'] = json_encode(['license_text' => $arabic], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } elseif ($location === 'legal') {
        $unsupported['legal']['consent'] = $arabic;
    } else {
        $unsupported['invoice_number'] = 'FV-' . $arabic;
    }
    shopInvoicePdfFallbackTest($unsupported, $arabic, 'preflight covers ' . $location);
}
$simpleScripts = $fixture;
$simpleScripts['order']['customer_name'] = 'Žaneta Καλημέρα Мария';
shopInvoiceTest(str_starts_with(shopInvoicePdf($simpleScripts), '%PDF-1.7'), 'Latin/Greek/Cyrillic remain PDF supported');
$cjk = $fixture;
$cjk['order']['customer_name'] = "\u{674E}\u{660E}";
shopInvoicePdfFallbackTest($cjk, $cjk['order']['customer_name'], 'CJK HTML/TXT fallback');
echo 'Complex-script PDF rejection / Unicode HTML+TXT fallback OK: ' . count($unsupportedScripts) . " script/control cases, font-backed Arabic and all document text locations.\n";

shopInvoiceReject(static fn () => shopQrPng(''), 'empty QR');
shopInvoiceReject(static fn () => shopQrPng(str_repeat('A', 7090)), 'oversize QR');

$oversized = $fixture;
$oversized['settings']['seller_register'] = str_repeat('Dlouhý neměnný právní text. ', 25000);
shopInvoicePdfFallbackTest($oversized, $oversized['settings']['seller_register'], 'bounded oversized document');
$fixtures = ['proforma' => $fixture];
$nonVat = $fixture;
$nonVat['kind'] = 'final';
$nonVat['invoice_number'] = 'FV-2026000001';
$nonVat['settings']['vat_mode'] = 'non_vat';
$nonVat['settings']['seller_dic'] = '';
$nonVat['items'][0]['tax_rate_bp'] = $nonVat['items'][0]['tax_cents'] = $nonVat['order']['tax_cents'] = 0;
$nonVat['order']['paid_at'] = '2026-10-03 13:00:00';
$nonVat['order']['status'] = 'paid';
$fixtures['non-vat-paid'] = $nonVat;
$final = $fixture;
$final['kind'] = 'final';
$final['invoice_number'] = 'FV-2026000001';
$final['settings']['vat_mode'] = 'oss';
$final['order']['paid_at'] = '2026-10-03 13:00:00';
$final['order']['fulfilled_at'] = '2026-10-03 13:05:00';
$final['order']['status'] = 'fulfilled';
$final['legal']['consent'] = 'Výslovně souhlasím se zpřístupněním digitálního obsahu před uplynutím lhůty pro odstoupení; práva z vad zůstávají zachovaná.';
$fixtures['oss-paid'] = $final;
$credit = $final;
$credit['kind'] = 'credit';
$credit['invoice_number'] = 'OD-2026000001';
$credit['order']['status'] = 'refunded';
$credit['original_invoice_number'] = 'FV-2026000001';
$credit['credit_reason'] = 'Vrácení ceny za původní digitální plnění.';
$fixtures['credit'] = $credit;
$foreign = $fixture;
$foreign['order']['country_code'] = 'DE';
$fixtures['foreign-pending'] = $foreign;
shopInvoiceTest(shopPaymentSpayd($foreign) === '', 'pending foreign order never payable');
shopInvoiceTest(str_contains(shopInvoiceText($foreign), 'Zatím neplaťte.'), 'pending foreign warning');
$foreign['order']['tax_verified_at'] = '2026-10-03 12:32:00';
shopInvoiceTest(shopPaymentSpayd($foreign) !== '', 'verified foreign order payable');
shopInvoiceTest(shopPaymentSpayd($credit) === '', 'credit never payable');
shopInvoiceTest(str_contains(shopInvoiceText($credit), '-363,00 Kč'), 'negative credit finances');
shopInvoiceTest(str_contains(shopInvoiceText($credit), 'Původní doklad: FV-2026000001'), 'credit original reference');
shopInvoiceTest(str_contains(shopInvoiceText($final), 'UHRAZENO. Neplaťte znovu.'), 'paid warning');
$paidTx = $final;
unset($paidTx['order']['fulfilled_at']);
$paidTx['order']['status'] = 'paid';
$paidTxBefore = serialize($paidTx);
$paidTxText = shopInvoiceText($paidTx);
shopInvoiceTest(str_contains($paidTxText, 'Datum přijetí úplaty: 03. 10. 2026 13:00:00')
    && str_contains($paidTxText, 'Datum plnění: Neuvedeno')
    && !str_contains($paidTxText, 'Datum plnění: 03. 10. 2026 13:00:00'), 'paid transaction receipt is not a delivery/performance timestamp');
shopInvoiceTest(str_contains($paidTxText, 'Čas dodání digitálního obsahu se eviduje samostatně.')
    && str_contains($paidTxText, 'Datum přijetí úplaty nepotvrzuje jeho dodání.'), 'paid invoice explains separately recorded delivery');
shopInvoiceTest(serialize($paidTx) === $paidTxBefore, 'rendering pending delivery does not synthesize fulfillment');
$fixtures['paid-delivery-pending'] = $paidTx;
$delayedDelivery = $paidTx;
$delayedDelivery['order']['fulfilled_at'] = '2026-10-05 13:00:00';
$delayedDelivery['order']['status'] = 'fulfilled';
shopInvoiceTest(str_contains(shopInvoiceText($delayedDelivery), 'Datum plnění: 05. 10. 2026 13:00:00')
    && str_contains(shopInvoiceText($delayedDelivery), 'Datum přijetí úplaty: 03. 10. 2026 13:00:00'), 'two-day SMTP delay keeps actual performance distinct from payment');
shopInvoiceTest(!str_contains(shopInvoiceText($delayedDelivery), 'Datum plnění: Neuvedeno'), 'known fulfillment is the actual performance date');
foreach (['performance_at','performed_at'] as $dateKey) {
    $explicitPerformance = $delayedDelivery;
    $explicitPerformance[$dateKey] = '2026-10-04 14:00:00';
    shopInvoiceTest(str_contains(shopInvoiceText($explicitPerformance), 'Datum plnění: 04. 10. 2026 14:00:00'), 'explicit performance takes precedence: ' . $dateKey);
}
$emptyExplicitPerformance = $delayedDelivery;
$emptyExplicitPerformance['performance_at'] = '';
$emptyExplicitPerformance['performed_at'] = null;
shopInvoiceTest(str_contains(shopInvoiceText($emptyExplicitPerformance), 'Datum plnění: 05. 10. 2026 13:00:00'), 'empty explicit date does not hide known fulfillment');
$orderPerformance = $paidTx;
$orderPerformance['order']['performance_at'] = '2026-10-04 14:00:00';
shopInvoiceTest(str_contains(shopInvoiceText($orderPerformance), 'Datum plnění: 04. 10. 2026 14:00:00'), 'order explicit performance is supported without fulfillment');
shopInvoiceTest(!str_contains(shopInvoiceText($paidTx), 'Faktura (nedaňový doklad)'), 'VAT final is tax invoice');
shopInvoiceTest(str_contains(shopInvoiceText($nonVat), 'Faktura (nedaňový doklad)'), 'non-VAT final is not tax invoice');
$nonVatOssCz = $nonVat;
$nonVatOssCz['settings']['vat_mode'] = 'non_vat_oss';
$nonVatOssCz['settings']['seller_dic'] = $fixture['settings']['seller_dic'];
$nonVatOssSk = $final;
$nonVatOssSk['settings']['vat_mode'] = 'non_vat_oss';
$nonVatOssSk['order']['country_code'] = 'SK';
$nonVatOssSk['order']['address'] = 'Priečna 123';
$nonVatOssSk['order']['city'] = 'Bratislava';
$nonVatOssSk['order']['postal_code'] = '811 01';
$nonVatOssSk['order']['tax_verified_at'] = '2026-10-03 12:32:00';
$nonVatOssSk['items'][0]['unit_price_cents'] = 12300;
$nonVatOssSk['items'][0]['total_cents'] = $nonVatOssSk['order']['total_cents'] = 36900;
$nonVatOssSk['items'][0]['tax_rate_bp'] = 2300;
$nonVatOssSk['items'][0]['tax_cents'] = $nonVatOssSk['order']['tax_cents'] = 6900;
$nonVatOssSk['legal']['tax_note'] .= ' Zahraniční DPH pro SK v režimu OSS; syntetická testovací sazba 23 %.';
foreach (['cz' => $nonVatOssCz,'sk' => $nonVatOssSk] as $country => $countrySnapshot) {
    foreach (['final','credit','proforma'] as $kind) {
        $snapshot = $countrySnapshot;
        $snapshot['kind'] = $kind;
        if ($kind === 'credit') {
            $snapshot['invoice_number'] = 'OD-2026000001';
            $snapshot['original_invoice_number'] = 'FV-2026000001';
            $snapshot['credit_reason'] = $credit['credit_reason'];
            $snapshot['order']['status'] = 'refunded';
        } elseif ($kind === 'proforma') {
            $snapshot['invoice_number'] = $fixture['invoice_number'];
            $snapshot['order']['status'] = 'awaiting_payment';
            $snapshot['order']['paid_at'] = null;
            unset($snapshot['order']['fulfilled_at'], $snapshot['order']['tax_verified_at']);
        }
        $expectedTitle = $kind === 'proforma' ? 'Platební výzva (nedaňový doklad)'
            : ($kind === 'credit' ? ($country === 'cz' ? 'Opravný doklad' : 'Opravný daňový doklad (zahraniční DPH, OSS)')
                : ($country === 'cz' ? 'Faktura (nedaňový doklad)' : 'Faktura - daňový doklad (zahraniční DPH, OSS)'));
        shopInvoiceTest(shopInvoiceDocument($snapshot)['blocks'][0] === ['h1', $expectedTitle], 'non_vat_oss title ' . $country . '/' . $kind);
        $before = serialize($snapshot);
        foreach ([shopInvoiceHtml($snapshot), shopInvoiceText($snapshot)] as $copy) {
            foreach ([$expectedTitle,'Daňový režim: Neplátce DPH v tuzemsku, identifikovaná osoba v OSS','DIČ: CZ12345678'] as $needle) {
                shopInvoiceTest(str_contains($copy, $needle), 'non_vat_oss identity/type ' . $country . '/' . $kind . ': ' . $needle);
            }
            shopInvoiceTest(!str_contains($copy, 'Daňový režim: plátce DPH'), 'identified person is not labeled domestic VAT payer');
            shopInvoiceTest(str_contains($copy, 'Tento doklad není daňovým dokladem.') === ($country === 'cz'), 'no blanket nontax claim for foreign OSS');
            $sign = $kind === 'credit' ? '-' : '';
            shopInvoiceTest(str_contains($copy, 'Daň celkem: ' . ($country === 'cz' ? '0,00' : $sign . '69,00') . ' Kč'), 'snapshot VAT preserved ' . $country . '/' . $kind);
            if ($country === 'sk') {
                shopInvoiceTest(str_contains($copy, 'Plnění pro zemi SK v režimu OSS') && str_contains($copy, 'částky zahraniční DPH'), 'foreign VAT context');
                shopInvoiceTest(str_contains($copy, '23,00 %') && str_contains($copy, 'Celkem bez daně: ' . $sign . '300,00 Kč')
                    && str_contains($copy, 'Celkem včetně daně: ' . $sign . '369,00 Kč'), 'foreign net/rate/gross preserved');
            }
            if ($kind === 'final') {
                shopInvoiceTest(str_contains($copy, 'UHRAZENO. Neplaťte znovu.'), 'non_vat_oss paid final warning');
            } elseif ($kind === 'credit') {
                shopInvoiceTest(str_contains($copy, 'Původní doklad: FV-2026000001'), 'non_vat_oss credit reference');
            } else {
                shopInvoiceTest(str_contains($copy, 'Tato platební výzva není daňovým dokladem.'), 'non_vat_oss proforma never tax invoice');
                shopInvoiceTest(str_contains($copy, 'Zatím neplaťte.') === ($country === 'sk'), 'non_vat_oss pending foreign warning');
            }
        }
        shopInvoiceTest(serialize($snapshot) === $before, 'tax presentation leaves snapshot immutable');
        shopInvoiceTest((shopPaymentSpayd($snapshot) !== '') === ($country === 'cz' && $kind === 'proforma'), 'non_vat_oss payment gate');
        $fixtures['non-vat-oss-' . $country . '-' . $kind] = $snapshot;
    }
    $domesticPayerOss = $countrySnapshot;
    $domesticPayerOss['settings']['vat_mode'] = 'oss';
    shopInvoiceTest(shopInvoiceDocument($domesticPayerOss)['blocks'][0] === ['h1', 'Faktura - daňový doklad']
        && str_contains(shopInvoiceText($domesticPayerOss), 'Daňový režim: plátce DPH, režim OSS'), 'existing domestic-payer OSS contract remains unchanged ' . $country);
}
$lowerCountry = $nonVatOssCz;
$lowerCountry['order']['country_code'] = 'cz';
shopInvoiceTest(shopInvoiceDocument($lowerCountry)['blocks'][0] === ['h1', 'Faktura (nedaňový doklad)'], 'domestic mode classification is case insensitive');
echo "non_vat_oss CZ/SK final, credit and proforma classification / foreign VAT / existing oss regression OK.\n";
$signed = $credit;
foreach (['total_cents','tax_cents'] as $key) {
    $signed['order'][$key] *= -1;
    $signed['items'][0][$key] *= -1;
}
$signed['items'][0]['unit_price_cents'] *= -1;
shopInvoiceTest(str_contains(shopInvoiceText($signed), '-363,00 Kč'), 'already signed credit not inverted');
$cancelled = $fixture;
$cancelled['order']['status'] = 'cancelled';
shopInvoiceTest(shopPaymentSpayd($cancelled) === '', 'cancelled order never payable');

$long = $fixture;
$long['items'] = [];
$long['order']['total_cents'] = $long['order']['tax_cents'] = 0;
for ($i = 1; $i <= 48; $i++) {
    $item = $fixture['items'][0];
    $item['title'] = sprintf('Položka-%03d', $i) . ' Žluťoučká elektronická kniha ' . str_repeat('Řehoř Čížek a příručka přístupnosti. ', $i === 48 ? 48 : 3);
    $long['items'][] = $item;
    $long['order']['total_cents'] += $item['total_cents'];
    $long['order']['tax_cents'] += $item['tax_cents'];
}
$long['legal']['terms'] .= "\n" . str_repeat('Úplná neměnná kopie právních podmínek. ', 140) . "\n" . str_repeat('WŘ', 300) . "\nKONEC-PODMÍNEK";
$fixtures['multipage'] = $long;
$unsafe = $fixture;
$unsafe['items'][0]['title'] = '<script>alert("x")</script> & Žluťoučký';
$unsafe['legal']['terms'] = '<img src="https://invalid.example/pixel"> <iframe src="file:///secret">';
$unsafe['items'][0]['product_snapshot'] = json_encode(['description' => '<svg onload="alert(1)"> immutable', 'license_text' => '<b>Licence & práva</b>'], JSON_THROW_ON_ERROR);
$html = shopInvoiceHtml($unsafe);
shopInvoiceTest(!str_contains($html, '<script>') && !str_contains($html, '<iframe') && !str_contains($html, '<svg'), 'untrusted content is literal escaped text');
shopInvoiceTest(str_contains($html, '&lt;img src='), 'legal copy not parsed as HTML');
shopInvoiceTest(str_contains(shopInvoiceText($unsafe), '<b>Licence & práva</b>'), 'text preserves immutable product content');

$manifest = ['fixtures' => [], 'qr' => []];
foreach ($fixtures as $name => $snapshot) {
    $html = shopInvoiceHtml($snapshot);
    $text = shopInvoiceText($snapshot);
    $pdf = shopInvoicePdf($snapshot);
    shopInvoiceTest(str_starts_with($pdf, '%PDF-1.7'), 'PDF header ' . $name);
    foreach (['/StructTreeRoot','/ParentTree','/MarkInfo','/ToUnicode','/FontFile2','/Lang (cs-CZ)','/S /H1','/S /H2','/S /P'] as $tag) {
        shopInvoiceTest(str_contains($pdf, $tag), 'PDF structural marker ' . $tag);
    }
    shopInvoiceTest(!str_contains($pdf, '/GTS_PDFUA') && !str_contains($pdf, 'pdfuaid'), 'no PDF/UA claim');
    $dom = new DOMDocument();
    shopInvoiceTest($dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING), 'standalone HTML parse');
    $xpath = new DOMXPath($dom);
    shopInvoiceTest($xpath->query('//html[@lang="cs"]')->length === 1 && $xpath->query('//main[@id="invoice"]')->length === 1, 'HTML language and main');
    shopInvoiceTest($xpath->query('//a[@href="#invoice"]')->length === 1 && str_contains($html, 'outline:3px'), 'skip link and visible focus');
    shopInvoiceTest($xpath->query('//table[not(caption) or not(thead/tr/th[@scope="col"])]')->length === 0, 'caption and scoped headers');
    foreach ($xpath->query('//*[@aria-labelledby or @aria-describedby]') as $element) {
        if (!$element instanceof DOMElement) {
            throw new RuntimeException('Expected an HTML element for ARIA checks.');
        }
        foreach (['aria-labelledby','aria-describedby'] as $attribute) {
            foreach (preg_split('/\s+/', trim($element->getAttribute($attribute))) ?: [] as $id) {
                if ($id !== '') {
                    shopInvoiceTest($xpath->query('//*[@id="' . $id . '"]')->length === 1, 'ARIA relationship resolves exactly once');
                }
            }
        }
    }
    shopInvoiceTest($xpath->query('//div[@class="table-wrap" and @tabindex="0" and @role="region"]')->length === count($snapshot['items']), 'scrollable tables keyboard accessible');
    shopInvoiceTest($xpath->query('//script|//link|//iframe|//img[not(starts-with(@src,"data:image/png;base64,"))]')->length === 0, 'no remote or executable content');
    $payable = shopPaymentSpayd($snapshot) !== '';
    shopInvoiceTest($xpath->query('//img[@alt="qr kód k platbě"]')->length === (int) $payable, 'exact QR alt when payable');
    shopInvoiceTest(str_contains($pdf, '/S /Figure') === $payable, 'PDF Figure when payable');
    if ($payable) {
        shopInvoiceTest(str_contains($pdf, '/Alt <FEFF' . strtoupper(bin2hex(mb_convert_encoding('qr kód k platbě', 'UTF-16BE', 'UTF-8'))) . '>'), 'exact PDF Figure alt');
    }
    foreach (['Neměnný popis produktu.','Licence pouze pro osobní použití.','Aktualizace po dobu jednoho roku.',
        'Telefon: +420 222 123 456', 'Datum vystavení:', 'Datum přijetí úplaty:', 'Datum plnění:', 'Daňové vysvětlení', 'Sazba ověřená provozovatelem'] as $needle) {
        shopInvoiceTest(str_contains($html, $needle) && str_contains($text, $needle), 'equivalent immutable content ' . $needle);
    }
    foreach (['html' => $html,'txt' => $text,'pdf' => $pdf,'json' => json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)] as $ext => $bytes) {
        shopInvoiceTest(file_put_contents($destination . '/' . $name . '.' . $ext, $bytes) === strlen($bytes), 'fixture write');
    }
    $manifest['fixtures'][] = ['name' => $name,'payable' => $payable,'items' => count($snapshot['items'])];
}
shopInvoiceTest(shopInvoicePdf($fixture) === shopInvoicePdf($fixture), 'deterministic PDF bytes');
file_put_contents($destination . '/payment.png', shopQrPng($spd));
file_put_contents($destination . '/payment.spayd', $spd);

// Exercise every version/ECC and every mask, plus automatic-mask selection and long byte lengths.
$cases = [];
for ($v = 1; $v <= 40; $v++) {
    for ($ecc = 0; $ecc < 4; $ecc++) {
        $cases[] = ['text' => 'V' . $v . ' E' . $ecc, 'version' => $v,'ecc' => $ecc,'mask' => ($v + $ecc) % 8,'boost' => false];
    }
}
foreach (['','012345678901234567890','SPD*1.0*ACC:CZ6508000000192000145399*AM:0.01*CC:CZK*X-VS:2026000001','Český Unicode, Καλημέρα',str_repeat('a', 256),str_repeat('Ab.', 700)] as $value) {
    $cases[] = ['text' => $value,'version' => null,'ecc' => 1,'mask' => -1,'boost' => true];
}
foreach ([7,32,40] as $v) {
    $cases[] = ['text' => 'AUTO MASK ' . $v,'version' => $v,'ecc' => 2,'mask' => -1,'boost' => false];
}
foreach ([26,127,128,16383,16384,999999] as $eci) {
    $segments = [\Kora\Shop\Qr\Segment::eci($eci), \Kora\Shop\Qr\Segment::bytes('Český Unicode')];
    $cases[] = ['text' => '','segments' => array_map(static fn ($s): array => ['mode' => $s->mode,'count' => $s->count,'bits' => $s->bits], $segments),
        'version' => null,'ecc' => 1,'mask' => -1,'boost' => true];
}
$kanjiBits = [];
\Kora\Shop\Qr\Segment::append($kanjiBits, 0x123, 13);
$segments = array_merge(
    \Kora\Shop\Qr\Segment::text('1234567890'),
    [new \Kora\Shop\Qr\Segment(8, 1, $kanjiBits)],
    [\Kora\Shop\Qr\Segment::bytes("\0\xFF\x80")],
    \Kora\Shop\Qr\Segment::text('HELLO')
);
$cases[] = ['text' => '','segments' => array_map(static fn ($s): array => ['mode' => $s->mode,'count' => $s->count,'bits' => $s->bits], $segments),
    'version' => null,'ecc' => 1,'mask' => -1,'boost' => true];
shopInvoiceReject(static fn () => \Kora\Shop\Qr\Segment::eci(-1), 'negative ECI');
shopInvoiceReject(static fn () => \Kora\Shop\Qr\Segment::eci(1000000), 'excessive ECI');
foreach ($cases as $case) {
    $segments = isset($case['segments']) ? array_map(static fn ($s): \Kora\Shop\Qr\Segment => new \Kora\Shop\Qr\Segment($s['mode'], $s['count'], $s['bits']), $case['segments'])
        : \Kora\Shop\Qr\Segment::text($case['text']);
    $qr = \Kora\Shop\Qr\QrCode::encodeSegments($segments, $case['ecc'], $case['version'] ?? 1, $case['version'] ?? 40, $case['mask'], $case['boost']);
    $matrix = '';
    for ($y = 0; $y < $qr->size; $y++) {
        for ($x = 0; $x < $qr->size; $x++) {
            $matrix .= $qr->getModule($x, $y) ? '1' : '0';
        }
    }
    $case['sha256'] = hash('sha256', $matrix);
    $case['actual_version'] = $qr->version;
    $case['actual_ecc'] = $qr->ecc;
    $case['actual_mask'] = $qr->mask;
    $manifest['qr'][] = $case;
}
file_put_contents($destination . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'Shop invoice PHP selftest OK: ' . count($fixtures) . ' document fixtures, ' . count($cases) . " QR matrices; no database.\n";
$python = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--python=')) {
        $python = substr($argument, 9);
    }
}
if ($python !== null) {
    $command = escapeshellarg($python) . ' ' . escapeshellarg(__DIR__ . '/../lib/third-party/shop/verify.py') . ' ' . escapeshellarg($destination);
    passthru($command, $code);
    exit($code);
}
echo "Optional pypdf/upstream/decode QA: pass --python=/absolute/path/to/python.\n";
