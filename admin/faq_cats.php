<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu kategorií FAQ nemáte potřebné oprávnění.');

$pdo = db_connect();
$success = false;
$error = '';
$editId = inputInt('get', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
    $updateId = inputInt('post', 'update_id');

    $parentId = inputInt('post', 'parent_id');

    if ($name === '') {
        $error = 'Název kategorie je povinný.';
    } elseif ($updateId !== null) {
        if ($parentId === $updateId) {
            $parentId = null;
        }
        $pdo->prepare("UPDATE cms_faq_categories SET name = ?, sort_order = ?, parent_id = ? WHERE id = ?")->execute([$name, $sortOrder, $parentId, $updateId]);
        $success = true;
        $editId = null;
    } else {
        $pdo->prepare("INSERT INTO cms_faq_categories (name, sort_order, parent_id) VALUES (?, ?, ?)")->execute([$name, $sortOrder, $parentId]);
        $success = true;
    }
}

$categories = $pdo->query("SELECT id, name, sort_order, parent_id FROM cms_faq_categories ORDER BY sort_order, name")->fetchAll();

// Sestavíme strom kategorií
$categoryTree = [];
$categoryById = [];
foreach ($categories as $cat) {
    $categoryById[(int)$cat['id']] = $cat;
}
foreach ($categories as $cat) {
    $pid = $cat['parent_id'] !== null ? (int)$cat['parent_id'] : 0;
    $categoryTree[$pid][] = $cat;
}

function renderCategoryOptions(array $tree, array $byId, int $parentId = 0, int $depth = 0, ?int $excludeId = null): string
{
    $out = '';
    foreach ($tree[$parentId] ?? [] as $cat) {
        $cid = (int)$cat['id'];
        if ($cid === $excludeId) {
            continue;
        }
        $prefix = str_repeat('— ', $depth);
        $out .= '<option value="' . $cid . '">' . h($prefix . $cat['name']) . '</option>';
        $out .= renderCategoryOptions($tree, $byId, $cid, $depth + 1, $excludeId);
    }
    return $out;
}

adminHeader('Kategorie znalostní báze');
?>
<?php if ($success): ?><p class="success" role="status">Kategorie uložena.</p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset>
    <legend>Nová kategorie</legend>
    <div style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label for="name">Název <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" required aria-required="true" maxlength="255">
      </div>
      <div>
        <label for="parent_id">Nadřazená kategorie</label>
        <select id="parent_id" name="parent_id">
          <option value="">— Kořenová —</option>
          <?= renderCategoryOptions($categoryTree, $categoryById) ?>
        </select>
      </div>
      <div>
        <label for="sort_order">Pořadí</label>
        <input type="number" id="sort_order" name="sort_order" min="0" style="width:5rem" value="0">
      </div>
      <button type="submit">Přidat kategorii</button>
    </div>
  </fieldset>
</form>

<h2>Přehled kategorií znalostní báze</h2>
<?php if (empty($categories)): ?>
  <p>Zatím tu nejsou žádné kategorie.</p>
<?php else: ?>
  <table>
    <caption>Přehled kategorií znalostní báze</caption>
    <thead><tr><th scope="col">Název</th><th scope="col">Pořadí</th><th scope="col">Akce</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $category): ?>
      <tr>
        <?php if ($editId === (int)$category['id']): ?>
          <td colspan="2">
            <form method="post" style="display:flex;gap:.4rem;align-items:center" novalidate>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= (int)$category['id'] ?>">
              <label for="name-<?= (int)$category['id'] ?>" class="sr-only">Název kategorie</label>
              <input type="text" id="name-<?= (int)$category['id'] ?>" name="name" required aria-required="true" maxlength="255"
                     value="<?= h((string)$category['name']) ?>" style="width:auto">
              <label for="sort-<?= (int)$category['id'] ?>" class="sr-only">Pořadí</label>
              <input type="number" id="sort-<?= (int)$category['id'] ?>" name="sort_order" min="0" style="width:5rem"
                     value="<?= (int)$category['sort_order'] ?>">
              <button type="submit" class="btn">Uložit</button>
              <a href="faq_cats.php">Zrušit</a>
            </form>
          </td>
        <?php else: ?>
          <td><?= h((string)$category['name']) ?></td>
          <td><?= (int)$category['sort_order'] ?></td>
        <?php endif; ?>
        <td class="actions">
          <?php if ($editId !== (int)$category['id']): ?>
            <a href="faq_cats.php?edit=<?= (int)$category['id'] ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="faq_cat_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm=”Smazat kategorii? Otázky bez kategorie se zobrazí v sekci „Ostatní”.”>Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<p><a href="faq.php"><span aria-hidden="true">&larr;</span> Zpět na znalostní bázi</a></p>

<?php adminFooter(); ?>
