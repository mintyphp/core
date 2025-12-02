<?php

namespace MintyPHP\Core\Debugger;

class Route
{
    public function __construct(
        public string $method,
        public bool $csrfOk,
        public string $request,
        public string $url,
        public string $dir,
        public string $viewFile,
        public string $actionFile,
        public string $templateFile,
        /** @var array<string,mixed> */
        public array $urlParameters,
        /** @var array<string,mixed> */
        public array $getParameters,
        /** @var array<string,mixed> */
        public array $postParameters
    ) {}
}
