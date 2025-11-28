<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\Debugger;
use MintyPHP\Core\Session;
use PHPUnit\Framework\TestCase;

class DebuggerTest extends TestCase
{
    private Debugger $debugger;
    private Session $session;

    protected function setUp(): void
    {
        // Create a session instance
        $this->session = new Session(null);

        // Create debugger instance with enabled = false for most tests
        $this->debugger = new Debugger(
            $this->session,
            10,
            false,
            'test_debugger_key'
        );
    }

    public function testDebuggerConstruction(): void
    {
        $this->assertInstanceOf(Debugger::class, $this->debugger);
    }

    public function testIsEnabledReturnsFalse(): void
    {
        $this->assertFalse($this->debugger->isEnabled());
    }

    public function testIsEnabledReturnsTrue(): void
    {
        $debugger = new Debugger($this->session, 10, true, 'test_debugger');
        $this->assertTrue($debugger->isEnabled());
    }

    public function testSetAndGet(): void
    {
        $this->debugger->set('test_key', 'test_value');
        $this->assertEquals('test_value', $this->debugger->get('test_key'));
    }

    public function testGetNonExistentKey(): void
    {
        $result = $this->debugger->get('nonexistent_key');
        $this->assertFalse($result);
    }

    public function testAdd(): void
    {
        $this->debugger->add('items', 'item1');
        $this->debugger->add('items', 'item2');
        $this->debugger->add('items', 'item3');

        $items = $this->debugger->get('items');
        $this->assertIsArray($items);
        $this->assertCount(3, $items);
        $this->assertEquals(['item1', 'item2', 'item3'], $items);
    }
    public function testDebugReturnsNullWhenDisabled(): void
    {
        $result = $this->debugger->debug(['test' => 'data']);
        $this->assertNull($result);
    }

    public function testDebugReturnsStringWhenEnabled(): void
    {
        $debugger = new Debugger($this->session, 10, true, 'test_debugger_enabled');

        $result = $debugger->debug('test string');
        $this->assertIsString($result);
        $this->assertStringContainsString('"test string"', $result);
    }

    public function testDebugBoolean(): void
    {
        $debugger = new Debugger($this->session, 10, true, 'test_debugger_bool');

        $resultTrue = $debugger->debug(true);
        $this->assertStringContainsString('true', $resultTrue);

        $resultFalse = $debugger->debug(false);
        $this->assertStringContainsString('false', $resultFalse);
    }

    public function testDebugInteger(): void
    {
        $debugger = new Debugger($this->session, 10, true, 'test_debugger_int');

        $result = $debugger->debug(42);
        $this->assertStringContainsString('42', $result);
    }

    public function testDebugArray(): void
    {
        $debugger = new Debugger($this->session, 10, true, 'test_debugger_array');

        $result = $debugger->debug(['key' => 'value', 'number' => 123]);
        $this->assertStringContainsString('array(2)', $result);
        $this->assertStringContainsString('[key]', $result);
        $this->assertStringContainsString('"value"', $result);
    }

    public function testDebugNull(): void
    {
        $debugger = new Debugger($this->session, 10, true, 'test_debugger_null');

        $result = $debugger->debug(null);
        $this->assertStringContainsString('null', $result);
    }

    public function testDebugObject(): void
    {
        $debugger = new Debugger($this->session, 10, true, 'test_debugger_object');

        $obj = new \stdClass();
        $obj->prop = 'value';

        $result = $debugger->debug($obj);
        $this->assertStringContainsString('stdClass', $result);
    }

    public function testDebugWithDepthLimit(): void
    {
        $debugger = new Debugger($this->session, 10, true, 'test_debugger_depth');

        $nested = ['level1' => ['level2' => ['level3' => 'value']]];
        $result = $debugger->debug($nested, 100, 25, 2); // depth = 2

        $this->assertStringContainsString('array', $result);
        $this->assertStringContainsString('{...}', $result); // Should hit depth limit
    }

    public function testDebugCircularReference(): void
    {
        $debugger = new Debugger($this->session, 10, true, 'test_debugger_circular');

        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1;

        $result = $debugger->debug([$obj1, $obj2]);
        $this->assertStringContainsString('stdClass', $result);
        // Should handle circular references without infinite loop
        $this->assertIsString($result);
    }

    public function testConfiguration(): void
    {
        // Verify static configuration was set
        $this->assertEquals(10, Debugger::$__history);
        $this->assertFalse(Debugger::$__enabled);
        $this->assertEquals('test_debugger_key', Debugger::$__sessionKey);
    }

    public function testEnd(): void
    {
        $this->debugger->set('start', microtime(true));
        $this->debugger->end('test');

        $type = $this->debugger->get('type');
        $this->assertEquals('test', $type);
    }

    public function testEndOnlyRunsOnce(): void
    {
        $this->debugger->set('start', microtime(true));
        $this->debugger->end('first');
        $this->debugger->end('second');

        $type = $this->debugger->get('type');
        $this->assertEquals('first', $type); // Should still be 'first'
    }
}
