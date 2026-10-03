<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** ค่าทดสอบจาก RFC 6238 (SHA1, ตัด 6 หลักท้าย) */
    public function test_rfc6238_vectors(): void
    {
        $secret = Totp::base32Encode('12345678901234567890');
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret);
        foreach ([59 => '287082', 1111111109 => '081804', 1234567890 => '005924', 2000000000 => '279037'] as $t => $code) {
            $this->assertSame($code, Totp::code($secret, Totp::step($t)));
        }
    }

    public function test_verify_window_and_replay(): void
    {
        $secret = Totp::generateSecret();
        $t = 1_800_000_000;
        $prev = Totp::code($secret, Totp::step($t) - 1);
        $this->assertSame(Totp::step($t) - 1, Totp::verify($secret, $prev, null, 1, $t));
        $this->assertNull(Totp::verify($secret, $prev, Totp::step($t) - 1, 1, $t), 'รหัสเดิมใช้ซ้ำไม่ได้');
        $this->assertNull(Totp::verify($secret, Totp::code($secret, Totp::step($t) - 3), null, 1, $t), 'เก่าเกินช่วงที่ยอม');
        $this->assertNull(Totp::verify($secret, '12345', null, 1, $t));
        $this->assertSame(Totp::base32Decode(Totp::base32Encode('abc')), 'abc');
        $this->assertCount(8, array_unique(Totp::recoveryCodes()));
    }
}
