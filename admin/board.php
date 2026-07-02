<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu úřední desky nemáte potřebné oprávnění.');
requireModuleEnabled('board');

$pdo = db_connect();
$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$allowedStatusFilters = ['all', 'pending', 'published', 'scheduled', 'hidden'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$whereParts = ['b.deleted_at IS NULL'];
$params = [];

if ($q !== '') {
    $whereParts[] = '(b.title LIKE ? OR b.excerpt LIKE ? OR b.description LIKE ? OR b.original_name LIKE ?
        OR b.contact_name LIKE ? OR b.contact_phone LIKE ? OR b.contact_email LIKE ? OR COALESCE(c.name, \'\') LIKE ?)';
    for ($i = 0; $i < 8; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($statusFilter === 'pending') {
    $whereParts[] = "COALESCE(b.status,'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $whereParts[] = "COALESCE(b.status,'published') = 'published' AND b.is_published = 1 AND b.posted_date <= CURDATE()";
} elseif ($statusFilter === 'scheduled') {
    $whereParts[] = "COALESCE(b.status,'published') = 'published' AND b.is_published = 1 AND b.posted_date > CURDATE()";
} elseif ($statusFilter === 'hidden') {
    $whereParts[] = "COALESCE(b.status,'published') = 'published' AND b.is_published = 0";
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);

$stmt = $pdo->prepare(
    "SELECT b.id, b.title, b.slug, b.board_type, b.category_id, c.name AS category_name,
            b.posted_date, b.removal_date, b.original_name, b.file_size, b.is_pinned, b.image_file,
            b.is_published, b.created_at, COALESCE(b.status,'published') AS status,
            COALESCE(ev.event_count, 0) AS publication_event_count,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
     FROM cms_board b
     LEFT JOIN cms_board_categories c ON c.id = b.category_id
     LEFT JOIN cms_users u ON u.id = b.author_id
     LEFT JOIN (
         SELECT board_id, COUNT(*) AS event_count
         FROM cms_board_publication_events
         GROUP BY board_id
     ) ev ON ev.board_id = b.id
     {$whereSql}
     ORDER BY b.is_pinned DESC, b.posted_date DESC, b.created_at DESC, b.title"
);
$stmt->execute($params);
$items = $stmt->fetchAll();

$subscriberStats = [
    'confirmed' => 0,
    'pending' => 0,
    'category_specific' => 0,
];
try {
    $stats = $pdo->query(
        "SELECT
            SUM(CASE WHEN confirmed = 1 THEN 1 ELSE 0 END) AS confirmed_count,
            SUM(CASE WHEN confirmed = 0 THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN all_categories = 0 THEN 1 ELSE 0 END) AS category_specific_count
         FROM cms_board_subscribers"
    )->fetch() ?: [];
    $subscriberStats = [
        'confirmed' => (int)($stats['confirmed_count'] ?? 0),
        'pending' => (int)($stats['pending_count'] ?? 0),
        'category_specific' => (int)($stats['category_specific_count'] ?? 0),
    ];
} catch (\PDOException $e) {
    koraLog('warning', 'board subscriber summary failed', ['exception' => $e]);
}

$publicLabel = boardModulePublicLabel();

adminHeader('Úřední deska');
?>
<p class="field-help field-help--flush">
  Návštěvníci tuto sekci na webu vidí jako <strong><?= h($publicLabel) ?></strong>.
</p>
<section class="admin-panel admin-panel--compact" aria-labelledby="board-subscribers-heading">
  <h2 id="board-subscribers-heading">Odběr vývěsky</h2>
  <p class="field-help field-help--flush">
    Potvrzení odběratelé: <strong><?= (int)$subscriberStats['confirmed'] ?></strong>,
    čekající na potvrzení: <strong><?= (int)$subscriberStats['pending'] ?></strong>,
    odběr jen vybraných kategorií: <strong><?= (int)$subscriberStats['category_specific'] ?></strong>.
  </p>
</section>
<p class="button-row button-row--start">
  <a href="board_form.php" class="btn">+ Přidat položku</a>
  <a href="board_cats.php">Kategorie vývěsky</a>
</p>

<form method="get" class="button-row button-row--baseline admin-stack-sm">
  <div>
    <label for="q" class="visually-hidden">Hledat v modulu Úřední deska</label>
    <input type="search" id="q" name="q" placeholder="Hledat v položkách..." value="<?= h($q) ?>" class="admin-search-input">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Publikované</option>
      <option value="scheduled"<?= $statusFilter === 'scheduled' ? ' selected' : '' ?>>Naplánované</option>
      <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Čekající</option>
      <option value="hidden"<?= $statusFilter === 'hidden' ? ' selected' : '' ?>>Skryté</option>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="board.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($items)): ?>
  <p><?= $q !== '' || $statusFilter !== 'all' ? 'Pro zvolený filtr tu teď nejsou žádné položky.' : 'Zatím tu nejsou žádné položky této sekce.' ?></p>
<?php else: ?>
  <?= bulkActions('board', BASE_URL . '/admin/board.php', 'Hromadné akce s vývěskou', 'dokument') ?>
  <table>
    <caption>Přehled položek sekce <?= h($publicLabel) ?></caption>
    <thead>
      <tr>
        <th scope="col"><label for="check-all" class="sr-only">Vybrat vše</label><input type="checkbox" id="check-all"></th>
        <th scope="col">Nadpis</th>
        <th scope="col">Typ</th>
        <th scope="col">Kategorie</th>
        <th scope="col">Vyvěšeno</th>
        <th scope="col">Příloha</th>
        <th scope="col">Autor</th>
        <th scope="col">Stav</th>
        <th scope="col">Evidence</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $document): ?>
      <tr>
        <td><label for="board-document-select-<?= (int)$document['id'] ?>" class="sr-only">Vybrat <?= h((string)$document['title']) ?></label><input type="checkbox" id="board-document-select-<?= (int)$document['id'] ?>" name="ids[]" value="<?= (int)$document['id'] ?>" form="bulk-form"></td>
        <td>
          <strong><?= h((string)$document['title']) ?></strong>
          <?php if ((int)($document['is_pinned'] ?? 0) === 1): ?>
            <span class="inline-badge inline-badge--warning">Připnuto</span>
          <?php endif; ?>
          <?php if ((string)($document['image_file'] ?? '') !== ''): ?>
            <span class="inline-badge inline-badge--info">Obrázek</span>
          <?php endif; ?>
          <br>
          <small class="table-meta">/board/<?= h((string)($document['slug'] ?? '')) ?></small>
        </td>
        <td><?= h(boardTypeLabel((string)($document['board_type'] ?? 'document'))) ?></td>
        <td>
          <?php if ($document['category_name']): ?>
            <?= h((string)$document['category_name']) ?>
          <?php else: ?>
            <em>-</em>
          <?php endif; ?>
        </td>
        <td>
          <time datetime="<?= h((string)$document['posted_date']) ?>"><?= h(formatCzechDate((string)$document['posted_date'])) ?></time>
          <?php if (!empty($document['removal_date'])): ?>
            <br><small>do <?= h(formatCzechDate((string)$document['removal_date'])) ?></small>
          <?php endif; ?>
        </td>
        <td>
          <?php if ((string)$document['original_name'] !== ''): ?>
            <?= h((string)$document['original_name']) ?>
            <?php if ((int)$document['file_size'] > 0): ?>
              <small>(<?= h(formatFileSize((int)$document['file_size'])) ?>)</small>
            <?php endif; ?>
          <?php else: ?>
            <em>bez přílohy</em>
          <?php endif; ?>
        </td>
        <td><?= $document['author_name'] ? h((string)$document['author_name']) : '<em>-</em>' ?></td>
        <td>
          <?php if ($document['status'] === 'pending'): ?>
            <strong class="status-badge status-badge--pending"><span aria-hidden="true">⟳</span> Čeká na schválení</strong>
          <?php elseif (!(int)$document['is_published']): ?>
            <strong>Skryto</strong>
          <?php elseif ((string)$document['posted_date'] > date('Y-m-d')): ?>
            <strong>Naplánováno</strong>
          <?php elseif (!empty($document['removal_date']) && (string)$document['removal_date'] < date('Y-m-d')): ?>
            <em>Archivováno</em>
          <?php else: ?>
            Publikováno
          <?php endif; ?>
        </td>
        <td>
          <?php if ((int)($document['publication_event_count'] ?? 0) > 0): ?>
            <?= (int)$document['publication_event_count'] ?> záznamů
          <?php else: ?>
            <em>bez záznamu</em>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="board_form.php?id=<?= (int)$document['id'] ?>" class="btn">Upravit</a>
          <?php if ($document['status'] === 'published' && (int)$document['is_published'] === 1 && (string)$document['posted_date'] <= date('Y-m-d')): ?>
            <a href="<?= h(boardPublicPath($document)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
          <?php endif; ?>
          <?php if ($document['status'] === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
            <form action="approve.php" method="post">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="board">
              <input type="hidden" name="id" value="<?= (int)$document['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/board.php">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="board_clone.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$document['id'] ?>">
            <button type="submit" class="btn" data-confirm="Vytvořit kopii položky?">Duplikovat</button>
          </form>
          <form action="board_delete.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$document['id'] ?>">
            <button type="submit" class="btn btn-danger" data-confirm="Smazat položku?">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= bulkCheckboxJs() ?>
<?php endif; ?>


<?php adminFooter(); ?>
