<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu souborů ke stažení nemáte potřebné oprávnění.');

$pdo = db_connect();
$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$categoryFilter = inputInt('get', 'kat');
$rawTypeFilter = trim((string)($_GET['typ'] ?? 'all'));
$typeFilter = $rawTypeFilter;
$sourceFilter = trim((string)($_GET['source'] ?? 'all'));
$platformFilter = trim((string)($_GET['platform'] ?? ''));
$featuredFilter = trim((string)($_GET['featured'] ?? 'all'));

$allowedStatusFilters = ['all', 'pending', 'published', 'hidden'];
$allowedSourceFilters = ['all', 'local', 'external', 'hybrid'];
$allowedFeaturedFilters = ['all', 'featured', 'regular'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}
if (!in_array($sourceFilter, $allowedSourceFilters, true)) {
    $sourceFilter = 'all';
}
if (!in_array($featuredFilter, $allowedFeaturedFilters, true)) {
    $featuredFilter = 'all';
}
if ($typeFilter !== 'all' && !isset(downloadTypeDefinitions()[$typeFilter])) {
    $typeFilter = 'all';
}

$categories = $pdo->query("SELECT id, name FROM cms_dl_categories ORDER BY name")->fetchAll();
$validCategoryIds = array_map(static fn(array $category): int => (int)$category['id'], $categories);
if ($categoryFilter !== null && !in_array($categoryFilter, $validCategoryIds, true)) {
    $categoryFilter = null;
}

$platformOptions = $pdo->query(
    "SELECT DISTINCT platform_label
     FROM cms_downloads
     WHERE TRIM(COALESCE(platform_label, '')) <> ''
     ORDER BY platform_label"
)->fetchAll(PDO::FETCH_COLUMN);
$platformOptions = array_values(array_filter(array_map(static fn($value): string => trim((string)$value), $platformOptions)));
if ($platformFilter !== '' && !in_array($platformFilter, $platformOptions, true)) {
    $platformFilter = '';
}

$whereParts = [];
$params = [];

if ($q !== '') {
    $whereParts[] = '(d.title LIKE ? OR d.excerpt LIKE ? OR d.description LIKE ? OR c.name LIKE ?
        OR d.version_label LIKE ? OR d.platform_label LIKE ? OR d.license_label LIKE ? OR d.requirements LIKE ?)';
    for ($i = 0; $i < 8; $i++) {
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

if ($categoryFilter !== null) {
    $whereParts[] = 'd.dl_category_id = ?';
    $params[] = $categoryFilter;
}

if ($typeFilter !== 'all') {
    $whereParts[] = 'd.download_type = ?';
    $params[] = $typeFilter;
}

if ($sourceFilter === 'local') {
    $whereParts[] = "d.filename <> '' AND d.external_url = ''";
} elseif ($sourceFilter === 'external') {
    $whereParts[] = "d.filename = '' AND d.external_url <> ''";
} elseif ($sourceFilter === 'hybrid') {
    $whereParts[] = "d.filename <> '' AND d.external_url <> ''";
}

if ($platformFilter !== '') {
    $whereParts[] = 'd.platform_label = ?';
    $params[] = $platformFilter;
}

if ($featuredFilter === 'featured') {
    $whereParts[] = 'd.is_featured = 1';
} elseif ($featuredFilter === 'regular') {
    $whereParts[] = 'd.is_featured = 0';
}

$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';
$currentListUrl = BASE_URL . '/admin/downloads.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

$stmt = $pdo->prepare(
    "SELECT d.id, d.title, d.slug, d.download_type, d.dl_category_id, COALESCE(c.name, '') AS category_name,
            d.excerpt, d.description, d.image_file, d.version_label, d.platform_label, d.license_label,
            d.project_url, d.release_date, d.requirements, d.checksum_sha256, d.series_key,
            d.external_url, d.filename, d.original_name, d.file_size, d.download_count, d.is_featured, d.is_published,
            d.created_at, d.updated_at, COALESCE(d.status,'published') AS status,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
     FROM cms_downloads d
     LEFT JOIN cms_dl_categories c ON c.id = d.dl_category_id
     LEFT JOIN cms_users u ON u.id = d.author_id
     {$whereSql}
     ORDER BY d.is_featured DESC, COALESCE(d.release_date, DATE(d.created_at)) DESC, d.created_at DESC, d.id DESC"
);
$stmt->execute($params);
$items = array_map(
    static fn(array $download): array => hydrateDownloadPresentation($download),
    $stmt->fetchAll()
);

adminHeader('Ke stažení');
?>
<p><a href="download_form.php" class="btn">+ Přidat položku</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q">Hledat</label>
    <input type="search" id="q" name="q" placeholder="Název, popis, platforma, požadavky…"
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
  <div>
    <label for="kat">Kategorie</label>
    <select id="kat" name="kat">
      <option value="">Všechny kategorie</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int)$category['id'] ?>"<?= $categoryFilter !== null && $categoryFilter === (int)$category['id'] ? ' selected' : '' ?>>
          <?= h((string)$category['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="typ">Typ</label>
    <select id="typ" name="typ">
      <option value="all">Všechny typy</option>
      <?php foreach (downloadTypeDefinitions() as $typeKey => $typeMeta): ?>
        <option value="<?= h($typeKey) ?>"<?= $typeFilter === $typeKey ? ' selected' : '' ?>><?= h((string)$typeMeta['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="source">Zdroj</label>
    <select id="source" name="source">
      <option value="all"<?= $sourceFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="local"<?= $sourceFilter === 'local' ? ' selected' : '' ?>>Jen lokální soubor</option>
      <option value="external"<?= $sourceFilter === 'external' ? ' selected' : '' ?>>Jen externí odkaz</option>
      <option value="hybrid"<?= $sourceFilter === 'hybrid' ? ' selected' : '' ?>>Soubor i externí odkaz</option>
    </select>
  </div>
  <div>
    <label for="platform">Platforma</label>
    <select id="platform" name="platform">
      <option value="">Všechny platformy</option>
      <?php foreach ($platformOptions as $platformOption): ?>
        <option value="<?= h($platformOption) ?>"<?= $platformFilter === $platformOption ? ' selected' : '' ?>><?= h($platformOption) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="featured">Doporučení</label>
    <select id="featured" name="featured">
      <option value="all"<?= $featuredFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="featured"<?= $featuredFilter === 'featured' ? ' selected' : '' ?>>Jen doporučené</option>
      <option value="regular"<?= $featuredFilter === 'regular' ? ' selected' : '' ?>>Bez doporučených</option>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all' || $categoryFilter !== null || $typeFilter !== 'all' || $sourceFilter !== 'all' || $platformFilter !== '' || $featuredFilter !== 'all'): ?>
    <a href="downloads.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($items)): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all' || $categoryFilter !== null || $typeFilter !== 'all' || $sourceFilter !== 'all' || $platformFilter !== '' || $featuredFilter !== 'all'): ?>
      Pro zvolený filtr tu teď nejsou žádné položky ke stažení.
    <?php else: ?>
      Zatím tu nejsou žádné položky ke stažení. <a href="download_form.php">Přidat první položku</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <?= bulkActions('downloads', $currentListUrl, 'Hromadné akce s položkami ke stažení', 'položka') ?>
  <table>
    <caption>Přehled položek ke stažení</caption>
    <thead>
      <tr>
        <th scope="col"><input type="checkbox" id="check-all" aria-label="Vybrat vše"></th>
        <th scope="col">Položka</th>
        <th scope="col">Katalog a metadata</th>
        <th scope="col">Zdroj a statistika</th>
        <th scope="col">Autor</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $download): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int)$download['id'] ?>" form="bulk-form" aria-label="Vybrat <?= h((string)$download['title']) ?>"></td>
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
          <?php if ((int)$download['is_featured'] === 1): ?>
            <strong>Doporučená položka</strong><br>
          <?php endif; ?>
          <strong><?= h((string)$download['download_type_label']) ?></strong>
          <?php if ($download['version_label'] !== ''): ?>
            <br><small style="color:#555">Verze <?= h((string)$download['version_label']) ?></small>
          <?php endif; ?>
          <?php if ($download['release_date_label'] !== ''): ?>
            <br><small style="color:#555">Vydáno <?= h((string)$download['release_date_label']) ?></small>
          <?php endif; ?>
          <?php if ($download['platform_label'] !== ''): ?>
            <br><small style="color:#555"><?= h((string)$download['platform_label']) ?></small>
          <?php endif; ?>
          <?php if ($download['license_label'] !== ''): ?>
            <br><small style="color:#555">Licence: <?= h((string)$download['license_label']) ?></small>
          <?php endif; ?>
          <?php if ($download['series_key'] !== ''): ?>
            <br><small style="color:#555">Skupina verzí: <?= h((string)$download['series_key']) ?></small>
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
            <small style="color:#555">Bez lokálního souboru</small>
          <?php endif; ?>
          <?php if ($download['has_external_url']): ?>
            <br><a href="<?= h((string)$download['external_url']) ?>" target="_blank" rel="noopener noreferrer">Externí odkaz</a>
          <?php endif; ?>
          <?php if ($download['has_project_url']): ?>
            <br><a href="<?= h((string)$download['project_url']) ?>" target="_blank" rel="noopener noreferrer">Domovská stránka projektu</a>
          <?php endif; ?>
          <br><small style="color:#555"><?= h((string)$download['download_count_label']) ?></small>
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
          <?php if ((string)$download['status'] === 'published' && (int)$download['is_published'] === 1): ?>
            <a href="<?= h(downloadPublicPath($download)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
          <?php endif; ?>
          <?php if ($download['status'] === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="downloads">
              <input type="hidden" name="id" value="<?= (int)$download['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h($currentListUrl) ?>">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="download_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$download['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Smazat položku ke stažení?">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= bulkCheckboxJs() ?>
<?php endif; ?>

<?php adminFooter(); ?>
