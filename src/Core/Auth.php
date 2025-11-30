<?php

namespace MintyPHP\Core;

use MintyPHP\Core\DB;
use MintyPHP\Error\TotpError;

/**
 * Authentication layer for MintyPHP
 * 
 * Provides user authentication, registration, and password management
 * with support for password hashing and two-factor authentication (TOTP).
 */
class Auth
{
    /**
     * Static configuration parameters
     */
    public static string $__usersTable = 'users';
    public static string $__usernameField = 'username';
    public static string $__passwordField = 'password';
    public static string $__createdField = 'created';
    public static string $__totpSecretField = 'totp_secret';

    /**
     * Actual configuration parameters
     */
    private readonly string $usersTable;
    private readonly string $usernameField;
    private readonly string $passwordField;
    private readonly string $createdField;
    private readonly string $totpSecretField;

    /**
     * Database instance for executing queries.
     */
    private DB $db;

    /**
     * Totp instance for handling TOTP verification.
     */
    private Totp $totp;

    /**
     * Session instance for managing user sessions.
     */
    private Session $session;

    /**
     * Constructor for the Auth class.
     * @param DB $db Database instance for executing queries.
     * @param Totp $totp Totp instance for handling TOTP verification.
     * @param Session $session Session instance for managing user sessions.
     * @param string $usersTable Name of the users table.
     * @param string $usernameField Name of the username field.
     * @param string $passwordField Name of the password field.
     * @param string $createdField Name of the created timestamp field.
     * @param string $totpSecretField Name of the TOTP secret field.
     */
    public function __construct(
        DB $db,
        Totp $totp,
        Session $session,
        string $usersTable = 'users',
        string $usernameField = 'username',
        string $passwordField = 'password',
        string $createdField = 'created',
        string $totpSecretField = 'totp_secret'
    ) {
        $this->db = $db;
        $this->totp = $totp;
        $this->session = $session;
        $this->usersTable = $usersTable;
        $this->usernameField = $usernameField;
        $this->passwordField = $passwordField;
        $this->createdField = $createdField;
        $this->totpSecretField = $totpSecretField;
    }

    /**
     * Authenticate a user with username, password, and optional TOTP code.
     * 
     * Verifies the username and password, checks TOTP if configured,
     * regenerates the session, and stores user data in the session.
     * 
     * @param string $username The username to authenticate.
     * @param string $password The password to verify.
     * @param string $totp Optional TOTP code for two-factor authentication.
     * @return array<string,array<string,mixed>> User data on success, empty array on failure.
     */
    public function login(string $username, string $password, string $totp = ''): array
    {
        $query = sprintf(
            'select * from `%s` where `%s` = ? limit 1',
            $this->usersTable,
            $this->usernameField
        );
        $user = $this->db->selectOne($query, $username);
        if ($user && is_string($user[$this->usersTable][$this->passwordField])) {
            $passwordHash = $user[$this->usersTable][$this->passwordField] ?? '';
            if (password_verify($password, $passwordHash)) {
                $totpSecret = $user[$this->usersTable][$this->totpSecretField] ?? '';
                if (!is_string($totpSecret)) {
                    $totpSecret = '';
                }
                if (!$this->totp->verify($totpSecret, $totp)) {
                    $username = $user[$this->usersTable][$this->usernameField] ?? '';
                    if (!is_string($username)) {
                        $username = '';
                    }
                    throw new TotpError($username);
                }
                $this->session->regenerate();
                $_SESSION['user'] = $user[$this->usersTable];
                return $user;
            }
        }
        return [];
    }

    /**
     * Log out the current user.
     * 
     * Removes user data from the session and regenerates the session ID
     * for security.
     * 
     * @return bool Always returns true.
     */
    public function logout(): bool
    {
        unset($_SESSION['user']);
        $this->session->regenerate();
        return true;
    }

    /**
     * Register a new user with username and password.
     * 
     * Hashes the password using PASSWORD_DEFAULT algorithm and stores
     * the user record with the current timestamp.
     * 
     * @param string $username The username for the new user.
     * @param string $password The password for the new user (will be hashed).
     * @return int The ID of the newly created user.
     */
    public function register(string $username, string $password): int
    {
        $query = sprintf(
            'insert into `%s` (`%s`,`%s`,`%s`) values (?,?,NOW())',
            $this->usersTable,
            $this->usernameField,
            $this->passwordField,
            $this->createdField
        );
        $password = password_hash($password, PASSWORD_DEFAULT);
        return $this->db->insert($query, $username, $password);
    }

    /**
     * Update a user's password.
     * 
     * Hashes the new password using PASSWORD_DEFAULT algorithm and
     * updates the user's record.
     * 
     * @param string $username The username to update.
     * @param string $password The new password (will be hashed).
     * @return int Number of rows affected (typically 1 on success, 0 if user not found).
     */
    public function update(string $username, string $password): int
    {
        $query = sprintf(
            'update `%s` set `%s`=? where `%s`=?',
            $this->usersTable,
            $this->passwordField,
            $this->usernameField
        );
        $password = password_hash($password, PASSWORD_DEFAULT);
        return $this->db->update($query, $password, $username);
    }

    /**
     * Update a user's TOTP secret.
     * 
     * Sets or updates the TOTP secret for two-factor authentication.
     * 
     * @param string $username The username to update.
     * @param string $secret The new TOTP secret (base32-encoded).
     * @return int Number of rows affected (typically 1 on success, 0 if user not found).
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
     * Check if a user exists.
     * 
     * Queries the database to determine if a user with the given
     * username exists.
     * 
     * @param string $username The username to check.
     * @return bool True if user exists, false otherwise.
     */
    public function exists(string $username): bool
    {
        $query = sprintf(
            'select 1 from `%s` where `%s`=?',
            $this->usersTable,
            $this->usernameField
        );
        return boolval($this->db->selectValue($query, $username));
    }
}
