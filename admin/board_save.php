<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo         = db_connect();
$id          = inputInt('post', 'id');
$title       = trim($_POST['title']       ?? '');
$categoryId  = inputInt('post', 'category_id');
$description = trim($_POST['description'] ?? '');
$postedDate  = trim($_POST['posted_date'] ?? '');
$removalDate = trim($_POST['removal_date'] ?? '');
$sortOrder   = max(0, (int)($_POST['sort_order'] ?? 0));
$isPublished = isset($_POST['is_published']) ? 1 : 0;

if ($title === '' || $postedDate === '') {
    header('Location: board_form.php' . ($id ? "?id={$id}" : ''));
    exit;
}

if ($removalDate !== '' && $removalDate < $postedDate) {
    header('Location: board_form.php' . ($id ? "?id={$id}" : ''));
    exit;
}
if ($removalDate === '') $removalDate = null;

// ── Nahrání souboru ───────────────────────────────────────────────────────
$newFilename = null;
$newOrigName = null;
$newFileSize = null;

if (!empty($_FILES['file']['name'])) {
    $tmp   = $_FILES['file']['tmp_name'];
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmp);

    $allowed = [
        'application/pdf'                                                         => 'pdf',
        'application/msword'                                                      => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel'                                                => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
        'application/vnd.ms-powerpoint'                                           => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.text'                                 => 'odt',
        'application/vnd.oasis.opendocument.spreadsheet'                          => 'ods',
        'application/vnd.oasis.opendocument.presentation'                         => 'odp',
        'application/zip'                                                         => 'zip',
        'application/x-zip-compressed'                                            => 'zip',
        'text/plain'                                                              => 'txt',
    ];

    if (isset($allowed[$mime])) {
        $ext = $allowed[$mime];
        $dir = __DIR__ . '/../uploads/board/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $storedName = uniqid('board_', true) . '.' . $ext;

        if (move_uploaded_file($tmp, $dir . $storedName)) {
            if ($id !== null) {
                $old = $pdo->prepare("SELECT filename FROM cms_board WHERE id = ?");
                $old->execute([$id]);
                $oldFile = $old->fetchColumn();
                if ($oldFile && file_exists($dir . $oldFile)) { unlink($dir . $oldFile); }
            }
            $newFilename = $storedName;
            $newOrigName = basename($_FILES['file']['name']);
            $newFileSize = (int)$_FILES['file']['size'];
        }
    }
}

// ── Uložení ───────────────────────────────────────────────────────────────
if ($id !== null) {
    $set    = "title=?, category_id=?, description=?, posted_date=?, removal_date=?,
               sort_order=?, is_published=?, author_id=COALESCE(author_id,?)";
    $params = [$title, $categoryId, $description, $postedDate, $removalDate,
               $sortOrder, $isPublished, currentUserId()];
    if ($newFilename !== null) {
        $set     .= ", filename=?, original_name=?, file_size=?";
        $params[] = $newFilename;
        $params[] = $newOrigName;
        $params[] = $newFileSize;
    }
    $params[] = $id;
    $pdo->prepare("UPDATE cms_board SET {$set} WHERE id=?")->execute($params);
    logAction('board_edit', "id={$id}");
} else {
    $status   = isSuperAdmin() ? 'published' : 'pending';
    $authorId = currentUserId();
    $pdo->prepare(
        "INSERT INTO cms_board
         (title, category_id, description, posted_date, removal_date, filename, original_name, file_size,
          sort_order, is_published, status, author_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([$title, $categoryId, $description, $postedDate, $removalDate,
                $newFilename ?? '', $newOrigName ?? '', $newFileSize ?? 0,
                $sortOrder, isSuperAdmin() ? $isPublished : 0, $status, $authorId]);
    logAction('board_add', "title={$title} status={$status}");
}

header('Location: ' . BASE_URL . '/admin/board.php');
exit;
