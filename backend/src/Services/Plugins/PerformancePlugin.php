<?php

namespace Prism\Backend\Services\Plugins;

use Monolog\Logger;

class PerformancePlugin extends BasePlugin
{
    private array $optimizations = [];
    private bool $enabled = false;
    private array $stats = [
        'images_optimized' => 0,
        'scripts_deferred' => 0,
        'css_optimized' => 0,
        'resources_compressed' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0
    ];

    public function initialize(): bool
    {
        try {
            $this->logger->info("Initializing Performance Plugin");
            
            $this->optimizations = $this->config['optimizations'] ?? $this->getDefaultOptimizations();
            
            $this->logger->info("Performance Plugin initialized");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Performance Plugin initialization failed: " . $e->getMessage());
            return false;
        }
    }

    public function enable(): bool
    {
        $this->enabled = true;
        $this->logger->info("Performance Plugin enabled");
        return true;
    }

    public function disable(): bool
    {
        $this->enabled = false;
        $this->logger->info("Performance Plugin disabled");
        return true;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getInfo(): array
    {
        return [
            'name' => 'Performance Plugin',
            'version' => '1.0.0',
            'description' => 'Performance optimization and resource management',
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
            case 'after_parse':
                return $this->optimizePage($data);
            case 'before_request':
                return $this->optimizeRequest($data);
            case 'after_response':
                return $this->optimizeResponse($data);
            default:
                return null;
        }
    }

    private function optimizePage(array $parseData): array
    {
        $optimizations = [];
        $dom = $parseData['dom'] ?? null;
        
        if (!$dom) {
            return $optimizations;
        }
        
        // Optimize images
        if ($this->optimizations['optimize_images'] ?? true) {
            $optimizations = array_merge($optimizations, $this->optimizeImages($dom));
        }
        
        // Defer scripts
        if ($this->optimizations['defer_scripts'] ?? true) {
            $optimizations = array_merge($optimizations, $this->deferScripts($dom));
        }
        
        // Optimize CSS
        if ($this->optimizations['optimize_css'] ?? true) {
            $optimizations = array_merge($optimizations, $this->optimizeCSS($dom));
        }
        
        // Add resource hints
        if ($this->optimizations['add_resource_hints'] ?? true) {
            $optimizations = array_merge($optimizations, $this->addResourceHints($dom));
        }
        
        return $optimizations;
    }

    private function optimizeImages($dom): array
    {
        $optimizations = [];
        $images = $dom->getElementsByTagName('img');
        
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            if ($src) {
                // Add loading="lazy" for images below the fold
                if (!$img->hasAttribute('loading')) {
                    $img->setAttribute('loading', 'lazy');
                    $optimizations[] = 'lazy_loading';
                    $this->stats['images_optimized']++;
                }
                
                // Add width and height attributes for layout stability
                if (!$img->hasAttribute('width') && !$img->hasAttribute('height')) {
                    $img->setAttribute('width', 'auto');
                    $img->setAttribute('height', 'auto');
                    $optimizations[] = 'layout_stability';
                }
                
                // Convert to WebP if supported
                if ($this->optimizations['convert_to_webp'] ?? false) {
                    $webpSrc = $this->convertToWebP($src);
                    if ($webpSrc) {
                        $img->setAttribute('src', $webpSrc);
                        $optimizations[] = 'webp_conversion';
                    }
                }
            }
        }
        
        return $optimizations;
    }

    private function deferScripts($dom): array
    {
        $optimizations = [];
        $scripts = $dom->getElementsByTagName('script');
        
        foreach ($scripts as $script) {
            $src = $script->getAttribute('src');
            $type = $script->getAttribute('type');
            
            // Skip inline scripts and modules
            if (!$src || $type === 'module') {
                continue;
            }
            
            // Defer non-critical scripts
            if (!$script->hasAttribute('defer') && !$script->hasAttribute('async')) {
                $script->setAttribute('defer', 'defer');
                $optimizations[] = 'script_deferred';
                $this->stats['scripts_deferred']++;
            }
        }
        
        return $optimizations;
    }

    private function optimizeCSS($dom): array
    {
        $optimizations = [];
        $styles = $dom->getElementsByTagName('style');
        
        foreach ($styles as $style) {
            $content = $style->textContent;
            
            // Minify CSS
            $minified = $this->minifyCSS($content);
            if ($minified !== $content) {
                $style->textContent = $minified;
                $optimizations[] = 'css_minified';
                $this->stats['css_optimized']++;
            }
        }
        
        return $optimizations;
    }

    private function addResourceHints($dom): array
    {
        $optimizations = [];
        $head = $dom->getElementsByTagName('head')->item(0);
        
        if (!$head) {
            return $optimizations;
        }
        
        // Add DNS prefetch for external domains
        $links = $dom->getElementsByTagName('a');
        $domains = [];
        
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if ($href && strpos($href, 'http') === 0) {
                $domain = parse_url($href, PHP_URL_HOST);
                if ($domain && !in_array($domain, $domains)) {
                    $domains[] = $domain;
                }
            }
        }
        
        foreach ($domains as $domain) {
            $dnsPrefetch = $dom->createElement('link');
            $dnsPrefetch->setAttribute('rel', 'dns-prefetch');
            $dnsPrefetch->setAttribute('href', '//' . $domain);
            $head->appendChild($dnsPrefetch);
            $optimizations[] = 'dns_prefetch';
        }
        
        return $optimizations;
    }

    private function optimizeRequest(array $requestData): ?array
    {
        $url = $requestData['url'] ?? '';
        $headers = $requestData['headers'] ?? [];
        
        // Add compression headers
        if ($this->optimizations['enable_compression'] ?? true) {
            $headers['Accept-Encoding'] = 'gzip, deflate, br';
        }
        
        // Add cache headers
        if ($this->optimizations['enable_caching'] ?? true) {
            $headers['Cache-Control'] = 'max-age=3600';
        }
        
        return [
            'headers' => $headers
        ];
    }

    private function optimizeResponse(array $responseData): ?array
    {
        $content = $responseData['body'] ?? '';
        $contentType = $responseData['headers']['content-type'][0] ?? '';
        
        // Compress response if not already compressed
        if (strpos($contentType, 'text/') === 0 && $this->optimizations['compress_responses'] ?? true) {
            $compressed = gzencode($content, 6);
            if ($compressed !== false) {
                $this->stats['resources_compressed']++;
                return [
                    'body' => $compressed,
                    'headers' => array_merge($responseData['headers'], [
                        'Content-Encoding' => 'gzip',
                        'Content-Length' => strlen($compressed)
                    ])
                ];
            }
        }
        
        return null;
    }

    private function convertToWebP(string $src): ?string
    {
        // Mock WebP conversion - in a real implementation, this would
        // actually convert the image to WebP format
        if (strpos($src, '.jpg') !== false || strpos($src, '.jpeg') !== false || strpos($src, '.png') !== false) {
            return str_replace(['.jpg', '.jpeg', '.png'], '.webp', $src);
        }
        
        return null;
    }

    private function minifyCSS(string $css): string
    {
        // Basic CSS minification
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/;\s*}/', '}', $css);
        $css = preg_replace('/{\s*/', '{', $css);
        $css = preg_replace('/;\s*/', ';', $css);
        $css = trim($css);
        
        return $css;
    }

    private function getDefaultOptimizations(): array
    {
        return [
            'optimize_images' => true,
            'defer_scripts' => true,
            'optimize_css' => true,
            'add_resource_hints' => true,
            'enable_compression' => true,
            'enable_caching' => true,
            'compress_responses' => true,
            'convert_to_webp' => false
        ];
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function cleanup(): void
    {
        $this->enabled = false;
        $this->optimizations = [];
        $this->stats = [
            'images_optimized' => 0,
            'scripts_deferred' => 0,
            'css_optimized' => 0,
            'resources_compressed' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0
        ];
        
        $this->logger->info("Performance Plugin cleaned up");
    }
}
