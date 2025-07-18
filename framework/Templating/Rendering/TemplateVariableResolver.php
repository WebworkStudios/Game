<?php

namespace Framework\Templating\Rendering;

/**
 * TemplateVariableResolver - Erweitert für Key-Value For-Loop Support
 *
 * KORRIGIERT: Unterstützt mehrere Loop-Variablen gleichzeitig
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
     * ERWEITERT: Unterstützt das Hinzufügen einzelner Variablen zum Loop-Context
     */
    public function pushLoopContext(string $variable, mixed $value): void
    {
        // KORRIGIERT: Füge Variable zu oberstem Loop-Context hinzu oder erstelle neuen
        if (empty($this->loopStack)) {
            $this->loopStack[] = [$variable => $value];
        } else {
            // Füge Variable zum obersten Context hinzu
            $topIndex = count($this->loopStack) - 1;
            $this->loopStack[$topIndex][$variable] = $value;
        }

        if ($_ENV['APP_DEBUG'] ?? false) {
            error_log("Pushed loop context: '{$variable}' => " . json_encode($value));
            error_log("Current loop stack depth: " . count($this->loopStack));
            error_log("Top context variables: " . json_encode(array_keys($this->loopStack[count($this->loopStack) - 1] ?? [])));
        }
    }

    /**
     * ERWEITERT: Intelligent pop - entfernt die zuletzt hinzugefügte Variable
     */
    public function popLoopContext(): void
    {
        if (empty($this->loopStack)) {
            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("Warning: Attempted to pop from empty loop stack");
            }
            return;
        }

        $topIndex = count($this->loopStack) - 1;
        $topContext = $this->loopStack[$topIndex];

        if (count($topContext) > 1) {
            // Entferne die zuletzt hinzugefügte Variable
            $keys = array_keys($topContext);
            $lastKey = end($keys);
            unset($this->loopStack[$topIndex][$lastKey]);

            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("Popped variable '{$lastKey}' from loop context");
                error_log("Remaining variables in top context: " . json_encode(array_keys($this->loopStack[$topIndex])));
            }
        } else {
            // Entferne den ganzen Context wenn nur noch eine Variable übrig ist
            array_pop($this->loopStack);

            if ($_ENV['APP_DEBUG'] ?? false) {
                error_log("Popped entire loop context");
                error_log("Remaining loop stack depth: " . count($this->loopStack));
            }
        }
    }

    /**
     * ALTERNATIVE: Push kompletten Loop-Context (für Kompatibilität)
     */
    public function pushCompleteLoopContext(array $variables): void
    {
        $this->loopStack[] = $variables;

        if ($_ENV['APP_DEBUG'] ?? false) {
            error_log("Pushed complete loop context: " . json_encode(array_keys($variables)));
        }
    }

    /**
     * ALTERNATIVE: Pop kompletten Loop-Context (für Kompatibilität)
     */
    public function popCompleteLoopContext(): void
    {
        $popped = array_pop($this->loopStack);

        if ($_ENV['APP_DEBUG'] ?? false) {
            error_log("Popped complete loop context: " . json_encode(array_keys($popped ?? [])));
        }
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

    /**
     * ERWEITERT: Verbesserte Variable Resolution mit Loop-Context-Priorität
     */
    public function resolve(string $variable): mixed
    {
        if ($_ENV['APP_DEBUG'] ?? false) {
            error_log("Resolving variable: '{$variable}'");
            error_log("Current loop stack depth: " . count($this->loopStack));
        }

        // Parse dot notation
        $keys = explode('.', $variable);
        $rootKey = $keys[0];

        // KORRIGIERT: Check loop variables first (in reverse order - neueste zuerst)
        if (!empty($this->loopStack)) {
            // Durchsuche Loop-Stack von oben nach unten
            for ($i = count($this->loopStack) - 1; $i >= 0; $i--) {
                $loopContext = $this->loopStack[$i];

                if (isset($loopContext[$rootKey])) {
                    $value = $loopContext[$rootKey];

                    if ($_ENV['APP_DEBUG'] ?? false) {
                        error_log("Found '{$rootKey}' in loop context {$i}: " . json_encode($value));
                    }

                    // If we have nested keys, resolve them
                    if (count($keys) > 1) {
                        return $this->resolveNestedKeys(array_slice($keys, 1), $value);
                    }

                    return $value;
                }
            }
        }

        // Resolve from main data
        $result = $this->resolveNestedKeys($keys, $this->data);

        if ($_ENV['APP_DEBUG'] ?? false) {
            error_log("Resolved '{$variable}' from main data: " . json_encode($result));
        }

        return $result;
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
                if ($_ENV['APP_DEBUG'] ?? false) {
                    error_log("Key '{$key}' not found in " . gettype($value));
                }
                return null;
            }
        }

        return $value;
    }

    /**
     * DEBUG: Get current loop stack for debugging
     */
    public function getLoopStack(): array
    {
        return $this->loopStack;
    }

    /**
     * DEBUG: Clear all loop contexts (for testing)
     */
    public function clearLoopStack(): void
    {
        $this->loopStack = [];
    }
}