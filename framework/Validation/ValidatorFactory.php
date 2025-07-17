<?php
declare(strict_types=1);

namespace Framework\Validation;

use Framework\Database\ConnectionManager;

/**
 * ValidatorFactory - Type-safe Factory für Validators
 *
 * MODERNISIERUNGEN:
 * ✅ Readonly class (PHP 8.2+)
 * ✅ Constructor property promotion
 * ✅ Bessere Array-Type-Hints
 * ✅ Nullable Parameter mit Default
 */
readonly class ValidatorFactory
{
    public function __construct(
        private ConnectionManager $connectionManager
    ) {}

    /**
     * Validator-Instanz mit Custom Messages erstellen
     *
     * @param array<string, mixed> $data Input-Daten
     * @param array<string, string> $rules Validation-Rules
     * @param array<string, string> $customMessages Custom Error-Messages
     */
    public function make(
        array $data,
        array $rules,
        array $customMessages = [],
        ?string $connectionName = null
    ): Validator {
        return Validator::make($data, $rules, $customMessages, $this->connectionManager);
    }
}
