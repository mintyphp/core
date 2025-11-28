<?php

namespace MintyPHP\Core;

use Exception;

/**
 * Buffer management for MintyPHP.
 * 
 * Provides functionality for managing named output buffers using PHP's
 * output buffering system. Buffers are tracked in a stack to ensure
 * proper nesting and can be stored, retrieved, and set by name.
 */
class Buffer
{
    /**
     * Stack of currently active buffer names.
     * 
     * Tracks the order of nested buffers to ensure proper matching
     * of start() and end() calls.
     * 
     * @var array<string>
     */
    private array $stack = [];

    /**
     * Storage for captured buffer contents.
     * 
     * Maps buffer names to their captured output strings.
     * 
     * @var array<string,string>
     */
    private array $data = [];

    /**
     * Throw an exception.
     * 
     * Helper method for consistent error handling.
     * 
     * @param string $message The error message.
     * @return never This method never returns as it always throws.
     * @throws Exception Always thrown with the provided message.
     */
    private function error(string $message): never
    {
        throw new Exception($message);
    }

    /**
     * Start a named output buffer.
     * 
     * Pushes the buffer name onto the stack and begins capturing output.
     * All output generated after this call will be captured until end()
     * is called with the same name.
     * 
     * @param string $name The name of the buffer to start.
     * @return void
     */
    public function start(string $name): void
    {
        array_push($this->stack, $name);
        ob_start();
    }

    /**
     * End a named output buffer.
     * 
     * Stops capturing output for the named buffer and stores the captured
     * content. The buffer name must match the most recently started buffer
     * to maintain proper nesting.
     * 
     * @param string $name The name of the buffer to end.
     * @return void
     * @throws Exception If the buffer name doesn't match the top of the stack.
     */
    public function end(string $name): void
    {
        $top = array_pop($this->stack);
        if ($top != $name) {
            $this->error("Buffer::end('$name') called, but Buffer::end('$top') expected.");
        }
        $this->data[$name] = ob_get_contents() ?: '';
        ob_end_clean();
    }

    /**
     * Set the contents of a named buffer.
     * 
     * Directly assigns content to a buffer without using output buffering.
     * This overwrites any existing content for the buffer.
     * 
     * @param string $name The name of the buffer.
     * @param string $string The content to store in the buffer.
     * @return void
     */
    public function set(string $name, string $string): void
    {
        $this->data[$name] = $string;
    }

    /**
     * Output the contents of a named buffer.
     * 
     * Echoes the stored content of the specified buffer if it exists.
     * 
     * @param string $name The name of the buffer to output.
     * @return bool True if the buffer exists and was output, false otherwise.
     */
    public function get(string $name): bool
    {
        if (!isset($this->data[$name])) return false;
        echo $this->data[$name];
        return true;
    }
}
