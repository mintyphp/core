<?php

namespace MintyPHP\Core\Debugger;

class Request
{
    public function __construct(
        /** @var array<string> */
        public array $log,
        /** @var array<int,Query> */
        public array $queries,
        /** @var array<int,ApiCall> */
        public array $apiCalls,
        public string $sessionBefore,
        public string $sessionAfter,
        /** @var array<int,CacheCall> */
        public array $cache,
        public float $start,
        public int $status,
        public string $user,
        public string $type,
        public float $duration,
        public int $memory,
        /** @var array<string> */
        public array $classes,
        public Route $route,
        public string $redirect,
    ) {}
}
