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
                    'database' => $_ENV['DB_DATABASE'] ?? 'kickerscup',
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
        ]
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
        'session' => [
            'name' => 'fm_session',
            'lifetime' => 0,
            'path' => '/',
            'domain' => $_ENV['SESSION_DOMAIN'] ?? '',
            'secure' => filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'httponly' => true,
            'samesite' => 'Strict',
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
    ],

    // Caching configuration
    'cache' => [
        'default' => 'redis',
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
        ],
    ],

    // Logging configuration
    'logging' => [
        'default' => 'daily',
        'channels' => [
            'daily' => [
                'driver' => 'daily',
                'path' => __DIR__ . '/../logs/application.log',
                'level' => $_ENV['LOG_LEVEL'] ?? 'info',
                'days' => (int)($_ENV['LOG_RETENTION_DAYS'] ?? 14),
                'permission' => 0644,
            ],
            'single' => [
                'driver' => 'single',
                'path' => __DIR__ . '/../logs/application.log',
                'level' => $_ENV['LOG_LEVEL'] ?? 'info',
            ],
            'syslog' => [
                'driver' => 'syslog',
                'level' => $_ENV['LOG_LEVEL'] ?? 'info',
            ],
            'error' => [
                'driver' => 'daily',
                'path' => __DIR__ . '/../logs/error.log',
                'level' => 'error',
                'days' => 30,
            ],
            'security' => [
                'driver' => 'daily',
                'path' => __DIR__ . '/../logs/security.log',
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

    // Localization configuration
    'localization' => [
        'storage' => 'database', // 'files' or 'database'
        'supported_locales' => ['de', 'en', 'es', 'fr'],
        'fallback_locale' => 'en',
        'cache_enabled' => true,
        'cache_ttl' => 3600, // 1 hour
    ],

    // Performance configuration
    'performance' => [
        'lazy_loading' => [
            'enabled' => true,
            'batch_size' => 50,
        ],
        'database' => [
            'query_cache_enabled' => true,
            'slow_query_threshold' => 2.0, // seconds
            'batch_insert_size' => 1000,
        ],
        'response' => [
            'compression_enabled' => true,
            'compression_level' => 6,
            'etag_enabled' => true,
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
        ],
        'cache_headers' => [
            'css' => 'max-age=31536000', // 1 year
            'js' => 'max-age=31536000', // 1 year
            'images' => 'max-age=2592000', // 30 days
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
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
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
                'redis' => true,
                'email' => false,
            ],
        ],
        'metrics' => [
            'enabled' => filter_var($_ENV['METRICS_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'endpoint' => '/metrics',
            'authentication_required' => true,
        ],
    ],
];