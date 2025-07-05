<?php
/**
 * Registration Service
 * Core business logic for user registration process
 *
 * File: src/Registration/Domain/RegistrationService.php
 * Directory: /src/Registration/Domain/
 */

declare(strict_types=1);

namespace Registration\Domain;

use Framework\Core\Logger;
use Framework\Database\ConnectionPool;
use Framework\Email\EmailService;
use Framework\Security\PasswordHasher;
use League\Domain\League;
use League\Domain\LeagueRepository;
use Player\Domain\PlayerFactory;
use Player\Domain\PlayerRepository;
use Team\Domain\Team;
use Team\Domain\TeamRepository;
use User\Domain\User;
use User\Domain\UserRepository;

class RegistrationService
{
    public function __construct(
        private ConnectionPool   $db,
        private UserRepository   $userRepository,
        private TeamRepository   $teamRepository,
        private PlayerRepository $playerRepository,
        private LeagueRepository $leagueRepository,
        private PlayerFactory    $playerFactory,
        private PasswordHasher   $passwordHasher,
        private EmailService     $emailService,
        private Logger           $logger
    )
    {
    }

    /**
     * Register a new user with complete setup
     */
    public function registerUser(array $data): array
    {
        return $this->db->transaction(function () use ($data) {
            try {
                // 1. Create user account
                $user = $this->createUser($data);

                // 2. Find or create league
                $league = $this->findOrCreateLeague();

                // 3. Create team
                $team = $this->createTeam($data, $user, $league);

                // 4. Generate players for team
                $this->generatePlayersForTeam($team);

                // 5. Send confirmation email
                $this->sendConfirmationEmail($user, $data);

                return [
                    'success' => true,
                    'user_id' => $user->getId(),
                    'team_id' => $team->getId(),
                    'league_id' => $league->getId(),
                    'message' => 'Registration successful! Please check your email to confirm your account.'
                ];

            } catch (\Throwable $e) {
                $this->logger->error('Registration service error: ' . $e->getMessage(), [
                    'exception' => $e,
                    'data' => $this->sanitizeLogData($data)
                ]);

                return [
                    'success' => false,
                    'error' => 'Registration failed. Please try again later.'
                ];
            }
        });
    }

    /**
     * Create user account
     */
    private function createUser(array $data): User
    {
        $hashedPassword = $this->passwordHasher->hash($data['password']);
        $emailVerificationToken = $this->generateEmailVerificationToken();

        $userData = [
            'trainer_name' => $data['trainer_name'],
            'email' => $data['email'],
            'password_hash' => $hashedPassword,
            'email_verification_token' => $emailVerificationToken,
            'email_verified' => false,
            'status' => 'pending_verification',
            'registration_ip' => $data['registration_ip'],
            'user_agent' => $data['user_agent'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $userId = $this->userRepository->create($userData);

        return $this->userRepository->findById($userId);
    }

    /**
     * Generate email verification token
     */
    private function generateEmailVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Find available league or create new one
     */
    private function findOrCreateLeague(): League
    {
        // Find league with less than 18 teams
        $availableLeague = $this->leagueRepository->findAvailableLeague();

        if ($availableLeague) {
            return $availableLeague;
        }

        // Create new league
        $nextLeagueNumber = $this->leagueRepository->getNextLeagueNumber();

        $leagueData = [
            'league_number' => $nextLeagueNumber,
            'name' => "Liga {$nextLeagueNumber}",
            'status' => 0, // inactive until full
            'max_teams' => 18,
            'current_teams' => 0,
            'season' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $leagueId = $this->leagueRepository->create($leagueData);

        return $this->leagueRepository->findById($leagueId);
    }

    /**
     * Create team for user
     */
    private function createTeam(array $data, User $user, League $league): Team
    {
        $teamData = [
            'user_id' => $user->getId(),
            'league_id' => $league->getId(),
            'name' => $data['team_name'],
            'cash' => 10000000, // 10 million starting cash
            'as_credits' => 200, // Starting A$ credits
            'pitch_quality' => 'british', // Default pitch quality
            'standing_capacity' => 5000,
            'seating_capacity' => 0,
            'vip_capacity' => 0,
            'stadium_name' => $data['team_name'] . ' Stadium',
            'founded_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $teamId = $this->teamRepository->create($teamData);

        // Update league team count
        $this->leagueRepository->incrementTeamCount($league->getId());

        return $this->teamRepository->findById($teamId);
    }

    /**
     * Generate 20 players for the team
     */
    private function generatePlayersForTeam(Team $team): void
    {
        $positions = [
            'GK' => 2,  // Goalkeepers
            'LB' => 2,  // Left backs
            'LWB' => 1, // Left wing back
            'RB' => 2,  // Right backs
            'RWB' => 1, // Right wing back
            'CB' => 3,  // Center backs
            'LM' => 1,  // Left midfielder
            'RM' => 1,  // Right midfielder
            'CAM' => 1, // Attacking midfielder
            'CDM' => 2, // Defensive midfielder
            'LW' => 1,  // Left winger
            'RW' => 1,  // Right winger
            'ST' => 2   // Strikers
        ];

        $playersData = [];

        foreach ($positions as $position => $count) {
            for ($i = 0; $i < $count; $i++) {
                $player = $this->playerFactory->createPlayer($team->getId(), $position);
                $playersData[] = $player->toArray();
            }
        }

        $this->playerRepository->createBatch($playersData);
    }

    /**
     * Send email confirmation
     */
    private function sendConfirmationEmail(User $user, array $data): void
    {
        $confirmationUrl = $this->generateConfirmationUrl($user->getEmailVerificationToken());

        $emailData = [
            'to' => $user->getEmail(),
            'to_name' => $user->getTrainerName(),
            'subject' => 'Welcome to Football Manager - Confirm Your Account',
            'template' => 'registration_confirmation',
            'data' => [
                'trainer_name' => $user->getTrainerName(),
                'team_name' => $data['team_name'],
                'confirmation_url' => $confirmationUrl,
                'login_url' => $this->generateLoginUrl()
            ]
        ];

        $this->emailService->send($emailData);
    }

    /**
     * Generate confirmation URL
     */
    private function generateConfirmationUrl(string $token): string
    {
        $baseUrl = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';

        return "{$protocol}://{$baseUrl}/confirm-email/{$token}";
    }

    /**
     * Generate login URL
     */
    private function generateLoginUrl(): string
    {
        $baseUrl = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';

        return "{$protocol}://{$baseUrl}/login";
    }

    /**
     * Sanitize data for logging (remove sensitive information)
     */
    private function sanitizeLogData(array $data): array
    {
        $sanitized = $data;
        unset($sanitized['password']);
        $sanitized['email'] = substr($data['email'], 0, 3) . '***@***';

        return $sanitized;
    }

    /**
     * Confirm user email
     */
    public function confirmEmail(string $token): array
    {
        try {
            $user = $this->userRepository->findByEmailVerificationToken($token);

            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'Invalid or expired confirmation token.'
                ];
            }

            if ($user->isEmailVerified()) {
                return [
                    'success' => false,
                    'error' => 'Email already confirmed.'
                ];
            }

            // Confirm email and activate account
            $this->userRepository->confirmEmail($user->getId());

            $this->logger->info('Email confirmed successfully', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);

            return [
                'success' => true,
                'message' => 'Email confirmed successfully! You can now log in to your account.'
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Email confirmation error: ' . $e->getMessage(), [
                'token' => $token,
                'exception' => $e
            ]);

            return [
                'success' => false,
                'error' => 'An error occurred during email confirmation.'
            ];
        }
    }
}