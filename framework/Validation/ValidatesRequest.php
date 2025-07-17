<?php

declare(strict_types=1);

namespace Framework\Validation;

use Framework\Core\Application;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;

/**
 * ValidatesRequests - Trait for easy validation in Actions
 *
 * KORRIGIERT: Verwendet korrekte Request-Methoden (getFiles statt files)
 */
trait ValidatesRequest
{
    /**
     * Validate request data or fail with exception
     */
    protected function validateOrFail(Request $request, array $rules, ?string $connectionName = null): array
    {
        $data = $request->all();
        $customMessages = $this->messages();

        if (property_exists($this, 'app') && $this->app instanceof Application) {
            return $this->app->validateOrFail($data, $rules, $customMessages, $connectionName);
        }

        throw new \RuntimeException('Application instance not found. Ensure $app property exists in Action.');
    }

    /**
     * Validate request and return Response on failure
     */
    protected function validateWithResponse(Request $request, array $rules, ?string $connectionName = null): Validator|Response
    {
        $validator = $this->validate($request, $rules, $connectionName);

        if ($validator->fails()) {
            return $this->createValidationErrorResponse($request, $validator);
        }

        return $validator;
    }

    /**
     * Validate request data without throwing exceptions
     */
    protected function validate(Request $request, array $rules, ?string $connectionName = null): Validator
    {
        $data = $request->all();
        $customMessages = $this->messages();

        if (property_exists($this, 'app') && $this->app instanceof Application) {
            return $this->app->validate($data, $rules, $customMessages, $connectionName);
        }

        throw new \RuntimeException('Application instance not found. Ensure $app property exists in Action.');
    }

    /**
     * Erstellt einheitliche Validation Error Response
     */
    private function createValidationErrorResponse(Request $request, Validator $validator): Response
    {
        $responseFactory = $this->getResponseFactory();
        $errors = $validator->errors();

        // JSON Response for API requests
        if ($request->expectsJson()) {
            return $responseFactory->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], HttpStatus::UNPROCESSABLE_ENTITY);
        }

        // HTML Response with error display
        return $responseFactory->view('errors/validation', [
            'errors' => $errors,
            'old_input' => $request->all(),
        ], HttpStatus::UNPROCESSABLE_ENTITY);
    }

    /**
     * Holt ResponseFactory aus Action
     */
    private function getResponseFactory(): ResponseFactory
    {
        // Check if Action has ResponseFactory injected
        if (property_exists($this, 'responseFactory') && $this->responseFactory instanceof ResponseFactory) {
            return $this->responseFactory;
        }

        // Fallback: Get from container if available
        if (property_exists($this, 'container') && method_exists($this->container, 'get')) {
            return $this->container->get(ResponseFactory::class);
        }

        throw new \RuntimeException(
            'ResponseFactory not found. Actions using ValidatesRequest must inject ResponseFactory:' . "\n" .
            'public function __construct(private readonly ResponseFactory $responseFactory) {}'
        );
    }

    /**
     * Override this method to provide custom validation messages
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Validate specific fields from request
     */
    protected function validateFields(Request $request, array $fields, array $rules, ?string $connectionName = null): array
    {
        $data = $request->only($fields);
        $customMessages = $this->messages();

        if (property_exists($this, 'app') && $this->app instanceof Application) {
            return $this->app->validateOrFail($data, $rules, $customMessages, $connectionName);
        }

        throw new \RuntimeException('Application instance not found. Ensure $app property exists in Action.');
    }

    /**
     * KORRIGIERT: Validate file uploads - verwendet getFiles() statt files()
     */
    protected function validateFile(Request $request, string $field, array $rules): array
    {
        $files = $request->getFiles();

        if (!isset($files[$field])) {
            throw new \InvalidArgumentException("File field '{$field}' not found in request");
        }

        $fileData = [$field => $files[$field]];
        $customMessages = $this->messages();

        if (property_exists($this, 'app') && $this->app instanceof Application) {
            return $this->app->validateOrFail($fileData, $rules, $customMessages);
        }

        throw new \RuntimeException('Application instance not found. Ensure $app property exists in Action.');
    }

    /**
     * NEU: Validate multiple files
     */
    protected function validateFiles(Request $request, array $fileFields, array $rules): array
    {
        $files = $request->getFiles();
        $fileData = [];

        foreach ($fileFields as $field) {
            if (!isset($files[$field])) {
                throw new \InvalidArgumentException("File field '{$field}' not found in request");
            }
            $fileData[$field] = $files[$field];
        }

        $customMessages = $this->messages();

        if (property_exists($this, 'app') && $this->app instanceof Application) {
            return $this->app->validateOrFail($fileData, $rules, $customMessages);
        }

        throw new \RuntimeException('Application instance not found. Ensure $app property exists in Action.');
    }

    /**
     * NEU: Validate uploaded file with convenience checks
     */
    protected function validateUploadedFile(Request $request, string $field, array $allowedTypes = [], int $maxSize = 0): array
    {
        if (!$request->hasFile($field)) {
            throw new \InvalidArgumentException("No file uploaded for field '{$field}'");
        }

        $rules = ['required', 'file'];

        // Add file type validation
        if (!empty($allowedTypes)) {
            $rules[] = 'mimes:' . implode(',', $allowedTypes);
        }

        // Add max size validation (in KB)
        if ($maxSize > 0) {
            $rules[] = 'max:' . $maxSize;
        }

        return $this->validateFile($request, $field, [$field => $rules]);
    }

    /**
     * Quick validation for common patterns
     */
    protected function requireFields(Request $request, array $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $rules[$field] = 'required';
        }

        return $this->validateOrFail($request, $rules);
    }

    /**
     * Validate email field
     */
    protected function validateEmail(Request $request, string $field = 'email'): array
    {
        return $this->validateOrFail($request, [
            $field => 'required|email'
        ]);
    }

    /**
     * Validate password confirmation
     */
    protected function validatePasswordConfirmation(Request $request): array
    {
        return $this->validateOrFail($request, [
            'password' => 'required|min:8',
            'password_confirmation' => 'required|same:password'
        ]);
    }

    /**
     * NEU: Validate JSON payload
     */
    protected function validateJson(Request $request, array $rules, ?string $connectionName = null): array
    {
        if (!$request->isJson()) {
            throw new \InvalidArgumentException('Request must have JSON content-type');
        }

        $data = $request->json();
        $customMessages = $this->messages();

        if (property_exists($this, 'app') && $this->app instanceof Application) {
            return $this->app->validateOrFail($data, $rules, $customMessages, $connectionName);
        }

        throw new \RuntimeException('Application instance not found. Ensure $app property exists in Action.');
    }

    /**
     * NEU: Validate with custom error messages
     */
    protected function validateWithMessages(Request $request, array $rules, array $messages, ?string $connectionName = null): array
    {
        $data = $request->all();

        if (property_exists($this, 'app') && $this->app instanceof Application) {
            return $this->app->validateOrFail($data, $rules, $messages, $connectionName);
        }

        throw new \RuntimeException('Application instance not found. Ensure $app property exists in Action.');
    }

    /**
     * NEU: Validate and return only validated data
     */
    protected function validated(Request $request, array $rules, ?string $connectionName = null): array
    {
        $validator = $this->validate($request, $rules, $connectionName);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $validator->errors()));
        }

        return $validator->validated();
    }

    /**
     * NEU: Safe validation that returns null on failure
     */
    protected function safeValidate(Request $request, array $rules, ?string $connectionName = null): ?array
    {
        try {
            return $this->validateOrFail($request, $rules, $connectionName);
        } catch (\Exception) {
            return null;
        }
    }
}