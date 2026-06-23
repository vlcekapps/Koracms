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
  <script nonce="<?= cspNonce() ?>">document.documentElement.dataset.fixture='ok';</script>
</section>
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
    'Script nonce guard',
    <<<'PHP'
<script>document.documentElement.dataset.bad='1';</script>
PHP,
    'script tag without CSP nonce'
);

echo "Theme view audit self-test OK\n";
