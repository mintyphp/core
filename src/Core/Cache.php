<?php

namespace MintyPHP\Core;

use Memcached;

/**
 * Cache layer for MintyPHP using Memcached
 * 
 * Provides caching operations with support for debugging and monitoring.
 */
class Cache
{
    /**
     * Static configuration parameters
     */
    public static string $__prefix = 'mintyphp';
    public static string $__servers = '127.0.0.1';

    /**
     * Actual configuration parameters
     */
    private string $prefix;
    private string $servers;


    /**
     * The Memcached instance used for caching
     */
    private Memcached $memcache;
    private ?Debugger $debugger;

    /**
     * Create a new Cache instance
     * 
     * @param Memcached|null $memcache Optional Memcached instance. If null, a new instance will be created.
     * @param string $prefix Prefix for cache keys.
     * @param string $servers Comma-separated list of Memcached servers (host:port).
     */
    public function __construct(string $prefix, string $servers, ?Memcached $memcache = null, ?Debugger $debugger = null)
    {
        $this->prefix = $prefix;
        $this->servers = $servers;
        // Initialize Memcached instance
        if ($memcache === null) {
            $memcache = new Memcached();
            $serverList = explode(',', $this->servers);
            $serverList = array_map(function ($server) {
                $server = explode(':', trim($server));
                if (count($server) == 1) $server[1] = '11211';
                return $server;
            }, $serverList);
            foreach ($serverList as $server) {
                $memcache->addServer($server[0], intval($server[1]));
            }
        }
        $this->memcache = $memcache;
        $this->debugger = $debugger;
    }
    /**
     * Convert a variable to a string representation for debugging
     *
     * @param mixed $var The variable to convert
     * @return string String representation of the variable
     */
    private function variable(mixed $var): string
    {
        $type = gettype($var);
        switch ($type) {
            case 'boolean':
                $result = $var ? 'TRUE' : 'FALSE';
                break;
            case 'integer':
                assert(is_int($var));
                $result = (string)$var;
                break;
            case 'NULL':
                $result = 'NULL';
                break;
            case 'string':
                assert(is_string($var));
                $result = '(string:' . strlen($var) . ')';
                break;
            case 'array':
                assert(is_array($var));
                $result = '(array:' . count($var) . ')';
                break;
            default:
                $result = '(' . $type . ')';
        }
        return $result;
    }

    /**
     * Add a value to the cache only if it doesn't already exist
     *
     * @param string $key The cache key
     * @param mixed $var The value to store
     * @param int $expire Expiration time in seconds (0 = never expire)
     * @return bool True on success, false on failure
     */
    public function add(string $key, mixed $var, int $expire = 0): bool
    {
        if ($this->debugger !== null) $time = microtime(true);
        $res = $this->memcache->add($this->prefix . $key, $var, $expire);
        if ($this->debugger !== null) {
            $duration = microtime(true) - $time;
            $command = 'add';
            $arguments = array($key, $this->variable($var));
            if ($expire) $arguments[] = $expire;
            $result = $this->variable($res);
            $this->debugger->addCacheCall($duration, $command, $arguments, $result);
        }
        return $res;
    }

    /**
     * Decrement a numeric value in the cache
     *
     * @param string $key The cache key
     * @param int $value The amount to decrement by (default: 1)
     * @return int|false The new value on success, false on failure
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        if ($this->debugger !== null) $time = microtime(true);
        $res = $this->memcache->decrement($this->prefix . $key, $value);
        if ($this->debugger !== null) {
            $duration = microtime(true) - $time;
            $command = 'decrement';
            $arguments = array($key);
            if ($value > 1) $arguments[] = $value;
            $result = $this->variable($res);
            $this->debugger->addCacheCall($duration, $command, $arguments, $result);
        }
        return $res;
    }

    /**
     * Delete a value from the cache
     *
     * @param string $key The cache key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool
    {
        if ($this->debugger !== null) $time = microtime(true);
        $res = $this->memcache->delete($this->prefix . $key, 0);
        if ($this->debugger !== null) {
            $duration = microtime(true) - $time;
            $command = 'delete';
            $arguments = array($key);
            $result = $this->variable($res);
            $this->debugger->addCacheCall($duration, $command, $arguments, $result);
        }
        return $res;
    }

    /**
     * Retrieve a value from the cache
     *
     * @param string $key The cache key
     * @return mixed The cached value, or false if not found
     */
    public function get(string $key): mixed
    {
        if ($this->debugger !== null) $time = microtime(true);
        $res = $this->memcache->get($this->prefix . $key);
        if ($this->debugger !== null) {
            $duration = microtime(true) - $time;
            $command = 'get';
            $arguments = array($key);
            $result = $this->variable($res);
            $this->debugger->addCacheCall($duration, $command, $arguments, $result);
        }
        return $res;
    }

    /**
     * Increment a numeric value in the cache
     *
     * @param string $key The cache key
     * @param int $value The amount to increment by (default: 1)
     * @return int|false The new value on success, false on failure
     */
    public function increment(string $key, int $value = 1): int|false
    {
        if ($this->debugger !== null) $time = microtime(true);
        $res = $this->memcache->increment($this->prefix . $key, $value);
        if ($this->debugger !== null) {
            $duration = microtime(true) - $time;
            $command = 'increment';
            $arguments = array($key);
            if ($value > 1) $arguments[] = $value;
            $result = $this->variable($res);
            $this->debugger->addCacheCall($duration, $command, $arguments, $result);
        }
        return $res;
    }

    /**
     * Replace a value in the cache only if it already exists
     *
     * @param string $key The cache key
     * @param mixed $var The value to store
     * @param int $expire Expiration time in seconds (0 = never expire)
     * @return bool True on success, false on failure
     */
    public function replace(string $key, mixed $var, int $expire = 0): bool
    {
        if ($this->debugger !== null) $time = microtime(true);
        $res = $this->memcache->replace($this->prefix . $key, $var, $expire);
        if ($this->debugger !== null) {
            $duration = microtime(true) - $time;
            $command = 'replace';
            $arguments = array($key, $this->variable($var));
            if ($expire) $arguments[] = $expire;
            $result = $this->variable($res);
            $this->debugger->addCacheCall($duration, $command, $arguments, $result);
        }
        return $res;
    }

    /**
     * Store a value in the cache (creates or updates)
     *
     * @param string $key The cache key
     * @param mixed $var The value to store
     * @param int $expire Expiration time in seconds (0 = never expire)
     * @return bool True on success, false on failure
     */
    public function set(string $key, mixed $var, int $expire = 0): bool
    {
        if ($this->debugger !== null) $time = microtime(true);
        $res = $this->memcache->set($this->prefix . $key, $var, $expire);
        if ($this->debugger !== null) {
            $duration = microtime(true) - $time;
            $command = 'set';
            $arguments = array($key, $this->variable($var));
            if ($expire) $arguments[] = $expire;
            $result = $this->variable($res);
            $this->debugger->addCacheCall($duration, $command, $arguments, $result);
        }
        return $res;
    }
}
