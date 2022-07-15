<?php

namespace MintyPHP;

class Router
{
	protected static $method = null;
	protected static $original = null;
	protected static $request = null;
	protected static $script = null;

	public static $baseUrl = '/';
	public static $pageRoot = 'pages/';
	public static $templateRoot = 'templates/';
	public static $executeRedirect = true;

	protected static $url = null;
	protected static $view = null;
	protected static $action = null;
	protected static $template = null;
	protected static $parameters = null;
	protected static $redirect = null;

	protected static $routes = array();

	public static $initialized = false;

	protected static function initialize()
	{
		if (static::$initialized) return;
		static::$initialized = true;
		static::$method = $_SERVER['REQUEST_METHOD'];
		static::$request = $_SERVER['REQUEST_URI'];
		static::$script = $_SERVER['SCRIPT_NAME'];
		static::$original = null;
		static::$redirect = null;
		static::applyRoutes();
		static::route();
	}

	protected static function error($message)
	{
		if (Debugger::$enabled) {
			Debugger::set('status', 500);
		}
		throw new RouterError($message);
	}

	protected static function removePrefix($string, $prefix)
	{
		if (substr($string, 0, strlen($prefix)) == $prefix) {
			$string = substr($string, strlen($prefix));
		}

		return $string;
	}

	public static function redirect($url, $permanent = false)
	{
		if (!static::$initialized) static::initialize();
		$url = parse_url($url, PHP_URL_HOST) ? $url : static::getBaseUrl() . $url;
		$status = $permanent ? 301 : 302;
		if (Debugger::$enabled) {
			Debugger::set('redirect', $url);
			Debugger::set('status', $status);
			Debugger::end('redirect');
		}
		if (static::$executeRedirect) {
			die(header("Location: $url", true, $permanent ? 301 : 302));
		} else {
			static::$redirect = $url;
		}
	}

	public static function json($object)
	{
		if (!static::$initialized) static::initialize();
		if (Debugger::$enabled) {
			Debugger::end('json');
		}
		header('Content-Type: application/json');
		die(json_encode($object));
	}

	public static function download($filename, $data)
	{
		if (!static::$initialized) static::initialize();
		if (Debugger::$enabled) {
			Debugger::end('download');
		}
		header('Content-Type: application/octet-stream');
		header("Content-Transfer-Encoding: Binary");
		header("Content-disposition: attachment; filename=\"" . $filename . "\"");
		header('Content-Length: ' . strlen($data));
		die($data);
	}

	public static function file($filename, $filepath)
	{
		if (!static::$initialized) static::initialize();
		if (Debugger::$enabled) {
			Debugger::end('download');
		}
		header('Content-Type: application/octet-stream');
		header("Content-Transfer-Encoding: Binary");
		header("Content-disposition: attachment; filename=\"" . $filename . "\"");
		header('Content-Length: ' . filesize($filepath));
		readfile($filepath);
		die();
	}

	protected static function extractParts($root, $dir, $match)
	{
		$view = false;
		$template = false;
		$action = false;
		$parameters = false;

		$filename = basename($match);
		$parts = preg_split('/\(|\)/', $filename);
		$extension = array_pop($parts);
		if ($extension == '.phtml') {
			$template = array_pop($parts);
			$view = array_pop($parts);
			$matches = glob($root . $dir . $view . '(*).php');
			if (count($matches) > 0) {
				$filename = basename($matches[0]);
				$parts = preg_split('/\(|\)/', $filename);
				$extension = array_pop($parts);
				$parameters = array_pop($parts);
				preg_match_all('/,?(\$([^,\)]+))+/', $parameters, $matches);
				$parameters = $matches[2];
				$action = array_pop($parts);
			}
		} else {
			$parameters = array_pop($parts);
			preg_match_all('/,?(\$([^,\)]+))+/', $parameters, $matches);
			$parameters = $matches[2];
			$action = array_pop($parts);
			$matches = glob($root . $dir . $action . '(*).phtml');
			if (count($matches) > 0) {
				$filename = basename($matches[0]);
				$parts = preg_split('/\(|\)/', $filename);
				$extension = array_pop($parts);
				$template = array_pop($parts);
				$view = array_pop($parts);
			}
		}
		return array($view, $template, $action, $parameters);
	}

	protected static function routeFile($templateRoot, $root, $dir, $path, $parameters, $getParameters)
	{
		$redirect = false;

		list($view, $template, $action, $parameterNames) = static::extractParts($root, $dir, $path);

		if ($view) $url = $dir . $view;
		else $url = $dir . $action;

		if ($view) {
			static::$view = $root . $dir . $view . '(' . $template . ').phtml';
			static::$template = $template == 'none' ? false : $templateRoot . $template;
		} else {
			static::$view = false;
			static::$template = false;
		}

		if ($action) {
			if (!count($parameterNames)) static::$action = $root . $dir . $action . '().php';
			else static::$action = $root . $dir . $action . '($' . implode(',$', $parameterNames) . ').php';

			if (substr($url, -7) == '//index') $redirect = substr($url, 0, -7);
			if (substr(static::$original, -6) == '/index') $redirect = substr($url, 0, -6);
			if (count($parameters) > count($parameterNames) || count(array_diff(array_keys($getParameters), $parameterNames)) > 0) {
				if (substr($url, -6) == '/index') $url = substr($url, 0, -6);
				if ($url == 'index') $url = '';
				$redirect = $url;
				for ($i = 0; $i < min(count($parameters), count($parameterNames)); $i++) {
					$redirect .= '/' . $parameters[$i];
				}
				$query = http_build_query(array_intersect_key($getParameters, array_flip($parameterNames)));
				$redirect .= $query ? '?' . $query : '';
			}
			$parameters = array_map('urldecode', $parameters);
			if (count($parameters) < count($parameterNames)) {
				for ($i = count($parameters); $i < count($parameterNames); $i++) {
					array_push($parameters, isset($getParameters[$parameterNames[$i]]) ? $getParameters[$parameterNames[$i]] : null);
				}
			}
			if (!$redirect && count($parameterNames)) {
				static::$parameters = array_combine($parameterNames, $parameters);
			} else {
				static::$parameters = array();
			}
		} else {
			static::$action = false;
			static::$parameters = array();
		}

		static::$url = $url;

		return $redirect;
	}

	protected static function routeDir($csrfOk, $templateRoot, $root, $dir, $parameters, $getParameters)
	{
		$status = 200;
		$matches = array();

		// route 403
		if (!$csrfOk) {
			$status = 403;
			$dir = 'error/';
			$matches = glob($root . $dir . 'forbidden(*).phtml');
			if (count($matches) == 0) {
				$matches = glob($root . $dir . 'forbidden(*).php');
			}
		}
		// normal route
		else {
			if (count($parameters)) {
				$part = array_shift($parameters);
				$matches = glob($root . $dir . $part . '(*).phtml');
				if (count($matches) == 0) {
					$matches = glob($root . $dir . $part . '(*).php');
				}
				if (count($matches) == 0) {
					array_unshift($parameters, $part);
				}
			}
			if (count($matches) == 0) {
				$matches = glob($root . $dir . 'index(*).phtml');
				if (count($matches) == 0) {
					$matches = glob($root . $dir . 'index(*).php');
				}
			}
		}
		// route 404
		if (count($matches) == 0) {
			$status = 404;
			$dir = 'error/';
			$matches = glob($root . $dir . 'not_found(*).phtml');
			if (count($matches) == 0) {
				$matches = glob($root . $dir . 'not_found(*).php');
			}
			if (count($matches) == 0) {
				static::error('Could not find 404');
			}
		}
		return array($status, static::routeFile($templateRoot, $root, $dir, $matches[0], $parameters, $getParameters));
	}

	protected static function route()
	{
		$root = static::$pageRoot;
		$dir = '';
		$redirect = false;
		$status = false;

		$request = static::removePrefix(static::$request, static::$script ?: '');
		$request = static::removePrefix($request, static::$baseUrl);
		if (static::$original === null) static::$original = $request;

		$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') == 'xmlhttprequest';
		$csrfOk = in_array(static::$method, ['GET', 'OPTIONS']) ?: ($isAjax || Session::checkCsrfToken());

		$getParameters = array();
		$questionMarkPosition = strpos($request, '?');
		if ($questionMarkPosition !== false) {
			list($request, $query) = explode('?', $request, 2);
			parse_str($query, $getParameters);
		}

		$parts = explode('/', $request);
		for ($i = count($parts); $i >= 0; $i--) {
			if ($i == 0) $dir = '';
			else $dir = implode('/', array_slice($parts, 0, $i)) . '/';
			if (file_exists($root . $dir) && is_dir($root . $dir)) {
				$parameters = array_slice($parts, $i, count($parts) - $i);
				list($status, $redirect) = static::routeDir($csrfOk, static::$templateRoot, $root, $dir, $parameters, $getParameters);
				break;
			}
		}
		if (Debugger::$enabled) {
			$method = static::$method;
			$request = '/' . static::$original;
			$url = '/' . static::$url;
			$viewFile = static::$view;
			$actionFile = static::$action;
			$templateFile = static::$template;
			$parameters = array();
			$parameters['url'] = static::$parameters;
			$parameters['get'] = $_GET;
			$parameters['post'] = $_POST;
			Debugger::set('router', compact('method', 'csrfOk', 'request', 'url', 'dir', 'viewFile', 'actionFile', 'templateFile', 'parameters'));
			Debugger::set('status', $status);
		}
		if ($redirect) static::redirect($redirect);
	}

	public static function getUrl()
	{
		if (!static::$initialized) static::initialize();
		return static::$url;
	}

	public static function addRoute($sourcePath, $destinationPath)
	{
		static::$routes[$destinationPath] = $sourcePath;
	}

	protected static function applyRoutes()
	{
		if (!static::$initialized) static::initialize();
		foreach (static::$routes as $destinationPath => $sourcePath) {
			if (rtrim(static::$request, '/') == rtrim(static::$baseUrl . $sourcePath, '/')) {
				static::$request = static::$baseUrl . $destinationPath;
				break;
			}
		}
	}

	public static function getRequest()
	{
		if (!static::$initialized) static::initialize();
		return static::$request;
	}

	public static function getAction()
	{
		if (!static::$initialized) static::initialize();
		return static::$action;
	}

	public static function getRedirect()
	{
		if (!static::$initialized) static::initialize();
		return static::$redirect;
	}

	public static function getView()
	{
		if (!static::$initialized) static::initialize();
		return static::$view;
	}

	public static function getTemplateView()
	{
		if (!static::$initialized) static::initialize();
		if (!static::$template) return false;
		$filename = static::$template . '.phtml';
		return file_exists($filename) ? $filename : false;
	}

	public static function getTemplateAction()
	{
		if (!static::$initialized) static::initialize();
		if (!static::$template) return false;
		$filename = static::$template . '.php';
		return file_exists($filename) ? $filename : false;
	}

	public static function getParameters()
	{
		if (!static::$initialized) static::initialize();
		if (!static::$parameters) return array();
		else return static::$parameters;
	}

	public static function getBaseUrl()
	{
		$url = static::$baseUrl;
		if (substr($url, 0, 4) != 'http') {
			$portNumber = ($_SERVER['SERVER_PORT'] ?? 80);
			$port = $portNumber == 80 ? '' : ':' . $portNumber;
			$host = ($_SERVER['HTTP_HOST'] ?? 'localhost');
			if (substr($url, 0, 2) != '//') {
				$url = '//' . $host . $port . $url;
			}
			$s = (($_SERVER['HTTPS'] ?? 'off') != 'off') ? 's' : '';
			$url = "http$s:$url";
		}
		return rtrim($url, '/') . '/';
	}
}
