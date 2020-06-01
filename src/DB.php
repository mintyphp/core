<?php

namespace MintyPHP;

class DB
{
  public static $host = null;
  public static $username = null;
  public static $password = null;
  public static $database = null;
  public static $port = null;
  public static $socket = null;

  protected static $mysqli = null;
  protected static $closed = false;

  protected static function connect()
  {
    if (static::$closed) {
      static::error('Database can only be used in MintyPHP action');
    }
    if (!static::$mysqli) {
      $reflect = new \ReflectionClass('mysqli');
      $args = array(static::$host, static::$username, static::$password, static::$database, static::$port, static::$socket);
      while (isset($args[count($args) - 1]) && $args[count($args) - 1] !== null) array_pop($args);
      static::$mysqli = $reflect->newInstanceArgs($args);
      if (mysqli_connect_errno()) static::error(mysqli_connect_error());
      if (!static::$mysqli->set_charset('utf8mb4')) static::error(mysqli_error(static::$mysqli));
    }
  }

  protected static function error($message)
  {
    throw new DBError($message);
  }

  public static function query($query)
  {
    if (Debugger::$enabled) {
      $time = microtime(true);
    }
    $result = forward_static_call_array('DB::queryTyped', func_get_args());
    if (Debugger::$enabled) {
      $duration = microtime(true) - $time;
      $arguments = func_get_args();
      if (strtoupper(substr(trim($query), 0, 6)) == 'SELECT') {
        $arguments[0] = 'explain ' . $query;
        $explain = forward_static_call_array('DB::queryTyped', $arguments);
      } else {
        $explain = false;
      }
      $arguments = array_slice(func_get_args(), 2);
      $equery = static::$mysqli->real_escape_string($query);
      Debugger::add('queries', compact('duration', 'query', 'equery', 'arguments', 'result', 'explain'));
    }
    return $result;
  }

  private static function queryTyped($query)
  {
    static::connect();
    if (func_num_args() > 1) {
      $args = func_get_args();
      $args[0] = str_repeat('s', count($args) - 1);
      $i = 0;
      while ($i < count($args)) {
        if ($i > 0) {
          if (is_array($args[$i])) {
            $arr = $args[$i];
            $query = preg_replace('/\?\?\?/', implode(',', str_split(str_repeat('?', count($arr)))), $query, 1);
            foreach (array_keys($arr) as $j) {
              $args[$i][$j] = &$arr[$j];
            }
            array_splice($args, $i, 1, $arr);
            $args[0] .= str_repeat('s', count($arr) - 1);
            $i += count($arr) - 1;
          } else {
            $args[$i] = &$args[$i];
          }
        }
        $i += 1;
      }
      $stmt = static::$mysqli->prepare($query);
      if (!$stmt) {
        return static::error(static::$mysqli->error);
      }
      //legacy (PHP 7.4)
      $ref    = new \ReflectionClass('mysqli_stmt');
      $method = $ref->getMethod("bind_param");
      $method->invokeArgs($stmt, $args);
      //$stmt->bind_param(...$args);
    } else {
      $stmt = static::$mysqli->prepare($query);
      if (!$stmt) {
        return static::error(static::$mysqli->error);
      }
    }
    $stmt->execute();
    if ($stmt->errno) {
      $error = static::$mysqli->error;
      $stmt->close();
      return static::error($error);
    }
    if ($stmt->affected_rows > -1) {
      $result = $stmt->affected_rows;
      $stmt->close();
      return $result;
    }
    $stmt->store_result();
    $params = array();
    $meta = $stmt->result_metadata();
    $row = array();
    while ($field = $meta->fetch_field()) {
      if (!$field->table && strpos($field->name, '.')) {
        $parts = explode('.', $field->name, 2);
        $params[] = &$row[$parts[0]][$parts[1]];
      } else {
        if (!isset($row[$field->table])) $row[$field->table] = array();
        $params[] = &$row[$field->table][$field->name];
      }
    }
    //legacy (PHP 7.4)
    $ref    = new \ReflectionClass('mysqli_stmt');
    $method = $ref->getMethod("bind_result");
    $method->invokeArgs($stmt, $params);
    //$stmt->bind_result(...$params);

    $result = array();
    while ($stmt->fetch()) {
      $result[] = unserialize(serialize($row));
    }

    $stmt->close();

    return $result;
  }

  public static function insert($query)
  {
    $result = forward_static_call_array('DB::query', func_get_args());
    if (!is_int($result)) return false;
    if (!$result) return false;
    return static::$mysqli->insert_id;
  }

  public static function update($query)
  {
    $result = forward_static_call_array('DB::query', func_get_args());
    if (!is_int($result)) return false;
    return $result;
  }

  public static function delete($query)
  {
    $result = forward_static_call_array('DB::query', func_get_args());
    if (!is_int($result)) return false;
    return $result;
  }

  public static function select($query)
  {
    $result = forward_static_call_array('DB::query', func_get_args());
    if (!is_array($result)) return false;
    return $result;
  }

  public static function selectValue($query)
  {
    $result = forward_static_call_array('DB::query', func_get_args());
    if (!is_array($result)) return false;
    if (!isset($result[0])) return false;
    $record = $result[0];
    if (!is_array($record)) return false;
    $firstTable = array_shift($record);
    if (!is_array($firstTable)) return false;
    return array_shift($firstTable);
  }


  public static function selectValues($query)
  {
    $result = forward_static_call_array('DB::query', func_get_args());
    if (!is_array($result)) return false;
    $list = array();
    foreach ($result as $record) {
      if (!is_array($record)) return false;
      $firstTable = array_shift($record);
      if (!is_array($firstTable)) return false;
      $list[] = array_shift($firstTable);
    }
    return $list;
  }

  public static function selectPairs($query)
  {
    $result = forward_static_call_array('DB::query', func_get_args());
    if (!is_array($result)) return false;
    $list = array();
    foreach ($result as $record) {
      $firstTable = array_shift($record);
      $list[array_shift($firstTable)] = array_shift($firstTable);
    }
    return $list;
  }

  public static function selectOne($query)
  {
    $result = forward_static_call_array('DB::query', func_get_args());
    if (!is_array($result)) return false;
    if (isset($result[0])) return $result[0];
    return $result;
  }

  public static function close()
  {
    if (static::$mysqli) {
      static::$mysqli->close();
      static::$mysqli = null;
    }
    static::$closed = true;
  }

  // Undocumented
  public static function handle()
  {
    static::connect();
    return static::$mysqli;
  }
}
