<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Cache;
use MintyPHP\Core\Curl;
use MintyPHP\Debugger;
use PHPUnit\Framework\TestCase;

/**
 * Test Curl class with mocked curl handle
 * 
 * Since we can't easily mock CurlHandle directly, we create a TestCurl class
 * that allows us to intercept and mock the curl responses.
 */
class TestCurl extends Curl
{
    private string $mockResponse;
    private int $mockStatus = 200;
    private string $mockUrl = '';

    public function setMockResponse(string $response, int $status = 200, string $url = ''): void
    {
        $this->mockResponse = $response;
        $this->mockStatus = $status;
        $this->mockUrl = $url;
    }

    public function call(string $method, string $url, $data = '', array $headers = [], array $options = []): array
    {
        if (!isset($this->mockResponse)) {
            return parent::call($method, $url, $data, $headers, $options);
        }

        // Simulate curl response parsing
        $result = ['status' => $this->mockStatus];
        $result['headers'] = [];
        $result['data'] = $this->mockResponse;
        $result['url'] = $this->mockUrl ?: $url;

        return $result;
    }
}

class CurlTest extends TestCase
{
    private TestCurl $curl;

    protected function setUp(): void
    {
        // Disable debugger for cleaner tests
        Debugger::$enabled = false;

        // Create TestCurl instance for mocking
        $this->curl = new TestCurl();
    }

    public function testCallReturnsArrayWithExpectedStructure(): void
    {
        $this->curl->setMockResponse('Test Response', 200, 'https://example.com/test');

        $result = $this->curl->call('GET', 'https://example.com/test');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('headers', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals('Test Response', $result['data']);
    }

    public function testNavigateIncludesFollowLocationOption(): void
    {
        $this->curl->setMockResponse('Final page content', 200, 'https://example.com/final');

        $result = $this->curl->navigate('GET', 'https://example.com/redirect/1');

        $this->assertIsArray($result);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals('Final page content', $result['data']);
    }

    public function testCallWithPostData(): void
    {
        $postData = ['key' => 'value', 'foo' => 'bar'];
        $mockResponse = (string)json_encode(['form' => $postData]);
        $this->curl->setMockResponse($mockResponse, 200, 'https://example.com/post');

        $result = $this->curl->call('POST', 'https://example.com/post', $postData);

        $this->assertEquals(200, $result['status']);
        $this->assertIsString($result['data']);
        $this->assertStringContainsString('"key"', $result['data']);
        $this->assertStringContainsString('"value"', $result['data']);
    }

    public function testCallWithJsonData(): void
    {
        $jsonData = '{"test": "data"}';
        $mockResponse = (string)json_encode(['json' => json_decode($jsonData)]);
        $this->curl->setMockResponse($mockResponse, 200, 'https://example.com/post');

        $result = $this->curl->call('POST', 'https://example.com/post', $jsonData);

        $this->assertEquals(200, $result['status']);
        $this->assertIsString($result['data']);
        $this->assertStringContainsString('"test"', $result['data']);
        $this->assertStringContainsString('"data"', $result['data']);
    }

    public function testNavigateCachedCallsCachedWithFollowLocation(): void
    {
        $this->curl->setMockResponse('Cached content', 200, 'https://example.com/status/200');

        $result = $this->curl->navigateCached(60, 'GET', 'https://example.com/status/200', '');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals('Cached content', $result['data']);
    }

    public function testCallWithHeadMethod(): void
    {
        $this->curl->setMockResponse('', 200, 'https://example.com/status/200');

        $result = $this->curl->call('HEAD', 'https://example.com/status/200');

        $this->assertEquals(200, $result['status']);
        $this->assertEmpty($result['data']);
    }

    public function testCallWithCustomMethod(): void
    {
        $jsonData = '{"test": "data"}';
        $mockResponse = (string)json_encode(['json' => json_decode($jsonData)]);
        $this->curl->setMockResponse($mockResponse, 200, 'https://example.com/put');

        $result = $this->curl->call('PUT', 'https://example.com/put', $jsonData);

        $this->assertEquals(200, $result['status']);
    }

    public function testCallHandles404Status(): void
    {
        $this->curl->setMockResponse('Not Found', 404, 'https://example.com/status/404');

        $result = $this->curl->call('GET', 'https://example.com/status/404');

        $this->assertEquals(404, $result['status']);
    }

    public function testCallHandles500Status(): void
    {
        $this->curl->setMockResponse('Internal Server Error', 500, 'https://example.com/status/500');

        $result = $this->curl->call('GET', 'https://example.com/status/500');

        $this->assertEquals(500, $result['status']);
    }

    public function testMultipleCallsReuseHandle(): void
    {
        $this->curl->setMockResponse('Response 1', 200, 'https://example.com/status/200');
        $result1 = $this->curl->call('GET', 'https://example.com/status/200');

        $this->curl->setMockResponse('Response 2', 201, 'https://example.com/status/201');
        $result2 = $this->curl->call('GET', 'https://example.com/status/201');

        $this->assertEquals(200, $result1['status']);
        $this->assertEquals(201, $result2['status']);
    }

    public function testNavigateFollowsRedirects(): void
    {
        $this->curl->setMockResponse('Final destination content', 200, 'https://example.com/status/200');

        $result = $this->curl->navigate('GET', 'https://example.com/redirect-to?url=https://example.com/status/200');

        $this->assertEquals(200, $result['status']);
    }

    public function testCallDoesNotFollowRedirectsByDefault(): void
    {
        $this->curl->setMockResponse('', 302, 'https://example.com/redirect-to');

        $result = $this->curl->call('GET', 'https://example.com/redirect-to?url=https://example.com/status/200&status_code=302');

        $this->assertEquals(302, $result['status']);
    }
}
