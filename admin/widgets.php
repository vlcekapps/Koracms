<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('settings_manage', 'Přístup odepřen. Pro správu widgetů nemáte potřebné oprávnění.');

$pdo = db_connect();
$allWidgets = getAllWidgetsByZone();
$available = availableWidgetTypes();
$zones = widgetZoneDefinitions();
$types = widgetTypeDefinitions();
$selectedAddZone = trim((string)($_GET['zone'] ?? 'homepage'));
if (!isset($zones[$selectedAddZone])) {
    $selectedAddZone = 'homepage';
}

$allBlogs = getAllBlogs();
$allAlbums = [];
$allShows = [];
$allForms = [];
$featuredSourceOptions = [
    'blog' => 'Blog (nejčtenější)',
];
if (isModuleEnabled('board')) {
    $featuredSourceOptions['board'] = 'Vývěska (nejnovější)';
}
if (isModuleEnabled('polls')) {
    $featuredSourceOptions['poll'] = 'Anketa';
}
if (isModuleEnabled('newsletter')) {
    $featuredSourceOptions['newsletter'] = 'Newsletter';
}
try {
    $allAlbums = $pdo->query("SELECT id, name FROM cms_gallery_albums ORDER BY name")->fetchAll();
} catch (\PDOException $e) {
}
try {
    $allShows = $pdo->query("SELECT id, title FROM cms_podcast_shows ORDER BY title ASC")->fetchAll();
} catch (\PDOException $e) {
}
try {
    $allForms = $pdo->query("SELECT id, title FROM cms_forms WHERE is_active = 1 ORDER BY title ASC")->fetchAll();
} catch (\PDOException $e) {
}

adminHeader('Widgety');
?>

<p class="widgets-intro">Přidávejte, přesouvejte a nastavujte widgety v jednotlivých zónách webu. Přetažením myší nebo klávesou Ctrl+šipka změníte pořadí. Aktivní widgety, které se teď na webu nedokážou zobrazit, tu uvidíte s vysvětlením přímo nad tlačítkem Nastavení.</p>

<fieldset id="widget-add" class="widget-panel">
  <legend>Přidat widget do zóny</legend>
  <div class="widget-add-zone">
    <label for="widget-add-zone">Cílová zóna</label>
    <select id="widget-add-zone" name="widget_add_zone">
      <?php foreach ($zones as $zoneKey => $zoneLabel): ?>
        <option value="<?= h($zoneKey) ?>"<?= $selectedAddZone === $zoneKey ? ' selected' : '' ?>><?= h($zoneLabel) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="widget-add-actions">
    <?php foreach ($available as $wType => $wDef): ?>
      <form method="post" action="widget_add.php" class="widget-inline-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="widget_type" value="<?= h($wType) ?>">
        <input type="hidden" name="zone" value="<?= h($selectedAddZone) ?>" class="widget-add-zone-input">
        <button type="submit" class="btn widget-button--compact">Přidat <?= h($wDef['name']) ?></button>
      </form>
    <?php endforeach; ?>
    <?php if (empty($available)): ?>
      <p class="field-help widget-empty">Žádné widgety nejsou dostupné. Zapněte moduly v <a href="settings_modules.php">Správě modulů</a>.</p>
    <?php endif; ?>
  </div>
</fieldset>

<?php foreach ($zones as $zoneKey => $zoneLabel): ?>
  <fieldset id="widget-zone-<?= h($zoneKey) ?>" class="widget-panel">
    <legend><?= h($zoneLabel) ?></legend>

    <?php if (empty($allWidgets[$zoneKey])): ?>
      <p class="field-help widget-empty">Žádné widgety v této zóně. <a href="widgets.php?zone=<?= h($zoneKey) ?>#widget-add">Vyberte <?= h(mb_strtolower($zoneLabel)) ?> a přidejte první widget</a>.</p>
    <?php else: ?>
      <ol class="widget-sort-list" data-sortable="widgets" data-zone="<?= h($zoneKey) ?>">
        <?php foreach ($allWidgets[$zoneKey] as $w):
            $wSettings = widgetSettings($w);
            $wTypeDef = $types[$w['widget_type']] ?? null;
            $wTypeName = $wTypeDef ? $wTypeDef['name'] : $w['widget_type'];
            $wTitle = trim((string)($w['title'] ?? ''));
            $wDisplayTitle = $wTitle !== '' ? $wTitle : $wTypeName;
            $wMetaParts = [];
            if ($wTitle !== '' && $wTitle !== $wTypeName) {
                $wMetaParts[] = $wTypeName;
            }
            if (!(int)$w['is_active']) {
                $wMetaParts[] = 'neaktivní';
            }
            $wAvailability = widgetInstanceAvailability($w);
            $wDisplayable = $wAvailability['displayable'];
            $wDisplayWarning = (int)$w['is_active'] === 1 && !$wDisplayable;
            $wDisplayReasons = $wAvailability['reasons'];
            if ($wDisplayWarning) {
                $wMetaParts[] = 'na webu se teď nezobrazí';
            }
            ?>
          <li class="widget-sort-item<?= !(int)$w['is_active'] ? ' widget-sort-item--inactive' : '' ?>"
              data-sort-id="<?= (int)$w['id'] ?>" tabindex="0"
              aria-label="<?= h($wDisplayTitle) ?> (<?= h($wTypeName) ?>)">

            <div class="widget-sort-item__body">
              <strong><?= h($wDisplayTitle) ?></strong>
              <?php if ($wMetaParts !== []): ?>
                <br><small class="widget-sort-item__meta"><?= h(implode(' · ', $wMetaParts)) ?></small>
              <?php endif; ?>
            </div>

            <div class="widget-sort-item__tools">
              <?php if ($wDisplayWarning && $wDisplayReasons !== []): ?>
                <p class="field-help widget-sort-item__warning">Na webu se teď nezobrazí: <?= h(implode('; ', $wDisplayReasons)) ?>.</p>
              <?php endif; ?>
              <div class="widget-sort-item__actions">
                <button type="button" class="btn widget-button--compact widget-edit-btn"
                        aria-label="Nastavení widgetu <?= h($wDisplayTitle) ?>"
                        aria-haspopup="dialog"
                        aria-controls="widget-dialog"
                        aria-expanded="false"
                        data-widget-id="<?= (int)$w['id'] ?>"
                        data-widget-title="<?= h($w['title']) ?>"
                        data-widget-type="<?= h($w['widget_type']) ?>"
                        data-widget-zone="<?= h($w['zone']) ?>"
                        data-widget-active="<?= (int)$w['is_active'] ?>"
                        data-widget-settings="<?= h(json_encode($wSettings, JSON_UNESCAPED_UNICODE)) ?>">Nastavení</button>
                <form method="post" action="widget_delete.php" class="widget-inline-form">
                  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                  <input type="hidden" name="widget_id" value="<?= (int)$w['id'] ?>">
                  <button type="submit" class="btn btn-danger widget-button--compact"
                          data-confirm="<?= h('Odebrat widget „' . $wDisplayTitle . '“?') ?>"
                          aria-label="Odebrat widget <?= h($wDisplayTitle) ?>">✕</button>
                </form>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </fieldset>
<?php endforeach; ?>

<!-- Modal dialog pro nastavení widgetu -->
<div id="widget-overlay" class="widget-dialog-overlay" hidden></div>
<section id="widget-dialog" class="widget-dialog" role="dialog" aria-modal="true" aria-labelledby="widget-dialog-title" aria-describedby="widget-dialog-description" hidden>
  <div class="widget-dialog__header">
    <h2 id="widget-dialog-title" class="widget-dialog__title">Nastavení widgetu</h2>
    <button type="button" id="widget-dialog-close" class="btn" aria-label="Zavřít dialog">✕</button>
  </div>
  <p id="widget-dialog-description" class="field-help widget-dialog__description">Upravte název, zónu a další nastavení vybraného widgetu.</p>
  <form method="post" action="widget_save.php" novalidate id="widget-dialog-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="widget_id" id="wd-id">

    <fieldset id="wd-basic-fieldset" class="widget-dialog-fieldset">
      <legend>Základní nastavení widgetu</legend>

      <label for="wd-title">Název widgetu</label>
      <input type="text" id="wd-title" name="title" maxlength="255">

      <label for="wd-zone">Zóna</label>
      <select id="wd-zone" name="zone">
        <?php foreach ($zones as $zk => $zl): ?>
          <option value="<?= h($zk) ?>"><?= h($zl) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="widget-dialog-checkbox">
        <input type="checkbox" name="is_active" value="1" id="wd-active"> Aktivní
      </label>
    </fieldset>

    <fieldset id="wd-dynamic-fieldset" class="widget-dialog-fieldset widget-dialog-fieldset--dynamic" hidden aria-hidden="true" aria-describedby="wd-dynamic-help">
      <legend id="wd-dynamic-legend">Doplňující nastavení widgetu</legend>
      <p id="wd-dynamic-help" class="field-help widget-dialog__description">Zobrazují se jen pole, která jsou relevantní pro vybraný typ widgetu.</p>

      <!-- Dynamická pole dle typu -->
      <div id="wd-field-count" class="widget-dialog-field" hidden aria-hidden="true">
        <label for="wd-count">Počet položek</label>
        <input type="number" id="wd-count" class="widget-dialog-number-input" name="widget_count" min="1" max="50" value="5" disabled>
      </div>

      <div id="wd-field-blog" class="widget-dialog-field widget-dialog-field--compact" hidden aria-hidden="true">
        <label for="wd-blog">Blog</label>
        <select id="wd-blog" name="widget_blog_id" aria-describedby="wd-blog-help" disabled>
          <option value="0">Všechny blogy</option>
          <?php if (count($allBlogs) > 1): ?>
            <option value="-1">Aktuální blog (na blogových stránkách)</option>
          <?php endif; ?>
          <?php foreach ($allBlogs as $b): ?>
            <option value="<?= (int)$b['id'] ?>"><?= h($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <small id="wd-blog-help" class="field-help">Vyberte konkrétní blog, nebo nechte widget reagovat na právě otevřený blog.</small>
      </div>

      <div id="wd-field-source" class="widget-dialog-field" hidden aria-hidden="true">
        <label for="wd-source">Zdroj</label>
        <select id="wd-source" name="widget_source" disabled>
          <?php foreach ($featuredSourceOptions as $sourceKey => $sourceLabel): ?>
            <option value="<?= h($sourceKey) ?>"><?= h($sourceLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="wd-field-cta" class="widget-dialog-field" hidden aria-hidden="true">
        <label for="wd-cta">Úvodní text widgetu</label>
        <input type="text" id="wd-cta" name="widget_cta_text" maxlength="500" aria-describedby="wd-cta-help" disabled>
        <small id="wd-cta-help" class="field-help">Krátký doprovodný text zobrazený nad vyhledáváním nebo newsletter formulářem.</small>
      </div>

      <div id="wd-field-album" class="widget-dialog-field" hidden aria-hidden="true">
        <label for="wd-album">Album</label>
        <select id="wd-album" name="widget_album_id" disabled>
          <option value="0">Všechny fotky</option>
          <?php foreach ($allAlbums as $alb): ?>
            <option value="<?= (int)$alb['id'] ?>"><?= h($alb['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="wd-field-show" class="widget-dialog-field" hidden aria-hidden="true">
        <label for="wd-show">Pořad</label>
        <select id="wd-show" name="widget_show_id" disabled>
          <option value="0">Všechny pořady</option>
          <?php foreach ($allShows as $show): ?>
            <option value="<?= (int)$show['id'] ?>"><?= h($show['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="wd-field-form" class="widget-dialog-field" hidden aria-hidden="true">
        <label for="wd-form">Formulář</label>
        <select id="wd-form" name="widget_form_id" aria-describedby="wd-form-help" disabled>
          <option value="0">Vyberte formulář</option>
          <?php foreach ($allForms as $form): ?>
            <option value="<?= (int)$form['id'] ?>"><?= h($form['title']) ?></option>
          <?php endforeach; ?>
        </select>
        <small id="wd-form-help" class="field-help">Na webu se zobrazí jen aktivní formulář.</small>
      </div>

      <div id="wd-field-content" class="widget-dialog-field" hidden aria-hidden="true">
        <label for="wd-content">HTML obsah</label>
        <textarea id="wd-content" name="widget_content" rows="6" aria-describedby="wd-content-help" disabled></textarea>
        <small id="wd-content-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small>
        <?php renderAdminContentReferencePicker('wd-content'); ?>
      </div>

      <div id="wd-field-social" class="widget-dialog-field" hidden aria-hidden="true">
        <fieldset class="widget-dialog-fieldset widget-dialog-fieldset--nested">
          <legend>Odkazy na sociální sítě</legend>
          <p id="wd-social-help" class="field-help widget-dialog__description">Vyplňte jen odkazy, které chcete v tomto widgetu zobrazit. Když pole necháte prázdná, widget se na webu nevykreslí.</p>
          <?php foreach (widgetSocialLinkDefinitions() as $socialSettingKey => $socialLabel): ?>
            <?php $socialFieldId = 'wd-' . str_replace('_', '-', $socialSettingKey); ?>
            <label for="<?= h($socialFieldId) ?>"><?= h($socialLabel) ?></label>
            <input type="url" id="<?= h($socialFieldId) ?>" name="widget_<?= h($socialSettingKey) ?>" placeholder="https://" aria-describedby="wd-social-help" disabled>
          <?php endforeach; ?>
        </fieldset>
      </div>
    </fieldset>

    <div class="button-row widget-dialog-actions">
      <button type="submit" class="btn">Uložit</button>
      <button type="button" id="widget-dialog-cancel" class="btn">Zrušit</button>
    </div>
  </form>
</section>

<script nonce="<?= cspNonce() ?>">
(function(){
  var overlay = document.getElementById('widget-overlay');
  var dialog = document.getElementById('widget-dialog');
  var closeBtn = document.getElementById('widget-dialog-close');
  var cancelBtn = document.getElementById('widget-dialog-cancel');
  var addZoneSelect = document.getElementById('widget-add-zone');
  var lastTrigger = null;
  var countTypes = ['latest_articles','latest_news','board','upcoming_events','latest_downloads','latest_faq','latest_places','latest_podcast_episodes'];
  var socialFieldKeys = ['social_facebook','social_youtube','social_instagram','social_twitter'];
  var multiBlog = <?= count($allBlogs) > 1 ? 'true' : 'false' ?>;
  var dialogTitle = document.getElementById('widget-dialog-title');
  var dynamicFieldset = document.getElementById('wd-dynamic-fieldset');

  function setFieldDisabledState(container, disabled) {
    container.querySelectorAll('input, select, textarea, button').forEach(function(el){
      if (el.type === 'hidden') return;
      el.disabled = disabled;
    });
  }

  function toggleDialogField(fieldName, visible) {
    var field = document.getElementById('wd-field-' + fieldName);
    if (!field) return;
    field.hidden = !visible;
    field.setAttribute('aria-hidden', visible ? 'false' : 'true');
    setFieldDisabledState(field, !visible);
  }

  function setDynamicFieldsetVisibility(visible) {
    dynamicFieldset.hidden = !visible;
    dynamicFieldset.setAttribute('aria-hidden', visible ? 'false' : 'true');
  }

  function getDialogFocusableElements() {
    return Array.from(dialog.querySelectorAll('a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
      .filter(function(el){
        return !el.hidden && el.offsetParent !== null;
      });
  }

  function syncAddZoneInputs() {
    if (!addZoneSelect) return;
    document.querySelectorAll('.widget-add-zone-input').forEach(function(input){
      input.value = addZoneSelect.value;
    });
  }

  syncAddZoneInputs();
  if (addZoneSelect) {
    addZoneSelect.addEventListener('change', syncAddZoneInputs);
  }

  function openDialog(btn) {
    lastTrigger = btn;
    var s = JSON.parse(btn.dataset.widgetSettings || '{}');
    var type = btn.dataset.widgetType;
    var dialogHeadingName = btn.dataset.widgetTitle
      || (btn.closest('[data-sort-id]') && btn.closest('[data-sort-id]').querySelector('strong')
        ? btn.closest('[data-sort-id]').querySelector('strong').textContent.trim()
        : '')
      || 'Widget';

    document.getElementById('wd-id').value = btn.dataset.widgetId;
    document.getElementById('wd-title').value = btn.dataset.widgetTitle;
    document.getElementById('wd-zone').value = btn.dataset.widgetZone;
    document.getElementById('wd-active').checked = btn.dataset.widgetActive === '1';
    dialogTitle.textContent = 'Nastavení widgetu: ' + dialogHeadingName;

    // Skrýt všechna dynamická pole
    ['count','blog','source','cta','album','show','form','content','social'].forEach(function(f){
      toggleDialogField(f, false);
    });
    socialFieldKeys.forEach(function(key){
      var input = document.querySelector('[name="widget_' + key + '"]');
      if (input) {
        input.value = '';
      }
    });

    // Zobrazit relevantní pole
    if (countTypes.indexOf(type) !== -1) {
      toggleDialogField('count', true);
      document.getElementById('wd-count').value = s.count || 5;
    }
    if (type === 'latest_articles' && multiBlog) {
      toggleDialogField('blog', true);
      document.getElementById('wd-blog').value = s.blog_id || 0;
    }
    if (type === 'featured_article') {
      toggleDialogField('source', true);
      document.getElementById('wd-source').value = s.source || 'blog';
    }
    if (type === 'newsletter' || type === 'search') {
      toggleDialogField('cta', true);
      document.getElementById('wd-cta').value = s.cta_text || '';
    }
    if (type === 'gallery_preview') {
      toggleDialogField('album', true);
      document.getElementById('wd-album').value = s.album_id || 0;
    }
    if (type === 'latest_podcast_episodes') {
      toggleDialogField('show', true);
      document.getElementById('wd-show').value = s.show_id || 0;
    }
    if (type === 'selected_form') {
      toggleDialogField('form', true);
      document.getElementById('wd-form').value = s.form_id || 0;
    }
    if (type === 'intro') {
      toggleDialogField('content', true);
      document.getElementById('wd-content').value = s.content || s.text || '';
    }
    if (type === 'custom_html') {
      toggleDialogField('content', true);
      document.getElementById('wd-content').value = s.content || '';
    }
    if (type === 'social_links') {
      toggleDialogField('social', true);
      socialFieldKeys.forEach(function(key){
        var input = document.querySelector('[name="widget_' + key + '"]');
        if (input) {
          input.value = s[key] || '';
        }
      });
    }
    setDynamicFieldsetVisibility(
      countTypes.indexOf(type) !== -1
      || (type === 'latest_articles' && multiBlog)
      || type === 'featured_article'
      || type === 'newsletter'
      || type === 'search'
      || type === 'gallery_preview'
      || type === 'latest_podcast_episodes'
      || type === 'selected_form'
      || type === 'intro'
      || type === 'custom_html'
      || type === 'social_links'
    );

    document.body.classList.add('admin-modal-open');
    overlay.hidden = false;
    dialog.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
    window.requestAnimationFrame(function () {
      document.getElementById('wd-title').focus();
    });
  }

  function closeDialog() {
    if (lastTrigger) {
      lastTrigger.setAttribute('aria-expanded', 'false');
    }
    document.body.classList.remove('admin-modal-open');
    overlay.hidden = true;
    dialog.hidden = true;
    if (lastTrigger) lastTrigger.focus();
  }

  document.querySelectorAll('.widget-edit-btn').forEach(function(btn){
    btn.addEventListener('click', function(){ openDialog(this); });
  });
  closeBtn.addEventListener('click', closeDialog);
  cancelBtn.addEventListener('click', closeDialog);
  overlay.addEventListener('click', closeDialog);
  dialog.addEventListener('keydown', function(e){
    if (e.key === 'Escape') { e.preventDefault(); closeDialog(); }
    if (e.key === 'Tab') {
      var focusable = getDialogFocusableElements();
      if (focusable.length === 0) return;
      if (e.shiftKey && document.activeElement === focusable[0]) { e.preventDefault(); focusable[focusable.length-1].focus(); }
      else if (!e.shiftKey && document.activeElement === focusable[focusable.length-1]) { e.preventDefault(); focusable[0].focus(); }
    }
  });

  setDynamicFieldsetVisibility(false);

  // Drag & drop
  var endpoint = <?= json_encode(BASE_URL . '/admin/reorder_ajax.php') ?>;
  var csrf = <?= json_encode(csrfToken()) ?>;

  document.querySelectorAll('[data-sortable="widgets"]').forEach(function(list){
    var zone = list.dataset.zone;
    var dragged = null;

    list.querySelectorAll('[data-sort-id]').forEach(function(el){ el.setAttribute('draggable','true'); });

    list.addEventListener('dragstart',function(e){
      var t=e.target.closest('[data-sort-id]');if(!t)return;
      dragged=t;t.classList.add('widget-sort-item--dragging');e.dataTransfer.effectAllowed='move';
    });
    list.addEventListener('dragover',function(e){
      e.preventDefault();e.dataTransfer.dropEffect='move';
      var t=e.target.closest('[data-sort-id]');
      if(t&&t!==dragged){var r=t.getBoundingClientRect();
      if(e.clientY<r.top+r.height/2)list.insertBefore(dragged,t);else list.insertBefore(dragged,t.nextSibling);}
    });
    list.addEventListener('dragend',function(){
      if(dragged)dragged.classList.remove('widget-sort-item--dragging');dragged=null;saveZone(list);
    });
    list.addEventListener('keydown',function(e){
      if(!e.ctrlKey||!['ArrowUp','ArrowDown'].includes(e.key))return;
      var t=e.target.closest('[data-sort-id]');if(!t)return;e.preventDefault();
      if(e.key==='ArrowUp'&&t.previousElementSibling)list.insertBefore(t,t.previousElementSibling);
      else if(e.key==='ArrowDown'&&t.nextElementSibling)list.insertBefore(t.nextElementSibling,t);
      saveZone(list);t.focus();
    });
  });

  function saveZone(list){
    var zone=list.dataset.zone;
    var order=Array.from(list.querySelectorAll('[data-sort-id]')).map(function(el){return el.dataset.sortId;});
    var fd=new FormData();fd.append('csrf_token',csrf);fd.append('module','widgets');fd.append('zone',zone);
    order.forEach(function(id){fd.append('order[]',id);});
    fetch(endpoint,{method:'POST',body:fd,credentials:'same-origin'});
  }
})();
</script>

<?php adminFooter(); ?>
