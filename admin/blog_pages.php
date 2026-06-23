<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu blogových stránek nemáte potřebné oprávnění.');

$pdo = db_connect();
$blogId = inputInt('get', 'blog_id');
$blog = $blogId !== null ? getBlogById($blogId) : null;
if (!$blog) {
    header('Location: ' . BASE_URL . '/admin/blogs.php');
    exit;
}

$returnUrl = internalRedirectTarget(
    trim((string)($_REQUEST['redirect'] ?? '')),
    BASE_URL . '/admin/blog_pages.php?blog_id=' . (int)$blog['id']
);

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
                 WHERE id = ? AND blog_id = ?"
            )->execute([
                (string)$linkForm['title'],
                $safeUrl,
                (string)$linkForm['alt_text'],
                (int)$linkForm['target_blank'],
                (int)$linkForm['is_active'],
                $linkId,
                (int)$blog['id'],
            ]);
            logAction('blog_nav_link_edit', 'blog_id=' . (int)$blog['id'] . ', id=' . $linkId);
            header('Location: ' . BASE_URL . '/admin/blog_pages.php?blog_id=' . (int)$blog['id'] . '&link_saved=1');
            exit;
        } else {
            $pdo->prepare(
                "INSERT INTO cms_nav_links (blog_id, title, url, alt_text, target_blank, is_active, nav_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                (int)$blog['id'],
                (string)$linkForm['title'],
                $safeUrl,
                (string)$linkForm['alt_text'],
                (int)$linkForm['target_blank'],
                (int)$linkForm['is_active'],
                nextNavigationLinkOrder($pdo, (int)$blog['id']),
            ]);
            logAction('blog_nav_link_add', 'blog_id=' . (int)$blog['id'] . ', id=' . (int)$pdo->lastInsertId());
            header('Location: ' . BASE_URL . '/admin/blog_pages.php?blog_id=' . (int)$blog['id'] . '&link_saved=1');
            exit;
        }
    } elseif ($action === 'delete_link') {
        verifyCsrf();
        $linkId = inputInt('post', 'link_id');
        if ($linkId !== null) {
            $pdo->prepare("DELETE FROM cms_nav_links WHERE id = ? AND blog_id = ?")->execute([$linkId, (int)$blog['id']]);
            logAction('blog_nav_link_delete', 'blog_id=' . (int)$blog['id'] . ', id=' . $linkId);
        }
        header('Location: ' . BASE_URL . '/admin/blog_pages.php?blog_id=' . (int)$blog['id'] . '&link_deleted=1');
        exit;
    }
}

$editLinkId = inputInt('get', 'edit_link');
if ($linkError === '' && $editLinkId !== null) {
    $editLinkStmt = $pdo->prepare(
        "SELECT id, title, url, alt_text, target_blank, is_active
         FROM cms_nav_links
         WHERE id = ? AND blog_id = ?"
    );
    $editLinkStmt->execute([$editLinkId, (int)$blog['id']]);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? 'reorder') === 'reorder') {
    verifyCsrf();

    $submittedOrder = array_values(array_filter(
        (array)($_POST['order'] ?? []),
        static fn ($value): bool => is_scalar($value) && (string)$value !== ''
    ));

    $stmt = $pdo->prepare(
        "SELECT id
         FROM cms_pages
         WHERE blog_id = ? AND deleted_at IS NULL
         ORDER BY blog_nav_order, title, id"
    );
    $stmt->execute([(int)$blog['id']]);
    $validKeys = array_map(static fn (array $row): string => 'page:' . (int)$row['id'], $stmt->fetchAll());
    foreach (loadNavigationLinks($pdo, (int)$blog['id'], false) as $linkRow) {
        $validKeys[] = 'link:' . (int)$linkRow['id'];
    }
    $validLookup = array_fill_keys($validKeys, true);

    $normalizedOrder = [];
    $seenKeys = [];
    foreach ($submittedOrder as $submittedKey) {
        $key = (string)$submittedKey;
        if (!isset($validLookup[$key]) || isset($seenKeys[$key])) {
            continue;
        }
        $normalizedOrder[] = $key;
        $seenKeys[$key] = true;
    }
    foreach ($validKeys as $validKey) {
        if (!isset($seenKeys[$validKey])) {
            $normalizedOrder[] = $validKey;
        }
    }

    $update = $pdo->prepare("UPDATE cms_pages SET blog_nav_order = ? WHERE id = ? AND blog_id = ?");
    $updateLink = $pdo->prepare("UPDATE cms_nav_links SET nav_order = ? WHERE id = ? AND blog_id = ?");
    foreach ($normalizedOrder as $position => $key) {
        [$type, $rawId] = explode(':', $key, 2);
        $itemId = (int)$rawId;
        if ($type === 'page') {
            $update->execute([$position + 1, $itemId, (int)$blog['id']]);
        } elseif ($type === 'link') {
            $updateLink->execute([$position + 1, $itemId, (int)$blog['id']]);
        }
    }

    logAction('blog_page_reorder', 'blog_id=' . (int)$blog['id'] . ', order=' . implode(',', $normalizedOrder));

    header('Location: ' . BASE_URL . '/admin/blog_pages.php?blog_id=' . (int)$blog['id'] . '&saved=1');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, title, slug, blog_nav_order, is_published, COALESCE(status, 'published') AS status
     FROM cms_pages
     WHERE blog_id = ? AND deleted_at IS NULL
     ORDER BY blog_nav_order, title, id"
);
$stmt->execute([(int)$blog['id']]);
$pages = $stmt->fetchAll();
$links = loadNavigationLinks($pdo, (int)$blog['id'], false);
$items = [];
foreach ($pages as $page) {
    $items[] = [
        'type' => 'page',
        'key' => 'page:' . (int)$page['id'],
        'sort_order' => (int)($page['blog_nav_order'] ?? 0),
        'title' => (string)$page['title'],
        'row' => $page,
    ];
}
foreach ($links as $link) {
    $items[] = [
        'type' => 'link',
        'key' => 'link:' . (int)$link['id'],
        'sort_order' => (int)($link['nav_order'] ?? 0),
        'title' => (string)$link['title'],
        'row' => $link,
    ];
}
usort($items, static function (array $left, array $right): int {
    $leftOrder = (int)$left['sort_order'];
    $rightOrder = (int)$right['sort_order'];
    if ($leftOrder !== $rightOrder) {
        return $leftOrder <=> $rightOrder;
    }

    return strcasecmp((string)$left['title'], (string)$right['title']);
});

adminHeader('Pořadí stránek blogu');
?>

<?php if (isset($_GET['saved'])): ?>
  <p class="success" role="status">Pořadí blogových stránek bylo uloženo.</p>
<?php endif; ?>
<?php if (isset($_GET['link_saved'])): ?>
  <p class="success" role="status">Externí odkaz blogu byl uložen.</p>
<?php endif; ?>
<?php if (isset($_GET['link_deleted'])): ?>
  <p class="success" role="status">Externí odkaz blogu byl smazán.</p>
<?php endif; ?>

<p class="button-row button-row--start">
  <a href="<?= BASE_URL ?>/admin/blogs.php"><span aria-hidden="true">←</span> Zpět na blogy</a>
  <a href="<?= BASE_URL ?>/admin/pages.php">Všechny statické stránky</a>
  <a href="<?= BASE_URL ?>/admin/page_form.php?blog_id=<?= (int)$blog['id'] ?>&amp;redirect=<?= rawurlencode($returnUrl) ?>" class="btn">+ Nová stránka blogu</a>
</p>

<p class="admin-description">Tady určujete pořadí statických stránek a externích odkazů blogu <strong><?= h((string)$blog['name']) ?></strong>. Toto pořadí je oddělené od globální hlavní navigace webu.</p>

<form method="post" id="blog-nav-link-form" class="form-card" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="action" value="save_link">
  <input type="hidden" name="link_id" value="<?= (int)$linkForm['id'] ?>">
  <fieldset>
    <legend><?= (int)$linkForm['id'] > 0 ? 'Upravit externí odkaz blogu' : 'Přidat externí odkaz blogu' ?></legend>
    <?php if ($linkError !== ''): ?>
      <p class="error" role="alert"><?= h($linkError) ?></p>
    <?php endif; ?>

    <label for="blog-nav-link-title">Název odkazu <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="blog-nav-link-title" name="title" value="<?= h((string)$linkForm['title']) ?>" required aria-required="true" maxlength="255">

    <label for="blog-nav-link-url">Adresa odkazu <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="url" id="blog-nav-link-url" name="url" value="<?= h((string)$linkForm['url']) ?>" required aria-required="true" maxlength="1000" aria-describedby="blog-nav-link-url-help">
    <small id="blog-nav-link-url-help" class="field-help">Použijte úplnou adresu začínající <code>https://</code> nebo interní cestu webu, například <code>/kontakt</code>.</small>

    <label for="blog-nav-link-alt">Popis pro čtečky obrazovky</label>
    <input type="text" id="blog-nav-link-alt" name="alt_text" value="<?= h((string)$linkForm['alt_text']) ?>" maxlength="255" aria-describedby="blog-nav-link-alt-help">
    <small id="blog-nav-link-alt-help" class="field-help">Volitelné. Použije se jako přístupný popis odkazu, ne jako HTML <code>alt</code> atribut.</small>

    <label><input type="checkbox" name="target_blank" value="1"<?= (int)$linkForm['target_blank'] === 1 ? ' checked' : '' ?>> Otevřít v novém okně</label>
    <label><input type="checkbox" name="is_active" value="1"<?= (int)$linkForm['is_active'] === 1 ? ' checked' : '' ?>> Zobrazit odkaz v blogu</label>

    <div class="button-row admin-action-row">
      <button type="submit" class="btn"><?= (int)$linkForm['id'] > 0 ? 'Uložit odkaz' : 'Přidat odkaz' ?></button>
      <?php if ((int)$linkForm['id'] > 0): ?>
        <a href="<?= BASE_URL ?>/admin/blog_pages.php?blog_id=<?= (int)$blog['id'] ?>#blog-nav-link-form" class="button-secondary">Zrušit úpravu</a>
      <?php endif; ?>
    </div>
  </fieldset>
</form>

<?php if ((int)$linkForm['id'] > 0): ?>
  <form method="post" class="admin-inline-form" novalidate data-confirm="Opravdu smazat tento externí odkaz blogu?">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="delete_link">
    <input type="hidden" name="link_id" value="<?= (int)$linkForm['id'] ?>">
    <button type="submit" class="btn btn-danger">Smazat externí odkaz blogu</button>
  </form>
<?php endif; ?>

<?php if ($items === []): ?>
  <p>Blog zatím nemá žádné vlastní statické stránky ani externí odkazy. <a href="<?= BASE_URL ?>/admin/page_form.php?blog_id=<?= (int)$blog['id'] ?>&amp;redirect=<?= rawurlencode($returnUrl) ?>">Vytvořit první stránku blogu</a>.</p>
<?php else: ?>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="reorder">
    <input type="hidden" name="redirect" value="<?= h($returnUrl) ?>">
    <p id="blog-page-order-help" class="field-help field-help--flush">Přetahujte myší nebo použijte tlačítka Nahoru/Dolů. U skrytých, vypnutých či neschválených položek zůstává pořadí zachované, ale na webu se teď nezobrazí.</p>
    <p id="blog-page-order-status" class="visually-hidden" role="status" aria-live="polite"></p>

    <ol id="blog-page-list" class="admin-sort-list">
      <?php $total = count($items); ?>
      <?php foreach ($items as $index => $item): ?>
        <?php $row = $item['row']; ?>
        <?php if ($item['type'] === 'page'): ?>
          <?php $publicPath = ((int)$row['is_published'] === 1 && (string)$row['status'] === 'published') ? pagePublicPath($row + ['blog_id' => $blog['id'], 'blog_slug' => $blog['slug']]) : ''; ?>
          <?php $isMuted = (int)$row['is_published'] !== 1 || (string)$row['status'] !== 'published'; ?>
        <?php else: ?>
          <?php $publicPath = navigationLinkHref($row); ?>
          <?php $isMuted = (int)($row['is_active'] ?? 0) !== 1 || $publicPath === ''; ?>
        <?php endif; ?>
        <li class="admin-sort-item<?= $isMuted ? ' admin-sort-item--muted' : '' ?>"
            data-sort-id="<?= h((string)$item['key']) ?>"
            tabindex="0"
            aria-describedby="blog-page-order-help">
          <input type="hidden" name="order[]" value="<?= h((string)$item['key']) ?>">
          <div class="admin-sort-item__body">
            <strong><?= h((string)$item['title']) ?></strong>
            <?php if ($item['type'] === 'page'): ?>
              <br><small class="table-meta">Statická stránka · <?= h((string)$row['slug']) ?></small>
              <?php if ((int)$row['is_published'] !== 1): ?>
                <br><small class="table-meta">Nezveřejněná stránka</small>
              <?php elseif ((string)$row['status'] !== 'published'): ?>
                <br><small class="table-meta">Čeká na schválení</small>
              <?php endif; ?>
            <?php else: ?>
              <br><small class="table-meta">Externí odkaz · <?= h((string)$row['url']) ?></small>
              <?php if ((int)($row['is_active'] ?? 0) !== 1): ?>
                <br><small class="table-meta">Vypnutý odkaz</small>
              <?php elseif ($publicPath === ''): ?>
                <br><small class="table-meta">Neplatná adresa odkazu</small>
              <?php endif; ?>
              <?php if ((int)($row['target_blank'] ?? 0) === 1): ?>
                <br><small class="table-meta">Otevírá se v novém okně</small>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <button type="button" class="btn blog-page-move-up"<?= $index === 0 ? ' disabled aria-disabled="true"' : ' aria-disabled="false"' ?> aria-label="Posunout <?= h((string)$item['title']) ?> nahoru">
            <span aria-hidden="true">↑</span> Nahoru
          </button>
          <button type="button" class="btn blog-page-move-down"<?= $index === $total - 1 ? ' disabled aria-disabled="true"' : ' aria-disabled="false"' ?> aria-label="Posunout <?= h((string)$item['title']) ?> dolů">
            <span aria-hidden="true">↓</span> Dolů
          </button>
          <?php if ($item['type'] === 'page'): ?>
            <a href="<?= BASE_URL ?>/admin/page_form.php?id=<?= (int)$row['id'] ?>&amp;redirect=<?= rawurlencode($returnUrl) ?>" class="btn">Upravit stránku</a>
          <?php else: ?>
            <a href="<?= BASE_URL ?>/admin/blog_pages.php?blog_id=<?= (int)$blog['id'] ?>&amp;edit_link=<?= (int)$row['id'] ?>#blog-nav-link-form" class="btn">Upravit odkaz</a>
          <?php endif; ?>
          <?php if ($publicPath !== ''): ?>
            <a href="<?= h($publicPath) ?>" target="_blank" rel="noopener" aria-label="<?= h(newWindowLinkLabel('Zobrazit na webu')) ?>">Zobrazit na webu</a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>

    <div class="admin-action-row">
      <button type="submit" class="btn">Uložit pořadí</button>
    </div>
  </form>

  <script nonce="<?= cspNonce() ?>">
  (function () {
      var list = document.getElementById('blog-page-list');
      var status = document.getElementById('blog-page-order-status');
      if (!list) {
          return;
      }

      function announce(message) {
          if (!status) {
              return;
          }
          status.textContent = '';
          window.setTimeout(function () {
              status.textContent = message;
          }, 20);
      }

      function itemPosition(item) {
          return Array.prototype.indexOf.call(list.querySelectorAll('[data-sort-id]'), item) + 1;
      }

      function updateHiddenInputs() {
          var items = list.querySelectorAll('[data-sort-id]');
          items.forEach(function (item) {
              var input = item.querySelector('input[name="order[]"]');
              if (input) {
                  input.value = item.dataset.sortId;
              }
          });
          items.forEach(function (item, index) {
              var up = item.querySelector('.blog-page-move-up');
              var down = item.querySelector('.blog-page-move-down');
              if (up) {
                  up.disabled = index === 0;
                  up.setAttribute('aria-disabled', index === 0 ? 'true' : 'false');
              }
              if (down) {
                  down.disabled = index === items.length - 1;
                  down.setAttribute('aria-disabled', index === items.length - 1 ? 'true' : 'false');
              }
          });
      }

      list.addEventListener('click', function (event) {
          var button = event.target.closest('.blog-page-move-up,.blog-page-move-down');
          if (!button || button.disabled) {
              return;
          }
          var item = button.closest('[data-sort-id]');
          if (!item) {
              return;
          }
          if (button.classList.contains('blog-page-move-up') && item.previousElementSibling) {
              list.insertBefore(item, item.previousElementSibling);
          } else if (button.classList.contains('blog-page-move-down') && item.nextElementSibling) {
              list.insertBefore(item.nextElementSibling, item);
          }
          updateHiddenInputs();
          announce(item.querySelector('strong').textContent + ' přesunuto na pozici ' + itemPosition(item) + '.');
          item.focus();
      });

      list.addEventListener('keydown', function (event) {
          if (!event.ctrlKey || !['ArrowUp', 'ArrowDown'].includes(event.key)) {
              return;
          }
          var item = event.target.closest('[data-sort-id]');
          if (!item) {
              return;
          }
          event.preventDefault();
          if (event.key === 'ArrowUp' && item.previousElementSibling) {
              list.insertBefore(item, item.previousElementSibling);
          } else if (event.key === 'ArrowDown' && item.nextElementSibling) {
              list.insertBefore(item.nextElementSibling, item);
          }
          updateHiddenInputs();
          announce(item.querySelector('strong').textContent + ' přesunuto na pozici ' + itemPosition(item) + '.');
          item.focus();
      });

      var dragged = null;
      list.querySelectorAll('[data-sort-id]').forEach(function (item) {
          item.setAttribute('draggable', 'true');
      });
      list.addEventListener('dragstart', function (event) {
          var item = event.target.closest('[data-sort-id]');
          if (!item) {
              return;
          }
          dragged = item;
          item.classList.add('admin-sort-item--dragging');
          event.dataTransfer.effectAllowed = 'move';
      });
      list.addEventListener('dragover', function (event) {
          event.preventDefault();
          event.dataTransfer.dropEffect = 'move';
          var item = event.target.closest('[data-sort-id]');
          if (!item || item === dragged) {
              return;
          }
          var rect = item.getBoundingClientRect();
          if (event.clientY < rect.top + rect.height / 2) {
              list.insertBefore(dragged, item);
          } else {
              list.insertBefore(dragged, item.nextSibling);
          }
      });
      list.addEventListener('dragend', function () {
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
<?php endif; ?>

<?php adminFooter(); ?>
