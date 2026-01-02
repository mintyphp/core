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

    /** 
     * Create a Route object from an associative array
     * @param array<string, mixed> $data 
     */
    static public function fromArray(array $data): Route
    {
        $method = is_string($data['method'] ?? null) ? $data['method'] : '';
        $csrfOk = is_bool($data['csrfOk'] ?? null) ? $data['csrfOk'] : false;
        $request = is_string($data['request'] ?? null) ? $data['request'] : '';
        $url = is_string($data['url'] ?? null) ? $data['url'] : '';
        $dir = is_string($data['dir'] ?? null) ? $data['dir'] : '';
        $viewFile = is_string($data['viewFile'] ?? null) ? $data['viewFile'] : '';
        $actionFile = is_string($data['actionFile'] ?? null) ? $data['actionFile'] : '';
        $templateFile = is_string($data['templateFile'] ?? null) ? $data['templateFile'] : '';
        /** @var array<string,mixed> $urlParameters */
        $urlParameters = is_array($data['urlParameters'] ?? null) ? $data['urlParameters'] : [];
        /** @var array<string,mixed> $getParameters */
        $getParameters = is_array($data['getParameters'] ?? null) ? $data['getParameters'] : [];
        /** @var array<string,mixed> $postParameters */
        $postParameters = is_array($data['postParameters'] ?? null) ? $data['postParameters'] : [];
        return new Route(
            $method,
            $csrfOk,
            $request,
            $url,
            $dir,
            $viewFile,
            $actionFile,
            $templateFile,
            $urlParameters,
            $getParameters,
            $postParameters
        );
    }
}
