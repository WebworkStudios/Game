<?php
namespace Framework\Templating\Parsing;

use Framework\Templating\Tokens\{TemplateToken, TokenFactory, ControlToken, TextToken, VariableToken};
/**
 * ParsedTemplate - Value Object für geparste Templates
 */
class ParsedTemplate
{
    public function __construct(
        private readonly array $tokens,
        private readonly string $templatePath,
        private readonly ?string $parentTemplate = null,
        private readonly array $blocks = [],
        private readonly array $dependencies = []
    ) {}

    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    public function getParentTemplate(): ?string
    {
        return $this->parentTemplate;
    }

    public function hasParent(): bool
    {
        return $this->parentTemplate !== null;
    }

    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function toArray(): array
    {
        return [
            'tokens' => array_map(fn($token) => $token->toArray(), $this->tokens),
            'template_path' => $this->templatePath,
            'parent_template' => $this->parentTemplate,
            'blocks' => array_map(fn($block) => array_map(fn($token) => $token->toArray(), $block), $this->blocks),
            'dependencies' => $this->dependencies
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            tokens: array_map(fn($token) => TokenFactory::fromArray($token), $data['tokens'] ?? []),
            templatePath: $data['template_path'] ?? '',
            parentTemplate: $data['parent_template'] ?? null,
            blocks: array_map(fn($block) => array_map(fn($token) => TokenFactory::fromArray($token), $block), $data['blocks'] ?? []),
            dependencies: $data['dependencies'] ?? []
        );
    }
}