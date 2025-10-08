<?php

namespace Prism\Backend\Services;

use Prism\Backend\Services\Engines\EngineInterface;
use Prism\Backend\Services\Engines\ChromiumEngine;
use Prism\Backend\Services\Engines\FirefoxEngine;
use Prism\Backend\Services\Engines\PrismEngine;
use Monolog\Logger;

class EngineManager
{
    private array $engines;
    private string $currentEngine;
    private ?EngineInterface $activeEngine = null;
    private Logger $logger;

    public function __construct(array $config)
    {
        $this->engines = $config['available'];
        $this->currentEngine = $config['default'];
        $this->logger = new Logger('engine-manager');
    }

    public function getAvailableEngines(): array
    {
        return array_filter($this->engines, fn($engine) => $engine['enabled']);
    }

    public function getCurrentEngine(): string
    {
        return $this->currentEngine;
    }

    public function switchEngine(string $engineName): bool
    {
        if (!isset($this->engines[$engineName]) || !$this->engines[$engineName]['enabled']) {
            $this->logger->error("Engine '{$engineName}' is not available or disabled");
            return false;
        }

        // Close current engine if active
        if ($this->activeEngine) {
            $this->activeEngine->close();
        }

        $this->currentEngine = $engineName;
        $this->activeEngine = null; // Will be lazy-loaded

        $this->logger->info("Switched to engine: {$engineName}");
        return true;
    }

    public function getActiveEngine(): EngineInterface
    {
        if (!$this->activeEngine) {
            $this->activeEngine = $this->createEngine($this->currentEngine);
        }

        return $this->activeEngine;
    }

    private function createEngine(string $engineName): EngineInterface
    {
        $engineConfig = $this->engines[$engineName];
        $engineClass = $engineConfig['class'];

        if (!class_exists($engineClass)) {
            throw new \RuntimeException("Engine class '{$engineClass}' not found");
        }

        $engine = new $engineClass($engineConfig['config']);
        
        if (!$engine instanceof EngineInterface) {
            throw new \RuntimeException("Engine class '{$engineClass}' must implement EngineInterface");
        }

        if (!$engine->initialize()) {
            throw new \RuntimeException("Failed to initialize engine '{$engineName}'");
        }

        $this->logger->info("Initialized engine: {$engineName}");
        return $engine;
    }

    public function getEngineStatus(string $engineName): array
    {
        if (!isset($this->engines[$engineName])) {
            return ['available' => false, 'error' => 'Engine not found'];
        }

        $engine = $this->engines[$engineName];
        
        try {
            $instance = $this->createEngine($engineName);
            $status = [
                'available' => true,
                'name' => $engine['name'],
                'description' => $engine['description'],
                'enabled' => $engine['enabled'],
                'initialized' => true
            ];
            $instance->close();
        } catch (\Exception $e) {
            $status = [
                'available' => false,
                'name' => $engine['name'],
                'description' => $engine['description'],
                'enabled' => $engine['enabled'],
                'error' => $e->getMessage()
            ];
        }

        return $status;
    }

    public function __destruct()
    {
        if ($this->activeEngine) {
            $this->activeEngine->close();
        }
    }
}
