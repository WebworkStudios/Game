<?php

declare(strict_types=1);

namespace Framework\Validation;

use Framework\Core\Application;
use Framework\Http\HttpStatus;
use Framework\Http\Request;
use Framework\Http\Response;

/**
 * ValidatesRequests - Trait for easy validation in Actions with Custom Messages Support
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
     * Validate request and return JSON error response on failure
     *
     * Note: This method requires the Action to have a ResponseFactory dependency.
     * Actions using this method should inject ResponseFactory in their constructor:
     *
     * public function __construct(private readonly ResponseFactory $responseFactory) {}
     */
    protected function validateWithResponse(Request $request, array $rules, ?string $connectionName = null): Validator|Response
    {
        $validator = $this->validate($request, $rules, $connectionName);

        if ($validator->fails()) {
            // Check if Action has ResponseFactory injected
            if (property_exists($this, 'responseFactory') && $this->responseFactory instanceof \Framework\Http\ResponseFactory) {
                // Return JSON response for API requests
                if ($request->expectsJson()) {
                    return $this->responseFactory->json([
                        'message' => 'The given data was invalid.',
                        'errors' => $validator->errors()->toArray()
                    ], HttpStatus::UNPROCESSABLE_ENTITY);
                }

                // For web requests, you might want to redirect back with errors
                // This would need session flash functionality
                return $this->responseFactory->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray()
                ], HttpStatus::UNPROCESSABLE_ENTITY);
            }

            // Fallback: throw ValidationFailedException
            throw new ValidationFailedException($validator->errors());
        }

        return $validator;
    }

    /**
     * Validate request data with custom messages support
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
     * Get custom validation messages for this action
     *
     * Override in child classes to provide localized or custom error messages.
     *
     * @return array Array of 'field.rule' => 'Custom message' mappings
     *
     * Example:
     * protected function messages(): array {
     *     return [
     *         'email.required' => 'Bitte geben Sie eine E-Mail-Adresse ein.',
     *         'email.email' => 'Die E-Mail-Adresse muss gültig sein.',
     *         'password.min' => 'Das Passwort muss mindestens :min Zeichen haben.',
     *         'password.confirmed' => 'Die Passwort-Bestätigung stimmt nicht überein.',
     *         'name.required' => 'Der Name ist erforderlich.',
     *         'name.max' => 'Der Name darf maximal :max Zeichen haben.',
     *     ];
     * }
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Auto-validate using rules() method with custom messages
     */
    protected function autoValidate(Request $request, ?string $connectionName = null): Validator
    {
        $rules = $this->rules($request);

        if (empty($rules)) {
            throw new \RuntimeException('No validation rules defined. Override rules() method or use validate() directly.');
        }

        return $this->validate($request, $rules, $connectionName);
    }

    /**
     * Get validation rules for this action (override in child classes)
     */
    protected function rules(Request $request): array
    {
        return [];
    }
}