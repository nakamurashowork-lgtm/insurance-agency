<?php
declare(strict_types=1);

namespace App\Auth;

/**
 * RFC 6238 TOTP (Time-based One-Time Password) implementation.
 * No external dependencies — pure PHP.
 */
final class Totp
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const STEP    = 30;   // seconds per time step
    private const DIGITS  = 6;    // OTP length
    private const ALGO    = 'sha1';

    /**
     * Generate a random base32-encoded secret (20 bytes → 32 chars).
     */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /**
     * Build an otpauth:// URI for QR code generation.
     */
    public static function buildOtpAuthUri(string $base32Secret, string $issuer, string $accountName): string
    {
        $label  = rawurlencode($issuer . ':' . $accountName);
        $params = http_build_query([
            'secret'    => $base32Secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::STEP,
        ]);

        return 'otpauth://totp/' . $label . '?' . $params;
    }

    /**
     * Compute the TOTP code for a given secret and UNIX timestamp.
     */
    public static function compute(string $base32Secret, int $timestamp): string
    {
        $step        = (int) floor($timestamp / self::STEP);
        $stepBytes   = pack('NN', 0, $step);                           // big-endian 64-bit
        $secretBytes = self::base32Decode($base32Secret);
        $hmac        = hash_hmac(self::ALGO, $stepBytes, $secretBytes, true);

        $offset = ord($hmac[19]) & 0x0F;
        $code   = (
            ((ord($hmac[$offset])     & 0x7F) << 24) |
            ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
            ((ord($hmac[$offset + 2]) & 0xFF) <<  8) |
            ( ord($hmac[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a user-supplied code.
     * Accepts ±$window steps (default 1 = 90-second tolerance for clock drift).
     */
    public static function verify(string $base32Secret, string $code, int $timestamp, int $window = 1): bool
    {
        $code = trim($code);
        if (strlen($code) !== self::DIGITS || !ctype_digit($code)) {
            return false;
        }

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::compute($base32Secret, $timestamp + $i * self::STEP), $code)) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Base32 helpers (RFC 4648)
    // -------------------------------------------------------------------------

    private static function base32Encode(string $bytes): string
    {
        $alphabet   = self::BASE32_ALPHABET;
        $result     = '';
        $bitBuffer  = 0;
        $bitsLeft   = 0;

        foreach (str_split($bytes) as $byte) {
            $bitBuffer = ($bitBuffer << 8) | ord($byte);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $result   .= $alphabet[($bitBuffer >> $bitsLeft) & 0x1F];
            }
        }

        if ($bitsLeft > 0) {
            $result .= $alphabet[($bitBuffer << (5 - $bitsLeft)) & 0x1F];
        }

        return $result;
    }

    public static function base32Decode(string $base32): string
    {
        $alphabet  = self::BASE32_ALPHABET;
        $base32    = strtoupper(str_replace('=', '', trim($base32)));
        $result    = '';
        $bitBuffer = 0;
        $bitsLeft  = 0;

        foreach (str_split($base32) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }

            $bitBuffer = ($bitBuffer << 5) | $pos;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result   .= chr(($bitBuffer >> $bitsLeft) & 0xFF);
            }
        }

        return $result;
    }
}
