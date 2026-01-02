<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\Router;
use MintyPHP\Core\Session;

/**
 * Note: This test suite creates temporary files and directories
 * in the system's temporary directory. It is important to ensure
 * that these files are cleaned up after the tests to avoid clutter.
 */
class RouterTest extends \PHPUnit\Framework\TestCase
{
    protected static string $path = '';
    protected static string $pageRoot = '';
    protected static string $templateRoot = '';
    /** @var array<string> */
    protected static array $pages = [];
    /** @var array<string> */
    protected static array $templates = [];

    public static function setUpBeforeClass(): void
    {
        self::$path = sys_get_temp_dir() . '/mintyphp_test';
        self::$pageRoot = self::$path . '/pages/';
        self::$templateRoot = self::$path . '/templates/';

        self::$pages = [
            'admin/posts/index().php',
            'admin/posts/index(admin).phtml',
            'admin/posts/view($id).php',
            'admin/posts/view(admin).phtml',
            'admin/index().php',
            'admin/index(admin).phtml',
            'admin/auth($code,$state).php',
            'admin/login().php',
            'admin/login(login).phtml',
            'error/forbidden(error).phtml',
            'error/method_not_allowed(error).phtml',
            'error/not_found(error).phtml',
            'rss().php',
            'home().php',
            'home(default).phtml',
            'index($slug).php',
            'index(default).phtml',
        ];
        self::$templates = [
            'admin.php',
            'admin.phtml',
            'default.phtml',
            'error.phtml',
            'login.phtml',
        ];

        foreach (self::$pages as $file) {
            $path = self::$pageRoot . $file;
            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            file_put_contents($path, '');
        }
        foreach (self::$templates as $file) {
            $path = self::$templateRoot . $file;
            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            file_put_contents($path, '');
        }
    }

    /**
     * Create a Router instance for testing
     * @param string $method The HTTP method
     * @param string $uri The request URI
     * @param array<string,string> $routes Custom routes
     * @return Router
     */
    protected function createRouter(string $method, string $uri, array $routes = []): Router
    {
        $serverGlobal = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'SCRIPT_NAME' => self::$path . '/web/index.php',
        ];
        $session = $this->createMock(Session::class);
        $session->expects($this->any())
            ->method('checkCsrfToken')
            ->willReturn(true);
        return new Router($session, '/', self::$pageRoot, self::$templateRoot, false, $routes, $serverGlobal);
    }

    public function testAdmin(): void
    {
        $router = $this->createRouter('GET', '/admin');
        $this->assertEquals(self::$templateRoot . 'admin.php', $router->getTemplateAction());
        $this->assertEquals(self::$templateRoot . 'admin.phtml', $router->getTemplateView());
        $this->assertEquals(self::$pageRoot . 'admin/index().php', $router->getAction());
        $this->assertEquals(self::$pageRoot . 'admin/index(admin).phtml', $router->getView());
    }

    public function testRootRoute(): void
    {
        $router = $this->createRouter('GET', '/', ['' => 'home']);
        $this->assertEquals(self::$pageRoot . 'home().php', $router->getAction());
        $this->assertEquals(self::$pageRoot . 'home(default).phtml', $router->getView());
    }

    public function testHomeRoute(): void
    {
        $router = $this->createRouter('GET', '/home/123', ['home/123' => 'slug']);
        $this->assertEquals(self::$pageRoot . 'index($slug).php', $router->getAction());
        $this->assertEquals(self::$pageRoot . 'index(default).phtml', $router->getView());
    }

    public function testTrailingSlashOnIndex(): void
    {
        $router = $this->createRouter('GET', '/admin/posts/');

        $this->assertEquals('http://localhost/admin/posts', $router->getRedirect());
    }

    public function testExplicitIndexRedirect(): void
    {
        $router = $this->createRouter('GET', '/admin/posts/index');

        $this->assertEquals('http://localhost/admin/posts', $router->getRedirect());
        $this->assertEquals(self::$templateRoot . 'admin.php', $router->getTemplateAction());
        $this->assertEquals(self::$templateRoot . 'admin.phtml', $router->getTemplateView());
        $this->assertEquals(self::$pageRoot . 'admin/posts/index().php', $router->getAction());
        $this->assertEquals(self::$pageRoot . 'admin/posts/index(admin).phtml', $router->getView());
    }

    public function testTrailingSlash(): void
    {
        $router = $this->createRouter('GET', '/admin/posts/view/12/');

        $this->assertEquals('http://localhost/admin/posts/view/12', $router->getRedirect());
    }

    public function testPageNotFoundOnIndex(): void
    {
        $router = $this->createRouter('GET', '/admin/posts/asdada');

        $this->assertEquals('http://localhost/admin/posts', $router->getRedirect());
    }

    public function testPageNotFoundOnNoIndex(): void
    {
        $router = $this->createRouter('GET', '/error/this-page-does-not-exist');

        $this->assertEquals(null, $router->getRedirect());
        $this->assertEquals(false, $router->getTemplateAction());
        $this->assertEquals(self::$templateRoot . 'error.phtml', $router->getTemplateView());
        $this->assertEquals(false, $router->getAction());
        $this->assertEquals(self::$pageRoot . 'error/not_found(error).phtml', $router->getView());
    }

    public function testRootParameters(): void
    {
        $router = $this->createRouter('GET', '/2014-some-blog-title');
        $this->assertEquals(['slug' => '2014-some-blog-title'], $router->getParameters());
    }

    public function testGetParameter(): void
    {
        $router = $this->createRouter('GET', '/admin/posts/view?id=12');
        $this->assertEquals(['id' => '12'], $router->getParameters());
    }

    public function testGetParameters(): void
    {
        $router = $this->createRouter('GET', '/admin/auth?code=23&state=12');
        $this->assertEquals(['code' => '23', 'state' => '12'], $router->getParameters());
    }

    public function testGetParameterWithWrongName(): void
    {
        $router = $this->createRouter('GET', '/admin/posts/view?idea=12');
        $this->assertEquals(['id' => ''], $router->getParameters());
        $this->assertEmpty($router->getRedirect());
    }

    public function testGetParameterHalf(): void
    {
        $router = $this->createRouter('GET', '/admin/auth/23?state=12');
        $this->assertEquals(['code' => '23', 'state' => '12'], $router->getParameters());
    }

    public function testGetParameterWrongOrder(): void
    {
        $router = $this->createRouter('GET', '/admin/auth?state=12&code=23');
        $this->assertEquals(['code' => '23', 'state' => '12'], $router->getParameters());
    }

    public function testGetParameterFirstOnly(): void
    {
        $router = $this->createRouter('GET', '/admin/auth?code=23');
        $this->assertEquals(['code' => '23', 'state' => null], $router->getParameters());
    }

    public function testGetParameterLastOnly(): void
    {
        $router = $this->createRouter('GET', '/admin/auth?state=12');
        $this->assertEquals(['code' => null, 'state' => '12'], $router->getParameters());
    }

    public function testGetParametersTooMany(): void
    {
        $router = $this->createRouter('GET', '/admin/posts/view?state=12&code=23&id=4');
        $this->assertEquals(['id' => 4], $router->getParameters());
        $this->assertEmpty($router->getRedirect());
    }

    public function testActionWithoutView(): void
    {
        $router = $this->createRouter('GET', '/rss');
        $this->assertEquals(false, $router->getTemplateAction());
        $this->assertEquals(false, $router->getTemplateView());
        $this->assertEquals(self::$pageRoot . 'rss().php', $router->getAction());
        $this->assertEquals(false, $router->getView());
    }

    public static function tearDownAfterClass(): void
    {
        // Cleanup temporary files and directories
        // Ensure removal is in the temp directory
        if (file_exists(self::$path) && str_starts_with(self::$path, sys_get_temp_dir())) {
            // Remove the temporary directory and its contents
            system('rm -Rf ' . escapeshellarg(self::$path));
        }
    }
}
