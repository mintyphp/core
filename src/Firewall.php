<?php

namespace MintyPHP;

class Firewall
{
	public static $concurrency = 10;
	public static $spinLockSeconds = 0.15;
	public static $intervalSeconds = 300;
	public static $cachePrefix = 'fw_concurrency_';
	public static $reverseProxy = false;

	protected static $key = false;

	protected static function getClientIp()
	{
		if (self::$reverseProxy && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip = array_pop($ips);
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}

	protected static function getKey()
	{
		if (!self::$key) {
			self::$key = self::$cachePrefix . '_' . self::getClientIp();
		}
		return self::$key;
	}

	public static function start()
	{
		header_remove('X-Powered-By');
		$key = self::getKey();
		$start = microtime(true);
		Cache::add($key, 0, self::$intervalSeconds);
		register_shutdown_function('MintyPHP\\Firewall::end');
		while (Cache::increment($key) > self::$concurrency) {
			Cache::decrement($key);
			if (!self::$spinLockSeconds || microtime(true) - $start > self::$intervalSeconds) {
				http_response_code(429);
				die('429: Too Many Requests');
			}
			usleep(self::$spinLockSeconds * 1000000);
		}
	}

	public static function end()
	{
		Cache::decrement(self::getKey());
	}
}
