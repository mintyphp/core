<?php

namespace MintyPHP\Tools;

use MintyPHP\DB;

class AdminerTool
{
    private ?string $host;
    private ?int $port;
    private ?string $username;
    private ?string $password;
    private ?string $db;
    private string $url;
    private string $storagePath;

    public static function run()
    {
        (new self(
            DB::$host ?? null,
            DB::$port ?? null,
            DB::$username ?? null,
            DB::$password ?? null,
            DB::$database ?? null
        ))->execute();
    }

    /**
     * Constructor with optional parameters
     */
    public function __construct(?string $host, ?int $port, ?string $username, ?string $password, ?string $db, ?string $url = null, ?string $storagePath = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->db = $db;
        $this->url = $url ?: 'https://www.adminer.org/latest-en.php';
        $this->storagePath = $storagePath ?: (__DIR__ . '/latest-en.txt');
    }

    /**
     * Download Adminer from the specified URL and store it
     */
    public function download(): bool
    {
        // Download the file from the URL
        $content = file_get_contents($this->url);

        if ($content === false) {
            return false;
        }

        return file_put_contents($this->storagePath, $content) !== false;
    }

    /**
     * Execute Adminer by including the stored file
     */
    public function execute(): void
    {
        // Check if the file exists
        if (!file_exists($this->storagePath)) {
            // Download if not present
            if (!$this->download()) {
                throw new \RuntimeException('Failed to download Adminer');
            }
        }

        // Read the file
        $content = file_get_contents($this->storagePath);

        if ($content === false) {
            throw new \RuntimeException('Failed to read Adminer file');
        }

        // database auto-login credentials
        if (!isset($_GET["username"])) {
            $_POST["auth"] = array(
                'driver' => 'server',
                'server' => $this->host,
                'port' => $this->port,
                'username' => $this->username,
                'password' => $this->password,
                'db' => $this->db,
            );
        }

        // Adminer Extension
        $extension = <<<'EOD'
function adminer_object()
{
    class AdminerSoftware extends Adminer\Adminer
    {
        public function loginForm()
        {
            echo "<p><a href='?'>Click to login</a></p>\n";
        }
    }
    return new AdminerSoftware();
}
EOD;

        // Execute using eval
        eval($extension);
        eval('?>' . $content);
    }
}
