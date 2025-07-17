<?php
declare(strict_types=1);

namespace Framework\Validation;

use Framework\Database\ConnectionManager;
use Framework\Validation\Rules\RuleInterface;
use InvalidArgumentException;

/**
 * Validator - Array-basierte Validierung mit Rule-Parsing und Custom Messages
 *
 * MODERNISIERUNGEN:
 * ✅ Typed Properties mit Array-Shapes
 * ✅ Nullable-Unterstützung in Constructor
 * ✅ Match-Expression für Rule-Parsing (PHP 8.0+)
 * ✅ Improved String-Interpolation
 * ✅ Performance-Optimierungen
 */
class Validator
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<string, string> */
    private array $rules = [];

    /** @var array<string, string> */
    private array $customMessages = [];

    private MessageBag $errors;

    /** @var array<string, mixed> */
    private array $validated = [];

    public function __construct(
        private readonly ?ConnectionManager $connectionManager = null
    ) {
        $this->errors = new MessageBag();
    }

    /**
     * Factory Method für Validator mit Custom Messages
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     * @param array<string, string> $customMessages
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
     * Custom Messages nach Instanziierung setzen
     */
    public function setCustomMessages(array $customMessages): self
    {
        $this->customMessages = $customMessages;
        return $this;
    }

    /**
     * Validation Errors abrufen
     */
    public function errors(): MessageBag
    {
        if (!$this->hasRun()) {
            $this->validate();
        }

        return $this->errors;
    }

    /**
     * Prüfen ob Validation bereits ausgeführt wurde
     */
    private function hasRun(): bool
    {
        return $this->validated !== [] || !$this->errors->isEmpty();
    }

    /**
     * Validation ausführen
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
     * Einzelnes Feld validieren
     */
    private function validateField(string $field, string $ruleString): void
    {
        $value = $this->getValue($field);
        $rules = $this->parseRules($ruleString);
        $fieldPassed = true;

        foreach ($rules as $rule) {
            ['name' => $ruleName, 'parameters' => $parameters] = $rule;

            if (!$this->validateRule($field, $value, $ruleName, $parameters)) {
                $fieldPassed = false;

                // Stop bei erstem Fehler für dieses Feld (bail behavior)
                if ($ruleName === 'required') {
                    break;
                }
            }
        }

        // Zu validated data hinzufügen wenn alle Rules bestanden
        if ($fieldPassed && array_key_exists($field, $this->data)) {
            $this->validated[$field] = $value;
        }
    }

    /**
     * Wert aus Data-Array abrufen (unterstützt Dot-Notation)
     */
    private function getValue(string $field): mixed
    {
        return str_contains($field, '.')
            ? $this->getNestedValue($field)
            : $this->data[$field] ?? null;
    }

    /**
     * Verschachtelte Werte mit Dot-Notation abrufen
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
     * Rules-String in Array parsen - MODERNISIERT
     *
     * @return array<array{name: string, parameters: array<string>}>
     */
    private function parseRules(string $ruleString): array
    {
        $rules = [];
        $ruleParts = explode('|', $ruleString);

        foreach ($ruleParts as $rulePart) {
            $rulePart = trim($rulePart);

            [$name, $params] = str_contains($rulePart, ':')
                ? explode(':', $rulePart, 2)
                : [$rulePart, ''];

            $parameters = $params !== ''
                ? array_map('trim', explode(',', $params))
                : [];

            $rules[] = [
                'name' => trim($name),
                'parameters' => $parameters
            ];
        }

        return $rules;
    }

    /**
     * Einzelne Rule validieren mit Custom Message Support
     *
     * @param array<string> $parameters
     */
    private function validateRule(string $field, mixed $value, string $ruleName, array $parameters): bool
    {
        $ruleClass = $this->getRuleClass($ruleName);

        if (!class_exists($ruleClass)) {
            throw new InvalidArgumentException("Validation rule '{$ruleName}' not found");
        }

        // Moderne Reflection-basierte Instanziierung
        $rule = (new \ReflectionClass($ruleClass))->hasMethod('__construct')
            ? new $ruleClass($this->connectionManager)
            : new $ruleClass();

        $passes = $rule->passes($field, $value, $parameters, $this->data);

        if (!$passes) {
            $message = $this->resolveErrorMessage($field, $value, $ruleName, $parameters, $rule);
            $this->errors->add($field, $message);
        }

        return $passes;
    }

    /**
     * Error-Message auflösen - Custom Messages haben Priorität
     */
    private function resolveErrorMessage(
        string $field,
        mixed $value,
        string $ruleName,
        array $parameters,
        RuleInterface $rule
    ): string {
        // Zuerst Custom Message prüfen
        $customMessageKey = "{$field}.{$ruleName}";
        if (isset($this->customMessages[$customMessageKey])) {
            return $this->interpolateMessage(
                $this->customMessages[$customMessageKey],
                $field,
                $value,
                $parameters
            );
        }

        // Fallback zur Rule's Default Message
        return $rule->message($field, $value, $parameters);
    }

    /**
     * Rule-Class-Name ermitteln
     */
    private function getRuleClass(string $ruleName): string
    {
        $className = str_replace('_', '', ucwords($ruleName, '_'));
        return "Framework\\Validation\\Rules\\{$className}Rule";
    }

    /**
     * Prüfen ob Validation erfolgreich war
     */
    public function passes(): bool
    {
        if (!$this->hasRun()) {
            $this->validate();
        }

        return $this->errors->isEmpty();
    }

    /**
     * Prüfen ob Validation fehlgeschlagen ist
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Validierte Daten abrufen
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        if (!$this->hasRun()) {
            $this->validate();
        }

        return $this->validated;
    }

    /**
     * Platzhalter in Custom Messages interpolieren - MODERNISIERT
     *
     * @param array<string> $parameters
     */
    private function interpolateMessage(string $message, string $field, mixed $value, array $parameters): string
    {
        // String-Interpolation mit modernen PHP-Features
        $replacements = [
            ':field' => $field,
            ':value' => (string) $value,
        ];

        // Parameter-Platzhalter hinzufügen (:min, :max, etc.)
        foreach ($parameters as $index => $param) {
            $key = match ($index) {
                0 => ':min',
                1 => ':max',
                default => ":param{$index}"
            };
            $replacements[$key] = $param;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }
}