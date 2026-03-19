<?php
require_once __DIR__ . '/../db.php';
requireLogin(BASE_URL . '/admin/login.php');
verifyCsrf();

$pdo = db_connect();
$id  = inputInt('post', 'id');

$question    = trim($_POST['question'] ?? '');
$answer      = trim($_POST['answer'] ?? '');
$categoryId  = inputInt('post', 'category_id');
$sortOrder   = max(0, (int)($_POST['sort_order'] ?? 0));
$isPublished = isset($_POST['is_published']) ? 1 : 0;

if ($question === '' || $answer === '') {
    $redir = 'faq_form.php?err=required' . ($id ? '&id=' . $id : '');
    header('Location: ' . $redir);
    exit;
}

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT id FROM cms_faqs WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) { header('Location: faq.php'); exit; }

    $pdo->prepare(
        "UPDATE cms_faqs SET question = ?, answer = ?, category_id = ?, sort_order = ?, is_published = ? WHERE id = ?"
    )->execute([$question, $answer, $categoryId, $sortOrder, $isPublished, $id]);

    logAction('faq_edit', "id={$id}, question=" . mb_substr($question, 0, 80));
} else {
    $pdo->prepare(
        "INSERT INTO cms_faqs (question, answer, category_id, sort_order, is_published) VALUES (?, ?, ?, ?, ?)"
    )->execute([$question, $answer, $categoryId, $sortOrder, $isPublished]);

    logAction('faq_add', "id=" . $pdo->lastInsertId() . ", question=" . mb_substr($question, 0, 80));
}

header('Location: faq.php');
exit;
