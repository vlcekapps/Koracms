<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$statusDefinitions = commentStatusDefinitions();
$validFilters = array_merge(array_keys($statusDefinitions), ['all']);

$filter = trim($_GET['filter'] ?? 'pending');
if (!in_array($filter, $validFilters, true)) {
    $filter = 'pending';
}
$q = trim($_GET['q'] ?? '');

$statusCounts = array_fill_keys(array_keys($statusDefinitions), 0);
try {
    $statusRows = $pdo->query("SELECT status, COUNT(*) AS cnt FROM cms_comments GROUP BY status")->fetchAll();
    foreach ($statusRows as $statusRow) {
        $statusKey = normalizeCommentStatus((string)$statusRow['status']);
        $statusCounts[$statusKey] = (int)$statusRow['cnt'];
    }
} catch (\PDOException $e) {
    $statusCounts['approved'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM cms_comments WHERE is_approved = 1"
    )->fetchColumn();
    $statusCounts['pending'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM cms_comments WHERE is_approved = 0"
    )->fetchColumn();
}

$where = 'WHERE 1';
$params = [];
if ($filter !== 'all') {
    $where .= ' AND c.status = ?';
    $params[] = $filter;
}

if ($q !== '') {
    $where .= " AND (c.author_name LIKE ? OR c.author_email LIKE ? OR c.content LIKE ? OR COALESCE(a.title, '') LIKE ?)";
    $searchNeedle = '%' . $q . '%';
    $params[] = $searchNeedle;
    $params[] = $searchNeedle;
    $params[] = $searchNeedle;
    $params[] = $searchNeedle;
}

$comments = [];
try {
    $stmt = $pdo->prepare(
        "SELECT c.id, c.author_name, c.author_email, c.content, c.status, c.created_at,
                a.title AS article_title, a.id AS article_id
         FROM cms_comments c
         LEFT JOIN cms_articles a ON a.id = c.article_id
         {$where}
         ORDER BY c.created_at DESC"
    );
    $stmt->execute($params);
    $comments = $stmt->fetchAll();
} catch (\PDOException $e) {
    $legacyParams = $params;
    if ($filter === 'spam' || $filter === 'trash') {
        $legacyWhere = str_replace('c.status = ?', '0 = 1', $where);
        array_shift($legacyParams);
    } else {
        $legacyWhere = str_replace('c.status = ?', 'c.is_approved = ?', $where);
    }
    if ($filter !== 'all' && $filter !== 'spam' && $filter !== 'trash') {
        $legacyParams[0] = $filter === 'approved' ? 1 : 0;
    }
    $stmt = $pdo->prepare(
        "SELECT c.id, c.author_name, c.author_email, c.content,
                CASE WHEN c.is_approved = 1 THEN 'approved' ELSE 'pending' END AS status,
                c.created_at, a.title AS article_title, a.id AS article_id
         FROM cms_comments c
         LEFT JOIN cms_articles a ON a.id = c.article_id
         {$legacyWhere}
         ORDER BY c.created_at DESC"
    );
    $stmt->execute($legacyParams);
    $comments = $stmt->fetchAll();
}

$bulkOptions = [
    'approve' => 'Schválit vybrané',
    'pending' => 'Vrátit do čekajících',
    'spam' => 'Označit jako spam',
    'trash' => 'Přesunout do koše',
    'delete' => 'Smazat trvale',
];

adminHeader('Komentáře');
?>

<nav aria-label="Filtr komentářů" class="button-row" style="margin-bottom:1rem">
  <a href="?filter=pending" <?= $filter === 'pending' ? 'aria-current="page"' : '' ?>>
    Čekající (<?= $statusCounts['pending'] ?>)
  </a>
  <a href="?filter=approved" <?= $filter === 'approved' ? 'aria-current="page"' : '' ?>>
    Schválené (<?= $statusCounts['approved'] ?>)
  </a>
  <a href="?filter=spam" <?= $filter === 'spam' ? 'aria-current="page"' : '' ?>>
    Spam (<?= $statusCounts['spam'] ?>)
  </a>
  <a href="?filter=trash" <?= $filter === 'trash' ? 'aria-current="page"' : '' ?>>
    Koš (<?= $statusCounts['trash'] ?>)
  </a>
  <a href="?filter=all" <?= $filter === 'all' ? 'aria-current="page"' : '' ?>>
    Všechny (<?= array_sum($statusCounts) ?>)
  </a>
</nav>

<form method="get" class="button-row" style="margin-bottom:1rem">
  <input type="hidden" name="filter" value="<?= h($filter) ?>">
  <label for="q" class="sr-only">Hledat v komentářích</label>
  <input type="search" id="q" name="q" placeholder="Hledat v komentářích…"
         value="<?= h($q) ?>" style="width:min(100%, 24rem)">
  <button type="submit" class="btn">Hledat</button>
  <?php if ($q !== ''): ?>
    <a href="?filter=<?= h($filter) ?>" class="btn">Zrušit</a>
  <?php endif; ?>
</form>

<?php if (empty($comments)): ?>
  <p>V této kategorii teď nejsou žádné komentáře.</p>
<?php else: ?>
  <form method="post" action="<?= BASE_URL ?>/admin/comment_bulk.php" id="bulk-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="filter" value="<?= h($filter) ?>">
  </form>

  <div class="button-row" style="margin-bottom:.75rem">
    <?php foreach ($bulkOptions as $bulkAction => $bulkLabel): ?>
      <?php if (($bulkAction === 'approve' && $filter === 'approved')
          || ($bulkAction === 'pending' && $filter === 'pending')
          || ($bulkAction === 'spam' && $filter === 'spam')
          || ($bulkAction === 'trash' && $filter === 'trash')): ?>
        <?php continue; ?>
      <?php endif; ?>
      <button type="submit" form="bulk-form" name="action" value="<?= h($bulkAction) ?>"
              class="btn<?= $bulkAction === 'delete' ? ' btn-danger' : '' ?>"
              <?php if ($bulkAction === 'delete'): ?>onclick="return confirm('Smazat vybrané komentáře trvale?')"<?php endif; ?>>
        <?= h($bulkLabel) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <table>
    <caption>Komentáře</caption>
    <thead>
      <tr>
        <th scope="col"><input type="checkbox" id="check-all" aria-label="Vybrat všechny komentáře" form="bulk-form"></th>
        <th scope="col">Autor</th>
        <th scope="col">Článek</th>
        <th scope="col">Komentář</th>
        <th scope="col">Datum</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($comments as $comment): ?>
        <?php
        $commentStatus = normalizeCommentStatus((string)$comment['status']);
        $articleTitle = $comment['article_title'] ?: 'Bez článku';
        ?>
        <tr>
          <td>
            <input type="checkbox" name="ids[]" value="<?= (int)$comment['id'] ?>"
                   aria-label="Vybrat komentář od <?= h($comment['author_name']) ?>" form="bulk-form">
          </td>
          <td>
            <strong><?= h($comment['author_name']) ?></strong>
            <?php if ($comment['author_email'] !== ''): ?>
              <br><a href="mailto:<?= h($comment['author_email']) ?>"><?= h($comment['author_email']) ?></a>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($comment['article_id'])): ?>
              <a href="<?= BASE_URL ?>/blog/article.php?id=<?= (int)$comment['article_id'] ?>">
                <?= h($articleTitle) ?>
              </a>
            <?php else: ?>
              <?= h($articleTitle) ?>
            <?php endif; ?>
          </td>
          <td>
            <div style="max-width:36rem;white-space:pre-wrap"><?= h($comment['content']) ?></div>
          </td>
          <td>
            <time datetime="<?= h(str_replace(' ', 'T', (string)$comment['created_at'])) ?>">
              <?= formatCzechDate((string)$comment['created_at']) ?>
            </time>
          </td>
          <td><?= h(commentStatusLabel($commentStatus)) ?></td>
          <td class="actions">
            <?php
            $rowActions = [];
            if ($commentStatus !== 'approved') {
                $rowActions['approve'] = 'Schválit';
            }
            if ($commentStatus !== 'pending') {
                $rowActions['pending'] = 'Do čekajících';
            }
            if ($commentStatus !== 'spam') {
                $rowActions['spam'] = 'Spam';
            }
            if ($commentStatus !== 'trash') {
                $rowActions['trash'] = 'Koš';
            }
            foreach ($rowActions as $actionKey => $actionLabel):
            ?>
              <form method="post" action="<?= BASE_URL ?>/admin/comment_action.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$comment['id'] ?>">
                <input type="hidden" name="filter" value="<?= h($filter) ?>">
                <input type="hidden" name="action" value="<?= h($actionKey) ?>">
                <button type="submit" class="btn"><?= h($actionLabel) ?></button>
              </form>
            <?php endforeach; ?>
            <form method="post" action="<?= BASE_URL ?>/admin/comment_action.php" style="display:inline"
                  onsubmit="return confirm('Smazat tento komentář trvale?')">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$comment['id'] ?>">
              <input type="hidden" name="filter" value="<?= h($filter) ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="btn btn-danger">Smazat trvale</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <script>
  document.getElementById('check-all')?.addEventListener('change', function () {
      document.querySelectorAll('input[form="bulk-form"][name="ids[]"]').forEach((checkbox) => {
          checkbox.checked = this.checked;
      });
  });
  </script>
<?php endif; ?>

<?php adminFooter(); ?>
