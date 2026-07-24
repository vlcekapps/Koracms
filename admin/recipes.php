<?php

require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu receptů nemáte potřebné oprávnění.');
requireModuleEnabled('recipes');

$pdo = db_connect();
$query = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'active'));
$categoryId = inputInt('get', 'category');
$allowedStatuses = ['active', 'draft', 'pending', 'published', 'trash'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'active';
}

$categories = $pdo->query(
    'SELECT id, name, is_active FROM cms_recipe_categories ORDER BY sort_order, name'
)->fetchAll();
$validCategoryIds = array_map(static fn (array $row): int => (int)$row['id'], $categories);
if ($categoryId !== null && !in_array($categoryId, $validCategoryIds, true)) {
    $categoryId = null;
}

$where = [];
$params = [];
if ($status === 'trash') {
    $where[] = 'r.deleted_at IS NOT NULL';
} else {
    $where[] = 'r.deleted_at IS NULL';
    if ($status !== 'active') {
        $where[] = 'r.status = ?';
        $params[] = $status;
    }
}
if ($query !== '') {
    $where[] = '(r.title LIKE ? OR r.summary LIKE ? OR r.notes LIKE ? OR c.name LIKE ?)';
    for ($i = 0; $i < 4; $i++) {
        $params[] = '%' . $query . '%';
    }
}
if ($categoryId !== null) {
    $where[] = 'r.category_id = ?';
    $params[] = $categoryId;
}

$stmt = $pdo->prepare(
    'SELECT r.*, c.name AS category_name, c.slug AS category_slug,
            (SELECT COUNT(*) FROM cms_recipe_ingredients i WHERE i.recipe_id = r.id) AS ingredient_count,
            (SELECT COUNT(*) FROM cms_recipe_steps s WHERE s.recipe_id = r.id) AS step_count,
            COALESCE(NULLIF(u.nickname, \'\'), NULLIF(TRIM(CONCAT(u.first_name, \' \', u.last_name)), \'\'), u.email) AS author_name
     FROM cms_recipes r
     LEFT JOIN cms_recipe_categories c ON c.id = r.category_id
     LEFT JOIN cms_users u ON u.id = r.author_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY r.updated_at DESC, r.id DESC'
);
$stmt->execute($params);
$recipes = $stmt->fetchAll();

$message = trim((string)($_GET['msg'] ?? ''));
$messageText = match ($message) {
    'saved' => 'Recept byl uložen.',
    'deleted' => 'Recept byl přesunut do koše.',
    'restored' => 'Recept byl obnoven jako koncept.',
    'purged' => 'Recept byl trvale smazán.',
    default => '',
};
$actionError = trim((string)($_GET['action_error'] ?? ''));

adminHeader('Recepty');
?>
<p class="button-row button-row--start">
  <a href="recipe_form.php" class="btn">+ Nový recept</a>
  <a href="recipe_categories.php" class="btn">Kategorie receptů</a>
  <a href="<?= h(recipeCookbookPublicPath()) ?>" target="_blank" rel="noopener noreferrer">Stáhnout veřejnou kuchařku EPUB<?= newWindowLinkSrOnlySuffix() ?></a>
</p>

<?php if ($messageText !== ''): ?>
  <p class="success" role="status"><?= h($messageText) ?></p>
<?php endif; ?>
<?php if ($actionError === 'confirm'): ?>
  <p class="error" role="alert">Akci se nepodařilo provést bez potvrzení kontroly jejího dopadu.</p>
<?php endif; ?>

<form method="get" role="search" aria-labelledby="recipe-admin-filter-title" class="button-row button-row--baseline admin-stack-sm">
  <h2 id="recipe-admin-filter-title" class="sr-only">Filtrovat recepty</h2>
  <div>
    <label for="q">Hledat</label>
    <input type="search" id="q" name="q" value="<?= h($query) ?>" placeholder="Název, shrnutí nebo poznámka">
  </div>
  <div>
    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Vše mimo koš</option>
      <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Koncepty</option>
      <option value="pending"<?= $status === 'pending' ? ' selected' : '' ?>>Čekající na schválení</option>
      <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Publikované</option>
      <option value="trash"<?= $status === 'trash' ? ' selected' : '' ?>>Koš</option>
    </select>
  </div>
  <div>
    <label for="category">Kategorie</label>
    <select id="category" name="category">
      <option value="">Všechny kategorie</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int)$category['id'] ?>"<?= $categoryId === (int)$category['id'] ? ' selected' : '' ?>>
          <?= h((string)$category['name']) ?><?= (int)$category['is_active'] === 1 ? '' : ' (neaktivní)' ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($query !== '' || $status !== 'active' || $categoryId !== null): ?>
    <a href="recipes.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<?php if ($recipes === []): ?>
  <p class="empty-state">Pro zvolený filtr tu nejsou žádné recepty.</p>
<?php else: ?>
  <div class="table-responsive">
    <table>
      <caption>Přehled receptů<?= $status === 'trash' ? ' v koši' : '' ?></caption>
      <thead>
        <tr>
          <th scope="col">Recept</th>
          <th scope="col">Kategorie</th>
          <th scope="col">Obsah receptu</th>
          <th scope="col">Stav</th>
          <th scope="col">Akce</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($recipes as $recipe): ?>
        <?php
        $recipeId = (int)$recipe['id'];
          $ingredientCount = (int)$recipe['ingredient_count'];
          $stepCount = (int)$recipe['step_count'];
          $complete = $ingredientCount > 0 && $stepCount > 0;
          ?>
        <tr>
          <td>
            <strong><?= h((string)$recipe['title']) ?></strong><br>
            <small class="table-meta">/recipes/<?= h((string)$recipe['slug']) ?></small>
            <?php if (trim((string)$recipe['author_name']) !== ''): ?>
              <br><small class="table-meta">Autor: <?= h((string)$recipe['author_name']) ?></small>
            <?php endif; ?>
          </td>
          <td><?= h((string)($recipe['category_name'] ?: 'Bez kategorie')) ?></td>
          <td>
            <?= $ingredientCount ?> <?= $ingredientCount === 1 ? 'ingredience' : 'ingrediencí' ?>,
            <?= $stepCount ?> <?= $stepCount === 1 ? 'krok' : 'kroků' ?>
            <br><small class="table-meta"><?= $complete ? 'Připraveno k publikaci' : 'Chybí ingredience nebo postup' ?></small>
          </td>
          <td>
            <?php if ($recipe['deleted_at'] !== null): ?>
              V koši
            <?php elseif ((string)$recipe['status'] === 'published' && !recipeIsPubliclyVisible($recipe)): ?>
              Naplánováno
            <?php else: ?>
              <?= h(match ((string)$recipe['status']) {
                  'published' => 'Publikováno',
                  'pending' => 'Čeká na schválení',
                  default => 'Koncept',
              }) ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($recipe['deleted_at'] === null): ?>
              <a href="recipe_form.php?id=<?= $recipeId ?>">Upravit</a>
              <a href="recipe_content.php?id=<?= $recipeId ?>">Ingredience a postup</a>
              <?php if (recipeIsPubliclyVisible($recipe)): ?>
                <a href="<?= h(recipePublicPath($recipe)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit<?= newWindowLinkSrOnlySuffix() ?></a>
              <?php endif; ?>
              <form method="post" action="recipe_action.php" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= $recipeId ?>">
                <input type="hidden" name="action" value="delete">
                <label class="admin-checkbox-label" for="confirm-recipe-delete-<?= $recipeId ?>">
                  <input type="checkbox" id="confirm-recipe-delete-<?= $recipeId ?>" name="confirm_action" value="1" required aria-required="true">
                  Potvrzuji přesun receptu „<?= h((string)$recipe['title']) ?>“ do koše
                </label>
                <button type="submit" class="btn btn-danger">Přesunout do koše</button>
              </form>
            <?php else: ?>
              <form method="post" action="recipe_action.php" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= $recipeId ?>">
                <input type="hidden" name="action" value="restore">
                <button type="submit" class="btn">Obnovit jako koncept</button>
              </form>
              <form method="post" action="recipe_action.php" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= $recipeId ?>">
                <input type="hidden" name="action" value="purge">
                <label class="admin-checkbox-label" for="confirm-recipe-purge-<?= $recipeId ?>">
                  <input type="checkbox" id="confirm-recipe-purge-<?= $recipeId ?>" name="confirm_action" value="1" required aria-required="true">
                  Potvrzuji nevratné smazání receptu „<?= h((string)$recipe['title']) ?>“ včetně ingrediencí a postupu
                </label>
                <button type="submit" class="btn btn-danger">Trvale smazat</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php adminFooter(); ?>
