<?php

namespace MintyPHP;

use MintyPHP\Core\Template as CoreTemplate;
use MintyPHP\Core\TemplateString;

/**
 * Static wrapper class for Template operations using a singleton pattern.
 */
class Template
{
	/**
	 * The template instance
	 * @var ?CoreTemplate
	 */
	private static ?CoreTemplate $instance = null;

	/**
	 * Get the template instance
	 * @return CoreTemplate
	 */
	public static function getInstance(): CoreTemplate
	{
		return self::$instance ??= new CoreTemplate();
	}

	/**
	 * Set the template instance to use
	 * @param CoreTemplate $template
	 * @return void
	 */
	public static function setInstance(CoreTemplate $template): void
	{
		self::$instance = $template;
	}

	/**
	 * Escapes a string based on the specified escape type.
	 * 
	 * @param string $escape The escape type (e.g., 'html').
	 * @param string $string The string to escape.
	 * @return string The escaped string.
	 */
	public static function escape($escape, $string)
	{
		$template = self::getInstance();
		return $template->escape($escape, $string);
	}

	/**
	 * Renders a template with the provided data and functions.
	 * 
	 * @param string $template The template string to render.
	 * @param array $data The data to use in the template.
	 * @param array $functions Custom functions to use in the template.
	 * @param string $escape The escape type to use (default: 'html').
	 * @return string The rendered template as a string.
	 */
	public static function render($template, $data, $functions = [], $escape = 'html'): string
	{
		$instance = self::getInstance();
		return $instance->render($template, $data, $functions, $escape);
	}
}
