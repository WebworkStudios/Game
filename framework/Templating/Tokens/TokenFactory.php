<?php
declare(strict_types=1);

namespace Framework\Templating\Tokens;

/**
 * TokenFactory - Factory für Token-Erstellung
 *
 * UPDATED: Nutzt TokenType Enum für type-safe Token-Erstellung
 */
class TokenFactory
{
    /**
     * Erstellt Token aus Array-Daten (type-safe)
     */
    public static function fromArray(array $data): TemplateToken
    {
        $tokenType = TokenType::fromString($data['type'] ?? '');

        return match ($tokenType) {
            TokenType::TEXT => TextToken::fromArray($data),
            TokenType::VARIABLE => VariableToken::fromArray($data),
            TokenType::CONTROL => ControlToken::fromArray($data),
        };
    }

    /**
     * Erstellt Text-Token
     */
    public static function createText(string $content): TextToken
    {
        return new TextToken($content);
    }

    /**
     * Erstellt Variable-Token
     */
    public static function createVariable(string $expression): VariableToken
    {
        return VariableToken::parse($expression);
    }

    /**
     * Erstellt Control-Token
     */
    public static function createControl(string $expression): ControlToken
    {
        return ControlToken::parse($expression);
    }

    /**
     * Type-safe Token-Erstellung basierend auf Enum
     */
    public static function createByType(TokenType $type, array $data): TemplateToken
    {
        return match ($type) {
            TokenType::TEXT => new TextToken($data['content'] ?? ''),
            TokenType::VARIABLE => new VariableToken(
                $data['variable'] ?? '',
                $data['filters'] ?? [],
                $data['should_escape'] ?? true
            ),
            TokenType::CONTROL => new ControlToken(
                $data['command'] ?? '',
                $data['expression'] ?? '',
                $data['children'] ?? [],
                $data['else_children'] ?? [],
                $data['metadata'] ?? []
            ),
        };
    }

    /**
     * Prüft ob Token-Type unterstützt wird
     */
    public static function supportsType(TokenType $type): bool
    {
        return match ($type) {
            TokenType::TEXT,
            TokenType::VARIABLE,
            TokenType::CONTROL => true,
        };
    }

    /**
     * Gibt alle unterstützten Token-Types zurück
     */
    public static function getSupportedTypes(): array
    {
        return TokenType::cases();
    }
}