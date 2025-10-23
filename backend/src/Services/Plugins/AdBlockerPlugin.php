<?php

namespace Prism\Backend\Services\Plugins;

use Monolog\Logger;

class AdBlockerPlugin extends BasePlugin
{
    private array $blockedDomains = [];
    private array $blockedSelectors = [];
    private array $blockedPatterns = [];
    private bool $isEnabled = false;
    private int $blockedCount = 0;
    private array $whitelist = [];

    public function __construct(array $config = [], Logger $logger = null)
    {
        parent::__construct($config, $logger);
        $this->loadDefaultBlockLists();
    }

    public function initialize(): bool
    {
        try {
            $this->loadConfiguration();
            $this->logger->info('AdBlocker plugin initialized');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize AdBlocker plugin: ' . $e->getMessage());
            return false;
        }
    }

    public function enable(): bool
    {
        $this->isEnabled = true;
        $this->logger->info('AdBlocker plugin enabled');
        return true;
    }

    public function disable(): bool
    {
        $this->isEnabled = false;
        $this->logger->info('AdBlocker plugin disabled');
        return true;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function getInfo(): array
    {
        return [
            'name' => 'AdBlocker',
            'version' => '1.0.0',
            'description' => 'Blocks advertisements and tracking scripts',
            'author' => 'Prism Team',
            'enabled' => $this->isEnabled,
            'blocked_count' => $this->blockedCount
        ];
    }

    public function onEvent(string $eventName, array $data = []): mixed
    {
        switch ($eventName) {
            case 'page_load':
                return $this->handlePageLoad($data);
            case 'request_made':
                return $this->handleRequest($data);
            case 'dom_ready':
                return $this->handleDOMReady($data);
            default:
                return null;
        }
    }

    public function blockAd(string $url, string $reason = 'ad_domain'): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        $this->blockedCount++;
        $this->logger->debug('Ad blocked', [
            'url' => $url,
            'reason' => $reason,
            'total_blocked' => $this->blockedCount
        ]);

        return true;
    }

    public function isAdDomain(string $domain): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        // Check whitelist first
        if (in_array($domain, $this->whitelist)) {
            return false;
        }

        // Check blocked domains
        foreach ($this->blockedDomains as $blockedDomain) {
            if (strpos($domain, $blockedDomain) !== false) {
                return true;
            }
        }

        return false;
    }

    public function isAdUrl(string $url): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            return false;
        }

        // Check if domain is blocked
        if ($this->isAdDomain($parsedUrl['host'])) {
            return true;
        }

        // Check URL patterns
        foreach ($this->blockedPatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    public function getBlockedCount(): int
    {
        return $this->blockedCount;
    }

    public function addBlockedDomain(string $domain): void
    {
        if (!in_array($domain, $this->blockedDomains)) {
            $this->blockedDomains[] = $domain;
            $this->logger->info('Domain added to block list', ['domain' => $domain]);
        }
    }

    public function removeBlockedDomain(string $domain): void
    {
        $key = array_search($domain, $this->blockedDomains);
        if ($key !== false) {
            unset($this->blockedDomains[$key]);
            $this->blockedDomains = array_values($this->blockedDomains);
            $this->logger->info('Domain removed from block list', ['domain' => $domain]);
        }
    }

    public function addWhitelistDomain(string $domain): void
    {
        if (!in_array($domain, $this->whitelist)) {
            $this->whitelist[] = $domain;
            $this->logger->info('Domain added to whitelist', ['domain' => $domain]);
        }
    }

    public function getBlockedDomains(): array
    {
        return $this->blockedDomains;
    }

    public function getWhitelist(): array
    {
        return $this->whitelist;
    }

    public function resetBlockedCount(): void
    {
        $this->blockedCount = 0;
        $this->logger->info('Blocked count reset');
    }

    private function handlePageLoad(array $data): array
    {
        $url = $data['url'] ?? '';
        
        if ($this->isAdUrl($url)) {
            $this->blockAd($url, 'ad_url');
            return ['blocked' => true, 'reason' => 'ad_url'];
        }

        return ['blocked' => false];
    }

    private function handleRequest(array $data): array
    {
        $url = $data['url'] ?? '';
        $method = $data['method'] ?? 'GET';
        
        if ($this->isAdUrl($url)) {
            $this->blockAd($url, 'ad_request');
            return [
                'blocked' => true,
                'reason' => 'ad_request',
                'response' => [
                    'status' => 204,
                    'headers' => ['X-AdBlocked' => 'true'],
                    'body' => ''
                ]
            ];
        }

        return ['blocked' => false];
    }

    private function handleDOMReady(array $data): array
    {
        $html = $data['html'] ?? '';
        
        if (empty($html)) {
            return ['processed' => false];
        }

        // Remove ad elements
        $processedHtml = $this->removeAdElements($html);
        
        return [
            'processed' => true,
            'original_length' => strlen($html),
            'processed_length' => strlen($processedHtml),
            'elements_removed' => $this->countRemovedElements($html, $processedHtml)
        ];
    }

    private function removeAdElements(string $html): string
    {
        // Remove elements with ad-related classes
        $adSelectors = [
            'div[class*="ad-"]',
            'div[class*="advertisement"]',
            'div[class*="banner"]',
            'div[id*="ad-"]',
            'div[id*="advertisement"]',
            'iframe[src*="ads"]',
            'script[src*="ads"]'
        ];

        foreach ($adSelectors as $selector) {
            $html = preg_replace('/<' . preg_quote($selector, '/') . '[^>]*>.*?<\/' . preg_quote($selector, '/') . '>/is', '', $html);
        }

        // Remove script tags with ad content
        $html = preg_replace('/<script[^>]*>.*?ad.*?<\/script>/is', '', $html);

        return $html;
    }

    private function countRemovedElements(string $original, string $processed): int
    {
        // Simple heuristic to count removed elements
        $originalDivs = substr_count($original, '<div');
        $processedDivs = substr_count($processed, '<div');
        
        return max(0, $originalDivs - $processedDivs);
    }

    private function loadDefaultBlockLists(): void
    {
        // Common ad domains
        $this->blockedDomains = [
            'googleadservices.com',
            'googlesyndication.com',
            'doubleclick.net',
            'googletagmanager.com',
            'facebook.com/tr',
            'amazon-adsystem.com',
            'adsystem.amazon.com',
            'outbrain.com',
            'taboola.com',
            'ads.yahoo.com',
            'adsystem.yahoo.com',
            'adsystem.amazon.com',
            'amazon-adsystem.com',
            'adsystem.amazon.com',
            'amazon-adsystem.com'
        ];

        // Common ad URL patterns
        $this->blockedPatterns = [
            '/\/ads?\//',
            '/\/advertisement/',
            '/\/banner/',
            '/\/popup/',
            '/\/popunder/',
            '/googleads/',
            '/doubleclick/',
            '/googlesyndication/',
            '/amazon-adsystem/',
            '/outbrain/',
            '/taboola/'
        ];

        // Common ad CSS selectors
        $this->blockedSelectors = [
            '.ad',
            '.advertisement',
            '.banner',
            '.popup',
            '.popunder',
            '[id*="ad-"]',
            '[class*="ad-"]',
            '[id*="advertisement"]',
            '[class*="advertisement"]'
        ];
    }

    private function loadConfiguration(): void
    {
        $config = $this->getConfig();
        
        if (isset($config['blocked_domains'])) {
            $this->blockedDomains = array_merge($this->blockedDomains, $config['blocked_domains']);
        }

        if (isset($config['whitelist'])) {
            $this->whitelist = $config['whitelist'];
        }

        if (isset($config['blocked_patterns'])) {
            $this->blockedPatterns = array_merge($this->blockedPatterns, $config['blocked_patterns']);
        }

        if (isset($config['blocked_selectors'])) {
            $this->blockedSelectors = array_merge($this->blockedSelectors, $config['blocked_selectors']);
        }
    }

    public function getStatistics(): array
    {
        return [
            'enabled' => $this->isEnabled,
            'blocked_count' => $this->blockedCount,
            'blocked_domains_count' => count($this->blockedDomains),
            'whitelist_count' => count($this->whitelist),
            'blocked_patterns_count' => count($this->blockedPatterns),
            'blocked_selectors_count' => count($this->blockedSelectors)
        ];
    }
}