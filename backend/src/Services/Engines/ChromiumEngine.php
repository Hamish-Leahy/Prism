<?php

namespace Prism\Backend\Services\Engines;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\WebDriverCookie;

class ChromiumEngine implements EngineInterface
{
    private array $config;
    private ?RemoteWebDriver $driver = null;
    private bool $initialized = false;
    private array $cookies = [];
    private array $localStorage = [];
    private array $sessionStorage = [];

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

    // Cookie Management
    public function getCookies(): array
    {
        if (!$this->isReady()) {
            return [];
        }

        try {
            $cookies = $this->driver->manage()->getCookies();
            $result = [];
            foreach ($cookies as $cookie) {
                $result[] = [
                    'name' => $cookie->getName(),
                    'value' => $cookie->getValue(),
                    'domain' => $cookie->getDomain(),
                    'path' => $cookie->getPath(),
                    'expiry' => $cookie->getExpiry(),
                    'secure' => $cookie->isSecure(),
                    'httpOnly' => $cookie->isHttpOnly()
                ];
            }
            return $result;
        } catch (WebDriverException $e) {
            return [];
        }
    }

    public function setCookie(string $name, string $value, array $options = []): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        try {
            $cookie = new WebDriverCookie(
                $name,
                $value,
                $options['domain'] ?? null,
                $options['path'] ?? null,
                $options['expiry'] ?? null,
                $options['secure'] ?? false,
                $options['httpOnly'] ?? false
            );
            
            $this->driver->manage()->addCookie($cookie);
            return true;
        } catch (WebDriverException $e) {
            return false;
        }
    }

    public function deleteCookie(string $name): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        try {
            $this->driver->manage()->deleteCookieNamed($name);
            return true;
        } catch (WebDriverException $e) {
            return false;
        }
    }

    public function clearCookies(): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        try {
            $this->driver->manage()->deleteAllCookies();
            return true;
        } catch (WebDriverException $e) {
            return false;
        }
    }

    // Local Storage Management
    public function getLocalStorage(): array
    {
        if (!$this->isReady()) {
            return [];
        }

        try {
            $script = "return Object.keys(localStorage).reduce((acc, key) => { acc[key] = localStorage.getItem(key); return acc; }, {});";
            return $this->driver->executeScript($script) ?? [];
        } catch (WebDriverException $e) {
            return [];
        }
    }

    public function setLocalStorageItem(string $key, string $value): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        try {
            $script = "localStorage.setItem(" . json_encode($key) . ", " . json_encode($value) . ");";
            $this->driver->executeScript($script);
            return true;
        } catch (WebDriverException $e) {
            return false;
        }
    }

    public function getLocalStorageItem(string $key): ?string
    {
        if (!$this->isReady()) {
            return null;
        }

        try {
            $script = "return localStorage.getItem(" . json_encode($key) . ");";
            return $this->driver->executeScript($script);
        } catch (WebDriverException $e) {
            return null;
        }
    }

    public function removeLocalStorageItem(string $key): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        try {
            $script = "localStorage.removeItem(" . json_encode($key) . ");";
            $this->driver->executeScript($script);
            return true;
        } catch (WebDriverException $e) {
            return false;
        }
    }

    public function clearLocalStorage(): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        try {
            $script = "localStorage.clear();";
            $this->driver->executeScript($script);
            return true;
        } catch (WebDriverException $e) {
            return false;
        }
    }

    // Session Storage Management
    public function getSessionStorage(): array
    {
        if (!$this->isReady()) {
            return [];
        }

        try {
            $script = "return Object.keys(sessionStorage).reduce((acc, key) => { acc[key] = sessionStorage.getItem(key); return acc; }, {});";
            return $this->driver->executeScript($script) ?? [];
        } catch (WebDriverException $e) {
            return [];
        }
    }

    public function setSessionStorageItem(string $key, string $value): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        try {
            $script = "sessionStorage.setItem(" . json_encode($key) . ", " . json_encode($value) . ");";
            $this->driver->executeScript($script);
            return true;
        } catch (WebDriverException $e) {
            return false;
        }
    }

    public function getSessionStorageItem(string $key): ?string
    {
        if (!$this->isReady()) {
            return null;
        }

        try {
            $script = "return sessionStorage.getItem(" . json_encode($key) . ");";
            return $this->driver->executeScript($script);
        } catch (WebDriverException $e) {
            return null;
        }
    }

    public function removeSessionStorageItem(string $key): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        try {
            $script = "sessionStorage.removeItem(" . json_encode($key) . ");";
            $this->driver->executeScript($script);
            return true;
        } catch (WebDriverException $e) {
            return false;
        }
    }

    public function clearSessionStorage(): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        try {
            $script = "sessionStorage.clear();";
            $this->driver->executeScript($script);
            return true;
        } catch (WebDriverException $e) {
            return false;
        }
    }

    // Download Management
    public function downloadFile(string $url, string $savePath): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        try {
            // Set download preferences
            $prefs = [
                'download.default_directory' => dirname($savePath),
                'download.prompt_for_download' => false,
                'download.directory_upgrade' => true,
                'safebrowsing.enabled' => true
            ];

            $chromeOptions = new ChromeOptions();
            $chromeOptions->setExperimentalOption('prefs', $prefs);
            
            // Navigate to download URL
            $this->driver->get($url);
            
            // Wait for download to complete (simplified)
            sleep(2);
            
            return true;
        } catch (WebDriverException $e) {
            return false;
        }
    }

    // User Agent Management
    public function setUserAgent(string $userAgent): bool
    {
        if ($this->initialized) {
            // Cannot change user agent after initialization
            return false;
        }

        $this->config['user_agent'] = $userAgent;
        return true;
    }

    public function getUserAgent(): string
    {
        if (!$this->isReady()) {
            return $this->config['user_agent'] ?? 'Mozilla/5.0 (compatible; Prism Browser)';
        }

        try {
            $script = "return navigator.userAgent;";
            return $this->driver->executeScript($script) ?? $this->config['user_agent'] ?? 'Mozilla/5.0 (compatible; Prism Browser)';
        } catch (WebDriverException $e) {
            return $this->config['user_agent'] ?? 'Mozilla/5.0 (compatible; Prism Browser)';
        }
    }

    // Performance Metrics
    public function getPerformanceMetrics(): array
    {
        if (!$this->isReady()) {
            return [];
        }

        try {
            $script = "
                const perfData = performance.getEntriesByType('navigation')[0];
                return {
                    loadTime: perfData.loadEventEnd - perfData.loadEventStart,
                    domContentLoaded: perfData.domContentLoadedEventEnd - perfData.domContentLoadedEventStart,
                    firstPaint: performance.getEntriesByName('first-paint')[0]?.startTime || 0,
                    firstContentfulPaint: performance.getEntriesByName('first-contentful-paint')[0]?.startTime || 0
                };
            ";
            return $this->driver->executeScript($script) ?? [];
        } catch (WebDriverException $e) {
            return [];
        }
    }

    // Memory Usage
    public function getMemoryUsage(): array
    {
        if (!$this->isReady()) {
            return [];
        }

        try {
            $script = "
                if (performance.memory) {
                    return {
                        used: performance.memory.usedJSHeapSize,
                        total: performance.memory.totalJSHeapSize,
                        limit: performance.memory.jsHeapSizeLimit
                    };
                }
                return {};
            ";
            return $this->driver->executeScript($script) ?? [];
        } catch (WebDriverException $e) {
            return [];
        }
    }

    // Cache Statistics
    public function getCacheStats(): array
    {
        if (!$this->isReady()) {
            return [];
        }

        try {
            $script = "
                if ('caches' in window) {
                    return caches.keys().then(keys => ({ cacheCount: keys.length }));
                }
                return { cacheCount: 0 };
            ";
            return $this->driver->executeScript($script) ?? ['cacheCount' => 0];
        } catch (WebDriverException $e) {
            return ['cacheCount' => 0];
        }
    }
}
