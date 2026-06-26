<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$themeViewAuditPath = __DIR__ . DIRECTORY_SEPARATOR . 'theme_view_audit.php';

function themeViewAuditSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function themeViewAuditSelfTestWriteTextFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        themeViewAuditSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        themeViewAuditSelfTestFail('Cannot write file: ' . $path);
    }
}

function themeViewAuditSelfTestRemoveTree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }

    $items = scandir($path);
    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            themeViewAuditSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int, output:string}
 */
function runThemeViewAuditCommand(array $command, string $cwd): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(
        $command,
        $descriptorSpec,
        $pipes,
        $cwd,
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        themeViewAuditSelfTestFail('Cannot start command: ' . implode(' ', $command));
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exitCode' => (int)$exitCode,
        'output' => trim(
            (is_string($stdout) ? $stdout : '')
            . (is_string($stderr) && $stderr !== '' ? PHP_EOL . $stderr : '')
        ),
    ];
}

/**
 * @return array{exitCode:int, output:string}
 */
function runThemeViewAuditWithFixture(string $fixtureSource): array
{
    global $projectRoot, $themeViewAuditPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_theme_view_audit_'
        . bin2hex(random_bytes(6));

    try {
        themeViewAuditSelfTestWriteTextFile(
            $tempRoot . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'fixture.php',
            $fixtureSource
        );

        return runThemeViewAuditCommand([PHP_BINARY, $themeViewAuditPath, $tempRoot], $projectRoot);
    } finally {
        themeViewAuditSelfTestRemoveTree($tempRoot);
    }
}

function assertThemeViewAuditPasses(string $label, string $fixtureSource): void
{
    $result = runThemeViewAuditWithFixture($fixtureSource);
    if ($result['exitCode'] !== 0) {
        themeViewAuditSelfTestFail($label . ' should pass theme view audit.' . PHP_EOL . $result['output']);
    }
}

function assertThemeViewAuditFails(string $label, string $fixtureSource, string $expectedOutput): void
{
    $result = runThemeViewAuditWithFixture($fixtureSource);
    if ($result['exitCode'] === 0) {
        themeViewAuditSelfTestFail($label . ' should fail theme view audit.');
    }
    if (!str_contains($result['output'], $expectedOutput)) {
        themeViewAuditSelfTestFail(
            $label . ' failed for an unexpected reason.'
            . PHP_EOL
            . 'Expected output fragment: ' . $expectedOutput
            . PHP_EOL
            . $result['output']
        );
    }
}

if (!is_file($themeViewAuditPath)) {
    themeViewAuditSelfTestFail('Theme view audit self-test cannot find theme_view_audit.php.');
}

assertThemeViewAuditPasses(
    'Clean theme view',
    <<<'PHP'
<?php $title = trim((string)($title ?? 'Ukázka')); ?>
<section aria-labelledby="fixture-heading">
  <h2 id="fixture-heading"><?= h($title) ?></h2>
  <button type="button" aria-controls="fixture-panel">Zobrazit</button>
  <div id="fixture-panel">
    <label for="fixture-input">Ukázkové pole</label>
    <input id="fixture-input" name="fixture_input">
    <img src="/uploads/example.jpg" alt="">
    <iframe src="/media/preview.php?id=1" title="Náhled PDF"></iframe>
    <a href="https://example.test" target="_blank" rel="noopener noreferrer">Externí odkaz<?= newWindowLinkSrOnlySuffix() ?></a>
  </div>
  <script nonce="<?= cspNonce() ?>">document.documentElement.dataset.fixture='ok';</script>
</section>
PHP
);

assertThemeViewAuditPasses(
    'Dynamic aria reference guard',
    <<<'PHP'
<section aria-labelledby="fixture-heading-<?= (int)$id ?>">
  <h2 id="fixture-heading-<?= (int)$id ?>">Dynamická sekce</h2>
</section>
PHP
);

assertThemeViewAuditPasses(
    'Hidden new-window text helper guard',
    <<<'PHP'
<a href="https://example.test" target="_blank" rel="noopener noreferrer">Externí odkaz<?= newWindowLinkSrOnlySuffix() ?></a>
PHP
);

assertThemeViewAuditPasses(
    'Dynamic form label guard',
    <<<'PHP'
<?php $fieldId = 'field-' . (int)$id; ?>
<label for="<?= $fieldId ?>">Dynamické pole</label>
<input id="<?= $fieldId ?>" name="dynamic_field">
PHP
);

assertThemeViewAuditPasses(
    'Fieldset legend guard',
    <<<'PHP'
<form method="get">
  <fieldset>
    <legend>Hledání</legend>
    <label for="fixture-search">Dotaz</label>
    <input id="fixture-search" name="q">
  </fieldset>
</form>
PHP
);

assertThemeViewAuditPasses(
    'Surface article heading guard',
    <<<'PHP'
<article class="surface surface--hero" aria-labelledby="fixture-article-title">
  <h1 id="fixture-article-title">Detail článku</h1>
</article>
PHP
);

assertThemeViewAuditFails(
    'Request input guard',
    <<<'PHP'
<p><?= h((string)($_GET['q'] ?? '')) ?></p>
PHP,
    'request input superglobal'
);

assertThemeViewAuditFails(
    'Runtime context superglobal guard',
    <<<'PHP'
<?php if (!empty($_SESSION['cms_user_id'])): ?>
  <a href="/admin/">Administrace</a>
<?php endif; ?>
PHP,
    'runtime context superglobal'
);

assertThemeViewAuditFails(
    'Database side effect guard',
    <<<'PHP'
<?php $pdo = db_connect(); $stmt = $pdo->query('SELECT 1'); ?>
<p><?= h((string)$stmt->fetchColumn()) ?></p>
PHP,
    'database connection or query'
);

assertThemeViewAuditFails(
    'Inline style guard',
    <<<'PHP'
<p style="color:red">Text</p>
PHP,
    'inline style element or attribute'
);

assertThemeViewAuditFails(
    'Section naming guard',
    <<<'PHP'
<section class="surface">
  <h2>Nepojmenovaná sekce</h2>
  <p>Obsah.</p>
</section>
PHP,
    'section without aria-labelledby'
);

assertThemeViewAuditFails(
    'Surface article missing heading guard',
    <<<'PHP'
<article class="surface surface--hero">
  <h1>Detail článku</h1>
</article>
PHP,
    'surface article without aria-labelledby'
);

assertThemeViewAuditFails(
    'Nav heading guard',
    <<<'PHP'
<nav aria-label="Pomocná navigace">
  <a href="/">Domů</a>
</nav>
PHP,
    'nav using aria-label instead of aria-labelledby'
);

assertThemeViewAuditFails(
    'Aside heading guard',
    <<<'PHP'
<aside class="article-shell__aside">
  <section aria-labelledby="aside-title">
    <h2 id="aside-title">Souvislosti</h2>
  </section>
</aside>
PHP,
    'aside without aria-labelledby'
);

assertThemeViewAuditFails(
    'Search landmark heading guard',
    <<<'PHP'
<form method="get" role="search" aria-label="Hledání">
  <label for="q">Hledat</label>
  <input id="q" name="q">
</form>
PHP,
    'search form using aria-label instead of aria-labelledby'
);

assertThemeViewAuditFails(
    'Fieldset legend missing guard',
    <<<'PHP'
<form method="get">
  <fieldset>
    <label for="fixture-q">Dotaz</label>
    <input id="fixture-q" name="q">
  </fieldset>
</form>
PHP,
    'fieldset without legend'
);

assertThemeViewAuditFails(
    'Fieldset aria-label guard',
    <<<'PHP'
<form method="get">
  <fieldset aria-label="Hledání">
    <label for="fixture-q">Dotaz</label>
    <input id="fixture-q" name="q">
  </fieldset>
</form>
PHP,
    'fieldset using aria-label instead of a legend'
);

assertThemeViewAuditFails(
    'Runtime clock guard',
    <<<'PHP'
<time datetime="<?= h(date('Y-m-d')) ?>">Dnes</time>
PHP,
    'runtime clock'
);

assertThemeViewAuditFails(
    'Script nonce guard',
    <<<'PHP'
<script>document.documentElement.dataset.bad='1';</script>
PHP,
    'script tag without CSP nonce'
);

assertThemeViewAuditFails(
    'Duplicate static id guard',
    <<<'PHP'
<section id="duplicated-id">
  <h2 id="duplicated-id">Duplicitní nadpis</h2>
</section>
PHP,
    'duplicate static id "duplicated-id"'
);

assertThemeViewAuditFails(
    'Missing static aria target guard',
    <<<'PHP'
<section aria-labelledby="missing-heading">
  <p>Obsah bez cílového nadpisu.</p>
</section>
PHP,
    'missing static aria-labelledby target "missing-heading"'
);

assertThemeViewAuditFails(
    'Missing static aria controls guard',
    <<<'PHP'
<button type="button" aria-controls="missing-panel">Otevřít</button>
PHP,
    'missing static aria-controls target "missing-panel"'
);

assertThemeViewAuditFails(
    'Missing static label target guard',
    <<<'PHP'
<label for="missing-input">Pole</label>
PHP,
    'missing static label target "missing-input"'
);

assertThemeViewAuditFails(
    'Form control id guard',
    <<<'PHP'
<input name="q">
PHP,
    'form control without id or ARIA label'
);

assertThemeViewAuditFails(
    'Form control label guard',
    <<<'PHP'
<input id="orphan-field" name="q">
PHP,
    'form control without matching label or ARIA label'
);

assertThemeViewAuditFails(
    'Image alt guard',
    <<<'PHP'
<img src="/uploads/example.jpg">
PHP,
    'image without alt attribute'
);

assertThemeViewAuditFails(
    'Iframe title guard',
    <<<'PHP'
<iframe src="/media/preview.php?id=1"></iframe>
PHP,
    'iframe without title attribute'
);

assertThemeViewAuditFails(
    'Button type guard',
    <<<'PHP'
<button>Odeslat</button>
PHP,
    'button without explicit type attribute'
);

assertThemeViewAuditPasses(
    'Table caption guard',
    <<<'PHP'
<table>
  <caption class="sr-only">Přehled položek</caption>
  <tr><th scope="col">Název</th><td>Položka</td></tr>
</table>
PHP
);

assertThemeViewAuditPasses(
    'Table aria-labelledby guard',
    <<<'PHP'
<h2 id="items-heading">Přehled položek</h2>
<table aria-labelledby="items-heading">
  <tr><th scope="col">Název</th><td>Položka</td></tr>
</table>
PHP
);

assertThemeViewAuditFails(
    'Table accessible name guard',
    <<<'PHP'
<table>
  <tr><th scope="col">Název</th><td>Položka</td></tr>
</table>
PHP,
    'table without caption or aria-labelledby'
);

assertThemeViewAuditFails(
    'Table aria-label guard',
    <<<'PHP'
<table aria-label="Přehled položek">
  <tr><th scope="col">Název</th><td>Položka</td></tr>
</table>
PHP,
    'table using aria-label instead of a caption or aria-labelledby'
);

assertThemeViewAuditFails(
    'Blank target rel guard',
    <<<'PHP'
<a href="https://example.test" target="_blank">Externí odkaz</a>
PHP,
    'target="_blank" link without rel="noopener noreferrer"'
);

assertThemeViewAuditFails(
    'Blank target noreferrer guard',
    <<<'PHP'
<a href="https://example.test" target="_blank" rel="noopener" aria-label="Externí odkaz – otevře se v novém okně">Externí odkaz</a>
PHP,
    'target="_blank" link without rel="noopener noreferrer"'
);

assertThemeViewAuditFails(
    'Blank target hidden text guard',
    <<<'PHP'
<a href="https://example.test" target="_blank" rel="noopener noreferrer">Externí odkaz</a>
PHP,
    'target="_blank" link without hidden DOM new-window text'
);

assertThemeViewAuditFails(
    'Blank target aria-label guard',
    <<<'PHP'
<a href="https://example.test" target="_blank" rel="noopener noreferrer" aria-label="Externí odkaz – otevře se v novém okně">Externí odkaz</a>
PHP,
    'target="_blank" link using aria-label instead of hidden DOM new-window text'
);

echo "Theme view audit self-test OK\n";
