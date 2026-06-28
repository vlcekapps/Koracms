<?php

declare(strict_types=1);

/**
 * Serializes integration-style test scripts that mutate shared local state.
 *
 * Runtime audit and HTTP integration both temporarily adjust settings in the
 * same database. A process-level file lock prevents parallel local runs from
 * creating false failures while keeping CI and normal sequential use unchanged.
 */
function koraAcquireDatabaseTestLock(string $scriptName): void
{
    static $lockHandle = null;

    if ($lockHandle !== null) {
        return;
    }

    $projectRoot = realpath(dirname(__DIR__));
    $lockId = hash('sha256', is_string($projectRoot) ? $projectRoot : dirname(__DIR__));
    $lockPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'koracms-db-tests-' . $lockId . '.lock';

    $handle = fopen($lockPath, 'c');
    if (!is_resource($handle)) {
        fwrite(STDERR, $scriptName . ": nepodařilo se otevřít lock soubor pro DB testy.\n");
        exit(1);
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        fwrite(STDERR, $scriptName . ": nepodařilo se získat lock pro DB testy.\n");
        exit(1);
    }

    $lockHandle = $handle;
    register_shutdown_function(static function () use (&$lockHandle): void {
        if (is_resource($lockHandle)) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
        $lockHandle = null;
    });
}
