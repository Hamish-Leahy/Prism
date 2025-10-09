<?php

namespace Prism\Backend\Services;

use Monolog\Logger;

class CookieJarService
{
    private Logger $logger;
    private array $cookies = [];
    private array $config;
    private string $storagePath;
    private bool $persistent = true;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->storagePath = $config['storage_path'] ?? sys_get_temp_dir() . '/prism_cookies.json';
        $this->persistent = $config['persistent'] ?? true;
        
        if ($this->persistent) {
            $this->loadCookies();
        }
    }

    /**
     * Set a cookie
     */
    public function setCookie(string $name, string $value, array $options = []): bool
    {
        try {
            $cookie = [
                'name' => $name,
                'value' => $value,
                'domain' => $options['domain'] ?? '',
                'path' => $options['path'] ?? '/',
                'expires' => $options['expires'] ?? null,
                'max_age' => $options['max_age'] ?? null,
                'secure' => $options['secure'] ?? false,
                'http_only' => $options['http_only'] ?? false,
                'same_site' => $options['same_site'] ?? 'Lax',
                'created' => time(),
                'last_accessed' => time()
            ];

            // Validate cookie
            if (!$this->validateCookie($cookie)) {
                return false;
            }

            // Check if cookie should be expired
            if ($this->isCookieExpired($cookie)) {
                $this->removeCookie($name, $options['domain'] ?? '', $options['path'] ?? '/');
                return false;
            }

            // Store cookie
            $key = $this->getCookieKey($name, $cookie['domain'], $cookie['path']);
            $this->cookies[$key] = $cookie;

            $this->logger->debug("Cookie set", [
                'name' => $name,
                'domain' => $cookie['domain'],
                'path' => $cookie['path'],
                'expires' => $cookie['expires']
            ]);

            if ($this->persistent) {
                $this->saveCookies();
            }

            return true;

        } catch (\Exception $e) {
            $this->logger->error("Failed to set cookie: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a cookie value
     */
    public function getCookie(string $name, string $domain = '', string $path = '/'): ?string
    {
        try {
            $key = $this->getCookieKey($name, $domain, $path);
            
            if (!isset($this->cookies[$key])) {
                return null;
            }

            $cookie = $this->cookies[$key];

            // Check if cookie is expired
            if ($this->isCookieExpired($cookie)) {
                unset($this->cookies[$key]);
                if ($this->persistent) {
                    $this->saveCookies();
                }
                return null;
            }

            // Update last accessed time
            $this->cookies[$key]['last_accessed'] = time();

            return $cookie['value'];

        } catch (\Exception $e) {
            $this->logger->error("Failed to get cookie: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all cookies for a domain
     */
    public function getCookiesForDomain(string $domain): array
    {
        $cookies = [];
        
        foreach ($this->cookies as $key => $cookie) {
            if ($this->isDomainMatch($domain, $cookie['domain'])) {
                if (!$this->isCookieExpired($cookie)) {
                    $cookies[] = $cookie;
                } else {
                    unset($this->cookies[$key]);
                }
            }
        }

        if ($this->persistent && !empty($cookies)) {
            $this->saveCookies();
        }

        return $cookies;
    }

    /**
     * Get all cookies
     */
    public function getAllCookies(): array
    {
        $cookies = [];
        
        foreach ($this->cookies as $key => $cookie) {
            if (!$this->isCookieExpired($cookie)) {
                $cookies[] = $cookie;
            } else {
                unset($this->cookies[$key]);
            }
        }

        if ($this->persistent) {
            $this->saveCookies();
        }

        return $cookies;
    }

    /**
     * Remove a cookie
     */
    public function removeCookie(string $name, string $domain = '', string $path = '/'): bool
    {
        try {
            $key = $this->getCookieKey($name, $domain, $path);
            
            if (isset($this->cookies[$key])) {
                unset($this->cookies[$key]);
                
                $this->logger->debug("Cookie removed", [
                    'name' => $name,
                    'domain' => $domain,
                    'path' => $path
                ]);

                if ($this->persistent) {
                    $this->saveCookies();
                }

                return true;
            }

            return false;

        } catch (\Exception $e) {
            $this->logger->error("Failed to remove cookie: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all cookies
     */
    public function clearAllCookies(): bool
    {
        try {
            $this->cookies = [];
            
            $this->logger->info("All cookies cleared");

            if ($this->persistent) {
                $this->saveCookies();
            }

            return true;

        } catch (\Exception $e) {
            $this->logger->error("Failed to clear cookies: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear cookies for a specific domain
     */
    public function clearCookiesForDomain(string $domain): bool
    {
        try {
            $removed = 0;
            
            foreach ($this->cookies as $key => $cookie) {
                if ($this->isDomainMatch($domain, $cookie['domain'])) {
                    unset($this->cookies[$key]);
                    $removed++;
                }
            }

            $this->logger->info("Cookies cleared for domain", [
                'domain' => $domain,
                'removed_count' => $removed
            ]);

            if ($this->persistent) {
                $this->saveCookies();
            }

            return true;

        } catch (\Exception $e) {
            $this->logger->error("Failed to clear cookies for domain: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Parse cookies from HTTP response headers
     */
    public function parseCookiesFromHeaders(array $headers, string $domain, string $path = '/'): array
    {
        $parsed = [];
        
        if (!isset($headers['set-cookie'])) {
            return $parsed;
        }

        $setCookieHeaders = is_array($headers['set-cookie']) ? $headers['set-cookie'] : [$headers['set-cookie']];

        foreach ($setCookieHeaders as $cookieHeader) {
            $cookie = $this->parseCookieHeader($cookieHeader, $domain, $path);
            if ($cookie) {
                $parsed[] = $cookie;
            }
        }

        return $parsed;
    }

    /**
     * Generate cookie header for HTTP requests
     */
    public function generateCookieHeader(string $domain, string $path = '/'): string
    {
        $cookies = $this->getCookiesForDomain($domain);
        $cookieStrings = [];

        foreach ($cookies as $cookie) {
            if ($this->isPathMatch($path, $cookie['path'])) {
                $cookieStrings[] = $cookie['name'] . '=' . $cookie['value'];
            }
        }

        return implode('; ', $cookieStrings);
    }

    /**
     * Get cookie statistics
     */
    public function getStats(): array
    {
        $total = count($this->cookies);
        $expired = 0;
        $domains = [];
        $secure = 0;
        $httpOnly = 0;

        foreach ($this->cookies as $cookie) {
            if ($this->isCookieExpired($cookie)) {
                $expired++;
            } else {
                $domains[$cookie['domain']] = ($domains[$cookie['domain']] ?? 0) + 1;
                
                if ($cookie['secure']) {
                    $secure++;
                }
                
                if ($cookie['http_only']) {
                    $httpOnly++;
                }
            }
        }

        return [
            'total' => $total,
            'active' => $total - $expired,
            'expired' => $expired,
            'domains' => count($domains),
            'domain_breakdown' => $domains,
            'secure_count' => $secure,
            'http_only_count' => $httpOnly,
            'persistent' => $this->persistent,
            'storage_path' => $this->storagePath
        ];
    }

    /**
     * Clean up expired cookies
     */
    public function cleanupExpiredCookies(): int
    {
        $removed = 0;
        
        foreach ($this->cookies as $key => $cookie) {
            if ($this->isCookieExpired($cookie)) {
                unset($this->cookies[$key]);
                $removed++;
            }
        }

        if ($removed > 0 && $this->persistent) {
            $this->saveCookies();
        }

        $this->logger->debug("Expired cookies cleaned up", ['removed_count' => $removed]);
        
        return $removed;
    }

    /**
     * Export cookies to array
     */
    public function exportCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Import cookies from array
     */
    public function importCookies(array $cookies): bool
    {
        try {
            $this->cookies = $cookies;
            
            if ($this->persistent) {
                $this->saveCookies();
            }

            $this->logger->info("Cookies imported", ['count' => count($cookies)]);
            return true;

        } catch (\Exception $e) {
            $this->logger->error("Failed to import cookies: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate cookie data
     */
    private function validateCookie(array $cookie): bool
    {
        // Check required fields
        if (empty($cookie['name']) || empty($cookie['value'])) {
            return false;
        }

        // Validate name (no spaces, semicolons, or commas)
        if (preg_match('/[\s;,]/', $cookie['name'])) {
            return false;
        }

        // Validate value (basic check)
        if (strlen($cookie['value']) > 4096) {
            return false;
        }

        // Validate domain
        if (!empty($cookie['domain']) && !$this->isValidDomain($cookie['domain'])) {
            return false;
        }

        // Validate path
        if (!empty($cookie['path']) && !str_starts_with($cookie['path'], '/')) {
            return false;
        }

        // Validate same-site value
        $validSameSite = ['Strict', 'Lax', 'None'];
        if (!in_array($cookie['same_site'], $validSameSite)) {
            return false;
        }

        return true;
    }

    /**
     * Check if cookie is expired
     */
    private function isCookieExpired(array $cookie): bool
    {
        // Check max-age
        if ($cookie['max_age'] !== null) {
            $age = time() - $cookie['created'];
            return $age > $cookie['max_age'];
        }

        // Check expires
        if ($cookie['expires'] !== null) {
            return time() > $cookie['expires'];
        }

        return false;
    }

    /**
     * Check if domain matches
     */
    private function isDomainMatch(string $requestDomain, string $cookieDomain): bool
    {
        if (empty($cookieDomain)) {
            return true;
        }

        // Exact match
        if ($requestDomain === $cookieDomain) {
            return true;
        }

        // Subdomain match (cookie domain starts with dot)
        if (str_starts_with($cookieDomain, '.')) {
            $baseDomain = substr($cookieDomain, 1);
            return str_ends_with($requestDomain, $baseDomain);
        }

        return false;
    }

    /**
     * Check if path matches
     */
    private function isPathMatch(string $requestPath, string $cookiePath): bool
    {
        if (empty($cookiePath)) {
            return true;
        }

        // Exact match
        if ($requestPath === $cookiePath) {
            return true;
        }

        // Path prefix match
        return str_starts_with($requestPath, $cookiePath);
    }

    /**
     * Generate cookie key
     */
    private function getCookieKey(string $name, string $domain, string $path): string
    {
        return md5($name . '|' . $domain . '|' . $path);
    }

    /**
     * Parse cookie header
     */
    private function parseCookieHeader(string $header, string $domain, string $path): ?array
    {
        $parts = explode(';', $header);
        $nameValue = array_shift($parts);
        
        if (!str_contains($nameValue, '=')) {
            return null;
        }

        [$name, $value] = explode('=', $nameValue, 2);
        $name = trim($name);
        $value = trim($value);

        $options = [
            'domain' => $domain,
            'path' => $path,
            'secure' => false,
            'http_only' => false,
            'same_site' => 'Lax'
        ];

        foreach ($parts as $part) {
            $part = trim($part);
            $lowerPart = strtolower($part);

            if ($lowerPart === 'secure') {
                $options['secure'] = true;
            } elseif ($lowerPart === 'httponly') {
                $options['http_only'] = true;
            } elseif (str_starts_with($lowerPart, 'domain=')) {
                $options['domain'] = trim(substr($part, 7));
            } elseif (str_starts_with($lowerPart, 'path=')) {
                $options['path'] = trim(substr($part, 5));
            } elseif (str_starts_with($lowerPart, 'max-age=')) {
                $options['max_age'] = (int) trim(substr($part, 8));
            } elseif (str_starts_with($lowerPart, 'expires=')) {
                $expires = strtotime(trim(substr($part, 8)));
                if ($expires !== false) {
                    $options['expires'] = $expires;
                }
            } elseif (str_starts_with($lowerPart, 'samesite=')) {
                $sameSite = trim(substr($part, 9));
                if (in_array($sameSite, ['Strict', 'Lax', 'None'])) {
                    $options['same_site'] = $sameSite;
                }
            }
        }

        return [
            'name' => $name,
            'value' => $value,
            'domain' => $options['domain'],
            'path' => $options['path'],
            'expires' => $options['expires'] ?? null,
            'max_age' => $options['max_age'] ?? null,
            'secure' => $options['secure'],
            'http_only' => $options['http_only'],
            'same_site' => $options['same_site'],
            'created' => time(),
            'last_accessed' => time()
        ];
    }

    /**
     * Validate domain
     */
    private function isValidDomain(string $domain): bool
    {
        // Basic domain validation
        if (empty($domain)) {
            return true;
        }

        // Check for valid domain format
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $domain)) {
            return false;
        }

        // Check for valid TLD
        if (!preg_match('/\.[a-zA-Z]{2,}$/', $domain)) {
            return false;
        }

        return true;
    }

    /**
     * Load cookies from storage
     */
    private function loadCookies(): void
    {
        try {
            if (file_exists($this->storagePath)) {
                $content = file_get_contents($this->storagePath);
                $cookies = json_decode($content, true);
                
                if (is_array($cookies)) {
                    $this->cookies = $cookies;
                    $this->logger->debug("Cookies loaded from storage", ['count' => count($cookies)]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to load cookies: " . $e->getMessage());
        }
    }

    /**
     * Save cookies to storage
     */
    private function saveCookies(): void
    {
        try {
            $content = json_encode($this->cookies, JSON_PRETTY_PRINT);
            file_put_contents($this->storagePath, $content);
            $this->logger->debug("Cookies saved to storage", ['count' => count($this->cookies)]);
        } catch (\Exception $e) {
            $this->logger->error("Failed to save cookies: " . $e->getMessage());
        }
    }

    /**
     * Close and cleanup
     */
    public function close(): void
    {
        if ($this->persistent) {
            $this->saveCookies();
        }
        
        $this->logger->info("Cookie jar service closed");
    }
}
