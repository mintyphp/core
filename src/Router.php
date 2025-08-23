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

	protected static $routes = [];

	public static $initialized = false;

	protected static function initialize()
	{
		if (self::$initialized) return;
		self::$initialized = true;
		self::$method = $_SERVER['REQUEST_METHOD'];
		self::$request = $_SERVER['REQUEST_URI'];
		self::$script = $_SERVER['SCRIPT_NAME'];
		self::$original = null;
		self::$redirect = null;
		self::applyRoutes();
		self::route();
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
		if (!self::$initialized) self::initialize();
		$url = parse_url($url, PHP_URL_HOST) ? $url : self::getBaseUrl() . $url;
		$status = $permanent ? 301 : 302;
		if (Debugger::$enabled) {
			Debugger::set('redirect', $url);
			Debugger::set('status', $status);
			Debugger::end('redirect');
		}
		if (self::$executeRedirect) {
			header("Location: $url", true, $permanent ? 301 : 302);
			die();
		} else {
			self::$redirect = $url;
		}
	}

	public static function json($object)
	{
		if (!self::$initialized) self::initialize();
		if (Debugger::$enabled) {
			Debugger::end('json');
		}
		header('Content-Type: application/json');
		die(json_encode($object));
	}

	public static function download($filename, $data)
	{
		if (!self::$initialized) self::initialize();
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
		if (!self::$initialized) self::initialize();
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

		list($view, $template, $action, $parameterNames) = self::extractParts($root, $dir, $path);

		if ($view) $url = $dir . $view;
		else $url = $dir . $action;

		if ($view) {
			self::$view = $root . $dir . $view . '(' . $template . ').phtml';
			self::$template = $template == 'none' ? false : $templateRoot . $template;
		} else {
			self::$view = false;
			self::$template = false;
		}

		if ($action) {
			if (!count($parameterNames)) self::$action = $root . $dir . $action . '().php';
			else self::$action = $root . $dir . $action . '($' . implode(',$', $parameterNames) . ').php';

			if (substr($url, -7) == '//index') $redirect = substr($url, 0, -7);
			if (substr(self::$original, -6) == '/index') $redirect = substr($url, 0, -6);
			if (count($parameters) > count($parameterNames) /*|| count(array_diff(array_keys($getParameters), $parameterNames)) > 0*/) {
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
				self::$parameters = array_combine($parameterNames, $parameters);
			} else {
				self::$parameters = [];
			}
		} else {
			self::$action = false;
			self::$parameters = [];
		}

		self::$url = $url;

		return $redirect;
	}

	protected static function routeDir($csrfOk, $templateRoot, $root, $dir, $parameters, $getParameters)
	{
		$status = 200;
		$matches = [];

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
				self::error('Could not find 404');
			}
		}
		return array($status, self::routeFile($templateRoot, $root, $dir, $matches[0], $parameters, $getParameters));
	}

	protected static function route()
	{
		$root = self::$pageRoot;
		$dir = '';
		$redirect = false;
		$status = false;

		$request = self::removePrefix(self::$request, self::$script ?: '');
		$request = self::removePrefix($request, self::$baseUrl);
		if (self::$original === null) self::$original = $request;

		$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') == 'xmlhttprequest';
		$csrfOk = in_array(self::$method, ['GET', 'OPTIONS']) ?: ($isAjax || Session::checkCsrfToken());

		$getParameters = [];
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
				list($status, $redirect) = self::routeDir($csrfOk, self::$templateRoot, $root, $dir, $parameters, $getParameters);
				break;
			}
		}
		if (Debugger::$enabled) {
			$method = self::$method;
			$request = '/' . self::$original;
			$url = '/' . self::$url;
			$viewFile = self::$view;
			$actionFile = self::$action;
			$templateFile = self::$template;
			$parameters = [];
			$parameters['url'] = self::$parameters;
			$parameters['get'] = $_GET;
			$parameters['post'] = $_POST;
			Debugger::set('router', compact('method', 'csrfOk', 'request', 'url', 'dir', 'viewFile', 'actionFile', 'templateFile', 'parameters'));
			Debugger::set('status', $status);
		}
		if ($redirect) self::redirect($redirect);
	}

	public static function getUrl()
	{
		if (!self::$initialized) self::initialize();
		return self::$url;
	}

	public static function getCanonical()
	{
		$canonical = self::$url;
		if (substr($canonical, -6) == '/index' || $canonical == 'index') {
			$canonical = substr($canonical, 0, -5);
		}
		return $canonical . implode('/', self::$parameters);
	}

	public static function addRoute($sourcePath, $destinationPath)
	{
		self::$routes[$destinationPath] = $sourcePath;
	}

	protected static function applyRoutes()
	{
		if (!self::$initialized) self::initialize();
		foreach (self::$routes as $destinationPath => $sourcePath) {
			if (rtrim(self::$request, '/') == rtrim(self::$baseUrl . $sourcePath, '/')) {
				self::$request = self::$baseUrl . $destinationPath;
				break;
			}
		}
	}

	public static function getRequest()
	{
		if (!self::$initialized) self::initialize();
		return self::$request;
	}

	public static function getAction()
	{
		if (!self::$initialized) self::initialize();
		return self::$action;
	}

	public static function getRedirect()
	{
		if (!self::$initialized) self::initialize();
		return self::$redirect;
	}

	public static function getView()
	{
		if (!self::$initialized) self::initialize();
		return self::$view;
	}

	public static function getTemplateView()
	{
		if (!self::$initialized) self::initialize();
		if (!self::$template) return false;
		$filename = self::$template . '.phtml';
		return file_exists($filename) ? $filename : false;
	}

	public static function getTemplateAction()
	{
		if (!self::$initialized) self::initialize();
		if (!self::$template) return false;
		$filename = self::$template . '.php';
		return file_exists($filename) ? $filename : false;
	}

	public static function getParameters()
	{
		if (!self::$initialized) self::initialize();
		if (!self::$parameters) return [];
		else return self::$parameters;
	}

	public static function getBaseUrl()
	{
		$url = self::$baseUrl;
		if (substr($url, 0, 4) != 'http') {
			$host = ($_SERVER['HTTP_HOST'] ?? 'localhost');
			if (substr($url, 0, 2) != '//') {
				$url = '//' . $host . $url;
			}
			$s = ($_SERVER['HTTPS'] ?? '') ? 's' : '';
			$url = "http$s:$url";
		}
		return rtrim($url, '/') . '/';
	}
}
