<?php

namespace MintyPHP\Core;

/**
 * JWT Token management class for generating and validating JSON Web Tokens.
 * 
 * Supports multiple algorithms (HS256, HS384, HS512, RS256, RS384, RS512) and
 * provides claim verification including audience, issuer, and expiration.
 */
class Token
{
    /**
     * Supported algorithms
     */
    public const array ALGORITHMS = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512',
        'RS256' => 'sha256',
        'RS384' => 'sha384',
        'RS512' => 'sha512',
    ];

    /**
     * Static configuration parameters
     */
    public static string $__algorithm = 'HS256';
    public static string $__secret = '';
    public static int $__leeway = 5; // 5 seconds
    public static int $__ttl = 30; // 1/2 minute
    public static string $__audience = '';
    public static string $__issuer = '';
    public static string $__algorithms = '';
    public static string $__audiences = '';
    public static string $__issuers = '';

    /**
     * Create a new Token instance
     */
    public function __construct(
        /**
         * Actual configuration parameters
         */
        private readonly string $algorithm,
        private readonly string $secret,
        private readonly int $leeway,
        private readonly int $ttl,
        private readonly string $audience = '',
        private readonly string $issuer = '',
        private readonly string $algorithms = '',
        private readonly string $audiences = '',
        private readonly string $issuers = '',
    ) {
        // throw on empty secret
        if (empty($this->secret)) {
            throw new \InvalidArgumentException('Token secret cannot be empty');
        }
    }

    /**
     * Verify the claims of a JWT token.
     * @param array<string, array<string>> $requirements
     * @return array<string, mixed>
     */
    private function getVerifiedClaims(string $token, int $time, int $leeway, int $ttl, string $secret, array $requirements): array
    {
        $tokenParts = explode('.', $token);
        if (count($tokenParts) < 3) {
            return [];
        }
        $headerJson = base64_decode(strtr($tokenParts[0], '-_', '+/'), true);
        if ($headerJson === false) {
            return [];
        }
        $header = json_decode($headerJson, true);
        if (!is_array($header)) {
            return [];
        }
        if (!$secret) {
            return [];
        }
        if (!isset($header['typ']) || !is_string($header['typ']) || $header['typ'] != 'JWT') {
            return [];
        }
        if (!isset($header['alg']) || !is_string($header['alg'])) {
            return [];
        }
        $algorithm = $header['alg'];
        if (!isset(self::ALGORITHMS[$algorithm])) {
            return [];
        }
        if (!empty($requirements['alg']) && !in_array($algorithm, $requirements['alg'])) {
            return [];
        }
        $hmac = self::ALGORITHMS[$algorithm];
        $signature = base64_decode(strtr($tokenParts[2], '-_', '+/'));
        $data = "$tokenParts[0].$tokenParts[1]";
        switch ($algorithm[0]) {
            case 'H':
                $hash = hash_hmac($hmac, $data, $secret, true);
                $equals = hash_equals($hash, $signature);
                if (!$equals) {
                    return [];
                }
                break;
            case 'R':
                $equals = openssl_verify($data, $signature, $secret, $hmac) == 1;
                if (!$equals) {
                    return [];
                }
                break;
        }
        $claimsBase64 = strtr($tokenParts[1], '-_', '+/');
        $claimsJson = base64_decode($claimsBase64, true);
        if ($claimsJson === false) {
            return [];
        }
        $claims = json_decode($claimsJson, true);
        if ($claims === null || !is_array($claims)) {
            return [];
        }
        foreach ($requirements as $field => $values) {
            if (!empty($values)) {
                if ($field != 'alg') {
                    if (!isset($claims[$field]) || !is_string($claims[$field]) || !in_array($claims[$field], $values)) {
                        return [];
                    }
                }
            }
        }
        if (isset($claims['nbf']) && is_int($claims['nbf']) && $time + $leeway < $claims['nbf']) {
            return [];
        }
        if (isset($claims['iat']) && is_int($claims['iat']) && $time + $leeway < $claims['iat']) {
            return [];
        }
        if (isset($claims['exp']) && is_int($claims['exp']) && $time - $leeway > $claims['exp']) {
            return [];
        }
        if (isset($claims['iat']) && is_int($claims['iat']) && !isset($claims['exp'])) {
            if ($time - $leeway > $claims['iat'] + $ttl) {
                return [];
            }
        }
        return $claims;
    }

    /**
     * Retrieve and verify claims from a JWT token.
     * @param string $token
     * @return array<string, mixed> 
     */
    public function getClaims(string $token): array
    {
        if (!$token) {
            return [];
        }
        $time = time();
        $leeway = $this->leeway;
        $ttl = $this->ttl;
        $secret = $this->secret;
        if (!$secret) {
            return [];
        }
        $requirements = [];
        $requirements['alg'] = array_filter(array_map('trim', explode(',', $this->algorithms)));
        $requirements['aud'] = array_filter(array_map('trim', explode(',', $this->audiences)));
        $requirements['iss'] = array_filter(array_map('trim', explode(',', $this->issuers)));
        return $this->getVerifiedClaims($token, $time, $leeway, $ttl, $secret, $requirements);
    }

    /**
     * Generate a JWT token.
     * @param array<string, mixed> $claims
     * @return string
     */
    private function generateToken(array $claims, int $time, int $ttl, string $algorithm, string $secret): string
    {
        $algorithms = [
            'HS256' => 'sha256',
            'HS384' => 'sha384',
            'HS512' => 'sha512',
            'RS256' => 'sha256',
            'RS384' => 'sha384',
            'RS512' => 'sha512',
        ];
        $header = [];
        $header['typ'] = 'JWT';
        $header['alg'] = $algorithm;
        $token = [];
        $token[0] = rtrim(strtr(base64_encode(json_encode((object) $header) ?: ''), '+/', '-_'), '=');
        $claims['iat'] = $time;
        $claims['exp'] = $time + $ttl;
        $token[1] = rtrim(strtr(base64_encode(json_encode((object) $claims) ?: ''), '+/', '-_'), '=');
        if (!isset($algorithms[$algorithm])) {
            return '';
        }
        $hmac = $algorithms[$algorithm];
        $data = "$token[0].$token[1]";
        switch ($algorithm[0]) {
            case 'H':
                $signature = hash_hmac($hmac, $data, $secret, true);
                break;
            case 'R':
                $signature = '';
                openssl_sign($data, $signature, $secret, $hmac);
                break;
            default:
                return '';
        }
        $token[2] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        return implode('.', $token);
    }

    /**
     * Generate a JWT token with the given claims.
     * @param array<string, mixed> $claims
     * @return string
     */
    public function getToken(array $claims): string
    {
        $time = time();
        $ttl = $this->ttl;
        $algorithm = $this->algorithm;
        $secret = $this->secret;
        if (!$secret) {
            return '';
        }
        if (!isset($claims['aud']) && $this->audience) {
            $claims['aud'] = $this->audience;
        }
        if (!isset($claims['iss']) && $this->issuer) {
            $claims['iss'] = $this->issuer;
        }
        return $this->generateToken($claims, $time, $ttl, $algorithm, $secret);
    }
}
