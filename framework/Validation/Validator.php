<?php

declare(strict_types=1);

namespace Framework\Validation;

use Framework\Database\ConnectionManager;

/**
 * Validator - Array-based validation with rule parsing
 */
class Validator
{
    private array $data = [];
    private array $rules = [];
    private MessageBag $errors;
    private array $validated = [];

    public function __construct(
        private readonly ?ConnectionManager $connectionManager = null
    ) {
        $this->errors = new MessageBag();
    }

    /**
     * Factory method to create validator instance
     */
    public static function make(array $data, array $rules = [], ?ConnectionManager $connectionManager = null): self
    {
        $validator = new self($connectionManager);
        $validator->data = $data;
        $validator->rules = $rules;

        return $validator;
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
     * Check if validation fails
     */
    public function fails(): bool
    {
        return !$this->passes();
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
     * Validate single rule
     */
    private function validateRule(string $field, mixed $value, string $ruleName, array $parameters): bool
    {
        $ruleClass = $this->getRuleClass($ruleName);

        if (!class_exists($ruleClass)) {
            throw new ValidationException("Validation rule '{$ruleName}' not found");
        }

        // Database rules need ConnectionManager, others don't
        $needsConnection = in_array($ruleName, ['unique', 'exists'], true);
        $rule = $needsConnection
            ? new $ruleClass($this->connectionManager)
            : new $ruleClass();

        $passes = $rule->passes($field, $value, $parameters, $this->data);

        if (!$passes) {
            $message = $rule->message($field, $value, $parameters);
            $this->errors->add($field, $message);
        }

        return $passes;
    }

    /**
     * Parse rule string into array of rules
     */
    private function parseRules(string $ruleString): array
    {
        $rules = [];
        $ruleParts = explode('|', $ruleString);

        foreach ($ruleParts as $rule) {
            $rule = trim($rule);

            if (str_contains($rule, ':')) {
                [$name, $paramString] = explode(':', $rule, 2);
                $parameters = explode(',', $paramString);
            } else {
                $name = $rule;
                $parameters = [];
            }

            $rules[] = [
                'name' => $name,
                'parameters' => array_map('trim', $parameters)
            ];
        }

        return $rules;
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
            if (!is_array($value) || !isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
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
     * Check if validation has been run
     */
    private function hasRun(): bool
    {
        return !empty($this->validated) || !$this->errors->isEmpty();
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