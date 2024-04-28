<?php

namespace MintyPHP;

class Session
{
	public static $sessionId = false;
	public static $sessionName = 'mintyphp';
	public static $csrfSessionKey = 'csrf_token';
	public static $enabled = true;
	public static $csrfLength = 16;
	public static $sameSite = 'Lax';

	protected static $initialized = false;
	protected static $started = false;
	protected static $ended = false;

	protected static function initialize()
	{
		if (self::$initialized) {
			return;
		}

		self::$initialized = true;
		//if (session_module_name() == 'files') {
		//	session_set_save_handler(new FilesSessionHandler(), true);
		//}
		self::start();
		self::setCsrfToken();
	}

	protected static function setCsrfToken()
	{
		if (!self::$enabled) {
			return;
		}

		if (isset($_SESSION[self::$csrfSessionKey])) {
			return;
		}

		$buffer = random_bytes(self::$csrfLength);

		$_SESSION[self::$csrfSessionKey] = bin2hex($buffer);
	}

	public static function regenerate()
	{
		if (!self::$enabled) {
			return;
		}

		session_regenerate_id(true);
		unset($_SESSION[self::$csrfSessionKey]);
		self::setCsrfToken();
	}

	public static function start()
	{
		if (!self::$initialized) {
			self::initialize();
		}

		if (self::$started) {
			return;
		}

		if (self::$enabled || Debugger::$enabled) {
			if (!ini_get('session.cookie_samesite')) {
				ini_set('session.cookie_samesite', self::$sameSite);
			}
			if (!ini_get('session.cookie_httponly')) {
				ini_set('session.cookie_httponly', 1);
			}
			if (!ini_get('session.cookie_secure') && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
				ini_set('session.cookie_secure', 1);
			}
			//if (!ini_get('session.use_strict_mode')) {
			//	ini_set('session.use_strict_mode', 1);
			//}
			//if (!ini_get('session.lazy_write')) {
			//	ini_set('session.lazy_write', 1);
			//}
			session_name(self::$sessionName);
			if (self::$sessionId) {
				session_id(self::$sessionId);
			}
			session_start(/*['read_and_close' => $_SERVER['REQUEST_METHOD'] == 'GET']*/);
			if (!self::$enabled) {
				foreach ($_SESSION as $k => $v) {
					if ($k != Debugger::$sessionKey) {
						unset($_SESSION[$k]);
					}
				}
			}
		}
		self::$started = true;
		if (Debugger::$enabled) {
			if (!isset($_SESSION[Debugger::$sessionKey])) {
				$_SESSION[Debugger::$sessionKey] = [];
			}
			Debugger::logSession('before');
		}
	}

	public static function end()
	{
		if (!self::$initialized) {
			self::initialize();
		}

		if (self::$ended) {
			return;
		}

		self::$ended = true;
		if (self::$enabled && !Debugger::$enabled) {
			session_write_close();
		}

		if (Debugger::$enabled) {
			Debugger::logSession('after');
		}

		if (Debugger::$enabled) {
			$session = $_SESSION;
			unset($_SESSION);
			$_SESSION = $session;
		}
	}

	public static function checkCsrfToken()
	{
		if (!self::$initialized) {
			self::initialize();
		}

		if (!self::$enabled) {
			return true;
		}

		$success = false;
		if (isset($_POST[self::$csrfSessionKey])) {
			$success = $_POST[self::$csrfSessionKey] == $_SESSION[self::$csrfSessionKey];
			//unset($_POST['csrf_token']);
		}
		return $success;
	}

	public static function getCsrfInput()
	{
		if (!self::$initialized) {
			self::initialize();
		}

		if (!self::$enabled) {
			return;
		}

		self::setCsrfToken();
		echo '<input type="hidden" name="' . self::$csrfSessionKey . '" value="' . $_SESSION[self::$csrfSessionKey] . '"/>';
	}
}
