<?php

namespace Prism\Backend\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Prism\Backend\Services\EngineManager;
use Prism\Backend\Models\Tab;
use Ramsey\Uuid\Uuid;

class TabController
{
    private EngineManager $engineManager;
    private array $tabs = [];

    public function __construct(EngineManager $engineManager)
    {
        $this->engineManager = $engineManager;
    }

    public function list(Request $request, Response $response): Response
    {
        $result = [];
        foreach ($this->tabs as $tab) {
            $result[] = [
                'id' => $tab->getId(),
                'title' => $tab->getTitle(),
                'url' => $tab->getUrl(),
                'is_active' => $tab->isActive(),
                'created_at' => $tab->getCreatedAt()
            ];
        }

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        $url = $data['url'] ?? 'about:blank';
        $title = $data['title'] ?? 'New Tab';

        $tab = new Tab(
            Uuid::uuid4()->toString(),
            $title,
            $url,
            true
        );

        $this->tabs[$tab->getId()] = $tab;

        // Navigate to URL if not about:blank
        if ($url !== 'about:blank') {
            $navigationResult = $this->navigateToUrl($tab, $url);
            if (!$navigationResult['success']) {
                $response->getBody()->write(json_encode([
                    'error' => $navigationResult['error'],
                    'tab' => [
                        'id' => $tab->getId(),
                        'title' => $tab->getTitle(),
                        'url' => $tab->getUrl(),
                        'is_active' => $tab->isActive(),
                        'created_at' => $tab->getCreatedAt()
                    ]
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        $result = [
            'id' => $tab->getId(),
            'title' => $tab->getTitle(),
            'url' => $tab->getUrl(),
            'is_active' => $tab->isActive(),
            'created_at' => $tab->getCreatedAt()
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $tabId = $args['id'] ?? '';

        if (!isset($this->tabs[$tabId])) {
            $response->getBody()->write(json_encode(['error' => 'Tab not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $tab = $this->tabs[$tabId];
        $result = [
            'id' => $tab->getId(),
            'title' => $tab->getTitle(),
            'url' => $tab->getUrl(),
            'is_active' => $tab->isActive(),
            'created_at' => $tab->getCreatedAt()
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $tabId = $args['id'] ?? '';
        $data = json_decode($request->getBody()->getContents(), true);

        if (!isset($this->tabs[$tabId])) {
            $response->getBody()->write(json_encode(['error' => 'Tab not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $tab = $this->tabs[$tabId];

        if (isset($data['title'])) {
            $tab->setTitle($data['title']);
        }

        if (isset($data['url'])) {
            $tab->setUrl($data['url']);
            $navigationResult = $this->navigateToUrl($tab, $data['url']);
            if (!$navigationResult['success']) {
                $response->getBody()->write(json_encode([
                    'error' => $navigationResult['error'],
                    'tab' => [
                        'id' => $tab->getId(),
                        'title' => $tab->getTitle(),
                        'url' => $tab->getUrl(),
                        'is_active' => $tab->isActive(),
                        'created_at' => $tab->getCreatedAt()
                    ]
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        if (isset($data['is_active'])) {
            $tab->setActive($data['is_active']);
        }

        $result = [
            'id' => $tab->getId(),
            'title' => $tab->getTitle(),
            'url' => $tab->getUrl(),
            'is_active' => $tab->isActive(),
            'created_at' => $tab->getCreatedAt()
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function close(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $tabId = $args['id'] ?? '';

        if (!isset($this->tabs[$tabId])) {
            $response->getBody()->write(json_encode(['error' => 'Tab not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        unset($this->tabs[$tabId]);

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function navigate(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $tabId = $args['id'] ?? '';
        $data = json_decode($request->getBody()->getContents(), true);

        if (!isset($this->tabs[$tabId])) {
            $response->getBody()->write(json_encode(['error' => 'Tab not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        if (!isset($data['url'])) {
            $response->getBody()->write(json_encode(['error' => 'URL is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $tab = $this->tabs[$tabId];
        $navigationResult = $this->navigateToUrl($tab, $data['url']);

        if (!$navigationResult['success']) {
            $response->getBody()->write(json_encode([
                'error' => $navigationResult['error'],
                'tab' => [
                    'id' => $tab->getId(),
                    'title' => $tab->getTitle(),
                    'url' => $tab->getUrl(),
                    'is_active' => $tab->isActive(),
                    'created_at' => $tab->getCreatedAt()
                ]
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $result = [
            'id' => $tab->getId(),
            'title' => $tab->getTitle(),
            'url' => $tab->getUrl(),
            'is_active' => $tab->isActive(),
            'created_at' => $tab->getCreatedAt(),
            'content' => $navigationResult['content'] ?? '',
            'metadata' => $navigationResult['metadata'] ?? []
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function content(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $tabId = $args['id'] ?? '';

        if (!isset($this->tabs[$tabId])) {
            $response->getBody()->write(json_encode(['error' => 'Tab not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $tab = $this->tabs[$tabId];
        $engine = $this->engineManager->getActiveEngine();

        if (!$engine->isReady()) {
            $response->getBody()->write(json_encode(['error' => 'Engine is not ready']));
            return $response->withStatus(503)->withHeader('Content-Type', 'application/json');
        }

        $content = $engine->getPageContent();
        $metadata = [
            'title' => $engine->getPageTitle(),
            'url' => $engine->getCurrentUrl(),
            'response_time' => $engine->getResponseTime(),
            'content_type' => $engine->getContentType(),
            'content_length' => $engine->getContentLength(),
            'server' => $engine->getServer(),
            'last_modified' => $engine->getLastModified()
        ];

        $result = [
            'content' => $content,
            'metadata' => $metadata
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function metadata(Request $request, Response $response): Response
    {
        $args = $request->getAttribute('route')->getArguments();
        $tabId = $args['id'] ?? '';

        if (!isset($this->tabs[$tabId])) {
            $response->getBody()->write(json_encode(['error' => 'Tab not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $tab = $this->tabs[$tabId];
        $engine = $this->engineManager->getActiveEngine();

        if (!$engine->isReady()) {
            $response->getBody()->write(json_encode(['error' => 'Engine is not ready']));
            return $response->withStatus(503)->withHeader('Content-Type', 'application/json');
        }

        $metadata = [
            'title' => $engine->getPageTitle(),
            'url' => $engine->getCurrentUrl(),
            'response_time' => $engine->getResponseTime(),
            'content_type' => $engine->getContentType(),
            'content_length' => $engine->getContentLength(),
            'server' => $engine->getServer(),
            'last_modified' => $engine->getLastModified()
        ];

        // Get additional metadata if available
        if (method_exists($engine, 'getPageMetadata')) {
            $metadata = array_merge($metadata, $engine->getPageMetadata());
        }

        $response->getBody()->write(json_encode($metadata));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function navigateToUrl(Tab $tab, string $url): array
    {
        try {
            $engine = $this->engineManager->getActiveEngine();
            
            if (!$engine->isReady()) {
                return [
                    'success' => false,
                    'error' => 'Engine is not ready'
                ];
            }
            
            $engine->navigate($url);
            
            $tab->setUrl($url);
            $tab->setTitle($engine->getPageTitle());
            
            return [
                'success' => true,
                'content' => $engine->getPageContent(),
                'metadata' => [
                    'title' => $engine->getPageTitle(),
                    'url' => $engine->getCurrentUrl(),
                    'response_time' => $engine->getResponseTime(),
                    'content_type' => $engine->getContentType(),
                    'content_length' => $engine->getContentLength(),
                    'server' => $engine->getServer(),
                    'last_modified' => $engine->getLastModified()
                ]
            ];
        } catch (\Exception $e) {
            $tab->setTitle('Error');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
