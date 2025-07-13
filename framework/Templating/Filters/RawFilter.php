<?php
declare(strict_types=1);

namespace Framework\Templating\Filters;

/**
 * Raw Filter - Gibt Inhalt ohne HTML-Escaping aus
 *
 * WARNUNG: Nur für vertrauenswürdige Inhalte verwenden!
 * Verwendung: {{ content|raw }}
 */
class RawFilter implements FilterInterface
{
    public function apply(mixed $value, array $parameters = []): mixed
    {
        // Raw Filter macht nichts - verhindert nur das automatische Escaping
        return $value;
    }

    public function getName(): string
    {
        return 'raw';
    }
}