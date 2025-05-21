<?php

namespace MintyPHP;

class Debugger
{
	public static int $history = 10;
	public static bool $enabled = false;
	public static string $sessionKey = 'debugger';

	protected static ?mixed $request = null;

	protected static bool $initialized = false;

	protected static function initialize(): void
	{
		if (self::$initialized) {
			return;
		}

		self::$initialized = true;
		if (!self::$enabled) {
			ini_set('display_errors', 0);
			error_reporting(0);
			return;
		}
		ini_set('display_errors', 1);
		error_reporting(-1);
		self::$request = array('log' => [], 'queries' => [], 'api_calls' => [], 'session' => [], 'cache' => []);
		Session::start();
		$_SESSION[self::$sessionKey][] = &self::$request;
		while (count($_SESSION[self::$sessionKey]) > self::$history) {
			array_shift($_SESSION[self::$sessionKey]);
		}

		self::set('start', microtime(true));
		self::set('user', get_current_user());
		register_shutdown_function('MintyPHP\\Debugger::end', 'abort');
	}

	public static function logSession($title): void
	{
		if (!self::$initialized) {
			self::initialize();
		}

		$session = [];
		foreach ($_SESSION as $k => $v) {
			if ($k == 'debugger') {
				$v = true;
			}

			$session[$k] = $v;
		}
		$data = self::debug($session);
		$data = substr($data, strpos($data, "\n"));
		self::$request['session'][$title] = trim($data);
		array_pop(self::$request['log']);
	}

	public static function set($key, $value): void
	{
		if (!self::$initialized) {
			self::initialize();
		}

		self::$request[$key] = $value;
	}

	public static function add($key, $value): void
	{
		if (!self::$initialized) {
			self::initialize();
		}

		if (!isset(self::$request[$key])) {
			self::$request[$key] = [];
		}
		self::$request[$key][] = $value;
	}

	public static function get($key): mixed
	{
		if (!self::$initialized) {
			self::initialize();
		}

		return isset(self::$request[$key]) ? self::$request[$key] : false;
	}

	private static function getLoadedFiles()
	{
		$result = [];
		$classes = get_declared_classes();
		$afterComposer = false;
		foreach ($classes as $class) {
			if (substr($class, 0, 8) == 'Composer') {
				$afterComposer = true;
			} elseif ($afterComposer) {
				$reflection = new \ReflectionClass($class);
				$result[] = $reflection->getFileName();
			}
		}
		return $result;
	}

	public static function end($type)
	{
		if (self::get('type')) {
			return;
		}

		Session::end();
		self::set('type', $type);
		self::set('duration', microtime(true) - self::get('start'));
		self::set('memory', memory_get_peak_usage(true));
		self::set('classes', self::getLoadedFiles());
	}

	public static function toolbar()
	{
		self::end('ok');
		$html = '<div id="debugger-bar" style="position: fixed; width:100%; left: 0; bottom: 0; border-top: 1px solid silver; background: white;">';
		$html .= '<div style="margin:6px;">';
		$javascript = "document.getElementById('debugger-bar').style.display='none'; return false;";
		$html .= '<a href="#" onclick="' . $javascript . '" style="float:right;">close</a>';
		$request = self::$request;
		$parts = [];
		$parts[] = date('H:i:s', (int)$request['start']);
		$parts[] = strtolower($request['router']['method']) . ' ' . htmlentities($request['router']['url']);
		if (!isset($request['type'])) {
			$parts[] = '???';
		} else {
			$parts[] = round($request['duration'] * 1000) . ' ms ';
			$parts[] = round($request['memory'] / 1000000) . ' MB';
		}
		$html .= implode(' - ', $parts) . ' - <a href="debugger/">debugger</a>';
		if (substr($_SERVER['SERVER_SOFTWARE'], 0, 4) == 'PHP ') {
			$html .= ' - <a href="/adminer.php">adminer</a>';
		}
		$html .= '</div></div>';
		echo $html;
	}

	public static function debug($variable, $strlen = 100, $width = 25, $depth = 10, $i = 0, &$objects = [])
	{
		if (!self::$enabled) {
			return;
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
						$string .= self::debug($variable[$key], $strlen, $width, $depth, $i + 1, $objects);
						$count++;
					}
					$string .= "\n" . $spaces . '}';
				}
				break;
			case 'object':
				$id = array_search($variable, $objects, true);
				if ($id !== false) {
					$string .= get_class($variable) . '#' . ($id + 1) . ' {...}';
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
						$string .= self::debug($array[$property], $strlen, $width, $depth, $i + 1, $objects);
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
		if ($caller) {
			$string = $caller['file'] . ':' . $caller['line'] . "\n" . $string;
		}

		self::add('log', $string);

		return $string;
	}
}
