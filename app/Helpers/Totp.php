<?php

namespace App\Helpers;

/**
 * RFC 6238 Time-based One-Time Password (TOTP) helper.
 * Pure PHP, no external dependencies.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a new random base32 secret (default 160-bit -> 32 chars).
     */
    public static function generateSecret(int $length = 32): string
    {
        $secret = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, $max)];
        }
        return $secret;
    }

    /**
     * Decode a base32 string to raw bytes.
     */
    public static function base32Decode(string $base32): string
    {
        $base32 = strtoupper(rtrim($base32, '='));
        $buffer = 0;
        $bits = 0;
        $output = '';
        foreach (str_split($base32) as $char) {
            $val = strpos(self::ALPHABET, $char);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bits += 5;
            if ($bits >= 8) {
                $output .= chr(($buffer >> ($bits - 8)) & 0xFF);
                $bits -= 8;
            }
        }
        return $output;
    }

    /**
     * Compute the TOTP code for a secret at a given time (unix seconds).
     */
    public static function code(string $secret, ?int $time = null, int $period = 30, int $digits = 6): string
    {
        if ($time === null) {
            $time = time();
        }
        $counter = intdiv($time, $period);
        $binCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binCounter, self::base32Decode($secret), true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        $mod = $binary % (10 ** $digits);
        return str_pad((string)$mod, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a submitted code against the secret, allowing a time window.
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d+$/', $code)) {
            return false;
        }
        $time = time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $time + ($i * 30)), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build the otpauth:// provisioning URI for QR code generation.
     */
    public static function provisioningUri(string $secret, string $label, string $issuer = 'PlexiQ LIMS'): string
    {
        $label = rawurlencode($label);
        $issuer = rawurlencode($issuer);
        return 'otpauth://totp/' . $issuer . ':' . $label
            . '?secret=' . $secret
            . '&issuer=' . $issuer
            . '&algorithm=SHA1&digits=6&period=30';
    }
}
