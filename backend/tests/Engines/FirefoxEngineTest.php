<?php

namespace Prism\Backend\Tests\Engines;

use PHPUnit\Framework\TestCase;
use Prism\Backend\Services\Engines\FirefoxEngine;
use Monolog\Logger;

class FirefoxEngineTest extends TestCase
{
    private FirefoxEngine $engine;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger('test');
        
        $config = [
            'headless' => true,
            'window_size' => ['width' => 1920, 'height' => 1080],
            'user_agent' => 'Mozilla/5.0 (Test) Firefox/120.0',
            'disable_images' => false,
            'disable_javascript' => false,
            'disable_css' => false,
            'disable_plugins' => false,
            'disable_extensions' => false,
            'disable_web_security' => false,
            'disable_features' => [],
            'experimental_options' => [],
            'prefs' => [],
            'args' => ['--no-sandbox'],
            'extensions' => [],
            'timeout' => 30,
            'implicit_wait' => 10,
            'page_load_timeout' => 30,
            'script_timeout' => 30,
            'private_browsing' => false,
            'tracking_protection' => false,
            'cookie_blocking' => false
        ];

        $this->engine = new FirefoxEngine($config);
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
        $this->assertEquals('Firefox', $info['name']);
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
        // Test with invalid GeckoDriver path
        $invalidConfig = [
            'gecko_driver_path' => '/invalid/path/geckodriver',
            'headless' => true
        ];
        
        $invalidEngine = new FirefoxEngine($invalidConfig);
        
        $this->expectException(\RuntimeException::class);
        $invalidEngine->initialize();
    }

    public function testEngineWithCustomOptions()
    {
        $customConfig = [
            'headless' => true,
            'window_size' => ['width' => 800, 'height' => 600],
            'user_agent' => 'Custom Firefox User Agent',
            'args' => ['--no-sandbox'],
            'prefs' => [
                'dom.webnotifications.enabled' => false
            ]
        ];
        
        $customEngine = new FirefoxEngine($customConfig);
        $result = $customEngine->initialize();
        
        $this->assertTrue($result);
        $this->assertTrue($customEngine->isReady());
        
        $customEngine->close();
    }

    public function testEngineWithPrivateBrowsing()
    {
        $privateConfig = [
            'headless' => true,
            'private_browsing' => true,
            'args' => ['--no-sandbox']
        ];
        
        $privateEngine = new FirefoxEngine($privateConfig);
        $result = $privateEngine->initialize();
        
        $this->assertTrue($result);
        $this->assertTrue($privateEngine->isReady());
        
        $privateEngine->close();
    }

    public function testEngineWithTrackingProtection()
    {
        $trackingConfig = [
            'headless' => true,
            'tracking_protection' => true,
            'args' => ['--no-sandbox']
        ];
        
        $trackingEngine = new FirefoxEngine($trackingConfig);
        $result = $trackingEngine->initialize();
        
        $this->assertTrue($result);
        $this->assertTrue($trackingEngine->isReady());
        
        $trackingEngine->close();
    }

    public function testEngineWithCookieBlocking()
    {
        $cookieConfig = [
            'headless' => true,
            'cookie_blocking' => true,
            'args' => ['--no-sandbox']
        ];
        
        $cookieEngine = new FirefoxEngine($cookieConfig);
        $result = $cookieEngine->initialize();
        
        $this->assertTrue($result);
        $this->assertTrue($cookieEngine->isReady());
        
        $cookieEngine->close();
    }

    public function testEngineWithExtensions()
    {
        $extensionConfig = [
            'headless' => false, // Extensions don't work in headless mode
            'extensions' => [
                // Add test extension paths here if available
            ]
        ];
        
        $extensionEngine = new FirefoxEngine($extensionConfig);
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
            'args' => ['--no-sandbox']
        ];
        
        $disabledEngine = new FirefoxEngine($disabledConfig);
        $result = $disabledEngine->initialize();
        
        $this->assertTrue($result);
        $this->assertTrue($disabledEngine->isReady());
        
        $disabledEngine->close();
    }

    public function testEngineWithCustomProfile()
    {
        $profileConfig = [
            'headless' => true,
            'profile_path' => sys_get_temp_dir() . '/firefox_test_profile',
            'args' => ['--no-sandbox']
        ];
        
        $profileEngine = new FirefoxEngine($profileConfig);
        $result = $profileEngine->initialize();
        
        $this->assertTrue($result);
        $this->assertTrue($profileEngine->isReady());
        
        $profileEngine->close();
    }
}
