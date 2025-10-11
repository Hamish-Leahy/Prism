<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class ServiceWorkerService
{
    private array $config;
    private Logger $logger;
    private array $workers = [];
    private array $registrations = [];
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
            $this->logger->info("Initializing Service Worker service");
            
            // Create service worker storage directory
            $storagePath = $this->config['storage_path'] ?? sys_get_temp_dir() . '/prism_service_workers';
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            $this->initialized = true;
            $this->logger->info("Service Worker service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Service Worker service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function registerServiceWorker(string $scope, string $scriptUrl, array $options = []): string
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Service Worker service not initialized');
        }

        try {
            $registrationId = 'sw_' . uniqid();
            
            $registration = [
                'id' => $registrationId,
                'scope' => $scope,
                'script_url' => $scriptUrl,
                'state' => 'installing',
                'active_worker' => null,
                'waiting_worker' => null,
                'installing_worker' => null,
                'update_via_cache' => $options['update_via_cache'] ?? 'imports',
                'created_at' => time(),
                'last_updated' => time()
            ];

            $this->registrations[$registrationId] = $registration;

            // Create the service worker
            $workerId = $this->createServiceWorker($registrationId, $scriptUrl, $options);
            $this->registrations[$registrationId]['installing_worker'] = $workerId;

            $this->logger->info("Registered service worker", [
                'registration_id' => $registrationId,
                'scope' => $scope,
                'script_url' => $scriptUrl
            ]);

            return $registrationId;
        } catch (\Exception $e) {
            $this->logger->error("Failed to register service worker: " . $e->getMessage());
            throw $e;
        }
    }

    public function createServiceWorker(string $registrationId, string $scriptUrl, array $options = []): string
    {
        try {
            $workerId = 'worker_' . uniqid();
            
            $worker = [
                'id' => $workerId,
                'registration_id' => $registrationId,
                'script_url' => $scriptUrl,
                'state' => 'installing',
                'script_source' => $this->fetchScriptSource($scriptUrl),
                'skip_waiting' => $options['skip_waiting'] ?? false,
                'clients' => [],
                'events' => [],
                'created_at' => time(),
                'last_activity' => time()
            ];

            $this->workers[$workerId] = $worker;

            // Simulate installation process
            $this->installServiceWorker($workerId);

            $this->logger->info("Created service worker", [
                'worker_id' => $workerId,
                'registration_id' => $registrationId
            ]);

            return $workerId;
        } catch (\Exception $e) {
            $this->logger->error("Failed to create service worker: " . $e->getMessage());
            throw $e;
        }
    }

    public function installServiceWorker(string $workerId): bool
    {
        if (!isset($this->workers[$workerId])) {
            throw new \RuntimeException('Service worker not found');
        }

        try {
            $worker = &$this->workers[$workerId];
            $worker['state'] = 'installing';
            $worker['last_activity'] = time();

            // Simulate installation process
            // In a real implementation, this would execute the service worker script
            $this->executeServiceWorkerEvent($workerId, 'install', []);

            $worker['state'] = 'installed';
            
            // Move to waiting state if there's already an active worker
            $registration = $this->registrations[$worker['registration_id']];
            if ($registration['active_worker']) {
                $worker['state'] = 'waiting';
                $this->registrations[$worker['registration_id']]['waiting_worker'] = $workerId;
            } else {
                $worker['state'] = 'activating';
                $this->activateServiceWorker($workerId);
            }

            $this->logger->info("Installed service worker", ['worker_id' => $workerId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to install service worker: " . $e->getMessage());
            return false;
        }
    }

    public function activateServiceWorker(string $workerId): bool
    {
        if (!isset($this->workers[$workerId])) {
            throw new \RuntimeException('Service worker not found');
        }

        try {
            $worker = &$this->workers[$workerId];
            $worker['state'] = 'activating';
            $worker['last_activity'] = time();

            // Simulate activation process
            $this->executeServiceWorkerEvent($workerId, 'activate', []);

            $worker['state'] = 'activated';
            
            // Update registration
            $registrationId = $worker['registration_id'];
            $this->registrations[$registrationId]['active_worker'] = $workerId;
            $this->registrations[$registrationId]['installing_worker'] = null;
            $this->registrations[$registrationId]['waiting_worker'] = null;
            $this->registrations[$registrationId]['state'] = 'activated';
            $this->registrations[$registrationId]['last_updated'] = time();

            $this->logger->info("Activated service worker", ['worker_id' => $workerId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to activate service worker: " . $e->getMessage());
            return false;
        }
    }

    public function executeServiceWorkerEvent(string $workerId, string $eventType, array $data = []): mixed
    {
        if (!isset($this->workers[$workerId])) {
            throw new \RuntimeException('Service worker not found');
        }

        $worker = &$this->workers[$workerId];
        $worker['last_activity'] = time();

        // Record the event
        $eventId = 'event_' . uniqid();
        $event = [
            'id' => $eventId,
            'type' => $eventType,
            'data' => $data,
            'timestamp' => time(),
            'processed' => false
        ];

        $worker['events'][] = $event;

        // Simulate event processing
        $result = $this->processServiceWorkerEvent($workerId, $eventType, $data);
        
        $event['processed'] = true;
        $event['result'] = $result;

        $this->logger->info("Executed service worker event", [
            'worker_id' => $workerId,
            'event_type' => $eventType,
            'event_id' => $eventId
        ]);

        return $result;
    }

    public function addClient(string $workerId, string $clientId): bool
    {
        if (!isset($this->workers[$workerId])) {
            throw new \RuntimeException('Service worker not found');
        }

        $worker = &$this->workers[$workerId];
        
        if (!in_array($clientId, $worker['clients'])) {
            $worker['clients'][] = $clientId;
            $worker['last_activity'] = time();
        }

        return true;
    }

    public function removeClient(string $workerId, string $clientId): bool
    {
        if (!isset($this->workers[$workerId])) {
            throw new \RuntimeException('Service worker not found');
        }

        $worker = &$this->workers[$workerId];
        $worker['clients'] = array_filter($worker['clients'], fn($id) => $id !== $clientId);
        $worker['last_activity'] = time();

        return true;
    }

    public function createCache(string $cacheName, array $options = []): string
    {
        try {
            $cacheId = 'cache_' . uniqid();
            
            $cache = [
                'id' => $cacheId,
                'name' => $cacheName,
                'entries' => [],
                'max_size' => $options['max_size'] ?? 10485760, // 10MB
                'max_entries' => $options['max_entries'] ?? 1000,
                'created_at' => time(),
                'last_accessed' => time()
            ];

            $this->caches[$cacheId] = $cache;

            $this->logger->info("Created cache", [
                'cache_id' => $cacheId,
                'cache_name' => $cacheName
            ]);

            return $cacheId;
        } catch (\Exception $e) {
            $this->logger->error("Failed to create cache: " . $e->getMessage());
            throw $e;
        }
    }

    public function addToCache(string $cacheId, string $request, string $response, array $options = []): bool
    {
        if (!isset($this->caches[$cacheId])) {
            throw new \RuntimeException('Cache not found');
        }

        $cache = &$this->caches[$cacheId];
        
        // Check size limits
        if (count($cache['entries']) >= $cache['max_entries']) {
            // Remove oldest entry
            $oldestKey = array_key_first($cache['entries']);
            unset($cache['entries'][$oldestKey]);
        }

        $entry = [
            'request' => $request,
            'response' => $response,
            'headers' => $options['headers'] ?? [],
            'timestamp' => time(),
            'size' => strlen($response)
        ];

        $cache['entries'][$request] = $entry;
        $cache['last_accessed'] = time();

        return true;
    }

    public function getFromCache(string $cacheId, string $request): ?array
    {
        if (!isset($this->caches[$cacheId])) {
            return null;
        }

        $cache = &$this->caches[$cacheId];
        $cache['last_accessed'] = time();

        return $cache['entries'][$request] ?? null;
    }

    public function deleteFromCache(string $cacheId, string $request): bool
    {
        if (!isset($this->caches[$cacheId])) {
            return false;
        }

        $cache = &$this->caches[$cacheId];
        unset($cache['entries'][$request]);
        $cache['last_accessed'] = time();

        return true;
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

        $registration = $this->registrations[$registrationId];
        
        // Terminate all workers for this registration
        foreach ($this->workers as $workerId => $worker) {
            if ($worker['registration_id'] === $registrationId) {
                unset($this->workers[$workerId]);
            }
        }

        unset($this->registrations[$registrationId]);

        $this->logger->info("Unregistered service worker", ['registration_id' => $registrationId]);
        return true;
    }

    public function getStats(): array
    {
        return [
            'registrations_count' => count($this->registrations),
            'workers_count' => count($this->workers),
            'caches_count' => count($this->caches),
            'active_workers' => count(array_filter($this->workers, fn($w) => $w['state'] === 'activated')),
            'waiting_workers' => count(array_filter($this->workers, fn($w) => $w['state'] === 'waiting')),
            'installing_workers' => count(array_filter($this->workers, fn($w) => $w['state'] === 'installing')),
            'initialized' => $this->initialized
        ];
    }

    private function fetchScriptSource(string $scriptUrl): string
    {
        // In a real implementation, this would fetch the actual script
        // For now, return a mock service worker script
        return "
// Mock Service Worker Script
self.addEventListener('install', function(event) {
    console.log('Service Worker installing');
    event.waitUntil(
        caches.open('v1').then(function(cache) {
            return cache.addAll(['/']);
        })
    );
});

self.addEventListener('activate', function(event) {
    console.log('Service Worker activating');
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.map(function(cacheName) {
                    if (cacheName !== 'v1') {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

self.addEventListener('fetch', function(event) {
    event.respondWith(
        caches.match(event.request).then(function(response) {
            return response || fetch(event.request);
        })
    );
});
";
    }

    private function processServiceWorkerEvent(string $workerId, string $eventType, array $data): mixed
    {
        // Mock event processing
        switch ($eventType) {
            case 'install':
                return ['status' => 'installed', 'timestamp' => time()];
            case 'activate':
                return ['status' => 'activated', 'timestamp' => time()];
            case 'fetch':
                return ['status' => 'fetched', 'url' => $data['url'] ?? 'unknown'];
            case 'message':
                return ['status' => 'message_received', 'data' => $data];
            default:
                return ['status' => 'processed', 'event_type' => $eventType];
        }
    }

    public function cleanup(): void
    {
        $this->workers = [];
        $this->registrations = [];
        $this->caches = [];
        $this->initialized = false;
        $this->logger->info("Service Worker service cleaned up");
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }
}
