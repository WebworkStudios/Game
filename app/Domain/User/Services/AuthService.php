<?php
namespace App\Domain\User\Services;

use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserId;
use Framework\Security\Session;

/**
 * Auth Service - Session-basierte Authentifizierung
 */
class AuthService
{
    private const string SESSION_USER_KEY = 'authenticated_user_id';
    private const string SESSION_USER_DATA_KEY = 'authenticated_user_data';

    public function __construct(
        private readonly Session $session,
        private readonly UserService $userService
    ) {}

    /**
     * Loggt User ein (Session)
     */
    public function login(User $user): void
    {
        // Session regenerieren für Sicherheit
        $this->session->regenerate();

        // User-Daten in Session speichern
        $this->session->set(self::SESSION_USER_KEY, $user->getId()->toInt());
        $this->session->set(self::SESSION_USER_DATA_KEY, [
            'id' => $user->getId()->toInt(),
            'username' => $user->getUsername()->toString(),
            'email' => $user->getEmail()->toString(),
            'role' => $user->getRole()->value,
            'status' => $user->getStatus()->value,
            'login_time' => time(),
        ]);
    }

    /**
     * Loggt User aus
     */
    public function logout(): void
    {
        $this->session->remove(self::SESSION_USER_KEY);
        $this->session->remove(self::SESSION_USER_DATA_KEY);
        $this->session->regenerate();
    }

    /**
     * Prüft ob User eingeloggt ist
     */
    public function isAuthenticated(): bool
    {
        return $this->session->has(self::SESSION_USER_KEY);
    }

    /**
     * Holt aktuellen User
     */
    public function getCurrentUser(): ?User
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $userId = $this->session->get(self::SESSION_USER_KEY);
        if (!$userId) {
            return null;
        }

        try {
            // User aus DB laden (immer aktuell)
            return $this->userService->findById(UserId::fromInt($userId));
        } catch (\Throwable) {
            // Bei Fehlern ausloggen
            $this->logout();
            return null;
        }
    }

    /**
     * Holt User-ID aus Session
     */
    public function getCurrentUserId(): ?UserId
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $userId = $this->session->get(self::SESSION_USER_KEY);
        return $userId ? UserId::fromInt($userId) : null;
    }

    /**
     * Holt gecachte User-Daten aus Session
     */
    public function getCurrentUserData(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return $this->session->get(self::SESSION_USER_DATA_KEY);
    }

    /**
     * Prüft Rolle des aktuellen Users
     */
    public function hasRole(string $role): bool
    {
        $userData = $this->getCurrentUserData();
        return $userData && $userData['role'] === $role;
    }

    /**
     * Prüft ob aktueller User Admin ist
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Prüft ob aktueller User Moderator ist (oder höher)
     */
    public function isModerator(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('moderator');
    }

    /**
     * Prüft ob aktueller User eine bestimmte User-ID hat
     */
    public function isCurrentUser(UserId $userId): bool
    {
        $currentUserId = $this->getCurrentUserId();
        return $currentUserId && $currentUserId->equals($userId);
    }

    /**
     * Force-Update der Session-Daten nach User-Änderungen
     */
    public function updateSessionData(User $user): void
    {
        if (!$this->isAuthenticated() || !$this->isCurrentUser($user->getId())) {
            return;
        }

        $this->session->set(self::SESSION_USER_DATA_KEY, [
            'id' => $user->getId()->toInt(),
            'username' => $user->getUsername()->toString(),
            'email' => $user->getEmail()->toString(),
            'role' => $user->getRole()->value,
            'status' => $user->getStatus()->value,
            'login_time' => $this->session->get(self::SESSION_USER_DATA_KEY)['login_time'] ?? time(),
        ]);
    }
}
