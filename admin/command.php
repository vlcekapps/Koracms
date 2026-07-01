<?php

require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$query = trim((string)($_GET['q'] ?? ''));
$results = adminCommandSearch($pdo, $query, 40);
$pinnedItems = adminCommandPinnedItems($pdo, adminCommandCurrentUserId());
$currentUrl = BASE_URL . '/admin/command.php' . ($query !== '' ? '?q=' . rawurlencode($query) : '');

adminHeader('Hledání v administraci');
?>

<p class="admin-description">Najděte administrační sekci, rychlou akci nebo konkrétní obsah k úpravě. Výsledky respektují vaše oprávnění a zapnuté moduly.</p>

<form action="<?= h(BASE_URL . '/admin/command.php') ?>" method="get" role="search" aria-labelledby="admin-command-page-heading" class="admin-command-page-search">
  <h2 id="admin-command-page-heading" class="sr-only">Hledat v administraci</h2>
  <label for="admin-command-page-q">Hledaný výraz</label>
  <input type="search" id="admin-command-page-q" name="q" value="<?= h($query) ?>" class="admin-search-input" placeholder="Například článek, média, nastavení nebo rezervace">
  <button type="submit" class="btn">Hledat</button>
</form>

<?php if ($pinnedItems !== []): ?>
<section class="admin-section-spaced" aria-labelledby="admin-command-pinned-heading">
  <h2 id="admin-command-pinned-heading">Moje zkratky</h2>
  <ul class="admin-command-results">
    <?php foreach ($pinnedItems as $item): ?>
      <li class="admin-command-result">
        <a href="<?= h((string)$item['url']) ?>"><?= h((string)$item['label']) ?></a>
        <span class="admin-inline-meta"><?= h((string)$item['badge']) ?></span>
        <form method="post" action="<?= h(BASE_URL . '/admin/shortcut.php') ?>" class="admin-command-pin-form">
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="action" value="unpin">
          <input type="hidden" name="item_type" value="<?= h((string)$item['type']) ?>">
          <input type="hidden" name="item_key" value="<?= h((string)$item['key']) ?>">
          <input type="hidden" name="redirect" value="<?= h($currentUrl) ?>">
          <button type="submit" class="btn btn-muted">Odepnout</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<section class="admin-section-spaced" aria-labelledby="admin-command-results-heading">
  <h2 id="admin-command-results-heading"><?= $query !== '' ? 'Výsledky hledání' : 'Doporučené položky' ?></h2>
  <?php if ($results === []): ?>
    <p role="status">Nic odpovídajícího se nenašlo.</p>
  <?php else: ?>
    <ul class="admin-command-results">
      <?php foreach ($results as $item): ?>
        <li class="admin-command-result">
          <div class="admin-command-result__body">
            <a href="<?= h((string)$item['url']) ?>"><?= h((string)$item['label']) ?></a>
            <?php if (trim((string)$item['badge']) !== ''): ?>
              <span class="admin-inline-meta"><?= h((string)$item['badge']) ?></span>
            <?php endif; ?>
            <p><?= h((string)$item['description']) ?></p>
          </div>
          <?php if (!empty($item['pin_available'])): ?>
            <form method="post" action="<?= h(BASE_URL . '/admin/shortcut.php') ?>" class="admin-command-pin-form">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="action" value="<?= !empty($item['pinned']) ? 'unpin' : 'pin' ?>">
              <input type="hidden" name="item_type" value="<?= h((string)$item['type']) ?>">
              <input type="hidden" name="item_key" value="<?= h((string)$item['key']) ?>">
              <input type="hidden" name="redirect" value="<?= h($currentUrl) ?>">
              <button type="submit" class="btn"><?= !empty($item['pinned']) ? 'Odepnout' : 'Připnout' ?></button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php adminFooter(); ?>
