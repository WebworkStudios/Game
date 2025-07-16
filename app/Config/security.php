<?php
declare(strict_types=1);

// app/Config/security.php

return [
    // =============================================================================
    // Session Configuration (Pure Data Layer)
    // =============================================================================
    'session' => [
        'lifetime' => 7200, // 2 hours
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
        'gc_maxlifetime' => 7200,
        'gc_probability' => 1,
        'gc_divisor' => 100,
        'save_path' => 'storage/sessions',
    ],

    // =============================================================================
    // Session Security Configuration (Security Layer)
    // =============================================================================
    'session_security' => [
        // Regeneration Settings
        'min_regeneration_interval' => 300, // 5 minutes
        'session_lifetime' => 7200, // 2 hours (should match session.lifetime)

        // Fingerprinting
        'enable_fingerprinting' => true,
        'fingerprint_components' => [
            'user_agent' => true,
            'accept_language' => true,
            'ip_address' => false, // Disabled for mobile users
        ],

        // Auto-regeneration triggers
        'auto_regenerate_on_ip_change' => false,
        'auto_regenerate_on_user_agent_change' => true,

        // Login Protection
        'max_login_attempts' => 5,
        'login_attempt_window' => 900, // 15 minutes

        // Security Violations
        'max_security_violations' => 3,
        'violation_lockout_time' => 1800, // 30 minutes
    ],

    // =============================================================================
    // Session Middleware Configuration (Orchestration Layer)
    // =============================================================================
    'session_middleware' => [
        'auto_start' => true,
        'security_validation' => true,

        // Paths that don't need sessions
        'exempt_paths' => [
            '/api/health',
            '/ping',
            '/favicon.ico',
            '/robots.txt',
            '/sitemap.xml',
        ],

        // Paths that require sessions
        'require_session_paths' => [
            '/admin',
            '/dashboard',
            '/team',
            '/player',
            '/transfer',
            '/match',
            '/settings',
        ],
    ],

    // =============================================================================
    // CSRF Configuration (unchanged)
    // =============================================================================
    'csrf' => [
        'token_name' => '_token',
        'header_name' => 'X-CSRF-TOKEN',
        'expire_time' => 3600, // 1 hour
    ],
];