<?php

namespace MintyPHP\Tests\Core;

use Memcached;
use MintyPHP\Core\Cache;
use MintyPHP\Debugger;
use PHPUnit\Framework\TestCase;

/**
 * Note: These tests require a running Memcached server on localhost:11211.
 * Make sure Memcached is installed and running before executing these tests.
 */
class CacheTest extends TestCase
{
    private static Cache $cache;
    private static Memcached $memcached;
    private static string $testPrefix = 'phpunit_test_';

    public static function setUpBeforeClass(): void
    {
        // Disable debugger for cleaner tests
        Debugger::$enabled = false;

        // Create a real Memcached instance
        self::$memcached = new Memcached();
        self::$memcached->addServer('127.0.0.1', 11211);

        // Check if Memcached is available
        $stats = self::$memcached->getStats();
        if (empty($stats) || !isset($stats['127.0.0.1:11211'])) {
            throw new \Exception('Memcached server is not available on 127.0.0.1:11211');
        }

        // Create Cache instance with real Memcached
        self::$cache = new Cache(self::$testPrefix, '127.0.0.1:11211', self::$memcached);
    }

    protected function setUp(): void
    {
        // Clean up any existing test keys before each test
        $this->cleanupTestKeys();
    }

    protected function tearDown(): void
    {
        // Clean up test keys after each test
        $this->cleanupTestKeys();
    }

    private function cleanupTestKeys(): void
    {
        // Delete common test keys used in tests
        $testKeys = [
            'mykey',
            'existing',
            'nonexistent',
            'arraykey',
            'counter',
            'key1',
            'key2',
            'key3',
            'testkey'
        ];

        foreach ($testKeys as $key) {
            self::$memcached->delete(self::$testPrefix . $key);
        }
    }

    public function testAdd(): void
    {
        $result = self::$cache->add('mykey', 'myvalue');
        $this->assertTrue($result);

        // Verify the value was stored
        $value = self::$cache->get('mykey');
        $this->assertEquals('myvalue', $value);
    }

    public function testAddWithExpiration(): void
    {
        $result = self::$cache->add('mykey', 'myvalue', 3600);
        $this->assertTrue($result);

        // Verify the value was stored
        $value = self::$cache->get('mykey');
        $this->assertEquals('myvalue', $value);
    }

    public function testAddFailure(): void
    {
        // First add should succeed
        $result1 = self::$cache->add('existing', 'value');
        $this->assertTrue($result1);

        // Second add should fail because key already exists
        $result2 = self::$cache->add('existing', 'newvalue');
        $this->assertFalse($result2);

        // Original value should still be there
        $value = self::$cache->get('existing');
        $this->assertEquals('value', $value);
    }

    public function testSet(): void
    {
        $result = self::$cache->set('mykey', 'myvalue');
        $this->assertTrue($result);

        // Verify the value was stored
        $value = self::$cache->get('mykey');
        $this->assertEquals('myvalue', $value);
    }

    public function testSetWithExpiration(): void
    {
        $result = self::$cache->set('mykey', ['data' => 'complex'], 7200);
        $this->assertTrue($result);

        // Verify the value was stored
        $value = self::$cache->get('mykey');
        $this->assertEquals(['data' => 'complex'], $value);
    }

    public function testGet(): void
    {
        // Set a value first
        self::$cache->set('mykey', 'myvalue');

        // Now get it
        $result = self::$cache->get('mykey');
        $this->assertEquals('myvalue', $result);
    }

    public function testGetNotFound(): void
    {
        // Try to get a key that doesn't exist
        $result = self::$cache->get('nonexistent');
        $this->assertFalse($result);
    }

    public function testGetArray(): void
    {
        $expectedData = ['foo' => 'bar', 'baz' => 123];

        // Set array data
        self::$cache->set('arraykey', $expectedData);

        // Get it back
        $result = self::$cache->get('arraykey');
        $this->assertEquals($expectedData, $result);
    }

    public function testDelete(): void
    {
        // Set a value first
        self::$cache->set('mykey', 'myvalue');

        // Delete it
        $result = self::$cache->delete('mykey');
        $this->assertTrue($result);

        // Verify it's gone
        $value = self::$cache->get('mykey');
        $this->assertFalse($value);
    }

    public function testDeleteNotFound(): void
    {
        // Try to delete a key that doesn't exist
        $result = self::$cache->delete('nonexistent');
        $this->assertFalse($result);
    }

    public function testIncrement(): void
    {
        // Set initial value
        self::$cache->set('counter', 10);

        // Increment it
        $result = self::$cache->increment('counter');
        $this->assertEquals(11, $result);

        // Verify the value
        $value = self::$cache->get('counter');
        $this->assertEquals(11, $value);
    }

    public function testIncrementWithValue(): void
    {
        // Set initial value
        self::$cache->set('counter', 10);

        // Increment by 5
        $result = self::$cache->increment('counter', 5);
        $this->assertEquals(15, $result);

        // Verify the value
        $value = self::$cache->get('counter');
        $this->assertEquals(15, $value);
    }

    public function testIncrementFailure(): void
    {
        // Try to increment a key that doesn't exist
        $result = self::$cache->increment('nonexistent');
        $this->assertFalse($result);
    }

    public function testDecrement(): void
    {
        // Set initial value
        self::$cache->set('counter', 10);

        // Decrement it
        $result = self::$cache->decrement('counter');
        $this->assertEquals(9, $result);

        // Verify the value
        $value = self::$cache->get('counter');
        $this->assertEquals(9, $value);
    }

    public function testDecrementWithValue(): void
    {
        // Set initial value
        self::$cache->set('counter', 10);

        // Decrement by 3
        $result = self::$cache->decrement('counter', 3);
        $this->assertEquals(7, $result);

        // Verify the value
        $value = self::$cache->get('counter');
        $this->assertEquals(7, $value);
    }

    public function testDecrementFailure(): void
    {
        // Try to decrement a key that doesn't exist
        $result = self::$cache->decrement('nonexistent');
        $this->assertFalse($result);
    }

    public function testReplace(): void
    {
        // Set initial value
        self::$cache->set('mykey', 'oldvalue');

        // Replace it
        $result = self::$cache->replace('mykey', 'newvalue');
        $this->assertTrue($result);

        // Verify the new value
        $value = self::$cache->get('mykey');
        $this->assertEquals('newvalue', $value);
    }

    public function testReplaceWithExpiration(): void
    {
        // Set initial value
        self::$cache->set('mykey', 'oldvalue');

        // Replace with expiration
        $result = self::$cache->replace('mykey', 'newvalue', 1800);
        $this->assertTrue($result);

        // Verify the new value
        $value = self::$cache->get('mykey');
        $this->assertEquals('newvalue', $value);
    }

    public function testReplaceFailure(): void
    {
        // Try to replace a key that doesn't exist
        $result = self::$cache->replace('nonexistent', 'value');
        $this->assertFalse($result);
    }

    public function testPrefixIsApplied(): void
    {
        // Create a cache with a different prefix
        $cache = new Cache('myapp:cache:', '127.0.0.1:11211', self::$memcached);

        // Set a value with this cache
        $cache->set('testkey', 'value');

        // Verify we can get it back
        $result = $cache->get('testkey');
        $this->assertEquals('value', $result);

        // Verify it's not accessible with the default test prefix
        $resultFromOtherCache = self::$cache->get('testkey');
        $this->assertFalse($resultFromOtherCache);

        // Clean up
        $cache->delete('testkey');
    }

    public function testMultipleOperations(): void
    {
        // Test a sequence of operations
        $result1 = self::$cache->set('key1', 'value1');
        $result2 = self::$cache->set('key2', 'value2');
        $result3 = self::$cache->set('key3', 'value3');

        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertTrue($result3);

        // Verify all values
        $this->assertEquals('value1', self::$cache->get('key1'));
        $this->assertEquals('value2', self::$cache->get('key2'));
        $this->assertEquals('value3', self::$cache->get('key3'));
    }

    public function testWithDebuggerEnabled(): void
    {
        // Enable debugger
        Debugger::$enabled = true;

        // Set and get a value
        self::$cache->set('mykey', 'myvalue');
        $result = self::$cache->get('mykey');
        $this->assertEquals('myvalue', $result);

        // Reset debugger state
        Debugger::$enabled = false;
    }

    public function testConstructorWithNullMemcached(): void
    {
        // Verify the constructor creates a working Memcached instance when null is passed
        $cache = new Cache('constructor_test_', '127.0.0.1:11211');
        $this->assertInstanceOf(Cache::class, $cache);

        // Test that it actually works
        $result = $cache->set('testkey', 'testvalue');
        $this->assertTrue($result);

        $value = $cache->get('testkey');
        $this->assertEquals('testvalue', $value);

        // Clean up
        $cache->delete('testkey');
    }

    public function testExpiration(): void
    {
        // Create a mock Memcached that simulates expiration
        $mockMemcached = $this->createMock(Memcached::class);
        $storage = [];
        $expirations = [];

        // Mock set to store expiration time
        $mockMemcached->method('set')->willReturnCallback(
            function ($key, $value, $expire) use (&$storage, &$expirations) {
                $storage[$key] = $value;
                $expirations[$key] = $expire > 0 ? time() + $expire : 0;
                return true;
            }
        );

        // Mock get to check expiration
        $mockMemcached->method('get')->willReturnCallback(
            function ($key) use (&$storage, &$expirations) {
                if (!isset($storage[$key])) {
                    return false;
                }
                // Check if expired
                if (isset($expirations[$key]) && $expirations[$key] > 0 && $expirations[$key] < time()) {
                    unset($storage[$key]);
                    unset($expirations[$key]);
                    return false;
                }
                return $storage[$key];
            }
        );

        $cache = new Cache('test_expire_', '127.0.0.1:11211', $mockMemcached);

        // Set a key with 1 second expiration
        $cache->set('expiring_key', 'value', 1);

        // Should exist immediately
        $value = $cache->get('expiring_key');
        $this->assertEquals('value', $value);

        // Simulate time passing by setting expiration in the past
        $expirations['test_expire_expiring_key'] = time() - 1;

        // Should be expired now
        $value = $cache->get('expiring_key');
        $this->assertFalse($value);
    }
}
