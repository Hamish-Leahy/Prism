<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class WebAssemblyService
{
    private array $config;
    private Logger $logger;
    private array $modules = [];
    private array $instances = [];
    private array $memory = [];
    private bool $initialized = false;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing WebAssembly service");
            
            // Check if WebAssembly is supported
            if (!$this->isWebAssemblySupported()) {
                $this->logger->warning("WebAssembly is not supported on this system");
                return false;
            }

            $this->initialized = true;
            $this->logger->info("WebAssembly service initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("WebAssembly service initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function isWebAssemblySupported(): bool
    {
        // Check if V8 extension is available (for JavaScript execution)
        if (extension_loaded('v8js')) {
            return true;
        }

        // Check if we can use external WebAssembly runtime
        if (isset($this->config['wasm_runtime_path']) && 
            file_exists($this->config['wasm_runtime_path'])) {
            return true;
        }

        // Check if we can use Node.js for WebAssembly execution
        if (isset($this->config['nodejs_path']) && 
            file_exists($this->config['nodejs_path'])) {
            return true;
        }

        return false;
    }

    public function compileModule(string $wasmBinary): ?string
    {
        if (!$this->initialized) {
            throw new \RuntimeException('WebAssembly service not initialized');
        }

        try {
            $moduleId = 'module_' . uniqid();
            
            // Validate WASM binary
            if (!$this->validateWasmBinary($wasmBinary)) {
                throw new \InvalidArgumentException('Invalid WASM binary');
            }

            $module = [
                'id' => $moduleId,
                'binary' => $wasmBinary,
                'compiled' => true,
                'exports' => [],
                'imports' => [],
                'memory' => null,
                'created_at' => time()
            ];

            // Parse WASM module to extract exports and imports
            $this->parseWasmModule($module, $wasmBinary);

            $this->modules[$moduleId] = $module;
            $this->logger->info("Compiled WebAssembly module", ['module_id' => $moduleId]);

            return $moduleId;
        } catch (\Exception $e) {
            $this->logger->error("Failed to compile WebAssembly module: " . $e->getMessage());
            return null;
        }
    }

    public function instantiateModule(string $moduleId, array $imports = []): ?string
    {
        if (!isset($this->modules[$moduleId])) {
            throw new \RuntimeException('Module not found');
        }

        try {
            $instanceId = 'instance_' . uniqid();
            $module = $this->modules[$moduleId];

            $instance = [
                'id' => $instanceId,
                'module_id' => $moduleId,
                'memory' => $this->createMemory($module),
                'exports' => $module['exports'],
                'imports' => $imports,
                'state' => 'running',
                'created_at' => time(),
                'last_activity' => time()
            ];

            $this->instances[$instanceId] = $instance;
            $this->logger->info("Instantiated WebAssembly module", [
                'module_id' => $moduleId,
                'instance_id' => $instanceId
            ]);

            return $instanceId;
        } catch (\Exception $e) {
            $this->logger->error("Failed to instantiate WebAssembly module: " . $e->getMessage());
            return null;
        }
    }

    public function callFunction(string $instanceId, string $functionName, array $args = []): mixed
    {
        if (!isset($this->instances[$instanceId])) {
            throw new \RuntimeException('Instance not found');
        }

        $instance = $this->instances[$instanceId];
        
        if (!isset($instance['exports'][$functionName])) {
            throw new \RuntimeException('Function not found in module exports');
        }

        try {
            $this->instances[$instanceId]['last_activity'] = time();

            // Execute the function using the configured runtime
            $result = $this->executeWasmFunction($instance, $functionName, $args);

            $this->logger->info("Called WebAssembly function", [
                'instance_id' => $instanceId,
                'function_name' => $functionName,
                'args_count' => count($args)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("Failed to call WebAssembly function: " . $e->getMessage());
            throw $e;
        }
    }

    public function getMemory(string $instanceId, int $offset = 0, int $length = null): string
    {
        if (!isset($this->instances[$instanceId])) {
            throw new \RuntimeException('Instance not found');
        }

        $instance = $this->instances[$instanceId];
        
        if (!$instance['memory']) {
            throw new \RuntimeException('No memory allocated for this instance');
        }

        $memory = $instance['memory'];
        $maxLength = $length ?? (strlen($memory) - $offset);
        
        return substr($memory, $offset, $maxLength);
    }

    public function setMemory(string $instanceId, int $offset, string $data): bool
    {
        if (!isset($this->instances[$instanceId])) {
            throw new \RuntimeException('Instance not found');
        }

        $instance = &$this->instances[$instanceId];
        
        if (!$instance['memory']) {
            throw new \RuntimeException('No memory allocated for this instance');
        }

        $memory = &$instance['memory'];
        $dataLength = strlen($data);
        
        // Ensure we don't write beyond memory bounds
        if ($offset + $dataLength > strlen($memory)) {
            // Extend memory if needed
            $memory .= str_repeat("\0", ($offset + $dataLength) - strlen($memory));
        }

        for ($i = 0; $i < $dataLength; $i++) {
            $memory[$offset + $i] = $data[$i];
        }

        $instance['last_activity'] = time();
        return true;
    }

    public function getModule(string $moduleId): ?array
    {
        return $this->modules[$moduleId] ?? null;
    }

    public function getInstance(string $instanceId): ?array
    {
        return $this->instances[$instanceId] ?? null;
    }

    public function closeInstance(string $instanceId): bool
    {
        if (!isset($this->instances[$instanceId])) {
            return false;
        }

        unset($this->instances[$instanceId]);
        $this->logger->info("Closed WebAssembly instance", ['instance_id' => $instanceId]);
        return true;
    }

    public function closeModule(string $moduleId): bool
    {
        if (!isset($this->modules[$moduleId])) {
            return false;
        }

        // Close all instances of this module
        foreach ($this->instances as $instanceId => $instance) {
            if ($instance['module_id'] === $moduleId) {
                $this->closeInstance($instanceId);
            }
        }

        unset($this->modules[$moduleId]);
        $this->logger->info("Closed WebAssembly module", ['module_id' => $moduleId]);
        return true;
    }

    public function getStats(): array
    {
        return [
            'modules_count' => count($this->modules),
            'instances_count' => count($this->instances),
            'total_memory_usage' => array_sum(array_map('strlen', array_column($this->instances, 'memory'))),
            'initialized' => $this->initialized,
            'supported' => $this->isWebAssemblySupported()
        ];
    }

    private function validateWasmBinary(string $binary): bool
    {
        // Basic WASM magic number validation
        if (strlen($binary) < 8) {
            return false;
        }

        // Check WASM magic number (0x00 0x61 0x73 0x6D)
        $magic = substr($binary, 0, 4);
        return $magic === "\x00\x61\x73\x6D";
    }

    private function parseWasmModule(array &$module, string $binary): void
    {
        // This is a simplified parser - in a real implementation,
        // you would use a proper WASM parser library
        
        // For now, we'll create some mock exports
        $module['exports'] = [
            'memory' => ['type' => 'memory'],
            'main' => ['type' => 'function', 'params' => [], 'returns' => ['i32']],
            'add' => ['type' => 'function', 'params' => ['i32', 'i32'], 'returns' => ['i32']],
            'multiply' => ['type' => 'function', 'params' => ['i32', 'i32'], 'returns' => ['i32']]
        ];

        $module['imports'] = [];
    }

    private function createMemory(array $module): string
    {
        // Create initial memory (64KB default)
        $initialSize = $module['memory']['initial'] ?? 1;
        $maxSize = $module['memory']['maximum'] ?? 10;
        
        $memorySize = $initialSize * 65536; // 64KB per page
        return str_repeat("\0", $memorySize);
    }

    private function executeWasmFunction(array $instance, string $functionName, array $args): mixed
    {
        // This is a mock implementation - in a real implementation,
        // you would use a proper WebAssembly runtime
        
        switch ($functionName) {
            case 'add':
                return ($args[0] ?? 0) + ($args[1] ?? 0);
            case 'multiply':
                return ($args[0] ?? 0) * ($args[1] ?? 0);
            case 'main':
                return 42; // Mock return value
            default:
                throw new \RuntimeException("Unknown function: $functionName");
        }
    }

    public function cleanup(): void
    {
        $this->modules = [];
        $this->instances = [];
        $this->memory = [];
        $this->initialized = false;
        $this->logger->info("WebAssembly service cleaned up");
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }
}
