<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu událostí nemáte potřebné oprávnění.');

$pdo = db_connect();
$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$typeFilter = trim((string)($_GET['typ'] ?? 'all'));
$scopeFilter = trim((string)($_GET['scope'] ?? 'all'));

$allowedStatusFilters = ['all', 'pending', 'published', 'hidden'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}
if ($typeFilter !== 'all' && !isset(eventKindDefinitions()[$typeFilter])) {
    $typeFilter = 'all';
}
if (!in_array($scopeFilter, ['all', 'upcoming', 'ongoing', 'past'], true)) {
    $scopeFilter = 'all';
}

$whereParts = ['e.deleted_at IS NULL'];
$params = [];

if ($q !== '') {
    $whereParts[] = '(e.title LIKE ? OR e.location LIKE ? OR e.description LIKE ? OR e.excerpt LIKE ? OR e.organizer_name LIKE ?)';
    for ($i = 0; $i < 5; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($statusFilter === 'pending') {
    $whereParts[] = "COALESCE(e.status, 'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $whereParts[] = "COALESCE(e.status, 'published') = 'published' AND e.is_published = 1";
} elseif ($statusFilter === 'hidden') {
    $whereParts[] = "COALESCE(e.status, 'published') = 'published' AND e.is_published = 0";
}

if ($typeFilter !== 'all') {
    $whereParts[] = 'e.event_kind = ?';
    $params[] = $typeFilter;
}

if ($scopeFilter !== 'all') {
    $whereParts[] = '(' . eventScopeVisibilitySql($scopeFilter, 'e') . ')';
}

$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';
$effectiveEndSql = eventEffectiveEndSql('e');

$stmt = $pdo->prepare(
    "SELECT e.*
     FROM cms_events e
     {$whereSql}
     ORDER BY
        CASE
            WHEN {$effectiveEndSql} >= NOW() THEN 0
            ELSE 1
        END,
        CASE
            WHEN {$effectiveEndSql} >= NOW() THEN e.event_date
            ELSE NULL
        END ASC,
        CASE
            WHEN {$effectiveEndSql} < NOW() THEN {$effectiveEndSql}
            ELSE NULL
        END DESC,
        e.id DESC"
);
$stmt->execute($params);
$events = array_map(
    static fn(array $event): array => hydrateEventPresentation($event),
    $stmt->fetchAll()
);

adminHeader('Události');
?>
<p><a href="event_form.php" class="btn">+ Přidat událost</a></p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q" class="visually-hidden">Hledat v událostech</label>
    <input type="search" id="q" name="q" placeholder="Hledat v událostech…"
           value="<?= h($q) ?>" style="width:300px">
  </div>
  <div>
    <label for="typ">Typ akce</label>
    <select id="typ" name="typ">
      <option value="all"<?= $typeFilter === 'all' ? ' selected' : '' ?>>Všechny typy</option>
      <?php foreach (eventKindDefinitions() as $typeKey => $typeMeta): ?>
        <option value="<?= h($typeKey) ?>"<?= $typeFilter === $typeKey ? ' selected' : '' ?>>
          <?= h((string)($typeMeta['label'] ?? $typeKey)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="scope">Časový stav</label>
    <select id="scope" name="scope">
      <option value="all"<?= $scopeFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="ongoing"<?= $scopeFilter === 'ongoing' ? ' selected' : '' ?>>Právě probíhá</option>
      <option value="upcoming"<?= $scopeFilter === 'upcoming' ? ' selected' : '' ?>>Připravované</option>
      <option value="past"<?= $scopeFilter === 'past' ? ' selected' : '' ?>>Proběhlé</option>
    </select>
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
  <?php if ($q !== '' || $statusFilter !== 'all' || $typeFilter !== 'all' || $scopeFilter !== 'all'): ?>
    <a href="events.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($events)): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all' || $typeFilter !== 'all' || $scopeFilter !== 'all'): ?>
      Pro zvolený filtr tu teď nejsou žádné události.
    <?php else: ?>
      Zatím tu nejsou žádné události. <a href="event_form.php">Přidat první událost</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <?= bulkActions('events', BASE_URL . '/admin/events.php', 'Hromadné akce s událostmi', 'událost') ?>
  <table>
    <caption>Přehled událostí</caption>
    <thead>
      <tr>
        <th scope="col"><input type="checkbox" id="check-all" aria-label="Vybrat vše"></th>
        <th scope="col">Název</th>
        <th scope="col">Typ a termín</th>
        <th scope="col">Místo</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($events as $event): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int)$event['id'] ?>" form="bulk-form" aria-label="Vybrat <?= h((string)$event['title']) ?>"></td>
        <td>
          <strong><?= h((string)$event['title']) ?></strong><br>
          <small style="color:#555">/events/<?= h((string)($event['slug'] ?? '')) ?></small>
        </td>
        <td>
          <span class="status-badge"><?= h((string)($event['event_kind_label'] ?? 'Akce')) ?></span><br>
          <time datetime="<?= h(str_replace(' ', 'T', (string)$event['event_date'])) ?>"><?= h(formatCzechDate((string)$event['event_date'])) ?></time>
          <?php if (!empty($event['event_end'])): ?>
            <br><small>do <?= h(formatCzechDate((string)$event['event_end'])) ?></small>
          <?php endif; ?>
          <br><small style="color:#555"><?= h((string)($event['event_status_label'] ?? 'Připravujeme')) ?></small>
        </td>
        <td><?= h((string)($event['location'] !== '' ? $event['location'] : '–')) ?></td>
        <td>
          <?php if (($event['status'] ?? 'published') === 'pending'): ?>
            <strong class="status-badge status-badge--pending"><span aria-hidden="true">⏳</span> Čeká na schválení</strong>
          <?php elseif ((int)($event['is_published'] ?? 0) === 1): ?>
            Publikováno
          <?php else: ?>
            <strong>Skrytá</strong>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="event_form.php?id=<?= (int)$event['id'] ?>" class="btn">Upravit</a>
          <?php if (($event['status'] ?? 'published') === 'published' && (int)($event['is_published'] ?? 0) === 1): ?>
            <a href="<?= h(eventPublicPath($event)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
          <?php endif; ?>
          <?php if (($event['status'] ?? 'published') === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="events">
              <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/events.php">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="event_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Smazat událost?">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= bulkCheckboxJs() ?>
<?php endif; ?>

<?php adminFooter(); ?>
