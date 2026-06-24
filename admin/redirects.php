<?php
require_once __DIR__ . '/layout.php';
requireCapability('settings_manage', 'Přístup odepřen. Pro správu přesměrování nemáte potřebné oprávnění.');

$pdo = db_connect();
$success = '';
$error   = '';

$editId = inputInt('get', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $oldPathInput = trim((string)($_POST['old_path'] ?? ''));
    $newPathInput = trim((string)($_POST['new_path'] ?? ''));
    $oldPath = internalRedirectTarget($oldPathInput, '');
    $newPath = storedRedirectTarget($newPathInput, '');
    $statusCode = in_array((int)($_POST['status_code'] ?? 301), [301, 302], true) ? (int)$_POST['status_code'] : 301;
    $updateId   = inputInt('post', 'update_id');

    if ($oldPathInput === '') {
        $error = 'Stará cesta je povinná.';
    } elseif ($oldPath === '') {
        $error = 'Stará cesta musí být interní cesta v rámci tohoto webu a začínat lomítkem.';
    } elseif ($newPathInput === '') {
        $error = 'Nová cesta je povinná.';
    } elseif ($newPath === '') {
        $error = 'Nová cesta musí být interní cesta nebo úplná adresa začínající http:// či https:// bez přihlašovacích údajů.';
    } elseif ($oldPath === $newPath) {
        $error = 'Stará a nová cesta nesmí být stejné.';
    } elseif ($updateId !== null) {
        try {
            $pdo->prepare("UPDATE cms_redirects SET old_path = ?, new_path = ?, status_code = ? WHERE id = ?")
                ->execute([$oldPath, $newPath, $statusCode, $updateId]);
            $success = 'Přesměrování uloženo.';
            $editId = null;
        } catch (\PDOException $e) {
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Přesměrování pro tuto cestu už existuje.' : 'Chyba při ukládání.';
        }
    } else {
        try {
            $pdo->prepare("INSERT INTO cms_redirects (old_path, new_path, status_code) VALUES (?, ?, ?)")
                ->execute([$oldPath, $newPath, $statusCode]);
            $success = 'Přesměrování přidáno.';
            logAction('redirect_add', "old={$oldPath} new={$newPath} status={$statusCode}");
        } catch (\PDOException $e) {
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Přesměrování pro tuto cestu už existuje.' : 'Chyba při ukládání.';
        }
    }
}

// Smazání
if (isset($_GET['delete'])) {
    $csrfGet = trim($_GET['csrf'] ?? '');
    if ($csrfGet === '' || !hash_equals(csrfToken(), $csrfGet)) {
        http_response_code(403);
        exit;
    }
    $delId = inputInt('get', 'delete');
    if ($delId !== null) {
        $pdo->prepare("DELETE FROM cms_redirects WHERE id = ?")->execute([$delId]);
        $success = 'Přesměrování smazáno.';
        logAction('redirect_delete', "id={$delId}");
    }
}

$redirects = $pdo->query("SELECT * FROM cms_redirects ORDER BY old_path")->fetchAll();

adminHeader('Přesměrování (301/302)');
?>
<?php if ($success !== ''): ?><p class="success" role="status"><?= h($success) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<p class="admin-description">Spravujte přesměrování starých URL na nové. Užitečné po importu obsahu z jiného webu nebo po změně slug adresy.</p>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset>
    <legend>Nové přesměrování</legend>

    <label for="old_path">Stará cesta <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="old_path" name="old_path" required aria-required="true" maxlength="500"
           placeholder="/stara-stranka" aria-describedby="old-path-help">
    <small id="old-path-help" class="field-help">Interní cesta bez domény, např. <code>/blog/stary-clanek</code>.</small>

    <label for="new_path">Nová cesta <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="new_path" name="new_path" required aria-required="true" maxlength="500"
           placeholder="/nova-stranka" aria-describedby="new-path-help">
    <small id="new-path-help" class="field-help">Interní cesta nebo úplná adresa začínající <code>http://</code> či <code>https://</code>, např. <code>/blog/novy-clanek</code>.</small>

    <label for="status_code">Typ přesměrování</label>
    <select id="status_code" name="status_code" aria-describedby="status-code-help" class="admin-input-auto">
      <option value="301">301 – Trvalé</option>
      <option value="302">302 – Dočasné</option>
    </select>
    <small id="status-code-help" class="field-help">301 je vhodné pro trvalý přesun obsahu (vyhledávače přenesou hodnocení). 302 pro dočasné přesměrování.</small>

    <button type="submit" class="btn admin-action-row">Přidat přesměrování</button>
  </fieldset>
</form>

<h2>Přehled přesměrování</h2>
<?php if (empty($redirects)): ?>
  <p>Zatím tu nejsou žádná přesměrování.</p>
<?php else: ?>
  <table>
    <caption>Přehled přesměrování</caption>
    <thead>
      <tr>
        <th scope="col">Stará cesta</th>
        <th scope="col">Nová cesta</th>
        <th scope="col">Typ</th>
        <th scope="col">Přístupy</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($redirects as $r): ?>
      <?php
        $redirectId = (int)$r['id'];
        $oldPathFieldId = 'redirect-old-path-' . $redirectId;
        $newPathFieldId = 'redirect-new-path-' . $redirectId;
        $statusCodeFieldId = 'redirect-status-code-' . $redirectId;
        ?>
      <tr>
        <td>
          <?php if ($editId === $redirectId): ?>
            <form method="post" class="admin-inline-edit-form">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= $redirectId ?>">
              <label for="<?= h($oldPathFieldId) ?>" class="sr-only">Stará cesta</label>
              <input type="text" id="<?= h($oldPathFieldId) ?>" name="old_path" required aria-required="true" maxlength="500"
                     value="<?= h((string)$r['old_path']) ?>">
              <label for="<?= h($newPathFieldId) ?>" class="sr-only">Nová cesta</label>
              <input type="text" id="<?= h($newPathFieldId) ?>" name="new_path" required aria-required="true" maxlength="500"
                     value="<?= h((string)$r['new_path']) ?>">
              <label for="<?= h($statusCodeFieldId) ?>" class="sr-only">Typ přesměrování</label>
              <select id="<?= h($statusCodeFieldId) ?>" name="status_code" class="admin-input-auto">
                <option value="301"<?= (int)$r['status_code'] === 301 ? ' selected' : '' ?>>301</option>
                <option value="302"<?= (int)$r['status_code'] === 302 ? ' selected' : '' ?>>302</option>
              </select>
              <div class="button-row">
                <button type="submit" class="btn">Uložit</button>
                <a href="redirects.php">Zrušit</a>
              </div>
            </form>
          <?php else: ?>
            <code><?= h((string)$r['old_path']) ?></code>
          <?php endif; ?>
        </td>
        <td><code><?= h((string)$r['new_path']) ?></code></td>
        <td><?= (int)$r['status_code'] ?></td>
        <td><?= (int)$r['hit_count'] ?></td>
        <td class="actions">
          <?php if ($editId !== $redirectId): ?>
            <a href="redirects.php?edit=<?= $redirectId ?>" class="btn">Upravit</a>
            <a href="redirects.php?delete=<?= $redirectId ?>&amp;csrf=<?= h(csrfToken()) ?>" class="btn btn-danger"
               data-confirm="Smazat přesměrování?">Smazat</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
