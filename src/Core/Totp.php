<?php

namespace MintyPHP\Core;

/**
 * Time-based One-Time Password (TOTP) implementation for MintyPHP.
 * 
 * Provides TOTP generation and verification functionality for two-factor authentication.
 * Supports customizable periods, algorithms, and digit lengths according to RFC 6238.
 */
class Totp
{
    /**
     * Static configuration parameters
     */
    public static int $__period = 30;
    public static string $__algorithm = 'sha1';
    public static int $__digits = 6;
    public static int $__secretLength = 10;

    /**
     * For testing purposes - allows overriding current timestamp
     */
    public int $timestamp = 0;

    /**
     * Constructor
     * 
     * @param int $period Time period in seconds for TOTP generation
     * @param string $algorithm Hash algorithm to use (sha1, sha256, sha512)
     * @param int $digits Number of digits in the OTP code
     * @param int $secretLength Length of the generated secret in bytes
     */
    public function __construct(private readonly int $period, private readonly string $algorithm, private readonly int $digits, private readonly int $secretLength)
    {
    }

    /**
     * Decode a Base32 encoded string
     * 
     * @param string $d Base32 encoded string
     * @return string Decoded binary string
     */
    private function decodeBase32(string $d): string
    {
        [$t, $b, $r] = ["ABCDEFGHIJKLMNOPQRSTUVWXYZ234567", "", ""];
        foreach (str_split($d) as $c) {
            $b .= sprintf("%05b", strpos($t, $c));
        }
        foreach (str_split($b, 8) as $c) {
            $r .= chr((int)bindec($c));
        }
        return ($r);
    }

    /**
     * Encode a string to Base32
     * 
     * @param string $d Binary string to encode
     * @return string Base32 encoded string
     */
    private function encodeBase32(string $d): string
    {
        [$t, $b, $r] = ["ABCDEFGHIJKLMNOPQRSTUVWXYZ234567", "", ""];
        foreach (str_split($d) as $c) {
            $b .= sprintf("%08b", ord($c));
        }
        foreach (str_split($b, 5) as $c) {
            $r .= $t[bindec($c)];
        }
        return ($r);
    }

    /**
     * Perform a timing-safe string comparison
     * 
     * @param string $s1 First string
     * @param string $s2 Second string
     * @return bool True if strings are equal
     */
    private function safeStringCompare(string $s1, string $s2): bool
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

    /**
     * Calculate OTP from HMAC hash
     * 
     * Implements the dynamic truncation algorithm from RFC 4226:
     * 1. Use the last 4 bits of the hash to determine an offset (0-15)
     * 2. Extract 4 bytes starting at that offset
     * 3. Clear the most significant bit to avoid sign issues
     * 4. Take modulo to get the desired number of digits
     * 5. Zero-pad the result to the configured digit length
     * 
     * @param string $hash HMAC hash value
     * @return string OTP code as a zero-padded string
     */
    private function calculateOtp(string $hash): string
    {
        // Extract offset from last nibble of hash (0-15)
        $lastByte = unpack('C', substr($hash, -1));
        $offset = ($lastByte ? $lastByte[1] : 0) & 0xF;
        // Extract 4 bytes at offset and clear the sign bit
        $fourBytes = unpack('N', substr($hash, $offset, 4));
        $code = ($fourBytes ? $fourBytes[1] : 0) & 0x7FFFFFFF;
        // Reduce to desired number of digits
        $otp = $code % (10 ** $this->digits);
        // Format with leading zeros
        return sprintf('%0' . $this->digits . 'd', $otp);
    }

    /**
     * Calculate HMAC hash for current time period
     * 
     * Implements the TOTP time-based counter from RFC 6238:
     * 1. Decode the Base32 encoded secret to binary
     * 2. Calculate the time counter (current time / period)
     * 3. Pack the counter as a 64-bit big-endian integer
     * 4. Compute HMAC-SHA using the secret as the key
     * 
     * @param string $secret Base32 encoded secret key
     * @return string HMAC hash value
     */
    private function calculateHash(string $secret): string
    {
        // Decode Base32 secret to binary format
        $secret = $this->decodeBase32($secret);
        // Calculate time counter and pack as 64-bit big-endian integer
        $data = pack('J', intval(($this->timestamp ?: time()) / $this->period));
        // Generate HMAC hash using configured algorithm
        $hash = hash_hmac($this->algorithm, $data, $secret, true);
        return $hash;
    }

    /**
     * Generate a random secret key
     * 
     * @return string Base32 encoded secret key
     */
    public function generateSecret(): string
    {
        if ($this->secretLength < 1) {
            return '';
        }
        return $this->encodeBase32(random_bytes($this->secretLength));
    }

    /**
     * Generate a TOTP URI for QR code generation
     * 
     * @param string $company Company/issuer name
     * @param string $username User identifier
     * @param string $secret Base32 encoded secret key
     * @return string TOTP URI in otpauth:// format
     */
    public function generateURI(string $company, string $username, string $secret): string
    {
        $defaults = [
            'period' => 30,
            'algorithm' => 'sha1',
            'digits' => 6,
        ];
        $current = [
            'period' => $this->period,
            'algorithm' => $this->algorithm,
            'issuer' => $company,
            'secret' => $secret,
            'digits' => $this->digits,
        ];
        $parameters = http_build_query(array_diff_assoc(array_filter($current), $defaults));
        $parameterString = str_replace(['+', '%7E'], ['%20', '~'], $parameters);
        return sprintf('otpauth://totp/%s?%s', rawurlencode("$company:$username"), $parameterString);
    }

    /**
     * Verify a TOTP code against a secret
     * 
     * @param string $secret Base32 encoded secret key
     * @param string $otp OTP code to verify
     * @return bool True if the OTP is valid for the current time period
     */
    public function verify(string $secret, string $otp): bool
    {
        if (!$secret) {
            return true;
        }
        $hash = $this->calculateHash($secret);
        $match = $this->calculateOtp($hash);
        return $this->safeStringCompare($otp, $match);
    }
}
