<?php

namespace Framework\Templating\Rendering;

/**
 * TemplateVariableResolver - Löst Template-Variablen auf
 *
 * KORRIGIERT: Unterstützt Dot-Notation in Loop-Contexten
 */
class TemplateVariableResolver
{
    private array $data = [];
    private array $loopStack = [];

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
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

        return (bool)$value;
    }

    public function resolve(string $variable): mixed
    {
        // Parse dot notation
        $keys = explode('.', $variable);
        $rootKey = $keys[0];

        // Check loop variables first for the root key
        if (!empty($this->loopStack)) {
            $topLoop = end($this->loopStack);
            if (isset($topLoop[$rootKey])) {
                $value = $topLoop[$rootKey];

                // If we have nested keys, resolve them
                if (count($keys) > 1) {
                    return $this->resolveNestedKeys(array_slice($keys, 1), $value);
                }

                return $value;
            }
        }

        // Resolve from main data
        return $this->resolveNestedKeys($keys, $this->data);
    }

    /**
     * Resolves nested keys from a value
     */
    private function resolveNestedKeys(array $keys, mixed $value): mixed
    {
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
}