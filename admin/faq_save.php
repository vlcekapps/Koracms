<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu FAQ nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$question = trim($_POST['question'] ?? '');
$submittedSlug = trim($_POST['slug'] ?? '');
$excerpt = trim($_POST['excerpt'] ?? '');
$answer = $_POST['answer'] ?? '';
$categoryId = inputInt('post', 'category_id');
$isPublished = isset($_POST['is_published']) ? 1 : 0;

if ($question === '' || trim($answer) === '') {
    header('Location: faq_form.php?err=required' . ($id ? '&id=' . $id : ''));
    exit;
}

$existingFaq = null;
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT id, status FROM cms_faqs WHERE id = ?");
    $existingStmt->execute([$id]);
    $existingFaq = $existingStmt->fetch() ?: null;
    if (!$existingFaq) {
        header('Location: ' . BASE_URL . '/admin/faq.php');
        exit;
    }
}

$slug = faqSlug($submittedSlug !== '' ? $submittedSlug : $question);
if ($slug === '') {
    header('Location: faq_form.php?err=slug' . ($id ? '&id=' . $id : ''));
    exit;
}

$uniqueSlug = uniqueFaqSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    header('Location: faq_form.php?err=slug' . ($id ? '&id=' . $id : ''));
    exit;
}
$slug = $uniqueSlug;

if ($existingFaq) {
    $oldStmt = $pdo->prepare("SELECT question, slug, excerpt, answer FROM cms_faqs WHERE id = ?");
    $oldStmt->execute([$id]);
    $oldData = $oldStmt->fetch();
    if ($oldData) {
        saveRevision($pdo, 'faq', $id, $oldData, [
            'question' => $question, 'slug' => $slug, 'excerpt' => $excerpt, 'answer' => $answer,
        ]);
    }

    $pdo->prepare(
        "UPDATE cms_faqs
         SET question = ?, slug = ?, excerpt = ?, answer = ?, category_id = ?, is_published = ?, updated_at = NOW()
         WHERE id = ?"
    )->execute([
        $question,
        $slug,
        $excerpt,
        $answer,
        $categoryId,
        $isPublished,
        $id,
    ]);
    logAction('faq_edit', "id={$id} question={$question} slug={$slug}");
} else {
    $status = currentUserHasCapability('content_approve_shared') ? 'published' : 'pending';
    $visible = currentUserHasCapability('content_approve_shared') ? $isPublished : 0;
    $pdo->prepare(
        "INSERT INTO cms_faqs (question, slug, excerpt, answer, category_id, is_published, status)
         VALUES (?,?,?,?,?,?,?)"
    )->execute([
        $question,
        $slug,
        $excerpt,
        $answer,
        $categoryId,
        $visible,
        $status,
    ]);
    $id = (int)$pdo->lastInsertId();
    logAction('faq_add', "id={$id} question={$question} slug={$slug} status={$status}");
}

header('Location: ' . BASE_URL . '/admin/faq.php');
exit;
