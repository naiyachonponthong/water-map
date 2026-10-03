<?php

namespace App\Support;

/**
 * รหัสผ่านใช้ครั้งเดียวตามเวลา (TOTP, RFC 6238) ใช้กับ Google Authenticator / Microsoft Authenticator
 * เขียนเองไม่พึ่ง package: HMAC-SHA1, 30 วินาที, 6 หลัก
 */
class Totp
{
    public const PERIOD = 30;

    public const DIGITS = 6;

    protected const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return static::base32Encode(random_bytes($bytes));
    }

    public static function step(?int $time = null): int
    {
        return intdiv($time ?? time(), self::PERIOD);
    }

    public static function code(string $secret, int $step): string
    {
        $key = static::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N2', $step >> 32 & 0xFFFFFFFF, $step & 0xFFFFFFFF), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $bin = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($bin % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * ตรวจรหัส ยอมให้นาฬิกาเหลื่อม ±1 ช่วง และกันใช้รหัสเดิมซ้ำ (ส่ง step ที่ใช้ล่าสุดมา)
     *
     * @return int|null step ที่ตรง (เก็บไว้กันใช้ซ้ำ) หรือ null ถ้าไม่ถูก
     */
    public static function verify(string $secret, string $code, ?int $lastStep = null, int $window = 1, ?int $time = null): ?int
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) {
            return null;
        }
        $now = static::step($time);
        for ($i = -$window; $i <= $window; $i++) {
            $s = $now + $i;
            if ($lastStep !== null && $s <= $lastStep) {
                continue;
            }
            if (hash_equals(static::code($secret, $s), $code)) {
                return $s;
            }
        }

        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer.':'.$account);

        return 'otpauth://totp/'.$label.'?'.http_build_query(['secret' => $secret, 'issuer' => $issuer, 'digits' => self::DIGITS, 'period' => self::PERIOD]);
    }

    /** รหัสสำรอง 8 ชุด รูปแบบ xxxxxx-xxxxxx (hex) */
    public static function recoveryCodes(int $n = 8): array
    {
        return collect(range(1, $n))->map(fn () => strtolower(bin2hex(random_bytes(3)).'-'.bin2hex(random_bytes(3))))->all();
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
        $bits = '';
        foreach (str_split($b32) as $c) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
