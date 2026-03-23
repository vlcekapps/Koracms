<?php
require_once __DIR__ . '/layout.php';
requireSuperAdmin();

$successMessage = '';
$errors = [];
$previewRedirectDefault = BASE_URL . '/index.php';

$reloadThemeCatalog = static function () use (&$availableThemeKeys, &$themeManifests, &$configuredTheme, &$effectiveTheme, &$usingFallback): void {
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
    $usingFallback = $configuredTheme !== $effectiveTheme;
};

$availableThemeKeys = [];
$themeManifests = [];
$configuredTheme = defaultThemeName();
$effectiveTheme = defaultThemeName();
$usingFallback = false;
$reloadThemeCatalog();

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
            $errors[] = 'Vybraná šablona není k dispozici.';
        }

        if ($errors === []) {
            if ($formAction === 'activate_theme') {
                saveSetting('active_theme', $selectedTheme);
                clearThemePreview();
                $reloadThemeCatalog();
                logAction('theme_activate', 'theme=' . $selectedTheme);
                $successMessage = 'Aktivní šablona byla uložena.';
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
            $errors[] = 'Vybraná šablona pro úpravu není k dispozici.';
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
                        $errors[] = $label . ': ' . $errorMessage;
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
            $errors = array_merge($errors, $importResult['errors']);
        } else {
            $reloadThemeCatalog();
            $importThemeKey = (string)($importResult['theme_key'] ?? '');
            logAction('theme_import', 'theme=' . $importThemeKey);
            $successMessage = 'ZIP balíček byl bezpečně importován jako šablona `' . $importThemeKey . '`.';
        }
    } elseif ($formAction === 'export_theme_package') {
        $exportThemeKey = trim((string)($_POST['export_theme'] ?? ''));
        if (!in_array($exportThemeKey, $availableThemeKeys, true)) {
            $errors[] = 'Vybraná šablona pro export není k dispozici.';
        } else {
            $exportResult = themeBuildPortablePackage($exportThemeKey);
            if (!$exportResult['ok']) {
                $errors = array_merge($errors, $exportResult['errors']);
            } else {
                logAction('theme_export', 'theme=' . $exportThemeKey);
                header('Content-Type: application/zip');
                header('X-Content-Type-Options: nosniff');
                header('Content-Disposition: attachment; filename="' . basename((string)$exportResult['filename']) . '"');
                header('Content-Length: ' . (string)filesize((string)$exportResult['path']));
                readfile((string)$exportResult['path']);
                @unlink((string)$exportResult['path']);
                exit;
            }
        }
    } elseif ($formAction === 'clear_preview') {
        clearThemePreview();
        logAction('theme_preview_stop');
        $successMessage = 'Živý náhled byl ukončen.';
    } else {
        $errors[] = 'Neplatná akce formuláře.';
    }
}

$reloadThemeCatalog();
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

adminHeader('Vzhled a šablony');
?>

<?php if ($successMessage !== ''): ?>
  <p class="success" role="status"><?= h($successMessage) ?></p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <ul class="error" role="alert">
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

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="preview_redirect" value="<?= h($previewRedirectDefault) ?>">

  <fieldset>
    <legend>Aktivní šablona</legend>
    <p id="theme-selection-help">
      Pokud je v nastavení uložená chybějící nebo neplatná šablona, systém automaticky přejde
      na výchozí <code><?= h(defaultThemeName()) ?></code>.
    </p>

    <div>
      <?php foreach ($availableThemeKeys as $themeKey): ?>
        <?php
          $manifest = $themeManifests[$themeKey];
          $themeId = 'theme-' . preg_replace('/[^a-z0-9_-]+/i', '-', $themeKey);
          $metaId = $themeId . '-meta';
          $isDefaultTheme = $themeKey === defaultThemeName();
          $isEffectiveTheme = $themeKey === $effectiveTheme;
          $isPreviewTheme = $previewIsActive && $themeKey === $previewThemeKey;
          $isConfiguredTheme = !$usingFallback && $themeKey === $configuredTheme;
        ?>
        <section style="border:1px solid #ccc;border-radius:8px;padding:1rem;margin-top:1rem;background:#fafafa">
          <div>
            <input
              type="radio"
              id="<?= h($themeId) ?>"
              name="active_theme"
              value="<?= h($themeKey) ?>"
              <?= $selectedTheme === $themeKey ? 'checked' : '' ?>
              aria-describedby="<?= h($metaId) ?>">
            <label for="<?= h($themeId) ?>" style="display:inline;font-weight:bold;margin-top:0">
              <?= h($manifest['name']) ?>
            </label>
          </div>

          <p id="<?= h($metaId) ?>" style="margin:.6rem 0 0;color:#444">
            Klíč: <code><?= h($manifest['key']) ?></code>
            · Verze: <?= h($manifest['version']) ?>
            · Autor: <?= h($manifest['author']) ?>
            <?= $isDefaultTheme ? '· Výchozí fallback' : '' ?>
          </p>

          <?php if (!empty($manifest['preview']['colors'])): ?>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem" aria-label="Barevný náhled šablony">
              <?php foreach ($manifest['preview']['colors'] as $previewColor): ?>
                <span
                  aria-hidden="true"
                  style="display:inline-block;width:1.35rem;height:1.35rem;border-radius:999px;border:1px solid rgba(0,0,0,.18);background:<?= h($previewColor) ?>"></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($manifest['preview']['summary'])): ?>
            <p style="margin:.6rem 0 0;color:#333"><?= h($manifest['preview']['summary']) ?></p>
          <?php endif; ?>

          <?php if ($manifest['description'] !== ''): ?>
            <p style="margin:.6rem 0 0"><?= h($manifest['description']) ?></p>
          <?php endif; ?>

          <p style="margin:.6rem 0 0;color:#333">
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
  </fieldset>

  <fieldset style="margin-top:1.5rem">
    <legend>Stav konfigurace</legend>
    <p><strong>Uložená hodnota:</strong> <code><?= h($configuredTheme) ?></code></p>
    <p><strong>Runtime používá:</strong> <code><?= h($effectiveTheme) ?></code></p>
    <p><strong>Pracovní šablona pro úpravy:</strong> <code><?= h($editableTheme) ?></code></p>
    <p><strong>Adresář šablon:</strong> <code>/themes</code></p>
  </fieldset>

  <div class="button-row" style="margin-top:1rem">
    <button type="submit" name="form_action" value="activate_theme" class="btn">Uložit šablonu</button>
    <button type="submit" name="form_action" value="preview_theme" class="btn">Spustit živý náhled</button>
    <?php if ($previewIsActive): ?>
      <button type="submit" name="form_action" value="clear_preview" class="btn">Ukončit náhled</button>
    <?php endif; ?>
  </div>
</form>

<section style="margin-top:2rem;border-top:1px solid #ddd;padding-top:1.5rem">
  <h2 style="margin-top:0">Vzhled vybrané šablony</h2>
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
    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="theme_key" value="<?= h($editableTheme) ?>">
      <input type="hidden" name="preview_redirect" value="<?= h($previewRedirectDefault) ?>">

      <fieldset>
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
            $value = $themeSettingValues[$settingKey] ?? (string)$definition['default'];
            $settingAvailable = themeSettingIsAvailable($definition);
            $requiredModulesText = themeRequiredModulesDescription((array)($definition['requires_modules'] ?? []));
          ?>
          <div style="margin-top:1rem">
            <label for="<?= h($fieldId) ?>"><?= h($definition['label']) ?></label>

            <?php if (!$settingAvailable): ?>
              <p id="<?= h($helpId) ?>" style="margin:.5rem 0 0;color:#4b5563">
                Toto nastavení je k dispozici jen při zapnutém modulu
                <strong><?= h($requiredModulesText) ?></strong>.
                Uložená hodnota zůstává beze změny a homepage ji znovu použije, až modul znovu aktivujete.
              </p>
            <?php elseif ($definition['type'] === 'color'): ?>
              <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
                <input
                  type="color"
                  id="<?= h($fieldId) ?>"
                  name="theme_settings[<?= h($settingKey) ?>]"
                  value="<?= h($value) ?>"
                  aria-describedby="<?= h($helpId) ?>">
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
                <p id="<?= h($helpId) ?>" style="margin:.5rem 0 0;color:#4b5563">
                  Pro tuto volbu teď není k dispozici žádná aktivní modulová varianta.
                  Uložená hodnota zůstává beze změny.
                </p>
              <?php else: ?>
              <select
                id="<?= h($fieldId) ?>"
                name="theme_settings[<?= h($settingKey) ?>]"
                aria-describedby="<?= h($helpId) ?>">
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
          </div>
        <?php endforeach; ?>
      </fieldset>

      <div class="button-row" style="margin-top:1rem">
        <button type="submit" name="form_action" value="save_theme_settings" class="btn">Uložit vzhled</button>
        <button type="submit" name="form_action" value="preview_theme_settings" class="btn">Náhled se zadaným vzhledem</button>
        <button type="submit" name="form_action" value="reset_theme_settings" class="btn">Obnovit výchozí vzhled</button>
      </div>
    </form>
  <?php endif; ?>
</section>

<section style="margin-top:2rem;border-top:1px solid #ddd;padding-top:1.5rem">
  <h2 style="margin-top:0">Import a export balíčků</h2>
  <p>
    Portable theme package je bezpečný ZIP formát pro přenos vzhledu mezi instalacemi.
    Obsahuje jen <code>theme.json</code> a statické assety v <code>assets/</code>;
    PHP layouty, partialy a view override soubory se do balíčku vědomě nepouštějí.
  </p>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(18rem,1fr));gap:1rem">
    <form method="post" enctype="multipart/form-data" novalidate style="border:1px solid #ccc;border-radius:8px;padding:1rem;background:#fafafa">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

      <fieldset>
        <legend>Import ZIP balíčku</legend>
        <label for="theme-package">Soubor ZIP</label>
        <input
          type="file"
          id="theme-package"
          name="theme_package"
          accept=".zip,application/zip"
          aria-describedby="theme-package-help">
        <small id="theme-package-help">
          Balíček musí obsahovat jediný kořenový adresář šablony, platný <code>theme.json</code>
          a soubor <code>assets/public.css</code>. Externí URL v CSS a PHP soubory nejsou povolené.
        </small>
      </fieldset>

      <div class="button-row" style="margin-top:1rem">
        <button type="submit" name="form_action" value="import_theme_package" class="btn">Importovat balíček</button>
      </div>
    </form>

    <form method="post" novalidate style="border:1px solid #ccc;border-radius:8px;padding:1rem;background:#fafafa">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

      <fieldset>
        <legend>Export ZIP balíčku</legend>
        <label for="export-theme">Šablona k exportu</label>
        <select id="export-theme" name="export_theme" aria-describedby="export-theme-help">
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
      </fieldset>

      <div class="button-row" style="margin-top:1rem">
        <button type="submit" name="form_action" value="export_theme_package" class="btn">Exportovat balíček</button>
      </div>
    </form>
  </div>
</section>

<?php adminFooter(); ?>
