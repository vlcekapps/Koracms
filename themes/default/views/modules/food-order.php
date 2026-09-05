<?php
$card = is_array($card ?? null) ? $card : [];
$selectableItems = is_array($selectableItems ?? null) ? $selectableItems : [];
$fieldErrors = is_array($fieldErrors ?? null) ? $fieldErrors : [];
$errors = is_array($errors ?? null) ? $errors : [];
$formData = is_array($formData ?? null) ? $formData : [];
$referenceCode = trim((string)($referenceCode ?? ''));
$fulfillmentModes = normalizeFoodOrderFulfillmentModes($fulfillmentModes ?? []);
$fieldErrorId = static fn (string $key): string => 'food-order-' . str_replace('_', '-', $key) . '-error';
$fieldValue = static function (string $key) use ($formData): string {
    return (string)($formData[$key] ?? '');
};
$fieldAttributes = static function (string $key, array $extraDescriptions = []) use ($fieldErrors, $fieldErrorId): string {
    $descriptions = [];
    foreach ($extraDescriptions as $descriptionId) {
        $descriptionId = trim((string)$descriptionId);
        if ($descriptionId !== '') {
            $descriptions[] = $descriptionId;
        }
    }
    if (isset($fieldErrors[$key])) {
        $descriptions[] = $fieldErrorId($key);
    }
    $attributes = isset($fieldErrors[$key]) ? ' aria-invalid="true"' : '';
    if ($descriptions !== []) {
        $attributes .= ' aria-describedby="' . h(implode(' ', array_unique($descriptions))) . '"';
    }

    return $attributes;
};
$quantities = is_array($formData['quantities'] ?? null) ? $formData['quantities'] : [];
$itemsDescriptionIds = ['food-order-items-help'];
if (isset($fieldErrors['items'])) {
    $itemsDescriptionIds[] = $fieldErrorId('items');
}
?>
<div class="auth-shell">
  <section class="surface surface--narrow" aria-labelledby="food-order-title">
    <p class="section-kicker">Nezávazná poptávka</p>
    <h1 id="food-order-title" class="section-title section-title--hero">Poptávka z lístku <?= h((string)($card['title'] ?? '')) ?></h1>
    <p class="section-subtitle">
      Vyberte dostupné položky a odešlete nezávaznou poptávku. Provozovatel vám objednávku teprve potvrdí nebo upřesní.
    </p>

    <div class="button-row button-row--start">
      <a class="button-secondary" href="<?= h((string)($card['public_path'] ?? (BASE_URL . '/food/index.php'))) ?>"><span aria-hidden="true">&larr;</span> Zpět na lístek</a>
    </div>

    <?php if (!empty($success)): ?>
      <div class="status-message status-message--success" role="status" aria-atomic="true" aria-labelledby="food-order-success-message">
        <p id="food-order-success-message">Poptávka byla odeslána. Děkujeme!</p>
        <?php if ($referenceCode !== ''): ?>
          <p>Referenční kód poptávky: <strong><?= h($referenceCode) ?></strong></p>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
          <p><?= h((string)$error) ?> Poptávku znovu neodesílejte; provozovatel ji má uloženou v administraci.</p>
        <?php endforeach; ?>
      </div>
    <?php elseif (!foodCardCanAcceptOrders($card)): ?>
      <p class="empty-state">Tento lístek teď nepřijímá objednávkové poptávky.</p>
    <?php else: ?>
      <?php if ($errors !== []): ?>
        <div id="food-order-errors" class="status-message status-message--error" role="alert" aria-atomic="true" aria-labelledby="food-order-errors-heading">
          <p id="food-order-errors-heading" class="sr-only">Poptávku se nepodařilo odeslat</p>
          <ul>
            <?php foreach ($errors as $error): ?><li><?= h((string)$error) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (trim((string)($card['order_instructions'] ?? '')) !== ''): ?>
        <section class="notice-box" aria-labelledby="food-order-instructions-title">
          <h2 id="food-order-instructions-title">Instrukce k poptávce</h2>
          <p><?= h((string)$card['order_instructions']) ?></p>
        </section>
      <?php endif; ?>

      <form method="post" novalidate class="form-stack"<?php if ($errors !== []): ?> aria-describedby="food-order-errors"<?php endif; ?>>
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="slug" value="<?= h((string)($card['slug'] ?? '')) ?>">
        <?= honeypotField() ?>

        <fieldset class="form-fieldset">
          <legend>Vybrané položky</legend>
          <p id="food-order-items-help" class="field-help field-help--flush">U položek, které chcete poptat, zadejte celé množství od 0 do 99. Položky s nulou se neodešlou.</p>
          <?php if (isset($fieldErrors['items'])): ?><small id="<?= h($fieldErrorId('items')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['items']) ?></small><?php endif; ?>
          <div class="food-order-items" aria-describedby="<?= h(implode(' ', $itemsDescriptionIds)) ?>">
            <?php foreach ($selectableItems as $item): ?>
              <?php
              $itemId = (int)($item['id'] ?? 0);
                $itemVariants = is_array($item['variants'] ?? null) ? array_values(array_filter(
                    $item['variants'],
                    static fn ($variant): bool => is_array($variant) && (int)($variant['is_available'] ?? 1) === 1
                )) : [];
                ?>
              <?php if ($itemVariants !== []): ?>
                <fieldset class="food-order-item-group">
                  <legend><?= h((string)($item['title'] ?? '')) ?></legend>
                  <?php foreach ($itemVariants as $variant): ?>
                    <?php
                      $choiceKey = foodOrderChoiceKey($itemId, (int)$variant['id']);
                      $quantityId = 'food-order-qty-' . str_replace('-', '_', $choiceKey);
                      ?>
                    <div class="food-order-item">
                      <div>
                        <label for="<?= h($quantityId) ?>"><strong><?= h(foodItemVariantDisplayLabel($variant)) ?></strong></label>
                        <?php if ((string)$variant['price_label'] !== ''): ?><p class="meta-row meta-row--tight"><?= h((string)$variant['price_label']) ?></p><?php endif; ?>
                      </div>
                      <input id="<?= h($quantityId) ?>" class="form-control form-control--compact" type="number" min="0" max="99" step="1"
                             name="qty[<?= h($choiceKey) ?>]" value="<?= h((string)($quantities[$choiceKey] ?? '0')) ?>" inputmode="numeric"<?= $fieldAttributes('qty-' . $choiceKey, $itemsDescriptionIds) ?>>
                      <?php if (isset($fieldErrors['qty-' . $choiceKey])): ?><small id="<?= h($fieldErrorId('qty-' . $choiceKey)) ?>" class="field-help field-error"><?= h($fieldErrors['qty-' . $choiceKey]) ?></small><?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </fieldset>
              <?php else: ?>
                <?php
                $choiceKey = foodOrderChoiceKey($itemId);
                  $quantityId = 'food-order-qty-' . str_replace('-', '_', $choiceKey);
                  $itemPrice = foodPriceLabel(
                      $item['price_amount'] !== null ? (string)$item['price_amount'] : null,
                      (string)($item['price_currency'] ?? 'CZK'),
                      (string)($item['price_note'] ?? '')
                  );
                  ?>
                <div class="food-order-item">
                  <div>
                    <label for="<?= h($quantityId) ?>"><strong><?= h((string)($item['title'] ?? '')) ?></strong></label>
                    <?php if ($itemPrice !== ''): ?><p class="meta-row meta-row--tight"><?= h($itemPrice) ?></p><?php endif; ?>
                  </div>
                  <input id="<?= h($quantityId) ?>" class="form-control form-control--compact" type="number" min="0" max="99" step="1"
                         name="qty[<?= h($choiceKey) ?>]" value="<?= h((string)($quantities[$choiceKey] ?? '0')) ?>" inputmode="numeric"<?= $fieldAttributes('qty-' . $choiceKey, $itemsDescriptionIds) ?>>
                  <?php if (isset($fieldErrors['qty-' . $choiceKey])): ?><small id="<?= h($fieldErrorId('qty-' . $choiceKey)) ?>" class="field-help field-error"><?= h($fieldErrors['qty-' . $choiceKey]) ?></small><?php endif; ?>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <?php if ($fulfillmentModes !== [] || (int)($card['order_requested_at_enabled'] ?? 0) === 1): ?>
          <fieldset class="form-fieldset">
            <legend>Převzetí poptávky</legend>
            <?php if (count($fulfillmentModes) === 1): ?>
              <input type="hidden" name="fulfillment_type" value="<?= h($fulfillmentModes[0]) ?>">
              <p><strong>Způsob převzetí:</strong> <?= h(foodOrderFulfillmentLabel($fulfillmentModes[0])) ?></p>
            <?php elseif (count($fulfillmentModes) > 1): ?>
              <fieldset class="form-fieldset">
                <legend>Způsob převzetí <span aria-hidden="true">*</span></legend>
                <p id="food-order-fulfillment-help" class="field-help field-help--flush">Vyberte jednu možnost. Provozovatel ji potvrdí spolu s poptávkou.</p>
                <?php foreach ($fulfillmentModes as $fulfillmentMode): ?>
                  <?php $fulfillmentId = 'food-order-fulfillment-' . str_replace('_', '-', $fulfillmentMode); ?>
                  <label class="check-row" for="<?= h($fulfillmentId) ?>">
                    <input id="<?= h($fulfillmentId) ?>" type="radio" name="fulfillment_type" value="<?= h($fulfillmentMode) ?>" required
                           <?= $fieldValue('fulfillment_type') === $fulfillmentMode ? 'checked' : '' ?>
                           <?= $fieldAttributes('fulfillment_type', ['food-order-fulfillment-help']) ?>>
                    <span><?= h(foodOrderFulfillmentLabel($fulfillmentMode)) ?></span>
                  </label>
                <?php endforeach; ?>
                <?php if (isset($fieldErrors['fulfillment_type'])): ?><small id="<?= h($fieldErrorId('fulfillment_type')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['fulfillment_type']) ?></small><?php endif; ?>
              </fieldset>
            <?php endif; ?>

            <?php if (in_array('delivery', $fulfillmentModes, true)): ?>
              <div class="field">
                <label for="customer_address">Adresa doručení <span class="field-help">(povinná při volbě Doručení)</span></label>
                <textarea id="customer_address" name="customer_address" class="form-control" autocomplete="street-address" maxlength="1000"
                          <?= $fieldAttributes('customer_address', ['food-order-address-help']) ?>><?= h($fieldValue('customer_address')) ?></textarea>
                <small id="food-order-address-help" class="field-help">Uveďte ulici, číslo, obec a PSČ. Při jiném způsobu převzetí pole nechte prázdné.</small>
                <?php if (isset($fieldErrors['customer_address'])): ?><small id="<?= h($fieldErrorId('customer_address')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['customer_address']) ?></small><?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ((int)($card['order_requested_at_enabled'] ?? 0) === 1): ?>
              <div class="field">
                <label for="requested_at">Požadovaný termín <span aria-hidden="true">*</span></label>
                <input type="datetime-local" id="requested_at" name="requested_at" class="form-control" required aria-required="true"
                       value="<?= h($fieldValue('requested_at')) ?>"
                       <?= $fieldAttributes('requested_at', ['food-order-requested-at-help']) ?>>
                <small id="food-order-requested-at-help" class="field-help">Vyberte budoucí datum a čas. Provozovatel termín teprve potvrdí.</small>
                <?php if (isset($fieldErrors['requested_at'])): ?><small id="<?= h($fieldErrorId('requested_at')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['requested_at']) ?></small><?php endif; ?>
              </div>
            <?php endif; ?>
          </fieldset>
        <?php endif; ?>

        <fieldset class="form-fieldset">
          <legend>Kontaktní údaje</legend>
          <div class="field">
            <label for="customer_name">Vaše jméno <span aria-hidden="true">*</span></label>
            <input type="text" id="customer_name" name="customer_name" class="form-control" required aria-required="true"
                   maxlength="255" autocomplete="name" value="<?= h($fieldValue('customer_name')) ?>"<?= $fieldAttributes('customer_name') ?>>
            <?php if (isset($fieldErrors['customer_name'])): ?><small id="<?= h($fieldErrorId('customer_name')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['customer_name']) ?></small><?php endif; ?>
          </div>

          <div class="field">
            <label for="customer_email">E-mail <span aria-hidden="true">*</span></label>
            <input type="email" id="customer_email" name="customer_email" class="form-control" required aria-required="true"
                   maxlength="255" autocomplete="email" value="<?= h($fieldValue('customer_email')) ?>"<?= $fieldAttributes('customer_email') ?>>
            <?php if (isset($fieldErrors['customer_email'])): ?><small id="<?= h($fieldErrorId('customer_email')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['customer_email']) ?></small><?php endif; ?>
          </div>

          <div class="field">
            <label for="customer_phone">Telefon <span aria-hidden="true">*</span></label>
            <input type="tel" id="customer_phone" name="customer_phone" class="form-control" required aria-required="true"
                   maxlength="80" autocomplete="tel" value="<?= h($fieldValue('customer_phone')) ?>"<?= $fieldAttributes('customer_phone') ?>>
            <?php if (isset($fieldErrors['customer_phone'])): ?><small id="<?= h($fieldErrorId('customer_phone')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['customer_phone']) ?></small><?php endif; ?>
          </div>

          <div class="field">
            <label for="customer_note">Poznámka</label>
            <textarea id="customer_note" name="customer_note" class="form-control" maxlength="3000"
                      <?= $fieldAttributes('customer_note', ['food-order-note-help']) ?>><?= h($fieldValue('customer_note')) ?></textarea>
            <small id="food-order-note-help" class="field-help">Volitelné upřesnění, nejvýše 3000 znaků.</small>
            <?php if (isset($fieldErrors['customer_note'])): ?><small id="<?= h($fieldErrorId('customer_note')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['customer_note']) ?></small><?php endif; ?>
          </div>

          <div class="field">
            <label for="captcha">Ověření: kolik je <?= h((string)$captchaExpr) ?>? <span aria-hidden="true">*</span></label>
            <input type="text" id="captcha" name="captcha" class="form-control form-control--compact" required aria-required="true"
                   inputmode="numeric" autocomplete="off"<?= $fieldAttributes('captcha') ?>>
            <?php if (isset($fieldErrors['captcha'])): ?><small id="<?= h($fieldErrorId('captcha')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['captcha']) ?></small><?php endif; ?>
          </div>

          <div class="button-row button-row--start">
            <button type="submit" class="button-primary">Odeslat nezávaznou poptávku</button>
          </div>
        </fieldset>
      </form>
    <?php endif; ?>
  </section>
</div>
