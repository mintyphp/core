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
    public static int $__concurrency = 10;
    public static float $__spinLockSeconds = 0.15;
    public static int $__intervalSeconds = 300;
    public static string $__cachePrefix = 'fw_concurrency_';
    public static bool $__reverseProxy = false;

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
        self::$__concurrency = $concurrency;
        self::$__spinLockSeconds = $spinLockSeconds;
        self::$__intervalSeconds = $intervalSeconds;
        self::$__cachePrefix = $cachePrefix;
        self::$__reverseProxy = $reverseProxy;
    }

    private function getClientIp(): string
    {
        if (self::$__reverseProxy && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
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
            $this->key = self::$__cachePrefix . '_' . $this->getClientIp();
        }
        return $this->key;
    }

    public function start(): void
    {
        if ($this->debugger->isEnabled()) return;
        header_remove('X-Powered-By');
        $key = $this->getKey();
        $start = microtime(true);
        $this->cache->add($key, 0, self::$__intervalSeconds);
        register_shutdown_function([$this, 'end']);
        while ($this->cache->increment($key) > self::$__concurrency) {
            $this->cache->decrement($key);
            if (!self::$__spinLockSeconds || microtime(true) - $start > self::$__intervalSeconds) {
                http_response_code(429);
                die('429: Too Many Requests');
            }
            usleep(intval(self::$__spinLockSeconds * 1000000));
        }
    }

    public function end(): void
    {
        $this->cache->decrement($this->getKey());
    }
}
