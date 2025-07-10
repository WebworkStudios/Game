<?php
declare(strict_types=1);

namespace Framework\Templating\Parser\Tokens;

enum TokenType: string
{
    case TEXT = 'text';
    case VARIABLE = 'variable';
    case BLOCK = 'block';
}