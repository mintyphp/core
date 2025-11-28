<?php

namespace MintyPHP\Tests\Core;

use Memcached;
use MintyPHP\Core\Cache;
use MintyPHP\Core\Debugger;
use MintyPHP\Core\Firewall;
use PHPUnit\Framework\TestCase;

class FirewallTest extends TestCase
{
    private static Firewall $firewall;
    private static Cache $cache;
    private static Debugger $debugger;
    private static Memcached $memcached;
    private static string $testPrefix = 'firewall_test_';

    public static function setUpBeforeClass(): void
    {
        // Create a real Memcached instance
        self::$memcached = new Memcached();
        self::$memcached->addServer('127.0.0.1', 11211);

        // Check if Memcached is available
        $stats = self::$memcached->getStats();
        if (empty($stats) || !isset($stats['127.0.0.1:11211'])) {
            throw new \Exception('Memcached server is not available on 127.0.0.1:11211');
        }

        // Create dependencies
        self::$cache = new Cache(self::$memcached, self::$testPrefix);
        self::$debugger = new Debugger(
            new \MintyPHP\Core\Session(null),
            10,
            false,
            'test_debugger'
        );

        // Create Firewall instance
        self::$firewall = new Firewall(
            self::$cache,
            self::$debugger,
            5, // Lower concurrency for testing
            0.1, // Shorter spin lock
            10, // Shorter interval
            self::$testPrefix . 'fw_',
            false
        );
    }

    protected function setUp(): void
    {
        // Clean up cache keys before each test
        $this->cleanupCache();
    }

    protected function tearDown(): void
    {
        // Clean up cache keys after each test
        $this->cleanupCache();
    }

    private function cleanupCache(): void
    {
        // Delete firewall-related keys
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = self::$testPrefix . 'fw__' . $ip;
        self::$memcached->delete($key);
    }

    public function testFirewallConstruction(): void
    {
        $this->assertInstanceOf(Firewall::class, self::$firewall);
    }

    public function testFirewallAllowsRequest(): void
    {
        // Store original remote addr
        $originalRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Start should not throw or die (in normal circumstances)
        // We can't easily test the actual start() method without mocking die()
        // so we'll just verify the firewall object is properly configured
        $this->assertInstanceOf(Firewall::class, self::$firewall);

        // Restore
        if ($originalRemoteAddr !== null) {
            $_SERVER['REMOTE_ADDR'] = $originalRemoteAddr;
        } else {
            unset($_SERVER['REMOTE_ADDR']);
        }
    }

    public function testFirewallHandlesReverseProxy(): void
    {
        // Create a firewall with reverse proxy enabled
        $firewall = new Firewall(
            self::$cache,
            self::$debugger,
            5,
            0.1,
            10,
            self::$testPrefix . 'fw_rp_',
            true // Enable reverse proxy
        );

        $this->assertInstanceOf(Firewall::class, $firewall);
    }

    public function testFirewallConfiguration(): void
    {
        // Verify static configuration was set
        $this->assertEquals(5, Firewall::$__concurrency);
        $this->assertEquals(0.1, Firewall::$__spinLockSeconds);
        $this->assertEquals(10, Firewall::$__intervalSeconds);
        $this->assertStringContainsString('fw_', Firewall::$__cachePrefix);
    }
}
