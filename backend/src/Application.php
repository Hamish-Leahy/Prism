<?php

namespace Prism\Backend;

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\BodyParsingMiddleware;
use Slim\Middleware\ErrorMiddleware;
use Prism\Backend\Middleware\CorsMiddleware;
use Prism\Backend\Middleware\LoggingMiddleware;
use Prism\Backend\Controllers\EngineController;
use Prism\Backend\Controllers\TabController;
use Prism\Backend\Controllers\BookmarkController;
use Prism\Backend\Controllers\HistoryController;
use Prism\Backend\Controllers\SettingsController;
use Prism\Backend\Controllers\DownloadController;
use Prism\Backend\Controllers\AuthenticationController;
use Prism\Backend\Controllers\HealthController;
use Prism\Backend\Controllers\CryptoWalletController;
use Prism\Backend\Controllers\SearchController;
use Prism\Backend\Services\EngineManager;
use Prism\Backend\Services\DatabaseService;
use Prism\Backend\Services\AuthenticationService;
use Prism\Backend\Middleware\JwtMiddleware;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Application
{
    private $app;
    private $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/app.php';
        
        // Create container first
        $container = new Container();
        
        // Create app with container
        $this->app = AppFactory::createFromContainer($container);
        
        $this->setupServices();
        $this->setupMiddleware();
        $this->setupRoutes();
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
        
        // Authentication routes (public)
        $this->app->group('/api/auth', function ($group) use ($container) {
            $group->post('/register', [AuthenticationController::class, 'register']);
            $group->post('/login', [AuthenticationController::class, 'login']);
            $group->post('/refresh', [AuthenticationController::class, 'refresh']);
            $group->post('/logout', [AuthenticationController::class, 'logout']);
            $group->post('/request-password-reset', [AuthenticationController::class, 'requestPasswordReset']);
            $group->post('/reset-password', [AuthenticationController::class, 'resetPassword']);
            $group->get('/verify', [AuthenticationController::class, 'verify']);
            $group->get('/profile', [AuthenticationController::class, 'profile']);
            $group->post('/change-password', [AuthenticationController::class, 'changePassword']);
        });
        
        // Engine routes
        $this->app->group('/api/engine', function ($group) use ($container) {
            $group->post('/navigate', [EngineController::class, 'navigate']);
        });
        
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
        
        // Settings routes
        $this->app->group('/api/settings', function ($group) use ($container) {
            $group->get('', [SettingsController::class, 'list']);
            $group->get('/{key}', [SettingsController::class, 'get']);
            $group->put('/{key}', [SettingsController::class, 'update']);
            $group->put('', [SettingsController::class, 'updateMultiple']);
            $group->delete('/{key}', [SettingsController::class, 'delete']);
            $group->post('/reset', [SettingsController::class, 'reset']);
        });
        
        // Download routes
        $this->app->group('/api/downloads', function ($group) use ($container) {
            $group->get('', [DownloadController::class, 'list']);
            $group->post('', [DownloadController::class, 'create']);
            $group->get('/{id}', [DownloadController::class, 'get']);
            $group->post('/{id}/pause', [DownloadController::class, 'pause']);
            $group->post('/{id}/resume', [DownloadController::class, 'resume']);
            $group->post('/{id}/cancel', [DownloadController::class, 'cancel']);
            $group->delete('/{id}', [DownloadController::class, 'delete']);
        });
        
        // Crypto Wallet routes
        $this->app->group('/api/wallet', function ($group) use ($container) {
            $group->post('/create', [CryptoWalletController::class, 'createWallet']);
            $group->post('/import', [CryptoWalletController::class, 'importWallet']);
            $group->get('', [CryptoWalletController::class, 'getWallets']);
            $group->get('/chains', [CryptoWalletController::class, 'getSupportedChains']);
            $group->get('/{walletId}/balance', [CryptoWalletController::class, 'getBalance']);
            $group->get('/{walletId}/transactions', [CryptoWalletController::class, 'getTransactions']);
            $group->post('/send', [CryptoWalletController::class, 'sendTransaction']);
        });
        
        // Search routes
        $this->app->group('/api/search', function ($group) use ($container) {
            $group->get('', [SearchController::class, 'search']);
            $group->post('/index', [SearchController::class, 'indexPage']);
            $group->get('/stats', [SearchController::class, 'getStats']);
            $group->post('/clear', [SearchController::class, 'clearIndex']);
        });
        
        // Health check
        $this->app->get('/health', function ($request, $response) {
            return $response->withJson(['status' => 'ok', 'timestamp' => time()]);
        });
    }

    private function setupServices(): void
    {
        $container = $this->app->getContainer();
        
        // Ensure container is not null
        if ($container === null) {
            throw new \RuntimeException('Container is null. App may not be properly initialized.');
        }
        
        // Database service
        $container->set('database', function () {
            return new DatabaseService($this->config['database']);
        });
        
        // Engine manager
        $container->set('engineManager', function () {
            return new EngineManager($this->config['engines']);
        });
        
        // Authentication service
        $container->set('authService', function () {
            return new AuthenticationService(
                $this->app->getContainer()->get('database')->getPdo(),
                $this->config['jwt']['secret'] ?? 'your-secret-key',
                $this->config['jwt']['expiration'] ?? 3600,
                $this->config['jwt']['refresh_expiration'] ?? 604800
            );
        });
        
        // JWT middleware
        $container->set('jwtMiddleware', function () {
            return new JwtMiddleware($this->app->getContainer()->get('authService'));
        });
    }

    public function run(): void
    {
        $this->app->run();
    }
}
