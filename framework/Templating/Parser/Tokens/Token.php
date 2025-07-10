<?php
declare(strict_types=1);

namespace Framework\Templating\Parser\Tokens;

readonly class Token
{
    public function __construct(
        private TokenType $type,
        private string $value,
        private int $position,
        private ?int $endPosition = null
    ) {}

    public function getType(): TokenType
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getEndPosition(): int
    {
        return $this->endPosition ?? ($this->position + strlen($this->value));
    }
}