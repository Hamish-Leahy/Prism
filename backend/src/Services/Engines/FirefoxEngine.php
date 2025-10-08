<?php

namespace Prism\Backend\Services\Engines;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Firefox\FirefoxProfile;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Exception\WebDriverException;

class FirefoxEngine implements EngineInterface
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
            $firefoxOptions = new FirefoxOptions();
            $profile = new FirefoxProfile();

            // Basic configuration
            if ($this->config['headless'] ?? false) {
                $firefoxOptions->addArguments(['--headless']);
            }

            // Privacy settings
            if ($this->config['private_mode'] ?? true) {
                $profile->setPreference('browser.privatebrowsing.autostart', true);
            }

            if ($this->config['tracking_protection'] ?? true) {
                $profile->setPreference('privacy.trackingprotection.enabled', true);
                $profile->setPreference('privacy.trackingprotection.pbmode.enabled', true);
            }

            if ($this->config['do_not_track'] ?? true) {
                $profile->setPreference('privacy.donottrackheader.enabled', true);
                $profile->setPreference('privacy.donottrackheader.value', 1);
            }

            // Cookie settings
            if ($this->config['block_third_party'] ?? true) {
                $profile->setPreference('network.cookie.cookieBehavior', 1);
            }

            // Extensions
            if (!($this->config['extensions'] ?? true)) {
                $profile->setPreference('extensions.enabledScopes', 0);
            }

            // Window size
            if (isset($this->config['window_size'])) {
                $size = $this->config['window_size'];
                $profile->setPreference('browser.window.width', $size['width']);
                $profile->setPreference('browser.window.height', $size['height']);
            }

            // User agent
            if (isset($this->config['user_agent'])) {
                $profile->setPreference('general.useragent.override', $this->config['user_agent']);
            }

            // Additional privacy preferences
            $profile->setPreference('privacy.clearOnShutdown.cookies', true);
            $profile->setPreference('privacy.clearOnShutdown.history', true);
            $profile->setPreference('privacy.clearOnShutdown.downloads', true);
            $profile->setPreference('browser.cache.disk.enable', false);
            $profile->setPreference('browser.cache.memory.enable', false);

            $firefoxOptions->setProfile($profile);

            $capabilities = DesiredCapabilities::firefox();
            $capabilities->setCapability(FirefoxOptions::CAPABILITY, $firefoxOptions);

            $this->driver = RemoteWebDriver::create(
                'http://localhost:4444', // GeckoDriver default port
                $capabilities
            );

            $this->initialized = true;
            return true;
        } catch (WebDriverException $e) {
            error_log("Firefox engine initialization failed: " . $e->getMessage());
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
                error_log("Error closing Firefox driver: " . $e->getMessage());
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
            'name' => 'Firefox',
            'version' => $this->getVersion(),
            'capabilities' => [
                'javascript' => true,
                'css' => true,
                'html5' => true,
                'extensions' => $this->config['extensions'] ?? true,
                'private_mode' => $this->config['private_mode'] ?? true,
                'tracking_protection' => $this->config['tracking_protection'] ?? true
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
