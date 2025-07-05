<?php

/**
 * Validation Framework
 * Comprehensive input validation with custom rules support
 *
 * File: framework/Validation/Validator.php
 * Directory: /framework/Validation/
 */

declare(strict_types=1);

namespace Framework\Validation;

use Framework\Database\ConnectionPool;
use Throwable;

class Validator
{
    private ConnectionPool $db;
    private array $customRules = [];
    private array $teamNameBlacklist = [
        // Real team names for licensing reasons
        'Bayern München', 'Bayern Munich', 'Borussia Dortmund', 'BVB', 'Schalke 04',
        'RB Leipzig', 'Bayer Leverkusen', 'Eintracht Frankfurt', 'VfL Wolfsburg',
        'SC Freiburg', 'TSG Hoffenheim', 'FC Augsburg', 'Hertha BSC', 'VfB Stuttgart',
        'Werder Bremen', 'FC Köln', 'Mainz 05', 'Union Berlin', 'Arminia Bielefeld',
        'Real Madrid', 'FC Barcelona', 'Atletico Madrid', 'Manchester United',
        'Manchester City', 'Liverpool FC', 'Chelsea FC', 'Arsenal FC', 'Tottenham',
        'Juventus', 'AC Milan', 'Inter Milan', 'AS Roma', 'SSC Napoli', 'Lazio',
        'Paris Saint-Germain', 'PSG', 'Olympique Marseille', 'AS Monaco', 'Lyon',
        // Add more as needed
        'Admin', 'Test', 'System', 'Root', 'Guest', 'Null', 'Undefined'
    ];

    public function __construct(ConnectionPool $db)
    {
        $this->db = $db;
        $this->initializeCustomRules();
    }

    /**
     * Initialize custom validation rules
     */
    private function initializeCustomRules(): void
    {
        $this->customRules = [
            'unique_email' => function ($value, $data) {
                try {
                    $count = $this->db->table('users')
                        ->where('email', '=', $value)
                        ->count();

                    if ($count > 0) {
                        return 'This email address is already registered.';
                    }
                    return true;
                } catch (Throwable $e) {
                    error_log('Email validation error: ' . $e->getMessage());
                    return 'Unable to validate email address.';
                }
            },

            'unique_trainer_name' => function ($value, $data) {
                try {
                    $count = $this->db->table('users')
                        ->where('trainer_name', '=', $value)
                        ->count();

                    if ($count > 0) {
                        return 'This trainer name is already taken.';
                    }
                    return true;
                } catch (Throwable $e) {
                    error_log('Trainer name validation error: ' . $e->getMessage());
                    return 'Unable to validate trainer name.';
                }
            },

            'valid_team_name' => function ($value, $data) {
                try {
                    // Check against blacklist
                    foreach ($this->teamNameBlacklist as $blacklistedName) {
                        if (strcasecmp(trim($value), trim($blacklistedName)) === 0) {
                            return 'This team name is not allowed due to licensing restrictions.';
                        }
                    }

                    // Check for uniqueness
                    $count = $this->db->table('teams')
                        ->where('name', '=', $value)
                        ->count();

                    if ($count > 0) {
                        return 'This team name is already taken.';
                    }

                    return true;
                } catch (Throwable $e) {
                    error_log('Team name validation error: ' . $e->getMessage());
                    return 'Unable to validate team name.';
                }
            }
        ];
    }

    /**
     * Validate input data against rules
     */
    public function validate(array $data, array $rules): array
    {
        $errors = [];
        $validData = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $fieldErrors = $this->validateField($field, $value, $fieldRules, $data);

            if (!empty($fieldErrors)) {
                $errors[$field] = $fieldErrors;
            } else {
                $validData[$field] = $this->sanitizeValue($value, $fieldRules);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validData
        ];
    }

    /**
     * Validate a single field
     */
    private function validateField(string $field, mixed $value, array $rules, array $allData): array
    {
        $errors = [];

        // Required validation
        if (isset($rules['required']) && $rules['required']) {
            if ($this->isEmpty($value)) {
                $errors[] = $this->getErrorMessage($field, 'required');
                return $errors; // Don't validate further if required field is empty
            }
        }

        // Skip other validations if value is empty and not required
        if ($this->isEmpty($value)) {
            return $errors;
        }

        // Type validations
        foreach ($rules as $rule => $ruleValue) {
            switch ($rule) {
                case 'email':
                    if ($ruleValue && !$this->validateEmail($value)) {
                        $errors[] = $this->getErrorMessage($field, 'email');
                    }
                    break;

                case 'min_length':
                    if (!$this->validateMinLength($value, $ruleValue)) {
                        $errors[] = $this->getErrorMessage($field, 'min_length', ['min' => $ruleValue]);
                    }
                    break;

                case 'max_length':
                    if (!$this->validateMaxLength($value, $ruleValue)) {
                        $errors[] = $this->getErrorMessage($field, 'max_length', ['max' => $ruleValue]);
                    }
                    break;

                case 'pattern':
                    if (!$this->validatePattern($value, $ruleValue)) {
                        $description = $rules['description'] ?? 'Invalid format';
                        $errors[] = $description;
                    }
                    break;

                case 'matches':
                    if (!$this->validateMatches($value, $allData[$ruleValue] ?? null)) {
                        $errors[] = $this->getErrorMessage($field, 'matches', ['other' => $ruleValue]);
                    }
                    break;

                case 'accepted':
                    if ($ruleValue && !$this->validateAccepted($value)) {
                        $errors[] = $this->getErrorMessage($field, 'accepted');
                    }
                    break;

                case 'numeric':
                    if ($ruleValue && !$this->validateNumeric($value)) {
                        $errors[] = $this->getErrorMessage($field, 'numeric');
                    }
                    break;

                case 'integer':
                    if ($ruleValue && !$this->validateInteger($value)) {
                        $errors[] = $this->getErrorMessage($field, 'integer');
                    }
                    break;

                case 'min':
                    if (!$this->validateMin($value, $ruleValue)) {
                        $errors[] = $this->getErrorMessage($field, 'min', ['min' => $ruleValue]);
                    }
                    break;

                case 'max':
                    if (!$this->validateMax($value, $ruleValue)) {
                        $errors[] = $this->getErrorMessage($field, 'max', ['max' => $ruleValue]);
                    }
                    break;

                case 'custom':
                    if (isset($this->customRules[$ruleValue])) {
                        $customResult = $this->customRules[$ruleValue]($value, $allData);
                        if ($customResult !== true) {
                            $errors[] = $customResult;
                        }
                    }
                    break;
            }
        }

        return $errors;
    }

    /**
     * Validation methods
     */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    /**
     * Get error message for validation rule
     */
    private function getErrorMessage(string $field, string $rule, array $params = []): string
    {
        $messages = [
            'required' => 'The :field field is required.',
            'email' => 'The :field must be a valid email address.',
            'min_length' => 'The :field must be at least :min characters.',
            'max_length' => 'The :field may not be greater than :max characters.',
            'matches' => 'The :field must match :other.',
            'accepted' => 'The :field must be accepted.',
            'numeric' => 'The :field must be a number.',
            'integer' => 'The :field must be an integer.',
            'min' => 'The :field must be at least :min.',
            'max' => 'The :field may not be greater than :max.'
        ];

        $message = $messages[$rule] ?? 'The :field is invalid.';

        // Replace placeholders
        $message = str_replace(':field', $this->getFieldDisplayName($field), $message);

        foreach ($params as $key => $value) {
            $message = str_replace(':' . $key, (string)$value, $message);
        }

        return $message;
    }

    /**
     * Get display name for field
     */
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

    private function validateEmail(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateMinLength(string $value, int $min): bool
    {
        return mb_strlen($value, 'UTF-8') >= $min;
    }

    private function validateMaxLength(string $value, int $max): bool
    {
        return mb_strlen($value, 'UTF-8') <= $max;
    }

    private function validatePattern(string $value, string $pattern): bool
    {
        return preg_match($pattern, $value) === 1;
    }

    private function validateMatches(mixed $value, mixed $otherValue): bool
    {
        return $value === $otherValue;
    }

    private function validateAccepted(mixed $value): bool
    {
        return in_array($value, [true, 'true', '1', 1, 'yes', 'on'], true);
    }

    private function validateNumeric(mixed $value): bool
    {
        return is_numeric($value);
    }

    private function validateInteger(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    private function validateMin(mixed $value, int|float $min): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        return (float)$value >= $min;
    }

    private function validateMax(mixed $value, int|float $max): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        return (float)$value <= $max;
    }

    /**
     * Sanitize value based on rules
     */
    private function sanitizeValue(mixed $value, array $rules): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            // HTML encode if not specifically allowing HTML
            if (!isset($rules['allow_html']) || !$rules['allow_html']) {
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }

        return $value;
    }

    /**
     * Add custom validation rule
     */
    public function addRule(string $name, callable $rule): void
    {
        $this->customRules[$name] = $rule;
    }

    /**
     * Add team name to blacklist
     */
    public function addTeamNameToBlacklist(string $teamName): void
    {
        $this->teamNameBlacklist[] = $teamName;
    }

    /**
     * Get team name blacklist
     */
    public function getTeamNameBlacklist(): array
    {
        return $this->teamNameBlacklist;
    }
}