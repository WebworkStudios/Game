<?php

/**
 * Template Engine
 * Simple PHP template rendering engine
 *
 * File: framework/Core/TemplateEngine.php
 * Directory: /framework/Core/
 */

declare(strict_types=1);

namespace Framework\Core;

class TemplateEngine
{
    private string $templatePath;

    public function __construct(string $templatePath = '')
    {
        $this->templatePath = $templatePath ?: __DIR__ . '/../../templates/';
    }

    /**
     * Get template content as string
     */
    public function getContent(string $template, array $data = []): string
    {
        ob_start();
        $this->render($template, $data);
        return ob_get_clean();
    }

    /**
     * Render a template
     */
    public function render(string $template, array $data = []): void
    {
        $templateFile = $this->templatePath . $template . '.php';

        if (!file_exists($templateFile)) {
            throw new \InvalidArgumentException("Template [{$template}] not found at {$templateFile}");
        }

        // Extract data to variables
        extract($data, EXTR_SKIP);

        // Include template
        include $templateFile;
    }
}