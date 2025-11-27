<?php

namespace MintyPHP;

use MintyPHP\Core\Analyzer as CoreAnalyzer;

class Analyzer
{
	/**
	 * The analyzer instance
	 * @var ?CoreAnalyzer
	 */
	private static ?CoreAnalyzer $instance = null;

	/**
	 * Get the analyzer instance
	 * @return CoreAnalyzer
	 */
	public static function getInstance(): CoreAnalyzer
	{
		return self::$instance ??= new CoreAnalyzer(Router::getInstance());
	}

	/**
	 * Set the analyzer instance to use
	 * @param CoreAnalyzer $analyzer
	 * @return void
	 */
	public static function setInstance(CoreAnalyzer $analyzer): void
	{
		self::$instance = $analyzer;
	}

	/**
	 * Execute the analyzer
	 * @return void
	 */
	public static function execute(): void
	{
		$analyzer = self::getInstance();
		$analyzer->execute();
	}
}
