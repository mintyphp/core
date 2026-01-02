<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\Curl;
use MintyPHP\Debugger;
use PHPUnit\Framework\TestCase;

/**
 * Note: This test suite starts a PHP built-in server to handle HTTP requests.
 * Ensure that the specified port (8765) is available before running the tests.
 */
class CurlTest extends TestCase
{
    private static ?int $serverPid = null;
    private static string $baseUrl = 'http://localhost:8765';
    private Curl $curl;

    public static function setUpBeforeClass(): void
    {
        // Start PHP built-in server
        $documentRoot = __DIR__ . '/../TestServer';
        $command = sprintf(
            'php -S localhost:8765 -t %s > /dev/null 2>&1 & echo $!',
            escapeshellarg($documentRoot)
        );
        $output = shell_exec($command);
        self::$serverPid = $output ? (int)trim($output) : 0;

        // Wait for server to be ready
        $maxWait = 3;
        $start = time();
        while (time() - $start < $maxWait) {
            $socket = @fsockopen('localhost', 8765, $errno, $errstr, 0.1);
            if ($socket !== false) {
                fclose($socket);
                usleep(100000); // Extra 100ms to ensure server is fully ready
                break;
            }
            usleep(100000);
        }
    }
    public static function tearDownAfterClass(): void
    {
        // Stop the server
        if (self::$serverPid) {
            exec('kill ' . self::$serverPid);
        }
    }

    protected function setUp(): void
    {
        Debugger::$enabled = false;
        $this->curl = new Curl();
    }

    public function testCallReturnsArrayWithExpectedStructure(): void
    {
        $result = $this->curl->call('GET', self::$baseUrl . '/test.php');

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('headers', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals('Test Response', $result['data']);
    }

    public function testNavigateIncludesFollowLocationOption(): void
    {
        $result = $this->curl->navigate('GET', self::$baseUrl . '/redirect.php?target=final.php');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals('Final page content', $result['data']);
    }

    public function testCallWithPostData(): void
    {
        $postData = ['key' => 'value', 'foo' => 'bar'];

        $result = $this->curl->call('POST', self::$baseUrl . '/post.php', $postData);

        $this->assertEquals(200, $result['status']);
        $decoded = json_decode($result['data'], true);
        $this->assertIsArray($decoded);
        $this->assertEquals('value', $decoded['key']);
        $this->assertEquals('bar', $decoded['foo']);
    }

    public function testCallWithJsonData(): void
    {
        $jsonData = '{"test": "data"}';

        $result = $this->curl->call('POST', self::$baseUrl . '/json.php', $jsonData);

        $this->assertEquals(200, $result['status']);
        $decoded = json_decode($result['data'], true);
        $this->assertIsArray($decoded);
        $this->assertEquals('data', $decoded['test']);
    }

    public function testNavigateCachedCallsCachedWithFollowLocation(): void
    {
        $result = $this->curl->navigateCached(60, 'GET', self::$baseUrl . '/test.php', '');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals('Test Response', $result['data']);
    }

    public function testCallWithHeadMethod(): void
    {
        $result = $this->curl->call('HEAD', self::$baseUrl . '/test.php');

        $this->assertEquals(200, $result['status']);
        $this->assertEmpty($result['data']);
    }

    public function testCallWithCustomMethod(): void
    {
        $jsonData = '{"test": "data"}';

        $result = $this->curl->call('PUT', self::$baseUrl . '/method.php', $jsonData);

        $this->assertEquals(200, $result['status']);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($result['data'], true);
        $this->assertEquals('PUT', $decoded['method']);
    }

    public function testCallHandles404Status(): void
    {
        $result = $this->curl->call('GET', self::$baseUrl . '/nonexistent.php');

        $this->assertEquals(404, $result['status']);
    }

    public function testCallHandles500Status(): void
    {
        $result = $this->curl->call('GET', self::$baseUrl . '/error.php');

        $this->assertEquals(500, $result['status']);
    }

    public function testMultipleCallsReuseHandle(): void
    {
        $result1 = $this->curl->call('GET', self::$baseUrl . '/status.php?code=200');
        $result2 = $this->curl->call('GET', self::$baseUrl . '/status.php?code=201');

        $this->assertEquals(200, $result1['status']);
        $this->assertEquals(201, $result2['status']);
    }

    public function testNavigateFollowsRedirects(): void
    {
        $result = $this->curl->navigate('GET', self::$baseUrl . '/redirect.php?target=final.php');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals('Final page content', $result['data']);
    }

    public function testCallDoesNotFollowRedirectsByDefault(): void
    {
        $result = $this->curl->call('GET', self::$baseUrl . '/redirect.php?target=final.php');

        $this->assertEquals(302, $result['status']);
    }
}
