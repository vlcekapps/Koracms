<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/content_reference_picker.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu událostí nemáte potřebné oprávnění.');
requireModuleEnabled('events');

$pdo = db_connect();
$id = inputInt('get', 'id');
$event = null;
$eventPlaceId = 0;
if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_events WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $event = $stmt->fetch() ?: null;
    if (!$event) {
        header('Location: events.php');
        exit;
    }
    $eventPlaceId = (int)($event['place_id'] ?? 0);
}

$eventTypes = loadEventTypes($pdo, false);
$eventTypeByLegacy = [];
foreach ($eventTypes as $eventType) {
    $legacyKey = trim((string)($eventType['legacy_key'] ?? ''));
    if ($legacyKey !== '') {
        $eventTypeByLegacy[$legacyKey] = $eventType;
    }
}
$placeOptions = [];
if (isModuleEnabled('places')) {
    try {
        $placeWhereSql = 'deleted_at IS NULL';
        $placeOptionParams = [];
        if ($eventPlaceId > 0) {
            $placeWhereSql = '(deleted_at IS NULL OR id = ?)';
            $placeOptionParams[] = $eventPlaceId;
        }
        $placeOptionsStmt = $pdo->prepare(
            "SELECT id, name, slug, address, locality, status, is_published, deleted_at
             FROM cms_places
             WHERE {$placeWhereSql}
             ORDER BY name, id"
        );
        $placeOptionsStmt->execute($placeOptionParams);
        $placeOptions = $placeOptionsStmt->fetchAll();
    } catch (PDOException) {
        $placeOptions = [];
    }
}

// Content locking – pokus o získání zámku při editaci existující události
$contentLockWarning = null;
if ($event) {
    $contentLockWarning = acquireContentLock('event', $id);
}

$event = array_merge([
    'title' => '',
    'slug' => '',
    'event_kind' => 'general',
    'event_type_id' => null,
    'place_id' => null,
    'recurrence_group_id' => '',
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
    'publish_at' => '',
    'unpublish_at' => '',
    'admin_note' => '',
    'status' => 'published',
], $event ?? []);
$event = hydrateEventPresentation($event);

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$currentEventKind = normalizeEventKind((string)($event['event_kind'] ?? 'general'));
$currentEventTypeId = (int)($event['event_type_id'] ?? 0);
if ($currentEventTypeId === 0 && isset($eventTypeByLegacy[$currentEventKind])) {
    $currentEventTypeId = (int)$eventTypeByLegacy[$currentEventKind]['id'];
}
$eventTypeHelpMap = [];
foreach ($eventTypes as $eventType) {
    $eventTypeHelpMap[(string)(int)$eventType['id']] = trim((string)($eventType['description'] ?? ''));
}

$err = trim((string)($_GET['err'] ?? ''));
$eventPublishAtErrorMessage = 'Plánované publikování musí být platné datum a čas. Vyberte hodnotu v poli datum a čas nebo pole nechte prázdné pro okamžité zveřejnění.';
$eventUnpublishAtErrorMessage = 'Plánované zrušení publikace musí být platné datum a čas. Vyberte hodnotu v poli datum a čas nebo pole nechte prázdné.';
$eventOrganizerEmailErrorMessage = 'E-mail pořadatele musí být úplná adresa ve tvaru jmeno@example.cz, nebo pole nechte prázdné.';
$eventImageUploadErrorMessage = 'Obrázek události se nepodařilo nahrát. Nahrajte JPEG, PNG, GIF nebo WebP; SVG a jiné formáty CMS nepřijímá. Pokud obrázek nechcete měnit, nechte pole prázdné.';
$formError = match ($err) {
    'required' => 'Událost nejde uložit bez názvu a data začátku. U obou polí je konkrétní nápověda.',
    'slug' => 'Slug události není použitelný nebo už existuje. U pole Slug (URL události) je konkrétní nápověda.',
    'dates' => 'Konec akce nesmí být dříve než její začátek. U polí termínu je konkrétní nápověda.',
    'registration_url' => 'Registrační odkaz není použitelný. U pole Registrační odkaz je konkrétní nápověda.',
    'organizer_email' => $eventOrganizerEmailErrorMessage,
    'image' => $eventImageUploadErrorMessage,
    'unpublish_at' => $eventUnpublishAtErrorMessage,
    'publish_at' => $eventPublishAtErrorMessage,
    'event_type' => 'Typ akce není dostupný. U pole Typ akce je konkrétní nápověda.',
    'place' => 'Vybrané místo není dostupné. U pole Spravované místo konání je konkrétní nápověda.',
    'recurrence' => 'Opakování události není použitelné. U polí opakování je konkrétní nápověda.',
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
    'publish_at' => ['publish_at'],
    'event_type' => ['event_type_id'],
    'place' => ['place_id'],
    'recurrence' => ['recurrence_frequency', 'recurrence_interval', 'recurrence_count'],
];
$fieldErrorMessages = [
    'title' => 'Doplňte krátký název události, například Letní koncert v parku.',
    'event_date' => 'Vyberte datum začátku události.',
    'slug' => 'Použijte jedinečný slug z malých písmen, číslic a pomlček, nebo upravte název pro automatické vytvoření.',
    'dates' => 'Upravte začátek nebo konec tak, aby konec akce byl později než začátek.',
    'registration_url' => 'Zadejte úplnou http/https adresu registrace, nebo pole nechte prázdné.',
    'organizer_email' => $eventOrganizerEmailErrorMessage,
    'image' => $eventImageUploadErrorMessage,
    'unpublish_at' => $eventUnpublishAtErrorMessage,
    'publish_at' => $eventPublishAtErrorMessage,
    'event_type' => 'Vyberte některý z dostupných typů akce, nebo typ nejdřív vytvořte ve správě typů.',
    'place' => 'Vyberte dostupné spravované místo, nebo vazbu na místo ponechte prázdnou a doplňte ručně psané místo.',
    'recurrence' => 'Vyberte typ opakování, interval 1 až 12 a počet termínů 2 až 52.',
];

adminHeader($id ? 'Upravit událost' : 'Nová událost');
?>

<p><a href="events.php"><span aria-hidden="true">&larr;</span> Zpět na přehled událostí</a></p>

<?php if ($id !== null): ?>
  <p><a href="revisions.php?type=event&amp;id=<?= (int)$id ?>">Historie revizí</a></p>
<?php endif; ?>

<?php if ($contentLockWarning !== null): ?>
  <div role="alert" class="admin-warning-box">
    <strong>Upozornění:</strong>
    Tuto událost právě upravuje <?= h((string)$contentLockWarning['locked_by']) ?>
    (od <?= h(date('H:i', strtotime((string)$contentLockWarning['locked_at']))) ?>).
    Vaše změny mohou přepsat jejich práci.
  </div>
<?php endif; ?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error" aria-atomic="true"><?= h($formError) ?></p>
<?php endif; ?>

<p class="admin-description admin-description--flush admin-description--muted">
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

    <label for="event_type_id">Typ akce</label>
    <select id="event_type_id" name="event_type_id"<?= adminFieldAttributes('event_type_id', $err, $fieldErrorMap, ['event-kind-help']) ?>>
      <?php foreach ($eventTypes as $eventType): ?>
        <option value="<?= (int)$eventType['id'] ?>"<?= $currentEventTypeId === (int)$eventType['id'] ? ' selected' : '' ?>>
          <?= h((string)$eventType['title']) ?><?= (int)$eventType['is_active'] === 0 ? ' (vypnuto)' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <small id="event-kind-help" class="field-help">
      <?= h($eventTypeHelpMap[(string)$currentEventTypeId] ?? 'Typy akcí spravujete v samostatné obrazovce. Každý aktivní typ má vlastní veřejnou stránku.') ?>
      <a href="event_types.php">Spravovat typy akcí</a>.
    </small>
    <?php adminRenderFieldError('event_type_id', $err, $fieldErrorMap, $fieldErrorMessages['event_type']); ?>

    <label for="excerpt">Krátké shrnutí</label>
    <textarea id="excerpt" name="excerpt" rows="3" aria-describedby="event-excerpt-help"><?= h((string)$event['excerpt']) ?></textarea>
    <small id="event-excerpt-help" class="field-help">Volitelné. Hodí se pro přehled akcí, sdílení a vyhledávání. Když ho nevyplníte, použije se začátek popisu.</small>

    <label for="place_id">Spravované místo konání</label>
    <select id="place_id" name="place_id"<?= adminFieldAttributes('place_id', $err, $fieldErrorMap, ['event-place-help']) ?>>
      <option value="">Bez vazby na spravované místo</option>
      <?php foreach ($placeOptions as $placeOption): ?>
        <?php
        $placeLabel = trim(implode(', ', array_filter([
            (string)($placeOption['name'] ?? ''),
            (string)($placeOption['locality'] ?? ''),
        ], static fn (string $value): bool => $value !== '')));
          ?>
        <option value="<?= (int)$placeOption['id'] ?>"<?= (int)($event['place_id'] ?? 0) === (int)$placeOption['id'] ? ' selected' : '' ?>>
          <?= h($placeLabel) ?><?php if (($placeOption['deleted_at'] ?? null) !== null): ?> (v Koši; vazba se zachová pro obnovení)<?php elseif ((string)($placeOption['status'] ?? 'published') !== 'published' || (int)($placeOption['is_published'] ?? 1) !== 1): ?> (není veřejné)<?php endif; ?>
        </option>
      <?php endforeach; ?>
    </select>
    <small id="event-place-help" class="field-help">
      Volitelné. Pokud modul Místa není aktivní nebo místo není veřejné, událost dál použije ručně vyplněné místo.
      <?php if (isModuleEnabled('places')): ?><a href="place_form.php?redirect=<?= h(rawurlencode(BASE_URL . '/admin/event_form.php' . ($id !== null ? '?id=' . (int)$id : ''))) ?>">Přidat místo</a>.<?php endif; ?>
    </small>
    <?php adminRenderFieldError('place_id', $err, $fieldErrorMap, $fieldErrorMessages['place']); ?>

    <label for="location">Ručně psané místo nebo upřesnění</label>
    <input type="text" id="location" name="location" maxlength="255"
           aria-describedby="event-location-help"
           value="<?= h((string)$event['location']) ?>">
    <small id="event-location-help" class="field-help">Může zůstat prázdné, pokud stačí vybrané spravované místo. Hodí se třeba pro sál, patro nebo online odkaz.</small>
  </fieldset>

  <fieldset class="admin-fieldset-card admin-action-row">
    <legend>Termín konání</legend>

    <div class="button-row button-row--baseline">
      <div>
        <label for="event_date">Začátek <span aria-hidden="true">*</span></label>
        <input type="date" id="event_date" name="event_date" required aria-required="true" class="admin-input-auto"
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
        <input type="time" id="event_time" name="event_time" class="admin-input-auto"
               <?= adminFieldAttributes('event_time', $err, $fieldErrorMap, ['event-time-help'], 'event-dates-error') ?>
               value="<?= !empty($event['event_date']) ? h(date('H:i', strtotime((string)$event['event_date']))) : '' ?>">
        <small id="event-time-help" class="field-help">Nechte prázdné jen tehdy, pokud nevadí výchozí čas 00:00.</small>
      </div>
      <div>
        <label for="event_end_date">Konec</label>
        <input type="date" id="event_end_date" name="event_end_date" class="admin-input-auto"
               <?= adminFieldAttributes('event_end_date', $err, $fieldErrorMap, ['event-end-help'], 'event-dates-error') ?>
               value="<?= !empty($event['event_end']) ? h(date('Y-m-d', strtotime((string)$event['event_end']))) : '' ?>">
      </div>
      <div>
        <label for="event_end_time">Čas konce</label>
        <input type="time" id="event_end_time" name="event_end_time" class="admin-input-auto"
               <?= adminFieldAttributes('event_end_time', $err, $fieldErrorMap, ['event-end-help'], 'event-dates-error') ?>
               value="<?= !empty($event['event_end']) ? h(date('H:i', strtotime((string)$event['event_end']))) : '' ?>">
      </div>
    </div>
    <small id="event-end-help" class="field-help">Vyplňte, pokud má akce jasný konec nebo trvá více dní. Probíhající akce se pak správně zobrazí i na veřejném webu.</small>
  </fieldset>

  <?php if ($id === null): ?>
    <fieldset class="admin-fieldset-card admin-action-row">
      <legend>Opakované termíny</legend>
      <p id="event-recurrence-help" class="field-help field-help--flush">Opakování vytvoří skutečné samostatné události. Pozdější úprava jednoho termínu se automaticky nepropíše do celé série.</p>
      <div class="form-grid">
        <div class="form-group">
          <label for="recurrence_frequency">Opakování</label>
          <select id="recurrence_frequency" name="recurrence_frequency"<?= adminFieldAttributes('recurrence_frequency', $err, $fieldErrorMap, ['event-recurrence-help'], 'event-recurrence-error') ?>>
            <option value="none">Žádné</option>
            <option value="daily">Denně</option>
            <option value="weekly">Týdně</option>
            <option value="monthly">Měsíčně</option>
          </select>
        </div>
        <div class="form-group">
          <label for="recurrence_interval">Interval</label>
          <input type="number" id="recurrence_interval" name="recurrence_interval" min="1" max="12" value="1"<?= adminFieldAttributes('recurrence_interval', $err, $fieldErrorMap, ['event-recurrence-help'], 'event-recurrence-error') ?>>
        </div>
        <div class="form-group">
          <label for="recurrence_count">Počet termínů</label>
          <input type="number" id="recurrence_count" name="recurrence_count" min="2" max="52" value="2"<?= adminFieldAttributes('recurrence_count', $err, $fieldErrorMap, ['event-recurrence-help'], 'event-recurrence-error') ?>>
        </div>
      </div>
      <?php if (adminFieldHasError('recurrence_frequency', $err, $fieldErrorMap)): ?>
        <small id="event-recurrence-error" class="field-help field-error"><?= h($fieldErrorMessages['recurrence']) ?></small>
      <?php endif; ?>
    </fieldset>
  <?php elseif ((string)($event['recurrence_group_id'] ?? '') !== ''): ?>
    <p class="admin-description admin-description--muted">
      Tato událost je součástí opakované série. Úpravy se týkají jen tohoto konkrétního termínu.
    </p>
  <?php endif; ?>

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
    <small id="event-image-help" class="field-help">Volitelné. Hodí se pro přehled akcí, detail události i sdílení na webu. Nahrajte JPEG, PNG, GIF nebo WebP; SVG a jiné formáty CMS nepřijímá.</small>
    <?php adminRenderFieldError('event_image', $err, $fieldErrorMap, $fieldErrorMessages['image']); ?>
    <?php if ((string)$event['image_url'] !== ''): ?>
      <div class="admin-preview-block">
        <img src="<?= h((string)$event['image_url']) ?>" alt="Náhled obrázku události" class="admin-image-preview">
      </div>
      <small id="event-image-current" class="field-help">Aktuální obrázek je nahraný. Nahrajte nový, pokud ho chcete nahradit.</small>
      <div class="admin-field-row">
        <label class="admin-checkbox-label">
          <input type="checkbox" id="event_image_delete" name="event_image_delete" value="1">
          Smazat aktuální obrázek
        </label>
      </div>
    <?php endif; ?>

    <div class="admin-action-row">
      <label class="admin-checkbox-label">
        <input type="checkbox" name="is_published" value="1" aria-describedby="event-published-help"
               <?= (int)($event['is_published'] ?? 1) === 1 ? 'checked' : '' ?>>
        Zveřejnit na webu
      </label>
    </div>
    <small id="event-published-help" class="field-help">Když volbu vypnete, událost zůstane uložená jen v administraci.</small>

    <label for="publish_at">Plánované publikování</label>
    <input type="datetime-local" id="publish_at" name="publish_at" class="admin-input-auto"
           <?= adminFieldAttributes('publish_at', $err, $fieldErrorMap, ['event-publish-help']) ?>
           value="<?= h(!empty($event['publish_at']) ? date('Y-m-d\TH:i', strtotime((string)$event['publish_at'])) : '') ?>">
    <small id="event-publish-help" class="field-help">Nechte prázdné pro okamžité zveřejnění.</small>
    <?php adminRenderFieldError('publish_at', $err, $fieldErrorMap, $fieldErrorMessages['publish_at']); ?>

    <label for="unpublish_at">Plánované zrušení publikace</label>
    <input type="datetime-local" id="unpublish_at" name="unpublish_at" class="admin-input-auto"
           <?= adminFieldAttributes('unpublish_at', $err, $fieldErrorMap, ['event-unpublish-help']) ?>
           value="<?= h(!empty($event['unpublish_at']) ? date('Y-m-d\TH:i', strtotime((string)$event['unpublish_at'])) : '') ?>">
    <small id="event-unpublish-help" class="field-help">Volitelné. V zadaný čas se událost skryje z veřejného webu, ale zůstane v administraci.</small>
    <?php adminRenderFieldError('unpublish_at', $err, $fieldErrorMap, $fieldErrorMessages['unpublish_at']); ?>
  </fieldset>

  <fieldset class="admin-fieldset-card admin-action-row">
    <legend>Interní poznámka</legend>
    <label for="admin_note" class="visually-hidden">Interní poznámka</label>
    <textarea id="admin_note" name="admin_note" rows="2" aria-describedby="event-admin-note-help"
              class="admin-textarea-compact"><?= h((string)$event['admin_note']) ?></textarea>
    <small id="event-admin-note-help" class="field-help">Viditelná jen v administraci. Na veřejném webu se nezobrazuje.</small>
  </fieldset>

  <fieldset class="admin-fieldset-card admin-action-row">
    <legend>Stav publikace</legend>
    <label for="article_status">Stav</label>
    <select id="article_status" name="article_status">
      <option value="draft"<?= ($event['status'] ?? '') === 'draft' ? ' selected' : '' ?>>Koncept</option>
      <?php if (currentUserHasCapability('content_approve_shared')): ?>
        <option value="published"<?= ($event['status'] ?? 'published') === 'published' ? ' selected' : '' ?>>Publikováno</option>
      <?php endif; ?>
      <option value="pending"<?= ($event['status'] ?? '') === 'pending' ? ' selected' : '' ?>>Čeká na schválení</option>
    </select>
  </fieldset>

  <div class="button-row admin-fieldset-spaced">
    <button type="submit" class="btn"><?= $id !== null ? 'Uložit změny' : 'Přidat událost' ?></button>
    <a href="events.php">Zrušit</a>
    <?php if ($id !== null && (string)$event['status'] === 'published' && (int)($event['is_published'] ?? 0) === 1): ?>
      <a href="<?= h(eventPublicPath($event)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
    <?php endif; ?>
    <?php if ($id !== null && !empty($event['preview_token'])): ?>
      <a href="<?= h(eventPreviewPath($event)) ?>" target="_blank" rel="noopener noreferrer">Náhled<?= newWindowLinkSrOnlySuffix() ?></a>
    <?php elseif ($id !== null): ?>
      <small class="field-help field-help--flush">(Uložte pro aktivaci odkazu „Náhled")</small>
    <?php endif; ?>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
(function () {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    const eventKindInput = document.getElementById('event_type_id');
    const eventKindHelp = document.getElementById('event-kind-help');
    const eventKindHelpMap = <?= json_encode($eventTypeHelpMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
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

<?php if ($event && $id !== null): ?>
<?php adminRenderContentLockRefreshScript('event', $id); ?>
<?php endif; ?>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script nonce="<?= cspNonce() ?>" src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script nonce="<?= cspNonce() ?>">
(function () {
    const editors = Array.from(document.querySelectorAll('textarea[data-wysiwyg="1"]'));
    if (!editors.length) {
        return;
    }

    editors.forEach((textarea) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'admin-rich-editor-frame admin-rich-editor-base';
        textarea.parentNode.insertBefore(wrapper, textarea);
        textarea.hidden = true;
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
