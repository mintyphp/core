<?php

declare(strict_types=1);

namespace MintyPHP\Tests;

class Adder 
{ 
    public static function add($a, $b):int 
    { 
        return $a+$b; 
    } 
}

class StaticMethodMockTest extends \PHPUnit\Framework\TestCase
{
    public function testStaticMethodMock(): void
    {
        // Create a static method mock for the Adder class
        $mock = new StaticMethodMock(Adder::class);
        // Set expectation for the add method
        $mock->expect('add', [1, 2], false, 3);
        // Call the public static add method
        $result = Adder::add(1, 2);
        // Verify the result
        $this->assertEquals(3, $result);
    }
}
