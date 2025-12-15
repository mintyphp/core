<?php

namespace MintyPHP\Tools;

use MintyPHP\Core\Buffer;
use MintyPHP\Debugger;

class DebuggerTool
{
    public static function run(): void
    {
        (new self())->execute(Debugger::view());
    }

    public function execute(string $html): void
    {
        echo $html;
    }
}
