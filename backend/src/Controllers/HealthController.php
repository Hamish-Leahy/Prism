<?php

namespace Prism\Backend\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Prism\Backend\Services\DatabaseService;
use Prism\Backend\Services\EngineManager;

class HealthController
{
    private DatabaseService $database;
    private EngineManager $engineManager;

    public function __construct(DatabaseService $database, EngineManager $engineManager)
    {
        $this->database = $database;
        $this->engineManager = $engineManager;
    }

    public function check(Request $request, Response $response): Response
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'version' => '1.0.0',
            'services' => []
        ];

        // Check database
        try {
            $this->database->query('SELECT 1');
            $health['services']['database'] = [
                'status' => 'healthy',
                'message' => 'Database connection successful'
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['services']['database'] = [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }

        // Check engines
        try {
            $engines = $this->engineManager->getAvailableEngines();
            $activeEngine = $this->engineManager->getCurrentEngine();
            
            $health['services']['engines'] = [
                'status' => 'healthy',
                'message' => 'Engine manager operational',
                'available_engines' => array_keys($engines),
                'active_engine' => $activeEngine
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['services']['engines'] = [
                'status' => 'unhealthy',
                'message' => 'Engine manager failed: ' . $e->getMessage()
            ];
        }

        // Check memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryPercent = $memoryUsage / $this->parseMemoryLimit($memoryLimit);

        $health['services']['memory'] = [
            'status' => $memoryPercent > 0.9 ? 'warning' : 'healthy',
            'message' => sprintf('Memory usage: %s / %s (%.1f%%)', 
                $this->formatBytes($memoryUsage), 
                $memoryLimit, 
                $memoryPercent * 100
            ),
            'usage_bytes' => $memoryUsage,
            'limit_bytes' => $this->parseMemoryLimit($memoryLimit),
            'usage_percent' => $memoryPercent
        ];

        // Check disk space
        $diskFree = disk_free_space(__DIR__);
        $diskTotal = disk_total_space(__DIR__);
        $diskPercent = ($diskTotal - $diskFree) / $diskTotal;

        $health['services']['disk'] = [
            'status' => $diskPercent > 0.9 ? 'warning' : 'healthy',
            'message' => sprintf('Disk usage: %s / %s (%.1f%%)', 
                $this->formatBytes($diskTotal - $diskFree), 
                $this->formatBytes($diskTotal), 
                $diskPercent * 100
            ),
            'free_bytes' => $diskFree,
            'total_bytes' => $diskTotal,
            'usage_percent' => $diskPercent
        ];

        // Check PHP version
        $phpVersion = PHP_VERSION;
        $health['services']['php'] = [
            'status' => 'healthy',
            'message' => "PHP {$phpVersion}",
            'version' => $phpVersion,
            'sapi' => php_sapi_name()
        ];

        // Overall status
        $unhealthyServices = array_filter($health['services'], function($service) {
            return $service['status'] === 'unhealthy';
        });

        if (!empty($unhealthyServices)) {
            $health['status'] = 'unhealthy';
        }

        $statusCode = $health['status'] === 'healthy' ? 200 : 503;
        
        $response->getBody()->write(json_encode($health, JSON_PRETTY_PRINT));
        return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
    }

    public function ready(Request $request, Response $response): Response
    {
        // Simple readiness check - just verify the application is running
        $ready = [
            'status' => 'ready',
            'timestamp' => date('c'),
            'message' => 'Application is ready to accept requests'
        ];

        $response->getBody()->write(json_encode($ready, JSON_PRETTY_PRINT));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }

    public function live(Request $request, Response $response): Response
    {
        // Simple liveness check - just verify the application is alive
        $live = [
            'status' => 'alive',
            'timestamp' => date('c'),
            'message' => 'Application is alive'
        ];

        $response->getBody()->write(json_encode($live, JSON_PRETTY_PRINT));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
