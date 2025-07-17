<?php
namespace Framework\Templating\Parsing;

use Framework\Templating\Tokens\{TemplateToken, TokenFactory, ControlToken, TextToken, VariableToken};
/**
 * TemplatePathResolver - Löst Template-Namen zu Dateipfaden auf
 */
class TemplatePathResolver
{
    public function __construct(
        private readonly array $paths = []
    ) {}

    public function resolve(string $template): string
    {
        // Add .html extension if not present
        if (!str_contains($template, '.')) {
            $template .= '.html';
        }

        foreach ($this->paths as $path) {
            $fullPath = $this->buildPath($path, $template);

            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        throw new \RuntimeException("Template not found: {$template} in paths: " . implode(', ', $this->paths));
    }

    private function buildPath(string $basePath, string $template): string
    {
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $basePath);
        $normalizedTemplate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $template);

        return rtrim($normalizedPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($normalizedTemplate, DIRECTORY_SEPARATOR);
    }
}
