<?php
$isReview = !empty($isReview);
$errors = is_array($errors ?? null) ? $errors : [];
$fieldErrors = is_array($fieldErrors ?? null) ? $fieldErrors : [];
$items = is_array($items ?? null) ? $items : [];
$total = (int)($total ?? 0);
$previewTax = isset($previewTax) ? (int)$previewTax : null;
$ready = !empty($ready);
$available = !empty($available);
$limitExceeded = !empty($limitExceeded);
$maximumTotal = (int)($maximumTotal ?? shopMaximumOrderCents());
$paymentMethods = is_array($paymentMethods ?? null) ? $paymentMethods : [];
$taxRules = is_array($taxRules ?? null) ? $taxRules : [];
$selectedRule = is_array($selectedRule ?? null) ? $selectedRule : [];
$signedIn = !empty($signedIn);
$nonce = (string)($nonce ?? '');
$quoteSignature = (string)($quoteSignature ?? '');
$captchaExpr = (string)($captchaExpr ?? '');
$settings = is_array($settings ?? null) ? $settings : [];
$formData = array_replace(array_fill_keys(['customer_name', 'email', 'address', 'city', 'postal_code', 'country_code', 'payment_method_id'], ''), is_array($formData ?? null) ? $formData : []);
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="shop-checkout-title">
    <h1 id="shop-checkout-title" class="section-title section-title--hero"><?= $isReview ? 'Zkontrolujte a potvrďte objednávku' : 'Dokončení objednávky' ?></h1>
    <?= renderThemeView('modules/shop-navigation') ?>
    <?php if ($errors !== []): ?>
      <div id="shop-checkout-errors" class="status-message status-message--error" role="alert">
        <h2 class="section-title section-title--compact">Objednávka nebyla odeslána</h2>
        <ul><?php foreach (array_unique($errors) as $error): ?><li><?= h((string)$error) ?></li><?php endforeach; ?></ul>
        <p>Běžné údaje zůstaly zachovány. Souhlasy a ověřovací otázku je nutné vyplnit znovu.</p>
      </div>
    <?php endif; ?>
    <?php if ($items !== []): ?><?= renderThemeView('modules/shop-summary', compact('items', 'total', 'previewTax')) ?><?php endif; ?>
    <?php if (!$ready || $paymentMethods === [] || $taxRules === []): ?>
      <div class="status-message status-message--warning" role="status"><p>Prodej nyní není dostupný. Objednávku nelze odeslat, dokud prodejce nedokončí nastavení, platební údaje a aktivní daňová pravidla.</p></div>
    <?php elseif ($limitExceeded): ?>
      <div class="status-message status-message--warning" role="status"><p>Celková cena překračuje limit jedné objednávky <?= h(shopMoney($maximumTotal)) ?>. <a href="<?= BASE_URL ?>/shop/cart.php">Snižte počet kusů nebo odeberte položky z košíku</a>; objednávku zatím nelze odeslat.</p></div>
    <?php elseif (!$available): ?>
      <div class="status-message status-message--warning" role="status"><p>Košík je prázdný nebo obsahuje nedostupnou položku. <a href="<?= BASE_URL ?>/shop/cart.php">Upravte košík</a>.</p></div>
    <?php else: ?>
      <?php if (!$isReview): ?>
        <section aria-labelledby="shop-checkout-details-title">
          <h2 id="shop-checkout-details-title" class="section-title">Fakturační údaje a platba</h2>
          <?php if ($signedIn): ?>
            <p>Objednávka bude přiřazena k vašemu stávajícímu přihlášenému účtu. Kontaktní údaje můžete upravit.</p>
          <?php else: ?>
            <p>Nakupujete bez registrace. Objednávku obdržíte přes soukromý odkaz; nebude přiřazena k účtu podle shody e-mailu.</p>
            <p><a href="<?= h(BASE_URL . '/public_login.php?redirect=' . rawurlencode(BASE_URL . '/shop/checkout.php')) ?>">Přihlásit se ke stávajícímu účtu</a><?php if (publicRegistrationEnabled()): ?> nebo <a href="<?= BASE_URL ?>/register.php">použít veřejnou registraci</a><?php endif; ?>. Registrace není podmínkou nákupu.</p>
          <?php endif; ?>
          <form method="post" action="<?= BASE_URL ?>/shop/checkout.php" class="form-stack" novalidate<?= $errors !== [] ? ' aria-describedby="shop-checkout-errors"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="checkout_nonce" value="<?= h($nonce) ?>">
            <input type="hidden" name="action" value="review">
            <?= honeypotField() ?>
            <fieldset class="form-fieldset">
              <legend>Kontaktní a fakturační údaje kupujícího</legend>
              <p>Všechna následující pole jsou povinná. Jde o prodej spotřebitelům; režim B2B reverse charge není podporován.</p>
              <?php foreach ([
                  'customer_name' => ['Jméno a příjmení', 'text', 'name', 255],
                  'email' => ['E-mail pro potvrzení a zpřístupnění souboru', 'email', 'email', 255],
                  'address' => ['Ulice a číslo domu', 'text', 'address-line1', 255],
                  'city' => ['Obec', 'text', 'address-level2', 150],
                  'postal_code' => ['PSČ', 'text', 'postal-code', 30],
              ] as $key => [$label, $type, $autocomplete, $maxLength]): ?>
                <div class="field">
                  <label for="shop-<?= h(str_replace('_', '-', $key)) ?>"><?= h($label) ?> (povinné)</label>
                  <input id="shop-<?= h(str_replace('_', '-', $key)) ?>" class="form-control" name="<?= h($key) ?>" type="<?= h($type) ?>" autocomplete="<?= h($autocomplete) ?>" maxlength="<?= (int)$maxLength ?>" required value="<?= h($formData[$key]) ?>"<?= shopPublicFieldAttributes($fieldErrors, $key) ?>>
                  <?= shopPublicFieldError($fieldErrors, $key) ?>
                </div>
              <?php endforeach; ?>
              <div class="field">
                <label for="shop-country-code">Země fakturační adresy (povinné)</label>
                <select id="shop-country-code" name="country_code" class="form-control" autocomplete="country" required<?= shopPublicFieldAttributes($fieldErrors, 'country_code', ['shop-country-help']) ?>>
                  <option value="">Vyberte skutečnou zemi adresy</option>
                  <?php if ($formData['country_code'] !== '' && !in_array($formData['country_code'], array_column($taxRules, 'country_code'), true)): ?><option value="<?= h($formData['country_code']) ?>" selected>Nepodporovaná země: <?= h($formData['country_code']) ?></option><?php endif; ?>
                  <?php foreach ($taxRules as $rule): ?><option value="<?= h((string)$rule['country_code']) ?>"<?= $formData['country_code'] === $rule['country_code'] ? ' selected' : '' ?>><?= h((string)$rule['country_name']) ?></option><?php endforeach; ?>
                </select>
                <p id="shop-country-help" class="field-help">Objednat lze pouze do zemí s aktivním daňovým pravidlem. Zahraniční objednávka vyžaduje ověření země a režimu prodejcem před platbou a dodáním.</p>
                <?= shopPublicFieldError($fieldErrors, 'country_code') ?>
              </div>
            </fieldset>
            <fieldset class="form-fieldset">
              <legend>Způsob platby</legend>
              <div class="field">
                <label for="shop-payment-method-id">Bankovní převod (povinné)</label>
                <select id="shop-payment-method-id" name="payment_method_id" class="form-control" required<?= shopPublicFieldAttributes($fieldErrors, 'payment_method_id') ?>>
                  <option value="">Vyberte platební možnost</option>
                  <?php if ($formData['payment_method_id'] !== '' && !in_array((int)$formData['payment_method_id'], array_map(static fn (array $method): int => (int)$method['id'], $paymentMethods), true)): ?><option value="<?= h($formData['payment_method_id']) ?>" selected>Dříve vybraná možnost již není dostupná</option><?php endif; ?>
                  <?php foreach ($paymentMethods as $method): ?><option value="<?= (int)$method['id'] ?>"<?= $formData['payment_method_id'] === (string)$method['id'] ? ' selected' : '' ?>><?= h((string)$method['name']) ?></option><?php endforeach; ?>
                </select>
                <?= shopPublicFieldError($fieldErrors, 'payment_method_id') ?>
              </div>
            </fieldset>
            <p>V dalším kroku zkontrolujete konečnou cenu, údaje prodejce a smluvní podmínky. Toto tlačítko ještě nevytváří objednávku.</p>
            <button type="submit" class="button-primary">Zkontrolovat objednávku</button>
          </form>
        </section>
      <?php else: ?>
        <section aria-labelledby="shop-review-customer-title">
          <h2 id="shop-review-customer-title" class="section-title">Kupující a platba</h2>
          <dl>
            <dt>Jméno a příjmení</dt><dd><?= h($formData['customer_name']) ?></dd>
            <dt>E-mail</dt><dd><?= h($formData['email']) ?></dd>
            <dt>Fakturační adresa</dt><dd><?= h($formData['address']) ?>, <?= h($formData['postal_code']) ?> <?= h($formData['city']) ?></dd>
            <dt>Země</dt><dd><?= h((string)$selectedRule['country_name']) ?> (<?= h($formData['country_code']) ?>)</dd>
            <dt>Platba</dt><dd><?php foreach ($paymentMethods as $method): ?><?php if ((string)$method['id'] === $formData['payment_method_id']): ?><?= h((string)$method['name']) ?><?php endif; ?><?php endforeach; ?>, bankovní převod v CZK</dd>
          </dl>
          <p><?= h((string)$selectedRule['tax_note']) ?></p>
          <?php if ($formData['country_code'] !== 'CZ'): ?><div class="status-message status-message--info"><p>Zahraniční objednávka bude nejprve přijata ke kontrole země a daně. Zatím neplaťte; platební výzvu obdržíte až po ověření prodejcem.</p></div><?php endif; ?>
          <p><a class="button-secondary" href="<?= h(BASE_URL . '/shop/checkout.php?edit=' . rawurlencode($nonce)) ?>">Upravit fakturační údaje nebo platbu</a></p>
        </section>
        <?= renderThemeView('modules/shop-seller', ['settings' => $settings, 'headingId' => 'shop-review-seller']) ?>
        <?= renderThemeView('modules/shop-legal-texts', ['legal' => $settings, 'prefix' => 'shop-review']) ?>
        <section aria-labelledby="shop-confirm-title">
          <h2 id="shop-confirm-title" class="section-title">Výslovné potvrzení objednávky</h2>
          <p>Soubor bude zpřístupněn až po ověření přesné platby, daňového režimu a vašeho souhlasu. Práva z vad zůstávají zachovaná.</p>
          <p>Soukromý odkaz ke stažení bude platný 365 dní od zpřístupnění digitálního obsahu. Opakované odeslání e-mailu tuto dobu neprodlužuje. Vypršení odkazu samo nemění licenci již staženého souboru; jeho použití se nadále řídí licencí produktu. Při vrácení platby bude přístup ke stažení zrušen.</p>
          <p>Do zpřístupnění je soukromý odkaz k objednávce platný 365 dní od přijetí objednávky.</p>
          <form method="post" action="<?= BASE_URL ?>/shop/checkout.php" class="form-stack" autocomplete="off" novalidate<?= $errors !== [] ? ' aria-describedby="shop-checkout-errors"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="checkout_nonce" value="<?= h($nonce) ?>">
            <input type="hidden" name="quote_signature" value="<?= h($quoteSignature) ?>">
            <input type="hidden" name="action" value="place">
            <?= honeypotField() ?>
            <fieldset class="form-fieldset">
              <legend>Souhlasy k této objednávce (oba povinné)</legend>
              <label class="choice-card" for="shop-confirm-terms">
                <input id="shop-confirm-terms" name="confirm_terms" type="checkbox" value="1" required autocomplete="off"<?= shopPublicFieldAttributes($fieldErrors, 'confirm_terms') ?>>
                <span>Souhlasím s <a href="#shop-review-terms">obchodními podmínkami</a> a elektronickým vystavením a zasláním dokladu.</span>
              </label>
              <?= shopPublicFieldError($fieldErrors, 'confirm_terms') ?>
              <label class="choice-card" for="shop-confirm-digital">
                <input id="shop-confirm-digital" name="confirm_digital" type="checkbox" value="1" required autocomplete="off"<?= shopPublicFieldAttributes($fieldErrors, 'confirm_digital', ['shop-digital-help']) ?>>
                <span>Výslovně souhlasím se zpřístupněním digitálního obsahu před uplynutím lhůty pro odstoupení a beru na vědomí, že jeho zpřístupněním ztrácím právo na odstoupení od této smlouvy. Práva z vad zůstávají zachovaná.</span>
              </label>
              <p id="shop-digital-help" class="field-help">Právo na odstoupení nezaniká pouhým odesláním objednávky. Důsledky nastávají až zpřístupněním digitálního obsahu za splnění zákonných podmínek.</p>
              <?= shopPublicFieldError($fieldErrors, 'confirm_digital') ?>
            </fieldset>
            <?= renderThemeView('modules/shop-captcha', compact('captchaExpr', 'fieldErrors')) ?>
            <p>Odesláním vzniká povinnost zaplatit <strong><?= h(shopMoney((int)$total)) ?></strong>. Cena a daň jsou před přijetím znovu ověřeny na serveru.</p>
            <button type="submit" class="button-primary">Objednávka zavazující k platbě</button>
          </form>
        </section>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
