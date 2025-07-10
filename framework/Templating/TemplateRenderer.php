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
     * Set blocks for template inheritance
     */
    public function setBlocks(array $blocks): void
    {
        $this->blocks = array_merge($this->blocks, $blocks);
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
    public function applyFilter(string $filter, mixed $value, array $params = []): mixed
    {
        return match ($filter) {
            'length' => $this->filterLength($value),
            'upper' => $this->filterUpper($value),
            'lower' => $this->filterLower($value),
            'capitalize' => $this->filterCapitalize($value),
            'date' => $this->filterDate($value, $params[0] ?? 'Y-m-d'),
            'number_format' => $this->filterNumberFormat($value, $params),
            'default' => $this->filterDefault($value, $params[0] ?? 'N/A'),
            'truncate' => $this->filterTruncate($value, (int)($params[0] ?? 50)),
            'escape' => $this->escape($value),
            'raw' => $this->raw($value),
            'json' => $this->filterJson($value),
            'slug' => $this->filterSlug($value),
            'currency' => $this->filterCurrency($value, $params[0] ?? '€', $params[1] ?? 'right'),
            'rating' => $this->filterRating($value, (int)($params[0] ?? 10)),
            'plural' => $this->filterPlural($value, $params[0] ?? '', $params[1] ?? 's'),
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
     * Enhanced date filter with format parameter
     */
    private function filterDate(mixed $value, string $format = 'Y-m-d'): string
    {
        if (is_string($value)) {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                $result = date($format, $timestamp);
                return $result;
            }
        }

        return (string)$value;
    }
    /**
     * Enhanced number format with parameters
     */
    private function filterNumberFormat(mixed $value, array $params = []): string
    {
        if (!is_numeric($value)) {
            return (string)$value;
        }

        $decimals = (int)($params[0] ?? 0);
        $decimalSep = $params[1] ?? '.';
        $thousandsSep = $params[2] ?? ',';

        return number_format((float)$value, $decimals, $decimalSep, $thousandsSep);
    }

    /**
     * Enhanced default filter with parameter
     */
    private function filterDefault(mixed $value, string $default = 'N/A'): mixed
    {
        return empty($value) ? $default : $value;
    }

    /**
     * Truncate filter - limit string length
     */
    private function filterTruncate(mixed $value, int $length): string
    {
        $string = (string)$value;
        return mb_strlen($string) > $length
            ? mb_substr($string, 0, $length) . '...'
            : $string;
    }

    /**
     * JSON filter - encode as JSON
     */
    private function filterJson(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Slug filter - create URL-friendly string
     */
    private function filterSlug(mixed $value): string
    {
        $string = mb_strtolower((string)$value);
        $string = preg_replace('/[^a-z0-9\-]/', '-', $string);
        return trim(preg_replace('/-+/', '-', $string), '-');
    }

    /**
     * Currency filter - format money values
     */
    private function filterCurrency(mixed $value, string $symbol = '€', string $position = 'right'): string
    {
        if (!is_numeric($value)) {
            return (string)$value;
        }

        $formatted = number_format((float)$value, 2, ',', '.');

        // Ensure symbol is properly decoded if it comes as Unicode escape
        $symbol = html_entity_decode($symbol, ENT_QUOTES, 'UTF-8');

        return $position === 'left'
            ? $symbol . ' ' . $formatted
            : $formatted . ' ' . $symbol;
    }

    /**
     * Rating filter - format rating with stars or numbers
     */
    private function filterRating(mixed $value, int $maxRating = 10): string
    {
        if (!is_numeric($value)) {
            return (string)$value;
        }

        $rating = (float)$value;
        $percentage = min(100, ($rating / $maxRating) * 100); // Cap at 100%

        return sprintf(
            '<span class="rating" data-rating="%.1f" data-percentage="%.0f">%.1f/%d</span>',
            $rating,
            $percentage,
            $rating,
            $maxRating
        );
    }

    /**
     * Plural filter - handle singular/plural forms
     */
    private function filterPlural(mixed $count, string $singular, string $plural): string
    {
        $num = is_numeric($count) ? (int)$count : 0;

        if ($num === 1) {
            return $singular;
        }

        return $plural;
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