<?php

namespace MintyPHP;

use MintyPHP\Core\DB as CoreDB;

/**
 * Static wrapper class for DB operations using a singleton pattern.
 */
class DB
{
    /**
     * The database connection parameters (static for singleton pattern)
     */
    public static ?string $host = null;
    public static ?string $username = null;
    public static ?string $password = null;
    public static ?string $database = null;
    public static ?int $port = null;
    public static ?string $socket = null;

    /**
     * The DB instance
     * @var ?CoreDB
     */
    private static ?CoreDB $instance = null;

    /**
     * Get the DB instance
     * @return CoreDB
     */
    public static function getInstance(): CoreDB
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
     * Set the DB instance to use
     * @param CoreDB $instance
     * @return void
     */
    public static function setInstance(CoreDB $instance): void
    {
        self::$instance = $instance;
    }

    /**
    	 * Executes a query with optional debugging
    	 * 
    	 * @param string $query SQL query with ? placeholders
    	 * @param mixed ...$params Query parameters
    	 * @return mixed Query result (array for SELECT, int for INSERT/UPDATE/DELETE)
    	 * @throws DBError if query execution fails or database is closed
    	 */
    public static function query(string $query, mixed ...$params): mixed
    {
        $instance = self::getInstance();
        return $instance->query($query, ...$params);
    }

    /**
    	 * Executes an INSERT query and returns the last insert ID
    	 * 
    	 * @param string $query SQL INSERT query
    	 * @param mixed ...$params Query parameters
    	 * @return int Last insert ID
    	 * @throws DBError if query execution fails or database is closed
    	 */
    public static function insert(string $query, mixed ...$params): int
    {
        $instance = self::getInstance();
        return $instance->insert($query, ...$params);
    }

    /**
    	 * Executes an UPDATE query and returns affected rows
    	 * 
    	 * @param string $query SQL UPDATE query
    	 * @param mixed ...$params Query parameters
    	 * @return int Number of affected rows
    	 * @throws DBError if query execution fails or database is closed
    	 */
    public static function update(string $query, mixed ...$params): int
    {
        $instance = self::getInstance();
        return $instance->update($query, ...$params);
    }

    /**
    	 * Executes a DELETE query and returns affected rows
    	 * 
    	 * @param string $query SQL DELETE query
    	 * @param mixed ...$params Query parameters
    	 * @return int Number of affected rows
    	 * @throws DBError if query execution fails or database is closed
    	 */
    public static function delete(string $query, mixed ...$params): int
    {
        $instance = self::getInstance();
        return $instance->delete($query, ...$params);
    }

    /**
    	 * Executes a SELECT query and returns all rows
    	 * 
    	 * @param string $query SQL SELECT query
    	 * @param mixed ...$params Query parameters
    	 * @return array<int, array<string, array<string, mixed>>> Array of result rows
    	 * @throws DBError if query execution fails or database is closed
    	 */
    public static function select(string $query, mixed ...$params): array
    {
        $instance = self::getInstance();
        return $instance->select($query, ...$params);
    }

    /**
    	 * Executes a SELECT query and returns the first row
    	 * 
    	 * @param string $query SQL SELECT query
    	 * @param mixed ...$params Query parameters
    	 * @return array<string, array<string, mixed>>|false First result row or false if no result
    	 * @throws DBError if query execution fails or database is closed
    	 */
    public static function selectOne(string $query, mixed ...$params): array|false
    {
        $instance = self::getInstance();
        return $instance->selectOne($query, ...$params);
    }

    /**
    	 * Executes a SELECT query and returns a single value
    	 * 
    	 * @param string $query SQL SELECT query
    	 * @param mixed ...$params Query parameters
    	 * @return mixed Single value from first column of first row, or false if no result
    	 * @throws DBError if query execution fails or database is closed
    	 */
    public static function selectValue(string $query, mixed ...$params): mixed
    {
        $instance = self::getInstance();
        return $instance->selectValue($query, ...$params);
    }

    /**
    	 * Executes a SELECT query and returns an array of values from first column
    	 * 
    	 * @param string $query SQL SELECT query
    	 * @param mixed ...$params Query parameters
    	 * @return array<int, mixed> Array of values from first column
    	 * @throws DBError if query execution fails or database is closed
    	 */
    public static function selectValues(string $query, mixed ...$params): array
    {
        $instance = self::getInstance();
        return $instance->selectValues($query, ...$params);
    }

    /**
    	 * Executes a SELECT query and returns key-value pairs
    	 * 
    	 * @param string $query SQL SELECT query returning at least 2 columns
    	 * @param mixed ...$params Query parameters
    	 * @return array<int|string, mixed> Associative array using first column as keys, second as values
    	 * @throws DBError if query execution fails or database is closed
    	 */
    public static function selectPairs(string $query, mixed ...$params): array
    {
        $instance = self::getInstance();
        return $instance->selectPairs($query, ...$params);
    }

    /**
    	 * Closes the database connection
    	 * 
    	 * @return void
    	 */
    public static function close(): void
    {
        $instance = self::getInstance();
        $instance->close();
    }

    /**
    	 * Returns the mysqli handle for advanced operations (undocumented)
    	 * 
    	 * @return mysqli|null Database connection handle
    	 */
    public static function handle(): ?mysqli
    {
        $instance = self::getInstance();
        return $instance->handle();
    }
}
