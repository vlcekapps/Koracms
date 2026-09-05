<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$entityType = trim($_GET['type'] ?? '');
$entityId   = inputInt('get', 'id');

$allowedTypes = revisionEntityDefinitions();

if ($entityType === '' || $entityId === null || !isset($allowedTypes[$entityType])) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$config = $allowedTypes[$entityType];
requireCapability($config['capability']);
if ($config['module'] !== '') {
    requireModuleEnabled($config['module']);
}
$pdo = db_connect();

$entityStmt = $pdo->prepare("SELECT *, {$config['title_col']} AS entity_title FROM {$config['table']} WHERE id = ?");
$entityStmt->execute([$entityId]);
$entity = $entityStmt->fetch();

if (!$entity) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

if (!canReadEntityRevisions($entityType, $entity)) {
    adminForbidden('Přístup odepřen. Pro historii tohoto obsahu nemáte potřebné oprávnění.');
}

$revisions = loadRevisions($pdo, $entityType, $entityId);

/**
 * Jednoduchý inline diff – zvýrazní přidané/odebrané věty.
 */
function simpleDiff(string $old, string $new): string
{
    if ($old === $new) {
        return h($new);
    }
    $oldWords = preg_split('/(\s+)/', $old, -1, PREG_SPLIT_DELIM_CAPTURE);
    $newWords = preg_split('/(\s+)/', $new, -1, PREG_SPLIT_DELIM_CAPTURE);

    $maxLen = max(count($oldWords), count($newWords));
    $out = '';
    $oi = 0;
    $ni = 0;

    while ($oi < count($oldWords) || $ni < count($newWords)) {
        $ow = $oldWords[$oi] ?? '';
        $nw = $newWords[$ni] ?? '';

        if ($ow === $nw) {
            $out .= h($nw);
            $oi++;
            $ni++;
        } elseif ($oi < count($oldWords) && !in_array($ow, array_slice($newWords, $ni, 10), true)) {
            $out .= '<del class="revision-diff__delete">' . h($ow) . '</del>';
            $oi++;
        } elseif ($ni < count($newWords)) {
            $out .= '<ins class="revision-diff__insert">' . h($nw) . '</ins>';
            $ni++;
        } else {
            $oi++;
            $ni++;
        }

        if ($oi > 500 || $ni > 500) {
            $out .= '…';
            break;
        }
    }
    return $out;
}

$revisionRows = [];
foreach ($revisions as $revision) {
    $old = (string)$revision['old_value'];
    $new = (string)$revision['new_value'];
    $revisionRows[] = $revision + [
        'old_length' => mb_strlen($old),
        'new_length' => mb_strlen($new),
        'diff_html' => simpleDiff($old, $new),
        'use_details' => mb_strlen($old) > 500 || mb_strlen($new) > 500,
    ];
}

adminHeader('Historie revizí – ' . mb_substr((string)$entity['entity_title'], 0, 60));
?>

<div class="admin-stack-sm">
  <a href="<?= h($config['back']) ?>?id=<?= $entityId ?>" class="btn">&larr; Zpět na editaci</a>
</div>

<p>
  <strong><?= h($config['label']) ?>:</strong> <?= h((string)$entity['entity_title']) ?><br>
  <strong>Celkem revizí:</strong> <?= count($revisionRows) ?>
</p>

<?php if (empty($revisionRows)): ?>
  <p>Pro tuto položku zatím nebyly zaznamenány žádné revize.</p>
<?php else: ?>
  <table>
    <caption class="sr-only">Historie revizí</caption>
    <thead>
      <tr>
        <th scope="col">Datum</th>
        <th scope="col">Uživatel</th>
        <th scope="col">Pole</th>
        <th scope="col">Změny</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($revisionRows as $rev): ?>
      <tr>
        <td><time datetime="<?= h(str_replace(' ', 'T', (string)$rev['created_at'])) ?>"><?= h(formatCzechDate((string)$rev['created_at'])) ?></time></td>
        <td><?= h((string)$rev['user_name']) ?></td>
        <td><?= h(revisionFieldLabel($entityType, (string)$rev['field_name'])) ?></td>
        <td class="revision-diff-cell">
          <?php if ((bool)$rev['use_details']): ?>
            <details>
              <summary>Zobrazit diff (<?= (int)$rev['old_length'] ?> → <?= (int)$rev['new_length'] ?> znaků)</summary>
              <div class="revision-diff revision-diff--details"><?= $rev['diff_html'] ?></div>
            </details>
          <?php else: ?>
            <div class="revision-diff"><?= $rev['diff_html'] ?></div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
