<?php


declare(strict_types=1);

namespace Framework\Http;

use Framework\Templating\TemplateEngine;
use Framework\Templating\ViewRenderer;
use InvalidArgumentException;
use JsonException;

/**
 * Response Factory - Creates Response objects with proper DI instead of static methods
 */
readonly class ResponseFactory
{
    public function __construct(
        private ViewRenderer   $viewRenderer,
        private TemplateEngine $engine
    )
    {
    }

    /**
     * Template Response with JSON fallback
     */
    public function viewOrJson(
        string     $template,
        array      $data = [],
        bool       $wantsJson = false,
        HttpStatus $status = HttpStatus::OK
    ): Response
    {
        if ($wantsJson) {
            return $this->json($data, $status);
        }

        return $this->view($template, $data, $status);
    }

    /**
     * JSON Response Factory
     */
    public function json(
        array      $data,
        HttpStatus $status = HttpStatus::OK,
        array      $headers = [],
        int        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ): Response
    {
        $headers['Content-Type'] = 'application/json; charset=UTF-8';

        try {
            $body = json_encode($data, JSON_THROW_ON_ERROR | $options);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON data: ' . $e->getMessage());
        }

        return new Response($status, $headers, $body);
    }

    /**
     * Template Response Factory
     */
    public function view(
        string     $template,
        array      $data = [],
        HttpStatus $status = HttpStatus::OK,
        array      $headers = []
    ): Response
    {
        try {
            return $this->viewRenderer->render($template, $data, $status, $headers);
        } catch (\Throwable $e) {
            // Fallback: Use TemplateEngine directly
            $content = $this->engine->render($template, $data);

            $headers['Content-Type'] = 'text/html; charset=UTF-8';
            return new Response($status, $headers, $content);
        }
    }

    /**
     * Common Response Factory Methods
     */
    public function ok(string $body = '', array $headers = []): Response
    {
        return new Response(HttpStatus::OK, $headers, $body);
    }

    public function created(string $body = '', array $headers = []): Response
    {
        return new Response(HttpStatus::CREATED, $headers, $body);
    }

    public function noContent(array $headers = []): Response
    {
        return new Response(HttpStatus::NO_CONTENT, $headers);
    }

    public function temporaryRedirect(string $url): Response
    {
        return $this->redirect($url, HttpStatus::TEMPORARY_REDIRECT);
    }

    /**
     * Redirect Response Factory
     */
    public function redirect(string $url, HttpStatus $status = HttpStatus::FOUND): Response
    {
        if (!$status->isRedirect()) {
            throw new InvalidArgumentException('Status code must be a redirect status');
        }

        return new Response($status, ['Location' => $url]);
    }

    public function badRequest(string $body = 'Bad Request', array $headers = []): Response
    {
        return new Response(HttpStatus::BAD_REQUEST, $headers, $body);
    }

    public function unauthorized(string $body = 'Unauthorized', array $headers = []): Response
    {
        return new Response(HttpStatus::UNAUTHORIZED, $headers, $body);
    }

    public function forbidden(string $body = 'Forbidden', array $headers = []): Response
    {
        return new Response(HttpStatus::FORBIDDEN, $headers, $body);
    }

    public function notFound(string $body = 'Not Found', array $headers = []): Response
    {
        return new Response(HttpStatus::NOT_FOUND, $headers, $body);
    }

    public function methodNotAllowed(string $body = 'Method Not Allowed', array $headers = []): Response
    {
        return new Response(HttpStatus::METHOD_NOT_ALLOWED, $headers, $body);
    }

    public function unprocessableEntity(string $body = 'Unprocessable Entity', array $headers = []): Response
    {
        return new Response(HttpStatus::UNPROCESSABLE_ENTITY, $headers, $body);
    }

    public function serverError(string $body = 'Internal Server Error', array $headers = []): Response
    {
        return new Response(HttpStatus::INTERNAL_SERVER_ERROR, $headers, $body);
    }
}