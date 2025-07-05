<?php

/**
 * League Domain Model
 * Represents a league entity in the football manager game
 *
 * File: src/League/Domain/League.php
 * Directory: /src/League/Domain/
 */

declare(strict_types=1);

namespace League\Domain;

use DateTimeImmutable;
use DateTimeInterface;

class League
{
    private int $id;
    private int $leagueNumber;
    private string $name;
    private int $status;
    private int $maxTeams;
    private int $currentTeams;
    private int $season;
    private ?DateTimeInterface $seasonStartDate;
    private ?DateTimeInterface $seasonEndDate;
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;

    public function __construct(
        int                $id,
        int                $leagueNumber,
        string             $name,
        int                $status = 0,
        int                $maxTeams = 18,
        int                $currentTeams = 0,
        int                $season = 1,
        ?DateTimeInterface $seasonStartDate = null,
        ?DateTimeInterface $seasonEndDate = null,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null
    )
    {
        $this->id = $id;
        $this->leagueNumber = $leagueNumber;
        $this->name = $name;
        $this->status = $status;
        $this->maxTeams = $maxTeams;
        $this->currentTeams = $currentTeams;
        $this->season = $season;
        $this->seasonStartDate = $seasonStartDate;
        $this->seasonEndDate = $seasonEndDate;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    /**
     * Create League from array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            leagueNumber: (int)$data['league_number'],
            name: $data['name'],
            status: (int)$data['status'],
            maxTeams: (int)$data['max_teams'],
            currentTeams: (int)$data['current_teams'],
            season: (int)$data['season'],
            seasonStartDate: $data['season_start_date'] ? new DateTimeImmutable($data['season_start_date']) : null,
            seasonEndDate: $data['season_end_date'] ? new DateTimeImmutable($data['season_end_date']) : null,
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at'])
        );
    }

    /**
     * Convert League to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'league_number' => $this->leagueNumber,
            'name' => $this->name,
            'status' => $this->status,
            'max_teams' => $this->maxTeams,
            'current_teams' => $this->currentTeams,
            'season' => $this->season,
            'season_start_date' => $this->seasonStartDate?->format('Y-m-d H:i:s'),
            'season_end_date' => $this->seasonEndDate?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s')
        ];
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getLeagueNumber(): int
    {
        return $this->leagueNumber;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getMaxTeams(): int
    {
        return $this->maxTeams;
    }

    public function getCurrentTeams(): int
    {
        return $this->currentTeams;
    }

    public function getSeason(): int
    {
        return $this->season;
    }

    public function getSeasonStartDate(): ?DateTimeInterface
    {
        return $this->seasonStartDate;
    }

    public function getSeasonEndDate(): ?DateTimeInterface
    {
        return $this->seasonEndDate;
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
     * Check if league is inactive
     */
    public function isInactive(): bool
    {
        return $this->status === 0;
    }

    /**
     * Check if league is active
     */
    public function isActive(): bool
    {
        return $this->status === 1;
    }

    /**
     * Check if league is finished
     */
    public function isFinished(): bool
    {
        return $this->status === 2;
    }

    /**
     * Check if league is full
     */
    public function isFull(): bool
    {
        return $this->currentTeams >= $this->maxTeams;
    }

    /**
     * Check if league has available spots
     */
    public function hasAvailableSpots(): bool
    {
        return $this->currentTeams < $this->maxTeams;
    }

    /**
     * Get remaining spots
     */
    public function getRemainingSpots(): int
    {
        return max(0, $this->maxTeams - $this->currentTeams);
    }

    /**
     * Get status display name
     */
    public function getStatusDisplay(): string
    {
        return match ($this->status) {
            0 => 'Inaktiv',
            1 => 'Aktiv',
            2 => 'Beendet',
            default => 'Unbekannt'
        };
    }

    /**
     * Get league progress percentage
     */
    public function getFillPercentage(): float
    {
        if ($this->maxTeams === 0) {
            return 0.0;
        }

        return round(($this->currentTeams / $this->maxTeams) * 100, 1);
    }

    /**
     * Check if season is running
     */
    public function isSeasonRunning(): bool
    {
        if (!$this->seasonStartDate || !$this->seasonEndDate) {
            return false;
        }

        $now = new DateTimeImmutable();
        return $now >= $this->seasonStartDate && $now <= $this->seasonEndDate;
    }

    /**
     * Get days until season start
     */
    public function getDaysUntilSeasonStart(): ?int
    {
        if (!$this->seasonStartDate) {
            return null;
        }

        $now = new DateTimeImmutable();

        if ($now >= $this->seasonStartDate) {
            return 0;
        }

        $diff = $now->diff($this->seasonStartDate);
        return (int)$diff->days;
    }

    /**
     * Get days until season end
     */
    public function getDaysUntilSeasonEnd(): ?int
    {
        if (!$this->seasonEndDate) {
            return null;
        }

        $now = new DateTimeImmutable();

        if ($now >= $this->seasonEndDate) {
            return 0;
        }

        $diff = $now->diff($this->seasonEndDate);
        return (int)$diff->days;
    }

    /**
     * Get season duration in days
     */
    public function getSeasonDuration(): ?int
    {
        if (!$this->seasonStartDate || !$this->seasonEndDate) {
            return null;
        }

        $diff = $this->seasonStartDate->diff($this->seasonEndDate);
        return (int)$diff->days;
    }
}