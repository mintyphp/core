<?php

namespace MintyPHP\Core\Debugger;

class SessionStates
{
    public function __construct(
        public string $before,
        public string $after,
    ) {}
}
