<?php

namespace MintyPHP;

class Token
{
	public static $algorithm = 'HS256';
	public static $secret = false;
	public static $leeway = 5; // 5 seconds
	public static $ttl = 30; // 1/2 minute
	public static $audience = false;
	public static $issuer = false;
	public static $algorithms = '';
	public static $audiences = '';
	public static $issuers = '';

	protected static $cache = null;

	protected static function getVerifiedClaims($token, $time, $leeway, $ttl, $secret, $requirements)
	{
		$algorithms = array(
			'HS256' => 'sha256',
			'HS384' => 'sha384',
			'HS512' => 'sha512',
			'RS256' => 'sha256',
			'RS384' => 'sha384',
			'RS512' => 'sha512',
		);
		$token = explode('.', $token);
		if (count($token) < 3) {
			return false;
		}
		$header = json_decode(base64_decode(strtr($token[0], '-_', '+/')), true);
		if (!$secret) {
			return false;
		}
		if ($header['typ'] != 'JWT') {
			return false;
		}
		$algorithm = $header['alg'];
		if (!isset($algorithms[$algorithm])) {
			return false;
		}
		if (!empty($requirements['alg']) && !in_array($algorithm, $requirements['alg'])) {
			return false;
		}
		$hmac = $algorithms[$algorithm];
		$signature = base64_decode(strtr($token[2], '-_', '+/'));
		$data = "$token[0].$token[1]";
		switch ($algorithm[0]) {
			case 'H':
				$hash = hash_hmac($hmac, $data, $secret, true);
				$equals = hash_equals($hash, $signature);
				if (!$equals) {
					return false;
				}
				break;
			case 'R':
				$equals = openssl_verify($data, $signature, $secret, $hmac) == 1;
				if (!$equals) {
					return false;
				}
				break;
		}
		$claims = json_decode(base64_decode(strtr($token[1], '-_', '+/')), true);
		if (!$claims) {
			return false;
		}
		foreach ($requirements as $field => $values) {
			if (!empty($values)) {
				if ($field != 'alg') {
					if (!isset($claims[$field]) || !in_array($claims[$field], $values)) {
						return false;
					}
				}
			}
		}
		if (isset($claims['nbf']) && $time + $leeway < $claims['nbf']) {
			return false;
		}
		if (isset($claims['iat']) && $time + $leeway < $claims['iat']) {
			return false;
		}
		if (isset($claims['exp']) && $time - $leeway > $claims['exp']) {
			return false;
		}
		if (isset($claims['iat']) && !isset($claims['exp'])) {
			if ($time - $leeway > intval($claims['iat']) + $ttl) {
				return false;
			}
		}
		return $claims;
	}

	public static function getClaims($token)
	{
		if (!$token) {
			return false;
		}
		$time = time();
		$leeway = self::$leeway;
		$ttl = self::$ttl;
		$secret = self::$secret;
		$requirements = [];
		$requirements['alg'] = array_filter(array_map('trim', explode(',', self::$algorithms)));
		$requirements['aud'] = array_filter(array_map('trim', explode(',', self::$audiences)));
		$requirements['iss'] = array_filter(array_map('trim', explode(',', self::$issuers)));
		return self::getVerifiedClaims($token, $time, $leeway, $ttl, $secret, $requirements);
	}

	protected static function generateToken($claims, $time, $ttl, $algorithm, $secret)
	{
		$algorithms = array(
			'HS256' => 'sha256',
			'HS384' => 'sha384',
			'HS512' => 'sha512',
			'RS256' => 'sha256',
			'RS384' => 'sha384',
			'RS512' => 'sha512',
		);
		$header = [];
		$header['typ'] = 'JWT';
		$header['alg'] = $algorithm;
		$token = [];
		$token[0] = rtrim(strtr(base64_encode(json_encode((object) $header)), '+/', '-_'), '=');
		$claims['iat'] = $time;
		$claims['exp'] = $time + $ttl;
		$token[1] = rtrim(strtr(base64_encode(json_encode((object) $claims)), '+/', '-_'), '=');
		if (!isset($algorithms[$algorithm])) {
			return false;
		}
		$hmac = $algorithms[$algorithm];
		$data = "$token[0].$token[1]";
		switch ($algorithm[0]) {
			case 'H':
				$signature = hash_hmac($hmac, $data, $secret, true);
				break;
			case 'R':
				$signature = (openssl_sign($data, $signature, $secret, $hmac) ? $signature : '');
				break;
			default:
				return false;
		}
		$token[2] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
		return implode('.', $token);
	}

	public static function getToken($claims)
	{
		$time = time();
		$ttl = self::$ttl;
		$algorithm = self::$algorithm;
		$secret = self::$secret;
		if (!isset($claims['aud']) && self::$audience) {
			$claims['aud'] = self::$audience;
		}
		if (!isset($claims['iss']) && self::$issuer) {
			$claims['iss'] = self::$issuer;
		}
		return self::generateToken($claims, $time, $ttl, $algorithm, $secret);
	}
}
