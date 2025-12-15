<?php

namespace MintyPHP\Tools;

use MintyPHP\Core\Buffer;

class Debugger
{
    private Buffer $buffer;

    public function __construct()
    {
        $this->buffer = new Buffer();
    }

    public function run(string $html): void
    {
        $this->buffer->set('debugger', $html);
        $this->buffer->get('debugger');
    }
}
