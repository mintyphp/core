<?php

namespace MintyPHP;

class Flash
{
	static $flashSessionKey = 'flash';

	public static function set($type, $message)
	{
		if (!isset($_SESSION[self::$flashSessionKey])) $_SESSION[self::$flashSessionKey] = [];
		$_SESSION[self::$flashSessionKey][$type] = $message;
	}

	public static function get()
	{
		if (isset($_SESSION[self::$flashSessionKey])) {
			$flash = $_SESSION[self::$flashSessionKey];
			unset($_SESSION[self::$flashSessionKey]);
		} else {
			$flash = [];
		}
		return $flash;
	}
}
