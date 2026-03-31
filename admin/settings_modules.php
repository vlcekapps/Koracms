<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$moduleKeys = ['blog', 'news', 'chat', 'contact', 'gallery', 'events', 'podcast', 'places', 'newsletter', 'downloads', 'food', 'polls', 'faq', 'board', 'reservations', 'forms', 'statistics'];
$flash = is_array($_SESSION['settings_modules_flash'] ?? null) ? $_SESSION['settings_modules_flash'] : [];
unset($_SESSION['settings_modules_flash']);
$successMessage = trim((string)($flash['success'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach ($moduleKeys as $m) {
        saveSetting('module_' . $m, isset($_POST['module_' . $m]) ? '1' : '0');
    }
    saveSetting('visitor_tracking_enabled', isset($_POST['visitor_tracking_enabled']) ? '1' : '0');
    saveSetting('visitor_counter_enabled', isset($_POST['visitor_counter_enabled']) ? '1' : '0');
    $retDays = max(1, (int)($_POST['stats_retention_days'] ?? 90));
    saveSetting('stats_retention_days', (string)$retDays);
    clearSettingsCache();
    logAction('settings_modules_save');
    $_SESSION['settings_modules_flash'] = [
        'success' => 'Nastavení modulů bylo uloženo.',
    ];
    header('Location: ' . BASE_URL . '/admin/settings_modules.php');
    exit;
}

adminHeader('Správa modulů');
?>

<?php if ($successMessage !== ''): ?>
  <p class="success" role="status"><?= h($successMessage) ?></p>
<?php endif; ?>

<p>Tady zapnete nebo vypnete jednotlivé moduly webu. Aktivní moduly se podle svého nastavení zobrazují návštěvníkům na veřejném webu.</p>

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
      'forms'        => 'Formuláře',
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
      <label for="visitor_counter_enabled" style="display:inline;font-weight:normal">Povolit widget Statistiky návštěvnosti na veřejném webu</label>
    </div>
    <p class="field-help" style="margin:.35rem 0 0">Samotné umístění statistik nastavíte ve <a href="widgets.php">Správě widgetů</a>, typicky ve footer zóně.</p>
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
