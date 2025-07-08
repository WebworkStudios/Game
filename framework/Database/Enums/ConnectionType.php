<?php
declare(strict_types=1);

namespace Framework\Database\Enums;

/**
 * Database Connection Types für Load Balancing
 */
enum ConnectionType: string
{
    case READ = 'read';
    case WRITE = 'write';

    public function isReadOnly(): bool
    {
        return $this === self::READ;
    }

    public function isWritable(): bool
    {
        return $this === self::WRITE;
    }
}