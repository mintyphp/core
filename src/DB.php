<?php

namespace MintyPHP;

class DB
{
	public static ?string $host = null;
	public static ?string $username = null;
	public static ?string $password = null;
	public static ?string $database = null;
	public static ?int $port = null;
	public static ?string $socket = null;

	protected static ?\mysqli $mysqli = null;
	protected static bool $closed = false;

	protected static function connect()
	{
		if (self::$closed) {
			self::error('Database can only be used in MintyPHP action');
		}
		if (!self::$mysqli) {
			mysqli_report(MYSQLI_REPORT_STRICT);
			self::$mysqli = mysqli_connect(self::$host, self::$username, self::$password, self::$database, self::$port, self::$socket);
			if (mysqli_connect_errno()) self::error(mysqli_connect_error());
			if (!self::$mysqli->set_charset('utf8mb4')) self::error(mysqli_error(self::$mysqli));
		}
	}

	protected static function error($message): void
	{
		throw new DBError($message);
	}

	public static function query($query): mixed
	{
		if (Debugger::$enabled) {
			$time = microtime(true);
		}
		$result = self::queryTyped(...func_get_args());
		if (Debugger::$enabled) {
			$duration = microtime(true) - $time;
			$arguments = func_get_args();
			if (strtoupper(substr(trim($query), 0, 6)) == 'SELECT') {
				$arguments[0] = 'explain ' . $query;
				$explain = self::queryTyped(...$arguments);
			} else {
				$explain = false;
			}
			$arguments = array_slice(func_get_args(), 1);
			$equery = self::$mysqli->real_escape_string($query);
			Debugger::add('queries', compact('duration', 'query', 'equery', 'arguments', 'result', 'explain'));
		}
		return $result;
	}

	private static function queryTyped($query): mixed
	{
		self::connect();
		$nargs = [''];
		if (func_num_args() > 1) {
			$args = func_get_args();
			for ($i = 1; $i < count($args); $i++) {
				if (is_array($args[$i])) {
					if (count($args[$i])) {
						$qmarks = '(' . implode(',', str_split(str_repeat('?', count($args[$i])))) . ')';
					} else {
						$qmarks = '(select 1 from dual where false)';
					}
					$query = preg_replace('/\(\?\?\?\)/', $qmarks, $query, 1);
					foreach (array_keys($args[$i]) as $j) {
						$nargs[0] .= 's';
						$nargs[] = &$args[$i][$j];
					}
				} else {
					$nargs[0] .= 's';
					$nargs[] = &$args[$i];
				}
			}
			$stmt = self::$mysqli->prepare($query);
			$stmt->bind_param(...$nargs);
		} else {
			$stmt = self::$mysqli->prepare($query);
		}
		if (!$stmt) {
			self::error(self::$mysqli->error);
		}
		$stmt->execute();
		if ($stmt->errno) {
			$error = self::$mysqli->error;
			$stmt->close();
			self::error($error);
		}
		if ($stmt->affected_rows > -1) {
			$result = $stmt->affected_rows;
			$stmt->close();
			return $result;
		}
		$stmt->store_result();
		$params = [];
		$meta = $stmt->result_metadata();
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

	public static function insert($query): int
	{
		$result = self::query(...func_get_args());
		if (!is_int($result)) return 0;
		if (!$result) return 0;
		return self::$mysqli->insert_id;
	}

	public static function update($query): int
	{
		$result = self::query(...func_get_args());
		if (!is_int($result)) return 0;
		return $result;
	}

	public static function delete($query): int
	{
		$result = self::query(...func_get_args());
		if (!is_int($result)) return 0;
		return $result;
	}

	public static function select($query): array
	{
		$result = self::query(...func_get_args());
		if (!is_array($result)) return [];
		return $result;
	}

	public static function selectValue($query): mixed
	{
		$result = self::query(...func_get_args());
		if (!is_array($result)) return false;
		if (!isset($result[0])) return false;
		$record = $result[0];
		if (!is_array($record)) return false;
		$firstTable = array_shift($record);
		if (!is_array($firstTable)) return false;
		return array_shift($firstTable);
	}


	public static function selectValues($query): array
	{
		$result = self::query(...func_get_args());
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

	public static function selectPairs($query): array
	{
		$result = self::query(...func_get_args());
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

	public static function selectOne($query)
	{
		$result = self::query(...func_get_args());
		if (!is_array($result)) return false;
		if (isset($result[0])) return $result[0];
		return $result;
	}

	public static function close()
	{
		if (self::$mysqli) {
			self::$mysqli->close();
			self::$mysqli = null;
		}
		self::$closed = true;
	}

	// Undocumented
	public static function handle()
	{
		self::$closed = false;
		self::connect();
		return self::$mysqli;
	}
}
