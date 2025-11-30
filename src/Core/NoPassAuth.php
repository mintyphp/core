<?php

namespace MintyPHP\Core;

use MintyPHP\Core\DB;
use MintyPHP\Core\Token;
use MintyPHP\Core\Totp;
use MintyPHP\Error\TotpError;

/**
 * Passwordless Authentication for MintyPHP
 * 
 * Provides passwordless user authentication using time-based tokens,
 * with support for remember-me functionality and optional TOTP two-factor authentication.
 */
class NoPassAuth
{
    /**
     * Static configuration parameters
     */
    public static string $__usersTable = 'users';
    public static string $__usernameField = 'username';
    public static string $__passwordField = 'password';
    public static string $__rememberTokenField = 'remember_token';
    public static string $__rememberExpiresField = 'remember_expires';
    public static string $__createdField = 'created';
    public static string $__totpSecretField = 'totp_secret';
    public static int $__tokenValidity = 300;
    public static int $__rememberDays = 90;
    public static string $__tokenAlgorithm = 'HS256';
    public static string $__sessionName = 'mintyphp';
    public static string $__baseUrl = '/';

    /**
     * Actual configuration parameters
     */
    private readonly string $usersTable;
    private readonly string $usernameField;
    private readonly string $passwordField;
    private readonly string $rememberTokenField;
    private readonly string $rememberExpiresField;
    private readonly string $createdField;
    private readonly string $totpSecretField;
    private readonly int $tokenValidity;
    private readonly int $rememberDays;
    private readonly string $tokenAlgorithm;
    private readonly string $sessionName;
    private readonly string $baseUrl;

    /**
     * Database instance for executing queries.
     */
    private DB $db;

    /**
     * Totp instance for handling TOTP verification.
     */
    private Totp $totp;

    /**
     * Constructor for the NoPassAuth class.
     * 
     * @param DB $db Database instance for executing queries.
     * @param Totp $totp Totp instance for handling TOTP verification.
     * @param string $usersTable Name of the users table.
     * @param string $usernameField Name of the username field.
     * @param string $passwordField Name of the password field (used as secret for tokens).
     * @param string $rememberTokenField Name of the remember token field.
     * @param string $rememberExpiresField Name of the remember expires field.
     * @param string $createdField Name of the created timestamp field.
     * @param string $totpSecretField Name of the TOTP secret field.
     * @param int $tokenValidity Token validity in seconds.
     * @param int $rememberDays Number of days to remember the user.
     * @param string $tokenAlgorithm The algorithm to use for token generation.
     * @param string $sessionName The session name for cookies.
     * @param string $baseUrl The base URL for cookies.
     */
    public function __construct(
        DB $db,
        Totp $totp,
        string $usersTable = 'users',
        string $usernameField = 'username',
        string $passwordField = 'password',
        string $rememberTokenField = 'remember_token',
        string $rememberExpiresField = 'remember_expires',
        string $createdField = 'created',
        string $totpSecretField = 'totp_secret',
        int $tokenValidity = 300,
        int $rememberDays = 90,
        string $tokenAlgorithm = 'HS256',
        string $sessionName = 'mintyphp',
        string $baseUrl = '/'
    ) {
        $this->db = $db;
        $this->totp = $totp;
        $this->usersTable = $usersTable;
        $this->usernameField = $usernameField;
        $this->passwordField = $passwordField;
        $this->rememberTokenField = $rememberTokenField;
        $this->rememberExpiresField = $rememberExpiresField;
        $this->createdField = $createdField;
        $this->totpSecretField = $totpSecretField;
        $this->tokenValidity = $tokenValidity;
        $this->rememberDays = $rememberDays;
        $this->tokenAlgorithm = $tokenAlgorithm;
        $this->sessionName = $sessionName;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Generate a token for the given username.
     * 
     * Creates a JWT token containing the username and IP address,
     * using the user's password hash as the secret.
     * 
     * @param string $username The username to generate a token for.
     * @return string The generated token, or empty string if user not found.
     */
    public function token(string $username): string
    {
        $query = sprintf(
            'select * from `%s` where `%s` = ? limit 1',
            $this->usersTable,
            $this->usernameField
        );
        $user = $this->db->selectOne($query, $username);
        if ($user) {
            $table = $this->usersTable;
            $username = $user[$table][$this->usernameField];
            $password = $user[$table][$this->passwordField] ?? '';

            // Create a new Token instance with the user's password as secret
            if (!is_string($password) || $password === '') {
                return '';
            }

            $tokenInstance = new Token(
                $this->tokenAlgorithm,
                $password,
                5, // leeway
                $this->tokenValidity,
                '', // audience
                '', // issuer
                '', // algorithms
                '', // audiences
                '' // issuers
            );

            $token = $tokenInstance->getToken(['user' => $username, 'ip' => $_SERVER['REMOTE_ADDR']]);
        } else {
            $token = '';
        }
        return $token;
    }

    /**
     * Attempt to restore a user session from a remember-me cookie.
     * 
     * Checks for a valid remember-me cookie, verifies the token,
     * and restores the user session if valid.
     * 
     * @return bool True if session was restored, false otherwise.
     */
    public function remember(): bool
    {
        $name = $this->sessionName . '_remember';
        $value = $_COOKIE[$name] ?? '';
        $parts = explode(':', $value, 2);
        $username = $parts[0] ?? '';
        $token = $parts[1] ?? '';

        $query = sprintf(
            'select * from `%s` where `%s` = ? and `%s` > NOW() limit 1',
            $this->usersTable,
            $this->usernameField,
            $this->rememberExpiresField
        );
        $user = $this->db->selectOne($query, $username);
        if ($user) {
            $table = $this->usersTable;
            $username = $user[$table][$this->usernameField];
            $hash = $user[$table][$this->rememberTokenField] ?? '';
            if (is_string($hash) && password_verify($token, $hash)) {
                session_regenerate_id(true);
                $_SESSION['user'] = $user[$table];
                return true;
            }
        }
        return false;
    }

    /**
     * Remove the remember-me cookie.
     */
    private function unRemember(): void
    {
        $name = $this->sessionName . '_remember';
        if (isset($_COOKIE[$name])) {
            setcookie($name, '');
        }
    }

    /**
     * Create a remember-me cookie for the given username.
     * 
     * Generates a secure random token, stores its hash in the database,
     * and sets a cookie containing the username and token.
     * 
     * @param string $username The username to remember.
     */
    private function doRemember(string $username): void
    {
        $name = $this->sessionName . '_remember';
        $token = base64_encode(random_bytes(24));
        $hash = password_hash($token, PASSWORD_DEFAULT);
        $query = sprintf(
            'update `%s` set `%s` = ?, `%s` = DATE_ADD(NOW(), INTERVAL ? DAY) where `%s` = ? limit 1',
            $this->usersTable,
            $this->rememberTokenField,
            $this->rememberExpiresField,
            $this->usernameField
        );
        $this->db->update($query, $hash, $this->rememberDays, $username);
        $value = "$username:$token";
        $expires = strtotime('+' . $this->rememberDays . ' days');
        if ($expires === false) {
            $expires = time() + ($this->rememberDays * 86400);
        }
        $path = $this->baseUrl;
        $domain = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
        if (!$domain || $domain == 'localhost') {
            setcookie($name, $value, $expires, $path);
        } else {
            setcookie($name, $value, $expires, $path, $domain, true, true);
        }
    }

    /**
     * Authenticate a user with a token and optional TOTP code.
     * 
     * Verifies the JWT token signature and claims, checks TOTP if configured,
     * regenerates the session, and stores user data in the session.
     * 
     * @param string $token The JWT token to verify.
     * @param bool $rememberMe Whether to set a remember-me cookie.
     * @param string|null $totp Optional TOTP code for two-factor authentication.
     * @return array<string,array<string,mixed>> User data on success, empty array on failure.
     */
    public function login(string $token, bool $rememberMe = false, ?string $totp = null): array
    {
        $parts = explode('.', $token);
        $claims = isset($parts[1]) ? json_decode(base64_decode($parts[1]), true) : false;
        $username = (is_array($claims) && isset($claims['user'])) ? $claims['user'] : false;
        $query = sprintf(
            'select * from `%s` where `%s` = ? limit 1',
            $this->usersTable,
            $this->usernameField
        );
        $user = $this->db->selectOne($query, $username);
        if ($user) {
            $table = $this->usersTable;
            $usernameStr = $user[$table][$this->usernameField];
            if (!is_string($usernameStr)) {
                return [];
            }
            $password = $user[$table][$this->passwordField] ?? '';

            // Create a new Token instance with the user's password as secret
            if (!is_string($password) || $password === '') {
                return [];
            }

            $tokenInstance = new Token(
                $this->tokenAlgorithm,
                $password,
                5, // leeway
                $this->tokenValidity,
                '', // audience
                '', // issuer
                '', // algorithms
                '', // audiences
                '' // issuers
            );

            $claims = $tokenInstance->getClaims($token);
            if ($claims && $claims['user'] == $usernameStr && $claims['ip'] == $_SERVER['REMOTE_ADDR']) {
                $totpSecret = $user[$table][$this->totpSecretField] ?? '';
                if (!$this->totp->verify(is_string($totpSecret) ? $totpSecret : '', $totp ?: '')) {
                    throw new TotpError($usernameStr);
                }
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }
                $_SESSION['user'] = $user[$table];
                if ($rememberMe) {
                    $this->doRemember($usernameStr);
                }
            } else {
                $user = [];
            }
        } else {
            $user = [];
        }
        return $user;
    }

    /**
     * Log out the current user.
     * 
     * Clears all session variables except debugger data,
     * regenerates the session ID, and removes the remember-me cookie.
     * 
     * @return bool Always returns true.
     */
    public function logout(): bool
    {
        foreach ($_SESSION as $key => $value) {
            if ($key != 'debugger') {
                unset($_SESSION[$key]);
            }
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $this->unRemember();
        return true;
    }

    /**
     * Register a new user with the given username.
     * 
     * Creates a new user record with a random hashed password.
     * 
     * @param string $username The username to register.
     * @return int The ID of the newly created user.
     */
    public function register(string $username): int
    {
        $query = sprintf(
            'insert into `%s` (`%s`,`%s`,`%s`) values (?,?,NOW())',
            $this->usersTable,
            $this->usernameField,
            $this->passwordField,
            $this->createdField
        );
        $password = bin2hex(random_bytes(16));
        $password = password_hash($password, PASSWORD_DEFAULT);
        return $this->db->insert($query, $username, $password);
    }

    /**
     * Update the password for an existing user.
     * 
     * Generates a new random hashed password for the user.
     * 
     * @param string $username The username to update.
     * @return int The number of affected rows.
     */
    public function update(string $username): int
    {
        $query = sprintf(
            'update `%s` set `%s`=? where `%s`=?',
            $this->usersTable,
            $this->passwordField,
            $this->usernameField
        );
        $password = bin2hex(random_bytes(16));
        $password = password_hash($password, PASSWORD_DEFAULT);
        return $this->db->update($query, $password, $username);
    }

    /**
     * Update the TOTP secret for a user.
     * 
     * @param string $username The username to update.
     * @param string $secret The TOTP secret to set.
     * @return int The number of affected rows.
     */
    public function updateTotpSecret(string $username, string $secret): int
    {
        $query = sprintf(
            'update `%s` set `%s`=? where `%s`=?',
            $this->usersTable,
            $this->totpSecretField,
            $this->usernameField
        );
        return $this->db->update($query, $secret, $username);
    }

    /**
     * Check if a user with the given username exists.
     * 
     * @param string $username The username to check.
     * @return mixed The user ID if exists, null otherwise.
     */
    public function exists(string $username)
    {
        $query = sprintf(
            'select `id` from `%s` where `%s`=?',
            $this->usersTable,
            $this->usernameField
        );
        return $this->db->selectValue($query, $username);
    }
}
