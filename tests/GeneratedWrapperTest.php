<?php

namespace MintyPHP\Tests;

use MintyPHP\Auth;
use MintyPHP\Core\Auth as CoreAuth;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the Auth wrapper class (static facade)
 * 
 * Tests that the wrapper correctly delegates to the Core\Auth instance
 */
class GeneratedWrapperTest extends TestCase
{
    /** @var CoreAuth&MockObject */
    private CoreAuth $mockCoreAuth;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock of CoreAuth
        $this->mockCoreAuth = $this->createMock(CoreAuth::class);

        // Reset the static instance before each test
        Auth::setInstance($this->mockCoreAuth);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Reset static properties using reflection
        $reflection = new \ReflectionClass(Auth::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setValue(null, null);
    }

    public function testGetInstance(): void
    {
        $instance = Auth::getInstance();
        $this->assertSame($this->mockCoreAuth, $instance);
    }

    public function testSetInstance(): void
    {
        $newMock = $this->createMock(CoreAuth::class);
        Auth::setInstance($newMock);
        $this->assertSame($newMock, Auth::getInstance());
    }

    /**
     * Test that all public methods from Core\Auth are wrapped in Auth
     * and that they correctly delegate to the core instance with all parameters
     */
    public function testAllPublicMethodsAreWrappedAndDelegate(): void
    {
        // Get reflection of both classes
        $coreReflection = new \ReflectionClass(CoreAuth::class);
        $wrapperReflection = new \ReflectionClass(Auth::class);

        // Get all public methods from Core\Orm (excluding constructor)
        $coreMethods = $coreReflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($coreMethods as $coreMethod) {
            // Skip constructor and static methods
            if ($coreMethod->isConstructor() || $coreMethod->isStatic()) {
                continue;
            }

            $methodName = $coreMethod->getName();

            // Assert that the wrapper has a static method with the same name
            $this->assertTrue(
                $wrapperReflection->hasMethod($methodName),
                "Wrapper class Auth is missing method: {$methodName}"
            );

            $wrapperMethod = $wrapperReflection->getMethod($methodName);

            // Verify the wrapper method is static and public
            $this->assertTrue(
                $wrapperMethod->isStatic(),
                "Method {$methodName} in wrapper should be static"
            );
            $this->assertTrue(
                $wrapperMethod->isPublic(),
                "Method {$methodName} in wrapper should be public"
            );

            // Get parameters for the core method
            $parameters = $coreMethod->getParameters();
            $paramCount = count($parameters);

            // Skip if no parameters (nothing to test for delegation)
            if ($paramCount === 0) {
                continue;
            }

            // Create mock arguments based on parameter types
            $mockArgs = [];
            $expectedArgs = [];

            foreach ($parameters as $param) {
                $paramType = $param->getType();

                if ($paramType instanceof \ReflectionNamedType) {
                    $typeName = $paramType->getName();

                    // Create appropriate mock values based on type
                    $mockValue = match ($typeName) {
                        'string' => 'test_' . $param->getName(),
                        'int' => 42,
                        'float' => 3.14,
                        'bool' => true,
                        'array' => ['key' => 'value'],
                        // For object types, use a simple stdClass
                        default => new \stdClass(),
                    };
                } else {
                    // Mixed or no type hint - use a string
                    $mockValue = 'mixed_value';
                }

                // Handle optional parameters with defaults
                if ($param->isOptional() && $param->isDefaultValueAvailable()) {
                    // We'll test both with and without optional params
                    // For now, just use the default
                    try {
                        $mockValue = $param->getDefaultValue();
                    } catch (\ReflectionException) {
                        // If we can't get the default, use our mock value
                    }
                }

                $mockArgs[] = $mockValue;
                $expectedArgs[] = $mockValue;
            }

            // Set up expectation on the mock
            $expectation = $this->mockCoreAuth
                ->expects($this->once())
                ->method($methodName);

            // Add parameter expectations
            $expectation->with(...$expectedArgs);

            // Set a return value based on the return type
            $returnType = $coreMethod->getReturnType();
            $returnValue = null;

            if ($returnType instanceof \ReflectionNamedType) {
                $returnTypeName = $returnType->getName();

                $returnValue = match ($returnTypeName) {
                    'int' => 123,
                    'bool' => true,
                    'string' => 'test_return',
                    'array' => ['result' => 'data'],
                    'float' => 1.23,
                    'void' => null,
                    default => new \stdClass(),
                };
            }

            $expectation->willReturn($returnValue);

            // Call the wrapper method with our mock arguments
            try {
                $wrapperReflection->getMethod($methodName)->invoke(null, ...$mockArgs);
            } catch (\Throwable $e) {
                $this->fail(
                    "Failed to invoke wrapper method {$methodName} with parameters: " .
                        $e->getMessage()
                );
            }
        }
    }

    /**
     * Test specific examples of method delegation with concrete values
     */
    public function testSpecificMethodDelegation(): void
    {
        // Test login method
        $this->mockCoreAuth
            ->expects($this->once())
            ->method('login')
            ->with('john', 'password123', '')
            ->willReturn(['username' => 'john']);

        $result = Auth::login('john', 'password123', '');
        $this->assertSame(['username' => 'john'], $result);

        // Reset mock for next test
        $this->setUp();

        // Test register method
        $this->mockCoreAuth
            ->expects($this->once())
            ->method('register')
            ->with('newuser', 'password456')
            ->willReturn(42);

        $result = Auth::register('newuser', 'password456');
        $this->assertSame(42, $result);

        // Reset mock for next test
        $this->setUp();

        // Test exists method
        $this->mockCoreAuth
            ->expects($this->once())
            ->method('exists')
            ->with('testuser')
            ->willReturn(true);

        $result = Auth::exists('testuser');
        $this->assertTrue($result);

        // Reset mock for next test
        $this->setUp();

        // Test logout method
        $this->mockCoreAuth
            ->expects($this->once())
            ->method('logout')
            ->willReturn(true);

        $result = Auth::logout();
        $this->assertTrue($result);
    }

    /**
     * Test that all public static variables with __ prefix in Core class
     * are represented as public static variables (without __) in the wrapper
     * and that the wrapper has a private static $instance variable
     */
    public function testConstructorParametersArePublicStaticVariables(): void
    {
        $coreReflection = new \ReflectionClass(CoreAuth::class);
        $wrapperReflection = new \ReflectionClass(Auth::class);

        // Get all public static properties from Core class that start with __
        $coreStaticProps = $coreReflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_STATIC);
        $constructorParams = [];

        foreach ($coreStaticProps as $prop) {
            $propName = $prop->getName();
            if (str_starts_with($propName, '__')) {
                $constructorParams[] = $propName;

                // The wrapper should have a public static property without the __ prefix
                $wrapperPropName = substr($propName, 2); // Remove __ prefix

                $this->assertTrue(
                    $wrapperReflection->hasProperty($wrapperPropName),
                    "Wrapper class is missing public static property: \${$wrapperPropName} (from Core::\${$propName})"
                );

                $wrapperProp = $wrapperReflection->getProperty($wrapperPropName);

                $this->assertTrue(
                    $wrapperProp->isPublic(),
                    "Property \${$wrapperPropName} in wrapper should be public"
                );

                $this->assertTrue(
                    $wrapperProp->isStatic(),
                    "Property \${$wrapperPropName} in wrapper should be static"
                );

                // Verify the types match (if typed)
                $coreType = $prop->getType();
                $wrapperType = $wrapperProp->getType();

                if ($coreType !== null && $wrapperType !== null) {
                    $this->assertEquals(
                        (string) $coreType,
                        (string) $wrapperType,
                        "Type mismatch for property \${$wrapperPropName}"
                    );
                }
            }
        }

        // Verify the wrapper has a private static $instance property
        $this->assertTrue(
            $wrapperReflection->hasProperty('instance'),
            "Wrapper class should have a private static \$instance property"
        );

        $instanceProp = $wrapperReflection->getProperty('instance');

        $this->assertTrue(
            $instanceProp->isPrivate(),
            "Property \$instance in wrapper should be private"
        );

        $this->assertTrue(
            $instanceProp->isStatic(),
            "Property \$instance in wrapper should be static"
        );
    }
}
