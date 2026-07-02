<?php
$card = is_array($card ?? null) ? $card : [];
$selectableItems = is_array($selectableItems ?? null) ? $selectableItems : [];
$fieldErrors = is_array($fieldErrors ?? null) ? $fieldErrors : [];
$errors = is_array($errors ?? null) ? $errors : [];
$formData = is_array($formData ?? null) ? $formData : [];
$referenceCode = trim((string)($referenceCode ?? ''));
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
          <p id="food-order-items-help" class="field-help field-help--flush">U položek, které chcete poptat, zadejte množství. Položky s nulou se neodešlou.</p>
          <?php if (isset($fieldErrors['items'])): ?><small id="<?= h($fieldErrorId('items')) ?>" class="field-help field-error"><?= h((string)$fieldErrors['items']) ?></small><?php endif; ?>
          <div class="food-order-items" aria-describedby="<?= h(implode(' ', $itemsDescriptionIds)) ?>">
            <?php foreach ($selectableItems as $item): ?>
              <?php
              $itemId = (int)($item['id'] ?? 0);
              $quantityId = 'food-order-qty-' . $itemId;
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
                       name="qty[<?= $itemId ?>]" value="<?= h((string)($quantities[$itemId] ?? '0')) ?>" inputmode="numeric">
              </div>
            <?php endforeach; ?>
          </div>
        </fieldset>

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
            <textarea id="customer_note" name="customer_note" class="form-control"><?= h($fieldValue('customer_note')) ?></textarea>
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
