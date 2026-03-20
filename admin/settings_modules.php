<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach (['blog', 'news', 'chat', 'contact', 'gallery', 'events', 'podcast', 'places', 'newsletter', 'downloads', 'food', 'polls', 'faq', 'board', 'reservations', 'statistics'] as $m) {
        saveSetting('module_' . $m, isset($_POST['module_' . $m]) ? '1' : '0');
    }
    saveSetting('visitor_tracking_enabled', isset($_POST['visitor_tracking_enabled']) ? '1' : '0');
    saveSetting('visitor_counter_enabled', isset($_POST['visitor_counter_enabled']) ? '1' : '0');
    $retDays = max(1, (int)($_POST['stats_retention_days'] ?? 90));
    saveSetting('stats_retention_days', (string)$retDays);
    logAction('settings_modules_save');
    $success = true;
}

adminHeader('Moduly');
?>

<?php if ($success): ?>
  <p class="success" role="status">Nastavení modulů bylo uloženo.</p>
<?php endif; ?>

<p>Zaškrtnuté moduly jsou aktivní a zobrazují se návštěvníkům v navigaci.</p>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

  <fieldset>
    <legend>Aktivní moduly</legend>
    <?php foreach ([
      'blog'      => 'Blog',
      'news'      => 'Novinky',
      'chat'      => 'Chat',
      'contact'   => 'Kontakt',
      'gallery'   => 'Galerie',
      'events'    => 'Události',
      'podcast'   => 'Podcast',
      'places'    => 'Zajímavá místa',
      'newsletter'=> 'Newsletter',
      'downloads' => 'Ke stažení',
      'food'      => 'Jídelní lístek',
      'polls'     => 'Ankety',
      'faq'       => 'FAQ',
      'board'     => 'Úřední deska',
      'reservations' => 'Rezervace',
      'statistics'   => 'Statistiky (admin dashboard)',
    ] as $k => $label): ?>
      <div>
        <input type="checkbox" id="module_<?= $k ?>" name="module_<?= $k ?>" value="1"
               <?= isModuleEnabled($k) ? 'checked' : '' ?>>
        <label for="module_<?= $k ?>" style="display:inline;font-weight:normal"><?= h($label) ?></label>
      </div>
    <?php endforeach; ?>
  </fieldset>

  <fieldset style="margin-top:1.5rem">
    <legend>Sledování návštěvnosti</legend>
    <div>
      <input type="checkbox" id="visitor_tracking_enabled" name="visitor_tracking_enabled" value="1"
             <?= getSetting('visitor_tracking_enabled', '0') === '1' ? 'checked' : '' ?>>
      <label for="visitor_tracking_enabled" style="display:inline;font-weight:normal">Sledovat návštěvnost webu</label>
    </div>
    <div>
      <input type="checkbox" id="visitor_counter_enabled" name="visitor_counter_enabled" value="1"
             <?= getSetting('visitor_counter_enabled', '0') === '1' ? 'checked' : '' ?>>
      <label for="visitor_counter_enabled" style="display:inline;font-weight:normal">Zobrazit počítadlo návštěv v patičce webu</label>
    </div>
    <div style="margin-top:.5rem">
      <label for="stats_retention_days">Uchovávat podrobná data (dní):</label>
      <input type="number" id="stats_retention_days" name="stats_retention_days" min="1" max="3650"
             value="<?= h(getSetting('stats_retention_days', '90')) ?>"
             style="width:6rem" aria-describedby="retention-hint">
      <small id="retention-hint">Po této době se raw data smažou, souhrnné statistiky zůstanou.</small>
    </div>
  </fieldset>

  <button type="submit" style="margin-top:1rem">Uložit moduly</button>
</form>

<?php adminFooter(); ?>
