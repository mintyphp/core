<?php

namespace MintyPHP;

use MintyPHP\Core\DB as CoreDB;

/**
 * Static class for database operations using a singleton pattern.
 */
class DB
{
	/**
	 * The database connection parameters
	 */
	public static ?string $host = null;
	public static ?string $username = null;
	public static ?string $password = null;
	public static ?string $database = null;
	public static ?int $port = null;
	public static ?string $socket = null;

	/**
	 * The database instance
	 * @var ?CoreDB
	 */
	private static ?CoreDB $instance = null;

	/**
	 * Get the database instance
	 * @return CoreDB
	 * @throws DBError if connection fails
	 */
	private static function getInstance(): CoreDB
	{
		return self::$instance ??= new CoreDB(
			self::$host,
			self::$username,
			self::$password,
			self::$database,
			self::$port,
			self::$socket
		);
	}

	/**
	 * Set the database instance to use
	 * @param CoreDB $db
	 * @return void
	 */
	public static function setInstance(CoreDB $db): void
	{
		self::$instance = $db;
	}

	/**
	 * Execute a database query
	 * @param string $query
	 * @param mixed ...$params
	 * @return mixed
	 * @throws DBError if query execution fails or database is closed
	 */
	public static function query(string $query, mixed ...$params): mixed
	{
		$db = self::getInstance();
		return $db->query($query, ...$params);
	}

	/**
	 * Insert a new record and return the inserted ID
	 * @param string $query
	 * @param mixed ...$params
	 * @return int
	 * @throws DBError if query execution fails or database is closed
	 */
	public static function insert(string $query, mixed ...$params): int
	{
		$db = self::getInstance();
		return $db->insert($query, ...$params);
	}

	/**
	 * Update existing records and return the number of affected rows
	 * @param string $query
	 * @param mixed ...$params
	 * @return int
	 * @throws DBError if query execution fails or database is closed
	 */
	public static function update(string $query, mixed ...$params): int
	{
		$db = self::getInstance();
		return $db->update($query, ...$params);
	}

	/**
	 * Delete records and return the number of affected rows
	 * @param string $query
	 * @param mixed ...$params
	 * @return int
	 * @throws DBError if query execution fails or database is closed
	 */
	public static function delete(string $query, mixed ...$params): int
	{
		$db = self::getInstance();
		return $db->delete($query, ...$params);
	}

	/**
	 * Select records from the database
	 * @param string $query
	 * @param mixed ...$params
	 * @return array<int, array<string, array<string, mixed>>>
	 * @throws DBError if query execution fails or database is closed
	 */
	public static function select(string $query, mixed ...$params): array
	{
		$db = self::getInstance();
		return $db->select($query, ...$params);
	}

	/**
	 * Select a single record from the database
	 * @param string $query
	 * @param mixed ...$params
	 * @return array<string, array<string, mixed>>|false
	 * @throws DBError if query execution fails or database is closed
	 */
	public static function selectOne(string $query, mixed ...$params): array|false
	{
		$db = self::getInstance();
		return $db->selectOne($query, ...$params);
	}

	/**
	 * Select a single value from the database
	 * @param string $query
	 * @param mixed ...$params
	 * @return mixed
	 * @throws DBError if query execution fails or database is closed
	 */
	public static function selectValue(string $query, mixed ...$params): mixed
	{
		$db = self::getInstance();
		return $db->selectValue($query, ...$params);
	}

	/**
	 * Select multiple values from the database
	 * @param string $query
	 * @param mixed ...$params
	 * @return array<int, mixed>
	 * @throws DBError if query execution fails or database is closed
	 */
	public static function selectValues(string $query, mixed ...$params): array
	{
		$db = self::getInstance();
		return $db->selectValues($query, ...$params);
	}

	/**
	 * Close the database connection
	 * @return void
	 * @throws DBError if connection fails
	 */
	public static function close(): void
	{
		$db = self::getInstance();
		$db->close();
	}

	/**
	 * Handle any pending database operations
	 * @return void
	 * @throws DBError if connection fails
	 */
	public static function handle(): void
	{
		$db = self::getInstance();
		$db->handle();
	}
}
