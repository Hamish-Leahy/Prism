<?php

namespace Prism\Backend\Services\Plugins;

class AdBlockerPlugin extends BasePlugin
{
    private array $blockedDomains = [];
    private array $blockedSelectors = [];
    private bool $enabled = false;

    protected function getName(): string
    {
        return 'Ad Blocker';
    }

    protected function getVersion(): string
    {
        return '1.0.0';
    }

    protected function getDescription(): string
    {
        return 'Blocks advertisements and tracking scripts for improved privacy and performance';
    }

    protected function getAuthor(): string
    {
        return 'Prism Team';
    }

    protected function getLicense(): string
    {
        return 'MIT';
    }

    protected function getHomepage(): string
    {
        return 'https://github.com/prism-browser/plugins';
    }

    protected function onInitialize(): bool
    {
        // Load blocked domains from config
        $this->blockedDomains = $this->config['blocked_domains'] ?? [
            'googleadservices.com',
            'googlesyndication.com',
            'doubleclick.net',
            'facebook.com/tr',
            'analytics.google.com',
            'googletagmanager.com'
        ];

        // Load blocked CSS selectors
        $this->blockedSelectors = $this->config['blocked_selectors'] ?? [
            '[class*="ad-"]',
            '[id*="ad-"]',
            '[class*="advertisement"]',
            '[id*="advertisement"]',
            '.ads',
            '.advertisement',
            '.banner-ad',
            '.popup-ad'
        ];

        $this->logger->info("Ad Blocker plugin initialized", [
            'blocked_domains_count' => count($this->blockedDomains),
            'blocked_selectors_count' => count($this->blockedSelectors)
        ]);

        return true;
    }

    protected function onEnable(): bool
    {
        $this->enabled = true;
        $this->logger->info("Ad Blocker plugin enabled");
        return true;
    }

    protected function onDisable(): bool
    {
        $this->enabled = false;
        $this->logger->info("Ad Blocker plugin disabled");
        return true;
    }

    public function shouldBlockRequest(string $url): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';

        foreach ($this->blockedDomains as $domain) {
            if (strpos($host, $domain) !== false) {
                $this->logger->debug("Blocked request", ['url' => $url, 'domain' => $domain]);
                return true;
            }
        }

        return false;
    }

    public function shouldBlockElement(string $html): bool
    {
        if (!$this->enabled) {
            return false;
        }

        foreach ($this->blockedSelectors as $selector) {
            // Simple pattern matching - in a real implementation, you'd use a proper HTML parser
            if (preg_match('/' . preg_quote($selector, '/') . '/i', $html)) {
                $this->logger->debug("Blocked element", ['selector' => $selector]);
                return true;
            }
        }

        return false;
    }

    public function filterHtml(string $html): string
    {
        if (!$this->enabled) {
            return $html;
        }

        // Remove blocked elements
        foreach ($this->blockedSelectors as $selector) {
            // Simple regex-based removal - in a real implementation, you'd use a proper HTML parser
            $pattern = '/<[^>]*' . preg_quote($selector, '/') . '[^>]*>.*?<\/[^>]*>/is';
            $html = preg_replace($pattern, '', $html);
        }

        return $html;
    }

    public function addBlockedDomain(string $domain): bool
    {
        if (!in_array($domain, $this->blockedDomains)) {
            $this->blockedDomains[] = $domain;
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

    public function getBlockedDomains(): array
    {
        return $this->blockedDomains;
    }

    public function getBlockedSelectors(): array
    {
        return $this->blockedSelectors;
    }

    public function getStats(): array
    {
        return [
            'enabled' => $this->enabled,
            'blocked_domains_count' => count($this->blockedDomains),
            'blocked_selectors_count' => count($this->blockedSelectors),
            'blocked_domains' => $this->blockedDomains,
            'blocked_selectors' => $this->blockedSelectors
        ];
    }

    public function onEvent(string $eventName, array $data = []): mixed
    {
        switch ($eventName) {
            case 'before_request':
                $url = $data['url'] ?? '';
                if ($this->shouldBlockRequest($url)) {
                    return ['blocked' => true, 'reason' => 'ad_domain'];
                }
                break;
                
            case 'before_render':
                $html = $data['html'] ?? '';
                $filteredHtml = $this->filterHtml($html);
                return ['html' => $filteredHtml, 'modified' => $filteredHtml !== $html];
                
            default:
                return null;
        }
    }
}
