<?php
declare(strict_types=1);

namespace Framework\Templating;

use Countable;
use RuntimeException;
use Throwable;

class TemplateRenderer
{
    public array $data;
    private array $blocks = [];
    private array $parentBlocks = [];

    public function __construct(
        private readonly TemplateEngine $engine,
        array                           $data = []
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
     * Apply filter to value - OPTIMIZED VERSION
     */
    public function applyFilter(string $filter, mixed $value, array $params = []): mixed
    {
        // Static filter map for O(1) lookup instead of match()
        static $filterMap = [
            'length' => 'filterLength',
            'upper' => 'filterUpper',
            'lower' => 'filterLower',
            'capitalize' => 'filterCapitalize',
            'date' => 'filterDate',
            'number_format' => 'filterNumberFormat',
            'default' => 'filterDefault',
            'truncate' => 'filterTruncate',
            'escape' => 'escape',
            'raw' => 'raw',
            'json' => 'filterJson',
            'slug' => 'filterSlug',
            'currency' => 'filterCurrency',
            'rating' => 'filterRating',
            'plural' => 'filterPlural',
            't' => 'filterTranslate',
            't_plural' => 'filterTranslatePlural',
        ];

        if (!isset($filterMap[$filter])) {
            throw new RuntimeException("Unknown filter: {$filter}");
        }

        $method = $filterMap[$filter];

        // Direct method call - faster than match()
        return match ($method) {
            'escape' => $this->escape($value),
            'raw' => $this->raw($value),
            'filterLength' => $this->filterLength($value),
            'filterUpper' => $this->filterUpper($value),
            'filterLower' => $this->filterLower($value),
            'filterCapitalize' => $this->filterCapitalize($value),
            'filterJson' => $this->filterJson($value),
            'filterSlug' => $this->filterSlug($value),
            'filterDate' => $this->filterDate($value, $params[0] ?? 'Y-m-d'),
            'filterNumberFormat' => $this->filterNumberFormat($value, $params),
            'filterDefault' => $this->filterDefault($value, $params[0] ?? 'N/A'),
            'filterTruncate' => $this->filterTruncate($value, (int)($params[0] ?? 50)),
            'filterCurrency' => $this->filterCurrency($value, $params[0] ?? '€', $params[1] ?? 'right'),
            'filterRating' => $this->filterRating($value, (int)($params[0] ?? 10)),
            'filterPlural' => $this->filterPlural($value, $params[0] ?? '', $params[1] ?? ''),
            'filterTranslate' => $this->filterTranslate($value, $params),
            'filterTranslatePlural' => $this->filterTranslatePlural($value, $params),
            default => $value,
        };
    }

    /**
     * Escape output for HTML
     */
    public function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        // Fast path for already-string values (most common case)
        if (is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        // Convert to string only when necessary
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
     * Length filter - get count of array or string length
     */
    private function filterLength(mixed $value): int
    {
        // Fast path for common types
        if (is_string($value)) {
            return mb_strlen($value);
        }

        if (is_array($value)) {
            return count($value);
        }

        if ($value instanceof Countable) {
            return count($value);
        }

        return 0;
    }

    /**
     * Upper filter - convert to uppercase
     */
    private function filterUpper(mixed $value): string
    {
        // Avoid type conversion if already string
        if (is_string($value)) {
            return mb_strtoupper($value);
        }
        return mb_strtoupper((string)$value);
    }

    /**
     * Lower filter - convert to lowercase
     */
    private function filterLower(mixed $value): string
    {
        if (is_string($value)) {
            return mb_strtolower($value);
        }
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
     * JSON filter - encode as JSON
     */
    private function filterJson(mixed $value): string
    {
        // Fast path for simple types
        if (is_string($value)) {
            return '"' . addslashes($value) . '"';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        // For complex types, use json_encode with optimized flags
        try {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            return '{}'; // Fallback for encoding errors
        }
    }

    /**
     * Slug filter - create URL-friendly string
     */
    private function filterSlug(mixed $value): string
    {
        if (!is_string($value)) {
            $value = (string)$value;
        }

        // Faster single regex instead of multiple operations
        return trim(
            preg_replace([
                '/[^a-z0-9\-]/i',  // Remove non-alphanumeric except hyphens
                '/-+/'             // Collapse multiple hyphens
            ], [
                '-',
                '-'
            ], mb_strtolower($value)),
            '-'
        );
    }

    /**
     * Enhanced date filter with format parameter
     */
    private function filterDate(mixed $value, string $format = 'Y-m-d'): string
    {
        if (!is_string($value)) {
            return (string)$value;
        }

        // Cache parsed timestamps to avoid repeated strtotime() calls
        static $timestampCache = [];

        $cacheKey = $value;
        if (!isset($timestampCache[$cacheKey])) {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return $value; // Return original if parsing fails
            }
            $timestampCache[$cacheKey] = $timestamp;

            // Prevent cache from growing too large
            if (count($timestampCache) > 100) {
                $timestampCache = array_slice($timestampCache, -50, null, true);
            }
        }

        return date($format, $timestampCache[$cacheKey]);
    }

    /**
     * Enhanced number format with parameters
     */
    private function filterNumberFormat(mixed $value, array $params = []): string
    {
        if (!is_numeric($value)) {
            return (string)$value;
        }

        // Pre-validate and set defaults to avoid repeated array access
        $decimals = isset($params[0]) ? (int)$params[0] : 0;
        $decimalSep = $params[1] ?? '.';
        $thousandsSep = $params[2] ?? ',';

        return number_format((float)$value, $decimals, $decimalSep, $thousandsSep);
    }

    /**
     * Enhanced default filter with parameter
     */
    private function filterDefault(mixed $value, string $default = 'N/A'): mixed
    {
        // Fast empty check - avoid complex empty() logic when possible
        if ($value === null || $value === '' || $value === []) {
            return $default;
        }

        // For other cases, use standard empty check
        return empty($value) ? $default : $value;
    }

    /**
     * Truncate filter - limit string length
     */
    private function filterTruncate(mixed $value, int $length): string
    {
        if (!is_string($value)) {
            $value = (string)$value;
        }

        // Fast path: if string is already short enough
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length) . '...';
    }

    /**
     * Currency filter - format money values
     */
    private function filterCurrency(mixed $value, string $symbol = '€', string $position = 'right'): string
    {
        if (!is_numeric($value)) {
            return (string)$value;
        }

        // Cache decoded symbols to avoid repeated html_entity_decode calls
        static $symbolCache = [];

        if (!isset($symbolCache[$symbol])) {
            $symbolCache[$symbol] = html_entity_decode($symbol, ENT_QUOTES, 'UTF-8');

            // Prevent cache from growing indefinitely
            if (count($symbolCache) > 20) {
                $symbolCache = array_slice($symbolCache, -10, null, true);
            }
        }

        $decodedSymbol = $symbolCache[$symbol];
        $formatted = number_format((float)$value, 2, ',', '.');

        return $position === 'left'
            ? $decodedSymbol . ' ' . $formatted
            : $formatted . ' ' . $decodedSymbol;
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

        // Avoid sprintf for better performance
        return '<span class="rating" data-rating="' . number_format($rating, 1) .
            '" data-percentage="' . round($percentage) . '">' .
            number_format($rating, 1) . '/' . $maxRating . '</span>';
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
        $parameters = [];

        // Handle parameters correctly
        if (!empty($params)) {
            $firstParam = $params[0];

            // If it's a JSON string (object syntax), decode it
            if (is_string($firstParam) && str_starts_with($firstParam, '{')) {
                $decoded = json_decode($firstParam, true);
                if ($decoded !== null) {
                    $parameters = $decoded;
                }
            } elseif (is_array($firstParam)) {
                $parameters = $firstParam;
            }
        }

        return $translator->translate($key, $parameters);
    }

    /**
     * Get translator instance from ServiceRegistry
     */
    private function getTranslator(): ?\Framework\Localization\Translator
    {
        static $translator = null;

        if ($translator === null) {
            try {
                $translator = \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
            } catch (\Throwable) {
                $translator = false; // Cache the failure
            }
        }

        return $translator ?: null;
    }

    /**
     * Get variable from data with dot notation support
     */
    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
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
        $parameters = [];

        // Handle second parameter (additional parameters)
        if (isset($params[1])) {
            $secondParam = $params[1];

            if (is_string($secondParam) && str_starts_with($secondParam, '{')) {
                $decoded = json_decode($secondParam, true);
                if ($decoded !== null) {
                    $parameters = $decoded;
                }
            } elseif (is_array($secondParam)) {
                $parameters = $secondParam;
            }
        }

        // Add count to parameters
        $parameters['count'] = $count;

        return $translator->translatePlural($key, $count, $parameters);
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

    /**
     * Get current locale (callable from templates)
     */
    public function getCurrentLocale(): string
    {
        $translator = $this->getTranslator();
        return $translator?->getLocale() ?? 'de';
    }

    /**
     * Get supported locales (callable from templates)
     */
    public function getSupportedLocales(): array
    {
        $translator = $this->getTranslator();
        return $translator?->getSupportedLocales() ?? ['de', 'en', 'fr', 'es'];
    }

    /**
     * Enhanced translate function with proper parameter handling
     */
    public function t(string $key, mixed $parameters = []): string
    {
        $translator = $this->getTranslator();

        if (!$translator) {
            return $key; // Fallback if no translator available
        }

        // Handle different parameter types
        if (is_string($parameters)) {
            // Try to decode JSON string
            $decoded = json_decode($parameters, true);
            $parameters = $decoded ?? [];
        }

        if (!is_array($parameters)) {
            $parameters = [];
        }

        return $translator->translate($key, $parameters);
    }

    /**
     * Enhanced translate plural function with proper parameter handling
     */
    public function tPlural(string $key, int $count, mixed $parameters = []): string
    {
        $translator = $this->getTranslator();

        if (!$translator) {
            return $key; // Fallback if no translator available
        }

        // Handle different parameter types
        if (is_string($parameters)) {
            // Try to decode JSON string
            $decoded = json_decode($parameters, true);
            $parameters = $decoded ?? [];
        }

        if (!is_array($parameters)) {
            $parameters = [];
        }

        // Add count to parameters
        $parameters['count'] = $count;

        return $translator->translatePlural($key, $count, $parameters);
    }
}