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
}
