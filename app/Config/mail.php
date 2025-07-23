<?php
declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | SMTP Configuration
    |--------------------------------------------------------------------------
    | Konfiguration für SMTP-Server
    | Empfohlen: Gmail SMTP, Postmark, Mailgun, oder lokaler SMTP-Server
    */
    'smtp' => [
        'host' => 'localhost',
        'port' => 'MAIL_PORT', 587,
        'username' => 'MAIL_USERNAME', '',
        'password' => 'MAIL_PASSWORD', '',
        'encryption' => 'MAIL_ENCRYPTION', 'tls', // tls, ssl, none
        'timeout' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | From Address
    |--------------------------------------------------------------------------
    | Standard-Absender für alle E-Mails
    */
    'from' => [
        'email' => 'noreply@kickerscup.com',
        'name' => 'Kickerscup.de',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    | Konfiguration für E-Mail-Queue (Newsletter, Batches)
    */
    'queue' => [
        'batch_size' => 50, // E-Mails pro Batch-Verarbeitung
        'retry_attempts' => 3, // Anzahl Wiederholungsversuche
        'retry_delay' => 300, // Wartezeit zwischen Versuchen (Sekunden)
        'priority_levels' => [
            'high' => 1,    // Verifikation, Password-Reset
            'normal' => 5,  // Standard-E-Mails
            'low' => 9,     // Newsletter
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Configuration
    |--------------------------------------------------------------------------
    | E-Mail-Template-Einstellungen
    */
    'templates' => [
        'path' => 'storage/mail/templates',
        'cache' => true,
        'default_variables' => [
            'app_name' => 'Kickerscup.de',
            'app_url' =>'http://localhost',
            'support_email' => 'support@kickerscup.de',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Configuration
    |--------------------------------------------------------------------------
    | Einstellungen für Entwicklungsumgebung
    */
    'development' => [
        'log_all_emails' => true,
        'fake_sending' => false, // Simuliert Versand ohne echte E-Mails
        'test_recipients' => [ // Alle E-Mails gehen an diese Adressen
            // 'developer@example.com',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    | Schutz vor Spam und Missbrauch
    */
    'rate_limiting' => [
        'max_per_minute' => 60,
        'max_per_hour' => 1000,
        'max_to_same_recipient_per_day' => 5,
    ],
];

// ========================================================================
// Beispiel .env Einträge:
// ========================================================================
/*
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@kickerscup.com
MAIL_FROM_NAME="KickersCup Manager"
APP_URL=https://kickerscup.com
SUPPORT_EMAIL=support@kickerscup.com

# Development
MAIL_LOG_EMAILS=true
MAIL_FAKE_SEND=false
*/