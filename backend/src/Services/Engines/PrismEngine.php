<?php

namespace Prism\Backend\Services\Engines;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use DOMDocument;
use DOMXPath;

class PrismEngine implements EngineInterface
{
    private array $config;
    private ?Client $httpClient = null;
    private ?DOMDocument $dom = null;
    private string $currentUrl = '';
    private string $pageContent = '';
    private bool $initialized = false;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function initialize(): bool
    {
        try {
            // Initialize HTTP client
            $this->httpClient = new Client([
                'timeout' => $this->config['timeout'] ?? 30,
                'connect_timeout' => 5,
                'headers' => [
                    'User-Agent' => $this->config['user_agent'] ?? 'Prism/1.0 (Custom Engine)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                ],
                'verify' => false, // For development only
                'allow_redirects' => [
                    'max' => 10,
                    'strict' => false,
                    'referer' => true,
                    'protocols' => ['http', 'https']
                ]
            ]);

            // Initialize DOM parser
            $this->dom = new DOMDocument();
            $this->dom->preserveWhiteSpace = false;
            $this->dom->formatOutput = true;

            $this->initialized = true;
            return true;
        } catch (\Exception $e) {
            error_log("Prism engine initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function navigate(string $url): void
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Engine not initialized');
        }

        try {
            $this->currentUrl = $url;
            
            // Fetch the page content
            $response = $this->httpClient->get($url);
            $this->pageContent = $response->getBody()->getContents();
            
            // Parse HTML if enabled
            if ($this->config['html_parsing'] ?? true) {
                $this->parseHtml();
            }
            
        } catch (RequestException $e) {
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
