<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Http\HttpStatus;
use Framework\Http\Response;

class ViewRenderer
{
    public function __construct(
        private readonly TemplateEngine $engine
    ) {}

    /**
     * Render template to Response
     */
    public function render(
        string $template,
        array $data = [],
        HttpStatus $status = HttpStatus::OK,
        array $headers = []
    ): Response {
        try {
            $content = $this->engine->render($template, $data);

            $headers['Content-Type'] = 'text/html; charset=UTF-8';

            return new Response($status, $headers, $content);

        } catch (\Throwable $e) {
            // In Development: Show template error
            if ($this->isDebugMode()) {
                return $this->renderTemplateError($e, $template, $data);
            }

            // In Production: Re-throw exception
            throw $e;
        }
    }

    /**
     * Render JSON with template fallback
     */
    public function renderOrJson(
        string $template,
        array $data = [],
        bool $wantsJson = false
    ): Response {
        if ($wantsJson) {
            return Response::json($data);
        }

        return $this->render($template, $data);
    }

    /**
     * Check if we're in debug mode
     */
    private function isDebugMode(): bool
    {
        // Check if Application is available and in debug mode
        try {
            if (class_exists('\Framework\Core\ServiceRegistry')) {
                $app = \Framework\Core\ServiceRegistry::get(\Framework\Core\Application::class);
                return $app->isDebug();
            }
        } catch (\Throwable) {
            // Fallback: assume production mode
        }

        return false;
    }

    /**
     * Debug template error page
     */
    private function renderTemplateError(\Throwable $e, string $template, array $data): Response
    {
        $html = "
        <!DOCTYPE html>
        <html lang=de>
        <head>
            <title>Template Error</title>
            <style>
                body { font-family: monospace; margin: 20px; background: #f8f8f8; }
                .error { background: white; padding: 20px; border-left: 5px solid #e74c3c; }
                .message { font-size: 1.2em; font-weight: bold; color: #e74c3c; margin-bottom: 10px; }
                .template { color: #666; margin-bottom: 20px; }
                .data { background: #f5f5f5; padding: 15px; border-radius: 5px; }
                .trace { background: #f0f0f0; padding: 15px; border-radius: 5px; margin-top: 20px; }
                pre { margin: 0; white-space: pre-wrap; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='error'>
                <div class='message'>Template Error: {$e->getMessage()}</div>
                <div class='template'>Template: {$template}</div>
                <div class='template'>File: {$e->getFile()}:{$e->getLine()}</div>
                <div class='data'>
                    <strong>Template Data:</strong>
                    <pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>
                </div>
                <div class='trace'>
                    <strong>Stack Trace:</strong>
                    <pre>{$e->getTraceAsString()}</pre>
                </div>
            </div>
        </body>
        </html>";

        return Response::serverError($html);
    }
}