<?php

declare(strict_types=1);

namespace Framework\Validation;

use Framework\Database\ConnectionManager;
use InvalidArgumentException;

/**
 * Validator - Array-based validation with rule parsing and custom messages support
 */
class Validator
{
    private array $data = [];
    private array $rules = [];
    private array $customMessages = [];
    private MessageBag $errors;
    private array $validated = [];

    public function __construct(
        private readonly ?ConnectionManager $connectionManager = null
    )
    {
        $this->errors = new MessageBag();
    }

    /**
     * Factory method to create validator instance with custom messages
     */
    public static function make(
        array $data,
        array $rules = [],
        array $customMessages = [],
        ?ConnectionManager $connectionManager = null
    ): self {
        $validator = new self($connectionManager);
        $validator->data = $data;
        $validator->rules = $rules;
        $validator->customMessages = $customMessages;

        return $validator;
    }

    /**
     * Set custom messages after instantiation
     */
    public function setCustomMessages(array $customMessages): self
    {
        $this->customMessages = $customMessages;
        return $this;
    }

    /**
     * Get validation errors
     */
    public function errors(): MessageBag
    {
        if (!$this->hasRun()) {
            $this->validate();
        }

        return $this->errors;
    }

    /**
     * Check if validation has been run
     */
    private function hasRun(): bool
    {
        return !empty($this->validated) || !$this->errors->isEmpty();
    }

    /**
     * Run validation
     */
    public function validate(): self
    {
        $this->errors = new MessageBag();
        $this->validated = [];

        foreach ($this->rules as $field => $ruleString) {
            $this->validateField($field, $ruleString);
        }

        return $this;
    }

    /**
     * Validate a single field
     */
    private function validateField(string $field, string $ruleString): void
    {
        $value = $this->getValue($field);
        $rules = $this->parseRules($ruleString);
        $fieldPassed = true;

        foreach ($rules as $rule) {
            $ruleName = $rule['name'];
            $parameters = $rule['parameters'];

            if (!$this->validateRule($field, $value, $ruleName, $parameters)) {
                $fieldPassed = false;

                // Stop on first failure for this field (bail behavior)
                if ($ruleName === 'required') {
                    break;
                }
            }
        }

        // Add to validated data if field passed all rules
        if ($fieldPassed && isset($this->data[$field])) {
            $this->validated[$field] = $value;
        }
    }

    /**
     * Get value from data array (supports dot notation)
     */
    private function getValue(string $field): mixed
    {
        if (str_contains($field, '.')) {
            return $this->getNestedValue($field);
        }

        return $this->data[$field] ?? null;
    }

    /**
     * Get nested value using dot notation
     */
    private function getNestedValue(string $field): mixed
    {
        $keys = explode('.', $field);
        $value = $this->data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Parse rules string into array
     */
    private function parseRules(string $ruleString): array
    {
        $rules = [];
        $ruleParts = explode('|', $ruleString);

        foreach ($ruleParts as $rulePart) {
            $rulePart = trim($rulePart);

            if (str_contains($rulePart, ':')) {
                [$name, $params] = explode(':', $rulePart, 2);
                $parameters = explode(',', $params);
            } else {
                $name = $rulePart;
                $parameters = [];
            }

            $rules[] = [
                'name' => trim($name),
                'parameters' => array_map('trim', $parameters)
            ];
        }

        return $rules;
    }

    /**
     * Validate single rule with custom message support
     */
    private function validateRule(string $field, mixed $value, string $ruleName, array $parameters): bool
    {
        $ruleClass = $this->getRuleClass($ruleName);

        if (!class_exists($ruleClass)) {
            throw new InvalidArgumentException("Validation rule '{$ruleName}' not found");
        }

        $rule = class_exists($ruleClass) && method_exists($ruleClass, '__construct')
            ? new $ruleClass($this->connectionManager)
            : new $ruleClass();

        $passes = $rule->passes($field, $value, $parameters, $this->data);

        if (!$passes) {
            // Check for custom message first
            $customMessageKey = "{$field}.{$ruleName}";
            if (isset($this->customMessages[$customMessageKey])) {
                $message = $this->interpolateMessage($this->customMessages[$customMessageKey], $field, $value, $parameters);
            } else {
                // Fallback to rule's default message
                $message = $rule->message($field, $value, $parameters);
            }

            $this->errors->add($field, $message);
        }

        return $passes;
    }

    /**
     * Interpolate placeholders in custom messages
     */
    private function interpolateMessage(string $message, string $field, mixed $value, array $parameters): string
    {
        // Replace :field placeholder
        $message = str_replace(':field', $field, $message);

        // Replace :value placeholder
        $message = str_replace(':value', (string) $value, $message);

        // Replace parameter placeholders like :min, :max, etc.
        foreach ($parameters as $index => $parameter) {
            $message = str_replace(":{$index}", $parameter, $message);

            // Common parameter names
            $paramNames = ['min', 'max', 'size', 'table', 'column', 'confirmed'];
            if (isset($paramNames[$index])) {
                $message = str_replace(":{$paramNames[$index]}", $parameter, $message);
            }
        }

        return $message;
    }

    /**
     * Get rule class name
     */
    private function getRuleClass(string $ruleName): string
    {
        $className = str_replace('_', '', ucwords($ruleName, '_'));
        return "Framework\\Validation\\Rules\\{$className}Rule";
    }

    /**
     * Check if validation passes
     */
    public function passes(): bool
    {
        if (!$this->hasRun()) {
            $this->validate();
        }

        return $this->errors->isEmpty();
    }

    /**
     * Validate and throw exception on failure
     */
    public function validateOrFail(): array
    {
        if ($this->fails()) {
            throw new ValidationFailedException($this->errors);
        }

        return $this->validated();
    }

    /**
     * Check if validation fails
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Get validated data (only fields that passed validation)
     */
    public function validated(): array
    {
        if (!$this->hasRun()) {
            $this->validate();
        }

        return $this->validated;
    }

    /**
     * Get safe data (validated + defaults for missing fields)
     */
    public function safe(): array
    {
        if (!$this->hasRun()) {
            $this->validate();
        }

        return $this->validated;
    }

    /**
     * Add custom error message
     */
    public function addError(string $field, string $message): self
    {
        $this->errors->add($field, $message);
        return $this;
    }

    /**
     * Check if specific field has errors
     */
    public function hasError(string $field): bool
    {
        return $this->errors->has($field);
    }

    /**
     * Get first error for field
     */
    public function getFirstError(string $field): ?string
    {
        return $this->errors->first($field);
    }
}