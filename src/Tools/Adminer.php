<?php

namespace MintyPHP\Tools;

class Adminer
{
    private ?string $host;
    private ?int $port;
    private ?string $username;
    private ?string $password;
    private ?string $db;
    private string $url;
    private string $storagePath;

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
        $this->storagePath = $storagePath ?: (__DIR__ . '/adminer.php.gz');
    }

    /**
     * Download Adminer from the specified URL and store it compressed
     */
    public function download(): bool
    {
        // Download the file from the URL
        $content = file_get_contents($this->url);

        if ($content === false) {
            return false;
        }

        // Compress and store the file
        $compressed = gzencode($content, 9);

        if ($compressed === false) {
            return false;
        }

        return file_put_contents($this->storagePath, $compressed) !== false;
    }

    /**
     * Run Adminer by decompressing and executing the stored file
     */
    public function run(): void
    {
        // Check if the gzipped file exists
        if (!file_exists($this->storagePath)) {
            // Download if not present
            if (!$this->download()) {
                throw new \RuntimeException('Failed to download Adminer');
            }
        }

        // Read the gzipped file
        $compressed = file_get_contents($this->storagePath);

        if ($compressed === false) {
            throw new \RuntimeException('Failed to read Adminer file');
        }

        // Decompress the content
        $content = gzdecode($compressed);

        if ($content === false) {
            throw new \RuntimeException('Failed to decompress Adminer file');
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
