<?php

declare(strict_types=1);

namespace Framework\Templating\Services;

use Framework\Http\HttpStatus;
use Framework\Http\Response;
use Throwable;

/**
 * TemplateErrorHandler - BEREINIGT: Externe Templates statt Inline-HTML
 */
readonly class TemplateErrorHandler
{
    public function __construct(
        private bool $debugMode = false,
        private string $templatesPath = __DIR__ . '/../../Views/errors'
    ) {}

    /**
     * Behandelt Template-Rendering-Fehler
     */
    public function handleRenderError(
        Throwable $exception,
        string $template,
        array $data,
        HttpStatus $status = HttpStatus::INTERNAL_SERVER_ERROR,
        array $headers = []
    ): Response {

        $errorData = $this->prepareErrorData($exception, $template, $data);
        $html = $this->renderErrorTemplate($errorData);

        return new Response(
            HttpStatus::INTERNAL_SERVER_ERROR,
            ['Content-Type' => 'text/html', ...$headers],
            $html
        );
    }

    /**
     * BEREINIGT: Einheitliche Error-Data-Preparation
     */
    private function prepareErrorData(Throwable $exception, string $template, array $data): array
    {
        $baseData = [
            'title' => $this->debugMode ? 'Template Rendering Error' : 'Internal Server Error',
            'code' => 500,
            'debug_mode' => $this->debugMode,
        ];

        if ($this->debugMode) {
            $baseData += [
                'template' => $template,
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'data_keys' => array_keys($data),
                'php_version' => PHP_VERSION,
                'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            ];
        } else {
            $baseData['message'] = 'An error occurred while processing your request.';
        }

        return $baseData;
    }

    /**
     * BEREINIGT: Template-basiertes Error-Rendering
     */
    private function renderErrorTemplate(array $data): string
    {
        $templateFile = $this->debugMode ? 'debug.html' : 'production.html';
        $templatePath = $this->templatesPath . '/' . $templateFile;

        // Fallback zu simplem Template falls Datei nicht existiert
        if (!file_exists($templatePath)) {
            return $this->renderFallbackTemplate($data);
        }

        // Template-Rendering mit Variable-Replacement
        try {
            $content = file_get_contents($templatePath);
            return $this->processTemplate($content, $data);
        } catch (\Throwable) {
            return $this->renderFallbackTemplate($data);
        }
    }

    /**
     * Simple Template-Variable-Replacement
     */
    private function processTemplate(string $content, array $data): string
    {
        // Simple {{ variable }} replacement
        foreach ($data as $key => $value) {
            $placeholder = '{{ ' . $key . ' }}';
            $replacement = is_scalar($value) ? htmlspecialchars((string)$value) : '';
            $content = str_replace($placeholder, $replacement, $content);
        }
        return $content;
    }

    /**
     * Minimal-Fallback ohne externe Abhängigkeiten
     */
    private function renderFallbackTemplate(array $data): string
    {
        $title = htmlspecialchars($data['title'] ?? 'Error');
        $message = htmlspecialchars($data['message'] ?? 'An error occurred');
        $code = (int)($data['code'] ?? 500);

        return "<!DOCTYPE html>
<html lang=de>
<head>
    <meta charset=\"UTF-8\">
    <title>{$title}</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .error { max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class=\"error\">
        <h1>{$title}</h1>
        <p>{$message}</p>
        <small>Error Code: {$code}</small>
    </div>
</body>
</html>";
    }

    /**
     * Helper: Bytes formatieren
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes) / log(1024));
        return sprintf('%.2f %s', $bytes / (1024 ** $factor), $units[$factor] ?? 'TB');
    }
}