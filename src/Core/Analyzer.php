<?php

namespace MintyPHP\Core;

use MintyPHP\Core\Router;

/**
 * Analyzer class for checking PHP files for disallowed output functions.
 * 
 * This class analyzes action and view files to ensure they don't contain
 * direct output functions like echo, print, var_dump, etc., which could
 * break the MVC pattern by mixing logic with output.
 */
class Analyzer
{
	/**
	 * Token types to check for in PHP files.
	 * 
	 * @var array<string>
	 */
	public const TOKENS = ['T_ECHO', 'T_PRINT', 'T_EXIT', 'T_STRING', 'T_EVAL', 'T_OPEN_TAG_WITH_ECHO'];

	/**
	 * Function names that are disallowed in actions and views.
	 * 
	 * @var array<string>
	 */
	public const FUNCTIONS = ['echo', 'print', 'die', 'exit', 'var_dump', 'eval', '<?='];

	/**
	 * Constructor.
	 * 
	 * @param Router $router The router instance to analyze files from.
	 */
	public function __construct(
        /**
         * Router instance for accessing file paths.
         */
        private Router $router
    )
    {
    }

	/**
	 * Execute the analyzer to check all action and view files.
	 * 
	 * Checks template actions, actions, views, and template views for
	 * disallowed output functions.
	 * 
	 * @return void
	 */
	public function execute(): void
	{
		$this->check('action', $this->router->getTemplateAction());
		$this->check('action', $this->router->getAction());
		$this->check('view', $this->router->getView());
		$this->check('view', $this->router->getTemplateView());
	}

	/**
	 * Check a specific file for disallowed output functions.
	 * 
	 * Parses the PHP file and triggers a warning if any disallowed functions
	 * are found (echo, print, var_dump, etc.).
	 * 
	 * @param string $type The type of file being checked ('action' or 'view').
	 * @param string|null $filename The path to the file to check, or null to skip.
	 * @return void
	 */
	private function check(string $type, ?string $filename): void
	{
		if (!$filename) return;
		$tokens = token_get_all(file_get_contents($filename) ?: '');
		foreach ($tokens as $token) {
			if (is_array($token)) {
				if (in_array(token_name($token[0]), self::TOKENS)) {
					if (in_array($token[1], self::FUNCTIONS)) {
						trigger_error('MintyPHP ' . $type . ' "' . $filename . '" should not use "' . htmlentities($token[1]) . '" on line ' . $token[2] . '. Error raised ', E_USER_WARNING);
					}
				}
			}
		}
	}
}
