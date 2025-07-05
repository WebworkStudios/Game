<?php

/**
 * Player Factory
 * Creates players with realistic attributes for team generation
 *
 * File: src/Player/Domain/PlayerFactory.php
 * Directory: /src/Player/Domain/
 */

declare(strict_types=1);

namespace Player\Domain;

class PlayerFactory
{
    /** @var array<string, array> Position-specific attribute weights */
    private array $positionAttributes = [
        'GK' => [
            'diving' => 0.3,
            'handling' => 0.3,
            'kicking' => 0.2,
            'reflexes' => 0.3,
            'speed' => 0.1,
            'positioning' => 0.2
        ],
        'LB' => [
            'defense' => 0.25,
            'speed' => 0.2,
            'crossing' => 0.15,
            'tackling' => 0.2,
            'stamina' => 0.15,
            'passing' => 0.1
        ],
        'LWB' => [
            'defense' => 0.2,
            'speed' => 0.25,
            'crossing' => 0.2,
            'tackling' => 0.15,
            'stamina' => 0.2,
            'passing' => 0.1
        ],
        'RB' => [
            'defense' => 0.25,
            'speed' => 0.2,
            'crossing' => 0.15,
            'tackling' => 0.2,
            'stamina' => 0.15,
            'passing' => 0.1
        ],
        'RWB' => [
            'defense' => 0.2,
            'speed' => 0.25,
            'crossing' => 0.2,
            'tackling' => 0.15,
            'stamina' => 0.2,
            'passing' => 0.1
        ],
        'CB' => [
            'defense' => 0.3,
            'tackling' => 0.25,
            'heading' => 0.2,
            'marking' => 0.2,
            'strength' => 0.15,
            'passing' => 0.05
        ],
        'LM' => [
            'crossing' => 0.2,
            'speed' => 0.2,
            'dribbling' => 0.15,
            'passing' => 0.2,
            'stamina' => 0.15,
            'technique' => 0.1
        ],
        'RM' => [
            'crossing' => 0.2,
            'speed' => 0.2,
            'dribbling' => 0.15,
            'passing' => 0.2,
            'stamina' => 0.15,
            'technique' => 0.1
        ],
        'CAM' => [
            'passing' => 0.25,
            'dribbling' => 0.2,
            'technique' => 0.2,
            'vision' => 0.2,
            'shooting' => 0.15,
            'creativity' => 0.15
        ],
        'CDM' => [
            'tackling' => 0.2,
            'passing' => 0.25,
            'positioning' => 0.2,
            'stamina' => 0.15,
            'defense' => 0.15,
            'vision' => 0.1
        ],
        'LW' => [
            'speed' => 0.25,
            'dribbling' => 0.2,
            'crossing' => 0.2,
            'shooting' => 0.15,
            'technique' => 0.15,
            'agility' => 0.15
        ],
        'RW' => [
            'speed' => 0.25,
            'dribbling' => 0.2,
            'crossing' => 0.2,
            'shooting' => 0.15,
            'technique' => 0.15,
            'agility' => 0.15
        ],
        'ST' => [
            'shooting' => 0.3,
            'finishing' => 0.25,
            'positioning' => 0.2,
            'strength' => 0.15,
            'heading' => 0.15,
            'movement' => 0.1
        ]
    ];

    /** @var array First names pool */
    private array $firstNames = [
        'Alexander', 'Andreas', 'Anton', 'Benjamin', 'Christian', 'Daniel', 'David', 'Dennis',
        'Erik', 'Felix', 'Florian', 'Jan', 'Jonas', 'Julian', 'Leon', 'Luca', 'Lukas', 'Manuel',
        'Marcel', 'Marco', 'Mario', 'Martin', 'Matthias', 'Max', 'Michael', 'Nico', 'Oliver',
        'Pascal', 'Patrick', 'Paul', 'Peter', 'Philipp', 'Rafael', 'Robert', 'Sebastian',
        'Stefan', 'Thomas', 'Tim', 'Tobias', 'Tom', 'Adrian', 'Alex', 'Angelo', 'Bruno',
        'Carlos', 'Diego', 'Eduardo', 'Fernando', 'Gabriel', 'Gonzalo', 'Ivan', 'Jorge',
        'Jose', 'Juan', 'Luis', 'Miguel', 'Pablo', 'Pedro', 'Ricardo', 'Roberto'
    ];

    /** @var array Last names pool */
    private array $lastNames = [
        'Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker',
        'Schulz', 'Hoffmann', 'Schäfer', 'Koch', 'Bauer', 'Richter', 'Klein', 'Wolf',
        'Schröder', 'Neumann', 'Schwarz', 'Zimmermann', 'Braun', 'Krüger', 'Hartmann',
        'Lange', 'Schmitt', 'Werner', 'Schmitz', 'Krause', 'Meier', 'Lehmann', 'Huber',
        'Mayer', 'Herrmann', 'König', 'Walter', 'Schulze', 'Fuchs', 'Kaiser', 'Lang',
        'Weiß', 'Peters', 'Stein', 'Jung', 'Möller', 'Berger', 'Martin', 'Friedrich',
        'Garcia', 'Rodriguez', 'Martinez', 'Lopez', 'Gonzalez', 'Perez', 'Sanchez', 'Ramirez',
        'Cruz', 'Flores', 'Gomez', 'Morales', 'Jimenez', 'Herrera', 'Silva', 'Castro'
    ];

    /**
     * Create batch of players for a team
     */
    public function createTeamPlayers(int $teamId): array
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

        $players = [];

        foreach ($positions as $position => $count) {
            for ($i = 0; $i < $count; $i++) {
                $players[] = $this->createPlayer($teamId, $position);
            }
        }

        return $players;
    }

    /**
     * Create a player for a team
     */
    public function createPlayer(int $teamId, string $position): Player
    {
        $firstName = $this->getRandomFirstName();
        $lastName = $this->getRandomLastName();
        $age = $this->generateAge();
        $strength = $this->generateStrength();
        $salary = $this->calculateSalary($strength, $age, $position);
        $marketValue = $this->calculateMarketValue($strength, $age, $position);

        $playerData = [
            'team_id' => $teamId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'position' => $position,
            'age' => $age,
            'strength' => $strength,
            'condition' => 60,
            'form' => 20,
            'freshness' => 100,
            'motivation' => 10,
            'contract_duration' => 4, // seasons
            'salary' => $salary,
            'market_value' => $marketValue,
            'yellow_cards' => 0,
            'red_cards' => 0,
            'yellow_red_cards' => 0,
            'appearances' => 0,
            'goals' => 0,
            'assists' => 0,
            'status' => 'ok', // ok, injured, suspended
            'injury_days' => 0,
            'suspension_games' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return Player::fromArray($playerData);
    }

    /**
     * Get random first name
     */
    private function getRandomFirstName(): string
    {
        return $this->firstNames[array_rand($this->firstNames)];
    }

    /**
     * Get random last name
     */
    private function getRandomLastName(): string
    {
        return $this->lastNames[array_rand($this->lastNames)];
    }

    /**
     * Generate realistic age distribution
     * Age range: 18-28 years with peak around 22-25
     */
    private function generateAge(): int
    {
        // Weighted age distribution
        $ageWeights = [
            18 => 5,
            19 => 8,
            20 => 12,
            21 => 15,
            22 => 18,
            23 => 20,
            24 => 18,
            25 => 15,
            26 => 12,
            27 => 8,
            28 => 5
        ];

        return $this->weightedRandom($ageWeights);
    }

    /**
     * Weighted random selection
     */
    private function weightedRandom(array $weights): int
    {
        $totalWeight = array_sum($weights);
        $random = mt_rand(1, $totalWeight);

        foreach ($weights as $value => $weight) {
            $random -= $weight;
            if ($random <= 0) {
                return $value;
            }
        }

        return array_key_first($weights); // Fallback
    }

    /**
     * Generate strength distribution (5-7 range)
     * Most players around 6, few exceptional (7) or weak (5)
     */
    private function generateStrength(): int
    {
        $strengthWeights = [
            5 => 15, // Weak players
            6 => 70, // Average players
            7 => 15  // Strong players
        ];

        return $this->weightedRandom($strengthWeights);
    }

    /**
     * Calculate player salary based on strength, age, and position
     */
    private function calculateSalary(int $strength, int $age, string $position): int
    {
        $basesalary = 50000; // Base salary in Taler

        // Strength multiplier
        $strengthMultiplier = match ($strength) {
            5 => 0.7,
            6 => 1.0,
            7 => 1.5,
            default => 1.0
        };

        // Age multiplier (peak around 24-26)
        $ageMultiplier = match (true) {
            $age <= 19 => 0.6,
            $age <= 21 => 0.8,
            $age <= 23 => 0.9,
            $age <= 26 => 1.0,
            $age <= 28 => 0.95,
            default => 0.8
        };

        // Position multiplier
        $positionMultiplier = match ($position) {
            'GK' => 0.9,
            'CB', 'LB', 'RB' => 0.85,
            'LWB', 'RWB' => 0.9,
            'CDM', 'LM', 'RM' => 0.95,
            'CAM' => 1.1,
            'LW', 'RW' => 1.05,
            'ST' => 1.2,
            default => 1.0
        };

        $salary = $basesalary * $strengthMultiplier * $ageMultiplier * $positionMultiplier;

        // Add some randomness (±20%)
        $randomFactor = mt_rand(80, 120) / 100;
        $salary *= $randomFactor;

        return (int)round($salary);
    }

    /**
     * Calculate market value based on strength, age, and position
     */
    private function calculateMarketValue(int $strength, int $age, string $position): int
    {
        $baseValue = 500000; // Base market value in Taler

        // Strength multiplier
        $strengthMultiplier = match ($strength) {
            5 => 0.5,
            6 => 1.0,
            7 => 2.0,
            default => 1.0
        };

        // Age multiplier (peak around 23-25)
        $ageMultiplier = match (true) {
            $age <= 19 => 0.7, // Young potential
            $age <= 21 => 0.9,
            $age <= 23 => 1.1,
            $age <= 25 => 1.2, // Peak value
            $age <= 27 => 1.0,
            $age <= 28 => 0.8,
            default => 0.6
        };

        // Position multiplier
        $positionMultiplier = match ($position) {
            'GK' => 0.8,
            'CB', 'LB', 'RB' => 0.9,
            'LWB', 'RWB' => 0.95,
            'CDM', 'LM', 'RM' => 1.0,
            'CAM' => 1.3,
            'LW', 'RW' => 1.2,
            'ST' => 1.4,
            default => 1.0
        };

        $value = $baseValue * $strengthMultiplier * $ageMultiplier * $positionMultiplier;

        // Add some randomness (±30%)
        $randomFactor = mt_rand(70, 130) / 100;
        $value *= $randomFactor;

        return (int)round($value);
    }

    /**
     * Generate player attributes based on position
     */
    public function generateAttributes(string $position, int $strength): array
    {
        $attributes = [];
        $positionAttribs = $this->positionAttributes[$position] ?? [];

        foreach ($positionAttribs as $attribute => $weight) {
            // Base attribute value influenced by overall strength
            $baseValue = $strength * 10; // Convert 5-7 to 50-70 range

            // Add position-specific bonus/malus
            $positionBonus = ($weight - 0.15) * 20; // -10 to +10 based on importance

            // Add randomness
            $randomness = mt_rand(-5, 5);

            $finalValue = $baseValue + $positionBonus + $randomness;

            // Clamp between reasonable bounds
            $attributes[$attribute] = max(30, min(80, (int)$finalValue));
        }

        return $attributes;
    }
}