<?php

namespace MintyPHP\Core;

use mysqli;
use mysqli_stmt;
use MintyPHP\DBError;
use MintyPHP\Debugger;

/**
 * Database abstraction layer for MintyPHP
 * 
 * Provides a secure interface for database operations using mysqli with prepared statements.
 * Supports debugging, query logging, and automatic parameter binding.
 */
class DB
{
    /**
     * The database connection parameters (static for singleton pattern)
     */
    public static ?string $__host = null;
    public static ?string $__username = null;
    public static ?string $__password = null;
    public static ?string $__database = null;
    public static ?int $__port = null;
    public static ?string $__socket = null;

    /**
     * The database connection parameters
     */
    private ?string $host;
    private ?string $username;
    private ?string $password;
    private ?string $database;
    private ?int $port;
    private ?string $socket;

    /**
     * Constructor to initialize database connection and establish connection
     * 
     * @param string|null $host Database host
     * @param string|null $username Database username
     * @param string|null $password Database password
     * @param string|null $database Database name
     * @param int|null $port Database port
     * @param string|null $socket Database socket
     * @throws DBError if connection fails
     */
    public function __construct(?string $host, ?string $username, ?string $password, ?string $database, ?int $port, ?string $socket)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->port = $port;
        $this->socket = $socket;

        // Establish the database connection
        mysqli_report(MYSQLI_REPORT_STRICT);
        $connection = mysqli_connect($this->host, $this->username, $this->password, $this->database, $this->port, $this->socket);
        if (!$connection instanceof mysqli) {
            throw new DBError(mysqli_connect_error() ?: 'Database connection failed');
        }
        $this->mysqli = $connection;
        if (mysqli_connect_errno()) {
            throw new DBError(mysqli_connect_error() ?: 'Database connection error');
        }
        if (!$this->mysqli->set_charset('utf8mb4')) {
            throw new DBError(mysqli_error($this->mysqli));
        }

        // Mark the connection as open
        $this->closed = false;
    }

    private mysqli $mysqli;
    private bool $closed;

    /**
     * Executes a query with optional debugging
     * 
     * @param string $query SQL query with ? placeholders
     * @param mixed ...$params Query parameters
     * @return mixed Query result (array for SELECT, int for INSERT/UPDATE/DELETE)
     * @throws DBError if query execution fails or database is closed
     */
    public function query(string $query, mixed ...$params): mixed
    {
        if (Debugger::$enabled) {
            $time = microtime(true);
        }
        $result = $this->queryTyped($query, ...$params);
        if (Debugger::$enabled) {
            $duration = microtime(true) - $time;
            $arguments = [$query, ...$params];
            if (strtoupper(substr(trim($query), 0, 6)) == 'SELECT') {
                $explain = $this->queryTyped('explain ' . $query, ...$params);
            } else {
                $explain = false;
            }
            $equery = $this->mysqli->real_escape_string($query);
            Debugger::add('queries', compact('duration', 'query', 'equery', 'arguments', 'result', 'explain'));
        }
        return $result;
    }

    /**
     * Executes a typed query using prepared statements
     * 
     * @param string $query SQL query with ? placeholders
     * @param mixed ...$params Query parameters
     * @return mixed Query result (array for SELECT, int for INSERT/UPDATE/DELETE)
     * @throws DBError if query execution fails or database is closed
     */
    private function queryTyped(string $query, mixed ...$params): mixed
    {
        if ($this->closed) {
            throw new DBError('Database can only be used in MintyPHP action');
        }
        $nargs = [''];
        if (count($params) > 0) {
            for ($i = 0; $i < count($params); $i++) {
                if (is_array($params[$i])) {
                    if (count($params[$i])) {
                        $qmarks = '(' . implode(',', str_split(str_repeat('?', count($params[$i])))) . ')';
                    } else {
                        $qmarks = '(select 1 from dual where false)';
                    }
                    $replaced = preg_replace('/\(\?\?\?\)/', $qmarks, $query, 1);
                    if ($replaced !== null) {
                        $query = $replaced;
                    }
                    foreach (array_keys($params[$i]) as $j) {
                        $nargs[0] .= 's';
                        $nargs[] = &$params[$i][$j];
                    }
                } else {
                    $nargs[0] .= 's';
                    $nargs[] = &$params[$i];
                }
            }
            $stmt = $this->mysqli->prepare($query);
            if ($stmt === false) {
                throw new DBError($this->mysqli->error);
            }
            assert($stmt instanceof mysqli_stmt);
            if ($nargs[0]) {
                $stmt->bind_param(...$nargs);
            }
        } else {
            $stmt = $this->mysqli->prepare($query);
            if ($stmt === false) {
                throw new DBError($this->mysqli->error);
            }
            assert($stmt instanceof mysqli_stmt);
        }
        $stmt->execute();
        if ($stmt->errno) {
            $error = $this->mysqli->error;
            $stmt->close();
            throw new DBError($error);
        }
        if ($stmt->affected_rows > -1) {
            $result = $stmt->affected_rows;
            $stmt->close();
            return $result;
        }
        $stmt->store_result();
        $params = [];
        $meta = $stmt->result_metadata();
        if ($meta === false) {
            $stmt->close();
            return [];
        }
        $row = [];
        while ($field = $meta->fetch_field()) {
            if (!$field->table && strpos($field->name, '.')) {
                $parts = explode('.', $field->name, 2);
                $params[] = &$row[$parts[0]][$parts[1]];
            } else {
                if (!isset($row[$field->table])) $row[$field->table] = [];
                $params[] = &$row[$field->table][$field->name];
            }
        }
        $stmt->bind_result(...$params);

        $result = [];
        while ($stmt->fetch()) {
            $result[] = unserialize(serialize($row));
        }

        $stmt->close();

        return $result;
    }

    /**
     * Executes an INSERT query and returns the last insert ID
     * 
     * @param string $query SQL INSERT query
     * @param mixed ...$params Query parameters
     * @return int Last insert ID
     * @throws DBError if query execution fails or database is closed
     */
    public function insert(string $query, mixed ...$params): int
    {
        $result = $this->query($query, ...$params);
        if (!is_int($result)) return 0;
        if (!$result) return 0;
        $insertId = $this->mysqli->insert_id;
        return is_int($insertId) ? $insertId : (int)$insertId;
    }

    /**
     * Executes an UPDATE query and returns affected rows
     * 
     * @param string $query SQL UPDATE query
     * @param mixed ...$params Query parameters
     * @return int Number of affected rows
     * @throws DBError if query execution fails or database is closed
     */
    public function update(string $query, mixed ...$params): int
    {
        $result = $this->query($query, ...$params);
        if (!is_int($result)) return 0;
        return $result;
    }

    /**
     * Executes a DELETE query and returns affected rows
     * 
     * @param string $query SQL DELETE query
     * @param mixed ...$params Query parameters
     * @return int Number of affected rows
     * @throws DBError if query execution fails or database is closed
     */
    public function delete(string $query, mixed ...$params): int
    {
        $result = $this->query($query, ...$params);
        if (!is_int($result)) return 0;
        return $result;
    }

    /**
     * Executes a SELECT query and returns all rows
     * 
     * @param string $query SQL SELECT query
     * @param mixed ...$params Query parameters
     * @return array<int, array<string, array<string, mixed>>> Array of result rows
     * @throws DBError if query execution fails or database is closed
     */
    public function select(string $query, mixed ...$params): array
    {
        $result = $this->query($query, ...$params);
        if (!is_array($result)) return [];
        return $result;
    }

    /**
     * Executes a SELECT query and returns the first row
     * 
     * @param string $query SQL SELECT query
     * @param mixed ...$params Query parameters
     * @return array<string, array<string, mixed>>|false First result row or false if no result
     * @throws DBError if query execution fails or database is closed
     */
    public function selectOne(string $query, mixed ...$params): array|false
    {
        $result = $this->query($query, ...$params);
        if (!is_array($result)) return [];
        if (isset($result[0])) return $result[0];
        return $result;
    }

    /**
     * Executes a SELECT query and returns a single value
     * 
     * @param string $query SQL SELECT query
     * @param mixed ...$params Query parameters
     * @return mixed Single value from first column of first row, or false if no result
     * @throws DBError if query execution fails or database is closed
     */
    public function selectValue(string $query, mixed ...$params): mixed
    {
        $result = $this->query($query, ...$params);
        if (!is_array($result)) return false;
        if (!isset($result[0])) return false;
        $record = $result[0];
        if (!is_array($record)) return false;
        $firstTable = array_shift($record);
        if (!is_array($firstTable)) return false;
        return array_shift($firstTable);
    }


    /**
     * Executes a SELECT query and returns an array of values from first column
     * 
     * @param string $query SQL SELECT query
     * @param mixed ...$params Query parameters
     * @return array<int, mixed> Array of values from first column
     * @throws DBError if query execution fails or database is closed
     */
    public function selectValues(string $query, mixed ...$params): array
    {
        $result = $this->query($query, ...$params);
        if (!is_array($result)) return [];
        $list = [];
        foreach ($result as $record) {
            if (!is_array($record)) return [];
            $firstTable = array_shift($record);
            if (!is_array($firstTable)) return [];
            $list[] = array_shift($firstTable);
        }
        return $list;
    }

    /**
     * Executes a SELECT query and returns key-value pairs
     * 
     * @param string $query SQL SELECT query returning at least 2 columns
     * @param mixed ...$params Query parameters
     * @return array<int|string, mixed> Associative array using first column as keys, second as values
     * @throws DBError if query execution fails or database is closed
     */
    public function selectPairs(string $query, mixed ...$params): array
    {
        $result = $this->query($query, ...$params);
        if (!is_array($result)) return [];
        $list = [];
        foreach ($result as $record) {
            if (!is_array($record)) return [];
            $columns = [];
            foreach ($record as $table) {
                if (!is_array($table)) return [];
                $columns = array_merge($columns, $table);
            }
            $list[array_shift($columns)] = array_shift($columns);
        }
        return $list;
    }

    /**
     * Closes the database connection
     * 
     * @return void
     */
    public function close(): void
    {
        $this->mysqli->close();
        $this->closed = true;
    }

    /**
     * Returns the mysqli handle for advanced operations (undocumented)
     * 
     * @return \mysqli|null Database connection handle
     */
    public function handle(): ?\mysqli
    {
        $this->closed = false;
        return $this->mysqli;
    }
}
