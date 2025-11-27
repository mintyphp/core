<?php

namespace MintyPHP;

use MintyPHP\Core\Router as CoreRouter;

class Router
{
	public static string $baseUrl = '/';
	public static string $pageRoot = 'pages/';
	public static string $templateRoot = 'templates/';
	public static bool $executeRedirect = true;
	public static bool $initialized = false;

	/**
	 * The router instance
	 * @var ?CoreRouter
	 */
	private static ?CoreRouter $instance = null;

	/**
	 * Routes mapping
	 * @var array<string, string>
	 */
	private static array $routes = [];

	/**
	 * Get the router instance
	 * @return CoreRouter
	 */
	public static function getInstance(): CoreRouter
	{
		if (!self::$initialized || self::$instance === null) {
			self::$instance = new CoreRouter(
				self::$baseUrl,
				self::$pageRoot,
				self::$templateRoot,
				self::$executeRedirect,
				$_SERVER,
				self::$routes,
			);
			self::$initialized = true;
		}
		return self::$instance;
	}

	/**
	 * Set the router instance to use
	 * @param CoreRouter $router
	 * @return void
	 */
	public static function setInstance(CoreRouter $router): void
	{
		self::$instance = $router;
	}

	/**
	 * Add a route mapping
	 * @param string $sourcePath
	 * @param string $destinationPath
	 * @return void
	 */
	public static function addRoute(string $sourcePath, string $destinationPath): void
	{
		if (self::$initialized && self::$instance !== null) {
			// Router already initialized, add route directly
			self::$instance->addRoute($sourcePath, $destinationPath);
		} else {
			// Store route for later when router is initialized
			self::$routes[$destinationPath] = $sourcePath;
		}
	}

	/**
	 * Redirect to a URL
	 * @param string $url
	 * @param bool $permanent
	 * @return void
	 */
	public static function redirect(string $url, bool $permanent = false): void
	{
		$router = self::getInstance();
		$router->redirect($url, $permanent);
	}

	/**
	 * Output JSON and terminate
	 * @param mixed $object
	 * @return void
	 */
	public static function json(mixed $object): void
	{
		$router = self::getInstance();
		$router->json($object);
	}

	/**
	 * Download data as a file
	 * @param string $filename
	 * @param string $data
	 * @return void
	 */
	public static function download(string $filename, string $data): void
	{
		$router = self::getInstance();
		$router->download($filename, $data);
	}

	/**
	 * Download a file from the filesystem
	 * @param string $filename
	 * @param string $filepath
	 * @return void
	 */
	public static function file(string $filename, string $filepath): void
	{
		$router = self::getInstance();
		$router->file($filename, $filepath);
	}

	/**
	 * Get the current URL
	 * @return string
	 */
	public static function getUrl(): string
	{
		$router = self::getInstance();
		return $router->getUrl();
	}

	/**
	 * Get the canonical URL
	 * @return string
	 */
	public static function getCanonical(): string
	{
		$router = self::getInstance();
		return $router->getCanonical();
	}

	/**
	 * Get the request URI
	 * @return string
	 */
	public static function getRequest(): string
	{
		$router = self::getInstance();
		return $router->getRequest();
	}

	/**
	 * Get the action file path
	 * @return string
	 */
	public static function getAction(): string
	{
		$router = self::getInstance();
		return $router->getAction();
	}

	/**
	 * Get the redirect URL if set
	 * @return ?string
	 */
	public static function getRedirect(): ?string
	{
		$router = self::getInstance();
		return $router->getRedirect();
	}

	/**
	 * Get the view file path
	 * @return string
	 */
	public static function getView(): string
	{
		$router = self::getInstance();
		return $router->getView();
	}

	/**
	 * Get the template view file path
	 * @return string
	 */
	public static function getTemplateView(): string
	{
		$router = self::getInstance();
		return $router->getTemplateView();
	}

	/**
	 * Get the template action file path
	 * @return string
	 */
	public static function getTemplateAction(): string
	{
		$router = self::getInstance();
		return $router->getTemplateAction();
	}

	/**
	 * Get parameters extracted from the URL
	 * @return array<string, string|null>
	 */
	public static function getParameters(): array
	{
		$router = self::getInstance();
		return $router->getParameters();
	}

	/**
	 * Get the base URL
	 * @return string
	 */
	public static function getBaseUrl(): string
	{
		$router = self::getInstance();
		return $router->getBaseUrl();
	}
}
