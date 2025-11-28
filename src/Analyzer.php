<?php

namespace MintyPHP;

use MintyPHP\Core\Analyzer as CoreAnalyzer;

/**
 * Static wrapper class for Analyzer operations using a singleton pattern.
 */
class Analyzer
{
    /**
     * The Analyzer instance
     * @var ?CoreAnalyzer
     */
    private static ?CoreAnalyzer $instance = null;

    /**
     * Get the Analyzer instance
     * @return CoreAnalyzer
     */
    public static function getInstance(): CoreAnalyzer
    {
        return self::$instance ??= new CoreAnalyzer(
            Router::getInstance()
        );
    }

    /**
     * Set the Analyzer instance to use
     * @param CoreAnalyzer $instance
     * @return void
     */
    public static function setInstance(CoreAnalyzer $instance): void
    {
        self::$instance = $instance;
    }

    /**
    	 * Execute the analyzer to check all action and view files.
    	 * 
    	 * Checks template actions, actions, views, and template views for
    	 * disallowed output functions.
    	 * 
    	 * @return void
    	 */
    public static function execute(): void
    {
        $instance = self::getInstance();
        $instance->execute();
    }
}
