<?php
/**
 * Player Repository
 * Data access layer for player management
 *
 * File: src/Player/Domain/PlayerRepository.php
 * Directory: /src/Player/Domain/
 */

declare(strict_types=1);

namespace Player\Domain;

use Framework\Database\ConnectionPool;

class PlayerRepository
{
    private ConnectionPool $db;

    public function __construct(ConnectionPool $db)
    {
        $this->db = $db;
    }

    /**
     * Create a new player
     */
    public function create(array $data): int
    {
        return $this->db->writeTable('players')->insert($data);
    }

    /**
     * Create multiple players (batch insert)
     */
    public function createBatch(array $playersData): bool
    {
        return $this->db->writeTable('players')->insertBatch($playersData);
    }

    /**
     * Find player by ID
     */
    public function findById(int $id): ?Player
    {
        $playerData = $this->db->table('players')
            ->where('id', $id)
            ->first();

        return $playerData ? Player::fromArray($playerData) : null;
    }

    /**
     * Get players by team ID
     */
    public function getByTeamId(int $teamId): array
    {
        $playersData = $this->db->table('players')
            ->where('team_id', $teamId)
            ->orderBy('position')
            ->orderBy('strength', 'DESC')
            ->get();

        return array_map(fn($playerData) => Player::fromArray($playerData), $playersData);
    }

    /**
     * Get players by team ID and position
     */
    public function getByTeamIdAndPosition(int $teamId, string $position): array
    {
        $playersData = $this->db->table('players')
            ->where('team_id', $teamId)
            ->where('position', $position)
            ->orderBy('strength', 'DESC')
            ->get();

        return array_map(fn($playerData) => Player::fromArray($playerData), $playersData);
    }

    /**
     * Get available players for team (not injured/suspended)
     */
    public function getAvailableByTeamId(int $teamId): array
    {
        $playersData = $this->db->table('players')
            ->where('team_id', $teamId)
            ->where('status', 'ok')
            ->where('injury_days', 0)
            ->where('suspension_games', 0)
            ->orderBy('position')
            ->orderBy('strength', 'DESC')
            ->get();

        return array_map(fn($playerData) => Player::fromArray($playerData), $playersData);
    }

    /**
     * Update player stats
     */
    public function updateStats(int $playerId, array $stats): bool
    {
        $allowedStats = [
            'condition', 'form', 'freshness', 'motivation', 'yellow_cards',
            'red_cards', 'yellow_red_cards', 'appearances', 'goals', 'assists'
        ];

        $updateData = array_intersect_key($stats, array_flip($allowedStats));

        return $this->update($playerId, $updateData);
    }

    /**
     * Update player
     */
    public function update(int $playerId, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        $affected = $this->db->writeTable('players')
            ->where('id', $playerId)
            ->update($data);

        return $affected > 0;
    }

    /**
     * Update player status
     */
    public function updateStatus(int $playerId, string $status, int $injuryDays = 0, int $suspensionGames = 0): bool
    {
        return $this->update($playerId, [
            'status' => $status,
            'injury_days' => $injuryDays,
            'suspension_games' => $suspensionGames
        ]);
    }

    /**
     * Update player contract
     */
    public function updateContract(int $playerId, int $duration, int $salary): bool
    {
        return $this->update($playerId, [
            'contract_duration' => $duration,
            'salary' => $salary
        ]);
    }

    /**
     * Update player market value
     */
    public function updateMarketValue(int $playerId, int $marketValue): bool
    {
        return $this->update($playerId, ['market_value' => $marketValue]);
    }

    /**
     * Get team's total salary cost
     */
    public function getTeamSalaryCost(int $teamId): int
    {
        $result = $this->db->table('players')
            ->where('team_id', $teamId)
            ->select(['SUM(salary) as total_salary'])
            ->first();

        return (int)($result['total_salary'] ?? 0);
    }

    /**
     * Get team's total market value
     */
    public function getTeamMarketValue(int $teamId): int
    {
        $result = $this->db->table('players')
            ->where('team_id', $teamId)
            ->select(['SUM(market_value) as total_value'])
            ->first();

        return (int)($result['total_value'] ?? 0);
    }

    /**
     * Get players by strength range
     */
    public function getByStrengthRange(int $minStrength, int $maxStrength, int $limit = 50): array
    {
        $playersData = $this->db->table('players')
            ->where('strength', '>=', $minStrength)
            ->where('strength', '<=', $maxStrength)
            ->orderBy('strength', 'DESC')
            ->limit($limit)
            ->get();

        return array_map(fn($playerData) => Player::fromArray($playerData), $playersData);
    }

    /**
     * Search players by name
     */
    public function searchByName(string $query, int $limit = 20): array
    {
        $playersData = $this->db->table('players')
            ->where('first_name', 'LIKE', "%{$query}%")
            ->orWhere('last_name', 'LIKE', "%{$query}%")
            ->orderBy('strength', 'DESC')
            ->limit($limit)
            ->get();

        return array_map(fn($playerData) => Player::fromArray($playerData), $playersData);
    }

    /**
     * Get top scorers
     */
    public function getTopScorers(int $limit = 10): array
    {
        $playersData = $this->db->table('players')
            ->where('goals', '>', 0)
            ->orderBy('goals', 'DESC')
            ->orderBy('appearances', 'ASC')
            ->limit($limit)
            ->get();

        return array_map(fn($playerData) => Player::fromArray($playerData), $playersData);
    }

    /**
     * Get players with expiring contracts
     */
    public function getExpiringContracts(int $teamId, int $seasonsRemaining = 1): array
    {
        $playersData = $this->db->table('players')
            ->where('team_id', $teamId)
            ->where('contract_duration', '<=', $seasonsRemaining)
            ->orderBy('contract_duration')
            ->orderBy('strength', 'DESC')
            ->get();

        return array_map(fn($playerData) => Player::fromArray($playerData), $playersData);
    }

    /**
     * Get injured players
     */
    public function getInjuredPlayers(int $teamId): array
    {
        $playersData = $this->db->table('players')
            ->where('team_id', $teamId)
            ->where(function ($query) {
                $query->where('status', 'injured')
                    ->orWhere('injury_days', '>', 0);
            })
            ->orderBy('injury_days', 'DESC')
            ->get();

        return array_map(fn($playerData) => Player::fromArray($playerData), $playersData);
    }

    /**
     * Get suspended players
     */
    public function getSuspendedPlayers(int $teamId): array
    {
        $playersData = $this->db->table('players')
            ->where('team_id', $teamId)
            ->where(function ($query) {
                $query->where('status', 'suspended')
                    ->orWhere('suspension_games', '>', 0);
            })
            ->orderBy('suspension_games', 'DESC')
            ->get();

        return array_map(fn($playerData) => Player::fromArray($playerData), $playersData);
    }

    /**
     * Get player statistics
     */
    public function getStats(): array
    {
        $stats = [];

        // Total players
        $stats['total_players'] = $this->db->table('players')->count();

        // Players by position
        $positionStats = $this->db->table('players')
            ->select(['position', 'COUNT(*) as player_count'])
            ->groupBy('position')
            ->get();

        $stats['players_by_position'] = [];
        foreach ($positionStats as $position) {
            $stats['players_by_position'][$position['position']] = (int)$position['player_count'];
        }

        // Average stats
        $avgStats = $this->db->table('players')
            ->select([
                'AVG(age) as avg_age',
                'AVG(strength) as avg_strength',
                'AVG(condition) as avg_condition',
                'AVG(form) as avg_form',
                'AVG(market_value) as avg_market_value'
            ])
            ->first();

        $stats['averages'] = [
            'age' => round($avgStats['avg_age'], 1),
            'strength' => round($avgStats['avg_strength'], 1),
            'condition' => round($avgStats['avg_condition'], 1),
            'form' => round($avgStats['avg_form'], 1),
            'market_value' => (int)$avgStats['avg_market_value']
        ];

        return $stats;
    }

    /**
     * Delete all players for a team
     */
    public function deleteByTeamId(int $teamId): bool
    {
        $affected = $this->db->writeTable('players')
            ->where('team_id', $teamId)
            ->delete();

        return $affected > 0;
    }

    /**
     * Delete player
     */
    public function delete(int $playerId): bool
    {
        $affected = $this->db->writeTable('players')
            ->where('id', $playerId)
            ->delete();

        return $affected > 0;
    }
}