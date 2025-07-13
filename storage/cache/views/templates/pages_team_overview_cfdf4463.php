<?php

// Template cache for: pages/team/overview
// Generated at: 2025-07-13 17:45:47
// DO NOT EDIT - This file is auto-generated

return array (
  'version' => '1.0',
  'template' => 'pages/team/overview',
  'template_path' => 'E:\\xampp\\htdocs\\kickerscup\\public\\..\\app\\Views\\pages\\team\\overview.html',
  'compiled_at' => 1752428747,
  'dependencies' => 
  array (
    'E:\\xampp\\htdocs\\kickerscup\\public\\..\\app\\Views\\pages\\team\\overview.html' => 1752417230,
  ),
  'compiled' => 
  array (
    'tokens' => 
    array (
      0 => 
      array (
        'type' => 'text',
        'content' => '<!-- app/Views/pages/team/overview.html -->
',
      ),
      1 => 
      array (
        'type' => 'extends',
        'template' => 'layouts/base.html',
      ),
      2 => 
      array (
        'type' => 'text',
        'content' => '

',
      ),
      3 => 
      array (
        'type' => 'block',
        'name' => 'title',
      ),
      4 => 
      array (
        'type' => 'text',
        'content' => 'Kaderübersicht - ',
      ),
      5 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'team.name',
        ),
      ),
      6 => 
      array (
        'type' => 'text',
        'content' => ' - ',
      ),
      7 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'app_name',
        ),
      ),
      8 => 
      array (
        'type' => 'endblock',
      ),
      9 => 
      array (
        'type' => 'text',
        'content' => '

',
      ),
      10 => 
      array (
        'type' => 'block',
        'name' => 'content',
      ),
      11 => 
      array (
        'type' => 'text',
        'content' => '
<div class="team-overview">
    <header class="team-header">
        <h1>',
      ),
      12 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'team.name',
        ),
      ),
      13 => 
      array (
        'type' => 'text',
        'content' => '</h1>
        <p class="team-subtitle">Kaderübersicht Saison 2024/25</p>
    </header>

    <!-- Team Statistics -->
    <section class="team-stats">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">',
      ),
      14 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'team.stats.players_count',
        ),
      ),
      15 => 
      array (
        'type' => 'text',
        'content' => '</div>
                <div class="stat-label">Spieler</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">',
      ),
      16 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'team.stats.average_age',
        ),
      ),
      17 => 
      array (
        'type' => 'text',
        'content' => '</div>
                <div class="stat-label">⌀ Alter</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">',
      ),
      18 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'team.stats.average_rating',
        ),
      ),
      19 => 
      array (
        'type' => 'text',
        'content' => '</div>
                <div class="stat-label">⌀ Bewertung</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">',
      ),
      20 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'team.stats.total_goals',
        ),
      ),
      21 => 
      array (
        'type' => 'text',
        'content' => '</div>
                <div class="stat-label">Tore</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">',
      ),
      22 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'team.stats.total_assists',
        ),
      ),
      23 => 
      array (
        'type' => 'text',
        'content' => '</div>
                <div class="stat-label">Vorlagen</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">€',
      ),
      24 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'math',
          'left' => 'team.total_market_value',
          'operator' => '/',
          'right' => '1000000',
        ),
        'filters' => 
        array (
          0 => 
          array (
            'name' => 'number_format(0)',
            'parameters' => 
            array (
            ),
          ),
        ),
      ),
      25 => 
      array (
        'type' => 'text',
        'content' => 'M</div>
                <div class="stat-label">Marktwert</div>
            </div>
        </div>

        <!-- Season Record -->
        <div class="season-record">
            <span class="record-item wins">',
      ),
      26 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'team.stats.wins',
        ),
      ),
      27 => 
      array (
        'type' => 'text',
        'content' => 'S</span>
            <span class="record-item draws">',
      ),
      28 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'team.stats.draws',
        ),
      ),
      29 => 
      array (
        'type' => 'text',
        'content' => 'U</span>
            <span class="record-item losses">',
      ),
      30 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'team.stats.losses',
        ),
      ),
      31 => 
      array (
        'type' => 'text',
        'content' => 'N</span>
        </div>
    </section>

    <!-- Injured Players Alert -->
    ',
      ),
      32 => 
      array (
        'type' => 'if',
        'condition' => 'team.injured_count > 0',
      ),
      33 => 
      array (
        'type' => 'text',
        'content' => '
    <div class="alert alert-warning">
        <i class="icon-warning">⚠️</i>
        <span>
            ',
      ),
      34 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'team.injured_count',
        ),
      ),
      35 => 
      array (
        'type' => 'text',
        'content' => ' Spieler ',
      ),
      36 => 
      array (
        'type' => 'if',
        'condition' => 'team.injured_count == 1',
      ),
      37 => 
      array (
        'type' => 'text',
        'content' => 'ist',
      ),
      38 => 
      array (
        'type' => 'else',
      ),
      39 => 
      array (
        'type' => 'text',
        'content' => 'sind',
      ),
      40 => 
      array (
        'type' => 'endif',
      ),
      41 => 
      array (
        'type' => 'text',
        'content' => ' verletzt
        </span>
    </div>
    ',
      ),
      42 => 
      array (
        'type' => 'endif',
      ),
      43 => 
      array (
        'type' => 'text',
        'content' => '

    <!-- Squad by Position -->
    <section class="squad-overview">
        ',
      ),
      44 => 
      array (
        'type' => 'for',
        'expression' => 'team.positions as position',
      ),
      45 => 
      array (
        'type' => 'text',
        'content' => '
        <div class="position-section">
            <div class="position-header">
                <h2 class="position-title">',
      ),
      46 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'position.name',
        ),
      ),
      47 => 
      array (
        'type' => 'text',
        'content' => '</h2>
                <span class="position-count">(',
      ),
      48 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'position.players',
        ),
        'filters' => 
        array (
          0 => 
          array (
            'name' => 'length',
            'parameters' => 
            array (
            ),
          ),
        ),
      ),
      49 => 
      array (
        'type' => 'text',
        'content' => ')</span>
            </div>

            ',
      ),
      50 => 
      array (
        'type' => 'if',
        'condition' => 'position.players',
      ),
      51 => 
      array (
        'type' => 'text',
        'content' => '
            <div class="players-grid">
                ',
      ),
      52 => 
      array (
        'type' => 'for',
        'expression' => 'position.players as player',
      ),
      53 => 
      array (
        'type' => 'text',
        'content' => '
                <div class="player-card ',
      ),
      54 => 
      array (
        'type' => 'if',
        'condition' => 'player.injured',
      ),
      55 => 
      array (
        'type' => 'text',
        'content' => 'injured',
      ),
      56 => 
      array (
        'type' => 'endif',
      ),
      57 => 
      array (
        'type' => 'text',
        'content' => '">
                    <!-- Player Header -->
                    <div class="player-header">
                        <div class="player-number">',
      ),
      58 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'player.shirt_number',
        ),
      ),
      59 => 
      array (
        'type' => 'text',
        'content' => '</div>
                        <div class="player-info">
                            <h3 class="player-name">',
      ),
      60 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'player.name',
        ),
      ),
      61 => 
      array (
        'type' => 'text',
        'content' => '</h3>
                            <div class="player-meta">
                                <span class="player-age">',
      ),
      62 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'player.age',
        ),
      ),
      63 => 
      array (
        'type' => 'text',
        'content' => ' Jahre</span>
                                ',
      ),
      64 => 
      array (
        'type' => 'if',
        'condition' => 'player.injured',
      ),
      65 => 
      array (
        'type' => 'text',
        'content' => '
                                <span class="injury-status">🏥 bis ',
      ),
      66 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'player.injury_until',
        ),
      ),
      67 => 
      array (
        'type' => 'text',
        'content' => '</span>
                                ',
      ),
      68 => 
      array (
        'type' => 'endif',
      ),
      69 => 
      array (
        'type' => 'text',
        'content' => '
                            </div>
                        </div>
                        <div class="player-rating">
                            <span class="rating-value">',
      ),
      70 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'player.rating',
        ),
      ),
      71 => 
      array (
        'type' => 'text',
        'content' => '</span>
                            <span class="rating-label">⭐</span>
                        </div>
                    </div>

                    <!-- Player Stats -->
                    <div class="player-stats">
                        <div class="stat-row">
                            <div class="stat">
                                <span class="stat-value">',
      ),
      72 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'player.goals',
        ),
      ),
      73 => 
      array (
        'type' => 'text',
        'content' => '</span>
                                <span class="stat-name">Tore</span>
                            </div>
                            <div class="stat">
                                <span class="stat-value">',
      ),
      74 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'player.assists',
        ),
      ),
      75 => 
      array (
        'type' => 'text',
        'content' => '</span>
                                <span class="stat-name">Vorlagen</span>
                            </div>
                            <div class="stat">
                                <span class="stat-value">',
      ),
      76 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'player.games_played',
        ),
      ),
      77 => 
      array (
        'type' => 'text',
        'content' => '</span>
                                <span class="stat-name">Spiele</span>
                            </div>
                            ',
      ),
      78 => 
      array (
        'type' => 'if',
        'condition' => 'player.position == \'Torwart\'',
      ),
      79 => 
      array (
        'type' => 'text',
        'content' => '
                            <div class="stat">
                                <span class="stat-value">',
      ),
      80 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'player.clean_sheets',
        ),
      ),
      81 => 
      array (
        'type' => 'text',
        'content' => '</span>
                                <span class="stat-name">Zu Null</span>
                            </div>
                            ',
      ),
      82 => 
      array (
        'type' => 'endif',
      ),
      83 => 
      array (
        'type' => 'text',
        'content' => '
                        </div>
                    </div>

                    <!-- Player Details -->
                    <div class="player-details">
                        <div class="market-value">
                            <span class="label">Marktwert:</span>
                            <span class="value">€',
      ),
      84 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'math',
          'left' => 'player.market_value',
          'operator' => '/',
          'right' => '1000000',
        ),
        'filters' => 
        array (
          0 => 
          array (
            'name' => 'number_format(1)',
            'parameters' => 
            array (
            ),
          ),
        ),
      ),
      85 => 
      array (
        'type' => 'text',
        'content' => 'M</span>
                        </div>
                        <div class="contract">
                            <span class="label">Vertrag bis:</span>
                            <span class="value">',
      ),
      86 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'player.contract_until',
        ),
      ),
      87 => 
      array (
        'type' => 'text',
        'content' => '</span>
                        </div>
                    </div>
                </div>
                ',
      ),
      88 => 
      array (
        'type' => 'endfor',
      ),
      89 => 
      array (
        'type' => 'text',
        'content' => '
            </div>
            ',
      ),
      90 => 
      array (
        'type' => 'else',
      ),
      91 => 
      array (
        'type' => 'text',
        'content' => '
            <div class="empty-position">
                <p>Keine Spieler auf dieser Position</p>
            </div>
            ',
      ),
      92 => 
      array (
        'type' => 'endif',
      ),
      93 => 
      array (
        'type' => 'text',
        'content' => '
        </div>
        ',
      ),
      94 => 
      array (
        'type' => 'endfor',
      ),
      95 => 
      array (
        'type' => 'text',
        'content' => '
    </section>
</div>

<style>
    .team-overview {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .team-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .team-header h1 {
        color: #1a365d;
        margin-bottom: 8px;
    }

    .team-subtitle {
        color: #718096;
        font-size: 1.1rem;
    }

    /* Stats Section */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: #2d3748;
        margin-bottom: 5px;
    }

    .stat-label {
        color: #718096;
        font-size: 0.9rem;
    }

    .season-record {
        text-align: center;
        margin-top: 15px;
    }

    .record-item {
        display: inline-block;
        padding: 8px 16px;
        margin: 0 5px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.9rem;
    }

    .wins {
        background: #c6f6d5;
        color: #22543d;
    }

    .draws {
        background: #fed7aa;
        color: #9c4221;
    }

    .losses {
        background: #fed7d7;
        color: #9b2c2c;
    }

    /* Alert */
    .alert {
        padding: 12px 16px;
        border-radius: 6px;
        margin: 20px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-warning {
        background: #fef5e7;
        border: 1px solid #f6ad55;
        color: #744210;
    }

    /* Position Sections */
    .position-section {
        margin-bottom: 40px;
    }

    .position-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e2e8f0;
    }

    .position-title {
        color: #2d3748;
        margin: 0;
    }

    .position-count {
        color: #718096;
        font-size: 1.1rem;
    }

    /* Players Grid */
    .players-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }

    .player-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .player-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .player-card.injured {
        border-color: #f56565;
        background: #fff5f5;
    }

    /* Player Header */
    .player-header {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        margin-bottom: 15px;
    }

    .player-number {
        background: #4299e1;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.1rem;
    }

    .player-info {
        flex: 1;
    }

    .player-name {
        margin: 0 0 5px 0;
        color: #2d3748;
        font-size: 1.1rem;
    }

    .player-meta {
        display: flex;
        gap: 10px;
        font-size: 0.9rem;
        color: #718096;
    }

    .injury-status {
        color: #e53e3e;
        font-weight: 500;
    }

    .player-rating {
        text-align: center;
    }

    .rating-value {
        display: block;
        font-size: 1.3rem;
        font-weight: bold;
        color: #2d3748;
    }

    .rating-label {
        font-size: 0.8rem;
    }

    /* Player Stats */
    .stat-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
    }

    .stat {
        text-align: center;
        flex: 1;
    }

    .stat-value {
        display: block;
        font-size: 1.2rem;
        font-weight: bold;
        color: #2d3748;
    }

    .stat-name {
        font-size: 0.8rem;
        color: #718096;
    }

    /* Player Details */
    .player-details {
        border-top: 1px solid #e2e8f0;
        padding-top: 12px;
        font-size: 0.9rem;
    }

    .player-details > div {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
    }

    .player-details .label {
        color: #718096;
    }

    .player-details .value {
        color: #2d3748;
        font-weight: 500;
    }

    .empty-position {
        text-align: center;
        padding: 40px;
        color: #718096;
        background: #f7fafc;
        border-radius: 8px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .players-grid {
            grid-template-columns: 1fr;
        }

        .stat-row {
            flex-wrap: wrap;
            gap: 10px;
        }

        .stat {
            min-width: 70px;
        }
    }
</style>
',
      ),
      96 => 
      array (
        'type' => 'endblock',
      ),
      97 => 
      array (
        'type' => 'text',
        'content' => '

',
      ),
      98 => 
      array (
        'type' => 'block',
        'name' => 'scripts',
      ),
      99 => 
      array (
        'type' => 'text',
        'content' => '
<script>
    // Interaktivität für Spielerkarten
    document.addEventListener(\'DOMContentLoaded\', function () {
        const playerCards = document.querySelectorAll(\'.player-card\');

        playerCards.forEach(card => {
            card.addEventListener(\'click\', function () {
                // Toggle expanded state
                this.classList.toggle(\'expanded\');
            });
        });

        // Filter für verletzte Spieler
        const filterButtons = document.querySelectorAll(\'[data-filter]\');
        filterButtons.forEach(button => {
            button.addEventListener(\'click\', function () {
                const filter = this.dataset.filter;

                playerCards.forEach(card => {
                    if (filter === \'all\') {
                        card.style.display = \'block\';
                    } else if (filter === \'injured\') {
                        card.style.display = card.classList.contains(\'injured\') ? \'block\' : \'none\';
                    } else if (filter === \'available\') {
                        card.style.display = card.classList.contains(\'injured\') ? \'none\' : \'block\';
                    }
                });
            });
        });
    });
</script>
',
      ),
      100 => 
      array (
        'type' => 'endblock',
      ),
    ),
    'blocks' => 
    array (
      'title' => 
      array (
        0 => 
        array (
          'type' => 'text',
          'content' => 'Kaderübersicht - ',
        ),
        1 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'team.name',
          ),
        ),
        2 => 
        array (
          'type' => 'text',
          'content' => ' - ',
        ),
        3 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'app_name',
          ),
        ),
      ),
      'content' => 
      array (
        0 => 
        array (
          'type' => 'text',
          'content' => '
<div class="team-overview">
    <header class="team-header">
        <h1>',
        ),
        1 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'team.name',
          ),
        ),
        2 => 
        array (
          'type' => 'text',
          'content' => '</h1>
        <p class="team-subtitle">Kaderübersicht Saison 2024/25</p>
    </header>

    <!-- Team Statistics -->
    <section class="team-stats">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">',
        ),
        3 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'team.stats.players_count',
          ),
        ),
        4 => 
        array (
          'type' => 'text',
          'content' => '</div>
                <div class="stat-label">Spieler</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">',
        ),
        5 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'team.stats.average_age',
          ),
        ),
        6 => 
        array (
          'type' => 'text',
          'content' => '</div>
                <div class="stat-label">⌀ Alter</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">',
        ),
        7 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'team.stats.average_rating',
          ),
        ),
        8 => 
        array (
          'type' => 'text',
          'content' => '</div>
                <div class="stat-label">⌀ Bewertung</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">',
        ),
        9 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'team.stats.total_goals',
          ),
        ),
        10 => 
        array (
          'type' => 'text',
          'content' => '</div>
                <div class="stat-label">Tore</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">',
        ),
        11 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'team.stats.total_assists',
          ),
        ),
        12 => 
        array (
          'type' => 'text',
          'content' => '</div>
                <div class="stat-label">Vorlagen</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">€',
        ),
        13 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'math',
            'left' => 'team.total_market_value',
            'operator' => '/',
            'right' => '1000000',
          ),
          'filters' => 
          array (
            0 => 
            array (
              'name' => 'number_format(0)',
              'parameters' => 
              array (
              ),
            ),
          ),
        ),
        14 => 
        array (
          'type' => 'text',
          'content' => 'M</div>
                <div class="stat-label">Marktwert</div>
            </div>
        </div>

        <!-- Season Record -->
        <div class="season-record">
            <span class="record-item wins">',
        ),
        15 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'team.stats.wins',
          ),
        ),
        16 => 
        array (
          'type' => 'text',
          'content' => 'S</span>
            <span class="record-item draws">',
        ),
        17 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'team.stats.draws',
          ),
        ),
        18 => 
        array (
          'type' => 'text',
          'content' => 'U</span>
            <span class="record-item losses">',
        ),
        19 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'team.stats.losses',
          ),
        ),
        20 => 
        array (
          'type' => 'text',
          'content' => 'N</span>
        </div>
    </section>

    <!-- Injured Players Alert -->
    ',
        ),
        21 => 
        array (
          'type' => 'if',
          'condition' => 'team.injured_count > 0',
        ),
        22 => 
        array (
          'type' => 'text',
          'content' => '
    <div class="alert alert-warning">
        <i class="icon-warning">⚠️</i>
        <span>
            ',
        ),
        23 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'team.injured_count',
          ),
        ),
        24 => 
        array (
          'type' => 'text',
          'content' => ' Spieler ',
        ),
        25 => 
        array (
          'type' => 'if',
          'condition' => 'team.injured_count == 1',
        ),
        26 => 
        array (
          'type' => 'text',
          'content' => 'ist',
        ),
        27 => 
        array (
          'type' => 'else',
        ),
        28 => 
        array (
          'type' => 'text',
          'content' => 'sind',
        ),
        29 => 
        array (
          'type' => 'endif',
        ),
        30 => 
        array (
          'type' => 'text',
          'content' => ' verletzt
        </span>
    </div>
    ',
        ),
        31 => 
        array (
          'type' => 'endif',
        ),
        32 => 
        array (
          'type' => 'text',
          'content' => '

    <!-- Squad by Position -->
    <section class="squad-overview">
        ',
        ),
        33 => 
        array (
          'type' => 'for',
          'expression' => 'team.positions as position',
        ),
        34 => 
        array (
          'type' => 'text',
          'content' => '
        <div class="position-section">
            <div class="position-header">
                <h2 class="position-title">',
        ),
        35 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'position.name',
          ),
        ),
        36 => 
        array (
          'type' => 'text',
          'content' => '</h2>
                <span class="position-count">(',
        ),
        37 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'position.players',
          ),
          'filters' => 
          array (
            0 => 
            array (
              'name' => 'length',
              'parameters' => 
              array (
              ),
            ),
          ),
        ),
        38 => 
        array (
          'type' => 'text',
          'content' => ')</span>
            </div>

            ',
        ),
        39 => 
        array (
          'type' => 'if',
          'condition' => 'position.players',
        ),
        40 => 
        array (
          'type' => 'text',
          'content' => '
            <div class="players-grid">
                ',
        ),
        41 => 
        array (
          'type' => 'for',
          'expression' => 'position.players as player',
        ),
        42 => 
        array (
          'type' => 'text',
          'content' => '
                <div class="player-card ',
        ),
        43 => 
        array (
          'type' => 'if',
          'condition' => 'player.injured',
        ),
        44 => 
        array (
          'type' => 'text',
          'content' => 'injured',
        ),
        45 => 
        array (
          'type' => 'endif',
        ),
        46 => 
        array (
          'type' => 'text',
          'content' => '">
                    <!-- Player Header -->
                    <div class="player-header">
                        <div class="player-number">',
        ),
        47 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'player.shirt_number',
          ),
        ),
        48 => 
        array (
          'type' => 'text',
          'content' => '</div>
                        <div class="player-info">
                            <h3 class="player-name">',
        ),
        49 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'player.name',
          ),
        ),
        50 => 
        array (
          'type' => 'text',
          'content' => '</h3>
                            <div class="player-meta">
                                <span class="player-age">',
        ),
        51 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'player.age',
          ),
        ),
        52 => 
        array (
          'type' => 'text',
          'content' => ' Jahre</span>
                                ',
        ),
        53 => 
        array (
          'type' => 'if',
          'condition' => 'player.injured',
        ),
        54 => 
        array (
          'type' => 'text',
          'content' => '
                                <span class="injury-status">🏥 bis ',
        ),
        55 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'player.injury_until',
          ),
        ),
        56 => 
        array (
          'type' => 'text',
          'content' => '</span>
                                ',
        ),
        57 => 
        array (
          'type' => 'endif',
        ),
        58 => 
        array (
          'type' => 'text',
          'content' => '
                            </div>
                        </div>
                        <div class="player-rating">
                            <span class="rating-value">',
        ),
        59 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'player.rating',
          ),
        ),
        60 => 
        array (
          'type' => 'text',
          'content' => '</span>
                            <span class="rating-label">⭐</span>
                        </div>
                    </div>

                    <!-- Player Stats -->
                    <div class="player-stats">
                        <div class="stat-row">
                            <div class="stat">
                                <span class="stat-value">',
        ),
        61 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'player.goals',
          ),
        ),
        62 => 
        array (
          'type' => 'text',
          'content' => '</span>
                                <span class="stat-name">Tore</span>
                            </div>
                            <div class="stat">
                                <span class="stat-value">',
        ),
        63 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'player.assists',
          ),
        ),
        64 => 
        array (
          'type' => 'text',
          'content' => '</span>
                                <span class="stat-name">Vorlagen</span>
                            </div>
                            <div class="stat">
                                <span class="stat-value">',
        ),
        65 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'player.games_played',
          ),
        ),
        66 => 
        array (
          'type' => 'text',
          'content' => '</span>
                                <span class="stat-name">Spiele</span>
                            </div>
                            ',
        ),
        67 => 
        array (
          'type' => 'if',
          'condition' => 'player.position == \'Torwart\'',
        ),
        68 => 
        array (
          'type' => 'text',
          'content' => '
                            <div class="stat">
                                <span class="stat-value">',
        ),
        69 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'player.clean_sheets',
          ),
        ),
        70 => 
        array (
          'type' => 'text',
          'content' => '</span>
                                <span class="stat-name">Zu Null</span>
                            </div>
                            ',
        ),
        71 => 
        array (
          'type' => 'endif',
        ),
        72 => 
        array (
          'type' => 'text',
          'content' => '
                        </div>
                    </div>

                    <!-- Player Details -->
                    <div class="player-details">
                        <div class="market-value">
                            <span class="label">Marktwert:</span>
                            <span class="value">€',
        ),
        73 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'math',
            'left' => 'player.market_value',
            'operator' => '/',
            'right' => '1000000',
          ),
          'filters' => 
          array (
            0 => 
            array (
              'name' => 'number_format(1)',
              'parameters' => 
              array (
              ),
            ),
          ),
        ),
        74 => 
        array (
          'type' => 'text',
          'content' => 'M</span>
                        </div>
                        <div class="contract">
                            <span class="label">Vertrag bis:</span>
                            <span class="value">',
        ),
        75 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'player.contract_until',
          ),
        ),
        76 => 
        array (
          'type' => 'text',
          'content' => '</span>
                        </div>
                    </div>
                </div>
                ',
        ),
        77 => 
        array (
          'type' => 'endfor',
        ),
        78 => 
        array (
          'type' => 'text',
          'content' => '
            </div>
            ',
        ),
        79 => 
        array (
          'type' => 'else',
        ),
        80 => 
        array (
          'type' => 'text',
          'content' => '
            <div class="empty-position">
                <p>Keine Spieler auf dieser Position</p>
            </div>
            ',
        ),
        81 => 
        array (
          'type' => 'endif',
        ),
        82 => 
        array (
          'type' => 'text',
          'content' => '
        </div>
        ',
        ),
        83 => 
        array (
          'type' => 'endfor',
        ),
        84 => 
        array (
          'type' => 'text',
          'content' => '
    </section>
</div>

<style>
    .team-overview {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .team-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .team-header h1 {
        color: #1a365d;
        margin-bottom: 8px;
    }

    .team-subtitle {
        color: #718096;
        font-size: 1.1rem;
    }

    /* Stats Section */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: #2d3748;
        margin-bottom: 5px;
    }

    .stat-label {
        color: #718096;
        font-size: 0.9rem;
    }

    .season-record {
        text-align: center;
        margin-top: 15px;
    }

    .record-item {
        display: inline-block;
        padding: 8px 16px;
        margin: 0 5px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.9rem;
    }

    .wins {
        background: #c6f6d5;
        color: #22543d;
    }

    .draws {
        background: #fed7aa;
        color: #9c4221;
    }

    .losses {
        background: #fed7d7;
        color: #9b2c2c;
    }

    /* Alert */
    .alert {
        padding: 12px 16px;
        border-radius: 6px;
        margin: 20px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-warning {
        background: #fef5e7;
        border: 1px solid #f6ad55;
        color: #744210;
    }

    /* Position Sections */
    .position-section {
        margin-bottom: 40px;
    }

    .position-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e2e8f0;
    }

    .position-title {
        color: #2d3748;
        margin: 0;
    }

    .position-count {
        color: #718096;
        font-size: 1.1rem;
    }

    /* Players Grid */
    .players-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }

    .player-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .player-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .player-card.injured {
        border-color: #f56565;
        background: #fff5f5;
    }

    /* Player Header */
    .player-header {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        margin-bottom: 15px;
    }

    .player-number {
        background: #4299e1;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.1rem;
    }

    .player-info {
        flex: 1;
    }

    .player-name {
        margin: 0 0 5px 0;
        color: #2d3748;
        font-size: 1.1rem;
    }

    .player-meta {
        display: flex;
        gap: 10px;
        font-size: 0.9rem;
        color: #718096;
    }

    .injury-status {
        color: #e53e3e;
        font-weight: 500;
    }

    .player-rating {
        text-align: center;
    }

    .rating-value {
        display: block;
        font-size: 1.3rem;
        font-weight: bold;
        color: #2d3748;
    }

    .rating-label {
        font-size: 0.8rem;
    }

    /* Player Stats */
    .stat-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
    }

    .stat {
        text-align: center;
        flex: 1;
    }

    .stat-value {
        display: block;
        font-size: 1.2rem;
        font-weight: bold;
        color: #2d3748;
    }

    .stat-name {
        font-size: 0.8rem;
        color: #718096;
    }

    /* Player Details */
    .player-details {
        border-top: 1px solid #e2e8f0;
        padding-top: 12px;
        font-size: 0.9rem;
    }

    .player-details > div {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
    }

    .player-details .label {
        color: #718096;
    }

    .player-details .value {
        color: #2d3748;
        font-weight: 500;
    }

    .empty-position {
        text-align: center;
        padding: 40px;
        color: #718096;
        background: #f7fafc;
        border-radius: 8px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .players-grid {
            grid-template-columns: 1fr;
        }

        .stat-row {
            flex-wrap: wrap;
            gap: 10px;
        }

        .stat {
            min-width: 70px;
        }
    }
</style>
',
        ),
      ),
      'scripts' => 
      array (
        0 => 
        array (
          'type' => 'text',
          'content' => '
<script>
    // Interaktivität für Spielerkarten
    document.addEventListener(\'DOMContentLoaded\', function () {
        const playerCards = document.querySelectorAll(\'.player-card\');

        playerCards.forEach(card => {
            card.addEventListener(\'click\', function () {
                // Toggle expanded state
                this.classList.toggle(\'expanded\');
            });
        });

        // Filter für verletzte Spieler
        const filterButtons = document.querySelectorAll(\'[data-filter]\');
        filterButtons.forEach(button => {
            button.addEventListener(\'click\', function () {
                const filter = this.dataset.filter;

                playerCards.forEach(card => {
                    if (filter === \'all\') {
                        card.style.display = \'block\';
                    } else if (filter === \'injured\') {
                        card.style.display = card.classList.contains(\'injured\') ? \'block\' : \'none\';
                    } else if (filter === \'available\') {
                        card.style.display = card.classList.contains(\'injured\') ? \'none\' : \'block\';
                    }
                });
            });
        });
    });
</script>
',
        ),
      ),
    ),
    'parent_template' => 'layouts/base.html',
    'template_path' => 'E:\\xampp\\htdocs\\kickerscup\\public\\..\\app\\Views\\pages\\team\\overview.html',
    'dependencies' => 
    array (
      0 => 'E:\\xampp\\htdocs\\kickerscup\\public\\..\\app\\Views\\pages\\team\\overview.html',
    ),
  ),
  'stats' => 
  array (
    'tokens' => 101,
    'blocks' => 3,
    'memory_usage' => 1948568,
  ),
);
