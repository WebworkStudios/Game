<?php
/**
 * Security Service Provider
 * Security-related services - CSRF, Password Hashing, Rate Limiting
 */
declare(strict_types=1);

namespace Framework\Providers;

use Framework\Core\Container;
use Framework\Core\ServiceProvider;
use Framework\Core\SessionManagerInterface;
use Framework\Security\CsrfProtection;
use Framework\Security\PasswordHasher;
use Framework\Security\RateLimiter;

class SecurityServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Password Hasher
        $container->singleton(PasswordHasher::class, function ($container) {
            $config = $container->get('config');
            $passwordConfig = $config['security']['password'];

            // Debug: Let's see what's in the config
            error_log("SecurityServiceProvider Debug - Password config: " . var_export($passwordConfig, true));
            error_log("SecurityServiceProvider Debug - Algorithm value: " . var_export($passwordConfig['algorithm'], true));
            error_log("SecurityServiceProvider Debug - Algorithm type: " . gettype($passwordConfig['algorithm']));
            error_log("SecurityServiceProvider Debug - PASSWORD_ARGON2ID constant: " . var_export(PASSWORD_ARGON2ID, true));

            return new PasswordHasher(
                $passwordConfig['algorithm'],
                $passwordConfig['options']
            );
        });

        // CSRF Protection
        $container->bind(CsrfProtection::class, function ($container) {
            $config = $container->get('config');
            $csrfConfig = $config['security']['csrf'];
            $session = $container->get(SessionManagerInterface::class);

            return new CsrfProtection(
                $session,
                $csrfConfig['token_name'],
                $csrfConfig['token_lifetime']
            );
        });

        // Rate Limiter
        $container->singleton(RateLimiter::class, function ($container) {
            return new RateLimiter($container->get('db'));
        });

        // Convenient aliases
        $container->alias('csrf', CsrfProtection::class);
        $container->alias('hasher', PasswordHasher::class);
        $container->alias('rateLimiter', RateLimiter::class);
    }

    public function boot(Container $container): void
    {
        $config = $container->get('config');
        $securityConfig = $config['security'];

        // Initialize CSRF protection if enabled
        if ($securityConfig['csrf']['enabled'] ?? true) {
            $container->get('logger')->debug('CSRF protection enabled', [
                'token_name' => $securityConfig['csrf']['token_name'],
                'token_lifetime' => $securityConfig['csrf']['token_lifetime']
            ]);
        }

        // Log password security settings
        $algorithm = $securityConfig['password']['algorithm'];
        $algorithmName = $this->getAlgorithmName($algorithm);

        $container->get('logger')->debug('Password security initialized', [
            'algorithm' => $algorithmName,
            'memory_cost' => $securityConfig['password']['options']['memory_cost'] ?? 'default',
            'time_cost' => $securityConfig['password']['options']['time_cost'] ?? 'default'
        ]);

        // Log rate limiting configuration
        $rateLimiting = $securityConfig['rate_limiting'] ?? [];
        $container->get('logger')->debug('Rate limiting configured', [
            'registration_limit' => $rateLimiting['registration']['max_attempts'] ?? 'unlimited',
            'login_limit' => $rateLimiting['login']['max_attempts'] ?? 'unlimited'
        ]);
    }

    /**
     * Get human-readable algorithm name
     */
    private function getAlgorithmName(string $algorithm): string
    {
        return match ($algorithm) {
            PASSWORD_ARGON2ID, 'argon2id' => 'Argon2ID',
            PASSWORD_ARGON2I, 'argon2i' => 'Argon2I',
            PASSWORD_BCRYPT, 'bcrypt' => 'BCrypt',
            default => 'Unknown'
        };
    }
}