<?php

namespace Prism\Backend\Tests\Engines;

use PHPUnit\Framework\TestCase;
use Prism\Backend\Services\Engines\PrismEngine;
use Monolog\Logger;

class PrismEngineTest extends TestCase
{
    private PrismEngine $engine;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger('test');
        
        $config = [
            'timeout' => 30,
            'connect_timeout' => 10,
            'read_timeout' => 30,
            'verify_ssl' => true,
            'max_redirects' => 10,
            'strict_redirects' => false,
            'follow_referer' => true,
            'allowed_protocols' => ['http', 'https'],
            'cookies_enabled' => true,
            'cache_ttl' => 300,
            'max_retries' => 3,
            'user_agent' => 'Prism/1.0 (Test Engine)',
            'html_parsing' => true,
            'css_parsing' => true,
            'javascript_execution' => true,
            'javascript_enabled' => true,
            'css_enabled' => true,
            'images_enabled' => true,
            'cookies_enabled' => true,
            'local_storage_enabled' => true,
            'session_storage_enabled' => true,
            'websocket_enabled' => true,
            'cache_enabled' => true,
            'local_storage_path' => sys_get_temp_dir() . '/prism_test_local_storage.json',
            'cookie_storage_path' => sys_get_temp_dir() . '/prism_test_cookies.json',
            'cache_path' => sys_get_temp_dir() . '/prism_test_cache',
            'service_worker_storage_path' => sys_get_temp_dir() . '/prism_test_service_workers',
            'offline_storage_path' => sys_get_temp_dir() . '/prism_test_offline'
        ];

        $this->engine = new PrismEngine($config);
    }

    protected function tearDown(): void
    {
        if ($this->engine->isReady()) {
            $this->engine->close();
        }
    }

    public function testEngineInitialization()
    {
        $this->assertFalse($this->engine->isReady());
        
        $result = $this->engine->initialize();
        $this->assertTrue($result);
        $this->assertTrue($this->engine->isReady());
    }

    public function testEngineInfo()
    {
        $this->engine->initialize();
        $info = $this->engine->getInfo();
        
        $this->assertIsArray($info);
        $this->assertEquals('Prism', $info['name']);
        $this->assertEquals('1.0.0', $info['version']);
        $this->assertArrayHasKey('capabilities', $info);
        $this->assertArrayHasKey('config', $info);
        $this->assertArrayHasKey('features', $info);
    }

    public function testNavigationToValidUrl()
    {
        $this->engine->initialize();
        
        // Test navigation to a simple HTML page
        $this->engine->navigate('data:text/html,<html><head><title>Test Page</title></head><body><h1>Hello World</h1></body></html>');
        
        $this->assertTrue($this->engine->isReady());
        $this->assertStringContains('Test Page', $this->engine->getPageTitle());
        $this->assertStringContains('Hello World', $this->engine->getPageContent());
    }

    public function testPageContentRetrieval()
    {
        $this->engine->initialize();
        
        $html = '<html><head><title>Test</title></head><body><p>Content</p></body></html>';
        $this->engine->navigate('data:text/html,' . urlencode($html));
        
        $content = $this->engine->getPageContent();
        $this->assertStringContains('Content', $content);
    }

    public function testPageTitleRetrieval()
    {
        $this->engine->initialize();
        
        $this->engine->navigate('data:text/html,<html><head><title>Test Title</title></head><body></body></html>');
        
        $title = $this->engine->getPageTitle();
        $this->assertEquals('Test Title', $title);
    }

    public function testCurrentUrlRetrieval()
    {
        $this->engine->initialize();
        
        $testUrl = 'data:text/html,<html><body>Test</body></html>';
        $this->engine->navigate($testUrl);
        
        $currentUrl = $this->engine->getCurrentUrl();
        $this->assertStringContains('data:text/html', $currentUrl);
    }

    public function testJavaScriptExecution()
    {
        $this->engine->initialize();
        
        // Test basic JavaScript execution
        $result = $this->engine->executeScript('return "Hello from JavaScript";');
        $this->assertEquals('Hello from JavaScript', $result);
    }

    public function testLocalStorageOperations()
    {
        $this->engine->initialize();
        
        // Test setting and getting local storage
        $this->engine->setLocalStorageItem('test_key', 'test_value');
        $value = $this->engine->getLocalStorageItem('test_key');
        $this->assertEquals('test_value', $value);
        
        // Test local storage stats
        $stats = $this->engine->getLocalStorageStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('items_count', $stats);
        $this->assertEquals(1, $stats['items_count']);
    }

    public function testSessionStorageOperations()
    {
        $this->engine->initialize();
        
        // Test setting and getting session storage
        $this->engine->setSessionStorageItem('session_key', 'session_value');
        $value = $this->engine->getSessionStorageItem('session_key');
        $this->assertEquals('session_value', $value);
        
        // Test session storage stats
        $stats = $this->engine->getSessionStorageStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('items_count', $stats);
        $this->assertEquals(1, $stats['items_count']);
    }

    public function testCookieOperations()
    {
        $this->engine->initialize();
        
        // Test setting and getting cookies
        $this->engine->setCookie('test_cookie', 'test_value', [
            'domain' => 'example.com',
            'path' => '/',
            'expires' => time() + 3600
        ]);
        
        $cookie = $this->engine->getCookie('test_cookie', 'example.com', '/');
        $this->assertEquals('test_value', $cookie);
        
        // Test cookie stats
        $stats = $this->engine->getCookieStats();
        $this->assertIsArray($stats);
    }

    public function testCacheOperations()
    {
        $this->engine->initialize();
        
        // Test cache operations
        $this->engine->cacheSet('test_cache_key', 'test_cache_value', 3600);
        $value = $this->engine->cacheGet('test_cache_key');
        $this->assertEquals('test_cache_value', $value);
        
        $hasKey = $this->engine->cacheHas('test_cache_key');
        $this->assertTrue($hasKey);
        
        // Test cache stats
        $stats = $this->engine->getCacheStats();
        $this->assertIsArray($stats);
    }

    public function testPerformanceMetrics()
    {
        $this->engine->initialize();
        
        $metrics = $this->engine->getPerformanceMetrics();
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('response_time', $metrics);
        $this->assertArrayHasKey('content_length', $metrics);
        $this->assertArrayHasKey('memory_usage', $metrics);
        $this->assertArrayHasKey('memory_limit', $metrics);
    }

    public function testEngineClose()
    {
        $this->engine->initialize();
        $this->assertTrue($this->engine->isReady());
        
        $this->engine->close();
        $this->assertFalse($this->engine->isReady());
    }

    public function testNavigationToInvalidUrl()
    {
        $this->engine->initialize();
        
        $this->expectException(\RuntimeException::class);
        $this->engine->navigate('invalid-url');
    }

    public function testScriptExecutionWhenNotReady()
    {
        $this->expectException(\RuntimeException::class);
        $this->engine->executeScript('return "test";');
    }

    public function testScreenshotNotSupported()
    {
        $this->engine->initialize();
        
        $this->expectException(\RuntimeException::class);
        $this->engine->takeScreenshot();
    }

    public function testLocalStorageQuotaExceeded()
    {
        $this->engine->initialize();
        
        // Set a very small quota
        $reflection = new \ReflectionClass($this->engine);
        $property = $reflection->getProperty('localStorageQuota');
        $property->setAccessible(true);
        $property->setValue($this->engine, 10); // 10 bytes
        
        $this->expectException(\RuntimeException::class);
        $this->engine->setLocalStorageItem('key', 'very_long_value_that_exceeds_quota');
    }

    public function testSessionStorageQuotaExceeded()
    {
        $this->engine->initialize();
        
        // Set a very small quota
        $reflection = new \ReflectionClass($this->engine);
        $property = $reflection->getProperty('sessionStorageQuota');
        $property->setAccessible(true);
        $property->setValue($this->engine, 10); // 10 bytes
        
        $this->expectException(\RuntimeException::class);
        $this->engine->setSessionStorageItem('key', 'very_long_value_that_exceeds_quota');
    }
}
