<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Football Manager</title>
    <link rel="stylesheet" href="/css/registration.css">
    <link rel="stylesheet" href="/css/main.css">
</head>
<body>
<div class="registration-container">
    <div class="registration-header">
        <h1>🏆 Football Manager</h1>
        <p>Registration Successful!</p>
    </div>

    <div class="success-container">
        <div class="success-message">
            <div class="success-icon">✅</div>
            <h2>Welcome to Football Manager!</h2>
            <p class="success-text"><?= htmlspecialchars($message) ?></p>
        </div>

        <div class="account-details">
            <h3>Your Account Details</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="label">User ID:</span>
                    <span class="value">#<?= htmlspecialchars($user_id) ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Team ID:</span>
                    <span class="value">#<?= htmlspecialchars($team_id) ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">League ID:</span>
                    <span class="value">#<?= htmlspecialchars($league_id) ?></span>
                </div>
            </div>
        </div>

        <div class="next-steps">
            <h3>📧 Check Your Email</h3>
            <p>We've sent you a confirmation email with a link to activate your account. Please check your inbox and
                click the activation link to complete your registration.</p>

            <div class="steps-list">
                <div class="step">
                    <span class="step-number">1</span>
                    <span class="step-text">Check your email inbox</span>
                </div>
                <div class="step">
                    <span class="step-number">2</span>
                    <span class="step-text">Click the confirmation link</span>
                </div>
                <div class="step">
                    <span class="step-number">3</span>
                    <span class="step-text">Start managing your team!</span>
                </div>
            </div>
        </div>

        <div class="team-setup-info">
            <h3>🎮 Your Team Setup</h3>
            <div class="setup-grid">
                <div class="setup-item">
                    <div class="setup-icon">💰</div>
                    <div class="setup-content">
                        <strong>Starting Budget</strong>
                        <span>10.000.000 Taler</span>
                    </div>
                </div>
                <div class="setup-item">
                    <div class="setup-icon">💎</div>
                    <div class="setup-content">
                        <strong>Premium Credits</strong>
                        <span>200 A$</span>
                    </div>
                </div>
                <div class="setup-item">
                    <div class="setup-icon">⚽</div>
                    <div class="setup-content">
                        <strong>Players</strong>
                        <span>20 auto-generated</span>
                    </div>
                </div>
                <div class="setup-item">
                    <div class="setup-icon">🏟️</div>
                    <div class="setup-content">
                        <strong>Stadium</strong>
                        <span>5.000 capacity</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <a href="/login" class="btn-primary">Go to Login</a>
            <a href="/" class="btn-secondary">Back to Homepage</a>
        </div>

        <div class="support-info">
            <p><strong>Didn't receive the email?</strong></p>
            <p>Check your spam folder or <a href="/support">contact our support team</a>.</p>
        </div>
    </div>
</div>
</body>
</html>