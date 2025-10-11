<?php

namespace Prism\Backend\Services\Plugins;

interface PluginInterface
{
    /**
     * Get plugin information
     */
    public function getInfo(): array;

    /**
     * Initialize the plugin
     */
    public function initialize(): bool;

    /**
     * Check if plugin is initialized
     */
    public function isInitialized(): bool;

    /**
     * Enable the plugin
     */
    public function enable(): bool;

    /**
     * Disable the plugin
     */
    public function disable(): bool;

    /**
     * Check if plugin is enabled
     */
    public function isEnabled(): bool;

    /**
     * Get plugin configuration
     */
    public function getConfig(): array;

    /**
     * Set plugin configuration
     */
    public function setConfig(array $config): bool;

    /**
     * Get plugin dependencies
     */
    public function getDependencies(): array;

    /**
     * Check if plugin dependencies are satisfied
     */
    public function checkDependencies(): bool;

    /**
     * Cleanup plugin resources
     */
    public function cleanup(): void;
}
