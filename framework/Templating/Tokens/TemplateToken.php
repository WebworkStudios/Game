<?php
declare(strict_types=1);

namespace Framework\Templating\Tokens;

/**
 * TemplateToken - Basis-Interface für alle Token-Typen
 *
 * UPDATED: Unterstützt nun TokenType Enum für type-safe Token-Handling
 */
interface TemplateToken
{
    public static function fromArray(array $data): self;

    /**
     * Gibt den Token-Type als Enum zurück
     */
    public function getTokenType(): TokenType;

    public function toArray(): array;
}