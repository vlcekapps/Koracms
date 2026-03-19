<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo  = db_connect();
$id   = inputInt('get', 'id');
$item = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_news WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) { header('Location: news.php'); exit; }
}

adminHeader($item ? 'Upravit novinku' : 'Přidat novinku');
?>
<form method="post" action="news_save.php" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($item): ?>
    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
  <?php endif; ?>

  <label for="content">Text novinky <span aria-hidden="true">*</span></label>
  <textarea id="content" name="content" rows="8" required aria-required="true"><?= h($item['content'] ?? '') ?></textarea>

  <p><small>Datum se uloží automaticky <?= $item ? '(zachová se původní)' : '(datum a čas přidání)' ?>.</small></p>

  <button type="submit" style="margin-top:.5rem"><?= $item ? 'Uložit změny' : 'Přidat novinku' ?></button>
  <a href="news.php" style="margin-left:1rem">Zrušit</a>
</form>
<?php adminFooter(); ?>
