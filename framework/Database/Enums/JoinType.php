<?php
declare(strict_types=1);

namespace Framework\Database\Enums;

/**
 * Join Types
 */
enum JoinType: string
{
    case INNER = 'INNER JOIN';
    case LEFT = 'LEFT JOIN';
    case RIGHT = 'RIGHT JOIN';
    case FULL = 'FULL OUTER JOIN';
    case CROSS = 'CROSS JOIN';
}
