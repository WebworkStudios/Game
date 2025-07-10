<?php
declare(strict_types=1);

namespace Framework\Core;

use RuntimeException;

/**
 * Service Registry für globalen Zugriff auf Services
 */
class ServiceRegistry
{
    private static ?ServiceContainer $container = null;

    /**
     * Registriert Container
     */
    public static function setContainer(ServiceContainer $container): void
    {
        self::$container = $container;
    }

    /**
     * Holt Service aus Container
     */
    public static function get(string $abstract): object
    {
        if (self::$container === null) {
            throw new RuntimeException('Service container not registered');
        }

        return self::$container->get($abstract);
    }

    /**
     * Prüft ob Service verfügbar ist
     */
    public static function has(string $abstract): bool
    {
        return self::$container?->has($abstract) ?? false;
    }
}