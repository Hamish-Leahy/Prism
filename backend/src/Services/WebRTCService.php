<?php

namespace Prism\Backend\Services;

use Monolog\Logger;
use React\EventLoop\LoopInterface;
use React\Socket\Server as SocketServer;
use React\Stream\WritableResourceStream;

class WebRTCService
{
    private Logger $logger;
    private LoopInterface $loop;
    private array $config;
    private array $activeConnections = [];
    private array $signalingServers = [];
    private array $iceServers = [];
    private array $mediaStreams = [];
    private array $dataChannels = [];
    private bool $isEnabled = false;
    private array $connectionStats = [];
    private array $mediaConstraints = [];

    public function __construct(array $config, Logger $logger, LoopInterface $loop = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->loop = $loop;
        $this->initializeIceServers();
        $this->initializeMediaConstraints();
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing WebRTC Service");
            
            if (!($this->config['enabled'] ?? true)) {
                $this->logger->info("WebRTC Service disabled by configuration");
                return true;
            }

            $this->isEnabled = true;
            $this->startSignalingServer();
            $this->startStatsCollection();
            
            $this->logger->info("WebRTC Service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("WebRTC Service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function createPeerConnection(string $connectionId, array $options = []): array
    {
        if (!$this->isEnabled) {
            return ['error' => 'WebRTC service is disabled'];
        }

        try {
            $peerConnection = [
                'id' => $connectionId,
                'state' => 'new',
                'ice_connection_state' => 'new',
                'ice_gathering_state' => 'new',
                'signaling_state' => 'stable',
                'local_description' => null,
                'remote_description' => null,
                'ice_candidates' => [],
                'data_channels' => [],
                'media_streams' => [],
                'created_at' => microtime(true),
                'options' => $options
            ];

            $this->activeConnections[$connectionId] = $peerConnection;

            $this->logger->info("Peer connection created", ['connection_id' => $connectionId]);
            
            return [
                'connection_id' => $connectionId,
                'ice_servers' => $this->iceServers,
                'media_constraints' => $this->mediaConstraints
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to create peer connection", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to create peer connection'];
        }
    }

    public function handleOffer(string $connectionId, array $offer): array
    {
        if (!isset($this->activeConnections[$connectionId])) {
            return ['error' => 'Connection not found'];
        }

        try {
            $connection = &$this->activeConnections[$connectionId];
            $connection['remote_description'] = $offer;
            $connection['signaling_state'] = 'have-remote-offer';

            // Generate answer
            $answer = $this->generateAnswer($connectionId, $offer);
            $connection['local_description'] = $answer;
            $connection['signaling_state'] = 'stable';

            $this->logger->info("Offer handled", [
                'connection_id' => $connectionId,
                'offer_type' => $offer['type'] ?? 'unknown'
            ]);

            return [
                'answer' => $answer,
                'ice_candidates' => $connection['ice_candidates']
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to handle offer", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to handle offer'];
        }
    }

    public function handleAnswer(string $connectionId, array $answer): bool
    {
        if (!isset($this->activeConnections[$connectionId])) {
            return false;
        }

        try {
            $connection = &$this->activeConnections[$connectionId];
            $connection['remote_description'] = $answer;
            $connection['signaling_state'] = 'stable';

            $this->logger->info("Answer handled", ['connection_id' => $connectionId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to handle answer", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function addIceCandidate(string $connectionId, array $candidate): bool
    {
        if (!isset($this->activeConnections[$connectionId])) {
            return false;
        }

        try {
            $connection = &$this->activeConnections[$connectionId];
            $connection['ice_candidates'][] = $candidate;

            $this->logger->debug("ICE candidate added", [
                'connection_id' => $connectionId,
                'candidate' => $candidate['candidate'] ?? 'unknown'
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to add ICE candidate", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function createDataChannel(string $connectionId, string $channelName, array $options = []): array
    {
        if (!isset($this->activeConnections[$connectionId])) {
            return ['error' => 'Connection not found'];
        }

        try {
            $dataChannel = [
                'id' => uniqid('channel_'),
                'name' => $channelName,
                'connection_id' => $connectionId,
                'state' => 'connecting',
                'ready_state' => 'connecting',
                'options' => $options,
                'created_at' => microtime(true)
            ];

            $this->dataChannels[$dataChannel['id']] = $dataChannel;
            $this->activeConnections[$connectionId]['data_channels'][] = $dataChannel['id'];

            $this->logger->info("Data channel created", [
                'connection_id' => $connectionId,
                'channel_name' => $channelName
            ]);

            return [
                'channel_id' => $dataChannel['id'],
                'channel_name' => $channelName,
                'state' => $dataChannel['state']
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to create data channel", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to create data channel'];
        }
    }

    public function sendData(string $channelId, string $data, string $type = 'text'): bool
    {
        if (!isset($this->dataChannels[$channelId])) {
            return false;
        }

        try {
            $channel = &$this->dataChannels[$channelId];
            
            if ($channel['ready_state'] !== 'open') {
                $this->logger->warning("Data channel not ready", ['channel_id' => $channelId]);
                return false;
            }

            // Simulate data sending
            $this->logger->debug("Data sent", [
                'channel_id' => $channelId,
                'data_length' => strlen($data),
                'type' => $type
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to send data", [
                'channel_id' => $channelId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getMediaStream(string $connectionId, array $constraints = []): array
    {
        if (!isset($this->activeConnections[$connectionId])) {
            return ['error' => 'Connection not found'];
        }

        try {
            $streamId = uniqid('stream_');
            $mediaStream = [
                'id' => $streamId,
                'connection_id' => $connectionId,
                'constraints' => array_merge($this->mediaConstraints, $constraints),
                'tracks' => [],
                'active' => true,
                'created_at' => microtime(true)
            ];

            $this->mediaStreams[$streamId] = $mediaStream;
            $this->activeConnections[$connectionId]['media_streams'][] = $streamId;

            $this->logger->info("Media stream created", [
                'connection_id' => $connectionId,
                'stream_id' => $streamId
            ]);

            return [
                'stream_id' => $streamId,
                'constraints' => $mediaStream['constraints']
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to get media stream", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to get media stream'];
        }
    }

    public function startScreenShare(string $connectionId, array $options = []): array
    {
        if (!isset($this->activeConnections[$connectionId])) {
            return ['error' => 'Connection not found'];
        }

        try {
            $screenShareId = uniqid('screenshare_');
            $screenShare = [
                'id' => $screenShareId,
                'connection_id' => $connectionId,
                'type' => 'screen',
                'options' => $options,
                'active' => true,
                'started_at' => microtime(true)
            ];

            $this->logger->info("Screen share started", [
                'connection_id' => $connectionId,
                'screen_share_id' => $screenShareId
            ]);

            return [
                'screen_share_id' => $screenShareId,
                'status' => 'active'
            ];
        } catch (\Exception $e) {
            $this->logger->error("Failed to start screen share", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to start screen share'];
        }
    }

    public function stopScreenShare(string $screenShareId): bool
    {
        try {
            $this->logger->info("Screen share stopped", ['screen_share_id' => $screenShareId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to stop screen share", [
                'screen_share_id' => $screenShareId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getConnectionStats(string $connectionId): array
    {
        if (!isset($this->activeConnections[$connectionId])) {
            return ['error' => 'Connection not found'];
        }

        $connection = $this->activeConnections[$connectionId];
        
        return [
            'connection_id' => $connectionId,
            'state' => $connection['state'],
            'ice_connection_state' => $connection['ice_connection_state'],
            'ice_gathering_state' => $connection['ice_gathering_state'],
            'signaling_state' => $connection['signaling_state'],
            'data_channels_count' => count($connection['data_channels']),
            'media_streams_count' => count($connection['media_streams']),
            'uptime' => microtime(true) - $connection['created_at'],
            'stats' => $this->connectionStats[$connectionId] ?? []
        ];
    }

    public function getAllConnections(): array
    {
        return array_keys($this->activeConnections);
    }

    public function closeConnection(string $connectionId): bool
    {
        if (!isset($this->activeConnections[$connectionId])) {
            return false;
        }

        try {
            // Close all data channels for this connection
            foreach ($this->activeConnections[$connectionId]['data_channels'] as $channelId) {
                if (isset($this->dataChannels[$channelId])) {
                    unset($this->dataChannels[$channelId]);
                }
            }

            // Close all media streams for this connection
            foreach ($this->activeConnections[$connectionId]['media_streams'] as $streamId) {
                if (isset($this->mediaStreams[$streamId])) {
                    unset($this->mediaStreams[$streamId]);
                }
            }

            unset($this->activeConnections[$connectionId]);

            $this->logger->info("Connection closed", ['connection_id' => $connectionId]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to close connection", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getWebRTCStats(): array
    {
        $totalConnections = count($this->activeConnections);
        $totalDataChannels = count($this->dataChannels);
        $totalMediaStreams = count($this->mediaStreams);

        $activeConnections = 0;
        foreach ($this->activeConnections as $connection) {
            if ($connection['state'] === 'connected') {
                $activeConnections++;
            }
        }

        return [
            'total_connections' => $totalConnections,
            'active_connections' => $activeConnections,
            'total_data_channels' => $totalDataChannels,
            'total_media_streams' => $totalMediaStreams,
            'ice_servers' => count($this->iceServers),
            'signaling_servers' => count($this->signalingServers)
        ];
    }

    private function generateAnswer(string $connectionId, array $offer): array
    {
        // This would generate a proper WebRTC answer
        // For now, return a mock answer
        return [
            'type' => 'answer',
            'sdp' => 'mock-sdp-answer-' . $connectionId
        ];
    }

    private function initializeIceServers(): void
    {
        $this->iceServers = [
            [
                'urls' => 'stun:stun.l.google.com:19302'
            ],
            [
                'urls' => 'stun:stun1.l.google.com:19302'
            ]
        ];

        // Add TURN servers if configured
        if (isset($this->config['turn_servers'])) {
            foreach ($this->config['turn_servers'] as $server) {
                $this->iceServers[] = [
                    'urls' => $server['url'],
                    'username' => $server['username'] ?? '',
                    'credential' => $server['credential'] ?? ''
                ];
            }
        }
    }

    private function initializeMediaConstraints(): void
    {
        $this->mediaConstraints = [
            'audio' => [
                'echoCancellation' => true,
                'noiseSuppression' => true,
                'autoGainControl' => true
            ],
            'video' => [
                'width' => 1280,
                'height' => 720,
                'frameRate' => 30
            ]
        ];
    }

    private function startSignalingServer(): void
    {
        if (!$this->loop) {
            return;
        }

        // Start signaling server on configured port
        $port = $this->config['signaling_port'] ?? 8080;
        
        $this->logger->info("Signaling server started", ['port' => $port]);
    }

    private function startStatsCollection(): void
    {
        if (!$this->loop) {
            return;
        }

        // Collect connection statistics every 30 seconds
        $this->loop->addPeriodicTimer(30.0, function() {
            $this->collectConnectionStats();
        });
    }

    private function collectConnectionStats(): void
    {
        foreach ($this->activeConnections as $connectionId => $connection) {
            $this->connectionStats[$connectionId] = [
                'timestamp' => microtime(true),
                'state' => $connection['state'],
                'ice_connection_state' => $connection['ice_connection_state'],
                'data_channels' => count($connection['data_channels']),
                'media_streams' => count($connection['media_streams'])
            ];
        }
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function cleanup(): void
    {
        $this->activeConnections = [];
        $this->signalingServers = [];
        $this->mediaStreams = [];
        $this->dataChannels = [];
        $this->connectionStats = [];
        $this->isEnabled = false;
        $this->logger->info("WebRTC Service cleaned up");
    }
}