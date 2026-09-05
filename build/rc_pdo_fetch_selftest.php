<?php

declare(strict_types=1);

// Repeat isolated SQLite regressions with numeric-string results, as returned
// by older PDO drivers. Application helpers must accept both fetch modes.
$environment = getenv();
$environment['KORA_RC_STRINGIFY_FETCHES'] = '1';
$failed = false;
foreach ([
    'rate_limit_expiry_selftest.php',
    'rc_legacy_auth_selftest.php',
    'rc_reservations_selftest.php',
    'rc_module_state_selftest.php',
    'rc_editor_publication_selftest.php',
    'rc_media_security_selftest.php',
] as $filename) {
    echo 'RC PDO numeric-string mode: ' . $filename . PHP_EOL;
    $process = proc_open(
        [PHP_BINARY, __DIR__ . '/' . $filename],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__),
        $environment
    );
    if (!is_resource($process)) {
        fwrite(STDERR, 'Cannot start isolated PDO regression: ' . $filename . PHP_EOL);
        $failed = true;
        continue;
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errorOutput = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    echo $output;
    if ($errorOutput !== '') {
        fwrite(STDERR, $errorOutput);
    }
    if (proc_close($process) !== 0) {
        $failed = true;
    }
}
exit($failed ? 1 : 0);
