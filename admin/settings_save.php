<?php

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/settings_shared.php';

requireCapability('settings_manage', 'Přístup odepřen. Pro správu nastavení webu nemáte potřebné oprávnění.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

verifyCsrf();

$siteProfiles = siteProfileDefinitions();
$formState = settingsDefaultFormState();
$errors = [];
$fieldErrors = [];
$fieldErrorMessages = [];
$successMessage = 'Nastavení bylo uloženo.';

$formState['site_name'] = trim((string)($_POST['site_name'] ?? ''));
$formState['site_description'] = trim((string)($_POST['site_description'] ?? ''));
$formState['contact_email'] = trim((string)($_POST['contact_email'] ?? ''));
$formState['site_profile'] = trim((string)($_POST['site_profile'] ?? $formState['site_profile']));
$formState['board_public_label'] = trim((string)($_POST['board_public_label'] ?? ''));
$formState['public_registration_enabled'] = isset($_POST['public_registration_enabled']) ? '1' : '0';
$formState['github_issues_enabled'] = isset($_POST['github_issues_enabled']) ? '1' : '0';
$formState['github_issues_repository'] = trim((string)($_POST['github_issues_repository'] ?? ''));
$formState['news_per_page'] = (string)max(1, (int)($_POST['news_per_page'] ?? $formState['news_per_page']));
$formState['blog_per_page'] = (string)max(1, (int)($_POST['blog_per_page'] ?? $formState['blog_per_page']));
$formState['events_per_page'] = (string)max(1, (int)($_POST['events_per_page'] ?? $formState['events_per_page']));
$formState['notify_form_submission'] = isset($_POST['notify_form_submission']) ? '1' : '0';
$formState['notify_pending_content'] = isset($_POST['notify_pending_content']) ? '1' : '0';
$formState['notify_chat_message'] = isset($_POST['notify_chat_message']) ? '1' : '0';
$formState['chat_retention_days'] = (string)max(0, min(3650, (int)($_POST['chat_retention_days'] ?? $formState['chat_retention_days'])));
$formState['content_editor'] = in_array((string)($_POST['content_editor'] ?? ''), ['html', 'wysiwyg'], true)
    ? (string)$_POST['content_editor']
    : 'html';
$formState['ga4_measurement_id'] = trim((string)($_POST['ga4_measurement_id'] ?? ''));
$formState['custom_head_code'] = (string)($_POST['custom_head_code'] ?? '');
$formState['custom_footer_code'] = (string)($_POST['custom_footer_code'] ?? '');
$formState['og_image_default'] = trim((string)($_POST['og_image_default'] ?? ''));
$formState['cookie_consent_enabled'] = isset($_POST['cookie_consent_enabled']) ? '1' : '0';
$formState['cookie_consent_text'] = trim((string)($_POST['cookie_consent_text'] ?? ''));
$formState['maintenance_mode'] = isset($_POST['maintenance_mode']) ? '1' : '0';
$formState['maintenance_text'] = trim((string)($_POST['maintenance_text'] ?? ''));
$formState['apply_site_profile'] = isset($_POST['apply_site_profile']) ? '1' : '0';

if (isModuleEnabled('blog')) {
    $formState['blog_authors_index_enabled'] = isset($_POST['blog_authors_index_enabled']) ? '1' : '0';
    $formState['comments_enabled'] = isset($_POST['comments_enabled']) ? '1' : '0';
    $formState['comment_moderation_mode'] = in_array((string)($_POST['comment_moderation_mode'] ?? ''), ['always', 'known', 'none'], true)
        ? (string)$_POST['comment_moderation_mode']
        : 'always';
    $formState['comment_close_days'] = (string)max(0, min(3650, (int)($_POST['comment_close_days'] ?? '0')));
    $formState['comment_notify_admin'] = isset($_POST['comment_notify_admin']) ? '1' : '0';
    $formState['comment_notify_author_approve'] = isset($_POST['comment_notify_author_approve']) ? '1' : '0';
    $formState['comment_notify_email'] = trim((string)($_POST['comment_notify_email'] ?? ''));
    $formState['comment_blocked_emails'] = trim(str_replace("\r", '', (string)($_POST['comment_blocked_emails'] ?? '')));
    $formState['comment_spam_words'] = trim(str_replace("\r", '', (string)($_POST['comment_spam_words'] ?? '')));
}

if (!isset($siteProfiles[$formState['site_profile']])) {
    $errors[] = 'Vybraný profil webu není dostupný. Vyberte některý z nabízených profilů.';
    $formState['site_profile'] = currentSiteProfileKey();
}

$normalizedRepository = normalizeGitHubRepository($formState['github_issues_repository']);
if ($formState['github_issues_repository'] !== '' && $normalizedRepository === '') {
    $errors[] = 'Výchozí repozitář pro GitHub issue bridge není použitelný. U pole Výchozí repozitář je konkrétní nápověda.';
    $fieldErrors[] = 'github_issues_repository';
    $fieldErrorMessages['github_issues_repository'] = 'Zadejte repozitář ve formátu owner/repo, například vlcekapps/Koracms, nebo pole nechte prázdné.';
} else {
    $formState['github_issues_repository'] = $normalizedRepository;
}

if ($formState['board_public_label'] === '') {
    $formState['board_public_label'] = defaultBoardPublicLabelForProfile($formState['site_profile']);
}

if ($formState['site_name'] === '') {
    $errors[] = 'Nastavení webu nejde uložit bez názvu webu. U pole Název webu je konkrétní nápověda.';
    $fieldErrors[] = 'site_name';
    $fieldErrorMessages['site_name'] = 'Doplňte krátký název webu, například název organizace nebo projektu.';
}

if (mb_strlen($formState['board_public_label'], 'UTF-8') > 60) {
    $errors[] = 'Veřejný název sekce vývěsky je příliš dlouhý. U pole Veřejný název sekce vývěsky je konkrétní nápověda.';
    $fieldErrors[] = 'board_public_label';
    $fieldErrorMessages['board_public_label'] = 'Zkraťte veřejný název sekce vývěsky na nejvýše 60 znaků, například Úřední deska.';
}

if ($formState['contact_email'] !== '' && !filter_var($formState['contact_email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Kontaktní e-mail musí být úplná adresa ve tvaru jmeno@example.cz, nebo pole nechte prázdné.';
    $fieldErrors[] = 'contact_email';
    $fieldErrorMessages['contact_email'] = 'Zadejte úplnou kontaktní e-mailovou adresu ve tvaru jmeno@example.cz, nebo pole nechte prázdné.';
}

if (
    isModuleEnabled('blog')
    && $formState['comment_notify_email'] !== ''
    && !filter_var($formState['comment_notify_email'], FILTER_VALIDATE_EMAIL)
) {
    $errors[] = 'E-mail pro upozornění na komentáře musí být úplná adresa ve tvaru jmeno@example.cz, nebo pole nechte prázdné.';
    $fieldErrors[] = 'comment_notify_email';
    $fieldErrorMessages['comment_notify_email'] = 'Zadejte úplnou e-mailovou adresu pro upozornění ve tvaru jmeno@example.cz, nebo pole nechte prázdné.';
}

$faviconUpload = null;
$logoUpload = null;
$siteFaviconMaxBytes = 256 * 1024;
$siteLogoMaxBytes = 2 * 1024 * 1024;
$siteDir = __DIR__ . '/../uploads/site/';

$validateUpload = static function (
    array $fileData,
    array $allowedMimeTypes,
    int $maxBytes,
    string $fieldName,
    string $emptyTempMessage,
    string $sizeMessage,
    string $typeMessage
) use (&$errors, &$fieldErrors, &$fieldErrorMessages): ?array {
    if (!koraUploadHasFile($fileData)) {
        return null;
    }

    $upload = koraInspectUploadedFile($fileData, [
        'upload_error' => $fieldName === 'site_favicon' ? 'Favicon se nepodařilo nahrát.' : 'Logo se nepodařilo nahrát.',
        'invalid_upload_error' => $emptyTempMessage,
        'empty_file_error' => $emptyTempMessage,
        'allowed_mime_map' => $allowedMimeTypes,
        'unsupported_type_error' => $typeMessage,
        'max_bytes' => $maxBytes,
        'too_large_error' => $sizeMessage,
    ]);
    if (empty($upload['ok'])) {
        $message = (string)($upload['error'] ?? $emptyTempMessage);
        $errors[] = $message;
        $fieldErrors[] = $fieldName;
        $fieldErrorMessages[$fieldName] = $message;
        return null;
    }

    return $upload;
};

$faviconUpload = $validateUpload(
    $_FILES['site_favicon'] ?? [],
    [
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'image/png' => 'png',
    ],
    $siteFaviconMaxBytes,
    'site_favicon',
    'Favicon se nepodařilo zpracovat.',
    'Favicon může mít nejvýše 256 KB.',
    'Favicon má nepodporovaný formát. Nahrajte ICO nebo PNG, případně pole nechte prázdné.'
);

$logoUpload = $validateUpload(
    $_FILES['site_logo'] ?? [],
    [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ],
    $siteLogoMaxBytes,
    'site_logo',
    'Logo se nepodařilo zpracovat.',
    'Logo může mít nejvýše 2 MB.',
    'Logo má nepodporovaný formát. Nahrajte JPEG, PNG, GIF nebo WebP, případně pole nechte prázdné.'
);

if (($faviconUpload !== null || $logoUpload !== null) && !is_dir($siteDir) && !@mkdir($siteDir, 0755, true) && !is_dir($siteDir)) {
    $errors[] = 'Adresář pro soubory webu se nepodařilo připravit.';
    if ($faviconUpload !== null) {
        $fieldErrors[] = 'site_favicon';
        $fieldErrorMessages['site_favicon'] = 'Adresář pro soubory webu se nepodařilo připravit.';
    }
    if ($logoUpload !== null) {
        $fieldErrors[] = 'site_logo';
        $fieldErrorMessages['site_logo'] = 'Adresář pro soubory webu se nepodařilo připravit.';
    }
}

if ($errors !== []) {
    settingsFlashSet([
        'errors' => $errors,
        'field_errors' => array_values(array_unique($fieldErrors)),
        'field_error_messages' => $fieldErrorMessages,
        'form' => $formState,
    ]);
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

$settingsToPersist = [
    'site_name',
    'site_description',
    'contact_email',
    'site_profile',
    'board_public_label',
    'public_registration_enabled',
    'github_issues_enabled',
    'github_issues_repository',
    'news_per_page',
    'blog_per_page',
    'events_per_page',
    'blog_authors_index_enabled',
    'comments_enabled',
    'comment_moderation_mode',
    'comment_close_days',
    'comment_notify_admin',
    'comment_notify_author_approve',
    'comment_notify_email',
    'comment_blocked_emails',
    'comment_spam_words',
    'content_editor',
    'notify_form_submission',
    'notify_pending_content',
    'notify_chat_message',
    'chat_retention_days',
    'ga4_measurement_id',
    'custom_head_code',
    'custom_footer_code',
    'maintenance_mode',
    'maintenance_text',
    'cookie_consent_enabled',
    'cookie_consent_text',
    'og_image_default',
];

$pdo = db_connect();
$movedFiles = [];
$generatedWebpFiles = [];

try {
    $pdo->beginTransaction();

    foreach ($settingsToPersist as $settingKey) {
        saveSetting($settingKey, (string)$formState[$settingKey]);
    }

    if ($faviconUpload !== null) {
        $faviconExtension = (string)($faviconUpload['extension'] ?? '');
        $faviconFilename = 'favicon.' . $faviconExtension;
        $storedFavicon = koraStoreInspectedUpload($faviconUpload, $siteDir, $faviconFilename, [
            'mkdir_error' => 'Adresář pro soubory webu se nepodařilo připravit.',
            'move_error' => 'Favicon se nepodařilo uložit.',
        ]);
        if (empty($storedFavicon['ok'])) {
            throw new RuntimeException((string)($storedFavicon['error'] ?? 'Favicon se nepodařilo uložit.'));
        }
        $faviconPath = (string)$storedFavicon['path'];
        $movedFiles[] = $faviconPath;
        saveSetting('site_favicon', $faviconFilename);
        if (($faviconUpload['mime_type'] ?? '') === 'image/png') {
            generateWebp($faviconPath);
            $generatedWebpFiles[] = preg_replace('/\.(png)$/i', '.webp', $faviconPath) ?: '';
        }
    }

    if ($logoUpload !== null) {
        $logoExtension = (string)($logoUpload['extension'] ?? '');
        $logoFilename = 'logo.' . $logoExtension;
        $storedLogo = koraStoreInspectedUpload($logoUpload, $siteDir, $logoFilename, [
            'mkdir_error' => 'Adresář pro soubory webu se nepodařilo připravit.',
            'move_error' => 'Logo se nepodařilo uložit.',
        ]);
        if (empty($storedLogo['ok'])) {
            throw new RuntimeException((string)($storedLogo['error'] ?? 'Logo se nepodařilo uložit.'));
        }
        $logoPath = (string)$storedLogo['path'];
        $movedFiles[] = $logoPath;
        saveSetting('site_logo', $logoFilename);
        generateWebp($logoPath);
        $generatedWebpFiles[] = preg_replace('/\.(jpe?g|png|gif|webp)$/i', '.webp', $logoPath) ?: '';
    }

    if ($formState['apply_site_profile'] === '1') {
        applySiteProfilePreset($formState['site_profile']);
        if (siteProfileSupportsPreset($formState['site_profile'])) {
            $successMessage = 'Nastavení bylo uloženo a doporučené přednastavení profilu bylo použito.';
        } else {
            $successMessage = 'Nastavení bylo uloženo. Vlastní profil zůstal bez zásahu do stávajících modulů a vzhledu.';
        }
    }

    logAction('settings_save');
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    foreach ($generatedWebpFiles as $generatedWebpPath) {
        if ($generatedWebpPath !== '') {
            @unlink($generatedWebpPath);
        }
    }
    foreach ($movedFiles as $movedPath) {
        @unlink($movedPath);
    }

    settingsFlashSet([
        'errors' => ['Nastavení se nepodařilo uložit. Zkuste to prosím znovu.'],
        'field_errors' => array_values(array_unique($fieldErrors)),
        'form' => $formState,
    ]);
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

settingsFlashSet([
    'success' => $successMessage,
]);

header('Location: ' . BASE_URL . '/admin/settings.php');
exit;
