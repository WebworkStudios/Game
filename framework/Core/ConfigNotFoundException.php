<?php
declare(strict_types=1);

namespace Framework\Core;

/**
 * ConfigNotFoundException - Custom Exception
 */
class ConfigNotFoundException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

