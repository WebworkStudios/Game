<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($app_name) ?></title>
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet" href="/css/homepage.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1>⚽ <?= htmlspecialchars($app_name) ?></h1>
                </div>
                <nav class="nav">
                    <?php if ($is_authenticated): ?>
                        <span class="welcome-text">Welcome back, <?= htmlspecialchars($user_data['trainer_name']) ?>!</span>
                        <a href="/dashboard" class="btn btn-primary">Dashboard</a>
                        <a href="/logout" class="btn btn-outline">Logout</a>
                    <?php else: ?>
                        <a href="/login" class="btn btn-outline">Login</a>
                        <a href="/register" class="btn btn-primary">Join Now</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-background"></div>
        <div class="container">
            <div class="hero-content">
                <h2 class="hero-title">Build Your Football Empire</h2>
                <p class="hero-subtitle">
                    Manage your team, train players, compete in leagues, and become the ultimate football manager.
                    Your journey to glory starts here.
                </p>
                <?php if (!$is_authenticated): ?>
                    <div class="hero-actions">
                        <a href="/register" class="btn btn-primary btn-lg">Start Your Journey</a>
                        <a href="#features" class="btn btn-secondary btn-lg">Learn More</a>
                    </div>
                <?php else: ?>
                    <div class="hero-actions">
                        <a href="/dashboard" class="btn btn-primary btn-lg">Go to Dashboard</a>
                        <a href="/team" class="btn btn-secondary btn-lg">Manage Team</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <h3 class="section-title">Game Features</h3>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🏆</div>
                    <h4>Compete in Leagues</h4>
                    <p>Join competitive leagues and climb the rankings to prove you're the best manager.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h4>Build Your Squad</h4>
                    <p>Scout, train, and develop players to create the perfect team for your tactics.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h4>Advanced Analytics</h4>
                    <p>Use detailed statistics and analysis to make informed decisions about your team.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💰</div>
                    <h4>Transfer Market</h4>
                    <p>Buy and sell players in a dynamic market to strengthen your squad.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🎯</div>
                    <h4>Tactical Depth</h4>
                    <p>Create custom formations and strategies to outsmart your opponents.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🌍</div>
                    <h4>Global Community</h4>
                    <p>Connect with managers from around the world in a thriving community.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Getting Started Section -->
    <?php if (!$is_authenticated): ?>
    <section class="getting-started">
        <div class="container">
            <div class="getting-started-content">
                <h3>Ready to Begin?</h3>
                <p>Join thousands of managers already building their football legacy.</p>
                <div class="stats">
                    <div class="stat">
                        <span class="stat-number">10,000+</span>
                        <span class="stat-label">Active Managers</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">50+</span>
                        <span class="stat-label">Leagues</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">24/7</span>
                        <span class="stat-label">Live Action</span>
                    </div>
                </div>
                <a href="/register" class="btn btn-primary btn-lg">Create Free Account</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4><?= htmlspecialchars($app_name) ?></h4>
                    <p>The ultimate football management experience.</p>
                </div>
                <div class="footer-section">
                    <h5>Game</h5>
                    <ul>
                        <li><a href="/features">Features</a></li>
                        <li><a href="/leagues">Leagues</a></li>
                        <li><a href="/help">Help</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h5>Community</h5>
                    <ul>
                        <li><a href="/forum">Forum</a></li>
                        <li><a href="/discord">Discord</a></li>
                        <li><a href="/news">News</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h5>Legal</h5>
                    <ul>
                        <li><a href="/datenschutz">Privacy Policy</a></li>
                        <li><a href="/agb">Terms of Service</a></li>
                        <li><a href="/impressum">Impressum</a></li> <!-- Neue Zeile -->
                        <li><a href="/contact">Contact</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($app_name) ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>