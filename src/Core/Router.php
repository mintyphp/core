<?php

namespace MintyPHP\Core;

use MintyPHP\Debugger;
use MintyPHP\Session;
use MintyPHP\RouterError;

class Router
{

	// Default static configuration
	public static string $__baseUrl = '/';
	public static string $__pageRoot = 'pages/';
	public static string $__templateRoot = 'templates/';
	public static bool $__executeRedirect = true;
	public static bool $__initialized = false;

	// Configuration properties
	private string $baseUrl;
	private string $pageRoot;
	private string $templateRoot;
	private bool $executeRedirect;
	/** @var array<string, string> */
	private array $serverGlobal;

	// Request state properties
	private string $method;
	private string $original;
	private string $request;
	private string $script;

	// Routing result properties
	private string $url;
	private string $view;
	private string $action;
	private string $template;
	/** @var array<string, string|null> */
	private array $parameters;
	private string $redirect;

	// Route mappings
	/** @var array<string, string> */
	private array $routes = [];

	/**
	 * Constructor
	 * @param string $baseUrl
	 * @param string $pageRoot
	 * @param string $templateRoot
	 * @param bool $executeRedirect
	 * @param array<string, string> $serverGlobal
	 * @param array<string, string> $routes
	 */
	public function __construct(
		string $baseUrl,
		string $pageRoot,
		string $templateRoot,
		bool $executeRedirect,
		array $serverGlobal,
		array $routes = [],
	) {
		$this->baseUrl = $baseUrl;
		$this->pageRoot = $pageRoot;
		$this->templateRoot = $templateRoot;
		$this->executeRedirect = $executeRedirect;
		$this->serverGlobal = $serverGlobal;
		$this->routes = $routes;
		$this->method = $this->serverGlobal['REQUEST_METHOD'] ?? 'GET';
		$this->request = $this->serverGlobal['REQUEST_URI'] ?? '/';
		$this->script = $this->serverGlobal['SCRIPT_NAME'] ?? 'index.php';
		$this->original = '';
		$this->redirect = '';
		$this->applyRoutes();
		$this->route();
	}

	// ========================================
	// Error Handling
	// ========================================

	/**
	 * Throw a router error with 500 status
	 * @param string $message
	 * @return void
	 * @throws RouterError
	 */
	private function error(string $message): void
	{
		if (Debugger::$enabled) {
			Debugger::set('status', 500);
		}
		throw new RouterError($message);
	}

	// ========================================
	// Public Response Methods
	// ========================================

	/**
	 * Redirect to a URL
	 * @param string $url
	 * @param bool $permanent
	 * @return void
	 */
	public function redirect(string $url, bool $permanent = false): void
	{
		$url = parse_url($url, PHP_URL_HOST) ? $url : $this->getBaseUrl() . $url;
		$status = $permanent ? 301 : 302;
		if (Debugger::$enabled) {
			Debugger::set('redirect', $url);
			Debugger::set('status', $status);
			Debugger::end('redirect');
		}
		if ($this->executeRedirect) {
			header("Location: $url", true, $status);
			die();
		} else {
			$this->redirect = $url;
		}
	}

	public function json(mixed $object): void
	{
		if (Debugger::$enabled) {
			Debugger::end('json');
		}
		header('Content-Type: application/json');
		die(json_encode($object));
	}

	public function download(string $filename, string $data): void
	{
		if (Debugger::$enabled) {
			Debugger::end('download');
		}
		header('Content-Type: application/octet-stream');
		header("Content-Transfer-Encoding: Binary");
		header("Content-disposition: attachment; filename=\"" . $filename . "\"");
		header('Content-Length: ' . strlen($data));
		die($data);
	}

	public function file(string $filename, string $filepath): void
	{
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

	// ========================================
	// Route Management
	// ========================================

	/**
	 * Apply route mappings to the current request
	 * @return void
	 */
	public function applyRoutes(): void
	{
		foreach ($this->routes as $destinationPath => $sourcePath) {
			if (rtrim($this->request, '/') == rtrim($this->baseUrl . $sourcePath, '/')) {
				$this->request = $this->baseUrl . $destinationPath;
				break;
			}
		}
	}

	/**
	 * Add a route mapping and re-route
	 * @param string $sourcePath
	 * @param string $destinationPath
	 * @return void
	 */
	public function addRoute(string $sourcePath, string $destinationPath): void
	{
		$this->routes[$destinationPath] = $sourcePath;
		// Reset to original request before applying routes
		$this->request = $this->serverGlobal['REQUEST_URI'] ?? '/';
		$this->original = '';
		$this->applyRoutes();
		$this->route();
	}

	// ========================================
	// Routing Logic
	// ========================================

	/**
	 * Extract view, template, action and parameters from a filename
	 * @param string $root
	 * @param string $dir
	 * @param string $match
	 * @return array{0: string, 1: string, 2: string, 3: array<string>}
	 */
	private function extractParts(string $root, string $dir, string $match): array
	{
		$view = '';
		$template = '';
		$action = '';
		$parameters = [];

		$filename = basename($match);
		$parts = preg_split('/\(|\)/', $filename);
		if ($parts === false) {
			return [$view, $template, $action, $parameters];
		}
		$extension = array_pop($parts) ?: '';
		if ($extension == '.phtml') {
			$template = array_pop($parts) ?: '';
			$view = array_pop($parts) ?: '';
			$matches = glob($root . $dir . $view . '(*)' . '.php');
			if ($matches !== false && count($matches) > 0) {
				$filename = basename($matches[0]);
				$parts = preg_split('/\(|\)/', $filename);
				if ($parts === false) {
					return [$view, $template, $action, $parameters];
				}
				array_pop($parts); // Remove extension (.php)
				$paramString = array_pop($parts);
				if ($paramString !== null) {
					preg_match_all('/,?(\$([^,\)]+))+/', $paramString, $matches);
					$parameters = $matches[2];
				}
				$action = array_pop($parts) ?: '';
			}
		} else {
			$paramString = array_pop($parts);
			if ($paramString !== null) {
				preg_match_all('/,?(\$([^,\)]+))+/', $paramString, $matches);
				$parameters = $matches[2];
			}
			$action = array_pop($parts) ?: '';
			$matches = glob($root . $dir . $action . '(*)' . '.phtml');
			if ($matches !== false && count($matches) > 0) {
				$filename = basename($matches[0]);
				$parts = preg_split('/\(|\)/', $filename);
				if ($parts === false) {
					return [$view, $template, $action, $parameters];
				}
				array_pop($parts); // Remove extension (.phtml)
				$template = array_pop($parts) ?: '';
				$view = array_pop($parts) ?: '';
			}
		}
		return array($view, $template, $action, $parameters);
	}

	/**
	 * @param string $templateRoot
	 * @param string $root
	 * @param string $dir
	 * @param string $path
	 * @param array<int, string> $parameters
	 * @param array<int|string, mixed> $getParameters
	 * @return string|false
	 */
	private function routeFile(string $templateRoot, string $root, string $dir, string $path, array $parameters, array $getParameters): string|false
	{
		$redirect = false;

		list($view, $template, $action, $parameterNames) = $this->extractParts($root, $dir, $path);

		$url = $view ? $dir . $view : $dir . $action;

		if ($view) {
			$this->view = $root . $dir . $view . '(' . $template . ')' . '.phtml';
			$this->template = $template == 'none' ? '' : $templateRoot . $template;
		} else {
			$this->view = '';
			$this->template = '';
		}

		if ($action) {
			if (count($parameterNames) === 0) $this->action = $root . $dir . $action . '()' . '.php';
			else $this->action = $root . $dir . $action . '($' . implode(',$', $parameterNames) . ')' . '.php';

			if (substr($url, -7) == '//' . 'index') $redirect = substr($url, 0, -7);
			if (substr($this->original, -6) == '/' . 'index') $redirect = substr($url, 0, -6);
			if (count($parameters) > count($parameterNames)) {
				if (substr($url, -6) == '/' . 'index') $url = substr($url, 0, -6);
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
					$value = $getParameters[$parameterNames[$i]] ?? '';
					array_push($parameters, is_string($value) ? $value : '');
				}
			}
			if (!$redirect && count($parameterNames) > 0) {
				$this->parameters = array_combine($parameterNames, $parameters) ?: [];
			} else {
				$this->parameters = [];
			}
		} else {
			$this->action = '';
			$this->parameters = [];
		}

		$this->url = $url;

		return $redirect;
	}

	/**
	 * Find matching files for a pattern
	 * @param string $root
	 * @param string $dir
	 * @param string $pattern
	 * @return array<string>
	 */
	private function findFiles(string $root, string $dir, string $pattern): array
	{
		$matches = glob($root . $dir . $pattern . '(*)' . '.phtml');
		if ($matches === false || count($matches) == 0) {
			$matches = glob($root . $dir . $pattern . '(*)' . '.php');
		}
		return $matches === false ? [] : $matches;
	}

	/**
	 * @param bool $csrfOk
	 * @param string $templateRoot
	 * @param string $root
	 * @param string $dir
	 * @param array<int, string> $parameters
	 * @param array<int|string, mixed> $getParameters
	 * @return array{0: int, 1: string|false}
	 */
	private function routeDir(bool $csrfOk, string $templateRoot, string $root, string $dir, array $parameters, array $getParameters): array
	{
		$status = 200;
		$matches = [];

		// route 403
		if (!$csrfOk) {
			$status = 403;
			$dir = 'error/';
			$matches = $this->findFiles($root, $dir, 'forbidden');
		}
		// normal route
		else {
			if (count($parameters) > 0) {
				$part = array_shift($parameters);
				$matches = $this->findFiles($root, $dir, $part);
				if (count($matches) == 0) {
					array_unshift($parameters, $part);
				}
			}
			if (count($matches) == 0) {
				$matches = $this->findFiles($root, $dir, 'index');
			}
		}
		// route 404
		if (count($matches) == 0) {
			$status = 404;
			$dir = 'error/';
			$matches = $this->findFiles($root, $dir, 'not_found');
			if (count($matches) == 0) {
				$this->error('Could not find 404');
			}
		}
		return array($status, $this->routeFile($templateRoot, $root, $dir, $matches[0], $parameters, $getParameters));
	}

	/**
	 * Main routing logic
	 * @return void
	 */
	private function route(): void
	{
		$root = $this->pageRoot;
		$dir = '';
		$redirect = false;
		$status = false;

		$request = $this->parseRequest();
		$csrfOk = $this->checkCsrfProtection();

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
				list($status, $redirect) = $this->routeDir($csrfOk, $this->templateRoot, $root, $dir, $parameters, $getParameters);
				break;
			}
		}
		if (Debugger::$enabled) {
			$method = $this->method;
			$request = '/' . $this->original;
			$url = '/' . $this->url;
			$viewFile = $this->view;
			$actionFile = $this->action;
			$templateFile = $this->template;
			$parameters = [];
			$parameters['url'] = $this->parameters;
			$parameters['get'] = $_GET;
			$parameters['post'] = $_POST;
			Debugger::set('router', compact('method', 'csrfOk', 'request', 'url', 'dir', 'viewFile', 'actionFile', 'templateFile', 'parameters'));
			Debugger::set('status', $status);
		}
		if ($redirect) $this->redirect($redirect);
	}

	/**
	 * Parse and normalize the request URI
	 * @return string
	 */
	private function parseRequest(): string
	{
		$request = $this->removePrefix($this->request, $this->script ?: '');
		$request = $this->removePrefix($request, $this->baseUrl);
		if ($this->original === '') $this->original = $request;
		return $request;
	}

	/**
	 * Check CSRF protection for non-safe methods
	 * @return bool
	 */
	private function checkCsrfProtection(): bool
	{
		$isAjax = strtolower($this->serverGlobal['HTTP_X_REQUESTED_WITH'] ?? '') == 'xmlhttprequest';
		return in_array($this->method, ['GET', 'OPTIONS']) ?: ($isAjax || Session::checkCsrfToken());
	}

	/**
	 * Remove prefix from string
	 * @param string $string
	 * @param string $prefix
	 * @return string
	 */
	private function removePrefix(string $string, string $prefix): string
	{
		if (substr($string, 0, strlen($prefix)) == $prefix) {
			$string = substr($string, strlen($prefix));
		}
		return $string;
	}

	// ========================================
	// Public Getters
	// ========================================

	public function getUrl(): string
	{
		return $this->url;
	}

	public function getCanonical(): string
	{
		$canonical = $this->url;
		if (substr($canonical, -6) == '/' . 'index' || $canonical == 'index') {
			$canonical = substr($canonical, 0, -5);
		}
		return $canonical . implode('/', $this->parameters);
	}

	public function getRequest(): string
	{
		return $this->request;
	}

	public function getAction(): string
	{
		return $this->action;
	}

	public function getRedirect(): ?string
	{
		return (isset($this->redirect) && $this->redirect !== '') ? $this->redirect : null;
	}

	public function getView(): string
	{
		return $this->view;
	}

	public function getTemplateView(): string
	{
		if (!$this->template) return '';
		$filename = $this->template . '.phtml';
		return file_exists($filename) ? $filename : '';
	}

	public function getTemplateAction(): string
	{
		if (!$this->template) return '';
		$filename = $this->template . '.php';
		return file_exists($filename) ? $filename : '';
	}

	/**
	 * Get parameters extracted from the URL
	 * @return array<string, string|null>
	 */
	public function getParameters(): array
	{
		return $this->parameters;
	}

	public function getBaseUrl(): string
	{
		$url = $this->baseUrl;
		if (substr($url, 0, 4) != 'http') {
			$host = ($this->serverGlobal['HTTP_HOST'] ?? 'localhost');
			if (substr($url, 0, 2) != '//') {
				$url = '//' . $host . $url;
			}
			$s = ($this->serverGlobal['HTTPS'] ?? '') ? 's' : '';
			$url = "http$s:$url";
		}
		return rtrim($url, '/') . '/';
	}
}
