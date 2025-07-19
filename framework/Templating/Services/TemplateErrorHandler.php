<?php

declare(strict_types=1);

namespace Framework\Templating\Services;

use Framework\Http\HttpStatus;
use Framework\Http\Response;
use Throwable;

/**
 * TemplateErrorHandler - SRP: Verantwortlich NUR für Template-Error-Handling
 *
 * Früher: Teil des ViewRenderer (SRP-Verletzung)
 * Jetzt: Spezialisierte Error-Handling-Klasse
 */
readonly class TemplateErrorHandler
{
    public function __construct(
        private bool $debugMode = false
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

        $message = $this->debugMode
            ? $this->createDebugErrorMessage($exception, $template, $data)
            : $this->createProductionErrorMessage();

        $html = $this->renderErrorPage($message, $template, $exception);

        return new Response(
            HttpStatus::INTERNAL_SERVER_ERROR,
            ['Content-Type' => 'text/html', ...$headers],
            $html
        );
    }

    /**
     * Debug-Error-Message für Development
     */
    private function createDebugErrorMessage(Throwable $exception, string $template, array $data): array
    {
        return [
            'title' => 'Template Rendering Error',
            'template' => $template,
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'data_keys' => array_keys($data),
            'debug_info' => [
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
            ]
        ];
    }

    /**
     * Production-Error-Message
     */
    private function createProductionErrorMessage(): array
    {
        return [
            'title' => 'Internal Server Error',
            'message' => 'An error occurred while processing your request.',
            'code' => 500
        ];
    }

    /**
     * Rendert eine Error-Page
     */
    private function renderErrorPage(array $errorData, string $template, Throwable $exception): string
    {
        if ($this->debugMode) {
            return $this->renderDebugErrorPage($errorData);
        }

        return $this->renderProductionErrorPage($errorData);
    }

    /**
     * Debug-Error-Page mit Details
     */
    private function renderDebugErrorPage(array $data): string
    {
        $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template Error - KickersCup Manager</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #1a1a1a; color: #fff; }
        .error-container { max-width: 1200px; margin: 0 auto; }
        .error-header { background: #dc3545; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .error-section { background: #333; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
        .error-section h3 { color: #ffc107; margin-top: 0; }
        pre { background: #222; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .highlight { background: #495057; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-header">
            <h1>' . htmlspecialchars($data['title']) . '</h1>
            <p><strong>Template:</strong> <span class="highlight">' . htmlspecialchars($data['template']) . '</span></p>
            <p><strong>Error:</strong> ' . htmlspecialchars($data['error']) . '</p>
        </div>
        
        <div class="error-section">
            <h3>📍 Location</h3>
            <p><strong>File:</strong> ' . htmlspecialchars($data['file']) . '</p>
            <p><strong>Line:</strong> ' . htmlspecialchars((string)$data['line']) . '</p>
        </div>
        
        <div class="error-section">
            <h3>📊 Debug Information</h3>
            <p><strong>PHP Version:</strong> ' . htmlspecialchars($data['debug_info']['php_version']) . '</p>
            <p><strong>Memory Usage:</strong> ' . number_format($data['debug_info']['memory_usage'] / 1024 / 1024, 2) . ' MB</p>
            <p><strong>Memory Peak:</strong> ' . number_format($data['debug_info']['memory_peak'] / 1024 / 1024, 2) . ' MB</p>
        </div>
        
        <div class="error-section">
            <h3>🔍 Stack Trace</h3>
            <pre>' . htmlspecialchars($data['trace']) . '</pre>
        </div>
        
        <div class="error-section">
            <h3>📦 Template Data Keys</h3>
            <p>' . implode(', ', array_map('htmlspecialchars', $data['data_keys'])) . '</p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Production-Error-Page (minimal)
     */
    private function renderProductionErrorPage(array $data): string
    {
        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
        .error-container { max-width: 500px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>' . htmlspecialchars($data['title']) . '</h1>
        <p>' . htmlspecialchars($data['message']) . '</p>
        <p>Error Code: ' . htmlspecialchars((string)$data['code']) . '</p>
    </div>
</body>
</html>';
    }
}