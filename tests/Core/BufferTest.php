<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\Buffer;
use MintyPHP\BufferError;
use PHPUnit\Framework\TestCase;

class BufferTest extends TestCase
{
    private Buffer $buffer;

    protected function setUp(): void
    {
        $this->buffer = new Buffer();
    }

    public function testStartAndEnd(): void
    {
        $this->buffer->start('test');
        echo 'Hello World';
        $this->buffer->end('test');

        $this->expectOutputString('Hello World');
        $result = $this->buffer->get('test');
        $this->assertTrue($result);
    }

    public function testMultipleBuffers(): void
    {
        $this->buffer->start('buffer1');
        echo 'Content 1';
        $this->buffer->end('buffer1');

        $this->buffer->start('buffer2');
        echo 'Content 2';
        $this->buffer->end('buffer2');

        $this->expectOutputString('Content 1Content 2');
        $this->buffer->get('buffer1');
        $this->buffer->get('buffer2');
    }

    public function testNestedBuffers(): void
    {
        $this->buffer->start('outer');
        echo 'Before ';
        $this->buffer->start('inner');
        echo 'Inner';
        $this->buffer->end('inner');
        echo ' After';
        $this->buffer->end('outer');

        $this->expectOutputString('InnerBefore  After');
        $this->buffer->get('inner');
        $this->buffer->get('outer');
    }

    public function testSet(): void
    {
        $this->buffer->set('direct', 'Direct content');

        $this->expectOutputString('Direct content');
        $result = $this->buffer->get('direct');
        $this->assertTrue($result);
    }

    public function testGetNonExistent(): void
    {
        $result = $this->buffer->get('nonexistent');
        $this->assertFalse($result);
    }

    public function testEndMismatch(): void
    {
        $exceptionThrown = false;
        $this->buffer->start('correct');

        try {
            $this->buffer->end('wrong');
        } catch (BufferError $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString("Buffer::end('wrong') called, but Buffer::end('correct') expected.", $e->getMessage());
            // Clean up the output buffer
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        $this->assertTrue($exceptionThrown, 'BufferError was not thrown');
    }
    public function testEmptyBuffer(): void
    {
        $this->buffer->start('empty');
        $this->buffer->end('empty');

        $this->expectOutputString('');
        $result = $this->buffer->get('empty');
        $this->assertTrue($result);
    }

    public function testOverwriteWithSet(): void
    {
        $this->buffer->start('overwrite');
        echo 'Original';
        $this->buffer->end('overwrite');

        $this->buffer->set('overwrite', 'New content');

        $this->expectOutputString('New content');
        $result = $this->buffer->get('overwrite');
        $this->assertTrue($result);
    }

    public function testMultipleNestedBuffers(): void
    {
        $this->buffer->start('level1');
        echo '1';
        $this->buffer->start('level2');
        echo '2';
        $this->buffer->start('level3');
        echo '3';
        $this->buffer->end('level3');
        echo '2b';
        $this->buffer->end('level2');
        echo '1b';
        $this->buffer->end('level1');

        $this->expectOutputString('322b11b');
        $this->buffer->get('level3');
        $this->buffer->get('level2');
        $this->buffer->get('level1');
    }

    public function testBufferWithHtml(): void
    {
        $this->buffer->start('html');
        echo '<div class="test">Hello <strong>World</strong></div>';
        $this->buffer->end('html');

        $this->expectOutputString('<div class="test">Hello <strong>World</strong></div>');
        $result = $this->buffer->get('html');
        $this->assertTrue($result);
    }
}
