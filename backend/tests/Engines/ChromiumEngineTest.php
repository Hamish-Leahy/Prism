<?php

namespace Prism\Backend\Tests\Engines;

use PHPUnit\Framework\TestCase;
use Prism\Backend\Services\Engines\ChromiumEngine;
use Monolog\Logger;

class ChromiumEngineTest extends TestCase
{
    private ChromiumEngine $engine;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger('test');
        
        $config = [
            'headless' => true,
            'window_size' => ['width' => 1920, 'height' => 1080],
            'user_agent' => 'Mozilla/5.0 (Test) Chrome/120.0.0.0',
            'disable_images' => false,
            'disable_javascript' => false,
            'disable_css' => false,
            'disable_plugins' => false,
            'disable_extensions' => false,
            'disable_web_security' => false,
            'disable_features' => [],
            'experimental_options' => [],
            'prefs' => [],
            'args' => ['--no-sandbox', '--disable-dev-shm-usage'],
            'extensions' => [],
            'timeout' => 30,
            'implicit_wait' => 10,
            'page_load_timeout' => 30,
            'script_timeout' => 30
        ];

        $this->engine = new ChromiumEngine($config);
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
        $this->assertEquals('Chromium', $info['name']);
        $this->assertArrayHasKey('version', $info);
        $this->assertArrayHasKey('capabilities', $info);
        $this->assertArrayHasKey('config', $info);
    }

    public function testNavigationToDataUrl()
    {
        $this->engine->initialize();
        
        $html = '<html><head><title>Test Page</title></head><body><h1>Hello World</h1></body></html>';
        $this->engine->navigate('data:text/html,' . urlencode($html));
        
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
        
        $this->engine->navigate('data:text/html,<html><body></body></html>');
        
        // Test basic JavaScript execution
        $result = $this->engine->executeScript('return "Hello from JavaScript";');
        $this->assertEquals('Hello from JavaScript', $result);
    }

    public function testJavaScriptExecutionWithArguments()
    {
        $this->engine->initialize();
        
        $this->engine->navigate('data:text/html,<html><body></body></html>');
        
        // Test JavaScript execution with arguments
        $result = $this->engine->executeScript('return arguments[0] + " " + arguments[1];', ['Hello', 'World']);
        $this->assertEquals('Hello World', $result);
    }

    public function testScreenshot()
    {
        $this->engine->initialize();
        
        $this->engine->navigate('data:text/html,<html><body><h1>Test Screenshot</h1></body></html>');
        
        $screenshot = $this->engine->takeScreenshot();
        $this->assertIsString($screenshot);
        $this->assertNotEmpty($screenshot);
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

    public function testScreenshotWhenNotReady()
    {
        $this->expectException(\RuntimeException::class);
        $this->engine->takeScreenshot();
    }

    public function testEngineInitializationFailure()
    {
        // Test with invalid ChromeDriver path
        $invalidConfig = [
            'chrome_driver_path' => '/invalid/path/chromedriver',
            'headless' => true
        ];
        
        $invalidEngine = new ChromiumEngine($invalidConfig);
        
        $this->expectException(\RuntimeException::class);
        $invalidEngine->initialize();
    }

    public function testEngineWithCustomOptions()
    {
        $customConfig = [
            'headless' => true,
            'window_size' => ['width' => 800, 'height' => 600],
            'user_agent' => 'Custom User Agent',
            'args' => ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
            'prefs' => [
                'profile.default_content_setting_values.notifications' => 2
            ]
        ];
        
        $customEngine = new ChromiumEngine($customConfig);
        $result = $customEngine->initialize();
        
        $this->assertTrue($result);
        $this->assertTrue($customEngine->isReady());
        
        $customEngine->close();
    }

    public function testEngineWithExtensions()
    {
        $extensionConfig = [
            'headless' => false, // Extensions don't work in headless mode
            'extensions' => [
                // Add test extension paths here if available
            ]
        ];
        
        $extensionEngine = new ChromiumEngine($extensionConfig);
        $result = $extensionEngine->initialize();
        
        $this->assertTrue($result);
        $this->assertTrue($extensionEngine->isReady());
        
        $extensionEngine->close();
    }

    public function testEnginePerformanceMetrics()
    {
        $this->engine->initialize();
        
        $this->engine->navigate('data:text/html,<html><body>Performance Test</body></html>');
        
        $metrics = $this->engine->getPerformanceMetrics();
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('response_time', $metrics);
        $this->assertArrayHasKey('content_length', $metrics);
        $this->assertArrayHasKey('memory_usage', $metrics);
    }

    public function testEngineWithDisabledFeatures()
    {
        $disabledConfig = [
            'headless' => true,
            'disable_images' => true,
            'disable_javascript' => true,
            'disable_css' => true,
            'args' => ['--no-sandbox', '--disable-dev-shm-usage']
        ];
        
        $disabledEngine = new ChromiumEngine($disabledConfig);
        $result = $disabledEngine->initialize();
        
        $this->assertTrue($result);
        $this->assertTrue($disabledEngine->isReady());
        
        $disabledEngine->close();
    }
}
