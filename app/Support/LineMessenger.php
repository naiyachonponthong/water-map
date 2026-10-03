<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * LINE Messaging API ของ LINE OA แต่ละจังหวัด (token/secret เก็บในตั้งค่าแบบเข้ารหัส)
 */
class LineMessenger
{
    public const API = 'https://api.line.me/v2/bot';

    public static function token(int $provinceId): ?string
    {
        return static::secretSetting('line_oa_token', $provinceId);
    }

    public static function channelSecret(int $provinceId): ?string
    {
        return static::secretSetting('line_oa_secret', $provinceId);
    }

    public static function configured(int $provinceId): bool
    {
        return filled(static::token($provinceId));
    }

    public static function saveSecret(string $key, ?string $value, int $provinceId): void
    {
        Settings::set($key, filled($value) ? Crypt::encryptString($value) : null, $provinceId);
    }

    protected static function secretSetting(string $key, int $provinceId): ?string
    {
        $v = Settings::get($key, $provinceId);
        if (! $v) {
            return null;
        }
        try {
            return Crypt::decryptString($v);
        } catch (Throwable) {
            return null;
        }
    }

    /** ส่งหาผู้ติดตาม LINE OA ทุกคน */
    public function broadcast(int $provinceId, array $messages): void
    {
        $this->post($provinceId, '/message/broadcast', ['messages' => $messages]);
    }

    /** ส่งหาผู้ใช้ที่ระบุ (ครั้งละไม่เกิน 500) */
    public function multicast(int $provinceId, array $userIds, array $messages): int
    {
        $n = 0;
        foreach (array_chunk(array_values(array_unique(array_filter($userIds))), 500) as $chunk) {
            $this->post($provinceId, '/message/multicast', ['to' => $chunk, 'messages' => $messages]);
            $n += count($chunk);
        }

        return $n;
    }

    public function reply(int $provinceId, string $replyToken, array $messages): void
    {
        $this->post($provinceId, '/message/reply', ['replyToken' => $replyToken, 'messages' => $messages]);
    }

    /** ตรวจลายเซ็น webhook: base64(HMAC-SHA256(channel secret, body)) */
    public static function verify(int $provinceId, string $body, ?string $signature): bool
    {
        $secret = static::channelSecret($provinceId);
        if (! $secret || ! $signature) {
            return false;
        }

        return hash_equals(base64_encode(hash_hmac('sha256', $body, $secret, true)), $signature);
    }

    public static function text(string $text): array
    {
        return ['type' => 'text', 'text' => mb_substr($text, 0, 4900)];
    }

    protected function post(int $provinceId, string $path, array $payload): void
    {
        $token = static::token($provinceId);
        if (! $token) {
            throw new RuntimeException('ยังไม่ได้ตั้งค่า LINE OA');
        }
        $res = Http::timeout(10)->withToken($token)->acceptJson()->post(self::API.$path, $payload);
        if (! $res->successful()) {
            throw new RuntimeException('LINE ตอบกลับ '.$res->status().': '.mb_substr((string) ($res->json('message') ?? $res->body()), 0, 150));
        }
    }
}
