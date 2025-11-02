<?php

namespace Prism\Backend\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Prism\Backend\Services\EngineManager;
use Prism\Backend\Services\DatabaseService;
use Prism\Backend\Models\Tab;
use Ramsey\Uuid\Uuid;
use Monolog\Logger;

class TabController
{
    private EngineManager $engineManager;
    private DatabaseService $database;
    private Logger $logger;

    public function __construct(EngineManager $engineManager, DatabaseService $database, Logger $logger)
    {
        $this->engineManager = $engineManager;
        $this->database = $database;
        $this->logger = $logger;
    }

    private function getUserId(Request $request): ?string
    {
        // Get user_id from JWT middleware if available
        // For now, return null (tabs are not user-specific yet)
        // In the future, this can be: return $request->getAttribute('user_id');
        return null;
    }

    private function loadTab(string $tabId, ?string $userId = null): ?Tab
    {
        $pdo = $this->database->getPdo();
        $sql = "SELECT * FROM tabs WHERE id = ?";
        $params = [$tabId];
        
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return Tab::fromArray($data);
    }

    private function saveTab(Tab $tab): void
    {
        $pdo = $this->database->getPdo();
        
        // Check if tab exists
        $checkStmt = $pdo->prepare("SELECT id FROM tabs WHERE id = ?");
        $checkStmt->execute([$tab->getId()]);
        $exists = $checkStmt->fetch() !== false;
        
        if ($exists) {
            // Update existing tab
            $sql = "UPDATE tabs SET user_id = ?, title = ?, url = ?, is_active = ?, updated_at = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $tab->getUserId(),
                $tab->getTitle(),
                $tab->getUrl(),
                $tab->isActive() ? 1 : 0,
                $tab->getUpdatedAt(),
                $tab->getId()
            ]);
        } else {
            // Insert new tab
            $sql = "INSERT INTO tabs (id, user_id, title, url, is_active, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $tab->getId(),
                $tab->getUserId(),
                $tab->getTitle(),
                $tab->getUrl(),
                $tab->isActive() ? 1 : 0,
                $tab->getCreatedAt(),
                $tab->getUpdatedAt()
            ]);
        }
    }

    private function deactivateOtherTabs(?string $userId, string $exceptTabId): void
    {
        $pdo = $this->database->getPdo();
        $sql = "UPDATE tabs SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id != ?";
        $params = [$exceptTabId];
        
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function list(Request $request, Response $response): Response
    {
        try {
            $userId = $this->getUserId($request);
            $pdo = $this->database->getPdo();
            
            $sql = "SELECT * FROM tabs";
            $params = [];
            
            if ($userId !== null) {
                $sql .= " WHERE user_id = ?";
                $params[] = $userId;
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $result = [];
            foreach ($rows as $row) {
                $tab = Tab::fromArray($row);
                $result[] = $tab->toArray();
            }

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error listing tabs: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to list tabs']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function create(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $url = $data['url'] ?? 'about:blank';
            $title = $data['title'] ?? 'New Tab';
            $userId = $this->getUserId($request);

            $tab = new Tab(
                Uuid::uuid4()->toString(),
                $title,
                $url,
                true,
                $userId
            );

            // Deactivate all other tabs for this user
            $this->deactivateOtherTabs($userId, $tab->getId());
            
            // Save to database
            $this->saveTab($tab);

            // Navigate to URL if not about:blank
            if ($url !== 'about:blank') {
                $navigationResult = $this->navigateToUrl($tab, $url);
                if (!$navigationResult['success']) {
                    // Still save the tab even if navigation fails
                    $this->saveTab($tab);
                    $response->getBody()->write(json_encode([
                        'error' => $navigationResult['error'],
                        'tab' => $tab->toArray()
                    ]));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                // Update tab after navigation (title might have changed)
                $this->saveTab($tab);
            }

            $response->getBody()->write(json_encode($tab->toArray()));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error creating tab: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to create tab']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function get(Request $request, Response $response): Response
    {
        try {
            $args = $request->getAttribute('route')->getArguments();
            $tabId = $args['id'] ?? '';
            $userId = $this->getUserId($request);

            $tab = $this->loadTab($tabId, $userId);
            
            if (!$tab) {
                $response->getBody()->write(json_encode(['error' => 'Tab not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode($tab->toArray()));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error getting tab: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to get tab']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function update(Request $request, Response $response): Response
    {
        try {
            $args = $request->getAttribute('route')->getArguments();
            $tabId = $args['id'] ?? '';
            $data = json_decode($request->getBody()->getContents(), true);
            $userId = $this->getUserId($request);

            $tab = $this->loadTab($tabId, $userId);
            
            if (!$tab) {
                $response->getBody()->write(json_encode(['error' => 'Tab not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            if (isset($data['title'])) {
                $tab->setTitle($data['title']);
            }

            if (isset($data['url'])) {
                $tab->setUrl($data['url']);
                $navigationResult = $this->navigateToUrl($tab, $data['url']);
                if (!$navigationResult['success']) {
                    $this->saveTab($tab); // Save even if navigation fails
                    $response->getBody()->write(json_encode([
                        'error' => $navigationResult['error'],
                        'tab' => $tab->toArray()
                    ]));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                // Title might have changed after navigation
                $this->saveTab($tab);
            }

            if (isset($data['is_active'])) {
                // If setting this tab as active, deactivate all others
                if ($data['is_active']) {
                    $this->deactivateOtherTabs($userId, $tabId);
                }
                $tab->setActive($data['is_active']);
            }

            $this->saveTab($tab);

            $response->getBody()->write(json_encode($tab->toArray()));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error updating tab: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to update tab']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function close(Request $request, Response $response): Response
    {
        try {
            $args = $request->getAttribute('route')->getArguments();
            $tabId = $args['id'] ?? '';
            $userId = $this->getUserId($request);

            $tab = $this->loadTab($tabId, $userId);
            
            if (!$tab) {
                $response->getBody()->write(json_encode(['error' => 'Tab not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $pdo = $this->database->getPdo();
            $sql = "DELETE FROM tabs WHERE id = ?";
            $params = [$tabId];
            
            if ($userId !== null) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error closing tab: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to close tab']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function navigate(Request $request, Response $response): Response
    {
        try {
            $args = $request->getAttribute('route')->getArguments();
            $tabId = $args['id'] ?? '';
            $data = json_decode($request->getBody()->getContents(), true);
            $userId = $this->getUserId($request);

            if (!isset($data['url'])) {
                $response->getBody()->write(json_encode(['error' => 'URL is required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $tab = $this->loadTab($tabId, $userId);
            
            if (!$tab) {
                $response->getBody()->write(json_encode(['error' => 'Tab not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $navigationResult = $this->navigateToUrl($tab, $data['url']);

            if (!$navigationResult['success']) {
                $this->saveTab($tab);
                $response->getBody()->write(json_encode([
                    'error' => $navigationResult['error'],
                    'tab' => $tab->toArray()
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $this->saveTab($tab);

            $result = $tab->toArray();
            $result['content'] = $navigationResult['content'] ?? '';
            $result['metadata'] = $navigationResult['metadata'] ?? [];

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error navigating tab: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to navigate tab']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function content(Request $request, Response $response): Response
    {
        try {
            $args = $request->getAttribute('route')->getArguments();
            $tabId = $args['id'] ?? '';
            $userId = $this->getUserId($request);

            $tab = $this->loadTab($tabId, $userId);
            
            if (!$tab) {
                $response->getBody()->write(json_encode(['error' => 'Tab not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

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
        } catch (\Exception $e) {
            $this->logger->error('Error getting tab content: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to get tab content']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function metadata(Request $request, Response $response): Response
    {
        try {
            $args = $request->getAttribute('route')->getArguments();
            $tabId = $args['id'] ?? '';
            $userId = $this->getUserId($request);

            $tab = $this->loadTab($tabId, $userId);
            
            if (!$tab) {
                $response->getBody()->write(json_encode(['error' => 'Tab not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

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
        } catch (\Exception $e) {
            $this->logger->error('Error getting tab metadata: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to get tab metadata']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
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
