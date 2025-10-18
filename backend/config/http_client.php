<?php

return [
    'enabled' => true,
    'debug' => false,
    'timeout' => 30,
    'connect_timeout' => 10,
    'read_timeout' => 30,
    'verify_ssl' => true,
    'max_redirects' => 10,
    'strict_redirects' => false,
    'follow_referer' => true,
    'allowed_protocols' => ['http', 'https'],
    'enable_cookies' => true,
    'cache_ttl' => 300,
    'max_retries' => 3,
    'user_agent' => 'Prism/1.0 (Custom Engine)',
    'default_headers' => [
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.9',
        'Accept-Encoding' => 'gzip, deflate, br',
        'Connection' => 'keep-alive',
        'Upgrade-Insecure-Requests' => '1',
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-Site' => 'none',
        'Cache-Control' => 'max-age=0',
    ],
    'curl_options' => [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => 'gzip,deflate,br',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_DNS_CACHE_TIMEOUT => 300,
        CURLOPT_TCP_KEEPALIVE => 1,
        CURLOPT_TCP_KEEPIDLE => 60,
        CURLOPT_TCP_KEEPINTVL => 10,
    ],
    'user_agents' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:120.0) Gecko/20100101 Firefox/120.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 OPR/106.0.0.0',
    ],
    'retry' => [
        'enabled' => true,
        'max_attempts' => 3,
        'delay' => 1000, // milliseconds
        'backoff_multiplier' => 2,
        'max_delay' => 10000, // milliseconds
        'retry_on' => [
            'connection_exceptions' => true,
            'server_errors' => true, // 5xx
            'timeout_exceptions' => true,
            'too_many_redirects' => true,
        ]
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 300, // seconds
        'max_size' => 100, // number of responses
        'storage' => 'memory', // memory, file, redis
        'path' => __DIR__ . '/../cache/http',
        'compress' => true,
        'serialize' => true,
    ],
    'proxy' => [
        'enabled' => false,
        'url' => null,
        'auth' => null, // username:password
        'type' => 'http', // http, socks4, socks5
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'log_requests' => true,
        'log_responses' => true,
        'log_errors' => true,
        'log_retries' => true,
        'include_headers' => false,
        'include_body' => false,
        'max_body_length' => 1024,
    ],
    'performance' => [
        'connection_pooling' => true,
        'max_connections' => 10,
        'max_connections_per_host' => 5,
        'keep_alive' => true,
        'keep_alive_timeout' => 30,
        'pipeline' => false,
        'compression' => true,
        'gzip' => true,
        'deflate' => true,
        'brotli' => true,
    ],
    'security' => [
        'verify_ssl' => true,
        'allow_self_signed' => false,
        'ca_bundle' => null,
        'client_cert' => null,
        'client_key' => null,
        'passphrase' => null,
        'ciphers' => null,
        'min_tls_version' => '1.2',
        'max_tls_version' => '1.3',
    ],
    'limits' => [
        'max_redirects' => 10,
        'max_retries' => 3,
        'max_response_size' => 10485760, // 10MB
        'max_request_size' => 1048576, // 1MB
        'max_header_size' => 8192, // 8KB
        'max_cookies' => 50,
        'max_cookie_size' => 4096, // 4KB
    ],
    'features' => [
        'cookies' => true,
        'redirects' => true,
        'compression' => true,
        'chunked_transfer' => true,
        'http2' => true,
        'websockets' => false,
        'multipart' => true,
        'json' => true,
        'xml' => true,
        'form_data' => true,
        'url_encoded' => true,
    ]
];