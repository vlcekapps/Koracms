<?php
$order = is_array($order ?? null) ? $order : [];
$authorization = is_array($authorization ?? null) ? $authorization : [];
$available = !empty($available);
$flash = is_array($flash ?? null) ? $flash : [];
$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
$fieldErrors = is_array($flash['fieldErrors'] ?? null) ? $flash['fieldErrors'] : [];
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="shop-withdraw-title">
    <h1 id="shop-withdraw-title" class="section-title section-title--hero">Odstoupení od smlouvy</h1>
    <?= renderThemeView('modules/shop-navigation') ?>
    <p>Otevření této rekapitulace objednávku nemění. Zkontrolujte údaje smlouvy a e-mail, na který bude odesláno potvrzení přijetí odstoupení. Údaje nemusíte znovu opisovat.</p>
    <?php if ($errors !== []): ?>
      <div id="shop-withdraw-errors" class="status-message status-message--error" role="alert">
        <h2 class="section-title section-title--compact">Odstoupení nyní nelze potvrdit</h2>
        <ul><?php foreach ($errors as $error): ?><li><?= h((string)$error) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>
    <section aria-labelledby="shop-withdraw-contract-title">
      <h2 id="shop-withdraw-contract-title" class="section-title">Smlouva a údaje pro potvrzení</h2>
      <dl id="shop-withdraw-contract">
        <dt>Číslo objednávky</dt><dd><?= h((string)$order['order_number']) ?></dd>
        <dt>Jméno kupujícího</dt><dd><?= h((string)$order['customer_name']) ?></dd>
        <dt>E-mail pro potvrzení</dt><dd><?= h((string)$order['email']) ?></dd>
        <dt>Stav objednávky</dt><dd><?= h(shopStates()[(string)$order['status']] ?? 'Nedostupný stav') ?></dd>
        <dt>Celková cena smlouvy</dt><dd><?= h(shopMoney((int)$order['total_cents'])) ?></dd>
      </dl>
    </section>
    <?php if ($available): ?>
      <p id="shop-withdraw-consequences">Odstoupení se týká celé uvedené smlouvy. Objednávka bude zrušena a digitální obsah nebude zpřístupněn. Samotný souhlas při objednání neruší právo na odstoupení před skutečným zpřístupněním obsahu.</p>
      <?php if (!empty($order['paid_at'])): ?>
        <p>Platba byla přijata. Prodejce musí samostatně vyřídit její vrácení. Potvrzení odstoupení samo peníze nevrací ani neoznačuje refundaci za provedenou.</p>
      <?php else: ?>
        <p>Po přijetí odstoupení už tuto objednávku neplaťte.</p>
      <?php endif; ?>
      <form action="<?= h(shopPublicOrderLink('withdraw', $authorization)) ?>" method="post" class="form-stack" autocomplete="off" novalidate<?= $errors !== [] ? ' aria-describedby="shop-withdraw-errors"' : '' ?>>
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <?= honeypotField() ?>
        <fieldset class="form-fieldset">
          <legend>Potvrzení odstoupení od celé smlouvy</legend>
          <label class="choice-card" for="shop-confirm-withdrawal">
            <input id="shop-confirm-withdrawal" type="checkbox" name="confirm_withdrawal" value="1" required autocomplete="off"<?= shopPublicFieldAttributes($fieldErrors, 'confirm_withdrawal', ['shop-withdraw-contract', 'shop-withdraw-consequences']) ?>>
            <span>Potvrzuji uvedené údaje a odstupuji od celé smlouvy pro objednávku <?= h((string)$order['order_number']) ?>.</span>
          </label>
          <?= shopPublicFieldError($fieldErrors, 'confirm_withdrawal') ?>
          <p>Potvrzení s obsahem prohlášení a časem jeho přijetí bude odesláno na výše uvedený e-mail v HTML a textové příloze. Práva z vad nejsou odstoupením dotčena.</p>
          <button type="submit" class="button-primary">Potvrdit odstoupení</button>
        </fieldset>
      </form>
    <?php else: ?>
      <div class="status-message status-message--info" role="status">
        <?php if ($order['status'] === 'fulfilled' || !empty($order['fulfilled_at'])): ?>
          <p>Digitální obsah již byl zpřístupněn. Při předčasném dodání s výslovným souhlasem tím zaniká běžné právo na odstoupení. Práva z vad a možnost reklamace zůstávají zachována; obraťte se na prodejce uvedeného v objednávce.</p>
        <?php else: ?>
          <p>Objednávka již není otevřená pro odstoupení. Případné již přijaté odstoupení a stav vrácení platby najdete v detailu objednávky.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <p><a class="button-secondary" href="<?= h(shopPublicOrderLink('order', $authorization)) ?>">Zpět k objednávce</a></p>
  </section>
</div>
