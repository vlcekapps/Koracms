<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo           = db_connect();
$id            = inputInt('post', 'id');
$title         = trim($_POST['title']          ?? '');
$dlCategoryId  = inputInt('post', 'dl_category_id');
$description   = trim($_POST['description']    ?? '');
$sortOrder     = max(0, (int)($_POST['sort_order'] ?? 0));
$isPublished   = isset($_POST['is_published']) ? 1 : 0;

if ($title === '') {
    header('Location: download_form.php' . ($id ? "?id={$id}" : ''));
    exit;
}

// ── Nahrání souboru ───────────────────────────────────────────────────────
$newFilename   = null;
$newOrigName   = null;
$newFileSize   = null;

if (!empty($_FILES['file']['name'])) {
    $tmp   = $_FILES['file']['tmp_name'];
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmp);

    $allowed = [
        'application/pdf'                                                        => 'pdf',
        'application/msword'                                                     => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=> 'docx',
        'application/vnd.ms-excel'                                               => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'     => 'xlsx',
        'application/vnd.ms-powerpoint'                                          => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.text'                                => 'odt',
        'application/vnd.oasis.opendocument.spreadsheet'                         => 'ods',
        'application/vnd.oasis.opendocument.presentation'                        => 'odp',
        'application/zip'                                                        => 'zip',
        'application/x-zip-compressed'                                           => 'zip',
        'text/plain'                                                             => 'txt',
    ];

    if (isset($allowed[$mime])) {
        $ext  = $allowed[$mime];
        $dir  = __DIR__ . '/../uploads/downloads/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $storedName = uniqid('dl_', true) . '.' . $ext;

        if (move_uploaded_file($tmp, $dir . $storedName)) {
            // Smazat starý soubor
            if ($id !== null) {
                $old = $pdo->prepare("SELECT filename FROM cms_downloads WHERE id = ?");
                $old->execute([$id]);
                $oldFile = $old->fetchColumn();
                if ($oldFile && file_exists($dir . $oldFile)) { unlink($dir . $oldFile); }
            }
            $newFilename = $storedName;
            $newOrigName = basename($_FILES['file']['name']);
            $newFileSize = (int)$_FILES['file']['size'];
        }
    }
} elseif ($id === null) {
    // Nový záznam bez souboru – přesměruj zpět
    header('Location: download_form.php');
    exit;
}

// ── Uložení ───────────────────────────────────────────────────────────────
if ($id !== null) {
    $set    = "title=?, dl_category_id=?, description=?, sort_order=?, is_published=?,
               author_id=COALESCE(author_id,?)";
    $params = [$title, $dlCategoryId, $description, $sortOrder, $isPublished, currentUserId()];
    if ($newFilename !== null) {
        $set     .= ", filename=?, original_name=?, file_size=?";
        $params[] = $newFilename;
        $params[] = $newOrigName;
        $params[] = $newFileSize;
    }
    $params[] = $id;
    $pdo->prepare("UPDATE cms_downloads SET {$set} WHERE id=?")->execute($params);
    logAction('download_edit', "id={$id}");
} else {
    $status   = currentUserHasCapability('content_approve_shared') ? 'published' : 'pending';
    $authorId = currentUserId();
    $pdo->prepare(
        "INSERT INTO cms_downloads
         (title, dl_category_id, description, filename, original_name, file_size, sort_order, is_published, status, author_id)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    )->execute([$title, $dlCategoryId, $description, $newFilename, $newOrigName, $newFileSize,
                $sortOrder, currentUserHasCapability('content_approve_shared') ? $isPublished : 0, $status, $authorId]);
    logAction('download_add', "title={$title} status={$status}");
}

header('Location: ' . BASE_URL . '/admin/downloads.php');
exit;
