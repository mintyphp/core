<?php

namespace MintyPHP\Core\Debugger;

class CacheCall
{
    public function __construct(
        public float $duration,
        public string $command,
        /** @var array<mixed> */
        public array $arguments,
        /** @var mixed */
        public mixed $result
    ) {}
}
