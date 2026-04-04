<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu FAQ nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$question = trim($_POST['question'] ?? '');
$submittedSlug = trim($_POST['slug'] ?? '');
$excerpt = trim($_POST['excerpt'] ?? '');
$metaTitle = trim((string)($_POST['meta_title'] ?? ''));
$metaDescription = trim((string)($_POST['meta_description'] ?? ''));
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

$faqCategoryNames = [];
foreach ($pdo->query("SELECT id, name FROM cms_faq_categories ORDER BY sort_order, name")->fetchAll() as $faqCategoryRow) {
    $faqCategoryNames[(int)$faqCategoryRow['id']] = (string)$faqCategoryRow['name'];
}

if ($existingFaq) {
    $oldStmt = $pdo->prepare("SELECT * FROM cms_faqs WHERE id = ?");
    $oldStmt->execute([$id]);
    $oldData = $oldStmt->fetch();

    $requestedStatus = trim($_POST['article_status'] ?? '');
    if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
        $requestedStatus = $oldData['status'] ?? 'published';
    }
    if ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
        $requestedStatus = (($oldData['status'] ?? '') === 'published') ? 'published' : 'pending';
    }

    if ($oldData) {
        saveRevision(
            $pdo,
            'faq',
            $id,
            faqRevisionSnapshot($oldData, $faqCategoryNames),
            faqRevisionSnapshot([
                'question' => $question,
                'slug' => $slug,
                'excerpt' => $excerpt,
                'answer' => $answer,
                'category_id' => $categoryId,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'is_published' => $isPublished,
                'status' => $requestedStatus,
            ], $faqCategoryNames)
        );
    }

    $oldPath = $oldData ? faqPublicPath($oldData) : '';

    // Při první publikaci aktualizovat created_at
    $publishingNow = $requestedStatus === 'published' && ($oldData['status'] ?? '') !== 'published';
    $createdAtClause = $publishingNow ? ', created_at = NOW()' : '';

    $pdo->prepare(
        "UPDATE cms_faqs
         SET question = ?, slug = ?, excerpt = ?, answer = ?, category_id = ?, meta_title = ?, meta_description = ?,
             is_published = ?, status = ?, updated_at = NOW(){$createdAtClause}
         WHERE id = ?"
    )->execute([
        $question,
        $slug,
        $excerpt,
        $answer,
        $categoryId,
        $metaTitle,
        $metaDescription,
        $isPublished,
        $requestedStatus,
        $id,
    ]);
    upsertPathRedirect($pdo, $oldPath, faqPublicPath(['id' => $id, 'slug' => $slug]));
    logAction('faq_edit', "id={$id} question={$question} slug={$slug}");
} else {
    $requestedStatus = trim($_POST['article_status'] ?? '');
    if (!in_array($requestedStatus, ['draft', 'pending', 'published'], true)) {
        $requestedStatus = 'draft';
    }
    if ($requestedStatus === 'published' && !currentUserHasCapability('content_approve_shared')) {
        $requestedStatus = 'pending';
    }
    $status = $requestedStatus;
    $visible = currentUserHasCapability('content_approve_shared') ? $isPublished : 0;
    $pdo->prepare(
        "INSERT INTO cms_faqs (question, slug, excerpt, answer, category_id, meta_title, meta_description, is_published, status)
         VALUES (?,?,?,?,?,?,?,?,?)"
    )->execute([
        $question,
        $slug,
        $excerpt,
        $answer,
        $categoryId,
        $metaTitle,
        $metaDescription,
        $visible,
        $status,
    ]);
    $id = (int)$pdo->lastInsertId();
    logAction('faq_add', "id={$id} question={$question} slug={$slug} status={$status}");
    if ($status === 'pending') {
        notifyPendingContent('Znalostní báze', $question, '/admin/faq.php');
    }
}

header('Location: ' . BASE_URL . '/admin/faq.php');
exit;
