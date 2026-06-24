<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('bookings_manage', 'Přístup odepřen. Pro správu zdrojů rezervací nemáte potřebné oprávnění.');

$pdo = db_connect();
$id  = inputInt('get', 'id');
$resource = null;
$hours    = [];
$slots    = [];
$blocked  = [];

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_res_resources WHERE id = ?");
    $stmt->execute([$id]);
    $resource = $stmt->fetch();
    if (!$resource) {
        header('Location: res_resources.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM cms_res_hours WHERE resource_id = ? ORDER BY day_of_week");
    $stmt->execute([$id]);
    $hours = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM cms_res_slots WHERE resource_id = ? ORDER BY day_of_week, start_time");
    $stmt->execute([$id]);
    $slots = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM cms_res_blocked WHERE resource_id = ? ORDER BY blocked_date");
    $stmt->execute([$id]);
    $blocked = $stmt->fetchAll();
}

$categories = $pdo->query("SELECT id, name FROM cms_res_categories ORDER BY sort_order, name")->fetchAll();
$locations  = $pdo->query("SELECT id, name, address FROM cms_res_locations ORDER BY name")->fetchAll();

// Load selected locations for this resource
$selectedLocations = [];
if ($id !== null) {
    $selectedLocations = $pdo->prepare("SELECT location_id FROM cms_res_resource_locations WHERE resource_id = ?");
    $selectedLocations->execute([$id]);
    $selectedLocations = array_column($selectedLocations->fetchAll(), 'location_id');
}

// Index hours by day_of_week
$hoursByDay = [];
foreach ($hours as $h) {
    $hoursByDay[(int)$h['day_of_week']] = $h;
}

// Index slots by day_of_week
$slotsByDay = [];
foreach ($slots as $s) {
    $slotsByDay[(int)$s['day_of_week']][] = $s;
}

$dayNames = ['Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota', 'Neděle'];

adminHeader($id ? 'Upravit zdroj rezervací' : 'Nový zdroj rezervací');

$err = trim($_GET['err'] ?? '');
$fieldErrorMap = [
    'name' => ['name'],
    'slug' => ['slug'],
    'capacity' => ['capacity'],
    'hours' => ['hours'],
    'slots' => ['slots'],
    'blocked_date' => ['blocked_dates'],
];
$fieldErrorMessages = [
    'name' => 'Název zdroje je povinný.',
    'slug' => 'Slug je povinný a musí být unikátní.',
    'capacity' => 'Kapacita musí být alespoň 1.',
    'hours' => 'Časy dostupnosti musí být ve správném formátu a konec musí být později než začátek.',
    'slots' => 'Předdefinované sloty musí mít platný čas začátku i konce a konec musí být později než začátek.',
    'blocked_date' => 'Blokované datum musí být ve správném formátu.',
];
?>

<?php if ($err === 'name'): ?>
  <p role="alert" class="error" id="form-error">Název zdroje je povinný.</p>
<?php elseif ($err === 'slug'): ?>
  <p role="alert" class="error" id="form-error">Slug je povinný a musí být unikátní.</p>
<?php elseif ($err === 'capacity'): ?>
  <p role="alert" class="error" id="form-error">Kapacita musí být alespoň 1.</p>
<?php elseif ($err === 'hours'): ?>
  <p role="alert" class="error" id="form-error">Časy dostupnosti musí být ve správném formátu a konec musí být později než začátek.</p>
<?php elseif ($err === 'slots'): ?>
  <p role="alert" class="error" id="form-error">Předdefinované sloty musí mít platný čas začátku i konce a konec musí být později než začátek.</p>
<?php elseif ($err === 'blocked_date'): ?>
  <p role="alert" class="error" id="form-error">Blokované datum musí být ve správném formátu.</p>
<?php elseif ($err === 'save'): ?>
  <p role="alert" class="error" id="form-error">Zdroj se nepodařilo uložit. Zkontrolujte zadané údaje a zkuste to prosím znovu.</p>
<?php endif; ?>

<p class="res-resource-intro">
  Vyplňte základní údaje o zdroji a pak nastavte způsob rezervací. Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<p><a href="res_resources.php"><span aria-hidden="true">←</span> Zpět na zdroje rezervací</a></p>

<form method="post" action="res_resource_save.php" novalidate
      <?= $err ? 'aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <!-- A) Basic info -->
  <fieldset>
    <legend>Základní informace</legend>

    <label for="name">Název <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="name" name="name" required aria-required="true" maxlength="255"
           <?= adminFieldAttributes('name', $err, $fieldErrorMap) ?>
           value="<?= h($resource['name'] ?? '') ?>">
    <?php adminRenderFieldError('name', $err, $fieldErrorMap, $fieldErrorMessages['name']); ?>

    <label for="slug">Slug (URL identifikátor) <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="100" pattern="[a-z0-9\-]+"
           <?= adminFieldAttributes('slug', $err, $fieldErrorMap, ['resource-slug-help']) ?>
           value="<?= h($resource['slug'] ?? '') ?>">
    <small id="resource-slug-help" class="field-help">Adresa se vyplní automaticky podle názvu. Pokud ji upravíte ručně, použijte malá písmena, číslice a pomlčky.</small>
    <?php adminRenderFieldError('slug', $err, $fieldErrorMap, $fieldErrorMessages['slug']); ?>

    <label for="description">Popis</label>
    <textarea id="description" name="description" rows="4" aria-describedby="resource-description-help"><?= h($resource['description'] ?? '') ?></textarea>
    <small id="resource-description-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small>
    <?php renderAdminContentReferencePicker('description'); ?>

    <label for="category_id">Kategorie</label>
    <select id="category_id" name="category_id">
      <option value="">-- bez kategorie --</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>"
          <?= (int)($resource['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>><?= h($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="capacity">Max. osob na jednu rezervaci <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="number" id="capacity" name="capacity" min="1" required aria-required="true" class="admin-input-compact"
           <?= adminFieldAttributes('capacity', $err, $fieldErrorMap, ['resource-capacity-help']) ?>
           value="<?= (int)($resource['capacity'] ?? 1) ?>">
    <small id="resource-capacity-help" class="field-help">Kolik lidí může přijít v rámci jedné rezervace, například rodina nebo skupina.</small>
    <?php adminRenderFieldError('capacity', $err, $fieldErrorMap, $fieldErrorMessages['capacity']); ?>

    <fieldset class="res-resource-location-fieldset">
      <legend>Lokality rezervací</legend>
      <?php if (empty($locations)): ?>
        <p class="res-resource-empty-copy">Zatím tu nejsou žádné lokality rezervací. <a href="res_locations.php">Přidat lokalitu</a></p>
      <?php else: ?>
        <?php foreach ($locations as $loc): ?>
          <div class="res-resource-location-row">
            <input type="checkbox" id="loc_<?= (int)$loc['id'] ?>" name="location_ids[]" value="<?= (int)$loc['id'] ?>"
                   <?= in_array((int)$loc['id'], $selectedLocations) ? 'checked' : '' ?>>
            <label for="loc_<?= (int)$loc['id'] ?>" class="admin-checkbox-label">
              <?= h($loc['name']) ?><?php if ($loc['address'] !== ''): ?> <span class="res-resource-location-address">(<?= h($loc['address']) ?>)</span><?php endif; ?>
            </label>
          </div>
        <?php endforeach; ?>
        <p class="res-resource-manage-link"><a href="res_locations.php">Spravovat lokality rezervací</a></p>
      <?php endif; ?>
    </fieldset>
  </fieldset>

  <!-- B) Booking rules -->
  <fieldset class="res-resource-fieldset">
    <legend>Pravidla rezervací</legend>

    <fieldset class="res-resource-mode-fieldset">
      <legend>Způsob rezervací <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></legend>
      <div class="res-resource-option-row">
        <input type="radio" id="mode_slots" name="slot_mode" value="slots" aria-describedby="mode-slots-help"
               <?= ($resource['slot_mode'] ?? 'slots') === 'slots' ? 'checked' : '' ?>>
        <label for="mode_slots" class="admin-checkbox-label">Předdefinované sloty</label>
        <small id="mode-slots-help" class="field-help res-resource-help-indented">Přesné časové bloky pro každý den, například prohlídky muzea nebo vyhlídkové jízdy.</small>
      </div>
      <div class="res-resource-option-row">
        <input type="radio" id="mode_range" name="slot_mode" value="range" aria-describedby="mode-range-help"
               <?= ($resource['slot_mode'] ?? '') === 'range' ? 'checked' : '' ?>>
        <label for="mode_range" class="admin-checkbox-label">Časový rozsah</label>
        <small id="mode-range-help" class="field-help res-resource-help-indented">Uživatel zadá začátek a konec, například u pronájmu sálu nebo sportoviště.</small>
      </div>
      <div class="res-resource-option-row">
        <input type="radio" id="mode_duration" name="slot_mode" value="duration" aria-describedby="mode-duration-help"
               <?= ($resource['slot_mode'] ?? '') === 'duration' ? 'checked' : '' ?>>
        <label for="mode_duration" class="admin-checkbox-label">Délka</label>
        <small id="mode-duration-help" class="field-help res-resource-help-indented">Uživatel zadá začátek a délka je fixní, například masáž na 60 minut nebo tenisový kurt na 90 minut.</small>
      </div>
    </fieldset>

    <div id="duration-field"<?= ($resource['slot_mode'] ?? 'slots') !== 'duration' ? ' hidden' : '' ?>>
      <label for="slot_duration_min">Délka slotu (minuty)</label>
      <input type="number" id="slot_duration_min" name="slot_duration_min" min="1" class="admin-input-compact"
             value="<?= (int)($resource['slot_duration_min'] ?? 60) ?>">
    </div>

    <label for="min_advance_hours">Nejpozději rezervovat (hodin předem)</label>
    <input type="number" id="min_advance_hours" name="min_advance_hours" min="0" class="admin-input-compact"
           aria-describedby="resource-min-advance-help"
           value="<?= (int)($resource['min_advance_hours'] ?? 1) ?>">
    <small id="resource-min-advance-help" class="field-help">Například 24 znamená, že rezervaci je nutné vytvořit nejpozději den předem.</small>

    <label for="max_advance_days">Nejdříve rezervovat (dní dopředu)</label>
    <input type="number" id="max_advance_days" name="max_advance_days" min="1" class="admin-input-compact"
           aria-describedby="resource-max-advance-help"
           value="<?= (int)($resource['max_advance_days'] ?? 30) ?>">
    <small id="resource-max-advance-help" class="field-help">Například 30 znamená, že rezervaci lze vytvořit nejvýš 30 dní dopředu.</small>

    <label for="cancellation_hours">Bezplatné zrušení do (hodin předem)</label>
    <input type="number" id="cancellation_hours" name="cancellation_hours" min="0" class="admin-input-compact"
           aria-describedby="resource-cancellation-help"
           value="<?= (int)($resource['cancellation_hours'] ?? 24) ?>">
    <small id="resource-cancellation-help" class="field-help">Hodnota 0 znamená, že rezervaci lze zrušit kdykoli před začátkem.</small>

    <div class="res-resource-check-row">
      <input type="checkbox" id="requires_approval" name="requires_approval" value="1" aria-describedby="resource-requires-approval-help"
             <?= !empty($resource['requires_approval']) ? 'checked' : '' ?>>
      <label for="requires_approval" class="admin-checkbox-label">Vyžaduje schválení</label>
    </div>
    <small id="resource-requires-approval-help" class="field-help res-resource-help-tight">Rezervace bude čekat na ruční potvrzení administrátorem.</small>

    <div class="res-resource-check-row res-resource-check-row--compact">
      <input type="checkbox" id="allow_guests" name="allow_guests" value="1" aria-describedby="resource-allow-guests-help"
             <?= !empty($resource['allow_guests']) ? 'checked' : '' ?>>
      <label for="allow_guests" class="admin-checkbox-label">Povolit rezervace bez registrace</label>
    </div>
    <small id="resource-allow-guests-help" class="field-help res-resource-help-tight">Host vyplní jméno, e-mail a telefon přímo ve formuláři.</small>

    <label for="max_concurrent">Max. rezervací ve stejném čase</label>
    <input type="number" id="max_concurrent" name="max_concurrent" min="1" class="admin-input-compact"
           aria-describedby="resource-max-concurrent-help"
           value="<?= (int)($resource['max_concurrent'] ?? 1) ?>">
    <small id="resource-max-concurrent-help" class="field-help">Kolik nezávislých skupin nebo osob si může rezervovat stejný slot, například masáž = 1 a prohlídka = 10.</small>
  </fieldset>

  <!-- C) Opening hours -->
  <fieldset class="res-resource-fieldset"<?= adminFieldHasError('hours', $err, $fieldErrorMap) ? ' aria-describedby="hours-error"' : '' ?>>
    <legend>Otevírací doba</legend>
    <?php if (adminFieldHasError('hours', $err, $fieldErrorMap)): ?>
      <small id="hours-error" class="field-help field-error"><?= h($fieldErrorMessages['hours']) ?></small>
    <?php endif; ?>
    <table>
      <caption class="sr-only">Otevírací doba podle dnů</caption>
      <thead>
        <tr>
          <th scope="col">Den</th>
          <th scope="col">Zavřeno</th>
          <th scope="col">Otevření</th>
          <th scope="col">Zavření</th>
        </tr>
      </thead>
      <tbody>
      <?php for ($d = 0; $d < 7; $d++):
          $hRow      = $hoursByDay[$d] ?? null;
          $isClosed  = $hRow ? (int)$hRow['is_closed'] : ($id ? 1 : 0);
          $openTime  = $hRow ? substr($hRow['open_time'], 0, 5) : '09:00';
          $closeTime = $hRow ? substr($hRow['close_time'], 0, 5) : '17:00';
          ?>
        <tr>
          <td><?= h($dayNames[$d]) ?></td>
          <td>
            <label for="closed_<?= $d ?>" class="sr-only"><?= h($dayNames[$d]) ?> zavřeno</label>
            <input type="checkbox" id="closed_<?= $d ?>" name="hours[<?= $d ?>][is_closed]" value="1"
                   <?= $isClosed ? 'checked' : '' ?>>
          </td>
          <td>
            <label for="open_<?= $d ?>" class="sr-only"><?= h($dayNames[$d]) ?> otevření</label>
            <input type="time" id="open_<?= $d ?>" name="hours[<?= $d ?>][open_time]" class="admin-input-auto"
                   value="<?= h($openTime) ?>">
          </td>
          <td>
            <label for="close_<?= $d ?>" class="sr-only"><?= h($dayNames[$d]) ?> zavření</label>
            <input type="time" id="close_<?= $d ?>" name="hours[<?= $d ?>][close_time]" class="admin-input-auto"
                   value="<?= h($closeTime) ?>">
          </td>
        </tr>
      <?php endfor; ?>
      </tbody>
    </table>
  </fieldset>

  <!-- D) Predefined slots -->
  <fieldset id="slots-section" class="res-resource-fieldset"<?= ($resource['slot_mode'] ?? 'slots') !== 'slots' ? ' hidden' : '' ?><?= adminFieldHasError('slots', $err, $fieldErrorMap) ? ' aria-describedby="slots-error"' : '' ?>>
    <legend>Časy k rezervaci</legend>
    <?php if (adminFieldHasError('slots', $err, $fieldErrorMap)): ?>
      <small id="slots-error" class="field-help field-error"><?= h($fieldErrorMessages['slots']) ?></small>
    <?php endif; ?>

    <fieldset class="res-resource-generator">
      <legend>Hromadné přidání slotů</legend>
      <div class="res-resource-inline-grid">
        <div>
          <label for="gen_from">První slot od</label>
          <input type="time" id="gen_from" class="res-resource-inline-control" value="09:00">
        </div>
        <div>
          <label for="gen_length">Délka slotu (min)</label>
          <input type="number" id="gen_length" min="1" class="res-resource-inline-control res-resource-inline-control--number" value="60">
        </div>
        <div>
          <label for="gen_gap">Mezera (min)</label>
          <input type="number" id="gen_gap" min="0" class="res-resource-inline-control res-resource-inline-control--number" value="0">
        </div>
        <div>
          <label for="gen_until">Poslední slot do</label>
          <input type="time" id="gen_until" class="res-resource-inline-control" value="17:00">
        </div>
        <button type="button" id="btn-generate" class="btn res-resource-inline-button">Generovat sloty</button>
      </div>
    </fieldset>

    <?php for ($d = 0; $d < 7; $d++): ?>
    <fieldset class="res-resource-day-fieldset" data-day="<?= $d ?>">
      <legend><?= h($dayNames[$d]) ?></legend>
      <div class="day-slots" id="day-slots-<?= $d ?>">
        <?php if (!empty($slotsByDay[$d])): ?>
          <?php foreach ($slotsByDay[$d] as $si => $sl): ?>
            <div class="slot-row res-resource-slot-row">
              <label for="slot_start_<?= $d ?>_<?= $si ?>" class="sr-only">Začátek</label>
              <input type="time" id="slot_start_<?= $d ?>_<?= $si ?>" name="slots[<?= $d ?>][start_time][]" class="admin-input-auto"
                     value="<?= h(substr($sl['start_time'], 0, 5)) ?>">
              <label for="slot_end_<?= $d ?>_<?= $si ?>" class="sr-only">Konec</label>
              <input type="time" id="slot_end_<?= $d ?>_<?= $si ?>" name="slots[<?= $d ?>][end_time][]" class="admin-input-auto"
                     value="<?= h(substr($sl['end_time'], 0, 5)) ?>">
              <label for="slot_max_<?= $d ?>_<?= $si ?>" class="sr-only">Max. rezervací</label>
              <input type="number" id="slot_max_<?= $d ?>_<?= $si ?>" name="slots[<?= $d ?>][max_bookings][]" min="1" class="res-resource-input-xxs"
                     value="<?= (int)$sl['max_bookings'] ?>" title="Max. rezervací">
              <button type="button" class="btn btn-danger btn-remove-slot"
                      aria-label="Odebrat slot" data-remove-slot>Odebrat</button>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <button type="button" class="btn btn-add-slot res-resource-add-slot" data-day="<?= $d ?>" data-add-slot>+ Přidat slot</button>
    </fieldset>
    <?php endfor; ?>
  </fieldset>

  <!-- E) Blocked dates -->
  <fieldset class="res-resource-fieldset"<?= adminFieldHasError('blocked_dates', $err, $fieldErrorMap) ? ' aria-describedby="blocked-dates-error"' : '' ?>>
    <legend>Blokované dny</legend>
    <?php if (adminFieldHasError('blocked_dates', $err, $fieldErrorMap)): ?>
      <small id="blocked-dates-error" class="field-help field-error"><?= h($fieldErrorMessages['blocked_date']) ?></small>
    <?php endif; ?>
    <div class="res-resource-inline-grid res-resource-blocked-grid">
      <div>
        <label for="block_date">Datum</label>
        <input type="date" id="block_date" class="res-resource-inline-control">
      </div>
      <div>
        <label for="block_reason">Důvod</label>
        <input type="text" id="block_reason" maxlength="255" class="res-resource-inline-control">
      </div>
      <button type="button" id="btn-add-blocked" class="btn res-resource-inline-button">Přidat</button>
    </div>
    <input type="hidden" name="deleted_blocked_ids" id="deleted_blocked_ids" value="">
    <div id="blocked-list">
      <?php foreach ($blocked as $bi => $bl): ?>
        <?php
          $blockedDateFieldId = 'blocked-date-' . (int)$bl['id'] . '-' . (int)$bi;
          $blockedReasonFieldId = 'blocked-reason-' . (int)$bl['id'] . '-' . (int)$bi;
          ?>
        <div class="blocked-row res-resource-blocked-row" data-blocked-id="<?= (int)$bl['id'] ?>">
          <input type="hidden" name="blocked_ids[]" value="<?= (int)$bl['id'] ?>">
          <label for="<?= h($blockedDateFieldId) ?>" class="sr-only">Datum blokování</label>
          <input type="date" id="<?= h($blockedDateFieldId) ?>" name="blocked_dates[]" value="<?= h($bl['blocked_date']) ?>" class="admin-input-auto">
          <label for="<?= h($blockedReasonFieldId) ?>" class="sr-only">Důvod blokování</label>
          <input type="text" id="<?= h($blockedReasonFieldId) ?>" name="blocked_reasons[]" value="<?= h($bl['reason'] ?? '') ?>" maxlength="255" class="admin-input-auto">
          <button type="button" class="btn btn-danger"
                  aria-label="Odebrat blokovaný den" data-remove-blocked>Odebrat</button>
        </div>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <div class="button-row res-resource-actions">
    <button type="submit" class="btn"><?= $id ? 'Uložit změny' : 'Vytvořit zdroj rezervací' ?></button>
    <a href="res_resources.php">Zrušit</a>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
(function () {
  var live = document.getElementById('a11y-live');

  // ── Slug auto-generation ──
  var nameInput = document.getElementById('name');
  var slugInput = document.getElementById('slug');
  var slugManual = <?= $id ? 'true' : 'false' ?>;

  slugInput.addEventListener('input', function () { slugManual = true; });

  nameInput.addEventListener('input', function () {
    if (slugManual) return;
    slugInput.value = this.value
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '');
  });

  // ── Show/hide slot_duration_min & slots section ──
  var radios = document.querySelectorAll('input[name="slot_mode"]');
  var durationField = document.getElementById('duration-field');
  var slotsSection  = document.getElementById('slots-section');

  function updateModeVisibility() {
    var mode = document.querySelector('input[name="slot_mode"]:checked').value;
    durationField.hidden = mode !== 'duration';
    slotsSection.hidden = mode !== 'slots';
  }
  for (var i = 0; i < radios.length; i++) {
    radios[i].addEventListener('change', updateModeVisibility);
  }

  // ── Slot counter for unique IDs ──
  var slotCounter = document.querySelectorAll('.slot-row').length + 100;

  // ── Add slot ──
  function addSlot(day) {
    var container = document.getElementById('day-slots-' + day);
    if (!container) {
      return;
    }
    var idx = slotCounter++;
    var div = document.createElement('div');
    div.className = 'slot-row res-resource-slot-row';
    div.innerHTML =
      '<label for="slot_start_' + day + '_' + idx + '" class="sr-only">Začátek</label>' +
      '<input type="time" id="slot_start_' + day + '_' + idx + '" name="slots[' + day + '][start_time][]" class="admin-input-auto" value="09:00">' +
      '<label for="slot_end_' + day + '_' + idx + '" class="sr-only">Konec</label>' +
      '<input type="time" id="slot_end_' + day + '_' + idx + '" name="slots[' + day + '][end_time][]" class="admin-input-auto" value="10:00">' +
      '<label for="slot_max_' + day + '_' + idx + '" class="sr-only">Max. rezervací</label>' +
      '<input type="number" id="slot_max_' + day + '_' + idx + '" name="slots[' + day + '][max_bookings][]" min="1" class="res-resource-input-xxs" value="1" title="Max. rezervací">' +
      '<button type="button" class="btn btn-danger btn-remove-slot" aria-label="Odebrat slot" data-remove-slot>Odebrat</button>';
    container.appendChild(div);
    div.querySelector('input[type="time"]').focus();
    if (live) live.textContent = 'Slot přidán';
  }

  // ── Remove slot ──
  function removeSlot(btn) {
    var row = btn.closest('.slot-row');
    if (!row || !row.parentNode) {
      return;
    }
    var container = row.parentNode;
    var prev = row.previousElementSibling;
    row.remove();
    if (prev) { var inp = prev.querySelector('input[type="time"]'); if (inp) inp.focus(); }
    else { var first = container.querySelector('input[type="time"]'); if (first) first.focus(); }
    if (live) live.textContent = 'Slot odebrán';
  }

  document.querySelectorAll('[data-add-slot]').forEach(function (button) {
    button.addEventListener('click', function () {
      addSlot(button.getAttribute('data-day'));
    });
  });

  if (slotsSection) {
    slotsSection.addEventListener('click', function (event) {
      var target = event.target;
      if (!(target instanceof Element)) {
        return;
      }
      var button = target.closest('[data-remove-slot]');
      if (!button || !slotsSection.contains(button)) {
        return;
      }
      removeSlot(button);
    });
  }

  // ── Bulk generator ──
  document.getElementById('btn-generate').addEventListener('click', function () {
    var from   = document.getElementById('gen_from').value;
    var length = parseInt(document.getElementById('gen_length').value, 10);
    var gap    = parseInt(document.getElementById('gen_gap').value, 10);
    var until  = document.getElementById('gen_until').value;

    if (!from || !until || !length || length < 1) {
      alert('Vyplňte všechny parametry generátoru.');
      return;
    }

    function timeToMin(t) { var p = t.split(':'); return parseInt(p[0],10)*60 + parseInt(p[1],10); }
    function minToTime(m) { var hh = Math.floor(m/60); var mm = m%60; return (hh<10?'0':'')+hh+':'+(mm<10?'0':'')+mm; }

    var startMin = timeToMin(from);
    var endMin   = timeToMin(until);
    var generated = [];

    while (startMin + length <= endMin) {
      generated.push({ start: minToTime(startMin), end: minToTime(startMin + length) });
      startMin += length + gap;
    }

    if (generated.length === 0) {
      alert('Nelze vygenerovat žádné sloty s danými parametry.');
      return;
    }

    for (var d = 0; d < 7; d++) {
      var container = document.getElementById('day-slots-' + d);
      container.innerHTML = '';
      for (var g = 0; g < generated.length; g++) {
        var idx = slotCounter++;
        var div = document.createElement('div');
        div.className = 'slot-row res-resource-slot-row';
        div.innerHTML =
          '<label for="slot_start_' + d + '_' + idx + '" class="sr-only">Začátek</label>' +
          '<input type="time" id="slot_start_' + d + '_' + idx + '" name="slots[' + d + '][start_time][]" class="admin-input-auto" value="' + generated[g].start + '">' +
          '<label for="slot_end_' + d + '_' + idx + '" class="sr-only">Konec</label>' +
          '<input type="time" id="slot_end_' + d + '_' + idx + '" name="slots[' + d + '][end_time][]" class="admin-input-auto" value="' + generated[g].end + '">' +
          '<label for="slot_max_' + d + '_' + idx + '" class="sr-only">Max. rezervací</label>' +
          '<input type="number" id="slot_max_' + d + '_' + idx + '" name="slots[' + d + '][max_bookings][]" min="1" class="res-resource-input-xxs" value="1" title="Max. rezervací">' +
          '<button type="button" class="btn btn-danger btn-remove-slot" aria-label="Odebrat slot" data-remove-slot>Odebrat</button>';
        container.appendChild(div);
      }
    }

    if (live) live.textContent = 'Vygenerováno ' + generated.length + ' slotů pro každý den';
  });

  // ── Blocked dates ──
  var blockedList = document.getElementById('blocked-list');
  var blockedCounter = <?= count($blocked) ?>;
  var deletedBlockedIds = [];

  document.getElementById('btn-add-blocked').addEventListener('click', function () {
    var dateVal   = document.getElementById('block_date').value;
    var reasonVal = document.getElementById('block_reason').value;
    if (!dateVal) { alert('Zadejte datum.'); return; }

    var idx = blockedCounter++;
    var dateFieldId = 'blocked-date-new-' + idx;
    var reasonFieldId = 'blocked-reason-new-' + idx;
    var div = document.createElement('div');
    div.className = 'blocked-row res-resource-blocked-row';
    div.innerHTML =
      '<input type="hidden" name="blocked_ids[]" value="0">' +
      '<label for="' + dateFieldId + '" class="sr-only">Datum blokování</label>' +
      '<input type="date" id="' + dateFieldId + '" name="blocked_dates[]" value="' + dateVal + '" class="admin-input-auto">' +
      '<label for="' + reasonFieldId + '" class="sr-only">Důvod blokování</label>' +
      '<input type="text" id="' + reasonFieldId + '" name="blocked_reasons[]" value="' + reasonVal.replace(/"/g, '&quot;') + '" maxlength="255" class="admin-input-auto">' +
      '<button type="button" class="btn btn-danger" aria-label="Odebrat blokovaný den" data-remove-blocked>Odebrat</button>';
    blockedList.appendChild(div);

    document.getElementById('block_date').value = '';
    document.getElementById('block_reason').value = '';
    document.getElementById('block_date').focus();
    if (live) live.textContent = 'Blokovaný den přidán';
  });

  function removeBlocked(btn) {
    var row = btn.closest('.blocked-row');
    if (!row) {
      return;
    }
    var bid = row.getAttribute('data-blocked-id');
    if (bid && bid !== '0') {
      deletedBlockedIds.push(bid);
      document.getElementById('deleted_blocked_ids').value = deletedBlockedIds.join(',');
    }
    row.remove();
    if (live) live.textContent = 'Blokovaný den odebrán';
  }

  if (blockedList) {
    blockedList.addEventListener('click', function (event) {
      var target = event.target;
      if (!(target instanceof Element)) {
        return;
      }
      var button = target.closest('[data-remove-blocked]');
      if (!button || !blockedList.contains(button)) {
        return;
      }
      removeBlocked(button);
    });
  }
})();
</script>

<?php adminFooter(); ?>
