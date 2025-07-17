<?php
declare(strict_types=1);

namespace Framework\Http;

use Framework\Templating\TemplateEngine;
use Framework\Templating\ViewRenderer;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * ResponseFactory - Factory für Response-Objekte mit PHP 8.4 Features
 */
readonly class ResponseFactory
{
    public function __construct(
        private ViewRenderer   $viewRenderer,
        private TemplateEngine $engine
    )
    {
    }

    // ===================================================================
    // Standard Response Methods
    // ===================================================================

    public function response(string $body = '', HttpStatus $status = HttpStatus::OK, array $headers = []): Response
    {
        return new Response($status, $headers, $body);
    }

    /**
     * VERBESSERT: View Response mit Template Engine
     */
    public function view(string $template, array $data = [], HttpStatus $status = HttpStatus::OK): Response
    {
        try {
            return $this->viewRenderer->render($template, $data, $status);
        } catch (Throwable $e) {
            // Fallback: Use TemplateEngine directly
            $content = $this->engine->render($template, $data);
            return $this->html($content, $status);
        }
    }

    public function html(string $content, HttpStatus $status = HttpStatus::OK, array $headers = []): Response
    {
        return new Response(
            $status,
            [...$headers, 'Content-Type' => 'text/html; charset=UTF-8'], // MODERNISIERT
            $content
        );
    }

    public function ok(string $body = 'OK', array $headers = []): Response
    {
        return new Response(HttpStatus::OK, $headers, $body);
    }

    // ===================================================================
    // Success Responses
    // ===================================================================

    public function created(string $body = 'Created', array $headers = []): Response
    {
        return new Response(HttpStatus::CREATED, $headers, $body);
    }

    public function noContent(array $headers = []): Response
    {
        return new Response(HttpStatus::NO_CONTENT, $headers);
    }

    public function permanentRedirect(string $url): Response
    {
        return $this->redirect($url, HttpStatus::MOVED_PERMANENTLY);
    }

    // ===================================================================
    // Redirect Responses - MODERNISIERT
    // ===================================================================

    public function redirect(string $url, HttpStatus $status = HttpStatus::FOUND): Response
    {
        if (!$status->isRedirect()) {
            throw new InvalidArgumentException(
                "Status code {$status->value} is not a redirect status"
            );
        }

        return new Response($status, ['Location' => $url]);
    }

    public function temporaryRedirect(string $url): Response
    {
        return $this->redirect($url, HttpStatus::TEMPORARY_REDIRECT);
    }

    /**
     * NEU: Redirect mit Flash Message Support
     */
    public function redirectWithMessage(string $url, string $message, string $type = 'info'): Response
    {
        // Hier könnte Session-Flash Integration hinzugefügt werden
        return $this->redirect($url);
    }

    public function badRequest(string $body = 'Bad Request', array $headers = []): Response
    {
        return new Response(HttpStatus::BAD_REQUEST, $headers, $body);
    }

    // ===================================================================
    // Error Responses - ERWEITERT
    // ===================================================================

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

    /**
     * NEU: Too Many Requests Response
     */
    public function tooManyRequests(string $body = 'Too Many Requests', array $headers = []): Response
    {
        return new Response(HttpStatus::TOO_MANY_REQUESTS, $headers, $body);
    }

    public function serverError(string $body = 'Internal Server Error', array $headers = []): Response
    {
        return new Response(HttpStatus::INTERNAL_SERVER_ERROR, $headers, $body);
    }

    /**
     * NEU: Service Unavailable Response
     */
    public function serviceUnavailable(string $body = 'Service Unavailable', array $headers = []): Response
    {
        return new Response(HttpStatus::SERVICE_UNAVAILABLE, $headers, $body);
    }

    /**
     * NEU: File Download Response
     */
    public function download(
        string $content,
        string $filename,
        string $contentType = 'application/octet-stream'
    ): Response
    {
        return new Response(HttpStatus::OK, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
            'Content-Length' => (string)strlen($content),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ], $content);
    }

    // ===================================================================
    // Special Responses - NEU
    // ===================================================================

    /**
     * NEU: Stream Response für große Dateien
     */
    public function stream(
        callable   $callback,
        HttpStatus $status = HttpStatus::OK,
        array      $headers = []
    ): Response
    {
        $headers = [...$headers, 'Content-Type' => 'application/octet-stream'];

        ob_start();
        $callback();
        $content = ob_get_clean();

        return new Response($status, $headers, $content ?: '');
    }

    /**
     * NEU: XML Response
     */
    public function xml(string $content, HttpStatus $status = HttpStatus::OK, array $headers = []): Response
    {
        return new Response(
            $status,
            [...$headers, 'Content-Type' => 'application/xml; charset=UTF-8'],
            $content
        );
    }

    /**
     * NEU: Plain Text Response
     */
    public function text(string $content, HttpStatus $status = HttpStatus::OK, array $headers = []): Response
    {
        return new Response(
            $status,
            [...$headers, 'Content-Type' => 'text/plain; charset=UTF-8'],
            $content
        );
    }

    /**
     * NEU: API Error Response mit standardisiertem Format
     */
    public function apiError(
        string     $message,
        HttpStatus $status = HttpStatus::BAD_REQUEST,
        array      $errors = [],
        ?string    $code = null
    ): Response
    {
        $data = [
            'success' => false,
            'message' => $message,
            'status' => $status->value,
        ];

        if (!empty($errors)) {
            $data['errors'] = $errors;
        }

        if ($code !== null) {
            $data['code'] = $code;
        }

        return $this->json($data, $status);
    }

    /**
     * MODERNISIERT: JSON Response mit besserer Fehlerbehandlung
     */
    public function json(
        mixed      $data,
        HttpStatus $status = HttpStatus::OK,
        array      $headers = [],
        int        $flags = JSON_THROW_ON_ERROR
    ): Response
    {
        try {
            $json = json_encode($data, $flags);
            return new Response(
                $status,
                [...$headers, 'Content-Type' => 'application/json'], // MODERNISIERT
                $json
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Failed to encode JSON: ' . $e->getMessage());
        }
    }

    /**
     * NEU: API Success Response mit standardisiertem Format
     */
    public function apiSuccess(
        mixed      $data = null,
        string     $message = 'Success',
        HttpStatus $status = HttpStatus::OK
    ): Response
    {
        $response = [
            'success' => true,
            'message' => $message,
            'status' => $status->value,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $this->json($response, $status);
    }
}