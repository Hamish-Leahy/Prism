<?php

return [
    'default' => [
        'timeout' => 30,
        'connect_timeout' => 10,
        'read_timeout' => 30,
        'verify_ssl' => true,
        'max_redirects' => 10,
        'strict_redirects' => false,
        'follow_referer' => true,
        'allowed_protocols' => ['http', 'https'],
        'enable_cookies' => true,
        'cache_ttl' => 300, // 5 minutes
        'max_retries' => 3,
        'user_agent' => 'Prism/1.0 (Custom Engine)',
    ],
    
    'prism_engine' => [
        'timeout' => 30,
        'connect_timeout' => 10,
        'read_timeout' => 30,
        'verify_ssl' => true,
        'max_redirects' => 10,
        'strict_redirects' => false,
        'follow_referer' => true,
        'allowed_protocols' => ['http', 'https'],
        'enable_cookies' => true,
        'cache_ttl' => 600, // 10 minutes for Prism engine
        'max_retries' => 3,
        'user_agent' => 'Prism/1.0 (Custom Engine)',
    ],
    
    'chromium_engine' => [
        'timeout' => 60,
        'connect_timeout' => 15,
        'read_timeout' => 60,
        'verify_ssl' => true,
        'max_redirects' => 20,
        'strict_redirects' => false,
        'follow_referer' => true,
        'allowed_protocols' => ['http', 'https'],
        'enable_cookies' => true,
        'cache_ttl' => 1800, // 30 minutes for Chromium
        'max_retries' => 2,
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ],
    
    'firefox_engine' => [
        'timeout' => 60,
        'connect_timeout' => 15,
        'read_timeout' => 60,
        'verify_ssl' => true,
        'max_redirects' => 20,
        'strict_redirects' => false,
        'follow_referer' => true,
        'allowed_protocols' => ['http', 'https'],
        'enable_cookies' => true,
        'cache_ttl' => 1800, // 30 minutes for Firefox
        'max_retries' => 2,
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:120.0) Gecko/20100101 Firefox/120.0',
    ],
    
    'user_agents' => [
        'chrome' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'firefox' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:120.0) Gecko/20100101 Firefox/120.0',
        'safari' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
        'edge' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
        'opera' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 OPR/106.0.0.0',
        'prism' => 'Prism/1.0 (Custom Engine)',
    ],
    
    'retry_strategies' => [
        'exponential_backoff' => [
            'base_delay' => 1000, // 1 second
            'max_delay' => 30000, // 30 seconds
            'multiplier' => 2,
            'jitter' => true,
        ],
        'linear_backoff' => [
            'base_delay' => 2000, // 2 seconds
            'max_delay' => 10000, // 10 seconds
            'increment' => 1000, // 1 second
            'jitter' => false,
        ],
        'fixed_delay' => [
            'delay' => 5000, // 5 seconds
            'jitter' => true,
        ],
    ],
    
    'cache_strategies' => [
        'memory_only' => [
            'type' => 'memory',
            'max_size' => 100,
            'ttl' => 300,
        ],
        'file_based' => [
            'type' => 'file',
            'path' => 'cache/http/',
            'max_size' => 1000,
            'ttl' => 1800,
        ],
        'redis' => [
            'type' => 'redis',
            'host' => 'localhost',
            'port' => 6379,
            'database' => 0,
            'ttl' => 3600,
        ],
    ],
    
    'proxy_settings' => [
        'enabled' => false,
        'type' => 'http', // http, https, socks4, socks5
        'host' => 'localhost',
        'port' => 8080,
        'username' => null,
        'password' => null,
        'bypass' => ['localhost', '127.0.0.1'],
    ],
    
    'security' => [
        'verify_ssl' => true,
        'allow_self_signed' => false,
        'check_hostname' => true,
        'ciphers' => 'HIGH:!SSLv2:!SSLv3',
        'protocols' => ['TLSv1.2', 'TLSv1.3'],
    ],
    
    'performance' => [
        'connection_pooling' => true,
        'keep_alive' => true,
        'tcp_keepalive' => true,
        'tcp_keepidle' => 60,
        'tcp_keepintvl' => 10,
        'tcp_keepcnt' => 3,
        'dns_cache_ttl' => 300,
        'max_connections' => 10,
    ],
    
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug, info, warning, error
        'log_requests' => true,
        'log_responses' => true,
        'log_headers' => false,
        'log_body' => false,
        'max_body_size' => 1024, // bytes
    ],
    
    'rate_limiting' => [
        'enabled' => false,
        'requests_per_minute' => 60,
        'burst_limit' => 10,
        'per_domain' => true,
    ],
    
    'compression' => [
        'enabled' => true,
        'algorithms' => ['gzip', 'deflate', 'br'],
        'min_size' => 1024, // bytes
    ],
    
    'timeouts' => [
        'default' => 30,
        'connect' => 10,
        'read' => 30,
        'write' => 30,
        'total' => 60,
    ],
];
