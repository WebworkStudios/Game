<?php


declare(strict_types=1);

namespace Framework\Validation;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Core\Application;

/**
 * ValidatesRequests - Trait for easy validation in Actions
 */
trait ValidatesRequest
{
    /**
     * Validate request data
     */
    protected function validate(Request $request, array $rules, ?string $connectionName = null): Validator
    {
        $data = $request->all();

        if (property_exists($this, 'app') && $this->app instanceof Application) {
            return $this->app->validate($data, $rules, $connectionName);
        }

        throw new \RuntimeException('Application instance not found. Ensure $app property exists in Action.');
    }

    /**
     * Validate request data or fail with exception
     */
    protected function validateOrFail(Request $request, array $rules, ?string $connectionName = null): array
    {
        $data = $request->all();

        if (property_exists($this, 'app') && $this->app instanceof Application) {
            return $this->app->validateOrFail($data, $rules, $connectionName);
        }

        throw new \RuntimeException('Application instance not found. Ensure $app property exists in Action.');
    }

    /**
     * Validate request and return JSON error response on failure
     */
    protected function validateWithResponse(Request $request, array $rules, ?string $connectionName = null): Validator|Response
    {
        $validator = $this->validate($request, $rules, $connectionName);

        if ($validator->fails()) {
            // Return JSON response for API requests
            if ($request->expectsJson()) {
                return Response::json([
                    'message' => 'The given data was invalid.',
                    'errors' => $validator->errors()->toArray()
                ], 422);
            }

            // For web requests, you might want to redirect back with errors
            // This would need session flash functionality
            return Response::json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()->toArray()
            ], 422);
        }

        return $validator;
    }

    /**
     * Get validation rules for this action (override in child classes)
     */
    protected function rules(Request $request): array
    {
        return [];
    }

    /**
     * Get custom validation messages (override in child classes)
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Auto-validate using rules() method
     */
    protected function autoValidate(Request $request, ?string $connectionName = null): Validator
    {
        $rules = $this->rules($request);

        if (empty($rules)) {
            throw new \RuntimeException('No validation rules defined. Override rules() method or use validate() directly.');
        }

        return $this->validate($request, $rules, $connectionName);
    }
}