<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo         = db_connect();
$id          = inputInt('post', 'id');
$showId      = inputInt('post', 'show_id');
$title       = trim($_POST['title']       ?? '');
$description = $_POST['description']      ?? '';
$audioUrl    = trim($_POST['audio_url']   ?? '');
$duration    = trim($_POST['duration']    ?? '');
$episodeNum  = !empty($_POST['episode_num']) ? (int)$_POST['episode_num'] : null;

// Pokud show_id chybí, přečteme ho z existující epizody
if ($showId === null && $id !== null) {
    $s = $pdo->prepare("SELECT show_id FROM cms_podcasts WHERE id = ?");
    $s->execute([$id]);
    $showId = (int)$s->fetchColumn() ?: 1;
}
if ($showId === null) $showId = 1;

$publishAt = null;
if (!empty($_POST['publish_at'])) {
    $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $_POST['publish_at']);
    if ($dt) $publishAt = $dt->format('Y-m-d H:i:s');
}

if ($title === '') {
    header('Location: podcast_form.php' . ($id ? "?id={$id}&show_id={$showId}" : "?show_id={$showId}"));
    exit;
}

// Audio soubor
$audioFile = null;
if (!empty($_FILES['audio_file']['name'])) {
    $tmp   = $_FILES['audio_file']['tmp_name'];
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmp);
    $allowed = ['audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav'];
    if (isset($allowed[$mime])) {
        $dir = __DIR__ . '/../uploads/podcasts/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = uniqid('ep_', true) . '.' . $allowed[$mime];
        if (move_uploaded_file($tmp, $dir . $filename)) {
            if ($id !== null) {
                $old = $pdo->prepare("SELECT audio_file FROM cms_podcasts WHERE id = ?");
                $old->execute([$id]);
                $oldFile = $old->fetchColumn();
                if ($oldFile) @unlink($dir . $oldFile);
            }
            $audioFile = $filename;
            $audioUrl  = '';
        }
    }
}

if ($id !== null) {
    $set    = "title=?,description=?,audio_url=?,duration=?,episode_num=?,publish_at=?,show_id=?,updated_at=NOW()";
    $params = [$title, $description, $audioUrl, $duration, $episodeNum, $publishAt, $showId];
    if ($audioFile !== null) { $set .= ",audio_file=?"; $params[] = $audioFile; }
    $params[] = $id;
    $pdo->prepare("UPDATE cms_podcasts SET {$set} WHERE id=?")->execute($params);
    logAction('podcast_edit', "id={$id}");
} else {
    $status = isSuperAdmin() ? 'published' : 'pending';
    $pdo->prepare(
        "INSERT INTO cms_podcasts (show_id,title,description,audio_file,audio_url,duration,episode_num,publish_at,status)
         VALUES (?,?,?,?,?,?,?,?,?)"
    )->execute([$showId, $title, $description, $audioFile ?? '', $audioUrl, $duration, $episodeNum, $publishAt, $status]);
    logAction('podcast_add', "title={$title} show_id={$showId} status={$status}");
}

header('Location: ' . BASE_URL . '/admin/podcast.php?show_id=' . (int)$showId);
exit;
