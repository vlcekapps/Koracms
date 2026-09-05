<?php

declare(strict_types=1);

// Deliberately no CMS bootstrap or database: only uniquely named, harmless files.
$baseUrl = rtrim((string)($argv[1] ?? (getenv('KORA_TEST_BASE_URL') ?: 'http://localhost')), '/');
$parts = parse_url($baseUrl);
if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
    || !in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true)
    || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
    fwrite(STDERR, "Usage: php build/rc_upload_http_selftest.php http://localhost[:port][/base]\n");
    exit(2);
}
$root = dirname(__DIR__);
$token = 'rc_media_probe_' . bin2hex(random_bytes(12));
$fixtures = [
    'forms/' . $token . '.png' => false,
    'backups/' . $token . '.png' => false,
    'gallery/' . $token . '.png' => false,
    'gallery/thumbs/' . $token . '.png' => false,
    'places/' . $token . '.png' => false,
    'podcasts/' . $token . '.mp3' => false,
    'podcasts/transcripts/' . $token . '.vtt' => false,
    'downloads/' . $token . '.pdf' => false,
    'downloads/images/' . $token . '.png' => true,
    'media/' . $token . '.html' => false,
    'media/' . $token . '_upper.HTML' => false,
    'media/' . $token . '.unknown' => false,
    'media/' . $token . '.txt' => false,
    'media/' . $token . '.png' => true,
    'media/thumbs/' . $token . '.webp' => true,
    'media/' . $token . '.vtt' => true,
    'media/' . $token . '.csv' => true,
];
$created = [];
$directories = [];
$checks = 0;
$failure = null;
try {
    foreach ($fixtures as $relative => $allowed) {
        $path = $root . '/uploads/' . $relative;
        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!is_dir(dirname($directory)) || !mkdir($directory, 0755)) {
                throw new RuntimeException('Cannot create fixture directory: ' . $directory);
            }
            $directories[] = $directory;
        }
        $stream = fopen($path, 'xb');
        if ($stream === false) {
            throw new RuntimeException('Cannot create unique fixture: ' . $path);
        }
        $created[] = $path;
        fwrite($stream, $token);
        fclose($stream);
        foreach (['GET', 'HEAD'] as $method) {
            $context = stream_context_create(['http' => [
                'method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10,
            ]]);
            $http_response_header = [];
            $body = file_get_contents($baseUrl . '/uploads/' . $relative, false, $context);
            preg_match('~^HTTP/\S+ (\d{3})~', $http_response_header[0] ?? '', $statusMatch);
            $status = (int)($statusMatch[1] ?? 0);
            if (($allowed && ($status !== 200 || ($method === 'GET' && $body !== $token)))
                || (!$allowed && !in_array($status, [403, 404], true))) {
                throw new RuntimeException($method . ' ' . $relative . ': unexpected HTTP ' . $status);
            }
            $checks++;
            echo $method . ' ' . $relative . ': ' . $status . "\n";
        }
    }
} catch (Throwable $exception) {
    $failure = $exception->getMessage();
} finally {
    foreach (array_reverse($created) as $path) {
        if (is_file($path) && !unlink($path)) {
            $failure = 'Cannot remove fixture: ' . $path;
        }
    }
    foreach (array_reverse($directories) as $directory) {
        if (!rmdir($directory)) {
            $failure = 'Cannot remove fixture directory: ' . $directory;
        }
    }
}
if ($failure !== null) {
    fwrite(STDERR, 'FAIL: ' . $failure . "\n");
    exit(1);
}
echo 'PASS: ' . $checks . " existing-file HTTP upload checks; fixtures removed.\n";
