<?php
declare(strict_types=1);

namespace Framework\Templating\Tokens;


/**
 * TokenFactory - Factory für Token-Erstellung
 */
class TokenFactory
{
    public static function fromArray(array $data): TemplateToken
    {
        return match ($data['type']) {
            'text' => TextToken::fromArray($data),
            'variable' => VariableToken::fromArray($data),
            'control' => ControlToken::fromArray($data),
            default => throw new \RuntimeException("Unknown token type: {$data['type']}")
        };
    }

    public static function createText(string $content): TextToken
    {
        return new TextToken($content);
    }

    public static function createVariable(string $expression): VariableToken
    {
        return VariableToken::parse($expression);
    }

    public static function createControl(string $expression): ControlToken
    {
        return ControlToken::parse($expression);
    }
}
