<?php

namespace Prism\Backend\Services\Plugins;

use Monolog\Logger;

abstract class BasePlugin implements PluginInterface
{
    protected array $config;
    protected Logger $logger;
    protected bool $initialized = false;
    protected bool $enabled = false;
    protected string $name;
    protected string $version;
    protected string $description;
    protected array $dependencies = [];

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->name = $this->getName();
        $this->version = $this->getVersion();
        $this->description = $this->getDescription();
        $this->dependencies = $this->getDependencies();
    }

    public function getInfo(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->getAuthor(),
            'license' => $this->getLicense(),
            'homepage' => $this->getHomepage(),
            'dependencies' => $this->dependencies,
            'initialized' => $this->initialized,
            'enabled' => $this->enabled,
            'config' => $this->config
        ];
    }

    public function initialize(): bool
    {
        try {
            if ($this->initialized) {
                return true;
            }

            $this->logger->info("Initializing plugin", ['plugin' => $this->name]);

            // Check dependencies
            if (!$this->checkDependencies()) {
                $this->logger->error("Plugin dependencies not satisfied", ['plugin' => $this->name]);
                return false;
            }

            // Call plugin-specific initialization
            $result = $this->onInitialize();

            if ($result) {
                $this->initialized = true;
                $this->logger->info("Plugin initialized successfully", ['plugin' => $this->name]);
            } else {
                $this->logger->error("Plugin initialization failed", ['plugin' => $this->name]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("Plugin initialization error", [
                'plugin' => $this->name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function enable(): bool
    {
        try {
            if (!$this->initialized) {
                $this->logger->error("Cannot enable uninitialized plugin", ['plugin' => $this->name]);
                return false;
            }

            if ($this->enabled) {
                return true;
            }

            $this->logger->info("Enabling plugin", ['plugin' => $this->name]);

            $result = $this->onEnable();

            if ($result) {
                $this->enabled = true;
                $this->logger->info("Plugin enabled successfully", ['plugin' => $this->name]);
            } else {
                $this->logger->error("Plugin enable failed", ['plugin' => $this->name]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("Plugin enable error", [
                'plugin' => $this->name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function disable(): bool
    {
        try {
            if (!$this->enabled) {
                return true;
            }

            $this->logger->info("Disabling plugin", ['plugin' => $this->name]);

            $result = $this->onDisable();

            if ($result) {
                $this->enabled = false;
                $this->logger->info("Plugin disabled successfully", ['plugin' => $this->name]);
            } else {
                $this->logger->error("Plugin disable failed", ['plugin' => $this->name]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("Plugin disable error", [
                'plugin' => $this->name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): bool
    {
        try {
            $this->config = array_merge($this->config, $config);
            $this->onConfigChange($config);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Plugin config update error", [
                'plugin' => $this->name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function checkDependencies(): bool
    {
        // Override in child classes to implement dependency checking
        return true;
    }

    public function cleanup(): void
    {
        try {
            if ($this->enabled) {
                $this->disable();
            }

            $this->onCleanup();
            $this->initialized = false;
            $this->enabled = false;

            $this->logger->info("Plugin cleaned up", ['plugin' => $this->name]);
        } catch (\Exception $e) {
            $this->logger->error("Plugin cleanup error", [
                'plugin' => $this->name,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Abstract methods to be implemented by child classes
    abstract protected function getName(): string;
    abstract protected function getVersion(): string;
    abstract protected function getDescription(): string;
    abstract protected function getAuthor(): string;
    abstract protected function getLicense(): string;
    abstract protected function getHomepage(): string;

    // Hook methods that can be overridden by child classes
    protected function onInitialize(): bool
    {
        return true;
    }

    protected function onEnable(): bool
    {
        return true;
    }

    protected function onDisable(): bool
    {
        return true;
    }

    protected function onConfigChange(array $newConfig): void
    {
        // Override in child classes if needed
    }

    protected function onCleanup(): void
    {
        // Override in child classes if needed
    }
}
