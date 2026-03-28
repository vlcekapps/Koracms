<?php
require_once __DIR__ . '/layout.php';
requireCapability('settings_manage', 'Přístup odepřen. Pro správu widgetů nemáte potřebné oprávnění.');

$pdo = db_connect();
$allWidgets = getAllWidgetsByZone();
$available = availableWidgetTypes();
$zones = widgetZoneDefinitions();
$types = widgetTypeDefinitions();
$editId = inputInt('get', 'edit');

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
          $isEditing = $editId === (int)$w['id'];
        ?>
          <li style="display:flex;align-items:flex-start;gap:.75rem;padding:.65rem .5rem;border-bottom:1px solid #eee;flex-wrap:wrap;cursor:grab<?= !(int)$w['is_active'] ? ';opacity:.5' : '' ?>"
              data-sort-id="<?= (int)$w['id'] ?>" tabindex="0"
              aria-label="<?= h($w['title'] ?: $wTypeName) ?> (<?= h($wTypeName) ?>)">

            <div style="min-width:14rem;flex:1 1 16rem">
              <strong><?= h($w['title'] ?: $wTypeName) ?></strong>
              <br><small style="color:#555"><?= h($wTypeName) ?><?= !(int)$w['is_active'] ? ' · <em>neaktivní</em>' : '' ?></small>
            </div>

            <div style="display:flex;gap:.4rem;flex-wrap:wrap">
              <a href="widgets.php?edit=<?= (int)$w['id'] ?>" class="btn" style="font-size:.85rem" aria-label="Nastavení widgetu <?= h($w['title'] ?: $wTypeName) ?>">Nastavení</a>
              <form method="post" action="widget_delete.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="widget_id" value="<?= (int)$w['id'] ?>">
                <button type="submit" class="btn btn-danger" style="font-size:.85rem"
                        data-confirm="Odebrat widget „<?= h($w['title'] ?: $wTypeName) ?>"?"
                        aria-label="Odebrat widget <?= h($w['title'] ?: $wTypeName) ?>">✕</button>
              </form>
            </div>
          </li>

          <?php if ($isEditing): ?>
            <li style="padding:.75rem;background:#f8f9fb;border:1px solid #d6d6d6;border-radius:8px;margin:.3rem 0">
              <form method="post" action="widget_save.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="widget_id" value="<?= (int)$w['id'] ?>">

                <label for="w-title">Název widgetu</label>
                <input type="text" id="w-title" name="title" value="<?= h($w['title']) ?>" maxlength="255">

                <label for="w-zone">Zóna</label>
                <select id="w-zone" name="zone">
                  <?php foreach ($zones as $zk => $zl): ?>
                    <option value="<?= h($zk) ?>"<?= $zk === $w['zone'] ? ' selected' : '' ?>><?= h($zl) ?></option>
                  <?php endforeach; ?>
                </select>

                <label style="font-weight:normal;margin-top:.5rem">
                  <input type="checkbox" name="is_active" value="1"<?= (int)$w['is_active'] ? ' checked' : '' ?>>
                  Aktivní
                </label>

                <?php // Specifická nastavení dle typu widgetu ?>
                <?php if (in_array($w['widget_type'], ['latest_articles', 'latest_news', 'board', 'upcoming_events'], true)): ?>
                  <label for="w-count" style="margin-top:.75rem">Počet položek</label>
                  <input type="number" id="w-count" name="widget_count" min="1" max="50" value="<?= (int)($wSettings['count'] ?? 5) ?>" style="width:6rem">
                <?php endif; ?>

                <?php if ($w['widget_type'] === 'latest_articles' && count($allBlogs) > 1): ?>
                  <label for="w-blog" style="margin-top:.5rem">Blog</label>
                  <select id="w-blog" name="widget_blog_id">
                    <option value="0">Všechny blogy</option>
                    <?php foreach ($allBlogs as $b): ?>
                      <option value="<?= (int)$b['id'] ?>"<?= (int)($wSettings['blog_id'] ?? 0) === (int)$b['id'] ? ' selected' : '' ?>><?= h($b['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php endif; ?>

                <?php if ($w['widget_type'] === 'featured_article'): ?>
                  <label for="w-source" style="margin-top:.75rem">Zdroj</label>
                  <select id="w-source" name="widget_source">
                    <option value="blog"<?= ($wSettings['source'] ?? 'blog') === 'blog' ? ' selected' : '' ?>>Blog (nejčtenější)</option>
                    <option value="board"<?= ($wSettings['source'] ?? '') === 'board' ? ' selected' : '' ?>>Vývěska (nejnovější)</option>
                    <option value="poll"<?= ($wSettings['source'] ?? '') === 'poll' ? ' selected' : '' ?>>Anketa</option>
                    <option value="newsletter"<?= ($wSettings['source'] ?? '') === 'newsletter' ? ' selected' : '' ?>>Newsletter</option>
                  </select>
                <?php endif; ?>

                <?php if ($w['widget_type'] === 'newsletter'): ?>
                  <label for="w-cta" style="margin-top:.75rem">CTA text</label>
                  <input type="text" id="w-cta" name="widget_cta_text" value="<?= h($wSettings['cta_text'] ?? '') ?>" maxlength="500">
                <?php endif; ?>

                <?php if ($w['widget_type'] === 'gallery_preview'): ?>
                  <label for="w-album" style="margin-top:.75rem">Album</label>
                  <select id="w-album" name="widget_album_id">
                    <option value="0">Všechny fotky</option>
                    <?php foreach ($allAlbums as $alb): ?>
                      <option value="<?= (int)$alb['id'] ?>"<?= (int)($wSettings['album_id'] ?? 0) === (int)$alb['id'] ? ' selected' : '' ?>><?= h($alb['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php endif; ?>

                <?php if ($w['widget_type'] === 'intro'): ?>
                  <label for="w-text" style="margin-top:.75rem">Text</label>
                  <textarea id="w-text" name="widget_text" rows="4"><?= h($wSettings['text'] ?? '') ?></textarea>
                <?php endif; ?>

                <?php if ($w['widget_type'] === 'custom_html'): ?>
                  <label for="w-content" style="margin-top:.75rem">HTML obsah</label>
                  <textarea id="w-content" name="widget_content" rows="6"><?= h($wSettings['content'] ?? '') ?></textarea>
                <?php endif; ?>

                <div class="button-row" style="margin-top:.75rem">
                  <button type="submit" class="btn">Uložit</button>
                  <a href="widgets.php">Zrušit</a>
                </div>
              </form>
            </li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </fieldset>
<?php endforeach; ?>

<script nonce="<?= cspNonce() ?>">
(function(){
  // Drag & drop mezi zónami
  var endpoint = <?= json_encode(BASE_URL . '/admin/reorder_ajax.php') ?>;
  var csrf = <?= json_encode(csrfToken()) ?>;

  document.querySelectorAll('[data-sortable="widgets"]').forEach(function(list){
    var zone = list.dataset.zone;
    var items = function(){return Array.from(list.querySelectorAll('[data-sort-id]'));};
    var dragged = null;

    items().forEach(function(el){el.setAttribute('draggable','true');});

    list.addEventListener('dragstart',function(e){
      var t=e.target.closest('[data-sort-id]');if(!t)return;
      dragged=t;t.style.opacity='0.4';
      e.dataTransfer.effectAllowed='move';
    });

    list.addEventListener('dragover',function(e){
      e.preventDefault();e.dataTransfer.dropEffect='move';
      var t=e.target.closest('[data-sort-id]');
      if(t&&t!==dragged){
        var r=t.getBoundingClientRect();
        if(e.clientY<r.top+r.height/2)list.insertBefore(dragged,t);
        else list.insertBefore(dragged,t.nextSibling);
      }
    });

    list.addEventListener('dragend',function(){
      if(dragged)dragged.style.opacity='';dragged=null;saveZone(list);
    });

    list.addEventListener('keydown',function(e){
      if(!e.ctrlKey||!['ArrowUp','ArrowDown'].includes(e.key))return;
      var t=e.target.closest('[data-sort-id]');if(!t)return;
      e.preventDefault();
      if(e.key==='ArrowUp'&&t.previousElementSibling)list.insertBefore(t,t.previousElementSibling);
      else if(e.key==='ArrowDown'&&t.nextElementSibling)list.insertBefore(t.nextElementSibling,t);
      saveZone(list);t.focus();
    });
  });

  // Cross-zone drag: přijímat widgety z jiných zón
  document.querySelectorAll('[data-sortable="widgets"]').forEach(function(list){
    list.addEventListener('dragover',function(e){e.preventDefault();});
    list.addEventListener('drop',function(e){
      e.preventDefault();
      var dragged = document.querySelector('[data-sort-id][style*="opacity"]');
      if(dragged && dragged.parentNode !== list){
        list.appendChild(dragged);
        dragged.style.opacity='';
        saveZone(list);
        saveZone(dragged._prevList || list);
      }
    });
  });

  function saveZone(list){
    var zone=list.dataset.zone;
    var order=Array.from(list.querySelectorAll('[data-sort-id]')).map(function(el){return el.dataset.sortId;});
    var fd=new FormData();
    fd.append('csrf_token',csrf);fd.append('module','widgets');fd.append('zone',zone);
    order.forEach(function(id){fd.append('order[]',id);});
    fetch(endpoint,{method:'POST',body:fd,credentials:'same-origin'});
  }
})();
</script>

<?php adminFooter(); ?>
