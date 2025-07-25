<?php
namespace App\Domain\User\Enums;

enum TokenType: string
{
    case EMAIL_VERIFICATION = 'email_verification';
    case PASSWORD_RESET = 'password_reset';

    public function label(): string
    {
        return match($this) {
            self::EMAIL_VERIFICATION => 'E-Mail-Verifikation',
            self::PASSWORD_RESET => 'Passwort zurücksetzen',
        };
    }

    public function getExpirationHours(): int
    {
        return match($this) {
            self::EMAIL_VERIFICATION => 24,
            self::PASSWORD_RESET => 24,
        };
    }
}