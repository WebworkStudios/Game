<?php
declare(strict_types=1);

namespace Framework\Templating;

use Countable;
use RuntimeException;
use Throwable;

class TemplateRenderer
{
    public array $data; // Public für For-Loop Zugriff
    private array $blocks = []; // Block storage
    private array $parentBlocks = []; // Parent blocks for inheritance

    public function __construct(
        private readonly TemplateEngine $engine,
        array                           $data
    )
    {
        $this->data = $data;
    }

    /**
     * Set blocks for template inheritance
     * ← VERBESSERT: Merge mit Parent-Blocks
     */
    public function setBlocks(array $blocks): void
    {
        // Child blocks überschreiben Parent blocks
        $this->blocks = array_merge($this->parentBlocks, $blocks);
    }

    /**
     * Render block
     * ← VERBESSERT: Fehlerbehandlung und Parent-Block-Support
     */
    public function renderBlock(string $name): string
    {
        if (!$this->hasBlock($name)) {
            return '';
        }

        try {
            $blockFunction = $this->blocks[$name];
            return (string)$blockFunction();
        } catch (Throwable $e) {
            throw new RuntimeException("Error rendering block '{$name}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if block exists
     * ← VERBESSERT: Bessere Block-Existenz-Prüfung
     */
    public function hasBlock(string $name): bool
    {
        return isset($this->blocks[$name]) && is_callable($this->blocks[$name]);
    }

    /**
     * ← NEU: Render parent block (für block inheritance mit parent() calls)
     */
    public function renderParentBlock(string $name): string
    {
        if (isset($this->parentBlocks[$name]) && is_callable($this->parentBlocks[$name])) {
            try {
                $parentFunction = $this->parentBlocks[$name];
                return (string)$parentFunction();
            } catch (Throwable $e) {
                throw new RuntimeException("Error rendering parent block '{$name}': " . $e->getMessage(), 0, $e);
            }
        }

        return '';
    }

    /**
     * Get variable from data with dot notation support
     */
    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Translate helper function (callable from templates)
     */
    public function t(string $key, array $parameters = []): string
    {
        $translator = $this->getTranslator();

        if (!$translator) {
            return $key; // Fallback if no translator available
        }

        return $translator->translate($key, $parameters);
    }

    /**
     * Translate plural helper function (callable from templates)
     */
    public function tPlural(string $key, int $count, array $parameters = []): string
    {
        $translator = $this->getTranslator();

        if (!$translator) {
            return $key; // Fallback if no translator available
        }

        return $translator->translatePlural($key, $count, $parameters);
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
     * ← VERBESSERT: Block-Vererbung bei Includes
     */
    public function include(string $template, array $data = []): string
    {
        $mergedData = array_merge($this->data, $data);

        // Create new renderer with merged data
        $childRenderer = new TemplateRenderer($this->engine, $mergedData);

        // ← WICHTIG: Parent-Blocks an Child-Renderer weitergeben
        $childRenderer->setParentBlocks($this->blocks);

        return $this->engine->renderWithRenderer($template, $childRenderer);
    }

    /**
     * ← NEU: Set parent blocks (for nested inheritance)
     */
    public function setParentBlocks(array $parentBlocks): void
    {
        $this->parentBlocks = $parentBlocks;
        // Re-merge mit aktuellen blocks
        $this->blocks = array_merge($this->parentBlocks, $this->blocks);
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
            't' => $this->filterTranslate($value, $params),
            't_plural' => $this->filterTranslatePlural($value, $params),
            default => throw new RuntimeException("Unknown filter: {$filter}")
        };
    }

    /**
     * Length filter - get count of array or string length
     */
    private function filterLength(mixed $value): int
    {
        if (is_array($value) || $value instanceof Countable) {
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
     * Translation filter - translate using key
     */
    private function filterTranslate(mixed $value, array $params = []): string
    {
        $translator = $this->getTranslator();

        if (!$translator) {
            return (string)$value; // Fallback if no translator available
        }

        $key = (string)$value;
        $parameters = $params[0] ?? [];

        if (!is_array($parameters)) {
            $parameters = [];
        }

        return $translator->translate($key, $parameters);
    }

    /**
     * Translation plural filter - translate with pluralization
     */
    private function filterTranslatePlural(mixed $value, array $params = []): string
    {
        $translator = $this->getTranslator();

        if (!$translator) {
            return (string)$value; // Fallback if no translator available
        }

        $key = (string)$value;
        $count = (int)($params[0] ?? 1);
        $parameters = $params[1] ?? [];

        if (!is_array($parameters)) {
            $parameters = [];
        }

        return $translator->translatePlural($key, $count, $parameters);
    }

    /**
     * Get translator instance from engine
     */
    private function getTranslator(): ?\Framework\Localization\Translator
    {
        try {
            return \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Include template with variable mapping
     * ← VERBESSERT: Block-Vererbung auch hier
     */
    public function includeWith(string $template, string $variable, mixed $data): string
    {
        $templateData = array_merge($this->data, [$variable => $data]);

        $childRenderer = new TemplateRenderer($this->engine, $templateData);
        $childRenderer->setParentBlocks($this->blocks);

        return $this->engine->renderWithRenderer($template, $childRenderer);
    }
}