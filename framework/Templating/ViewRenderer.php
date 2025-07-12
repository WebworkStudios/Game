<?php
// framework/Templating/ViewRenderer.php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Http\HttpStatus;
use Framework\Http\Response;

/**
 * View Renderer - Integriert Template Engine in Response System
 */
readonly class ViewRenderer
{
    public function __construct(
        private TemplateEngine $engine
    )
    {
    }

    /**
     * Rendert Template zu Response
     */
    public function render(
        string     $template,
        array      $data = [],
        HttpStatus $status = HttpStatus::OK,
        array      $headers = []
    ): Response
    {
        // Auto-inject current locale if not already provided
        if (!isset($data['current_locale'])) {
            try {
                $translator = \Framework\Core\ServiceRegistry::get(\Framework\Localization\Translator::class);
                $data['current_locale'] = $translator->getLocale();
            } catch (\Throwable) {
                $data['current_locale'] = 'de'; // Fallback
            }
        }

        // Auto-inject CSRF token field for forms
        if (!isset($data['csrf_token_field'])) {
            try {
                $csrf = \Framework\Core\ServiceRegistry::get(\Framework\Security\Csrf::class);
                $data['csrf_token_field'] = $csrf->getTokenField();
            } catch (\Throwable) {
                $data['csrf_token_field'] = '<!-- CSRF not available -->';
            }
        }

        $content = $this->engine->render($template, $data);

        $headers['Content-Type'] = 'text/html; charset=UTF-8';

        return new Response($status, $headers, $content);
    }
}