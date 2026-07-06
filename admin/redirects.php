<?php
require_once __DIR__ . '/layout.php';
requireCapability('settings_manage', 'Přístup odepřen. Pro správu přesměrování nemáte potřebné oprávnění.');

$pdo = db_connect();
$success = '';
$error   = '';
$redirectDeleteError = '';
$redirectDeleteErrorId = null;
$redirectDeleteErrorFields = [];
$redirectFieldErrors = [];
$redirectFieldErrorMessages = [
    'old_path' => 'Zadejte interní cestu bez domény, která začíná jedním lomítkem, například /stara-stranka.',
    'new_path' => 'Zadejte interní cestu začínající lomítkem, nebo úplnou http/https adresu bez přihlašovacích údajů.',
];
$redirectForm = [
    'old_path' => '',
    'new_path' => '',
    'status_code' => 301,
];

if (trim((string)($_GET['deleted'] ?? '')) === '1') {
    $success = 'Přesměrování smazáno.';
}

$editId = inputInt('get', 'edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $formAction = trim((string)($_POST['form_action'] ?? 'save_redirect'));

    if ($formAction === 'delete_redirect') {
        $deleteId = inputInt('post', 'delete_id');
        $confirmFieldName = $deleteId !== null ? 'confirm_redirect_delete_' . $deleteId : 'confirm_redirect_delete';
        $redirectDeleteConfirmed = isset($_POST[$confirmFieldName])
            && (string)$_POST[$confirmFieldName] === '1';

        if ($deleteId === null) {
            $redirectDeleteError = 'Přesměrování nejde smazat, protože požadavek neobsahuje platný identifikátor. Obnovte stránku a zkuste akci znovu.';
        } else {
            $redirectToDeleteStmt = $pdo->prepare('SELECT id, old_path, new_path, status_code FROM cms_redirects WHERE id = ?');
            $redirectToDeleteStmt->execute([$deleteId]);
            $redirectToDelete = $redirectToDeleteStmt->fetch();

            if (!$redirectToDelete) {
                $redirectDeleteError = 'Přesměrování už není dostupné. Obnovte stránku a zkontrolujte aktuální seznam.';
            } elseif (!$redirectDeleteConfirmed) {
                $redirectDeleteError = 'Přesměrování nejde smazat bez potvrzení kontroly dopadu. U pole Potvrzení smazání je konkrétní nápověda.';
                $redirectDeleteErrorId = $deleteId;
                $redirectDeleteErrorFields[] = $confirmFieldName;
            } else {
                $pdo->prepare('DELETE FROM cms_redirects WHERE id = ?')->execute([$deleteId]);
                logAction(
                    'redirect_delete',
                    'id=' . $deleteId
                    . ' old=' . (string)$redirectToDelete['old_path']
                    . ' new=' . (string)$redirectToDelete['new_path']
                    . ' status=' . (int)$redirectToDelete['status_code']
                );
                header('Location: ' . BASE_URL . '/admin/redirects.php?deleted=1');
                exit;
            }
        }
    } elseif ($formAction === 'save_redirect') {
        $oldPathInput = trim((string)($_POST['old_path'] ?? ''));
        $newPathInput = trim((string)($_POST['new_path'] ?? ''));
        $redirectForm = [
            'old_path' => $oldPathInput,
            'new_path' => $newPathInput,
            'status_code' => in_array((int)($_POST['status_code'] ?? 301), [301, 302], true) ? (int)$_POST['status_code'] : 301,
        ];
        $oldPath = internalRedirectTarget($oldPathInput, '');
        $newPath = storedRedirectTarget($newPathInput, '');
        $statusCode = (int)$redirectForm['status_code'];
        $updateId   = inputInt('post', 'update_id');

        if ($oldPathInput === '') {
            $error = 'Přesměrování nejde uložit bez staré cesty. U pole Stará cesta je konkrétní nápověda.';
            $redirectFieldErrors[] = 'old_path';
        } elseif ($oldPath === '') {
            $error = 'Stará cesta přesměrování není použitelná. U pole Stará cesta je konkrétní nápověda.';
            $redirectFieldErrors[] = 'old_path';
        } elseif ($newPathInput === '') {
            $error = 'Přesměrování nejde uložit bez nové cesty. U pole Nová cesta je konkrétní nápověda.';
            $redirectFieldErrors[] = 'new_path';
        } elseif ($newPath === '') {
            $error = 'Nová cesta přesměrování není použitelná. U pole Nová cesta je konkrétní nápověda.';
            $redirectFieldErrors[] = 'new_path';
        } elseif ($oldPath === $newPath) {
            $error = 'Stará a nová cesta přesměrování se nesmí shodovat. U obou polí je konkrétní nápověda.';
            $redirectFieldErrors = ['old_path', 'new_path'];
        } elseif ($updateId !== null) {
            try {
                $pdo->prepare("UPDATE cms_redirects SET old_path = ?, new_path = ?, status_code = ? WHERE id = ?")
                    ->execute([$oldPath, $newPath, $statusCode, $updateId]);
                $success = 'Přesměrování uloženo.';
                $editId = null;
            } catch (\PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate')
                    ? 'Přesměrování pro tuto starou cestu už existuje. U pole Stará cesta je konkrétní nápověda.'
                    : 'Přesměrování se nepodařilo uložit. Zkontrolujte zadané cesty a zkuste to znovu.';
                $redirectFieldErrors = str_contains($e->getMessage(), 'Duplicate') ? ['old_path'] : ['old_path', 'new_path'];
            }
        } else {
            try {
                $pdo->prepare("INSERT INTO cms_redirects (old_path, new_path, status_code) VALUES (?, ?, ?)")
                    ->execute([$oldPath, $newPath, $statusCode]);
                $success = 'Přesměrování přidáno.';
                logAction('redirect_add', "old={$oldPath} new={$newPath} status={$statusCode}");
            } catch (\PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate')
                    ? 'Přesměrování pro tuto starou cestu už existuje. U pole Stará cesta je konkrétní nápověda.'
                    : 'Přesměrování se nepodařilo uložit. Zkontrolujte zadané cesty a zkuste to znovu.';
                $redirectFieldErrors = str_contains($e->getMessage(), 'Duplicate') ? ['old_path'] : ['old_path', 'new_path'];
            }
        }
    } else {
        $error = 'Akce formuláře není platná. Vraťte se na stránku přesměrování a použijte dostupné tlačítko.';
    }
}

$redirects = $pdo->query("SELECT * FROM cms_redirects ORDER BY old_path")->fetchAll();

adminHeader('Přesměrování (301/302)');
?>
<?php if ($success !== ''): ?><p class="success" role="status"><?= h($success) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p id="redirect-form-error" class="error" role="alert" aria-atomic="true"><?= h($error) ?></p><?php endif; ?>
<?php if ($redirectDeleteError !== ''): ?><p id="redirect-delete-error" class="error" role="alert" aria-atomic="true"><?= h($redirectDeleteError) ?></p><?php endif; ?>

<p class="admin-description">Spravujte přesměrování starých URL na nové. Užitečné po importu obsahu z jiného webu nebo po změně slug adresy.</p>

<form method="post" novalidate<?= $error !== '' ? ' aria-describedby="redirect-form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="form_action" value="save_redirect">
  <fieldset>
    <legend>Nové přesměrování</legend>

    <label for="old_path">Stará cesta <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="old_path" name="old_path" required aria-required="true" maxlength="500"
           placeholder="/stara-stranka" value="<?= h((string)$redirectForm['old_path']) ?>"
           <?= adminFieldAttributes('old_path', $redirectFieldErrors, [], ['old-path-help']) ?>>
    <small id="old-path-help" class="field-help">Interní cesta bez domény, např. <code>/blog/stary-clanek</code>.</small>
    <?php adminRenderFieldError('old_path', $redirectFieldErrors, [], $redirectFieldErrorMessages['old_path']); ?>

    <label for="new_path">Nová cesta <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="new_path" name="new_path" required aria-required="true" maxlength="500"
           placeholder="/nova-stranka" value="<?= h((string)$redirectForm['new_path']) ?>"
           <?= adminFieldAttributes('new_path', $redirectFieldErrors, [], ['new-path-help']) ?>>
    <small id="new-path-help" class="field-help">Interní cesta nebo úplná adresa začínající <code>http://</code> či <code>https://</code>, např. <code>/blog/novy-clanek</code>.</small>
    <?php adminRenderFieldError('new_path', $redirectFieldErrors, [], $redirectFieldErrorMessages['new_path']); ?>

    <label for="status_code">Typ přesměrování</label>
    <select id="status_code" name="status_code" aria-describedby="status-code-help" class="admin-input-auto">
      <option value="301"<?= (int)$redirectForm['status_code'] === 301 ? ' selected' : '' ?>>301 – Trvalé</option>
      <option value="302"<?= (int)$redirectForm['status_code'] === 302 ? ' selected' : '' ?>>302 – Dočasné</option>
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
        $redirectDeleteConfirmField = 'confirm_redirect_delete_' . $redirectId;
        $redirectDeleteConfirmId = 'confirm-redirect-delete-' . $redirectId;
        $redirectDeleteReviewId = 'redirect-delete-review-' . $redirectId;
        $redirectDeleteFieldErrorId = 'confirm-redirect-delete-' . $redirectId . '-error';
        $redirectDeleteHasError = $redirectDeleteErrorId === $redirectId;
        ?>
      <tr>
        <td>
          <?php if ($editId === $redirectId): ?>
            <form method="post" class="admin-inline-edit-form">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="form_action" value="save_redirect">
              <input type="hidden" name="update_id" value="<?= $redirectId ?>">
              <fieldset class="admin-inline-fieldset">
                <legend class="sr-only">Úprava přesměrování <?= h((string)$r['old_path']) ?></legend>
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
              </fieldset>
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
            <form method="post" class="admin-inline-form" novalidate<?= $redirectDeleteHasError ? ' aria-describedby="redirect-delete-error"' : '' ?>>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="form_action" value="delete_redirect">
              <input type="hidden" name="delete_id" value="<?= $redirectId ?>">
              <fieldset class="admin-inline-fieldset">
                <legend class="sr-only">Smazání přesměrování <?= h((string)$r['old_path']) ?></legend>
                <p id="<?= h($redirectDeleteReviewId) ?>" class="field-help field-help--flush">
                  Smazání ukončí přesměrování této staré cesty na novou. Zkontrolujte řádek před potvrzením.
                </p>
                <label for="<?= h($redirectDeleteConfirmId) ?>" class="admin-checkbox-label">
                  <input
                    type="checkbox"
                    id="<?= h($redirectDeleteConfirmId) ?>"
                    name="<?= h($redirectDeleteConfirmField) ?>"
                    value="1"
                    required
                    aria-required="true"<?= adminFieldAttributes($redirectDeleteConfirmField, $redirectDeleteErrorFields, [], [$redirectDeleteReviewId], $redirectDeleteFieldErrorId) ?>>
                  Potvrzuji smazání tohoto přesměrování.
                </label>
                <?php adminRenderFieldError($redirectDeleteConfirmField, $redirectDeleteErrorFields, [], 'Před smazáním přesměrování potvrďte, že jste zkontrolovali starou i novou cestu a dopad na veřejné URL.', $redirectDeleteFieldErrorId); ?>
                <button type="submit" class="btn btn-danger"
                        data-confirm="Smazat přesměrování? Veřejná stará URL přestane návštěvníky převádět na novou adresu.">Smazat</button>
              </fieldset>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
