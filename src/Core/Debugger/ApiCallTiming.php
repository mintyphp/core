<?php

namespace MintyPHP\Core\Debugger;

class ApiCallTiming
{
    public function __construct(
        public float $nameLookup,
        public float $connect,
        public float $preTransfer,
        public float $startTransfer,
        public float $redirect,
        public float $total,
    ) {}

    /** 
     * Create an ApiCallTiming object from an associative array
     * @param array<string,mixed> $data
     */
    static public function fromArray(array $data): ApiCallTiming
    {
        $nameLookup = is_float($data['nameLookup'] ?? null) ? $data['nameLookup'] : 0.0;
        $connect = is_float($data['connect'] ?? null) ? $data['connect'] : 0.0;
        $preTransfer = is_float($data['preTransfer'] ?? null) ? $data['preTransfer'] : 0.0;
        $startTransfer = is_float($data['startTransfer'] ?? null) ? $data['startTransfer'] : 0.0;
        $redirect = is_float($data['redirect'] ?? null) ? $data['redirect'] : 0.0;
        $total = is_float($data['total'] ?? null) ? $data['total'] : 0.0;
        return new ApiCallTiming(
            $nameLookup,
            $connect,
            $preTransfer,
            $startTransfer,
            $redirect,
            $total,
        );
    }
}
