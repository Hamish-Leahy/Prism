<?php

namespace Prism\Backend;

use Slim\Factory\AppFactory;
use Slim\Middleware\BodyParsingMiddleware;
use Slim\Middleware\ErrorMiddleware;
use Prism\Backend\Middleware\CorsMiddleware;
use Prism\Backend\Middleware\LoggingMiddleware;
use Prism\Backend\Controllers\EngineController;
use Prism\Backend\Controllers\TabController;
use Prism\Backend\Controllers\BookmarkController;
use Prism\Backend\Controllers\HistoryController;
use Prism\Backend\Services\EngineManager;
use Prism\Backend\Services\DatabaseService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Application
{
    private $app;
    private $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/app.php';
        $this->app = AppFactory::create();
        $this->setupMiddleware();
        $this->setupRoutes();
        $this->setupServices();
    }

    private function setupMiddleware(): void
    {
        // CORS middleware
        $this->app->add(new CorsMiddleware($this->config['api']['cors']));
        
        // Body parsing middleware
        $this->app->add(new BodyParsingMiddleware());
        
        // Logging middleware
        $logger = new Logger('prism');
        $logger->pushHandler(new StreamHandler($this->config['logging']['path'], $this->config['logging']['level']));
        $this->app->add(new LoggingMiddleware($logger));
        
        // Error handling middleware
        $errorMiddleware = $this->app->addErrorMiddleware(true, true, true);
        $errorMiddleware->setDefaultErrorHandler(function ($request, $exception) use ($logger) {
            $logger->error('Unhandled exception: ' . $exception->getMessage(), [
                'exception' => $exception,
                'request' => $request
            ]);
            return null;
        });
    }

    private function setupRoutes(): void
    {
        $container = $this->app->getContainer();
        
        // Engine routes
        $this->app->group('/api/engines', function ($group) use ($container) {
            $group->get('', [EngineController::class, 'list']);
            $group->post('/switch', [EngineController::class, 'switch']);
            $group->get('/current', [EngineController::class, 'current']);
            $group->get('/{engine}/status', [EngineController::class, 'status']);
            $group->get('/{engine}/info', [EngineController::class, 'info']);
            $group->get('/{engine}/stats', [EngineController::class, 'stats']);
        });
        
        // Tab routes
        $this->app->group('/api/tabs', function ($group) use ($container) {
            $group->get('', [TabController::class, 'list']);
            $group->post('', [TabController::class, 'create']);
            $group->get('/{id}', [TabController::class, 'get']);
            $group->put('/{id}', [TabController::class, 'update']);
            $group->delete('/{id}', [TabController::class, 'close']);
            $group->post('/{id}/navigate', [TabController::class, 'navigate']);
            $group->get('/{id}/content', [TabController::class, 'content']);
            $group->get('/{id}/metadata', [TabController::class, 'metadata']);
        });
        
        // Bookmark routes
        $this->app->group('/api/bookmarks', function ($group) use ($container) {
            $group->get('', [BookmarkController::class, 'list']);
            $group->post('', [BookmarkController::class, 'create']);
            $group->get('/{id}', [BookmarkController::class, 'get']);
            $group->put('/{id}', [BookmarkController::class, 'update']);
            $group->delete('/{id}', [BookmarkController::class, 'delete']);
        });
        
        // History routes
        $this->app->group('/api/history', function ($group) use ($container) {
            $group->get('', [HistoryController::class, 'list']);
            $group->post('', [HistoryController::class, 'add']);
            $group->delete('/{id}', [HistoryController::class, 'delete']);
            $group->delete('', [HistoryController::class, 'clear']);
        });
        
        // Health check
        $this->app->get('/health', function ($request, $response) {
            return $response->withJson(['status' => 'ok', 'timestamp' => time()]);
        });
    }

    private function setupServices(): void
    {
        $container = $this->app->getContainer();
        
        // Database service
        $container->set('database', function () {
            return new DatabaseService($this->config['database']);
        });
        
        // Engine manager
        $container->set('engineManager', function () {
            return new EngineManager($this->config['engines']);
        });
    }

    public function run(): void
    {
        $this->app->run();
    }
}
