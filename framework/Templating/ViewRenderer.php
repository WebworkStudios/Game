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
    ) {}

    /**
     * Rendert Template zu Response
     */
    public function render(
        string $template,
        array $data = [],
        HttpStatus $status = HttpStatus::OK,
        array $headers = []
    ): Response {
        $content = $this->engine->render($template, $data);

        $headers['Content-Type'] = 'text/html; charset=UTF-8';

        return new Response($status, $headers, $content);
    }
}