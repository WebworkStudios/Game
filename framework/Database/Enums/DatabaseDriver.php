<?php
declare(strict_types=1);

namespace Framework\Database\Enums;


/**
 * Database Drivers
 */
enum DatabaseDriver: string
{
    case MYSQL = 'mysql';
    case POSTGRESQL = 'pgsql';
    case SQLITE = 'sqlite';

    public function getDefaultPort(): int
    {
        return match($this) {
            self::MYSQL => 3306,
            self::POSTGRESQL => 5432,
            self::SQLITE => 0,
        };
    }

    public function requiresHost(): bool
    {
        return match($this) {
            self::MYSQL, self::POSTGRESQL => true,
            self::SQLITE => false,
        };
    }
}