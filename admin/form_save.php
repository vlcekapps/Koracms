<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen.');
verifyCsrf();

function adminAllowedFormFieldTypes(): array
{
    return ['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'number', 'date'];
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
$isActive = isset($_POST['is_active']) ? 1 : 0;

if ($title === '') {
    header('Location: form_form.php?err=required' . ($id ? '&id=' . $id : ''));
    exit;
}

$slug = formSlug($submittedSlug !== '' ? $submittedSlug : $title);
if ($slug === '') {
    header('Location: form_form.php?err=slug' . ($id ? '&id=' . $id : ''));
    exit;
}

$uniqueSlug = uniqueFormSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    header('Location: form_form.php?err=slug' . ($id ? '&id=' . $id : ''));
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
        "UPDATE cms_forms SET title = ?, slug = ?, description = ?, success_message = ?, is_active = ?, updated_at = NOW() WHERE id = ?"
    )->execute([$title, $slug, $description, $successMessage, $isActive, $id]);
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
        $fieldRequired = isset($fieldData['is_required']) ? 1 : 0;
        $fieldSort = max(0, (int)($fieldData['sort_order'] ?? 0));
        $fieldName = trim((string)($existingNames[$fieldId] ?? ''));
        if ($fieldName === '') {
            $fieldName = adminUniqueFormFieldName($fieldLabel, $usedFieldNames, 'field_' . $fieldId);
        }

        $pdo->prepare(
            "UPDATE cms_form_fields SET label = ?, name = ?, field_type = ?, options = ?, placeholder = ?, is_required = ?, sort_order = ? WHERE id = ? AND form_id = ?"
        )->execute([$fieldLabel, $fieldName, $fieldType, $fieldOptions, $fieldPlaceholder, $fieldRequired, $fieldSort, $fieldId, $id]);
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
        $newRequired = isset($_POST['new_field_required']) ? 1 : 0;
        $newSort = max(0, (int)($_POST['new_field_sort'] ?? 0));
        $newName = adminUniqueFormFieldName($newLabel, $usedFieldNames, 'field_' . time());

        $pdo->prepare(
            "INSERT INTO cms_form_fields (form_id, field_type, label, name, options, placeholder, is_required, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$id, $newType, $newLabel, $newName, $newOptions, $newPlaceholder, $newRequired, $newSort]);
    }

    header('Location: ' . BASE_URL . '/admin/form_form.php?id=' . $id);
} else {
    $pdo->prepare(
        "INSERT INTO cms_forms (title, slug, description, success_message, is_active) VALUES (?, ?, ?, ?, ?)"
    )->execute([$title, $slug, $description, $successMessage, $isActive]);
    $newId = (int)$pdo->lastInsertId();
    logAction('form_add', "id={$newId} title={$title}");
    header('Location: ' . BASE_URL . '/admin/form_form.php?id=' . $newId);
}
exit;
