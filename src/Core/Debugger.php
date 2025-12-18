<?php

namespace MintyPHP\Core;

use MintyPHP\Core\Debugger\ApiCall;
use MintyPHP\Core\Debugger\CacheCall;
use MintyPHP\Core\Debugger\Request;
use MintyPHP\Core\Debugger\Route;
use MintyPHP\Core\Debugger\Query;

/**
 * Debugger class for development and debugging support.
 * 
 * Provides comprehensive debugging capabilities including request logging,
 * query tracking, session inspection, and performance monitoring.
 */
class Debugger
{
    /**
     * Static configuration parameters
     */
    public static int $__history = 10;
    public static bool $__enabled = false;
    public static string $__cookieName = 'minty_debug_session';
    public static int $__retentionHours = 24;
    public static ?string $__storagePath = null;

    /**
     * Actual configuration parameters
     */
    private readonly int $history;
    private readonly bool $enabled;
    private readonly string $cookieName;
    private readonly int $retentionHours;
    private readonly string $storagePath;

    /**
     * The request data for the current session
     */
    public Request $request;

    public function __construct(int $history, bool $enabled, string $cookieName, int $retentionHours, ?string $storagePath)
    {
        $this->history = $history;
        $this->enabled = $enabled;
        $this->cookieName = $cookieName;
        $this->retentionHours = $retentionHours;
        $this->storagePath = $storagePath ?: sys_get_temp_dir() . '/mintyphp-debug';
        // initialize request data
        $this->request = new Request(
            log: [],
            queries: [],
            apiCalls: [],
            sessionBefore: '',
            sessionAfter: '',
            cache: [],
            start: microtime(true),
            status: 0,
            user: get_current_user(),
            type: '',
            duration: 0.0,
            memory: 0,
            classes: [],
            route: new Route(
                method: '',
                csrfOk: false,
                request: '',
                url: '',
                dir: '',
                viewFile: '',
                actionFile: '',
                templateFile: '',
                urlParameters: [],
                getParameters: [],
                postParameters: [],
            ),
            redirect: ''
        );
        // don't do anything if not enabled
        if (!$this->enabled) {
            return;
        }

        // Initialize storage directory
        $storagePath = $this->storagePath;
        $this->ensureDirectory($storagePath);

        // Clean old sessions on initialization
        $this->cleanHistory();

        // only run on first instantiation
        static $run = 0;
        if ($run++ == 1) {
            // configure error reporting
            ini_set('display_errors', 1);
            error_reporting(-1);
            register_shutdown_function([$this, 'end'], 'abort');
        }
    }

    /**
     * Check if the debugger is enabled
     * @return bool True if enabled, false otherwise
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    private function getSessionData(): string
    {
        $data = $this->debug($_SESSION);
        array_pop($this->request->log);
        if ($data !== null) {
            $pos = strpos($data, "\n");
            $data = substr($data, $pos !== false ? $pos : 0);
            $data = trim($data);
        }
        return $data;
    }

    /** @return array<string> */
    private function getLoadedFiles(): array
    {
        $result = [];
        $classes = get_declared_classes();
        $afterComposer = false;
        foreach ($classes as $class) {
            if (substr($class, 0, 8) == 'Composer') {
                $afterComposer = true;
            } elseif ($afterComposer) {
                $reflection = new \ReflectionClass($class);
                $filename = $reflection->getFileName();
                if ($filename !== false) {
                    $result[] = $filename;
                }
            }
        }
        return $result;
    }

    /**
     * Generate RFC 4122 version 4 UUID
     * @return string UUID string like 550e8400-e29b-41d4-a716-446655440000
     */
    private function generateUUID(): string
    {
        $data = random_bytes(16);

        // Set version (4) - bits 12-15 of 7th byte
        $data[6] = chr((ord($data[6]) & 0x0F) | 0x40);

        // Set variant (RFC 4122) - bits 6-7 of 9th byte
        $data[8] = chr((ord($data[8]) & 0x3F) | 0x80);

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }

    /**
     * Get or create browser session identifier via cookie
     * @return string 32-character session identifier
     */
    private function getBrowserSessionId(): string
    {
        // Check for existing cookie
        if (isset($_COOKIE[$this->cookieName])) {
            $identifier = $_COOKIE[$this->cookieName];

            // Validate format (32 chars, alphanumeric + - _)
            if (preg_match('/^[A-Za-z0-9_-]{32}$/', $identifier)) {
                return $identifier;
            }
        }

        // Generate new identifier: 24 bytes (192 bits) → base64 URL-safe
        $identifier = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');

        // Set cookie
        if (!headers_sent()) {
            setcookie(
                name: $this->cookieName,
                value: $identifier,
                expires_or_options: [
                    'expires' => 0,          // Session cookie (browser close)
                    'path' => '/',
                    'domain' => '',
                    'secure' => false,       // Development uses HTTP
                    'httponly' => true,      // XSS protection
                    'samesite' => 'Lax',     // CSRF protection
                ]
            );
        }

        return $identifier;
    }

    /**
     * Ensure directory exists, create if needed
     * @param string $path Directory path to ensure
     * @return bool True if directory exists or was created, false on failure
     */
    private function ensureDirectory(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }

        // Try to create with 0777, let umask adjust
        if (@mkdir($path, 0777, true)) {
            return true;
        }

        // Log error for developer awareness
        error_log("MintyPHP Debugger: Failed to create directory: {$path}");
        return false;
    }

    /**
     * Write data to file atomically using write-then-rename pattern
     * @param string $path File path to write to
     * @param string $data Data to write
     * @return bool True on success, false on failure
     */
    private function atomicWrite(string $path, string $data): bool
    {
        $dir = dirname($path);
        if (!$this->ensureDirectory($dir)) {
            return false;
        }

        // Write to temporary file first
        $tempPath = $path . '.tmp.' . getmypid();
        if (@file_put_contents($tempPath, $data) === false) {
            error_log("MintyPHP Debugger: Failed to write temp file: {$tempPath}");
            return false;
        }

        // Atomic rename
        if (@rename($tempPath, $path)) {
            return true;
        }

        // Cleanup temp file on failure
        @unlink($tempPath);
        error_log("MintyPHP Debugger: Failed to rename {$tempPath} to {$path}");
        return false;
    }

    /**
     * Get file path for request data
     * @param string $uuid Request UUID
     * @return string File path like /tmp/mintyphp-debug/{sessionId}/{uuid}.json
     */
    private function getRequestPath(string $uuid): string
    {
        $storagePath = $this->storagePath;
        $sessionId = $this->getBrowserSessionId();
        return "{$storagePath}/{$sessionId}/{$uuid}.json";
    }

    /**
     * Get file path for history data
     * @return string File path like /tmp/mintyphp-debug/{sessionId}/history.json
     */
    private function getHistoryPath(): string
    {
        $storagePath = $this->storagePath;
        $sessionId = $this->getBrowserSessionId();
        return "{$storagePath}/{$sessionId}/history.json";
    }

    /**
     * Read history file with shared lock
     * @return array<string> Array of UUIDs, most recent first
     */
    private function readHistory(): array
    {
        $historyPath = $this->getHistoryPath();
        if (!file_exists($historyPath)) {
            return [];
        }

        $fp = @fopen($historyPath, 'r');
        if ($fp === false) {
            error_log("MintyPHP Debugger: Failed to open history file: {$historyPath}");
            return [];
        }

        // Shared lock with timeout
        $timeout = microtime(true) + 0.1; // 100ms timeout
        while (!flock($fp, LOCK_SH | LOCK_NB)) {
            if (microtime(true) > $timeout) {
                fclose($fp);
                error_log("MintyPHP Debugger: Timeout acquiring shared lock on history");
                return [];
            }
            usleep(1000); // 1ms
        }

        $contents = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($contents === false) {
            return [];
        }

        $history = json_decode($contents, true);
        return is_array($history) ? $history : [];
    }

    /**
     * Write history file with exclusive lock
     * @param array<string> $history Array of UUIDs
     * @return bool True on success, false on failure
     */
    private function writeHistory(array $history): bool
    {
        $historyPath = $this->getHistoryPath();
        $dir = dirname($historyPath);

        if (!$this->ensureDirectory($dir)) {
            return false;
        }

        $fp = @fopen($historyPath, 'c+');
        if ($fp === false) {
            error_log("MintyPHP Debugger: Failed to open history file for writing: {$historyPath}");
            return false;
        }

        // Exclusive lock with timeout
        $timeout = microtime(true) + 0.1; // 100ms timeout
        while (!flock($fp, LOCK_EX | LOCK_NB)) {
            if (microtime(true) > $timeout) {
                fclose($fp);
                error_log("MintyPHP Debugger: Timeout acquiring exclusive lock on history");
                return false;
            }
            usleep(1000); // 1ms
        }

        rewind($fp);
        ftruncate($fp, 0);
        $jsonData = json_encode($history, JSON_PRETTY_PRINT);
        if ($jsonData !== false) {
            fwrite($fp, $jsonData);
        }
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }

    /**
     * Update history file with new UUID and enforce limit
     * @param string $uuid New request UUID to add
     * @return bool True on success, false on failure
     */
    private function updateHistory(string $uuid): bool
    {
        $history = $this->readHistory();

        // Prepend new UUID (most recent first)
        array_unshift($history, $uuid);

        // Enforce history limit - delete old request files
        if (count($history) > $this->history) {
            $toDelete = array_slice($history, $this->history);
            $history = array_slice($history, 0, $this->history);

            // Delete old request files
            foreach ($toDelete as $oldUuid) {
                $this->deleteRequestFile($oldUuid);
            }
        }

        return $this->writeHistory($history);
    }

    /**
     * Delete a request file by UUID
     * @param string $uuid Request UUID
     * @return bool True on success, false on failure
     */
    private function deleteRequestFile(string $uuid): bool
    {
        $requestPath = $this->getRequestPath($uuid);

        if (file_exists($requestPath)) {
            if (@unlink($requestPath)) {
                return true;
            }
            error_log("MintyPHP Debugger: Failed to delete request file: {$requestPath}");
            return false;
        }

        return true; // Already deleted or never existed
    }

    /**
     * Get all requests from history
     * @return array<int,\MintyPHP\Core\Debugger\Request> Array of request objects, most recent first
     */
    public function getHistory(): array
    {
        $history = $this->readHistory();
        $requests = [];

        foreach ($history as $uuid) {
            $requestPath = $this->getRequestPath($uuid);

            if (!file_exists($requestPath)) {
                continue;
            }

            $contents = @file_get_contents($requestPath);
            if ($contents === false) {
                continue;
            }

            $data = json_decode($contents, true);
            if (!is_array($data)) {
                continue;
            }

            // Reconstruct Request object from JSON data
            try {
                $request = new Request(
                    log: $data['log'] ?? [],
                    queries: array_map(fn($q) => new Query(
                        $q['duration'],
                        $q['query'],
                        $q['equery'],
                        $q['arguments'],
                        $q['result'],
                        $q['explain']
                    ), $data['queries'] ?? []),
                    apiCalls: array_map(fn($a) => new ApiCall(
                        $a['duration'],
                        $a['method'],
                        $a['url'],
                        $a['data'],
                        $a['options'],
                        $a['headers'],
                        $a['timing'],
                        $a['status'],
                        $a['effectiveUrl'],
                        $a['responseHeaders'],
                        $a['body']
                    ), $data['apiCalls'] ?? []),
                    sessionBefore: $data['sessionBefore'] ?? '',
                    sessionAfter: $data['sessionAfter'] ?? '',
                    cache: array_map(fn($c) => new CacheCall(
                        $c['duration'],
                        $c['command'],
                        $c['arguments'],
                        $c['result']
                    ), $data['cache'] ?? []),
                    start: $data['start'] ?? 0.0,
                    status: $data['status'] ?? 0,
                    user: $data['user'] ?? '',
                    type: $data['type'] ?? '',
                    duration: $data['duration'] ?? 0.0,
                    memory: $data['memory'] ?? 0,
                    classes: $data['classes'] ?? [],
                    route: new Route(
                        $data['route']['method'] ?? '',
                        $data['route']['csrfOk'] ?? false,
                        $data['route']['request'] ?? '',
                        $data['route']['url'] ?? '',
                        $data['route']['dir'] ?? '',
                        $data['route']['viewFile'] ?? '',
                        $data['route']['actionFile'] ?? '',
                        $data['route']['templateFile'] ?? '',
                        $data['route']['urlParameters'] ?? [],
                        $data['route']['getParameters'] ?? [],
                        $data['route']['postParameters'] ?? []
                    ),
                    redirect: $data['redirect'] ?? ''
                );

                $requests[] = $request;
            } catch (\Throwable $e) {
                error_log("MintyPHP Debugger: Failed to reconstruct request from {$requestPath}: {$e->getMessage()}");
                continue;
            }
        }
        return $requests;
    }

    /**
     * Clean old debug sessions older than retention period
     * Removes session directories and history files for expired sessions
     * @return void
     */
    private function cleanHistory(): void
    {
        $storagePath = $this->storagePath;

        if (!is_dir($storagePath)) {
            return;
        }

        $cutoffTime = time() - ($this->retentionHours * 3600);

        // Scan for session directories
        $entries = @scandir($storagePath);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = "{$storagePath}/{$entry}";

            // Handle session history files (session-*.json)
            if (is_file($fullPath) && preg_match('/^session-[A-Za-z0-9_-]{32}\.json$/', $entry)) {
                $mtime = @filemtime($fullPath);
                if ($mtime !== false && $mtime < $cutoffTime) {
                    @unlink($fullPath);
                }
                continue;
            }

            // Handle session directories (32-char session IDs)
            if (is_dir($fullPath) && preg_match('/^[A-Za-z0-9_-]{32}$/', $entry)) {
                $mtime = @filemtime($fullPath);
                if ($mtime !== false && $mtime < $cutoffTime) {
                    // Delete all request files in the directory
                    $requestFiles = @scandir($fullPath);
                    if ($requestFiles !== false) {
                        foreach ($requestFiles as $requestFile) {
                            if ($requestFile !== '.' && $requestFile !== '..') {
                                @unlink("{$fullPath}/{$requestFile}");
                            }
                        }
                    }
                    // Remove the empty directory
                    @rmdir($fullPath);

                    // Remove corresponding history file
                    $historyFile = "{$storagePath}/session-{$entry}.json";
                    if (file_exists($historyFile)) {
                        @unlink($historyFile);
                    }
                }
            }
        }
    }

    /**
     * Add a cache call entry to the debugger log
     * @param float $duration The duration of the cache call
     * @param string $command The cache command executed
     * @param array<mixed> $arguments The arguments passed to the cache command
     * @param mixed $result The result returned from the cache command
     * @return void
     */
    public function addCacheCall(float $duration, string $command, array $arguments, mixed $result): void
    {
        $this->request->cache[] = new CacheCall($duration, $command, $arguments, $result);
    }

    /**
     * Add an API call entry to the debugger log
     * @param float $duration The duration of the API call
     * @param string $method The HTTP method used
     * @param string $url The URL called
     * @param string $data The data sent with the request
     * @param array<string,mixed> $options The options used for the request
     * @param array<string,string> $headers The headers sent with the request
     * @param array{nameLookup:float,connect:float,preTransfer:float,startTransfer:float,redirect:float,total:float} $timing The timing information for the call
     * @param int $status The HTTP status code returned
     * @param string $effectiveUrl The effective URL after redirects
     * @param array<string,string> $responseHeaders The response headers received
     * @param string $body The response body received
     * @return void
     */
    public function addApiCall(float $duration, string $method, string $url, string $data, array $options, array $headers, array $timing, int $status, string $effectiveUrl, array $responseHeaders, string $body): void
    {
        $this->request->apiCalls[] = new ApiCall($duration, $method, $url, $data, $options, $headers, $timing, $status, $effectiveUrl, $responseHeaders, $body);
    }

    /**
     * Add a query entry to the debugger log
     * @param float $duration The duration of the query
     * @param string $query The SQL query executed
     * @param string $equery The SQL query executed
     * @param array<int|string, mixed> $arguments The arguments passed to the query
     * @param mixed $result The result returned from the query
     * @param mixed $explain The result returned from the query
     * @return void
     */
    public function addQuery(float $duration, string $query, string $equery, array $arguments, mixed $result, mixed $explain): void
    {
        $this->request->queries[] = new Query($duration, $query, $equery, $arguments, $result, $explain);
    }

    /**
     * Set the route information for the current request
     * @param string $method The HTTP method used
     * @param bool $csrfOk Whether CSRF validation passed
     * @param string $request The raw request string
     * @param string $url The requested URL
     * @param string $dir The directory of the request
     * @param string $viewFile The view file used
     * @param string $actionFile The action file used
     * @param string $templateFile The template file used
     * @param array<string,mixed> $urlParameters The URL parameters
     * @param array<string,mixed> $getParameters The GET parameters
     * @param array<string,mixed> $postParameters The POST parameters
     * @return void
     */
    public function setRoute(string $method, bool $csrfOk, string $request, string $url, string $dir, string $viewFile, string $actionFile, string $templateFile, array $urlParameters, array $getParameters, array $postParameters): void
    {
        $this->request->route = new Route($method, $csrfOk, $request, $url, $dir, $viewFile, $actionFile, $templateFile, $urlParameters, $getParameters, $postParameters);
    }

    /**
     * Set the redirect URL for the current request
     * @param string $url The URL to redirect to
     * @return void
     */
    public function setRedirect(string $url): void
    {
        $this->request->redirect = $url;
    }

    /**
     * Set the status code for the current request
     * @param int $status The HTTP status code
     * @return void
     */
    public function setStatus(int $status): void
    {
        $this->request->status = $status;
    }

    /**
     * Log the session state before processing the request
     * @return void
     */
    public function logSessionBefore(): void
    {
        $this->request->sessionBefore = $this->getSessionData();
    }

    /**
     * Log the session state after processing the request
     * @return void
     */
    public function logSessionAfter(): void
    {
        $this->request->sessionAfter = $this->getSessionData();
    }

    /**
     * Finalize and store the debugger data at the end of the request
     * @param string $type The type of request completion (e.g., 'ok', 'abort')
     * @return void
     */
    public function end(string $type): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($this->request->type) {
            return;
        }

        // finalize request data
        $this->request->type = $type;
        $this->request->duration = microtime(true) - (float)$this->request->start;
        $this->request->memory = memory_get_peak_usage(true);
        $this->request->classes = $this->getLoadedFiles();

        // Generate UUID for this request
        $uuid = $this->generateUUID();

        // Store request data to filesystem
        $requestPath = $this->getRequestPath($uuid);
        $jsonData = json_encode($this->request, JSON_PRETTY_PRINT);
        if ($jsonData !== false) {
            if (!$this->atomicWrite($requestPath, $jsonData)) {
                error_log("MintyPHP Debugger: Failed to write request file: {$requestPath}");
                // Continue execution - graceful degradation
            }
        }

        // Update history file
        if (!$this->updateHistory($uuid)) {
            error_log("MintyPHP Debugger: Failed to update history");
            // Continue execution - graceful degradation
        }
    }

    /**
     * Output the debugger toolbar HTML.
     * @return void
     */
    public function toolbar(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->end('ok');
        $html = '<div id="debugger-bar" style="position: fixed; width:100%; left: 0; bottom: 0; border-top: 1px solid silver; background: white;">';
        $html .= '<div style="margin:6px;">';
        $javascript = "document.getElementById('debugger-bar').style.display='none'; return false;";
        $html .= '<a href="#" onclick="' . $javascript . '" style="float:right;">close</a>';
        $parts = [];
        $parts[] = date('H:i:s', (int)$this->request->start);
        $method = $this->request->route->method;
        $url = $this->request->route->url;
        $parts[] = strtolower($method) . ' ' . htmlentities($url);
        $parts[] = round((float)$this->request->duration * 1000) . ' ms ';
        $parts[] = round((float)$this->request->memory / 1000000) . ' MB';
        $html .= implode(' - ', $parts);
        $html .= ' - <a href="debugger.php">debugger</a>';
        $html .= ' - <a href="adminer.php">adminer</a>';
        $html .= ' - <a href="configurator.php">configurator</a>';
        $html .= ' - <a href="translator.php">translator</a>';
        $html .= '</div></div>';
        echo $html;
    }

    /**
     * Debug a variable and return its string representation.
     * @param mixed $variable The variable to debug
     * @param int $strlen Maximum string length
     * @param int $width Maximum array/object width
     * @param int $depth Maximum depth for nested structures
     * @param int $i Current depth (used internally) 
     * @param array<object> $objects List of already processed objects (used internally)
     * @return string String representation of the variable
     */
    public function debug(mixed $variable, int $strlen = 100, int $width = 25, int $depth = 10, int $i = 0, array &$objects = []): string
    {
        if (!$this->enabled) {
            return '';
        }

        $search = array("\0", "\a", "\b", "\f", "\n", "\r", "\t", "\v");
        $replace = array('\0', '\a', '\b', '\f', '\n', '\r', '\t', '\v');

        $string = '';

        switch (gettype($variable)) {
            case 'boolean':
                $string .= $variable ? 'true' : 'false';
                break;
            case 'integer':
                $string .= $variable;
                break;
            case 'double':
                $string .= $variable;
                break;
            case 'resource':
                $string .= '[resource]';
                break;
            case 'NULL':
                $string .= "null";
                break;
            case 'unknown type':
                $string .= '???';
                break;
            case 'string':
                $len = strlen($variable);
                $variable = str_replace($search, $replace, substr($variable, 0, $strlen), $count);
                $variable = substr($variable, 0, $strlen);
                if ($len < $strlen) {
                    $string .= '"' . $variable . '"';
                } else {
                    $string .= 'string(' . $len . '): "' . $variable . '"...';
                }

                break;
            case 'array':
                $len = count($variable);
                if ($i == $depth) {
                    $string .= 'array(' . $len . ') {...}';
                } elseif (!$len) {
                    $string .= 'array(0) {}';
                } else {
                    $keys = array_keys($variable);
                    $spaces = str_repeat(' ', $i * 2);
                    $string .= "array($len)\n" . $spaces . '{';
                    $count = 0;
                    foreach ($keys as $key) {
                        if ($count == $width) {
                            $string .= "\n" . $spaces . "  ...";
                            break;
                        }
                        $string .= "\n" . $spaces . "  [$key] => ";
                        $string .= $this->debug($variable[$key], $strlen, $width, $depth, $i + 1, $objects);
                        $count++;
                    }
                    $string .= "\n" . $spaces . '}';
                }
                break;
            case 'object':
                $id = array_search($variable, $objects, true);
                if ($id !== false) {
                    $string .= get_class($variable) . '#' . (((int)$id) + 1) . ' {...}';
                } else if ($i == $depth) {
                    $string .= get_class($variable) . ' {...}';
                } else {
                    $id = array_push($objects, $variable);
                    $array = (array) $variable;
                    $spaces = str_repeat(' ', $i * 2);
                    $string .= get_class($variable) . "#$id\n" . $spaces . '{';
                    $properties = array_keys($array);
                    foreach ($properties as $property) {
                        $name = str_replace("\0", ':', trim($property));
                        $string .= "\n" . $spaces . "  [$name] => ";
                        $string .= $this->debug($array[$property], $strlen, $width, $depth, $i + 1, $objects);
                    }
                    $string .= "\n" . $spaces . '}';
                }
                break;
        }

        if ($i > 0) {
            return $string;
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        do $caller = array_shift($backtrace);
        while ($caller && !isset($caller['file']));
        if ($caller && isset($caller['file'], $caller['line'])) {
            $string = $caller['file'] . ':' . $caller['line'] . "\n" . $string;
        }

        $this->request->log[] = $string;

        return $string;
    }
}
