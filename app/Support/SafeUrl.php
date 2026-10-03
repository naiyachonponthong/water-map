<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ดึง URL ภายนอกที่ผู้ดูแลตั้งค่าไว้ โดยกันไม่ให้ยิงเข้าเครือข่ายภายใน (SSRF)
 * อนุญาตเฉพาะ http/https ไปยัง host ที่ resolve เป็น IP สาธารณะ ทุกครั้งที่ redirect ก็ตรวจซ้ำ
 */
class SafeUrl
{
    public static function isAllowed(?string $url): bool
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);
        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $host = $parts['host'] ?? '';
        if ($host === '' || in_array(strtolower($host), ['localhost'], true)) {
            return false;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (! $ips) {
            return false;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    public static function get(string $url, int $timeout = 10, array $query = []): Response
    {
        if (! static::isAllowed($url)) {
            throw new RuntimeException('URL นี้ไม่อนุญาต (ต้องเป็น http/https ไปยังเซิร์ฟเวอร์สาธารณะ)');
        }

        return Http::timeout($timeout)->acceptJson()->withOptions(['allow_redirects' => [
            'max' => 3,
            'protocols' => ['http', 'https'],
            'on_redirect' => function ($req, $res, $uri) {
                if (! static::isAllowed((string) $uri)) {
                    throw new RuntimeException('redirect ไปยังปลายทางที่ไม่อนุญาต');
                }
            },
        ]])->get($url, $query);
    }
}
