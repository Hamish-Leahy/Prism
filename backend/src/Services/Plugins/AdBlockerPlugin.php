<?php

namespace Prism\Backend\Services\Plugins;

use Monolog\Logger;

class AdBlockerPlugin extends BasePlugin
{
    private array $blockedDomains = [];
    private array $blockedSelectors = [];
    private array $blockedScripts = [];
    private bool $enabled = false;
    private array $stats = [
        'ads_blocked' => 0,
        'scripts_blocked' => 0,
        'domains_blocked' => 0,
        'requests_blocked' => 0
    ];

    public function __construct(array $config, Logger $logger)
    {
        parent::__construct($config, $logger);
        
        // Load ad blocking rules
        $this->loadAdBlockingRules();
    }

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing Ad Blocker Plugin");
            
            // Load configuration
            $this->blockedDomains = $this->config['blocked_domains'] ?? [];
            $this->blockedSelectors = $this->config['blocked_selectors'] ?? [];
            $this->blockedScripts = $this->config['blocked_scripts'] ?? [];
            
            // Load additional rules from files
            $this->loadFilterLists();
            
            $this->logger->info("Ad Blocker Plugin initialized", [
                'blocked_domains' => count($this->blockedDomains),
                'blocked_selectors' => count($this->blockedSelectors),
                'blocked_scripts' => count($this->blockedScripts)
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Ad Blocker Plugin initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function enable(): bool
    {
        $this->enabled = true;
        $this->logger->info("Ad Blocker Plugin enabled");
        return true;
    }

    public function disable(): bool
    {
        $this->enabled = false;
        $this->logger->info("Ad Blocker Plugin disabled");
        return true;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getInfo(): array
    {
        return [
            'name' => 'Ad Blocker Plugin',
            'version' => '1.0.0',
            'description' => 'Blocks advertisements and tracking scripts',
            'author' => 'Prism Team',
            'enabled' => $this->enabled,
            'stats' => $this->stats
        ];
    }

    public function onEvent(string $eventName, array $data = []): mixed
    {
        if (!$this->enabled) {
            return null;
        }

        switch ($eventName) {
            case 'before_request':
                return $this->blockRequest($data);
            case 'after_parse':
                return $this->blockElements($data);
            case 'before_execute_script':
                return $this->blockScript($data);
            default:
                return null;
        }
    }

    public function blockRequest(array $requestData): ?array
    {
        $url = $requestData['url'] ?? '';
        $domain = parse_url($url, PHP_URL_HOST);
        
        if ($this->isDomainBlocked($domain)) {
            $this->stats['requests_blocked']++;
            $this->logger->info("Blocked request", ['url' => $url, 'domain' => $domain]);
            
            return [
                'blocked' => true,
                'reason' => 'ad_domain',
                'url' => $url
            ];
        }
        
        return null;
    }

    public function blockElements(array $parseData): array
    {
        $blockedElements = [];
        $dom = $parseData['dom'] ?? null;
        
        if (!$dom) {
            return $blockedElements;
        }
        
        // Block elements by CSS selectors
        foreach ($this->blockedSelectors as $selector) {
            $elements = $dom->querySelectorAll($selector);
            foreach ($elements as $element) {
                $element->remove();
                $blockedElements[] = $selector;
                $this->stats['ads_blocked']++;
            }
        }
        
        // Block script tags with ad content
        $scripts = $dom->getElementsByTagName('script');
        foreach ($scripts as $script) {
            $src = $script->getAttribute('src');
            $content = $script->textContent;
            
            if ($this->isScriptBlocked($src, $content)) {
                $script->remove();
                $blockedElements[] = 'script';
                $this->stats['scripts_blocked']++;
            }
        }
        
        if (!empty($blockedElements)) {
            $this->logger->info("Blocked elements", [
                'count' => count($blockedElements),
                'types' => array_unique($blockedElements)
            ]);
        }
        
        return $blockedElements;
    }

    public function blockScript(array $scriptData): ?array
    {
        $script = $scriptData['script'] ?? '';
        $src = $scriptData['src'] ?? '';
        
        if ($this->isScriptBlocked($src, $script)) {
            $this->stats['scripts_blocked']++;
            $this->logger->info("Blocked script", ['src' => $src]);
            
            return [
                'blocked' => true,
                'reason' => 'ad_script',
                'src' => $src
            ];
        }
        
        return null;
    }

    public function addBlockedDomain(string $domain): bool
    {
        if (!in_array($domain, $this->blockedDomains)) {
            $this->blockedDomains[] = $domain;
            $this->stats['domains_blocked']++;
            $this->logger->info("Added blocked domain", ['domain' => $domain]);
            return true;
        }
        
        return false;
    }

    public function removeBlockedDomain(string $domain): bool
    {
        $key = array_search($domain, $this->blockedDomains);
        if ($key !== false) {
            unset($this->blockedDomains[$key]);
            $this->blockedDomains = array_values($this->blockedDomains);
            $this->logger->info("Removed blocked domain", ['domain' => $domain]);
            return true;
        }
        
        return false;
    }

    public function addBlockedSelector(string $selector): bool
    {
        if (!in_array($selector, $this->blockedSelectors)) {
            $this->blockedSelectors[] = $selector;
            $this->logger->info("Added blocked selector", ['selector' => $selector]);
            return true;
        }
        
        return false;
    }

    public function removeBlockedSelector(string $selector): bool
    {
        $key = array_search($selector, $this->blockedSelectors);
        if ($key !== false) {
            unset($this->blockedSelectors[$key]);
            $this->blockedSelectors = array_values($this->blockedSelectors);
            $this->logger->info("Removed blocked selector", ['selector' => $selector]);
            return true;
        }
        
        return false;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function resetStats(): void
    {
        $this->stats = [
            'ads_blocked' => 0,
            'scripts_blocked' => 0,
            'domains_blocked' => count($this->blockedDomains),
            'requests_blocked' => 0
        ];
    }

    private function isDomainBlocked(string $domain): bool
    {
        foreach ($this->blockedDomains as $blockedDomain) {
            if (strpos($domain, $blockedDomain) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function isScriptBlocked(string $src, string $content): bool
    {
        // Check if script source is blocked
        if ($src) {
            $domain = parse_url($src, PHP_URL_HOST);
            if ($this->isDomainBlocked($domain)) {
                return true;
            }
        }
        
        // Check if script content contains ad patterns
        $adPatterns = [
            'google-analytics',
            'googletagmanager',
            'facebook.com/tr',
            'doubleclick',
            'googlesyndication',
            'adsystem',
            'amazon-adsystem'
        ];
        
        foreach ($adPatterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function loadAdBlockingRules(): void
    {
        // Load default ad blocking rules
        $this->blockedDomains = [
            'google-analytics.com',
            'googletagmanager.com',
            'doubleclick.net',
            'googlesyndication.com',
            'amazon-adsystem.com',
            'facebook.com',
            'twitter.com',
            'linkedin.com',
            'pinterest.com',
            'adsystem.amazon.com'
        ];
        
        $this->blockedSelectors = [
            '[id*="ad"]',
            '[class*="ad"]',
            '[id*="banner"]',
            '[class*="banner"]',
            '[id*="popup"]',
            '[class*="popup"]',
            '[id*="overlay"]',
            '[class*="overlay"]',
            'iframe[src*="ads"]',
            'iframe[src*="doubleclick"]',
            'iframe[src*="googlesyndication"]'
        ];
        
        $this->blockedScripts = [
            'google-analytics',
            'googletagmanager',
            'facebook.com/tr',
            'doubleclick',
            'googlesyndication',
            'adsystem'
        ];
    }

    private function loadFilterLists(): void
    {
        // In a real implementation, this would load from external filter lists
        // like EasyList, EasyPrivacy, etc.
        
        $filterListPath = $this->config['filter_list_path'] ?? null;
        if ($filterListPath && file_exists($filterListPath)) {
            $this->loadFilterListFromFile($filterListPath);
        }
    }

    private function loadFilterListFromFile(string $filePath): void
    {
        try {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Skip comments and empty lines
                if (empty($line) || strpos($line, '!') === 0) {
                    continue;
                }
                
                // Parse filter list rules
                if (strpos($line, '||') === 0) {
                    // Domain filter
                    $domain = substr($line, 2);
                    $domain = str_replace('^', '', $domain);
                    $this->addBlockedDomain($domain);
                } elseif (strpos($line, '##') === 0) {
                    // Element filter
                    $selector = substr($line, 2);
                    $this->addBlockedSelector($selector);
                }
            }
            
            $this->logger->info("Loaded filter list", ['file' => $filePath]);
        } catch (\Exception $e) {
            $this->logger->error("Failed to load filter list", [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function cleanup(): void
    {
        $this->enabled = false;
        $this->blockedDomains = [];
        $this->blockedSelectors = [];
        $this->blockedScripts = [];
        $this->stats = [
            'ads_blocked' => 0,
            'scripts_blocked' => 0,
            'domains_blocked' => 0,
            'requests_blocked' => 0
        ];
        
        $this->logger->info("Ad Blocker Plugin cleaned up");
    }
}