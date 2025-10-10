<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class CacheService
{
    private array $config;
    private Logger $logger;
    private array $cache = [];
    private string $cachePath;
    private int $maxMemorySize;
    private int $maxDiskSize;
    private int $defaultTtl;
    private bool $persistent;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->cachePath = $config['cache_path'] ?? sys_get_temp_dir() . '/prism_cache';
        $this->maxMemorySize = $config['max_memory_size'] ?? 67108864; // 64MB
        $this->maxDiskSize = $config['max_disk_size'] ?? 1073741824; // 1GB
        $this->defaultTtl = $config['default_ttl'] ?? 3600; // 1 hour
        $this->persistent = $config['persistent'] ?? true;
        
        $this->initializeCache();
    }

    /**
     * Initialize cache directory and load existing cache
     */
    private function initializeCache(): void
    {
        try {
            if (!is_dir($this->cachePath)) {
                mkdir($this->cachePath, 0755, true);
            }

            if ($this->persistent) {
                $this->loadCacheFromDisk();
            }

            $this->logger->info("Cache service initialized", [
                'cache_path' => $this->cachePath,
                'max_memory_size' => $this->maxMemorySize,
                'max_disk_size' => $this->maxDiskSize,
                'persistent' => $this->persistent
            ]);
        } catch (\Exception $e) {
            $this->logger->error("Cache initialization failed: " . $e->getMessage());
        }
    }

    /**
     * Load cache from disk
     */
    private function loadCacheFromDisk(): void
    {
        try {
            $indexFile = $this->cachePath . '/index.json';
            if (file_exists($indexFile)) {
                $index = json_decode(file_get_contents($indexFile), true);
                if (is_array($index)) {
                    foreach ($index as $key => $metadata) {
                        if ($this->isExpired($metadata)) {
                            $this->removeFromDisk($key);
                        } else {
                            $this->cache[$key] = $metadata;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to load cache from disk: " . $e->getMessage());
        }
    }

    /**
     * Save cache index to disk
     */
    private function saveCacheIndex(): void
    {
        try {
            $indexFile = $this->cachePath . '/index.json';
            $index = [];
            
            foreach ($this->cache as $key => $metadata) {
                $index[$key] = $metadata;
            }
            
            file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT), LOCK_EX);
        } catch (\Exception $e) {
            $this->logger->error("Failed to save cache index: " . $e->getMessage());
        }
    }

    /**
     * Store data in cache
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        try {
            $ttl = $ttl ?? $this->defaultTtl;
            $expiresAt = time() + $ttl;
            
            // Serialize value
            $serializedValue = serialize($value);
            $size = strlen($serializedValue);
            
            // Check if we need to evict items
            $this->evictIfNeeded($size);
            
            $metadata = [
                'key' => $key,
                'size' => $size,
                'created_at' => time(),
                'expires_at' => $expiresAt,
                'access_count' => 0,
                'last_accessed' => time(),
                'type' => gettype($value)
            ];
            
            // Store in memory
            $this->cache[$key] = $metadata;
            
            // Store data to disk if persistent
            if ($this->persistent) {
                $this->saveToDisk($key, $serializedValue);
                $this->saveCacheIndex();
            }
            
            $this->logger->debug("Cache item stored", [
                'key' => $key,
                'size' => $size,
                'ttl' => $ttl,
                'expires_at' => $expiresAt
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to store cache item", [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Retrieve data from cache
     */
    public function get(string $key): mixed
    {
        try {
            if (!isset($this->cache[$key])) {
                return null;
            }
            
            $metadata = $this->cache[$key];
            
            // Check if expired
            if ($this->isExpired($metadata)) {
                $this->delete($key);
                return null;
            }
            
            // Update access statistics
            $this->cache[$key]['access_count']++;
            $this->cache[$key]['last_accessed'] = time();
            
            // Load from disk if persistent
            if ($this->persistent) {
                $dataFile = $this->cachePath . '/' . md5($key) . '.cache';
                if (file_exists($dataFile)) {
                    $serializedValue = file_get_contents($dataFile);
                    $value = unserialize($serializedValue);
                    
                    $this->logger->debug("Cache item retrieved", [
                        'key' => $key,
                        'access_count' => $this->cache[$key]['access_count']
                    ]);
                    
                    return $value;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->error("Failed to retrieve cache item", [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if cache item exists and is not expired
     */
    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }
        
        $metadata = $this->cache[$key];
        
        if ($this->isExpired($metadata)) {
            $this->delete($key);
            return false;
        }
        
        return true;
    }

    /**
     * Delete cache item
     */
    public function delete(string $key): bool
    {
        try {
            if (isset($this->cache[$key])) {
                unset($this->cache[$key]);
                
                if ($this->persistent) {
                    $this->removeFromDisk($key);
                    $this->saveCacheIndex();
                }
                
                $this->logger->debug("Cache item deleted", ['key' => $key]);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error("Failed to delete cache item", [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Clear all cache items
     */
    public function clear(): bool
    {
        try {
            $this->cache = [];
            
            if ($this->persistent) {
                $files = glob($this->cachePath . '/*.cache');
                foreach ($files as $file) {
                    unlink($file);
                }
                
                $indexFile = $this->cachePath . '/index.json';
                if (file_exists($indexFile)) {
                    unlink($indexFile);
                }
            }
            
            $this->logger->info("Cache cleared");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to clear cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $totalSize = 0;
        $itemCount = count($this->cache);
        $expiredCount = 0;
        
        foreach ($this->cache as $metadata) {
            $totalSize += $metadata['size'];
            if ($this->isExpired($metadata)) {
                $expiredCount++;
            }
        }
        
        return [
            'item_count' => $itemCount,
            'total_size' => $totalSize,
            'expired_count' => $expiredCount,
            'memory_usage' => memory_get_usage(true),
            'max_memory_size' => $this->maxMemorySize,
            'max_disk_size' => $this->maxDiskSize,
            'persistent' => $this->persistent,
            'cache_path' => $this->cachePath
        ];
    }

    /**
     * Get cache item metadata
     */
    public function getMetadata(string $key): ?array
    {
        if (!isset($this->cache[$key])) {
            return null;
        }
        
        $metadata = $this->cache[$key];
        
        if ($this->isExpired($metadata)) {
            $this->delete($key);
            return null;
        }
        
        return $metadata;
    }

    /**
     * Clean up expired items
     */
    public function cleanup(): int
    {
        $cleanedCount = 0;
        
        foreach ($this->cache as $key => $metadata) {
            if ($this->isExpired($metadata)) {
                $this->delete($key);
                $cleanedCount++;
            }
        }
        
        if ($cleanedCount > 0) {
            $this->logger->info("Cache cleanup completed", ['cleaned_items' => $cleanedCount]);
        }
        
        return $cleanedCount;
    }

    /**
     * Check if metadata indicates expired item
     */
    private function isExpired(array $metadata): bool
    {
        return time() > $metadata['expires_at'];
    }

    /**
     * Save data to disk
     */
    private function saveToDisk(string $key, string $data): void
    {
        $dataFile = $this->cachePath . '/' . md5($key) . '.cache';
        file_put_contents($dataFile, $data, LOCK_EX);
    }

    /**
     * Remove data from disk
     */
    private function removeFromDisk(string $key): void
    {
        $dataFile = $this->cachePath . '/' . md5($key) . '.cache';
        if (file_exists($dataFile)) {
            unlink($dataFile);
        }
    }

    /**
     * Evict items if cache is full
     */
    private function evictIfNeeded(int $newItemSize): void
    {
        $currentSize = $this->getCurrentMemorySize();
        
        if ($currentSize + $newItemSize > $this->maxMemorySize) {
            // Sort by last accessed time (LRU)
            uasort($this->cache, function($a, $b) {
                return $a['last_accessed'] - $b['last_accessed'];
            });
            
            // Remove oldest items until we have enough space
            $targetSize = $this->maxMemorySize - $newItemSize;
            $currentSize = $this->getCurrentMemorySize();
            
            foreach ($this->cache as $key => $metadata) {
                if ($currentSize <= $targetSize) {
                    break;
                }
                
                $this->delete($key);
                $currentSize -= $metadata['size'];
            }
        }
    }

    /**
     * Get current memory usage
     */
    private function getCurrentMemorySize(): int
    {
        $size = 0;
        foreach ($this->cache as $metadata) {
            $size += $metadata['size'];
        }
        return $size;
    }

    /**
     * Set cache configuration
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        
        if (isset($config['max_memory_size'])) {
            $this->maxMemorySize = $config['max_memory_size'];
        }
        
        if (isset($config['max_disk_size'])) {
            $this->maxDiskSize = $config['max_disk_size'];
        }
        
        if (isset($config['default_ttl'])) {
            $this->defaultTtl = $config['default_ttl'];
        }
    }

    /**
     * Get cache configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Close cache service
     */
    public function close(): void
    {
        if ($this->persistent) {
            $this->saveCacheIndex();
        }
        
        $this->cache = [];
        $this->logger->info("Cache service closed");
    }
}
