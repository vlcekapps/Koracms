<?php
require_once __DIR__ . '/layout.php';
requireSuperAdmin();

/**
 * @return array{
 *   availableThemeKeys: list<string>,
 *   themeManifests: array<string, array<string, mixed>>,
 *   configuredTheme: string,
 *   effectiveTheme: string,
 *   usingFallback: bool
 * }
 */
function themeAdminCatalogState(): array
{
    $availableThemeKeys = availableThemes();

    $themeManifests = [];
    foreach ($availableThemeKeys as $themeKey) {
        $themeManifests[$themeKey] = themeManifest($themeKey);
    }

    $configuredTheme = trim(getSetting('active_theme', defaultThemeName()));
    if ($configuredTheme === '') {
        $configuredTheme = defaultThemeName();
    }

    $effectiveTheme = resolveThemeName($configuredTheme);

    return [
        'availableThemeKeys' => $availableThemeKeys,
        'themeManifests' => $themeManifests,
        'configuredTheme' => $configuredTheme,
        'effectiveTheme' => $effectiveTheme,
        'usingFallback' => $configuredTheme !== $effectiveTheme,
    ];
}

$successMessage = '';
$errors = [];
$themeFieldErrors = [];
$themeFieldErrorMessages = [];
$previewRedirectDefault = BASE_URL . '/index.php';

[
    'availableThemeKeys' => $availableThemeKeys,
    'themeManifests' => $themeManifests,
    'configuredTheme' => $configuredTheme,
    'effectiveTheme' => $effectiveTheme,
    'usingFallback' => $usingFallback,
] = themeAdminCatalogState();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $formAction = trim((string)($_POST['form_action'] ?? ''));
    $previewRedirect = internalRedirectTarget((string)($_POST['preview_redirect'] ?? ''), $previewRedirectDefault);
    $previewData = themePreviewData();
    $previewThemeKey = (string)($previewData['theme'] ?? '');
    $previewIsActive = $previewThemeKey !== '';

    if ($formAction === 'activate_theme' || $formAction === 'preview_theme') {
        $selectedTheme = trim((string)($_POST['active_theme'] ?? ''));
        if (!in_array($selectedTheme, $availableThemeKeys, true)) {
            $errors[] = 'Vybranou šablonu nejde použít. U výběru aktivní šablony je konkrétní nápověda.';
            $themeFieldErrors[] = 'active_theme';
            $themeFieldErrorMessages['active_theme'] = 'Vyberte některou z dostupných šablon v katalogu.';
        }

        if ($errors === []) {
            if ($formAction === 'activate_theme') {
                $profileDetached = false;
                saveSetting('active_theme', $selectedTheme);
                clearThemePreview();
                $currentProfile = currentSiteProfileKey();
                if (siteProfileShouldDetachForTheme($currentProfile, $selectedTheme)) {
                    saveSetting('site_profile', 'custom');
                    $profileDetached = true;
                }
                [
                    'availableThemeKeys' => $availableThemeKeys,
                    'themeManifests' => $themeManifests,
                    'configuredTheme' => $configuredTheme,
                    'effectiveTheme' => $effectiveTheme,
                    'usingFallback' => $usingFallback,
                ] = themeAdminCatalogState();
                logAction('theme_activate', 'theme=' . $selectedTheme);
                $successMessage = 'Aktivní šablona byla uložena.';
                if ($profileDetached) {
                    $successMessage .= ' Profil webu byl přepnut na Vlastní profil, aby se ruční volba šablony nepřepisovala doporučeným presetem.';
                }
            } else {
                setThemePreview($selectedTheme, themeSettingsValues($selectedTheme));
                logAction('theme_preview_start', 'theme=' . $selectedTheme . ';source=theme');
                header('Location: ' . $previewRedirect);
                exit;
            }
        }
    } elseif (in_array($formAction, ['save_theme_settings', 'reset_theme_settings', 'preview_theme_settings'], true)) {
        $postedThemeKey = trim((string)($_POST['theme_key'] ?? ''));
        if (!in_array($postedThemeKey, $availableThemeKeys, true)) {
            $errors[] = 'Vzhled vybrané šablony nejde uložit, protože šablona už není dostupná. Obnovte stránku a vyberte dostupnou šablonu.';
            $themeFieldErrors[] = 'theme_key';
            $themeFieldErrorMessages['theme_key'] = 'Obnovte stránku a upravte některou z dostupných šablon.';
        } else {
            if ($formAction === 'reset_theme_settings') {
                resetThemeSettings($postedThemeKey);
                if ($previewIsActive && $previewThemeKey === $postedThemeKey) {
                    setThemePreview($postedThemeKey, themeDefaultSettings($postedThemeKey));
                }
                logAction('theme_settings_reset', 'theme=' . $postedThemeKey);
                $successMessage = 'Vzhled vybrané šablony byl vrácen na výchozí hodnoty.';
            } else {
                $validation = themeSettingsValidation(
                    is_array($_POST['theme_settings'] ?? null) ? $_POST['theme_settings'] : [],
                    $postedThemeKey
                );

                if (!empty($validation['errors'])) {
                    $definitions = themeSettingDefinitions($postedThemeKey);
                    foreach ($validation['errors'] as $settingKey => $errorMessage) {
                        $label = $definitions[$settingKey]['label'] ?? $settingKey;
                        $themeSettingField = 'theme_setting_' . $settingKey;
                        $errors[] = $label . ' nejde uložit. U příslušného nastavení vzhledu je konkrétní nápověda.';
                        $themeFieldErrors[] = $themeSettingField;
                        $themeFieldErrorMessages[$themeSettingField] = (string)$errorMessage . ' Upravte hodnotu podle nápovědy u pole nebo obnovte výchozí vzhled.';
                    }
                } elseif ($formAction === 'save_theme_settings') {
                    saveThemeSettings($validation['values'], $postedThemeKey);
                    if ($previewIsActive && $previewThemeKey === $postedThemeKey) {
                        setThemePreview($postedThemeKey, $validation['values']);
                    }
                    logAction('theme_settings_save', 'theme=' . $postedThemeKey);
                    $successMessage = 'Vzhled vybrané šablony byl uložen.';
                } else {
                    setThemePreview($postedThemeKey, $validation['values']);
                    logAction('theme_preview_start', 'theme=' . $postedThemeKey . ';source=settings');
                    header('Location: ' . $previewRedirect);
                    exit;
                }
            }
        }
    } elseif ($formAction === 'import_theme_package') {
        $importResult = themeImportPortablePackageUpload($_FILES['theme_package'] ?? null);
        if (!$importResult['ok']) {
            $errors[] = 'ZIP balíček šablony nejde importovat. U pole Soubor ZIP je konkrétní nápověda.';
            $themeFieldErrors[] = 'theme_package';
            $themeFieldErrorMessages['theme_package'] = implode(' ', array_map('strval', (array)$importResult['errors']))
                . ' Vyberte portable ZIP balíček s jedním kořenovým adresářem, manifestem theme.json a assets/public.css.';
        } else {
            [
                'availableThemeKeys' => $availableThemeKeys,
                'themeManifests' => $themeManifests,
                'configuredTheme' => $configuredTheme,
                'effectiveTheme' => $effectiveTheme,
                'usingFallback' => $usingFallback,
            ] = themeAdminCatalogState();
            $importThemeKey = (string)($importResult['theme_key'] ?? '');
            logAction('theme_import', 'theme=' . $importThemeKey);
            $successMessage = 'ZIP balíček byl bezpečně importován jako šablona `' . $importThemeKey . '`.';
        }
    } elseif ($formAction === 'export_theme_package') {
        $exportThemeKey = trim((string)($_POST['export_theme'] ?? ''));
        if (!in_array($exportThemeKey, $availableThemeKeys, true)) {
            $errors[] = 'Vybranou šablonu nejde exportovat. U pole Šablona k exportu je konkrétní nápověda.';
            $themeFieldErrors[] = 'export_theme';
            $themeFieldErrorMessages['export_theme'] = 'Vyberte některou z dostupných šablon v seznamu a export spusťte znovu.';
        } else {
            $exportResult = themeBuildPortablePackage($exportThemeKey);
            if (!$exportResult['ok']) {
                $errors[] = 'ZIP balíček šablony se nepodařilo vytvořit. U pole Šablona k exportu je konkrétní nápověda.';
                $themeFieldErrors[] = 'export_theme';
                $themeFieldErrorMessages['export_theme'] = implode(' ', array_map('strval', (array)$exportResult['errors']))
                    . ' Zkuste vybrat jinou šablonu nebo zkontrolovat soubory šablony.';
            } else {
                logAction('theme_export', 'theme=' . $exportThemeKey);
                $exportPath = (string)$exportResult['path'];
                $exportSize = filesize($exportPath);
                sendAdminAttachmentHeaders(
                    'application/zip',
                    basename((string)$exportResult['filename']),
                    is_int($exportSize) ? $exportSize : null
                );
                readfile($exportPath);
                @unlink($exportPath);
                exit;
            }
        }
    } elseif ($formAction === 'clear_preview') {
        clearThemePreview();
        logAction('theme_preview_stop');
        $successMessage = 'Živý náhled byl ukončen.';
    } else {
        $errors[] = 'Akce formuláře není platná. Vraťte se na stránku šablon a použijte jedno z dostupných tlačítek.';
    }
}

[
    'availableThemeKeys' => $availableThemeKeys,
    'themeManifests' => $themeManifests,
    'configuredTheme' => $configuredTheme,
    'effectiveTheme' => $effectiveTheme,
    'usingFallback' => $usingFallback,
] = themeAdminCatalogState();
$previewData = themePreviewData();
$previewThemeKey = (string)($previewData['theme'] ?? '');
$previewIsActive = $previewThemeKey !== '';
$selectedTheme = $previewIsActive ? $previewThemeKey : ($usingFallback ? $effectiveTheme : $configuredTheme);
$editableTheme = $previewIsActive ? $previewThemeKey : $effectiveTheme;
$editableManifest = $themeManifests[$editableTheme] ?? themeManifest($editableTheme);
$themeSettingDefinitions = themeSettingDefinitions($editableTheme);
$themeSettingValues = $previewIsActive && !empty($previewData['settings'])
    ? $previewData['settings']
    : themeSettingsValues($editableTheme);
$exportThemeDefault = in_array($selectedTheme, $availableThemeKeys, true)
    ? $selectedTheme
    : ($availableThemeKeys[0] ?? defaultThemeName());
$themeFieldErrors = array_values(array_unique($themeFieldErrors));
$activeThemeHasError = adminFieldHasError('active_theme', $themeFieldErrors);
$themeSettingsHasError = adminFieldHasError('theme_key', $themeFieldErrors);
foreach ($themeFieldErrors as $themeFieldErrorName) {
    if (str_starts_with($themeFieldErrorName, 'theme_setting_')) {
        $themeSettingsHasError = true;
        break;
    }
}
$themePackageHasError = adminFieldHasError('theme_package', $themeFieldErrors);
$themeExportHasError = adminFieldHasError('export_theme', $themeFieldErrors);

adminHeader('Vzhled a šablony');
?>

<?php if ($successMessage !== ''): ?>
  <p class="success" role="status"><?= h($successMessage) ?></p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <ul class="error" role="alert" id="themes-form-errors" aria-atomic="true">
    <?php foreach ($errors as $errorMessage): ?>
      <li><?= h($errorMessage) ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if ($usingFallback): ?>
  <p class="error" role="alert">
    Uložená šablona <code><?= h($configuredTheme) ?></code> není na serveru dostupná.
    Web proto právě používá bezpečný fallback <code><?= h($effectiveTheme) ?></code>.
    Uložte platnou volbu, aby se konfigurace srovnala.
  </p>
<?php endif; ?>

<?php if ($previewIsActive): ?>
  <p class="success" role="status">
    Živý náhled je aktivní pro šablonu <code><?= h($previewThemeKey) ?></code>.
    Produkční web stále používá <code><?= h($effectiveTheme) ?></code>, dokud změnu výslovně neaktivujete.
  </p>
<?php endif; ?>

<p>
  Aktivní veřejná šablona určuje společný layout, assety a fallback view pro návštěvníky.
  Kromě vestavěných šablon teď můžete bezpečně importovat i portable ZIP balíček se statickými assety,
  bez PHP override souborů a bez zásahu do produkční konfigurace.
</p>

<form method="post" novalidate<?= $activeThemeHasError ? ' aria-describedby="themes-form-errors"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="preview_redirect" value="<?= h($previewRedirectDefault) ?>">

  <fieldset aria-describedby="theme-selection-help<?= $activeThemeHasError ? ' active-theme-error' : '' ?>">
    <legend>Aktivní šablona</legend>
    <p id="theme-selection-help">
      Pokud je v nastavení uložená chybějící nebo neplatná šablona, systém automaticky přejde
      na výchozí <code><?= h(defaultThemeName()) ?></code>.
    </p>

    <div class="theme-catalog">
      <?php foreach ($availableThemeKeys as $themeKey): ?>
        <?php
          $manifest = $themeManifests[$themeKey];
          $themeId = 'theme-' . preg_replace('/[^a-z0-9_-]+/i', '-', $themeKey);
          $metaId = $themeId . '-meta';
          $isDefaultTheme = $themeKey === defaultThemeName();
          $isEffectiveTheme = $themeKey === $effectiveTheme;
          $isPreviewTheme = $previewIsActive && $themeKey === $previewThemeKey;
          $isConfiguredTheme = !$usingFallback && $themeKey === $configuredTheme;
          $previewAssetUrl = themePreviewAssetUrl($themeKey);
          $cardClasses = ['theme-card'];
          if ($selectedTheme === $themeKey) {
              $cardClasses[] = 'theme-card--selected';
          }
          ?>
        <section class="<?= h(implode(' ', $cardClasses)) ?>">
          <div class="theme-card__heading">
            <input
              type="radio"
              id="<?= h($themeId) ?>"
              name="active_theme"
              value="<?= h($themeKey) ?>"
              <?= $selectedTheme === $themeKey ? 'checked' : '' ?>
              <?= adminFieldAttributes('active_theme', $themeFieldErrors, [], [$metaId], 'active-theme-error') ?>>
            <label for="<?= h($themeId) ?>" class="theme-card__title">
              <?= h($manifest['name']) ?>
            </label>
          </div>

          <p id="<?= h($metaId) ?>" class="theme-card__meta">
            Klíč: <code><?= h($manifest['key']) ?></code>
            · Verze: <?= h($manifest['version']) ?>
            · Autor: <?= h($manifest['author']) ?>
            <?= $isDefaultTheme ? '· Výchozí fallback' : '' ?>
          </p>

          <div class="theme-card__preview" aria-hidden="true">
            <?php if ($previewAssetUrl !== ''): ?>
              <img src="<?= h($previewAssetUrl) ?>" alt="" class="theme-card__preview-image">
            <?php else: ?>
              <div class="theme-card__placeholder">Náhled není k dispozici</div>
            <?php endif; ?>
          </div>

          <?php if (!empty($manifest['preview']['colors'])): ?>
            <div class="theme-card__swatches" aria-hidden="true">
              <?php foreach ($manifest['preview']['colors'] as $previewColor): ?>
                <svg class="theme-card__swatch" viewBox="0 0 16 16" focusable="false">
                  <circle cx="8" cy="8" r="7" fill="<?= h((string)$previewColor) ?>"></circle>
                </svg>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($manifest['preview']['summary'])): ?>
            <p class="theme-card__summary"><?= h($manifest['preview']['summary']) ?></p>
          <?php endif; ?>

          <?php if ($manifest['description'] !== ''): ?>
            <p class="theme-card__description"><?= h($manifest['description']) ?></p>
          <?php endif; ?>

          <p class="theme-card__status">
            <?php if ($isEffectiveTheme): ?>
              <strong>Právě se používá na veřejném webu.</strong>
            <?php endif; ?>
            <?php if ($isPreviewTheme): ?>
              <strong>Je spuštěná v živém náhledu pro váš účet.</strong>
            <?php endif; ?>
            <?php if ($isConfiguredTheme): ?>
              Tato volba je zároveň uložená v nastavení.
            <?php elseif ($usingFallback && $isDefaultTheme): ?>
              Tato šablona se používá jen jako bezpečný fallback, dokud neuložíte platnou volbu.
            <?php endif; ?>
          </p>
        </section>
      <?php endforeach; ?>
    </div>
    <p class="theme-card__hint">
      Vyberte kartu šablony a potom použijte tlačítka níže pro aktivaci nebo živý náhled.
    </p>
    <?php adminRenderFieldError('active_theme', $themeFieldErrors, [], $themeFieldErrorMessages['active_theme'] ?? '', 'active-theme-error'); ?>
  </fieldset>

  <fieldset class="admin-fieldset-spaced">
    <legend>Stav konfigurace</legend>
    <p><strong>Uložená hodnota:</strong> <code><?= h($configuredTheme) ?></code></p>
    <p><strong>Runtime používá:</strong> <code><?= h($effectiveTheme) ?></code></p>
    <p><strong>Pracovní šablona pro úpravy:</strong> <code><?= h($editableTheme) ?></code></p>
    <p><strong>Adresář šablon:</strong> <code>/themes</code></p>
  </fieldset>

  <div class="button-row admin-action-row">
    <button type="submit" name="form_action" value="activate_theme" class="btn">Uložit šablonu</button>
    <button type="submit" name="form_action" value="preview_theme" class="btn">Spustit živý náhled</button>
    <?php if ($previewIsActive): ?>
      <button type="submit" name="form_action" value="clear_preview" class="btn">Ukončit náhled</button>
    <?php endif; ?>
  </div>
</form>

<section class="admin-section-block">
  <h2 class="admin-section-block__heading">Vzhled vybrané šablony</h2>
  <p>
    Nastavení níže upravují šablonu <strong><?= h($editableManifest['name']) ?></strong>.
    <?php if ($previewIsActive): ?>
      Formulář právě pracuje s šablonou v živém náhledu, nikoli nutně s aktivní produkční šablonou.
    <?php else: ?>
      Pokud mezi šablonami přepínáte, každá si drží vlastní uložené hodnoty.
    <?php endif; ?>
  </p>

  <?php if ($themeSettingDefinitions === []): ?>
    <p>Tato šablona zatím nemá konfigurovatelné vizuální prvky.</p>
  <?php else: ?>
    <form method="post" novalidate<?= $themeSettingsHasError ? ' aria-describedby="themes-form-errors"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="theme_key" value="<?= h($editableTheme) ?>">
      <input type="hidden" name="preview_redirect" value="<?= h($previewRedirectDefault) ?>">

      <fieldset aria-describedby="theme-settings-help">
        <legend>Bezpečné theme settings</legend>
        <p id="theme-settings-help">
          Můžete upravit nejen barvy a typografii, ale i variantu hlavičky a úvodní stránky.
          Barevná pole zároveň hlídají minimální kontrast pro kritické prvky s bílým textem.
          Pokud barva nevyhoví, systém ji neuloží.
        </p>

        <?php foreach ($themeSettingDefinitions as $settingKey => $definition): ?>
          <?php
              $fieldId = 'theme-setting-' . preg_replace('/[^a-z0-9_-]+/i', '-', $settingKey);
            $helpId = $fieldId . '-help';
            $errorFieldName = 'theme_setting_' . $settingKey;
            $errorId = $fieldId . '-error';
            $value = $themeSettingValues[$settingKey] ?? (string)$definition['default'];
            $settingAvailable = themeSettingIsAvailable($definition);
            $requiredModulesText = themeRequiredModulesDescription((array)($definition['requires_modules'] ?? []));
            ?>
          <div class="theme-setting-row">
            <label for="<?= h($fieldId) ?>"><?= h($definition['label']) ?></label>

            <?php if (!$settingAvailable): ?>
              <p id="<?= h($helpId) ?>" class="field-help theme-card__hint">
                Toto nastavení je k dispozici jen při zapnutém modulu
                <strong><?= h($requiredModulesText) ?></strong>.
                Uložená hodnota zůstává beze změny a homepage ji znovu použije, až modul znovu aktivujete.
              </p>
            <?php elseif ($definition['type'] === 'color'): ?>
              <div class="theme-setting-color-row">
                <input
                  type="color"
                  id="<?= h($fieldId) ?>"
                  name="theme_settings[<?= h($settingKey) ?>]"
                  value="<?= h($value) ?>"
                  <?= adminFieldAttributes($errorFieldName, $themeFieldErrors, [], [$helpId], $errorId) ?>>
                <code><?= h($value) ?></code>
              </div>
              <small id="<?= h($helpId) ?>">
                <?= h($definition['description']) ?>
                Výchozí hodnota: <code><?= h((string)$definition['default']) ?></code>.
              </small>
            <?php else: ?>
              <?php
                  $availableOptions = themeAvailableSelectOptions($definition);
                $displayValue = themeAvailableSelectValue($definition, (string)$value);
                $selectedOption = $definition['options'][$displayValue] ?? $definition['options'][$definition['default']];
                $hiddenOptionLabels = [];
                foreach ($definition['options'] as $optionKey => $option) {
                    if (isset($availableOptions[$optionKey])) {
                        continue;
                    }

                    $optionLabel = $option['label'];
                    $optionModules = themeRequiredModulesDescription((array)($option['requires_modules'] ?? []));
                    if ($optionModules !== '') {
                        $optionLabel .= ' (' . $optionModules . ')';
                    }
                    $hiddenOptionLabels[] = $optionLabel;
                }
                ?>
              <?php if ($availableOptions === []): ?>
                <p id="<?= h($helpId) ?>" class="field-help theme-card__hint">
                  Pro tuto volbu teď není k dispozici žádná aktivní modulová varianta.
                  Uložená hodnota zůstává beze změny.
                </p>
              <?php else: ?>
              <select
                id="<?= h($fieldId) ?>"
                name="theme_settings[<?= h($settingKey) ?>]"
                <?= adminFieldAttributes($errorFieldName, $themeFieldErrors, [], [$helpId], $errorId) ?>>
                <?php foreach ($availableOptions as $optionKey => $option): ?>
                  <option value="<?= h($optionKey) ?>" <?= $displayValue === $optionKey ? 'selected' : '' ?>>
                    <?= h($option['label']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small id="<?= h($helpId) ?>">
                <?= h($definition['description']) ?>
                Aktuálně: <strong><?= h($selectedOption['label']) ?></strong>.
                <?php if ($selectedOption['description'] !== ''): ?>
                  <?= h($selectedOption['description']) ?>
                <?php endif; ?>
                <?php if ($hiddenOptionLabels !== []): ?>
                  Nedostupné při současném stavu modulů:
                  <?= h(implode(', ', $hiddenOptionLabels)) ?>.
                <?php endif; ?>
              </small>
              <?php endif; ?>
            <?php endif; ?>
            <?php adminRenderFieldError($errorFieldName, $themeFieldErrors, [], $themeFieldErrorMessages[$errorFieldName] ?? '', $errorId); ?>
          </div>
        <?php endforeach; ?>
      </fieldset>

      <div class="button-row admin-action-row">
        <button type="submit" name="form_action" value="save_theme_settings" class="btn">Uložit vzhled</button>
        <button type="submit" name="form_action" value="preview_theme_settings" class="btn">Náhled se zadaným vzhledem</button>
        <button type="submit" name="form_action" value="reset_theme_settings" class="btn">Obnovit výchozí vzhled</button>
      </div>
    </form>
  <?php endif; ?>
</section>

<section class="admin-section-block">
  <h2 class="admin-section-block__heading">Import a export balíčků</h2>
  <p>
    Portable theme package je bezpečný ZIP formát pro přenos vzhledu mezi instalacemi.
    Obsahuje jen <code>theme.json</code> a statické assety v <code>assets/</code>;
    PHP layouty, partialy a view override soubory se do balíčku vědomě nepouštějí.
  </p>

  <div class="theme-package-grid">
    <form method="post" enctype="multipart/form-data" novalidate class="theme-package-card"<?= $themePackageHasError ? ' aria-describedby="themes-form-errors"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

      <fieldset>
        <legend>Import ZIP balíčku</legend>
        <label for="theme-package">Soubor ZIP</label>
        <input
          type="file"
          id="theme-package"
          name="theme_package"
          accept=".zip,application/zip"
          <?= adminFieldAttributes('theme_package', $themeFieldErrors, [], ['theme-package-help']) ?>>
        <small id="theme-package-help">
          Balíček musí obsahovat jediný kořenový adresář šablony, platný <code>theme.json</code>
          a soubor <code>assets/public.css</code>. Externí URL v CSS a PHP soubory nejsou povolené.
        </small>
        <?php adminRenderFieldError('theme_package', $themeFieldErrors, [], $themeFieldErrorMessages['theme_package'] ?? ''); ?>
      </fieldset>

      <div class="button-row admin-action-row">
        <button type="submit" name="form_action" value="import_theme_package" class="btn">Importovat balíček</button>
      </div>
    </form>

    <form method="post" novalidate class="theme-package-card"<?= $themeExportHasError ? ' aria-describedby="themes-form-errors"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

      <fieldset>
        <legend>Export ZIP balíčku</legend>
        <label for="export-theme">Šablona k exportu</label>
        <select id="export-theme" name="export_theme"<?= adminFieldAttributes('export_theme', $themeFieldErrors, [], ['export-theme-help']) ?>>
          <?php foreach ($availableThemeKeys as $themeKey): ?>
            <?php $manifest = $themeManifests[$themeKey]; ?>
            <option value="<?= h($themeKey) ?>" <?= $exportThemeDefault === $themeKey ? 'selected' : '' ?>>
              <?= h($manifest['name']) ?> (<?= h($themeKey) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <small id="export-theme-help">
          Export vytvoří portable ZIP s manifestem, uloženými výchozími theme settings a statickými assety.
          Runtime PHP override soubory se do balíčku z bezpečnostních důvodů nevkládají.
        </small>
        <?php adminRenderFieldError('export_theme', $themeFieldErrors, [], $themeFieldErrorMessages['export_theme'] ?? ''); ?>
      </fieldset>

      <div class="button-row admin-action-row">
        <button type="submit" name="form_action" value="export_theme_package" class="btn">Exportovat balíček</button>
      </div>
    </form>
  </div>
</section>

<?php adminFooter(); ?>
