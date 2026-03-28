<?php
require_once __DIR__ . '/layout.php';
requireCapability('settings_manage', 'Přístup odepřen. Pro správu přesměrování nemáte potřebné oprávnění.');

$pdo = db_connect();
$success = '';
$error   = '';

$editId = inputInt('get', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $oldPath    = trim($_POST['old_path'] ?? '');
    $newPath    = trim($_POST['new_path'] ?? '');
    $statusCode = in_array((int)($_POST['status_code'] ?? 301), [301, 302], true) ? (int)$_POST['status_code'] : 301;
    $updateId   = inputInt('post', 'update_id');

    if ($oldPath === '') {
        $error = 'Stará cesta je povinná.';
    } elseif ($newPath === '') {
        $error = 'Nová cesta je povinná.';
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

<p style="font-size:.9rem">Spravujte přesměrování starých URL na nové. Užitečné po importu obsahu z jiného webu nebo po změně slug adresy.</p>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset>
    <legend>Nové přesměrování</legend>

    <label for="old_path">Stará cesta <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="old_path" name="old_path" required aria-required="true" maxlength="500"
           placeholder="/stara-stranka" aria-describedby="old-path-help">
    <small id="old-path-help" class="field-help">Relativní cesta bez domény, např. <code>/blog/stary-clanek</code></small>

    <label for="new_path">Nová cesta <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="new_path" name="new_path" required aria-required="true" maxlength="500"
           placeholder="/nova-stranka" aria-describedby="new-path-help">
    <small id="new-path-help" class="field-help">Relativní cesta nebo úplná URL, např. <code>/blog/novy-clanek</code></small>

    <label for="status_code">Typ přesměrování</label>
    <select id="status_code" name="status_code" aria-describedby="status-code-help" style="width:auto">
      <option value="301">301 – Trvalé</option>
      <option value="302">302 – Dočasné</option>
    </select>
    <small id="status-code-help" class="field-help">301 je vhodné pro trvalý přesun obsahu (vyhledávače přenesou hodnocení). 302 pro dočasné přesměrování.</small>

    <button type="submit" class="btn" style="margin-top:.5rem">Přidat přesměrování</button>
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
      <tr>
        <td>
          <?php if ($editId === (int)$r['id']): ?>
            <form method="post" style="display:flex;flex-direction:column;gap:.4rem">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= (int)$r['id'] ?>">
              <input type="text" name="old_path" required aria-required="true" aria-label="Stará cesta" maxlength="500"
                     value="<?= h((string)$r['old_path']) ?>">
              <input type="text" name="new_path" required aria-required="true" aria-label="Nová cesta" maxlength="500"
                     value="<?= h((string)$r['new_path']) ?>">
              <select name="status_code" aria-label="Typ přesměrování" style="width:auto">
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
          <?php if ($editId !== (int)$r['id']): ?>
            <a href="redirects.php?edit=<?= (int)$r['id'] ?>" class="btn">Upravit</a>
            <a href="redirects.php?delete=<?= (int)$r['id'] ?>&amp;csrf=<?= h(csrfToken()) ?>" class="btn btn-danger"
               data-confirm="Smazat přesměrování?">Smazat</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
