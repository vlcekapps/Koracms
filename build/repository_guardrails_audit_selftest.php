<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$repositoryGuardrailsAuditPath = __DIR__ . DIRECTORY_SEPARATOR . 'repository_guardrails_audit.php';

function repositoryGuardrailsSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function repositoryGuardrailsSelfTestWriteTextFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        repositoryGuardrailsSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        repositoryGuardrailsSelfTestFail('Cannot write file: ' . $path);
    }
}

function repositoryGuardrailsSelfTestVariable(string $name): string
{
    return '$' . $name;
}

function repositoryGuardrailsSelfTestRemoveTree(string $path): void
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

            repositoryGuardrailsSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int, output:string}
 */
function runRepositoryGuardrailsSelfTestCommand(array $command, string $cwd): array
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
        repositoryGuardrailsSelfTestFail('Cannot start command: ' . implode(' ', $command));
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
 * @param array<string,string> $files
 * @return array{exitCode:int, output:string}
 */
function runRepositoryGuardrailsAuditWithFixture(array $files): array
{
    global $projectRoot, $repositoryGuardrailsAuditPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_repository_guardrails_'
        . bin2hex(random_bytes(6));

    try {
        if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
            repositoryGuardrailsSelfTestFail('Cannot create temp directory: ' . $tempRoot);
        }

        $gitInitResult = runRepositoryGuardrailsSelfTestCommand(['git', 'init', '--quiet'], $tempRoot);
        if ($gitInitResult['exitCode'] !== 0) {
            repositoryGuardrailsSelfTestFail('Cannot initialize fixture git repository.' . PHP_EOL . $gitInitResult['output']);
        }

        foreach ($files as $relativePath => $contents) {
            repositoryGuardrailsSelfTestWriteTextFile(
                $tempRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
                $contents
            );
        }

        $gitAddResult = runRepositoryGuardrailsSelfTestCommand(['git', 'add', '-f', '--', '.'], $tempRoot);
        if ($gitAddResult['exitCode'] !== 0) {
            repositoryGuardrailsSelfTestFail('Cannot stage fixture files.' . PHP_EOL . $gitAddResult['output']);
        }

        return runRepositoryGuardrailsSelfTestCommand(
            [PHP_BINARY, $repositoryGuardrailsAuditPath, $tempRoot],
            $projectRoot
        );
    } finally {
        repositoryGuardrailsSelfTestRemoveTree($tempRoot);
    }
}

/**
 * @param array<string,string> $files
 */
function assertRepositoryGuardrailsPasses(string $label, array $files): void
{
    $result = runRepositoryGuardrailsAuditWithFixture($files);
    if ($result['exitCode'] !== 0) {
        repositoryGuardrailsSelfTestFail($label . ' should pass repository guardrails audit.' . PHP_EOL . $result['output']);
    }
}

/**
 * @param array<string,string> $files
 */
function assertRepositoryGuardrailsFails(string $label, array $files, string $expectedOutput): void
{
    $result = runRepositoryGuardrailsAuditWithFixture($files);
    if ($result['exitCode'] === 0) {
        repositoryGuardrailsSelfTestFail($label . ' should fail repository guardrails audit.');
    }
    if (!str_contains($result['output'], $expectedOutput)) {
        repositoryGuardrailsSelfTestFail(
            $label . ' failed for an unexpected reason.'
            . PHP_EOL
            . 'Expected output fragment: ' . $expectedOutput
            . PHP_EOL
            . $result['output']
        );
    }
}

if (!is_file($repositoryGuardrailsAuditPath)) {
    repositoryGuardrailsSelfTestFail('Repository guardrails audit self-test cannot find repository_guardrails_audit.php.');
}

assertRepositoryGuardrailsPasses(
    'Clean fixture',
    [
        'config.sample.php' => "<?php\n"
            . repositoryGuardrailsSelfTestVariable('server') . " = 'localhost';\n"
            . repositoryGuardrailsSelfTestVariable('database') . " = 'kora';\n"
            . repositoryGuardrailsSelfTestVariable('user') . " = 'root';\n"
            . repositoryGuardrailsSelfTestVariable('pass') . " = '';\n",
        'db.php' => "<?php\n"
            . repositoryGuardrailsSelfTestVariable('server') . " = 'localhost';\n"
            . repositoryGuardrailsSelfTestVariable('database') . " = 'kora';\n"
            . repositoryGuardrailsSelfTestVariable('user') . " = 'root';\n"
            . repositoryGuardrailsSelfTestVariable('pass') . " = '';\n",
        'admin/example.php' => "<?php\nrequire_once __DIR__ . '/../db.php';\n\$account = 'editor';\n",
        'uploads/.htaccess' => "# Upload guard\n",
        'uploads/backups/.htaccess' => "Require all denied\n",
    ]
);

assertRepositoryGuardrailsFails(
    'Tracked config guard',
    ['config.php' => "<?php\n" . repositoryGuardrailsSelfTestVariable('server') . " = 'localhost';\n"],
    'config.php: sensitive local configuration must not be tracked'
);

assertRepositoryGuardrailsFails(
    'Tracked environment guard',
    ['.env' => "DB_PASSWORD=secret\n"],
    '.env: environment file must not be tracked'
);

assertRepositoryGuardrailsFails(
    'Tracked vendor guard',
    ['vendor/autoload.php' => "<?php\n"],
    'vendor/autoload.php: Composer vendor dependencies must not be tracked'
);

assertRepositoryGuardrailsFails(
    'Tracked node dependencies guard',
    ['node_modules/example/index.js' => "module.exports = {};\n"],
    'node_modules/example/index.js: Node dependencies must not be tracked'
);

assertRepositoryGuardrailsFails(
    'Tracked local Codex metadata guard',
    ['.codex/settings.json' => "{}\n"],
    '.codex/settings.json: local Codex metadata must not be tracked'
);

assertRepositoryGuardrailsFails(
    'Tracked local Cursor metadata guard',
    ['.cursor/rules/project.md' => "# Local notes\n"],
    '.cursor/rules/project.md: local Cursor metadata must not be tracked'
);

assertRepositoryGuardrailsFails(
    'Tracked upload guard',
    ['uploads/media/file.pdf' => '%PDF-1.4'],
    'uploads/media/file.pdf: user upload content must not be tracked'
);

assertRepositoryGuardrailsFails(
    'Reserved DB variable guard',
    ['admin/example.php' => "<?php\nrequire_once __DIR__ . '/../db.php';\n" . repositoryGuardrailsSelfTestVariable('user') . " = 'editor';\n"],
    'admin/example.php:3: reserved DB connection variable ' . repositoryGuardrailsSelfTestVariable('user')
);

echo "Repository guardrails audit self-test OK\n";
