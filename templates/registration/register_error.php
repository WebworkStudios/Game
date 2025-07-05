<?php

/**
 * Registration Error Template
 * Template shown when registration fails unexpectedly
 *
 * File: templates/registration/register_error.php
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
        <p>Registration Error</p>
    </div>

    <div class="error-container">
        <div class="error-message">
            <div class="error-icon">❌</div>
            <h2>Registration Failed</h2>
            <p class="error-text"><?= htmlspecialchars($error_message) ?></p>
        </div>

        <div class="error-help">
            <h3>What can you do?</h3>
            <ul class="help-list">
                <li>Try registering again with the form below</li>
                <li>Check if your email address is correct</li>
                <li>Make sure your trainer name and team name are unique</li>
                <li>Contact our support team if the problem persists</li>
            </ul>
        </div>

        <div class="action-buttons">
            <a href="/register" class="btn-primary">Try Again</a>
            <a href="/" class="btn-secondary">Back to Homepage</a>
            <a href="/support" class="btn-tertiary">Contact Support</a>
        </div>

        <div class="support-info">
            <p><strong>Need help?</strong></p>
            <p>Our support team is here to help you get started. <a href="/support">Get in touch</a> and we'll resolve
                any issues quickly.</p>
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
        color: var(--error-color);
        margin-bottom: 15px;
    }

    .error-text {
        font-size: 1.1rem;
        color: var(--text-secondary);
        margin-bottom: 30px;
        background: #FFEBEE;
        border: 1px solid var(--error-color);
        border-radius: var(--border-radius);
        padding: 15px;
    }

    .error-help {
        text-align: left;
        margin: 30px 0;
        background: #F8F9FA;
        padding: 20px;
        border-radius: var(--border-radius);
    }

    .error-help h3 {
        color: var(--primary-color);
        margin-bottom: 15px;
    }

    .help-list {
        list-style: none;
        padding: 0;
    }

    .help-list li {
        padding: 8px 0;
        border-bottom: 1px solid #E0E0E0;
        position: relative;
        padding-left: 25px;
    }

    .help-list li:before {
        content: "→";
        position: absolute;
        left: 0;
        color: var(--primary-color);
        font-weight: bold;
    }

    .help-list li:last-child {
        border-bottom: none;
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

    .support-info {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
        color: var(--text-secondary);
    }

    .support-info a {
        color: var(--primary-color);
        text-decoration: none;
    }

    .support-info a:hover {
        text-decoration: underline;
    }
</style>
</body>
</html>