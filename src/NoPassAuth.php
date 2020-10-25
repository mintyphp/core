<?php
namespace MintyPHP;

class NoPassAuth
{
    static $usersTable = 'users';
    static $usernameField = 'username';
    static $passwordField = 'password';
    static $rememberTokenField = 'remember_token';
    static $rememberExpiresField = 'remember_expires';
    static $createdField = 'created';
    static $tokenValidity = 300;
    static $rememberDays = 0;

    public static function token($username)
    {
        $query = sprintf('select * from `%s` where `%s` = ? limit 1',
            static::$usersTable,
            static::$usernameField);
        $user = DB::selectOne($query, $username);
        if ($user) {
            $table = static::$usersTable;
            $username = $user[$table][static::$usernameField];
            $password = $user[$table][static::$passwordField];
            Token::$secret = $password;
            Token::$ttl = static::$tokenValidity;
            $token = Token::getToken(array('user' => $username, 'ip' => $_SERVER['REMOTE_ADDR']));
        } else {
            $token = '';
        }
        return $token;
    }

    public static function remember()
    {
        $name = Session::$sessionName . '_remember';
        $value = $_COOKIE[$name];
        $username = explode(':', $value, 2)[0];
        $token = explode(':', $value, 2)[1] ?? '';
        $query = sprintf('select * from `%s` where `%s` = ? and `%s` > NOW() limit 1',
            static::$usersTable,
            static::$usernameField,
            static::$rememberExpiresField);
        $user = DB::selectOne($query, $username);
        if ($user) {
            $table = static::$usersTable;
            $username = $user[$table][static::$usernameField];
            $hash = $user[$table][static::$rememberTokenField];
            if (password_verify($token, $hash)) {
                session_regenerate_id(true);
                $_SESSION['user'] = $user[$table];
                return true;
            }
        }
        return false;
    }

    private static function unRemember()
    {
        $name = Session::$sessionName . '_remember';
        if ($_COOKIE[$name]) {
            setcookie($name, false);
        }
    }

    private static function doRemember($username)
    {
        $name = Session::$sessionName . '_remember';
        $token = base64_encode(random_bytes(24));
        $hash = password_hash($token, PASSWORD_DEFAULT);
        $query = sprintf('update `%s` set `%s` = ?, `%s` = DATE_ADD(NOW(), INTERVAL ? DAY) where `%s` = ? limit 1',
            static::$usersTable,
            static::$rememberTokenField,
            static::$rememberExpiresField,
            static::$usernameField);
        DB::update($query, $hash, static::$rememberDays, $username);
        $value = "$username:$token";
        $expires = strtotime('+' . static::$rememberDays . ' days');
        $path = Router::$baseUrl;
        $domain = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
        if (!$domain || $domain == 'localhost') {
            setcookie($name, $value, $expires, $path);
        } else {
            setcookie($name, $value, $expires, $path, $domain, true, true);
        }
    }

    public static function login($token)
    {
        $parts = explode('.', $token);
        $claims = isset($parts[1]) ? json_decode(base64_decode($parts[1]), true) : false;
        $username = isset($claims['user']) ? $claims['user'] : false;
        $query = sprintf('select * from `%s` where `%s` = ? limit 1',
            static::$usersTable,
            static::$usernameField);
        $user = DB::selectOne($query, $username);
        if ($user) {
            $table = static::$usersTable;
            $username = $user[$table][static::$usernameField];
            $password = $user[$table][static::$passwordField];
            Token::$secret = $password;
            Token::$ttl = static::$tokenValidity;
            $claims = Token::getClaims($token);
            if ($claims && $claims['user'] == $username && $claims['ip'] == $_SERVER['REMOTE_ADDR']) {
                session_regenerate_id(true);
                $_SESSION['user'] = $user[$table];
                if (static::$rememberDays > 0) {
                    static::doRemember($username);
                }
            } else {
                $user = array();
            }
        }
        return $user;
    }

    public static function logout()
    {
        foreach ($_SESSION as $key => $value) {
            if ($key != 'debugger') {
                unset($_SESSION[$key]);
            }

        }
        session_regenerate_id(true);
        static::unRemember();
        return true;
    }

    public static function register($username)
    {
        $query = sprintf('insert into `%s` (`%s`,`%s`,`%s`) values (?,?,NOW())',
            static::$usersTable,
            static::$usernameField,
            static::$passwordField,
            static::$createdField);
        $password = bin2hex(random_bytes(16));
        $password = password_hash($password, PASSWORD_DEFAULT);
        return DB::insert($query, $username, $password);
    }

    public static function update($username)
    {
        $query = sprintf('update `%s` set `%s`=? where `%s`=?',
            static::$usersTable,
            static::$passwordField,
            static::$usernameField);
        $password = bin2hex(random_bytes(16));
        $password = password_hash($password, PASSWORD_DEFAULT);
        return DB::update($query, $password, $username);
    }

    public static function exists($username)
    {
        $query = sprintf('select `id` from `%s` where `%s`=?',
            static::$usersTable,
            static::$usernameField);
        return DB::selectValue($query, $username);
    }

}
