<?php
require_once __DIR__ . '/layout.php';
requireCapability('settings_manage', 'Přístup odepřen. Pro správu widgetů nemáte potřebné oprávnění.');

$pdo = db_connect();
$allWidgets = getAllWidgetsByZone();
$available = availableWidgetTypes();
$zones = widgetZoneDefinitions();
$types = widgetTypeDefinitions();

$allBlogs = getAllBlogs();
$allAlbums = [];
try {
    $allAlbums = $pdo->query("SELECT id, name FROM cms_gallery_albums ORDER BY name")->fetchAll();
} catch (\PDOException $e) {}

adminHeader('Widgety');
?>

<p style="font-size:.9rem">Přidávejte, přesouvejte a nastavujte widgety v jednotlivých zónách webu. Přetažením myší nebo klávesou Ctrl+šipka změníte pořadí.</p>

<fieldset style="margin-bottom:1.5rem;border:1px solid #d6d6d6;border-radius:10px;padding:.85rem 1rem">
  <legend>Přidat widget</legend>
  <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
    <?php foreach ($available as $wType => $wDef): ?>
      <form method="post" action="widget_add.php" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="widget_type" value="<?= h($wType) ?>">
        <input type="hidden" name="zone" value="homepage">
        <button type="submit" class="btn" style="font-size:.85rem">+ <?= h($wDef['name']) ?></button>
      </form>
    <?php endforeach; ?>
    <?php if (empty($available)): ?>
      <p class="field-help" style="margin:0">Žádné widgety nejsou dostupné. Zapněte moduly v <a href="settings_modules.php">Správě modulů</a>.</p>
    <?php endif; ?>
  </div>
</fieldset>

<?php foreach ($zones as $zoneKey => $zoneLabel): ?>
  <fieldset style="margin-bottom:1.5rem;border:1px solid #d6d6d6;border-radius:10px;padding:.85rem 1rem">
    <legend><?= h($zoneLabel) ?></legend>

    <?php if (empty($allWidgets[$zoneKey])): ?>
      <p class="field-help" style="margin:0">Žádné widgety v této zóně. Použijte tlačítka nahoře pro přidání.</p>
    <?php else: ?>
      <ol style="list-style:none;padding:0;margin:0" data-sortable="widgets" data-zone="<?= h($zoneKey) ?>">
        <?php foreach ($allWidgets[$zoneKey] as $w):
          $wSettings = widgetSettings($w);
          $wTypeDef = $types[$w['widget_type']] ?? null;
          $wTypeName = $wTypeDef ? $wTypeDef['name'] : $w['widget_type'];
        ?>
          <li style="display:flex;align-items:flex-start;gap:.75rem;padding:.65rem .5rem;border-bottom:1px solid #eee;flex-wrap:wrap;cursor:grab<?= !(int)$w['is_active'] ? ';opacity:.5' : '' ?>"
              data-sort-id="<?= (int)$w['id'] ?>" tabindex="0"
              aria-label="<?= h($w['title'] ?: $wTypeName) ?> (<?= h($wTypeName) ?>)">

            <div style="min-width:14rem;flex:1 1 16rem">
              <strong><?= h($w['title'] ?: $wTypeName) ?></strong>
              <br><small style="color:#555"><?= h($wTypeName) ?><?= !(int)$w['is_active'] ? ' · <em>neaktivní</em>' : '' ?></small>
            </div>

            <div style="display:flex;gap:.4rem;flex-wrap:wrap">
              <button type="button" class="btn widget-edit-btn" style="font-size:.85rem"
                      aria-label="Nastavení widgetu <?= h($w['title'] ?: $wTypeName) ?>"
                      aria-haspopup="dialog"
                      aria-controls="widget-dialog"
                      aria-expanded="false"
                      data-widget-id="<?= (int)$w['id'] ?>"
                      data-widget-title="<?= h($w['title']) ?>"
                      data-widget-type="<?= h($w['widget_type']) ?>"
                      data-widget-zone="<?= h($w['zone']) ?>"
                      data-widget-active="<?= (int)$w['is_active'] ?>"
                      data-widget-settings="<?= h(json_encode($wSettings, JSON_UNESCAPED_UNICODE)) ?>">Nastavení</button>
              <form method="post" action="widget_delete.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="widget_id" value="<?= (int)$w['id'] ?>">
                <button type="submit" class="btn btn-danger" style="font-size:.85rem"
                        data-confirm="Odebrat widget „<?= h($w['title'] ?: $wTypeName) ?>"?"
                        aria-label="Odebrat widget <?= h($w['title'] ?: $wTypeName) ?>">✕</button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </fieldset>
<?php endforeach; ?>

<!-- Modal dialog pro nastavení widgetu -->
<div id="widget-overlay" hidden style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.54);z-index:1000"></div>
<section id="widget-dialog" role="dialog" aria-modal="true" aria-labelledby="widget-dialog-title" aria-describedby="widget-dialog-description" hidden
         style="display:none;position:fixed;inset:50% auto auto 50%;transform:translate(-50%,-50%);
                width:min(32rem,calc(100vw - 2rem));max-height:calc(100vh - 2rem);overflow:auto;
                padding:1.2rem;border:1px solid #cbd5e1;border-radius:.9rem;background:#fff;
                box-shadow:0 28px 60px rgba(15,23,42,.28);z-index:1001">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
    <h2 id="widget-dialog-title" style="margin:0;font-size:1.15rem">Nastavení widgetu</h2>
    <button type="button" id="widget-dialog-close" class="btn" aria-label="Zavřít dialog">✕</button>
  </div>
  <p id="widget-dialog-description" class="field-help" style="margin-top:0">Upravte název, zónu a další nastavení vybraného widgetu.</p>
  <form method="post" action="widget_save.php" novalidate id="widget-dialog-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="widget_id" id="wd-id">

    <label for="wd-title">Název widgetu</label>
    <input type="text" id="wd-title" name="title" maxlength="255">

    <label for="wd-zone">Zóna</label>
    <select id="wd-zone" name="zone">
      <?php foreach ($zones as $zk => $zl): ?>
        <option value="<?= h($zk) ?>"><?= h($zl) ?></option>
      <?php endforeach; ?>
    </select>

    <label style="font-weight:normal;margin-top:.5rem">
      <input type="checkbox" name="is_active" value="1" id="wd-active"> Aktivní
    </label>

    <!-- Dynamická pole dle typu -->
    <div id="wd-field-count" style="display:none;margin-top:.75rem">
      <label for="wd-count">Počet položek</label>
      <input type="number" id="wd-count" name="widget_count" min="1" max="50" value="5" style="width:6rem">
    </div>

    <div id="wd-field-blog" style="display:none;margin-top:.5rem">
      <label for="wd-blog">Blog</label>
      <select id="wd-blog" name="widget_blog_id">
        <option value="0">Všechny blogy</option>
        <?php foreach ($allBlogs as $b): ?>
          <option value="<?= (int)$b['id'] ?>"><?= h($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div id="wd-field-source" style="display:none;margin-top:.75rem">
      <label for="wd-source">Zdroj</label>
      <select id="wd-source" name="widget_source">
        <option value="blog">Blog (nejčtenější)</option>
        <option value="board">Vývěska (nejnovější)</option>
        <option value="poll">Anketa</option>
        <option value="newsletter">Newsletter</option>
      </select>
    </div>

    <div id="wd-field-cta" style="display:none;margin-top:.75rem">
      <label for="wd-cta">CTA text</label>
      <input type="text" id="wd-cta" name="widget_cta_text" maxlength="500">
    </div>

    <div id="wd-field-album" style="display:none;margin-top:.75rem">
      <label for="wd-album">Album</label>
      <select id="wd-album" name="widget_album_id">
        <option value="0">Všechny fotky</option>
        <?php foreach ($allAlbums as $alb): ?>
          <option value="<?= (int)$alb['id'] ?>"><?= h($alb['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div id="wd-field-text" style="display:none;margin-top:.75rem">
      <label for="wd-text">Text</label>
      <textarea id="wd-text" name="widget_text" rows="4"></textarea>
    </div>

    <div id="wd-field-content" style="display:none;margin-top:.75rem">
      <label for="wd-content">HTML obsah</label>
      <textarea id="wd-content" name="widget_content" rows="6"></textarea>
    </div>

    <div class="button-row" style="margin-top:1rem">
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
  var lastTrigger = null;
  var previousBodyOverflow = '';
  var countTypes = ['latest_articles','latest_news','board','upcoming_events'];
  var multiBlog = <?= count($allBlogs) > 1 ? 'true' : 'false' ?>;

  function openDialog(btn) {
    lastTrigger = btn;
    var s = JSON.parse(btn.dataset.widgetSettings || '{}');
    var type = btn.dataset.widgetType;

    document.getElementById('wd-id').value = btn.dataset.widgetId;
    document.getElementById('wd-title').value = btn.dataset.widgetTitle;
    document.getElementById('wd-zone').value = btn.dataset.widgetZone;
    document.getElementById('wd-active').checked = btn.dataset.widgetActive === '1';

    // Skrýt všechna dynamická pole
    ['count','blog','source','cta','album','text','content'].forEach(function(f){
      document.getElementById('wd-field-'+f).style.display = 'none';
    });

    // Zobrazit relevantní pole
    if (countTypes.indexOf(type) !== -1) {
      document.getElementById('wd-field-count').style.display = '';
      document.getElementById('wd-count').value = s.count || 5;
    }
    if (type === 'latest_articles' && multiBlog) {
      document.getElementById('wd-field-blog').style.display = '';
      document.getElementById('wd-blog').value = s.blog_id || 0;
    }
    if (type === 'featured_article') {
      document.getElementById('wd-field-source').style.display = '';
      document.getElementById('wd-source').value = s.source || 'blog';
    }
    if (type === 'newsletter') {
      document.getElementById('wd-field-cta').style.display = '';
      document.getElementById('wd-cta').value = s.cta_text || '';
    }
    if (type === 'gallery_preview') {
      document.getElementById('wd-field-album').style.display = '';
      document.getElementById('wd-album').value = s.album_id || 0;
    }
    if (type === 'intro') {
      document.getElementById('wd-field-text').style.display = '';
      document.getElementById('wd-text').value = s.text || '';
    }
    if (type === 'custom_html') {
      document.getElementById('wd-field-content').style.display = '';
      document.getElementById('wd-content').value = s.content || '';
    }

    previousBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    overlay.hidden = false;
    dialog.hidden = false;
    overlay.style.display = '';
    dialog.style.display = '';
    btn.setAttribute('aria-expanded', 'true');
    window.requestAnimationFrame(function () {
      document.getElementById('wd-title').focus();
    });
  }

  function closeDialog() {
    if (lastTrigger) {
      lastTrigger.setAttribute('aria-expanded', 'false');
    }
    document.body.style.overflow = previousBodyOverflow;
    overlay.style.display = 'none';
    dialog.style.display = 'none';
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
      var focusable = Array.from(dialog.querySelectorAll('input:not([type=hidden]),select,textarea,button'));
      if (focusable.length === 0) return;
      if (e.shiftKey && document.activeElement === focusable[0]) { e.preventDefault(); focusable[focusable.length-1].focus(); }
      else if (!e.shiftKey && document.activeElement === focusable[focusable.length-1]) { e.preventDefault(); focusable[0].focus(); }
    }
  });

  // Drag & drop
  var endpoint = <?= json_encode(BASE_URL . '/admin/reorder_ajax.php') ?>;
  var csrf = <?= json_encode(csrfToken()) ?>;

  document.querySelectorAll('[data-sortable="widgets"]').forEach(function(list){
    var zone = list.dataset.zone;
    var dragged = null;

    list.querySelectorAll('[data-sort-id]').forEach(function(el){ el.setAttribute('draggable','true'); });

    list.addEventListener('dragstart',function(e){
      var t=e.target.closest('[data-sort-id]');if(!t)return;
      dragged=t;t.style.opacity='0.4';e.dataTransfer.effectAllowed='move';
    });
    list.addEventListener('dragover',function(e){
      e.preventDefault();e.dataTransfer.dropEffect='move';
      var t=e.target.closest('[data-sort-id]');
      if(t&&t!==dragged){var r=t.getBoundingClientRect();
      if(e.clientY<r.top+r.height/2)list.insertBefore(dragged,t);else list.insertBefore(dragged,t.nextSibling);}
    });
    list.addEventListener('dragend',function(){
      if(dragged)dragged.style.opacity='';dragged=null;saveZone(list);
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
