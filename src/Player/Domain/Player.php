<?php
/**
 * Player Domain Model
 * Represents a player entity in the football manager game
 *
 * File: src/Player/Domain/Player.php
 * Directory: /src/Player/Domain/
 */

declare(strict_types=1);

namespace Player\Domain;

use DateTimeImmutable;
use DateTimeInterface;

class Player
{
    private int $id;
    private int $teamId;
    private string $firstName;
    private string $lastName;
    private string $position;
    private int $age;
    private int $strength;
    private int $condition;
    private int $form;
    private int $freshness;
    private int $motivation;
    private int $contractDuration;
    private int $salary;
    private int $marketValue;
    private int $yellowCards;
    private int $redCards;
    private int $yellowRedCards;
    private int $appearances;
    private int $goals;
    private int $assists;
    private string $status;
    private int $injuryDays;
    private int $suspensionGames;
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;

    public function __construct(
        int                $id,
        int                $teamId,
        string             $firstName,
        string             $lastName,
        string             $position,
        int                $age,
        int                $strength,
        int                $condition = 60,
        int                $form = 20,
        int                $freshness = 100,
        int                $motivation = 10,
        int                $contractDuration = 4,
        int                $salary = 0,
        int                $marketValue = 0,
        int                $yellowCards = 0,
        int                $redCards = 0,
        int                $yellowRedCards = 0,
        int                $appearances = 0,
        int                $goals = 0,
        int                $assists = 0,
        string             $status = 'ok',
        int                $injuryDays = 0,
        int                $suspensionGames = 0,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null
    )
    {
        $this->id = $id;
        $this->teamId = $teamId;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->position = $position;
        $this->age = $age;
        $this->strength = $strength;
        $this->condition = $condition;
        $this->form = $form;
        $this->freshness = $freshness;
        $this->motivation = $motivation;
        $this->contractDuration = $contractDuration;
        $this->salary = $salary;
        $this->marketValue = $marketValue;
        $this->yellowCards = $yellowCards;
        $this->redCards = $redCards;
        $this->yellowRedCards = $yellowRedCards;
        $this->appearances = $appearances;
        $this->goals = $goals;
        $this->assists = $assists;
        $this->status = $status;
        $this->injuryDays = $injuryDays;
        $this->suspensionGames = $suspensionGames;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    /**
     * Create Player from array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            teamId: (int)$data['team_id'],
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            position: $data['position'],
            age: (int)$data['age'],
            strength: (int)$data['strength'],
            condition: (int)$data['condition'],
            form: (int)$data['form'],
            freshness: (int)$data['freshness'],
            motivation: (int)$data['motivation'],
            contractDuration: (int)$data['contract_duration'],
            salary: (int)$data['salary'],
            marketValue: (int)$data['market_value'],
            yellowCards: (int)$data['yellow_cards'],
            redCards: (int)$data['red_cards'],
            yellowRedCards: (int)$data['yellow_red_cards'],
            appearances: (int)$data['appearances'],
            goals: (int)$data['goals'],
            assists: (int)$data['assists'],
            status: $data['status'],
            injuryDays: (int)$data['injury_days'],
            suspensionGames: (int)$data['suspension_games'],
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at'])
        );
    }

    /**
     * Convert Player to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->teamId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'position' => $this->position,
            'age' => $this->age,
            'strength' => $this->strength,
            'condition' => $this->condition,
            'form' => $this->form,
            'freshness' => $this->freshness,
            'motivation' => $this->motivation,
            'contract_duration' => $this->contractDuration,
            'salary' => $this->salary,
            'market_value' => $this->marketValue,
            'yellow_cards' => $this->yellowCards,
            'red_cards' => $this->redCards,
            'yellow_red_cards' => $this->yellowRedCards,
            'appearances' => $this->appearances,
            'goals' => $this->goals,
            'assists' => $this->assists,
            'status' => $this->status,
            'injury_days' => $this->injuryDays,
            'suspension_games' => $this->suspensionGames,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s')
        ];
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getTeamId(): int
    {
        return $this->teamId;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function getStrength(): int
    {
        return $this->strength;
    }

    public function getCondition(): int
    {
        return $this->condition;
    }

    public function getForm(): int
    {
        return $this->form;
    }

    public function getFreshness(): int
    {
        return $this->freshness;
    }

    public function getMotivation(): int
    {
        return $this->motivation;
    }

    public function getContractDuration(): int
    {
        return $this->contractDuration;
    }

    public function getSalary(): int
    {
        return $this->salary;
    }

    public function getMarketValue(): int
    {
        return $this->marketValue;
    }

    public function getYellowCards(): int
    {
        return $this->yellowCards;
    }

    public function getRedCards(): int
    {
        return $this->redCards;
    }

    public function getYellowRedCards(): int
    {
        return $this->yellowRedCards;
    }

    public function getAppearances(): int
    {
        return $this->appearances;
    }

    public function getGoals(): int
    {
        return $this->goals;
    }

    public function getAssists(): int
    {
        return $this->assists;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getInjuryDays(): int
    {
        return $this->injuryDays;
    }

    public function getSuspensionGames(): int
    {
        return $this->suspensionGames;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    // Business Logic Methods

    /**
     * Get full name
     */
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    /**
     * Get position display name
     */
    public function getPositionDisplay(): string
    {
        return match ($this->position) {
            'GK' => 'Torwart',
            'LB' => 'Linksverteidiger',
            'LWB' => 'Linker Wingback',
            'RB' => 'Rechtsverteidiger',
            'RWB' => 'Rechter Wingback',
            'CB' => 'Innenverteidiger',
            'LM' => 'Linkes Mittelfeld',
            'RM' => 'Rechtes Mittelfeld',
            'CAM' => 'Offensives Mittelfeld',
            'CDM' => 'Defensives Mittelfeld',
            'LW' => 'Linksaußen',
            'RW' => 'Rechtsaußen',
            'ST' => 'Stürmer',
            default => $this->position
        };
    }

    /**
     * Check if player is available for selection
     */
    public function isAvailable(): bool
    {
        return $this->status === 'ok' && $this->injuryDays === 0 && $this->suspensionGames === 0;
    }

    /**
     * Check if player is injured
     */
    public function isInjured(): bool
    {
        return $this->status === 'injured' || $this->injuryDays > 0;
    }

    /**
     * Check if player is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended' || $this->suspensionGames > 0;
    }

    /**
     * Get overall performance rating
     */
    public function getPerformanceRating(): int
    {
        $rating = $this->strength * 10; // Base rating from strength (50-70)

        // Adjust for form (-20 to +20)
        $rating += ($this->form - 50) * 0.4;

        // Adjust for condition (-20 to +20)
        $rating += ($this->condition - 50) * 0.4;

        // Adjust for freshness (-10 to +10)
        $rating += ($this->freshness - 50) * 0.2;

        // Adjust for motivation (-20 to +20)
        $rating += ($this->motivation - 50) * 0.4;

        return max(10, min(99, (int)round($rating)));
    }

    /**
     * Get goals per game ratio
     */
    public function getGoalsPerGame(): float
    {
        if ($this->appearances === 0) {
            return 0.0;
        }

        return round($this->goals / $this->appearances, 2);
    }

    /**
     * Get assists per game ratio
     */
    public function getAssistsPerGame(): float
    {
        if ($this->appearances === 0) {
            return 0.0;
        }

        return round($this->assists / $this->appearances, 2);
    }

    /**
     * Get formatted salary
     */
    public function getFormattedSalary(): string
    {
        return number_format($this->salary, 0, ',', '.') . ' Taler/Woche';
    }

    /**
     * Get formatted market value
     */
    public function getFormattedMarketValue(): string
    {
        return number_format($this->marketValue, 0, ',', '.') . ' Taler';
    }

    /**
     * Get status display
     */
    public function getStatusDisplay(): string
    {
        return match ($this->status) {
            'ok' => 'Verfügbar',
            'injured' => 'Verletzt',
            'suspended' => 'Gesperrt',
            default => $this->status
        };
    }

    /**
     * Get contract remaining display
     */
    public function getContractDisplay(): string
    {
        if ($this->contractDuration <= 0) {
            return 'Vertragslos';
        }

        $seasons = $this->contractDuration === 1 ? 'Saison' : 'Saisons';
        return $this->contractDuration . ' ' . $seasons;
    }

    /**
     * Calculate weekly wage cost
     */
    public function getWeeklyCost(): int
    {
        return $this->salary;
    }

    /**
     * Calculate season wage cost
     */
    public function getSeasonCost(): int
    {
        return $this->salary * 52; // 52 weeks per season
    }
}