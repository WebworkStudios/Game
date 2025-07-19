<?php
declare(strict_types=1);

namespace Framework\Http;

use Framework\Templating\TemplateEngine;
use Framework\Templating\ViewRenderer;
use Framework\Templating\Utils\JsonUtility;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * ResponseFactory - Modernisiert mit JsonUtility Integration
 *
 * UPDATED: Erweiterte JSON-Response-Funktionalität und Template-Integration
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
    // STANDARD RESPONSE METHODS (bestehend)
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
            [...$headers, 'Content-Type' => 'text/html; charset=UTF-8'],
            $content
        );
    }

    public function ok(string $body = 'OK', array $headers = []): Response
    {
        return new Response(HttpStatus::OK, $headers, $body);
    }

    // ===================================================================
    // MODERNISIERTE JSON RESPONSE METHODS
    // ===================================================================

    /**
     * MODERNISIERT: Standard JSON Response mit JsonUtility (keine Duplikate)
     */
    public function json(
        array|object $data,
        HttpStatus   $status = HttpStatus::OK,
        array        $headers = [],
        int          $flags = JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ): Response
    {
        try {
            $json = JsonUtility::encode($data, $flags);
            return new Response(
                $status,
                [...$headers, 'Content-Type' => 'application/json; charset=utf-8'],
                $json
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Failed to encode JSON: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * HINZUGEFÜGT: Pretty JSON für Debug/Development
     */
    public function jsonPretty(array|object $data, HttpStatus $status = HttpStatus::OK): Response
    {
        try {
            $json = JsonUtility::prettyEncode($data);
            return new Response(
                $status,
                ['Content-Type' => 'application/json; charset=utf-8'],
                $json
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Failed to encode pretty JSON: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * HINZUGEFÜGT: API-Standard Response Format
     */
    public function apiResponse(
        mixed      $data = null,
        string     $message = '',
        HttpStatus $status = HttpStatus::OK,
        array      $meta = []
    ): Response
    {
        $response = [
            'success' => $status->isSuccessful(),
            'status' => $status->value,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $this->json($response, $status);
    }

    /**
     * HINZUGEFÜGT: Success Responses
     */
    public function success(mixed $data = null, string $message = 'Success'): Response
    {
        return $this->apiResponse($data, $message, HttpStatus::OK);
    }

    public function created(mixed $data = null, string $message = 'Created'): Response
    {
        return $this->apiResponse($data, $message, HttpStatus::CREATED);
    }

    public function accepted(mixed $data = null, string $message = 'Accepted'): Response
    {
        return $this->apiResponse($data, $message, HttpStatus::ACCEPTED);
    }

    public function noContent(): Response
    {
        return new Response(HttpStatus::NO_CONTENT);
    }

    // ===================================================================
    // ERROR RESPONSE METHODS (erweitert)
    // ===================================================================

    /**
     * MODERNISIERT: Error Response mit JsonUtility
     */
    public function error(
        string     $message,
        HttpStatus $status = HttpStatus::BAD_REQUEST,
        array      $errors = [],
        mixed      $code = null
    ): Response
    {
        $response = [
            'success' => false,
            'error' => $message,
            'status' => $status->value
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        if ($code !== null) {
            $response['code'] = $code;
        }

        return $this->json($response, $status);
    }

    public function badRequest(string $message = 'Bad Request', array $errors = []): Response
    {
        return $this->error($message, HttpStatus::BAD_REQUEST, $errors, 'BAD_REQUEST');
    }

    public function unauthorized(string $message = 'Unauthorized'): Response
    {
        return $this->error($message, HttpStatus::UNAUTHORIZED, [], 'UNAUTHORIZED');
    }

    public function forbidden(string $message = 'Forbidden'): Response
    {
        return $this->error($message, HttpStatus::FORBIDDEN, [], 'FORBIDDEN');
    }

    public function notFound(string $message = 'Resource not found'): Response
    {
        return $this->error($message, HttpStatus::NOT_FOUND, [], 'NOT_FOUND');
    }

    public function methodNotAllowed(string $message = 'Method not allowed'): Response
    {
        return $this->error($message, HttpStatus::METHOD_NOT_ALLOWED, [], 'METHOD_NOT_ALLOWED');
    }

    public function unprocessableEntity(string $message = 'Validation failed', array $errors = []): Response
    {
        return $this->error($message, HttpStatus::UNPROCESSABLE_ENTITY, $errors, 'VALIDATION_ERROR');
    }

    public function internalServerError(string $message = 'Internal server error'): Response
    {
        return $this->error($message, HttpStatus::INTERNAL_SERVER_ERROR, [], 'INTERNAL_ERROR');
    }

    // ===================================================================
    // VALIDATION & FORM RESPONSES
    // ===================================================================

    /**
     * HINZUGEFÜGT: Validation Error Response
     */
    public function validationError(array $errors, string $message = 'Validation failed'): Response
    {
        return $this->unprocessableEntity($message, $errors);
    }

    /**
     * HINZUGEFÜGT: Form Response (HTML oder JSON basierend auf Request)
     */
    public function formResponse(
        Request    $request,
        string     $template,
        array      $data = [],
        array      $errors = [],
        HttpStatus $status = HttpStatus::OK
    ): Response
    {
        if ($request->expectsJson()) {
            if (!empty($errors)) {
                return $this->validationError($errors);
            }
            return $this->apiResponse($data, '', $status);
        }

        // HTML Response
        $templateData = [
            ...$data,
            'errors' => $errors,
            'old_input' => $request->all()
        ];

        return $this->view($template, $templateData, $status);
    }

    // ===================================================================
    // REDIRECT RESPONSES
    // ===================================================================

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
     * HINZUGEFÜGT: Redirect mit Flash-Messages (für Session-basierte Apps)
     */
    public function redirectWithMessage(
        string $url,
        string $message,
        string $type = 'success'
    ): Response
    {
        // TODO: Session Flash implementierung hinzufügen wenn Session-System verfügbar
        return $this->redirect($url);
    }

    // ===================================================================
    // FILE & DOWNLOAD RESPONSES
    // ===================================================================

    public function download(
        string $content,
        string $filename,
        string $contentType = 'application/octet-stream'
    ): Response
    {
        return new Response(
            HttpStatus::OK,
            [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => (string)strlen($content),
                'Cache-Control' => 'must-revalidate',
                'Pragma' => 'public',
            ],
            $content
        );
    }

    /**
     * HINZUGEFÜGT: JSON-File Download
     */
    public function jsonDownload(array|object $data, string $filename = 'data.json'): Response
    {
        $json = JsonUtility::prettyEncode($data);
        return $this->download($json, $filename, 'application/json');
    }

    // ===================================================================
    // API-SPECIFIC RESPONSES
    // ===================================================================

    /**
     * HINZUGEFÜGT: Paginated API Response
     */
    public function paginated(
        array  $items,
        int    $total,
        int    $page,
        int    $perPage,
        string $message = ''
    ): Response
    {
        $totalPages = (int)ceil($total / $perPage);

        $meta = [
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total),
                'has_more' => $page < $totalPages
            ]
        ];

        return $this->apiResponse($items, $message, HttpStatus::OK, $meta);
    }

    /**
     * HINZUGEFÜGT: API Resource Response
     */
    public function resource(mixed $resource, string $type = 'resource'): Response
    {
        return $this->apiResponse($resource, '', HttpStatus::OK, ['type' => $type]);
    }

    /**
     * HINZUGEFÜGT: API Collection Response
     */
    public function collection(array $items, string $type = 'collection'): Response
    {
        return $this->apiResponse($items, '', HttpStatus::OK, [
            'type' => $type,
            'count' => count($items)
        ]);
    }

    // ===================================================================
    // TEMPLATE-SPECIFIC METHODS
    // ===================================================================

    /**
     * HINZUGEFÜGT: Template mit JSON-Daten für JavaScript
     */
    public function viewWithJson(
        string     $template,
        array      $data = [],
        array      $jsonData = [],
        HttpStatus $status = HttpStatus::OK
    ): Response
    {
        // JSON-Daten für JavaScript-Integration vorbereiten
        $processedJsonData = [];
        foreach ($jsonData as $key => $value) {
            $processedJsonData[$key] = JsonUtility::encodeForJavaScript($value);
        }

        $templateData = [
            ...$data,
            'json_data' => $processedJsonData
        ];

        return $this->view($template, $templateData, $status);
    }

    /**
     * HINZUGEFÜGT: Debug-Template für Development
     */
    public function debug(array $data, string $title = 'Debug Information'): Response
    {
        $debugData = [
            'title' => $title,
            'data' => $data,
            'json_data' => JsonUtility::prettyEncode($data),
            'debug_info' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'memory_usage' => memory_get_usage(true),
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
            ]
        ];

        return $this->json($debugData);
    }

    // ===================================================================
    // CONDITIONAL RESPONSES
    // ===================================================================

    /**
     * HINZUGEFÜGT: Conditional Response basierend auf Request-Type
     */
    public function conditional(
        Request    $request,
        string     $template,
        array      $data,
        HttpStatus $status = HttpStatus::OK
    ): Response
    {
        if ($request->expectsJson()) {
            return $this->apiResponse($data, '', $status);
        }

        return $this->view($template, $data, $status);
    }

    /**
     * HINZUGEFÜGT: CORS-enabled JSON Response
     */
    public function jsonWithCors(
        array|object $data,
        HttpStatus   $status = HttpStatus::OK,
        array        $corsOptions = []
    ): Response
    {
        $defaults = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
        ];

        $headers = [...$defaults, ...$corsOptions];
        return $this->json($data, $status, $headers);
    }
}