<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$lockHelperPath = __DIR__ . DIRECTORY_SEPARATOR . 'test_run_lock.php';

/**
 * @return never
 */
function testRunLockSelfTestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function testRunLockSelfTestWriteTextFile(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        testRunLockSelfTestFail('Cannot create directory: ' . $directory);
    }

    if (file_put_contents($path, $contents) === false) {
        testRunLockSelfTestFail('Cannot write file: ' . $path);
    }
}

function testRunLockSelfTestRemoveTree(string $path): void
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

            testRunLockSelfTestRemoveTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }

    @rmdir($path);
}

/**
 * @param list<string> $command
 * @return resource
 */
function testRunLockSelfTestStartProcess(array $command, string $cwd, string $logPath)
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['file', $logPath, 'a'],
        2 => ['file', $logPath, 'a'],
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
        testRunLockSelfTestFail('Cannot start process: ' . implode(' ', $command));
    }

    fclose($pipes[0]);

    return $process;
}

function testRunLockSelfTestWaitForFile(string $path, int $timeoutMs, string $label): void
{
    $deadline = microtime(true) + ($timeoutMs / 1000);
    while (microtime(true) < $deadline) {
        if (is_file($path)) {
            return;
        }

        usleep(20_000);
    }

    testRunLockSelfTestFail('Timed out waiting for ' . $label . ': ' . $path);
}

/**
 * @param resource $process
 */
function testRunLockSelfTestWaitForExit($process, int $timeoutMs, string $label): int
{
    $deadline = microtime(true) + ($timeoutMs / 1000);
    while (microtime(true) < $deadline) {
        $status = proc_get_status($process);
        if ($status['running'] === false) {
            $statusExitCode = $status['exitcode'];
            $closeExitCode = proc_close($process);
            if ($statusExitCode !== -1) {
                return $statusExitCode;
            }

            return (int) $closeExitCode;
        }

        usleep(20_000);
    }

    proc_terminate($process);
    testRunLockSelfTestFail('Timed out waiting for process: ' . $label);
}

/**
 * @param resource $process
 */
function testRunLockSelfTestAssertRunning($process, string $label): void
{
    $status = proc_get_status($process);
    if ($status['running'] !== true) {
        testRunLockSelfTestFail($label . ' exited before the lock was released.');
    }
}

$tempRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR
    . 'koracms_test_run_lock_'
    . bin2hex(random_bytes(6));

try {
    if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
        testRunLockSelfTestFail('Cannot create temp directory: ' . $tempRoot);
    }

    $holderScript = $tempRoot . DIRECTORY_SEPARATOR . 'holder.php';
    $waiterScript = $tempRoot . DIRECTORY_SEPARATOR . 'waiter.php';
    $readyFile = $tempRoot . DIRECTORY_SEPARATOR . 'holder.ready';
    $releaseFile = $tempRoot . DIRECTORY_SEPARATOR . 'release.flag';
    $acquiredFile = $tempRoot . DIRECTORY_SEPARATOR . 'waiter.acquired';
    $logPath = $tempRoot . DIRECTORY_SEPARATOR . 'process.log';

    testRunLockSelfTestWriteTextFile(
        $holderScript,
        <<<'PHP'
<?php
declare(strict_types=1);

require $argv[1];

koraAcquireDatabaseTestLock('holder');
file_put_contents($argv[2], 'ready');

$deadline = microtime(true) + 5;
while (!is_file($argv[3]) && microtime(true) < $deadline) {
    usleep(20_000);
}

if (!is_file($argv[3])) {
    fwrite(STDERR, "Timed out waiting for release flag.\n");
    exit(1);
}
PHP
    );

    testRunLockSelfTestWriteTextFile(
        $waiterScript,
        <<<'PHP'
<?php
declare(strict_types=1);

require $argv[1];

koraAcquireDatabaseTestLock('waiter');
file_put_contents($argv[2], 'acquired');
PHP
    );

    $holder = testRunLockSelfTestStartProcess(
        [PHP_BINARY, $holderScript, $lockHelperPath, $readyFile, $releaseFile],
        $projectRoot,
        $logPath
    );

    testRunLockSelfTestWaitForFile($readyFile, 2000, 'holder lock marker');

    $waiter = testRunLockSelfTestStartProcess(
        [PHP_BINARY, $waiterScript, $lockHelperPath, $acquiredFile],
        $projectRoot,
        $logPath
    );

    usleep(250_000);
    testRunLockSelfTestAssertRunning($waiter, 'Waiter process');
    if (is_file($acquiredFile)) {
        testRunLockSelfTestFail('Waiter acquired the lock before the holder released it.');
    }

    testRunLockSelfTestWriteTextFile($releaseFile, 'release');

    $holderExitCode = testRunLockSelfTestWaitForExit($holder, 3000, 'holder');
    if ($holderExitCode !== 0) {
        testRunLockSelfTestFail('Holder process failed with exit code ' . $holderExitCode . '. Log: ' . (string) file_get_contents($logPath));
    }

    $waiterExitCode = testRunLockSelfTestWaitForExit($waiter, 3000, 'waiter');
    if ($waiterExitCode !== 0) {
        testRunLockSelfTestFail('Waiter process failed with exit code ' . $waiterExitCode . '. Log: ' . (string) file_get_contents($logPath));
    }

    testRunLockSelfTestWaitForFile($acquiredFile, 1000, 'waiter acquired marker');
} finally {
    testRunLockSelfTestRemoveTree($tempRoot);
}

echo "Test run lock self-test OK\n";
