<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu typů akcí nemáte potřebné oprávnění.');
requireModuleEnabled('events');

$pdo = db_connect();
$error = '';
$fieldErrors = [];
$fieldErrorMessages = [];
$editId = inputInt('get', 'edit');
$formState = [
    'title' => '',
    'slug' => '',
    'description' => '',
    'meta_title' => '',
    'meta_description' => '',
    'is_active' => '1',
    'sort_order' => '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $updateId = inputInt('post', 'update_id');
    $formState = [
        'title' => trim((string)($_POST['title'] ?? '')),
        'slug' => trim((string)($_POST['slug'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'meta_title' => trim((string)($_POST['meta_title'] ?? '')),
        'meta_description' => trim((string)($_POST['meta_description'] ?? '')),
        'is_active' => isset($_POST['is_active']) ? '1' : '0',
        'sort_order' => (string)max(0, (int)($_POST['sort_order'] ?? 0)),
    ];
    $editId = $updateId;

    if ($formState['title'] === '') {
        $error = 'Typ akce nejde uložit bez názvu. U pole Název je konkrétní nápověda.';
        $fieldErrors[] = 'title';
        $fieldErrorMessages['title'] = 'Doplňte krátký název typu akce, například Workshop.';
    } elseif (mb_strlen($formState['meta_title'], 'UTF-8') > 160) {
        $error = 'Meta title typu akce je příliš dlouhý. U pole Meta titulek je konkrétní nápověda.';
        $fieldErrors[] = 'meta_title';
        $fieldErrorMessages['meta_title'] = 'Zkraťte meta titulek nejvýše na 160 znaků, nebo pole nechte prázdné.';
    } else {
        $submittedSlug = eventTypeSlug($formState['slug'] !== '' ? $formState['slug'] : $formState['title']);
        if ($submittedSlug === '') {
            $error = 'Slug veřejného typu akce není možné vytvořit. U pole Slug je konkrétní nápověda.';
            $fieldErrors[] = 'slug';
            $fieldErrorMessages['slug'] = 'Použijte alespoň jedno písmeno nebo číslo. Vhodný slug může vypadat třeba workshop.';
        } else {
            $uniqueSlug = uniqueEventTypeSlug($pdo, $submittedSlug, $updateId);
            if ($uniqueSlug !== $submittedSlug) {
                $error = 'Slug veřejného typu akce už používá jiný typ. U pole Slug je konkrétní nápověda.';
                $fieldErrors[] = 'slug';
                $fieldErrorMessages['slug'] = 'Zadejte jiný unikátní slug z malých písmen, číslic a pomlček, nebo upravte název typu.';
            } elseif ($updateId !== null) {
                $existingStmt = $pdo->prepare("SELECT * FROM cms_event_types WHERE id = ?");
                $existingStmt->execute([$updateId]);
                $existingType = $existingStmt->fetch() ?: null;
                if (!$existingType) {
                    $error = 'Upravovaný typ akce neexistuje.';
                } else {
                    $pdo->prepare(
                        "UPDATE cms_event_types
                         SET title = ?, slug = ?, description = ?, meta_title = ?, meta_description = ?,
                             is_active = ?, sort_order = ?, updated_at = NOW()
                         WHERE id = ?"
                    )->execute([
                        $formState['title'],
                        $uniqueSlug,
                        $formState['description'],
                        $formState['meta_title'],
                        $formState['meta_description'],
                        (int)$formState['is_active'],
                        (int)$formState['sort_order'],
                        $updateId,
                    ]);

                    $updatedType = ['id' => $updateId, 'slug' => $uniqueSlug];
                    if (eventTypePath($existingType) !== eventTypePath($updatedType)) {
                        upsertPathRedirect($pdo, eventTypePath($existingType), eventTypePath($updatedType));
                    }
                    logAction('event_type_edit', "id={$updateId} title={$formState['title']} slug={$uniqueSlug}");
                    header('Location: ' . BASE_URL . '/admin/event_types.php?ok=save');
                    exit;
                }
            } else {
                $pdo->prepare(
                    "INSERT INTO cms_event_types
                     (legacy_key, title, slug, description, meta_title, meta_description, is_active, sort_order, created_at, updated_at)
                     VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                )->execute([
                    $formState['title'],
                    $uniqueSlug,
                    $formState['description'],
                    $formState['meta_title'],
                    $formState['meta_description'],
                    (int)$formState['is_active'],
                    (int)$formState['sort_order'],
                ]);
                logAction('event_type_add', "title={$formState['title']} slug={$uniqueSlug}");
                header('Location: ' . BASE_URL . '/admin/event_types.php?ok=save');
                exit;
            }
        }
    }
}

if ($editId !== null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $editStmt = $pdo->prepare("SELECT * FROM cms_event_types WHERE id = ?");
    $editStmt->execute([$editId]);
    $editType = $editStmt->fetch() ?: null;
    if ($editType) {
        $formState = [
            'title' => (string)($editType['title'] ?? ''),
            'slug' => (string)($editType['slug'] ?? ''),
            'description' => (string)($editType['description'] ?? ''),
            'meta_title' => (string)($editType['meta_title'] ?? ''),
            'meta_description' => (string)($editType['meta_description'] ?? ''),
            'is_active' => (string)(int)($editType['is_active'] ?? 1),
            'sort_order' => (string)(int)($editType['sort_order'] ?? 0),
        ];
    } else {
        $editId = null;
    }
}

$success = (string)($_GET['ok'] ?? '') === 'save';
$types = $pdo->query(
    "SELECT t.*, COUNT(e.id) AS event_count
     FROM cms_event_types t
     LEFT JOIN cms_events e ON e.event_type_id = t.id AND e.deleted_at IS NULL
     GROUP BY t.id, t.legacy_key, t.title, t.slug, t.description, t.meta_title, t.meta_description,
              t.is_active, t.sort_order, t.created_at, t.updated_at
     ORDER BY t.sort_order, t.title, t.id"
)->fetchAll();

adminHeader('Události – typy akcí');
?>

<?php if ($success): ?><p class="success" role="status">Typ akce byl uložen.</p><?php endif; ?>
<?php if ($error !== ''): ?><p id="form-error" class="error" role="alert" aria-atomic="true"><?= h($error) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <a href="events.php"><span aria-hidden="true">←</span> Zpět na události</a>
</p>

<form method="post" novalidate<?= $error !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($editId !== null): ?>
    <input type="hidden" name="update_id" value="<?= (int)$editId ?>">
  <?php endif; ?>
  <fieldset>
    <legend><?= $editId !== null ? 'Upravit typ akce' : 'Nový typ akce' ?></legend>
    <p id="event-type-help" class="field-help field-help--flush">Aktivní typy se zobrazují ve filtrech a mají veřejnou stránku <code>/events/typ/slug</code>.</p>

    <div class="form-grid">
      <div class="form-group">
        <label for="title">Název <span aria-hidden="true">*</span></label>
        <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
               value="<?= h($formState['title']) ?>"
               <?= adminFieldAttributes('title', $fieldErrors, [], ['event-type-help']) ?>>
        <?php adminRenderFieldError('title', $fieldErrors, [], $fieldErrorMessages['title'] ?? ''); ?>
      </div>
      <div class="form-group">
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" maxlength="150" pattern="[a-z0-9\-]+"
               value="<?= h($formState['slug']) ?>"
               <?= adminFieldAttributes('slug', $fieldErrors, [], ['event-type-slug-help']) ?>>
        <small id="event-type-slug-help" class="field-help">Volitelné. Pokud zůstane prázdný, vytvoří se automaticky z názvu.</small>
        <?php adminRenderFieldError('slug', $fieldErrors, [], $fieldErrorMessages['slug'] ?? ''); ?>
      </div>
      <div class="form-group">
        <label for="sort_order">Pořadí</label>
        <input type="number" id="sort_order" name="sort_order" min="0" value="<?= h($formState['sort_order']) ?>">
      </div>
    </div>

    <div class="form-group">
      <label for="description">Popis</label>
      <textarea id="description" name="description" rows="4" aria-describedby="event-type-description-help"><?= h($formState['description']) ?></textarea>
      <small id="event-type-description-help" class="field-help">Zobrazí se na veřejné stránce typu nad výpisem akcí.</small>
    </div>

    <div class="form-grid">
      <div class="form-group">
        <label for="meta_title">Meta titulek</label>
        <input type="text" id="meta_title" name="meta_title" maxlength="160" value="<?= h($formState['meta_title']) ?>"
               <?= adminFieldAttributes('meta_title', $fieldErrors) ?>>
        <?php adminRenderFieldError('meta_title', $fieldErrors, [], $fieldErrorMessages['meta_title'] ?? ''); ?>
      </div>
      <div class="form-group">
        <label for="meta_description">Meta description</label>
        <textarea id="meta_description" name="meta_description" rows="3"><?= h($formState['meta_description']) ?></textarea>
      </div>
    </div>

    <label class="checkbox-label">
      <input type="checkbox" name="is_active" value="1"<?= $formState['is_active'] === '1' ? ' checked' : '' ?>>
      Aktivní typ zobrazit veřejně
    </label>

    <div class="button-row button-row--start">
      <button type="submit" class="btn"><?= $editId !== null ? 'Uložit typ' : 'Přidat typ' ?></button>
      <?php if ($editId !== null): ?><a href="event_types.php" class="btn">Zrušit úpravu</a><?php endif; ?>
    </div>
  </fieldset>
</form>

<h2>Existující typy akcí</h2>
<?php if ($types === []): ?>
  <p>Zatím tu nejsou žádné typy akcí.</p>
<?php else: ?>
  <table>
    <caption>Správa typů akcí</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Slug</th>
        <th scope="col">Stav</th>
        <th scope="col">Události</th>
        <th scope="col">Veřejná stránka</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($types as $type): ?>
        <tr>
          <td>
            <strong><?= h((string)$type['title']) ?></strong>
            <?php if (trim((string)($type['description'] ?? '')) !== ''): ?>
              <br><small class="table-meta"><?= h(mb_strimwidth(normalizePlainText((string)$type['description']), 0, 120, '…', 'UTF-8')) ?></small>
            <?php endif; ?>
          </td>
          <td><code><?= h((string)$type['slug']) ?></code></td>
          <td><?= (int)$type['is_active'] === 1 ? 'Aktivní' : 'Vypnutý' ?></td>
          <td><?= (int)$type['event_count'] ?></td>
          <td>
            <?php if ((int)$type['is_active'] === 1): ?>
              <a href="<?= h(eventTypePath($type)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
            <?php else: ?>
              <span class="table-meta">Typ je vypnutý.</span>
            <?php endif; ?>
          </td>
          <td class="actions">
            <a href="event_types.php?edit=<?= (int)$type['id'] ?>" class="btn">Upravit</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
