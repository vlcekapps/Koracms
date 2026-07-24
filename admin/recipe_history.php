<?php

require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu receptů nemáte potřebné oprávnění.');
requireModuleEnabled('recipes');

$pdo = db_connect();
$recipeId = inputInt('get', 'id') ?? inputInt('post', 'recipe_id');
if ($recipeId === null) {
    header('Location: ' . BASE_URL . '/admin/recipes.php');
    exit;
}

$recipeStmt = $pdo->prepare('SELECT id, title FROM cms_recipes WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$recipeStmt->execute([$recipeId]);
$recipe = $recipeStmt->fetch();
if (!is_array($recipe)) {
    header('Location: ' . BASE_URL . '/admin/recipes.php');
    exit;
}

$contentLockWarning = acquireContentLock('recipe', $recipeId);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $snapshotId = inputInt('post', 'snapshot_id');
    if ((string)($_POST['confirm_action'] ?? '') !== '1') {
        $error = 'Obnovení vyžaduje potvrzení, že současnou strukturu receptu nahradí vybraná verze.';
    } elseif ($snapshotId === null) {
        $error = 'Vybranou verzi se nepodařilo najít.';
    } else {
        $snapshotStmt = $pdo->prepare(
            'SELECT snapshot_json FROM cms_recipe_structure_snapshots
             WHERE id = ? AND recipe_id = ? LIMIT 1'
        );
        $snapshotStmt->execute([$snapshotId, $recipeId]);
        $snapshotJson = $snapshotStmt->fetchColumn();
        $snapshot = is_string($snapshotJson) ? recipeDecodeStructureSnapshot($snapshotJson) : null;
        if ($snapshot === null) {
            $error = 'Vybraná historická verze je neúplná a nelze ji bezpečně obnovit.';
        } else {
            $pdo->beginTransaction();
            try {
                recipeSaveStructureSnapshot(
                    $pdo,
                    $recipeId,
                    'Před obnovením historické verze',
                    currentUserId()
                );
                if (!recipeRestoreStructure($pdo, $recipeId, $snapshot)) {
                    throw new RuntimeException('Historickou verzi se nepodařilo obnovit.');
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            logAction('recipe_structure_restore', "recipe={$recipeId} snapshot={$snapshotId}");
            header('Location: ' . appendUrlQuery(
                BASE_URL . '/admin/recipe_history.php',
                ['id' => $recipeId, 'msg' => 'restored']
            ));
            exit;
        }
    }
}

$historyStmt = $pdo->prepare(
    "SELECT s.*,
            COALESCE(NULLIF(u.nickname, ''), NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), u.email) AS user_name
     FROM cms_recipe_structure_snapshots s
     LEFT JOIN cms_users u ON u.id = s.user_id
     WHERE s.recipe_id = ?
     ORDER BY s.created_at DESC, s.id DESC
     LIMIT 100"
);
$historyStmt->execute([$recipeId]);
$snapshots = $historyStmt->fetchAll();
$message = trim((string)($_GET['msg'] ?? '')) === 'restored'
    ? 'Vybraná struktura receptu byla obnovena. Předchozí stav zůstal uložený v historii.'
    : '';

adminHeader('Historie struktury: ' . (string)$recipe['title']);
?>
<p class="button-row button-row--start">
  <a href="recipe_content.php?id=<?= $recipeId ?>"><span aria-hidden="true">←</span> Ingredience a postup</a>
  <a href="recipe_form.php?id=<?= $recipeId ?>">Základní údaje receptu</a>
</p>

<?php if ($message !== ''): ?><p class="success" role="status"><?= h($message) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert" id="recipe-history-error"><?= h($error) ?></p><?php endif; ?>
<?php if ($contentLockWarning !== null): ?>
  <p class="warning" role="status">
    Tento recept právě upravuje <?= h((string)$contentLockWarning['locked_by']) ?>.
    Před obnovením historické verze se nejprve domluvte, aby se nepřepsaly souběžné změny.
  </p>
<?php endif; ?>

<section aria-labelledby="recipe-history-title">
  <h2 id="recipe-history-title">Obnovitelné verze ingrediencí a postupu</h2>
  <p class="admin-description">
    Verze vzniká před změnou skupiny, ingredience, kroku nebo jejich pořadí. Obnova nahradí současnou strukturu,
    ale základní údaje receptu, stav publikace a veřejnou adresu nezmění.
  </p>

  <?php if ($snapshots === []): ?>
    <p class="empty-state">Historie struktury zatím neobsahuje žádnou verzi.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <caption>Posledních <?= count($snapshots) ?> obnovitelných verzí struktury receptu</caption>
        <thead>
          <tr>
            <th scope="col">Čas a důvod</th>
            <th scope="col">Obsah verze</th>
            <th scope="col">Uživatel</th>
            <th scope="col">Obnova</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($snapshots as $snapshot): ?>
          <?php $snapshotId = (int)$snapshot['id']; ?>
          <tr>
            <td>
              <strong><?= h((string)$snapshot['action_label']) ?></strong><br>
              <small class="table-meta"><?= h(formatCzechDateTime((string)$snapshot['created_at'])) ?></small>
            </td>
            <td>
              <?= (int)$snapshot['ingredient_count'] ?> ingrediencí,
              <?= (int)$snapshot['step_count'] ?> kroků
            </td>
            <td><?= h(trim((string)$snapshot['user_name']) !== '' ? (string)$snapshot['user_name'] : 'Systém') ?></td>
            <td>
              <form method="post" aria-describedby="recipe-history-impact">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="recipe_id" value="<?= $recipeId ?>">
                <input type="hidden" name="snapshot_id" value="<?= $snapshotId ?>">
                <label class="admin-checkbox-label" for="confirm-recipe-snapshot-<?= $snapshotId ?>">
                  <input type="checkbox" id="confirm-recipe-snapshot-<?= $snapshotId ?>"
                         name="confirm_action" value="1" required aria-required="true">
                  Potvrzuji nahrazení současných ingrediencí a postupu touto verzí
                </label>
                <button type="submit" class="btn">Obnovit tuto verzi</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p id="recipe-history-impact" class="field-help">
      Před obnovou se současná struktura automaticky uloží jako další návratová verze.
    </p>
  <?php endif; ?>
</section>

<?php adminFooter(); ?>
