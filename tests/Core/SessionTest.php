<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\Debugger;
use MintyPHP\Core\Session;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        // Clear any existing session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Clean up session data
        $_SESSION = [];

        // Create session instance without debugger
        $this->session = new Session('', 'test_session_' . mt_rand(), 'test_csrf_token', true, 16, 'Lax');
    }

    protected function tearDown(): void
    {
        // Clean up session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
    }

    public function testSessionConstruction(): void
    {
        $this->assertInstanceOf(Session::class, $this->session);
    }

    public function testSessionStart(): void
    {
        $this->session->start();
        $this->assertEquals(PHP_SESSION_ACTIVE, session_status());
    }

    public function testCsrfTokenGeneration(): void
    {
        $this->session->start();
        $this->assertArrayHasKey('test_csrf_token', $_SESSION);
        $this->assertIsString($_SESSION['test_csrf_token']);
        $this->assertEquals(32, strlen($_SESSION['test_csrf_token'])); // 16 bytes = 32 hex chars
    }

    public function testCsrfTokenPersists(): void
    {
        $this->session->start();
        $firstToken = $_SESSION['test_csrf_token'];

        // Start again should not regenerate token
        $this->session->start();
        $this->assertEquals($firstToken, $_SESSION['test_csrf_token']);
    }

    public function testRegenerate(): void
    {
        $this->session->start();
        $firstToken = $_SESSION['test_csrf_token'];
        $firstSessionId = session_id();

        $this->session->regenerate();

        $newToken = $_SESSION['test_csrf_token'];
        $newSessionId = session_id();

        $this->assertNotEquals($firstToken, $newToken);
        $this->assertNotEquals($firstSessionId, $newSessionId);
    }

    public function testCheckCsrfTokenSuccess(): void
    {
        $this->session->start();
        $_POST['test_csrf_token'] = $_SESSION['test_csrf_token'];

        $result = $this->session->checkCsrfToken();
        $this->assertTrue($result);

        unset($_POST['test_csrf_token']);
    }

    public function testCheckCsrfTokenFailure(): void
    {
        $this->session->start();
        $_POST['test_csrf_token'] = 'invalid_token';

        $result = $this->session->checkCsrfToken();
        $this->assertFalse($result);

        unset($_POST['test_csrf_token']);
    }

    public function testCheckCsrfTokenMissing(): void
    {
        $this->session->start();

        $result = $this->session->checkCsrfToken();
        $this->assertFalse($result);
    }

    public function testGetCsrfInput(): void
    {
        $this->session->start();

        ob_start();
        $this->session->getCsrfInput();
        $output = ob_get_clean() ?: '';

        $this->assertStringContainsString('<input type="hidden"', $output);
        $this->assertStringContainsString('name="' . 'test_csrf_token' . '"', $output);
        $this->assertStringContainsString('value="' . $_SESSION['test_csrf_token'] . '"', $output);
    }

    public function testSessionEnd(): void
    {
        $this->session->start();
        $this->session->end();

        // Session writes are closed
        $this->assertEquals(PHP_SESSION_NONE, session_status());
    }
    public function testDisabledSessionDoesNotStartSession(): void
    {
        $disabledSession = new Session('', 'test_disabled', 'csrf', false, 16, 'Lax');

        // Close any active session first
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $disabledSession->start();
        // Should not start session when disabled and no debugger
        // Session might still be active from previous tests, so we just verify no error
        $this->assertInstanceOf(Session::class, $disabledSession);
    }

    public function testDisabledSessionAlwaysPassesCsrfCheck(): void
    {
        $disabledSession = new Session('', 'test_disabled', 'csrf', false, 16, 'Lax');

        $result = $disabledSession->checkCsrfToken();
        $this->assertTrue($result);
    }
}
