<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\Analyzer;
use MintyPHP\Core\Router;

class AnalyzerTest extends \PHPUnit\Framework\TestCase
{
    protected static string $path = '';
    /** @var array<string> */
    protected static array $pages = [];
    /** @var array<string> */
    protected static array $templates = [];
    /** @var array<string, string> */
    protected static array $fileContents = [];

    public static function setUpBeforeClass(): void
    {
        self::$path = sys_get_temp_dir() . '/mintyphp_analyzer_test';

        self::$pages = [
            'good/action.php',
            'good/view.phtml',
            'bad_echo/action.php',
            'bad_echo/view.phtml',
            'bad_print/action.php',
            'bad_exit/action.php',
            'bad_die/action.php',
            'bad_var_dump/action.php',
            'bad_eval/action.php',
            'bad_short_echo/view.phtml',
        ];
        self::$templates = [
            'good.php',
            'good.phtml',
            'bad_echo.php',
            'bad_print.phtml',
        ];

        // Define content for each file
        self::$fileContents = [
            'good/action.php' => '<?php $data = ["test" => "value"]; return $data;',
            'good/view.phtml' => '<div><?php $escaped = htmlentities($data["test"]); ?></div>',
            'bad_echo/action.php' => '<?php $data = "test"; echo $data;',
            'bad_echo/view.phtml' => '<div><?php echo $data; ?></div>',
            'bad_print/action.php' => '<?php print "Hello World";',
            'bad_exit/action.php' => '<?php exit("Error occurred");',
            'bad_die/action.php' => '<?php die("Fatal error");',
            'bad_var_dump/action.php' => '<?php $data = ["test"]; var_dump($data);',
            'bad_eval/action.php' => '<?php eval("echo \'test\';");',
            'bad_short_echo/view.phtml' => '<div><?= $data ?></div>',
            'good.php' => '<?php $config = ["key" => "value"];',
            'good.phtml' => '<html><body><?php $safe = htmlentities($config["key"]); ?></body></html>',
            'bad_echo.php' => '<?php echo "template action";',
            'bad_print.phtml' => '<?php print "template view";',
        ];        // Create page files
        foreach (self::$pages as $file) {
            $path = self::$path . '/pages/' . $file;
            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            $content = self::$fileContents[$file] ?? '<?php // empty file';
            file_put_contents($path, $content);
        }

        // Create template files
        foreach (self::$templates as $file) {
            $path = self::$path . '/templates/' . $file;
            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            $content = self::$fileContents[$file] ?? '<?php // empty template';
            file_put_contents($path, $content);
        }
    }

    protected function createRouter(string $actionFile, ?string $viewFile = null, ?string $templateActionFile = null, ?string $templateViewFile = null): Router
    {
        $router = $this->getMockBuilder(Router::class)
            ->disableOriginalConstructor()
            ->getMock();

        $action = $actionFile ? self::$path . '/pages/' . $actionFile : '';
        $view = $viewFile ? self::$path . '/pages/' . $viewFile : '';
        $templateAction = $templateActionFile ? self::$path . '/templates/' . $templateActionFile : '';
        $templateView = $templateViewFile ? self::$path . '/templates/' . $templateViewFile : '';

        $router->method('getAction')->willReturn($action);
        $router->method('getView')->willReturn($view);
        $router->method('getTemplateAction')->willReturn($templateAction);
        $router->method('getTemplateView')->willReturn($templateView);

        return $router;
    }
    protected function assertWarningTriggered(callable $callback, string $expectedMessagePattern): void
    {
        $warningTriggered = false;
        $warningMessage = '';

        set_error_handler(function ($errno, $errstr) use (&$warningTriggered, &$warningMessage) {
            if ($errno === E_USER_WARNING) {
                $warningTriggered = true;
                $warningMessage = $errstr;
            }
            return true; // Don't execute PHP internal error handler
        });

        $callback();

        restore_error_handler();

        $this->assertTrue($warningTriggered, 'Expected a warning to be triggered');
        $this->assertMatchesRegularExpression($expectedMessagePattern, $warningMessage);
    }

    public function testGoodFilesNoWarnings(): void
    {
        $router = $this->createRouter('good/action.php', 'good/view.phtml', 'good.php', 'good.phtml');
        $analyzer = new Analyzer($router);

        $warningTriggered = false;
        set_error_handler(function ($errno) use (&$warningTriggered) {
            if ($errno === E_USER_WARNING) {
                $warningTriggered = true;
            }
            return true;
        });

        $analyzer->execute();
        restore_error_handler();

        $this->assertFalse($warningTriggered, 'No warnings should be triggered for good files');
    }

    public function testBadEchoInAction(): void
    {
        $router = $this->createRouter('bad_echo/action.php');
        $analyzer = new Analyzer($router);

        $this->assertWarningTriggered(
            fn() => $analyzer->execute(),
            '/MintyPHP action.*should not use "echo"/'
        );
    }

    public function testBadEchoInView(): void
    {
        $router = $this->createRouter('bad_echo/action.php', 'bad_echo/view.phtml');
        $analyzer = new Analyzer($router);

        $this->assertWarningTriggered(
            fn() => $analyzer->execute(),
            '/should not use "echo"/'
        );
    }

    public function testBadPrintInAction(): void
    {
        $router = $this->createRouter('bad_print/action.php');
        $analyzer = new Analyzer($router);

        $this->assertWarningTriggered(
            fn() => $analyzer->execute(),
            '/MintyPHP action.*should not use "print"/'
        );
    }

    public function testBadExitInAction(): void
    {
        $router = $this->createRouter('bad_exit/action.php');
        $analyzer = new Analyzer($router);

        $this->assertWarningTriggered(
            fn() => $analyzer->execute(),
            '/MintyPHP action.*should not use "exit"/'
        );
    }

    public function testBadDieInAction(): void
    {
        $router = $this->createRouter('bad_die/action.php');
        $analyzer = new Analyzer($router);

        $this->assertWarningTriggered(
            fn() => $analyzer->execute(),
            '/MintyPHP action.*should not use "die"/'
        );
    }

    public function testBadVarDumpInAction(): void
    {
        $router = $this->createRouter('bad_var_dump/action.php');
        $analyzer = new Analyzer($router);

        $this->assertWarningTriggered(
            fn() => $analyzer->execute(),
            '/MintyPHP action.*should not use "var_dump"/'
        );
    }

    public function testBadEvalInAction(): void
    {
        $router = $this->createRouter('bad_eval/action.php');
        $analyzer = new Analyzer($router);

        $this->assertWarningTriggered(
            fn() => $analyzer->execute(),
            '/MintyPHP action.*should not use "eval"/'
        );
    }

    public function testBadShortEchoInView(): void
    {
        $router = $this->createRouter('good/action.php', 'bad_short_echo/view.phtml');
        $analyzer = new Analyzer($router);

        $this->assertWarningTriggered(
            fn() => $analyzer->execute(),
            '/MintyPHP view.*should not use/'
        );
    }

    public function testBadEchoInTemplateAction(): void
    {
        $router = $this->createRouter('good/action.php', 'good/view.phtml', 'bad_echo.php');
        $analyzer = new Analyzer($router);

        $this->assertWarningTriggered(
            fn() => $analyzer->execute(),
            '/MintyPHP action.*should not use "echo"/'
        );
    }

    public function testBadPrintInTemplateView(): void
    {
        $router = $this->createRouter('good/action.php', 'good/view.phtml', 'good.php', 'bad_print.phtml');
        $analyzer = new Analyzer($router);

        $this->assertWarningTriggered(
            fn() => $analyzer->execute(),
            '/MintyPHP view.*should not use "print"/'
        );
    }

    public function testNullFilesDoNotCauseErrors(): void
    {
        $router = $this->createRouter('good/action.php');
        $analyzer = new Analyzer($router);

        $warningTriggered = false;
        set_error_handler(function ($errno) use (&$warningTriggered) {
            if ($errno === E_USER_WARNING) {
                $warningTriggered = true;
            }
            return true;
        });

        $analyzer->execute();
        restore_error_handler();

        $this->assertFalse($warningTriggered, 'Null files should be handled gracefully');
    }
    public static function tearDownAfterClass(): void
    {
        // Cleanup temporary files and directories
        // Ensure removal is in the temp directory
        if (file_exists(self::$path) && strpos(self::$path, sys_get_temp_dir()) === 0) {
            system('rm -Rf ' . escapeshellarg(self::$path));
        }
    }
}
