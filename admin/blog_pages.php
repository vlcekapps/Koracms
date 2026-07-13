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
$linkFieldErrors = [];
$linkFieldErrorMessages = [
    'title' => 'Doplňte krátký název odkazu, například Ceník služeb.',
    'url' => 'Zadejte interní cestu začínající lomítkem, například /kontakt, nebo úplnou http/https adresu bez přihlašovacích údajů.',
];
$linkForm = [
    'id' => 0,
    'title' => '',
    'url' => '',
    'alt_text' => '',
    'target_blank' => 0,
    'is_active' => 1,
];
$linkDeleteErrorCode = trim((string)($_GET['delete_error'] ?? ''));
$linkDeleteErrorId = inputInt('get', 'delete_error_id');

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
            $linkError = 'Externí odkaz blogu nejde uložit bez názvu. U pole Název odkazu je konkrétní nápověda.';
            $linkFieldErrors[] = 'title';
        } elseif ($safeUrl === '') {
            $linkError = 'Adresa externího odkazu blogu není použitelná. U pole Adresa odkazu je konkrétní nápověda.';
            $linkFieldErrors[] = 'url';
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
        $blogPagesUrl = BASE_URL . '/admin/blog_pages.php?blog_id=' . (int)$blog['id'];
        if ($linkId === null) {
            header('Location: ' . $blogPagesUrl . '&delete_error=not_found');
            exit;
        }

        $deleteLinkStmt = $pdo->prepare(
            "SELECT id, title, url
             FROM cms_nav_links
             WHERE id = ? AND blog_id = ?
             LIMIT 1"
        );
        $deleteLinkStmt->execute([$linkId, (int)$blog['id']]);
        $linkForDelete = $deleteLinkStmt->fetch() ?: null;
        if (!$linkForDelete) {
            header('Location: ' . $blogPagesUrl . '&delete_error=not_found');
            exit;
        }

        $confirmFieldName = 'confirm_blog_nav_link_delete_' . $linkId;
        $deleteConfirmed = isset($_POST[$confirmFieldName])
            && (string)$_POST[$confirmFieldName] === '1';
        if (!$deleteConfirmed) {
            header('Location: ' . appendUrlQuery($blogPagesUrl, [
                'edit_link' => $linkId,
                'delete_error' => 'confirm_required',
                'delete_error_id' => $linkId,
            ]) . '#blog-nav-link-delete-form');
            exit;
        }

        try {
            $pdo->beginTransaction();

            $deleteStmt = $pdo->prepare("DELETE FROM cms_nav_links WHERE id = ? AND blog_id = ?");
            $deleteStmt->execute([$linkId, (int)$blog['id']]);
            if ($deleteStmt->rowCount() !== 1) {
                throw new RuntimeException('Externí odkaz blogové navigace se během mazání změnil.');
            }

            normalizeBlogPageNavigationOrder($pdo, (int)$blog['id']);
            logAction('blog_nav_link_delete', 'blog_id=' . (int)$blog['id'] . ', id=' . $linkId);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            koraLog('error', 'blog navigation link deletion failed', [
                'blog_id' => (int)$blog['id'],
                'link_id' => $linkId,
                'exception' => $e,
            ]);
            header('Location: ' . appendUrlQuery($blogPagesUrl, [
                'edit_link' => $linkId,
                'delete_error' => 'failed',
                'delete_error_id' => $linkId,
            ]) . '#blog-nav-link-delete-form');
            exit;
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

$linkDeleteConfirmField = 'confirm_blog_nav_link_delete_' . (int)$linkForm['id'];
$linkDeleteConfirmId = 'confirm-blog-nav-link-delete-' . (int)$linkForm['id'];
$linkDeleteReviewId = 'blog-nav-link-delete-review-' . (int)$linkForm['id'];
$linkDeleteFieldErrorId = 'confirm-blog-nav-link-delete-' . (int)$linkForm['id'] . '-error';
$linkDeleteErrorMessage = match ($linkDeleteErrorCode) {
    'confirm_required' => 'Externí odkaz blogu nelze trvale smazat bez potvrzení. U pole Potvrzení trvalého smazání je konkrétní nápověda.',
    'failed' => 'Externí odkaz blogu se nepodařilo smazat. Odkaz i pořadí zůstaly beze změny; zkontrolujte údaje a zkuste akci znovu.',
    'not_found' => 'Externí odkaz pro smazání nebyl v tomto blogu nalezen. Obnovte přehled a vyberte existující odkaz.',
    default => '',
};
$linkDeleteErrorVisible = $linkDeleteErrorMessage !== ''
    && ($linkDeleteErrorCode === 'not_found' || $linkDeleteErrorId === (int)$linkForm['id']);
$linkDeleteConfirmError = $linkDeleteErrorVisible && $linkDeleteErrorCode === 'confirm_required';
$linkDeleteErrorFields = $linkDeleteConfirmError ? [$linkDeleteConfirmField] : [];

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
  <p class="success" role="status" aria-atomic="true">Externí odkaz blogu byl smazán.</p>
<?php endif; ?>
<?php if ($linkDeleteErrorMessage !== '' && (int)$linkForm['id'] === 0): ?>
  <p class="error" role="alert" aria-atomic="true"><?= h($linkDeleteErrorMessage) ?></p>
<?php endif; ?>

<p class="button-row button-row--start">
  <a href="<?= BASE_URL ?>/admin/blogs.php"><span aria-hidden="true">←</span> Zpět na blogy</a>
  <a href="<?= BASE_URL ?>/admin/pages.php">Všechny statické stránky</a>
  <a href="<?= BASE_URL ?>/admin/page_form.php?blog_id=<?= (int)$blog['id'] ?>&amp;redirect=<?= rawurlencode($returnUrl) ?>" class="btn">+ Nová stránka blogu</a>
</p>

<p class="admin-description">Tady určujete pořadí statických stránek a externích odkazů blogu <strong><?= h((string)$blog['name']) ?></strong>. Toto pořadí je oddělené od globální hlavní navigace webu.</p>

<form method="post" id="blog-nav-link-form" class="form-card" novalidate<?= $linkError !== '' ? ' aria-describedby="blog-nav-link-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="action" value="save_link">
  <input type="hidden" name="link_id" value="<?= (int)$linkForm['id'] ?>">
  <fieldset>
    <legend><?= (int)$linkForm['id'] > 0 ? 'Upravit externí odkaz blogu' : 'Přidat externí odkaz blogu' ?></legend>
    <?php if ($linkError !== ''): ?>
      <p id="blog-nav-link-error" class="error" role="alert" aria-atomic="true"><?= h($linkError) ?></p>
    <?php endif; ?>

    <label for="blog-nav-link-title">Název odkazu <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="blog-nav-link-title" name="title" value="<?= h((string)$linkForm['title']) ?>" required aria-required="true" maxlength="255"
           <?= adminFieldAttributes('title', $linkFieldErrors, [], ['blog-nav-link-title-help']) ?>>
    <small id="blog-nav-link-title-help" class="field-help">Název se zobrazí v navigaci blogu jako text odkazu.</small>
    <?php adminRenderFieldError('title', $linkFieldErrors, [], $linkFieldErrorMessages['title']); ?>

    <label for="blog-nav-link-url">Adresa odkazu <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="url" id="blog-nav-link-url" name="url" value="<?= h((string)$linkForm['url']) ?>" required aria-required="true" maxlength="1000"
           <?= adminFieldAttributes('url', $linkFieldErrors, [], ['blog-nav-link-url-help']) ?>>
    <small id="blog-nav-link-url-help" class="field-help">Použijte úplnou adresu začínající <code>https://</code> nebo interní cestu webu, například <code>/kontakt</code>.</small>
    <?php adminRenderFieldError('url', $linkFieldErrors, [], $linkFieldErrorMessages['url']); ?>

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
  <form method="post" id="blog-nav-link-delete-form" class="form-card" novalidate<?= $linkDeleteErrorVisible ? ' aria-describedby="blog-nav-link-delete-error"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="delete_link">
    <input type="hidden" name="link_id" value="<?= (int)$linkForm['id'] ?>">
    <fieldset>
      <legend>Kontrola trvalého smazání externího odkazu blogu</legend>

      <?php if ($linkDeleteErrorVisible): ?>
        <p id="blog-nav-link-delete-error" class="error" role="alert" aria-atomic="true"><?= h($linkDeleteErrorMessage) ?></p>
      <?php endif; ?>

      <dl class="admin-summary-list">
        <dt>Blog</dt>
        <dd><?= h((string)$blog['name']) ?></dd>
        <dt>Odkaz</dt>
        <dd><?= h((string)$linkForm['title']) ?></dd>
        <dt>Cílová adresa</dt>
        <dd><code><?= h((string)$linkForm['url']) ?></code></dd>
        <dt>Stav v navigaci</dt>
        <dd><?= (int)$linkForm['is_active'] === 1 ? 'Zobrazený' : 'Vypnutý' ?><?= (int)$linkForm['target_blank'] === 1 ? ', otevírá se v novém okně' : '' ?></dd>
      </dl>

      <p id="<?= h($linkDeleteReviewId) ?>" class="admin-description admin-description--muted">Odkaz bude trvale odstraněn z navigace tohoto blogu a zbývající položky se znovu seřadí. Tato akce nepoužívá Koš a nelze ji obnovit.</p>
      <label for="<?= h($linkDeleteConfirmId) ?>" class="admin-checkbox-label">
        <input type="checkbox" id="<?= h($linkDeleteConfirmId) ?>" name="<?= h($linkDeleteConfirmField) ?>" value="1" required aria-required="true"<?= adminFieldAttributes($linkDeleteConfirmField, $linkDeleteErrorFields, [], [$linkDeleteReviewId], $linkDeleteFieldErrorId) ?>>
        Potvrzuji trvalé smazání tohoto externího odkazu z navigace blogu.
      </label>
      <?php adminRenderFieldError($linkDeleteConfirmField, $linkDeleteErrorFields, [], 'Před smazáním potvrďte, že jste zkontrolovali blog, název, cílovou adresu a dopad na navigaci.', $linkDeleteFieldErrorId); ?>

      <div class="button-row admin-action-row">
        <button type="submit" class="btn btn-danger">Trvale smazat externí odkaz blogu</button>
        <a href="<?= BASE_URL ?>/admin/blog_pages.php?blog_id=<?= (int)$blog['id'] ?>#blog-nav-link-form" class="button-secondary">Zrušit smazání</a>
      </div>
    </fieldset>
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
          <button type="button" class="btn blog-page-move-up"<?= $index === 0 ? ' disabled aria-disabled="true"' : ' aria-disabled="false"' ?>>
            <span aria-hidden="true">↑</span> Nahoru<span class="sr-only"> v pořadí: <?= h((string)$item['title']) ?></span>
          </button>
          <button type="button" class="btn blog-page-move-down"<?= $index === $total - 1 ? ' disabled aria-disabled="true"' : ' aria-disabled="false"' ?>>
            <span aria-hidden="true">↓</span> Dolů<span class="sr-only"> v pořadí: <?= h((string)$item['title']) ?></span>
          </button>
          <?php if ($item['type'] === 'page'): ?>
            <a href="<?= BASE_URL ?>/admin/page_form.php?id=<?= (int)$row['id'] ?>&amp;redirect=<?= rawurlencode($returnUrl) ?>" class="btn">Upravit stránku</a>
          <?php else: ?>
            <a href="<?= BASE_URL ?>/admin/blog_pages.php?blog_id=<?= (int)$blog['id'] ?>&amp;edit_link=<?= (int)$row['id'] ?>#blog-nav-link-form" class="btn">Upravit odkaz</a>
          <?php endif; ?>
          <?php if ($publicPath !== ''): ?>
            <a href="<?= h($publicPath) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
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
