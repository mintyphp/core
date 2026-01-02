<?php

namespace MintyPHP\Core\Debugger;

class Query
{
    public function __construct(
        public float $duration,
        public string $query,
        public string $equery,
        /** @var array<int|string, mixed> */
        public array $arguments,
        /** @var mixed */
        public mixed $result,
        /** @var mixed */
        public mixed $explain,
    ) {}

    /**
     * Create a Query object from an associative array
     * @param array<string,mixed> $data
     */
    static public function fromArray(array $data): Query
    {
        $duration = is_float($data['duration'] ?? null) ? $data['duration'] : 0.0;
        $query = is_string($data['query'] ?? null) ? $data['query'] : '';
        $equery = is_string($data['equery'] ?? null) ? $data['equery'] : '';
        $arguments = is_array($data['arguments'] ?? null) ? $data['arguments'] : [];
        return new Query(
            $duration,
            $query,
            $equery,
            $arguments,
            $data['result'] ?? null,
            $data['explain'] ?? null,
        );
    }
}
