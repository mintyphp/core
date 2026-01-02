<?php

namespace MintyPHP\Core\Debugger;

class ApiCall
{
    public function __construct(
        public float $duration,
        public string $method,
        public string $url,
        public string $data,
        /** @var array<string,mixed> */
        public array $options,
        /** @var array<string,string> */
        public array $headers,
        public ApiCallTiming $timing,
        public int $status,
        public string $effectiveUrl,
        /** @var array<string,string> */
        public array $responseHeaders,
        public string $body,
    ) {}

    /** 
     * Create an ApiCall object from an associative array
     * @param array<string,mixed> $data
     */
    static public function fromArray(array $data): ApiCall
    {
        $duration = is_float($data['duration'] ?? null) ? $data['duration'] : 0.0;
        $method = is_string($data['method'] ?? null) ? $data['method'] : '';
        $url = is_string($data['url'] ?? null) ? $data['url'] : '';
        $dataStr = is_string($data['data'] ?? null) ? $data['data'] : '';
        /** @var array<string,mixed> */
        $options = is_array($data['options']) ? $data['options'] : [];
        /** @var array<string,string> */
        $headers = is_array($data['headers']) ? $data['headers'] : [];
        /** @var array<string,mixed> */
        $timing = is_array($data['timing']) ? $data['timing'] : [];
        $status = is_int($data['status'] ?? null) ? $data['status'] : 0;
        $effectiveUrl = is_string($data['effectiveUrl'] ?? null) ? $data['effectiveUrl'] : '';
        /** @var array<string,string> */
        $responseHeaders = is_array($data['responseHeaders']) ? $data['responseHeaders'] : [];
        $body = is_string($data['body'] ?? null) ? $data['body'] : '';
        return new ApiCall(
            $duration,
            $method,
            $url,
            $dataStr,
            $options,
            $headers,
            ApiCallTiming::fromArray($timing),
            $status,
            $effectiveUrl,
            $responseHeaders,
            $body,
        );
    }
}
