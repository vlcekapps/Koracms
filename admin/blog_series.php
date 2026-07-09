<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');
requireModuleEnabled('blog');

$pdo = db_connect();
$blogId = inputInt('get', 'blog_id') ?? inputInt('post', 'blog_id');
$blog = $blogId !== null ? getBlogById($blogId) : null;
if (!$blog) {
    header('Location: ' . BASE_URL . '/admin/blogs.php');
    exit;
}

$blogId = (int)$blog['id'];
if (!canCurrentUserWriteToBlog($blogId)) {
    header('Location: ' . BASE_URL . '/admin/blog.php?msg=no_blog_access');
    exit;
}

$seriesError = '';
$message = trim((string)($_GET['msg'] ?? ''));
$editSeriesId = inputInt('get', 'edit');
$deleteConfirmError = trim((string)($_GET['delete_error'] ?? '')) === 'confirm_required';
$deleteErrorSeriesId = inputInt('get', 'delete_error_id');
$seriesFieldErrors = [];
$seriesFieldErrorMessages = [
    'title' => 'Doplňte krátký název série, například Průvodce začátečníka.',
];
$seriesForm = [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'description' => '',
    'is_active' => 1,
];
$selectedArticleIds = [];
$selectedArticleOrder = [];

/**
 * @param array<int, mixed> $ids
 * @param array<int|string, mixed> $orderMap
 * @return list<int>
 */
function adminBlogSeriesNormalizeArticleOrder(array $ids, array $orderMap): array
{
    $ids = normalizeBlogSeriesIds($ids);
    usort($ids, static function (int $leftId, int $rightId) use ($orderMap): int {
        $leftOrder = max(0, (int)($orderMap[$leftId] ?? $orderMap[(string)$leftId] ?? 0));
        $rightOrder = max(0, (int)($orderMap[$rightId] ?? $orderMap[(string)$rightId] ?? 0));
        if ($leftOrder !== $rightOrder) {
            if ($leftOrder === 0) {
                return 1;
            }
            if ($rightOrder === 0) {
                return -1;
            }
            return $leftOrder <=> $rightOrder;
        }

        return $leftId <=> $rightId;
    });

    return $ids;
}

/**
 * @param list<int> $articleIds
 * @return list<int>
 */
function adminBlogSeriesValidArticleIds(PDO $pdo, int $blogId, array $articleIds): array
{
    $articleIds = normalizeBlogSeriesIds($articleIds);
    if ($articleIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id
         FROM cms_articles
         WHERE blog_id = ?
           AND deleted_at IS NULL
           AND id IN ({$placeholders})"
    );
    $stmt->execute(array_merge([$blogId], $articleIds));
    $validIds = array_values(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));

    return array_values(array_filter(
        $articleIds,
        static fn (int $articleId): bool => in_array($articleId, $validIds, true)
    ));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? 'save_series'));

    if ($action === 'delete_series') {
        $deleteSeriesId = inputInt('post', 'series_id');
        if ($deleteSeriesId !== null) {
            $seriesStmt = $pdo->prepare(
                "SELECT id
                 FROM cms_blog_series
                 WHERE id = ? AND blog_id = ?
                 LIMIT 1"
            );
            $seriesStmt->execute([$deleteSeriesId, $blogId]);
            $seriesForDelete = $seriesStmt->fetch() ?: null;
            if (!$seriesForDelete) {
                header('Location: ' . BASE_URL . '/admin/blog_series.php?blog_id=' . $blogId);
                exit;
            }

            $confirmFieldName = 'confirm_blog_series_delete_' . $deleteSeriesId;
            $deleteConfirmed = isset($_POST[$confirmFieldName])
                && (string)$_POST[$confirmFieldName] === '1';
            if (!$deleteConfirmed) {
                header('Location: ' . BASE_URL . '/admin/blog_series.php?blog_id=' . $blogId . '&delete_error=confirm_required&delete_error_id=' . $deleteSeriesId);
                exit;
            }

            $articleCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cms_blog_series_items WHERE series_id = ?');
            $articleCountStmt->execute([$deleteSeriesId]);
            $seriesArticleCount = (int)$articleCountStmt->fetchColumn();

            $pdo->prepare("DELETE FROM cms_blog_series_items WHERE series_id = ?")->execute([$deleteSeriesId]);
            $pdo->prepare("DELETE FROM cms_blog_series WHERE id = ? AND blog_id = ?")->execute([$deleteSeriesId, $blogId]);
            logAction('blog_series_delete', "blog_id={$blogId};id={$deleteSeriesId};article_count={$seriesArticleCount}");
        }
        header('Location: ' . BASE_URL . '/admin/blog_series.php?blog_id=' . $blogId . '&msg=deleted');
        exit;
    }

    $seriesId = inputInt('post', 'series_id') ?? 0;
    $seriesForm = [
        'id' => $seriesId,
        'title' => trim((string)($_POST['title'] ?? '')),
        'slug' => blogSeriesSlug(trim((string)($_POST['slug'] ?? ''))),
        'description' => trim((string)($_POST['description'] ?? '')),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    $selectedArticleIds = normalizeBlogSeriesIds((array)($_POST['article_ids'] ?? []));
    $selectedArticleOrder = (array)($_POST['article_order'] ?? []);

    if ((string)$seriesForm['title'] === '') {
        $seriesError = 'Sérii článků nejde uložit bez názvu. U pole Název série je konkrétní nápověda.';
        $seriesFieldErrors[] = 'title';
    } else {
        $slugBase = (string)$seriesForm['slug'];
        if ($slugBase === '') {
            $slugBase = (string)$seriesForm['title'];
        }
        $seriesForm['slug'] = uniqueBlogSeriesSlug($pdo, $slugBase, $blogId, $seriesId > 0 ? $seriesId : null);
        $validArticleIds = adminBlogSeriesValidArticleIds(
            $pdo,
            $blogId,
            adminBlogSeriesNormalizeArticleOrder($selectedArticleIds, $selectedArticleOrder)
        );

        try {
            $pdo->beginTransaction();
            $existingSeriesForRedirect = null;
            if ($seriesId > 0) {
                $existingStmt = $pdo->prepare(
                    "SELECT id, title, slug, blog_id, is_active
                     FROM cms_blog_series
                     WHERE id = ? AND blog_id = ?
                     LIMIT 1"
                );
                $existingStmt->execute([$seriesId, $blogId]);
                $existingSeriesForRedirect = $existingStmt->fetch() ?: null;
                if (!$existingSeriesForRedirect) {
                    throw new RuntimeException('Series not found');
                }
                $pdo->prepare(
                    "UPDATE cms_blog_series
                     SET title = ?, slug = ?, description = ?, is_active = ?
                     WHERE id = ? AND blog_id = ?"
                )->execute([
                    (string)$seriesForm['title'],
                    (string)$seriesForm['slug'],
                    (string)$seriesForm['description'],
                    (int)$seriesForm['is_active'],
                    $seriesId,
                    $blogId,
                ]);
            } else {
                $sortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM cms_blog_series WHERE blog_id = ?");
                $sortStmt->execute([$blogId]);
                $sortOrder = (int)$sortStmt->fetchColumn();
                $pdo->prepare(
                    "INSERT INTO cms_blog_series (blog_id, title, slug, description, is_active, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?)"
                )->execute([
                    $blogId,
                    (string)$seriesForm['title'],
                    (string)$seriesForm['slug'],
                    (string)$seriesForm['description'],
                    (int)$seriesForm['is_active'],
                    $sortOrder,
                ]);
                $seriesId = (int)$pdo->lastInsertId();
                $seriesForm['id'] = $seriesId;
            }

            $pdo->prepare("DELETE FROM cms_blog_series_items WHERE series_id = ?")->execute([$seriesId]);
            if ($validArticleIds !== []) {
                $insertItem = $pdo->prepare(
                    "INSERT IGNORE INTO cms_blog_series_items (series_id, article_id, sort_order)
                     VALUES (?, ?, ?)"
                );
                foreach ($validArticleIds as $position => $articleId) {
                    $insertItem->execute([$seriesId, $articleId, $position + 1]);
                }
            }
            if ($existingSeriesForRedirect && (int)$seriesForm['is_active'] === 1 && blogSeriesSlug((string)($existingSeriesForRedirect['slug'] ?? '')) !== '') {
                $updatedSeriesForRedirect = $existingSeriesForRedirect;
                $updatedSeriesForRedirect['title'] = (string)$seriesForm['title'];
                $updatedSeriesForRedirect['slug'] = (string)$seriesForm['slug'];
                $updatedSeriesForRedirect['is_active'] = (int)$seriesForm['is_active'];
                upsertPathRedirect(
                    $pdo,
                    blogSeriesPath($blog, $existingSeriesForRedirect),
                    blogSeriesPath($blog, $updatedSeriesForRedirect),
                    301
                );
            }
            $pdo->commit();
            logAction('blog_series_save', 'blog_id=' . $blogId . ', id=' . $seriesId);
            header('Location: ' . BASE_URL . '/admin/blog_series.php?blog_id=' . $blogId . '&msg=saved');
            exit;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            koraLog('warning', 'admin blog series save failed', [
                'blog_id' => $blogId,
                'series_id' => $seriesId,
                'exception' => $e,
            ]);
            $seriesError = 'Sérii se nepodařilo uložit.';
        }
    }
} elseif ($editSeriesId !== null) {
    $editStmt = $pdo->prepare(
        "SELECT id, title, slug, description, is_active
         FROM cms_blog_series
         WHERE id = ? AND blog_id = ?"
    );
    $editStmt->execute([$editSeriesId, $blogId]);
    $editSeries = $editStmt->fetch() ?: null;
    if ($editSeries) {
        $seriesForm = [
            'id' => (int)$editSeries['id'],
            'title' => (string)$editSeries['title'],
            'slug' => (string)$editSeries['slug'],
            'description' => (string)($editSeries['description'] ?? ''),
            'is_active' => (int)($editSeries['is_active'] ?? 1),
        ];
        $itemsStmt = $pdo->prepare(
            "SELECT article_id, sort_order
             FROM cms_blog_series_items
             WHERE series_id = ?
             ORDER BY sort_order ASC, article_id ASC"
        );
        $itemsStmt->execute([(int)$editSeries['id']]);
        foreach ($itemsStmt->fetchAll() ?: [] as $itemRow) {
            $articleId = (int)$itemRow['article_id'];
            $selectedArticleIds[] = $articleId;
            $selectedArticleOrder[$articleId] = (int)$itemRow['sort_order'];
        }
    }
}

$seriesRows = [];
try {
    $seriesStmt = $pdo->prepare(
        "SELECT s.id, s.title, s.slug, s.description, s.is_active, s.sort_order,
                COUNT(si.article_id) AS article_count
         FROM cms_blog_series s
         LEFT JOIN cms_blog_series_items si ON si.series_id = s.id
         WHERE s.blog_id = ?
         GROUP BY s.id, s.title, s.slug, s.description, s.is_active, s.sort_order
         ORDER BY s.sort_order ASC, s.title ASC, s.id ASC"
    );
    $seriesStmt->execute([$blogId]);
    $seriesRows = $seriesStmt->fetchAll() ?: [];
} catch (\PDOException $e) {
    koraLog('warning', 'admin blog series list failed', ['blog_id' => $blogId, 'exception' => $e]);
}

$articleRows = [];
$articleStmt = $pdo->prepare(
    "SELECT id, title, COALESCE(status, 'published') AS status, COALESCE(publish_at, created_at) AS display_date
     FROM cms_articles
     WHERE blog_id = ?
       AND deleted_at IS NULL
     ORDER BY COALESCE(publish_at, created_at) DESC, id DESC"
);
$articleStmt->execute([$blogId]);
$articleRows = $articleStmt->fetchAll() ?: [];

$selectedArticleLookup = array_fill_keys($selectedArticleIds, true);

adminHeader('Série článků blogu');
?>

<?php if ($message === 'saved'): ?>
  <p class="success" role="status">Série článků byla uložena.</p>
<?php elseif ($message === 'deleted'): ?>
  <p class="success" role="status">Série článků byla smazána.</p>
<?php endif; ?>
<?php if ($deleteConfirmError): ?>
  <p id="blog-series-delete-error" class="error" role="alert" aria-atomic="true">Sérii článků nejde smazat bez potvrzení kontroly dopadu. U pole Potvrzení smazání je konkrétní nápověda.</p>
<?php endif; ?>

<p class="button-row button-row--start">
  <a href="<?= BASE_URL ?>/admin/blog.php?blog=<?= $blogId ?>"><span aria-hidden="true">←</span> Zpět na články blogu</a>
  <a href="<?= h(blogIndexPath($blog)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit blog na webu<?= newWindowLinkSrOnlySuffix() ?></a>
</p>

<p class="admin-description">Tady spravujete tematické série článků blogu <strong><?= h((string)$blog['name']) ?></strong>. Série se veřejně zobrazí jen tehdy, když je aktivní a obsahuje alespoň jeden publikovaný článek.</p>

<form method="post" class="form-card" novalidate<?= $seriesError !== '' ? ' aria-describedby="blog-series-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="blog_id" value="<?= $blogId ?>">
  <input type="hidden" name="action" value="save_series">
  <input type="hidden" name="series_id" value="<?= (int)$seriesForm['id'] ?>">
  <fieldset>
    <legend><?= (int)$seriesForm['id'] > 0 ? 'Upravit sérii článků' : 'Přidat sérii článků' ?></legend>
    <?php if ($seriesError !== ''): ?>
      <p id="blog-series-error" class="error" role="alert" aria-atomic="true"><?= h($seriesError) ?></p>
    <?php endif; ?>

    <label for="series-title">Název série <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="series-title" name="title" required aria-required="true" maxlength="255"
           value="<?= h((string)$seriesForm['title']) ?>"
           <?= adminFieldAttributes('title', $seriesFieldErrors, [], ['series-title-help']) ?>>
    <small id="series-title-help" class="field-help">Použijte krátký název, podle kterého čtenář pozná společné téma článků.</small>
    <?php adminRenderFieldError('title', $seriesFieldErrors, [], $seriesFieldErrorMessages['title']); ?>

    <label for="series-slug">Slug série</label>
    <input type="text" id="series-slug" name="slug" maxlength="255" pattern="[a-z0-9\-]+" value="<?= h((string)$seriesForm['slug']) ?>" aria-describedby="series-slug-help">
    <small id="series-slug-help" class="field-help">Volitelné. Když ho nevyplníte, vytvoří se automaticky z názvu. Veřejná adresa bude mít tvar <code>/<?= h((string)$blog['slug']) ?>/serie/slug-serie</code>.</small>

    <label for="series-description">Popis série</label>
    <textarea id="series-description" name="description" rows="3" aria-describedby="series-description-help"><?= h((string)$seriesForm['description']) ?></textarea>
    <small id="series-description-help" class="field-help">Volitelné. Zobrazí se na veřejné stránce série a pomůže čtenářům pochopit souvislost článků.</small>

    <label><input type="checkbox" name="is_active" value="1"<?= (int)$seriesForm['is_active'] === 1 ? ' checked' : '' ?>> Zobrazovat sérii na webu</label>
  </fieldset>

  <fieldset>
    <legend>Články v sérii</legend>
    <p class="field-help">Zaškrtněte články, které do série patří. Pole pořadí určuje, v jakém pořadí se zobrazí na veřejném webu.</p>
    <?php if ($articleRows === []): ?>
      <p class="empty-state">V tomto blogu zatím nejsou žádné články.</p>
    <?php else: ?>
      <div class="admin-stack-sm">
        <?php foreach ($articleRows as $articleRow): ?>
          <?php
          $articleId = (int)$articleRow['id'];
            $isSelected = isset($selectedArticleLookup[$articleId]);
            $orderValue = (int)($selectedArticleOrder[$articleId] ?? 0);
            $orderId = 'series-article-order-' . $articleId;
            $checkboxId = 'series-article-' . $articleId;
            ?>
          <div class="admin-check-row">
            <label for="<?= h($checkboxId) ?>">
              <input type="checkbox" id="<?= h($checkboxId) ?>" name="article_ids[]" value="<?= $articleId ?>"<?= $isSelected ? ' checked' : '' ?>>
              <?= h((string)$articleRow['title']) ?>
            </label>
            <label for="<?= h($orderId) ?>" class="sr-only">Pořadí článku <?= h((string)$articleRow['title']) ?> v sérii</label>
            <input type="number" id="<?= h($orderId) ?>" name="article_order[<?= $articleId ?>]" min="1" max="9999" value="<?= $orderValue > 0 ? $orderValue : '' ?>" class="admin-input-xs" aria-label="Pořadí v sérii">
            <small class="field-help"><?= h((string)$articleRow['status']) ?>, <?= h(formatCzechDate((string)$articleRow['display_date'])) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </fieldset>

  <div class="button-row admin-action-row">
    <button type="submit" class="btn"><?= (int)$seriesForm['id'] > 0 ? 'Uložit sérii' : 'Přidat sérii' ?></button>
    <?php if ((int)$seriesForm['id'] > 0): ?>
      <a href="<?= BASE_URL ?>/admin/blog_series.php?blog_id=<?= $blogId ?>" class="button-secondary">Zrušit úpravu</a>
    <?php endif; ?>
  </div>
</form>

<h2>Přehled sérií</h2>
<?php if ($seriesRows === []): ?>
  <p>V tomto blogu zatím nejsou žádné série článků.</p>
<?php else: ?>
  <table>
    <caption>Série článků blogu <?= h((string)$blog['name']) ?></caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Slug</th>
        <th scope="col">Články</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($seriesRows as $seriesRow): ?>
        <?php
          $seriesId = (int)$seriesRow['id'];
          $deleteConfirmField = 'confirm_blog_series_delete_' . $seriesId;
          $deleteConfirmId = 'confirm-blog-series-delete-' . $seriesId;
          $deleteReviewId = 'blog-series-delete-review-' . $seriesId;
          $deleteFieldErrorId = 'confirm-blog-series-delete-' . $seriesId . '-error';
          $deleteHasError = $deleteConfirmError && $deleteErrorSeriesId === $seriesId;
          $deleteErrorFields = $deleteHasError ? [$deleteConfirmField] : [];
          ?>
        <tr>
          <td>
            <?= h((string)$seriesRow['title']) ?>
            <?php if ((string)($seriesRow['description'] ?? '') !== ''): ?>
              <br><small class="field-help"><?= h(mb_strimwidth(normalizePlainText((string)$seriesRow['description']), 0, 120, '…', 'UTF-8')) ?></small>
            <?php endif; ?>
          </td>
          <td><code><?= h((string)$seriesRow['slug']) ?></code></td>
          <td><?= (int)$seriesRow['article_count'] ?></td>
          <td><?= (int)$seriesRow['is_active'] === 1 ? 'Aktivní' : 'Skrytá' ?></td>
          <td class="actions">
            <?php if ((int)$seriesRow['is_active'] === 1 && (int)$seriesRow['article_count'] > 0): ?>
              <a class="btn" href="<?= h(blogSeriesPath($blog, $seriesRow)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
            <?php endif; ?>
            <a class="btn" href="<?= BASE_URL ?>/admin/blog_series.php?blog_id=<?= $blogId ?>&amp;edit=<?= $seriesId ?>">Upravit</a>
            <form method="post" class="admin-inline-form" novalidate<?= $deleteHasError ? ' aria-describedby="blog-series-delete-error"' : '' ?>>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="blog_id" value="<?= $blogId ?>">
              <input type="hidden" name="action" value="delete_series">
              <input type="hidden" name="series_id" value="<?= $seriesId ?>">
              <fieldset class="admin-inline-fieldset">
                <legend class="sr-only">Smazání série článků <?= h((string)$seriesRow['title']) ?></legend>
                <p id="<?= h($deleteReviewId) ?>" class="field-help field-help--flush">
                  Smazání odebere sérii z <?= (int)$seriesRow['article_count'] ?> článků. Články zůstanou zachované bez této série.
                </p>
                <label for="<?= h($deleteConfirmId) ?>" class="admin-checkbox-label">
                  <input
                    type="checkbox"
                    id="<?= h($deleteConfirmId) ?>"
                    name="<?= h($deleteConfirmField) ?>"
                    value="1"
                    required
                    aria-required="true"<?= adminFieldAttributes($deleteConfirmField, $deleteErrorFields, [], [$deleteReviewId], $deleteFieldErrorId) ?>>
                  Potvrzuji smazání této série článků.
                </label>
                <?php adminRenderFieldError($deleteConfirmField, $deleteErrorFields, [], 'Před smazáním série potvrďte, že jste zkontrolovali dopad na zařazené články.', $deleteFieldErrorId); ?>
                <button type="submit" class="btn btn-danger" data-confirm="Smazat sérii článků? Články zůstanou zachované bez této série.">Smazat</button>
              </fieldset>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
