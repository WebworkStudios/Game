<?php

declare(strict_types=1);

namespace Framework\Templating;

class TemplateRenderer
{
    public array $data; // Public für For-Loop Zugriff
    private array $blocks = []; // Add blocks storage

    public function __construct(
        private readonly TemplateEngine $engine,
        array                           $data
    )
    {
        $this->data = $data;
    }

    /**
     * Set blocks for template inheritance
     */
    public function setBlocks(array $blocks): void
    {
        $this->blocks = array_merge($this->blocks, $blocks);
    }

    /**
     * Check if block exists
     */
    public function hasBlock(string $name): bool
    {
        return isset($this->blocks[$name]);
    }

    /**
     * Render block
     */
    public function renderBlock(string $name): string
    {
        if (isset($this->blocks[$name])) {
            return $this->blocks[$name]();
        }
        return '';
    }

    /**
     * Escape output for HTML
     */
    public function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get variable from data with dot notation support
     */
    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Set variable in data (for loops)
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Check if variable exists
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Include another template
     */
    public function include(string $template, array $data = []): string
    {
        $mergedData = array_merge($this->data, $data);

        // Create new renderer with same blocks but merged data
        $childRenderer = new TemplateRenderer($this->engine, $mergedData);
        $childRenderer->setBlocks($this->blocks); // Wichtig: Blocks weitergeben!

        return $this->engine->renderWithRenderer($template, $childRenderer);
    }

    /**
     * Raw output (unescaped)
     */
    public function raw(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string)$value;
    }

    /**
     * Apply filter to value
     */
    public function applyFilter(string $filter, mixed $value): mixed
    {
        return match ($filter) {
            'length' => $this->filterLength($value),
            'upper' => $this->filterUpper($value),
            'lower' => $this->filterLower($value),
            'capitalize' => $this->filterCapitalize($value),
            'date' => $this->filterDate($value),
            'number_format' => $this->filterNumberFormat($value),
            'default' => $this->filterDefault($value),
            default => throw new \RuntimeException("Unknown filter: {$filter}")
        };
    }

    /**
     * Length filter - get count of array or string length
     */
    private function filterLength(mixed $value): int
    {
        if (is_array($value) || $value instanceof \Countable) {
            return count($value);
        }

        if (is_string($value)) {
            return mb_strlen($value);
        }

        return 0;
    }

    /**
     * Upper filter - convert to uppercase
     */
    private function filterUpper(mixed $value): string
    {
        return mb_strtoupper((string)$value);
    }

    /**
     * Lower filter - convert to lowercase
     */
    private function filterLower(mixed $value): string
    {
        return mb_strtolower((string)$value);
    }

    /**
     * Capitalize filter - capitalize first letter
     */
    private function filterCapitalize(mixed $value): string
    {
        return mb_convert_case((string)$value, MB_CASE_TITLE);
    }

    /**
     * Date filter - format date
     */
    private function filterDate(mixed $value): string
    {
        if (is_string($value)) {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        return (string)$value;
    }

    /**
     * Number format filter
     */
    private function filterNumberFormat(mixed $value): string
    {
        if (is_numeric($value)) {
            return number_format((float)$value, 0, '.', ',');
        }

        return (string)$value;
    }

    /**
     * Default filter - return default value if empty
     */
    private function filterDefault(mixed $value): mixed
    {
        return empty($value) ? 'N/A' : $value;
    }

    /**
     * Include template with variable mapping
     */
    public function includeWith(string $template, string $variable, mixed $data): string
    {
        $templateData = array_merge($this->data, [$variable => $data]);
        return $this->engine->render($template, $templateData);
    }
}