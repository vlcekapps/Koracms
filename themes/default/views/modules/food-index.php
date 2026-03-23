<div class="listing-shell">
  <section class="surface" aria-labelledby="food-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Nabídka</p>
        <h1 id="food-title" class="section-title section-title--hero">Jídelní a nápojový lístek</h1>
      </div>
    </div>

    <div class="tabs-nav" role="tablist" aria-label="Typ lístku">
      <button type="button" class="tabs-nav__tab" role="tab" aria-selected="true" aria-controls="food-panel-food"
              id="food-tab-food" data-tab="food" tabindex="0">Jídelní lístek</button>
      <button type="button" class="tabs-nav__tab" role="tab" aria-selected="false" aria-controls="food-panel-beverage"
              id="food-tab-beverage" data-tab="beverage" tabindex="-1">Nápojový lístek</button>
    </div>

    <section id="food-panel-food" class="tab-panel" role="tabpanel" aria-labelledby="food-tab-food" data-panel="food">
      <?php if ($foodCard): ?>
        <h2 class="section-title section-title--compact"><?= h($foodCard['title']) ?></h2>
        <?php if ($foodMeta !== ''): ?>
          <p class="meta-row meta-row--tight"><?= h($foodMeta) ?></p>
        <?php endif; ?>
        <div class="prose menu-content"><?= renderContent($foodCard['content']) ?></div>
      <?php else: ?>
        <p class="empty-state">Aktuální jídelní lístek zatím není k dispozici.</p>
      <?php endif; ?>
    </section>

    <section id="food-panel-beverage" class="tab-panel" role="tabpanel" aria-labelledby="food-tab-beverage" data-panel="beverage" hidden>
      <?php if ($beverageCard): ?>
        <h2 class="section-title section-title--compact"><?= h($beverageCard['title']) ?></h2>
        <?php if ($beverageMeta !== ''): ?>
          <p class="meta-row meta-row--tight"><?= h($beverageMeta) ?></p>
        <?php endif; ?>
        <div class="prose menu-content"><?= renderContent($beverageCard['content']) ?></div>
      <?php else: ?>
        <p class="empty-state">Aktuální nápojový lístek zatím není k dispozici.</p>
      <?php endif; ?>
    </section>

    <div class="article-actions">
      <a class="button-secondary" href="<?= BASE_URL ?>/food/archive.php">Archiv lístků</a>
    </div>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var tabs = Array.prototype.slice.call(document.querySelectorAll('.tabs-nav__tab'));
  var panels = Array.prototype.slice.call(document.querySelectorAll('.tab-panel'));
  if (tabs.length === 0 || panels.length === 0) return;

  function activateTab(type, moveFocus, updateHash) {
    tabs.forEach(function (tab) {
      var active = tab.getAttribute('data-tab') === type;
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.setAttribute('tabindex', active ? '0' : '-1');
      if (active && moveFocus) {
        tab.focus();
      }
    });

    panels.forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-panel') !== type;
    });

    if (updateHash) {
      window.location.hash = type === 'beverage' ? 'beverage' : 'food';
    }
  }

  tabs.forEach(function (tab, index) {
    tab.addEventListener('click', function () {
      activateTab(tab.getAttribute('data-tab'), false, true);
    });

    tab.addEventListener('keydown', function (event) {
      var nextIndex = index;
      if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        nextIndex = (index + 1) % tabs.length;
      } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        nextIndex = (index - 1 + tabs.length) % tabs.length;
      } else if (event.key === 'Home') {
        nextIndex = 0;
      } else if (event.key === 'End') {
        nextIndex = tabs.length - 1;
      } else {
        return;
      }

      event.preventDefault();
      activateTab(tabs[nextIndex].getAttribute('data-tab'), true, true);
    });
  });

  var hash = window.location.hash.replace('#', '');
  activateTab(hash === 'beverage' || hash === 'napojovy' ? 'beverage' : 'food', false, false);
});
</script>
