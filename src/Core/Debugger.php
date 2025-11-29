<?php

namespace MintyPHP\Core;

class DebuggerApiCallResult
{
    public function __construct(
        public int $status,
        /** @var array<string,string> */
        public array $headers,
        public string $data,
        public string $url,
    ) {}
}

class DebuggerCacheCall
{
    public function __construct(
        public float $duration,
        public string $command,
        /** @var array<mixed> */
        public array $arguments,
        /** @var mixed */
        public mixed $result
    ) {}
}

class DebuggerSessionStates
{
    public function __construct(
        public string $before,
        public string $after,
    ) {}
}

class DebuggerApiCallTiming
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

class DebuggerApiCall
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

class DebuggerQuery
{
    public function __construct(
        public float $duration,
        public string $query,
        public string $equery,
        /** @var array<int|string, mixed> */
        public array $params,
        /** @var mixed */
        public mixed $result,
        /** @var mixed */
        public mixed $explain,
    ) {}
}

class DebuggerRoute
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

class DebuggerRequest
{
    public function __construct(
        /** @var array<string> */
        public array $log,
        /** @var array<DebuggerQuery> */
        public array $queries,
        /** @var array<DebuggerApiCall> */
        public array $apiCalls,
        public DebuggerSessionStates $session,
        /** @var array<DebuggerCacheCall> */
        public array $cache,
        public float $start,
        public int $status,
        public string $user,
        public string $type,
        public float $duration,
        public int $memory,
        /** @var array<string> */
        public array $classes,
        public DebuggerRoute $route,
        public string $redirect,
    ) {}
}

/**
 * Debugger class for development and debugging support.
 * 
 * Provides comprehensive debugging capabilities including request logging,
 * query tracking, session inspection, and performance monitoring.
 */
class Debugger
{
    /**
     * Static configuration parameters
     */
    public static int $__history = 10;
    public static bool $__enabled = false;
    public static string $__sessionKey = 'debugger';

    /**
     * Actual configuration parameters
     */
    private int $history;
    private bool $enabled;
    private string $sessionKey;

    /**
     * The request data for the current session
     */
    public DebuggerRequest $request;

    public function __construct(int $history, bool $enabled, string $sessionKey)
    {
        $this->history = $history;
        $this->enabled = $enabled;
        $this->sessionKey = $sessionKey;
        // initialize request data
        $this->request = new DebuggerRequest(
            log: [],
            queries: [],
            apiCalls: [],
            session: new DebuggerSessionStates(
                before: '',
                after: '',
            ),
            cache: [],
            start: microtime(true),
            status: 0,
            user: get_current_user(),
            type: '',
            duration: 0.0,
            memory: 0,
            classes: [],
            route: new DebuggerRoute(
                method: '',
                csrfOk: false,
                request: '',
                url: '',
                dir: '',
                viewFile: '',
                actionFile: '',
                templateFile: '',
                urlParameters: [],
                getParameters: [],
                postParameters: [],
            ),
            redirect: ''
        );
        // only run on first instantiation
        static $run = 0;
        if ($run++ == 1) {
            // configure error reporting
            if (!$this->enabled) {
                ini_set('display_errors', 0);
                error_reporting(0);
                return;
            }
            ini_set('display_errors', 1);
            error_reporting(-1);
            register_shutdown_function([$this, 'end'], 'abort');
        }
    }

    private function getSessionData(): string
    {
        $session = [];
        foreach ($_SESSION as $k => $v) {
            if ($k == $this->sessionKey) {
                $v = true;
            }
            $session[$k] = $v;
        }
        $data = $this->debug($session);
        array_pop($this->request->log);
        if ($data !== null) {
            $pos = strpos($data, "\n");
            $data = substr($data, $pos !== false ? $pos : 0);
            $data = trim($data);
        }
        return $data;
    }

    /** @return array<string> */
    private function getLoadedFiles(): array
    {
        $result = [];
        $classes = get_declared_classes();
        $afterComposer = false;
        foreach ($classes as $class) {
            if (substr($class, 0, 8) == 'Composer') {
                $afterComposer = true;
            } elseif ($afterComposer) {
                $reflection = new \ReflectionClass($class);
                $filename = $reflection->getFileName();
                if ($filename !== false) {
                    $result[] = $filename;
                }
            }
        }
        return $result;
    }

    /**
     * Add a cache call entry to the debugger log
     * @param float $duration The duration of the cache call
     * @param string $command The cache command executed
     * @param array<mixed> $arguments The arguments passed to the cache command
     * @param mixed $result The result returned from the cache command
     * @return void
     */
    public function addCacheCall(float $duration, string $command, array $arguments, mixed $result): void
    {
        $this->request->cache[] = new DebuggerCacheCall($duration, $command, $arguments, $result);
    }

    /**
     * Add an API call entry to the debugger log
     * @param float $duration The duration of the API call
     * @param string $method The HTTP method used
     * @param string $url The URL called
     * @param array<string,mixed> $data The data sent with the request
     * @param array<string,mixed> $options The options used for the request
     * @param array<string,string> $headers The headers sent with the request
     * @param int $status The HTTP status code returned
     * @param array{nameLookup:float,connect:float,preTransfer:float,startTransfer:float,redirect:float,total:float} $timing The timing information for the call
     * @param mixed $result The result returned from the API call
     * @return void
     */
    public function addApiCall(float $duration, string $method, string $url, mixed $data, array $options, array $headers, int $status, array $timing, mixed $result): void
    {
        $this->request->apiCalls[] = new DebuggerApiCall($duration, $method, $url, $data, $options, $headers, $status, $timing, $result);
    }

    /**
     * Add a query entry to the debugger log
     * @param float $duration The duration of the query
     * @param string $query The SQL query executed
     * @param string $equery The SQL query executed
     * @param array<int|string, mixed> $arguments The arguments passed to the query
     * @param mixed $result The result returned from the query
     * @param mixed $explain The result returned from the query
     * @return void
     */
    public function addQuery(float $duration, string $query, string $equery, array $arguments, mixed $result, mixed $explain): void
    {
        $this->request->queries[] = new DebuggerQuery($duration, $query, $equery, $arguments, $result, $explain);
    }

    /**
     * Set the route information for the current request
     * @param string $method The HTTP method used
     * @param bool $csrfOk Whether CSRF validation passed
     * @param string $request The raw request string
     * @param string $url The requested URL
     * @param string $dir The directory of the request
     * @param string $viewFile The view file used
     * @param string $actionFile The action file used
     * @param string $templateFile The template file used
     * @param array<string,mixed> $urlParameters The URL parameters
     * @param array<string,mixed> $getParameters The GET parameters
     * @param array<string,mixed> $postParameters The POST parameters
     * @return void
     */
    public function setRoute(string $method, bool $csrfOk, string $request, string $url, string $dir, string $viewFile, string $actionFile, string $templateFile, array $urlParameters, array $getParameters, array $postParameters): void
    {
        $this->request->route = new DebuggerRoute($method, $csrfOk, $request, $url, $dir, $viewFile, $actionFile, $templateFile, $urlParameters, $getParameters, $postParameters);
    }

    /**
     * Set the redirect URL for the current request
     * @param string $url The URL to redirect to
     * @return void
     */
    public function setRedirect(string $url): void
    {
        $this->request->redirect = $url;
    }

    /**
     * Set the status code for the current request
     * @param int $status The HTTP status code
     * @return void
     */
    public function setStatus(int $status): void
    {
        $this->request->status = $status;
    }

    /**
     * Log the session state before processing the request
     * @return void
     */
    public function logSessionBefore(): void
    {
        $this->request->session->before = $this->getSessionData();
    }

    /**
     * Log the session state after processing the request
     * @return void
     */
    public function logSessionAfter(): void
    {
        $this->request->session->after = $this->getSessionData();
    }

    /**
     * Finalize and store the debugger data at the end of the request
     * @param string $type The type of request completion (e.g., 'ok', 'abort')
     * @return void
     */
    public function end(string $type): void
    {
        if ($this->request->type) {
            return;
        }

        // finalize request data
        $this->request->type = $type;
        $this->request->duration = microtime(true) - (float)$this->request->start;
        $this->request->memory = memory_get_peak_usage(true);
        $this->request->classes = $this->getLoadedFiles();

        // store in session
        $_SESSION[$this->sessionKey][] = $this->request;
        while (count($_SESSION[$this->sessionKey]) > $this->history) {
            array_shift($_SESSION[$this->sessionKey]);
        }
    }

    /**
     * Output the debugger toolbar HTML.
     * @return void
     */
    public function toolbar(): void
    {
        $this->end('ok');
        $html = '<div id="debugger-bar" style="position: fixed; width:100%; left: 0; bottom: 0; border-top: 1px solid silver; background: white;">';
        $html .= '<div style="margin:6px;">';
        $javascript = "document.getElementById('debugger-bar').style.display='none'; return false;";
        $html .= '<a href="#" onclick="' . $javascript . '" style="float:right;">close</a>';
        $parts = [];
        $parts[] = date('H:i:s', (int)$this->request->start);
        $method = $this->request->route->method;
        $url = $this->request->route->url;
        $parts[] = strtolower($method) . ' ' . htmlentities($url);
        $parts[] = round((float)$this->request->duration * 1000) . ' ms ';
        $parts[] = round((float)$this->request->memory / 1000000) . ' MB';
        $html .= implode(' - ', $parts) . ' - <a href="debugger/">debugger</a>';
        if (isset($_SERVER['SERVER_SOFTWARE']) && substr($_SERVER['SERVER_SOFTWARE'], 0, 4) == 'PHP ') {
            $html .= ' - <a href="/adminer.php">adminer</a>';
        }
        $html .= '</div></div>';
        echo $html;
    }

    /**
     * Debug a variable and return its string representation.
     * @param mixed $variable The variable to debug
     * @param int $strlen Maximum string length
     * @param int $width Maximum array/object width
     * @param int $depth Maximum depth for nested structures
     * @param int $i Current depth (used internally) 
     * @param array<object> $objects List of already processed objects (used internally)
     * @return string String representation of the variable
     */
    public function debug(mixed $variable, int $strlen = 100, int $width = 25, int $depth = 10, int $i = 0, array &$objects = []): string
    {
        if (!$this->enabled) {
            return '';
        }

        $search = array("\0", "\a", "\b", "\f", "\n", "\r", "\t", "\v");
        $replace = array('\0', '\a', '\b', '\f', '\n', '\r', '\t', '\v');

        $string = '';

        switch (gettype($variable)) {
            case 'boolean':
                $string .= $variable ? 'true' : 'false';
                break;
            case 'integer':
                $string .= $variable;
                break;
            case 'double':
                $string .= $variable;
                break;
            case 'resource':
                $string .= '[resource]';
                break;
            case 'NULL':
                $string .= "null";
                break;
            case 'unknown type':
                $string .= '???';
                break;
            case 'string':
                $len = strlen($variable);
                $variable = str_replace($search, $replace, substr($variable, 0, $strlen), $count);
                $variable = substr($variable, 0, $strlen);
                if ($len < $strlen) {
                    $string .= '"' . $variable . '"';
                } else {
                    $string .= 'string(' . $len . '): "' . $variable . '"...';
                }

                break;
            case 'array':
                $len = count($variable);
                if ($i == $depth) {
                    $string .= 'array(' . $len . ') {...}';
                } elseif (!$len) {
                    $string .= 'array(0) {}';
                } else {
                    $keys = array_keys($variable);
                    $spaces = str_repeat(' ', $i * 2);
                    $string .= "array($len)\n" . $spaces . '{';
                    $count = 0;
                    foreach ($keys as $key) {
                        if ($count == $width) {
                            $string .= "\n" . $spaces . "  ...";
                            break;
                        }
                        $string .= "\n" . $spaces . "  [$key] => ";
                        $string .= $this->debug($variable[$key], $strlen, $width, $depth, $i + 1, $objects);
                        $count++;
                    }
                    $string .= "\n" . $spaces . '}';
                }
                break;
            case 'object':
                $id = array_search($variable, $objects, true);
                if ($id !== false) {
                    $string .= get_class($variable) . '#' . (((int)$id) + 1) . ' {...}';
                } else if ($i == $depth) {
                    $string .= get_class($variable) . ' {...}';
                } else {
                    $id = array_push($objects, $variable);
                    $array = (array) $variable;
                    $spaces = str_repeat(' ', $i * 2);
                    $string .= get_class($variable) . "#$id\n" . $spaces . '{';
                    $properties = array_keys($array);
                    foreach ($properties as $property) {
                        $name = str_replace("\0", ':', trim($property));
                        $string .= "\n" . $spaces . "  [$name] => ";
                        $string .= $this->debug($array[$property], $strlen, $width, $depth, $i + 1, $objects);
                    }
                    $string .= "\n" . $spaces . '}';
                }
                break;
        }

        if ($i > 0) {
            return $string;
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        do $caller = array_shift($backtrace);
        while ($caller && !isset($caller['file']));
        if ($caller && isset($caller['file'], $caller['line'])) {
            $string = $caller['file'] . ':' . $caller['line'] . "\n" . $string;
        }

        $this->request->log[] = $string;

        return $string;
    }
}
