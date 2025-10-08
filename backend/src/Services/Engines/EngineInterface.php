<?php

namespace Prism\Backend\Services\Engines;

interface EngineInterface
{
    /**
     * Initialize the engine
     */
    public function initialize(): bool;

    /**
     * Navigate to a URL
     */
    public function navigate(string $url): void;

    /**
     * Execute JavaScript code
     */
    public function executeScript(string $script): mixed;

    /**
     * Get the current page content
     */
    public function getPageContent(): string;

    /**
     * Get the current page title
     */
    public function getPageTitle(): string;

    /**
     * Get the current URL
     */
    public function getCurrentUrl(): string;

    /**
     * Take a screenshot
     */
    public function takeScreenshot(): string;

    /**
     * Close the engine
     */
    public function close(): void;

    /**
     * Check if the engine is ready
     */
    public function isReady(): bool;

    /**
     * Get engine information
     */
    public function getInfo(): array;
}
