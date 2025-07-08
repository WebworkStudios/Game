<?php
declare(strict_types=1);

namespace Framework\Database\Enums;

/**
 * Order Directions
 */
enum OrderDirection: string
{
    case ASC = 'ASC';
    case DESC = 'DESC';
}