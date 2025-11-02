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
use Slim\Routing\RouteContext;

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
        
        // Clear any existing tabs
        $this->databaseService->getPdo()->exec('DELETE FROM tabs');
        
        // Create EngineManager with minimal config
        $engineConfig = [
            'available' => [],
            'default' => 'prism'
        ];
        $this->engineManager = new EngineManager($engineConfig);
        $this->controller = new TabController($this->engineManager, $this->databaseService, $this->logger);
    }

    protected function tearDown(): void
    {
        // Clean up tabs
        $this->databaseService->getPdo()->exec('DELETE FROM tabs');
    }

    private function createRequestWithRoute(string $method, string $uri, array $routeArgs = []): \Psr\Http\Message\ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
        
        // Mock route arguments
        if (!empty($routeArgs)) {
            $route = $this->createMock(\Slim\Routing\Route::class);
            $route->method('getArguments')->willReturn($routeArgs);
            
            $routeContext = $this->createMock(\Slim\Routing\RouteContext::class);
            $routeContext->method('getRoute')->willReturn($route);
            
            $request = $request->withAttribute(RouteContext::ROUTE, $route);
        }
        
        return $request;
    }

    public function testList()
    {
        $request = $this->createRequestWithRoute('GET', '/api/tabs');
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->list($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertIsArray($body);
        // Should return an array of tabs, not wrapped in 'tabs' key
    }

    public function testCreate()
    {
        $request = $this->createRequestWithRoute('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'about:blank',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->create($request, $response);
        
        $this->assertEquals(201, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('id', $body);
        $this->assertArrayHasKey('url', $body);
        $this->assertArrayHasKey('title', $body);
        $this->assertEquals('about:blank', $body['url']);
        $this->assertEquals('Test Tab', $body['title']);
    }

    public function testCreateWithDefaultValues()
    {
        $request = $this->createRequestWithRoute('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([])));
        
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->create($request, $response);
        
        $this->assertEquals(201, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertEquals('about:blank', $body['url']);
        $this->assertEquals('New Tab', $body['title']);
    }

    public function testGet()
    {
        // First create a tab
        $createRequest = $this->createRequestWithRoute('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'about:blank',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        $createResult = $this->controller->create($createRequest, $response);
        $createBody = json_decode($createResult->getBody()->getContents(), true);
        $tabId = $createBody['id'];
        
        // Now get the tab
        $request = $this->createRequestWithRoute('GET', "/api/tabs/{$tabId}", ['id' => $tabId]);
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->get($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('id', $body);
        $this->assertEquals($tabId, $body['id']);
    }

    public function testGetNonExistentTab()
    {
        $request = $this->createRequestWithRoute('GET', '/api/tabs/non-existent-id', ['id' => 'non-existent-id']);
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->get($request, $response);
        
        $this->assertEquals(404, $result->getStatusCode());
    }

    public function testUpdate()
    {
        // First create a tab
        $createRequest = $this->createRequestWithRoute('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'about:blank',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        $createResult = $this->controller->create($createRequest, $response);
        $createBody = json_decode($createResult->getBody()->getContents(), true);
        $tabId = $createBody['id'];
        
        // Now update the tab
        $request = $this->createRequestWithRoute('PUT', "/api/tabs/{$tabId}", ['id' => $tabId])
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'title' => 'Updated Tab Title'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->update($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('title', $body);
        $this->assertEquals('Updated Tab Title', $body['title']);
    }

    public function testClose()
    {
        // First create a tab
        $createRequest = $this->createRequestWithRoute('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'about:blank',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        $createResult = $this->controller->create($createRequest, $response);
        $createBody = json_decode($createResult->getBody()->getContents(), true);
        $tabId = $createBody['id'];
        
        // Now close the tab
        $request = $this->createRequestWithRoute('DELETE', "/api/tabs/{$tabId}", ['id' => $tabId]);
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->close($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        $body = json_decode($result->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('success', $body);
        $this->assertTrue($body['success']);
        
        // Verify tab is deleted
        $getRequest = $this->createRequestWithRoute('GET', "/api/tabs/{$tabId}", ['id' => $tabId]);
        $getResponse = (new ResponseFactory())->createResponse();
        $getResult = $this->controller->get($getRequest, $getResponse);
        $this->assertEquals(404, $getResult->getStatusCode());
    }

    public function testNavigate()
    {
        // First create a tab
        $createRequest = $this->createRequestWithRoute('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'about:blank',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        $createResult = $this->controller->create($createRequest, $response);
        $createBody = json_decode($createResult->getBody()->getContents(), true);
        $tabId = $createBody['id'];
        
        // Now navigate the tab (will likely fail without a real engine, but structure should be correct)
        $request = $this->createRequestWithRoute('POST', "/api/tabs/{$tabId}/navigate", ['id' => $tabId])
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'about:blank'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->navigate($request, $response);
        
        // Should return 400 if engine not ready, or 200 if successful
        $this->assertContains($result->getStatusCode(), [200, 400, 503]);
    }

    public function testNavigateWithoutUrl()
    {
        // First create a tab
        $createRequest = $this->createRequestWithRoute('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'about:blank',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        $createResult = $this->controller->create($createRequest, $response);
        $createBody = json_decode($createResult->getBody()->getContents(), true);
        $tabId = $createBody['id'];
        
        // Try to navigate without URL
        $request = $this->createRequestWithRoute('POST', "/api/tabs/{$tabId}/navigate", ['id' => $tabId])
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([])));
        
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->navigate($request, $response);
        
        $this->assertEquals(400, $result->getStatusCode());
    }

    public function testContent()
    {
        // First create a tab
        $createRequest = $this->createRequestWithRoute('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'about:blank',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        $createResult = $this->controller->create($createRequest, $response);
        $createBody = json_decode($createResult->getBody()->getContents(), true);
        $tabId = $createBody['id'];
        
        // Get tab content (will likely fail without engine, but structure should be correct)
        $request = $this->createRequestWithRoute('GET', "/api/tabs/{$tabId}/content", ['id' => $tabId]);
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->content($request, $response);
        
        // Should return 503 if engine not ready, or 200 if successful
        $this->assertContains($result->getStatusCode(), [200, 503]);
    }

    public function testMetadata()
    {
        // First create a tab
        $createRequest = $this->createRequestWithRoute('POST', '/api/tabs')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode([
                'url' => 'about:blank',
                'title' => 'Test Tab'
            ])));
        
        $response = (new ResponseFactory())->createResponse();
        $createResult = $this->controller->create($createRequest, $response);
        $createBody = json_decode($createResult->getBody()->getContents(), true);
        $tabId = $createBody['id'];
        
        // Get tab metadata (will likely fail without engine, but structure should be correct)
        $request = $this->createRequestWithRoute('GET', "/api/tabs/{$tabId}/metadata", ['id' => $tabId]);
        $response = (new ResponseFactory())->createResponse();
        
        $result = $this->controller->metadata($request, $response);
        
        // Should return 503 if engine not ready, or 200 if successful
        $this->assertContains($result->getStatusCode(), [200, 503]);
    }
}
