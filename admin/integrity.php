<?php
/**
 * Kontrola integrity souborů – porovnává SHA-256 hashe PHP souborů s uloženým snapshotem.
 * Detekuje nové, změněné a smazané soubory.
 */
require_once __DIR__ . '/layout.php';
requireCapability('settings_manage', 'Přístup odepřen.');

$baseDir = dirname(__DIR__);
$snapshotFile = koraStoragePath('integrity/snapshot.json');
$legacySnapshotFile = $baseDir . '/.integrity_snapshot.json';

/**
 * Projde adresáře a vrátí pole [relativní_cesta => sha256_hash] pro PHP soubory.
 *
 * @return array<string, string>
 */
function scanPhpFiles(string $baseDir): array
{
    $hashes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    // Vývojové závislosti nejsou v release; runtime šablony a uploads se naopak skenují.
    $skipDirs = ['vendor', 'node_modules', '.git', '.claude'];

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['php', 'htaccess'], true) && $file->getFilename() !== '.htaccess') {
            continue;
        }

        $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($baseDir) + 1));

        // Přeskočit adresáře, které se mění legitimně
        $firstDir = explode('/', $relativePath)[0];
        if (in_array($firstDir, $skipDirs, true)) {
            continue;
        }


        $hashes[$relativePath] = hash_file('sha256', $file->getPathname());
    }

    ksort($hashes);
    return $hashes;
}

/**
 * @param array<string, mixed> $snapshot
 */
function writeIntegritySnapshotFile(string $snapshotFile, array $snapshot): bool
{
    if (!koraEnsureDirectory(dirname($snapshotFile))) {
        return false;
    }

    $encoded = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }

    try {
        $suffix = bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        return false;
    }

    $temporaryFile = $snapshotFile . '.tmp.' . $suffix;
    $backupFile = $snapshotFile . '.previous.' . $suffix;
    if (@file_put_contents($temporaryFile, $encoded, LOCK_EX) === false) {
        return false;
    }

    $hasPreviousSnapshot = is_file($snapshotFile);
    if ($hasPreviousSnapshot && !@rename($snapshotFile, $backupFile)) {
        @unlink($temporaryFile);
        return false;
    }

    if (!@rename($temporaryFile, $snapshotFile)) {
        if ($hasPreviousSnapshot) {
            @rename($backupFile, $snapshotFile);
        }
        @unlink($temporaryFile);
        return false;
    }

    if ($hasPreviousSnapshot) {
        @unlink($backupFile);
    }
    @chmod($snapshotFile, 0600);
    return true;
}

$submittedAction = $_POST['action'] ?? $_GET['action'] ?? '';
$action = is_string($submittedAction) ? $submittedAction : '';
$log = [];
$integrityError = '';
$integrityConfirmField = 'confirm_integrity_snapshot';
$integrityFieldErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'generate') {
    verifyCsrf();
    $integrityConfirmed = isset($_POST[$integrityConfirmField])
        && (string)$_POST[$integrityConfirmField] === '1';

    if (!$integrityConfirmed) {
        $integrityFieldErrors[] = $integrityConfirmField;
        $integrityError = 'Před uložením nové baseline potvrďte kontrolu aktuálního stavu souborů.';
    } else {
        $currentHashes = scanPhpFiles($baseDir);
        $newSnapshot = [
            'generated_at' => date('Y-m-d H:i:s'),
            'file_count' => count($currentHashes),
            'files' => $currentHashes,
        ];

        if (!writeIntegritySnapshotFile($snapshotFile, $newSnapshot)) {
            $integrityError = 'Snapshot se nepodařilo bezpečně uložit do privátního úložiště. Zkontrolujte nastavení KORA_STORAGE_DIR a oprávnění adresáře.';
            koraLog('error', 'integrity snapshot write failed', [
                'snapshot_path_hash' => hash('sha256', $snapshotFile),
            ]);
        } else {
            if (is_file($legacySnapshotFile) && !@unlink($legacySnapshotFile)) {
                $log[] = 'Nový snapshot byl uložen, ale starý snapshot ve webrootu se nepodařilo odstranit.';
                koraLog('warning', 'legacy integrity snapshot cleanup failed', [
                    'snapshot_path_hash' => hash('sha256', $legacySnapshotFile),
                ]);
            }

            logAction('integrity_snapshot', 'files=' . count($currentHashes));
            $log[] = 'Snapshot vytvořen (' . count($currentHashes) . ' souborů).';
        }
    }
}

// Načtení existujícího snapshotu z privátního úložiště.
$snapshot = null;
$snapshotReadError = '';
if (is_file($snapshotFile)) {
    $rawSnapshot = @file_get_contents($snapshotFile);
    $decodedSnapshot = is_string($rawSnapshot) ? json_decode($rawSnapshot, true) : null;
    if (is_array($decodedSnapshot) && is_array($decodedSnapshot['files'] ?? null)) {
        $snapshot = $decodedSnapshot;
    } else {
        $snapshotReadError = 'Uložený snapshot integrity je poškozený nebo nečitelný. Vytvořte novou baseline až po kontrole aktuálních souborů.';
    }
}

// Porovnání
$changes = ['new' => [], 'modified' => [], 'deleted' => []];
$hasChanges = false;

if ($snapshot !== null && $action === 'check') {
    $currentHashes = $currentHashes ?? scanPhpFiles($baseDir);
    $savedHashes = $snapshot['files'];

    foreach ($currentHashes as $path => $hash) {
        if (!isset($savedHashes[$path])) {
            $changes['new'][] = $path;
        } elseif ($savedHashes[$path] !== $hash) {
            $changes['modified'][] = $path;
        }
    }

    foreach ($savedHashes as $path => $hash) {
        if (!isset($currentHashes[$path])) {
            $changes['deleted'][] = $path;
        }
    }

    $hasChanges = !empty($changes['new']) || !empty($changes['modified']) || !empty($changes['deleted']);
}

adminHeader('Kontrola integrity souborů');
?>

<?php if (!empty($log)): ?>
  <div class="integrity-panel integrity-panel--success" role="status" aria-atomic="true" aria-labelledby="integrity-status-heading">
    <h2 id="integrity-status-heading" class="sr-only">Výsledek uložení snapshotu</h2>
    <?php foreach ($log as $line): ?><p class="integrity-copy--flush"><?= h($line) ?></p><?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($integrityError !== ''): ?>
  <div id="integrity-form-error" class="integrity-panel integrity-panel--danger" role="alert" aria-atomic="true" aria-labelledby="integrity-error-heading">
    <h2 id="integrity-error-heading" class="integrity-heading--flush">Snapshot nebyl uložen</h2>
    <p class="integrity-copy--footer"><?= h($integrityError) ?></p>
  </div>
<?php endif; ?>

<?php if ($snapshotReadError !== ''): ?>
  <div class="integrity-panel integrity-panel--danger" role="alert" aria-atomic="true" aria-labelledby="integrity-read-error-heading">
    <h2 id="integrity-read-error-heading" class="integrity-heading--flush">Snapshot nelze načíst</h2>
    <p class="integrity-copy--footer"><?= h($snapshotReadError) ?></p>
  </div>
<?php endif; ?>

<p>Kontrola integrity sleduje změny v PHP souborech. Porovnává aktuální SHA-256 hashe s uloženým snapshotem a hlásí nové, změněné nebo smazané soubory.</p>

<?php if (is_file($legacySnapshotFile)): ?>
  <section class="integrity-panel integrity-panel--warning" aria-labelledby="integrity-legacy-heading">
    <h2 id="integrity-legacy-heading" class="integrity-heading--flush">Starý snapshot čeká na bezpečné nahrazení</h2>
    <p class="integrity-copy--footer">Snapshot uložený ve webrootu se už z bezpečnostních důvodů nepoužívá. Po kontrole souborů vytvořte novou baseline; úspěšné uložení starý soubor odstraní.</p>
  </section>
<?php endif; ?>

<?php if ($snapshot === null): ?>
  <section class="integrity-panel integrity-panel--warning" aria-labelledby="integrity-missing-heading">
    <h2 id="integrity-missing-heading" class="integrity-heading--flush">Žádný použitelný snapshot</h2>
    <p class="integrity-copy--footer">Zkontrolujte aktuální stav instalace a potom vytvořte první bezpečnou baseline.</p>
  </section>
<?php else: ?>
  <p>
    <strong>Poslední snapshot:</strong> <?= h((string)($snapshot['generated_at'] ?? '?')) ?> ·
    <strong>Sledovaných souborů:</strong> <?= (int)($snapshot['file_count'] ?? 0) ?>
  </p>
<?php endif; ?>

<?php if ($snapshot !== null): ?>
  <div class="button-row integrity-actions">
    <a href="integrity.php?action=check" class="btn btn-primary">Zkontrolovat integritu</a>
  </div>
<?php endif; ?>

<form method="post" id="integrity-baseline-form" class="integrity-baseline-form" novalidate<?= $integrityError !== '' ? ' aria-describedby="integrity-form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="action" value="generate">
  <fieldset class="admin-fieldset-card">
    <legend><?= $snapshot !== null ? 'Obnova důvěryhodné baseline' : 'Vytvoření důvěryhodné baseline' ?></legend>
    <p id="integrity-baseline-review" class="field-help field-help--flush">
      Nová baseline označí právě aktuální PHP soubory a <code>.htaccess</code> jako důvěryhodné. Nejdříve prověřte, že instalace neobsahuje neočekávané změny.
    </p>
    <div class="admin-field-row">
      <label for="confirm_integrity_snapshot" class="checkbox-label">
        <input type="checkbox" id="confirm_integrity_snapshot" name="confirm_integrity_snapshot" value="1"
               required aria-required="true"
               <?= adminFieldAttributes(
                   $integrityConfirmField,
                   $integrityFieldErrors,
                   [],
                   ['integrity-baseline-review', 'integrity-baseline-help'],
                   'confirm-integrity-snapshot-error'
               ) ?>>
        Potvrzuji, že jsem zkontroloval(a) aktuální stav souborů a chci jej uložit jako důvěryhodnou baseline.
      </label>
      <small id="integrity-baseline-help" class="field-help">Obnova baseline odstraní důkaz o dosud zjištěných změnách, proto ji provádějte až po ověření nasazení.</small>
      <?php adminRenderFieldError(
          $integrityConfirmField,
          $integrityFieldErrors,
          [],
          'Před uložením nové baseline potvrďte kontrolu aktuálního stavu souborů.',
          'confirm-integrity-snapshot-error'
      ); ?>
    </div>
    <div class="button-row">
      <button type="submit" class="btn">
        <?= $snapshot !== null ? 'Obnovit snapshot (nová baseline)' : 'Vytvořit první snapshot' ?>
      </button>
    </div>
  </fieldset>
</form>
<?php if ($action === 'check' && $snapshot !== null): ?>
  <section aria-labelledby="check-result-heading" class="integrity-result">
    <?php if (!$hasChanges): ?>
      <div class="integrity-panel integrity-panel--success" role="status">
        <h2 id="check-result-heading" class="integrity-heading--flush">✓ Integrita v pořádku</h2>
        <p class="integrity-copy--footer">Všechny sledované soubory odpovídají uloženému snapshotu. Žádné neočekávané změny.</p>
      </div>
    <?php else: ?>
      <div class="integrity-panel integrity-panel--danger" role="alert">
        <h2 id="check-result-heading" class="integrity-heading--flush">⚠ Zjištěny změny</h2>
        <p>Od posledního snapshotu (<?= h($snapshot['generated_at'] ?? '?') ?>) byly detekovány následující změny:</p>

        <?php if (!empty($changes['new'])): ?>
          <h3 class="integrity-heading--danger">Nové soubory (<?= count($changes['new']) ?>)</h3>
          <p>Tyto soubory nebyly v původním snapshotu – mohou být legitimní (deploy, update) nebo podvržené.</p>
          <table>
            <caption class="sr-only">Nové soubory od posledního snapshotu</caption>
            <thead><tr><th scope="col">Soubor</th></tr></thead>
            <tbody>
              <?php foreach ($changes['new'] as $path): ?>
                <tr><td><code><?= h($path) ?></code></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <?php if (!empty($changes['modified'])): ?>
          <h3 class="integrity-heading--modified">Změněné soubory (<?= count($changes['modified']) ?>)</h3>
          <p>Obsah těchto souborů se liší od snapshotu – ověřte, že změny jsou legitimní.</p>
          <table>
            <caption class="sr-only">Změněné soubory od posledního snapshotu</caption>
            <thead><tr><th scope="col">Soubor</th></tr></thead>
            <tbody>
              <?php foreach ($changes['modified'] as $path): ?>
                <tr><td><code><?= h($path) ?></code></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <?php if (!empty($changes['deleted'])): ?>
          <h3 class="integrity-heading--muted">Smazané soubory (<?= count($changes['deleted']) ?>)</h3>
          <table>
            <caption class="sr-only">Smazané soubory od posledního snapshotu</caption>
            <thead><tr><th scope="col">Soubor</th></tr></thead>
            <tbody>
              <?php foreach ($changes['deleted'] as $path): ?>
                <tr><td><code><?= h($path) ?></code></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <p class="integrity-copy--top">Pokud jsou změny legitimní (po nasazení nové verze), obnovte snapshot tlačítkem výše.</p>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section class="integrity-panel integrity-panel--info integrity-panel--spaced" aria-labelledby="integrity-info-heading">
  <h2 id="integrity-info-heading" class="integrity-heading--flush">Jak to funguje</h2>
  <ul>
    <li><strong>Snapshot</strong> – uloží SHA-256 hash každého PHP souboru a <code>.htaccess</code></li>
    <li><strong>Kontrola</strong> – porovná aktuální hashe s uloženým snapshotem</li>
    <li><strong>Nové soubory</strong> – soubory, které v snapshotu nebyly (potenciální backdoor)</li>
    <li><strong>Změněné soubory</strong> – soubory, jejichž obsah se liší (injekce kódu)</li>
    <li><strong>Smazané soubory</strong> – soubory, které chybí (odstraněná funkčnost)</li>
    <li>Adresáře <code>uploads/</code>, <code>themes/</code>, <code>.git/</code> se přeskakují</li>
  </ul>
  <p><strong>Doporučení:</strong> Po každém nasazení nové verze vytvořte nový snapshot. Kontrolu integrity spouštějte pravidelně.</p>
</section>

<?php adminFooter(); ?>
