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

    /** 
     * Create a Request object from an associative array
     * @param array<string, mixed> $data 
     */
    static public function fromArray(array $data): Request
    {
        /** @var array<string> */
        $log = $data['log'] ?? [];
        /** @var array<int,array<string,mixed>> $queries */
        $queries = is_array($data['queries']) ? $data['queries'] : [];
        /** @var array<int,array<string,mixed>> $apiCalls */
        $apiCalls = is_array($data['apiCalls']) ? $data['apiCalls'] : [];
        $sessionBefore = is_string($data['sessionBefore'] ?? null) ? $data['sessionBefore'] : '';
        $sessionAfter = is_string($data['sessionAfter'] ?? null) ? $data['sessionAfter'] : '';
        /** @var array<int,array<string,mixed>> $cache */
        $cache = is_array($data['cache']) ? $data['cache'] : [];
        $start = is_float($data['start'] ?? null) ? $data['start'] : 0.0;
        $status = is_int($data['status'] ?? null) ? $data['status'] : 0;
        $user = is_string($data['user'] ?? null) ? $data['user'] : '';
        $type = is_string($data['type'] ?? null) ? $data['type'] : '';
        $duration = is_float($data['duration'] ?? null) ? $data['duration'] : 0.0;
        $memory = is_int($data['memory'] ?? null) ? $data['memory'] : 0;
        /** @var array<string> $classes */
        $classes = is_array($data['classes'] ?? null) ? $data['classes'] : [];
        /** @var array<string,mixed> $route */
        $route = is_array($data['route'] ?? null) ? $data['route'] : [];
        $redirect = is_string($data['redirect'] ?? null) ? $data['redirect'] : '';
        return new Request(
            $log,
            array_map(fn(array $q): Query => Query::fromArray($q), $queries),
            array_map(fn(array $a): ApiCall => ApiCall::fromArray($a), $apiCalls),
            $sessionBefore,
            $sessionAfter,
            array_map(fn(array $c): CacheCall => CacheCall::fromArray($c), $cache),
            $start,
            $status,
            $user,
            $type,
            $duration,
            $memory,
            $classes,
            Route::fromArray($route),
            $redirect,
        );
    }
}
