<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class OfflineService
{
    private array $config;
    private Logger $logger;
    private array $offlineData = [];
    private array $offlineManifests = [];
    private array $offlineCache = [];
    private array $syncQueue = [];
    private bool $initialized = false;
    private bool $isOnline = true;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing Offline service");
            
            // Create offline storage directory
            $storagePath = $this->config['storage_path'] ?? sys_get_temp_dir() . '/prism_offline';
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            // Load existing offline data
            $this->loadOfflineData();

            $this->initialized = true;
            $this->logger->info("Offline service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Offline service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function setOnlineStatus(bool $isOnline): void
    {
        $this->isOnline = $isOnline;
        $this->logger->info("Network status changed", ['is_online' => $isOnline]);
        
        if ($isOnline) {
            $this->processSyncQueue();
        }
    }

    public function isOnline(): bool
    {
        return $this->isOnline;
    }

    public function createOfflineManifest(string $manifestId, array $resources, array $options = []): bool
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Offline service not initialized');
        }

        try {
            $manifest = [
                'id' => $manifestId,
                'version' => $options['version'] ?? '1.0.0',
                'resources' => $resources,
                'fallback' => $options['fallback'] ?? '/offline.html',
                'network' => $options['network'] ?? ['*'],
                'cache' => $options['cache'] ?? [],
                'strategy' => $options['strategy'] ?? 'cache_first',
                'max_age' => $options['max_age'] ?? 86400, // 24 hours
                'created_at' => time(),
                'last_updated' => time(),
                'active' => true
            ];

            $this->offlineManifests[$manifestId] = $manifest;
            $this->saveOfflineData();

            $this->logger->info("Created offline manifest", [
                'manifest_id' => $manifestId,
                'resources_count' => count($resources)
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to create offline manifest: " . $e->getMessage());
            return false;
        }
    }

    public function cacheResource(string $url, string $content, array $headers = [], array $options = []): bool
    {
        try {
            $cacheKey = $this->generateCacheKey($url);
            
            $cachedResource = [
                'url' => $url,
                'content' => $content,
                'headers' => $headers,
                'content_type' => $headers['content-type'] ?? 'text/html',
                'content_length' => strlen($content),
                'cached_at' => time(),
                'expires_at' => time() + ($options['ttl'] ?? 86400),
                'strategy' => $options['strategy'] ?? 'cache_first',
                'priority' => $options['priority'] ?? 'normal'
            ];

            $this->offlineCache[$cacheKey] = $cachedResource;
            $this->saveOfflineData();

            $this->logger->info("Cached resource", [
                'url' => $url,
                'size' => strlen($content),
                'strategy' => $cachedResource['strategy']
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to cache resource: " . $e->getMessage());
            return false;
        }
    }

    public function getCachedResource(string $url): ?array
    {
        $cacheKey = $this->generateCacheKey($url);
        
        if (!isset($this->offlineCache[$cacheKey])) {
            return null;
        }

        $resource = $this->offlineCache[$cacheKey];
        
        // Check if resource has expired
        if ($resource['expires_at'] < time()) {
            unset($this->offlineCache[$cacheKey]);
            $this->saveOfflineData();
            return null;
        }

        // Update last accessed time
        $resource['last_accessed'] = time();
        $this->offlineCache[$cacheKey] = $resource;

        return $resource;
    }

    public function isResourceCached(string $url): bool
    {
        $cacheKey = $this->generateCacheKey($url);
        return isset($this->offlineCache[$cacheKey]) && 
               $this->offlineCache[$cacheKey]['expires_at'] >= time();
    }

    public function getOfflineResponse(string $url, array $requestHeaders = []): ?array
    {
        if ($this->isOnline()) {
            return null; // Don't serve offline content when online
        }

        $cachedResource = $this->getCachedResource($url);
        if (!$cachedResource) {
            return null;
        }

        return [
            'status' => 200,
            'headers' => $cachedResource['headers'],
            'body' => $cachedResource['content'],
            'cached' => true,
            'cached_at' => $cachedResource['cached_at']
        ];
    }

    public function addToSyncQueue(string $action, array $data, array $options = []): string
    {
        $syncId = 'sync_' . uniqid();
        
        $syncItem = [
            'id' => $syncId,
            'action' => $action,
            'data' => $data,
            'options' => $options,
            'priority' => $options['priority'] ?? 'normal',
            'retry_count' => 0,
            'max_retries' => $options['max_retries'] ?? 3,
            'created_at' => time(),
            'last_attempt' => null,
            'status' => 'pending'
        ];

        $this->syncQueue[$syncId] = $syncItem;
        $this->saveOfflineData();

        $this->logger->info("Added item to sync queue", [
            'sync_id' => $syncId,
            'action' => $action,
            'priority' => $syncItem['priority']
        ]);

        return $syncId;
    }

    public function processSyncQueue(): int
    {
        if (!$this->isOnline()) {
            return 0;
        }

        $processed = 0;
        $failed = 0;

        // Sort by priority and creation time
        uasort($this->syncQueue, function($a, $b) {
            $priorityOrder = ['high' => 3, 'normal' => 2, 'low' => 1];
            $aPriority = $priorityOrder[$a['priority']] ?? 2;
            $bPriority = $priorityOrder[$b['priority']] ?? 2;
            
            if ($aPriority === $bPriority) {
                return $a['created_at'] - $b['created_at'];
            }
            
            return $bPriority - $aPriority;
        });

        foreach ($this->syncQueue as $syncId => $item) {
            if ($item['status'] !== 'pending') {
                continue;
            }

            try {
                $success = $this->processSyncItem($item);
                
                if ($success) {
                    $this->syncQueue[$syncId]['status'] = 'completed';
                    $this->syncQueue[$syncId]['completed_at'] = time();
                    $processed++;
                } else {
                    $this->syncQueue[$syncId]['retry_count']++;
                    $this->syncQueue[$syncId]['last_attempt'] = time();
                    
                    if ($this->syncQueue[$syncId]['retry_count'] >= $item['max_retries']) {
                        $this->syncQueue[$syncId]['status'] = 'failed';
                        $failed++;
                    }
                }
            } catch (\Exception $e) {
                $this->syncQueue[$syncId]['retry_count']++;
                $this->syncQueue[$syncId]['last_attempt'] = time();
                $this->syncQueue[$syncId]['error'] = $e->getMessage();
                
                if ($this->syncQueue[$syncId]['retry_count'] >= $item['max_retries']) {
                    $this->syncQueue[$syncId]['status'] = 'failed';
                    $failed++;
                }
                
                $this->logger->error("Failed to process sync item", [
                    'sync_id' => $syncId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Clean up completed and failed items
        $this->cleanupSyncQueue();

        $this->logger->info("Processed sync queue", [
            'processed' => $processed,
            'failed' => $failed,
            'remaining' => count($this->syncQueue)
        ]);

        return $processed;
    }

    public function getOfflineManifest(string $manifestId): ?array
    {
        return $this->offlineManifests[$manifestId] ?? null;
    }

    public function updateOfflineManifest(string $manifestId, array $updates): bool
    {
        if (!isset($this->offlineManifests[$manifestId])) {
            return false;
        }

        $this->offlineManifests[$manifestId] = array_merge($this->offlineManifests[$manifestId], $updates);
        $this->offlineManifests[$manifestId]['last_updated'] = time();
        $this->saveOfflineData();

        return true;
    }

    public function deleteOfflineManifest(string $manifestId): bool
    {
        if (!isset($this->offlineManifests[$manifestId])) {
            return false;
        }

        unset($this->offlineManifests[$manifestId]);
        $this->saveOfflineData();

        $this->logger->info("Deleted offline manifest", ['manifest_id' => $manifestId]);
        return true;
    }

    public function clearOfflineCache(array $filters = []): int
    {
        $cleared = 0;
        
        foreach ($this->offlineCache as $cacheKey => $resource) {
            $shouldClear = true;
            
            if (!empty($filters['strategy'])) {
                $shouldClear = $resource['strategy'] === $filters['strategy'];
            }
            
            if (!empty($filters['max_age'])) {
                $shouldClear = $shouldClear && (time() - $resource['cached_at']) > $filters['max_age'];
            }
            
            if ($shouldClear) {
                unset($this->offlineCache[$cacheKey]);
                $cleared++;
            }
        }

        $this->saveOfflineData();

        $this->logger->info("Cleared offline cache", [
            'cleared_count' => $cleared,
            'filters' => $filters
        ]);

        return $cleared;
    }

    public function getSyncQueueStatus(): array
    {
        $status = [
            'total' => count($this->syncQueue),
            'pending' => 0,
            'completed' => 0,
            'failed' => 0,
            'retrying' => 0
        ];

        foreach ($this->syncQueue as $item) {
            $status[$item['status']]++;
        }

        return $status;
    }

    public function getStats(): array
    {
        $cacheSize = array_sum(array_column($this->offlineCache, 'content_length'));
        
        return [
            'manifests_count' => count($this->offlineManifests),
            'cached_resources' => count($this->offlineCache),
            'cache_size' => $cacheSize,
            'sync_queue_size' => count($this->syncQueue),
            'is_online' => $this->isOnline,
            'initialized' => $this->initialized
        ];
    }

    private function generateCacheKey(string $url): string
    {
        return 'cache_' . md5($url);
    }

    private function processSyncItem(array $item): bool
    {
        // Mock sync processing - in a real implementation, this would
        // make actual HTTP requests or database operations
        
        switch ($item['action']) {
            case 'http_request':
                return $this->processHttpRequest($item['data']);
            case 'form_submission':
                return $this->processFormSubmission($item['data']);
            case 'data_sync':
                return $this->processDataSync($item['data']);
            default:
                return false;
        }
    }

    private function processHttpRequest(array $data): bool
    {
        // Mock HTTP request processing
        $this->logger->info("Processing HTTP request", ['url' => $data['url'] ?? 'unknown']);
        return true;
    }

    private function processFormSubmission(array $data): bool
    {
        // Mock form submission processing
        $this->logger->info("Processing form submission", ['form_id' => $data['form_id'] ?? 'unknown']);
        return true;
    }

    private function processDataSync(array $data): bool
    {
        // Mock data sync processing
        $this->logger->info("Processing data sync", ['data_type' => $data['type'] ?? 'unknown']);
        return true;
    }

    private function cleanupSyncQueue(): void
    {
        $this->syncQueue = array_filter($this->syncQueue, function($item) {
            return $item['status'] === 'pending';
        });
    }

    private function loadOfflineData(): void
    {
        $storagePath = $this->config['storage_path'] ?? sys_get_temp_dir() . '/prism_offline';
        $dataFile = $storagePath . '/offline_data.json';
        
        if (file_exists($dataFile)) {
            $data = json_decode(file_get_contents($dataFile), true);
            if ($data) {
                $this->offlineData = $data['offline_data'] ?? [];
                $this->offlineManifests = $data['manifests'] ?? [];
                $this->offlineCache = $data['cache'] ?? [];
                $this->syncQueue = $data['sync_queue'] ?? [];
            }
        }
    }

    private function saveOfflineData(): void
    {
        $storagePath = $this->config['storage_path'] ?? sys_get_temp_dir() . '/prism_offline';
        $dataFile = $storagePath . '/offline_data.json';
        
        $data = [
            'offline_data' => $this->offlineData,
            'manifests' => $this->offlineManifests,
            'cache' => $this->offlineCache,
            'sync_queue' => $this->syncQueue,
            'last_saved' => time()
        ];

        file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function cleanup(): void
    {
        $this->offlineData = [];
        $this->offlineManifests = [];
        $this->offlineCache = [];
        $this->syncQueue = [];
        $this->initialized = false;
        $this->isOnline = true;
        $this->logger->info("Offline service cleaned up");
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }
}
