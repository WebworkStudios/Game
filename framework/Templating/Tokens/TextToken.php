<?php
declare(strict_types=1);

namespace Framework\Templating\Tokens;

/**
 * TextToken - Repräsentiert statischen Text im Template
 *
 * UPDATED: Nutzt TokenType Enum für type-safe Token-Handling
 */
class TextToken implements TemplateToken
{
    public function __construct(
        private readonly string $content
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self($data['content']);
    }

    /**
     * Type-safe Token-Type-Zugriff
     */
    public function getTokenType(): TokenType
    {
        return TokenType::TEXT;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->getTokenType()->value,
            'content' => $this->content
        ];
    }
}