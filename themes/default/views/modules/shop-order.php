<?php
$order = is_array($order ?? null) ? $order : [];
$items = is_array($items ?? null) ? $items : [];
$payment = is_array($payment ?? null) ? $payment : [];
$seller = is_array($seller ?? null) ? $seller : [];
$legal = is_array($legal ?? null) ? $legal : [];
$authorization = is_array($authorization ?? null) ? $authorization : [];
$invoices = is_array($invoices ?? null) ? $invoices : [];
$canDownload = !empty($canDownload);
$canPay = !empty($canPay);
$withdrawalAvailable = !empty($withdrawalAvailable);
$withdrawalReceipt = is_string($withdrawalReceipt ?? null) ? $withdrawalReceipt : null;
$flash = is_array($flash ?? null) ? $flash : [];
$confirmationSent = !empty($order['confirmation_sent_at'])
    && ($withdrawalReceipt === null || ($order['mail_sent_status'] ?? '') === 'cancelled');
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="shop-order-title">
    <h1 id="shop-order-title" class="section-title section-title--hero">Objednávka <?= h((string)$order['order_number']) ?></h1>
    <?= renderThemeView('modules/shop-navigation') ?>
    <?php if (!empty($flash['message'])): ?><div class="status-message status-message--success" role="status"><p><?= h((string)$flash['message']) ?></p></div><?php endif; ?>
    <div class="status-message status-message--info" role="status">
      <p><strong>Stav: <?= h(shopStates()[(string)$order['status']] ?? 'Nedostupný stav') ?>.</strong></p>
      <?php if ($order['status'] === 'accepted'): ?>
        <p>Objednávka byla přijata ke kontrole země a daňového režimu. Zatím neplaťte. Platební výzva bude vystavena až po ověření prodejcem.</p>
      <?php elseif ($order['status'] === 'awaiting_payment'): ?>
        <p>Objednávka byla přijata a čeká na bankovní převod. Zpřístupnění souboru následuje po ověření přesné platby a splnění podmínek dodání.</p>
      <?php elseif ($order['status'] === 'paid'): ?>
        <p>Platba byla ověřena. Dokončuje se zpřístupnění a oznámení dodání. Pokud soubor zatím není dostupný, kontaktujte prodejce.</p>
      <?php elseif ($order['status'] === 'fulfilled'): ?>
        <p>Objednávka byla vyřízena. Zakoupené soubory můžete stáhnout, pokud je oprávnění stále platné.</p>
      <?php elseif ($order['status'] === 'cancelled'): ?>
        <?php if ($withdrawalReceipt !== null): ?>
          <p>Odstoupení od smlouvy bylo přijato a zaznamenáno. Objednávka byla zrušena; digitální obsah nebude zpřístupněn.</p>
          <?php if (!empty($order['paid_at'])): ?>
            <p><strong>Vrácení přijaté platby dosud není zaznamenáno jako provedené.</strong> Prodejce musí vrácení vyřídit samostatně. Přijetí odstoupení samo neprovádí bankovní příkaz.</p>
          <?php else: ?><p>Tuto objednávku už neplaťte.</p><?php endif; ?>
        <?php else: ?><p>Objednávka byla zrušena. Neplaťte; soubory nejsou dostupné ke stažení.</p><?php endif; ?>
      <?php elseif ($order['status'] === 'refunded'): ?>
        <p>Prodejce zaznamenal již provedené vrácení platby. Přístup ke stažení byl zrušen. Toto oznámení samo neprovádí bankovní příkaz.</p>
      <?php endif; ?>
    </div>
    <?php if ($withdrawalAvailable): ?>
      <section aria-labelledby="shop-order-withdrawal-title">
        <h2 id="shop-order-withdrawal-title" class="section-title">Odstoupení před zpřístupněním obsahu</h2>
        <p>Dokud digitální obsah nebyl zpřístupněn, můžete odstoupit i od zaplacené objednávky. Samotný souhlas s předčasným dodáním právo na odstoupení neruší. Následuje rekapitulace a samostatné potvrzení.</p>
        <p><a class="button-primary" href="<?= h(shopPublicOrderLink('withdraw', $authorization)) ?>">Odstoupit od smlouvy</a></p>
      </section>
    <?php endif; ?>
    <p>Soukromý odkaz k objednávce nesdílejte. Stránka se neukládá do mezipaměti a není určena k indexaci.</p>
    <?php if (!$confirmationSent): ?><p><?= $withdrawalReceipt !== null ? 'Potvrzení přijetí odstoupení e-mailem zatím není označeno jako odeslané. Odstoupení je přesto uložené; pokud potvrzení nepřijde, kontaktujte prodejce.' : 'Potvrzení e-mailem zatím není označeno jako odeslané. Objednávka je přesto přijata; tuto stránku si ponechte pro kontrolu stavu.' ?></p><?php else: ?><p>Odeslání potvrzení e-mailem bylo zaznamenáno. Přijetí poštovním serverem nezaručuje doručení; zkontrolujte také nevyžádanou poštu.</p><?php endif; ?>
    <?php if ($withdrawalReceipt !== null): ?>
      <section aria-labelledby="shop-order-withdrawal-receipt-title">
        <h2 id="shop-order-withdrawal-receipt-title" class="section-title">Potvrzení přijetí odstoupení</h2>
        <p><?= nl2br(h($withdrawalReceipt)) ?></p>
      </section>
    <?php endif; ?>
    <section aria-labelledby="shop-order-customer-title">
      <h2 id="shop-order-customer-title" class="section-title">Údaje objednávky</h2>
      <dl>
        <dt>Objednávka</dt><dd><?= h((string)$order['order_number']) ?></dd>
        <dt>Přijata</dt><dd><?= h((string)$order['created_at']) ?></dd>
        <dt>Kupující</dt><dd><?= h((string)$order['customer_name']) ?></dd>
        <dt>E-mail</dt><dd><?= h((string)$order['email']) ?></dd>
        <dt>Fakturační adresa</dt><dd><?= h((string)$order['address']) ?>, <?= h((string)$order['postal_code']) ?> <?= h((string)$order['city']) ?>, <?= h((string)$order['country_code']) ?></dd>
        <dt>Konečná cena</dt><dd><?= h(shopMoney((int)$order['total_cents'])) ?> (<?= h((string)$order['currency']) ?>)</dd>
        <dt>Zahrnutá daň</dt><dd><?= h(shopMoney((int)$order['tax_cents'])) ?></dd>
        <dt>Platnost soukromého odkazu nejdéle do</dt><dd><?= h((string)$order['token_expires_at']) ?></dd>
      </dl>
    </section>
    <section aria-labelledby="shop-order-items-title">
      <h2 id="shop-order-items-title" class="section-title">Zakoupené produkty</h2>
      <div class="table-shell">
        <table class="data-table">
          <caption>Neměnný obsah objednávky a přístup k zakoupeným souborům</caption>
          <thead><tr><th scope="col">Produkt</th><th scope="col">Počet kusů</th><th scope="col">Cena za kus</th><th scope="col">Celkem</th><th scope="col">Soubor</th></tr></thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <th scope="row"><?= h((string)$item['title']) ?></th>
                <td><?= (int)$item['quantity'] ?></td>
                <td><?= h(shopMoney((int)$item['unit_price_cents'])) ?></td>
                <td><?= h(shopMoney((int)$item['total_cents'])) ?></td>
                <td>
                  <?php if ($canDownload): ?>
                    <a href="<?= h(shopPublicOrderLink('download', $authorization, ['item' => (int)$item['id']])) ?>">Stáhnout <?= h((string)$item['file_original_name']) ?></a> (<?= h(formatFileSize((int)$item['file_size'])) ?>)
                  <?php else: ?>Stažení nyní není dostupné.<?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot><tr><th scope="row" colspan="3">Celkem včetně případné daně</th><td colspan="2"><?= h(shopMoney((int)$order['total_cents'])) ?></td></tr></tfoot>
        </table>
      </div>
    </section>
    <?php if ($canPay): ?>
      <section aria-labelledby="shop-order-payment-title">
        <h2 id="shop-order-payment-title" class="section-title">Platební údaje</h2>
        <p>Zašlete přesně uvedenou částku v CZK se správným variabilním symbolem. Jiná částka, měna nebo symbol automaticky nezpřístupní soubor.</p>
        <dl>
          <dt>Způsob platby</dt><dd><?= h((string)($payment['name'] ?? 'Bankovní převod')) ?></dd>
          <dt>Číslo bankovního účtu</dt><dd><?= h((string)($payment['account_number'] ?? '')) ?></dd>
          <dt>IBAN</dt><dd><?= h((string)($payment['iban'] ?? '')) ?></dd>
          <dt>Částka</dt><dd><?= h(shopMoney((int)$order['total_cents'])) ?></dd>
          <dt>Měna</dt><dd>CZK</dd>
          <dt>Variabilní symbol</dt><dd><?= h((string)$order['order_number']) ?></dd>
        </dl>
        <?php if (isset($invoices['proforma'])): ?>
          <figure aria-labelledby="shop-order-qr-caption">
            <img src="<?= h(shopPublicOrderLink('invoice', $authorization, ['format' => 'qr', 'kind' => 'proforma'])) ?>" alt="qr kód k platbě" width="256" height="256" referrerpolicy="no-referrer">
            <figcaption id="shop-order-qr-caption">QR kód pro bankovní převod. Všechny údaje jsou dostupné také jako text výše; před zaplacením je zkontrolujte.</figcaption>
          </figure>
        <?php else: ?><p>Platební výzva zatím není dostupná. Před platbou kontaktujte prodejce.</p><?php endif; ?>
      </section>
    <?php endif; ?>
    <section aria-labelledby="shop-order-documents-title">
      <h2 id="shop-order-documents-title" class="section-title">Doklady</h2>
      <?php if ($invoices === []): ?><p>Pro aktuální stav objednávky dosud nebyl vystaven doklad. Tato stránka žádné doklady nevytváří.</p><?php else: ?>
        <ul>
          <?php foreach ($invoices as $kind => $label): ?>
            <li><?= h($label) ?>:
              <a href="<?= h(shopPublicOrderLink('invoice', $authorization, ['kind' => $kind, 'format' => 'html'])) ?>"><?= h($label) ?> v HTML</a>,
              <a href="<?= h(shopPublicOrderLink('invoice', $authorization, ['kind' => $kind, 'format' => 'text'])) ?>"><?= h($label) ?> jako text</a>,
              <a href="<?= h(shopPublicOrderLink('invoice', $authorization, ['kind' => $kind, 'format' => 'pdf'])) ?>"><?= h($label) ?> v PDF</a>
            </li>
          <?php endforeach; ?>
        </ul>
        <p>Platební výzva není daňový doklad. Pokud vám PDF nevyhovuje, použijte HTML nebo textovou podobu; shoda PDF/UA není bez ověření tvrzena.</p>
      <?php endif; ?>
    </section>
    <?= renderThemeView('modules/shop-seller', ['settings' => $seller, 'headingId' => 'shop-order-seller']) ?>
    <?= renderThemeView('modules/shop-legal-texts', ['legal' => $legal, 'prefix' => 'shop-order-legal']) ?>
    <section aria-labelledby="shop-order-consent-title">
      <h2 id="shop-order-consent-title" class="section-title">Neměnný záznam souhlasu</h2>
      <p>Souhlasy s obchodními podmínkami, elektronickým dokladem a digitálním dodáním byly zaznamenány <?= h((string)$order['consent_at']) ?>.</p>
      <p><?= h((string)($legal['consent'] ?? '')) ?></p>
      <?php if ($order['status'] === 'fulfilled'): ?>
        <p>Digitální obsah již byl zpřístupněn na základě výslovného souhlasu s dodáním před uplynutím lhůty pro odstoupení. Právo na běžné odstoupení zaniká až tímto zpřístupněním, nikoli samotným souhlasem při objednání. Práva z vad a možnost reklamace zůstávají zachována; kontaktujte prodejce uvedeného výše.</p>
      <?php endif; ?>
      <p><?= h((string)($legal['tax_note'] ?? '')) ?></p>
      <p>Výše jsou uvedeny údaje prodejce a podmínky platné při přijetí této objednávky, nikoli později změněná nabídka.</p>
    </section>
  </section>
</div>
