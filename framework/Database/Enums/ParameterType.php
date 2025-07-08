<?php
declare(strict_types=1);

namespace Framework\Database\Enums;

/**
 * PDO Parameter Types mit erweiterten Funktionen
 */
enum ParameterType: int
{
    case STRING = \PDO::PARAM_STR;
    case INTEGER = \PDO::PARAM_INT;
    case BOOLEAN = \PDO::PARAM_BOOL;
    case NULL = \PDO::PARAM_NULL;
    case LOB = \PDO::PARAM_LOB;

    /**
     * Automatische Typ-Erkennung basierend auf PHP-Wert
     */
    public static function fromValue(mixed $value): self
    {
        return match(true) {
            is_null($value) => self::NULL,
            is_bool($value) => self::BOOLEAN,
            is_int($value) => self::INTEGER,
            is_string($value) => self::STRING,
            default => self::STRING,
        };
    }
}