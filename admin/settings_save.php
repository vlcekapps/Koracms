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
    $errors[] = 'Vyberte platný profil webu.';
    $formState['site_profile'] = currentSiteProfileKey();
}

$normalizedRepository = normalizeGitHubRepository($formState['github_issues_repository']);
if ($formState['github_issues_repository'] !== '' && $normalizedRepository === '') {
    $errors[] = 'Výchozí repozitář pro GitHub issue bridge musí být ve formátu owner/repo.';
    $fieldErrors[] = 'github_issues_repository';
} else {
    $formState['github_issues_repository'] = $normalizedRepository;
}

if ($formState['board_public_label'] === '') {
    $formState['board_public_label'] = defaultBoardPublicLabelForProfile($formState['site_profile']);
}

if ($formState['site_name'] === '') {
    $errors[] = 'Název webu je povinný.';
    $fieldErrors[] = 'site_name';
}

if (mb_strlen($formState['board_public_label'], 'UTF-8') > 60) {
    $errors[] = 'Veřejný název sekce vývěsky může mít nejvýše 60 znaků.';
    $fieldErrors[] = 'board_public_label';
}

if ($formState['contact_email'] !== '' && !filter_var($formState['contact_email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Neplatná e-mailová adresa pro kontakt.';
    $fieldErrors[] = 'contact_email';
}

if (
    isModuleEnabled('blog')
    && $formState['comment_notify_email'] !== ''
    && !filter_var($formState['comment_notify_email'], FILTER_VALIDATE_EMAIL)
) {
    $errors[] = 'Neplatná e-mailová adresa pro upozornění na komentáře.';
    $fieldErrors[] = 'comment_notify_email';
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
) use (&$errors, &$fieldErrors): ?array {
    if (empty($fileData['name'])) {
        return null;
    }

    $uploadError = (int)($fileData['error'] ?? UPLOAD_ERR_NO_FILE);
    $tmpPath = (string)($fileData['tmp_name'] ?? '');
    if ($uploadError !== UPLOAD_ERR_OK) {
        $errors[] = $fieldName === 'site_favicon' ? 'Favicon se nepodařilo nahrát.' : 'Logo se nepodařilo nahrát.';
        $fieldErrors[] = $fieldName;
        return null;
    }

    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $errors[] = $emptyTempMessage;
        $fieldErrors[] = $fieldName;
        return null;
    }

    if ((int)($fileData['size'] ?? 0) > $maxBytes) {
        $errors[] = $sizeMessage;
        $fieldErrors[] = $fieldName;
        return null;
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string)$fileInfo->file($tmpPath);
    if (!isset($allowedMimeTypes[$mimeType])) {
        $errors[] = $typeMessage;
        $fieldErrors[] = $fieldName;
        return null;
    }

    return [
        'tmp_path' => $tmpPath,
        'mime' => $mimeType,
        'extension' => $allowedMimeTypes[$mimeType],
    ];
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
    'Favicon: nepodporovaný formát (povoleno: ICO, PNG).'
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
    'Logo: nepodporovaný formát (povoleno: JPEG, PNG, GIF, WebP).'
);

if (($faviconUpload !== null || $logoUpload !== null) && !is_dir($siteDir) && !@mkdir($siteDir, 0755, true) && !is_dir($siteDir)) {
    $errors[] = 'Adresář pro soubory webu se nepodařilo připravit.';
    if ($faviconUpload !== null) {
        $fieldErrors[] = 'site_favicon';
    }
    if ($logoUpload !== null) {
        $fieldErrors[] = 'site_logo';
    }
}

if ($errors !== []) {
    settingsFlashSet([
        'errors' => $errors,
        'field_errors' => array_values(array_unique($fieldErrors)),
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
        $faviconFilename = 'favicon.' . $faviconUpload['extension'];
        $faviconPath = $siteDir . $faviconFilename;
        if (!move_uploaded_file($faviconUpload['tmp_path'], $faviconPath)) {
            throw new RuntimeException('Favicon se nepodařilo uložit.');
        }
        $movedFiles[] = $faviconPath;
        saveSetting('site_favicon', $faviconFilename);
        if ($faviconUpload['mime'] === 'image/png') {
            generateWebp($faviconPath);
            $generatedWebpFiles[] = preg_replace('/\.(png)$/i', '.webp', $faviconPath) ?: '';
        }
    }

    if ($logoUpload !== null) {
        $logoFilename = 'logo.' . $logoUpload['extension'];
        $logoPath = $siteDir . $logoFilename;
        if (!move_uploaded_file($logoUpload['tmp_path'], $logoPath)) {
            throw new RuntimeException('Logo se nepodařilo uložit.');
        }
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
        if (is_string($generatedWebpPath) && $generatedWebpPath !== '') {
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
