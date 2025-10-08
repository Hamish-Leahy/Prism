<?php

namespace Prism\Backend\Services\Engines;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Exception\WebDriverException;

class ChromiumEngine implements EngineInterface
{
    private array $config;
    private ?RemoteWebDriver $driver = null;
    private bool $initialized = false;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function initialize(): bool
    {
        try {
            $chromeOptions = new ChromeOptions();
            
            // Basic configuration
            if ($this->config['headless'] ?? false) {
                $chromeOptions->addArguments(['--headless']);
            }
            
            if (!($this->config['sandbox'] ?? true)) {
                $chromeOptions->addArguments(['--no-sandbox']);
            }
            
            // Window size
            if (isset($this->config['window_size'])) {
                $size = $this->config['window_size'];
                $chromeOptions->addArguments([
                    "--window-size={$size['width']},{$size['height']}"
                ]);
            }
            
            // User agent
            if (isset($this->config['user_agent'])) {
                $chromeOptions->addArguments([
                    "--user-agent={$this->config['user_agent']}"
                ]);
            }
            
            // Extensions
            if (!($this->config['extensions'] ?? true)) {
                $chromeOptions->addArguments(['--disable-extensions']);
            }
            
            // Additional security options
            $chromeOptions->addArguments([
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--no-first-run',
                '--disable-default-apps',
                '--disable-popup-blocking'
            ]);

            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);

            $this->driver = RemoteWebDriver::create(
                'http://localhost:9515', // ChromeDriver default port
                $capabilities
            );

            $this->initialized = true;
            return true;
        } catch (WebDriverException $e) {
            error_log("Chromium engine initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function navigate(string $url): void
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Engine not initialized');
        }

        try {
            $this->driver->get($url);
            
            // Wait for page to load
            $this->driver->wait(10, 1000)->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::tagName('body'))
            );
        } catch (WebDriverException $e) {
            throw new \RuntimeException("Navigation failed: " . $e->getMessage());
        }
    }

    public function executeScript(string $script): mixed
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Engine not initialized');
        }

        try {
            return $this->driver->executeScript($script);
        } catch (WebDriverException $e) {
            throw new \RuntimeException("Script execution failed: " . $e->getMessage());
        }
    }

    public function getPageContent(): string
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Engine not initialized');
        }

        try {
            return $this->driver->getPageSource();
        } catch (WebDriverException $e) {
            throw new \RuntimeException("Failed to get page content: " . $e->getMessage());
        }
    }

    public function getPageTitle(): string
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Engine not initialized');
        }

        try {
            return $this->driver->getTitle();
        } catch (WebDriverException $e) {
            return 'Unknown Title';
        }
    }

    public function getCurrentUrl(): string
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Engine not initialized');
        }

        try {
            return $this->driver->getCurrentURL();
        } catch (WebDriverException $e) {
            return '';
        }
    }

    public function takeScreenshot(): string
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Engine not initialized');
        }

        try {
            return $this->driver->takeScreenshot();
        } catch (WebDriverException $e) {
            throw new \RuntimeException("Screenshot failed: " . $e->getMessage());
        }
    }

    public function close(): void
    {
        if ($this->driver) {
            try {
                $this->driver->quit();
            } catch (WebDriverException $e) {
                error_log("Error closing Chromium driver: " . $e->getMessage());
            }
            $this->driver = null;
        }
        $this->initialized = false;
    }

    public function isReady(): bool
    {
        return $this->initialized && $this->driver !== null;
    }

    public function getInfo(): array
    {
        return [
            'name' => 'Chromium',
            'version' => $this->getVersion(),
            'capabilities' => [
                'javascript' => true,
                'css' => true,
                'html5' => true,
                'extensions' => $this->config['extensions'] ?? true,
                'sandbox' => $this->config['sandbox'] ?? true
            ],
            'config' => $this->config
        ];
    }

    private function getVersion(): string
    {
        if (!$this->isReady()) {
            return 'Unknown';
        }

        try {
            $capabilities = $this->driver->getCapabilities();
            return $capabilities->getCapability('browserVersion') ?? 'Unknown';
        } catch (WebDriverException $e) {
            return 'Unknown';
        }
    }
}
