<?php
declare(strict_types=1);
namespace App\Domain\User\Services;

use App\Domain\User\Entities\User;
use App\Domain\User\Entities\UserToken;
use App\Domain\User\Enums\TokenType;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\Username;
use App\Domain\User\ValueObjects\Password;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Enums\UserRole;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\User\Repositories\UserTokenRepositoryInterface;
use Framework\Mail\MailService;

/**
 * User Service - Haupt-Geschäftslogik für User-Management
 */
class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserTokenRepositoryInterface $tokenRepository,
        private readonly MailService $mailService
    ) {}

    // ========================================================================
    // REGISTRIERUNG
    // ========================================================================

    /**
     * Registriert neuen User
     */
    public function registerUser(string $username, string $email, string $password): User
    {
        $usernameVO = Username::fromString($username);
        $emailVO = Email::fromString($email);
        $passwordVO = Password::fromPlaintext($password);

        // Prüfe Eindeutigkeit
        if ($this->userRepository->existsByUsername($usernameVO)) {
            throw new \DomainException('Username already exists');
        }

        if ($this->userRepository->existsByEmail($emailVO)) {
            throw new \DomainException('Email already exists');
        }

        // Erstelle User
        $user = User::create($usernameVO, $emailVO, $passwordVO);
        $user = $this->userRepository->save($user);

        // Sende Verifikations-E-Mail
        $this->sendEmailVerification($user);

        return $user;
    }

    /**
     * Sendet E-Mail-Verifikation
     */
    public function sendEmailVerification(User $user): void
    {
        // Lösche alte Tokens
        $this->tokenRepository->deleteTokensForUser($user->getId(), TokenType::EMAIL_VERIFICATION);

        // Erstelle neuen Token
        $token = UserToken::createEmailVerification($user->getId());
        $token = $this->tokenRepository->save($token);

        // Sende E-Mail
        $verificationUrl = $this->buildVerificationUrl($token->getToken());

        $this->mailService->sendTemplate(
            $user->getEmail()->toString(),
            'email_verification',
            [
                'username' => $user->getUsername()->toString(),
                'verification_url' => $verificationUrl,
                'app_name' => 'KickersCup Manager',
            ],
            $user->getUsername()->toString()
        );
    }

    /**
     * Verifiziert E-Mail-Adresse
     */
    public function verifyEmail(string $token): User
    {
        $tokenEntity = $this->tokenRepository->findValidToken($token, TokenType::EMAIL_VERIFICATION);

        if (!$tokenEntity) {
            throw new \DomainException('Invalid or expired verification token');
        }

        $user = $this->userRepository->findById($tokenEntity->getUserId());
        if (!$user) {
            throw new \DomainException('User not found');
        }

        if ($user->isEmailVerified()) {
            throw new \DomainException('Email is already verified');
        }

        // Aktiviere User
        $user->activate();
        $user = $this->userRepository->save($user);

        // Markiere Token als verwendet
        $tokenEntity->markAsUsed();
        $this->tokenRepository->save($tokenEntity);

        // Sende Welcome-E-Mail
        $this->sendWelcomeEmail($user);

        return $user;
    }

    // ========================================================================
    // LOGIN & AUTHENTICATION
    // ========================================================================

    /**
     * Authentifiziert User
     */
    public function authenticateUser(string $identifier, string $password): User
    {
        $user = $this->userRepository->findByEmailOrUsername($identifier);

        if (!$user) {
            throw new \DomainException('Invalid credentials');
        }

        if (!$user->canLogin()) {
            if ($user->getStatus()->isPending()) {
                throw new \DomainException('Account not activated. Please check your email.');
            } elseif ($user->getStatus()->isSuspended()) {
                throw new \DomainException('Account is suspended');
            } elseif ($user->isLoginBlocked()) {
                throw new \DomainException('Too many login attempts. Please try again in 30 minutes.');
            } else {
                throw new \DomainException('Login not allowed');
            }
        }

        if (!$user->getPassword()->verify($password)) {
            $user->recordFailedLogin();
            $this->userRepository->save($user);
            throw new \DomainException('Invalid credentials');
        }

        // Erfolgreicher Login
        $user->recordSuccessfulLogin();

        // Prüfe ob Passwort-Rehash nötig ist
        if ($user->getPassword()->needsRehash()) {
            $newPassword = Password::fromPlaintext($password);
            $user->changePassword($newPassword);
        }

        $user = $this->userRepository->save($user);

        return $user;
    }

    // ========================================================================
    // PASSWORT ZURÜCKSETZEN
    // ========================================================================

    /**
     * Startet Passwort-Reset-Prozess
     */
    public function requestPasswordReset(string $email): void
    {
        $emailVO = Email::fromString($email);
        $user = $this->userRepository->findByEmail($emailVO);

        if (!$user) {
            // Aus Sicherheitsgründen keinen Fehler werfen
            // (verhindert E-Mail-Enumeration)
            return;
        }

        if (!$user->getStatus()->isActive()) {
            // Inaktive User können kein Passwort zurücksetzen
            return;
        }

        // Lösche alte Reset-Tokens
        $this->tokenRepository->deleteTokensForUser($user->getId(), TokenType::PASSWORD_RESET);

        // Erstelle neuen Token
        $token = UserToken::createPasswordReset($user->getId());
        $token = $this->tokenRepository->save($token);

        // Sende E-Mail
        $resetUrl = $this->buildPasswordResetUrl($token->getToken());

        $this->mailService->sendTemplate(
            $user->getEmail()->toString(),
            'password_reset',
            [
                'username' => $user->getUsername()->toString(),
                'reset_url' => $resetUrl,
                'app_name' => 'KickersCup Manager',
            ],
            $user->getUsername()->toString()
        );
    }

    /**
     * Setzt Passwort zurück
     */
    public function resetPassword(string $token, string $newPassword): User
    {
        $tokenEntity = $this->tokenRepository->findValidToken($token, TokenType::PASSWORD_RESET);

        if (!$tokenEntity) {
            throw new \DomainException('Invalid or expired reset token');
        }

        $user = $this->userRepository->findById($tokenEntity->getUserId());
        if (!$user) {
            throw new \DomainException('User not found');
        }

        // Setze neues Passwort
        $passwordVO = Password::fromPlaintext($newPassword);
        $user->changePassword($passwordVO);

        // Reset Login-Versuche
        $user->resetLoginAttempts();

        $user = $this->userRepository->save($user);

        // Markiere Token als verwendet
        $tokenEntity->markAsUsed();
        $this->tokenRepository->save($tokenEntity);

        // Lösche alle anderen Reset-Tokens für diesen User
        $this->tokenRepository->deleteTokensForUser($user->getId(), TokenType::PASSWORD_RESET);

        return $user;
    }

    // ========================================================================
    // PROFIL-MANAGEMENT
    // ========================================================================

    /**
     * Ändert Passwort (mit altem Passwort-Verifikation)
     */
    public function changePassword(UserId $userId, string $currentPassword, string $newPassword): User
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new \DomainException('User not found');
        }

        if (!$user->getPassword()->verify($currentPassword)) {
            throw new \DomainException('Current password is incorrect');
        }

        $passwordVO = Password::fromPlaintext($newPassword);
        $user->changePassword($passwordVO);

        return $this->userRepository->save($user);
    }

    /**
     * Ändert Username
     */
    public function changeUsername(UserId $userId, string $newUsername): User
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new \DomainException('User not found');
        }

        $usernameVO = Username::fromString($newUsername);

        // Prüfe Eindeutigkeit (außer bei aktuellem User)
        $existing = $this->userRepository->findByUsername($usernameVO);
        if ($existing && !$existing->getId()->equals($userId)) {
            throw new \DomainException('Username already exists');
        }

        $user->changeUsername($usernameVO);

        return $this->userRepository->save($user);
    }

    /**
     * Setzt Profilbild
     */
    public function setProfileImage(UserId $userId, string $imagePath): User
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new \DomainException('User not found');
        }

        $user->setProfileImage($imagePath);

        return $this->userRepository->save($user);
    }

    // ========================================================================
    // ADMIN-FUNKTIONEN
    // ========================================================================

    /**
     * Ändert User-Status (Admin)
     */
    public function changeUserStatus(UserId $userId, UserStatus $newStatus): User
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new \DomainException('User not found');
        }

        switch ($newStatus) {
            case UserStatus::ACTIVE:
                $user->reactivate();
                break;
            case UserStatus::SUSPENDED:
                $user->suspend();
                break;
            default:
                throw new \DomainException('Invalid status change');
        }

        return $this->userRepository->save($user);
    }

    /**
     * Ändert User-Rolle (Admin)
     */
    public function changeUserRole(UserId $userId, UserRole $newRole): User
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new \DomainException('User not found');
        }

        $user->changeRole($newRole);

        return $this->userRepository->save($user);
    }

    /**
     * Resettet Login-Versuche (Admin)
     */
    public function resetUserLoginAttempts(UserId $userId): User
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new \DomainException('User not found');
        }

        $user->resetLoginAttempts();

        return $this->userRepository->save($user);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Sendet Welcome-E-Mail
     */
    private function sendWelcomeEmail(User $user): void
    {
        $loginUrl = $_SERVER['HTTP_HOST'] . '/login';

        $this->mailService->sendTemplate(
            $user->getEmail()->toString(),
            'welcome',
            [
                'username' => $user->getUsername()->toString(),
                'app_name' => 'KickersCup Manager',
                'login_url' => 'https://' . $loginUrl,
            ],
            $user->getUsername()->toString()
        );
    }

    /**
     * Erstellt Verifikations-URL
     */
    private function buildVerificationUrl(string $token): string
    {
        $baseUrl = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "https://{$baseUrl}/auth/verify-email?token={$token}";
    }

    /**
     * Erstellt Password-Reset-URL
     */
    private function buildPasswordResetUrl(string $token): string
    {
        $baseUrl = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "https://{$baseUrl}/auth/reset-password?token={$token}";
    }

    /**
     * Holt User-Statistiken
     */
    public function getUserStats(): array
    {
        return $this->userRepository->getUserStats();
    }

    /**
     * Bereinigt abgelaufene Tokens
     */
    public function cleanupExpiredTokens(): int
    {
        return $this->tokenRepository->deleteExpiredTokens();
    }
}