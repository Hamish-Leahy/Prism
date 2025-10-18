<?php

return [
    'name' => 'Prism Browser Backend',
    'version' => '1.0.0',
    'environment' => $_ENV['APP_ENV'] ?? 'development',
    'debug' => $_ENV['APP_DEBUG'] ?? true,
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
    'locale' => $_ENV['APP_LOCALE'] ?? 'en',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    
    'database' => [
        'driver' => $_ENV['DB_DRIVER'] ?? 'sqlite',
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'database' => $_ENV['DB_DATABASE'] ?? 'prism_browser.sqlite',
        'username' => $_ENV['DB_USERNAME'] ?? '',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
        'prefix' => $_ENV['DB_PREFIX'] ?? '',
        'strict' => $_ENV['DB_STRICT'] ?? true,
        'engine' => $_ENV['DB_ENGINE'] ?? null,
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],
    
    'supabase' => [
        'enabled' => $_ENV['SUPABASE_ENABLED'] ?? false,
        'url' => $_ENV['SUPABASE_URL'] ?? '',
        'key' => $_ENV['SUPABASE_KEY'] ?? '',
        'host' => $_ENV['SUPABASE_HOST'] ?? 'localhost',
        'port' => $_ENV['SUPABASE_PORT'] ?? 5432,
        'database' => $_ENV['SUPABASE_DATABASE'] ?? 'postgres',
        'username' => $_ENV['SUPABASE_USERNAME'] ?? 'postgres',
        'password' => $_ENV['SUPABASE_PASSWORD'] ?? '',
    ],
    
    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-this-in-production',
        'algorithm' => $_ENV['JWT_ALGORITHM'] ?? 'HS256',
        'expiration' => $_ENV['JWT_EXPIRATION'] ?? 3600, // 1 hour
        'refresh_expiration' => $_ENV['JWT_REFRESH_EXPIRATION'] ?? 604800, // 7 days
        'issuer' => $_ENV['JWT_ISSUER'] ?? 'prism-browser',
        'audience' => $_ENV['JWT_AUDIENCE'] ?? 'prism-browser-users',
    ],
    
    'engines' => [
        'prism' => [
            'enabled' => true,
            'default' => true,
            'config' => [
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
                'html_parsing' => true,
                'css_parsing' => true,
                'javascript_execution' => true,
                'javascript_enabled' => true,
                'css_enabled' => true,
                'images_enabled' => true,
                'cookies_enabled' => true,
                'local_storage_enabled' => true,
                'session_storage_enabled' => true,
                'websocket_enabled' => true,
                'cache_enabled' => true,
                'webrtc_enabled' => true,
                'webassembly_enabled' => true,
                'service_worker_enabled' => true,
                'push_notifications_enabled' => true,
                'offline_enabled' => true,
                'plugins_enabled' => true,
            ]
        ],
        'chromium' => [
            'enabled' => true,
            'default' => false,
            'config' => [
                'headless' => true,
                'sandbox' => true,
                'window_size' => ['width' => 1920, 'height' => 1080],
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'extensions' => true,
                'timeout' => 30,
                'memory_limit' => '512M',
            ]
        ],
        'firefox' => [
            'enabled' => true,
            'default' => false,
            'config' => [
                'headless' => true,
                'private_mode' => true,
                'tracking_protection' => true,
                'do_not_track' => true,
                'block_third_party' => true,
                'extensions' => true,
                'window_size' => ['width' => 1920, 'height' => 1080],
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:120.0) Gecko/20100101 Firefox/120.0',
                'timeout' => 30,
                'memory_limit' => '512M',
            ]
        ]
    ],
    
    'plugins' => [
        'enabled' => true,
        'auto_load' => true,
        'paths' => [
            __DIR__ . '/../src/Services/Plugins/',
            __DIR__ . '/../../plugins/'
        ],
        'enabled_plugins' => [
            'AdBlockerPlugin',
            'PerformancePlugin',
            'PrivacyPlugin'
        ],
        'api_tokens' => [
            // Plugin-specific API tokens can be added here
        ],
        'plugin_config' => [
            'AdBlockerPlugin' => [
                'blocked_domains' => [],
                'blocked_selectors' => [],
                'blocked_scripts' => [],
                'filter_list_path' => null,
            ],
            'PerformancePlugin' => [
                'enable_monitoring' => true,
                'metrics_interval' => 1000,
                'memory_threshold' => 0.8,
                'cpu_threshold' => 0.8,
            ],
            'PrivacyPlugin' => [
                'block_trackers' => true,
                'block_ads' => true,
                'block_cookies' => false,
                'block_local_storage' => false,
                'block_session_storage' => false,
                'block_indexed_db' => false,
                'block_web_sql' => false,
                'block_file_system' => false,
                'block_geolocation' => true,
                'block_camera' => true,
                'block_microphone' => true,
                'block_notifications' => true,
                'block_media_devices' => true,
                'block_screen_orientation' => true,
                'block_fullscreen' => true,
                'block_pointer_lock' => true,
                'block_clipboard' => true,
                'block_battery' => true,
                'block_network_information' => true,
                'block_device_orientation' => true,
                'block_vibration' => true,
                'block_web_rtc' => false,
                'block_web_sockets' => false,
                'block_push_notifications' => true,
                'block_service_workers' => false,
                'block_web_assembly' => false,
                'block_web_gl' => false,
                'block_web_audio' => false,
                'block_intersection_observer' => false,
                'block_mutation_observer' => false,
                'block_resize_observer' => false,
                'block_performance_observer' => false,
                'block_user_timing' => false,
                'block_navigation_timing' => false,
                'block_resource_timing' => false,
                'block_paint_timing' => false,
                'block_layout_shift' => false,
                'block_first_input_delay' => false,
                'block_largest_contentful_paint' => false,
                'block_cumulative_layout_shift' => false,
                'block_first_contentful_paint' => false,
                'block_time_to_interactive' => false,
                'block_speed_index' => false,
            ]
        ]
    ],
    
    'cache' => [
        'default' => 'file',
        'stores' => [
            'file' => [
                'driver' => 'file',
                'path' => __DIR__ . '/../cache',
                'ttl' => 3600,
            ],
            'memory' => [
                'driver' => 'memory',
                'ttl' => 300,
                'max_size' => '64M',
            ],
            'redis' => [
                'driver' => 'redis',
                'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
                'port' => $_ENV['REDIS_PORT'] ?? 6379,
                'password' => $_ENV['REDIS_PASSWORD'] ?? null,
                'database' => $_ENV['REDIS_DATABASE'] ?? 0,
                'ttl' => 3600,
            ],
        ],
    ],
    
    'logging' => [
        'default' => 'single',
        'channels' => [
            'single' => [
                'driver' => 'single',
                'path' => __DIR__ . '/../logs/app.log',
                'level' => 'debug',
            ],
            'daily' => [
                'driver' => 'daily',
                'path' => __DIR__ . '/../logs/app.log',
                'level' => 'debug',
                'days' => 14,
            ],
            'slack' => [
                'driver' => 'slack',
                'url' => $_ENV['LOG_SLACK_WEBHOOK_URL'] ?? null,
                'username' => 'Prism Browser',
                'emoji' => ':boom:',
                'level' => 'critical',
            ],
            'papertrail' => [
                'driver' => 'monolog',
                'level' => 'debug',
                'handler' => 'Monolog\Handler\SyslogUdpHandler',
                'handler_with' => [
                    'host' => $_ENV['PAPERTRAIL_URL'] ?? null,
                    'port' => $_ENV['PAPERTRAIL_PORT'] ?? null,
                ],
            ],
            'stderr' => [
                'driver' => 'monolog',
                'handler' => 'Monolog\Handler\StreamHandler',
                'with' => [
                    'stream' => 'php://stderr',
                ],
            ],
            'syslog' => [
                'driver' => 'syslog',
                'level' => 'debug',
            ],
            'errorlog' => [
                'driver' => 'errorlog',
                'level' => 'debug',
            ],
        ],
    ],
    
    'mail' => [
        'default' => 'smtp',
        'mailers' => [
            'smtp' => [
                'transport' => 'smtp',
                'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
                'port' => $_ENV['MAIL_PORT'] ?? 587,
                'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
                'username' => $_ENV['MAIL_USERNAME'] ?? '',
                'password' => $_ENV['MAIL_PASSWORD'] ?? '',
                'timeout' => null,
                'auth_mode' => null,
            ],
            'ses' => [
                'transport' => 'ses',
            ],
            'mailgun' => [
                'transport' => 'mailgun',
            ],
            'postmark' => [
                'transport' => 'postmark',
            ],
            'sendmail' => [
                'transport' => 'sendmail',
                'path' => '/usr/sbin/sendmail -bs',
            ],
        ],
        'from' => [
            'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'hello@example.com',
            'name' => $_ENV['MAIL_FROM_NAME'] ?? 'Prism Browser',
        ],
    ],
    
    'queue' => [
        'default' => 'sync',
        'connections' => [
            'sync' => [
                'driver' => 'sync',
            ],
            'database' => [
                'driver' => 'database',
                'table' => 'jobs',
                'queue' => 'default',
                'retry_after' => 90,
            ],
            'beanstalkd' => [
                'driver' => 'beanstalkd',
                'host' => 'localhost',
                'queue' => 'default',
                'retry_after' => 90,
                'block_for' => 0,
            ],
            'sqs' => [
                'driver' => 'sqs',
                'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? '',
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? '',
                'prefix' => $_ENV['SQS_PREFIX'] ?? '',
                'queue' => $_ENV['SQS_QUEUE'] ?? 'default',
                'region' => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
            ],
            'redis' => [
                'driver' => 'redis',
                'connection' => 'default',
                'queue' => 'default',
                'retry_after' => 90,
                'block_for' => null,
            ],
        ],
        'failed' => [
            'driver' => 'database',
            'database' => 'default',
            'table' => 'failed_jobs',
        ],
    ],
    
    'session' => [
        'driver' => $_ENV['SESSION_DRIVER'] ?? 'file',
        'lifetime' => $_ENV['SESSION_LIFETIME'] ?? 120,
        'expire_on_close' => false,
        'encrypt' => false,
        'files' => __DIR__ . '/../storage/framework/sessions',
        'connection' => null,
        'table' => 'sessions',
        'store' => null,
        'lottery' => [2, 100],
        'cookie' => 'prism_session',
        'path' => '/',
        'domain' => $_ENV['SESSION_DOMAIN'] ?? null,
        'secure' => $_ENV['SESSION_SECURE_COOKIE'] ?? false,
        'http_only' => true,
        'same_site' => 'lax',
    ],
    
    'cors' => [
        'paths' => ['api/*', 'sanctum/csrf-cookie'],
        'allowed_methods' => ['*'],
        'allowed_origins' => ['*'],
        'allowed_origins_patterns' => [],
        'allowed_headers' => ['*'],
        'exposed_headers' => [],
        'max_age' => 0,
        'supports_credentials' => false,
    ],
    
    'rate_limiting' => [
        'enabled' => true,
        'max_attempts' => 60,
        'decay_minutes' => 1,
        'key' => 'ip',
        'prefix' => 'rate_limiter',
    ],
    
    'security' => [
        'encryption_key' => $_ENV['APP_KEY'] ?? 'base64:' . base64_encode(random_bytes(32)),
        'cipher' => 'AES-256-CBC',
        'hash' => 'bcrypt',
        'hash_rounds' => 12,
        'password_reset' => [
            'expire' => 60, // minutes
            'throttle' => 60, // seconds
        ],
        'verification' => [
            'expire' => 60, // minutes
            'throttle' => 60, // seconds
        ],
    ],
    
    'features' => [
        'registration' => true,
        'email_verification' => true,
        'password_reset' => true,
        'two_factor' => false,
        'api_rate_limiting' => true,
        'cors' => true,
        'maintenance_mode' => false,
        'debug_mode' => true,
        'profiling' => false,
        'caching' => true,
        'compression' => true,
        'gzip' => true,
        'brotli' => true,
    ],
    
    'maintenance' => [
        'enabled' => false,
        'secret' => $_ENV['MAINTENANCE_SECRET'] ?? 'prism-maintenance',
        'message' => 'The application is currently in maintenance mode. Please try again later.',
        'retry_after' => 60,
        'refresh' => 5,
    ],
    
    'health' => [
        'enabled' => true,
        'path' => '/health',
        'checks' => [
            'database' => true,
            'cache' => true,
            'queue' => false,
            'redis' => false,
            'mail' => false,
        ],
    ],
];