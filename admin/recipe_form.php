<?php

require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu receptů nemáte potřebné oprávnění.');
requireModuleEnabled('recipes');

$pdo = db_connect();
$id = inputInt('get', 'id');
$recipe = [
    'id' => null,
    'category_id' => null,
    'title' => '',
    'slug' => '',
    'summary' => '',
    'notes' => '',
    'servings' => '',
    'prep_minutes' => '',
    'cook_minutes' => '',
    'difficulty' => '',
    'dietary_flags' => [],
    'allergens' => [],
    'calories_kcal' => '',
    'media_id' => null,
    'image_alt_text' => '',
    'source_name' => '',
    'source_url' => '',
    'meta_title' => '',
    'meta_description' => '',
    'status' => 'draft',
    'publish_at' => '',
];

$contentLockWarning = null;
if ($id !== null) {
    $stmt = $pdo->prepare('SELECT * FROM cms_recipes WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!is_array($existing)) {
        header('Location: ' . BASE_URL . '/admin/recipes.php');
        exit;
    }
    $recipe = array_merge($recipe, $existing);
    $contentLockWarning = acquireContentLock('recipe', $id);
}

$flash = is_array($_SESSION['recipe_form_flash'] ?? null) ? $_SESSION['recipe_form_flash'] : [];
unset($_SESSION['recipe_form_flash']);
if (isset($flash['form']) && is_array($flash['form'])) {
    $recipe = array_merge($recipe, $flash['form']);
}
$errors = isset($flash['errors']) && is_array($flash['errors']) ? $flash['errors'] : [];
$fieldErrors = isset($flash['field_errors']) && is_array($flash['field_errors'])
    ? array_values(array_unique(array_map('strval', $flash['field_errors'])))
    : [];
$fieldErrorMap = [];
foreach ($fieldErrors as $fieldError) {
    $fieldErrorMap[$fieldError] = [$fieldError];
}
$fieldMessage = static function (string $fieldName) use ($errors): string {
    foreach ($errors as $error) {
        if (is_array($error) && (string)($error['field'] ?? '') === $fieldName) {
            return (string)($error['message'] ?? '');
        }
    }
    return '';
};

$selectedDietaryFlags = normalizeRecipeSelection(
    $recipe['dietary_flags'] ?? [],
    recipeDietaryFlagDefinitions()
);
$selectedAllergens = normalizeRecipeSelection(
    $recipe['allergens'] ?? [],
    recipeAllergenDefinitions()
);
$selectedCategoryId = (int)($recipe['category_id'] ?? 0);
$categories = $pdo->query(
    'SELECT id, name, is_active FROM cms_recipe_categories ORDER BY sort_order, name'
)->fetchAll();
$mediaRows = $pdo->query(
    "SELECT id, original_name, alt_text, mime_type
     FROM cms_media
     WHERE visibility = 'public' AND mime_type LIKE 'image/%' AND mime_type != 'image/svg+xml'
     ORDER BY created_at DESC, id DESC
     LIMIT 500"
)->fetchAll();
$structure = $id !== null ? recipeLoadStructure($pdo, $id) : [
    'ingredient_count' => 0,
    'step_count' => 0,
];
$canApprove = currentUserHasCapability('content_approve_shared');
$duplicatedMessage = trim((string)($_GET['msg'] ?? '')) === 'duplicated'
    ? 'Kopie receptu byla vytvořena jako koncept. Můžete upravit její základní údaje i obsah.'
    : '';

adminHeader($id !== null ? 'Upravit recept' : 'Nový recept');
?>
<p><a href="recipes.php"><span aria-hidden="true">←</span> Zpět na přehled receptů</a></p>

<?php if ($duplicatedMessage !== ''): ?><p class="success" role="status"><?= h($duplicatedMessage) ?></p><?php endif; ?>
<?php if ($contentLockWarning !== null): ?>
  <p class="warning" role="status">
    Tuto položku právě upravuje <?= h((string)$contentLockWarning['locked_by']) ?>.
    Změny ukládejte až po domluvě, aby se navzájem nepřepsaly.
  </p>
<?php endif; ?>

<?php if ($errors !== []): ?>
  <div class="error" role="alert" id="recipe-form-errors" aria-atomic="true">
    <p><strong>Recept se nepodařilo uložit.</strong></p>
    <ul>
      <?php foreach ($errors as $error): ?>
        <li><?= h(is_array($error) ? (string)($error['message'] ?? '') : (string)$error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($id !== null): ?>
  <section class="admin-card" aria-labelledby="recipe-content-summary-title">
    <h2 id="recipe-content-summary-title">Obsah receptu</h2>
    <p>
      <?= (int)$structure['ingredient_count'] ?> ingrediencí a
      <?= (int)$structure['step_count'] ?> kroků postupu.
      <a href="recipe_content.php?id=<?= $id ?>">Spravovat ingredience a postup</a>.
      <a href="revisions.php?type=recipe&amp;id=<?= $id ?>">Zobrazit historii změn</a>.
      <a href="recipe_history.php?id=<?= $id ?>">Obnovit starší strukturu</a>.
    </p>
  </section>
<?php else: ?>
  <p class="admin-description">
    Nejprve uložte základní údaje. Potom doplníte ingredience a postup a teprve hotový recept zveřejníte.
  </p>
<?php endif; ?>

<form method="post" action="recipe_save.php" novalidate<?= $errors !== [] ? ' aria-describedby="recipe-form-errors"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($id !== null): ?>
    <input type="hidden" name="id" value="<?= $id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Základní údaje receptu</legend>

    <label for="title">Název <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input type="text" id="title" name="title" maxlength="255" required aria-required="true"
           value="<?= h((string)$recipe['title']) ?>"<?= adminFieldAttributes('title', $fieldErrors, $fieldErrorMap) ?>>
    <?php adminRenderFieldError('title', $fieldErrors, $fieldErrorMap, $fieldMessage('title')); ?>

    <label for="slug">Slug veřejné stránky, volitelné</label>
    <input type="text" id="slug" name="slug" maxlength="150" pattern="[a-z0-9\-]+"
           value="<?= h((string)$recipe['slug']) ?>"
           <?= adminFieldAttributes('slug', $fieldErrors, $fieldErrorMap, ['recipe-slug-help']) ?>>
    <small id="recipe-slug-help" class="field-help">Prázdný slug se vytvoří z názvu. Použijte malá písmena, číslice a pomlčky.</small>
    <?php adminRenderFieldError('slug', $fieldErrors, $fieldErrorMap, $fieldMessage('slug')); ?>

    <label for="category_id">Kategorie <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <select id="category_id" name="category_id" required aria-required="true"
            <?= adminFieldAttributes('category_id', $fieldErrors, $fieldErrorMap, ['recipe-category-help']) ?>>
      <option value="">Vyberte kategorii</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int)$category['id'] ?>"<?= $selectedCategoryId === (int)$category['id'] ? ' selected' : '' ?>>
          <?= h((string)$category['name']) ?><?= (int)$category['is_active'] === 1 ? '' : ' (neaktivní)' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <small id="recipe-category-help" class="field-help">Kategorie určuje umístění ve veřejném katalogu a kuchařce EPUB.</small>
    <?php adminRenderFieldError('category_id', $fieldErrors, $fieldErrorMap, $fieldMessage('category_id')); ?>

    <label for="summary">Krátké shrnutí <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <textarea id="summary" name="summary" rows="4" maxlength="1000" required aria-required="true"
              <?= adminFieldAttributes('summary', $fieldErrors, $fieldErrorMap, ['recipe-summary-help']) ?>><?= h((string)$recipe['summary']) ?></textarea>
    <small id="recipe-summary-help" class="field-help">Jednou až třemi větami popište pokrm a to, čím je recept užitečný.</small>
    <?php adminRenderFieldError('summary', $fieldErrors, $fieldErrorMap, $fieldMessage('summary')); ?>

    <label for="notes">Poznámky a tipy</label>
    <textarea id="notes" name="notes" rows="8" aria-describedby="recipe-notes-help"><?= h((string)$recipe['notes']) ?></textarea>
    <small id="recipe-notes-help" class="field-help">Volitelný prostý text. Můžete přidat varianty, tip na skladování nebo upozornění na citlivý krok.</small>
  </fieldset>

  <fieldset>
    <legend>Porce a čas</legend>
    <p id="recipe-time-help" class="field-help">Vyplňujte pouze hodnoty, které znáte. CMS je nebude odhadovat ani dopočítávat.</p>
    <div class="form-grid">
      <div>
        <label for="servings">Počet porcí</label>
        <input type="number" id="servings" name="servings" min="1" max="10000"
               value="<?= h((string)$recipe['servings']) ?>"
               <?= adminFieldAttributes('servings', $fieldErrors, $fieldErrorMap, ['recipe-time-help']) ?>>
        <?php adminRenderFieldError('servings', $fieldErrors, $fieldErrorMap, $fieldMessage('servings')); ?>
      </div>
      <div>
        <label for="prep_minutes">Příprava v minutách</label>
        <input type="number" id="prep_minutes" name="prep_minutes" min="1" max="100000"
               value="<?= h((string)$recipe['prep_minutes']) ?>"
               <?= adminFieldAttributes('prep_minutes', $fieldErrors, $fieldErrorMap, ['recipe-time-help']) ?>>
        <?php adminRenderFieldError('prep_minutes', $fieldErrors, $fieldErrorMap, $fieldMessage('prep_minutes')); ?>
      </div>
      <div>
        <label for="cook_minutes">Tepelná úprava v minutách</label>
        <input type="number" id="cook_minutes" name="cook_minutes" min="1" max="100000"
               value="<?= h((string)$recipe['cook_minutes']) ?>"
               <?= adminFieldAttributes('cook_minutes', $fieldErrors, $fieldErrorMap, ['recipe-time-help']) ?>>
        <?php adminRenderFieldError('cook_minutes', $fieldErrors, $fieldErrorMap, $fieldMessage('cook_minutes')); ?>
      </div>
    </div>

    <label for="difficulty">Náročnost</label>
    <select id="difficulty" name="difficulty">
      <option value="">Neuvedeno</option>
      <?php foreach (recipeDifficultyDefinitions() as $difficultyKey => $difficultyLabel): ?>
        <option value="<?= h($difficultyKey) ?>"<?= (string)$recipe['difficulty'] === $difficultyKey ? ' selected' : '' ?>><?= h($difficultyLabel) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="calories_kcal">Energie na porci v kcal</label>
    <input type="number" id="calories_kcal" name="calories_kcal" min="1" max="100000"
           value="<?= h((string)$recipe['calories_kcal']) ?>"
           <?= adminFieldAttributes('calories_kcal', $fieldErrors, $fieldErrorMap, ['recipe-calories-help']) ?>>
    <small id="recipe-calories-help" class="field-help">Volitelné. Uveďte pouze ověřenou hodnotu na jednu porci.</small>
    <?php adminRenderFieldError('calories_kcal', $fieldErrors, $fieldErrorMap, $fieldMessage('calories_kcal')); ?>
  </fieldset>

  <fieldset>
    <legend>Dietní vlastnosti a alergeny</legend>
    <p id="recipe-diet-help" class="field-help">Označte jen vlastnosti a alergeny, které jste ověřili pro celý recept.</p>
    <fieldset class="admin-fieldset-nested">
      <legend>Dietní vlastnosti</legend>
      <?php foreach (recipeDietaryFlagDefinitions() as $flagKey => $flagLabel): ?>
        <label class="admin-checkbox-label">
          <input type="checkbox" name="dietary_flags[]" value="<?= h($flagKey) ?>"
                 <?= in_array($flagKey, $selectedDietaryFlags, true) ? 'checked' : '' ?> aria-describedby="recipe-diet-help">
          <?= h($flagLabel) ?>
        </label>
      <?php endforeach; ?>
    </fieldset>
    <fieldset class="admin-fieldset-nested">
      <legend>Obsahuje alergeny</legend>
      <?php foreach (recipeAllergenDefinitions() as $allergenKey => $allergenLabel): ?>
        <?php $allergenValue = (string)$allergenKey; ?>
        <label class="admin-checkbox-label">
          <input type="checkbox" name="allergens[]" value="<?= h($allergenValue) ?>"
                 <?= in_array($allergenValue, $selectedAllergens, true) ? 'checked' : '' ?> aria-describedby="recipe-diet-help">
          <?= h($allergenValue . '. ' . $allergenLabel) ?>
        </label>
      <?php endforeach; ?>
    </fieldset>
  </fieldset>

  <fieldset>
    <legend>Obrázek a zdroj</legend>
    <label for="media_id">Hlavní obrázek z knihovny médií</label>
    <select id="media_id" name="media_id" <?= adminFieldAttributes('media_id', $fieldErrors, $fieldErrorMap, ['recipe-media-help']) ?>>
      <option value="">Bez obrázku</option>
      <?php foreach ($mediaRows as $media): ?>
        <option value="<?= (int)$media['id'] ?>"<?= (int)($recipe['media_id'] ?? 0) === (int)$media['id'] ? ' selected' : '' ?>>
          <?= h((string)($media['original_name'] ?: ('Médium #' . $media['id']))) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <small id="recipe-media-help" class="field-help">Nabízí se pouze veřejné rastrové obrázky. Soukromý nebo smazaný obrázek se veřejně nezobrazí.</small>
    <?php adminRenderFieldError('media_id', $fieldErrors, $fieldErrorMap, $fieldMessage('media_id')); ?>

    <label for="image_alt_text">Alternativní text obrázku</label>
    <input type="text" id="image_alt_text" name="image_alt_text" maxlength="255"
           value="<?= h((string)$recipe['image_alt_text']) ?>" aria-describedby="recipe-alt-help">
    <small id="recipe-alt-help" class="field-help">Popište důležitý vizuální obsah. Prázdné pole použije alt text média a potom název receptu.</small>

    <label for="source_name">Název zdroje nebo autora předlohy</label>
    <input type="text" id="source_name" name="source_name" maxlength="255" value="<?= h((string)$recipe['source_name']) ?>">

    <label for="source_url">Odkaz na zdroj</label>
    <input type="url" id="source_url" name="source_url" maxlength="500"
           value="<?= h((string)$recipe['source_url']) ?>"
           <?= adminFieldAttributes('source_url', $fieldErrors, $fieldErrorMap, ['recipe-source-help']) ?>>
    <small id="recipe-source-help" class="field-help">Volitelná bezpečná adresa začínající http:// nebo https://.</small>
    <?php adminRenderFieldError('source_url', $fieldErrors, $fieldErrorMap, $fieldMessage('source_url')); ?>
  </fieldset>

  <fieldset>
    <legend>SEO a zveřejnění</legend>
    <label for="meta_title">Meta title</label>
    <input type="text" id="meta_title" name="meta_title" maxlength="160" value="<?= h((string)$recipe['meta_title']) ?>">

    <label for="meta_description">Meta description</label>
    <textarea id="meta_description" name="meta_description" rows="3" maxlength="320"><?= h((string)$recipe['meta_description']) ?></textarea>

    <?php if ($id === null): ?>
      <p>Nový recept se uloží jako koncept. Po doplnění ingrediencí a postupu jej můžete zveřejnit.</p>
      <input type="hidden" name="status" value="draft">
    <?php else: ?>
      <label for="status">Stav</label>
      <select id="status" name="status"
              <?= adminFieldAttributes('status', $fieldErrors, $fieldErrorMap, ['recipe-status-help']) ?>>
        <option value="draft"<?= (string)$recipe['status'] === 'draft' ? ' selected' : '' ?>>Koncept</option>
        <option value="pending"<?= (string)$recipe['status'] === 'pending' ? ' selected' : '' ?>>Čeká na schválení</option>
        <?php if ($canApprove): ?>
          <option value="published"<?= (string)$recipe['status'] === 'published' ? ' selected' : '' ?>>Publikováno</option>
        <?php endif; ?>
      </select>
      <small id="recipe-status-help" class="field-help">Publikovat lze pouze recept s aktivní kategorií, alespoň jednou ingrediencí a jedním krokem.</small>
      <?php adminRenderFieldError('status', $fieldErrors, $fieldErrorMap, $fieldMessage('status')); ?>

      <label for="publish_at">Datum a čas zveřejnění</label>
      <input type="datetime-local" id="publish_at" name="publish_at"
             value="<?= h(str_replace(' ', 'T', substr((string)$recipe['publish_at'], 0, 16))) ?>"
             <?= adminFieldAttributes('publish_at', $fieldErrors, $fieldErrorMap, ['recipe-publish-help']) ?>>
      <small id="recipe-publish-help" class="field-help">Prázdné pole zveřejní recept ihned po nastavení stavu Publikováno.</small>
      <?php adminRenderFieldError('publish_at', $fieldErrors, $fieldErrorMap, $fieldMessage('publish_at')); ?>
    <?php endif; ?>
  </fieldset>

  <div class="button-row">
    <button type="submit" class="btn"><?= $id !== null ? 'Uložit recept' : 'Uložit a doplnit obsah' ?></button>
    <a href="recipes.php">Zrušit</a>
    <?php if ($id !== null && recipeIsPubliclyVisible($recipe)): ?>
      <a href="<?= h(recipePublicPath($recipe)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
    <?php endif; ?>
  </div>
</form>

<script nonce="<?= cspNonce() ?>">
(function () {
    const title = document.getElementById('title');
    const slug = document.getElementById('slug');
    let manuallyEdited = Boolean(slug && slug.value.trim());
    const slugify = (value) => value.toLowerCase().normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    slug?.addEventListener('input', function () {
        manuallyEdited = this.value.trim() !== '';
    });
    title?.addEventListener('input', function () {
        if (!manuallyEdited && slug) {
            slug.value = slugify(this.value);
        }
    });
})();
</script>

<?php adminRenderContentLockRefreshScript('recipe', $id); ?>
<?php adminFooter(); ?>
