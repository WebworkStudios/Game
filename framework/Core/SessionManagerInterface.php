<?php
/**
 * Session Manager Interface
 * Contract for session management implementations
 */
declare(strict_types=1);

namespace Framework\Core;

interface SessionManagerInterface
{
    /**
     * Start session if not already started
     */
    public function start(): void;

    /**
     * Check if session is active
     */
    public function isActive(): bool;

    /**
     * Regenerate session ID for security
     */
    public function regenerateId(bool $deleteOldSession = true): void;

    /**
     * Destroy session and all data
     */
    public function destroy(): void;

    /**
     * Get session ID
     */
    public function getId(): string;

    /**
     * Set session name
     */
    public function setName(string $name): void;

    /**
     * Get session name
     */
    public function getName(): string;

    /**
     * Store flash message for next request
     */
    public function flash(string $key, mixed $value): void;

    /**
     * Get and remove flash message
     */
    public function getFlash(string $key, mixed $default = null): mixed;

    /**
     * Check if flash message exists
     */
    public function hasFlash(string $key): bool;

    /**
     * Get all flash messages and clear them
     */
    public function getAllFlash(): array;

    /**
     * Set session value
     */
    public function set(string $key, mixed $value): void;

    /**
     * Get session value
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Check if session key exists
     */
    public function has(string $key): bool;

    /**
     * Remove session key
     */
    public function remove(string $key): void;

    /**
     * Clear all session data except system keys
     */
    public function clear(): void;

    /**
     * Get all session data
     */
    public function all(): array;
}