<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu míst nemáte potřebné oprávnění.');
requireModuleEnabled('places');

$pdo = db_connect();
$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$kindFilter = trim((string)($_GET['kind'] ?? 'all'));
$categoryFilter = trim((string)($_GET['category'] ?? ''));
$localityFilter = trim((string)($_GET['locality'] ?? ''));
$placeBulkFlash = $_SESSION['place_bulk_flash'] ?? null;
unset($_SESSION['place_bulk_flash']);

$allowedStatusFilters = ['all', 'pending', 'published', 'hidden'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$kindOptions = placeKindOptions();
if ($kindFilter !== 'all' && !isset($kindOptions[$kindFilter])) {
    $kindFilter = 'all';
}

$categoryOptions = $pdo->query(
    "SELECT DISTINCT TRIM(category) AS category_label
     FROM cms_places
     WHERE deleted_at IS NULL
       AND TRIM(COALESCE(category, '')) <> ''
     ORDER BY category_label"
)->fetchAll(PDO::FETCH_COLUMN);
$categoryOptions = array_values(array_filter(array_map(static fn ($value): string => trim((string)$value), $categoryOptions)));
if ($categoryFilter !== '' && !in_array($categoryFilter, $categoryOptions, true)) {
    $categoryFilter = '';
}

$localityOptions = $pdo->query(
    "SELECT DISTINCT TRIM(locality) AS locality_label
     FROM cms_places
     WHERE deleted_at IS NULL
       AND TRIM(COALESCE(locality, '')) <> ''
     ORDER BY locality_label"
)->fetchAll(PDO::FETCH_COLUMN);
$localityOptions = array_values(array_filter(array_map(static fn ($value): string => trim((string)$value), $localityOptions)));
if ($localityFilter !== '' && !in_array($localityFilter, $localityOptions, true)) {
    $localityFilter = '';
}

$whereParts = ['p.deleted_at IS NULL'];
$params = [];
$perPage = 25;

if ($q !== '') {
    $whereParts[] = '(p.name LIKE ? OR p.excerpt LIKE ? OR p.description LIKE ? OR p.category LIKE ? OR p.locality LIKE ? OR p.address LIKE ? OR p.contact_phone LIKE ? OR p.contact_email LIKE ?)';
    for ($i = 0; $i < 8; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($statusFilter === 'pending') {
    $whereParts[] = "COALESCE(p.status, 'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $whereParts[] = "COALESCE(p.status, 'published') = 'published' AND p.is_published = 1";
} elseif ($statusFilter === 'hidden') {
    $whereParts[] = "COALESCE(p.status, 'published') = 'published' AND p.is_published = 0";
}

if ($kindFilter !== 'all') {
    $whereParts[] = 'p.place_kind = ?';
    $params[] = $kindFilter;
}

if ($categoryFilter !== '') {
    $whereParts[] = 'TRIM(p.category) = ?';
    $params[] = $categoryFilter;
}

if ($localityFilter !== '') {
    $whereParts[] = 'TRIM(p.locality) = ?';
    $params[] = $localityFilter;
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);
$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM cms_places p {$whereSql}",
    $params,
    $perPage
);
['totalPages' => $pages, 'page' => $page, 'offset' => $offset] = $pagination;

$stmt = $pdo->prepare(
    "SELECT p.id, p.name, p.slug, p.place_kind, p.category, p.locality, p.url, p.image_file,
            p.meta_title, p.meta_description, p.is_published, COALESCE(p.status,'published') AS status
     FROM cms_places p
     {$whereSql}
     ORDER BY p.name ASC, p.id ASC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$places = array_map(
    static fn (array $place): array => hydratePlacePresentation($place),
    $stmt->fetchAll()
);

$deleteError = trim((string)($_GET['delete_error'] ?? ''));
$deleteErrorPlaceId = inputInt('get', 'delete_error_id');
$deleteErrorMessage = match ($deleteError) {
    'confirm_required' => 'Místo nejde přesunout do Koše bez potvrzení kontroly dopadu. U pole Potvrzení přesunu je konkrétní nápověda.',
    'invalid' => 'Místo už není dostupné. Vyberte místo ze seznamu znovu.',
    'failed' => 'Místo se nepodařilo přesunout do Koše. Data zůstala beze změny; zkontrolujte položku a zkuste akci znovu.',
    default => '',
};
$deleteSuccessMessage = trim((string)($_GET['deleted'] ?? '')) === '1'
    ? 'Místo bylo přesunuto do Koše a lze je obnovit.'
    : '';

$bulkDeleteErrorCode = is_array($placeBulkFlash) ? trim((string)($placeBulkFlash['code'] ?? '')) : '';
$bulkDeleteHasError = is_array($placeBulkFlash)
    && ($placeBulkFlash['type'] ?? '') === 'error'
    && in_array($bulkDeleteErrorCode, [
        'place_bulk_delete_selection_required',
        'place_bulk_delete_confirm_required',
        'place_bulk_delete_selection_invalid',
        'place_bulk_delete_failed',
    ], true);
$bulkDeleteConfirmError = $bulkDeleteErrorCode === 'place_bulk_delete_confirm_required';
$bulkDeleteErrorFields = $bulkDeleteConfirmError ? ['confirm_place_bulk_delete'] : [];
$bulkDeleteSelectedIds = [];
foreach ((array)(is_array($placeBulkFlash) ? ($placeBulkFlash['selected_ids'] ?? []) : []) as $selectedId) {
    $selectedId = (int)$selectedId;
    if ($selectedId > 0) {
        $bulkDeleteSelectedIds[] = $selectedId;
    }
}
$visiblePlaceIds = array_map(static fn (array $place): int => (int)$place['id'], $places);
$bulkDeleteSelectedIds = array_values(array_intersect(array_unique($bulkDeleteSelectedIds), $visiblePlaceIds));
$bulkDeleteSelectedLookup = array_fill_keys($bulkDeleteSelectedIds, true);
$bulkDeleteFormErrorId = 'place-bulk-delete-form-error';
$bulkDeleteReviewId = 'place-bulk-delete-review';
$bulkDeleteFieldErrorId = 'confirm-place-bulk-delete-error';

$currentListQuery = array_filter([
    'q' => $q !== '' ? $q : null,
    'status' => $statusFilter !== 'all' ? $statusFilter : null,
    'kind' => $kindFilter !== 'all' ? $kindFilter : null,
    'category' => $categoryFilter !== '' ? $categoryFilter : null,
    'locality' => $localityFilter !== '' ? $localityFilter : null,
    'strana' => $page > 1 ? $page : null,
], static fn ($value): bool => $value !== null && $value !== '');
$currentListUrl = BASE_URL . '/admin/places.php' . ($currentListQuery !== [] ? '?' . http_build_query($currentListQuery) : '');

$pagerParams = array_filter([
    'q' => $q !== '' ? $q : null,
    'status' => $statusFilter !== 'all' ? $statusFilter : null,
    'kind' => $kindFilter !== 'all' ? $kindFilter : null,
    'category' => $categoryFilter !== '' ? $categoryFilter : null,
    'locality' => $localityFilter !== '' ? $localityFilter : null,
], static fn ($value): bool => $value !== null && $value !== '');
$pagerBaseUrl = '?' . ($pagerParams !== [] ? http_build_query($pagerParams) . '&' : '');

adminHeader('Zajímavá místa');
?>
<?php if ($deleteSuccessMessage !== ''): ?><p class="success" role="status" aria-atomic="true"><?= h($deleteSuccessMessage) ?></p><?php endif; ?>
<?php if ($deleteErrorMessage !== ''): ?><p id="place-delete-error" class="error" role="alert" aria-atomic="true"><?= h($deleteErrorMessage) ?></p><?php endif; ?>
<?php if (is_array($placeBulkFlash) && trim((string)($placeBulkFlash['message'] ?? '')) !== ''): ?>
  <p id="<?= h($bulkDeleteFormErrorId) ?>" class="<?= ($placeBulkFlash['type'] ?? '') === 'success' ? 'success' : 'error' ?>"
     role="<?= ($placeBulkFlash['type'] ?? '') === 'success' ? 'status' : 'alert' ?>" aria-atomic="true"><?= h((string)$placeBulkFlash['message']) ?></p>
<?php endif; ?>
<p><a href="place_form.php?redirect=<?= urlencode($currentListUrl) ?>" class="btn">+ Přidat místo</a></p>

<form method="get" class="button-row button-row--baseline admin-stack-sm">
  <div>
    <label for="q" class="visually-hidden">Hledat v místech</label>
    <input type="search" id="q" name="q" placeholder="Hledat v místech…" value="<?= h($q) ?>" class="admin-search-input">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Publikovaná</option>
      <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Čekající</option>
      <option value="hidden"<?= $statusFilter === 'hidden' ? ' selected' : '' ?>>Skrytá</option>
    </select>
  </div>
  <div>
    <label for="kind">Typ místa</label>
    <select id="kind" name="kind">
      <option value="all">Všechny typy</option>
      <?php foreach ($kindOptions as $kindKey => $kindMeta): ?>
        <option value="<?= h((string)$kindKey) ?>"<?= $kindFilter === (string)$kindKey ? ' selected' : '' ?>><?= h((string)$kindMeta['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="category">Kategorie</label>
    <select id="category" name="category">
      <option value="">Všechny kategorie</option>
      <?php foreach ($categoryOptions as $categoryOption): ?>
        <option value="<?= h((string)$categoryOption) ?>"<?= $categoryFilter === (string)$categoryOption ? ' selected' : '' ?>><?= h((string)$categoryOption) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="locality">Lokalita</label>
    <select id="locality" name="locality">
      <option value="">Všechny lokality</option>
      <?php foreach ($localityOptions as $localityOption): ?>
        <option value="<?= h((string)$localityOption) ?>"<?= $localityFilter === (string)$localityOption ? ' selected' : '' ?>><?= h((string)$localityOption) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all' || $kindFilter !== 'all' || $categoryFilter !== '' || $localityFilter !== ''): ?>
    <a href="places.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($places)): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all' || $kindFilter !== 'all' || $categoryFilter !== '' || $localityFilter !== ''): ?>
      Pro zvolený filtr tu teď nejsou žádná místa.
    <?php else: ?>
      Zatím tu nejsou žádná místa. <a href="place_form.php?redirect=<?= urlencode($currentListUrl) ?>">Přidat první místo</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <form method="post" action="place_bulk.php" id="bulk-form"<?= $bulkDeleteHasError ? ' aria-describedby="' . h($bulkDeleteFormErrorId) . '"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="redirect" value="<?= h($currentListUrl) ?>">
    <fieldset class="admin-fieldset-card">
      <legend>Hromadné akce s místy</legend>
      <p id="bulk-status" data-selection-status="bulk" class="field-help field-help--flush" aria-live="polite">Zatím není vybrané žádné místo.</p>
      <p id="<?= h($bulkDeleteReviewId) ?>" class="field-help field-help--flush">
        Přesun do Koše skryje vybraná místa z webu, administrativních přehledů a výběrů, ale zachová obrázky, revize, redirecty i vazby událostí. Místa lze v Koši obnovit; data a soubory se odstraní až samostatně potvrzeným trvalým smazáním.
      </p>
      <label for="confirm-place-bulk-delete" class="admin-checkbox-label">
        <input type="checkbox" id="confirm-place-bulk-delete" name="confirm_place_bulk_delete" value="1"
               required aria-required="true"<?= adminFieldAttributes('confirm_place_bulk_delete', $bulkDeleteErrorFields, [], [$bulkDeleteReviewId], $bulkDeleteFieldErrorId) ?>>
        Zkontroloval(a) jsem vybraná místa a chci je přesunout do Koše.
      </label>
      <?php adminRenderFieldError(
          'confirm_place_bulk_delete',
          $bulkDeleteErrorFields,
          [],
          'Před přesunem míst do Koše potvrďte, že jste zkontrolovali výběr a zachování souvisejících dat.',
          $bulkDeleteFieldErrorId
      ); ?>
      <div class="button-row">
        <button type="submit" name="action" value="delete" class="btn btn-danger bulk-action-btn" disabled data-confirm="Přesunout vybraná místa do Koše?">Přesunout vybrané do Koše</button>
        <button type="submit" name="action" value="publish" class="btn bulk-action-btn" disabled formnovalidate>Publikovat vybrané</button>
        <button type="submit" name="action" value="hide" class="btn bulk-action-btn" disabled formnovalidate>Skrýt vybrané</button>
      </div>
    </fieldset>
  </form>
  <table>
    <caption>Přehled zajímavých míst</caption>
    <thead>
      <tr>
        <th scope="col"><label for="check-all" class="sr-only">Vybrat vše</label><input type="checkbox" id="check-all"></th>
        <th scope="col">Místo</th>
        <th scope="col">Typ a lokalita</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($places as $place): ?>
      <?php
        $placeId = (int)$place['id'];
        $deleteConfirmField = 'confirm_place_delete_' . $placeId;
        $deleteConfirmId = 'confirm-place-delete-' . $placeId;
        $deleteReviewId = 'place-delete-review-' . $placeId;
        $deleteFieldErrorId = 'confirm-place-delete-' . $placeId . '-error';
        $deleteHasError = $deleteError === 'confirm_required' && $deleteErrorPlaceId === $placeId;
        $deleteErrorFields = $deleteHasError ? [$deleteConfirmField] : [];
        ?>
      <tr>
        <td><label for="place-select-<?= $placeId ?>" class="sr-only">Vybrat <?= h((string)$place['name']) ?></label><input type="checkbox" id="place-select-<?= $placeId ?>" name="ids[]" value="<?= $placeId ?>" form="bulk-form"<?= isset($bulkDeleteSelectedLookup[$placeId]) ? ' checked' : '' ?>></td>
        <td>
          <strong><?= h((string)$place['name']) ?></strong><br>
          <small class="table-meta">/places/<?= h((string)$place['slug']) ?></small>
          <?php if (!empty($place['category'])): ?>
            <br><small class="table-meta"><?= h((string)$place['category']) ?></small>
          <?php endif; ?>
          <?php if (!empty($place['image_file'])): ?>
            <br><small class="table-meta">Obrázek připojen</small>
          <?php endif; ?>
          <?php if ($place['meta_title'] !== '' || $place['meta_description'] !== ''): ?>
            <br><small class="table-meta">SEO metadata vyplněná</small>
          <?php endif; ?>
        </td>
        <td>
          <strong><?= h((string)$place['place_kind_label']) ?></strong>
          <?php if (!empty($place['locality'])): ?>
            <br><small class="table-meta"><?= h((string)$place['locality']) ?></small>
          <?php endif; ?>
          <?php if (!empty($place['url'])): ?>
            <br><a href="<?= h((string)$place['url']) ?>" target="_blank" rel="noopener noreferrer">Externí web<?= newWindowLinkSrOnlySuffix() ?></a>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($place['status'] === 'pending'): ?>
            <strong class="status-badge status-badge--pending">Čeká na schválení</strong>
          <?php elseif ((int)$place['is_published'] === 1): ?>
            Publikováno
          <?php else: ?>
            <strong>Skryto</strong>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="place_form.php?id=<?= (int)$place['id'] ?>&amp;redirect=<?= urlencode($currentListUrl) ?>" class="btn">Upravit</a>
          <?php if ($place['status'] === 'published' && (int)$place['is_published'] === 1): ?>
            <a href="<?= h(placePublicPath($place)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
          <?php endif; ?>
          <?php if ($place['status'] === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
            <form action="approve.php" method="post">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="places">
              <input type="hidden" name="id" value="<?= (int)$place['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h($currentListUrl) ?>">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="place_delete.php" method="post" class="admin-inline-form" novalidate<?= $deleteHasError ? ' aria-describedby="place-delete-error"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= $placeId ?>">
            <input type="hidden" name="redirect" value="<?= h($currentListUrl) ?>">
            <fieldset class="admin-inline-fieldset">
              <legend class="sr-only">Přesun místa <?= h((string)$place['name']) ?> do Koše</legend>
              <p id="<?= h($deleteReviewId) ?>" class="field-help field-help--flush">
                Přesun skryje místo z webu a výběrů, ale zachová jeho obrázek, revize, redirecty a vazby událostí pro případné obnovení.
              </p>
              <label for="<?= h($deleteConfirmId) ?>" class="admin-checkbox-label">
                <input type="checkbox" id="<?= h($deleteConfirmId) ?>" name="<?= h($deleteConfirmField) ?>" value="1"
                       required aria-required="true"<?= adminFieldAttributes($deleteConfirmField, $deleteErrorFields, [], [$deleteReviewId], $deleteFieldErrorId) ?>>
                Potvrzuji přesun tohoto místa do Koše.
              </label>
              <?php adminRenderFieldError($deleteConfirmField, $deleteErrorFields, [], 'Před přesunem místa do Koše potvrďte, že jste zkontrolovali zachování obrázku, revizí, redirectů a vazeb událostí.', $deleteFieldErrorId); ?>
              <button type="submit" class="btn btn-danger"
                      data-confirm="Přesunout místo <?= h((string)$place['name']) ?> do Koše?">Přesunout do Koše</button>
            </fieldset>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= renderPager($page, $pages, $pagerBaseUrl, 'Stránkování míst', 'Předchozí stránka', 'Další stránka') ?>
  <?= bulkCheckboxJs() ?>
<?php endif; ?>

<?php adminFooter(); ?>
