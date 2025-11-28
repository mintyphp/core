<?php

namespace MintyPHP\Tests\Core;

use Memcached;
use MintyPHP\Core\Cache;
use MintyPHP\Debugger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    private Cache $cache;

    /** @var Memcached&MockObject */
    private Memcached $memcachedMock;

    protected function setUp(): void
    {
        // Disable debugger for cleaner tests
        Debugger::$enabled = false;

        // Create a mock Memcached instance
        $this->memcachedMock = $this->createMock(Memcached::class);

        // Create Cache instance with mocked Memcached
        $this->cache = new Cache($this->memcachedMock, 'test_');
    }

    public function testAdd(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('add')
            ->with('test_mykey', 'myvalue', 0)
            ->willReturn(true);

        $result = $this->cache->add('mykey', 'myvalue');
        $this->assertTrue($result);
    }

    public function testAddWithExpiration(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('add')
            ->with('test_mykey', 'myvalue', 3600)
            ->willReturn(true);

        $result = $this->cache->add('mykey', 'myvalue', 3600);
        $this->assertTrue($result);
    }

    public function testAddFailure(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('add')
            ->with('test_existing', 'value', 0)
            ->willReturn(false);

        $result = $this->cache->add('existing', 'value');
        $this->assertFalse($result);
    }

    public function testSet(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('set')
            ->with('test_mykey', 'myvalue', 0)
            ->willReturn(true);

        $result = $this->cache->set('mykey', 'myvalue');
        $this->assertTrue($result);
    }

    public function testSetWithExpiration(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('set')
            ->with('test_mykey', ['data' => 'complex'], 7200)
            ->willReturn(true);

        $result = $this->cache->set('mykey', ['data' => 'complex'], 7200);
        $this->assertTrue($result);
    }

    public function testGet(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('get')
            ->with('test_mykey')
            ->willReturn('myvalue');

        $result = $this->cache->get('mykey');
        $this->assertEquals('myvalue', $result);
    }

    public function testGetNotFound(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('get')
            ->with('test_nonexistent')
            ->willReturn(false);

        $result = $this->cache->get('nonexistent');
        $this->assertFalse($result);
    }

    public function testGetArray(): void
    {
        $expectedData = ['foo' => 'bar', 'baz' => 123];

        $this->memcachedMock
            ->expects($this->once())
            ->method('get')
            ->with('test_arraykey')
            ->willReturn($expectedData);

        $result = $this->cache->get('arraykey');
        $this->assertEquals($expectedData, $result);
    }

    public function testDelete(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('delete')
            ->with('test_mykey', 0)
            ->willReturn(true);

        $result = $this->cache->delete('mykey');
        $this->assertTrue($result);
    }

    public function testDeleteNotFound(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('delete')
            ->with('test_nonexistent', 0)
            ->willReturn(false);

        $result = $this->cache->delete('nonexistent');
        $this->assertFalse($result);
    }

    public function testIncrement(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('increment')
            ->with('test_counter', 1)
            ->willReturn(11);

        $result = $this->cache->increment('counter');
        $this->assertEquals(11, $result);
    }

    public function testIncrementWithValue(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('increment')
            ->with('test_counter', 5)
            ->willReturn(15);

        $result = $this->cache->increment('counter', 5);
        $this->assertEquals(15, $result);
    }

    public function testIncrementFailure(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('increment')
            ->with('test_nonexistent', 1)
            ->willReturn(false);

        $result = $this->cache->increment('nonexistent');
        $this->assertFalse($result);
    }

    public function testDecrement(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('decrement')
            ->with('test_counter', 1)
            ->willReturn(9);

        $result = $this->cache->decrement('counter');
        $this->assertEquals(9, $result);
    }

    public function testDecrementWithValue(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('decrement')
            ->with('test_counter', 3)
            ->willReturn(7);

        $result = $this->cache->decrement('counter', 3);
        $this->assertEquals(7, $result);
    }

    public function testDecrementFailure(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('decrement')
            ->with('test_nonexistent', 1)
            ->willReturn(false);

        $result = $this->cache->decrement('nonexistent');
        $this->assertFalse($result);
    }

    public function testReplace(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('replace')
            ->with('test_mykey', 'newvalue', 0)
            ->willReturn(true);

        $result = $this->cache->replace('mykey', 'newvalue');
        $this->assertTrue($result);
    }

    public function testReplaceWithExpiration(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('replace')
            ->with('test_mykey', 'newvalue', 1800)
            ->willReturn(true);

        $result = $this->cache->replace('mykey', 'newvalue', 1800);
        $this->assertTrue($result);
    }

    public function testReplaceFailure(): void
    {
        $this->memcachedMock
            ->expects($this->once())
            ->method('replace')
            ->with('test_nonexistent', 'value', 0)
            ->willReturn(false);

        $result = $this->cache->replace('nonexistent', 'value');
        $this->assertFalse($result);
    }

    public function testPrefixIsApplied(): void
    {
        // Test that the prefix is correctly applied to all operations
        $cache = new Cache($this->memcachedMock, 'myapp:cache:');

        $this->memcachedMock
            ->expects($this->once())
            ->method('get')
            ->with('myapp:cache:testkey')
            ->willReturn('value');

        $cache->get('testkey');
    }

    public function testMultipleOperations(): void
    {
        // Test a sequence of operations
        $this->memcachedMock
            ->expects($this->exactly(3))
            ->method('set')
            ->willReturnOnConsecutiveCalls(true, true, true);

        $result1 = $this->cache->set('key1', 'value1');
        $result2 = $this->cache->set('key2', 'value2');
        $result3 = $this->cache->set('key3', 'value3');

        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertTrue($result3);
    }

    public function testWithDebuggerEnabled(): void
    {
        // Enable debugger
        Debugger::$enabled = true;

        $this->memcachedMock
            ->expects($this->once())
            ->method('get')
            ->with('test_mykey')
            ->willReturn('myvalue');

        $result = $this->cache->get('mykey');
        $this->assertEquals('myvalue', $result);

        // Reset debugger state
        Debugger::$enabled = false;
    }

    public function testConstructorWithNullMemcached(): void
    {
        // This test verifies the constructor creates a Memcached instance when null is passed
        // We can't easily test the actual connection without a running Memcached server
        // but we can verify the constructor doesn't throw an error
        $cache = new Cache(null, 'test_', '127.0.0.1:11211');
        $this->assertInstanceOf(Cache::class, $cache);
    }

    public function testConstructorWithMultipleServers(): void
    {
        // Test that multiple servers can be configured
        $cache = new Cache(null, 'test_', '127.0.0.1:11211,192.168.1.1:11211');
        $this->assertInstanceOf(Cache::class, $cache);
    }

    public function testConstructorWithServerDefaultPort(): void
    {
        // Test that default port is used when not specified
        $cache = new Cache(null, 'test_', '127.0.0.1');
        $this->assertInstanceOf(Cache::class, $cache);
    }
}
