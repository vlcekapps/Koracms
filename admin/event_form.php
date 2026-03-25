<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu událostí nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$event = null;

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_events WHERE id = ?");
    $stmt->execute([$id]);
    $event = $stmt->fetch();
    if (!$event) {
        header('Location: events.php');
        exit;
    }
}

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$err = trim($_GET['err'] ?? '');
$formError = match ($err) {
    'required' => 'Vyplňte prosím všechna povinná pole (název a datum konání).',
    'slug' => 'Slug události je povinný a musí být unikátní.',
    default => '',
};

adminHeader($id ? 'Upravit událost' : 'Nová událost');
?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error"><?= h($formError) ?></p>
<?php endif; ?>

<p style="margin-top:0;font-size:.9rem">
  Vyplňte potřebné údaje k této události. Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<form method="post" action="event_save.php" novalidate<?= $formError !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Základní údaje události</legend>

    <label for="title">Název <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           value="<?= h((string)($event['title'] ?? '')) ?>">

    <label for="slug">Slug (URL události) <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255" pattern="[a-z0-9\-]+"
           aria-describedby="event-slug-help"
           value="<?= h((string)($event['slug'] ?? '')) ?>">
    <small id="event-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Použijte malá písmena, číslice a pomlčky.</small>
  </fieldset>

  <fieldset style="border:1px solid #ccc;padding:.5rem 1rem;margin-top:1rem">
    <legend>Datum konání <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></legend>
    <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label for="event_date">Datum <span aria-hidden="true">*</span></label>
        <input type="date" id="event_date" name="event_date" required aria-required="true" style="width:auto;display:block;margin-top:.2rem"
               value="<?= $event ? h(date('Y-m-d', strtotime((string)$event['event_date']))) : '' ?>">
      </div>
      <div>
        <label for="event_time">Čas</label>
        <input type="time" id="event_time" name="event_time" style="width:auto;display:block;margin-top:.2rem"
               aria-describedby="event-time-help"
               value="<?= $event ? h(date('H:i', strtotime((string)$event['event_date']))) : '' ?>">
        <small id="event-time-help" class="field-help">Vyplňte jen pokud chcete návštěvníkům ukázat přesný čas začátku.</small>
      </div>
    </div>
  </fieldset>

  <fieldset style="border:1px solid #ccc;padding:.5rem 1rem;margin-top:1rem">
    <legend>Konec akce</legend>
    <small id="event-end-help" class="field-help" style="margin-top:0">Vyplňte jen pokud má událost jasný konec.</small>
    <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label for="event_end_date">Datum</label>
        <input type="date" id="event_end_date" name="event_end_date" style="width:auto;display:block;margin-top:.2rem" aria-describedby="event-end-help"
               value="<?= !empty($event['event_end']) ? h(date('Y-m-d', strtotime((string)$event['event_end']))) : '' ?>">
      </div>
      <div>
        <label for="event_end_time">Čas</label>
        <input type="time" id="event_end_time" name="event_end_time" style="width:auto;display:block;margin-top:.2rem" aria-describedby="event-end-help"
               value="<?= !empty($event['event_end']) ? h(date('H:i', strtotime((string)$event['event_end']))) : '' ?>">
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Popis události</legend>

    <label for="location">Místo konání</label>
    <input type="text" id="location" name="location" maxlength="255"
           value="<?= h((string)($event['location'] ?? '')) ?>">

    <label for="description">Popis</label>
    <textarea id="description" name="description" rows="10"<?= !$useWysiwyg ? ' aria-describedby="event-description-help"' : '' ?>><?= h((string)($event['description'] ?? '')) ?></textarea>
    <?php if (!$useWysiwyg): ?><small id="event-description-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small><?php endif; ?>
    <?php if (!$useWysiwyg): ?>
      <?php renderAdminContentReferencePicker('description'); ?>
    <?php endif; ?>

    <label style="font-weight:normal;margin-top:1rem">
      <input type="checkbox" name="is_published" value="1" aria-describedby="event-published-help"
             <?= (int)($event['is_published'] ?? 1) === 1 ? 'checked' : '' ?>>
      Zveřejnit na webu
    </label>
    <small id="event-published-help" class="field-help" style="margin-top:.2rem">Když volbu vypnete, událost zůstane uložená jen v administraci.</small>

    <div style="margin-top:1.5rem">
      <button type="submit" class="btn"><?= $id ? 'Uložit změny' : 'Přidat událost' ?></button>
      <a href="events.php" style="margin-left:1rem">Zrušit</a>
      <?php if ($event && ($event['status'] ?? 'published') === 'published' && (int)($event['is_published'] ?? 1) === 1): ?>
        <a href="<?= h(eventPublicPath($event)) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
      <?php endif; ?>
    </div>
  </fieldset>
</form>

<script>
(function () {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    let slugManual = <?= $event && !empty($event['slug']) ? 'true' : 'false' ?>;

    const slugify = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    slugInput?.addEventListener('input', function () {
        slugManual = this.value.trim() !== '';
    });

    titleInput?.addEventListener('input', function () {
        if (slugManual || !slugInput) {
            return;
        }
        slugInput.value = slugify(this.value);
    });
})();
</script>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
(function () {
    const textarea = document.getElementById('description');
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'background:#fff;border:1px solid #ccc;margin-top:.2rem;min-height:200px';
    textarea.parentNode.insertBefore(wrapper, textarea);
    textarea.style.display = 'none';
    const quill = new Quill(wrapper, { theme: 'snow' });
    quill.root.innerHTML = textarea.value;
    textarea.closest('form')?.addEventListener('submit', function () {
        textarea.value = quill.root.innerHTML;
    });
})();
</script>
<?php endif; ?>

<?php adminFooter(); ?>
