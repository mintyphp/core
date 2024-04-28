<?php

namespace MintyPHP;

class NoPassAuth
{
	static $usersTable = 'users';
	static $usernameField = 'username';
	static $passwordField = 'password';
	static $rememberTokenField = 'remember_token';
	static $rememberExpiresField = 'remember_expires';
	static $createdField = 'created';
	static $totpSecretField = 'totp_secret';
	static $tokenValidity = 300;
	static $rememberDays = 90;

	public static function token(string $username)
	{
		$query = sprintf(
			'select * from `%s` where `%s` = ? limit 1',
			self::$usersTable,
			self::$usernameField
		);
		$user = DB::selectOne($query, $username);
		if ($user) {
			$table = self::$usersTable;
			$username = $user[$table][self::$usernameField];
			$password = $user[$table][self::$passwordField];
			Token::$secret = $password;
			Token::$ttl = self::$tokenValidity;
			$token = Token::getToken(array('user' => $username, 'ip' => $_SERVER['REMOTE_ADDR']));
		} else {
			$token = '';
		}
		return $token;
	}

	public static function remember()
	{
		$name = Session::$sessionName . '_remember';
		$value = $_COOKIE[$name];
		$username = explode(':', $value, 2)[0];
		$token = explode(':', $value, 2)[1] ?? '';
		$query = sprintf(
			'select * from `%s` where `%s` = ? and `%s` > NOW() limit 1',
			self::$usersTable,
			self::$usernameField,
			self::$rememberExpiresField
		);
		$user = DB::selectOne($query, $username);
		if ($user) {
			$table = self::$usersTable;
			$username = $user[$table][self::$usernameField];
			$hash = $user[$table][self::$rememberTokenField];
			if (password_verify($token, $hash)) {
				session_regenerate_id(true);
				$_SESSION['user'] = $user[$table];
				return true;
			}
		}
		return false;
	}

	private static function unRemember()
	{
		$name = Session::$sessionName . '_remember';
		if (isset($_COOKIE[$name])) {
			setcookie($name, '');
		}
	}

	private static function doRemember(string $username)
	{
		$name = Session::$sessionName . '_remember';
		$token = base64_encode(random_bytes(24));
		$hash = password_hash($token, PASSWORD_DEFAULT);
		$query = sprintf(
			'update `%s` set `%s` = ?, `%s` = DATE_ADD(NOW(), INTERVAL ? DAY) where `%s` = ? limit 1',
			self::$usersTable,
			self::$rememberTokenField,
			self::$rememberExpiresField,
			self::$usernameField
		);
		DB::update($query, $hash, self::$rememberDays, $username);
		$value = "$username:$token";
		$expires = strtotime('+' . self::$rememberDays . ' days');
		$path = Router::$baseUrl;
		$domain = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
		if (!$domain || $domain == 'localhost') {
			setcookie($name, $value, $expires, $path);
		} else {
			setcookie($name, $value, $expires, $path, $domain, true, true);
		}
	}

	public static function login(string $token, bool $rememberMe = false, string $totp = null)
	{
		$parts = explode('.', $token);
		$claims = isset($parts[1]) ? json_decode(base64_decode($parts[1]), true) : false;
		$username = isset($claims['user']) ? $claims['user'] : false;
		$query = sprintf(
			'select * from `%s` where `%s` = ? limit 1',
			self::$usersTable,
			self::$usernameField
		);
		$user = DB::selectOne($query, $username);
		if ($user) {
			$table = self::$usersTable;
			$username = $user[$table][self::$usernameField];
			$password = $user[$table][self::$passwordField];
			Token::$secret = $password;
			Token::$ttl = self::$tokenValidity;
			$claims = Token::getClaims($token);
			if ($claims && $claims['user'] == $username && $claims['ip'] == $_SERVER['REMOTE_ADDR']) {
				if (!Totp::verify($user[$table][self::$totpSecretField] ?? '', $totp ?: '')) {
					throw new TotpError($username);
				}
				session_regenerate_id(true);
				$_SESSION['user'] = $user[$table];
				if ($rememberMe) {
					self::doRemember($username);
				}
			} else {
				$user = [];
			}
		}
		return $user;
	}

	public static function logout(): bool
	{
		foreach ($_SESSION as $key => $value) {
			if ($key != 'debugger') {
				unset($_SESSION[$key]);
			}
		}
		session_regenerate_id(true);
		self::unRemember();
		return true;
	}

	public static function register(string $username)
	{
		$query = sprintf(
			'insert into `%s` (`%s`,`%s`,`%s`) values (?,?,NOW())',
			self::$usersTable,
			self::$usernameField,
			self::$passwordField,
			self::$createdField
		);
		$password = bin2hex(random_bytes(16));
		$password = password_hash($password, PASSWORD_DEFAULT);
		return DB::insert($query, $username, $password);
	}

	public static function update(string $username)
	{
		$query = sprintf(
			'update `%s` set `%s`=? where `%s`=?',
			self::$usersTable,
			self::$passwordField,
			self::$usernameField
		);
		$password = bin2hex(random_bytes(16));
		$password = password_hash($password, PASSWORD_DEFAULT);
		return DB::update($query, $password, $username);
	}

	public static function updateTotpSecret(string $username, string $secret)
	{
		$query = sprintf(
			'update `%s` set `%s`=? where `%s`=?',
			self::$usersTable,
			self::$totpSecretField,
			self::$usernameField
		);
		return DB::update($query, $secret, $username);
	}

	public static function exists(string $username)
	{
		$query = sprintf(
			'select `id` from `%s` where `%s`=?',
			self::$usersTable,
			self::$usernameField
		);
		return DB::selectValue($query, $username);
	}
}
