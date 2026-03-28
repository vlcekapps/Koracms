<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

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

$errors = [];
$success = false;
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('form_submit_' . (int)$form['id'], 5, 300);

    if (honeypotTriggered()) {
        $success = true;
    } else {
        verifyCsrf();

        if (!captchaVerify($_POST['captcha'] ?? '')) {
            $errors[] = 'Chybná odpověď na ověřovací otázku.';
        }

        $submissionData = [];
        foreach ($fields as $field) {
            $name = (string)$field['name'];
            $value = trim((string)($_POST[$name] ?? ''));
            $formData[$name] = $value;

            if ((int)$field['is_required'] && $value === '') {
                $errors[] = 'Pole „' . (string)$field['label'] . '" je povinné.';
            }

            if ($field['field_type'] === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Pole „' . (string)$field['label'] . '" musí být platná e-mailová adresa.';
            }

            $submissionData[$name] = $value;
        }

        if (empty($errors)) {
            $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . '|form_' . (int)$form['id']);
            try {
                $pdo->prepare(
                    "INSERT INTO cms_form_submissions (form_id, data, ip_hash) VALUES (?, ?, ?)"
                )->execute([
                    (int)$form['id'],
                    json_encode($submissionData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $ipHash,
                ]);
            } catch (\PDOException $e) {
                error_log('form submit: ' . $e->getMessage());
                $errors[] = 'Odeslání formuláře se nezdařilo. Zkuste to prosím později.';
            }

            if (empty($errors)) {
                $success = true;
            }
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
        'formData' => $formData,
        'captchaExpr' => $captchaExpr,
    ],
    'body_class' => 'page-form',
    'page_kind' => 'detail',
]);
