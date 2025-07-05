<?php

/**
 * Registration Confirmation Email Template
 * HTML email template for user registration confirmation
 *
 * File: templates/email/registration_confirmation.php
 * Directory: /templates/email/
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Football Manager</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .email-header {
            background: linear-gradient(135deg, #2E7D32 0%, #4CAF50 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .email-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
        }

        .email-content {
            padding: 30px 20px;
        }

        .welcome-message {
            text-align: center;
            margin-bottom: 30px;
        }

        .welcome-message h2 {
            color: #2E7D32;
            margin-bottom: 10px;
        }

        .team-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .team-info h3 {
            color: #2E7D32;
            margin-top: 0;
        }

        .info-grid {
            display: table;
            width: 100%;
            margin: 15px 0;
        }

        .info-row {
            display: table-row;
        }

        .info-label, .info-value {
            display: table-cell;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-label {
            font-weight: bold;
            color: #757575;
            width: 40%;
        }

        .info-value {
            color: #2E7D32;
            font-weight: 600;
        }

        .confirmation-button {
            text-align: center;
            margin: 30px 0;
        }

        .btn-confirm {
            display: inline-block;
            background: #2E7D32;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .login-info {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .login-info h3 {
            color: #2E7D32;
            margin-top: 0;
        }

        .email-footer {
            background: #f5f5f5;
            padding: 20px;
            text-align: center;
            color: #757575;
            font-size: 0.9rem;
        }

        .divider {
            height: 1px;
            background: #e0e0e0;
            margin: 20px 0;
        }

        @media (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }

            .email-content {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
<div class="email-container">
    <div class="email-header">
        <h1>🏆 Football Manager</h1>
        <p>Welcome to Your Managerial Career!</p>
    </div>

    <div class="email-content">
        <div class="welcome-message">
            <h2>Welcome, <?= htmlspecialchars($trainer_name) ?>!</h2>
            <p>Thank you for registering with Football Manager. Your account has been created successfully!</p>
        </div>

        <div class="team-info">
            <h3>🎮 Your Team: <?= htmlspecialchars($team_name) ?></h3>
            <p>Congratulations! Your team has been created and is ready for action. Here's what you've received:</p>

            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Starting Budget:</div>
                    <div class="info-value">10.000.000 Taler</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Premium Credits:</div>
                    <div class="info-value">200 A$</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Players Generated:</div>
                    <div class="info-value">20 Players</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Stadium Capacity:</div>
                    <div class="info-value">5.000 Seats</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Pitch Quality:</div>
                    <div class="info-value">British (Standard)</div>
                </div>
            </div>
        </div>

        <div class="confirmation-button">
            <a href="<?= htmlspecialchars($confirmation_url) ?>" class="btn-confirm">
                🔓 Activate Your Account
            </a>
        </div>

        <div class="login-info">
            <h3>🔐 Important: Activate Your Account</h3>
            <p><strong>Before you can log in, you must confirm your email address.</strong></p>
            <p>Click the "Activate Your Account" button above to complete your registration. Once confirmed, you can log
                in using:</p>
            <ul>
                <li><strong>Trainer Name:</strong> <?= htmlspecialchars($trainer_name) ?></li>
                <li><strong>Password:</strong> The password you created during registration</li>
            </ul>
            <p><a href="<?= htmlspecialchars($login_url) ?>">Login here after confirmation</a></p>
        </div>

        <div class="divider"></div>

        <p><strong>Next Steps:</strong></p>
        <ol>
            <li>Click the activation button above to confirm your email</li>
            <li>Log in to your account using your trainer name and password</li>
            <li>Explore your team and stadium</li>
            <li>Start competing in your league!</li>
        </ol>

        <div class="divider"></div>

        <p><strong>Need Help?</strong></p>
        <p>If you're having trouble with the confirmation link or have any questions, don't hesitate to contact our
            support team. We're here to help you get started!</p>

        <p><small><strong>Security Note:</strong> This link will expire in 24 hours for your security. If you didn't
                create this account, please ignore this email.</small></p>
    </div>

    <div class="email-footer">
        <p><strong>Football Manager Team</strong></p>
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>If you didn't register for this account, you can safely ignore this email.</p>
    </div>
</div>
</body>
</html>