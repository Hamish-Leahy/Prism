<?php

namespace Prism\Backend\Services;

use Monolog\Logger;
use React\EventLoop\LoopInterface;
use React\Socket\Server as SocketServer;
use React\Http\Server as HttpServer;
use React\Stream\WritableResourceStream;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer as RatchetHttpServer;
use Ratchet\WebSocket\WsServer;

class WebSocketService implements MessageComponentInterface
{
    private Logger $logger;
    private array $clients = [];
    private array $rooms = [];
    private array $subscriptions = [];
    private LoopInterface $loop;
    private IoServer $server;
    private array $messageHandlers = [];

    public function __construct(Logger $logger, LoopInterface $loop)
    {
        $this->logger = $logger;
        $this->loop = $loop;
        $this->setupMessageHandlers();
    }

    public function start(int $port = 8080): bool
    {
        try {
            $this->server = IoServer::factory(
                new RatchetHttpServer(
                    new WsServer($this)
                ),
                $port
            );

            $this->logger->info('WebSocket server started', ['port' => $port]);
            
            // Start the server in a non-blocking way
            $this->loop->addTimer(0.1, function() {
                $this->server->run();
            });

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to start WebSocket server: ' . $e->getMessage());
            return false;
        }
    }

    public function stop(): bool
    {
        try {
            if ($this->server) {
                $this->server->socket->close();
                $this->logger->info('WebSocket server stopped');
            }
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to stop WebSocket server: ' . $e->getMessage());
            return false;
        }
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $clientId = uniqid('client_');
        $this->clients[$clientId] = $conn;
        
        $this->logger->info('New WebSocket connection', ['client_id' => $clientId]);
        
        // Send welcome message
        $this->sendToClient($clientId, [
            'type' => 'connection_established',
            'client_id' => $clientId,
            'timestamp' => microtime(true)
        ]);
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $clientId = $this->getClientId($from);
        if (!$clientId) {
            return;
        }

        try {
            $data = json_decode($msg, true);
            if (!$data) {
                $this->sendError($clientId, 'Invalid JSON message');
                return;
            }

            $this->handleMessage($clientId, $data);
        } catch (\Exception $e) {
            $this->logger->error('Error processing message', [
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
            $this->sendError($clientId, 'Message processing error');
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $clientId = $this->getClientId($conn);
        if ($clientId) {
            $this->cleanupClient($clientId);
            $this->logger->info('WebSocket connection closed', ['client_id' => $clientId]);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $clientId = $this->getClientId($conn);
        $this->logger->error('WebSocket error', [
            'client_id' => $clientId,
            'error' => $e->getMessage()
        ]);
        
        if ($clientId) {
            $this->cleanupClient($clientId);
        }
    }

    public function broadcast(string $type, array $data, ?string $room = null): void
    {
        $message = [
            'type' => $type,
            'data' => $data,
            'timestamp' => microtime(true)
        ];

        if ($room) {
            $this->broadcastToRoom($room, $message);
        } else {
            $this->broadcastToAll($message);
        }
    }

    public function subscribeToRoom(string $clientId, string $room): bool
    {
        if (!isset($this->clients[$clientId])) {
            return false;
        }

        if (!isset($this->rooms[$room])) {
            $this->rooms[$room] = [];
        }

        $this->rooms[$room][$clientId] = $this->clients[$clientId];
        $this->subscriptions[$clientId][] = $room;

        $this->sendToClient($clientId, [
            'type' => 'subscribed',
            'room' => $room,
            'timestamp' => microtime(true)
        ]);

        $this->logger->info('Client subscribed to room', [
            'client_id' => $clientId,
            'room' => $room
        ]);

        return true;
    }

    public function unsubscribeFromRoom(string $clientId, string $room): bool
    {
        if (!isset($this->rooms[$room][$clientId])) {
            return false;
        }

        unset($this->rooms[$room][$clientId]);
        
        if (isset($this->subscriptions[$clientId])) {
            $this->subscriptions[$clientId] = array_filter(
                $this->subscriptions[$clientId],
                function($r) use ($room) { return $r !== $room; }
            );
        }

        $this->sendToClient($clientId, [
            'type' => 'unsubscribed',
            'room' => $room,
            'timestamp' => microtime(true)
        ]);

        $this->logger->info('Client unsubscribed from room', [
            'client_id' => $clientId,
            'room' => $room
        ]);

        return true;
    }

    public function getConnectedClients(): array
    {
        return array_keys($this->clients);
    }

    public function getRoomClients(string $room): array
    {
        return isset($this->rooms[$room]) ? array_keys($this->rooms[$room]) : [];
    }

    public function getClientSubscriptions(string $clientId): array
    {
        return $this->subscriptions[$clientId] ?? [];
    }

    private function setupMessageHandlers(): void
    {
        $this->messageHandlers = [
            'subscribe' => [$this, 'handleSubscribe'],
            'unsubscribe' => [$this, 'handleUnsubscribe'],
            'ping' => [$this, 'handlePing'],
            'performance_subscribe' => [$this, 'handlePerformanceSubscribe'],
            'download_subscribe' => [$this, 'handleDownloadSubscribe'],
            'tab_subscribe' => [$this, 'handleTabSubscribe']
        ];
    }

    private function handleMessage(string $clientId, array $data): void
    {
        $type = $data['type'] ?? '';
        
        if (isset($this->messageHandlers[$type])) {
            $this->messageHandlers[$type]($clientId, $data);
        } else {
            $this->sendError($clientId, 'Unknown message type: ' . $type);
        }
    }

    private function handleSubscribe(string $clientId, array $data): void
    {
        $room = $data['room'] ?? '';
        if ($room) {
            $this->subscribeToRoom($clientId, $room);
        } else {
            $this->sendError($clientId, 'Room name required for subscription');
        }
    }

    private function handleUnsubscribe(string $clientId, array $data): void
    {
        $room = $data['room'] ?? '';
        if ($room) {
            $this->unsubscribeFromRoom($clientId, $room);
        } else {
            $this->sendError($clientId, 'Room name required for unsubscription');
        }
    }

    private function handlePing(string $clientId, array $data): void
    {
        $this->sendToClient($clientId, [
            'type' => 'pong',
            'timestamp' => microtime(true)
        ]);
    }

    private function handlePerformanceSubscribe(string $clientId, array $data): void
    {
        $this->subscribeToRoom($clientId, 'performance');
        $this->sendToClient($clientId, [
            'type' => 'performance_subscribed',
            'message' => 'Subscribed to performance updates',
            'timestamp' => microtime(true)
        ]);
    }

    private function handleDownloadSubscribe(string $clientId, array $data): void
    {
        $this->subscribeToRoom($clientId, 'downloads');
        $this->sendToClient($clientId, [
            'type' => 'download_subscribed',
            'message' => 'Subscribed to download updates',
            'timestamp' => microtime(true)
        ]);
    }

    private function handleTabSubscribe(string $clientId, array $data): void
    {
        $this->subscribeToRoom($clientId, 'tabs');
        $this->sendToClient($clientId, [
            'type' => 'tab_subscribed',
            'message' => 'Subscribed to tab updates',
            'timestamp' => microtime(true)
        ]);
    }

    private function sendToClient(string $clientId, array $data): void
    {
        if (isset($this->clients[$clientId])) {
            $this->clients[$clientId]->send(json_encode($data));
        }
    }

    private function sendError(string $clientId, string $message): void
    {
        $this->sendToClient($clientId, [
            'type' => 'error',
            'message' => $message,
            'timestamp' => microtime(true)
        ]);
    }

    private function broadcastToAll(array $message): void
    {
        foreach ($this->clients as $clientId => $client) {
            $client->send(json_encode($message));
        }
    }

    private function broadcastToRoom(string $room, array $message): void
    {
        if (!isset($this->rooms[$room])) {
            return;
        }

        foreach ($this->rooms[$room] as $clientId => $client) {
            $client->send(json_encode($message));
        }
    }

    private function getClientId(ConnectionInterface $conn): ?string
    {
        foreach ($this->clients as $clientId => $client) {
            if ($client === $conn) {
                return $clientId;
            }
        }
        return null;
    }

    private function cleanupClient(string $clientId): void
    {
        // Remove from all rooms
        if (isset($this->subscriptions[$clientId])) {
            foreach ($this->subscriptions[$clientId] as $room) {
                if (isset($this->rooms[$room][$clientId])) {
                    unset($this->rooms[$room][$clientId]);
                }
            }
            unset($this->subscriptions[$clientId]);
        }

        // Remove from clients
        unset($this->clients[$clientId]);
    }

    // Integration methods for other services
    public function broadcastPerformanceUpdate(array $metrics): void
    {
        $this->broadcast('performance_update', $metrics, 'performance');
    }

    public function broadcastDownloadUpdate(string $downloadId, array $data): void
    {
        $this->broadcast('download_update', array_merge($data, ['download_id' => $downloadId]), 'downloads');
    }

    public function broadcastTabUpdate(string $tabId, array $data): void
    {
        $this->broadcast('tab_update', array_merge($data, ['tab_id' => $tabId]), 'tabs');
    }

    public function broadcastSystemAlert(array $alert): void
    {
        $this->broadcast('system_alert', $alert);
    }

    public function broadcastEngineSwitch(string $fromEngine, string $toEngine): void
    {
        $this->broadcast('engine_switch', [
            'from' => $fromEngine,
            'to' => $toEngine,
            'timestamp' => microtime(true)
        ]);
    }
}