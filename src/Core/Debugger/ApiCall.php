<?php

namespace MintyPHP\Core\Debugger;

class ApiCall
{
    public function __construct(
        public float $duration,
        public string $method,
        public string $url,
        /** @var array<string,mixed> */
        public array $data,
        /** @var array<string,mixed> */
        public array $options,
        /** @var array<string,string> */
        public array $headers,
        public int $status,
        /** @var array{nameLookup:float,connect:float,preTransfer:float,startTransfer:float,redirect:float,total:float} */
        public array $timing,
        public mixed $result,
    ) {}
}
