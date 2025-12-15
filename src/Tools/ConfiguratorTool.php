<?php

namespace MintyPHP\Tools;

class ConfiguratorTool
{
    public static function run(): void
    {
        (new self())->execute();
    }

    public function execute(): void
    {
        // Implementation of the configurator tool goes here
        echo "Configurator Tool executed.";
    }
}
