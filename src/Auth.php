<?php

namespace MintyPHP;

use MintyPHP\Core\Auth as CoreAuth;

/**
 * Static class for authentication operations using a singleton pattern.
 */
class Auth
{
	/**
	 * The Auth instance
	 * @var ?CoreAuth
	 */
	private static ?CoreAuth $instance = null;

	/**
	 * Configuration for the Auth instance
	 */
	public static string $usersTable = 'users';
	public static string $usernameField = 'username';
	public static string $passwordField = 'password';
	public static string $createdField = 'created';
	public static string $totpSecretField = 'totp_secret';

	/**
	 * Get the Auth instance
	 * @return CoreAuth
	 */
	public static function getInstance(): CoreAuth
	{
		return self::$instance ??= new CoreAuth(
			DB::getInstance(),
			self::$usersTable,
			self::$usernameField,
			self::$passwordField,
			self::$createdField,
			self::$totpSecretField
		);
	}

	/**
	 * Set the Auth instance to use
	 * @param CoreAuth $auth
	 * @return void
	 */
	public static function setInstance(CoreAuth $auth): void
	{
		self::$instance = $auth;
	}

	/**
	 * Authenticate a user with username, password, and optional TOTP code
	 * 
	 * @param string $username The username to authenticate
	 * @param string $password The password to verify
	 * @param string $totp Optional TOTP code for two-factor authentication
	 * @return array<string,array<string|mixed>> User data on success, empty array on failure
	 */
	public static function login(string $username, string $password, string $totp = ''): array
	{
		$auth = self::getInstance();
		return $auth->login($username, $password, $totp);
	}

	/**
	 * Log out the current user
	 * 
	 * @return bool Always returns true
	 */
	public static function logout(): bool
	{
		$auth = self::getInstance();
		return $auth->logout();
	}

	/**
	 * Register a new user with username and password
	 * 
	 * @param string $username The username for the new user
	 * @param string $password The password for the new user
	 * @return int The ID of the newly created user
	 */
	public static function register(string $username, string $password): int
	{
		$auth = self::getInstance();
		return $auth->register($username, $password);
	}

	/**
	 * Update a user's password
	 * 
	 * @param string $username The username to update
	 * @param string $password The new password
	 * @return int Number of rows affected
	 */
	public static function update(string $username, string $password): int
	{
		$auth = self::getInstance();
		return $auth->update($username, $password);
	}

	/**
	 * Update a user's TOTP secret
	 * 
	 * @param string $username The username to update
	 * @param string $secret The new TOTP secret
	 * @return int Number of rows affected
	 */
	public static function updateTotpSecret(string $username, string $secret): int
	{
		$auth = self::getInstance();
		return $auth->updateTotpSecret($username, $secret);
	}

	/**
	 * Check if a user exists
	 * 
	 * @param string $username The username to check
	 * @return bool True if user exists, false otherwise
	 */
	public static function exists(string $username): bool
	{
		$auth = self::getInstance();
		return $auth->exists($username);
	}
}
