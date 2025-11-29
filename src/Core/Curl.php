<?php

namespace MintyPHP\Core;

use MintyPHP\Cache;
use MintyPHP\Debugger;

/**
 * HTTP client for making cURL requests
 * 
 * Provides methods for making HTTP requests with support for caching,
 * redirects, cookies, and various HTTP methods.
 */
class Curl
{
    /**
     * Static configuration parameters
     */
    public static bool $__cookies = false;
    /** @var array<string,mixed> */
    public static array $__options = [];
    /** @var array<string,string> */
    public static array $__headers = [];

    /**
     * Actual configuration parameters
     */
    private readonly bool $cookies;
    /** @var array<string,mixed> */
    private readonly array $options;
    /** @var array<string,string> */
    private readonly array $headers;

    /**
     * The cURL handle used for requests
     */
    private \CurlHandle $ch;

    /**
     * Constructor
     * 
     * @param array<string,mixed> $options Default cURL options
     * @param array<string,string> $headers Default headers to send with requests
     * @param bool $cookies Whether to enable cookie handling
     * @param \CurlHandle|null $ch Optional cURL handle to use
     */
    public function __construct(array $options = [], array $headers = [], bool $cookies = false, \CurlHandle|null $ch = null)
    {
        $this->ch = $ch ?? curl_init();
        $this->options = $options;
        $this->headers = $headers;
        $this->cookies = $cookies;
    }

    /**
     * Make a cached HTTP request with automatic redirects
     * 
     * @param int $expire Cache expiration time in seconds
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url The URL to request
     * @param string|array<mixed> $data Request data
     * @param array<string,string> $headers Additional headers
     * @param array<string,mixed> $options Additional cURL options
     * @return array<string,mixed> Response array with status, headers, data, and url
     */
    public function navigateCached(int $expire, string $method, string $url, mixed $data, array $headers = [], array $options = []): array
    {
        return $this->callCached($expire, $method, $url, $data, $headers, array_merge($options, ['CURLOPT_FOLLOWLOCATION' => true]));
    }

    /**
     * Make a cached HTTP request
     * 
     * @param int $expire Cache expiration time in seconds
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url The URL to request
     * @param string|array<mixed> $data Request data
     * @param array<string,string> $headers Additional headers
     * @param array<string,mixed> $options Additional cURL options
     * @return array<string,mixed> Response array with status, headers, data, and url
     */
    public function callCached(int $expire, string $method, string $url, mixed $data, array $headers = [], array $options = []): array
    {
        $key = $method . '_' . $url . '_' . json_encode($data) . '_' . json_encode($headers) . '_' . json_encode($options);
        $result = Cache::get($key);
        if (is_array($result)) {
            return $result;
        }
        $result = $this->call($method, $url, $data, $headers, $options);
        if ($result['status'] == 200) {
            Cache::set($key, $result, $expire);
        }
        return $result;
    }

    /**
     * Make an HTTP request with automatic redirects
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url The URL to request
     * @param string|array<mixed> $data Request data
     * @param array<string,string> $headers Additional headers
     * @param array<string,mixed> $options Additional cURL options
     * @return array<string,mixed> Response array with status, headers, data, and url
     */
    public function navigate(string $method, string $url, $data = '', array $headers = [], array $options = []): array
    {
        return $this->call($method, $url, $data, $headers, array_merge($options, array('CURLOPT_FOLLOWLOCATION' => true)));
    }

    /**
     * Make an HTTP request
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url The URL to request
     * @param string|array<mixed> $data Request data
     * @param array<string,string> $headers Additional headers
     * @param array<string,mixed> $options Additional cURL options
     * @return array<string,mixed> Response array with status, headers, data, and url
     */
    public function call(string $method, string $url, $data = '', array $headers = [], array $options = []): array
    {
        if (Debugger::$enabled) {
            $time = microtime(true);
        }

        $ch = $this->ch;
        $cookieJar = null;

        if ($this->cookies) {
            $cookieJar = tempnam(sys_get_temp_dir(), "curl_cookies-");
            if ($cookieJar && isset($_SESSION['curl_cookies'])) {
                file_put_contents($cookieJar, $_SESSION['curl_cookies']);
            }
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
        }

        $headers = array_merge($this->headers, $headers);
        $options = array_merge($this->options, $options);
        $this->setOptions($ch, $method, $url, $data, $headers, $options);

        $result = strval($this->curlExec($ch));
        $status = 0 + $this->curlGetInfo($ch, CURLINFO_HTTP_CODE);
        $location = $this->curlGetInfo($ch, CURLINFO_EFFECTIVE_URL);

        if (Debugger::$enabled) {
            $timing = [
                'nameLookup' => 0 + $this->curlGetInfo($ch, CURLINFO_NAMELOOKUP_TIME),
                'connect' => 0 + $this->curlGetInfo($ch, CURLINFO_CONNECT_TIME),
                'preTransfer' => 0 + $this->curlGetInfo($ch, CURLINFO_PRETRANSFER_TIME),
                'startTransfer' => 0 + $this->curlGetInfo($ch, CURLINFO_STARTTRANSFER_TIME),
                'redirect' => 0 + $this->curlGetInfo($ch, CURLINFO_REDIRECT_TIME),
                'total' => 0 + $this->curlGetInfo($ch, CURLINFO_TOTAL_TIME),
            ];
        }

        if ($this->cookies && $cookieJar) {
            $_SESSION['curl_cookies'] = file_get_contents($cookieJar);
            unlink($cookieJar);
        } else {
            if (isset($_SESSION['curl_cookies'])) {
                unset($_SESSION['curl_cookies']);
            }
        }

        if (strpos($result, "\r\n\r\n") === false) {
            list($head, $body) = array($result, '');
        } else {
            list($head, $body) = explode("\r\n\r\n", $result, 2);
            $statusCodes = [100];
            if ($options['CURLOPT_FOLLOWLOCATION'] ?? false) {
                $statusCodes[] = 301;
                $statusCodes[] = 302;
            }
            $regex = '/\s+(' . implode('|', $statusCodes) . ')\s+/';
            while (preg_match($regex, explode("\r\n", $head)[0])) {
                list($head, $body) = explode("\r\n\r\n", $body, 2);
            }
        }

        $result = [
            'status' => $status,
            'headers' => [],
            'data' => $body,
            'url' => $location
        ];

        foreach (explode("\r\n", $head) as $i => $header) {
            if ($i == 0) {
                continue;
            }
            list($key, $value) = explode(': ', $header);
            $result['headers'][$key] = $value;
        }

        if (Debugger::$enabled) {
            $duration = microtime(true) - $time;
            Debugger::addApiCall($duration, $method, $url, $data, $options, $headers, $status, $timing, $result);
        }

        return $result;
    }

    /**
     * Set cURL options and configure the request
     * 
     * @param \CurlHandle $ch The cURL handle
     * @param string $method HTTP method
     * @param string $url The URL (passed by reference, may be modified)
     * @param string|array<mixed> $data Request data (passed by reference, may be modified)
     * @param array<string,string> $headers Headers to send
     * @param array<string,mixed> $options cURL options
     */
    private function setOptions(\CurlHandle $ch, string $method, string &$url, mixed &$data, array $headers, array $options): void
    {
        // Set default options
        foreach ($options as $option => $value) {
            $constantValue = constant(strtoupper($option));
            if (is_int($constantValue)) {
                curl_setopt($ch, $constantValue, $value);
            }
        }

        if (is_array($data)) {
            $data = http_build_query($data);
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        } elseif (strlen($data) > 0 && in_array($data[0], ['{', '['])) {
            $headers['Content-Type'] = 'application/json';
        }

        $head = [];
        foreach ($headers as $key => $value) {
            $head[] = $key . ': ' . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $head);
        curl_setopt($ch, CURLOPT_HEADER, true);

        switch (strtoupper($method)) {
            case 'HEAD':
                curl_setopt($ch, CURLOPT_NOBODY, true);
                break;
            case 'GET':
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                if ($data) {
                    $url .= '?' . $data;
                    $data = '';
                }
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        curl_setopt($ch, CURLOPT_URL, $url);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * Wrapper for curl_exec to allow mocking in tests
     * 
     * @param \CurlHandle $ch The cURL handle
     * @return string|bool The response or false on failure
     */
    private function curlExec(\CurlHandle $ch): string|bool
    {
        return curl_exec($ch);
    }

    /**
     * Wrapper for curl_getinfo to allow mocking in tests
     * 
     * @param \CurlHandle $ch The cURL handle
     * @param int $option The CURLINFO option
     * @return mixed The information value
     */
    private function curlGetInfo(\CurlHandle $ch, int $option): mixed
    {
        return curl_getinfo($ch, $option);
    }
}
