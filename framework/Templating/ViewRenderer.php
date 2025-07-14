<?php

declare(strict_types=1);

namespace Framework\Templating;

use Framework\Http\HttpStatus;
use Framework\Http\Response;
use Framework\Localization\Translator;
use Framework\Security\Csrf;

/**
 * View Renderer - Integriert Template Engine in Response System
 */
readonly class ViewRenderer
{
    public function __construct(
        private TemplateEngine $engine,
        private Translator $translator,
        private Csrf $csrf
    ) {}

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
                $data['current_locale'] = $this->translator->getLocale();
            } catch (\Throwable) {
                $data['current_locale'] = 'de'; // Fallback
            }
        }

        // Auto-inject CSRF token field for forms
        if (!isset($data['csrf_token_field'])) {
            try {
                $data['csrf_token_field'] = $this->csrf->getTokenField();
            } catch (\Throwable) {
                $data['csrf_token_field'] = '<!-- CSRF not available -->';
            }
        }

        // Auto-inject CSRF meta tag for JavaScript
        if (!isset($data['csrf_meta_tag'])) {
            try {
                $data['csrf_meta_tag'] = $this->csrf->getTokenMeta();
            } catch (\Throwable) {
                $data['csrf_meta_tag'] = '<!-- CSRF meta not available -->';
            }
        }

        // Render template content
        $content = $this->engine->render($template, $data);

        // Inject CSRF meta tag into HTML head if not already present
        $content = $this->injectCsrfMeta($content, $data);

        $headers['Content-Type'] = 'text/html; charset=UTF-8';

        return new Response($status, $headers, $content);
    }

    /**
     * Injiziert CSRF Meta Tag in HTML Head
     */
    private function injectCsrfMeta(string $content, array $data): string
    {
        // Check if CSRF meta tag is already present
        if (str_contains($content, 'name="csrf-token"')) {
            return $content;
        }

        // Check if we have a head section
        if (!str_contains($content, '<head>')) {
            return $content;
        }

        // Get CSRF meta tag
        $csrfMeta = $data['csrf_meta_tag'] ?? '';
        if (empty($csrfMeta) || str_contains($csrfMeta, 'not available')) {
            return $content;
        }

        // Inject after <head> tag
        $content = str_replace(
            '<head>',
            "<head>\n    " . $csrfMeta,
            $content
        );

        return $content;
    }
}