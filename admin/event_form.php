<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$id  = inputInt('get', 'id');
$ev  = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_events WHERE id = ?");
    $stmt->execute([$id]);
    $ev = $stmt->fetch();
    if (!$ev) { header('Location: events.php'); exit; }
}

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';

adminHeader($id ? 'Upravit událost' : 'Nová událost');

$err = trim($_GET['err'] ?? '');
?>

<?php if ($err === 'required'): ?>
  <p role="alert" class="error" id="form-error">
    Vyplňte prosím všechna povinná pole (název a datum konání).
  </p>
<?php endif; ?>

<p style="margin-top:0;font-size:.9rem">
  Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<form method="post" action="event_save.php" novalidate
      <?= $err ? 'aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Událost</legend>

    <label for="title">Název <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           value="<?= h($ev['title'] ?? '') ?>">
  </fieldset>

  <fieldset style="border:1px solid #ccc;padding:.5rem 1rem;margin-top:1rem">
    <legend>Datum konání <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></legend>
    <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label for="event_date">Datum <span aria-hidden="true">*</span></label>
        <input type="date" id="event_date" name="event_date" required aria-required="true" style="width:auto;display:block;margin-top:.2rem"
               value="<?= $ev ? h(date('Y-m-d', strtotime($ev['event_date']))) : '' ?>">
      </div>
      <div>
        <label for="event_time">Čas <small>(nepovinný)</small></label>
        <input type="time" id="event_time" name="event_time" style="width:auto;display:block;margin-top:.2rem"
               value="<?= $ev ? h(date('H:i', strtotime($ev['event_date']))) : '' ?>">
      </div>
    </div>
  </fieldset>

  <fieldset style="border:1px solid #ccc;padding:.5rem 1rem;margin-top:1rem">
    <legend>Konec akce <small>(nepovinné)</small></legend>
    <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label for="event_end_date">Datum</label>
        <input type="date" id="event_end_date" name="event_end_date" style="width:auto;display:block;margin-top:.2rem"
               value="<?= (!empty($ev['event_end'])) ? h(date('Y-m-d', strtotime($ev['event_end']))) : '' ?>">
      </div>
      <div>
        <label for="event_end_time">Čas</label>
        <input type="time" id="event_end_time" name="event_end_time" style="width:auto;display:block;margin-top:.2rem"
               value="<?= (!empty($ev['event_end'])) ? h(date('H:i', strtotime($ev['event_end']))) : '' ?>">
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Podrobnosti</legend>

    <label for="location">Místo konání</label>
    <input type="text" id="location" name="location" maxlength="255"
           value="<?= h($ev['location'] ?? '') ?>">

    <label for="description">Popis</label>
    <textarea id="description" name="description" rows="10"><?= h($ev['description'] ?? '') ?></textarea>
    <?php if (!$useWysiwyg): ?><small style="color:#666">Podporuje HTML i Markdown syntaxi.</small><?php endif; ?>

    <label style="font-weight:normal;margin-top:1rem">
      <input type="checkbox" name="is_published" value="1"
             <?= ($ev['is_published'] ?? 1) ? 'checked' : '' ?>>
      Publikováno
    </label>

    <div style="margin-top:1.5rem">
      <button type="submit" class="btn"><?= $id ? 'Uložit' : 'Přidat událost' ?></button>
      <a href="events.php" style="margin-left:1rem">Zrušit</a>
    </div>
  </fieldset>
</form>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
(function () {
    const ta = document.getElementById('description');
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'background:#fff;border:1px solid #ccc;margin-top:.2rem;min-height:200px';
    ta.parentNode.insertBefore(wrapper, ta);
    ta.style.display = 'none';
    const quill = new Quill(wrapper, { theme: 'snow' });
    quill.root.innerHTML = ta.value;
    ta.closest('form').addEventListener('submit', () => { ta.value = quill.root.innerHTML; });
})();
</script>
<?php endif; ?>

<?php adminFooter(); ?>
