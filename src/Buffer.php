<?php

namespace MintyPHP;

use MintyPHP\Core\Buffer as CoreBuffer;

/**
 * Static class for Buffer operations using a singleton pattern.
 */
class Buffer
{
	/**
	 * The Buffer instance
	 * @var ?CoreBuffer
	 */
	private static ?CoreBuffer $instance = null;

	/**
	 * Get the Buffer instance
	 * @return CoreBuffer
	 */
	public static function getInstance(): CoreBuffer
	{
		return self::$instance ??= new CoreBuffer();
	}

	/**
	 * Set the Buffer instance to use
	 * @param CoreBuffer $buffer
	 * @return void
	 */
	public static function setInstance(CoreBuffer $buffer): void
	{
		self::$instance = $buffer;
	}

	/**
	 * Start capturing output into a named buffer.
	 * 
	 * @param string $name The name of the buffer.
	 * @return void
	 */
	public static function start(string $name): void
	{
		$buffer = self::getInstance();
		$buffer->start($name);
	}

	/**
	 * End capturing output and store it in the named buffer.
	 * 
	 * @param string $name The name of the buffer.
	 * @return void
	 */
	public static function end(string $name): void
	{
		$buffer = self::getInstance();
		$buffer->end($name);
	}

	/**
	 * Set the content of a named buffer directly.
	 * 
	 * @param string $name The name of the buffer.
	 * @param string $string The content to store in the buffer.
	 * @return void
	 */
	public static function set(string $name, string $string): void
	{
		$buffer = self::getInstance();
		$buffer->set($name, $string);
	}

	/**
	 * Get and output the content of a named buffer.
	 * 
	 * @param string $name The name of the buffer.
	 * @return bool Returns true if the buffer exists and was output, false otherwise.
	 */
	public static function get(string $name): bool
	{
		$buffer = self::getInstance();
		return $buffer->get($name);
	}
}
