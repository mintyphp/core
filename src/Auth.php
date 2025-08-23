<?php

namespace MintyPHP;

class Auth
{
	public static string $usersTable = 'users';
	public static string $usernameField = 'username';
	public static string $passwordField = 'password';
	public static string $createdField = 'created';
	public static string $totpSecretField = 'totp_secret';

	/** @return array<string,array<string|mixed>> */
	public static function login(string $username, string $password, string $totp = ''): array
	{
		$query = sprintf(
			'select * from `%s` where `%s` = ? limit 1',
			self::$usersTable,
			self::$usernameField
		);
		$user = DB::selectOne($query, $username);
		if ($user) {
			$table = self::$usersTable;
			if (password_verify($password, $user[$table][self::$passwordField])) {
				if (!Totp::verify($user[$table][self::$totpSecretField] ?? '', $totp ?: '')) {
					throw new TotpError($user[$table][self::$usernameField]);
				}
				Session::regenerate();
				$_SESSION['user'] = $user[$table];
			} else {
				$user = [];
			}
		}
		return $user;
	}

	public static function logout(): bool
	{
		unset($_SESSION['user']);
		Session::regenerate();
		return true;
	}

	public static function register(string $username, string $password): int
	{
		$query = sprintf(
			'insert into `%s` (`%s`,`%s`,`%s`) values (?,?,NOW())',
			self::$usersTable,
			self::$usernameField,
			self::$passwordField,
			self::$createdField
		);
		$password = password_hash($password, PASSWORD_DEFAULT);
		return DB::insert($query, $username, $password);
	}

	public static function update(string $username, string $password): int
	{
		$query = sprintf(
			'update `%s` set `%s`=? where `%s`=?',
			self::$usersTable,
			self::$passwordField,
			self::$usernameField
		);
		$password = password_hash($password, PASSWORD_DEFAULT);
		return DB::update($query, $password, $username);
	}

	public static function updateTotpSecret(string $username, string $secret): int
	{
		$query = sprintf(
			'update `%s` set `%s`=? where `%s`=?',
			self::$usersTable,
			self::$totpSecretField,
			self::$usernameField
		);
		return DB::update($query, $secret, $username);
	}

	public static function exists(string $username): bool
	{
		$query = sprintf(
			'select 1 from `%s` where `%s`=?',
			self::$usersTable,
			self::$usernameField
		);
		return boolval(DB::selectValue($query, $username));
	}
}

