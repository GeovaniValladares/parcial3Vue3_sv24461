<?php

namespace Tests\Unit;

use App\Services\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    public function test_rfc_6238_vectors_with_six_digits(): void
    {
        $t = new Totp;
        $s = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        foreach ([[59, '287082'], [1111111109, '081804'], [1111111111, '050471'], [1234567890, '005924'], [2000000000, '279037']] as [$time,$code]) {
            $this->assertSame($code, $t->code($s, intdiv($time, 30)));
            $this->assertNotNull($t->verify($s, $code, null, $time));
        }
    }

    public function test_rejects_reuse_wrong_code_and_bad_format(): void
    {
        $t = new Totp;
        $s = $t->secret();
        $c = $t->code($s, 100);
        $this->assertNull($t->verify($s, $c, 100, 3000));
        $this->assertNull($t->verify($s, '123', null, 3000));
        $this->assertNull($t->verify($s, 'abcdef', null, 3000));
        $this->assertNull($t->verify($s, $t->code($s,95), null, 3000));
    }
}
