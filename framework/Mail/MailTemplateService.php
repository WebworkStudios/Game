<?php
declare(strict_types=1);

namespace Framework\Mail;

use Framework\Database\ConnectionManager;
use Framework\Database\MySQLGrammar;
use Framework\Database\QueryBuilder;
use InvalidArgumentException;

/**
 * Mail Template Service - Verwaltet E-Mail-Vorlagen
 */
class MailTemplateService
{
    private QueryBuilder $queryBuilder;

    public function __construct(
        private readonly ConnectionManager $connectionManager,
    )
    {
        $this->queryBuilder = new QueryBuilder(
            connectionManager: $this->connectionManager,
            grammar: new MySQLGrammar()
        );
    }

    /**
     * Rendert Template mit Daten
     */
    public function renderTemplate(string $name, array $data): array
    {
        $template = $this->getTemplate($name);
        if (!$template) {
            throw new InvalidArgumentException("Template '{$name}' not found");
        }

        $subject = $this->replaceVariables($template['subject'], $data);
        $bodyHtml = $this->replaceVariables($template['body_html'], $data);
        $bodyText = $template['body_text']
            ? $this->replaceVariables($template['body_text'], $data)
            : null;

        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ];
    }

    /**
     * Holt Template nach Name
     */
    public function getTemplate(string $name): ?array
    {
        return $this->queryBuilder
            ->table('mail_templates')
            ->where('name', $name)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Ersetzt Variablen in Template-Text
     */
    private function replaceVariables(string $text, array $data): string
    {
        $result = $text;

        foreach ($data as $key => $value) {
            $result = str_replace("{{$key}}", (string)$value, $result);
        }

        return $result;
    }

    /**
     * Erstellt Standard-Templates
     */
    public function createDefaultTemplates(): void
    {
        $templates = [
            [
                'name' => 'email_verification',
                'subject' => 'Bitte bestätige deine E-Mail-Adresse - {{app_name}}',
                'body_html' => $this->getEmailVerificationTemplate(),
                'variables' => ['username', 'verification_url', 'app_name'],
            ],
            [
                'name' => 'password_reset',
                'subject' => 'Passwort zurücksetzen - {{app_name}}',
                'body_html' => $this->getPasswordResetTemplate(),
                'variables' => ['username', 'reset_url', 'app_name'],
            ],
            [
                'name' => 'welcome',
                'subject' => 'Willkommen bei {{app_name}}!',
                'body_html' => $this->getWelcomeTemplate(),
                'variables' => ['username', 'app_name', 'login_url'],
            ],
        ];

        foreach ($templates as $template) {
            $this->saveTemplate(
                $template['name'],
                $template['subject'],
                $template['body_html'],
                null,
                $template['variables']
            );
        }
    }

    /**
     * E-Mail-Verifikations-Template
     */
    private function getEmailVerificationTemplate(): string
    {
        return '
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Mail bestätigen</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚽ {{app_name}}</h1>
        <p>Willkommen im besten Fußball-Manager!</p>
    </div>
    <div class="content">
        <h2>Hallo {{username}}!</h2>
        <p>Vielen Dank für deine Registrierung bei {{app_name}}! Um deinen Account zu aktivieren, bestätige bitte deine E-Mail-Adresse:</p>
        
        <div style="text-align: center;">
            <a href="{{verification_url}}" class="button">✅ E-Mail bestätigen</a>
        </div>
        
        <p>Falls der Button nicht funktioniert, kopiere diesen Link in deinen Browser:</p>
        <p style="word-break: break-all; background: #e9ecef; padding: 10px; border-radius: 5px;">{{verification_url}}</p>
        
        <p><strong>Wichtig:</strong> Dieser Link ist 24 Stunden gültig.</p>
        
        <p>Falls du dich nicht registriert hast, ignoriere diese E-Mail einfach.</p>
    </div>
    <div class="footer">
        <p>© {{app_name}} - Dein Fußball-Manager-Erlebnis</p>
    </div>
</body>
</html>';
    }

    /**
     * Passwort-Reset-Template
     */
    private function getPasswordResetTemplate(): string
    {
        return '
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort zurücksetzen</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔐 {{app_name}}</h1>
        <p>Passwort zurücksetzen</p>
    </div>
    <div class="content">
        <h2>Hallo {{username}}!</h2>
        <p>Du hast eine Passwort-Zurücksetzung für deinen {{app_name}} Account angefordert.</p>
        
        <div style="text-align: center;">
            <a href="{{reset_url}}" class="button">🔑 Neues Passwort setzen</a>
        </div>
        
        <p>Falls der Button nicht funktioniert, kopiere diesen Link in deinen Browser:</p>
        <p style="word-break: break-all; background: #e9ecef; padding: 10px; border-radius: 5px;">{{reset_url}}</p>
        
        <div class="warning">
            <strong>⚠️ Wichtige Sicherheitshinweise:</strong>
            <ul>
                <li>Dieser Link ist nur 24 Stunden gültig</li>
                <li>Er kann nur einmal verwendet werden</li>
                <li>Falls du diese Anfrage nicht gestellt hast, ignoriere diese E-Mail</li>
            </ul>
        </div>
        
        <p>Dein aktuelles Passwort bleibt gültig, bis du ein neues festlegst.</p>
    </div>
    <div class="footer">
        <p>© {{app_name}} - Sicherheit geht vor!</p>
    </div>
</body>
</html>';
    }

    /**
     * Willkommens-Template
     */
    private function getWelcomeTemplate(): string
    {
        return '
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Willkommen!</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #00b894 0%, #00cec9 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
        .feature { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007bff; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Willkommen bei {{app_name}}!</h1>
        <p>Dein Fußball-Manager-Abenteuer beginnt jetzt!</p>
    </div>
    <div class="content">
        <h2>Hallo {{username}}!</h2>
        <p>Herzlich willkommen bei {{app_name}}! Dein Account ist jetzt aktiviert und du kannst loslegen.</p>
        
        <div style="text-align: center;">
            <a href="{{login_url}}" class="button">🚀 Jetzt einloggen</a>
        </div>
        
        <h3>Was dich erwartet:</h3>
        <div class="feature">
            <strong>⚽ Team-Management</strong><br>
            Stelle dein Traumteam zusammen und führe es zum Erfolg!
        </div>
        <div class="feature">
            <strong>💰 Transfermarkt</strong><br>
            Kaufe und verkaufe Spieler auf dem dynamischen Transfermarkt.
        </div>
        <div class="feature">
            <strong>🏆 Turniere</strong><br>
            Nimm an spannenden Turnieren teil und gewinne Preise!
        </div>
        
        <p>Falls du Fragen hast, zögere nicht uns zu kontaktieren. Viel Spaß beim Spielen!</p>
    </div>
    <div class="footer">
        <p>© {{app_name}} - Dein Weg zum Erfolg beginnt hier!</p>
    </div>
</body>
</html>';
    }

    /**
     * Erstellt oder aktualisiert Template
     */
    public function saveTemplate(
        string  $name,
        string  $subject,
        string  $bodyHtml,
        ?string $bodyText = null,
        array   $variables = []
    ): int
    {
        $data = [
            'name' => $name,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'variables' => json_encode($variables),
            'is_active' => true,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Prüfe ob Template existiert
        $existing = $this->queryBuilder
            ->table('mail_templates')
            ->where('name', $name)
            ->first();

        if ($existing) {
            $this->queryBuilder
                ->table('mail_templates')
                ->where('name', $name)
                ->update($data);
            return $existing['id'];
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            return $this->queryBuilder
                ->table('mail_templates')
                ->insertGetId($data);
        }
    }
}