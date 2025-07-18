<?php

namespace Framework\Templating\Rendering;

/**
 * TemplateVariableResolver - Erweitert für Key-Value For-Loop Support
 *
 * BEREINIGT: Debug-Logging komplett entfernt
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

    /**
     * Unterstützt das Hinzufügen einzelner Variablen zum Loop-Context
     */
    public function pushLoopContext(string $variable, mixed $value): void
    {
        if (empty($this->loopStack)) {
            $this->loopStack[] = [$variable => $value];
        } else {
            // Füge Variable zum obersten Context hinzu
            $topIndex = count($this->loopStack) - 1;
            $this->loopStack[$topIndex][$variable] = $value;
        }
    }

    /**
     * Intelligent pop - entfernt die zuletzt hinzugefügte Variable
     */
    public function popLoopContext(): void
    {
        if (empty($this->loopStack)) {
            return;
        }

        $topIndex = count($this->loopStack) - 1;
        $topContext = $this->loopStack[$topIndex];

        if (count($topContext) > 1) {
            // Entferne die zuletzt hinzugefügte Variable
            $keys = array_keys($topContext);
            $lastKey = end($keys);
            unset($this->loopStack[$topIndex][$lastKey]);
        } else {
            // Entferne den ganzen Context wenn nur noch eine Variable übrig ist
            array_pop($this->loopStack);
        }
    }

    /**
     * Push kompletten Loop-Context (für Kompatibilität)
     */
    public function pushCompleteLoopContext(array $variables): void
    {
        $this->loopStack[] = $variables;
    }

    /**
     * Pop kompletten Loop-Context (für Kompatibilität)
     */
    public function popCompleteLoopContext(): void
    {
        array_pop($this->loopStack);
    }

    public function evaluateCondition(string $condition): bool
    {
        $value = $this->resolve($condition);

        if (is_array($value)) {
            return !empty($value);
        }

        return (bool)$value;
    }

    /**
     * Verbesserte Variable Resolution mit Loop-Context-Priorität
     */
    public function resolve(string $variable): mixed
    {
        // Parse dot notation
        $keys = explode('.', $variable);
        $rootKey = $keys[0];

        // Check loop variables first (in reverse order - neueste zuerst)
        if (!empty($this->loopStack)) {
            // Durchsuche Loop-Stack von oben nach unten
            for ($i = count($this->loopStack) - 1; $i >= 0; $i--) {
                $loopContext = $this->loopStack[$i];

                if (isset($loopContext[$rootKey])) {
                    $value = $loopContext[$rootKey];

                    // If we have nested keys, resolve them
                    if (count($keys) > 1) {
                        return $this->resolveNestedKeys(array_slice($keys, 1), $value);
                    }

                    return $value;
                }
            }
        }

        // Resolve from main data
        return $this->resolveNestedKeys($keys, $this->data);
    }

    /**
     * Resolve nested object/array access
     */
    private function resolveNestedKeys(array $keys, mixed $data): mixed
    {
        $current = $data;

        foreach ($keys as $key) {
            if (is_array($current) && isset($current[$key])) {
                $current = $current[$key];
            } elseif (is_object($current) && property_exists($current, $key)) {
                $current = $current->$key;
            } elseif (is_object($current) && method_exists($current, $key)) {
                $current = $current->$key();
            } else {
                return null;
            }
        }

        return $current;
    }
}