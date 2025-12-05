<?php

namespace MintyPHP\Core;

use MintyPHP\Core\Cache;
use MintyPHP\Core\Debugger;

/**
 * Firewall class for rate limiting and request concurrency control.
 * 
 * Protects your application from abuse by limiting the number of concurrent
 * requests from the same IP address. Uses a spin-lock mechanism with Memcached
 * to ensure requests wait rather than fail immediately.
 */
class Firewall
{
    /**
     * Static configuration parameters
     */
    public static int $__concurrency = 10;
    public static float $__spinLockSeconds = 0.15;
    public static int $__intervalSeconds = 300;
    public static string $__cachePrefix = 'fw_concurrency_';
    public static bool $__reverseProxy = false;

    /**
     * Actual configuration parameters
     */
    private readonly int $concurrency;
    private readonly float $spinLockSeconds;
    private readonly int $intervalSeconds;
    private readonly string $cachePrefix;
    private readonly bool $reverseProxy;

    private Cache $cache;
    /** @var array<string,string> */
    private array $serverGlobal;
    private string $key;

    /**
     * Constructor
     * 
     * @param Cache $cache Cache instance for storing concurrency data
     * @param int $concurrency Maximum number of concurrent requests allowed per IP
     * @param float $spinLockSeconds Time in seconds to wait between retries when limit is reached
     * @param int $intervalSeconds Time in seconds for which the concurrency count is valid
     * @param string $cachePrefix Prefix for cache keys
     * @param bool $reverseProxy Whether to trust X-Forwarded-For header for client IP
     * @param ?array<string,string> $serverGlobal Optional server global array (defaults to $_SERVER)
     */
    public function __construct(Cache $cache, int $concurrency, float $spinLockSeconds, int $intervalSeconds, string $cachePrefix, bool $reverseProxy, ?array $serverGlobal = null)
    {
        $this->cache = $cache;
        $this->concurrency = $concurrency;
        $this->spinLockSeconds = $spinLockSeconds;
        $this->intervalSeconds = $intervalSeconds;
        $this->cachePrefix = $cachePrefix;
        $this->reverseProxy = $reverseProxy;
        $this->serverGlobal = $serverGlobal ?? $_SERVER;
        $this->key = $this->cachePrefix . '_' . $this->getClientIp();
    }

    /**
     * Get the cache key for the current client IP
     * @return string The cache key
     */
    private function getClientIp(): string
    {
        if ($this->reverseProxy && isset($this->serverGlobal['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $this->serverGlobal['HTTP_X_FORWARDED_FOR']);
            $ip = array_pop($ips);
        } else {
            $ip = $this->serverGlobal['REMOTE_ADDR'] ?? '';
        }
        return $ip;
    }

    /**
     * Start the firewall check
     * 
     * Increments the concurrency count for the client IP. If the limit is exceeded,
     * waits using a spin-lock mechanism until a slot is available or the timeout is reached.
     * If the timeout is reached, sends a 429 Too Many Requests response and terminates.
     * 
     * @return void
     */
    public function start(): void
    {
        header_remove('X-Powered-By');
        $key = $this->key;
        $start = microtime(true);
        $this->cache->add($key, 0, $this->intervalSeconds);
        register_shutdown_function([$this, 'end']);
        while ($this->cache->increment($key) > $this->concurrency) {
            $this->cache->decrement($key);
            if (!$this->spinLockSeconds || microtime(true) - $start > $this->intervalSeconds) {
                http_response_code(429);
                die('429: Too Many Requests');
            }
            usleep(intval($this->spinLockSeconds * 1000000));
        }
    }

    /**
     * End the firewall check
     * 
     * Decrements the concurrency count for the client IP.
     * 
     * @return void
     */
    public function end(): void
    {
        $this->cache->decrement($this->key);
    }
}
