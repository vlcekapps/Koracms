<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('settings_manage', 'Přístup odepřen. Pro správu nastavení webu nemáte potřebné oprávnění.');

$success = false;
$errors  = [];
$successMessage = '';
$siteProfiles = siteProfileDefinitions();
$selectedSiteProfile = currentSiteProfileKey();
$boardPublicLabel = trim($_POST['board_public_label'] ?? getSetting('board_public_label', boardModulePublicLabel()));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $siteName    = trim($_POST['site_name']       ?? '');
    $siteDesc    = trim($_POST['site_description'] ?? '');
    $contactEmail= trim($_POST['contact_email']   ?? '');
    $siteProfile = trim($_POST['site_profile'] ?? $selectedSiteProfile);
    $boardPublicLabel = trim($_POST['board_public_label'] ?? $boardPublicLabel);
    $homeBlog      = max(0, (int)($_POST['home_blog_count'] ?? 5));
    $homeNews      = max(0, (int)($_POST['home_news_count'] ?? 5));
    $newsPerPage   = max(1, (int)($_POST['news_per_page']   ?? 10));
    $blogPerPage   = max(1, (int)($_POST['blog_per_page']   ?? 10));
    $eventsPerPage = max(1, (int)($_POST['events_per_page'] ?? 10));
    if (isModuleEnabled('blog')) {
        $blogAuthorsIndexEnabled = isset($_POST['blog_authors_index_enabled']) ? '1' : '0';
        $commentsEnabled = isset($_POST['comments_enabled']) ? '1' : '0';
        $commentModerationMode = in_array($_POST['comment_moderation_mode'] ?? '', ['always', 'known', 'none'], true)
            ? (string)$_POST['comment_moderation_mode']
            : 'always';
        $commentCloseDays = max(0, min(3650, (int)($_POST['comment_close_days'] ?? 0)));
        $commentNotifyAdmin = isset($_POST['comment_notify_admin']) ? '1' : '0';
        $commentNotifyAuthorOnApprove = isset($_POST['comment_notify_author_approve']) ? '1' : '0';
        $commentNotifyEmail = trim($_POST['comment_notify_email'] ?? '');
        $commentBlockedEmails = trim(str_replace("\r", '', $_POST['comment_blocked_emails'] ?? ''));
        $commentSpamWords = trim(str_replace("\r", '', $_POST['comment_spam_words'] ?? ''));
    } else {
        $blogAuthorsIndexEnabled = getSetting('blog_authors_index_enabled', '0');
        $commentsEnabled = getSetting('comments_enabled', '1');
        $commentModerationMode = commentModerationMode();
        $commentCloseDays = commentCloseDays();
        $commentNotifyAdmin = getSetting('comment_notify_admin', '1');
        $commentNotifyAuthorOnApprove = getSetting('comment_notify_author_approve', '0');
        $commentNotifyEmail = getSetting('comment_notify_email', '');
        $commentBlockedEmails = getSetting('comment_blocked_emails', '');
        $commentSpamWords = getSetting('comment_spam_words', '');
    }
    $contentEditor = in_array($_POST['content_editor'] ?? '', ['html', 'wysiwyg']) ? $_POST['content_editor'] : 'html';
    $applySiteProfile = isset($_POST['apply_site_profile']);

    if (!isset($siteProfiles[$siteProfile])) {
        $errors[] = 'Vyberte platný profil webu.';
        $siteProfile = currentSiteProfileKey();
    }
    if ($boardPublicLabel === '') {
        $boardPublicLabel = defaultBoardPublicLabelForProfile($siteProfile);
    }
    $selectedSiteProfile = $siteProfile;

    if ($siteName === '') $errors[] = 'Název webu je povinný.';
    if (mb_strlen($boardPublicLabel, 'UTF-8') > 60) $errors[] = 'Veřejný název sekce vývěsky může mít nejvýše 60 znaků.';
    if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Neplatná e-mailová adresa pro kontakt.';

    if ($commentNotifyEmail !== '' && !filter_var($commentNotifyEmail, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Neplatná e-mailová adresa pro upozornění na komentáře.';

    if (empty($errors)) {
        saveSetting('site_name',        $siteName);
        saveSetting('site_description', $siteDesc);
        saveSetting('contact_email',    $contactEmail);
        saveSetting('site_profile',     $siteProfile);
        saveSetting('board_public_label', $boardPublicLabel);
        saveSetting('home_blog_count',  (string)$homeBlog);
        saveSetting('home_news_count',  (string)$homeNews);
        saveSetting('news_per_page',    (string)$newsPerPage);
        saveSetting('blog_per_page',    (string)$blogPerPage);
        saveSetting('events_per_page',  (string)$eventsPerPage);
        saveSetting('blog_authors_index_enabled', $blogAuthorsIndexEnabled);
        saveSetting('comments_enabled', $commentsEnabled);
        saveSetting('comment_moderation_mode', $commentModerationMode);
        saveSetting('comment_close_days', (string)$commentCloseDays);
        saveSetting('comment_notify_admin', $commentNotifyAdmin);
        saveSetting('comment_notify_author_approve', $commentNotifyAuthorOnApprove);
        saveSetting('comment_notify_email', $commentNotifyEmail);
        saveSetting('comment_blocked_emails', $commentBlockedEmails);
        saveSetting('comment_spam_words', $commentSpamWords);
        saveSetting('content_editor',   $contentEditor);
        $homeBoard     = max(0, (int)($_POST['home_board_count'] ?? 5));
        saveSetting('home_board_count', (string)$homeBoard);
        logAction('settings_save');

        saveSetting('social_facebook',  trim($_POST['social_facebook']  ?? ''));
        saveSetting('social_youtube',   trim($_POST['social_youtube']   ?? ''));
        saveSetting('social_instagram', trim($_POST['social_instagram'] ?? ''));
        saveSetting('social_twitter',   trim($_POST['social_twitter']   ?? ''));

        // Maintenance mód
        saveSetting('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');
        saveSetting('maintenance_text', trim($_POST['maintenance_text'] ?? ''));

        // Cookie lišta
        saveSetting('cookie_consent_enabled', isset($_POST['cookie_consent_enabled']) ? '1' : '0');
        saveSetting('cookie_consent_text',    trim($_POST['cookie_consent_text'] ?? ''));

        // OG image default (textové pole – URL nebo cesta v uploads/)
        saveSetting('og_image_default', trim($_POST['og_image_default'] ?? ''));
        saveSetting('home_intro',       trim($_POST['home_intro']       ?? ''));

        // Favicon – nahrání souboru
        $siteDir = __DIR__ . '/../uploads/site/';
        if (!is_dir($siteDir)) mkdir($siteDir, 0755, true);

        if (!empty($_FILES['site_favicon']['name'])) {
            $tmp   = $_FILES['site_favicon']['tmp_name'];
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($tmp);
            $allowedFav = ['image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico',
                           'image/png' => 'png', 'image/svg+xml' => 'svg'];
            if (isset($allowedFav[$mime])) {
                $fname = 'favicon.' . $allowedFav[$mime];
                if (move_uploaded_file($tmp, $siteDir . $fname)) {
                    saveSetting('site_favicon', $fname);
                }
            } else {
                $errors[] = 'Favicon: nepodporovaný formát (povoleno: ICO, PNG, SVG).';
            }
        }

        // Logo – nahrání souboru
        if (!empty($_FILES['site_logo']['name'])) {
            $tmp   = $_FILES['site_logo']['tmp_name'];
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($tmp);
            $allowedImg = ['image/jpeg' => 'jpg', 'image/png' => 'png',
                           'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
            if (isset($allowedImg[$mime])) {
                $ext   = $allowedImg[$mime];
                $fname = 'logo.' . $ext;
                if (move_uploaded_file($tmp, $siteDir . $fname)) {
                    saveSetting('site_logo', $fname);
                }
            } else {
                $errors[] = 'Logo: nepodporovaný formát (povoleno: JPEG, PNG, GIF, WebP, SVG).';
            }
        }

        if (empty($errors) && $applySiteProfile) {
            applySiteProfilePreset($siteProfile);
            if (siteProfileSupportsPreset($siteProfile)) {
                $successMessage = 'Doporučené přednastavení profilu bylo použito.';
            } else {
                $successMessage = 'Vlastní profil byl uložen bez zásahu do stávajících modulů a vzhledu.';
            }
        }
        if (empty($errors)) $success = true;
    }
}

$settingsSections = [
    ['id' => 'settings-homepage', 'label' => 'Domovská stránka'],
    ['id' => 'settings-basics', 'label' => 'Obecná nastavení'],
    ['id' => 'settings-profile', 'label' => 'Profil webu'],
    ['id' => 'settings-home-sections', 'label' => 'Sekce na domovské stránce'],
    ['id' => 'settings-pagination', 'label' => 'Výpisy a stránkování'],
];
if (isModuleEnabled('blog')) {
    $settingsSections[] = ['id' => 'settings-blog-authors', 'label' => 'Veřejní autoři'];
    $settingsSections[] = ['id' => 'settings-comments', 'label' => 'Komentáře blogu'];
}
$settingsSections[] = ['id' => 'settings-editor', 'label' => 'Obsah a editor'];
$settingsSections[] = ['id' => 'settings-social', 'label' => 'Sociální sítě'];
$settingsSections[] = ['id' => 'settings-brand', 'label' => 'Logo a sdílení'];
$settingsSections[] = ['id' => 'settings-privacy', 'label' => 'Soukromí a cookies'];
$settingsSections[] = ['id' => 'settings-operation', 'label' => 'Provoz webu'];

adminHeader('Nastavení webu');
?>

<?php if ($success): ?>
  <p class="success" role="status">Nastavení bylo uloženo.</p>
<?php endif; ?>

<?php if ($success && $successMessage !== ''): ?>
  <p class="success" role="status"><?= h($successMessage) ?></p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <ul class="error" role="alert">
    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
  </ul>
<?php endif; ?>

<p>Tady nastavíte identitu webu, domovskou stránku, komentáře, vzhled i provoz webu. Pokud hledáte heslo nebo osobní údaje účtu, najdete je v <a href="profile.php">Mém profilu</a>.</p>

<nav aria-label="Sekce nastavení webu" class="button-row" style="margin:1rem 0 1.5rem">
  <?php foreach ($settingsSections as $section): ?>
    <a href="#<?= h($section['id']) ?>"><?= h($section['label']) ?></a>
  <?php endforeach; ?>
</nav>

<form method="post" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset id="settings-homepage">
    <legend>Domovská stránka</legend>
    <p>Pokud text vyplníte, zobrazí se na domovské stránce nad hlavním obsahem. Když pole necháte prázdné, úvodní blok se nezobrazí.</p>
    <label for="home_intro" class="sr-only">Úvodní text</label>
    <textarea id="home_intro" name="home_intro" rows="6" aria-describedby="home-intro-help"><?= h(getSetting('home_intro', '')) ?></textarea>
    <small id="home-intro-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small>
    <?php renderAdminContentReferencePicker('home_intro'); ?>
  </fieldset>

  <fieldset id="settings-basics">
    <legend>Obecná nastavení</legend>
    <label for="site_name">Název webu <span aria-hidden="true">*</span></label>
    <input type="text" id="site_name" name="site_name" required aria-required="true"
           value="<?= h(getSetting('site_name')) ?>">

    <label for="site_description">Popis webu</label>
    <input type="text" id="site_description" name="site_description"
           value="<?= h(getSetting('site_description')) ?>">

    <label for="contact_email">E-mail pro kontaktní formulář</label>
    <input type="email" id="contact_email" name="contact_email"
           value="<?= h(getSetting('contact_email')) ?>">

    <label for="board_public_label">Veřejný název sekce vývěsky</label>
    <input type="text" id="board_public_label" name="board_public_label" maxlength="60"
           aria-describedby="board-public-label-help"
           value="<?= h($boardPublicLabel) ?>">
    <small id="board-public-label-help" class="field-help">Používá se ve veřejné navigaci, na výpisu sekce a na detailu položky. Hodí se například <em>Úřední deska</em>, <em>Vývěska</em> nebo <em>Oznámení</em>. Pokud chcete odkazovat na instituci, bývá srozumitelnější název jako <em>Oznámení obecního úřadu</em> než samotné <em>Obecní úřad</em>.</small>
  </fieldset>

  <fieldset id="settings-profile">
    <legend>Profil webu</legend>
    <p style="margin-top:.25rem;color:#555">Profil pomáhá držet vhodné výchozí moduly, domovskou stránku a doporučenou šablonu pro typ webu, který tvoříte.</p>
    <?php foreach ($siteProfiles as $profileKey => $profile): ?>
      <div style="margin-top:.85rem;padding:.85rem 1rem;border:1px solid #d0d7de;border-radius:10px">
        <input type="radio" id="site_profile_<?= h($profileKey) ?>" name="site_profile" value="<?= h($profileKey) ?>"
               <?= $selectedSiteProfile === $profileKey ? 'checked' : '' ?>>
        <label for="site_profile_<?= h($profileKey) ?>" style="display:inline;margin-top:0"><?= h($profile['label']) ?></label>
        <p style="margin:.45rem 0 0 1.8rem;color:#444"><?= h($profile['description']) ?></p>
      </div>
    <?php endforeach; ?>
    <div style="margin-top:1rem">
      <input type="checkbox" id="apply_site_profile" name="apply_site_profile" value="1" aria-describedby="apply-site-profile-help"
             <?= isset($_POST['apply_site_profile']) ? 'checked' : '' ?>>
      <label for="apply_site_profile" style="display:inline;font-weight:normal">
        Použít doporučené moduly, pořadí navigace a vzhled pro zvolený profil
      </label>
    </div>
    <small id="apply-site-profile-help" class="field-help">Bez zaškrtnutí se uloží jen zvolený profil webu a stávající konfigurace se nepřepíše. U vlastního profilu zůstane konfigurace beze změny i při použití této volby.</small>
  </fieldset>

  <fieldset id="settings-home-sections">
    <legend>Sekce na domovské stránce</legend>
    <p style="margin-top:.25rem;font-size:.9rem;color:#555">Hodnota 0 znamená, že se sekce na domovské stránce nezobrazí.</p>
    <label for="home_blog_count">Počet článků na domovské stránce</label>
    <input type="number" id="home_blog_count" name="home_blog_count" min="0" max="50"
           value="<?= h(getSetting('home_blog_count', '5')) ?>">

    <label for="home_news_count">Počet novinek na domovské stránce</label>
    <input type="number" id="home_news_count" name="home_news_count" min="0" max="50"
           value="<?= h(getSetting('home_news_count', '5')) ?>">

    <label for="home_board_count">Počet položek sekce <?= h($boardPublicLabel) ?> na domovské stránce</label>
    <input type="number" id="home_board_count" name="home_board_count" min="0" max="50"
           value="<?= h(getSetting('home_board_count', '5')) ?>">
  </fieldset>

  <fieldset id="settings-pagination">
    <legend>Výpisy a stránkování</legend>
    <label for="news_per_page">Novinek na stránku</label>
    <input type="number" id="news_per_page" name="news_per_page" min="1" max="100"
           value="<?= h(getSetting('news_per_page', '10')) ?>">

    <label for="blog_per_page">Článků blogu na stránku</label>
    <input type="number" id="blog_per_page" name="blog_per_page" min="1" max="100"
           value="<?= h(getSetting('blog_per_page', '10')) ?>">

    <label for="events_per_page">Akcí na stránku</label>
    <input type="number" id="events_per_page" name="events_per_page" min="1" max="100"
           value="<?= h(getSetting('events_per_page', '10')) ?>">
  </fieldset>

  <?php if (isModuleEnabled('blog')): ?>
  <fieldset id="settings-blog-authors">
    <legend>Veřejní autoři</legend>

    <div>
      <input type="checkbox" id="blog_authors_index_enabled" name="blog_authors_index_enabled" value="1"
             aria-describedby="blog-authors-index-help"
             <?= getSetting('blog_authors_index_enabled', '0') === '1' ? 'checked' : '' ?>>
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
             <?= getSetting('comments_enabled', '1') === '1' ? 'checked' : '' ?>>
      <label for="comments_enabled" style="display:inline;font-weight:normal">
        Povolit komentáře u článků blogu
      </label>
    </div>

    <fieldset style="margin-top:1rem;border:1px solid #ccc;padding:.75rem 1rem">
      <legend>Moderace komentářů</legend>
      <p style="margin-top:.25rem;color:#555">Zvolte, kdy se má nový komentář zveřejnit a kdy má čekat na schválení.</p>

      <p>
        <input type="radio" id="comment_moderation_always" name="comment_moderation_mode" value="always"
               <?= commentModerationMode() === 'always' ? 'checked' : '' ?>>
        <label for="comment_moderation_always" style="display:inline;font-weight:normal">
          Vždy schvalovat každý nový komentář
        </label>
      </p>

      <p>
        <input type="radio" id="comment_moderation_known" name="comment_moderation_mode" value="known"
               <?= commentModerationMode() === 'known' ? 'checked' : '' ?>>
        <label for="comment_moderation_known" style="display:inline;font-weight:normal">
          Automaticky schválit autora, který už má schválený komentář se stejným e-mailem
        </label>
      </p>

      <p>
        <input type="radio" id="comment_moderation_none" name="comment_moderation_mode" value="none"
               <?= commentModerationMode() === 'none' ? 'checked' : '' ?>>
        <label for="comment_moderation_none" style="display:inline;font-weight:normal">
          Zveřejnit nový komentář ihned bez schválení
        </label>
      </p>
    </fieldset>

    <label for="comment_close_days">Uzavřít komentáře po kolika dnech od publikace článku</label>
    <input type="number" id="comment_close_days" name="comment_close_days" min="0" max="3650"
           aria-describedby="comment-close-days-help"
           value="<?= h(getSetting('comment_close_days', '0')) ?>">
    <small id="comment-close-days-help" class="field-help">Hodnota 0 znamená, že se komentáře automaticky neuzavírají.</small>

    <div style="margin-top:1rem">
      <input type="checkbox" id="comment_notify_admin" name="comment_notify_admin" value="1"
             <?= getSetting('comment_notify_admin', '1') === '1' ? 'checked' : '' ?>>
      <label for="comment_notify_admin" style="display:inline;font-weight:normal">
        Poslat upozornění administrátorovi, když nový komentář čeká na schválení
      </label>
    </div>

    <label for="comment_notify_email">E-mail pro upozornění na komentáře</label>
    <input type="email" id="comment_notify_email" name="comment_notify_email"
           aria-describedby="comment-notify-email-help"
           value="<?= h(getSetting('comment_notify_email', '')) ?>">
    <small id="comment-notify-email-help" class="field-help">Nepovinné pole. Když ho necháte prázdné, použije se kontaktní e-mail webu.</small>

    <div style="margin-top:1rem">
      <input type="checkbox" id="comment_notify_author_approve" name="comment_notify_author_approve" value="1" aria-describedby="comment-notify-author-help"
             <?= getSetting('comment_notify_author_approve', '0') === '1' ? 'checked' : '' ?>>
      <label for="comment_notify_author_approve" style="display:inline;font-weight:normal">
        Poslat autorovi e-mail, když jeho komentář schválíte
      </label>
    </div>
    <small id="comment-notify-author-help" class="field-help">Použije se stejná e-mailová vrstva jako u registrace, resetu hesla a rezervací. Odesílá se jen tehdy, když autor vyplnil platný e-mail.</small>

    <label for="comment_blocked_emails">Blokované e-maily a domény</label>
    <textarea id="comment_blocked_emails" name="comment_blocked_emails" rows="4" aria-describedby="comment-blocked-emails-help"><?= h(getSetting('comment_blocked_emails', '')) ?></textarea>
    <small id="comment-blocked-emails-help" class="field-help">Jeden záznam na řádek. Použít můžete konkrétní adresu <code>spam@example.com</code> nebo celou doménu <code>@example.com</code>.</small>

    <label for="comment_spam_words">Zakázané fráze v komentářích</label>
    <textarea id="comment_spam_words" name="comment_spam_words" rows="4" aria-describedby="comment-spam-words-help"><?= h(getSetting('comment_spam_words', '')) ?></textarea>
    <small id="comment-spam-words-help" class="field-help">Jeden výraz na řádek. Když se objeví ve jméně autora nebo v textu komentáře, komentář skončí ve spamu.</small>
  </fieldset>
  <?php endif; ?>

  <fieldset id="settings-editor">
    <legend>Obsah a editor</legend>
    <p style="margin-top:.5rem">
      <label style="font-weight:normal">
        <input type="radio" name="content_editor" value="html"
               aria-describedby="content-editor-help"
               <?= getSetting('content_editor', 'html') === 'html' ? 'checked' : '' ?>>
        Čisté HTML (textarea) – doporučená a přístupnější volba
      </label>
    </p>
    <p>
      <label style="font-weight:normal">
        <input type="radio" name="content_editor" value="wysiwyg"
               aria-describedby="content-editor-help"
               <?= getSetting('content_editor', 'html') === 'wysiwyg' ? 'checked' : '' ?>>
        WYSIWYG editor (Quill) – volitelný vizuální editor pro uživatele bez asistivních technologií
      </label>
    </p>
    <small id="content-editor-help" class="field-help">Pokud používáte čtečku obrazovky nebo jinou asistivní technologii, doporučujeme ponechat čisté HTML (textarea).</small>
  </fieldset>

  <fieldset id="settings-social">
    <legend>Sociální sítě</legend>
    <label for="social_facebook">Facebook (URL)</label>
    <input type="url" id="social_facebook" name="social_facebook"
           value="<?= h(getSetting('social_facebook')) ?>">
    <label for="social_youtube">YouTube (URL)</label>
    <input type="url" id="social_youtube" name="social_youtube"
           value="<?= h(getSetting('social_youtube')) ?>">
    <label for="social_instagram">Instagram (URL)</label>
    <input type="url" id="social_instagram" name="social_instagram"
           value="<?= h(getSetting('social_instagram')) ?>">
    <label for="social_twitter">X / Twitter (URL)</label>
    <input type="url" id="social_twitter" name="social_twitter"
           value="<?= h(getSetting('social_twitter')) ?>">
  </fieldset>

  <fieldset id="settings-brand">
    <legend>Logo, favicon a sdílení webu</legend>
    <label for="site_favicon">Favicon</label>
    <input type="file" id="site_favicon" name="site_favicon" accept=".ico,.png,.svg,image/x-icon,image/png,image/svg+xml"
           aria-describedby="site-favicon-help<?= getSetting('site_favicon', '') !== '' ? ' site-favicon-current' : '' ?>">
    <small id="site-favicon-help" class="field-help">Povolené formáty: ICO, PNG nebo SVG. Maximální velikost je 256 KB.</small>
    <?php $fav = getSetting('site_favicon', ''); ?>
    <?php if ($fav !== ''): ?>
      <div id="site-favicon-current" class="field-help">
        Aktuální favicon:
        <img src="<?= BASE_URL ?>/uploads/site/<?= h($fav) ?>"
             alt="Aktuální favicon" style="height:20px;vertical-align:middle">
      </div>
    <?php endif; ?>

    <label for="site_logo">Logo webu</label>
    <input type="file" id="site_logo" name="site_logo" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,image/*"
           aria-describedby="site-logo-help<?= getSetting('site_logo', '') !== '' ? ' site-logo-current' : '' ?>">
    <small id="site-logo-help" class="field-help">Povolené formáty: JPEG, PNG, WebP nebo SVG. Maximální velikost je 2 MB.</small>
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
           value="<?= h(getSetting('og_image_default', '')) ?>">
    <small id="og-image-default-help" class="field-help">Zadejte relativní cestu v <code>uploads/</code>, například <code>site/og.jpg</code>.</small>
  </fieldset>

  <fieldset id="settings-privacy">
    <legend>Soukromí a cookies</legend>
    <div>
      <input type="checkbox" id="cookie_consent_enabled" name="cookie_consent_enabled" value="1"
             <?= getSetting('cookie_consent_enabled', '0') === '1' ? 'checked' : '' ?>>
      <label for="cookie_consent_enabled" style="display:inline;font-weight:normal">
        Zobrazovat cookie lištu
      </label>
    </div>
    <label for="cookie_consent_text">Text cookie lišty</label>
    <textarea id="cookie_consent_text" name="cookie_consent_text"
              rows="2"><?= h(getSetting('cookie_consent_text',
              'Tento web používá soubory cookies ke zlepšení vašeho zážitku z prohlížení.')) ?></textarea>
  </fieldset>

  <fieldset id="settings-operation">
    <legend>Provoz webu</legend>
    <div>
      <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" aria-describedby="maintenance-mode-help"
             <?= getSetting('maintenance_mode', '0') === '1' ? 'checked' : '' ?>>
      <label for="maintenance_mode" style="display:inline;font-weight:normal">
        Zapnout režim údržby
      </label>
    </div>
    <small id="maintenance-mode-help" class="field-help">Přihlášení administrátoři vidí web normálně.</small>
    <label for="maintenance_text">Zpráva pro návštěvníky</label>
    <textarea id="maintenance_text" name="maintenance_text"
              rows="2"><?= h(getSetting('maintenance_text',
              'Právě probíhá údržba webu. Brzy budeme zpět, děkujeme za trpělivost.')) ?></textarea>
  </fieldset>

  <button type="submit" style="margin-top:1rem">Uložit nastavení</button>
</form>

<?php adminFooter(); ?>
