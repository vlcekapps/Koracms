<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
require_once __DIR__ . '/settings_shared.php';
requireCapability('settings_manage', 'Přístup odepřen. Pro správu nastavení webu nemáte potřebné oprávnění.');

$siteProfiles = siteProfileDefinitions();
$flash = settingsFlashPull();
$formState = settingsDefaultFormState();
if (isset($flash['form']) && is_array($flash['form'])) {
    foreach ($flash['form'] as $fieldName => $fieldValue) {
        if (is_string($fieldName) && array_key_exists($fieldName, $formState) && is_string($fieldValue)) {
            $formState[$fieldName] = $fieldValue;
        }
    }
}
$errors = array_values(array_filter((array)($flash['errors'] ?? []), 'is_string'));
$fieldErrors = array_values(array_unique(array_filter((array)($flash['field_errors'] ?? []), 'is_string')));
$successMessage = is_string($flash['success'] ?? null) ? (string)$flash['success'] : '';
$fieldErrorMessages = settingsFieldErrorMessages();
$flashedFieldErrorMessages = array_filter((array)($flash['field_error_messages'] ?? []), 'is_string');
$fieldMessageFor = static function (string $fieldName) use ($fieldErrorMessages, $flashedFieldErrorMessages): string {
    $message = $flashedFieldErrorMessages[$fieldName] ?? $fieldErrorMessages[$fieldName] ?? '';
    return is_string($message) ? $message : '';
};

if (!isset($siteProfiles[$formState['site_profile']])) {
    $formState['site_profile'] = currentSiteProfileKey();
}

$settingsSections = [
    ['id' => 'settings-basics', 'label' => 'Obecná nastavení'],
    ['id' => 'settings-profile', 'label' => 'Profil webu'],
    ['id' => 'settings-home-sections', 'label' => 'Sekce na domovské stránce'],
    ['id' => 'settings-pagination', 'label' => 'Výpisy a stránkování'],
];
if (isModuleEnabled('blog')) {
    $settingsSections[] = ['id' => 'settings-blog-authors', 'label' => 'Veřejní autoři'];
    $settingsSections[] = ['id' => 'settings-comments', 'label' => 'Komentáře blogu'];
}
$settingsSections[] = ['id' => 'settings-notifications', 'label' => 'E-mailové notifikace'];
$settingsSections[] = ['id' => 'settings-editor', 'label' => 'Obsah a editor'];
$settingsSections[] = ['id' => 'settings-analytics', 'label' => 'Google Analytics a vlastní kód'];
$settingsSections[] = ['id' => 'settings-brand', 'label' => 'Logo a sdílení'];
$settingsSections[] = ['id' => 'settings-privacy', 'label' => 'Soukromí a cookies'];
$settingsSections[] = ['id' => 'settings-integrations', 'label' => 'Integrace'];
$settingsSections[] = ['id' => 'settings-operation', 'label' => 'Provoz webu'];

adminHeader('Nastavení webu');
?>

<?php if ($successMessage !== ''): ?>
  <p class="success" role="status"><?= h($successMessage) ?></p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <ul class="error" role="alert" id="settings-form-errors">
    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
  </ul>
<?php endif; ?>

<p>Tady nastavíte identitu webu, domovskou stránku, komentáře, vzhled i provoz webu. Pokud hledáte heslo nebo osobní údaje účtu, najdete je v <a href="profile.php">Mém profilu</a>.</p>

<nav aria-label="Sekce nastavení webu" class="button-row" style="margin:1rem 0 1.5rem">
  <?php foreach ($settingsSections as $section): ?>
    <a href="#<?= h($section['id']) ?>"><?= h($section['label']) ?></a>
  <?php endforeach; ?>
</nav>

<form method="post" action="settings_save.php" enctype="multipart/form-data" novalidate<?= $errors !== [] ? ' aria-describedby="settings-form-errors"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset id="settings-basics">
    <legend>Obecná nastavení</legend>
    <label for="site_name">Název webu <span aria-hidden="true">*</span></label>
    <input type="text" id="site_name" name="site_name" required aria-required="true"
           value="<?= h($formState['site_name']) ?>"<?= adminFieldAttributes('site_name', $fieldErrors) ?>>
    <?php adminRenderFieldError('site_name', $fieldErrors, [], $fieldMessageFor('site_name')); ?>

    <label for="site_description">Popis webu</label>
    <input type="text" id="site_description" name="site_description"
           value="<?= h($formState['site_description']) ?>">

    <label for="contact_email">E-mail pro kontaktní formulář</label>
    <input type="email" id="contact_email" name="contact_email"
           value="<?= h($formState['contact_email']) ?>"<?= adminFieldAttributes('contact_email', $fieldErrors) ?>>
    <?php adminRenderFieldError('contact_email', $fieldErrors, [], $fieldMessageFor('contact_email')); ?>

    <div style="margin-top:1rem">
      <input type="checkbox" id="public_registration_enabled" name="public_registration_enabled" value="1"
             aria-describedby="public-registration-help"
             <?= $formState['public_registration_enabled'] === '1' ? 'checked' : '' ?>>
      <label for="public_registration_enabled" style="display:inline;font-weight:normal">
        Povolit veřejnou registraci uživatelů
      </label>
    </div>
    <small id="public-registration-help" class="field-help">Když volbu vypnete, veřejná registrace se zablokuje a panel s přihlášením a registrací se návštěvníkům nezobrazí. Nové účty pak může ručně přidávat jen hlavní administrátor.</small>

    <label for="board_public_label">Veřejný název sekce vývěsky</label>
    <input type="text" id="board_public_label" name="board_public_label" maxlength="60"
           aria-describedby="board-public-label-help"
           value="<?= h($formState['board_public_label']) ?>"<?= adminFieldAttributes('board_public_label', $fieldErrors, [], ['board-public-label-help']) ?>>
    <small id="board-public-label-help" class="field-help">Používá se ve veřejné navigaci, na výpisu sekce a na detailu položky. Hodí se například <em>Úřední deska</em>, <em>Vývěska</em> nebo <em>Oznámení</em>. Pokud chcete odkazovat na instituci, bývá srozumitelnější název jako <em>Oznámení obecního úřadu</em> než samotné <em>Obecní úřad</em>.</small>
    <?php adminRenderFieldError('board_public_label', $fieldErrors, [], $fieldMessageFor('board_public_label')); ?>
  </fieldset>

  <fieldset id="settings-profile">
    <legend>Profil webu</legend>
    <p style="margin-top:.25rem;color:#555">Profil pomáhá držet vhodné výchozí moduly, domovskou stránku a doporučenou šablonu pro typ webu, který tvoříte.</p>
    <?php foreach ($siteProfiles as $profileKey => $profile): ?>
      <div style="margin-top:.85rem;padding:.85rem 1rem;border:1px solid #d0d7de;border-radius:10px">
        <input type="radio" id="site_profile_<?= h($profileKey) ?>" name="site_profile" value="<?= h($profileKey) ?>"
               <?= $formState['site_profile'] === $profileKey ? 'checked' : '' ?>>
        <label for="site_profile_<?= h($profileKey) ?>" style="display:inline;margin-top:0"><?= h($profile['label']) ?></label>
        <p style="margin:.45rem 0 0 1.8rem;color:#444"><?= h($profile['description']) ?></p>
      </div>
    <?php endforeach; ?>
    <div style="margin-top:1rem">
      <input type="checkbox" id="apply_site_profile" name="apply_site_profile" value="1" aria-describedby="apply-site-profile-help"
             <?= $formState['apply_site_profile'] === '1' ? 'checked' : '' ?>>
      <label for="apply_site_profile" style="display:inline;font-weight:normal">
        Použít doporučené moduly, pořadí navigace a vzhled pro zvolený profil
      </label>
    </div>
    <small id="apply-site-profile-help" class="field-help">Bez zaškrtnutí se uloží jen zvolený profil webu a stávající konfigurace se nepřepíše. U vlastního profilu zůstane konfigurace beze změny i při použití této volby.</small>
  </fieldset>

  <fieldset id="settings-pagination">
    <legend>Výpisy a stránkování</legend>
    <label for="news_per_page">Novinek na stránku</label>
    <input type="number" id="news_per_page" name="news_per_page" min="1" max="100"
           value="<?= h($formState['news_per_page']) ?>">

    <label for="blog_per_page">Článků blogu na stránku</label>
    <input type="number" id="blog_per_page" name="blog_per_page" min="1" max="100"
           value="<?= h($formState['blog_per_page']) ?>">

    <label for="events_per_page">Akcí na stránku</label>
    <input type="number" id="events_per_page" name="events_per_page" min="1" max="100"
           value="<?= h($formState['events_per_page']) ?>">
  </fieldset>

  <?php if (isModuleEnabled('blog')): ?>
  <fieldset id="settings-blog-authors">
    <legend>Veřejní autoři</legend>

    <div>
      <input type="checkbox" id="blog_authors_index_enabled" name="blog_authors_index_enabled" value="1"
             aria-describedby="blog-authors-index-help"
             <?= $formState['blog_authors_index_enabled'] === '1' ? 'checked' : '' ?>>
      <label for="blog_authors_index_enabled" style="display:inline;font-weight:normal">
        Zobrazovat na blogu odkaz na veřejný seznam autorů
      </label>
    </div>
    <small id="blog-authors-index-help" class="field-help">Když je volba zapnutá, na stránce blogu se zobrazí odkaz na přehled všech veřejných autorů. Výchozí stav je vypnutý.</small>
  </fieldset>

  <fieldset id="settings-comments">
    <legend>Komentáře blogu</legend>

    <div>
      <input type="checkbox" id="comments_enabled" name="comments_enabled" value="1"
             <?= $formState['comments_enabled'] === '1' ? 'checked' : '' ?>>
      <label for="comments_enabled" style="display:inline;font-weight:normal">
        Povolit komentáře u článků blogu
      </label>
    </div>

    <fieldset style="margin-top:1rem;border:1px solid #ccc;padding:.75rem 1rem">
      <legend>Moderace komentářů</legend>
      <p style="margin-top:.25rem;color:#555">Zvolte, kdy se má nový komentář zveřejnit a kdy má čekat na schválení.</p>

      <p>
        <input type="radio" id="comment_moderation_always" name="comment_moderation_mode" value="always"
               <?= $formState['comment_moderation_mode'] === 'always' ? 'checked' : '' ?>>
        <label for="comment_moderation_always" style="display:inline;font-weight:normal">
          Vždy schvalovat každý nový komentář
        </label>
      </p>

      <p>
        <input type="radio" id="comment_moderation_known" name="comment_moderation_mode" value="known"
               <?= $formState['comment_moderation_mode'] === 'known' ? 'checked' : '' ?>>
        <label for="comment_moderation_known" style="display:inline;font-weight:normal">
          Automaticky schválit autora, který už má schválený komentář se stejným e-mailem
        </label>
      </p>

      <p>
        <input type="radio" id="comment_moderation_none" name="comment_moderation_mode" value="none"
               <?= $formState['comment_moderation_mode'] === 'none' ? 'checked' : '' ?>>
        <label for="comment_moderation_none" style="display:inline;font-weight:normal">
          Zveřejnit nový komentář ihned bez schválení
        </label>
      </p>
    </fieldset>

    <label for="comment_close_days">Uzavřít komentáře po kolika dnech od publikace článku</label>
    <input type="number" id="comment_close_days" name="comment_close_days" min="0" max="3650"
           aria-describedby="comment-close-days-help"
           value="<?= h($formState['comment_close_days']) ?>">
    <small id="comment-close-days-help" class="field-help">Hodnota 0 znamená, že se komentáře automaticky neuzavírají.</small>

    <div style="margin-top:1rem">
      <input type="checkbox" id="comment_notify_admin" name="comment_notify_admin" value="1"
             <?= $formState['comment_notify_admin'] === '1' ? 'checked' : '' ?>>
      <label for="comment_notify_admin" style="display:inline;font-weight:normal">
        Poslat upozornění administrátorovi, když nový komentář čeká na schválení
      </label>
    </div>

    <label for="comment_notify_email">E-mail pro upozornění na komentáře</label>
    <input type="email" id="comment_notify_email" name="comment_notify_email"
           aria-describedby="comment-notify-email-help"
           value="<?= h($formState['comment_notify_email']) ?>"<?= adminFieldAttributes('comment_notify_email', $fieldErrors, [], ['comment-notify-email-help']) ?>>
    <small id="comment-notify-email-help" class="field-help">Nepovinné pole. Když ho necháte prázdné, použije se kontaktní e-mail webu.</small>
    <?php adminRenderFieldError('comment_notify_email', $fieldErrors, [], $fieldMessageFor('comment_notify_email')); ?>

    <div style="margin-top:1rem">
      <input type="checkbox" id="comment_notify_author_approve" name="comment_notify_author_approve" value="1" aria-describedby="comment-notify-author-help"
             <?= $formState['comment_notify_author_approve'] === '1' ? 'checked' : '' ?>>
      <label for="comment_notify_author_approve" style="display:inline;font-weight:normal">
        Poslat autorovi e-mail, když jeho komentář schválíte
      </label>
    </div>
    <small id="comment-notify-author-help" class="field-help">Použije se stejná e-mailová vrstva jako u registrace, resetu hesla a rezervací. Odesílá se jen tehdy, když autor vyplnil platný e-mail.</small>

    <label for="comment_blocked_emails">Blokované e-maily a domény</label>
    <textarea id="comment_blocked_emails" name="comment_blocked_emails" rows="4" aria-describedby="comment-blocked-emails-help"><?= h($formState['comment_blocked_emails']) ?></textarea>
    <small id="comment-blocked-emails-help" class="field-help">Jeden záznam na řádek. Použít můžete konkrétní adresu <code>spam@example.com</code> nebo celou doménu <code>@example.com</code>.</small>

    <label for="comment_spam_words">Zakázané fráze v komentářích</label>
    <textarea id="comment_spam_words" name="comment_spam_words" rows="4" aria-describedby="comment-spam-words-help"><?= h($formState['comment_spam_words']) ?></textarea>
    <small id="comment-spam-words-help" class="field-help">Jeden výraz na řádek. Když se objeví ve jméně autora nebo v textu komentáře, komentář skončí ve spamu.</small>
  </fieldset>
  <?php endif; ?>

  <fieldset id="settings-notifications">
    <legend>E-mailové notifikace</legend>
    <p style="margin-top:.5rem;font-size:.9rem">Upozornění se odesílají na e-mail administrátora (případně kontaktní e-mail webu).</p>

    <div style="margin-top:.5rem">
      <input type="checkbox" id="notify_form_submission" name="notify_form_submission" value="1"
             <?= $formState['notify_form_submission'] === '1' ? 'checked' : '' ?>>
      <label for="notify_form_submission" style="display:inline;font-weight:normal">
        Upozornit na nové odeslání formuláře
      </label>
    </div>

    <div style="margin-top:.5rem">
      <input type="checkbox" id="notify_pending_content" name="notify_pending_content" value="1"
             <?= $formState['notify_pending_content'] === '1' ? 'checked' : '' ?>>
      <label for="notify_pending_content" style="display:inline;font-weight:normal">
        Upozornit, když nový obsah čeká na schválení
      </label>
    </div>

    <?php if (isModuleEnabled('chat')): ?>
    <div style="margin-top:.5rem">
      <input type="checkbox" id="notify_chat_message" name="notify_chat_message" value="1"
             aria-describedby="notify-chat-help"
             <?= $formState['notify_chat_message'] === '1' ? 'checked' : '' ?>>
      <label for="notify_chat_message" style="display:inline;font-weight:normal">
        Upozornit na novou zprávu v chatu
      </label>
    </div>
    <small id="notify-chat-help" class="field-help">Může generovat velký počet e-mailů při aktivním chatu.</small>
    <?php endif; ?>
  </fieldset>

  <fieldset id="settings-editor">
    <legend>Obsah a editor</legend>
    <p style="margin-top:.5rem">
      <label style="font-weight:normal">
        <input type="radio" name="content_editor" value="html"
               aria-describedby="content-editor-help"
               <?= $formState['content_editor'] === 'html' ? 'checked' : '' ?>>
        Čisté HTML (textarea) – doporučená a přístupnější volba
      </label>
    </p>
    <p>
      <label style="font-weight:normal">
        <input type="radio" name="content_editor" value="wysiwyg"
               aria-describedby="content-editor-help"
               <?= $formState['content_editor'] === 'wysiwyg' ? 'checked' : '' ?>>
        WYSIWYG editor (Quill) – volitelný vizuální editor pro uživatele bez asistivních technologií
      </label>
    </p>
    <small id="content-editor-help" class="field-help">Pokud používáte čtečku obrazovky nebo jinou asistivní technologii, doporučujeme ponechat čisté HTML (textarea).</small>
  </fieldset>

  <fieldset id="settings-analytics">
    <legend>Google Analytics a vlastní kód</legend>
    <div style="margin-bottom:.75rem">
      <label for="ga4_measurement_id">GA4 Measurement ID</label>
      <input type="text" id="ga4_measurement_id" name="ga4_measurement_id"
             value="<?= h($formState['ga4_measurement_id']) ?>"
             placeholder="G-XXXXXXXXXX" style="width:20rem"
             aria-describedby="ga4-help">
      <small id="ga4-help" class="field-help">Zadejte Google Analytics 4 Measurement ID (např. G-8EV9896EKZ). Snippet se automaticky vloží do hlavičky webu.</small>
    </div>
    <div style="margin-bottom:.75rem">
      <label for="custom_head_code">Vlastní kód do &lt;head&gt;</label>
      <textarea id="custom_head_code" name="custom_head_code" rows="4" style="width:100%;max-width:600px;font-family:monospace;font-size:.85rem"
                aria-describedby="head-code-help"><?= h($formState['custom_head_code']) ?></textarea>
      <small id="head-code-help" class="field-help">HTML/JS kód vložený před &lt;/head&gt;. Slouží pro Google Tag Manager, meta tagy, ověřovací kódy apod.</small>
    </div>
    <div style="margin-bottom:.75rem">
      <label for="custom_footer_code">Vlastní kód před &lt;/body&gt;</label>
      <textarea id="custom_footer_code" name="custom_footer_code" rows="4" style="width:100%;max-width:600px;font-family:monospace;font-size:.85rem"
                aria-describedby="footer-code-help"><?= h($formState['custom_footer_code']) ?></textarea>
      <small id="footer-code-help" class="field-help">HTML/JS kód vložený před zavírací &lt;/body&gt;. Slouží pro analytické skripty, chat widgety apod.</small>
    </div>
  </fieldset>

  <fieldset id="settings-brand">
    <legend>Logo, favicon a sdílení webu</legend>
    <label for="site_favicon">Favicon</label>
    <input type="file" id="site_favicon" name="site_favicon" accept=".ico,.png,image/x-icon,image/png"
           <?= adminFieldAttributes('site_favicon', $fieldErrors, [], array_filter(['site-favicon-help', getSetting('site_favicon', '') !== '' ? 'site-favicon-current' : null])) ?>>
    <small id="site-favicon-help" class="field-help">Povolené formáty: ICO nebo PNG. Maximální velikost je 256 KB.</small>
    <?php adminRenderFieldError('site_favicon', $fieldErrors, [], $fieldMessageFor('site_favicon')); ?>
    <?php $fav = getSetting('site_favicon', ''); ?>
    <?php if ($fav !== ''): ?>
      <div id="site-favicon-current" class="field-help">
        Aktuální favicon:
        <img src="<?= BASE_URL ?>/uploads/site/<?= h($fav) ?>"
             alt="Aktuální favicon" style="height:20px;vertical-align:middle">
      </div>
    <?php endif; ?>

    <label for="site_logo">Logo webu</label>
    <input type="file" id="site_logo" name="site_logo" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
           <?= adminFieldAttributes('site_logo', $fieldErrors, [], array_filter(['site-logo-help', getSetting('site_logo', '') !== '' ? 'site-logo-current' : null])) ?>>
    <small id="site-logo-help" class="field-help">Povolené formáty: JPEG, PNG, GIF nebo WebP. Maximální velikost je 2 MB.</small>
    <?php adminRenderFieldError('site_logo', $fieldErrors, [], $fieldMessageFor('site_logo')); ?>
    <?php $logo = getSetting('site_logo', ''); ?>
    <?php if ($logo !== ''): ?>
      <div id="site-logo-current" class="field-help">
        Aktuální logo:
        <img src="<?= BASE_URL ?>/uploads/site/<?= h($logo) ?>"
             alt="Aktuální logo webu" style="max-height:40px;vertical-align:middle">
      </div>
    <?php endif; ?>

    <label for="og_image_default">Výchozí OG obrázek</label>
    <input type="text" id="og_image_default" name="og_image_default" aria-describedby="og-image-default-help"
           value="<?= h($formState['og_image_default']) ?>">
    <small id="og-image-default-help" class="field-help">Zadejte relativní cestu v <code>uploads/</code>, například <code>site/og.jpg</code>.</small>
  </fieldset>

  <fieldset id="settings-privacy">
    <legend>Soukromí a cookies</legend>
    <div>
      <input type="checkbox" id="cookie_consent_enabled" name="cookie_consent_enabled" value="1"
             <?= $formState['cookie_consent_enabled'] === '1' ? 'checked' : '' ?>>
      <label for="cookie_consent_enabled" style="display:inline;font-weight:normal">
        Zobrazovat cookie lištu
      </label>
    </div>
    <label for="cookie_consent_text">Text cookie lišty</label>
    <textarea id="cookie_consent_text" name="cookie_consent_text"
              rows="2"><?= h($formState['cookie_consent_text']) ?></textarea>
  </fieldset>

  <fieldset id="settings-integrations">
    <legend>Integrace</legend>
    <div>
      <input type="checkbox" id="github_issues_enabled" name="github_issues_enabled" value="1" aria-describedby="github-issues-enabled-help"
             <?= $formState['github_issues_enabled'] === '1' ? 'checked' : '' ?>>
      <label for="github_issues_enabled" style="display:inline;font-weight:normal">
        Povolit vytváření GitHub issues z odpovědí formulářů
      </label>
    </div>
    <small id="github-issues-enabled-help" class="field-help">
      Přímé vytvoření issue funguje jen při nastavené konstantě <code>GITHUB_ISSUES_TOKEN</code> v <code>config.php</code>.
      Bez tokenu zůstane v detailu hlášení připravený návrh pro ruční otevření na GitHubu nebo pro zkopírování.
    </small>

    <label for="github_issues_repository">Výchozí repozitář pro issue bridge</label>
    <input type="text" id="github_issues_repository" name="github_issues_repository"
           value="<?= h($formState['github_issues_repository']) ?>"
           placeholder="owner/repo"
           <?= adminFieldAttributes('github_issues_repository', $fieldErrors, [], ['github-issues-repository-help']) ?>>
    <small id="github-issues-repository-help" class="field-help">
      Nepovinné. Hodnota se předvyplní v detailu odpovědi formuláře a můžete ji tam případně upravit ručně.
      Formát je <code>owner/repo</code>.
      <?= githubIssueBridgeHasToken()
          ? 'Přístupový token je v konfiguraci dostupný.'
          : 'Přístupový token zatím v konfiguraci chybí, takže přímé vytvoření issue ještě nebude dostupné.' ?>
    </small>
    <?php adminRenderFieldError('github_issues_repository', $fieldErrors, [], $fieldErrorMessages['github_issues_repository']); ?>
  </fieldset>

  <fieldset id="settings-operation">
    <legend>Provoz webu</legend>
    <div>
      <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" aria-describedby="maintenance-mode-help"
             <?= $formState['maintenance_mode'] === '1' ? 'checked' : '' ?>>
      <label for="maintenance_mode" style="display:inline;font-weight:normal">
        Zapnout režim údržby
      </label>
    </div>
    <small id="maintenance-mode-help" class="field-help">Přihlášení administrátoři vidí web normálně.</small>
    <label for="maintenance_text">Zpráva pro návštěvníky</label>
    <textarea id="maintenance_text" name="maintenance_text"
              rows="2"><?= h($formState['maintenance_text']) ?></textarea>

    <?php if (isModuleEnabled('chat')): ?>
    <label for="chat_retention_days">Mazat vyřízené chat zprávy po kolika dnech</label>
    <input type="number" id="chat_retention_days" name="chat_retention_days" min="0" max="3650"
           aria-describedby="chat-retention-days-help"
           value="<?= h($formState['chat_retention_days']) ?>">
    <small id="chat-retention-days-help" class="field-help">Hodnota 0 znamená, že se vyřízené chat zprávy automaticky nemažou. Mazání provádí cron.php.</small>
    <?php endif; ?>
  </fieldset>

  <button type="submit" style="margin-top:1rem">Uložit nastavení</button>
</form>

<?php adminFooter(); ?>
