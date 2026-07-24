<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const KORA_APPMARKET_ATTESTATION_PREFIX = "KORA-APPMARKET-ATTESTATION-V1\n";
const KORA_APPMARKET_MANIFEST_MAX_BYTES = 65536;
const KORA_APPMARKET_RSA_BITS = 3072;

function appmarketAttestFail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

/**
 * @param array<string,mixed> $payload
 */
function appmarketAttestJson(array $payload): void
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        appmarketAttestFail('Výstup nástroje se nepodařilo zakódovat.');
    }
    fwrite(STDOUT, $json . PHP_EOL);
    exit(0);
}

function appmarketAttestRequireOpenSsl(): void
{
    if (!function_exists('openssl_pkey_new')
        || !function_exists('openssl_pkey_get_details')
        || !function_exists('openssl_sign')
        || !defined('OPENSSL_KEYTYPE_RSA')
        || !defined('OPENSSL_ALGO_SHA256')
    ) {
        appmarketAttestFail('Lokální PHP nemá rozšíření OpenSSL potřebné pro publisher klíč.');
    }
}

/**
 * @return array{private_key_bits:int,private_key_type:int,config?:string}
 */
function appmarketAttestKeyOptions(): array
{
    $options = [
        'private_key_bits' => KORA_APPMARKET_RSA_BITS,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];
    $candidates = [
        getenv('OPENSSL_CONF'),
        dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl'
            . DIRECTORY_SEPARATOR . 'openssl.cnf',
        dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'extras'
            . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
    ];
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate) && is_readable($candidate)) {
            $options['config'] = $candidate;
            break;
        }
    }

    return $options;
}

/**
 * @return array{private:mixed,public_key:string,fingerprint:string}
 */
function appmarketAttestLoadPrivateKey(string $path): array
{
    $resolved = realpath($path);
    if (!is_string($resolved) || !is_file($resolved) || !is_readable($resolved)) {
        appmarketAttestFail('Privátní publisher klíč nebyl nalezen nebo není čitelný.');
    }
    $pem = file_get_contents($resolved);
    if (!is_string($pem) || $pem === '' || strlen($pem) > 16384) {
        appmarketAttestFail('Privátní publisher klíč nemá platnou velikost.');
    }
    $privateKey = openssl_pkey_get_private($pem);
    if ($privateKey === false) {
        appmarketAttestFail('Privátní publisher klíč není platný PEM klíč.');
    }
    $details = openssl_pkey_get_details($privateKey);
    if (!is_array($details)
        || (int)($details['type'] ?? -1) !== OPENSSL_KEYTYPE_RSA
        || (int)($details['bits'] ?? 0) < KORA_APPMARKET_RSA_BITS
        || !is_string($details['key'] ?? null)
    ) {
        appmarketAttestFail('Publisher klíč musí být RSA o velikosti alespoň 3072 bitů.');
    }
    $publicKey = trim($details['key']) . "\n";
    $body = preg_replace(
        '/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/',
        '',
        $publicKey
    );
    $der = is_string($body) ? base64_decode($body, true) : false;
    if (!is_string($der) || $der === '') {
        appmarketAttestFail('Z publisher klíče se nepodařilo odvodit veřejný otisk.');
    }

    return [
        'private' => $privateKey,
        'public_key' => $publicKey,
        'fingerprint' => hash('sha256', $der),
    ];
}

appmarketAttestRequireOpenSsl();

$command = strtolower(trim((string)($argv[1] ?? '')));
if ($command === 'generate') {
    $privatePath = trim((string)($argv[2] ?? ''));
    $publicPath = trim((string)($argv[3] ?? ''));
    if ($privatePath === '' || $publicPath === '') {
        appmarketAttestFail(
            'Použití: php tools/appmarket-attest.php generate C:\cesta\publisher-private.pem C:\cesta\publisher-public.pem'
        );
    }
    if (file_exists($privatePath) || file_exists($publicPath)) {
        appmarketAttestFail('Cílový soubor klíče už existuje; nástroj jej nepřepíše.');
    }
    foreach ([$privatePath, $publicPath] as $targetPath) {
        $directory = dirname($targetPath);
        if (!is_dir($directory) || !is_writable($directory)) {
            appmarketAttestFail('Cílový adresář klíče neexistuje nebo do něj nelze zapisovat.');
        }
    }

    $keyOptions = appmarketAttestKeyOptions();
    $privateKey = openssl_pkey_new($keyOptions);
    $privatePem = '';
    if ($privateKey === false || !openssl_pkey_export($privateKey, $privatePem, null, $keyOptions)) {
        appmarketAttestFail('Publisher klíč se nepodařilo vygenerovat.');
    }
    $details = openssl_pkey_get_details($privateKey);
    if (!is_array($details) || !is_string($details['key'] ?? null)) {
        appmarketAttestFail('Z publisher klíče se nepodařilo získat veřejnou část.');
    }
    $publicPem = trim($details['key']) . "\n";
    if (file_put_contents($privatePath, $privatePem, LOCK_EX) === false
        || file_put_contents($publicPath, $publicPem, LOCK_EX) === false
    ) {
        if (is_file($privatePath)) {
            unlink($privatePath);
        }
        if (is_file($publicPath)) {
            unlink($publicPath);
        }
        appmarketAttestFail('Publisher klíče se nepodařilo bezpečně uložit.');
    }
    @chmod($privatePath, 0600);
    @chmod($publicPath, 0644);
    $loaded = appmarketAttestLoadPrivateKey($privatePath);
    appmarketAttestJson([
        'algorithm' => 'rsa-sha256',
        'key_fingerprint_sha256' => $loaded['fingerprint'],
        'public_key_path' => realpath($publicPath) ?: $publicPath,
        'private_key_path' => realpath($privatePath) ?: $privatePath,
    ]);
}

if ($command === 'fingerprint') {
    $loaded = appmarketAttestLoadPrivateKey(trim((string)($argv[2] ?? '')));
    appmarketAttestJson([
        'algorithm' => 'rsa-sha256',
        'key_fingerprint_sha256' => $loaded['fingerprint'],
        'public_key_pem' => $loaded['public_key'],
    ]);
}

if ($command === 'sign') {
    $loaded = appmarketAttestLoadPrivateKey(trim((string)($argv[2] ?? '')));
    $manifestPath = realpath(trim((string)($argv[3] ?? '')));
    if (!is_string($manifestPath) || !is_file($manifestPath) || !is_readable($manifestPath)) {
        appmarketAttestFail('Manifest k podpisu nebyl nalezen nebo není čitelný.');
    }
    $manifest = file_get_contents($manifestPath);
    if (!is_string($manifest)
        || $manifest === ''
        || strlen($manifest) > KORA_APPMARKET_MANIFEST_MAX_BYTES
    ) {
        appmarketAttestFail('Manifest k podpisu je prázdný nebo překračuje 64 KiB.');
    }
    $signature = '';
    if (!openssl_sign(
        KORA_APPMARKET_ATTESTATION_PREFIX . $manifest,
        $signature,
        $loaded['private'],
        OPENSSL_ALGO_SHA256
    )) {
        appmarketAttestFail('Publisher manifest se nepodařilo podepsat.');
    }
    appmarketAttestJson([
        'algorithm' => 'rsa-sha256',
        'key_fingerprint_sha256' => $loaded['fingerprint'],
        'signature_base64' => base64_encode($signature),
    ]);
}

appmarketAttestFail(
    'Použití: generate <private.pem> <public.pem>, fingerprint <private.pem> nebo sign <private.pem> <release.json>.'
);
