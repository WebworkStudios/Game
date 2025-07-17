<?php
declare(strict_types=1);

namespace Framework\Templating\Tokens;

class TextToken implements TemplateToken
{
    public function __construct(
        private readonly string $content
    ) {}

    public function getType(): string
    {
        return 'text';
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function toArray(): array
    {
        return [
            'type' => 'text',
            'content' => $this->content
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self($data['content']);
    }
}