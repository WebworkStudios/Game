<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate your account</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2E7D32; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px 20px; background: #f9f9f9; }
        .button { display: inline-block; background: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { padding: 20px; text-align: center; color: #666; font-size: 14px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>⚽ Welcome to <?= htmlspecialchars($app_name) ?>!</h1>
    </div>

    <div class="content">
        <h2>Hello <?= htmlspecialchars($trainer_name) ?>!</h2>

        <p>Thank you for joining Kickerscup! To complete your registration and start your football management journey, please activate your account by clicking the button below:</p>

        <a href="<?= htmlspecialchars($verification_url) ?>" class="button">Activate Account</a>

        <p>If the button doesn't work, copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #666;"><?= htmlspecialchars($verification_url) ?></p>

        <p><strong>Your starting package:</strong></p>
        <ul>
            <li>1,000 Action Dollars (A$)</li>
            <li>Full access to all game features</li>
            <li>Join competitive leagues</li>
        </ul>

        <p>This activation link will expire in 24 hours for security reasons.</p>
    </div>

    <div class="footer">
        <p>This email was sent because you registered at <?= htmlspecialchars($app_name) ?>.</p>
        <p>If you didn't register, please ignore this email.</p>
    </div>
</div>
</body>
</html><?php
// templates/email/verification.php
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate your account</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2E7D32; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px 20px; background: #f9f9f9; }
        .button { display: inline-block; background: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { padding: 20px; text-align: center; color: #666; font-size: 14px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>⚽ Welcome to <?= htmlspecialchars($app_name) ?>!</h1>
    </div>

    <div class="content">
        <h2>Hello <?= htmlspecialchars($trainer_name) ?>!</h2>

        <p>Thank you for joining Kickerscup! To complete your registration and start your football management journey, please activate your account by clicking the button below:</p>

        <a href="<?= htmlspecialchars($verification_url) ?>" class="button">Activate Account</a>

        <p>If the button doesn't work, copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #666;"><?= htmlspecialchars($verification_url) ?></p>

        <p><strong>Your starting package:</strong></p>
        <ul>
            <li>1,000 Action Dollars (A$)</li>
            <li>Full access to all game features</li>
            <li>Join competitive leagues</li>
        </ul>

        <p>This activation link will expire in 24 hours for security reasons.</p>
    </div>

    <div class="footer">
        <p>This email was sent because you registered at <?= htmlspecialchars($app_name) ?>.</p>
        <p>If you didn't register, please ignore this email.</p>
    </div>
</div>
</body>
</html>