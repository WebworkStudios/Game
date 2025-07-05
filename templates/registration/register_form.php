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
        <p>Create your account and start your managerial career!</p>
    </div>

    <div class="registration-form-container">
        <form id="registrationForm" method="POST" action="/register" class="registration-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <!-- General Errors -->
            <?php if (isset($errors['general'])): ?>
                <div class="error-message general-error">
                    <?= htmlspecialchars($errors['general']) ?>
                </div>
            <?php endif; ?>

            <!-- Trainer Information -->
            <fieldset class="form-section">
                <legend>Trainer Information</legend>

                <div class="form-group">
                    <label for="trainer_name">Trainer Name</label>
                    <input
                            type="text"
                            id="trainer_name"
                            name="trainer_name"
                            value="<?= htmlspecialchars($old_input['trainer_name'] ?? '') ?>"
                            maxlength="50"
                            required
                            aria-describedby="trainer_name_help"
                    >
                    <small id="trainer_name_help">This will be your unique username (3-50 characters, alphanumeric,
                        spaces, hyphens, underscores)</small>
                    <?php if (isset($errors['trainer_name'])): ?>
                        <div class="field-error">
                            <?php foreach ($errors['trainer_name'] as $error): ?>
                                <div><?= htmlspecialchars($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($old_input['email'] ?? '') ?>"
                            maxlength="255"
                            required
                            aria-describedby="email_help"
                    >
                    <small id="email_help">We'll send a confirmation email to activate your account</small>
                    <?php if (isset($errors['email'])): ?>
                        <div class="field-error">
                            <?php foreach ($errors['email'] as $error): ?>
                                <div><?= htmlspecialchars($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </fieldset>

            <!-- Security -->
            <fieldset class="form-section">
                <legend>Security</legend>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            maxlength="255"
                            required
                            aria-describedby="password_help"
                    >
                    <small id="password_help">At least 8 characters with uppercase, lowercase, number and special
                        character</small>
                    <?php if (isset($errors['password'])): ?>
                        <div class="field-error">
                            <?php foreach ($errors['password'] as $error): ?>
                                <div><?= htmlspecialchars($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="password-strength" id="passwordStrength"></div>
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            minlength="8"
                            maxlength="255"
                            required
                            aria-describedby="password_confirmation_help"
                    >
                    <small id="password_confirmation_help">Re-enter your password</small>
                    <?php if (isset($errors['password_confirmation'])): ?>
                        <div class="field-error">
                            <?php foreach ($errors['password_confirmation'] as $error): ?>
                                <div><?= htmlspecialchars($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </fieldset>

            <!-- Team Information -->
            <fieldset class="form-section">
                <legend>Team Information</legend>

                <div class="form-group">
                    <label for="team_name">Team Name</label>
                    <input
                            type="text"
                            id="team_name"
                            name="team_name"
                            value="<?= htmlspecialchars($old_input['team_name'] ?? '') ?>"
                            maxlength="50"
                            required
                            aria-describedby="team_name_help"
                    >
                    <small id="team_name_help">Choose a unique name for your football team (3-50 characters)</small>
                    <?php if (isset($errors['team_name'])): ?>
                        <div class="field-error">
                            <?php foreach ($errors['team_name'] as $error): ?>
                                <div><?= htmlspecialchars($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </fieldset>

            <!-- Terms and Conditions -->
            <fieldset class="form-section">
                <legend>Agreement</legend>

                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input
                                type="checkbox"
                                id="terms_accepted"
                                name="terms_accepted"
                                value="1"
                                required
                        >
                        <span class="checkmark"></span>
                        I agree to the <a href="/terms" target="_blank">Terms of Service</a> and <a href="/privacy"
                                                                                                    target="_blank">Privacy
                            Policy</a>
                    </label>
                    <?php if (isset($errors['terms_accepted'])): ?>
                        <div class="field-error">
                            <?php foreach ($errors['terms_accepted'] as $error): ?>
                                <div><?= htmlspecialchars($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </fieldset>

            <!-- Submit Button -->
            <div class="form-actions">
                <button type="submit" class="btn-primary" id="submitBtn">
                    <span class="btn-text">Create Account</span>
                    <span class="btn-spinner" style="display: none;">⚽</span>
                </button>
            </div>

            <!-- Additional Links -->
            <div class="form-footer">
                <p>Already have an account? <a href="/login">Sign in here</a></p>
            </div>
        </form>
    </div>

    <!-- Game Information Sidebar -->
    <div class="game-info">
        <h3>🎮 What You Get</h3>
        <ul class="feature-list">
            <li>🏟️ Your own football stadium</li>
            <li>⚽ 20 unique players generated for you</li>
            <li>💰 10 Million Taler starting budget</li>
            <li>💎 200 A$ credits for premium features</li>
            <li>🏆 Compete in leagues with up to 18 teams</li>
            <li>📈 Advanced player development system</li>
        </ul>

        <div class="starting-conditions">
            <h4>Starting Conditions</h4>
            <div class="condition-item">
                <span class="label">Stadium:</span>
                <span class="value">5,000 standing capacity</span>
            </div>
            <div class="condition-item">
                <span class="label">Pitch Quality:</span>
                <span class="value">British (Standard)</span>
            </div>
            <div class="condition-item">
                <span class="label">Players:</span>
                <span class="value">20 auto-generated</span>
            </div>
            <div class="condition-item">
                <span class="label">Contract Length:</span>
                <span class="value">4 seasons</span>
            </div>
        </div>
    </div>
</div>

<script src="/js/registration.js"></script>
<script>
    // Initialize form validation and enhancements
    document.addEventListener('DOMContentLoaded', function () {
        initializeRegistrationForm();
    });
</script>
</body>
</html>