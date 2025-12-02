<?php

namespace MintyPHP\Core\Debugger;

class Query
{
    public function __construct(
        public float $duration,
        public string $query,
        public string $equery,
        /** @var array<int|string, mixed> */
        public array $params,
        /** @var mixed */
        public mixed $result,
        /** @var mixed */
        public mixed $explain,
    ) {}
}
