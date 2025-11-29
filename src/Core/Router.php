<?php

namespace MintyPHP\Core;

use MintyPHP\Error\RouterError;

/**
 * Router class for handling URL routing in MintyPHP
 * 
 * Maps incoming requests to views, actions, and templates based on defined routes
 * and file structure. Supports redirection, JSON responses, and file downloads.
 */
class Router
{

	/**
	 * Static configuration parameters
	 */
	public static string $__baseUrl = '/';
	public static string $__pageRoot = 'pages/';
	public static string $__templateRoot = 'templates/';
	public static bool $__executeRedirect = true;
	/** @var array<string, string> */
	public static array $__serverGlobal = [];
	/** @var array<string, string> */
	public static array $__routes = [];

	/**
	 * Actual configuration parameters
	 */
	private readonly string $baseUrl;
	private readonly string $pageRoot;
	private readonly string $templateRoot;
	private readonly bool $executeRedirect;
	/** @var array<string, string> */
	private readonly array $serverGlobal;
	/** @var array<string, string> */
	private readonly array $routes;

	/**
	 * Session instance for managing user sessions
	 */
	private readonly Session $session;

	/**
	 * Debugger instance for logging routing operations
	 */
	private ?Debugger $debugger;

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

	/**
	 * Constructor for the Router class
	 * @param Session $session
	 * @param string $baseUrl
	 * @param string $pageRoot
	 * @param string $templateRoot
	 * @param bool $executeRedirect
	 * @param array<string, string> $serverGlobal
	 * @param array<string, string> $routes
	 */
	public function __construct(
		Session $session,
		string $baseUrl,
		string $pageRoot,
		string $templateRoot,
		bool $executeRedirect,
		array $serverGlobal,
		array $routes = [],
		?Debugger $debugger = null
	) {
		$this->session = $session;
		$this->baseUrl = $baseUrl;
		$this->pageRoot = $pageRoot;
		$this->templateRoot = $templateRoot;
		$this->executeRedirect = $executeRedirect;
		$this->serverGlobal = $serverGlobal;
		$this->routes = $routes;
		$this->debugger = $debugger;
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
	 * @throws \RuntimeException
	 */
	private function error(string $message): void
	{
		if ($this->debugger !== null) {
			$this->debugger->setStatus(500);
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
		if ($this->debugger !== null) {
			$this->debugger->setRedirect($url);
			$this->debugger->setStatus($status);
			$this->debugger->end('redirect');
		}
		if ($this->executeRedirect) {
			header("Location: $url", true, $status);
			die();
		} else {
			$this->redirect = $url;
		}
	}

	/**
	 * Output JSON response and terminate execution
	 * @param mixed $object The object to encode as JSON
	 * @return void
	 */
	public function json(mixed $object): void
	{
		if ($this->debugger !== null) {
			$this->debugger->end('json');
		}
		header('Content-Type: application/json');
		die(json_encode($object));
	}

	/**
	 * Initiate file download with provided data and terminate execution
	 * @param string $filename The name of the file to download
	 * @param string $data The file content data
	 * @return void
	 */
	public function download(string $filename, string $data): void
	{
		if ($this->debugger !== null) {
			$this->debugger->end('download');
		}
		header('Content-Type: application/octet-stream');
		header("Content-Transfer-Encoding: Binary");
		header("Content-disposition: attachment; filename=\"" . $filename . "\"");
		header('Content-Length: ' . strlen($data));
		die($data);
	}

	/**
	 * Initiate file download from filesystem and terminate execution
	 * @param string $filename The name for the downloaded file
	 * @param string $filepath The path to the file on the filesystem
	 * @return void
	 */
	public function file(string $filename, string $filepath): void
	{
		if ($this->debugger !== null) {
			$this->debugger->end('download');
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
	 * Process a matched file and extract routing information
	 * Sets the view, action, template, and parameters for the current route
	 * @param string $templateRoot The root directory for templates
	 * @param string $root The root directory for pages
	 * @param string $dir The directory path within the root
	 * @param string $path The matched file path
	 * @param array<int, string> $parameters URL path parameters
	 * @param array<int|string, mixed> $getParameters Query string parameters
	 * @return string|false Redirect URL if needed, false otherwise
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
	 * Find matching files for a pattern, checking .phtml first then .php
	 * @param string $root The root directory to search in
	 * @param string $dir The subdirectory within root
	 * @param string $pattern The file pattern to match (without extension)
	 * @return array<string> Array of matching file paths
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
	 * Route a directory by finding matching files and handling errors
	 * Returns HTTP status code and optional redirect URL
	 * @param bool $csrfOk Whether CSRF validation passed
	 * @param string $templateRoot The root directory for templates
	 * @param string $root The root directory for pages
	 * @param string $dir The directory path to route
	 * @param array<int, string> $parameters URL path parameters
	 * @param array<int|string, mixed> $getParameters Query string parameters
	 * @return array{0: int, 1: string|false} Tuple of [HTTP status code, redirect URL or false]
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
		$status = 0;

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
		if ($this->debugger !== null) {
			$method = $this->method;
			$request = '/' . $this->original;
			$url = '/' . $this->url;
			$viewFile = $this->view;
			$actionFile = $this->action;
			$templateFile = $this->template;
			$this->debugger->setRoute($method, $csrfOk, $request, $url, $dir, $viewFile, $actionFile, $templateFile, $this->parameters, $_GET,  $_POST);
			$this->debugger->setStatus($status);
		}
		if ($redirect) $this->redirect($redirect);
	}

	/**
	 * Parse and normalize the request URI by removing script name and base URL
	 * Also stores the original normalized request for later reference
	 * @return string The normalized request path
	 */
	private function parseRequest(): string
	{
		$request = $this->removePrefix($this->request, $this->script ?: '');
		$request = $this->removePrefix($request, $this->baseUrl);
		if ($this->original === '') $this->original = $request;
		return $request;
	}

	/**
	 * Check CSRF protection for non-safe HTTP methods
	 * Safe methods (GET, OPTIONS) always pass. Other methods require AJAX header or valid CSRF token
	 * @return bool True if CSRF check passes, false otherwise
	 */
	private function checkCsrfProtection(): bool
	{
		$isSafeMethod = in_array($this->method, ['GET', 'OPTIONS']);
		$isAjax = strtolower($this->serverGlobal['HTTP_X_REQUESTED_WITH'] ?? '') == 'xmlhttprequest';
		return $isSafeMethod ?: ($isAjax || $this->session->checkCsrfToken());
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

	/**
	 * Get the matched URL path (view or action name with directory)
	 * @return string The URL path
	 */
	public function getUrl(): string
	{
		return $this->url;
	}

	/**
	 * Get the canonical URL with parameters appended
	 * Removes trailing 'index' from the URL if present
	 * @return string The canonical URL path
	 */
	public function getCanonical(): string
	{
		$canonical = $this->url;
		if (substr($canonical, -6) == '/' . 'index' || $canonical == 'index') {
			$canonical = substr($canonical, 0, -5);
		}
		return $canonical . implode('/', $this->parameters);
	}

	/**
	 * Get the current request URI
	 * @return string The request URI
	 */
	public function getRequest(): string
	{
		return $this->request;
	}

	/**
	 * Get the matched action file path
	 * @return string The full path to the action PHP file, or empty string if none
	 */
	public function getAction(): string
	{
		return $this->action;
	}

	/**
	 * Get the redirect URL if one was set during routing
	 * @return string|null The redirect URL, or null if no redirect
	 */
	public function getRedirect(): ?string
	{
		return (isset($this->redirect) && $this->redirect !== '') ? $this->redirect : null;
	}

	/**
	 * Get the matched view file path
	 * @return string The full path to the view PHTML file, or empty string if none
	 */
	public function getView(): string
	{
		return $this->view;
	}

	/**
	 * Get the template view file path if it exists
	 * @return string The full path to the template PHTML file, or empty string if none
	 */
	public function getTemplateView(): string
	{
		if (!$this->template) return '';
		$filename = $this->template . '.phtml';
		return file_exists($filename) ? $filename : '';
	}

	/**
	 * Get the template action file path if it exists
	 * @return string The full path to the template PHP file, or empty string if none
	 */
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

	/**
	 * Get the base URL with protocol and host
	 * Constructs full URL from base path, adding protocol and host if needed
	 * @return string The complete base URL with trailing slash
	 */
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
