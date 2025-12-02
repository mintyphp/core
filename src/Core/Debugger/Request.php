<?php

namespace MintyPHP\Core\Debugger;

class Request
{
    public function __construct(
        /** @var array<string> */
        public array $log,
        /** @var array<Query> */
        public array $queries,
        /** @var array<ApiCall> */
        public array $apiCalls,
        public SessionStates $session,
        /** @var array<CacheCall> */
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
