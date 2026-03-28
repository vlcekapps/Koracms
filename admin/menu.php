<?php
/**
 * Správa navigace – jednotné řazení modulů, statických stránek a blogů.
 */
require_once __DIR__ . '/layout.php';
requireCapability('settings_manage', 'Přístup odepřen.');

$pdo = db_connect();

// ── Sestavení seznamu všech navigačních položek ──────────────────────────────

$navItems = []; // [{key, type, label, sublabel, enabled}]

// Moduly
$moduleMap = navModuleDefaults();
foreach (array_keys($moduleMap) as $mKey) {
    if ($mKey === 'blog') {
        // Multiblog: každý blog jako samostatná položka
        foreach (getAllBlogs() as $blogEntry) {
            $blogNav = (int)($blogEntry['show_in_nav'] ?? 1);
            $navItems[] = [
                'key' => 'blog:' . (int)$blogEntry['id'],
                'type' => 'blog',
                'label' => (string)$blogEntry['name'],
                'sublabel' => 'Blog' . (!$blogNav ? ' · mimo navigaci' : ''),
                'enabled' => isModuleEnabled('blog') && $blogNav,
            ];
        }
    } else {
        $navItems[] = [
            'key' => 'module:' . $mKey,
            'type' => 'module',
            'label' => $moduleMap[$mKey][1],
            'sublabel' => 'Modul',
            'enabled' => isModuleEnabled($mKey),
        ];
    }
}

// Statické stránky
$pages = $pdo->query(
    "SELECT id, title, slug, show_in_nav, is_published, COALESCE(status,'published') AS status
     FROM cms_pages WHERE deleted_at IS NULL ORDER BY nav_order, title"
)->fetchAll();
foreach ($pages as $p) {
    $navItems[] = [
        'key' => 'page:' . (int)$p['id'],
        'type' => 'page',
        'label' => (string)$p['title'],
        'sublabel' => 'Stránka' . ((int)$p['show_in_nav'] !== 1 ? ' · mimo navigaci' : '') . ((int)$p['is_published'] !== 1 ? ' · nezveřejněná' : ''),
        'enabled' => (int)$p['show_in_nav'] === 1 && (int)$p['is_published'] === 1 && ($p['status'] ?? 'published') === 'published',
    ];
}

// ── Načtení uloženého pořadí ─────────────────────────────────────────────────

$savedOrder = getSetting('nav_order_unified', '');
if ($savedOrder !== '') {
    $orderedKeys = explode(',', $savedOrder);
    $itemsByKey = [];
    foreach ($navItems as $item) {
        $itemsByKey[$item['key']] = $item;
    }
    $sorted = [];
    foreach ($orderedKeys as $key) {
        if (isset($itemsByKey[$key])) {
            $sorted[] = $itemsByKey[$key];
            unset($itemsByKey[$key]);
        }
    }
    // Přidat nové položky (dosud neuložené) na konec
    foreach ($itemsByKey as $item) {
        $sorted[] = $item;
    }
    $navItems = $sorted;
}

// ── POST: uložení pořadí ────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $order = (array)($_POST['order'] ?? []);
    $order = array_values(array_filter($order, fn($v) => is_string($v) && $v !== ''));
    saveSetting('nav_order_unified', implode(',', $order));
    logAction('nav_reorder_unified', 'order=' . implode(',', $order));
    header('Location: ' . BASE_URL . '/admin/menu.php?saved=1');
    exit;
}

adminHeader('Navigace webu');
?>

<?php if (isset($_GET['saved'])): ?>
  <p class="success" role="status">Pořadí navigace uloženo.</p>
<?php endif; ?>

<p style="font-size:.9rem">Nastavte pořadí položek v hlavní navigaci webu. Přetahujte myší nebo použijte tlačítka Nahoru/Dolů.
   Vypnuté moduly a nezveřejněné stránky se návštěvníkům nezobrazí.</p>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <ol style="list-style:none;padding:0;margin:0;max-width:62rem" id="nav-list" data-sortable="nav_unified">
    <?php $total = count($navItems); ?>
    <?php foreach ($navItems as $index => $item): ?>
      <li style="display:flex;align-items:center;gap:.75rem;padding:.55rem 0;border-bottom:1px solid #eee;flex-wrap:wrap;cursor:grab<?= !$item['enabled'] ? ';opacity:.5' : '' ?>"
          data-sort-id="<?= h($item['key']) ?>" tabindex="0">
        <input type="hidden" name="order[]" value="<?= h($item['key']) ?>">
        <div style="min-width:14rem;flex:1 1 16rem">
          <strong><?= h($item['label']) ?></strong>
          <br><small style="color:#555"><?= h($item['sublabel']) ?><?= !$item['enabled'] ? ' · <em>neaktivní</em>' : '' ?></small>
        </div>

        <button type="button" class="btn nav-move-up"
                <?= $index === 0 ? 'disabled aria-disabled="true"' : '' ?>
                aria-label="Posunout <?= h($item['label']) ?> nahoru">
          <span aria-hidden="true">↑</span> Nahoru
        </button>
        <button type="button" class="btn nav-move-down"
                <?= $index === $total - 1 ? 'disabled aria-disabled="true"' : '' ?>
                aria-label="Posunout <?= h($item['label']) ?> dolů">
          <span aria-hidden="true">↓</span> Dolů
        </button>
      </li>
    <?php endforeach; ?>
  </ol>

  <div style="margin-top:1rem">
    <button type="submit" class="btn">Uložit pořadí</button>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
(function(){
  var list = document.getElementById('nav-list');
  if (!list) return;

  function updateHiddenInputs() {
    var items = list.querySelectorAll('[data-sort-id]');
    items.forEach(function(li){
      var input = li.querySelector('input[name="order[]"]');
      if (input) input.value = li.dataset.sortId;
    });
    // Update button states
    items.forEach(function(li, i){
      var up = li.querySelector('.nav-move-up');
      var down = li.querySelector('.nav-move-down');
      if (up) { up.disabled = i === 0; }
      if (down) { down.disabled = i === items.length - 1; }
    });
  }

  // Nahoru/Dolů tlačítka
  list.addEventListener('click', function(e){
    var btn = e.target.closest('.nav-move-up,.nav-move-down');
    if (!btn || btn.disabled) return;
    var li = btn.closest('[data-sort-id]');
    if (!li) return;
    if (btn.classList.contains('nav-move-up') && li.previousElementSibling) {
      list.insertBefore(li, li.previousElementSibling);
    } else if (btn.classList.contains('nav-move-down') && li.nextElementSibling) {
      list.insertBefore(li.nextElementSibling, li);
    }
    updateHiddenInputs();
    li.focus();
  });

  // Ctrl+šipka klávesnice
  list.addEventListener('keydown', function(e){
    if (!e.ctrlKey || !['ArrowUp','ArrowDown'].includes(e.key)) return;
    var li = e.target.closest('[data-sort-id]');
    if (!li) return;
    e.preventDefault();
    if (e.key === 'ArrowUp' && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
    else if (e.key === 'ArrowDown' && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
    updateHiddenInputs();
    li.focus();
  });

  // Drag & drop
  var dragged = null;
  list.querySelectorAll('[data-sort-id]').forEach(function(el){ el.setAttribute('draggable','true'); });
  list.addEventListener('dragstart', function(e){
    var t = e.target.closest('[data-sort-id]'); if(!t) return;
    dragged = t; t.style.opacity = '0.4';
    e.dataTransfer.effectAllowed = 'move';
  });
  list.addEventListener('dragover', function(e){
    e.preventDefault(); e.dataTransfer.dropEffect = 'move';
    var t = e.target.closest('[data-sort-id]');
    if (t && t !== dragged) {
      var r = t.getBoundingClientRect();
      if (e.clientY < r.top + r.height/2) list.insertBefore(dragged, t);
      else list.insertBefore(dragged, t.nextSibling);
    }
  });
  list.addEventListener('dragend', function(){
    if (dragged) dragged.style.opacity = '';
    dragged = null;
    updateHiddenInputs();
  });
})();
</script>

<?php adminFooter(); ?>
