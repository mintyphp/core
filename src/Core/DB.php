<?php

namespace MintyPHP\Core;

use mysqli;
use mysqli_stmt;

use MintyPHP\Error\DBError;

/**
 * Database abstraction layer for MintyPHP
 * 
 * Provides a secure interface for database operations using mysqli with prepared statements.
 * Supports debugging, query logging, and automatic parameter binding.
 */
class DB
{
    /**
     * Static configuration parameters
     */
    public static ?string $__host = null;
    public static ?string $__username = null;
    public static ?string $__password = null;
    public static ?string $__database = null;
    public static ?int $__port = null;
    public static ?string $__socket = null;

    /**
     * Database instance for executing queries.
     */
    private \mysqli $mysqli;

    /**
     * Indicates whether the database connection is closed.
     */
    private bool $closed;

    /**
     * Constructor to initialize database connection and establish connection
     * 
     * @param string|null $host Database host
     * @param string|null $username Database username
     * @param string|null $password Database password
     * @param string|null $database Database name
     * @param int|null $port Database port
     * @param string|null $socket Database socket
     * @param \mysqli|null $mysqli Existing mysqli connection
     * @throws \RuntimeException if connection fails
     */
    public function __construct(
        private readonly ?string $host,
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly ?string $database,
        private readonly ?int $port,
        private readonly ?string $socket,
        ?\mysqli $mysqli = null,
        /**
         * Debugger instance for logging database operations
         */
        private ?Debugger $debugger = null
    ) {
        // Establish the database connection
        mysqli_report(MYSQLI_REPORT_STRICT);
        if ($mysqli === null) {
            $mysqli = mysqli_connect($this->host, $this->username, $this->password, $this->database, $this->port, $this->socket);
            if (!($mysqli instanceof mysqli) || mysqli_connect_errno()) {
                throw new DBError(mysqli_connect_error() ?: 'Database connection failed');
            }
        }
        $this->mysqli = $mysqli;
        if (!$this->mysqli->set_charset('utf8mb4')) {
            throw new DBError(mysqli_error($this->mysqli));
        }

        // Mark the connection as open
        $this->closed = false;
    }

    /**
     * Executes a query with optional debugging
     * 
     * @param string $query SQL query with ? placeholders
     * @param mixed ...$params Query parameters
     * @return array<int, array<string, array<string, mixed>>>|int Query result (array for SELECT, int for INSERT/UPDATE/DELETE)
     * @throws \RuntimeException if query execution fails or database is closed
     */
    public function query(string $query, mixed ...$params): mixed
    {
        if ($this->debugger !== null) {
            $time = microtime(true);
        }
        $result = $this->queryTyped($query, ...$params);
        if ($this->debugger !== null) {
            $duration = microtime(true) - $time;
            if (strtoupper(substr(trim($query), 0, 6)) == 'SELECT') {
                $explain = $this->queryTyped('explain ' . $query, ...$params);
            } else {
                $explain = false;
            }
            $equery = $this->mysqli->real_escape_string($query);
            $this->debugger->addQuery($duration, $query, $equery, $params, $result, $explain);
        }
        return $result;
    }

    /**
     * Executes a typed query using prepared statements
     * 
     * @param string $query SQL query with ? placeholders
     * @param mixed ...$params Query parameters
     * @return array<int, array<string, array<string, mixed>>>|int Query result (array for SELECT, int for INSERT/UPDATE/DELETE)
     * @throws \RuntimeException if query execution fails or database is closed
     */
    private function queryTyped(string $query, mixed ...$params): mixed
    {
        if ($this->closed) {
            throw new DBError('Database can only be used in MintyPHP action');
        }
        if (count($params) > 0) {
            $types = '';
            $arguments = [''];
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
                        $types .= 's';
                        $arguments[] = &$params[$i][$j];
                    }
                } else {
                    $types .= 's';
                    $arguments[] = &$params[$i];
                }
            }
            $stmt = $this->mysqli->prepare($query);
            if ($stmt === false) {
                throw new DBError($this->mysqli->error);
            }
            if ($types) {
                $stmt->bind_param($types, ...$arguments);
            }
        } else {
            $stmt = $this->mysqli->prepare($query);
            if ($stmt === false) {
                throw new DBError($this->mysqli->error);
            }
        }
        $stmt->execute();
        if ($stmt->errno) {
            $error = $this->mysqli->error;
            $stmt->close();
            throw new DBError($error);
        }
        if ($stmt->affected_rows > -1) {
            $result = $stmt->affected_rows;
            $result = is_int($result) ? $result : (int)$result;
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
            /** @var array<string, array<string, mixed>> $copy */
            $copy = unserialize(serialize($row));
            $result[] = $copy;
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
     * @throws \RuntimeException if query execution fails or database is closed
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
     * @throws \RuntimeException if query execution fails or database is closed
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
     * @throws \RuntimeException if query execution fails or database is closed
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
     * @throws \RuntimeException if query execution fails or database is closed
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
     * @throws \RuntimeException if query execution fails or database is closed
     */
    public function selectOne(string $query, mixed ...$params): array|false
    {
        $result = $this->query($query, ...$params);
        if (!is_array($result)) return [];
        if (count($result) == 0) return false;
        return $result[0];
    }

    /**
     * Executes a SELECT query and returns a single value
     * 
     * @param string $query SQL SELECT query
     * @param mixed ...$params Query parameters
     * @return mixed Single value from first column of first row, or false if no result
     * @throws \RuntimeException if query execution fails or database is closed
     */
    public function selectValue(string $query, mixed ...$params): mixed
    {
        $result = $this->query($query, ...$params);
        if (!is_array($result)) return false;
        if (count($result) == 0) return false;
        $record = $result[0];
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
     * @throws \RuntimeException if query execution fails or database is closed
     */
    public function selectValues(string $query, mixed ...$params): array
    {
        $result = $this->query($query, ...$params);
        if (!is_array($result)) return [];
        $list = [];
        foreach ($result as $record) {
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
     * @throws \RuntimeException if query execution fails or database is closed
     */
    public function selectPairs(string $query, mixed ...$params): array
    {
        $result = $this->query($query, ...$params);
        if (!is_array($result)) return [];
        $list = [];
        foreach ($result as $record) {
            $columns = [];
            foreach ($record as $table) {
                $columns = array_merge($columns, $table);
            }
            $key = array_shift($columns);
            $value = array_shift($columns);
            if (is_int($key) || is_string($key)) {
                $list[$key] = $value;
            } else {
                $list[] = $value;
            }
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
