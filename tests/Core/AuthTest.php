<?php

namespace MintyPHP\Tests\Core;

use Exception;
use MintyPHP\Core\Auth;
use MintyPHP\Core\DB;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for the Core Auth class.
 * 
 * This test suite covers user registration, login, and logout functionalities.
 * It sets up a temporary users table in a test database and cleans up after tests.
 * 
 * Note: These tests require a MySQL database named 'mintyphp_test
 * to be set up and accessible with the appropriate credentials.
 * Adjust the connection parameters in setUpBeforeClass() as needed.
 * These tests will create and drop tables in the database,
 * so ensure that it is safe to do so.
 */
class AuthTest extends TestCase
{
    private static DB $db;
    private static Auth $auth;

    public static function setUpBeforeClass(): void
    {
        // Create database connection
        self::$db = new DB(null, 'mintyphp_test', 'mintyphp_test', 'mintyphp_test', null, null);

        // Create Core Auth instance
        self::$auth = new Auth(self::$db);

        // Drop and recreate users table
        self::$db->query('DROP TABLE IF EXISTS `users`;');
        self::$db->query('CREATE TABLE `users` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`username` varchar(255) COLLATE utf8_bin NOT NULL,
			`password` varchar(255) COLLATE utf8_bin NOT NULL,
			`created` datetime NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `username` (`username`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;');
    }

    public function testRegister(): void
    {
        $this->assertNotNull(self::$auth);
        $registered = self::$auth->register('test', 'test');
        $this->assertNotFalse($registered, 'user not registered');
    }

    public function testLogin(): void
    {
        $this->assertNotNull(self::$auth);
        // assert that session has had a call to regenerate_id
        $session_regenerated = false;
        try {
            self::$auth->login('test', 'test');
        } catch (Exception $e) {
            $session_regenerated = explode(':', $e->getMessage())[0] == "session_regenerate_id()";
        }
        $this->assertTrue($session_regenerated, 'session not regenerated on login');
    }

    public function testLogout(): void
    {
        $this->assertNotNull(self::$auth);
        $_SESSION['user'] = array('id' => 1, 'username' => 'test');
        // assert that session has had a call to regenerate_id
        $session_regenerated = false;
        try {
            self::$auth->logout();
        } catch (\Exception $e) {
            $session_regenerated = explode(':', $e->getMessage())[0] == "session_regenerate_id()";
        }
        $this->assertTrue($session_regenerated, 'session not regenerated');
        $this->assertFalse(isset($_SESSION['user']), 'user not unset');
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up database
        self::$db->query('DROP TABLE IF EXISTS `users`;');
    }
}
