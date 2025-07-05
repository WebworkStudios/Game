<?php

/**
 * Team Domain Model
 * Represents a team entity in the football manager game
 *
 * File: src/Team/Domain/Team.php
 * Directory: /src/Team/Domain/
 */

declare(strict_types=1);

namespace Team\Domain;

use DateTimeImmutable;
use DateTimeInterface;

class Team
{
    private int $id;
    private int $userId;
    private int $leagueId;
    private string $name;
    private int $cash;
    private int $asCredits;
    private string $pitchQuality;
    private int $standingCapacity;
    private int $seatingCapacity;
    private int $vipCapacity;
    private string $stadiumName;
    private int $points;
    private int $goalsFor;
    private int $goalsAgainst;
    private int $wins;
    private int $draws;
    private int $losses;
    private DateTimeInterface $foundedAt;
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;

    public function __construct(
        int                $id,
        int                $userId,
        int                $leagueId,
        string             $name,
        int                $cash,
        int                $asCredits,
        string             $pitchQuality,
        int                $standingCapacity,
        int                $seatingCapacity,
        int                $vipCapacity,
        string             $stadiumName,
        int                $points = 0,
        int                $goalsFor = 0,
        int                $goalsAgainst = 0,
        int                $wins = 0,
        int                $draws = 0,
        int                $losses = 0,
        ?DateTimeInterface $foundedAt = null,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null
    )
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->leagueId = $leagueId;
        $this->name = $name;
        $this->cash = $cash;
        $this->asCredits = $asCredits;
        $this->pitchQuality = $pitchQuality;
        $this->standingCapacity = $standingCapacity;
        $this->seatingCapacity = $seatingCapacity;
        $this->vipCapacity = $vipCapacity;
        $this->stadiumName = $stadiumName;
        $this->points = $points;
        $this->goalsFor = $goalsFor;
        $this->goalsAgainst = $goalsAgainst;
        $this->wins = $wins;
        $this->draws = $draws;
        $this->losses = $losses;
        $this->foundedAt = $foundedAt ?? new DateTimeImmutable();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    /**
     * Create Team from array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            userId: (int)$data['user_id'],
            leagueId: (int)$data['league_id'],
            name: $data['name'],
            cash: (int)$data['cash'],
            asCredits: (int)$data['as_credits'],
            pitchQuality: $data['pitch_quality'],
            standingCapacity: (int)$data['standing_capacity'],
            seatingCapacity: (int)$data['seating_capacity'],
            vipCapacity: (int)$data['vip_capacity'],
            stadiumName: $data['stadium_name'],
            points: (int)$data['points'],
            goalsFor: (int)$data['goals_for'],
            goalsAgainst: (int)$data['goals_against'],
            wins: (int)$data['wins'],
            draws: (int)$data['draws'],
            losses: (int)$data['losses'],
            foundedAt: new DateTimeImmutable($data['founded_at']),
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at'])
        );
    }

    /**
     * Convert Team to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'league_id' => $this->leagueId,
            'name' => $this->name,
            'cash' => $this->cash,
            'as_credits' => $this->asCredits,
            'pitch_quality' => $this->pitchQuality,
            'standing_capacity' => $this->standingCapacity,
            'seating_capacity' => $this->seatingCapacity,
            'vip_capacity' => $this->vipCapacity,
            'stadium_name' => $this->stadiumName,
            'points' => $this->points,
            'goals_for' => $this->goalsFor,
            'goals_against' => $this->goalsAgainst,
            'wins' => $this->wins,
            'draws' => $this->draws,
            'losses' => $this->losses,
            'founded_at' => $this->foundedAt->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s')
        ];
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getLeagueId(): int
    {
        return $this->leagueId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCash(): int
    {
        return $this->cash;
    }

    public function getAsCredits(): int
    {
        return $this->asCredits;
    }

    public function getPitchQuality(): string
    {
        return $this->pitchQuality;
    }

    public function getStandingCapacity(): int
    {
        return $this->standingCapacity;
    }

    public function getSeatingCapacity(): int
    {
        return $this->seatingCapacity;
    }

    public function getVipCapacity(): int
    {
        return $this->vipCapacity;
    }

    public function getStadiumName(): string
    {
        return $this->stadiumName;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function getGoalsFor(): int
    {
        return $this->goalsFor;
    }

    public function getGoalsAgainst(): int
    {
        return $this->goalsAgainst;
    }

    public function getWins(): int
    {
        return $this->wins;
    }

    public function getDraws(): int
    {
        return $this->draws;
    }

    public function getLosses(): int
    {
        return $this->losses;
    }

    public function getFoundedAt(): DateTimeInterface
    {
        return $this->foundedAt;
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
     * Get total stadium capacity
     */
    public function getTotalCapacity(): int
    {
        return $this->standingCapacity + $this->seatingCapacity + $this->vipCapacity;
    }

    /**
     * Get goal difference
     */
    public function getGoalDifference(): int
    {
        return $this->goalsFor - $this->goalsAgainst;
    }

    /**
     * Get win percentage
     */
    public function getWinPercentage(): float
    {
        $gamesPlayed = $this->getGamesPlayed();

        if ($gamesPlayed === 0) {
            return 0.0;
        }

        return round(($this->wins / $gamesPlayed) * 100, 2);
    }

    /**
     * Get total games played
     */
    public function getGamesPlayed(): int
    {
        return $this->wins + $this->draws + $this->losses;
    }

    /**
     * Get formatted cash display
     */
    public function getFormattedCash(): string
    {
        return number_format($this->cash, 0, ',', '.') . ' Taler';
    }

    /**
     * Check if team can afford amount
     */
    public function canAfford(int $amount): bool
    {
        return $this->cash >= $amount;
    }

    /**
     * Get pitch quality display name
     */
    public function getPitchQualityDisplay(): string
    {
        return match ($this->pitchQuality) {
            'kuhkoppel' => 'Kuhkoppel',
            'normal' => 'Normal',
            'british' => 'British',
            default => $this->pitchQuality
        };
    }
}