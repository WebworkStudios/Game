<?php
namespace Framework\Templating\Rendering;

use Framework\Templating\Tokens\{TemplateToken, TextToken, VariableToken, ControlToken};
use Framework\Templating\Parsing\{ParsedTemplate, TemplateParser, TemplatePathResolver};
use Framework\Templating\FilterManager;

/**
 * TemplateVariableResolver - Löst Template-Variablen auf
 */
class TemplateVariableResolver
{
    private array $data = [];
    private array $loopStack = [];

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function resolve(string $variable): mixed
    {
        // Check loop variables first
        if (!empty($this->loopStack)) {
            $topLoop = end($this->loopStack);
            if (isset($topLoop[$variable])) {
                return $topLoop[$variable];
            }
        }

        // Resolve dot notation: user.profile.name
        $keys = explode('.', $variable);
        $value = $this->data;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } elseif (is_object($value) && property_exists($value, $key)) {
                $value = $value->$key;
            } else {
                return null;
            }
        }

        return $value;
    }

    public function pushLoopContext(string $variable, mixed $value): void
    {
        $this->loopStack[] = [$variable => $value];
    }

    public function popLoopContext(): void
    {
        array_pop($this->loopStack);
    }

    public function evaluateCondition(string $condition): bool
    {
        // Simple condition evaluation
        $value = $this->resolve($condition);

        if (is_array($value)) {
            return !empty($value);
        }

        return (bool) $value;
    }
}