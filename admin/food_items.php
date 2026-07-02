<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu položek jídelních lístků nemáte potřebné oprávnění.');
requireModuleEnabled('food');

$pdo = db_connect();
$cardId = inputInt('get', 'card') ?? inputInt('post', 'card_id');
if ($cardId === null) {
    header('Location: ' . BASE_URL . '/admin/food.php');
    exit;
}

$cardStmt = $pdo->prepare("SELECT * FROM cms_food_cards WHERE id = ? AND deleted_at IS NULL");
$cardStmt->execute([$cardId]);
$card = $cardStmt->fetch() ?: null;
if (!$card) {
    header('Location: ' . BASE_URL . '/admin/food.php');
    exit;
}
$card = hydrateFoodCardPresentation($card);

$message = trim((string)($_GET['msg'] ?? ''));
$error = '';
$fieldErrors = [];
$editSectionId = inputInt('get', 'edit_section');
$editItemId = inputInt('get', 'edit_item');
$sectionState = [
    'id' => 0,
    'title' => '',
    'description' => '',
    'sort_order' => '0',
];
$itemState = [
    'id' => 0,
    'section_id' => '',
    'title' => '',
    'description' => '',
    'price_amount' => '',
    'price_currency' => 'CZK',
    'price_note' => '',
    'allergens' => [],
    'dietary_flags' => [],
    'is_available' => '1',
    'sort_order' => '0',
];

$redirectToItems = static function (string $messageKey = '') use ($cardId): void {
    $query = ['card' => $cardId];
    if ($messageKey !== '') {
        $query['msg'] = $messageKey;
    }
    header('Location: ' . BASE_URL . '/admin/food_items.php?' . http_build_query($query));
    exit;
};

$sectionBelongsToCard = static function (?int $sectionId) use ($pdo, $cardId): bool {
    if ($sectionId === null) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT id FROM cms_food_sections WHERE id = ? AND card_id = ?");
    $stmt->execute([$sectionId, $cardId]);

    return (bool)$stmt->fetch();
};

$itemBelongsToCard = static function (?int $itemId) use ($pdo, $cardId): bool {
    if ($itemId === null) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT id FROM cms_food_items WHERE id = ? AND card_id = ?");
    $stmt->execute([$itemId, $cardId]);

    return (bool)$stmt->fetch();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'delete_section') {
        $deleteSectionId = inputInt('post', 'section_id');
        if ($sectionBelongsToCard($deleteSectionId)) {
            $pdo->prepare("DELETE FROM cms_food_items WHERE section_id = ? AND card_id = ?")->execute([$deleteSectionId, $cardId]);
            $pdo->prepare("DELETE FROM cms_food_sections WHERE id = ? AND card_id = ?")->execute([$deleteSectionId, $cardId]);
            logAction('food_section_delete', "card={$cardId} section={$deleteSectionId}");
        }
        $redirectToItems('deleted');
    }

    if ($action === 'delete_item') {
        $deleteItemId = inputInt('post', 'item_id');
        if ($itemBelongsToCard($deleteItemId)) {
            $pdo->prepare("DELETE FROM cms_food_items WHERE id = ? AND card_id = ?")->execute([$deleteItemId, $cardId]);
            logAction('food_item_delete', "card={$cardId} item={$deleteItemId}");
        }
        $redirectToItems('deleted');
    }

    if ($action === 'save_section') {
        $sectionId = inputInt('post', 'section_id');
        $sectionState = [
            'id' => $sectionId ?? 0,
            'title' => trim((string)($_POST['title'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'sort_order' => (string)max(0, (int)($_POST['sort_order'] ?? 0)),
        ];
        $editSectionId = $sectionId;

        if ($sectionState['title'] === '') {
            $error = 'Název sekce je povinný.';
            $fieldErrors[] = 'section_title';
        } elseif ($sectionId !== null && !$sectionBelongsToCard($sectionId)) {
            $error = 'Upravovaná sekce nepatří k tomuto lístku.';
        } elseif ($sectionId !== null) {
            $pdo->prepare(
                "UPDATE cms_food_sections
                 SET title = ?, description = ?, sort_order = ?, updated_at = NOW()
                 WHERE id = ? AND card_id = ?"
            )->execute([
                $sectionState['title'],
                $sectionState['description'],
                (int)$sectionState['sort_order'],
                $sectionId,
                $cardId,
            ]);
            logAction('food_section_edit', "card={$cardId} section={$sectionId}");
            $redirectToItems('saved');
        } else {
            $sortOrder = (int)$sectionState['sort_order'];
            if ($sortOrder <= 0) {
                $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM cms_food_sections WHERE card_id = ?");
                $maxStmt->execute([$cardId]);
                $sortOrder = (int)$maxStmt->fetchColumn();
            }
            $pdo->prepare(
                "INSERT INTO cms_food_sections (card_id, title, description, sort_order)
                 VALUES (?, ?, ?, ?)"
            )->execute([$cardId, $sectionState['title'], $sectionState['description'], $sortOrder]);
            logAction('food_section_add', "card={$cardId}");
            $redirectToItems('saved');
        }
    }

    if ($action === 'save_item') {
        $itemId = inputInt('post', 'item_id');
        $sectionId = inputInt('post', 'section_id');
        $priceAmount = normalizeFoodPriceInput((string)($_POST['price_amount'] ?? ''));
        $allergens = normalizeFoodAllergenList($_POST['allergens'] ?? []);
        $dietaryFlags = normalizeFoodDietaryFlags($_POST['dietary_flags'] ?? []);
        $itemState = [
            'id' => $itemId ?? 0,
            'section_id' => $sectionId !== null ? (string)$sectionId : '',
            'title' => trim((string)($_POST['title'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'price_amount' => $priceAmount !== false && $priceAmount !== null ? $priceAmount : trim((string)($_POST['price_amount'] ?? '')),
            'price_currency' => normalizeFoodCurrency((string)($_POST['price_currency'] ?? 'CZK')),
            'price_note' => trim((string)($_POST['price_note'] ?? '')),
            'allergens' => $allergens,
            'dietary_flags' => $dietaryFlags,
            'is_available' => isset($_POST['is_available']) ? '1' : '0',
            'sort_order' => (string)max(0, (int)($_POST['sort_order'] ?? 0)),
        ];
        $editItemId = $itemId;

        if ($itemState['title'] === '') {
            $error = 'Název položky je povinný.';
            $fieldErrors[] = 'item_title';
        } elseif (!$sectionBelongsToCard($sectionId)) {
            $error = 'Vyberte platnou sekci tohoto lístku.';
            $fieldErrors[] = 'item_section_id';
        } elseif ($priceAmount === false) {
            $error = 'Cena musí být číslo s nejvýše dvěma desetinnými místy.';
            $fieldErrors[] = 'item_price_amount';
        } elseif ($itemId !== null && !$itemBelongsToCard($itemId)) {
            $error = 'Upravovaná položka nepatří k tomuto lístku.';
        } elseif ($itemId !== null) {
            $pdo->prepare(
                "UPDATE cms_food_items
                 SET section_id = ?, title = ?, description = ?, price_amount = ?, price_currency = ?,
                     price_note = ?, allergens = ?, dietary_flags = ?, is_available = ?, sort_order = ?, updated_at = NOW()
                 WHERE id = ? AND card_id = ?"
            )->execute([
                $sectionId,
                $itemState['title'],
                $itemState['description'],
                $priceAmount,
                $itemState['price_currency'],
                $itemState['price_note'],
                implode(',', $allergens),
                implode(',', $dietaryFlags),
                (int)$itemState['is_available'],
                (int)$itemState['sort_order'],
                $itemId,
                $cardId,
            ]);
            logAction('food_item_edit', "card={$cardId} item={$itemId}");
            $redirectToItems('saved');
        } else {
            $sortOrder = (int)$itemState['sort_order'];
            if ($sortOrder <= 0) {
                $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM cms_food_items WHERE section_id = ? AND card_id = ?");
                $maxStmt->execute([$sectionId, $cardId]);
                $sortOrder = (int)$maxStmt->fetchColumn();
            }
            $pdo->prepare(
                "INSERT INTO cms_food_items
                 (card_id, section_id, title, description, price_amount, price_currency, price_note,
                  allergens, dietary_flags, is_available, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $cardId,
                $sectionId,
                $itemState['title'],
                $itemState['description'],
                $priceAmount,
                $itemState['price_currency'],
                $itemState['price_note'],
                implode(',', $allergens),
                implode(',', $dietaryFlags),
                (int)$itemState['is_available'],
                $sortOrder,
            ]);
            logAction('food_item_add', "card={$cardId} section={$sectionId}");
            $redirectToItems('saved');
        }
    }
}

if ($editSectionId !== null && $error === '') {
    $editSectionStmt = $pdo->prepare("SELECT id, title, description, sort_order FROM cms_food_sections WHERE id = ? AND card_id = ?");
    $editSectionStmt->execute([$editSectionId, $cardId]);
    $editSection = $editSectionStmt->fetch() ?: null;
    if ($editSection) {
        $sectionState = [
            'id' => (int)$editSection['id'],
            'title' => (string)$editSection['title'],
            'description' => (string)($editSection['description'] ?? ''),
            'sort_order' => (string)(int)$editSection['sort_order'],
        ];
    } else {
        $editSectionId = null;
    }
}

$sections = foodLoadCardSections($pdo, $cardId);

if ($editItemId !== null && $error === '') {
    $editItemStmt = $pdo->prepare("SELECT * FROM cms_food_items WHERE id = ? AND card_id = ?");
    $editItemStmt->execute([$editItemId, $cardId]);
    $editItem = $editItemStmt->fetch() ?: null;
    if ($editItem) {
        $editItem = hydrateFoodItemPresentation($editItem);
        $itemState = [
            'id' => (int)$editItem['id'],
            'section_id' => (string)(int)$editItem['section_id'],
            'title' => (string)$editItem['title'],
            'description' => (string)($editItem['description'] ?? ''),
            'price_amount' => $editItem['price_amount'] !== null ? (string)$editItem['price_amount'] : '',
            'price_currency' => (string)$editItem['price_currency'],
            'price_note' => (string)$editItem['price_note'],
            'allergens' => $editItem['allergen_values'],
            'dietary_flags' => $editItem['dietary_flag_values'],
            'is_available' => (string)(int)$editItem['is_available'],
            'sort_order' => (string)(int)$editItem['sort_order'],
        ];
    } else {
        $editItemId = null;
    }
}

$allergenDefinitions = foodAllergenDefinitions();
$dietaryFlagDefinitions = foodDietaryFlagDefinitions();

adminHeader('Položky lístku: ' . (string)$card['title']);
?>

<?php if ($message === 'saved'): ?><p class="success" role="status">Položky lístku byly uloženy.</p><?php endif; ?>
<?php if ($message === 'deleted'): ?><p class="success" role="status">Položka nebo sekce byla smazána.</p><?php endif; ?>
<?php if ($error !== ''): ?><p id="food-items-error" class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <a href="food.php"><span aria-hidden="true">&larr;</span> Zpět na lístky</a>
  <a href="food_form.php?id=<?= (int)$cardId ?>">Upravit údaje lístku</a>
  <?php if (!empty($card['is_publicly_visible'])): ?>
    <a href="<?= h((string)$card['public_path']) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
  <?php endif; ?>
</p>

<p class="admin-description">
  Strukturované položky se na webu zobrazí před volným HTML obsahem lístku. Když žádné položky nezadáte, veřejný web dál použije původní obsah lístku.
</p>

<section class="form-card" aria-labelledby="food-section-form-title">
  <h2 id="food-section-form-title"><?= (int)$sectionState['id'] > 0 ? 'Upravit sekci' : 'Přidat sekci' ?></h2>
  <form method="post" novalidate<?= $error !== '' && in_array('section_title', $fieldErrors, true) ? ' aria-describedby="food-items-error"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="save_section">
    <input type="hidden" name="card_id" value="<?= (int)$cardId ?>">
    <input type="hidden" name="section_id" value="<?= (int)$sectionState['id'] ?>">
    <fieldset>
      <legend>Sekce lístku</legend>
      <label for="section-title">Název sekce <span aria-hidden="true">*</span></label>
      <input type="text" id="section-title" name="title" required aria-required="true" maxlength="255"
             value="<?= h((string)$sectionState['title']) ?>"
             <?= adminFieldAttributes('section_title', $fieldErrors, [], ['section-title-help']) ?>>
      <small id="section-title-help" class="field-help">Například Polévky, Hlavní jídla, Dezerty nebo Teplé nápoje.</small>
      <?php adminRenderFieldError('section_title', $fieldErrors, [], $error); ?>

      <label for="section-description">Popis sekce</label>
      <textarea id="section-description" name="description" rows="3"><?= h((string)$sectionState['description']) ?></textarea>

      <label for="section-sort">Pořadí</label>
      <input type="number" id="section-sort" name="sort_order" min="0" class="admin-input-auto" value="<?= h((string)$sectionState['sort_order']) ?>" aria-describedby="section-sort-help">
      <small id="section-sort-help" class="field-help">Nižší číslo se zobrazí dříve. Prázdné nebo nula zařadí novou sekci na konec.</small>

      <div class="button-row button-row--start admin-action-row">
        <button type="submit" class="btn"><?= (int)$sectionState['id'] > 0 ? 'Uložit sekci' : 'Přidat sekci' ?></button>
        <?php if ((int)$sectionState['id'] > 0): ?><a class="btn" href="food_items.php?card=<?= (int)$cardId ?>">Zrušit úpravu</a><?php endif; ?>
      </div>
    </fieldset>
  </form>
</section>

<section class="form-card" aria-labelledby="food-item-form-title">
  <h2 id="food-item-form-title"><?= (int)$itemState['id'] > 0 ? 'Upravit položku' : 'Přidat položku' ?></h2>
  <?php if ($sections === []): ?>
    <p class="field-help field-help--flush">Nejprve přidejte alespoň jednu sekci lístku.</p>
  <?php else: ?>
    <form method="post" novalidate<?= $error !== '' && array_intersect($fieldErrors, ['item_title', 'item_section_id', 'item_price_amount']) !== [] ? ' aria-describedby="food-items-error"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="action" value="save_item">
      <input type="hidden" name="card_id" value="<?= (int)$cardId ?>">
      <input type="hidden" name="item_id" value="<?= (int)$itemState['id'] ?>">
      <fieldset>
        <legend>Položka lístku</legend>

        <label for="item-section">Sekce <span aria-hidden="true">*</span></label>
        <select id="item-section" name="section_id" class="admin-input-auto"
                <?= adminFieldAttributes('item_section_id', $fieldErrors, []) ?>>
          <?php foreach ($sections as $section): ?>
            <option value="<?= (int)$section['id'] ?>"<?= (string)$itemState['section_id'] === (string)(int)$section['id'] ? ' selected' : '' ?>>
              <?= h((string)$section['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php adminRenderFieldError('item_section_id', $fieldErrors, [], $error); ?>

        <label for="item-title">Název položky <span aria-hidden="true">*</span></label>
        <input type="text" id="item-title" name="title" required aria-required="true" maxlength="255"
               value="<?= h((string)$itemState['title']) ?>"
               <?= adminFieldAttributes('item_title', $fieldErrors, []) ?>>
        <?php adminRenderFieldError('item_title', $fieldErrors, [], $error); ?>

        <label for="item-description">Popis položky</label>
        <textarea id="item-description" name="description" rows="3" aria-describedby="item-description-help"><?= h((string)$itemState['description']) ?></textarea>
        <small id="item-description-help" class="field-help">Volitelně doplňte složení, porci nebo krátkou poznámku.</small>

        <div class="form-grid">
          <div class="form-group">
            <label for="item-price">Cena</label>
            <input type="text" id="item-price" name="price_amount" inputmode="decimal" maxlength="20"
                   value="<?= h((string)$itemState['price_amount']) ?>"
                   <?= adminFieldAttributes('item_price_amount', $fieldErrors, [], ['item-price-help']) ?>>
            <small id="item-price-help" class="field-help">Volitelné. Použijte například 129 nebo 129,90.</small>
            <?php adminRenderFieldError('item_price_amount', $fieldErrors, [], $error); ?>
          </div>
          <div class="form-group">
            <label for="item-currency">Měna</label>
            <input type="text" id="item-currency" name="price_currency" maxlength="3" class="admin-input-auto" value="<?= h((string)$itemState['price_currency']) ?>">
          </div>
          <div class="form-group">
            <label for="item-price-note">Poznámka k ceně</label>
            <input type="text" id="item-price-note" name="price_note" maxlength="255" value="<?= h((string)$itemState['price_note']) ?>" placeholder="např. za 100 g">
          </div>
        </div>

        <fieldset class="admin-fieldset-card">
          <legend>Alergeny</legend>
          <p class="field-help field-help--flush">Vyberte čísla alergenů, která se u položky zobrazí čtenářům textově.</p>
          <div class="checkbox-grid">
            <?php foreach ($allergenDefinitions as $allergenNumber => $allergenLabel): ?>
              <label class="admin-checkbox-label">
                <input type="checkbox" name="allergens[]" value="<?= (int)$allergenNumber ?>"<?= in_array((int)$allergenNumber, $itemState['allergens'], true) ? ' checked' : '' ?>>
                <?= (int)$allergenNumber ?> - <?= h($allergenLabel) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="admin-fieldset-card">
          <legend>Dietní štítky</legend>
          <div class="checkbox-grid">
            <?php foreach ($dietaryFlagDefinitions as $flagKey => $flagLabel): ?>
              <label class="admin-checkbox-label">
                <input type="checkbox" name="dietary_flags[]" value="<?= h($flagKey) ?>"<?= in_array($flagKey, $itemState['dietary_flags'], true) ? ' checked' : '' ?>>
                <?= h($flagLabel) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <label class="admin-checkbox-label">
          <input type="checkbox" name="is_available" value="1"<?= $itemState['is_available'] === '1' ? ' checked' : '' ?>>
          Položka je dostupná
        </label>

        <label for="item-sort">Pořadí</label>
        <input type="number" id="item-sort" name="sort_order" min="0" class="admin-input-auto" value="<?= h((string)$itemState['sort_order']) ?>">

        <div class="button-row button-row--start admin-action-row">
          <button type="submit" class="btn"><?= (int)$itemState['id'] > 0 ? 'Uložit položku' : 'Přidat položku' ?></button>
          <?php if ((int)$itemState['id'] > 0): ?><a class="btn" href="food_items.php?card=<?= (int)$cardId ?>">Zrušit úpravu</a><?php endif; ?>
        </div>
      </fieldset>
    </form>
  <?php endif; ?>
</section>

<h2>Strukturovaný obsah lístku</h2>
<?php if ($sections === []): ?>
  <p>Zatím tu nejsou žádné strukturované sekce ani položky.</p>
<?php else: ?>
  <?php foreach ($sections as $section): ?>
    <section class="admin-section-card" aria-labelledby="food-section-<?= (int)$section['id'] ?>">
      <div class="section-heading">
        <div>
          <h3 id="food-section-<?= (int)$section['id'] ?>"><?= h((string)$section['title']) ?></h3>
          <?php if (trim((string)($section['description'] ?? '')) !== ''): ?>
            <p class="field-help field-help--flush"><?= h((string)$section['description']) ?></p>
          <?php endif; ?>
        </div>
        <div class="actions">
          <a class="btn" href="food_items.php?card=<?= (int)$cardId ?>&amp;edit_section=<?= (int)$section['id'] ?>">Upravit sekci</a>
          <form method="post" class="admin-inline-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="action" value="delete_section">
            <input type="hidden" name="card_id" value="<?= (int)$cardId ?>">
            <input type="hidden" name="section_id" value="<?= (int)$section['id'] ?>">
            <button type="submit" class="btn btn-danger" data-confirm="Smazat sekci včetně všech položek?">Smazat sekci</button>
          </form>
        </div>
      </div>

      <?php if (empty($section['items'])): ?>
        <p class="field-help">Tato sekce zatím nemá žádné položky.</p>
      <?php else: ?>
        <table>
          <caption>Položky sekce <?= h((string)$section['title']) ?></caption>
          <thead>
            <tr>
              <th scope="col">Položka</th>
              <th scope="col">Cena</th>
              <th scope="col">Alergeny a štítky</th>
              <th scope="col">Stav</th>
              <th scope="col">Akce</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($section['items'] as $item): ?>
              <tr>
                <td>
                  <strong><?= h((string)$item['title']) ?></strong>
                  <?php if (trim((string)($item['description'] ?? '')) !== ''): ?>
                    <br><small class="table-meta"><?= h((string)$item['description']) ?></small>
                  <?php endif; ?>
                </td>
                <td><?= h((string)($item['price_label'] !== '' ? $item['price_label'] : 'Bez ceny')) ?></td>
                <td>
                  <?php if (!empty($item['allergen_labels'])): ?>
                    <small class="table-meta">Alergeny: <?= h(implode(', ', $item['allergen_labels'])) ?></small><br>
                  <?php endif; ?>
                  <?php if (!empty($item['dietary_flag_labels'])): ?>
                    <small class="table-meta">Štítky: <?= h(implode(', ', $item['dietary_flag_labels'])) ?></small>
                  <?php endif; ?>
                </td>
                <td><?= (int)$item['is_available'] === 1 ? 'Dostupná' : 'Nedostupná' ?></td>
                <td class="actions">
                  <a class="btn" href="food_items.php?card=<?= (int)$cardId ?>&amp;edit_item=<?= (int)$item['id'] ?>">Upravit</a>
                  <form method="post" class="admin-inline-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="card_id" value="<?= (int)$cardId ?>">
                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                    <button type="submit" class="btn btn-danger" data-confirm="Smazat položku?">Smazat</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php adminFooter(); ?>
