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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $submittedOrder = array_values(array_filter(
        (array)($_POST['order'] ?? []),
        static fn($value): bool => is_scalar($value) && (string)$value !== ''
    ));

    $stmt = $pdo->prepare(
        "SELECT id
         FROM cms_pages
         WHERE blog_id = ? AND deleted_at IS NULL
         ORDER BY blog_nav_order, title, id"
    );
    $stmt->execute([(int)$blog['id']]);
    $validIds = array_map(static fn(array $row): int => (int)$row['id'], $stmt->fetchAll());
    $validLookup = array_fill_keys($validIds, true);

    $normalizedOrder = [];
    $seenIds = [];
    foreach ($submittedOrder as $submittedId) {
        $pageId = (int)$submittedId;
        if ($pageId <= 0 || !isset($validLookup[$pageId]) || isset($seenIds[$pageId])) {
            continue;
        }
        $normalizedOrder[] = $pageId;
        $seenIds[$pageId] = true;
    }
    foreach ($validIds as $validId) {
        if (!isset($seenIds[$validId])) {
            $normalizedOrder[] = $validId;
        }
    }

    $update = $pdo->prepare("UPDATE cms_pages SET blog_nav_order = ? WHERE id = ? AND blog_id = ?");
    foreach ($normalizedOrder as $position => $pageId) {
        $update->execute([$position + 1, $pageId, (int)$blog['id']]);
    }
    normalizeBlogPageNavigationOrder($pdo, (int)$blog['id']);

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

adminHeader('Pořadí stránek blogu');
?>

<?php if (isset($_GET['saved'])): ?>
  <p class="success" role="status">Pořadí blogových stránek bylo uloženo.</p>
<?php endif; ?>

<p class="button-row button-row--start">
  <a href="<?= BASE_URL ?>/admin/blogs.php"><span aria-hidden="true">←</span> Zpět na blogy</a>
  <a href="<?= BASE_URL ?>/admin/pages.php">Všechny statické stránky</a>
  <a href="<?= BASE_URL ?>/admin/page_form.php?blog_id=<?= (int)$blog['id'] ?>&amp;redirect=<?= rawurlencode($returnUrl) ?>" class="btn">+ Nová stránka blogu</a>
</p>

<p style="margin-top:0;font-size:.9rem">Tady určujete pořadí statických stránek blogu <strong><?= h((string)$blog['name']) ?></strong>. Toto pořadí je oddělené od globální hlavní navigace webu.</p>

<?php if ($pages === []): ?>
  <p>Blog zatím nemá žádné vlastní statické stránky. <a href="<?= BASE_URL ?>/admin/page_form.php?blog_id=<?= (int)$blog['id'] ?>&amp;redirect=<?= rawurlencode($returnUrl) ?>">Vytvořit první stránku blogu</a>.</p>
<?php else: ?>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="redirect" value="<?= h($returnUrl) ?>">
    <p id="blog-page-order-help" class="field-help" style="margin-top:0">Přetahujte myší nebo použijte tlačítka Nahoru/Dolů. U skrytých či neschválených stránek zůstává pořadí zachované, ale na webu se teď nezobrazí.</p>
    <p id="blog-page-order-status" class="visually-hidden" role="status" aria-live="polite"></p>

    <ol id="blog-page-list" style="list-style:none;padding:0;margin:0;max-width:62rem">
      <?php $total = count($pages); ?>
      <?php foreach ($pages as $index => $page): ?>
        <?php $publicPath = ((int)$page['is_published'] === 1 && (string)$page['status'] === 'published') ? pagePublicPath($page + ['blog_id' => $blog['id'], 'blog_slug' => $blog['slug']]) : ''; ?>
        <li style="display:flex;align-items:center;gap:.75rem;padding:.55rem 0;border-bottom:1px solid #eee;flex-wrap:wrap;cursor:grab<?= ((int)$page['is_published'] !== 1 || (string)$page['status'] !== 'published') ? ';opacity:.6' : '' ?>"
            data-sort-id="<?= (int)$page['id'] ?>"
            tabindex="0"
            aria-describedby="blog-page-order-help">
          <input type="hidden" name="order[]" value="<?= (int)$page['id'] ?>">
          <div style="min-width:14rem;flex:1 1 18rem">
            <strong><?= h((string)$page['title']) ?></strong>
            <br><small style="color:#555"><?= h((string)$page['slug']) ?></small>
            <?php if ((int)$page['is_published'] !== 1): ?>
              <br><small style="color:#555">Nezveřejněná stránka</small>
            <?php elseif ((string)$page['status'] !== 'published'): ?>
              <br><small style="color:#555">Čeká na schválení</small>
            <?php endif; ?>
          </div>
          <button type="button" class="btn blog-page-move-up"<?= $index === 0 ? ' disabled aria-disabled="true"' : ' aria-disabled="false"' ?> aria-label="Posunout <?= h((string)$page['title']) ?> nahoru">
            <span aria-hidden="true">↑</span> Nahoru
          </button>
          <button type="button" class="btn blog-page-move-down"<?= $index === $total - 1 ? ' disabled aria-disabled="true"' : ' aria-disabled="false"' ?> aria-label="Posunout <?= h((string)$page['title']) ?> dolů">
            <span aria-hidden="true">↓</span> Dolů
          </button>
          <a href="<?= BASE_URL ?>/admin/page_form.php?id=<?= (int)$page['id'] ?>&amp;redirect=<?= rawurlencode($returnUrl) ?>" class="btn">Upravit</a>
          <?php if ($publicPath !== ''): ?>
            <a href="<?= h($publicPath) ?>" target="_blank" rel="noopener">Zobrazit na webu</a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>

    <div style="margin-top:1rem">
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
          item.style.opacity = '0.4';
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
              dragged.style.opacity = '';
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
