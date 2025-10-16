<?php

return [
    'app' => [
        'name' => 'Prism Browser Backend',
        'version' => '1.0.0',
        'debug' => $_ENV['APP_DEBUG'] ?? false,
        'timezone' => 'UTC',
    ],
    
    'database' => [
        'driver' => $_ENV['DB_DRIVER'] ?? 'sqlite',
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'database' => $_ENV['DB_DATABASE'] ?? 'prism.db',
        'username' => $_ENV['DB_USERNAME'] ?? '',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],
    
    'engines' => [
        'default' => $_ENV['DEFAULT_ENGINE'] ?? 'chromium',
        'available' => [
            'chromium' => [
                'name' => 'Chromium',
                'description' => 'Chromium-based engine with full web compatibility',
                'class' => 'Prism\\Backend\\Services\\Engines\\ChromiumEngine',
                'enabled' => true,
                'config' => [
                    'binary_path' => $_ENV['CHROMIUM_BINARY'] ?? '/usr/bin/chromium-browser',
                    'headless' => false,
                    'sandbox' => true,
                    'extensions' => true,
                ]
            ],
            'firefox' => [
                'name' => 'Firefox',
                'description' => 'Firefox-based engine with privacy focus',
                'class' => 'Prism\\Backend\\Services\\Engines\\FirefoxEngine',
                'enabled' => true,
                'config' => [
                    'binary_path' => $_ENV['FIREFOX_BINARY'] ?? '/usr/bin/firefox',
                    'headless' => false,
                    'private_mode' => true,
                    'extensions' => true,
                ]
            ],
            'prism' => [
                'name' => 'Prism',
                'description' => 'Custom lightweight engine',
                'class' => 'Prism\\Backend\\Services\\Engines\\PrismEngine',
                'enabled' => true,
                'config' => [
                    'memory_limit' => '256M',
                    'timeout' => 30,
                    'cache_enabled' => true,
                    'javascript_enabled' => true,
                ]
            ]
        ]
    ],
    
    'api' => [
        'base_url' => $_ENV['API_BASE_URL'] ?? 'http://localhost:8000',
        'cors' => [
            'enabled' => true,
            'origins' => ['http://localhost:3000', 'http://localhost:5173'],
            'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'headers' => ['Content-Type', 'Authorization', 'X-Requested-With']
        ],
        'rate_limit' => [
            'enabled' => true,
            'requests_per_minute' => 100,
            'burst_limit' => 20
        ]
    ],
    
    'logging' => [
        'level' => $_ENV['LOG_LEVEL'] ?? 'info',
        'path' => $_ENV['LOG_PATH'] ?? 'logs/app.log',
        'max_files' => 5,
        'max_size' => '10MB'
    ],
    
    'security' => [
        'encryption_key' => $_ENV['ENCRYPTION_KEY'] ?? 'your-secret-key-here',
        'session_lifetime' => 3600, // 1 hour
        'csrf_protection' => true,
        'xss_protection' => true
    ],
    
    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'] ?? 'your-jwt-secret-key-change-this-in-production',
        'expiration' => $_ENV['JWT_EXPIRATION'] ?? 3600, // 1 hour
        'refresh_expiration' => $_ENV['JWT_REFRESH_EXPIRATION'] ?? 604800, // 7 days
        'algorithm' => 'HS256'
    ]
];
