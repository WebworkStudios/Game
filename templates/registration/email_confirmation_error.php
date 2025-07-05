<?php

/**
 * Email Confirmation Error Template
 * Template shown when email confirmation fails
 *
 * File: templates/registration/email_confirmation_error.php
 * Directory: /templates/registration/
 */
?>
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
        <p>Email Confirmation Error</p>
    </div>

    <div class="error-container">
        <div class="error-message">
            <div class="error-icon">⚠️</div>
            <h2>Confirmation Failed</h2>
            <p class="error-text"><?= htmlspecialchars($error_message) ?></p>
        </div>

        <div class="error-reasons">
            <h3>Common Reasons</h3>
            <ul class="reasons-list">
                <li>
                    <strong>Expired Link:</strong>
                    The confirmation link may have expired. Links are valid for a limited time.
                </li>
                <li>
                    <strong>Already Confirmed:</strong>
                    Your email might already be confirmed. Try logging in instead.
                </li>
                <li>
                    <strong>Invalid Link:</strong>
                    The link might be corrupted or incomplete. Check if you copied the full URL.
                </li>
                <li>
                    <strong>Account Issues:</strong>
                    There might be an issue with your account. Contact support for help.
                </li>
            </ul>
        </div>

        <div class="solutions">
            <h3>What You Can Do</h3>
            <div class="solution-cards">
                <div class="solution-card">
                    <div class="solution-icon">🔄</div>
                    <h4>Try Login</h4>
                    <p>Your account might already be active. Try logging in with your credentials.</p>
                    <a href="/login" class="solution-btn">Login Now</a>
                </div>

                <div class="solution-card">
                    <div class="solution-icon">📧</div>
                    <h4>Request New Link</h4>
                    <p>Get a fresh confirmation email with a new activation link.</p>
                    <a href="/resend-confirmation" class="solution-btn">Resend Email</a>
                </div>

                <div class="solution-card">
                    <div class="solution-icon">💬</div>
                    <h4>Contact Support</h4>
                    <p>Our team can help resolve any account activation issues.</p>
                    <a href="/support" class="solution-btn">Get Help</a>
                </div>
            </div>
        </div>

        <div class="troubleshooting">
            <h3>📋 Troubleshooting Tips</h3>
            <div class="tips-grid">
                <div class="tip">
                    <span class="tip-number">1</span>
                    <span class="tip-text">Check your spam/junk folder for the confirmation email</span>
                </div>
                <div class="tip">
                    <span class="tip-number">2</span>
                    <span class="tip-text">Make sure you clicked the complete link from the email</span>
                </div>
                <div class="tip">
                    <span class="tip-number">3</span>
                    <span class="tip-text">Try copying and pasting the link into a new browser tab</span>
                </div>
                <div class="tip">
                    <span class="tip-number">4</span>
                    <span class="tip-text">Clear your browser cache and cookies, then try again</span>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <a href="/login" class="btn-primary">Try Login</a>
            <a href="/register" class="btn-secondary">Register Again</a>
            <a href="/" class="btn-tertiary">Homepage</a>
        </div>
    </div>
</div>

<style>
    .error-container {
        background: var(--card-background);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        padding: 30px;
        text-align: center;
        animation: slideInLeft 0.6s ease-out;
    }

    .error-icon {
        font-size: 4rem;
        margin-bottom: 20px;
    }

    .error-message h2 {
        color: var(--warning-color);
        margin-bottom: 15px;
    }

    .error-text {
        font-size: 1.1rem;
        color: var(--text-secondary);
        margin-bottom: 30px;
        background: #FFF3E0;
        border: 1px solid var(--warning-color);
        border-radius: var(--border-radius);
        padding: 15px;
    }

    .error-reasons, .solutions, .troubleshooting {
        text-align: left;
        margin: 30px 0;
        background: #F8F9FA;
        padding: 20px;
        border-radius: var(--border-radius);
    }

    .error-reasons h3, .solutions h3, .troubleshooting h3 {
        color: var(--primary-color);
        margin-bottom: 15px;
    }

    .reasons-list {
        list-style: none;
        padding: 0;
    }

    .reasons-list li {
        padding: 12px 0;
        border-bottom: 1px solid #E0E0E0;
    }

    .reasons-list li:last-child {
        border-bottom: none;
    }

    .solution-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .solution-card {
        background: white;
        padding: 20px;
        border-radius: var(--border-radius);
        border: 1px solid var(--border-color);
        text-align: center;
    }

    .solution-icon {
        font-size: 2rem;
        margin-bottom: 10px;
    }

    .solution-card h4 {
        color: var(--primary-color);
        margin-bottom: 10px;
    }

    .solution-card p {
        color: var(--text-secondary);
        margin-bottom: 15px;
        font-size: 0.9rem;
    }

    .solution-btn {
        display: inline-block;
        padding: 8px 16px;
        background: var(--primary-color);
        color: white;
        text-decoration: none;
        border-radius: var(--border-radius);
        font-size: 0.9rem;
        transition: var(--transition);
    }

    .solution-btn:hover {
        background: var(--primary-hover);
    }

    .tips-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }

    .tip {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: white;
        border-radius: var(--border-radius);
        border: 1px solid var(--border-color);
    }

    .tip-number {
        background: var(--primary-color);
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        flex-shrink: 0;
    }

    .tip-text {
        font-size: 0.9rem;
    }

    .action-buttons {
        margin: 30px 0;
    }

    .action-buttons a {
        display: inline-block;
        margin: 5px 10px;
        padding: 12px 25px;
        border-radius: var(--border-radius);
        text-decoration: none;
        font-weight: 500;
        transition: var(--transition);
    }

    .btn-primary {
        background: var(--primary-color);
        color: white;
    }

    .btn-primary:hover {
        background: var(--primary-hover);
    }

    .btn-secondary {
        background: var(--text-secondary);
        color: white;
    }

    .btn-secondary:hover {
        background: #424242;
    }

    .btn-tertiary {
        background: var(--info-color);
        color: white;
    }

    .btn-tertiary:hover {
        background: #1976D2;
    }
</style>
</body>
</html>