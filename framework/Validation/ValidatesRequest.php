<?php

declare(strict_types=1);

namespace Framework\Validation;

use Framework\Core\ServiceContainer;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\ResponseFactory;
use RuntimeException;

/**
 * ValidatesRequest - Modern ApplicationKernel-compatible validation trait
 *
 * MODERNISIERUNGEN:
 * ✅ Union Types für Return Values (PHP 8.0+)
 * ✅ Match-Expressions für Response-Handling
 * ✅ Bessere Error-Handling
 * ✅ Type-safe Service Container Integration
 * ✅ Improved Method Signatures
 */
trait ValidatesRequest
{
    /**
     * Request validieren und Response bei Fehler zurückgeben
     */
    protected function validateWithResponse(Request $request, array $rules, ?string $connectionName = null): Validator|Response
    {
        $validator = $this->validate($request, $rules, $connectionName);

        return $validator->fails()
            ? $this->createValidationErrorResponse($request, $validator)
            : $validator;
    }

    /**
     * Request-Daten validieren ohne Exceptions
     */
    protected function validate(Request $request, array $rules, ?string $connectionName = null): Validator
    {
        $data = $request->all();
        $customMessages = $this->messages();

        $validatorFactory = $this->getValidatorFactory();

        return $validatorFactory->make($data, $rules, $customMessages, $connectionName);
    }

    /**
     * Request-Daten validieren oder Exception werfen
     *
     * @return array<string, mixed>
     * @throws ValidationFailedException
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
     * Spezifische Felder validieren
     *
     * @param array<string> $fields
     * @param array<string, string> $rules
     * @return array<string, mixed>
     * @throws ValidationFailedException
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
     * Custom Validation Messages überschreiben
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Schnelle Validierung für Required-Felder
     *
     * @param array<string> $fields
     * @return array<string, mixed>
     */
    protected function requireFields(Request $request, array $fields): array
    {
        $rules = array_fill_keys($fields, 'required');
        return $this->validateOrFail($request, $rules);
    }

    /**
     * Email-Feld validieren
     *
     * @return array<string, mixed>
     */
    protected function validateEmail(Request $request, string $field = 'email'): array
    {
        return $this->validateOrFail($request, [
            $field => 'required|email'
        ]);
    }

    /**
     * Password-Confirmation validieren
     *
     * @return array<string, mixed>
     */
    protected function validatePasswordConfirmation(Request $request): array
    {
        return $this->validateOrFail($request, [
            'password' => 'required|min:8',
            'password_confirmation' => 'required|confirmed'
        ]);
    }

    /**
     * JSON-Payload validieren
     *
     * @param array<string, string> $rules
     * @return array<string, mixed>
     * @throws ValidationFailedException
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
     * Validierung mit Custom Error Messages
     *
     * @param array<string, string> $rules
     * @param array<string, string> $messages
     * @return array<string, mixed>
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
     * Validation Error Response erstellen - MODERNISIERT
     */
    private function createValidationErrorResponse(Request $request, Validator $validator): Response
    {
        $responseFactory = $this->getResponseFactory();
        $errors = $validator->errors()->toArray();

        // Modern Match-Expression für Response-Type
        return match ($request->expectsJson()) {
            true => $responseFactory->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], HttpStatus::UNPROCESSABLE_ENTITY),

            false => $responseFactory->view('errors/validation', [
                'errors' => $errors,
                'old_input' => $request->all(),
            ], HttpStatus::UNPROCESSABLE_ENTITY)
        };
    }

    /**
     * ValidatorFactory aus Service Container abrufen
     */
    private function getValidatorFactory(): ValidatorFactory
    {
        $container = $this->getServiceContainer();
        return $container->get(ValidatorFactory::class);
    }

    /**
     * ResponseFactory aus Action abrufen
     */
    private function getResponseFactory(): ResponseFactory
    {
        // Check ob Action ResponseFactory injiziert hat
        if (property_exists($this, 'responseFactory') && $this->responseFactory instanceof ResponseFactory) {
            return $this->responseFactory;
        }

        // Fallback: Aus Container abrufen
        $container = $this->getServiceContainer();
        return $container->get(ResponseFactory::class);
    }

    /**
     * Service Container aus Action abrufen
     */
    private function getServiceContainer(): ServiceContainer
    {
        // Moderne Actions sollten ServiceContainer injizieren
        if (property_exists($this, 'container') && $this->container instanceof ServiceContainer) {
            return $this->container;
        }

        throw new RuntimeException(
            'ServiceContainer not found. Actions using ValidatesRequest must inject ServiceContainer: ' . "\n" .
            'public function __construct(private readonly ServiceContainer $container, private readonly ResponseFactory $responseFactory) {}'
        );
    }
}