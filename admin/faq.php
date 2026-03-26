<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu FAQ nemáte potřebné oprávnění.');

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
    $whereParts[] = '(f.question LIKE ? OR f.excerpt LIKE ? OR f.answer LIKE ? OR c.name LIKE ?)';
    for ($i = 0; $i < 4; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($statusFilter === 'pending') {
    $whereParts[] = "COALESCE(f.status,'published') = 'pending'";
} elseif ($statusFilter === 'published') {
    $whereParts[] = "COALESCE(f.status,'published') = 'published' AND f.is_published = 1";
} elseif ($statusFilter === 'hidden') {
    $whereParts[] = "COALESCE(f.status,'published') = 'published' AND f.is_published = 0";
}

$whereSql = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$stmt = $pdo->prepare(
    "SELECT f.id, f.question, f.slug, f.excerpt, f.answer, f.is_published,
            COALESCE(f.status,'published') AS status, f.created_at, f.updated_at,
            c.name AS category_name
     FROM cms_faqs f
     LEFT JOIN cms_faq_categories c ON c.id = f.category_id
     {$whereSql}
     ORDER BY c.sort_order, c.name, f.created_at DESC, f.id DESC"
);
$stmt->execute($params);
$faqs = array_map(
    static fn(array $faq): array => hydrateFaqPresentation($faq),
    $stmt->fetchAll()
);

$canApproveFaq = currentUserHasCapability('content_approve_shared');

adminHeader('FAQ');
?>
<p>
  <a href="faq_form.php" class="btn">+ Přidat otázku</a>
  <a href="faq_cats.php" style="margin-left:1rem">Kategorie FAQ</a>
</p>

<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
  <div>
    <label for="q" class="visually-hidden">Hledat ve FAQ</label>
    <input type="search" id="q" name="q" placeholder="Hledat ve FAQ…"
           value="<?= h($q) ?>" style="width:300px">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>Vše</option>
      <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Publikováno</option>
      <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Čekající</option>
      <option value="hidden"<?= $statusFilter === 'hidden' ? ' selected' : '' ?>>Skryté</option>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== '' || $statusFilter !== 'all'): ?>
    <a href="faq.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if (empty($faqs)): ?>
  <p>
    <?php if ($q !== '' || $statusFilter !== 'all'): ?>
      Pro zvolený filtr tu teď nejsou žádné otázky.
    <?php else: ?>
      Zatím tu nejsou žádné otázky. <a href="faq_form.php">Přidat první otázku</a>.
    <?php endif; ?>
  </p>
<?php else: ?>
  <table>
    <caption>Přehled otázek FAQ</caption>
    <thead>
      <tr>
        <th scope="col">Otázka</th>
        <th scope="col">Kategorie</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($faqs as $faq): ?>
      <tr<?= $faq['status'] === 'pending' ? ' class="table-row--pending"' : '' ?>>
        <td>
          <strong><?= h((string)$faq['question']) ?></strong><br>
          <small style="color:#555">/faq/<?= h((string)$faq['slug']) ?></small>
          <?php if ($faq['excerpt'] !== ''): ?>
            <br><small style="color:#555"><?= h((string)$faq['excerpt']) ?></small>
          <?php endif; ?>
        </td>
        <td><?= h((string)($faq['category_name'] ?: '–')) ?></td>
        <td>
          <?php if ($faq['status'] === 'pending'): ?>
            <strong class="status-badge status-badge--pending">Čeká na schválení</strong>
          <?php elseif ((int)$faq['is_published'] === 1): ?>
            Publikováno
          <?php else: ?>
            <strong>Skryto</strong>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="faq_form.php?id=<?= (int)$faq['id'] ?>" class="btn">Upravit</a>
          <?php if ($faq['is_publicly_visible']): ?>
            <a href="<?= h(faqPublicPath($faq)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
          <?php endif; ?>
          <?php if ($faq['status'] === 'pending' && $canApproveFaq): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="faq">
              <input type="hidden" name="id" value="<?= (int)$faq['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/faq.php">
              <button type="submit" class="btn btn-success">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="faq_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$faq['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat otázku?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>


<?php adminFooter(); ?>
