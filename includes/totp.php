<?php
declare(strict_types=1);

/**
 * TOTP (RFC 6238) — без внешних зависимостей.
 */

const TOTP_PERIOD = 30;
const TOTP_DIGITS = 6;
const TOTP_ALGORITHM = 'sha1';

function totp_base32_alphabet(): string
{
    return 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
}

function totp_base32_encode(string $data): string
{
    if ($data === '') {
        return '';
    }
    $alphabet = totp_base32_alphabet();
    $bits = '';
    $len = strlen($data);
    for ($i = 0; $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }
        $out .= $alphabet[bindec($chunk)];
    }
    return $out;
}

function totp_base32_decode(string $encoded): string
{
    $encoded = strtoupper(preg_replace('/\s+/', '', $encoded) ?? '');
    if ($encoded === '') {
        return '';
    }
    $alphabet = totp_base32_alphabet();
    $bits = '';
    $len = strlen($encoded);
    for ($i = 0; $i < $len; $i++) {
        $pos = strpos($alphabet, $encoded[$i]);
        if ($pos === false) {
            return '';
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) < 8) {
            break;
        }
        $out .= chr(bindec($chunk));
    }
    return $out;
}

function totp_generate_secret(int $bytes = 20): string
{
    return totp_base32_encode(random_bytes($bytes));
}

function totp_counter(?int $time = null): int
{
    return intdiv($time ?? time(), TOTP_PERIOD);
}

function totp_get_code(string $secret, ?int $counter = null): string
{
    $key = totp_base32_decode($secret);
    if ($key === '') {
        return '';
    }
    $counter = $counter ?? totp_counter();
    $binary = pack('N*', 0, $counter);
    $hash = hash_hmac(TOTP_ALGORITHM, $binary, $key, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $truncated = (
        ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF)
    );
    $mod = 10 ** TOTP_DIGITS;

    return str_pad((string) ($truncated % $mod), TOTP_DIGITS, '0', STR_PAD_LEFT);
}

function totp_verify(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\s+/', '', $code) ?? '';
    if (!preg_match('/^\d{' . TOTP_DIGITS . '}$/', $code)) {
        return false;
    }
    $base = totp_counter();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_get_code($secret, $base + $i), $code)) {
            return true;
        }
    }

    return false;
}

function totp_provisioning_uri(string $secret, string $account, string $issuer): string
{
    $label = $issuer !== '' ? $issuer . ':' . $account : $account;
    $query = http_build_query([
        'secret' => $secret,
        'issuer' => $issuer,
        'algorithm' => strtoupper(TOTP_ALGORITHM),
        'digits' => TOTP_DIGITS,
        'period' => TOTP_PERIOD,
    ], '', '&', PHP_QUERY_RFC3986);

    return 'otpauth://totp/' . rawurlencode($label) . '?' . $query;
}

function totp_format_secret(string $secret): string
{
    return trim(chunk_split(strtoupper($secret), 4, ' '));
}

function totp_qr_data_uri(string $secret, string $username): ?string
{
    if (!function_exists('images_gd_available')) {
        require_once ROOT_PATH . '/includes/images.php';
    }
    if (!images_gd_available()) {
        return null;
    }

    require_once ROOT_PATH . '/vendor/qrcode.php';

    $uri = totp_provisioning_uri($secret, $username, site_title());
    $generator = new QRCode($uri, [
        's' => 'qrl',
        'sf' => 5,
        'p' => 1,
        'bc' => 'FFFFFF',
        'fc' => '324047',
    ]);

    $image = $generator->render_image();
    if (!$image) {
        return null;
    }

    ob_start();
    imagepng($image);
    imagedestroy($image);
    $png = ob_get_clean();
    if ($png === false || $png === '') {
        return null;
    }

    return 'data:image/png;base64,' . base64_encode($png);
}
