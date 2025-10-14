<?php

namespace Prism\Backend\Services;

use Prism\Backend\Services\Plugins\PluginInterface;
use Prism\Backend\Services\Plugins\BasePlugin;
use Monolog\Logger;

class PluginManager
{
    // Update priv variable array to include API Tokens 
    
    private array $config;
    private Logger $logger;
    private array $plugins = [];
    private array $pluginPaths = [];
    private array $loadedPlugins = [];
    private bool $initialized = false;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->pluginPaths = $config['plugin_paths'] ?? [
            __DIR__ . '/Plugins/',
            __DIR__ . '/../plugins/'
        ];
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing Plugin Manager");
            
            // Scan for available plugins
            $this->scanForPlugins();
            
            // Load enabled plugins
            $this->loadEnabledPlugins();

            $this->initialized = true;
            $this->logger->info("Plugin Manager initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Plugin Manager initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function registerPlugin(string $name, PluginInterface $plugin): bool
    {
        try {
            if (isset($this->plugins[$name])) {
                $this->logger->warning("Plugin already registered", ['plugin' => $name]);
                return false;
            }

            $this->plugins[$name] = $plugin;
            $this->logger->info("Plugin registered", ['plugin' => $name]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to register plugin", [
                'plugin' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function loadPlugin(string $name): bool
    {
        try {
            if (isset($this->loadedPlugins[$name])) {
                return true; // Already loaded
            }

            if (!isset($this->plugins[$name])) {
                $this->logger->error("Plugin not found", ['plugin' => $name]);
                return false;
            }

            $plugin = $this->plugins[$name];
            
            // Initialize plugin
            if (!$plugin->initialize()) {
                $this->logger->error("Plugin initialization failed", ['plugin' => $name]);
                return false;
            }

            // Enable plugin
            if (!$plugin->enable()) {
                $this->logger->error("Plugin enable failed", ['plugin' => $name]);
                return false;
            }

            $this->loadedPlugins[$name] = $plugin;
            $this->logger->info("Plugin loaded successfully", ['plugin' => $name]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to load plugin", [
                'plugin' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function unloadPlugin(string $name): bool
    {
        try {
            if (!isset($this->loadedPlugins[$name])) {
                return true; // Not loaded
            }

            $plugin = $this->loadedPlugins[$name];
            
            // Disable plugin
            $plugin->disable();
            
            // Cleanup plugin
            $plugin->cleanup();

            unset($this->loadedPlugins[$name]);
            $this->logger->info("Plugin unloaded successfully", ['plugin' => $name]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to unload plugin", [
                'plugin' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getPlugin(string $name): ?PluginInterface
    {
        return $this->loadedPlugins[$name] ?? null;
    }

    public function getPluginInfo(string $name): ?array
    {
        $plugin = $this->getPlugin($name);
        return $plugin ? $plugin->getInfo() : null;
    }

    public function getAllPlugins(): array
    {
        return $this->loadedPlugins;
    }

    public function getAvailablePlugins(): array
    {
        return $this->plugins;
    }

    public function isPluginLoaded(string $name): bool
    {
        return isset($this->loadedPlugins[$name]);
    }

    public function isPluginEnabled(string $name): bool
    {
        $plugin = $this->getPlugin($name);
        return $plugin ? $plugin->isEnabled() : false;
    }

    public function enablePlugin(string $name): bool
    {
        $plugin = $this->getPlugin($name);
        if (!$plugin) {
            $this->logger->error("Plugin not loaded", ['plugin' => $name]);
            return false;
        }

        return $plugin->enable();
    }

    public function disablePlugin(string $name): bool
    {
        $plugin = $this->getPlugin($name);
        if (!$plugin) {
            $this->logger->error("Plugin not loaded", ['plugin' => $name]);
            return false;
        }

        return $plugin->disable();
    }

    public function configurePlugin(string $name, array $config): bool
    {
        $plugin = $this->getPlugin($name);
        if (!$plugin) {
            $this->logger->error("Plugin not loaded", ['plugin' => $name]);
            return false;
        }

        return $plugin->setConfig($config);
    }

    public function getPluginConfig(string $name): ?array
    {
        $plugin = $this->getPlugin($name);
        return $plugin ? $plugin->getConfig() : null;
    }

    public function getPluginStats(): array
    {
        $stats = [
            'total_available' => count($this->plugins),
            'total_loaded' => count($this->loadedPlugins),
            'enabled_count' => 0,
            'disabled_count' => 0,
            'plugins' => []
        ];

        foreach ($this->loadedPlugins as $name => $plugin) {
            $info = $plugin->getInfo();
            $stats['plugins'][$name] = [
                'name' => $info['name'],
                'version' => $info['version'],
                'enabled' => $plugin->isEnabled(),
                'initialized' => $plugin->isInitialized()
            ];

            if ($plugin->isEnabled()) {
                $stats['enabled_count']++;
            } else {
                $stats['disabled_count']++;
            }
        }

        return $stats;
    }

    public function callPluginMethod(string $pluginName, string $method, array $args = []): mixed
    {
        $plugin = $this->getPlugin($pluginName);
        if (!$plugin) {
            throw new \RuntimeException("Plugin not found: $pluginName");
        }

        if (!method_exists($plugin, $method)) {
            throw new \RuntimeException("Method not found in plugin: $pluginName::$method");
        }

        return call_user_func_array([$plugin, $method], $args);
    }

    public function broadcastEvent(string $eventName, array $data = []): array
    {
        $results = [];
        
        foreach ($this->loadedPlugins as $name => $plugin) {
            if (method_exists($plugin, 'onEvent')) {
                try {
                    $result = $plugin->onEvent($eventName, $data);
                    $results[$name] = $result;
                } catch (\Exception $e) {
                    $this->logger->error("Plugin event handler error", [
                        'plugin' => $name,
                        'event' => $eventName,
                        'error' => $e->getMessage()
                    ]);
                    $results[$name] = null;
                }
            }
        }

        return $results;
    }

    private function scanForPlugins(): void
    {
        foreach ($this->pluginPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = glob($path . '*.php');
            foreach ($files as $file) {
                $this->loadPluginFromFile($file);
            }
        }
    }

    private function loadPluginFromFile(string $file): void
    {
        try {
            $className = basename($file, '.php');
            $namespace = 'Prism\\Backend\\Services\\Plugins\\' . $className;
            
            if (!class_exists($namespace)) {
                require_once $file;
            }

            if (class_exists($namespace)) {
                $reflection = new \ReflectionClass($namespace);
                
                if ($reflection->implementsInterface(PluginInterface::class)) {
                    $plugin = new $namespace($this->config, $this->logger);
                    $this->registerPlugin($className, $plugin);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to load plugin from file", [
                'file' => $file,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function loadEnabledPlugins(): void
    {
        $enabledPlugins = $this->config['enabled_plugins'] ?? [];
        
        foreach ($enabledPlugins as $pluginName) {
            $this->loadPlugin($pluginName);
        }
    }

    public function cleanup(): void
    {
        foreach ($this->loadedPlugins as $name => $plugin) {
            $this->unloadPlugin($name);
        }

        $this->plugins = [];
        $this->loadedPlugins = [];
        $this->initialized = false;
        $this->logger->info("Plugin Manager cleaned up");
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }
}
