<?php
require_once __DIR__ . '/../db.php';
$requestMethod = requireHttpMethods(['GET', 'HEAD', 'POST']);
requireSuperAdmin();
requireModuleEnabled('shop');
require_once __DIR__ . '/../lib/shop.php';
require_once __DIR__ . '/../lib/shop_invoice.php';
require_once __DIR__ . '/layout.php';
shopSafeHeaders();

$pdo = db_connect();
$settingsUrl = BASE_URL . '/admin/shop_settings.php';
$paymentId = inputInt('get', 'edit_payment');
$taxId = inputInt('get', 'edit_tax');
$sellerFields = [
    'seller_name' => 'Jméno nebo obchodní firma', 'seller_address' => 'Úplná adresa prodejce',
    'seller_ico' => 'IČO', 'seller_dic' => 'DIČ', 'seller_email' => 'Kontaktní e-mail',
    'seller_phone' => 'Telefon prodejce',
    'seller_register' => 'Zápis v rejstříku nebo údaj o nezapsání',
];
$legalFields = [
    'terms' => 'Obchodní podmínky', 'privacy' => 'Ochrana soukromí',
    'complaints' => 'Reklamace a práva z vad', 'withdrawal' => 'Odstoupení a dodání digitálního obsahu',
];
$sellerValues = array_replace(array_fill_keys(array_merge(array_keys($sellerFields), array_keys($legalFields)), ''), shopSettings());
$sellerValues['vat_mode'] = $sellerValues['vat_mode'] ?? 'non_vat';
$sellerValues['ready'] = $sellerValues['ready'] ?? '0';
$paymentValues = ['name' => '', 'account_number' => '', 'iban' => '', 'is_active' => '0'];
$taxValues = ['country_code' => '', 'country_name' => '', 'general_rate' => '', 'publication_rate' => '', 'tax_note' => '', 'is_active' => '0'];
$payment = null;
$taxRule = null;
if ($paymentId !== null) {
    $stmt = $pdo->prepare("SELECT id, name, account_number, iban, is_active,
        CASE WHEN fio_token_encrypted IS NOT NULL AND fio_token_encrypted <> '' THEN 1 ELSE 0 END AS has_fio_token
        , (SELECT COUNT(*) FROM cms_shop_orders o WHERE o.payment_method_id = cms_shop_payment_methods.id) AS order_count
        FROM cms_shop_payment_methods WHERE id = ?");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch() ?: null;
    if ($payment !== null) {
        foreach ($paymentValues as $field => $default) {
            $paymentValues[$field] = (string)$payment[$field];
        }
    }
}
if ($taxId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM cms_shop_tax_rules WHERE id = ?');
    $stmt->execute([$taxId]);
    $taxRule = $stmt->fetch() ?: null;
    if ($taxRule !== null) {
        foreach ($taxValues as $field => $default) {
            if ($field === 'general_rate' || $field === 'publication_rate') {
                $rateBp = (int)$taxRule[$field . '_bp'];
                $taxValues[$field] = intdiv($rateBp, 100) . ',' . str_pad((string)($rateBp % 100), 2, '0', STR_PAD_LEFT);
            } else {
                $taxValues[$field] = (string)$taxRule[$field];
            }
        }
    }
}
if (($paymentId !== null && $payment === null) || ($taxId !== null && $taxRule === null)) {
    http_response_code(404);
    if ($requestMethod === 'HEAD') {
        exit;
    }
    adminHeader('Nastavení nenalezeno');
    echo '<p class="error">Požadovaná platební metoda nebo daňové pravidlo neexistuje.</p><p><a href="shop_settings.php">Zpět na nastavení</a></p>';
    adminFooter();
    exit;
}
if ($requestMethod === 'HEAD') {
    exit;
}
$sellerErrors = [];
$paymentErrors = [];
$taxErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $errors = [];
    $message = '';
    $values = [];
    $newToken = '';
    $removeToken = false;
    $rates = ['general_rate' => null, 'publication_rate' => null];
    $scopeKey = 'seller';
    $target = $settingsUrl;
    if ($action === 'seller') {
        $values = $sellerValues;
        foreach (array_merge(array_keys($sellerFields), array_keys($legalFields), ['vat_mode']) as $field) {
            $values[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
        }
        $values['ready'] = ($_POST['ready'] ?? '') === '1' ? '1' : '0';
        foreach ($sellerFields + $legalFields as $field => $label) {
            if ($field === 'seller_dic' && $values['vat_mode'] === 'non_vat') {
                continue;
            }
            if ($values[$field] === '') {
                $errors[$field] = 'Doplňte údaj: ' . $label . '.';
            } elseif (strlen($values[$field]) > 65535 || mb_strlen($values[$field]) > (isset($legalFields[$field]) ? 50000 : 5000)) {
                $errors[$field] = 'Zadaný text je příliš dlouhý.';
            }
        }
        if (!filter_var($values['seller_email'], FILTER_VALIDATE_EMAIL) || strlen($values['seller_email']) > 254) {
            $errors['seller_email'] = 'Doplňte platnou kontaktní e-mailovou adresu.';
        }
        if (!in_array($values['vat_mode'], ['non_vat', 'vat', 'oss', 'non_vat_oss'], true)) {
            $errors['vat_mode'] = 'Vyberte jeden z nabízených režimů prodejce.';
        }
        if ($values['ready'] === '1' && ($_POST['confirm_ready'] ?? '') !== '1') {
            $errors['confirm_ready'] = 'Před povolením prodeje znovu výslovně potvrďte kontrolu všech nastavení.';
        }
        if ($values['ready'] === '1') {
            $methods = $pdo->query('SELECT iban FROM cms_shop_payment_methods WHERE is_active = 1')->fetchAll();
            $validBank = false;
            foreach ($methods as $method) {
                try {
                    if (shopNormalizeIban((string)$method['iban']) !== '') {
                        $validBank = true;
                    }
                } catch (Throwable $exception) {
                    $errors['ready'] = 'Opravte IBAN u všech aktivních platebních metod.';
                }
            }
            if (!$validBank) {
                $errors['ready'] = 'Prodej vyžaduje alespoň jednu aktivní platební metodu s platným IBAN.';
            }
            if ((int)$pdo->query('SELECT COUNT(*) FROM cms_shop_tax_rules WHERE is_active = 1')->fetchColumn() === 0) {
                $errors['ready'] = 'Prodej vyžaduje alespoň jedno výslovně aktivní daňové pravidlo země.';
            }
            if (in_array($values['vat_mode'], ['non_vat', 'non_vat_oss'], true)
                && (int)$pdo->query("SELECT COUNT(*) FROM cms_shop_tax_rules WHERE country_code = 'CZ' AND is_active = 1
                    AND (general_rate_bp <> 0 OR publication_rate_bp <> 0)")->fetchColumn() > 0) {
                $errors['vat_mode'] = 'Tuzemský neplátce, včetně identifikované osoby v OSS, vyžaduje obě české sazby 0 %. Nejprve upravte pravidlo CZ.';
            }
        }
    } elseif ($action === 'payment') {
        $scopeKey = 'payment:' . ($paymentId ?? 'new');
        $target .= $paymentId !== null ? '?edit_payment=' . $paymentId : '';
        foreach ($paymentValues as $field => $default) {
            $values[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
        }
        $values['is_active'] = ($_POST['is_active'] ?? '') === '1' ? '1' : '0';
        if ($values['name'] === '' || mb_strlen($values['name']) > 190) {
            $errors['name'] = 'Doplňte název platební metody, nejvýše 190 znaků.';
        }
        if (mb_strlen($values['account_number']) > 100) {
            $errors['account_number'] = 'Označení účtu může mít nejvýše 100 znaků.';
        }
        try {
            $values['iban'] = shopNormalizeIban($values['iban'] !== '' ? $values['iban'] : $values['account_number']);
            if ($values['iban'] === '') {
                $errors['iban'] = 'Doplňte platný český nebo zahraniční IBAN.';
            }
        } catch (Throwable $exception) {
            $errors['iban'] = 'IBAN nebo české číslo účtu není platné. Zkontrolujte účet včetně kódu banky.';
        }
        $newToken = is_string($_POST['fio_token'] ?? null) ? trim($_POST['fio_token']) : '';
        $removeToken = ($_POST['confirm_remove_token'] ?? '') === '1';
        if ($newToken !== '' && preg_match('/\A[A-Za-z0-9]{30,128}\z/', $newToken) !== 1) {
            $errors['fio_token'] = 'Fio token musí mít 30 až 128 alfanumerických znaků bez mezer.';
        }
        if ($newToken !== '' && $removeToken) {
            $errors['confirm_remove_token'] = 'Zvolte buď nový token, nebo odstranění tokenu, nikoli obě akce současně.';
        }
    } elseif ($action === 'tax') {
        $scopeKey = 'tax:' . ($taxId ?? 'new');
        $target .= $taxId !== null ? '?edit_tax=' . $taxId : '';
        foreach ($taxValues as $field => $default) {
            $values[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
        }
        $values['is_active'] = ($_POST['is_active'] ?? '') === '1' ? '1' : '0';
        $values['country_code'] = strtoupper($values['country_code']);
        if (!preg_match('/^[A-Z]{2}$/D', $values['country_code'])) {
            $errors['country_code'] = 'Zadejte dvoupísmenný kód země ISO 3166-1, například CZ.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM cms_shop_tax_rules WHERE country_code = ? AND id <> ?');
            $stmt->execute([$values['country_code'], $taxId ?? 0]);
            if ($stmt->fetchColumn() !== false) {
                $errors['country_code'] = 'Pro tuto zemi už pravidlo existuje. Upravte existující pravidlo.';
            }
        }
        if ($values['country_name'] === '' || mb_strlen($values['country_name']) > 100) {
            $errors['country_name'] = 'Doplňte název země, nejvýše 100 znaků.';
        }
        foreach (['general_rate', 'publication_rate'] as $field) {
            $rates[$field] = shopAmountCents($values[$field]);
            if ($rates[$field] === null || $rates[$field] > 10000) {
                $errors[$field] = 'Zadejte sazbu od 0 do 100 % s nejvýše dvěma desetinnými místy.';
            } elseif ($values['country_code'] === 'CZ' && in_array($sellerValues['vat_mode'], ['non_vat', 'non_vat_oss'], true)
                && $rates[$field] !== 0) {
                $errors[$field] = 'Tuzemský neplátce, včetně identifikované osoby v OSS, musí mít českou sazbu 0 %.';
            }
        }
        if (mb_strlen($values['tax_note']) < 20 || mb_strlen($values['tax_note']) > 10000) {
            $errors['tax_note'] = 'Doplňte vysvětlení režimu a sazeb pro tuto zemi (20 až 10 000 znaků).';
        }
        if ($values['country_code'] !== 'CZ' && ($_POST['confirm_tax_rule'] ?? '') !== '1') {
            $errors['confirm_tax_rule'] = 'Zahraniční pravidlo vyžaduje nové výslovné potvrzení ověření režimu, sazeb a případných místních povinností.';
        }
    } else {
        $message = 'Neznámá akce. Žádné nastavení nebylo změněno.';
    }
    if ($errors === [] && $message === '') {
        try {
            $pdo->beginTransaction();
            // Checkout locks this gate before reading settings, bank and tax snapshots.
            saveSetting('shop_ready', '0');
            if ($action === 'seller') {
                foreach (array_merge(array_keys($sellerFields), array_keys($legalFields), ['vat_mode']) as $field) {
                    saveSetting('shop_' . $field, $values[$field]);
                }
                saveSetting('shop_ready', $values['ready']);
                if ($values['ready'] === '1' && !shopReady()) {
                    throw new DomainException('Nastavení ještě nesplňuje podmínky jádra pro bezpečný prodej. Zkontrolujte identitu, právní texty, banku, pravidla zemí a důvěryhodnou HTTPS site_url v hlavním nastavení CMS.');
                }
            } elseif ($action === 'payment') {
                $encryptedToken = null;
                if ($newToken !== '') {
                    $encryptedToken = shopSeal($newToken);
                    if ($encryptedToken === '') {
                        throw new RuntimeException('Encryption unavailable');
                    }
                } elseif ($removeToken) {
                    $encryptedToken = '';
                }
                $data = [$values['name'], $values['account_number'], $values['iban'], (int)$values['is_active']];
                if ($paymentId !== null) {
                    $stmt = $pdo->prepare('SELECT id, account_number, iban FROM cms_shop_payment_methods WHERE id = ? FOR UPDATE');
                    $stmt->execute([$paymentId]);
                    $lockedMethod = $stmt->fetch();
                    if (!$lockedMethod) {
                        throw new DomainException('Platební metoda už neexistuje.');
                    }
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM cms_shop_orders WHERE payment_method_id = ?');
                    $stmt->execute([$paymentId]);
                    if ((int)$stmt->fetchColumn() > 0 && ($values['account_number'] !== (string)$lockedMethod['account_number']
                        || $values['iban'] !== (string)$lockedMethod['iban'])) {
                        throw new DomainException('Účet ani IBAN metody s existujícími objednávkami nelze změnit. Pro nový účet vytvořte novou platební metodu; token lze změnit samostatně.');
                    }
                    $tokenSql = '';
                    if ($encryptedToken !== null) {
                        $tokenSql = ', fio_token_encrypted = ?';
                        $data[] = $encryptedToken;
                    }
                    $data[] = $paymentId;
                    $pdo->prepare('UPDATE cms_shop_payment_methods SET name = ?, account_number = ?, iban = ?, is_active = ?' . $tokenSql . ' WHERE id = ?')->execute($data);
                } else {
                    $data[] = $encryptedToken ?? '';
                    $pdo->prepare('INSERT INTO cms_shop_payment_methods (name, account_number, iban, is_active, fio_token_encrypted, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())')->execute($data);
                }
            } elseif ($action === 'tax') {
                $data = [$values['country_code'], $values['country_name'], $rates['general_rate'], $rates['publication_rate'], $values['tax_note'], (int)$values['is_active']];
                if ($taxId !== null) {
                    $stmt = $pdo->prepare('SELECT id FROM cms_shop_tax_rules WHERE id = ? FOR UPDATE');
                    $stmt->execute([$taxId]);
                    if ($stmt->fetchColumn() === false) {
                        throw new DomainException('Daňové pravidlo už neexistuje.');
                    }
                    $data[] = $taxId;
                    $pdo->prepare('UPDATE cms_shop_tax_rules SET country_code = ?, country_name = ?, general_rate_bp = ?, publication_rate_bp = ?, tax_note = ?, is_active = ? WHERE id = ?')->execute($data);
                } else {
                    $pdo->prepare('INSERT INTO cms_shop_tax_rules (country_code, country_name, general_rate_bp, publication_rate_bp, tax_note, is_active) VALUES (?, ?, ?, ?, ?, ?)')->execute($data);
                }
            }
            $pdo->commit();
            unset($_SESSION['shop_admin_settings_flash'][$scopeKey]);
            $_SESSION['shop_admin_settings_message'] = $action === 'seller'
                ? ($values['ready'] === '1' ? 'Nastavení bylo uloženo a připravenost prodeje potvrzena.' : 'Nastavení bylo uloženo. Prodej zůstává pozastavený.')
                : 'Nastavení bylo uloženo. Změna banky nebo daňového pravidla pozastavila prodej; znovu ověřte a potvrďte připravenost.';
            header('Location: ' . internalRedirectTarget($settingsUrl), true, 303);
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            clearSettingsCache();
            if ($exception instanceof PDOException && (string)$exception->getCode() === '23000' && $action === 'tax') {
                $errors['country_code'] = 'Pravidlo pro tuto zemi již existuje.';
            } elseif ($exception instanceof DomainException && $action === 'seller') {
                $errors['ready'] = $exception->getMessage();
            } elseif ($exception instanceof DomainException && $action === 'payment') {
                $errors['iban'] = $exception->getMessage();
            } else {
                $message = 'Nastavení se nepodařilo bezpečně uložit. Běžné údaje byly zachovány; token případně zadejte znovu.';
            }
        }
    }
    // Deliberately omit Fio credentials and all confirmation fields from PRG recovery.
    $_SESSION['shop_admin_settings_flash'][$scopeKey] = ['values' => $values, 'errors' => $errors, 'message' => $message];
    header('Location: ' . internalRedirectTarget($target), true, 303);
    exit;
}
$messages = [];
foreach (['seller' => 'seller', 'payment' => 'payment:' . ($paymentId ?? 'new'), 'tax' => 'tax:' . ($taxId ?? 'new')] as $section => $scopeKey) {
    $flash = $_SESSION['shop_admin_settings_flash'][$scopeKey] ?? null;
    unset($_SESSION['shop_admin_settings_flash'][$scopeKey]);
    if (!is_array($flash)) {
        continue;
    }
    if ($section === 'seller') {
        $sellerValues = array_replace($sellerValues, $flash['values'] ?? []);
        $sellerErrors = $flash['errors'] ?? [];
    } elseif ($section === 'payment') {
        $paymentValues = array_replace($paymentValues, $flash['values'] ?? []);
        $paymentErrors = $flash['errors'] ?? [];
    } else {
        $taxValues = array_replace($taxValues, $flash['values'] ?? []);
        $taxErrors = $flash['errors'] ?? [];
    }
    if (!empty($flash['message'])) {
        $messages[] = (string)$flash['message'];
    }
}
$success = $_SESSION['shop_admin_settings_message'] ?? '';
unset($_SESSION['shop_admin_settings_message']);
if (!empty($payment['order_count'])) {
    $paymentValues['account_number'] = (string)$payment['account_number'];
    $paymentValues['iban'] = (string)$payment['iban'];
}
$sectionErrors = ['seller' => $sellerErrors, 'payment' => $paymentErrors, 'tax' => $taxErrors];
$attributes = static function (string $section, string $field, array $help = []) use ($sectionErrors): string {
    return adminFieldAttributes($field, array_keys($sectionErrors[$section]), [], $help, $section . '-' . $field . '-error');
};
$renderError = static function (string $section, string $field) use ($sectionErrors): void {
    adminRenderFieldError($field, array_keys($sectionErrors[$section]), [], $sectionErrors[$section][$field] ?? '', $section . '-' . $field . '-error');
};
$paymentMethods = $pdo->query("SELECT id, name, account_number, iban, is_active, fio_last_polled_at, fio_last_error,
    CASE WHEN fio_token_encrypted IS NOT NULL AND fio_token_encrypted <> '' THEN 1 ELSE 0 END AS has_fio_token
    FROM cms_shop_payment_methods ORDER BY id")->fetchAll();
$taxRules = $pdo->query('SELECT * FROM cms_shop_tax_rules ORDER BY country_code, id')->fetchAll();
$paymentUrl = $settingsUrl . ($paymentId !== null ? '?edit_payment=' . $paymentId : '');
$taxUrl = $settingsUrl . ($taxId !== null ? '?edit_tax=' . $taxId : '');
adminHeader('Nastavení digitálního obchodu');
?>
<p class="button-row"><a href="shop.php">Produkty</a><a href="shop_categories.php">Kategorie</a><a href="shop_orders.php">Objednávky</a></p>
<?php if (is_string($success) && $success !== ''): ?><p class="success" role="status"><?= h($success) ?></p><?php endif; ?>
<?php if ($messages !== [] || $sellerErrors !== [] || $paymentErrors !== [] || $taxErrors !== []): ?>
  <div class="error" role="alert">
    <p>Nastavení nebylo uloženo. Opravte označená pole; potvrzení i nový bankovní token je nutné zadat znovu.</p>
    <?php foreach ($messages as $message): ?><p><?= h($message) ?></p><?php endforeach; ?>
    <ul><?php foreach ($sectionErrors as $section => $errors): ?><?php foreach ($errors as $field => $error): ?><li><a href="#<?= h($section . '-' . $field) ?>"><?= h($error) ?></a></li><?php endforeach; ?><?php endforeach; ?></ul>
  </div>
<?php endif; ?>
<p class="admin-warning-box">Stav prodeje: <strong><?= shopReady() ? 'Připravený' : 'Nepřipravený / pozastavený' ?></strong>. Modul neposkytuje právní ani daňovou certifikaci. Režim prodejce, zahraniční povinnosti a sazby ověřte s odborníkem pro tento konkrétní provoz.</p>
<form method="post" action="<?= h($settingsUrl) ?>" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="action" value="seller">
  <fieldset class="admin-fieldset-card">
    <legend>Identita prodejce</legend>
    <?php foreach ($sellerFields as $field => $label): ?>
      <label for="seller-<?= h($field) ?>"><?= h($label) ?><?= $field === 'seller_dic' ? ' (povinné pro plátce a oba režimy OSS)' : ' (povinné)' ?></label>
      <?php if ($field === 'seller_address'): ?>
        <textarea class="admin-textarea-compact" id="seller-<?= h($field) ?>" name="<?= h($field) ?>" rows="3" maxlength="5000" required<?= $attributes('seller', $field) ?>><?= h($sellerValues[$field]) ?></textarea>
      <?php else: ?>
        <input type="<?= $field === 'seller_email' ? 'email' : ($field === 'seller_phone' ? 'tel' : 'text') ?>" id="seller-<?= h($field) ?>" name="<?= h($field) ?>" maxlength="<?= $field === 'seller_email' ? 254 : 5000 ?>"<?= $field !== 'seller_dic' ? ' required' : '' ?> value="<?= h($sellerValues[$field]) ?>"<?= $attributes('seller', $field) ?>>
      <?php endif; ?>
      <?php $renderError('seller', $field); ?>
    <?php endforeach; ?>
    <label for="seller-vat_mode">Režim prodejce (povinné)</label>
    <select id="seller-vat_mode" name="vat_mode" required<?= $attributes('seller', 'vat_mode', ['seller-tax-help']) ?>>
      <?php foreach (['non_vat' => 'Neplátce DPH', 'vat' => 'Plátce DPH', 'oss' => 'Tuzemský plátce DPH v OSS', 'non_vat_oss' => 'Tuzemský neplátce DPH – identifikovaná osoba v OSS'] as $key => $label): ?><option value="<?= h($key) ?>"<?= $sellerValues['vat_mode'] === $key ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
    </select>
    <small class="field-help" id="seller-tax-help">CZK je jediná měna. B2B reverse charge není podporován. Oba tuzemské režimy neplátce vyžadují české sazby 0 %. Identifikovaná osoba v OSS musí uvést DIČ; zahraniční sazby a daňový režim se ověřují samostatně, nejsou automaticky nulové. <a href="https://financnisprava.gov.cz/cs/mezinarodni-spoluprace/mezinarodni-spoluprace-a-dph/one-stop-shop-oss/rezim-eu/registrace">Finanční správa: registrace do režimu EU OSS</a>.</small>
    <?php $renderError('seller', 'vat_mode'); ?>
  </fieldset>
  <fieldset class="admin-fieldset-card">
    <legend>Právní informace (všechny povinné)</legend>
    <p id="legal-help">Vložte vlastní ověřené texty. Nejde o univerzální vzor. Dodání digitálního obsahu a ztráta práva na odstoupení nesmějí vylučovat práva z vad. Přijaté objednávky si uchovávají původní znění.</p>
    <?php foreach ($legalFields as $field => $label): ?>
      <label for="seller-<?= h($field) ?>"><?= h($label) ?></label>
      <textarea id="seller-<?= h($field) ?>" name="<?= h($field) ?>" rows="8" maxlength="50000" required<?= $attributes('seller', $field, ['legal-help']) ?>><?= h($sellerValues[$field]) ?></textarea>
      <?php $renderError('seller', $field); ?>
    <?php endforeach; ?>
  </fieldset>
  <fieldset class="admin-fieldset-card">
    <legend>Připravenost prodeje</legend>
    <p id="ready-help">Povolení vyžaduje kompletní identitu, právní texty, platný aktivní bankovní účet a aktivní pravidlo pro každou obsluhovanou zemi. Změna banky nebo daňových pravidel povolení zruší. Ostatní země se nikdy nepovolují implicitně.</p>
    <label for="seller-ready"><input type="checkbox" id="seller-ready" name="ready" value="1"<?= $sellerValues['ready'] === '1' ? ' checked' : '' ?><?= $attributes('seller', 'ready', ['ready-help']) ?>> Povolit prodej po uložení nastavení</label>
    <?php $renderError('seller', 'ready'); ?>
    <label for="seller-confirm_ready"><input type="checkbox" id="seller-confirm_ready" name="confirm_ready" value="1" autocomplete="off"<?= $attributes('seller', 'confirm_ready', ['ready-help']) ?>> Výslovně potvrzuji, že jsem nyní ověřil identitu, právní texty, bankovní účet, režim a sazby všech povolených zemí.</label>
    <?php $renderError('seller', 'confirm_ready'); ?>
    <p><button type="submit" class="btn">Uložit identitu, právní texty a připravenost</button></p>
  </fieldset>
</form>

<h2 id="shop-payment">Platební metody a Fio</h2>
<form method="post" action="<?= h($paymentUrl) ?>" novalidate autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="action" value="payment">
  <fieldset class="admin-fieldset-card">
    <legend><?= $paymentId !== null ? 'Upravit platební metodu' : 'Nová platební metoda' ?></legend>
    <?php if (!empty($payment['order_count'])): ?><p id="locked-bank-help" class="admin-warning-box">Tato metoda už má objednávky. Účet a IBAN jsou neměnné; pro jiný účet vytvořte novou metodu. Fio token lze změnit.</p><?php endif; ?>
    <?php foreach (['name' => 'Název metody (povinné)', 'account_number' => 'Číslo účtu / čitelné označení', 'iban' => 'IBAN (povinné, případně se odvodí z českého čísla účtu)'] as $field => $label): ?>
      <label for="payment-<?= h($field) ?>"><?= h($label) ?></label>
      <input type="text" id="payment-<?= h($field) ?>" name="<?= h($field) ?>" maxlength="<?= $field === 'name' ? 190 : 100 ?>" value="<?= h($paymentValues[$field]) ?>"<?= $field === 'name' ? ' required' : '' ?><?= !empty($payment['order_count']) && $field !== 'name' ? ' readonly' : '' ?><?= $attributes('payment', $field, array_merge($field === 'iban' ? ['iban-help'] : [], !empty($payment['order_count']) && $field !== 'name' ? ['locked-bank-help'] : [])) ?>>
      <?php $renderError('payment', $field); ?>
    <?php endforeach; ?>
    <small class="field-help" id="iban-help">Účet se ověří přes kontrolní součet IBAN. Bankovní údaje přijatých objednávek se pozdější úpravou nepřepisují.</small>
    <label for="payment-is_active"><input type="checkbox" id="payment-is_active" name="is_active" value="1"<?= $paymentValues['is_active'] === '1' ? ' checked' : '' ?> aria-describedby="fio-help"> Aktivní pro nové objednávky</label>
    <p>Fio token: <?= !empty($payment['has_fio_token']) ? 'Je uložen šifrovaně; jeho hodnotu nelze zobrazit.' : 'Není uložen.' ?></p>
    <label for="payment-fio_token">Nový read-only Fio token</label>
    <input type="password" id="payment-fio_token" name="fio_token" value="" minlength="30" maxlength="128" autocomplete="new-password"<?= $attributes('payment', 'fio_token', ['fio-help']) ?>>
    <small class="field-help" id="fio-help">Prázdné pole zachová současný token. Nový token se šifruje klíčem mimo webroot; nikdy se nevrací do formuláře ani konceptu. Automatické párování a bankovní API obsluhuje jádro a cron. Neaktivní metoda se nenabízí novým objednávkám, ale Fio dál kontroluje její dříve přijaté objednávky čekající na platbu. Pro zastavení bankovní kontroly odstraňte token.</small>
    <?php $renderError('payment', 'fio_token'); ?>
    <?php if ($paymentId !== null): ?>
      <label for="payment-confirm_remove_token"><input type="checkbox" id="payment-confirm_remove_token" name="confirm_remove_token" value="1" autocomplete="off"<?= $attributes('payment', 'confirm_remove_token', ['fio-help']) ?>> Potvrzuji odstranění uloženého Fio tokenu a vypnutí automatické kontroly tohoto účtu.</label>
      <?php $renderError('payment', 'confirm_remove_token'); ?>
    <?php endif; ?>
    <p class="button-row"><button type="submit" class="btn">Uložit platební metodu a pozastavit prodej</button><?php if ($paymentId !== null): ?><a href="shop_settings.php#shop-payment">Zrušit úpravu / nová metoda</a><?php endif; ?></p>
  </fieldset>
</form>
<div class="table-responsive" tabindex="0" role="region" aria-labelledby="payment-caption">
  <table>
    <caption id="payment-caption">Platební metody, dostupnost a stav bankovní kontroly</caption>
    <thead><tr><th scope="col">Metoda</th><th scope="col">IBAN</th><th scope="col">Stav</th><th scope="col">Fio</th><th scope="col">Poslední kontrola</th><th scope="col">Akce</th></tr></thead>
    <tbody><?php foreach ($paymentMethods as $method): ?><tr><th scope="row"><?= h((string)$method['name']) ?></th><td><?= h((string)$method['iban']) ?></td><td><?= (int)$method['is_active'] === 1 ? 'Aktivní' : 'Neaktivní' ?></td><td><?= (int)$method['has_fio_token'] === 1 ? 'Token uložen' : 'Bez tokenu' ?><?= !empty($method['fio_last_error']) ? ' · Chyba bankovní kontroly' : '' ?></td><td><?= h((string)($method['fio_last_polled_at'] ?? 'Dosud neproběhla')) ?></td><td><a href="shop_settings.php?edit_payment=<?= (int)$method['id'] ?>#shop-payment">Upravit<span class="sr-only"> <?= h((string)$method['name']) ?></span></a></td></tr><?php endforeach; ?>
      <?php if ($paymentMethods === []): ?><tr><td colspan="6">Žádná platební metoda není nastavena.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<h2 id="shop-tax">Pravidla zemí a daňové sazby</h2>
<p class="admin-warning-box">Prodává se pouze do zemí s výslovně aktivním pravidlem. Země bez pravidla ani neaktivní země nejsou povoleny. U zahraničních objednávek je navíc nutné ověřit zemi a daňový režim dvěma různými kategoriemi důkazů před dodáním.</p>
<form method="post" action="<?= h($taxUrl) ?>" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="action" value="tax">
  <fieldset class="admin-fieldset-card">
    <legend><?= $taxId !== null ? 'Upravit pravidlo země' : 'Nové pravidlo země' ?></legend>
    <label for="tax-country_code">Kód země ISO 3166-1 (povinné)</label>
    <input type="text" id="tax-country_code" name="country_code" maxlength="2" required value="<?= h($taxValues['country_code']) ?>"<?= $attributes('tax', 'country_code') ?>>
    <?php $renderError('tax', 'country_code'); ?>
    <label for="tax-country_name">Název země (povinné)</label>
    <input type="text" id="tax-country_name" name="country_name" maxlength="100" required value="<?= h($taxValues['country_name']) ?>"<?= $attributes('tax', 'country_name') ?>>
    <?php $renderError('tax', 'country_name'); ?>
    <p id="rates-help">Zadejte procenta, například 21,00 nebo 12. Jádro je přesně převede na celočíselné setiny procenta (21 % = 2 100 bp), bez float. Nulovou zahraniční sazbu je nutné výslovně odůvodnit; nepředvyplňuje se.</p>
    <?php foreach (['general_rate' => 'Obecná sazba v procentech', 'publication_rate' => 'Sazba publikací v procentech'] as $field => $label): ?>
      <label for="tax-<?= h($field) ?>"><?= h($label) ?> (povinné)</label>
      <input type="text" id="tax-<?= h($field) ?>" name="<?= h($field) ?>" inputmode="decimal" maxlength="6" required value="<?= h($taxValues[$field]) ?>"<?= $attributes('tax', $field, ['rates-help']) ?>>
      <?php $renderError('tax', $field); ?>
    <?php endforeach; ?>
    <label for="tax-tax_note">Vysvětlení režimu a sazeb, podklad ověření (povinné)</label>
    <textarea id="tax-tax_note" name="tax_note" rows="5" minlength="20" maxlength="10000" required<?= $attributes('tax', 'tax_note') ?>><?= h($taxValues['tax_note']) ?></textarea>
    <?php $renderError('tax', 'tax_note'); ?>
    <label for="tax-is_active"><input type="checkbox" id="tax-is_active" name="is_active" value="1"<?= $taxValues['is_active'] === '1' ? ' checked' : '' ?>> Aktivní pravidlo: povolit tuto zemi po opětovném potvrzení připravenosti</label>
    <label for="tax-confirm_tax_rule"><input type="checkbox" id="tax-confirm_tax_rule" name="confirm_tax_rule" value="1" autocomplete="off"<?= $attributes('tax', 'confirm_tax_rule', ['rates-help']) ?>> Výslovně potvrzuji ověření zahraničního daňového režimu, obou sazeb a místních povinností pro tuto zemi. Pro každé uložení jiné země než CZ je potvrzení povinné.</label>
    <?php $renderError('tax', 'confirm_tax_rule'); ?>
    <p class="button-row"><button type="submit" class="btn">Uložit pravidlo a pozastavit prodej</button><?php if ($taxId !== null): ?><a href="shop_settings.php#shop-tax">Zrušit úpravu / nové pravidlo</a><?php endif; ?></p>
  </fieldset>
</form>
<div class="table-responsive" tabindex="0" role="region" aria-labelledby="tax-caption">
  <table>
    <caption id="tax-caption">Výslovná pravidla zemí; sazby v setinách procenta</caption>
    <thead><tr><th scope="col">Země</th><th scope="col">Obecná sazba (bp)</th><th scope="col">Publikace (bp)</th><th scope="col">Vysvětlení</th><th scope="col">Stav</th><th scope="col">Akce</th></tr></thead>
    <tbody><?php foreach ($taxRules as $rule): ?><tr><th scope="row"><?= h((string)$rule['country_name']) ?> (<?= h((string)$rule['country_code']) ?>)</th><td><?= (int)$rule['general_rate_bp'] ?></td><td><?= (int)$rule['publication_rate_bp'] ?></td><td class="table-cell--prewrap"><?= h((string)$rule['tax_note']) ?></td><td><?= (int)$rule['is_active'] === 1 ? 'Aktivní' : 'Neaktivní' ?></td><td><a href="shop_settings.php?edit_tax=<?= (int)$rule['id'] ?>#shop-tax">Upravit<span class="sr-only"> <?= h((string)$rule['country_name']) ?></span></a></td></tr><?php endforeach; ?>
      <?php if ($taxRules === []): ?><tr><td colspan="6">Žádná země zatím není povolena.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php adminFooter(); ?>
