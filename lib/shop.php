<?php

/** @return array<string,string> */
function shopSchema(): array
{
    $suffix = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    return [
        'cms_shop_categories' => "CREATE TABLE IF NOT EXISTS cms_shop_categories (
            id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, slug VARCHAR(150) NOT NULL,
            description TEXT, is_active TINYINT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0,
            UNIQUE KEY uq_shop_category_slug (slug))" . $suffix,
        'cms_shop_products' => "CREATE TABLE IF NOT EXISTS cms_shop_products (
            id INT AUTO_INCREMENT PRIMARY KEY, category_id INT NOT NULL, title VARCHAR(255) NOT NULL,
            slug VARCHAR(150) NOT NULL, description TEXT, requirements TEXT, license_text TEXT, update_policy TEXT,
            price_cents BIGINT NOT NULL, tax_class VARCHAR(20) NOT NULL DEFAULT 'general',
            file_storage_name VARCHAR(80) NOT NULL DEFAULT '', file_original_name VARCHAR(255) NOT NULL DEFAULT '',
            file_size BIGINT NOT NULL DEFAULT 0, file_sha256 VARCHAR(64) NOT NULL DEFAULT '',
            is_active TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_shop_product_slug (slug), INDEX idx_shop_product_category (category_id,is_active))" . $suffix,
        'cms_shop_payment_methods' => "CREATE TABLE IF NOT EXISTS cms_shop_payment_methods (
            id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, account_number VARCHAR(100) NOT NULL,
            iban VARCHAR(34) NOT NULL, is_active TINYINT NOT NULL DEFAULT 1,
            fio_token_encrypted TEXT, fio_last_polled_at DATETIME NULL, fio_last_error VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)" . $suffix,
        'cms_shop_tax_rules' => "CREATE TABLE IF NOT EXISTS cms_shop_tax_rules (
            id INT AUTO_INCREMENT PRIMARY KEY, country_code CHAR(2) NOT NULL, country_name VARCHAR(100) NOT NULL,
            general_rate_bp INT NOT NULL DEFAULT 0, publication_rate_bp INT NOT NULL DEFAULT 0,
            tax_note TEXT, is_active TINYINT NOT NULL DEFAULT 0,
            UNIQUE KEY uq_shop_tax_country (country_code))" . $suffix,
        'cms_shop_sequences' => "CREATE TABLE IF NOT EXISTS cms_shop_sequences (
            sequence_key VARCHAR(40) PRIMARY KEY, sequence_value INT NOT NULL DEFAULT 0)" . $suffix,
        'cms_shop_orders' => "CREATE TABLE IF NOT EXISTS cms_shop_orders (
            id INT AUTO_INCREMENT PRIMARY KEY, order_number VARCHAR(10) NOT NULL, user_id INT NULL,
            status ENUM('accepted','awaiting_payment','paid','fulfilled','cancelled','refunded') NOT NULL,
            customer_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL,
            city VARCHAR(150) NOT NULL, postal_code VARCHAR(30) NOT NULL, country_code CHAR(2) NOT NULL,
            payment_method_id INT NOT NULL, total_cents BIGINT NOT NULL, tax_cents BIGINT NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'CZK', seller_snapshot MEDIUMTEXT NOT NULL,
            legal_snapshot MEDIUMTEXT NOT NULL, payment_snapshot TEXT NOT NULL,
            token_hash CHAR(64) NOT NULL, token_encrypted TEXT NOT NULL, token_expires_at DATETIME NOT NULL,
            consent_at DATETIME NOT NULL, tax_verified_at DATETIME NULL, tax_evidence TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, paid_at DATETIME NULL, fulfilled_at DATETIME NULL,
            cancelled_at DATETIME NULL, confirmation_sent_at DATETIME NULL, delivery_sent_at DATETIME NULL,
            mail_claim_until DATETIME NULL, mail_attempts INT NOT NULL DEFAULT 0, mail_retry_at DATETIME NULL,
            mail_last_error VARCHAR(255) NOT NULL DEFAULT '',
            mail_sent_status VARCHAR(24) NOT NULL DEFAULT '', mail_claim_token CHAR(64) NULL,
            UNIQUE KEY uq_shop_order_number (order_number), UNIQUE KEY uq_shop_order_token (token_hash),
            INDEX idx_shop_orders_user (user_id,created_at), INDEX idx_shop_orders_status (status,created_at))" . $suffix,
        'cms_shop_order_items' => "CREATE TABLE IF NOT EXISTS cms_shop_order_items (
            id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, product_id INT NOT NULL, title VARCHAR(255) NOT NULL,
            quantity INT NOT NULL, unit_price_cents BIGINT NOT NULL, total_cents BIGINT NOT NULL,
            tax_rate_bp INT NOT NULL, tax_cents BIGINT NOT NULL, file_storage_name VARCHAR(80) NOT NULL,
            file_original_name VARCHAR(255) NOT NULL, file_size BIGINT NOT NULL, file_sha256 CHAR(64) NOT NULL,
            product_snapshot MEDIUMTEXT NOT NULL,
            INDEX idx_shop_items_order (order_id,id))" . $suffix,
        'cms_shop_payments' => "CREATE TABLE IF NOT EXISTS cms_shop_payments (
            id INT AUTO_INCREMENT PRIMARY KEY, payment_method_id INT NOT NULL, bank_transaction_id VARCHAR(100) NOT NULL,
            order_id INT NOT NULL, amount_cents BIGINT NOT NULL, currency CHAR(3) NOT NULL, received_at DATETIME NOT NULL,
            UNIQUE KEY uq_shop_payment_movement (payment_method_id,bank_transaction_id),
            UNIQUE KEY uq_shop_payment_order (order_id))" . $suffix,
        'cms_shop_invoices' => "CREATE TABLE IF NOT EXISTS cms_shop_invoices (
            id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, kind ENUM('proforma','final','credit') NOT NULL,
            invoice_number VARCHAR(40) NOT NULL, snapshot_json MEDIUMTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_shop_invoice_kind (order_id,kind), UNIQUE KEY uq_shop_invoice_number (invoice_number))" . $suffix,
        'cms_shop_order_events' => "CREATE TABLE IF NOT EXISTS cms_shop_order_events (
            id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, event_type VARCHAR(40) NOT NULL,
            note TEXT, user_id INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_shop_events_order (order_id,id))" . $suffix,
    ];
}

/** @return array<string,string> */
function shopSettings(): array
{
    $result = [];
    foreach (['seller_name','seller_address','seller_ico','seller_dic','seller_email','seller_phone','seller_register',
        'vat_mode','terms','privacy','complaints','withdrawal','ready'] as $key) {
        $result[$key] = getSetting('shop_' . $key, $key === 'vat_mode' ? 'non_vat' : '');
    }
    return $result;
}

function shopLockSettings(PDO $pdo): void
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Checkout settings require a transaction.');
    }
    // Use the current locked configuration, not an earlier request-cache snapshot.
    $statement = $pdo->query("SELECT `key`, value FROM cms_settings
        WHERE LEFT(`key`, 5) = 'shop_' OR `key` IN ('module_shop', 'site_url') ORDER BY `key` FOR UPDATE");
    $settings = getSettings();
    foreach (array_keys($settings) as $key) {
        if (str_starts_with($key, 'shop_') || in_array($key, ['module_shop', 'site_url'], true)) {
            unset($settings[$key]);
        }
    }
    foreach ($statement->fetchAll() as $row) {
        $settings[(string)$row['key']] = (string)$row['value'];
    }
    $GLOBALS['_CMS_SETTINGS'] = $settings;
}

function shopReady(): bool
{
    $settings = shopSettings();
    foreach (['seller_name','seller_address','seller_ico','seller_email','seller_phone','seller_register','terms','privacy','complaints','withdrawal'] as $key) {
        if (trim($settings[$key]) === '') {
            return false;
        }
    }
    $url = normalizeHttpExternalUrl(getSetting('site_url', ''), false);
    $host = (string)parse_url($url, PHP_URL_HOST);
    return $settings['ready'] === '1' && in_array($settings['vat_mode'], ['non_vat','vat','oss','non_vat_oss'], true)
        && ($settings['vat_mode'] === 'non_vat' || $settings['seller_dic'] !== '')
        && filter_var($settings['seller_email'], FILTER_VALIDATE_EMAIL) !== false
        && $url !== '' && (str_starts_with($url, 'https://') || in_array($host, ['localhost','127.0.0.1'], true));
}

function shopAmountCents(string $amount): ?int
{
    $amount = str_replace(',', '.', trim($amount));
    if (!preg_match('/\A(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,2}))?\z/', $amount, $matches)) {
        return null;
    }
    return (int)$matches[1] * 100 + (int)str_pad($matches[2] ?? '', 2, '0');
}

function shopMoney(int $cents): string
{
    return ($cents < 0 ? '-' : '') . number_format(intdiv(abs($cents), 100), 0, ',', ' ')
        . ',' . str_pad((string)(abs($cents) % 100), 2, '0', STR_PAD_LEFT) . ' Kč';
}

function shopMaximumOrderCents(): int
{
    return 999999999;
}

function shopRateTax(int $grossCents, int $rateBp): int
{
    if ($grossCents < 0 || $grossCents > 1000000000000 || $rateBp < 0 || $rateBp > 10000) {
        throw new DomainException('Neplatná částka nebo sazba daně.');
    }
    $denominator = 10000 + $rateBp;
    return intdiv($grossCents * $rateBp + intdiv($denominator, 2), $denominator);
}

function shopSlug(string $text): string
{
    return substr(slugify($text), 0, 150);
}

/** @return array<string,string> */
function shopStates(): array
{
    return ['accepted' => 'Přijatá', 'awaiting_payment' => 'Čeká na platbu', 'paid' => 'Zaplacená',
        'fulfilled' => 'Vyřízená', 'cancelled' => 'Zrušená', 'refunded' => 'Vrácená platba'];
}

function shopPrivateDirectory(): string
{
    $root = koraStorageDirectory();
    if (!koraEnsureDirectory($root, 0750)) {
        throw new RuntimeException('Soukromé úložiště není dostupné.');
    }
    $real = realpath($root);
    $web = realpath(dirname(__DIR__));
    $normalize = static fn (string $p): string => strtolower(str_replace('\\', '/', $p));
    if (!is_string($real) || !is_string($web) || is_link($root)
        || str_starts_with($normalize($real) . '/', $normalize($web) . '/')) {
        throw new RuntimeException('Obchod vyžaduje úložiště mimo veřejný web.');
    }
    $directory = $real . DIRECTORY_SEPARATOR . 'shop';
    if (is_link($directory) || !koraEnsureDirectory($directory, 0750)) {
        throw new RuntimeException('Soukromé úložiště obchodu není dostupné.');
    }
    return $directory;
}

function shopPrivatePath(string $name): string
{
    if (preg_match('/\A[a-f0-9]{64}\.bin\z/', $name) !== 1) {
        return '';
    }
    $directory = shopPrivateDirectory() . DIRECTORY_SEPARATOR . 'files';
    if (is_link($directory) || !koraEnsureDirectory($directory, 0750)) {
        throw new RuntimeException('Úložiště produktů není dostupné.');
    }
    $path = $directory . DIRECTORY_SEPARATOR . $name;
    return is_link($path) ? '' : $path;
}

/**
 * @param array<string,mixed> $file
 * @return array{file_storage_name:string,file_original_name:string,file_size:int,file_sha256:string}
 */
function shopStoreUpload(array $file): array
{
    $inspection = koraInspectUploadedFile($file, ['max_bytes' => koraDefaultUploadMaxSizeBytes()]);
    if (empty($inspection['ok'])) {
        throw new DomainException((string)($inspection['error'] ?? 'Soubor se nepodařilo ověřit.'));
    }
    $extension = koraUploadSanitizeExtension((string)($file['name'] ?? ''));
    if (!in_array($extension, ['zip','7z','tar','gz','bz2','xz','zst','exe','msi','dmg','pkg','rpm','deb','apk','pdf','epub','mp3','wav','flac','ogg','m4a','aac','mp4','webm','mov','mkv'], true)) {
        throw new DomainException('Nepodporovaný formát digitálního produktu.');
    }
    $source = (string)$inspection['tmp_path'];
    $hash = hash_file('sha256', $source);
    $size = filesize($source);
    if (!is_string($hash) || !is_int($size) || $size <= 0) {
        throw new DomainException('Soubor je prázdný nebo nečitelný.');
    }
    $name = $hash . '.bin';
    $path = shopPrivatePath($name);
    if ($path === '') {
        throw new RuntimeException('Soubor nelze bezpečně uložit.');
    }
    if (is_file($path)) {
        if (filesize($path) !== $size || !hash_equals($hash, (string)hash_file('sha256', $path))) {
            throw new RuntimeException('Uložený soubor neodpovídá kontrolnímu součtu.');
        }
    } elseif (!move_uploaded_file($source, $path)) {
        throw new RuntimeException('Soubor se nepodařilo uložit.');
    }
    if (!chmod($path, 0640)) {
        throw new RuntimeException('Nelze zabezpečit soubor produktu.');
    }
    return ['file_storage_name' => $name, 'file_original_name' => mb_substr(safeDownloadName((string)$file['name'], 'produkt.' . $extension), 0, 255),
        'file_size' => $size, 'file_sha256' => $hash];
}

function shopSecretKey(): string
{
    $path = shopPrivateDirectory() . DIRECTORY_SEPARATOR . 'secret.key';
    if (is_link($path)) {
        throw new RuntimeException('Neplatné úložiště klíče.');
    }
    if (!is_file($path)) {
        // Exclusive creation prevents two requests from replacing an existing key.
        set_error_handler(static fn (): bool => true);
        try {
            $stream = fopen($path, 'x+b');
        } finally {
            restore_error_handler();
        }
        if (is_resource($stream)) {
            $key = random_bytes(32);
            $written = fwrite($stream, $key);
            fclose($stream);
            if ($written !== 32 || !chmod($path, 0600)) {
                throw new RuntimeException('Nelze uložit klíč obchodu.');
            }
        }
    }
    $key = file_get_contents($path);
    if (!is_string($key) || strlen($key) !== 32) {
        throw new RuntimeException('Klíč obchodu není dostupný.');
    }
    return $key;
}

function shopSeal(string $value): string
{
    $iv = random_bytes(12);
    $cipher = openssl_encrypt($value, 'aes-256-gcm', shopSecretKey(), OPENSSL_RAW_DATA, $iv, $tag, 'kora-shop-v1');
    if (!is_string($cipher)) {
        throw new RuntimeException('Šifrování obchodu selhalo.');
    }
    return base64_encode($iv . $tag . $cipher);
}

function shopOpen(string $value): string
{
    $bytes = base64_decode($value, true);
    if (!is_string($bytes) || strlen($bytes) < 29) {
        throw new RuntimeException('Neplatné šifrované údaje.');
    }
    $plain = openssl_decrypt(
        substr($bytes, 28),
        'aes-256-gcm',
        shopSecretKey(),
        OPENSSL_RAW_DATA,
        substr($bytes, 0, 12),
        substr($bytes, 12, 16),
        'kora-shop-v1'
    );
    if (!is_string($plain)) {
        throw new RuntimeException('Klíč neodpovídá datům obchodu.');
    }
    return $plain;
}

/** @param array<string,mixed> $order */
function shopToken(array $order): string
{
    return shopOpen((string)$order['token_encrypted']);
}

/** @param array<string,mixed> $order */
function shopOrderUrl(array $order): string
{
    $base = rtrim(normalizeHttpExternalUrl(getSetting('site_url', ''), false), '/');
    if ($base === '') {
        throw new RuntimeException('Nastavte důvěryhodnou veřejnou URL webu.');
    }
    return $base . BASE_URL . '/shop/order.php?token=' . rawurlencode(shopToken($order));
}

/** @return array<string,mixed>|null */
function shopOrderByToken(PDO $pdo, string $token): ?array
{
    if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT *, (token_expires_at > NOW()) AS token_active
        FROM cms_shop_orders WHERE token_hash = ? AND token_expires_at > NOW()');
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/** @return array<string,mixed>|null */
function shopFindOwnedOrder(PDO $pdo, int $id): ?array
{
    $uid = currentUserId();
    if ($uid === null) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT *, (token_expires_at > NOW()) AS token_active
        FROM cms_shop_orders WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $uid]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/** @return list<array<string,mixed>> */
function shopOrderItems(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM cms_shop_order_items WHERE order_id = ? ORDER BY id');
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

function shopSafeHeaders(): void
{
    header('Cache-Control: no-store, private, max-age=0');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
}

function shopEvent(PDO $pdo, int $id, string $type, string $note = ''): void
{
    $pdo->prepare('INSERT INTO cms_shop_order_events (order_id,event_type,note,user_id) VALUES (?,?,?,?)')
        ->execute([$id, $type, mb_substr($note, 0, 2000), currentUserId()]);
}

/**
 * @param list<array<string,mixed>> $items
 * @param array<string,mixed> $rule
 */
function shopQuoteSignature(array $items, string $country, array $rule): string
{
    $lines = [];
    foreach ($items as $item) {
        $lines[] = [(int)$item['id'],(int)$item['quantity'],(int)$item['price_cents'],
            (string)$item['tax_class'],(string)$item['file_sha256'],(string)($item['updated_at'] ?? ''),
            (string)($item['title'] ?? ''),(string)($item['description'] ?? ''),
            (string)($item['requirements'] ?? ''),(string)($item['license_text'] ?? ''),
            (string)($item['update_policy'] ?? ''),(string)($item['file_original_name'] ?? '')];
    }
    usort($lines, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
    $data = [$country,$lines,(int)$rule['general_rate_bp'],(int)$rule['publication_rate_bp'],
        (string)($rule['tax_note'] ?? ''),shopSettings()];
    return hash_hmac('sha256', json_encode($data, JSON_THROW_ON_ERROR), shopSecretKey());
}

/** @param array<int|string,mixed> $cart */
function shopCartQuote(PDO $pdo, array $cart, string $country): string
{
    $stmt = $pdo->prepare('SELECT * FROM cms_shop_tax_rules WHERE country_code=? AND is_active=1');
    $stmt->execute([$country]);
    $rule = $stmt->fetch();
    if (!is_array($rule)) {
        return '';
    }
    $items = [];
    foreach ($cart as $id => $quantity) {
        $stmt = $pdo->prepare('SELECT p.* FROM cms_shop_products p JOIN cms_shop_categories c ON c.id=p.category_id
            WHERE p.id=? AND p.is_active=1 AND c.is_active=1');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!is_array($item)) {
            return '';
        }
        $item['quantity'] = $quantity;
        $items[] = $item;
    }
    return shopQuoteSignature($items, $country, $rule);
}

/** @return array<string,mixed> */
function shopLockOrder(PDO $pdo, int $id): array
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Shop writes require a transaction.');
    }
    $stmt = $pdo->prepare('SELECT *, COALESCE(mail_claim_until > NOW(), 0) AS mail_claim_active,
        COALESCE(mail_retry_at > NOW(), 0) AS mail_retry_pending, (token_expires_at > NOW()) AS token_active
        FROM cms_shop_orders WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        throw new DomainException('Objednávka nebyla nalezena.');
    }
    return $row;
}

/**
 * @param array<string,mixed> $customer
 * @param array<int|string,mixed> $cart
 * @return array<string,mixed>
 */
function shopCreateOrder(PDO $pdo, array $customer, array $cart, int $paymentId): array
{
    foreach (['customer_name' => 255,'email' => 255,'address' => 255,'city' => 150,'postal_code' => 30] as $key => $max) {
        if (!is_string($customer[$key] ?? null) || trim($customer[$key]) === '' || mb_strlen($customer[$key]) > $max
            || preg_match('/[\x00-\x1f\x7f]/', $customer[$key])) {
            throw new DomainException('Doplňte platné fakturační a kontaktní údaje.');
        }
    }
    if (!filter_var($customer['email'], FILTER_VALIDATE_EMAIL)
        || ($customer['confirm_terms'] ?? '') !== '1' || ($customer['confirm_digital'] ?? '') !== '1') {
        throw new DomainException('Zkontrolujte e-mail a výslovně potvrďte podmínky i souhlas s digitálním dodáním.');
    }
    if ($cart === [] || count($cart) > 20) {
        throw new DomainException('Vyberte 1 až 20 různých produktů.');
    }
    $country = strtoupper(trim((string)($customer['country_code'] ?? '')));
    $pdo->beginTransaction();
    try {
        shopLockSettings($pdo);
        if (!isModuleEnabled('shop') || !shopReady()) {
            throw new DomainException('Obchod nyní nepřijímá objednávky. Obsah košíku zůstává zachovaný.');
        }
        $ruleStmt = $pdo->prepare('SELECT * FROM cms_shop_tax_rules WHERE country_code = ? AND is_active = 1 FOR UPDATE');
        $ruleStmt->execute([$country]);
        $rule = $ruleStmt->fetch();
        $methodStmt = $pdo->prepare('SELECT * FROM cms_shop_payment_methods WHERE id = ? AND is_active = 1 FOR UPDATE');
        $methodStmt->execute([$paymentId]);
        $method = $methodStmt->fetch();
        if (!is_array($rule) || !is_array($method) || shopNormalizeIban((string)$method['iban']) === '') {
            throw new DomainException('Vyberte podporovanou zemi a dostupný bankovní převod.');
        }
        $items = [];
        $total = $taxTotal = 0;
        ksort($cart, SORT_NUMERIC);
        foreach ($cart as $productId => $quantityRaw) {
            $quantity = filter_var($quantityRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1,'max_range' => 100]]);
            if ($quantity === false || filter_var($productId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new DomainException('Neplatné množství produktu.');
            }
            $stmt = $pdo->prepare('SELECT p.* FROM cms_shop_products p JOIN cms_shop_categories c ON c.id=p.category_id
                WHERE p.id=? AND p.is_active=1 AND c.is_active=1 FOR UPDATE');
            $stmt->execute([(int)$productId]);
            $product = $stmt->fetch();
            if (!is_array($product) || !shopProductFileValid($product)) {
                throw new DomainException('Některý produkt již není dostupný. Zkontrolujte košík.');
            }
            $rate = (int)$rule[$product['tax_class'] === 'publication' ? 'publication_rate_bp' : 'general_rate_bp'];
            if (in_array(shopSettings()['vat_mode'], ['non_vat','non_vat_oss'], true) && $country === 'CZ' && $rate !== 0) {
                throw new DomainException('Prodejce neplátce nemůže účtovat tuzemskou DPH.');
            }
            $lineTotal = (int)$product['price_cents'] * $quantity;
            if ($lineTotal <= 0 || $lineTotal > 10000000000) {
                throw new DomainException('Neplatná cena produktu.');
            }
            $tax = shopRateTax($lineTotal, $rate);
            $items[] = $product + ['quantity' => $quantity, 'total_cents' => $lineTotal,'tax_rate_bp' => $rate,'tax_cents' => $tax];
            $total += $lineTotal;
            $taxTotal += $tax;
        }
        if ($total > shopMaximumOrderCents()) {
            throw new DomainException('Cena objednávky přesahuje limit 9 999 999,99 Kč podporovaný pro QR platbu. Upravte košík.');
        }
        $quote = shopQuoteSignature($items, $country, $rule);
        if (!is_string($customer['quote_signature'] ?? null) || !hash_equals($quote, $customer['quote_signature'])) {
            throw new DomainException('Cena nebo daňové nastavení se změnily. Zkontrolujte aktuální souhrn a objednávku potvrďte znovu.');
        }
        $year = date('Y');
        $pdo->prepare('INSERT IGNORE INTO cms_shop_sequences (sequence_key,sequence_value) VALUES (?,0)')->execute([$year]);
        $sequence = $pdo->prepare('SELECT sequence_value FROM cms_shop_sequences WHERE sequence_key=? FOR UPDATE');
        $sequence->execute([$year]);
        $next = (int)$sequence->fetchColumn() + 1;
        if ($next > 999999) {
            throw new DomainException('Číselná řada objednávek pro tento rok je vyčerpaná.');
        }
        $pdo->prepare('UPDATE cms_shop_sequences SET sequence_value=? WHERE sequence_key=?')->execute([$next,$year]);
        $number = $year . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
        $token = bin2hex(random_bytes(32));
        $settings = shopSettings();
        $legal = ['terms' => $settings['terms'],'privacy' => $settings['privacy'],'complaints' => $settings['complaints'],
            'withdrawal' => $settings['withdrawal'], 'tax_note' => (string)$rule['tax_note'],
            'consent' => 'Výslovně souhlasím se zpřístupněním digitálního obsahu před uplynutím lhůty pro odstoupení a beru na vědomí, že jeho zpřístupněním ztrácím právo na odstoupení od této smlouvy. Práva z vad zůstávají zachovaná.'];
        $payment = ['name' => $method['name'],'iban' => $method['iban'],'account_number' => $method['account_number']];
        $stmt = $pdo->prepare('INSERT INTO cms_shop_orders
            (order_number,user_id,status,customer_name,email,address,city,postal_code,country_code,payment_method_id,
            total_cents,tax_cents,seller_snapshot,legal_snapshot,payment_snapshot,token_hash,token_encrypted,token_expires_at,consent_at,tax_verified_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 365 DAY),NOW(),?)');
        $stmt->execute([$number,currentUserId(),$country === 'CZ' ? 'awaiting_payment' : 'accepted',
            $customer['customer_name'],$customer['email'],$customer['address'],$customer['city'],$customer['postal_code'],$country,
            $paymentId,$total,$taxTotal,json_encode($settings, JSON_THROW_ON_ERROR),json_encode($legal, JSON_THROW_ON_ERROR),
            json_encode($payment, JSON_THROW_ON_ERROR),hash('sha256', $token),shopSeal($token),$country === 'CZ' ? date('Y-m-d H:i:s') : null]);
        $id = (int)$pdo->lastInsertId();
        $insert = $pdo->prepare('INSERT INTO cms_shop_order_items
            (order_id,product_id,title,quantity,unit_price_cents,total_cents,tax_rate_bp,tax_cents,file_storage_name,file_original_name,file_size,file_sha256,product_snapshot)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($items as $item) {
            $insert->execute([$id,$item['id'],$item['title'],$item['quantity'],$item['price_cents'],$item['total_cents'],
                $item['tax_rate_bp'],$item['tax_cents'],$item['file_storage_name'],$item['file_original_name'],$item['file_size'],$item['file_sha256'],
                json_encode(array_intersect_key($item, array_flip(['description','requirements','license_text','update_policy'])), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        }
        $order = shopLockOrder($pdo, $id);
        if ($country === 'CZ') {
            shopCreateInvoiceLocked($pdo, $order, 'proforma');
        }
        shopEvent($pdo, $id, 'created', 'Objednávka přijata; digitální souhlas uložen v neměnné kopii.');
        $pdo->commit();
        return $order;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @param array<string,mixed> $file */
function shopProductFileValid(array $file): bool
{
    $name = (string)($file['file_storage_name'] ?? '');
    $path = shopPrivatePath($name);
    return $path !== '' && is_file($path) && !is_link($path) && is_readable($path)
        && (int)($file['file_size'] ?? 0) > 0 && filesize($path) === (int)$file['file_size']
        && hash_equals((string)($file['file_sha256'] ?? ''), (string)hash_file('sha256', $path));
}

/** @param array<string,mixed> $order */
function shopCanDownload(array $order): bool
{
    $active = array_key_exists('token_active', $order) ? (int)$order['token_active'] === 1
        : strtotime((string)($order['token_expires_at'] ?? '')) > time();
    return ($order['status'] ?? '') === 'fulfilled' && !empty($order['paid_at']) && !empty($order['consent_at'])
        && !empty($order['tax_verified_at']) && $active;
}

/**
 * @param array<string,mixed> $order
 * @return array<string,mixed>
 */
function shopCreateInvoiceLocked(PDO $pdo, array $order, string $kind, string $creditReason = ''): array
{
    if (!$pdo->inTransaction() || !in_array($kind, ['proforma','final','credit'], true)) {
        throw new LogicException('Invoice writes require a transaction and a known kind.');
    }
    $existing = $pdo->prepare('SELECT * FROM cms_shop_invoices WHERE order_id=? AND kind=?');
    $existing->execute([$order['id'],$kind]);
    $row = $existing->fetch();
    if (is_array($row)) {
        return $row;
    }
    if ($kind !== 'proforma' && (empty($order['paid_at']) || empty($order['tax_verified_at']))) {
        throw new DomainException('Finální doklad vyžaduje potvrzenou úhradu a ověřenou daň.');
    }
    $number = ['proforma' => 'P-','final' => 'FV-','credit' => 'OD-'][$kind] . $order['order_number'];
    $snapshot = ['order' => $order,'items' => shopOrderItems($pdo, (int)$order['id']),
        'settings' => json_decode((string)$order['seller_snapshot'], true, 512, JSON_THROW_ON_ERROR),
        'legal' => json_decode((string)$order['legal_snapshot'], true, 512, JSON_THROW_ON_ERROR),
        'payment' => json_decode((string)$order['payment_snapshot'], true, 512, JSON_THROW_ON_ERROR),
        'issued_at' => date('Y-m-d H:i:s'),'kind' => $kind,'invoice_number' => $number];
    if ($kind === 'credit') {
        $snapshot['credit_reason'] = $creditReason;
        $snapshot['original_invoice_number'] = 'FV-' . $order['order_number'];
    }
    // Documents never contain bearer tokens, encrypted secrets or unrelated mail metadata.
    foreach (['token_hash','token_encrypted','mail_claim_token','mail_claim_until','mail_last_error','mail_attempts',
        'mail_retry_at','mail_sent_status','mail_claim_active','mail_retry_pending','token_active','tax_evidence',
        'seller_snapshot','legal_snapshot','payment_snapshot'] as $key) {
        unset($snapshot['order'][$key]);
    }
    $pdo->prepare('INSERT INTO cms_shop_invoices (order_id,kind,invoice_number,snapshot_json) VALUES (?,?,?,?)')
        ->execute([$order['id'],$kind,$number,json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    $existing->execute([$order['id'],$kind]);
    return $existing->fetch();
}

/**
 * @param array<string,mixed> $order
 * @return array<string,mixed>
 */
function shopInvoice(PDO $pdo, array $order, string $kind): array
{
    if (!in_array($kind, ['proforma','final','credit'], true)) {
        throw new DomainException('Neznámý doklad.');
    }
    $stmt = $pdo->prepare('SELECT * FROM cms_shop_invoices WHERE order_id=? AND kind=?');
    $stmt->execute([$order['id'],$kind]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        throw new DomainException('Doklad zatím nebyl vystaven.');
    }
    return $row;
}

function shopRecordPayment(PDO $pdo, int $orderId, string $reference, ?string $receivedAt = null, ?int $methodId = null): bool
{
    if (!preg_match('/\A[A-Za-z0-9:._-]{1,100}\z/', $reference)) {
        throw new DomainException('Doplňte jednoznačné číslo bankovního pohybu bez osobních údajů.');
    }
    $receivedAt ??= date('Y-m-d H:i:s');
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $receivedAt);
    if ($parsedDate === false || $parsedDate->format('Y-m-d H:i:s') !== $receivedAt || $parsedDate->getTimestamp() > time() + 86400) {
        throw new DomainException('Neplatné datum úhrady.');
    }
    $pdo->beginTransaction();
    try {
        $order = shopLockOrder($pdo, $orderId);
        if ($order['status'] !== 'awaiting_payment') {
            $pdo->rollBack();
            return false;
        }
        if (empty($order['tax_verified_at']) || empty($order['consent_at'])) {
            throw new DomainException('Úhradu nelze potvrdit bez ověřené daně a uloženého souhlasu.');
        }
        if ($methodId !== null && $methodId !== (int)$order['payment_method_id']) {
            throw new DomainException('Platba patří jinému bankovnímu účtu.');
        }
        if (strtotime($receivedAt) < strtotime((string)$order['created_at']) - 86400) {
            throw new DomainException('Platba předchází objednávce.');
        }
        $pdo->prepare('INSERT INTO cms_shop_payments (payment_method_id,bank_transaction_id,order_id,amount_cents,currency,received_at)
            VALUES (?,?,?,?,?,?)')->execute([$order['payment_method_id'],$reference,$orderId,$order['total_cents'],$order['currency'],$receivedAt]);
        $pdo->prepare("UPDATE cms_shop_orders SET status='paid',paid_at=?,mail_attempts=0,mail_retry_at=NULL WHERE id=?")
            ->execute([$receivedAt,$orderId]);
        $order['paid_at'] = $receivedAt;
        $order['status'] = 'paid';
        shopCreateInvoiceLocked($pdo, $order, 'final');
        shopEvent($pdo, $orderId, 'paid', 'Úhrada ověřena podle bankovního pohybu.');
        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @param array<string,mixed> $evidence */
function shopVerifyTax(PDO $pdo, int $orderId, array $evidence): void
{
    $types = ['billing','bank','geolocation','other'];
    if (($evidence['confirm_tax'] ?? '') !== '1' || !in_array($evidence['type_one'] ?? '', $types, true)
        || !in_array($evidence['type_two'] ?? '', $types, true) || $evidence['type_one'] === $evidence['type_two']
        || mb_strlen(trim((string)($evidence['note'] ?? ''))) < 20 || mb_strlen((string)$evidence['note']) > 2000) {
        throw new DomainException('Potvrďte daňový režim, dvě odlišné kategorie ověřených podkladů a popis jejich kontroly.');
    }
    $pdo->beginTransaction();
    try {
        $order = shopLockOrder($pdo, $orderId);
        if (($evidence['country'] ?? '') !== $order['country_code'] || $order['status'] !== 'accepted') {
            throw new DomainException('Země musí souhlasit s objednávkou; kontrola patří pouze přijaté zahraniční objednávce.');
        }
        $pdo->prepare("UPDATE cms_shop_orders SET tax_verified_at=NOW(),tax_evidence=?,status='awaiting_payment',
            confirmation_sent_at=NULL,mail_attempts=0,mail_retry_at=NULL WHERE id=?")
            ->execute([json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),$orderId]);
        shopEvent($pdo, $orderId, 'tax_verified', 'Země a daňový režim ověřeny; objednávka čeká na platbu.');
        shopCreateInvoiceLocked($pdo, shopLockOrder($pdo, $orderId), 'proforma');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function shopChangeOrder(PDO $pdo, int $orderId, string $action, string $note = ''): void
{
    $pdo->beginTransaction();
    try {
        $order = shopLockOrder($pdo, $orderId);
        if ($action === 'cancel' && in_array($order['status'], ['accepted','awaiting_payment'], true)) {
            $pdo->prepare("UPDATE cms_shop_orders SET status='cancelled',cancelled_at=NOW(),mail_attempts=0,mail_retry_at=NULL WHERE id=?")->execute([$orderId]);
        } elseif ($action === 'refund' && !empty($order['paid_at'])
            && in_array($order['status'], ['paid','fulfilled','cancelled'], true) && mb_strlen(trim($note)) >= 10) {
            shopCreateInvoiceLocked($pdo, $order, 'credit', $note);
            $pdo->prepare("UPDATE cms_shop_orders SET status='refunded',mail_attempts=0,mail_retry_at=NULL WHERE id=?")->execute([$orderId]);
        } else {
            throw new DomainException('Tato změna stavu není povolená. Refundaci zaznamenejte až po skutečném vrácení peněz.');
        }
        shopEvent($pdo, $orderId, $action, $note);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @param array<string,mixed> $order */
function shopWithdrawalAvailable(array $order): bool
{
    return in_array($order['status'] ?? '', ['accepted', 'awaiting_payment', 'paid'], true)
        && empty($order['fulfilled_at']);
}

function shopWithdrawOrder(PDO $pdo, int $orderId): void
{
    $pdo->beginTransaction();
    try {
        $order = shopLockOrder($pdo, $orderId);
        $existing = $pdo->prepare("SELECT id FROM cms_shop_order_events WHERE order_id=? AND event_type='withdrawal' LIMIT 1");
        $existing->execute([$orderId]);
        if ($existing->fetchColumn() !== false) {
            $pdo->commit();
            return;
        }
        if (!shopWithdrawalAvailable($order)) {
            throw new DomainException('Obsah již byl zpřístupněn, nebo objednávka není v otevřeném stavu. Práva z vad tím nejsou dotčena; kontaktujte prodejce.');
        }
        $submittedAt = (string)$pdo->query("SELECT DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-%dT%H:%i:%sZ')")->fetchColumn();
        $receipt = ['order_number' => (string)$order['order_number'], 'customer_name' => (string)$order['customer_name'],
            'email' => (string)$order['email'], 'submitted_at' => $submittedAt];
        $pdo->prepare("UPDATE cms_shop_orders SET status='cancelled',cancelled_at=NOW(),mail_attempts=0,
            mail_retry_at=NULL,mail_sent_status='' WHERE id=?")->execute([$orderId]);
        shopEvent($pdo, $orderId, 'withdrawal', json_encode($receipt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @param array<string,mixed> $order */
function shopWithdrawalText(PDO $pdo, array $order): ?string
{
    $stmt = $pdo->prepare("SELECT note FROM cms_shop_order_events WHERE order_id=? AND event_type='withdrawal' ORDER BY id LIMIT 1");
    $stmt->execute([$order['id']]);
    $note = $stmt->fetchColumn();
    if (!is_string($note)) {
        return null;
    }
    $receipt = json_decode($note, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($receipt) || ($receipt['order_number'] ?? '') !== (string)$order['order_number']) {
        throw new RuntimeException('Potvrzení odstoupení nelze bezpečně načíst.');
    }
    return "Potvrzení přijetí odstoupení od smlouvy\n"
        . 'Objednávka: ' . (string)$receipt['order_number'] . "\n"
        . 'Zákazník: ' . (string)$receipt['customer_name'] . "\nE-mail: " . (string)$receipt['email'] . "\n"
        . 'Datum a čas odeslání (UTC): ' . (string)$receipt['submitted_at'] . "\n"
        . "Prohlášení: Odstupuji od celé uvedené smlouvy.\n"
        . (!empty($order['paid_at']) ? 'Platba byla přijata. Prodejce musí samostatně vyřídit její vrácení; toto potvrzení peníze nepřevádí.'
            : 'Objednávka je zrušena. Neposílejte platbu.')
        . "\nDigitální obsah nebude zpřístupněn. Práva z vad nejsou dotčena.\n";
}

/**
 * @param array<string,mixed> $order
 * @return array<string,mixed>
 */
function shopContractSnapshot(PDO $pdo, array $order): array
{
    $snapshot = ['order' => $order,'items' => shopOrderItems($pdo, (int)$order['id']),
        'settings' => json_decode((string)$order['seller_snapshot'], true, 512, JSON_THROW_ON_ERROR),
        'legal' => json_decode((string)$order['legal_snapshot'], true, 512, JSON_THROW_ON_ERROR),
        'payment' => json_decode((string)$order['payment_snapshot'], true, 512, JSON_THROW_ON_ERROR),
        'issued_at' => (string)$order['created_at'],'kind' => 'contract','invoice_number' => (string)$order['order_number']];
    foreach (['token_hash','token_encrypted','mail_claim_token','mail_last_error','tax_evidence'] as $key) {
        unset($snapshot['order'][$key]);
    }
    return $snapshot;
}

/** @param array<string,mixed> $snapshot */
function shopContractHtml(array $snapshot): string
{
    $escape = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = 'Objednávka ' . $snapshot['order']['order_number'] . "\n"
        . 'Prodejce: ' . $snapshot['settings']['seller_name'] . "\n"
        . $snapshot['settings']['seller_address'] . "\nIČO: " . $snapshot['settings']['seller_ico']
        . "\nKontakt: " . $snapshot['settings']['seller_email'] . "\n"
        . 'Zákazník: ' . $snapshot['order']['customer_name'] . "\n" . $snapshot['order']['address'] . "\n"
        . $snapshot['order']['postal_code'] . ' ' . $snapshot['order']['city'] . ' ' . $snapshot['order']['country_code'] . "\n";
    foreach ($snapshot['items'] as $item) {
        $text .= $item['title'] . ' (' . $item['quantity'] . '): ' . shopMoney((int)$item['total_cents']) . "\n";
        $details = json_decode((string)$item['product_snapshot'], true, 512, JSON_THROW_ON_ERROR);
        foreach ($details as $value) {
            $text .= (string)$value . "\n";
        }
    }
    $html = '<!doctype html><html lang="cs"><meta charset="utf-8"><title>Potvrzení smlouvy</title><body><main>'
        . '<h1>Potvrzení smlouvy</h1><p>' . nl2br($escape($text)) . '</p><p>Celkem: '
        . $escape(shopMoney((int)$snapshot['order']['total_cents'])) . '</p>';
    foreach (['terms' => 'Obchodní podmínky','privacy' => 'Ochrana soukromí','complaints' => 'Reklamace',
        'withdrawal' => 'Odstoupení','tax_note' => 'Daňové informace','consent' => 'Potvrzený souhlas s digitálním dodáním'] as $key => $title) {
        $html .= '<h2>' . $escape($title) . '</h2><p>' . nl2br($escape($snapshot['legal'][$key] ?? '')) . '</p>';
    }
    return $html . '</main></body></html>';
}

/** @param null|callable(string,string,string,array<string,mixed>):bool $mailer */
function shopDispatchOrder(PDO $pdo, int $orderId, ?callable $mailer = null): bool
{
    $mailer ??= 'sendMail';
    $claim = bin2hex(random_bytes(32));
    $pdo->beginTransaction();
    try {
        $order = shopLockOrder($pdo, $orderId);
        $phase = (string)$order['status'];
        if (!in_array($phase, ['accepted','awaiting_payment','paid','fulfilled','cancelled','refunded'], true)
            || $order['mail_sent_status'] === $phase || (int)$order['mail_attempts'] >= 5
            || (int)$order['mail_claim_active'] === 1 || (int)$order['mail_retry_pending'] === 1) {
            $pdo->rollBack();
            return false;
        }
        $pdo->prepare('UPDATE cms_shop_orders SET mail_claim_token=?,mail_claim_until=DATE_ADD(NOW(),INTERVAL 10 MINUTE),
            mail_attempts=mail_attempts+1 WHERE id=?')->execute([$claim,$orderId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    $sent = false;
    $delivery = null;
    try {
        $snapshot = shopContractSnapshot($pdo, $order);
        $attachments = [['filename' => 'smlouva-' . $order['order_number'] . '.html','content_type' => 'text/html',
            'content' => shopContractHtml($snapshot)]];
        $withdrawal = $phase === 'cancelled' ? shopWithdrawalText($pdo, $order) : null;
        if ($withdrawal !== null) {
            $receiptHtml = '<!doctype html><html lang="cs"><meta charset="utf-8"><title>Potvrzení odstoupení</title><body><main>'
                . '<h1>Potvrzení odstoupení</h1><p>' . nl2br(htmlspecialchars($withdrawal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
                . '</p></main></body></html>';
            $attachments[] = ['filename' => 'odstoupeni-' . $order['order_number'] . '.html', 'content_type' => 'text/html', 'content' => $receiptHtml];
            $attachments[] = ['filename' => 'odstoupeni-' . $order['order_number'] . '.txt', 'content_type' => 'text/plain', 'content' => $withdrawal];
        }
        if (in_array($phase, ['awaiting_payment','paid','fulfilled','refunded'], true)) {
            $invoice = shopInvoice($pdo, $order, ['awaiting_payment' => 'proforma','paid' => 'final','fulfilled' => 'final','refunded' => 'credit'][$phase]);
            $invoiceSnapshot = json_decode((string)$invoice['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            $attachments[] = ['filename' => $invoice['invoice_number'] . '.html','content_type' => 'text/html',
                'content' => shopInvoiceHtml($invoiceSnapshot)];
            $attachments[] = ['filename' => $invoice['invoice_number'] . '.txt','content_type' => 'text/plain',
                'content' => shopInvoiceText($invoiceSnapshot)];
            try {
                $attachments[] = ['filename' => $invoice['invoice_number'] . '.pdf','content_type' => 'application/pdf',
                    'content' => shopInvoicePdf($invoiceSnapshot)];
            } catch (DomainException) {
                // A font limitation must not withhold paid content or lose a legal document.
                shopEvent($pdo, $orderId, 'pdf_alternative', 'PDF nelze vytvořit; úplný doklad byl připraven v HTML a textu.');
            }
        }
        $message = ['accepted' => 'Objednávku jsme přijali. Vyčkejte na ověření země a daňového režimu. Zatím neplaťte.',
            'awaiting_payment' => 'Objednávka čeká na bankovní převod. Platební údaje a QR kód obsahuje přiložená platební výzva.',
            'paid' => 'Platba byla ověřena. Digitální obsah je zpřístupněn přes následující jedinečný odkaz. Uchovejte jej soukromě.',
            'fulfilled' => 'Opakované oznámení: platba byla ověřena. Digitální obsah je přístupný přes následující jedinečný odkaz. Uchovejte jej soukromě.',
            'cancelled' => 'Objednávka byla zrušena. Digitální obsah není zpřístupněn.',
            'refunded' => 'Vrácení platby bylo zaznamenáno. Přístup ke stažení je zrušen; přiložen je opravný doklad.'][$phase];
        $body = $message . "\n\nObjednávka: " . $order['order_number'] . "\n" . shopOrderUrl($order)
            . "\n\nNeměnná kopie smlouvy a digitálního souhlasu je v příloze.\nKontakt prodejce: " . $snapshot['settings']['seller_email'];
        if ($withdrawal !== null) {
            $body .= "\n\n" . $withdrawal;
        }
        $delivery = ['body' => $body, 'options' => ['reply_to' => (string)$snapshot['settings']['seller_email'],
            'attachments' => $attachments]];
    } catch (Throwable $exception) {
        // Never persist exception text: transports may include private addresses or bank tokens.
        $sent = false;
    }
    $pdo->beginTransaction();
    try {
        $fresh = shopLockOrder($pdo, $orderId);
        if ($fresh['mail_claim_token'] !== $claim) {
            $pdo->rollBack();
            return false;
        }
        if ($fresh['status'] !== $phase) {
            $pdo->prepare('UPDATE cms_shop_orders SET mail_claim_until=NULL,mail_claim_token=NULL WHERE id=?')->execute([$orderId]);
            $pdo->commit();
            return false;
        }
        // Serialize SMTP with cancellation/refund; preparing documents did not hold the lock.
        if ($delivery !== null) {
            try {
                $sent = $mailer(
                    (string)$fresh['email'],
                    'Objednávka ' . $fresh['order_number'],
                    $delivery['body'],
                    $delivery['options']
                );
            } catch (Throwable) {
                $sent = false;
            }
        }
        if ($sent && $fresh['status'] === $phase) {
            if ($phase === 'paid') {
                $pdo->prepare("UPDATE cms_shop_orders SET status='fulfilled',fulfilled_at=NOW(),delivery_sent_at=NOW(),
                    token_expires_at=DATE_ADD(NOW(),INTERVAL 365 DAY),mail_sent_status='fulfilled' WHERE id=?")->execute([$orderId]);
                shopEvent($pdo, $orderId, 'fulfilled', 'SMTP přijalo oznámení dodání; přístup k zakoupenému souboru aktivován.');
            } else {
                $pdo->prepare('UPDATE cms_shop_orders SET mail_sent_status=?,confirmation_sent_at=NOW() WHERE id=?')->execute([$phase,$orderId]);
            }
        }
        $pdo->prepare('UPDATE cms_shop_orders SET mail_claim_until=NULL,mail_claim_token=NULL,mail_last_error=?,
            mail_retry_at=IF(?,NULL,DATE_ADD(NOW(),INTERVAL ? SECOND)) WHERE id=?')
            ->execute([$sent ? '' : 'Odeslání se nezdařilo; ověřte SMTP a dostupnost soukromého úložiště.',
                $sent ? 1 : 0, 900 * min(5, (int)$fresh['mail_attempts']), $orderId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    return $sent;
}

/** @param array<string,mixed> $statement */
function shopApplyFioStatement(PDO $pdo, int $methodId, array $statement): int
{
    $stmt = $pdo->prepare('SELECT * FROM cms_shop_payment_methods WHERE id=?');
    $stmt->execute([$methodId]);
    $method = $stmt->fetch();
    $info = $statement['accountStatement']['info'] ?? [];
    if (!is_array($method) || !is_array($info) || shopNormalizeIban((string)($info['iban'] ?? '')) === ''
        || shopNormalizeIban((string)$info['iban']) !== shopNormalizeIban((string)$method['iban'])
        || ($info['currency'] ?? '') !== 'CZK') {
        throw new DomainException('Výpis nepatří nastavenému účtu a měně CZK.');
    }
    $transactions = $statement['accountStatement']['transactionList']['transaction'] ?? [];
    if (!is_array($transactions) || count($transactions) > 20000) {
        throw new DomainException('Neplatný rozsah bankovního výpisu.');
    }
    $count = 0;
    foreach ($transactions as $transaction) {
        if (!is_array($transaction)) {
            continue;
        }
        $value = static fn (int $column): mixed => $transaction['column' . $column]['value'] ?? null;
        $amount = $value(1);
        $cents = (is_string($amount) || is_int($amount) || is_float($amount)) ? shopAmountCents((string)$amount) : null;
        $vs = $value(5);
        $movement = $value(22);
        $day = substr((string)$value(0), 0, 10);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day);
        if ($cents === null || $cents <= 0 || $value(14) !== 'CZK' || !is_scalar($vs)
            || !preg_match('/\A[0-9]{10}\z/', (string)$vs) || !is_scalar($movement)
            || !preg_match('/\A[0-9]{1,30}\z/', (string)$movement) || $date === false || $date->format('Y-m-d') !== $day) {
            continue;
        }
        $stmt = $pdo->prepare("SELECT id,total_cents FROM cms_shop_orders WHERE order_number=?
            AND payment_method_id=? AND status='awaiting_payment' AND tax_verified_at IS NOT NULL");
        $stmt->execute([(string)$vs,$methodId]);
        $order = $stmt->fetch();
        if (!is_array($order) || (int)$order['total_cents'] !== $cents) {
            continue;
        }
        try {
            if (shopRecordPayment($pdo, (int)$order['id'], (string)$movement, $day . ' 00:00:00', $methodId)) {
                $count++;
            }
        } catch (DomainException $exception) {
            continue;
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
        }
    }
    return $count;
}

/** @param array<string,mixed> $method */
function shopPollFio(PDO $pdo, array $method): void
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Fio kontrola vyžaduje PHP cURL.');
    }
    $token = shopOpen((string)$method['fio_token_encrypted']);
    if (!preg_match('/\A[A-Za-z0-9]{30,128}\z/', $token)) {
        throw new DomainException('Neplatný formát Fio klíče.');
    }
    $stmt = $pdo->prepare("SELECT MIN(created_at) FROM cms_shop_orders WHERE payment_method_id=? AND status='awaiting_payment'");
    $stmt->execute([$method['id']]);
    $oldest = $stmt->fetchColumn();
    $from = max(time() - 90 * 86400, is_string($oldest) ? (int)strtotime($oldest) - 86400 : time() - 7 * 86400);
    $url = 'https://fioapi.fio.cz/v1/rest/periods/' . $token . '/' . date('Y-m-d', $from) . '/' . date('Y-m-d') . '/transactions.json';
    $curl = curl_init($url);
    $body = '';
    curl_setopt_array($curl, [CURLOPT_FOLLOWLOCATION => false,CURLOPT_CONNECTTIMEOUT => 10,CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYHOST => 2,CURLOPT_SSL_VERIFYPEER => true,CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 5 * 1024 * 1024) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        }]);
    $ok = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($ok === false || $status !== 200) {
        throw new RuntimeException('Fio kontrola není dostupná; ověřte API klíč a spojení.');
    }
    $statement = json_decode($body, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    if (!is_array($statement)) {
        throw new DomainException('Neplatný bankovní výpis.');
    }
    shopApplyFioStatement($pdo, (int)$method['id'], $statement);
}

/** @return list<array<string,mixed>> */
function shopFioMethods(PDO $pdo): array
{
    // Retiring a method closes new checkout, not existing payment instructions.
    return $pdo->query("SELECT m.* FROM cms_shop_payment_methods m
        WHERE m.fio_token_encrypted IS NOT NULL AND m.fio_token_encrypted<>''
        AND (m.is_active=1 OR EXISTS (SELECT 1 FROM cms_shop_orders o
            WHERE o.payment_method_id=m.id AND o.status='awaiting_payment'))
        AND (m.fio_last_polled_at IS NULL OR m.fio_last_polled_at<DATE_SUB(NOW(),INTERVAL 5 MINUTE))
        ORDER BY m.id LIMIT 10")->fetchAll();
}

function shopCron(PDO $pdo): void
{
    if (!isModuleEnabled('shop') || (int)$pdo->query("SELECT GET_LOCK('kora_shop_cron',0)")->fetchColumn() !== 1) {
        return;
    }
    try {
        $methods = shopFioMethods($pdo);
        foreach ($methods as $method) {
            $error = '';
            $pdo->prepare('UPDATE cms_shop_payment_methods SET fio_last_polled_at=NOW() WHERE id=?')->execute([$method['id']]);
            try {
                shopPollFio($pdo, $method);
            } catch (Throwable $exception) {
                $error = 'Kontrola Fio selhala. Ověřte klíč, účet, připojení a rozsah výpisu; token se neloguje.';
            }
            $pdo->prepare('UPDATE cms_shop_payment_methods SET fio_last_error=? WHERE id=?')->execute([$error,$method['id']]);
        }
        $ids = $pdo->query("SELECT id FROM cms_shop_orders WHERE status IN ('accepted','awaiting_payment','paid','fulfilled','cancelled','refunded')
            AND mail_sent_status<>status AND mail_attempts<5 AND (mail_retry_at IS NULL OR mail_retry_at<=NOW())
            AND (mail_claim_until IS NULL OR mail_claim_until<=NOW()) ORDER BY id LIMIT 25")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            shopDispatchOrder($pdo, (int)$id);
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('kora_shop_cron')");
    }
}

function shopRetryMail(PDO $pdo, int $orderId): void
{
    $pdo->beginTransaction();
    try {
        $order = shopLockOrder($pdo, $orderId);
        if ((int)$order['mail_claim_active'] === 1) {
            throw new DomainException('Odesílání právě probíhá. Počkejte na jeho výsledek.');
        }
        if ((int)$order['token_active'] !== 1) {
            throw new DomainException('Platnost odkazu vypršela. Nejprve vyřešte obnovení přístupu se zákazníkem.');
        }
        $pdo->prepare("UPDATE cms_shop_orders SET mail_attempts=0,mail_retry_at=NULL,mail_sent_status='',mail_claim_token=NULL WHERE id=?")
            ->execute([$orderId]);
        shopEvent($pdo, $orderId, 'mail_retry', 'Správce výslovně vyžádal opakování oznámení; platební a fakturační historie se nemění.');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @param array<string,mixed> $data */
function shopImportCatalog(PDO $pdo, array $data): bool
{
    if (!isset($data['shop_categories']) && !isset($data['shop_products']) && !isset($data['shop_tax_rules'])
        && !isset($data['shop_payment_methods'])) {
        return false;
    }
    if (!$pdo->inTransaction()) {
        throw new LogicException('Shop import requires the enclosing import transaction.');
    }
    $categoryMap = [];
    foreach (is_array($data['shop_categories'] ?? null) ? $data['shop_categories'] : [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $slug = shopSlug((string)($row['slug'] ?? $row['name'] ?? ''));
        $name = trim((string)($row['name'] ?? ''));
        if ($slug === '' || $name === '' || mb_strlen($name) > 255) {
            continue;
        }
        $pdo->prepare('INSERT IGNORE INTO cms_shop_categories (name,slug,description,is_active,sort_order) VALUES (?,?,?,0,?)')
            ->execute([$name,$slug,mb_substr((string)($row['description'] ?? ''), 0, 20000),(int)($row['sort_order'] ?? 0)]);
        $stmt = $pdo->prepare('SELECT id FROM cms_shop_categories WHERE slug=?');
        $stmt->execute([$slug]);
        $categoryMap[(int)($row['id'] ?? 0)] = (int)$stmt->fetchColumn();
    }
    foreach (is_array($data['shop_products'] ?? null) ? $data['shop_products'] : [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $category = $categoryMap[(int)($row['category_id'] ?? 0)] ?? 0;
        $slug = shopSlug((string)($row['slug'] ?? $row['title'] ?? ''));
        $title = trim((string)($row['title'] ?? ''));
        $price = filter_var($row['price_cents'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1,'max_range' => 99999999999]]);
        if ($category < 1 || $slug === '' || $title === '' || mb_strlen($title) > 255 || $price === false) {
            continue;
        }
        $pdo->prepare('INSERT IGNORE INTO cms_shop_products
            (category_id,title,slug,description,requirements,license_text,update_policy,price_cents,tax_class,is_active)
            VALUES (?,?,?,?,?,?,?,?,?,0)')->execute([$category,$title,$slug,mb_substr((string)($row['description'] ?? ''), 0, 20000),
            mb_substr((string)($row['requirements'] ?? ''), 0, 10000),mb_substr((string)($row['license_text'] ?? ''), 0, 10000),
            mb_substr((string)($row['update_policy'] ?? ''), 0, 10000),$price,
            ($row['tax_class'] ?? '') === 'publication' ? 'publication' : 'general']);
    }
    foreach (is_array($data['shop_tax_rules'] ?? null) ? $data['shop_tax_rules'] : [] as $row) {
        if (!is_array($row) || !preg_match('/\A[A-Z]{2}\z/', (string)($row['country_code'] ?? ''))) {
            continue;
        }
        $general = filter_var($row['general_rate_bp'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0,'max_range' => 10000]]);
        $publication = filter_var($row['publication_rate_bp'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0,'max_range' => 10000]]);
        if ($general === false || $publication === false) {
            continue;
        }
        $pdo->prepare('INSERT IGNORE INTO cms_shop_tax_rules
            (country_code,country_name,general_rate_bp,publication_rate_bp,tax_note,is_active) VALUES (?,?,?,?,?,0)')
            ->execute([$row['country_code'],mb_substr((string)($row['country_name'] ?? $row['country_code']), 0, 100),$general,$publication,
            mb_substr((string)($row['tax_note'] ?? ''), 0, 10000)]);
    }
    foreach (is_array($data['shop_payment_methods'] ?? null) ? $data['shop_payment_methods'] : [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $iban = shopNormalizeIban((string)($row['iban'] ?? ''));
        if ($iban === '') {
            continue;
        }
        $stmt = $pdo->prepare('SELECT id FROM cms_shop_payment_methods WHERE iban=?');
        $stmt->execute([$iban]);
        if ($stmt->fetchColumn() !== false) {
            continue;
        }
        $pdo->prepare('INSERT INTO cms_shop_payment_methods (name,account_number,iban,is_active) VALUES (?,?,?,0)')
            ->execute([mb_substr((string)($row['name'] ?? 'Bankovní převod'), 0, 255),mb_substr((string)($row['account_number'] ?? $iban), 0, 100),$iban]);
    }
    $pdo->prepare("INSERT INTO cms_settings (`key`,value) VALUES ('shop_ready','0') ON DUPLICATE KEY UPDATE value='0'")->execute();
    unset($GLOBALS['_CMS_SETTINGS']);
    return true;
}
