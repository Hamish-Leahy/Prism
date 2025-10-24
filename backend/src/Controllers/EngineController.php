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

    /**
     * Advanced Engine Features
     */
    public function getAdvancedStats(Request $request, Response $response): Response
    {
        try {
            $engine = $this->engineManager->getActiveEngine();
            if (!$engine) {
                throw new \RuntimeException('No engine available');
            }

            $stats = $engine->getAdvancedStats();
            
            $response->getBody()->write(json_encode($stats));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function enableAdvancedFeatures(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $features = $data['features'] ?? [];

            $engine = $this->engineManager->getActiveEngine();
            if (!$engine) {
                throw new \RuntimeException('No engine available');
            }

            $success = $engine->enableAdvancedFeatures($features);
            
            $response->getBody()->write(json_encode(['success' => $success]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * WebRTC Endpoints
     */
    public function createWebRTCConnection(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $connectionId = $data['connection_id'] ?? uniqid('webrtc_');
            $options = $data['options'] ?? [];

            $engine = $this->engineManager->getActiveEngine();
            if (!$engine) {
                throw new \RuntimeException('No engine available');
            }

            $connection = $engine->createWebRTCPeerConnection($connectionId, $options);
            
            $response->getBody()->write(json_encode($connection));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function sendWebRTCData(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $connectionId = $data['connection_id'] ?? '';
            $channelName = $data['channel_name'] ?? '';
            $message = $data['message'] ?? '';

            if (empty($connectionId) || empty($channelName)) {
                throw new \InvalidArgumentException('Connection ID and channel name are required');
            }

            $engine = $this->engineManager->getActiveEngine();
            if (!$engine) {
                throw new \RuntimeException('No engine available');
            }

            $success = $engine->sendWebRTCData($connectionId, $channelName, $message);
            
            $response->getBody()->write(json_encode(['success' => $success]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * WebAssembly Endpoints
     */
    public function compileWebAssemblyModule(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $wasmBinary = $data['wasm_binary'] ?? '';

            if (empty($wasmBinary)) {
                throw new \InvalidArgumentException('WASM binary is required');
            }

            $engine = $this->engineManager->getActiveEngine();
            if (!$engine) {
                throw new \RuntimeException('No engine available');
            }

            $moduleId = $engine->compileWebAssemblyModule($wasmBinary);
            
            if ($moduleId) {
                $response->getBody()->write(json_encode(['module_id' => $moduleId]));
            } else {
                $response->getBody()->write(json_encode(['error' => 'Failed to compile module']));
                return $response->withStatus(500);
            }
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function callWebAssemblyFunction(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $instanceId = $data['instance_id'] ?? '';
            $functionName = $data['function_name'] ?? '';
            $args = $data['args'] ?? [];

            if (empty($instanceId) || empty($functionName)) {
                throw new \InvalidArgumentException('Instance ID and function name are required');
            }

            $engine = $this->engineManager->getActiveEngine();
            if (!$engine) {
                throw new \RuntimeException('No engine available');
            }

            $result = $engine->callWebAssemblyFunction($instanceId, $functionName, $args);
            
            $response->getBody()->write(json_encode(['result' => $result]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Service Worker Endpoints
     */
    public function registerServiceWorker(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $scope = $data['scope'] ?? '/';
            $scriptUrl = $data['script_url'] ?? '';
            $options = $data['options'] ?? [];

            if (empty($scriptUrl)) {
                throw new \InvalidArgumentException('Script URL is required');
            }

            $engine = $this->engineManager->getActiveEngine();
            if (!$engine) {
                throw new \RuntimeException('No engine available');
            }

            $registrationId = $engine->registerServiceWorker($scope, $scriptUrl, $options);
            
            $response->getBody()->write(json_encode(['registration_id' => $registrationId]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Push Notification Endpoints
     */
    public function subscribeToPushNotifications(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $subscriptionId = $data['subscription_id'] ?? uniqid('push_');
            $subscriptionData = $data['subscription_data'] ?? [];

            $engine = $this->engineManager->getActiveEngine();
            if (!$engine) {
                throw new \RuntimeException('No engine available');
            }

            $success = $engine->subscribeToPushNotifications($subscriptionId, $subscriptionData);
            
            $response->getBody()->write(json_encode(['success' => $success, 'subscription_id' => $subscriptionId]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Offline Support Endpoints
     */
    public function setOnlineStatus(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $isOnline = $data['is_online'] ?? true;

            $engine = $this->engineManager->getActiveEngine();
            if (!$engine) {
                throw new \RuntimeException('No engine available');
            }

            $engine->setOnlineStatus($isOnline);
            
            $response->getBody()->write(json_encode(['success' => true, 'is_online' => $isOnline]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function createOfflineManifest(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $manifestId = $data['manifest_id'] ?? uniqid('manifest_');
            $resources = $data['resources'] ?? [];
            $options = $data['options'] ?? [];

            if (empty($resources)) {
                throw new \InvalidArgumentException('Resources are required');
            }

            $engine = $this->engineManager->getActiveEngine();
            if (!$engine) {
                throw new \RuntimeException('No engine available');
            }

            $success = $engine->createOfflineManifest($manifestId, $resources, $options);
            
            $response->getBody()->write(json_encode(['success' => $success, 'manifest_id' => $manifestId]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Navigate to URL with specified engine
     */
    public function navigate(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $url = $data['url'] ?? '';
            $engineName = $data['engine'] ?? 'prism';

            if (empty($url)) {
                throw new \InvalidArgumentException('URL is required');
            }

            // Switch to specified engine if different from current
            if ($engineName !== $this->engineManager->getCurrentEngine()) {
                $this->engineManager->switchEngine($engineName);
            }

            $engine = $this->engineManager->getActiveEngine();
            if (!$engine) {
                throw new \RuntimeException('No engine available');
            }

            // Navigate to URL
            $engine->navigateTo($url);
            
            // Get rendered content
            $content = $engine->getPageContent();
            $title = $engine->getPageTitle();
            $currentUrl = $engine->getCurrentUrl();
            
            $result = [
                'success' => true,
                'url' => $currentUrl,
                'title' => $title,
                'content' => $content,
                'engine' => $engineName
            ];
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
