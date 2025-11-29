<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\Debugger;
use PHPUnit\Framework\TestCase;

class DebuggerTest extends TestCase
{
    private Debugger $debugger;

    protected function setUp(): void
    {
        // Create debugger instance
        $this->debugger = new Debugger(10, true, 'test_debugger_key');
    }

    public function testDebuggerConstruction(): void
    {
        $this->assertInstanceOf(Debugger::class, $this->debugger);
    }

    public function testIsEnabledReturnsFalse(): void
    {
        $this->debugger = new Debugger(10, false, 'test_debugger_key');
        $this->assertFalse($this->debugger->isEnabled());
    }

    public function testIsEnabledReturnsTrue(): void
    {
        $this->assertTrue($this->debugger->isEnabled());
    }

    public function testSetAndGet(): void
    {
        $this->debugger->setStatus(200);
        $this->assertEquals(200, $this->debugger->request->status);
    }

    public function testAddApiCall(): void
    {
        $timing = ['nameLookup' => 0.01, 'connect' => 0.02, 'preTransfer' => 0.03, 'startTransfer' => 0.04, 'redirect' => 0.0, 'total' => 0.05];
        $this->debugger->addApiCall(0.123, 'GET', '/api/items', ['id' => 1], [], ['Authorization' => 'Bearer token'], 200, $timing, 'Fetched successfully');
        $this->debugger->addApiCall(0.125, 'POST', '/api/items', ['name' => 'item2'], [], ['Authorization' => 'Bearer token'], 200, $timing, 'Created successfully');
        $this->debugger->addApiCall(0.111, 'DELETE', '/api/items/1', [], [], ['Authorization' => 'Bearer token'], 200, $timing, 'Deleted successfully');
        $items = $this->debugger->request->apiCalls;
        $this->assertIsArray($items);
        $this->assertCount(3, $items);
        $this->assertEquals('Deleted successfully', $items[2]->result);
    }
    public function testDebugReturnsEmptyStringWhenDisabled(): void
    {
        $this->debugger = new Debugger(10, false, 'test_debugger_disabled');
        $result = $this->debugger->debug(['test' => 'data']);
        $this->assertEquals('', $result);
    }

    public function testDebugReturnsStringWhenEnabled(): void
    {
        $result = $this->debugger->debug('test string');
        $this->assertIsString($result);
        $this->assertStringContainsString('"test string"', $result);
    }

    public function testDebugBoolean(): void
    {
        $resultTrue = $this->debugger->debug(true);
        $this->assertStringContainsString('true', $resultTrue);

        $resultFalse = $this->debugger->debug(false);
        $this->assertStringContainsString('false', $resultFalse);
    }

    public function testDebugInteger(): void
    {
        $result = $this->debugger->debug(42);
        $this->assertStringContainsString('42', $result);
    }

    public function testDebugArray(): void
    {
        $result = $this->debugger->debug(['key' => 'value', 'number' => 123]);
        $this->assertStringContainsString('array(2)', $result);
        $this->assertStringContainsString('[key]', $result);
        $this->assertStringContainsString('"value"', $result);
    }

    public function testDebugNull(): void
    {
        $result = $this->debugger->debug(null);
        $this->assertStringContainsString('null', $result);
    }

    public function testDebugObject(): void
    {
        $obj = new \stdClass();
        $obj->prop = 'value';

        $result = $this->debugger->debug($obj);
        $this->assertStringContainsString('stdClass', $result);
    }

    public function testDebugWithDepthLimit(): void
    {
        $nested = ['level1' => ['level2' => ['level3' => 'value']]];
        $result = $this->debugger->debug($nested, 100, 25, 2); // depth = 2

        $this->assertStringContainsString('array', $result);
        $this->assertStringContainsString('{...}', $result); // Should hit depth limit
    }

    public function testDebugCircularReference(): void
    {
        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1;

        $result = $this->debugger->debug([$obj1, $obj2]);
        $this->assertStringContainsString('stdClass', $result);
        // Should handle circular references without infinite loop
        $this->assertIsString($result);
    }

    public function testStaticConfiguration(): void
    {
        // Verify static configuration was set
        \MintyPHP\Debugger::$sessionKey = 'test_configuration_key';
        // Reset instance to apply new config
        \MintyPHP\Debugger::setInstance(null);
        // Get new instance
        $this->debugger = \MintyPHP\Debugger::getInstance();
        // Verify the configuration
        $this->assertEquals('test_configuration_key', $this->debugger->getSessionKey());
    }

    public function testEnd(): void
    {
        $this->debugger->end('test_end_type');
        $type = $this->debugger->request->type;
        $this->assertEquals('test_end_type', $type);
    }

    public function testEndOnlyRunsOnce(): void
    {
        $this->debugger->end('first');
        $this->debugger->end('second');

        $type = $this->debugger->request->type;
        $this->assertEquals('first', $type); // Should still be 'first'
    }
}
