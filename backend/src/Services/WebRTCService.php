<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class WebRTCService
{
    private array $config;
    private Logger $logger;
    private array $connections = [];
    private array $dataChannels = [];
    private bool $initialized = false;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing WebRTC Service");
            
            // Validate configuration
            if (!isset($this->config['ice_servers']) || empty($this->config['ice_servers'])) {
                $this->logger->warning("No ICE servers configured, using defaults");
                $this->config['ice_servers'] = [
                    ['urls' => 'stun:stun.l.google.com:19302'],
                    ['urls' => 'stun:stun1.l.google.com:19302']
                ];
            }

            $this->initialized = true;
            $this->logger->info("WebRTC Service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("WebRTC Service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function createPeerConnection(string $connectionId, array $options = []): array
    {
        if (!$this->initialized) {
            throw new \RuntimeException('WebRTC Service not initialized');
        }

        try {
            $connection = [
                'id' => $connectionId,
                'state' => 'new',
                'ice_connection_state' => 'new',
                'ice_gathering_state' => 'new',
                'signaling_state' => 'stable',
                'local_description' => null,
                'remote_description' => null,
                'ice_candidates' => [],
                'data_channels' => [],
                'created_at' => time(),
                'options' => $options
            ];

            $this->connections[$connectionId] = $connection;
            
            $this->logger->info("WebRTC peer connection created", ['connection_id' => $connectionId]);
            
            return [
                'success' => true,
                'connection_id' => $connectionId,
                'state' => $connection['state']
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to create peer connection", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function createDataChannel(string $connectionId, string $channelName, array $options = []): array
    {
        if (!isset($this->connections[$connectionId])) {
            throw new \RuntimeException('Peer connection not found');
        }

        try {
            $channelId = $connectionId . '_' . $channelName;
            
            $dataChannel = [
                'id' => $channelId,
                'connection_id' => $connectionId,
                'name' => $channelName,
                'ready_state' => 'connecting',
                'buffered_amount' => 0,
                'max_packet_life_time' => $options['max_packet_life_time'] ?? null,
                'max_retransmits' => $options['max_retransmits'] ?? null,
                'ordered' => $options['ordered'] ?? true,
                'protocol' => $options['protocol'] ?? '',
                'negotiated' => $options['negotiated'] ?? false,
                'id' => $options['id'] ?? null,
                'created_at' => time()
            ];

            $this->dataChannels[$channelId] = $dataChannel;
            $this->connections[$connectionId]['data_channels'][] = $channelId;
            
            $this->logger->info("WebRTC data channel created", [
                'connection_id' => $connectionId,
                'channel_name' => $channelName,
                'channel_id' => $channelId
            ]);
            
            return [
                'success' => true,
                'channel_id' => $channelId,
                'ready_state' => $dataChannel['ready_state']
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to create data channel", [
                'connection_id' => $connectionId,
                'channel_name' => $channelName,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function setLocalDescription(string $connectionId, array $description): bool
    {
        if (!isset($this->connections[$connectionId])) {
            return false;
        }

        try {
            $this->connections[$connectionId]['local_description'] = $description;
            $this->connections[$connectionId]['signaling_state'] = 'have-local-offer';
            
            $this->logger->info("Local description set", ['connection_id' => $connectionId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to set local description", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function setRemoteDescription(string $connectionId, array $description): bool
    {
        if (!isset($this->connections[$connectionId])) {
            return false;
        }

        try {
            $this->connections[$connectionId]['remote_description'] = $description;
            $this->connections[$connectionId]['signaling_state'] = 'have-remote-offer';
            
            $this->logger->info("Remote description set", ['connection_id' => $connectionId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to set remote description", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function addIceCandidate(string $connectionId, array $candidate): bool
    {
        if (!isset($this->connections[$connectionId])) {
            return false;
        }

        try {
            $this->connections[$connectionId]['ice_candidates'][] = $candidate;
            
            $this->logger->info("ICE candidate added", ['connection_id' => $connectionId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to add ICE candidate", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendData(string $connectionId, string $channelName, string $data): bool
    {
        $channelId = $connectionId . '_' . $channelName;
        
        if (!isset($this->dataChannels[$channelId])) {
            return false;
        }

        try {
            // In a real implementation, this would send data through the actual WebRTC connection
            $this->dataChannels[$channelId]['buffered_amount'] += strlen($data);
            
            $this->logger->debug("Data sent through WebRTC channel", [
                'connection_id' => $connectionId,
                'channel_name' => $channelName,
                'data_length' => strlen($data)
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to send data", [
                'connection_id' => $connectionId,
                'channel_name' => $channelName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getConnection(string $connectionId): ?array
    {
        return $this->connections[$connectionId] ?? null;
    }

    public function getDataChannel(string $connectionId, string $channelName): ?array
    {
        $channelId = $connectionId . '_' . $channelName;
        return $this->dataChannels[$channelId] ?? null;
    }

    public function closeConnection(string $connectionId): bool
    {
        if (!isset($this->connections[$connectionId])) {
            return false;
        }

        try {
            // Close all data channels for this connection
            foreach ($this->connections[$connectionId]['data_channels'] as $channelId) {
                unset($this->dataChannels[$channelId]);
            }
            
            unset($this->connections[$connectionId]);
            
            $this->logger->info("WebRTC connection closed", ['connection_id' => $connectionId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to close connection", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getStats(string $connectionId): array
    {
        if (!isset($this->connections[$connectionId])) {
            return [];
        }

        $connection = $this->connections[$connectionId];
        $dataChannelCount = count($connection['data_channels']);
        
        return [
            'connection_id' => $connectionId,
            'state' => $connection['state'],
            'ice_connection_state' => $connection['ice_connection_state'],
            'ice_gathering_state' => $connection['ice_gathering_state'],
            'signaling_state' => $connection['signaling_state'],
            'data_channels_count' => $dataChannelCount,
            'ice_candidates_count' => count($connection['ice_candidates']),
            'created_at' => $connection['created_at'],
            'uptime' => time() - $connection['created_at']
        ];
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function cleanup(): void
    {
        $this->connections = [];
        $this->dataChannels = [];
        $this->initialized = false;
        $this->logger->info("WebRTC Service cleaned up");
    }
}