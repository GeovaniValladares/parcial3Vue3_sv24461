<?php

namespace App\Services;

/** RFC 6238: HMAC-SHA1, 30 seconds, six digits. */
class Totp
{
    public function secret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        for ($i = 0; $i < 32; $i++) {
            $out .= $alphabet[random_int(0, 31)];
        }

return $out;
    }

    private function decode(string $secret): string
    {
        $bits = '';
        foreach (str_split($secret) as $char) {
            $index = strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $char);
            if ($index === false) {
                throw new \InvalidArgumentException('Invalid Base32');
            }$bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }$bytes = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }

return $bytes;
    }

    public function code(string $secret, int $counter): string
    {
        $hash = hash_hmac('sha1', pack('N2', intdiv($counter, 4294967296), $counter % 4294967296), $this->decode($secret), true);
        $offset = ord($hash[19]) & 15;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function verify(string $secret, string $code, ?int $lastCounter = null, ?int $timestamp = null): ?int
    {
        if (! preg_match('/^\d{6}$/D', $code)) {
            return null;
        }$current = intdiv($timestamp ?? time(), 30);
        foreach ([$current, $current - 1, $current + 1] as $counter) {
            if (($lastCounter === null || $counter > $lastCounter) && hash_equals($this->code($secret, $counter), $code)) {
                return $counter;
            }
        }

return null;
    }

    public function uri(string $secret, string $email): string
    {
        return 'otpauth://totp/'.rawurlencode('ULS:'.$email).'?'.http_build_query(['secret' => $secret, 'issuer' => 'ULS', 'algorithm' => 'SHA1', 'digits' => 6, 'period' => 30], '', '&', PHP_QUERY_RFC3986);
    }
}
