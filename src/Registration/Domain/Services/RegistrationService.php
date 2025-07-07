<?php
// src/Registration/Services/RegistrationService.php - Überarbeitet

declare(strict_types=1);

namespace Registration\Domain\Services;

use Framework\Database\ConnectionPool;
use Framework\Security\PasswordHasher;
use Framework\Validation\Validator;
use Registration\Domain\RegistrationData;
use Registration\Domain\User;
use Registration\Domain\UserRepository;

class RegistrationService
{
    private UserRepository $userRepository;
    private PasswordHasher $hasher;
    private Validator $validator;
    private ConnectionPool $db;

    public function __construct(
        UserRepository $userRepository,
        PasswordHasher $hasher,
        Validator $validator,
        ConnectionPool $db
    ) {
        $this->userRepository = $userRepository;
        $this->hasher = $hasher;
        $this->validator = $validator;
        $this->db = $db;
    }

    /**
     * Register new user with full validation
     */
    public function register(RegistrationData $data): User
    {
        // Validate in service - central business logic
        $this->validateRegistration($data);

        // Hash password
        $passwordHash = $this->hasher->hash($data->password);

        // Create and save user
        $user = User::create(
            trainerName: $data->trainerName,
            email: $data->email,
            passwordHash: $passwordHash,
            newsletterSubscribed: $data->newsletterSubscribed
        );

        return $this->db->transaction(function() use ($user) {
            return $this->userRepository->save($user);
        });
    }

    public function activateUser(string $token): User
    {
        $user = $this->userRepository->findByVerificationToken($token);

        if (!$user) {
            throw new InvalidVerificationTokenException('Invalid or expired verification token');
        }

        if (!$user->isVerificationTokenValid()) {
            throw new InvalidVerificationTokenException('Verification token has expired');
        }

        $activatedUser = $user->activate();
        return $this->userRepository->save($activatedUser);
    }

    /**
     * Validate registration data - throws exception on failure
     */
    private function validateRegistration(RegistrationData $data): void
    {
        $rules = [
            'trainer_name' => [
                'required' => true,
                'min_length' => 3,
                'max_length' => 50,
                'pattern' => '/^[a-zA-Z0-9_\-\s]+$/',
                'custom' => 'unique_trainer_name'
            ],
            'email' => [
                'required' => true,
                'email' => true,
                'max_length' => 255,
                'custom' => 'unique_email'
            ],
            'password' => [
                'required' => true,
                'min_length' => 8,
                'max_length' => 255
            ],
            'password_confirmation' => [
                'required' => true,
                'matches' => 'password'
            ],
            'terms_accepted' => [
                'accepted' => true
            ]
        ];

        $inputData = [
            'trainer_name' => $data->trainerName,
            'email' => $data->email,
            'password' => $data->password,
            'password_confirmation' => $data->passwordConfirmation,
            'terms_accepted' => $data->termsAccepted
        ];

        $validation = $this->validator->validate($inputData, $rules);

        if (!$validation['valid']) {
            throw new RegistrationValidationException($validation['errors']);
        }

        // Additional password strength validation
        $strength = $this->hasher->getPasswordStrength($data->password);
        if ($strength['score'] < 60) {
            throw new RegistrationValidationException([
                'password' => ['Password is too weak. ' . implode(', ', $strength['feedback'])]
            ]);
        }
    }
}