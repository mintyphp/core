<?php

namespace MintyPHP;

class Analyzer
{
	/** @var array<string> */
	public static array $tokens	= ['T_ECHO', 'T_PRINT', 'T_EXIT', 'T_STRING', 'T_EVAL', 'T_OPEN_TAG_WITH_ECHO'];
	/** @var array<string> */
	public static array $functions = ['echo', 'print', 'die', 'exit', 'var_dump', 'eval', '<?='];

	public static function execute(): void
	{
		self::check('action', Router::getTemplateAction());
		self::check('action', Router::getAction());
		self::check('view', Router::getView());
		self::check('view', Router::getTemplateView());
	}

	protected static function check(string $type, string $filename): void
	{
		if (!$filename) return;
		$tokens = token_get_all(file_get_contents($filename) ?: '');
		foreach ($tokens as $token) {
			if (is_array($token)) {
				if (in_array(token_name($token[0]), self::$tokens)) {
					if (in_array($token[1], self::$functions)) {
						trigger_error('MintyPHP ' . $type . ' "' . $filename . '" should not use "' . htmlentities($token[1]) . '" on line ' . $token[2] . '. Error raised ', E_USER_WARNING);
					}
				}
			}
		}
	}
}
