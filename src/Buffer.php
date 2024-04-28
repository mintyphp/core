<?php

namespace MintyPHP;

class Buffer
{
	/** @var array<string> */
	protected static array $stack = [];
	/** @var array<string,string> */
	protected static array $data = [];

	protected static function error(string $message): never
	{
		throw new BufferError($message);
	}

	public static function start(string $name): void
	{
		array_push(self::$stack, $name);
		ob_start();
	}

	public static function end(string $name): void
	{
		$top = array_pop(self::$stack);
		if ($top != $name) {
			self::error("Buffer::end('$name') called, but Buffer::end('$top') expected.");
		}
		self::$data[$name] = ob_get_contents() ?: '';
		ob_end_clean();
	}

	public static function set(string $name, string $string): void
	{
		self::$data[$name] = $string;
	}

	public static function get(string $name): bool
	{
		if (!isset(self::$data[$name])) return false;
		echo self::$data[$name];
		return true;
	}
}
