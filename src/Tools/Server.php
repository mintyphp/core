<?php

namespace MintyPHP\Tools;

use MintyPHP\Debugger;

class Server
{
    // Mapping of paths to their respective handler classes
    const array MAPPING = [
        '/debugger.php' => DebuggerTool::class,
        '/adminer.php' => AdminerTool::class,
    ];

    private string $documentRoot;
    private string $scriptName;

    public function __construct(string $documentRoot, string $scriptName)
    {
        $this->documentRoot = $documentRoot;
        $this->scriptName = $scriptName;
    }

    public function run(): bool
    {
        $dir = $this->documentRoot;
        $file = realpath($dir . $this->scriptName);
        if (file_exists($file) && (strpos($file, $dir) === 0)) {
            return false;
        }

        if (Debugger::$enabled && array_key_exists($this->scriptName, self::MAPPING)) {
            Debugger::$enabled = false;
            $handlerClass = self::MAPPING[$this->scriptName];
            $handlerClass::run();
        } else {
            $_SERVER['SCRIPT_NAME'] = '/index.php';
            chdir('web');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            require 'index.php';
        }
        return true;
    }
}

if (isset($_SERVER['DOCUMENT_ROOT']) && isset($_SERVER['SCRIPT_NAME'])) {
    require 'vendor/autoload.php';
    require 'config/config.php';
    return (new Server($_SERVER['DOCUMENT_ROOT'], $_SERVER['SCRIPT_NAME']))->run();
}
