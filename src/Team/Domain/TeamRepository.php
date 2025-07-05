<?php

/**
 * Team Repository
 * Data access layer for team management
 *
 * File: src/Team/Domain/TeamRepository.php
 * Directory: /src/Team/Domain/
 */

declare(strict_types=1);

namespace Team\Domain;

use Framework\Database\ConnectionPool;

class TeamRepository
{
    private ConnectionPool $db;

    public function __construct(ConnectionPool $db)
    {
        $this->db = $db;
    }

    /**
     * Create a new team
     */
    public function create(array $data): int
    {
        return $this->db->writeTable('teams')->insert($data);
    }

    /**
     * Find team by ID
     */
    public function findById(int $id): ?Team
    {
        $teamData = $this->db->table('teams')
            ->where('id', $id)
            ->first();

        return $teamData ? Team::fromArray($teamData) : null;
    }

    /**
     * Find team by user ID
     */
    public function findByUserId(int $userId): ?Team
    {
        $teamData = $this->db->table('teams')
            ->where('user_id', (string)$userId)
            ->first();

        return $teamData ? Team::fromArray($teamData) : null;
    }

    /**
     * Find team by name
     */
    public function findByName(string $name): ?Team
    {
        $teamData = $this->db->table('teams')
            ->where('name', $name)
            ->first();

        return $teamData ? Team::fromArray($teamData) : null;
    }

    /**
     * Check if team name exists
     */
    public function nameExists(string $name): bool
    {
        return $this->db->table('teams')
                ->where('name', $name)
                ->count() > 0;
    }

    /**
     * Get teams by league ID
     */
    public function getByLeagueId(int $leagueId): array
    {
        $teamsData = $this->db->table('teams')
            ->where('league_id', $leagueId)
            ->orderBy('points', 'DESC')
            ->orderBy('goals_for', 'DESC')
            ->orderBy('goals_against', 'ASC')
            ->get();

        return array_map(fn($teamData) => Team::fromArray($teamData), $teamsData);
    }

    /**
     * Update team cash
     */
    public function updateCash(int $teamId, int $newCash): bool
    {
        return $this->update($teamId, ['cash' => $newCash]);
    }

    /**
     * Update team
     */
    public function update(int $teamId, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        $affected = $this->db->writeTable('teams')
            ->where('id', $teamId)
            ->update($data);

        return $affected > 0;
    }

    /**
     * Update team A$ credits
     */
    public function updateAsCredits(int $teamId, int $newCredits): bool
    {
        return $this->update($teamId, ['as_credits' => $newCredits]);
    }

    /**
     * Update team stats
     */
    public function updateStats(int $teamId, array $stats): bool
    {
        $allowedStats = [
            'points', 'goals_for', 'goals_against', 'wins', 'draws', 'losses'
        ];

        $updateData = array_intersect_key($stats, array_flip($allowedStats));

        return $this->update($teamId, $updateData);
    }

    /**
     * Get league table
     */
    public function getLeagueTable(int $leagueId): array
    {
        $teamsData = $this->db->table('teams')
            ->where('league_id', $leagueId)
            ->orderBy('points', 'DESC')
            ->orderBy('goals_for', 'DESC')
            ->orderBy('goals_against', 'ASC')
            ->get();

        $teams = array_map(fn($teamData) => Team::fromArray($teamData), $teamsData);

        // Add position to teams
        $position = 1;
        foreach ($teams as $team) {
            $team->position = $position++;
        }

        return $teams;
    }

    /**
     * Get team statistics
     */
    public function getStats(): array
    {
        $stats = [];

        // Total teams
        $stats['total_teams'] = $this->db->table('teams')->count();

        // Teams by league
        $leagueStats = $this->db->table('teams')
            ->select(['league_id', 'COUNT(*) as team_count'])
            ->groupBy('league_id')
            ->get();

        $stats['teams_by_league'] = [];
        foreach ($leagueStats as $league) {
            $stats['teams_by_league'][$league['league_id']] = (int)$league['team_count'];
        }

        // Average cash
        $cashStats = $this->db->table('teams')
            ->select(['AVG(cash) as avg_cash', 'MAX(cash) as max_cash', 'MIN(cash) as min_cash'])
            ->first();

        $stats['cash'] = [
            'average' => (int)$cashStats['avg_cash'],
            'maximum' => (int)$cashStats['max_cash'],
            'minimum' => (int)$cashStats['min_cash']
        ];

        // Teams created today
        $stats['teams_created_today'] = $this->db->table('teams')
            ->where('created_at', '>=', date('Y-m-d 00:00:00'))
            ->count();

        return $stats;
    }

    /**
     * Search teams
     */
    public function search(string $query, int $limit = 20): array
    {
        $teamsData = $this->db->table('teams')
            ->where('name', 'LIKE', "%{$query}%")
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return array_map(fn($teamData) => Team::fromArray($teamData), $teamsData);
    }

    /**
     * Get teams with pagination
     */
    public function getPaginated(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $query = $this->db->table('teams');

        // Apply filters
        if (!empty($filters['league_id'])) {
            $query->where('league_id', $filters['league_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Get total count
        $total = $query->count();

        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $teamsData = $query
            ->orderBy('points', 'DESC')
            ->orderBy('name')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        $teams = array_map(fn($teamData) => Team::fromArray($teamData), $teamsData);

        return [
            'teams' => $teams,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Delete team
     */
    public function delete(int $teamId): bool
    {
        $affected = $this->db->writeTable('teams')
            ->where('id', $teamId)
            ->delete();

        return $affected > 0;
    }
}