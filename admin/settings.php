<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $siteName    = trim($_POST['site_name']       ?? '');
    $siteDesc    = trim($_POST['site_description'] ?? '');
    $contactEmail= trim($_POST['contact_email']   ?? '');
    $homeBlog      = max(1, (int)($_POST['home_blog_count'] ?? 5));
    $homeNews      = max(1, (int)($_POST['home_news_count'] ?? 5));
    $newsPerPage   = max(1, (int)($_POST['news_per_page']   ?? 10));
    $blogPerPage   = max(1, (int)($_POST['blog_per_page']   ?? 10));
    $eventsPerPage = max(1, (int)($_POST['events_per_page'] ?? 10));
    $contentEditor = in_array($_POST['content_editor'] ?? '', ['html', 'wysiwyg']) ? $_POST['content_editor'] : 'html';

    if ($siteName === '') $errors[] = 'Název webu je povinný.';
    if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Neplatná e-mailová adresa pro kontakt.';

    if (empty($errors)) {
        saveSetting('site_name',        $siteName);
        saveSetting('site_description', $siteDesc);
        saveSetting('contact_email',    $contactEmail);
        saveSetting('home_blog_count',  (string)$homeBlog);
        saveSetting('home_news_count',  (string)$homeNews);
        saveSetting('news_per_page',    (string)$newsPerPage);
        saveSetting('blog_per_page',    (string)$blogPerPage);
        saveSetting('events_per_page',  (string)$eventsPerPage);
        saveSetting('content_editor',   $contentEditor);
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

        if (empty($errors)) $success = true;
    }
}

adminHeader('Základní nastavení');
?>

<?php if ($success): ?>
  <p class="success" role="status">Nastavení bylo uloženo.</p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <ul class="error" role="alert">
    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset>
    <legend>Úvodní text webu</legend>
    <p>Zobrazí se na úvodní stránce webu nad novinkami a články. Podporuje HTML. Nechte prázdné, pokud úvodní text nechcete.</p>
    <label for="home_intro" class="sr-only">Úvodní text (HTML)</label>
    <textarea id="home_intro" name="home_intro" rows="6"><?= h(getSetting('home_intro', '')) ?></textarea>
  </fieldset>

  <fieldset>
    <legend>Základní informace</legend>
    <label for="site_name">Název webu <span aria-hidden="true">*</span></label>
    <input type="text" id="site_name" name="site_name" required
           value="<?= h(getSetting('site_name')) ?>">

    <label for="site_description">Popis webu</label>
    <input type="text" id="site_description" name="site_description"
           value="<?= h(getSetting('site_description')) ?>">

    <label for="contact_email">E-mail pro kontaktní formulář</label>
    <input type="email" id="contact_email" name="contact_email"
           value="<?= h(getSetting('contact_email')) ?>">
  </fieldset>

  <fieldset>
    <legend>Počty položek na hlavní stránce</legend>
    <label for="home_blog_count">Počet článků blogu na HP</label>
    <input type="number" id="home_blog_count" name="home_blog_count" min="1" max="50"
           value="<?= h(getSetting('home_blog_count', '5')) ?>">

    <label for="home_news_count">Počet novinek na HP</label>
    <input type="number" id="home_news_count" name="home_news_count" min="1" max="50"
           value="<?= h(getSetting('home_news_count', '5')) ?>">

    <label for="news_per_page">Novinek na stránku (stránkovač)</label>
    <input type="number" id="news_per_page" name="news_per_page" min="1" max="100"
           value="<?= h(getSetting('news_per_page', '10')) ?>">

    <label for="blog_per_page">Článků blogu na stránku</label>
    <input type="number" id="blog_per_page" name="blog_per_page" min="1" max="100"
           value="<?= h(getSetting('blog_per_page', '10')) ?>">

    <label for="events_per_page">Akcí na stránku (stránkovač)</label>
    <input type="number" id="events_per_page" name="events_per_page" min="1" max="100"
           value="<?= h(getSetting('events_per_page', '10')) ?>">
  </fieldset>

  <fieldset>
    <legend>Editor obsahu</legend>
    <p style="margin-top:.5rem">
      <label style="font-weight:normal">
        <input type="radio" name="content_editor" value="html"
               <?= getSetting('content_editor', 'html') === 'html' ? 'checked' : '' ?>>
        Čisté HTML (textarea) – vhodné pro pokročilé uživatele
      </label>
    </p>
    <p>
      <label style="font-weight:normal">
        <input type="radio" name="content_editor" value="wysiwyg"
               <?= getSetting('content_editor', 'html') === 'wysiwyg' ? 'checked' : '' ?>>
        WYSIWYG editor (Quill) – vizuální editor bez nutnosti znát HTML
      </label>
    </p>
  </fieldset>

  <fieldset>
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

  <fieldset>
    <legend>Favicon a logo</legend>
    <label for="site_favicon">
      Favicon <small>(ICO, PNG nebo SVG, max. 256 KB)</small>
      <?php $fav = getSetting('site_favicon', ''); ?>
      <?php if ($fav !== ''): ?>
        – aktuální: <img src="<?= BASE_URL ?>/uploads/site/<?= h($fav) ?>"
                         alt="favicon" style="height:20px;vertical-align:middle">
      <?php endif; ?>
    </label>
    <input type="file" id="site_favicon" name="site_favicon" accept=".ico,.png,.svg,image/x-icon,image/png,image/svg+xml">

    <label for="site_logo">
      Logo webu <small>(JPEG, PNG, WebP nebo SVG, max. 2 MB)</small>
      <?php $logo = getSetting('site_logo', ''); ?>
      <?php if ($logo !== ''): ?>
        – aktuální: <img src="<?= BASE_URL ?>/uploads/site/<?= h($logo) ?>"
                         alt="logo" style="max-height:40px;vertical-align:middle">
      <?php endif; ?>
    </label>
    <input type="file" id="site_logo" name="site_logo" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,image/*">

    <label for="og_image_default">Výchozí OG obrázek <small>(relativní cesta v uploads/, např. site/og.jpg)</small></label>
    <input type="text" id="og_image_default" name="og_image_default"
           value="<?= h(getSetting('og_image_default', '')) ?>">
  </fieldset>

  <fieldset>
    <legend>Cookie lišta (GDPR)</legend>
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

  <fieldset>
    <legend>Režim údržby</legend>
    <div>
      <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1"
             <?= getSetting('maintenance_mode', '0') === '1' ? 'checked' : '' ?>>
      <label for="maintenance_mode" style="display:inline;font-weight:normal">
        Zapnout režim údržby <small>(přihlášení admini vidí web normálně)</small>
      </label>
    </div>
    <label for="maintenance_text">Zpráva pro návštěvníky</label>
    <textarea id="maintenance_text" name="maintenance_text"
              rows="2"><?= h(getSetting('maintenance_text',
              'Právě probíhá údržba webu. Brzy budeme zpět, děkujeme za trpělivost.')) ?></textarea>
  </fieldset>

  <p style="margin-top:.5rem">
    <small>Heslo a osobní údaje změníte v <a href="profile.php">Mém profilu</a>.</small>
  </p>

  <button type="submit" style="margin-top:1rem">Uložit nastavení</button>
</form>

<?php adminFooter(); ?>
