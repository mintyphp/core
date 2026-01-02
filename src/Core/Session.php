<?php

namespace MintyPHP\Core;

/**
 * Session management class for handling PHP sessions with security features.
 * 
 * Provides session initialization, CSRF token management, and secure session
 * configuration with support for debugging mode.
 */
class Session
{
    /**
     * Static configuration parameters
     */
    public static string $__sessionId = '';
    public static string $__sessionName = 'mintyphp';
    public static string $__csrfSessionKey = 'csrf_token';
    public static bool $__enabled = true;
    public static int $__csrfLength = 16;
    public static string $__sameSite = 'Lax';

    /**
     * Indicates whether the session has been initialized.
     */
    private bool $started = false;

    /**
     * Indicates whether the session has been ended.
     */
    private bool $ended = false;

    /**
     * Create a new Session instance
     * 
     * @param string $sessionId Optional session ID to use
     * @param string $sessionName Name of the session cookie
     * @param string $csrfSessionKey Key in $_SESSION to store CSRF token
     * @param bool $enabled Whether session management is enabled
     * @param int $csrfLength Length of the CSRF token in bytes
     * @param string $sameSite SameSite attribute for session cookie
     * @param ?Debugger $debugger Optional Debugger instance for logging
     */
    public function __construct(
        private readonly string $sessionId = '',
        private readonly string $sessionName = 'mintyphp',
        private readonly string $csrfSessionKey = 'csrf_token',
        private readonly bool $enabled = true,
        private readonly int $csrfLength = 16,
        private readonly string $sameSite = 'Lax',
        private ?Debugger $debugger = null
    ) {
        // Initialize session
        $this->start();
        $this->setCsrfToken();
    }

    /**
     * Set the CSRF token in the session if not already set
     * @return void
     */
    private function setCsrfToken(): void
    {
        if (isset($_SESSION[$this->csrfSessionKey])) {
            return;
        }

        $length = $this->csrfLength;
        if ($length < 1) {
            $length = 16;
        }
        $buffer = random_bytes($length);

        $_SESSION[$this->csrfSessionKey] = bin2hex($buffer);
    }

    /**
     * Regenerate the session ID and CSRF token
     * @return void
     */
    public function regenerate(): void
    {
        if (!$this->enabled) {
            return;
        }

        session_regenerate_id(true);
        unset($_SESSION[$this->csrfSessionKey]);
        $this->setCsrfToken();
    }

    /**
     * Start the session
     * @return void
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        if ($this->enabled || $this->debugger !== null) {
            if (!ini_get('session.cookie_samesite')) {
                ini_set('session.cookie_samesite', $this->sameSite);
            }
            if (!ini_get('session.cookie_httponly')) {
                ini_set('session.cookie_httponly', '1');
            }
            if (!ini_get('session.cookie_secure') && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                ini_set('session.cookie_secure', '1');
            }
            session_name($this->sessionName);
            if ($this->sessionId) {
                session_id($this->sessionId);
            }
            session_start();
        }
        $this->started = true;

        if ($this->debugger !== null) {
            $this->debugger->logSessionBefore();
        }
    }

    /**
     * End the session
     * @return void
     */
    public function end(): void
    {
        if ($this->ended) {
            return;
        }

        $this->ended = true;
        if ($this->enabled && $this->debugger === null) {
            session_write_close();
        }

        if ($this->debugger !== null) {
            $this->debugger->logSessionAfter();
        }

        if ($this->debugger !== null) {
            $session = $_SESSION;
            unset($_SESSION);
            $_SESSION = $session;
        }
    }

    /**
     * Check the CSRF token from the POST data against the session
     * @return bool True if the token is valid, false otherwise
     */
    public function checkCsrfToken(): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $success = false;
        if (isset($_POST[$this->csrfSessionKey])) {
            $success = $_POST[$this->csrfSessionKey] == $_SESSION[$this->csrfSessionKey];
        }
        return $success;
    }

    /**
     * Output a hidden input field with the CSRF token
     * @return void
     */
    public function getCsrfInput(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->setCsrfToken();
        echo '<input type="hidden" name="' . $this->csrfSessionKey . '" value="' . $_SESSION[$this->csrfSessionKey] . '"/>';
    }
}
