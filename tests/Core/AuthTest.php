<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\Auth;
use MintyPHP\Core\DB;
use MintyPHP\Core\Totp;
use MintyPHP\Core\Session;
use MintyPHP\Error\TotpError;
use PHPUnit\Framework\MockObject\MockObject;
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
    private Totp&MockObject $totp;
    private Session&MockObject $session;

    public static function setUpBeforeClass(): void
    {
        // Create database connection
        self::$db = new DB(null, 'mintyphp_test', 'mintyphp_test', 'mintyphp_test', null, null);

        // Drop and recreate users table
        self::$db->query('DROP TABLE IF EXISTS `users`;');
        // Read schema file containing users table creation
        $schema = file_get_contents(__DIR__ . '/../Schemas/Auth.sql') ?: '';
        // Execute schema to create users table
        self::$db->query($schema);
    }

    public function setUp(): void
    {
        // Create mock instances per test
        $this->totp = $this->createMock(Totp::class);
        $this->session = $this->createMock(Session::class);

        // Create Core Auth instance with mocks
        self::$auth = new Auth(
            self::$db,
            $this->totp,
            $this->session,
            'users',
            'username',
            'password',
            'created',
            'totp_secret'
        );
    }

    public function testRegister(): void
    {
        $this->assertNotNull(self::$auth);
        $registered = self::$auth->register('test', 'test');
        $this->assertNotFalse($registered, 'user not registered');
    }

    /**
     * @depends testRegister
     */
    public function testLogin(): void
    {
        $this->assertNotNull(self::$auth);

        // Mock TOTP to return true for verification
        $this->totp->expects($this->once())
            ->method('verify')
            ->with('', '')
            ->willReturn(true);

        // Mock Session to expect regenerate() to be called
        $this->session->expects($this->once())
            ->method('regenerate');

        $result = self::$auth->login('test', 'test');
        $this->assertNotEmpty($result, 'login failed');
        $this->assertArrayHasKey('users', $result);
    }

    /**
     * @depends testRegister
     */
    public function testLoginTotpFailure(): void
    {
        $this->assertNotNull(self::$auth);

        // Mock TOTP to return false for verification
        $this->totp->expects($this->once())
            ->method('verify')
            ->with('', '')
            ->willReturn(false);

        $this->expectException(TotpError::class);
        self::$auth->login('test', 'test');
    }

    public function testLogout(): void
    {
        $this->assertNotNull(self::$auth);
        $_SESSION['user'] = array('id' => 1, 'username' => 'test');

        // Mock Session to expect regenerate() to be called
        $this->session->expects($this->once())
            ->method('regenerate');

        self::$auth->logout();
        $this->assertFalse(isset($_SESSION['user']), 'user not unset');
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up database
        self::$db->query('DROP TABLE IF EXISTS `users`;');
    }
}
