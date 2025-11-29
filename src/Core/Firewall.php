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
    private int $concurrency;
    private float $spinLockSeconds;
    private int $intervalSeconds;
    private string $cachePrefix;
    private bool $reverseProxy;

    private string|false $key = false;

    public function __construct(
        private Cache $cache,
        private Debugger $debugger,
        int $concurrency = 10,
        float $spinLockSeconds = 0.15,
        int $intervalSeconds = 300,
        string $cachePrefix = 'fw_concurrency_',
        bool $reverseProxy = false
    ) {
        $this->concurrency = $concurrency;
        $this->spinLockSeconds = $spinLockSeconds;
        $this->intervalSeconds = $intervalSeconds;
        $this->cachePrefix = $cachePrefix;
        $this->reverseProxy = $reverseProxy;
    }

    private function getClientIp(): string
    {
        if ($this->reverseProxy && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = array_pop($ips);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    private function getKey(): string
    {
        if (!$this->key) {
            $this->key = $this->cachePrefix . '_' . $this->getClientIp();
        }
        return $this->key;
    }

    public function start(): void
    {
        if ($this->debugger->isEnabled()) return;
        header_remove('X-Powered-By');
        $key = $this->getKey();
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

    public function end(): void
    {
        $this->cache->decrement($this->getKey());
    }
}
