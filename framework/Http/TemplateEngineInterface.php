<?php
declare(strict_types=1);

namespace Framework\Http;

/**
 * Template Engine Interface für ResponseFactory
 */
interface TemplateEngineInterface
{
    /**
     * Rendert Template mit Daten
     */
    public function render(string $template, array $data = []): string;

    /**
     * Prüft ob Template existiert
     */
    public function exists(string $template): bool;
}