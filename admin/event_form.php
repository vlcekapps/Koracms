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
    $event = $stmt->fetch() ?: null;
    if (!$event) {
        header('Location: events.php');
        exit;
    }
}

$event = array_merge([
    'title' => '',
    'slug' => '',
    'event_kind' => 'general',
    'excerpt' => '',
    'description' => '',
    'program_note' => '',
    'location' => '',
    'organizer_name' => '',
    'organizer_email' => '',
    'registration_url' => '',
    'price_note' => '',
    'accessibility_note' => '',
    'image_file' => '',
    'event_date' => '',
    'event_end' => '',
    'is_published' => 1,
    'unpublish_at' => '',
    'admin_note' => '',
    'status' => 'published',
], $event ?? []);
$event = hydrateEventPresentation($event);

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$currentEventKind = normalizeEventKind((string)($event['event_kind'] ?? 'general'));
$eventKindHelpMap = [];
foreach (eventKindDefinitions() as $typeKey => $typeMeta) {
    $eventKindHelpMap[$typeKey] = (string)($typeMeta['help'] ?? '');
}

$err = trim((string)($_GET['err'] ?? ''));
$formError = match ($err) {
    'required' => 'Vyplňte prosím všechna povinná pole. Událost musí mít název a datum začátku.',
    'slug' => 'Slug události je povinný a musí být unikátní.',
    'dates' => 'Konec akce nesmí být dříve než její začátek.',
    'registration_url' => 'Registrační odkaz musí být platná adresa začínající na http:// nebo https://.',
    'organizer_email' => 'E-mail pořadatele musí být platná e-mailová adresa.',
    'image' => 'Obrázek události se nepodařilo uložit.',
    'unpublish_at' => 'Plánované zrušení publikace nemá platný formát data a času.',
    default => '',
};
$fieldErrorMap = [
    'required' => ['title', 'event_date'],
    'slug' => ['slug'],
    'dates' => ['event_date', 'event_time', 'event_end_date', 'event_end_time'],
    'registration_url' => ['registration_url'],
    'organizer_email' => ['organizer_email'],
    'image' => ['event_image'],
    'unpublish_at' => ['unpublish_at'],
];
$fieldErrorMessages = [
    'title' => 'Událost musí mít název.',
    'event_date' => 'Událost musí mít datum začátku.',
    'slug' => 'Slug události je povinný a musí být unikátní.',
    'dates' => 'Konec akce nesmí být dříve než její začátek.',
    'registration_url' => 'Registrační odkaz musí být platná adresa začínající na http:// nebo https://.',
    'organizer_email' => 'E-mail pořadatele musí být platná e-mailová adresa.',
    'image' => 'Obrázek události se nepodařilo uložit.',
    'unpublish_at' => 'Plánované zrušení publikace nemá platný formát data a času.',
];

adminHeader($id ? 'Upravit událost' : 'Nová událost');
?>

<p><a href="events.php"><span aria-hidden="true">&larr;</span> Zpět na přehled událostí</a></p>

<?php if ($id !== null): ?>
  <p><a href="revisions.php?type=event&amp;id=<?= (int)$id ?>">Historie revizí</a></p>
<?php endif; ?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error"><?= h($formError) ?></p>
<?php endif; ?>

<p style="margin-top:0;color:#555">
  Vyplňte potřebné údaje k této události. Můžete doplnit i stručné shrnutí, obrázek, pořadatele, registrační odkaz nebo poznámku k přístupnosti.
</p>

<form method="post" action="event_save.php" enctype="multipart/form-data" novalidate<?= $formError !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id !== null): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Základní údaje události</legend>

    <label for="title">Název <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           <?= adminFieldAttributes('title', $err, $fieldErrorMap) ?>
           value="<?= h((string)$event['title']) ?>">
    <?php adminRenderFieldError('title', $err, $fieldErrorMap, $fieldErrorMessages['title']); ?>

    <label for="slug">Slug (URL události) <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255" pattern="[a-z0-9\-]+"
           <?= adminFieldAttributes('slug', $err, $fieldErrorMap, ['event-slug-help']) ?>
           value="<?= h((string)$event['slug']) ?>">
    <small id="event-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Použijte malá písmena, číslice a pomlčky.</small>
    <?php adminRenderFieldError('slug', $err, $fieldErrorMap, $fieldErrorMessages['slug']); ?>

    <label for="event_kind">Typ akce</label>
    <select id="event_kind" name="event_kind" aria-describedby="event-kind-help">
      <?php foreach (eventKindDefinitions() as $typeKey => $typeMeta): ?>
        <option value="<?= h($typeKey) ?>"<?= $currentEventKind === $typeKey ? ' selected' : '' ?>>
          <?= h((string)($typeMeta['label'] ?? $typeKey)) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <small id="event-kind-help" class="field-help"><?= h($eventKindHelpMap[$currentEventKind] ?? '') ?></small>

    <label for="excerpt">Krátké shrnutí</label>
    <textarea id="excerpt" name="excerpt" rows="3" aria-describedby="event-excerpt-help"><?= h((string)$event['excerpt']) ?></textarea>
    <small id="event-excerpt-help" class="field-help">Volitelné. Hodí se pro přehled akcí, sdílení a vyhledávání. Když ho nevyplníte, použije se začátek popisu.</small>

    <label for="location">Místo konání</label>
    <input type="text" id="location" name="location" maxlength="255"
           value="<?= h((string)$event['location']) ?>">
  </fieldset>

  <fieldset style="border:1px solid #ccc;padding:.5rem 1rem;margin-top:1rem">
    <legend>Termín konání</legend>

    <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label for="event_date">Začátek <span aria-hidden="true">*</span></label>
        <input type="date" id="event_date" name="event_date" required aria-required="true" style="width:auto;display:block;margin-top:.2rem"
               <?= adminFieldAttributes('event_date', $err, $fieldErrorMap, [], 'event-dates-error') ?>
               value="<?= !empty($event['event_date']) ? h(date('Y-m-d', strtotime((string)$event['event_date']))) : '' ?>">
        <?php if (adminFieldHasError('event_date', $err, $fieldErrorMap)): ?>
          <small id="event-dates-error" class="field-help field-error">
            <?= h($err === 'required' ? $fieldErrorMessages['event_date'] : $fieldErrorMessages['dates']) ?>
          </small>
        <?php endif; ?>
      </div>
      <div>
        <label for="event_time">Čas začátku</label>
        <input type="time" id="event_time" name="event_time" style="width:auto;display:block;margin-top:.2rem"
               <?= adminFieldAttributes('event_time', $err, $fieldErrorMap, ['event-time-help'], 'event-dates-error') ?>
               value="<?= !empty($event['event_date']) ? h(date('H:i', strtotime((string)$event['event_date']))) : '' ?>">
        <small id="event-time-help" class="field-help">Nechte prázdné jen tehdy, pokud nevadí výchozí čas 00:00.</small>
      </div>
      <div>
        <label for="event_end_date">Konec</label>
        <input type="date" id="event_end_date" name="event_end_date" style="width:auto;display:block;margin-top:.2rem"
               <?= adminFieldAttributes('event_end_date', $err, $fieldErrorMap, ['event-end-help'], 'event-dates-error') ?>
               value="<?= !empty($event['event_end']) ? h(date('Y-m-d', strtotime((string)$event['event_end']))) : '' ?>">
      </div>
      <div>
        <label for="event_end_time">Čas konce</label>
        <input type="time" id="event_end_time" name="event_end_time" style="width:auto;display:block;margin-top:.2rem"
               <?= adminFieldAttributes('event_end_time', $err, $fieldErrorMap, ['event-end-help'], 'event-dates-error') ?>
               value="<?= !empty($event['event_end']) ? h(date('H:i', strtotime((string)$event['event_end']))) : '' ?>">
      </div>
    </div>
    <small id="event-end-help" class="field-help">Vyplňte, pokud má akce jasný konec nebo trvá více dní. Probíhající akce se pak správně zobrazí i na veřejném webu.</small>
  </fieldset>

  <fieldset>
    <legend>Obsah události</legend>

    <label for="description">Popis události</label>
    <textarea id="description" name="description" rows="8"<?= !$useWysiwyg ? ' aria-describedby="event-description-help"' : ' data-wysiwyg="1"' ?>><?= h((string)$event['description']) ?></textarea>
    <?php if (!$useWysiwyg): ?>
      <small id="event-description-help" class="field-help"><?= adminHtmlSnippetSupportMarkup() ?></small>
      <?php renderAdminContentReferencePicker('description'); ?>
    <?php endif; ?>

    <label for="program_note">Program a doplňující informace</label>
    <textarea id="program_note" name="program_note" rows="6"<?= !$useWysiwyg ? ' aria-describedby="event-program-help"' : ' data-wysiwyg="1"' ?>><?= h((string)$event['program_note']) ?></textarea>
    <?php if (!$useWysiwyg): ?>
      <small id="event-program-help" class="field-help">Volitelné. Můžete sem dopsat program, harmonogram, co si vzít s sebou nebo jiné podrobnosti. <?= adminHtmlSnippetSupportMarkup() ?></small>
      <?php renderAdminContentReferencePicker('program_note'); ?>
    <?php endif; ?>
  </fieldset>

  <fieldset>
    <legend>Pořadatel, registrace a dostupnost</legend>

    <label for="organizer_name">Pořadatel</label>
    <input type="text" id="organizer_name" name="organizer_name" maxlength="255"
           value="<?= h((string)$event['organizer_name']) ?>">

    <label for="organizer_email">E-mail pořadatele</label>
    <input type="email" id="organizer_email" name="organizer_email" maxlength="255"
           <?= adminFieldAttributes('organizer_email', $err, $fieldErrorMap) ?>
           value="<?= h((string)$event['organizer_email']) ?>">
    <?php adminRenderFieldError('organizer_email', $err, $fieldErrorMap, $fieldErrorMessages['organizer_email']); ?>

    <label for="registration_url">Registrační odkaz</label>
    <input type="url" id="registration_url" name="registration_url" maxlength="500"
           <?= adminFieldAttributes('registration_url', $err, $fieldErrorMap, ['event-registration-help']) ?>
           placeholder="https://example.com/registrace"
           value="<?= h((string)$event['registration_url']) ?>">
    <small id="event-registration-help" class="field-help">Volitelné. Hodí se pro přihlášení, vstupenky nebo externí detail akce.</small>
    <?php adminRenderFieldError('registration_url', $err, $fieldErrorMap, $fieldErrorMessages['registration_url']); ?>

    <label for="price_note">Cena / vstupné</label>
    <input type="text" id="price_note" name="price_note" maxlength="255"
           placeholder="např. zdarma, 250 Kč, rezervace nutná"
           value="<?= h((string)$event['price_note']) ?>">

    <label for="accessibility_note">Přístupnost a praktické poznámky</label>
    <textarea id="accessibility_note" name="accessibility_note" rows="3" aria-describedby="event-accessibility-help"><?= h((string)$event['accessibility_note']) ?></textarea>
    <small id="event-accessibility-help" class="field-help">Volitelné. Uveďte třeba bezbariérovost, tlumočení, přístup se psem nebo další důležité informace.</small>
  </fieldset>

  <fieldset>
    <legend>Obrázek a zveřejnění</legend>

    <label for="event_image">Obrázek události</label>
    <input type="file" id="event_image" name="event_image" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
           <?= adminFieldAttributes('event_image', $err, $fieldErrorMap, array_filter(['event-image-help', (string)$event['image_file'] !== '' ? 'event-image-current' : ''])) ?>
           >
    <small id="event-image-help" class="field-help">Volitelné. Hodí se pro přehled akcí, detail události i sdílení na webu.</small>
    <?php adminRenderFieldError('event_image', $err, $fieldErrorMap, $fieldErrorMessages['image']); ?>
    <?php if ((string)$event['image_url'] !== ''): ?>
      <div style="margin:.75rem 0">
        <img src="<?= h((string)$event['image_url']) ?>" alt="Náhled obrázku události" style="max-width:16rem;height:auto;border-radius:12px">
      </div>
      <small id="event-image-current" class="field-help">Aktuální obrázek je nahraný. Nahrajte nový, pokud ho chcete nahradit.</small>
      <label style="font-weight:normal;margin-top:.75rem">
        <input type="checkbox" id="event_image_delete" name="event_image_delete" value="1">
        Smazat aktuální obrázek
      </label>
    <?php endif; ?>

    <label style="font-weight:normal;margin-top:1rem">
      <input type="checkbox" name="is_published" value="1" aria-describedby="event-published-help"
             <?= (int)($event['is_published'] ?? 1) === 1 ? 'checked' : '' ?>>
      Zveřejnit na webu
    </label>
    <small id="event-published-help" class="field-help" style="margin-top:.2rem">Když volbu vypnete, událost zůstane uložená jen v administraci.</small>

    <label for="unpublish_at">Plánované zrušení publikace</label>
    <input type="datetime-local" id="unpublish_at" name="unpublish_at"
           <?= adminFieldAttributes('unpublish_at', $err, $fieldErrorMap, ['event-unpublish-help']) ?>
           style="width:auto" value="<?= h(!empty($event['unpublish_at']) ? date('Y-m-d\TH:i', strtotime((string)$event['unpublish_at'])) : '') ?>">
    <small id="event-unpublish-help" class="field-help">Volitelné. V zadaný čas se událost skryje z veřejného webu, ale zůstane v administraci.</small>
    <?php adminRenderFieldError('unpublish_at', $err, $fieldErrorMap, $fieldErrorMessages['unpublish_at']); ?>
  </fieldset>

  <fieldset style="margin-top:1rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Interní poznámka</legend>
    <label for="admin_note" class="visually-hidden">Interní poznámka</label>
    <textarea id="admin_note" name="admin_note" rows="2" aria-describedby="event-admin-note-help"
              style="min-height:0"><?= h((string)$event['admin_note']) ?></textarea>
    <small id="event-admin-note-help" class="field-help">Viditelná jen v administraci. Na veřejném webu se nezobrazuje.</small>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id !== null ? 'Uložit změny' : 'Přidat událost' ?></button>
    <a href="events.php" style="margin-left:1rem">Zrušit</a>
    <?php if ($id !== null && (string)$event['status'] === 'published' && (int)($event['is_published'] ?? 0) === 1): ?>
      <a href="<?= h(eventPublicPath($event)) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
    <?php endif; ?>
    <?php if ($id !== null && !empty($event['preview_token'])): ?>
      <a href="<?= h(eventPreviewPath($event)) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Náhled</a>
    <?php elseif ($id !== null): ?>
      <small style="margin-left:1rem;color:#666">(Uložte pro aktivaci odkazu „Náhled")</small>
    <?php endif; ?>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
(function () {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    const eventKindInput = document.getElementById('event_kind');
    const eventKindHelp = document.getElementById('event-kind-help');
    const eventKindHelpMap = <?= json_encode($eventKindHelpMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let slugManual = <?= $id !== null && !empty($event['slug']) ? 'true' : 'false' ?>;

    const slugify = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    const syncEventKindHelp = function () {
        if (!eventKindInput || !eventKindHelp) {
            return;
        }
        eventKindHelp.textContent = eventKindHelpMap[eventKindInput.value] || '';
    };

    slugInput?.addEventListener('input', function () {
        slugManual = this.value.trim() !== '';
    });

    titleInput?.addEventListener('input', function () {
        if (slugManual || !slugInput) {
            return;
        }
        slugInput.value = slugify(this.value);
    });

    eventKindInput?.addEventListener('change', syncEventKindHelp);
    syncEventKindHelp();
})();
</script>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script nonce="<?= cspNonce() ?>">
(function () {
    const editors = Array.from(document.querySelectorAll('textarea[data-wysiwyg="1"]'));
    if (!editors.length) {
        return;
    }

    editors.forEach((textarea) => {
        const wrapper = document.createElement('div');
        wrapper.style.cssText = 'background:#fff;border:1px solid #ccc;margin-top:.2rem;min-height:200px';
        textarea.parentNode.insertBefore(wrapper, textarea);
        textarea.style.display = 'none';
        const quill = new Quill(wrapper, { theme: 'snow' });
        quill.root.innerHTML = textarea.value;
        textarea.form?.addEventListener('submit', function () {
            textarea.value = quill.root.innerHTML;
        });
    });
})();
</script>
<?php endif; ?>

<?php adminFooter(); ?>
