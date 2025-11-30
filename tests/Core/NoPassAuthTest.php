<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\NoPassAuth;
use MintyPHP\Core\DB;
use MintyPHP\Core\Token;
use MintyPHP\Core\Totp;
use MintyPHP\Core\Session;
use MintyPHP\Core\Router;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for the Core NoPassAuth class.
 * 
 * This test suite covers passwordless user authentication, registration,
 * token generation, and remember-me functionality.
 */
class NoPassAuthTest extends TestCase
{
    private static DB $db;
    private static NoPassAuth $auth;
    private Totp&MockObject $totp;

    public static function setUpBeforeClass(): void
    {
        // Create database connection
        self::$db = new DB(null, 'mintyphp_test', 'mintyphp_test', 'mintyphp_test', null, null);

        // Drop and recreate users table
        self::$db->query('DROP TABLE IF EXISTS `users`;');
        self::$db->query('CREATE TABLE `users` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`username` varchar(255) COLLATE utf8_bin NOT NULL,
			`password` varchar(255) COLLATE utf8_bin NOT NULL,
            `totp_secret` varchar(255) COLLATE utf8_bin DEFAULT NULL,
            `remember_token` varchar(255) COLLATE utf8_bin DEFAULT NULL,
            `remember_expires` datetime DEFAULT NULL,
			`created` datetime NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `username` (`username`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;');
    }

    public function setUp(): void
    {
        // Create mock instances per test
        $this->totp = $this->createMock(Totp::class);
        $session = $this->createMock(Session::class);

        // Create Core NoPassAuth instance with mocks
        self::$auth = new NoPassAuth(
            self::$db,
            $this->totp,
            $session,
            'users',
            'username',
            'password',
            'remember_token',
            'remember_expires',
            'created',
            'totp_secret',
            300,
            90,
            'HS256',
            'mintyphp',
            '/'
        );

        // Set up server variables
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    public function testRegister(): void
    {
        $this->assertNotNull(self::$auth);
        $registered = self::$auth->register('testuser');
        $this->assertGreaterThan(0, $registered, 'user not registered');
    }

    public function testToken(): void
    {
        $this->assertNotNull(self::$auth);

        $token = self::$auth->token('testuser');
        $this->assertNotEmpty($token, 'token generation failed');
        $this->assertStringContainsString('.', $token, 'token is not a JWT');
    }

    public function testTokenNonExistentUser(): void
    {
        $this->assertNotNull(self::$auth);

        $token = self::$auth->token('nonexistent');
        $this->assertEmpty($token, 'token should be empty for non-existent user');
    }

    public function testLogin(): void
    {
        $this->assertNotNull(self::$auth);

        // Generate a valid token
        $token = self::$auth->token('testuser');
        $this->assertNotEmpty($token);

        // Mock TOTP to return true for verification
        $this->totp->expects($this->once())
            ->method('verify')
            ->with('', '')
            ->willReturn(true);

        $result = self::$auth->login($token);
        $this->assertNotEmpty($result, 'login failed');
        $this->assertArrayHasKey('users', $result);
        $this->assertEquals('testuser', $result['users']['username']);
    }

    public function testLoginWithInvalidToken(): void
    {
        $this->assertNotNull(self::$auth);

        $result = self::$auth->login('invalid.token.here');
        $this->assertEmpty($result, 'login should fail with invalid token');
    }

    public function testLoginWithWrongIP(): void
    {
        $this->assertNotNull(self::$auth);

        // Generate a valid token
        $token = self::$auth->token('testuser');
        $this->assertNotEmpty($token);

        // Change IP address
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $result = self::$auth->login($token);
        $this->assertEmpty($result, 'login should fail with wrong IP');
    }

    public function testLoginWithTotpFailure(): void
    {
        $this->assertNotNull(self::$auth);

        // Update user with TOTP secret
        self::$auth->updateTotpSecret('testuser', 'TESTSECRET123456');

        // Generate a valid token
        $token = self::$auth->token('testuser');
        $this->assertNotEmpty($token);

        // Mock TOTP to return false for verification
        $this->totp->expects($this->once())
            ->method('verify')
            ->with('TESTSECRET123456', '')
            ->willReturn(false);

        $result = self::$auth->login($token);
        $this->assertEmpty($result, 'login should return empty array when TOTP verification fails');
    }

    public function testUpdate(): void
    {
        $this->assertNotNull(self::$auth);

        $updated = self::$auth->update('testuser');
        $this->assertEquals(1, $updated, 'user not updated');
    }

    public function testUpdateTotpSecret(): void
    {
        $this->assertNotNull(self::$auth);

        $updated = self::$auth->updateTotpSecret('testuser', 'NEWSECRET123456');
        $this->assertEquals(1, $updated, 'TOTP secret not updated');
    }

    public function testExists(): void
    {
        $this->assertNotNull(self::$auth);

        $exists = self::$auth->exists('testuser');
        $this->assertNotFalse($exists, 'user should exist');

        $notExists = self::$auth->exists('nonexistent');
        $this->assertFalse($notExists, 'user should not exist');
    }
    public function testLogout(): void
    {
        $this->assertNotNull(self::$auth);
        $_SESSION['user'] = array('id' => 1, 'username' => 'testuser');
        $_SESSION['other'] = 'data';
        $_SESSION['debugger'] = 'debug_data';

        $result = self::$auth->logout();
        $this->assertTrue($result, 'logout failed');
        $this->assertArrayNotHasKey('user', $_SESSION, 'user session not cleared');
        $this->assertArrayNotHasKey('other', $_SESSION, 'other session not cleared');
        $this->assertArrayHasKey('debugger', $_SESSION, 'debugger session should be preserved');
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test database
        self::$db->query('DROP TABLE IF EXISTS `users`;');
    }
}
