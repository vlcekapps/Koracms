<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$releasePackageAuditPath = __DIR__ . DIRECTORY_SEPARATOR . 'release_package_audit.php';

function releasePackageAuditSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function releasePackageAuditSelfTestWriteTextFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        releasePackageAuditSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        releasePackageAuditSelfTestFail('Cannot write file: ' . $path);
    }
}

function releasePackageAuditSelfTestReadTextFile(string $path): string
{
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        releasePackageAuditSelfTestFail('Cannot read file: ' . $path);
    }

    return $contents;
}

function releasePackageAuditSelfTestCopyFile(string $source, string $destination): void
{
    $contents = releasePackageAuditSelfTestReadTextFile($source);
    releasePackageAuditSelfTestWriteTextFile($destination, $contents);
}

function releasePackageAuditSelfTestReplaceInFile(
    string $path,
    string $search,
    string $replace,
    string $label
): void {
    $contents = releasePackageAuditSelfTestReadTextFile($path);
    $updated = str_replace($search, $replace, $contents, $count);

    if ($count < 1) {
        releasePackageAuditSelfTestFail('Cannot prepare release package audit fixture: ' . $label);
    }

    releasePackageAuditSelfTestWriteTextFile($path, $updated);
}

function releasePackageAuditSelfTestAppendToFile(string $path, string $contents): void
{
    $currentContents = releasePackageAuditSelfTestReadTextFile($path);
    releasePackageAuditSelfTestWriteTextFile($path, $currentContents . $contents);
}

function releasePackageAuditSelfTestRemoveTree(string $path): void
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

            releasePackageAuditSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return array{exitCode:int, output:string}
 */
function runReleasePackageAuditSelfTestCommand(array $command, string $cwd): array
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
        releasePackageAuditSelfTestFail('Cannot start command: ' . implode(' ', $command));
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

function prepareReleasePackageAuditFixture(string $tempRoot): void
{
    global $projectRoot;

    releasePackageAuditSelfTestCopyFile(
        $projectRoot . DIRECTORY_SEPARATOR . '.gitattributes',
        $tempRoot . DIRECTORY_SEPARATOR . '.gitattributes'
    );
    releasePackageAuditSelfTestCopyFile(
        $projectRoot . DIRECTORY_SEPARATOR . '.gitignore',
        $tempRoot . DIRECTORY_SEPARATOR . '.gitignore'
    );
    releasePackageAuditSelfTestCopyFile(
        $projectRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release.ps1',
        $tempRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release.ps1'
    );
    releasePackageAuditSelfTestCopyFile(
        $projectRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release_smoke.php',
        $tempRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release_smoke.php'
    );
}

/**
 * @param null|callable(string):void $mutateFixture
 * @return array{exitCode:int, output:string}
 */
function runReleasePackageAuditWithFixture(?callable $mutateFixture = null): array
{
    global $projectRoot, $releasePackageAuditPath;

    $tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms_release_package_audit_'
        . bin2hex(random_bytes(6));

    try {
        if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
            releasePackageAuditSelfTestFail('Cannot create temp directory: ' . $tempRoot);
        }

        prepareReleasePackageAuditFixture($tempRoot);

        if ($mutateFixture !== null) {
            $mutateFixture($tempRoot);
        }

        return runReleasePackageAuditSelfTestCommand(
            [PHP_BINARY, $releasePackageAuditPath, $tempRoot],
            $projectRoot
        );
    } finally {
        releasePackageAuditSelfTestRemoveTree($tempRoot);
    }
}

function assertReleasePackageAuditPasses(string $label): void
{
    $result = runReleasePackageAuditWithFixture();
    if ($result['exitCode'] !== 0) {
        releasePackageAuditSelfTestFail($label . ' should pass release package audit.' . PHP_EOL . $result['output']);
    }
}

/**
 * @param callable(string):void $mutateFixture
 */
function assertReleasePackageAuditFails(string $label, callable $mutateFixture, string $expectedOutput): void
{
    $result = runReleasePackageAuditWithFixture($mutateFixture);
    if ($result['exitCode'] === 0) {
        releasePackageAuditSelfTestFail($label . ' should fail release package audit.');
    }
    if (!str_contains($result['output'], $expectedOutput)) {
        releasePackageAuditSelfTestFail(
            $label . ' failed for an unexpected reason.'
            . PHP_EOL
            . 'Expected output fragment: ' . $expectedOutput
            . PHP_EOL
            . $result['output']
        );
    }
}

if (!is_file($releasePackageAuditPath)) {
    releasePackageAuditSelfTestFail('Release package audit self-test cannot find release_package_audit.php.');
}

assertReleasePackageAuditPasses('Clean release package fixture');

assertReleasePackageAuditFails(
    'Missing release script vendor exclusion guard',
    static function (string $tempRoot): void {
        releasePackageAuditSelfTestReplaceInFile(
            $tempRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release.ps1',
            ", 'vendor'",
            '',
            'missing release script vendor exclusion'
        );
    },
    'build/release.ps1 does not exclude vendor.'
);

assertReleasePackageAuditFails(
    'Missing release script Codex exclusion guard',
    static function (string $tempRoot): void {
        releasePackageAuditSelfTestReplaceInFile(
            $tempRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release.ps1',
            ", '.codex'",
            '',
            'missing release script Codex exclusion'
        );
    },
    'build/release.ps1 does not exclude .codex.'
);

assertReleasePackageAuditFails(
    'Compress-Archive regression guard',
    static function (string $tempRoot): void {
        releasePackageAuditSelfTestAppendToFile(
            $tempRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release.ps1',
            "\nCompress-Archive\n"
        );
    },
    'build/release.ps1 must not use Compress-Archive'
);

assertReleasePackageAuditFails(
    'Missing release smoke required file guard',
    static function (string $tempRoot): void {
        releasePackageAuditSelfTestReplaceInFile(
            $tempRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release_smoke.php',
            "        'config.sample.php',\n",
            '',
            'missing release smoke config.sample.php guard'
        );
    },
    "build/release_smoke.php is missing release artifact guard: 'config.sample.php',"
);

assertReleasePackageAuditFails(
    'Missing release smoke license guard',
    static function (string $tempRoot): void {
        releasePackageAuditSelfTestReplaceInFile(
            $tempRoot . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'release_smoke.php',
            "        'LICENSE',\n",
            '',
            'missing release smoke LICENSE guard'
        );
    },
    "build/release_smoke.php is missing release artifact guard: 'LICENSE',"
);

assertReleasePackageAuditFails(
    'Missing source archive export-ignore guard',
    static function (string $tempRoot): void {
        releasePackageAuditSelfTestReplaceInFile(
            $tempRoot . DIRECTORY_SEPARATOR . '.gitattributes',
            "vendor/** export-ignore\n",
            '',
            'missing vendor export-ignore rule'
        );
    },
    '.gitattributes is missing source archive rule: vendor/** export-ignore'
);

assertReleasePackageAuditFails(
    'Missing node modules ignore guard',
    static function (string $tempRoot): void {
        releasePackageAuditSelfTestReplaceInFile(
            $tempRoot . DIRECTORY_SEPARATOR . '.gitignore',
            "node_modules/\n",
            '',
            'missing node_modules ignore rule'
        );
    },
    '.gitignore is missing local/generated file rule: node_modules/'
);

assertReleasePackageAuditFails(
    'Missing local dist ignore guard',
    static function (string $tempRoot): void {
        releasePackageAuditSelfTestReplaceInFile(
            $tempRoot . DIRECTORY_SEPARATOR . '.gitignore',
            "dist/\n",
            '',
            'missing dist ignore rule'
        );
    },
    '.gitignore is missing local/generated file rule: dist/'
);

echo "Release package audit self-test OK\n";
