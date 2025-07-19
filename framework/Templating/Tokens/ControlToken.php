<?php
declare(strict_types=1);

namespace Framework\Templating\Tokens;

/**
 * ControlToken - Repräsentiert {% control %} Ausdrücke
 *
 * UPDATED: Nutzt TokenType Enum für type-safe Token-Handling
 */
class ControlToken implements TemplateToken
{
    public function __construct(
        private readonly string $command,
        private readonly string $expression = '',
        private readonly array  $children = [],
        private readonly array  $elseChildren = [],
        private readonly array  $metadata = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['command'],
            $data['expression'] ?? '',
            array_map(fn($child) => TokenFactory::fromArray($child), $data['children'] ?? []),
            array_map(fn($child) => TokenFactory::fromArray($child), $data['else_children'] ?? []),
            $data['metadata'] ?? []
        );
    }

    public static function parse(string $expression): self
    {
        $parts = explode(' ', $expression, 2);
        $command = $parts[0];
        $args = $parts[1] ?? '';

        $metadata = [];

        // Parse spezifische Commands
        switch ($command) {
            case 'if':
                $metadata['condition'] = $args;
                break;
            case 'for':
                if (preg_match('/^(.+?)\s+in\s+(.+)$/', $args, $matches)) {
                    $metadata['variable'] = trim($matches[1]);
                    $metadata['iterable'] = trim($matches[2]);
                }
                break;
            case 'block':
                $metadata['block_name'] = trim($args);
                break;
            case 'extends':
                $metadata['parent_template'] = trim($args, '\'"');
                break;
            case 'include':
                $metadata['template'] = trim($args, '\'"');
                break;
        }

        return new self($command, $args, [], [], $metadata);
    }

    /**
     * Type-safe Token-Type-Zugriff
     */
    public function getTokenType(): TokenType
    {
        return TokenType::CONTROL;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function getElseChildren(): array
    {
        return $this->elseChildren;
    }

    public function hasElse(): bool
    {
        return !empty($this->elseChildren);
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function withChildren(array $children): self
    {
        return new self($this->command, $this->expression, $children, $this->elseChildren, $this->metadata);
    }

    public function withElseChildren(array $elseChildren): self
    {
        return new self($this->command, $this->expression, $this->children, $elseChildren, $this->metadata);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->getTokenType()->value,
            'command' => $this->command,
            'expression' => $this->expression,
            'children' => array_map(fn($child) => $child->toArray(), $this->children),
            'else_children' => array_map(fn($child) => $child->toArray(), $this->elseChildren),
            'metadata' => $this->metadata
        ];
    }
}