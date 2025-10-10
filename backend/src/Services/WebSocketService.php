<?php

namespace Prism\Backend\Services;

use Monolog\Logger;
use React\EventLoop\Loop;
use React\Socket\Connector;
use React\Stream\WritableResourceStream;
use React\Stream\ReadableResourceStream;
use React\Stream\ThroughStream;
use React\Stream\Util;

class WebSocketService
{
    private array $config;
    private Logger $logger;
    private array $connections = [];
    private array $eventListeners = [];
    private ?\React\EventLoop\LoopInterface $loop = null;
    private bool $initialized = false;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function initialize(): bool
    {
        try {
            if (!extension_loaded('sockets')) {
                throw new \RuntimeException('Socket extension not available');
            }

            $this->loop = Loop::get();
            $this->initialized = true;
            
            $this->logger->info("WebSocket service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("WebSocket service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Connect to a WebSocket server
     */
    public function connect(string $url, array $options = []): ?string
    {
        if (!$this->isInitialized()) {
            throw new \RuntimeException('WebSocket service not initialized');
        }

        try {
            $connectionId = uniqid('ws_', true);
            $parsedUrl = parse_url($url);
            
            if (!$parsedUrl || !isset($parsedUrl['host'])) {
                throw new \InvalidArgumentException('Invalid WebSocket URL');
            }

            $host = $parsedUrl['host'];
            $port = $parsedUrl['port'] ?? ($parsedUrl['scheme'] === 'wss' ? 443 : 80);
            $path = $parsedUrl['path'] ?? '/';
            $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
            $fullPath = $path . $query;

            // Create WebSocket connection
            $connection = $this->createWebSocketConnection($host, $port, $fullPath, $options);
            
            if ($connection) {
                $this->connections[$connectionId] = [
                    'id' => $connectionId,
                    'url' => $url,
                    'host' => $host,
                    'port' => $port,
                    'path' => $fullPath,
                    'connection' => $connection,
                    'status' => 'connecting',
                    'created_at' => time(),
                    'last_activity' => time()
                ];

                $this->logger->info("WebSocket connection initiated", [
                    'connection_id' => $connectionId,
                    'url' => $url
                ]);

                return $connectionId;
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error("WebSocket connection failed", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create a WebSocket connection using raw sockets
     */
    private function createWebSocketConnection(string $host, int $port, string $path, array $options): ?resource
    {
        try {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (!$socket) {
                throw new \RuntimeException('Failed to create socket');
            }

            // Set socket options
            socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
            socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);

            // Connect to server
            $connected = socket_connect($socket, $host, $port);
            if (!$connected) {
                socket_close($socket);
                return null;
            }

            // Send WebSocket handshake
            $key = base64_encode(random_bytes(16));
            $handshake = $this->buildHandshake($host, $port, $path, $key);
            
            socket_write($socket, $handshake);
            
            // Read handshake response
            $response = socket_read($socket, 1024);
            if (!$this->validateHandshake($response)) {
                socket_close($socket);
                return null;
            }

            return $socket;
        } catch (\Exception $e) {
            $this->logger->error("WebSocket connection creation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Build WebSocket handshake request
     */
    private function buildHandshake(string $host, int $port, string $path, string $key): string
    {
        $headers = [
            "GET {$path} HTTP/1.1",
            "Host: {$host}:{$port}",
            "Upgrade: websocket",
            "Connection: Upgrade",
            "Sec-WebSocket-Key: {$key}",
            "Sec-WebSocket-Version: 13",
            "User-Agent: Prism/1.0 (WebSocket Client)",
            "",
            ""
        ];

        return implode("\r\n", $headers);
    }

    /**
     * Validate WebSocket handshake response
     */
    private function validateHandshake(string $response): bool
    {
        return strpos($response, '101 Switching Protocols') !== false &&
               strpos($response, 'Sec-WebSocket-Accept') !== false;
    }

    /**
     * Send data through WebSocket connection
     */
    public function send(string $connectionId, string $data, int $opcode = 1): bool
    {
        if (!isset($this->connections[$connectionId])) {
            $this->logger->warning("WebSocket connection not found", ['connection_id' => $connectionId]);
            return false;
        }

        $connection = $this->connections[$connectionId];
        $socket = $connection['connection'];

        try {
            $frame = $this->buildFrame($data, $opcode);
            $sent = socket_write($socket, $frame);
            
            if ($sent !== false) {
                $this->connections[$connectionId]['last_activity'] = time();
                $this->logger->debug("WebSocket data sent", [
                    'connection_id' => $connectionId,
                    'data_length' => strlen($data)
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error("WebSocket send failed", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Build WebSocket frame
     */
    private function buildFrame(string $data, int $opcode = 1): string
    {
        $length = strlen($data);
        $frame = '';

        // First byte: FIN + RSV + Opcode
        $frame .= chr(0x80 | $opcode);

        // Second byte: MASK + Payload length
        if ($length < 126) {
            $frame .= chr(0x80 | $length);
        } elseif ($length < 65536) {
            $frame .= chr(0x80 | 126);
            $frame .= pack('n', $length);
        } else {
            $frame .= chr(0x80 | 127);
            $frame .= pack('J', $length);
        }

        // Masking key
        $maskingKey = random_bytes(4);
        $frame .= $maskingKey;

        // Mask payload
        $maskedData = '';
        for ($i = 0; $i < $length; $i++) {
            $maskedData .= $data[$i] ^ $maskingKey[$i % 4];
        }

        $frame .= $maskedData;
        return $frame;
    }

    /**
     * Receive data from WebSocket connection
     */
    public function receive(string $connectionId): ?string
    {
        if (!isset($this->connections[$connectionId])) {
            return null;
        }

        $connection = $this->connections[$connectionId];
        $socket = $connection['connection'];

        try {
            // Read frame header (2 bytes minimum)
            $header = socket_read($socket, 2);
            if (strlen($header) < 2) {
                return null;
            }

            $firstByte = ord($header[0]);
            $secondByte = ord($header[1]);

            $fin = ($firstByte & 0x80) >> 7;
            $opcode = $firstByte & 0x0F;
            $masked = ($secondByte & 0x80) >> 7;
            $payloadLength = $secondByte & 0x7F;

            // Read extended payload length if needed
            if ($payloadLength === 126) {
                $extendedLength = socket_read($socket, 2);
                $payloadLength = unpack('n', $extendedLength)[1];
            } elseif ($payloadLength === 127) {
                $extendedLength = socket_read($socket, 8);
                $payloadLength = unpack('J', $extendedLength)[1];
            }

            // Read masking key if present
            $maskingKey = '';
            if ($masked) {
                $maskingKey = socket_read($socket, 4);
            }

            // Read payload
            $payload = socket_read($socket, $payloadLength);
            if (strlen($payload) !== $payloadLength) {
                return null;
            }

            // Unmask payload if masked
            if ($masked && $maskingKey) {
                $unmaskedPayload = '';
                for ($i = 0; $i < $payloadLength; $i++) {
                    $unmaskedPayload .= $payload[$i] ^ $maskingKey[$i % 4];
                }
                $payload = $unmaskedPayload;
            }

            $this->connections[$connectionId]['last_activity'] = time();

            return $payload;
        } catch (\Exception $e) {
            $this->logger->error("WebSocket receive failed", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Close WebSocket connection
     */
    public function close(string $connectionId): bool
    {
        if (!isset($this->connections[$connectionId])) {
            return false;
        }

        $connection = $this->connections[$connectionId];
        $socket = $connection['connection'];

        try {
            // Send close frame
            $this->send($connectionId, '', 8); // Opcode 8 = Close
            
            socket_close($socket);
            unset($this->connections[$connectionId]);

            $this->logger->info("WebSocket connection closed", [
                'connection_id' => $connectionId
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("WebSocket close failed", [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get connection status
     */
    public function getConnectionStatus(string $connectionId): ?array
    {
        if (!isset($this->connections[$connectionId])) {
            return null;
        }

        $connection = $this->connections[$connectionId];
        return [
            'id' => $connection['id'],
            'url' => $connection['url'],
            'status' => $connection['status'],
            'created_at' => $connection['created_at'],
            'last_activity' => $connection['last_activity']
        ];
    }

    /**
     * Get all connections
     */
    public function getConnections(): array
    {
        return array_keys($this->connections);
    }

    /**
     * Add event listener
     */
    public function addEventListener(string $event, callable $listener): void
    {
        if (!isset($this->eventListeners[$event])) {
            $this->eventListeners[$event] = [];
        }
        $this->eventListeners[$event][] = $listener;
    }

    /**
     * Remove event listener
     */
    public function removeEventListener(string $event, callable $listener): void
    {
        if (isset($this->eventListeners[$event])) {
            $key = array_search($listener, $this->eventListeners[$event], true);
            if ($key !== false) {
                unset($this->eventListeners[$event][$key]);
            }
        }
    }

    /**
     * Dispatch event
     */
    private function dispatchEvent(string $event, array $data = []): void
    {
        if (isset($this->eventListeners[$event])) {
            foreach ($this->eventListeners[$event] as $listener) {
                try {
                    $listener($data);
                } catch (\Exception $e) {
                    $this->logger->error("Event listener error", [
                        'event' => $event,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Check if service is initialized
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get service statistics
     */
    public function getStats(): array
    {
        return [
            'initialized' => $this->initialized,
            'connections_count' => count($this->connections),
            'connections' => array_map(function($conn) {
                return [
                    'id' => $conn['id'],
                    'url' => $conn['url'],
                    'status' => $conn['status'],
                    'created_at' => $conn['created_at'],
                    'last_activity' => $conn['last_activity']
                ];
            }, $this->connections)
        ];
    }

    /**
     * Cleanup and close all connections
     */
    public function shutdown(): void
    {
        foreach (array_keys($this->connections) as $connectionId) {
            $this->close($connectionId);
        }
        
        $this->connections = [];
        $this->eventListeners = [];
        $this->initialized = false;
        
        $this->logger->info("WebSocket service closed");
    }
}
