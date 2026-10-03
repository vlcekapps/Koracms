<?php

declare(strict_types=1);

require_once __DIR__ . '/third-party/shop/QrCode.php';

/** Pure snapshot renderers: no database, settings lookup, network or HTML parsing. */
function shopInvoiceString(mixed $value): string
{
    if (!is_scalar($value) && $value !== null) {
        throw new DomainException('Doklad obsahuje neplatný textový údaj.');
    }
    $text = (string) $value;
    if (!mb_check_encoding($text, 'UTF-8')) {
        throw new DomainException('Doklad musí obsahovat platný text UTF-8.');
    }
    $text = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", '    '], $text);
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
}

function shopInvoiceInteger(mixed $value, string $label): int
{
    if (!is_int($value) && !(is_string($value) && preg_match('/\A-?(?:0|[1-9][0-9]*)\z/D', $value))) {
        throw new DomainException('Neplatná celočíselná hodnota: ' . $label);
    }
    $number = filter_var($value, FILTER_VALIDATE_INT);
    // Keep intermediate accounting arithmetic safely within signed 64-bit integers.
    if ($number === false || $number < -999999999999 || $number > 999999999999) {
        throw new DomainException('Částka nebo množství je mimo podporovaný rozsah: ' . $label);
    }
    return $number;
}

function shopInvoiceDecimal(int $value, bool $group = true): string
{
    $digits = (string) intdiv(abs($value), 100);
    if ($group) {
        $digits = preg_replace('/\B(?=(?:[0-9]{3})+(?![0-9]))/', ' ', $digits) ?? $digits;
    }
    return ($value < 0 ? '-' : '') . $digits . ($group ? ',' : '.') . str_pad((string) (abs($value) % 100), 2, '0', STR_PAD_LEFT);
}

function shopInvoiceMoney(int $cents): string
{
    return shopInvoiceDecimal($cents) . ' Kč';
}

function shopInvoiceMod97(string $digits): int
{
    $remainder = 0;
    foreach (str_split($digits) as $char) {
        $part = ctype_digit($char) ? $char : (string) (ord($char) - 55);
        foreach (str_split($part) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }
    }
    return $remainder;
}

/** Validates/converts an account; invalid input returns '' for core/form validators. */
function shopNormalizeIban(string $account): string
{
    $account = strtoupper(preg_replace('/\s+/u', '', $account) ?? '');
    if (preg_match('/\A(?:(\d{1,6})-)?(\d{1,10})\/(\d{4})\z/D', $account, $match)) {
        $prefix = str_pad($match[1], 6, '0', STR_PAD_LEFT);
        $number = str_pad($match[2], 10, '0', STR_PAD_LEFT);
        foreach ([[$prefix,[10,5,8,4,2,1]], [$number,[6,3,7,9,10,5,8,4,2,1]]] as [$part,$weights]) {
            $sum = 0;
            foreach ($weights as $i => $weight) {
                $sum += (int) $part[$i] * $weight;
            }
            if ($sum % 11 !== 0) {
                return '';
            }
        }
        if ((int) $number === 0 || (int) $match[3] === 0) {
            return '';
        }
        $bban = $match[3] . $prefix . $number;
        $account = 'CZ' . str_pad((string) (98 - shopInvoiceMod97($bban . 'CZ00')), 2, '0', STR_PAD_LEFT) . $bban;
    }
    // ISO 13616 national lengths. Unknown countries fail closed, not just mod-97.
    $lengths = ['AD' => 24,'AE' => 23,'AL' => 28,'AT' => 20,'AZ' => 28,'BA' => 20,'BE' => 16,'BG' => 22,'BH' => 22,
        'BI' => 27,'BR' => 29,'BY' => 28,'CH' => 21,'CR' => 22,'CY' => 28,'CZ' => 24,'DE' => 22,'DJ' => 27,'DK' => 18,
        'DO' => 28,'EE' => 20,'EG' => 29,'ES' => 24,'FI' => 18,'FK' => 18,'FO' => 18,'FR' => 27,'GB' => 22,'GE' => 22,
        'GI' => 23,'GL' => 18,'GR' => 27,'GT' => 28,'HR' => 21,'HU' => 28,'IE' => 22,'IL' => 23,'IQ' => 23,'IS' => 26,
        'IT' => 27,'JO' => 30,'KW' => 30,'KZ' => 20,'LB' => 28,'LC' => 32,'LI' => 21,'LT' => 20,'LU' => 20,'LV' => 21,
        'LY' => 25,'MC' => 27,'MD' => 24,'ME' => 22,'MK' => 19,'MN' => 20,'MR' => 27,'MT' => 31,'MU' => 30,'NI' => 28,
        'NL' => 18,'NO' => 15,'OM' => 23,'PK' => 24,'PL' => 28,'PS' => 29,'PT' => 25,'QA' => 29,'RO' => 24,'RS' => 22,
        'RU' => 33,'SA' => 24,'SC' => 31,'SD' => 18,'SE' => 24,'SI' => 19,'SK' => 24,'SM' => 27,'SO' => 23,'ST' => 25,
        'SV' => 28,'TL' => 23,'TN' => 24,'TR' => 26,'UA' => 29,'VA' => 22,'VG' => 24,'XK' => 20];
    $country = substr($account, 0, 2);
    if (!preg_match('/\A[A-Z]{2}[0-9]{2}[A-Z0-9]+\z/D', $account)
        || !isset($lengths[$country]) || strlen($account) !== $lengths[$country]
        || (int) substr($account, 2, 2) < 2 || (int) substr($account, 2, 2) > 98
        || shopInvoiceMod97(substr($account, 4) . substr($account, 0, 4)) !== 1
        || ($country === 'CZ' && !ctype_digit(substr($account, 4)))) {
        return '';
    }
    return $account;
}

/** @param array<string,mixed> $order */
function shopInvoicePaid(array $order): bool
{
    return !empty($order['paid_at']) || in_array($order['status'] ?? '', ['paid','fulfilled','refunded'], true);
}

/** @param array<string,mixed> $order */
function shopInvoiceTaxPending(array $order): bool
{
    return strtoupper(shopInvoiceString($order['country_code'] ?? 'CZ')) !== 'CZ' && empty($order['tax_verified_at']);
}

/**
 * Empty means no payable instruction (paid/credit/cancelled/zero); malformed payment throws.
 * @param array<string,mixed> $snapshot
 */
function shopPaymentSpayd(array $snapshot): string
{
    $order = $snapshot['order'] ?? [];
    $kind = $snapshot['kind'] ?? 'proforma';
    if (!in_array($kind, ['proforma','final','credit'], true)) {
        throw new DomainException('Neplatný druh dokladu.');
    }
    $amount = shopInvoiceInteger($order['total_cents'] ?? null, 'total_cents');
    if ($kind === 'credit' || shopInvoicePaid($order) || shopInvoiceTaxPending($order)
        || in_array($order['status'] ?? '', ['cancelled','refunded'], true) || $amount === 0) {
        return '';
    }
    if ($amount < 0 || $amount > 999999999 || ($order['currency'] ?? 'CZK') !== 'CZK') {
        throw new DomainException('QR platba vyžaduje kladnou částku do 9 999 999,99 Kč a měnu CZK.');
    }
    $vs = shopInvoiceString($order['order_number'] ?? '');
    if (!preg_match('/\A[0-9]{10}\z/D', $vs)) {
        throw new DomainException('Variabilní symbol musí být desetimístné číslo objednávky.');
    }
    $payment = $snapshot['payment'] ?? [];
    $account = shopInvoiceString(!empty($payment['iban']) ? $payment['iban'] : ($payment['account_number'] ?? ''));
    $iban = shopNormalizeIban($account);
    if ($iban === '') {
        throw new DomainException('QR platba vyžaduje platný IBAN nebo české číslo účtu.');
    }
    if (!empty($payment['iban']) && !empty($payment['account_number']) && str_contains((string) $payment['account_number'], '/')) {
        if (shopNormalizeIban((string) $payment['account_number']) !== $iban) {
            throw new DomainException('IBAN neodpovídá českému číslu účtu ve snapshotu.');
        }
    }
    return 'SPD*1.0*ACC:' . $iban . '*AM:' . shopInvoiceDecimal($amount, false) . '*CC:CZK*X-VS:' . $vs;
}

/** PNG generated directly from QR modules, six pixels/module and four-module quiet zone. */
function shopQrPng(string $spd): string
{
    if ($spd === '' || strlen($spd) > 7089) {
        throw new DomainException('QR kód vyžaduje neprázdný řetězec v podporovaném rozsahu.');
    }
    $qr = \Kora\Shop\Qr\QrCode::encodeText($spd);
    $scale = 6;
    $width = ($qr->size + 8) * $scale;
    $pixels = '';
    for ($y = -4; $y < $qr->size + 4; $y++) {
        $row = "\0";
        for ($x = -4; $x < $qr->size + 4; $x++) {
            $row .= str_repeat($qr->getModule($x, $y) ? "\0" : "\xFF", $scale);
        }
        $pixels .= str_repeat($row, $scale);
    }
    $chunk = static function (string $type, string $bytes): string {
        return pack('N', strlen($bytes)) . $type . $bytes . pack('N', crc32($type . $bytes));
    };
    return "\x89PNG\r\n\x1A\n" . $chunk('IHDR', pack('NNCCCCC', $width, $width, 8, 0, 0, 0, 0))
        . $chunk('IDAT', gzcompress($pixels, 9)) . $chunk('IEND', '');
}

function shopInvoiceDate(mixed $value): string
{
    $text = shopInvoiceString($value);
    if ($text === '') {
        return 'Neuvedeno';
    }
    if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})(?:[ T].*)?\z/u', $text, $m)
        && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return $m[3] . '. ' . $m[2] . '. ' . $m[1] . (strlen($text) > 10 ? ' ' . substr($text, 11) : '');
    }
    throw new DomainException('Neplatné datum dokladu.');
}

/**
 * One common semantic content model keeps PDF, HTML and text data equivalent.
 * @param array<string,mixed> $snapshot
 * @return array{title:string,blocks:list<array{string,string}>,items:list<list<string>>,spayd:string}
 */
function shopInvoiceDocument(array $snapshot): array
{
    foreach (['order','items','settings','legal','payment'] as $key) {
        if (!isset($snapshot[$key]) || !is_array($snapshot[$key])) {
            throw new DomainException('Doklad neobsahuje snapshot: ' . $key);
        }
    }
    $order = $snapshot['order'];
    $settings = $snapshot['settings'];
    $kind = shopInvoiceString($snapshot['kind'] ?? '');
    $mode = shopInvoiceString($settings['vat_mode'] ?? '');
    if (!in_array($kind, ['proforma','final','credit'], true) || !in_array($mode, ['non_vat','vat','oss','non_vat_oss'], true)) {
        throw new DomainException('Neplatný druh dokladu nebo daňový režim.');
    }
    if (($order['currency'] ?? 'CZK') !== 'CZK') {
        throw new DomainException('Doklad podporuje pouze měnu CZK.');
    }
    $country = strtoupper(shopInvoiceString($order['country_code'] ?? 'CZ'));
    // OSS registration alone does not make an identified person a domestic VAT payer.
    $nonTax = $mode === 'non_vat' || ($mode === 'non_vat_oss' && $country === 'CZ');
    $title = $kind === 'proforma' ? 'Platební výzva (nedaňový doklad)'
        : ($kind === 'credit' ? ($nonTax ? 'Opravný doklad' : 'Opravný daňový doklad')
            : ($nonTax ? 'Faktura (nedaňový doklad)' : 'Faktura - daňový doklad'));
    if ($mode === 'non_vat_oss' && !$nonTax && $kind !== 'proforma') {
        $title .= ' (zahraniční DPH, OSS)';
    }
    $number = shopInvoiceString($snapshot['invoice_number'] ?? '');
    if ($number === '' || $snapshot['items'] === []) {
        throw new DomainException('Chybí číslo dokladu nebo jeho položky.');
    }
    $blocks = [['h1', $title], ['p', 'Číslo dokladu: ' . $number], ['p', 'Číslo objednávky: ' . shopInvoiceString($order['order_number'] ?? '')]];
    $add = static function (string $type, string $text) use (&$blocks): void {
        $blocks[] = [$type, $text];
    };
    if ($kind === 'proforma') {
        $add('p', 'Tato platební výzva není daňovým dokladem.');
    }
    if ($kind === 'credit') {
        $original = $snapshot['original_invoice_number'] ?? $snapshot['original_document'] ?? $snapshot['original_invoice'] ?? $snapshot['original']
            ?? ('FV-' . shopInvoiceString($order['order_number'] ?? ''));
        if (is_array($original)) {
            $original = $original['invoice_number'] ?? '';
        }
        $original = shopInvoiceString($original);
        if ($original === '') {
            throw new DomainException('Opravný doklad vyžaduje číslo původního dokladu.');
        }
        $add('p', 'Původní doklad: ' . $original);
        $add('p', 'Oprava původního plnění; nejde o výzvu k další platbě.');
        if (!empty($snapshot['credit_reason'])) {
            $add('p', 'Důvod opravy: ' . shopInvoiceString($snapshot['credit_reason']));
        }
    }
    $add('h2', 'Dodavatel');
    foreach (['seller_name' => 'Jméno / název','seller_address' => 'Adresa','seller_ico' => 'IČO','seller_dic' => 'DIČ',
        'seller_email' => 'E-mail','seller_phone' => 'Telefon','seller_register' => 'Zápis v rejstříku'] as $key => $label) {
        $value = shopInvoiceString($settings[$key] ?? '');
        if ($value !== '') {
            $add('p', $label . ': ' . $value);
        }
    }
    $add('p', 'Daňový režim: ' . ['non_vat' => 'neplátce DPH','vat' => 'plátce DPH','oss' => 'plátce DPH, režim OSS',
        'non_vat_oss' => 'Neplátce DPH v tuzemsku, identifikovaná osoba v OSS'][$mode]);
    if ($mode === 'non_vat') {
        $add('p', 'Dodavatel není plátcem DPH. Tento doklad není daňovým dokladem.');
    } elseif ($mode === 'non_vat_oss') {
        if ($nonTax) {
            $add('p', 'Dodavatel není plátcem DPH v tuzemsku. Tento doklad není daňovým dokladem.');
        } else {
            $add('p', 'Dodavatel není plátcem DPH v tuzemsku. Plnění pro zemi ' . $country
                . ' v režimu OSS; částky zahraniční DPH jsou uvedeny podle uložené objednávky.');
        }
    }
    $add('h2', 'Odběratel');
    foreach (['customer_name' => 'Jméno / název','address' => 'Adresa','city' => 'Město','postal_code' => 'PSČ',
        'country_code' => 'Země','email' => 'E-mail','customer_ico' => 'IČO','customer_dic' => 'DIČ'] as $key => $label) {
        $value = shopInvoiceString($order[$key] ?? '');
        if ($value !== '') {
            $add('p', $label . ': ' . $value);
        }
    }
    $add('h2', 'Data dokladu');
    $add('p', 'Datum vystavení: ' . shopInvoiceDate($snapshot['issued_at'] ?? ''));
    $add('p', 'Datum přijetí úplaty: ' . shopInvoiceDate($order['paid_at'] ?? ''));
    $performance = '';
    foreach ([$snapshot['performance_at'] ?? null, $snapshot['performed_at'] ?? null,
        $order['performance_at'] ?? null, $order['fulfilled_at'] ?? null] as $performanceValue) {
        $performance = shopInvoiceString($performanceValue);
        if ($performance !== '') {
            break;
        }
    }
    $add('p', 'Datum plnění: ' . shopInvoiceDate($performance));
    if ($performance === '') {
        $add('p', 'Čas dodání digitálního obsahu se eviduje samostatně. Datum přijetí úplaty nepotvrzuje jeho dodání.');
    }
    if (!empty($snapshot['due_at'])) {
        $add('p', 'Datum splatnosti: ' . shopInvoiceDate($snapshot['due_at']));
    }
    $add('h2', 'Položky');
    $items = [];
    $sum = $taxSum = 0;
    $taxGroups = [];
    // Credits accept either original positive amounts or already signed amounts.
    $orderTotal = shopInvoiceInteger($order['total_cents'] ?? null, 'total_cents');
    $sign = $kind === 'credit' && $orderTotal > 0 ? -1 : 1;
    foreach ($snapshot['items'] as $index => $item) {
        if (!is_array($item)) {
            throw new DomainException('Neplatná položka dokladu.');
        }
        $quantity = shopInvoiceInteger($item['quantity'] ?? null, 'quantity');
        $unit = shopInvoiceInteger($item['unit_price_cents'] ?? null, 'unit_price_cents');
        $gross = shopInvoiceInteger($item['total_cents'] ?? null, 'item total_cents');
        $tax = shopInvoiceInteger($item['tax_cents'] ?? null, 'item tax_cents');
        $rate = shopInvoiceInteger($item['tax_rate_bp'] ?? null, 'tax_rate_bp');
        if ($quantity < 1 || $quantity > 1000000 || $rate < 0 || $rate > 10000 || abs($tax) > abs($gross)
            || ($gross > 0 && ($tax < 0 || $unit < 0)) || ($gross < 0 && ($tax > 0 || $unit > 0))
            || ($kind !== 'credit' && ($gross < 0 || $tax < 0 || $unit < 0))
            || ($rate === 0 && $tax !== 0) || $quantity * $unit !== $gross) {
            throw new DomainException('Nekonzistentní částky nebo množství položky dokladu.');
        }
        $sum += $gross;
        $taxSum += $tax;
        $unitTax = intdiv(abs($unit) * $rate + intdiv(10000 + $rate, 2), 10000 + $rate) * ($unit < 0 ? -1 : 1);
        $name = shopInvoiceString($item['title'] ?? '');
        if ($name === '') {
            throw new DomainException('Chybí název položky dokladu.');
        }
        $row = [$name, (string) $quantity, shopInvoiceMoney($sign * $unit), shopInvoiceMoney($sign * ($unit - $unitTax)),
            shopInvoiceDecimal($rate) . ' %', shopInvoiceMoney($sign * ($gross - $tax)), shopInvoiceMoney($sign * $tax), shopInvoiceMoney($sign * $gross)];
        $items[] = $row;
        $details = [];
        $productCopy = $item['product_snapshot'] ?? [];
        if (is_string($productCopy)) {
            try {
                $productCopy = json_decode($productCopy, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new DomainException('Neplatná neměnná kopie produktu.', 0, $e);
            }
        }
        if (!is_array($productCopy)) {
            throw new DomainException('Neplatná neměnná kopie produktu.');
        }
        foreach (['description' => 'Popis','requirements' => 'Požadavky','license_text' => 'Licence','update_policy' => 'Aktualizace',
            'file_original_name' => 'Soubor','tax_note' => 'Daňové vysvětlení'] as $key => $label) {
            $value = $productCopy[$key] ?? $item[$key] ?? '';
            if (shopInvoiceString($value) !== '') {
                $details[] = $label . ': ' . shopInvoiceString($value);
            }
        }
        $add('item', (string) count($items));
        foreach ($details as $detail) {
            $add('p', $detail);
        }
        $taxGroups[$rate]['net'] = ($taxGroups[$rate]['net'] ?? 0) + $sign * ($gross - $tax);
        $taxGroups[$rate]['tax'] = ($taxGroups[$rate]['tax'] ?? 0) + $sign * $tax;
    }
    $orderTax = shopInvoiceInteger($order['tax_cents'] ?? null, 'tax_cents');
    if ($sum !== $orderTotal || $taxSum !== $orderTax) {
        throw new DomainException('Součty položek neodpovídají uložené objednávce.');
    }
    $add('h2', 'Souhrn');
    ksort($taxGroups);
    foreach ($taxGroups as $rate => $group) {
        $add('p', 'Sazba ' . shopInvoiceDecimal((int) $rate) . ' %: základ ' . shopInvoiceMoney($group['net']) . '; daň ' . shopInvoiceMoney($group['tax']));
    }
    $add('p', 'Celkem bez daně: ' . shopInvoiceMoney($sign * ($sum - $taxSum)));
    $add('p', 'Daň celkem: ' . shopInvoiceMoney($sign * $taxSum));
    $add('p', 'Celkem včetně daně: ' . shopInvoiceMoney($sign * $sum));
    if (!empty($snapshot['tax_note'])) {
        $add('p', 'Daňové vysvětlení: ' . shopInvoiceString($snapshot['tax_note']));
    }
    $add('h2', 'Platba');
    if (shopInvoicePaid($order)) {
        $add('p', 'UHRAZENO. Neplaťte znovu.');
    } elseif (shopInvoiceTaxPending($order)) {
        $add('p', 'Zahraniční objednávka čeká na ověření daňového režimu. Zatím neplaťte.');
    } elseif (($order['status'] ?? '') === 'cancelled') {
        $add('p', 'Objednávka byla zrušena. Neplaťte.');
    } elseif ($sum === 0) {
        $add('p', 'K úhradě: 0,00 Kč. Platbu neprovádějte.');
    } elseif ($kind !== 'credit') {
        $add('p', 'K úhradě: ' . shopInvoiceMoney($sum));
    }
    if ($kind === 'credit' || ($order['status'] ?? '') === 'refunded') {
        $add('p', 'Vrácení platby je evidováno provozovatelem; doklad sám bankovní převod neprovádí.');
    }
    $payment = $snapshot['payment'];
    foreach (['name' => 'Způsob platby','account_number' => 'Bankovní účet'] as $key => $label) {
        if (!empty($payment[$key])) {
            $add('p', $label . ': ' . shopInvoiceString($payment[$key]));
        }
    }
    $account = !empty($payment['iban']) ? $payment['iban'] : ($payment['account_number'] ?? '');
    if ($account !== '') {
        $iban = shopNormalizeIban(shopInvoiceString($account));
        if ($iban === '') {
            throw new DomainException('Snapshot dokladu obsahuje neplatný bankovní účet.');
        }
        $add('p', 'IBAN: ' . $iban);
    }
    $add('p', 'Měna: CZK');
    $add('p', 'Variabilní symbol: ' . shopInvoiceString($order['order_number'] ?? ''));
    $spd = shopPaymentSpayd($snapshot);
    if ($spd !== '') {
        $add('qr', $spd);
    }
    $add('h2', 'Trvalá kopie právních textů a souhlasů');
    $add('p', 'Následující texty jsou neměnnou kopií uloženou s objednávkou, nikoli aktuálními podmínkami webu.');
    $labels = ['terms' => 'Obchodní podmínky','privacy' => 'Ochrana osobních údajů','complaints' => 'Reklamace',
        'withdrawal' => 'Odstoupení od smlouvy','tax_note' => 'Daňové vysvětlení','consent' => 'Souhlas','confirm_terms' => 'Souhlas s podmínkami a elektronickým dokladem',
        'confirm_digital' => 'Souhlas s předčasným dodáním digitálního obsahu','consent_at' => 'Datum souhlasu'];
    $appendCopy = static function (array $copy, string $prefix = '') use (&$appendCopy, $labels, $add): void {
        foreach ($copy as $key => $value) {
            $label = $prefix . ($labels[$key] ?? (string) $key);
            if (is_array($value)) {
                $appendCopy($value, $label . ' / ');
            } else {
                $add('h3', $label);
                $add('p', is_bool($value) ? ($value ? 'Ano (uložený záznam)' : 'Ne (uložený záznam)') : shopInvoiceString($value));
            }
        }
    };
    $appendCopy($snapshot['legal']);
    if (isset($snapshot['consent']) && is_array($snapshot['consent'])) {
        $appendCopy($snapshot['consent'], 'Souhlas / ');
    }
    $add('p', 'Datum zaznamenání souhlasů: ' . shopInvoiceDate($order['consent_at'] ?? ''));
    return ['title' => $title . ' ' . $number, 'blocks' => $blocks, 'items' => $items, 'spayd' => $spd];
}

/**
 * @param list<string> $row
 * @return list<array{string,string}>
 */
function shopInvoiceItemText(array $row, int $index): array
{
    return [['h3', 'Položka ' . $index . ': ' . $row[0]],
        ['p', 'Množství: ' . $row[1] . '; jednotková cena včetně daně: ' . $row[2]],
        ['p', 'Jednotková cena bez daně: ' . $row[3] . '; sazba daně: ' . $row[4]],
        ['p', 'Základ: ' . $row[5] . '; daň: ' . $row[6] . '; celkem: ' . $row[7]]];
}

/** @param array<string,mixed> $snapshot */
function shopInvoiceText(array $snapshot): string
{
    $doc = shopInvoiceDocument($snapshot);
    $lines = [];
    foreach ($doc['blocks'] as [$type,$text]) {
        if ($type === 'item') {
            foreach (shopInvoiceItemText($doc['items'][(int) $text - 1], (int) $text) as $block) {
                $lines[] = $block[1];
            }
        } elseif ($type === 'qr') {
            $lines[] = 'QR platba (SPAYD): ' . $text;
        } else {
            $lines[] = $text;
        }
        $lines[] = '';
    }
    return implode("\n", $lines) . "\n";
}

/** @param array<string,mixed> $snapshot */
function shopInvoiceHtml(array $snapshot): string
{
    $doc = shopInvoiceDocument($snapshot);
    $escape = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="referrer" content="no-referrer"><meta name="robots" content="noindex,nofollow"><title>' . $escape($doc['title']) . '</title>'
        . '<style>body{font-family:Georgia,serif;color:#17202a;background:#fff;line-height:1.6;margin:0;padding:1rem}'
        . 'main{max-width:70rem;margin:auto}h1,h2,h3{line-height:1.3;overflow-wrap:anywhere}'
        . 'p,td,th{overflow-wrap:anywhere}p{white-space:pre-wrap}a{color:#003b70}a:focus,main:focus,.table-wrap:focus{outline:3px solid #003b70;outline-offset:4px}'
        . '.skip{display:inline-block;padding:.5rem;margin-bottom:1rem}.table-wrap{overflow-x:auto}table{border-collapse:collapse;width:100%;font-size:1rem}'
        . 'caption{text-align:left;font-weight:bold;padding:.5rem}th,td{border:1px solid #59636e;padding:.5rem;text-align:left;vertical-align:top}'
        . 'img{display:block;max-width:100%;height:auto}figure{margin:1rem 0}figcaption{overflow-wrap:anywhere}'
        . '@media print{.skip{display:none}body{padding:0}h2,h3{break-after:avoid}tr{break-inside:avoid}}</style></head><body>'
        . '<a class="skip" href="#invoice">Přeskočit na obsah dokladu</a><main id="invoice" tabindex="-1">';
    foreach ($doc['blocks'] as [$type,$text]) {
        if ($type === 'item') {
            $i = (int) $text;
            $row = $doc['items'][$i - 1];
            $html .= '<h3>Položka ' . $i . ': ' . $escape($row[0]) . '</h3><div class="table-wrap" tabindex="0" role="region" aria-labelledby="item-prices-' . $i
                . '"><table><caption id="item-prices-' . $i . '">Ceny a daň položky ' . $i . '</caption><thead><tr>';
            foreach (['Množství','Cena za jednotku včetně daně','Cena za jednotku bez daně','Sazba daně','Základ','Daň','Celkem'] as $label) {
                $html .= '<th scope="col">' . $label . '</th>';
            }
            $html .= '</tr></thead><tbody><tr>';
            foreach (array_slice($row, 1) as $cell) {
                $html .= '<td>' . $escape($cell) . '</td>';
            }
            $html .= '</tr></tbody></table></div>';
        } elseif ($type === 'qr') {
            $html .= '<figure><img src="data:image/png;base64,' . base64_encode(shopQrPng($text))
                . '" alt="qr kód k platbě" width="294" height="294"><figcaption>QR platba. Účet, částka, měna a variabilní symbol jsou také uvedeny výše v textu.</figcaption></figure>';
        } else {
            $html .= '<' . $type . '>' . $escape($text) . '</' . $type . '>';
        }
    }
    return $html . '</main></body></html>';
}

/** Static trusted TrueType only. Unicode CIDs are mapped to actual glyphs, not transliterated. */
final class ShopInvoiceFont
{
    public string $bytes;
    public int $units;
    /** @var list<int> */
    public array $bbox;
    public int $ascent;
    public int $descent;
    /** @var array<string,int> */
    private array $tables = [];
    /** @var list<int> */
    private array $widths = [];
    /** @var list<array{int,int,int}> */
    private array $groups = [];
    private int $cmap4 = 0;
    /** @var array<int,array{int,int,int,string}> */
    private array $characters = [];

    public function __construct()
    {
        $bytes = file_get_contents(__DIR__ . '/third-party/shop/DejaVuSans.ttf');
        if ($bytes === false) {
            throw new RuntimeException('Chybí vložené písmo DejaVu Sans.');
        }
        $this->bytes = $bytes;
        for ($i = 0; $i < $this->u16(4); $i++) {
            $offset = 12 + $i * 16;
            $this->tables[substr($bytes, $offset, 4)] = $this->u32($offset + 8);
        }
        $head = $this->tables['head'];
        $this->units = $this->u16($head + 18);
        $this->bbox = [];
        for ($i = 0; $i < 4; $i++) {
            $this->bbox[] = $this->scale($this->s16($head + 36 + $i * 2));
        }
        $hhea = $this->tables['hhea'];
        $this->ascent = $this->scale($this->s16($hhea + 4));
        $this->descent = $this->scale($this->s16($hhea + 6));
        $metrics = $this->u16($hhea + 34);
        for ($i = 0; $i < $metrics; $i++) {
            $this->widths[] = $this->scale($this->u16($this->tables['hmtx'] + $i * 4));
        }
        $cmap = $this->tables['cmap'];
        for ($i = 0; $i < $this->u16($cmap + 2); $i++) {
            $offset = $cmap + $this->u32($cmap + 4 + $i * 8 + 4);
            $format = $this->u16($offset);
            if ($format === 12) {
                for ($j = 0; $j < $this->u32($offset + 12); $j++) {
                    $p = $offset + 16 + $j * 12;
                    $this->groups[] = [$this->u32($p),$this->u32($p + 4),$this->u32($p + 8)];
                }
                break;
            }
            if ($format === 4) {
                $this->cmap4 = $offset;
            }
        }
    }

    private function u16(int $offset): int
    {
        return unpack('n', substr($this->bytes, $offset, 2))[1];
    }
    private function u32(int $offset): int
    {
        return unpack('N', substr($this->bytes, $offset, 4))[1];
    }
    private function s16(int $offset): int
    {
        $n = $this->u16($offset);
        return $n > 32767 ? $n - 65536 : $n;
    }
    private function scale(int $value): int
    {
        return (int) round($value * 1000 / $this->units);
    }

    private function glyph(int $code): int
    {
        foreach ($this->groups as [$start,$end,$glyph]) {
            if ($code >= $start && $code <= $end) {
                return $glyph + $code - $start;
            }
        }
        if ($this->groups === [] && $code <= 65535 && $this->cmap4 > 0) {
            $p = $this->cmap4;
            $n = intdiv($this->u16($p + 6), 2);
            for ($i = 0; $i < $n; $i++) {
                if ($code < $this->u16($p + 16 + $n * 2 + $i * 2) || $code > $this->u16($p + 14 + $i * 2)) {
                    continue;
                }
                $delta = $this->s16($p + 16 + $n * 4 + $i * 2);
                $rangePos = $p + 16 + $n * 6 + $i * 2;
                $range = $this->u16($rangePos);
                if ($range === 0) {
                    return ($code + $delta) & 65535;
                }
                $g = $this->u16($rangePos + $range + ($code - $this->u16($p + 16 + $n * 2 + $i * 2)) * 2);
                return $g === 0 ? 0 : ($g + $delta) & 65535;
            }
        }
        return 0;
    }

    /** @return array{int,int,int,string} */
    public function character(string $char): array
    {
        $code = mb_ord($char, 'UTF-8');
        if (!isset($this->characters[$code])) {
            $glyph = $this->glyph($code);
            if ($glyph === 0) {
                throw new DomainException(sprintf('Písmo PDF nepodporuje znak U+%04X. Použijte HTML/text doklad.', $code));
            }
            if (count($this->characters) >= 65534) {
                throw new DomainException('Doklad obsahuje příliš mnoho různých znaků.');
            }
            $this->characters[$code] = [count($this->characters) + 1, $glyph, $this->widths[min($glyph, count($this->widths) - 1)], $char];
        }
        return $this->characters[$code];
    }

    public function width(string $text, float $size): float
    {
        $width = 0;
        foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
            $width += $this->character($char)[2];
        }
        return $width * $size / 1000;
    }

    public function encode(string $text): string
    {
        $bytes = '';
        foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
            $bytes .= pack('n', $this->character($char)[0]);
        }
        return strtoupper(bin2hex($bytes));
    }

    /**
     * Wrap by real glyph advances and grapheme clusters, including long unbroken strings.
     * @return list<string>
     */
    public function wrap(string $text, float $size, float $max): array
    {
        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }
            preg_match_all('/\X/u', $paragraph, $matches);
            $line = '';
            $width = 0.0;
            $lastSpace = -1;
            $clusters = [];
            foreach ($matches[0] as $cluster) {
                $clusterWidth = $this->width($cluster, $size);
                if ($clusterWidth > $max) {
                    throw new DomainException('Jeden textový znak přesahuje šířku stránky PDF.');
                }
                while ($width + $clusterWidth > $max && $line !== '') {
                    if ($lastSpace >= 0) {
                        $lines[] = implode('', array_slice($clusters, 0, $lastSpace + 1));
                        $clusters = array_slice($clusters, $lastSpace + 1);
                        $line = implode('', $clusters);
                        $width = $this->width($line, $size);
                    } else {
                        $lines[] = $line;
                        $clusters = [];
                        $line = '';
                        $width = 0.0;
                    }
                    $lastSpace = -1;
                    foreach ($clusters as $i => $c) {
                        if ($c === ' ') {
                            $lastSpace = $i;
                        }
                    }
                }
                $clusters[] = $cluster;
                $line .= $cluster;
                $width += $clusterWidth;
                if ($cluster === ' ') {
                    $lastSpace = count($clusters) - 1;
                }
            }
            $lines[] = $line;
        }
        return $lines;
    }

    /** @return array{string,string,string} */
    public function maps(): array
    {
        $cidMap = "\0\0";
        $widths = [];
        $mappings = [];
        foreach ($this->characters as [$cid,$glyph,$width,$char]) {
            $cidMap .= pack('n', $glyph);
            $widths[] = $width;
            $mappings[] = sprintf('<%04X> <%s>', $cid, strtoupper(bin2hex(mb_convert_encoding($char, 'UTF-16BE', 'UTF-8'))));
        }
        $cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /ShopUnicode def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n";
        foreach (array_chunk($mappings, 100) as $chunk) {
            $cmap .= count($chunk) . " beginbfchar\n" . implode("\n", $chunk) . "\nendbfchar\n";
        }
        return [$cidMap, '1 [' . implode(' ', $widths) . ']', $cmap . "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend"];
    }
}

/** Small fixed-layout tagged PDF writer; it intentionally does not claim PDF/UA. */
final class ShopInvoicePdfWriter
{
    /** @var list<string> */
    private array $objects = [''];
    private ShopInvoiceFont $font;
    /** @var list<array{id:int,stream:string,parents:list<int>}> */
    private array $pages = [];
    /** @var list<array{id:int,type:string,alt:string,kids:list<array{int,int}>}> */
    private array $structure = [];
    private int $page = -1;
    private float $y = 0.0;

    private function reserve(): int
    {
        $this->objects[] = '';
        return count($this->objects) - 1;
    }
    private function object(string $value): int
    {
        $id = $this->reserve();
        $this->objects[$id] = $value;
        return $id;
    }
    private static function ref(int $id): string
    {
        return $id . ' 0 R';
    }
    private static function unicode(string $text): string
    {
        return '<FEFF' . strtoupper(bin2hex(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8'))) . '>';
    }
    private static function stream(string $bytes, string $extra = ''): string
    {
        $compressed = gzcompress($bytes, 9);
        return '<< /Length ' . strlen($compressed) . ' /Filter /FlateDecode ' . $extra . ">>\nstream\n" . $compressed . "\nendstream";
    }

    private static function requireSupportedText(string $text): void
    {
        // Glyph coverage is not shaping/bidi support. Fail closed for unverified scripts
        // and format controls; Arabic inherited marks/tatweel also need shaping context.
        if (preg_match('/[^\p{Latin}\p{Greek}\p{Cyrillic}\p{Common}\p{Inherited}]|\p{Cf}|[\x{0600}-\x{06FF}]/u', $text, $match) === 1) {
            throw new DomainException(sprintf(
                'PDF nepodporuje bezpečné vykreslení tohoto písma nebo směru textu (U+%04X). Použijte HTML nebo TXT doklad.',
                mb_ord($match[0], 'UTF-8')
            ));
        }
    }

    private function newPage(): void
    {
        if (count($this->pages) >= 250) {
            throw new DomainException('Doklad přesahuje bezpečný počet stran PDF. Použijte úplný HTML nebo TXT doklad.');
        }
        $this->page++;
        $this->pages[] = ['id' => $this->reserve(), 'stream' => '', 'parents' => []];
        $this->y = 788.0;
    }

    private function tag(string $type, string $text = ''): int
    {
        $id = $this->reserve();
        $this->structure[] = ['id' => $id,'type' => $type,'alt' => $text,'kids' => []];
        return count($this->structure) - 1;
    }

    private function marked(int $tag, string $operations): void
    {
        $mcid = count($this->pages[$this->page]['parents']);
        $this->pages[$this->page]['parents'][] = $this->structure[$tag]['id'];
        $this->structure[$tag]['kids'][] = [$this->pages[$this->page]['id'], $mcid];
        $this->pages[$this->page]['stream'] .= '/' . $this->structure[$tag]['type'] . ' <</MCID ' . $mcid . ">> BDC\n" . $operations . "\nEMC\n";
    }

    private function paragraph(string $type, string $text): void
    {
        $size = ['h1' => 19.0,'h2' => 14.0,'h3' => 11.5,'p' => 10.0][$type];
        $leading = $size * 1.5;
        $space = $type === 'p' ? 5.0 : 11.0;
        $lines = $this->font->wrap($text, $size, 487.0);
        $tag = $this->tag(['h1' => 'H1','h2' => 'H2','h3' => 'H3','p' => 'P'][$type]);
        // Keep a heading with at least two following body lines when possible.
        if ($this->y - $leading - $space - ($type === 'p' ? 0 : 36) < 58) {
            $this->newPage();
        }
        foreach ($lines as $line) {
            if ($this->y - $leading < 58) {
                $this->newPage();
            }
            $operations = sprintf("BT /F1 %.2F Tf 0.09 0.12 0.16 rg 1 0 0 1 54 %.2F Tm <%s> Tj ET", $size, $this->y, $this->font->encode($line));
            $this->marked($tag, $operations);
            $this->y -= $leading;
        }
        $this->y -= $space;
    }

    /** @param array{title:string,blocks:list<array{string,string}>,items:list<list<string>>,spayd:string} $doc */
    public function render(array $doc): string
    {
        self::requireSupportedText($doc['title']);
        foreach ($doc['blocks'] as $block) {
            self::requireSupportedText($block[1]);
        }
        foreach ($doc['items'] as $row) {
            foreach ($row as $text) {
                self::requireSupportedText($text);
            }
        }
        $this->font = new ShopInvoiceFont();
        $catalog = $this->reserve();
        $pagesRoot = $this->reserve();
        $structRoot = $this->reserve();
        $document = $this->reserve();
        $parentTree = $this->reserve();
        $fontId = $this->reserve();
        $imageId = 0;
        if ($doc['spayd'] !== '') {
            $qr = \Kora\Shop\Qr\QrCode::encodeText($doc['spayd']);
            $pixels = '';
            for ($y = -4; $y < $qr->size + 4; $y++) {
                for ($x = -4; $x < $qr->size + 4; $x++) {
                    $pixels .= $qr->getModule($x, $y) ? "\0" : "\xFF";
                }
            }
            $imageId = $this->object(self::stream($pixels, '/Type /XObject /Subtype /Image /Width ' . ($qr->size + 8)
                . ' /Height ' . ($qr->size + 8) . ' /ColorSpace /DeviceGray /BitsPerComponent 8 /Interpolate false '));
        }
        $this->newPage();
        foreach ($doc['blocks'] as [$type,$text]) {
            if ($type === 'item') {
                foreach (shopInvoiceItemText($doc['items'][(int) $text - 1], (int) $text) as [$t,$s]) {
                    $this->paragraph($t, $s);
                }
            } elseif ($type === 'qr') {
                if ($this->y - 146 < 58) {
                    $this->newPage();
                }
                $tag = $this->tag('Figure', 'qr kód k platbě');
                $this->marked($tag, sprintf('q 130 0 0 130 54 %.2F cm /QR Do Q', $this->y - 130));
                $this->y -= 146;
                $this->paragraph('p', 'QR platba. Účet, částka, měna a variabilní symbol jsou uvedeny také v textu.');
            } else {
                $this->paragraph($type, $text);
            }
        }
        $pageRefs = $nums = [];
        foreach ($this->pages as $i => $page) {
            $footer = 'Strana ' . ($i + 1) . ' / ' . count($this->pages);
            $page['stream'] .= '/Artifact <</Type /Pagination /Subtype /Footer>> BDC ' . sprintf('BT /F1 9 Tf 1 0 0 1 54 30 Tm <%s> Tj ET', $this->font->encode($footer)) . " EMC\n";
            $content = $this->object(self::stream($page['stream']));
            $this->objects[$page['id']] = '<< /Type /Page /Parent ' . self::ref($pagesRoot) . ' /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 '
                . self::ref($fontId) . ' >>' . ($imageId ? ' /XObject << /QR ' . self::ref($imageId) . ' >>' : '')
                . ' >> /Contents ' . self::ref($content) . ' /StructParents ' . $i . ' /Tabs /S >>';
            $pageRefs[] = self::ref($page['id']);
            $nums[] = $i . ' [' . implode(' ', array_map([self::class,'ref'], $page['parents'])) . ']';
        }
        [$cidMap,$widths,$cmap] = $this->font->maps();
        $fontFile = $this->object(self::stream($this->font->bytes, '/Length1 ' . strlen($this->font->bytes) . ' '));
        $descriptor = $this->object('<< /Type /FontDescriptor /FontName /DejaVuSans /Flags 32 /FontBBox [' . implode(' ', $this->font->bbox)
            . '] /ItalicAngle 0 /Ascent ' . $this->font->ascent . ' /Descent ' . $this->font->descent . ' /CapHeight 729 /StemV 80 /FontFile2 ' . self::ref($fontFile) . ' >>');
        $mapping = $this->object(self::stream($cidMap));
        $toUnicode = $this->object(self::stream($cmap));
        $cidFont = $this->object('<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DejaVuSans /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor '
            . self::ref($descriptor) . ' /DW 600 /W [' . $widths . '] /CIDToGIDMap ' . self::ref($mapping) . ' >>');
        $this->objects[$fontId] = '<< /Type /Font /Subtype /Type0 /BaseFont /DejaVuSans /Encoding /Identity-H /DescendantFonts [' . self::ref($cidFont) . '] /ToUnicode ' . self::ref($toUnicode) . ' >>';
        $children = [];
        foreach ($this->structure as $entry) {
            $kids = [];
            foreach ($entry['kids'] as [$pageId,$mcid]) {
                $kids[] = '<< /Type /MCR /Pg ' . self::ref($pageId) . ' /MCID ' . $mcid . ' >>';
            }
            $this->objects[$entry['id']] = '<< /Type /StructElem /S /' . $entry['type'] . ' /P ' . self::ref($document)
                . ($entry['type'] === 'Figure' ? ' /Alt ' . self::unicode($entry['alt']) : '') . ' /K [' . implode(' ', $kids) . '] >>';
            $children[] = self::ref($entry['id']);
        }
        $this->objects[$document] = '<< /Type /StructElem /S /Document /P ' . self::ref($structRoot) . ' /K [' . implode(' ', $children) . '] >>';
        $this->objects[$parentTree] = '<< /Nums [' . implode(' ', $nums) . '] >>';
        $this->objects[$structRoot] = '<< /Type /StructTreeRoot /K [' . self::ref($document) . '] /ParentTree ' . self::ref($parentTree) . ' /ParentTreeNextKey ' . count($this->pages) . ' >>';
        $this->objects[$pagesRoot] = '<< /Type /Pages /Count ' . count($this->pages) . ' /Kids [' . implode(' ', $pageRefs) . '] >>';
        $this->objects[$catalog] = '<< /Type /Catalog /Pages ' . self::ref($pagesRoot) . ' /Lang (cs-CZ) /MarkInfo << /Marked true >> /StructTreeRoot '
            . self::ref($structRoot) . ' /ViewerPreferences << /DisplayDocTitle true >> >>';
        $info = $this->object('<< /Title ' . self::unicode($doc['title']) . ' /Author ' . self::unicode('Doklad digitálního obchodu') . ' /Producer (Kora snapshot invoice) >>');
        $pdf = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach (array_slice($this->objects, 1, null, true) as $id => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . count($this->objects) . "\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        return $pdf . 'trailer << /Size ' . count($this->objects) . ' /Root ' . self::ref($catalog) . ' /Info ' . self::ref($info)
            . " >>\nstartxref\n" . $xref . "\n%%EOF\n";
    }
}

/** @param array<string,mixed> $snapshot */
function shopInvoicePdf(array $snapshot): string
{
    // Bound optional layout work before splitting large legal texts into glyphs.
    if (strlen(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) > 512000) {
        throw new DomainException('Doklad přesahuje bezpečnou velikost PDF. Použijte úplný HTML nebo TXT doklad.');
    }
    return (new ShopInvoicePdfWriter())->render(shopInvoiceDocument($snapshot));
}
