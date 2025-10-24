<?php

namespace Prism\Backend\Services;

use Monolog\Logger;
use React\EventLoop\LoopInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class OfflineService
{
    private array $config;
    private Logger $logger;
    private LoopInterface $loop;
    private Client $httpClient;
    private array $manifests = [];
    private array $cachedResources = [];
    private array $syncQueue = [];
    private array $offlinePages = [];
    private array $backgroundSync = [];
    private bool $isOnline = true;
    private bool $initialized = false;
    private array $cacheStrategies = [];
    private array $offlineAnalytics = [];

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing Offline Service");
            
            // Check if Offline functionality is enabled
            if (!($this->config['enabled'] ?? true)) {
                $this->logger->info("Offline Service disabled by configuration");
                return true;
            }

            // Create storage directory if it doesn't exist
            $storagePath = $this->config['storage_path'] ?? sys_get_temp_dir() . '/prism_offline';
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            $this->initialized = true;
            $this->logger->info("Offline Service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Offline Service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function setOnlineStatus(bool $isOnline): void
    {
        $this->isOnline = $isOnline;
        $this->logger->info("Network status changed", ['online' => $isOnline]);
    }

    public function isOnline(): bool
    {
        return $this->isOnline;
    }

    public function createOfflineManifest(string $manifestId, array $resources, array $options = []): bool
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Offline Service not initialized');
        }

        try {
            $manifest = [
                'id' => $manifestId,
                'resources' => $resources,
                'version' => $options['version'] ?? '1.0.0',
                'name' => $options['name'] ?? 'Offline Manifest',
                'description' => $options['description'] ?? '',
                'fallback' => $options['fallback'] ?? null,
                'network' => $options['network'] ?? [],
                'cache' => $options['cache'] ?? [],
                'created_at' => time(),
                'updated_at' => time()
            ];

            $this->manifests[$manifestId] = $manifest;
            
            $this->logger->info("Offline manifest created", [
                'manifest_id' => $manifestId,
                'resources_count' => count($resources)
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to create offline manifest", [
                'manifest_id' => $manifestId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function cacheResource(string $url, string $content, array $headers = [], array $options = []): bool
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Offline Service not initialized');
        }

        try {
            $resourceId = md5($url);
            
            $resource = [
                'id' => $resourceId,
                'url' => $url,
                'content' => $content,
                'headers' => $headers,
                'content_type' => $headers['content-type'] ?? 'text/html',
                'content_length' => strlen($content),
                'ttl' => $options['ttl'] ?? $this->config['default_ttl'] ?? 86400,
                'cached_at' => time(),
                'expires_at' => time() + ($options['ttl'] ?? $this->config['default_ttl'] ?? 86400)
            ];

            $this->cachedResources[$resourceId] = $resource;
            
            $this->logger->debug("Resource cached for offline use", [
                'url' => $url,
                'size' => strlen($content)
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to cache resource", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getCachedResource(string $url): ?array
    {
        $resourceId = md5($url);
        
        if (!isset($this->cachedResources[$resourceId])) {
            return null;
        }

        $resource = $this->cachedResources[$resourceId];
        
        // Check if resource has expired
        if ($resource['expires_at'] < time()) {
            unset($this->cachedResources[$resourceId]);
            return null;
        }

        return $resource;
    }

    public function isResourceCached(string $url): bool
    {
        $resourceId = md5($url);
        
        if (!isset($this->cachedResources[$resourceId])) {
            return false;
        }

        // Check if resource has expired
        if ($this->cachedResources[$resourceId]['expires_at'] < time()) {
            unset($this->cachedResources[$resourceId]);
            return false;
        }

        return true;
    }

    public function getOfflineResponse(string $url, array $requestHeaders = []): ?array
    {
        if ($this->isOnline()) {
            return null; // Don't serve offline content when online
        }

        $resource = $this->getCachedResource($url);
        if (!$resource) {
            return null;
        }

        return [
            'status' => 200,
            'headers' => $resource['headers'],
            'body' => $resource['content'],
            'cached' => true,
            'cached_at' => $resource['cached_at']
        ];
    }

    public function addToSyncQueue(string $action, array $data, array $options = []): string
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Offline Service not initialized');
        }

        try {
            $queueId = 'queue_' . uniqid();
            
            $queueItem = [
                'id' => $queueId,
                'action' => $action,
                'data' => $data,
                'options' => $options,
                'status' => 'pending',
                'retry_count' => 0,
                'max_retries' => $options['max_retries'] ?? 3,
                'created_at' => time(),
                'processed_at' => null,
                'failed_at' => null
            ];

            $this->syncQueue[$queueId] = $queueItem;
            
            $this->logger->debug("Item added to sync queue", [
                'queue_id' => $queueId,
                'action' => $action
            ]);
            
            return $queueId;
        } catch (\Exception $e) {
            $this->logger->error("Failed to add item to sync queue", [
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Failed to add to sync queue: " . $e->getMessage());
        }
    }

    public function processSyncQueue(): int
    {
        if (!$this->isOnline()) {
            return 0; // Don't process queue when offline
        }

        $processedCount = 0;
        
        foreach ($this->syncQueue as $queueId => $item) {
            if ($item['status'] !== 'pending') {
                continue;
            }

            try {
                $this->processQueueItem($queueId, $item);
                $processedCount++;
            } catch (\Exception $e) {
                $this->logger->error("Failed to process sync queue item", [
                    'queue_id' => $queueId,
                    'error' => $e->getMessage()
                ]);
                
                $this->syncQueue[$queueId]['retry_count']++;
                if ($this->syncQueue[$queueId]['retry_count'] >= $item['max_retries']) {
                    $this->syncQueue[$queueId]['status'] = 'failed';
                    $this->syncQueue[$queueId]['failed_at'] = time();
                }
            }
        }
        
        if ($processedCount > 0) {
            $this->logger->info("Sync queue processed", ['processed_count' => $processedCount]);
        }
        
        return $processedCount;
    }

    public function getOfflineManifest(string $manifestId): ?array
    {
        return $this->manifests[$manifestId] ?? null;
    }

    public function updateOfflineManifest(string $manifestId, array $updates): bool
    {
        if (!isset($this->manifests[$manifestId])) {
            return false;
        }

        try {
            $this->manifests[$manifestId] = array_merge(
                $this->manifests[$manifestId],
                $updates,
                ['updated_at' => time()]
            );
            
            $this->logger->info("Offline manifest updated", ['manifest_id' => $manifestId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to update offline manifest", [
                'manifest_id' => $manifestId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function deleteOfflineManifest(string $manifestId): bool
    {
        if (!isset($this->manifests[$manifestId])) {
            return false;
        }

        try {
            unset($this->manifests[$manifestId]);
            
            $this->logger->info("Offline manifest deleted", ['manifest_id' => $manifestId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to delete offline manifest", [
                'manifest_id' => $manifestId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function clearOfflineCache(array $filters = []): int
    {
        $clearedCount = 0;
        
        foreach ($this->cachedResources as $resourceId => $resource) {
            $shouldClear = true;
            
            // Apply filters
            if (!empty($filters['url_pattern']) && !preg_match($filters['url_pattern'], $resource['url'])) {
                $shouldClear = false;
            }
            
            if (!empty($filters['content_type']) && $resource['content_type'] !== $filters['content_type']) {
                $shouldClear = false;
            }
            
            if (!empty($filters['max_age']) && (time() - $resource['cached_at']) < $filters['max_age']) {
                $shouldClear = false;
            }
            
            if ($shouldClear) {
                unset($this->cachedResources[$resourceId]);
                $clearedCount++;
            }
        }
        
        if ($clearedCount > 0) {
            $this->logger->info("Offline cache cleared", ['cleared_count' => $clearedCount]);
        }
        
        return $clearedCount;
    }

    public function getSyncQueueStatus(): array
    {
        $pendingCount = 0;
        $processedCount = 0;
        $failedCount = 0;
        
        foreach ($this->syncQueue as $item) {
            switch ($item['status']) {
                case 'pending':
                    $pendingCount++;
                    break;
                case 'processed':
                    $processedCount++;
                    break;
                case 'failed':
                    $failedCount++;
                    break;
            }
        }
        
        return [
            'total_items' => count($this->syncQueue),
            'pending_items' => $pendingCount,
            'processed_items' => $processedCount,
            'failed_items' => $failedCount,
            'is_online' => $this->isOnline()
        ];
    }

    public function getStats(): array
    {
        $totalCacheSize = 0;
        foreach ($this->cachedResources as $resource) {
            $totalCacheSize += $resource['content_length'];
        }

        return [
            'manifests_count' => count($this->manifests),
            'cached_resources_count' => count($this->cachedResources),
            'total_cache_size' => $totalCacheSize,
            'max_cache_size' => $this->config['max_cache_size'] ?? 104857600,
            'sync_queue_items' => count($this->syncQueue),
            'is_online' => $this->isOnline(),
            'max_manifests' => $this->config['max_manifests'] ?? 50,
            'max_sync_queue' => $this->config['max_sync_queue'] ?? 1000,
            'default_ttl' => $this->config['default_ttl'] ?? 86400,
            'sync_interval' => $this->config['sync_interval'] ?? 300,
            'auto_sync' => $this->config['auto_sync'] ?? true
        ];
    }

    private function processQueueItem(string $queueId, array $item): void
    {
        // Simulate processing the queue item
        $this->syncQueue[$queueId]['status'] = 'processed';
        $this->syncQueue[$queueId]['processed_at'] = time();
        
        $this->logger->debug("Sync queue item processed", [
            'queue_id' => $queueId,
            'action' => $item['action']
        ]);
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function cleanup(): void
    {
        $this->manifests = [];
        $this->cachedResources = [];
        $this->syncQueue = [];
        $this->isOnline = true;
        $this->initialized = false;
        $this->logger->info("Offline Service cleaned up");
    }
}