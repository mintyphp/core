<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\Auth;
use MintyPHP\Core\DB;
use PHPUnit\Framework\TestCase;

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

        // Set error handler to catch session warnings
        set_error_handler([self::class, 'throwErrorException'], E_ALL);
    }

    public static function throwErrorException(int $errNo, string $errstr, string $errFile, int $errLine): bool
    {
        throw new \Exception($errstr, $errNo);
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
        try {
            self::$auth->login('test', 'test');
            $session_regenerated = false;
        } catch (\Exception $e) {
            $session_regenerated = explode(':', $e->getMessage())[0] == "session_regenerate_id()";
        }
        $this->assertTrue($session_regenerated, 'session not regenerated');
    }

    public function testLogout(): void
    {
        $this->assertNotNull(self::$auth);
        $_SESSION['user'] = array('id' => 1, 'username' => 'test');
        try {
            self::$auth->logout();
            $session_regenerated = false;
        } catch (\Exception $e) {
            $session_regenerated = explode(':', $e->getMessage())[0] == "session_regenerate_id()";
        }
        $this->assertTrue($session_regenerated, 'session not regenerated');
        $this->assertFalse(isset($_SESSION['user']), 'user not unset');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        // Restore error handler
        restore_error_handler();

        // Clean up database
        self::$db->query('DROP TABLE IF EXISTS `users`;');
    }
}
