<?php

namespace MintyPHP\Tests;

use MintyPHP\Auth;

class AuthTest extends \PHPUnit\Framework\TestCase
{
	public static DBTest $db;

	public static function setUpBeforeClass(): void
	{
		DBTest::setUpBeforeClass();
		self::$db = new DBTest("AuthTest");
		self::$db->testDropUsersBefore();
		self::$db->testCreateUsers();
		set_error_handler(static function (int $errno, string $errstr): never {
			throw new \Exception($errstr, $errno);
		}, E_ALL);
	}

	public function testRegister(): void
	{
		$registered = Auth::register('test', 'test');
		$this->assertNotFalse($registered, 'user not registered');
	}

	public function testLogin(): void
	{
		try {
			Auth::login('test', 'test');
			$session_regenerated = false;
		} catch (\Exception $e) {
			$session_regenerated = explode(':', $e->getMessage())[0] == "session_regenerate_id()";
		}
		$this->assertTrue($session_regenerated, 'session not regenerated');
	}

	public function testLogout(): void
	{
		$_SESSION['user'] = array('id' => 1, 'username' => 'test');
		try {
			Auth::logout();
			$session_regenerated = false;
		} catch (\Exception $e) {
			$session_regenerated = explode(':', $e->getMessage())[0] == "session_regenerate_id()";
		}
		$this->assertTrue($session_regenerated, 'session not regenerated');
		$this->assertFalse(isset($_SESSION['user']), 'user not unset');
	}

	public static function tearDownAfterClass(): void
	{
		restore_error_handler();
		self::$db->testDropUsers();
	}
}
