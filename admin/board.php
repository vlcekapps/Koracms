<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu úřední desky nemáte potřebné oprávnění.');

$pdo = db_connect();
$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$allowedStatusFilters = ['all', 'pending', 'published', 'hidden'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$whereParts = [];
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
    $whereParts[] = "COALESCE(b.status,'published') = 'published' AND b.is_published = 1";
} elseif ($statusFilter === 'hidden') {
    $whereParts[] = "COALESCE(b.status,'published') = 'published' AND b.is_published = 0";
}

$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$stmt = $pdo->prepare(
    "SELECT b.id, b.title, b.slug, b.board_type, b.category_id, c.name AS category_name,
            b.posted_date, b.removal_date, b.original_name, b.file_size, b.is_pinned, b.image_file,
            b.is_published, b.created_at, COALESCE(b.status,'published') AS status,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
     FROM cms_board b
     LEFT JOIN cms_board_categories c ON c.id = b.category_id
     LEFT JOIN cms_users u ON u.id = b.author_id
     {$whereSql}
     ORDER BY b.is_pinned DESC, b.posted_date DESC, b.created_at DESC, b.title"
);
$stmt->execute($params);
$items = $stmt->fetchAll();

$publicLabel = boardModulePublicLabel();

adminHeader('Úřední deska');
?>
<p style="margin-top:0;color:#555">
  Návštěvníci tuto sekci na webu vidí jako <strong><?= h($publicLabel) ?></strong>.
</p>
<p>
  <a href="board_form.php" class="btn">+ Přidat položku</a>
  <a href="board_cats.php" style="margin-left:1rem">Kategorie vývěsky</a>
</p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q" class="visually-hidden">Hledat v modulu Úřední deska</label>
    <input type="search" id="q" name="q" placeholder="Hledat v položkách..." value="<?= h($q) ?>" style="width:320px">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Publikované</option>
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
  <table>
    <caption>Přehled položek sekce <?= h($publicLabel) ?></caption>
    <thead>
      <tr>
        <th scope="col">Nadpis</th>
        <th scope="col">Typ</th>
        <th scope="col">Kategorie</th>
        <th scope="col">Vyvěšeno</th>
        <th scope="col">Příloha</th>
        <th scope="col">Autor</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $document): ?>
      <tr>
        <td>
          <strong><?= h((string)$document['title']) ?></strong>
          <?php if ((int)($document['is_pinned'] ?? 0) === 1): ?>
            <span style="display:inline-block;margin-left:.4rem;padding:.1rem .45rem;border-radius:999px;background:#fff1c2;color:#7a4a00;font-size:.78rem;font-weight:600">Připnuto</span>
          <?php endif; ?>
          <?php if ((string)($document['image_file'] ?? '') !== ''): ?>
            <span style="display:inline-block;margin-left:.4rem;padding:.1rem .45rem;border-radius:999px;background:#eef4fb;color:#1b4d7a;font-size:.78rem;font-weight:600">Obrázek</span>
          <?php endif; ?>
          <br>
          <small style="color:#555">/board/<?= h((string)($document['slug'] ?? '')) ?></small>
        </td>
        <td><?= h(boardTypeLabel((string)($document['board_type'] ?? 'document'))) ?></td>
        <td><?= $document['category_name'] ? h((string)$document['category_name']) : '<em>-</em>' ?></td>
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
          <?php elseif (!empty($document['removal_date']) && (string)$document['removal_date'] < date('Y-m-d')): ?>
            <em>Archivováno</em>
          <?php else: ?>
            Publikováno
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="board_form.php?id=<?= (int)$document['id'] ?>" class="btn">Upravit</a>
          <?php if ($document['status'] === 'published' && (int)$document['is_published'] === 1): ?>
            <a href="<?= h(boardPublicPath($document)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
          <?php endif; ?>
          <?php if ($document['status'] === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="board">
              <input type="hidden" name="id" value="<?= (int)$document['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/board.php">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="board_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$document['id'] ?>">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Smazat položku?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>



<?php adminFooter(); ?>
