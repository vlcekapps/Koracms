<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$id  = inputInt('get', 'id');
$poll = null;
$options = [];

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_polls WHERE id = ?");
    $stmt->execute([$id]);
    $poll = $stmt->fetch();
    if (!$poll) { header('Location: polls.php'); exit; }

    $stmt = $pdo->prepare(
        "SELECT o.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE option_id = o.id) AS vote_count
         FROM cms_poll_options o WHERE o.poll_id = ? ORDER BY o.sort_order, o.id"
    );
    $stmt->execute([$id]);
    $options = $stmt->fetchAll();
}

$totalVotes = 0;
if ($id) {
    $stmtVc = $pdo->prepare("SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = ?");
    $stmtVc->execute([$id]);
    $totalVotes = (int)$stmtVc->fetchColumn();
}

adminHeader($id ? 'Upravit anketu' : 'Nová anketa');

$err = trim($_GET['err'] ?? '');
?>

<?php if ($err === 'required'): ?>
  <p role="alert" class="error" id="form-error">Vyplňte prosím otázku a alespoň 2 možnosti odpovědi.</p>
<?php elseif ($err === 'max_options'): ?>
  <p role="alert" class="error" id="form-error">Maximální počet možností je 10.</p>
<?php elseif ($err === 'has_votes'): ?>
  <p role="alert" class="error" id="form-error">Nelze odebrat možnosti, které již mají hlasy.</p>
<?php endif; ?>

<p style="margin-top:0;font-size:.9rem">
  Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<form method="post" action="polls_save.php" novalidate
      <?= $err ? 'aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <label for="question">Otázka <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
  <input type="text" id="question" name="question" required aria-required="true" maxlength="500"
         value="<?= h($poll['question'] ?? '') ?>">

  <label for="description">Popis <small>(nepovinný)</small></label>
  <textarea id="description" name="description" rows="3"><?= h($poll['description'] ?? '') ?></textarea>

  <label for="status">Stav</label>
  <select id="status" name="status">
    <option value="active" <?= ($poll['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktivní</option>
    <option value="closed" <?= ($poll['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Uzavřená</option>
  </select>

  <fieldset style="border:1px solid #ccc;padding:.5rem 1rem;margin-top:1rem">
    <legend>Časové omezení <small>(nepovinné)</small></legend>
    <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label for="start_date">Začátek – datum</label>
        <input type="date" id="start_date" name="start_date" style="width:auto;display:block;margin-top:.2rem"
               value="<?= $poll && $poll['start_date'] ? h(date('Y-m-d', strtotime($poll['start_date']))) : '' ?>">
      </div>
      <div>
        <label for="start_time">Začátek – čas</label>
        <input type="time" id="start_time" name="start_time" style="width:auto;display:block;margin-top:.2rem"
               value="<?= $poll && $poll['start_date'] ? h(date('H:i', strtotime($poll['start_date']))) : '' ?>">
      </div>
      <div>
        <label for="end_date">Konec – datum</label>
        <input type="date" id="end_date" name="end_date" style="width:auto;display:block;margin-top:.2rem"
               value="<?= $poll && $poll['end_date'] ? h(date('Y-m-d', strtotime($poll['end_date']))) : '' ?>">
      </div>
      <div>
        <label for="end_time">Konec – čas</label>
        <input type="time" id="end_time" name="end_time" style="width:auto;display:block;margin-top:.2rem"
               value="<?= $poll && $poll['end_date'] ? h(date('H:i', strtotime($poll['end_date']))) : '' ?>">
      </div>
    </div>
  </fieldset>

  <fieldset id="options-fieldset" style="border:1px solid #ccc;padding:.5rem 1rem;margin-top:1rem">
    <legend>Možnosti odpovědi <span aria-hidden="true">*</span><span class="sr-only">(povinné, min. 2, max. 10)</span></legend>
    <div id="options-list">
      <?php if (!empty($options)): ?>
        <?php foreach ($options as $i => $opt): ?>
          <div class="option-row" style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem">
            <input type="hidden" name="option_ids[]" value="<?= (int)$opt['id'] ?>">
            <label for="option_<?= $i ?>" class="sr-only">Možnost <?= $i + 1 ?></label>
            <input type="text" id="option_<?= $i ?>" name="options[]" required aria-required="true" maxlength="500"
                   value="<?= h($opt['option_text']) ?>" style="flex:1">
            <?php if ((int)$opt['vote_count'] > 0): ?>
              <span style="font-size:.85rem;color:#666">(<?= (int)$opt['vote_count'] ?> hlasů)</span>
            <?php else: ?>
              <button type="button" class="btn btn-danger btn-remove-option"
                      aria-label="Odebrat možnost <?= $i + 1 ?>"
                      onclick="removeOption(this)">Odebrat</button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <?php for ($i = 0; $i < 2; $i++): ?>
          <div class="option-row" style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem">
            <input type="hidden" name="option_ids[]" value="0">
            <label for="option_<?= $i ?>" class="sr-only">Možnost <?= $i + 1 ?></label>
            <input type="text" id="option_<?= $i ?>" name="options[]" required aria-required="true" maxlength="500"
                   value="" style="flex:1">
            <button type="button" class="btn btn-danger btn-remove-option"
                    aria-label="Odebrat možnost <?= $i + 1 ?>"
                    onclick="removeOption(this)">Odebrat</button>
          </div>
        <?php endfor; ?>
      <?php endif; ?>
    </div>
    <button type="button" id="add-option" class="btn" onclick="addOption()" style="margin-top:.5rem">+ Přidat možnost</button>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id ? 'Uložit' : 'Vytvořit anketu' ?></button>
    <a href="polls.php" style="margin-left:1rem">Zrušit</a>
  </div>
</form>

<?php if ($id && $totalVotes > 0): ?>
  <h2 style="margin-top:2rem">Výsledky <small>(celkem <?= $totalVotes ?> hlasů)</small></h2>
  <div role="list" aria-label="Výsledky ankety">
    <?php foreach ($options as $opt):
      $pct = $totalVotes > 0 ? round((int)$opt['vote_count'] / $totalVotes * 100, 1) : 0;
    ?>
      <div role="listitem" style="margin-bottom:.75rem">
        <div style="display:flex;justify-content:space-between;margin-bottom:.2rem">
          <span><?= h($opt['option_text']) ?></span>
          <span><?= $pct ?> % (<?= (int)$opt['vote_count'] ?> hlasů)</span>
        </div>
        <div style="background:#e8e8e8;border-radius:4px;overflow:hidden" aria-hidden="true">
          <div style="background:#005fcc;height:1.2rem;width:<?= $pct ?>%;min-width:<?= $pct > 0 ? '2px' : '0' ?>"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
(function () {
  var counter = document.querySelectorAll('.option-row').length;
  var list = document.getElementById('options-list');
  var addBtn = document.getElementById('add-option');
  var live = document.getElementById('a11y-live');

  function updateState() {
    var rows = list.querySelectorAll('.option-row');
    var removable = list.querySelectorAll('.btn-remove-option');
    for (var i = 0; i < removable.length; i++) {
      removable[i].disabled = rows.length <= 2;
    }
    addBtn.disabled = rows.length >= 10;
    // Re-number labels
    for (var j = 0; j < rows.length; j++) {
      var inp = rows[j].querySelector('input[type="text"]');
      var lbl = rows[j].querySelector('label');
      var btn = rows[j].querySelector('.btn-remove-option');
      inp.id = 'option_' + j;
      if (lbl) { lbl.setAttribute('for', 'option_' + j); lbl.textContent = 'Možnost ' + (j + 1); }
      if (btn) btn.setAttribute('aria-label', 'Odebrat možnost ' + (j + 1));
    }
  }

  window.addOption = function () {
    var rows = list.querySelectorAll('.option-row');
    if (rows.length >= 10) return;
    var div = document.createElement('div');
    div.className = 'option-row';
    div.style.cssText = 'display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem';
    var idx = counter++;
    div.innerHTML =
      '<input type="hidden" name="option_ids[]" value="0">' +
      '<label for="option_' + idx + '" class="sr-only">Možnost ' + (rows.length + 1) + '</label>' +
      '<input type="text" id="option_' + idx + '" name="options[]" required aria-required="true" maxlength="500" style="flex:1">' +
      '<button type="button" class="btn btn-danger btn-remove-option" aria-label="Odebrat možnost ' + (rows.length + 1) + '" onclick="removeOption(this)">Odebrat</button>';
    list.appendChild(div);
    div.querySelector('input[type="text"]').focus();
    updateState();
    if (live) live.textContent = 'Přidána možnost ' + (rows.length + 1);
  };

  window.removeOption = function (btn) {
    var rows = list.querySelectorAll('.option-row');
    if (rows.length <= 2) return;
    var row = btn.closest('.option-row');
    var prev = row.previousElementSibling;
    row.remove();
    updateState();
    if (prev) { var inp = prev.querySelector('input[type="text"]'); if (inp) inp.focus(); }
    else { var first = list.querySelector('input[type="text"]'); if (first) first.focus(); }
    if (live) live.textContent = 'Možnost odebrána';
  };

  updateState();
})();
</script>

<?php adminFooter(); ?>
