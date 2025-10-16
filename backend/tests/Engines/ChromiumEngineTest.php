<?php

namespace Prism\Backend\Tests\Engines;

use PHPUnit\Framework\TestCase;
use Prism\Backend\Services\Engines\ChromiumEngine;
use Facebook\WebDriver\Exception\WebDriverException;

class ChromiumEngineTest extends TestCase
{
    private ChromiumEngine $engine;
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'headless' => true,
            'sandbox' => false,
            'extensions' => false,
            'window_size' => ['width' => 1920, 'height' => 1080]
        ];
        
        $this->engine = new ChromiumEngine($this->config);
    }

    protected function tearDown(): void
    {
        if ($this->engine->isReady()) {
            $this->engine->close();
        }
    }

    public function testEngineInitialization(): void
    {
        // Note: This test requires ChromeDriver to be running
        // Skip if ChromeDriver is not available
        if (!$this->isChromeDriverAvailable()) {
            $this->markTestSkipped('ChromeDriver not available');
        }

        $result = $this->engine->initialize();
        $this->assertTrue($result);
        $this->assertTrue($this->engine->isReady());
    }

    public function testEngineInfo(): void
    {
        $info = $this->engine->getInfo();
        
        $this->assertIsArray($info);
        $this->assertEquals('Chromium', $info['name']);
        $this->assertArrayHasKey('version', $info);
        $this->assertArrayHasKey('capabilities', $info);
        $this->assertArrayHasKey('config', $info);
        
        $this->assertTrue($info['capabilities']['javascript']);
        $this->assertTrue($info['capabilities']['css']);
        $this->assertTrue($info['capabilities']['html5']);
    }

    public function testNavigation(): void
    {
        if (!$this->isChromeDriverAvailable()) {
            $this->markTestSkipped('ChromeDriver not available');
        }

        $this->engine->initialize();
        
        $this->engine->navigate('https://example.com');
        $this->assertEquals('https://example.com', $this->engine->getCurrentUrl());
        $this->assertStringContainsString('Example Domain', $this->engine->getPageTitle());
    }

    public function testJavaScriptExecution(): void
    {
        if (!$this->isChromeDriverAvailable()) {
            $this->markTestSkipped('ChromeDriver not available');
        }

        $this->engine->initialize();
        $this->engine->navigate('https://example.com');
        
        $result = $this->engine->executeScript('return document.title;');
        $this->assertEquals('Example Domain', $result);
    }

    public function testScreenshot(): void
    {
        if (!$this->isChromeDriverAvailable()) {
            $this->markTestSkipped('ChromeDriver not available');
        }

        $this->engine->initialize();
        $this->engine->navigate('https://example.com');
        
        $screenshot = $this->engine->takeScreenshot();
        $this->assertIsString($screenshot);
        $this->assertNotEmpty($screenshot);
    }

    public function testCookieManagement(): void
    {
        if (!$this->isChromeDriverAvailable()) {
            $this->markTestSkipped('ChromeDriver not available');
        }

        $this->engine->initialize();
        $this->engine->navigate('https://example.com');
        
        // Set a cookie
        $result = $this->engine->setCookie('test_cookie', 'test_value', [
            'domain' => 'example.com',
            'path' => '/',
            'secure' => false
        ]);
        $this->assertTrue($result);
        
        // Get cookies
        $cookies = $this->engine->getCookies();
        $this->assertIsArray($cookies);
        
        // Find our test cookie
        $testCookie = null;
        foreach ($cookies as $cookie) {
            if ($cookie['name'] === 'test_cookie') {
                $testCookie = $cookie;
                break;
            }
        }
        
        $this->assertNotNull($testCookie);
        $this->assertEquals('test_value', $testCookie['value']);
        
        // Delete cookie
        $result = $this->engine->deleteCookie('test_cookie');
        $this->assertTrue($result);
    }

    public function testLocalStorageManagement(): void
    {
        if (!$this->isChromeDriverAvailable()) {
            $this->markTestSkipped('ChromeDriver not available');
        }

        $this->engine->initialize();
        $this->engine->navigate('https://example.com');
        
        // Set localStorage item
        $result = $this->engine->setLocalStorageItem('test_key', 'test_value');
        $this->assertTrue($result);
        
        // Get localStorage item
        $value = $this->engine->getLocalStorageItem('test_key');
        $this->assertEquals('test_value', $value);
        
        // Get all localStorage
        $storage = $this->engine->getLocalStorage();
        $this->assertIsArray($storage);
        $this->assertArrayHasKey('test_key', $storage);
        $this->assertEquals('test_value', $storage['test_key']);
        
        // Remove localStorage item
        $result = $this->engine->removeLocalStorageItem('test_key');
        $this->assertTrue($result);
        
        // Verify removal
        $value = $this->engine->getLocalStorageItem('test_key');
        $this->assertNull($value);
    }

    public function testSessionStorageManagement(): void
    {
        if (!$this->isChromeDriverAvailable()) {
            $this->markTestSkipped('ChromeDriver not available');
        }

        $this->engine->initialize();
        $this->engine->navigate('https://example.com');
        
        // Set sessionStorage item
        $result = $this->engine->setSessionStorageItem('session_key', 'session_value');
        $this->assertTrue($result);
        
        // Get sessionStorage item
        $value = $this->engine->getSessionStorageItem('session_key');
        $this->assertEquals('session_value', $value);
        
        // Get all sessionStorage
        $storage = $this->engine->getSessionStorage();
        $this->assertIsArray($storage);
        $this->assertArrayHasKey('session_key', $storage);
        $this->assertEquals('session_value', $storage['session_key']);
    }

    public function testUserAgentManagement(): void
    {
        $customUserAgent = 'Mozilla/5.0 (compatible; Prism Browser Test)';
        
        // Set user agent before initialization
        $result = $this->engine->setUserAgent($customUserAgent);
        $this->assertTrue($result);
        
        if (!$this->isChromeDriverAvailable()) {
            $this->markTestSkipped('ChromeDriver not available');
        }

        $this->engine->initialize();
        $this->engine->navigate('https://example.com');
        
        $userAgent = $this->engine->getUserAgent();
        $this->assertStringContainsString('Prism Browser Test', $userAgent);
    }

    public function testPerformanceMetrics(): void
    {
        if (!$this->isChromeDriverAvailable()) {
            $this->markTestSkipped('ChromeDriver not available');
        }

        $this->engine->initialize();
        $this->engine->navigate('https://example.com');
        
        $metrics = $this->engine->getPerformanceMetrics();
        $this->assertIsArray($metrics);
        
        // Performance metrics might be empty for some pages
        // Just verify the structure is correct
        if (!empty($metrics)) {
            $this->assertArrayHasKey('loadTime', $metrics);
        }
    }

    public function testMemoryUsage(): void
    {
        if (!$this->isChromeDriverAvailable()) {
            $this->markTestSkipped('ChromeDriver not available');
        }

        $this->engine->initialize();
        $this->engine->navigate('https://example.com');
        
        $memory = $this->engine->getMemoryUsage();
        $this->assertIsArray($memory);
        
        // Memory usage might be empty if not supported
        if (!empty($memory)) {
            $this->assertArrayHasKey('used', $memory);
            $this->assertArrayHasKey('total', $memory);
            $this->assertArrayHasKey('limit', $memory);
        }
    }

    public function testCacheStats(): void
    {
        if (!$this->isChromeDriverAvailable()) {
            $this->markTestSkipped('ChromeDriver not available');
        }

        $this->engine->initialize();
        $this->engine->navigate('https://example.com');
        
        $cache = $this->engine->getCacheStats();
        $this->assertIsArray($cache);
        $this->assertArrayHasKey('cacheCount', $cache);
    }

    public function testEngineClose(): void
    {
        if (!$this->isChromeDriverAvailable()) {
            $this->markTestSkipped('ChromeDriver not available');
        }

        $this->engine->initialize();
        $this->assertTrue($this->engine->isReady());
        
        $this->engine->close();
        $this->assertFalse($this->engine->isReady());
    }

    private function isChromeDriverAvailable(): bool
    {
        // Check if ChromeDriver is running on default port
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:9515/status');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
}