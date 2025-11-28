<?php

namespace MintyPHP;

use MintyPHP\Core\Template as CoreTemplate;

/**
 * Static wrapper class for Template operations using a singleton pattern.
 */
class Template
{
    public static string $escape = 'html';

    /**
     * The Template instance
     * @var ?CoreTemplate
     */
    private static ?CoreTemplate $instance = null;

    /**
     * Get the Template instance
     * @return CoreTemplate
     */
    public static function getInstance(): CoreTemplate
    {
        return self::$instance ??= new CoreTemplate(
            self::$escape
        );
    }

    /**
     * Set the Template instance to use
     * @param CoreTemplate $instance
     * @return void
     */
    public static function setInstance(CoreTemplate $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Renders a template string with the provided data and custom functions.
     *
     * @param string $template The template string containing placeholders like {{variable}}.
     * @param array<string,mixed> $data Associative array of data to use in the template.
     * @param array<string,callable> $functions Associative array of custom functions available in the template.
     * @return string The rendered template string.
     */
    public static function render(string $template, array $data, array $functions = []): string
    {
        $instance = self::getInstance();
        return $instance->render($template, $data, $functions);
    }
}
