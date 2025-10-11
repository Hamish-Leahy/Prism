<?php

namespace Prism\Backend\Services\Engines;

use Prism\Backend\Services\HttpClientService;
use Prism\Backend\Services\Html5ParserService;
use Prism\Backend\Services\CssParserService;
use Prism\Backend\Services\CssRendererService;
use Prism\Backend\Services\JavaScriptEngineService;
use Prism\Backend\Services\CookieJarService;
use Prism\Backend\Services\WebSocketService;
use Prism\Backend\Services\CacheService;
use Prism\Backend\Services\WebRTCService;
use DOMDocument;
use DOMXPath;
use Monolog\Logger;

class PrismEngine implements EngineInterface
{
    private array $config;
    private ?HttpClientService $httpClient = null;
    private ?Html5ParserService $htmlParser = null;
    private ?CssParserService $cssParser = null;
    private ?CssRendererService $cssRenderer = null;
    private ?JavaScriptEngineService $jsEngine = null;
    private ?CookieJarService $cookieJar = null;
    private ?WebSocketService $webSocketService = null;
    private ?CacheService $cacheService = null;
    private ?WebRTCService $webRTCService = null;
    private ?DOMDocument $dom = null;
    private string $currentUrl = '';
    private string $pageContent = '';
    private bool $initialized = false;
    private Logger $logger;
    private array $pageMetadata = [];
    private array $localStorage = [];
    private array $sessionStorage = [];
    private string $localStoragePath;
    private int $localStorageQuota = 5242880; // 5MB default quota
    private int $sessionStorageQuota = 1048576; // 1MB default quota
    private array $parsedData = [];
    private array $cssData = [];
    private array $renderedElements = [];
    private array $jsData = [];
    private array $executedScripts = [];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->logger = new Logger('prism-engine');
        $this->localStoragePath = $config['local_storage_path'] ?? sys_get_temp_dir() . '/prism_local_storage.json';
        $this->localStorageQuota = $config['local_storage_quota'] ?? 5242880; // 5MB
        $this->sessionStorageQuota = $config['session_storage_quota'] ?? 1048576; // 1MB
    }

    public function initialize(): bool
    {
        try {
            // Initialize advanced HTTP client
            $httpConfig = [
                'timeout' => $this->config['timeout'] ?? 30,
                'connect_timeout' => $this->config['connect_timeout'] ?? 10,
                'read_timeout' => $this->config['read_timeout'] ?? 30,
                'verify_ssl' => $this->config['verify_ssl'] ?? true,
                'max_redirects' => $this->config['max_redirects'] ?? 10,
                'strict_redirects' => $this->config['strict_redirects'] ?? false,
                'follow_referer' => $this->config['follow_referer'] ?? true,
                'allowed_protocols' => $this->config['allowed_protocols'] ?? ['http', 'https'],
                'enable_cookies' => $this->config['cookies_enabled'] ?? true,
                'cache_ttl' => $this->config['cache_ttl'] ?? 300,
                'max_retries' => $this->config['max_retries'] ?? 3,
                'user_agent' => $this->config['user_agent'] ?? 'Prism/1.0 (Custom Engine)',
            ];

            $this->httpClient = new HttpClientService($httpConfig, $this->logger);

            // Initialize HTML5 parser
            $parserConfig = [
                'preserve_whitespace' => $this->config['preserve_whitespace'] ?? false,
                'format_output' => $this->config['format_output'] ?? false,
                'strict_error_checking' => $this->config['strict_error_checking'] ?? false,
                'recover' => $this->config['recover'] ?? true,
                'substitute_entities' => $this->config['substitute_entities'] ?? true,
                'validate_on_parse' => $this->config['validate_on_parse'] ?? false,
                'normalize_whitespace' => $this->config['normalize_whitespace'] ?? true,
            ];
            
            $this->htmlParser = new Html5ParserService($parserConfig, $this->logger);

            // Initialize CSS parser
            $cssConfig = require __DIR__ . '/../../config/css_parser.php';
            $this->cssParser = new CssParserService($cssConfig, $this->logger);

            // Initialize CSS renderer
            $this->cssRenderer = new CssRendererService($cssConfig, $this->logger);

            // Initialize JavaScript engine
            $jsConfig = require __DIR__ . '/../../config/javascript_engine.php';
            $this->jsEngine = new JavaScriptEngineService($jsConfig, $this->logger);
            $this->jsEngine->initialize();

            // Initialize cookie jar
            $cookieConfig = [
                'storage_path' => $this->config['cookie_storage_path'] ?? sys_get_temp_dir() . '/prism_cookies.json',
                'persistent' => $this->config['cookie_persistent'] ?? true
            ];
            $this->cookieJar = new CookieJarService($cookieConfig, $this->logger);

            // Initialize local storage
            $this->loadLocalStorage();

            // Initialize WebSocket service
            $wsConfig = [
                'enabled' => $this->config['websocket_enabled'] ?? true,
                'timeout' => $this->config['websocket_timeout'] ?? 30,
                'max_connections' => $this->config['websocket_max_connections'] ?? 10
            ];
            $this->webSocketService = new WebSocketService($wsConfig, $this->logger);
            $this->webSocketService->initialize();

            // Initialize cache service
            $cacheConfig = [
                'cache_path' => $this->config['cache_path'] ?? sys_get_temp_dir() . '/prism_cache',
                'max_memory_size' => $this->config['cache_memory_size'] ?? 67108864, // 64MB
                'max_disk_size' => $this->config['cache_disk_size'] ?? 1073741824, // 1GB
                'default_ttl' => $this->config['cache_default_ttl'] ?? 3600, // 1 hour
                'persistent' => $this->config['cache_persistent'] ?? true
            ];
            $this->cacheService = new CacheService($cacheConfig, $this->logger);

            // Initialize legacy DOM parser for backward compatibility
            $this->dom = new DOMDocument();
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = true;

            // Initialize page metadata
            $this->pageMetadata = [
                'title' => '',
                'description' => '',
                'keywords' => '',
                'author' => '',
                'viewport' => '',
                'canonical' => '',
                'og_title' => '',
                'og_description' => '',
                'og_image' => '',
                'twitter_card' => '',
                'robots' => '',
                'charset' => 'utf-8',
                'language' => 'en',
                'last_modified' => null,
                'content_type' => 'text/html',
                'content_length' => 0,
                'server' => '',
                'response_time' => 0
            ];

            $this->initialized = true;
            $this->logger->info("Prism engine initialized successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Prism engine initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function navigate(string $url): void
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Engine not initialized');
        }

        try {
            $startTime = microtime(true);
            $this->currentUrl = $url;
            
            $this->logger->info("Navigating to URL", ['url' => $url]);
            
            // Get cookies for this domain
            $parsedUrl = parse_url($url);
            $domain = $parsedUrl['host'] ?? '';
            $path = $parsedUrl['path'] ?? '/';
            
            // Add cookies to request headers
            $cookieHeader = $this->cookieJar->generateCookieHeader($domain, $path);
            if (!empty($cookieHeader)) {
                $this->httpClient->setHeaders(['Cookie' => $cookieHeader]);
            }
            
            // Fetch the page content using advanced HTTP client
            $response = $this->httpClient->get($url);
            
            // Parse and store cookies from response
            if (isset($response['headers'])) {
                $cookies = $this->cookieJar->parseCookiesFromHeaders($response['headers'], $domain, $path);
                foreach ($cookies as $cookie) {
                    $this->cookieJar->setCookie($cookie['name'], $cookie['value'], $cookie);
                }
            }
            
            if (!$response['success']) {
                throw new \RuntimeException("Navigation failed: " . ($response['error'] ?? 'Unknown error'));
            }
            
            $this->pageContent = $response['body'];
            $this->pageMetadata['response_time'] = microtime(true) - $startTime;
            $this->pageMetadata['content_type'] = $response['headers']['content-type'][0] ?? 'text/html';
            $this->pageMetadata['content_length'] = strlen($this->pageContent);
            $this->pageMetadata['server'] = $response['headers']['server'][0] ?? '';
            $this->pageMetadata['last_modified'] = $response['headers']['last-modified'][0] ?? null;
            
            // Update current URL to final URL after redirects
            if (!empty($response['final_url'])) {
                $this->currentUrl = $response['final_url'];
            }
            
            // Parse HTML if enabled
            if ($this->config['html_parsing'] ?? true) {
                $this->parseHtml();
                $this->parseHtml5();
                $this->extractMetadata();
            }
            
            // Parse CSS if enabled
            if ($this->config['css_parsing'] ?? true) {
                $this->parseCss();
            }
            
            // Execute JavaScript if enabled
            if ($this->config['javascript_execution'] ?? true) {
                $this->executeJavaScript();
            }
            
            $this->logger->info("Navigation completed", [
                'url' => $this->currentUrl,
                'status' => $response['status'],
                'size' => $this->pageMetadata['content_length'],
                'time' => $this->pageMetadata['response_time']
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error("Navigation failed", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Navigation failed: " . $e->getMessage());
        }
    }

    public function executeScript(string $script): mixed
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Engine not initialized');
        }

        if (!($this->config['javascript_enabled'] ?? true)) {
            throw new \RuntimeException('JavaScript execution is disabled');
        }

        try {
            // Basic JavaScript execution using V8 if available
            if (extension_loaded('v8js')) {
                $v8 = new \V8Js();
                $v8->executeString($script);
                return $v8->executeString('return ' . $script);
            } else {
                // Fallback: basic string manipulation
                return $this->executeBasicScript($script);
            }
        } catch (\Exception $e) {
            throw new \RuntimeException("Script execution failed: " . $e->getMessage());
        }
    }

    public function getPageContent(): string
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Engine not initialized');
        }

        return $this->pageContent;
    }

    public function getPageTitle(): string
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Engine not initialized');
        }

        // Return cached title if available
        if (!empty($this->pageMetadata['title'])) {
            return $this->pageMetadata['title'];
        }

        if ($this->dom) {
            $titleElements = $this->dom->getElementsByTagName('title');
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                $this->pageMetadata['title'] = $title;
                return $title;
            }
        }

        // Fallback: extract title from HTML
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $this->pageContent, $matches)) {
            $title = trim($matches[1]);
            $this->pageMetadata['title'] = $title;
            return $title;
        }

        return 'Untitled';
    }

    public function getCurrentUrl(): string
    {
        return $this->currentUrl;
    }

    public function takeScreenshot(): string
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Engine not initialized');
        }

        // Prism engine doesn't support screenshots in headless mode
        // This would require a headless browser or image generation
        throw new \RuntimeException('Screenshots not supported in Prism engine');
    }


    public function isReady(): bool
    {
        return $this->initialized && $this->httpClient !== null;
    }

    public function getInfo(): array
    {
        return [
            'name' => 'Prism',
            'version' => '1.0.0',
            'capabilities' => [
                'javascript' => $this->config['javascript_enabled'] ?? true,
                'css' => $this->config['css_enabled'] ?? true,
                'html5' => true,
                'images' => $this->config['images_enabled'] ?? true,
                'cookies' => $this->config['cookies_enabled'] ?? true,
                'local_storage' => $this->config['local_storage_enabled'] ?? false,
                'session_storage' => $this->config['session_storage_enabled'] ?? true,
                'websockets' => $this->config['websocket_enabled'] ?? true,
                'caching' => $this->config['cache_enabled'] ?? true,
                'screenshots' => false
            ],
            'config' => $this->config,
            'features' => [
                'lightweight' => true,
                'fast' => true,
                'customizable' => true,
                'privacy_focused' => true
            ]
        ];
    }

    private function parseHtml(): void
    {
        if (empty($this->pageContent)) {
            return;
        }

        try {
            // Suppress HTML parsing errors
            libxml_use_internal_errors(true);
            
            // Load HTML content
            $this->dom->loadHTML($this->pageContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            
            // Clear libxml errors
            libxml_clear_errors();
            
        } catch (\Exception $e) {
            $this->logger->error("HTML parsing failed: " . $e->getMessage());
        }
    }

    private function parseHtml5(): void
    {
        if (empty($this->pageContent) || !$this->htmlParser) {
            return;
        }

        try {
            $this->parsedData = $this->htmlParser->parseHtml($this->pageContent, $this->currentUrl);
            
            $this->logger->debug("HTML5 parsing completed", [
                'elements_count' => $this->parsedData['performance']['dom_elements'] ?? 0,
                'links_count' => count($this->parsedData['links'] ?? []),
                'images_count' => count($this->parsedData['media']['images'] ?? []),
                'forms_count' => count($this->parsedData['forms'] ?? [])
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error("HTML5 parsing failed: " . $e->getMessage());
        }
    }

    private function executeBasicScript(string $script): mixed
    {
        // Very basic script execution for simple operations
        // This is a fallback when V8 is not available
        
        // Remove common JavaScript patterns and return basic results
        $script = trim($script);
        
        if (strpos($script, 'document.title') !== false) {
            return $this->getPageTitle();
        }
        
        if (strpos($script, 'document.URL') !== false) {
            return $this->getCurrentUrl();
        }
        
        if (strpos($script, 'document.body.innerHTML') !== false) {
            return $this->getPageContent();
        }
        
        // Return null for unsupported operations
        return null;
    }

    private function getMemoryUsage(): int
    {
        return memory_get_usage(true);
    }

    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
        return $this->parseMemoryLimit($limit);
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int) $limit;
        
        switch ($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }
        
        return $limit;
    }

    private function extractMetadata(): void
    {
        if (!$this->dom) {
            return;
        }

        try {
            $xpath = new DOMXPath($this->dom);

            // Extract basic meta tags
            $this->pageMetadata['description'] = $this->extractMetaContent($xpath, 'description');
            $this->pageMetadata['keywords'] = $this->extractMetaContent($xpath, 'keywords');
            $this->pageMetadata['author'] = $this->extractMetaContent($xpath, 'author');
            $this->pageMetadata['viewport'] = $this->extractMetaContent($xpath, 'viewport');
            $this->pageMetadata['robots'] = $this->extractMetaContent($xpath, 'robots');
            $this->pageMetadata['canonical'] = $this->extractLinkHref($xpath, 'canonical');

            // Extract Open Graph meta tags
            $this->pageMetadata['og_title'] = $this->extractMetaContent($xpath, 'og:title');
            $this->pageMetadata['og_description'] = $this->extractMetaContent($xpath, 'og:description');
            $this->pageMetadata['og_image'] = $this->extractMetaContent($xpath, 'og:image');

            // Extract Twitter Card meta tags
            $this->pageMetadata['twitter_card'] = $this->extractMetaContent($xpath, 'twitter:card');

            // Extract charset
            $charsetNodes = $xpath->query('//meta[@charset]');
            if ($charsetNodes->length > 0) {
                $this->pageMetadata['charset'] = $charsetNodes->item(0)->getAttribute('charset');
            }

            // Extract language
            $htmlNodes = $xpath->query('//html[@lang]');
            if ($htmlNodes->length > 0) {
                $this->pageMetadata['language'] = $htmlNodes->item(0)->getAttribute('lang');
            }

        } catch (\Exception $e) {
            $this->logger->warning("Failed to extract metadata: " . $e->getMessage());
        }
    }

    private function extractMetaContent(DOMXPath $xpath, string $name): string
    {
        $nodes = $xpath->query("//meta[@name='$name' or @property='$name']");
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->getAttribute('content'));
        }
        return '';
    }

    private function extractLinkHref(DOMXPath $xpath, string $rel): string
    {
        $nodes = $xpath->query("//link[@rel='$rel']");
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->getAttribute('href'));
        }
        return '';
    }

    public function getPageMetadata(): array
    {
        return $this->pageMetadata;
    }

    public function getPageDescription(): string
    {
        return $this->pageMetadata['description'] ?? '';
    }

    public function getPageKeywords(): string
    {
        return $this->pageMetadata['keywords'] ?? '';
    }

    public function getPageAuthor(): string
    {
        return $this->pageMetadata['author'] ?? '';
    }

    public function getPageLanguage(): string
    {
        return $this->pageMetadata['language'] ?? 'en';
    }

    public function getResponseTime(): float
    {
        return $this->pageMetadata['response_time'] ?? 0.0;
    }

    public function getContentType(): string
    {
        return $this->pageMetadata['content_type'] ?? 'text/html';
    }

    public function getContentLength(): int
    {
        return $this->pageMetadata['content_length'] ?? 0;
    }

    public function getServer(): string
    {
        return $this->pageMetadata['server'] ?? '';
    }

    public function getLastModified(): ?string
    {
        return $this->pageMetadata['last_modified'];
    }

    public function downloadResource(string $url, string $destination): array
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Engine not initialized');
        }

        return $this->httpClient->download($url, $destination);
    }

    public function postData(string $url, array $data): array
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Engine not initialized');
        }

        return $this->httpClient->post($url, $data);
    }

    public function getRequestHistory(): array
    {
        if (!$this->isReady()) {
            return [];
        }

        return $this->httpClient->getRequestHistory();
    }

    public function clearCache(): void
    {
        if ($this->httpClient) {
            $this->httpClient->clearCache();
        }
    }

    public function getHttpCacheStats(): array
    {
        if (!$this->isReady()) {
            return [];
        }

        return $this->httpClient->getCacheStats();
    }

    public function setProxy(string $proxy): void
    {
        if ($this->httpClient) {
            $this->httpClient->setProxy($proxy);
        }
    }

    public function setCustomHeaders(array $headers): void
    {
        if ($this->httpClient) {
            $this->httpClient->setHeaders($headers);
        }
    }

    public function setTimeout(int $timeout): void
    {
        if ($this->httpClient) {
            $this->httpClient->setTimeout($timeout);
        }
    }

    public function getCookies(): array
    {
        if (!$this->cookieJar) {
            return [];
        }
        return $this->cookieJar->getAllCookies();
    }

    public function setCookie(string $name, string $value, array $options = []): void
    {
        if ($this->cookieJar) {
            $this->cookieJar->setCookie($name, $value, $options);
        }
    }

    public function getCookie(string $name, string $domain = '', string $path = '/'): ?string
    {
        if (!$this->cookieJar) {
            return null;
        }
        return $this->cookieJar->getCookie($name, $domain, $path);
    }

    public function removeCookie(string $name, string $domain = '', string $path = '/'): bool
    {
        if (!$this->cookieJar) {
            return false;
        }
        return $this->cookieJar->removeCookie($name, $domain, $path);
    }

    public function clearCookies(): bool
    {
        if (!$this->cookieJar) {
            return false;
        }
        return $this->cookieJar->clearAllCookies();
    }

    public function getCookiesForDomain(string $domain): array
    {
        if (!$this->cookieJar) {
            return [];
        }
        return $this->cookieJar->getCookiesForDomain($domain);
    }

    public function getCookieStats(): array
    {
        if (!$this->cookieJar) {
            return [];
        }
        return $this->cookieJar->getStats();
    }

    public function cleanupExpiredCookies(): int
    {
        if (!$this->cookieJar) {
            return 0;
        }
        return $this->cookieJar->cleanupExpiredCookies();
    }

    /**
     * Load local storage from persistent storage
     */
    private function loadLocalStorage(): void
    {
        try {
            if (file_exists($this->localStoragePath)) {
                $content = file_get_contents($this->localStoragePath);
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $this->localStorage = $data;
                    $this->logger->debug("Local storage loaded", [
                        'items_count' => count($this->localStorage),
                        'file_size' => strlen($content)
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to load local storage: " . $e->getMessage());
        }
    }

    /**
     * Save local storage to persistent storage
     */
    private function saveLocalStorage(): void
    {
        try {
            $data = json_encode($this->localStorage, JSON_PRETTY_PRINT);
            file_put_contents($this->localStoragePath, $data, LOCK_EX);
            $this->logger->debug("Local storage saved", [
                'items_count' => count($this->localStorage),
                'file_size' => strlen($data)
            ]);
        } catch (\Exception $e) {
            $this->logger->error("Failed to save local storage: " . $e->getMessage());
        }
    }

    /**
     * Calculate storage size in bytes
     */
    private function calculateStorageSize(array $storage): int
    {
        $size = 0;
        foreach ($storage as $key => $value) {
            $size += strlen($key) + strlen($value);
        }
        return $size;
    }

    /**
     * Check if storage quota would be exceeded
     */
    private function wouldExceedQuota(array $storage, string $key, string $value, int $quota): bool
    {
        $currentSize = $this->calculateStorageSize($storage);
        $newSize = $currentSize + strlen($key) + strlen($value);
        return $newSize > $quota;
    }

    /**
     * Get all local storage items
     */
    public function getLocalStorage(): array
    {
        return $this->localStorage;
    }

    /**
     * Set a local storage item
     */
    public function setLocalStorageItem(string $key, string $value): void
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Storage key cannot be empty');
        }

        if ($this->wouldExceedQuota($this->localStorage, $key, $value, $this->localStorageQuota)) {
            throw new \RuntimeException('Storage quota exceeded');
        }

        $oldValue = $this->localStorage[$key] ?? null;
        $this->localStorage[$key] = $value;
        
        // Save to persistent storage
        $this->saveLocalStorage();
        
        // Dispatch storage event
        $this->dispatchStorageEvent('localStorage', 'setItem', $key, $oldValue, $value);
        
        $this->logger->debug("Local storage item set", [
            'key' => $key,
            'value_length' => strlen($value),
            'storage_size' => $this->calculateStorageSize($this->localStorage)
        ]);
    }

    /**
     * Get a local storage item
     */
    public function getLocalStorageItem(string $key): ?string
    {
        return $this->localStorage[$key] ?? null;
    }

    /**
     * Remove a local storage item
     */
    public function removeLocalStorageItem(string $key): void
    {
        if (array_key_exists($key, $this->localStorage)) {
            $oldValue = $this->localStorage[$key];
            unset($this->localStorage[$key]);
            
            // Save to persistent storage
            $this->saveLocalStorage();
            
            // Dispatch storage event
            $this->dispatchStorageEvent('localStorage', 'removeItem', $key, $oldValue, null);
            
            $this->logger->debug("Local storage item removed", [
                'key' => $key,
                'storage_size' => $this->calculateStorageSize($this->localStorage)
            ]);
        }
    }

    /**
     * Clear all local storage items
     */
    public function clearLocalStorage(): void
    {
        $itemCount = count($this->localStorage);
        $this->localStorage = [];
        
        // Save to persistent storage
        $this->saveLocalStorage();
        
        // Dispatch storage event
        $this->dispatchStorageEvent('localStorage', 'clear', null, null, null);
        
        $this->logger->debug("Local storage cleared", [
            'items_removed' => $itemCount
        ]);
    }

    /**
     * Get local storage quota information
     */
    public function getLocalStorageQuota(): array
    {
        $used = $this->calculateStorageSize($this->localStorage);
        return [
            'used' => $used,
            'quota' => $this->localStorageQuota,
            'available' => $this->localStorageQuota - $used,
            'percentage' => round(($used / $this->localStorageQuota) * 100, 2)
        ];
    }

    /**
     * Get local storage statistics
     */
    public function getLocalStorageStats(): array
    {
        return [
            'items_count' => count($this->localStorage),
            'total_size' => $this->calculateStorageSize($this->localStorage),
            'quota' => $this->getLocalStorageQuota(),
            'keys' => array_keys($this->localStorage)
        ];
    }

    /**
     * Session Storage Methods
     */

    /**
     * Get all session storage items
     */
    public function getSessionStorage(): array
    {
        return $this->sessionStorage;
    }

    /**
     * Set a session storage item
     */
    public function setSessionStorageItem(string $key, string $value): void
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Storage key cannot be empty');
        }

        if ($this->wouldExceedQuota($this->sessionStorage, $key, $value, $this->sessionStorageQuota)) {
            throw new \RuntimeException('Session storage quota exceeded');
        }

        $oldValue = $this->sessionStorage[$key] ?? null;
        $this->sessionStorage[$key] = $value;
        
        // Dispatch storage event
        $this->dispatchStorageEvent('sessionStorage', 'setItem', $key, $oldValue, $value);
        
        $this->logger->debug("Session storage item set", [
            'key' => $key,
            'value_length' => strlen($value),
            'storage_size' => $this->calculateStorageSize($this->sessionStorage)
        ]);
    }

    /**
     * Get a session storage item
     */
    public function getSessionStorageItem(string $key): ?string
    {
        return $this->sessionStorage[$key] ?? null;
    }

    /**
     * Remove a session storage item
     */
    public function removeSessionStorageItem(string $key): void
    {
        if (array_key_exists($key, $this->sessionStorage)) {
            $oldValue = $this->sessionStorage[$key];
            unset($this->sessionStorage[$key]);
            
            // Dispatch storage event
            $this->dispatchStorageEvent('sessionStorage', 'removeItem', $key, $oldValue, null);
            
            $this->logger->debug("Session storage item removed", [
                'key' => $key,
                'storage_size' => $this->calculateStorageSize($this->sessionStorage)
            ]);
        }
    }

    /**
     * Clear all session storage items
     */
    public function clearSessionStorage(): void
    {
        $itemCount = count($this->sessionStorage);
        $this->sessionStorage = [];
        
        // Dispatch storage event
        $this->dispatchStorageEvent('sessionStorage', 'clear', null, null, null);
        
        $this->logger->debug("Session storage cleared", [
            'items_removed' => $itemCount
        ]);
    }

    /**
     * Get session storage quota information
     */
    public function getSessionStorageQuota(): array
    {
        $used = $this->calculateStorageSize($this->sessionStorage);
        return [
            'used' => $used,
            'quota' => $this->sessionStorageQuota,
            'available' => $this->sessionStorageQuota - $used,
            'percentage' => round(($used / $this->sessionStorageQuota) * 100, 2)
        ];
    }

    /**
     * Get session storage statistics
     */
    public function getSessionStorageStats(): array
    {
        return [
            'items_count' => count($this->sessionStorage),
            'total_size' => $this->calculateStorageSize($this->sessionStorage),
            'quota' => $this->getSessionStorageQuota(),
            'keys' => array_keys($this->sessionStorage)
        ];
    }

    /**
     * Dispatch storage change event
     */
    private function dispatchStorageEvent(string $storageType, string $action, ?string $key, ?string $oldValue, ?string $newValue): void
    {
        // This would typically dispatch to JavaScript event listeners
        // For now, we'll just log the event
        $this->logger->debug("Storage event dispatched", [
            'storage_type' => $storageType,
            'action' => $action,
            'key' => $key,
            'old_value' => $oldValue,
            'new_value' => $newValue
        ]);
    }

    /**
     * WebSocket Methods
     */

    /**
     * Connect to a WebSocket server
     */
    public function connectWebSocket(string $url, array $options = []): ?string
    {
        if (!$this->webSocketService) {
            throw new \RuntimeException('WebSocket service not initialized');
        }

        return $this->webSocketService->connect($url, $options);
    }

    /**
     * Send data through WebSocket connection
     */
    public function sendWebSocketData(string $connectionId, string $data, int $opcode = 1): bool
    {
        if (!$this->webSocketService) {
            throw new \RuntimeException('WebSocket service not initialized');
        }

        return $this->webSocketService->send($connectionId, $data, $opcode);
    }

    /**
     * Receive data from WebSocket connection
     */
    public function receiveWebSocketData(string $connectionId): ?string
    {
        if (!$this->webSocketService) {
            throw new \RuntimeException('WebSocket service not initialized');
        }

        return $this->webSocketService->receive($connectionId);
    }

    /**
     * Close WebSocket connection
     */
    public function closeWebSocket(string $connectionId): bool
    {
        if (!$this->webSocketService) {
            throw new \RuntimeException('WebSocket service not initialized');
        }

        return $this->webSocketService->close($connectionId);
    }

    /**
     * Get WebSocket connection status
     */
    public function getWebSocketStatus(string $connectionId): ?array
    {
        if (!$this->webSocketService) {
            return null;
        }

        return $this->webSocketService->getConnectionStatus($connectionId);
    }

    /**
     * Get all WebSocket connections
     */
    public function getWebSocketConnections(): array
    {
        if (!$this->webSocketService) {
            return [];
        }

        return $this->webSocketService->getConnections();
    }

    /**
     * Add WebSocket event listener
     */
    public function addWebSocketEventListener(string $event, callable $listener): void
    {
        if (!$this->webSocketService) {
            throw new \RuntimeException('WebSocket service not initialized');
        }

        $this->webSocketService->addEventListener($event, $listener);
    }

    /**
     * Remove WebSocket event listener
     */
    public function removeWebSocketEventListener(string $event, callable $listener): void
    {
        if (!$this->webSocketService) {
            throw new \RuntimeException('WebSocket service not initialized');
        }

        $this->webSocketService->removeEventListener($event, $listener);
    }

    /**
     * Get WebSocket service statistics
     */
    public function getWebSocketStats(): array
    {
        if (!$this->webSocketService) {
            return [];
        }

        return $this->webSocketService->getStats();
    }

    /**
     * Check if WebSocket service is initialized
     */
    public function isWebSocketInitialized(): bool
    {
        return $this->webSocketService && $this->webSocketService->isInitialized();
    }

    /**
     * Cache Methods
     */

    /**
     * Store data in cache
     */
    public function cacheSet(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (!$this->cacheService) {
            throw new \RuntimeException('Cache service not initialized');
        }

        return $this->cacheService->set($key, $value, $ttl);
    }

    /**
     * Retrieve data from cache
     */
    public function cacheGet(string $key): mixed
    {
        if (!$this->cacheService) {
            throw new \RuntimeException('Cache service not initialized');
        }

        return $this->cacheService->get($key);
    }

    /**
     * Check if cache item exists
     */
    public function cacheHas(string $key): bool
    {
        if (!$this->cacheService) {
            return false;
        }

        return $this->cacheService->has($key);
    }

    /**
     * Delete cache item
     */
    public function cacheDelete(string $key): bool
    {
        if (!$this->cacheService) {
            throw new \RuntimeException('Cache service not initialized');
        }

        return $this->cacheService->delete($key);
    }

    /**
     * Clear all cache items
     */
    public function cacheClear(): bool
    {
        if (!$this->cacheService) {
            throw new \RuntimeException('Cache service not initialized');
        }

        return $this->cacheService->clear();
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        if (!$this->cacheService) {
            return [];
        }

        return $this->cacheService->getStats();
    }

    /**
     * Get cache item metadata
     */
    public function getCacheMetadata(string $key): ?array
    {
        if (!$this->cacheService) {
            return null;
        }

        return $this->cacheService->getMetadata($key);
    }

    /**
     * Clean up expired cache items
     */
    public function cacheCleanup(): int
    {
        if (!$this->cacheService) {
            return 0;
        }

        return $this->cacheService->cleanup();
    }

    /**
     * Set cache configuration
     */
    public function setCacheConfig(array $config): void
    {
        if (!$this->cacheService) {
            throw new \RuntimeException('Cache service not initialized');
        }

        $this->cacheService->setConfig($config);
    }

    /**
     * Get cache configuration
     */
    public function getCacheConfig(): array
    {
        if (!$this->cacheService) {
            return [];
        }

        return $this->cacheService->getConfig();
    }

    /**
     * Check if cache service is initialized
     */
    public function isCacheInitialized(): bool
    {
        return $this->cacheService !== null;
    }

    public function getPerformanceMetrics(): array
    {
        return [
            'response_time' => $this->getResponseTime(),
            'content_length' => $this->getContentLength(),
            'memory_usage' => $this->getMemoryUsage(),
            'memory_limit' => $this->getMemoryLimit(),
            'cache_stats' => $this->getCacheStats(),
            'request_count' => count($this->getRequestHistory())
        ];
    }

    // HTML5 Parser Methods

    public function getParsedData(): array
    {
        return $this->parsedData;
    }

    public function getPageStructure(): array
    {
        return $this->parsedData['structure'] ?? [];
    }

    public function getParsedPageContent(): array
    {
        return $this->parsedData['content'] ?? [];
    }

    public function getPageForms(): array
    {
        return $this->parsedData['forms'] ?? [];
    }

    public function getPageMedia(): array
    {
        return $this->parsedData['media'] ?? [];
    }

    public function getPageLinks(): array
    {
        return $this->parsedData['links'] ?? [];
    }

    public function getPageScripts(): array
    {
        return $this->parsedData['scripts'] ?? [];
    }

    public function getPageStyles(): array
    {
        return $this->parsedData['styles'] ?? [];
    }

    public function getAccessibilityInfo(): array
    {
        return $this->parsedData['accessibility'] ?? [];
    }

    public function getSemanticElements(): array
    {
        return $this->parsedData['semantic'] ?? [];
    }

    public function getMicrodata(): array
    {
        return $this->parsedData['microdata'] ?? [];
    }

    public function getJsonLd(): array
    {
        return $this->parsedData['json_ld'] ?? [];
    }

    public function getOpenGraphData(): array
    {
        return $this->parsedData['metadata']['open_graph'] ?? [];
    }

    public function getTwitterCardData(): array
    {
        return $this->parsedData['metadata']['twitter_card'] ?? [];
    }

    public function getSchemaOrgData(): array
    {
        return $this->parsedData['metadata']['schema_org'] ?? [];
    }

    public function getImages(): array
    {
        return $this->parsedData['media']['images'] ?? [];
    }

    public function getVideos(): array
    {
        return $this->parsedData['media']['videos'] ?? [];
    }

    public function getAudio(): array
    {
        return $this->parsedData['media']['audio'] ?? [];
    }

    public function getIframes(): array
    {
        return $this->parsedData['media']['iframes'] ?? [];
    }

    public function getHeadings(): array
    {
        return $this->parsedData['structure']['headings'] ?? [];
    }

    public function getSections(): array
    {
        return $this->parsedData['structure']['sections'] ?? [];
    }

    public function getNavigation(): array
    {
        return $this->parsedData['structure']['navigation'] ?? [];
    }

    public function getMainContent(): array
    {
        return $this->parsedData['structure']['main_content'] ?? [];
    }

    public function getSidebar(): array
    {
        return $this->parsedData['structure']['sidebar'] ?? [];
    }

    public function getFooter(): array
    {
        return $this->parsedData['structure']['footer'] ?? [];
    }

    public function getHeader(): array
    {
        return $this->parsedData['structure']['header'] ?? [];
    }

    public function getParagraphs(): array
    {
        return $this->parsedData['content']['paragraphs'] ?? [];
    }

    public function getLists(): array
    {
        return $this->parsedData['content']['lists'] ?? [];
    }

    public function getTables(): array
    {
        return $this->parsedData['content']['tables'] ?? [];
    }

    public function getBlockquotes(): array
    {
        return $this->parsedData['content']['blockquotes'] ?? [];
    }

    public function getCodeBlocks(): array
    {
        return $this->parsedData['content']['code_blocks'] ?? [];
    }

    public function getTextContent(): string
    {
        return $this->parsedData['content']['text_content'] ?? '';
    }

    // DOM Query Methods

    public function querySelector(string $selector): ?\DOMElement
    {
        if (!$this->htmlParser) {
            return null;
        }

        try {
            $xpath = $this->htmlParser->getXPath();
            if (!$xpath) {
                return null;
            }

            // Convert CSS selector to XPath (basic implementation)
            $xpathQuery = $this->cssToXPath($selector);
            $nodes = $xpath->query($xpathQuery);
            
            return $nodes->length > 0 ? $nodes->item(0) : null;
        } catch (\Exception $e) {
            $this->logger->error("Query selector failed: " . $e->getMessage());
            return null;
        }
    }

    public function querySelectorAll(string $selector): array
    {
        if (!$this->htmlParser) {
            return [];
        }

        try {
            $xpath = $this->htmlParser->getXPath();
            if (!$xpath) {
                return [];
            }

            $xpathQuery = $this->cssToXPath($selector);
            $nodes = $xpath->query($xpathQuery);
            
            $elements = [];
            foreach ($nodes as $node) {
                $elements[] = $node;
            }
            
            return $elements;
        } catch (\Exception $e) {
            $this->logger->error("Query selector all failed: " . $e->getMessage());
            return [];
        }
    }

    public function getElementById(string $id): ?\DOMElement
    {
        if (!$this->htmlParser) {
            return null;
        }

        return $this->htmlParser->getElementById($id);
    }

    public function getElementsByTagName(string $tagName): array
    {
        if (!$this->htmlParser) {
            return [];
        }

        $nodes = $this->htmlParser->getElementsByTagName($tagName);
        $elements = [];
        
        foreach ($nodes as $node) {
            $elements[] = $node;
        }
        
        return $elements;
    }

    public function getElementsByClassName(string $className): array
    {
        if (!$this->htmlParser) {
            return [];
        }

        try {
            $nodes = $this->htmlParser->getElementsByClassName($className);
            $elements = [];
            
            foreach ($nodes as $node) {
                $elements[] = $node;
            }
            
            return $elements;
        } catch (\Exception $e) {
            $this->logger->error("Get elements by class name failed: " . $e->getMessage());
            return [];
        }
    }

    private function cssToXPath(string $selector): string
    {
        // Basic CSS to XPath conversion
        // This is a simplified implementation
        
        // Remove whitespace
        $selector = trim($selector);
        
        // Handle ID selector
        if (strpos($selector, '#') === 0) {
            $id = substr($selector, 1);
            return "//*[@id='$id']";
        }
        
        // Handle class selector
        if (strpos($selector, '.') === 0) {
            $class = substr($selector, 1);
            return "//*[contains(@class, '$class')]";
        }
        
        // Handle tag selector
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $selector)) {
            return "//$selector";
        }
        
        // Handle attribute selector
        if (preg_match('/^\[([^=]+)=([^\]]+)\]$/', $selector, $matches)) {
            $attr = $matches[1];
            $value = $matches[2];
            return "//*[@$attr='$value']";
        }
        
        // Default fallback
        return "//$selector";
    }

    public function getHtml(): string
    {
        if ($this->htmlParser) {
            return $this->htmlParser->getHtml();
        }
        
        return $this->pageContent;
    }

    public function getInnerHtml(\DOMElement $element): string
    {
        if (!$this->htmlParser) {
            return '';
        }

        return $this->htmlParser->getInnerHtml($element);
    }

    public function getElementTextContent(\DOMElement $element): string
    {
        if (!$this->htmlParser) {
            return '';
        }

        return $this->htmlParser->getTextContent($element);
    }

    public function getAttribute(\DOMElement $element, string $name): ?string
    {
        if (!$this->htmlParser) {
            return null;
        }

        return $this->htmlParser->getAttribute($element, $name);
    }

    public function hasAttribute(\DOMElement $element, string $name): bool
    {
        if (!$this->htmlParser) {
            return false;
        }

        return $this->htmlParser->hasAttribute($element, $name);
    }

    /**
     * Parse CSS from the current page
     */
    private function parseCss(): void
    {
        if (!$this->cssParser || !$this->htmlParser) {
            return;
        }

        try {
            // Extract CSS from <style> tags
            $styleElements = $this->htmlParser->querySelectorAll('style');
            $cssContent = '';
            
            foreach ($styleElements as $style) {
                $cssContent .= $this->htmlParser->getTextContent($style) . "\n";
            }
            
            // Extract CSS from <link> tags
            $linkElements = $this->htmlParser->querySelectorAll('link[rel="stylesheet"]');
            foreach ($linkElements as $link) {
                $href = $this->htmlParser->getAttribute($link, 'href');
                if ($href) {
                    // Resolve relative URLs
                    $cssUrl = $this->resolveUrl($href, $this->currentUrl);
                    
                    // Fetch external CSS
                    $response = $this->httpClient->get($cssUrl);
                    if ($response['success']) {
                        $cssContent .= $response['body'] . "\n";
                    }
                }
            }
            
            if (!empty($cssContent)) {
                $this->cssData = $this->cssParser->parseCss($cssContent, $this->currentUrl);
                $this->logger->debug("CSS parsed successfully", [
                    'rules_count' => count($this->cssData['rules'] ?? []),
                    'media_queries_count' => count($this->cssData['media_queries'] ?? []),
                    'keyframes_count' => count($this->cssData['keyframes'] ?? [])
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->error("CSS parsing failed: " . $e->getMessage());
        }
    }

    /**
     * Get parsed CSS data
     */
    public function getCssData(): array
    {
        return $this->cssData;
    }

    /**
     * Get CSS rules
     */
    public function getCssRules(): array
    {
        return $this->cssData['rules'] ?? [];
    }

    /**
     * Get media queries
     */
    public function getMediaQueries(): array
    {
        return $this->cssData['media_queries'] ?? [];
    }

    /**
     * Get CSS keyframes
     */
    public function getKeyframes(): array
    {
        return $this->cssData['keyframes'] ?? [];
    }

    /**
     * Get CSS variables
     */
    public function getCssVariables(): array
    {
        return $this->cssData['variables'] ?? [];
    }

    /**
     * Compute styles for an element
     */
    public function computeElementStyles(array $element): array
    {
        if (!$this->cssParser || !$this->cssRenderer) {
            return [];
        }

        try {
            $rules = $this->cssData['rules'] ?? [];
            $computedStyles = $this->cssParser->computeStyles($rules, $element);
            
            return $computedStyles;
        } catch (\Exception $e) {
            $this->logger->error("Style computation failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Render an element with computed styles
     */
    public function renderElement(array $element, array $parentStyles = []): array
    {
        if (!$this->cssRenderer) {
            return $element;
        }

        try {
            $computedStyles = $this->computeElementStyles($element);
            $rendered = $this->cssRenderer->renderElement($element, $computedStyles, $parentStyles);
            
            $this->renderedElements[] = $rendered;
            return $rendered;
        } catch (\Exception $e) {
            $this->logger->error("Element rendering failed: " . $e->getMessage());
            return $element;
        }
    }

    /**
     * Render the entire page
     */
    public function renderPage(): array
    {
        if (!$this->cssRenderer || !$this->htmlParser) {
            return [];
        }

        try {
            // Get all elements from HTML parser
            $elements = $this->htmlParser->getParsedData()['elements'] ?? [];
            
            // Render the page
            $rendered = $this->cssRenderer->renderPage($elements, $this->cssData);
            
            $this->logger->debug("Page rendered successfully", [
                'elements_count' => count($rendered['elements'] ?? []),
                'viewport' => $rendered['viewport'] ?? []
            ]);
            
            return $rendered;
        } catch (\Exception $e) {
            $this->logger->error("Page rendering failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get rendered elements
     */
    public function getRenderedElements(): array
    {
        return $this->renderedElements;
    }

    /**
     * Get CSS statistics
     */
    public function getCssStats(): array
    {
        return $this->cssData['stats'] ?? [];
    }

    /**
     * Resolve relative URL to absolute URL
     */
    private function resolveUrl(string $url, string $baseUrl): string
    {
        if (empty($baseUrl)) {
            return $url;
        }
        
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        }
        
        $base = parse_url($baseUrl);
        if (!$base) {
            return $url;
        }
        
        if (strpos($url, '/') === 0) {
            return $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '') . $url;
        } else {
            $basePath = isset($base['path']) ? dirname($base['path']) : '/';
            if ($basePath === '.') {
                $basePath = '/';
            }
            return $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '') . $basePath . '/' . $url;
        }
    }

    /**
     * Execute JavaScript from the current page
     */
    private function executeJavaScript(): void
    {
        if (!$this->jsEngine || !$this->htmlParser) {
            return;
        }

        try {
            // Extract JavaScript from <script> tags
            $scriptElements = $this->htmlParser->querySelectorAll('script');
            $jsContent = '';
            
            foreach ($scriptElements as $script) {
                $src = $this->htmlParser->getAttribute($script, 'src');
                if ($src) {
                    // External script
                    $jsUrl = $this->resolveUrl($src, $this->currentUrl);
                    $response = $this->httpClient->get($jsUrl);
                    if ($response['success']) {
                        $jsContent .= $response['body'] . "\n";
                        $this->executedScripts[] = [
                            'type' => 'external',
                            'src' => $jsUrl,
                            'content_length' => strlen($response['body'])
                        ];
                    }
                } else {
                    // Inline script
                    $content = $this->htmlParser->getTextContent($script);
                    if (!empty($content)) {
                        $jsContent .= $content . "\n";
                        $this->executedScripts[] = [
                            'type' => 'inline',
                            'content_length' => strlen($content)
                        ];
                    }
                }
            }
            
            if (!empty($jsContent)) {
                // Set up DOM context for JavaScript
                $domContext = $this->createDOMContext();
                
                // Execute JavaScript
                $result = $this->jsEngine->execute($jsContent, $domContext);
                
                $this->jsData = [
                    'executed' => true,
                    'result' => $result,
                    'scripts_count' => count($this->executedScripts),
                    'total_length' => strlen($jsContent)
                ];
                
                $this->logger->debug("JavaScript executed successfully", [
                    'scripts_count' => count($this->executedScripts),
                    'total_length' => strlen($jsContent)
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->error("JavaScript execution failed: " . $e->getMessage());
        }
    }

    /**
     * Create DOM context for JavaScript execution
     */
    private function createDOMContext(): array
    {
        if (!$this->htmlParser) {
            return [];
        }

        return [
            'document' => [
                'URL' => $this->currentUrl,
                'title' => $this->getPageTitle(),
                'body' => $this->htmlParser->querySelector('body'),
                'head' => $this->htmlParser->querySelector('head'),
                'getElementById' => function($id) {
                    return $this->htmlParser->getElementById($id);
                },
                'getElementsByClassName' => function($className) {
                    return $this->htmlParser->getElementsByClassName($className);
                },
                'getElementsByTagName' => function($tagName) {
                    return $this->htmlParser->getElementsByTagName($tagName);
                },
                'querySelector' => function($selector) {
                    return $this->htmlParser->querySelector($selector);
                },
                'querySelectorAll' => function($selector) {
                    return $this->htmlParser->querySelectorAll($selector);
                }
            ],
            'window' => [
                'location' => [
                    'href' => $this->currentUrl,
                    'protocol' => parse_url($this->currentUrl, PHP_URL_SCHEME) . ':',
                    'host' => parse_url($this->currentUrl, PHP_URL_HOST),
                    'hostname' => parse_url($this->currentUrl, PHP_URL_HOST),
                    'port' => parse_url($this->currentUrl, PHP_URL_PORT) ?: '',
                    'pathname' => parse_url($this->currentUrl, PHP_URL_PATH) ?: '/',
                    'search' => parse_url($this->currentUrl, PHP_URL_QUERY) ? '?' . parse_url($this->currentUrl, PHP_URL_QUERY) : '',
                    'hash' => parse_url($this->currentUrl, PHP_URL_FRAGMENT) ? '#' . parse_url($this->currentUrl, PHP_URL_FRAGMENT) : ''
                ],
                'navigator' => [
                    'userAgent' => $this->config['user_agent'] ?? 'Prism/1.0 (Custom Engine)'
                ]
            ]
        ];
    }

    /**
     * Execute JavaScript code directly
     */
    public function executeJavaScriptCode(string $code, array $variables = []): mixed
    {
        if (!$this->jsEngine) {
            throw new \RuntimeException('JavaScript engine not initialized');
        }

        $domContext = $this->createDOMContext();
        $context = array_merge($domContext, $variables);
        
        return $this->jsEngine->execute($code, $context);
    }

    /**
     * Execute JavaScript file
     */
    public function executeJavaScriptFile(string $filePath, array $variables = []): mixed
    {
        if (!$this->jsEngine) {
            throw new \RuntimeException('JavaScript engine not initialized');
        }

        $domContext = $this->createDOMContext();
        $context = array_merge($domContext, $variables);
        
        return $this->jsEngine->executeFile($filePath, $context);
    }

    /**
     * Create JavaScript context
     */
    public function createJavaScriptContext(string $name = null): string
    {
        if (!$this->jsEngine) {
            throw new \RuntimeException('JavaScript engine not initialized');
        }

        return $this->jsEngine->createContext($name);
    }

    /**
     * Set JavaScript context variable
     */
    public function setJavaScriptVariable(string $contextId, string $name, mixed $value): void
    {
        if (!$this->jsEngine) {
            throw new \RuntimeException('JavaScript engine not initialized');
        }

        $this->jsEngine->setContextVariable($contextId, $name, $value);
    }

    /**
     * Get JavaScript context variable
     */
    public function getJavaScriptVariable(string $contextId, string $name): mixed
    {
        if (!$this->jsEngine) {
            throw new \RuntimeException('JavaScript engine not initialized');
        }

        return $this->jsEngine->getContextVariable($contextId, $name);
    }

    /**
     * Execute JavaScript in context
     */
    public function executeJavaScriptInContext(string $contextId, string $code): mixed
    {
        if (!$this->jsEngine) {
            throw new \RuntimeException('JavaScript engine not initialized');
        }

        return $this->jsEngine->executeInContext($contextId, $code);
    }

    /**
     * Add JavaScript event listener
     */
    public function addJavaScriptEventListener(string $event, callable $listener, array $options = []): void
    {
        if (!$this->jsEngine) {
            throw new \RuntimeException('JavaScript engine not initialized');
        }

        $this->jsEngine->addEventListener($event, $listener, $options);
    }

    /**
     * Remove JavaScript event listener
     */
    public function removeJavaScriptEventListener(string $event, callable $listener): void
    {
        if (!$this->jsEngine) {
            throw new \RuntimeException('JavaScript engine not initialized');
        }

        $this->jsEngine->removeEventListener($event, $listener);
    }

    /**
     * Dispatch JavaScript event
     */
    public function dispatchJavaScriptEvent(string $event, array $data = []): void
    {
        if (!$this->jsEngine) {
            throw new \RuntimeException('JavaScript engine not initialized');
        }

        $this->jsEngine->dispatchEvent($event, $data);
    }

    /**
     * Get JavaScript data
     */
    public function getJavaScriptData(): array
    {
        return $this->jsData;
    }

    /**
     * Get executed scripts
     */
    public function getExecutedScripts(): array
    {
        return $this->executedScripts;
    }

    /**
     * Get JavaScript memory usage
     */
    public function getJavaScriptMemoryUsage(): array
    {
        if (!$this->jsEngine) {
            return [];
        }

        return $this->jsEngine->getMemoryUsage();
    }

    /**
     * Get JavaScript timers
     */
    public function getJavaScriptTimers(): array
    {
        if (!$this->jsEngine) {
            return [];
        }

        return $this->jsEngine->getTimers();
    }

    /**
     * Clear JavaScript timers
     */
    public function clearJavaScriptTimers(): void
    {
        if (!$this->jsEngine) {
            return;
        }

        $this->jsEngine->clearAllTimers();
    }

    /**
     * Get JavaScript event listeners
     */
    public function getJavaScriptEventListeners(): array
    {
        if (!$this->jsEngine) {
            return [];
        }

        return $this->jsEngine->getEventListeners();
    }

    /**
     * Get JavaScript contexts
     */
    public function getJavaScriptContexts(): array
    {
        if (!$this->jsEngine) {
            return [];
        }

        return $this->jsEngine->getContexts();
    }

    /**
     * Check if JavaScript engine is initialized
     */
    public function isJavaScriptInitialized(): bool
    {
        return $this->jsEngine && $this->jsEngine->isInitialized();
    }

    /**
     * Update close method to include JavaScript engine cleanup
     */
    public function close(): void
    {
        if ($this->httpClient) {
            $this->httpClient->close();
            $this->httpClient = null;
        }
        
        $this->htmlParser = null;
        $this->cssParser = null;
        $this->cssRenderer = null;
        
        if ($this->jsEngine) {
            $this->jsEngine->close();
            $this->jsEngine = null;
        }
        
        if ($this->cookieJar) {
            $this->cookieJar->close();
            $this->cookieJar = null;
        }

        if ($this->webSocketService) {
            $this->webSocketService->shutdown();
            $this->webSocketService = null;
        }

        if ($this->cacheService) {
            $this->cacheService->close();
            $this->cacheService = null;
        }
        
        $this->dom = null;
        
        $this->pageMetadata = [];
        $this->parsedData = [];
        $this->cssData = [];
        $this->renderedElements = [];
        $this->jsData = [];
        $this->executedScripts = [];
        
        // Save local storage before clearing
        if (!empty($this->localStorage)) {
            $this->saveLocalStorage();
        }
        $this->localStorage = [];
        $this->sessionStorage = [];
        
        $this->initialized = false;
        $this->currentUrl = '';
        $this->pageContent = '';
    }
}
