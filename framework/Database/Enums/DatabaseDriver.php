<?php
declare(strict_types=1);

namespace Framework\Database\Enums;


/**
 * Database Drivers
 */
enum DatabaseDriver: string
{
    case MYSQL = 'mysql';
    case POSTGRESQL = 'postgresql'; // Geändert von 'pgsql' zu 'postgresql'
    case PGSQL = 'pgsql'; // Zusätzlicher Alias für Kompatibilität
    case SQLITE = 'sqlite';

    public function getDefaultPort(): int
    {
        return match ($this) {
            self::MYSQL => 3306,
            self::POSTGRESQL, self::PGSQL => 5432,
            self::SQLITE => 0,
        };
    }

    public function requiresHost(): bool
    {
        return match ($this) {
            self::MYSQL, self::POSTGRESQL, self::PGSQL => true,
            self::SQLITE => false,
        };
    }

    /**
     * Holt PDO-Driver-Namen
     */
    public function getPdoDriver(): string
    {
        return match ($this) {
            self::MYSQL => 'mysql',
            self::POSTGRESQL, self::PGSQL => 'pgsql',
            self::SQLITE => 'sqlite',
        };
    }
}