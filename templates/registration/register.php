<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($app_name) ?></title>
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet" href="/css/registration.css">
</head>
<body>
    <div class="registration-container">
        <div class="registration-header">
            <h1>⚽ Join Kickerscup</h1>
            <p>Start your football management journey today</p>
        </div>

        <div class="registration-form-container">
            <?php if (isset($errors['_rate_limit'])): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($errors['_rate_limit'][0]) ?>
                </div>
            <?php endif; ?>

            <?php if ($flash_error = $session->getFlash('error')): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($flash_error) ?>
                </div>
            <?php endif; ?>

            <form id="registrationForm" method="POST" action="/register" class="registration-form">
                <?= $csrf_field ?>

                <fieldset class="form-section">
                    <legend>Account Information</legend>

                    <div class="form-group">
                        <label for="trainer_name">Trainer Name *</label>
                        <input
                            type="text"
                            id="trainer_name"
                            name="trainer_name"
                            value="<?= htmlspecialchars($form_data['trainer_name'] ?? '') ?>"
                            required
                            maxlength="50"
                            autocomplete="username"
                        >
                        <small id="trainer_name_help">Choose your unique trainer name (3-50 characters, letters, numbers, spaces, hyphens, underscores only)</small>
                        <?php if (isset($errors['trainer_name'])): ?>
                            <div class="field-error">
                                <?php foreach ($errors['trainer_name'] as $error): ?>
                                    <div><?= htmlspecialchars($error) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($form_data['email'] ?? '') ?>"
                            required
                            maxlength="255"
                            autocomplete="email"
                        >
                        <small>We'll send you an activation email</small>
                        <?php if (isset($errors['email'])): ?>
                            <div class="field-error">
                                <?php foreach ($errors['email'] as $error): ?>
                                    <div><?= htmlspecialchars($error) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Security</legend>

                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            minlength="8"
                            maxlength="255"
                            autocomplete="new-password"
                        >
                        <div id="passwordStrength" class="password-strength"></div>
                        <small id="password_help">At least 8 characters with uppercase, lowercase, numbers, and special characters</small>
                        <?php if (isset($errors['password'])): ?>
                            <div class="field-error">
                                <?php foreach ($errors['password'] as $error): ?>
                                    <div><?= htmlspecialchars($error) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">Confirm Password *</label>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            minlength="8"
                            maxlength="255"
                            autocomplete="new-password"
                        >
                        <small>Please confirm your password</small>
                        <?php if (isset($errors['password_confirmation'])): ?>
                            <div class="field-error">
                                <?php foreach ($errors['password_confirmation'] as $error): ?>
                                    <div><?= htmlspecialchars($error) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Preferences</legend>

                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input
                                type="checkbox"
                                id="terms_accepted"
                                name="terms_accepted"
                                required
                                <?= (!empty($form_data['terms_accepted'])) ? 'checked' : '' ?>
                            >
                            <span class="checkmark"></span>
                            <span>I accept the <a href="/agb" target="_blank">Terms and Conditions</a> *</span>
                        </label>
                        <?php if (isset($errors['terms_accepted'])): ?>
                            <div class="field-error">
                                <?php foreach ($errors['terms_accepted'] as $error): ?>
                                    <div><?= htmlspecialchars($error) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input
                                type="checkbox"
                                id="newsletter_subscribed"
                                name="newsletter_subscribed"
                                <?= (!empty($form_data['newsletter_subscribed'])) ? 'checked' : '' ?>
                            >
                            <span class="checkmark"></span>
                            <span>I want to receive news and updates via email</span>
                        </label>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" id="submitBtn" class="btn-primary">
                        <span class="btn-text">Create Account</span>
                        <span class="btn-spinner" style="display: none;">⟳</span>
                    </button>
                </div>

                <div class="form-footer">
                    <p>Already have an account? <a href="/login">Sign in here</a></p>
                </div>
            </form>
        </div>

        <div class="game-info">
            <h3>What You Get</h3>
            <ul class="feature-list">
                <li>🏆 Join competitive leagues</li>
                <li>👥 Build and manage your team</li>
                <li>📊 Advanced analytics and statistics</li>
                <li>💰 Start with 1,000 Action Dollars</li>
                <li>🎯 Custom tactics and formations</li>
                <li>🌍 Global community of managers</li>
            </ul>

            <div class="starting-conditions">
                <h4>Starting Package</h4>
                <div class="condition-item">
                    <span class="label">Action Dollars:</span>
                    <span class="value">A$ 1,000</span>
                </div>
                <div class="condition-item">
                    <span class="label">Role:</span>
                    <span class="value">Manager</span>
                </div>
                <div class="condition-item">
                    <span class="label">Free Trial:</span>
                    <span class="value">Forever</span>
                </div>
            </div>
        </div>
    </div>

    <script src="/js/registration.js"></script>
</body>
</html>