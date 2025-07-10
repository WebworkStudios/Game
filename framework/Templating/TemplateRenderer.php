<?php

declare(strict_types=1);

namespace Framework\Templating;

class TemplateRenderer
{
    public array $data; // Public für For-Loop Zugriff

    public function __construct(
        private readonly TemplateEngine $engine,
        array $data
    ) {
        $this->data = $data;
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
        return $this->engine->render($template, $mergedData);
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
}