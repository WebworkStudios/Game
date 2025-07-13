<?php
// framework/Templating/Filters/EscapeFilter.php

declare(strict_types=1);

namespace Framework\Templating\Filters;

/**
 * Escape Filter - Explizites HTML-Escaping
 *
 * Verwendung: {{ content|escape }}
 * Auch verfügbar als: {{ content|e }}
 */
class EscapeFilter implements FilterInterface
{
    public function apply(mixed $value, array $parameters = []): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $strategy = $parameters[0] ?? 'html';

        return match ($strategy) {
            'html' => htmlspecialchars(
                $value,
                ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
                'UTF-8',
                true
            ),
            'attr' => htmlspecialchars(
                $value,
                ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
                'UTF-8',
                true
            ),
            'js' => $this->escapeJavaScript($value),
            'css' => $this->escapeCss($value),
            'url' => rawurlencode($value),
            default => htmlspecialchars(
                $value,
                ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
                'UTF-8',
                true
            ),
        };
    }

    /**
     * JavaScript-String escaping
     */
    private function escapeJavaScript(string $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    /**
     * CSS-String escaping
     */
    private function escapeCss(string $value): string
    {
        // Entferne gefährliche CSS-Zeichen
        return preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $value);
    }

    public function getName(): string
    {
        return 'escape';
    }

    public function getAliases(): array
    {
        return ['e'];
    }
}