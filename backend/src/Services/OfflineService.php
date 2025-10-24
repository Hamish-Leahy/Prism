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

    public function __construct(array $config, Logger $logger, LoopInterface $loop = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->loop = $loop;
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10
        ]);
        $this->initializeCacheStrategies();
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
        $this->offlinePages = [];
        $this->backgroundSync = [];
        $this->isOnline = true;
        $this->initialized = false;
        $this->logger->info("Offline Service cleaned up");
    }

    // Enhanced offline features
    public function enableOfflineMode(): void
    {
        $this->isOnline = false;
        $this->logger->info("Offline mode enabled");
        
        // Start background sync if available
        if ($this->loop && $this->config['auto_sync'] ?? true) {
            $this->startBackgroundSync();
        }
    }

    public function disableOfflineMode(): void
    {
        $this->isOnline = true;
        $this->logger->info("Offline mode disabled");
        
        // Process any pending sync queue items
        $this->processSyncQueue();
    }

    public function cachePageForOffline(string $url, string $html, array $resources = [], array $options = []): bool
    {
        try {
            $pageId = md5($url);
            
            $offlinePage = [
                'id' => $pageId,
                'url' => $url,
                'html' => $html,
                'resources' => $resources,
                'title' => $options['title'] ?? $this->extractTitle($html),
                'description' => $options['description'] ?? $this->extractDescription($html),
                'keywords' => $options['keywords'] ?? $this->extractKeywords($html),
                'cached_at' => time(),
                'last_accessed' => time(),
                'access_count' => 0,
                'priority' => $options['priority'] ?? 'normal',
                'strategy' => $options['strategy'] ?? 'cache_first'
            ];

            $this->offlinePages[$pageId] = $offlinePage;
            
            // Cache associated resources
            foreach ($resources as $resourceUrl) {
                $this->cacheResourceFromUrl($resourceUrl);
            }

            $this->logger->info("Page cached for offline use", [
                'url' => $url,
                'resources_count' => count($resources)
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to cache page for offline use", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getOfflinePage(string $url): ?array
    {
        $pageId = md5($url);
        
        if (!isset($this->offlinePages[$pageId])) {
            return null;
        }

        $page = $this->offlinePages[$pageId];
        $page['last_accessed'] = time();
        $page['access_count']++;
        $this->offlinePages[$pageId] = $page;

        // Track analytics
        $this->trackOfflineAccess($url, $page);

        return $page;
    }

    public function searchOfflinePages(string $query): array
    {
        $results = [];
        $query = strtolower($query);

        foreach ($this->offlinePages as $page) {
            $score = 0;
            
            // Search in title
            if (strpos(strtolower($page['title']), $query) !== false) {
                $score += 10;
            }
            
            // Search in description
            if (strpos(strtolower($page['description']), $query) !== false) {
                $score += 5;
            }
            
            // Search in keywords
            if (strpos(strtolower($page['keywords']), $query) !== false) {
                $score += 3;
            }
            
            // Search in URL
            if (strpos(strtolower($page['url']), $query) !== false) {
                $score += 2;
            }

            if ($score > 0) {
                $results[] = [
                    'page' => $page,
                    'score' => $score
                ];
            }
        }

        // Sort by score (highest first)
        usort($results, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $results;
    }

    public function getOfflinePageSuggestions(string $url): array
    {
        $suggestions = [];
        $currentPage = $this->getOfflinePage($url);
        
        if (!$currentPage) {
            return $suggestions;
        }

        // Find pages with similar keywords
        $currentKeywords = explode(',', strtolower($currentPage['keywords']));
        
        foreach ($this->offlinePages as $page) {
            if ($page['id'] === $currentPage['id']) {
                continue;
            }

            $pageKeywords = explode(',', strtolower($page['keywords']));
            $commonKeywords = array_intersect($currentKeywords, $pageKeywords);
            
            if (count($commonKeywords) > 0) {
                $suggestions[] = [
                    'page' => $page,
                    'relevance' => count($commonKeywords) / max(count($currentKeywords), 1)
                ];
            }
        }

        // Sort by relevance
        usort($suggestions, function($a, $b) {
            return $b['relevance'] - $a['relevance'];
        });

        return array_slice($suggestions, 0, 5); // Return top 5 suggestions
    }

    public function registerBackgroundSync(string $name, callable $syncFunction, array $options = []): bool
    {
        $this->backgroundSync[$name] = [
            'function' => $syncFunction,
            'interval' => $options['interval'] ?? 300, // 5 minutes default
            'last_run' => 0,
            'enabled' => $options['enabled'] ?? true,
            'options' => $options
        ];

        $this->logger->info("Background sync registered", ['name' => $name]);
        return true;
    }

    public function startBackgroundSync(): void
    {
        if (!$this->loop) {
            return;
        }

        foreach ($this->backgroundSync as $name => $sync) {
            if (!$sync['enabled']) {
                continue;
            }

            $this->loop->addPeriodicTimer($sync['interval'], function() use ($name, $sync) {
                $this->runBackgroundSync($name, $sync);
            });
        }

        $this->logger->info("Background sync started");
    }

    public function runBackgroundSync(string $name, array $sync = null): void
    {
        if (!$sync) {
            $sync = $this->backgroundSync[$name] ?? null;
        }

        if (!$sync || !$sync['enabled']) {
            return;
        }

        try {
            $sync['function']($this);
            $this->backgroundSync[$name]['last_run'] = time();
            
            $this->logger->debug("Background sync completed", ['name' => $name]);
        } catch (\Exception $e) {
            $this->logger->error("Background sync failed", [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function setCacheStrategy(string $urlPattern, string $strategy): void
    {
        $this->cacheStrategies[$urlPattern] = $strategy;
        $this->logger->info("Cache strategy set", [
            'pattern' => $urlPattern,
            'strategy' => $strategy
        ]);
    }

    public function getCacheStrategy(string $url): string
    {
        foreach ($this->cacheStrategies as $pattern => $strategy) {
            if (preg_match($pattern, $url)) {
                return $strategy;
            }
        }

        return $this->config['default_strategy'] ?? 'cache_first';
    }

    public function preloadResources(array $urls, array $options = []): array
    {
        $results = [];
        $concurrency = $options['concurrency'] ?? 5;
        $urlChunks = array_chunk($urls, $concurrency);

        foreach ($urlChunks as $chunk) {
            $promises = [];
            
            foreach ($chunk as $url) {
                $promises[] = $this->cacheResourceFromUrl($url, $options);
            }

            // Wait for all promises to complete
            foreach ($promises as $promise) {
                $results[] = $promise;
            }
        }

        $this->logger->info("Resources preloaded", [
            'total_urls' => count($urls),
            'successful' => count(array_filter($results))
        ]);

        return $results;
    }

    public function getOfflineAnalytics(): array
    {
        $totalPages = count($this->offlinePages);
        $totalResources = count($this->cachedResources);
        $totalCacheSize = array_sum(array_column($this->cachedResources, 'content_length'));
        
        $mostAccessedPages = $this->offlinePages;
        usort($mostAccessedPages, function($a, $b) {
            return $b['access_count'] - $a['access_count'];
        });

        return [
            'total_offline_pages' => $totalPages,
            'total_cached_resources' => $totalResources,
            'total_cache_size' => $totalCacheSize,
            'most_accessed_pages' => array_slice($mostAccessedPages, 0, 10),
            'offline_access_stats' => $this->offlineAnalytics,
            'cache_hit_rate' => $this->calculateCacheHitRate(),
            'storage_usage' => $this->calculateStorageUsage()
        ];
    }

    public function optimizeOfflineCache(): array
    {
        $optimizations = [];
        
        // Remove expired resources
        $expiredCount = 0;
        foreach ($this->cachedResources as $id => $resource) {
            if ($resource['expires_at'] < time()) {
                unset($this->cachedResources[$id]);
                $expiredCount++;
            }
        }
        
        if ($expiredCount > 0) {
            $optimizations[] = "Removed {$expiredCount} expired resources";
        }

        // Remove least accessed pages
        $maxPages = $this->config['max_offline_pages'] ?? 100;
        if (count($this->offlinePages) > $maxPages) {
            $pages = $this->offlinePages;
            usort($pages, function($a, $b) {
                return $a['access_count'] - $b['access_count'];
            });

            $toRemove = count($this->offlinePages) - $maxPages;
            for ($i = 0; $i < $toRemove; $i++) {
                unset($this->offlinePages[$pages[$i]['id']]);
            }
            
            $optimizations[] = "Removed {$toRemove} least accessed pages";
        }

        // Compress large resources
        $compressedCount = 0;
        foreach ($this->cachedResources as $id => $resource) {
            if ($resource['content_length'] > 1024 * 1024) { // 1MB
                $compressed = gzcompress($resource['content']);
                if ($compressed !== false) {
                    $this->cachedResources[$id]['content'] = $compressed;
                    $this->cachedResources[$id]['compressed'] = true;
                    $compressedCount++;
                }
            }
        }

        if ($compressedCount > 0) {
            $optimizations[] = "Compressed {$compressedCount} large resources";
        }

        $this->logger->info("Offline cache optimized", ['optimizations' => $optimizations]);
        return $optimizations;
    }

    private function cacheResourceFromUrl(string $url, array $options = []): bool
    {
        try {
            $response = $this->httpClient->get($url);
            $content = $response->getBody()->getContents();
            $headers = $response->getHeaders();

            return $this->cacheResource($url, $content, $headers, $options);
        } catch (RequestException $e) {
            $this->logger->warning("Failed to cache resource from URL", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        return '';
    }

    private function extractDescription(string $html): string
    {
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    private function extractKeywords(string $html): string
    {
        if (preg_match('/<meta[^>]*name=["\']keywords["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    private function trackOfflineAccess(string $url, array $page): void
    {
        $this->offlineAnalytics[] = [
            'url' => $url,
            'accessed_at' => time(),
            'access_count' => $page['access_count']
        ];

        // Keep only last 1000 entries
        if (count($this->offlineAnalytics) > 1000) {
            $this->offlineAnalytics = array_slice($this->offlineAnalytics, -1000);
        }
    }

    private function calculateCacheHitRate(): float
    {
        $totalRequests = count($this->offlineAnalytics);
        if ($totalRequests === 0) {
            return 0.0;
        }

        $cacheHits = count(array_filter($this->offlineAnalytics, function($entry) {
            return isset($entry['cached']) && $entry['cached'];
        }));

        return ($cacheHits / $totalRequests) * 100;
    }

    private function calculateStorageUsage(): array
    {
        $totalSize = 0;
        $byType = [];

        foreach ($this->cachedResources as $resource) {
            $size = $resource['content_length'];
            $totalSize += $size;
            
            $type = $resource['content_type'];
            $byType[$type] = ($byType[$type] ?? 0) + $size;
        }

        return [
            'total_size' => $totalSize,
            'by_content_type' => $byType,
            'max_size' => $this->config['max_cache_size'] ?? 104857600
        ];
    }

    private function initializeCacheStrategies(): void
    {
        $this->cacheStrategies = [
            '/\.(css|js)$/' => 'cache_first',
            '/\.(png|jpg|jpeg|gif|svg|webp)$/' => 'cache_first',
            '/\.(woff|woff2|ttf|eot)$/' => 'cache_first',
            '/api\//' => 'network_first',
            '/admin\//' => 'network_only',
            '/login/' => 'network_only'
        ];
    }
}