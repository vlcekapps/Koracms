<?php
require_once __DIR__ . '/layout.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu míst rezervací nemáte potřebné oprávnění.');
requireModuleEnabled('reservations');

$pdo = db_connect();
$success = false;
$error = '';
$fieldErrors = [];
$fieldErrorMessages = [];
$editId = inputInt('get', 'edit');
$q = trim($_GET['q'] ?? '');
$formState = [
    'name' => '',
    'address' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $updateId = inputInt('post', 'update_id');
    $formState = [
        'name' => $name,
        'address' => $address,
    ];
    $editId = $updateId;

    if ($name === '') {
        $error = 'Místo rezervací nejde uložit bez názvu. U pole Název je konkrétní nápověda.';
        $fieldErrors[] = 'name';
        $fieldErrorMessages['name'] = 'Doplňte krátký název místa, například Zasedací místnost A.';
    } elseif ($updateId !== null) {
        $pdo->prepare("UPDATE cms_res_locations SET name = ?, address = ? WHERE id = ?")->execute([$name, $address, $updateId]);
        logAction('res_location_edit', "id={$updateId}, name=" . mb_substr($name, 0, 80));
        $success = true;
        $editId = null;
    } else {
        $pdo->prepare("INSERT INTO cms_res_locations (name, address) VALUES (?, ?)")->execute([$name, $address]);
        logAction('res_location_add', "name=" . mb_substr($name, 0, 80));
        $success = true;
        $formState = [
            'name' => '',
            'address' => '',
        ];
    }
}

$stmt = $pdo->prepare(
    "SELECT l.id, l.name, l.address, COUNT(rl.resource_id) AS resource_count
     FROM cms_res_locations l
     LEFT JOIN cms_res_resource_locations rl ON rl.location_id = l.id
     " . ($q !== '' ? "WHERE l.name LIKE ? OR l.address LIKE ?" : '') . "
     GROUP BY l.id, l.name, l.address
     ORDER BY l.name"
);
$stmt->execute($q !== '' ? ['%' . $q . '%', '%' . $q . '%'] : []);
$locations = $stmt->fetchAll();

$deleteConfirmError = trim((string)($_GET['delete_error'] ?? '')) === 'confirm_required';
$deleteErrorId = inputInt('get', 'delete_error_id');
if ($deleteConfirmError) {
    $error = 'Místo rezervací nejde smazat bez potvrzení kontroly dopadu. U pole Potvrzení smazání je konkrétní nápověda.';
}
$successMessage = $success ? 'Místo uloženo.' : '';
if (trim((string)($_GET['deleted'] ?? '')) === '1') {
    $successMessage = 'Místo bylo smazáno.';
}
$createFormHasError = $error !== '' && $editId === null && !$deleteConfirmError;

adminHeader('Lokality rezervací');
?>
<?php if ($successMessage !== ''): ?><p class="success" role="status"><?= h($successMessage) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p id="res-location-form-error" class="error" role="alert" aria-atomic="true"><?= h($error) ?></p><?php endif; ?>

<form method="get" class="button-row button-row--baseline admin-stack-sm">
  <div>
    <label for="q">Hledat</label>
    <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Název nebo adresa" class="admin-search-input">
  </div>
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== ''): ?>
    <a href="res_locations.php" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<form method="post" novalidate<?= $createFormHasError ? ' aria-describedby="res-location-form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <fieldset>
    <legend>Nové místo</legend>
    <div class="button-row button-row--baseline">
      <div>
        <label for="name">Název <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" required aria-required="true" maxlength="255"
               placeholder="např. Dům u Růženky" value="<?= h($editId === null ? $formState['name'] : '') ?>"
               <?= adminFieldAttributes('name', $editId === null ? $fieldErrors : [], [], ['res-location-name-help']) ?>>
        <small id="res-location-name-help" class="field-help">Zobrazuje se v editoru rezervačních zdrojů a veřejných detailech rezervací.</small>
        <?php adminRenderFieldError('name', $editId === null ? $fieldErrors : [], [], $fieldErrorMessages['name'] ?? ''); ?>
      </div>
      <div>
        <label for="address">Adresa</label>
        <input type="text" id="address" name="address" maxlength="500" class="admin-search-input"
               placeholder="Ulice, číslo domu, PSČ, město" value="<?= h($editId === null ? $formState['address'] : '') ?>">
      </div>
      <button type="submit" class="btn">Přidat místo</button>
    </div>
  </fieldset>
</form>

<h2>Existující místa</h2>
<?php if ($locations === []): ?>
  <p><?= $q !== '' ? 'Pro zvolený filtr tu teď nejsou žádné lokality rezervací.' : 'Zatím tu nejsou žádné lokality rezervací.' ?></p>
<?php else: ?>
  <table>
    <caption>Lokality rezervací</caption>
    <thead><tr><th scope="col">Název</th><th scope="col">Adresa</th><th scope="col">Zdroje</th><th scope="col">Akce</th></tr></thead>
    <tbody>
    <?php foreach ($locations as $location): ?>
      <?php
        $locationId = (int)$location['id'];
        $deleteConfirmField = 'confirm_res_location_delete_' . $locationId;
        $deleteConfirmId = 'confirm-res-location-delete-' . $locationId;
        $deleteReviewId = 'res-location-delete-review-' . $locationId;
        $deleteFieldErrorId = 'confirm-res-location-delete-' . $locationId . '-error';
        $deleteHasError = $deleteConfirmError && $deleteErrorId === $locationId;
        $deleteErrorFields = $deleteHasError ? [$deleteConfirmField] : [];
        ?>
      <tr>
        <?php if ($editId === $locationId): ?>
          <?php $editLocationHasError = $error !== '' && $editId === $locationId && !$deleteConfirmError; ?>
          <td colspan="3">
            <form method="post" novalidate<?= $editLocationHasError ? ' aria-describedby="res-location-form-error"' : '' ?>>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="update_id" value="<?= $locationId ?>">
              <fieldset class="admin-filter-fieldset button-row button-row--baseline">
                <legend class="sr-only">Upravit místo <?= h((string)$location['name']) ?></legend>
                <label for="name-<?= $locationId ?>" class="sr-only">Název místa</label>
                <input type="text" id="name-<?= $locationId ?>" name="name" required aria-required="true" maxlength="255"
                       value="<?= h($editLocationHasError ? $formState['name'] : (string)$location['name']) ?>" class="admin-input-auto"
                       <?= adminFieldAttributes('name', $editLocationHasError ? $fieldErrors : [], [], ['res-location-name-help-' . $locationId], 'name-error-' . $locationId) ?>>
                <small id="res-location-name-help-<?= $locationId ?>" class="field-help">Doplňte krátký název místa rezervací.</small>
                <?php adminRenderFieldError('name', $editLocationHasError ? $fieldErrors : [], [], $fieldErrorMessages['name'] ?? '', 'name-error-' . $locationId); ?>
                <label for="addr-<?= $locationId ?>" class="sr-only">Adresa místa</label>
                <input type="text" id="addr-<?= $locationId ?>" name="address" maxlength="500"
                       value="<?= h($editLocationHasError ? $formState['address'] : (string)$location['address']) ?>" class="admin-search-input">
                <button type="submit" class="btn">Uložit</button>
                <a href="res_locations.php<?= $q !== '' ? '?q=' . rawurlencode($q) : '' ?>">Zrušit</a>
              </fieldset>
            </form>
          </td>
        <?php else: ?>
          <td><?= h((string)$location['name']) ?></td>
          <td><?= h((string)($location['address'] ?: '–')) ?></td>
          <td><?= (int)$location['resource_count'] ?></td>
        <?php endif; ?>
        <td class="actions">
          <?php if ($editId !== $locationId): ?>
            <a href="res_locations.php?edit=<?= $locationId ?><?= $q !== '' ? '&amp;q=' . rawurlencode($q) : '' ?>" class="btn">Upravit</a>
          <?php endif; ?>
          <form action="res_location_delete.php" method="post"
                class="admin-inline-form"
                novalidate<?= $deleteHasError ? ' aria-describedby="res-location-form-error"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= $locationId ?>">
            <fieldset class="admin-inline-fieldset">
              <legend class="sr-only">Smazání místa rezervací <?= h((string)$location['name']) ?></legend>
              <p id="<?= h($deleteReviewId) ?>" class="field-help field-help--flush">
                Smazání odebere místo z <?= (int)$location['resource_count'] ?> rezervačních zdrojů. Zdroje i existující rezervace zůstanou zachované bez tohoto místa.
              </p>
              <label for="<?= h($deleteConfirmId) ?>" class="admin-checkbox-label">
                <input
                  type="checkbox"
                  id="<?= h($deleteConfirmId) ?>"
                  name="<?= h($deleteConfirmField) ?>"
                  value="1"
                  required
                  aria-required="true"<?= adminFieldAttributes($deleteConfirmField, $deleteErrorFields, [], [$deleteReviewId], $deleteFieldErrorId) ?>>
                Potvrzuji smazání tohoto místa rezervací.
              </label>
              <?php adminRenderFieldError($deleteConfirmField, $deleteErrorFields, [], 'Před smazáním místa potvrďte, že jste zkontrolovali dopad na rezervační zdroje.', $deleteFieldErrorId); ?>
              <button type="submit" class="btn btn-danger"
                      data-confirm="Smazat místo? Vazby na zdroje budou odebrány.">Smazat</button>
            </fieldset>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<p><a href="res_resources.php"><span aria-hidden="true">&larr;</span> Zpět na zdroje rezervací</a></p>
<?php adminFooter(); ?>
