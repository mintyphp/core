<?php

namespace MintyPHP\Core;

use MintyPHP\BufferError;

/**
 * Buffer management for MintyPHP
 * 
 * Provides functionality for managing named output buffers.
 */
class Buffer
{
    /** @var array<string> */
    protected array $stack = [];
    /** @var array<string,string> */
    protected array $data = [];

    protected function error(string $message): never
    {
        throw new BufferError($message);
    }

    public function start(string $name): void
    {
        array_push($this->stack, $name);
        ob_start();
    }

    public function end(string $name): void
    {
        $top = array_pop($this->stack);
        if ($top != $name) {
            $this->error("Buffer::end('$name') called, but Buffer::end('$top') expected.");
        }
        $this->data[$name] = ob_get_contents() ?: '';
        ob_end_clean();
    }

    public function set(string $name, string $string): void
    {
        $this->data[$name] = $string;
    }

    public function get(string $name): bool
    {
        if (!isset($this->data[$name])) return false;
        echo $this->data[$name];
        return true;
    }
}
