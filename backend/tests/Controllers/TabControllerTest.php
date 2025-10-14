<?php

namespace Prism\Backend\Tests\Controllers;

use PHPUnit\Framework\TestCase;
use Prism\Backend\Controllers\TabController;
use Prism\Backend\Services\EngineManager;
use Prism\Backend\Services\DatabaseService;
use Monolog\Logger;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

class TabControllerTest extends TestCase
{
    private TabController $controller;
    private EngineManager $engineManager;
    private DatabaseService $databaseService;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger('test');
        
        // Create a test database
        $this->databaseService = new DatabaseService([
            'driver' => 'sqlite',
            'database' => ':memory:'
        ]);
        
        $this->engineManager = new EngineManager($this->logger);
        $this->controller = new TabController($this->engineManager, $this->databaseService, $this->logger);
    }

    protected function tearDown(): void
    {
        if ($this->engineManager->getActiveEngine() && $this->engineManager->getActiveEngine()->isReady()) {
            $this->engineManager->getActiveEngine()->close();
        }
    }

    public function testListTabs()
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/tabs');
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->listTabs($request, $response, []);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('tabs', $body);
    }

    public function testCreateTab()
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'https://example.com',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->createTab($request, $response, []);
        
        $this->assertEquals(201, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('tab', $body);
        $this->assertEquals('https://example.com', $body['tab']['url']);
        $this->assertEquals('Test Tab', $body['tab']['title']);
    }

    public function testCreateTabWithInvalidData()
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'invalid-url'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->createTab($request, $response, []);
        
        $this->assertEquals(400, $result->getStatusCode());
    }

    public function testGetTab()
    {
        // First create a tab
        $createRequest = (new ServerRequestFactory())->createServerRequest('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'https://example.com',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        $createResult = $this->controller->createTab($createRequest, $response, []);
        $createBody = json_decode($createResult->getBody()->getContents(), true);
        $tabId = $createBody['tab']['id'];
        
        // Now get the tab
        $request = (new ServerRequestFactory())->createServerRequest('GET', "/api/tabs/{$tabId}");
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->getTab($request, $response, ['id' => $tabId]);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('tab', $body);
        $this->assertEquals($tabId, $body['tab']['id']);
    }

    public function testGetNonExistentTab()
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/tabs/non-existent-id');
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->getTab($request, $response, ['id' => 'non-existent-id']);
        
        $this->assertEquals(404, $result->getStatusCode());
    }

    public function testUpdateTab()
    {
        // First create a tab
        $createRequest = (new ServerRequestFactory())->createServerRequest('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'https://example.com',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        $createResult = $this->controller->createTab($createRequest, $response, []);
        $createBody = json_decode($createResult->getBody()->getContents(), true);
        $tabId = $createBody['tab']['id'];
        
        // Now update the tab
        $request = (new ServerRequestFactory())->createServerRequest('PUT', "/api/tabs/{$tabId}")
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'title' => 'Updated Tab Title',
                'url' => 'https://updated.com'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->updateTab($request, $response, ['id' => $tabId]);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('tab', $body);
        $this->assertEquals('Updated Tab Title', $body['tab']['title']);
        $this->assertEquals('https://updated.com', $body['tab']['url']);
    }

    public function testCloseTab()
    {
        // First create a tab
        $createRequest = (new ServerRequestFactory())->createServerRequest('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'https://example.com',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        $createResult = $this->controller->createTab($createRequest, $response, []);
        $createBody = json_decode($createResult->getBody()->getContents(), true);
        $tabId = $createBody['tab']['id'];
        
        // Now close the tab
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', "/api/tabs/{$tabId}");
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->closeTab($request, $response, ['id' => $tabId]);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('message', $body);
        $this->assertEquals('Tab closed successfully', $body['message']);
    }

    public function testNavigateTab()
    {
        // First create a tab
        $createRequest = (new ServerRequestFactory())->createServerRequest('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'https://example.com',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        $createResult = $this->controller->createTab($createRequest, $response, []);
        $createBody = json_decode($createResult->getBody()->getContents(), true);
        $tabId = $createBody['tab']['id'];
        
        // Now navigate the tab
        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/tabs/{$tabId}/navigate")
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'https://google.com'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->navigateTab($request, $response, ['id' => $tabId]);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('tab', $body);
        $this->assertEquals('https://google.com', $body['tab']['url']);
    }

    public function testNavigateTabWithInvalidUrl()
    {
        // First create a tab
        $createRequest = (new ServerRequestFactory())->createServerRequest('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'https://example.com',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        $createResult = $this->controller->createTab($createRequest, $response, []);
        $createBody = json_decode($createResult->getBody()->getContents(), true);
        $tabId = $createBody['tab']['id'];
        
        // Now try to navigate to an invalid URL
        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/tabs/{$tabId}/navigate")
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'invalid-url'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->navigateTab($request, $response, ['id' => $tabId]);
        
        $this->assertEquals(400, $result->getStatusCode());
    }

    public function testGetTabContent()
    {
        // First create a tab
        $createRequest = (new ServerRequestFactory())->createServerRequest('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'data:text/html,<html><body>Test Content</body></html>',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        $createResult = $this->controller->createTab($createRequest, $response, []);
        $createBody = json_decode($createResult->getBody()->getContents(), true);
        $tabId = $createBody['tab']['id'];
        
        // Now get the tab content
        $request = (new ServerRequestFactory())->createServerRequest('GET', "/api/tabs/{$tabId}/content");
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->getTabContent($request, $response, ['id' => $tabId]);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('content', $body);
        $this->assertStringContains('Test Content', $body['content']);
    }

    public function testExecuteScript()
    {
        // First create a tab
        $createRequest = (new ServerRequestFactory())->createServerRequest('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'data:text/html,<html><body></body></html>',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        $createResult = $this->controller->createTab($createRequest, $response, []);
        $createBody = json_decode($createResult->getBody()->getContents(), true);
        $tabId = $createBody['tab']['id'];
        
        // Now execute a script
        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/tabs/{$tabId}/execute")
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'script' => 'return "Hello from JavaScript";'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->executeScript($request, $response, ['id' => $tabId]);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('result', $body);
        $this->assertEquals('Hello from JavaScript', $body['result']);
    }
}
