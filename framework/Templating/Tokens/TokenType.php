<?php
declare(strict_types=1);

namespace Framework\Templating\Tokens;

/**
 * TokenType - Type-safe Enum für Template-Token-Typen
 *
 * Ersetzt Magic Strings durch type-safe Enum-Werte.
 * Bietet bessere IDE-Unterstützung und Compile-Time-Sicherheit.
 */
enum TokenType: string
{
    case TEXT = 'text';
    case VARIABLE = 'variable';
    case CONTROL = 'control';

    /**
     * Gibt alle verfügbaren Token-Types als Array zurück
     */
    public static function values(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }

    /**
     * Prüft ob ein String-Wert ein gültiger TokenType ist
     */
    public static function isValid(string $type): bool
    {
        return self::tryFrom($type) !== null;
    }

    /**
     * Erstellt TokenType aus String mit Fallback
     */
    public static function fromString(string $type): self
    {
        return self::tryFrom($type) ?? throw new \InvalidArgumentException(
            "Invalid token type: '{$type}'. Valid types: " . implode(', ', self::values())
        );
    }

    /**
     * Gibt den Token-Type als String zurück (für Kompatibilität)
     */
    public function toString(): string
    {
        return $this->value;
    }
}