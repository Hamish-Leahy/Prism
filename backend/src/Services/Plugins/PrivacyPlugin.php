<?php

namespace Prism\Backend\Services\Plugins;

use Monolog\Logger;

class PrivacyPlugin extends BasePlugin
{
    private array $trackingDomains = [];
    private array $fingerprintingProtection = [];
    private bool $enabled = false;
    private array $stats = [
        'trackers_blocked' => 0,
        'fingerprints_blocked' => 0,
        'cookies_blocked' => 0,
        'requests_blocked' => 0
    ];

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing Privacy Plugin");
            
            $this->trackingDomains = $this->config['tracking_domains'] ?? $this->getDefaultTrackingDomains();
            $this->fingerprintingProtection = $this->config['fingerprinting_protection'] ?? $this->getDefaultFingerprintingProtection();
            
            $this->logger->info("Privacy Plugin initialized");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Privacy Plugin initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function enable(): bool
    {
        $this->enabled = true;
        $this->logger->info("Privacy Plugin enabled");
        return true;
    }

    public function disable(): bool
    {
        $this->enabled = false;
        $this->logger->info("Privacy Plugin disabled");
        return true;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getInfo(): array
    {
        return [
            'name' => 'Privacy Plugin',
            'version' => '1.0.0',
            'description' => 'Enhanced privacy protection and tracking prevention',
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
                return $this->blockTrackingRequest($data);
            case 'after_parse':
                return $this->blockFingerprinting($data);
            case 'cookie_set':
                return $this->blockTrackingCookie($data);
            default:
                return null;
        }
    }

    private function blockTrackingRequest(array $requestData): ?array
    {
        $url = $requestData['url'] ?? '';
        $domain = parse_url($url, PHP_URL_HOST);
        
        if ($this->isTrackingDomain($domain)) {
            $this->stats['requests_blocked']++;
            $this->logger->info("Blocked tracking request", ['url' => $url]);
            
            return [
                'blocked' => true,
                'reason' => 'tracking_domain',
                'url' => $url
            ];
        }
        
        return null;
    }

    private function blockFingerprinting(array $parseData): array
    {
        $blockedElements = [];
        $dom = $parseData['dom'] ?? null;
        
        if (!$dom) {
            return $blockedElements;
        }
        
        // Block canvas fingerprinting
        $canvasElements = $dom->querySelectorAll('canvas');
        foreach ($canvasElements as $canvas) {
            if ($this->isFingerprintingCanvas($canvas)) {
                $canvas->remove();
                $blockedElements[] = 'canvas';
                $this->stats['fingerprints_blocked']++;
            }
        }
        
        // Block WebGL fingerprinting
        $scripts = $dom->getElementsByTagName('script');
        foreach ($scripts as $script) {
            $content = $script->textContent;
            if ($this->containsFingerprintingCode($content)) {
                $script->remove();
                $blockedElements[] = 'script';
                $this->stats['fingerprints_blocked']++;
            }
        }
        
        return $blockedElements;
    }

    private function blockTrackingCookie(array $cookieData): ?array
    {
        $name = $cookieData['name'] ?? '';
        $domain = $cookieData['domain'] ?? '';
        
        if ($this->isTrackingCookie($name, $domain)) {
            $this->stats['cookies_blocked']++;
            $this->logger->info("Blocked tracking cookie", ['name' => $name, 'domain' => $domain]);
            
            return [
                'blocked' => true,
                'reason' => 'tracking_cookie',
                'name' => $name
            ];
        }
        
        return null;
    }

    private function isTrackingDomain(string $domain): bool
    {
        foreach ($this->trackingDomains as $trackingDomain) {
            if (strpos($domain, $trackingDomain) !== false) {
                return true;
            }
        }
        return false;
    }

    private function isTrackingCookie(string $name, string $domain): bool
    {
        $trackingPatterns = [
            '_ga',
            '_gid',
            '_fbp',
            '_fbc',
            'utm_',
            'fbclid',
            'gclid',
            '_gat',
            '_gcl_'
        ];
        
        foreach ($trackingPatterns as $pattern) {
            if (strpos($name, $pattern) !== false) {
                return true;
            }
        }
        
        return $this->isTrackingDomain($domain);
    }

    private function isFingerprintingCanvas($canvas): bool
    {
        // Check for canvas fingerprinting patterns
        $parent = $canvas->parentNode;
        if ($parent) {
            $parentText = $parent->textContent;
            $fingerprintingKeywords = [
                'fingerprint',
                'canvas',
                'webgl',
                'audio',
                'font',
                'screen'
            ];
            
            foreach ($fingerprintingKeywords as $keyword) {
                if (stripos($parentText, $keyword) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }

    private function containsFingerprintingCode(string $content): bool
    {
        $fingerprintingPatterns = [
            'getContext("2d")',
            'getContext("webgl")',
            'getContext("experimental-webgl")',
            'toDataURL()',
            'getImageData()',
            'getComputedStyle',
            'screen.width',
            'screen.height',
            'navigator.userAgent',
            'navigator.platform',
            'navigator.language',
            'Date.getTime()',
            'Math.random()'
        ];
        
        $patternCount = 0;
        foreach ($fingerprintingPatterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                $patternCount++;
            }
        }
        
        // If multiple fingerprinting patterns are found, likely fingerprinting
        return $patternCount >= 3;
    }

    private function getDefaultTrackingDomains(): array
    {
        return [
            'google-analytics.com',
            'googletagmanager.com',
            'doubleclick.net',
            'facebook.com',
            'twitter.com',
            'linkedin.com',
            'pinterest.com',
            'amazon-adsystem.com',
            'scorecardresearch.com',
            'quantserve.com',
            'outbrain.com',
            'taboola.com'
        ];
    }

    private function getDefaultFingerprintingProtection(): array
    {
        return [
            'block_canvas_fingerprinting' => true,
            'block_webgl_fingerprinting' => true,
            'block_audio_fingerprinting' => true,
            'block_font_fingerprinting' => true,
            'block_screen_fingerprinting' => true,
            'block_webgl_parameters' => true,
            'block_webgl_extensions' => true
        ];
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function cleanup(): void
    {
        $this->enabled = false;
        $this->trackingDomains = [];
        $this->fingerprintingProtection = [];
        $this->stats = [
            'trackers_blocked' => 0,
            'fingerprints_blocked' => 0,
            'cookies_blocked' => 0,
            'requests_blocked' => 0
        ];
        
        $this->logger->info("Privacy Plugin cleaned up");
    }
}
