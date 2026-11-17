<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\NoPassAuth;
use MintyPHP\Core\DB;
use MintyPHP\Core\Token;
use MintyPHP\Core\Totp;
use MintyPHP\Core\Session;
use MintyPHP\Core\Router;
use MintyPHP\Error\TotpError;
use MintyPHP\Mocking\BuiltInFunctionMock;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
        // Read schema file containing users table creation
        $schema = file_get_contents(__DIR__ . '/../Schemas/NoPassAuth.sql') ?: '';
        // Execute schema to create users table
        self::$db->query($schema);
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

    #[AllowMockObjectsWithoutExpectations]
    public function testRegister(): void
    {
        $registered = self::$auth->register('testuser');
        $this->assertGreaterThan(0, $registered, 'user not registered');
    }

    /**
     * @depends testRegister
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testToken(): void
    {

        $token = self::$auth->token('testuser');
        $this->assertNotEmpty($token, 'token generation failed');
        $this->assertStringContainsString('.', $token, 'token is not a JWT');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testTokenNonExistentUser(): void
    {

        $token = self::$auth->token('nonexistent');
        $this->assertEmpty($token, 'token should be empty for non-existent user');
    }

    /**
     * @depends testRegister
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testLogin(): void
    {

        // Generate a valid token
        $token = self::$auth->token('testuser');
        $this->assertNotEmpty($token);

        // Mock TOTP to return true for verification (use any() to allow multiple calls or different secrets)
        $this->totp->expects($this->any())
            ->method('verify')
            ->willReturn(true);

        $result = self::$auth->login($token);
        $this->assertNotEmpty($result, 'login failed');
        $this->assertArrayHasKey('users', $result);
        $this->assertEquals('testuser', $result['users']['username']);
        $sessionUser = $_SESSION['user'] ?? null;
        $this->assertIsArray($sessionUser);
        $this->assertEquals('testuser', $sessionUser['username']);
        $this->assertArrayNotHasKey('password', $sessionUser);
        $this->assertArrayNotHasKey('totp_secret', $sessionUser);
        $this->assertArrayNotHasKey('remember_token', $sessionUser);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testLoginWithInvalidToken(): void
    {

        $result = self::$auth->login('invalid.token.here');
        $this->assertEmpty($result, 'login should fail with invalid token');
    }

    /**
     * @depends testRegister
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testLoginWithWrongIP(): void
    {

        // Generate a valid token
        $token = self::$auth->token('testuser');
        $this->assertNotEmpty($token);

        // Change IP address
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $result = self::$auth->login($token);
        $this->assertEmpty($result, 'login should fail with wrong IP');

        // Reset IP address for other tests
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    /**
     * @depends testRegister
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testLoginWithTotpFailure(): void
    {

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

        $this->expectException(TotpError::class);
        self::$auth->login($token);
    }
    /**
     * @depends testRegister
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testUpdate(): void
    {

        $updated = self::$auth->update('testuser');
        $this->assertEquals(1, $updated, 'user not updated');
    }

    /**
     * @depends testRegister
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testUpdateTotpSecret(): void
    {

        $updated = self::$auth->updateTotpSecret('testuser', 'NEWSECRET123456');
        $this->assertEquals(1, $updated, 'TOTP secret not updated');
    }

    /**
     * @depends testRegister
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testExists(): void
    {

        $exists = self::$auth->exists('testuser');
        $this->assertNotFalse($exists, 'user should exist');

        $notExists = self::$auth->exists('nonexistent');
        $this->assertFalse($notExists, 'user should not exist');
    }
    #[AllowMockObjectsWithoutExpectations]
    public function testLogout(): void
    {
        $_SESSION['user'] = ['id' => 1, 'username' => 'testuser'];
        $_SESSION['other'] = 'data';
        $_SESSION['debugger'] = 'debug_data';

        $result = self::$auth->logout();
        $this->assertTrue($result, 'logout failed');
        $this->assertArrayNotHasKey('user', $_SESSION, 'user session not cleared');
        $this->assertArrayNotHasKey('other', $_SESSION, 'other session not cleared');
        $this->assertArrayHasKey('debugger', $_SESSION, 'debugger session should be preserved');
    }

    /**
     * Store a known remember token for the user and put it in the cookie.
     */
    private function rememberUser(string $username, string $token): void
    {
        self::$db->update(
            'update `users` set `remember_token` = ?, `remember_expires` = DATE_ADD(NOW(), INTERVAL 1 DAY) where `username` = ?',
            password_hash($token, PASSWORD_DEFAULT),
            $username
        );
        $_COOKIE['mintyphp_remember'] = "$username:$token";
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testLogoutInvalidatesRememberToken(): void
    {
        self::$auth->register('rememberuser');
        $this->rememberUser('rememberuser', 'known-token');
        $this->assertTrue(self::$auth->remember(), 'remember should work before logout');
        $sessionUser = $_SESSION['user'] ?? null;
        $this->assertIsArray($sessionUser);
        $this->assertArrayNotHasKey('password', $sessionUser);
        $this->assertArrayNotHasKey('totp_secret', $sessionUser);
        $this->assertArrayNotHasKey('remember_token', $sessionUser);

        // no cookie in this request, so setcookie is not called
        unset($_COOKIE['mintyphp_remember']);
        $_SESSION['user'] = ['id' => 2, 'username' => 'rememberuser'];
        self::$auth->logout();

        $row = self::$db->selectOne('select * from `users` where `username` = ?', 'rememberuser');
        $this->assertIsArray($row);
        $this->assertNull($row['users']['remember_token']);
        $this->assertNull($row['users']['remember_expires']);

        // a stolen cookie no longer works after logout
        $_COOKIE['mintyphp_remember'] = 'rememberuser:known-token';
        $this->assertFalse(self::$auth->remember(), 'remember should fail after logout');
        unset($_COOKIE['mintyphp_remember']);
    }

    #[RunInSeparateProcess]
    #[AllowMockObjectsWithoutExpectations]
    public function testLogoutRemovesCookieWithSameDomainAndPath(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com:443';
        self::$auth->register('cookieuser');
        $this->rememberUser('cookieuser', 'known-token');
        $_SESSION['user'] = ['id' => 3, 'username' => 'cookieuser'];

        $mock = new BuiltInFunctionMock('MintyPHP\Core', $this);
        $mock->expect('setcookie', ['mintyphp_remember', '', [
            'expires' => 1,
            'path' => '/',
            'domain' => 'example.com',
            'secure' => true,
            'httponly' => true,
        ]], true);

        self::$auth->logout();
        $mock->assertExpectationsMet();
        $this->assertArrayNotHasKey('mintyphp_remember', $_COOKIE);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test database
        self::$db->query('DROP TABLE IF EXISTS `users`;');
    }
}
