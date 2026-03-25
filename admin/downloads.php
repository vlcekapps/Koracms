<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu souborů ke stažení nemáte potřebné oprávnění.');

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
    $whereParts[] = '(d.title LIKE ? OR d.excerpt LIKE ? OR d.description LIKE ? OR c.name LIKE ?
        OR d.version_label LIKE ? OR d.platform_label LIKE ? OR d.license_label LIKE ?)';
    for ($i = 0; $i < 7; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($statusFilter === 'pending') {
    $whereParts[] = "COALESCE(d.status,'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $whereParts[] = "COALESCE(d.status,'published') = 'published' AND d.is_published = 1";
} elseif ($statusFilter === 'hidden') {
    $whereParts[] = "COALESCE(d.status,'published') = 'published' AND d.is_published = 0";
}

$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$stmt = $pdo->prepare(
    "SELECT d.id, d.title, d.slug, d.download_type, d.dl_category_id, COALESCE(c.name, '') AS category_name,
            d.excerpt, d.description, d.image_file, d.version_label, d.platform_label, d.license_label,
            d.external_url, d.filename, d.original_name, d.file_size, d.sort_order, d.is_published,
            d.created_at, COALESCE(d.status,'published') AS status,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
     FROM cms_downloads d
     LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
     LEFT JOIN cms_users u ON u.id = d.author_id
     {$whereSql}
     ORDER BY c.name, d.sort_order, d.title"
);
$stmt->execute($params);
$items = array_map(
    static fn(array $download): array => hydrateDownloadPresentation($download),
    $stmt->fetchAll()
);

adminHeader('Ke stažení');
?>
<p><a href="download_form.php" class="btn">+ Přidat položku</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q" class="visually-hidden">Hledat v souborech ke stažení</label>
    <input type="search" id="q" name="q" placeholder="Hledat v souborech ke stažení…"
           value="<?= h($q) ?>" style="width:320px">
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
  <button type="submit" class="btn">Filtrovat</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="downloads.php" class="btn">Zrušit</a>
  <?php endif; ?>
</form>

<?php if (empty($items)): ?>
  <p>Žádné položky<?= $q !== '' || $statusFilter !== 'all' ? ' pro zadaný filtr.' : '.' ?></p>
<?php else: ?>
  <table>
    <caption>Položky ke stažení</caption>
    <thead>
      <tr>
        <th scope="col">Položka</th>
        <th scope="col">Typ a metadata</th>
        <th scope="col">Zdroj</th>
        <th scope="col">Autor</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $download): ?>
      <tr>
        <td>
          <strong><?= h((string)$download['title']) ?></strong><br>
          <small style="color:#555">/downloads/<?= h((string)$download['slug']) ?></small>
          <?php if ($download['category_name'] !== ''): ?>
            <br><small style="color:#555"><?= h((string)$download['category_name']) ?></small>
          <?php endif; ?>
          <?php if (!empty($download['image_file'])): ?>
            <br><small style="color:#555">Náhledový obrázek připojen</small>
          <?php endif; ?>
        </td>
        <td>
          <strong><?= h((string)$download['download_type_label']) ?></strong>
          <?php if ($download['version_label'] !== ''): ?>
            <br><small style="color:#555">Verze <?= h((string)$download['version_label']) ?></small>
          <?php endif; ?>
          <?php if ($download['platform_label'] !== ''): ?>
            <br><small style="color:#555"><?= h((string)$download['platform_label']) ?></small>
          <?php endif; ?>
          <?php if ($download['license_label'] !== ''): ?>
            <br><small style="color:#555">Licence: <?= h((string)$download['license_label']) ?></small>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($download['has_file']): ?>
            <a href="<?= moduleFileUrl('downloads', (int)$download['id']) ?>"
               target="_blank" rel="noopener" download="<?= h((string)$download['original_name']) ?>">
              <?= h((string)$download['original_name']) ?>
            </a>
            <?php if ((int)$download['file_size'] > 0): ?>
              <small>(<?= h(formatFileSize((int)$download['file_size'])) ?>)</small>
            <?php endif; ?>
          <?php else: ?>
            <small style="color:#555">Bez přímého souboru</small>
          <?php endif; ?>
          <?php if ($download['has_external_url']): ?>
            <br><a href="<?= h((string)$download['external_url']) ?>" target="_blank" rel="noopener noreferrer">Externí odkaz</a>
          <?php endif; ?>
        </td>
        <td><?= $download['author_name'] ? h((string)$download['author_name']) : '<em>–</em>' ?></td>
        <td>
          <?php if ($download['status'] === 'pending'): ?>
            <strong class="status-badge status-badge--pending">Čeká na schválení</strong>
          <?php elseif ((int)$download['is_published'] === 1): ?>
            Publikováno
          <?php else: ?>
            <strong>Skryto</strong>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="download_form.php?id=<?= (int)$download['id'] ?>" class="btn">Upravit</a>
          <?php if ($download['status'] === 'published' && (int)$download['is_published'] === 1): ?>
            <a href="<?= h(downloadPublicPath($download)) ?>" target="_blank" rel="noopener noreferrer">Veřejná stránka</a>
          <?php endif; ?>
          <?php if ($download['status'] === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="downloads">
              <input type="hidden" name="id" value="<?= (int)$download['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/downloads.php">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="download_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$download['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat položku ke stažení?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>



<?php adminFooter(); ?>
