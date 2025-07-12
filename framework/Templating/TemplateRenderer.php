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
        array $data = []
    ) {
        $this->data = $data;
    }

    /**
     * Get variable value with support for nested access
     */
    public function get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Set variable in data
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
     * Escape value for HTML output
     */
    public function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Return raw (unescaped) value
     */
    public function raw(mixed $value): string
    {
        return (string)($value ?? '');
    }

    /**
     * Include another template
     */
    public function include(string $template, array $data = []): string
    {
        return $this->engine->include($template, array_merge($this->data, $data));
    }

    /**
     * Include template with data mapping
     */
    public function includeWith(string $template, string $variable, mixed $data): string
    {
        return $this->engine->includeWith($template, $variable, $data);
    }

    /**
     * Set blocks for template inheritance
     */
    public function setBlocks(array $blocks): void
    {
        // Child blocks override parent blocks
        $this->blocks = array_merge($this->parentBlocks, $blocks);
    }

    /**
     * Set parent blocks (for nested inheritance)
     */
    public function setParentBlocks(array $parentBlocks): void
    {
        $this->parentBlocks = $parentBlocks;
        // Re-merge with current blocks
        $this->blocks = array_merge($this->parentBlocks, $this->blocks);
    }

    /**
     * Check if block exists
     */
    public function hasBlock(string $name): bool
    {
        return isset($this->blocks[$name]) && is_callable($this->blocks[$name]);
    }

    /**
     * Render block
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
     * Render parent block (for block inheritance with parent() calls)
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
            'number_format' => $this->filterNumberFormat($value, $params[0] ?? 0, $params[1] ?? '.', $params[2] ?? ','),
            'default' => $this->filterDefault($value, $params[0] ?? ''),
            'truncate' => $this->filterTruncate($value, $params[0] ?? 100, $params[1] ?? '...'),
            'escape' => $this->escape($value),
            'raw' => $this->raw($value),
            'json' => $this->filterJson($value),
            'slug' => $this->filterSlug($value),
            'currency' => $this->filterCurrency($value, $params[0] ?? 'EUR'),
            'rating' => $this->filterRating($value),
            'plural' => $this->filterPlural($value, $params[0] ?? '', $params[1] ?? ''),
            't' => $this->filterTranslate($value, $params),
            't_plural' => $this->filterTranslatePlural($value, $params[0] ?? 1, $params),
            default => throw new RuntimeException("Unknown filter: {$filter}")
        };
    }

    // Filter implementations
    private function filterLength(mixed $value): int
    {
        if (is_array($value) || $value instanceof Countable) {
            return count($value);
        }
        return strlen((string)$value);
    }

    private function filterUpper(mixed $value): string
    {
        return mb_strtoupper((string)$value, 'UTF-8');
    }

    private function filterLower(mixed $value): string
    {
        return mb_strtolower((string)$value, 'UTF-8');
    }

    private function filterCapitalize(mixed $value): string
    {
        return mb_convert_case((string)$value, MB_CASE_TITLE, 'UTF-8');
    }

    private function filterDate(mixed $value, string $format): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        if (is_string($value)) {
            $date = \DateTime::createFromFormat('Y-m-d', $value);
            if ($date === false) {
                $date = new \DateTime($value);
            }
            return $date->format($format);
        }

        if (is_numeric($value)) {
            return date($format, (int)$value);
        }

        return '';
    }

    private function filterNumberFormat(mixed $value, int $decimals, string $decimalSeparator, string $thousandsSeparator): string
    {
        return number_format((float)$value, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    private function filterDefault(mixed $value, mixed $default): mixed
    {
        return $value ?: $default;
    }

    private function filterTruncate(mixed $value, int $length, string $suffix): string
    {
        $str = (string)$value;
        if (mb_strlen($str, 'UTF-8') <= $length) {
            return $str;
        }
        return mb_substr($str, 0, $length, 'UTF-8') . $suffix;
    }

    private function filterJson(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_QUOT | JSON_HEX_TAG);
    }

    private function filterSlug(mixed $value): string
    {
        $str = (string)$value;
        $str = mb_strtolower($str, 'UTF-8');
        $str = preg_replace('/[^a-z0-9\-]/', '-', $str);
        $str = preg_replace('/-+/', '-', $str);
        return trim($str, '-');
    }

    private function filterCurrency(mixed $value, string $currency): string
    {
        $amount = number_format((float)$value, 2, ',', '.');
        return match ($currency) {
            'EUR' => $amount . ' €',
            'USD' => '$' . $amount,
            'GBP' => '£' . $amount,
            default => $amount . ' ' . $currency
        };
    }

    private function filterRating(mixed $value): string
    {
        $rating = (float)$value;
        $stars = str_repeat('⭐', (int)$rating);
        return $stars . ' (' . number_format($rating, 1) . '/10)';
    }

    private function filterPlural(mixed $value, string $singular, string $plural): string
    {
        $count = is_numeric($value) ? (int)$value : $this->filterLength($value);
        return $count === 1 ? $singular : $plural;
    }

    private function filterTranslate(string $key, array $params = []): string
    {
        try {
            $translator = \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
            return $translator->translate($key, $params);
        } catch (Throwable) {
            return $key; // Fallback to key if translator not available
        }
    }

    private function filterTranslatePlural(string $key, int $count, array $params = []): string
    {
        try {
            $translator = \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
            return $translator->translatePlural($key, $count, $params);
        } catch (Throwable) {
            return $key; // Fallback to key if translator not available
        }
    }
}