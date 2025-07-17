<?php
declare(strict_types=1);

namespace Framework\Templating\Tokens;

/**
 * VariableToken - Repräsentiert {{ variable }} Ausdrücke
 */
class VariableToken implements TemplateToken
{
    public function __construct(
        private readonly string $variable,
        private readonly array $filters = [],
        private readonly bool $shouldEscape = true
    ) {}

    public function getType(): string
    {
        return 'variable';
    }

    public function getVariable(): string
    {
        return $this->variable;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function shouldEscape(): bool
    {
        return $this->shouldEscape;
    }

    public function toArray(): array
    {
        return [
            'type' => 'variable',
            'variable' => $this->variable,
            'filters' => $this->filters,
            'should_escape' => $this->shouldEscape
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['variable'],
            $data['filters'] ?? [],
            $data['should_escape'] ?? true
        );
    }

    public static function parse(string $expression): self
    {
        // Parse {{ variable|filter:param }} syntax
        if (str_contains($expression, '|')) {
            [$variable, $filterChain] = explode('|', $expression, 2);
            $filters = self::parseFilters($filterChain);
            $shouldEscape = !self::hasRawFilter($filters);
        } else {
            $variable = $expression;
            $filters = [];
            $shouldEscape = true;
        }

        return new self(trim($variable), $filters, $shouldEscape);
    }

    private static function parseFilters(string $filterChain): array
    {
        $filters = [];
        $parts = explode('|', $filterChain);

        foreach ($parts as $filterExpr) {
            $filterExpr = trim($filterExpr);

            if (str_contains($filterExpr, ':')) {
                [$name, $params] = explode(':', $filterExpr, 2);
                $parameters = explode(':', $params);
            } else {
                $name = $filterExpr;
                $parameters = [];
            }

            $filters[] = [
                'name' => trim($name),
                'parameters' => array_map('trim', $parameters)
            ];
        }

        return $filters;
    }

    private static function hasRawFilter(array $filters): bool
    {
        foreach ($filters as $filter) {
            if (($filter['name'] ?? '') === 'raw') {
                return true;
            }
        }
        return false;
    }
}