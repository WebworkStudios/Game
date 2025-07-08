<?php
declare(strict_types=1);

namespace Framework\Database\Enums;

/**
 * Query Types für interne Klassifizierung
 */
enum QueryType: string
{
    case SELECT = 'select';
    case INSERT = 'insert';
    case UPDATE = 'update';
    case DELETE = 'delete';

    public function isReadOnly(): bool
    {
        return $this === self::SELECT;
    }

    public function requiresConnection(): ConnectionType
    {
        return $this->isReadOnly() ? ConnectionType::READ : ConnectionType::WRITE;
    }
}