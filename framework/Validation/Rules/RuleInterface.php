<?php
declare(strict_types=1);

namespace Framework\Validation\Rules;

/**
 * RuleInterface - Contract für Validation Rules
 *
 * MODERNISIERUNGEN:
 * ✅ Strikte Typdeklarationen mit mixed
 * ✅ Dokumentation für bessere IDE-Unterstützung
 * ✅ Konsistente Parameter-Namen
 */
interface RuleInterface
{
    /**
     * Bestimmt ob die Validierungsregel erfolgreich ist
     *
     * @param string $field Der Feldname
     * @param mixed $value Der zu validierende Wert
     * @param array<string> $parameters Rule-Parameter
     * @param array<string, mixed> $data Komplette Input-Daten
     */
    public function passes(string $field, mixed $value, array $parameters, array $data): bool;

    /**
     * Erstellt Fehlermeldung für fehlgeschlagene Validierung
     *
     * @param string $field Der Feldname
     * @param mixed $value Der fehlgeschlagene Wert
     * @param array<string> $parameters Rule-Parameter
     */
    public function message(string $field, mixed $value, array $parameters): string;
}