<?php

namespace Prism\Backend\Services;

use Monolog\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use React\EventLoop\LoopInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class CloudSyncService
{
    private Logger $logger;
    private Client $httpClient;
    private LoopInterface $loop;
    private array $config;
    private array $syncQueue = [];
    private array $conflictResolution = [];
    private array $deviceRegistry = [];
    private array $syncHistory = [];
    private bool $isEnabled = false;
    private string $currentDeviceId;
    private array $encryptionKeys = [];

    public function __construct(array $config, Logger $logger, LoopInterface $loop = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->loop = $loop;
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'base_uri' => $this->config['api_endpoint'] ?? 'https://api.prism-browser.com'
        ]);
        $this->currentDeviceId = $this->generateDeviceId();
        $this->initializeEncryption();
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing Cloud Sync Service");
            
            if (!($this->config['enabled'] ?? true)) {
                $this->logger->info("Cloud Sync Service disabled by configuration");
                return true;
            }

            $this->isEnabled = true;
            $this->registerDevice();
            $this->startSyncProcess();
            
            $this->logger->info("Cloud Sync Service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Cloud Sync Service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function syncData(string $dataType, array $data, array $options = []): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        try {
            $syncItem = [
                'id' => uniqid('sync_'),
                'device_id' => $this->currentDeviceId,
                'data_type' => $dataType,
                'data' => $this->encryptData($data),
                'timestamp' => microtime(true),
                'version' => $options['version'] ?? 1,
                'priority' => $options['priority'] ?? 'normal',
                'conflict_resolution' => $options['conflict_resolution'] ?? 'last_write_wins',
                'tags' => $options['tags'] ?? [],
                'metadata' => $options['metadata'] ?? []
            ];

            $this->syncQueue[] = $syncItem;
            $this->processSyncQueue();

            $this->logger->debug("Data queued for sync", [
                'data_type' => $dataType,
                'item_id' => $syncItem['id']
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to sync data", [
                'data_type' => $dataType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getSyncedData(string $dataType, array $filters = []): array
    {
        try {
            $response = $this->httpClient->get('/api/sync/data', [
                'headers' => $this->getAuthHeaders(),
                'query' => array_merge([
                    'data_type' => $dataType,
                    'device_id' => $this->currentDeviceId
                ], $filters)
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            // Decrypt the data
            foreach ($data['items'] as &$item) {
                $item['data'] = $this->decryptData($item['data']);
            }

            return $data;
        } catch (RequestException $e) {
            $this->logger->error("Failed to get synced data", [
                'data_type' => $dataType,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function resolveConflict(string $conflictId, string $resolution): bool
    {
        try {
            $response = $this->httpClient->post('/api/sync/conflicts/' . $conflictId . '/resolve', [
                'headers' => $this->getAuthHeaders(),
                'json' => [
                    'resolution' => $resolution,
                    'device_id' => $this->currentDeviceId
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $this->logger->info("Conflict resolved", [
                    'conflict_id' => $conflictId,
                    'resolution' => $resolution
                ]);
                return true;
            }

            return false;
        } catch (RequestException $e) {
            $this->logger->error("Failed to resolve conflict", [
                'conflict_id' => $conflictId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getConflicts(): array
    {
        try {
            $response = $this->httpClient->get('/api/sync/conflicts', [
                'headers' => $this->getAuthHeaders(),
                'query' => ['device_id' => $this->currentDeviceId]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->logger->error("Failed to get conflicts", ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function registerDevice(): bool
    {
        try {
            $deviceInfo = [
                'device_id' => $this->currentDeviceId,
                'name' => $this->getDeviceName(),
                'type' => $this->getDeviceType(),
                'os' => $this->getOperatingSystem(),
                'browser_version' => $this->getBrowserVersion(),
                'capabilities' => $this->getDeviceCapabilities(),
                'last_seen' => time()
            ];

            $response = $this->httpClient->post('/api/sync/devices', [
                'headers' => $this->getAuthHeaders(),
                'json' => $deviceInfo
            ]);

            if ($response->getStatusCode() === 201) {
                $this->deviceRegistry[$this->currentDeviceId] = $deviceInfo;
                $this->logger->info("Device registered", ['device_id' => $this->currentDeviceId]);
                return true;
            }

            return false;
        } catch (RequestException $e) {
            $this->logger->error("Failed to register device", ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getRegisteredDevices(): array
    {
        try {
            $response = $this->httpClient->get('/api/sync/devices', [
                'headers' => $this->getAuthHeaders()
            ]);

            $devices = json_decode($response->getBody()->getContents(), true);
            $this->deviceRegistry = array_merge($this->deviceRegistry, $devices);
            
            return $devices;
        } catch (RequestException $e) {
            $this->logger->error("Failed to get registered devices", ['error' => $e->getMessage()]);
            return $this->deviceRegistry;
        }
    }

    public function removeDevice(string $deviceId): bool
    {
        try {
            $response = $this->httpClient->delete('/api/sync/devices/' . $deviceId, [
                'headers' => $this->getAuthHeaders()
            ]);

            if ($response->getStatusCode() === 200) {
                unset($this->deviceRegistry[$deviceId]);
                $this->logger->info("Device removed", ['device_id' => $deviceId]);
                return true;
            }

            return false;
        } catch (RequestException $e) {
            $this->logger->error("Failed to remove device", [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function enableRealTimeSync(): bool
    {
        if (!$this->loop) {
            return false;
        }

        try {
            // Set up WebSocket connection for real-time sync
            $this->loop->addPeriodicTimer(5.0, function() {
                $this->checkForUpdates();
            });

            $this->logger->info("Real-time sync enabled");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to enable real-time sync", ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function createBackup(array $dataTypes = []): string
    {
        try {
            $backupData = [
                'device_id' => $this->currentDeviceId,
                'timestamp' => time(),
                'data_types' => $dataTypes,
                'version' => '1.0'
            ];

            foreach ($dataTypes as $dataType) {
                $backupData['data'][$dataType] = $this->getSyncedData($dataType);
            }

            $backupId = uniqid('backup_');
            $encryptedBackup = $this->encryptData($backupData);

            $response = $this->httpClient->post('/api/sync/backups', [
                'headers' => $this->getAuthHeaders(),
                'json' => [
                    'backup_id' => $backupId,
                    'data' => $encryptedBackup
                ]
            ]);

            if ($response->getStatusCode() === 201) {
                $this->logger->info("Backup created", ['backup_id' => $backupId]);
                return $backupId;
            }

            return '';
        } catch (RequestException $e) {
            $this->logger->error("Failed to create backup", ['error' => $e->getMessage()]);
            return '';
        }
    }

    public function restoreBackup(string $backupId): bool
    {
        try {
            $response = $this->httpClient->get('/api/sync/backups/' . $backupId, [
                'headers' => $this->getAuthHeaders()
            ]);

            $backupData = json_decode($response->getBody()->getContents(), true);
            $decryptedData = $this->decryptData($backupData['data']);

            // Restore each data type
            foreach ($decryptedData['data'] as $dataType => $data) {
                $this->syncData($dataType, $data, ['priority' => 'high']);
            }

            $this->logger->info("Backup restored", ['backup_id' => $backupId]);
            return true;
        } catch (RequestException $e) {
            $this->logger->error("Failed to restore backup", [
                'backup_id' => $backupId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getSyncStatistics(): array
    {
        try {
            $response = $this->httpClient->get('/api/sync/statistics', [
                'headers' => $this->getAuthHeaders(),
                'query' => ['device_id' => $this->currentDeviceId]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->logger->error("Failed to get sync statistics", ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function setConflictResolutionStrategy(string $dataType, string $strategy): void
    {
        $this->conflictResolution[$dataType] = $strategy;
        $this->logger->info("Conflict resolution strategy set", [
            'data_type' => $dataType,
            'strategy' => $strategy
        ]);
    }

    public function getSyncHistory(int $limit = 100): array
    {
        return array_slice($this->syncHistory, -$limit);
    }

    private function processSyncQueue(): void
    {
        if (empty($this->syncQueue)) {
            return;
        }

        foreach ($this->syncQueue as $item) {
            $this->uploadSyncItem($item);
        }

        $this->syncQueue = [];
    }

    private function uploadSyncItem(array $item): bool
    {
        try {
            $response = $this->httpClient->post('/api/sync/upload', [
                'headers' => $this->getAuthHeaders(),
                'json' => $item
            ]);

            if ($response->getStatusCode() === 200) {
                $this->syncHistory[] = [
                    'item_id' => $item['id'],
                    'data_type' => $item['data_type'],
                    'timestamp' => $item['timestamp'],
                    'status' => 'success'
                ];
                return true;
            }

            return false;
        } catch (RequestException $e) {
            $this->logger->error("Failed to upload sync item", [
                'item_id' => $item['id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function checkForUpdates(): void
    {
        try {
            $response = $this->httpClient->get('/api/sync/updates', [
                'headers' => $this->getAuthHeaders(),
                'query' => [
                    'device_id' => $this->currentDeviceId,
                    'since' => $this->getLastSyncTime()
                ]
            ]);

            $updates = json_decode($response->getBody()->getContents(), true);
            
            foreach ($updates['items'] as $update) {
                $this->processUpdate($update);
            }

            $this->updateLastSyncTime();
        } catch (RequestException $e) {
            $this->logger->error("Failed to check for updates", ['error' => $e->getMessage()]);
        }
    }

    private function processUpdate(array $update): void
    {
        // Decrypt the update data
        $update['data'] = $this->decryptData($update['data']);
        
        // Apply conflict resolution if needed
        $strategy = $this->conflictResolution[$update['data_type']] ?? 'last_write_wins';
        
        switch ($strategy) {
            case 'last_write_wins':
                $this->applyUpdate($update);
                break;
            case 'first_write_wins':
                if ($this->isNewerUpdate($update)) {
                    $this->applyUpdate($update);
                }
                break;
            case 'manual':
                $this->createConflict($update);
                break;
        }
    }

    private function applyUpdate(array $update): void
    {
        // This would integrate with the appropriate service to apply the update
        $this->logger->debug("Update applied", [
            'data_type' => $update['data_type'],
            'update_id' => $update['id']
        ]);
    }

    private function createConflict(array $update): void
    {
        $conflictId = uniqid('conflict_');
        
        $this->logger->info("Conflict created", [
            'conflict_id' => $conflictId,
            'data_type' => $update['data_type']
        ]);
    }

    private function isNewerUpdate(array $update): bool
    {
        // Check if this update is newer than the local version
        return true; // Simplified for now
    }

    private function encryptData(array $data): string
    {
        $jsonData = json_encode($data);
        $key = $this->encryptionKeys['data_key'];
        
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($jsonData, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    private function decryptData(string $encryptedData): array
    {
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $key = $this->encryptionKeys['data_key'];
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        
        return json_decode($decrypted, true) ?? [];
    }

    private function initializeEncryption(): void
    {
        $this->encryptionKeys = [
            'data_key' => $this->config['encryption_key'] ?? hash('sha256', 'default_key'),
            'device_key' => hash('sha256', $this->currentDeviceId)
        ];
    }

    private function generateDeviceId(): string
    {
        return 'device_' . uniqid() . '_' . substr(md5(php_uname()), 0, 8);
    }

    private function getDeviceName(): string
    {
        return gethostname() ?: 'Unknown Device';
    }

    private function getDeviceType(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($userAgent, 'Mobile') !== false) {
            return 'mobile';
        } elseif (strpos($userAgent, 'Tablet') !== false) {
            return 'tablet';
        } else {
            return 'desktop';
        }
    }

    private function getOperatingSystem(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($userAgent, 'Windows') !== false) {
            return 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            return 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            return 'Linux';
        } else {
            return 'Unknown';
        }
    }

    private function getBrowserVersion(): string
    {
        return 'Prism Browser 1.0.0';
    }

    private function getDeviceCapabilities(): array
    {
        return [
            'sync_enabled' => true,
            'real_time_sync' => $this->loop !== null,
            'encryption_supported' => true,
            'conflict_resolution' => true
        ];
    }

    private function getAuthHeaders(): array
    {
        $token = $this->generateAuthToken();
        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'X-Device-ID' => $this->currentDeviceId
        ];
    }

    private function generateAuthToken(): string
    {
        $payload = [
            'device_id' => $this->currentDeviceId,
            'timestamp' => time(),
            'exp' => time() + 3600 // 1 hour
        ];

        return JWT::encode($payload, $this->config['jwt_secret'] ?? 'default_secret', 'HS256');
    }

    private function getLastSyncTime(): int
    {
        return $this->config['last_sync_time'] ?? 0;
    }

    private function updateLastSyncTime(): void
    {
        $this->config['last_sync_time'] = time();
    }

    private function startSyncProcess(): void
    {
        if (!$this->loop) {
            return;
        }

        // Process sync queue every 30 seconds
        $this->loop->addPeriodicTimer(30.0, function() {
            $this->processSyncQueue();
        });

        // Check for updates every 60 seconds
        $this->loop->addPeriodicTimer(60.0, function() {
            $this->checkForUpdates();
        });
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function cleanup(): void
    {
        $this->syncQueue = [];
        $this->conflictResolution = [];
        $this->deviceRegistry = [];
        $this->syncHistory = [];
        $this->isEnabled = false;
        $this->logger->info("Cloud Sync Service cleaned up");
    }
}
