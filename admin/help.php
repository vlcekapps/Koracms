<?php

require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');
requireHttpMethods(['GET', 'HEAD']);

$canManageMessages = currentUserHasCapability('messages_manage');
$canManageContent = currentUserHasCapability('content_manage_shared') || currentUserHasCapability('blog_manage_own');
$canManageSettings = currentUserHasCapability('settings_manage');
adminHeader('Nápověda a podpora');
?>

<p class="admin-description">Tato stránka je jednotné místo pro orientaci v administraci, redakční pravidla a návazné kontaktní workflow. Odkaz je dostupný ze stejného místa ve spodní části administrační navigace.</p>

<section class="admin-section-spaced" aria-labelledby="admin-help-start-heading">
  <h2 id="admin-help-start-heading">Rychlá orientace</h2>
  <div class="admin-summary-grid admin-stack-sm" role="list" aria-labelledby="admin-help-start-heading">
    <article class="admin-summary-card" role="listitem" aria-labelledby="admin-help-command-heading">
      <h3 id="admin-help-command-heading" class="admin-summary-card__heading">Hledání a zkratky</h3>
      <p>Pro rychlé otevření obrazovek, obsahu a bezpečných akcí použijte hledání v levé navigaci nebo command centrum.</p>
      <p class="admin-summary-card__footer"><a class="btn" href="<?= h(BASE_URL . '/admin/command.php') ?>">Otevřít hledání</a></p>
    </article>

    <article class="admin-summary-card" role="listitem" aria-labelledby="admin-help-profile-heading">
      <h3 id="admin-help-profile-heading" class="admin-summary-card__heading">Účet a zabezpečení</h3>
      <p>V profilu můžete upravit jméno, e-mail, veřejný autorský profil, heslo a dvoufaktorové ověření.</p>
      <p class="admin-summary-card__footer"><a class="btn" href="<?= h(BASE_URL . '/admin/profile.php') ?>">Otevřít profil</a></p>
    </article>

    <?php if ($canManageContent): ?>
      <article class="admin-summary-card" role="listitem" aria-labelledby="admin-help-editorial-heading">
        <h3 id="admin-help-editorial-heading" class="admin-summary-card__heading">Redakční kontrola</h3>
        <p>Před publikací projděte alt texty, přepisy, titulky, jazyk částí textu, odkazy, nadpisy, tabulky, barvy a externí embedy.</p>
        <p class="admin-summary-card__footer">Checklist: <code>docs/accessibility/author-content-checklist.md</code></p>
      </article>
    <?php endif; ?>
  </div>
</section>

<section class="admin-section-spaced" aria-labelledby="admin-help-contact-heading">
  <h2 id="admin-help-contact-heading">Kontakt a návazná podpora</h2>
  <p>Veřejné dotazy návštěvníků řešte v modulech, které jsou pro komunikaci určené. Administrace tím drží jasné oddělení mezi redakční nápovědou, kontaktními zprávami a technickým nastavením.</p>
  <ul class="admin-help-list">
    <?php if ($canManageMessages && isModuleEnabled('contact')): ?>
      <li><a href="<?= h(BASE_URL . '/admin/contact.php') ?>">Kontaktní zprávy</a> pro obecné dotazy z veřejného kontaktního formuláře.</li>
    <?php endif; ?>
    <?php if ($canManageMessages && isModuleEnabled('chat')): ?>
      <li><a href="<?= h(BASE_URL . '/admin/chat.php') ?>">Chat</a> pro rychlé návštěvnické zprávy a témata chatu.</li>
    <?php endif; ?>
    <?php if (isModuleEnabled('forms') && currentUserHasCapability('content_manage_shared')): ?>
      <li><a href="<?= h(BASE_URL . '/admin/forms.php') ?>">Formuláře</a> pro vlastní veřejné formuláře a jejich odpovědi.</li>
    <?php endif; ?>
    <?php if ($canManageSettings): ?>
      <li><a href="<?= h(BASE_URL . '/admin/settings.php') ?>">Obecná nastavení</a> pro kontaktní e-mail, SMTP a další provozní údaje webu.</li>
    <?php endif; ?>
  </ul>
</section>

<section class="admin-section-spaced" aria-labelledby="admin-help-docs-heading">
  <h2 id="admin-help-docs-heading">Dokumentace pro správce</h2>
  <p>Technická a redakční dokumentace je součástí repozitáře, aby šla verzovat spolu s kódem a conformance reportem.</p>
  <ul class="admin-help-list">
    <li><code>docs/admin-guide.md</code> – hlavní administrátorská příručka.</li>
    <li><code>docs/accessibility/wcag-22-aa-conformance.md</code> – pracovní WCAG 2.2 AA matice.</li>
    <li><code>docs/accessibility/manual-test-protocol.md</code> – ruční testovací scénáře pro čtečku, klávesnici, média a reflow.</li>
    <li><code>docs/developer-modules.md</code> – checklist pro návrh a kontrolu nových modulů.</li>
  </ul>
</section>

<?php adminFooter(); ?>
