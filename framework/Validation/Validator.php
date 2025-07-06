<?php
/**
 * Validation Framework
 * High-performance input validation with unified database validation
 */

declare(strict_types=1);

namespace Framework\Validation;

use Framework\Database\ConnectionPool;
use Framework\Security\RateLimiter;
use Throwable;

class Validator
{
    private ConnectionPool $db;
    private ?RateLimiter $rateLimiter;

    // Rule cache for performance
    private static array $compiledRules = [];
    private static array $queryCache = [];

    // Performance counters
    public array $stats {
        get => [
            'rules_executed' => $this->rulesExecuted,
            'cache_hits' => $this->cacheHits,
            'db_queries' => $this->dbQueries,
            'validation_time' => $this->validationTime
        ];
    }

    private int $rulesExecuted = 0;
    private int $cacheHits = 0;
    private int $dbQueries = 0;
    private float $validationTime = 0.0;

    // Enhanced configuration
    private array $config {
        get => $this->configData;
        set(array $value) {
            $this->configData = array_merge($this->getDefaultConfig(), $value);
            $this->clearCache();
        }
    }
    private array $configData;

    // Custom rules with enhanced caching
    private array $customRules = [];

    public function __construct(ConnectionPool $db, ?RateLimiter $rateLimiter = null)
    {
        $this->db = $db;
        $this->rateLimiter = $rateLimiter;
        $this->config = $this->getDefaultConfig();
        $this->initializeCustomRules();
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'cache_enabled' => true,
            'cache_ttl' => 300, // 5 minutes
            'rate_limit_enabled' => true,
            'rate_limit_attempts' => 100,
            'rate_limit_window' => 3600,
            'sanitization_enabled' => true,
            'async_db_checks' => false,
            'memory_limit' => '64M',
            'max_rules_per_field' => 20,
            'debug_mode' => false
        ];
    }

    /**
     * Enhanced validation with rate limiting and caching
     */
    public function validate(array $data, array $rules): array
    {
        $startTime = microtime(true);

        // Rate limiting check
        if (!$this->checkRateLimit()) {
            return [
                'valid' => false,
                'errors' => ['_rate_limit' => ['Too many validation attempts. Please try again later.']],
                'data' => []
            ];
        }

        // Input sanitization
        $data = $this->sanitizeInput($data);

        // Validate with enhanced processing
        $result = $this->processValidation($data, $rules);

        $this->validationTime = microtime(true) - $startTime;

        return $result;
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit(): bool
    {
        if (!$this->config['rate_limit_enabled'] || !$this->rateLimiter) {
            return true;
        }

        return $this->rateLimiter->allowRequest(
            $this->config['rate_limit_attempts'],
            'validation',
            $this->config['rate_limit_window']
        );
    }

    /**
     * Enhanced input sanitization pipeline
     */
    private function sanitizeInput(array $data): array
    {
        if (!$this->config['sanitization_enabled']) {
            return $data;
        }

        return array_map(function ($value) {
            return match (true) {
                is_string($value) => $this->sanitizeString($value),
                is_array($value) => $this->sanitizeInput($value),
                default => $value
            };
        }, $data);
    }

    /**
     * Sanitize string values
     */
    private function sanitizeString(string $value): string
    {
        // Remove null bytes and control characters
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        // Normalize whitespace
        $value = preg_replace('/\s+/', ' ', trim($value));

        // Remove potential script tags
        $value = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $value);

        return $value;
    }

    /**
     * Process validation with enhanced performance
     */
    private function processValidation(array $data, array $rules): array
    {
        $errors = [];
        $validData = [];
        $asyncChecks = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            // Compile rules for performance
            $compiledRules = $this->compileFieldRules($field, $fieldRules);

            // Separate sync and async validations
            [$syncRules, $asyncRules] = $this->separateRules($compiledRules);

            // Execute synchronous rules
            $fieldErrors = $this->validateFieldSync($field, $value, $syncRules, $data);

            // Queue async rules if no sync errors
            if (empty($fieldErrors) && !empty($asyncRules)) {
                $asyncChecks[$field] = [$value, $asyncRules, $data];
            }

            if (!empty($fieldErrors)) {
                $errors[$field] = $fieldErrors;
            } else {
                $validData[$field] = $this->sanitizeValue($value, $fieldRules);
            }
        }

        // Execute async checks
        if (!empty($asyncChecks)) {
            $asyncErrors = $this->executeAsyncChecks($asyncChecks);
            $errors = array_merge($errors, $asyncErrors);

            // Remove fields with async errors from valid data
            foreach ($asyncErrors as $field => $fieldErrors) {
                unset($validData[$field]);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validData
        ];
    }

    /**
     * Compile field rules for performance
     */
    private function compileFieldRules(string $field, array $rules): array
    {
        $cacheKey = md5($field . serialize($rules));

        if ($this->config['cache_enabled'] && isset(self::$compiledRules[$cacheKey])) {
            $this->cacheHits++;
            return self::$compiledRules[$cacheKey];
        }

        $compiled = $this->doCompileRules($rules);

        if ($this->config['cache_enabled']) {
            self::$compiledRules[$cacheKey] = $compiled;
        }

        return $compiled;
    }

    /**
     * Compile rules implementation
     */
    private function doCompileRules(array $rules): array
    {
        $compiled = [];

        foreach ($rules as $rule => $ruleValue) {
            $compiled[] = match ($rule) {
                'required' => ['type' => 'sync', 'rule' => 'required', 'value' => $ruleValue],
                'email' => ['type' => 'sync', 'rule' => 'email', 'value' => $ruleValue],
                'min_length' => ['type' => 'sync', 'rule' => 'min_length', 'value' => $ruleValue],
                'max_length' => ['type' => 'sync', 'rule' => 'max_length', 'value' => $ruleValue],
                'pattern' => ['type' => 'sync', 'rule' => 'pattern', 'value' => $ruleValue],
                'matches' => ['type' => 'sync', 'rule' => 'matches', 'value' => $ruleValue],
                'accepted' => ['type' => 'sync', 'rule' => 'accepted', 'value' => $ruleValue],
                'numeric' => ['type' => 'sync', 'rule' => 'numeric', 'value' => $ruleValue],
                'integer' => ['type' => 'sync', 'rule' => 'integer', 'value' => $ruleValue],
                'min' => ['type' => 'sync', 'rule' => 'min', 'value' => $ruleValue],
                'max' => ['type' => 'sync', 'rule' => 'max', 'value' => $ruleValue],
                'custom' => ['type' => 'async', 'rule' => 'custom', 'value' => $ruleValue],
                default => ['type' => 'unknown', 'rule' => $rule, 'value' => $ruleValue]
            };
        }

        return array_filter($compiled, fn($rule) => $rule['type'] !== 'unknown');
    }

    /**
     * Separate synchronous and asynchronous rules
     */
    private function separateRules(array $compiledRules): array
    {
        $sync = array_filter($compiledRules, fn($rule) => $rule['type'] === 'sync');
        $async = array_filter($compiledRules, fn($rule) => $rule['type'] === 'async');

        return [$sync, $async];
    }

    /**
     * Validate field synchronously with pattern matching
     */
    private function validateFieldSync(string $field, mixed $value, array $rules, array $allData): array
    {
        $errors = [];

        foreach ($rules as $ruleConfig) {
            $this->rulesExecuted++;

            $result = match ($ruleConfig['rule']) {
                'required' => $this->validateRequired($value, $ruleConfig['value']),
                'email' => $this->validateEmail($value, $ruleConfig['value']),
                'min_length' => $this->validateMinLength($value, $ruleConfig['value']),
                'max_length' => $this->validateMaxLength($value, $ruleConfig['value']),
                'pattern' => $this->validatePattern($value, $ruleConfig['value']),
                'matches' => $this->validateMatches($value, $allData[$ruleConfig['value']] ?? null),
                'accepted' => $this->validateAccepted($value, $ruleConfig['value']),
                'numeric' => $this->validateNumeric($value, $ruleConfig['value']),
                'integer' => $this->validateInteger($value, $ruleConfig['value']),
                'min' => $this->validateMin($value, $ruleConfig['value']),
                'max' => $this->validateMax($value, $ruleConfig['value']),
                default => null
            };

            if ($result !== null && $result !== true) {
                $errors[] = is_string($result) ? $result : $this->getErrorMessage($field, $ruleConfig['rule'], ['value' => $ruleConfig['value']]);

                // Early return on required field failure
                if ($ruleConfig['rule'] === 'required') {
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Execute asynchronous database checks
     */
    private function executeAsyncChecks(array $asyncChecks): array
    {
        $errors = [];

        foreach ($asyncChecks as $field => [$value, $rules, $allData]) {
            foreach ($rules as $ruleConfig) {
                if ($ruleConfig['rule'] === 'custom' && isset($this->customRules[$ruleConfig['value']])) {
                    $this->rulesExecuted++;
                    $this->dbQueries++;

                    $result = $this->customRules[$ruleConfig['value']]($value, $allData);

                    if ($result !== true) {
                        $errors[$field][] = $result;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Initialize custom rules with unified database approach
     */
    private function initializeCustomRules(): void
    {
        $this->customRules = [
            'unique_email' => function ($value, $data) {
                return $this->checkUniqueEmail($value);
            },

            'unique_trainer_name' => function ($value, $data) {
                return $this->checkUniqueTrainerName($value);
            },

            'valid_team_name' => function ($value, $data) {
                return $this->checkValidTeamName($value);
            }
        ];
    }

    /**
     * Enhanced unique email check with caching
     */
    private function checkUniqueEmail(string $email): string|bool
    {
        $normalizedEmail = strtolower(trim($email));
        $cacheKey = 'email_' . hash('sha256', $normalizedEmail);

        if ($this->config['cache_enabled'] && isset(self::$queryCache[$cacheKey])) {
            $this->cacheHits++;
            return self::$queryCache[$cacheKey];
        }

        try {
            $count = $this->db->table('users')
                ->where('email', '=', $normalizedEmail)
                ->count();

            $result = $count > 0 ? 'This email address is already registered.' : true;

            if ($this->config['cache_enabled']) {
                self::$queryCache[$cacheKey] = $result;
            }

            return $result;
        } catch (Throwable $e) {
            error_log('Email validation error: ' . $e->getMessage());
            return 'Unable to validate email address.';
        }
    }

    /**
     * Enhanced unique trainer name check with caching
     */
    private function checkUniqueTrainerName(string $name): string|bool
    {
        $trimmedName = trim($name);
        $cacheKey = 'trainer_' . hash('sha256', strtolower($trimmedName));

        if ($this->config['cache_enabled'] && isset(self::$queryCache[$cacheKey])) {
            $this->cacheHits++;
            return self::$queryCache[$cacheKey];
        }

        try {
            $count = $this->db->table('users')
                ->where('trainer_name', '=', $trimmedName)
                ->count();

            $result = $count > 0 ? 'This trainer name is already taken.' : true;

            if ($this->config['cache_enabled']) {
                self::$queryCache[$cacheKey] = $result;
            }

            return $result;
        } catch (Throwable $e) {
            error_log('Trainer name validation error: ' . $e->getMessage());
            return 'Unable to validate trainer name.';
        }
    }

    /**
     * Unified database-based team name validation
     * Checks both existing teams and blacklisted/reserved names in single query
     */
    private function checkValidTeamName(string $name): string|bool
    {
        $trimmedName = trim($name);
        $cacheKey = 'team_' . hash('sha256', strtolower($trimmedName));

        if ($this->config['cache_enabled'] && isset(self::$queryCache[$cacheKey])) {
            $this->cacheHits++;
            return self::$queryCache[$cacheKey];
        }

        try {
            // Single unified query for all team name validations
            $result = $this->db->table('teams')
                ->where(function($query) use ($trimmedName) {
                    $query->where('name', '=', $trimmedName)                    // Exact name match
                    ->orWhere('LOWER(name)', '=', strtolower($trimmedName)); // Case-insensitive match
                })
                ->select(['name', 'type', 'is_blacklisted'])
                ->first();

            if ($result) {
                // Determine error message based on team type
                $errorMessage = match ($result['type'] ?? 'user') {
                    'blacklisted' => 'This team name is not allowed due to licensing restrictions.',
                    'reserved' => 'This team name is reserved and cannot be used.',
                    'admin' => 'This team name is not available.',
                    default => $result['is_blacklisted'] ?
                        'This team name is not allowed due to licensing restrictions.' :
                        'This team name is already taken.'
                };

                if ($this->config['cache_enabled']) {
                    self::$queryCache[$cacheKey] = $errorMessage;
                }

                return $errorMessage;
            }

            // Name is available
            if ($this->config['cache_enabled']) {
                self::$queryCache[$cacheKey] = true;
            }

            return true;

        } catch (Throwable $e) {
            error_log('Team name validation error: ' . $e->getMessage());
            return 'Unable to validate team name.';
        }
    }

    // Enhanced validation methods with null safety
    private function validateRequired(mixed $value, bool $required): string|bool|null
    {
        return $required && $this->isEmpty($value) ? 'This field is required.' : null;
    }

    private function validateEmail(mixed $value, bool $shouldValidate): string|bool|null
    {
        if (!$shouldValidate || $this->isEmpty($value)) return null;

        return filter_var($value, FILTER_VALIDATE_EMAIL) === false ?
            'Please enter a valid email address.' : null;
    }

    private function validateMinLength(mixed $value, int $min): string|bool|null
    {
        if ($this->isEmpty($value)) return null;

        return mb_strlen((string)$value, 'UTF-8') < $min ?
            "Must be at least {$min} characters long." : null;
    }

    private function validateMaxLength(mixed $value, int $max): string|bool|null
    {
        if ($this->isEmpty($value)) return null;

        return mb_strlen((string)$value, 'UTF-8') > $max ?
            "Must not exceed {$max} characters." : null;
    }

    private function validatePattern(mixed $value, string $pattern): string|bool|null
    {
        if ($this->isEmpty($value)) return null;

        return preg_match($pattern, (string)$value) !== 1 ?
            'Invalid format.' : null;
    }

    private function validateMatches(mixed $value, mixed $otherValue): string|bool|null
    {
        return $value !== $otherValue ? 'Values do not match.' : null;
    }

    private function validateAccepted(mixed $value, bool $shouldBeAccepted): string|bool|null
    {
        if (!$shouldBeAccepted) return null;

        return !in_array($value, [true, 'true', '1', 1, 'yes', 'on'], true) ?
            'This field must be accepted.' : null;
    }

    private function validateNumeric(mixed $value, bool $shouldBeNumeric): string|bool|null
    {
        if (!$shouldBeNumeric || $this->isEmpty($value)) return null;

        return !is_numeric($value) ? 'Must be a number.' : null;
    }

    private function validateInteger(mixed $value, bool $shouldBeInteger): string|bool|null
    {
        if (!$shouldBeInteger || $this->isEmpty($value)) return null;

        return filter_var($value, FILTER_VALIDATE_INT) === false ?
            'Must be an integer.' : null;
    }

    private function validateMin(mixed $value, int|float $min): string|bool|null
    {
        if ($this->isEmpty($value) || !is_numeric($value)) return null;

        return (float)$value < $min ? "Must be at least {$min}." : null;
    }

    private function validateMax(mixed $value, int|float $max): string|bool|null
    {
        if ($this->isEmpty($value) || !is_numeric($value)) return null;

        return (float)$value > $max ? "Must not exceed {$max}." : null;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    /**
     * Enhanced error message generation
     */
    private function getErrorMessage(string $field, string $rule, array $params = []): string
    {
        $messages = [
            'required' => 'The :field field is required.',
            'email' => 'The :field must be a valid email address.',
            'min_length' => 'The :field must be at least :value characters.',
            'max_length' => 'The :field may not exceed :value characters.',
            'matches' => 'The :field must match the other field.',
            'accepted' => 'The :field must be accepted.',
            'numeric' => 'The :field must be a number.',
            'integer' => 'The :field must be an integer.',
            'min' => 'The :field must be at least :value.',
            'max' => 'The :field may not exceed :value.'
        ];

        $message = $messages[$rule] ?? 'The :field is invalid.';
        $message = str_replace(':field', $this->getFieldDisplayName($field), $message);

        foreach ($params as $key => $value) {
            $message = str_replace(':' . $key, (string)$value, $message);
        }

        return $message;
    }

    private function getFieldDisplayName(string $field): string
    {
        $displayNames = [
            'trainer_name' => 'trainer name',
            'team_name' => 'team name',
            'password_confirmation' => 'password confirmation',
            'terms_accepted' => 'terms and conditions'
        ];

        return $displayNames[$field] ?? str_replace('_', ' ', $field);
    }

    /**
     * Enhanced value sanitization
     */
    private function sanitizeValue(mixed $value, array $rules): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);

        // HTML encode if not specifically allowing HTML
        if (!isset($rules['allow_html']) || !$rules['allow_html']) {
            $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Additional sanitization based on field type
        if (isset($rules['email']) && $rules['email']) {
            $value = strtolower($value);
        }

        return $value;
    }

    /**
     * Cache management methods
     */
    public function clearCache(): void
    {
        self::$compiledRules = [];
        self::$queryCache = [];
    }

    public function getCacheStats(): array
    {
        return [
            'compiled_rules' => count(self::$compiledRules),
            'query_cache' => count(self::$queryCache),
            'cache_hits' => $this->cacheHits,
            'cache_enabled' => $this->config['cache_enabled']
        ];
    }

    /**
     * Add custom validation rule
     */
    public function addRule(string $name, callable $rule): void
    {
        $this->customRules[$name] = $rule;
        $this->clearCache(); // Clear cache when rules change
    }

    /**
     * Performance monitoring
     */
    public function resetStats(): void
    {
        $this->rulesExecuted = 0;
        $this->cacheHits = 0;
        $this->dbQueries = 0;
        $this->validationTime = 0.0;
    }

    /**
     * Debug and development helpers
     */
    public function debug(): array
    {
        return [
            'config' => $this->config,
            'stats' => $this->stats,
            'cache' => $this->getCacheStats(),
            'custom_rules' => array_keys($this->customRules)
        ];
    }
}