<?php

declare(strict_types=1);

function shopTestClearSettingsCache(): void
{
    // Remove the actual global slot, not a local alias imported with global.
    unset($GLOBALS['_CMS_SETTINGS']);
}

/**
 * Shared fixture support for shop HTTP tests. Loading this file does not bootstrap
 * the application, connect to a DB, install schema, or execute the MySQL suite.
 */
final class ShopTestFixture
{
    public string $prefix;
    public string $email;
    public string $cjkName;
    public int $categoryId = 0;
    public int $methodId = 0;
    /** @var list<int> */
    public array $productIds = [];
    /** @var list<int> */
    public array $accountIds = [];
    /** @var list<string> */
    public array $countries = [];
    /** @var array<string,string> */
    public array $files = [];
    /** @var array<string,string|null> */
    private array $settings = [];
    /** @var list<string> */
    private array $temporaryTables = [];
    /** @var array<string,mixed> */
    private array $session;
    /** @var array<string,string>|null */
    private ?array $cache;

    public function __construct(private PDO $pdo)
    {
        $this->prefix = 'shop-test-' . bin2hex(random_bytes(12));
        $this->email = $this->prefix . '@example.test';
        $this->cjkName = $this->prefix . ' 李明';
        $this->session = $_SESSION ?? [];
        $this->cache = isset($GLOBALS['_CMS_SETTINGS']) ? $GLOBALS['_CMS_SETTINGS'] : null;
    }

    public function setup(bool $temporary = true, string $baseUrl = 'https://shop.example.test'): void
    {
        if ($temporary) {
            foreach (shopSchema() as $table => $ddl) {
                $this->pdo->exec(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $ddl));
                $this->temporaryTables[] = $table;
            }
            $this->cloneTemporaryTable('cms_users');
            $settingRows = $this->pdo->query('SELECT `key`,value FROM cms_settings')->fetchAll(PDO::FETCH_ASSOC);
            $this->cloneTemporaryTable('cms_settings');
            $insertSetting = $this->pdo->prepare('INSERT INTO cms_settings (`key`,value) VALUES (?,?)');
            foreach ($settingRows as $row) {
                $insertSetting->execute([$row['key'], $row['value']]);
            }
        }
        $values = ['shop_seller_name' => $this->prefix . ' seller', 'shop_seller_address' => 'Test address 1, Praha',
            'shop_seller_ico' => '12345678', 'shop_seller_dic' => 'CZ12345678', 'shop_seller_email' => $this->email,
            'shop_seller_phone' => '+420 123 456 789', 'shop_seller_register' => 'Fixture register', 'shop_vat_mode' => 'vat', 'shop_ready' => '1',
            'shop_terms' => $this->prefix . ' terms', 'shop_privacy' => $this->prefix . ' privacy',
            'shop_complaints' => $this->prefix . ' complaints', 'shop_withdrawal' => $this->prefix . ' withdrawal',
            'site_url' => $baseUrl, 'module_shop' => '1'];
        foreach ($values as $key => $value) {
            if (!$temporary) {
                $stmt = $this->pdo->prepare('SELECT value FROM cms_settings WHERE `key`=?');
                $stmt->execute([$key]);
                $old = $stmt->fetchColumn();
                $this->settings[$key] = $old === false ? null : (string)$old;
            }
            $this->pdo->prepare('INSERT INTO cms_settings (`key`,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)')
                ->execute([$key, $value]);
        }
        shopTestClearSettingsCache();
        $this->pdo->prepare('INSERT INTO cms_shop_categories (name,slug,is_active) VALUES (?,?,1)')
            ->execute([$this->prefix, $this->prefix]);
        $this->categoryId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO cms_shop_payment_methods (name,account_number,iban,is_active) VALUES (?,?,?,1)')
            ->execute([$this->prefix, '19-2000145399/0800', 'CZ6508000000192000145399']);
        $this->methodId = (int)$this->pdo->lastInsertId();
        // Persistent fixtures never overwrite the unique domestic tax rule.
        $countries = ['CZ', 'SK'];
        if (!$temporary) {
            $countries = [];
            foreach (str_split('ZYXWVUTSRQPONMLJIHGFEDCBA') as $letter) {
                $candidate = 'X' . $letter;
                $stmt = $this->pdo->prepare('SELECT id FROM cms_shop_tax_rules WHERE country_code=?');
                $stmt->execute([$candidate]);
                if ($stmt->fetchColumn() === false) {
                    $countries[] = $candidate;
                    break;
                }
            }
            if ($countries === []) {
                throw new RuntimeException('No unused private-use country code for an isolated HTTP fixture');
            }
        }
        foreach ($countries as $country) {
            $this->pdo->prepare('INSERT INTO cms_shop_tax_rules (country_code,country_name,general_rate_bp,publication_rate_bp,tax_note,is_active) VALUES (?,?,2100,0,?,1)')
                ->execute([$country, $this->prefix, $this->prefix . ' tax evidence']);
            $this->countries[] = $country;
        }
        foreach (['general', 'publication'] as $index => $taxClass) {
            $file = $this->createFile($index);
            $this->pdo->prepare('INSERT INTO cms_shop_products (category_id,title,slug,description,requirements,license_text,update_policy,price_cents,tax_class,file_storage_name,file_original_name,file_size,file_sha256,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)')
                ->execute([$this->categoryId, $this->prefix . ' ' . $taxClass, $this->prefix . '-' . $index,
                    'Original description', 'Original requirements', 'Original license', 'Original update policy',
                    $index === 0 ? 12100 : 99, $taxClass, $file['file_storage_name'], $file['file_original_name'],
                    $file['file_size'], $file['file_sha256']]);
            $this->productIds[] = (int)$this->pdo->lastInsertId();
        }
        for ($index = 0; $index < 2; $index++) {
            $this->pdo->prepare("INSERT INTO cms_users (email,password,first_name,role,is_confirmed) VALUES (?,?,?,'public',1)")
                ->execute([$index === 0 ? $this->email : $this->prefix . '-other@example.test',
                    password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), $this->prefix]);
            $this->accountIds[] = (int)$this->pdo->lastInsertId();
        }
    }

    private function cloneTemporaryTable(string $table): void
    {
        if (!in_array($table, ['cms_users', 'cms_settings'], true)) {
            throw new InvalidArgumentException('Unknown temporary fixture table');
        }
        // CREATE TEMPORARY TABLE t LIKE t fails with MySQL alias error 1066.
        $ddl = $this->pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM);
        if (!is_array($ddl) || !is_string($ddl[1] ?? null) || !str_starts_with($ddl[1], 'CREATE TABLE ')) {
            throw new RuntimeException('Cannot obtain installed fixture schema for ' . $table);
        }
        $this->pdo->exec('CREATE TEMPORARY TABLE ' . substr($ddl[1], strlen('CREATE TABLE ')));
        $this->temporaryTables[] = $table;
    }

    /** @return array{file_storage_name:string,file_original_name:string,file_size:int,file_sha256:string} */
    public function createFile(int $index): array
    {
        $bytes = "%PDF-1.4\n% " . $this->prefix . ' file ' . $index . "\n%%EOF\n";
        $hash = hash('sha256', $bytes);
        $path = shopPrivatePath($hash . '.bin');
        $handle = fopen($path, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Cannot exclusively create private shop fixture');
        }
        $this->files[$path] = $bytes;
        try {
            if (fwrite($handle, $bytes) !== strlen($bytes)) {
                throw new RuntimeException('Incomplete shop fixture write');
            }
        } finally {
            fclose($handle);
        }
        return ['file_storage_name' => $hash . '.bin', 'file_original_name' => $this->prefix . '-' . $index . '.pdf',
            'file_size' => strlen($bytes), 'file_sha256' => $hash];
    }

    /** @return array<string,mixed> */
    public function customer(string $country = 'CZ'): array
    {
        return ['customer_name' => $this->prefix, 'email' => $this->email, 'address' => 'Test address 1',
            'city' => 'Praha', 'postal_code' => '11000', 'country_code' => $country,
            'confirm_terms' => '1', 'confirm_digital' => '1'];
    }

    /**
     * @param array<int|string,mixed> $cart
     * @param array<string,mixed> $changes
     * @return array<string,mixed>
     */
    public function createOrder(string $country = 'CZ', array $cart = [], array $changes = []): array
    {
        $cart = $cart === [] ? [$this->productIds[0] => 1] : $cart;
        $customer = $this->customer($country);
        $quote = shopCartQuote($this->pdo, $cart, $country);
        $customer['quote_signature'] = $quote;
        return shopCreateOrder($this->pdo, array_replace($customer, $changes), $cart, $this->methodId);
    }

    /** @return list<array<string,mixed>> */
    public function orders(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cms_shop_orders WHERE email=? AND customer_name IN (?,?) ORDER BY id');
        $stmt->execute([$this->email, $this->prefix, $this->cjkName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed> */
    public function order(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cms_shop_orders WHERE id=? AND email=? AND customer_name IN (?,?)');
        $stmt->execute([$id, $this->email, $this->prefix, $this->cjkName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Order is not owned by this fixture');
        }
        return $row;
    }

    /** @return list<array<string,mixed>> */
    public function orderRows(string $table, int $id): array
    {
        $this->order($id);
        if (!in_array($table, ['cms_shop_order_items', 'cms_shop_order_events', 'cms_shop_invoices', 'cms_shop_payments'], true)) {
            throw new InvalidArgumentException('Unknown order fixture table');
        }
        $stmt = $this->pdo->prepare('SELECT * FROM ' . $table . ' WHERE order_id=? ORDER BY id');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,string> */
    public function evidence(string $country): array
    {
        return ['country' => $country, 'type_one' => 'billing', 'type_two' => 'bank',
            'note' => $this->prefix . ': billing address and independent bank evidence agree.', 'confirm_tax' => '1'];
    }

    public function cleanup(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $errors = [];
        try {
            foreach ($this->orders() as $order) {
                $id = (int)$order['id'];
                foreach (['cms_shop_order_events', 'cms_shop_invoices', 'cms_shop_payments', 'cms_shop_order_items'] as $table) {
                    $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE order_id=?')->execute([$id]);
                }
                $this->pdo->prepare('DELETE FROM cms_shop_orders WHERE id=? AND email=? AND customer_name=?')
                    ->execute([$id, $this->email, $order['customer_name']]);
            }
            foreach ($this->productIds as $index => $id) {
                $this->pdo->prepare('DELETE FROM cms_shop_products WHERE id=? AND category_id=? AND slug=?')
                    ->execute([$id, $this->categoryId, $this->prefix . '-' . $index]);
            }
            $this->pdo->prepare('DELETE FROM cms_shop_categories WHERE id=? AND slug=?')->execute([$this->categoryId, $this->prefix]);
            $this->pdo->prepare('DELETE FROM cms_shop_payment_methods WHERE id=? AND name=?')->execute([$this->methodId, $this->prefix]);
            foreach ($this->countries as $country) {
                $this->pdo->prepare('DELETE FROM cms_shop_tax_rules WHERE country_code=? AND country_name=?')->execute([$country, $this->prefix]);
            }
            foreach ($this->accountIds as $index => $id) {
                $this->pdo->prepare('DELETE FROM cms_users WHERE id=? AND email=?')
                    ->execute([$id, $index === 0 ? $this->email : $this->prefix . '-other@example.test']);
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        } finally {
            // Restoration still runs if any data cleanup fails, including absent keys.
            foreach ($this->settings as $key => $value) {
                try {
                    if ($value === null) {
                        $this->pdo->prepare('DELETE FROM cms_settings WHERE `key`=?')->execute([$key]);
                    } else {
                        saveSetting($key, $value);
                    }
                } catch (Throwable $exception) {
                    $errors[] = 'Setting restoration failed: ' . $key . ': ' . $exception->getMessage();
                }
            }
            shopTestClearSettingsCache();
            if ($this->cache !== null) {
                $GLOBALS['_CMS_SETTINGS'] = $this->cache;
            }
            $_SESSION = $this->session;
            foreach (array_reverse($this->temporaryTables) as $table) {
                try {
                    $this->pdo->exec('DROP TEMPORARY TABLE ' . $table);
                } catch (Throwable $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
            // Never remove a directory or secret.key, only exclusively-created files.
            foreach ($this->files as $path => $bytes) {
                if (!is_link($path) && is_file($path) && file_get_contents($path) === $bytes) {
                    if (!unlink($path)) {
                        $errors[] = 'Private fixture cleanup failed';
                    }
                } else {
                    $errors[] = 'Private fixture changed; cleanup refused';
                }
            }
        }
        if ($errors !== []) {
            throw new RuntimeException(implode('; ', $errors));
        }
    }
}

function shopMysqlSelftest(PDO $pdo): int
{
    $checks = 0;
    $check = static function (bool $condition, string $message) use (&$checks): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
        $checks++;
    };
    $fixture = new ShopTestFixture($pdo);
    $rejects = static function (callable $operation, string $label) use ($check, $fixture, $pdo): void {
        $before = $fixture->orders();
        try {
            $operation();
        } catch (DomainException) {
            $check(!$pdo->inTransaction(), $label . ': no leaked transaction');
            $check($before === $fixture->orders(), $label . ': no order mutation');
            return;
        }
        throw new RuntimeException($label . ': expected DomainException');
    };
    try {
        $fixture->setup();
        unset($_SESSION['cms_user_id']);
        $cart = [$fixture->productIds[0] => 2, $fixture->productIds[1] => 3];
        $quote = shopCartQuote($pdo, $cart, 'CZ');
        $check(preg_match('/\A[a-f0-9]{64}\z/', $quote) === 1, 'Server quote is authenticated');
        $check($quote === shopCartQuote($pdo, array_reverse($cart, true), 'CZ'), 'Quote is independent of cart ordering');
        $check($quote !== shopCartQuote($pdo, [$fixture->productIds[0] => 1], 'CZ'), 'Quote binds exact quantity and items');
        $customer = $fixture->customer();
        $customer['quote_signature'] = $quote;
        $settingUpdate = $pdo->prepare('UPDATE cms_settings SET value=? WHERE `key`=?');
        foreach (['shop_terms' => $fixture->prefix . ' revised terms', 'module_shop' => '0', 'shop_ready' => '0',
            'shop_seller_phone' => '', 'site_url' => 'http://untrusted.example.test'] as $key => $changed) {
            shopTestClearSettingsCache();
            $original = getSetting($key);
            $cachedCustomer = $customer;
            $cachedCustomer['quote_signature'] = shopCartQuote($pdo, $cart, 'CZ');
            $settingUpdate->execute([$changed, $key]);
            $check(getSetting($key) === $original, 'Fixture intentionally retains stale cache for ' . $key);
            $rejects(static fn (): array => shopCreateOrder($pdo, $cachedCustomer, $cart, $fixture->methodId), 'Locked DB settings override stale cache for ' . $key);
            $settingUpdate->execute([$original, $key]);
            shopTestClearSettingsCache();
            $check(shopReady() && isModuleEnabled('shop'), 'Fixture readiness restored after ' . $key);
        }
        foreach (['shop_terms', 'module_shop', 'shop_ready', 'site_url'] as $key) {
            shopTestClearSettingsCache();
            $original = getSetting($key);
            $cachedCustomer = $customer;
            $cachedCustomer['quote_signature'] = shopCartQuote($pdo, $cart, 'CZ');
            $pdo->prepare('DELETE FROM cms_settings WHERE `key`=?')->execute([$key]);
            $check(getSetting($key) === $original, 'Deleted setting deliberately remains in request cache for ' . $key);
            $rejects(static fn (): array => shopCreateOrder($pdo, $cachedCustomer, $cart, $fixture->methodId), 'Deleted DB setting cannot be recovered from stale cache: ' . $key);
            $pdo->prepare('INSERT INTO cms_settings (`key`,value) VALUES (?,?)')->execute([$key, $original]);
            shopTestClearSettingsCache();
            $check(shopReady() && isModuleEnabled('shop'), 'Fixture readiness restored after deleted ' . $key);
        }
        $customer['quote_signature'] = shopCartQuote($pdo, $cart, 'CZ');
        foreach (['customer_name' => '', 'email' => 'bad', 'address' => "bad\naddress", 'city' => [],
            'postal_code' => str_repeat('x', 31), 'confirm_terms' => '', 'confirm_digital' => '',
            'quote_signature' => '', 'country_code' => 'XX'] as $key => $value) {
            $rejects(static fn (): array => shopCreateOrder($pdo, array_replace($customer, [$key => $value]), $cart, $fixture->methodId), 'Invalid customer ' . $key);
        }
        foreach ([[], [$fixture->productIds[0] => 0], [$fixture->productIds[0] => -1], [$fixture->productIds[0] => '1.5'],
            [$fixture->productIds[0] => 101], [$fixture->productIds[0] => []], ['not-an-id' => 1], [PHP_INT_MAX => 1], array_fill_keys(range(1, 21), 1)] as $invalidCart) {
            $rejects(static fn (): array => shopCreateOrder($pdo, $customer, $invalidCart, $fixture->methodId), 'Invalid cart');
        }
        foreach (['confirm_terms', 'confirm_digital'] as $consent) {
            foreach ([true, 1, 'true', ['1']] as $notExplicit) {
                $rejects(static fn (): array => shopCreateOrder($pdo, array_replace($customer, [$consent => $notExplicit]), $cart, $fixture->methodId), 'Consent must be a fresh explicit value');
            }
        }
        $pdo->prepare('UPDATE cms_shop_products SET price_cents=price_cents+1 WHERE id=?')->execute([$fixture->productIds[0]]);
        $rejects(static fn (): array => shopCreateOrder($pdo, $customer, $cart, $fixture->methodId), 'Stale price quote under lock');
        $pdo->prepare('UPDATE cms_shop_products SET price_cents=12100 WHERE id=?')->execute([$fixture->productIds[0]]);
        $taxCustomer = $customer;
        $taxCustomer['quote_signature'] = shopCartQuote($pdo, $cart, 'CZ');
        $pdo->exec("UPDATE cms_shop_tax_rules SET general_rate_bp=1200 WHERE country_code='CZ'");
        $rejects(static fn (): array => shopCreateOrder($pdo, $taxCustomer, $cart, $fixture->methodId), 'Stale tax quote');
        $pdo->exec("UPDATE cms_shop_tax_rules SET general_rate_bp=2100 WHERE country_code='CZ'");
        $productBefore = $pdo->prepare('SELECT * FROM cms_shop_products WHERE id=?');
        $productBefore->execute([$fixture->productIds[0]]);
        $originalProduct = $productBefore->fetch(PDO::FETCH_ASSOC);
        if (!is_array($originalProduct)) {
            throw new RuntimeException('Missing product fixture for same-second quote checks');
        }
        foreach (['title', 'description', 'requirements', 'license_text', 'update_policy', 'file_original_name'] as $field) {
            $textCustomer = $customer;
            $textCustomer['quote_signature'] = shopCartQuote($pdo, $cart, 'CZ');
            $change = $pdo->prepare('UPDATE cms_shop_products SET ' . $field . '=?,updated_at=? WHERE id=?');
            $change->execute(['Changed after review', $originalProduct['updated_at'], $fixture->productIds[0]]);
            $productBefore->execute([$fixture->productIds[0]]);
            $changedProduct = $productBefore->fetch(PDO::FETCH_ASSOC);
            $check(is_array($changedProduct) && $changedProduct['updated_at'] === $originalProduct['updated_at'], 'Same-second fixture keeps timestamp unchanged for ' . $field);
            $rejects(static fn (): array => shopCreateOrder($pdo, $textCustomer, $cart, $fixture->methodId), 'Quote binds actual same-second product content: ' . $field);
            $change->execute([$originalProduct[$field], $originalProduct['updated_at'], $fixture->productIds[0]]);
        }
        $metadataFields = ['description', 'requirements', 'license_text', 'update_policy'];
        $metadataUpdate = $pdo->prepare('UPDATE cms_shop_products SET description=?,requirements=?,license_text=?,update_policy=?,updated_at=? WHERE id=?');
        $longMetadata = [];
        foreach ($metadataFields as $index => $field) {
            $longMetadata[$field] = chr(65 + $index) . str_repeat('ž', 32765) . 'END!';
            $check(strlen($longMetadata[$field]) === 65535, 'Long metadata uses the full valid TEXT byte limit: ' . $field);
        }
        try {
            $oldMetadataCustomer = $customer;
            $oldMetadataCustomer['quote_signature'] = shopCartQuote($pdo, $cart, 'CZ');
            $metadataUpdate->execute([...array_values($longMetadata), $originalProduct['updated_at'], $fixture->productIds[0]]);
            $rejects(static fn (): array => shopCreateOrder($pdo, $oldMetadataCustomer, $cart, $fixture->methodId), 'Long metadata invalidates the previous same-second quote');
            $longOrder = $fixture->createOrder();
            $longId = (int)$longOrder['id'];
            $longItems = shopOrderItems($pdo, $longId);
            $check(strlen((string)$longItems[0]['product_snapshot']) > 4 * 65535, 'JSON snapshot safely exceeds the TEXT capacity with all four full-size fields');
            $decodedMetadata = json_decode((string)$longItems[0]['product_snapshot'], true, 512, JSON_THROW_ON_ERROR);
            foreach ($metadataFields as $field) {
                $check(($decodedMetadata[$field] ?? null) === $longMetadata[$field], 'Long UTF-8 product snapshot has no truncation: ' . $field);
            }
            $check($longOrder['status'] === 'awaiting_payment' && !shopCanDownload($longOrder), 'Long metadata checkout succeeds without dispatching unpaid content');
            $longCustomer = $fixture->customer();
            $longCart = [$fixture->productIds[0] => 1];
            $longCustomer['quote_signature'] = shopCartQuote($pdo, $longCart, 'CZ');
            $changedMetadata = $longMetadata;
            $changedMetadata['license_text'] = substr($changedMetadata['license_text'], 0, -1) . '?';
            $metadataUpdate->execute([...array_values($changedMetadata), $originalProduct['updated_at'], $fixture->productIds[0]]);
            $rejects(static fn (): array => shopCreateOrder($pdo, $longCustomer, $longCart, $fixture->methodId), 'Changing the final byte of a long license invalidates its same-second quote');
            $check(shopOrderItems($pdo, $longId) === $longItems, 'Long purchased metadata remains immutable after catalogue changes');
        } finally {
            $metadataUpdate->execute([$originalProduct['description'], $originalProduct['requirements'], $originalProduct['license_text'],
                $originalProduct['update_policy'], $originalProduct['updated_at'], $fixture->productIds[0]]);
        }
        $pdo->prepare('UPDATE cms_shop_categories SET is_active=0 WHERE id=?')->execute([$fixture->categoryId]);
        $rejects(static fn (): array => shopCreateOrder($pdo, $customer, $cart, $fixture->methodId), 'Inactive category');
        $pdo->prepare('UPDATE cms_shop_categories SET is_active=1 WHERE id=?')->execute([$fixture->categoryId]);
        $pdo->prepare('UPDATE cms_shop_payment_methods SET is_active=0 WHERE id=?')->execute([$fixture->methodId]);
        $rejects(static fn (): array => shopCreateOrder($pdo, $customer, $cart, $fixture->methodId), 'Inactive payment method');
        $pdo->prepare('UPDATE cms_shop_payment_methods SET is_active=1 WHERE id=?')->execute([$fixture->methodId]);
        $pdo->prepare('UPDATE cms_shop_products SET is_active=0 WHERE id=?')->execute([$fixture->productIds[0]]);
        $rejects(static fn (): array => shopCreateOrder($pdo, $customer, $cart, $fixture->methodId), 'Inactive product');
        $pdo->prepare('UPDATE cms_shop_products SET is_active=1,price_cents=0 WHERE id=?')->execute([$fixture->productIds[0]]);
        $rejects(static fn (): array => $fixture->createOrder(), 'Zero-price product rejected');
        $pdo->prepare('UPDATE cms_shop_products SET price_cents=12100 WHERE id=?')->execute([$fixture->productIds[0]]);

        foreach ($fixture->productIds as $productId) {
            $pdo->prepare('UPDATE cms_shop_products SET price_cents=500000000 WHERE id=?')->execute([$productId]);
        }
        $limitCart = [$fixture->productIds[0] => 1, $fixture->productIds[1] => 1];
        $rejects(static fn (): array => $fixture->createOrder('CZ', $limitCart), 'Combined total above SPAYD ceiling rejected before acceptance');
        $pdo->prepare('UPDATE cms_shop_products SET price_cents=499999999 WHERE id=?')->execute([$fixture->productIds[1]]);
        $limitOrder = $fixture->createOrder('CZ', $limitCart);
        $check((int)$limitOrder['total_cents'] === shopMaximumOrderCents(), 'Exact upper payment boundary remains accepted');
        $limitInvoice = shopInvoice($pdo, $limitOrder, 'proforma');
        $limitSnapshot = json_decode((string)$limitInvoice['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        $check(str_contains(shopPaymentSpayd($limitSnapshot), '*AM:9999999.99*'), 'Upper-bound core invoice produces a valid exact SPAYD amount');
        foreach ($fixture->productIds as $index => $productId) {
            $pdo->prepare('UPDATE cms_shop_products SET price_cents=? WHERE id=?')->execute([$index === 0 ? 12100 : 99, $productId]);
        }

        $domesticRates = $pdo->prepare("UPDATE cms_shop_tax_rules SET general_rate_bp=?,publication_rate_bp=? WHERE country_code='CZ'");
        $foreignRates = $pdo->prepare("UPDATE cms_shop_tax_rules SET general_rate_bp=?,publication_rate_bp=? WHERE country_code='SK'");
        $originalDic = getSetting('shop_seller_dic');
        try {
            $settingUpdate->execute(['non_vat_oss', 'shop_vat_mode']);
            shopTestClearSettingsCache();
            $check(shopReady(), 'Non-domestic VAT payer in OSS is a supported configured mode');
            $settingUpdate->execute(['', 'shop_seller_dic']);
            shopTestClearSettingsCache();
            $check(!shopReady(), 'Non-VAT OSS registration requires seller DIC');
            $rejects(static fn (): array => $fixture->createOrder(), 'Non-VAT OSS checkout without DIC fails closed');
            $settingUpdate->execute([$originalDic, 'shop_seller_dic']);
            shopTestClearSettingsCache();
            $check(shopReady(), 'Non-VAT OSS readiness is restored with DIC');
            $rejects(static fn (): array => $fixture->createOrder(), 'Non-VAT OSS rejects a positive Czech general VAT rate');
            $domesticRates->execute([0, 1200]);
            $rejects(static fn (): array => $fixture->createOrder('CZ', [$fixture->productIds[1] => 1]), 'Non-VAT OSS rejects a positive Czech publication VAT rate');
            $domesticRates->execute([0, 0]);
            $domesticOss = $fixture->createOrder('CZ', $cart);
            $domesticOssItems = shopOrderItems($pdo, (int)$domesticOss['id']);
            $check($domesticOss['status'] === 'awaiting_payment' && (int)$domesticOss['tax_cents'] === 0, 'Non-VAT OSS accepts Czech checkout only with zero domestic VAT');
            foreach ($domesticOssItems as $item) {
                $check((int)$item['tax_rate_bp'] === 0 && (int)$item['tax_cents'] === 0, 'Both domestic product classes retain zero VAT in OSS');
            }
            $check(json_decode((string)$domesticOss['seller_snapshot'], true, 512, JSON_THROW_ON_ERROR)['vat_mode'] === 'non_vat_oss', 'Seller snapshot preserves the separate non-VAT OSS registration');
            $foreignRates->execute([2100, 1200]);
            $foreignOss = $fixture->createOrder('SK', $cart);
            $foreignOssId = (int)$foreignOss['id'];
            $check($foreignOss['status'] === 'accepted' && empty($foreignOss['tax_verified_at'])
                && (int)$foreignOss['tax_cents'] === 4232 && !shopCanDownload($foreignOss), 'Non-VAT OSS keeps configured foreign VAT and the manual verification hold');
            $foreignOssItems = shopOrderItems($pdo, $foreignOssId);
            $check((int)$foreignOssItems[0]['tax_rate_bp'] === 2100 && (int)$foreignOssItems[1]['tax_rate_bp'] === 1200, 'Non-VAT OSS does not replace configured foreign rates with domestic zero rates');
            $rejects(static fn (): array => shopInvoice($pdo, $foreignOss, 'proforma'), 'Non-VAT OSS foreign hold has no payment invitation');
            $check(!shopRecordPayment($pdo, $foreignOssId, $fixture->prefix . '-oss-held'), 'Non-VAT OSS cannot be paid before foreign evidence is verified');
            shopVerifyTax($pdo, $foreignOssId, $fixture->evidence('SK'));
            $foreignOssVerified = $fixture->order($foreignOssId);
            $check($foreignOssVerified['status'] === 'awaiting_payment' && !empty($foreignOssVerified['tax_verified_at'])
                && (int)$foreignOssVerified['tax_cents'] === 4232, 'Explicit evidence releases the non-VAT OSS payment gate without changing foreign tax');
            $check(shopInvoice($pdo, $foreignOssVerified, 'proforma')['kind'] === 'proforma'
                && !shopCanDownload($foreignOssVerified), 'Verified non-VAT OSS creates its first proforma but does not deliver');
            $rejects(static fn (): array => $fixture->createOrder('XX'), 'Non-VAT OSS missing foreign country rate never falls back');
            $settingUpdate->execute(['oss', 'shop_vat_mode']);
            $domesticRates->execute([2100, 0]);
            shopTestClearSettingsCache();
            $vatOss = $fixture->createOrder();
            $check((int)$vatOss['tax_cents'] === 2100, 'Existing OSS mode remains a domestic VAT payer rather than non-VAT OSS');
        } finally {
            $settingUpdate->execute(['vat', 'shop_vat_mode']);
            $settingUpdate->execute([$originalDic, 'shop_seller_dic']);
            $domesticRates->execute([2100, 0]);
            $foreignRates->execute([2100, 0]);
            shopTestClearSettingsCache();
        }

        $order = $fixture->createOrder('CZ', $cart, ['user_id' => $fixture->accountIds[0], 'total_cents' => 1]);
        $id = (int)$order['id'];
        $check($order['user_id'] === null, 'Guest is never attached by matching email or posted identity');
        $check((int)$order['total_cents'] === 24497 && (int)$order['tax_cents'] === 4200, 'Totals come from DB, not request');
        $check($order['status'] === 'awaiting_payment' && !shopCanDownload($order), 'Domestic unpaid download denied');
        $check(preg_match('/\A[0-9]{10}\z/', (string)$order['order_number']) === 1, 'Ten-digit order VS');
        $token = shopToken($order);
        $check(strlen($token) === 64 && $order['token_hash'] === hash('sha256', $token), 'Token hash, not raw bearer, in DB');
        $check($order['token_encrypted'] !== $token && shopSeal($token) !== shopSeal($token), 'Random authenticated token encryption');
        $check((int)(shopOrderByToken($pdo, $token)['id'] ?? 0) === $id, 'Valid bearer authorizes its order');
        foreach (['', '../' . $token, strtoupper($token), bin2hex(random_bytes(32)), (string)$id] as $badToken) {
            $check(shopOrderByToken($pdo, $badToken) === null, 'Invalid bearer rejected');
        }
        $pdo->prepare('UPDATE cms_shop_orders SET token_expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?')->execute([$id]);
        $check(shopOrderByToken($pdo, $token) === null, 'Expired bearer rejected');
        $pdo->prepare('UPDATE cms_shop_orders SET token_expires_at=DATE_ADD(NOW(),INTERVAL 1 DAY) WHERE id=?')->execute([$id]);
        $_SESSION['cms_user_id'] = $fixture->accountIds[0];
        $check(shopFindOwnedOrder($pdo, $id) === null, 'Email match does not own a guest order');
        $owned = $fixture->createOrder();
        $check((int)(shopFindOwnedOrder($pdo, (int)$owned['id'])['id'] ?? 0) === (int)$owned['id'], 'Signed-in order belongs to server identity');
        $_SESSION['cms_user_id'] = $fixture->accountIds[1];
        $check(shopFindOwnedOrder($pdo, (int)$owned['id']) === null, 'Another owner cannot read order');
        unset($_SESSION['cms_user_id']);

        $before = $fixture->order($id);
        $items = shopOrderItems($pdo, $id);
        $invoice = shopInvoice($pdo, $before, 'proforma');
        $pdo->prepare('UPDATE cms_shop_products SET title=?,price_cents=1,file_storage_name=?,file_original_name=?,file_size=?,file_sha256=?,license_text=? WHERE id=?')
            ->execute([$fixture->prefix . ' edited', $items[1]['file_storage_name'], 'replacement.pdf', $items[1]['file_size'], $items[1]['file_sha256'], 'New license', $fixture->productIds[0]]);
        $pdo->prepare('UPDATE cms_shop_payment_methods SET name=?,iban=? WHERE id=?')->execute([$fixture->prefix . '-changed', 'CZ6508000000192000145399', $fixture->methodId]);
        $settingUpdate->execute(['Edited terms', 'shop_terms']);
        $settingUpdate->execute(['Edited seller', 'shop_seller_name']);
        shopTestClearSettingsCache();
        $after = $fixture->order($id);
        foreach (['seller_snapshot', 'legal_snapshot', 'payment_snapshot', 'total_cents', 'tax_cents', 'consent_at'] as $field) {
            $check($before[$field] === $after[$field], 'Immutable order snapshot ' . $field);
        }
        $check($items === shopOrderItems($pdo, $id), 'Immutable product/file/price snapshot');
        $check(shopProductFileValid($items[0]), 'Original purchased bytes still validate');
        $check($invoice === shopInvoice($pdo, $after, 'proforma'), 'Immutable proforma snapshot');
        $contract = shopContractHtml(shopContractSnapshot($pdo, $after));
        $check(str_contains($contract, 'Original license') && str_contains($contract, $fixture->prefix . ' terms')
            && !str_contains($contract, 'New license') && !str_contains($contract, 'Edited terms'), 'Immutable contract legal and product details');
        // Restore only our catalogue fixtures for subsequent order creation.
        $pdo->prepare('UPDATE cms_shop_products SET price_cents=12100 WHERE id=?')->execute([$fixture->productIds[0]]);
        $pdo->prepare('UPDATE cms_shop_payment_methods SET name=? WHERE id=?')->execute([$fixture->prefix, $fixture->methodId]);
        $foreign = $fixture->createOrder('SK');
        $foreignId = (int)$foreign['id'];
        $check($foreign['status'] === 'accepted' && empty($foreign['tax_verified_at']) && !shopCanDownload($foreign), 'Foreign order held for evidence');
        $rejects(static fn (): array => shopInvoice($pdo, $foreign, 'proforma'), 'Foreign hold has no premature payment instruction');
        $check(!shopRecordPayment($pdo, $foreignId, $fixture->prefix . '-held'), 'Foreign unverified order cannot be paid');
        $evidence = $fixture->evidence('SK');
        foreach (['country' => 'CZ', 'type_two' => 'billing', 'type_one' => 'customer_declaration', 'note' => 'short', 'confirm_tax' => ''] as $key => $value) {
            $rejects(static fn () => shopVerifyTax($pdo, $foreignId, array_replace($evidence, [$key => $value])), 'Invalid tax evidence ' . $key);
        }
        shopVerifyTax($pdo, $foreignId, $evidence);
        $verified = $fixture->order($foreignId);
        $check($verified['status'] === 'awaiting_payment' && !empty($verified['tax_verified_at']) && !empty($verified['tax_evidence']), 'Explicit independent tax evidence releases payment gate');
        $check(shopInvoice($pdo, $verified, 'proforma')['kind'] === 'proforma', 'Tax verification creates the first foreign payment instruction');
        $check(!shopCanDownload($verified), 'Tax verification alone does not deliver');
        $rejects(static fn (): array => $fixture->createOrder('XX'), 'Missing country rate never falls back');

        $fioOrder = $fixture->createOrder();
        $fioId = (int)$fioOrder['id'];
        $movement = ['column0' => ['value' => date('Y-m-d') . '+0200'], 'column1' => ['value' => '121.00'],
            'column5' => ['value' => $fioOrder['order_number']], 'column14' => ['value' => 'CZK'],
            'column22' => ['value' => (string)random_int(100000000, 999999999)]];
        $statement = ['accountStatement' => ['info' => ['iban' => 'CZ6508000000192000145399', 'currency' => 'CZK'],
            'transactionList' => ['transaction' => [$movement]]]];
        foreach ([[1, '120.99'], [1, '121.01'], [1, '0'], [1, '-121.00'], [1, '121.001'],
            [14, 'EUR'], [5, '9999999999'], [0, '2000-01-01'], [22, 'not-a-bank-id']] as [$column, $value]) {
            $bad = $statement;
            $bad['accountStatement']['transactionList']['transaction'][0]['column' . $column]['value'] = $value;
            $check(shopApplyFioStatement($pdo, $fixture->methodId, $bad) === 0, 'Fio rejects wrong amount/currency/VS/date/id ' . $column);
            $check($fixture->order($fioId)['status'] === 'awaiting_payment', 'Rejected Fio movement never pays');
        }
        $bad = $statement;
        $bad['accountStatement']['info']['iban'] = 'DE89370400440532013000';
        $rejects(static fn (): int => shopApplyFioStatement($pdo, $fixture->methodId, $bad), 'Fio account mismatch');
        $bad = $statement;
        $bad['accountStatement']['info']['currency'] = 'EUR';
        $rejects(static fn (): int => shopApplyFioStatement($pdo, $fixture->methodId, $bad), 'Fio statement currency mismatch');
        $check(shopApplyFioStatement($pdo, $fixture->methodId, $statement) === 1, 'Fio exact movement pays once');
        $fioPaid = $fixture->order($fioId);
        $check(shopApplyFioStatement($pdo, $fixture->methodId, $statement) === 0 && $fixture->order($fioId) === $fioPaid, 'Duplicate Fio statement cannot repay or dispatch');
        $check(count($fixture->orderRows('cms_shop_payments', $fioId)) === 1
            && count($fixture->orderRows('cms_shop_invoices', $fioId)) === 2, 'Fio deduplication leaves one payment and one final invoice');
        $otherFioOrder = $fixture->createOrder();
        $reused = $statement;
        $reused['accountStatement']['transactionList']['transaction'][0]['column5']['value'] = $otherFioOrder['order_number'];
        $check(shopApplyFioStatement($pdo, $fixture->methodId, $reused) === 0
            && $fixture->order((int)$otherFioOrder['id'])['status'] === 'awaiting_payment', 'Movement cannot be reused for a different order');

        $pdo->prepare('INSERT INTO cms_shop_payment_methods (name,account_number,iban,is_active) VALUES (?,?,?,1)')
            ->execute([$fixture->prefix . '-retired', '19-2000145399/0800', 'CZ6508000000192000145399']);
        $retiredMethodId = (int)$pdo->lastInsertId();
        $originalMethodId = $fixture->methodId;
        try {
            $fixture->methodId = $retiredMethodId;
            $retiredOrder = $fixture->createOrder();
            $pdo->prepare('UPDATE cms_shop_payment_methods SET is_active=0 WHERE id=?')->execute([$retiredMethodId]);
            $rejects(static fn (): array => $fixture->createOrder(), 'Retired bank method cannot accept new checkout');
        } finally {
            $fixture->methodId = $originalMethodId;
        }
        $selectedFioIds = static fn (): array => array_map(static fn (array $method): int => (int)$method['id'], shopFioMethods($pdo));
        $check(!in_array($retiredMethodId, $selectedFioIds(), true), 'Retired bank without a token is not polled');
        $pdo->prepare('UPDATE cms_shop_payment_methods SET fio_token_encrypted=? WHERE id=?')
            ->execute([shopSeal(str_repeat('A', 64)), $retiredMethodId]);
        $check(in_array($retiredMethodId, $selectedFioIds(), true), 'Retired bank with an awaiting order remains eligible for Fio');
        $pdo->prepare('UPDATE cms_shop_payment_methods SET fio_last_polled_at=NOW() WHERE id=?')->execute([$retiredMethodId]);
        $check(!in_array($retiredMethodId, $selectedFioIds(), true), 'Retired bank polling respects the five-minute limit');
        $pdo->prepare('UPDATE cms_shop_payment_methods SET fio_last_polled_at=DATE_SUB(NOW(),INTERVAL 6 MINUTE) WHERE id=?')->execute([$retiredMethodId]);
        $check(in_array($retiredMethodId, $selectedFioIds(), true), 'Retired bank becomes eligible after the polling interval');
        $retiredStatement = $statement;
        $retiredStatement['accountStatement']['transactionList']['transaction'][0]['column5']['value'] = $retiredOrder['order_number'];
        $retiredStatement['accountStatement']['transactionList']['transaction'][0]['column22']['value'] = (string)random_int(1000000000, 2000000000);
        $check(shopApplyFioStatement($pdo, $retiredMethodId, $retiredStatement) === 1
            && $fixture->order((int)$retiredOrder['id'])['status'] === 'paid', 'Exact Fio movement pays an existing order on a retired bank method');
        $check(!in_array($retiredMethodId, $selectedFioIds(), true), 'Retired bank stops polling after its last awaiting order is paid');
        $retiredPaid = $fixture->order((int)$retiredOrder['id']);
        $check(shopApplyFioStatement($pdo, $retiredMethodId, $retiredStatement) === 0
            && $fixture->order((int)$retiredOrder['id']) === $retiredPaid, 'Retired bank movement stays idempotent');

        $rejects(static fn (): bool => shopRecordPayment($pdo, $id, $fixture->prefix . '-wrong-method', null, $fixture->methodId + 10000), 'Wrong bank method');
        $rejects(static fn (): bool => shopRecordPayment($pdo, $id, $fixture->prefix . '-old', '2000-01-01 00:00:00'), 'Payment predates order');
        $rejects(static fn () => shopChangeOrder($pdo, $id, 'refund', 'Refund before payment'), 'No unpaid refund');
        $pdo->prepare('UPDATE cms_shop_orders SET created_at=DATE_SUB(NOW(),INTERVAL 300 DAY),token_expires_at=DATE_ADD(NOW(),INTERVAL 2 DAY) WHERE id=?')->execute([$id]);
        $longPending = $fixture->order($id);
        $check(shopRecordPayment($pdo, $id, $fixture->prefix . '-manual'), 'Manual exact payment');
        $check(!shopRecordPayment($pdo, $id, $fixture->prefix . '-manual'), 'Duplicate manual payment is idempotent');
        $check(!shopRecordPayment($pdo, $id, $fixture->prefix . '-different'), 'A second reference cannot repay an order');
        $paid = $fixture->order($id);
        $check($paid['status'] === 'paid' && !shopCanDownload($paid), 'Paid is distinct from fulfilled');
        $rejects(static fn () => shopChangeOrder($pdo, $id, 'cancel'), 'Paid cancellation rejected');
        $finalSnapshot = json_decode((string)shopInvoice($pdo, $paid, 'final')['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        foreach (['token_hash', 'token_encrypted', 'mail_claim_token', 'mail_claim_until', 'mail_last_error', 'mail_attempts',
            'mail_retry_at', 'mail_sent_status', 'mail_claim_active', 'mail_retry_pending', 'token_active', 'tax_evidence',
            'seller_snapshot', 'legal_snapshot', 'payment_snapshot'] as $excludedKey) {
            $check(!array_key_exists($excludedKey, $finalSnapshot['order']), 'Final financial snapshot excludes internal data even when null: ' . $excludedKey);
        }
        $check(str_starts_with(shopInvoicePdf($finalSnapshot), '%PDF-'), 'Actual core final invoice renders before dispatch');
        $check(str_contains(shopInvoiceHtml($finalSnapshot), '<html'), 'Actual core final HTML renders before dispatch');
        $check(str_contains(shopContractHtml(shopContractSnapshot($pdo, $paid)), 'Original license'), 'Actual paid contract renders before dispatch');
        $check(str_contains(shopOrderUrl($paid), '/shop/order.php?token='), 'Actual bearer URL is available before dispatch');

        $calls = [];
        $mailer = static function (string $recipient, string $subject, string $body, array $options) use (&$calls): bool {
            $calls[] = compact('recipient', 'subject', 'body', 'options');
            return true;
        };
        $check(!shopDispatchOrder($pdo, $id, static fn (string $recipient, string $subject, string $body, array $options): bool => false), 'Failed delivery is retryable');
        $failed = $fixture->order($id);
        $check($failed['status'] === 'paid' && !empty($failed['paid_at']) && !empty($failed['mail_retry_at'])
            && !empty($failed['mail_last_error']) && empty($failed['mail_claim_until']) && !shopCanDownload($failed), 'Transport failure preserves payment and schedules retry without delivery');
        $check(!shopDispatchOrder($pdo, $id, $mailer) && $calls === [], 'Retry deadline suppresses early resend');
        $pdo->prepare('UPDATE cms_shop_orders SET mail_retry_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id=?')->execute([$id]);
        $dispatched = shopDispatchOrder($pdo, $id, $mailer);
        $check($dispatched, 'Delivery succeeds with code-only injected mailer (calls=' . count($calls) . ')');
        $fulfilled = $fixture->order($id);
        $check($fulfilled['status'] === 'fulfilled' && shopCanDownload($fulfilled), 'Successful dispatch enables download');
        $deliveryDate = new DateTimeImmutable((string)$fulfilled['fulfilled_at']);
        $check($fulfilled['token_expires_at'] === $deliveryDate->modify('+365 days')->format('Y-m-d H:i:s')
            && $fulfilled['token_expires_at'] > $longPending['token_expires_at'], 'Long payment wait still grants 365 days of access after delivery');
        $sent = count($calls);
        shopDispatchOrder($pdo, $id, $mailer);
        $check(count($calls) === $sent, 'Duplicate dispatch does not send again');
        $check(!shopRecordPayment($pdo, $id, $fixture->prefix . '-after-delivery'), 'Delivered order cannot be paid again');
        $attachments = $calls[0]['options']['attachments'] ?? [];
        $check(
            count($attachments) === 4 && $attachments[0]['content_type'] === 'text/html'
            && $attachments[1]['content_type'] === 'text/html' && $attachments[2]['content_type'] === 'text/plain'
            && $attachments[3]['content_type'] === 'application/pdf' && str_starts_with($attachments[3]['content'], '%PDF-'),
            'Mailer collects permanent contract, accessible HTML/text and optional PDF attachments'
        );
        $check(str_contains($attachments[0]['content'], 'Original license')
            && str_contains($attachments[0]['content'], $fixture->prefix . ' terms'), 'Delivered contract uses original snapshot');
        $financialHistory = [$fixture->orderRows('cms_shop_payments', $id), $fixture->orderRows('cms_shop_invoices', $id)];
        $fulfillEvents = array_values(array_filter(
            $fixture->orderRows('cms_shop_order_events', $id),
            static fn (array $event): bool => $event['event_type'] === 'fulfilled'
        ));
        $pdo->prepare("UPDATE cms_shop_orders SET mail_sent_status='',mail_claim_until=DATE_ADD(NOW(),INTERVAL 10 MINUTE),mail_claim_token=? WHERE id=?")
            ->execute([bin2hex(random_bytes(32)), $id]);
        $rejects(static fn () => shopRetryMail($pdo, $id), 'Admin retry refuses an active mail lease');
        $check(!shopDispatchOrder($pdo, $id, $mailer) && count($calls) === $sent, 'An active lease prevents a second sender');
        $pdo->prepare('UPDATE cms_shop_orders SET mail_claim_until=NULL,mail_claim_token=NULL,token_expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id=?')->execute([$id]);
        $rejects(static fn () => shopRetryMail($pdo, $id), 'Admin retry refuses an expired access token');
        $pdo->prepare('UPDATE cms_shop_orders SET token_expires_at=?,mail_attempts=5,mail_retry_at=DATE_ADD(NOW(),INTERVAL 1 DAY) WHERE id=?')
            ->execute([$fulfilled['token_expires_at'], $id]);
        $check(!shopDispatchOrder($pdo, $id, $mailer) && count($calls) === $sent, 'Attempt limit prevents automatic retries');
        shopRetryMail($pdo, $id);
        $retry = $fixture->order($id);
        $check((int)$retry['mail_attempts'] === 0 && $retry['mail_retry_at'] === null && $retry['mail_sent_status'] === '', 'Explicit admin retry clears attempt limit, sent phase and schedule');
        // Ensure even a same-second TTL rewrite would be visible in this regression test.
        usleep(1100000);
        $check(shopDispatchOrder($pdo, $id, $mailer) && count($calls) === $sent + 1, 'Explicit retry sends a fulfilled notification once');
        $resent = $fixture->order($id);
        $check(
            $resent['status'] === 'fulfilled' && $resent['fulfilled_at'] === $fulfilled['fulfilled_at']
            && $resent['paid_at'] === $fulfilled['paid_at'] && $resent['created_at'] === $longPending['created_at']
            && $resent['token_expires_at'] === $fulfilled['token_expires_at'] && shopToken($resent) === $token,
            'Resend does not extend access, rewrite dates, refulfill, repay or issue a different entitlement'
        );
        $check($financialHistory === [$fixture->orderRows('cms_shop_payments', $id), $fixture->orderRows('cms_shop_invoices', $id)], 'Resend leaves financial documents and payment immutable');
        $check($fulfillEvents === array_values(array_filter(
            $fixture->orderRows('cms_shop_order_events', $id),
            static fn (array $event): bool => $event['event_type'] === 'fulfilled'
        )), 'Resend produces no duplicate fulfillment event');
        $check(!shopDispatchOrder($pdo, $id, $mailer) && count($calls) === $sent + 1, 'Explicit retry is itself idempotent');
        shopChangeOrder($pdo, $id, 'refund', 'Refund actually completed by bank');
        $refunded = $fixture->order($id);
        $check($refunded['status'] === 'refunded' && !shopCanDownload($refunded), 'Refund revokes access');
        $check(shopInvoice($pdo, $refunded, 'credit')['kind'] === 'credit', 'Refund produces credit document');
        $credit = json_decode((string)shopInvoice($pdo, $refunded, 'credit')['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        $check($credit['credit_reason'] === 'Refund actually completed by bank'
            && $credit['original_invoice_number'] === 'FV-' . $refunded['order_number'], 'Credit document snapshots completed-refund reason and original invoice');
        $rejects(static fn () => shopChangeOrder($pdo, $id, 'refund', 'Second refund rejected'), 'No duplicate refund');
        shopChangeOrder($pdo, (int)$owned['id'], 'cancel');
        $check($fixture->order((int)$owned['id'])['status'] === 'cancelled', 'Unpaid cancellation');
        $check(!shopRecordPayment($pdo, (int)$owned['id'], $fixture->prefix . '-cancelled'), 'Cancelled payment rejected');

        $phpTimezone = date_default_timezone_get();
        $clockSession = $_SESSION;
        $mysqlTimezone = $pdo->query('SELECT @@session.time_zone')->fetchColumn();
        if (!is_string($mysqlTimezone)) {
            throw new RuntimeException('Cannot preserve MySQL session time zone');
        }
        try {
            $pdo->exec("SET SESSION time_zone='+00:00'");
            $_SESSION['cms_user_id'] = $fixture->accountIds[0];
            foreach (['Pacific/Kiritimati', 'Etc/GMT+12'] as $timezone) {
                date_default_timezone_set($timezone);
                $sqlClock = $pdo->query('SELECT NOW()')->fetchColumn();
                $misreadClock = is_string($sqlClock) ? strtotime($sqlClock) : false;
                $check($misreadClock !== false && abs($misreadClock - time()) >= 11 * 3600, 'Fixture forces a real PHP/MySQL clock mismatch: ' . $timezone);
                $clockOrder = $fixture->createOrder();
                $clockId = (int)$clockOrder['id'];
                $clockToken = shopToken($clockOrder);
                $clockRead = static function () use ($pdo, $clockId): array {
                    $pdo->beginTransaction();
                    try {
                        return shopLockOrder($pdo, $clockId);
                    } finally {
                        $pdo->rollBack();
                    }
                };
                $clockCalls = 0;
                $transportTransactions = [];
                $transportClaims = [];
                $clockMailer = static function (string $recipient, string $subject, string $body, array $options) use ($pdo, $clockId, &$clockCalls, &$transportTransactions, &$transportClaims): bool {
                    $clockCalls++;
                    $transportTransactions[] = $pdo->inTransaction();
                    if ($pdo->inTransaction()) {
                        $duringSend = shopLockOrder($pdo, $clockId);
                        $transportClaims[] = $duringSend['status'] === 'awaiting_payment'
                            && is_string($duringSend['mail_claim_token']) && $duringSend['mail_claim_token'] !== ''
                            && (int)($duringSend['mail_claim_active'] ?? -1) === 1;
                    }
                    return true;
                };
                $pdo->prepare('UPDATE cms_shop_orders SET mail_claim_until=DATE_ADD(NOW(),INTERVAL 10 MINUTE),mail_claim_token=? WHERE id=?')
                    ->execute([bin2hex(random_bytes(32)), $clockId]);
                $clockLease = $clockRead();
                $check((int)($clockLease['mail_claim_active'] ?? -1) === 1 && (int)($clockLease['mail_retry_pending'] ?? -1) === 0, 'Locked order computes active lease and empty retry from SQL NOW: ' . $timezone);
                $rejects(static fn () => shopRetryMail($pdo, $clockId), 'Active SQL lease refuses admin retry despite PHP clock mismatch: ' . $timezone);
                $check(!shopDispatchOrder($pdo, $clockId, $clockMailer) && $clockCalls === 0, 'Active SQL lease prevents another sender despite PHP clock mismatch: ' . $timezone);
                $pdo->prepare('UPDATE cms_shop_orders SET mail_claim_until=DATE_SUB(NOW(),INTERVAL 1 MINUTE),mail_retry_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE) WHERE id=?')
                    ->execute([$clockId]);
                $clockPending = $clockRead();
                $check((int)($clockPending['mail_claim_active'] ?? -1) === 0 && (int)($clockPending['mail_retry_pending'] ?? -1) === 1, 'Locked order computes expired lease and pending retry from SQL NOW: ' . $timezone);
                $check(!shopDispatchOrder($pdo, $clockId, $clockMailer) && $clockCalls === 0, 'Future SQL retry remains pending despite an elapsed lease and PHP clock mismatch: ' . $timezone);
                $pdo->prepare('UPDATE cms_shop_orders SET mail_retry_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id=?')->execute([$clockId]);
                $check(shopDispatchOrder($pdo, $clockId, $clockMailer) && $clockCalls === 1, 'Elapsed SQL lease and retry allow exactly one sender despite PHP clock mismatch: ' . $timezone);
                $check($transportTransactions === [true] && $transportClaims === [true], 'SMTP boundary keeps the validated order and claim locked until the sent-state commit: ' . $timezone);
                $clockSent = $fixture->order($clockId);
                $check(!$pdo->inTransaction() && $clockSent['mail_sent_status'] === 'awaiting_payment'
                    && $clockSent['mail_claim_token'] === null && $clockSent['mail_claim_until'] === null, 'Transport commits its sent phase and releases the SQL lease: ' . $timezone);
                $check(!shopDispatchOrder($pdo, $clockId, $clockMailer) && $clockCalls === 1, 'Committed phase is not sent twice after a timezone-independent delivery: ' . $timezone);
                $pdo->prepare('UPDATE cms_shop_orders SET mail_claim_until=DATE_SUB(NOW(),INTERVAL 1 MINUTE),mail_claim_token=?,mail_retry_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE),mail_attempts=3 WHERE id=?')
                    ->execute([bin2hex(random_bytes(32)), $clockId]);
                shopRetryMail($pdo, $clockId);
                $clockRetry = $fixture->order($clockId);
                $check((int)$clockRetry['mail_attempts'] === 0 && $clockRetry['mail_retry_at'] === null
                    && $clockRetry['mail_sent_status'] === '' && $clockRetry['mail_claim_token'] === null, 'Admin retry accepts an expired SQL lease and clears pending retry independent of PHP clock: ' . $timezone);
                $check(shopDispatchOrder($pdo, $clockId, $clockMailer) && $clockCalls === 2
                    && $transportTransactions === [true, true] && $transportClaims === [true, true], 'Explicit repeat also holds the order transaction across transport: ' . $timezone);
                $check(count($fixture->orderRows('cms_shop_invoices', $clockId)) === 1
                    && $fixture->orderRows('cms_shop_payments', $clockId) === [], 'Timezone and lease checks cannot duplicate an invoice or fabricate payment: ' . $timezone);
                shopRetryMail($pdo, $clockId);
                $check(!shopDispatchOrder($pdo, $clockId, static fn (string $recipient, string $subject, string $body, array $options): bool => false), 'Failed transport schedules another attempt under mismatched clocks: ' . $timezone);
                $retryDelay = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND,NOW(),mail_retry_at) FROM cms_shop_orders WHERE id=?');
                $retryDelay->execute([$clockId]);
                $sqlDelay = (int)$retryDelay->fetchColumn();
                $clockFailed = $clockRead();
                $check($sqlDelay >= 890 && $sqlDelay <= 900 && (int)($clockFailed['mail_retry_pending'] ?? -1) === 1
                    && (int)($clockFailed['mail_claim_active'] ?? -1) === 0, 'Failed mail schedules the first 900-second retry using SQL time, not PHP time: ' . $timezone);
                $check(!shopDispatchOrder($pdo, $clockId, $clockMailer) && $clockCalls === 2, 'SQL retry deadline suppresses transport after a failure under mismatched clocks: ' . $timezone);
                $pdo->prepare('UPDATE cms_shop_orders SET token_expires_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE) WHERE id=?')->execute([$clockId]);
                $activeBearer = shopOrderByToken($pdo, $clockToken);
                $activeOwner = shopFindOwnedOrder($pdo, $clockId);
                $check(is_array($activeBearer) && (int)$activeBearer['id'] === $clockId && (int)($activeBearer['token_active'] ?? -1) === 1, 'Bearer SELECT accepts a SQL-future token independently of PHP time: ' . $timezone);
                $check(is_array($activeOwner) && (int)$activeOwner['id'] === $clockId && (int)($activeOwner['token_active'] ?? -1) === 1, 'Owner SELECT accepts a SQL-future token independently of PHP time: ' . $timezone);
                $check((int)($clockRead()['token_active'] ?? -1) === 1, 'Locked order computes a SQL-active token under mismatched clocks: ' . $timezone);
                $pdo->prepare('UPDATE cms_shop_orders SET token_expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id=?')->execute([$clockId]);
                $check(shopOrderByToken($pdo, $clockToken) === null, 'Bearer SELECT rejects a SQL-expired token independently of PHP time: ' . $timezone);
                $expiredOwner = shopFindOwnedOrder($pdo, $clockId);
                $check(
                    is_array($expiredOwner) && (int)$expiredOwner['id'] === $clockId && (int)($expiredOwner['token_active'] ?? -1) === 0,
                    'Owner may still read its order, with SQL-expired download entitlement independent of PHP time: ' . $timezone
                );
                $check((int)($clockRead()['token_active'] ?? -1) === 0, 'Locked order computes a SQL-expired token under mismatched clocks: ' . $timezone);
                $rejects(static fn () => shopRetryMail($pdo, $clockId), 'Admin retry rejects SQL-expired access independently of PHP time: ' . $timezone);
            }
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            date_default_timezone_set($phpTimezone);
            $_SESSION = $clockSession;
            $pdo->prepare('SET SESSION time_zone=?')->execute([$mysqlTimezone]);
        }

        $cjk = $fixture->createOrder('SK', [], ['customer_name' => $fixture->cjkName]);
        $cjkId = (int)$cjk['id'];
        shopVerifyTax($pdo, $cjkId, $fixture->evidence('SK'));
        $check(shopRecordPayment($pdo, $cjkId, $fixture->prefix . '-cjk'), 'CJK foreign customer pays after tax verification');
        $cjkCalls = [];
        $check(shopDispatchOrder($pdo, $cjkId, static function (string $recipient, string $subject, string $body, array $options) use (&$cjkCalls): bool {
            $cjkCalls[] = $options;
            return true;
        }), 'Unsupported PDF glyphs cannot block a paid CJK customer');
        $cjkAttachments = $cjkCalls[0]['attachments'] ?? [];
        $check(count($cjkAttachments) === 3 && $cjkAttachments[0]['content_type'] === 'text/html'
            && $cjkAttachments[1]['content_type'] === 'text/html' && $cjkAttachments[2]['content_type'] === 'text/plain', 'CJK fallback sends HTML and text, not a broken PDF');
        foreach ($cjkAttachments as $attachment) {
            $check(str_contains($attachment['content'], '李明'), 'CJK identity survives every permanent accessible attachment');
        }
        $cjkFulfilled = $fixture->order($cjkId);
        $check($cjkFulfilled['status'] === 'fulfilled' && shopCanDownload($cjkFulfilled)
            && $cjkFulfilled['customer_name'] === $fixture->cjkName, 'CJK order is fulfilled without transliteration or lost entitlement');
        $check(count($fixture->orderRows('cms_shop_payments', $cjkId)) === 1
            && count($fixture->orderRows('cms_shop_invoices', $cjkId)) === 2, 'PDF fallback does not duplicate payment or invoice');

        foreach (['accepted', 'awaiting_payment', 'paid'] as $withdrawalPhase) {
            $withdrawOrder = $fixture->createOrder($withdrawalPhase === 'accepted' ? 'SK' : 'CZ');
            $withdrawId = (int)$withdrawOrder['id'];
            if ($withdrawalPhase === 'paid') {
                $check(shopRecordPayment($pdo, $withdrawId, $fixture->prefix . '-withdraw-paid'), 'Paid withdrawal fixture records exactly one actual payment');
            }
            $withdrawBefore = $fixture->order($withdrawId);
            $withdrawFinancial = [$fixture->orderRows('cms_shop_payments', $withdrawId), $fixture->orderRows('cms_shop_invoices', $withdrawId)];
            $check($withdrawBefore['status'] === $withdrawalPhase && shopWithdrawalAvailable($withdrawBefore), 'Withdrawal starts only from an open undelivered state: ' . $withdrawalPhase);
            $check(shopWithdrawalText($pdo, $withdrawBefore) === null, 'No receipt exists before a withdrawal was actually submitted: ' . $withdrawalPhase);
            $receiptClock = (string)$pdo->query("SELECT DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-%dT%H:%i:%sZ')")->fetchColumn();
            shopWithdrawOrder($pdo, $withdrawId);
            $withdrawn = $fixture->order($withdrawId);
            $check($withdrawn['status'] === 'cancelled' && !empty($withdrawn['cancelled_at']) && $withdrawn['fulfilled_at'] === null
                && !shopCanDownload($withdrawn) && !shopWithdrawalAvailable($withdrawn), 'Withdrawal revokes later delivery without introducing a new state: ' . $withdrawalPhase);
            $check($withdrawn['paid_at'] === $withdrawBefore['paid_at'] && $withdrawn['delivery_sent_at'] === null
                && $withdrawFinancial === [$fixture->orderRows('cms_shop_payments', $withdrawId), $fixture->orderRows('cms_shop_invoices', $withdrawId)], 'Withdrawal preserves payment and invoices and never fabricates a refund: ' . $withdrawalPhase);
            $withdrawalEvents = array_values(array_filter(
                $fixture->orderRows('cms_shop_order_events', $withdrawId),
                static fn (array $event): bool => $event['event_type'] === 'withdrawal'
            ));
            $check(count($withdrawalEvents) === 1, 'Withdrawal records a single locked submission event: ' . $withdrawalPhase);
            $receipt = json_decode((string)$withdrawalEvents[0]['note'], true, 512, JSON_THROW_ON_ERROR);
            $receiptNow = (string)$pdo->query("SELECT DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-%dT%H:%i:%sZ')")->fetchColumn();
            $check($receipt['customer_name'] === $withdrawBefore['customer_name'] && $receipt['email'] === $withdrawBefore['email']
                && $receipt['order_number'] === $withdrawBefore['order_number'] && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z\z/', $receipt['submitted_at']) === 1
                && $receipt['submitted_at'] >= $receiptClock && $receipt['submitted_at'] <= $receiptNow, 'Receipt stores actual order identity and SQL UTC submission time: ' . $withdrawalPhase);
            shopWithdrawOrder($pdo, $withdrawId);
            $check($fixture->order($withdrawId) === $withdrawn && array_values(array_filter(
                $fixture->orderRows('cms_shop_order_events', $withdrawId),
                static fn (array $event): bool => $event['event_type'] === 'withdrawal'
            )) === $withdrawalEvents, 'Repeated withdrawal preserves the original timestamp and acknowledgement event: ' . $withdrawalPhase);
            $receiptText = shopWithdrawalText($pdo, $withdrawn);
            $check(is_string($receiptText) && str_contains($receiptText, $receipt['submitted_at']) && str_contains($receiptText, $receipt['email']), 'Permanent withdrawal text contains the saved receipt identity: ' . $withdrawalPhase);
            $check(!shopRecordPayment($pdo, $withdrawId, $fixture->prefix . '-withdraw-late-' . $withdrawalPhase), 'Late payment cannot reopen a withdrawn order: ' . $withdrawalPhase);
            $check(!shopDispatchOrder($pdo, $withdrawId, static fn (string $recipient, string $subject, string $body, array $options): bool => false), 'Withdrawal acknowledgement transport failure remains retryable: ' . $withdrawalPhase);
            shopRetryMail($pdo, $withdrawId);
            $receiptCalls = [];
            $check(shopDispatchOrder($pdo, $withdrawId, static function (string $recipient, string $subject, string $body, array $options) use ($pdo, &$receiptCalls): bool {
                $receiptCalls[] = ['recipient' => $recipient, 'body' => $body, 'options' => $options, 'transaction' => $pdo->inTransaction()];
                return true;
            }), 'Withdrawal acknowledgement is sent through the injected locked mailer: ' . $withdrawalPhase);
            $receiptAttachments = $receiptCalls[0]['options']['attachments'] ?? [];
            $check(count($receiptCalls) === 1 && $receiptCalls[0]['transaction'] && $receiptCalls[0]['recipient'] === $fixture->email
                && count($receiptAttachments) === 3 && $receiptAttachments[0]['content_type'] === 'text/html'
                && $receiptAttachments[1]['content_type'] === 'text/html' && $receiptAttachments[2]['content_type'] === 'text/plain', 'Cancellation mail includes mandatory contract and accessible HTML/TXT acknowledgement, not a payment invoice: ' . $withdrawalPhase);
            foreach (array_slice($receiptAttachments, 1) as $attachment) {
                $check(str_contains($attachment['content'], $receipt['customer_name']) && str_contains($attachment['content'], $receipt['email'])
                    && str_contains($attachment['content'], $receipt['order_number']) && str_contains($attachment['content'], $receipt['submitted_at'])
                    && !str_contains($attachment['content'], 'SPD*1.0') && !str_contains($attachment['content'], 'qr kód k platbě'), 'Acknowledgement preserves the immutable receipt and never requests payment: ' . $withdrawalPhase);
            }
            $withdrawDelivered = $fixture->order($withdrawId);
            $check($withdrawDelivered['status'] === 'cancelled' && $withdrawDelivered['mail_sent_status'] === 'cancelled'
                && $withdrawDelivered['fulfilled_at'] === null && $withdrawDelivered['delivery_sent_at'] === null && !shopCanDownload($withdrawDelivered)
                && shopWithdrawalText($pdo, $withdrawDelivered) === $receiptText, 'Sending the receipt never fulfills or grants content, and leaves receipt data immutable: ' . $withdrawalPhase);
            $check(!shopDispatchOrder($pdo, $withdrawId, static function (string $recipient, string $subject, string $body, array $options) use (&$receiptCalls): bool {
                $receiptCalls[] = ['body' => $body];
                return true;
            }) && count($receiptCalls) === 1, 'Duplicate receipt dispatch cannot send again: ' . $withdrawalPhase);
            if ($withdrawalPhase === 'paid') {
                $rejects(static fn () => shopChangeOrder($pdo, $withdrawId, 'refund', 'short'), 'Paid withdrawal cannot claim a completed refund without an explicit sufficient note');
                $check(str_contains((string)$receiptText, 'potvrzení peníze nepřevádí'), 'Paid acknowledgement explicitly says the receipt does not transfer funds');
                $refundNote = 'Actual bank refund completed after customer withdrawal';
                shopChangeOrder($pdo, $withdrawId, 'refund', $refundNote);
                $withdrawRefunded = $fixture->order($withdrawId);
                $refundSnapshot = json_decode((string)shopInvoice($pdo, $withdrawRefunded, 'credit')['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
                $check($withdrawRefunded['status'] === 'refunded' && $withdrawRefunded['paid_at'] === $withdrawBefore['paid_at']
                    && !shopCanDownload($withdrawRefunded) && $refundSnapshot['credit_reason'] === $refundNote, 'Only a subsequent explicit completed refund creates the corrective document');
                $check(count($fixture->orderRows('cms_shop_payments', $withdrawId)) === 1
                    && count($fixture->orderRows('cms_shop_invoices', $withdrawId)) === 3, 'Paid withdrawal and actual refund leave one payment, one final invoice and one credit');
                shopWithdrawOrder($pdo, $withdrawId);
                $check($fixture->order($withdrawId) === $withdrawRefunded && shopWithdrawalText($pdo, $withdrawRefunded) === $receiptText, 'Repeated original withdrawal remains idempotent even after actual refund');
                $rejects(static fn () => shopChangeOrder($pdo, $withdrawId, 'refund', $refundNote), 'Withdrawn paid order cannot be refunded twice');
            } else {
                $rejects(static fn () => shopChangeOrder($pdo, $withdrawId, 'refund', 'No money was ever received'), 'Unpaid withdrawal never creates a forged refund: ' . $withdrawalPhase);
            }
        }
        $rejects(static fn () => shopWithdrawOrder($pdo, $cjkId), 'Already fulfilled content cannot start withdrawal');
        $rejects(static fn () => shopWithdrawOrder($pdo, $id), 'Already refunded order without a prior withdrawal cannot start withdrawal');
        $rejects(static fn () => shopWithdrawOrder($pdo, (int)$owned['id']), 'Administrative cancellation without a prior withdrawal cannot manufacture a receipt');
    } finally {
        $fixture->cleanup();
    }
    return $checks;
}

// Explicit opt-in is required even though shop data itself uses temporary tables.
if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    if (($argv[1] ?? '') !== '--allow-db') {
        fwrite(STDERR, "After coordination: php build/shop_mysql_selftest.php --allow-db\n");
        exit(2);
    }
    require_once dirname(__DIR__) . '/db.php';
    require_once dirname(__DIR__) . '/lib/shop.php';
    require_once dirname(__DIR__) . '/lib/shop_invoice.php';
    try {
        $pdo = db_connect();
        $pdo->exec('SET SESSION innodb_lock_wait_timeout=8');
        echo 'Digital shop MySQL selftest: ' . shopMysqlSelftest($pdo) . " checks passed.\n";
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Digital shop MySQL selftest failed: ' . $exception->getMessage() . "\n");
        foreach (array_slice($exception->getTrace(), 0, 3) as $frame) {
            fwrite(STDERR, $frame['function'] . ' at ' . basename((string)($frame['file'] ?? '')) . ':' . (string)($frame['line'] ?? '') . "\n");
        }
        exit(1);
    }
}
