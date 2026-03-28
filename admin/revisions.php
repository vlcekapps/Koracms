<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$entityType = trim($_GET['type'] ?? '');
$entityId   = inputInt('get', 'id');

$allowedTypes = [
    'article' => ['table' => 'cms_articles', 'label' => 'Článek', 'title_col' => 'title', 'back' => 'blog_form.php'],
    'news'    => ['table' => 'cms_news',     'label' => 'Novinka', 'title_col' => 'title', 'back' => 'news_form.php'],
    'page'    => ['table' => 'cms_pages',    'label' => 'Stránka', 'title_col' => 'title', 'back' => 'page_form.php'],
    'event'   => ['table' => 'cms_events',   'label' => 'Událost', 'title_col' => 'title', 'back' => 'event_form.php'],
    'faq'     => ['table' => 'cms_faqs',     'label' => 'FAQ', 'title_col' => 'question', 'back' => 'faq_form.php'],
];

if ($entityType === '' || $entityId === null || !isset($allowedTypes[$entityType])) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$config = $allowedTypes[$entityType];
$pdo = db_connect();

$entityStmt = $pdo->prepare("SELECT {$config['title_col']} AS entity_title FROM {$config['table']} WHERE id = ?");
$entityStmt->execute([$entityId]);
$entity = $entityStmt->fetch();

if (!$entity) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
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
            $out .= '<del style="background:#fdd;text-decoration:line-through">' . h($ow) . '</del>';
            $oi++;
        } elseif ($ni < count($newWords)) {
            $out .= '<ins style="background:#dfd;text-decoration:none">' . h($nw) . '</ins>';
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

adminHeader('Historie revizí – ' . mb_substr((string)$entity['entity_title'], 0, 60));
?>

<div style="margin-bottom:1rem">
  <a href="<?= h($config['back']) ?>?id=<?= $entityId ?>" class="btn">&larr; Zpět na editaci</a>
</div>

<p>
  <strong><?= h($config['label']) ?>:</strong> <?= h((string)$entity['entity_title']) ?><br>
  <strong>Celkem revizí:</strong> <?= count($revisions) ?>
</p>

<?php if (empty($revisions)): ?>
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
    <?php foreach ($revisions as $rev): ?>
      <tr>
        <td><time datetime="<?= h(str_replace(' ', 'T', (string)$rev['created_at'])) ?>"><?= h(formatCzechDate((string)$rev['created_at'])) ?></time></td>
        <td><?= h((string)$rev['user_name']) ?></td>
        <td><?= h(revisionFieldLabel($entityType, (string)$rev['field_name'])) ?></td>
        <td style="max-width:600px;word-break:break-word">
          <?php
            $old = (string)$rev['old_value'];
            $new = (string)$rev['new_value'];
            if (mb_strlen($old) > 500 || mb_strlen($new) > 500): ?>
            <details>
              <summary>Zobrazit diff (<?= mb_strlen($old) ?> → <?= mb_strlen($new) ?> znaků)</summary>
              <div style="white-space:pre-wrap;font-size:.88rem;line-height:1.5;margin-top:.5rem"><?= simpleDiff($old, $new) ?></div>
            </details>
          <?php else: ?>
            <div style="white-space:pre-wrap;font-size:.88rem;line-height:1.5"><?= simpleDiff($old, $new) ?></div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
