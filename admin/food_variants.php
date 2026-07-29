<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu variant položek nemáte potřebné oprávnění.');
requireModuleEnabled('food');

$pdo = db_connect();
$itemId = inputInt('get', 'item') ?? inputInt('post', 'item_id');
if ($itemId === null) {
    header('Location: ' . BASE_URL . '/admin/food.php');
    exit;
}

$itemStmt = $pdo->prepare(
    "SELECT fi.*, c.title AS card_title, c.slug AS card_slug
     FROM cms_food_items fi
     INNER JOIN cms_food_cards c ON c.id = fi.card_id
     WHERE fi.id = ? AND c.deleted_at IS NULL
     LIMIT 1"
);
$itemStmt->execute([$itemId]);
$item = $itemStmt->fetch() ?: null;
if (!$item) {
    header('Location: ' . BASE_URL . '/admin/food.php');
    exit;
}

$cardId = (int)$item['card_id'];
$editVariantId = inputInt('get', 'edit');
$message = trim((string)($_GET['msg'] ?? ''));
$error = '';
$fieldErrors = [];
$fieldErrorMessages = [];
$deleteErrorVariantId = null;
$variantState = [
    'id' => 0,
    'label' => '',
    'portion_label' => '',
    'price_amount' => '',
    'price_currency' => (string)($item['price_currency'] ?? 'CZK'),
    'price_note' => '',
    'is_available' => '1',
    'sort_order' => '0',
];

$redirectToVariants = static function (string $messageKey = '') use ($itemId): void {
    $query = ['item' => $itemId];
    if ($messageKey !== '') {
        $query['msg'] = $messageKey;
    }
    header('Location: ' . BASE_URL . '/admin/food_variants.php?' . http_build_query($query));
    exit;
};

$loadVariant = static function (?int $variantId) use ($pdo, $itemId, $cardId): ?array {
    if ($variantId === null) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT *
         FROM cms_food_item_variants
         WHERE id = ? AND item_id = ? AND card_id = ?
         LIMIT 1"
    );
    $stmt->execute([$variantId, $itemId, $cardId]);
    $variant = $stmt->fetch() ?: null;

    return is_array($variant) ? $variant : null;
};

$normalizeVariantOrders = static function () use ($pdo, $itemId, $cardId): void {
    $stmt = $pdo->prepare(
        "SELECT id
         FROM cms_food_item_variants
         WHERE item_id = ? AND card_id = ?
         ORDER BY sort_order, id"
    );
    $stmt->execute([$itemId, $cardId]);
    $update = $pdo->prepare(
        "UPDATE cms_food_item_variants
         SET sort_order = ?
         WHERE id = ? AND item_id = ? AND card_id = ?"
    );
    $sortOrder = 10;
    foreach ($stmt->fetchAll() as $row) {
        $update->execute([$sortOrder, (int)$row['id'], $itemId, $cardId]);
        $sortOrder += 10;
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'save') {
        $variantId = inputInt('post', 'variant_id');
        $priceAmount = normalizeFoodPriceInput((string)($_POST['price_amount'] ?? ''));
        $variantState = [
            'id' => $variantId ?? 0,
            'label' => mb_substr(trim((string)($_POST['label'] ?? '')), 0, 120),
            'portion_label' => mb_substr(trim((string)($_POST['portion_label'] ?? '')), 0, 80),
            'price_amount' => $priceAmount !== false && $priceAmount !== null
                ? $priceAmount
                : trim((string)($_POST['price_amount'] ?? '')),
            'price_currency' => normalizeFoodCurrency((string)($_POST['price_currency'] ?? 'CZK')),
            'price_note' => mb_substr(trim((string)($_POST['price_note'] ?? '')), 0, 255),
            'is_available' => isset($_POST['is_available']) ? '1' : '0',
            'sort_order' => (string)max(0, (int)($_POST['sort_order'] ?? 0)),
        ];
        $editVariantId = $variantId;

        if ($variantState['label'] === '') {
            $error = 'Variantu nejde uložit bez názvu. U pole Název varianty je konkrétní nápověda.';
            $fieldErrors[] = 'variant_label';
            $fieldErrorMessages['variant_label'] = 'Doplňte rozlišující název, například Malá, Velká nebo 0,5 l.';
        } elseif ($priceAmount === false) {
            $error = 'Cena varianty není použitelná. U pole Cena varianty je konkrétní nápověda.';
            $fieldErrors[] = 'variant_price_amount';
            $fieldErrorMessages['variant_price_amount'] = 'Zadejte částku jako 59 nebo 59,90, nejvýše se dvěma desetinnými místy, případně pole nechte prázdné.';
        } elseif ($variantId !== null && !$loadVariant($variantId)) {
            $error = 'Upravovanou variantu se u této položky nepodařilo najít.';
        } else {
            $duplicateStmt = $pdo->prepare(
                "SELECT id
                 FROM cms_food_item_variants
                 WHERE item_id = ? AND label = ? AND id <> ?
                 LIMIT 1"
            );
            $duplicateStmt->execute([$itemId, $variantState['label'], $variantId ?? 0]);
            if ($duplicateStmt->fetch()) {
                $error = 'Variantu nejde uložit, protože stejný název už tato položka používá.';
                $fieldErrors[] = 'variant_label';
                $fieldErrorMessages['variant_label'] = 'Použijte u této položky jedinečný název varianty.';
            } elseif ($variantId !== null) {
                $pdo->prepare(
                    "UPDATE cms_food_item_variants
                     SET label = ?, portion_label = ?, price_amount = ?, price_currency = ?, price_note = ?,
                         is_available = ?, sort_order = ?, updated_at = NOW()
                     WHERE id = ? AND item_id = ? AND card_id = ?"
                )->execute([
                    $variantState['label'],
                    $variantState['portion_label'],
                    $priceAmount,
                    $variantState['price_currency'],
                    $variantState['price_note'],
                    (int)$variantState['is_available'],
                    (int)$variantState['sort_order'],
                    $variantId,
                    $itemId,
                    $cardId,
                ]);
                logAction('food_variant_edit', "card={$cardId} item={$itemId} variant={$variantId}");
                $redirectToVariants('saved');
            } else {
                $sortOrder = (int)$variantState['sort_order'];
                if ($sortOrder <= 0) {
                    $maxStmt = $pdo->prepare(
                        "SELECT COALESCE(MAX(sort_order), 0) + 10
                         FROM cms_food_item_variants
                         WHERE item_id = ? AND card_id = ?"
                    );
                    $maxStmt->execute([$itemId, $cardId]);
                    $sortOrder = (int)$maxStmt->fetchColumn();
                }
                $pdo->prepare(
                    "INSERT INTO cms_food_item_variants
                     (card_id, item_id, label, portion_label, price_amount, price_currency, price_note, is_available, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $cardId,
                    $itemId,
                    $variantState['label'],
                    $variantState['portion_label'],
                    $priceAmount,
                    $variantState['price_currency'],
                    $variantState['price_note'],
                    (int)$variantState['is_available'],
                    $sortOrder,
                ]);
                logAction('food_variant_add', "card={$cardId} item={$itemId}");
                $redirectToVariants('saved');
            }
        }
    }

    if ($action === 'move') {
        $variantId = inputInt('post', 'variant_id');
        $direction = trim((string)($_POST['direction'] ?? ''));
        $variant = $loadVariant($variantId);
        if ($variant && in_array($direction, ['up', 'down'], true)) {
            $normalizeVariantOrders();
            $rowsStmt = $pdo->prepare(
                "SELECT id, sort_order
                 FROM cms_food_item_variants
                 WHERE item_id = ? AND card_id = ?
                 ORDER BY sort_order, id"
            );
            $rowsStmt->execute([$itemId, $cardId]);
            $rows = $rowsStmt->fetchAll();
            $currentIndex = null;
            foreach ($rows as $index => $row) {
                if ((int)$row['id'] === (int)$variantId) {
                    $currentIndex = $index;
                    break;
                }
            }
            $targetIndex = $currentIndex === null
                ? null
                : ($direction === 'up' ? $currentIndex - 1 : $currentIndex + 1);
            if ($targetIndex !== null && isset($rows[$targetIndex], $rows[$currentIndex])) {
                $current = $rows[$currentIndex];
                $target = $rows[$targetIndex];
                $update = $pdo->prepare(
                    "UPDATE cms_food_item_variants
                     SET sort_order = ?
                     WHERE id = ? AND item_id = ? AND card_id = ?"
                );
                $update->execute([(int)$target['sort_order'], (int)$current['id'], $itemId, $cardId]);
                $update->execute([(int)$current['sort_order'], (int)$target['id'], $itemId, $cardId]);
                logAction('food_variant_move', "card={$cardId} item={$itemId} variant={$variantId} direction={$direction}");
            }
        }
        $redirectToVariants('moved');
    }

    if ($action === 'delete') {
        $variantId = inputInt('post', 'variant_id');
        $variant = $loadVariant($variantId);
        if ($variant) {
            $confirmationField = 'confirm_food_variant_delete_' . (int)$variantId;
            if (!isset($_POST[$confirmationField]) || (string)$_POST[$confirmationField] !== '1') {
                $error = 'Variantu nejde smazat bez potvrzení kontroly dopadu.';
                $deleteErrorVariantId = (int)$variantId;
            } else {
                $pdo->prepare(
                    "DELETE FROM cms_food_item_variants
                     WHERE id = ? AND item_id = ? AND card_id = ?"
                )->execute([$variantId, $itemId, $cardId]);
                logAction('food_variant_delete', "card={$cardId} item={$itemId} variant={$variantId}");
                $redirectToVariants('deleted');
            }
        }
    }
}

if ($editVariantId !== null && $error === '') {
    $editVariant = $loadVariant($editVariantId);
    if ($editVariant) {
        $variantState = [
            'id' => (int)$editVariant['id'],
            'label' => (string)$editVariant['label'],
            'portion_label' => (string)($editVariant['portion_label'] ?? ''),
            'price_amount' => $editVariant['price_amount'] !== null ? (string)$editVariant['price_amount'] : '',
            'price_currency' => (string)$editVariant['price_currency'],
            'price_note' => (string)($editVariant['price_note'] ?? ''),
            'is_available' => (string)(int)$editVariant['is_available'],
            'sort_order' => (string)(int)$editVariant['sort_order'],
        ];
    }
}

$variantsStmt = $pdo->prepare(
    "SELECT *
     FROM cms_food_item_variants
     WHERE item_id = ? AND card_id = ?
     ORDER BY sort_order, id"
);
$variantsStmt->execute([$itemId, $cardId]);
$variants = array_map(
    static fn (array $variant): array => hydrateFoodItemVariant($variant),
    $variantsStmt->fetchAll()
);
$fieldErrorFor = static function (string $field) use ($fieldErrorMessages): string {
    return (string)($fieldErrorMessages[$field] ?? '');
};

adminHeader('Varianty položky: ' . (string)$item['title']);
?>

<?php if ($message === 'saved'): ?><p class="success" role="status">Varianta byla uložena.</p><?php endif; ?>
<?php if ($message === 'moved'): ?><p class="success" role="status">Pořadí variant bylo změněno.</p><?php endif; ?>
<?php if ($message === 'deleted'): ?><p class="success" role="status">Varianta byla smazána.</p><?php endif; ?>
<?php if ($error !== ''): ?><p id="food-variant-error" class="error" role="alert" aria-atomic="true"><?= h($error) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <a href="food_items.php?card=<?= $cardId ?>"><span aria-hidden="true">&larr;</span> Zpět na položky lístku</a>
  <a href="<?= h(foodCardPublicPath(['slug' => (string)$item['card_slug']])) ?>" target="_blank" rel="noopener noreferrer">Zobrazit lístek<?= newWindowLinkSrOnlySuffix() ?></a>
</p>

<p class="admin-description admin-description--flush">
  Varianty použijte pro různé velikosti nebo porce stejné položky, například malou a velkou pizzu nebo nápoj 0,3 a 0,5 l.
  Pokud položka varianty má, veřejný lístek a objednávka použijí jejich ceny místo základní ceny položky.
</p>

<section class="form-card" aria-labelledby="food-variant-form-title">
  <h2 id="food-variant-form-title"><?= (int)$variantState['id'] > 0 ? 'Upravit variantu' : 'Přidat variantu' ?></h2>
  <form method="post" novalidate<?= $error !== '' && $deleteErrorVariantId === null ? ' aria-describedby="food-variant-error"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="item_id" value="<?= $itemId ?>">
    <input type="hidden" name="variant_id" value="<?= (int)$variantState['id'] ?>">
    <fieldset>
      <legend>Údaje varianty</legend>

      <label for="variant-label">Název varianty <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
      <input type="text" id="variant-label" name="label" required aria-required="true" maxlength="120"
             value="<?= h((string)$variantState['label']) ?>"
             <?= adminFieldAttributes('variant_label', $fieldErrors, [], ['variant-label-help']) ?>>
      <small id="variant-label-help" class="field-help">Například Malá, Velká, 250 g nebo 0,5 l. Název musí být u této položky jedinečný.</small>
      <?php adminRenderFieldError('variant_label', $fieldErrors, [], $fieldErrorFor('variant_label')); ?>

      <label for="variant-portion">Porce nebo objem</label>
      <input type="text" id="variant-portion" name="portion_label" maxlength="80"
             value="<?= h((string)$variantState['portion_label']) ?>"
             aria-describedby="variant-portion-help">
      <small id="variant-portion-help" class="field-help">Volitelné upřesnění, pokud není už součástí názvu varianty.</small>

      <div class="admin-price-row">
        <div>
          <label for="variant-price">Cena</label>
          <input type="text" id="variant-price" name="price_amount" inputmode="decimal" maxlength="20"
                 value="<?= h((string)$variantState['price_amount']) ?>"
                 <?= adminFieldAttributes('variant_price_amount', $fieldErrors, [], ['variant-price-help']) ?>>
          <small id="variant-price-help" class="field-help">Částka bez měny, například 159,90.</small>
          <?php adminRenderFieldError('variant_price_amount', $fieldErrors, [], $fieldErrorFor('variant_price_amount')); ?>
        </div>
        <div>
          <label for="variant-currency">Měna</label>
          <input type="text" id="variant-currency" name="price_currency" maxlength="3" pattern="[A-Za-z]{3}"
                 value="<?= h((string)$variantState['price_currency']) ?>" class="admin-input-short"
                 aria-describedby="variant-currency-help">
          <small id="variant-currency-help" class="field-help">Třípísmenný kód, například CZK nebo EUR.</small>
        </div>
      </div>

      <label for="variant-price-note">Poznámka k ceně</label>
      <input type="text" id="variant-price-note" name="price_note" maxlength="255"
             value="<?= h((string)$variantState['price_note']) ?>">

      <label for="variant-sort-order">Pořadí</label>
      <input type="number" id="variant-sort-order" name="sort_order" min="0" step="1"
             value="<?= h((string)$variantState['sort_order']) ?>" class="admin-input-short"
             aria-describedby="variant-sort-help">
      <small id="variant-sort-help" class="field-help">Nula zařadí novou variantu automaticky na konec.</small>

      <label class="admin-checkbox-label">
        <input type="checkbox" name="is_available" value="1"<?= (int)$variantState['is_available'] === 1 ? ' checked' : '' ?>>
        Varianta je dostupná
      </label>

      <div class="button-row button-row--start admin-action-row">
        <button type="submit" class="btn"><?= (int)$variantState['id'] > 0 ? 'Uložit variantu' : 'Přidat variantu' ?></button>
        <?php if ((int)$variantState['id'] > 0): ?><a class="btn" href="food_variants.php?item=<?= $itemId ?>">Zrušit úpravu</a><?php endif; ?>
      </div>
    </fieldset>
  </form>
</section>

<section class="form-card" aria-labelledby="food-variant-list-title">
  <h2 id="food-variant-list-title">Uložené varianty</h2>
  <?php if ($variants === []): ?>
    <p>Zatím nejsou vytvořené žádné varianty. Položka proto používá svou základní cenu a porci.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <caption>Varianty položky <?= h((string)$item['title']) ?></caption>
        <thead>
          <tr>
            <th scope="col">Varianta</th>
            <th scope="col">Cena</th>
            <th scope="col">Dostupnost</th>
            <th scope="col">Pořadí</th>
            <th scope="col">Akce</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($variants as $index => $variant): ?>
            <?php
            $variantId = (int)$variant['id'];
              $deleteConfirmationField = 'confirm_food_variant_delete_' . $variantId;
              $deleteReviewId = 'food-variant-delete-review-' . $variantId;
              $deleteErrorId = 'confirm-food-variant-delete-' . $variantId . '-error';
              $hasDeleteError = $deleteErrorVariantId === $variantId;
              ?>
            <tr>
              <td>
                <strong><?= h((string)$variant['label']) ?></strong>
                <?php if ((string)$variant['portion_label'] !== ''): ?><br><small><?= h((string)$variant['portion_label']) ?></small><?php endif; ?>
              </td>
              <td><?= h((string)$variant['price_label']) ?></td>
              <td><?= (int)$variant['is_available'] === 1 ? 'Dostupná' : 'Nedostupná' ?></td>
              <td><?= (int)$variant['sort_order'] ?></td>
              <td class="actions">
                <a class="btn" href="food_variants.php?item=<?= $itemId ?>&amp;edit=<?= $variantId ?>">Upravit</a>
                <form method="post" class="inline-form">
                  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                  <input type="hidden" name="action" value="move">
                  <input type="hidden" name="item_id" value="<?= $itemId ?>">
                  <input type="hidden" name="variant_id" value="<?= $variantId ?>">
                  <input type="hidden" name="direction" value="up">
                  <button type="submit" class="btn"<?= $index === 0 ? ' disabled' : '' ?>>Nahoru</button>
                </form>
                <form method="post" class="inline-form">
                  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                  <input type="hidden" name="action" value="move">
                  <input type="hidden" name="item_id" value="<?= $itemId ?>">
                  <input type="hidden" name="variant_id" value="<?= $variantId ?>">
                  <input type="hidden" name="direction" value="down">
                  <button type="submit" class="btn"<?= $index === count($variants) - 1 ? ' disabled' : '' ?>>Dolů</button>
                </form>
                <form method="post" class="admin-inline-confirm-form"<?= $hasDeleteError ? ' aria-describedby="food-variant-error"' : '' ?>>
                  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="item_id" value="<?= $itemId ?>">
                  <input type="hidden" name="variant_id" value="<?= $variantId ?>">
                  <fieldset>
                    <legend class="sr-only">Smazat variantu <?= h((string)$variant['label']) ?></legend>
                    <p id="<?= h($deleteReviewId) ?>" class="field-help">Smazání odstraní variantu z veřejného lístku a budoucích poptávek. Historické poptávky si zachovají uložený název a cenu.</p>
                    <label class="admin-checkbox-label">
                      <input type="checkbox" name="<?= h($deleteConfirmationField) ?>" value="1" required aria-required="true"
                             <?= $hasDeleteError ? 'aria-invalid="true" aria-describedby="' . h($deleteReviewId . ' ' . $deleteErrorId) . '"' : 'aria-describedby="' . h($deleteReviewId) . '"' ?>>
                      Potvrzuji kontrolu dopadu smazání
                    </label>
                    <?php if ($hasDeleteError): ?><small id="<?= h($deleteErrorId) ?>" class="field-help field-error">Před smazáním varianty potvrďte, že jste zkontrolovali její použití.</small><?php endif; ?>
                    <button type="submit" class="btn btn-danger">Smazat variantu</button>
                  </fieldset>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php adminFooter(); ?>
