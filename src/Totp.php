<?php
declare (strict_types = 1);

namespace MintyPHP;

class Totp
{
    public static $period = 30;
    public static $algorithm = 'sha1';
    public static $digits = 6;
    public static $secretLength = 10;

    // for testing
    public static $timestamp = 0;

    private static function decodeBase32(string $d): string
    {
        list($t, $b, $r) = array("ABCDEFGHIJKLMNOPQRSTUVWXYZ234567", "", "");
        foreach (str_split($d) as $c) {
            $b = $b . sprintf("%05b", strpos($t, $c));
        }
        foreach (str_split($b, 8) as $c) {
            $r = $r . chr(bindec($c));
        }
        return ($r);
    }

    private static function encodeBase32(string $d): string
    {
        list($t, $b, $r) = array("ABCDEFGHIJKLMNOPQRSTUVWXYZ234567", "", "");
        foreach (str_split($d) as $c) {
            $b = $b . sprintf("%08b", ord($c));
        }
        foreach (str_split($b, 5) as $c) {
            $r = $r . $t[bindec($c)];
        }
        return ($r);
    }

    private static function safeStringCompare(string $s1, string $s2): bool
    {
        $len = strlen($s1);
        if ($len != strlen($s2)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < $len; $i++) {
            $sum |= ord($s1[$i]) ^ ord($s2[$i]);
        }
        return $sum == 0;
    }

    private static function calculateOtp(string $hash): string
    {
        $offset = unpack('C', substr($hash, -1))[1] & 0xF;
        $code = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
        $otp = $code % (10 ** static::$digits);
        return sprintf('%0' . static::$digits . 'd', $otp);
    }

    private static function calculateHash(string $secret): string
    {
        $secret = static::decodeBase32($secret);
        $data = pack('J', intval((static::$timestamp ?: time()) / static::$period));
        $hash = hash_hmac(static::$algorithm, $data, $secret, true);
        return $hash;
    }

    public static function generateSecret(): string
    {
        return static::encodeBase32(random_bytes(static::$secretLength));
    }

    public static function generateURI(string $company, string $username, string $secret): string
    {
        $defaults = [
            'period' => 30,
            'algorithm' => 'sha1',
            'digits' => 6,
        ];
        $current = [
            'period' => static::$period,
            'algorithm' => static::$algorithm,
            'digits' => static::$digits,
            'issuer' => $company,
            'secret' => $secret,
            'digits' => static::$digits,
        ];
        $parameters = http_build_query(array_diff_assoc(array_filter($current), $defaults));
        $parameterString = str_replace(['+', '%7E'], ['%20', '~'], $parameters);
        return sprintf('otpauth://totp/%s?%s', rawurlencode("$company:$username"), $parameterString);
    }

    public static function verify(string $secret, string $otp): bool
    {
        if (!$secret) {
            return true;
        }
        $hash = static::calculateHash($secret);
        $match = static::calculateOtp($hash);
        return static::safeStringCompare($otp, $match);
    }

}
