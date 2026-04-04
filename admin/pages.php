<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu statických stránek nemáte potřebné oprávnění.');

$pdo = db_connect();
$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = in_array($_GET['status'] ?? '', ['all', 'pending', 'published', 'hidden'], true)
    ? (string)$_GET['status']
    : 'all';

$where = ['p.deleted_at IS NULL'];
$params = [];

if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.slug LIKE ? OR p.content LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($statusFilter === 'pending') {
    $where[] = "COALESCE(p.status,'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $where[] = "COALESCE(p.status,'published') = 'published' AND p.is_published = 1";
} elseif ($statusFilter === 'hidden') {
    $where[] = "COALESCE(p.status,'published') = 'published' AND p.is_published = 0";
}

$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT p.id, p.title, p.slug, p.blog_id, p.blog_nav_order, p.is_published, p.show_in_nav, p.nav_order,
            COALESCE(p.status,'published') AS status, p.created_at,
            b.name AS blog_name, b.slug AS blog_slug
     FROM cms_pages p
     LEFT JOIN cms_blogs b ON b.id = p.blog_id
     {$whereSql}
     ORDER BY p.title, p.id"
);
$stmt->execute($params);
$pages = $stmt->fetchAll();

$navigationKeys = [];
$navigationLookup = [];
foreach (array_keys(navModuleDefaults()) as $moduleKey) {
    if ($moduleKey === 'blog') {
        foreach (getAllBlogs() as $blogEntry) {
            $key = 'blog:' . (int)($blogEntry['id'] ?? 0);
            $navigationKeys[] = $key;
            $navigationLookup[$key] = true;
        }
        continue;
    }

    $key = 'module:' . $moduleKey;
    $navigationKeys[] = $key;
    $navigationLookup[$key] = true;
}

$pageIds = [];
foreach ($pages as $pageRow) {
    if (!empty($pageRow['blog_id'])) {
        continue;
    }
    $pageId = (int)$pageRow['id'];
    $pageIds[$pageId] = true;
    $key = 'page:' . $pageId;
    $navigationKeys[] = $key;
    $navigationLookup[$key] = true;
}

$savedNavigationOrder = array_values(array_filter(array_map(
    'trim',
    explode(',', getSetting('nav_order_unified', ''))
), static fn(string $value): bool => $value !== ''));

$normalizedNavigationKeys = [];
$seenNavigationKeys = [];
foreach ($savedNavigationOrder as $key) {
    if (!isset($navigationLookup[$key]) || isset($seenNavigationKeys[$key])) {
        continue;
    }
    $normalizedNavigationKeys[] = $key;
    $seenNavigationKeys[$key] = true;
}
foreach ($navigationKeys as $key) {
    if (isset($seenNavigationKeys[$key])) {
        continue;
    }
    $normalizedNavigationKeys[] = $key;
    $seenNavigationKeys[$key] = true;
}

$pageNavigationPositions = [];
$navigationPosition = 1;
foreach ($normalizedNavigationKeys as $key) {
    if (str_starts_with($key, 'page:')) {
        $pageId = (int)substr($key, 5);
        if (isset($pageIds[$pageId])) {
            $pageNavigationPositions[$pageId] = $navigationPosition;
        }
    }
    $navigationPosition++;
}

usort($pages, static function (array $left, array $right) use ($pageNavigationPositions): int {
    $leftBlogId = !empty($left['blog_id']) ? (int)$left['blog_id'] : null;
    $rightBlogId = !empty($right['blog_id']) ? (int)$right['blog_id'] : null;

    if ($leftBlogId === null && $rightBlogId !== null) {
        return -1;
    }
    if ($leftBlogId !== null && $rightBlogId === null) {
        return 1;
    }

    if ($leftBlogId === null && $rightBlogId === null) {
        $leftPosition = $pageNavigationPositions[(int)$left['id']] ?? PHP_INT_MAX;
        $rightPosition = $pageNavigationPositions[(int)$right['id']] ?? PHP_INT_MAX;
        if ($leftPosition !== $rightPosition) {
            return $leftPosition <=> $rightPosition;
        }
    } else {
        $blogNameComparison = strcasecmp((string)($left['blog_name'] ?? ''), (string)($right['blog_name'] ?? ''));
        if ($blogNameComparison !== 0) {
            return $blogNameComparison;
        }
        $leftBlogOrder = (int)($left['blog_nav_order'] ?? 0);
        $rightBlogOrder = (int)($right['blog_nav_order'] ?? 0);
        if ($leftBlogOrder !== $rightBlogOrder) {
            return $leftBlogOrder <=> $rightBlogOrder;
        }
    }

    $titleComparison = strcasecmp((string)$left['title'], (string)$right['title']);
    if ($titleComparison !== 0) {
        return $titleComparison;
    }

    return ((int)$left['id']) <=> ((int)$right['id']);
});

$currentRedirect = BASE_URL . '/admin/pages.php';
$queryArgs = array_filter([
    'q' => $q,
    'status' => $statusFilter !== 'all' ? $statusFilter : null,
], static fn($value): bool => $value !== null && $value !== '');
if ($queryArgs !== []) {
    $currentRedirect .= '?' . http_build_query($queryArgs);
}

adminHeader('Statické stránky');
?>

<p class="button-row button-row--start">
  <a href="<?= BASE_URL ?>/admin/page_form.php" class="btn">+ Nová stránka</a>
  <a href="<?= BASE_URL ?>/admin/menu.php" class="btn">Navigace webu</a>
</p>

<p class="field-help" style="margin-top:0">Globální statické stránky se řadí v <a href="<?= BASE_URL ?>/admin/menu.php">Navigaci webu</a>. Blogové stránky mají vlastní pořadí jen uvnitř konkrétního blogu.</p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q">Hledat</label>
    <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Název, slug nebo obsah stránky">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status" style="width:auto">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Publikováno</option>
      <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Čekající</option>
      <option value="hidden"<?= $statusFilter === 'hidden' ? ' selected' : '' ?>>Skryté</option>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="<?= BASE_URL ?>/admin/pages.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if ($pages === []): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all'): ?>
      Pro zvolený filtr tu teď nejsou žádné statické stránky.
    <?php else: ?>
      Zatím tu nejsou žádné statické stránky. <a href="<?= BASE_URL ?>/admin/page_form.php">Přidat první stránku</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <?= bulkActions('pages', BASE_URL . '/admin/pages.php', 'Hromadné akce se stránkami', 'stránka') ?>
  <table>
    <caption>Přehled statických stránek</caption>
    <thead>
      <tr>
        <th scope="col"><input type="checkbox" id="check-all" aria-label="Vybrat vše"></th>
        <th scope="col">Název</th>
        <th scope="col">Umístění</th>
        <th scope="col">Stav</th>
        <th scope="col">V navigaci</th>
        <th scope="col">Pořadí</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pages as $page): ?>
        <?php
        $pageId = (int)$page['id'];
        $isBlogPageRow = !empty($page['blog_id']);
        $publicPath = pagePublicPath($page);
        ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= $pageId ?>" form="bulk-form" aria-label="Vybrat <?= h((string)$page['title']) ?>"></td>
          <td>
            <strong><?= h((string)$page['title']) ?></strong>
            <br><small><?= h((string)$page['slug']) ?></small>
            <?php if (!empty($page['created_at'])): ?>
              <br><small style="color:#555">Vytvořeno <?= h((string)$page['created_at']) ?></small>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isBlogPageRow): ?>
              <strong>Blogová stránka</strong>
              <br><small><a href="<?= BASE_URL ?>/admin/blog_pages.php?blog_id=<?= (int)$page['blog_id'] ?>"><?= h((string)$page['blog_name']) ?></a></small>
            <?php else: ?>
              <strong>Globální stránka</strong>
              <br><small>Spravuje se v hlavní navigaci webu</small>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($page['status'] === 'pending'): ?>
              <strong class="status-badge status-badge--pending">Čeká na schválení</strong>
            <?php elseif ((int)$page['is_published'] === 1): ?>
              Publikováno
            <?php else: ?>
              <strong>Skryto</strong>
            <?php endif; ?>
          </td>
          <td><?= !$isBlogPageRow && (int)$page['show_in_nav'] === 1 ? 'Ano' : '–' ?></td>
          <td>
            <?php if ($isBlogPageRow): ?>
              <?= (int)($page['blog_nav_order'] ?? 0) ?>
            <?php else: ?>
              <?= (int)$page['show_in_nav'] === 1 ? (int)($pageNavigationPositions[$pageId] ?? 0) : '–' ?>
            <?php endif; ?>
          </td>
          <td class="actions">
            <a href="<?= BASE_URL ?>/admin/page_form.php?id=<?= $pageId ?>&amp;redirect=<?= rawurlencode($currentRedirect) ?>" class="btn">Upravit</a>
            <?php if ($isBlogPageRow): ?>
              <a href="<?= BASE_URL ?>/admin/blog_pages.php?blog_id=<?= (int)$page['blog_id'] ?>" class="btn">Pořadí blogu</a>
            <?php endif; ?>
            <?php if ($page['status'] === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
              <form action="<?= BASE_URL ?>/admin/approve.php" method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="module" value="pages">
                <input type="hidden" name="id" value="<?= $pageId ?>">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn btn-success">Schválit</button>
              </form>
            <?php endif; ?>
            <?php if ((int)$page['is_published'] === 1 && (string)$page['status'] === 'published'): ?>
              <a href="<?= h($publicPath) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
            <?php endif; ?>
            <form method="post" action="<?= BASE_URL ?>/admin/convert_content.php" style="display:inline"
                  onsubmit="return confirm('Převést stránku na článek blogu?')">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="direction" value="page_to_article">
              <input type="hidden" name="id" value="<?= $pageId ?>">
              <button type="submit" class="btn"><span aria-hidden="true">→</span> Článek</button>
            </form>
            <form action="<?= BASE_URL ?>/admin/page_clone.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= $pageId ?>">
              <button type="submit" class="btn" data-confirm="Vytvořit kopii stránky?">Duplikovat</button>
            </form>
            <form method="post" action="<?= BASE_URL ?>/admin/page_delete.php" style="display:inline"
                  onsubmit="return confirm('Smazat tuto stránku?')">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= $pageId ?>">
              <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
              <button type="submit" class="btn btn-danger">Smazat</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?= bulkCheckboxJs() ?>
<?php endif; ?>

<?php adminFooter(); ?>
