<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

adminHeader('Pozice modulů');
?>

<?php if (isset($_GET['nav_saved'])): ?>
  <p class="success" role="status">Pořadí bylo uloženo.</p>
<?php endif; ?>

<p>Tlačítky měňte pořadí, v jakém se moduly zobrazují návštěvníkům v hlavní navigaci webu.
   Vypnuté moduly se v navigaci nezobrazí bez ohledu na zvolenou pozici.</p>

<?php
  $moduleMap = navModuleDefaults();
  $navOrder  = navModuleOrder();
  $total     = count($navOrder);
?>
<ol style="list-style:none;padding:0;margin:0;max-width:480px">
<?php foreach ($navOrder as $i => $key):
  if (!isset($moduleMap[$key])) continue;
  [, $label] = $moduleMap[$key];
  $enabled   = isModuleEnabled($key);
?>
  <li style="display:flex;align-items:center;gap:.5rem;padding:.4rem 0;border-bottom:1px solid #eee">
    <span style="min-width:160px<?= $enabled ? '' : ';color:#666' ?>">
      <?= h($label) ?><?= $enabled ? '' : ' <em>(vypnuto)</em>' ?>
    </span>

    <form method="post" action="nav_reorder.php" style="display:inline;margin:0">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="module" value="<?= h($key) ?>">
      <input type="hidden" name="dir" value="up">
      <button type="submit" class="btn"
              <?= $i === 0 ? 'disabled aria-disabled="true"' : '' ?>
              aria-label="Posunout <?= h($label) ?> nahoru">
        <span aria-hidden="true">↑</span> Nahoru
      </button>
    </form>

    <form method="post" action="nav_reorder.php" style="display:inline;margin:0">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="module" value="<?= h($key) ?>">
      <input type="hidden" name="dir" value="down">
      <button type="submit" class="btn"
              <?= $i === $total - 1 ? 'disabled aria-disabled="true"' : '' ?>
              aria-label="Posunout <?= h($label) ?> dolů">
        <span aria-hidden="true">↓</span> Dolů
      </button>
    </form>
  </li>
<?php endforeach; ?>
</ol>

<?php adminFooter(); ?>
