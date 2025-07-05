<?php

/**
 * Route Attribute
 * PHP 8.4 attribute for defining routes on Action classes
 *
 * File: framework/Core/Attributes/Route.php
 * Directory: /framework/Core/Attributes/
 */

declare(strict_types=1);

namespace Framework\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public string  $path,
        public string  $method = 'GET',
        public ?string $name = null,
        public array   $middleware = [],
        public ?int    $rateLimit = null
    )
    {
    }
}