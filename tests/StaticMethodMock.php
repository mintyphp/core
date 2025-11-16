<?php

declare(strict_types=1);

namespace MintyPHP\Tests;

class StaticMethodMock
{
    /** @var ?callable */
    private static $autoloader = null;
    /** @var array<string,StaticMethodMock> */
    public static array $mocks = [];
    /** @var string */
    private string $className;
    /** @var array<int,array{method:string,arguments:array,returnsVoid:bool,returns:mixed,exception:?Throwable}> */
    private array $expectations = [];

    // Register a static mock for the given class name.
    public function __construct(string $className) {
        $this->className = $className;
        self::$mocks[$className] = $this;
        if (self::$autoloader === null) {
            self::$autoloader = function (string $class) {
                if ($class === $this->className) {
                    $namespace = substr($this->className, 0, strrpos($this->className, '\\'));
                    $shortClassName = substr($this->className, strrpos($this->className, '\\') + 1);
                    eval('namespace ' . $namespace . ' { class ' . $shortClassName . ' { public static function __callStatic($name, $arguments) { return \MintyPHP\Tests\StaticMethodMock::handleStaticCall(\'' . $this->className . '\', $name, $arguments); } } }');
                    return true;
                }
                return false;
            };
            spl_autoload_register(self::$autoloader, true, true);
        }
    }

    /** Expect a with specific body (exact match). */
    public function expect(string $method, array $arguments, bool $returnsVoid = true, mixed $returns = null, ?\Throwable $exception = null)
    {
        $this->expectations[] = [
            'method' => strtoupper($method),
            'arguments' => $arguments,
            'returnsVoid' => $returnsVoid,
            'returns' => $returns,
            'exception' => $exception,
        ];
    }

    public static function handleStaticCall(string $className, string $method, array $arguments)
    {
        if (!isset(self::$mocks[$className])) {
            throw new \Exception(sprintf('StaticMethodMock no mock registered for class: %s', $className));
        }
        $mock = self::$mocks[$className];
        if (empty($mock->expectations)) {
            throw new \Exception(sprintf('StaticMethodMock unexpected call: %s::%s', $className, $method));
        }
        $expected = array_shift($mock->expectations);
        // Basic matching
        if ($expected['method'] != strtoupper($method)) {
            throw new \Exception(sprintf('StaticMethodMock method mismatch: expected %s got %s for %s::%s', $expected['method'], strtoupper($method), $className, $method));
        }
        if ($expected['arguments'] != $arguments) {
            throw new \Exception(sprintf('StaticMethodMock arguments mismatch for %s::%s: expected %s got %s', $className, $method, json_encode($expected['arguments']), json_encode($arguments)));
        }
        if ($expected['exception'] !== null) {
            throw $expected['exception'];
        }
        if ($expected['returnsVoid']) {
            return;
        }
        return $expected['returns'];
    }
}
