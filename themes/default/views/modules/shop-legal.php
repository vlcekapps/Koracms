<?php
$ready = !empty($ready);
$settings = is_array($settings ?? null) ? $settings : [];
?>
<div class="listing-shell">
  <section class="surface" aria-labelledby="shop-legal-title">
    <h1 id="shop-legal-title" class="section-title section-title--hero">Prodejce a podmínky nákupu</h1>
    <?= renderThemeView('modules/shop-navigation') ?>
    <?php if (!$ready): ?><div class="status-message status-message--warning" role="status"><p>Prodej nyní není dostupný. Prodejce musí doplnit a ověřit všechna požadovaná nastavení.</p></div><?php endif; ?>
    <section aria-labelledby="shop-legal-withdrawal-function-title">
      <h2 id="shop-legal-withdrawal-function-title" class="section-title">Jak odstoupit od smlouvy online</h2>
      <p>Otevřete soukromý odkaz k objednávce z e-mailového potvrzení. Pokud jste nakoupili přihlášení, objednávku najdete také v části <a href="<?= BASE_URL ?>/shop/my.php">Moje objednávky</a>. Účet se s nákupem hosta nespojuje podle shody e-mailu.</p>
      <p>V detailu dosud nezpřístupněné objednávky je výrazný odkaz <strong>Odstoupit od smlouvy</strong>. Otevře rekapitulaci jména, e-mailu a čísla objednávky; údaje nemusíte znovu opisovat. Odstoupení odešlete až tlačítkem <strong>Potvrdit odstoupení</strong> po novém výslovném potvrzení. Potvrzení přijetí s časem odeslání obdržíte e-mailem v HTML a textové příloze.</p>
      <p>Odstoupit lze i po zaplacení, dokud digitální obsah nebyl zpřístupněn. Prodejce musí případnou přijatou platbu vrátit samostatně; tato funkce sama nepřevádí peníze. Samotný souhlas s předčasným dodáním právo na odstoupení neruší. Při skutečném zpřístupnění s výslovným souhlasem zaniká běžné právo na odstoupení, nikoli práva z vad a možnost reklamace.</p>
      <p>Pokud soukromý odkaz nemáte nebo potřebujete uplatnit práva z vad, obraťte se na prodejce uvedeného níže.</p>
    </section>
    <?= renderThemeView('modules/shop-seller', ['settings' => $settings, 'headingId' => 'shop-legal-seller']) ?>
    <?= renderThemeView('modules/shop-legal-texts', ['legal' => $settings, 'prefix' => 'shop-legal']) ?>
    <section aria-labelledby="shop-legal-delivery">
      <h2 id="shop-legal-delivery" class="section-title">Platba a digitální dodání</h2>
      <p>Platba probíhá bankovním převodem v CZK. Konečná cena je uvedena v rekapitulaci a znovu ověřena na serveru před přijetím objednávky. Soubor bude zpřístupněn až po ověření platby, daňového režimu a výslovného souhlasu s dodáním.</p>
      <p>Prodej je určen spotřebitelům. Do země bez aktivního daňového pravidla nelze objednat. Zahraniční objednávka vyžaduje ověření země a režimu prodejcem před platbou a dodáním.</p>
      <p>Platební výzva není daňový doklad. Doklady mají vedle PDF také přístupnou HTML a textovou podobu. Soukromý odkaz k objednávce nesdílejte s dalšími osobami.</p>
    </section>
  </section>
</div>
