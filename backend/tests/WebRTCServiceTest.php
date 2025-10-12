<?php

namespace Prism\Backend\Tests;

use PHPUnit\Framework\TestCase;
use Prism\Backend\Services\WebRTCService;
use Monolog\Logger;
use Monolog\Handler\NullHandler;

class WebRTCServiceTest extends TestCase
{
    private WebRTCService $webRTCService;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger('test');
        $this->logger->pushHandler(new NullHandler());
        
        $config = [
            'ice_servers' => [
                ['urls' => 'stun:stun.l.google.com:19302'],
                ['urls' => 'stun:stun1.l.google.com:19302']
            ],
            'turn_servers' => []
        ];
        
        $this->webRTCService = new WebRTCService($config, $this->logger);
    }

    public function testInitialization(): void
    {
        $this->assertFalse($this->webRTCService->isInitialized());
        
        $result = $this->webRTCService->initialize();
        $this->assertTrue($result);
        $this->assertTrue($this->webRTCService->isInitialized());
    }

    public function testCreatePeerConnection(): void
    {
        $this->webRTCService->initialize();
        
        $connectionId = 'test-connection-1';
        $options = [
            'ice_candidate_pool_size' => 5,
            'bundle_policy' => 'max-bundle'
        ];
        
        $connection = $this->webRTCService->createPeerConnection($connectionId, $options);
        
        $this->assertEquals($connectionId, $connection['id']);
        $this->assertEquals('new', $connection['state']);
        $this->assertEquals('new', $connection['iceConnectionState']);
        $this->assertEquals('new', $connection['iceGatheringState']);
        $this->assertEquals('stable', $connection['signalingState']);
        $this->assertArrayHasKey('config', $connection);
        $this->assertArrayHasKey('iceServers', $connection['config']);
    }

    public function testCreateDataChannel(): void
    {
        $this->webRTCService->initialize();
        
        $connectionId = 'test-connection-1';
        $this->webRTCService->createPeerConnection($connectionId);
        
        $channelName = 'test-channel';
        $options = [
            'ordered' => true,
            'maxRetransmits' => 3
        ];
        
        $channel = $this->webRTCService->createDataChannel($connectionId, $channelName, $options);
        
        $this->assertEquals($channelName, $channel['id']);
        $this->assertEquals($connectionId, $channel['connection_id']);
        $this->assertEquals('connecting', $channel['readyState']);
        $this->assertTrue($channel['ordered']);
        $this->assertEquals(3, $channel['maxRetransmits']);
    }

    public function testSetLocalDescription(): void
    {
        $this->webRTCService->initialize();
        
        $connectionId = 'test-connection-1';
        $this->webRTCService->createPeerConnection($connectionId);
        
        $description = [
            'type' => 'offer',
            'sdp' => 'v=0\r\no=- 1234567890 1234567890 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\n'
        ];
        
        $result = $this->webRTCService->setLocalDescription($connectionId, $description);
        $this->assertTrue($result);
        
        $connection = $this->webRTCService->getConnection($connectionId);
        $this->assertEquals($description, $connection['localDescription']);
        $this->assertEquals('have-local-offer', $connection['signalingState']);
    }

    public function testSetRemoteDescription(): void
    {
        $this->webRTCService->initialize();
        
        $connectionId = 'test-connection-1';
        $this->webRTCService->createPeerConnection($connectionId);
        
        $description = [
            'type' => 'answer',
            'sdp' => 'v=0\r\no=- 1234567890 1234567890 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\n'
        ];
        
        $result = $this->webRTCService->setRemoteDescription($connectionId, $description);
        $this->assertTrue($result);
        
        $connection = $this->webRTCService->getConnection($connectionId);
        $this->assertEquals($description, $connection['remoteDescription']);
    }

    public function testAddIceCandidate(): void
    {
        $this->webRTCService->initialize();
        
        $connectionId = 'test-connection-1';
        $this->webRTCService->createPeerConnection($connectionId);
        
        $candidate = [
            'candidate' => 'candidate:1 1 UDP 2113667326 192.168.1.100 54400 typ host',
            'sdpMid' => '0',
            'sdpMLineIndex' => 0,
            'is_remote' => true
        ];
        
        $result = $this->webRTCService->addIceCandidate($connectionId, $candidate);
        $this->assertTrue($result);
        
        $connection = $this->webRTCService->getConnection($connectionId);
        $this->assertCount(1, $connection['remoteCandidates']);
        $this->assertEquals($candidate, $connection['remoteCandidates'][0]);
    }

    public function testSendData(): void
    {
        $this->webRTCService->initialize();
        
        $connectionId = 'test-connection-1';
        $this->webRTCService->createPeerConnection($connectionId);
        
        $channelName = 'test-channel';
        $this->webRTCService->createDataChannel($connectionId, $channelName);
        
        // Get the channel and update its state using reflection
        $reflection = new \ReflectionClass($this->webRTCService);
        $dataChannelsProperty = $reflection->getProperty('dataChannels');
        $dataChannelsProperty->setAccessible(true);
        $dataChannels = $dataChannelsProperty->getValue($this->webRTCService);
        
        $channelKey = $connectionId . '_' . $channelName;
        $dataChannels[$channelKey]['readyState'] = 'open';
        $dataChannelsProperty->setValue($this->webRTCService, $dataChannels);
        
        $data = 'Hello, WebRTC!';
        $result = $this->webRTCService->sendData($connectionId, $channelName, $data);
        $this->assertTrue($result);
    }

    public function testCreateMediaStream(): void
    {
        $this->webRTCService->initialize();
        
        $streamId = 'test-stream-1';
        $options = [
            'audio' => true,
            'video' => true
        ];
        
        $stream = $this->webRTCService->createMediaStream($streamId, $options);
        
        $this->assertEquals($streamId, $stream['id']);
        $this->assertTrue($stream['audio']);
        $this->assertTrue($stream['video']);
        $this->assertTrue($stream['active']);
        $this->assertIsArray($stream['audio_tracks']);
        $this->assertIsArray($stream['video_tracks']);
    }

    public function testAddMediaTrack(): void
    {
        $this->webRTCService->initialize();
        
        $streamId = 'test-stream-1';
        $this->webRTCService->createMediaStream($streamId);
        
        $trackId = 'audio-track-1';
        $kind = 'audio';
        $options = [
            'enabled' => true,
            'muted' => false,
            'settings' => ['sampleRate' => 44100]
        ];
        
        $track = $this->webRTCService->addMediaTrack($streamId, $trackId, $kind, $options);
        
        $this->assertEquals($trackId, $track['id']);
        $this->assertEquals($kind, $track['kind']);
        $this->assertTrue($track['enabled']);
        $this->assertFalse($track['muted']);
        $this->assertEquals('live', $track['readyState']);
    }

    public function testUpdateConnectionState(): void
    {
        $this->webRTCService->initialize();
        
        $connectionId = 'test-connection-1';
        $this->webRTCService->createPeerConnection($connectionId);
        
        $result = $this->webRTCService->updateConnectionState($connectionId, 'connecting');
        $this->assertTrue($result);
        
        $connection = $this->webRTCService->getConnection($connectionId);
        $this->assertEquals('connecting', $connection['state']);
    }

    public function testUpdateIceConnectionState(): void
    {
        $this->webRTCService->initialize();
        
        $connectionId = 'test-connection-1';
        $this->webRTCService->createPeerConnection($connectionId);
        
        $result = $this->webRTCService->updateIceConnectionState($connectionId, 'checking');
        $this->assertTrue($result);
        
        $connection = $this->webRTCService->getConnection($connectionId);
        $this->assertEquals('checking', $connection['iceConnectionState']);
    }

    public function testUpdateIceGatheringState(): void
    {
        $this->webRTCService->initialize();
        
        $connectionId = 'test-connection-1';
        $this->webRTCService->createPeerConnection($connectionId);
        
        $result = $this->webRTCService->updateIceGatheringState($connectionId, 'gathering');
        $this->assertTrue($result);
        
        $connection = $this->webRTCService->getConnection($connectionId);
        $this->assertEquals('gathering', $connection['iceGatheringState']);
    }

    public function testEventHandling(): void
    {
        $this->webRTCService->initialize();
        
        $eventTriggered = false;
        $eventData = null;
        
        $this->webRTCService->on('connectionStateChange', function($data) use (&$eventTriggered, &$eventData) {
            $eventTriggered = true;
            $eventData = $data;
        });
        
        $connectionId = 'test-connection-1';
        $this->webRTCService->createPeerConnection($connectionId);
        $this->webRTCService->updateConnectionState($connectionId, 'connecting');
        
        $this->assertTrue($eventTriggered);
        $this->assertIsArray($eventData);
        $this->assertEquals($connectionId, $eventData['connection_id']);
        $this->assertEquals('new', $eventData['old_state']);
        $this->assertEquals('connecting', $eventData['new_state']);
    }

    public function testGetStats(): void
    {
        $this->webRTCService->initialize();
        
        $connectionId = 'test-connection-1';
        $this->webRTCService->createPeerConnection($connectionId);
        
        $stats = $this->webRTCService->getStats($connectionId);
        
        $this->assertIsArray($stats);
        $this->assertEquals($connectionId, $stats['connection_id']);
        $this->assertEquals('new', $stats['state']);
        $this->assertEquals('new', $stats['ice_connection_state']);
        $this->assertEquals('new', $stats['ice_gathering_state']);
        $this->assertEquals('stable', $stats['signaling_state']);
    }

    public function testGetEnhancedStats(): void
    {
        $this->webRTCService->initialize();
        
        $connectionId = 'test-connection-1';
        $this->webRTCService->createPeerConnection($connectionId);
        
        $channelName = 'test-channel';
        $this->webRTCService->createDataChannel($connectionId, $channelName);
        
        $stats = $this->webRTCService->getEnhancedStats($connectionId);
        
        $this->assertIsArray($stats);
        $this->assertEquals($connectionId, $stats['connection_id']);
        $this->assertEquals(1, $stats['data_channels_count']);
        $this->assertIsArray($stats['data_channels']);
        $this->assertCount(1, $stats['data_channels']);
        $this->assertFalse($stats['has_local_description']);
        $this->assertFalse($stats['has_remote_description']);
        $this->assertGreaterThan(0, $stats['ice_servers_count']);
    }

    public function testCloseConnection(): void
    {
        $this->webRTCService->initialize();
        
        $connectionId = 'test-connection-1';
        $this->webRTCService->createPeerConnection($connectionId);
        $this->webRTCService->createDataChannel($connectionId, 'test-channel');
        
        $result = $this->webRTCService->closeConnection($connectionId);
        $this->assertTrue($result);
        
        $connection = $this->webRTCService->getConnection($connectionId);
        $this->assertNull($connection);
    }

    public function testCloseMediaStream(): void
    {
        $this->webRTCService->initialize();
        
        $streamId = 'test-stream-1';
        $this->webRTCService->createMediaStream($streamId);
        
        $result = $this->webRTCService->closeMediaStream($streamId);
        $this->assertTrue($result);
        
        $stream = $this->webRTCService->getMediaStream($streamId);
        $this->assertNull($stream);
    }

    public function testCleanup(): void
    {
        $this->webRTCService->initialize();
        
        $connectionId = 'test-connection-1';
        $this->webRTCService->createPeerConnection($connectionId);
        $this->webRTCService->createDataChannel($connectionId, 'test-channel');
        
        $streamId = 'test-stream-1';
        $this->webRTCService->createMediaStream($streamId);
        
        $this->webRTCService->cleanup();
        
        $this->assertFalse($this->webRTCService->isInitialized());
        $this->assertEmpty($this->webRTCService->getAllConnections());
        $this->assertEmpty($this->webRTCService->getAllDataChannels());
        $this->assertEmpty($this->webRTCService->getAllMediaStreams());
    }

    public function testExceptionHandling(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->webRTCService->createPeerConnection('test-connection-1');
    }

    public function testDataChannelNotFound(): void
    {
        $this->webRTCService->initialize();
        
        $this->expectException(\RuntimeException::class);
        $this->webRTCService->sendData('non-existent-connection', 'non-existent-channel', 'data');
    }

    public function testConnectionNotFound(): void
    {
        $this->webRTCService->initialize();
        
        $this->expectException(\RuntimeException::class);
        $this->webRTCService->createDataChannel('non-existent-connection', 'test-channel');
    }

    public function testMediaStreamNotFound(): void
    {
        $this->webRTCService->initialize();
        
        $this->expectException(\RuntimeException::class);
        $this->webRTCService->addMediaTrack('non-existent-stream', 'track-1', 'audio');
    }
}
