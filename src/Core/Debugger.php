<?php

namespace MintyPHP\Core;

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
     * 
     * @var array<string, mixed>
     */
    private array $request;

    private bool $initialized = false;

    public function __construct(
        int $history = 10,
        bool $enabled = false,
        string $sessionKey = 'debugger'
    ) {
        $this->history = $history;
        $this->enabled = $enabled;
        $this->sessionKey = $sessionKey;
        // initialize session management
        if (!$this->enabled) {
            ini_set('display_errors', 0);
            error_reporting(0);
            return;
        }
        ini_set('display_errors', 1);
        error_reporting(-1);
        $this->request = array('log' => [], 'queries' => [], 'api_calls' => [], 'session' => [], 'cache' => []);
        $this->session->start();
        $_SESSION[$this->sessionKey][] = &$this->request;
        while (count($_SESSION[$this->sessionKey]) > $this->history) {
            array_shift($_SESSION[$this->sessionKey]);
        }

        $this->set('start', microtime(true));
        $this->set('user', get_current_user());
        register_shutdown_function([$this, 'end'], 'abort');
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function logSession(string $title): void
    {
        $session = [];
        foreach ($_SESSION as $k => $v) {
            if ($k == 'debugger') {
                $v = true;
            }

            $session[$k] = $v;
        }
        $data = $this->debug($session);
        if ($data !== null && $this->request !== null) {
            $pos = strpos($data, "\n");
            $data = substr($data, $pos !== false ? $pos : 0);
            if (!isset($this->request['session'])) {
                $this->request['session'] = [];
            }
            if (is_array($this->request['session'])) {
                $this->request['session'][$title] = trim($data);
            }
            if (is_array($this->request['log'])) {
                array_pop($this->request['log']);
            }
        }
    }

    public function set(string $key, mixed $value): void
    {
        if (!$this->initialized) {
            $this->initialized = true;
            if (!$this->enabled) {
                $this->request = array('log' => [], 'queries' => [], 'api_calls' => [], 'session' => [], 'cache' => []);
            } else {
                $this->initialize();
            }
        }

        if ($this->request !== null) {
            $this->request[$key] = $value;
        }
    }

    public function add(string $key, mixed $value): void
    {
        if (!$this->initialized) {
            $this->initialized = true;
            if (!$this->enabled) {
                $this->request = array('log' => [], 'queries' => [], 'api_calls' => [], 'session' => [], 'cache' => []);
            } else {
                $this->initialize();
            }
        }

        if ($this->request !== null) {
            if (!isset($this->request[$key])) {
                $this->request[$key] = [];
            }
            if (is_array($this->request[$key])) {
                $this->request[$key][] = $value;
            }
        }
    }

    public function get(string $key): mixed
    {
        if (!$this->initialized) {
            $this->initialized = true;
            if (!$this->enabled) {
                $this->request = array('log' => [], 'queries' => [], 'api_calls' => [], 'session' => [], 'cache' => []);
            } else {
                $this->initialize();
            }
        }

        return $this->request !== null && isset($this->request[$key]) ? $this->request[$key] : false;
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

    public function end(string $type): void
    {
        if ($this->get('type')) {
            return;
        }

        $this->session->end();
        if ($this->request !== null) {
            $this->set('type', $type);
            $start = $this->get('start');
            if (is_numeric($start)) {
                $this->set('duration', microtime(true) - (float)$start);
            }
            $this->set('memory', memory_get_peak_usage(true));
            $this->set('classes', $this->getLoadedFiles());
        }
    }

    public function toolbar(): void
    {
        $this->end('ok');
        if ($this->request === null) {
            return;
        }
        $html = '<div id="debugger-bar" style="position: fixed; width:100%; left: 0; bottom: 0; border-top: 1px solid silver; background: white;">';
        $html .= '<div style="margin:6px;">';
        $javascript = "document.getElementById('debugger-bar').style.display='none'; return false;";
        $html .= '<a href="#" onclick="' . $javascript . '" style="float:right;">close</a>';
        $request = $this->request;
        $parts = [];
        if (isset($request['start']) && is_numeric($request['start'])) {
            $parts[] = date('H:i:s', (int)$request['start']);
        }
        if (isset($request['router']) && is_array($request['router'])) {
            if (isset($request['router']['method'], $request['router']['url'])) {
                $method = is_string($request['router']['method']) ? $request['router']['method'] : '';
                $url = is_string($request['router']['url']) ? $request['router']['url'] : '';
                $parts[] = strtolower($method) . ' ' . htmlentities($url);
            }
        }
        if (!isset($request['type'])) {
            $parts[] = '???';
        } else {
            if (isset($request['duration']) && is_numeric($request['duration'])) {
                $parts[] = round((float)$request['duration'] * 1000) . ' ms ';
            }
            if (isset($request['memory']) && is_numeric($request['memory'])) {
                $parts[] = round((float)$request['memory'] / 1000000) . ' MB';
            }
        }
        $html .= implode(' - ', $parts) . ' - <a href="debugger/">debugger</a>';
        if (isset($_SERVER['SERVER_SOFTWARE']) && substr($_SERVER['SERVER_SOFTWARE'], 0, 4) == 'PHP ') {
            $html .= ' - <a href="/adminer.php">adminer</a>';
        }
        $html .= '</div></div>';
        echo $html;
    }

    /** @param array<object> $objects */
    public function debug(mixed $variable, int $strlen = 100, int $width = 25, int $depth = 10, int $i = 0, array &$objects = []): ?string
    {
        if (!$this->enabled) {
            return null;
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

        $this->add('log', $string);

        return $string;
    }
}
