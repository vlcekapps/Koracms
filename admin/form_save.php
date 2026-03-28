<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');
verifyCsrf();

function adminAllowedFormFieldTypes(): array
{
    return array_keys(formFieldTypeDefinitions());
}

function adminUniqueFormFieldName(string $baseName, array &$usedNames, string $fallbackPrefix = 'field'): string
{
    $baseName = slugify($baseName);
    if ($baseName === '') {
        $baseName = $fallbackPrefix;
    }

    $candidate = $baseName;
    $suffix = 2;
    while (in_array($candidate, $usedNames, true)) {
        $candidate = $baseName . '_' . $suffix;
        $suffix++;
    }

    $usedNames[] = $candidate;
    return $candidate;
}

$pdo = db_connect();
$id = inputInt('post', 'id');
$title = trim($_POST['title'] ?? '');
$submittedSlug = trim($_POST['slug'] ?? '');
$description = trim($_POST['description'] ?? '');
$successMessage = trim($_POST['success_message'] ?? '');
$submitLabel = trim($_POST['submit_label'] ?? '');
$notificationEmail = trim($_POST['notification_email'] ?? '');
$notificationSubject = trim($_POST['notification_subject'] ?? '');
$redirectUrl = trim($_POST['redirect_url'] ?? '');
$useHoneypot = isset($_POST['use_honeypot']) ? 1 : 0;
$submitterConfirmationEnabled = isset($_POST['submitter_confirmation_enabled']) ? 1 : 0;
$submitterEmailField = trim($_POST['submitter_email_field'] ?? '');
$submitterConfirmationSubject = trim($_POST['submitter_confirmation_subject'] ?? '');
$submitterConfirmationMessage = trim($_POST['submitter_confirmation_message'] ?? '');
$isActive = isset($_POST['is_active']) ? 1 : 0;
$presetKey = trim((string)($_POST['preset'] ?? ''));
$presetDefinition = $id === null ? formPresetDefinition($presetKey) : null;
$errorSuffix = $id !== null ? '&id=' . $id : ($presetKey !== '' ? '&preset=' . urlencode($presetKey) : '');

if ($submitLabel === '') {
    $submitLabel = 'Odeslat formulář';
}
if ($notificationEmail !== '' && !filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
    header('Location: form_form.php?err=notification_email' . $errorSuffix);
    exit;
}
if ($submitterConfirmationEnabled === 1 && $submitterEmailField === '') {
    header('Location: form_form.php?err=submitter_email_field' . $errorSuffix);
    exit;
}
$redirectUrl = $redirectUrl !== '' ? internalRedirectTarget($redirectUrl, '') : '';

if ($title === '') {
    header('Location: form_form.php?err=required' . $errorSuffix);
    exit;
}

$slug = formSlug($submittedSlug !== '' ? $submittedSlug : $title);
if ($slug === '') {
    header('Location: form_form.php?err=slug' . $errorSuffix);
    exit;
}

$uniqueSlug = uniqueFormSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    header('Location: form_form.php?err=slug' . $errorSuffix);
    exit;
}
$slug = $uniqueSlug;

if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT id FROM cms_forms WHERE id = ?");
    $existingStmt->execute([$id]);
    if (!$existingStmt->fetch()) {
        header('Location: ' . BASE_URL . '/admin/forms.php');
        exit;
    }

    $pdo->prepare(
        "UPDATE cms_forms
         SET title = ?, slug = ?, description = ?, success_message = ?, submit_label = ?, notification_email = ?, notification_subject = ?, redirect_url = ?, use_honeypot = ?, submitter_confirmation_enabled = ?, submitter_email_field = ?, submitter_confirmation_subject = ?, submitter_confirmation_message = ?, is_active = ?, updated_at = NOW()
         WHERE id = ?"
    )->execute([$title, $slug, $description, $successMessage, $submitLabel, $notificationEmail, $notificationSubject, $redirectUrl, $useHoneypot, $submitterConfirmationEnabled, $submitterEmailField, $submitterConfirmationSubject, $submitterConfirmationMessage, $isActive, $id]);
    logAction('form_edit', "id={$id} title={$title}");

    // Zpracování existujících polí
    $existingNames = $pdo->prepare("SELECT id, name FROM cms_form_fields WHERE form_id = ?");
    $existingNames->execute([$id]);
    $existingNames = $existingNames->fetchAll(PDO::FETCH_KEY_PAIR);
    $usedFieldNames = array_values(array_filter(array_map(static fn($value): string => trim((string)$value), $existingNames)));

    $submittedFields = (array)($_POST['fields'] ?? []);
    foreach ($submittedFields as $fieldData) {
        $fieldId = (int)($fieldData['id'] ?? 0);
        if ($fieldId <= 0) {
            continue;
        }

        if (!empty($fieldData['delete'])) {
            $pdo->prepare("DELETE FROM cms_form_fields WHERE id = ? AND form_id = ?")->execute([$fieldId, $id]);
            continue;
        }

        $fieldLabel = trim((string)($fieldData['label'] ?? ''));
        if ($fieldLabel === '') {
            continue;
        }

        $fieldType = trim((string)($fieldData['field_type'] ?? 'text'));
        if (!in_array($fieldType, adminAllowedFormFieldTypes(), true)) {
            $fieldType = 'text';
        }
        $fieldOptions = trim((string)($fieldData['options'] ?? ''));
        $fieldPlaceholder = trim((string)($fieldData['placeholder'] ?? ''));
        $fieldDefaultValue = trim((string)($fieldData['default_value'] ?? ''));
        $fieldHelpText = trim((string)($fieldData['help_text'] ?? ''));
        $fieldAcceptTypes = trim((string)($fieldData['accept_types'] ?? ''));
        $fieldMaxFileSize = max(1, min(100, (int)($fieldData['max_file_size_mb'] ?? 10)));
        $fieldAllowMultiple = isset($fieldData['allow_multiple']) && $fieldType === 'file' ? 1 : 0;
        $fieldShowIfField = trim((string)($fieldData['show_if_field'] ?? ''));
        $fieldShowIfValue = trim((string)($fieldData['show_if_value'] ?? ''));
        if ($fieldShowIfField === '') {
            $fieldShowIfValue = '';
        }
        $fieldRequired = isset($fieldData['is_required']) ? 1 : 0;
        $fieldSort = max(0, (int)($fieldData['sort_order'] ?? 0));
        $fieldName = trim((string)($existingNames[$fieldId] ?? ''));
        if ($fieldName === '') {
            $fieldName = adminUniqueFormFieldName($fieldLabel, $usedFieldNames, 'field_' . $fieldId);
        }

        $pdo->prepare(
            "UPDATE cms_form_fields
             SET label = ?, name = ?, field_type = ?, options = ?, placeholder = ?, default_value = ?, help_text = ?, accept_types = ?, max_file_size_mb = ?, allow_multiple = ?, show_if_field = ?, show_if_value = ?, is_required = ?, sort_order = ?
             WHERE id = ? AND form_id = ?"
        )->execute([$fieldLabel, $fieldName, $fieldType, $fieldOptions, $fieldPlaceholder, $fieldDefaultValue, $fieldHelpText, $fieldAcceptTypes, $fieldMaxFileSize, $fieldAllowMultiple, $fieldShowIfField, $fieldShowIfValue, $fieldRequired, $fieldSort, $fieldId, $id]);
    }

    // Nové pole
    $newLabel = trim($_POST['new_field_label'] ?? '');
    if ($newLabel !== '') {
        $newType = trim($_POST['new_field_type'] ?? 'text');
        if (!in_array($newType, adminAllowedFormFieldTypes(), true)) {
            $newType = 'text';
        }
        $newOptions = trim($_POST['new_field_options'] ?? '');
        $newPlaceholder = trim($_POST['new_field_placeholder'] ?? '');
        $newDefaultValue = trim($_POST['new_field_default_value'] ?? '');
        $newHelpText = trim($_POST['new_field_help_text'] ?? '');
        $newAcceptTypes = trim($_POST['new_field_accept_types'] ?? '');
        $newMaxFileSize = max(1, min(100, (int)($_POST['new_field_max_file_size_mb'] ?? 10)));
        $newAllowMultiple = isset($_POST['new_field_allow_multiple']) && $newType === 'file' ? 1 : 0;
        $newShowIfField = trim($_POST['new_field_show_if_field'] ?? '');
        $newShowIfValue = trim($_POST['new_field_show_if_value'] ?? '');
        if ($newShowIfField === '') {
            $newShowIfValue = '';
        }
        $newRequired = isset($_POST['new_field_required']) ? 1 : 0;
        $newSort = max(0, (int)($_POST['new_field_sort'] ?? 0));
        $newName = adminUniqueFormFieldName($newLabel, $usedFieldNames, 'field_' . time());

        $pdo->prepare(
            "INSERT INTO cms_form_fields (form_id, field_type, label, name, options, placeholder, default_value, help_text, accept_types, max_file_size_mb, allow_multiple, show_if_field, show_if_value, is_required, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$id, $newType, $newLabel, $newName, $newOptions, $newPlaceholder, $newDefaultValue, $newHelpText, $newAcceptTypes, $newMaxFileSize, $newAllowMultiple, $newShowIfField, $newShowIfValue, $newRequired, $newSort]);
    }

    header('Location: ' . BASE_URL . '/admin/form_form.php?id=' . $id);
} else {
    $pdo->prepare(
        "INSERT INTO cms_forms (title, slug, description, success_message, submit_label, notification_email, notification_subject, redirect_url, use_honeypot, submitter_confirmation_enabled, submitter_email_field, submitter_confirmation_subject, submitter_confirmation_message, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([$title, $slug, $description, $successMessage, $submitLabel, $notificationEmail, $notificationSubject, $redirectUrl, $useHoneypot, $submitterConfirmationEnabled, $submitterEmailField, $submitterConfirmationSubject, $submitterConfirmationMessage, $isActive]);
    $newId = (int)$pdo->lastInsertId();

    if ($presetDefinition !== null && !empty($presetDefinition['fields'])) {
        $usedFieldNames = [];
        $insertFieldStmt = $pdo->prepare(
            "INSERT INTO cms_form_fields (form_id, field_type, label, name, options, placeholder, default_value, help_text, accept_types, max_file_size_mb, allow_multiple, show_if_field, show_if_value, is_required, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ((array)$presetDefinition['fields'] as $presetField) {
            $presetFieldType = normalizeFormFieldType((string)($presetField['field_type'] ?? 'text'));
            $presetFieldLabel = trim((string)($presetField['label'] ?? ''));
            if ($presetFieldLabel === '') {
                continue;
            }

            $presetFieldName = adminUniqueFormFieldName(
                trim((string)($presetField['name'] ?? '')) !== '' ? (string)$presetField['name'] : $presetFieldLabel,
                $usedFieldNames,
                'field'
            );

            $insertFieldStmt->execute([
                $newId,
                $presetFieldType,
                $presetFieldLabel,
                $presetFieldName,
                trim((string)($presetField['options'] ?? '')),
                trim((string)($presetField['placeholder'] ?? '')),
                trim((string)($presetField['default_value'] ?? '')),
                trim((string)($presetField['help_text'] ?? '')),
                trim((string)($presetField['accept_types'] ?? '')),
                max(1, min(100, (int)($presetField['max_file_size_mb'] ?? 10))),
                !empty($presetField['allow_multiple']) ? 1 : 0,
                trim((string)($presetField['show_if_field'] ?? '')),
                trim((string)($presetField['show_if_value'] ?? '')),
                !empty($presetField['is_required']) ? 1 : 0,
                max(0, (int)($presetField['sort_order'] ?? 0)),
            ]);
        }
    }

    logAction('form_add', "id={$newId} title={$title}");
    header('Location: ' . BASE_URL . '/admin/form_form.php?id=' . $newId);
}
exit;
