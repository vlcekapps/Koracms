<?php
/**
 * Správa navigace – jednotné řazení modulů, statických stránek, blogů a formulářů.
 */
require_once __DIR__ . '/layout.php';
requireCapability('settings_manage', 'Přístup odepřen.');

$pdo = db_connect();

$navItems = [];

$moduleMap = navModuleDefaults();
foreach (array_keys($moduleMap) as $mKey) {
    if ($mKey === 'blog') {
        foreach (getAllBlogs() as $blogEntry) {
            $blogId = (int)($blogEntry['id'] ?? 0);
            $blogNav = (int)($blogEntry['show_in_nav'] ?? 1) === 1;
            $navItems[] = [
                'key' => 'blog:' . $blogId,
                'type' => 'blog',
                'id' => $blogId,
                'label' => (string)$blogEntry['name'],
                'sublabel' => 'Blog' . (!$blogNav ? ' · mimo navigaci' : ''),
                'enabled' => isModuleEnabled('blog') && $blogNav,
                'manage_url' => BASE_URL . '/admin/blogs.php',
                'manage_label' => 'Správa blogů',
            ];
        }
        continue;
    }

    $navItems[] = [
        'key' => 'module:' . $mKey,
        'type' => 'module',
        'id' => $mKey,
        'label' => $moduleMap[$mKey][1],
        'sublabel' => 'Modul',
        'enabled' => isModuleEnabled($mKey),
        'manage_url' => BASE_URL . '/admin/settings_modules.php',
        'manage_label' => 'Správa modulů',
    ];
}

$pages = $pdo->query(
    "SELECT id, title, slug, show_in_nav, is_published, COALESCE(status,'published') AS status
     FROM cms_pages
     WHERE deleted_at IS NULL
     ORDER BY nav_order, title, id"
)->fetchAll();
foreach ($pages as $pageRow) {
    $pageId = (int)$pageRow['id'];
    $enabled = (int)$pageRow['show_in_nav'] === 1
        && (int)$pageRow['is_published'] === 1
        && ($pageRow['status'] ?? 'published') === 'published';
    $navItems[] = [
        'key' => 'page:' . $pageId,
        'type' => 'page',
        'id' => $pageId,
        'label' => (string)$pageRow['title'],
        'sublabel' => 'Stránka'
            . ((int)$pageRow['show_in_nav'] !== 1 ? ' · mimo navigaci' : '')
            . ((int)$pageRow['is_published'] !== 1 ? ' · nezveřejněná' : '')
            . (($pageRow['status'] ?? 'published') === 'pending' ? ' · čeká na schválení' : ''),
        'enabled' => $enabled,
        'manage_url' => BASE_URL . '/admin/page_form.php?id=' . $pageId
            . '&redirect=' . rawurlencode(BASE_URL . '/admin/menu.php'),
        'manage_label' => 'Upravit stránku',
    ];
}

$forms = $pdo->query(
    "SELECT id, title, slug, show_in_nav, is_active
     FROM cms_forms
     ORDER BY title, id"
)->fetchAll();
foreach ($forms as $formRow) {
    $formId = (int)$formRow['id'];
    $enabled = isModuleEnabled('forms')
        && (int)$formRow['is_active'] === 1
        && (int)$formRow['show_in_nav'] === 1;
    $navItems[] = [
        'key' => 'form:' . $formId,
        'type' => 'form',
        'id' => $formId,
        'label' => (string)$formRow['title'],
        'sublabel' => 'Formulář'
            . ((int)$formRow['show_in_nav'] !== 1 ? ' · mimo navigaci' : '')
            . ((int)$formRow['is_active'] !== 1 ? ' · nezveřejněný' : '')
            . (!isModuleEnabled('forms') ? ' · modul vypnutý' : ''),
        'enabled' => $enabled,
        'manage_url' => BASE_URL . '/admin/form_form.php?id=' . $formId,
        'manage_label' => 'Upravit formulář',
    ];
}

$savedOrder = getSetting('nav_order_unified', '');
if ($savedOrder !== '') {
    $orderedKeys = explode(',', $savedOrder);
    $itemsByKey = [];
    foreach ($navItems as $item) {
        $itemsByKey[$item['key']] = $item;
    }
    $sorted = [];
    foreach ($orderedKeys as $key) {
        if (!isset($itemsByKey[$key])) {
            continue;
        }
        $sorted[] = $itemsByKey[$key];
        unset($itemsByKey[$key]);
    }
    foreach ($itemsByKey as $item) {
        $sorted[] = $item;
    }
    $navItems = $sorted;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $order = (array)($_POST['order'] ?? []);
    $order = array_values(array_filter($order, static fn($value) => is_string($value) && $value !== ''));

    $validKeys = array_map(static fn(array $item): string => (string)$item['key'], $navItems);
    $validLookup = array_fill_keys($validKeys, true);
    $normalizedOrder = [];
    $seenKeys = [];

    foreach ($order as $key) {
        if (!isset($validLookup[$key]) || isset($seenKeys[$key])) {
            continue;
        }
        $normalizedOrder[] = $key;
        $seenKeys[$key] = true;
    }
    foreach ($validKeys as $key) {
        if (isset($seenKeys[$key])) {
            continue;
        }
        $normalizedOrder[] = $key;
    }

    saveSetting('nav_order_unified', implode(',', $normalizedOrder));
    logAction('nav_reorder_unified', 'order=' . implode(',', $normalizedOrder));
    header('Location: ' . BASE_URL . '/admin/menu.php?saved=1');
    exit;
}

adminHeader('Navigace webu');
?>

<?php if (isset($_GET['saved'])): ?>
  <p class="success" role="status">Pořadí navigace bylo uloženo.</p>
<?php endif; ?>
<?php if (isset($_GET['page_positions'])): ?>
  <p class="success" role="status">Pořadí statických stránek se nyní spravuje tady společně s moduly a blogy.</p>
<?php endif; ?>

<p style="font-size:.9rem">Tady určujete skutečné pořadí hlavní navigace webu napříč moduly, blogy, formuláři a statickými stránkami. Přetahujte myší nebo použijte tlačítka Nahoru/Dolů. Položky označené jako mimo navigaci nebo nezveřejněné tu zůstávají kvůli přehledu, ale návštěvníkům se teď nezobrazí.</p>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <p id="nav-order-help" class="field-help" style="margin-top:0">Potřebujete změnit stav položky? U stránek použijte „Upravit stránku“, u formulářů „Upravit formulář“, u blogů „Správa blogů“ a u modulů „Správa modulů“.</p>
  <p id="nav-order-status" class="visually-hidden" role="status" aria-live="polite"></p>
  <ol style="list-style:none;padding:0;margin:0;max-width:62rem" id="nav-list" data-sortable="nav_unified">
    <?php $total = count($navItems); ?>
    <?php foreach ($navItems as $index => $item): ?>
      <li style="display:flex;align-items:center;gap:.75rem;padding:.55rem 0;border-bottom:1px solid #eee;flex-wrap:wrap;cursor:grab<?= !$item['enabled'] ? ';opacity:.5' : '' ?>"
          data-sort-id="<?= h($item['key']) ?>"
          tabindex="0"
          aria-describedby="nav-order-help">
        <input type="hidden" name="order[]" value="<?= h($item['key']) ?>">
        <div style="min-width:14rem;flex:1 1 16rem">
          <strong><?= h($item['label']) ?></strong>
          <br><small style="color:#555"><?= h($item['sublabel']) ?><?= !$item['enabled'] ? ' · na webu se teď nezobrazí' : '' ?></small>
        </div>

        <button type="button" class="btn nav-move-up"
                <?= $index === 0 ? 'disabled aria-disabled="true"' : 'aria-disabled="false"' ?>
                aria-label="Posunout <?= h($item['label']) ?> nahoru">
          <span aria-hidden="true">↑</span> Nahoru
        </button>
        <button type="button" class="btn nav-move-down"
                <?= $index === $total - 1 ? 'disabled aria-disabled="true"' : 'aria-disabled="false"' ?>
                aria-label="Posunout <?= h($item['label']) ?> dolů">
          <span aria-hidden="true">↓</span> Dolů
        </button>
        <a href="<?= h($item['manage_url']) ?>" class="btn"><?= h($item['manage_label']) ?></a>
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
  var status = document.getElementById('nav-order-status');
  if (!list) return;

  function announce(message) {
    if (!status) return;
    status.textContent = '';
    window.setTimeout(function(){
      status.textContent = message;
    }, 20);
  }

  function itemPosition(item) {
    return Array.prototype.indexOf.call(list.querySelectorAll('[data-sort-id]'), item) + 1;
  }

  function updateHiddenInputs() {
    var items = list.querySelectorAll('[data-sort-id]');
    items.forEach(function(li){
      var input = li.querySelector('input[name="order[]"]');
      if (input) input.value = li.dataset.sortId;
    });
    items.forEach(function(li, i){
      var up = li.querySelector('.nav-move-up');
      var down = li.querySelector('.nav-move-down');
      if (up) {
        up.disabled = i === 0;
        up.setAttribute('aria-disabled', i === 0 ? 'true' : 'false');
      }
      if (down) {
        down.disabled = i === items.length - 1;
        down.setAttribute('aria-disabled', i === items.length - 1 ? 'true' : 'false');
      }
    });
  }

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
    announce(li.querySelector('strong').textContent + ' přesunuto na pozici ' + itemPosition(li) + '.');
    li.focus();
  });

  list.addEventListener('keydown', function(e){
    if (!e.ctrlKey || !['ArrowUp','ArrowDown'].includes(e.key)) return;
    var li = e.target.closest('[data-sort-id]');
    if (!li) return;
    e.preventDefault();
    if (e.key === 'ArrowUp' && li.previousElementSibling) {
      list.insertBefore(li, li.previousElementSibling);
    } else if (e.key === 'ArrowDown' && li.nextElementSibling) {
      list.insertBefore(li.nextElementSibling, li);
    }
    updateHiddenInputs();
    announce(li.querySelector('strong').textContent + ' přesunuto na pozici ' + itemPosition(li) + '.');
    li.focus();
  });

  var dragged = null;
  list.querySelectorAll('[data-sort-id]').forEach(function(el){ el.setAttribute('draggable','true'); });
  list.addEventListener('dragstart', function(e){
    var t = e.target.closest('[data-sort-id]');
    if (!t) return;
    dragged = t;
    t.style.opacity = '0.4';
    e.dataTransfer.effectAllowed = 'move';
  });
  list.addEventListener('dragover', function(e){
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    var t = e.target.closest('[data-sort-id]');
    if (!t || t === dragged) return;
    var r = t.getBoundingClientRect();
    if (e.clientY < r.top + r.height / 2) {
      list.insertBefore(dragged, t);
    } else {
      list.insertBefore(dragged, t.nextSibling);
    }
  });
  list.addEventListener('dragend', function(){
    if (dragged) {
      dragged.style.opacity = '';
      updateHiddenInputs();
      announce(dragged.querySelector('strong').textContent + ' přesunuto na pozici ' + itemPosition(dragged) + '.');
    }
    dragged = null;
  });

  updateHiddenInputs();
})();
</script>

<?php adminFooter(); ?>
