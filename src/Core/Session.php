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
     * Actual configuration parameters
     */
    private string $sessionId;
    private string $sessionName;
    private string $csrfSessionKey;
    private bool $enabled;
    private int $csrfLength;
    private string $sameSite;
    private ?Debugger $debugger;

    private bool $initialized = false;
    private bool $started = false;
    private bool $ended = false;

    public function __construct(
        string $sessionId = '',
        string $sessionName = 'mintyphp',
        string $csrfSessionKey = 'csrf_token',
        bool $enabled = true,
        int $csrfLength = 16,
        string $sameSite = 'Lax'
    ) {
        $this->sessionId = $sessionId;
        $this->sessionName = $sessionName;
        $this->csrfSessionKey = $csrfSessionKey;
        $this->enabled = $enabled;
        $this->csrfLength = $csrfLength;
        $this->sameSite = $sameSite;
        $this->debugger = Debugger::isEnabled() ? Debugger::getInstance() : null;
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;
        $this->start();
        $this->setCsrfToken();
    }

    private function setCsrfToken(): void
    {
        if (!$this->enabled) {
            return;
        }

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

    public function regenerate(): void
    {
        if (!$this->enabled) {
            return;
        }

        session_regenerate_id(true);
        unset($_SESSION[$this->csrfSessionKey]);
        $this->setCsrfToken();
    }

    public function start(): void
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if ($this->started) {
            return;
        }

        $debuggerEnabled = $this->debugger && $this->debugger->isEnabled();
        if ($this->enabled || $debuggerEnabled) {
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
            if (!$this->enabled && $this->debugger) {
                $debuggerSessionKey = Debugger::$__sessionKey;
                foreach ($_SESSION as $k => $v) {
                    if ($k != $debuggerSessionKey) {
                        unset($_SESSION[$k]);
                    }
                }
            }
        }
        $this->started = true;
        if ($debuggerEnabled && $this->debugger) {
            $debuggerSessionKey = Debugger::$__sessionKey;
            if (!isset($_SESSION[$debuggerSessionKey])) {
                $_SESSION[$debuggerSessionKey] = [];
            }
            $this->debugger->logSessionBefore();
        }
    }

    public function end(): void
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if ($this->ended) {
            return;
        }

        $this->ended = true;
        $debuggerEnabled = $this->debugger && $this->debugger->isEnabled();
        if ($this->enabled && !$debuggerEnabled) {
            session_write_close();
        }

        if ($debuggerEnabled && $this->debugger) {
            $this->debugger->logSessionAfter();
        }

        if ($debuggerEnabled) {
            $session = $_SESSION;
            unset($_SESSION);
            $_SESSION = $session;
        }
    }

    public function checkCsrfToken(): bool
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if (!$this->enabled) {
            return true;
        }

        $success = false;
        if (isset($_POST[$this->csrfSessionKey])) {
            $success = $_POST[$this->csrfSessionKey] == $_SESSION[$this->csrfSessionKey];
        }
        return $success;
    }

    public function getCsrfInput(): void
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if (!$this->enabled) {
            return;
        }

        $this->setCsrfToken();
        echo '<input type="hidden" name="' . $this->csrfSessionKey . '" value="' . $_SESSION[$this->csrfSessionKey] . '"/>';
    }
}
