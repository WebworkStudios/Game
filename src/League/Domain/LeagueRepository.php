<?php

/**
 * League Repository
 * Data access layer for league management
 *
 * File: src/League/Domain/LeagueRepository.php
 * Directory: /src/League/Domain/
 */

declare(strict_types=1);

namespace League\Domain;

use Framework\Database\ConnectionPool;

class LeagueRepository
{
    private ConnectionPool $db;

    public function __construct(ConnectionPool $db)
    {
        $this->db = $db;
    }

    /**
     * Create a new league
     */
    public function create(array $data): int
    {
        return $this->db->writeTable('leagues')->insert($data);
    }

    /**
     * Find league by league number
     */
    public function findByLeagueNumber(int $leagueNumber): ?League
    {
        $leagueData = $this->db->table('leagues')
            ->where('league_number', $leagueNumber)
            ->first();

        return $leagueData ? League::fromArray($leagueData) : null;
    }

    /**
     * Find available league (not full and active/inactive)
     */
    public function findAvailableLeague(): ?League
    {
        $leagueData = $this->db->table('leagues')
            ->where('current_teams', '<', 18)
            ->whereIn('status', [0, 1])
            ->orderBy('league_number')
            ->first();

        return $leagueData ? League::fromArray($leagueData) : null;
    }

    /**
     * Get next league number
     */
    public function getNextLeagueNumber(): int
    {
        $result = $this->db->table('leagues')
            ->select(['MAX(league_number) as max_number'])
            ->first();

        return ((int)($result['max_number'] ?? 0)) + 1;
    }

    /**
     * Get all leagues
     */
    public function getAll(): array
    {
        $leaguesData = $this->db->table('leagues')
            ->orderBy('league_number')
            ->get();

        return array_map(fn($leagueData) => League::fromArray($leagueData), $leaguesData);
    }

    /**
     * Get leagues by status
     */
    public function getByStatus(int $status): array
    {
        $leaguesData = $this->db->table('leagues')
            ->where('status', $status)
            ->orderBy('league_number')
            ->get();

        return array_map(fn($leagueData) => League::fromArray($leagueData), $leaguesData);
    }

    /**
     * Increment team count for league
     */
    public function incrementTeamCount(int $leagueId): bool
    {
        $league = $this->findById($leagueId);

        if (!$league) {
            return false;
        }

        $newCount = $league->getCurrentTeams() + 1;
        $updateData = ['current_teams' => $newCount];

        // Activate league if it reaches max teams
        if ($newCount >= $league->getMaxTeams() && $league->getStatus() === 0) {
            $updateData['status'] = 1;
        }

        return $this->update($leagueId, $updateData);
    }

    /**
     * Find league by ID
     */
    public function findById(int $id): ?League
    {
        $leagueData = $this->db->table('leagues')
            ->where('id', (string)$id)
            ->first();

        return $leagueData ? League::fromArray($leagueData) : null;
    }

    /**
     * Update league
     */
    public function update(int $leagueId, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        $affected = $this->db->writeTable('leagues')
            ->where('id', $leagueId)
            ->update($data);

        return $affected > 0;
    }

    /**
     * Decrement team count for league
     */
    public function decrementTeamCount(int $leagueId): bool
    {
        $league = $this->findById($leagueId);

        if (!$league) {
            return false;
        }

        $newCount = max(0, $league->getCurrentTeams() - 1);

        return $this->update($leagueId, ['current_teams' => $newCount]);
    }

    /**
     * Update league status
     */
    public function updateStatus(int $leagueId, int $status): bool
    {
        return $this->update($leagueId, ['status' => $status]);
    }

    /**
     * Start season for league
     */
    public function startSeason(int $leagueId, ?string $startDate = null, ?string $endDate = null): bool
    {
        $updateData = [
            'status' => 1,
            'season_start_date' => $startDate ?? date('Y-m-d H:i:s'),
        ];

        if ($endDate) {
            $updateData['season_end_date'] = $endDate;
        }

        return $this->update($leagueId, $updateData);
    }

    /**
     * End season for league
     */
    public function endSeason(int $leagueId): bool
    {
        return $this->update($leagueId, [
            'status' => 2,
            'season_end_date' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Start new season for league
     */
    public function startNewSeason(int $leagueId): bool
    {
        $league = $this->findById($leagueId);

        if (!$league) {
            return false;
        }

        return $this->update($leagueId, [
            'status' => 1,
            'season' => $league->getSeason() + 1,
            'season_start_date' => date('Y-m-d H:i:s'),
            'season_end_date' => null
        ]);
    }

    /**
     * Get league statistics
     */
    public function getStats(): array
    {
        $stats = [];

        // Total leagues
        $stats['total_leagues'] = $this->db->table('leagues')->count();

        // Leagues by status
        $statusStats = $this->db->table('leagues')
            ->select(['status', 'COUNT(*) as league_count'])
            ->groupBy('status')
            ->get();

        $stats['leagues_by_status'] = [];
        foreach ($statusStats as $status) {
            $statusName = match ((int)$status['status']) {
                0 => 'inactive',
                1 => 'active',
                2 => 'finished',
                default => 'unknown'
            };
            $stats['leagues_by_status'][$statusName] = (int)$status['league_count'];
        }

        // Average teams per league
        $teamStats = $this->db->table('leagues')
            ->select(['AVG(current_teams) as avg_teams', 'SUM(current_teams) as total_teams'])
            ->first();

        $stats['teams'] = [
            'average_per_league' => round($teamStats['avg_teams'], 1),
            'total_teams' => (int)$teamStats['total_teams']
        ];

        // Full leagues
        $stats['full_leagues'] = $this->db->table('leagues')
            ->where('current_teams', '>=', 18)
            ->count();

        return $stats;
    }

    /**
     * Get leagues with pagination
     */
    public function getPaginated(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $query = $this->db->table('leagues');

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Get total count
        $total = $query->count();

        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $leaguesData = $query
            ->orderBy('league_number')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        $leagues = array_map(fn($leagueData) => League::fromArray($leagueData), $leaguesData);

        return [
            'leagues' => $leagues,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Delete league
     */
    public function delete(int $leagueId): bool
    {
        $affected = $this->db->writeTable('leagues')
            ->where('id', $leagueId)
            ->delete();

        return $affected > 0;
    }
}