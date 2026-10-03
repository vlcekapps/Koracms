<?php
require_once __DIR__ . '/../db.php';
$requestMethod = requireHttpMethods(['GET', 'HEAD', 'POST']);
requireSuperAdmin();
requireModuleEnabled('shop');
require_once __DIR__ . '/../lib/shop.php';
require_once __DIR__ . '/layout.php';
shopSafeHeaders();

$pdo = db_connect();
$orderId = inputInt('get', 'id');
$stmt = $pdo->prepare('SELECT * FROM cms_shop_orders WHERE id = ?');
$stmt->execute([$orderId ?? 0]);
$order = $stmt->fetch() ?: null;
if ($order === null) {
    http_response_code(404);
    if ($requestMethod === 'HEAD') {
        exit;
    }
    adminHeader('Objednávka nenalezena');
    echo '<p class="error">Požadovaná objednávka neexistuje.</p><p><a href="shop_orders.php">Zpět na objednávky</a></p>';
    adminFooter();
    exit;
}
if ($requestMethod === 'HEAD') {
    exit;
}
$orderId = (int)$order['id'];
$detailUrl = BASE_URL . '/admin/shop_order.php?id=' . $orderId;
$evidenceTypes = ['billing' => 'Fakturační adresa', 'bank' => 'Bankovní podklady', 'geolocation' => 'Geolokace', 'other' => 'Jiné nezávislé ověřitelné obchodní údaje'];
$taxValues = ['country' => (string)$order['country_code'], 'type_one' => '', 'type_two' => '', 'source_one' => '', 'source_two' => '', 'note' => ''];
$paymentValues = ['reference' => ''];
$changeValues = ['cancel' => ['note' => ''], 'refund' => ['note' => '']];
$errors = [];
$errorAction = '';
$success = '';
$message = '';
$dispatch = static function () use ($pdo, $orderId): string {
    try {
        return shopDispatchOrder($pdo, $orderId)
            ? 'Jádro zpracovalo odeslání objednávky. Přijetí SMTP není zárukou doručení zákazníkovi.'
            : 'Odeslání zatím nebylo dokončeno. Zkontrolujte provozní stav e-mailu a daňové ověření níže.';
    } catch (Throwable $exception) {
        return 'Změna objednávky zůstává uložená, ale odeslání se nepodařilo dokončit. Zkontrolujte provozní stav a případně opakujte odeslání.';
    }
};
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $values = [];
    if ($action === 'verify_tax') {
        foreach ($taxValues as $field => $default) {
            $values[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
        }
        $values['country'] = strtoupper($values['country']);
        if ($values['country'] !== (string)$order['country_code'] || !preg_match('/^[A-Z]{2}$/D', $values['country'])) {
            $errors['country'] = 'Ověřená země musí odpovídat zemi objednávky.';
        }
        foreach (['type_one', 'type_two'] as $field) {
            if (!isset($evidenceTypes[$values[$field]])) {
                $errors[$field] = 'Vyberte ověřitelnou kategorii důkazu země.';
            }
        }
        if ($values['type_one'] === $values['type_two']) {
            $errors['type_two'] = 'Druhý důkaz musí pocházet z jiné kategorie než první.';
        }
        foreach (['source_one', 'source_two', 'note'] as $field) {
            $words = preg_split('/\s+/u', $values[$field], -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (mb_strlen($values[$field]) < 20 || mb_strlen($values[$field]) > 600 || count(array_unique($words)) < 4) {
                $errors[$field] = 'Doplňte konkrétní popis podkladu nebo jeho kontroly: 20 až 600 znaků, alespoň čtyři různá slova.';
            }
        }
        if (mb_strtolower($values['source_one']) === mb_strtolower($values['source_two'])) {
            $errors['source_two'] = 'Dva totožné popisy podkladu nejsou nezávislé důkazy.';
        }
        if (($_POST['confirm_tax'] ?? '') !== '1') {
            $errors['confirm_tax'] = 'Čerstvě potvrďte režim a kontrolu dvou různých, nezávislých a neprotiřečících si důkazů. Dvě prohlášení zákazníka nestačí.';
        }
    } elseif ($action === 'payment') {
        $values['reference'] = is_string($_POST['reference'] ?? null) ? trim($_POST['reference']) : '';
        if (!preg_match('/\A[A-Za-z0-9:._-]{1,100}\z/', $values['reference'])) {
            $errors['reference'] = 'Zadejte jednoznačné číslo bankovního pohybu (1 až 100 znaků, písmena, číslice, dvojtečka, tečka, pomlčka nebo podtržítko).';
        }
        if (($_POST['confirm_payment'] ?? '') !== '1') {
            $errors['confirm_payment'] = 'Před zaznamenáním platby znovu potvrďte účet, měnu, VS a přesnou skutečně přijatou částku.';
        }
        if ($order['country_code'] !== 'CZ' && empty($order['tax_verified_at'])) {
            $message = 'Zahraniční objednávku nelze vyzvat k platbě ani ručně označit za zaplacenou před daňovým ověřením.';
        }
    } elseif (in_array($action, ['cancel', 'refund'], true)) {
        $values['note'] = is_string($_POST['note'] ?? null) ? trim($_POST['note']) : '';
        if (mb_strlen($values['note']) < 10 || mb_strlen($values['note']) > 2000) {
            $errors['note'] = 'Doplňte konkrétní důvod nebo referenci skutečně provedené refundace (10 až 2 000 znaků).';
        }
        if (($_POST['confirm_action'] ?? '') !== '1') {
            $errors['confirm_action'] = 'Akci je nutné znovu výslovně potvrdit. Historie a doklady se nemažou.';
        }
    } elseif ($action === 'resend') {
        if (($_POST['confirm_resend'] ?? '') !== '1') {
            $errors['confirm_resend'] = 'Znovu potvrďte odeslání; zákazník může obdržet opakované oznámení.';
        }
    } else {
        $message = 'Neznámá akce. Objednávka nebyla změněna.';
    }
    if ($errors === [] && $message === '') {
        try {
            if ($action === 'verify_tax') {
                $note = 'První podklad (' . $values['type_one'] . '): ' . $values['source_one']
                    . "\nDruhý podklad (" . $values['type_two'] . '): ' . $values['source_two'] . "\nKontrola: " . $values['note'];
                shopVerifyTax($pdo, $orderId, [
                    'country' => $values['country'], 'type_one' => $values['type_one'],
                    'type_two' => $values['type_two'], 'note' => $note, 'confirm_tax' => '1',
                ]);
                $success = 'Daňové ověření bylo uloženo. ' . $dispatch();
            } elseif ($action === 'payment') {
                $recorded = shopRecordPayment($pdo, $orderId, $values['reference']);
                $success = ($recorded ? 'Platba byla zaznamenána. ' : 'Jádro neprovedlo nový zápis platby; aktuální stav již tuto akci nepovoluje. ') . $dispatch();
            } elseif ($action === 'cancel' || $action === 'refund') {
                shopChangeOrder($pdo, $orderId, $action, $values['note']);
                $success = ($action === 'cancel'
                    ? 'Objednávka byla zrušena. Finanční historie zůstává zachována. '
                    : 'Již provedená refundace byla zaznamenána a jádro vystavilo opravný doklad. ') . $dispatch();
            } elseif ($action === 'resend') {
                shopRetryMail($pdo, $orderId);
                $success = $dispatch();
            }
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = 'Akci se nepodařilo dokončit. Zkontrolujte aktuální stav a historii před opakováním; potvrzení zadejte znovu.';
        }
    }
    $_SESSION['shop_admin_order_flash'][$orderId] = [
        'action' => $action, 'values' => $success === '' ? $values : [],
        'errors' => $errors, 'message' => $message, 'success' => $success,
    ];
    header('Location: ' . internalRedirectTarget($detailUrl), true, 303);
    exit;
}
$flash = $_SESSION['shop_admin_order_flash'][$orderId] ?? null;
unset($_SESSION['shop_admin_order_flash'][$orderId]);
if (is_array($flash)) {
    $errorAction = (string)($flash['action'] ?? '');
    $errors = $flash['errors'] ?? [];
    $message = (string)($flash['message'] ?? '');
    $success = (string)($flash['success'] ?? '');
    if ($errorAction === 'verify_tax') {
        $taxValues = array_replace($taxValues, $flash['values'] ?? []);
    } elseif ($errorAction === 'payment') {
        $paymentValues = array_replace($paymentValues, $flash['values'] ?? []);
    } elseif (isset($changeValues[$errorAction])) {
        $changeValues[$errorAction] = array_replace($changeValues[$errorAction], $flash['values'] ?? []);
    }
}
$attributes = static function (string $action, string $field, array $help = []) use ($errors, $errorAction): string {
    return adminFieldAttributes($field, $errorAction === $action ? array_keys($errors) : [], [], $help, $action . '-' . $field . '-error');
};
$renderError = static function (string $action, string $field) use ($errors, $errorAction): void {
    adminRenderFieldError($field, $errorAction === $action ? array_keys($errors) : [], [], $errorAction === $action ? ($errors[$field] ?? '') : '', $action . '-' . $field . '-error');
};
$decode = static function ($json): array {
    $value = is_string($json) ? json_decode($json, true) : null;
    return is_array($value) ? $value : [];
};
$taxEvidence = $decode($order['tax_evidence'] ?? null);
$sellerSnapshot = $decode($order['seller_snapshot']);
$paymentSnapshot = $decode($order['payment_snapshot']);
$legalSnapshot = $decode($order['legal_snapshot']);
$items = shopOrderItems($pdo, $orderId);
$stmt = $pdo->prepare('SELECT id, kind, invoice_number, created_at FROM cms_shop_invoices WHERE order_id = ? ORDER BY id');
$stmt->execute([$orderId]);
$invoices = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT * FROM cms_shop_payments WHERE order_id = ? ORDER BY id');
$stmt->execute([$orderId]);
$payments = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT event_type, note, user_id, created_at FROM cms_shop_order_events WHERE order_id = ? ORDER BY id');
$stmt->execute([$orderId]);
$events = $stmt->fetchAll();
$states = shopStates();
$pendingForeign = $order['country_code'] !== 'CZ' && empty($order['tax_verified_at']);
adminHeader('Objednávka ' . h((string)$order['order_number']));
?>
<p class="button-row"><a href="shop_orders.php">Zpět na objednávky</a><a href="shop.php">Produkty</a><a href="shop_settings.php">Nastavení</a></p>
<?php if ($success !== ''): ?><p class="success" role="status"><?= h($success) ?></p><?php endif; ?>
<?php if ($errors !== [] || $message !== ''): ?>
  <div class="error" role="alert"><p><?= h($message ?: 'Akce nebyla provedena. Opravte označená pole a znovu potvrďte kontrolu.') ?></p>
    <?php if ($errors !== []): ?><ul><?php foreach ($errors as $field => $error): ?><li><a href="#<?= h($errorAction . '-' . $field) ?>"><?= h($error) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
<?php endif; ?>
<?php if ($pendingForeign && $order['status'] === 'accepted'): ?><p class="admin-warning-box">Zahraniční objednávka je pouze přijatá. Před ověřením země a daňového režimu nevystavujte platební výzvu, nevyzývejte k platbě ani nezpřístupňujte soubory.</p><?php endif; ?>
<section class="admin-section-card" aria-labelledby="order-summary">
  <h2 id="order-summary">Objednávka a neměnné údaje kupujícího</h2>
  <dl>
    <dt>Stav</dt><dd><?= h($states[$order['status']] ?? (string)$order['status']) ?></dd>
    <dt>Zákazník</dt><dd><?= h((string)$order['customer_name']) ?></dd>
    <dt>E-mail</dt><dd><?= h((string)$order['email']) ?></dd>
    <dt>Fakturační adresa</dt><dd><?= h((string)$order['address']) ?>, <?= h((string)$order['postal_code']) ?> <?= h((string)$order['city']) ?>, <?= h((string)$order['country_code']) ?></dd>
    <dt>Celkem včetně daně</dt><dd><?= h(shopMoney((int)$order['total_cents'])) ?></dd>
    <dt>Z toho daň</dt><dd><?= h(shopMoney((int)$order['tax_cents'])) ?></dd>
    <dt>Měna</dt><dd><?= h((string)$order['currency']) ?></dd>
    <dt>Čas přijetí a souhlasu</dt><dd><?= h((string)$order['created_at']) ?> / <?= h((string)$order['consent_at']) ?></dd>
    <?php foreach (['paid_at' => 'Platba', 'fulfilled_at' => 'Dodání', 'cancelled_at' => 'Zrušení', 'token_expires_at' => 'Platnost přístupu do'] as $field => $label): ?><dt><?= h($label) ?></dt><dd><?= h((string)($order[$field] ?? 'Dosud nenastalo')) ?></dd><?php endforeach; ?>
  </dl>
  <h3>Prodejce a banka při přijetí objednávky</h3>
  <dl><?php foreach (['seller_name' => 'Prodejce', 'seller_address' => 'Adresa prodejce', 'seller_ico' => 'IČO', 'seller_dic' => 'DIČ', 'seller_phone' => 'Telefon prodejce', 'vat_mode' => 'Režim prodejce'] as $field => $label): ?><dt><?= h($label) ?></dt><dd><?= h((string)($sellerSnapshot[$field] ?? 'Neuvedeno')) ?></dd><?php endforeach; ?>
    <dt>Platební metoda</dt><dd><?= h((string)($paymentSnapshot['name'] ?? 'Neuvedeno')) ?></dd>
    <dt>Účet / IBAN ze snapshotu</dt><dd><?= h((string)($paymentSnapshot['account_number'] ?? '')) ?> / <?= h((string)($paymentSnapshot['iban'] ?? '')) ?></dd>
    <dt>Variabilní symbol</dt><dd><?= h((string)$order['order_number']) ?></dd>
  </dl>
</section>
<section class="admin-section-card" aria-labelledby="order-tax">
  <h2 id="order-tax">Daňové ověření</h2>
  <p><?= !empty($order['tax_verified_at']) ? 'Ověřeno: ' . h((string)$order['tax_verified_at']) : 'Daňové ověření dosud neproběhlo.' ?></p>
  <?php if ($taxEvidence !== []): ?>
    <dl><dt>Ověřená země</dt><dd><?= h((string)($taxEvidence['country'] ?? '')) ?></dd><dt>První kategorie</dt><dd><?= h($evidenceTypes[$taxEvidence['type_one'] ?? ''] ?? 'Neuvedena') ?></dd><dt>Druhá kategorie</dt><dd><?= h($evidenceTypes[$taxEvidence['type_two'] ?? ''] ?? 'Neuvedena') ?></dd><dt>Podklady a kontrola</dt><dd><?= nl2br(h((string)($taxEvidence['note'] ?? ''))) ?></dd></dl>
  <?php endif; ?>
  <?php if ($pendingForeign && $order['status'] === 'accepted'): ?>
    <form method="post" action="<?= h($detailUrl) ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="verify_tax">
      <fieldset class="admin-fieldset-card">
        <legend>Ověřit zahraniční objednávku před platební výzvou</legend>
        <p id="tax-evidence-help">Dvě různé kategorie nezávislých podkladů musí určit stejnou zemi. Dvě pouhá prohlášení zákazníka, ani označená různými názvy, nestačí. Popište skutečné zdroje, výsledek kontroly a použitý režim; nevkládejte celé bankovní výpisy ani tajné údaje.</p>
        <label for="verify_tax-country">Ověřená země (povinné)</label>
        <input type="text" id="verify_tax-country" name="country" maxlength="2" required value="<?= h($taxValues['country']) ?>"<?= $attributes('verify_tax', 'country') ?>>
        <?php $renderError('verify_tax', 'country'); ?>
        <?php foreach (['one' => 'První', 'two' => 'Druhý'] as $suffix => $label): ?>
          <label for="verify_tax-type_<?= h($suffix) ?>"><?= h($label) ?> typ důkazu (povinné)</label>
          <select id="verify_tax-type_<?= h($suffix) ?>" name="type_<?= h($suffix) ?>" required<?= $attributes('verify_tax', 'type_' . $suffix, ['tax-evidence-help']) ?>><option value="">Vyberte kategorii</option><?php foreach ($evidenceTypes as $key => $typeLabel): ?><option value="<?= h($key) ?>"<?= $taxValues['type_' . $suffix] === $key ? ' selected' : '' ?>><?= h($typeLabel) ?></option><?php endforeach; ?></select>
          <?php $renderError('verify_tax', 'type_' . $suffix); ?>
          <label for="verify_tax-source_<?= h($suffix) ?>"><?= h($label) ?> konkrétní podklad a zjištěná země (povinné)</label>
          <input type="text" id="verify_tax-source_<?= h($suffix) ?>" name="source_<?= h($suffix) ?>" minlength="20" maxlength="600" required value="<?= h($taxValues['source_' . $suffix]) ?>"<?= $attributes('verify_tax', 'source_' . $suffix, ['tax-evidence-help']) ?>>
          <?php $renderError('verify_tax', 'source_' . $suffix); ?>
        <?php endforeach; ?>
        <label for="verify_tax-note">Výsledek kontroly země, režimu a sazeb (povinné)</label>
        <textarea id="verify_tax-note" name="note" minlength="20" maxlength="600" required rows="4"<?= $attributes('verify_tax', 'note', ['tax-evidence-help']) ?>><?= h($taxValues['note']) ?></textarea>
        <?php $renderError('verify_tax', 'note'); ?>
        <label for="verify_tax-confirm_tax"><input type="checkbox" id="verify_tax-confirm_tax" name="confirm_tax" value="1" required autocomplete="off"<?= $attributes('verify_tax', 'confirm_tax', ['tax-evidence-help']) ?>> Potvrzuji daňový režim a sazby, shodu země a dvě různé nezávislé kategorie ověřených důkazů bez rozporů; nejde jen o dvě zákaznická prohlášení.</label>
        <?php $renderError('verify_tax', 'confirm_tax'); ?>
        <p><button type="submit" class="btn">Uložit ověření a nechat jádro vystavit platební výzvu</button></p>
      </fieldset>
    </form>
  <?php endif; ?>
</section>
<section class="admin-section-card" aria-labelledby="order-items">
  <h2 id="order-items">Položky a soubory objednávky</h2>
  <div class="table-responsive" tabindex="0" role="region" aria-labelledby="order-items-caption"><table>
    <caption id="order-items-caption">Neměnné snapshoty položek a digitálních souborů při nákupu</caption>
    <thead><tr><th scope="col">Produkt</th><th scope="col">Množství</th><th scope="col">Jednotková cena</th><th scope="col">Celkem</th><th scope="col">Sazba (bp)</th><th scope="col">Daň</th><th scope="col">Soubor</th></tr></thead>
    <tbody><?php foreach ($items as $item): ?><tr><th scope="row"><?= h((string)$item['title']) ?></th><td><?= (int)$item['quantity'] ?></td><td><?= h(shopMoney((int)$item['unit_price_cents'])) ?></td><td><?= h(shopMoney((int)$item['total_cents'])) ?></td><td><?= (int)$item['tax_rate_bp'] ?></td><td><?= h(shopMoney((int)$item['tax_cents'])) ?></td><td class="table-cell--detail"><?= h((string)$item['file_original_name']) ?> (<?= (int)$item['file_size'] ?> bajtů)<small class="table-meta admin-code-break">SHA-256: <?= h((string)$item['file_sha256']) ?></small></td></tr><?php endforeach; ?></tbody>
  </table></div>
</section>
<section class="admin-section-card" aria-labelledby="order-payments">
  <h2 id="order-payments">Zaznamenané platby</h2>
  <div class="table-responsive" tabindex="0" role="region" aria-labelledby="order-payments-caption"><table>
    <caption id="order-payments-caption">Jednoznačné bankovní pohyby přiřazené této objednávce</caption>
    <thead><tr><th scope="col">Reference</th><th scope="col">Částka</th><th scope="col">Měna</th><th scope="col">Přijato</th><th scope="col">ID metody</th></tr></thead>
    <tbody><?php foreach ($payments as $payment): ?><tr><th scope="row"><?= h((string)$payment['bank_transaction_id']) ?></th><td><?= h(shopMoney((int)$payment['amount_cents'])) ?></td><td><?= h((string)$payment['currency']) ?></td><td><?= h((string)$payment['received_at']) ?></td><td><?= (int)$payment['payment_method_id'] ?></td></tr><?php endforeach; ?><?php if ($payments === []): ?><tr><td colspan="5">Žádná platba nebyla zaznamenána.</td></tr><?php endif; ?></tbody>
  </table></div>
  <?php if (!$pendingForeign && $order['status'] === 'awaiting_payment'): ?>
    <form method="post" action="<?= h($detailUrl) ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="payment">
      <fieldset class="admin-fieldset-card">
        <legend>Ručně zaznamenat skutečně přijatou platbu</legend>
        <p id="payment-review">Zkontrolujte skutečně přijatou částku <?= h(shopMoney((int)$order['total_cents'])) ?> v CZK, účet ze snapshotu a VS <?= h((string)$order['order_number']) ?>. Toto není bankovní příkaz. Deduplikaci, zápis úhrady, fakturu a stav obsluhuje výhradně jádro.</p>
        <label for="payment-reference">Jednoznačná reference bankovního pohybu (povinné)</label>
        <input type="text" id="payment-reference" name="reference" maxlength="100" required value="<?= h($paymentValues['reference']) ?>"<?= $attributes('payment', 'reference', ['payment-review']) ?>>
        <?php $renderError('payment', 'reference'); ?>
        <label for="payment-confirm_payment"><input type="checkbox" id="payment-confirm_payment" name="confirm_payment" value="1" required autocomplete="off"<?= $attributes('payment', 'confirm_payment', ['payment-review']) ?>> Potvrzuji čerstvou kontrolu skutečně přijaté platby, účtu, CZK, VS a přesné kladné částky.</label>
        <?php $renderError('payment', 'confirm_payment'); ?>
        <p><button type="submit" class="btn">Zaznamenat platbu a zpracovat dodání</button></p>
      </fieldset>
    </form>
  <?php endif; ?>
</section>
<section class="admin-section-card" aria-labelledby="order-invoices">
  <h2 id="order-invoices">Doklady</h2>
  <div class="table-responsive" tabindex="0" role="region" aria-labelledby="order-invoices-caption"><table>
    <caption id="order-invoices-caption">Již vystavené neměnné doklady; platební výzva není daňový doklad</caption>
    <thead><tr><th scope="col">Číslo</th><th scope="col">Druh</th><th scope="col">Vystaveno</th><th scope="col">Přístupné zobrazení</th></tr></thead>
    <tbody><?php $shownInvoices = 0; ?><?php foreach ($invoices as $invoice): ?><?php if ($invoice['kind'] === 'proforma' && ($pendingForeign || $order['status'] === 'accepted')) {
        continue;
    } $shownInvoices++; ?>
      <tr><th scope="row"><?= h((string)$invoice['invoice_number']) ?></th><td><?= h(['proforma' => 'Platební výzva (nedaňový doklad)', 'final' => 'Faktura', 'credit' => 'Opravný doklad'][$invoice['kind']] ?? (string)$invoice['kind']) ?></td><td><?= h((string)$invoice['created_at']) ?></td><td><?php foreach (['html' => 'HTML', 'text' => 'Text', 'pdf' => 'PDF'] as $format => $label): ?><a href="shop_invoice.php?id=<?= (int)$invoice['id'] ?>&amp;format=<?= h($format) ?>"><?= h($label) ?><span class="sr-only"> dokladu <?= h((string)$invoice['invoice_number']) ?></span></a> <?php endforeach; ?></td></tr>
    <?php endforeach; ?><?php if ($shownInvoices === 0): ?><tr><td colspan="4">Žádný doklad nyní není dostupný. U zahraniční přijaté objednávky se platební výzva vystaví až po ověření daně.</td></tr><?php endif; ?></tbody>
  </table></div>
</section>
<section class="admin-section-card" aria-labelledby="order-mail">
  <h2 id="order-mail">E-mail a provozní stav</h2>
  <p>Chyba e-mailu nikdy nevrací zaplacenou objednávku do stavu čekání na platbu. Přijetí zprávy SMTP není zárukou doručení.</p>
  <dl><dt>Poslední úspěšně odeslaná fáze</dt><dd><?= h($states[$order['mail_sent_status'] ?? ''] ?? 'Dosud žádná') ?></dd><?php foreach (['confirmation_sent_at' => 'Poslední potvrzení fáze odesláno', 'delivery_sent_at' => 'Oznámení dodání odesláno', 'mail_retry_at' => 'Naplánovaný další pokus', 'mail_claim_until' => 'Probíhající zpracování do'] as $field => $label): ?><dt><?= h($label) ?></dt><dd><?= h((string)($order[$field] ?? 'Dosud neproběhlo / není naplánováno')) ?></dd><?php endforeach; ?><dt>Počet pokusů</dt><dd><?= (int)$order['mail_attempts'] ?></dd></dl>
  <?php if (!empty($order['mail_last_error'])): ?><p class="error" role="status">Poslední odeslání selhalo: <?= h((string)$order['mail_last_error']) ?></p><?php else: ?><p>Bez zaznamenané chyby odeslání.</p><?php endif; ?>
  <form method="post" action="<?= h($detailUrl) ?>" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="resend">
    <fieldset class="admin-fieldset-card">
      <legend>Ruční opakování odeslání přes jádro</legend>
      <p id="resend-help">Jádro pod zámkem připraví nový pokus a zapíše událost. Aktivní claim se neruší. I opakované oznámení vyřízené objednávky zachovává původní fakturu a nárok; nevzniká nová platba ani nové dodání. Přijatá zahraniční objednávka před ověřením nedostane platební výzvu.</p>
      <label for="resend-confirm_resend"><input type="checkbox" id="resend-confirm_resend" name="confirm_resend" value="1" required autocomplete="off"<?= $attributes('resend', 'confirm_resend', ['resend-help']) ?>> Potvrzuji opětovné odeslání; zákazník může obdržet opakované oznámení.</label>
      <?php $renderError('resend', 'confirm_resend'); ?>
      <p><button type="submit" class="btn">Znovu zpracovat odeslání</button></p>
    </fieldset>
  </form>
</section>
<?php foreach (['cancel' => 'Zrušit neuhrazenou objednávku', 'refund' => 'Zaznamenat již provedenou refundaci'] as $action => $label): ?>
  <?php if (($action === 'cancel' && !in_array($order['status'], ['accepted', 'awaiting_payment'], true)) || ($action === 'refund' && (empty($order['paid_at']) || !in_array($order['status'], ['paid', 'fulfilled', 'cancelled'], true)))) {
      continue;
  } ?>
  <form method="post" action="<?= h($detailUrl) ?>" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="<?= h($action) ?>">
    <fieldset class="admin-fieldset-card">
      <legend><?= h($label) ?></legend>
      <p id="<?= h($action) ?>-help"><?= $action === 'refund' ? 'Nejde o bankovní příkaz. Zaznamenejte pouze již skutečně vrácenou platbu v plné výši. Jádro vystaví opravný doklad a zruší možnost stahování.' : 'Zrušení se provede pouze v povoleném stavu a zabrání pozdějšímu automatickému dodání.' ?> Historie, položky, platby a doklady se nemažou.</p>
      <label for="<?= h($action) ?>-note"><?= $action === 'refund' ? 'Reference a popis skutečně provedeného vrácení peněz' : 'Důvod zrušení' ?> (povinné)</label>
      <textarea id="<?= h($action) ?>-note" name="note" rows="3" minlength="10" maxlength="2000" required<?= $attributes($action, 'note', [$action . '-help']) ?>><?= h($changeValues[$action]['note']) ?></textarea>
      <?php $renderError($action, 'note'); ?>
      <label for="<?= h($action) ?>-confirm_action"><input type="checkbox" id="<?= h($action) ?>-confirm_action" name="confirm_action" value="1" required autocomplete="off"<?= $attributes($action, 'confirm_action', [$action . '-help']) ?>> <?= $action === 'refund' ? 'Potvrzuji, že celá platba již byla skutečně vrácena a chci to nevratně zaznamenat.' : 'Potvrzuji zrušení této neuhrazené objednávky.' ?></label>
      <?php $renderError($action, 'confirm_action'); ?>
      <p><button type="submit" class="btn btn-danger"><?= h($label) ?></button></p>
    </fieldset>
  </form>
<?php endforeach; ?>
<section class="admin-section-card" aria-labelledby="order-legal">
  <h2 id="order-legal">Právní texty a souhlas v okamžiku nákupu</h2>
  <?php foreach (['terms' => 'Obchodní podmínky', 'privacy' => 'Ochrana soukromí', 'complaints' => 'Reklamace', 'withdrawal' => 'Odstoupení', 'tax_note' => 'Daňové vysvětlení', 'consent' => 'Výslovný digitální souhlas'] as $field => $label): ?><details><summary><?= h($label) ?></summary><p><?= nl2br(h((string)($legalSnapshot[$field] ?? 'Neuvedeno'))) ?></p></details><?php endforeach; ?>
</section>
<div class="table-responsive" tabindex="0" role="region" aria-labelledby="order-events-caption"><table>
  <caption id="order-events-caption">Nemazatelná historie událostí objednávky v pořadí zápisu</caption>
  <thead><tr><th scope="col">Čas</th><th scope="col">Událost</th><th scope="col">Poznámka</th><th scope="col">ID správce / uživatele</th></tr></thead>
  <tbody><?php foreach ($events as $event): ?><tr><td><?= h((string)$event['created_at']) ?></td><th scope="row"><?= h((string)$event['event_type']) ?></th><td class="table-cell--prewrap"><?= h((string)($event['note'] ?? '')) ?></td><td><?= $event['user_id'] !== null ? (int)$event['user_id'] : 'Automaticky / host' ?></td></tr><?php endforeach; ?><?php if ($events === []): ?><tr><td colspan="4">Zatím nejsou zaznamenány události.</td></tr><?php endif; ?></tbody>
</table></div>
<?php adminFooter(); ?>
