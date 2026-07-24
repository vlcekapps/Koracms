<?php

require_once __DIR__ . '/layout.php';
requireCapability('appmarket_manage', 'Přístup odepřen. Pro správu Appmarketu nemáte potřebné oprávnění.');
requireModuleEnabled('appmarket');
requireHttpMethods(['POST']);
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$existing = $id !== null ? appmarketFindApp($pdo, $id) : null;
if ($id !== null && $existing === null) {
    http_response_code(404);
    exit('Aplikace nebyla nalezena.');
}

$form = [
    'name' => mb_substr(trim((string)($_POST['name'] ?? '')), 0, 255),
    'slug' => appmarketAppSlug((string)($_POST['slug'] ?? '')),
    'package_id' => appmarketNormalizePackageId((string)($_POST['package_id'] ?? '')),
    'short_description' => mb_substr(trim((string)($_POST['short_description'] ?? '')), 0, 500),
    'description' => (string)($_POST['description'] ?? ''),
    'icon_media_id' => inputInt('post', 'icon_media_id'),
    'website_url' => appmarketNormalizeHttpUrl((string)($_POST['website_url'] ?? '')),
    'support_url' => appmarketNormalizeHttpUrl((string)($_POST['support_url'] ?? '')),
    'privacy_url' => appmarketNormalizeHttpUrl((string)($_POST['privacy_url'] ?? '')),
    'license_label' => mb_substr(trim((string)($_POST['license_label'] ?? '')), 0, 100),
    'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
    'sort_order' => max(0, min(100000, (int)($_POST['sort_order'] ?? 0))),
    'status' => $existing !== null ? (string)$existing['status'] : 'draft',
];
if ($form['slug'] === '' && $form['name'] !== '') {
    $form['slug'] = appmarketAppSlug($form['name']);
}

$errors = [];
$addError = static function (string $field, string $message) use (&$errors): void {
    $errors[] = ['field' => $field, 'message' => $message];
};
if ($form['name'] === '') {
    $addError('name', 'Doplňte název aplikace.');
}
if ($form['slug'] === '') {
    $addError('slug', 'Doplňte použitelný slug z malých písmen, číslic a pomlček.');
}
if ($form['package_id'] === '') {
    $addError('package_id', 'Zadejte platné Android applicationId, například cz.example.aplikace.');
}
if ($form['short_description'] === '') {
    $addError('short_description', 'Doplňte krátký popis aplikace.');
}
foreach (['website_url', 'support_url', 'privacy_url'] as $urlField) {
    $submitted = trim((string)($_POST[$urlField] ?? ''));
    if ($submitted !== '' && $form[$urlField] === '') {
        $addError($urlField, 'Použijte úplnou adresu začínající http:// nebo https://.');
    }
}

$slugStmt = $pdo->prepare(
    'SELECT id FROM cms_appmarket_apps WHERE slug = ?' . ($id !== null ? ' AND id <> ?' : '') . ' LIMIT 1'
);
$slugParams = [$form['slug']];
if ($id !== null) {
    $slugParams[] = $id;
}
$slugStmt->execute($slugParams);
if ($form['slug'] !== '' && $slugStmt->fetch()) {
    $addError('slug', 'Tento slug už používá jiná aplikace.');
}

$packageStmt = $pdo->prepare(
    'SELECT id FROM cms_appmarket_apps WHERE package_id = ?' . ($id !== null ? ' AND id <> ?' : '') . ' LIMIT 1'
);
$packageParams = [$form['package_id']];
if ($id !== null) {
    $packageParams[] = $id;
}
$packageStmt->execute($packageParams);
if ($form['package_id'] !== '' && $packageStmt->fetch()) {
    $addError('package_id', 'Toto applicationId už používá jiná aplikace.');
}
if ($existing !== null
    && $form['package_id'] !== (string)$existing['package_id']
) {
    $releaseCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_appmarket_releases WHERE app_id = ?');
    $releaseCountStmt->execute([(int)$existing['id']]);
    if ((int)$releaseCountStmt->fetchColumn() > 0) {
        $addError(
            'package_id',
            'ApplicationId nelze po nahrání prvního vydání změnit. Založte samostatnou aplikaci.'
        );
    }
}

if ($form['icon_media_id'] !== null) {
    $mediaStmt = $pdo->prepare(
        "SELECT id FROM cms_media WHERE id = ? AND visibility = 'public' AND mime_type LIKE 'image/%' LIMIT 1"
    );
    $mediaStmt->execute([$form['icon_media_id']]);
    if (!$mediaStmt->fetch()) {
        $form['icon_media_id'] = null;
        $addError('icon_media_id', 'Vybraná ikona není veřejný obrázek z knihovny médií.');
    }
}

$screenshotIds = [];
foreach (is_array($_POST['screenshot_ids'] ?? null) ? $_POST['screenshot_ids'] : [] as $rawScreenshotId) {
    $screenshotId = filter_var($rawScreenshotId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($screenshotId !== false && !in_array((int)$screenshotId, $screenshotIds, true)) {
        $screenshotIds[] = (int)$screenshotId;
    }
}
if (count($screenshotIds) > 12) {
    $errors[] = ['field' => 'screenshots', 'message' => 'Vyberte nejvýše 12 snímků obrazovky.'];
}
if ($screenshotIds !== []) {
    $placeholders = implode(',', array_fill(0, count($screenshotIds), '?'));
    $mediaStmt = $pdo->prepare(
        "SELECT id, alt_text FROM cms_media
         WHERE id IN ({$placeholders})
           AND visibility = 'public'
           AND mime_type LIKE 'image/%'"
    );
    $mediaStmt->execute($screenshotIds);
    $validScreenshotRows = $mediaStmt->fetchAll();
    $validScreenshotIds = array_map(
        static fn (array $media): int => (int)$media['id'],
        $validScreenshotRows
    );
    if (count($validScreenshotIds) !== count($screenshotIds)) {
        $errors[] = ['field' => 'screenshots', 'message' => 'Jeden nebo více snímků není veřejný obrázek.'];
    }
    foreach ($validScreenshotRows as $media) {
        if (trim((string)$media['alt_text']) === '') {
            $errors[] = [
                'field' => 'screenshots',
                'message' => 'Každý vybraný snímek musí mít v knihovně médií výstižný alt text.',
            ];
            break;
        }
    }
}

if ($id !== null && isSuperAdmin()) {
    $requestedStatus = appmarketNormalizeAppStatus((string)($_POST['status'] ?? $form['status']));
    if ($requestedStatus === 'published') {
        $publishedStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM cms_appmarket_releases WHERE app_id = ? AND status = 'published'"
        );
        $publishedStmt->execute([$id]);
        if ((int)$publishedStmt->fetchColumn() === 0) {
            $errors[] = ['field' => 'status', 'message' => 'Aplikaci lze zveřejnit až po zveřejnění alespoň jednoho vydání.'];
        } else {
            $form['status'] = 'published';
        }
    } else {
        $form['status'] = $requestedStatus;
    }
}

if ($errors !== []) {
    $_SESSION['appmarket_form_flash'] = [
        'form' => $form,
        'errors' => $errors,
        'field_errors' => array_values(array_unique(array_map(
            static fn (array $error): string => (string)$error['field'],
            $errors
        ))),
        'screenshots' => $screenshotIds,
    ];
    header('Location: appmarket_form.php' . ($id !== null ? '?id=' . $id : ''));
    exit;
}

try {
    $pdo->beginTransaction();
    if ($id === null) {
        $pdo->prepare(
            "INSERT INTO cms_appmarket_apps
             (name, slug, package_id, short_description, description, icon_media_id,
              website_url, support_url, privacy_url, license_label, status, is_featured,
              sort_order, created_by_user_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,'draft',?,?,?)"
        )->execute([
            $form['name'],
            $form['slug'],
            $form['package_id'],
            $form['short_description'],
            $form['description'],
            $form['icon_media_id'],
            $form['website_url'],
            $form['support_url'],
            $form['privacy_url'],
            $form['license_label'],
            $form['is_featured'],
            $form['sort_order'],
            currentUserId(),
        ]);
        $id = (int)$pdo->lastInsertId();
    } else {
        $pdo->prepare(
            "UPDATE cms_appmarket_apps
             SET name = ?, slug = ?, package_id = ?, short_description = ?, description = ?,
                 icon_media_id = ?, website_url = ?, support_url = ?, privacy_url = ?,
                 license_label = ?, status = ?, is_featured = ?, sort_order = ?,
                 published_at = CASE WHEN ? = 'published' THEN COALESCE(published_at, NOW()) ELSE published_at END
             WHERE id = ?"
        )->execute([
            $form['name'],
            $form['slug'],
            $form['package_id'],
            $form['short_description'],
            $form['description'],
            $form['icon_media_id'],
            $form['website_url'],
            $form['support_url'],
            $form['privacy_url'],
            $form['license_label'],
            $form['status'],
            $form['is_featured'],
            $form['sort_order'],
            $form['status'],
            $id,
        ]);
    }

    $pdo->prepare('DELETE FROM cms_appmarket_screenshots WHERE app_id = ?')->execute([$id]);
    $insertScreenshotStmt = $pdo->prepare(
        "INSERT INTO cms_appmarket_screenshots (app_id, media_id, alt_text, caption, sort_order)
         SELECT ?, id, alt_text, caption, ?
         FROM cms_media
         WHERE id = ?
           AND visibility = 'public'
           AND mime_type LIKE 'image/%'"
    );
    foreach ($screenshotIds as $sortOrder => $screenshotId) {
        $insertScreenshotStmt->execute([$id, $sortOrder, $screenshotId]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    koraLog('error', 'appmarket app save failed', ['app_id' => $id, 'exception' => $e]);
    $_SESSION['appmarket_form_flash'] = [
        'form' => $form,
        'errors' => [['field' => 'form', 'message' => 'Aplikaci se nepodařilo uložit.']],
        'field_errors' => [],
        'screenshots' => $screenshotIds,
    ];
    header('Location: appmarket_form.php' . ($id !== null ? '?id=' . $id : ''));
    exit;
}

header('Location: appmarket.php?ok=saved');
exit;
