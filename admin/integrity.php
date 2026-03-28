<?php
/**
 * Kontrola integrity souborů – porovnává SHA-256 hashe PHP souborů s uloženým snapshotem.
 * Detekuje nové, změněné a smazané soubory.
 */
require_once __DIR__ . '/layout.php';
requireCapability('settings_manage', 'Přístup odepřen.');

$baseDir = dirname(__DIR__);
$snapshotFile = $baseDir . '/.integrity_snapshot.json';

/**
 * Projde adresáře a vrátí pole [relativní_cesta => sha256_hash] pro PHP soubory.
 */
function scanPhpFiles(string $baseDir): array
{
    $hashes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $skipDirs = ['vendor', 'node_modules', '.git', '.claude', 'uploads', 'themes'];

    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;

        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['php', 'htaccess'], true) && $file->getFilename() !== '.htaccess') continue;

        $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($baseDir) + 1));

        // Přeskočit adresáře, které se mění legitimně
        $firstDir = explode('/', $relativePath)[0] ?? '';
        if (in_array($firstDir, $skipDirs, true)) continue;

        // Přeskočit temp/cache soubory
        if (str_contains($relativePath, '/tmp/') || str_contains($relativePath, '/cache/')) continue;

        $hashes[$relativePath] = hash_file('sha256', $file->getPathname());
    }

    ksort($hashes);
    return $hashes;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'generate') {
    verifyCsrf();
    $currentHashes = scanPhpFiles($baseDir);
    $snapshot = [
        'generated_at' => date('Y-m-d H:i:s'),
        'file_count' => count($currentHashes),
        'files' => $currentHashes,
    ];
    file_put_contents($snapshotFile, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    logAction('integrity_snapshot', 'files=' . count($currentHashes));
    $log[] = '✓ Snapshot vytvořen (' . count($currentHashes) . ' souborů)';
}

// Načtení existujícího snapshotu
$snapshot = null;
if (is_file($snapshotFile)) {
    $snapshot = json_decode(file_get_contents($snapshotFile), true);
}

// Porovnání
$changes = ['new' => [], 'modified' => [], 'deleted' => []];
$hasChanges = false;

if ($snapshot !== null && ($action === 'check' || $_SERVER['REQUEST_METHOD'] === 'POST')) {
    $currentHashes = $currentHashes ?? scanPhpFiles($baseDir);
    $savedHashes = $snapshot['files'] ?? [];

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
  <div style="background:#edf8ef;border:1px solid #2e7d32;border-radius:8px;padding:1rem;margin-bottom:1rem" role="status">
    <?php foreach ($log as $line): ?><p style="margin:0"><?= $line ?></p><?php endforeach; ?>
  </div>
<?php endif; ?>

<p>Kontrola integrity sleduje změny v PHP souborech. Porovnává aktuální SHA-256 hashe s uloženým snapshotem a hlásí nové, změněné nebo smazané soubory.</p>

<?php if ($snapshot === null): ?>
  <div style="background:#fff4e6;border:1px solid #d7b600;border-radius:8px;padding:1rem;margin:1rem 0" role="alert">
    <p style="margin:0"><strong>Žádný snapshot nebyl vytvořen.</strong> Vygenerujte první snapshot pro zahájení sledování.</p>
  </div>
<?php else: ?>
  <p>
    <strong>Poslední snapshot:</strong> <?= h($snapshot['generated_at'] ?? '?') ?> ·
    <strong>Sledovaných souborů:</strong> <?= (int)($snapshot['file_count'] ?? 0) ?>
  </p>
<?php endif; ?>

<div style="display:flex;gap:.5rem;margin:1rem 0;flex-wrap:wrap">
  <?php if ($snapshot !== null): ?>
    <a href="integrity.php?action=check" class="btn btn-primary">Zkontrolovat integritu</a>
  <?php endif; ?>
  <form method="post" style="display:inline">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="generate">
    <button type="submit" class="btn"
            data-confirm="<?= $snapshot !== null ? 'Přepsat existující snapshot novým? Tím se aktuální stav označí jako důvěryhodný.' : 'Vytvořit první snapshot?' ?>">
      <?= $snapshot !== null ? 'Obnovit snapshot (nová baseline)' : 'Vytvořit první snapshot' ?>
    </button>
  </form>
</div>

<?php if ($action === 'check' || ($_SERVER['REQUEST_METHOD'] === 'POST' && $snapshot !== null)): ?>
  <section aria-labelledby="check-result-heading" style="margin-top:1.5rem">
    <?php if (!$hasChanges): ?>
      <div style="background:#edf8ef;border:1px solid #2e7d32;border-radius:8px;padding:1rem" role="status">
        <h2 id="check-result-heading" style="margin-top:0">✓ Integrita v pořádku</h2>
        <p style="margin-bottom:0">Všechny sledované soubory odpovídají uloženému snapshotu. Žádné neočekávané změny.</p>
      </div>
    <?php else: ?>
      <div style="background:#fff0f0;border:1px solid #c62828;border-radius:8px;padding:1rem" role="alert">
        <h2 id="check-result-heading" style="margin-top:0">⚠ Zjištěny změny</h2>
        <p>Od posledního snapshotu (<?= h($snapshot['generated_at'] ?? '?') ?>) byly detekovány následující změny:</p>

        <?php if (!empty($changes['new'])): ?>
          <h3 style="color:#c62828">Nové soubory (<?= count($changes['new']) ?>)</h3>
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
          <h3 style="color:#d84315">Změněné soubory (<?= count($changes['modified']) ?>)</h3>
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
          <h3 style="color:#555">Smazané soubory (<?= count($changes['deleted']) ?>)</h3>
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

        <p style="margin-top:1rem">Pokud jsou změny legitimní (po nasazení nové verze), obnovte snapshot tlačítkem výše.</p>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section style="margin-top:2rem;padding:1rem;background:#f5f7fa;border:1px solid #d6d6d6;border-radius:8px" aria-labelledby="integrity-info-heading">
  <h2 id="integrity-info-heading" style="margin-top:0">Jak to funguje</h2>
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
