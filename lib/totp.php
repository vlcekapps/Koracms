<?php
/**
 * TOTP (Time-based One-Time Password) – RFC 6238 implementace.
 * Kompatibilní s FreeOTP, Authy, Google Authenticator a dalšími.
 * Žádné externí závislosti.
 */

/**
 * Vygeneruje náhodný TOTP secret (base32).
 */
function totpGenerateSecret(int $length = 20): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    $bytes = random_bytes($length);
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[ord($bytes[$i]) % 32];
    }
    return $secret;
}

/**
 * Dekóduje base32 řetězec na binární data.
 */
function base32Decode(string $input): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(rtrim($input, '='));
    $buffer = 0;
    $bitsLeft = 0;
    $output = '';

    for ($i = 0, $len = strlen($input); $i < $len; $i++) {
        $val = strpos($alphabet, $input[$i]);
        if ($val === false) {
            continue;
        }
        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;
        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $output .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }
    return $output;
}

/**
 * Vypočítá TOTP kód pro daný secret a čas.
 */
function totpCalculate(string $secret, ?int $timestamp = null, int $digits = 6, int $period = 30): string
{
    $timestamp = $timestamp ?? time();
    $counter = intdiv($timestamp, $period);

    $key = base32Decode($secret);
    $time = pack('N*', 0, $counter);

    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $code = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % (10 ** $digits);

    return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
}

/**
 * Ověří TOTP kód (s tolerancí ±1 perioda).
 */
function totpVerify(string $secret, string $code, int $window = 1): bool
{
    $code = trim($code);
    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totpCalculate($secret, $now + $i * 30), $code)) {
            return true;
        }
    }
    return false;
}

/**
 * Vrátí otpauth:// URI pro QR kód.
 */
function totpUri(string $secret, string $email, string $issuer = 'Kora CMS'): string
{
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($email)
         . '?secret=' . rawurlencode($secret)
         . '&issuer=' . rawurlencode($issuer)
         . '&digits=6&period=30&algorithm=SHA1';
}

/**
 * Vrátí URL pro QR kód obrázek (přes Google Charts API – veřejné).
 */
function totpQrUrl(string $uri): string
{
    return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . rawurlencode($uri);
}

/**
 * Zkontroluje, zda uživatel má aktivní 2FA.
 */
function userHas2FA(array $user): bool
{
    return !empty($user['totp_secret']);
}

/**
 * Zkontroluje, zda uživatel má registrované passkey.
 */
function userHasPasskey(array $user): bool
{
    $creds = $user['passkey_credentials'] ?? '';
    if ($creds === '' || $creds === '[]') {
        return false;
    }
    $decoded = json_decode($creds, true);
    return is_array($decoded) && count($decoded) > 0;
}
