<?php

/**
 * Application Configuration
 * Main configuration file for the football manager application
 *
 * File: config/app.php
 * Directory: /config/
 */

return [
    // Application settings
    'app' => [
        'name' => 'Kickerscup',
        'version' => '2.0.0',
        'environment' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'timezone' => 'Europe/Berlin',
        'locale' => 'de',
        'fallback_locale' => 'en',
        'url' => $_ENV['APP_URL'] ?? 'http://localhost',
        'cdn_url' => $_ENV['CDN_URL'] ?? null,
    ],

    // Database configuration with connection pooling and read replicas
    'database' => [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'write' => [
                    'driver' => 'mysql',
                    'host' => $_ENV['DB_HOST'] ?? 'localhost',
                    'port' => (int)($_ENV['DB_PORT'] ?? 3306),
                    'database' => $_ENV['DB_DATABASE'] ?? 'football_manager',
                    'username' => $_ENV['DB_USERNAME'] ?? 'root',
                    'password' => $_ENV['DB_PASSWORD'] ?? '',
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'timeout' => 30,
                    'persistent' => false,
                    'ssl' => [
                        'ca' => $_ENV['DB_SSL_CA'] ?? null,
                        'cert' => $_ENV['DB_SSL_CERT'] ?? null,
                        'key' => $_ENV['DB_SSL_KEY'] ?? null,
                        'verify_server_cert' => filter_var($_ENV['DB_SSL_VERIFY'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ]
                ],
                'read' => [
                    [
                        'driver' => 'mysql',
                        'host' => $_ENV['DB_READ_HOST_1'] ?? $_ENV['DB_HOST'] ?? 'localhost',
                        'port' => (int)($_ENV['DB_READ_PORT_1'] ?? $_ENV['DB_PORT'] ?? 3306),
                        'database' => $_ENV['DB_DATABASE'] ?? 'football_manager',
                        'username' => $_ENV['DB_READ_USERNAME_1'] ?? $_ENV['DB_USERNAME'] ?? 'root',
                        'password' => $_ENV['DB_READ_PASSWORD_1'] ?? $_ENV['DB_PASSWORD'] ?? '',
                        'charset' => 'utf8mb4',
                        'collation' => 'utf8mb4_unicode_ci',
                        'timeout' => 30,
                        'persistent' => false,
                    ],
                ]
            ]
        ],
        'test_on_boot' => filter_var($_ENV['DB_TEST_ON_BOOT'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],

    // Security configuration
    'security' => [
        'password' => [
            'algorithm' => PASSWORD_ARGON2ID,
            'options' => [
                'memory_cost' => 65536, // 64MB
                'time_cost' => 4,
                'threads' => 3,
            ],
        ],
        'csrf' => [
            'enabled' => true,
            'token_name' => 'csrf_token',
            'token_lifetime' => 3600, // 1 hour
        ],
        'session' => [
            'name' => 'fm_session',
            'lifetime' => 0, // Session cookie expires when browser closes
            'path' => '/',
            'domain' => $_ENV['SESSION_DOMAIN'] ?? '',
            'secure' => filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'httponly' => true,
            'samesite' => 'Strict',
            'auto_start' => false, // Start session on first access, not during bootstrap
            'gc_maxlifetime' => 1440, // 24 minutes garbage collection
            'gc_probability' => 1,
            'gc_divisor' => 100,
            'regenerate_interval' => 1800, // Regenerate ID every 30 minutes
            'max_inactive_time' => 3600, // 1 hour max inactive time
        ],
        'rate_limiting' => [
            'registration' => [
                'max_attempts' => 5,
                'window' => 3600, // 1 hour
            ],
            'login' => [
                'max_attempts' => 10,
                'window' => 900, // 15 minutes
            ],
            'password_reset' => [
                'max_attempts' => 3,
                'window' => 3600, // 1 hour
            ],
        ],
    ],

    // Email configuration
    'email' => [
        'driver' => $_ENV['MAIL_DRIVER'] ?? 'smtp',
        'smtp' => [
            'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
            'port' => (int)($_ENV['MAIL_PORT'] ?? 587),
            'username' => $_ENV['MAIL_USERNAME'] ?? '',
            'password' => $_ENV['MAIL_PASSWORD'] ?? '',
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'auth' => true,
            'timeout' => 30,
        ],
        'from' => [
            'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@footballmanager.com',
            'name' => $_ENV['MAIL_FROM_NAME'] ?? 'Football Manager',
        ],
        'templates' => [
            'path' => __DIR__ . '/../templates/email/',
            'cache' => __DIR__ . '/../storage/email_cache/',
        ],
        'queue' => [
            'enabled' => filter_var($_ENV['MAIL_QUEUE_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'batch_size' => (int)($_ENV['MAIL_QUEUE_BATCH_SIZE'] ?? 10),
            'retry_attempts' => (int)($_ENV['MAIL_QUEUE_RETRY_ATTEMPTS'] ?? 3),
            'retry_delay' => (int)($_ENV['MAIL_QUEUE_RETRY_DELAY'] ?? 300), // 5 minutes
        ],
        'log_path' => __DIR__ . '/../storage/logs/emails.log',
    ],

    // Caching configuration
    'cache' => [
        'default' => 'file', // Changed from redis to file for simpler setup
        'stores' => [
            'redis' => [
                'driver' => 'redis',
                'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
                'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
                'password' => $_ENV['REDIS_PASSWORD'] ?? null,
                'database' => (int)($_ENV['REDIS_DB'] ?? 0),
                'timeout' => 5.0,
                'prefix' => 'fm_cache:',
            ],
            'file' => [
                'driver' => 'file',
                'path' => __DIR__ . '/../storage/cache/',
            ],
        ],
        'ttl' => [
            'user' => 3600, // 1 hour
            'team' => 1800, // 30 minutes
            'player' => 900, // 15 minutes
            'league' => 3600, // 1 hour
            'routes' => 86400, // 24 hours
            'translations' => 3600, // 1 hour
        ],
    ],

    // Logging configuration
    'logging' => [
        'default' => 'daily',
        'channels' => [
            'daily' => [
                'driver' => 'daily',
                'path' => __DIR__ . '/../storage/logs/application.log',
                'level' => $_ENV['LOG_LEVEL'] ?? 'info',
                'days' => (int)($_ENV['LOG_RETENTION_DAYS'] ?? 14),
                'permission' => 0644,
            ],
            'single' => [
                'driver' => 'single',
                'path' => __DIR__ . '/../storage/logs/application.log',
                'level' => $_ENV['LOG_LEVEL'] ?? 'info',
            ],
            'syslog' => [
                'driver' => 'syslog',
                'level' => $_ENV['LOG_LEVEL'] ?? 'info',
            ],
            'error' => [
                'driver' => 'daily',
                'path' => __DIR__ . '/../storage/logs/error.log',
                'level' => 'error',
                'days' => 30,
            ],
            'security' => [
                'driver' => 'daily',
                'path' => __DIR__ . '/../storage/logs/security.log',
                'level' => 'info',
                'days' => 90,
            ],
        ],
        'correlation_ids' => [
            'enabled' => true,
            'header_name' => 'X-Correlation-ID',
            'generate_if_missing' => true,
        ],
    ],

    // Localization configuration - NEW
    'localization' => [
        'storage' => 'database',
        'supported_locales' => ['de', 'en', 'es', 'fr'],
        'fallback_locale' => 'en',
        'cache_enabled' => !filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'cache_ttl' => 3600, // 1 hour
        'auto_detect' => true,
        'preload_categories' => ['general', 'validation', 'auth', 'game', 'registration'],
        'memory_limit' => 1000, // Max translations in memory per category
        'file_fallback_path' => __DIR__ . '/../resources/lang/',
        'development_mode' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'auto_register_missing' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],

    // Performance configuration with route caching
    'performance' => [
        'route_cache' => [
            'enabled' => !filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'path' => __DIR__ . '/../storage/cache/routes.php',
            'auto_invalidate' => true, // Check file modification times
            'check_source_changes' => true, // Automatically rebuild if source files change
        ],
        'lazy_loading' => [
            'enabled' => true,
            'batch_size' => 50,
        ],
        'database' => [
            'query_cache_enabled' => true,
            'slow_query_threshold' => 2.0, // seconds
            'batch_insert_size' => 1000,
            'connection_pool_size' => 10,
        ],
        'response' => [
            'compression_enabled' => true,
            'compression_level' => 6,
            'etag_enabled' => true,
            'cache_headers' => [
                'static_assets' => 'max-age=31536000, public', // 1 year
                'dynamic_content' => 'max-age=300, private', // 5 minutes
            ],
        ],
        'memory' => [
            'limit' => '256M',
            'opcache_enabled' => true,
            'preload_enabled' => false, // Can be enabled in production
        ],
    ],

    // CDN and Load Balancing
    'cdn' => [
        'enabled' => filter_var($_ENV['CDN_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'url' => $_ENV['CDN_URL'] ?? null,
        'assets' => [
            'css' => true,
            'js' => true,
            'images' => true,
            'fonts' => true,
        ],
        'cache_headers' => [
            'css' => 'max-age=31536000, public', // 1 year
            'js' => 'max-age=31536000, public', // 1 year
            'images' => 'max-age=2592000, public', // 30 days
            'fonts' => 'max-age=31536000, public', // 1 year
        ],
        'compression' => [
            'gzip' => true,
            'brotli' => true,
        ],
    ],

    // Load balancing configuration
    'load_balancing' => [
        'nginx' => [
            'upstream_servers' => [
                '127.0.0.1:9000',
                '127.0.0.1:9001',
                '127.0.0.1:9002',
            ],
            'method' => 'round_robin', // round_robin, least_conn, ip_hash
            'health_check' => [
                'enabled' => true,
                'interval' => 30, // seconds
                'timeout' => 5, // seconds
                'path' => '/health',
            ],
        ],
        'php_fpm' => [
            'pools' => [
                'registration' => [
                    'pm' => 'dynamic',
                    'pm.max_children' => 20,
                    'pm.start_servers' => 5,
                    'pm.min_spare_servers' => 3,
                    'pm.max_spare_servers' => 10,
                ],
                'general' => [
                    'pm' => 'dynamic',
                    'pm.max_children' => 50,
                    'pm.start_servers' => 10,
                    'pm.min_spare_servers' => 5,
                    'pm.max_spare_servers' => 20,
                ],
            ],
        ],
    ],

    // API configuration
    'api' => [
        'version' => 'v1',
        'rate_limiting' => [
            'enabled' => true,
            'requests_per_minute' => 60,
            'burst_limit' => 10,
        ],
        'authentication' => [
            'jwt' => [
                'secret' => $_ENV['JWT_SECRET'] ?? 'your-jwt-secret-key',
                'algorithm' => 'HS256',
                'expiration' => 3600, // 1 hour
                'refresh_expiration' => 2592000, // 30 days
            ],
        ],
        'cors' => [
            'enabled' => true,
            'allowed_origins' => $_ENV['CORS_ALLOWED_ORIGINS'] ? explode(',', $_ENV['CORS_ALLOWED_ORIGINS']) : ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Correlation-ID'],
            'exposed_headers' => ['X-Correlation-ID'],
            'max_age' => 86400, // 24 hours
        ],
    ],

    // Monitoring and health checks
    'monitoring' => [
        'health_check' => [
            'enabled' => true,
            'endpoint' => '/health',
            'checks' => [
                'database' => true,
                'redis' => false, // Disabled by default since using file cache
                'email' => false,
                'storage' => true,
                'route_cache' => true,
                'translations' => true, // NEW
            ],
        ],
        'metrics' => [
            'enabled' => filter_var($_ENV['METRICS_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'endpoint' => '/metrics',
            'authentication_required' => true,
            'collect_route_stats' => true,
            'collect_db_stats' => true,
            'collect_translation_stats' => true, // NEW
        ],
        'profiling' => [
            'enabled' => filter_var($_ENV['PROFILING_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sample_rate' => 0.1, // 10% of requests
            'storage_path' => __DIR__ . '/../storage/profiling/',
        ],
    ],

    // Storage configuration
    'storage' => [
        'default' => 'local',
        'disks' => [
            'local' => [
                'driver' => 'local',
                'root' => __DIR__ . '/../storage/app/',
                'url' => $_ENV['APP_URL'] . '/storage',
                'visibility' => 'private',
            ],
            'public' => [
                'driver' => 'local',
                'root' => __DIR__ . '/../storage/app/public/',
                'url' => $_ENV['APP_URL'] . '/storage',
                'visibility' => 'public',
            ],
            'cache' => [
                'driver' => 'local',
                'root' => __DIR__ . '/../storage/cache/',
                'visibility' => 'private',
            ],
            'logs' => [
                'driver' => 'local',
                'root' => __DIR__ . '/../storage/logs/',
                'visibility' => 'private',
            ],
            'translations' => [
                'driver' => 'local',
                'root' => __DIR__ . '/../storage/translations/',
                'visibility' => 'private',
            ],
        ],
    ],

    // Templates configuration
    'templates' => [
        'path' => __DIR__ . '/../templates/',
        'cache_path' => __DIR__ . '/../storage/cache/templates/',
        'cache_enabled' => !filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'auto_reload' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'extensions' => ['.php'],
        'globals' => [
            'app_name' => 'Kickerscup',
            'app_version' => '2.0.0',
        ],
    ],

];