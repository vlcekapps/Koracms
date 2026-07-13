<?php

/**
 * Převod obsahu: článek -> stránka nebo stránka -> článek.
 * POST: direction (article_to_page | page_to_article), id, stage, redirect, csrf_token
 */
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro převod článků a stránek nemáte potřebné oprávnění.');
requireModuleEnabled('blog');
requireHttpMethods(['POST']);
verifyCsrf();

/**
 * @param array{
 *   heading:string,
 *   description:string,
 *   source_type:string,
 *   source_title:string,
 *   target_type:string,
 *   target_context:string,
 *   source_path:string,
 *   review_items:list<string>,
 *   direction:string,
 *   source_id:int,
 *   return_target:string,
 *   confirm_field:string,
 *   confirm_label:string,
 *   submit_label:string,
 *   confirm_error:bool,
 *   operation_error:string
 * } $review
 */
function renderContentConversionReview(array $review): void
{
    $reviewId = 'content-conversion-review';
    $formErrorId = 'content-conversion-form-error';
    $confirmId = 'content-conversion-confirm';
    $confirmErrorId = 'content-conversion-confirm-error';
    $confirmErrorFields = $review['confirm_error'] ? [$review['confirm_field']] : [];
    $hasFormError = $review['confirm_error'] || $review['operation_error'] !== '';

    adminHeader($review['heading']);
    ?>

    <p class="admin-description"><?= h($review['description']) ?></p>

    <dl class="admin-summary-list">
      <dt>Zdroj</dt>
      <dd><?= h($review['source_type']) ?> „<?= h($review['source_title']) ?>“</dd>
      <dt>Výsledek</dt>
      <dd><?= h($review['target_type']) ?> v <?= h($review['target_context']) ?></dd>
      <dt>Dosavadní veřejná cesta</dt>
      <dd><code><?= h($review['source_path']) ?></code></dd>
    </dl>

    <form method="post" action="<?= BASE_URL ?>/admin/convert_content.php" class="form-card" novalidate<?= $hasFormError ? ' aria-describedby="' . h($formErrorId) . '"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="direction" value="<?= h($review['direction']) ?>">
      <input type="hidden" name="id" value="<?= $review['source_id'] ?>">
      <input type="hidden" name="stage" value="confirm">
      <input type="hidden" name="redirect" value="<?= h($review['return_target']) ?>">

      <fieldset>
        <legend>Kontrola dopadu převodu</legend>

        <?php if ($review['confirm_error']): ?>
          <p id="<?= h($formErrorId) ?>" class="error" role="alert" aria-atomic="true">Převod nelze provést bez potvrzení kontroly dopadu. U pole Potvrzení převodu je konkrétní nápověda.</p>
        <?php elseif ($review['operation_error'] !== ''): ?>
          <p id="<?= h($formErrorId) ?>" class="error" role="alert" aria-atomic="true"><?= h($review['operation_error']) ?></p>
        <?php endif; ?>

        <p id="<?= h($reviewId) ?>" class="field-help field-help--flush">Převod vytvoří nový obsahový záznam. Původní záznam se přesune do Koše, takže jej lze obnovit; obnovení ale samo neodstraní nově vytvořený záznam.</p>
        <ul aria-labelledby="<?= h($reviewId) ?>">
          <?php foreach ($review['review_items'] as $reviewItem): ?>
            <li><?= h($reviewItem) ?></li>
          <?php endforeach; ?>
        </ul>

        <label for="<?= h($confirmId) ?>" class="admin-checkbox-label">
          <input type="checkbox" id="<?= h($confirmId) ?>" name="<?= h($review['confirm_field']) ?>" value="1" required aria-required="true"<?= adminFieldAttributes($review['confirm_field'], $confirmErrorFields, [], [$reviewId], $confirmErrorId) ?>>
          <?= h($review['confirm_label']) ?>
        </label>
        <?php adminRenderFieldError($review['confirm_field'], $confirmErrorFields, [], 'Před převodem potvrďte, že jste zkontrolovali zdroj, cílový typ, zachovaná data a změnu veřejné adresy.', $confirmErrorId); ?>

        <div class="button-row admin-action-row">
          <button type="submit" class="btn"><?= h($review['submit_label']) ?></button>
          <a href="<?= h($review['return_target']) ?>" class="button-secondary">Zrušit převod</a>
        </div>
      </fieldset>
    </form>

    <?php
    adminFooter();
}

$direction = trim((string)($_POST['direction'] ?? ''));
$sourceId = inputInt('post', 'id');
$stage = trim((string)($_POST['stage'] ?? 'review'));

if ($sourceId === null || !in_array($direction, ['article_to_page', 'page_to_article'], true)) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$defaultReturnTarget = $direction === 'article_to_page'
    ? BASE_URL . '/admin/blog.php'
    : BASE_URL . '/admin/pages.php';
$returnTarget = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), $defaultReturnTarget);
$pdo = db_connect();

/**
 * @param list<mixed> $params
 */
$countRows = static function (string $sql, array $params) use ($pdo): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
};

if ($direction === 'article_to_page') {
    $stmt = $pdo->prepare(
        "SELECT a.id, a.title, a.slug, a.perex, a.content, a.blog_id, a.category_id, a.author_id,
                a.image_file, a.meta_title, a.meta_description, a.preview_token, a.status,
                a.publish_at, a.unpublish_at, a.admin_note, a.view_count, a.is_featured_in_blog,
                a.created_at, a.updated_at, b.name AS blog_name, b.slug AS blog_slug
         FROM cms_articles a
         LEFT JOIN cms_blogs b ON b.id = a.blog_id
         WHERE a.id = ? AND a.deleted_at IS NULL"
    );
    $stmt->execute([$sourceId]);
    $article = $stmt->fetch();

    if (!$article) {
        header('Location: ' . $returnTarget);
        exit;
    }

    $commentCount = $countRows('SELECT COUNT(*) FROM cms_comments WHERE article_id = ?', [$sourceId]);
    $tagCount = $countRows('SELECT COUNT(*) FROM cms_article_tags WHERE article_id = ?', [$sourceId]);
    $seriesCount = $countRows('SELECT COUNT(*) FROM cms_blog_series_items WHERE article_id = ?', [$sourceId]);
    $relatedCount = $countRows(
        'SELECT COUNT(*) FROM cms_article_related WHERE article_id = ? OR related_article_id = ?',
        [$sourceId, $sourceId]
    );
    $confirmField = 'confirm_article_to_page_' . $sourceId;
    $review = [
        'heading' => 'Kontrola převodu článku na stránku',
        'description' => 'Před převodem zkontrolujte nový typ obsahu, veřejnou adresu a data, která zůstanou u původního článku v Koši.',
        'source_type' => 'Článek',
        'source_title' => (string)$article['title'],
        'target_type' => 'Statická stránka',
        'target_context' => trim((string)($article['blog_name'] ?? '')) !== ''
            ? 'blogu „' . (string)$article['blog_name'] . '“'
            : 'původním blogu',
        'source_path' => articlePublicPath($article),
        'review_items' => [
            'Nová stránka převezme název, slug, perex s obsahem, blog, publikační stav a plánování, interní poznámku, náhledový token a původní časové údaje.',
            'Původní článek se přesune do Koše. Zachová se u něj ' . $commentCount . ' komentářů, ' . $tagCount . ' vazeb na štítky, ' . $seriesCount . ' vazeb na série a ' . $relatedCount . ' vazeb na související články.',
            'Kategorie, autor, obrázek, SEO metadata, počet zobrazení a příznak doporučeného článku se do stránky nepřenášejí; zůstanou zachované u zdrojového článku v Koši.',
            'Nová stránka nebude automaticky přidána do veřejné navigace a dosavadní adresa článku přestane být veřejně dostupná.',
        ],
        'direction' => $direction,
        'source_id' => $sourceId,
        'return_target' => $returnTarget,
        'confirm_field' => $confirmField,
        'confirm_label' => 'Potvrzuji převod tohoto článku na stránku a přesun původního článku do Koše.',
        'submit_label' => 'Převést článek na stránku',
        'confirm_error' => $stage === 'confirm'
            && (!isset($_POST[$confirmField]) || (string)$_POST[$confirmField] !== '1'),
        'operation_error' => '',
    ];

    if ($stage !== 'confirm' || $review['confirm_error']) {
        renderContentConversionReview($review);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $pageBlogId = !empty($article['blog_id']) ? (int)$article['blog_id'] : null;
        $slug = uniquePageSlug(
            $pdo,
            pageSlug((string)$article['slug'] ?: (string)$article['title']),
            null,
            $pageBlogId
        );
        $navOrder = $pageBlogId === null ? nextPageNavigationOrder($pdo) : 0;
        $blogNavOrder = $pageBlogId !== null ? nextBlogPageNavigationOrder($pdo, $pageBlogId) : 0;
        $pageContent = '';
        if (trim((string)$article['perex']) !== '') {
            $pageContent = '<p><strong>' . h((string)$article['perex']) . '</strong></p>' . "\n\n";
        }
        $pageContent .= (string)$article['content'];

        $pdo->prepare(
            "INSERT INTO cms_pages
                (title, slug, content, blog_id, blog_nav_order, is_published, show_in_nav, nav_order,
                 status, publish_at, unpublish_at, admin_note, preview_token, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            (string)$article['title'],
            $slug,
            $pageContent,
            $pageBlogId,
            $blogNavOrder,
            (string)$article['status'] === 'published' ? 1 : 0,
            $navOrder,
            (string)$article['status'],
            $article['publish_at'] !== null ? (string)$article['publish_at'] : null,
            $article['unpublish_at'] !== null ? (string)$article['unpublish_at'] : null,
            (string)($article['admin_note'] ?? ''),
            (string)($article['preview_token'] ?? ''),
            (string)$article['created_at'],
            (string)($article['updated_at'] ?: $article['created_at']),
        ]);
        $newPageId = (int)$pdo->lastInsertId();

        $softDeleteStmt = $pdo->prepare('UPDATE cms_articles SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
        $softDeleteStmt->execute([$sourceId]);
        if ($softDeleteStmt->rowCount() !== 1) {
            throw new RuntimeException('Zdrojový článek se během převodu změnil.');
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        koraLog('error', 'article to page conversion failed', [
            'article_id' => $sourceId,
            'exception' => $e,
        ]);
        $review['operation_error'] = 'Převod se nepodařilo uložit. Žádná data se nezměnila; obnovte kontrolu dopadu a zkuste akci znovu.';
        renderContentConversionReview($review);
        exit;
    }

    releaseContentLock('article', $sourceId);
    logAction(
        'convert_article_to_page',
        'article_id=' . $sourceId . ' page_id=' . $newPageId . ' source_soft_deleted=true title=' . (string)$article['title']
    );

    header('Location: ' . appendUrlQuery(BASE_URL . '/admin/page_form.php', [
        'id' => $newPageId,
        'converted' => 'article_to_page',
    ]));
    exit;
}

$stmt = $pdo->prepare(
    "SELECT p.id, p.title, p.slug, p.content, p.blog_id, p.blog_nav_order, p.show_in_nav,
            p.nav_order, p.is_published, p.status, p.publish_at, p.unpublish_at, p.admin_note,
            p.preview_token, p.created_at, p.updated_at, b.name AS blog_name, b.slug AS blog_slug
     FROM cms_pages p
     LEFT JOIN cms_blogs b ON b.id = p.blog_id
     WHERE p.id = ? AND p.deleted_at IS NULL"
);
$stmt->execute([$sourceId]);
$page = $stmt->fetch();

if (!$page) {
    header('Location: ' . $returnTarget);
    exit;
}

$targetBlog = !empty($page['blog_id']) ? getBlogById((int)$page['blog_id']) : getDefaultBlog();
if (!$targetBlog) {
    header('Location: ' . appendUrlQuery($returnTarget, ['conversion_error' => 'missing_blog']));
    exit;
}

$targetBlogId = (int)$targetBlog['id'];
$confirmField = 'confirm_page_to_article_' . $sourceId;
$review = [
    'heading' => 'Kontrola převodu stránky na článek',
    'description' => 'Před převodem zkontrolujte cílový blog, změnu veřejné adresy a data, která zůstanou u původní stránky v Koši.',
    'source_type' => 'Statická stránka',
    'source_title' => (string)$page['title'],
    'target_type' => 'Článek',
    'target_context' => 'blogu „' . (string)$targetBlog['name'] . '“',
    'source_path' => pagePublicPath($page),
    'review_items' => [
        'Nový článek převezme název, slug, obsah, publikační stav a plánování, interní poznámku, náhledový token a původní časové údaje.',
        'Původní stránka se přesune do Koše včetně svého přiřazení k blogu a pořadí v navigaci.',
        'Nový článek dostane jako autora aktuálně přihlášeného správce; komentáře budou povolené a perex, kategorie, štítky, série a související články začnou prázdné.',
        'Dosavadní stránka zmizí z veřejné navigace a její veřejná adresa přestane být dostupná.',
    ],
    'direction' => $direction,
    'source_id' => $sourceId,
    'return_target' => $returnTarget,
    'confirm_field' => $confirmField,
    'confirm_label' => 'Potvrzuji převod této stránky na článek a přesun původní stránky do Koše.',
    'submit_label' => 'Převést stránku na článek',
    'confirm_error' => $stage === 'confirm'
        && (!isset($_POST[$confirmField]) || (string)$_POST[$confirmField] !== '1'),
    'operation_error' => '',
];

if ($stage !== 'confirm' || $review['confirm_error']) {
    renderContentConversionReview($review);
    exit;
}

try {
    $pdo->beginTransaction();

    $slug = uniqueArticleSlug(
        $pdo,
        articleSlug((string)$page['slug'] ?: (string)$page['title']),
        null,
        $targetBlogId
    );
    $status = (string)($page['status'] ?? 'published');
    if ($status === '' || $status === 'published') {
        $status = (int)$page['is_published'] === 1 ? 'published' : 'pending';
    }

    $pdo->prepare(
        "INSERT INTO cms_articles
            (title, slug, perex, content, blog_id, status, comments_enabled, author_id,
             publish_at, unpublish_at, admin_note, preview_token, created_at, updated_at)
         VALUES (?, ?, '', ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([
        (string)$page['title'],
        $slug,
        (string)$page['content'],
        $targetBlogId,
        $status,
        (int)currentUserId(),
        $page['publish_at'] !== null ? (string)$page['publish_at'] : null,
        $page['unpublish_at'] !== null ? (string)$page['unpublish_at'] : null,
        (string)($page['admin_note'] ?? ''),
        (string)($page['preview_token'] ?? ''),
        (string)$page['created_at'],
        (string)($page['updated_at'] ?: $page['created_at']),
    ]);
    $newArticleId = (int)$pdo->lastInsertId();

    $softDeleteStmt = $pdo->prepare('UPDATE cms_pages SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
    $softDeleteStmt->execute([$sourceId]);
    if ($softDeleteStmt->rowCount() !== 1) {
        throw new RuntimeException('Zdrojová stránka se během převodu změnila.');
    }

    if (!empty($page['blog_id'])) {
        normalizeBlogPageNavigationOrder($pdo, (int)$page['blog_id']);
    } else {
        normalizePageNavigationOrder($pdo);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    koraLog('error', 'page to article conversion failed', [
        'page_id' => $sourceId,
        'exception' => $e,
    ]);
    $review['operation_error'] = 'Převod se nepodařilo uložit. Žádná data se nezměnila; obnovte kontrolu dopadu a zkuste akci znovu.';
    renderContentConversionReview($review);
    exit;
}

releaseContentLock('page', $sourceId);
logAction(
    'convert_page_to_article',
    'page_id=' . $sourceId . ' article_id=' . $newArticleId . ' source_soft_deleted=true title=' . (string)$page['title']
);

header('Location: ' . appendUrlQuery(BASE_URL . '/admin/blog_form.php', [
    'id' => $newArticleId,
    'converted' => 'page_to_article',
]));
exit;
