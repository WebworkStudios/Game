<?php

declare(strict_types=1);

namespace Framework\Validation;

use Framework\Core\ServiceContainer;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use Framework\Validation\ValidatorFactory;

/**
 * ValidatesRequest - Modern ApplicationKernel-compatible validation trait
 *
 * REFACTORED: Ersetzt Application-Dependency durch ServiceContainer + ValidatorFactory
 *
 * Neue Architektur:
 * - ✅ ApplicationKernel-kompatibel
 * - ✅ Keine hard-coded Application dependency
 * - ✅ Service Container Injection
 * - ✅ Modern ValidatorFactory pattern
 * - ✅ ResponseFactory integration
 */
trait ValidatesRequest
{
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

        $validatorFactory = $this->getValidatorFactory();

        return $validatorFactory->make($data, $rules, $customMessages, $connectionName);
    }

    /**
     * Validate request data or fail with exception
     */
    protected function validateOrFail(Request $request, array $rules, ?string $connectionName = null): array
    {
        $validator = $this->validate($request, $rules, $connectionName);

        if ($validator->fails()) {
            throw new ValidationFailedException($validator->errors());
        }

        return $validator->validated();
    }

    /**
     * Validate specific fields from request
     */
    protected function validateFields(Request $request, array $fields, array $rules, ?string $connectionName = null): array
    {
        $data = $request->only($fields);
        $customMessages = $this->messages();

        $validatorFactory = $this->getValidatorFactory();
        $validator = $validatorFactory->make($data, $rules, $customMessages, $connectionName);

        if ($validator->fails()) {
            throw new ValidationFailedException($validator->errors());
        }

        return $validator->validated();
    }

    /**
     * Override this method to provide custom validation messages
     */
    protected function messages(): array
    {
        return [];
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
     * Validate JSON payload
     */
    protected function validateJson(Request $request, array $rules, ?string $connectionName = null): array
    {
        if (!$request->isJson()) {
            throw new \InvalidArgumentException('Request must have JSON content-type');
        }

        $data = $request->json();
        $customMessages = $this->messages();

        $validatorFactory = $this->getValidatorFactory();
        $validator = $validatorFactory->make($data, $rules, $customMessages, $connectionName);

        if ($validator->fails()) {
            throw new ValidationFailedException($validator->errors());
        }

        return $validator->validated();
    }

    /**
     * Validate with custom error messages
     */
    protected function validateWithMessages(Request $request, array $rules, array $messages, ?string $connectionName = null): array
    {
        $data = $request->all();

        $validatorFactory = $this->getValidatorFactory();
        $validator = $validatorFactory->make($data, $rules, $messages, $connectionName);

        if ($validator->fails()) {
            throw new ValidationFailedException($validator->errors());
        }

        return $validator->validated();
    }

    /**
     * Validate and return only validated data
     */
    protected function validated(Request $request, array $rules, ?string $connectionName = null): array
    {
        return $this->validateOrFail($request, $rules, $connectionName);
    }

    /**
     * Safe validation that returns null on failure
     */
    protected function safeValidate(Request $request, array $rules, ?string $connectionName = null): ?array
    {
        try {
            return $this->validateOrFail($request, $rules, $connectionName);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Validate multiple files
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

        $validatorFactory = $this->getValidatorFactory();
        $validator = $validatorFactory->make($fileData, $rules, $customMessages);

        if ($validator->fails()) {
            throw new ValidationFailedException($validator->errors());
        }

        return $validator->validated();
    }

    /**
     * Validate uploaded file with convenience checks
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
     * Validate file uploads
     */
    protected function validateFile(Request $request, string $field, array $rules): array
    {
        $files = $request->getFiles();

        if (!isset($files[$field])) {
            throw new \InvalidArgumentException("File field '{$field}' not found in request");
        }

        $fileData = [$field => $files[$field]];
        $customMessages = $this->messages();

        $validatorFactory = $this->getValidatorFactory();
        $validator = $validatorFactory->make($fileData, $rules, $customMessages);

        if ($validator->fails()) {
            throw new ValidationFailedException($validator->errors());
        }

        return $validator->validated();
    }

    // ===================================================================
    // PRIVATE HELPER METHODS
    // ===================================================================

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
     * Gets ValidatorFactory from Service Container
     */
    private function getValidatorFactory(): ValidatorFactory
    {
        $container = $this->getServiceContainer();
        return $container->get(ValidatorFactory::class);
    }

    /**
     * Gets ResponseFactory from Action (modern pattern)
     */
    private function getResponseFactory(): ResponseFactory
    {
        // Check if Action has ResponseFactory injected
        if (property_exists($this, 'responseFactory') && $this->responseFactory instanceof ResponseFactory) {
            return $this->responseFactory;
        }

        // Fallback: Get from container
        $container = $this->getServiceContainer();
        return $container->get(ResponseFactory::class);
    }

    /**
     * Gets Service Container from Action
     */
    private function getServiceContainer(): ServiceContainer
    {
        // Modern Actions should inject ServiceContainer
        if (property_exists($this, 'container') && $this->container instanceof ServiceContainer) {
            return $this->container;
        }

        // Alternative: Check for direct ValidatorFactory injection
        if (property_exists($this, 'validatorFactory') && $this->validatorFactory instanceof ValidatorFactory) {
            // Create minimal container-like access
            throw new \RuntimeException(
                'Direct ValidatorFactory injection not yet supported. ' .
                'Actions using ValidatesRequest must inject ServiceContainer: ' . "\n" .
                'public function __construct(private readonly ServiceContainer $container) {}'
            );
        }

        throw new \RuntimeException(
            'ServiceContainer not found. Actions using ValidatesRequest must inject ServiceContainer: ' . "\n" .
            'public function __construct(private readonly ServiceContainer $container, private readonly ResponseFactory $responseFactory) {}'
        );
    }
}