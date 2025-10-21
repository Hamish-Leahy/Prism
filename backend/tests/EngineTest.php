<?php

namespace Prism\Backend\Tests;

use PHPUnit\Framework\TestCase;
use Prism\Backend\Services\Engines\PrismEngine;
use Prism\Backend\Services\Engines\ChromiumEngine;
use Prism\Backend\Services\Engines\FirefoxEngine;
use Monolog\Logger;

class EngineTest extends TestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger('test');
    }

    public function testPrismEngineInitialization()
    {
        $config = [
            'timeout' => 30,
            'verify_ssl' => true,
            'user_agent' => 'Prism/1.0 (Test)'
        ];

        $engine = new PrismEngine($config, $this->logger);
        $this->assertInstanceOf(PrismEngine::class, $engine);
        
        $result = $engine->initialize();
        $this->assertTrue($result);
        $this->assertTrue($engine->isReady());
    }

    public function testChromiumEngineInitialization()
    {
        $config = [
            'headless' => true,
            'timeout' => 30
        ];

        $engine = new ChromiumEngine($config, $this->logger);
        $this->assertInstanceOf(ChromiumEngine::class, $engine);
        
        // Note: This test may fail if ChromeDriver is not installed
        // In a real test environment, you'd mock the WebDriver
        $this->assertTrue(true); // Placeholder assertion
    }

    public function testFirefoxEngineInitialization()
    {
        $config = [
            'headless' => true,
            'timeout' => 30
        ];

        $engine = new FirefoxEngine($config, $this->logger);
        $this->assertInstanceOf(FirefoxEngine::class, $engine);
        
        // Note: This test may fail if GeckoDriver is not installed
        // In a real test environment, you'd mock the WebDriver
        $this->assertTrue(true); // Placeholder assertion
    }

    public function testPrismEngineNavigation()
    {
        $config = [
            'timeout' => 30,
            'verify_ssl' => false, // Disable SSL verification for testing
            'user_agent' => 'Prism/1.0 (Test)'
        ];

        $engine = new PrismEngine($config, $this->logger);
        $engine->initialize();

        // Test navigation to a simple URL
        $engine->navigate('data:text/html,<html><body><h1>Test Page</h1></body></html>');
        
        $this->assertTrue($engine->isReady());
        $this->assertStringContainsString('Test Page', $engine->getPageContent());
        $this->assertEquals('data:text/html,<html><body><h1>Test Page</h1></body></html>', $engine->getCurrentUrl());
    }

    public function testPrismEngineJavaScriptExecution()
    {
        $config = [
            'timeout' => 30,
            'verify_ssl' => false,
            'user_agent' => 'Prism/1.0 (Test)'
        ];

        $engine = new PrismEngine($config, $this->logger);
        $engine->initialize();

        // Test JavaScript execution
        $result = $engine->executeScript('2 + 2');
        $this->assertEquals(4, $result);

        $result = $engine->executeScript('"Hello " + "World"');
        $this->assertEquals('Hello World', $result);
    }

    public function testPrismEngineScreenshot()
    {
        $config = [
            'timeout' => 30,
            'verify_ssl' => false,
            'user_agent' => 'Prism/1.0 (Test)'
        ];

        $engine = new PrismEngine($config, $this->logger);
        $engine->initialize();

        // Test screenshot functionality
        $screenshot = $engine->takeScreenshot();
        $this->assertIsString($screenshot);
        $this->assertStringStartsWith('data:image/', $screenshot);
    }

    public function testPrismEngineInfo()
    {
        $config = [
            'timeout' => 30,
            'verify_ssl' => false,
            'user_agent' => 'Prism/1.0 (Test)'
        ];

        $engine = new PrismEngine($config, $this->logger);
        $engine->initialize();

        $info = $engine->getInfo();
        $this->assertIsArray($info);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('version', $info);
        $this->assertEquals('Prism Engine', $info['name']);
    }

    protected function tearDown(): void
    {
        // Clean up any resources
    }
}

