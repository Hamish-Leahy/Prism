<?php

namespace Prism\Backend\Services\Engines;

use Prism\Backend\Services\HttpClientService;
use DOMDocument;
use DOMXPath;
use Monolog\Logger;

class PrismEngine implements EngineInterface
{
    private array $config;
    private ?HttpClientService $httpClient = null;
    private ?DOMDocument $dom = null;
    private string $currentUrl = '';
    private string $pageContent = '';
    private bool $initialized = false;
    private Logger $logger;
    private array $pageMetadata = [];
    private array $cookies = [];
    private array $localStorage = [];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->logger = new Logger('prism-engine');
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

            // Initialize DOM parser
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
            
            // Fetch the page content using advanced HTTP client
            $response = $this->httpClient->get($url);
            
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
                $this->extractMetadata();
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

        if ($this->dom) {
            $titleElements = $this->dom->getElementsByTagName('title');
            if ($titleElements->length > 0) {
                return $titleElements->item(0)->textContent;
            }
        }

        // Fallback: extract title from HTML
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $this->pageContent, $matches)) {
            return trim($matches[1]);
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

    public function close(): void
    {
        $this->httpClient = null;
        $this->dom = null;
        $this->currentUrl = '';
        $this->pageContent = '';
        $this->initialized = false;
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
            error_log("HTML parsing failed: " . $e->getMessage());
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
}
