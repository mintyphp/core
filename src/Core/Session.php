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
    public static string|false $__sessionId = false;
    public static string $__sessionName = 'mintyphp';
    public static string $__csrfSessionKey = 'csrf_token';
    public static bool $__enabled = true;
    public static int $__csrfLength = 16;
    public static string $__sameSite = 'Lax';

    private bool $initialized = false;
    private bool $started = false;
    private bool $ended = false;

    public function __construct(
        string|false $sessionId = false,
        string $sessionName = 'mintyphp',
        string $csrfSessionKey = 'csrf_token',
        bool $enabled = true,
        int $csrfLength = 16,
        string $sameSite = 'Lax'
    ) {
        $this->__sessionId = $sessionId;
        $this->__sessionName = $sessionName;
        $this->__csrfSessionKey = $csrfSessionKey;
        $this->__enabled = $enabled;
        $this->__csrfLength = $csrfLength;
        $this->__sameSite = $sameSite;
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
        if (!$this->__enabled) {
            return;
        }

        if (isset($_SESSION[$this->__csrfSessionKey])) {
            return;
        }

        $length = $this->__csrfLength;
        if ($length < 1) {
            $length = 16;
        }
        $buffer = random_bytes($length);

        $_SESSION[$this->__csrfSessionKey] = bin2hex($buffer);
    }

    public function regenerate(): void
    {
        if (!$this->__enabled) {
            return;
        }

        session_regenerate_id(true);
        unset($_SESSION[$this->__csrfSessionKey]);
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
        if ($this->__enabled || $debuggerEnabled) {
            if (!ini_get('session.cookie_samesite')) {
                ini_set('session.cookie_samesite', $this->__sameSite);
            }
            if (!ini_get('session.cookie_httponly')) {
                ini_set('session.cookie_httponly', '1');
            }
            if (!ini_get('session.cookie_secure') && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                ini_set('session.cookie_secure', '1');
            }
            session_name($this->__sessionName);
            if ($this->__sessionId) {
                session_id($this->__sessionId);
            }
            session_start();
            if (!$this->__enabled && $this->debugger) {
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
        if ($this->__enabled && !$debuggerEnabled) {
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

        if (!$this->__enabled) {
            return true;
        }

        $success = false;
        if (isset($_POST[$this->__csrfSessionKey])) {
            $success = $_POST[$this->__csrfSessionKey] == $_SESSION[$this->__csrfSessionKey];
        }
        return $success;
    }

    public function getCsrfInput(): void
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if (!$this->__enabled) {
            return;
        }

        $this->setCsrfToken();
        echo '<input type="hidden" name="' . $this->__csrfSessionKey . '" value="' . $_SESSION[$this->__csrfSessionKey] . '"/>';
    }
}
