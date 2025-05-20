<?php

namespace MintyPHP;

use Memcached;

class Cache
{
	public static string $prefix = 'mintyphp';
	public static string $servers = '127.0.0.1';

	/**
	 * @var ?Memcached
	 */
	protected static $memcache = null;

	protected static function initialize(): void
	{
		if (!self::$memcache) {
			self::$memcache = new Memcached();
			$servers = explode(',', self::$servers);
			$servers = array_map(function ($server) {
				$server = explode(':', trim($server));
				if (count($server) == 1) $server[1] = '11211';
				return $server;
			}, $servers);
			foreach ($servers as $server) {
				self::$memcache->addServer($server[0], intval($server[1]));
			}
		}
	}

	protected static function variable(mixed $var): string
	{
		$type = gettype($var);
		switch ($type) {
			case 'boolean':
				$result = $var ? 'TRUE' : 'FALSE';
				break;
			case 'integer':
				$result = $var;
				break;
			case 'NULL':
				$result = $var;
				break;
			case 'string':
				$result = '(string:' . strlen($var) . ')';
				break;
			case 'array':
				$result = '(array:' . count($var) . ')';
				break;
			default:
				$result = '(' . $type . ')';
		}
		return $result;
	}

	public static function add(string $key, mixed $var, int $expire = 0): bool
	{
		if (Debugger::$enabled) $time = microtime(true);
		if (!self::$memcache) self::initialize();
		$res = self::$memcache->add(self::$prefix . $key, $var, $expire);
		if (Debugger::$enabled) {
			$duration = microtime(true) - $time;
			$command = 'add';
			$arguments = array($key, self::variable($var));
			if ($expire) $arguments[] = $expire;
			$result = self::variable($res);
			Debugger::add('cache', compact('duration', 'command', 'arguments', 'result'));
		}
		return $res;
	}

	public static function decrement(string $key, int $value = 1): int|false
	{
		if (Debugger::$enabled) $time = microtime(true);
		if (!self::$memcache) self::initialize();
		$res = self::$memcache->decrement(self::$prefix . $key, $value);
		if (Debugger::$enabled) {
			$duration = microtime(true) - $time;
			$command = 'decrement';
			$arguments = array($key);
			if ($value > 1) $arguments[] = $value;
			$result = self::variable($res);
			Debugger::add('cache', compact('duration', 'command', 'arguments', 'result'));
		}
		return $res;
	}

	public static function delete(string $key): bool
	{
		if (Debugger::$enabled) $time = microtime(true);
		if (!self::$memcache) self::initialize();
		$res = self::$memcache->delete(self::$prefix . $key, 0);
		if (Debugger::$enabled) {
			$duration = microtime(true) - $time;
			$command = 'delete';
			$arguments = array($key);
			$result = self::variable($res);
			Debugger::add('cache', compact('duration', 'command', 'arguments', 'result'));
		}
		return $res;
	}

	public static function get(string $key): mixed
	{
		if (Debugger::$enabled) $time = microtime(true);
		if (!self::$memcache) self::initialize();
		$res = self::$memcache->get(self::$prefix . $key);
		if (Debugger::$enabled) {
			$duration = microtime(true) - $time;
			$command = 'get';
			$arguments = array($key);
			$result = self::variable($res);
			Debugger::add('cache', compact('duration', 'command', 'arguments', 'result'));
		}
		return $res;
	}

	public static function increment(string $key, int $value = 1): int|false
	{
		if (Debugger::$enabled) $time = microtime(true);
		if (!self::$memcache) self::initialize();
		$res = self::$memcache->increment(self::$prefix . $key, $value);
		if (Debugger::$enabled) {
			$duration = microtime(true) - $time;
			$command = 'increment';
			$arguments = array($key);
			if ($value > 1) $arguments[] = $value;
			$result = self::variable($res);
			Debugger::add('cache', compact('duration', 'command', 'arguments', 'result'));
		}
		return $res;
	}

	public static function replace(string $key, mixed $var, int $expire = 0): bool
	{
		if (Debugger::$enabled) $time = microtime(true);
		if (!self::$memcache) self::initialize();
		$res = self::$memcache->replace(self::$prefix . $key, $var, $expire);
		if (Debugger::$enabled) {
			$duration = microtime(true) - $time;
			$command = 'replace';
			$arguments = array($key, self::variable($var));
			if ($expire) $arguments[] = $expire;
			$result = self::variable($res);
			Debugger::add('cache', compact('duration', 'command', 'arguments', 'result'));
		}
		return $res;
	}

	public static function set(string $key, mixed $var, int $expire = 0): bool
	{
		if (Debugger::$enabled) $time = microtime(true);
		if (!self::$memcache) self::initialize();
		$res = self::$memcache->set(self::$prefix . $key, $var, $expire);
		if (Debugger::$enabled) {
			$duration = microtime(true) - $time;
			$command = 'set';
			$arguments = array($key, self::variable($var));
			if ($expire) $arguments[] = $expire;
			$result = self::variable($res);
			Debugger::add('cache', compact('duration', 'command', 'arguments', 'result'));
		}
		return $res;
	}
}
