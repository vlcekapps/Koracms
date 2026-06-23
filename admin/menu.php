<?php
/**
 * Správa navigace – jednotné řazení modulů, statických stránek, blogů a formulářů.
 */
require_once __DIR__ . '/layout.php';
requireCapability('settings_manage', 'Přístup odepřen.');

$pdo = db_connect();

$linkError = '';
$linkForm = [
    'id' => 0,
    'title' => '',
    'url' => '',
    'alt_text' => '',
    'target_blank' => 0,
    'is_active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'reorder');
    if ($action === 'save_link') {
        verifyCsrf();
        $linkId = inputInt('post', 'link_id') ?? 0;
        $linkForm = [
            'id' => $linkId,
            'title' => trim((string)($_POST['title'] ?? '')),
            'url' => trim((string)($_POST['url'] ?? '')),
            'alt_text' => mb_substr(trim((string)($_POST['alt_text'] ?? '')), 0, 255),
            'target_blank' => isset($_POST['target_blank']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        $safeUrl = navigationLinkUrl((string)$linkForm['url']);

        if ((string)$linkForm['title'] === '') {
            $linkError = 'Zadejte název odkazu.';
        } elseif ($safeUrl === '') {
            $linkError = 'Zadejte interní cestu webu nebo úplnou adresu začínající http:// či https:// bez přihlašovacích údajů.';
        } elseif ($linkId > 0) {
            $pdo->prepare(
                "UPDATE cms_nav_links
                 SET title = ?, url = ?, alt_text = ?, target_blank = ?, is_active = ?
                 WHERE id = ? AND blog_id IS NULL"
            )->execute([
                (string)$linkForm['title'],
                $safeUrl,
                (string)$linkForm['alt_text'],
                (int)$linkForm['target_blank'],
                (int)$linkForm['is_active'],
                $linkId,
            ]);
            logAction('nav_link_edit', 'id=' . $linkId);
            header('Location: ' . BASE_URL . '/admin/menu.php?link_saved=1');
            exit;
        } else {
            $pdo->prepare(
                "INSERT INTO cms_nav_links (blog_id, title, url, alt_text, target_blank, is_active, nav_order)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?)"
            )->execute([
                (string)$linkForm['title'],
                $safeUrl,
                (string)$linkForm['alt_text'],
                (int)$linkForm['target_blank'],
                (int)$linkForm['is_active'],
                nextNavigationLinkOrder($pdo, null),
            ]);
            logAction('nav_link_add', 'id=' . (int)$pdo->lastInsertId());
            header('Location: ' . BASE_URL . '/admin/menu.php?link_saved=1');
            exit;
        }
    } elseif ($action === 'delete_link') {
        verifyCsrf();
        $linkId = inputInt('post', 'link_id');
        if ($linkId !== null) {
            $pdo->prepare("DELETE FROM cms_nav_links WHERE id = ? AND blog_id IS NULL")->execute([$linkId]);
            $savedOrder = getSetting('nav_order_unified', '');
            if ($savedOrder !== '') {
                $filteredOrder = array_values(array_filter(
                    explode(',', $savedOrder),
                    static fn (string $key): bool => $key !== 'link:' . $linkId
                ));
                saveSetting('nav_order_unified', implode(',', $filteredOrder));
            }
            logAction('nav_link_delete', 'id=' . $linkId);
        }
        header('Location: ' . BASE_URL . '/admin/menu.php?link_deleted=1');
        exit;
    }
}

$editLinkId = inputInt('get', 'edit_link');
if ($linkError === '' && $editLinkId !== null) {
    $editLinkStmt = $pdo->prepare(
        "SELECT id, title, url, alt_text, target_blank, is_active
         FROM cms_nav_links
         WHERE id = ? AND blog_id IS NULL"
    );
    $editLinkStmt->execute([$editLinkId]);
    $editLink = $editLinkStmt->fetch();
    if ($editLink) {
        $linkForm = [
            'id' => (int)$editLink['id'],
            'title' => (string)$editLink['title'],
            'url' => (string)$editLink['url'],
            'alt_text' => (string)($editLink['alt_text'] ?? ''),
            'target_blank' => (int)($editLink['target_blank'] ?? 0),
            'is_active' => (int)($editLink['is_active'] ?? 1),
        ];
    }
}

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
     WHERE blog_id IS NULL AND deleted_at IS NULL
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
    $enabled = formVisibleInPublicNavigation($formRow);
    $navItems[] = [
        'key' => 'form:' . $formId,
        'type' => 'form',
        'id' => $formId,
        'label' => (string)$formRow['title'],
        'sublabel' => implode(' · ', formPublicNavigationStatusParts($formRow)),
        'enabled' => $enabled,
        'manage_url' => BASE_URL . '/admin/form_form.php?id=' . $formId,
        'manage_label' => 'Upravit formulář',
    ];
}

$navigationLinks = loadNavigationLinks($pdo, null, false);
foreach ($navigationLinks as $linkRow) {
    $linkId = (int)$linkRow['id'];
    $safeUrl = navigationLinkHref($linkRow);
    $enabled = (int)($linkRow['is_active'] ?? 0) === 1 && $safeUrl !== '';
    $navItems[] = [
        'key' => 'link:' . $linkId,
        'type' => 'link',
        'id' => $linkId,
        'label' => (string)$linkRow['title'],
        'sublabel' => 'Externí odkaz'
            . ((int)($linkRow['is_active'] ?? 0) !== 1 ? ' · vypnutý' : '')
            . ($safeUrl === '' ? ' · neplatná adresa' : '')
            . ((int)($linkRow['target_blank'] ?? 0) === 1 ? ' · nové okno' : ''),
        'enabled' => $enabled,
        'manage_url' => BASE_URL . '/admin/menu.php?edit_link=' . $linkId . '#nav-link-form',
        'manage_label' => 'Upravit odkaz',
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? 'reorder') === 'reorder') {
    verifyCsrf();
    $order = (array)($_POST['order'] ?? []);
    $order = array_values(array_filter($order, static fn ($value) => is_string($value) && $value !== ''));

    $validKeys = array_map(static fn (array $item): string => (string)$item['key'], $navItems);
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
<?php if (isset($_GET['link_saved'])): ?>
  <p class="success" role="status">Externí odkaz byl uložen.</p>
<?php endif; ?>
<?php if (isset($_GET['link_deleted'])): ?>
  <p class="success" role="status">Externí odkaz byl smazán.</p>
<?php endif; ?>
<?php if (isset($_GET['page_positions'])): ?>
  <p class="success" role="status">Pořadí statických stránek se nyní spravuje tady společně s moduly a blogy.</p>
<?php endif; ?>

<p class="admin-description">Tady určujete skutečné pořadí hlavní navigace webu napříč moduly, blogy, formuláři, externími odkazy a statickými stránkami. Přetahujte myší nebo použijte tlačítka Nahoru/Dolů. Položky označené jako mimo navigaci nebo nezveřejněné tu zůstávají kvůli přehledu, ale návštěvníkům se teď nezobrazí.</p>

<form method="post" id="nav-link-form" class="form-card" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="action" value="save_link">
  <input type="hidden" name="link_id" value="<?= (int)$linkForm['id'] ?>">
  <fieldset>
    <legend><?= (int)$linkForm['id'] > 0 ? 'Upravit externí odkaz' : 'Přidat externí odkaz' ?></legend>
    <?php if ($linkError !== ''): ?>
      <p class="error" role="alert"><?= h($linkError) ?></p>
    <?php endif; ?>

    <label for="nav-link-title">Název odkazu <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="nav-link-title" name="title" value="<?= h((string)$linkForm['title']) ?>" required aria-required="true" maxlength="255">

    <label for="nav-link-url">Adresa odkazu <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="url" id="nav-link-url" name="url" value="<?= h((string)$linkForm['url']) ?>" required aria-required="true" maxlength="1000" aria-describedby="nav-link-url-help">
    <small id="nav-link-url-help" class="field-help">Použijte úplnou adresu začínající <code>https://</code> nebo interní cestu webu, například <code>/kontakt</code>.</small>

    <label for="nav-link-alt">Popis pro čtečky obrazovky</label>
    <input type="text" id="nav-link-alt" name="alt_text" value="<?= h((string)$linkForm['alt_text']) ?>" maxlength="255" aria-describedby="nav-link-alt-help">
    <small id="nav-link-alt-help" class="field-help">Volitelné. Použije se jako přístupný popis odkazu, ne jako HTML <code>alt</code> atribut.</small>

    <label><input type="checkbox" name="target_blank" value="1"<?= (int)$linkForm['target_blank'] === 1 ? ' checked' : '' ?>> Otevřít v novém okně</label>
    <label><input type="checkbox" name="is_active" value="1"<?= (int)$linkForm['is_active'] === 1 ? ' checked' : '' ?>> Zobrazit odkaz v navigaci</label>

    <div class="button-row admin-action-row">
      <button type="submit" class="btn"><?= (int)$linkForm['id'] > 0 ? 'Uložit odkaz' : 'Přidat odkaz' ?></button>
      <?php if ((int)$linkForm['id'] > 0): ?>
        <a href="<?= BASE_URL ?>/admin/menu.php#nav-link-form" class="button-secondary">Zrušit úpravu</a>
      <?php endif; ?>
    </div>
  </fieldset>
</form>

<?php if ((int)$linkForm['id'] > 0): ?>
  <form method="post" class="admin-inline-form" novalidate data-confirm="Opravdu smazat tento externí odkaz?">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="delete_link">
    <input type="hidden" name="link_id" value="<?= (int)$linkForm['id'] ?>">
    <button type="submit" class="btn btn-danger">Smazat externí odkaz</button>
  </form>
<?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="action" value="reorder">
  <p id="nav-order-help" class="field-help field-help--flush">Potřebujete změnit stav položky? U stránek použijte „Upravit stránku“, u formulářů „Upravit formulář“, u blogů „Správa blogů“ a u modulů „Správa modulů“.</p>
  <p id="nav-order-status" class="visually-hidden" role="status" aria-live="polite"></p>
  <ol class="admin-sort-list" id="nav-list" data-sortable="nav_unified">
    <?php $total = count($navItems); ?>
    <?php foreach ($navItems as $index => $item): ?>
      <li class="admin-sort-item<?= !$item['enabled'] ? ' admin-sort-item--muted' : '' ?>"
          data-sort-id="<?= h($item['key']) ?>"
          tabindex="0"
          aria-describedby="nav-order-help">
        <input type="hidden" name="order[]" value="<?= h($item['key']) ?>">
        <div class="admin-sort-item__body">
          <strong><?= h($item['label']) ?></strong>
          <br><small class="table-meta"><?= h($item['sublabel']) ?><?= !$item['enabled'] ? ' · na webu se teď nezobrazí' : '' ?></small>
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

  <div class="button-row admin-action-row">
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
    t.classList.add('admin-sort-item--dragging');
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
      dragged.classList.remove('admin-sort-item--dragging');
      updateHiddenInputs();
      announce(dragged.querySelector('strong').textContent + ' přesunuto na pozici ' + itemPosition(dragged) + '.');
    }
    dragged = null;
  });

  updateHiddenInputs();
})();
</script>

<?php adminFooter(); ?>
