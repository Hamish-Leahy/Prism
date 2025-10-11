<?php

namespace Prism\Backend\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Prism\Backend\Services\EngineManager;

class EngineController
{
    private EngineManager $engineManager;

    public function __construct(EngineManager $engineManager)
    {
        $this->engineManager = $engineManager;
    }

    public function list(Request $request, Response $response): Response
    {
        $engines = $this->engineManager->getAvailableEngines();
        
        $result = [];
        foreach ($engines as $key => $engine) {
            $result[] = [
                'id' => $key,
                'name' => $engine['name'],
                'description' => $engine['description'],
                'enabled' => $engine['enabled'],
                'is_current' => $key === $this->engineManager->getCurrentEngine()
            ];
        }

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function switch(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['engine'])) {
            $response->getBody()->write(json_encode(['error' => 'Engine parameter is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $success = $this->engineManager->switchEngine($data['engine']);
        
        if (!$success) {
            $response->getBody()->write(json_encode(['error' => 'Failed to switch engine']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['success' => true, 'engine' => $data['engine']]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function current(Request $request, Response $response): Response
    {
        $currentEngine = $this->engineManager->getCurrentEngine();
        $engines = $this->engineManager->getAvailableEngines();
        $engineInfo = $engines[$currentEngine] ?? null;

        $result = [
            'engine' => $currentEngine,
            'name' => $engineInfo['name'] ?? 'Unknown',
            'description' => $engineInfo['description'] ?? '',
            'is_ready' => $this->engineManager->getActiveEngine()->isReady()
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function status(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $engineName = $args['engine'] ?? '';

        if (empty($engineName)) {
            $response->getBody()->write(json_encode(['error' => 'Engine name is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $status = $this->engineManager->getEngineStatus($engineName);
        
        $response->getBody()->write(json_encode($status));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function info(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $engineName = $args['engine'] ?? '';

        if (empty($engineName)) {
            $response->getBody()->write(json_encode(['error' => 'Engine name is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $engine = $this->engineManager->getEngine($engineName);
        if (!$engine) {
            $response->getBody()->write(json_encode(['error' => 'Engine not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $info = $engine->getInfo();
        
        $response->getBody()->write(json_encode($info));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function stats(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $engineName = $args['engine'] ?? '';

        if (empty($engineName)) {
            $response->getBody()->write(json_encode(['error' => 'Engine name is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $engine = $this->engineManager->getEngine($engineName);
        if (!$engine) {
            $response->getBody()->write(json_encode(['error' => 'Engine not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $stats = [];
        
        // Get performance metrics if available
        if (method_exists($engine, 'getPerformanceMetrics')) {
            $stats['performance'] = $engine->getPerformanceMetrics();
        }
        
        // Get cache stats if available
        if (method_exists($engine, 'getCacheStats')) {
            $stats['cache'] = $engine->getCacheStats();
        }
        
        // Get memory usage if available
        if (method_exists($engine, 'getMemoryUsage')) {
            $stats['memory'] = $engine->getMemoryUsage();
        }
        
        $response->getBody()->write(json_encode($stats));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
