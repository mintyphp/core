<?php

namespace MintyPHP\Core\Debugger;

class ApiCallResult
{
    public function __construct(
        public int $status,
        /** @var array<string,string> */
        public array $headers,
        public string $data,
        public string $url,
    ) {}
}
