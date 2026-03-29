<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

function publicFormFieldAcceptsUpload(array $field, string $originalName, string $mimeType): bool
{
    $acceptTypes = trim((string)($field['accept_types'] ?? ''));
    if ($acceptTypes === '') {
        return true;
    }

    $extension = '.' . strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    foreach (array_map('trim', explode(',', $acceptTypes)) as $allowed) {
        if ($allowed === '') {
            continue;
        }
        if (str_ends_with($allowed, '/*')) {
            $prefix = strtolower(substr($allowed, 0, -1));
            if (str_starts_with(strtolower($mimeType), $prefix)) {
                return true;
            }
            continue;
        }
        if (str_starts_with($allowed, '.')) {
            if ($extension === strtolower($allowed)) {
                return true;
            }
            continue;
        }
        if (strcasecmp($allowed, $mimeType) === 0) {
            return true;
        }
    }

    return false;
}

function storePublicFormUpload(array $field, array $file): array
{
    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        return ['error' => 'Soubor se nepodařilo nahrát. Zkuste to prosím znovu.'];
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['error' => 'Nahraný soubor se nepodařilo ověřit.'];
    }

    $originalName = trim((string)($file['name'] ?? 'soubor'));
    $fileSize = (int)($file['size'] ?? 0);
    $maxFileSizeBytes = max(1, (int)($field['max_file_size_mb'] ?? 10)) * 1024 * 1024;
    if ($fileSize < 1) {
        return ['error' => 'Vybraný soubor je prázdný.'];
    }
    if ($fileSize > $maxFileSizeBytes) {
        return ['error' => 'Vybraný soubor je větší, než tento formulář dovoluje.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string)$finfo->file($tmpName);
    if (!publicFormFieldAcceptsUpload($field, $originalName, $mimeType)) {
        return ['error' => 'Vybraný typ souboru není v tomto poli povolený.'];
    }

    $uploadDir = formUploadDirectory();
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $storedName = uniqid('form_', true) . ($extension !== '' ? '.' . $extension : '');
    $destination = $uploadDir . $storedName;
    if (!move_uploaded_file($tmpName, $destination)) {
        return ['error' => 'Soubor se nepodařilo uložit na server.'];
    }

    return [
        'value' => [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'url' => formUploadPublicPath($storedName),
        ],
    ];
}

function publicFormUploadEntries(mixed $fileInput): array
{
    if (!is_array($fileInput) || !isset($fileInput['error'])) {
        return [];
    }

    if (!is_array($fileInput['error'])) {
        return [$fileInput];
    }

    $entries = [];
    $names = (array)($fileInput['name'] ?? []);
    $types = (array)($fileInput['type'] ?? []);
    $tmpNames = (array)($fileInput['tmp_name'] ?? []);
    $errors = (array)($fileInput['error'] ?? []);
    $sizes = (array)($fileInput['size'] ?? []);

    foreach ($errors as $index => $errorCode) {
        $entries[] = [
            'name' => $names[$index] ?? '',
            'type' => $types[$index] ?? '',
            'tmp_name' => $tmpNames[$index] ?? '',
            'error' => $errorCode,
            'size' => $sizes[$index] ?? 0,
        ];
    }

    return $entries;
}

function publicFormUploadInputHasFile(mixed $fileInput): bool
{
    foreach (publicFormUploadEntries($fileInput) as $entry) {
        if ((int)($entry['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return true;
        }
    }

    return false;
}

function storePublicFormUploads(array $field, mixed $fileInput): array
{
    $uploadedFiles = [];
    foreach (publicFormUploadEntries($fileInput) as $entry) {
        if ((int)($entry['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $uploadResult = storePublicFormUpload($field, $entry);
        if (isset($uploadResult['error'])) {
            foreach ($uploadedFiles as $uploadedFile) {
                foreach (formCollectUploadedFilesFromSubmissionData($uploadedFile) as $storedName) {
                    formDeleteUploadedFile($storedName);
                }
            }
            return $uploadResult;
        }

        $uploadedFiles[] = $uploadResult['value'] ?? '';
    }

    if (formFieldAllowsMultipleFiles($field)) {
        return ['value' => $uploadedFiles];
    }

    return ['value' => $uploadedFiles[0] ?? ''];
}

if (!isModuleEnabled('forms')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');

$slug = formSlug(trim($_GET['slug'] ?? ''));
$id = inputInt('get', 'id');

if ($slug === '' && $id === null) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($slug !== '') {
    $stmt = $pdo->prepare("SELECT * FROM cms_forms WHERE slug = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM cms_forms WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$id]);
}

$form = $stmt->fetch() ?: null;
if (!$form) {
    http_response_code(404);
    renderPublicPage([
        'title' => 'Formulář nenalezen – ' . $siteName,
        'meta' => ['title' => 'Formulář nenalezen – ' . $siteName],
        'view' => 'not-found',
        'body_class' => 'page-form-not-found',
    ]);
    exit;
}

// Přesměrování na slug URL
if ($slug === '' && !empty($form['slug'])) {
    header('Location: ' . formPublicPath($form));
    exit;
}

$fields = $pdo->prepare("SELECT * FROM cms_form_fields WHERE form_id = ? ORDER BY sort_order, id");
$fields->execute([(int)$form['id']]);
$fields = $fields->fetchAll();
$fieldsByName = [];
foreach ($fields as $fieldRow) {
    $fieldName = trim((string)($fieldRow['name'] ?? ''));
    if ($fieldName !== '') {
        $fieldsByName[$fieldName] = $fieldRow;
    }
}

$errors = [];
$success = false;
$formData = [];
$storedUploads = [];
$successActions = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('form_submit_' . (int)$form['id'], 5, 300);

    if ((int)($form['use_honeypot'] ?? 1) === 1 && honeypotTriggered()) {
        $success = true;
    } else {
        verifyCsrf();

        if (!captchaVerify($_POST['captcha'] ?? '')) {
            $errors[] = 'Chybná odpověď na ověřovací otázku.';
        }

        $previewData = [];
        foreach ($fields as $field) {
            $name = (string)$field['name'];
            $fieldType = normalizeFormFieldType((string)($field['field_type'] ?? 'text'));
            $defaultValue = trim((string)($field['default_value'] ?? ''));

            if (!formFieldStoresSubmissionValue($field)) {
                continue;
            }

            if ($fieldType === 'hidden') {
                $previewData[$name] = $defaultValue;
                continue;
            }

            if ($fieldType === 'checkbox_group') {
                $rawValues = $_POST[$name] ?? [];
                $previewData[$name] = is_array($rawValues)
                    ? array_values(array_filter(array_map(static fn($item): string => trim((string)$item), $rawValues), static fn(string $item): bool => $item !== ''))
                    : [];
                continue;
            }

            if (in_array($fieldType, ['checkbox', 'consent'], true)) {
                $previewData[$name] = isset($_POST[$name]) ? '1' : '';
                continue;
            }

            if ($fieldType === 'file') {
                $previewData[$name] = publicFormUploadInputHasFile($_FILES[$name] ?? null) ? '__uploaded__' : '';
                continue;
            }

            $rawValue = $_POST[$name] ?? '';
            $previewData[$name] = is_array($rawValue) ? '' : trim((string)$rawValue);
        }

        $submissionData = [];
        $notificationData = [];
        foreach ($fields as $field) {
            $name = (string)$field['name'];
            $label = (string)$field['label'];
            $fieldType = normalizeFormFieldType((string)($field['field_type'] ?? 'text'));
            $required = (int)($field['is_required'] ?? 0) === 1;
            $defaultValue = trim((string)($field['default_value'] ?? ''));
            $value = '';

            if (!formFieldStoresSubmissionValue($field)) {
                continue;
            }

            if (!formFieldConditionMatches($field, $previewData)) {
                $submissionData[$name] = $fieldType === 'checkbox_group' ? [] : '';
                $notificationData[$label] = '';
                continue;
            }

            if ($fieldType === 'hidden') {
                $submissionData[$name] = $defaultValue;
                $notificationData[$label] = formSubmissionDisplayValueForField($field, $defaultValue);
                continue;
            }

            if ($fieldType === 'checkbox_group') {
                $rawValues = $_POST[$name] ?? [];
                $value = is_array($rawValues)
                    ? array_values(array_filter(array_map(static fn($item): string => trim((string)$item), $rawValues), static fn(string $item): bool => $item !== ''))
                    : [];
                $formData[$name] = $value;

                if ($required && $value === []) {
                    $errors[] = 'Pole „' . $label . '“ je povinné.';
                }

                $allowedOptions = formFieldOptionsList((string)($field['options'] ?? ''));
                foreach ($value as $selectedOption) {
                    if (!in_array($selectedOption, $allowedOptions, true)) {
                        $errors[] = 'Pole „' . $label . '“ obsahuje nepovolenou hodnotu.';
                        break;
                    }
                }

                $submissionData[$name] = $value;
                $notificationData[$label] = formSubmissionDisplayValueForField($field, $value);
                continue;
            }

            if (in_array($fieldType, ['checkbox', 'consent'], true)) {
                $value = isset($_POST[$name]) ? '1' : '';
                $formData[$name] = $value;
                if ($required && $value !== '1') {
                    $errors[] = 'Pole „' . $label . '“ je povinné.';
                }
                $submissionData[$name] = $value;
                $notificationData[$label] = formSubmissionDisplayValueForField($field, $value);
                continue;
            } elseif ($fieldType === 'file') {
                $hasUpload = publicFormUploadInputHasFile($_FILES[$name] ?? null);
                if (!$hasUpload) {
                    if ($required) {
                        $errors[] = 'Pole „' . $label . '“ je povinné.';
                    }
                    $emptyFileValue = formFieldAllowsMultipleFiles($field) ? [] : '';
                    $submissionData[$name] = $emptyFileValue;
                    $notificationData[$label] = '';
                    continue;
                }

                $uploadResult = storePublicFormUploads($field, $_FILES[$name] ?? null);
                if (isset($uploadResult['error'])) {
                    $errors[] = 'Pole „' . $label . '“: ' . $uploadResult['error'];
                    $submissionData[$name] = formFieldAllowsMultipleFiles($field) ? [] : '';
                    $notificationData[$label] = '';
                    continue;
                }

                $storedValue = $uploadResult['value'] ?? '';
                $submissionData[$name] = $storedValue;
                $notificationData[$label] = formSubmissionDisplayValueForField($field, $storedValue);
                foreach (formCollectUploadedFilesFromSubmissionData($storedValue) as $storedName) {
                    $storedUploads[] = $storedName;
                }
                continue;
            } else {
                $rawValue = $_POST[$name] ?? '';
                $value = is_array($rawValue) ? '' : trim((string)$rawValue);
                $formData[$name] = $value;
            }

            if ($required && $value === '') {
                $errors[] = 'Pole „' . $label . '“ je povinné.';
            }

            if ($fieldType === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Pole „' . $label . '“ musí být platná e-mailová adresa.';
            }

            if ($fieldType === 'url' && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                $errors[] = 'Pole „' . $label . '“ musí být platná webová adresa.';
            }

            if (in_array($fieldType, ['select', 'radio'], true) && $value !== '') {
                $allowedOptions = formFieldOptionsList((string)($field['options'] ?? ''));
                if (!in_array($value, $allowedOptions, true)) {
                    $errors[] = 'Pole „' . $label . '“ obsahuje nepovolenou hodnotu.';
                }
            }

            $submissionData[$name] = $value;
            $notificationData[$label] = formSubmissionDisplayValueForField($field, $value);
        }

        if (empty($errors)) {
            $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . '|form_' . (int)$form['id']);
            $submissionId = 0;
            $submissionReference = '';
            $savedSubmission = null;
            $submissionPriority = formSubmissionInferPriority($fieldsByName, $submissionData);
            try {
                $pdo->prepare(
                    "INSERT INTO cms_form_submissions (form_id, data, ip_hash, priority, labels) VALUES (?, ?, ?, ?, '')"
                )->execute([
                    (int)$form['id'],
                    json_encode($submissionData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $ipHash,
                    $submissionPriority,
                ]);
                $submissionId = (int)$pdo->lastInsertId();
                $submissionReference = formSubmissionBuildReference($form, $submissionId);
                $pdo->prepare(
                    "UPDATE cms_form_submissions
                     SET reference_code = ?
                     WHERE id = ?"
                )->execute([
                    $submissionReference,
                    $submissionId,
                ]);
                $savedSubmissionStmt = $pdo->prepare("SELECT * FROM cms_form_submissions WHERE id = ?");
                $savedSubmissionStmt->execute([$submissionId]);
                $savedSubmission = $savedSubmissionStmt->fetch() ?: null;
                formSubmissionHistoryCreate(
                    $pdo,
                    $submissionId,
                    null,
                    'created',
                    'Odpověď byla přijata přes veřejný formulář.'
                );
            } catch (\PDOException $e) {
                error_log('form submit: ' . $e->getMessage());
                $errors[] = 'Odeslání formuláře se nezdařilo. Zkuste to prosím později.';
            }

            if (empty($errors)) {
                $detailUrl = siteUrl('/admin/form_submission.php?id=' . $submissionId . '&form_id=' . (int)$form['id']);
                notifyFormSubmission(
                    (string)$form['title'],
                    $notificationData,
                    trim((string)($form['notification_email'] ?? '')),
                    trim((string)($form['notification_subject'] ?? '')),
                    $submissionReference,
                    $detailUrl
                );
                sendFormSubmitterConfirmation($form, $fieldsByName, $submissionData, [
                    '{{submission_reference}}' => $submissionReference,
                ]);
                if (is_array($savedSubmission)) {
                    dispatchFormWebhook(
                        $form,
                        'submission_created',
                        $savedSubmission,
                        $fieldsByName,
                        $submissionData,
                        [
                            'source' => 'public_form',
                            'detail_url' => $detailUrl,
                            'notification_email' => trim((string)($form['notification_email'] ?? '')),
                        ]
                    );
                }

                $effectiveSuccessBehavior = normalizeFormSuccessBehavior(
                    (string)($form['success_behavior'] ?? ''),
                    (string)($form['redirect_url'] ?? '')
                );
                $redirectUrl = internalRedirectTarget((string)($form['redirect_url'] ?? ''), '');
                if ($effectiveSuccessBehavior === 'redirect' && $redirectUrl !== '') {
                    header('Location: ' . $redirectUrl);
                    exit;
                }

                $success = true;
                $successActions = formResolveSuccessActions($form);
            }
        }
    }

    if (!empty($errors) && $storedUploads !== []) {
        foreach ($storedUploads as $storedName) {
            formDeleteUploadedFile($storedName);
        }
    }
}

$captchaExpr = captchaGenerate();

renderPublicPage([
    'title' => (string)$form['title'] . ' – ' . $siteName,
    'meta' => [
        'title' => (string)$form['title'] . ' – ' . $siteName,
        'url' => formPublicUrl($form),
    ],
    'view' => 'modules/forms-show',
    'view_data' => [
        'form' => $form,
        'fields' => $fields,
        'errors' => $errors,
        'success' => $success,
        'successActions' => $successActions,
        'formData' => $formData,
        'captchaExpr' => $captchaExpr,
    ],
    'body_class' => 'page-form',
    'page_kind' => 'detail',
]);
