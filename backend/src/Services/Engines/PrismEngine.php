<?php

namespace Prism\Backend\Services\Engines;

use Prism\Backend\Services\HttpClientService;
use Prism\Backend\Services\Html5ParserService;
use DOMDocument;
use DOMXPath;
use Monolog\Logger;

class PrismEngine implements EngineInterface
{
    private array $config;
    private ?HttpClientService $httpClient = null;
    private ?Html5ParserService $htmlParser = null;
    private ?DOMDocument $dom = null;
    private string $currentUrl = '';
    private string $pageContent = '';
    private bool $initialized = false;
    private Logger $logger;
    private array $pageMetadata = [];
    private array $cookies = [];
    private array $localStorage = [];
    private array $parsedData = [];

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
                $this->parseHtml5();
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

    public function close(): void
    {
        if ($this->httpClient) {
            $this->httpClient->close();
        }
        
        $this->httpClient = null;
        $this->htmlParser = null;
        $this->dom = null;
        $this->currentUrl = '';
        $this->pageContent = '';
        $this->pageMetadata = [];
        $this->parsedData = [];
        $this->cookies = [];
        $this->localStorage = [];
        $this->initialized = false;
        
        $this->logger->info("Prism engine closed");
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

    public function getCacheStats(): array
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
        return $this->cookies;
    }

    public function setCookie(string $name, string $value, array $options = []): void
    {
        $this->cookies[$name] = [
            'value' => $value,
            'options' => $options
        ];
    }

    public function getLocalStorage(): array
    {
        return $this->localStorage;
    }

    public function setLocalStorageItem(string $key, string $value): void
    {
        $this->localStorage[$key] = $value;
    }

    public function getLocalStorageItem(string $key): ?string
    {
        return $this->localStorage[$key] ?? null;
    }

    public function removeLocalStorageItem(string $key): void
    {
        unset($this->localStorage[$key]);
    }

    public function clearLocalStorage(): void
    {
        $this->localStorage = [];
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

    public function getPageContent(): array
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

    public function getTextContent(\DOMElement $element): string
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
}
