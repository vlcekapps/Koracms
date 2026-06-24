<?php

declare(strict_types=1);

function lintPhpProjectRoot(?string $override): string
{
    if ($override !== null && trim($override) !== '') {
        return rtrim($override, DIRECTORY_SEPARATOR . '/');
    }

    $envRoot = getenv('KORA_LINT_PHP_ROOT');
    if (is_string($envRoot) && trim($envRoot) !== '') {
        return rtrim($envRoot, DIRECTORY_SEPARATOR . '/');
    }

    return dirname(__DIR__);
}

$rootArgument = $argv[1] ?? null;
$root = lintPhpProjectRoot(is_string($rootArgument) ? $rootArgument : null);
$excludedDirectories = [
    $root . DIRECTORY_SEPARATOR . 'dist',
    $root . DIRECTORY_SEPARATOR . 'vendor',
    $root . DIRECTORY_SEPARATOR . 'uploads',
];

$files = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $current) use ($excludedDirectories): bool {
            $path = $current->getPathname();
            foreach ($excludedDirectories as $excludedDirectory) {
                if ($path === $excludedDirectory || str_starts_with($path, $excludedDirectory . DIRECTORY_SEPARATOR)) {
                    return false;
                }
            }

            return true;
        }
    )
);

$failures = 0;
foreach ($files as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        $failures++;
        echo implode("\n", $output) . "\n";
    }
}

if ($failures > 0) {
    fwrite(STDERR, "PHP lint failed for {$failures} file(s).\n");
    exit(1);
}

echo "PHP lint OK\n";
