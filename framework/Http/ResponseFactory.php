<?php
declare(strict_types=1);

namespace Framework\Http;

use Framework\Security\Session;
use Framework\Templating\TemplateEngine;
use Framework\Templating\ViewRenderer;
use Framework\Templating\Utils\JsonUtility;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * ResponseFactory - UPDATED: Flash-Messages vollständig implementiert
 */
readonly class ResponseFactory
{
    public function __construct(
        private ViewRenderer   $viewRenderer,
        private TemplateEngine $engine,
        private ?Session      $session = null  // Optional dependency injection
    ) {}

    public function response(string $body = '', HttpStatus $status = HttpStatus::OK, array $headers = []): Response
    {
        return new Response($status, $headers, $body);
    }

    public function view(string $template, array $data = [], HttpStatus $status = HttpStatus::OK): Response
    {
        try {
            return $this->viewRenderer->render($template, $data, $status);
        } catch (Throwable $e) {
            $content = $this->engine->render($template, $data);
            return $this->html($content, $status);
        }
    }

    public function html(string $content, HttpStatus $status = HttpStatus::OK, array $headers = []): Response
    {
        return new Response(
            $status,
            [...$headers, 'Content-Type' => 'text/html; charset=UTF-8'],
            $content
        );
    }

    public function json(
        array|object $data,
        HttpStatus   $status = HttpStatus::OK,
        array        $headers = [],
        int          $flags = JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ): Response {
        try {
            $json = JsonUtility::encode($data, $flags);
            return new Response(
                $status,
                [...$headers, 'Content-Type' => 'application/json; charset=utf-8'],
                $json
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Failed to encode JSON: ' . $e->getMessage());
        }
    }

    public function redirect(string $url, HttpStatus $status = HttpStatus::FOUND): Response
    {
        return new Response($status, ['Location' => $url]);
    }

    public function redirectBack(Request $request, string $fallback = '/'): Response
    {
        $referer = $request->getReferer() ?? $fallback;
        return $this->redirect($referer);
    }

    /**
     * IMPLEMENTIERT: Redirect mit Flash-Message
     *
     * Unterstützt die Standard-Flash-Message-Types:
     * - success: Erfolgreiche Aktionen
     * - error: Fehlermeldungen
     * - warning: Warnungen
     * - info: Informationen
     */
    public function redirectWithMessage(
        string $url,
        string $message,
        string $type = 'success'
    ): Response {
        // Flash-Message nur setzen wenn Session verfügbar
        if ($this->session !== null) {
            $this->session->flash($type, $message);
        }

        return $this->redirect($url);
    }

    /**
     * NEU: Redirect mit mehreren Flash-Messages
     */
    public function redirectWithMessages(string $url, array $messages): Response
    {
        if ($this->session !== null) {
            foreach ($messages as $type => $message) {
                $this->session->flash($type, $message);
            }
        }

        return $this->redirect($url);
    }

    /**
     * NEU: Redirect zurück mit Flash-Message
     */
    public function redirectBackWithMessage(
        Request $request,
        string  $message,
        string  $type = 'success',
        string  $fallback = '/'
    ): Response {
        if ($this->session !== null) {
            $this->session->flash($type, $message);
        }

        return $this->redirectBack($request, $fallback);
    }

    /**
     * NEU: Redirect mit Success-Message (convenience method)
     */
    public function redirectWithSuccess(string $url, string $message): Response
    {
        return $this->redirectWithMessage($url, $message, 'success');
    }

    /**
     * NEU: Redirect mit Error-Message (convenience method)
     */
    public function redirectWithError(string $url, string $message): Response
    {
        return $this->redirectWithMessage($url, $message, 'error');
    }

    /**
     * NEU: Redirect mit Warning-Message (convenience method)
     */
    public function redirectWithWarning(string $url, string $message): Response
    {
        return $this->redirectWithMessage($url, $message, 'warning');
    }

    /**
     * NEU: Redirect mit Info-Message (convenience method)
     */
    public function redirectWithInfo(string $url, string $message): Response
    {
        return $this->redirectWithMessage($url, $message, 'info');
    }

    /**
     * ERWEITERT: Form Response mit Flash-Message Support
     */
    public function formResponse(
        Request    $request,
        string     $template,
        array      $data = [],
        array      $errors = [],
        HttpStatus $status = HttpStatus::OK,
        ?string    $flashMessage = null,
        string     $flashType = 'error'
    ): Response {
        // Flash-Message setzen falls vorhanden
        if ($flashMessage && $this->session !== null) {
            $this->session->flash($flashType, $flashMessage);
        }

        if ($request->expectsJson()) {
            if (!empty($errors)) {
                return $this->json([
                    'success' => false,
                    'message' => $flashMessage ?? 'Validation failed',
                    'errors' => $errors
                ], HttpStatus::UNPROCESSABLE_ENTITY);
            }
            return $this->json([
                'success' => true,
                'message' => $flashMessage ?? '',
                'data' => $data
            ], $status);
        }

        // HTML Response mit Flash-Messages und Errors
        $templateData = [
            ...$data,
            'errors' => $errors,
            'old_input' => $request->all()
        ];

        return $this->view($template, $templateData, $status);
    }

    public function notFound(string $message = 'Not Found'): Response
    {
        return new Response(HttpStatus::NOT_FOUND, [], $message);
    }

    public function serverError(string $message = 'Internal Server Error'): Response
    {
        return new Response(HttpStatus::INTERNAL_SERVER_ERROR, [], $message);
    }

    public function forbidden(string $message = 'Forbidden'): Response
    {
        return new Response(HttpStatus::FORBIDDEN, [], $message);
    }

    public function unauthorized(string $message = 'Unauthorized'): Response
    {
        return new Response(HttpStatus::UNAUTHORIZED, [], $message);
    }

    public function validationError(array $errors, string $message = 'Validation failed'): Response
    {
        return $this->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], HttpStatus::UNPROCESSABLE_ENTITY);
    }
}