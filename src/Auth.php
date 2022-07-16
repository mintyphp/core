<?php

namespace MintyPHP;

class Auth
{
	static $usersTable = 'users';
	static $usernameField = 'username';
	static $passwordField = 'password';
	static $createdField = 'created';
	static $totpSecretField = 'totp_secret';

	public static function login(string $username, string $password, string $totp = null)
	{
		$query = sprintf(
			'select * from `%s` where `%s` = ? limit 1',
			static::$usersTable,
			static::$usernameField
		);
		$user = DB::selectOne($query, $username);
		if ($user) {
			$table = static::$usersTable;
			if (password_verify($password, $user[$table][static::$passwordField])) {
				if (!Totp::verify($user[$table][static::$totpSecretField] ?? '', $totp ?: '')) {
					throw new TotpError($user[$table][static::$usernameField]);
				}
				session_regenerate_id(true);
				$_SESSION['user'] = $user[$table];
			} else {
				$user = array();
			}
		}
		return $user;
	}

	public static function logout(): bool
	{
		unset($_SESSION['user']);
		session_regenerate_id(true);
		return true;
	}

	public static function register(string $username, string $password)
	{
		$query = sprintf(
			'insert into `%s` (`%s`,`%s`,`%s`) values (?,?,NOW())',
			static::$usersTable,
			static::$usernameField,
			static::$passwordField,
			static::$createdField
		);
		$password = password_hash($password, PASSWORD_DEFAULT);
		return DB::insert($query, $username, $password);
	}

	public static function update(string $username, string $password)
	{
		$query = sprintf(
			'update `%s` set `%s`=? where `%s`=?',
			static::$usersTable,
			static::$passwordField,
			static::$usernameField
		);
		$password = password_hash($password, PASSWORD_DEFAULT);
		return DB::update($query, $password, $username);
	}

	public static function updateTotpSecret(string $username, string $secret)
	{
		$query = sprintf(
			'update `%s` set `%s`=? where `%s`=?',
			static::$usersTable,
			static::$totpSecretField,
			static::$usernameField
		);
		return DB::update($query, $secret, $username);
	}

	public static function exists(string $username)
	{
		$query = sprintf(
			'select 1 from `%s` where `%s`=?',
			static::$usersTable,
			static::$usernameField
		);
		return DB::selectValue($query, $username);
	}
}
