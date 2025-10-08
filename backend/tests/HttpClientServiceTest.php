<?php

namespace Prism\Backend\Tests;

use PHPUnit\Framework\TestCase;
use Prism\Backend\Services\HttpClientService;
use Monolog\Logger;
use Monolog\Handler\TestHandler;

class HttpClientServiceTest extends TestCase
{
    private HttpClientService $httpClient;
    private TestHandler $logHandler;

    protected function setUp(): void
    {
        $config = [
            'timeout' => 10,
            'connect_timeout' => 5,
            'verify_ssl' => false, // For testing
            'max_retries' => 2,
            'cache_ttl' => 60,
        ];

        $logger = new Logger('test');
        $this->logHandler = new TestHandler();
        $logger->pushHandler($this->logHandler);

        $this->httpClient = new HttpClientService($config, $logger);
    }

    protected function tearDown(): void
    {
        $this->httpClient->close();
    }

    public function testSuccessfulGetRequest(): void
    {
        $response = $this->httpClient->get('https://httpbin.org/get');
        
        $this->assertTrue($response['success']);
        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('body', $response);
        $this->assertArrayHasKey('headers', $response);
    }

    public function testPostRequest(): void
    {
        $data = ['test' => 'data', 'key' => 'value'];
        $response = $this->httpClient->post('https://httpbin.org/post', $data);
        
        $this->assertTrue($response['success']);
        $this->assertEquals(200, $response['status']);
        
        $body = json_decode($response['body'], true);
        $this->assertEquals($data, $body['json']);
    }

    public function testHeadRequest(): void
    {
        $response = $this->httpClient->head('https://httpbin.org/get');
        
        $this->assertTrue($response['success']);
        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('headers', $response);
    }

    public function testDownloadRequest(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'prism_test_');
        $response = $this->httpClient->download('https://httpbin.org/bytes/1024', $tempFile);
        
        $this->assertTrue($response['success']);
        $this->assertEquals(200, $response['status']);
        $this->assertFileExists($tempFile);
        $this->assertEquals(1024, filesize($tempFile));
        
        unlink($tempFile);
    }

    public function testRetryMechanism(): void
    {
        // Test with a URL that might fail initially
        $response = $this->httpClient->get('https://httpbin.org/status/500');
        
        // Should retry and eventually fail
        $this->assertFalse($response['success']);
        $this->assertEquals(500, $response['status']);
    }

    public function testRequestHistory(): void
    {
        $this->httpClient->get('https://httpbin.org/get');
        $this->httpClient->get('https://httpbin.org/user-agent');
        
        $history = $this->httpClient->getRequestHistory();
        $this->assertCount(2, $history);
        $this->assertStringContainsString('httpbin.org/get', $history[0]['url']);
        $this->assertStringContainsString('httpbin.org/user-agent', $history[1]['url']);
    }

    public function testCacheFunctionality(): void
    {
        // First request
        $response1 = $this->httpClient->get('https://httpbin.org/cache/60');
        $this->assertTrue($response1['success']);
        
        // Second request should be cached
        $response2 = $this->httpClient->get('https://httpbin.org/cache/60');
        $this->assertTrue($response2['success']);
        
        $stats = $this->httpClient->getCacheStats();
        $this->assertArrayHasKey('cache_size', $stats);
    }

    public function testCustomHeaders(): void
    {
        $this->httpClient->setHeaders(['X-Test-Header' => 'test-value']);
        $response = $this->httpClient->get('https://httpbin.org/headers');
        
        $this->assertTrue($response['success']);
        $body = json_decode($response['body'], true);
        $this->assertEquals('test-value', $body['headers']['X-Test-Header']);
    }

    public function testTimeoutConfiguration(): void
    {
        $this->httpClient->setTimeout(1); // 1 second timeout
        $response = $this->httpClient->get('https://httpbin.org/delay/2');
        
        // Should timeout
        $this->assertFalse($response['success']);
    }

    public function testProxyConfiguration(): void
    {
        // This test would require a proxy server
        // For now, just test that the method doesn't throw an exception
        $this->httpClient->setProxy('http://localhost:8080');
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testCacheClearing(): void
    {
        $this->httpClient->get('https://httpbin.org/get');
        $stats1 = $this->httpClient->getCacheStats();
        
        $this->httpClient->clearCache();
        $stats2 = $this->httpClient->getCacheStats();
        
        $this->assertGreaterThanOrEqual(0, $stats1['cache_size']);
        $this->assertEquals(0, $stats2['cache_size']);
    }

    public function testLogging(): void
    {
        $this->httpClient->get('https://httpbin.org/get');
        
        $records = $this->logHandler->getRecords();
        $this->assertNotEmpty($records);
        
        $hasRequestLog = false;
        $hasResponseLog = false;
        
        foreach ($records as $record) {
            if (strpos($record['message'], 'HTTP Request') !== false) {
                $hasRequestLog = true;
            }
            if (strpos($record['message'], 'HTTP Response') !== false) {
                $hasResponseLog = true;
            }
        }
        
        $this->assertTrue($hasRequestLog, 'Request should be logged');
        $this->assertTrue($hasResponseLog, 'Response should be logged');
    }

    public function testErrorHandling(): void
    {
        $response = $this->httpClient->get('https://nonexistent-domain-12345.com');
        
        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('code', $response);
    }

    public function testRedirectHandling(): void
    {
        $response = $this->httpClient->get('https://httpbin.org/redirect/2');
        
        $this->assertTrue($response['success']);
        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('redirects', $response);
        $this->assertCount(2, $response['redirects']);
    }

    public function testUserAgentRotation(): void
    {
        $response1 = $this->httpClient->get('https://httpbin.org/user-agent');
        $response2 = $this->httpClient->get('https://httpbin.org/user-agent');
        
        $this->assertTrue($response1['success']);
        $this->assertTrue($response2['success']);
        
        $body1 = json_decode($response1['body'], true);
        $body2 = json_decode($response2['body'], true);
        
        // User agents should be different due to rotation
        $this->assertNotEquals($body1['user-agent'], $body2['user-agent']);
    }
}
