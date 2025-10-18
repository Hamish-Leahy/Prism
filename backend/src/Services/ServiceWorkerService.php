<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class ServiceWorkerService
{
    private array $config;
    private Logger $logger;
    private array $registrations = [];
    private array $workers = [];
    private array $caches = [];
    private bool $initialized = false;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing Service Worker Service");
            
            // Check if Service Workers are enabled
            if (!($this->config['enabled'] ?? true)) {
                $this->logger->info("Service Worker Service disabled by configuration");
                return true;
            }

            // Create storage directory if it doesn't exist
            $storagePath = $this->config['storage_path'] ?? sys_get_temp_dir() . '/prism_service_workers';
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            $this->initialized = true;
            $this->logger->info("Service Worker Service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Service Worker Service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function registerServiceWorker(string $scope, string $scriptUrl, array $options = []): string
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Service Worker Service not initialized');
        }

        try {
            $registrationId = 'registration_' . uniqid();
            
            $registration = [
                'id' => $registrationId,
                'scope' => $scope,
                'script_url' => $scriptUrl,
                'state' => 'installing',
                'active_worker' => null,
                'installing_worker' => null,
                'waiting_worker' => null,
                'update_via_cache' => $options['update_via_cache'] ?? 'imports',
                'created_at' => time(),
                'updated_at' => time()
            ];

            $this->registrations[$registrationId] = $registration;
            
            $this->logger->info("Service Worker registered", [
                'registration_id' => $registrationId,
                'scope' => $scope,
                'script_url' => $scriptUrl
            ]);
            
            return $registrationId;
        } catch (\Exception $e) {
            $this->logger->error("Failed to register Service Worker", [
                'scope' => $scope,
                'script_url' => $scriptUrl,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Registration failed: " . $e->getMessage());
        }
    }

    public function createServiceWorker(string $registrationId, string $scriptUrl, array $options = []): string
    {
        if (!isset($this->registrations[$registrationId])) {
            throw new \RuntimeException('Registration not found');
        }

        try {
            $workerId = 'worker_' . uniqid();
            
            $worker = [
                'id' => $workerId,
                'registration_id' => $registrationId,
                'script_url' => $scriptUrl,
                'state' => 'installing',
                'script' => '',
                'clients' => [],
                'caches' => [],
                'skip_waiting' => false,
                'created_at' => time(),
                'updated_at' => time()
            ];

            $this->workers[$workerId] = $worker;
            $this->registrations[$registrationId]['installing_worker'] = $workerId;
            
            $this->logger->info("Service Worker created", [
                'worker_id' => $workerId,
                'registration_id' => $registrationId
            ]);
            
            return $workerId;
        } catch (\Exception $e) {
            $this->logger->error("Failed to create Service Worker", [
                'registration_id' => $registrationId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Worker creation failed: " . $e->getMessage());
        }
    }

    public function installServiceWorker(string $workerId): bool
    {
        if (!isset($this->workers[$workerId])) {
            return false;
        }

        try {
            $this->workers[$workerId]['state'] = 'installed';
            $this->workers[$workerId]['updated_at'] = time();
            
            $registrationId = $this->workers[$workerId]['registration_id'];
            $this->registrations[$registrationId]['waiting_worker'] = $workerId;
            
            $this->logger->info("Service Worker installed", ['worker_id' => $workerId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to install Service Worker", [
                'worker_id' => $workerId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function activateServiceWorker(string $workerId): bool
    {
        if (!isset($this->workers[$workerId])) {
            return false;
        }

        try {
            $this->workers[$workerId]['state'] = 'activated';
            $this->workers[$workerId]['updated_at'] = time();
            
            $registrationId = $this->workers[$workerId]['registration_id'];
            $this->registrations[$registrationId]['active_worker'] = $workerId;
            $this->registrations[$registrationId]['installing_worker'] = null;
            $this->registrations[$registrationId]['waiting_worker'] = null;
            
            $this->logger->info("Service Worker activated", ['worker_id' => $workerId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to activate Service Worker", [
                'worker_id' => $workerId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function executeServiceWorkerEvent(string $workerId, string $eventType, array $data = []): mixed
    {
        if (!isset($this->workers[$workerId])) {
            throw new \RuntimeException('Worker not found');
        }

        try {
            $this->logger->debug("Service Worker event executed", [
                'worker_id' => $workerId,
                'event_type' => $eventType
            ]);
            
            // In a real implementation, this would execute the actual Service Worker script
            // For now, we'll simulate event handling
            switch ($eventType) {
                case 'install':
                    return $this->installServiceWorker($workerId);
                case 'activate':
                    return $this->activateServiceWorker($workerId);
                case 'fetch':
                    return $this->handleFetchEvent($workerId, $data);
                case 'message':
                    return $this->handleMessageEvent($workerId, $data);
                case 'push':
                    return $this->handlePushEvent($workerId, $data);
                case 'sync':
                    return $this->handleSyncEvent($workerId, $data);
                default:
                    return null;
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to execute Service Worker event", [
                'worker_id' => $workerId,
                'event_type' => $eventType,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Event execution failed: " . $e->getMessage());
        }
    }

    public function addClient(string $workerId, string $clientId): bool
    {
        if (!isset($this->workers[$workerId])) {
            return false;
        }

        try {
            if (!in_array($clientId, $this->workers[$workerId]['clients'])) {
                $this->workers[$workerId]['clients'][] = $clientId;
                $this->workers[$workerId]['updated_at'] = time();
            }
            
            $this->logger->debug("Client added to Service Worker", [
                'worker_id' => $workerId,
                'client_id' => $clientId
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to add client to Service Worker", [
                'worker_id' => $workerId,
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function removeClient(string $workerId, string $clientId): bool
    {
        if (!isset($this->workers[$workerId])) {
            return false;
        }

        try {
            $key = array_search($clientId, $this->workers[$workerId]['clients']);
            if ($key !== false) {
                unset($this->workers[$workerId]['clients'][$key]);
                $this->workers[$workerId]['clients'] = array_values($this->workers[$workerId]['clients']);
                $this->workers[$workerId]['updated_at'] = time();
            }
            
            $this->logger->debug("Client removed from Service Worker", [
                'worker_id' => $workerId,
                'client_id' => $clientId
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to remove client from Service Worker", [
                'worker_id' => $workerId,
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function createCache(string $cacheName, array $options = []): string
    {
        try {
            $cacheId = 'cache_' . uniqid();
            
            $cache = [
                'id' => $cacheId,
                'name' => $cacheName,
                'entries' => [],
                'max_size' => $options['max_size'] ?? 104857600, // 100MB
                'created_at' => time(),
                'updated_at' => time()
            ];

            $this->caches[$cacheId] = $cache;
            
            $this->logger->info("Service Worker cache created", [
                'cache_id' => $cacheId,
                'cache_name' => $cacheName
            ]);
            
            return $cacheId;
        } catch (\Exception $e) {
            $this->logger->error("Failed to create Service Worker cache", [
                'cache_name' => $cacheName,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Cache creation failed: " . $e->getMessage());
        }
    }

    public function addToCache(string $cacheId, string $request, string $response, array $options = []): bool
    {
        if (!isset($this->caches[$cacheId])) {
            return false;
        }

        try {
            $entry = [
                'request' => $request,
                'response' => $response,
                'headers' => $options['headers'] ?? [],
                'status' => $options['status'] ?? 200,
                'status_text' => $options['status_text'] ?? 'OK',
                'cached_at' => time()
            ];

            $this->caches[$cacheId]['entries'][$request] = $entry;
            $this->caches[$cacheId]['updated_at'] = time();
            
            $this->logger->debug("Entry added to Service Worker cache", [
                'cache_id' => $cacheId,
                'request' => $request
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to add entry to Service Worker cache", [
                'cache_id' => $cacheId,
                'request' => $request,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getFromCache(string $cacheId, string $request): ?array
    {
        if (!isset($this->caches[$cacheId])) {
            return null;
        }

        return $this->caches[$cacheId]['entries'][$request] ?? null;
    }

    public function deleteFromCache(string $cacheId, string $request): bool
    {
        if (!isset($this->caches[$cacheId])) {
            return false;
        }

        try {
            if (isset($this->caches[$cacheId]['entries'][$request])) {
                unset($this->caches[$cacheId]['entries'][$request]);
                $this->caches[$cacheId]['updated_at'] = time();
            }
            
            $this->logger->debug("Entry deleted from Service Worker cache", [
                'cache_id' => $cacheId,
                'request' => $request
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to delete entry from Service Worker cache", [
                'cache_id' => $cacheId,
                'request' => $request,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getRegistration(string $registrationId): ?array
    {
        return $this->registrations[$registrationId] ?? null;
    }

    public function getWorker(string $workerId): ?array
    {
        return $this->workers[$workerId] ?? null;
    }

    public function getCache(string $cacheId): ?array
    {
        return $this->caches[$cacheId] ?? null;
    }

    public function unregisterServiceWorker(string $registrationId): bool
    {
        if (!isset($this->registrations[$registrationId])) {
            return false;
        }

        try {
            // Close all workers for this registration
            foreach ($this->workers as $workerId => $worker) {
                if ($worker['registration_id'] === $registrationId) {
                    unset($this->workers[$workerId]);
                }
            }
            
            unset($this->registrations[$registrationId]);
            
            $this->logger->info("Service Worker unregistered", ['registration_id' => $registrationId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to unregister Service Worker", [
                'registration_id' => $registrationId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getStats(): array
    {
        $activeWorkers = array_filter($this->workers, function($worker) {
            return $worker['state'] === 'activated';
        });

        return [
            'registrations_count' => count($this->registrations),
            'workers_count' => count($this->workers),
            'active_workers_count' => count($activeWorkers),
            'caches_count' => count($this->caches),
            'max_workers' => $this->config['max_workers'] ?? 10,
            'max_caches' => $this->config['max_caches'] ?? 50,
            'cache_size_limit' => $this->config['cache_size_limit'] ?? 104857600
        ];
    }

    private function handleFetchEvent(string $workerId, array $data): array
    {
        // Simulate fetch event handling
        return [
            'success' => true,
            'response' => 'Simulated response from Service Worker'
        ];
    }

    private function handleMessageEvent(string $workerId, array $data): array
    {
        // Simulate message event handling
        return [
            'success' => true,
            'message' => 'Message processed by Service Worker'
        ];
    }

    private function handlePushEvent(string $workerId, array $data): array
    {
        // Simulate push event handling
        return [
            'success' => true,
            'notification' => 'Push notification processed by Service Worker'
        ];
    }

    private function handleSyncEvent(string $workerId, array $data): array
    {
        // Simulate sync event handling
        return [
            'success' => true,
            'sync' => 'Background sync processed by Service Worker'
        ];
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function cleanup(): void
    {
        $this->registrations = [];
        $this->workers = [];
        $this->caches = [];
        $this->initialized = false;
        $this->logger->info("Service Worker Service cleaned up");
    }
}