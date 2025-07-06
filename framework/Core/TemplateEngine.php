<?php
/**
 * Template Engine
 * Simple PHP template rendering engine
 */
declare(strict_types=1);

namespace Framework\Core;

use Framework\Localization\LocalizationService;

class TemplateEngine
{
    private string $templatePath;
    private array $globals = []; // Add globals storage

    public function __construct(string $templatePath = '')
    {
        $this->templatePath = $templatePath ?: __DIR__ . '/../../templates/';
    }

    /**
     * Add global variable available to all templates
     */
    public function addGlobal(string $key, mixed $value): void
    {
        $this->globals[$key] = $value;
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

        // Merge data with globals (data takes precedence)
        $templateData = array_merge($this->globals, $data);

        // Extract data to variables
        extract($templateData, EXTR_SKIP);

        // Include template
        include $templateFile;
    }

    /**
     * Set localization service
     */
    public function setLocalizationService(LocalizationService $localization): void
    {
        $this->addGlobal('localization', $localization);
        $this->addGlobal('__', fn(string $key, array $params = []) => $localization->get($key, $params));
        $this->addGlobal('trans', fn(string $key, array $params = []) => $localization->get($key, $params));
    }
}