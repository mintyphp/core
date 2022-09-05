<?php
namespace MintyPHP\Tests;

use MintyPHP\Auth;

class AuthTest extends \PHPUnit\Framework\TestCase
{
	public static $db;

	public static function setUpBeforeClass(): void
	{
		DBTest::setUpBeforeClass();
		self::$db = new DBTest();
		self::$db->testDropUsersBefore();
		self::$db->testCreateUsers();
	}

	public function testRegister()
	{
		$registered = Auth::register('test', 'test');
		$this->assertNotFalse($registered, 'user not registered');
	}

	public function testLogin()
	{
		try {
			Auth::login('test', 'test');
			$session_regenerated = false;
		} catch (\Exception $e) {
			$session_regenerated = explode(':',$e->getMessage())[0] == "session_regenerate_id()";
		}
		$this->assertTrue($session_regenerated, 'session not regenerated');
	}

	public function testLogout()
	{
		$_SESSION['user'] = array('id' => 1, 'username' => 'test');
		try {
			Auth::logout();
			$session_regenerated = false;
		} catch (\Exception $e) {
			$session_regenerated = explode(':',$e->getMessage())[0] == "session_regenerate_id()";
		}
		$this->assertTrue($session_regenerated, 'session not regenerated');
		$this->assertFalse(isset($_SESSION['user']), 'user not unset');
	}

	public static function tearDownAfterClass(): void
	{
		self::$db->testDropUsers();
	}
}
