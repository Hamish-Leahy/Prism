<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class WebRTCService
{
    private array $config;
    private Logger $logger;
    private array $connections = [];
    private array $dataChannels = [];
    private array $iceServers = [];
    private bool $initialized = false;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->iceServers = $config['ice_servers'] ?? [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:stun1.l.google.com:19302']
        ];
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing WebRTC service");
            
            // Initialize ICE servers configuration
            $this->iceServers = $this->config['ice_servers'] ?? [
                ['urls' => 'stun:stun.l.google.com:19302'],
                ['urls' => 'stun:stun1.l.google.com:19302']
            ];

            // Add TURN servers if configured
            if (isset($this->config['turn_servers'])) {
                foreach ($this->config['turn_servers'] as $turnServer) {
                    $this->iceServers[] = [
                        'urls' => $turnServer['urls'],
                        'username' => $turnServer['username'] ?? null,
                        'credential' => $turnServer['credential'] ?? null
                    ];
                }
            }

            $this->initialized = true;
            $this->logger->info("WebRTC service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("WebRTC service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function createPeerConnection(string $connectionId, array $options = []): array
    {
        if (!$this->initialized) {
            throw new \RuntimeException('WebRTC service not initialized');
        }

        try {
            $config = [
                'iceServers' => $this->iceServers,
                'iceCandidatePoolSize' => $options['ice_candidate_pool_size'] ?? 10,
                'bundlePolicy' => $options['bundle_policy'] ?? 'balanced',
                'rtcpMuxPolicy' => $options['rtcp_mux_policy'] ?? 'require',
                'iceTransportPolicy' => $options['ice_transport_policy'] ?? 'all'
            ];

            $connection = [
                'id' => $connectionId,
                'config' => $config,
                'state' => 'new',
                'iceConnectionState' => 'new',
                'iceGatheringState' => 'new',
                'signalingState' => 'stable',
                'localDescription' => null,
                'remoteDescription' => null,
                'localCandidates' => [],
                'remoteCandidates' => [],
                'dataChannels' => [],
                'created_at' => time(),
                'last_activity' => time()
            ];

            $this->connections[$connectionId] = $connection;
            $this->logger->info("Created peer connection", ['connection_id' => $connectionId]);

            return $connection;
        } catch (\Exception $e) {
            $this->logger->error("Failed to create peer connection: " . $e->getMessage());
            throw $e;
        }
    }

    public function createDataChannel(string $connectionId, string $channelName, array $options = []): array
    {
        if (!isset($this->connections[$connectionId])) {
            throw new \RuntimeException('Connection not found');
        }

        try {
            $channel = [
                'id' => $channelName,
                'connection_id' => $connectionId,
                'label' => $options['label'] ?? $channelName,
                'ordered' => $options['ordered'] ?? true,
                'maxRetransmits' => $options['max_retransmits'] ?? null,
                'maxRetransmitTime' => $options['max_retransmit_time'] ?? null,
                'protocol' => $options['protocol'] ?? '',
                'negotiated' => $options['negotiated'] ?? false,
                'id' => $options['id'] ?? null,
                'readyState' => 'connecting',
                'bufferedAmount' => 0,
                'bufferedAmountLowThreshold' => 0,
                'maxPacketLifeTime' => null,
                'maxRetransmits' => null,
                'binaryType' => 'blob',
                'created_at' => time()
            ];

            $this->dataChannels[$connectionId . '_' . $channelName] = $channel;
            $this->connections[$connectionId]['dataChannels'][] = $channelName;

            $this->logger->info("Created data channel", [
                'connection_id' => $connectionId,
                'channel_name' => $channelName
            ]);

            return $channel;
        } catch (\Exception $e) {
            $this->logger->error("Failed to create data channel: " . $e->getMessage());
            throw $e;
        }
    }

    public function setLocalDescription(string $connectionId, array $description): bool
    {
        if (!isset($this->connections[$connectionId])) {
            throw new \RuntimeException('Connection not found');
        }

        try {
            $this->connections[$connectionId]['localDescription'] = $description;
            $this->connections[$connectionId]['signalingState'] = 'have-local-offer';
            $this->connections[$connectionId]['last_activity'] = time();

            $this->logger->info("Set local description", [
                'connection_id' => $connectionId,
                'type' => $description['type'] ?? 'unknown'
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to set local description: " . $e->getMessage());
            return false;
        }
    }

    public function setRemoteDescription(string $connectionId, array $description): bool
    {
        if (!isset($this->connections[$connectionId])) {
            throw new \RuntimeException('Connection not found');
        }

        try {
            $this->connections[$connectionId]['remoteDescription'] = $description;
            
            // Update signaling state based on current state and description type
            $currentState = $this->connections[$connectionId]['signalingState'];
            $descriptionType = $description['type'] ?? '';

            if ($currentState === 'have-local-offer' && $descriptionType === 'answer') {
                $this->connections[$connectionId]['signalingState'] = 'stable';
            } elseif ($currentState === 'stable' && $descriptionType === 'offer') {
                $this->connections[$connectionId]['signalingState'] = 'have-remote-offer';
            }

            $this->connections[$connectionId]['last_activity'] = time();

            $this->logger->info("Set remote description", [
                'connection_id' => $connectionId,
                'type' => $descriptionType
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to set remote description: " . $e->getMessage());
            return false;
        }
    }

    public function addIceCandidate(string $connectionId, array $candidate): bool
    {
        if (!isset($this->connections[$connectionId])) {
            throw new \RuntimeException('Connection not found');
        }

        try {
            $candidateType = $candidate['candidate'] ?? '';
            $isRemote = $candidate['is_remote'] ?? false;

            if ($isRemote) {
                $this->connections[$connectionId]['remoteCandidates'][] = $candidate;
            } else {
                $this->connections[$connectionId]['localCandidates'][] = $candidate;
            }

            $this->connections[$connectionId]['last_activity'] = time();

            $this->logger->info("Added ICE candidate", [
                'connection_id' => $connectionId,
                'is_remote' => $isRemote,
                'candidate' => substr($candidateType, 0, 50) . '...'
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to add ICE candidate: " . $e->getMessage());
            return false;
        }
    }

    public function sendData(string $connectionId, string $channelName, string $data): bool
    {
        $channelKey = $connectionId . '_' . $channelName;
        
        if (!isset($this->dataChannels[$channelKey])) {
            throw new \RuntimeException('Data channel not found');
        }

        try {
            $channel = &$this->dataChannels[$channelKey];
            
            if ($channel['readyState'] !== 'open') {
                throw new \RuntimeException('Data channel not open');
            }

            // Simulate sending data (in a real implementation, this would interface with WebRTC)
            $channel['bufferedAmount'] += strlen($data);
            $this->connections[$connectionId]['last_activity'] = time();

            $this->logger->info("Sent data via data channel", [
                'connection_id' => $connectionId,
                'channel_name' => $channelName,
                'data_length' => strlen($data)
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to send data: " . $e->getMessage());
            return false;
        }
    }

    public function getConnection(string $connectionId): ?array
    {
        return $this->connections[$connectionId] ?? null;
    }

    public function getDataChannel(string $connectionId, string $channelName): ?array
    {
        $channelKey = $connectionId . '_' . $channelName;
        return $this->dataChannels[$channelKey] ?? null;
    }

    public function closeConnection(string $connectionId): bool
    {
        if (!isset($this->connections[$connectionId])) {
            return false;
        }

        try {
            // Close all data channels for this connection
            foreach ($this->connections[$connectionId]['dataChannels'] as $channelName) {
                $channelKey = $connectionId . '_' . $channelName;
                if (isset($this->dataChannels[$channelKey])) {
                    $this->dataChannels[$channelKey]['readyState'] = 'closed';
                    unset($this->dataChannels[$channelKey]);
                }
            }

            // Remove connection
            unset($this->connections[$connectionId]);

            $this->logger->info("Closed connection", ['connection_id' => $connectionId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to close connection: " . $e->getMessage());
            return false;
        }
    }

    public function getStats(string $connectionId): array
    {
        if (!isset($this->connections[$connectionId])) {
            return [];
        }

        $connection = $this->connections[$connectionId];
        
        return [
            'connection_id' => $connectionId,
            'state' => $connection['state'],
            'ice_connection_state' => $connection['iceConnectionState'],
            'ice_gathering_state' => $connection['iceGatheringState'],
            'signaling_state' => $connection['signalingState'],
            'data_channels_count' => count($connection['dataChannels']),
            'local_candidates_count' => count($connection['localCandidates']),
            'remote_candidates_count' => count($connection['remoteCandidates']),
            'created_at' => $connection['created_at'],
            'last_activity' => $connection['last_activity'],
            'uptime' => time() - $connection['created_at']
        ];
    }

    public function cleanup(): void
    {
        $this->connections = [];
        $this->dataChannels = [];
        $this->initialized = false;
        $this->logger->info("WebRTC service cleaned up");
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }
}
