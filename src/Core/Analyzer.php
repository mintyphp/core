<?php

namespace MintyPHP\Core;

use MintyPHP\Core\Router;

class Analyzer
{
	/** @var array<string> */
	const TOKENS = ['T_ECHO', 'T_PRINT', 'T_EXIT', 'T_STRING', 'T_EVAL', 'T_OPEN_TAG_WITH_ECHO'];
	/** @var array<string> */
	const FUNCTIONS = ['echo', 'print', 'die', 'exit', 'var_dump', 'eval', '<?='];

	private Router $router;

	public function __construct(Router $router)
	{
		$this->router = $router;
	}

	public function execute(): void
	{
		$this->check('action', $this->router->getTemplateAction());
		$this->check('action', $this->router->getAction());
		$this->check('view', $this->router->getView());
		$this->check('view', $this->router->getTemplateView());
	}

	protected function check(string $type, ?string $filename): void
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
