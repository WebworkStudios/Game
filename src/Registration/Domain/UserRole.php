<?php
declare(strict_types=1);

namespace Registration\Domain;

enum UserRole: string
{
    case USER = 'user';
    case ADMIN = 'admin';
}