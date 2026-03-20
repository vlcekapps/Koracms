<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach (['blog', 'news', 'chat', 'contact', 'gallery', 'events', 'podcast', 'places', 'newsletter', 'downloads', 'food', 'polls', 'faq', 'board', 'reservations'] as $m) {
        saveSetting('module_' . $m, isset($_POST['module_' . $m]) ? '1' : '0');
    }
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
    ] as $k => $label): ?>
      <div>
        <input type="checkbox" id="module_<?= $k ?>" name="module_<?= $k ?>" value="1"
               <?= isModuleEnabled($k) ? 'checked' : '' ?>>
        <label for="module_<?= $k ?>" style="display:inline;font-weight:normal"><?= h($label) ?></label>
      </div>
    <?php endforeach; ?>
  </fieldset>

  <button type="submit" style="margin-top:1rem">Uložit moduly</button>
</form>

<?php adminFooter(); ?>
