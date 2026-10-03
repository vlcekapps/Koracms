<?php
$captchaExpr = (string)($captchaExpr ?? '');
$fieldErrors = is_array($fieldErrors ?? null) ? $fieldErrors : [];
?>
<fieldset class="form-fieldset">
  <legend>Ověření proti nevyžádaným objednávkám</legend>
  <div class="field">
    <label for="shop-captcha">Kolik je <?= h($captchaExpr) ?>? (povinné)</label>
    <input id="shop-captcha" name="captcha" class="form-control" type="text" inputmode="numeric" autocomplete="off" required value=""<?= shopPublicFieldAttributes($fieldErrors, 'captcha', ['shop-captcha-help']) ?>>
    <p id="shop-captcha-help" class="field-help">Zadejte pouze číslo. Po každém odeslání se ověřovací otázka změní.</p>
    <?= shopPublicFieldError($fieldErrors, 'captcha') ?>
  </div>
</fieldset>
