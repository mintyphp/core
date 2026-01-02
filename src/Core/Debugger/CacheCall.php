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

    /** 
     * Create a CacheCall object from an associative array
     * @param array<string,mixed> $data
     */
    static public function fromArray(array $data): CacheCall
    {
        $duration = is_float($data['duration'] ?? null) ? $data['duration'] : 0.0;
        $command = is_string($data['command'] ?? null) ? $data['command'] : '';
        /** @var array<mixed> */
        $arguments = is_array($data['arguments'] ?? null) ? $data['arguments'] : [];
        return new CacheCall(
            $duration,
            $command,
            $arguments,
            $data['result'] ?? null,
        );
    }
}
